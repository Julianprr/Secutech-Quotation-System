-- ============================================================
-- Secutech Quotation System - Invoices feature
-- Run this once in phpMyAdmin (or your MySQL client) against
-- your existing secutech_quotes database.
-- ============================================================

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_id INT NULL,
    customer_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_date DATE NOT NULL,
    valid_until DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Unpaid',
    sales_person VARCHAR(255) NULL,
    payment_terms VARCHAR(255) NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quotation_id (quotation_id),
    INDEX idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT NULL,
    section_id INT NULL,
    item_code VARCHAR(100) NULL,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(5,2) NOT NULL DEFAULT 0,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 15,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lets a quotation know it has already been converted, so the
-- "Convert to Invoice" button can turn into "View Invoice" instead.
ALTER TABLE quotations
    ADD COLUMN IF NOT EXISTS converted_invoice_id INT NULL;
