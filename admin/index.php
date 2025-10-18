<?php
declare(strict_types=1);

$adminCurrent = 'dashboard';
require __DIR__ . '/includes/admin_app.php';

$productCount = (int)($pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() ?? 0);
$activeProducts = (int)($pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn() ?? 0);
$lowStockCount = (int)($pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 5')->fetchColumn() ?? 0);
$orderCount = (int)($pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() ?? 0);
$totalRevenue = (float)($pdo->query('SELECT COALESCE(SUM(grand_total),0) FROM orders')->fetchColumn() ?? 0.0);
$monthRevenueStmt = $pdo->prepare('SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE placed_at >= DATE_FORMAT(CURRENT_DATE, "%Y-%m-01")');
$monthRevenueStmt->execute();
$monthRevenue = (float)$monthRevenueStmt->fetchColumn();
$newCustomers = (int)($pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer" AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn() ?? 0);

$recentOrders = $pdo->query("SELECT order_number, grand_total, status, placed_at, user_id FROM orders ORDER BY placed_at DESC LIMIT 6")->fetchAll() ?: [];
$topProducts = $pdo->query("
    SELECT
        oi.product_id,
        oi.product_name,
        SUM(oi.quantity) AS qty_sold,
        SUM(oi.total_price) AS revenue
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    GROUP BY oi.product_id, oi.product_name
    ORDER BY qty_sold DESC
    LIMIT 6
")->fetchAll() ?: [];

$revenueSeries = $pdo->query("
    SELECT DATE(placed_at) AS day, SUM(grand_total) AS revenue
    FROM orders
    WHERE placed_at >= DATE_SUB(CURDATE(), INTERVAL 10 DAY)
    GROUP BY day
    ORDER BY day ASC
")->fetchAll() ?: [];

$revenueLabels = array_map(static fn($row) => date('M j', strtotime($row['day'])), $revenueSeries);
$revenueValues = array_map(static fn($row) => (float)$row['revenue'], $revenueSeries);
$topProductLabels = array_map(static fn($row) => $row['product_name'] ?? 'Product', $topProducts);
$topProductValues = array_map(static fn($row) => (int)($row['qty_sold'] ?? 0), $topProducts);
$revenueLabelsJson = json_encode($revenueLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$revenueValuesJson = json_encode($revenueValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$topProductLabelsJson = json_encode($topProductLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$topProductValuesJson = json_encode($topProductValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | <?= rb_escape($brandName) ?></title>
  <link rel="icon" href="<?= rb_asset('../media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('../media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="#">
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
      <div style="margin-top:auto">
        <a class="admin-btn secondary" href="../index.php">View Storefront</a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <h1 style="margin:0;font-size:22px;">Dashboard</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Overview of store performance and inventory health.</p>
        </div>
        <div class="user">
          <div>
            <div style="font-weight:600;"><?= rb_escape($avatarMeta['label']) ?></div>
            <small style="color:var(--admin-muted);">Administrator</small>
          </div>
          <img src="<?= rb_escape($avatarMeta['url']) ?>" alt="<?= rb_escape($avatarMeta['alt']) ?>">
          <a class="admin-btn secondary" href="../logout.php">Sign out</a>
        </div>
      </header>

      <main class="admin-content">
        <section class="admin-cards">
          <div class="admin-card">
            <span class="label">Total Revenue</span>
            <span class="value"><?= rb_escape(rb_format_price($totalRevenue)) ?></span>
            <small class="muted">All time sales</small>
          </div>
          <div class="admin-card">
            <span class="label">This Month</span>
            <span class="value"><?= rb_escape(rb_format_price($monthRevenue)) ?></span>
            <small class="muted">Revenue since <?= rb_escape(date('M 1, Y')) ?></small>
          </div>
          <div class="admin-card">
            <span class="label">Products</span>
            <span class="value"><?= rb_escape((string)$productCount) ?></span>
            <small class="muted"><?= rb_escape((string)$activeProducts) ?> active &middot; <?= rb_escape((string)$lowStockCount) ?> low stock</small>
          </div>
          <div class="admin-card">
            <span class="label">Orders</span>
            <span class="value"><?= rb_escape((string)$orderCount) ?></span>
            <small class="muted">New customers: <?= rb_escape((string)$newCustomers) ?> in 30 days</small>
          </div>
        </section>

        <section class="admin-section">
          <h2 style="margin:0 0 12px;">Revenue trend (last 10 days)</h2>
          <?php if ($revenueSeries): ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px;">
              <canvas id="adminRevenueChart" height="140"></canvas>
            </div>
          <?php else: ?>
            <div class="empty-state">Revenue chart will appear once orders are placed.</div>
          <?php endif; ?>
        </section>

        <section class="admin-section">
          <div class="admin-actions" style="justify-content: space-between;">
            <h2 style="margin:0;">Recent Orders</h2>
            <a class="admin-btn" href="orders.php">View all</a>
          </div>
          <?php if ($recentOrders): ?>
            <table class="admin-table" aria-label="Recent orders">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $order): ?>
                  <tr>
                    <td><a href="orders.php?order=<?= rb_escape($order['order_number']) ?>"><?= rb_escape($order['order_number']) ?></a></td>
                    <td><?= rb_escape(date('M j, Y g:ia', strtotime($order['placed_at']))) ?></td>
                    <td><span class="admin-badge"><?= rb_escape(ucfirst($order['status'])) ?></span></td>
                    <td><?= rb_escape(rb_format_price((float)$order['grand_total'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-state">No orders yet. Promote your products to start selling!</div>
          <?php endif; ?>
        </section>

        <section class="admin-section">
          <div class="admin-actions" style="justify-content: space-between;">
            <h2 style="margin:0;">Top Selling Products</h2>
            <a class="admin-btn secondary" href="products.php">Manage products</a>
          </div>
          <?php if ($topProducts): ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px;margin-bottom:16px;">
              <canvas id="adminTopProductsChart" height="150"></canvas>
            </div>
          <?php endif; ?>
          <?php if ($topProducts): ?>
            <table class="admin-table" aria-label="Top products">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Units sold</th>
                  <th>Revenue</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($topProducts as $product): ?>
                  <tr>
                    <td><?= rb_escape($product['product_name'] ?? 'Product') ?></td>
                    <td><?= rb_escape((string)($product['qty_sold'] ?? 0)) ?></td>
                    <td><?= rb_escape(rb_format_price((float)($product['revenue'] ?? 0))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-state">Sales data will appear once orders start coming in.</div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" integrity="sha384-GTWWBRGZp6eENCDJDkEuWxpPfpsLifvVQJxZ0/iwYDg+BYJKuWaNfTe74mQX87Ue" crossorigin="anonymous"></script>
  <script>
    (function () {
      const revenueLabels = <?= $revenueLabelsJson ?>;
      const revenueValues = <?= $revenueValuesJson ?>;
      const topProductLabels = <?= $topProductLabelsJson ?>;
      const topProductValues = <?= $topProductValuesJson ?>;

      const dpr = window.devicePixelRatio || 1;

      function setupCanvas(canvas) {
        if (!canvas) {
          return null;
        }
        const rect = canvas.getBoundingClientRect();
        const width = rect.width > 0 ? rect.width : (canvas.clientWidth || 600);
        const height = rect.height > 0 ? rect.height : (parseInt(canvas.getAttribute('height'), 10) || 200);
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
          return null;
        }
        if (typeof ctx.resetTransform === 'function') {
          ctx.resetTransform();
        }
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);
        return { ctx, width, height };
      }

      function renderLineChart(canvas, labels, values) {
        const setup = setupCanvas(canvas);
        if (!setup || !values.length) {
          return;
        }
        const { ctx, width, height } = setup;
        const padding = { top: 18, right: 12, bottom: 28, left: 48 };
        const innerWidth = Math.max(10, width - padding.left - padding.right);
        const innerHeight = Math.max(10, height - padding.top - padding.bottom);
        const maxValue = Math.max(...values);
        const minValue = Math.min(0, Math.min(...values));
        const range = maxValue - minValue || 1;

        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 1;
        const gridLines = Math.min(6, Math.max(3, values.length));
        for (let i = 0; i <= gridLines; i += 1) {
          const ratio = i / gridLines;
          const y = padding.top + innerHeight - ratio * innerHeight;
          ctx.beginPath();
          ctx.moveTo(padding.left, y);
          ctx.lineTo(width - padding.right, y);
          ctx.stroke();

          const tickValue = minValue + (range * ratio);
          ctx.fillStyle = '#64748b';
          ctx.font = '12px "Inter", system-ui, sans-serif';
          ctx.fillText(`$${tickValue.toFixed(range >= 10 ? 0 : 2)}`, 8, y + 4);
        }

        ctx.strokeStyle = '#cbd5e1';
        ctx.beginPath();
        ctx.moveTo(padding.left, padding.top);
        ctx.lineTo(padding.left, padding.top + innerHeight);
        ctx.lineTo(width - padding.right, padding.top + innerHeight);
        ctx.stroke();

        const stepX = values.length > 1 ? innerWidth / (values.length - 1) : innerWidth;
        const points = values.map((value, index) => {
          const x = padding.left + (stepX * index);
          const y = padding.top + innerHeight - ((value - minValue) / range) * innerHeight;
          return { x, y };
        });

        const gradient = ctx.createLinearGradient(0, padding.top, 0, height);
        gradient.addColorStop(0, 'rgba(245, 158, 11, 0.28)');
        gradient.addColorStop(1, 'rgba(245, 158, 11, 0)');

        ctx.beginPath();
        points.forEach((point, index) => {
          if (index === 0) {
            ctx.moveTo(point.x, point.y);
          } else {
            ctx.lineTo(point.x, point.y);
          }
        });
        ctx.strokeStyle = '#f59e0b';
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.lineTo(points[points.length - 1].x, padding.top + innerHeight);
        ctx.lineTo(points[0].x, padding.top + innerHeight);
        ctx.closePath();
        ctx.fillStyle = gradient;
        ctx.fill();

        ctx.fillStyle = '#f59e0b';
        points.forEach(point => {
          ctx.beginPath();
          ctx.arc(point.x, point.y, 3.5, 0, Math.PI * 2);
          ctx.fill();
        });

        ctx.fillStyle = '#475569';
        ctx.font = '12px "Inter", system-ui, sans-serif';
        labels.forEach((label, index) => {
          const x = padding.left + (stepX * index);
          const textWidth = ctx.measureText(label).width;
          ctx.fillText(label, x - textWidth / 2, height - 6);
        });
      }

      function drawRoundedBar(ctx, x, y, width, height, radius) {
        const r = Math.min(radius, width / 2, height / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + width - r, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + r);
        ctx.lineTo(x + width, y + height - r);
        ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
        ctx.lineTo(x + r, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
        ctx.fill();
      }

      function renderBarChart(canvas, labels, values) {
        const setup = setupCanvas(canvas);
        if (!setup || !values.length) {
          return;
        }
        const { ctx, width, height } = setup;
        const padding = { top: 10, right: 10, bottom: 32, left: 44 };
        const innerWidth = Math.max(10, width - padding.left - padding.right);
        const innerHeight = Math.max(10, height - padding.top - padding.bottom);
        const maxValue = Math.max(...values, 1);

        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 1;
        const gridLines = Math.min(5, Math.max(3, values.length));
        for (let i = 0; i <= gridLines; i += 1) {
          const ratio = i / gridLines;
          const y = padding.top + innerHeight - ratio * innerHeight;
          ctx.beginPath();
          ctx.moveTo(padding.left, y);
          ctx.lineTo(width - padding.right, y);
          ctx.stroke();

          const tickValue = maxValue * ratio;
          ctx.fillStyle = '#64748b';
          ctx.font = '12px "Inter", system-ui, sans-serif';
          ctx.fillText(Math.round(tickValue).toString(), 12, y + 4);
        }

        ctx.strokeStyle = '#cbd5e1';
        ctx.beginPath();
        ctx.moveTo(padding.left, padding.top);
        ctx.lineTo(padding.left, padding.top + innerHeight);
        ctx.lineTo(width - padding.right, padding.top + innerHeight);
        ctx.stroke();

        const barGap = Math.min(28, innerWidth / (values.length * 2));
        const barWidth = Math.max(12, (innerWidth - barGap * (values.length + 1)) / values.length);

        values.forEach((value, index) => {
          const x = padding.left + barGap * (index + 1) + barWidth * index;
          const barHeight = (value / maxValue) * innerHeight;
          const y = padding.top + innerHeight - barHeight;
          ctx.fillStyle = '#2563eb';
          drawRoundedBar(ctx, x, y, barWidth, barHeight, 6);

          ctx.fillStyle = '#1f2937';
          ctx.font = '12px "Inter", system-ui, sans-serif';
          ctx.fillText(String(value), x + barWidth / 2 - ctx.measureText(String(value)).width / 2, y - 6);

          ctx.fillStyle = '#475569';
          const label = labels[index];
          const textWidth = ctx.measureText(label).width;
          ctx.fillText(label, x + barWidth / 2 - textWidth / 2, height - 8);
        });
      }

      const revenueCanvas = document.getElementById('adminRevenueChart');
      const topCanvas = document.getElementById('adminTopProductsChart');
      const hasChartJs = typeof window.Chart === 'function';

      function drawWithFallback() {
        if (revenueCanvas && revenueValues.length) {
          renderLineChart(revenueCanvas, revenueLabels, revenueValues);
        }
        if (topCanvas && topProductValues.length) {
          renderBarChart(topCanvas, topProductLabels, topProductValues);
        }
      }

      if (hasChartJs) {
        if (revenueCanvas) {
          new Chart(revenueCanvas, {
            type: 'line',
            data: {
              labels: revenueLabels,
              datasets: [{
                label: 'Revenue',
                data: revenueValues,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.15)',
                tension: 0.35,
                fill: true,
                borderWidth: 2,
                pointRadius: 3
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: {
                y: {
                  ticks: {
                    callback: value => '$' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                  }
                }
              }
            }
          });
        }

        if (topCanvas) {
          new Chart(topCanvas, {
            type: 'bar',
            data: {
              labels: topProductLabels,
              datasets: [{
                label: 'Units sold',
                data: topProductValues,
                backgroundColor: '#2563eb'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
              }
            }
          });
        }
      } else {
        drawWithFallback();
        window.addEventListener('resize', () => drawWithFallback());
      }
    })();
  </script>
</body>
</html>
