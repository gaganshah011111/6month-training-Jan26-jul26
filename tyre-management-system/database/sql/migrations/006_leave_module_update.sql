-- 006: Smart leave module (allocation, staffing, notifications)
USE `tyre_erp`;

ALTER TABLE leaves MODIFY status ENUM('Applied','Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending';
UPDATE leaves SET status = 'Pending' WHERE status = 'Applied';

ALTER TABLE leaves ADD COLUMN paid_days DECIMAL(6,2) NOT NULL DEFAULT 0;
ALTER TABLE leaves ADD COLUMN half_paid_days DECIMAL(6,2) NOT NULL DEFAULT 0;
ALTER TABLE leaves ADD COLUMN unpaid_days DECIMAL(6,2) NOT NULL DEFAULT 0;
ALTER TABLE leaves ADD COLUMN total_days DECIMAL(6,2) NOT NULL DEFAULT 0;
ALTER TABLE leaves ADD COLUMN is_emergency TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE leaves ADD COLUMN auto_approved TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE leaves ADD COLUMN staffing_risk VARCHAR(20) NOT NULL DEFAULT 'Safe';
ALTER TABLE leaves ADD COLUMN system_note VARCHAR(255) NULL;
ALTER TABLE leaves ADD COLUMN day_allocation_json TEXT NULL;
ALTER TABLE leaves ADD COLUMN approved_by INT NULL;
ALTER TABLE leaves ADD COLUMN rejection_reason VARCHAR(255) NULL;
ALTER TABLE leaves ADD COLUMN approved_at DATETIME NULL;

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

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('leave_auto_approve_enabled', '0');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('leave_min_present_pct', '50');
