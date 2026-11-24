<?php
declare(strict_types=1);

/**
 * Every page and action requires this file first.
 */
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

require_once PROJECT_ROOT . '/config/env.php';
load_env(PROJECT_ROOT . '/.env');

date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

require_once PROJECT_ROOT . '/config/db.php';
require_once __DIR__ . '/helpers.php';

configure_errors(env('APP_ENV', 'production'));

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e): void {
        error_log((string) $e);
        $message = 'An unexpected error occurred. Please try again in a moment.';
        if (env('APP_ENV') === 'local') {
            $message .= ' (' . $e->getMessage() . ')';
        }
        render_error_page(500, 'Something went wrong', $message);
    });

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
