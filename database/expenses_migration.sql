-- ============================================================
-- Secutech Quotation System - Expense tracking
-- Run this once in phpMyAdmin.
-- ============================================================

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    invoice_number VARCHAR(100) NULL,
    expense_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NULL,
    vat_amount DECIMAL(12,2) NULL,
    total DECIMAL(12,2) NOT NULL,
    notes TEXT NULL,
    receipt_image_path VARCHAR(255) NULL,
    receipt_mime_type VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (supplier_name),
    INDEX idx_expense_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
