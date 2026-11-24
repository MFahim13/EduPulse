<?php
declare(strict_types=1);

final class ThrottleTest extends IntegrationTestCase
{
    private const EMAIL = 'throttle@edupulse.test';
    private const IP = '203.0.113.9';

    protected function setUp(): void
    {
        query('DELETE FROM login_attempts');
    }

    public function test_locks_after_five_failures_in_window(): void
    {
        for ($i = 0; $i < 4; $i++) {
            record_login_failure(self::EMAIL, self::IP);
        }
        $this->assertFalse(login_locked(self::EMAIL, self::IP));

        record_login_failure(self::EMAIL, self::IP);
        $this->assertTrue(login_locked(self::EMAIL, self::IP));
        $this->assertFalse(login_locked(self::EMAIL, '198.51.100.1'), 'other IPs are not locked');
    }

    public function test_failures_older_than_window_ignored(): void
    {
        for ($i = 0; $i < 5; $i++) {
            query('INSERT INTO login_attempts (email, ip, attempted_at) VALUES (?, ?, NOW() - INTERVAL 16 MINUTE)', [self::EMAIL, self::IP]);
        }
        $this->assertFalse(login_locked(self::EMAIL, self::IP));
    }

    public function test_clear_removes_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            record_login_failure(self::EMAIL, self::IP);
        }
        clear_login_failures(self::EMAIL, self::IP);
        $this->assertFalse(login_locked(self::EMAIL, self::IP));
    }

    public function test_constants(): void
    {
        $this->assertSame(5, LOGIN_MAX_FAILURES);
        $this->assertSame(15, LOGIN_LOCK_MINUTES);
    }
}
