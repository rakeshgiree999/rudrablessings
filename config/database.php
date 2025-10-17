<?php
declare(strict_types=1);

namespace RudraBlessings\Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    /**
     * Returns the shared PDO connection. Lazily initialises using env vars.
     *
     * Expected environment variables:
     *  - DB_HOST (default 127.0.0.1)
     *  - DB_PORT (default 3306)
     *  - DB_NAME
     *  - DB_USER
     *  - DB_PASS
     */
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = (int)($_ENV['DB_PORT'] ?? 3306);
        $database = $_ENV['DB_NAME'] ?? 'rudraBlessings';
        $user = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? null;

        if ($database === null || $user === null) {
            throw new RuntimeException('Database credentials are missing. Please set DB_NAME, DB_USER and DB_PASS in your environment.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$connection = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }

        return self::$connection;
    }

    /**
     * Reset the PDO connection, useful for long running scripts or tests.
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }
}
