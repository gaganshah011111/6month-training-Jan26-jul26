CREATE DATABASE IF NOT EXISTS tyre_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tyre_erp;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Super Admin','HR Manager','Production Manager','Inventory Manager','Dispatch Manager','Quality Manager','Employee') NOT NULL DEFAULT 'Employee',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role_status(role, status)
);

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    department VARCHAR(80) NOT NULL,
    contact_no VARCHAR(20),
    joining_date DATE NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    shift ENUM('Morning','Evening','Night') NOT NULL,
    status ENUM('Present','Absent','Leave') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_attendance (employee_id, attendance_date),
    INDEX idx_att_date(attendance_date),
    CONSTRAINT fk_att_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month_year VARCHAR(7) NOT NULL,
    basic DECIMAL(12,2) NOT NULL,
    overtime DECIMAL(12,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_salary_month(month_year),
    CONSTRAINT fk_salary_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('Applied','Approved','Rejected') DEFAULT 'Applied',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(120),
    phone VARCHAR(20),
    email VARCHAR(120),
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE raw_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_name VARCHAR(120) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
    supplier_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_material_stock(stock_qty),
    CONSTRAINT fk_material_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

CREATE TABLE machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_code VARCHAR(30) NOT NULL UNIQUE,
    machine_name VARCHAR(120) NOT NULL,
    status ENUM('Active','Under Maintenance','Inactive') NOT NULL DEFAULT 'Active',
    last_maintenance_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE production (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    machine_id INT NOT NULL,
    shift ENUM('Morning','Evening','Night') NOT NULL,
    raw_material_id INT NOT NULL,
    material_used_qty DECIMAL(12,2) NOT NULL,
    output_quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prod_date(production_date),
    CONSTRAINT fk_prod_machine FOREIGN KEY (machine_id) REFERENCES machines(id),
    CONSTRAINT fk_prod_material FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
);

CREATE TABLE quality_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    inspector_name VARCHAR(120) NOT NULL,
    passed_qty INT NOT NULL,
    failed_qty INT NOT NULL,
    defects VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_qc_production FOREIGN KEY (production_id) REFERENCES production(id)
);

CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(120) NOT NULL,
    batch_ref VARCHAR(40) NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    warehouse_location VARCHAR(120) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inventory_qty(qty)
);

CREATE TABLE dispatch (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(40) NOT NULL UNIQUE,
    customer_name VARCHAR(120) NOT NULL,
    invoice_no VARCHAR(40) NOT NULL,
    dispatch_date DATE NOT NULL,
    qty INT NOT NULL,
    dispatch_status ENUM('Created','In Transit','Delivered') DEFAULT 'Created',
    tracking_no VARCHAR(60),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dispatch_status(dispatch_status),
    INDEX idx_dispatch_date(dispatch_date)
);

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(60) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL
);

INSERT INTO users (full_name, email, password_hash, role, status) VALUES
('Super Admin', 'superadmin@ralson.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'active'),
('HR Manager', 'hr@ralson.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR Manager', 'active'),
('Production Manager', 'production@ralson.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Production Manager', 'active'),
('Inventory Manager', 'inventory@ralson.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Manager', 'active'),
('Dispatch Manager', 'dispatch@ralson.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dispatch Manager', 'active'),
('Quality Manager', 'quality@ralson.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quality Manager', 'active'),
('Employee User', 'employee@ralson.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', 'active');

INSERT INTO employees (employee_code, full_name, department, contact_no, joining_date, basic_salary) VALUES
('EMP001', 'Ravi Kumar', 'Production', '9876500001', '2024-01-15', 28000),
('EMP002', 'Neha Sharma', 'Quality', '9876500002', '2024-02-12', 30000),
('EMP003', 'Aman Verma', 'Dispatch', '9876500003', '2024-03-10', 26000);

INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
('Punjab Rubber Corp', 'Harjit Singh', '9811111111', 'harjit@rubber.local', 'Ludhiana'),
('ChemTech Industries', 'K. Mehta', '9822222222', 'mehta@chem.local', 'Delhi NCR');

INSERT INTO raw_materials (material_name, unit, stock_qty, reorder_level, supplier_id) VALUES
('Natural Rubber', 'kg', 5000, 1200, 1),
('Carbon Black', 'kg', 2200, 800, 2);

INSERT INTO machines (machine_code, machine_name, status, last_maintenance_date) VALUES
('MC-101', 'Tyre Curing Press 1', 'Active', '2026-04-10'),
('MC-102', 'Tyre Building Machine 1', 'Active', '2026-04-22');

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Ralson India Private Limited');

