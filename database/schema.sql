-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 16, 2025 at 05:55 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rudrablessings`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(60) NOT NULL,
  `contact_name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `line1` varchar(160) NOT NULL,
  `line2` varchar(160) DEFAULT NULL,
  `city` varchar(80) NOT NULL,
  `state` varchar(80) NOT NULL,
  `postcode` varchar(15) NOT NULL,
  `country` varchar(80) NOT NULL DEFAULT 'Australia',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `label`, `contact_name`, `phone`, `line1`, `line2`, `city`, `state`, `postcode`, `country`, `is_default`, `created_at`) VALUES
(1, 2, 'Home', 'Guest Customer', '+61 400 111 111', '12 Oceanic Way', 'Apartment 6B', 'Sydney', 'NSW', '2000', 'Australia', 1, '2025-10-16 13:21:00'),
(2, 2, 'Studio', 'Guest Customer', '+61 400 111 111', '48 Market Street', NULL, 'Melbourne', 'VIC', '3000', 'Australia', 0, '2025-10-16 13:21:00'),
(3, 1, 'Checkout', 'Admin User', '0410462468', '8/558 Railway Parade, Hurstville 2220', '', 'Hurstville', 'NSW', '2220', 'Australia', 0, '2025-10-16 14:15:58');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(150) NOT NULL,
  `title` varchar(200) NOT NULL,
  `excerpt` text NOT NULL,
  `body` longtext NOT NULL,
  `hero_image` varchar(255) DEFAULT NULL,
  `author_name` varchar(120) NOT NULL,
  `author_avatar` varchar(255) DEFAULT NULL,
  `author_bio` text DEFAULT NULL,
  `published_at` date NOT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `slug`, `title`, `excerpt`, `body`, `hero_image`, `author_name`, `author_avatar`, `author_bio`, `published_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 'choosing-your-first-rudraksha', 'Choosing Your First Rudraksha', 'A practical guide to meanings, selection and daily practice.', '<p><b>Rudraksha is more than an ornament; it is a companion for intention.</b> It supports focus, steadies emotions, and reminds us to return to our practice. Each bead carries a natural energy that can align with different needs such as calmness, clarity, or protection.</p>\r\n\r\n<p><b>How to choose the right Rudraksha</b><br>\r\n• Start with your intention: peace, protection, success, or spiritual growth.<br>\r\n• Understand “mukhis” (faces): a five-mukhi is commonly used for balance and daily peace; other mukhis may be chosen for specific goals.<br>\r\n• Notice resonance: the “right” bead feels grounding and comfortable to wear.<br>\r\n• Prefer simplicity: if unsure, begin with a single five-mukhi mala or pendant for daily use.</p>\r\n\r\n<p><b>Authenticity checks</b><br>\r\n• Natural grooves are visible and uneven; the bead feels organic, not plastic-smooth.<br>\r\n• The hole is usually centered and the seed looks fibrous inside when inspected carefully (do not damage the bead).<br>\r\n• Buy from a reputable source that can explain origin and mukhi count clearly.</p>\r\n\r\n<p><b>Daily care and handling</b><br>\r\n• Wipe gently with a soft, slightly damp cloth; let it air-dry fully.<br>\r\n• Apply a tiny drop of natural oil (sesame, coconut, or sandalwood) occasionally to prevent drying.<br>\r\n• Avoid soaking, harsh soaps, perfumes, or chemical cleaners.<br>\r\n• Remove before showering, swimming, heavy exercise, or sleeping to preserve the bead and thread.</p>\r\n\r\n<p><b>Energizing your bead</b><br>\r\n• Sit quietly for a few minutes, breathe steadily, and set a clear intention.<br>\r\n• Chant a mantra you connect with, or silently repeat your intention 9, 27, or 108 times.<br>\r\n• Keep the bead on a clean altar or pouch when not in use.</p>\r\n\r\n<p><b>How to wear</b><br>\r\n• As a mala (108 beads) for meditation and japa practice.<br>\r\n• As a pendant or bracelet for all-day mindfulness and gentle protection.<br>\r\n• Keep it close to the skin; consistency matters more than duration.</p>\r\n\r\n<p><b>Simple practice for beginners</b><br>\r\n• Morning: hold the bead, inhale for 4 counts, exhale for 6 counts, repeat 9 times while stating your intention.<br>\r\n• Midday reset: touch the bead and take 3 calm breaths before important tasks.<br>\r\n• Evening: express gratitude for three things while holding the bead, then store it respectfully.</p>\r\n\r\n<p><b>Common questions</b><br>\r\n• Can anyone wear Rudraksha? Yes, it is generally suitable for all, regardless of background.<br>\r\n• How long until effects are felt? This varies; consistency of intention and practice is key.<br>\r\n• Can I wear multiple mukhis? Yes, but start simple and notice how each bead affects your state.</p>\r\n\r\n<p><b>Quick safety and respect notes</b><br>\r\n• If the thread loosens, restring gently on a clean day; avoid metal wires that may cut the bead.<br>\r\n• Keep away from extreme heat and prolonged moisture.<br>\r\n• Treat it as sacred: handle with clean hands and a calm mind.</p>\r\n\r\n<p><b>Bottom line</b><br>\r\nRudraksha is a steady anchor for intention. Choose a bead that matches your goal, keep your practice simple and regular, and care for the bead with respect. Over time, its presence can help cultivate clarity, calmness, and deeper focus.</p>\r\n', 'media/blog1.jpg', 'Admin', 'media/product8.jpg', 'Sharing grounded practices.', '2025-09-15', 'published', '2025-10-16 13:21:00', '2025-10-16 14:34:29'),
(2, 'beginner-crystal-rituals', 'Beginner Crystal Rituals', 'Simple home rituals with crystals to ground and soften the day.', '<p><b>Simple Home Rituals with Crystals to Ground and Soothe</b></p> <p>Crystals are more than decorative stones — they are natural energy holders that can bring calm, balance, and grounding into daily life. When used with intention, they create a peaceful environment that supports emotional and spiritual well-being.</p> <p><b>1. Morning Grounding Ritual</b><br> Keep a grounding crystal like <b>Black Tourmaline</b>, <b>Hematite</b>, or <b>Smoky Quartz</b> near your bedside. After waking, hold the stone in your hand, take three deep breaths, and set an intention for the day such as “I am calm, safe, and centered.” This helps stabilize your energy before you begin your morning routine.</p> <p><b>2. Cleansing and Charging Crystals</b><br> Once a week, cleanse your crystals to remove stagnant energy. You can:<br> • Rinse them briefly under running water (if safe for that stone).<br> • Leave them in moonlight overnight during a full moon.<br> • Place them on a bed of rock salt for several hours.<br> • Use sage, incense, or sound (like a bell) for gentle energy cleansing.</p> <p><b>3. Creating a Crystal Corner or Altar</b><br> Designate a small space in your home where you place crystals that inspire peace. Add candles, incense, or small plants. Crystals like <b>Amethyst</b>, <b>Rose Quartz</b>, and <b>Clear Quartz</b> work well together to promote relaxation, love, and clarity. Sit near this space daily, even for a few minutes, to restore calmness.</p> <p><b>4. Bath Ritual for Emotional Release</b><br> Before a bath, place gentle stones like <b>Rose Quartz</b> or <b>Lepidolite</b> around the tub (not directly in hot water if delicate). Light a candle and visualize tension melting away. This practice helps release stress and brings emotional balance.</p> <p><b>5. Meditation with Crystals</b><br> Hold a crystal like <b>Amethyst</b> for spiritual calm, or <b>Smoky Quartz</b> for grounding. Sit comfortably, close your eyes, and focus on your breath. Imagine the crystal’s energy merging with your heartbeat, anchoring you in peace and stillness.</p> <p><b>6. Nighttime Soothing Ritual</b><br> Place <b>Selenite</b> or <b>Moonstone</b> near your pillow to calm the mind and ease restlessness. Before sleeping, hold the crystal to your heart, take slow breaths, and silently affirm, “I release today and welcome rest.”</p> <p><b>7. Simple Daily Practice</b><br> Carry a small tumbled stone in your pocket. Whenever you feel stressed, hold it for a few seconds and breathe deeply. It acts as a quiet reminder to stay centered throughout the day.</p> <p><b>Final Thought</b><br> Crystals work best when used with sincerity and routine. Treat them with respect, cleanse them regularly, and allow their gentle vibrations to guide you toward calmness, balance, and grounding energy each day.</p>', 'media/blog2.jpg', 'Admin', 'media/product2.jpg', 'Everyday clarity and balance.', '2025-10-02', 'published', '2025-10-16 13:21:00', '2025-10-16 14:35:20'),
(3, 'sandalwood-cleansing', 'Sandalwood Cleansing', 'How to cleanse spaces with smoke, sound and salt.', '<p><b>How to Cleanse Spaces with Smoke, Sound, and Salt</b></p>\r\n\r\n<p>Our living spaces absorb energy from thoughts, emotions, and daily activities. Over time, this energy can become heavy or stagnant. Cleansing your home or workspace helps reset its vibration, promoting peace, clarity, and flow. Three of the most effective and natural methods are <b>smoke</b>, <b>sound</b>, and <b>salt</b>.</p>\r\n\r\n<p><b>1. Smoke Cleansing</b><br>\r\nSmoke cleansing is an ancient practice used in many cultures to purify energy. You can use sage, incense, palo santo, or even dried herbs like rosemary and lavender.<br><br>\r\n<b>Steps:</b><br>\r\n• Open windows and doors to allow energy to move freely.<br>\r\n• Light your cleansing stick or incense and let it produce a steady stream of smoke.<br>\r\n• Move clockwise around each room, wafting the smoke into corners, doorways, and areas that feel heavy.<br>\r\n• As you move, repeat a simple affirmation like “Only peace and positivity remain in this space.”<br>\r\n• When finished, safely extinguish the smoke stick and thank the herbs for their cleansing energy.</p>\r\n\r\n<p><b>2. Sound Cleansing</b><br>\r\nSound carries vibration that can break up stagnant energy and raise the frequency of a space. This can be done with singing bowls, bells, chimes, or even clapping your hands.<br><br>\r\n<b>Steps:</b><br>\r\n• Begin at your front door or the center of the room.<br>\r\n• Strike your bowl, ring a bell, or clap gently while walking around the space.<br>\r\n• Focus on corners, as energy tends to gather there.<br>\r\n• Imagine the sound waves clearing away any dense or negative energy, leaving brightness and balance behind.<br>\r\n• End the ritual with a few deep breaths and a short moment of silence to anchor the new energy.</p>\r\n\r\n<p><b>3. Salt Cleansing</b><br>\r\nSalt is a powerful natural purifier that absorbs negativity and restores grounding energy.<br><br>\r\n<b>Methods:</b><br>\r\n• <b>Bowls of Salt:</b> Place small bowls of natural sea salt in the corners of rooms for 24–48 hours, then discard the salt outside or flush it away.<br>\r\n• <b>Salt Water Mop:</b> Mix a handful of salt into a bucket of warm water and mop or wipe surfaces to cleanse physical and energetic residue.<br>\r\n• <b>Crystal Cleansing:</b> Place your crystals or sacred objects on a bed of salt overnight to recharge and neutralize their energy.<br><br>\r\nAlways use natural, unprocessed salt (like sea salt or Himalayan salt) for best results.</p>\r\n\r\n<p><b>4. Combine the Three for Deep Cleansing</b><br>\r\nFor a thorough reset, begin with <b>sound</b> to awaken the space, follow with <b>smoke</b> to remove stagnant energy, and end with <b>salt</b> to absorb leftover negativity. This combination balances all energetic layers—physical, emotional, and spiritual.</p>\r\n\r\n<p><b>5. Final Tip</b><br>\r\nCleansing works best when done with clear intention. Before starting, pause for a moment and state your purpose—such as “I cleanse this space to invite peace, clarity, and harmony.” Do this weekly, monthly, or whenever your space feels heavy. Over time, you’ll notice a lighter atmosphere, better focus, and calmer emotions within your surroundings.</p>\r\n', 'media/blog3.jpg', 'Admin', 'media/product3.jpg', 'Practical calm.', '2025-11-05', 'published', '2025-10-16 13:21:00', '2025-10-16 14:35:56');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `session_token` char(40) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `session_token`, `created_at`, `updated_at`) VALUES
(1, 1, 'b48e31870b052c6ebc8c8dd3ad472452c400dd6a', '2025-10-16 13:22:52', '2025-10-16 14:54:18'),
(3, NULL, '8182a4e5944c1e87acbfd6070bae5b7bab77d7e1', '2025-10-16 14:45:13', '2025-10-16 14:45:13');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `cart_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `added_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `quantity`, `unit_price`, `added_at`) VALUES
(2, 1, 2, 1, 26.00, '2025-10-16 14:20:56'),
(3, 1, 4, 1, 34.00, '2025-10-16 14:20:57'),
(5, 1, 1, 1, 49.99, '2025-10-16 14:48:16');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(120) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `slug`, `name`, `description`, `image_path`) VALUES
(1, 'rudraksha', 'Rudraksha', 'Hand-knotted malas and sacred beads.', 'media/cat-rudraksha.jpg'),
(2, 'crystals', 'Crystals', 'Healing stones for balance and clarity.', 'media/cat-crystals.jpg'),
(3, 'incense', 'Incense', 'Aromatics for cleansing and ritual.', 'media/cat-incense.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `content_blocks`
--

CREATE TABLE `content_blocks` (
  `id` int(10) UNSIGNED NOT NULL,
  `page_slug` varchar(80) NOT NULL,
  `block_key` varchar(80) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `media_path` varchar(255) DEFAULT NULL,
  `cta_label` varchar(120) DEFAULT NULL,
  `cta_url` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `content_blocks`
--

INSERT INTO `content_blocks` (`id`, `page_slug`, `block_key`, `title`, `subtitle`, `body`, `media_path`, `cta_label`, `cta_url`, `sort_order`) VALUES
(1, 'home', 'hero', 'Discover the Power of Crystals & Rudraksha', 'Balance your mind, body & soul with sacred collections', 'Curated malas, crystals and incense to anchor your daily rituals.', 'media/hero1.jpg', 'Shop Now', 'shop.php', 1),
(2, 'home', 'category-intro', 'Shop by Category', NULL, 'Explore crystals, Rudraksha malas and calming incense. Each piece is sourced with care and intention.', NULL, NULL, NULL, 2),
(3, 'home', 'featured-title', 'Featured Products', NULL, 'Best loved pieces from the RudraBlessings community.', NULL, NULL, NULL, 3),
(4, 'home', 'new-arrivals', 'New Arrivals', NULL, 'Fresh energy for your altar and practice.', NULL, NULL, NULL, 4),
(5, 'about', 'hero', 'About RudraBlessings', 'We make mindful rituals feel welcoming and grounded.', 'At RudraBlessings we curate Rudraksha malas, natural crystals, incense and decor that connect tradition with modern living.', NULL, NULL, NULL, 1),
(6, 'about', 'story', 'Our Story', NULL, 'We started as a tiny collection of sacred malas and have grown into a curated store serving seekers across Australia. Every product is selected with care, tested in our own practice, and prepared with intention before it leaves our studio.', 'media/products/crystals/clear-quartz/image3.jpg', NULL, NULL, 2),
(7, 'about', 'values', 'Our Values', NULL, '<ul><li><strong>Authenticity:</strong> Every Rudraksha and crystal is sourced responsibly.</li><li><strong>Sustainability:</strong> We support mindful materials and low-waste packaging.</li><li><strong>Community:</strong> Spiritual practice is for everyone and we keep it welcoming.</li><li><strong>Craftsmanship:</strong> We collaborate with artisans who honour timeless traditions.</li></ul>', NULL, NULL, NULL, 3),
(8, 'about', 'promise', 'Our Promise', NULL, 'Shopping with RudraBlessings means inviting heritage, devotion, and positive energy into your daily life.', NULL, NULL, NULL, 4),
(9, 'about', 'cta', 'Join Our Journey', NULL, 'Whether you are beginning a meditation practice or gifting someone a symbol of good energy, we are here to guide you.', NULL, 'Browse Shop', 'shop.php', 5),
(10, 'contact', 'hero', 'Contact Us', 'Questions, wholesale enquiries, partnerships or just say hi - we respond within 24 hours.', NULL, NULL, NULL, NULL, 1),
(11, 'contact', 'form-note', NULL, NULL, 'By sending this message you agree to our <a href=\"terms.php\">Terms</a>.', NULL, NULL, NULL, 2),
(12, 'shipping', 'hero', 'Shipping & Returns', 'Clear timelines, fair policies, and easy returns so you can shop with confidence.', NULL, NULL, NULL, NULL, 1),
(13, 'shipping', 'processing', 'Order Processing', NULL, 'Orders are typically processed within <strong>1-2 business days</strong>. During launches or holiday periods, processing may take an additional 1-2 days. You\'ll receive an email confirmation once your order is placed and another when it ships.', NULL, NULL, NULL, 2),
(14, 'shipping', 'rates', 'Shipping Times & Rates', NULL, '<ul><li><strong>Australia (Standard):</strong> 3-7 business days</li><li><strong>Australia (Express):</strong> 1-3 business days</li><li><strong>International:</strong> 7-18 business days (country dependent)</li></ul><p>Shipping rates are calculated at checkout based on weight and destination. Free shipping promotions (when available) will be applied automatically.</p>', NULL, NULL, NULL, 3),
(15, 'shipping', 'tracking', 'Tracking', NULL, 'Once shipped, you\'ll receive a tracking link via email. It may take up to 24 hours for the carrier to update tracking details.', NULL, NULL, NULL, 4),
(16, 'shipping', 'returns', 'Returns & Exchanges', NULL, '<p>We accept returns on eligible items within <strong>30 days</strong> of delivery. Items must be unused, in original packaging, and include any accessories or gifts received with the order.</p><ul><li>For hygienic reasons, opened incense and worn mala or bracelet items cannot be returned.</li><li>Custom or personalised items are final sale unless defective.</li></ul><p>To start a return, email <a href=\"mailto:support@rudrablessings.com\">support@rudrablessings.com</a> with your order number and reason. We\'ll respond within one business day.</p>', NULL, NULL, NULL, 5),
(17, 'shipping', 'refunds', 'Refunds', NULL, 'Refunds are issued to the original payment method within <strong>5-10 business days</strong> after your return is received and inspected. Shipping fees are non-refundable unless we made an error.', NULL, NULL, NULL, 6),
(18, 'shipping', 'damaged', 'Damaged or Missing Items', NULL, 'If your package arrives damaged or an item is missing, contact us within <strong>7 days</strong> of delivery with photos of the outer packaging and the product. We\'ll prioritise replacements or refunds as appropriate.', NULL, NULL, NULL, 7),
(19, 'shipping', 'support', 'Questions?', NULL, 'Email <a href=\"mailto:support@rudrablessings.com\">support@rudrablessings.com</a> or use our <a href=\"contact.php\">contact form</a>. We\'re here to help.', NULL, NULL, NULL, 8),
(20, 'privacy', 'hero', 'Privacy Policy', 'We value your trust. This page explains what we collect, why we collect it, and how we protect it.', NULL, NULL, NULL, NULL, 1),
(21, 'privacy', 'collect', 'Information We Collect', NULL, '<ul><li><strong>Account and contact:</strong> name, email, shipping address, phone (optional).</li><li><strong>Order details:</strong> items purchased and payment method tokens supplied by our payment gateway. We never store full card numbers.</li><li><strong>Technical:</strong> IP address, device, pages viewed, and basic analytics.</li></ul>', NULL, NULL, NULL, 2),
(22, 'privacy', 'use', 'How We Use Information', NULL, '<ul><li>Process, deliver, and support your orders.</li><li>Improve site experience, product selection, and customer support.</li><li>Send important notifications such as order updates or policy changes. Marketing messages are optional and include an unsubscribe link.</li></ul>', NULL, NULL, NULL, 3),
(23, 'privacy', 'cookies', 'Cookies and Tracking', NULL, '<p>We use cookies to keep your cart, remember preferences, and understand site performance. You can manage cookies in your browser settings, though some features may not work without them.</p>', NULL, NULL, NULL, 4),
(24, 'privacy', 'share', 'Sharing and Disclosure', NULL, '<p>We share data only with trusted providers who help us run the store, such as payment processors, shipping carriers, and analytics tools. We do not sell your personal information.</p>', NULL, NULL, NULL, 5),
(25, 'privacy', 'security', 'Security', NULL, '<p>We implement reasonable technical and organisational safeguards, including HTTPS, limited access, and vetted third-party processors. While no method is perfect, we work hard to protect your information.</p>', NULL, NULL, NULL, 6),
(26, 'privacy', 'rights', 'Your Rights', NULL, '<p>You can request access to, correction of, or deletion of your data where applicable. You may also object to or restrict processing. Contact us to exercise these rights.</p>', NULL, NULL, NULL, 7),
(27, 'privacy', 'retention', 'Data Retention', NULL, '<p>We retain information as long as necessary to provide services and meet legal obligations. Order records may be retained for tax and audit requirements.</p>', NULL, NULL, NULL, 8),
(28, 'privacy', 'updates', 'Policy Updates', NULL, '<p>We may update this policy from time to time. Material changes will be communicated by email or highlighted on the site.</p>', NULL, NULL, NULL, 9),
(29, 'privacy', 'contact', 'Contact', NULL, '<p>Email <a href=\"mailto:support@rudrablessings.com\">support@rudrablessings.com</a> or use our <a href=\"contact.php\">contact form</a> for privacy questions or requests.</p>', NULL, NULL, NULL, 10),
(30, 'terms', 'hero', 'Terms and Conditions', 'Please review these terms before using RudraBlessings or placing an order.', NULL, NULL, NULL, NULL, 1),
(31, 'terms', 'agreement', 'Agreement to Terms', NULL, '<p>By using this website you agree to these terms and to our Privacy Policy. If you do not agree, do not use the site.</p>', NULL, NULL, NULL, 2),
(32, 'terms', 'eligibility', 'Eligibility and Account', NULL, '<p>You must be at least 18 years old to create an account or place an order. You are responsible for keeping your account credentials secure.</p>', NULL, NULL, NULL, 3),
(33, 'terms', 'orders', 'Orders and Payment', NULL, '<p>All orders are subject to acceptance and availability. Prices are listed in AUD and include GST where applicable. Payment is processed securely through our payment partners.</p>', NULL, NULL, NULL, 4),
(34, 'terms', 'shipping', 'Shipping and Delivery', NULL, '<p>We ship within Australia using tracked services. Estimated delivery times are provided at checkout, but delays beyond our control may occur.</p>', NULL, NULL, NULL, 5),
(35, 'terms', 'returns', 'Returns and Exchanges', NULL, '<p>If a product is faulty or arrives damaged, contact us within 14 days for a replacement or refund. Change of mind returns are accepted for unused items within 14 days; shipping costs are non-refundable.</p>', NULL, NULL, NULL, 6),
(36, 'terms', 'liability', 'Limitation of Liability', NULL, '<p>To the extent permitted by law, RudraBlessings is not liable for indirect, incidental, or consequential damages arising from the use of the site or products.</p>', NULL, NULL, NULL, 7),
(37, 'terms', 'updates', 'Changes to Terms', NULL, '<p>We may update these terms when needed. Updated versions take effect when posted on the website.</p>', NULL, NULL, NULL, 8),
(38, 'terms', 'contact', 'Contact', NULL, '<p>Questions about these terms? Email <a href=\"mailto:support@rudrablessings.com\">support@rudrablessings.com</a>.</p>', NULL, NULL, NULL, 9),
(39, 'footer', 'legal', 'RudraBlessings', NULL, '&copy; 2025 RudraBlessings - Crafted with love in Australia.', NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `menu_key` varchar(40) NOT NULL,
  `label` varchar(120) NOT NULL,
  `url` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `menu_key`, `label`, `url`, `sort_order`, `is_active`) VALUES
(1, 'primary', 'Home', 'index.php', 1, 1),
(2, 'primary', 'Shop', 'shop.php', 2, 1),
(3, 'primary', 'About', 'about.php', 3, 1),
(4, 'primary', 'Blog', 'blog.php', 4, 1),
(5, 'primary', 'Contact', 'contact.php', 5, 1),
(6, 'primary', 'Sign in', 'signin.php', 6, 1),
(7, 'footer_shop', 'Rudraksha', 'shop.php?cat=rudraksha', 1, 1),
(8, 'footer_shop', 'Crystals', 'shop.php?cat=crystals', 2, 1),
(9, 'footer_shop', 'Incense', 'shop.php?cat=incense', 3, 1),
(11, 'footer_company', 'About', 'about.php', 1, 1),
(12, 'footer_company', 'Blog', 'blog.php', 2, 1),
(13, 'footer_company', 'Contact', 'contact.php', 3, 1),
(14, 'footer_company', 'Shipping & Returns', 'shipping.php', 4, 1),
(15, 'footer_legal', 'Terms & Conditions', 'terms.php', 1, 1),
(16, 'footer_legal', 'Privacy Policy', 'privacy.php', 2, 1),
(17, 'footer_legal', 'Track My Order', 'tracking.php', 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `address_id` int(10) UNSIGNED DEFAULT NULL,
  `order_number` varchar(30) NOT NULL,
  `status` enum('pending','paid','shipped','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `subtotal` decimal(10,2) NOT NULL,
  `shipping` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL,
  `placed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `address_id`, `order_number`, `status`, `subtotal`, `shipping`, `tax`, `grand_total`, `placed_at`) VALUES
