<?php
declare(strict_types=1);

$adminCurrent = 'messages';
require __DIR__ . '/includes/admin_app.php';

$supportEmail = trim((string)($settings['support.email'] ?? 'support@rudrablessings.com'));

$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$validStatuses = ['', 'all', 'pending', 'queued', 'failed', 'replied', 'reply_failed', 'reply_queued'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

$messages = rb_admin_contact_messages($pdo, [
    'status' => $statusFilter === '' || $statusFilter === 'all' ? null : $statusFilter,
    'limit' => 100,
]);

$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($messageId <= 0 && $messages) {
    $messageId = (int)$messages[0]['id'];
}

$currentMessage = $messageId > 0 ? rb_admin_contact_message($pdo, $messageId) : null;
$replyForm = [
    'subject' => $currentMessage && !empty($currentMessage['subject'])
        ? 'Re: ' . $currentMessage['subject']
        : 'Support from ' . $brandName,
    'body' => '',
];
$replyErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rb_csrf_validate_request()) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'Your session expired. Please reload and try again.',
        ];
        header('Location: messages.php' . ($messageId ? '?id=' . $messageId : ''));
        exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'send_reply') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        $currentMessage = $messageId > 0 ? rb_admin_contact_message($pdo, $messageId) : null;
        if (!$currentMessage) {
            $_SESSION['admin_flash'] = [
                'type' => 'error',
                'message' => 'Message not found. It may have been removed.',
            ];
            header('Location: messages.php');
            exit;
        }

        $replyForm['subject'] = trim((string)($_POST['reply_subject'] ?? $replyForm['subject']));
        $replyForm['body'] = trim((string)($_POST['reply_body'] ?? ''));

        if ($replyForm['subject'] === '') {
            $replyErrors['reply_subject'] = 'Subject is required.';
        }
        if ($replyForm['body'] === '') {
            $replyErrors['reply_body'] = 'Write a message before sending.';
        }

        if (!$replyErrors) {
            $replyToEmail = $supportEmail !== '' ? $supportEmail : $currentMessage['email'];
            $htmlBody = '<p style="margin:0 0 16px;">' . nl2br(rb_escape($replyForm['body'])) . '</p>';
            $htmlBody .= '<p style="margin:24px 0 0;color:#555;">— ' . rb_escape($brandName) . ' Support</p>';
            $textBody = $replyForm['body'] . "\n\n— " . $brandName . ' Support';

            $sendResult = rb_mail_send([
                'subject' => $replyForm['subject'],
                'html' => $htmlBody,
                'text' => $textBody,
                'to' => [
                    [$currentMessage['email'], $currentMessage['name']],
                ],
                'reply_to_email' => $replyToEmail,
                'reply_to_name' => $brandName . ' Support',
            ]);

            $mailStatus = 'replied';
            if (!$sendResult['ok']) {
                $mailStatus = 'reply_failed';
            } elseif (($sendResult['status'] ?? '') === 'queued') {
                $mailStatus = 'reply_queued';
            }

            $record = rb_admin_record_contact_reply($pdo, [
                'message_id' => $messageId,
                'admin_id' => (int)$currentUser['id'],
                'subject' => $replyForm['subject'],
                'body' => $replyForm['body'],
                'status' => $mailStatus,
                'error' => $sendResult['ok'] ? null : (string)($sendResult['error'] ?? 'Unknown error'),
            ]);

            rb_admin_touch_contact_message(
                $pdo,
                $messageId,
                $mailStatus,
                $sendResult['ok'] ? null : (string)($sendResult['error'] ?? 'Email failed')
            );

            $_SESSION['admin_flash'] = [
                'type' => $sendResult['ok'] ? 'success' : 'error',
                'message' => $sendResult['ok']
                    ? 'Reply sent to ' . $currentMessage['email'] . '.'
                    : ('Failed to send email: ' . ($sendResult['error'] ?? 'Unknown error. The reply has been saved.')),
            ];

            header('Location: messages.php?id=' . $messageId);
            exit;
        }
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
  <title>Customer Messages | <?= rb_escape($brandName) ?></title>
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
  <style>
    .messages-layout {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 20px;
    }
    .message-list {
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      overflow: hidden;
      max-height: calc(100vh - 220px);
      display: flex;
      flex-direction: column;
    }
    .message-list header {
      padding: 16px;
      border-bottom: 1px solid #f1f5f9;
    }
    .message-items {
      overflow-y: auto;
      flex: 1;
    }
    .message-items a {
      display: block;
      padding: 14px 16px;
      border-bottom: 1px solid #f1f5f9;
      text-decoration: none;
      color: inherit;
      transition: background .15s ease;
    }
    .message-items a:hover {
      background: #f9fafb;
    }
    .message-items a.active {
      background: #fef3c7;
    }
    .message-meta {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      color: var(--admin-muted);
    }
    .message-detail {
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    .message-body {
      white-space: pre-wrap;
      background: #f9fafb;
      border-radius: 12px;
      padding: 18px;
      line-height: 1.6;
      border: 1px solid #e5e7eb;
    }
    .reply-form textarea {
      min-height: 180px;
      resize: vertical;
    }
    .reply-form input,
    .reply-form textarea {
      width: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #d1d5db;
      font-size: 14px;
      font-family: inherit;
    }
    .reply-form label {
      font-weight: 600;
      margin-bottom: 6px;
      display: block;
    }
    .reply-form .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .reply-form .error {
      color: #b91c1c;
      font-size: 12px;
    }
    .replies {
      display: grid;
      gap: 16px;
    }
    .reply-card {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 16px;
      background: #f8fafc;
    }
    .reply-card .reply-meta {
      font-size: 12px;
      color: var(--admin-muted);
      margin-bottom: 8px;
      display: flex;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    @media (max-width: 960px) {
      .messages-layout {
        grid-template-columns: 1fr;
      }
      .message-list {
        max-height: none;
      }
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
        <a class="admin-btn secondary" href="../index.php">View Storefront</a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <h1 style="margin:0;font-size:22px;">Customer Messages</h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Review enquiries and respond directly via email.</p>
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
        <?php if ($flash): ?>
          <div class="notice <?= rb_escape($flash['type'] ?? 'info') ?>"><?= rb_escape($flash['message'] ?? '') ?></div>
        <?php endif; ?>
        <div class="messages-layout">
          <section class="message-list">
            <header>
              <form method="get" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="id" value="<?= $messageId > 0 ? (int)$messageId : '' ?>">
                <select name="status" style="flex:1;padding:8px 10px;border-radius:8px;border:1px solid #d1d5db;">
                  <option value="all"<?= $statusFilter === 'all' || $statusFilter === '' ? ' selected' : '' ?>>All</option>
                  <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Pending</option>
                  <option value="queued"<?= $statusFilter === 'queued' ? ' selected' : '' ?>>Queued</option>
                  <option value="failed"<?= $statusFilter === 'failed' ? ' selected' : '' ?>>Failed</option>
                  <option value="replied"<?= $statusFilter === 'replied' ? ' selected' : '' ?>>Replied</option>
                  <option value="reply_failed"<?= $statusFilter === 'reply_failed' ? ' selected' : '' ?>>Reply failed</option>
                  <option value="reply_queued"<?= $statusFilter === 'reply_queued' ? ' selected' : '' ?>>Reply queued</option>
                </select>
                <button type="submit" class="admin-btn">Filter</button>
              </form>
            </header>
            <div class="message-items">
              <?php if ($messages): ?>
                <?php foreach ($messages as $message): ?>
                  <?php
                    $active = (int)$message['id'] === $messageId;
                    $label = $message['subject'] !== '' ? $message['subject'] : '(No subject)';
                    $statusLabel = ucfirst(str_replace('_', ' ', $message['mail_status']));
                  ?>
                  <a href="messages.php?id=<?= (int)$message['id'] ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>"<?= $active ? ' class="active"' : '' ?>>
                    <strong><?= rb_escape($message['name']) ?></strong><br>
                    <span class="muted" style="font-size:13px;"><?= rb_escape($label) ?></span>
                    <div class="message-meta">
                      <span><?= rb_escape(date('M j, g:ia', strtotime($message['created_at']))) ?></span>
                      <span><?= rb_escape($statusLabel) ?></span>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="empty-state" style="padding:24px;">No messages found for the selected filter.</div>
              <?php endif; ?>
            </div>
          </section>

          <section class="message-detail">
            <?php if (!$currentMessage): ?>
              <div class="empty-state" style="padding:40px 0;">Select a message from the left to view details and reply.</div>
            <?php else: ?>
              <article>
                <h2 style="margin:0 0 12px;font-size:20px;"><?= rb_escape($currentMessage['subject'] ?: 'No subject') ?></h2>
                <p class="muted" style="margin:0 0 12px;">
                  From <strong><?= rb_escape($currentMessage['name']) ?></strong>
                  &middot; <a href="mailto:<?= rb_escape($currentMessage['email']) ?>"><?= rb_escape($currentMessage['email']) ?></a><br>
                  Received <?= rb_escape(date('M j, Y g:ia', strtotime($currentMessage['created_at']))) ?>
                  <?php if (!empty($currentMessage['responded_at'])): ?>
                    &middot; Replied <?= rb_escape(date('M j, Y g:ia', strtotime($currentMessage['responded_at']))) ?>
                  <?php endif; ?>
                </p>
                <div class="message-body"><?= nl2br(rb_escape($currentMessage['message'])) ?></div>
              </article>

              <section class="reply-form">
                <h3 style="margin:0 0 12px;">Reply via email</h3>
                <form method="post" style="display:grid;gap:16px;">
                  <?= rb_csrf_input() ?>
                  <input type="hidden" name="action" value="send_reply">
                  <input type="hidden" name="message_id" value="<?= (int)$currentMessage['id'] ?>">
                  <div class="field">
                    <label for="reply_subject">Subject</label>
                    <input id="reply_subject" name="reply_subject" value="<?= rb_escape($replyForm['subject']) ?>">
                    <?php if (isset($replyErrors['reply_subject'])): ?>
                      <div class="error"><?= rb_escape($replyErrors['reply_subject']) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="field">
                    <label for="reply_body">Message</label>
                    <textarea id="reply_body" name="reply_body"><?= rb_escape($replyForm['body']) ?></textarea>
                    <?php if (isset($replyErrors['reply_body'])): ?>
                      <div class="error"><?= rb_escape($replyErrors['reply_body']) ?></div>
                    <?php endif; ?>
                  </div>
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                      <button type="submit" class="admin-btn">Send email</button>
                    <span class="muted" style="font-size:12px;">We send from your configured SMTP address. Replies go to <?= rb_escape($supportEmail !== '' ? $supportEmail : $currentMessage['email']) ?>.</span>
                  </div>
                </form>
              </section>

              <section>
                <h3 style="margin:0 0 12px;">History</h3>
                <?php if (!empty($currentMessage['replies'])): ?>
                  <div class="replies">
                    <?php foreach ($currentMessage['replies'] as $reply): ?>
                      <div class="reply-card">
                        <div class="reply-meta">
                          <span><?= rb_escape(date('M j, Y g:ia', strtotime($reply['created_at']))) ?></span>
                          <span>
                            <?= rb_escape(ucfirst(str_replace('_', ' ', $reply['mail_status']))) ?>
                            &middot;
                            <?= rb_escape(trim(($reply['first_name'] ?? '') . ' ' . ($reply['last_name'] ?? ''))) ?>
                          </span>
                        </div>
                        <div><?= nl2br(rb_escape($reply['body'])) ?></div>
                        <?php if (!empty($reply['mail_error'])): ?>
                          <div class="error" style="margin-top:8px;">Error: <?= rb_escape($reply['mail_error']) ?></div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="empty-state">No replies sent yet.</div>
                <?php endif; ?>
              </section>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
