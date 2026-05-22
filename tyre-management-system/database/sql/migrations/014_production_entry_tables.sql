-- Simple production daily entry tables (mixing, building, curing, QC)
USE `tyre_erp`;

CREATE TABLE IF NOT EXISTS mixing_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    machine_id INT NULL,
    operator_id INT NULL,
    tyre_type VARCHAR(120) NOT NULL,
    produced_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    rejected_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mix_entry_date (production_date),
    INDEX idx_mix_entry_shift (shift)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS building_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    machine_id INT NULL,
    operator_id INT NULL,
    tyre_type VARCHAR(120) NOT NULL,
    produced_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bld_entry_date (production_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS curing_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    machine_id INT NULL,
    operator_id INT NULL,
    tyre_type VARCHAR(120) NOT NULL DEFAULT 'Tyre',
    produced_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    downtime_minutes INT NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cur_entry_date (production_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS qc_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
    inspector_name VARCHAR(150) NOT NULL,
    tyre_type VARCHAR(120) NOT NULL,
    checked_qty INT NOT NULL DEFAULT 0,
    passed_qty INT NOT NULL DEFAULT 0,
    failed_qty INT NOT NULL DEFAULT 0,
    defect_type VARCHAR(120) NULL,
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_qc_entry_date (entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
