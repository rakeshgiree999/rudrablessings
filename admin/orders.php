<?php
declare(strict_types=1);

$adminCurrent = 'orders';
require __DIR__ . '/includes/admin_app.php';

$allowedStatuses = ['pending', 'paid', 'shipped', 'completed', 'cancelled', 'refunded'];

$statusFilter = (string)($_GET['status'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));
$orderNumber = trim((string)($_GET['order'] ?? ''));

$flash = $_SESSION['admin_flash'] ?? null;
if ($flash !== null) {
    unset($_SESSION['admin_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Your session expired. Please try again.',
        ];
        $redirectTarget = 'orders.php';
        if (!empty($_POST['order_number'])) {
            $redirectTarget .= '?order=' . urlencode((string)$_POST['order_number']);
        }
        header('Location: ' . $redirectTarget);
        exit;
    }
    $orderToUpdate = strtoupper(trim((string)($_POST['order_number'] ?? '')));
    $newStatus = trim((string)($_POST['new_status'] ?? ''));
    $redirect = 'orders.php';
    if ($orderToUpdate !== '') {
        $redirect .= '?order=' . urlencode($orderToUpdate);
    }

    if ($orderToUpdate === '' || !in_array($newStatus, $allowedStatuses, true)) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Invalid status update request. Please try again.',
        ];
        header('Location: ' . $redirect);
        exit;
    }

    $updateStmt = $pdo->prepare('UPDATE orders SET status = :status WHERE order_number = :order LIMIT 1');
    $updateStmt->execute([
        'status' => $newStatus,
        'order' => $orderToUpdate,
    ]);

    if ($updateStmt->rowCount() > 0) {
        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => 'Order status updated to ' . ucfirst($newStatus) . '.',
        ];
    } else {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'No changes were made. The order may not exist or already has that status.',
        ];
    }

    header('Location: ' . $redirect);
    exit;
}

$conditions = [];
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $conditions[] = 'o.status = :status';
    $params['status'] = $statusFilter;
}

if ($search !== '') {
    $conditions[] = '(o.order_number LIKE :term OR u.email LIKE :term)';
    $params['term'] = '%' . $search . '%';
}

$sql = "
    SELECT
        o.id,
        o.order_number,
        o.grand_total,
        o.subtotal,
        o.shipping,
        o.status,
        o.placed_at,
        u.email,
        CONCAT(u.first_name, ' ', u.last_name) AS customer_name
    FROM orders o
    INNER JOIN users u ON u.id = o.user_id
";

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY o.placed_at DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll() ?: [];

$selectedOrder = null;
$orderItems = [];
$orderShipping = [];

