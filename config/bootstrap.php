<?php
declare(strict_types=1);

if (!defined('RB_ASSET_VERSION')) {
    define('RB_ASSET_VERSION', '20251012');
}

/**
 * Basic bootstrap helpers shared across the PHP application.
 *
 * - Loads environment variables from a .env file (line separated KEY=VALUE).
 * - Exposes a global helper to resolve the PDO connection.
 */

require_once __DIR__ . '/database.php';

if (!function_exists('rb_load_env')) {
    /**
     * Load environment variables from a .env style file.
     *
     * @param string $path Absolute path to the env file.
     */
    function rb_load_env(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $delimiterPos = strpos($line, '=');
            if ($delimiterPos === false) {
                continue;
            }

            $name = trim(substr($line, 0, $delimiterPos));
            if ($name === '') {
                continue;
            }

            $valueSegment = substr($line, $delimiterPos + 1);
            $value = trim($valueSegment);
            if ($value !== '') {
                $value = trim($value, "\"'");
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv(sprintf('%s=%s', $name, $value));
        }
    }
}

if (!function_exists('rb_db')) {
    /**
     * Convenience wrapper around the shared PDO connection.
     */
    function rb_db(): PDO
    {
        return \RudraBlessings\Config\Database::connection();
    }
}




