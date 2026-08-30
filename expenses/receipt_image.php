<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Not logged in.');
}

require_once __DIR__ . '/../config/db.php';

$expense_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT receipt_image_path, receipt_mime_type FROM expenses WHERE id = ?");
$stmt->execute([$expense_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['receipt_image_path'])) {
    http_response_code(404);
    exit('Not found.');
}

/* Filenames are always our own generated hex strings - strip any path
   characters defensively regardless. */
$filename = basename($row['receipt_image_path']);

$storage_dir = dirname(__DIR__, 3) . '/private_uploads/receipts/';
$full_path = $storage_dir . $filename;

if (!file_exists($full_path)) {
    http_response_code(404);
    exit('Receipt image not found.');
}

header('Content-Type: ' . ($row['receipt_mime_type'] ?: 'image/jpeg'));
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=3600');

readfile($full_path);
exit;
