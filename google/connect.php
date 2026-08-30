<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/google.php';
require_once __DIR__ . '/../includes/google_calendar.php';

if (GOOGLE_CLIENT_ID === '') {
    die(
        'Google Calendar is not configured yet. Copy config/google.local.example.php ' .
        'to config/google.local.php and add your real Client ID and Client Secret.'
    );
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

header('Location: ' . google_get_auth_url($state));
exit;
