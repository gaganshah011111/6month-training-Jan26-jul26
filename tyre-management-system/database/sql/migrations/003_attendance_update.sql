-- 003: Attendance workflow columns and status normalization
USE `tyre_erp`;

ALTER TABLE attendance MODIFY status VARCHAR(40) NOT NULL DEFAULT 'Present';
ALTER TABLE attendance MODIFY total_hours DECIMAL(8,2) NULL DEFAULT NULL;

UPDATE attendance SET status = 'Paid Leave' WHERE status = 'Leave';
UPDATE attendance SET is_emergency_duty = 1 WHERE status = 'Emergency Duty';