if ($orderNumber !== '') {
    $detailStmt = $pdo->prepare("
        SELECT o.*, u.email, CONCAT(u.first_name, ' ', u.last_name) AS customer_name
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        WHERE o.order_number = :order
        LIMIT 1
    ");
    $detailStmt->execute(['order' => $orderNumber]);
    $selectedOrder = $detailStmt->fetch();
    if ($selectedOrder) {
        $itemStmt = $pdo->prepare('SELECT product_name, quantity, unit_price, total_price FROM order_items WHERE order_id = :id ORDER BY id ASC');
        $itemStmt->execute(['id' => $selectedOrder['id']]);
        $orderItems = $itemStmt->fetchAll() ?: [];

        if ($selectedOrder['address_id']) {
            $addrStmt = $pdo->prepare('SELECT * FROM addresses WHERE id = :id LIMIT 1');
            $addrStmt->execute(['id' => $selectedOrder['address_id']]);
            $orderShipping = $addrStmt->fetch() ?: [];
        }
    }
}

$statusCounts = $pdo->query("
    SELECT status, COUNT(*) as total
    FROM orders
    GROUP BY status
")->fetchAll() ?: [];
$statusMap = [];
foreach ($statusCounts as $row) {
    $statusMap[$row['status']] = (int)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders | <?= rb_escape($brandName) ?> Admin</title>
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
  <style>
    .order-detail {
      display: grid;
      gap: 20px;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }
    .order-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 16px;
      box-shadow: 0 6px 16px rgba(15, 23, 42, .06);
    }
    .order-card h3 {
      margin: 0 0 10px;
    }
    .order-card ul {
      margin: 0;
      padding-left: 18px;
      font-size: 14px;
      color: var(--admin-muted);
    }
    .admin-flash {
      margin: 16px 0;
      padding: 12px 16px;
      border-radius: 12px;
      border: 1px solid transparent;
      font-size: 14px;
    }
    .admin-flash.success {
      background: #ecfdf5;
      border-color: #bbf7d0;
      color: #166534;
    }
    .admin-flash.error {
      background: #fef2f2;
      border-color: #fecaca;
      color: #b91c1c;
    }
    .order-status-form {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .order-status-form label {
      font-size: 14px;
      font-weight: 600;
      color: var(--admin-muted);
    }
    .order-status-form select {
      padding: 8px 12px;
      border-radius: 10px;
      border: 1px solid #d1d5db;
      min-width: 160px;
    }
    .order-status-form button {
      padding: 8px 16px;
      border-radius: 10px;
      border: none;
      background: #111827;
      color: #fff;
      cursor: pointer;
      transition: background .2s ease;
    }
    .order-status-form button:hover {
      background: #0f172a;
    }
  </style>
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="index.php">
        <img src="../media/logo.png" alt="<?= rb_escape($brandName) ?>" style="width:32px;height:32px;">
        <span><?= rb_escape($brandName) ?> Admin</span>
      </a>
      <nav class="admin-nav">
        <?php foreach ($adminNavItems as $item): ?>
          <?php $active = $item['key'] === $adminCurrent; ?>
          <a href="<?= rb_escape($item['href']) ?>"<?= $active ? ' class="active"' : '' ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true"><?= rb_admin_icon($item['icon']) ?></svg>
            <span><?= rb_escape($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <h1 style="margin:0;font-size:22px;">Orders</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Monitor order lifecycle and fulfilment progress.</p>
        </div>
      </header>

      <main class="admin-content">
        <?php if (!empty($flash) && is_array($flash)): ?>
          <div class="admin-flash <?= rb_escape($flash['type'] ?? 'info') ?>">
            <?= rb_escape($flash['message'] ?? '') ?>
          </div>
        <?php endif; ?>

        <section class="admin-section">
          <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
            <input type="search" name="q" placeholder="Search order # or email" value="<?= rb_escape($search) ?>" style="flex:1; min-width:220px; padding:10px 12px; border-radius:10px; border:1px solid #d1d5db;">
            <select name="status" style="padding:10px 12px; border-radius:10px; border:1px solid #d1d5db;">
              <option value="">All status</option>
              <?php foreach (['pending','paid','shipped','completed','cancelled','refunded'] as $status): ?>
                <option value="<?= $status ?>"<?= $statusFilter === $status ? ' selected' : '' ?>>
                  <?= ucfirst($status) ?> (<?= $statusMap[$status] ?? 0 ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <button class="admin-btn" type="submit">Filter</button>
          </form>

          <?php if ($orders): ?>
            <table class="admin-table" aria-label="Orders list">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Placed</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th>Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $order): ?>
                  <tr>
                    <td><?= rb_escape($order['order_number']) ?></td>
                    <td><?= rb_escape(date('M j, Y g:ia', strtotime($order['placed_at']))) ?></td>
                    <td>
                      <?= rb_escape($order['customer_name']) ?><br>
                      <small class="muted"><?= rb_escape($order['email']) ?></small>
                    </td>
                    <td><span class="admin-badge"><?= rb_escape(ucfirst($order['status'])) ?></span></td>
                    <td><?= rb_escape(rb_format_price((float)$order['grand_total'])) ?></td>
                    <td style="text-align:right;">
                      <a class="admin-btn secondary" href="?order=<?= rb_escape($order['order_number']) ?>">View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-state">
              No orders found for the selected filters.
            </div>
          <?php endif; ?>
        </section>

        <?php if ($selectedOrder): ?>
          <section class="admin-section">
            <div class="admin-actions" style="justify-content:space-between;">
              <h2 style="margin:0;">Order <?= rb_escape($selectedOrder['order_number']) ?></h2>
              <div class="admin-actions">
                <span class="admin-badge"><?= rb_escape(ucfirst($selectedOrder['status'])) ?></span>
                <a class="admin-btn secondary" href="orders.php">Close</a>
              </div>
            </div>

            <?php $statusUpdateAction = 'orders.php?order=' . rawurlencode((string)$selectedOrder['order_number']); ?>
            <form class="order-status-form" method="post" action="<?= rb_escape($statusUpdateAction) ?>">
              <?= rb_csrf_input() ?>
              <input type="hidden" name="order_number" value="<?= rb_escape($selectedOrder['order_number']) ?>">
              <label for="orderStatusSelect">Update status</label>
              <select id="orderStatusSelect" name="new_status">
                <?php foreach ($allowedStatuses as $statusOption): ?>
                  <option value="<?= rb_escape($statusOption) ?>"<?= $selectedOrder['status'] === $statusOption ? ' selected' : '' ?>>
                    <?= rb_escape(ucfirst($statusOption)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit">Save status</button>
            </form>

            <div class="order-detail" style="margin-top:20px;">
              <div class="order-card">
                <h3>Summary</h3>
                <ul>
                  <li>Total: <?= rb_escape(rb_format_price((float)$selectedOrder['grand_total'])) ?></li>
                  <li>Subtotal: <?= rb_escape(rb_format_price((float)$selectedOrder['subtotal'])) ?></li>
                  <li>Shipping: <?= rb_escape(rb_format_price((float)$selectedOrder['shipping'])) ?></li>
                  <li>Placed: <?= rb_escape(date('M j, Y g:ia', strtotime($selectedOrder['placed_at']))) ?></li>
                </ul>
              </div>
              <div class="order-card">
                <h3>Customer</h3>
                <ul>
                  <li><?= rb_escape($selectedOrder['customer_name']) ?></li>
                  <li><?= rb_escape($selectedOrder['email']) ?></li>
                </ul>
              </div>
              <div class="order-card">
                <h3>Shipping address</h3>
                <?php if ($orderShipping): ?>
                  <ul>
                    <li><?= rb_escape($orderShipping['contact_name'] ?? '') ?></li>
                    <li><?= rb_escape($orderShipping['line1'] ?? '') ?></li>
                    <?php if (!empty($orderShipping['line2'])): ?><li><?= rb_escape($orderShipping['line2']) ?></li><?php endif; ?>
                    <li><?= rb_escape(($orderShipping['city'] ?? '') . ', ' . ($orderShipping['state'] ?? '')) ?></li>
                    <li><?= rb_escape(($orderShipping['postcode'] ?? '') . ' ' . ($orderShipping['country'] ?? '')) ?></li>
                    <?php if (!empty($orderShipping['phone'])): ?><li><?= rb_escape($orderShipping['phone']) ?></li><?php endif; ?>
                  </ul>
                <?php else: ?>
                  <p class="muted">No address recorded.</p>
                <?php endif; ?>
              </div>
            </div>

            <div class="admin-section" style="margin-top:24px;">
              <h3>Items</h3>
              <?php if ($orderItems): ?>
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Quantity</th>
                      <th>Unit price</th>
                      <th>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($orderItems as $item): ?>
                      <tr>
                        <td><?= rb_escape($item['product_name']) ?></td>
                        <td><?= rb_escape((string)$item['quantity']) ?></td>
                        <td><?= rb_escape(rb_format_price((float)$item['unit_price'])) ?></td>
                        <td><?= rb_escape(rb_format_price((float)$item['total_price'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <div class="empty-state">No items recorded for this order.</div>
              <?php endif; ?>
            </div>
          </section>
        <?php endif; ?>
      </main>
    </div>
  </div>
</body>
</html>
