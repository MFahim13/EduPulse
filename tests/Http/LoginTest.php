<?php
declare(strict_types=1);

final class LoginTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        query('DELETE FROM login_attempts');
    }

    private function attempt(string $email, string $password): HttpResponse
    {
        $response = $this->submitForm('/login.php', '/actions/auth.php', [
            'action' => 'login', 'email' => $email, 'password' => $password,
        ]);
        return $response;
    }

    private function attemptAndFollow(string $email, string $password): HttpResponse
    {
        return $this->follow($this->attempt($email, $password));
    }

    public function test_unknown_email_and_wrong_password_same_message(): void
    {
        $unknown = $this->attemptAndFollow('nobody@edupulse.test', 'whatever123');
        $wrong = $this->attemptAndFollow('student@edupulse.test', 'wrong-password');

        foreach ([$unknown, $wrong] as $page) {
            $this->assertStringContainsString('Invalid email or password.', $page->body);
            $this->assertStringNotContainsString('Email not found', $page->body);
            $this->assertStringNotContainsString('Invalid password', $page->body);
        }
    }

    public function test_locked_after_five_failures_even_with_correct_password(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->attempt('student@edupulse.test', 'wrong-password');
        }
        $response = $this->attempt('student@edupulse.test', 'Student123!');

        $this->assertStringContainsString('login.php', (string) $response->location());
        $this->assertStringContainsString('Too many failed attempts. Try again in 15 minutes.', $this->follow($response)->body);
        $this->assertSame(302, $this->get('/student_dashboard.php')->status);
    }

    public function test_success_clears_failures(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->attempt('student@edupulse.test', 'wrong-password');
        }
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM login_attempts WHERE email = 'student@edupulse.test'"));
    }

    public function test_register_rejects_7_char_password(): void
    {
        $response = $this->submitForm('/register.php', '/actions/auth.php', [
            'action' => 'register', 'name' => 'Short Pass', 'email' => 'short.pass@edupulse.test',
            'role' => 'student', 'password' => 'Abc1234', 'confirm_password' => 'Abc1234',
        ]);

        $this->assertStringContainsString('Password must be at least 8 characters.', $this->follow($response)->body);
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM users WHERE email = 'short.pass@edupulse.test'"));
    }

    public function test_email_is_case_insensitive(): void
    {
        $register = fn (string $email) => $this->submitForm('/register.php', '/actions/auth.php', [
            'action' => 'register', 'name' => 'New User', 'email' => $email,
            'role' => 'student', 'password' => 'Password123', 'confirm_password' => 'Password123',
        ]);

        $register('New.User@Example.com');
        $this->assertSame('new.user@example.com', $this->scalar("SELECT email FROM users WHERE name = 'New User'"));

        $this->loginAs('new.user@example.com', 'Password123');
        $this->cookies = [];

        $again = $register('NEW.USER@example.com');
        $this->assertStringContainsString('Email already registered', $this->follow($again)->body);
        $this->assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM users WHERE name = 'New User'"));
    }

    public function test_login_page_shows_demo_buttons(): void
    {
        $body = $this->get('/login.php')->body;
        $this->assertStringContainsString('Log in as demo student', $body);
        $this->assertStringContainsString('Log in as demo instructor', $body);
    }
}