(1, 2, 1, 'RB-10001', 'completed', 129.98, 9.95, 0.00, 139.93, '2025-02-14 09:32:00'),
(2, 2, 2, 'RB-10002', 'shipped', 81.99, 12.50, 0.00, 94.49, '2025-03-07 16:48:00'),
(3, 2, 1, 'RB-10003', 'paid', 49.99, 9.95, 0.00, 59.94, '2025-04-25 11:12:00'),
(4, 1, 3, 'RB-251016-0AF6D0', 'pending', 149.97, 0.00, 0.00, 149.97, '2025-10-16 14:15:58');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `cost_price`, `total_price`) VALUES
(1, 1, 5, 'Amethyst Crystal', 1, 29.99, 14.99, 29.99),
(2, 1, 6, 'Rose Quartz', 1, 24.99, 9.99, 24.99),
(3, 1, 11, 'Crystal Energy Kit', 1, 59.99, 44.99, 59.99),
(4, 2, 7, 'Citrine', 1, 22.50, 7.50, 22.50),
(5, 2, 8, 'Clear Quartz', 2, 18.00, 3.00, 36.00),
(6, 2, 12, 'Sandalwood Incense', 1, 19.99, 4.99, 19.99),
(7, 3, 1, '5 Mukhi Rudraksha Mala', 1, 49.99, 34.99, 49.99),
(8, 4, 1, '5 Mukhi Rudraksha Mala', 3, 49.99, 34.99, 149.97);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(150) NOT NULL,
  `name` varchar(200) NOT NULL,
  `short_description` text NOT NULL,
  `description` longtext DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 25,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_new_arrival` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `slug`, `name`, `short_description`, `description`, `price`, `cost_price`, `stock`, `status`, `is_featured`, `is_new_arrival`, `created_at`, `updated_at`) VALUES
(1, '5-mukhi-rudraksha-mala', '5 Mukhi Rudraksha Mala', 'Hand-knotted 108 beads that bring balance, discipline and spiritual focus.', '<p>Each mala is strung by hand using five-faced Rudraksha beads, finished with a soft tassel and blessed before shipping.</p>', 49.99, 34.99, 37, 'active', 1, 0, '2025-10-16 13:21:00', '2025-10-16 14:15:58'),
(2, 'rudraksha-wrist-strand', 'Rudraksha Wrist Strand', '27-bead wrist mala for daily grounding and quick mantra sets.', '<p>A compact wrist mala perfect for travel or on-the-go practice, finished with adjustable cord.</p>', 26.00, 11.00, 55, 'active', 1, 0, '2025-10-16 13:21:00', '2025-10-16 14:11:41'),
(3, 'gold-capped-rudraksha', 'Gold-Capped Rudraksha', 'Polished bead with gold caps for ceremonial elegance.', '<p>A single Rudraksha bead embellished with gold-toned caps, ready to wear or add to your altar.</p>', 79.00, 64.00, 20, 'active', 1, 0, '2025-10-16 13:21:00', '2025-10-16 14:11:46'),
(4, 'rudraksha-meditation-beads', 'Rudraksha Meditation Beads', 'Smooth, lightweight beads perfect for mantra practice.', '<p>Crafted for long sittings, this mala features lightweight beads and durable thread.</p>', 34.00, 19.00, 35, 'active', 1, 0, '2025-10-16 13:21:00', '2025-10-16 14:11:51'),
(5, 'amethyst-crystal', 'Amethyst Crystal', 'Soothing, protective, perfect for meditation and better sleep.', '<p>Natural amethyst cluster that invites stillness and supports restful sleep.</p>', 29.99, 14.99, 30, 'active', 0, 1, '2025-10-16 13:21:00', '2025-10-16 13:21:00'),
(6, 'rose-quartz', 'Rose Quartz', 'Heart chakra stone for compassion, self-love and harmony.', '<p>Rose quartz polished palm stone to open the heart and soften the day.</p>', 24.99, 9.99, 45, 'active', 0, 1, '2025-10-16 13:21:00', '2025-10-16 13:21:00'),
(7, 'citrine', 'Citrine', 'Manifestation stone for abundance, clarity and optimism.', '<p>Sunny citrine point that brightens any space and supports confident action.</p>', 22.50, 7.50, 28, 'active', 1, 0, '2025-10-16 13:21:00', '2025-10-16 14:11:57'),
(8, 'clear-quartz', 'Clear Quartz', 'Amplifier stone to clarify intention and focus.', '<p>Clear quartz tower cleansed and charged, ready to amplify your rituals.</p>', 18.00, 3.00, 50, 'active', 1, 0, '2025-10-16 13:21:00', '2025-10-16 14:12:03'),
(9, 'labradorite-palm-stone', 'Labradorite Palm Stone', 'Iridescent sheen to support insight and creativity.', '<p>A palm-sized labradorite stone with vivid flash, perfect for meditation.</p>', 27.00, 12.00, 32, 'active', 0, 1, '2025-10-16 13:21:00', '2025-10-16 14:12:08'),
(10, 'black-tourmaline', 'Black Tourmaline', 'Grounding shield stone for protection and stability.', '<p>Chunky black tourmaline for energetic boundaries and security.</p>', 21.00, 6.00, 38, 'active', 0, 1, '2025-10-16 13:21:00', '2025-10-16 14:12:14'),
(11, 'crystal-energy-kit', 'Crystal Energy Kit', 'Curated set for daily grounding and intention setting.', '<p>A balanced set of four stones with a guide to daily rituals.</p>', 59.99, 44.99, 25, 'active', 0, 1, '2025-10-16 13:21:00', '2025-10-16 14:12:19'),
(12, 'sandalwood-incense', 'Sandalwood Incense', 'Warm, woody aroma for cleansing rituals.', '<p>Hand-rolled incense sticks infused with pure sandalwood.</p>', 19.99, 4.99, 60, 'active', 0, 1, '2025-10-16 13:21:00', '2025-10-16 14:12:24'),
(13, 'brass-buddha-statue', 'Brass Buddha Statue', 'Solid brass statue to anchor peace and presence.', '<p>Cast brass Buddha idol polished to a warm glow, ideal for an altar centerpiece.</p>', 89.99, 74.99, 12, 'active', 0, 1, '2025-10-16 13:21:00', '2025-10-16 14:12:29');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`product_id`, `category_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 2),
(6, 2),
(7, 2),
(8, 2),
(9, 2),
(10, 2),
(11, 2),
(12, 3),
(13, 2);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `alt_text`, `sort_order`) VALUES
(16, 5, 'media/products/crystals/amethyst-crystal/image1.jpg', 'Amethyst Crystal - main', 1),
(17, 5, 'media/products/crystals/amethyst-crystal/image2.jpg', 'Amethyst Crystal - image 2', 2),
(18, 5, 'media/products/crystals/amethyst-crystal/image3.jpg', 'Amethyst Crystal - image 3', 3),
(19, 5, 'media/products/crystals/amethyst-crystal/image4.jpg', 'Amethyst Crystal - image 4', 4),
(20, 5, 'media/products/crystals/amethyst-crystal/image5.jpg', 'Amethyst Crystal - image 5', 5),
(21, 6, 'media/products/crystals/rose-quartz/image1.jpg', 'Rose Quartz - main', 1),
(22, 6, 'media/products/crystals/rose-quartz/image2.jpg', 'Rose Quartz - image 2', 2),
(23, 6, 'media/products/crystals/rose-quartz/image3.jpg', 'Rose Quartz - image 3', 3),
(24, 6, 'media/products/crystals/rose-quartz/image4.jpg', 'Rose Quartz - image 4', 4),
(25, 6, 'media/products/crystals/rose-quartz/image5.jpg', 'Rose Quartz - image 5', 5),
(64, 1, 'media/products/5-mukhi-rudraksha-mala/5-mukhi-rudraksha-mala-20251016050933-4-bbd9a8af.webp', '5 Mukhi Rudraksha Mala - image 5', 1),
(65, 1, 'media/products/rudraksha/5-mukhi-rudraksha-mala/image1.jpg', '5 Mukhi Rudraksha Mala - main', 2),
(66, 1, 'media/products/rudraksha/5-mukhi-rudraksha-mala/image2.jpg', '5 Mukhi Rudraksha Mala - image 2', 3),
(67, 1, 'media/products/rudraksha/5-mukhi-rudraksha-mala/image3.jpg', '5 Mukhi Rudraksha Mala - image 3', 4),
(68, 1, 'media/products/rudraksha/5-mukhi-rudraksha-mala/image4.jpg', '5 Mukhi Rudraksha Mala - image 4', 5),
(69, 2, 'media/products/rudraksha/rudraksha-wrist-strand/image1.jpg', 'Rudraksha Wrist Strand - main', 1),
(70, 2, 'media/products/rudraksha/rudraksha-wrist-strand/image5.jpg', 'Rudraksha Wrist Strand - image 2', 2),
(71, 3, 'media/products/rudraksha/gold-capped-rudraksha/image1.jpg', 'Gold-Capped Rudraksha - main', 1),
(72, 3, 'media/products/rudraksha/gold-capped-rudraksha/image2.jpg', 'Gold-Capped Rudraksha - image 2', 2),
(73, 3, 'media/products/rudraksha/gold-capped-rudraksha/image3.jpg', 'Gold-Capped Rudraksha - image 3', 3),
(74, 4, 'media/products/rudraksha/rudraksha-meditation-beads/image1.jpg', 'Rudraksha Meditation Beads - main', 1),
(75, 4, 'media/products/rudraksha/rudraksha-meditation-beads/image2.jpg', 'Rudraksha Meditation Beads - image 2', 2),
(76, 4, 'media/products/rudraksha/rudraksha-meditation-beads/image3.jpg', 'Rudraksha Meditation Beads - image 3', 3),
(77, 4, 'media/products/rudraksha/rudraksha-meditation-beads/image5.jpg', 'Rudraksha Meditation Beads - image 4', 4),
(78, 7, 'media/products/crystals/citrine/image1.jpg', 'Citrine - main', 1),
(79, 7, 'media/products/crystals/citrine/image2.jpg', 'Citrine - image 2', 2),
(80, 7, 'media/products/crystals/citrine/image3.jpg', 'Citrine - image 3', 3),
(81, 7, 'media/products/crystals/citrine/image5.jpg', 'Citrine - image 4', 4),
(82, 8, 'media/products/crystals/clear-quartz/image1.jpg', 'Clear Quartz - main', 1),
(83, 8, 'media/products/crystals/clear-quartz/image2.jpg', 'Clear Quartz - image 2', 2),
(84, 8, 'media/products/crystals/clear-quartz/image3.jpg', 'Clear Quartz - image 3', 3),
(85, 8, 'media/products/crystals/clear-quartz/image5.jpg', 'Clear Quartz - image 4', 4),
(86, 9, 'media/products/crystals/labradorite-palm-stone/image1.jpg', 'Labradorite Palm Stone - main', 1),
(87, 9, 'media/products/crystals/labradorite-palm-stone/image2.jpg', 'Labradorite Palm Stone - image 2', 2),
(88, 9, 'media/products/crystals/labradorite-palm-stone/image3.jpg', 'Labradorite Palm Stone - image 3', 3),
(89, 9, 'media/products/crystals/labradorite-palm-stone/image4.jpg', 'Labradorite Palm Stone - image 4', 4),
(90, 9, 'media/products/crystals/labradorite-palm-stone/image5.jpg', 'Labradorite Palm Stone - image 5', 5),
(91, 10, 'media/products/crystals/black-tourmaline/image1.jpg', 'Black Tourmaline - main', 1),
(92, 10, 'media/products/crystals/black-tourmaline/image2.jpg', 'Black Tourmaline - image 2', 2),
(93, 10, 'media/products/crystals/black-tourmaline/image3.jpg', 'Black Tourmaline - image 3', 3),
(94, 10, 'media/products/crystals/black-tourmaline/image4.jpg', 'Black Tourmaline - image 4', 4),
(95, 10, 'media/products/crystals/black-tourmaline/image5.jpg', 'Black Tourmaline - image 5', 5),
(96, 11, 'media/products/crystals/crystal-energy-kit/image1.jpg', 'Crystal Energy Kit - main', 1),
(97, 11, 'media/products/crystals/crystal-energy-kit/image2.jpg', 'Crystal Energy Kit - image 2', 2),
(98, 11, 'media/products/crystals/crystal-energy-kit/image3.jpg', 'Crystal Energy Kit - image 3', 3),
(99, 11, 'media/products/crystals/crystal-energy-kit/image4.jpg', 'Crystal Energy Kit - image 4', 4),
(100, 11, 'media/products/crystals/crystal-energy-kit/image5.jpg', 'Crystal Energy Kit - image 5', 5),
(101, 12, 'media/products/incense/sandalwood-incense/image1.jpg', 'Sandalwood Incense - main', 1),
(102, 12, 'media/products/incense/sandalwood-incense/image2.jpg', 'Sandalwood Incense - image 2', 2),
(103, 12, 'media/products/incense/sandalwood-incense/image3.jpg', 'Sandalwood Incense - image 3', 3),
(104, 12, 'media/products/incense/sandalwood-incense/image4.jpg', 'Sandalwood Incense - image 4', 4),
(105, 12, 'media/products/incense/sandalwood-incense/image5.jpg', 'Sandalwood Incense - image 5', 5),
(106, 13, 'media/products/decor/brass-buddha-statue/image1.jpg', 'Brass Buddha Statue - main', 1),
(107, 13, 'media/products/decor/brass-buddha-statue/image2.jpg', 'Brass Buddha Statue - image 2', 2),
(108, 13, 'media/products/decor/brass-buddha-statue/image3.jpg', 'Brass Buddha Statue - image 3', 3),
(109, 13, 'media/products/decor/brass-buddha-statue/image4.jpg', 'Brass Buddha Statue - image 4', 4),
(110, 13, 'media/products/decor/brass-buddha-statue/image5.jpg', 'Brass Buddha Statue - image 5', 5);

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(120) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'brand.name', 'RudraBlessings', '2025-10-16 13:21:00'),
(2, 'brand.tagline', 'Sacred goods for mindful living', '2025-10-16 13:21:00'),
(3, 'support.email', 'support@rudrablessings.com', '2025-10-16 13:21:00'),
(4, 'support.phone', '0410462468', '2025-10-16 13:21:00'),
(5, 'store.currency', 'AUD', '2025-10-16 13:21:00'),
(6, 'support.address', 'VIT Sydney, 540 George St, Sydney NSW', '2025-10-16 13:21:00'),
(7, 'support.hours', 'Mon-Fri: 9:00 - 18:00<br>Sat: 10:00 - 16:00<br>Sun &amp; public holidays: Closed', '2025-10-16 13:21:00'),
(8, 'social.instagram', 'https://instagram.com/rudrablessings', '2025-10-16 13:21:00'),
(9, 'social.facebook', 'https://facebook.com/rudrablessings', '2025-10-16 13:21:00'),
(10, 'social.youtube', 'https://youtube.com/@rudrablessings', '2025-10-16 13:21:00'),
(11, 'contact.map_embed', 'https://www.google.com/maps?q=Victorian%20Institute%20of%20Technology%20(VIT)%20Sydney&output=embed', '2025-10-16 13:21:00'),
(12, 'contact.hero.title', 'Contact Us', '2025-10-16 13:21:00'),
(13, 'contact.hero.subtitle', 'Questions, wholesale enquiries, partnerships or just say hi - we respond within 24 hours.', '2025-10-16 13:21:00'),
(14, 'blog.meta.description', 'RudraBlessings blog: guides on Rudraksha, crystals, incense and spiritual living.', '2025-10-16 13:21:00'),
(15, 'blog.default_image', 'media/blog1.jpg', '2025-10-16 13:21:00'),
(16, 'blog.admin_bio', 'Sharing grounded practices.', '2025-10-16 13:21:00'),
(17, 'footer.shop.title', 'Shop', '2025-10-16 13:21:00'),
(18, 'footer.company.title', 'Company', '2025-10-16 13:21:00'),
(19, 'footer.legal.title', 'Legal', '2025-10-16 13:21:00'),
(20, 'shipping.last_updated', '2025-09-21', '2025-10-16 13:21:00'),
(21, 'privacy.last_updated', '2025-09-21', '2025-10-16 13:21:00'),
(22, 'terms.last_updated', '2025-09-21', '2025-10-16 13:21:00');

