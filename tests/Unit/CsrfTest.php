<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_token_is_64_hex_and_stable_within_session(): void
    {
        $token = csrf_token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, csrf_token());

        $_SESSION = [];
        $this->assertNotSame($token, csrf_token());
    }

    public function test_csrf_valid_rejects_missing_and_wrong_accepts_correct(): void
    {
        $token = csrf_token();
        $this->assertFalse(csrf_valid(null));
        $this->assertFalse(csrf_valid(''));
        $this->assertFalse(csrf_valid(str_repeat('0', 64)));
        $this->assertTrue(csrf_valid($token));
    }

    public function test_csrf_field_renders_hidden_input(): void
    {
        $token = csrf_token();
        $this->assertSame('<input type="hidden" name="_csrf" value="' . $token . '">', csrf_field());
    }
}
