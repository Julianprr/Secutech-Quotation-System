# Secutech Quotation System

Automated quotation system for technicians on the move. Built in PHP with a MySQL/MariaDB database, designed to run on standard shared hosting (cPanel).

## Features

- Customer management (add / edit / list)
- Product & service item catalogue with VAT rates
- Create quotations, add line items, auto-calculate VAT and totals
- Printable / PDF-style quotation view
- Simple session-based login

## Setup

### 1. Database

Create a MySQL database and import your schema (tables: `customers`, `products`, `quotations`, `quotation_items`, `company_settings`).

### 2. Configure credentials

Copy the example config and fill in your real database details:

```bash
cp config/db.local.example.php config/db.local.php
```

Then edit `config/db.local.php` with your actual host, database name, username, and password. This file is git-ignored and will never be committed.

Alternatively, you can set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` as environment variables instead (useful on hosts that support it).

### 3. Deploy

Upload all files to your web root (e.g. `public_html` on cPanel). Make sure `config/db.local.php` is uploaded too — it's excluded from git on purpose, so it has to be added to the server manually (or set via environment variables).

### 4. AI Assistant (optional)

The dashboard includes a chat assistant that can search customers/products and build quotations for you conversationally. To enable it:

1. Sign up at [console.anthropic.com](https://console.anthropic.com) (separate from any claude.ai subscription — billed separately, pay-as-you-go).
2. Create an API key under Settings → API Keys.
3. Copy `config/api.local.example.php` to `config/api.local.php` and paste in your real key.
4. Upload `config/api.local.php` to your server (it's git-ignored, so it won't come from GitHub).

If no key is configured, the rest of the app works fine — the assistant will just show a setup message when opened.

### 5. Email (optional, recommended)

Quotations can be emailed directly to customers from the "Email to Customer" button. By default this uses PHP's built-in `mail()`, which works out of the box but can be inconsistent with deliverability. For reliable sending, connect a real mailbox instead:

1. In cPanel, set up a mailbox (e.g. `quotes@yourdomain.co.za`) under Email Accounts.
2. Click **Connect Devices** next to it to see the exact Outgoing Server host, port, and encryption type.
3. Copy `config/mail.local.example.php` to `config/mail.local.php` and fill in those real details.
4. Upload `config/mail.local.php` to your server (it's git-ignored, so it won't come from GitHub).

If `config/mail.local.php` isn't present, the app automatically falls back to basic `mail()`.

## Project structure

```
config/         Database connection and credentials
customers/      Customer CRUD pages
items/          Product catalogue + quotation line-item management
create/         New quotation form
view/           Quotation list + printable quotation view
assets/         Logo and static assets
dashboard.php   Main landing page after login
login.php       Login screen
authenticate.php  Login form handler
```

## Security notes

- Real database credentials live only in `config/db.local.php`, which is excluded from version control via `.gitignore`. Never commit real credentials to this repository.
- If this repository is public, review all files before pushing to make sure no other secrets have been added over time.
