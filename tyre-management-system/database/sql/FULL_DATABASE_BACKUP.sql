-- =============================================================================
-- Tyre ERP — FULL DATABASE BACKUP (disaster recovery)
-- =============================================================================
-- Database: tyre_erp
--
-- THIS FILE:
--   After you run tools/backup_database.bat (or: php tools/db_sync.php backup)
--   this file contains ALL tables + ALL live data — use it to restore everything.
--
--   Until then, this copy holds full SCHEMA + default logins (no business data).
--
-- RESTORE (command line):
--   c:\xampp\mysql\bin\mysql.exe -u root < database\sql\FULL_DATABASE_BACKUP.sql
--
-- phpMyAdmin: Import this file.
-- Default logins password: password
--
-- Refresh backup: tools\backup_database.bat
-- Guide: database\sql\BACKUP_AND_RESTORE.md
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 000: database
CREATE DATABASE IF NOT EXISTS `tyre_erp` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tyre_erp`;

-- 001: Core ERP tables (users, HR, manufacturing, settings)
USE `tyre_erp`;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL UNIQUE,
    username VARCHAR(80) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Employee',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL UNIQUE,
    employee_code VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    father_name VARCHAR(120) NULL,
    dob DATE NULL,
    aadhaar_number CHAR(12) NULL,
    email VARCHAR(150) NULL,
    password_hash VARCHAR(255) NULL,
    employee_type ENUM('Staff','Worker') NOT NULL DEFAULT 'Staff',
    department VARCHAR(100) NOT NULL,
    designation VARCHAR(120) NULL,
    role VARCHAR(100) NOT NULL DEFAULT 'Employee',
    salary_type VARCHAR(50) NOT NULL DEFAULT 'Monthly',
    shift_timing VARCHAR(80) NULL,
    shift_start TIME NULL,
    shift_end TIME NULL,
    contact_no VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    profile_image VARCHAR(255) NULL,
    joining_date DATE NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_leave_limit DECIMAL(6,2) NOT NULL DEFAULT 12,
    half_paid_leave_limit DECIMAL(6,2) NOT NULL DEFAULT 6,
    hra_percentage DECIMAL(6,2) NOT NULL DEFAULT 40,
    hra_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    pf_applicable TINYINT(1) NOT NULL DEFAULT 1,
    pf_percentage DECIMAL(6,2) NOT NULL DEFAULT 12,
    esi_applicable TINYINT(1) NOT NULL DEFAULT 1,
    esi_percentage DECIMAL(6,2) NOT NULL DEFAULT 0.75,
    esi_salary_limit DECIMAL(12,2) NOT NULL DEFAULT 21000,
    medical_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    dearness_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    travel_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    other_allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    metro TINYINT(1) NOT NULL DEFAULT 0,
    payroll_auto_indian TINYINT(1) NOT NULL DEFAULT 1,
    gratuity_monthly DECIMAL(12,2) NOT NULL DEFAULT 0,
    gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    overtime_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    daily_wage DECIMAL(12,2) NOT NULL DEFAULT 0,
    hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_employees_aadhaar (aadhaar_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    shift ENUM('Morning','Evening','Night') NOT NULL DEFAULT 'Morning',
    status VARCHAR(40) NOT NULL DEFAULT 'Present',
    remarks VARCHAR(255) NULL,
    punch_in_time DATETIME NULL,
    punch_out_time DATETIME NULL,
    total_hours DECIMAL(8,2) NULL DEFAULT NULL,
    overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
    is_late TINYINT(1) NOT NULL DEFAULT 0,
    is_early_exit TINYINT(1) NOT NULL DEFAULT 0,
    is_emergency_duty TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_attendance_employee_day (employee_id, attendance_date),
    INDEX idx_attendance_date (attendance_date),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS company_holidays (
    holiday_date DATE NOT NULL PRIMARY KEY,
    label VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hr_holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL,
    holiday_name VARCHAR(160) NOT NULL,
    holiday_type VARCHAR(50) NOT NULL,
    department_scope VARCHAR(120) NOT NULL DEFAULT '',
    remarks VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_hr_holiday_scope (holiday_date, department_scope),
    INDEX idx_hr_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    leave_type VARCHAR(50) NOT NULL DEFAULT 'General',
    reason VARCHAR(255) NOT NULL,
    leave_category ENUM('Paid','Half Paid','Unpaid') NOT NULL DEFAULT 'Paid',
    is_paid TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('Applied','Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    paid_days DECIMAL(6,2) NOT NULL DEFAULT 0,
    half_paid_days DECIMAL(6,2) NOT NULL DEFAULT 0,
    unpaid_days DECIMAL(6,2) NOT NULL DEFAULT 0,
    total_days DECIMAL(6,2) NOT NULL DEFAULT 0,
    is_emergency TINYINT(1) NOT NULL DEFAULT 0,
    auto_approved TINYINT(1) NOT NULL DEFAULT 0,
    entry_source VARCHAR(20) NOT NULL DEFAULT 'employee',
    recorded_by INT NULL,
    staffing_risk VARCHAR(20) NOT NULL DEFAULT 'Safe',
    system_note VARCHAR(255) NULL,
    day_allocation_json TEXT NULL,
    rejection_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_dates (from_date, to_date),
    CONSTRAINT fk_leaves_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month_year CHAR(7) NOT NULL,
    present_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    half_paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    unpaid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
    overtime_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    basic DECIMAL(12,2) NOT NULL DEFAULT 0,
    hra_percentage DECIMAL(6,2) NOT NULL DEFAULT 0,
    hra_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    pf_percentage DECIMAL(6,2) NOT NULL DEFAULT 0,
    pf_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    esi_employee_percentage DECIMAL(6,2) NOT NULL DEFAULT 0,
    esi_employee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    esi_employer_percentage DECIMAL(6,2) NOT NULL DEFAULT 0,
    esi_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    medical_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    dearness_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    travel_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    other_allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    pf_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    gratuity_accrual DECIMAL(12,2) NOT NULL DEFAULT 0,
    leave_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    half_day_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    late_entry_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    is_draft TINYINT(1) NOT NULL DEFAULT 0,
    paid_at DATETIME NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_salary_employee_month (employee_id, month_year),
    CONSTRAINT fk_salaries_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS salary_increments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    old_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    new_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    increment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    increment_percentage DECIMAL(8,2) NOT NULL DEFAULT 0,
    effective_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_increment_employee (employee_id),
    CONSTRAINT fk_increment_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS raw_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_name VARCHAR(150) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
    supplier_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_raw_stock (stock_qty),
    CONSTRAINT fk_raw_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_code VARCHAR(50) NOT NULL UNIQUE,
    machine_name VARCHAR(150) NOT NULL,
    status ENUM('Active','Under Maintenance','Inactive') NOT NULL DEFAULT 'Active',
    last_maintenance_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS production (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    machine_id INT NOT NULL,
    shift ENUM('Morning','Evening','Night') NOT NULL,
    raw_material_id INT NOT NULL,
    material_used_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    output_quantity INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_production_date (production_date),
    CONSTRAINT fk_production_machine FOREIGN KEY (machine_id) REFERENCES machines(id),
    CONSTRAINT fk_production_raw FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS quality_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    inspector_name VARCHAR(150) NOT NULL,
    passed_qty INT NOT NULL DEFAULT 0,
    failed_qty INT NOT NULL DEFAULT 0,
    quality_status ENUM('Pass','Fail') NOT NULL DEFAULT 'Pass',
    defects VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quality_production FOREIGN KEY (production_id) REFERENCES production(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(120) NOT NULL,
    batch_ref VARCHAR(80) NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 0,
    warehouse_location VARCHAR(120) NOT NULL DEFAULT 'Main Warehouse',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inventory_qty (qty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS dispatch (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispatch_code VARCHAR(40) NULL,
    inventory_id INT NULL,
    order_no VARCHAR(60) NOT NULL UNIQUE,
    customer_name VARCHAR(150) NOT NULL,
    customer_id INT NULL,
    tyre_type VARCHAR(120) NULL,
    invoice_no VARCHAR(80) NOT NULL UNIQUE,
    vehicle_no VARCHAR(40) NULL,
    vehicle_id INT NULL,
    driver_name VARCHAR(150) NULL,
    driver_id INT NULL,
    transport_company VARCHAR(150) NULL,
    transport_company_id INT NULL,
    dispatch_date DATE NOT NULL,
    qty INT NOT NULL,
    gross_weight_kg DECIMAL(12,2) NULL,
    tare_weight_kg DECIMAL(12,2) NULL,
    net_weight_kg DECIMAL(12,2) NULL,
    remarks VARCHAR(500) NULL,
    status ENUM('Pending','Dispatched','Delivered') NOT NULL DEFAULT 'Pending',
    stock_deducted TINYINT(1) NOT NULL DEFAULT 0,
    dispatch_status ENUM('Created','In Transit','Delivered','Cancelled') NOT NULL DEFAULT 'Created',
    tracking_no VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dispatch_date (dispatch_date),
    INDEX idx_dispatch_status (status),
    CONSTRAINT fk_dispatch_inventory FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS defect_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quality_check_id INT NOT NULL,
    production_id INT NOT NULL,
    failed_qty INT NOT NULL,
    defect_notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_defect_quality FOREIGN KEY (quality_check_id) REFERENCES quality_checks(id) ON DELETE CASCADE,
    CONSTRAINT fk_defect_production FOREIGN KEY (production_id) REFERENCES production(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_name', 'Ralson India Private Limited - Tyre ERP');

-- 002: Department hierarchy (categories, departments, designations)
USE `tyre_erp`;

CREATE TABLE IF NOT EXISTS department_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(120) NOT NULL,
    category_code VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dept_cat_code (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    department_name VARCHAR(160) NOT NULL,
    department_short_name VARCHAR(80) NULL,
    department_code VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    min_staff_required INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dept_code (department_code),
    UNIQUE KEY uk_dept_cat_name (category_id, department_name),
    INDEX idx_dept_category (category_id),
    CONSTRAINT fk_dept_category FOREIGN KEY (category_id) REFERENCES department_categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS designations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    designation_name VARCHAR(120) NOT NULL,
    designation_code VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_desig_dept_code (department_id, designation_code),
    INDEX idx_desig_department (department_id),
    CONSTRAINT fk_desig_department FOREIGN KEY (department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 005: Payroll settings and legacy payroll summary table
USE `tyre_erp`;

CREATE TABLE IF NOT EXISTS payroll_settings (
    id INT NOT NULL PRIMARY KEY DEFAULT 1,
    basic_pct_of_gross DECIMAL(6,2) NOT NULL DEFAULT 50.00,
    da_pct_of_basic DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    da_enabled TINYINT(1) NOT NULL DEFAULT 0,
    hra_pct_non_metro DECIMAL(6,2) NOT NULL DEFAULT 40.00,
    hra_pct_metro DECIMAL(6,2) NOT NULL DEFAULT 50.00,
    medical_enabled TINYINT(1) NOT NULL DEFAULT 1,
    medical_mode VARCHAR(12) NOT NULL DEFAULT 'fixed',
    medical_fixed DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    medical_pct_of_basic DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    travel_enabled TINYINT(1) NOT NULL DEFAULT 0,
    travel_mode VARCHAR(12) NOT NULL DEFAULT 'fixed',
    travel_fixed DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    travel_pct_of_basic DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    gratuity_pct_of_basic DECIMAL(7,3) NOT NULL DEFAULT 4.810,
    pf_employee_pct DECIMAL(6,2) NOT NULL DEFAULT 12.00,
    pf_employer_pct DECIMAL(6,2) NOT NULL DEFAULT 12.00,
    esi_employee_pct DECIMAL(6,2) NOT NULL DEFAULT 0.75,
    esi_employer_pct DECIMAL(6,2) NOT NULL DEFAULT 3.25,
    esi_gross_limit DECIMAL(12,2) NOT NULL DEFAULT 21000.00,
    working_days_default DECIMAL(6,2) NOT NULL DEFAULT 26.00,
    shift_hours_default DECIMAL(6,2) NOT NULL DEFAULT 8.00,
    ot_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    late_deduction_pct_of_daily DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO payroll_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    present_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    unpaid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    overtime_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_payroll_employee_month_year (employee_id, month, year),
    CONSTRAINT fk_payroll_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Leave notifications (also created by migration 006 on legacy DBs)
CREATE TABLE IF NOT EXISTS leave_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL,
    leave_id INT NULL,
    notice_type VARCHAR(40) NOT NULL,
    message VARCHAR(500) NOT NULL,
    audience ENUM('employee','hr') NOT NULL DEFAULT 'employee',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ln_employee (employee_id),
    INDEX idx_ln_audience (audience, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 008: HR notifications (leave audit columns are in 001)
CREATE TABLE IF NOT EXISTS hr_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_key VARCHAR(120) NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dismissed_at TIMESTAMP NULL,
    UNIQUE KEY uq_hr_notif_user_key (user_id, notification_key),
    INDEX idx_hr_notif_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('leave_auto_approve_enabled', '0');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('leave_min_present_pct', '50');

-- 007: Employee department / designation links
ALTER TABLE employees ADD COLUMN department_id INT NULL;
ALTER TABLE employees ADD COLUMN designation_id INT NULL;
CREATE INDEX idx_employees_department_id ON employees (department_id);
CREATE INDEX idx_employees_designation_id ON employees (designation_id);

CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(191) NOT NULL PRIMARY KEY,
    batch INT NOT NULL DEFAULT 1,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 009: Production workflow columns
ALTER TABLE machines ADD COLUMN IF NOT EXISTS machine_type VARCHAR(80) NULL AFTER machine_name;
ALTER TABLE machines ADD COLUMN IF NOT EXISTS shift_capacity INT NOT NULL DEFAULT 0 AFTER machine_type;
ALTER TABLE machines ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER last_maintenance_date;
ALTER TABLE production ADD COLUMN IF NOT EXISTS operator_id INT NULL AFTER shift;
ALTER TABLE production ADD COLUMN IF NOT EXISTS tyre_type VARCHAR(120) NULL AFTER operator_id;
ALTER TABLE production ADD COLUMN IF NOT EXISTS planned_quantity INT NOT NULL DEFAULT 0 AFTER tyre_type;
ALTER TABLE production ADD COLUMN IF NOT EXISTS rejected_quantity INT NOT NULL DEFAULT 0 AFTER output_quantity;
ALTER TABLE production ADD COLUMN IF NOT EXISTS downtime_minutes INT NOT NULL DEFAULT 0 AFTER rejected_quantity;
ALTER TABLE production ADD COLUMN IF NOT EXISTS remarks VARCHAR(500) NULL AFTER downtime_minutes;
ALTER TABLE production ADD COLUMN IF NOT EXISTS efficiency_pct DECIMAL(6,2) NULL AFTER remarks;
ALTER TABLE production ADD COLUMN IF NOT EXISTS entry_status VARCHAR(30) NOT NULL DEFAULT 'Submitted' AFTER efficiency_pct;
ALTER TABLE production ADD COLUMN IF NOT EXISTS inventory_deducted TINYINT(1) NOT NULL DEFAULT 0 AFTER entry_status;
ALTER TABLE production ADD COLUMN IF NOT EXISTS payroll_ot_flag TINYINT(1) NOT NULL DEFAULT 0 AFTER inventory_deducted;

-- 010: Production orders workflow
CREATE TABLE IF NOT EXISTS production_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(40) NOT NULL UNIQUE,
    tyre_type VARCHAR(120) NOT NULL,
    target_qty INT NOT NULL DEFAULT 0,
    deadline DATE NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'Normal',
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    current_stage VARCHAR(40) NOT NULL DEFAULT 'Mixing',
    total_produced INT NOT NULL DEFAULT 0,
    total_rejected INT NOT NULL DEFAULT 0,
    total_downtime INT NOT NULL DEFAULT 0,
    qc_passed_qty INT NOT NULL DEFAULT 0,
    qc_failed_qty INT NOT NULL DEFAULT 0,
    inventory_deducted TINYINT(1) NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS production_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_name VARCHAR(40) NOT NULL,
    stage_order TINYINT NOT NULL DEFAULT 1,
    machine_id INT NULL,
    operator_id INT NULL,
    shift VARCHAR(20) NULL DEFAULT 'Morning',
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    produced_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    downtime_minutes INT NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    remarks VARCHAR(500) NULL,
    UNIQUE KEY uk_order_stage (order_id, stage_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS production_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_id INT NULL,
    action VARCHAR(60) NOT NULL,
    message VARCHAR(500) NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS machine_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_id INT NOT NULL,
    machine_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS production_downtime (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_id INT NULL,
    minutes INT NOT NULL DEFAULT 0,
    reason VARCHAR(255) NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS production_bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tyre_type VARCHAR(120) NOT NULL,
    raw_material_id INT NOT NULL,
    qty_per_unit DECIMAL(12,4) NOT NULL DEFAULT 0,
    UNIQUE KEY uk_bom_tyre_material (tyre_type, raw_material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 011: Production stage logs
CREATE TABLE IF NOT EXISTS production_stage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_id INT NULL,
    stage_name VARCHAR(40) NOT NULL,
    machine_id INT NULL,
    operator_id INT NULL,
    shift VARCHAR(20) NULL,
    status VARCHAR(30) NOT NULL,
    produced_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    downtime_minutes INT NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    remarks VARCHAR(500) NULL,
    action_type VARCHAR(40) NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 012: Inventory warehouse (see migrations/012_inventory_warehouse_module.sql)
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER address;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS materials_supplied VARCHAR(500) NULL AFTER status;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS material_code VARCHAR(40) NULL AFTER id;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS category VARCHAR(80) NOT NULL DEFAULT 'General' AFTER material_name;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS storage_location VARCHAR(120) NOT NULL DEFAULT 'Main Store' AFTER reorder_level;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS remarks VARCHAR(500) NULL AFTER storage_location;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER remarks;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS max_stock_level DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER reorder_level;

CREATE TABLE IF NOT EXISTS stock_inward (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inward_date DATE NOT NULL,
    supplier_id INT NULL,
    invoice_no VARCHAR(80) NULL,
    material_id INT NOT NULL,
    batch_no VARCHAR(80) NULL,
    expiry_date DATE NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    received_by VARCHAR(150) NULL,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inward_date (inward_date),
    INDEX idx_inward_material (material_id),
    CONSTRAINT fk_inward_material FOREIGN KEY (material_id) REFERENCES raw_materials(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usage_date DATE NOT NULL,
    material_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    usage_type ENUM('production','manual') NOT NULL DEFAULT 'manual',
    department VARCHAR(40) NULL,
    usage_reason VARCHAR(80) NULL,
    reference_id INT NULL,
    remarks VARCHAR(500) NULL,
    created_by VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usage_date (usage_date),
    INDEX idx_usage_material (material_id),
    CONSTRAINT fk_usage_material FOREIGN KEY (material_id) REFERENCES raw_materials(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inventory_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activity_type VARCHAR(40) NOT NULL,
    material_id INT NULL,
    qty_change DECIMAL(12,2) NOT NULL DEFAULT 0,
    message VARCHAR(500) NOT NULL,
    INDEX idx_inv_activity_at (activity_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adjust_date DATE NOT NULL,
    material_id INT NOT NULL,
    previous_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    actual_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    difference_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    reason VARCHAR(80) NOT NULL,
    operator_name VARCHAR(150) NULL,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_adj_date (adjust_date),
    CONSTRAINT fk_adj_material FOREIGN KEY (material_id) REFERENCES raw_materials(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 013: Dispatch & logistics
CREATE TABLE IF NOT EXISTS dispatch_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    company VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    gst_number VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(80) NULL,
    state VARCHAR(80) NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dcust_name (customer_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS dispatch_transport_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(120) NULL,
    phone VARCHAR(30) NULL,
    gst_number VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_transport_name (company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS dispatch_drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    license_number VARCHAR(60) NULL,
    vehicle_id INT NULL,
    vehicle_no VARCHAR(40) NULL,
    transport_company_id INT NULL,
    transport_company VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ddriver_name (driver_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS dispatch_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_number VARCHAR(40) NOT NULL,
    vehicle_type VARCHAR(60) NULL,
    capacity VARCHAR(40) NULL,
    driver_id INT NULL,
    transport_company_id INT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vehicle_number (vehicle_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 014: Production entry tables
CREATE TABLE IF NOT EXISTS mixing_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    machine_id INT NULL,
    operator_id INT NULL,
    tyre_type VARCHAR(120) NOT NULL,
    produced_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    rejected_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS building_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    machine_id INT NULL,
    operator_id INT NULL,
    tyre_type VARCHAR(120) NOT NULL,
    produced_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS curing_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    machine_id INT NULL,
    operator_id INT NULL,
    tyre_type VARCHAR(120) NOT NULL DEFAULT 'Tyre',
    produced_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    downtime_minutes INT NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS qc_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    inspector_name VARCHAR(150) NOT NULL,
    tyre_type VARCHAR(120) NOT NULL,
    checked_qty INT NOT NULL DEFAULT 0,
    passed_qty INT NOT NULL DEFAULT 0,
    failed_qty INT NOT NULL DEFAULT 0,
    defect_type VARCHAR(120) NULL,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO schema_migrations (migration, batch) VALUES
('000_create_database.sql', 1),
('001_core_erp_tables.sql', 1),
('002_department_hierarchy.sql', 1),
('003_attendance_update.sql', 1),
('004_salary_structure_update.sql', 1),
('005_create_payroll_tables.sql', 1),
('006_leave_module_update.sql', 1),
('007_employee_department_links.sql', 1),
('008_hr_notifications_and_leave_audit.sql', 1),
('009_production_workflow.sql', 1),
('010_production_orders_workflow.sql', 1),
('011_production_stage_logs.sql', 1),
('012_inventory_warehouse_module.sql', 1),
('013_dispatch_logistics_module.sql', 1),
('014_production_entry_tables.sql', 1);

-- Default ERP login users (password for all: password)
USE `tyre_erp`;

INSERT INTO users(full_name, email, username, password_hash, role, status, must_change_password) VALUES
    ('Super Admin', 'superadmin@ralson.local', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'active', 0),
    ('HR Manager', 'hr@ralson.local', 'hrmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR Manager', 'active', 0),
    ('Production Manager', 'production@ralson.local', 'prodmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Production Manager', 'active', 0),
    ('Inventory Manager', 'inventory@ralson.local', 'invmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Manager', 'active', 0),
    ('Dispatch Manager', 'dispatch@ralson.local', 'dispatchmgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dispatch Manager', 'active', 0),
    ('Quality Manager', 'quality@ralson.local', 'qualitymgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quality Manager', 'active', 0),
    ('Employee User', 'employee@ralson.local', 'employeeuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', 'active', 0)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    username = VALUES(username),
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    status = 'active';

SET FOREIGN_KEY_CHECKS = 1;

