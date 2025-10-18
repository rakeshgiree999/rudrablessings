<?php
declare(strict_types=1);

$adminCurrent = 'promos';
require __DIR__ . '/includes/admin_app.php';

$flash = $_SESSION['admin_flash'] ?? null;
if ($flash !== null) {
    unset($_SESSION['admin_flash']);
}

$redirectToListing = static function (): void {
    header('Location: promos.php');
    exit;
};

$parseDateTime = static function (string $value): ?string {
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $value);
    if ($dt === false) {
        return null;
    }
    return $dt->format('Y-m-d H:i:s');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Your session expired. Please try again.',
        ];
        $redirectToListing();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_promo' || $action === 'update_promo') {
        $promoId = $action === 'update_promo' ? (int)($_POST['promo_id'] ?? 0) : null;
        $code = rb_promo_normalize_code((string)($_POST['code'] ?? ''));
        $type = strtolower(trim((string)($_POST['type'] ?? 'percent')));
        if (!in_array($type, ['percent', 'amount', 'shipping'], true)) {
            $type = 'percent';
        }
        $label = trim((string)($_POST['label'] ?? ''));
        $valueRaw = (float)($_POST['value'] ?? 0.0);
        $value = max(0.0, $valueRaw);
        if ($type === 'percent') {
            $value = min(100.0, $value);
        }
        if ($type === 'shipping') {
            $value = 0.0;
        }
        $minSubtotal = max(0.0, (float)($_POST['min_subtotal'] ?? 0.0));
        $maxUsageInput = trim((string)($_POST['max_usage'] ?? ''));
        $maxUsage = null;
        if ($maxUsageInput !== '') {
            $maxUsageValue = max(0, (int)$maxUsageInput);
            $maxUsage = $maxUsageValue > 0 ? $maxUsageValue : null;
        }
        $startsAtRaw = (string)($_POST['starts_at'] ?? '');
        $endsAtRaw = (string)($_POST['ends_at'] ?? '');
        $startsAt = $parseDateTime($startsAtRaw);
        $endsAt = $parseDateTime($endsAtRaw);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($code === '') {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Promo code is required.',
            ];
            $redirectToListing();
        }

        if ($startsAtRaw !== '' && $startsAt === null) {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Enter a valid start date.',
            ];
            $redirectToListing();
        }
        if ($endsAtRaw !== '' && $endsAt === null) {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Enter a valid end date.',
            ];
            $redirectToListing();
        }
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'End date must be after the start date.',
            ];
            $redirectToListing();
        }

        if ($action === 'create_promo') {
            $check = $pdo->prepare('SELECT id FROM promos WHERE code = :code LIMIT 1');
            $check->execute(['code' => $code]);
            if ($check->fetch()) {
                $_SESSION['admin_flash'] = [
                    'type' => 'error',
                    'message' => 'Another promo already uses this code.',
                ];
                $redirectToListing();
            }
        } else {
            if ($promoId === null || $promoId <= 0) {
                $_SESSION['admin_flash'] = [
                    'type' => 'error',
                    'message' => 'Promo not found.',
                ];
                $redirectToListing();
            }
            $exists = $pdo->prepare('SELECT id FROM promos WHERE id = :id LIMIT 1');
            $exists->execute(['id' => $promoId]);
            if (!$exists->fetch()) {
                $_SESSION['admin_flash'] = [
                    'type' => 'error',
                    'message' => 'Promo not found.',
                ];
                $redirectToListing();
            }
            $dup = $pdo->prepare('SELECT id FROM promos WHERE code = :code AND id <> :id LIMIT 1');
            $dup->execute(['code' => $code, 'id' => $promoId]);
            if ($dup->fetch()) {
                $_SESSION['admin_flash'] = [
                    'type' => 'error',
                    'message' => 'Another promo already uses this code.',
                ];
                $redirectToListing();
            }
        }

        if ($label === '') {
            $label = rb_promo_default_message([
                'code' => $code,
                'type' => $type,
                'value' => $value,
                'label' => '',
            ]);
        }

        if ($action === 'create_promo') {
            $insert = $pdo->prepare('
                INSERT INTO promos (code, type, value, label, min_subtotal, max_usage, is_active, starts_at, ends_at)
                VALUES (:code, :type, :value, :label, :min_subtotal, :max_usage, :is_active, :starts_at, :ends_at)
            ');
            $insert->execute([
                'code' => $code,
                'type' => $type,
                'value' => $value,
                'label' => $label,
                'min_subtotal' => $minSubtotal,
                'max_usage' => $maxUsage,
                'is_active' => $isActive,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
            $_SESSION['admin_flash'] = [
                'type' => 'success',
                'message' => 'Promo created successfully.',
            ];
            $redirectToListing();
        } else {
            $update = $pdo->prepare('
                UPDATE promos
                SET code = :code,
                    type = :type,
                    value = :value,
                    label = :label,
                    min_subtotal = :min_subtotal,
                    max_usage = :max_usage,
                    is_active = :is_active,
                    starts_at = :starts_at,
                    ends_at = :ends_at
                WHERE id = :id
            ');
            $update->execute([
                'code' => $code,
                'type' => $type,
                'value' => $value,
                'label' => $label,
                'min_subtotal' => $minSubtotal,
                'max_usage' => $maxUsage,
                'is_active' => $isActive,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'id' => $promoId,
            ]);
            $_SESSION['admin_flash'] = [
                'type' => 'success',
                'message' => 'Promo updated.',
            ];
            $redirectToListing();
        }
    }
}

$promos = rb_promos_fetch($pdo);
$formatDateForInput = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return '';
    }
    return date('Y-m-d\TH:i', $ts);
};

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= rb_escape($brandName) ?> Admin &mdash; Promos</title>
  <link rel="icon" href="<?= rb_asset('../media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('../media/logo.png') ?>">
  <link rel="stylesheet" href="css/admin.css">
  <style>
    .promo-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    .promo-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 18px;
      box-shadow: 0 5px 12px rgba(15, 23, 42, 0.04);
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .promo-card header {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .promo-card header strong {
      font-size: 16px;
      color: #0f172a;
    }
    .promo-card label {
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-size: 14px;
      color: #334155;
    }
    .promo-card input,
    .promo-card select,
    .promo-card textarea {
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 8px 10px;
      font: inherit;
      background: #fff;
    }
    .promo-card input[type="checkbox"] {
      width: auto;
      height: auto;
      margin: 0;
    }
    .promo-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      color: #475569;
      gap: 8px;
      flex-wrap: wrap;
    }
    .promo-actions {
      display: flex;
      justify-content: flex-end;
      margin-top: 10px;
    }
    .promo-create {
      border-style: dashed;
      border-color: #cbd5f5;
      background: #f8fafc;
    }
    .field-hint {
      color: #64748b;
      font-size: 12px;
    }
    .switcher {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 4px;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.01em;
    }
  </style>
