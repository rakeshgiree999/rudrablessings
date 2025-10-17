<?php
declare(strict_types=1);

$adminCurrent = 'settings';
require __DIR__ . '/includes/admin_app.php';

$settingKeys = [
    'brand.name',
    'brand.tagline',
    'support.email',
    'support.phone',
    'support.address',
    'support.hours',
    'contact.hero.title',
    'contact.hero.subtitle',
    'contact.map_embed',
    'social.instagram',
    'social.facebook',
    'social.youtube',
];

$currentSettings = rb_admin_fetch_settings($pdo, $settingKeys);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Your session expired. Please try again.',
        ];
        header('Location: settings.php');
        exit;
    }
    $updates = [
        'brand.name' => trim((string)($_POST['brand_name'] ?? $currentSettings['brand.name'] ?? '')),
        'brand.tagline' => trim((string)($_POST['brand_tagline'] ?? $currentSettings['brand.tagline'] ?? '')),
        'support.email' => trim((string)($_POST['support_email'] ?? $currentSettings['support.email'] ?? '')),
        'support.phone' => trim((string)($_POST['support_phone'] ?? $currentSettings['support.phone'] ?? '')),
        'support.address' => trim((string)($_POST['support_address'] ?? $currentSettings['support.address'] ?? '')),
        'support.hours' => trim((string)($_POST['support_hours'] ?? $currentSettings['support.hours'] ?? '')),
        'contact.hero.title' => trim((string)($_POST['contact_hero_title'] ?? $currentSettings['contact.hero.title'] ?? '')),
        'contact.hero.subtitle' => trim((string)($_POST['contact_hero_subtitle'] ?? $currentSettings['contact.hero.subtitle'] ?? '')),
        'contact.map_embed' => trim((string)($_POST['contact_map_embed'] ?? $currentSettings['contact.map_embed'] ?? '')),
        'social.instagram' => trim((string)($_POST['social_instagram'] ?? $currentSettings['social.instagram'] ?? '')),
        'social.facebook' => trim((string)($_POST['social_facebook'] ?? $currentSettings['social.facebook'] ?? '')),
        'social.youtube' => trim((string)($_POST['social_youtube'] ?? $currentSettings['social.youtube'] ?? '')),
    ];

    $result = rb_admin_save_settings($pdo, $updates);
    if ($result['ok']) {
        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => $result['changed'] > 0 ? 'Settings updated successfully.' : 'No changes detected.',
        ];
    } else {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Unable to save some settings.',
        ];
    }
    header('Location: settings.php');
    exit;
}

$currentSettings = rb_admin_fetch_settings($pdo, $settingKeys);
$flash = $_SESSION['admin_flash'] ?? null;
if ($flash !== null) {
    unset($_SESSION['admin_flash']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Site Settings | <?= rb_escape($brandName) ?></title>
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
  <style>
    .setting-grid {
      display: grid;
      gap: 24px;
    }
    .setting-card {
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      padding: 24px;
      display: grid;
      gap: 16px;
    }
    .setting-card h2 {
      margin: 0;
      font-size: 18px;
    }
    .setting-card p {
      margin: 0;
      color: var(--admin-muted);
      font-size: 14px;
    }
    .setting-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .setting-field label {
      font-weight: 600;
    }
    .setting-field input,
    .setting-field textarea {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #d1d5db;
      font-family: inherit;
      font-size: 14px;
    }
    .setting-field textarea {
      resize: vertical;
      min-height: 90px;
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
          <h1 style="margin:0;font-size:22px;">Site settings</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Update branding, contact, and social information across the site.</p>
        </div>
      </header>

      <main class="admin-content">
        <?php if ($flash): ?>
          <div class="notice <?= rb_escape($flash['type'] ?? 'info') ?>"><?= rb_escape($flash['message'] ?? '') ?></div>
        <?php endif; ?>
        <form method="post" class="setting-grid">
          <?= rb_csrf_input() ?>
          <section class="setting-card">
            <div>
              <h2>Brand</h2>
              <p>Control the name and tagline displayed across the storefront.</p>
            </div>
            <div class="setting-field">
              <label for="brand_name">Brand name</label>
              <input id="brand_name" name="brand_name" value="<?= rb_escape($currentSettings['brand.name'] ?? '') ?>" required>
            </div>
            <div class="setting-field">
              <label for="brand_tagline">Tagline</label>
              <input id="brand_tagline" name="brand_tagline" value="<?= rb_escape($currentSettings['brand.tagline'] ?? '') ?>">
            </div>
          </section>

          <section class="setting-card">
            <div>
              <h2>Contact</h2>
              <p>Displayed on contact page and used for transactional emails.</p>
            </div>
            <div class="setting-field">
              <label for="support_email">Support email</label>
              <input id="support_email" name="support_email" type="email" value="<?= rb_escape($currentSettings['support.email'] ?? '') ?>" required>
            </div>
            <div class="setting-field">
              <label for="support_phone">Phone number</label>
              <input id="support_phone" name="support_phone" value="<?= rb_escape($currentSettings['support.phone'] ?? '') ?>">
            </div>
            <div class="setting-field">
              <label for="support_address">Address</label>
              <textarea id="support_address" name="support_address"><?= rb_escape($currentSettings['support.address'] ?? '') ?></textarea>
            </div>
            <div class="setting-field">
              <label for="support_hours">Operating hours (HTML allowed)</label>
              <textarea id="support_hours" name="support_hours"><?= rb_escape($currentSettings['support.hours'] ?? '') ?></textarea>
            </div>
            <div class="setting-field">
              <label for="contact_map_embed">Map embed URL</label>
              <input id="contact_map_embed" name="contact_map_embed" value="<?= rb_escape($currentSettings['contact.map_embed'] ?? '') ?>" placeholder="Google Maps embed URL">
            </div>
          </section>

          <section class="setting-card">
            <div>
              <h2>Contact hero content</h2>
              <p>Update the hero copy shown on the contact page.</p>
            </div>
            <div class="setting-field">
              <label for="contact_hero_title">Title</label>
              <input id="contact_hero_title" name="contact_hero_title" value="<?= rb_escape($currentSettings['contact.hero.title'] ?? '') ?>">
            </div>
            <div class="setting-field">
              <label for="contact_hero_subtitle">Subtitle</label>
              <textarea id="contact_hero_subtitle" name="contact_hero_subtitle"><?= rb_escape($currentSettings['contact.hero.subtitle'] ?? '') ?></textarea>
            </div>
          </section>

          <section class="setting-card">
            <div>
              <h2>Social profiles</h2>
              <p>Links appear in the footer and contact page.</p>
            </div>
            <div class="setting-field">
              <label for="social_instagram">Instagram URL</label>
              <input id="social_instagram" name="social_instagram" value="<?= rb_escape($currentSettings['social.instagram'] ?? '') ?>">
            </div>
            <div class="setting-field">
              <label for="social_facebook">Facebook URL</label>
              <input id="social_facebook" name="social_facebook" value="<?= rb_escape($currentSettings['social.facebook'] ?? '') ?>">
            </div>
            <div class="setting-field">
              <label for="social_youtube">YouTube URL</label>
              <input id="social_youtube" name="social_youtube" value="<?= rb_escape($currentSettings['social.youtube'] ?? '') ?>">
            </div>
          </section>

          <div class="admin-actions" style="justify-content:flex-end;">
            <a class="admin-btn secondary" href="../index.php">Cancel</a>
            <button class="admin-btn" type="submit">Save settings</button>
          </div>
        </form>
      </main>
    </div>
  </div>
</body>
</html>
