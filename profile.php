<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/account.php';
require __DIR__ . '/includes/cart.php';

$currentUser = rb_auth_current_user($pdo);
if (!$currentUser) {
    header('Location: signin.php?redirect=profile.php');
    exit;
}

$userId = (int)$currentUser['id'];
$settings = rb_settings($pdo);
$brandName = $settings['brand.name'] ?? 'RudraBlessings';
$menuItems = rb_menu($pdo);
$footerBlock = rb_block($pdo, 'footer', 'legal');

$cartInfo = rb_cart_resolve($pdo);
$cartId = (int)$cartInfo['id'];

$accountMessage = null;
$accountErrors = [];
$addressMessage = null;
$addressErrors = [];
$passwordMessage = null;
$passwordErrors = [];
$preferencesMessage = null;
$preferencesError = null;
$orderMessage = null;
$orderError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!rb_csrf_validate_request()) {
        $csrfMessage = 'Your session expired. Please refresh and try again.';
        switch ($action) {
            case 'update_account':
                $accountErrors['general'] = $csrfMessage;
                break;
            case 'add_address':
            case 'set_default_address':
            case 'delete_address':
                $addressErrors['general'] = $csrfMessage;
                break;
            case 'update_password':
                $passwordErrors['general'] = $csrfMessage;
                break;
            case 'update_preferences':
                $preferencesError = $csrfMessage;
                break;
            case 'reorder':
                $orderError = $csrfMessage;
                break;
            case 'resend_verification':
                $accountErrors['general'] = $csrfMessage;
                break;
            default:
                $accountErrors['general'] = $csrfMessage;
                break;
        }
    } elseif ($action === 'update_account') {
        $accountData = $_POST;
        $existingProfilePicture = (string)($currentUser['avatar_path'] ?? '');
        $uploadResult = ['ok' => true];

        if (!empty($_FILES['profile_picture'])) {
            $uploadResult = rb_account_upload_profile_picture($pdo, $userId, $_FILES['profile_picture']);
            if (!$uploadResult['ok']) {
                $accountErrors['profile_picture'] = $uploadResult['error'] ?? 'Unable to upload profile picture.';
            } elseif (!empty($uploadResult['path'])) {
                $accountData['avatar_path'] = $uploadResult['path'];
            }
        }

        if (empty($accountErrors)) {
            $result = rb_account_update_user($pdo, $userId, $accountData);
            if ($result['ok']) {
                $accountMessage = 'Account details updated.';
                if (!empty($accountData['avatar_path'])) {
                    $accountMessage .= ' Profile picture refreshed.';
                }
                if (!empty($result['verification_sent'])) {
                    $accountMessage = 'Account details updated. Please verify your email using the link we just sent.';
                }
                if (!empty($result['verification_error'])) {
                    $accountErrors['general'] = $result['verification_error'];
                }

                if (!empty($accountData['avatar_path']) && $existingProfilePicture !== '' && $existingProfilePicture !== $accountData['avatar_path'] && strpos($existingProfilePicture, 'media/profile-picture.svg') === false) {
                    $oldPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $existingProfilePicture);
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $currentUser = rb_account_user($pdo, $userId) ?? $currentUser;
                $avatarMeta = rb_user_avatar($currentUser);
            } else {
                $accountErrors = $result['errors'];
                if (!empty($accountData['avatar_path'])) {
                    $newPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $accountData['avatar_path']);
                    if (is_file($newPath)) {
                        @unlink($newPath);
                    }
                }
            }
        } else {
            if (!empty($uploadResult['path'])) {
                $newPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadResult['path']);
                if (is_file($newPath)) {
                    @unlink($newPath);
                }
            }
        }
    } elseif ($action === 'add_address') {
        $result = rb_account_add_address($pdo, $userId, $_POST);
        if ($result['ok']) {
            $addressMessage = 'Address saved.';
        } else {
            $addressErrors = $result['errors'];
        }
    } elseif ($action === 'set_default_address') {
        $addressId = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
        $result = rb_account_set_default_address($pdo, $userId, $addressId);
        if ($result['ok']) {
            $addressMessage = 'Default address updated.';
        } else {
            $addressErrors['general'] = $result['error'] ?? 'Unable to update default address.';
        }
    } elseif ($action === 'delete_address') {
        $addressId = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
        $result = rb_account_delete_address($pdo, $userId, $addressId);
        if ($result['ok']) {
            $addressMessage = 'Address removed.';
        } else {
            $addressErrors['general'] = $result['error'] ?? 'Unable to remove address.';
        }
    } elseif ($action === 'update_password') {
        $result = rb_account_change_password($pdo, $userId, $_POST);
        if ($result['ok']) {
            $passwordMessage = 'Password updated successfully.';
        } else {
            $passwordErrors = $result['errors'];
        }
    } elseif ($action === 'update_preferences') {
        $result = rb_account_update_preferences($pdo, $userId, [
            'marketing_emails' => isset($_POST['marketing_emails']) ? 1 : 0,
            'order_updates' => isset($_POST['order_updates']) ? 1 : 0,
            'sms_alerts' => isset($_POST['sms_alerts']) ? 1 : 0,
        ]);
        if ($result['ok']) {
            $preferencesMessage = 'Preferences updated.';
        }
    } elseif ($action === 'resend_verification') {
        $resend = rb_auth_resend_verification($pdo, $userId);
        if (!empty($resend['already_verified'])) {
            $accountMessage = 'Your email address is already verified.';
        } elseif (($resend['ok'] ?? false)) {
            $emailForMessage = $currentUser['email'] ?? '';
            $accountMessage = $emailForMessage !== ''
                ? 'We emailed a new verification link to ' . $emailForMessage . '.'
                : 'We emailed a new verification link to your account email.';
        } else {
            $accountErrors['general'] = $resend['error'] ?? 'Unable to send a verification email right now.';
        }
    } elseif ($action === 'reorder') {
        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $stmt = $pdo->prepare('SELECT order_number FROM orders WHERE id = :id AND user_id = :user LIMIT 1');
        $stmt->execute(['id' => $orderId, 'user' => $userId]);
        $orderRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$orderRow) {
            $orderError = 'Order not found or does not belong to your account.';
        } else {
            $orderNumber = (string)$orderRow['order_number'];
            $items = rb_account_order_items($pdo, $orderId);
            if (!$items) {
                $orderError = 'No items available to reorder.';
            } else {
                foreach ($items as $item) {
                    $quantity = max(1, (int)($item['quantity'] ?? 1));
                    $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : ((float)$item['total_price'] / max(1, $quantity));
                    $result = rb_cart_add_item($pdo, $cartId, (int)$item['product_id'], $quantity, $unitPrice);
                    if (isset($result['ok']) && !$result['ok']) {
                        $orderError = $result['error'] ?? 'Unable to add one of the items to your cart.';
                        break;
                    }
                }
                if ($orderError === null) {
                    $orderMessage = 'Items were added to your cart from order #' . rb_escape($orderNumber) . '. <a href="cart.php">Review cart</a>';
                }
            }
        }
    }
}

