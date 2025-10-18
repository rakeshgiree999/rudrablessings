<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$currentPath = 'blog.php';
$blogPosts = rb_blog_posts($pdo, 9);
$blogMetaDescription = $settings['blog.meta.description'] ?? 'RudraBlessings blog: guides on Rudraksha, crystals, incense and spiritual living.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Blog | <?= rb_escape($brandName) ?></title>
  <meta name="description" content="<?= rb_escape($blogMetaDescription) ?>">
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="rb-blog-list">
    <div class="container">
      <h1 class="rb-title">Our Blog</h1>
      <p class="rb-subtitle">Spiritual wisdom, rituals &amp; healing guides</p>

      <div class="rb-blog-grid">
        <?php if (empty($blogPosts)): ?>
          <p class="muted">Articles are on their way. Please check back soon.</p>
        <?php else: ?>
          <?php foreach ($blogPosts as $post): ?>
            <?php
            $slug = (string)($post['slug'] ?? '');
            $url = 'blog-single.php?slug=' . rawurlencode($slug !== '' ? $slug : (string)($post['id'] ?? ''));
            $hero = trim((string)($post['hero_image'] ?? '')) ?: (string)($settings['blog.default_image'] ?? 'media/blog1.jpg');
            $published = rb_format_date($post['published_at'] ?? null, 'M d, Y');
            ?>
            <article class="rb-blog-card">
              <img src="<?= rb_escape($hero) ?>" alt="<?= rb_escape($post['title'] ?? 'Blog post') ?>">
              <div class="rb-blog-info">
                <h3><?= rb_escape($post['title'] ?? '') ?></h3>
                <?php if (!empty($post['excerpt'])): ?>
                  <p><?= rb_escape($post['excerpt']) ?></p>
                <?php endif; ?>
                <span class="meta">
                  <?php if ($published): ?>
                    <?= rb_escape($published) ?>
                    <span aria-hidden="true">&bull;</span>
                  <?php endif; ?>
                  <?= rb_escape($post['author_name'] ?? 'Admin') ?>
                </span>
                <a href="<?= rb_escape($url) ?>" class="btn-read">Read More</a>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>
