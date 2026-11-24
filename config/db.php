<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Shared PDO connection, created on first use from environment settings.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'edupulse')
        );

        $pdo = new PDO($dsn, env('DB_USER', ''), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $offset = (new DateTimeImmutable('now', new DateTimeZone(env('APP_TIMEZONE', 'UTC'))))->format('P');
        $pdo->exec("SET time_zone = '{$offset}'");
    }

    return $pdo;
}

/**
 * Run a prepared statement and return it.
 */
function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
