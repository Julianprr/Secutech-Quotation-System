-- ============================================================
-- Secutech Quotation System - Banking details
-- Run this once in phpMyAdmin. It adds the columns, then update
-- the values with the second statement below (edit the real
-- details in before running it).
-- ============================================================

ALTER TABLE company_settings
    ADD COLUMN IF NOT EXISTS bank_name VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS account_holder VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS account_number VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS branch_code VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS account_type VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS swift_code VARCHAR(50) NULL;

-- ============================================================
-- Fill in your real banking details below, then run this
-- second statement (edit the values first!).
-- ============================================================

UPDATE company_settings
SET
    bank_name = 'Your Bank Name',
    account_holder = 'New Invest 147 Pty Ltd',
    account_number = '0000000000',
    branch_code = '000000',
    account_type = 'Business Cheque Account',
    swift_code = ''
WHERE id = (SELECT id FROM (SELECT id FROM company_settings ORDER BY id ASC LIMIT 1) AS t);
