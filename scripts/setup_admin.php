<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../includes/site.php';
require __DIR__ . '/../includes/auth.php';

rb_load_env(__DIR__ . '/../.env');

/**
 * Output a response tailored for CLI vs browser usage.
 */
function rb_setup_admin_respond(string $message, bool $ok = true): void
{
    if (PHP_SAPI === 'cli') {
        fwrite($ok ? STDOUT : STDERR, $message . PHP_EOL);
        exit($ok ? 0 : 1);
    }

    http_response_code($ok ? 200 : 500);
    header('Content-Type: text/html; charset=utf-8');
    $status = $ok ? 'success' : 'error';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Admin Setup <?= $ok ? 'Complete' : 'Failed' ?></title>
      <style>
        body { font-family: Inter, system-ui, -apple-system, Segoe UI, sans-serif; background:#f6efe8; color:#2d1c11; margin:0; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .panel { background:#fff; border:1px solid #f0d6b2; border-radius:14px; padding:28px 32px; max-width:420px; box-shadow:0 18px 50px rgba(59,42,34,.12); text-align:center; }
        .panel h1 { margin:0 0 12px; font-size:24px; color:#a66328; }
        .panel p { margin: 0 0 18px; line-height:1.5; }
        .status-success { color:#047857; }
        .status-error { color:#b91c1c; }
        .details { font-size:14px; color:#4b5563; word-break:break-word; }
        a.button { display:inline-block; margin-top:18px; padding:10px 18px; border-radius:999px; background:#a66328; color:#fff; text-decoration:none; font-weight:600; }
      </style>
    </head>
    <body>
      <div class="panel">
        <h1><?= $ok ? 'Admin Ready' : 'Setup Failed' ?></h1>
        <p class="status-<?= $status ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($ok): ?>
          <p class="details">Sign in with <strong>admin@rudrablessings.com</strong> and password <strong>admin</strong>, then update the credentials immediately.</p>
          <a class="button" href="../signin.php">Go to Sign In</a>
        <?php endif; ?>
      </div>
    </body>
    </html>
    <?php
    exit;
}

try {
    $pdo = rb_db();

    $columnStmt = $pdo->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'avatar_path'
        LIMIT 1
    ");
    $columnStmt->execute();
    if (!$columnStmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER last_name");
    }

    $passwordHash = password_hash('admin', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (role, email, password_hash, first_name, last_name, avatar_path, is_active, created_at, updated_at)
        VALUES (:role, :email, :password_hash, :first_name, :last_name, :avatar_path, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
          role = VALUES(role),
          password_hash = VALUES(password_hash),
          first_name = VALUES(first_name),
          last_name = VALUES(last_name),
          avatar_path = VALUES(avatar_path),
          is_active = 1,
          updated_at = NOW()
    ");
    $stmt->execute([
        'role' => 'admin',
        'email' => 'admin@rudrablessings.com',
        'password_hash' => $passwordHash,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'avatar_path' => 'media/admin-avatar.svg',
    ]);

    rb_setup_admin_respond('Admin account ready. Default password: admin. Please change it after logging in.');
} catch (Throwable $exception) {
    $message = 'Setup failed: ' . $exception->getMessage();
    rb_setup_admin_respond($message, false);
}
