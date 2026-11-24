<?php
declare(strict_types=1);

final class CsrfSessionTest extends HttpTestCase
{
    private function courseCount(): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM courses');
    }

    private function createCourseFields(string $code): array
    {
        return [
            'action' => 'create',
            'title' => 'CSRF Course ' . $code,
            'course_code' => $code,
            'category' => 'Programming',
            'description' => 'Testing CSRF protection.',
            'enrollment_limit' => '20',
        ];
    }

    public function test_post_without_token_is_rejected(): void
    {
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $before = $this->courseCount();

        $response = $this->post('/actions/course.php', $this->createCourseFields('CSRF1'));

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('This form expired or was sent from somewhere else', $response->body);
        $this->assertSame($before, $this->courseCount());
    }

    public function test_post_with_wrong_token_is_rejected(): void
    {
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $before = $this->courseCount();

        $response = $this->post('/actions/course.php', $this->createCourseFields('CSRF2') + ['_csrf' => str_repeat('a', 64)]);

        $this->assertSame(403, $response->status);
        $this->assertSame($before, $this->courseCount());
    }

    public function test_post_with_token_succeeds(): void
    {
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $before = $this->courseCount();

        $response = $this->submitForm('/instructor_dashboard.php', '/actions/course.php', $this->createCourseFields('CSRF3'));

        $this->assertSame(302, $response->status);
        $this->assertSame($before + 1, $this->courseCount());
    }

    public function test_login_without_token_is_rejected(): void
    {
        $response = $this->post('/actions/auth.php', [
            'action' => 'login', 'email' => 'student@edupulse.test', 'password' => 'Student123!',
        ]);
        $this->assertSame(403, $response->status);
    }

    public function test_session_id_changes_on_login(): void
    {
        $this->get('/login.php');
        $before = $this->cookies['PHPSESSID'] ?? null;
        $this->assertNotNull($before);

        $this->loginAs('student@edupulse.test', 'Student123!');

        $this->assertNotSame($before, $this->cookies['PHPSESSID'] ?? null);
    }

    public function test_session_cookie_flags(): void
    {
        $cookie = $this->get('/login.php')->header('set-cookie');
        $this->assertNotNull($cookie);
        $this->assertStringContainsStringIgnoringCase('HttpOnly', $cookie);
        $this->assertStringContainsStringIgnoringCase('SameSite=Lax', $cookie);
    }

    public function test_security_headers_present(): void
    {
        $response = $this->get('/');
        $this->assertSame('nosniff', $response->header('x-content-type-options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->header('referrer-policy'));
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $response->header('content-security-policy'));
    }

    public function test_logout_get_does_nothing_post_logs_out(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');

        $this->get('/actions/auth.php?action=logout');
        $this->assertSame(200, $this->get('/student_dashboard.php')->status);

        $logout = $this->submitForm('/student_dashboard.php', '/actions/auth.php', ['action' => 'logout']);
        $this->assertSame(302, $logout->status);
        $this->assertStringContainsString('login.php?message=logged_out', (string) $logout->location());

        $this->assertSame(302, $this->get('/student_dashboard.php')->status);
        $this->assertSame(404, $this->get('/logout.php')->status);
    }

    public function test_no_third_party_avatar(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $body = $this->get('/profile.php')->body;
        $this->assertStringNotContainsString('ui-avatars.com', $body);
        $this->assertStringContainsString('>AR<', $body);
    }
}
