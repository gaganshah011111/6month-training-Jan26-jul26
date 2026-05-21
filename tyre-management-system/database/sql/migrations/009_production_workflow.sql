-- Tyre factory production workflow (machines master + production entries)
-- Applied automatically via config/db.php migrateProductionModule() on connect.

ALTER TABLE machines ADD COLUMN IF NOT EXISTS machine_type VARCHAR(80) NULL AFTER machine_name;
ALTER TABLE machines ADD COLUMN IF NOT EXISTS shift_capacity INT NOT NULL DEFAULT 0 AFTER machine_type;
ALTER TABLE machines ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER last_maintenance_date;

ALTER TABLE production ADD COLUMN IF NOT EXISTS operator_id INT NULL AFTER shift;
ALTER TABLE production ADD COLUMN IF NOT EXISTS tyre_type VARCHAR(120) NULL AFTER operator_id;
ALTER TABLE production ADD COLUMN IF NOT EXISTS planned_quantity INT NOT NULL DEFAULT 0 AFTER tyre_type;
ALTER TABLE production ADD COLUMN IF NOT EXISTS rejected_quantity INT NOT NULL DEFAULT 0 AFTER output_quantity;
ALTER TABLE production ADD COLUMN IF NOT EXISTS downtime_minutes INT NOT NULL DEFAULT 0 AFTER rejected_quantity;
ALTER TABLE production ADD COLUMN IF NOT EXISTS remarks VARCHAR(500) NULL AFTER downtime_minutes;
ALTER TABLE production ADD COLUMN IF NOT EXISTS efficiency_pct DECIMAL(6,2) NULL AFTER remarks;
ALTER TABLE production ADD COLUMN IF NOT EXISTS entry_status VARCHAR(30) NOT NULL DEFAULT 'Submitted' AFTER efficiency_pct;
ALTER TABLE production ADD COLUMN IF NOT EXISTS inventory_deducted TINYINT(1) NOT NULL DEFAULT 0 AFTER entry_status;
ALTER TABLE production ADD COLUMN IF NOT EXISTS payroll_ot_flag TINYINT(1) NOT NULL DEFAULT 0 AFTER inventory_deducted;
