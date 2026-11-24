<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that need the database. Reseeds once per test class.
 */
abstract class IntegrationTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        seed_database(db(), PROJECT_ROOT);
    }

    protected function pdo(): PDO
    {
        return db();
    }

    protected function scalar(string $sql, array $params = []): mixed
    {
        return query($sql, $params)->fetchColumn();
    }

    protected function userId(string $email): int
    {
        return (int) $this->scalar('SELECT id FROM users WHERE email = ?', [$email]);
    }
}
