<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private string $file;
    private array $keys = ['EDU_T_A', 'EDU_T_B', 'EDU_T_C', 'EDU_T_D', 'EDU_T_X'];

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'env');
        foreach ($this->keys as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        foreach ($this->keys as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }

    public function test_parses_values_and_strips_quotes(): void
    {
        file_put_contents($this->file, "EDU_T_A=1\nEDU_T_B=\"two words\"\nEDU_T_C='x'\n");
        load_env($this->file);
        $this->assertSame('1', env('EDU_T_A'));
        $this->assertSame('two words', env('EDU_T_B'));
        $this->assertSame('x', env('EDU_T_C'));
    }

    public function test_skips_comments_and_blank_lines(): void
    {
        file_put_contents($this->file, "# EDU_T_X=1\n\n   \nEDU_T_A=ok\n");
        load_env($this->file);
        $this->assertNull(env('EDU_T_X'));
        $this->assertSame('ok', env('EDU_T_A'));
    }

    public function test_does_not_override_real_environment(): void
    {
        putenv('EDU_T_D=real');
        file_put_contents($this->file, "EDU_T_D=file\n");
        load_env($this->file);
        $this->assertSame('real', env('EDU_T_D'));
    }

    public function test_returns_default_when_missing(): void
    {
        $this->assertSame('d', env('EDU_T_NOPE', 'd'));
        $this->assertNull(env('EDU_T_NOPE'));
    }

    public function test_missing_file_is_ignored(): void
    {
        load_env('/nonexistent/.env');
        $this->assertNull(env('EDU_T_A'));
    }
}
