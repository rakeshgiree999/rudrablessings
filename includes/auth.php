<?php
declare(strict_types=1);

if (!function_exists('rb_auth_find_user_by_email')) {
    function rb_auth_find_user_by_email(\PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}

if (!function_exists('rb_auth_current_user')) {
    function rb_auth_current_user(\PDO $pdo): ?array
    {
        static $cached = false;
        static $user = null;
        if ($cached) {
            return $user;
        }
        $cached = true;
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$user) {
            unset($_SESSION['user_id']);
        }
        return $user;
    }
}

if (!function_exists('rb_auth_validate_password')) {
    /**
     * Validate password strength and return any error messages.
     *
     * @return array<int,string>
     */
    function rb_auth_validate_password(string $password): array
    {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Include at least one uppercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Include at least one number.';
        }
        return $errors;
    }
}

if (!function_exists('rb_auth_issue_token')) {
    /**
     * Create a single-use token for a user and return its value.
     */
    function rb_auth_issue_token(\PDO $pdo, int $userId, string $type, array $meta = [], int $ttlSeconds = 3600): string
    {
        $allowedTypes = ['verify', 'password_reset'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new \InvalidArgumentException('Unsupported token type: ' . $type);
        }
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid user id for token issuance.');
        }

        $ttl = max(300, $ttlSeconds);
        $expiresAt = (new \DateTimeImmutable('now'))->add(new \DateInterval('PT' . $ttl . 'S'));
        $token = bin2hex(random_bytes(32));

        $pdo->prepare('DELETE FROM user_tokens WHERE user_id = :user AND type = :type AND consumed_at IS NULL')->execute([
            'user' => $userId,
            'type' => $type,
        ]);

        $metaJson = '{}';
        if (!empty($meta)) {
            $encoded = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $metaJson = $encoded;
            }
        }

        $insert = $pdo->prepare('INSERT INTO user_tokens (user_id, token, type, meta, expires_at) VALUES (:user_id, :token, :type, :meta, :expires_at)');
        $insert->execute([
            'user_id' => $userId,
            'token' => $token,
            'type' => $type,
            'meta' => $metaJson,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return $token;
    }
}

if (!function_exists('rb_auth_consume_token')) {
    /**
     * Look up a token and optionally mark it as consumed.
     *
     * @return array<string,mixed>|null
     */
    function rb_auth_consume_token(\PDO $pdo, string $token, string $type, bool $consume = true): ?array
    {
        if ($token === '') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM user_tokens WHERE token = :token AND type = :type LIMIT 1');
        $stmt->execute(['token' => $token, 'type' => $type]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || !empty($row['consumed_at'])) {
            return null;
        }

        try {
            $expiresAt = new \DateTimeImmutable((string)$row['expires_at']);
        } catch (\Exception $e) {
            $expiresAt = null;
        }
        if ($expiresAt !== null && $expiresAt < new \DateTimeImmutable('now')) {
            return null;
        }

        if ($consume) {
            $pdo->prepare('UPDATE user_tokens SET consumed_at = NOW() WHERE id = :id')->execute(['id' => $row['id']]);
            $row['consumed_at'] = date('Y-m-d H:i:s');
        }

        return $row;
    }
}

if (!function_exists('rb_auth_send_verification_email')) {
    function rb_auth_send_verification_email(\PDO $pdo, array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Invalid account.'];
        }

        $token = rb_auth_issue_token($pdo, $userId, 'verify', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], 86400);

        $verifyUrl = rb_url('verify.php?token=' . urlencode($token));
        $settings = rb_settings($pdo);
        $brand = $settings['brand.name'] ?? 'RudraBlessings';
        $firstName = trim((string)($user['first_name'] ?? ''));
        $greeting = $firstName !== '' ? 'Hi ' . $firstName . ',' : 'Hello,';

        $html = '<p>' . htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<p>Please confirm your email address to activate your ' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . ' account.</p>';
        $html .= '<p><a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 16px;background:#1f2937;color:#fff;border-radius:6px;text-decoration:none;">Verify my email</a></p>';
        $html .= '<p>If the button does not work, copy and paste this link into your browser:<br>' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '</p>';

        $text = $greeting . "\n\nPlease confirm your email address to activate your {$brand} account.\n{$verifyUrl}\n\nIf you did not create this account, you can ignore this email.";

        return rb_mail_send([
            'subject' => $brand . ' - Verify your email address',
            'html' => $html,
            'text' => $text,
            'to' => [
                [$user['email'] ?? '', trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''))],
            ],
        ]);
    }
}

