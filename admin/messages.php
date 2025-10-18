<?php
declare(strict_types=1);

$adminCurrent = 'messages';
require __DIR__ . '/includes/admin_app.php';

$supportEmail = trim((string)($settings['support.email'] ?? 'support@rudrablessings.com'));

$composeMode = isset($_GET['compose']) && $_GET['compose'] === '1';
$composeForm = [
    'name' => '',
    'email' => '',
    'subject' => 'Support from ' . $brandName,
    'body' => '',
];
$composeErrors = [];

$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$validStatuses = ['', 'all', 'pending', 'queued', 'failed', 'replied', 'reply_failed', 'reply_queued', 'outbound_sent', 'outbound_failed', 'outbound_queued'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

$messages = rb_admin_contact_messages($pdo, [
    'status' => $statusFilter === '' || $statusFilter === 'all' ? null : $statusFilter,
    'limit' => 100,
]);

$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($composeMode) {
    $messageId = 0;
}
if ($messageId <= 0 && $messages && !$composeMode) {
    $messageId = (int)$messages[0]['id'];
}

$currentMessage = (!$composeMode && $messageId > 0) ? rb_admin_contact_message($pdo, $messageId) : null;
$statusLabelMap = [
    'pending' => 'Pending',
    'queued' => 'Queued',
    'failed' => 'Failed',
    'replied' => 'Replied',
    'reply_failed' => 'Reply failed',
    'reply_queued' => 'Reply queued',
    'outbound_sent' => 'Outbound sent',
    'outbound_failed' => 'Outbound failed',
    'outbound_queued' => 'Outbound queued',
];
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
    if ($action === 'compose_email') {
        $composeMode = true;
        $messageId = 0;
        $currentMessage = null;

        $composeForm['name'] = trim((string)($_POST['compose_name'] ?? ''));
        $composeForm['email'] = trim((string)($_POST['compose_email'] ?? ''));
        $composeForm['subject'] = trim((string)($_POST['compose_subject'] ?? $composeForm['subject']));
        $composeForm['body'] = trim((string)($_POST['compose_body'] ?? ''));

        if ($composeForm['email'] === '' || !filter_var($composeForm['email'], FILTER_VALIDATE_EMAIL)) {
            $composeErrors['email'] = 'Enter a valid email address.';
        }
        if ($composeForm['subject'] === '') {
            $composeErrors['subject'] = 'Subject is required.';
        }
        if ($composeForm['body'] === '') {
            $composeErrors['body'] = 'Write a message before sending.';
        }

        if (!$composeErrors) {
            $recipientName = $composeForm['name'];
            if ($recipientName === '' && $composeForm['email'] !== '') {
                $recipientName = strstr($composeForm['email'], '@', true) ?: $composeForm['email'];
            }
            if ($recipientName === '') {
                $recipientName = 'Recipient';
            }

            $replyToEmail = $supportEmail !== '' ? $supportEmail : null;
            $htmlBody = '<p style="margin:0 0 16px;">' . nl2br(rb_escape($composeForm['body'])) . '</p>';
            $htmlBody .= '<p style="margin:24px 0 0;color:#555;">&mdash; ' . rb_escape($brandName) . ' Support</p>';
            $textBody = $composeForm['body'] . "\n\n-- " . $brandName . ' Support';

            $sendResult = rb_mail_send([
                'subject' => $composeForm['subject'],
                'html' => $htmlBody,
                'text' => $textBody,
                'to' => [
                    [$composeForm['email'], $recipientName],
                ],
                'reply_to_email' => $replyToEmail,
                'reply_to_name' => $brandName . ' Support',
            ]);

            $mailStatus = 'outbound_sent';
            if (!$sendResult['ok']) {
                $mailStatus = 'outbound_failed';
            } elseif (($sendResult['status'] ?? '') === 'queued') {
                $mailStatus = 'outbound_queued';
            }
            $mailError = $sendResult['ok'] ? null : (string)($sendResult['error'] ?? 'Unknown error');
            $respondedAt = in_array($mailStatus, ['outbound_sent', 'outbound_queued'], true) ? date('Y-m-d H:i:s') : null;

            try {
                $pdo->beginTransaction();

                $insertMessage = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message, reply_to, ip_address, mail_status, mail_error, responded_at) VALUES (:name, :email, :subject, :message, :reply_to, :ip, :status, :error, :responded_at)');
                $insertMessage->execute([
                    'name' => $recipientName,
                    'email' => $composeForm['email'],
                    'subject' => $composeForm['subject'],
                    'message' => $composeForm['body'],
                    'reply_to' => $replyToEmail,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'status' => $mailStatus,
                    'error' => $mailError,
                    'responded_at' => $respondedAt,
                ]);
                $newMessageId = (int)$pdo->lastInsertId();

                $insertReply = $pdo->prepare('INSERT INTO contact_message_replies (contact_message_id, admin_id, subject, body, mail_status, mail_error) VALUES (:message_id, :admin_id, :subject, :body, :status, :error)');
                $insertReply->execute([
                    'message_id' => $newMessageId,
                    'admin_id' => (int)$currentUser['id'],
                    'subject' => $composeForm['subject'],
                    'body' => $composeForm['body'],
                    'status' => $mailStatus,
                    'error' => $mailError,
                ]);

                $pdo->commit();

                $_SESSION['admin_flash'] = [
                    'type' => $sendResult['ok'] ? 'success' : 'error',
                    'message' => $sendResult['ok']
                        ? 'Email sent to ' . $composeForm['email'] . '.'
                        : ('Failed to send email: ' . ($mailError ?? 'Unknown error. The email has been saved.')),
                ];

                header('Location: messages.php?id=' . $newMessageId);
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin/messages] compose outbound email failed: ' . $exception->getMessage());
                $composeErrors['general'] = 'We could not save the email. Please try again.';
            }
        }
    } elseif ($action === 'send_reply') {
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

$composeLink = 'messages.php?compose=1';
$cancelComposeLink = 'messages.php';
if ($statusFilter !== '' && $statusFilter !== 'all') {
    $composeLink .= '&status=' . urlencode($statusFilter);
    $cancelComposeLink .= '?status=' . urlencode($statusFilter);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Messages | <?= rb_escape($brandName) ?></title>
  <link rel="icon" href="<?= rb_asset('../media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('../media/logo.png') ?>">
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
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
    }
    .message-list header form {
      display: flex;
      gap: 8px;
      align-items: center;
      flex: 1;
      flex-wrap: wrap;
    }
    .message-list header form select {
      flex: 1;
      min-width: 160px;
      padding: 8px 10px;
      border-radius: 8px;
      border: 1px solid #d1d5db;
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
              <form method="get">
                <input type="hidden" name="id" value="<?= $messageId > 0 ? (int)$messageId : '' ?>">
                <select name="status">
                  <option value="all"<?= $statusFilter === 'all' || $statusFilter === '' ? ' selected' : '' ?>>All</option>
                  <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Pending</option>
                  <option value="queued"<?= $statusFilter === 'queued' ? ' selected' : '' ?>>Queued</option>
                  <option value="failed"<?= $statusFilter === 'failed' ? ' selected' : '' ?>>Failed</option>
                  <option value="replied"<?= $statusFilter === 'replied' ? ' selected' : '' ?>>Replied</option>
                  <option value="reply_failed"<?= $statusFilter === 'reply_failed' ? ' selected' : '' ?>>Reply failed</option>
                  <option value="reply_queued"<?= $statusFilter === 'reply_queued' ? ' selected' : '' ?>>Reply queued</option>
                  <option value="outbound_sent"<?= $statusFilter === 'outbound_sent' ? ' selected' : '' ?>>Outbound sent</option>
                  <option value="outbound_failed"<?= $statusFilter === 'outbound_failed' ? ' selected' : '' ?>>Outbound failed</option>
                  <option value="outbound_queued"<?= $statusFilter === 'outbound_queued' ? ' selected' : '' ?>>Outbound queued</option>
                </select>
                <button type="submit" class="admin-btn">Filter</button>
              </form>
              <a class="admin-btn<?= $composeMode ? ' secondary' : '' ?>" href="<?= rb_escape($composeLink) ?>">Compose email</a>
            </header>
            <div class="message-items">
              <?php if ($messages): ?>
                <?php foreach ($messages as $message): ?>
                  <?php
                    $active = (int)$message['id'] === $messageId;
                    $label = $message['subject'] !== '' ? $message['subject'] : '(No subject)';
                    $statusLabel = $statusLabelMap[$message['mail_status']] ?? ucfirst(str_replace('_', ' ', $message['mail_status']));
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
            <?php if ($composeMode): ?>
              <section class="reply-form">
                <h3 style="margin:0 0 12px;">Compose email</h3>
                <?php if (isset($composeErrors['general'])): ?>
                  <div class="error"><?= rb_escape($composeErrors['general']) ?></div>
                <?php endif; ?>
                <form method="post" style="display:grid;gap:16px;">
                  <?= rb_csrf_input() ?>
                  <input type="hidden" name="action" value="compose_email">
                  <div class="field">
                    <label for="compose_name">Recipient name (optional)</label>
                    <input id="compose_name" name="compose_name" value="<?= rb_escape($composeForm['name']) ?>">
                  </div>
                  <div class="field">
                    <label for="compose_email">Recipient email</label>
                    <input id="compose_email" name="compose_email" type="email" value="<?= rb_escape($composeForm['email']) ?>" required>
                    <?php if (isset($composeErrors['email'])): ?>
                      <div class="error"><?= rb_escape($composeErrors['email']) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="field">
                    <label for="compose_subject">Subject</label>
                    <input id="compose_subject" name="compose_subject" value="<?= rb_escape($composeForm['subject']) ?>">
                    <?php if (isset($composeErrors['subject'])): ?>
                      <div class="error"><?= rb_escape($composeErrors['subject']) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="field">
                    <label for="compose_body">Message</label>
                    <textarea id="compose_body" name="compose_body"><?= rb_escape($composeForm['body']) ?></textarea>
                    <?php if (isset($composeErrors['body'])): ?>
                      <div class="error"><?= rb_escape($composeErrors['body']) ?></div>
                    <?php endif; ?>
                  </div>
                  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="admin-btn">Send email</button>
                    <a class="admin-btn secondary" href="<?= rb_escape($cancelComposeLink) ?>">Cancel</a>
                    <?php $composeReplyInfo = $supportEmail !== '' ? $supportEmail : 'the address configured in SMTP settings'; ?>
                    <span class="muted" style="font-size:12px;">We send from your configured SMTP address. Replies go to <?= rb_escape($composeReplyInfo) ?>.</span>
                  </div>
                </form>
              </section>
            <?php elseif (!$currentMessage): ?>
              <div class="empty-state" style="padding:40px 0;">Select a message from the left to view details and reply.</div>
            <?php else: ?>
              <?php
                $currentStatusLabel = $statusLabelMap[$currentMessage['mail_status']] ?? ucfirst(str_replace('_', ' ', $currentMessage['mail_status']));
                $isOutbound = str_starts_with((string)$currentMessage['mail_status'], 'outbound_');
              ?>
              <article>
                <h2 style="margin:0 0 12px;font-size:20px;"><?= rb_escape($currentMessage['subject'] ?: 'No subject') ?></h2>
                <p class="muted" style="margin:0 0 12px;">
                  <?php if ($isOutbound): ?>
                    Sent to <strong><?= rb_escape($currentMessage['name']) ?></strong>
                    &middot; <a href="mailto:<?= rb_escape($currentMessage['email']) ?>"><?= rb_escape($currentMessage['email']) ?></a><br>
                    Sent <?= rb_escape(date('M j, Y g:ia', strtotime($currentMessage['created_at']))) ?>
                    <?php if (!empty($currentMessage['responded_at'])): ?>
                      <br>Confirmed <?= rb_escape(date('M j, Y g:ia', strtotime($currentMessage['responded_at']))) ?>
                    <?php endif; ?>
                  <?php else: ?>
                    From <strong><?= rb_escape($currentMessage['name']) ?></strong>
                    &middot; <a href="mailto:<?= rb_escape($currentMessage['email']) ?>"><?= rb_escape($currentMessage['email']) ?></a><br>
                    Received <?= rb_escape(date('M j, Y g:ia', strtotime($currentMessage['created_at']))) ?>
                    <?php if (!empty($currentMessage['responded_at'])): ?>
                      <br>Replied <?= rb_escape(date('M j, Y g:ia', strtotime($currentMessage['responded_at']))) ?>
                    <?php endif; ?>
                  <?php endif; ?>
                  <br>Status: <?= rb_escape($currentStatusLabel) ?>
                  <?php if (!empty($currentMessage['mail_error'])): ?>
                    · <span style="color:#b91c1c;">Error: <?= rb_escape($currentMessage['mail_error']) ?></span>
                  <?php endif; ?>
                </p>
                <div class="message-body"><?= nl2br(rb_escape($currentMessage['message'])) ?></div>
              </article>

              <section class="reply-form">
                <h3 style="margin:0 0 12px;"><?= $isOutbound ? 'Send follow-up email' : 'Reply via email' ?></h3>
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
                    <?php $replyInfoEmail = $supportEmail !== '' ? $supportEmail : $currentMessage['email']; ?>
                    <span class="muted" style="font-size:12px;">We send from your configured SMTP address. Replies go to <?= rb_escape($replyInfoEmail) ?>.</span>
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
                            <?= rb_escape($statusLabelMap[$reply['mail_status']] ?? ucfirst(str_replace('_', ' ', $reply['mail_status']))) ?>
                            <?php $replyAuthor = trim(($reply['first_name'] ?? '') . ' ' . ($reply['last_name'] ?? '')); ?>
                            <?php if ($replyAuthor !== ''): ?>
                              &middot; <?= rb_escape($replyAuthor) ?>
                            <?php endif; ?>
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
