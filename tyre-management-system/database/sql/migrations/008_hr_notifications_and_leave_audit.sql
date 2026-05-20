-- 008: HR navbar notifications + leave audit fields
USE `tyre_erp`;

CREATE TABLE IF NOT EXISTS hr_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_key VARCHAR(120) NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dismissed_at TIMESTAMP NULL,
    UNIQUE KEY uq_hr_notif_user_key (user_id, notification_key),
    INDEX idx_hr_notif_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE leaves ADD COLUMN entry_source VARCHAR(20) NOT NULL DEFAULT 'employee' AFTER auto_approved;
ALTER TABLE leaves ADD COLUMN recorded_by INT NULL AFTER entry_source;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('leave_auto_approve_enabled', '0');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('leave_min_present_pct', '50');
