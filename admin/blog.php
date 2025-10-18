<?php
declare(strict_types=1);

$adminCurrent = 'blog';
require __DIR__ . '/includes/admin_app.php';

$statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : '';
if (!in_array($statusFilter, ['', 'draft', 'published'], true)) {
    $statusFilter = '';
}
$search = trim((string)($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Your session expired. Please try again.',
        ];
        header('Location: blog.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_post') {
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        if ($postId > 0) {
            $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
            $stmt->execute(['id' => $postId]);
            $_SESSION['admin_flash'] = [
                'type' => 'success',
                'message' => 'Blog post deleted.',
            ];
        } else {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Invalid blog post selection.',
            ];
        }
        header('Location: blog.php');
        exit;
    }

    if ($action === 'change_status') {
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $newStatus = in_array($_POST['status'] ?? '', ['draft', 'published'], true) ? $_POST['status'] : 'draft';
        if ($postId > 0) {
            if ($newStatus === 'published') {
                $stmt = $pdo->prepare('UPDATE blog_posts SET status = :status, published_at = COALESCE(published_at, CURRENT_DATE) WHERE id = :id');
            } else {
                $stmt = $pdo->prepare('UPDATE blog_posts SET status = :status, published_at = NULL WHERE id = :id');
            }
            $stmt->execute([
                'status' => $newStatus,
                'id' => $postId,
            ]);
            $_SESSION['admin_flash'] = [
                'type' => 'success',
                'message' => 'Post status updated.',
            ];
        } else {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Invalid blog post selection.',
            ];
        }
        header('Location: blog.php');
        exit;
    }
}

$posts = rb_admin_blog_posts($pdo, [
    'status' => $statusFilter ?: null,
    'search' => $search !== '' ? $search : null,
]);

$publishedCount = (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn();
$draftCount = (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'")->fetchColumn();

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
  <title>Blog Posts | <?= rb_escape($brandName) ?></title>
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
        <a class="admin-btn secondary" href="../blog.php">View blog</a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <h1 style="margin:0;font-size:22px;">Blog posts</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Create, edit, and manage your published articles.</p>
        </div>
        <div class="admin-actions">
          <a class="admin-btn" href="blog-edit.php">Add post</a>
        </div>
      </header>

      <main class="admin-content">
        <section class="admin-cards">
          <div class="admin-card">
            <span class="label">Published</span>
            <span class="value"><?= rb_escape((string)$publishedCount) ?></span>
          </div>
          <div class="admin-card">
            <span class="label">Drafts</span>
            <span class="value"><?= rb_escape((string)$draftCount) ?></span>
          </div>
        </section>

        <section class="admin-section">
          <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
            <input type="search" name="q" placeholder="Search posts..." value="<?= rb_escape($search) ?>" style="flex:1; min-width:220px; padding:10px 12px; border-radius:10px; border:1px solid #d1d5db;">
            <select name="status" style="padding:10px 12px; border-radius:10px; border:1px solid #d1d5db;">
              <option value="">All status</option>
              <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Published</option>
              <option value="draft"<?= $statusFilter === 'draft' ? ' selected' : '' ?>>Draft</option>
            </select>
            <button class="admin-btn" type="submit">Filter</button>
          </form>

          <?php if ($flash): ?>
            <div class="notice <?= rb_escape($flash['type'] ?? 'info') ?>" style="margin-bottom:16px;"><?= rb_escape($flash['message'] ?? '') ?></div>
          <?php endif; ?>

          <?php if ($posts): ?>
            <table class="admin-table" aria-label="Blog posts list">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Slug</th>
                  <th>Status</th>
                  <th>Published</th>
                  <th>Updated</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($posts as $post): ?>
                  <tr>
                    <td>
                      <strong><?= rb_escape($post['title']) ?></strong>
                    </td>
                    <td><?= rb_escape($post['slug']) ?></td>
                    <td><span class="admin-badge"><?= rb_escape(ucfirst($post['status'])) ?></span></td>
                    <td><?= $post['published_at'] ? rb_escape(date('M j, Y', strtotime($post['published_at']))) : '—' ?></td>
                    <td><?= rb_escape(date('M j, Y', strtotime($post['updated_at']))) ?></td>
                    <td style="text-align:right;">
                      <div class="admin-actions" style="justify-content:flex-end;">
                        <a class="admin-btn secondary" href="blog-edit.php?id=<?= (int)$post['id'] ?>">Edit</a>
                        <form method="post">
                          <?= rb_csrf_input() ?>
                          <input type="hidden" name="action" value="change_status">
                          <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                          <input type="hidden" name="status" value="<?= $post['status'] === 'published' ? 'draft' : 'published' ?>">
                          <button type="submit" class="admin-btn secondary"><?= $post['status'] === 'published' ? 'Move to draft' : 'Publish' ?></button>
                        </form>
                        <form method="post" class="delete-form">
                          <?= rb_csrf_input() ?>
                          <input type="hidden" name="action" value="delete_post">
                          <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
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
              No blog posts found. Try adjusting the filters or <a href="blog-edit.php">create your first article</a>.
            </div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>
  <script>
    document.addEventListener('submit', (event) => {
      if (event.target && event.target.matches('.delete-form')) {
        if (!confirm('Delete this blog post? This action cannot be undone.')) {
          event.preventDefault();
        }
      }
    });
  </script>
</body>
</html>
