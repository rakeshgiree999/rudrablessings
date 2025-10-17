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
require __DIR__ . '/../includes/cart.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/site.php';

function rb_json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function rb_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST ?: [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$pdo = rb_db();

$cartInfo = rb_cart_resolve($pdo);
$cartId = $cartInfo['id'];

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $csrfToken = rb_csrf_ensure_cookie();
    header('X-CSRF-Token: ' . $csrfToken);
    $summary = rb_cart_summary($pdo, $cartId);
    rb_json_response([
        'items' => $summary['items'],
        'subtotal' => $summary['subtotal'],
        'shipping' => $summary['shipping'],
        'tax' => $summary['tax'],
        'total' => $summary['total'],
        'count' => $summary['count'],
    ]);
}


$body = rb_json_input();
$headerToken = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? trim((string)$_SERVER['HTTP_X_CSRF_TOKEN']) : '';
$bodyToken = isset($body['csrf_token']) && is_string($body['csrf_token']) ? trim($body['csrf_token']) : '';
$token = $headerToken !== '' ? $headerToken : $bodyToken;
$cookieName = rb_csrf_cookie_name();
$cookieToken = isset($_COOKIE[$cookieName]) ? trim((string)$_COOKIE[$cookieName]) : '';

if ($token === '' || $cookieToken === '' || !hash_equals($cookieToken, $token) || !rb_csrf_validate($token, false)) {
    $csrfToken = rb_csrf_ensure_cookie(true);
    header('X-CSRF-Token: ' . $csrfToken);
    rb_json_response(['error' => 'Invalid CSRF token. Please refresh and try again.'], 419);
}

$newToken = rb_csrf_ensure_cookie(true);
header('X-CSRF-Token: ' . $newToken);

$action = $body['action'] ?? 'add';

switch ($action) {
    case 'add':
        $productId = (int)($body['product_id'] ?? 0);
        $quantity = (int)($body['quantity'] ?? 1);
        $result = rb_cart_add_item($pdo, $cartId, $productId, $quantity);
        if (!$result['ok']) {
            rb_json_response(['error' => $result['error'] ?? 'Unable to add item.'], 400);
        }
        break;

    case 'update':
        $productId = (int)($body['product_id'] ?? 0);
        $quantity = (int)($body['quantity'] ?? 1);
        rb_cart_update_quantity($pdo, $cartId, $productId, $quantity);
        break;

    case 'remove':
        $productId = (int)($body['product_id'] ?? 0);
        rb_cart_remove_item($pdo, $cartId, $productId);
        break;

    case 'clear':
        rb_cart_clear($pdo, $cartId);
        break;

    default:
        rb_json_response(['error' => 'Unsupported action.'], 400);
}

$summary = rb_cart_summary($pdo, $cartId);
rb_json_response([
    'status' => 'ok',
    'items' => $summary['items'],
    'subtotal' => $summary['subtotal'],
    'shipping' => $summary['shipping'],
    'tax' => $summary['tax'],
    'total' => $summary['total'],
    'count' => $summary['count'],
]);

