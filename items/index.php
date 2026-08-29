<?php

require_once __DIR__ . '/../config/db.php';


/* =================================================
   ITEM CATALOGUE
   Shown when index.php is opened without a quote ID
================================================= */

$quote_id = (int)($_GET['id'] ?? $_POST['quote_id'] ?? 0);


/*
 * If there is NO quotation ID, this page becomes
 * the permanent SecuTech item catalogue.
 */
if ($quote_id <= 0) {

    $catalogue_message = '';
    $catalogue_error = '';


    /* ---------------------------------------------
       DELETE ITEM FROM CATALOGUE
    --------------------------------------------- */

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['catalogue_delete'])
    ) {

        $product_id = (int)($_POST['product_id'] ?? 0);

        if ($product_id > 0) {

            try {

                /*
                 * Do not physically delete products.
                 * Mark them inactive instead.
                 */
                $stmt = $pdo->prepare("
                    UPDATE products
                    SET active = 0
                    WHERE id = ?
                ");

                $stmt->execute([$product_id]);

                $catalogue_message =
                    'Item removed from the active catalogue.';

            } catch (PDOException $e) {

                $catalogue_error =
                    'Unable to remove item: ' .
                    $e->getMessage();
            }
        }
    }


    /* ---------------------------------------------
       ADD NEW ITEM
    --------------------------------------------- */

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['catalogue_add'])
    ) {

        $item_code = trim(
            $_POST['catalogue_item_code'] ?? ''
        );

        $description = trim(
            $_POST['catalogue_description'] ?? ''
        );

        $category = trim(
            $_POST['catalogue_category'] ?? ''
        );

        $item_type = trim(
            $_POST['catalogue_item_type'] ?? 'Product'
        );

        $unit = trim(
            $_POST['catalogue_unit'] ?? 'Each'
        );

        $selling_price = (float)(
            $_POST['catalogue_selling_price'] ?? 0
        );

        $vat_rate = (float)(
            $_POST['catalogue_vat_rate'] ?? 15
        );


        if ($item_code === '') {

            $catalogue_error =
                'Please enter an item code.';

        } elseif ($description === '') {

            $catalogue_error =
                'Please enter a description.';

        } elseif ($selling_price < 0) {

            $catalogue_error =
                'Selling price cannot be negative.';

        } elseif (
            $vat_rate !== 0.0
            && $vat_rate !== 15.0
        ) {

            $catalogue_error =
                'VAT rate must be 15% or 0%.';

        } else {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO products
                    (
                        item_code,
                        description,
                        category,
                        item_type,
                        unit,
                        selling_price,
                        vat_rate,
                        active
                    )
                    VALUES
                    (
                        :item_code,
                        :description,
                        :category,
                        :item_type,
                        :unit,
                        :selling_price,
                        :vat_rate,
                        1
                    )
                ");

                $stmt->execute([
                    ':item_code' =>
                        $item_code,

                    ':description' =>
                        $description,

                    ':category' =>
                        $category,

                    ':item_type' =>
                        $item_type,

                    ':unit' =>
                        $unit,

                    ':selling_price' =>
                        $selling_price,

                    ':vat_rate' =>
                        $vat_rate
                ]);

                $catalogue_message =
                    'Item added successfully.';

            } catch (PDOException $e) {

                if (
                    strpos(
                        strtolower($e->getMessage()),
                        'duplicate'
                    ) !== false
                ) {

                    $catalogue_error =
                        'That item code already exists.';

                } else {

                    $catalogue_error =
                        'Unable to add item: ' .
                        $e->getMessage();
                }
            }
        }
    }


    /* ---------------------------------------------
       EDIT EXISTING ITEM
    --------------------------------------------- */

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['catalogue_edit'])
    ) {

        $product_id = (int)(
            $_POST['product_id'] ?? 0
        );

        $item_code = trim(
            $_POST['catalogue_item_code'] ?? ''
        );

        $description = trim(
            $_POST['catalogue_description'] ?? ''
        );

        $category = trim(
            $_POST['catalogue_category'] ?? ''
        );

        $item_type = trim(
            $_POST['catalogue_item_type'] ?? 'Product'
        );

        $unit = trim(
            $_POST['catalogue_unit'] ?? 'Each'
        );

        $selling_price = (float)(
            $_POST['catalogue_selling_price'] ?? 0
        );

        $vat_rate = (float)(
            $_POST['catalogue_vat_rate'] ?? 15
        );


        if ($product_id <= 0) {

            $catalogue_error =
                'Invalid item.';

        } elseif ($item_code === '') {

            $catalogue_error =
                'Please enter an item code.';

        } elseif ($description === '') {

            $catalogue_error =
                'Please enter a description.';

        } elseif ($selling_price < 0) {

            $catalogue_error =
                'Selling price cannot be negative.';

        } elseif (
            $vat_rate !== 0.0
            && $vat_rate !== 15.0
        ) {

            $catalogue_error =
                'VAT rate must be 15% or 0%.';

        } else {

            try {

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET
                        item_code = :item_code,
                        description = :description,
                        category = :category,
                        item_type = :item_type,
                        unit = :unit,
                        selling_price = :selling_price,
                        vat_rate = :vat_rate,
                        active = 1
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':item_code' =>
                        $item_code,

                    ':description' =>
                        $description,

                    ':category' =>
                        $category,

                    ':item_type' =>
                        $item_type,

                    ':unit' =>
                        $unit,

                    ':selling_price' =>
                        $selling_price,

                    ':vat_rate' =>
                        $vat_rate,

                    ':id' =>
                        $product_id
                ]);

                $catalogue_message =
                    'Item updated successfully.';

            } catch (PDOException $e) {

                $catalogue_error =
                    'Unable to update item: ' .
                    $e->getMessage();
            }
        }
    }


    /* ---------------------------------------------
       GET ACTIVE PRODUCTS
    --------------------------------------------- */

    $stmt = $pdo->query("
        SELECT
            id,
            item_code,
            description,
            category,
            item_type,
            unit,
            selling_price,
            vat_rate,
            active
        FROM products
        WHERE active = 1
        ORDER BY category, description
    ");

    $catalogue_products =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* ---------------------------------------------
       ITEM CATALOGUE PAGE
    --------------------------------------------- */

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
        Item Catalogue - SecuTech SA
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


    /* =========================================
       HEADER
    ========================================== */

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

        width: 55px;

        height: auto;

        display: block;
    }


    .header-links {

        display: flex;

        align-items: center;

        gap: 20px;
    }


    .header-links a {

        color: white;

        text-decoration: none;

        font-size: 14px;
    }


    .header-links a:hover {

        text-decoration: underline;
    }


    /* =========================================
       PAGE
    ========================================== */

    .container {

        max-width: 1200px;

        margin: 35px auto;

        padding: 0 20px;
    }


    .card {

        background: white;

        padding: 25px;

        margin-bottom: 25px;

        border-radius: 10px;

        box-shadow:
            0 4px 15px
            rgba(0,0,0,0.08);
    }


    h2 {

        margin-top: 0;

        color: #172d4d;
    }


    .form-grid {

        display: grid;

        grid-template-columns:
            repeat(3, 1fr);

        gap: 15px;
    }


    .full {

        grid-column: 1 / -1;
    }


    label {

        display: block;

        font-weight: bold;

        margin-bottom: 6px;
    }


    input,
    select {

        width: 100%;

        padding: 11px;

        border:
            1px solid #ccc;

        border-radius: 6px;

        font-size: 14px;
    }


    button {

        border: none;

        border-radius: 6px;

        padding: 11px 18px;

        cursor: pointer;

        font-size: 14px;
    }


    .add-button {

        background: #172d4d;

        color: white;

        margin-top: 18px;
    }


    .edit-button {

        background: #172d4d;

        color: white;
    }


    .delete-button {

        background: #a40000;

        color: white;
    }


    .success {

        background: #dff5df;

        color: #216b21;

        padding: 13px;

        border-radius: 6px;

        margin-bottom: 20px;
    }


    .error {

        background: #ffe1e1;

        color: #a00000;

        padding: 13px;

        border-radius: 6px;

        margin-bottom: 20px;
    }


    table {

        width: 100%;

        border-collapse: collapse;

        font-size: 14px;
    }


    th {

        background: #172d4d;

        color: white;

        padding: 12px;

        text-align: left;
    }


    td {

        padding: 11px;

        border-bottom:
            1px solid #ddd;
    }


    tr:nth-child(even) {

        background: #f7f8fa;
    }


    .price {

        text-align: right;

        white-space: nowrap;
    }


    .actions {

        white-space: nowrap;
    }


    .catalogue-note {

        color: #666;

        margin-top: -10px;

        margin-bottom: 20px;
    }


    @media(max-width: 800px) {

        .form-grid {

            grid-template-columns: 1fr;
        }

        .full {

            grid-column: auto;
        }

        table {

            font-size: 12px;
        }

        .header {

            padding: 12px 18px;
        }

        .brand-link {

            font-size: 16px;
        }

        .header-logo {

            width: 45px;
        }

        .header-links {

            gap: 10px;
        }

    }

    </style>

    </head>


    <body>


    <!-- =========================================
         HEADER
    ========================================== -->

    <div class="header">

        <a
            href="../dashboard.php"
            class="brand-link"
        >

            <img
                src="../assets/secutech-logo.png"
                alt="SecuTech SA"
                class="header-logo"
            >

            <span>
                SecuTech Quoting System
            </span>

        </a>


        <div class="header-links">

            <a href="../dashboard.php">
                Home
            </a>

            <a href="../customers/index.php">
                Customers
            </a>

            <a href="import.php">
                Import Price List
            </a>

        </div>

    </div>


    <div class="container">


    <?php if ($catalogue_message): ?>

        <div class="success">

            <?= htmlspecialchars(
                $catalogue_message
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($catalogue_error): ?>

        <div class="error">

            <?= htmlspecialchars(
                $catalogue_error
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =========================================
         ADD ITEM
    ========================================== -->

    <div class="card">

        <h2>
            Item Catalogue
        </h2>

        <p class="catalogue-note">

            These are the products and services you
            keep and can reuse on future quotations.

        </p>


        <form method="POST">


        <div class="form-grid">


        <div>

        <label>
            Item Code *
        </label>

        <input
            type="text"
            name="catalogue_item_code"
            placeholder="e.g. HIK-8MP"
            required
        >

        </div>


        <div>

        <label>
            Description *
        </label>

        <input
            type="text"
            name="catalogue_description"
            placeholder="e.g. Hikvision 8MP Camera"
            required
        >

        </div>


        <div>

        <label>
            Category
        </label>

        <input
            type="text"
            name="catalogue_category"
            placeholder="e.g. CCTV"
        >

        </div>


        <div>

        <label>
            Item Type
        </label>

        <select
            name="catalogue_item_type"
        >

            <option value="Product">
                Product
            </option>

            <option value="Service">
                Service
            </option>

            <option value="Labour">
                Labour
            </option>

        </select>

        </div>


        <div>

        <label>
            Unit
        </label>

        <select
            name="catalogue_unit"
        >

            <option value="Each">
                Each
            </option>

            <option value="metre">
                metre
            </option>

            <option value="hour">
                hour
            </option>

            <option value="day">
                day
            </option>

            <option value="month">
                month
            </option>

        </select>

        </div>


        <div>

        <label>
            Selling Price
        </label>

        <input
            type="number"
            name="catalogue_selling_price"
            value="0.00"
            min="0"
            step="0.01"
        >

        </div>


        <div>

        <label>
            VAT Rate
        </label>

        <select
            name="catalogue_vat_rate"
        >

            <option value="15">
                15% - Standard VAT
            </option>

            <option value="0">
                0% - Zero Rated
            </option>

        </select>

        </div>


        </div>


        <button
            type="submit"
            name="catalogue_add"
            value="1"
            class="add-button"
        >

            + Add Item

        </button>


        </form>

    </div>


    <!-- =========================================
         PRODUCT LIST
    ========================================== -->

    <div class="card">

        <h2>
            Products & Services
        </h2>


        <?php if (empty($catalogue_products)): ?>

            <p>
                No active items have been added yet.
            </p>

        <?php else: ?>


        <div style="overflow-x:auto;">

        <table>

        <thead>

        <tr>

            <th>Code</th>

            <th>Description</th>

            <th>Category</th>

            <th>Type</th>

            <th>Unit</th>

            <th>VAT</th>

            <th class="price">
                Selling Price
            </th>

            <th>
                Action
            </th>

        </tr>

        </thead>


        <tbody>


        <?php foreach (
            $catalogue_products
            as $product
        ): ?>


        <tr>

        <td>

            <strong>
                <?= htmlspecialchars(
                    $product['item_code']
                ) ?>
            </strong>

        </td>


        <td>

            <?= htmlspecialchars(
                $product['description']
            ) ?>

        </td>


        <td>

            <?= htmlspecialchars(
                $product['category'] ?? ''
            ) ?>

        </td>


        <td>

            <?= htmlspecialchars(
                $product['item_type'] ?? ''
            ) ?>

        </td>


        <td>

            <?= htmlspecialchars(
                $product['unit'] ?? 'Each'
            ) ?>

        </td>


        <td>

            <?= number_format(
                (float)$product['vat_rate'],
                0
            ) ?>%

        </td>


        <td class="price">

            R <?= number_format(
                (float)$product['selling_price'],
                2
            ) ?>

        </td>


        <td class="actions">


            <!-- EDIT -->

            <form
                method="POST"
                style="display:inline-block;"
            >

                <input
                    type="hidden"
                    name="product_id"
                    value="<?= (int)$product['id'] ?>"
                >

                <input
                    type="hidden"
                    name="catalogue_item_code"
                    value="<?= htmlspecialchars(
                        $product['item_code']
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="catalogue_description"
                    value="<?= htmlspecialchars(
                        $product['description']
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="catalogue_category"
                    value="<?= htmlspecialchars(
                        $product['category'] ?? ''
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="catalogue_item_type"
                    value="<?= htmlspecialchars(
                        $product['item_type'] ?? 'Product'
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="catalogue_unit"
                    value="<?= htmlspecialchars(
                        $product['unit'] ?? 'Each'
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="catalogue_selling_price"
                    value="<?= htmlspecialchars(
                        $product['selling_price']
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="catalogue_vat_rate"
                    value="<?= htmlspecialchars(
                        $product['vat_rate']
                    ) ?>"
                >

                <button
                    type="button"
                    class="edit-button"
                    onclick="editItem(this)"
                >

                    Edit

                </button>

            </form>


            <!-- REMOVE -->

            <form
                method="POST"
                style="display:inline-block;"
            >

                <input
                    type="hidden"
                    name="product_id"
                    value="<?= (int)$product['id'] ?>"
                >

                <button
                    type="submit"
                    name="catalogue_delete"
                    value="1"
                    class="delete-button"
                    onclick="
                        return confirm(
                            'Remove this item from the active catalogue?'
                        );
                    "
                >

                    Remove

                </button>

            </form>


        </td>

        </tr>


        <?php endforeach; ?>


        </tbody>

        </table>

        </div>


        <?php endif; ?>


    </div>


    </div>


    <script>

    function editItem(button) {

        const form =
            button.closest('form');


        const values = {

            id:
                form.querySelector(
                    '[name="product_id"]'
                ).value,

            code:
                form.querySelector(
                    '[name="catalogue_item_code"]'
                ).value,

            description:
                form.querySelector(
                    '[name="catalogue_description"]'
                ).value,

            category:
                form.querySelector(
                    '[name="catalogue_category"]'
                ).value,

            type:
                form.querySelector(
                    '[name="catalogue_item_type"]'
                ).value,

            unit:
                form.querySelector(
                    '[name="catalogue_unit"]'
                ).value,

            price:
                form.querySelector(
                    '[name="catalogue_selling_price"]'
                ).value,

            vat:
                form.querySelector(
                    '[name="catalogue_vat_rate"]'
                ).value

        };


        const newCode =
            prompt(
                'Item Code:',
                values.code
            );

        if (newCode === null) return;


        const newDescription =
            prompt(
                'Description:',
                values.description
            );

        if (newDescription === null) return;


        const newCategory =
            prompt(
                'Category:',
                values.category
            );

        if (newCategory === null) return;


        const newPrice =
            prompt(
                'Selling Price:',
                values.price
            );

        if (newPrice === null) return;


        const newVat =
            prompt(
                'VAT Rate (15 or 0):',
                values.vat
            );

        if (newVat === null) return;


        const editForm =
            document.createElement('form');

        editForm.method = 'POST';


        const fields = {

            catalogue_edit: '1',

            product_id:
                values.id,

            catalogue_item_code:
                newCode,

            catalogue_description:
                newDescription,

            catalogue_category:
                newCategory,

            catalogue_item_type:
                values.type,

            catalogue_unit:
                values.unit,

            catalogue_selling_price:
                newPrice,

            catalogue_vat_rate:
                newVat

        };


        Object.keys(fields).forEach(
            function(name) {

                const input =
                    document.createElement(
                        'input'
                    );

                input.type = 'hidden';

                input.name = name;

                input.value =
                    fields[name];

                editForm.appendChild(
                    input
                );

            }
        );


        document.body.appendChild(
            editForm
        );

        editForm.submit();

    }

    </script>


    </body>

    </html>

    <?php

    exit;
}


