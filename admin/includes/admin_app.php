<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/app.php';

if (!$isLoggedIn || strtolower((string)($currentUser['role'] ?? 'customer')) !== 'admin') {
    $requested = $_SERVER['REQUEST_URI'] ?? '/admin/index.php';
    $redirect = urlencode($requested);
    header('Location: ../signin.php?redirect=' . $redirect);
    exit;
}

$adminNavItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'index.php', 'icon' => 'dashboard'],
    ['key' => 'products', 'label' => 'Products', 'href' => 'products.php', 'icon' => 'box'],
    ['key' => 'categories', 'label' => 'Categories', 'href' => 'categories.php', 'icon' => 'categories'],
    ['key' => 'orders', 'label' => 'Orders', 'href' => 'orders.php', 'icon' => 'orders'],
    ['key' => 'messages', 'label' => 'Messages', 'href' => 'messages.php', 'icon' => 'mail'],
    ['key' => 'blog', 'label' => 'Blog Posts', 'href' => 'blog.php', 'icon' => 'blog'],
    ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'reports'],
    ['key' => 'promos', 'label' => 'Promos', 'href' => 'promos.php', 'icon' => 'discount'],
    ['key' => 'users', 'label' => 'Customers', 'href' => 'users.php', 'icon' => 'users'],
    ['key' => 'contact_content', 'label' => 'Contact Content', 'href' => 'contact-content.php', 'icon' => 'contact'],
    ['key' => 'settings', 'label' => 'Settings', 'href' => 'settings.php', 'icon' => 'settings'],
];

$adminCurrent = $adminCurrent ?? 'dashboard';

function rb_admin_icon(string $name): string
{
    $icons = [
        'dashboard' => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>',
        'box' => '<path d="M4 7l8 4 8-4-8-4-8 4zm0 2v8l8 4 8-4V9l-8 4-8-4z"/>',
        'categories' => '<path d="M4 4h6v6H4V4zm0 10h6v6H4v-6zm10-10h6v6h-6V4zm0 10h6v6h-6v-6z"/>',
        'orders' => '<path d="M4 6h2l2 9h8l2-6H9.42l-.4-2H20V5H6.42l-.33-1.65A1 1 0 0 0 5.11 3H2v2h2l2.6 12.09A2 2 0 0 0 8.55 19H18v-2H8.55l-.3-1.5H19a1 1 0 0 0 .95-.68l3-9-.95-.32-3 9H8.55L7.3 6H4z"/>',
        'reports' => '<path d="M5 4h4v16H5V4zm5 6h4v10h-4V10zm5-6h4v16h-4V4z"/>',
        'users' => '<path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-3.33 0-10 1.67-10 5v3h20v-3c0-3.33-6.67-5-10-5z"/>',
        'mail' => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2v.01L12 11l8-4.99V6H4zm0 12h16V8l-8 5-8-5v10z"/>',
        'blog' => '<path d="M4 4h12a2 2 0 0 1 2 2v3h-2V6H4v12h12v-3h2v3a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm7 4h9v2h-9V8zm0 4h9v2h-9v-2zm0 4h5v2h-5v-2z"/>',
        'discount' => '<path d="M20.5 11.5l-7.95 7.95a2.5 2.5 0 0 1-1.77.73H6a2 2 0 0 1-2-2v-4.78a2.5 2.5 0 0 1 .73-1.77L11.68 3.7a2.5 2.5 0 0 1 3.54 0l5.28 5.28a2.5 2.5 0 0 1 0 3.54zM8.5 9A1.5 1.5 0 1 0 8.5 6a1.5 1.5 0 0 0 0 3zm7 5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM9 16.5a.75.75 0 0 1 0-1.5h6a.75.75 0 0 1 0 1.5H9z"/>',
        'settings' => '<path d="M19.14 12.94a7.97 7.97 0 0 0 .05-.94 7.97 7.97 0 0 0-.05-.94l2.03-1.58a.5.5 0 0 0 .11-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.06 7.06 0 0 0-1.63-.94l-.36-2.54A.5.5 0 0 0 14.89 2h-3.78a.5.5 0 0 0-.5.43l-.36 2.54a7.06 7.06 0 0 0-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L3.71 8.49a.5.5 0 0 0 .11.64L5.85 10.7a7.97 7.97 0 0 0-.05.94 7.97 7.97 0 0 0 .05.94l-2.03 1.58a.5.5 0 0 0-.11.64l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96c.5.38 1.05.7 1.63.94l.36 2.54a.5.5 0 0 0 .5.43h3.78a.5.5 0 0 0 .5-.43l.36-2.54c.58-.24 1.13-.56 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.5.5 0 0 0-.11-.64l-2.03-1.58zM13 15a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>',
        'contact' => '<path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-5l-5 3v-3H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2v9h12.67L17 13H7l3.5-4 3 3 3.5-4 1 3H20V6H4z"/>',
    ];
    return $icons[$name] ?? $icons['dashboard'];
}

