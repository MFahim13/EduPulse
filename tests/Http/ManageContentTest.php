<?php
declare(strict_types=1);

final class ManageContentTest extends HttpTestCase
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

    private function assignmentId(string $title): int
    {
        return (int) $this->scalar('SELECT id FROM assignments WHERE title = ?', [$title]);
    }

    private function storageFile(string $key): string
    {
        return PROJECT_ROOT . '/storage/uploads/' . $key;
    }

    private function act(string $endpoint, array $fields): HttpResponse
    {
        return $this->submitForm('/profile.php', $endpoint, $fields);
    }

    private function courseFields(int $course, array $overrides = []): array
    {
        return $overrides + [
            'action' => 'update', 'course_id' => $course, 'title' => 'Intro to Programming (2026)',
            'course_code' => 'CS101', 'category' => 'Programming',
            'description' => 'Updated description.', 'enrollment_limit' => '45',
        ];
    }

    public function test_owner_edits_course(): void
    {
        $course = $this->courseId('CS101');
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $page = $this->follow($this->act('/actions/course.php', $this->courseFields($course)));

        $this->assertStringContainsString('Course updated.', $page->body);
        $row = query('SELECT title, description, enrollment_limit FROM courses WHERE id = ?', [$course])->fetch();
        $this->assertSame(['title' => 'Intro to Programming (2026)', 'description' => 'Updated description.', 'enrollment_limit' => 45], $row);
    }

    public function test_edited_course_code_must_stay_unique(): void
    {
        $course = $this->courseId('CS101');
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $page = $this->follow($this->act('/actions/course.php', $this->courseFields($course, ['course_code' => 'DB201'])));

        $this->assertStringContainsString('Course code already exists', $page->body);
        $this->assertSame('CS101', $this->scalar('SELECT course_code FROM courses WHERE id = ?', [$course]));
    }

    public function test_limit_cannot_drop_below_enrollment(): void
    {
        $course = $this->courseId('CS101'); // 6 enrolled
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $page = $this->follow($this->act('/actions/course.php', $this->courseFields($course, ['enrollment_limit' => '5'])));

        $this->assertStringContainsString(e("Limit can't be lower than the 6 students already enrolled."), $page->body);
        $this->assertSame(40, (int) $this->scalar('SELECT enrollment_limit FROM courses WHERE id = ?', [$course]));
    }

    public function test_owner_edits_assignment(): void
    {
        $assignment = $this->assignmentId('Loops Challenge');
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $page = $this->follow($this->act('/actions/assignment.php', [
            'action' => 'update', 'assignment_id' => $assignment, 'title' => 'Loops Challenge v2',
            'description' => 'New instructions.', 'due_date' => '2030-05-01T09:30', 'max_points' => '30',
        ]));

        $this->assertStringContainsString('Assignment updated.', $page->body);
        $row = query('SELECT title, description, due_date, max_points FROM assignments WHERE id = ?', [$assignment])->fetch();
        $this->assertSame(['title' => 'Loops Challenge v2', 'description' => 'New instructions.', 'due_date' => '2030-05-01 09:30:00', 'max_points' => 30], $row);
    }

    public function test_max_points_cannot_drop_below_existing_grade(): void
    {
        $assignment = $this->assignmentId('ER Diagram for a Library'); // grades 45, 48, 38 out of 50
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $page = $this->follow($this->act('/actions/assignment.php', [
            'action' => 'update', 'assignment_id' => $assignment, 'title' => 'ER Diagram for a Library',
            'description' => 'x', 'due_date' => '2030-05-01T09:30', 'max_points' => '40',
        ]));

        $this->assertStringContainsString(e("Max points can't be lower than the highest grade already given (48)."), $page->body);
        $this->assertSame(50, (int) $this->scalar('SELECT max_points FROM assignments WHERE id = ?', [$assignment]));
    }

    public function test_delete_assignment_removes_submissions_and_files(): void
    {
        $assignment = $this->assignmentId('Variables and Control Flow');
        $keys = query('SELECT file_path FROM submissions WHERE assignment_id = ?', [$assignment])->fetchAll(PDO::FETCH_COLUMN);
        $keys[] = (string) $this->scalar('SELECT file_path FROM assignments WHERE id = ?', [$assignment]);
        $this->assertCount(5, $keys); // 4 submissions + the brief

        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $page = $this->follow($this->act('/actions/assignment.php', ['action' => 'delete', 'assignment_id' => $assignment]));

        $this->assertStringContainsString('Assignment deleted.', $page->body);
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM submissions WHERE assignment_id = ?', [$assignment]));
        foreach ($keys as $key) {
            $this->assertFileDoesNotExist($this->storageFile($key));
        }
        $gone = $this->get("/assignment.php?id={$assignment}");
        $this->assertSame(302, $gone->status);
    }

    public function test_delete_module_removes_materials_and_files(): void
    {
        $module = (int) $this->scalar(
            "SELECT mo.id FROM modules mo JOIN courses c ON c.id = mo.course_id WHERE c.course_code = 'CS101' AND mo.title = 'Control Flow'"
        );
        $keys = query('SELECT file_path FROM materials WHERE module_id = ?', [$module])->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(2, $keys);

        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $this->follow($this->act('/actions/course.php', ['action' => 'delete_module', 'module_id' => $module]));

        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM modules WHERE id = ?', [$module]));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM materials WHERE module_id = ?', [$module]));
        foreach ($keys as $key) {
            $this->assertFileDoesNotExist($this->storageFile($key));
        }
    }

    public function test_delete_material_removes_file(): void
    {
        $material = query(
            "SELECT m.id, m.file_path FROM materials m JOIN modules mo ON mo.id = m.module_id JOIN courses c ON c.id = mo.course_id WHERE c.course_code = 'CS101' LIMIT 1"
        )->fetch();

        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $page = $this->follow($this->act('/actions/course.php', ['action' => 'delete_material', 'material_id' => $material['id']]));

        $this->assertStringContainsString('Material deleted.', $page->body);
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM materials WHERE id = ?', [$material['id']]));
        $this->assertFileDoesNotExist($this->storageFile($material['file_path']));
    }

    public function test_delete_course_removes_all_files_and_page_redirects(): void
    {
        $course = $this->courseId('CS101');
        $keys = array_merge(
            query('SELECT m.file_path FROM materials m JOIN modules mo ON mo.id = m.module_id WHERE mo.course_id = ?', [$course])->fetchAll(PDO::FETCH_COLUMN),
            query('SELECT file_path FROM assignments WHERE course_id = ? AND file_path IS NOT NULL', [$course])->fetchAll(PDO::FETCH_COLUMN),
            query('SELECT s.file_path FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.course_id = ?', [$course])->fetchAll(PDO::FETCH_COLUMN),
        );
        $this->assertGreaterThan(5, count($keys));

        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $this->act('/actions/course.php', ['action' => 'delete', 'course_id' => $course]);

        foreach ($keys as $key) {
            $this->assertFileDoesNotExist($this->storageFile($key));
        }
        $response = $this->get("/course_detail.php?id={$course}");
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('Course not found.', $this->follow($response)->body);
    }

    public function test_non_owner_cannot_edit_or_delete(): void
    {
        $course = $this->courseId('CS101');
        $assignment = $this->assignmentId('Loops Challenge');
        $module = (int) $this->scalar('SELECT id FROM modules WHERE course_id = ? LIMIT 1', [$course]);
        $material = (int) $this->scalar('SELECT m.id FROM materials m JOIN modules mo ON mo.id = m.module_id WHERE mo.course_id = ? LIMIT 1', [$course]);
        $snapshot = fn () => [
            $this->scalar('SELECT COUNT(*) FROM courses'), $this->scalar('SELECT title FROM courses WHERE id = ?', [$course]),
            $this->scalar('SELECT title FROM assignments WHERE id = ?', [$assignment]),
            $this->scalar('SELECT COUNT(*) FROM modules'), $this->scalar('SELECT COUNT(*) FROM materials'),
            $this->scalar('SELECT COUNT(*) FROM assignments'),
        ];
        $before = $snapshot();

        foreach ([['instructor2@edupulse.test', 'Instructor123!'], ['student@edupulse.test', 'Student123!']] as [$email, $password]) {
            $this->cookies = [];
            $this->loginAs($email, $password);
            $this->act('/actions/course.php', $this->courseFields($course, ['title' => 'Hacked']));
            $this->act('/actions/course.php', ['action' => 'delete', 'course_id' => $course]);
            $this->act('/actions/course.php', ['action' => 'delete_module', 'module_id' => $module]);
            $this->act('/actions/course.php', ['action' => 'delete_material', 'material_id' => $material]);
            $this->act('/actions/assignment.php', [
                'action' => 'update', 'assignment_id' => $assignment, 'title' => 'Hacked', 'description' => 'x',
                'due_date' => '2030-01-01T10:00', 'max_points' => '10',
            ]);
            $this->act('/actions/assignment.php', ['action' => 'delete', 'assignment_id' => $assignment]);
        }

        $this->assertSame($before, $snapshot());
    }
}
