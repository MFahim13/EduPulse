<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_e_escapes_html_and_handles_null(): void
    {
        $this->assertSame('&lt;a href=&quot;x&quot;&gt;', e('<a href="x">'));
        $this->assertSame('it&#039;s', e("it's"));
        $this->assertSame('', e(null));
        $this->assertSame('42', e(42));
    }

    public function test_initials(): void
    {
        $this->assertSame('MF', initials('Muhammad Fahim'));
        $this->assertSame('Z', initials('ziad'));
        $this->assertSame('AM', initials('Ana Maria Lopez'));
        $this->assertSame('?', initials('   '));
    }

    public function test_take_flashes_returns_and_clears(): void
    {
        flash('error', ['a', 'b']);
        flash('success', 'saved');
        $this->assertSame(['error' => ['a', 'b'], 'success' => ['saved']], take_flashes());
        $this->assertSame([], take_flashes());
    }

    public function test_configure_errors_production_hides_and_local_shows(): void
    {
        $original = ini_get('display_errors');
        try {
            configure_errors('production');
            $this->assertContains(ini_get('display_errors'), ['0', '']);
            configure_errors('local');
            $this->assertSame('1', ini_get('display_errors'));
        } finally {
            ini_set('display_errors', (string) $original);
        }
    }

    public function test_url_prefixes_base_path_and_strips_actions_dir(): void
    {
        $saved = $_SERVER['SCRIPT_NAME'] ?? null;
        try {
            $_SERVER['SCRIPT_NAME'] = '/actions/auth.php';
            $this->assertSame('/login.php', url('login.php'));
            $_SERVER['SCRIPT_NAME'] = '/EduPulse/public/actions/course.php';
            $this->assertSame('/EduPulse/public/course_detail.php?id=3', url('course_detail.php?id=3'));
            $_SERVER['SCRIPT_NAME'] = '/index.php';
            $this->assertSame('/assets/css/style.css', url('/assets/css/style.css'));
        } finally {
            $_SERVER['SCRIPT_NAME'] = $saved;
        }
    }
}
