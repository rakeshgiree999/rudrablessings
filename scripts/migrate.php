<?php declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line." . PHP_EOL;
    exit(1);
}

require __DIR__ . '/../config/bootstrap.php';
if (!defined('RB_ENV_LOADED')) {
    rb_load_env(__DIR__ . '/../.env');
    define('RB_ENV_LOADED', true);
}
require __DIR__ . '/../includes/upgrade.php';

$pdo = rb_db();

fwrite(STDOUT, "Running database migrations..." . PHP_EOL);

try {
    rb_run_migrations($pdo);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Migrations complete." . PHP_EOL);
