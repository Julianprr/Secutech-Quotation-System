<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$customer_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($customer_id <= 0) {
    die('Invalid customer ID.');
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die('Customer not found.');
}


/* -------------------------------------------------
   HANDLE ACTUAL DELETE
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {

    try {

        $pdo->beginTransaction();

        /* Delete invoice items + invoices for this customer */

        $stmt = $pdo->prepare("SELECT id FROM invoices WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $invoice_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($invoice_ids)) {
            $placeholders = implode(',', array_fill(0, count($invoice_ids), '?'));
            $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id IN ($placeholders)")->execute($invoice_ids);
            $pdo->prepare("DELETE FROM invoices WHERE id IN ($placeholders)")->execute($invoice_ids);
        }

        /* Delete quotation items + quotations for this customer */

        $stmt = $pdo->prepare("SELECT id FROM quotations WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $quote_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($quote_ids)) {
            $placeholders = implode(',', array_fill(0, count($quote_ids), '?'));
            $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id IN ($placeholders)")->execute($quote_ids);
            $pdo->prepare("DELETE FROM quotations WHERE id IN ($placeholders)")->execute($quote_ids);
        }

        /* Finally, the customer itself */

        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);

        $pdo->commit();

        header('Location: index.php?deleted=1');
        exit;

    } catch (PDOException $e) {

        $pdo->rollBack();
        error_log('Customer delete error: ' . $e->getMessage());
        die('Something went wrong deleting this customer. Please try again.');

    }

}


/* -------------------------------------------------
   COUNT LINKED RECORDS FOR THE WARNING
------------------------------------------------- */

$stmt = $pdo->prepare("SELECT COUNT(*) FROM quotations WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$quote_count = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$invoice_count = (int)$stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Customer - SecuTech</title>

    <style>
        * { box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f5f7;
            margin: 0;
            padding: 50px 20px;
            color: #222;
        }

        .box {
            max-width: 480px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        h2 {
            margin-top: 0;
            color: #172d4d;
        }

        .warning {
            background: #ffe1e1;
            color: #a00000;
            padding: 14px 16px;
            border-radius: 6px;
            font-size: 14px;
            line-height: 1.6;
            margin: 18px 0;
        }

        .warning strong {
            display: block;
            margin-bottom: 6px;
        }

        .btn-danger {
            background: #a00000;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }

        .cancel {
            display: inline-block;
            margin-top: 10px;
            margin-left: 12px;
            color: #666;
            text-decoration: none;
        }

        label {
            display: block;
            margin-top: 16px;
            font-size: 14px;
        }

        input[type=text] {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
    </style>
</head>

<body>

<div class="box">

    <h2>Delete Customer</h2>

    <p>You're about to permanently delete:</p>

    <p style="font-size:16px; font-weight:bold;"><?= e($customer['company_name']) ?></p>

    <div class="warning">
        <strong>This cannot be undone.</strong>
        This will also permanently delete
        <?= $quote_count ?> quotation<?= $quote_count === 1 ? '' : 's' ?>
        and
        <?= $invoice_count ?> invoice<?= $invoice_count === 1 ? '' : 's' ?>
        linked to this customer.
    </div>

    <form method="POST" onsubmit="return confirmFinal();">

        <input type="hidden" name="id" value="<?= (int)$customer_id ?>">

        <label>
            Type "yes" to confirm:
        </label>
        <input type="text" id="confirmName" autocomplete="off" placeholder="yes">

        <button type="submit" name="confirm_delete" value="1" class="btn-danger">
            Permanently Delete
        </button>

        <a href="index.php" class="cancel">Cancel</a>

    </form>

</div>

<script>
function confirmFinal() {
    const typed = document.getElementById('confirmName').value.trim().toLowerCase();

    if (typed !== 'yes') {
        alert('Please type "yes" to confirm deletion.');
        return false;
    }

    return true;
}
</script>

</body>
</html>