</head>

<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="#">
        <img src="../media/logo.png" alt="<?= rb_escape($brandName) ?>" style="width:32px;height:32px;">
        <span><?= rb_escape($brandName) ?> Admin</span>
      </a>
      <nav class="admin-nav">
        <?php foreach ($adminNavItems as $item): ?>
          <?php $active = $item['key'] === $adminCurrent; ?>
          <a href="<?= rb_escape($item['href']) ?>"<?= $active ? ' class="active"' : '' ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true"><?= rb_admin_icon($item['icon']) ?></svg>
            <span><?= rb_escape($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div style="margin-top:auto">
        <a class="admin-btn secondary" href="../index.php">View storefront</a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <h1 style="margin:0;font-size:22px;">Promos</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">
            Manage promo codes for cart discounts or free shipping.
          </p>
        </div>
      </header>

      <main class="admin-content">
        <?php if ($flash): ?>
          <div class="notice <?= rb_escape($flash['type'] ?? 'info') ?>"><?= rb_escape($flash['message'] ?? '') ?></div>
        <?php endif; ?>

        <section class="promo-grid">
          <?php foreach ($promos as $promo): ?>
            <?php
              $promoId = (int)($promo['id'] ?? 0);
              $code = rb_promo_normalize_code((string)($promo['code'] ?? ''));
              $type = (string)($promo['type'] ?? 'percent');
              $value = (float)($promo['value'] ?? 0.0);
              $label = (string)($promo['label'] ?? '');
              $minSubtotal = (float)($promo['min_subtotal'] ?? 0.0);
              $usageCount = (int)($promo['usage_count'] ?? 0);
              $maxUsage = $promo['max_usage'] !== null ? (int)$promo['max_usage'] : null;
              $isActive = (int)($promo['is_active'] ?? 0) === 1;
              $startsAtInput = $formatDateForInput($promo['starts_at'] ?? null);
              $endsAtInput = $formatDateForInput($promo['ends_at'] ?? null);
            ?>
            <article class="promo-card">
              <header>
                <strong><?= rb_escape($code) ?></strong>
                <span class="badge" style="background:<?= $isActive ? '#dcfce7' : '#fee2e2' ?>;color:<?= $isActive ? '#166534' : '#7f1d1d' ?>;">
                  <?= $isActive ? 'Active' : 'Inactive' ?>
                </span>
              </header>
              <form method="post">
                <?= rb_csrf_input() ?>
                <input type="hidden" name="action" value="update_promo">
                <input type="hidden" name="promo_id" value="<?= $promoId ?>">
                <label>
                  Code
                  <input name="code" value="<?= rb_escape($code) ?>" required maxlength="32">
                </label>
                <label>
                  Label
                  <input name="label" value="<?= rb_escape($label) ?>" placeholder="Shown to customers after applying the promo">
                </label>
                <label>
                  Type
                  <select name="type">
                    <option value="percent"<?= $type === 'percent' ? ' selected' : '' ?>>Percent off</option>
                    <option value="amount"<?= $type === 'amount' ? ' selected' : '' ?>>Amount off</option>
                    <option value="shipping"<?= $type === 'shipping' ? ' selected' : '' ?>>Free shipping</option>
                  </select>
                </label>
                <label>
                  Value
                  <input name="value" type="number" step="0.01" min="0" value="<?= rb_escape(number_format($value, 2, '.', '')) ?>">
                  <span class="field-hint">Percent codes: value is percentage (e.g. 10 = 10%). Amount codes: value is dollars. Free shipping ignores this value.</span>
                </label>
                <label>
                  Minimum subtotal
                  <input name="min_subtotal" type="number" step="0.01" min="0" value="<?= rb_escape(number_format($minSubtotal, 2, '.', '')) ?>">
                </label>
                <label>
                  Usage limit
                  <input name="max_usage" type="number" min="1" placeholder="Unlimited" value="<?= $maxUsage !== null ? (int)$maxUsage : '' ?>">
                  <span class="field-hint">Leave blank for unlimited uses. Current usage: <?= $usageCount ?><?= $maxUsage !== null ? ' / ' . (int)$maxUsage : '' ?>.</span>
                </label>
                <label>
                  Starts at
                  <input name="starts_at" type="datetime-local" value="<?= rb_escape($startsAtInput) ?>">
                </label>
                <label>
                  Ends at
                  <input name="ends_at" type="datetime-local" value="<?= rb_escape($endsAtInput) ?>">
                </label>
                <label class="switcher">
                  <input type="checkbox" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                  <span>Promo is active</span>
                </label>
                <div class="promo-meta">
                  <span>Usage: <strong><?= $usageCount ?></strong><?= $maxUsage !== null ? ' / ' . (int)$maxUsage : ' / unlimited' ?></span>
                  <?php if ($startsAtInput !== ''): ?>
                    <span>Starts: <?= rb_escape(str_replace('T', ' ', $startsAtInput)) ?></span>
                  <?php endif; ?>
                  <?php if ($endsAtInput !== ''): ?>
                    <span>Ends: <?= rb_escape(str_replace('T', ' ', $endsAtInput)) ?></span>
                  <?php endif; ?>
                </div>
                <div class="promo-actions">
                  <button type="submit" class="admin-btn">Save changes</button>
                </div>
              </form>
            </article>
          <?php endforeach; ?>

          <article class="promo-card promo-create">
            <strong>Create a promo</strong>
            <form method="post">
              <?= rb_csrf_input() ?>
              <input type="hidden" name="action" value="create_promo">
              <label>
                Code
                <input name="code" placeholder="SAVE10" required maxlength="32">
              </label>
              <label>
                Label
                <input name="label" placeholder="Shown to customers once applied">
              </label>
              <label>
                Type
                <select name="type">
                  <option value="percent">Percent off</option>
                  <option value="amount">Amount off</option>
                  <option value="shipping">Free shipping</option>
                </select>
              </label>
              <label>
                Value
                <input name="value" type="number" step="0.01" min="0" value="10">
                <span class="field-hint">Percent codes: percentage. Amount codes: dollars. Ignored for free shipping.</span>
              </label>
              <label>
                Minimum subtotal
                <input name="min_subtotal" type="number" step="0.01" min="0" value="0.00">
              </label>
              <label>
                Usage limit
                <input name="max_usage" type="number" min="1" placeholder="Unlimited">
              </label>
              <label>
                Starts at
                <input name="starts_at" type="datetime-local">
              </label>
              <label>
                Ends at
                <input name="ends_at" type="datetime-local">
              </label>
              <label class="switcher">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Promo is active</span>
              </label>
              <div class="promo-actions" style="justify-content:flex-start;">
                <button type="submit" class="admin-btn">Create promo</button>
              </div>
            </form>
          </article>
        </section>
      </main>
    </div>
  </div>
</body>

</html>
