<?php
declare(strict_types=1);

final class AnnouncementTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        seed_database(db(), PROJECT_ROOT);
    }

    private function courseId(string $code): int
    {
        return (int) $this->scalar('SELECT id FROM courses WHERE course_code = ?', [$code]);
    }

    private function post_announcement(int $course, string $message): HttpResponse
    {
        return $this->submitForm("/course_detail.php?id={$course}", '/actions/announcement.php', [
            'action' => 'create', 'course_id' => $course, 'message' => $message,
        ]);
    }

    private function countMessage(string $message): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM announcements WHERE message = ?', [$message]);
    }

    public function test_owner_posts_and_enrolled_student_sees_it_on_course_and_dashboard(): void
    {
        $course = $this->courseId('CS101');
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $response = $this->post_announcement($course, 'Quiz moved to Friday');
        $this->assertStringContainsString('Announcement posted.', $this->follow($response)->body);
        $this->assertSame(1, $this->countMessage('Quiz moved to Friday'));

        $this->cookies = [];
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertStringContainsString('Quiz moved to Friday', $this->get("/course_detail.php?id={$course}")->body);
        $dashboard = $this->get('/student_dashboard.php')->body;
        $this->assertStringContainsString('Quiz moved to Friday', $dashboard);
        $this->assertStringContainsString('Introduction to Programming', $dashboard);
    }

    public function test_dashboard_hides_announcements_from_other_courses(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $dashboard = $this->get('/student_dashboard.php')->body;
        $this->assertStringNotContainsString('Office hours are on Wednesdays', $dashboard); // CS250, not enrolled
    }

    public function test_message_is_escaped(): void
    {
        $course = $this->courseId('CS101');
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $this->post_announcement($course, '<script>x</script>');
        $body = $this->get("/course_detail.php?id={$course}")->body;
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>x</script>', $body);
    }

    public function test_non_owner_cannot_post_or_delete(): void
    {
        $course = $this->courseId('CS101');
        $existing = (int) $this->scalar('SELECT id FROM announcements WHERE course_id = ? LIMIT 1', [$course]);
        $total = (int) $this->scalar('SELECT COUNT(*) FROM announcements');

        $this->loginAs('instructor2@edupulse.test', 'Instructor123!');
        $this->post_announcement($course, 'Hijacked announcement');
        $this->submitForm('/instructor_dashboard.php', '/actions/announcement.php', ['action' => 'delete', 'announcement_id' => $existing]);

        $this->cookies = [];
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->post_announcement($course, 'Student announcement');

        $this->assertSame($total, (int) $this->scalar('SELECT COUNT(*) FROM announcements'));
        $this->assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM announcements WHERE id = ?', [$existing]));
    }

    public function test_owner_deletes_announcement(): void
    {
        $course = $this->courseId('CS101');
        $id = (int) $this->scalar('SELECT id FROM announcements WHERE course_id = ? LIMIT 1', [$course]);
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');

        $response = $this->submitForm("/course_detail.php?id={$course}", '/actions/announcement.php', ['action' => 'delete', 'announcement_id' => $id]);

        $this->assertStringContainsString('Announcement deleted.', $this->follow($response)->body);
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM announcements WHERE id = ?', [$id]));
    }

    public function test_owner_sees_post_form_and_student_does_not(): void
    {
        $course = $this->courseId('CS101');
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $this->assertStringContainsString('action="/actions/announcement.php"', $this->get("/course_detail.php?id={$course}")->body);

        $this->cookies = [];
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertStringNotContainsString('action="/actions/announcement.php"', $this->get("/course_detail.php?id={$course}")->body);
    }

    public function test_empty_and_2001_char_messages_rejected(): void
    {
        $course = $this->courseId('CS101');
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $total = (int) $this->scalar('SELECT COUNT(*) FROM announcements');

        $empty = $this->follow($this->post_announcement($course, '   '));
        $this->assertStringContainsString('Announcements must be between 1 and 2000 characters.', $empty->body);
        $this->follow($this->post_announcement($course, str_repeat('a', 2001)));
        $this->assertSame($total, (int) $this->scalar('SELECT COUNT(*) FROM announcements'));

        $this->post_announcement($course, str_repeat('b', 2000));
        $this->assertSame($total + 1, (int) $this->scalar('SELECT COUNT(*) FROM announcements'));
    }
}