$account = rb_account_user($pdo, $userId) ?? $currentUser;
$addresses = rb_account_addresses($pdo, $userId);
$orders = rb_account_orders($pdo, $userId);
$wishlistItems = rb_account_wishlist_items($pdo, $userId);
$preferences = rb_account_user_preferences($pdo, $userId);

$isEmailVerified = !empty($account['email_verified_at']);
$accountEmail = (string)($account['email'] ?? '');

$orderDetails = [];
foreach ($orders as $order) {
    $orderDetails[$order['id']] = rb_account_order_items($pdo, (int)$order['id']);
}

$memberSince = (new DateTime($account['created_at']))->format('F j, Y');
$orderCount = count($orders);
$wishlistCount = count($wishlistItems);
$profileAvatar = rb_user_avatar($account);
$cartSummary = rb_cart_summary($pdo, $cartId);
$cartCount = $cartSummary['count'];

$currentPath = 'profile.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile | <?= rb_escape($brandName) ?></title>
  <meta name="description" content="Manage your RudraBlessings account: details, orders, addresses, wishlist and security.">
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/pages.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
    <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="profile-hero">
    <div class="wrap container">
      <div class="user-head">
        <img id="pfAvatar" class="avatar" src="<?= rb_escape($profileAvatar['url']) ?>" alt="<?= rb_escape($profileAvatar['alt']) ?>">
        <div class="meta">
          <h1 id="pfName"><?= rb_escape($account['first_name'] . ' ' . $account['last_name']) ?></h1>
          <p id="pfEmail" class="muted"><?= rb_escape($account['email']) ?></p>
          <div class="badges">
            <span class="badge">Member since <span><?= rb_escape($memberSince) ?></span></span>
            <span class="badge tone">Orders <span><?= $orderCount ?></span></span>
            <span class="badge tone2">Wishlist <span><?= $wishlistCount ?></span></span>
            <?php if ($isEmailVerified): ?>
              <span class="badge tone2" style="background:#dcfce7;color:#166534;">Email verified</span>
            <?php else: ?>
              <span class="badge tone" style="background:#fee2e2;color:#7f1d1d;">Verification pending</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="actions">
          <?php if (!$isEmailVerified): ?>
            <form method="post" style="display:inline-flex;gap:8px;align-items:center;" class="resend-form">
              <?= rb_csrf_input() ?>
              <input type="hidden" name="action" value="resend_verification">
              <button type="submit" class="btn-secondary">Resend verification</button>
            </form>
          <?php endif; ?>
          <a href="logout.php" class="btn-outline">Sign out</a>
          <a href="cart.php" class="btn-primary">Go to Cart</a>
        </div>
      </div>
    </div>
  </section>

  <section class="container" style="padding:24px 16px 60px;">
    <div class="profile-grid">
      <aside class="pf-side">
        <nav class="pf-tabs">
          <button data-tab="account" class="active">Account</button>
          <button data-tab="orders">Orders</button>
          <button data-tab="addresses">Addresses</button>
          <button data-tab="wishlist">Wishlist</button>
          <button data-tab="security">Security</button>
          <button data-tab="preferences">Preferences</button>
        </nav>
        <div class="pf-tip">
          <p>Tip: keep your address up to date to speed up delivery.</p>
        </div>
      </aside>

      <main class="pf-main" data-tabs>
        <section id="tab-account" class="pf-card">
          <h3>Account Details</h3>
          <?php if ($accountMessage): ?>
            <div class="notice success"><?= rb_escape($accountMessage) ?></div>
          <?php endif; ?>
          <?php if (isset($accountErrors['general'])): ?>
            <div class="notice error"><?= rb_escape($accountErrors['general']) ?></div>
          <?php endif; ?>
          <form method="post" class="grid2" novalidate enctype="multipart/form-data">
            <?= rb_csrf_input() ?>
            <input type="hidden" name="action" value="update_account">
            <div class="field">
              <label for="accFirst">First name</label>
              <input id="accFirst" name="first_name" type="text" value="<?= rb_escape($account['first_name']) ?>" required>
              <?php if (isset($accountErrors['first_name'])): ?><small class="muted error"><?= rb_escape($accountErrors['first_name']) ?></small><?php endif; ?>
            </div>
            <div class="field">
              <label for="accLast">Last name</label>
              <input id="accLast" name="last_name" type="text" value="<?= rb_escape($account['last_name']) ?>" required>
              <?php if (isset($accountErrors['last_name'])): ?><small class="muted error"><?= rb_escape($accountErrors['last_name']) ?></small><?php endif; ?>
            </div>
            <div class="field">
              <label for="accEmail">Email</label>
              <input id="accEmail" name="email" type="email" value="<?= rb_escape($account['email']) ?>" required>
              <small class="muted">Used for orders and notifications.</small>
              <?php if (isset($accountErrors['email'])): ?><small class="muted error"><?= rb_escape($accountErrors['email']) ?></small><?php endif; ?>
            </div>
            <div class="field" style="grid-column:1 / -1;">
              <label for="accAvatar">Profile picture</label>
              <input id="accAvatar" name="profile_picture" type="file" accept="image/png,image/jpeg,image/webp">
              <small class="muted">JPG, PNG, or WebP up to 2MB.</small>
              <?php if (isset($accountErrors['profile_picture'])): ?><small class="muted error"><?= rb_escape($accountErrors['profile_picture']) ?></small><?php endif; ?>
            </div>
            <div class="row">
              <button class="btn-primary" type="submit">Save Changes</button>
            </div>
          </form>
        </section>

        <section id="tab-orders" class="pf-card" hidden>
          <h3>Orders</h3>
          <?php if ($orderMessage): ?>
            <div class="notice success"><?= rb_trusted_html($orderMessage) ?></div>
          <?php endif; ?>
          <?php if ($orderError): ?>
            <div class="notice error"><?= rb_escape($orderError) ?></div>
          <?php endif; ?>
          <?php if (!$orders): ?>
            <p class="muted">No orders yet. <a href="shop.php">Start shopping</a>.</p>
          <?php endif; ?>
          <?php foreach ($orders as $order): ?>
            <?php
              $statusClass = strtolower((string)$order['status']);
              $items = $orderDetails[$order['id']] ?? [];
            ?>
            <article class="order-card">
              <div class="order-header">
                <div>
                  <strong>Order <?= rb_escape($order['order_number']) ?></strong>
                  <div class="muted" style="font-size:13px;">Placed <?= rb_escape(date('M j, Y g:i a', strtotime($order['placed_at']))) ?></div>
                </div>
                <span class="badge status <?= rb_escape($statusClass) ?>"><?= rb_escape(ucfirst($order['status'])) ?></span>
              </div>
              <div class="order-meta">
                <span><?= (int)$order['item_count'] ?> items</span>
                <span>Shipping: <?= rb_escape($order['shipping'] > 0 ? rb_format_price((float)$order['shipping']) : 'Free') ?></span>
                <span>Total: <?= rb_escape(rb_format_price((float)$order['grand_total'])) ?></span>
              </div>
              <?php if ($items): ?>
                <details class="order-details">
                  <summary>View order items</summary>
                  <ul class="order-items">
                    <?php foreach ($items as $item): ?>
                      <li>
                        <div>
                          <span class="item-name"><?= rb_escape($item['product_name']) ?></span>
                          <span class="item-qty">&times; <?= (int)$item['quantity'] ?></span>
                        </div>
                        <div class="item-price"><?= rb_escape(rb_format_price((float)$item['total_price'])) ?></div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php endif; ?>
              <div class="order-actions">
                <form method="post">
                  <?= rb_csrf_input() ?>
                  <input type="hidden" name="action" value="reorder">
                  <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                  <button type="submit" class="btn">Reorder items</button>
                </form>
                <a class="btn secondary" href="mailto:<?= rb_escape($settings['support.email'] ?? 'support@rudrablessings.com') ?>?subject=Question%20about%20order%20<?= urlencode((string)$order['order_number']) ?>">Need help?</a>
              </div>
            </article>
          <?php endforeach; ?>
        </section>

        <section id="tab-addresses" class="pf-card" hidden>
          <h3>Addresses</h3>
          <?php if ($addressMessage): ?>
            <div class="notice success"><?= rb_escape($addressMessage) ?></div>
          <?php endif; ?>
          <?php if (isset($addressErrors['general'])): ?>
            <div class="notice error"><?= rb_escape($addressErrors['general']) ?></div>
          <?php endif; ?>
          <?php if ($addresses): ?>
            <div class="addr-list">
              <?php foreach ($addresses as $address): ?>
                <div class="addr-card<?= $address['is_default'] ? ' default' : '' ?>">
                  <div>
                    <strong><?= rb_escape($address['label']) ?></strong>
                    <?php if ($address['is_default']): ?><span class="badge tone">Default</span><?php endif; ?>
                    <p><?= rb_escape($address['contact_name']) ?></p>
                    <p><?= rb_escape($address['line1']) ?><?= $address['line2'] ? ', ' . rb_escape($address['line2']) : '' ?></p>
                    <p><?= rb_escape($address['city']) ?>, <?= rb_escape($address['state']) ?> <?= rb_escape($address['postcode']) ?></p>
                    <p><?= rb_escape($address['country']) ?></p>
                    <?php if ($address['phone']): ?><p class="muted">Phone: <?= rb_escape($address['phone']) ?></p><?php endif; ?>
                  </div>
                  <div class="addr-actions">
                    <?php if (!$address['is_default']): ?>
                      <form method="post">
                        <?= rb_csrf_input() ?>
                        <input type="hidden" name="action" value="set_default_address">
                        <input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>">
                        <button type="submit" class="btn secondary">Make default</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Remove this address?');">
                      <?= rb_csrf_input() ?>
                      <input type="hidden" name="action" value="delete_address">
                      <input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>">
                      <button type="submit" class="btn secondary">Remove</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="muted">No saved addresses yet.</p>
          <?php endif; ?>

          <details class="addr-add">
            <summary>Add new address</summary>
            <form method="post" class="grid2" novalidate enctype="multipart/form-data">
              <?= rb_csrf_input() ?>
              <input type="hidden" name="action" value="add_address">
              <div class="field"><label for="aLabel">Label</label><input id="aLabel" name="label" required><?php if (isset($addressErrors['label'])): ?><small class="muted error"><?= rb_escape($addressErrors['label']) ?></small><?php endif; ?></div>
              <div class="field"><label for="aName">Contact name</label><input id="aName" name="contact_name" required><?php if (isset($addressErrors['contact_name'])): ?><small class="muted error"><?= rb_escape($addressErrors['contact_name']) ?></small><?php endif; ?></div>
              <div class="field"><label for="aPhone">Phone</label><input id="aPhone" name="phone"></div>
              <div class="field"><label for="aLine1">Address line 1</label><input id="aLine1" name="line1" required><?php if (isset($addressErrors['line1'])): ?><small class="muted error"><?= rb_escape($addressErrors['line1']) ?></small><?php endif; ?></div>
              <div class="field"><label for="aLine2">Address line 2</label><input id="aLine2" name="line2"></div>
              <div class="field"><label for="aCity">City</label><input id="aCity" name="city" required><?php if (isset($addressErrors['city'])): ?><small class="muted error"><?= rb_escape($addressErrors['city']) ?></small><?php endif; ?></div>
              <div class="field"><label for="aState">State</label><input id="aState" name="state" required><?php if (isset($addressErrors['state'])): ?><small class="muted error"><?= rb_escape($addressErrors['state']) ?></small><?php endif; ?></div>
              <div class="field"><label for="aPost">Postcode</label><input id="aPost" name="postcode" required><?php if (isset($addressErrors['postcode'])): ?><small class="muted error"><?= rb_escape($addressErrors['postcode']) ?></small><?php endif; ?></div>
              <div class="field"><label for="aCountry">Country</label><input id="aCountry" name="country" value="Australia" required><?php if (isset($addressErrors['country'])): ?><small class="muted error"><?= rb_escape($addressErrors['country']) ?></small><?php endif; ?></div>
              <div class="row">
                <button class="btn-primary" type="submit">Save Address</button>
              </div>
            </form>
          </details>
        </section>

        <section id="tab-wishlist" class="pf-card" hidden>
          <h3>Wishlist</h3>
          <?php if (!$wishlistItems): ?>
            <p class="muted">Your wishlist is empty.</p>
          <?php endif; ?>
          <div class="wish-grid">
            <?php foreach ($wishlistItems as $item): ?>
              <div class="product-card">
                <a class="card-link" href="product.php?id=<?= (int)$item['product_id'] ?>">
                  <div class="product-thumb">
                    <img src="<?= rb_escape($item['image_path'] ?? 'media/logo.png') ?>" alt="<?= rb_escape($item['name']) ?>">
                  </div>
                  <div class="product-info">
                    <h4><?= rb_escape($item['name']) ?></h4>
                    <p class="price"><?= rb_escape(rb_format_price((float)$item['price'])) ?></p>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section id="tab-security" class="pf-card" hidden>
          <h3>Security</h3>
          <p class="muted">Keep your credentials up to date and monitor recent activity for peace of mind.</p>
          <?php if ($passwordMessage): ?>
            <div class="notice success"><?= rb_escape($passwordMessage) ?></div>
          <?php endif; ?>
          <?php if (isset($passwordErrors['general'])): ?>
            <div class="notice error"><?= rb_escape($passwordErrors['general']) ?></div>
          <?php endif; ?>
          <form method="post" class="security-form" novalidate>
            <?= rb_csrf_input() ?>
            <input type="hidden" name="action" value="update_password">
            <div class="field">
              <label for="curPwd">Current password</label>
              <input id="curPwd" name="current_password" type="password" autocomplete="current-password" required>
              <?php if (isset($passwordErrors['current_password'])): ?><small class="muted error"><?= rb_escape($passwordErrors['current_password']) ?></small><?php endif; ?>
            </div>
            <div class="field">
              <label for="newPwd">New password</label>
              <input id="newPwd" name="new_password" type="password" autocomplete="new-password" required>
              <small class="muted">Use at least 8 characters with a mix of numbers and uppercase letters.</small>
              <?php if (isset($passwordErrors['new_password'])): ?><small class="muted error"><?= rb_escape($passwordErrors['new_password']) ?></small><?php endif; ?>
            </div>
            <div class="field">
              <label for="confirmPwd">Confirm new password</label>
              <input id="confirmPwd" name="confirm_password" type="password" autocomplete="new-password" required>
              <?php if (isset($passwordErrors['confirm_password'])): ?><small class="muted error"><?= rb_escape($passwordErrors['confirm_password']) ?></small><?php endif; ?>
            </div>
            <div class="row">
              <button type="submit" class="btn-primary">Update password</button>
            </div>
          </form>
          <div class="security-tips">
            <h4>Security tips</h4>
            <ul>
              <li>Never share your password or OTP with anyone.</li>
              <li>Re-use of passwords across sites increases risk&mdash;keep it unique.</li>
              <li>We will soon launch two-factor authentication. Stay tuned!</li>
            </ul>
          </div>
        </section>

        <section id="tab-preferences" class="pf-card" hidden>
          <h3>Communication preferences</h3>
          <p class="muted">Choose how you want to hear from us.</p>
          <?php if ($preferencesMessage): ?>
            <div class="notice success"><?= rb_escape($preferencesMessage) ?></div>
          <?php endif; ?>
          <?php if ($preferencesError): ?>
            <div class="notice error"><?= rb_escape($preferencesError) ?></div>
          <?php endif; ?>
          <form method="post" class="pref-form">
            <?= rb_csrf_input() ?>
            <input type="hidden" name="action" value="update_preferences">
            <label class="pref-option">
              <input type="checkbox" name="marketing_emails" value="1"<?= $preferences['marketing_emails'] ? ' checked' : '' ?>>
              <div>
                <strong>Product news &amp; rituals</strong>
                <span class="muted">Monthly newsletters and guided practices.</span>
              </div>
            </label>
            <label class="pref-option">
              <input type="checkbox" name="order_updates" value="1"<?= $preferences['order_updates'] ? ' checked' : '' ?>>
              <div>
                <strong>Order updates</strong>
                <span class="muted">Stay notified about shipping progress and delivery.</span>
              </div>
            </label>
            <label class="pref-option">
              <input type="checkbox" name="sms_alerts" value="1"<?= $preferences['sms_alerts'] ? ' checked' : '' ?>>
              <div>
                <strong>SMS alerts</strong>
                <span class="muted">Receive delivery day reminders via text message.</span>
              </div>
            </label>
            <div class="row">
              <button type="submit" class="btn-primary">Save preferences</button>
            </div>
          </form>
        </section>
      </main>
    </div>
  </section>

  
  <?php include __DIR__ . '/includes/footer.php'; ?>


  <script src="<?= rb_asset('js/main.js') ?>"></script>
  <script src="<?= rb_asset('js/pages.js') ?>"></script>
</body>

</html>




