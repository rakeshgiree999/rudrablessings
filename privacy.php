<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$currentPath = 'privacy.php';
$privacyBlocks = rb_blocks($pdo, 'privacy');
$privacyHero = $privacyBlocks['hero'] ?? [];
unset($privacyBlocks['hero']);
$privacySections = $privacyBlocks;
$privacyUpdatedAt = rb_format_date($settings['privacy.last_updated'] ?? null);
$privacyTitle = $privacyHero['title'] ?? 'Privacy Policy';
$privacySubtitle = $privacyHero['subtitle'] ?? 'How RudraBlessings collects, uses, and protects your information.';
$privacyMetaDescription = $privacySubtitle ?: 'How RudraBlessings collects, uses, and protects your information.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= rb_escape($privacyTitle) ?> | <?= rb_escape($brandName) ?></title>
  <meta name="description" content="<?= rb_escape($privacyMetaDescription) ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/pages.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="contact-hero">
    <div class="wrap">
      <nav class="breadcrumbs"><a href="index.php">Home</a> &raquo; <span><?= rb_escape($privacyTitle) ?></span></nav>
      <h1><?= rb_escape($privacyTitle) ?></h1>
      <?php if ($privacySubtitle): ?>
        <p><?= rb_escape($privacySubtitle) ?></p>
      <?php endif; ?>
      <?php if ($privacyUpdatedAt): ?>
        <p class="tiny-note" style="margin-top:6px;">Last updated: <?= rb_escape($privacyUpdatedAt) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="container" style="padding:28px 16px 60px;">
    <div class="policy-grid">
      <aside class="policy-nav">
        <h4>On this page</h4>
        <ul>
          <?php
          $navIndex = 0;
          foreach ($privacySections as $key => $section):
              $title = trim((string)($section['title'] ?? ''));
              if ($title === '') {
                  continue;
              }
              $anchor = preg_replace('/[^a-z0-9]+/i', '-', (string)$key);
              $anchor = trim((string)$anchor, '-');
              if ($anchor === '') {
                  $anchor = 'section-' . (++$navIndex);
              }
              ?>
              <li><a href="#<?= rb_escape($anchor) ?>"><?= rb_escape($title) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </aside>

      <article class="policy-body">
        <?php
        $sectionIndex = 0;
        foreach ($privacySections as $key => $section):
            $title = trim((string)($section['title'] ?? ''));
            $body = $section['body'] ?? '';
            $anchor = preg_replace('/[^a-z0-9]+/i', '-', (string)$key);
            $anchor = trim((string)$anchor, '-');
            if ($anchor === '') {
                $anchor = 'section-' . (++$sectionIndex);
            }
            ?>
            <section id="<?= rb_escape($anchor) ?>" class="policy-card">
              <?php if ($title !== ''): ?>
                <h2><?= rb_escape($title) ?></h2>
              <?php endif; ?>
              <?= rb_trusted_html($body) ?>
            </section>
        <?php endforeach; ?>
      </article>
    </div>
  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="<?= rb_asset('js/main.js') ?>"></script>
  <script src="<?= rb_asset('js/pages.js') ?>"></script>
</body>

</html>
