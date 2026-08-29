<?php

/*
 * Copy this file to mail.local.php and fill in your real "quotes"
 * mailbox credentials.
 *
 * Find these in cPanel -> Email Accounts -> next to the mailbox,
 * click "Connect Devices" (or "Set Up Mail Client") to see the
 * exact Outgoing Server host, port, and encryption type.
 *
 * mail.local.php is listed in .gitignore, so it will never be
 * committed or pushed to GitHub.
 */

define('SMTP_HOST', 'mail.secutechsa.co.za');   // Outgoing server, from cPanel
define('SMTP_PORT', 587);                        // 587 for TLS, 465 for SSL
define('SMTP_ENCRYPTION', 'tls');                 // 'tls' or 'ssl' - match cPanel
define('SMTP_USER', 'quotes@secutechsa.co.za');
define('SMTP_PASS', 'your-real-mailbox-password');
define('SMTP_FROM_EMAIL', 'quotes@secutechsa.co.za');
define('SMTP_FROM_NAME', 'SecuTech SA');
