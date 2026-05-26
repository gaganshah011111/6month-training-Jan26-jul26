-- Purchase inward fields on stock_inward + supplier GST + material avg cost
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS gst_number VARCHAR(40) NULL AFTER address;

ALTER TABLE raw_materials ADD COLUMN IF NOT EXISTS avg_purchase_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER max_stock_level;

ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS pinv_no VARCHAR(40) NULL AFTER id;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS challan_no VARCHAR(80) NULL AFTER invoice_no;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS gst_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER rate;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS transport_charges DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gst_percent;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS loading_charges DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER transport_charges;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS other_charges DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loading_charges;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER other_charges;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS subtotal DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER discount_amount;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS gst_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER subtotal;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS total_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER gst_amount;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_amount;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS payment_status ENUM('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Unpaid' AFTER paid_amount;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(40) NULL AFTER payment_status;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS due_date DATE NULL AFTER payment_mode;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS payment_ref VARCHAR(80) NULL AFTER due_date;
ALTER TABLE stock_inward ADD COLUMN IF NOT EXISTS warehouse_location VARCHAR(120) NULL AFTER payment_ref;

CREATE INDEX IF NOT EXISTS idx_inward_pinv ON stock_inward (pinv_no);
CREATE INDEX IF NOT EXISTS idx_inward_payment_status ON stock_inward (payment_status);
