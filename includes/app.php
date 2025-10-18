<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (!defined('RB_ENV_LOADED')) {
    rb_load_env(dirname(__DIR__) . '/.env');
    define('RB_ENV_LOADED', true);
}

require_once __DIR__ . '/session.php';
rb_session_boot();
require_once __DIR__ . '/security.php';
rb_enforce_https();

ini_set('default_charset', 'UTF-8');
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Link: </favicon.ico>; rel="icon"; type="image/x-icon"', false);
    header('Link: </media/logo.png>; rel="icon"; type="image/png"', false);
    header('Link: </media/logo.png>; rel="apple-touch-icon"', false);
}

require_once __DIR__ . '/site.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/promos.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/mailer.php';

$rbCsrfToken = rb_csrf_ensure_cookie();

/** @var \PDO $pdo */
if (!isset($pdo) || !($pdo instanceof \PDO)) {
    $pdo = rb_db();
}

$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$brandTagline = $settings['brand.tagline'] ?? 'Divine Store';
$menuItems = rb_menu($pdo);
$footerBlock = rb_block($pdo, 'footer', 'legal');
$footerMenus = [
    [
        'title' => trim((string)($settings['footer.shop.title'] ?? 'Shop')),
        'items' => rb_menu($pdo, 'footer_shop'),
    ],
    [
        'title' => trim((string)($settings['footer.company.title'] ?? 'Company')),
        'items' => rb_menu($pdo, 'footer_company'),
    ],
    [
        'title' => trim((string)($settings['footer.legal.title'] ?? 'Legal')),
        'items' => rb_menu($pdo, 'footer_legal'),
    ],
];
$footerMenus = array_values(array_filter($footerMenus, static function (array $menu): bool {
    $title = trim((string)($menu['title'] ?? ''));
    $items = $menu['items'] ?? [];
    return $title !== '' || !empty($items);
}));

$currentUser = rb_auth_current_user($pdo);
$isLoggedIn = (bool)$currentUser;
$avatarMeta = rb_user_avatar($currentUser);
