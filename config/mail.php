<?php

/*
 * Outgoing email (SMTP) configuration.
 *
 * Real mailbox credentials are NOT stored in this file, so it's safe
 * to commit to a public repository like GitHub.
 *
 * Loaded from one of two places (in this order):
 *
 *   1. config/mail.local.php - a git-ignored file with your real
 *                               mailbox credentials.
 *                               Copy config/mail.local.example.php to create it.
 *
 *   2. Environment variables  - SMTP_HOST, SMTP_PORT, SMTP_USER,
 *                                SMTP_PASS, SMTP_ENCRYPTION,
 *                                SMTP_FROM_EMAIL, SMTP_FROM_NAME
 */

$local_config = __DIR__ . '/mail.local.php';

if (file_exists($local_config)) {
    require $local_config;
} else {
    if (!defined('SMTP_HOST'))        define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
    if (!defined('SMTP_PORT'))        define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
    if (!defined('SMTP_ENCRYPTION'))  define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
    if (!defined('SMTP_USER'))        define('SMTP_USER', getenv('SMTP_USER') ?: '');
    if (!defined('SMTP_PASS'))        define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
    if (!defined('SMTP_FROM_EMAIL'))  define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
    if (!defined('SMTP_FROM_NAME'))   define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'SecuTech SA');
}
