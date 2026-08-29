<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$invoice_id = (int)($_GET['id'] ?? 0);

if ($invoice_id <= 0) {
    die('Invalid invoice ID.');
}


/* -------------------------------------------------
   UPDATE STATUS (Unpaid / Deposit Paid / Paid in Full)
------------------------------------------------- */

$status_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $allowed_statuses = ['Unpaid', 'Deposit Paid', 'Paid in Full'];

    $new_status = trim($_POST['status'] ?? '');

    if (in_array($new_status, $allowed_statuses, true)) {

        if ($new_status === 'Deposit Paid') {

            $deposit_amount = isset($_POST['deposit_amount']) && $_POST['deposit_amount'] !== ''
                ? (float)$_POST['deposit_amount']
                : null;

            $stmt = $pdo->prepare("
                UPDATE invoices
                SET status = ?, deposit_amount = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$new_status, $deposit_amount, $invoice_id]);

        } else {

            $stmt = $pdo->prepare("
                UPDATE invoices
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$new_status, $invoice_id]);

        }

        $status_message = 'Invoice status updated to "' . $new_status . '".';

    }

}


/* -------------------------------------------------
   GET COMPANY SETTINGS
------------------------------------------------- */

$stmt = $pdo->query("
    SELECT *
    FROM company_settings
    ORDER BY id ASC
    LIMIT 1
");

$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    die('Company settings have not been configured.');
}


/* -------------------------------------------------
   GET INVOICE + CUSTOMER
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        i.*,
        c.company_name,
        c.contact_name,
        c.address,
        c.telephone,
        c.email,
        c.vat_number
    FROM invoices i
    INNER JOIN customers c ON c.id = i.customer_id
    WHERE i.id = ?
");

$stmt->execute([$invoice_id]);

$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die('Invoice not found.');
}


/* -------------------------------------------------
   GET INVOICE ITEMS
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        ii.*,
        p.unit AS product_unit
    FROM invoice_items ii
    LEFT JOIN products p ON p.id = ii.product_id
    WHERE ii.invoice_id = ?
    ORDER BY ii.sort_order, ii.id
");

$stmt->execute([$invoice_id]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* -------------------------------------------------
   CALCULATE TOTALS
------------------------------------------------- */

$subtotal = 0;
$vat_total = 0;

foreach ($items as $item) {

    $line_total = (float)$item['line_total'];
    $vat_rate   = (float)$item['vat_rate'];

    $subtotal += $line_total;
    $vat_total += $line_total * ($vat_rate / 100);
}

$grand_total = $subtotal + $vat_total;


/* -------------------------------------------------
   HELPERS
------------------------------------------------- */

function money($amount)
{
    return 'R ' . number_format((float)$amount, 2);
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/* -------------------------------------------------
   LOGO
------------------------------------------------- */

$logo = '../assets/secutech-logo.png';

if (!empty($company['logo_path'])) {

    $logo_path = trim($company['logo_path']);

    if (
        str_starts_with($logo_path, 'http://') ||
        str_starts_with($logo_path, 'https://')
    ) {
        $logo = $logo_path;
    } else {
        $logo = '../' . ltrim($logo_path, '/');
    }
}


/* -------------------------------------------------
   COMPANY INFORMATION
------------------------------------------------- */

$company_name = $company['company_name'] ?? 'SecuTech SA';

/*
 * Legal entity for invoices.
 * SecuTech SA is the trading name / brand.
 */
$legal_name = 'New Invest 147 Pty Ltd';

$registration = $company['registration_number'] ?? '';
$vat_number   = $company['vat_number'] ?? '';
$phone        = $company['phone'] ?? '';
$email        = $company['email'] ?? '';
$website      = $company['website'] ?? '';
$address      = $company['address'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e($invoice['invoice_number']) ?> - <?= e($legal_name) ?></title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #eeeeee;
    color: #17243a;
    font-family: Arial, Helvetica, sans-serif;
}

.page {
    width: 210mm;
    min-height: 297mm;
    margin: 20px auto;
    background: #fff;
    padding: 12mm;
    box-shadow: 0 0 12px rgba(0,0,0,.15);
}


/* HEADER */

.company-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    border-bottom: 3px solid #172d4d;
    padding-bottom: 12px;
    margin-bottom: 15px;
}

