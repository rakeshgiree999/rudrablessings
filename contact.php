<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$currentPath = 'contact.php';
$contactBlocks = rb_blocks($pdo, 'contact');
$contactHero = $contactBlocks['hero'] ?? [];
$formNoteHtml = (string)($contactBlocks['form-note']['body'] ?? '');

$contactTitle = trim((string)($contactHero['title'] ?? ''));
if ($contactTitle === '') {
    $contactTitle = trim(sprintf('%s Contact', (string)$brandName));
}
$contactSubtitle = trim((string)($contactHero['subtitle'] ?? ''));
$metaDescription = '';
if ($contactSubtitle !== '') {
    $metaDescription = $contactSubtitle;
} elseif (!empty($settings['seo.meta_description'])) {
    $metaDescription = trim((string)$settings['seo.meta_description']);
} elseif (!empty($brandTagline)) {
    $metaDescription = (string)$brandTagline;
} else {
    $metaDescription = trim(sprintf('Connect with %s', (string)$brandName));
}

// All human-facing copy now comes from seeded schema tables.
$contactAddress = trim((string)($settings['support.address'] ?? ''));
$contactEmail = trim((string)($settings['support.email'] ?? ''));
$contactPhone = trim((string)($settings['support.phone'] ?? ''));
$contactHours = trim((string)($settings['support.hours'] ?? ''));
$mapEmbedUrl = trim((string)($settings['contact.map_embed'] ?? ''));
$mapDirectionsUrl = trim((string)($settings['contact.map_link'] ?? ''));
$mapImageRelative = 'media/maps/map-vit.png';
$mapImagePath = __DIR__ . '/' . $mapImageRelative;
$mapImageAvailable = is_file($mapImagePath);
if (($mapDirectionsUrl !== '' || $mapEmbedUrl !== '') && !$mapImageAvailable) {
    error_log('[contact] Map preview image missing at ' . $mapImagePath);
}
$mapClickTarget = '';
if ($mapDirectionsUrl !== '') {
    $mapClickTarget = $mapDirectionsUrl;
} elseif ($contactAddress !== '') {
    $mapClickTarget = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($contactAddress);
}

$socialLinks = [];
$registeredSocial = [
    ['key' => 'social.instagram', 'label' => 'Instagram', 'icon' => 'media/instagram.png'],
    ['key' => 'social.facebook', 'label' => 'Facebook', 'icon' => 'media/facebook.png'],
    ['key' => 'social.youtube', 'label' => 'YouTube', 'icon' => 'media/youtube.png'],
];
foreach ($registeredSocial as $social) {
    $url = trim((string)($settings[$social['key']] ?? ''));
    if ($url === '') {
        continue;
    }
    $socialLinks[] = [
        'label' => $social['label'],
        'url' => $url,
        'icon' => $social['icon'],
    ];
}

// Structured sections load from dedicated tables seeded in database/schema.sql.
$statsStmt = $pdo->query('SELECT metric_value AS value, metric_label AS label FROM contact_stats ORDER BY sort_order ASC, id ASC');
$contactStats = $statsStmt ? $statsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$channelsStmt = $pdo->query('SELECT icon, title, description, cta_label, cta_url FROM contact_channels ORDER BY sort_order ASC, id ASC');
$supportChannels = $channelsStmt ? $channelsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$highlightsStmt = $pdo->query('SELECT title, body FROM contact_highlights ORDER BY sort_order ASC, id ASC');
$contactHighlights = $highlightsStmt ? $highlightsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$faqStmt = $pdo->query('SELECT question, answer FROM contact_faqs ORDER BY sort_order ASC, id ASC');
$faqEntries = $faqStmt ? $faqStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$measureLength = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
};

