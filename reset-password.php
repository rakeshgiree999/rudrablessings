<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$menuItems = rb_menu($pdo);
$footerBlock = rb_block($pdo, 'footer', 'legal');

$errors = [];
$status = null;
$tokenParam = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

$tokenPreview = null;
if ($tokenParam !== '') {
    $tokenPreview = rb_auth_consume_token($pdo, $tokenParam, 'password_reset', false);
    if (!$tokenPreview && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $status = 'invalid';
    }
} else {
    $status = 'missing';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $status !== 'invalid') {
    if (!rb_csrf_validate_request()) {
        $errors['general'] = 'Your session expired. Please refresh and try again.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($password === '') {
            $errors['password'] = 'New password is required.';
        }
        if ($confirm === '') {
            $errors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!$errors) {
            $tokenRow = rb_auth_consume_token($pdo, $tokenParam, 'password_reset');
            if (!$tokenRow) {
                $status = 'invalid';
            } else {
                $reset = rb_auth_reset_password($pdo, (int)$tokenRow['user_id'], $password);
                if ($reset['ok']) {
                    $status = 'reset';
                } else {
                    $errors = $reset['errors'];
                }
            }
        }

        if ($status !== 'reset') {
            $tokenPreview = rb_auth_consume_token($pdo, $tokenParam, 'password_reset', false);
            if (!$tokenPreview && $status !== 'invalid') {
                $status = 'invalid';
            }
        }
    }
}

$currentPath = 'reset-password.php';
$isLoggedIn = (bool)$currentUser;

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Choose New Password | <?= rb_escape($brandName) ?></title>
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="auth-wrap container" style="margin:40px auto">
    <h1>Choose a new password</h1>

    <?php if ($status === 'missing'): ?>
      <div class="auth-errors" style="background:#fee2e2;color:#7f1d1d;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong>Reset link missing</strong>
        <p style="margin:8px 0 0;">Use the link in your email to access this page.</p>
      </div>
    <?php elseif ($status === 'invalid'): ?>
      <div class="auth-errors" style="background:#fee2e2;color:#7f1d1d;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong>That link has expired</strong>
        <p style="margin:8px 0 0;">Request a fresh reset email from the <a href="forgot-password.php">password reset page</a>.</p>
      </div>
    <?php elseif ($status === 'reset'): ?>
      <div class="auth-errors" style="background:#ecfdf5;color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong>Password updated</strong>
        <p style="margin:8px 0 0;">You can now <a href="signin.php">sign in</a> with your new password.</p>
      </div>
    <?php endif; ?>

    <?php if ($errors && $status !== 'reset'): ?>
      <div class="auth-errors" style="background:#fee2e2;color:#7f1d1d;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong>Please fix the highlighted fields.</strong>
      </div>
    <?php endif; ?>

    <?php if (($tokenPreview || $status === null) && $status !== 'reset'): ?>
      <form method="post" class="auth-form" novalidate style="max-width:360px;">
        <?= rb_csrf_input() ?>
        <input type="hidden" name="token" value="<?= rb_escape($tokenParam) ?>">
        <label>New password
          <input name="password" type="password" required>
          <small class="muted">Use at least 8 characters, including a number and uppercase letter.</small>
          <?php if (isset($errors['password'])): ?><small class="muted" style="color:#b91c1c;"><?= rb_escape($errors['password']) ?></small><?php endif; ?>
        </label>
        <label>Confirm password
          <input name="confirm_password" type="password" required>
          <?php if (isset($errors['confirm_password'])): ?><small class="muted" style="color:#b91c1c;"><?= rb_escape($errors['confirm_password']) ?></small><?php endif; ?>
        </label>
        <button class="btn" type="submit">Update password</button>
      </form>
    <?php endif; ?>
  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>
