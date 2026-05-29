-- Per-employee salary payments + HR verify workflow
ALTER TABLE salaries
    ADD COLUMN IF NOT EXISTS amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER net_salary,
    ADD COLUMN IF NOT EXISTS hr_payroll_status VARCHAR(24) NOT NULL DEFAULT 'generated' AFTER payment_status,
    ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL AFTER hr_payroll_status,
    ADD COLUMN IF NOT EXISTS verified_by VARCHAR(120) NULL AFTER verified_at;

ALTER TABLE accounts_salary_payments
    ADD COLUMN IF NOT EXISTS salary_id INT NULL AFTER batch_id,
    ADD COLUMN IF NOT EXISTS employee_id INT NULL AFTER salary_id,
    ADD COLUMN IF NOT EXISTS expense_id INT NULL AFTER recorded_by,
    ADD KEY idx_salary_pay_salary (salary_id),
    ADD KEY idx_salary_pay_employee (employee_id);

ALTER TABLE accounts_salary_batches
    ADD COLUMN IF NOT EXISTS payroll_closed_at DATETIME NULL AFTER generated_by_hr;
