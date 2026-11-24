<?php
declare(strict_types=1);

final class ProgressTest extends IntegrationTestCase
{
    private int $instructor;
    private int $course;
    /** @var int[] */
    private array $assignments = [];

    protected function setUp(): void
    {
        $this->instructor = $this->userId('instructor@edupulse.test');
        query(
            "INSERT INTO courses (title, course_code, category, description, instructor_id, enrollment_limit)
             VALUES ('Progress Course', ?, 'Programming', 'x', ?, 50)",
            ['PRG' . random_int(1000, 9999), $this->instructor]
        );
        $this->course = (int) db()->lastInsertId();
        $this->assignments = [];
    }

    private function addAssignments(int $count, int $maxPoints = 10): void
    {
        for ($i = 0; $i < $count; $i++) {
            query(
                "INSERT INTO assignments (course_id, title, description, due_date, max_points) VALUES (?, ?, 'x', NOW(), ?)",
                [$this->course, "P{$i}", $maxPoints]
            );
            $this->assignments[] = (int) db()->lastInsertId();
        }
    }

    private function enroll(int $student): void
    {
        query('INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)', [$student, $this->course]);
    }

    private function submit(int $student, int $assignment, ?int $grade = null): void
    {
        query(
            "INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at, grade) VALUES (?, ?, 'submissions/x.pdf', NOW(), ?)",
            [$assignment, $student, $grade]
        );
    }

    public function test_progress_counts(): void
    {
        $student = $this->userId('hamza.tariq@edupulse.test');
        $this->enroll($student);
        $this->addAssignments(4);
        $this->submit($student, $this->assignments[0], 8);
        $this->submit($student, $this->assignments[1]);

        $this->assertSame(
            ['total' => 4, 'submitted' => 2, 'graded' => 1, 'percent' => 50],
            student_course_progress($student, $this->course)
        );
    }

    public function test_progress_with_no_assignments(): void
    {
        $this->assertSame(
            ['total' => 0, 'submitted' => 0, 'graded' => 0, 'percent' => 0],
            student_course_progress($this->userId('hamza.tariq@edupulse.test'), $this->course)
        );
    }

    public function test_average_percent_across_max_points(): void
    {
        $student = $this->userId('omar.farooq@edupulse.test');
        query('DELETE FROM submissions WHERE student_id = ?', [$student]);
        $this->addAssignments(1, 20);
        $first = $this->assignments[0];
        $this->addAssignments(1, 50);
        $second = $this->assignments[1];
        $this->submit($student, $first, 18);
        $this->submit($student, $second, 40);
        $this->addAssignments(1, 30);
        $this->submit($student, $this->assignments[2]); // ungraded, ignored

        $this->assertEqualsWithDelta(85.0, student_average_percent($student), 0.001);
    }

    public function test_average_null_without_grades(): void
    {
        $student = $this->userId('zainab.hussain@edupulse.test');
        query('DELETE FROM submissions WHERE student_id = ?', [$student]);
        $this->assertNull(student_average_percent($student));
    }

    public function test_instructor_submission_rate(): void
    {
        foreach (['hamza.tariq@edupulse.test', 'fatima.noor@edupulse.test', 'usman.javed@edupulse.test'] as $email) {
            $this->enroll($this->userId($email));
        }
        $this->addAssignments(2);
        $this->submit($this->userId('hamza.tariq@edupulse.test'), $this->assignments[0], 5);
        $this->submit($this->userId('fatima.noor@edupulse.test'), $this->assignments[0]);
        $this->submit($this->userId('fatima.noor@edupulse.test'), $this->assignments[1]);

        $this->assertSame(
            ['students' => 3, 'assignments' => 2, 'submissions' => 3, 'graded' => 1, 'submission_rate' => 50],
            instructor_course_stats($this->course)
        );
    }

    public function test_instructor_stats_empty_course(): void
    {
        $this->assertSame(
            ['students' => 0, 'assignments' => 0, 'submissions' => 0, 'graded' => 0, 'submission_rate' => 0],
            instructor_course_stats($this->course)
        );
    }
}
