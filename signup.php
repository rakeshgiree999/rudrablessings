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

$fieldErrors = [];
$registrationSuccess = false;
$pendingVerificationEmail = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $fieldErrors['general'] = 'Your session expired. Please refresh and try again.';
    } else {
        $result = rb_auth_register($pdo, $_POST);
        if ($result['ok']) {
            $registrationSuccess = true;
            $pendingVerificationEmail = trim((string)($_POST['email'] ?? ''));
            $_POST = [];
        } else {
            $fieldErrors = $result['errors'];
        }
    }
}

function field_value(string $key): string
{
    return rb_escape($_POST[$key] ?? '');
}

$currentPath = 'signup.php';
$isLoggedIn = false;

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Account | <?= rb_escape($brandName) ?></title>
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="auth-wrap container" style="margin:40px auto">
    <h1>Create an account</h1>
    <?php if ($registrationSuccess): ?>
      <div class="auth-errors" style="background:#ecfdf5;color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:20px;">
        <strong>Check your inbox</strong>
        <p style="margin:8px 0 0;">We sent a verification link to <?= rb_escape($pendingVerificationEmail) ?: 'your email' ?>. Activate your account from that email, then <a href="signin.php">sign in</a>.</p>
      </div>
    <?php endif; ?>
    <?php if ($fieldErrors && !$registrationSuccess): ?>
      <div class="auth-errors" style="background:#fee2e2;color:#7f1d1d;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong><?= rb_escape($fieldErrors['general'] ?? 'Please correct the highlighted fields.') ?></strong>
      </div>
    <?php endif; ?>
    <form method="post" class="auth-form" novalidate>
      <?= rb_csrf_input() ?>
      <label>First name
        <input name="first_name" value="<?= field_value('first_name') ?>" required>
        <?php if (isset($fieldErrors['first_name'])): ?><small class="muted" style="color:#b91c1c;"><?= rb_escape($fieldErrors['first_name']) ?></small><?php endif; ?>
      </label>
      <label>Last name
        <input name="last_name" value="<?= field_value('last_name') ?>" required>
        <?php if (isset($fieldErrors['last_name'])): ?><small class="muted" style="color:#b91c1c;"><?= rb_escape($fieldErrors['last_name']) ?></small><?php endif; ?>
      </label>
      <label>Email
        <input name="email" type="email" value="<?= field_value('email') ?>" required>
        <?php if (isset($fieldErrors['email'])): ?><small class="muted" style="color:#b91c1c;"><?= rb_escape($fieldErrors['email']) ?></small><?php endif; ?>
      </label>
      <label>Password
        <input name="password" type="password" required>
        <small class="muted">Use at least 8 characters, including a number and uppercase letter.</small>
        <?php if (isset($fieldErrors['password'])): ?><small class="muted" style="color:#b91c1c;"><?= rb_escape($fieldErrors['password']) ?></small><?php endif; ?>
      </label>
      <label>Confirm password
        <input name="confirm_password" type="password" required>
        <?php if (isset($fieldErrors['confirm_password'])): ?><small class="muted" style="color:#b91c1c;"><?= rb_escape($fieldErrors['confirm_password']) ?></small><?php endif; ?>
      </label>
      <button class="btn" type="submit">Create account</button>
      <p>Already have an account? <a href="signin.php">Sign in</a></p>
    </form>
  </section>

  
  <?php include __DIR__ . '/includes/footer.php'; ?>


  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>






