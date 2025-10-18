<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$menuItems = rb_menu($pdo);
$footerBlock = rb_block($pdo, 'footer', 'legal');

$currentUser = rb_auth_current_user($pdo);
if ($currentUser) {
    header('Location: profile.php');
    exit;
}

$errors = [];
$loginStatus = null;
$unverifiedEmail = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $result = rb_auth_login($pdo, $email, $password);
        if ($result['ok']) {
            rb_cart_resolve($pdo);
            header('Location: profile.php');
            exit;
        }
        $loginStatus = $result['status'] ?? null;
        $errors = $result['errors'];
        if ($loginStatus === 'unverified') {
            $unverifiedEmail = trim((string)($result['email'] ?? $email));
        }
    }
}

$currentPath = 'signin.php';
$isLoggedIn = false;

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | <?= rb_escape($brandName) ?></title>
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="auth-wrap container" style="margin:40px auto">
    <h1>Sign in</h1>
    <?php if ($errors): ?>
      <div class="auth-errors" style="background:#fee2e2;color:#7f1d1d;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong>We couldn't sign you in:</strong>
        <ul style="margin:8px 0 0;padding-left:18px;">
          <?php foreach ($errors as $error): ?>
            <li><?= rb_escape($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <?php if ($loginStatus === 'unverified' && $unverifiedEmail !== ''): ?>
      <div class="auth-errors" style="background:#eff6ff;color:#1d4ed8;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong>Email verification needed</strong>
        <p style="margin:8px 0 0;">We sent a verification link to <?= rb_escape($unverifiedEmail) ?>. <a href="verify.php?email=<?= rawurlencode($unverifiedEmail) ?>">Resend verification email</a>.</p>
      </div>
    <?php endif; ?>
    <form method="post" class="auth-form" novalidate>
      <?= rb_csrf_input() ?>
      <label>Email
        <input name="email" type="email" value="<?= rb_escape($_POST['email'] ?? '') ?>" required>
      </label>
      <label>Password
        <input name="password" type="password" required>
      </label>
      <button class="btn" type="submit">Sign in</button>
      <p class="muted" style="margin-top:8px;">Forgot password? <a href="forgot-password.php">Reset it here</a>.</p>
      <p>New here? <a href="signup.php">Create an account</a></p>
    </form>
  </section>

  
  <?php include __DIR__ . '/includes/footer.php'; ?>


  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>






