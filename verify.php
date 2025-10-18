<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$menuItems = rb_menu($pdo);
$footerBlock = rb_block($pdo, 'footer', 'legal');

$status = null;
$messages = [];
$prefillEmail = trim((string)($_GET['email'] ?? ''));
$tokenParam = trim((string)($_GET['token'] ?? ''));

if ($tokenParam !== '') {
    $tokenRow = rb_auth_consume_token($pdo, $tokenParam, 'verify');
    if ($tokenRow) {
        rb_auth_mark_email_verified($pdo, (int)$tokenRow['user_id']);
        $status = 'verified';
    } else {
        $status = 'invalid';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $messages[] = ['type' => 'error', 'text' => 'Your session expired. Please refresh and try again.'];
    } else {
        $emailInput = trim(mb_strtolower((string)($_POST['email'] ?? '')));
        $prefillEmail = $emailInput;
        if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $messages[] = ['type' => 'error', 'text' => 'Please enter a valid email address.'];
        } else {
            $user = rb_auth_find_user_by_email($pdo, $emailInput);
            if ($user && empty($user['email_verified_at'])) {
                $sendResult = rb_auth_send_verification_email($pdo, $user);
                if ($sendResult['ok'] ?? false) {
                    $messages[] = ['type' => 'success', 'text' => 'We sent a new verification email to ' . $emailInput . '.'];
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'We could not send the verification email right now. Please try again soon.'];
                }
            } else {
                // Already verified or unknown email. Respond generically to avoid enumeration.
                $messages[] = ['type' => 'success', 'text' => 'If an account exists for ' . $emailInput . ', a verification email has been sent.'];
            }
        }
    }
}

$currentPath = 'verify.php';
$isLoggedIn = (bool)$currentUser;

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email Verification | <?= rb_escape($brandName) ?></title>
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="auth-wrap container" style="margin:40px auto">
    <h1>Verify your email</h1>

    <?php if ($status === 'verified'): ?>
      <div class="auth-errors" style="background:#ecfdf5;color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong>Email confirmed</strong>
        <p style="margin:8px 0 0;">Your account is verified. <a href="signin.php">Sign in now</a>.</p>
      </div>
    <?php elseif ($status === 'invalid'): ?>
      <div class="auth-errors" style="background:#fee2e2;color:#7f1d1d;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <strong>That link has expired</strong>
        <p style="margin:8px 0 0;">Request a new verification email below and try again.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($messages as $message): ?>
      <div class="auth-errors" style="background:<?= $message['type'] === 'error' ? '#fee2e2' : '#eff6ff' ?>;color:<?= $message['type'] === 'error' ? '#7f1d1d' : '#1d4ed8' ?>;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
        <?= rb_escape($message['text']) ?>
      </div>
    <?php endforeach; ?>

    <p class="muted" style="margin-bottom:16px;">Enter your email address and we'll send a fresh verification link.</p>

    <form method="post" class="auth-form" novalidate style="max-width:360px;">
      <?= rb_csrf_input() ?>
      <label>Email
        <input name="email" type="email" value="<?= rb_escape($prefillEmail) ?>" required>
      </label>
      <button class="btn" type="submit">Resend verification email</button>
    </form>
  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>
