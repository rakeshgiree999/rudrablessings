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
require __DIR__ . '/../includes/site.php';

function rb_catalog_response(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$pdo = rb_db();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
$limit = max(1, min($limit, 500));

$options = [
    'limit' => $limit,
    'sort' => 'newest',
];

$category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
if ($category !== '') {
    $options['categorySlugs'] = [$category];
}

$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
if ($search !== '') {
    $options['search'] = $search;
}

$products = rb_products($pdo, $options);

$payload = [];
foreach ($products as $product) {
    $payload[] = [
        'id' => (int)($product['id'] ?? 0),
        'slug' => (string)($product['slug'] ?? ''),
        'name' => (string)($product['name'] ?? ''),
        'price' => (float)($product['price'] ?? 0.0),
        'tag' => (string)($product['category_slug'] ?? ''),
        'category_name' => (string)($product['category_name'] ?? ''),
        'image_path' => (string)($product['image_path'] ?? ''),
        'short_description' => (string)($product['short_description'] ?? ''),
        'description' => (string)($product['description'] ?? ''),
    ];
}

$token = rb_csrf_ensure_cookie();
header('X-CSRF-Token: ' . $token);

rb_catalog_response([
    'products' => $payload,
    'count' => count($payload),
]);
