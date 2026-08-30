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


/* -------------------------------------------------
   HANDLE DELETE
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_expense'])) {

    $delete_id = (int)($_POST['expense_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT receipt_image_path FROM expenses WHERE id = ?");
    $stmt->execute([$delete_id]);
    $image_path = $stmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->execute([$delete_id]);

    if ($image_path) {
        $full_path = dirname(__DIR__, 3) . '/private_uploads/receipts/' . basename($image_path);
        if (file_exists($full_path)) {
            @unlink($full_path);
        }
    }

    header('Location: index.php?deleted=1');
    exit;

}


/* -------------------------------------------------
   FILTERS
------------------------------------------------- */

$filter_supplier = trim($_GET['supplier'] ?? '');
$filter_month = trim($_GET['month'] ?? ''); // YYYY-MM

$where = [];
$params = [];

if ($filter_supplier !== '') {
    $where[] = 'supplier_name = ?';
    $params[] = $filter_supplier;
}

if ($filter_month !== '' && preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $where[] = "DATE_FORMAT(expense_date, '%Y-%m') = ?";
    $params[] = $filter_month;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';


/* -------------------------------------------------
   GET EXPENSES
------------------------------------------------- */

$stmt = $pdo->prepare("SELECT * FROM expenses $where_sql ORDER BY expense_date DESC, id DESC");
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filtered_total = array_sum(array_column($expenses, 'total'));


/* -------------------------------------------------
   SUMMARY CARDS (unfiltered)
------------------------------------------------- */

$stmt = $pdo->query("
    SELECT COALESCE(SUM(total), 0) FROM expenses
    WHERE DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
");
$this_month_total = (float)$stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COALESCE(SUM(total), 0) FROM expenses
    WHERE YEAR(expense_date) = YEAR(CURDATE())
");
$this_year_total = (float)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM expenses");
$all_time_total = (float)$stmt->fetchColumn();


/* -------------------------------------------------
   SUPPLIER LIST FOR FILTER DROPDOWN
------------------------------------------------- */

$stmt = $pdo->query("SELECT DISTINCT supplier_name FROM expenses ORDER BY supplier_name");
$suppliers = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - SecuTech Quotation System</title>

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
            max-width: 1100px;
            margin: 45px auto;
            padding: 0 25px;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .add-btn {
            background: #172d4d;
            color: white;
            padding: 11px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
        }

        .success {
            background: #dff5df;
            color: #216b21;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .summary-card .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .summary-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #172d4d;
            margin-top: 6px;
        }

        .filters {
            background: white;
            border-radius: 8px;
            padding: 18px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filters label {
            display: block;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .filters select,
        .filters input {
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        .filters button {
            background: #172d4d;
            color: white;
            border: none;
            padding: 9px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .filters a {
            color: #666;
            font-size: 13px;
            text-decoration: none;
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
            padding: 12px 14px;
            font-size: 13px;
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .view-link {
            color: #172d4d;
            text-decoration: none;
            font-size: 13px;
            margin-right: 12px;
        }

        .delete-link {
            color: #a00000;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            padding: 0;
            text-decoration: underline;
        }

        .empty {
            padding: 40px;
            text-align: center;
            color: #666;
        }

        .filtered-total {
            padding: 14px 20px;
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #172d4d;
            text-align: right;
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
    </div>
</div>

<div class="container">

    <div class="top">
        <h2 style="margin:0;">Expenses</h2>
        <a class="add-btn" href="add.php">+ Add Expense</a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="success">Expense saved.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="success">Expense deleted.</div>
    <?php endif; ?>

    <div class="summary-cards">
        <div class="summary-card">
            <div class="label">This Month</div>
            <div class="value">R <?= number_format($this_month_total, 2) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">This Year</div>
            <div class="value">R <?= number_format($this_year_total, 2) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">All Time</div>
            <div class="value">R <?= number_format($all_time_total, 2) ?></div>
        </div>
    </div>

    <form method="GET" class="filters">

        <div>
            <label>Supplier</label>
            <select name="supplier">
                <option value="">All Suppliers</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filter_supplier === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Month</label>
            <input type="month" name="month" value="<?= e($filter_month) ?>">
        </div>

        <button type="submit">Filter</button>

        <?php if ($filter_supplier !== '' || $filter_month !== ''): ?>
            <a href="index.php">Clear filters</a>
        <?php endif; ?>

    </form>

    <div class="table-container">

        <?php if (empty($expenses)): ?>

            <div class="empty">
                <h3>No expenses logged yet</h3>
                <p>Add one by photographing a supplier receipt.</p>
            </div>

        <?php else: ?>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Invoice #</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($expenses as $expense): ?>

                    <tr>
                        <td><?= e($expense['expense_date']) ?></td>
                        <td><?= e($expense['supplier_name']) ?></td>
                        <td><?= e($expense['invoice_number'] ?? '') ?></td>
                        <td>R <?= number_format((float)$expense['total'], 2) ?></td>
                        <td>

                            <?php if (!empty($expense['receipt_image_path'])): ?>
                                <a class="view-link" href="receipt_image.php?id=<?= (int)$expense['id'] ?>" target="_blank">
                                    View Receipt
                                </a>
                            <?php endif; ?>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this expense?');">
                                <input type="hidden" name="expense_id" value="<?= (int)$expense['id'] ?>">
                                <button type="submit" name="delete_expense" class="delete-link">Delete</button>
                            </form>

                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

            <div class="filtered-total">
                <?= (!empty($where)) ? 'Filtered Total' : 'Total' ?>: R <?= number_format($filtered_total, 2) ?>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
