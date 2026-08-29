<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$stmt = $pdo->prepare("
    SELECT id, company_name, contact_name, telephone, email, vat_number
    FROM customers
    ORDER BY company_name ASC
");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Secutech Quotation System</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #222;
        }

        .header {
            background: #111;
            color: white;
            padding: 20px 30px;
            display: flex;
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

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .top-bar h2 {
            margin: 0;
        }

        .button {
            display: inline-block;
            padding: 11px 16px;
            border-radius: 6px;
            background: #222;
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .button:hover {
            opacity: 0.9;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        th {
            background: #f8f8f8;
        }

        tr:hover {
            background: #fafafa;
        }

        .empty {
            padding: 40px;
            text-align: center;
            color: #777;
        }

        .logout {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .action-cell {
            white-space: nowrap;
        }
    </style>
</head>
<body>

<header class="header">
    <a href="../dashboard.php" class="brand-link">
        <img src="../assets/secutech-logo.png" alt="SecuTech SA" class="header-logo">
        <span>SecuTech Quoting System</span>
    </a>
    <div style="display:flex; align-items:center; gap:20px;">
        <a class="logout" href="../dashboard.php">Home</a>
        <a class="logout" href="../logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <div class="top-bar">
        <h2>Customers</h2>
        <a class="button" href="add.php">+ Add Customer</a>
    </div>

    <div class="table-container">
        <?php if (!empty($customers)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Telephone</th>
                        <th>Email</th>
                        <th>VAT Number</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= e($customer['company_name'] ?? '') ?></td>
                            <td><?= e($customer['contact_name'] ?? '') ?></td>
                            <td><?= e($customer['telephone'] ?? '') ?></td>
                            <td><?= e($customer['email'] ?? '') ?></td>
                            <td><?= e($customer['vat_number'] ?? '') ?></td>
                            <td class="action-cell">
                                <a class="button" href="edit.php?id=<?= (int)($customer['id'] ?? 0) ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty">
                <h3>No customers yet</h3>
                <p>Add your first customer to begin creating quotations.</p>
                <a class="button" href="add.php">+ Add Customer</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>