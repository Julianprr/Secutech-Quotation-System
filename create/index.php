<?php

require_once __DIR__ . '/../config/db.php';

$message = '';
$error = '';

$selected_customer_id = (int)($_POST['customer_id'] ?? 0);


/* =================================================
   SAVE NEW CUSTOMER
================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {

    $new_company_name = trim($_POST['new_company_name'] ?? '');
    $new_contact_name = trim($_POST['new_contact_name'] ?? '');
    $new_address      = trim($_POST['new_address'] ?? '');
    $new_telephone    = trim($_POST['new_telephone'] ?? '');
    $new_email        = trim($_POST['new_email'] ?? '');
    $new_vat_number   = trim($_POST['new_vat_number'] ?? '');

    if ($new_company_name === '') {

        $error = 'Please enter the customer company name.';

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO customers
                (
                    company_name,
                    contact_name,
                    address,
                    telephone,
                    email,
                    vat_number
                )
                VALUES
                (
                    :company_name,
                    :contact_name,
                    :address,
                    :telephone,
                    :email,
                    :vat_number
                )
            ");

            $stmt->execute([
                ':company_name' => $new_company_name,
                ':contact_name' => $new_contact_name,
                ':address'      => $new_address,
                ':telephone'    => $new_telephone,
                ':email'        => $new_email,
                ':vat_number'   => $new_vat_number
            ]);

            $selected_customer_id = (int)$pdo->lastInsertId();

            $message = 'Customer saved successfully.';

        } catch (PDOException $e) {

            $error = 'Unable to save customer: ' . $e->getMessage();

        }
    }
}


/* =================================================
   GET CUSTOMERS
================================================= */