.brand {
    width: 56%;
}

.logo {
    width: 235px;
    max-width: 100%;
    height: auto;
    display: block;
    margin-bottom: 6px;
}

.tagline {
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 1.5px;
    color: #172d4d;
    text-transform: uppercase;
}

.services {
    margin-top: 5px;
    font-size: 9px;
    color: #555;
    font-weight: bold;
}

.company-info {
    width: 44%;
    font-size: 10px;
    line-height: 1.4;
    color: #333;
    border-left: 2px solid #b5d900;
    padding-left: 12px;
}

.company-info strong {
    color: #172d4d;
    font-size: 13px;
}

.company-info .legal {
    font-weight: bold;
    color: #172d4d;
}


/* INVOICE BANNER */

.quote-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #172d4d;
    color: #fff;
    padding: 10px 15px;
    margin-bottom: 15px;
    border-radius: 3px;
}

.quote-title {
    font-size: 24px;
    font-weight: bold;
    letter-spacing: 1px;
}

.quote-number {
    background: #b5d900;
    color: #172d4d;
    padding: 7px 13px;
    font-size: 15px;
    font-weight: bold;
    border-radius: 3px;
}


/* DETAILS */

.details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin-bottom: 16px;
}

.details-box {
    font-size: 11px;
    line-height: 1.45;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 12px;
}

.details-box h3 {
    margin: -12px -12px 9px -12px;
    padding: 7px 10px;
    background: #172d4d;
    color: #fff;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.details-box strong {
    color: #172d4d;
}

.quote-meta div {
    margin-bottom: 3px;
}

.status-pill {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 10px;
}

.status-pill.Unpaid {
    background: #ffe1e1;
    color: #a00000;
}

.status-pill.Deposit-Paid {
    background: #fff3cd;
    color: #8a6400;
}

.status-pill.Paid-in-Full {
    background: #dff5df;
    color: #216b21;
}


/* ITEMS */

.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 5px;
}

.items-table th {
    background: #172d4d;
    color: #fff;
    padding: 7px 5px;
    font-size: 9px;
    text-align: left;
}

.items-table td {
    padding: 7px 5px;
    border-bottom: 1px solid #ddd;
    font-size: 9px;
    vertical-align: top;
}

.items-table tbody tr:nth-child(even) {
    background: #f7f9fb;
}

.text-right {
    text-align: right !important;
}

.text-center {
    text-align: center !important;
}


/* TOTALS */

.totals {
    width: 285px;
    margin-left: auto;
    margin-top: 14px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    font-size: 12px;
}

.total-row.grand {
    background: #172d4d;
    color: #fff;
    margin-top: 5px;
    padding: 9px 12px;
    font-size: 17px;
    font-weight: bold;
    border-radius: 3px;
}

.total-row.grand strong {
    color: #b5d900;
}


/* BOTTOM SECTIONS */

.bottom-section {
    margin-top: 18px;
}

.bottom-box {
    margin-bottom: 12px;
}

.bottom-box h3 {
    color: #172d4d;
    font-size: 11px;
    border-bottom: 2px solid #b5d900;
    padding-bottom: 3px;
    margin: 0 0 5px 0;
}

.bottom-box p {
    white-space: pre-line;
    font-size: 10px;
    line-height: 1.4;
    margin: 0;
}


/* FOOTER */

.footer {
    margin-top: 18px;
    padding-top: 8px;
    border-top: 2px solid #172d4d;
    text-align: center;
    font-size: 9px;
    color: #555;
}

.footer .slogan {
    font-size: 13px;
    font-weight: bold;
    color: #172d4d;
    margin-bottom: 3px;
}

.footer .services-footer {
    color: #172d4d;
    font-weight: bold;
    letter-spacing: .5px;
}


/* STATUS BAR (screen only) */

