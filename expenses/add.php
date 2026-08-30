<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/../includes/receipt_vision.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Storage lives outside public_html entirely, so receipt photos are
   never directly reachable by URL - only through receipt_image.php,
   which checks the login session first. */
$storage_dir = dirname(__DIR__, 3) . '/private_uploads/receipts/';

if (!is_dir($storage_dir)) {
    @mkdir($storage_dir, 0755, true);
}

$step = 'upload';
$error = '';
$extracted = [];
$preview_data_uri = '';
$pending_filename = '';


/* -------------------------------------------------
   STEP 1: PHOTO UPLOADED - ANALYZE WITH CLAUDE
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt_photo']) && $_FILES['receipt_photo']['error'] === UPLOAD_ERR_OK) {

    if (ANTHROPIC_API_KEY === '' || ANTHROPIC_API_KEY === 'sk-ant-your-real-key-here') {

        $error = 'The AI assistant\'s API key isn\'t configured yet, so receipts can\'t be auto-read. ' .
                 'You can still add this expense manually below.';
        $step = 'map';

    } else {

        $tmp_path = $_FILES['receipt_photo']['tmp_name'];
        $mime_type = mime_content_type($tmp_path);

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime_type, $allowed_types, true)) {

            $error = 'Please upload a JPEG, PNG, or WEBP photo. If your phone saves photos as HEIC, ' .
                     'switch your camera format to "Most Compatible" in Settings, or take the photo ' .
                     'directly through this form instead of choosing one from your gallery.';

        } else {

            $normalized = normalize_receipt_image($tmp_path, $mime_type);

            $pending_filename = 'receipt_' . bin2hex(random_bytes(8)) . '.jpg';
            file_put_contents($storage_dir . $pending_filename, $normalized['bytes']);

            $image_base64 = base64_encode($normalized['bytes']);
            $extraction_result = extract_receipt_data($image_base64, $normalized['mime_type']);

            $_SESSION['pending_receipt_filename'] = $pending_filename;
            $_SESSION['pending_receipt_mime'] = $normalized['mime_type'];

            if (isset($extraction_result['error'])) {
                $error = $extraction_result['error'];
                $extracted = [];
            } else {
                $extracted = $extraction_result;
            }

            $preview_data_uri = 'data:' . $normalized['mime_type'] . ';base64,' . $image_base64;
            $step = 'map';

        }

    }

}


/* -------------------------------------------------
   STEP 2: CONFIRMED - SAVE THE EXPENSE
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_expense'])) {

    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $expense_date = trim($_POST['expense_date'] ?? '');
    $subtotal = ($_POST['subtotal'] ?? '') !== '' ? (float)$_POST['subtotal'] : null;
    $vat_amount = ($_POST['vat_amount'] ?? '') !== '' ? (float)$_POST['vat_amount'] : null;
    $total = (float)($_POST['total'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    $saved_filename = $_SESSION['pending_receipt_filename'] ?? null;
    $saved_mime = $_SESSION['pending_receipt_mime'] ?? null;

    if ($supplier_name === '' || $expense_date === '' || $total <= 0) {

        $error = 'Please fill in at least the supplier name, date, and total.';
        $step = 'map';
        $extracted = $_POST;

        if ($saved_filename) {
            $full_path = $storage_dir . $saved_filename;
            if (file_exists($full_path)) {
                $preview_data_uri = 'data:' . $saved_mime . ';base64,' . base64_encode(file_get_contents($full_path));
            }
        }

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO expenses
            (supplier_name, invoice_number, expense_date, subtotal, vat_amount, total, notes, receipt_image_path, receipt_mime_type)
            VALUES
            (:supplier_name, :invoice_number, :expense_date, :subtotal, :vat_amount, :total, :notes, :receipt_image_path, :receipt_mime_type)
        ");

        $stmt->execute([
            ':supplier_name'       => $supplier_name,
            ':invoice_number'      => $invoice_number !== '' ? $invoice_number : null,
            ':expense_date'        => $expense_date,
            ':subtotal'            => $subtotal,
            ':vat_amount'          => $vat_amount,
            ':total'               => $total,
            ':notes'               => $notes !== '' ? $notes : null,
            ':receipt_image_path'  => $saved_filename,
            ':receipt_mime_type'   => $saved_mime,
        ]);

        unset($_SESSION['pending_receipt_filename'], $_SESSION['pending_receipt_mime']);

        header('Location: index.php?saved=1');
        exit;

    }

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense - SecuTech Quotation System</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f5f7;
            color: #222;
        }

        .header {
            background: #111;
            color: white;
            padding: 14px 30px;
            display: flex;
            flex-wrap: wrap;
            row-gap: 10px;
            justify-content: space-between;
            align-items: center;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 20px;
        }

        .header-logo {
            width: 45px;
            height: auto;
            display: block;
        }

        .header a {
            color: white;
            text-decoration: none;
        }

        .container {
            max-width: 640px;
            margin: 45px auto;
            padding: 0 25px;
        }

        h2 {
            margin-top: 0;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .error {
            background: #ffe1e1;
            color: #a00000;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .info {
            background: #eef2ff;
            color: #172d4d;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        label {
            display: block;
            margin-top: 16px;
            font-weight: bold;
            font-size: 14px;
        }

        input[type=file],
        input[type=text],
        input[type=date],
        input[type=number],
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            font-family: inherit;
        }

        textarea {
            min-height: 70px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            margin-top: 22px;
            background: #172d4d;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .link-back {
            display: inline-block;
            margin-top: 20px;
            margin-left: 12px;
            color: #666;
            text-decoration: none;
        }

        .preview-img {
            width: 100%;
            max-height: 320px;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            background: #fafafa;
        }

        .upload-box {
            text-align: center;
            padding: 30px 20px;
            border: 2px dashed #ccc;
            border-radius: 10px;
        }
    </style>
</head>

<body>

<div class="header">
    <a href="../dashboard.php" class="brand-link">
        <img src="../assets/secutech-logo.png" alt="SecuTech SA" class="header-logo">
        <span>SecuTech Quoting System</span>
    </a>
    <div style="display:flex; align-items:center; gap:20px;">
        <a href="../dashboard.php">Home</a>
        <a href="index.php">Expenses</a>
    </div>
</div>

<div class="container">

    <div class="card">

        <h2>Add Expense</h2>

        <?php if ($error !== ''): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>


        <?php if ($step === 'upload'): ?>

            <div class="info">
                Take a photo of the supplier receipt or invoice, or upload one from
                your gallery. Claude will read the supplier, date, and total
                automatically - you'll get a chance to check and correct it
                before it's saved.
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="upload-box">
                    <input type="file" name="receipt_photo" accept="image/*" capture="environment" required>
                </div>
                <button type="submit" class="btn" style="width:100%;">
                    Analyze Receipt
                </button>
            </form>

        <?php elseif ($step === 'map'): ?>

            <?php if ($preview_data_uri !== ''): ?>
                <img src="<?= e($preview_data_uri) ?>" class="preview-img" alt="Receipt preview">
            <?php endif; ?>

            <form method="POST">

                <label>Supplier Name</label>
                <input type="text" name="supplier_name" value="<?= e($extracted['supplier_name'] ?? '') ?>" required>

                <div class="form-row">
                    <div>
                        <label>Invoice / Receipt Number</label>
                        <input type="text" name="invoice_number" value="<?= e($extracted['invoice_number'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Date</label>
                        <input type="date" name="expense_date" value="<?= e($extracted['expense_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label>Subtotal (excl. VAT)</label>
                        <input type="number" name="subtotal" step="0.01" value="<?= e((string)($extracted['subtotal'] ?? '')) ?>">
                    </div>
                    <div>
                        <label>VAT Amount</label>
                        <input type="number" name="vat_amount" step="0.01" value="<?= e((string)($extracted['vat_amount'] ?? '')) ?>">
                    </div>
                </div>

                <label>Total (required)</label>
                <input type="number" name="total" step="0.01" value="<?= e((string)($extracted['total'] ?? '')) ?>" required>

                <label>Notes</label>
                <textarea name="notes" placeholder="Optional"><?= e($extracted['notes'] ?? '') ?></textarea>

                <button type="submit" name="save_expense" class="btn">
                    Save Expense
                </button>

                <a href="add.php" class="link-back">Start Over</a>

            </form>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
