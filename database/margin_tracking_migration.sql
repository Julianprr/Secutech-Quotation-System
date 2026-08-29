-- ============================================================
-- Secutech Quotation System - Cost & margin tracking
-- Run this once in phpMyAdmin.
--
-- These columns are for YOUR reference only - they are never
-- selected or shown on any customer-facing quote, invoice, PDF,
-- or email. They only appear on the internal quote-building screen.
-- ============================================================

ALTER TABLE quotation_items
    ADD COLUMN IF NOT EXISTS cost_price DECIMAL(12,2) NULL,
    ADD COLUMN IF NOT EXISTS margin_percent DECIMAL(6,2) NULL;
