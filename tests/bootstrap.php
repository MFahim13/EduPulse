<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));

require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/config/env.php';
load_env(PROJECT_ROOT . '/.env');
require_once PROJECT_ROOT . '/config/db.php';
if (is_file(PROJECT_ROOT . '/database/seeder.php')) {
    require_once PROJECT_ROOT . '/database/seeder.php';
}
if (is_file(PROJECT_ROOT . '/includes/helpers.php')) {
    require_once PROJECT_ROOT . '/includes/helpers.php';
}
require_once __DIR__ . '/Integration/IntegrationTestCase.php';
require_once __DIR__ . '/Http/HttpResponse.php';
require_once __DIR__ . '/Http/HttpTestCase.php';
