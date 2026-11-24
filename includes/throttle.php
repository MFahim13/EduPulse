<?php
declare(strict_types=1);

const LOGIN_MAX_FAILURES = 5;
const LOGIN_LOCK_MINUTES = 15;

function login_locked(string $email, string $ip): bool
{
    $failures = (int) query(
        'SELECT COUNT(*) FROM login_attempts
         WHERE email = ? AND ip = ? AND attempted_at > NOW() - INTERVAL ' . LOGIN_LOCK_MINUTES . ' MINUTE',
        [$email, $ip]
    )->fetchColumn();

    return $failures >= LOGIN_MAX_FAILURES;
}

function record_login_failure(string $email, string $ip): void
{
    query('INSERT INTO login_attempts (email, ip, attempted_at) VALUES (?, ?, NOW())', [$email, $ip]);
}

function clear_login_failures(string $email, string $ip): void
{
    query('DELETE FROM login_attempts WHERE email = ? AND ip = ?', [$email, $ip]);
}
