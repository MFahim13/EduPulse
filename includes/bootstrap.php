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
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/throttle.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/progress.php';

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

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: frame-ancestors 'self'");

    if (session_status() === PHP_SESSION_NONE) {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => app_root_path() . '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // PHP silently drops POSTs larger than post_max_size (including the CSRF token),
    // so explain the real problem instead of showing a "form expired" error.
    if (post_too_large()) {
        flash('error', 'File too large (max 10 MB).');
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $sameOrigin = parse_url($referer, PHP_URL_HOST) === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        $target = $sameOrigin ? (string) parse_url($referer, PHP_URL_PATH) : url('index.php');
        $query = $sameOrigin ? parse_url($referer, PHP_URL_QUERY) : null;
        header('Location: ' . $target . ($query ? '?' . $query : ''));
        exit;
    }
}
