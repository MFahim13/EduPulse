<?php
declare(strict_types=1);

/**
 * The README documents serving the project root (XAMPP). The app container exposes that
 * layout internally on port 8081 at /html/, with the root .htaccess active.
 */
final class XamppLayoutTest extends HttpTestCase
{
    protected function baseUrl(): string
    {
        return 'http://localhost:8081/html';
    }

    public function test_root_serves_the_landing_page(): void
    {
        $response = $this->get('/');
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Courses, assignments and grades in one place.', $response->body);
        $this->assertSame(200, $this->get('/public/login.php')->status);
    }

    public function test_nothing_outside_public_is_reachable(): void
    {
        $paths = [
            '/config/db.php', '/.env', '/composer.json', '/storage/uploads/', '/database/schema.sql',
            '/tests/fixtures/shell.php',
            '/tests/fixtures/shell.php/public/',
            '/tests/fixtures/shell.php/public/?c=id',
            '/includes/helpers.php/public/x',
            '/x/public/../../config/db.php',
        ];
        foreach ($paths as $path) {
            $response = $this->get($path);
            $this->assertContains($response->status, [403, 404], $path);
            $this->assertStringNotContainsString('EXEC', $response->body, $path);
            $this->assertStringNotContainsString('uid=', $response->body, $path);
        }
    }

    public function test_session_cookie_covers_the_whole_app(): void
    {
        $cookie = (string) $this->get('/')->header('set-cookie');
        $this->assertStringContainsStringIgnoringCase('path=/html/;', $cookie . ';');
    }

    public function test_login_survives_returning_to_the_root_url(): void
    {
        $this->submitForm('/public/login.php', '/public/actions/auth.php', [
            'action' => 'login', 'email' => 'student@edupulse.test', 'password' => 'Student123!',
        ]);
        $this->get('/');
        $this->assertSame(200, $this->get('/public/student_dashboard.php')->status);
    }
}
