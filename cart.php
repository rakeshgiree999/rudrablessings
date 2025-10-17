<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$menuItems = rb_menu($pdo);
$footerBlock = rb_block($pdo, 'footer', 'legal');

$cartInfo = rb_cart_resolve($pdo);
$cartSummary = rb_cart_summary($pdo, $cartInfo['id']);
$cartItems = $cartSummary['items'];
$cartProductIds = array_map('intval', array_column($cartItems, 'product_id'));
$uniqueCartIds = array_flip($cartProductIds);

$suggestPool = rb_products($pdo, ['limit' => 12, 'sort' => 'newest']);
$suggestProducts = array_values(array_filter($suggestPool, static function ($product) use ($uniqueCartIds) {
    $pid = (int)($product['id'] ?? 0);
    return $pid > 0 && !isset($uniqueCartIds[$pid]);
}));
$suggestProducts = array_slice($suggestProducts, 0, 4);

$currentPath = 'cart.php';
$cartCount = count($cartItems);

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Cart | <?= rb_escape($brandName) ?></title>
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="container" style="padding:30px 0">
    <h1>My Cart</h1>
    <table class="cart-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Price</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="cartBody">
        <?php if (!$cartItems): ?>
          <tr>
            <td colspan="3">Your cart is empty.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($cartItems as $item): ?>
            <tr>
              <td>
                <a href="product.php?id=<?= (int)$item['product_id'] ?>"><?= rb_escape($item['name']) ?></a>
              </td>
              <td>$<?= number_format((float)$item['unit_price'], 2) ?> &times; <?= (int)$item['quantity'] ?></td>
              <td><button class="btn secondary mini-remove" data-id="<?= (int)$item['product_id'] ?>">Remove</button></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <div class="cart-actions">
      <strong>Total: $<span id="cartPageTotal"><?= number_format((float)$cartSummary['subtotal'], 2) ?></span></strong>
      <button id="cartClear" class="btn secondary">Clear Cart</button>
      <a href="shop.php" class="btn">Continue Shopping</a>
      <a href="checkout.php" class="btn">Checkout</a>
    </div>
  </section>

  <section class="container" style="padding:0 16px 40px;">
    <h3>You may also like</h3>
    <div class="shop-grid" id="cartSuggest" data-render="server">
      <?php foreach ($suggestProducts as $product): ?>
        <div class="product-card">
          <a class="card-link" href="product.php?id=<?= (int)$product['id'] ?>">
            <div class="product-thumb">
              <span class="badge-cat"><?= rb_escape($product['category_name'] ?? '') ?></span>
              <img src="<?= rb_escape($product['image_path'] ?? 'media/logo.png') ?>" alt="<?= rb_escape($product['name']) ?>">
            </div>
            <div class="product-info">
              <h4><?= rb_escape($product['name']) ?></h4>
              <p class="price"><?= rb_escape(rb_format_price((float)$product['price'])) ?></p>
            </div>
          </a>
          <button class="btn btn-add" data-id="<?= (int)$product['id'] ?>" data-name="<?= rb_escape($product['name']) ?>" data-price="<?= rb_escape((string)$product['price']) ?>">Add to Cart</button>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  
  <?php include __DIR__ . '/includes/footer.php'; ?>


  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>









