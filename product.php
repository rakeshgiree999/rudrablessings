<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$brandTagline = $settings['brand.tagline'] ?? 'Divine Store';

$menuItems = rb_menu($pdo);
$footerBlock = rb_block($pdo, 'footer', 'legal');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    echo 'Product not found.';
    exit;
}

$product = rb_product($pdo, $id);
if (!$product) {
    http_response_code(404);
    echo 'Product not found.';
    exit;
}

$productCategories = rb_product_categories($pdo, $id);
$productImages = rb_product_images($pdo, $id);
$similarProductsRaw = rb_related_products(
    $pdo,
    $id,
    $product['category_slug'] ?? null,
    12
);

$similarSidebar = [];
$similarBottom = [];
$excludeIds = [(int)$product['id']];

foreach ($similarProductsRaw as $candidate) {
    $candidateId = (int)($candidate['id'] ?? 0);
    if ($candidateId === 0 || in_array($candidateId, $excludeIds, true)) {
        continue;
    }
    if (count($similarSidebar) < 6) {
        $similarSidebar[] = $candidate;
    } elseif (count($similarBottom) < 4) {
        $similarBottom[] = $candidate;
    } else {
        break;
    }
    $excludeIds[] = $candidateId;
}

$neededSidebar = 6 - count($similarSidebar);
$neededBottom = 4 - count($similarBottom);

$consumeFallback = static function (array $products) use (&$similarSidebar, &$similarBottom, &$neededSidebar, &$neededBottom, &$excludeIds, $product): void {
    foreach ($products as $fallback) {
        $fallbackId = (int)($fallback['id'] ?? 0);
        if ($fallbackId === 0 || in_array($fallbackId, $excludeIds, true)) {
            continue;
        }

        $normalized = [
            'id' => $fallbackId,
            'slug' => $fallback['slug'] ?? null,
            'name' => $fallback['name'] ?? 'Product',
            'price' => $fallback['price'] ?? 0,
            'image_path' => $fallback['image_path'] ?? 'media/logo.png',
            'category_slug' => $fallback['category_slug'] ?? ($product['category_slug'] ?? null),
        ];

        if ($neededSidebar > 0) {
            $similarSidebar[] = $normalized;
            $neededSidebar--;
            $excludeIds[] = $fallbackId;
            continue;
        }

        if ($neededBottom > 0) {
            $similarBottom[] = $normalized;
            $neededBottom--;
            $excludeIds[] = $fallbackId;
        }

        if ($neededSidebar <= 0 && $neededBottom <= 0) {
            break;
        }
    }
};

if ($neededSidebar > 0 || $neededBottom > 0) {
    $fallbackProducts = rb_products($pdo, [
        'limit' => 20,
        'categorySlugs' => $product['category_slug'] ? [$product['category_slug']] : [],
        'sort' => 'newest',
    ]);
    $consumeFallback($fallbackProducts);
}

if ($neededSidebar > 0 || $neededBottom > 0) {
    $fallbackProducts = rb_products($pdo, [
        'limit' => 20,
        'sort' => 'newest',
    ]);
    $consumeFallback($fallbackProducts);
}

$primaryImage = $productImages[0]['image_path'] ?? 'media/logo.png';
$primaryAlt = $productImages[0]['alt_text'] ?? $product['name'];
$shortDesc = $product['short_description'] ?? '';
$longDesc = $product['description'] ?? '';
$categorySlug = $product['category_slug'] ?? null;
$categoryName = $product['category_name'] ?? 'Product';

$specs = [
    ['Category', $categoryName],
    ['SKU', 'RB-' . str_pad((string)$product['id'], 4, '0', STR_PAD_LEFT)],
    ['Price', rb_format_price((float)$product['price'])],
    ['Availability', (int)$product['stock'] > 0 ? 'In stock' : 'Backorder'],
    ['Ships from', 'Local warehouse'],
    ['Returns', '30-day easy returns'],
];