/* =================================================
   EXISTING QUOTATION ITEM SYSTEM
================================================= */


/* -------------------------------------------------
   GET QUOTE + CUSTOMER
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        q.*,
        c.company_name,
        c.contact_name,
        c.address,
        c.telephone,
        c.email,
        c.vat_number
    FROM quotations q
    INNER JOIN customers c ON c.id = q.customer_id
    WHERE q.id = ?
");

$stmt->execute([$quote_id]);

$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {

    die('Quotation not found.');

}


/* -------------------------------------------------
   UPDATE QUOTE DETAILS
------------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_quote_details'])
) {

    $new_status = trim($_POST['status'] ?? 'Draft');
    $new_valid_until = trim($_POST['valid_until'] ?? '');
    $new_sales_person = trim($_POST['sales_person'] ?? '');
    $new_payment_terms = trim($_POST['payment_terms'] ?? '');
    $new_notes = trim($_POST['notes'] ?? '');

    $allowed_statuses = ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'];

    if (!in_array($new_status, $allowed_statuses, true)) {
        $new_status = 'Draft';
    }

    $stmt = $pdo->prepare("
        UPDATE quotations
        SET status = ?, valid_until = ?, sales_person = ?, payment_terms = ?, notes = ?, updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $new_status,
        $new_valid_until !== '' ? $new_valid_until : null,
        $new_sales_person,
        $new_payment_terms,
        $new_notes,
        $quote_id,
    ]);

    /* Refresh in-memory quote data so the page reflects the change immediately */
    $quote['status'] = $new_status;
    $quote['valid_until'] = $new_valid_until !== '' ? $new_valid_until : $quote['valid_until'];
    $quote['sales_person'] = $new_sales_person;
    $quote['payment_terms'] = $new_payment_terms;
    $quote['notes'] = $new_notes;

    $success = 'Quote details updated.';

}


