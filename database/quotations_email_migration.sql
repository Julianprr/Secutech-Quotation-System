-- ============================================================
-- Secutech Quotation System - Email tracking on quotations
-- Run this once in phpMyAdmin.
-- ============================================================

ALTER TABLE quotations
    ADD COLUMN IF NOT EXISTS emailed_at DATETIME NULL;
