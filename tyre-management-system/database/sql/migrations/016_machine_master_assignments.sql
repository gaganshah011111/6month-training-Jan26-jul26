-- Machine master extensions, operator assignments, audit history
USE `tyre_erp`;

ALTER TABLE machines ADD COLUMN section VARCHAR(80) NULL AFTER department;
ALTER TABLE machines ADD COLUMN installation_date DATE NULL AFTER section;
ALTER TABLE machines ADD COLUMN remarks TEXT NULL AFTER notes;
ALTER TABLE machines ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE machines ADD COLUMN deactivated_at DATETIME NULL AFTER is_active;

-- Normalize legacy statuses to industrial master values
UPDATE machines SET status = 'Active' WHERE status IN ('Running', 'Active', 'active');
UPDATE machines SET status = 'Idle' WHERE status IN ('Inactive', 'inactive') AND is_active = 1;
UPDATE machines SET status = 'Under Repair' WHERE status IN ('Maintenance', 'Breakdown', 'Under Maintenance', 'under maintenance');
UPDATE machines SET status = 'Scrap / Deactivated', is_active = 0 WHERE status IN ('Scrap', 'Deactivated');

CREATE TABLE IF NOT EXISTS machine_operator_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    employee_id INT NOT NULL,
    department VARCHAR(40) NOT NULL,
    shift VARCHAR(20) NULL,
    assigned_from DATE NOT NULL,
    assigned_till DATE NULL,
    remarks TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    closed_at DATETIME NULL,
    closed_reason VARCHAR(120) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_moa_machine (machine_id),
    INDEX idx_moa_employee (employee_id),
    INDEX idx_moa_active (machine_id, is_active),
    CONSTRAINT fk_moa_machine FOREIGN KEY (machine_id) REFERENCES machines(id),
    CONSTRAINT fk_moa_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS machine_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    old_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by INT NULL,
    remarks TEXT NULL,
    INDEX idx_msh_machine (machine_id),
    CONSTRAINT fk_msh_machine FOREIGN KEY (machine_id) REFERENCES machines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS machine_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    field_name VARCHAR(40) NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by INT NULL,
    remarks TEXT NULL,
    INDEX idx_mal_machine (machine_id),
    CONSTRAINT fk_mal_machine FOREIGN KEY (machine_id) REFERENCES machines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
