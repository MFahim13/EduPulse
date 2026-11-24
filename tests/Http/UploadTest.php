<?php
declare(strict_types=1);

final class UploadTest extends HttpTestCase
{
    private function fixture(string $name): string
    {
        return PROJECT_ROOT . '/tests/fixtures/' . $name;
    }

    private function courseId(string $code): int
    {
        return (int) $this->scalar('SELECT id FROM courses WHERE course_code = ?', [$code]);
    }

    private function storedFiles(string $folder): array
    {
        return array_values(array_filter(
            scandir(PROJECT_ROOT . '/storage/uploads/' . $folder),
            fn ($f) => $f[0] !== '.'
        ));
    }

    private function addModule(string $title, array $files): HttpResponse
    {
        $course = $this->courseId('CS101');
        return $this->submitForm("/course_detail.php?id={$course}", '/actions/course.php', [
            'action' => 'add_module',
            'course_id' => $course,
            'module_title' => $title,
        ], $files);
    }

    private function materialId(string $fileName): int
    {
        return (int) $this->scalar('SELECT id FROM materials WHERE file_name = ?', [$fileName]);
    }

    private function seededMaterialIn(string $code): int
    {
        return (int) $this->scalar(
            'SELECT m.id FROM materials m JOIN modules mo ON mo.id = m.module_id JOIN courses c ON c.id = mo.course_id WHERE c.course_code = ? LIMIT 1',
            [$code]
        );
    }

    public function test_php_material_upload_rejected(): void
    {
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $before = $this->storedFiles('materials');
        $materials = (int) $this->scalar('SELECT COUNT(*) FROM materials');

        $response = $this->addModule('Evil Module', ['materials[0]' => ['path' => $this->fixture('shell.php'), 'name' => 'shell.php']]);

        $this->assertStringContainsString('not allowed', $this->follow($response)->body);
        $this->assertSame($materials, (int) $this->scalar('SELECT COUNT(*) FROM materials'));
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM modules WHERE title = 'Evil Module'"));
        $this->assertSame($before, $this->storedFiles('materials'));
    }

    public function test_material_not_reachable_by_public_url(): void
    {
        $key = (string) $this->scalar('SELECT file_path FROM materials LIMIT 1');
        $this->assertStringStartsWith('materials/', $key);
        $this->assertSame(404, $this->get('/storage/uploads/' . $key)->status);
        $this->assertSame(404, $this->get('/assets/uploads/' . $key)->status);
    }

    public function test_download_allowed_for_owner_and_enrolled_student(): void
    {
        $id = $this->seededMaterialIn('CS101');

        $this->loginAs('student@edupulse.test', 'Student123!');
        $asStudent = $this->get("/download.php?type=material&id={$id}");
        $this->assertSame(200, $asStudent->status);
        $this->assertStringStartsWith('attachment', (string) $asStudent->header('content-disposition'));
        $this->assertSame('nosniff', $asStudent->header('x-content-type-options'));
        $this->assertStringStartsWith('%PDF', $asStudent->body);

        $this->cookies = [];
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $this->assertSame(200, $this->get("/download.php?type=material&id={$id}")->status);
    }

    public function test_download_denied_for_unenrolled_student_and_other_instructor(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertSame(403, $this->get('/download.php?type=material&id=' . $this->seededMaterialIn('MD401'))->status);

        $this->cookies = [];
        $this->loginAs('instructor2@edupulse.test', 'Instructor123!');
        $this->assertSame(403, $this->get('/download.php?type=material&id=' . $this->seededMaterialIn('CS101'))->status);

        $this->cookies = [];
        $this->assertSame(302, $this->get('/download.php?type=material&id=' . $this->seededMaterialIn('CS101'))->status);
    }

    public function test_assignment_attachment_follows_course_access(): void
    {
        $id = (int) $this->scalar(
            "SELECT a.id FROM assignments a JOIN courses c ON c.id = a.course_id WHERE c.course_code = 'CS101' AND a.file_path IS NOT NULL LIMIT 1"
        );
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertSame(200, $this->get("/download.php?type=assignment&id={$id}")->status);

        $this->cookies = [];
        $this->loginAs('student2@edupulse.test', 'Student123!');
        $other = (int) $this->scalar(
            "SELECT a.id FROM assignments a JOIN courses c ON c.id = a.course_id WHERE c.course_code = 'WD301' AND a.file_path IS NOT NULL LIMIT 1"
        );
        $this->assertSame(403, $this->get("/download.php?type=assignment&id={$other}")->status);
    }

