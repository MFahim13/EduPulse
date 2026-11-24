<?php
declare(strict_types=1);

final class AuthorizationTest extends HttpTestCase
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

    private function courseId(string $code): int
    {
        return (int) $this->scalar('SELECT id FROM courses WHERE course_code = ?', [$code]);
    }

    private function submissionId(string $email, string $assignmentTitle): int
    {
        return (int) $this->scalar(
            'SELECT s.id FROM submissions s JOIN users u ON u.id = s.student_id JOIN assignments a ON a.id = s.assignment_id WHERE u.email = ? AND a.title = ?',
            [$email, $assignmentTitle]
        );
    }

    /** Follow redirects until a page renders. */
    private function land(HttpResponse $response): HttpResponse
    {
        for ($i = 0; $i < 3 && $response->status === 302; $i++) {
            $response = $this->follow($response);
        }
        return $response;
    }

    private function act(string $action, array $fields, array $files = []): HttpResponse
    {
        return $this->submitForm('/profile.php', $action, $fields, $files);
    }

    public function test_cannot_grade_submission_of_another_assignment(): void
    {
        $victim = $this->submissionId('student2@edupulse.test', 'Big-O Problem Set');   // CS250, owned by instructor2
        $before = $this->scalar('SELECT grade FROM submissions WHERE id = ?', [$victim]);

        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $page = $this->land($this->act('/actions/assignment.php', [
            'action' => 'grade',
            'submission_id' => $victim,
            'assignment_id' => $this->assignmentId('Variables and Control Flow'), // instructor@'s own assignment
            'grade' => 0,
            'feedback' => 'overwritten',
        ]));

        $this->assertStringContainsString(e("You don't have permission to grade this submission."), $page->body);
        $this->assertSame($before, $this->scalar('SELECT grade FROM submissions WHERE id = ?', [$victim]));
        $this->assertNotSame('overwritten', $this->scalar('SELECT feedback FROM submissions WHERE id = ?', [$victim]));
    }

    public function test_other_instructor_cannot_grade(): void
    {
        $submission = $this->submissionId('student@edupulse.test', 'Variables and Control Flow');
        $this->loginAs('instructor2@edupulse.test', 'Instructor123!');
        $this->act('/actions/assignment.php', [
            'action' => 'grade', 'submission_id' => $submission,
            'assignment_id' => $this->assignmentId('Variables and Control Flow'), 'grade' => 1,
        ]);
        $this->assertSame(18, (int) $this->scalar('SELECT grade FROM submissions WHERE id = ?', [$submission]));
    }

    public function test_unenrolled_student_cannot_submit(): void
    {
        $assignment = $this->assignmentId('Wireframe a Mobile App'); // MD401, student@ not enrolled
        $this->loginAs('student@edupulse.test', 'Student123!');

        $page = $this->land($this->act('/actions/assignment.php', ['action' => 'submit', 'assignment_id' => $assignment],
            ['submission_file' => PROJECT_ROOT . '/tests/fixtures/sample.pdf']));

        $this->assertStringContainsString('You need to be enrolled in this course to submit.', $page->body);
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM submissions WHERE assignment_id = ?', [$assignment]));
    }

    public function test_instructor_cannot_submit(): void
    {
        $assignment = $this->assignmentId('Loops Challenge');
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');

        $page = $this->land($this->act('/actions/assignment.php', ['action' => 'submit', 'assignment_id' => $assignment],
            ['submission_file' => PROJECT_ROOT . '/tests/fixtures/sample.pdf']));

        $this->assertStringContainsString('Only students can submit assignments.', $page->body);
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM submissions WHERE assignment_id = ?', [$assignment]));
    }

    public function test_submit_to_missing_assignment_rejected(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $page = $this->land($this->act('/actions/assignment.php', ['action' => 'submit', 'assignment_id' => 999999],
            ['submission_file' => PROJECT_ROOT . '/tests/fixtures/sample.pdf']));

        $this->assertStringContainsString('Assignment not found.', $page->body);
        $this->assertStringNotContainsString('SQLSTATE', $page->body);
    }

    public function test_enroll_missing_course_rejected(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $page = $this->land($this->act('/actions/course.php', ['action' => 'enroll', 'course_id' => 999999]));

        $this->assertStringContainsString('Course not found.', $page->body);
        $this->assertStringNotContainsString('SQLSTATE', $page->body);
    }

    public function test_enroll_limit_boundary(): void
    {
        $course = $this->courseId('MD401'); // 2 students enrolled in the seed
        query('UPDATE courses SET enrollment_limit = 3 WHERE id = ?', [$course]);

        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->act('/actions/course.php', ['action' => 'enroll', 'course_id' => $course]);
        $this->assertSame(3, (int) $this->scalar('SELECT COUNT(*) FROM enrollments WHERE course_id = ?', [$course]));

        $this->cookies = [];
        $this->loginAs('student2@edupulse.test', 'Student123!');
        $page = $this->land($this->act('/actions/course.php', ['action' => 'enroll', 'course_id' => $course]));
        $this->assertStringContainsString('reached its enrollment limit', $page->body);
        $this->assertSame(3, (int) $this->scalar('SELECT COUNT(*) FROM enrollments WHERE course_id = ?', [$course]));
    }

    public function test_instructor_cannot_enroll(): void
    {
        $course = $this->courseId('CS250');
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $this->act('/actions/course.php', ['action' => 'enroll', 'course_id' => $course]);
        $this->assertSame(0, (int) $this->scalar(
            "SELECT COUNT(*) FROM enrollments e JOIN users u ON u.id = e.user_id WHERE u.email = 'instructor@edupulse.test'"
        ));
    }

    public function test_cannot_add_module_or_assignment_to_others_course(): void
    {
        $course = $this->courseId('CS101');
        $this->loginAs('instructor2@edupulse.test', 'Instructor123!');

        $this->act('/actions/course.php', ['action' => 'add_module', 'course_id' => $course, 'module_title' => 'Hijack']);
        $this->act('/actions/assignment.php', [
            'action' => 'create', 'course_id' => $course, 'title' => 'Hijack', 'description' => 'x',
            'due_date' => '2030-01-01T10:00', 'max_points' => 10,
        ]);

        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM modules WHERE title = 'Hijack'"));
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM assignments WHERE title = 'Hijack'"));
    }

    public function test_enrollment_limit_must_be_1_to_500(): void
    {
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        foreach (['0' => 'LIM0', '501' => 'LIM501'] as $limit => $code) {
            $page = $this->land($this->act('/actions/course.php', [
                'action' => 'create', 'title' => "Limit {$limit}", 'course_code' => $code,
                'category' => 'Programming', 'description' => 'x', 'enrollment_limit' => $limit,
            ]));
            $this->assertStringContainsString('Enrollment limit must be between 1 and 500.', $page->body);
            $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM courses WHERE course_code = ?', [$code]));
        }
    }
}
