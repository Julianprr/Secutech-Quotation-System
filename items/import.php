<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$step = 'upload';
$error = '';
$preview_headers = [];
$preview_rows = [];
$result = null;


/* -------------------------------------------------
   STEP 1: FILE UPLOADED - PARSE AND SHOW PREVIEW
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pricelist']) && $_FILES['pricelist']['error'] === UPLOAD_ERR_OK) {

    $tmp_path = $_FILES['pricelist']['tmp_name'];
    $rows = [];

    if (($handle = fopen($tmp_path, 'r')) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
    }

    if (count($rows) < 2) {

        $error = 'That file doesn\'t look like a CSV with a header row plus at least one data row.';

    } else {

        $headers = array_shift($rows);

        /* Cap how much we hold in the session - large files still import
           fine, we just don't preview every single row on screen. */
        $_SESSION['import_headers'] = $headers;
        $_SESSION['import_rows'] = $rows;

        $step = 'map';
        $preview_headers = $headers;
        $preview_rows = array_slice($rows, 0, 5);

    }

}


/* -------------------------------------------------
   STEP 2: MAPPING SUBMITTED - RUN THE IMPORT
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_import'])) {

    $headers = $_SESSION['import_headers'] ?? [];
    $rows = $_SESSION['import_rows'] ?? [];

    if (empty($headers) || empty($rows)) {

        $error = 'Your import session expired. Please upload the file again.';
        $step = 'upload';

    } else {

        $map = [
            'item_code'     => $_POST['col_item_code'] ?? '',
            'description'   => $_POST['col_description'] ?? '',
            'category'      => $_POST['col_category'] ?? '',
            'unit'          => $_POST['col_unit'] ?? '',
            'selling_price' => $_POST['col_selling_price'] ?? '',
            'vat_rate'      => $_POST['col_vat_rate'] ?? '',
        ];

        $default_vat_rate = is_numeric($_POST['default_vat_rate'] ?? '') ? (float)$_POST['default_vat_rate'] : 15;
        $default_unit = trim($_POST['default_unit'] ?? 'Each') ?: 'Each';

        $col_index = array_flip($headers);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {

            $get = function (string $field) use ($map, $col_index, $row) {
                $col_name = $map[$field];
                if ($col_name === '' || !isset($col_index[$col_name])) {
                    return null;
                }
                $idx = $col_index[$col_name];
                return $row[$idx] ?? null;
            };

            $description = trim((string)($get('description') ?? ''));

            if ($description === '') {
                $skipped++;
                continue;
            }

            $item_code = trim((string)($get('item_code') ?? ''));
            $category = trim((string)($get('category') ?? ''));
            $unit = trim((string)($get('unit') ?? '')) ?: $default_unit;

            $price_raw = (string)($get('selling_price') ?? '0');
            $price_clean = preg_replace('/[^0-9.\-]/', '', $price_raw);
            $selling_price = $price_clean !== '' ? (float)$price_clean : 0.0;

            $vat_raw = $get('vat_rate');
            $vat_rate = ($vat_raw !== null && is_numeric($vat_raw)) ? (float)$vat_raw : $default_vat_rate;

            /* Match existing product by item_code first, then by exact description */

            $existing = null;

            if ($item_code !== '') {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE item_code = ? LIMIT 1");
                $stmt->execute([$item_code]);
                $existing = $stmt->fetchColumn();
            }

            if (!$existing) {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE description = ? LIMIT 1");
                $stmt->execute([$description]);
                $existing = $stmt->fetchColumn();
            }

            if ($existing) {

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET item_code = :item_code, description = :description, category = :category,
                        unit = :unit, selling_price = :selling_price, vat_rate = :vat_rate
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':item_code'     => $item_code,
                    ':description'   => $description,
                    ':category'      => $category,
                    ':unit'          => $unit,
                    ':selling_price' => $selling_price,
                    ':vat_rate'      => $vat_rate,
                    ':id'            => $existing,
                ]);
                $updated++;

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO products (item_code, description, category, unit, selling_price, vat_rate, active)
                    VALUES (:item_code, :description, :category, :unit, :selling_price, :vat_rate, 1)
                ");
                $stmt->execute([
                    ':item_code'     => $item_code,
                    ':description'   => $description,
                    ':category'      => $category,
                    ':unit'          => $unit,
                    ':selling_price' => $selling_price,
                    ':vat_rate'      => $vat_rate,
                ]);
                $created++;

            }

        }

        unset($_SESSION['import_headers'], $_SESSION['import_rows']);

        $step = 'done';
        $result = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'total' => count($rows)];

    }

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Price List - SecuTech Quotation System</title>

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
            max-width: 900px;
            margin: 45px auto;
            padding: 0 25px;
        }

        h2 {
            margin-top: 0;
        }

        .box {
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

        .success {
            background: #dff5df;
            color: #216b21;
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 16px;
            font-weight: bold;
            font-size: 14px;
        }

        input[type=file],
        input[type=text],
        input[type=number],
        select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }

        th {
            background: #172d4d;
            color: white;
        }

        .map-row {
            display: grid;
            grid-template-columns: 160px 1fr;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }

        .map-row label {
            margin: 0;
        }
    </style>
