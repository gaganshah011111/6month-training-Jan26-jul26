-- Sales & CRM module
USE `tyre_erp`;

CREATE TABLE IF NOT EXISTS sales_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(30) NOT NULL,
    company_name VARCHAR(180) NOT NULL,
    customer_type ENUM('Dealer','Distributor','Retailer','Industrial Buyer') NOT NULL DEFAULT 'Dealer',
    contact_person VARCHAR(120) NULL,
    gst_number VARCHAR(40) NULL,
    pan_number VARCHAR(20) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(120) NULL,
    billing_address VARCHAR(255) NULL,
    shipping_address VARCHAR(255) NULL,
    city VARCHAR(80) NULL,
    state VARCHAR(80) NULL,
    pincode VARCHAR(12) NULL,
    credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_terms VARCHAR(80) NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    remarks VARCHAR(500) NULL,
    dispatch_customer_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_cust_code (customer_code),
    INDEX idx_sales_cust_status (status),
    INDEX idx_sales_cust_company (company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sales_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    so_number VARCHAR(40) NOT NULL,
    customer_id INT NOT NULL,
    order_date DATE NOT NULL,
    delivery_date DATE NULL,
    priority ENUM('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
    status ENUM('Pending','In Production','Ready','Partially Dispatched','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    payment_terms VARCHAR(80) NULL,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    gst_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_so (so_number),
    INDEX idx_sales_ord_customer (customer_id),
    INDEX idx_sales_ord_status (status),
    INDEX idx_sales_ord_date (order_date),
    CONSTRAINT fk_sales_ord_customer FOREIGN KEY (customer_id) REFERENCES sales_customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sales_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    line_no INT NOT NULL DEFAULT 1,
    tyre_type VARCHAR(120) NOT NULL,
    qty_ordered INT NOT NULL DEFAULT 0,
    qty_dispatched INT NOT NULL DEFAULT 0,
    rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    gst_percent DECIMAL(5,2) NOT NULL DEFAULT 18,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_gst DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    stock_available INT NOT NULL DEFAULT 0,
    fulfillment_status ENUM('Ready for Dispatch','Production Required') NOT NULL DEFAULT 'Production Required',
    INDEX idx_sales_oi_order (order_id),
    CONSTRAINT fk_sales_oi_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sales_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(40) NOT NULL,
    customer_id INT NOT NULL,
    order_id INT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    gst_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_status ENUM('Paid','Partial','Pending','Overdue') NOT NULL DEFAULT 'Pending',
    remarks VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_inv (invoice_no),
    INDEX idx_sales_inv_customer (customer_id),
    INDEX idx_sales_inv_order (order_id),
    INDEX idx_sales_inv_status (payment_status),
    CONSTRAINT fk_sales_inv_customer FOREIGN KEY (customer_id) REFERENCES sales_customers(id),
    CONSTRAINT fk_sales_inv_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sales_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    order_item_id INT NULL,
    tyre_type VARCHAR(120) NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    gst_percent DECIMAL(5,2) NOT NULL DEFAULT 18,
    line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_gst DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    INDEX idx_sales_ii_inv (invoice_id),
    CONSTRAINT fk_sales_ii_inv FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sales_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_mode ENUM('Cash','Bank Transfer','UPI','Cheque') NOT NULL DEFAULT 'Bank Transfer',
    reference_no VARCHAR(80) NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sales_pay_customer (customer_id),
    INDEX idx_sales_pay_invoice (invoice_id),
    CONSTRAINT fk_sales_pay_customer FOREIGN KEY (customer_id) REFERENCES sales_customers(id),
    CONSTRAINT fk_sales_pay_invoice FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sales_customer_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    note_text TEXT NOT NULL,
    created_by VARCHAR(120) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sales_note_customer (customer_id),
    CONSTRAINT fk_sales_note_customer FOREIGN KEY (customer_id) REFERENCES sales_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sales_dispatch_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT NOT NULL,
    sales_order_item_id INT NOT NULL,
    dispatch_id INT NOT NULL,
    qty INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sda_order (sales_order_id),
    INDEX idx_sda_dispatch (dispatch_id),
    CONSTRAINT fk_sda_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id),
    CONSTRAINT fk_sda_item FOREIGN KEY (sales_order_item_id) REFERENCES sales_order_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS sales_order_id INT NULL AFTER order_no;
ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS sales_order_item_id INT NULL AFTER sales_order_id;
ALTER TABLE dispatch ADD INDEX IF NOT EXISTS idx_dispatch_sales_order (sales_order_id);
