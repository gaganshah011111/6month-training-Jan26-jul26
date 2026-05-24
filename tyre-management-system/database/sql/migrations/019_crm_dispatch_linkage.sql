-- CRM ↔ Dispatch linkage: dispatch queue + sales customer on dispatch rows
USE `tyre_erp`;

CREATE TABLE IF NOT EXISTS sales_dispatch_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT NOT NULL,
    sales_order_item_id INT NOT NULL,
    so_number VARCHAR(40) NOT NULL,
    customer_id INT NOT NULL,
    company_name VARCHAR(180) NOT NULL,
    tyre_type VARCHAR(120) NOT NULL,
    ordered_qty INT NOT NULL DEFAULT 0,
    available_qty INT NOT NULL DEFAULT 0,
    pending_qty INT NOT NULL DEFAULT 0,
    dispatchable_qty INT NOT NULL DEFAULT 0,
    stock_status ENUM('READY','PARTIAL STOCK','PRODUCTION REQUIRED') NOT NULL DEFAULT 'PRODUCTION REQUIRED',
    queue_status ENUM('Waiting for Production','Ready for Dispatch','Partially Ready','Completed') NOT NULL DEFAULT 'Waiting for Production',
    dispatch_readiness VARCHAR(60) NOT NULL DEFAULT 'Waiting for Production',
    expected_dispatch_date DATE NULL,
    order_date DATE NOT NULL,
    order_priority VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sdq_item (sales_order_item_id),
    INDEX idx_sdq_order (sales_order_id),
    INDEX idx_sdq_queue_status (queue_status),
    INDEX idx_sdq_stock_status (stock_status),
    CONSTRAINT fk_sdq_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_sdq_item FOREIGN KEY (sales_order_item_id) REFERENCES sales_order_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_sdq_customer FOREIGN KEY (customer_id) REFERENCES sales_customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE dispatch ADD COLUMN IF NOT EXISTS sales_customer_id INT NULL AFTER sales_order_item_id;
ALTER TABLE dispatch ADD INDEX IF NOT EXISTS idx_dispatch_sales_customer (sales_customer_id);
