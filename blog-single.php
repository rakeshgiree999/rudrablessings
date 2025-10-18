<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$currentPath = 'blog.php';

$slugParam = trim((string)($_GET['slug'] ?? ''));
$idParam = isset($_GET['id']) ? (int)$_GET['id'] : null;

$post = null;
if ($slugParam !== '') {
    $post = rb_blog_post($pdo, ['slug' => $slugParam]);
}
if ($post === null && $idParam) {
    $post = rb_blog_post($pdo, ['id' => $idParam]);
}

$adminStmt = $pdo->prepare("SELECT first_name, last_name, email, avatar_path FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1");
$adminStmt->execute();
$adminProfile = $adminStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
$defaultAdminName = $adminProfile ? rb_user_display_name($adminProfile) : '';
if ($defaultAdminName === '') {
    $defaultAdminName = 'Admin';
}
$adminAvatarMeta = $adminProfile ? rb_user_avatar($adminProfile) : rb_user_avatar(null);
$defaultAdminAvatar = $adminAvatarMeta['url'];
$defaultAdminBio = trim((string)($settings['blog.admin_bio'] ?? 'Sharing grounded practices.'));

if ($post === null) {
    http_response_code(404);
    $post = [
        'id' => 0,
        'slug' => '',
        'title' => 'Article Not Found',
        'excerpt' => 'The article you are looking for could not be found.',
        'body' => '<p>Return to the <a href="blog.php">blog</a> to explore our latest guides.</p>',
        'hero_image' => $settings['blog.default_image'] ?? 'media/blog1.jpg',
        'author_name' => $defaultAdminName,
        'author_avatar' => $defaultAdminAvatar,
        'author_bio' => $defaultAdminBio,
        'published_at' => null,
    ];
}

$postTitle = (string)($post['title'] ?? 'Blog Article');
$postExcerpt = trim((string)($post['excerpt'] ?? ''));
$heroImage = trim((string)($post['hero_image'] ?? '')) ?: (string)($settings['blog.default_image'] ?? 'media/blog1.jpg');
$authorName = trim((string)($post['author_name'] ?? '')) ?: $defaultAdminName;
$authorAvatar = trim((string)($post['author_avatar'] ?? ''));
$normalizeAuthorAvatar = $authorAvatar !== '';
if ($normalizeAuthorAvatar && strncmp($authorAvatar, 'data:', 5) !== 0 && !preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $authorAvatar)) {
    $relativePath = ltrim($authorAvatar, '/\\');
    $diskPath = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;
    if (!is_file($diskPath)) {
        $authorAvatar = '';
        $normalizeAuthorAvatar = false;
    }
}
if ($authorAvatar === '') {
    $authorAvatar = $defaultAdminAvatar;
    $normalizeAuthorAvatar = false;
}
if ($normalizeAuthorAvatar && strncmp($authorAvatar, 'data:', 5) !== 0 && !preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $authorAvatar)) {
    $authorAvatar = rb_asset(ltrim($authorAvatar, '/\\'));
}
$authorBio = trim((string)($post['author_bio'] ?? '')) ?: $defaultAdminBio;
$publishedDate = rb_format_date($post['published_at'] ?? null, 'M d, Y');
$metaDescription = $postExcerpt !== '' ? $postExcerpt : (string)($settings['blog.meta.description'] ?? 'RudraBlessings blog: guides on Rudraksha, crystals, incense and spiritual living.');
$articleBody = (string)($post['body'] ?? '');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$queryPath = $post['slug'] !== '' ? '/blog-single.php?slug=' . rawurlencode((string)$post['slug']) : ($_SERVER['REQUEST_URI'] ?? '/blog-single.php');
$canonicalUrl = sprintf('%s://%s%s', $scheme, $host, $queryPath);

