<?php
declare(strict_types=1);

$adminCurrent = 'reports';
require __DIR__ . '/includes/admin_app.php';

$defaultStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
$defaultEnd = (new DateTimeImmutable())->format('Y-m-d');

$from = $_GET['from'] ?? $defaultStart;
$to = $_GET['to'] ?? $defaultEnd;

$fromDate = DateTimeImmutable::createFromFormat('Y-m-d', $from) ?: new DateTimeImmutable($defaultStart);
$toDate = DateTimeImmutable::createFromFormat('Y-m-d', $to) ?: new DateTimeImmutable($defaultEnd);

$fromParam = $fromDate->format('Y-m-d 00:00:00');
$toParam = $toDate->modify('+1 day')->format('Y-m-d 00:00:00');

$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT o.id) AS orders,
        COALESCE(SUM(oi.total_price), 0) AS gross_revenue,
        COALESCE(SUM(oi.quantity * COALESCE(NULLIF(oi.cost_price, 0), NULLIF(p.cost_price, 0), 0)), 0) AS cost_of_goods,
        COALESCE(SUM(oi.total_price - (oi.quantity * COALESCE(NULLIF(oi.cost_price, 0), NULLIF(p.cost_price, 0), 0))), 0) AS net_revenue
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.placed_at >= :from AND o.placed_at < :to
");
$summaryStmt->execute(['from' => $fromParam, 'to' => $toParam]);
$summary = $summaryStmt->fetch() ?: ['orders' => 0, 'gross_revenue' => 0, 'cost_of_goods' => 0, 'net_revenue' => 0];
$summaryOrders = (int)($summary['orders'] ?? 0);
$summaryGross = (float)($summary['gross_revenue'] ?? 0.0);
$summaryNet = (float)($summary['net_revenue'] ?? 0.0);
$summaryAvgOrder = $summaryOrders > 0 ? $summaryGross / $summaryOrders : 0.0;

