<?php
declare(strict_types=1);

final class SubmissionTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        seed_database(db(), PROJECT_ROOT);
    }

    private function assignmentId(string $title): int
    {
        return (int) $this->scalar('SELECT id FROM assignments WHERE title = ?', [$title]);
    }

    private function submission(string $email, int $assignment): array|false
    {
        return query(
            'SELECT s.* FROM submissions s JOIN users u ON u.id = s.student_id WHERE u.email = ? AND s.assignment_id = ?',
            [$email, $assignment]
        )->fetch();
    }

    private function grade(int $assignment, int $submission, string $grade): HttpResponse
    {
        return $this->submitForm("/assignment.php?id={$assignment}", '/actions/assignment.php', [
            'action' => 'grade', 'assignment_id' => $assignment, 'submission_id' => $submission,
            'grade' => $grade, 'feedback' => 'Checked.',
        ]);
    }

    private function submit(int $assignment): HttpResponse
    {
        return $this->submitForm("/assignment.php?id={$assignment}", '/actions/assignment.php', [
            'action' => 'submit', 'assignment_id' => $assignment,
        ], ['submission_file' => PROJECT_ROOT . '/tests/fixtures/sample.pdf']);
    }

    public function test_grade_range_uses_max_points(): void
    {
        $assignment = $this->assignmentId('Variables and Control Flow'); // max 20
        $submission = (int) $this->submission('student@edupulse.test', $assignment)['id'];
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');

        $this->assertStringContainsString('Grade must be between 0 and 20.', $this->follow($this->grade($assignment, $submission, '21'))->body);
        $this->follow($this->grade($assignment, $submission, '-1'));
        $this->assertSame(18, (int) $this->submission('student@edupulse.test', $assignment)['grade']);

        $page = $this->follow($this->grade($assignment, $submission, '20'));
        $this->assertSame(20, (int) $this->submission('student@edupulse.test', $assignment)['grade']);
        $this->assertStringContainsString('20 / 20 (100%)', $page->body);
    }

    public function test_student_sees_points_based_grade(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $body = $this->get('/assignment.php?id=' . $this->assignmentId('Variables and Control Flow'))->body;
        $this->assertStringContainsString('18 / 20 (90%)', $body);
    }

    public function test_late_one_minute_after_due(): void
    {
        $assignment = $this->assignmentId('Loops Challenge');
        query('UPDATE assignments SET due_date = NOW() - INTERVAL 1 MINUTE WHERE id = ?', [$assignment]);
        $this->loginAs('student@edupulse.test', 'Student123!');

        $page = $this->follow($this->submit($assignment));

        $this->assertNotFalse($this->submission('student@edupulse.test', $assignment));
        $this->assertStringContainsString('late-badge', $page->body);
    }

    public function test_not_late_one_minute_before_due(): void
    {
        $assignment = $this->assignmentId('Loops Challenge');
        query('UPDATE assignments SET due_date = NOW() + INTERVAL 1 MINUTE WHERE id = ?', [$assignment]);
        $this->loginAs('student@edupulse.test', 'Student123!');

        $page = $this->follow($this->submit($assignment));

        $this->assertNotFalse($this->submission('student@edupulse.test', $assignment));
        $this->assertStringNotContainsString('late-badge', $page->body);
    }

    public function test_instructor_sees_late_badge(): void
    {
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $body = $this->get('/assignment.php?id=' . $this->assignmentId('Functions Lab'))->body; // student@ submitted a day late
        $this->assertStringContainsString('late-badge', $body);
    }

    public function test_resubmit_replaces_until_graded(): void
    {
        $assignment = $this->assignmentId('Functions Lab'); // student@ submitted, not graded
        $first = $this->submission('student@edupulse.test', $assignment);
        $this->assertNull($first['grade']);
        $this->loginAs('student@edupulse.test', 'Student123!');

        $page = $this->follow($this->submit($assignment));
        $second = $this->submission('student@edupulse.test', $assignment);

        $this->assertStringContainsString('Your submission was updated.', $page->body);
        $this->assertNotSame($first['file_path'], $second['file_path']);
        $this->assertFileDoesNotExist(PROJECT_ROOT . '/storage/uploads/' . $first['file_path']);
        $this->assertFileExists(PROJECT_ROOT . '/storage/uploads/' . $second['file_path']);
        $this->assertGreaterThan(strtotime($first['submitted_at']), strtotime($second['submitted_at']));
        $this->assertSame(1, (int) $this->scalar(
            "SELECT COUNT(*) FROM submissions s JOIN users u ON u.id = s.student_id WHERE u.email = 'student@edupulse.test' AND s.assignment_id = ?",
            [$assignment]
        ));

        query('UPDATE submissions SET grade = 15, graded_at = NOW() WHERE id = ?', [$second['id']]);
        $rejected = $this->follow($this->submit($assignment));
        $this->assertStringContainsString('This submission has already been graded.', $rejected->body);
        $this->assertSame($second['file_path'], $this->submission('student@edupulse.test', $assignment)['file_path']);
    }

    public function test_max_points_must_be_1_to_1000(): void
    {
        $course = (int) $this->scalar("SELECT id FROM courses WHERE course_code = 'CS101'");
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        foreach (['0', '1001'] as $points) {
            $page = $this->follow($this->submitForm("/course_detail.php?id={$course}", '/actions/assignment.php', [
                'action' => 'create', 'course_id' => $course, 'title' => "Points {$points}", 'description' => 'x',
                'due_date' => '2030-01-01T10:00', 'max_points' => $points,
            ]));
            $this->assertStringContainsString('Max points must be between 1 and 1000.', $page->body);
        }
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM assignments WHERE title LIKE 'Points %'"));
    }
}
