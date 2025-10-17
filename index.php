<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$brandTagline = $settings['brand.tagline'] ?? 'Divine Store';

$menuItems = rb_menu($pdo);
$heroBlock = rb_block($pdo, 'home', 'hero');
$categoryIntro = rb_block($pdo, 'home', 'category-intro');
$featuredBlock = rb_block($pdo, 'home', 'featured-title');
$arrivalsBlock = rb_block($pdo, 'home', 'new-arrivals');
$categories = rb_categories($pdo);
$featuredProducts = rb_products($pdo, ['limit' => 8, 'featured' => true, 'sort' => 'newest']);
if (!$featuredProducts) {
  $featuredProducts = rb_products($pdo, ['limit' => 8, 'sort' => 'newest']);
}
$newArrivalProducts = rb_products($pdo, ['limit' => 8, 'new_arrival' => true]);
if (!$newArrivalProducts) {
  $newArrivalProducts = rb_products($pdo, ['limit' => 8, 'sort' => 'newest']);
}
$footerBlock = rb_block($pdo, 'footer', 'legal');

$currentPath = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
if ($currentPath === '' || $currentPath === DIRECTORY_SEPARATOR) {
  $currentPath = 'index.php';
}

$heroImagePath = trim((string) ($heroBlock['media_path'] ?? ''));
$heroTitle = $heroBlock['title'] ?? 'Discover the Power of Crystals & Rudraksha';
$heroSubtitle = $heroBlock['subtitle'] ?? 'Special Offer';
$heroBody = $heroBlock['body'] ?? 'Curated malas, crystals and incense to anchor your daily rituals.';
$heroCtaLabel = $heroBlock['cta_label'] ?? 'Shop Now';
$heroCtaUrl = $heroBlock['cta_url'] ?? 'shop.php';

$heroImages = [];
$heroImageIndex = [];
$appendHeroImage = static function (string $path) use (&$heroImages, &$heroImageIndex): void {
  $path = trim($path);
  if ($path === '') {
    return;
  }
  $key = strtolower($path);
  if (isset($heroImageIndex[$key])) {
    return;
  }
  $heroImages[] = $path;
  $heroImageIndex[$key] = true;
};

$appendHeroImage($heroImagePath !== '' ? $heroImagePath : 'media/hero1.jpg');

$mediaDir = __DIR__ . DIRECTORY_SEPARATOR . 'media';
if (is_dir($mediaDir)) {
  $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
  foreach ($extensions as $ext) {
    $pattern = $mediaDir . DIRECTORY_SEPARATOR . 'hero*.' . $ext;
    $files = glob($pattern) ?: [];
    if (!$files && strtolower($ext) === 'jpg' && defined('GLOB_BRACE')) {
      // Attempt brace glob when available (covers mixed-case variants).
      $files = glob($mediaDir . DIRECTORY_SEPARATOR . 'hero*.{' . $ext . ',' . strtoupper($ext) . '}', GLOB_BRACE) ?: [];
    }
    foreach ($files as $file) {
      if (!is_string($file) || $file === '') {
        continue;
      }
      $relative = 'media/' . basename($file);
      $relative = str_replace('\\', '/', $relative);
      $appendHeroImage($relative);
    }
  }
}

if (!$heroImages) {
  $appendHeroImage('media/hero1.jpg');
}

