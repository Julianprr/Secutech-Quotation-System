<?php

/*
 * Database configuration
 *
 * Real credentials are NOT stored in this file, so it's safe to commit
 * to a public repository like GitHub.
 *
 * Credentials are loaded from one of two places (in this order):
 *
 *   1. config/db.local.php  - a git-ignored file containing your real
 *                              credentials for this specific server.
 *                              Copy config/db.local.example.php to create it.
 *
 *   2. Environment variables - DB_HOST, DB_NAME, DB_USER, DB_PASS
 *                              (useful for hosts that let you set env vars,
 *                              e.g. Railway, Render, Docker, etc.)
 */

$local_config = __DIR__ . '/db.local.php';

if (file_exists($local_config)) {
    require $local_config;
} else {
    if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: '');
    if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: '');
    if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
}

if (DB_NAME === '' || DB_USER === '') {
    die(
        'Database is not configured. Copy config/db.local.example.php to ' .
        'config/db.local.php and fill in your real credentials, or set ' .
        'the DB_HOST / DB_NAME / DB_USER / DB_PASS environment variables.'
    );
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('Quotation system database error: ' . $e->getMessage());
    die('Database connection unavailable.');
}
