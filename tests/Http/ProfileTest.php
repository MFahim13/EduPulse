<?php
declare(strict_types=1);

final class ProfileTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        seed_database(db(), PROJECT_ROOT);
    }

    private function save(array $overrides = []): HttpResponse
    {
        return $this->submitForm('/profile.php', '/actions/profile.php', $overrides + [
            'action' => 'update_profile', 'name' => 'Ali Raza', 'email' => 'student@edupulse.test',
            'phone' => '', 'dob' => '', 'bio' => 'Updated bio.',
        ]);
    }

    public function test_saving_with_empty_date_of_birth_works(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $page = $this->follow($this->save());

        $this->assertStringContainsString('Profile updated successfully', $page->body);
        $row = query("SELECT bio, dob FROM users WHERE email = 'student@edupulse.test'")->fetch();
        $this->assertSame(['bio' => 'Updated bio.', 'dob' => null], $row);
    }

    public function test_valid_date_is_saved_and_invalid_date_rejected(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $this->follow($this->save(['dob' => '2004-03-15']));
        $this->assertSame('2004-03-15', $this->scalar("SELECT dob FROM users WHERE email = 'student@edupulse.test'"));

        $page = $this->follow($this->save(['dob' => '2004-02-30']));
        $this->assertStringContainsString('Please enter a valid date of birth.', $page->body);
        $this->assertSame('2004-03-15', $this->scalar("SELECT dob FROM users WHERE email = 'student@edupulse.test'"));
    }

    public function test_overlong_name_and_phone_are_rejected_not_500(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $page = $this->follow($this->save(['name' => str_repeat('a', 101), 'phone' => str_repeat('1', 31)]));
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('Name must be at most 100 characters.', $page->body);
        $this->assertStringContainsString('Phone number must be at most 30 characters.', $page->body);
    }

    public function test_profile_average_matches_dashboard(): void
    {
        $this->loginAs('student@edupulse.test', 'Student123!');
        $body = $this->get('/profile.php')->body;
        $this->assertStringContainsString('90%', $body);   // 18/20 and 45/50
        $this->assertStringNotContainsString('32%', $body);
    }
}
