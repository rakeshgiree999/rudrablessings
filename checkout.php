<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/account.php';

if (!$isLoggedIn) {
    header('Location: signin.php?redirect=checkout.php');
    exit;
}

$currentPath = 'checkout.php';

$checkoutSuccess = $_SESSION['checkout_success'] ?? null;
if ($checkoutSuccess !== null) {
    unset($_SESSION['checkout_success']);
}

$cartInfo = rb_cart_resolve($pdo);
$cartSummary = rb_cart_summary($pdo, $cartInfo['id']);
$cartItems = $cartSummary['items'];
$cartCount = (int)($cartSummary['count'] ?? 0);
$subtotal = (float)($cartSummary['subtotal'] ?? 0.0);

$shippingOptions = [
    'standard' => ['label' => 'Standard', 'description' => '3-6 business days', 'amount' => 0.00],
    'express' => ['label' => 'Express', 'description' => '1-2 business days', 'amount' => 9.99],
];

$activePromos = rb_promos_active($pdo);
$promoCache = [];
$promoDataset = [];
foreach ($activePromos as $promoRow) {
    $normalizedCode = rb_promo_normalize_code((string)($promoRow['code'] ?? ''));
    if ($normalizedCode === '') {
        continue;
    }
    $promoCache[$normalizedCode] = $promoRow;
    $promoDataset[$normalizedCode] = [
        'code' => $normalizedCode,
        'type' => (string)($promoRow['type'] ?? 'percent'),
        'value' => (float)($promoRow['value'] ?? 0.0),
        'label' => (string)($promoRow['label'] ?? ''),
        'min_subtotal' => (float)($promoRow['min_subtotal'] ?? 0.0),
        'free_shipping' => ((string)($promoRow['type'] ?? '') === 'shipping'),
    ];
}

$applyPromo = static function (string $code, float $baseSubtotal, float $baseShipping) use ($promoCache): array {
    return rb_promo_apply_cached($code, $baseSubtotal, $baseShipping, $promoCache);
};

$computeCartSignature = static function (array $items): string {
    $fingerprint = [];
    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $quantity = max(0, (int)($item['quantity'] ?? 0));
        if ($productId > 0 && $quantity > 0) {
            $fingerprint[] = $productId . ':' . $quantity;
        }
    }
    sort($fingerprint);
    return sha1(implode('|', $fingerprint));
};

$buildCheckoutUrl = static function (string $suffix): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/checkout.php';
    $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($basePath === '.' || $basePath === '') {
        $basePath = '';
    }
    $baseUrl = $scheme . '://' . $host . ($basePath !== '' ? '/' . ltrim($basePath, '/') : '');
    return rtrim($baseUrl, '/') . '/' . ltrim($suffix, '/');
};

$checkoutPending = $_SESSION['checkout_pending'] ?? null;
$stripeError = null;
$checkoutCanceled = isset($_GET['canceled']);

$defaultShippingKey = array_key_first($shippingOptions) ?? 'standard';

$formData = [
    'email' => (string)($currentUser['email'] ?? ''),
    'phone' => '',
    'first_name' => (string)($currentUser['first_name'] ?? ''),
    'last_name' => (string)($currentUser['last_name'] ?? ''),
    'address' => '',
    'city' => '',
    'state' => '',
    'postcode' => '',
    'country' => 'Australia',
    'shipping' => $defaultShippingKey,
    'promo' => '',
];

$addresses = rb_account_addresses($pdo, (int)$currentUser['id']);
$defaultAddress = null;
foreach ($addresses as $address) {
    if ((int)($address['is_default'] ?? 0) === 1) {
        $defaultAddress = $address;
        break;
    }
}
if (!$defaultAddress && $addresses) {
    $defaultAddress = $addresses[0];
}
if ($defaultAddress) {
    $line1 = trim((string)($defaultAddress['line1'] ?? ''));
    $line2 = trim((string)($defaultAddress['line2'] ?? ''));
    $fullAddress = trim($line1 . ($line2 !== '' ? ', ' . $line2 : ''));
    if ($fullAddress !== '') {
        $formData['address'] = $fullAddress;
    }
    $formData['city'] = (string)($defaultAddress['city'] ?? $formData['city']);
    $formData['state'] = (string)($defaultAddress['state'] ?? $formData['state']);
    $formData['postcode'] = (string)($defaultAddress['postcode'] ?? $formData['postcode']);
    $formData['country'] = (string)($defaultAddress['country'] ?? $formData['country']);
    $formData['phone'] = (string)($defaultAddress['phone'] ?? $formData['phone']);
}