$currentPath = 'product.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= rb_escape($product['name']) ?> | <?= rb_escape($brandName) ?></title>
  <meta name="description" content="<?= rb_escape($shortDesc) ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
  <style>
    .product-layout {
      display: grid;
      grid-template-columns: minmax(240px, 0.85fr) 1fr;
      gap: 24px;
      align-items: flex-start;
    }

    @media (max-width: 1024px) {
      .product-layout {
        grid-template-columns: 1fr;
      }
    }

    .similar-col {
      display: flex;
      flex-direction: column;
      gap: 10px;
      position: sticky;
      top: 90px;
      max-height: none;
      overflow: visible;
      padding-right: 0;
    }

    @media (max-width: 1024px) {
      .similar-col {
        position: static;
        order: 2;
      }
    }

    .sim-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(140px, 1fr));
      gap: 10px;
      align-items: stretch;
      grid-auto-rows: 1fr
    }

    .sim-card {
      display: flex;
      flex-direction: column;
      background: #fff;
      border: 1px solid #eee;
      border-radius: 12px;
      padding: 8px;
      text-decoration: none;
      color: inherit;
      transition: .2s;
      gap: 6px;
      height: 100%
    }

    .sim-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, .06)
    }

    .sim-card img {
      width: 100%;
      aspect-ratio: 1 / 1;
      object-fit: cover;
      border-radius: 10px;
      border: 1px solid #eee
    }

    .sim-card h4 {
      margin: 0;
      font-size: 13px;
      line-height: 1.25;
      flex-grow: 1
    }

    .sim-card .price {
      font-weight: 800;
      color: #b45309;
      font-size: 13px;
      margin-top: 2px
    }

    .pv-meta {
      display: flex;
      gap: 8px;
      align-items: center;
      margin: 8px 0 12px
    }

    .pv-badge {
      background: #fef3c7;
      color: #92400e;
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 12px
    }

    .pv-tags {
      margin-top: 10px
    }

    .pv-tags .tag {
      display: inline-block;
      margin: 0 6px 6px 0;
      padding: 4px 10px;
      border: 1px solid #eee;
      border-radius: 999px;
      font-size: 12px;
      color: #374151;
      background: #fff
    }

    .pv-sections {
      margin-top: 18px;
      display: grid;
      gap: 18px
    }

    .pv-panel {
      background: #fff;
      border: 1px solid #eee;
      border-radius: 14px;
      padding: 14px 16px
    }

    .pv-panel h3 {
      margin: 0 0 8px;
      font-size: 18px
    }

    .specs {
      width: 100%;
      border-collapse: collapse
    }

    .specs td {
      padding: 8px 10px;
      border-bottom: 1px dashed #edf2f7
    }

    .specs td:first-child {
      color: #6b7280;
      width: 38%
    }

    .pv-gallery .pv-main {
      border: 1px solid #eee;
      border-radius: 12px;
      width: 100%;
      max-width: 560px;
      max-height: min(520px, 70vh);
      aspect-ratio: 1 / 1;
      object-fit: cover;
      background: #fff;
      display: block;
    }

    @media (max-width: 768px) {
      .pv-gallery .pv-main {
        max-height: 60vh;
      }
    }

    .pv-thumbs {
      display: flex;
      gap: 8px;
      margin-top: 10px;
      flex-wrap: wrap
    }

    .pv-thumbs img {
      width: 72px;
      height: 72px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #eee;
      cursor: pointer
    }

    @media (max-width: 768px) {
      .pv-thumbs img {
        width: 60px;
        height: 60px;
      }
    }

    .pv-thumbs img.active {
      border-color: #f59e0b;
      box-shadow: 0 0 0 2px rgba(245, 158, 11, .25);
    }

    .crumbs {
      font-size: 12px;
      color: #6b7280;
      margin: 8px 0 0
    }

    .crumbs a {
      color: inherit
    }

    .buy-wrap {
      margin-top: 24px;
      width: 100%;
    }

    .buy-card {
      position: sticky;
      top: 90px;
      background: #fff;
      border: 1px solid #eee;
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 10px 25px rgba(15, 23, 42, .08);
      display: grid;
      gap: 10px;
      width: 100%;
    }

    .buy-price {
      font-size: 24px;
      font-weight: 800
    }

    .buy-badge {
      color: #059669;
      font-weight: 700;
      margin: 4px 0
    }

    .buy-qty select {
      width: 80px;
      padding: 8px 10px;
      border-radius: 8px;
      border: 1px solid #e5e7eb
    }

    .buy-actions {
      display: flex;
      flex-direction: column;
      gap: 10px
    }

    .btn-amz {
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: 10px;
      font-weight: 700;
      cursor: pointer;
      transition: background .2s ease
    }

    .btn-amz:hover {
      background: #1d4ed8
    }

    .btn-buy-now {
      background: #f59e0b;
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: 10px;
      font-weight: 700;
      cursor: pointer;
      transition: background .2s ease
    }

    .btn-buy-now:hover {
      background: #d97706
    }

    @media (max-width: 640px) {
      .buy-wrap {
        max-width: 100%;
      }

      .buy-card {
        position: static;
        box-shadow: none;
      }

      .product-layout {
        gap: 18px;
      }
    }

    .buy-meta {
      font-size: 13px;
      color: #555;
      margin: 2px 0
    }

    .buy-meta .label {
      color: #6b7280;
      margin-right: 4px
    }

    .btn-wish-row button {
      width: 100%;
      border: 1px solid #e5e7eb;
      background: #fff;
      border-radius: 8px;
      padding: 8px 10px;
      cursor: pointer
    }
  </style>
