<?php
declare(strict_types=1);

if (!defined('RB_ACCOUNT_PROFILE_PICTURE_MAX_BYTES')) {
    define('RB_ACCOUNT_PROFILE_PICTURE_MAX_BYTES', 2 * 1024 * 1024); // 2MB
}
if (!defined('RB_ACCOUNT_PROFILE_PICTURE_MAX_DIMENSION')) {
    define('RB_ACCOUNT_PROFILE_PICTURE_MAX_DIMENSION', 512);
}
if (!defined('RB_ACCOUNT_PROFILE_PICTURE_ALLOWED_MIME')) {
    define('RB_ACCOUNT_PROFILE_PICTURE_ALLOWED_MIME', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ]);
}

if (!function_exists('rb_account_user')) {
    function rb_account_user(\PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT id, email, first_name, last_name, avatar_path, role, is_active, email_verified_at, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}

if (!function_exists('rb_account_upload_profile_picture')) {
    /**
     * @param array<string,mixed> $file
     * @return array{ok:bool,path?:string,error?:string}
     */
    function rb_account_upload_profile_picture(\PDO $pdo, int $userId, array $file): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $tmpName = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($error === UPLOAD_ERR_NO_FILE || $tmpName === '') {
            return ['ok' => true];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }
        if ($size <= 0 || $size > RB_ACCOUNT_PROFILE_PICTURE_MAX_BYTES) {
            return ['ok' => false, 'error' => 'Profile picture must be 2MB or smaller.'];
        }
        if (!is_uploaded_file($tmpName)) {
            return ['ok' => false, 'error' => 'Invalid file upload.'];
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string)finfo_file($finfo, $tmpName);
                finfo_close($finfo);
            }
        }
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string)@mime_content_type($tmpName);
        }
        if ($mime === '' || !isset(RB_ACCOUNT_PROFILE_PICTURE_ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'error' => 'Please upload a JPG, PNG, or WebP image.'];
        }

        $info = @getimagesize($tmpName);
        if (!$info) {
            return ['ok' => false, 'error' => 'Unable to read image dimensions.'];
        }
        [$width, $height] = $info;
        if ($width <= 0 || $height <= 0) {
            return ['ok' => false, 'error' => 'Image dimensions are invalid.'];
        }

        $extension = RB_ACCOUNT_PROFILE_PICTURE_ALLOWED_MIME[$mime];
        $needsResize = $width > RB_ACCOUNT_PROFILE_PICTURE_MAX_DIMENSION || $height > RB_ACCOUNT_PROFILE_PICTURE_MAX_DIMENSION;
        $preserveAlpha = $mime === 'image/png' || $mime === 'image/webp';

        $canProcess = true;
        $image = null;
        switch ($mime) {
            case 'image/jpeg':
                if (function_exists('imagecreatefromjpeg')) {
                    $image = @imagecreatefromjpeg($tmpName);
                } else {
                    $canProcess = false;
                }
                break;
            case 'image/png':
                if (function_exists('imagecreatefrompng')) {
                    $image = @imagecreatefrompng($tmpName);
                } else {
                    $canProcess = false;
                }
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($tmpName);
                } else {
                    $canProcess = false;
                }
                break;
            default:
                $canProcess = false;
        }

        if ($canProcess && !$image) {
            $canProcess = false;
        }

        if (!$canProcess) {
            if ($needsResize) {
                return ['ok' => false, 'error' => 'Upload a profile picture that is 512px or smaller, or enable GD JPEG/PNG support on the server.'];
            }
        }

        $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'users';
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
        $filename = sprintf('user-%d-%s.%s', $userId, bin2hex(random_bytes(6)), $extension);
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;

        $saved = false;
        if ($canProcess && $image) {
            $targetWidth = $width;
            $targetHeight = $height;
            if ($needsResize) {
                $scale = RB_ACCOUNT_PROFILE_PICTURE_MAX_DIMENSION / max($width, $height);
                $targetWidth = max(1, (int)round($width * $scale));
                $targetHeight = max(1, (int)round($height * $scale));
            }

            $canvas = $image;
            if ($needsResize) {
                $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
                if ($preserveAlpha) {
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                }
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            }

            $targetExtension = $extension;
            if ($mime === 'image/webp' && function_exists('imagewebp')) {
                $targetExtension = 'webp';
            } elseif ($preserveAlpha) {
                $targetExtension = 'png';
            } else {
                $targetExtension = 'jpg';
            }

            if ($targetExtension !== $extension) {
                $filename = sprintf('user-%d-%s.%s', $userId, bin2hex(random_bytes(6)), $targetExtension);
                $destination = $directory . DIRECTORY_SEPARATOR . $filename;
                $extension = $targetExtension;
            }

            switch ($extension) {
                case 'png':
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                    $saved = imagepng($canvas, $destination, 6);
                    break;
                case 'webp':
                    $saved = imagewebp($canvas, $destination, 85);
                    break;
                default:
                    imageinterlace($canvas, true);
                    $saved = imagejpeg($canvas, $destination, 85);
            }

            if ($canvas !== $image) {
                imagedestroy($canvas);
            }
            imagedestroy($image);
        } else {
            $saved = move_uploaded_file($tmpName, $destination);
        }

        if (!$saved || !is_file($destination)) {
            return ['ok' => false, 'error' => 'Failed to store profile picture.'];
        }

        @chmod($destination, 0644);
        $relative = 'media/users/' . $filename;

        return ['ok' => true, 'path' => $relative];
    }
}