$stripeSessionId = isset($_GET['session_id']) ? trim((string)$_GET['session_id']) : '';
if ($stripeSessionId !== '') {
    if (is_array($checkoutPending) && ($checkoutPending['session_id'] ?? '') === $stripeSessionId) {
        $sessionResponse = rb_stripe_retrieve_session($stripeSessionId);
        if ($sessionResponse['ok']) {
            $sessionData = $sessionResponse['session'];
            $paymentStatus = (string)($sessionData['payment_status'] ?? '');
            if ($paymentStatus === 'paid') {
                if (empty($checkoutPending['completed'])) {
                    $cartSummary = rb_cart_summary($pdo, $cartInfo['id']);
                    $cartItems = $cartSummary['items'];
                    $cartCount = (int)($cartSummary['count'] ?? 0);
                    $subtotal = (float)($cartSummary['subtotal'] ?? 0.0);
                    $signature = $computeCartSignature($cartItems);

                    if ($cartCount === 0) {
                        $stripeError = 'Your cart was empty when we returned from payment. Please start again.';
                        unset($_SESSION['checkout_pending']);
                    } elseif ($signature !== ($checkoutPending['cart_signature'] ?? '')) {
                        $stripeError = 'Cart contents changed before confirmation. Please start checkout again.';
                        unset($_SESSION['checkout_pending']);
                    } else {
                        try {
                            $shippingKey = $checkoutPending['shipping_option'] ?? $defaultShippingKey;
                            $shippingLabel = $shippingOptions[$shippingKey]['label'] ?? 'Standard';

                            $order = rb_cart_place_order(
                                $pdo,
                                (int)$currentUser['id'],
                                $cartInfo['id'],
                                $cartItems,
                                (float)($checkoutPending['subtotal'] ?? $subtotal),
                                (float)($checkoutPending['shipping_cost'] ?? 0.0),
                                (float)($checkoutPending['discount'] ?? 0.0),
                                0.0,
                                isset($checkoutPending['address_id']) ? (int)$checkoutPending['address_id'] : null,
                                (string)($checkoutPending['promo'] ?? ''),
                                !empty($checkoutPending['promo_free_shipping']),
                                isset($checkoutPending['promo_id']) ? (int)$checkoutPending['promo_id'] : null
                            );

                            $_SESSION['checkout_success'] = [
                                'orderNumber' => $order['order_number'],
                                'total' => $order['grand_total'],
                                'email' => $checkoutPending['email'] ?? $formData['email'],
                                'shippingLabel' => $shippingLabel,
                                'paymentMethod' => 'Stripe (Test Mode)',
                            ];
                            unset($_SESSION['checkout_pending']);
                            header('Location: checkout.php?thanks=1');
                            exit;
                        } catch (\Throwable $e) {
                            $stripeError = 'We confirmed payment but could not finalise the order: ' . $e->getMessage();
                        }
                    }
                }
            } elseif ($paymentStatus === 'unpaid') {
                $stripeError = 'Payment was not completed. Please try again.';
                unset($_SESSION['checkout_pending']);
            } else {
                $stripeError = 'Stripe reported payment status: ' . ($paymentStatus !== '' ? $paymentStatus : 'unknown') . '.';
            }
        } else {
            $stripeError = $sessionResponse['error'] ?? 'Unable to verify Stripe session.';
        }
    } else {
        $stripeError = 'Checkout session has expired or is invalid. Please try again.';
    }
}

