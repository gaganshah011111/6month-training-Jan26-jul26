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
