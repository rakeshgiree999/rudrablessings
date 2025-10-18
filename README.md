# RudraBlessings Storefront

RudraBlessings is a PHP/MySQL commerce platform built for the RudraBlessings spiritual and wellness shop. It delivers a data-driven storefront, customer accounts, checkout flows, and an admin portal that keeps catalogue and content updates in the hands of the team instead of code.

---

## 1. Architecture at a Glance

| Layer | Responsibilities | Key Directories |
|------|------------------|-----------------|
| Presentation | Server-rendered PHP templates plus progressive enhancement (`js/main.js`, `js/pages.js`). | `/` top-level `.php` pages, `/css`, `/js` |
| Business Logic | Session bootstrap, security enforcement, catalogue/cart/auth helpers, mailer. | `includes/` |
| Persistence | MySQL schema seeded with products, navigation, content blocks, contact data. | `database/schema.sql`, `config/` |
| Admin | Back-office dashboards, product & order management, contact content CMS. | `admin/` |
| APIs | JSON endpoints consumed by front-end scripts for cart & wishlist actions. | `api/` |

Supporting assets live in `media/` (product imagery, contact map preview) and `scripts/` (utility CLI scripts such as `setup_admin.php`). Composer is not required; PHPMailer ships inside `vendor/phpmailer/`.

---

## 2. Requirements

- **PHP 8.1+** with extensions: `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `gd` (JPEG/PNG/WebP support).
- **MySQL 8.0+** (5.7 compatible) with privileges to create schema objects.
- **Web server**: Apache/Nginx or PHP’s built-in server for development. `.htaccess` targets Apache.
- **OpenSSL & mkcert (optional)** for trusted HTTPS certificates during local development.

> **Enable GD & Fileinfo (XAMPP example)**  
> Edit `C:\xampp\php\php.ini` and ensure:
> ```ini
> extension=gd
> extension=fileinfo
> ```
> Restart Apache/PHP-FPM and confirm via `phpinfo()` that GD lists **JPEG Support => enabled**. Without these extensions image uploads and MIME checks fail.

---

## 3. Installation Guide

### 3.1 Clone the repository
```bash
git clone <repo-url> rudraBlessings
cd rudraBlessings
```

### 3.2 Configure environment
Create `.env` alongside `config/` and `database/`:
```ini
APP_URL=https://localhost/rudraBlessings
APP_FORCE_HTTPS=1

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=rudrablessings
DB_USER=root
DB_PASS=secret

SMTP_HOST=
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_FROM_EMAIL=no-reply@rudrablessings.com
SMTP_FROM_NAME=RudraBlessings
SMTP_TO_EMAIL=support@rudrablessings.com

STRIPE_PUBLIC_KEY=pk_test_xxx
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_DEFAULT_CURRENCY=aud
```
- Leave `SMTP_HOST` blank for local development. The mailer will store messages but skip outbound delivery.
- Set `APP_FORCE_HTTPS=force` if you want to enforce HTTPS even for `localhost` requests.

### 3.3 Import the database seed
```bash
mysql -u <user> -p -e "CREATE DATABASE IF NOT EXISTS rudrablessings CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u <user> -p rudrablessings < database/schema.sql
```
The seed creates all tables plus initial data: products, categories, menus, CMS blocks, users, and contact-page content (`contact_stats`, `contact_channels`, `contact_highlights`, `contact_faqs`). Re-run the second command to reset local data.

### 3.4 Provision an admin user
```bash
php scripts/setup_admin.php
```
This guarantees an admin exists (`admin@rudrablessings.com` / `admin`). Log in immediately and change the password via `/admin/users.php`.

### 3.5 Serve the app
- **PHP built-in server (dev)**  
  ```bash
  php -S localhost:8000
  ```  
  Visit `https://localhost:8000/` for the storefront and `https://localhost:8000/admin/` for the admin UI (create a self-signed cert).
- **Apache/Nginx** – point the document root at the repository root. `.htaccess` already enforces HTTPS and security headers.

