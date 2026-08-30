<?php

/*
 * Google Calendar OAuth configuration.
 *
 * Real credentials are NOT stored in this file, so it's safe to commit
 * to a public repository like GitHub.
 *
 * Loaded from one of two places (in this order):
 *
 *   1. config/google.local.php - a git-ignored file with your real
 *                                 Client ID and Client Secret.
 *                                 Copy config/google.local.example.php to create it.
 *
 *   2. Environment variables  - GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET
 */

$local_config = __DIR__ . '/google.local.php';

if (file_exists($local_config)) {
    require $local_config;
} else {
    if (!defined('GOOGLE_CLIENT_ID'))     define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
    if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}

if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', 'https://www.secutechsa.co.za/quotes/google/callback.php');
}