/* -------------------------------------------------
   ADD ITEM
------------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_item'])
) {

    $product_id = !empty($_POST['product_id'])
        ? (int)$_POST['product_id']
        : null;

    $item_code = trim(
        $_POST['item_code'] ?? 'CUSTOM'
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $quantity = (float)(
        $_POST['quantity'] ?? 1
    );

    $unit_price = (float)(
        $_POST['unit_price'] ?? 0
    );

    $cost_price = ($_POST['cost_price'] ?? '') !== ''
        ? (float)$_POST['cost_price']
        : null;

    $margin_percent = ($_POST['margin_percent'] ?? '') !== ''
        ? (float)$_POST['margin_percent']
        : null;

    $discount = (float)(
        $_POST['discount'] ?? 0
    );

    $vat_rate = (float)(
        $_POST['vat_rate'] ?? 15
    );


    if ($description === '') {

        $error =
            'Please enter a description.';

    } elseif ($quantity <= 0) {

        $error =
            'Quantity must be greater than zero.';

    } else {


        /* Calculate line total */

        $line_total =
            $quantity * $unit_price;


        if ($discount > 0) {

            $line_total -=
                $line_total *
                ($discount / 100);

        }


        /* Get next sort order */

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(
                    MAX(sort_order),
                    0
                ) + 1
            FROM quotation_items
            WHERE quotation_id = ?
        ");

        $stmt->execute([
            $quote_id
        ]);

        $sort_order =
            (int)$stmt->fetchColumn();


        /* Insert item */

        $stmt = $pdo->prepare("
            INSERT INTO quotation_items
            (
                quotation_id,
                product_id,
                section_id,
                item_code,
                description,
                quantity,
                unit_price,
                cost_price,
                margin_percent,
                discount,
                vat_rate,
                line_total,
                sort_order
            )
            VALUES
            (
                :quotation_id,
                :product_id,
                NULL,
                :item_code,
                :description,
                :quantity,
                :unit_price,
                :cost_price,
                :margin_percent,
                :discount,
                :vat_rate,
                :line_total,
                :sort_order
            )
        ");


        $stmt->execute([

            ':quotation_id' =>
                $quote_id,

            ':product_id' =>
                $product_id,

            ':item_code' =>
                $item_code,

            ':description' =>
                $description,

            ':quantity' =>
                $quantity,

            ':unit_price' =>
                $unit_price,

            ':cost_price' =>
                $cost_price,

            ':margin_percent' =>
                $margin_percent,

            ':discount' =>
                $discount,

            ':vat_rate' =>
                $vat_rate,

            ':line_total' =>
                $line_total,

            ':sort_order' =>
                $sort_order

        ]);


        /* Recalculate subtotal */

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(
                    SUM(line_total),
                    0
                )
            FROM quotation_items
            WHERE quotation_id = ?
        ");

        $stmt->execute([
            $quote_id
        ]);

        $subtotal =
            (float)$stmt->fetchColumn();


        /* Calculate VAT by actual item VAT rate */

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(
                    SUM(
                        line_total *
                        vat_rate / 100
                    ),
                    0
                )
            FROM quotation_items
            WHERE quotation_id = ?
        ");

        $stmt->execute([
            $quote_id
        ]);

        $vat_amount =
            (float)$stmt->fetchColumn();


        $total =
            $subtotal +
            $vat_amount;


        /* Update quotation */

        $stmt = $pdo->prepare("
            UPDATE quotations
            SET
                subtotal = ?,
                vat_amount = ?,
                total = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([

            $subtotal,

            $vat_amount,

            $total,

            $quote_id

        ]);


        /* Reload quote */

        $stmt = $pdo->prepare("
            SELECT
                q.*,
                c.company_name,
                c.contact_name,
                c.address,
                c.telephone,
                c.email,
                c.vat_number
            FROM quotations q
            INNER JOIN customers c
                ON c.id = q.customer_id
            WHERE q.id = ?
        ");

        $stmt->execute([
            $quote_id
        ]);

        $quote =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        $success =
            'Item added successfully.';

    }

}


/* -------------------------------------------------
   UPDATE ITEM
------------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_item'])
) {

    $edit_item_id = (int)($_POST['edit_item_id'] ?? 0);

    $item_code = trim($_POST['item_code'] ?? 'CUSTOM');
    $description = trim($_POST['description'] ?? '');
    $quantity = (float)($_POST['quantity'] ?? 1);

    $cost_price = ($_POST['cost_price'] ?? '') !== ''
        ? (float)$_POST['cost_price']
        : null;

    $margin_percent = ($_POST['margin_percent'] ?? '') !== ''
        ? (float)$_POST['margin_percent']
        : null;

    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $vat_rate = (float)($_POST['vat_rate'] ?? 15);

    if ($edit_item_id <= 0) {

        $error = 'Could not identify which item to update.';

    } elseif ($description === '') {

        $error = 'Please enter a description.';

    } elseif ($quantity <= 0) {

        $error = 'Quantity must be greater than zero.';

    } else {

        $line_total = $quantity * $unit_price;

        if ($discount > 0) {
            $line_total -= $line_total * ($discount / 100);
        }

        $stmt = $pdo->prepare("
            UPDATE quotation_items
            SET item_code = :item_code,
                description = :description,
                quantity = :quantity,
                unit_price = :unit_price,
                cost_price = :cost_price,
                margin_percent = :margin_percent,
                discount = :discount,
                vat_rate = :vat_rate,
                line_total = :line_total
            WHERE id = :id AND quotation_id = :quotation_id
        ");

        $stmt->execute([
            ':item_code'       => $item_code,
            ':description'     => $description,
            ':quantity'        => $quantity,
            ':unit_price'      => $unit_price,
            ':cost_price'      => $cost_price,
            ':margin_percent'  => $margin_percent,
            ':discount'        => $discount,
            ':vat_rate'        => $vat_rate,
            ':line_total'      => $line_total,
            ':id'              => $edit_item_id,
            ':quotation_id'    => $quote_id,
        ]);

        /* Recalculate quote totals */

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(line_total), 0)
            FROM quotation_items
            WHERE quotation_id = ?
        ");
        $stmt->execute([$quote_id]);
        $new_subtotal = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(line_total * vat_rate / 100), 0)
            FROM quotation_items
            WHERE quotation_id = ?
        ");
        $stmt->execute([$quote_id]);
        $new_vat = (float)$stmt->fetchColumn();

        $new_total = $new_subtotal + $new_vat;

        $stmt = $pdo->prepare("
            UPDATE quotations
            SET subtotal = ?, vat_amount = ?, total = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_subtotal, $new_vat, $new_total, $quote_id]);

        $success = 'Item updated.';

    }

}