$formErrors = [];
$promoMessage = '';
$discountValue = 0.0;
$freeShippingApplied = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $checkoutSuccess === null) {
    if (!rb_csrf_validate_request()) {
        $formErrors['general'] = 'Your session expired. Please refresh and try again.';
    } else {
        $formData['email'] = trim((string)($_POST['email'] ?? $formData['email']));
        $formData['phone'] = trim((string)($_POST['phone'] ?? $formData['phone']));
        $formData['first_name'] = trim((string)($_POST['first_name'] ?? $formData['first_name']));
        $formData['last_name'] = trim((string)($_POST['last_name'] ?? $formData['last_name']));
        $formData['address'] = trim((string)($_POST['address'] ?? $formData['address']));
        $formData['city'] = trim((string)($_POST['city'] ?? $formData['city']));
        $formData['state'] = trim((string)($_POST['state'] ?? $formData['state']));
        $formData['postcode'] = trim((string)($_POST['postcode'] ?? $formData['postcode']));
        $formData['country'] = trim((string)($_POST['country'] ?? $formData['country']));
        $submittedShipping = (string)($_POST['shipping'] ?? $formData['shipping']);
        $formData['shipping'] = isset($shippingOptions[$submittedShipping]) ? $submittedShipping : $defaultShippingKey;
        unset($_SESSION['checkout_pending']);
        if (!rb_stripe_available()) {
        $formErrors['general'] = 'Stripe sandbox keys are missing. Add STRIPE_SECRET_KEY and STRIPE_PUBLISHABLE_KEY (or STRIPE_PUBLIC_KEY) to your .env file.';
        }
        $formData['promo'] = rb_promo_normalize_code((string)($_POST['promo'] ?? $formData['promo']));
        $selectedShipping = $formData['shipping'];

        if ($formData['email'] === '' || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $formErrors['email'] = 'Please enter a valid email address.';
        }
        if ($formData['first_name'] === '') {
            $formErrors['first_name'] = 'First name is required.';
        }
        if ($formData['last_name'] === '') {
            $formErrors['last_name'] = 'Last name is required.';
        }
        if ($formData['address'] === '') {
            $formErrors['address'] = 'Address is required.';
        }
        if ($formData['city'] === '') {
            $formErrors['city'] = 'City is required.';
        }
        if ($formData['state'] === '') {
            $formErrors['state'] = 'State or region is required.';
        }
        if ($formData['postcode'] === '') {
            $formErrors['postcode'] = 'Postal code is required.';
        }
        if ($formData['country'] === '') {
            $formErrors['country'] = 'Country is required.';
        }

        if ($cartCount === 0) {
            $formErrors['cart'] = 'Your cart is empty.';
        }

        $selectedShipping = $formData['shipping'];
        $baseShippingCost = $shippingOptions[$selectedShipping]['amount'] ?? 0.0;
        $promoResult = $applyPromo($formData['promo'], $subtotal, $baseShippingCost);
        if (!$promoResult['valid'] && $formData['promo'] !== '') {
            $formErrors['promo'] = $promoResult['message'] !== '' ? $promoResult['message'] : 'Promo code not valid.';
        }

        if (!$formErrors) {
            $cartSummary = rb_cart_summary($pdo, $cartInfo['id']);
            $cartItems = $cartSummary['items'];
            $cartCount = (int)($cartSummary['count'] ?? 0);
            $subtotal = (float)($cartSummary['subtotal'] ?? 0.0);
            if ($cartCount === 0) {
                $formErrors['cart'] = 'Your cart is empty.';
            } else {
                $baseShippingCost = $shippingOptions[$selectedShipping]['amount'] ?? 0.0;
                $promoResult = $applyPromo($formData['promo'], $subtotal, $baseShippingCost);
                if (!$promoResult['valid'] && $formData['promo'] !== '') {
                    $formErrors['promo'] = $promoResult['message'] !== '' ? $promoResult['message'] : 'Promo code not valid.';
                } else {
                    $discountValue = $promoResult['discount'];
                    $shippingCost = $promoResult['shipping'];
                    $freeShippingApplied = $promoResult['free_ship'];
                    $promoMessage = $promoResult['message'];
                    $formData['promo'] = $promoResult['code'];
                    $orderSubtotal = max(0, $subtotal - $discountValue);
                    $grandTotal = max(0, $orderSubtotal + $shippingCost);

                    $addressId = null;
                    $normalizedFormAddress = strtolower($formData['address']);
                    foreach ($addresses as $address) {
                        $combined = strtolower(trim(implode(' ', array_filter([$address['line1'] ?? '', $address['line2'] ?? '']))));
                        if (
                            $combined === $normalizedFormAddress
                            && strtolower(trim((string)$address['city'])) === strtolower($formData['city'])
                            && strtolower(trim((string)$address['state'])) === strtolower($formData['state'])
                            && strtolower(trim((string)$address['postcode'])) === strtolower($formData['postcode'])
                            && strtolower(trim((string)$address['country'])) === strtolower($formData['country'])
                        ) {
                            $addressId = (int)$address['id'];
                            break;
                        }
                    }

                    if ($addressId === null) {
                        $addressPayload = [
                            'label' => 'Checkout',
                            'contact_name' => trim($formData['first_name'] . ' ' . $formData['last_name']),
                            'phone' => $formData['phone'],
                            'line1' => $formData['address'],
                            'line2' => '',
                            'city' => $formData['city'],
                            'state' => $formData['state'],
                            'postcode' => $formData['postcode'],
                            'country' => $formData['country'],
                        ];
                        $addressResult = rb_account_add_address($pdo, (int)$currentUser['id'], $addressPayload);
                        if (($addressResult['ok'] ?? false) && isset($addressResult['id'])) {
                            $addressId = (int)$addressResult['id'];
                        }
                    }

                    $successUrl = $buildCheckoutUrl('checkout.php?session_id={CHECKOUT_SESSION_ID}');
                    $cancelUrl = $buildCheckoutUrl('checkout.php?canceled=1');

                    $lineItems = [];
                    foreach ($cartItems as $item) {
                        $lineItems[] = [
                            'name' => (string)($item['name'] ?? 'Product'),
                            'description' => 'Product ID ' . (int)($item['product_id'] ?? 0),
                            'amount' => (float)($item['unit_price'] ?? 0.0),
                            'quantity' => max(1, (int)($item['quantity'] ?? 1)),
                        ];
                    }
                    if ($shippingCost > 0) {
                        $lineItems[] = [
                            'name' => 'Shipping - ' . ($shippingOptions[$selectedShipping]['label'] ?? 'Standard'),
                            'description' => '',
                            'amount' => $shippingCost,
                            'quantity' => 1,
                        ];
                    }

                    $extraFields = [];
                    if ($discountValue > 0) {
                        $extraFields['discounts[0][discount_data][amount_off]'] = (int)round($discountValue * 100);
                        $extraFields['discounts[0][discount_data][currency]'] = rb_stripe_currency();
                        $extraFields['discounts[0][discount_data][name]'] = $formData['promo'] !== '' ? $formData['promo'] : 'Discount';
                    }

                $sessionResult = rb_stripe_create_checkout_session([
                    'customer_email' => $formData['email'],
                    'currency' => rb_stripe_currency(),
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'line_items' => $lineItems,
                    'metadata' => [
                        'cart_id' => (string)$cartInfo['id'],
                        'user_id' => (string)$currentUser['id'],
                        'promo_code' => $formData['promo'],
                        'promo_id' => $promoResult['promo_id'] !== null ? (string)$promoResult['promo_id'] : '',
                        'promo_free_shipping' => $promoResult['free_ship'] ? '1' : '0',
                        'promo_discount' => number_format($discountValue, 2, '.', ''),
                        'shipping_option' => $selectedShipping,
                        'item_count' => (string)$cartCount,
                        'subtotal' => number_format($orderSubtotal, 2, '.', ''),
                    ],
                    'extra_fields' => $extraFields + [
                        'client_reference_id' => 'cart-' . $cartInfo['id'],
                    ],
                ]);

                if (!$sessionResult['ok']) {
                    $formErrors['general'] = ($sessionResult['error'] ?? 'Unable to begin Stripe checkout.') . ' Please try again or contact support if this continues.';
                } else {
                        $_SESSION['checkout_pending'] = [
                            'session_id' => $sessionResult['id'],
                            'cart_signature' => $computeCartSignature($cartItems),
                            'subtotal' => $subtotal,
                            'shipping_cost' => $shippingCost,
                            'discount' => $discountValue,
                            'promo' => $formData['promo'],
                            'promo_id' => $promoResult['promo_id'],
                            'promo_free_shipping' => $promoResult['free_ship'] ? 1 : 0,
                            'address_id' => $addressId,
                            'shipping_option' => $selectedShipping,
                            'email' => $formData['email'],
                        ];
                        header('Location: ' . $sessionResult['url']);
                        exit;
                    }
                }
            }
        }
    }
}