.status-bar {
    width: 210mm;
    margin: 15px auto 0 auto;
    background: white;
    border-radius: 6px;
    padding: 16px 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.status-bar form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-bar select {
    padding: 9px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}

.status-bar button {
    background: #172d4d;
    color: white;
    border: none;
    padding: 9px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.status-bar button:hover {
    background: #263f61;
}

.status-message {
    background: #dff5df;
    color: #216b21;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
}


/* BUTTONS */

.screen-actions {
    width: 210mm;
    margin: 15px auto;
    display: flex;
    gap: 8px;
}

.screen-actions a,
.screen-actions button {
    padding: 9px 15px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

.back {
    background: #ddd;
    color: #222;
}

.print {
    background: #172d4d;
    color: #fff;
}


/* PRINT */

@media print {

    body {
        background: #fff;
    }

    .page {
        width: 210mm;
        min-height: 297mm;
        margin: 0;
        padding: 11mm;
        box-shadow: none;
    }

    .screen-actions,
    .status-bar {
        display: none;
    }

    @page {
        size: A4;
        margin: 0;
    }
}


/* MOBILE */

@media(max-width: 800px) {

    .page {
        width: 100%;
        margin: 0;
        padding: 20px;
    }

    .screen-actions,
    .status-bar {
        width: 100%;
        padding: 10px;
    }

    .company-header {
        flex-direction: column;
    }

    .brand,
    .company-info {
        width: 100%;
    }

    .company-info {
        border-left: none;
        border-top: 2px solid #b5d900;
        padding-left: 0;
        padding-top: 8px;
    }

    .quote-banner {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .details {
        grid-template-columns: 1fr;
    }

    .totals {
        width: 100%;
    }
}

</style>

</head>

<body>


<!-- BUTTONS -->

<div class="screen-actions">

<a class="back" href="../dashboard.php">
    Home
</a>

<a class="back" href="list.php">
    ← Back to Invoices
</a>

<?php if (!empty($invoice['quotation_id'])): ?>
<a class="back" href="../view/index.php?id=<?= (int)$invoice['quotation_id'] ?>">
    View Original Quotation
</a>
<?php endif; ?>

<button class="print" onclick="window.print()">
    Print / Save PDF
</button>

</div>


<!-- STATUS BAR (screen only, hidden when printing) -->

<div class="status-bar">

    <?php if ($status_message): ?>
        <div class="status-message"><?= e($status_message) ?></div>
    <?php endif; ?>

    <form method="POST" id="statusForm">

        <label for="status">
            <strong>Payment Status:</strong>
        </label>

        <select name="status" id="status" onchange="toggleDepositField()">
            <option value="Unpaid" <?= $invoice['status'] === 'Unpaid' ? 'selected' : '' ?>>
                Unpaid
            </option>
            <option value="Deposit Paid" <?= $invoice['status'] === 'Deposit Paid' ? 'selected' : '' ?>>
                Deposit Paid
            </option>
            <option value="Paid in Full" <?= $invoice['status'] === 'Paid in Full' ? 'selected' : '' ?>>
                Paid in Full
            </option>
        </select>

        <span id="depositFieldWrap" style="display:none; align-items:center; gap:8px;">

            <label for="deposit_amount">
                Deposit Amount:
            </label>

            <input
                type="number"
                name="deposit_amount"
                id="deposit_amount"
                step="0.01"
                min="0"
                value="<?= e($invoice['deposit_amount'] ?? '') ?>"
                style="width:120px; padding:9px; border:1px solid #ccc; border-radius:6px;"
            >

            <button type="button" onclick="fillDefaultDeposit()" style="background:#eee; color:#222;">
                Use 75%
            </button>

        </span>

        <button type="submit" name="update_status" value="1">
            Update Status
        </button>

    </form>

</div>


<script>

const invoiceGrandTotal = <?= json_encode($grand_total) ?>;

function toggleDepositField() {

    const status = document.getElementById('status').value;
    const wrap = document.getElementById('depositFieldWrap');

    if (status === 'Deposit Paid') {

        wrap.style.display = 'inline-flex';

        const depositInput = document.getElementById('deposit_amount');

        if (!depositInput.value) {
            depositInput.value = (invoiceGrandTotal * 0.75).toFixed(2);
        }

    } else {

        wrap.style.display = 'none';

    }

}

function fillDefaultDeposit() {
    document.getElementById('deposit_amount').value = (invoiceGrandTotal * 0.75).toFixed(2);
}

document.addEventListener('DOMContentLoaded', toggleDepositField);

</script>


<!-- INVOICE -->

<div class="page">


<!-- COMPANY HEADER -->

<div class="company-header">

    <div class="brand">

        <img
            src="<?= e($logo) ?>"
            alt="<?= e($company_name) ?>"
            class="logo"
        >

        <div class="tagline">
            Your One Stop Solution for Security and IT
        </div>

        <div class="services">
            CCTV &nbsp; | &nbsp;
            ALARMS &nbsp; | &nbsp;
            ACCESS CONTROL &nbsp; | &nbsp;
            NETWORKING
        </div>

    </div>


    <div class="company-info">

        <strong><?= e($legal_name) ?></strong>

        <br>

        <span class="legal">
            Trading as <?= e($company_name) ?>
        </span>

        <?php if ($registration !== ''): ?>
        <br>
        Reg No: <?= e($registration) ?>
        <?php endif; ?>

        <?php if ($vat_number !== ''): ?>
        <br>
        VAT No: <?= e($vat_number) ?>
        <?php endif; ?>

        <?php if ($phone !== ''): ?>
        <br>
        Tel: <?= e($phone) ?>
        <?php endif; ?>

        <?php if ($email !== ''): ?>
        <br>
        Email: <?= e($email) ?>
        <?php endif; ?>

        <?php if ($website !== ''): ?>
        <br>
        Website: <?= e($website) ?>
        <?php endif; ?>

        <?php if ($address !== ''): ?>
        <br>
        <?= nl2br(e($address)) ?>
        <?php endif; ?>

    </div>

</div>


<!-- INVOICE BANNER -->

<div class="quote-banner">

    <div class="quote-title">
        INVOICE
    </div>

    <div class="quote-number">
        <?= e($invoice['invoice_number']) ?>
    </div>

</div>


<!-- CUSTOMER / INVOICE DETAILS -->

<div class="details">

    <div class="details-box">

        <h3>Bill To</h3>

        <strong>
            <?= e($invoice['company_name']) ?>
        </strong>

        <?php if (!empty($invoice['contact_name'])): ?>
        <br>
        <?= e($invoice['contact_name']) ?>
        <?php endif; ?>

        <?php if (!empty($invoice['address'])): ?>
        <br>
        <?= nl2br(e($invoice['address'])) ?>
        <?php endif; ?>

        <?php if (!empty($invoice['telephone'])): ?>
        <br>
        Tel: <?= e($invoice['telephone']) ?>
        <?php endif; ?>

        <?php if (!empty($invoice['email'])): ?>
        <br>
        Email: <?= e($invoice['email']) ?>
        <?php endif; ?>

        <?php if (!empty($invoice['vat_number'])): ?>
        <br>
        VAT No: <?= e($invoice['vat_number']) ?>
        <?php endif; ?>

    </div>


    <div class="details-box quote-meta">

        <h3>Invoice Details</h3>

        <div>
            <strong>Invoice Date:</strong>
            <?= e($invoice['invoice_date']) ?>
        </div>

        <div>
            <strong>Valid Until:</strong>
            <?= e($invoice['valid_until']) ?>
        </div>

        <div>
            <strong>Sales Person:</strong>
            <?= e($invoice['sales_person'] ?? '') ?>
        </div>

        <?php if (!empty($invoice['payment_terms'])): ?>
        <div>
            <strong>Payment Terms:</strong>
            <?= e($invoice['payment_terms']) ?>
        </div>
        <?php endif; ?>

        <div>
            <strong>Status:</strong>
            <span class="status-pill <?= e(str_replace(' ', '-', $invoice['status'])) ?>">
                <?= e($invoice['status']) ?>
            </span>
        </div>

    </div>

</div>


<!-- ITEMS -->

<table class="items-table">

<thead>

<tr>

<th style="width: 12%;">Code</th>

<th>Description</th>

<th style="width: 8%;" class="text-center">
    Qty
</th>

<th style="width: 9%;">
    Unit
</th>

<th style="width: 13%;" class="text-right">
    Unit Price
</th>

<th style="width: 10%;" class="text-right">
    Discount
</th>

<th style="width: 9%;" class="text-right">
    VAT
</th>

<th style="width: 14%;" class="text-right">
    Total
</th>

</tr>

</thead>


<tbody>

<?php foreach ($items as $item): ?>

<tr>

<td>
    <?= e($item['item_code']) ?>
</td>

<td>
    <?= e($item['description']) ?>
</td>

<td class="text-center">
    <?= number_format((float)$item['quantity'], 2) ?>
</td>

<td>
    <?= e($item['product_unit'] ?? 'Each') ?>
</td>

<td class="text-right">
    <?= money($item['unit_price']) ?>
</td>

<td class="text-right">
    <?= number_format((float)$item['discount'], 2) ?>%
</td>

<td class="text-right">
    <?= number_format((float)$item['vat_rate'], 2) ?>%
</td>

<td class="text-right">
    <?= money($item['line_total']) ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>


<!-- TOTALS -->

<div class="totals">

    <div class="total-row">
        <span>Subtotal</span>
        <strong><?= money($subtotal) ?></strong>
    </div>

    <div class="total-row">
        <span>VAT</span>
        <strong><?= money($vat_total) ?></strong>
    </div>

    <div class="total-row grand">
        <span>TOTAL</span>
        <strong><?= money($grand_total) ?></strong>
    </div>

    <?php if ($invoice['status'] === 'Deposit Paid' && $invoice['deposit_amount'] !== null): ?>

    <div class="total-row" style="margin-top:8px;">
        <span>Deposit Paid</span>
        <strong><?= money($invoice['deposit_amount']) ?></strong>
    </div>

    <div class="total-row">
        <span>Balance Due</span>
        <strong><?= money($grand_total - (float)$invoice['deposit_amount']) ?></strong>
    </div>

    <?php elseif ($invoice['status'] === 'Paid in Full'): ?>

    <div class="total-row" style="margin-top:8px;">
        <span>Balance Due</span>
        <strong><?= money(0) ?></strong>
    </div>

    <?php endif; ?>

</div>


<!-- NOTES -->

<?php if (!empty($invoice['notes'])): ?>

<div class="bottom-section">

    <div class="bottom-box">

        <h3>NOTES</h3>

        <p><?= e($invoice['notes']) ?></p>

    </div>

</div>

<?php endif; ?>


<!-- PAYMENT TERMS & REQUIREMENTS -->

<div class="bottom-section">

    <div class="bottom-box">

        <h3>PAYMENT TERMS &amp; REQUIREMENTS</h3>

        <?php if (!empty($invoice['payment_terms'])): ?>

        <p>
            <strong>Payment Terms:</strong>
            <?= e($invoice['payment_terms']) ?>
        </p>

        <?php endif; ?>

        <?php if (!empty($company['payment_notice'])): ?>

        <p>
            <strong>Payment Requirement:</strong>
            <?= e($company['payment_notice']) ?>
        </p>

        <?php endif; ?>

        <?php if (!empty($company['payment_instructions'])): ?>

        <p>
            <?= e($company['payment_instructions']) ?>
        </p>

        <?php endif; ?>

    </div>

</div>


<!-- TERMS & CONDITIONS -->

<?php if (!empty($company['terms_conditions'])): ?>

<div class="bottom-section">

    <div class="bottom-box">

        <h3>TERMS &amp; CONDITIONS</h3>

        <p><?= e($company['terms_conditions']) ?></p>

    </div>

</div>

<?php endif; ?>


<!-- FOOTER -->

<div class="footer">

    <div class="slogan">
        Entigration of Technology and Security into your life and business
    </div>

    <div class="services-footer">
        CCTV &nbsp; | &nbsp;
        ALARMS &nbsp; | &nbsp;
        ACCESS CONTROL &nbsp; | &nbsp;
        NETWORKING
    </div>

    <br>

    <strong><?= e($legal_name) ?></strong>
    &nbsp; | &nbsp;
    Trading as <?= e($company_name) ?>

    <?php if ($registration !== ''): ?>
    <br>
    Reg No: <?= e($registration) ?>
    <?php endif; ?>

    <br>

    Invoice <?= e($invoice['invoice_number']) ?>

</div>


</div>

</body>

</html>
