<?php
declare(strict_types=1);

/**
 * Centralised PHP session bootstrap with hardened cookie defaults.
 */

if (!function_exists('rb_session_bool_from_env')) {
    /**
     * Interpret an environment flag as boolean.
     */
    function rb_session_bool_from_env(string $key, bool $default = false): bool
    {
        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            return $default;
        }
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? '';
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return $default;
        }
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('rb_session_cookie_domain')) {
    /**
     * Derive the cookie domain, excluding any port component.
     */
    function rb_session_cookie_domain(): string
    {
        $override = trim((string)($_ENV['SESSION_COOKIE_DOMAIN'] ?? $_SERVER['SESSION_COOKIE_DOMAIN'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return '';
        }
        return preg_replace('/:\\d+$/', '', $host) ?: '';
    }
}

if (!function_exists('rb_session_cookie_options')) {
    /**
     * Build the session cookie parameter array with secure defaults.
     *
     * @return array<string,mixed>
     */
    function rb_session_cookie_options(): array
    {
        $secureDefault = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $secure = rb_session_bool_from_env('SESSION_SECURE', $secureDefault);
        $httpOnly = rb_session_bool_from_env('SESSION_HTTP_ONLY', true);

        $sameSite = trim((string)($_ENV['SESSION_SAME_SITE'] ?? $_SERVER['SESSION_SAME_SITE'] ?? 'Lax'));
        $sameSite = ucfirst(strtolower($sameSite));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }
        // Browsers require Secure when SameSite=None.
        if ($sameSite === 'None') {
            $secure = true;
        }

        $lifetime = (int)($_ENV['SESSION_LIFETIME'] ?? $_SERVER['SESSION_LIFETIME'] ?? 0);
        if ($lifetime < 0) {
            $lifetime = 0;
        }

        return [
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => rb_session_cookie_domain(),
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
    }
}

if (!function_exists('rb_session_name')) {
    /**
     * Resolve the session cookie name (defaults to PHPSESSID).
     */
    function rb_session_name(): string
    {
        $name = trim((string)($_ENV['SESSION_COOKIE_NAME'] ?? $_SERVER['SESSION_COOKIE_NAME'] ?? ''));
        if ($name === '') {
            return session_name();
        }
        return $name;
    }
}

if (!function_exists('rb_session_boot')) {
    /**
     * Configure and start the PHP session idempotently.
     */
    function rb_session_boot(): void
    {
        static $booted = false;
        if ($booted) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            return;
        }

        $booted = true;

        $options = rb_session_cookie_options();

        if (PHP_SAPI !== 'cli') {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', $options['httponly'] ? '1' : '0');
            ini_set('session.cookie_secure', $options['secure'] ? '1' : '0');
        }

        $name = rb_session_name();
        if ($name !== session_name()) {
            session_name($name);
        }

        // Apply cookie parameters before starting the session.
        session_set_cookie_params($options);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