$selectedShipping = $formData['shipping'];
$baseShippingCost = $shippingOptions[$selectedShipping]['amount'] ?? 0.0;
$shippingCost = $baseShippingCost;
$promoResultDisplay = $applyPromo($formData['promo'], $subtotal, $baseShippingCost);
if ($promoResultDisplay['valid']) {
    $discountValue = $promoResultDisplay['discount'];
    $shippingCost = $promoResultDisplay['shipping'];
    $freeShippingApplied = $promoResultDisplay['free_ship'];
    if ($promoResultDisplay['message'] !== '') {
        $promoMessage = $promoResultDisplay['message'];
    }
    $formData['promo'] = $promoResultDisplay['code'];
} elseif (!empty($formData['promo']) && !isset($formErrors['promo'])) {
    $formErrors['promo'] = $promoResultDisplay['message'] !== '' ? $promoResultDisplay['message'] : 'Promo code not valid.';
}
$grandTotal = max(0, ($subtotal - $discountValue) + $shippingCost);
$formDisabled = ($cartCount === 0);

$cartProductIds = array_map(static fn($item) => (int)($item['product_id'] ?? 0), $cartItems);
$inCartLookup = array_flip($cartProductIds);
$suggestPool = rb_products($pdo, ['limit' => 12, 'sort' => 'newest']);
$suggestProducts = [];
foreach ($suggestPool as $product) {
    $pid = (int)($product['id'] ?? 0);
    if ($pid > 0 && !isset($inCartLookup[$pid])) {
        $suggestProducts[] = $product;
    }
    if (count($suggestProducts) >= 4) {
        break;
    }
}

