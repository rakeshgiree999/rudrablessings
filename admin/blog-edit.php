<?php
declare(strict_types=1);

$adminCurrent = 'blog';
require __DIR__ . '/includes/admin_app.php';

$postId = isset($_GET['id']) ? max(0, (int)$_GET['id']) : 0;
$isEditing = $postId > 0;

$form = [
    'title' => '',
    'slug' => '',
    'excerpt' => '',
    'body' => '',
    'hero_image' => '',
    'author_name' => '',
    'author_avatar' => '',
    'author_bio' => '',
    'status' => 'draft',
    'published_at' => '',
];
$errors = [];

if ($isEditing) {
    $post = rb_admin_blog_post($pdo, $postId);
    if (!$post) {
        header('Location: blog.php?notfound=1');
        exit;
    }
    $form = array_merge($form, [
        'title' => $post['title'],
        'slug' => $post['slug'],
        'excerpt' => $post['excerpt'],
        'body' => $post['body'],
        'hero_image' => $post['hero_image'],
        'author_name' => $post['author_name'],
        'author_avatar' => $post['author_avatar'],
        'author_bio' => $post['author_bio'],
        'status' => $post['status'],
        'published_at' => $post['published_at'] ?: '',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
        'body' => trim((string)($_POST['body'] ?? '')),
        'hero_image' => trim((string)($_POST['hero_image'] ?? '')),
        'author_name' => trim((string)($_POST['author_name'] ?? '')),
        'author_avatar' => trim((string)($_POST['author_avatar'] ?? '')),
        'author_bio' => trim((string)($_POST['author_bio'] ?? '')),
        'status' => in_array($_POST['status'] ?? 'draft', ['draft', 'published'], true) ? $_POST['status'] : 'draft',
        'published_at' => trim((string)($_POST['published_at'] ?? '')),
    ];

    if (!rb_csrf_validate_request()) {
        $errors['form'] = 'Your session expired. Please refresh and try again.';
    } else {
        $result = rb_admin_blog_save($pdo, $form, $isEditing ? $postId : null);
        if ($result['ok']) {
            $_SESSION['admin_flash'] = [
                'type' => 'success',
                'message' => 'Blog post saved successfully.',
            ];
            $redirectId = $result['id'] ?? $postId;
            header('Location: blog-edit.php?id=' . $redirectId);
            exit;
        }
        $errors = $result['errors'];
    }
}

$flash = $_SESSION['admin_flash'] ?? null;
if ($flash !== null) {
    unset($_SESSION['admin_flash']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEditing ? 'Edit Blog Post' : 'Add Blog Post' ?> | <?= rb_escape($brandName) ?></title>
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
  <style>
    .admin-form {
      display: grid;
      gap: 18px;
    }
    .admin-form .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .admin-form label {
      font-weight: 600;
    }
    .admin-form input,
    .admin-form textarea,
    .admin-form select {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #d1d5db;
      font-size: 14px;
      font-family: inherit;
    }
    .admin-form textarea {
      min-height: 160px;
      resize: vertical;
    }
    .admin-form .error {
      color: #b91c1c;
      font-size: 12px;
    }
    .form-grid {
      display: grid;
      gap: 18px;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
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
        <a class="admin-btn secondary" href="blog.php">Back to posts</a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <h1 style="margin:0;font-size:22px;"><?= $isEditing ? 'Edit blog post' : 'Create blog post' ?></h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Share new articles with your community.</p>
        </div>
        <div class="admin-actions">
          <a class="admin-btn secondary" href="<?= $form['slug'] !== '' ? '../blog-single.php?slug=' . urlencode($form['slug']) : '../blog.php' ?>" target="_blank" rel="noopener">View live</a>
        </div>
      </header>

      <main class="admin-content">
        <?php if ($flash): ?>
          <div class="notice <?= rb_escape($flash['type'] ?? 'success') ?>"><?= rb_escape($flash['message'] ?? '') ?></div>
        <?php endif; ?>
        <section class="admin-section">
          <form method="post" class="admin-form">
            <?= rb_csrf_input() ?>
            <?php if (isset($errors['form'])): ?>
              <div class="notice error"><?= rb_escape($errors['form']) ?></div>
            <?php endif; ?>
            <div class="field">
              <label for="title">Title</label>
              <input id="title" name="title" value="<?= rb_escape($form['title']) ?>" required>
              <?php if (isset($errors['title'])): ?><div class="error"><?= rb_escape($errors['title']) ?></div><?php endif; ?>
            </div>
            <div class="field">
              <label for="slug">Slug</label>
              <input id="slug" name="slug" value="<?= rb_escape($form['slug']) ?>" placeholder="auto-generated if left blank">
              <?php if (isset($errors['slug'])): ?><div class="error"><?= rb_escape($errors['slug']) ?></div><?php endif; ?>
            </div>
            <div class="field">
              <label for="excerpt">Excerpt</label>
              <textarea id="excerpt" name="excerpt" rows="3"><?= rb_escape($form['excerpt']) ?></textarea>
              <?php if (isset($errors['excerpt'])): ?><div class="error"><?= rb_escape($errors['excerpt']) ?></div><?php endif; ?>
            </div>
            <div class="field">
              <label for="body">Article body (HTML allowed)</label>
              <textarea id="body" name="body" rows="16"><?= rb_escape($form['body']) ?></textarea>
              <?php if (isset($errors['body'])): ?><div class="error"><?= rb_escape($errors['body']) ?></div><?php endif; ?>
            </div>
            <div class="form-grid">
              <div class="field">
                <label for="hero_image">Hero image path / URL</label>
                <input id="hero_image" name="hero_image" value="<?= rb_escape($form['hero_image']) ?>" placeholder="e.g. media/blog1.jpg">
              </div>
              <div class="field">
                <label for="author_name">Author name</label>
                <input id="author_name" name="author_name" value="<?= rb_escape($form['author_name']) ?>" required>
                <?php if (isset($errors['author_name'])): ?><div class="error"><?= rb_escape($errors['author_name']) ?></div><?php endif; ?>
              </div>
              <div class="field">
                <label for="author_avatar">Author avatar path / URL</label>
                <input id="author_avatar" name="author_avatar" value="<?= rb_escape($form['author_avatar']) ?>" placeholder="Optional">
              </div>
              <div class="field">
                <label for="author_bio">Author bio</label>
                <textarea id="author_bio" name="author_bio" rows="3"><?= rb_escape($form['author_bio']) ?></textarea>
              </div>
            </div>
            <div class="form-grid">
              <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                  <option value="draft"<?= $form['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
                  <option value="published"<?= $form['status'] === 'published' ? ' selected' : '' ?>>Published</option>
                </select>
              </div>
              <div class="field">
                <label for="published_at">Publish date</label>
                <input id="published_at" name="published_at" type="date" value="<?= $form['published_at'] ? rb_escape(date('Y-m-d', strtotime($form['published_at']))) : '' ?>">
                <?php if (isset($errors['published_at'])): ?><div class="error"><?= rb_escape($errors['published_at']) ?></div><?php endif; ?>
              </div>
            </div>
            <div class="admin-actions" style="justify-content:flex-end;">
              <a class="admin-btn secondary" href="blog.php">Cancel</a>
              <button type="submit" class="admin-btn"><?= $isEditing ? 'Save changes' : 'Create post' ?></button>
            </div>
          </form>
        </section>
      </main>
    </div>
  </div>
</body>
</html>