$stmt = $pdo->query("
    SELECT
        id,
        company_name,
        contact_name,
        address,
        telephone,
        email,
        vat_number
    FROM customers
    ORDER BY company_name ASC
");

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =================================================
   CREATE QUOTATION
================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quote'])) {

    $customer_id   = (int)($_POST['customer_id'] ?? 0);
    $quote_date    = $_POST['quote_date'] ?? date('Y-m-d');
    $valid_until   = $_POST['valid_until'] ?? date('Y-m-d', strtotime('+7 days'));
    $sales_person  = trim($_POST['sales_person'] ?? '');
    $payment_terms = trim($_POST['payment_terms'] ?? '');
    $notes         = trim($_POST['notes'] ?? '');

    if ($customer_id <= 0) {

        $error = 'Please select a customer.';

    } else {

        try {

            /* -----------------------------------------
               Generate next quotation number
            ----------------------------------------- */

            $year = date('Y');

            $stmt = $pdo->prepare("
                SELECT quote_number
                FROM quotations
                WHERE quote_number LIKE ?
                ORDER BY id DESC
                LIMIT 1
            ");

            $stmt->execute([
                "QT-$year-%"
            ]);

            $lastQuote = $stmt->fetchColumn();

            if ($lastQuote) {

                $lastNumber = (int)substr($lastQuote, -4);

                $nextNumber = $lastNumber + 1;

            } else {

                $nextNumber = 1;

            }

            $quote_number =
                'QT-' .
                $year .
                '-' .
                str_pad(
                    $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );


            /* -----------------------------------------
               Create quotation
            ----------------------------------------- */

            $stmt = $pdo->prepare("
                INSERT INTO quotations
                (
                    customer_id,
                    quote_number,
                    quote_date,
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
                    :customer_id,
                    :quote_number,
                    :quote_date,
                    :valid_until,
                    'Draft',
                    :sales_person,
                    :payment_terms,
                    0.00,
                    0.00,
                    0.00,
                    :notes
                )
            ");

            $stmt->execute([
                ':customer_id'   => $customer_id,
                ':quote_number'  => $quote_number,
                ':quote_date'    => $quote_date,
                ':valid_until'   => $valid_until,
                ':sales_person'  => $sales_person,
                ':payment_terms' => $payment_terms,
                ':notes'         => $notes
            ]);

            $quote_id = (int)$pdo->lastInsertId();


            /* -----------------------------------------
               IMPORTANT:
               Continue directly to Add Items
            ----------------------------------------- */

            header(
                'Location: ../items/?id=' . $quote_id
            );

            exit;


        } catch (PDOException $e) {

            $error =
                'Unable to create quote: ' .
                $e->getMessage();

        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Create Quote - SecuTech SA
</title>


<style>

* {
    box-sizing: border-box;
}


body {
    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f5f7;

    color: #222;
}


/* ================================================
   HEADER
================================================ */

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


.header a {

    color: white;

    text-decoration: none;

    font-size: 14px;
}


/* ================================================
   CONTAINER
================================================ */

.container {

    max-width: 1100px;

    margin: 40px auto;

    padding: 0 20px;
}


.card {

    background: white;

    padding: 30px;

    border-radius: 10px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.08);
}


h2 {

    margin-top: 0;

    color: #172d4d;
}


/* ================================================
   FORM
================================================ */

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}


.full {

    grid-column: 1 / -1;
}


label {

    display: block;

    font-weight: bold;

    margin-bottom: 7px;
}


input,
select,
textarea {

    width: 100%;

    padding: 12px;

    border:
        1px solid #ccc;

    border-radius: 6px;

    font-size: 15px;
}


textarea {

    min-height: 100px;

    resize: vertical;
}


/* ================================================
   CUSTOMER INFORMATION
================================================ */

.customer-info {

    margin-top: 5px;

    padding: 18px;

    background: #f7f7f7;

    border-radius: 8px;

    display: none;
}


.customer-info h3 {

    margin-top: 0;

    color: #172d4d;
}


.customer-info p {

    margin: 6px 0;
}


/* ================================================
   NEW CUSTOMER
================================================ */

.new-customer {

    margin-top: 5px;

    padding: 22px;

    background: #f1f5f9;

    border:
        1px solid #d5dce5;

    border-radius: 8px;

    display: none;
}


.new-customer h3 {

    margin-top: 0;

    color: #172d4d;
}


.new-customer-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;
}


.new-customer-grid .full {

    grid-column: 1 / -1;
}


/* ================================================
   BUTTONS
================================================ */

button {

    background: #172d4d;

    color: white;

    border: none;

    padding: 13px 22px;

    border-radius: 6px;

    cursor: pointer;

    font-size: 15px;
}


button:hover {

    background: #263f61;
}


.save-customer-button {

    margin-top: 18px;

    background: #172d4d;
}


.actions {

    margin-top: 25px;

    display: flex;

    gap: 10px;
}


.back {

    display: inline-block;

    padding: 13px 20px;

    background: #ddd;

    color: #222;

    text-decoration: none;

    border-radius: 6px;
}


/* ================================================
   MESSAGES
================================================ */

.success {

    background: #dff5df;

    color: #216b21;

    padding: 15px;

    border-radius: 6px;

    margin-bottom: 20px;
}


.error {

    background: #ffe1e1;

    color: #a00000;

    padding: 15px;

    border-radius: 6px;

    margin-bottom: 20px;
}


/* ================================================
   MOBILE
================================================ */

@media(max-width: 700px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .new-customer-grid {

        grid-template-columns: 1fr;
    }

    .full {

        grid-column: auto;
    }

}

</style>

</head>


<body>


<!-- =============================================
     HEADER
================================================ -->

<div class="header">

    <a href="../dashboard.php" class="brand-link">
        <img src="../assets/secutech-logo.png" alt="SecuTech SA" class="header-logo">
        <span>SecuTech Quoting System</span>
    </a>

    <div style="display:flex; align-items:center; gap:20px;">

        <a href="../dashboard.php">
            Home
        </a>

        <a href="../customers/index.php">
            Customers
        </a>

    </div>

</div>


<!-- =============================================
     MAIN
================================================ -->

<div class="container">

<div class="card">


<h2>
    Create New Quote
</h2>


<?php if ($message): ?>

<div class="success">

    <?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="error">

    <?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<form method="POST">


<div class="form-grid">


<!-- ============================================
     CUSTOMER
================================================ -->

<div class="full">

<label for="customer_id">
    Customer
</label>


<select
    name="customer_id"
    id="customer_id"
    required
>

<option value="">
    -- Select Customer --
</option>


<?php foreach ($customers as $customer): ?>

<option
    value="<?= (int)$customer['id'] ?>"

    data-company="<?= htmlspecialchars(
        $customer['company_name'] ?? ''
    ) ?>"

    data-contact="<?= htmlspecialchars(
        $customer['contact_name'] ?? ''
    ) ?>"

    data-address="<?= htmlspecialchars(
        $customer['address'] ?? ''
    ) ?>"

    data-telephone="<?= htmlspecialchars(
        $customer['telephone'] ?? ''
    ) ?>"

    data-email="<?= htmlspecialchars(
        $customer['email'] ?? ''
    ) ?>"

    data-vat="<?= htmlspecialchars(
        $customer['vat_number'] ?? ''
    ) ?>"

    <?= $selected_customer_id ===
        (int)$customer['id']
        ? 'selected'
        : ''
    ?>
