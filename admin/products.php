<?php
declare(strict_types=1);

$adminCurrent = 'products';
require __DIR__ . '/includes/admin_app.php';

$query = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Your session expired. Please try again.',
        ];
        header('Location: products.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_product') {
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        if ($productId <= 0) {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Invalid product selection.',
            ];
            header('Location: products.php');
            exit;
        }

        $imageStmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = :id');
        $imageStmt->execute(['id' => $productId]);
        $imagePaths = array_map(static fn($row) => (string)$row['image_path'], $imageStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $orderUsageStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = :id');
        $orderUsageStmt->execute(['id' => $productId]);
        $usageCount = (int)$orderUsageStmt->fetchColumn();
        if ($usageCount > 0) {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Cannot delete a product that exists on past orders.',
            ];
            header('Location: products.php');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM product_images WHERE product_id = :id')->execute(['id' => $productId]);
            $pdo->prepare('DELETE FROM product_categories WHERE product_id = :id')->execute(['id' => $productId]);
            $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $productId]);
            $pdo->commit();

            $mediaRoot = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'media');
            if ($mediaRoot) {
                foreach ($imagePaths as $path) {
                    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
                    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $normalized;
                    $realFull = realpath($fullPath);
                    if ($realFull && strpos($realFull, $mediaRoot) === 0 && is_file($realFull)) {
                        @unlink($realFull);
                    }
                }
            }

            $_SESSION['admin_flash'] = [
                'type' => 'success',
                'message' => 'Product deleted.',
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Unable to delete product. ' . $exception->getMessage(),
            ];
        }

        header('Location: products.php');
        exit;
    }
}

$sql = "
    SELECT
        p.id,
        p.name,
        p.slug,
        p.price,
        p.stock,
        p.status,
        p.is_featured,
        p.is_new_arrival,
        p.updated_at,
        (
            SELECT name FROM categories c
            INNER JOIN product_categories pc ON pc.category_id = c.id
            WHERE pc.product_id = p.id
            ORDER BY c.name
            LIMIT 1
        ) AS category_name,
        (
            SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1
        ) AS image_path
    FROM products p
    WHERE 1=1
";

$params = [];

if ($query !== '') {
    $sql .= " AND (p.name LIKE :term OR p.slug LIKE :term)";
    $params['term'] = '%' . $query . '%';
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'inactive', 'archived'], true)) {
    $sql .= " AND p.status = :status";
    $params['status'] = $statusFilter;
}

$sql .= " ORDER BY p.updated_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll() ?: [];

$totalProducts = (int)($pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() ?? 0);
$lowStockTotal = (int)($pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 5')->fetchColumn() ?? 0);

$flashMessage = '';
$flash = $_SESSION['admin_flash'] ?? null;
if ($flash !== null) {
    unset($_SESSION['admin_flash']);
}

if (isset($_GET['saved'])) {
    $flashMessage = 'Product saved successfully.';
} elseif (isset($_GET['deleted'])) {
    $flashMessage = 'Product deleted.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products | <?= rb_escape($brandName) ?> Admin</title>
  <link rel="icon" href="<?= rb_asset('../media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('../media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
  <style>
    .admin-btn.danger {
      background: #dc2626;
      color: #fff;
    }
    .admin-btn.danger:hover {
      background: #b91c1c;
    }
    .admin-actions-inline {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      align-items: center;
    }
    .admin-actions-inline form {
      margin: 0;
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
          <h1 style="margin:0;font-size:22px;">Products</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Manage catalogue, prices and stock levels.</p>
        </div>
        <div class="admin-actions">
          <a class="admin-btn" href="product-edit.php">Add product</a>
        </div>
      </header>

      <main class="admin-content">
        <section class="admin-cards">
          <div class="admin-card">
            <span class="label">Total products</span>
            <span class="value"><?= rb_escape((string)$totalProducts) ?></span>
          </div>
          <div class="admin-card">
            <span class="label">Low stock (&le;5)</span>
            <span class="value"><?= rb_escape((string)$lowStockTotal) ?></span>
          </div>
        </section>

        <section class="admin-section">
          <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
            <input type="search" name="q" placeholder="Search products..." value="<?= rb_escape($query) ?>" style="flex:1; min-width:220px; padding:10px 12px; border-radius:10px; border:1px solid #d1d5db;">
            <select name="status" style="padding:10px 12px; border-radius:10px; border:1px solid #d1d5db;">
              <option value="">All status</option>
              <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>Active</option>
              <option value="inactive"<?= $statusFilter === 'inactive' ? ' selected' : '' ?>>Inactive</option>
              <option value="archived"<?= $statusFilter === 'archived' ? ' selected' : '' ?>>Archived</option>
            </select>
            <button class="admin-btn" type="submit">Filter</button>
          </form>

          <?php if ($flash): ?>
            <div class="notice <?= rb_escape($flash['type'] ?? 'success') ?>" style="margin-bottom:16px;"><?= rb_escape($flash['message'] ?? '') ?></div>
          <?php endif; ?>
          <?php if ($flashMessage): ?>
            <div class="notice success" style="margin-bottom:16px;"><?= rb_escape($flashMessage) ?></div>
          <?php endif; ?>

          <?php if ($products): ?>
            <table class="admin-table" aria-label="Products list">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Stock</th>
                  <th>Status</th>
                  <th>Updated</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($products as $product): ?>
                  <tr>
                    <td style="display:flex; align-items:center; gap:12px;">
                      <?php if (!empty($product['image_path'])): ?>
                        <img src="../<?= rb_escape($product['image_path']) ?>" alt="<?= rb_escape($product['name']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;">
                      <?php endif; ?>
                      <div>
                        <strong><?= rb_escape($product['name']) ?></strong>
                        <?php if (!empty($product['is_featured'])): ?>
                          <span class="admin-badge" style="margin-left:6px;background:#fbbf24;color:#92400e;">Featured</span>
                        <?php endif; ?>
                        <?php if (!empty($product['is_new_arrival'])): ?>
                          <span class="admin-badge" style="margin-left:6px;background:#bfdbfe;color:#1d4ed8;">New</span>
                        <?php endif; ?>
                        <br>
                        <small class="muted"><?= rb_escape($product['slug']) ?></small>
                      </div>
                    </td>
                    <td><?= rb_escape($product['category_name'] ?? '—') ?></td>
                    <td><?= rb_escape(rb_format_price((float)$product['price'])) ?></td>
                    <td>
                      <?= rb_escape((string)$product['stock']) ?>
                      <?php if ((int)$product['stock'] <= 5): ?>
                        <span class="admin-badge" style="margin-left:6px;">Low</span>
                      <?php endif; ?>
                    </td>
                    <td><span class="admin-badge"><?= rb_escape(ucfirst($product['status'])) ?></span></td>
                    <td><?= rb_escape(date('M j, Y', strtotime($product['updated_at']))) ?></td>
                    <td style="text-align:right;">
                      <div class="admin-actions-inline">
                        <a class="admin-btn secondary" href="product-edit.php?id=<?= (int)$product['id'] ?>">Edit</a>
                        <form method="post" class="delete-form">
                          <?= rb_csrf_input() ?>
                          <input type="hidden" name="action" value="delete_product">
                          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                          <button type="submit" class="admin-btn danger">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-state">
              No products found. Try adjusting the filters or <a href="product-edit.php">add a new product</a>.
            </div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>
<script>
  document.addEventListener('submit', (event) => {
    if (event.target && event.target.matches('.delete-form')) {
      if (!confirm('Delete this product? This action cannot be undone.')) {
        event.preventDefault();
      }
    }
  });
</script>
</body>
</html>

