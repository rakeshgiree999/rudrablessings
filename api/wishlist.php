<?php
declare(strict_types=1);

header('Content-Type: application/json');

require __DIR__ . '/../config/bootstrap.php';
if (!defined('RB_ENV_LOADED')) {
    rb_load_env(__DIR__ . '/../.env');
    define('RB_ENV_LOADED', true);
}
require __DIR__ . '/../includes/session.php';
rb_session_boot();
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/account.php';
require __DIR__ . '/../includes/site.php';

function rb_json(array $data, int $code = 200): void
{
    if (defined('RB_API_TEST_MODE') && RB_API_TEST_MODE && class_exists('RbApiTestResponse')) {
        throw new RbApiTestResponse($data, $code);
    }

    http_response_code($code);
    echo json_encode($data);
    exit;
}

function rb_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST ?: [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$pdo = rb_db();
$user = rb_auth_current_user($pdo);
if (!$user) {
    rb_json(['error' => 'Authentication required.'], 401);
}

$userId = (int)$user['id'];
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $token = rb_csrf_ensure_cookie();
    header('X-CSRF-Token: ' . $token);
}

if ($method === 'GET') {
    $items = rb_account_wishlist_items($pdo, $userId);
    $ids = [];
    foreach ($items as $item) {
        $ids[] = (int)$item['product_id'];
    }
    rb_json([
        'items' => $items,
        'product_ids' => $ids,
        'count' => count($items),
    ]);
}

$body = rb_input();
$headerToken = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? trim((string)$_SERVER['HTTP_X_CSRF_TOKEN']) : '';
$bodyToken = isset($body['csrf_token']) && is_string($body['csrf_token']) ? trim($body['csrf_token']) : '';
$token = $headerToken !== '' ? $headerToken : $bodyToken;
$cookieName = rb_csrf_cookie_name();
$cookieToken = isset($_COOKIE[$cookieName]) ? trim((string)$_COOKIE[$cookieName]) : '';

if ($token === '' || $cookieToken === '' || !hash_equals($cookieToken, $token) || !rb_csrf_validate($token, false)) {
    $refresh = rb_csrf_ensure_cookie(true);
    header('X-CSRF-Token: ' . $refresh);
    rb_json(['error' => 'Invalid CSRF token. Please refresh and try again.'], 419);
}

$refresh = rb_csrf_ensure_cookie(true);
header('X-CSRF-Token: ' . $refresh);

$action = $body['action'] ?? 'add';
$productId = (int)($body['product_id'] ?? 0);

if ($productId <= 0) {
    rb_json(['error' => 'Invalid product.'], 400);
}

if ($action === 'add') {
    $ok = rb_account_add_wishlist_item($pdo, $userId, $productId);
    if (!$ok) {
        rb_json(['error' => 'Unable to add item.'], 400);
    }
    $items = rb_account_wishlist_items($pdo, $userId);
    rb_json(['status' => 'added', 'count' => count($items)]);
} elseif ($action === 'remove') {
    rb_account_remove_wishlist_item($pdo, $userId, $productId);
    $items = rb_account_wishlist_items($pdo, $userId);
    rb_json(['status' => 'removed', 'count' => count($items)]);
}

rb_json(['error' => 'Unsupported action.'], 400);