/* -------------------------------------------------
   DELETE ITEM
------------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_item'])
) {

    $item_id =
        (int)$_POST['item_id'];


    $stmt = $pdo->prepare("
        DELETE FROM quotation_items
        WHERE id = ?
        AND quotation_id = ?
    ");

    $stmt->execute([

        $item_id,

        $quote_id

    ]);


    /* Recalculate subtotal */

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(line_total),
                0
            )
        FROM quotation_items
        WHERE quotation_id = ?
    ");

    $stmt->execute([
        $quote_id
    ]);

    $subtotal =
        (float)$stmt->fetchColumn();


    /* Calculate VAT */

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(
                    line_total *
                    vat_rate / 100
                ),
                0
            )
        FROM quotation_items
        WHERE quotation_id = ?
    ");

    $stmt->execute([
        $quote_id
    ]);

    $vat_amount =
        (float)$stmt->fetchColumn();


    $total =
        $subtotal +
        $vat_amount;


    /* Update quotation */

    $stmt = $pdo->prepare("
        UPDATE quotations
        SET
            subtotal = ?,
            vat_amount = ?,
            total = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([

        $subtotal,

        $vat_amount,

        $total,

        $quote_id

    ]);


    $success =
        'Item removed.';

}


/* -------------------------------------------------
   GET PRODUCTS
------------------------------------------------- */

