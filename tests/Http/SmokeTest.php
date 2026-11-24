<?php
declare(strict_types=1);

final class SmokeTest extends HttpTestCase
{
    public function test_public_pages_render(): void
    {
        foreach (['/', '/login.php', '/register.php'] as $path) {
            $this->assertSame(200, $this->get($path)->status, $path);
        }
    }

    public function test_protected_pages_redirect_when_logged_out(): void
    {
        $paths = ['/student_dashboard.php', '/instructor_dashboard.php', '/profile.php', '/course_detail.php?id=1', '/assignment.php?id=1'];
        foreach ($paths as $path) {
            $response = $this->get($path);
            $this->assertSame(302, $response->status, $path);
            $this->assertStringContainsString('login.php', (string) $response->location(), $path);
        }
    }

    public function test_demo_student_reaches_student_dashboard(): void
    {
        $login = $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertStringContainsString('student_dashboard.php', (string) $login->location());

        $dashboard = $this->follow($login);
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('Welcome', $dashboard->body);
        $this->assertStringContainsString('Ali Raza', $dashboard->body);
    }

    public function test_demo_instructor_reaches_instructor_dashboard(): void
    {
        $login = $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $this->assertStringContainsString('instructor_dashboard.php', (string) $login->location());
        $dashboard = $this->follow($login);
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('Introduction to Programming', $dashboard->body);
    }

    public function test_wrong_role_redirects(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $response = $this->get('/instructor_dashboard.php');
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('index.php', (string) $response->location());
    }

    public function test_internal_files_not_served(): void
    {
        foreach (['/config/db.php', '/includes/helpers.php', '/.env', '/database/schema.sql', '/controllers/auth_controller.php'] as $path) {
            $this->assertSame(404, $this->get($path)->status, $path);
        }
    }

    public function test_instructor_can_create_course(): void
    {
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $response = $this->submitForm('/instructor_dashboard.php', '/actions/course.php', [
            'action' => 'create',
            'title' => 'Smoke Test Course',
            'course_code' => 'SMK100',
            'category' => 'Programming',
            'description' => 'Created by the smoke test.',
            'enrollment_limit' => '30',
        ]);
        $this->assertSame(302, $response->status);

        $dashboard = $this->follow($response);
        $this->assertStringContainsString('Smoke Test Course', $dashboard->body);
        $this->assertStringContainsString('Course created successfully', $dashboard->body);
    }

    public function test_pages_link_assets_from_site_root(): void
    {
        $body = $this->get('/login.php')->body;
        $this->assertMatchesRegularExpression('#href="/assets/css/style\.css\?v=\d+"#', $body);
        $this->assertMatchesRegularExpression('#src="/assets/js/main\.js\?v=\d+"#', $body);
    }
}