>

    <?= htmlspecialchars(
        $customer['company_name']
    ) ?>

</option>

<?php endforeach; ?>


<option value="new">
    ➕ New Customer
</option>


</select>

</div>


<!-- ============================================
     NEW CUSTOMER FORM
================================================ -->

<div
    class="full new-customer"
    id="newCustomerForm"
>

<h3>
    Add New Customer
</h3>


<div class="new-customer-grid">


<div>

<label for="new_company_name">
    Company Name *
</label>

<input
    type="text"
    name="new_company_name"
    id="new_company_name"
    placeholder="Company name"
    value="<?= htmlspecialchars(
        $_POST['new_company_name'] ?? ''
    ) ?>"
>

</div>


<div>

<label for="new_contact_name">
    Contact Name
</label>

<input
    type="text"
    name="new_contact_name"
    id="new_contact_name"
    placeholder="Contact person"
    value="<?= htmlspecialchars(
        $_POST['new_contact_name'] ?? ''
    ) ?>"
>

</div>


<div class="full">

<label for="new_address">
    Address
</label>

<textarea
    name="new_address"
    id="new_address"
    placeholder="Customer address"
><?= htmlspecialchars(
    $_POST['new_address'] ?? ''
) ?></textarea>

</div>


<div>

<label for="new_telephone">
    Telephone
</label>

<input
    type="text"
    name="new_telephone"
    id="new_telephone"
    placeholder="Telephone number"
    value="<?= htmlspecialchars(
        $_POST['new_telephone'] ?? ''
    ) ?>"
>

</div>


<div>

<label for="new_email">
    Email
</label>

<input
    type="email"
    name="new_email"
    id="new_email"
    placeholder="Email address"
    value="<?= htmlspecialchars(
        $_POST['new_email'] ?? ''
    ) ?>"
>

</div>


<div>

<label for="new_vat_number">
    VAT Number
</label>

<input
    type="text"
    name="new_vat_number"
    id="new_vat_number"
    placeholder="VAT number"
    value="<?= htmlspecialchars(
        $_POST['new_vat_number'] ?? ''
    ) ?>"
>

</div>


</div>


<button
    type="submit"
    name="save_customer"
    value="1"
    class="save-customer-button"
>

    Save Customer

</button>


</div>


<!-- ============================================
     EXISTING CUSTOMER DETAILS
================================================ -->

<div
    class="full customer-info"
    id="customerInfo"
>

<h3>
    Customer Details
</h3>


<p>
    <strong>Company:</strong>
    <span id="infoCompany"></span>
</p>


<p>
    <strong>Contact:</strong>
    <span id="infoContact"></span>
</p>


<p>
    <strong>Address:</strong>
    <span id="infoAddress"></span>
</p>


<p>
    <strong>Telephone:</strong>
    <span id="infoTelephone"></span>
</p>


<p>
    <strong>Email:</strong>
    <span id="infoEmail"></span>
</p>