$stmt = $pdo->query("
    SELECT
        id,
        item_code,
        description,
        category,
        item_type,
        unit,
        selling_price,
        vat_rate,
        active
    FROM products
    WHERE active = 1
    ORDER BY category, description
");

$products =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* -------------------------------------------------
   GET QUOTE ITEMS
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        qi.*,
        p.unit,
        p.category
    FROM quotation_items qi
    LEFT JOIN products p
        ON p.id = qi.product_id
    WHERE qi.quotation_id = ?
    ORDER BY qi.sort_order, qi.id
");

$stmt->execute([
    $quote_id
]);

$items =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* -------------------------------------------------
   INTERNAL-ONLY PROFIT SUMMARY
   (never shown to customers - not selected on any
   customer-facing quote/invoice/PDF/email query)
------------------------------------------------- */

$total_cost = 0.0;
$total_sell_excl_vat = 0.0;
$has_cost_data = false;

foreach ($items as $item) {
    if ($item['cost_price'] !== null) {
        $has_cost_data = true;
        $total_cost += (float)$item['cost_price'] * (float)$item['quantity'];
    }
    $total_sell_excl_vat += (float)$item['line_total'];
}

$total_profit = $total_sell_excl_vat - $total_cost;
$overall_margin_percent = $total_sell_excl_vat > 0
    ? ($total_profit / $total_sell_excl_vat) * 100
    : 0;

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

    <?= htmlspecialchars(
        $quote['quote_number']
    ) ?>

    -
    SecuTech Quotation System

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


/* =========================================
   HEADER
========================================= */

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

    width: 55px;

    height: auto;

    display: block;
}


.header-links {

    display: flex;

    align-items: center;

    gap: 20px;
}


.header-links a {

    color: white;

    text-decoration: none;

    font-size: 14px;
}


.header-links a:hover {

    text-decoration: underline;
}


/* =========================================
   PAGE
========================================= */

.container {

    max-width: 1200px;

    margin: 35px auto;

    padding: 0 20px;
}