$dailyStmt = $pdo->prepare("
    SELECT
        DATE(o.placed_at) AS day,
        COUNT(DISTINCT o.id) AS orders,
        COALESCE(SUM(oi.total_price), 0) AS gross_revenue,
        COALESCE(SUM(oi.total_price - (oi.quantity * COALESCE(NULLIF(oi.cost_price, 0), NULLIF(p.cost_price, 0), 0))), 0) AS net_revenue
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.placed_at >= :from AND o.placed_at < :to
    GROUP BY day
    ORDER BY day ASC
");
$dailyStmt->execute(['from' => $fromParam, 'to' => $toParam]);
$daily = $dailyStmt->fetchAll() ?: [];

$dailyByDate = [];
foreach ($daily as $row) {
    $key = (string)$row['day'];
    $dailyByDate[$key] = [
        'day' => $key,
        'orders' => (int)$row['orders'],
        'gross' => (float)$row['gross_revenue'],
        'net' => (float)$row['net_revenue'],
    ];
}

$period = new DatePeriod($fromDate, new DateInterval('P1D'), $toDate->add(new DateInterval('P1D')));
$dailyFilled = [];
foreach ($period as $date) {
    $key = $date->format('Y-m-d');
    $dailyFilled[] = $dailyByDate[$key] ?? [
        'day' => $key,
        'orders' => 0,
        'gross' => 0.0,
        'net' => 0.0,
    ];
}
$daily = $dailyFilled;

$categoryStmt = $pdo->prepare("
    SELECT
        c.name,
        SUM(oi.quantity) AS qty,
        SUM(oi.total_price) AS gross_revenue,
        SUM(oi.total_price - (oi.quantity * COALESCE(NULLIF(oi.cost_price, 0), NULLIF(p.cost_price, 0), 0))) AS net_revenue
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    INNER JOIN product_categories pc ON pc.product_id = oi.product_id
    INNER JOIN categories c ON c.id = pc.category_id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.placed_at >= :from AND o.placed_at < :to
    GROUP BY c.id, c.name
    ORDER BY gross_revenue DESC
    LIMIT 6
");
$categoryStmt->execute(['from' => $fromParam, 'to' => $toParam]);
$categoryBreakdown = $categoryStmt->fetchAll() ?: [];

$lowStock = $pdo->query('SELECT name, stock FROM products WHERE stock <= 5 ORDER BY stock ASC, name ASC LIMIT 8')->fetchAll() ?: [];

$dailyLabels = array_map(static fn($row) => date('M j', strtotime($row['day'])), $daily);
$dailyValues = array_map(static fn($row) => (float)$row['gross'], $daily);
$dailyNetValues = array_map(static fn($row) => (float)$row['net'], $daily);
$categoryLabels = array_map(static fn($row) => $row['name'], $categoryBreakdown);
$categoryGrossValues = array_map(static fn($row) => (float)$row['gross_revenue'], $categoryBreakdown);
$categoryNetValues = array_map(static fn($row) => (float)$row['net_revenue'], $categoryBreakdown);
$dailyLabelsJson = json_encode($dailyLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$dailyValuesJson = json_encode($dailyValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$dailyNetValuesJson = json_encode($dailyNetValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$categoryLabelsJson = json_encode($categoryLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$categoryGrossValuesJson = json_encode($categoryGrossValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$categoryNetValuesJson = json_encode($categoryNetValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';

if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Orders', 'Gross Revenue', 'Net Revenue']);
    foreach ($daily as $row) {
        fputcsv($out, [
            $row['day'],
            $row['orders'],
            number_format((float)$row['gross'], 2, '.', ''),
            number_format((float)$row['net'], 2, '.', ''),
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports | <?= rb_escape($brandName) ?> Admin</title>
  <link rel="icon" href="<?= rb_asset('../media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('../media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
  <style>
    .chart-grid {
      display: grid;
      gap: 20px;
    }
    @media (min-width: 960px) {
      .chart-grid {
        grid-template-columns: 2fr 1fr;
      }
    }
    .chart-area {
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      padding: 16px;
      box-shadow: 0 6px 16px rgba(15, 23, 42, .06);
      overflow-x: auto;
    }
    .chart-area canvas {
      width: 100% !important;
      max-height: 220px;
    }
    .chart-area table {
      width: 100%;
      border-collapse: collapse;
    }
    .chart-area th,
    .chart-area td {
      padding: 10px 12px;
      text-align: left;
      border-bottom: 1px solid #f1f5f9;
      font-size: 14px;
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
          <h1 style="margin:0;font-size:22px;">Sales reports</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Analyse revenue and product performance across selected dates.</p>
        </div>
        <div class="admin-actions">
          <a class="admin-btn secondary" href="?from=<?= rb_escape($defaultStart) ?>&amp;to=<?= rb_escape($defaultEnd) ?>">Reset</a>
        </div>
      </header>

      <main class="admin-content">
        <section class="admin-section">
          <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;">
            <label style="display:flex;flex-direction:column;font-size:13px;color:var(--admin-muted);">
              From
              <input type="date" name="from" value="<?= rb_escape($fromDate->format('Y-m-d')) ?>" style="padding:10px 12px;border-radius:10px;border:1px solid #d1d5db;">
            </label>
            <label style="display:flex;flex-direction:column;font-size:13px;color:var(--admin-muted);">
              To
              <input type="date" name="to" value="<?= rb_escape($toDate->format('Y-m-d')) ?>" style="padding:10px 12px;border-radius:10px;border:1px solid #d1d5db;">
            </label>
            <button class="admin-btn" type="submit" style="align-self:flex-end;">Update</button>
            <a class="admin-btn secondary" style="align-self:flex-end;" href="?download=csv&amp;from=<?= rb_escape($fromDate->format('Y-m-d')) ?>&amp;to=<?= rb_escape($toDate->format('Y-m-d')) ?>">Export CSV</a>
          </form>
        </section>

        <section class="admin-cards">
          <div class="admin-card">
            <span class="label">Net revenue</span>
            <span class="value"><?= rb_escape(rb_format_price($summaryNet)) ?></span>
            <small class="muted">After cost of goods</small>
          </div>
          <div class="admin-card">
            <span class="label">Orders</span>
            <span class="value"><?= rb_escape((string)$summaryOrders) ?></span>
            <small class="muted"><?= rb_escape($summaryOrders ? rb_format_price($summaryAvgOrder) : '$0.00') ?> avg order value</small>
          </div>
          <div class="admin-card">
            <span class="label">Gross sales</span>
            <span class="value"><?= rb_escape(rb_format_price($summaryGross)) ?></span>
          </div>
        </section>

        <div class="chart-grid">
          <div class="chart-area">
            <h2 style="margin:0 0 12px;">Daily revenue</h2>
            <?php if ($daily): ?>
              <canvas id="reportRevenueChart" height="220" style="height:220px;max-height:220px;margin-bottom:12px;"></canvas>
              <table>
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Orders</th>
                    <th>Gross</th>
                    <th>Net</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($daily as $row): ?>
                    <tr>
                      <td><?= rb_escape(date('M j, Y', strtotime($row['day']))) ?></td>
                      <td><?= rb_escape((string)$row['orders']) ?></td>
                      <td><?= rb_escape(rb_format_price((float)$row['gross'])) ?></td>
                      <td><?= rb_escape(rb_format_price((float)$row['net'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="empty-state">No orders in this period.</div>
            <?php endif; ?>
          </div>
          <div class="chart-area">
            <h2 style="margin:0 0 12px;">Top categories</h2>
            <?php if ($categoryBreakdown): ?>
              <canvas id="reportCategoryChart" height="180" style="margin-bottom:16px;"></canvas>
              <table>
                <thead>
                  <tr>
                    <th>Category</th>
                    <th>Units</th>
                    <th>Gross</th>
                    <th>Net</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($categoryBreakdown as $row): ?>
                    <tr>
                      <td><?= rb_escape($row['name']) ?></td>
                      <td><?= rb_escape((string)$row['qty']) ?></td>
                      <td><?= rb_escape(rb_format_price((float)$row['gross_revenue'])) ?></td>
                      <td><?= rb_escape(rb_format_price((float)$row['net_revenue'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="empty-state">Category performance will appear once sales are recorded.</div>
            <?php endif; ?>
          </div>
        </div>

        <section class="admin-section">
          <h2 style="margin:0 0 12px;">Low stock alerts</h2>
          <?php if ($lowStock): ?>
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Stock</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lowStock as $product): ?>
                  <tr>
                    <td><?= rb_escape($product['name']) ?></td>
                    <td><?= rb_escape((string)$product['stock']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-state">Great job! No low stock products right now.</div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" integrity="sha384-GTWWBRGZp6eENCDJDkEuWxpPfpsLifvVQJxZ0/iwYDg+BYJKuWaNfTe74mQX87Ue" crossorigin="anonymous"></script>
  <script>
    (function () {
      const revenueLabels = <?= $dailyLabelsJson ?>;
      const grossValues = <?= $dailyValuesJson ?>;
      const netValues = <?= $dailyNetValuesJson ?>;
      const categoryLabels = <?= $categoryLabelsJson ?>;
      const categoryGrossValues = <?= $categoryGrossValuesJson ?>;
      const categoryNetValues = <?= $categoryNetValuesJson ?>;

      const revenueCanvas = document.getElementById('reportRevenueChart');
      const categoryCanvas = document.getElementById('reportCategoryChart');
      const hasChartJs = typeof window.Chart === 'function';
      const dpr = window.devicePixelRatio || 1;

      function setupCanvas(canvas) {
        if (!canvas) {
          return null;
        }
        const rect = canvas.getBoundingClientRect();
        const width = rect.width || canvas.clientWidth || parseInt(canvas.getAttribute('width'), 10) || 600;
        const height = rect.height || canvas.clientHeight || parseInt(canvas.getAttribute('height'), 10) || 200;
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

      function drawLegend(ctx, items, startX, startY) {
        let x = startX;
        const y = startY;
        ctx.font = '12px "Inter", system-ui, sans-serif';
        items.forEach(item => {
          ctx.fillStyle = item.color;
          ctx.fillRect(x, y - 10, 12, 12);
          ctx.fillStyle = '#1f2937';
          ctx.fillText(item.label, x + 18, y);
          x += ctx.measureText(item.label).width + 48;
        });
      }

      function renderRevenueFallback(canvas, labels, gross, net) {
        const setup = setupCanvas(canvas);
        if (!setup || (!gross.length && !net.length)) {
          return;
        }
        const { ctx, width, height } = setup;
        const padding = { top: 32, right: 16, bottom: 36, left: 56 };
        const innerWidth = Math.max(10, width - padding.left - padding.right);
        const innerHeight = Math.max(10, height - padding.top - padding.bottom);
        const allValues = gross.concat(net);
        const maxValue = Math.max(...allValues, 1);
        const minValue = Math.min(0, ...allValues);
        const range = maxValue - minValue || 1;

        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 1;
        const gridLines = Math.min(6, Math.max(3, labels.length));
        ctx.font = '12px "Inter", system-ui, sans-serif';
        ctx.fillStyle = '#64748b';
        for (let i = 0; i <= gridLines; i += 1) {
          const ratio = i / gridLines;
          const y = padding.top + innerHeight - ratio * innerHeight;
          ctx.beginPath();
          ctx.moveTo(padding.left, y);
          ctx.lineTo(width - padding.right, y);
          ctx.stroke();
          const tick = minValue + range * ratio;
          ctx.fillText(`$${tick.toFixed(range > 50 ? 0 : 2)}`, 8, y + 4);
        }

        ctx.strokeStyle = '#cbd5e1';
        ctx.beginPath();
        ctx.moveTo(padding.left, padding.top);
        ctx.lineTo(padding.left, padding.top + innerHeight);
        ctx.lineTo(width - padding.right, padding.top + innerHeight);
        ctx.stroke();

        const stepX = labels.length > 1 ? innerWidth / (labels.length - 1) : innerWidth;
        const convert = value => padding.top + innerHeight - ((value - minValue) / range) * innerHeight;

        const grossPoints = gross.map((value, index) => ({
          x: padding.left + stepX * index,
          y: convert(value)
        }));
        const netPoints = net.map((value, index) => ({
          x: padding.left + stepX * index,
          y: convert(value)
        }));

        const gradient = ctx.createLinearGradient(0, padding.top, 0, height);
        gradient.addColorStop(0, 'rgba(22, 163, 74, 0.28)');
        gradient.addColorStop(1, 'rgba(22, 163, 74, 0)');

        if (grossPoints.length) {
          ctx.beginPath();
          grossPoints.forEach((point, index) => {
            if (index === 0) {
              ctx.moveTo(point.x, point.y);
            } else {
              ctx.lineTo(point.x, point.y);
            }
          });
          ctx.lineTo(grossPoints[grossPoints.length - 1].x, padding.top + innerHeight);
          ctx.lineTo(grossPoints[0].x, padding.top + innerHeight);
          ctx.closePath();
          ctx.fillStyle = gradient;
          ctx.fill();

          ctx.beginPath();
          grossPoints.forEach((point, index) => {
            if (index === 0) {
              ctx.moveTo(point.x, point.y);
            } else {
              ctx.lineTo(point.x, point.y);
            }
          });
          ctx.strokeStyle = '#15803d';
          ctx.lineWidth = 2;
          ctx.stroke();
        }

        if (netPoints.length) {
          ctx.beginPath();
          netPoints.forEach((point, index) => {
            if (index === 0) {
              ctx.moveTo(point.x, point.y);
            } else {
              ctx.lineTo(point.x, point.y);
            }
          });
          ctx.strokeStyle = '#ea580c';
          ctx.lineWidth = 2;
          ctx.stroke();
        }

        ctx.fillStyle = '#475569';
        ctx.font = '12px "Inter", system-ui, sans-serif';
        labels.forEach((label, index) => {
          const x = padding.left + stepX * index;
          const textWidth = ctx.measureText(label).width;
          ctx.fillText(label, x - textWidth / 2, height - 8);
        });

        drawLegend(ctx, [
          { label: 'Gross revenue', color: '#16a34a' },
          { label: 'Net revenue', color: '#f97316' }
        ], padding.left, padding.top - 12);
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

      function renderCategoryFallback(canvas, labels, gross, net) {
        const setup = setupCanvas(canvas);
        if (!setup || (!gross.length && !net.length)) {
          return;
        }
        const { ctx, width, height } = setup;
        const padding = { top: 32, right: 20, bottom: 44, left: 64 };
        const innerWidth = Math.max(10, width - padding.left - padding.right);
        const innerHeight = Math.max(10, height - padding.top - padding.bottom);
        const maxValue = Math.max(...gross.concat(net), 1);

        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 1;
        const gridLines = Math.min(5, Math.max(3, labels.length));
        ctx.font = '12px "Inter", system-ui, sans-serif';
        ctx.fillStyle = '#64748b';
        for (let i = 0; i <= gridLines; i += 1) {
          const ratio = i / gridLines;
          const y = padding.top + innerHeight - ratio * innerHeight;
          ctx.beginPath();
          ctx.moveTo(padding.left, y);
          ctx.lineTo(width - padding.right, y);
          ctx.stroke();
          const tick = (maxValue * ratio);
          ctx.fillText('$' + tick.toFixed(maxValue > 100 ? 0 : 2), 12, y + 4);
        }

        ctx.strokeStyle = '#cbd5e1';
        ctx.beginPath();
        ctx.moveTo(padding.left, padding.top);
        ctx.lineTo(padding.left, padding.top + innerHeight);
        ctx.lineTo(width - padding.right, padding.top + innerHeight);
        ctx.stroke();

        const groups = labels.length;
        const totalBarsPerGroup = 2;
        const groupGap = Math.min(32, innerWidth / (groups * 2));
        const barGap = 8;
        const totalGapPerGroup = barGap * (totalBarsPerGroup - 1);
        const barWidth = Math.max(14, (innerWidth / groups) - groupGap - totalGapPerGroup);

        labels.forEach((label, index) => {
          const baseX = padding.left + groupGap / 2 + (innerWidth / groups) * index;

          const grossValue = gross[index] ?? 0;
          const netValue = net[index] ?? 0;
          const grossHeight = (grossValue / maxValue) * innerHeight;
          const netHeight = (netValue / maxValue) * innerHeight;

          const grossX = baseX;
          const netX = baseX + barWidth + barGap;

          const grossY = padding.top + innerHeight - grossHeight;
          const netY = padding.top + innerHeight - netHeight;

          ctx.fillStyle = '#2563eb';
          drawRoundedBar(ctx, grossX, grossY, barWidth, grossHeight, 6);

          ctx.fillStyle = '#f59e0b';
          drawRoundedBar(ctx, netX, netY, barWidth, netHeight, 6);

          ctx.fillStyle = '#475569';
          const textWidth = ctx.measureText(label).width;
          const groupCenter = baseX + (barWidth * totalBarsPerGroup + barGap) / 2 - barGap / 2;
          ctx.fillText(label, groupCenter - textWidth / 2, height - 8);
        });

        drawLegend(ctx, [
          { label: 'Gross', color: '#2563eb' },
          { label: 'Net', color: '#f59e0b' }
        ], padding.left, padding.top - 12);
      }

      function drawFallbackCharts() {
        if (revenueCanvas && (grossValues.length || netValues.length)) {
          renderRevenueFallback(revenueCanvas, revenueLabels, grossValues, netValues);
        }
        if (categoryCanvas && (categoryGrossValues.length || categoryNetValues.length)) {
          renderCategoryFallback(categoryCanvas, categoryLabels, categoryGrossValues, categoryNetValues);
        }
      }

      if (hasChartJs) {
        if (revenueCanvas) {
          new Chart(revenueCanvas, {
            type: 'line',
            data: {
              labels: revenueLabels,
              datasets: [{
                label: 'Gross revenue',
                data: grossValues,
                borderColor: '#16a34a',
                backgroundColor: 'rgba(22, 163, 74, 0.18)',
                tension: 0.35,
                fill: true,
                borderWidth: 2,
                pointRadius: 3
              }, {
                label: 'Net revenue',
                data: netValues,
                borderColor: '#f97316',
                backgroundColor: 'rgba(249, 115, 22, 0.12)',
                tension: 0.35,
                fill: false,
                borderWidth: 2,
                pointRadius: 3
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: true } },
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

        if (categoryCanvas) {
          new Chart(categoryCanvas, {
            type: 'bar',
            data: {
              labels: categoryLabels,
              datasets: [{
                label: 'Gross',
                data: categoryGrossValues,
                backgroundColor: '#2563eb'
              }, {
                label: 'Net',
                data: categoryNetValues,
                backgroundColor: '#f59e0b'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { position: 'bottom' }
              },
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
      } else {
        drawFallbackCharts();
        window.addEventListener('resize', drawFallbackCharts);
      }
    })();
  </script>
</body>
</html>
