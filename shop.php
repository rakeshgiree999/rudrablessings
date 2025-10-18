<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$brandTagline = $settings['brand.tagline'] ?? 'Divine Store';

$menuItems = rb_menu($pdo);
$categories = rb_categories($pdo);
$footerBlock = rb_block($pdo, 'footer', 'legal');

// Filters
$selectedCats = [];
if (isset($_GET['cat'])) {
    $selectedCats = array_filter((array)$_GET['cat'], static fn ($slug) => is_string($slug) && $slug !== '');
}

$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$priceMax = null;
if (isset($_GET['price_max']) && is_numeric($_GET['price_max'])) {
    $priceMax = max(0, (float)$_GET['price_max']);
}

$sortOptions = ['default', 'price-asc', 'price-desc', 'name-asc', 'name-desc', 'newest'];
$sortParam = isset($_GET['sort']) ? (string)$_GET['sort'] : null;
$sort = $sortParam && in_array($sortParam, $sortOptions, true) ? $sortParam : 'default';

$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$productFilters = [
    'categorySlugs' => $selectedCats,
    'search' => $search ?: null,
    'priceMax' => $priceMax ?: null,
    'sort' => $sort,
    'limit' => $perPage,
    'offset' => $offset,
];

$products = rb_products($pdo, $productFilters);
$totalProducts = rb_products_count($pdo, $productFilters);
$totalPages = (int)max(1, ceil($totalProducts / $perPage));

// For sidebar counts (without selected categories but respecting search/price)
$categoryCounts = [];
foreach ($categories as $category) {
    $categoryCounts[$category['slug']] = rb_products_count($pdo, [
        'categorySlugs' => [$category['slug']],
        'search' => $search ?: null,
        'priceMax' => $priceMax ?: null,
    ]);
}

// Determine max price for slider
$maxPriceRow = $pdo->query("SELECT MAX(price) FROM products WHERE status = 'active'")->fetchColumn();
$priceSliderMax = $maxPriceRow ? ceil((float)$maxPriceRow / 10) * 10 : 200;
$priceValue = $priceMax ? min($priceMax, $priceSliderMax) : $priceSliderMax;

$suggestProducts = rb_products($pdo, ['limit' => 4, 'sort' => 'newest']);

$currentPath = 'shop.php';

function rb_query(array $params): string
{
    $clean = [];
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            $values = array_values(array_filter($value, static fn ($v) => $v !== null && $v !== ''));
            if ($values) {
                $clean[$key] = $values;
            }
        } elseif ($value !== null && $value !== '') {
            $clean[$key] = $value;
        }
    }
    return http_build_query($clean);
}

function rb_pagination_link(int $page, array $currentParams): string
{
    $params = $currentParams;
    $params['page'] = $page;
    return 'shop.php?' . rb_query($params);
}

$baseParams = [
    'search' => $search,
    'price_max' => $priceMax,
    'sort' => $sort,
    'cat' => $selectedCats,
];

$headerSearchValue = $search;
$headerSearchHidden = [];
foreach ($selectedCats as $slug) {
    if ($slug !== '') {
        $headerSearchHidden[] = ['name' => 'cat[]', 'value' => $slug];
    }
}
if ($priceMax !== null) {
    $headerSearchHidden[] = ['name' => 'price_max', 'value' => (string)$priceMax];
}
$headerSearchHidden[] = ['name' => 'sort', 'value' => $sort];

