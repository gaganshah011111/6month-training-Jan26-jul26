-- =============================================================================
-- Tyre ERP â€” Fresh database restore (schema + default logins, no test data)
-- Generated: 2026-05-20
-- Database: tyre_erp
--
-- Use when XAMPP MySQL is empty or corrupted and you want a clean ERP database.
-- For your full data (employees, attendance, payroll runs), use:
--   database/sql/full_latest_backup.sql
--
-- Import (command line):
--   c:\xampp\mysql\bin\mysql.exe -u root < database\sql\restore_fresh.sql
--
-- phpMyAdmin: Import this file, then open the app once (seeds departments).
-- Default password for all demo users: password
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
    inventory_id INT NOT NULL,
    order_no VARCHAR(60) NOT NULL UNIQUE,
    customer_name VARCHAR(150) NOT NULL,
    invoice_no VARCHAR(80) NOT NULL UNIQUE,
    dispatch_date DATE NOT NULL,
    qty INT NOT NULL,
    dispatch_status ENUM('Created','In Transit','Delivered','Cancelled') NOT NULL DEFAULT 'Created',
    tracking_no VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dispatch_date (dispatch_date),
    CONSTRAINT fk_dispatch_inventory FOREIGN KEY (inventory_id) REFERENCES inventory(id)
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

INSERT IGNORE INTO schema_migrations (migration, batch) VALUES
('000_create_database.sql', 1),
('001_core_erp_tables.sql', 1),
('002_department_hierarchy.sql', 1),
('003_attendance_update.sql', 1),
('004_salary_structure_update.sql', 1),
('005_create_payroll_tables.sql', 1),
('006_leave_module_update.sql', 1),
('007_employee_department_links.sql', 1),
('008_hr_notifications_and_leave_audit.sql', 1);

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

