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