$shippingMapForJs = [];
foreach ($shippingOptions as $key => $option) {
    $shippingMapForJs[$key] = $option['amount'];
}
$formDatasetShippingJson = json_encode($shippingMapForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($formDatasetShippingJson === false) {
    $formDatasetShippingJson = '{}';
}
$itemsForJs = array_map(static function ($item) {
    return [
        'product_id' => (int)($item['product_id'] ?? 0),
        'name' => (string)($item['name'] ?? ''),
        'line_total' => (float)($item['line_total'] ?? 0.0),
    ];
}, $cartItems);
$formDatasetItemsJson = json_encode($itemsForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($formDatasetItemsJson === false) {
    $formDatasetItemsJson = '[]';
}

$formDatasetPromosJson = json_encode($promoDataset, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($formDatasetPromosJson === false) {
    $formDatasetPromosJson = '{}';
}

$supportEmail = $settings['support.email'] ?? 'support@rudrablessings.com';
$noteText = 'Stripe Checkout runs in test mode. Payments are simulated and no real charge is taken.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout | <?= rb_escape($brandName) ?></title>
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="co-hero">
    <div class="container">
      <h1>Secure Checkout</h1>
      <p class="muted">Payments are handled by Stripe test mode. Use any Stripe test card to complete checkout securely.</p>
    </div>
  </section>

  <div class="container" style="padding: 0 16px 48px;">
    <?php if ($checkoutSuccess): ?>
      <div class="notice success">
        <strong>Thank you!</strong>
        <p>Your order <strong><?= rb_escape($checkoutSuccess['orderNumber']) ?></strong> is confirmed. A receipt has been sent to <?= rb_escape($checkoutSuccess['email']) ?>.</p>
        <p><a href="profile.php#orders">View your orders</a> or <a href="shop.php">continue shopping</a>.</p>
      </div>
    <?php endif; ?>

    <?php if (!$checkoutSuccess): ?>
      <?php if ($checkoutCanceled): ?>
        <div class="notice warning">
          <strong>Payment cancelled.</strong> You can adjust your details below and try again.
        </div>
      <?php endif; ?>
      <?php if ($stripeError): ?>
        <div class="notice error">
          <?= rb_escape($stripeError) ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($formErrors)): ?>
        <div class="notice error">
          <strong>Please check the details below.</strong>
          <ul style="margin:8px 0 0; padding-left:18px;">
            <?php foreach ($formErrors as $message): if (!is_string($message)) continue; ?>
              <li><?= rb_escape($message) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if ($formDisabled): ?>
        <div class="notice info">
          Your cart is empty. <a href="shop.php">Browse the shop</a> to add items.
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!$checkoutSuccess): ?>
      <section class="checkout-wrap">
        <form id="checkoutForm" class="checkout-form" method="post"
          data-mode="server"
          data-shipping='<?= rb_escape($formDatasetShippingJson) ?>'
          data-shipping-default="<?= rb_escape(number_format($shippingOptions[$selectedShipping]['amount'] ?? 0.0, 2, '.', '')) ?>"
          data-subtotal="<?= rb_escape(number_format($subtotal, 2, '.', '')) ?>"
          data-discount="<?= rb_escape(number_format($discountValue, 2, '.', '')) ?>"
          data-free-ship="<?= $freeShippingApplied ? '1' : '0' ?>"
          data-items='<?= rb_escape($formDatasetItemsJson) ?>'
          data-promos='<?= rb_escape($formDatasetPromosJson) ?>'>
          <?= rb_csrf_input() ?>
          <h1 class="co-title">Checkout</h1>
          <ol class="co-steps">
            <li class="active">Shipping</li>
            <li>Payment</li>
            <li>Review</li>
          </ol>

          <h2>Contact</h2>
          <div class="grid2">
            <label>Email
              <input name="email" id="coEmail" type="email" placeholder="you@example.com" value="<?= rb_escape($formData['email']) ?>" required class="<?= isset($formErrors['email']) ? 'input-invalid' : '' ?>">
              <?php if (isset($formErrors['email'])): ?><span class="field-error"><?= rb_escape($formErrors['email']) ?></span><?php endif; ?>
            </label>
            <label>Phone
              <input name="phone" id="coPhone" type="tel" placeholder="Optional" value="<?= rb_escape($formData['phone']) ?>">
            </label>
          </div>

          <h2>Shipping</h2>
          <div class="grid2">
            <label>First name
              <input name="first_name" id="coFirst" type="text" value="<?= rb_escape($formData['first_name']) ?>" required class="<?= isset($formErrors['first_name']) ? 'input-invalid' : '' ?>">
              <?php if (isset($formErrors['first_name'])): ?><span class="field-error"><?= rb_escape($formErrors['first_name']) ?></span><?php endif; ?>
            </label>
            <label>Last name
              <input name="last_name" id="coLast" type="text" value="<?= rb_escape($formData['last_name']) ?>" required class="<?= isset($formErrors['last_name']) ? 'input-invalid' : '' ?>">
              <?php if (isset($formErrors['last_name'])): ?><span class="field-error"><?= rb_escape($formErrors['last_name']) ?></span><?php endif; ?>
            </label>
            <label>Address
              <input name="address" id="coAddr" type="text" value="<?= rb_escape($formData['address']) ?>" required class="<?= isset($formErrors['address']) ? 'input-invalid' : '' ?>">
              <?php if (isset($formErrors['address'])): ?><span class="field-error"><?= rb_escape($formErrors['address']) ?></span><?php endif; ?>
            </label>
            <label>City
              <input name="city" id="coCity" type="text" value="<?= rb_escape($formData['city']) ?>" required class="<?= isset($formErrors['city']) ? 'input-invalid' : '' ?>">
              <?php if (isset($formErrors['city'])): ?><span class="field-error"><?= rb_escape($formErrors['city']) ?></span><?php endif; ?>
            </label>
            <label>State / Region
              <input name="state" id="coState" type="text" value="<?= rb_escape($formData['state']) ?>" required class="<?= isset($formErrors['state']) ? 'input-invalid' : '' ?>">
              <?php if (isset($formErrors['state'])): ?><span class="field-error"><?= rb_escape($formErrors['state']) ?></span><?php endif; ?>
            </label>
            <label>Postal Code
              <input name="postcode" id="coZip" type="text" value="<?= rb_escape($formData['postcode']) ?>" required class="<?= isset($formErrors['postcode']) ? 'input-invalid' : '' ?>">
              <?php if (isset($formErrors['postcode'])): ?><span class="field-error"><?= rb_escape($formErrors['postcode']) ?></span><?php endif; ?>
            </label>
            <label>Country
              <input name="country" id="coCountry" type="text" value="<?= rb_escape($formData['country']) ?>" required class="<?= isset($formErrors['country']) ? 'input-invalid' : '' ?>">
              <?php if (isset($formErrors['country'])): ?><span class="field-error"><?= rb_escape($formErrors['country']) ?></span><?php endif; ?>
            </label>
          </div>

          <fieldset class="ship-methods">
            <legend>Delivery Method</legend>
            <?php foreach ($shippingOptions as $key => $option): ?>
              <label class="ship-opt">
                <input type="radio" name="shipping" value="<?= rb_escape($key) ?>"<?= $formData['shipping'] === $key ? ' checked' : '' ?>>
                <span class="name"><?= rb_escape($option['label']) ?></span>
                <span class="muted"><?= rb_escape($option['description']) ?></span>
                <strong class="price"><?= $option['amount'] <= 0 ? 'Free' : rb_escape(rb_format_price($option['amount'])) ?></strong>
              </label>
            <?php endforeach; ?>
          </fieldset>

          <h2>Payment</h2>
          <div class="pay-methods">
            <p class="muted"><strong>Only Stripe Checkout is accepted.</strong> You will complete payment on Stripe (test mode) to ensure card details stay secure.</p>
            <p class="muted">Use any Stripe test card, for example <code>4242&nbsp;4242&nbsp;4242&nbsp;4242</code> with a future expiry and any CVC.</p>
          </div>

          <div class="promo-row">
            <input id="promoInput" name="promo" type="text" placeholder="Promo code (e.g. SAVE10)" value="<?= rb_escape($formData['promo']) ?>">
            <button type="button" id="promoApply" class="btn secondary">Apply</button>
            <span id="promoMsg" class="muted"><?= rb_escape($promoMessage) ?></span>
          </div>

          <button type="submit" class="cta-btn"<?= $formDisabled ? ' disabled' : '' ?>>Place Order</button>
        </form>

        <aside class="checkout-summary">
          <h3>Order Summary</h3>
          <ul id="checkoutList" class="sum-items">
            <?php if ($cartItems): ?>
              <?php foreach ($cartItems as $item): ?>
                <li>
                  <span class="name">
                    <?= rb_escape($item['name'] ?? 'Product') ?>
                    <small class="muted">×<?= (int)($item['quantity'] ?? 1) ?></small>
                  </span>
                  <span class="price"><?= rb_escape(rb_format_price((float)($item['line_total'] ?? 0.0))) ?></span>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li class="muted">Your cart is empty.</li>
            <?php endif; ?>
          </ul>
          <div class="sum-row"><span>Subtotal</span><strong>$<span id="sumSubtotal"><?= number_format($subtotal, 2) ?></span></strong></div>
          <div class="sum-row">
            <span>Shipping</span>
            <strong>
              <?php if ($shippingCost <= 0): ?>
                <span id="sumShipping" data-free-label="Free">Free</span>
              <?php else: ?>
                $<span id="sumShipping" data-free-label="Free"><?= number_format($shippingCost, 2) ?></span>
              <?php endif; ?>
            </strong>
          </div>
          <div class="sum-row"><span>Discount</span><strong>- $<span id="sumDiscount"><?= number_format($discountValue, 2) ?></span></strong></div>
          <hr>
          <div class="sum-total"><span>Total</span><strong>$<span id="checkoutTotal"><?= number_format($grandTotal, 2) ?></span></strong></div>
          <div class="pay-logos trust" style="margin-top:8px">
            <img class="logo" data-brand="visa" alt="Visa">
            <img class="logo" data-brand="mastercard" alt="Mastercard">
            <img class="logo" data-brand="amex" alt="American Express">
          </div>
          <p class="muted" id="checkoutNote" data-text="<?= rb_escape($noteText) ?>"><?= rb_escape($noteText) ?></p>
          <p class="muted">Questions? Email <a href="mailto:<?= rb_escape($supportEmail) ?>"><?= rb_escape($supportEmail) ?></a>.</p>
        </aside>
      </section>
    <?php endif; ?>

    <section class="container co-suggest" style="padding: 32px 0 0;">
      <h3>You May Also Want</h3>
      <?php if ($suggestProducts): ?>
        <div class="shop-grid">
          <?php foreach ($suggestProducts as $product): ?>
            <div class="product-card">
              <a class="card-link" href="product.php?id=<?= (int)$product['id'] ?>">
                <div class="product-thumb">
                  <span class="badge-cat"><?= rb_escape($product['category_name'] ?? $product['tag'] ?? 'Featured') ?></span>
                  <img src="<?= rb_escape($product['image_path'] ?? 'media/logo.png') ?>" alt="<?= rb_escape($product['name'] ?? 'Product') ?>">
                </div>
                <div class="product-info">
                  <h4><?= rb_escape($product['name'] ?? 'Product') ?></h4>
                  <p class="price"><?= rb_escape(rb_format_price((float)($product['price'] ?? 0.0))) ?></p>
                </div>
              </a>
              <button class="btn btn-add" data-id="<?= (int)$product['id'] ?>" data-name="<?= rb_escape($product['name'] ?? 'Product') ?>" data-price="<?= rb_escape(number_format((float)($product['price'] ?? 0.0), 2, '.', '')) ?>">Add to Cart</button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">Looking for inspiration? Explore our <a href="shop.php">latest arrivals</a>.</p>
      <?php endif; ?>
    </section>
  </div>

  
  <?php include __DIR__ . '/includes/footer.php'; ?>


  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>


