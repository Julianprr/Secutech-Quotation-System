<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$quote_id = (int)($_GET['quote_id'] ?? $_POST['quote_id'] ?? 0);

if ($quote_id <= 0) {
    die('Invalid quotation ID.');
}


/* -------------------------------------------------
   GET QUOTATION
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT *
    FROM quotations
    WHERE id = ?
");

$stmt->execute([$quote_id]);

$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    die('Quotation not found.');
}


/* -------------------------------------------------
   ALREADY CONVERTED? Just go straight to it.
------------------------------------------------- */

if (!empty($quote['converted_invoice_id'])) {

    header('Location: index.php?id=' . (int)$quote['converted_invoice_id']);
    exit;

}


/* -------------------------------------------------
   GENERATE NEXT INVOICE NUMBER

   Format: JPI-YYYYMMDD-N
   e.g. JPI-20261229-1, JPI-20261229-2, ...

   N resets back to 1 at the start of each new day
   and increments for every invoice created that day.
------------------------------------------------- */

$today = date('Ymd');

$number_prefix = "JPI-$today-";

$stmt = $pdo->prepare("
    SELECT invoice_number
    FROM invoices
    WHERE invoice_number LIKE ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([
    $number_prefix . '%'
]);

$lastInvoice = $stmt->fetchColumn();

if ($lastInvoice) {

    $lastSegment = substr(
        $lastInvoice,
        strlen($number_prefix)
    );

    $nextNumber = (int)$lastSegment + 1;

} else {

    $nextNumber = 1;

}

$invoice_number = $number_prefix . $nextNumber;


/* -------------------------------------------------
   CREATE INVOICE (copied from the quotation)
------------------------------------------------- */

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO invoices
        (
            quotation_id,
            customer_id,
            invoice_number,
            invoice_date,
            valid_until,
            status,
            sales_person,
            payment_terms,
            subtotal,
            vat_amount,
            total,
            notes
        )
        VALUES
        (
            :quotation_id,
            :customer_id,
            :invoice_number,
            :invoice_date,
            :valid_until,
            'Unpaid',
            :sales_person,
            :payment_terms,
            :subtotal,
            :vat_amount,
            :total,
            :notes
        )
    ");

    $stmt->execute([
        ':quotation_id'  => $quote_id,
        ':customer_id'   => $quote['customer_id'],
        ':invoice_number' => $invoice_number,
        ':invoice_date'  => date('Y-m-d'),
        ':valid_until'   => $quote['valid_until'],
        ':sales_person'  => $quote['sales_person'],
        ':payment_terms' => $quote['payment_terms'],
        ':subtotal'      => $quote['subtotal'],
        ':vat_amount'    => $quote['vat_amount'],
        ':total'         => $quote['total'],
        ':notes'         => $quote['notes'],
    ]);

    $invoice_id = (int)$pdo->lastInsertId();


    /* -----------------------------------------
       COPY LINE ITEMS
    ----------------------------------------- */

    $stmt = $pdo->prepare("
        SELECT *
        FROM quotation_items
        WHERE quotation_id = ?
        ORDER BY sort_order, id
    ");

    $stmt->execute([$quote_id]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insertItem = $pdo->prepare("
        INSERT INTO invoice_items
        (
            invoice_id,
            product_id,
            section_id,
            item_code,
            description,
            quantity,
            unit_price,
            discount,
            vat_rate,
            line_total,
            sort_order
        )
        VALUES
        (
            :invoice_id,
            :product_id,
            :section_id,
            :item_code,
            :description,
            :quantity,
            :unit_price,
            :discount,
            :vat_rate,
            :line_total,
            :sort_order
        )
    ");

    foreach ($items as $item) {

        $insertItem->execute([
            ':invoice_id'  => $invoice_id,
            ':product_id'  => $item['product_id'],
            ':section_id'  => $item['section_id'],
            ':item_code'   => $item['item_code'],
            ':description' => $item['description'],
            ':quantity'    => $item['quantity'],
            ':unit_price'  => $item['unit_price'],
            ':discount'    => $item['discount'],
            ':vat_rate'    => $item['vat_rate'],
            ':line_total'  => $item['line_total'],
            ':sort_order'  => $item['sort_order'],
        ]);

    }


    /* -----------------------------------------
       LINK THE QUOTATION TO THE NEW INVOICE
    ----------------------------------------- */

    $stmt = $pdo->prepare("
        UPDATE quotations
        SET converted_invoice_id = ?
        WHERE id = ?
    ");

    $stmt->execute([$invoice_id, $quote_id]);

    $pdo->commit();

    header('Location: index.php?id=' . $invoice_id);
    exit;

} catch (PDOException $e) {

    $pdo->rollBack();

    error_log('Invoice conversion error: ' . $e->getMessage());

    die(
        'Unable to convert this quotation to an invoice. ' .
        'Please make sure the invoices database tables have ' .
        'been created (see database/invoices_migration.sql).'
    );

}
