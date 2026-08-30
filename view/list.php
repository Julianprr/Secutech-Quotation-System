<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$stmt = $pdo->query("
    SELECT
        q.id,
        q.quote_number,
        q.quote_date,
        q.valid_until,
        q.status,
        q.subtotal,
        q.vat_amount,
        q.total,
        c.company_name,
        c.contact_name
    FROM quotations q
    INNER JOIN customers c ON c.id = q.customer_id
    ORDER BY q.id DESC
");

$quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quotations - SecuTech Quotation System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f5f7;
            color: #222;
        }

        .header {
            background: #111;
            color: white;
            padding: 20px 35px;
            display: flex;
            flex-wrap: wrap;
            row-gap: 10px;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
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
            max-width: 1200px;
            margin: 45px auto;
            padding: 0 25px;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .top h2 {
            margin: 0;
        }

        .button {
            background: #222;
            color: white;
            padding: 11px 18px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #222;
            color: white;
            text-align: left;
            padding: 14px;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #ddd;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .view {
            background: #222;
            color: white;
            padding: 8px 14px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .status {
            font-weight: bold;
        }

        .empty {
            padding: 40px;
            text-align: center;
            color: #666;
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
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="top">
        <h2>Quotations</h2>

        <a class="button" href="../create/index.php">
            + New Quotation
        </a>
    </div>

    <div class="table-container">

        <?php if (empty($quotations)): ?>

            <div class="empty">
                <h3>No quotations yet</h3>
                <p>Create your first quotation to see it here.</p>
            </div>

        <?php else: ?>

            <table>

                <thead>
                    <tr>
                        <th>Quotation</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Date</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($quotations as $quote): ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars($quote['quote_number']) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($quote['company_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($quote['contact_name'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($quote['quote_date']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($quote['valid_until'] ?? '') ?>
                        </td>

                        <td class="status">
                            <?= htmlspecialchars($quote['status'] ?? 'Draft') ?>
                        </td>

                        <td>
                            R <?= number_format((float)$quote['total'], 2) ?>
                        </td>

                        <td>
                            <a class="view"
                               href="index.php?id=<?= (int)$quote['id'] ?>">
                                View
                            </a>
                            <a class="view"
                               style="background:#172d4d; margin-left:6px;"
                               href="../items/index.php?id=<?= (int)$quote['id'] ?>">
                                Edit
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>
</html>