-- --------------------------------------------------------

--
-- Table structure for table `contact_channels`
--

CREATE TABLE `contact_channels` (
  `id` int(10) UNSIGNED NOT NULL,
  `icon` varchar(40) NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text NOT NULL,
  `cta_label` varchar(190) DEFAULT NULL,
  `cta_url` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_channels`
--

INSERT INTO `contact_channels` (`id`, `icon`, `title`, `description`, `cta_label`, `cta_url`, `sort_order`, `created_at`) VALUES
(1, 'envelope', 'Email Support', 'Need help with an order or custom build? We reply within a business day.', 'support@rudrablessings.com', 'mailto:support@rudrablessings.com', 1, '2025-10-16 13:21:00'),
(2, 'phone', 'Phone', 'Prefer to chat? Leave a message and we will call you back within 6 hours.', '0410462468', 'tel:0410462468', 2, '2025-10-16 13:21:00');

-- --------------------------------------------------------

--
-- Table structure for table `contact_faqs`
--

CREATE TABLE `contact_faqs` (
  `id` int(10) UNSIGNED NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_faqs`
--

INSERT INTO `contact_faqs` (`id`, `question`, `answer`, `sort_order`, `created_at`) VALUES
(1, 'How quickly will I hear back?', 'We reply to most enquiries within 24 hours Monday to Friday. Urgent shipping questions are prioritised.', 1, '2025-10-16 13:21:00'),
(2, 'Do you ship internationally?', 'Yes! We currently ship to 40+ countries. Include your city and postal code so we can confirm delivery estimates and customs notes.', 2, '2025-10-16 13:21:00'),
(3, 'Can I visit your studio?', 'We host private studio visits by appointment in Hurstville. Share your preferred date and we will confirm availability.', 3, '2025-10-16 13:21:00');

-- --------------------------------------------------------

--
-- Table structure for table `contact_highlights`
--

CREATE TABLE `contact_highlights` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `body` text NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_highlights`
--

INSERT INTO `contact_highlights` (`id`, `title`, `body`, `sort_order`, `created_at`) VALUES
(1, 'Wholesale & Partnerships', 'Curating for a studio, spa, or boutique? We offer flexible wholesale bundles, private label options, and facilitator training.', 1, '2025-10-16 13:21:00'),
(2, 'Custom Ritual Kits', 'From wedding favours to corporate gifting, we co-create personalised ritual kits aligned to your story and intention.', 2, '2025-10-16 13:21:00'),
(3, 'Community Circles', 'Seeking a guided meditation or workshop for your community? Share your idea and we will craft the experience with you.', 3, '2025-10-16 13:21:00');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `subject` varchar(190) DEFAULT NULL,
  `message` text NOT NULL,
  `reply_to` varchar(190) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `mail_status` varchar(32) NOT NULL DEFAULT 'pending',
  `mail_error` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_message_replies`
--

CREATE TABLE `contact_message_replies` (
  `id` int(10) UNSIGNED NOT NULL,
  `contact_message_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `subject` varchar(190) NOT NULL,
  `body` text NOT NULL,
  `mail_status` varchar(32) NOT NULL DEFAULT 'queued',
  `mail_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_stats`
--

CREATE TABLE `contact_stats` (
  `id` int(10) UNSIGNED NOT NULL,
  `metric_value` varchar(50) NOT NULL,
  `metric_label` varchar(190) NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_stats`
--

INSERT INTO `contact_stats` (`id`, `metric_value`, `metric_label`, `sort_order`, `created_at`) VALUES
(1, '24h', 'Average response time', 1, '2025-10-16 13:21:00'),
(2, '5k+', 'Customers supported worldwide', 2, '2025-10-16 13:21:00'),
(3, '7', 'Time zones covered by our team', 3, '2025-10-16 13:21:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `marketing_emails` tinyint(1) NOT NULL DEFAULT 1,
  `order_updates` tinyint(1) NOT NULL DEFAULT 1,
  `sms_alerts` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_login_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_attempt_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `password_updated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `email`, `password_hash`, `email_verified_at`, `first_name`, `last_name`, `avatar_path`, `is_active`, `failed_login_attempts`, `locked_until`, `last_login_attempt_at`, `last_login_at`, `password_updated_at`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@rudrablessings.com', '$2y$10$N5yxIJCDb18N7KjjD2HvyuqZeTMcV73SXok3CPSr8t5Gs.TV6FfKO', '2025-10-16 13:21:00', 'Admin', 'User', 'media/admin-avatar.svg', 1, 0, NULL, NULL, NULL, '2025-10-16 13:21:00', '2025-10-16 13:21:00', '2025-10-16 13:21:00', '2025-10-16 13:21:00'),
(2, 'customer', 'guest@example.com', '$2y$10$Fz2Xu6Q1l2PpXGa6Ipi.YepbqsSLIV6uh1srO2Qd6hwzEwA9H9n3K', '2025-10-16 13:21:00', 'Guest', 'Customer', NULL, 1, 0, NULL, NULL, NULL, '2025-10-16 13:21:00', '2025-10-16 13:21:00', '2025-10-16 13:21:00', '2025-10-16 13:21:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `type` enum('verify','password_reset') NOT NULL,
  `meta` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `created_at`) VALUES
(1, 1, '2025-10-16 13:22:52');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist_items`
--

CREATE TABLE `wishlist_items` (
  `wishlist_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `added_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token_unique` (`session_token`),
  ADD UNIQUE KEY `cart_user_unique` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_id` (`cart_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `content_blocks`
--
ALTER TABLE `content_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_block` (`page_slug`,`block_key`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `address_id` (`address_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`product_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `contact_channels`
--
ALTER TABLE `contact_channels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_faqs`
--
ALTER TABLE `contact_faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_highlights`
--
ALTER TABLE `contact_highlights`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_created_idx` (`email`,`created_at`);

--
-- Indexes for table `contact_message_replies`
--
ALTER TABLE `contact_message_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_idx` (`contact_message_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `contact_stats`
--
ALTER TABLE `contact_stats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_unique` (`token`),
  ADD KEY `user_type_idx` (`user_id`,`type`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_unique` (`user_id`);

--
-- Indexes for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD PRIMARY KEY (`wishlist_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `content_blocks`
--
ALTER TABLE `content_blocks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `contact_channels`
--
ALTER TABLE `contact_channels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `contact_faqs`
--
ALTER TABLE `contact_faqs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contact_highlights`
--
ALTER TABLE `contact_highlights`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `contact_message_replies`
--
ALTER TABLE `contact_message_replies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `contact_stats`
--
ALTER TABLE `contact_stats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `product_categories_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD CONSTRAINT `user_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_message_replies`
--
ALTER TABLE `contact_message_replies`
  ADD CONSTRAINT `contact_replies_message_fk` FOREIGN KEY (`contact_message_id`) REFERENCES `contact_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contact_replies_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD CONSTRAINT `wishlist_items_ibfk_1` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
