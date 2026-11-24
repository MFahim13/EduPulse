<?php
declare(strict_types=1);

/**
 * Usage: php database/seed.php [--if-empty]
 * Rebuilds the database with demo data. With --if-empty it only runs on a fresh database.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/config/env.php';
load_env($root . '/.env');
require_once $root . '/config/db.php';
require_once __DIR__ . '/seeder.php';

if (in_array('--if-empty', $argv, true)) {
    $exists = db()->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($exists !== false) {
        echo "Database already set up; skipping seed.\n";
        exit(0);
    }
}

seed_database(db(), $root);
echo "Database seeded with demo data.\n";