if (!function_exists('rb_admin_slugify')) {
    function rb_admin_slugify(string $string): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($string)));
        $slug = trim((string)$slug, '-');
        return $slug !== '' ? $slug : uniqid('item-', false);
    }
}

if (!function_exists('rb_admin_fetch_settings')) {
    /**
     * @param array<int,string> $keys
     * @return array<string,string>
     */
    function rb_admin_fetch_settings(PDO $pdo, array $keys = []): array
    {
        if ($keys) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders) ORDER BY setting_key ASC");
            $stmt->execute(array_values($keys));
        } else {
            $stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings ORDER BY setting_key ASC');
        }
        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[(string)$row['setting_key']] = (string)$row['setting_value'];
        }
        return $results;
    }
}

if (!function_exists('rb_admin_save_settings')) {
    /**
     * @param array<string,string> $settings
     * @return array{ok:bool,changed:int,errors:array<string,string>}
     */
    function rb_admin_save_settings(PDO $pdo, array $settings): array
    {
        $errors = [];
        $changed = 0;
        $selectStmt = $pdo->prepare('SELECT id, setting_value FROM site_settings WHERE setting_key = :key LIMIT 1');
        $updateStmt = $pdo->prepare('UPDATE site_settings SET setting_value = :value WHERE id = :id');
        $insertStmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)');

        foreach ($settings as $key => $value) {
            $key = trim((string)$key);
            if ($key === '') {
                $errors[$key] = 'Setting key cannot be empty.';
                continue;
            }
            $value = (string)$value;
            $selectStmt->execute(['key' => $key]);
            $existing = $selectStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if ((string)$existing['setting_value'] !== $value) {
                    $updateStmt->execute([
                        'value' => $value,
                        'id' => (int)$existing['id'],
                    ]);
                    $changed++;
                }
            } else {
                $insertStmt->execute([
                    'key' => $key,
                    'value' => $value,
                ]);
                $changed++;
            }
        }

        return [
            'ok' => empty($errors),
            'changed' => $changed,
            'errors' => $errors,
        ];
    }
}

