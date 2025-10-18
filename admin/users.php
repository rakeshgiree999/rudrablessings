<?php
declare(strict_types=1);

$adminCurrent = 'users';
require __DIR__ . '/includes/admin_app.php';

$search = trim((string)($_GET['q'] ?? ''));

$sql = "
    SELECT
        u.id,
        u.email,
        u.first_name,
        u.last_name,
        u.created_at,
        COALESCE((
            SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id
        ),0) AS orders_count,
        COALESCE((
            SELECT SUM(grand_total) FROM orders o WHERE o.user_id = u.id
        ),0) AS spend
    FROM users u
    WHERE u.role <> 'admin'
";

$params = [];
if ($search !== '') {
    $sql .= ' AND (u.email LIKE :term OR u.first_name LIKE :term OR u.last_name LIKE :term)';
    $params['term'] = '%' . $search . '%';
}

$sql .= ' ORDER BY u.created_at DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll() ?: [];

$customerTotal = (int)($pdo->query('SELECT COUNT(*) FROM users WHERE role <> "admin"')->fetchColumn() ?? 0);
$activeCustomerMonth = (int)($pdo->query('SELECT COUNT(DISTINCT user_id) FROM orders WHERE placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn() ?? 0);
$averageSpend = $customerTotal > 0 ? $pdo->query('SELECT COALESCE(SUM(grand_total),0)/COUNT(DISTINCT user_id) FROM orders')->fetchColumn() : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customers | <?= rb_escape($brandName) ?> Admin</title>
  <link rel="icon" href="<?= rb_asset('../media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('../media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
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
          <h1 style="margin:0;font-size:22px;">Customers</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Track customer signups and lifetime value.</p>
        </div>
      </header>

      <main class="admin-content">
        <section class="admin-cards">
          <div class="admin-card">
            <span class="label">Total customers</span>
            <span class="value"><?= rb_escape((string)$customerTotal) ?></span>
          </div>
          <div class="admin-card">
            <span class="label">30 day actives</span>
            <span class="value"><?= rb_escape((string)$activeCustomerMonth) ?></span>
          </div>
          <div class="admin-card">
            <span class="label">Avg spend</span>
            <span class="value"><?= rb_escape(rb_format_price((float)$averageSpend)) ?></span>
          </div>
        </section>

        <section class="admin-section">
          <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
            <input type="search" name="q" placeholder="Search email or name" value="<?= rb_escape($search) ?>" style="flex:1; min-width:220px; padding:10px 12px; border-radius:10px; border:1px solid #d1d5db;">
            <button class="admin-btn" type="submit">Search</button>
          </form>

          <?php if ($customers): ?>
            <table class="admin-table" aria-label="Customers">
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>Email</th>
                  <th>Orders</th>
                  <th>Total spend</th>
                  <th>Joined</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($customers as $customer): ?>
                  <tr>
                    <td><?= rb_escape(trim($customer['first_name'] . ' ' . $customer['last_name'])) ?></td>
                    <td><?= rb_escape($customer['email']) ?></td>
                    <td><?= rb_escape((string)$customer['orders_count']) ?></td>
                    <td><?= rb_escape(rb_format_price((float)$customer['spend'])) ?></td>
                    <td><?= rb_escape(date('M j, Y', strtotime($customer['created_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-state">
              No customers found.
            </div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>
</body>
</html>
