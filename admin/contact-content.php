<?php
declare(strict_types=1);

$adminCurrent = 'contact_content';
require __DIR__ . '/includes/admin_app.php';

/**
 * Simple redirect helper with optional anchor.
 */
$redirectToPage = static function (string $anchor = ''): void {
    $target = 'contact-content.php';
    if ($anchor !== '') {
        $target .= '#' . $anchor;
    }
    header('Location: ' . $target);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Your session expired. Please try again.',
        ];
        $redirectToPage();
    }

    $section = (string)($_POST['section'] ?? '');
    $action = (string)($_POST['action'] ?? 'save');
    $anchor = '';
    $message = 'Changes saved.';

    try {
        switch ($section) {
            case 'stat':
                $anchor = 'stats';
                $id = (int)($_POST['id'] ?? 0);
                if ($action === 'delete') {
                    if ($id > 0) {
                        $stmt = $pdo->prepare('DELETE FROM contact_stats WHERE id = :id');
                        $stmt->execute(['id' => $id]);
                        $message = 'Stat removed.';
                    }
                    break;
                }
                $value = trim((string)($_POST['metric_value'] ?? ''));
                $label = trim((string)($_POST['metric_label'] ?? ''));
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                if ($value === '' || $label === '') {
                    throw new RuntimeException('Value and label are required.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE contact_stats SET metric_value = :value, metric_label = :label, sort_order = :sort WHERE id = :id');
                    $stmt->execute([
                        'value' => $value,
                        'label' => $label,
                        'sort' => $sortOrder,
                        'id' => $id,
                    ]);
                    $message = 'Stat updated.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO contact_stats (metric_value, metric_label, sort_order) VALUES (:value, :label, :sort)');
                    $stmt->execute([
                        'value' => $value,
                        'label' => $label,
                        'sort' => $sortOrder,
                    ]);
                    $message = 'Stat created.';
                }
                break;

            case 'channel':
                $anchor = 'channels';
                $id = (int)($_POST['id'] ?? 0);
                if ($action === 'delete') {
                    if ($id > 0) {
                        $stmt = $pdo->prepare('DELETE FROM contact_channels WHERE id = :id');
                        $stmt->execute(['id' => $id]);
                        $message = 'Channel removed.';
                    }
                    break;
                }
                $icon = trim((string)($_POST['icon'] ?? ''));
                $title = trim((string)($_POST['title'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $ctaLabel = trim((string)($_POST['cta_label'] ?? ''));
                $ctaUrl = trim((string)($_POST['cta_url'] ?? ''));
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                if ($title === '' || $description === '') {
                    throw new RuntimeException('Title and description are required.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE contact_channels
                        SET icon = :icon,
                            title = :title,
                            description = :description,
                            cta_label = :cta_label,
                            cta_url = :cta_url,
                            sort_order = :sort
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'icon' => $icon,
                        'title' => $title,
                        'description' => $description,
                        'cta_label' => $ctaLabel,
                        'cta_url' => $ctaUrl,
                        'sort' => $sortOrder,
                        'id' => $id,
                    ]);
                    $message = 'Channel updated.';
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO contact_channels (icon, title, description, cta_label, cta_url, sort_order)
                        VALUES (:icon, :title, :description, :cta_label, :cta_url, :sort)
                    ');
                    $stmt->execute([
                        'icon' => $icon,
                        'title' => $title,
                        'description' => $description,
                        'cta_label' => $ctaLabel,
                        'cta_url' => $ctaUrl,
                        'sort' => $sortOrder,
                    ]);
                    $message = 'Channel created.';
                }
                break;

            case 'highlight':
                $anchor = 'highlights';
                $id = (int)($_POST['id'] ?? 0);
                if ($action === 'delete') {
                    if ($id > 0) {
                        $stmt = $pdo->prepare('DELETE FROM contact_highlights WHERE id = :id');
                        $stmt->execute(['id' => $id]);
                        $message = 'Highlight removed.';
                    }
                    break;
                }
                $title = trim((string)($_POST['title'] ?? ''));
                $body = trim((string)($_POST['body'] ?? ''));
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                if ($title === '' || $body === '') {
                    throw new RuntimeException('Title and body are required.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE contact_highlights SET title = :title, body = :body, sort_order = :sort WHERE id = :id');
                    $stmt->execute([
                        'title' => $title,
                        'body' => $body,
                        'sort' => $sortOrder,
                        'id' => $id,
                    ]);
                    $message = 'Highlight updated.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO contact_highlights (title, body, sort_order) VALUES (:title, :body, :sort)');
                    $stmt->execute([
                        'title' => $title,
                        'body' => $body,
                        'sort' => $sortOrder,
                    ]);
                    $message = 'Highlight created.';
                }
                break;

            case 'faq':
                $anchor = 'faqs';
                $id = (int)($_POST['id'] ?? 0);
                if ($action === 'delete') {
                    if ($id > 0) {
                        $stmt = $pdo->prepare('DELETE FROM contact_faqs WHERE id = :id');
                        $stmt->execute(['id' => $id]);
                        $message = 'FAQ removed.';
                    }
                    break;
                }
                $question = trim((string)($_POST['question'] ?? ''));
                $answer = trim((string)($_POST['answer'] ?? ''));
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                if ($question === '' || $answer === '') {
                    throw new RuntimeException('Question and answer are required.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE contact_faqs SET question = :question, answer = :answer, sort_order = :sort WHERE id = :id');
                    $stmt->execute([
                        'question' => $question,
                        'answer' => $answer,
                        'sort' => $sortOrder,
                        'id' => $id,
                    ]);
                    $message = 'FAQ updated.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO contact_faqs (question, answer, sort_order) VALUES (:question, :answer, :sort)');
                    $stmt->execute([
                        'question' => $question,
                        'answer' => $answer,
                        'sort' => $sortOrder,
                    ]);
                    $message = 'FAQ created.';
                }
                break;

            default:
                throw new RuntimeException('Unknown content section.');
        }

        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => $message,
        ];
    } catch (Throwable $exception) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Unable to save changes: ' . $exception->getMessage(),
        ];
    }

    $redirectToPage($anchor);
}