.card {

    background: white;

    padding: 25px;

    margin-bottom: 25px;

    border-radius: 10px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.07);
}


.quote-header {

    display: flex;

    justify-content: space-between;

    gap: 30px;
}


.quote-header h2 {

    margin-top: 0;
}


.customer-details {

    line-height: 1.6;
}


.quote-number {

    text-align: right;
}


.quote-number strong {

    font-size: 22px;
}


.message {

    padding: 14px;

    border-radius: 6px;

    margin-bottom: 20px;
}


.success {

    background: #dff5df;

    color: #216b21;
}


.error {

    background: #ffe1e1;

    color: #a00000;
}


h3 {

    margin-top: 0;
}


.form-grid {

    display: grid;

    grid-template-columns:
        2fr 1fr 1fr 1fr;

    gap: 15px;
}


.form-group {

    display: flex;

    flex-direction: column;
}


.form-group label {

    font-weight: bold;

    margin-bottom: 6px;
}


input,
select {

    padding: 11px;

    border:
        1px solid #ccc;

    border-radius: 6px;

    font-size: 14px;
}


.full {

    grid-column: 1 / -1;
}


button {

    background: #222;

    color: white;

    border: none;

    padding: 11px 18px;

    border-radius: 6px;

    cursor: pointer;
}


button:hover {

    background: #444;
}


.add-button {

    margin-top: 20px;
}


table {

    width: 100%;

    border-collapse: collapse;
}


th {

    background: #222;

    color: white;

    padding: 12px;

    text-align: left;
}


td {

    padding: 12px;

    border-bottom:
        1px solid #ddd;
}


.text-right {

    text-align: right;
}


.delete-button {

    background: #b00000;

    padding: 7px 10px;

    font-size: 12px;
}


.edit-item-button {

    background: #172d4d;

    color: white;

    border: none;

    padding: 7px 10px;

    font-size: 12px;

    border-radius: 4px;

    cursor: pointer;

    margin-right: 6px;
}


.totals {

    margin-left: auto;

    max-width: 350px;

    margin-top: 25px;
}


.total-row {

    display: flex;

    justify-content: space-between;

    padding: 8px 0;
}


.grand-total {

    font-size: 21px;

    font-weight: bold;

    border-top:
        2px solid #222;

    margin-top: 8px;

    padding-top: 15px;
}


.actions {

    display: flex;

    gap: 10px;

    margin-top: 25px;
}


.secondary {

    background: #ddd;

    color: #222;

    text-decoration: none;

    padding: 11px 18px;

    border-radius: 6px;
}


@media(max-width: 800px) {

    .quote-header {

        flex-direction: column;
    }

    .quote-number {

        text-align: left;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    table {

        font-size: 13px;
    }

    .header {

        padding: 12px 18px;
    }

    .brand-link {

        font-size: 16px;
    }

    .header-logo {

        width: 45px;
    }

    .header-links {

        gap: 10px;
    }

}

</style>

</head>


<body>


<!-- =========================================
     HEADER
========================================= -->

<div class="header">

    <a
        href="../dashboard.php"
        class="brand-link"
    >

        <img
            src="../assets/secutech-logo.png"
            alt="SecuTech SA"
            class="header-logo"
        >

        <span>
            SecuTech Quoting System
        </span>

    </a>


    <div class="header-links">

        <a href="../dashboard.php">
            Home
        </a>

        <a href="../customers/index.php">
            Customers
        </a>

        <a href="../items/index.php">
            Items
        </a>

        <a href="import.php">
            Import Price List
        </a>

    </div>

</div>


<div class="container">


<!-- =========================================
     QUOTE HEADER
========================================= -->

<div class="card">

<div class="quote-header">


<div>

<h2>

    <?= htmlspecialchars(
        $quote['company_name']
    ) ?>

</h2>


<div class="customer-details">

<strong>
    Contact:
</strong>

<?= htmlspecialchars(
    $quote['contact_name'] ?? ''
) ?>

<br>


<strong>
    Telephone:
</strong>

<?= htmlspecialchars(
    $quote['telephone'] ?? ''
) ?>

<br>


<strong>
    Email:
</strong>

<?= htmlspecialchars(
    $quote['email'] ?? ''
) ?>

<br>


<strong>
    VAT Number:
</strong>

<?= htmlspecialchars(
    $quote['vat_number'] ?? ''
) ?>

</div>

</div>


<div class="quote-number">

<div>
    Quotation
</div>


<strong>

    <?= htmlspecialchars(
        $quote['quote_number']
    ) ?>

</strong>


<br><br>


<strong>
    Date:
</strong>

<?= htmlspecialchars(
    $quote['quote_date']
) ?>


<br>


<strong>
    Valid Until:
</strong>

<?= htmlspecialchars(
    $quote['valid_until']
) ?>

</div>


</div>

</div>


<?php if (!empty($success)): ?>

<div class="message success">

    <?= htmlspecialchars(
        $success
    ) ?>

</div>

<?php endif; ?>


<?php if (!empty($error)): ?>

<div class="message error">

    <?= htmlspecialchars(
        $error
    ) ?>

</div>

<?php endif; ?>


<!-- =========================================
     EDIT QUOTE DETAILS
========================================= -->

<div class="card">

<h3 style="cursor:pointer;" onclick="toggleDetailsForm()">
    Edit Quote Details <span id="detailsToggleArrow">▸</span>
</h3>

<div id="detailsFormWrap" style="display:none;">

<form method="POST">

<input type="hidden" name="quote_id" value="<?= $quote_id ?>">

<div class="form-grid">

<div class="form-group">
<label>Status</label>
<select name="status">
<?php foreach (['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'] as $status_option): ?>
<option value="<?= $status_option ?>" <?= ($quote['status'] ?? 'Draft') === $status_option ? 'selected' : '' ?>>
    <?= $status_option ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="form-group">
<label>Valid Until</label>
<input type="date" name="valid_until" value="<?= htmlspecialchars($quote['valid_until']) ?>">
</div>

<div class="form-group">
<label>Sales Person</label>
<input type="text" name="sales_person" value="<?= htmlspecialchars($quote['sales_person'] ?? '') ?>">
</div>

<div class="form-group">
<label>Payment Terms</label>
<input type="text" name="payment_terms" value="<?= htmlspecialchars($quote['payment_terms'] ?? '') ?>">
</div>

<div class="form-group full">
<label>Notes</label>
<textarea name="notes" rows="3" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-family:inherit; box-sizing:border-box;"><?= htmlspecialchars($quote['notes'] ?? '') ?></textarea>
</div>

</div>

<button type="submit" name="update_quote_details" class="add-button">
    Save Quote Details
</button>

</form>

</div>

</div>


<!-- =========================================
     ADD ITEM
========================================= -->

<div class="card">

<h3 id="itemFormHeading">
    Add Item
</h3>


<form method="POST" id="itemForm">


<input
    type="hidden"
    name="quote_id"
    value="<?= $quote_id ?>"
>

<input
    type="hidden"
    name="edit_item_id"
    id="edit_item_id"
    value=""
>


<div class="form-grid">


<div class="form-group">

<label>
    Product / Item
</label>


<select
    id="product_id"
    name="product_id"
>


<option value="">

    -- Custom Item --

</option>


<?php foreach (
    $products
    as $product
): ?>


<option

    value="<?= (int)$product['id'] ?>"

    data-code="<?= htmlspecialchars(
        $product['item_code']
    ) ?>"

    data-description="<?= htmlspecialchars(
        $product['description']
    ) ?>"

    data-price="<?= htmlspecialchars(
        $product['selling_price']
    ) ?>"

    data-vat="<?= htmlspecialchars(
        $product['vat_rate']
    ) ?>"

    data-unit="<?= htmlspecialchars(
        $product['unit']
    ) ?>"