if (!function_exists('rb_auth_send_password_reset_email')) {
    function rb_auth_send_password_reset_email(\PDO $pdo, array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Invalid account.'];
        }

        $token = rb_auth_issue_token($pdo, $userId, 'password_reset', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], 3600);

        $resetUrl = rb_url('reset-password.php?token=' . urlencode($token));
        $settings = rb_settings($pdo);
        $brand = $settings['brand.name'] ?? 'RudraBlessings';
        $firstName = trim((string)($user['first_name'] ?? ''));
        $greeting = $firstName !== '' ? 'Hi ' . $firstName . ',' : 'Hello,';

        $html = '<p>' . htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<p>We received a request to reset your ' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . ' password. If this was you, click the button below.</p>';
        $html .= '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 16px;background:#1f2937;color:#fff;border-radius:6px;text-decoration:none;">Reset my password</a></p>';
        $html .= '<p>If you did not request a reset, you can ignore this email.</p>';

        $text = $greeting . "\n\nWe received a request to reset your {$brand} password.\n{$resetUrl}\n\nIf you did not request a reset you can ignore this message.";

        return rb_mail_send([
            'subject' => $brand . ' - Reset your password',
            'html' => $html,
            'text' => $text,
            'to' => [
                [$user['email'] ?? '', trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''))],
            ],
        ]);
    }
}

if (!function_exists('rb_auth_resend_verification')) {
    function rb_auth_resend_verification(\PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }
        if (!empty($user['email_verified_at'])) {
            return ['ok' => true, 'already_verified' => true];
        }

        return rb_auth_send_verification_email($pdo, $user);
    }
}

if (!function_exists('rb_auth_request_password_reset')) {
    function rb_auth_request_password_reset(\PDO $pdo, string $email): array
    {
        $emailNormalized = trim(mb_strtolower($email));
        if (!filter_var($emailNormalized, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please provide a valid email address.'];
        }

        $user = rb_auth_find_user_by_email($pdo, $emailNormalized);
        if ($user) {
            rb_auth_send_password_reset_email($pdo, $user);
        }

        // Always succeed to avoid account enumeration.
        return ['ok' => true];
    }
}

if (!function_exists('rb_auth_mark_email_verified')) {
    function rb_auth_mark_email_verified(\PDO $pdo, int $userId): void
    {
        $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), failed_login_attempts = 0, locked_until = NULL WHERE id = :id')
            ->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM user_tokens WHERE user_id = :id AND type = :type')->execute([
            'id' => $userId,
            'type' => 'verify',
        ]);
    }
}

