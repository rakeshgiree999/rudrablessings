<?php
declare(strict_types=1);

if (!function_exists('rb_is_local_request')) {
    function rb_is_local_request(): bool
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return false;
        }
        $host = explode(':', $host)[0];
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}

if (!function_exists('rb_enforce_https')) {
    function rb_enforce_https(): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }

        $forceValue = (string)($_ENV['APP_FORCE_HTTPS'] ?? $_SERVER['APP_FORCE_HTTPS'] ?? '1');
        $force = filter_var($forceValue, FILTER_VALIDATE_BOOLEAN);
        $forceIncludingLocal = in_array(strtolower($forceValue), ['force', 'all'], true);
        if (!$force && !$forceIncludingLocal) {
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return;
        }

        if (!$forceIncludingLocal && rb_is_local_request()) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!$isHttps) {
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $target = 'https://' . $host . $uri;
            header('Location: ' . $target, true, 301);
            exit;
        }

        // HSTS is served once by Apache/.htaccess so we avoid duplicating the header here.
    }
}