>

<?= htmlspecialchars(
    $product['description']
) ?>


<?php if (
    !empty($product['category'])
): ?>

    —
    <?= htmlspecialchars(
        $product['category']
    ) ?>

<?php endif; ?>


</option>


<?php endforeach; ?>


</select>

</div>


<div class="form-group">

<label>
    Item Code
</label>


<input
    type="text"
    name="item_code"
    id="item_code"
    value="CUSTOM"
>

</div>


<div class="form-group">

<label>
    Cost (internal only)
</label>


<input
    type="number"
    name="cost_price"
    id="cost_price"
    value=""
    min="0"
    step="0.01"
    placeholder="e.g. 10.00"
    oninput="recalcMarkupPrice()"
>

</div>


<div class="form-group">

<label>
    Margin % of selling price (internal only)
</label>


<input
    type="number"
    name="margin_percent"
    id="margin_percent"
    value=""
    min="0"
    max="99.99"
    step="0.01"
    placeholder="e.g. 25"
    oninput="recalcMarkupPrice()"
>

</div>


<div class="form-group">

<label>
    Quantity
</label>


<input
    type="number"
    name="quantity"
    id="quantity"
    value="1"
    min="0.01"
    step="0.01"
>

</div>


<div class="form-group">

<label>
    Unit Price
</label>


<input
    type="number"
    name="unit_price"
    id="unit_price"
    value="0.00"
    min="0"
    step="0.01"
>

</div>


<div class="form-group">

<label>
    Discount %
</label>


<input
    type="number"
    name="discount"
    id="discount"
    value="0"
    min="0"
    max="100"
    step="0.01"
>

</div>


<div class="form-group">

<label>
    VAT Rate
</label>


<select
    name="vat_rate"
    id="vat_rate"
>

<option value="15" selected>
    15% - Standard VAT
</option>

<option value="0">
    0% - Zero Rated
</option>

</select>

</div>


<div class="form-group full">

<label>
    Description
</label>


<input
    type="text"
    name="description"
    id="description"
    placeholder="Enter item description"
    required
>

</div>


</div>


<button
    type="submit"
    name="add_item"
    id="itemSubmitButton"
    class="add-button"
>

    + Add Item

</button>

<button
    type="button"
    id="cancelEditButton"
    class="add-button"
    style="display:none; background:#888; margin-left:10px;"
    onclick="cancelEditItem()"
>

    Cancel Edit

</button>


</form>

</div>


<!-- =========================================
     CURRENT ITEMS
========================================= -->

<div class="card">

<h3>
    Quotation Items
</h3>


<?php if (empty($items)): ?>

<p>
    No items have been added to this quotation yet.
</p>


<?php else: ?>


<table>


<thead>

<tr>

<th>
    Code
</th>

<th>
    Description
</th>

<th>
    Qty
</th>

<th>
    Unit
</th>

<th>
    Unit Price
</th>

<th>
    Discount
</th>

<th>
    Total
</th>

<th>
    Action
</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $items
    as $item
): ?>


<tr>


<td>

    <?= htmlspecialchars(
        $item['item_code']
    ) ?>

</td>


<td>

    <?= htmlspecialchars(
        $item['description']
    ) ?>

</td>


<td>

    <?= number_format(
        (float)$item['quantity'],
        2
    ) ?>

</td>


<td>

    <?= htmlspecialchars(
        $item['unit'] ?? 'Each'
    ) ?>

</td>


<td>

    R
    <?= number_format(
        (float)$item['unit_price'],
        2
    ) ?>

</td>


<td>

    <?= number_format(
        (float)$item['discount'],
        2
    ) ?>%

</td>


<td>

    R
    <?= number_format(
        (float)$item['line_total'],
        2
    ) ?>

</td>


<td>


<form method="POST">


<input
    type="hidden"
    name="quote_id"
    value="<?= $quote_id ?>"
>


<input
    type="hidden"
    name="item_id"
    value="<?= (int)$item['id'] ?>"
>


