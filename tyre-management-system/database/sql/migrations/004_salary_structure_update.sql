-- 004: Employee & salaries payroll structure columns
USE `tyre_erp`;

-- Employee payroll fields (safe if already in 001)
ALTER TABLE employees MODIFY gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE employees MODIFY payroll_auto_indian TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE salaries ADD COLUMN dearness_allowance DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE salaries ADD COLUMN travel_allowance DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE salaries ADD COLUMN pf_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE salaries ADD COLUMN tax_deduction DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE salaries ADD COLUMN gratuity_accrual DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE salaries ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid';
ALTER TABLE salaries ADD COLUMN is_draft TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE salaries ADD COLUMN paid_at DATETIME NULL;
ALTER TABLE salaries ADD COLUMN generated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;