if (!function_exists('rb_auth_reset_password')) {
    /**
     * @return array{ok:bool, errors:array<int,string>}
     */
    function rb_auth_reset_password(\PDO $pdo, int $userId, string $newPassword): array
    {
        $issues = rb_auth_validate_password($newPassword);
        if ($issues) {
            return ['ok' => false, 'errors' => ['password' => implode(' ', $issues)]];
        }

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, password_updated_at = NOW(), failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
        $stmt->execute([
            'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'errors' => ['general' => 'Unable to update password.']];
        }

        $pdo->prepare('DELETE FROM user_tokens WHERE user_id = :id AND type = :type')->execute([
            'id' => $userId,
            'type' => 'password_reset',
        ]);

        return ['ok' => true, 'errors' => []];
    }
}

if (!function_exists('rb_auth_login')) {
    /**
     * @return array{ok:bool, errors:array<int,string>, status?:string, user_id?:int, email?:string, retry_after?:string}
     */
    function rb_auth_login(\PDO $pdo, string $email, string $password): array
    {
        $emailNormalized = trim(mb_strtolower($email));
        $errors = [];
        if (!filter_var($emailNormalized, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'status' => 'invalid'];
        }

        $user = rb_auth_find_user_by_email($pdo, $emailNormalized);
        $now = new \DateTimeImmutable('now');

        if ($user) {
            $lockedUntil = null;
            if (!empty($user['locked_until'])) {
                try {
                    $lockedUntil = new \DateTimeImmutable((string)$user['locked_until']);
                } catch (\Exception $e) {
                    $lockedUntil = null;
                }
            }
            if ($lockedUntil !== null && $lockedUntil > $now) {
                $minutes = (int)ceil(($lockedUntil->getTimestamp() - $now->getTimestamp()) / 60);
                $message = 'Too many failed attempts. Try again in ' . max(1, $minutes) . ' minute' . ($minutes === 1 ? '' : 's') . '.';
                return [
                    'ok' => false,
                    'errors' => [$message],
                    'status' => 'locked',
                    'retry_after' => $lockedUntil->format(\DATE_ATOM),
                ];
            }
        }

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            if ($user) {
                $failedAttempts = (int)($user['failed_login_attempts'] ?? 0);
                $lastAttemptTs = null;
                if (!empty($user['last_login_attempt_at'])) {
                    try {
                        $lastAttempt = new \DateTimeImmutable((string)$user['last_login_attempt_at']);
                        $lastAttemptTs = $lastAttempt->getTimestamp();
                    } catch (\Exception $e) {
                        $lastAttemptTs = null;
                    }
                }

                $nowTs = $now->getTimestamp();
                if ($lastAttemptTs === null || ($nowTs - $lastAttemptTs) >= 900) {
                    $failedAttempts = 0;
                }

                $failedAttempts++;
                $lockUntilValue = null;
                $message = 'Invalid email or password.';
                if ($failedAttempts >= 5) {
                    $lockUntil = $now->add(new \DateInterval('PT15M'));
                    $lockUntilValue = $lockUntil->format('Y-m-d H:i:s');
                    $failedAttempts = 0;
                    $message = 'Too many failed attempts. Try again in 15 minutes.';
                }

                $pdo->prepare('UPDATE users SET failed_login_attempts = :attempts, last_login_attempt_at = :attempted_at, locked_until = :locked WHERE id = :id')->execute([
                    'attempts' => $failedAttempts,
                    'attempted_at' => $now->format('Y-m-d H:i:s'),
                    'locked' => $lockUntilValue,
                    'id' => $user['id'],
                ]);

                $status = $lockUntilValue ? 'locked' : 'invalid';
                $response = ['ok' => false, 'errors' => [$message], 'status' => $status];
                if ($lockUntilValue) {
                    $response['retry_after'] = $lockUntilValue;
                }
                return $response;
            }

            return ['ok' => false, 'errors' => ['Invalid email or password.'], 'status' => 'invalid'];
        }

        if (!(int)$user['is_active']) {
            return ['ok' => false, 'errors' => ['This account has been deactivated.'], 'status' => 'inactive'];
        }

        if (empty($user['email_verified_at'])) {
            return [
                'ok' => false,
                'errors' => ['Please verify your email to continue.'],
                'status' => 'unverified',
                'user_id' => (int)$user['id'],
                'email' => (string)$user['email'],
            ];
        }

        $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_attempt_at = NOW(), last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);

        $_SESSION['user_id'] = (int)$user['id'];
        return ['ok' => true, 'errors' => []];
    }
}

if (!function_exists('rb_auth_register')) {
    /**
     * @return array{ok:bool, errors:array<string,string>, status?:string, user_id?:int}
     */
    function rb_auth_register(\PDO $pdo, array $input): array
    {
        $first = trim((string)($input['first_name'] ?? ''));
        $last = trim((string)($input['last_name'] ?? ''));
        $email = trim(mb_strtolower((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $confirm = (string)($input['confirm_password'] ?? '');

        $errors = [];
        if ($first === '') {
            $errors['first_name'] = 'First name is required.';
        }
        if ($last === '') {
            $errors['last_name'] = 'Last name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        $passwordIssues = rb_auth_validate_password($password);
        if ($passwordIssues) {
            $errors['password'] = implode(' ', $passwordIssues);
        }

        if ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!isset($errors['email'])) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'This email is already registered.';
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (role, email, password_hash, first_name, last_name, password_updated_at) VALUES (:role, :email, :password_hash, :first_name, :last_name, :password_updated_at)');
        $stmt->execute([
            'role' => 'customer',
            'email' => $email,
            'password_hash' => $hash,
            'first_name' => $first,
            'last_name' => $last,
            'password_updated_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        $userId = (int)$pdo->lastInsertId();
        rb_auth_send_verification_email($pdo, [
            'id' => $userId,
            'email' => $email,
            'first_name' => $first,
            'last_name' => $last,
        ]);

        return [
            'ok' => true,
            'errors' => [],
            'status' => 'pending_verification',
            'user_id' => $userId,
        ];
    }
}

if (!function_exists('rb_auth_logout')) {
    function rb_auth_logout(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
    }
}
