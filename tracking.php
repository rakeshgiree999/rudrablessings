<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$currentPath = 'tracking.php';

$orderNumberInput = trim((string)($_GET['id'] ?? ''));
$emailInput = trim((string)($_GET['email'] ?? ''));
$formSubmitted = $orderNumberInput !== '' || $emailInput !== '';
$orderData = null;
$orderItems = [];
$lookupError = '';

$trackingSteps = ['Placed', 'Paid', 'Packed', 'Shipped', 'Out', 'Delivered'];
$stepStates = array_map(static fn(string $label): array => ['label' => $label, 'state' => 'upcoming'], $trackingSteps);
$progressPercent = 0;
$orderSubtitle = 'Please enter your order details above.';
$orderStatus = 'pending';
$orderStatusLabel = 'Pending';
$statusBadgeClass = 'badge status pending';
$orderIdDisplay = 'N/A';
$orderDateDisplay = 'N/A';
$customerName = 'N/A';
$customerEmail = 'N/A';
$shipToDisplay = 'N/A';
$trackingNumberDisplay = 'N/A';
$itemCount = 0;

if ($formSubmitted) {
    if ($orderNumberInput === '' || $emailInput === '') {
        $lookupError = 'Please provide both the order ID and the email used at checkout.';
    } elseif (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
        $lookupError = 'Please enter a valid email address.';
    } else {
        $orderLookup = $pdo->prepare("
            SELECT o.id, o.order_number, o.status, o.subtotal, o.shipping, o.tax, o.grand_total, o.placed_at,
                   u.email AS customer_email, u.first_name, u.last_name,
                   a.contact_name, a.line1, a.line2, a.city, a.state, a.postcode, a.country
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            LEFT JOIN addresses a ON a.id = o.address_id
            WHERE o.order_number = :order AND LOWER(u.email) = :email
            LIMIT 1
        ");
        $orderLookup->execute([
            'order' => strtoupper($orderNumberInput),
            'email' => mb_strtolower($emailInput),
        ]);
        $orderData = $orderLookup->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($orderData) {
            $itemsStmt = $pdo->prepare("
                SELECT oi.product_id, oi.product_name, oi.quantity, oi.unit_price, oi.total_price,
                       (
                           SELECT image_path
                           FROM product_images pi
                           WHERE pi.product_id = oi.product_id
                           ORDER BY pi.sort_order ASC, pi.id ASC
                           LIMIT 1
                       ) AS image_path
                FROM order_items oi
                WHERE oi.order_id = :order_id
                ORDER BY oi.id ASC
            ");
            $itemsStmt->execute(['order_id' => (int)$orderData['id']]);
            $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $lookupError = 'We could not find an order matching that ID and email.';
        }
    }
}

if ($orderData) {
    $orderStatus = strtolower((string)$orderData['status']);
    $statusBadgeClass = 'badge status ' . $orderStatus;
    $orderStatusLabel = ucwords(str_replace('_', ' ', $orderStatus));
    $orderIdDisplay = (string)$orderData['order_number'];
    $orderDateDisplay = rb_format_date($orderData['placed_at'] ?? null, 'M j, Y g:ia') ?? 'N/A';

    $first = trim((string)($orderData['first_name'] ?? ''));
    $last = trim((string)($orderData['last_name'] ?? ''));
    $customerName = trim($first . ' ' . $last);
    if ($customerName === '') {
        $contactName = trim((string)($orderData['contact_name'] ?? ''));
        if ($contactName !== '') {
            $customerName = $contactName;
        } else {
            $customerName = 'Customer';
        }
    }

    $customerEmail = strtolower((string)($orderData['customer_email'] ?? $emailInput));

    $addressParts = array_filter([
        trim((string)($orderData['contact_name'] ?? '')),
        trim((string)($orderData['line1'] ?? '')),
        trim((string)($orderData['line2'] ?? '')),
        trim((string)($orderData['city'] ?? '')),
        trim((string)($orderData['state'] ?? '')),
        trim((string)($orderData['postcode'] ?? '')),
        trim((string)($orderData['country'] ?? '')),
    ], static fn(string $part): bool => $part !== '');
    $shipToDisplay = $addressParts ? implode(', ', $addressParts) : 'N/A';

    $itemCount = array_sum(array_map(static fn(array $item): int => (int)($item['quantity'] ?? 0), $orderItems));
    $orderSubtitle = sprintf(
        'Placed on %s | %d item%s',
        rb_format_date($orderData['placed_at'] ?? null, 'M j, Y') ?? 'N/A',
        $itemCount,
        $itemCount === 1 ? '' : 's'
    );

    if ($orderStatus === 'cancelled') {
        $orderSubtitle = 'This order has been cancelled.';
    } elseif ($orderStatus === 'refunded') {
        $orderSubtitle = 'This order has been refunded.';
    }

    $trackingSeed = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)$orderData['order_number']));
    $trackingNumberDisplay = 'TRK-' . substr(hash('crc32b', $trackingSeed), 0, 8);

    $stepIndexMap = [
        'pending' => 0,
        'paid' => 1,
        'shipped' => 3,
        'completed' => 5,
        'refunded' => 5,
        'cancelled' => 0,
    ];
    $activeStepIndex = $stepIndexMap[$orderStatus] ?? 0;
    $progressPercent = count($trackingSteps) > 1
        ? max(0, min(100, round(($activeStepIndex / (count($trackingSteps) - 1)) * 100)))
        : 0;

    foreach ($trackingSteps as $idx => $label) {
        $state = 'upcoming';
        if ($orderStatus === 'cancelled') {
            $state = $idx === 0 ? 'cancelled' : 'upcoming';
        } else {
            if ($idx < $activeStepIndex) {
                $state = 'completed';
            } elseif ($idx === $activeStepIndex) {
                $state = 'current';
            }
        }
        $stepStates[$idx] = ['label' => $label, 'state' => $state];
    }
} else {
    // Ensure visible defaults when an email was provided but no result
    if ($lookupError !== '' && $formSubmitted) {
        $orderSubtitle = 'Double-check your Order ID and email, then try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Order Tracking | RudraBlessings</title>
  <meta name="description"
    content="Track your RudraBlessings order by ID and email. View live status, shipment details, and delivery progress.">
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/pages.css') ?>">
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <!-- MAIN -->
  <main class="track-wrap container">
    <h1>Track Your Order</h1>

    <form class="track-form" method="get" action="tracking.php" id="trackForm">
      <input name="id" id="orderIdInput" placeholder="Order ID (e.g. RB-10235)" value="<?= rb_escape($orderNumberInput) ?>" required>
      <input name="email" id="emailInput" type="email" placeholder="Email used at checkout" value="<?= rb_escape($emailInput) ?>" required>
      <button type="submit">Track</button>
    </form>

    <?php if ($lookupError !== ''): ?>
      <div class="notice error"><?= rb_escape($lookupError) ?></div>
    <?php endif; ?>

    <section class="track-summary">
      <div class="track-head">
        <h2 id="orderTitle">Order</h2>
        <span id="orderStatus" class="<?= rb_escape($statusBadgeClass) ?>"><?= rb_escape($orderStatusLabel) ?></span>
      </div>
      <p class="muted" id="orderSubtitle"><?= rb_escape($orderSubtitle) ?></p>
    </section>

    <div class="status-bar">
      <div id="statusFill" class="status-fill" data-target="<?= rb_escape((string)$progressPercent) ?>" style="width: <?= rb_escape((string)$progressPercent) ?>%;"></div>
    </div>
    <div class="status-steps muted">
      <?php foreach ($stepStates as $step): ?>
        <div class="<?= rb_escape($step['state']) ?>"><?= rb_escape($step['label']) ?></div>
      <?php endforeach; ?>
    </div>

    <div class="meta">
      <div><span class="label">Order ID</span><strong id="orderId"><?= rb_escape($orderIdDisplay) ?></strong></div>
      <div><span class="label">Date</span><strong id="orderDate"><?= rb_escape($orderDateDisplay) ?></strong></div>
      <div><span class="label">Customer</span><strong id="customerName"><?= rb_escape($customerName) ?></strong></div>
      <div><span class="label">Email</span><strong id="customerEmail"><?= rb_escape($customerEmail) ?></strong></div>
    </div>

    <div class="meta">
      <div><span class="label">Ship To</span><strong id="shipTo"><?= rb_escape($shipToDisplay) ?></strong></div>
      <div><span class="label">Tracking No.</span><strong id="trackingNo"><?= rb_escape($trackingNumberDisplay) ?></strong></div>
    </div>

    <h3>Items</h3>
    <div id="items" class="items">
      <?php if ($orderData && $orderItems): ?>
        <?php foreach ($orderItems as $item): ?>
          <?php
          $itemImage = $item['image_path'] ?? null;
          if (!$itemImage) {
              $itemImage = 'media/logo.png';
          }
          $quantity = (int)($item['quantity'] ?? 0);
          $lineTotal = rb_format_price((float)($item['total_price'] ?? 0.0));
          ?>
          <div class="item">
            <img src="<?= rb_escape($itemImage) ?>" alt="<?= rb_escape($item['product_name'] ?? 'Product') ?>">
            <div>
              <strong><?= rb_escape($item['product_name'] ?? 'Product') ?></strong>
              <div class="muted">Qty <?= rb_escape((string)$quantity) ?> | <?= rb_escape($lineTotal) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php elseif ($orderData && !$orderItems): ?>
        <p class="muted" style="grid-column:1/-1;">No items found for this order.</p>
      <?php else: ?>
        <p class="muted" style="grid-column:1/-1;">Items will display after you locate your order.</p>
      <?php endif; ?>
    </div>

    <p class="muted">For assistance, contact support with your Order ID.</p>
  </main>

  <!-- footer  -->
  
  <?php include __DIR__ . '/includes/footer.php'; ?>


  <script src="<?= rb_asset('js/main.js') ?>"></script>
  <script src="<?= rb_asset('js/pages.js') ?>"></script>
  <script src="<?= rb_asset('js/tracking.js') ?>"></script>
</body>

</html>










