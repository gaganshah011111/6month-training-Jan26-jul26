-- 002: Department hierarchy (categories, departments, designations)
USE `tyre_erp`;

CREATE TABLE IF NOT EXISTS department_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(120) NOT NULL,
    category_code VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dept_cat_code (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    department_name VARCHAR(160) NOT NULL,
    department_short_name VARCHAR(80) NULL,
    department_code VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    min_staff_required INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dept_code (department_code),
    UNIQUE KEY uk_dept_cat_name (category_id, department_name),
    INDEX idx_dept_category (category_id),
    CONSTRAINT fk_dept_category FOREIGN KEY (category_id) REFERENCES department_categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS designations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    designation_name VARCHAR(120) NOT NULL,
    designation_code VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_desig_dept_code (department_id, designation_code),
    INDEX idx_desig_department (department_id),
    CONSTRAINT fk_desig_department FOREIGN KEY (department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