$stats = $pdo->query('SELECT id, metric_value, metric_label, sort_order FROM contact_stats ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$channels = $pdo->query('SELECT id, icon, title, description, cta_label, cta_url, sort_order FROM contact_channels ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$highlights = $pdo->query('SELECT id, title, body, sort_order FROM contact_highlights ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$faqs = $pdo->query('SELECT id, question, answer, sort_order FROM contact_faqs ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
  <title>Contact Content | <?= rb_escape($brandName) ?></title>
  <link rel="icon" href="<?= rb_asset('../media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('../media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
  <style>
    .content-card {
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      padding: 24px;
      display: grid;
      gap: 18px;
    }
    .content-card h2 {
      margin: 0;
      font-size: 18px;
    }
    .content-card p {
      margin: 0;
      color: var(--admin-muted);
      font-size: 14px;
    }
    .collection {
      display: grid;
      gap: 14px;
    }
    .collection form {
      display: grid;
      gap: 12px;
      padding: 16px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      background: #f9fafb;
    }
    .field-grid {
      display: grid;
      gap: 12px;
    }
    .field-grid.double {
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }
    .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .field label {
      font-weight: 600;
      font-size: 13px;
    }
    .field input,
    .field textarea,
    .field select {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #cbd5f5;
      font-family: inherit;
      font-size: 14px;
      background: #fff;
    }
    .field textarea {
      resize: vertical;
      min-height: 90px;
    }
    .collection-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
    }
    .collection-empty {
      border-radius: 12px;
      padding: 18px;
      background: #f1f5f9;
      color: #475569;
      font-size: 14px;
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
          <h1 style="margin:0;font-size:22px;">Contact page content</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Manage the stats, channels, highlights, and FAQs rendered on the public contact page.</p>
        </div>
      </header>

      <main class="admin-content">
        <?php if ($flash): ?>
          <div class="notice <?= rb_escape($flash['type'] ?? 'info') ?>"><?= rb_escape($flash['message'] ?? '') ?></div>
        <?php endif; ?>

        <section class="content-card" id="stats">
          <div>
            <h2>Hero stats</h2>
            <p>Metrics shown beneath the hero on the contact page.</p>
          </div>
          <div class="collection">
            <?php if (!$stats): ?>
              <div class="collection-empty">No stats yet. Add the first metric below.</div>
            <?php endif; ?>
            <?php foreach ($stats as $stat): ?>
              <form method="post">
                <?= rb_csrf_input() ?>
                <input type="hidden" name="section" value="stat">
                <input type="hidden" name="id" value="<?= (int)$stat['id'] ?>">
                <div class="field-grid double">
                  <div class="field">
                    <label>Metric value</label>
                    <input name="metric_value" value="<?= rb_escape((string)$stat['metric_value']) ?>" required>
                  </div>
                  <div class="field">
                    <label>Label</label>
                    <input name="metric_label" value="<?= rb_escape((string)$stat['metric_label']) ?>" required>
                  </div>
                  <div class="field">
                    <label>Sort order</label>
                    <input name="sort_order" type="number" value="<?= (int)$stat['sort_order'] ?>" min="0">
                  </div>
                </div>
                <div class="collection-actions">
                  <button class="admin-btn secondary" name="action" value="delete" onclick="return confirm('Delete this stat?');">Delete</button>
                  <button class="admin-btn" type="submit" name="action" value="save">Save</button>
                </div>
              </form>
            <?php endforeach; ?>
            <form method="post">
              <?= rb_csrf_input() ?>
              <input type="hidden" name="section" value="stat">
              <div class="field-grid double">
                <div class="field">
                  <label>Metric value</label>
                  <input name="metric_value" placeholder="e.g. 24h" required>
                </div>
                <div class="field">
                  <label>Label</label>
                  <input name="metric_label" placeholder="Average response time" required>
                </div>
                <div class="field">
                  <label>Sort order</label>
                  <input name="sort_order" type="number" value="<?= count($stats) + 1 ?>" min="0">
                </div>
              </div>
              <div class="collection-actions">
                <button class="admin-btn" type="submit" name="action" value="save">Add stat</button>
              </div>
            </form>
          </div>
        </section>

        <section class="content-card" id="channels">
          <div>
            <h2>Support channels</h2>
            <p>Displayed in the “Connect with us” grid.</p>
          </div>
          <div class="collection">
            <?php if (!$channels): ?>
              <div class="collection-empty">No support channels added yet.</div>
            <?php endif; ?>
            <?php foreach ($channels as $channel): ?>
              <form method="post">
                <?= rb_csrf_input() ?>
                <input type="hidden" name="section" value="channel">
                <input type="hidden" name="id" value="<?= (int)$channel['id'] ?>">
                <div class="field-grid double">
                  <div class="field">
                    <label>Icon</label>
                    <select name="icon">
                      <?php
                      $icons = ['envelope' => 'Envelope', 'phone' => 'Phone', 'chat' => 'Chat', 'calendar' => 'Calendar'];
                      $currentIcon = (string)$channel['icon'];
                      foreach ($icons as $key => $label):
                        $selected = $currentIcon === $key ? ' selected' : '';
                      ?>
                        <option value="<?= rb_escape($key) ?>"<?= $selected ?>><?= rb_escape($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="field">
                    <label>Title</label>
                    <input name="title" value="<?= rb_escape((string)$channel['title']) ?>" required>
                  </div>
                  <div class="field">
                    <label>CTA label</label>
                    <input name="cta_label" value="<?= rb_escape((string)$channel['cta_label']) ?>">
                  </div>
                  <div class="field">
                    <label>CTA URL</label>
                    <input name="cta_url" value="<?= rb_escape((string)$channel['cta_url']) ?>" placeholder="mailto:, tel:, or https://">
                  </div>
                  <div class="field">
                    <label>Sort order</label>
                    <input name="sort_order" type="number" value="<?= (int)$channel['sort_order'] ?>" min="0">
                  </div>
                </div>
                <div class="field">
                  <label>Description</label>
                  <textarea name="description" required><?= rb_escape((string)$channel['description']) ?></textarea>
                </div>
                <div class="collection-actions">
                  <button class="admin-btn secondary" name="action" value="delete" onclick="return confirm('Delete this channel?');">Delete</button>
                  <button class="admin-btn" type="submit" name="action" value="save">Save</button>
                </div>
              </form>
            <?php endforeach; ?>
            <form method="post">
              <?= rb_csrf_input() ?>
              <input type="hidden" name="section" value="channel">
              <div class="field-grid double">
                <div class="field">
                  <label>Icon</label>
                  <select name="icon">
                    <option value="envelope">Envelope</option>
                    <option value="phone">Phone</option>
                    <option value="chat">Chat</option>
                    <option value="calendar">Calendar</option>
                  </select>
                </div>
                <div class="field">
                  <label>Title</label>
                  <input name="title" placeholder="e.g. Email support" required>
                </div>
                <div class="field">
                  <label>CTA label</label>
                  <input name="cta_label" placeholder="support@domain.com">
                </div>
                <div class="field">
                  <label>CTA URL</label>
                  <input name="cta_url" placeholder="mailto:support@domain.com">
                </div>
                <div class="field">
                  <label>Sort order</label>
                  <input name="sort_order" type="number" value="<?= count($channels) + 1 ?>" min="0">
                </div>
              </div>
              <div class="field">
                <label>Description</label>
                <textarea name="description" placeholder="Short description of how this channel helps" required></textarea>
              </div>
              <div class="collection-actions">
                <button class="admin-btn" type="submit" name="action" value="save">Add channel</button>
              </div>
            </form>
          </div>
        </section>

        <section class="content-card" id="highlights">
          <div>
            <h2>Highlights</h2>
            <p>Three cards that outline partnership opportunities.</p>
          </div>
          <div class="collection">
            <?php if (!$highlights): ?>
              <div class="collection-empty">No highlights configured.</div>
            <?php endif; ?>
            <?php foreach ($highlights as $highlight): ?>
              <form method="post">
                <?= rb_csrf_input() ?>
                <input type="hidden" name="section" value="highlight">
                <input type="hidden" name="id" value="<?= (int)$highlight['id'] ?>">
                <div class="field-grid double">
                  <div class="field">
                    <label>Title</label>
                    <input name="title" value="<?= rb_escape((string)$highlight['title']) ?>" required>
                  </div>
                  <div class="field">
                    <label>Sort order</label>
                    <input name="sort_order" type="number" value="<?= (int)$highlight['sort_order'] ?>" min="0">
                  </div>
                </div>
                <div class="field">
                  <label>Body</label>
                  <textarea name="body" required><?= rb_escape((string)$highlight['body']) ?></textarea>
                </div>
                <div class="collection-actions">
                  <button class="admin-btn secondary" name="action" value="delete" onclick="return confirm('Delete this highlight?');">Delete</button>
                  <button class="admin-btn" type="submit" name="action" value="save">Save</button>
                </div>
              </form>
            <?php endforeach; ?>
            <form method="post">
              <?= rb_csrf_input() ?>
              <input type="hidden" name="section" value="highlight">
              <div class="field-grid double">
                <div class="field">
                  <label>Title</label>
                  <input name="title" placeholder="e.g. Wholesale & Partnerships" required>
                </div>
                <div class="field">
                  <label>Sort order</label>
                  <input name="sort_order" type="number" value="<?= count($highlights) + 1 ?>" min="0">
                </div>
              </div>
              <div class="field">
                <label>Body</label>
                <textarea name="body" placeholder="Explain the offer in 1-2 sentences" required></textarea>
              </div>
              <div class="collection-actions">
                <button class="admin-btn" type="submit" name="action" value="save">Add highlight</button>
              </div>
            </form>
          </div>
        </section>

        <section class="content-card" id="faqs">
          <div>
            <h2>FAQs</h2>
            <p>Questions that appear in the expandable FAQ section.</p>
          </div>
          <div class="collection">
            <?php if (!$faqs): ?>
              <div class="collection-empty">No FAQs configured.</div>
            <?php endif; ?>
            <?php foreach ($faqs as $faq): ?>
              <form method="post">
                <?= rb_csrf_input() ?>
                <input type="hidden" name="section" value="faq">
                <input type="hidden" name="id" value="<?= (int)$faq['id'] ?>">
                <div class="field-grid double">
                  <div class="field">
                    <label>Question</label>
                    <input name="question" value="<?= rb_escape((string)$faq['question']) ?>" required>
                  </div>
                  <div class="field">
                    <label>Sort order</label>
                    <input name="sort_order" type="number" value="<?= (int)$faq['sort_order'] ?>" min="0">
                  </div>
                </div>
                <div class="field">
                  <label>Answer</label>
                  <textarea name="answer" required><?= rb_escape((string)$faq['answer']) ?></textarea>
                </div>
                <div class="collection-actions">
                  <button class="admin-btn secondary" name="action" value="delete" onclick="return confirm('Delete this FAQ?');">Delete</button>
                  <button class="admin-btn" type="submit" name="action" value="save">Save</button>
                </div>
              </form>
            <?php endforeach; ?>
            <form method="post">
              <?= rb_csrf_input() ?>
              <input type="hidden" name="section" value="faq">
              <div class="field-grid double">
                <div class="field">
                  <label>Question</label>
                  <input name="question" placeholder="e.g. How quickly will I hear back?" required>
                </div>
                <div class="field">
                  <label>Sort order</label>
                  <input name="sort_order" type="number" value="<?= count($faqs) + 1 ?>" min="0">
                </div>
              </div>
              <div class="field">
                <label>Answer</label>
                <textarea name="answer" placeholder="Provide a concise answer (HTML allowed)." required></textarea>
              </div>
              <div class="collection-actions">
                <button class="admin-btn" type="submit" name="action" value="save">Add FAQ</button>
              </div>
            </form>
          </div>
        </section>

      </main>
    </div>
  </div>
</body>
</html>
