<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$currentPath = 'about.php';
$aboutBlocks = rb_blocks($pdo, 'about');
$aboutHero = $aboutBlocks['hero'] ?? [];
$aboutStory = $aboutBlocks['story'] ?? [];
$aboutValues = $aboutBlocks['values'] ?? [];
$aboutPromise = $aboutBlocks['promise'] ?? [];
$aboutCta = $aboutBlocks['cta'] ?? [];

$heroTitle = trim((string)($aboutHero['title'] ?? 'About RudraBlessings'));
if ($heroTitle === '') {
    $heroTitle = 'About RudraBlessings';
}
$heroSubtitle = trim((string)($aboutHero['subtitle'] ?? ''));
if ($heroSubtitle === '') {
    $heroSubtitle = 'Sacred tools and mindful rituals inspired by the Himalayas.';
}
$heroBody = trim((string)($aboutHero['body'] ?? ''));
if ($heroBody === '') {
    $heroBody = 'RudraBlessings is a collective of seekers, artisans, and teachers dedicated to creating ritual companions that feel as grounding as they are beautiful.';
}
$storyImage = trim((string)($aboutStory['media_path'] ?? ''));
if ($storyImage === '') {
    $storyImage = 'media/products/crystals/clear-quartz/image3.jpg';
}
$normalizeStoryImage = $storyImage !== '' && strncmp($storyImage, 'data:', 5) !== 0 && !preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $storyImage);
if ($normalizeStoryImage) {
    $storyImage = rb_asset(ltrim($storyImage, '/\\'));
}
$storyTitle = trim((string)($aboutStory['title'] ?? ''));
if ($storyTitle === '') {
    $storyTitle = 'Our Story';
}
$storyBody = trim((string)($aboutStory['body'] ?? ''));
if ($storyBody === '') {
    $storyBody = '<p>RudraBlessings began as a small circle of friends searching for meaningful spiritual tools that honoured both lineage and modern life. Guided by teachers in Rishikesh and Kathmandu, we spent years learning the art of cleansing, stringing, and energising Rudraksha beads and crystals.</p><p>We now collaborate with artisan families we met along that journey. Every mala, incense bundle, and altar piece is curated to support mindful rituals—whether you are meditating before dawn or taking a quiet moment between meetings. We exist to make sacred practice accessible, beautiful, and sustainable.</p>';
}
$storyCaption = trim((string)($aboutStory['subtitle'] ?? ''));
if ($storyCaption === '') {
    $storyCaption = 'Each Rudraksha strand is energetically cleansed, blessed, and quality checked before it leaves our studio.';
}
$valuesTitle = trim((string)($aboutValues['title'] ?? ''));
if ($valuesTitle === '') {
    $valuesTitle = 'Our Values';
}
$valuesBody = trim((string)($aboutValues['body'] ?? ''));
$promiseTitle = trim((string)($aboutPromise['title'] ?? ''));
if ($promiseTitle === '') {
    $promiseTitle = 'Our Promise';
}
$promiseBody = trim((string)($aboutPromise['body'] ?? ''));
if ($promiseBody === '') {
    $promiseBody = '<p>We pledge to honour the origins of every piece we share by practicing transparent sourcing, fair partnerships, and mindful packaging. If something ever falls short, we will make it right—your trust is as sacred as the rituals we support.</p><ul><li>Authenticity guaranteed on every Rudraksha bead and gemstone.</li><li>Fair compensation for our artisan partners and growers.</li><li>Earth-kind operations with compostable or reusable packaging.</li></ul>';
}
$ctaTitle = trim((string)($aboutCta['title'] ?? ''));
if ($ctaTitle === '') {
    $ctaTitle = 'Join Our Journey';
}
$ctaBody = trim((string)($aboutCta['body'] ?? ''));
if ($ctaBody === '') {
    $ctaBody = 'Receive guided rituals, early product previews, and invitations to our community circles.';
}
$ctaLabel = trim((string)($aboutCta['cta_label'] ?? ''));
if ($ctaLabel === '') {
    $ctaLabel = 'Browse Shop';
}
$ctaUrl = trim((string)($aboutCta['cta_url'] ?? ''));
if ($ctaUrl === '') {
    $ctaUrl = 'shop.php';
}
$aboutMetaDescription = $heroBody !== '' ? $heroBody : 'Learn about RudraBlessings and the intention behind our mindful goods.';

