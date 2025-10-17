<?php
declare(strict_types=1);

$cartCount = isset($cartCount) ? (int)$cartCount : 0;
$headerBrand = $brandName ?? 'RudraBlessings';
$navItems = $menuItems ?? [];
$navCurrent = $currentPath ?? '';
$avatar = $avatarMeta ?? ['url' => 'media/logo.png', 'alt' => 'Profile picture', 'label' => 'My Profile'];
$isSignedIn = $isLoggedIn ?? false;
$headerSearchAction = $headerSearchAction ?? 'shop.php';
$headerSearchValue = $headerSearchValue ?? '';
$headerSearchHidden = $headerSearchHidden ?? [];
?>
<header class="rb-header glass">
  <div class="header-top container">
    <a href="index.php" class="rb-brand">
      <img src="media/logo.png" alt="<?= rb_escape($headerBrand) ?>"><span class="brand-text"><?= rb_escape($headerBrand) ?></span>
    </a>
    <form class="rb-search" action="<?= rb_escape($headerSearchAction) ?>" method="get">
      <input id="rbSearch" name="search" type="search" placeholder="Search products..." value="<?= rb_escape($headerSearchValue) ?>">
      <?php foreach ($headerSearchHidden as $hiddenField): ?>
        <?php
        $hiddenName = (string)($hiddenField['name'] ?? '');
        if ($hiddenName === '') {
            continue;
        }
        $hiddenValue = (string)($hiddenField['value'] ?? '');
        ?>
        <input type="hidden" name="<?= rb_escape($hiddenName) ?>" value="<?= rb_escape($hiddenValue) ?>">
      <?php endforeach; ?>
      <button class="rb-go" aria-label="Search">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M11 4a7 7 0 0 1 5.52 11.3l4.09 4.1-1.42 1.42-4.1-4.09A7 7 0 1 1 11 4zm0 2a5 5 0 1 0 0 10 5 5 0 0 0 0-10z" />
        </svg>
      </button>
    </form>
    <div class="header-icons">
      <?php if ($isSignedIn): ?>
        <a href="profile.php" title="<?= rb_escape($avatar['label']) ?>" id="profileBtn" class="icon-btn profile-avatar" aria-label="<?= rb_escape($avatar['label']) ?>">
          <img src="<?= rb_escape($avatar['url']) ?>" alt="<?= rb_escape($avatar['alt']) ?>">
        </a>
      <?php else: ?>
        <a href="signin.php" id="profileBtn" class="btn header-signin" title="Sign in" aria-label="Sign in">Sign in</a>
      <?php endif; ?>
      <a href="cart.php" class="icon-btn cart-icon" id="openCart" aria-label="View cart">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M7 4h-2V2h3l1 2h11l-2 9H8l-1.2-5H4V6h2l1.2 5H17l1.2-5H9.4l-1-2zm0 14a2 2 0 1 1 .01 4A2 2 0 0 1 7 18zm10 0a2 2 0 1 1 .01 4A2 2 0 0 1 17 18z" />
        </svg>
        <span id="cart-count"><?= $cartCount ?></span>
      </a>
      <button class="burger icon-btn" id="burger" aria-label="Toggle navigation" aria-expanded="false" aria-controls="rbNav">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z" />
        </svg>
      </button>
    </div>
  </div>

  <nav class="rb-nav container" id="rbNav">
    <?php foreach ($navItems as $item): ?>
      <?php
      $label = (string)($item['label'] ?? '');
      $url = (string)($item['url'] ?? '#');
      if ($isSignedIn && strcasecmp($label, 'Sign in') === 0) {
          $label = 'My Profile';
          $url = 'profile.php';
      }
      $itemPath = basename(parse_url($url, PHP_URL_PATH) ?? '');
      $active = $itemPath === $navCurrent;
      ?>
      <a href="<?= rb_escape($url) ?>"<?= $active ? ' class="active" aria-current="page"' : '' ?>><?= rb_escape($label) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<div id="rbOverlay" class="rb-overlay"></div>
