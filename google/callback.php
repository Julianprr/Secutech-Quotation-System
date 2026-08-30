<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/google.php';
require_once __DIR__ . '/../includes/google_calendar.php';

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (isset($_GET['error'])) {
    header('Location: ../appointments/index.php?google_error=' . urlencode($_GET['error']));
    exit;
}

if ($code === '' || $state === '' || $state !== ($_SESSION['google_oauth_state'] ?? '')) {
    die('Invalid or expired authorization request. Please try connecting again.');
}

unset($_SESSION['google_oauth_state']);

$result = google_exchange_code($code);

if (isset($result['error'])) {
    header('Location: ../appointments/index.php?google_error=' . urlencode($result['error']));
    exit;
}

if (empty($result['refresh_token'])) {
    /*
     * Google only issues a refresh_token the FIRST time an app is
     * authorized (or after access is revoked and re-granted). If the
     * user had connected before and Google didn't send a fresh one,
     * we keep the token already on file.
     */
    header('Location: ../appointments/index.php?connected=1');
    exit;
}

$stmt = $pdo->query("SELECT id FROM company_settings ORDER BY id ASC LIMIT 1");
$company_id = $stmt->fetchColumn();

if ($company_id) {

    $stmt = $pdo->prepare("
        UPDATE company_settings
        SET google_refresh_token = ?, google_calendar_connected_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$result['refresh_token'], $company_id]);

}

header('Location: ../appointments/index.php?connected=1');
exit;
