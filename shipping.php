<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$currentPath = 'shipping.php';
$shippingBlocks = rb_blocks($pdo, 'shipping');
$shippingHero = $shippingBlocks['hero'] ?? [];
unset($shippingBlocks['hero']);
$shippingSections = $shippingBlocks;
$shippingUpdatedAt = rb_format_date($settings['shipping.last_updated'] ?? null);
$shippingTitle = $shippingHero['title'] ?? 'Shipping & Returns';
$shippingSubtitle = $shippingHero['subtitle'] ?? 'Clear timelines, fair policies, and easy returns so you can shop with confidence.';
$shippingMetaDescription = $shippingSubtitle ?: 'Shipping timelines, rates, returns and support for RudraBlessings.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= rb_escape($shippingTitle) ?> | <?= rb_escape($brandName) ?></title>
  <meta name="description" content="<?= rb_escape($shippingMetaDescription) ?>">
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/pages.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="contact-hero">
    <div class="wrap">
      <nav class="breadcrumbs"><a href="index.php">Home</a> &raquo; <span><?= rb_escape($shippingTitle) ?></span></nav>
      <h1><?= rb_escape($shippingTitle) ?></h1>
      <?php if ($shippingSubtitle): ?>
        <p><?= rb_escape($shippingSubtitle) ?></p>
      <?php endif; ?>
      <?php if ($shippingUpdatedAt): ?>
        <p class="tiny-note" style="margin-top:6px;">Last updated: <?= rb_escape($shippingUpdatedAt) ?></p>
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
          foreach ($shippingSections as $key => $section):
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
        foreach ($shippingSections as $key => $section):
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
