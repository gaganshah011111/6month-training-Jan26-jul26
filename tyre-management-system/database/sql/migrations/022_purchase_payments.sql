-- Purchase payment history (append-only partial payments)
CREATE TABLE IF NOT EXISTS purchase_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inward_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_mode VARCHAR(40) NULL,
    payment_ref VARCHAR(80) NULL,
    notes VARCHAR(500) NULL,
    recorded_by VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pp_inward (inward_id),
    INDEX idx_pp_date (payment_date),
    CONSTRAINT fk_pp_inward FOREIGN KEY (inward_id) REFERENCES stock_inward(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