</head>

<body>

<div class="header">
    <a href="../dashboard.php" class="brand-link">
        <img src="../assets/secutech-logo.png" alt="SecuTech SA" class="header-logo">
        <span>SecuTech Quoting System</span>
    </a>
    <div class="header-links">
        <a href="../dashboard.php">Home</a>
        <a href="index.php">Items</a>
    </div>
</div>

<div class="container">

    <div class="box">

        <h2>Import Supplier Price List</h2>

        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <?php if ($step === 'upload'): ?>

            <div class="info">
                Upload a CSV file with a header row. If your supplier only gives you
                an Excel file, open it and use "Save As" or "Export" to save a CSV
                copy first - most spreadsheet programs support this.
                On the next screen you'll tell us which column is which, so any
                supplier's format will work.
            </div>

            <form method="POST" enctype="multipart/form-data">
                <label>CSV File</label>
                <input type="file" name="pricelist" accept=".csv" required>
                <button type="submit" class="btn">Upload &amp; Preview</button>
            </form>

        <?php elseif ($step === 'map'): ?>

            <p>Found <?= count($_SESSION['import_rows'] ?? []) ?> rows. Here's a preview of the first few:</p>

            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($preview_headers as $h): ?>
                                <th><?= htmlspecialchars($h) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= htmlspecialchars((string)$cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" style="margin-top:25px;">

                <input type="hidden" name="run_import" value="1">

                <h3 style="margin-bottom:0;">Match each column</h3>
                <p style="color:#666; font-size:13px; margin-top:4px;">
                    Items are matched to your existing catalogue by Item Code first,
                    then by exact Description. Unmatched items are added as new.
                </p>

                <?php
                $fields = [
                    'col_item_code'     => 'Item Code (optional but recommended)',
                    'col_description'   => 'Description (required)',
                    'col_category'      => 'Category (optional)',
                    'col_unit'          => 'Unit (optional, e.g. Each/Meter)',
                    'col_selling_price' => 'Selling Price (required)',
                    'col_vat_rate'      => 'VAT Rate % (optional)',
                ];
                ?>

                <?php foreach ($fields as $name => $label): ?>
                <div class="map-row">
                    <label><?= htmlspecialchars($label) ?></label>
                    <select name="<?= $name ?>">
                        <option value="">-- Not in this file --</option>
                        <?php foreach ($preview_headers as $h): ?>
                            <option value="<?= htmlspecialchars($h) ?>"><?= htmlspecialchars($h) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>

                <div class="map-row">
                    <label>Default Unit (if not in file)</label>
                    <input type="text" name="default_unit" value="Each">
                </div>

                <div class="map-row">
                    <label>Default VAT % (if not in file)</label>
                    <input type="number" name="default_vat_rate" value="15" step="0.01">
                </div>

                <button type="submit" class="btn">Run Import</button>
                <a href="import.php" class="link-back">Cancel</a>

            </form>

        <?php elseif ($step === 'done' && $result): ?>

            <div class="success">
                Import complete: <?= (int)$result['created'] ?> new items added,
                <?= (int)$result['updated'] ?> existing items updated<?php if ($result['skipped'] > 0): ?>,
                <?= (int)$result['skipped'] ?> rows skipped (missing description)<?php endif; ?>.
            </div>

            <a href="index.php" class="btn" style="display:inline-block; text-decoration:none;">
                View Catalogue
            </a>
            <a href="import.php" class="link-back">
                Import Another File
            </a>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
