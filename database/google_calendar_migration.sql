-- ============================================================
-- Secutech Quotation System - Google Calendar connection
-- Run this once in phpMyAdmin.
-- ============================================================

ALTER TABLE company_settings
    ADD COLUMN IF NOT EXISTS google_refresh_token TEXT NULL,
    ADD COLUMN IF NOT EXISTS google_calendar_connected_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS google_calendar_email VARCHAR(255) NULL;
