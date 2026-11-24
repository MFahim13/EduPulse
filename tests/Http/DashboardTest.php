<?php
declare(strict_types=1);

final class DashboardTest extends HttpTestCase
{
    public function test_student_dashboard_progressbar_per_enrolled_course(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $body = $this->get('/student_dashboard.php')->body;
        $enrolled = (int) $this->scalar(
            "SELECT COUNT(*) FROM enrollments e JOIN users u ON u.id = e.user_id WHERE u.email = 'student@edupulse.test'"
        );
        $this->assertSame($enrolled, substr_count($body, 'role="progressbar"'));
    }

    public function test_student_dashboard_average_is_percentage_of_max_points(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        // student@ has 18/20 (90%) and 45/50 (90%) graded in the seed
        $this->assertStringContainsString('90%', $this->get('/student_dashboard.php')->body);
    }

    public function test_instructor_dashboard_shows_submission_rate(): void
    {
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $body = $this->get('/instructor_dashboard.php')->body;
        $course = (int) $this->scalar("SELECT id FROM courses WHERE course_code = 'CS101'");
        $rate = instructor_course_stats($course)['submission_rate'];
        $this->assertStringContainsString("{$rate}% submitted", $body);
    }
}
