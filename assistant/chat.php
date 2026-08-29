<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api.php';

header('Content-Type: application/json');

if (ANTHROPIC_API_KEY === '' || ANTHROPIC_API_KEY === 'sk-ant-your-real-key-here') {
    http_response_code(500);
    echo json_encode([
        'error' => 'No Anthropic API key configured. Copy config/api.local.example.php ' .
                   'to config/api.local.php and add your real key.'
    ]);
    exit;
}


/* =================================================
   READ REQUEST
================================================= */

$raw_input = file_get_contents('php://input');
$request = json_decode($raw_input, true);

$conversation = $request['messages'] ?? [];

if (!is_array($conversation) || empty($conversation)) {
    http_response_code(400);
    echo json_encode(['error' => 'No message provided.']);
    exit;
}


/* =================================================
   TOOL DEFINITIONS
================================================= */

$tools = [
    [
        'name' => 'search_customers',
        'description' => 'Search existing customers by company or contact name. ' .
            'Always use this before creating a new customer, to check whether ' .
            'they already exist.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search text, e.g. a company or contact name',
                ],
            ],
            'required' => ['query'],
        ],
    ],
    [
        'name' => 'search_products',
        'description' => 'Search the item/product catalogue by description or item ' .
            'code. Use this to find correct existing pricing before adding an item ' .
            'to a quotation.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search text, e.g. "camera" or an item code',
                ],
            ],
            'required' => ['query'],
        ],
    ],
    [
        'name' => 'create_customer',
        'description' => 'Create a new customer record. Only call this after the ' .
            'user has confirmed the customer does not already exist and has ' .
            'approved the details you are about to save.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'company_name' => ['type' => 'string'],
                'contact_name' => ['type' => 'string'],
                'address'      => ['type' => 'string'],
                'telephone'    => ['type' => 'string'],
                'email'        => ['type' => 'string'],
                'vat_number'   => ['type' => 'string'],
            ],
            'required' => ['company_name'],
        ],
    ],
    [
        'name' => 'create_quotation',
        'description' => 'Create a new draft quotation for a customer. Only call ' .
            'this after the user has confirmed the customer and is ready to start ' .
            'the quote. This does not add line items - call add_quotation_item ' .
            'afterward for each item.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'customer_id'   => ['type' => 'integer'],
                'sales_person'  => ['type' => 'string'],
                'payment_terms' => ['type' => 'string'],
                'notes'         => ['type' => 'string'],
                'valid_days'    => [
                    'type' => 'integer',
                    'description' => 'How many days the quote stays valid for. Default 7.',
                ],
            ],
            'required' => ['customer_id'],
        ],
    ],
    [
        'name' => 'add_quotation_item',
        'description' => 'Add a single line item to an existing quotation. Call ' .
            'this once per item. The quotation totals are recalculated ' .
            'automatically after each call. Only call this after the user has ' .
            'confirmed the item, quantity, and price.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'quotation_id' => ['type' => 'integer'],
                'product_id'   => [
                    'type' => 'integer',
                    'description' => 'ID of an existing catalogue product, if this ' .
                        'item matches one found via search_products. Omit for a ' .
                        'custom item.',
                ],
                'item_code'    => ['type' => 'string'],
                'description'  => ['type' => 'string'],
                'quantity'     => ['type' => 'number'],
                'unit_price'   => ['type' => 'number'],
                'discount'     => [
                    'type' => 'number',
                    'description' => 'Percentage discount, 0-100. Default 0.',
                ],
                'vat_rate'     => [
                    'type' => 'number',
                    'description' => '15 for standard VAT, 0 for zero-rated. Default 15.',
                ],
            ],
            'required' => ['quotation_id', 'description', 'quantity', 'unit_price'],
        ],
    ],
    [
        'name' => 'get_quotation_summary',
        'description' => 'Get the full current summary of a quotation, including ' .
            'all items (with their item_id) and totals. Call this after adding ' .
            'items to check everything is correct, or when amending an existing ' .
            'quotation to see what is currently on it before changing anything.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'quotation_id' => ['type' => 'integer'],
            ],
            'required' => ['quotation_id'],
        ],
    ],
    [
        'name' => 'search_quotations',
        'description' => 'Search existing quotations by quote number or customer ' .
            'name. Use this when the user wants to look up, amend, or check an ' .
            'existing quote rather than create a new one.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search text, e.g. a quote number like ' .
                        '"JP-20261229-1" or a customer/company name',
                ],
            ],
            'required' => ['query'],
        ],
    ],
    [
        'name' => 'update_quotation_item',
        'description' => 'Change the quantity, price, discount, VAT rate, or ' .
            'description of an existing line item on a quotation (identified by ' .
            'its item_id from get_quotation_summary). Only the fields provided ' .
            'are changed - leave others out to keep them as they are. Totals are ' .
            'recalculated automatically. Only call this after the user has ' .
            'confirmed exactly what should change.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'item_id'     => ['type' => 'integer'],
                'description' => ['type' => 'string'],
                'quantity'    => ['type' => 'number'],
                'unit_price'  => ['type' => 'number'],
                'discount'    => ['type' => 'number'],
                'vat_rate'    => ['type' => 'number'],
            ],
            'required' => ['item_id'],
        ],
    ],
    [
        'name' => 'remove_quotation_item',
        'description' => 'Remove a line item from a quotation entirely, ' .
            'identified by its item_id from get_quotation_summary. Totals are ' .
            'recalculated automatically. Only call this after the user has ' .
            'confirmed which item to remove.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'item_id' => ['type' => 'integer'],
            ],
            'required' => ['item_id'],
        ],
    ],
    [
        'name' => 'create_product',
        'description' => 'Add a new item to the product/service catalogue, so it ' .
            'can be reused on future quotations. Only call this after the user ' .
            'has confirmed the description, price, unit, and VAT rate.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'item_code'      => ['type' => 'string'],
                'description'    => ['type' => 'string'],
                'category'       => ['type' => 'string'],
                'unit'           => [
                    'type' => 'string',
                    'description' => 'e.g. "Each", "Meter", "Hour". Default "Each".',
                ],
                'selling_price'  => ['type' => 'number'],
                'vat_rate'       => [
                    'type' => 'number',
                    'description' => '15 for standard VAT, 0 for zero-rated. Default 15.',
                ],
            ],
            'required' => ['description', 'selling_price'],
        ],
    ],
];


