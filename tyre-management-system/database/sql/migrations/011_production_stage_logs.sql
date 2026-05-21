-- Stage execution audit trail (applied via config/db.php migrateProductionOrdersWorkflow)
CREATE TABLE IF NOT EXISTS production_stage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    stage_id INT NULL,
    stage_name VARCHAR(40) NOT NULL,
    machine_id INT NULL,
    operator_id INT NULL,
    shift VARCHAR(20) NULL,
    status VARCHAR(30) NOT NULL,
    produced_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    downtime_minutes INT NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    remarks VARCHAR(500) NULL,
    action_type VARCHAR(40) NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