$twitterShareUrl = 'https://twitter.com/intent/tweet?url=' . rawurlencode($canonicalUrl) . '&text=' . rawurlencode($postTitle);
$relatedPosts = $post['id'] ? rb_blog_related_posts($pdo, (int)$post['id'], 3) : rb_blog_posts($pdo, 3);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= rb_escape($postTitle) ?> | <?= rb_escape($brandName) ?></title>
  <meta name="description" content="<?= rb_escape($metaDescription) ?>">
  <link rel="canonical" href="<?= rb_escape($canonicalUrl) ?>">
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/pages.css') ?>">
  <meta property="og:type" content="article">
  <meta property="og:title" content="<?= rb_escape($postTitle) ?>">
  <meta property="og:description" content="<?= rb_escape($metaDescription) ?>">
  <meta property="og:image" content="<?= rb_escape($heroImage) ?>">
  <meta property="og:url" content="<?= rb_escape($canonicalUrl) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= rb_escape($postTitle) ?>">
  <meta name="twitter:description" content="<?= rb_escape($metaDescription) ?>">
  <meta name="twitter:image" content="<?= rb_escape($heroImage) ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="blog-hero">
    <div class="bg" style="background-image: url('<?= rb_escape($heroImage) ?>');"></div>
    <div class="wrap">
      <nav class="breadcrumbs">
        <a href="index.php">Home</a> &raquo; <a href="blog.php">Blog</a> &raquo; <span><?= rb_escape($postTitle) ?></span>
      </nav>
      <div class="blog-meta">
        <?php if ($publishedDate): ?>
          <span><?= rb_escape($publishedDate) ?></span>
          <span class="dot" aria-hidden="true"></span>
        <?php endif; ?>
        <span><?= rb_escape($authorName) ?></span>
      </div>
      <h1 class="blog-title"><?= rb_escape($postTitle) ?></h1>
      <?php if ($postExcerpt !== ''): ?>
        <p class="blog-sub"><?= rb_escape($postExcerpt) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="article-wrap">
    <article class="article-body">
      <?= rb_trusted_html($articleBody) ?>
    </article>

    <div class="author-card">
      <img src="<?= rb_escape($authorAvatar) ?>" alt="<?= rb_escape($authorName) ?>">
      <div>
        <div class="name"><?= rb_escape($authorName) ?></div>
        <div class="muted"><?= rb_escape($authorBio) ?></div>
      </div>
      <div class="share-row">
        <button id="copyLinkBtn" type="button" class="btn-link" data-url="<?= rb_escape($canonicalUrl) ?>">Copy Link</button>
        <a id="shareTw" class="btn-link" href="<?= rb_escape($twitterShareUrl) ?>" target="_blank" rel="noopener">Share on X</a>
      </div>
    </div>
  </section>

  <section class="suggest-wrap">
    <h3>You may also like</h3>
    <div class="suggest-grid">
      <?php if (empty($relatedPosts)): ?>
        <p class="muted">More articles coming soon.</p>
      <?php else: ?>
        <?php foreach ($relatedPosts as $related): ?>
          <?php
          $relatedUrl = 'blog-single.php?slug=' . rawurlencode((string)($related['slug'] ?? ''));
          $relatedImage = trim((string)($related['hero_image'] ?? '')) ?: (string)($settings['blog.default_image'] ?? 'media/blog1.jpg');
          $relatedDate = rb_format_date($related['published_at'] ?? null, 'M d, Y');
          ?>
          <div class="suggest-card">
            <img src="<?= rb_escape($relatedImage) ?>" alt="<?= rb_escape($related['title'] ?? 'Blog post') ?>">
            <div class="info">
              <h4><?= rb_escape($related['title'] ?? '') ?></h4>
              <?php if (!empty($related['excerpt'])): ?>
                <p><?= rb_escape($related['excerpt']) ?></p>
              <?php endif; ?>
              <p class="meta">
                <?php if ($relatedDate): ?>
                  <span><?= rb_escape($relatedDate) ?></span>
                  <span class="dot" aria-hidden="true"></span>
                <?php endif; ?>
                <span><?= rb_escape($related['author_name'] ?? $defaultAdminName) ?></span>
              </p>
              <a class="btn" href="<?= rb_escape($relatedUrl) ?>">Read More</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="<?= rb_asset('js/main.js') ?>"></script>
  <script src="<?= rb_asset('js/pages.js') ?>"></script>
</body>

</html>
