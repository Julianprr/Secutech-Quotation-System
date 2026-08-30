<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$stmt = $pdo->query("
    SELECT
        i.id,
        i.invoice_number,
        i.invoice_date,
        i.status,
        i.total,
        c.company_name,
        c.contact_name
    FROM invoices i
    INNER JOIN customers c ON c.id = i.customer_id
    ORDER BY i.id DESC
");

$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Invoices - SecuTech Quotation System</title>

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
            background: #172d4d;
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
            background: #172d4d;
            color: white;
            padding: 8px 14px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-Unpaid {
            background: #ffe1e1;
            color: #a00000;
        }

        .status-Deposit-Paid {
            background: #fff3cd;
            color: #8a6400;
        }

        .status-Paid-in-Full {
            background: #dff5df;
            color: #216b21;
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
        <a href="../view/list.php">Quotations</a>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="top">
        <h2>Invoices</h2>
    </div>

    <div class="table-container">

        <?php if (empty($invoices)): ?>

            <div class="empty">
                <h3>No invoices yet</h3>
                <p>Convert a quotation into an invoice to see it here.</p>
            </div>

        <?php else: ?>

            <table>

                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($invoices as $invoice): ?>

                    <?php $status_class = 'status-' . str_replace(' ', '-', $invoice['status']); ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars($invoice['invoice_number']) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($invoice['company_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($invoice['contact_name'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($invoice['invoice_date']) ?>
                        </td>

                        <td>
                            <span class="status-badge <?= htmlspecialchars($status_class) ?>">
                                <?= htmlspecialchars($invoice['status']) ?>
                            </span>
                        </td>

                        <td>
                            R <?= number_format((float)$invoice['total'], 2) ?>
                        </td>

                        <td>
                            <a class="view"
                               href="index.php?id=<?= (int)$invoice['id'] ?>">
                                View
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
