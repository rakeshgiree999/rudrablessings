<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$currentPath = 'terms.php';
$termsBlocks = rb_blocks($pdo, 'terms');
$termsHero = $termsBlocks['hero'] ?? [];
unset($termsBlocks['hero']);
$termsSections = $termsBlocks;
$termsUpdatedAt = rb_format_date($settings['terms.last_updated'] ?? null);
$termsTitle = $termsHero['title'] ?? 'Terms and Conditions';
$termsSubtitle = $termsHero['subtitle'] ?? 'Read the terms and conditions for using RudraBlessings and purchasing products.';
$termsMetaDescription = $termsSubtitle ?: 'Read the terms and conditions for using RudraBlessings and purchasing products.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= rb_escape($termsTitle) ?> | <?= rb_escape($brandName) ?></title>
  <meta name="description" content="<?= rb_escape($termsMetaDescription) ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/pages.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="contact-hero">
    <div class="wrap">
      <nav class="breadcrumbs"><a href="index.php">Home</a> &raquo; <span><?= rb_escape($termsTitle) ?></span></nav>
      <h1><?= rb_escape($termsTitle) ?></h1>
      <?php if ($termsSubtitle): ?>
        <p><?= rb_escape($termsSubtitle) ?></p>
      <?php endif; ?>
      <?php if ($termsUpdatedAt): ?>
        <p class="tiny-note" style="margin-top:6px;">Last updated: <?= rb_escape($termsUpdatedAt) ?></p>
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
          foreach ($termsSections as $key => $section):
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
        foreach ($termsSections as $key => $section):
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
