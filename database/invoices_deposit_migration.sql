-- ============================================================
-- Secutech Quotation System - Deposit tracking on invoices
-- Run this once in phpMyAdmin, AFTER invoices_migration.sql
-- has already been run.
-- ============================================================

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS deposit_amount DECIMAL(12,2) NULL;
