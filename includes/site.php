<?php
declare(strict_types=1);

if (!function_exists('rb_settings')) {
    /**
     * Returns site settings keyed by setting_key.
     *
     * @return array<string, string>
     */
    function rb_settings(PDO $pdo): array
    {
        static $settings = null;
        if ($settings !== null) {
            return $settings;
        }
        $settings = [];
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
        foreach ($stmt as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
}

if (!function_exists('rb_asset')) {
    function rb_asset(string $path): string
    {
        $version = defined('RB_ASSET_VERSION') ? RB_ASSET_VERSION : '1';
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . 'v=' . rawurlencode($version);
    }
}

if (!function_exists('rb_url')) {
    /**
     * Build an absolute URL for use in emails and redirects.
     */
    function rb_url(string $path = ''): string
    {
        $base = trim((string)($_ENV['APP_URL'] ?? ''));
        if ($base !== '') {
            $base = rtrim($base, '/');
        } else {
            $forceHttps = filter_var($_ENV['APP_FORCE_HTTPS'] ?? $_SERVER['APP_FORCE_HTTPS'] ?? '1', FILTER_VALIDATE_BOOLEAN);
            $scheme = 'http';
            if ($forceHttps) {
                $scheme = 'https';
            } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $scheme = 'https';
            }
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            if ($dir === '.' || $dir === '/') {
                $dir = '';
            }
            $base = $scheme . '://' . $host . ($dir !== '' ? $dir : '');
        }
        $path = trim($path);
        if ($path === '') {
            return $base . '/';
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('rb_menu')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function rb_menu(PDO $pdo, string $menuKey = 'primary'): array
    {
        static $menuCache = [];

        if (isset($menuCache[$menuKey])) {
            return $menuCache[$menuKey];
        }

        $stmt = $pdo->prepare('SELECT label, url, sort_order FROM menu_items WHERE menu_key = :key AND is_active = 1 ORDER BY sort_order ASC');
        $stmt->execute(['key' => $menuKey]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            if (!isset($item['url']) || !is_string($item['url'])) {
                continue;
            }
            $item['url'] = preg_replace('/\\.html($|\\?)/i', '.php$1', $item['url']);
        }
        unset($item);

        $menuCache[$menuKey] = $items;
        return $items;
    }
}

if (!function_exists('rb_block')) {
    function rb_block(PDO $pdo, string $pageSlug, string $blockKey): ?array
    {
        $stmt = $pdo->prepare('SELECT title, subtitle, body, media_path, cta_label, cta_url FROM content_blocks WHERE page_slug = :page AND block_key = :block LIMIT 1');
        $stmt->execute([
            'page' => $pageSlug,
            'block' => $blockKey,
        ]);
        $block = $stmt->fetch();
        return $block ?: null;
    }
}

if (!function_exists('rb_blocks')) {
    /**
     * Fetch all blocks for a page keyed by block_key while preserving insert order.
     *
     * @return array<string, array<string, mixed>>
     */
    function rb_blocks(PDO $pdo, string $pageSlug): array
    {
        $stmt = $pdo->prepare('SELECT block_key, title, subtitle, body, media_path, cta_label, cta_url, sort_order FROM content_blocks WHERE page_slug = :page ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['page' => $pageSlug]);
        $blocks = [];
        foreach ($stmt->fetchAll() ?: [] as $block) {
            $key = (string)($block['block_key'] ?? '');
            if ($key === '') {
                $key = uniqid('block_', true);
            }
            $blocks[$key] = $block;
        }
        return $blocks;
    }
}

if (!function_exists('rb_product_media_paths')) {
    /**
     * Locate product media files on disk based on category and product slugs.
     *
     * @return array<int, array{image_path:string, alt_text:string}>
     */
    function rb_product_media_paths(string $categorySlug, string $productSlug, string $productName = ''): array
    {
        $categorySlug = trim($categorySlug);
        $productSlug = trim($productSlug);
        if ($categorySlug === '' || $productSlug === '') {
            return [];
        }

        static $root = null;
        static $cache = [];
        if ($root === null) {
            $root = dirname(__DIR__);
        }
        $cacheKey = $categorySlug . '/' . $productSlug;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $directory = $root . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $categorySlug . DIRECTORY_SEPARATOR . $productSlug;
        if (!is_dir($directory)) {
            return $cache[$cacheKey] = [];
        }

        $pattern = $directory . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,webp,gif}';
        $files = [];
        if (defined('GLOB_BRACE')) {
            $files = glob($pattern, GLOB_BRACE) ?: [];
        }
        if (!$files) {
            $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            foreach ($extensions as $ext) {
                $files = array_merge($files, glob($directory . DIRECTORY_SEPARATOR . '*.' . $ext) ?: []);
            }
        }
        $files = array_values(array_unique($files));
        if (!$files) {
            return [];
        }
        natsort($files);
        $files = array_values($files);

        $nameForAlt = $productName !== '' ? $productName : str_replace('-', ' ', $productSlug);
        $images = [];
        foreach ($files as $index => $filePath) {
            if (!is_file($filePath)) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($filePath, strlen($root) + 1));
            if ($relative === '') {
                continue;
            }
            $alt = $nameForAlt;
            if ($index > 0) {
                $alt .= ' image ' . ($index + 1);
            }
            $images[] = [
                'image_path' => $relative,
                'alt_text' => $alt,
            ];
        }

        return $cache[$cacheKey] = $images;
    }
}

if (!function_exists('rb_blog_posts')) {
    /**
     * Fetch published blog posts ordered by most recent.
     *
     * @return array<int, array<string, mixed>>
     */
    function rb_blog_posts(PDO $pdo, int $limit = 6, int $offset = 0): array
    {
        $stmt = $pdo->prepare('SELECT id, slug, title, excerpt, hero_image, author_name, author_avatar, author_bio, published_at FROM blog_posts WHERE status = :status ORDER BY published_at DESC, id DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':status', 'published', PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('rb_blog_post')) {
    /**
     * Fetch a single published blog post by slug or id.
     *
     * @param array{id?:int,slug?:string} $criteria
     */
    function rb_blog_post(PDO $pdo, array $criteria): ?array
    {
        $query = 'SELECT id, slug, title, excerpt, body, hero_image, author_name, author_avatar, author_bio, published_at, created_at FROM blog_posts WHERE status = :status';
        $params = [':status' => 'published'];
        if (!empty($criteria['slug'])) {
            $query .= ' AND slug = :slug';
            $params[':slug'] = (string)$criteria['slug'];
        } elseif (!empty($criteria['id'])) {
            $query .= ' AND id = :id';
            $params[':id'] = (int)$criteria['id'];
        } else {
            return null;
        }
        $query .= ' LIMIT 1';
        $stmt = $pdo->prepare($query);
        foreach ($params as $name => $value) {
            $type = str_ends_with($name, 'id') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        return $post ?: null;
    }
}

if (!function_exists('rb_blog_related_posts')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function rb_blog_related_posts(PDO $pdo, int $postId, int $limit = 3): array
    {
        $stmt = $pdo->prepare('SELECT id, slug, title, excerpt, hero_image, author_name, published_at FROM blog_posts WHERE status = :status AND id <> :id ORDER BY published_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':status', 'published', PDO::PARAM_STR);
        $stmt->bindValue(':id', $postId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('rb_categories')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function rb_categories(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT id, slug, name, description, image_path FROM categories WHERE is_active = 1 ORDER BY name ASC');
        return $stmt->fetchAll() ?: [];
    }
}

if (!function_exists('rb_products')) {
    /**
     * Fetch products according to filters.
     *
     * Supported options: categorySlugs[], search, priceMax, sort, limit, offset.
     *
     * @param array<string,mixed> $options
     * @return array<int, array<string, mixed>>
     */
    function rb_products(PDO $pdo, array $options = []): array
    {
        $defaults = [
            'categorySlugs' => [],
            'search' => null,
            'priceMax' => null,
            'sort' => 'default',
            'limit' => 12,
            'offset' => 0,
            'featured' => false,
            'new_arrival' => false,
        ];
        $opts = array_merge($defaults, $options);

        $query = "
            SELECT
                p.id,
                p.slug,
                p.name,
                p.short_description,
                p.description,
                p.price,
                p.created_at,
                (
                    SELECT c.name
                    FROM categories c
                    INNER JOIN product_categories pc ON pc.category_id = c.id
                    WHERE pc.product_id = p.id
                    ORDER BY c.name ASC
                    LIMIT 1
                ) AS category_name,
                (
                    SELECT c.slug
                    FROM categories c
                    INNER JOIN product_categories pc ON pc.category_id = c.id
                    WHERE pc.product_id = p.id
                    ORDER BY c.name ASC
                    LIMIT 1
                ) AS category_slug,
                (
                    SELECT pi.image_path
                    FROM product_images pi
                    WHERE pi.product_id = p.id
                    ORDER BY pi.sort_order ASC, pi.id ASC
                    LIMIT 1
                ) AS image_path,
                (
                    SELECT pi.alt_text
                    FROM product_images pi
                    WHERE pi.product_id = p.id
                    ORDER BY pi.sort_order ASC, pi.id ASC
                    LIMIT 1
                ) AS image_alt
            FROM products p
            WHERE p.status = 'active'
        ";

        $params = [];

        if (!empty($opts['featured'])) {
            $query .= " AND p.is_featured = 1";
        }

        if (!empty($opts['new_arrival'])) {
            $query .= " AND p.is_new_arrival = 1";
        }

        if (!empty($opts['categorySlugs']) && is_array($opts['categorySlugs'])) {
            $slugs = array_values(array_filter($opts['categorySlugs'], static fn ($slug) => is_string($slug) && $slug !== ''));
            if ($slugs) {
                $placeholders = implode(',', array_fill(0, count($slugs), '?'));
                $query .= "
                AND EXISTS (
                    SELECT 1
                    FROM product_categories pc
                    INNER JOIN categories c ON c.id = pc.category_id
                    WHERE pc.product_id = p.id AND c.slug IN ($placeholders)
                )
            ";
                $params = array_merge($params, $slugs);
            }
        }

        if (!empty($opts['search'])) {
            $searchTerm = '%' . mb_strtolower((string)$opts['search']) . '%';
            $query .= " AND (LOWER(p.name) LIKE ? OR LOWER(p.short_description) LIKE ? OR LOWER(p.description) LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($opts['priceMax'])) {
            $query .= " AND p.price <= ?";
            $params[] = (float)$opts['priceMax'];
        }

        if (!empty($opts['new_arrival']) && ($opts['sort'] ?? '') === 'default') {
            $opts['sort'] = 'newest';
        }

        $order = 'p.id ASC';
        switch ($opts['sort']) {
            case 'price-asc':
                $order = 'p.price ASC';
                break;
            case 'price-desc':
                $order = 'p.price DESC';
                break;
            case 'name-asc':
                $order = 'p.name ASC';
                break;
            case 'name-desc':
                $order = 'p.name DESC';
                break;
            case 'newest':
                $order = 'p.created_at DESC';
                break;
        }
        $limit = max(1, (int)$opts['limit']);
        $offset = max(0, (int)$opts['offset']);
        $query .= " ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll() ?: [];
        foreach ($products as &$product) {
            $media = null;
            if (empty($product['image_path'])) {
                $media = rb_product_media_paths($product['category_slug'] ?? '', $product['slug'] ?? '', $product['name'] ?? '');
                if ($media) {
                    $product['image_path'] = $media[0]['image_path'];
                    $product['image_alt'] = $media[0]['alt_text'];
                }
            }
            if (empty($product['image_path'])) {
                $product['image_path'] = 'media/logo.png';
                $product['image_alt'] = $product['name'] ?? 'Product image';
            }
        }
        unset($product);
        return $products;
    }
}

if (!function_exists('rb_products_count')) {
    /**
     * Count products using same filters as rb_products.
     *
     * @param array<string,mixed> $options
     */
    function rb_products_count(PDO $pdo, array $options = []): int
    {
        $defaults = [
            'categorySlugs' => [],
            'search' => null,
            'priceMax' => null,
            'featured' => false,
            'new_arrival' => false,
        ];
        $opts = array_merge($defaults, $options);

        $query = "SELECT COUNT(*) FROM products p WHERE p.status = 'active'";
        $params = [];

        if (!empty($opts['featured'])) {
            $query .= " AND p.is_featured = 1";
        }

        if (!empty($opts['new_arrival'])) {
            $query .= " AND p.is_new_arrival = 1";
        }

        if (!empty($opts['categorySlugs']) && is_array($opts['categorySlugs'])) {
            $slugs = array_values(array_filter($opts['categorySlugs'], static fn ($slug) => is_string($slug) && $slug !== ''));
            if ($slugs) {
                $placeholders = implode(',', array_fill(0, count($slugs), '?'));
                $query .= "
                AND EXISTS (
                    SELECT 1
                    FROM product_categories pc
                    INNER JOIN categories c ON c.id = pc.category_id
                    WHERE pc.product_id = p.id AND c.slug IN ($placeholders)
                )
            ";
                $params = array_merge($params, $slugs);
            }
        }

        if (!empty($opts['search'])) {
            $searchTerm = '%' . mb_strtolower((string)$opts['search']) . '%';
            $query .= " AND (LOWER(p.name) LIKE ? OR LOWER(p.short_description) LIKE ? OR LOWER(p.description) LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($opts['priceMax'])) {
            $query .= " AND p.price <= ?";
            $params[] = (float)$opts['priceMax'];
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('rb_product')) {
    function rb_product(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                p.*,
                c.id AS category_id,
                c.name AS category_name,
                c.slug AS category_slug
            FROM products p
            LEFT JOIN product_categories pc ON pc.product_id = p.id
            LEFT JOIN categories c ON c.id = pc.category_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }
}

if (!function_exists('rb_product_categories')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function rb_product_categories(PDO $pdo, int $productId): array
    {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.slug, c.description
            FROM categories c
            INNER JOIN product_categories pc ON pc.category_id = c.id
            WHERE pc.product_id = :id
            ORDER BY c.name ASC
        ");
        $stmt->execute(['id' => $productId]);
        return $stmt->fetchAll() ?: [];
    }
}

if (!function_exists('rb_product_images')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function rb_product_images(PDO $pdo, int $productId): array
    {
        $metaStmt = $pdo->prepare("
            SELECT
                p.name,
                p.slug,
                c.slug AS category_slug
            FROM products p
            LEFT JOIN product_categories pc ON pc.product_id = p.id
            LEFT JOIN categories c ON c.id = pc.category_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $metaStmt->execute(['id' => $productId]);
        $meta = $metaStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$meta) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT image_path, alt_text
            FROM product_images
            WHERE product_id = :id
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute(['id' => $productId]);
        $dbImages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($dbImages) {
            foreach ($dbImages as &$image) {
                if (($image['alt_text'] ?? '') === '' && !empty($meta['name'])) {
                    $image['alt_text'] = (string)$meta['name'];
                }
            }
            unset($image);
            return $dbImages;
        }

        $media = rb_product_media_paths((string)($meta['category_slug'] ?? ''), (string)($meta['slug'] ?? ''), (string)($meta['name'] ?? ''));
        if ($media) {
            return $media;
        }

        return [[
            'image_path' => 'media/logo.png',
            'alt_text' => (string)($meta['name'] ?? 'Product image'),
        ]];
    }
}

if (!function_exists('rb_related_products')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function rb_related_products(PDO $pdo, int $productId, ?string $categorySlug, int $limit = 4): array
    {
        $params = ['productId' => $productId];
        $query = "
            SELECT
                p.id,
                p.slug,
                p.name,
                p.price,
                (
                    SELECT c.slug
                    FROM categories c
                    INNER JOIN product_categories pc ON pc.category_id = c.id
                    WHERE pc.product_id = p.id
                    ORDER BY c.name ASC
                    LIMIT 1
                ) AS category_slug,
                (
                    SELECT pi.image_path FROM product_images pi
                    WHERE pi.product_id = p.id
                    ORDER BY pi.sort_order ASC, pi.id ASC
                    LIMIT 1
                ) AS image_path
            FROM products p
            WHERE p.status = 'active' AND p.id <> :productId
        ";
        if ($categorySlug) {
            $query .= "
                AND EXISTS (
                    SELECT 1 FROM product_categories pc
                    INNER JOIN categories c ON c.id = pc.category_id
                    WHERE pc.product_id = p.id AND c.slug = :slug
                )
            ";
            $params['slug'] = $categorySlug;
        }
        $limit = max(1, (int)$limit);
        $query .= " ORDER BY p.created_at DESC LIMIT {$limit}";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $related = $stmt->fetchAll() ?: [];
        foreach ($related as &$product) {
            if (empty($product['image_path'])) {
                $media = rb_product_media_paths($product['category_slug'] ?? '', $product['slug'] ?? '', $product['name'] ?? '');
                if ($media) {
                    $product['image_path'] = $media[0]['image_path'];
                }
            }
            if (empty($product['image_path'])) {
                $product['image_path'] = 'media/logo.png';
            }
        }
        unset($product);
        $collected = [];
        $seen = [$productId];
        foreach ($related as $item) {
            $pid = (int)($item['id'] ?? 0);
            if ($pid === 0 || in_array($pid, $seen, true)) {
                continue;
            }
            $collected[] = $item;
            $seen[] = $pid;
            if (count($collected) >= $limit) {
                return $collected;
            }
        }

        $tryFill = function (array $options) use ($pdo, &$collected, &$seen, $limit): void {
            if (count($collected) >= $limit) {
                return;
            }
            $options['limit'] = max($limit * 2, 8);
            $extra = rb_products($pdo, $options);
            foreach ($extra as $product) {
                $pid = (int)($product['id'] ?? 0);
                if ($pid === 0 || in_array($pid, $seen, true)) {
                    continue;
                }
                $collected[] = $product;
                $seen[] = $pid;
                if (count($collected) >= $limit) {
                    break;
                }
            }
        };

        if ($categorySlug) {
            $tryFill(['categorySlugs' => [$categorySlug], 'sort' => 'newest']);
        }
        $tryFill(['sort' => 'newest']);

        return array_slice($collected, 0, $limit);
    }
}

if (!function_exists('rb_format_price')) {
    function rb_format_price(float $price): string
    {
        return '$' . number_format($price, 2);
    }
}

if (!function_exists('rb_escape')) {
    function rb_escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rb_trusted_html')) {
    /**
     * Return trusted HTML content for output. Intended for CMS-managed fields.
     */
    function rb_trusted_html(?string $value): string
    {
        return $value !== null ? (string)$value : '';
    }
}

if (!function_exists('rb_format_date')) {
    /**
     * Format a stored date string into a human readable value.
     */
    function rb_format_date(?string $dateValue, string $format = 'd M Y'): ?string
    {
        if ($dateValue === null || $dateValue === '') {
            return null;
        }
        $date = date_create($dateValue);
        if ($date === false) {
            return null;
        }
        return $date->format($format);
    }
}

if (!function_exists('rb_user_display_name')) {
    /**
     * @param array<string, mixed>|null $user
     */
    function rb_user_display_name(?array $user): string
    {
        if (!$user) {
            return '';
        }
        $first = trim((string)($user['first_name'] ?? ''));
        $last = trim((string)($user['last_name'] ?? ''));
        $full = trim($first . ' ' . $last);
        if ($full !== '') {
            return $full;
        }
        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return 'Customer';
    }
}

if (!function_exists('rb_user_avatar')) {
    /**
     * Resolve avatar details for rendering in the header/profile components.
     *
     * @param array<string, mixed>|null $user
     * @return array{url:string, alt:string, label:string}
     */
    function rb_user_avatar(?array $user): array
    {
        $displayName = rb_user_display_name($user);
        $alt = $displayName !== '' ? 'Profile picture of ' . $displayName : 'Profile picture';

        $defaultRelative = 'media/profile-picture.svg';
        $candidate = '';
        if ($user && !empty($user['avatar_path']) && is_string($user['avatar_path'])) {
            $candidate = trim((string)$user['avatar_path']);
        }

        if ($candidate === '') {
            $candidate = $defaultRelative;
        }

        if (strncmp($candidate, 'data:', 5) === 0) {
            $finalUrl = $candidate;
        } elseif (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $candidate)) {
            $finalUrl = $candidate;
        } else {
            $normalized = ltrim($candidate, '/\\');
            $normalized = preg_replace('#[\\/]+#', '/', $normalized);
            $baseDir = realpath(dirname(__DIR__));
            $fullPath = $baseDir !== false ? $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized) : null;
            if (!$fullPath || !is_file($fullPath) || ($baseDir !== false && strpos(realpath($fullPath) ?: '', $baseDir) !== 0)) {
                $normalized = $defaultRelative;
            }
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $assetPrefix = strpos($scriptName, '/admin/') !== false ? '../' : '';
            $finalUrl = rb_asset($assetPrefix . $normalized);
        }

        return [
            'url' => $finalUrl,
            'alt' => $alt,
            'label' => $displayName !== '' ? $displayName : 'My Profile',
        ];
    }
}

if (!function_exists('rb_csrf_token')) {
    /**
     * Generate a one-time CSRF token and persist it in session storage.
     */
    function rb_csrf_token(): string
    {
        if (!isset($_SESSION['rb_csrf_tokens']) || !is_array($_SESSION['rb_csrf_tokens'])) {
            $_SESSION['rb_csrf_tokens'] = [];
        }

        // purge expired tokens (older than 1 hour)
        $now = time();
        foreach ($_SESSION['rb_csrf_tokens'] as $token => $issuedAt) {
            if (!is_int($issuedAt) || ($now - $issuedAt) > 3600) {
                unset($_SESSION['rb_csrf_tokens'][$token]);
            }
        }

        do {
            $token = bin2hex(random_bytes(32));
        } while (isset($_SESSION['rb_csrf_tokens'][$token]));

        $_SESSION['rb_csrf_tokens'][$token] = $now;

        // keep storage bounded
        if (count($_SESSION['rb_csrf_tokens']) > 50) {
            $_SESSION['rb_csrf_tokens'] = array_slice($_SESSION['rb_csrf_tokens'], -50, null, true);
        }

        return $token;
    }
}

if (!function_exists('rb_csrf_cookie_name')) {
    function rb_csrf_cookie_name(): string
    {
        $name = trim((string)($_ENV['CSRF_COOKIE_NAME'] ?? $_SERVER['CSRF_COOKIE_NAME'] ?? 'rb_csrf'));
        return $name !== '' ? $name : 'rb_csrf';
    }
}

if (!function_exists('rb_csrf_ensure_cookie')) {
    /**
     * Ensure a CSRF token cookie is present for front-end API requests.
     *
     * @param bool $renew Generate a fresh token regardless of current cookie state.
     */
    function rb_csrf_ensure_cookie(bool $renew = false): string
    {
        $cookieName = rb_csrf_cookie_name();
        $cookieToken = isset($_COOKIE[$cookieName]) ? (string)$_COOKIE[$cookieName] : '';

        if ($renew || $cookieToken === '' || !rb_csrf_validate($cookieToken, false)) {
            $token = rb_csrf_token();
        } else {
            $token = $cookieToken;
        }

        $sessionOptions = function_exists('rb_session_cookie_options') ? rb_session_cookie_options() : [
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'samesite' => 'Lax',
        ];

        setcookie($cookieName, $token, [
            'expires' => 0,
            'path' => '/',
            'domain' => function_exists('rb_session_cookie_domain') ? rb_session_cookie_domain() : '',
            'secure' => (bool)($sessionOptions['secure'] ?? false),
            'httponly' => false,
            'samesite' => $sessionOptions['samesite'] ?? 'Lax',
        ]);
        $_COOKIE[$cookieName] = $token;

        return $token;
    }
}

if (!function_exists('rb_csrf_input')) {
    /**
     * Return a hidden input field containing a CSRF token.
     */
    function rb_csrf_input(): string
    {
        $token = rb_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . rb_escape($token) . '">';
    }
}

if (!function_exists('rb_csrf_validate')) {
    /**
     * Validate a token submitted by the client.
     */
    function rb_csrf_validate(?string $token, bool $consume = true): bool
    {
        if (!isset($token) || !is_string($token) || $token === '') {
            return false;
        }
        if (!isset($_SESSION['rb_csrf_tokens']) || !is_array($_SESSION['rb_csrf_tokens'])) {
            return false;
        }
        if (!isset($_SESSION['rb_csrf_tokens'][$token])) {
            return false;
        }
        if ($consume) {
            unset($_SESSION['rb_csrf_tokens'][$token]);
        }
        return true;
    }
}

if (!function_exists('rb_csrf_validate_request')) {
    function rb_csrf_validate_request(string $fieldName = 'csrf_token'): bool
    {
        $value = $_POST[$fieldName] ?? '';
        return rb_csrf_validate(is_string($value) ? $value : '');
    }
}





