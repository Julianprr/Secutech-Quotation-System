<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecuTech Quotation System</title>

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

        .logout {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .container {
            max-width: 1100px;
            margin: 45px auto;
            padding: 0 25px;
        }

        .welcome {
            margin-bottom: 30px;
        }

        .welcome h2 {
            margin-bottom: 8px;
        }

        .welcome p {
            color: #666;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            text-decoration: none;
            color: #222;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(0,0,0,0.12);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }

        .card p {
            color: #666;
            margin-bottom: 0;
            line-height: 1.5;
        }

        .icon {
            font-size: 30px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

<div class="header">

    <a href="dashboard.php" class="brand-link">
        <img src="assets/secutech-logo.png" alt="SecuTech SA" class="header-logo">
        <span>SecuTech Quoting System</span>
    </a>

    <a class="logout" href="login.php">
        Logout
    </a>

</div>

<div class="container">

    <div class="welcome">

        <h2>Dashboard</h2>

        <p>
            Welcome, <?= htmlspecialchars($user_name) ?>.
        </p>

    </div>

    <div class="cards">

        <!-- CUSTOMERS -->

        <a class="card" href="customers/index.php">

            <div class="icon">👥</div>

            <h3>Customers</h3>

            <p>
                Add, edit and manage your customers.
            </p>

        </a>


        <!-- PRODUCTS / ITEMS -->

        <a class="card" href="items/index.php">

            <div class="icon">📦</div>

            <h3>Products / Items</h3>

            <p>
                Manage products, item codes, prices and VAT rates.
            </p>

        </a>


        <!-- NEW QUOTATION -->

        <a class="card" href="create/index.php">

            <div class="icon">📝</div>

            <h3>New Quotation</h3>

            <p>
                Create a new quotation for a customer.
            </p>

        </a>


        <!-- QUOTATIONS -->

        <a class="card" href="view/list.php">

            <div class="icon">📄</div>

            <h3>Quotations</h3>

            <p>
                View and manage existing quotations.
            </p>

        </a>


        <!-- INVOICES -->

        <a class="card" href="invoices/list.php">

            <div class="icon">🧾</div>

            <h3>Invoices</h3>

            <p>
                View invoices and track payment status.
            </p>

        </a>

    </div>

</div>

</body>
</html>