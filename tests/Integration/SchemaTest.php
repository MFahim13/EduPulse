<?php
declare(strict_types=1);

final class SchemaTest extends IntegrationTestCase
{
    public function test_demo_accounts_exist_with_roles_and_passwords(): void
    {
        $expected = [
            'instructor@edupulse.test' => ['instructor', 'Instructor123!'],
            'instructor2@edupulse.test' => ['instructor', 'Instructor123!'],
            'student@edupulse.test' => ['student', 'Student123!'],
            'student2@edupulse.test' => ['student', 'Student123!'],
        ];

        foreach ($expected as $email => [$role, $password]) {
            $user = query('SELECT role, password FROM users WHERE email = ?', [$email])->fetch();
            $this->assertNotFalse($user, "missing demo account {$email}");
            $this->assertSame($role, $user['role']);
            $this->assertTrue(password_verify($password, $user['password']), "wrong password for {$email}");
        }
    }

    public function test_duplicate_enrollment_is_rejected(): void
    {
        $row = query('SELECT user_id, course_id FROM enrollments LIMIT 1')->fetch();

        try {
            query('INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)', [$row['user_id'], $row['course_id']]);
            $this->fail('duplicate enrollment was accepted');
        } catch (PDOException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
        }
    }

    public function test_deleting_a_course_cascades(): void
    {
        $courseId = (int) $this->scalar(
            'SELECT c.id FROM courses c WHERE EXISTS (SELECT 1 FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.course_id = c.id) LIMIT 1'
        );
        $this->assertGreaterThan(0, $courseId);

        query('DELETE FROM courses WHERE id = ?', [$courseId]);

        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM modules WHERE course_id = ?', [$courseId]));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM materials m LEFT JOIN modules mo ON mo.id = m.module_id WHERE mo.id IS NULL'));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM assignments WHERE course_id = ?', [$courseId]));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM submissions s LEFT JOIN assignments a ON a.id = s.assignment_id WHERE a.id IS NULL'));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM enrollments WHERE course_id = ?', [$courseId]));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM announcements WHERE course_id = ?', [$courseId]));

        seed_database(db(), PROJECT_ROOT);
    }

    public function test_demo_student_has_every_assignment_state(): void
    {
        $student = $this->userId('student@edupulse.test');
        $base = 'FROM assignments a JOIN enrollments e ON e.course_id = a.course_id AND e.user_id = :sid1
                 LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :sid2';

        $states = [
            'upcoming' => "s.id IS NULL AND a.due_date > NOW()",
            'overdue-unsubmitted' => "s.id IS NULL AND a.due_date < NOW()",
            'submitted-ungraded' => "s.id IS NOT NULL AND s.grade IS NULL",
            'graded-with-feedback' => "s.grade IS NOT NULL AND s.feedback IS NOT NULL AND s.feedback <> ''",
            'late-submitted' => "s.id IS NOT NULL AND s.submitted_at > a.due_date",
        ];

        foreach ($states as $name => $where) {
            $count = (int) $this->scalar("SELECT COUNT(*) {$base} WHERE {$where}", ['sid1' => $student, 'sid2' => $student]);
            $this->assertGreaterThanOrEqual(1, $count, "no assignment in state {$name}");
        }
    }

    public function test_demo_student_is_not_enrolled_everywhere(): void
    {
        $student = $this->userId('student@edupulse.test');
        $notEnrolled = (int) $this->scalar(
            'SELECT COUNT(*) FROM courses c WHERE NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.user_id = ?)',
            [$student]
        );
        $this->assertGreaterThanOrEqual(1, $notEnrolled);
    }

    public function test_seed_files_exist_for_every_stored_file(): void
    {
        $paths = query(
            "SELECT file_path FROM materials
             UNION ALL SELECT file_path FROM assignments WHERE file_path IS NOT NULL
             UNION ALL SELECT file_path FROM submissions"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertNotEmpty($paths);
        foreach ($paths as $path) {
            $this->assertFileExists(PROJECT_ROOT . '/storage/uploads/' . $path);
        }
    }

    public function test_emails_are_stored_lowercase(): void
    {
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM users WHERE BINARY email <> LOWER(email)'));
    }
}