/* =================================================
   SYSTEM PROMPT
================================================= */

$today = date('Y-m-d');

$system_prompt = <<<PROMPT
You are a helpful assistant built into the Secutech Quotation System, a South
African security and IT company's internal quoting tool. Staff will describe a
quote to you in plain language, and your job is to turn that into a real
quotation in the system by using your tools.

Today's date is {$today}. All prices are in South African Rand (R). Standard
VAT is 15%; some items may be zero-rated (0%).

Rules you must follow:
- Before creating a NEW customer, always call search_customers first to check
  they don't already exist. If a close match is found, ask the user whether to
  use the existing customer or create a new one.
- Before adding an item, call search_products to check whether it already
  exists in the catalogue (for correct pricing and item code). If nothing
  matches, ask the user for a price, or add it as a custom item if they give
  you one directly.
- Before calling create_customer, create_quotation, add_quotation_item,
  update_quotation_item, remove_quotation_item, or create_product, briefly
  summarize exactly what you are about to create, change, or add, and wait for
  the user to confirm (e.g. "yes", "correct", "go ahead") before calling the
  tool. Never create, change, or remove something the user hasn't approved.
- After all items are added, call get_quotation_summary and give the user a
  short final summary including the quote number, item count, and total.
- If the user wants to look up, amend, or check an existing quotation, call
  search_quotations first (by quote number or customer name). If more than one
  result could match, ask which one they mean. Then call get_quotation_summary
  to see the current items and their item_id values before changing anything.
- When amending a quotation whose status is not "Draft", mention that it may
  already be with the customer, but go ahead if the user confirms they still
  want to update it.
