<?php
declare(strict_types=1);

if (!function_exists('rb_run_migrations')) {
    function rb_run_migrations(PDO $pdo): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        $ensureColumn = function (string $table, string $column, string $definition) use ($pdo): void {
            $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $columnSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
            if ($tableSafe === '' || $columnSafe === '') {
                return;
            }
            $checkSql = sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $tableSafe, $columnSafe);
            $exists = $pdo->query($checkSql)->fetch();
            if (!$exists) {
                $alterSql = sprintf("ALTER TABLE `%s` ADD COLUMN `%s` %s", $tableSafe, $columnSafe, $definition);
                $pdo->exec($alterSql);
            }
        };

        $ensureColumn('products', 'is_featured', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
        $ensureColumn('products', 'is_new_arrival', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_featured`");
        $ensureColumn('categories', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1 AFTER `image_path`");
        $ensureColumn('users', 'email_verified_at', "DATETIME NULL AFTER `password_hash`");
        $ensureColumn('users', 'failed_login_attempts', "SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0 AFTER `is_active`");
        $ensureColumn('users', 'locked_until', "DATETIME NULL AFTER `failed_login_attempts`");
        $ensureColumn('users', 'last_login_attempt_at', "DATETIME NULL AFTER `locked_until`");
        $ensureColumn('users', 'last_login_at', "DATETIME NULL AFTER `last_login_attempt_at`");
        $ensureColumn('users', 'password_updated_at', "DATETIME NULL AFTER `last_login_at`");
        $ensureColumn('orders', 'promo_code', "VARCHAR(32) NULL AFTER `tax`");
        $ensureColumn('orders', 'promo_discount', "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `promo_code`");
        $ensureColumn('orders', 'promo_free_shipping', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `promo_discount`");

        $featureCount = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_featured = 1')->fetchColumn();
        if ($featureCount === 0) {
            $pdo->exec('UPDATE products SET is_featured = 1 WHERE id IN (SELECT id FROM (SELECT id FROM products ORDER BY created_at ASC LIMIT 4) AS tmp)');
        }

        $newArrivalCount = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_new_arrival = 1')->fetchColumn();
        if ($newArrivalCount === 0) {
            $pdo->exec('UPDATE products SET is_new_arrival = 1 WHERE id IN (SELECT id FROM (SELECT id FROM products ORDER BY created_at DESC LIMIT 8) AS tmp)');
        }

        $pdo->exec('UPDATE products SET cost_price = GREATEST(price - 15, 0) WHERE cost_price IS NULL OR cost_price <= 0');
        $pdo->exec('UPDATE order_items oi INNER JOIN products p ON p.id = oi.product_id SET oi.cost_price = GREATEST(p.cost_price, GREATEST(p.price - 15, 0)) WHERE oi.cost_price IS NULL OR oi.cost_price <= 0');

        $decorIdStmt = $pdo->prepare("SELECT id FROM categories WHERE slug = 'decor' LIMIT 1");
        $decorIdStmt->execute();
        $decorId = $decorIdStmt->fetchColumn();
        if ($decorId) {
            $decorId = (int)$decorId;
            $crystalId = (int)$pdo->query("SELECT id FROM categories WHERE slug = 'crystals' LIMIT 1")->fetchColumn();
            if ($crystalId) {
                $reassign = $pdo->prepare('UPDATE product_categories SET category_id = :new WHERE category_id = :old');
                $reassign->execute(['new' => $crystalId, 'old' => $decorId]);
            } else {
                $pdo->prepare('DELETE FROM product_categories WHERE category_id = :old')->execute(['old' => $decorId]);
            }
            $pdo->prepare('DELETE FROM categories WHERE id = :old')->execute(['old' => $decorId]);
        }

        $pdo->prepare("DELETE FROM menu_items WHERE menu_key = 'footer_shop' AND label = 'Decor'")->execute();

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `user_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token` CHAR(64) NOT NULL,
    `type` ENUM('verify','password_reset') NOT NULL,
    `meta` TEXT NULL,
    `expires_at` DATETIME NOT NULL,
    `consumed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `token_unique` (`token`),
    KEY `user_type_idx` (`user_id`, `type`),
    CONSTRAINT `user_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `promos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(32) NOT NULL,
    `type` ENUM('percent','amount','shipping') NOT NULL DEFAULT 'percent',
    `value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `label` VARCHAR(190) NOT NULL DEFAULT '',
    `min_subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_usage` INT UNSIGNED DEFAULT NULL,
    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `starts_at` DATETIME NULL,
    `ends_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `promo_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $promoCount = (int)$pdo->query('SELECT COUNT(*) FROM promos')->fetchColumn();
        if ($promoCount === 0) {
            $insertPromo = $pdo->prepare('INSERT INTO promos (code, type, value, label, min_subtotal, is_active) VALUES (:code, :type, :value, :label, :min_subtotal, :is_active)');
            $insertPromo->execute([
                'code' => 'SAVE10',
                'type' => 'percent',
                'value' => 10.00,
                'label' => 'SAVE10 applied - 10% off subtotal',
                'min_subtotal' => 0.00,
                'is_active' => 1,
            ]);
            $insertPromo->execute([
                'code' => 'FREESHIP',
                'type' => 'shipping',
                'value' => 0.00,
                'label' => 'Free standard shipping applied',
                'min_subtotal' => 0.00,
                'is_active' => 1,
            ]);
        }

        $pdo->exec('UPDATE users SET failed_login_attempts = 0 WHERE failed_login_attempts IS NULL OR failed_login_attempts < 0');
        $pdo->exec('UPDATE users SET locked_until = NULL WHERE locked_until IS NOT NULL AND locked_until < DATE_SUB(NOW(), INTERVAL 1 YEAR)');

        $ensureColumn('contact_messages', 'responded_at', "DATETIME NULL AFTER `mail_error`");

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `subject` VARCHAR(190) NULL,
    `message` TEXT NOT NULL,
    `reply_to` VARCHAR(190) NULL,
    `ip_address` VARCHAR(45) NULL,
    `mail_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `mail_error` TEXT NULL,
    `responded_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `email_created_idx` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `contact_message_replies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contact_message_id` INT UNSIGNED NOT NULL,
    `admin_id` INT UNSIGNED NULL,
    `subject` VARCHAR(190) NOT NULL,
    `body` TEXT NOT NULL,
    `mail_status` VARCHAR(32) NOT NULL DEFAULT 'queued',
    `mail_error` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `message_idx` (`contact_message_id`),
    CONSTRAINT `contact_replies_message_fk` FOREIGN KEY (`contact_message_id`) REFERENCES `contact_messages`(`id`) ON DELETE CASCADE,
    CONSTRAINT `contact_replies_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $phoneSelect = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'support.phone' LIMIT 1");
        $phoneSelect->execute();
        $currentPhone = (string)$phoneSelect->fetchColumn();
        $targetPhone = '0410462468';
        if ($currentPhone !== $targetPhone && $targetPhone !== '') {
            $phoneUpdate = $pdo->prepare("UPDATE site_settings SET setting_value = :phone WHERE setting_key = 'support.phone'");
            $phoneUpdate->execute(['phone' => $targetPhone]);
        }
        $phoneTel = 'tel:' . preg_replace('/[^0-9+]/', '', $targetPhone);

        $emailSelect = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'support.email' LIMIT 1");
        $emailSelect->execute();
        $supportEmail = (string)$emailSelect->fetchColumn();
        if ($supportEmail === '') {
            $supportEmail = 'support@rudrablessings.com';
        }

        $indexCheck = $pdo->query("SHOW INDEX FROM site_settings WHERE Key_name = 'setting_key'");
        if ($indexCheck && !$indexCheck->fetch()) {
            $pdo->exec('ALTER TABLE site_settings ADD UNIQUE KEY `setting_key` (`setting_key`)');
        }

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `contact_stats` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `metric_value` VARCHAR(50) NOT NULL,
    `metric_label` VARCHAR(190) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `contact_channels` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `icon` VARCHAR(40) NOT NULL,
    `title` VARCHAR(190) NOT NULL,
    `description` TEXT NOT NULL,
    `cta_label` VARCHAR(190) DEFAULT NULL,
    `cta_url` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `contact_highlights` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(190) NOT NULL,
    `body` TEXT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `contact_faqs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question` VARCHAR(255) NOT NULL,
    `answer` TEXT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $statCount = (int)$pdo->query('SELECT COUNT(*) FROM contact_stats')->fetchColumn();
        if ($statCount === 0) {
            $statInsert = $pdo->prepare('INSERT INTO contact_stats (metric_value, metric_label, sort_order) VALUES (:value, :label, :sort)');
            $defaults = [
                ['value' => '24h', 'label' => 'Average response time', 'sort' => 1],
                ['value' => '5k+', 'label' => 'Customers supported worldwide', 'sort' => 2],
                ['value' => '7', 'label' => 'Time zones covered by our team', 'sort' => 3],
            ];
            foreach ($defaults as $row) {
                $statInsert->execute($row);
            }
        }

        $channelCount = (int)$pdo->query('SELECT COUNT(*) FROM contact_channels')->fetchColumn();
        if ($channelCount === 0) {
            $channelInsert = $pdo->prepare('INSERT INTO contact_channels (icon, title, description, cta_label, cta_url, sort_order) VALUES (:icon, :title, :description, :cta_label, :cta_url, :sort)');
            $channelDefaults = [
                [
                    'icon' => 'envelope',
                    'title' => 'Email Support',
                    'description' => 'Need help with an order or custom build? We reply within a business day.',
                    'cta_label' => $supportEmail,
                    'cta_url' => 'mailto:' . $supportEmail,
                    'sort' => 1,
                ],
                [
                    'icon' => 'phone',
                    'title' => 'Phone',
                    'description' => 'Prefer to chat? Leave a message and we will call you back within 6 hours.',
                    'cta_label' => $targetPhone,
                    'cta_url' => $phoneTel,
                    'sort' => 2,
                ],
            ];
            foreach ($channelDefaults as $row) {
                $channelInsert->execute($row);
            }
        }

        $highlightCount = (int)$pdo->query('SELECT COUNT(*) FROM contact_highlights')->fetchColumn();
        if ($highlightCount === 0) {
            $highlightInsert = $pdo->prepare('INSERT INTO contact_highlights (title, body, sort_order) VALUES (:title, :body, :sort)');
            $highlightDefaults = [
                [
                    'title' => 'Wholesale & Partnerships',
                    'body' => 'Curating for a studio, spa, or boutique? We offer flexible wholesale bundles, private label options, and facilitator training.',
                    'sort' => 1,
                ],
                [
                    'title' => 'Custom Ritual Kits',
                    'body' => 'From wedding favours to corporate gifting, we co-create personalised ritual kits aligned to your story and intention.',
                    'sort' => 2,
                ],
                [
                    'title' => 'Community Circles',
                    'body' => 'Seeking a guided meditation or workshop for your community? Share your idea and we will craft the experience with you.',
                    'sort' => 3,
                ],
            ];
            foreach ($highlightDefaults as $row) {
                $highlightInsert->execute($row);
            }
        }

        $faqCount = (int)$pdo->query('SELECT COUNT(*) FROM contact_faqs')->fetchColumn();
        if ($faqCount === 0) {
            $faqInsert = $pdo->prepare('INSERT INTO contact_faqs (question, answer, sort_order) VALUES (:question, :answer, :sort)');
            $faqDefaults = [
                [
                    'question' => 'How quickly will I hear back?',
                    'answer' => 'We reply to most enquiries within 24 hours Monday to Friday. Urgent shipping questions are prioritised.',
                    'sort' => 1,
                ],
                [
                    'question' => 'Do you ship internationally?',
                    'answer' => 'Yes! We currently ship to 40+ countries. Include your city and postal code so we can confirm delivery estimates and customs notes.',
                    'sort' => 2,
                ],
                [
                    'question' => 'Can I visit your studio?',
                    'answer' => 'We host private studio visits by appointment in Hurstville. Share your preferred date and we will confirm availability.',
                    'sort' => 3,
                ],
            ];
            foreach ($faqDefaults as $row) {
                $faqInsert->execute($row);
            }
        }

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `user_preferences` (
    `user_id` INT UNSIGNED NOT NULL,
    `marketing_emails` TINYINT(1) NOT NULL DEFAULT 1,
    `order_updates` TINYINT(1) NOT NULL DEFAULT 1,
    `sms_alerts` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `user_preferences_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
