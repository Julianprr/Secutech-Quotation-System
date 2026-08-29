<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/quote_pdf.php';
require_once __DIR__ . '/../includes/invoice_pdf.php';

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


    /* ===== INVOICES ===== */

    [
        'name' => 'search_invoices',
        'description' => 'Search existing invoices by invoice number or customer name.',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string']],
            'required' => ['query'],
        ],
    ],
    [
        'name' => 'get_invoice_summary',
        'description' => 'Get the full summary of an invoice - items, totals, status, and deposit info.',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['invoice_id' => ['type' => 'integer']],
            'required' => ['invoice_id'],
        ],
    ],
    [
        'name' => 'create_invoice_from_quote',
        'description' => 'Convert an existing quotation into an invoice, copying ' .
            'the customer and all line items across. If the quotation was already ' .
            'converted before, this just returns the existing invoice instead of ' .
            'making a duplicate. Only call this after the user confirms.',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['quotation_id' => ['type' => 'integer']],
            'required' => ['quotation_id'],
        ],
    ],
    [
        'name' => 'update_invoice_status',
        'description' => 'Change an invoice\'s payment status to Unpaid, Deposit ' .
            'Paid, or Paid in Full. If marking as Deposit Paid, include the ' .
            'deposit_amount actually received (ask the user for it, or offer 75% ' .
            'of the total as the standard default). Only call this after the user confirms.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'invoice_id' => ['type' => 'integer'],
                'status' => [
                    'type' => 'string',
                    'enum' => ['Unpaid', 'Deposit Paid', 'Paid in Full'],
                ],
                'deposit_amount' => ['type' => 'number'],
            ],
            'required' => ['invoice_id', 'status'],
        ],
    ],


    /* ===== EMAILING ===== */

    [
        'name' => 'email_quotation',
        'description' => 'Email a quotation (as a PDF attachment) to a customer. ' .
            'If to_email is omitted, uses the email address already on the ' .
            'customer\'s profile. Only call this after the user confirms the ' .
            'recipient address.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'quotation_id' => ['type' => 'integer'],
                'to_email' => ['type' => 'string'],
                'note' => ['type' => 'string', 'description' => 'Optional short personal message to include in the email.'],
            ],
            'required' => ['quotation_id'],
        ],
    ],
    [
        'name' => 'email_invoice',
        'description' => 'Email an invoice (as a PDF attachment) to a customer. ' .
            'If to_email is omitted, uses the email address already on the ' .
            'customer\'s profile. Only call this after the user confirms the ' .
            'recipient address.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'invoice_id' => ['type' => 'integer'],
                'to_email' => ['type' => 'string'],
                'note' => ['type' => 'string'],
            ],
            'required' => ['invoice_id'],
        ],
    ],


    /* ===== QUOTE ACTIONS ===== */

    [
        'name' => 'duplicate_quotation',
        'description' => 'Create a copy of an existing quotation (same customer, ' .
            'items, and pricing) as a new draft quote with a fresh quote number. ' .
            'Useful as a starting point for a similar new quote. Only call this ' .
            'after the user confirms.',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['quotation_id' => ['type' => 'integer']],
            'required' => ['quotation_id'],
        ],
    ],
    [
        'name' => 'apply_bulk_discount',
        'description' => 'Apply the same percentage discount to every line item ' .
            'on a quotation at once, and recalculate totals. Only call this after ' .
            'the user confirms the exact percentage.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'quotation_id' => ['type' => 'integer'],
                'discount_percent' => ['type' => 'number'],
            ],
            'required' => ['quotation_id', 'discount_percent'],
        ],
    ],


    /* ===== CUSTOMER MANAGEMENT ===== */

    [
        'name' => 'update_customer',
        'description' => 'Update a customer\'s contact details. Only include the ' .
            'fields that are actually changing. Only call this after the user confirms.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'customer_id' => ['type' => 'integer'],
                'company_name' => ['type' => 'string'],
                'contact_name' => ['type' => 'string'],
                'address' => ['type' => 'string'],
                'telephone' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'vat_number' => ['type' => 'string'],
            ],
            'required' => ['customer_id'],
        ],
    ],
    [
        'name' => 'get_customer_history',
        'description' => 'Get a customer\'s full quote and invoice history - use ' .
            'this when the user asks to see everything for a specific customer.',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string', 'description' => 'Customer name to search for']],
            'required' => ['query'],
        ],
    ],


    /* ===== REPORTING ===== */

    [
        'name' => 'get_quote_stats',
        'description' => 'Get a summary count and total value of quotations ' .
            'created in a given period. Use this for questions like "how many ' .
            'quotes did I send this month".',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['today', 'this_week', 'this_month', 'all_time'],
                ],
            ],
            'required' => ['period'],
        ],
    ],
    [
        'name' => 'get_unpaid_invoices',
        'description' => 'List all invoices that are not yet fully paid (Unpaid ' .
            'or Deposit Paid status), with their outstanding balance. Use this ' .
            'for questions about outstanding money or unpaid invoices.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [],
        ],
    ],
    [
        'name' => 'get_inactive_customers',
        'description' => 'List customers who have not had a quotation created ' .
            'for them in a given number of days. Use this for questions about ' .
            'customers who haven\'t been quoted recently.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'days' => ['type' => 'integer', 'description' => 'Default 90 if not specified.'],
            ],
        ],
    ],


    /* ===== NAVIGATION ===== */

    [
        'name' => 'navigate_to_page',
        'description' => 'Take the user directly to a page in the app. Use this ' .
            'when they ask to be taken somewhere, e.g. "open the customer list" ' .
            'or "take me to invoices".',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'page' => [
                    'type' => 'string',
                    'enum' => ['dashboard', 'customers', 'items', 'create_quote', 'quotations', 'invoices'],
                ],
            ],
            'required' => ['page'],
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
- To convert a quotation to an invoice, use create_invoice_from_quote. To
  change an invoice's payment status, use update_invoice_status - if marking
  Deposit Paid, ask for the amount received or offer 75% of the total as the
  standard default.
