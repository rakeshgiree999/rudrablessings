<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$menuItems = rb_menu($pdo);
$footerBlock = rb_block($pdo, 'footer', 'legal');

$errors = [];
$statusMessage = null;
$emailValue = trim((string)($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    } else {
        $normalizedEmail = trim(mb_strtolower($emailValue));
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            rb_auth_request_password_reset($pdo, $normalizedEmail);
            $statusMessage = 'If an account exists for that email address, a password reset link has been sent.';
            $emailValue = '';
        }
    }
}

$currentPath = 'forgot-password.php';
$isLoggedIn = (bool)$currentUser;

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password | <?= rb_escape($brandName) ?></title>
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="auth-wrap container" style="margin:40px auto">
    <h1>Reset your password</h1>
    <p class="muted" style="margin-bottom:16px;">Enter your email and we&rsquo;ll send you a link to choose a new password.</p>

    <?php if ($statusMessage): ?>
      <div class="auth-errors" style="background:#eff6ff;color:#1d4ed8;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <?= rb_escape($statusMessage) ?>
      </div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="auth-errors" style="background:#fee2e2;color:#7f1d1d;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong>We couldn&rsquo;t process that request:</strong>
        <ul style="margin:8px 0 0;padding-left:18px;">
          <?php foreach ($errors as $error): ?>
            <li><?= rb_escape($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="auth-form" novalidate style="max-width:360px;">
      <?= rb_csrf_input() ?>
      <label>Email
        <input name="email" type="email" value="<?= rb_escape($emailValue) ?>" required>
      </label>
      <button class="btn" type="submit">Send reset link</button>
      <p style="margin-top:8px;">Remembered your password? <a href="signin.php">Sign in</a>.</p>
    </form>
  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>