<button
    type="submit"
    name="delete_item"
    class="delete-button"
    onclick="
        return confirm(
            'Remove this item?'
        );
    "
>

    Delete

</button>


</form>


<button
    type="button"
    class="edit-item-button"
    onclick="startEditItem(<?= (int)$item['id'] ?>, <?= htmlspecialchars(json_encode([
        'item_code'      => $item['item_code'],
        'description'    => $item['description'],
        'quantity'       => $item['quantity'],
        'unit_price'     => $item['unit_price'],
        'cost_price'     => $item['cost_price'],
        'margin_percent' => $item['margin_percent'],
        'discount'       => $item['discount'],
        'vat_rate'       => $item['vat_rate'],
    ]), ENT_QUOTES, 'UTF-8') ?>)"
>

    Edit

</button>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


<div class="totals">


<div class="total-row">

<span>
    Subtotal
</span>

<strong>

    R
    <?= number_format(
        (float)$quote['subtotal'],
        2
    ) ?>

</strong>

</div>


<div class="total-row">

<span>
    VAT
</span>

<strong>

    R
    <?= number_format(
        (float)$quote['vat_amount'],
        2
    ) ?>

</strong>

</div>


<div class="total-row grand-total">

<span>
    Total
</span>

<strong>

    R
    <?= number_format(
        (float)$quote['total'],
        2
    ) ?>

</strong>

</div>


</div>


<?php if ($has_cost_data): ?>

<div class="totals" style="margin-top:15px; background:#fff8e1; border:1px dashed #d4a017;">

<div style="font-size:12px; font-weight:bold; color:#8a6400; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px;">
    Internal Only - Not Shown to Customer
</div>

<div class="total-row">
<span>Total Cost</span>
<strong>R <?= number_format($total_cost, 2) ?></strong>
</div>

<div class="total-row">
<span>Total Profit</span>
<strong>R <?= number_format($total_profit, 2) ?></strong>
</div>

<div class="total-row">
<span>Overall Margin</span>
<strong><?= number_format($overall_margin_percent, 1) ?>%</strong>
</div>

</div>

<?php endif; ?>


<?php endif; ?>


<div class="actions">


<a
    class="secondary"
    href="../dashboard.php"
>

    Home

</a>


<a
    class="secondary"
    href="../customers/index.php"
>

    Customers

</a>


</div>


</div>


</div>


<script>


/* =========================================
   TOGGLE QUOTE DETAILS FORM
========================================= */

function toggleDetailsForm() {
    const wrap = document.getElementById('detailsFormWrap');
    const arrow = document.getElementById('detailsToggleArrow');
    const isHidden = wrap.style.display === 'none';
    wrap.style.display = isHidden ? 'block' : 'none';
    arrow.textContent = isHidden ? '▾' : '▸';
}


/* =========================================
   EDIT EXISTING LINE ITEM
========================================= */

function startEditItem(itemId, data) {

    document.getElementById('edit_item_id').value = itemId;

    document.getElementById('item_code').value = data.item_code || 'CUSTOM';
    document.getElementById('description').value = data.description || '';
    document.getElementById('quantity').value = data.quantity || 1;
    document.getElementById('unit_price').value = data.unit_price || 0;
    document.getElementById('cost_price').value = data.cost_price !== null ? data.cost_price : '';
    document.getElementById('margin_percent').value = data.margin_percent !== null ? data.margin_percent : '';
    document.getElementById('discount').value = data.discount || 0;
    document.getElementById('vat_rate').value = data.vat_rate || 15;

    const submitBtn = document.getElementById('itemSubmitButton');
    submitBtn.name = 'update_item';
    submitBtn.textContent = 'Save Changes';

    document.getElementById('itemFormHeading').textContent = 'Edit Item';
    document.getElementById('cancelEditButton').style.display = 'inline-block';

    document.getElementById('itemForm').scrollIntoView({ behavior: 'smooth', block: 'start' });

}

function cancelEditItem() {

    document.getElementById('edit_item_id').value = '';
    document.getElementById('itemForm').reset();

    const submitBtn = document.getElementById('itemSubmitButton');
    submitBtn.name = 'add_item';
    submitBtn.textContent = '+ Add Item';

    document.getElementById('itemFormHeading').textContent = 'Add Item';
    document.getElementById('cancelEditButton').style.display = 'none';

}


/* =========================================
   COST + MARGIN -> UNIT PRICE (true margin calc)
   Margin % is a percentage of the SELLING PRICE,
   e.g. cost 10, margin 25% -> price 13.33
========================================= */

function recalcMarkupPrice() {

    const costInput = document.getElementById('cost_price');
    const marginInput = document.getElementById('margin_percent');
    const priceInput = document.getElementById('unit_price');

    const cost = parseFloat(costInput.value);
    const margin = parseFloat(marginInput.value);

    if (!isNaN(cost) && !isNaN(margin) && margin < 100) {
        const price = cost / (1 - (margin / 100));
        priceInput.value = price.toFixed(2);
    }

}


/* =========================================
   PRODUCT AUTO-FILL
========================================= */

const productSelect =
    document.getElementById(
        'product_id'
    );


const itemCode =
    document.getElementById(
        'item_code'
    );


const description =
    document.getElementById(
        'description'
    );


const unitPrice =
    document.getElementById(
        'unit_price'
    );


const vatRate =
    document.getElementById(
        'vat_rate'
    );


productSelect.addEventListener(
    'change',
    function() {

        const option =
            this.options[
                this.selectedIndex
            ];


        if (!option.value) {

            itemCode.value =
                'CUSTOM';

            description.value =
                '';

            unitPrice.value =
                '0.00';

            vatRate.value =
                '15';

            return;
        }


        itemCode.value =
            option.dataset.code || '';


        description.value =
            option.dataset.description
            || '';


        unitPrice.value =
            option.dataset.price
            || '0.00';


        vatRate.value =
            option.dataset.vat
            || '15';

    }
);

</script>


</body>

</html>