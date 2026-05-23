-- Quality Control module: curing batch linkage, inspections, defects, inventory categories
USE `tyre_erp`;

ALTER TABLE curing_entries ADD COLUMN IF NOT EXISTS batch_code VARCHAR(40) NULL AFTER id;
ALTER TABLE curing_entries ADD COLUMN IF NOT EXISTS qc_status VARCHAR(30) NOT NULL DEFAULT 'Pending' AFTER remarks;
ALTER TABLE curing_entries ADD COLUMN IF NOT EXISTS qc_inspection_id INT NULL AFTER qc_status;

ALTER TABLE inventory ADD COLUMN IF NOT EXISTS stock_category VARCHAR(30) NULL DEFAULT NULL AFTER warehouse_location;

CREATE TABLE IF NOT EXISTS qc_inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curing_entry_id INT NOT NULL,
    batch_code VARCHAR(40) NOT NULL,
    tyre_type VARCHAR(120) NOT NULL,
    machine_id INT NULL,
    production_date DATE NOT NULL,
    production_shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    produced_qty INT NOT NULL DEFAULT 0,
    inspected_qty INT NOT NULL DEFAULT 0,
    passed_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    rework_qty INT NOT NULL DEFAULT 0,
    inspector_name VARCHAR(150) NOT NULL,
    inspection_date DATE NOT NULL,
    inspection_shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    qc_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_qc_curing (curing_entry_id),
    INDEX idx_qc_insp_date (inspection_date),
    CONSTRAINT fk_qc_curing FOREIGN KEY (curing_entry_id) REFERENCES curing_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS qc_defect_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    defect_type VARCHAR(40) NOT NULL,
    defect_label VARCHAR(120) NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_qc_def_insp (inspection_id),
    CONSTRAINT fk_qc_def_insp FOREIGN KEY (inspection_id) REFERENCES qc_inspections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE curing_entries SET batch_code = CONCAT('CUR-', DATE_FORMAT(production_date,'%Y%m%d'), '-', LPAD(id, 4, '0'))
WHERE batch_code IS NULL OR batch_code = '';
