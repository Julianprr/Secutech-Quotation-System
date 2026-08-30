<?php

require_once __DIR__ . '/simple_pdf.php';

function generate_invoice_pdf(array $company, array $invoice, array $items, float $subtotal, float $vat_total, float $grand_total): string
{
    $pdf = new SimplePdf();

    $NAVY = '#172d4d';
    $GREEN = '#b5d900';
    $GREY = '#555555';
    $LIGHT_GREY = '#dddddd';
    $DARK_TEXT = '#222222';

    $legal_name = 'New Invest 147 Pty Ltd';
    $company_name = $company['company_name'] ?? 'SecuTech SA';
    $tagline = 'YOUR ONE STOP SOLUTION FOR SECURITY AND IT';

    $pdf->rect(0, 0, 210, 28, $NAVY);
    $pdf->setColor('#ffffff');
    $pdf->text(12, 13, $company_name, 18, true);
    $pdf->setColor($GREEN);
    $pdf->text(12, 20, $tagline, 7, true);
    $pdf->setColor('#ffffff');
    $pdf->textRight(198, 13, 'INVOICE', 15, true);
    $pdf->setColor($GREEN);
    $pdf->textRight(198, 20, $invoice['invoice_number'], 10, true);

    $y = 34;

    $info_parts = [$legal_name, 'Trading as ' . $company_name];
    if (!empty($company['registration_number'])) $info_parts[] = 'Reg: ' . $company['registration_number'];
    if (!empty($company['vat_number'])) $info_parts[] = 'VAT: ' . $company['vat_number'];
    if (!empty($company['phone'])) $info_parts[] = 'Tel: ' . $company['phone'];
    if (!empty($company['email'])) $info_parts[] = 'Email: ' . $company['email'];
    $info_line = implode('  |  ', $info_parts);

    $pdf->setColor($GREY);
    foreach ($pdf->wrapText($info_line, 186, 7.5) as $line) {
        $pdf->text(12, $y, $line, 7.5);
        $y += 3.8;
    }
    $y += 5;

    $box_top = $y;
    $box_h = 32;
    $col_w = 90;

    $pdf->setStrokeColor($LIGHT_GREY);
    $pdf->rect(12, $box_top, $col_w, $box_h, 'S');
    $pdf->rect(12 + $col_w + 6, $box_top, $col_w, $box_h, 'S');
    $pdf->rect(12, $box_top, $col_w, 6, $NAVY);
    $pdf->setColor('#ffffff');
    $pdf->text(14, $box_top + 4.3, 'BILL TO', 8, true);
    $pdf->rect(12 + $col_w + 6, $box_top, $col_w, 6, $NAVY);
    $pdf->text(14 + $col_w + 6, $box_top + 4.3, 'INVOICE DETAILS', 8, true);

    $pdf->setColor($DARK_TEXT);
    $by = $box_top + 11;
    $pdf->text(14, $by, $invoice['company_name'], 9.5, true);
    $by += 5;
    if (!empty($invoice['contact_name'])) { $pdf->text(14, $by, 'Contact: ' . $invoice['contact_name'], 8.5); $by += 4.5; }
    if (!empty($invoice['telephone'])) { $pdf->text(14, $by, 'Tel: ' . $invoice['telephone'], 8.5); $by += 4.5; }
    if (!empty($invoice['customer_email'])) { $pdf->text(14, $by, 'Email: ' . $invoice['customer_email'], 8.5); $by += 4.5; }

    $dy = $box_top + 11;
    $dx = 14 + $col_w + 6;
    $pdf->text($dx, $dy, 'Invoice Date: ' . $invoice['invoice_date'], 8.5); $dy += 4.5;
    $pdf->text($dx, $dy, 'Valid Until: ' . $invoice['valid_until'], 8.5); $dy += 4.5;
    if (!empty($invoice['sales_person'])) { $pdf->text($dx, $dy, 'Sales Person: ' . $invoice['sales_person'], 8.5); $dy += 4.5; }
    $pdf->text($dx, $dy, 'Status: ' . $invoice['status'], 8.5); $dy += 4.5;

    $y = $box_top + $box_h + 8;

    $col_x = ['code' => 12, 'desc' => 32, 'qty' => 118, 'unit_price' => 140, 'disc' => 165, 'vat' => 178, 'total' => 198];

    $pdf->rect(12, $y, 186, 7, $NAVY);
    $pdf->setColor('#ffffff');
    $pdf->text($col_x['code'] + 1, $y + 5, 'Code', 7.5, true);
    $pdf->text($col_x['desc'] + 1, $y + 5, 'Description', 7.5, true);
    $pdf->textRight($col_x['qty'], $y + 5, 'Qty', 7.5, true);
    $pdf->textRight($col_x['unit_price'], $y + 5, 'Unit Price', 7.5, true);
    $pdf->textRight($col_x['disc'], $y + 5, 'Disc%', 7.5, true);
    $pdf->textRight($col_x['vat'], $y + 5, 'VAT%', 7.5, true);
    $pdf->textRight($col_x['total'], $y + 5, 'Total', 7.5, true);
    $y += 7;

    $row_h = 6.5;
    foreach ($items as $idx => $item) {
        if ($idx % 2 === 1) {
            $pdf->setColor('#f7f9fb');
            $pdf->rect(12, $y, 186, $row_h, 'F');
        }
        $pdf->setColor($DARK_TEXT);
        $desc_lines = $pdf->wrapText((string) $item['description'], 82, 8);
        $pdf->text($col_x['code'] + 1, $y + 4.5, (string) $item['item_code'], 8);
        $pdf->text($col_x['desc'] + 1, $y + 4.5, $desc_lines[0] ?? '', 8);
        $pdf->textRight($col_x['qty'], $y + 4.5, number_format((float) $item['quantity'], 2), 8);
        $pdf->textRight($col_x['unit_price'], $y + 4.5, 'R ' . number_format((float) $item['unit_price'], 2), 8);
        $pdf->textRight($col_x['disc'], $y + 4.5, number_format((float) $item['discount'], 0) . '%', 8);
        $pdf->textRight($col_x['vat'], $y + 4.5, number_format((float) $item['vat_rate'], 0) . '%', 8);
        $pdf->textRight($col_x['total'], $y + 4.5, 'R ' . number_format((float) $item['line_total'], 2), 8);
        $y += $row_h;
    }

    $pdf->setStrokeColor($LIGHT_GREY);
    $pdf->line(12, $y, 198, $y, 0.3);
    $y += 8;

    $totals_x_left = 150;
    $totals_x_right = 198;

    $pdf->setColor($DARK_TEXT);
    $pdf->text($totals_x_left, $y, 'Subtotal', 9);
    $pdf->textRight($totals_x_right, $y, 'R ' . number_format($subtotal, 2), 9, true);
    $y += 5.5;
    $pdf->text($totals_x_left, $y, 'VAT', 9);
    $pdf->textRight($totals_x_right, $y, 'R ' . number_format($vat_total, 2), 9, true);
    $y += 6;

    $pdf->rect($totals_x_left - 2, $y - 4.5, 50, 8, $NAVY);
    $pdf->setColor('#ffffff');
    $pdf->text($totals_x_left, $y + 1, 'TOTAL', 10, true);
    $pdf->setColor($GREEN);
    $pdf->textRight($totals_x_right, $y + 1, 'R ' . number_format($grand_total, 2), 10, true);
    $y += 8;

    if ($invoice['status'] === 'Deposit Paid' && !empty($invoice['deposit_amount'])) {
        $deposit = (float) $invoice['deposit_amount'];
        $pdf->setColor($DARK_TEXT);
        $pdf->text($totals_x_left, $y, 'Deposit Paid', 9);
        $pdf->textRight($totals_x_right, $y, 'R ' . number_format($deposit, 2), 9, true);
        $y += 5.5;
        $pdf->text($totals_x_left, $y, 'Balance Due', 9, true);
        $pdf->textRight($totals_x_right, $y, 'R ' . number_format($grand_total - $deposit, 2), 9, true);
        $y += 6;
    } elseif ($invoice['status'] === 'Paid in Full') {
        $pdf->setColor($DARK_TEXT);
        $pdf->text($totals_x_left, $y, 'Balance Due', 9, true);
        $pdf->textRight($totals_x_right, $y, 'R 0.00', 9, true);
        $y += 6;
    }

    $y += 7;

    if (!empty($invoice['notes'])) {
        $pdf->setColor($NAVY);
        $pdf->text(12, $y, 'NOTES', 8.5, true);
        $y += 4.5;
        $pdf->setColor('#333333');
        foreach ($pdf->wrapText((string) $invoice['notes'], 186, 8) as $line) {
            $pdf->text(12, $y, $line, 8);
            $y += 4;
        }
        $y += 4;
    }

    if (!empty($company['bank_name']) || !empty($company['account_number'])) {
        $pdf->setColor($NAVY);
        $pdf->text(12, $y, 'BANKING DETAILS', 8.5, true);
        $y += 4.5;
        $pdf->setColor('#333333');
        $bank_lines = [];
        if (!empty($company['account_holder'])) $bank_lines[] = 'Account Holder: ' . $company['account_holder'];
        if (!empty($company['bank_name'])) $bank_lines[] = 'Bank: ' . $company['bank_name'];
        if (!empty($company['account_number'])) $bank_lines[] = 'Account Number: ' . $company['account_number'];
        if (!empty($company['branch_code'])) $bank_lines[] = 'Branch Code: ' . $company['branch_code'];
        if (!empty($company['account_type'])) $bank_lines[] = 'Account Type: ' . $company['account_type'];
        if (!empty($company['swift_code'])) $bank_lines[] = 'SWIFT Code: ' . $company['swift_code'];
        $bank_lines[] = 'Reference: ' . $invoice['invoice_number'];
        foreach ($bank_lines as $line) {
            $pdf->text(12, $y, $line, 8);
            $y += 4;
        }
        $y += 4;
    }

    $footer_y = 280;
    $pdf->setStrokeColor($NAVY);
    $pdf->line(12, $footer_y, 198, $footer_y, 0.5);
    $footer_y += 6;
    $pdf->setColor($NAVY);
    $pdf->textCentered(105, $footer_y, 'Integration of Technology and Security into your life and business', 9, true);
    $footer_y += 5;
    $pdf->setColor('#666666');
    $pdf->textCentered(105, $footer_y, $legal_name . '  |  Trading as ' . $company_name, 8);

    return $pdf->output();
}