- To email a quotation or invoice, use email_quotation / email_invoice.
  Confirm the recipient address with the user first (it defaults to the
  customer's email on file if they don't specify one).
- To duplicate a quotation as a starting point for a new one, use
  duplicate_quotation. To discount every item on a quote at once, use
  apply_bulk_discount - confirm the exact percentage first.
- To update a customer's contact details, use update_customer, only
  including the fields that are changing, after the user confirms.
- For questions about business activity - how many quotes this month, unpaid
  invoices, customers who haven't been quoted recently - use get_quote_stats,
  get_unpaid_invoices, or get_inactive_customers. These are read-only and can
  be called without confirmation.
- If the user asks to be taken to a page (e.g. "open invoices", "take me to
  customers"), call navigate_to_page. This is a UI action, not a data change,
  so no confirmation is needed first.
- Reply in whichever language the user writes or speaks to you in (English,
  Afrikaans, or otherwise) - match their language naturally throughout the
  conversation.
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
        case 'search_invoices':
            return tool_search_invoices($pdo, $input);
        case 'get_invoice_summary':
            return tool_get_invoice_summary($pdo, $input);
        case 'create_invoice_from_quote':
            return tool_create_invoice_from_quote($pdo, $input);
        case 'update_invoice_status':
            return tool_update_invoice_status($pdo, $input);
        case 'email_quotation':
            return tool_email_quotation($pdo, $input);
        case 'email_invoice':
            return tool_email_invoice($pdo, $input);
        case 'duplicate_quotation':
            return tool_duplicate_quotation($pdo, $input);
        case 'apply_bulk_discount':
            return tool_apply_bulk_discount($pdo, $input);
        case 'update_customer':
            return tool_update_customer($pdo, $input);
        case 'get_customer_history':
            return tool_get_customer_history($pdo, $input);
        case 'get_quote_stats':
            return tool_get_quote_stats($pdo, $input);
        case 'get_unpaid_invoices':
            return tool_get_unpaid_invoices($pdo, $input);
        case 'get_inactive_customers':
            return tool_get_inactive_customers($pdo, $input);
        case 'navigate_to_page':
            return tool_navigate_to_page($pdo, $input);
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


/* ===================================================
   INVOICES
=================================================== */

function tool_search_invoices(PDO $pdo, array $input): array
{
    $query = trim($input['query'] ?? '');
    $like = '%' . $query . '%';

    $stmt = $pdo->prepare("
        SELECT i.id AS invoice_id, i.invoice_number, i.invoice_date, i.status, i.total,
               c.company_name, c.contact_name
        FROM invoices i
        INNER JOIN customers c ON c.id = i.customer_id
        WHERE i.invoice_number LIKE ? OR c.company_name LIKE ? OR c.contact_name LIKE ?
        ORDER BY i.id DESC
        LIMIT 10
    ");
    $stmt->execute([$like, $like, $like]);

    return ['invoices' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function tool_get_invoice_summary(PDO $pdo, array $input): array
{
    $invoice_id = (int)($input['invoice_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT i.*, c.company_name, c.contact_name
        FROM invoices i
        INNER JOIN customers c ON c.id = i.customer_id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        return ['error' => 'Invoice not found'];
    }

    $stmt = $pdo->prepare("
        SELECT item_code, description, quantity, unit_price, discount, vat_rate, line_total
        FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id
    ");
    $stmt->execute([$invoice_id]);

    return [
        'invoice_id'     => $invoice_id,
        'invoice_number' => $invoice['invoice_number'],
        'status'         => $invoice['status'],
        'deposit_amount' => $invoice['deposit_amount'] !== null ? (float)$invoice['deposit_amount'] : null,
        'customer'       => $invoice['company_name'],
        'subtotal'       => (float)$invoice['subtotal'],
        'vat_amount'     => (float)$invoice['vat_amount'],
        'total'          => (float)$invoice['total'],
        'items'          => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function tool_create_invoice_from_quote(PDO $pdo, array $input): array
{
    $quote_id = (int)($input['quotation_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        return ['error' => 'Quotation not found'];
    }

    if (!empty($quote['converted_invoice_id'])) {
        return [
            'invoice_id' => (int)$quote['converted_invoice_id'],
            'already_existed' => true,
        ];
    }

    $today = date('Ymd');
    $number_prefix = "JPI-$today-";

    $stmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$number_prefix . '%']);
    $lastInvoice = $stmt->fetchColumn();
    $nextNumber = $lastInvoice ? ((int)substr($lastInvoice, strlen($number_prefix)) + 1) : 1;
    $invoice_number = $number_prefix . $nextNumber;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO invoices
            (quotation_id, customer_id, invoice_number, invoice_date, valid_until, status,
             sales_person, payment_terms, subtotal, vat_amount, total, notes)
            VALUES
            (:quotation_id, :customer_id, :invoice_number, :invoice_date, :valid_until, 'Unpaid',
             :sales_person, :payment_terms, :subtotal, :vat_amount, :total, :notes)
        ");
        $stmt->execute([
            ':quotation_id'  => $quote_id,
            ':customer_id'   => $quote['customer_id'],
            ':invoice_number' => $invoice_number,
            ':invoice_date'  => date('Y-m-d'),
            ':valid_until'   => $quote['valid_until'],
            ':sales_person'  => $quote['sales_person'],
            ':payment_terms' => $quote['payment_terms'],
            ':subtotal'      => $quote['subtotal'],
            ':vat_amount'    => $quote['vat_amount'],
            ':total'         => $quote['total'],
            ':notes'         => $quote['notes'],
        ]);

        $invoice_id = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order, id");
        $stmt->execute([$quote_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $insertItem = $pdo->prepare("
            INSERT INTO invoice_items
            (invoice_id, product_id, section_id, item_code, description, quantity, unit_price, discount, vat_rate, line_total, sort_order)
            VALUES
            (:invoice_id, :product_id, :section_id, :item_code, :description, :quantity, :unit_price, :discount, :vat_rate, :line_total, :sort_order)
        ");

        foreach ($items as $item) {
            $insertItem->execute([
                ':invoice_id'  => $invoice_id,
                ':product_id'  => $item['product_id'],
                ':section_id'  => $item['section_id'],
                ':item_code'   => $item['item_code'],
                ':description' => $item['description'],
                ':quantity'    => $item['quantity'],
                ':unit_price'  => $item['unit_price'],
                ':discount'    => $item['discount'],
                ':vat_rate'    => $item['vat_rate'],
                ':line_total'  => $item['line_total'],
                ':sort_order'  => $item['sort_order'],
            ]);
        }

        $stmt = $pdo->prepare("UPDATE quotations SET converted_invoice_id = ? WHERE id = ?");
        $stmt->execute([$invoice_id, $quote_id]);

        $pdo->commit();

        return ['invoice_id' => $invoice_id, 'invoice_number' => $invoice_number, 'already_existed' => false];

    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['error' => 'Could not convert quotation: ' . $e->getMessage()];
    }
}

function tool_update_invoice_status(PDO $pdo, array $input): array
{
    $invoice_id = (int)($input['invoice_id'] ?? 0);
    $status = $input['status'] ?? '';

    if (!in_array($status, ['Unpaid', 'Deposit Paid', 'Paid in Full'], true)) {
        return ['error' => 'Invalid status'];
    }

    if ($status === 'Deposit Paid') {
        $deposit_amount = isset($input['deposit_amount']) ? (float)$input['deposit_amount'] : null;
        $stmt = $pdo->prepare("UPDATE invoices SET status = ?, deposit_amount = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $deposit_amount, $invoice_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $invoice_id]);
    }

    return ['invoice_id' => $invoice_id, 'status' => $status];
}


/* ===================================================
   EMAILING
=================================================== */

function tool_email_quotation(PDO $pdo, array $input): array
{
    $quote_id = (int)($input['quotation_id'] ?? 0);
    $note = trim($input['note'] ?? '');

    $stmt = $pdo->query("SELECT * FROM company_settings ORDER BY id ASC LIMIT 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("
        SELECT q.*, c.company_name, c.contact_name, c.telephone, c.email AS customer_email
        FROM quotations q INNER JOIN customers c ON c.id = q.customer_id
        WHERE q.id = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        return ['error' => 'Quotation not found'];
    }

    $to_email = trim($input['to_email'] ?? '') ?: ($quote['customer_email'] ?? '');

    if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'No valid email address available. Ask the user for one.'];
    }

    $stmt = $pdo->prepare("
        SELECT item_code, description, quantity, unit_price, discount, vat_rate, line_total
        FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order, id
    ");
    $stmt->execute([$quote_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf_bytes = generate_quote_pdf($company, $quote, $items, (float)$quote['subtotal'], (float)$quote['vat_amount'], (float)$quote['total']);

    $html_body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#222;">' .
        '<div style="background:#172d4d;color:#fff;padding:22px;text-align:center;"><h1 style="margin:0;font-size:20px;">' .
        htmlspecialchars($company['company_name'] ?? 'SecuTech SA') . '</h1></div>' .
        '<div style="padding:24px;font-size:14px;line-height:1.6;">' .
        ($note !== '' ? '<p>' . nl2br(htmlspecialchars($note)) . '</p><hr>' : '') .
        '<p>Please find attached quotation <strong>' . htmlspecialchars($quote['quote_number']) . '</strong> for <strong>' .
        htmlspecialchars($quote['company_name']) . '</strong>, valid until <strong>' . htmlspecialchars($quote['valid_until']) . '</strong>.</p>' .
        '</div></div>';

    $result = send_app_email($to_email, 'Quotation ' . $quote['quote_number'], $html_body, [], [
        'filename' => 'Quotation-' . $quote['quote_number'] . '.pdf',
        'content'  => $pdf_bytes,
        'mime'     => 'application/pdf',
    ]);

    if ($result['success']) {
        $stmt = $pdo->prepare("UPDATE quotations SET status = 'Sent', emailed_at = NOW() WHERE id = ?");
        $stmt->execute([$quote_id]);
        return ['sent' => true, 'to' => $to_email, 'quote_number' => $quote['quote_number']];
    }

    return ['error' => $result['error']];
}

function tool_email_invoice(PDO $pdo, array $input): array
{
    $invoice_id = (int)($input['invoice_id'] ?? 0);
    $note = trim($input['note'] ?? '');

    $stmt = $pdo->query("SELECT * FROM company_settings ORDER BY id ASC LIMIT 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("
        SELECT i.*, c.company_name, c.contact_name, c.telephone, c.email AS customer_email
        FROM invoices i INNER JOIN customers c ON c.id = i.customer_id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        return ['error' => 'Invoice not found'];
    }

    $to_email = trim($input['to_email'] ?? '') ?: ($invoice['customer_email'] ?? '');

    if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'No valid email address available. Ask the user for one.'];
    }

    $stmt = $pdo->prepare("
        SELECT item_code, description, quantity, unit_price, discount, vat_rate, line_total
        FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf_bytes = generate_invoice_pdf($company, $invoice, $items, (float)$invoice['subtotal'], (float)$invoice['vat_amount'], (float)$invoice['total']);

    $html_body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#222;">' .
        '<div style="background:#172d4d;color:#fff;padding:22px;text-align:center;"><h1 style="margin:0;font-size:20px;">' .
        htmlspecialchars($company['company_name'] ?? 'SecuTech SA') . '</h1></div>' .
        '<div style="padding:24px;font-size:14px;line-height:1.6;">' .
        ($note !== '' ? '<p>' . nl2br(htmlspecialchars($note)) . '</p><hr>' : '') .
        '<p>Please find attached invoice <strong>' . htmlspecialchars($invoice['invoice_number']) . '</strong> for <strong>' .
        htmlspecialchars($invoice['company_name']) . '</strong>.</p></div></div>';

    $result = send_app_email($to_email, 'Invoice ' . $invoice['invoice_number'], $html_body, [], [
        'filename' => 'Invoice-' . $invoice['invoice_number'] . '.pdf',
        'content'  => $pdf_bytes,
        'mime'     => 'application/pdf',
    ]);

    if ($result['success']) {
        return ['sent' => true, 'to' => $to_email, 'invoice_number' => $invoice['invoice_number']];
    }

    return ['error' => $result['error']];
}


/* ===================================================
   QUOTE ACTIONS
=================================================== */

function tool_duplicate_quotation(PDO $pdo, array $input): array
{
    $source_id = (int)($input['quotation_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmt->execute([$source_id]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        return ['error' => 'Quotation not found'];
    }

    $today = date('Ymd');
    $number_prefix = "JP-$today-";
    $stmt = $pdo->prepare("SELECT quote_number FROM quotations WHERE quote_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$number_prefix . '%']);
    $lastQuote = $stmt->fetchColumn();
    $nextNumber = $lastQuote ? ((int)substr($lastQuote, strlen($number_prefix)) + 1) : 1;
    $new_number = $number_prefix . $nextNumber;

    $stmt = $pdo->prepare("
        INSERT INTO quotations
        (customer_id, quote_number, quote_date, valid_until, status, sales_person, payment_terms, subtotal, vat_amount, total, notes)
        VALUES
        (:customer_id, :quote_number, :quote_date, :valid_until, 'Draft', :sales_person, :payment_terms, :subtotal, :vat_amount, :total, :notes)
    ");
    $stmt->execute([
        ':customer_id'   => $source['customer_id'],
        ':quote_number'  => $new_number,
        ':quote_date'    => date('Y-m-d'),
        ':valid_until'   => date('Y-m-d', strtotime('+7 days')),
        ':sales_person'  => $source['sales_person'],
        ':payment_terms' => $source['payment_terms'],
        ':subtotal'      => $source['subtotal'],
        ':vat_amount'    => $source['vat_amount'],
        ':total'         => $source['total'],
        ':notes'         => $source['notes'],
    ]);

    $new_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order, id");
    $stmt->execute([$source_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insertItem = $pdo->prepare("
        INSERT INTO quotation_items
        (quotation_id, product_id, section_id, item_code, description, quantity, unit_price, discount, vat_rate, line_total, sort_order)
        VALUES
        (:quotation_id, :product_id, :section_id, :item_code, :description, :quantity, :unit_price, :discount, :vat_rate, :line_total, :sort_order)
    ");

    foreach ($items as $item) {
        $insertItem->execute([
            ':quotation_id' => $new_id,
            ':product_id'   => $item['product_id'],
            ':section_id'   => $item['section_id'],
            ':item_code'    => $item['item_code'],
            ':description'  => $item['description'],
            ':quantity'     => $item['quantity'],
            ':unit_price'   => $item['unit_price'],
            ':discount'     => $item['discount'],
            ':vat_rate'     => $item['vat_rate'],
            ':line_total'   => $item['line_total'],
            ':sort_order'   => $item['sort_order'],
        ]);
    }

    return ['quotation_id' => $new_id, 'quote_number' => $new_number];
}

function tool_apply_bulk_discount(PDO $pdo, array $input): array
{
    $quotation_id = (int)($input['quotation_id'] ?? 0);
    $discount_percent = (float)($input['discount_percent'] ?? 0);

    $stmt = $pdo->prepare("SELECT id, quantity, unit_price FROM quotation_items WHERE quotation_id = ?");
    $stmt->execute([$quotation_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $update = $pdo->prepare("UPDATE quotation_items SET discount = ?, line_total = ? WHERE id = ?");

    foreach ($items as $item) {
        $line_total = (float)$item['quantity'] * (float)$item['unit_price'];
        $line_total -= $line_total * ($discount_percent / 100);
        $update->execute([$discount_percent, $line_total, $item['id']]);
    }

    $totals = recalculate_quotation_totals($pdo, $quotation_id);

    return array_merge(['discount_applied' => $discount_percent, 'items_updated' => count($items)], $totals);
}


/* ===================================================
   CUSTOMER MANAGEMENT
=================================================== */

function tool_update_customer(PDO $pdo, array $input): array
{
    $customer_id = (int)($input['customer_id'] ?? 0);

    $fields = ['company_name', 'contact_name', 'address', 'telephone', 'email', 'vat_number'];
    $sets = [];
    $params = [':id' => $customer_id];

    foreach ($fields as $field) {
        if (array_key_exists($field, $input)) {
            $sets[] = "$field = :$field";
            $params[":$field"] = trim($input[$field]);
        }
    }

    if (empty($sets)) {
        return ['error' => 'No fields provided to update'];
    }

    $stmt = $pdo->prepare("UPDATE customers SET " . implode(', ', $sets) . " WHERE id = :id");
    $stmt->execute($params);

    return ['customer_id' => $customer_id, 'updated_fields' => array_keys($input)];
}

function tool_get_customer_history(PDO $pdo, array $input): array
{
    $query = trim($input['query'] ?? '');
    $like = '%' . $query . '%';

    $stmt = $pdo->prepare("SELECT * FROM customers WHERE company_name LIKE ? OR contact_name LIKE ? LIMIT 1");
    $stmt->execute([$like, $like]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        return ['error' => 'Customer not found'];
    }

    $stmt = $pdo->prepare("SELECT quote_number, quote_date, status, total FROM quotations WHERE customer_id = ? ORDER BY id DESC");
    $stmt->execute([$customer['id']]);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT invoice_number, invoice_date, status, total FROM invoices WHERE customer_id = ? ORDER BY id DESC");
    $stmt->execute([$customer['id']]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'customer_id'   => (int)$customer['id'],
        'company_name'  => $customer['company_name'],
        'quotations'    => $quotes,
        'invoices'      => $invoices,
    ];
}


/* ===================================================
   REPORTING
=================================================== */

function tool_get_quote_stats(PDO $pdo, array $input): array
{
    $period = $input['period'] ?? 'this_month';

    $where = '1=1';
    if ($period === 'today') {
        $where = 'DATE(quote_date) = CURDATE()';
    } elseif ($period === 'this_week') {
        $where = 'YEARWEEK(quote_date, 1) = YEARWEEK(CURDATE(), 1)';
    } elseif ($period === 'this_month') {
        $where = 'YEAR(quote_date) = YEAR(CURDATE()) AND MONTH(quote_date) = MONTH(CURDATE())';
    }

    $stmt = $pdo->query("SELECT COUNT(*) AS quote_count, COALESCE(SUM(total),0) AS total_value FROM quotations WHERE $where");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'period'      => $period,
        'quote_count' => (int)$row['quote_count'],
        'total_value' => round((float)$row['total_value'], 2),
    ];
}

function tool_get_unpaid_invoices(PDO $pdo, array $input): array
{
    $stmt = $pdo->query("
        SELECT i.invoice_number, i.status, i.total, i.deposit_amount, c.company_name
        FROM invoices i
        INNER JOIN customers c ON c.id = i.customer_id
        WHERE i.status IN ('Unpaid', 'Deposit Paid')
        ORDER BY i.id DESC
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $invoices = array_map(function ($row) {
        $balance = (float)$row['total'] - (float)($row['deposit_amount'] ?? 0);
        return [
            'invoice_number' => $row['invoice_number'],
            'company_name'   => $row['company_name'],
            'status'         => $row['status'],
            'total'          => (float)$row['total'],
            'balance_due'    => round($balance, 2),
        ];
    }, $rows);

    return [
        'unpaid_invoices' => $invoices,
        'total_outstanding' => round(array_sum(array_column($invoices, 'balance_due')), 2),
    ];
}

function tool_get_inactive_customers(PDO $pdo, array $input): array
{
    $days = (int)($input['days'] ?? 90);

    $stmt = $pdo->prepare("
        SELECT c.company_name, c.contact_name, MAX(q.quote_date) AS last_quote_date
        FROM customers c
        LEFT JOIN quotations q ON q.customer_id = c.id
        GROUP BY c.id
        HAVING last_quote_date IS NULL OR last_quote_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ORDER BY last_quote_date ASC
    ");
    $stmt->execute([$days]);

    return ['inactive_customers' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'days_threshold' => $days];
}


/* ===================================================
   NAVIGATION
=================================================== */

function tool_navigate_to_page(PDO $pdo, array $input): array
{
    $page_map = [
        'dashboard'    => 'dashboard.php',
        'customers'    => 'customers/index.php',
        'items'        => 'items/index.php',
        'create_quote' => 'create/index.php',
        'quotations'   => 'view/list.php',
        'invoices'     => 'invoices/list.php',
    ];

    $page = $input['page'] ?? '';

    if (!isset($page_map[$page])) {
        return ['error' => 'Unknown page'];
    }

    return ['navigate_url' => $page_map[$page]];

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
    $created_invoice_id = null;
    $navigate_url = null;

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

                if (isset($result['invoice_id'])) {
                    $created_invoice_id = (int)$result['invoice_id'];
                }

                if (isset($result['navigate_url'])) {
                    $navigate_url = $result['navigate_url'];
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
        'invoice_id'    => $created_invoice_id,
        'navigate_url'  => $navigate_url,
        'messages'      => $conversation,
    ]);

} catch (Exception $e) {

    error_log('Assistant chat error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Something went wrong talking to the assistant: ' . $e->getMessage(),
    ]);

}