### 3.6 Trusted HTTPS locally (mkcert)
1. Install [mkcert](https://github.com/FiloSottile/mkcert).
2. From an elevated shell:
   ```powershell
   mkcert -install
   mkcert -cert-file "C:\xampp\apache\conf\ssl.crt\localhost.pem" -key-file "C:\xampp\apache\conf\ssl.key\localhost-key.pem" localhost 127.0.0.1 ::1
   ```
3. Update `apache/conf/extra/httpd-ssl.conf` to reference the generated certificate and key.
4. Restart Apache and ensure the mkcert root is trusted on Windows. Browsers should now show a secure padlock for `https://localhost/...`.

---

## 4. Admin & Content Management

### 4.1 Access
- Admin home: `https://<host>/rudraBlessings/admin/`
- Default credentials after running `setup_admin.php`: `admin@rudrablessings.com` / `admin`

### 4.2 Contact Page CMS (`admin/contact-content.php`)
Manages four tables rendered on `contact.php`:
1. **Hero stats** (`contact_stats`) – value/label pairs shown beneath the hero card.
2. **Support channels** (`contact_channels`) – icon (envelope/phone/chat/calendar), title, description, CTA label/URL.
3. **Highlights** (`contact_highlights`) – partnership opportunities section.
4. **FAQs** (`contact_faqs`) – collapsible questions and answers.

Each form supports create, update, delete with CSRF protection. Because storefront templates read directly from the tables, updates are immediate—no PHP fallbacks remain.

### 4.3 Site Settings (`admin/settings.php`)
Controls:
- Brand name & tagline (`brand.*` settings).
- Support email, phone, address, hours (`support.*`).
- Contact hero title/subtitle (`contact.hero.*`).
- Map embed URL (`contact.map_embed`) and social links.

> **Map behaviour:**  
> `contact.php` prefers the static preview image at `media/maps/map-vit.png`. If a directions URL is set (`contact.map_link`), the image links to it; otherwise it auto-generates a Google Maps search based on the support address. Keep the image in sync with any rebranding.

### 4.4 Catalogue & Orders
Existing admin pages (`products.php`, `orders.php`, `promos.php`, `reports.php`, etc.) continue to manage inventory, promotions, and reporting through helpers in `includes/`.

### 4.5 User Accounts
`admin/users.php` lists customers and allows password resets, soft activation, and admin promotion.

---

## 5. Storefront Behaviour

- **Navigation & copy**: pulled from `menu_items`, `content_blocks`, and `site_settings` via `includes/site.php` helpers.
- **Cart & wishlist**: AJAX endpoints at `/api/cart.php` and `/api/wishlist.php` rely on CSRF tokens established in `includes/app.php`.
- **Checkout**: Uses Stripe Checkout (sandbox). Update `.env` with live keys and configure webhooks before production launch.
- **Contact form**: Validates inputs, stores submissions in `contact_messages`, and attempts to send via `includes/mailer.php`. With SMTP disabled, the mailer records a `queued`/`failed` status but avoids network calls.
- **Security**: `.htaccess` applies HSTS (`max-age=31536000; includeSubDomains; preload`), CSP, and other hardened headers. `includes/security.php` enforces HTTPS redirects based on `APP_FORCE_HTTPS`.

---

## 6. Maintenance Checklist

| Task | Steps |
|------|-------|
| Reset database | Re-run the import in section 3.3. |
| Update contact info, hero copy, social links | Use `admin/settings.php`. |
| Edit contact stats/channels/highlights/FAQs | Use `admin/contact-content.php`. |
| Update map preview | Replace `media/maps/map-vit.png` and confirm `contact.map_link`. |
| Provision admin account | Run `php scripts/setup_admin.php` or create via `admin/users.php`. |
| Update seeded defaults | After changing data in MySQL, export the relevant tables into `database/schema.sql` to keep source control aligned. |
| Rotate credentials | Update `.env`, restart PHP/Apache to apply. |

Keep regular backups of both the database and the `media/` directory to preserve product assets and contact imagery.

---

## 7. Troubleshooting

| Symptom | Likely Cause & Fix |
|---------|--------------------|
| Browser marks HTTPS as “Not secure” | Trust the mkcert root CA (or install a valid certificate) and ensure you visit the `https://` origin defined in `.env`. |
| Contact map shows “Content blocked” | Use the static preview image. Google Maps embed URLs must live inside iframes; the code already handles this fall-back automatically. |
| Mail not sending | Populate SMTP credentials in `.env`. Without `SMTP_HOST`, the app stores contact messages but skips SMTP to protect against accidental live sends. |
| Image uploads failing | Verify PHP extensions (GD, Fileinfo) and file permissions on `media/`. |
| Redirect loops locally | Temporarily set `APP_FORCE_HTTPS=0` to diagnose proxy or port forwarding setup. |

---

## 8. Roadmap Ideas

- Reintroduce automated tests (removed per deployment requirements).
- Add Stripe webhook handlers and fulfilment automation for production.
- Implement granular admin roles and activity logging.
- Extend CMS tooling to other content blocks (home page, blog hero, etc.).
- Provide REST APIs for orders and customer management to integrate with external systems.

---

## 9. Attribution

- Application code & content © RudraBlessings.
- PHPMailer (bundled in `vendor/phpmailer/`) is open source under the LGPL/MIT license.
- mkcert instructions adapted from the official project documentation.

For deployment assistance or feature work, reach out to the RudraBlessings development team. Enjoy building and maintaining the experience! 
