-- Inventory warehouse module: suppliers/raw materials extensions, stock inward/usage/adjustments
USE `tyre_erp`;

ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER address;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS materials_supplied VARCHAR(500) NULL AFTER status;

ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS material_code VARCHAR(40) NULL AFTER id;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS category VARCHAR(80) NOT NULL DEFAULT 'General' AFTER material_name;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS storage_location VARCHAR(120) NOT NULL DEFAULT 'Main Store' AFTER reorder_level;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS remarks VARCHAR(500) NULL AFTER storage_location;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER remarks;
ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS max_stock_level DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER reorder_level;

CREATE TABLE IF NOT EXISTS stock_inward (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inward_date DATE NOT NULL,
    supplier_id INT NULL,
    invoice_no VARCHAR(80) NULL,
    material_id INT NOT NULL,
    batch_no VARCHAR(80) NULL,
    expiry_date DATE NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    received_by VARCHAR(150) NULL,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inward_date (inward_date),
    INDEX idx_inward_material (material_id),
    INDEX idx_inward_supplier (supplier_id),
    CONSTRAINT fk_inward_material FOREIGN KEY (material_id) REFERENCES raw_materials(id),
    CONSTRAINT fk_inward_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usage_date DATE NOT NULL,
    material_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    usage_type ENUM('production','manual') NOT NULL DEFAULT 'manual',
    department VARCHAR(40) NULL,
    usage_reason VARCHAR(80) NULL,
    reference_id INT NULL,
    remarks VARCHAR(500) NULL,
    created_by VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usage_date (usage_date),
    INDEX idx_usage_material (material_id),
    INDEX idx_usage_type (usage_type),
    CONSTRAINT fk_usage_material FOREIGN KEY (material_id) REFERENCES raw_materials(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inventory_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activity_type VARCHAR(40) NOT NULL,
    material_id INT NULL,
    qty_change DECIMAL(12,2) NOT NULL DEFAULT 0,
    message VARCHAR(500) NOT NULL,
    INDEX idx_inv_activity_at (activity_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adjust_date DATE NOT NULL,
    material_id INT NOT NULL,
    previous_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    actual_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    difference_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    reason VARCHAR(80) NOT NULL,
    operator_name VARCHAR(150) NULL,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_adj_date (adjust_date),
    INDEX idx_adj_material (material_id),
    CONSTRAINT fk_adj_material FOREIGN KEY (material_id) REFERENCES raw_materials(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO raw_materials (material_code, material_name, category, unit, stock_qty, reorder_level, storage_location, status)
SELECT 'RM-RUBBER', 'Natural Rubber', 'Rubber', 'kg', 5000, 800, 'Store-A1', 'Active'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM raw_materials LIMIT 1);

INSERT INTO raw_materials (material_code, material_name, category, unit, stock_qty, reorder_level, storage_location, status)
SELECT 'RM-CARBON', 'Carbon Black', 'Fillers', 'kg', 3000, 500, 'Store-A2', 'Active'
FROM DUAL WHERE (SELECT COUNT(*) FROM raw_materials) < 2;

INSERT INTO raw_materials (material_code, material_name, category, unit, stock_qty, reorder_level, storage_location, status)
SELECT 'RM-STEEL', 'Steel Wire', 'Reinforcement', 'kg', 2000, 300, 'Store-B1', 'Active'
FROM DUAL WHERE (SELECT COUNT(*) FROM raw_materials) < 3;

INSERT INTO raw_materials (material_code, material_name, category, unit, stock_qty, reorder_level, storage_location, status)
SELECT 'RM-FABRIC', 'Fabric', 'Reinforcement', 'piece', 1500, 200, 'Store-B2', 'Active'
FROM DUAL WHERE (SELECT COUNT(*) FROM raw_materials) < 4;

INSERT INTO raw_materials (material_code, material_name, category, unit, stock_qty, reorder_level, storage_location, status)
SELECT 'RM-CHEM', 'Chemicals', 'Chemicals', 'kg', 800, 100, 'Store-C1', 'Active'
FROM DUAL WHERE (SELECT COUNT(*) FROM raw_materials) < 5;

INSERT INTO raw_materials (material_code, material_name, category, unit, stock_qty, reorder_level, storage_location, status)
SELECT 'RM-PACK', 'Packaging', 'Packaging', 'piece', 5000, 500, 'Store-D1', 'Active'
FROM DUAL WHERE (SELECT COUNT(*) FROM raw_materials) < 6;

UPDATE raw_materials SET material_code = CONCAT('RM-', id) WHERE material_code IS NULL OR material_code = '';