if (!function_exists('rb_admin_contact_messages')) {
    /**
     * @param array{status?:string,limit?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    function rb_admin_contact_messages(PDO $pdo, array $filters = []): array
    {
        $sql = 'SELECT cm.*, (SELECT COUNT(*) FROM contact_message_replies r WHERE r.contact_message_id = cm.id) AS reply_count FROM contact_messages cm WHERE 1=1';
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' AND cm.mail_status = :status';
            $params['status'] = $filters['status'];
        }
        $sql .= ' ORDER BY cm.created_at DESC';
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('rb_admin_contact_message')) {
    /**
     * @return array<string,mixed>|null
     */
    function rb_admin_contact_message(PDO $pdo, int $messageId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM contact_messages WHERE id = :id');
        $stmt->execute(['id' => $messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$message) {
            return null;
        }
        $replyStmt = $pdo->prepare('
            SELECT r.*, u.first_name, u.last_name, u.email AS admin_email
            FROM contact_message_replies r
            LEFT JOIN users u ON u.id = r.admin_id
            WHERE r.contact_message_id = :id
            ORDER BY r.created_at ASC
        ');
        $replyStmt->execute(['id' => $messageId]);
        $message['replies'] = $replyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $message;
    }
}

if (!function_exists('rb_admin_record_contact_reply')) {
    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    function rb_admin_record_contact_reply(PDO $pdo, array $data): array
    {
        try {
            $stmt = $pdo->prepare('INSERT INTO contact_message_replies (contact_message_id, admin_id, subject, body, mail_status, mail_error) VALUES (:message_id, :admin_id, :subject, :body, :status, :error)');
            $stmt->execute([
                'message_id' => (int)$data['message_id'],
                'admin_id' => $data['admin_id'] ?? null,
                'subject' => (string)$data['subject'],
                'body' => (string)$data['body'],
                'status' => (string)$data['status'],
                'error' => $data['error'] ?? null,
            ]);
            return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }
}

if (!function_exists('rb_admin_touch_contact_message')) {
    function rb_admin_touch_contact_message(PDO $pdo, int $messageId, string $status, ?string $error = null): void
    {
        $setResponded = in_array($status, ['replied', 'reply_sent', 'reply_queued'], true) ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE contact_messages SET mail_status = :status, mail_error = :error, responded_at = CASE WHEN :responded = 1 THEN NOW() ELSE responded_at END WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'error' => $error,
            'responded' => $setResponded,
            'id' => $messageId,
        ]);
    }
}

if (!function_exists('rb_admin_blog_posts')) {
    /**
     * @param array{status?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    function rb_admin_blog_posts(PDO $pdo, array $filters = []): array
    {
        $sql = 'SELECT id, slug, title, status, published_at, updated_at FROM blog_posts WHERE 1=1';
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], ['draft', 'published'], true)) {
            $sql .= ' AND status = :status';
            $params['status'] = $filters['status'];
        }
        if (isset($filters['search']) && $filters['search'] !== null && $filters['search'] !== '') {
            $sql .= ' AND (title LIKE :term OR slug LIKE :term)';
            $params['term'] = '%' . (string)$filters['search'] . '%';
        }
        $sql .= ' ORDER BY updated_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('rb_admin_blog_post')) {
    /**
     * @return array<string,mixed>|null
     */
    function rb_admin_blog_post(PDO $pdo, int $postId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id');
        $stmt->execute(['id' => $postId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('rb_admin_blog_save')) {
    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,id?:int,errors:array<string,string>}
     */
    function rb_admin_blog_save(PDO $pdo, array $input, ?int $postId = null): array
    {
        $errors = [];

        $title = trim((string)($input['title'] ?? ''));
        $slugInput = trim((string)($input['slug'] ?? ''));
        $excerpt = trim((string)($input['excerpt'] ?? ''));
        $body = trim((string)($input['body'] ?? ''));
        $heroImage = trim((string)($input['hero_image'] ?? ''));
        $authorName = trim((string)($input['author_name'] ?? ''));
        $authorAvatar = trim((string)($input['author_avatar'] ?? ''));
        $authorBio = trim((string)($input['author_bio'] ?? ''));
        $status = in_array(($input['status'] ?? 'draft'), ['draft', 'published'], true) ? $input['status'] : 'draft';
        $publishedRaw = trim((string)($input['published_at'] ?? ''));

        if ($title === '') {
            $errors['title'] = 'Title is required.';
        }
        $slug = $slugInput !== '' ? rb_admin_slugify($slugInput) : rb_admin_slugify($title);
        if ($slug === '') {
            $errors['slug'] = 'Unable to derive a valid slug.';
        }

        if ($excerpt === '') {
            $errors['excerpt'] = 'Provide a short excerpt (1-2 sentences).';
        }

        if ($body === '') {
            $errors['body'] = 'The article body cannot be empty.';
        }

        if ($authorName === '') {
            $errors['author_name'] = 'Author name is required.';
        }

        $publishedAt = null;
        if ($publishedRaw !== '') {
            $dt = date_create($publishedRaw);
            if ($dt === false) {
                $errors['published_at'] = 'Enter a valid publish date.';
            } else {
                $publishedAt = $dt->format('Y-m-d');
            }
        } elseif ($status === 'published') {
            $publishedAt = date('Y-m-d');
        }

        if ($status === 'published' && $publishedAt === null) {
            $errors['published_at'] = 'Published posts require a publish date.';
        }

        $slugCheckSql = 'SELECT id FROM blog_posts WHERE slug = :slug';
        $slugParams = ['slug' => $slug];
        if ($postId) {
            $slugCheckSql .= ' AND id <> :id';
            $slugParams['id'] = $postId;
        }
        $slugStmt = $pdo->prepare($slugCheckSql);
        $slugStmt->execute($slugParams);
        if ($slugStmt->fetch()) {
            $errors['slug'] = 'This slug is already in use.';
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        if ($postId) {
            $stmt = $pdo->prepare('
                UPDATE blog_posts
                SET slug = :slug,
                    title = :title,
                    excerpt = :excerpt,
                    body = :body,
                    hero_image = :hero_image,
                    author_name = :author_name,
                    author_avatar = :author_avatar,
                    author_bio = :author_bio,
                    published_at = :published_at,
                    status = :status
                WHERE id = :id
            ');
            $stmt->execute([
                'slug' => $slug,
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => $body,
                'hero_image' => $heroImage,
                'author_name' => $authorName,
                'author_avatar' => $authorAvatar,
                'author_bio' => $authorBio,
                'published_at' => $publishedAt,
                'status' => $status,
                'id' => $postId,
            ]);
            return ['ok' => true, 'id' => $postId, 'errors' => []];
        }

        $stmt = $pdo->prepare('
            INSERT INTO blog_posts (slug, title, excerpt, body, hero_image, author_name, author_avatar, author_bio, published_at, status)
            VALUES (:slug, :title, :excerpt, :body, :hero_image, :author_name, :author_avatar, :author_bio, :published_at, :status)
        ');
        $stmt->execute([
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $excerpt,
            'body' => $body,
            'hero_image' => $heroImage,
            'author_name' => $authorName,
            'author_avatar' => $authorAvatar,
            'author_bio' => $authorBio,
            'published_at' => $publishedAt,
            'status' => $status,
        ]);

        return [
            'ok' => true,
            'id' => (int)$pdo->lastInsertId(),
            'errors' => [],
        ];
    }
}