$activeFilterCount = 0;
if (!empty($selectedCats)) {
    $activeFilterCount++;
}
if ($priceMax !== null && $priceMax < $priceSliderMax) {
    $activeFilterCount++;
}
if ($search !== '') {
    $activeFilterCount++;
}
if ($sort !== 'default') {
    $activeFilterCount++;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= rb_escape($brandName) ?> | Shop</title>
  <meta name="description" content="Browse the full RudraBlessings collection of malas, crystals and incense.">
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="page-hero" style="background:var(--rb-cream);padding:40px 0;border-bottom:1px solid #eee">
    <div class="container">
      <h1>Shop All</h1>
      <p class="muted">Explore our full collection</p>
    </div>
  </section>

  <section class="shop-container container">
    <aside id="shopFilters" class="shop-filters">
      <form id="filterForm" method="get">
        <h3>Filter</h3>
        <?php foreach ($categories as $category): ?>
          <?php
          $slug = (string)$category['slug'];
          $checked = !$selectedCats ? true : in_array($slug, $selectedCats, true);
          ?>
          <label>
            <input type="checkbox" name="cat[]" value="<?= rb_escape($slug) ?>"<?= $checked ? ' checked' : '' ?>>
            <?= rb_escape($category['name']) ?>
            <span class="count">(<?= (int)($categoryCounts[$slug] ?? 0) ?>)</span>
          </label>
        <?php endforeach; ?>

        <h4>Price</h4>
        <input type="range" id="priceRange" name="price_max" min="0" max="<?= rb_escape((string)$priceSliderMax) ?>" value="<?= rb_escape((string)$priceValue) ?>">
        <div class="price-display">$0 - <span id="priceMax"><?= rb_escape((string)$priceValue) ?></span></div>

        <h4>Search</h4>
        <input type="search" name="search" placeholder="Find products..." value="<?= rb_escape($search) ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">

        <h4>Sort By</h4>
        <select name="sort" id="sortBy" style="width:100%;padding:10px;border-radius:10px;border:1px solid #ddd;">
          <option value="default"<?= $sort === 'default' ? ' selected' : '' ?>>Default</option>
          <option value="price-asc"<?= $sort === 'price-asc' ? ' selected' : '' ?>>Price: Low to High</option>
          <option value="price-desc"<?= $sort === 'price-desc' ? ' selected' : '' ?>>Price: High to Low</option>
          <option value="name-asc"<?= $sort === 'name-asc' ? ' selected' : '' ?>>Name: A to Z</option>
          <option value="name-desc"<?= $sort === 'name-desc' ? ' selected' : '' ?>>Name: Z to A</option>
          <option value="newest"<?= $sort === 'newest' ? ' selected' : '' ?>>Newest</option>
        </select>

        <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
          <button type="submit" class="btn">Apply Filters</button>
          <a href="shop.php" class="btn secondary">Clear Filters</a>
        </div>
      </form>
    </aside>

    <div class="shop-main">
      <div class="shop-toolbar">
        <button type="button" class="filter-toggle" id="filterToggle" aria-expanded="false" aria-controls="shopFilters">
          Filters<?php if ($activeFilterCount > 0): ?><span class="badge"><?= (int)$activeFilterCount ?></span><?php endif; ?>
        </button>
        <span class="muted"><?= $totalProducts ? sprintf('%d products found', $totalProducts) : 'No products match your filters yet.' ?></span>
      </div>

      <div class="shop-grid" id="shopGrid" data-render="server">
        <?php if ($products): ?>
          <?php foreach ($products as $product): ?>
            <div class="product-card">
              <a class="card-link" href="product.php?id=<?= (int)$product['id'] ?>">
                <div class="product-thumb">
                  <span class="badge-cat"><?= rb_escape($product['category_name'] ?? 'Product') ?></span>
                  <img src="<?= rb_escape($product['image_path'] ?? 'media/logo.png') ?>" alt="<?= rb_escape($product['image_alt'] ?: $product['name']) ?>">
                </div>
              </a>
              <div class="product-info">
                <h4><?= rb_escape($product['name']) ?></h4>
                <p class="price"><?= rb_escape(rb_format_price((float)$product['price'])) ?></p>
                <button class="btn btn-add" data-id="<?= (int)$product['id'] ?>" data-name="<?= rb_escape($product['name']) ?>" data-price="<?= rb_escape((string)$product['price']) ?>">Add to Cart</button>
                <button class="btn secondary btn-wish" data-id="<?= (int)$product['id'] ?>" aria-pressed="false">Add to Wishlist</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="grid-column:1/-1;text-align:center;padding:24px 0;">No products found. Try adjusting your filters.</p>
        <?php endif; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Catalogue pagination" style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $link = rb_pagination_link($i, $baseParams); ?>
            <a class="btn<?= $i === $page ? '' : ' secondary' ?>" href="<?= rb_escape($link) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>

      <section class="suggest-wrap">
        <h3>You may also like</h3>
        <div class="suggest-grid" id="shopSuggest">
          <?php foreach ($suggestProducts as $product): ?>
            <div class="suggest-card">
              <img src="<?= rb_escape($product['image_path'] ?? 'media/logo.png') ?>" alt="<?= rb_escape($product['name']) ?>">
              <div class="info">
                <h4><?= rb_escape($product['name']) ?></h4>
                <p><?= rb_escape($product['short_description'] ?? '') ?></p>
                <a class="btn" href="product.php?id=<?= (int)$product['id'] ?>">View Details</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </section>

  
  <?php include __DIR__ . '/includes/footer.php'; ?>


  <script>
    (function () {
      const priceRange = document.getElementById('priceRange');
      const priceMax = document.getElementById('priceMax');
      if (priceRange && priceMax) {
        priceRange.addEventListener('input', () => {
          priceMax.textContent = priceRange.value;
        });
      }
      const form = document.getElementById('filterForm');
      if (form) {
        form.addEventListener('change', (evt) => {
          const target = evt.target;
          if (target && target.matches('input[type="checkbox"], select')) {
            form.submit();
          }
        });
      }

      const container = document.querySelector('.shop-container');
      const toggle = document.getElementById('filterToggle');
      const filters = document.getElementById('shopFilters');

      if (container && toggle && filters) {
        toggle.addEventListener('click', () => {
          const open = container.classList.toggle('filters-open');
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          if (open) {
            const focusable = filters.querySelector('input, select, button');
            if (focusable) {
              focusable.focus();
            }
          }
        });

        window.addEventListener('resize', () => {
          if (window.innerWidth > 992 && container.classList.contains('filters-open')) {
            container.classList.remove('filters-open');
            toggle.setAttribute('aria-expanded', 'false');
          }
        });
      }
    })();
  </script>
  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>







