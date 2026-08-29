<?php

require_once __DIR__ . '/../config/db.php';

$quote_id = (int)($_GET['id'] ?? 0);

if ($quote_id <= 0) {
    die('Invalid quotation ID.');
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
   GET QUOTE + CUSTOMER
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        q.*,
        c.company_name,
        c.contact_name,
        c.address,
        c.telephone,
        c.email,
        c.vat_number
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
   GET QUOTATION ITEMS
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        qi.*,
        p.unit AS product_unit
    FROM quotation_items qi
    LEFT JOIN products p ON p.id = qi.product_id
    WHERE qi.quotation_id = ?
    ORDER BY qi.sort_order, qi.id
");

$stmt->execute([$quote_id]);

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
 * Legal entity for quotations.
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

<title><?= e($quote['quote_number']) ?> - <?= e($legal_name) ?></title>

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


/* QUOTATION BANNER */

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

.totals-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-top: 14px;
}

.totals-row .totals {
    margin-top: 0;
}

.banking-inline {
    flex: 1;
    max-width: 340px;
    font-size: 10px;
    line-height: 1.55;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 10px 12px;
}

.banking-inline-title {
    font-weight: bold;
    color: #172d4d;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 2px solid #b5d900;
    padding-bottom: 4px;
    margin-bottom: 6px;
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

    .screen-actions {
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

    .screen-actions {
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

    .totals-row {
        flex-direction: column;
    }

    .banking-inline {
        max-width: 100%;
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
    ← Back to Quotations
</a>

<?php if (!empty($quote['converted_invoice_id'])): ?>
<a class="back" href="../invoices/index.php?id=<?= (int)$quote['converted_invoice_id'] ?>" style="background:#172d4d; color:#fff;">
    View Invoice
</a>
<?php else: ?>
<a class="back" href="../invoices/convert.php?quote_id=<?= (int)$quote['id'] ?>" style="background:#172d4d; color:#fff;">
    Convert to Invoice
</a>
<?php endif; ?>

<button class="print" onclick="window.print()">
    Print / Save PDF
</button>

<a class="back" href="../email/send.php?id=<?= (int)$quote['id'] ?>" style="background:#b5d900; color:#172d4d;">
    ✉ Email to Customer
</a>

</div>


<!-- EMAIL SUCCESS MESSAGE -->

<?php if (isset($_GET['emailed'])): ?>
<div style="width:210mm; margin:0 auto 15px auto; background:#dff5df; color:#216b21; padding:14px 20px; border-radius:6px; font-size:14px;">
    Quotation emailed successfully.
</div>
<?php endif; ?>


<!-- QUOTATION -->

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


<!-- QUOTATION BANNER -->

<div class="quote-banner">

    <div class="quote-title">
        QUOTATION
    </div>

    <div class="quote-number">
        <?= e($quote['quote_number']) ?>
    </div>

</div>


<!-- CUSTOMER / QUOTE DETAILS -->

<div class="details">

    <div class="details-box">

        <h3>Bill To</h3>

        <strong>
            <?= e($quote['company_name']) ?>
        </strong>

        <?php if (!empty($quote['contact_name'])): ?>
        <br>
        <?= e($quote['contact_name']) ?>
        <?php endif; ?>

        <?php if (!empty($quote['address'])): ?>
        <br>
        <?= nl2br(e($quote['address'])) ?>
        <?php endif; ?>

        <?php if (!empty($quote['telephone'])): ?>
        <br>
        Tel: <?= e($quote['telephone']) ?>
        <?php endif; ?>

        <?php if (!empty($quote['email'])): ?>
        <br>
        Email: <?= e($quote['email']) ?>
        <?php endif; ?>

        <?php if (!empty($quote['vat_number'])): ?>
        <br>
        VAT No: <?= e($quote['vat_number']) ?>
        <?php endif; ?>

    </div>


    <div class="details-box quote-meta">

        <h3>Quotation Details</h3>

        <div>
            <strong>Quote Date:</strong>
            <?= e($quote['quote_date']) ?>
        </div>

        <div>
            <strong>Valid Until:</strong>
            <?= e($quote['valid_until']) ?>
        </div>

        <div>
            <strong>Sales Person:</strong>
            <?= e($quote['sales_person'] ?? '') ?>
        </div>

        <?php if (!empty($quote['payment_terms'])): ?>
        <div>
            <strong>Payment Terms:</strong>
            <?= e($quote['payment_terms']) ?>
        </div>
        <?php endif; ?>

        <div>
            <strong>Status:</strong>
            <?= e($quote['status']) ?>
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


<!-- TOTALS + BANKING DETAILS -->

<div class="totals-row">

    <?php if (!empty($company['bank_name']) || !empty($company['account_number'])): ?>

    <div class="banking-inline">

        <div class="banking-inline-title">
            Banking Details
        </div>

        <?php if (!empty($company['account_holder'])): ?>
        <strong>Account Holder:</strong> <?= e($company['account_holder']) ?><br>
        <?php endif; ?>

        <?php if (!empty($company['bank_name'])): ?>
        <strong>Bank:</strong> <?= e($company['bank_name']) ?><br>
        <?php endif; ?>

        <?php if (!empty($company['account_number'])): ?>
        <strong>Account Number:</strong> <?= e($company['account_number']) ?><br>
        <?php endif; ?>

        <?php if (!empty($company['branch_code'])): ?>
        <strong>Branch Code:</strong> <?= e($company['branch_code']) ?><br>
        <?php endif; ?>

        <?php if (!empty($company['account_type'])): ?>
        <strong>Account Type:</strong> <?= e($company['account_type']) ?><br>
        <?php endif; ?>

        <?php if (!empty($company['swift_code'])): ?>
        <strong>SWIFT Code:</strong> <?= e($company['swift_code']) ?><br>
        <?php endif; ?>

        <strong>Reference:</strong> <?= e($quote['quote_number']) ?>

    </div>

    <?php endif; ?>

    <div class="totals" style="margin-top:0;">

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

    </div>

</div>


<!-- NOTES -->

<?php if (!empty($quote['notes'])): ?>

<div class="bottom-section">

    <div class="bottom-box">

        <h3>NOTES</h3>

        <p><?= e($quote['notes']) ?></p>

    </div>

</div>

<?php endif; ?>


<!-- PAYMENT TERMS & REQUIREMENTS -->

<div class="bottom-section">

    <div class="bottom-box">

        <h3>PAYMENT TERMS &amp; REQUIREMENTS</h3>

        <?php if (!empty($quote['payment_terms'])): ?>

        <p>
            <strong>Payment Terms:</strong>
            <?= e($quote['payment_terms']) ?>
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

    Quotation <?= e($quote['quote_number']) ?>

</div>


</div>

</body>

</html>