$heroStats = [
    ['value' => '12+', 'label' => 'Years guiding seekers worldwide'],
    ['value' => '18', 'label' => 'Artisan families we partner with'],
    ['value' => '96%', 'label' => 'Orders shipped plastic-free'],
];

$pillarCards = [
    [
        'title' => 'Integrity in Every Bead',
        'description' => 'Each Rudraksha and crystal is verified for origin, vibration, and ethical harvesting before it becomes part of your ritual kit.',
    ],
    [
        'title' => 'Artisans First',
        'description' => 'We co-create with family-run workshops across India and Nepal, providing stable income, safe working spaces, and long-term collaborations.',
    ],
    [
        'title' => 'Guidance with Heart',
        'description' => 'Our practitioners share detailed care rituals, mantras, and journal prompts so your tools stay aligned to your intention.',
    ],
    [
        'title' => 'Sustainable Future',
        'description' => 'From compostable incense sleeves to reusable brass tins, we minimise waste at every stage of production and shipping.',
    ],
];

$craftHighlights = [
    [
        'title' => 'Intentional Sourcing',
        'description' => 'We trace each material to its origin, building relationships with growers who cultivate with traditional, chemical-free methods.',
    ],
    [
        'title' => 'Energetic Cleansing',
        'description' => 'Every item is cleansed with mantra, sunlight, and smoke, then rested in copper pyramids to anchor it with balanced energy.',
    ],
    [
        'title' => 'Mindful Packaging',
        'description' => 'Orders are wrapped in recycled cotton, accompanied by practice cards, and shipped carbon-neutral whenever possible.',
    ],
];

$journeySteps = [
    [
        'year' => '2012',
        'title' => 'Seeds of Intention',
        'description' => 'Our founder travelled through Rishikesh, learning from temple keepers and sourcing the first mala strands for friends back home.',
    ],
    [
        'year' => '2016',
        'title' => 'Collective Takes Shape',
        'description' => 'We formalised partnerships with artisan families in Haridwar, Varanasi, and Bhaktapur, ensuring fair pricing and ongoing orders.',
    ],
    [
        'year' => '2020',
        'title' => 'Sustainable Shift',
        'description' => 'Introduced plastic-free packaging, carbon-offset shipping, and educational resources for conscious spiritual practice.',
    ],
    [
        'year' => '2024',
        'title' => 'Community Circles',
        'description' => 'Launched digital satsangs, guided meditations, and pop-up gatherings to nurture a global community of seekers.',
    ],
];

$teamMembers = [
    [
        'name' => 'Rakesh Giri',
        'role' => 'Founder & Head of Rituals',
        'bio' => 'Cultivates relationships with Himalayan partner families and guides the sacred processes behind every RudraBlessings collection.',
        'photo' => 'media/testimonial1.jpg',
    ],
    [
        'name' => 'MD Kamrul',
        'role' => 'Sourcing & Production Lead',
        'bio' => 'Coordinates artisan production, ensuring each bead, crystal, and incense batch meets our authenticity and sustainability standards.',
        'photo' => 'media/testimonial2.jpg',
    ],
    [
        'name' => 'Alvi',
        'role' => 'Experience Designer',
        'bio' => 'Crafts the meditations, rituals, and journaling prompts that help our community anchor intentions into daily life.',
        'photo' => 'media/testimonial3.jpg',
    ],
    [
        'name' => 'MO Kaswar',
        'role' => 'Operations Steward',
        'bio' => 'Oversees mindful logistics, from eco-friendly packaging to carbon-aware shipping routes, so every parcel arrives with care.',
        'photo' => 'media/testimonial4.jpg',
    ],
    [
        'name' => 'Adnan',
        'role' => 'Community & Care Guide',
        'bio' => 'Supports seekers through every step of their journey, hosting circles and offering personalised recommendations.',
        'photo' => 'media/testimonial5.jpg',
    ],
];

