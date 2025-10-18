<?php
declare(strict_types=1);

$adminCurrent = 'categories';
require __DIR__ . '/includes/admin_app.php';

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
        header('Location: categories.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'create_category') {
        $name = trim((string)($_POST['name'] ?? ''));
        $slugInput = trim((string)($_POST['slug'] ?? ''));
        $slug = $slugInput !== '' ? rb_admin_slugify($slugInput) : rb_admin_slugify($name);
        $description = trim((string)($_POST['description'] ?? ''));
        $imagePath = trim((string)($_POST['image_path'] ?? ''));
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $slug === '') {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Name and slug are required.',
            ];
            header('Location: categories.php');
            exit;
        }

        $slugCheck = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
        $slugCheck->execute(['slug' => $slug]);
        if ($slugCheck->fetch()) {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Another category already uses this slug.',
            ];
            header('Location: categories.php');
            exit;
        }

        $insert = $pdo->prepare('INSERT INTO categories (name, slug, description, image_path, is_active) VALUES (:name, :slug, :description, :image_path, :is_active)');
        $insert->execute([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'image_path' => $imagePath !== '' ? $imagePath : null,
            'is_active' => $isActive,
        ]);

        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => 'Category created successfully.',
        ];
        header('Location: categories.php');
        exit;
    }

    if ($action === 'update_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $slugInput = trim((string)($_POST['slug'] ?? ''));
        $slug = $slugInput !== '' ? rb_admin_slugify($slugInput) : rb_admin_slugify($name);
        $description = trim((string)($_POST['description'] ?? ''));
        $imagePath = trim((string)($_POST['image_path'] ?? ''));
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($categoryId <= 0 || $name === '' || $slug === '') {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Please provide a valid name and slug before saving.',
            ];
            header('Location: categories.php');
            exit;
        }

        $exists = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
        $exists->execute(['id' => $categoryId]);
        if (!$exists->fetch()) {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Category not found.',
            ];
            header('Location: categories.php');
            exit;
        }

        $slugCheck = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug AND id <> :id LIMIT 1');
        $slugCheck->execute(['slug' => $slug, 'id' => $categoryId]);
        if ($slugCheck->fetch()) {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Another category already uses this slug.',
            ];
            header('Location: categories.php');
            exit;
        }

        $update = $pdo->prepare('UPDATE categories SET name = :name, slug = :slug, description = :description, image_path = :image_path, is_active = :is_active WHERE id = :id');
        $update->execute([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'image_path' => $imagePath !== '' ? $imagePath : null,
            'is_active' => $isActive,
            'id' => $categoryId,
        ]);

        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => 'Category updated.',
        ];
        header('Location: categories.php');
        exit;
    }
}

$categories = $pdo->query('SELECT id, name, slug, description, image_path, is_active FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Categories | <?= rb_escape($brandName) ?> Admin</title>
  <link rel="icon" href="<?= rb_asset('../media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('../media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
  <style>
    .category-grid {
      display: grid;
      gap: 16px;
    }
    .category-card {
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      padding: 16px;
      display: grid;
      gap: 12px;
    }
    .category-card header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .category-card form {
      display: grid;
      gap: 10px;
    }
    .category-card label {
      font-weight: 600;
      font-size: 13px;
      color: #374151;
    }
    .category-card input,
    .category-card textarea,
    .category-card select {
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 14px;
      font-family: inherit;
    }
    .category-card textarea {
      min-height: 80px;
      resize: vertical;
    }
    .category-card .actions {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }
    .create-card {
      border-style: dashed;
      background: #f9fafb;
    }
  </style>
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
        <a class="admin-btn secondary" href="../index.php">View storefront</a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <h1 style="margin:0;font-size:22px;">Categories</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Manage product categories and toggle their visibility on the storefront.</p>
        </div>
      </header>

      <main class="admin-content">
        <?php if ($flash): ?>
          <div class="notice <?= rb_escape($flash['type'] ?? 'info') ?>"><?= rb_escape($flash['message'] ?? '') ?></div>
        <?php endif; ?>

        <section class="category-grid">
          <?php foreach ($categories as $category): ?>
            <article class="category-card">
              <header>
                <strong><?= rb_escape($category['name']) ?></strong>
                <span class="badge" style="background:<?= $category['is_active'] ? '#dcfce7' : '#fee2e2' ?>;color:<?= $category['is_active'] ? '#166534' : '#7f1d1d' ?>;">
                  <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </header>
              <form method="post">
                <?= rb_csrf_input() ?>
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                <label>
                  Name
                  <input name="name" value="<?= rb_escape($category['name']) ?>" required>
                </label>
                <label>
                  Slug
                  <input name="slug" value="<?= rb_escape($category['slug']) ?>" required>
                </label>
                <label>
                  Image path
                  <input name="image_path" value="<?= rb_escape((string)$category['image_path']) ?>" placeholder="media/...">
                </label>
                <label>
                  Description
                  <textarea name="description" rows="2"><?= rb_escape((string)$category['description']) ?></textarea>
                </label>
                <label style="display:flex;gap:8px;align-items:center;">
                  <input type="checkbox" name="is_active" value="1"<?= $category['is_active'] ? ' checked' : '' ?>>
                  <span>Visible on storefront</span>
                </label>
                <div class="actions">
                  <button type="submit" class="admin-btn">Save changes</button>
                </div>
              </form>
            </article>
          <?php endforeach; ?>

          <article class="category-card create-card">
            <strong>Create a category</strong>
            <form method="post">
              <?= rb_csrf_input() ?>
              <input type="hidden" name="action" value="create_category">
              <label>
                Name
                <input name="name" placeholder="e.g. Crystals" required>
              </label>
              <label>
                Slug
                <input name="slug" placeholder="Generated automatically if left blank">
              </label>
              <label>
                Image path
                <input name="image_path" placeholder="media/cat-crystals.jpg">
              </label>
              <label>
                Description
                <textarea name="description" rows="2" placeholder="Short summary shown in listings."></textarea>
              </label>
              <label style="display:flex;gap:8px;align-items:center;">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Visible on storefront</span>
              </label>
              <div class="actions" style="justify-content:flex-start;">
                <button type="submit" class="admin-btn">Create category</button>
              </div>
            </form>
          </article>
        </section>
      </main>
    </div>
  </div>
</body>
</html>