<p>
    <strong>VAT Number:</strong>
    <span id="infoVat"></span>
</p>


</div>


<!-- ============================================
     QUOTE DATE
================================================ -->

<div>

<label for="quote_date">
    Quote Date
</label>

<input
    type="date"
    name="quote_date"
    id="quote_date"

    value="<?= htmlspecialchars(
        $_POST['quote_date']
        ?? date('Y-m-d')
    ) ?>"

    required
>

</div>


<!-- ============================================
     VALID UNTIL
================================================ -->

<div>

<label for="valid_until">
    Valid Until
</label>

<input
    type="date"
    name="valid_until"
    id="valid_until"

    value="<?= htmlspecialchars(
        $_POST['valid_until']
        ?? date(
            'Y-m-d',
            strtotime('+7 days')
        )
    ) ?>"

    required
>

</div>


<!-- ============================================
     SALES PERSON
================================================ -->

<div>

<label for="sales_person">
    Sales Person
</label>

<input
    type="text"
    name="sales_person"
    id="sales_person"

    value="<?= htmlspecialchars(
        $_POST['sales_person'] ?? ''
    ) ?>"

    placeholder="Sales person"
>

</div>


<!-- ============================================
     PAYMENT TERMS
================================================ -->

<div>

<label for="payment_terms">
    Payment Terms
</label>

<input
    type="text"
    name="payment_terms"
    id="payment_terms"

    value="<?= htmlspecialchars(
        $_POST['payment_terms'] ?? ''
    ) ?>"

    placeholder="e.g. COD"
>

</div>


<!-- ============================================
     NOTES
================================================ -->

<div class="full">

<label for="notes">
    Notes
</label>

<textarea
    name="notes"
    id="notes"
    placeholder="Additional notes for this quotation..."
><?= htmlspecialchars(
    $_POST['notes'] ?? ''
) ?></textarea>

</div>


</div>


<!-- =============================================
     ACTIONS
================================================ -->

<div class="actions">


<button
    type="submit"
    name="create_quote"
    value="1"
>

    Create Quote

</button>


<a
    class="back"
    href="../customers/index.php"
>

    Cancel

</a>


</div>


</form>


</div>

</div>


<script>


/* ================================================
   CUSTOMER SELECT
================================================ */

const customerSelect =
    document.getElementById(
        'customer_id'
    );


const customerInfo =
    document.getElementById(
        'customerInfo'
    );


const newCustomerForm =
    document.getElementById(
        'newCustomerForm'
    );


function updateCustomerSelection() {

    const option =
        customerSelect.options[
            customerSelect.selectedIndex
        ];


    /* ---------------------------------------------
       NEW CUSTOMER
    --------------------------------------------- */

    if (
        option &&
        option.value === 'new'
    ) {

        newCustomerForm.style.display =
            'block';

        customerInfo.style.display =
            'none';

        return;
    }


    /* ---------------------------------------------
       NO CUSTOMER
    --------------------------------------------- */

    if (
        !option ||
        !option.value
    ) {

        newCustomerForm.style.display =
            'none';

        customerInfo.style.display =
            'none';

        return;
    }


    /* ---------------------------------------------
       EXISTING CUSTOMER
    --------------------------------------------- */

    newCustomerForm.style.display =
        'none';


    document.getElementById(
        'infoCompany'
    ).textContent =
        option.dataset.company || '';


    document.getElementById(
        'infoContact'
    ).textContent =
        option.dataset.contact || '';


    document.getElementById(
        'infoAddress'
    ).textContent =
        option.dataset.address || '';


    document.getElementById(
        'infoTelephone'
    ).textContent =
        option.dataset.telephone || '';


    document.getElementById(
        'infoEmail'
    ).textContent =
        option.dataset.email || '';


    document.getElementById(
        'infoVat'
    ).textContent =
        option.dataset.vat || '';


    customerInfo.style.display =
        'block';

}


customerSelect.addEventListener(
    'change',
    updateCustomerSelection
);


/*
 * Run when page loads
 */

updateCustomerSelection();


</script>


</body>

</html>