$communityQuotes = [
    [
        'quote' => '“I can feel the care woven into every mala. The guidance cards kept me consistent with my meditation for the first time in years.”',
        'name' => 'Priya S.',
        'location' => 'Sydney, Australia',
    ],
    [
        'quote' => '“RudraBlessings helped our studio transition to eco-conscious incense. Clients notice the difference immediately.”',
        'name' => 'Arjun @ Inner Glow Yoga',
        'location' => 'Melbourne, Australia',
    ],
    [
        'quote' => '“The community circles remind me that spiritual practice is shared. I leave every session grounded and inspired.”',
        'name' => 'Ella R.',
        'location' => 'Toronto, Canada',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= rb_escape($heroTitle) ?> | <?= rb_escape($brandName) ?></title>
  <meta name="description" content="<?= rb_escape($aboutMetaDescription) ?>">
  <link rel="icon" href="<?= rb_asset('media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('css/style.css') ?>">
  <style>
    .about-main {
      display: flex;
      flex-direction: column;
      gap: 72px;
      margin-top: 40px;
    }

    .about-hero {
      background: linear-gradient(135deg, #f9efe3 0%, #fbe7cf 100%);
      border-radius: 32px;
      padding: 72px 0 64px;
      position: relative;
      overflow: hidden;
    }

    .about-hero::after,
    .about-hero::before {
      content: '';
      position: absolute;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.4);
      filter: blur(120px);
      z-index: 0;
    }

    .about-hero::before {
      width: 320px;
      height: 320px;
      top: -80px;
      left: -60px;
    }

    .about-hero::after {
      width: 240px;
      height: 240px;
      top: -40px;
      right: 10%;
    }

    .about-hero .container {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: minmax(260px, 1.15fr) minmax(200px, 0.85fr);
      gap: 48px;
      align-items: center;
    }

    .hero-text h1 {
      margin: 0 0 12px;
      font-size: clamp(2.1rem, 4vw, 3rem);
      color: #432212;
    }

    .hero-subtitle {
      font-weight: 600;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #a4703c;
      font-size: 0.9rem;
      margin-bottom: 18px;
    }

    .hero-text p {
      color: #5d4633;
      max-width: 540px;
      line-height: 1.65;
      margin: 0;
    }

    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      margin-top: 28px;
    }

    .hero-actions .btn-primary {
      background: #a1561c;
      border-color: #a1561c;
    }

    .hero-actions .btn-outline {
      border-color: #a1561c;
      color: #a1561c;
    }

    .hero-actions .btn-outline:hover {
      background: #a1561c;
      color: #fff;
    }

    .hero-gallery {
      display: grid;
      gap: 16px;
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hero-gallery img {
      width: 100%;
      border-radius: 18px;
      object-fit: cover;
      height: 180px;
      display: block;
      box-shadow: 0 16px 32px rgba(0, 0, 0, 0.08);
    }

    .hero-gallery img:last-child {
      grid-column: span 2;
      height: 210px;
    }

    .metrics-grid {
      display: grid;
      gap: 18px;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      margin-top: 32px;
    }

    .metric-card {
      background: #fff;
      border-radius: 18px;
      padding: 24px;
      box-shadow: 0 12px 24px rgba(34, 20, 8, 0.08);
      border: 1px solid rgba(161, 86, 28, 0.12);
    }

    .metric-card strong {
      display: block;
      font-size: 1.9rem;
      color: #a1561c;
      margin-bottom: 6px;
      font-weight: 700;
    }

    .metric-card span {
      color: #6b5a4d;
      font-size: 0.95rem;
    }

    .about-section {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .story-layout {
      display: grid;
      grid-template-columns: minmax(220px, 0.9fr) minmax(260px, 1.1fr);
      gap: 36px;
      align-items: start;
    }

    .story-media figure {
      margin: 0;
      border-radius: 18px;
      overflow: hidden;
      border: 1px solid rgba(161, 86, 28, 0.14);
      background: #fff;
      box-shadow: 0 18px 36px rgba(0, 0, 0, 0.12);
    }

    .story-media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      max-height: 460px;
      display: block;
    }

    .story-media figcaption {
      padding: 14px 18px;
      font-size: 0.85rem;
      color: #6b5a4d;
      background: #fffdf9;
    }

    .story-copy h2 {
      margin-top: 0;
      color: #432212;
    }

    .story-copy .story-body {
      display: grid;
      gap: 16px;
      color: #513a2a;
      line-height: 1.7;
    }

    .values-section h2 {
      margin-bottom: 8px;
      color: #432212;
    }

    .values-intro {
      color: #513a2a;
      line-height: 1.7;
    }

    .pillar-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
    }

    .pillar-card {
      background: #fff;
      padding: 24px;
      border-radius: 20px;
      border: 1px solid rgba(161, 86, 28, 0.14);
      box-shadow: 0 14px 24px rgba(0, 0, 0, 0.07);
    }

    .pillar-card h3 {
      margin-top: 0;
      color: #a1561c;
    }

    .pillar-card p {
      margin: 12px 0 0;
      color: #513a2a;
      line-height: 1.6;
    }

    .craft-section .section-intro {
      color: #513a2a;
      max-width: 620px;
      line-height: 1.7;
    }

    .craft-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
    }

    .craft-card {
      background: #fff;
      border: 1px solid rgba(161, 86, 28, 0.12);
      border-radius: 18px;
      padding: 24px;
      box-shadow: 0 12px 22px rgba(0, 0, 0, 0.08);
    }

    .craft-card h3 {
      margin: 0 0 14px;
      color: #432212;
    }

    .craft-card p {
      margin: 0;
      color: #513a2a;
      line-height: 1.6;
    }

    .craft-step {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: rgba(161, 86, 28, 0.12);
      color: #a1561c;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      margin-bottom: 18px;
    }

    .timeline-section h2 {
      color: #432212;
    }

    .journey-timeline {
      position: relative;
      padding-left: 28px;
      margin-top: 24px;
    }

    .journey-timeline::before {
      content: '';
      position: absolute;
      left: 10px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: linear-gradient(180deg, #a1561c 0%, rgba(161, 86, 28, 0.12) 100%);
    }

    .timeline-step {
      position: relative;
      padding-left: 24px;
      margin-bottom: 32px;
    }

    .timeline-step:last-child {
      margin-bottom: 0;
    }

    .timeline-step::before {
      content: '';
      position: absolute;
      left: -18px;
      top: 6px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: #fff;
      border: 3px solid #a1561c;
      box-shadow: 0 0 0 4px rgba(161, 86, 28, 0.1);
    }

    .timeline-step .step-year {
      font-weight: 700;
      color: #a1561c;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-bottom: 6px;
      display: block;
    }

    .timeline-step h3 {
      margin: 0 0 8px;
      color: #432212;
    }

    .timeline-step p {
      margin: 0;
      color: #513a2a;
      line-height: 1.6;
    }

    .team-section h2 {
      color: #432212;
      text-align: center;
    }

    .team-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
    }

    .team-card {
      background: #fff;
      border-radius: 20px;
      padding: 24px;
      text-align: center;
      border: 1px solid rgba(161, 86, 28, 0.12);
      box-shadow: 0 16px 28px rgba(0, 0, 0, 0.08);
    }

    .team-card img {
      width: 96px;
      height: 96px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 16px;
      box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
    }

    .team-card .role {
      font-size: 0.9rem;
      color: #a1561c;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .team-card p {
      margin: 0;
      color: #513a2a;
      line-height: 1.6;
    }

    .community-section {
      background: #fff7ed;
      border-radius: 26px;
      padding: 48px 36px;
      border: 1px solid rgba(161, 86, 28, 0.18);
      box-shadow: 0 18px 32px rgba(0, 0, 0, 0.08);
    }

    .community-section h2 {
      margin-top: 0;
      color: #432212;
      text-align: center;
    }

    .community-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 24px;
      margin-top: 24px;
    }

    .community-card {
      margin: 0;
      background: #fff;
      border-radius: 18px;
      padding: 24px;
      border: 1px solid rgba(161, 86, 28, 0.12);
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .community-card blockquote {
      margin: 0;
      font-style: italic;
      color: #432212;
      line-height: 1.6;
    }

    .community-card figcaption {
      font-size: 0.85rem;
      color: #6b5a4d;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .promise-section {
      background: #fffdf9;
      border-radius: 24px;
      padding: 40px;
      border: 1px solid rgba(161, 86, 28, 0.14);
      box-shadow: 0 18px 30px rgba(0, 0, 0, 0.06);
    }

    .promise-section h2 {
      margin-top: 0;
      color: #432212;
    }

    .promise-section .promise-body {
      color: #513a2a;
      line-height: 1.7;
    }

    .promise-section ul {
      padding-left: 18px;
      margin: 16px 0 0;
    }

    .cta {
      background: linear-gradient(135deg, #a1561c 0%, #c87b3f 100%);
      border-radius: 28px;
      padding: 48px 40px;
      color: #fff;
      position: relative;
      overflow: hidden;
    }

    .cta::after {
      content: '';
      position: absolute;
      inset: -60% 60% auto -30%;
      background: rgba(255, 255, 255, 0.15);
      transform: rotate(-12deg);
    }

    .cta h2 {
      margin: 0 0 12px;
      font-size: 2rem;
    }

    .cta p {
      margin: 0 0 24px;
      max-width: 480px;
      line-height: 1.6;
    }

    .cta .btn-primary {
      background: #fff;
      color: #a1561c;
      border: none;
    }

    .cta .btn-primary:hover {
      background: #fce8d5;
    }

    .cta .secondary {
      display: inline-flex;
      margin-left: 16px;
      color: #fff;
      font-weight: 600;
      text-decoration: underline;
    }

    @media (max-width: 1024px) {
      .about-hero .container {
        grid-template-columns: 1fr;
        text-align: center;
      }

      .hero-text p {
        margin: 0 auto;
      }

      .hero-actions {
        justify-content: center;
      }

      .hero-gallery {
        max-width: 520px;
        margin: 0 auto;
      }

      .story-layout {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 760px) {
      .about-main {
        gap: 56px;
        margin-top: 24px;
      }

      .about-hero {
        border-radius: 24px;
        padding: 56px 0;
      }

      .hero-gallery img {
        height: 160px;
      }

      .metrics-grid {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      }

      .pillar-grid {
        grid-template-columns: 1fr;
      }

      .craft-grid {
        grid-template-columns: 1fr;
      }

      .community-section {
        padding: 36px 24px;
      }

      .cta {
        text-align: center;
      }

      .cta .secondary {
        display: block;
        margin: 12px 0 0;
      }
    }
  </style>
</head>

<body data-user="<?= $isLoggedIn ? '1' : '0' ?>">
  <?php include __DIR__ . '/includes/header.php'; ?>
  <main class="about-main">
    <section class="about-hero">
      <div class="container">
        <div class="hero-text">
          <div class="hero-subtitle"><?= rb_escape($heroSubtitle) ?></div>
          <h1><?= rb_escape($heroTitle) ?></h1>
          <p><?= rb_escape($heroBody) ?></p>
          <div class="hero-actions">
            <a href="shop.php" class="btn btn-primary">Explore the Shop</a>
            <a href="contact.php" class="btn btn-outline">Talk to Our Team</a>
          </div>
        </div>
        <div class="hero-gallery">
          <img src="<?= rb_escape(rb_asset('media/hero1.jpg')) ?>" alt="Handcrafted Rudraksha malas in the RudraBlessings studio">
          <img src="<?= rb_escape(rb_asset('media/hero2.jpg')) ?>" alt="Artisan carefully inspecting crystals">
          <img src="<?= rb_escape(rb_asset('media/hero3.jpg')) ?>" alt="Meditation setup featuring incense and candles">
        </div>
      </div>
      <div class="container">
        <div class="metrics-grid">
          <?php foreach ($heroStats as $stat): ?>
            <article class="metric-card">
              <strong><?= rb_escape($stat['value']) ?></strong>
              <span><?= rb_escape($stat['label']) ?></span>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="about-section container story-section">
      <div class="story-layout">
        <div class="story-media">
          <figure>
            <img src="<?= rb_escape($storyImage) ?>" alt="<?= rb_escape($storyTitle) ?> illustration">
            <?php if ($storyCaption): ?>
              <figcaption><?= rb_escape($storyCaption) ?></figcaption>
            <?php endif; ?>
          </figure>
        </div>
        <div class="story-copy">
          <h2><?= rb_escape($storyTitle) ?></h2>
          <div class="story-body">
            <?= rb_trusted_html($storyBody) ?>
          </div>
        </div>
      </div>
    </section>

    <section class="about-section container values-section">
      <h2><?= rb_escape($valuesTitle) ?></h2>
      <?php if ($valuesBody !== ''): ?>
        <div class="values-intro">
          <?= rb_trusted_html($valuesBody) ?>
        </div>
      <?php endif; ?>
      <div class="pillar-grid">
        <?php foreach ($pillarCards as $pillar): ?>
          <article class="pillar-card">
            <h3><?= rb_escape($pillar['title']) ?></h3>
            <p><?= rb_escape($pillar['description']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="about-section container craft-section">
      <h2>How We Craft Each Offering</h2>
      <p class="section-intro">From seed to shipping, every step is handled with reverence. Here is how we keep every RudraBlessings tool aligned to your intention.</p>
      <div class="craft-grid">
        <?php foreach ($craftHighlights as $index => $highlight): ?>
          <article class="craft-card">
            <span class="craft-step"><?= $index + 1 ?></span>
            <h3><?= rb_escape($highlight['title']) ?></h3>
            <p><?= rb_escape($highlight['description']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="about-section container timeline-section">
      <h2>Our Journey</h2>
      <div class="journey-timeline">
        <?php foreach ($journeySteps as $step): ?>
          <div class="timeline-step">
            <span class="step-year"><?= rb_escape($step['year']) ?></span>
            <h3><?= rb_escape($step['title']) ?></h3>
            <p><?= rb_escape($step['description']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="about-section container team-section">
      <h2>Meet the Team</h2>
      <div class="team-grid">
        <?php foreach ($teamMembers as $member): ?>
          <article class="team-card">
            <img src="<?= rb_escape(rb_asset($member['photo'])) ?>" alt="<?= rb_escape($member['name']) ?> portrait">
            <div class="role"><?= rb_escape($member['role']) ?></div>
            <h3><?= rb_escape($member['name']) ?></h3>
            <p><?= rb_escape($member['bio']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="about-section container community-section">
      <h2>Community Voices</h2>
      <div class="community-grid">
        <?php foreach ($communityQuotes as $quote): ?>
          <figure class="community-card">
            <blockquote><?= rb_escape($quote['quote']) ?></blockquote>
            <figcaption><?= rb_escape($quote['name']) ?> • <?= rb_escape($quote['location']) ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>

  </main>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="<?= rb_asset('js/main.js') ?>"></script>
</body>

</html>