- To change an item on an existing quote, use update_quotation_item with its
  item_id, only including the fields that are actually changing. To remove an
  item entirely, use remove_quotation_item.
- When adding a new item to the product catalogue, confirm the description,
  price, unit, and VAT rate with the user before calling create_product.
- Keep your responses short and conversational. Ask only one question at a
  time when you need more information.
- Write in plain sentences only - never use markdown formatting: no
  asterisks, bullet points, numbered lists, headers, backticks, or
  bold/italic markers. This response is both displayed as text and read
  aloud, so it needs to work as natural spoken language.
- If the user's request is ambiguous (e.g. an item description with no clear
  match), ask for clarification rather than guessing.
PROMPT;


/* =================================================
   TOOL EXECUTION
================================================= */

function execute_tool(string $name, array $input, PDO $pdo): array
{
    switch ($name) {
        case 'search_customers':
            return tool_search_customers($pdo, $input);
        case 'search_products':
            return tool_search_products($pdo, $input);
        case 'create_customer':
            return tool_create_customer($pdo, $input);
        case 'create_quotation':
            return tool_create_quotation($pdo, $input);
        case 'add_quotation_item':
            return tool_add_quotation_item($pdo, $input);
        case 'get_quotation_summary':
            return tool_get_quotation_summary($pdo, $input);
        case 'search_quotations':
            return tool_search_quotations($pdo, $input);
        case 'update_quotation_item':
            return tool_update_quotation_item($pdo, $input);
        case 'remove_quotation_item':
            return tool_remove_quotation_item($pdo, $input);
        case 'create_product':
            return tool_create_product($pdo, $input);
        default:
            return ['error' => 'Unknown tool: ' . $name];
    }
}

