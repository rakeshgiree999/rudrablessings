# RudraBlessings

RudraBlessings is a full-stack PHP and MySQL ecommerce application for the RudraBlessings spiritual and wellness store. The project replaces the former static prototype with a data-driven storefront, persistent carts, order processing, and a full-featured admin back office.

## Features

- **Storefront pages** - Homepage, catalogue (`shop.php`), product detail (`product.php`), blog, tracking, checkout, and account pages render directly from MySQL via helpers in `includes/site.php`.
- **Content management** - Site settings, navigation, and CMS blocks live in the database (`site_settings`, `menu_items`, `content_blocks`), making copy and menus editable without code changes.
- **Accounts & authentication** - Secure registration, login, logout, profile management, addresses, wishlists, and session handling powered by `includes/auth.php` and `includes/account.php`.
- **Cart, checkout, and orders** - Persistent carts merge between guests and logged-in users, checkout captures orders, and stock decrements through `includes/cart.php`, `checkout.php`, and `/api/cart.php`.
- **Wishlist & APIs** - REST-style JSON endpoints at `/api/cart.php` and `/api/wishlist.php` support the front-end JavaScript experience (`js/main.js`, `js/pages.js`).
- **Admin portal** - `/admin` provides a dashboard, product editor, orders, reports, and customer management with access control enforced in `admin/includes/admin_app.php`.
- **Database migrations** - `includes/upgrade.php` provides idempotent schema helpers. Run them on demand via `php scripts/migrate.php`.

## Project Layout

- `admin/` - Admin UI, dashboards, product and order management.
- `api/` - Cart and wishlist JSON endpoints.
- `config/` - Application bootstrap and PDO connection factory.
- `database/` - `schema.sql` with schema definition plus seed data for products, content, and sample accounts.
- `includes/` - Shared domain logic (app bootstrap, authentication, cart, site helpers, migrations).
- `js/` - Front-end interaction scripts for storefront pages.
- `media/` - Product imagery and shared graphics.
- `scripts/` - Utility scripts such as `setup_admin.php` for provisioning an admin user.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `mbstring`, and `json` extensions enabled.
- MySQL 8.0+ (5.7+ compatible) with a database user that can create schema objects.
- Composer is not required; the codebase relies on native PHP only.

## Setup

1. **Clone the repository**
   ```bash
   git clone <repo-url> rudraBlessings
   cd rudraBlessings
   ```

2. **Create configuration**
   Add a `.env` file at the project root (next to `config/` and `database/`) with database and Stripe sandbox credentials:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=rudrablessings
   DB_USER=root
   DB_PASS=secret
   STRIPE_SECRET_KEY=sk_test_xxx
   STRIPE_PUBLISHABLE_KEY=pk_test_xxx
   STRIPE_DEFAULT_CURRENCY=aud
   ```
   Replace the Stripe keys with the test credentials from your Stripe account (Developers → API keys). Checkout runs entirely in Stripe test mode and never charges real cards.

3. **Provision the database**
   ```bash
   mysql -u <user> -p -e "CREATE DATABASE IF NOT EXISTS rudrablessings CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u <user> -p rudrablessings < database/schema.sql
   ```
   The import creates all tables and seeds the content, products, blog entries, menus, and sample accounts.

4. **Seed or reset the admin account (recommended)**
   ```bash
   php scripts/setup_admin.php
   ```
   This script ensures an administrator exists with email `admin@rudrablessings.com` and password `admin` (prompting you to change it after first login). It is safe to rerun at any time.

5. **Serve the application**
   - With PHP's built-in server:
     ```bash
     php -S localhost:8000
     ```
     Then visit `http://localhost:8000/` for the storefront and `http://localhost:8000/admin/` for the admin panel.
   - Or configure Apache/Nginx to point the document root at the project directory. `.htaccess` is included for common Apache settings.

## Seeded Accounts

| Role     | Email                      | Notes                                                                 |
|----------|----------------------------|-----------------------------------------------------------------------|
| Admin    | `admin@rudrablessings.com` | Run `php scripts/setup_admin.php` to set the password to `admin`.     |
| Customer | `guest@example.com`        | Demo customer account seeded with sample data.                        |

Passwords are stored hashed; adjust or replace them via the admin UI once logged in.

## JSON APIs

- `GET /api/catalog.php` - Returns a lightweight product list (id, slug, price, category) for client-side experiences.
- `GET /api/cart.php` - Returns cart summary (`items`, `subtotal`, `shipping`, `tax`, `total`, `count`).
- `POST /api/cart.php` - Accepts JSON with `action` (`add`, `update`, `remove`, `clear`) plus `product_id` and optional `quantity`. Responds with the updated cart summary.
- `GET /api/wishlist.php` - Returns wishlist items for the current user/session.
- `POST /api/wishlist.php` - Toggle wishlist membership using `{ "action": "add"|"remove", "product_id": <id> }`.

Requests are session-aware and rely on the shared helpers in `includes/cart.php` and `includes/account.php` for persistence.

## Development Notes

- Include `require 'includes/app.php';` at the top of storefront and admin entry points to bootstrap sessions, environment variables, database connections, and navigation/content helpers.
- Run `php scripts/migrate.php` whenever you need to apply pending schema tweaks from `includes/upgrade.php`. The application no longer executes migrations automatically during normal requests.
- Front-end JavaScript (`js/main.js`, `js/pages.js`, `js/tracking.js`) expects the REST endpoints above and uses progressive enhancement alongside server-rendered HTML.
- Media assets live under `media/`; product detail pages auto-detect gallery images via `rb_product_media_paths()`.
- Checkout uses Stripe Checkout in sandbox mode via `includes/payments.php`. Successful returns are verified server-side before orders are created; configure `STRIPE_*` env vars and switch to live keys (and webhooks) when you go beyond local testing.

## Next Steps

- Add automated tests (unit or end-to-end) for authentication, cart flows, and admin CRUD.
- Extend the admin area with role management and content editing forms.
- Add Stripe webhooks/live keys to capture payments, send receipts, and reconcile fulfilment for production usage.

With the database-backed foundation in place, new features can be built by layering additional PHP templates or APIs on top of the existing helpers and schema.
