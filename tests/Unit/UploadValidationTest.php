<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UploadValidationTest extends TestCase
{
    private function file(string $fixture, ?string $name = null, ?int $size = null, int $error = UPLOAD_ERR_OK): array
    {
        $path = PROJECT_ROOT . '/tests/fixtures/' . $fixture;
        return [
            'name' => $name ?? $fixture,
            'tmp_name' => $path,
            'size' => $size ?? filesize($path),
            'error' => $error,
            'type' => 'application/octet-stream',
        ];
    }

    private function assertRejected(array $file, string $purpose, string $messagePart = ''): void
    {
        try {
            validate_upload($file, $purpose);
            $this->fail("{$file['name']} was accepted for {$purpose}");
        } catch (UploadError $e) {
            if ($messagePart !== '') {
                $this->assertStringContainsString($messagePart, $e->getMessage());
            }
        }
    }

    public function test_rules_match_spec(): void
    {
        $this->assertSame(['pdf', 'doc', 'docx', 'zip'], upload_rules('submission')['exts']);
        $this->assertSame(10 * 1024 * 1024, upload_rules('material')['max_bytes']);
        $this->assertSame(2 * 1024 * 1024, upload_rules('profile')['max_bytes']);
        $this->assertTrue(upload_rules('assignment')['private']);
        $this->assertFalse(upload_rules('profile')['private']);
    }

    public function test_rejects_php_for_every_purpose(): void
    {
        foreach (['material', 'assignment', 'submission', 'profile'] as $purpose) {
            $this->assertRejected($this->file('shell.php'), $purpose, 'not allowed');
        }
    }

    public function test_rejects_disguised_php(): void
    {
        $this->assertRejected($this->file('report.php.pdf'), 'submission');
        $this->assertRejected($this->file('shell.php', 'shell.pHp'), 'material', 'not allowed');
        $this->assertRejected($this->file('report.php.pdf', 'notes.PDF'), 'material');
    }

    public function test_accepts_real_pdf_submission(): void
    {
        $this->assertSame('pdf', validate_upload($this->file('sample.pdf', 'My Work.PDF'), 'submission'));
    }

    public function test_rejects_file_over_limit(): void
    {
        $this->assertRejected($this->file('sample.pdf', null, 10 * 1024 * 1024 + 1), 'submission', 'File too large (max 10 MB).');
        $this->assertRejected($this->file('sample.pdf', null, 0, UPLOAD_ERR_INI_SIZE), 'submission', 'File too large');
    }

    public function test_profile_requires_real_image(): void
    {
        $this->assertRejected($this->file('fake.png'), 'profile');
        $this->assertSame('png', validate_upload($this->file('sample.png'), 'profile'));
    }

    public function test_rejects_missing_extension(): void
    {
        $this->assertRejected($this->file('sample.pdf', 'README'), 'material', 'extension');
    }

    public function test_rejects_missing_file(): void
    {
        $this->assertRejected($this->file('sample.pdf', '', 0, UPLOAD_ERR_NO_FILE), 'submission', 'Please choose a file');
    }

    public function test_delete_upload_refuses_traversal(): void
    {
        $victim = PROJECT_ROOT . '/storage/uploads/victim-test.txt';
        file_put_contents($victim, 'x');
        try {
            delete_upload('materials/../victim-test.txt');
            $this->assertFileExists($victim);
            delete_upload(null);
        } finally {
            @unlink($victim);
        }
    }
}
