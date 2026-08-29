<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/quote_pdf.php';

$quote_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($quote_id <= 0) {
    die('Invalid quotation ID.');
}


/* -------------------------------------------------
   GET COMPANY SETTINGS
------------------------------------------------- */

$stmt = $pdo->query("SELECT * FROM company_settings ORDER BY id ASC LIMIT 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC);


/* -------------------------------------------------
   GET QUOTATION + CUSTOMER
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT q.*, c.company_name, c.contact_name, c.telephone, c.email AS customer_email
    FROM quotations q
    INNER JOIN customers c ON c.id = q.customer_id
    WHERE q.id = ?
");
$stmt->execute([$quote_id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    die('Quotation not found.');
}


/* -------------------------------------------------
   GET ITEMS + TOTALS
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT item_code, description, quantity, unit_price, discount, vat_rate, line_total
    FROM quotation_items
    WHERE quotation_id = ?
    ORDER BY sort_order, id
");
$stmt->execute([$quote_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subtotal = (float)$quote['subtotal'];
$vat_total = (float)$quote['vat_amount'];
$grand_total = (float)$quote['total'];


/* -------------------------------------------------
   BUILD EMAIL HTML
------------------------------------------------- */

function build_quote_email_html(array $company, array $quote, string $note): string
{
    $legal_name = 'New Invest 147 Pty Ltd';
    $company_name = $company['company_name'] ?? 'SecuTech SA';
    $tagline = 'Your One Stop Solution for Security and IT';

    ob_start();
    ?>
<div style="font-family: Arial, Helvetica, sans-serif; max-width:600px; margin:0 auto; color:#222;">

    <div style="background:#172d4d; color:#fff; padding:22px; text-align:center;">
        <h1 style="margin:0; font-size:20px;"><?= htmlspecialchars($company_name) ?></h1>
        <div style="font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#b5d900; margin-top:5px;">
            <?= htmlspecialchars($tagline) ?>
        </div>
    </div>

    <div style="padding:24px; font-size:14px; line-height:1.6;">

        <?php if ($note !== ''): ?>
        <p><?= nl2br(htmlspecialchars($note)) ?></p>
        <hr style="border:none; border-top:1px solid #eee; margin:20px 0;">
        <?php endif; ?>

        <p>
            Please find attached quotation
            <strong><?= htmlspecialchars($quote['quote_number']) ?></strong>
            for <strong><?= htmlspecialchars($quote['company_name']) ?></strong>,
            valid until <strong><?= htmlspecialchars($quote['valid_until']) ?></strong>.
        </p>

        <p>Please don't hesitate to get in touch if you have any questions.</p>

    </div>

    <div style="background:#f4f5f7; padding:16px; text-align:center; font-size:11px; color:#666;">
        Entigration of Technology and Security into your life and business<br>
        <?= htmlspecialchars($legal_name) ?> &nbsp;|&nbsp; Trading as <?= htmlspecialchars($company_name) ?>
    </div>

</div>
    <?php
    return ob_get_clean();
}


/* -------------------------------------------------
   HANDLE SEND
------------------------------------------------- */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $to_email = trim($_POST['to_email'] ?? '');
    $cc_email = trim($_POST['cc_email'] ?? '');
    $message_note = trim($_POST['message_note'] ?? '');

    if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address to send to.';

    } else {

        $html_body = build_quote_email_html(
            $company ?: [],
            $quote,
            $message_note
        );

        $pdf_bytes = generate_quote_pdf(
            $company ?: [],
            $quote,
            $items,
            $subtotal,
            $vat_total,
            $grand_total
        );

        $attachment = [
            'filename' => 'Quotation-' . $quote['quote_number'] . '.pdf',
            'content'  => $pdf_bytes,
            'mime'     => 'application/pdf',
        ];

        $from_name = $company['company_name'] ?? 'SecuTech SA';

        $subject = 'Quotation ' . $quote['quote_number'] . ' from ' . $from_name;

        $cc_list = [];
        if ($cc_email !== '' && filter_var($cc_email, FILTER_VALIDATE_EMAIL)) {
            $cc_list[] = $cc_email;
        }

        $result = send_app_email($to_email, $subject, $html_body, $cc_list, $attachment);

        if ($result['success']) {

            $stmt = $pdo->prepare("
                UPDATE quotations
                SET status = 'Sent', emailed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quote_id]);

            header('Location: ../view/index.php?id=' . $quote_id . '&emailed=1');
            exit;

        } else {

            $error = $result['error'];

        }

    }

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Quotation - SecuTech</title>

    <style>
        * { box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f5f7;
            margin: 0;
            padding: 40px 20px;
            color: #222;
        }

        .box {
            max-width: 520px;
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

        label {
            display: block;
            margin-top: 16px;
            font-weight: bold;
            font-size: 14px;
        }

        input[type=text],
        input[type=email],
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
            font-family: inherit;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
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

        .cancel {
            display: inline-block;
            margin-top: 22px;
            margin-left: 12px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        .error {
            background: #ffe1e1;
            color: #a00000;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .warning {
            background: #fff3cd;
            color: #8a6400;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }
    </style>
</head>

<body>

<div class="box">

    <h2>Email Quotation <?= htmlspecialchars($quote['quote_number']) ?></h2>

    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($quote['customer_email'])): ?>
        <div class="warning">
            This customer doesn't have an email address on file. You can still
            type one in below, or add it to their profile for next time.
        </div>
    <?php endif; ?>

    <form method="POST">

        <input type="hidden" name="id" value="<?= (int)$quote_id ?>">

        <label>Send to</label>
        <input
            type="email"
            name="to_email"
            value="<?= htmlspecialchars($_POST['to_email'] ?? $quote['customer_email'] ?? '') ?>"
            required
        >

        <label>CC (optional)</label>
        <input
            type="email"
            name="cc_email"
            value="<?= htmlspecialchars($_POST['cc_email'] ?? '') ?>"
        >

        <label>Message (optional)</label>
        <textarea
            name="message_note"
            placeholder="Add a personal note above the quote..."
        ><?= htmlspecialchars($_POST['message_note'] ?? '') ?></textarea>

        <button type="submit" class="btn">
            Send Quotation
        </button>

        <a href="../view/index.php?id=<?= (int)$quote_id ?>" class="cancel">
            Cancel
        </a>

    </form>

</div>

</body>
</html>