$heroImage = $heroImages[0];
$heroImagesJson = json_encode($heroImages, JSON_UNESCAPED_SLASHES);
if (!is_string($heroImagesJson)) {
  $heroImagesJson = '[]';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= rb_escape($brandName) ?> | <?= rb_escape($brandTagline) ?></title>
  <meta name="description" content="<?= rb_escape($heroBody) ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <!-- HERO BANNER -->
  <section class="rb-hero" data-hero-images="<?= rb_escape($heroImagesJson) ?>"
    style="--hero:url('<?= rb_escape($heroImage) ?>');">
    <div class="rb-hero-bg"></div>
    <div class="rb-hero-content">
      <?php if ($heroSubtitle): ?>
        <h2 class="rb-hero-kicker"><?= rb_escape($heroSubtitle) ?></h2>
      <?php endif; ?>
      <h1 class="rb-hero-title"><?= rb_escape($heroTitle) ?></h1>
      <?php if ($heroBody): ?>
        <p class="rb-hero-sub"><?= rb_escape($heroBody) ?></p>
      <?php endif; ?>
      <a href="<?= rb_escape($heroCtaUrl) ?>" class="btn rb-btn-primary"><?= rb_escape($heroCtaLabel) ?></a>
    </div>
  </section>

  <!-- CATEGORIES -->
  <section class="section container">
    <h2><?= rb_escape($categoryIntro['title'] ?? 'Shop by Category') ?></h2>
    <?php if (!empty($categoryIntro['body'])): ?>
      <p class="muted"><?= rb_escape($categoryIntro['body']) ?></p>
    <?php endif; ?>
    <div class="cat-grid">
      <?php foreach ($categories as $category): ?>
        <a class="cat-card" href="shop.php?cat=<?= urlencode($category['slug']) ?>">
          <img src="<?= rb_escape($category['image_path'] ?? 'media/logo.png') ?>"
            alt="<?= rb_escape($category['name']) ?>">
          <h3><?= rb_escape($category['name']) ?></h3>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- FEATURED -->
  <section class="section container">
    <h2><?= rb_escape($featuredBlock['title'] ?? 'Featured Products') ?></h2>
    <?php if (!empty($featuredBlock['body'])): ?>
      <p class="muted"><?= rb_escape($featuredBlock['body']) ?></p>
    <?php endif; ?>
    <div class="shop-grid" id="featuredProducts" data-render="server">
      <?php foreach ($featuredProducts as $product): ?>
        <div class="product-card">
          <a class="card-link" href="product.php?id=<?= (int) $product['id'] ?>">
            <div class="product-thumb">
              <span class="badge-cat"><?= rb_escape($product['category_name'] ?? 'Product') ?></span>
              <img src="<?= rb_escape($product['image_path'] ?? 'media/logo.png') ?>"
                alt="<?= rb_escape($product['image_alt'] ?: $product['name']) ?>">
            </div>
            <div class="product-info">
              <h4><?= rb_escape($product['name']) ?></h4>
              <p class="price"><?= rb_escape(rb_format_price((float) $product['price'])) ?></p>
            </div>
          </a>
          <button class="btn btn-add" data-id="<?= (int) $product['id'] ?>" data-name="<?= rb_escape($product['name']) ?>"
            data-price="<?= rb_escape((string) $product['price']) ?>">Add to Cart</button>
          <button class="btn secondary btn-wish" data-id="<?= (int) $product['id'] ?>" aria-pressed="false">Add to
            Wishlist</button>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- NEW ARRIVALS -->
  <section class="section container">
    <h2><?= rb_escape($arrivalsBlock['title'] ?? 'New Arrivals') ?></h2>
    <?php if (!empty($arrivalsBlock['body'])): ?>
      <p class="muted"><?= rb_escape($arrivalsBlock['body']) ?></p>
    <?php endif; ?>
    <div class="shop-grid" id="newArrivals" data-render="server">
      <?php foreach ($newArrivalProducts as $product): ?>
        <div class="product-card">
          <a class="card-link" href="product.php?id=<?= (int) $product['id'] ?>">
            <div class="product-thumb">
              <span class="badge-cat"><?= rb_escape($product['category_name'] ?? 'Product') ?></span>
              <img src="<?= rb_escape($product['image_path'] ?? 'media/logo.png') ?>"
                alt="<?= rb_escape($product['image_alt'] ?: $product['name']) ?>">
            </div>
            <div class="product-info">
              <h4><?= rb_escape($product['name']) ?></h4>
              <p class="price"><?= rb_escape(rb_format_price((float) $product['price'])) ?></p>
            </div>
          </a>
          <button class="btn btn-add" data-id="<?= (int) $product['id'] ?>" data-name="<?= rb_escape($product['name']) ?>"
            data-price="<?= rb_escape((string) $product['price']) ?>">Add to Cart</button>
          <button class="btn secondary btn-wish" data-id="<?= (int) $product['id'] ?>" aria-pressed="false">Add to
            Wishlist</button>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php include __DIR__ . '/includes/footer.php'; ?>


  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>
