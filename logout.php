<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
if (!defined('RB_ENV_LOADED')) {
    rb_load_env(__DIR__ . '/.env');
    define('RB_ENV_LOADED', true);
}
require __DIR__ . '/includes/session.php';
rb_session_boot();
require __DIR__ . '/includes/auth.php';

rb_auth_logout();

header('Location: index.php');
exit;