function tool_search_customers(PDO $pdo, array $input): array
{
    $query = trim($input['query'] ?? '');
    $like = '%' . $query . '%';

    $stmt = $pdo->prepare("
        SELECT id, company_name, contact_name, email, telephone
        FROM customers
        WHERE company_name LIKE ? OR contact_name LIKE ?
        ORDER BY company_name
        LIMIT 10
    ");
    $stmt->execute([$like, $like]);

    return ['customers' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function tool_search_products(PDO $pdo, array $input): array
{
    $query = trim($input['query'] ?? '');
    $like = '%' . $query . '%';

    $stmt = $pdo->prepare("
        SELECT id, item_code, description, category, unit, selling_price, vat_rate
        FROM products
        WHERE active = 1 AND (description LIKE ? OR item_code LIKE ?)
        ORDER BY description
        LIMIT 10
    ");
    $stmt->execute([$like, $like]);

    return ['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function tool_create_customer(PDO $pdo, array $input): array
{
    $company_name = trim($input['company_name'] ?? '');

    if ($company_name === '') {
        return ['error' => 'company_name is required'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO customers
        (company_name, contact_name, address, telephone, email, vat_number)
        VALUES
        (:company_name, :contact_name, :address, :telephone, :email, :vat_number)
    ");

    $stmt->execute([
        ':company_name' => $company_name,
        ':contact_name' => trim($input['contact_name'] ?? ''),
        ':address'      => trim($input['address'] ?? ''),
        ':telephone'    => trim($input['telephone'] ?? ''),
        ':email'        => trim($input['email'] ?? ''),
        ':vat_number'   => trim($input['vat_number'] ?? ''),
    ]);

    return [
        'customer_id'  => (int)$pdo->lastInsertId(),
        'company_name' => $company_name,
    ];
}

function tool_create_quotation(PDO $pdo, array $input): array
{
    $customer_id = (int)($input['customer_id'] ?? 0);

    if ($customer_id <= 0) {
        return ['error' => 'customer_id is required'];
    }

    $valid_days = (int)($input['valid_days'] ?? 7);

    $quote_date  = date('Y-m-d');
    $valid_until = date('Y-m-d', strtotime("+{$valid_days} days"));

    /*
     * Quote number format: JP-YYYYMMDD-N
     * (matches the numbering used elsewhere in the app)
     */
    $today = date('Ymd');
    $number_prefix = "JP-$today-";

    $stmt = $pdo->prepare("
        SELECT quote_number
        FROM quotations
        WHERE quote_number LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$number_prefix . '%']);
    $lastQuote = $stmt->fetchColumn();

    if ($lastQuote) {
        $lastSegment = substr($lastQuote, strlen($number_prefix));
        $nextNumber = (int)$lastSegment + 1;
    } else {
        $nextNumber = 1;
    }

    $quote_number = $number_prefix . $nextNumber;

    $stmt = $pdo->prepare("
        INSERT INTO quotations
        (customer_id, quote_number, quote_date, valid_until, status,
         sales_person, payment_terms, subtotal, vat_amount, total, notes)
        VALUES
        (:customer_id, :quote_number, :quote_date, :valid_until, 'Draft',
         :sales_person, :payment_terms, 0, 0, 0, :notes)
    ");

    $stmt->execute([
        ':customer_id'   => $customer_id,
        ':quote_number'  => $quote_number,
        ':quote_date'    => $quote_date,
        ':valid_until'   => $valid_until,
        ':sales_person'  => trim($input['sales_person'] ?? ''),
        ':payment_terms' => trim($input['payment_terms'] ?? ''),
        ':notes'         => trim($input['notes'] ?? ''),
    ]);

    return [
        'quotation_id' => (int)$pdo->lastInsertId(),
        'quote_number' => $quote_number,
    ];
}

function tool_add_quotation_item(PDO $pdo, array $input): array
{
    $quotation_id = (int)($input['quotation_id'] ?? 0);

    if ($quotation_id <= 0) {
        return ['error' => 'quotation_id is required'];
    }

    $product_id  = !empty($input['product_id']) ? (int)$input['product_id'] : null;
    $item_code   = trim($input['item_code'] ?? 'CUSTOM');
    $description = trim($input['description'] ?? '');
    $quantity    = (float)($input['quantity'] ?? 1);
    $unit_price  = (float)($input['unit_price'] ?? 0);
    $discount    = (float)($input['discount'] ?? 0);
    $vat_rate    = (float)($input['vat_rate'] ?? 15);

    if ($description === '') {
        return ['error' => 'description is required'];
    }

    if ($quantity <= 0) {
        return ['error' => 'quantity must be greater than zero'];
    }

    $line_total = $quantity * $unit_price;

    if ($discount > 0) {
        $line_total -= $line_total * ($discount / 100);
    }

    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(sort_order), 0) + 1
        FROM quotation_items
        WHERE quotation_id = ?
    ");
    $stmt->execute([$quotation_id]);
    $sort_order = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO quotation_items
        (quotation_id, product_id, section_id, item_code, description,
         quantity, unit_price, discount, vat_rate, line_total, sort_order)
        VALUES
        (:quotation_id, :product_id, NULL, :item_code, :description,
         :quantity, :unit_price, :discount, :vat_rate, :line_total, :sort_order)
    ");

    $stmt->execute([
        ':quotation_id' => $quotation_id,
        ':product_id'   => $product_id,
        ':item_code'    => $item_code,
        ':description'  => $description,
        ':quantity'     => $quantity,
        ':unit_price'   => $unit_price,
        ':discount'     => $discount,
        ':vat_rate'     => $vat_rate,
        ':line_total'   => $line_total,
        ':sort_order'   => $sort_order,
    ]);

    /* Recalculate quotation totals */

    $totals = recalculate_quotation_totals($pdo, $quotation_id);

    return array_merge(
        [
            'item_added'   => $description,
            'line_total'   => round($line_total, 2),
            'quotation_id' => $quotation_id,
        ],
        $totals
    );
}

function recalculate_quotation_totals(PDO $pdo, int $quotation_id): array
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(line_total), 0)
        FROM quotation_items
        WHERE quotation_id = ?
    ");
    $stmt->execute([$quotation_id]);
    $subtotal = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(line_total * vat_rate / 100), 0)
        FROM quotation_items
        WHERE quotation_id = ?
    ");
    $stmt->execute([$quotation_id]);
    $vat_amount = (float)$stmt->fetchColumn();

    $total = $subtotal + $vat_amount;

    $stmt = $pdo->prepare("
        UPDATE quotations
        SET subtotal = ?, vat_amount = ?, total = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$subtotal, $vat_amount, $total, $quotation_id]);

    return [
        'quotation_id'       => $quotation_id,
        'quotation_subtotal' => round($subtotal, 2),
        'quotation_vat'      => round($vat_amount, 2),
        'quotation_total'    => round($total, 2),
    ];
}

function tool_get_quotation_summary(PDO $pdo, array $input): array
{
    $quotation_id = (int)($input['quotation_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT q.*, c.company_name, c.contact_name
        FROM quotations q
        INNER JOIN customers c ON c.id = q.customer_id
        WHERE q.id = ?
    ");
    $stmt->execute([$quotation_id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        return ['error' => 'Quotation not found'];
    }

    $stmt = $pdo->prepare("
        SELECT id AS item_id, item_code, description, quantity, unit_price, discount, vat_rate, line_total
        FROM quotation_items
        WHERE quotation_id = ?
        ORDER BY sort_order, id
    ");
    $stmt->execute([$quotation_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'quotation_id' => $quotation_id,
        'quote_number' => $quote['quote_number'],
        'status'       => $quote['status'],
        'customer'     => $quote['company_name'],
        'subtotal'     => (float)$quote['subtotal'],
        'vat_amount'   => (float)$quote['vat_amount'],
        'total'        => (float)$quote['total'],
        'items'        => $items,
    ];
}

function tool_search_quotations(PDO $pdo, array $input): array
{
    $query = trim($input['query'] ?? '');
    $like = '%' . $query . '%';

    $stmt = $pdo->prepare("
        SELECT
            q.id AS quotation_id,
            q.quote_number,
            q.quote_date,
            q.status,
            q.total,
            c.company_name,
            c.contact_name
        FROM quotations q
        INNER JOIN customers c ON c.id = q.customer_id
        WHERE q.quote_number LIKE ?
           OR c.company_name LIKE ?
           OR c.contact_name LIKE ?
        ORDER BY q.id DESC
        LIMIT 10
    ");
    $stmt->execute([$like, $like, $like]);

    return ['quotations' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function tool_update_quotation_item(PDO $pdo, array $input): array
{
    $item_id = (int)($input['item_id'] ?? 0);

    if ($item_id <= 0) {
        return ['error' => 'item_id is required'];
    }

    $stmt = $pdo->prepare("SELECT * FROM quotation_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        return ['error' => 'Item not found'];
    }

    /* Only overwrite fields that were actually provided; keep the rest as-is. */

    $description = array_key_exists('description', $input)
        ? trim($input['description'])
        : $item['description'];

    $quantity = array_key_exists('quantity', $input)
        ? (float)$input['quantity']
        : (float)$item['quantity'];

    $unit_price = array_key_exists('unit_price', $input)
        ? (float)$input['unit_price']
        : (float)$item['unit_price'];

    $discount = array_key_exists('discount', $input)
        ? (float)$input['discount']
        : (float)$item['discount'];

    $vat_rate = array_key_exists('vat_rate', $input)
        ? (float)$input['vat_rate']
        : (float)$item['vat_rate'];

    if ($quantity <= 0) {
        return ['error' => 'quantity must be greater than zero'];
    }

    $line_total = $quantity * $unit_price;

    if ($discount > 0) {
        $line_total -= $line_total * ($discount / 100);
    }

    $stmt = $pdo->prepare("
        UPDATE quotation_items
        SET description = ?, quantity = ?, unit_price = ?, discount = ?,
            vat_rate = ?, line_total = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $description, $quantity, $unit_price, $discount, $vat_rate, $line_total, $item_id,
    ]);

    $totals = recalculate_quotation_totals($pdo, (int)$item['quotation_id']);

    return array_merge(
        [
            'item_updated' => $description,
            'line_total'   => round($line_total, 2),
        ],
        $totals
    );
}

function tool_remove_quotation_item(PDO $pdo, array $input): array
{
    $item_id = (int)($input['item_id'] ?? 0);

    if ($item_id <= 0) {
        return ['error' => 'item_id is required'];
    }

    $stmt = $pdo->prepare("
        SELECT quotation_id, description
        FROM quotation_items
        WHERE id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        return ['error' => 'Item not found'];
    }

    $stmt = $pdo->prepare("DELETE FROM quotation_items WHERE id = ?");
    $stmt->execute([$item_id]);

    $totals = recalculate_quotation_totals($pdo, (int)$item['quotation_id']);

    return array_merge(
        ['item_removed' => $item['description']],
        $totals
    );
}

function tool_create_product(PDO $pdo, array $input): array
{
    $description = trim($input['description'] ?? '');

    if ($description === '') {
        return ['error' => 'description is required'];
    }

    $item_code     = trim($input['item_code'] ?? '');
    $category      = trim($input['category'] ?? '');
    $unit          = trim($input['unit'] ?? 'Each');
    $selling_price = (float)($input['selling_price'] ?? 0);
    $vat_rate      = (float)($input['vat_rate'] ?? 15);

    $stmt = $pdo->prepare("
        INSERT INTO products
        (item_code, description, category, unit, selling_price, vat_rate, active)
        VALUES
        (:item_code, :description, :category, :unit, :selling_price, :vat_rate, 1)
    ");
    $stmt->execute([
        ':item_code'     => $item_code,
        ':description'   => $description,
        ':category'      => $category,
        ':unit'          => $unit,
        ':selling_price' => $selling_price,
        ':vat_rate'      => $vat_rate,
    ]);

    return [
        'product_id'    => (int)$pdo->lastInsertId(),
        'description'   => $description,
        'selling_price' => $selling_price,
    ];
}


/* =================================================
   CALL CLAUDE API
================================================= */

function call_claude(array $messages, array $tools, string $system): array
{
    $payload = [
        'model'      => CLAUDE_MODEL,
        'max_tokens' => 1024,
        'system'     => $system,
        'messages'   => $messages,
        'tools'      => $tools,
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 60,
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('Claude API request failed: ' . $curl_error);
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        throw new Exception('Claude API error: ' . ($data['error']['message'] ?? 'Unknown error'));
    }

    if ($data === null) {
        throw new Exception('Unexpected response from Claude API.');
    }

    return $data;
}


/* =================================================
   TOOL-USE LOOP
================================================= */

try {

    $max_iterations = 6;
    $final_text = '';
    $created_quotation_id = null;

    for ($i = 0; $i < $max_iterations; $i++) {

        $data = call_claude($conversation, $tools, $system_prompt);

        $conversation[] = [
            'role'    => 'assistant',
            'content' => $data['content'],
        ];

        if (($data['stop_reason'] ?? '') !== 'tool_use') {

            foreach ($data['content'] as $block) {
                if ($block['type'] === 'text') {
                    $final_text .= $block['text'];
                }
            }

            break;

        }

        $tool_results = [];

        foreach ($data['content'] as $block) {

            if ($block['type'] === 'tool_use') {

                $result = execute_tool($block['name'], $block['input'], $pdo);

                if (isset($result['quotation_id'])) {
                    $created_quotation_id = (int)$result['quotation_id'];
                }

                $tool_results[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content'     => json_encode($result),
                ];

            }

        }

        $conversation[] = [
            'role'    => 'user',
            'content' => $tool_results,
        ];

    }

    echo json_encode([
        'reply'         => $final_text !== '' ? $final_text : 'Done.',
        'quotation_id'  => $created_quotation_id,
        'messages'      => $conversation,
    ]);

} catch (Exception $e) {

    error_log('Assistant chat error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Something went wrong talking to the assistant: ' . $e->getMessage(),
    ]);

}
