<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

/*
 * Get customer
 */
$stmt = $pdo->prepare("
    SELECT *
    FROM customers
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$customer = $stmt->fetch();

if (!$customer) {
    die('Customer not found.');
}

$error = '';

/*
 * Save changes
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $company_name = trim($_POST['company_name'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $telephone    = trim($_POST['telephone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $vat_number   = trim($_POST['vat_number'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    if ($company_name === '') {

        $error = 'Company name is required.';

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE customers
                SET
                    company_name = :company_name,
                    contact_name = :contact_name,
                    address = :address,
                    telephone = :telephone,
                    email = :email,
                    vat_number = :vat_number,
                    notes = :notes
                WHERE id = :id
            ");

            $stmt->execute([
                ':company_name' => $company_name,
                ':contact_name' => $contact_name,
                ':address'      => $address,
                ':telephone'    => $telephone,
                ':email'        => $email,
                ':vat_number'   => $vat_number,
                ':notes'        => $notes,
                ':id'           => $id
            ]);

            header('Location: index.php');
            exit;

        } catch (PDOException $e) {

            error_log('Customer update error: ' . $e->getMessage());

            $error = 'Unable to update customer.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Customer - Secutech Quotation System</title>

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

.logout {
    color: white;
    text-decoration: none;
}

.container {
    max-width: 900px;
    margin: 30px auto;
    padding: 0 20px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

h2 {
    margin-top: 0;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

input,
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 15px;
    font-family: Arial, sans-serif;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

input:focus,
textarea:focus {
    outline: none;
    border-color: #333;
}

.buttons {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.button {
    padding: 12px 18px;
    border-radius: 6px;
    border: none;
    background: #222;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 15px;
}

.button.secondary {
    background: #777;
}

.error {
    background: #ffe5e5;
    color: #a00000;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

@media (max-width: 700px) {

    .form-row {
        grid-template-columns: 1fr;
    }

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

        <a class="logout" href="../dashboard.php">
            Home
        </a>

        <a class="logout" href="../logout.php">
            Logout
        </a>

    </div>

</header>

<div class="container">

    <div class="card">

        <h2>Edit Customer</h2>

        <p>Update the customer's information below.</p>

        <?php if ($error): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="form-row">

                <div class="form-group">

                    <label>Company Name *</label>

                    <input
                        type="text"
                        name="company_name"
                        required
                        value="<?= htmlspecialchars(
                            $_POST['company_name']
                            ?? $customer['company_name']
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Contact Person</label>

                    <input
                        type="text"
                        name="contact_name"
                        value="<?= htmlspecialchars(
                            $_POST['contact_name']
                            ?? $customer['contact_name']
                        ) ?>"
                    >

                </div>

            </div>


            <div class="form-group">

                <label>Address</label>

                <textarea
                    name="address"
                ><?= htmlspecialchars(
                    $_POST['address']
                    ?? $customer['address']
                ) ?></textarea>

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label>Telephone</label>

                    <input
                        type="text"
                        name="telephone"
                        value="<?= htmlspecialchars(
                            $_POST['telephone']
                            ?? $customer['telephone']
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Email</label>

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $_POST['email']
                            ?? $customer['email']
                        ) ?>"
                    >

                </div>

            </div>


            <div class="form-group">

                <label>VAT Number</label>

                <input
                    type="text"
                    name="vat_number"
                    value="<?= htmlspecialchars(
                        $_POST['vat_number']
                        ?? $customer['vat_number']
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>Notes</label>

                <textarea
                    name="notes"
                ><?= htmlspecialchars(
                    $_POST['notes']
                    ?? $customer['notes']
                ) ?></textarea>

            </div>


            <div class="buttons">

                <button type="submit" class="button">
                    Save Changes
                </button>

                <a href="index.php" class="button secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>