</head>

<body data-product-source="server" data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="container" style="padding:20px 16px 0;">
    <div class="crumbs">
      <a href="index.php">Home</a> &raquo;
      <?php if ($categorySlug): ?>
        <a href="shop.php?cat=<?= rb_escape($categorySlug) ?>"><?= rb_escape($categoryName) ?></a> &raquo;
      <?php else: ?>
        <a href="shop.php">Shop</a> &raquo;
      <?php endif; ?>
      <span><?= rb_escape($product['name']) ?></span>
    </div>
  </section>

  <section class="container" style="padding:30px 16px 60px;">
    <div class="product-layout">
      <!-- Similar items -->
      <aside class="similar-col" id="similarList">
        <h3>Similar Items</h3>
        <div class="sim-grid">
          <?php foreach ($similarSidebar as $similar): ?>
            <a class="sim-card" href="product.php?id=<?= (int)$similar['id'] ?>">
              <img src="<?= rb_escape($similar['image_path'] ?? 'media/logo.png') ?>" alt="<?= rb_escape($similar['name']) ?>">
              <h4><?= rb_escape($similar['name']) ?></h4>
              <div class="price"><?= rb_escape(rb_format_price((float)$similar['price'])) ?></div>
            </a>
          <?php endforeach; ?>
          <?php if (!$similarSidebar): ?>
            <p style="grid-column:1/-1;font-size:13px;color:#6b7280;">Explore other categories for more items.</p>
          <?php endif; ?>
        </div>
        <a class="btn secondary" href="<?= $categorySlug ? 'shop.php?cat=' . urlencode($categorySlug) : 'shop.php' ?>" style="margin-top:4px;">Browse all</a>
      </aside>

      <!-- Main -->
      <section class="product-view">
        <div class="pv-gallery">
          <img class="pv-main" id="pvMain" src="<?= rb_escape($primaryImage) ?>" alt="<?= rb_escape($primaryAlt) ?>">
          <div class="pv-thumbs" id="pvThumbs">
            <?php foreach ($productImages as $image): ?>
              <img src="<?= rb_escape($image['image_path']) ?>" alt="<?= rb_escape($image['alt_text'] ?? $product['name']) ?>" data-fallback="<?= rb_escape($primaryImage) ?>">
            <?php endforeach; ?>
          </div>
        </div>

        <div class="pv-info">
          <h1 id="pvName"><?= rb_escape($product['name']) ?></h1>
          <div class="pv-meta">
            <span class="pv-badge" id="pvBadge"><?= rb_escape($categoryName) ?></span>
            <span class="muted"><?= (int)$product['stock'] > 0 ? 'In stock' : 'Backorder' ?></span>
          </div>

          <div class="pv-price" id="pvPrice"><?= rb_escape(rb_format_price((float)$product['price'])) ?></div>
          <p class="muted" id="pvDesc"><?= rb_escape($shortDesc) ?></p>

          <div class="pv-cta" style="margin:12px 0 8px; display:flex; gap:8px; flex-wrap:wrap;">
            <button id="pvAdd" class="btn btn-add" type="button" data-id="<?= (int)$product['id'] ?>" data-name="<?= rb_escape($product['name']) ?>" data-price="<?= rb_escape((string)$product['price']) ?>">Add to Cart</button>
            <button id="pvWish" class="btn secondary" type="button" data-id="<?= (int)$product['id'] ?>">Add to Wishlist</button>
            <a class="btn secondary" href="shipping.php">Shipping &amp; Returns</a>
          </div>

          <div class="pv-tags" id="pvTags">
            <?php foreach ($productCategories as $cat): ?>
              <span class="tag"><?= rb_escape($cat['name']) ?></span>
            <?php endforeach; ?>
            <?php if (!$productCategories): ?>
              <span class="tag">RudraBlessings</span>
            <?php endif; ?>
          </div>

          <div class="pv-sections">
            <div class="pv-panel">
              <h3>Description</h3>
              <div><?= $longDesc ?: '<p>' . rb_escape($shortDesc) . '</p>' ?></div>
            </div>

            <div class="pv-panel">
              <h3>Specifications</h3>
              <table class="specs" id="pvSpecs">
                <?php foreach ($specs as [$label, $value]): ?>
                  <tr>
                    <td><?= rb_escape($label) ?></td>
                    <td><?= is_string($value) ? rb_escape($value) : $value ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            </div>
          </div>

          <div class="buy-wrap">
            <div class="buy-card" id="buyCard">
              <div class="buy-price" id="buyPrice"><?= rb_escape(rb_format_price((float)$product['price'])) ?></div>
              <div class="buy-badge"><?= (int)$product['stock'] > 0 ? 'In stock' : 'Backorder' ?></div>
              <div class="buy-qty">
                <label class="muted" for="buyQty">Quantity:</label>
                <select id="buyQty" name="qty">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="buy-actions">
                <button id="buyAdd" class="btn-amz" type="button" data-id="<?= (int)$product['id'] ?>" data-name="<?= rb_escape($product['name']) ?>" data-price="<?= rb_escape((string)$product['price']) ?>">Add to Cart</button>
                <button id="buyNow" class="btn-buy-now" type="button" data-id="<?= (int)$product['id'] ?>" data-name="<?= rb_escape($product['name']) ?>" data-price="<?= rb_escape((string)$product['price']) ?>">Buy Now</button>
              </div>
              <div class="buy-meta"><span class="label">Seller</span>RudraBlessings</div>
              <div class="buy-meta"><span class="label">Returns</span>30-day easy returns</div>
              <div class="buy-meta"><span class="label">Gift wrap</span>Available at checkout</div>
              <div class="btn-wish-row">
                <button id="buyWish" type="button" data-id="<?= (int)$product['id'] ?>">Add to Wishlist</button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </section>

  <section class="section container" style="padding-top:0;">
    <h3>You May Also Like</h3>
    <div class="shop-grid" id="pvSuggest" data-render="server">
      <?php foreach ($similarBottom as $card): ?>
        <div class="product-card">
          <a class="card-link" href="product.php?id=<?= (int)$card['id'] ?>">
            <div class="product-thumb">
              <span class="badge-cat"><?= rb_escape($categoryName) ?></span>
              <img src="<?= rb_escape($card['image_path'] ?? 'media/logo.png') ?>" alt="<?= rb_escape($card['name']) ?>">
            </div>
            <div class="product-info">
              <h4><?= rb_escape($card['name']) ?></h4>
              <p class="price"><?= rb_escape(rb_format_price((float)$card['price'])) ?></p>
            </div>
          </a>
          <button class="btn btn-add" data-id="<?= (int)$card['id'] ?>" data-name="<?= rb_escape($card['name']) ?>" data-price="<?= rb_escape((string)$card['price']) ?>">Add to Cart</button>
          <button class="btn secondary btn-wish" data-id="<?= (int)$card['id'] ?>" aria-pressed="false">Add to Wishlist</button>
        </div>
      <?php endforeach; ?>
      <?php if (!$similarBottom): ?>
        <p style="grid-column:1/-1;text-align:center;color:#6b7280;">More products coming soon.</p>
      <?php endif; ?>
    </div>
  </section>

  
  <?php include __DIR__ . '/includes/footer.php'; ?>


  <script src="<?= rb_asset('js/main.js') ?>"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const mainImage = document.getElementById('pvMain');
      const thumbContainer = document.getElementById('pvThumbs');
      if (!mainImage || !thumbContainer) {
        return;
      }
      const thumbs = Array.from(thumbContainer.querySelectorAll('img'));
      if (!thumbs.length) {
        return;
      }
      const setActive = (img) => {
        thumbs.forEach(t => t.classList.toggle('active', t === img));
        mainImage.src = img.src;
        mainImage.alt = img.alt || mainImage.alt;
      };
      thumbs.forEach(img => {
        img.addEventListener('click', () => setActive(img));
        img.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            setActive(img);
          }
        });
        img.setAttribute('tabindex', '0');
      });
      setActive(thumbs[0]);
    });
  </script>
</body>

</html>









