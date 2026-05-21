-- Production orders workflow (reference migration; applied via config/db.php migrateProductionOrdersWorkflow)
CREATE TABLE IF NOT EXISTS production_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(40) NOT NULL UNIQUE,
    tyre_type VARCHAR(120) NOT NULL,
    target_qty INT NOT NULL DEFAULT 0,
    deadline DATE NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'Normal',
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    current_stage VARCHAR(40) NOT NULL DEFAULT 'Mixing',
    total_produced INT NOT NULL DEFAULT 0,
    total_rejected INT NOT NULL DEFAULT 0,
    total_downtime INT NOT NULL DEFAULT 0,
    qc_passed_qty INT NOT NULL DEFAULT 0,
    qc_failed_qty INT NOT NULL DEFAULT 0,
    inventory_deducted TINYINT(1) NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS production_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_name VARCHAR(40) NOT NULL,
    stage_order TINYINT NOT NULL DEFAULT 1,
    machine_id INT NULL,
    operator_id INT NULL,
    shift VARCHAR(20) NULL DEFAULT 'Morning',
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    produced_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    downtime_minutes INT NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    remarks VARCHAR(500) NULL,
    UNIQUE KEY uk_order_stage (order_id, stage_name)
);

CREATE TABLE IF NOT EXISTS production_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_id INT NULL,
    action VARCHAR(60) NOT NULL,
    message VARCHAR(500) NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS machine_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_id INT NOT NULL,
    machine_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS production_downtime (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_id INT NULL,
    minutes INT NOT NULL DEFAULT 0,
    reason VARCHAR(255) NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS production_bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tyre_type VARCHAR(120) NOT NULL,
    raw_material_id INT NOT NULL,
    qty_per_unit DECIMAL(12,4) NOT NULL DEFAULT 0,
    UNIQUE KEY uk_bom_tyre_material (tyre_type, raw_material_id)
);

-- ALTER TABLE quality_checks ADD COLUMN production_order_id INT NULL AFTER production_id;