    public function test_submission_download_only_owner_and_submitter(): void
    {
        $id = (int) $this->scalar(
            "SELECT s.id FROM submissions s JOIN users u ON u.id = s.student_id WHERE u.email = 'student@edupulse.test' AND s.grade IS NOT NULL LIMIT 1"
        );

        $this->loginAs('student@edupulse.test', 'Student123!');
        $mine = $this->get("/download.php?type=submission&id={$id}");
        $this->assertSame(200, $mine->status);
        $this->assertStringContainsString('Ali Raza', rawurldecode((string) $mine->header('content-disposition')));

        $this->cookies = [];
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $this->assertSame(200, $this->get("/download.php?type=submission&id={$id}")->status);

        $this->cookies = [];
        $this->loginAs('student2@edupulse.test', 'Student123!');
        $this->assertSame(403, $this->get("/download.php?type=submission&id={$id}")->status);
    }

    public function test_download_unknown_type_or_id_is_404(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->assertSame(404, $this->get('/download.php?type=material&id=999999')->status);
        $this->assertSame(404, $this->get('/download.php?type=../../config&id=1')->status);
    }

    public function test_unicode_filename_kept_and_escaped(): void
    {
        $this->loginAs('instructor@edupulse.test', 'Instructor123!');
        $original = 'Week 1 — Intro <b>.pdf';

        $response = $this->addModule('Unicode Module', ['materials[0]' => ['path' => $this->fixture('sample.pdf'), 'name' => $original]]);
        $page = $this->follow($response);

        $this->assertStringContainsString('Week 1 — Intro &lt;b&gt;.pdf', $page->body);
        $this->assertStringNotContainsString('Intro <b>', $page->body);

        $key = (string) $this->scalar('SELECT file_path FROM materials WHERE file_name = ?', [$original]);
        $this->assertMatchesRegularExpression('#^materials/[0-9a-f]{32}\.pdf$#', $key);

        $download = $this->get('/download.php?type=material&id=' . $this->materialId($original));
        $this->assertStringContainsString("filename*=UTF-8''" . rawurlencode($original), (string) $download->header('content-disposition'));
    }

    public function test_oversize_post_shows_size_error_not_403(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $big = tempnam(sys_get_temp_dir(), 'big');
        file_put_contents($big, str_repeat('A', 13 * 1024 * 1024));
        $assignment = (int) $this->scalar("SELECT id FROM assignments WHERE title = 'Loops Challenge'");

        try {
            $page = "/assignment.php?id={$assignment}";
            $this->get($page);
            $response = $this->submitForm($page, '/actions/assignment.php', [
                'action' => 'submit', 'assignment_id' => $assignment,
            ], ['submission_file' => ['path' => $big, 'name' => 'huge.pdf']]);
        } finally {
            unlink($big);
        }

        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('File too large (max 10 MB).', $this->follow($response)->body);
    }

    public function test_php_in_public_uploads_not_executed(): void
    {
        $probe = PROJECT_ROOT . '/public/assets/uploads/profiles/probe.php';
        file_put_contents($probe, '<?php echo "EXEC";');
        try {
            $response = $this->get('/assets/uploads/profiles/probe.php');
            $this->assertSame(403, $response->status);
            $this->assertStringNotContainsString('EXEC', $response->body);
        } finally {
            unlink($probe);
        }
    }

    public function test_profile_photo_must_be_real_image(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');

        $bad = $this->submitForm('/profile.php', '/actions/profile.php', ['action' => 'upload_photo'],
            ['profile_photo' => ['path' => $this->fixture('fake.png'), 'name' => 'me.png']]);
        $this->assertStringContainsString('real JPG or PNG image', $this->follow($bad)->body);

        $good = $this->submitForm('/profile.php', '/actions/profile.php', ['action' => 'upload_photo'],
            ['profile_photo' => ['path' => $this->fixture('sample.png'), 'name' => 'me.png']]);
        $this->assertStringContainsString('Profile picture updated', $this->follow($good)->body);

        $key = (string) $this->scalar("SELECT profile_picture FROM users WHERE email = 'student@edupulse.test'");
        $this->assertMatchesRegularExpression('#^assets/uploads/profiles/[0-9a-f]{32}\.png$#', $key);
        $this->assertSame(200, $this->get('/' . $key)->status);
    }
}