if (!function_exists('rb_account_upload_avatar')) {
    function rb_account_upload_avatar(\PDO $pdo, int $userId, array $file): array
    {
        return rb_account_upload_profile_picture($pdo, $userId, $file);
    }
}

if (!function_exists('rb_account_update_user')) {
    /**
     * @return array{ok:bool, errors:array<string,string>}
     */
    function rb_account_update_user(\PDO $pdo, int $userId, array $input): array
    {
        $first = trim($input['first_name'] ?? '');
        $last = trim($input['last_name'] ?? '');
        $email = trim(mb_strtolower($input['email'] ?? ''));

        $currentStmt = $pdo->prepare('SELECT email, email_verified_at, first_name, last_name, avatar_path FROM users WHERE id = :id LIMIT 1');
        $currentStmt->execute(['id' => $userId]);
        $current = $currentStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$current) {
            return ['ok' => false, 'errors' => ['general' => 'Account not found.']];
        }

        $errors = [];
        if ($first === '') {
            $errors['first_name'] = 'First name is required.';
        }
        if ($last === '') {
            $errors['last_name'] = 'Last name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email.';
        }

        $emailChanged = strcasecmp((string)$current['email'], $email) !== 0;

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
            $stmt->execute(['email' => $email, 'id' => $userId]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Another account already uses this email.';
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $updateSet = 'first_name = :first, last_name = :last, email = :email, email_verified_at = :verified';
        $params = [
            'first' => $first,
            'last' => $last,
            'email' => $email,
            'verified' => $emailChanged ? null : $current['email_verified_at'],
            'id' => $userId,
        ];

        if (array_key_exists('avatar_path', $input) && is_string($input['avatar_path']) && $input['avatar_path'] !== '') {
            $updateSet .= ', avatar_path = :avatar';
            $params['avatar'] = $input['avatar_path'];
        }

        $update = $pdo->prepare('UPDATE users SET ' . $updateSet . ' WHERE id = :id');
        $update->execute($params);

        $verificationSent = false;
        $verificationError = null;
        if ($emailChanged) {
            $sendResult = rb_auth_send_verification_email($pdo, [
                'id' => $userId,
                'email' => $email,
                'first_name' => $first,
                'last_name' => $last,
            ]);
            if ($sendResult['ok'] ?? false) {
                $verificationSent = true;
            } else {
                $verificationError = (string)($sendResult['error'] ?? 'We could not send the verification email.');
            }
        }

        return [
            'ok' => true,
            'errors' => [],
            'verification_sent' => $verificationSent,
            'verification_error' => $verificationError,
            'avatar_path' => $params['avatar'] ?? null,
        ];
    }
}

