-- Dispatch & logistics module: customers, drivers, vehicles, transport, extended dispatch
USE `tyre_erp`;

CREATE TABLE IF NOT EXISTS dispatch_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    company VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    gst_number VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(80) NULL,
    state VARCHAR(80) NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dcust_name (customer_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS dispatch_code VARCHAR(40) NULL AFTER id;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS customer_id INT NULL AFTER customer_name;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS tyre_type VARCHAR(120) NULL AFTER customer_id;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS vehicle_no VARCHAR(40) NULL AFTER invoice_no;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS driver_name VARCHAR(150) NULL AFTER vehicle_no;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS transport_company VARCHAR(150) NULL AFTER driver_name;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS gross_weight_kg DECIMAL(12,2) NULL AFTER qty;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS tare_weight_kg DECIMAL(12,2) NULL AFTER gross_weight_kg;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS net_weight_kg DECIMAL(12,2) NULL AFTER tare_weight_kg;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS remarks VARCHAR(500) NULL AFTER net_weight_kg;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS status ENUM('Pending','Dispatched','Delivered') NOT NULL DEFAULT 'Delivered' AFTER remarks;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS stock_deducted TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS driver_id INT NULL AFTER driver_name;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS transport_company_id INT NULL AFTER transport_company;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS vehicle_id INT NULL AFTER vehicle_no;

ALTER TABLE dispatch MODIFY inventory_id INT NULL;

UPDATE dispatch SET status = CASE
    WHEN dispatch_status IN ('Delivered') THEN 'Delivered'
    WHEN dispatch_status IN ('In Transit','Created') THEN 'Dispatched'
    ELSE 'Pending' END
WHERE status IS NULL OR status = '';

UPDATE dispatch SET dispatch_code = CONCAT('DSP-', LPAD(id, 5, '0'))
WHERE dispatch_code IS NULL OR dispatch_code = '';

CREATE TABLE IF NOT EXISTS dispatch_transport_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(120) NULL,
    phone VARCHAR(30) NULL,
    gst_number VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_transport_name (company_name),
    INDEX idx_transport_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS dispatch_drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    license_number VARCHAR(60) NULL,
    vehicle_id INT NULL,
    vehicle_no VARCHAR(40) NULL,
    transport_company_id INT NULL,
    transport_company VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ddriver_name (driver_name),
    INDEX idx_ddriver_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS dispatch_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_number VARCHAR(40) NOT NULL,
    vehicle_type VARCHAR(60) NULL,
    capacity VARCHAR(40) NULL,
    driver_id INT NULL,
    transport_company_id INT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vehicle_number (vehicle_number),
    INDEX idx_vehicle_status (status),
    INDEX idx_vehicle_driver (driver_id),
    INDEX idx_vehicle_transport (transport_company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO dispatch_customers (customer_name, company, phone, city, state)
SELECT 'Metro Tyre Traders', 'Metro Tyre Traders Pvt Ltd', '9876500001', 'Mumbai', 'Maharashtra'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM dispatch_customers LIMIT 1);

INSERT INTO dispatch_customers (customer_name, company, phone, city, state)
SELECT 'North Highway Fleet', 'North Highway Fleet Services', '9876500002', 'Delhi', 'Delhi'
FROM DUAL WHERE (SELECT COUNT(*) FROM dispatch_customers) < 2;

INSERT INTO dispatch_customers (customer_name, company, phone, city, state)
SELECT 'Southern Auto Mart', 'Southern Auto Mart', '9876500003', 'Chennai', 'Tamil Nadu'
FROM DUAL WHERE (SELECT COUNT(*) FROM dispatch_customers) < 3;

INSERT INTO dispatch_transport_companies (company_name, contact_person, phone, gst_number, address, status)
SELECT 'Punjab Logistics', 'Harpreet Singh', '9876520001', '03AABCP1234F1Z5', 'Ludhiana, Punjab', 'Active'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM dispatch_transport_companies LIMIT 1);

INSERT INTO dispatch_transport_companies (company_name, contact_person, phone, gst_number, address, status)
SELECT 'Western Freight Lines', 'Amit Shah', '9876520002', '24AABCW5678G1Z9', 'Ahmedabad, Gujarat', 'Active'
FROM DUAL WHERE (SELECT COUNT(*) FROM dispatch_transport_companies) < 2;

INSERT INTO dispatch_transport_companies (company_name, contact_person, phone, gst_number, address, status)
SELECT 'North Star Transport', 'Vikram Mehta', '9876520003', '06AABCN9012H1Z3', 'Gurgaon, Haryana', 'Active'
FROM DUAL WHERE (SELECT COUNT(*) FROM dispatch_transport_companies) < 3;

INSERT INTO dispatch_drivers (driver_name, phone, license_number, vehicle_no, transport_company_id, transport_company, address, status)
SELECT 'Ravi Kumar', '9876510001', 'DL-09-2018-0012345', 'PB10AX1234', t.id, 'Punjab Logistics', 'Ludhiana, Punjab', 'Active'
FROM dispatch_transport_companies t
WHERE t.company_name = 'Punjab Logistics'
  AND NOT EXISTS (SELECT 1 FROM dispatch_drivers LIMIT 1)
LIMIT 1;

INSERT INTO dispatch_drivers (driver_name, phone, license_number, vehicle_no, transport_company_id, transport_company, address, status)
SELECT 'Suresh Patel', '9876510002', 'GJ-12-2019-0098765', 'GJ01BT5678', t.id, 'Western Freight Lines', 'Ahmedabad, Gujarat', 'Active'
FROM dispatch_transport_companies t
WHERE t.company_name = 'Western Freight Lines'
  AND (SELECT COUNT(*) FROM dispatch_drivers) < 2
LIMIT 1;

INSERT INTO dispatch_drivers (driver_name, phone, license_number, vehicle_no, transport_company_id, transport_company, address, status)
SELECT 'Mohit Singh', '9876510003', 'HR-05-2020-0045678', 'HR26CD9012', t.id, 'North Star Transport', 'Gurgaon, Haryana', 'Active'
FROM dispatch_transport_companies t
WHERE t.company_name = 'North Star Transport'
  AND (SELECT COUNT(*) FROM dispatch_drivers) < 3
LIMIT 1;

UPDATE dispatch_drivers d
INNER JOIN dispatch_transport_companies t ON t.company_name = d.transport_company
SET d.transport_company_id = t.id
WHERE d.transport_company_id IS NULL AND d.transport_company IS NOT NULL AND d.transport_company != '';

INSERT IGNORE INTO dispatch_vehicles (vehicle_number, vehicle_type, capacity, driver_id, transport_company_id, status)
SELECT UPPER(TRIM(d.vehicle_no)), 'Truck', '40 tyres', d.id, d.transport_company_id, 'Active'
FROM dispatch_drivers d
WHERE d.vehicle_no IS NOT NULL AND d.vehicle_no != '';

UPDATE dispatch_drivers d
INNER JOIN dispatch_vehicles v ON v.vehicle_number = UPPER(TRIM(d.vehicle_no))
SET d.vehicle_id = v.id
WHERE d.vehicle_id IS NULL;