$contactFormData = [
    'name' => '',
    'email' => '',
    'subject' => '',
    'message' => '',
];
$contactErrors = [];
$contactSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactFormData = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'subject' => trim((string)($_POST['subject'] ?? '')),
        'message' => trim((string)($_POST['message'] ?? '')),
    ];
    $honeypot = trim((string)($_POST['website'] ?? ''));

    if (!rb_csrf_validate_request()) {
        $contactErrors['form'] = 'Your session expired. Please refresh and try again.';
    } else {
        if ($honeypot !== '') {
            $contactErrors['form'] = 'Something went wrong. Please resubmit the form.';
        }

        if ($contactFormData['name'] === '' || $measureLength($contactFormData['name']) < 2) {
            $contactErrors['name'] = 'Please share your name so we know how to greet you.';
        }

        if ($contactFormData['email'] === '' || !filter_var($contactFormData['email'], FILTER_VALIDATE_EMAIL)) {
            $contactErrors['email'] = 'A valid email helps us return your message.';
        }

        if ($contactFormData['message'] === '' || $measureLength($contactFormData['message']) < 12) {
            $contactErrors['message'] = 'Tell us a little more so we can prepare a helpful response.';
        }

        if ($measureLength($contactFormData['message']) > 5000) {
            $contactErrors['message'] = 'Messages must be under 5,000 characters.';
        }

        if (empty($contactErrors)) {
            $subjectLine = $contactFormData['subject'] !== ''
                ? $contactFormData['subject']
                : sprintf('New enquiry from %s', $contactFormData['name']);

            $htmlBody = '<h2 style="margin-top:0;margin-bottom:16px;font-family:Arial,sans-serif;">New contact enquiry</h2>';
            $htmlBody .= '<p style="font-family:Arial,sans-serif;margin:0 0 12px;">You have received a new message from the RudraBlessings contact form.</p>';
            $htmlBody .= '<table cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,sans-serif;border-collapse:collapse;width:100%;max-width:600px;">';
            $htmlBody .= sprintf('<tr><td style="padding:8px 12px;font-weight:bold;background:#f9f5ef;">Name</td><td style="padding:8px 12px;background:#fdfaf6;">%s</td></tr>', rb_escape($contactFormData['name']));
            $htmlBody .= sprintf('<tr><td style="padding:8px 12px;font-weight:bold;background:#f9f5ef;">Email</td><td style="padding:8px 12px;background:#fdfaf6;"><a href="mailto:%1$s">%1$s</a></td></tr>', rb_escape($contactFormData['email']));
            if ($contactFormData['subject'] !== '') {
                $htmlBody .= sprintf('<tr><td style="padding:8px 12px;font-weight:bold;background:#f9f5ef;">Subject</td><td style="padding:8px 12px;background:#fdfaf6;">%s</td></tr>', rb_escape($contactFormData['subject']));
            }
            $htmlBody .= '</table>';
            $htmlBody .= sprintf(
                '<div style="margin-top:18px;padding:16px 12px;background:#fffaf3;border:1px solid #edd9c4;border-radius:8px;font-family:Arial,sans-serif;line-height:1.6;">%s</div>',
                nl2br(rb_escape($contactFormData['message']))
            );

            $textBody = "New contact enquiry\n"
                . "----------------------\n"
                . 'Name: ' . $contactFormData['name'] . "\n"
                . 'Email: ' . $contactFormData['email'] . "\n";
            if ($contactFormData['subject'] !== '') {
                $textBody .= 'Subject: ' . $contactFormData['subject'] . "\n";
            }
            $textBody .= "\nMessage:\n" . $contactFormData['message'] . "\n";

            $sendResult = rb_mail_send([
                'subject' => $subjectLine,
                'html' => $htmlBody,
                'text' => $textBody,
                'reply_to_email' => $contactFormData['email'],
                'reply_to_name' => $contactFormData['name'],
                'to' => [
                    [$contactEmail, $brandName . ' Support'],
                ],
            ]);

            $mailStatus = (string)($sendResult['status'] ?? ($sendResult['ok'] ? 'sent' : 'failed'));
            if ($mailStatus === '') {
                $mailStatus = $sendResult['ok'] ? 'sent' : 'failed';
            }
            $mailError = (!$sendResult['ok'] && isset($sendResult['error'])) ? (string)$sendResult['error'] : null;
            $ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

            try {
                $storeStmt = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message, reply_to, ip_address, mail_status, mail_error, created_at) VALUES (:name, :email, :subject, :message, :reply_to, :ip_address, :mail_status, :mail_error, NOW())');
                $storeStmt->execute([
                    'name' => $contactFormData['name'],
                    'email' => $contactFormData['email'],
                    'subject' => $contactFormData['subject'] !== '' ? $contactFormData['subject'] : null,
                    'message' => $contactFormData['message'],
                    'reply_to' => $contactFormData['email'],
                    'ip_address' => $ipAddress !== '' ? $ipAddress : null,
                    'mail_status' => $mailStatus,
                    'mail_error' => $mailError,
                ]);
            } catch (\Throwable $storeException) {
                error_log('[contact] failed to store message: ' . $storeException->getMessage());
            }

            if ($sendResult['ok']) {
                $contactSuccess = 'Thank you for reaching out. We\'ve received your message and will reply shortly.';
                $contactFormData = [
                    'name' => '',
                    'email' => '',
                    'subject' => '',
                    'message' => '',
                ];
            } else {
                $contactErrors['form'] = 'We couldn\'t send your message right now. Please try again in a moment or email us directly at ' . $contactEmail . '.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= rb_escape($contactTitle) ?> | <?= rb_escape($brandName) ?></title>
  <meta name="description" content="<?= rb_escape($metaDescription) ?>">
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/pages.css') ?>">
  <style>
    .contact-hero {
      background: linear-gradient(135deg, #f9efe3 0%, #fbe7cf 100%);
      padding: 72px 0;
      border-radius: 32px;
      margin: 24px auto 48px;
      width: min(1180px, 92vw);
      position: relative;
      overflow: hidden;
    }

    .contact-hero::before,
    .contact-hero::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.4);
      filter: blur(120px);
      z-index: 0;
    }

    .contact-hero::before {
      width: 300px;
      height: 300px;
      top: -120px;
      left: -60px;
    }

    .contact-hero::after {
      width: 240px;
      height: 240px;
      bottom: -100px;
      right: 0;
    }

    .contact-hero .hero-inner {
      position: relative;
      z-index: 1;
      display: grid;
      align-items: center;
      gap: 40px;
      grid-template-columns: minmax(260px, 1fr) minmax(220px, 0.9fr);
    }

    .contact-hero h1 {
      margin: 0 0 12px;
      font-size: clamp(2.1rem, 4vw, 3rem);
      color: #432212;
    }

    .contact-hero p {
      margin: 0;
      color: #5d4633;
      line-height: 1.65;
      max-width: 540px;
    }

    .hero-stats {
      display: grid;
      gap: 16px;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }

    .hero-stat {
      background: #fff;
      border-radius: 18px;
      padding: 20px;
      border: 1px solid rgba(161, 86, 28, 0.12);
      box-shadow: 0 12px 22px rgba(0, 0, 0, 0.08);
    }

    .hero-stat strong {
      display: block;
      font-size: 1.8rem;
      color: #a1561c;
    }

    .hero-stat span {
      color: #6b5a4d;
      font-size: 0.9rem;
    }

    .contact-main {
      display: flex;
      flex-direction: column;
      gap: 64px;
      margin-bottom: 80px;
    }

    .contact-grid {
      display: grid;
      gap: 28px;
      grid-template-columns: minmax(280px, 1.1fr) minmax(240px, 0.9fr);
    }

    .card {
      background: #fff;
      border-radius: 24px;
      border: 1px solid rgba(161, 86, 28, 0.14);
      box-shadow: 0 18px 32px rgba(0, 0, 0, 0.08);
      padding: 32px;
    }

    .contact-form .row {
      display: grid;
      gap: 18px;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      margin-bottom: 18px;
    }

    .contact-form .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .contact-form label {
      font-weight: 600;
      color: #432212;
    }

    .contact-form input,
    .contact-form textarea {
      border-radius: 12px;
      border: 1px solid rgba(161, 86, 28, 0.22);
      padding: 12px 14px;
      font-size: 1rem;
      font-family: inherit;
      background: #fff;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .contact-form input:focus,
    .contact-form textarea:focus {
      border-color: #a1561c;
      box-shadow: 0 0 0 3px rgba(161, 86, 28, 0.18);
      outline: none;
    }

    .contact-form textarea {
      min-height: 160px;
      resize: vertical;
    }

    .err,
    .form-error {
      color: #b9471d;
      font-size: 0.85rem;
    }

    .form-success {
      background: #e6f7ec;
      border: 1px solid #a4d8b3;
      color: #1f6b3a;
      border-radius: 12px;
      padding: 14px 18px;
      margin-bottom: 18px;
    }

    .form-error {
      background: #fee9e3;
      border: 1px solid #f5b39a;
      border-radius: 12px;
      padding: 14px 18px;
      margin-bottom: 18px;
    }

    .contact-form .actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 16px;
      margin-top: 10px;
    }

    .contact-form button {
      border: none;
      cursor: pointer;
      border-radius: 999px;
      background: linear-gradient(135deg, #a1561c 0%, #c87b3f 100%);
      color: #fff;
      padding: 12px 28px;
      font-weight: 600;
      font-size: 1rem;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .contact-form button:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 22px rgba(161, 86, 28, 0.25);
    }

    .contact-side {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .channel-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
    }

    .channel-card {
      background: #fffdf9;
      border-radius: 20px;
      border: 1px solid rgba(161, 86, 28, 0.12);
      padding: 24px;
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .channel-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: rgba(161, 86, 28, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #a1561c;
    }

    .channel-card h3 {
      margin: 0;
      color: #432212;
    }

    .channel-card p {
      margin: 0;
      color: #513a2a;
      line-height: 1.6;
      flex: 1;
    }

    .channel-card a {
      font-weight: 600;
      color: #a1561c;
      text-decoration: none;
    }

    .channel-card a:hover {
      text-decoration: underline;
    }

    .channel-card .cta-label {
      font-weight: 600;
      color: #a1561c;
    }

    .highlight-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
    }

    .highlight-card {
      background: #fff;
      border-radius: 20px;
      border: 1px solid rgba(161, 86, 28, 0.12);
      padding: 26px;
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
    }

    .highlight-card h3 {
      margin-top: 0;
      color: #a1561c;
      font-size: 1.15rem;
    }

    .highlight-card .highlight-body {
      margin: 12px 0 0;
      color: #513a2a;
      line-height: 1.6;
    }

    .faq-section details {
      border: 1px solid rgba(161, 86, 28, 0.18);
      border-radius: 16px;
      padding: 18px 22px;
      background: #fff;
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
    }

    .faq-section summary {
      cursor: pointer;
      font-weight: 600;
      color: #432212;
      list-style: none;
      position: relative;
    }

    .faq-section summary::after {
      content: '+';
      position: absolute;
      right: 0;
      top: 0;
      font-weight: 700;
      transition: transform 0.2s ease;
    }

    .faq-section details[open] summary::after {
      transform: rotate(45deg);
    }

    .faq-section .faq-answer {
      margin: 12px 0 0;
      color: #513a2a;
      line-height: 1.6;
    }

    .contact-cta {
      background: linear-gradient(135deg, #a1561c 0%, #c87b3f 100%);
      border-radius: 28px;
      padding: 48px 40px;
      color: #fff;
      display: grid;
      align-items: center;
      gap: 24px;
      grid-template-columns: minmax(260px, 1fr) minmax(200px, 0.8fr);
    }

    .contact-cta h2 {
      margin: 0 0 12px;
      font-size: clamp(1.8rem, 3vw, 2.4rem);
    }

    .contact-cta p {
      margin: 0;
      line-height: 1.6;
    }

    .contact-cta .cta-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 14px;
    }

    .contact-cta .btn-light {
      background: #fff;
      color: #a1561c;
      border-radius: 999px;
      padding: 12px 28px;
      font-weight: 600;
      text-decoration: none;
    }

    .contact-cta .btn-outline {
      border-radius: 999px;
      padding: 12px 28px;
      border: 1px solid rgba(255, 255, 255, 0.6);
      color: #fff;
      text-decoration: none;
      background: transparent;
    }

    .contact-cta .btn-outline:hover,
    .contact-cta .btn-light:hover {
      opacity: 0.9;
    }

    .map-embed {
      display: inline-block;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 12px 30px rgba(30, 41, 59, 0.08);
      transition: transform .2s ease;
    }

    .map-embed img {
      display: block;
      width: 100%;
      height: auto;
    }

    .map-embed iframe {
      display: block;
      width: 100%;
      min-height: 240px;
      border: 0;
    }

    .map-embed:hover {
      transform: translateY(-2px);
    }

    .social-inline {
      display: flex;
      gap: 10px;
      margin-top: 16px;
    }

    .social-inline img {
      width: 28px;
      height: 28px;
    }

    @media (max-width: 960px) {
      .contact-hero .hero-inner,
      .contact-cta {
        grid-template-columns: 1fr;
        text-align: center;
      }

      .contact-cta .cta-actions {
        justify-content: center;
      }

      .contact-grid {
        grid-template-columns: 1fr;
      }

      .contact-form .row {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 760px) {
      .contact-hero {
        padding: 56px 0;
        border-radius: 24px;
      }

      .contact-main {
        gap: 48px;
      }

      .card {
        padding: 24px;
        border-radius: 20px;
      }

      .highlight-grid,
      .channel-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <section class="contact-hero">
    <div class="container hero-inner">
      <div>
        <h1><?= rb_escape($contactTitle) ?></h1>
        <p><?= rb_escape($contactSubtitle) ?></p>
      </div>
      <div class="hero-stats">
        <?php foreach ($contactStats as $stat): ?>
          <div class="hero-stat">
            <strong><?= rb_escape($stat['value']) ?></strong>
            <span><?= rb_escape($stat['label']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <main class="contact-main container">
    <section class="contact-grid">
      <div class="card contact-form">
        <h2>Send a message</h2>
        <p>We read every message carefully. Share as much context as you can and we will guide you from there.</p>
        <?php if ($contactSuccess): ?>
          <div class="form-success"><?= rb_escape($contactSuccess) ?></div>
        <?php endif; ?>
        <?php if (isset($contactErrors['form'])): ?>
          <div class="form-error"><?= rb_escape($contactErrors['form']) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
          <?= rb_csrf_input() ?>
          <div class="row">
            <div class="field">
              <label for="cName">Name</label>
              <input id="cName" name="name" type="text" value="<?= rb_escape($contactFormData['name']) ?>" placeholder="Your full name" autocomplete="name" required>
              <?php if (isset($contactErrors['name'])): ?><span class="err"><?= rb_escape($contactErrors['name']) ?></span><?php endif; ?>
            </div>
            <div class="field">
              <label for="cEmail">Email</label>
              <input id="cEmail" name="email" type="email" value="<?= rb_escape($contactFormData['email']) ?>" placeholder="you@example.com" autocomplete="email" required>
              <?php if (isset($contactErrors['email'])): ?><span class="err"><?= rb_escape($contactErrors['email']) ?></span><?php endif; ?>
            </div>
          </div>

          <div class="field">
            <label for="cSubject">Subject (optional)</label>
            <input id="cSubject" name="subject" type="text" value="<?= rb_escape($contactFormData['subject']) ?>" placeholder="How can we help?">
          </div>

          <div class="field">
            <label for="cMessage">Message</label>
            <textarea id="cMessage" name="message" rows="6" placeholder="Share a few details so we can best support you." required><?= rb_escape($contactFormData['message']) ?></textarea>
            <?php if (isset($contactErrors['message'])): ?><span class="err"><?= rb_escape($contactErrors['message']) ?></span><?php endif; ?>
          </div>

          <input type="text" name="website" tabindex="-1" autocomplete="off" style="display:none;">

          <div class="actions">
            <button type="submit">Send message</button>
            <?php if ($formNoteHtml): ?>
              <small><?= rb_trusted_html($formNoteHtml) ?></small>
            <?php else: ?>
              <small>Prefer email? Write to <a href="mailto:<?= rb_escape($contactEmail) ?>"><?= rb_escape($contactEmail) ?></a>.</small>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <aside class="contact-side">
        <div class="card">
          <h3>Visit or call</h3>
          <p><?= rb_escape($contactAddress) ?></p>
          <p><a href="mailto:<?= rb_escape($contactEmail) ?>"><?= rb_escape($contactEmail) ?></a></p>
          <p><a href="tel:<?= rb_escape(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>"><?= rb_escape($contactPhone) ?></a></p>
          <p class="muted"><?= rb_trusted_html($contactHours) ?></p>
          <?php if (!empty($socialLinks)): ?>
            <div class="social-inline">
              <?php foreach ($socialLinks as $social): ?>
                <a href="<?= rb_escape($social['url']) ?>" target="_blank" rel="noopener" aria-label="<?= rb_escape($social['label']) ?>">
                  <img src="<?= rb_escape($social['icon']) ?>" alt="<?= rb_escape($social['label']) ?>">
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($mapImageAvailable || $mapDirectionsUrl !== '' || $mapEmbedUrl !== ''): ?>
          <div class="card map">
            <h3>Studio map</h3>
            <?php if ($mapImageAvailable): ?>
              <?php
              $mapImageTag = '<img src="' . rb_asset($mapImageRelative) . '" alt="Map preview for the RudraBlessings studio">';
              if ($mapClickTarget !== '') {
                  echo '<a class="map-embed" href="' . rb_escape($mapClickTarget) . '" target="_blank" rel="noopener noreferrer">' . $mapImageTag . '</a>';
              } else {
                  echo '<div class="map-embed">' . $mapImageTag . '</div>';
              }
              ?>
            <?php elseif ($mapEmbedUrl !== ''): ?>
              <div class="map-embed map-iframe">
                <iframe title="Studio map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="<?= rb_escape($mapEmbedUrl) ?>"></iframe>
              </div>
            <?php else: ?>
              <p class="muted" style="margin:0;">Map preview unavailable. <?= $mapDirectionsUrl !== '' ? '<a href="' . rb_escape($mapDirectionsUrl) . '" target="_blank" rel="noopener noreferrer">Open in Google Maps</a>.' : '' ?></p>
            <?php endif; ?>
            <?php if ($mapClickTarget !== ''): ?>
              <p class="muted" style="margin-top:10px;font-size:13px;">
                Tap the map to open directions in Google Maps.
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </aside>
    </section>

    <section>
      <h2>Connect with us</h2>
      <div class="channel-grid">
        <?php foreach ($supportChannels as $channel): ?>
          <?php
          $channelIcon = (string)($channel['icon'] ?? '');
          $channelTitle = (string)($channel['title'] ?? '');
          $channelDescription = (string)($channel['description'] ?? '');
          $channelCtaLabel = trim((string)($channel['cta_label'] ?? ''));
          $channelCtaUrl = trim((string)($channel['cta_url'] ?? ''));
          if ($channelCtaUrl === '' && $channelCtaLabel !== '') {
              if ($channelIcon === 'phone') {
                  $channelCtaUrl = 'tel:' . preg_replace('/[^0-9+]/', '', $channelCtaLabel);
              } elseif ($channelIcon === 'envelope' && filter_var($channelCtaLabel, FILTER_VALIDATE_EMAIL)) {
                  $channelCtaUrl = 'mailto:' . $channelCtaLabel;
              }
          }
          ?>
          <article class="channel-card">
            <div class="channel-icon" aria-hidden="true">
              <?php if ($channelIcon === 'phone'): ?>
                <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M6.62 10.79a15.053 15.053 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1-.24 11.36 11.36 0 0 0 3.58.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.36 11.36 0 0 0 .57 3.58 1 1 0 0 1-.25 1z"/></svg>
              <?php else: ?>
                <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm8 7L4 6v12h16V6l-8 5zM4 6l8 5 8-5H4z"/></svg>
              <?php endif; ?>
            </div>
            <h3><?= rb_escape($channelTitle) ?></h3>
            <p><?= rb_escape($channelDescription) ?></p>
            <?php if ($channelCtaLabel !== ''): ?>
              <?php if ($channelCtaUrl !== ''): ?>
                <a href="<?= rb_escape($channelCtaUrl) ?>"><?= rb_escape($channelCtaLabel) ?></a>
              <?php else: ?>
                <span class="cta-label"><?= rb_escape($channelCtaLabel) ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section>
      <h2>How we can collaborate</h2>
      <div class="highlight-grid">
        <?php foreach ($contactHighlights as $highlight): ?>
          <?php
          $highlightTitle = (string)($highlight['title'] ?? '');
          $highlightBody = (string)($highlight['body'] ?? '');
          ?>
          <article class="highlight-card">
            <h3><?= rb_escape($highlightTitle) ?></h3>
            <div class="highlight-body"><?= rb_trusted_html($highlightBody) ?></div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="faq-section">
      <h2>FAQ</h2>
      <div class="stack" style="display:grid;gap:16px;">
        <?php foreach ($faqEntries as $faq): ?>
          <?php
          $faqQuestion = (string)($faq['question'] ?? '');
          $faqAnswer = (string)($faq['answer'] ?? '');
          ?>
          <details>
            <summary><?= rb_escape($faqQuestion) ?></summary>
            <div class="faq-answer"><?= rb_trusted_html($faqAnswer) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="contact-cta">
      <div>
        <h2>Prefer a face-to-face chat?</h2>
        <p>Book a virtual tea with our ritual specialists. We will walk you through product recommendations, cleansing practices, or community facilitation ideas.</p>
      </div>
      <div class="cta-actions">
        <a class="btn-light" href="mailto:<?= rb_escape($contactEmail) ?>">Email our team</a>
        <a class="btn-outline" href="tel:<?= rb_escape(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>">Call <?= rb_escape($contactPhone) ?></a>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="<?= rb_asset('js/main.js') ?>"></script>
  <script src="<?= rb_asset('js/pages.js') ?>"></script>
</body>

</html>
