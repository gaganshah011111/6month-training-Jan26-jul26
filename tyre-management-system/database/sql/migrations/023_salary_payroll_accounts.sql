-- HR → Accounts salary payable workflow
CREATE TABLE IF NOT EXISTS accounts_salary_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month_year VARCHAR(7) NOT NULL,
    employee_count INT NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending',
    sent_by VARCHAR(120) NULL,
    sent_at DATETIME NULL,
    generated_by_hr VARCHAR(120) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_salary_batch_month (month_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS accounts_salary_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_mode VARCHAR(40) NOT NULL DEFAULT 'Bank',
    reference_no VARCHAR(80) NULL,
    remarks TEXT NULL,
    recorded_by VARCHAR(120) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_salary_pay_batch (batch_id),
    CONSTRAINT fk_salary_pay_batch FOREIGN KEY (batch_id) REFERENCES accounts_salary_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Link payroll rows to accounts batch; extend payment_status values
ALTER TABLE salaries ADD COLUMN accounts_batch_id INT NULL AFTER payment_status;
ALTER TABLE salaries ADD KEY idx_salaries_accounts_batch (accounts_batch_id);
