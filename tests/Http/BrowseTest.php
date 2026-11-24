<?php
declare(strict_types=1);

final class BrowseTest extends HttpTestCase
{
    public function test_browse_requires_login(): void
    {
        $this->assertSame(302, $this->get('/courses.php')->status);
    }

    public function test_browse_lists_every_course(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $body = $this->get('/courses.php')->body;
        foreach (query('SELECT title FROM courses')->fetchAll(PDO::FETCH_COLUMN) as $title) {
            $this->assertStringContainsString(e($title), $body);
        }
    }

    public function test_browse_filters_by_search_and_category(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');

        $search = $this->get('/courses.php?q=' . rawurlencode('database'))->body;
        $this->assertStringContainsString('Database Systems', $search);
        $this->assertStringNotContainsString('Mobile App Development', $search);

        $category = $this->get('/courses.php?category=' . rawurlencode('Programming'))->body;
        $this->assertStringContainsString('Introduction to Programming', $category);
        $this->assertStringContainsString(e('Data Structures & Algorithms'), $category);
        $this->assertStringNotContainsString('Database Systems', $category);

        $both = $this->get('/courses.php?q=CS250&category=Programming')->body;
        $this->assertStringContainsString(e('Data Structures & Algorithms'), $both);
        $this->assertStringNotContainsString('Introduction to Programming', $both);
    }

    public function test_browse_shows_enroll_only_where_not_enrolled(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $body = $this->get('/courses.php?q=MD401')->body;
        $this->assertStringContainsString('value="enroll"', $body);

        $enrolled = $this->get('/courses.php?q=CS101')->body;
        $this->assertStringNotContainsString('value="enroll"', $enrolled);
        $this->assertStringContainsString('Enrolled', $enrolled);
    }

    public function test_browse_empty_state(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertStringContainsString('No courses match your search.', $this->get('/courses.php?q=zzzzzz')->body);
    }

    public function test_search_term_is_escaped(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $body = $this->get('/courses.php?q=' . rawurlencode('"><script>alert(1)</script>'))->body;
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    public function test_like_wildcards_are_literal(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertStringContainsString('No courses match your search.', $this->get('/courses.php?q=' . rawurlencode('%'))->body);
        $this->assertStringContainsString('No courses match your search.', $this->get('/courses.php?q=_')->body);
    }

    public function test_navbar_links_to_browse(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertStringContainsString('href="/courses.php"', $this->get('/student_dashboard.php')->body);
    }

    public function test_logged_out_message(): void
    {
        $this->assertStringContainsString(e("You've been logged out."), $this->get('/login.php?message=logged_out')->body);
        $this->assertStringNotContainsString(e("You've been logged out."), $this->get('/login.php')->body);
    }

    public function test_profile_has_no_notification_toggles(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $body = $this->get('/profile.php')->body;
        $this->assertStringNotContainsString('emailNotifications', $body);
        $this->assertStringNotContainsString('form-switch', $body);
    }

    public function test_course_page_lists_each_module_with_its_own_materials(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $course = (int) $this->scalar("SELECT id FROM courses WHERE course_code = 'CS101'");
        $body = $this->get("/course_detail.php?id={$course}")->body;
        $this->assertStringContainsString(e('Week 3 – Writing Functions.pdf'), $body);
        $this->assertSame(1, substr_count($body, e('Week 2 – Conditions and Loops.pdf')));
    }
}