if (!function_exists('rb_account_addresses')) {
    /**
     * @return array<int, array<string,mixed>>
     */
    function rb_account_addresses(\PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM addresses WHERE user_id = :id ORDER BY is_default DESC, created_at DESC');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }
}

if (!function_exists('rb_account_add_address')) {
    /**
     * @return array{ok:bool, errors:array<string,string>}
     */
    function rb_account_add_address(\PDO $pdo, int $userId, array $input): array
    {
        $fields = [
            'label' => trim($input['label'] ?? ''),
            'contact_name' => trim($input['contact_name'] ?? ''),
            'phone' => trim($input['phone'] ?? ''),
            'line1' => trim($input['line1'] ?? ''),
            'line2' => trim($input['line2'] ?? ''),
            'city' => trim($input['city'] ?? ''),
            'state' => trim($input['state'] ?? ''),
            'postcode' => trim($input['postcode'] ?? ''),
            'country' => trim($input['country'] ?? 'Australia'),
        ];

        $errors = [];
        foreach (['label', 'contact_name', 'line1', 'city', 'state', 'postcode', 'country'] as $key) {
            if ($fields[$key] === '') {
                $errors[$key] = 'Required field.';
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $stmt = $pdo->prepare('INSERT INTO addresses (user_id, label, contact_name, phone, line1, line2, city, state, postcode, country, is_default) VALUES (:user_id, :label, :contact_name, :phone, :line1, :line2, :city, :state, :postcode, :country, :is_default)');
        $stmt->execute([
            'user_id' => $userId,
            'label' => $fields['label'],
            'contact_name' => $fields['contact_name'],
            'phone' => $fields['phone'],
            'line1' => $fields['line1'],
            'line2' => $fields['line2'],
            'city' => $fields['city'],
            'state' => $fields['state'],
            'postcode' => $fields['postcode'],
            'country' => $fields['country'],
            'is_default' => 0,
        ]);

        return ['ok' => true, 'errors' => [], 'id' => (int)$pdo->lastInsertId()];
    }
}

if (!function_exists('rb_account_ensure_wishlist')) {
    function rb_account_ensure_wishlist(\PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare('SELECT id FROM wishlists WHERE user_id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $wishlistId = $stmt->fetchColumn();
        if ($wishlistId) {
            return (int)$wishlistId;
        }
        $stmt = $pdo->prepare('INSERT INTO wishlists (user_id, created_at) VALUES (:id, NOW())');
        $stmt->execute(['id' => $userId]);
        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('rb_account_wishlist_items')) {
    /**
     * @return array<int, array<string,mixed>>
     */
    function rb_account_wishlist_items(\PDO $pdo, int $userId): array
    {
        $wishlistId = rb_account_ensure_wishlist($pdo, $userId);
        $stmt = $pdo->prepare("
            SELECT wi.product_id, wi.added_at, p.name, p.price,
                (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS image_path
            FROM wishlist_items wi
            INNER JOIN products p ON p.id = wi.product_id
            WHERE wi.wishlist_id = :wid
            ORDER BY wi.added_at DESC
        ");
        $stmt->execute(['wid' => $wishlistId]);
        return $stmt->fetchAll() ?: [];
    }
}

if (!function_exists('rb_account_orders')) {
    /**
     * @return array<int, array<string,mixed>>
     */
    function rb_account_orders(\PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("
            SELECT o.*, (
                SELECT SUM(quantity)
                FROM order_items
                WHERE order_id = o.id
            ) AS item_count
            FROM orders o
            WHERE o.user_id = :id
            ORDER BY o.placed_at DESC
        ");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }
}

if (!function_exists('rb_account_order_items')) {
    /**
     * @return array<int, array<string,mixed>>
     */
    function rb_account_order_items(\PDO $pdo, int $orderId): array
    {
        $stmt = $pdo->prepare('SELECT product_id, product_name, quantity, unit_price, total_price FROM order_items WHERE order_id = :id ORDER BY id ASC');
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetchAll() ?: [];
    }
}

if (!function_exists('rb_account_change_password')) {
    /**
     * @return array{ok:bool, errors:array<string,string>}
     */
    function rb_account_change_password(\PDO $pdo, int $userId, array $input): array
    {
        $current = (string)($input['current_password'] ?? '');
        $new = (string)($input['new_password'] ?? '');
        $confirm = (string)($input['confirm_password'] ?? '');

        $errors = [];
        if ($current === '') {
            $errors['current_password'] = 'Enter your current password.';
        }
        if (strlen($new) < 8) {
            $errors['new_password'] = 'Choose a password with at least 8 characters.';
        }
        if (!preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $existing = $errors['new_password'] ?? '';
            $errors['new_password'] = trim($existing . ' Include at least one number and uppercase letter.');
        }
        if ($new !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($current, (string)$hash)) {
            return ['ok' => false, 'errors' => ['current_password' => 'Current password is incorrect.']];
        }

        if (password_verify($new, (string)$hash)) {
            return ['ok' => false, 'errors' => ['new_password' => 'New password must be different from the current password.']];
        }

        $update = $pdo->prepare('UPDATE users SET password_hash = :hash, password_updated_at = NOW(), failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
        $update->execute([
            'hash' => password_hash($new, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        return ['ok' => true, 'errors' => []];
    }
}

if (!function_exists('rb_account_user_preferences')) {
    /**
     * @return array{marketing_emails:int,order_updates:int,sms_alerts:int}
     */
    function rb_account_user_preferences(\PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT marketing_emails, order_updates, sms_alerts FROM user_preferences WHERE user_id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $pref = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$pref) {
            return [
                'marketing_emails' => 1,
                'order_updates' => 1,
                'sms_alerts' => 0,
            ];
        }
        return [
            'marketing_emails' => (int)$pref['marketing_emails'],
            'order_updates' => (int)$pref['order_updates'],
            'sms_alerts' => (int)$pref['sms_alerts'],
        ];
    }
}

if (!function_exists('rb_account_update_preferences')) {
    /**
     * @param array{marketing_emails?:int,order_updates?:int,sms_alerts?:int} $prefs
     * @return array{ok:bool}
     */
    function rb_account_update_preferences(\PDO $pdo, int $userId, array $prefs): array
    {
        $marketing = !empty($prefs['marketing_emails']) ? 1 : 0;
        $orders = !empty($prefs['order_updates']) ? 1 : 0;
        $sms = !empty($prefs['sms_alerts']) ? 1 : 0;

        $stmt = $pdo->prepare('
            INSERT INTO user_preferences (user_id, marketing_emails, order_updates, sms_alerts)
            VALUES (:user_id, :marketing, :orders, :sms)
            ON DUPLICATE KEY UPDATE
                marketing_emails = VALUES(marketing_emails),
                order_updates = VALUES(order_updates),
                sms_alerts = VALUES(sms_alerts),
                updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            'user_id' => $userId,
            'marketing' => $marketing,
            'orders' => $orders,
            'sms' => $sms,
        ]);

        return ['ok' => true];
    }
}

if (!function_exists('rb_account_set_default_address')) {
    /**
     * @return array{ok:bool,error?:string}
     */
    function rb_account_set_default_address(\PDO $pdo, int $userId, int $addressId): array
    {
        $pdo->beginTransaction();
        try {
            $check = $pdo->prepare('SELECT id FROM addresses WHERE id = :id AND user_id = :user LIMIT 1');
            $check->execute(['id' => $addressId, 'user' => $userId]);
            if (!$check->fetchColumn()) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Address not found.'];
            }

            $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :user')->execute(['user' => $userId]);
            $pdo->prepare('UPDATE addresses SET is_default = 1 WHERE id = :id AND user_id = :user')->execute(['id' => $addressId, 'user' => $userId]);
            $pdo->commit();
            return ['ok' => true];
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }
}

if (!function_exists('rb_account_delete_address')) {
    /**
     * @return array{ok:bool,error?:string}
     */
    function rb_account_delete_address(\PDO $pdo, int $userId, int $addressId): array
    {
        $stmt = $pdo->prepare('DELETE FROM addresses WHERE id = :id AND user_id = :user');
        $stmt->execute(['id' => $addressId, 'user' => $userId]);
        if ($stmt->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'Address could not be removed.'];
        }
        return ['ok' => true];
    }
}
