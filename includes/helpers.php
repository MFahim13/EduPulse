<?php
declare(strict_types=1);

/** Escape a value for HTML output. */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL path of the public/ folder, e.g. "" in Docker or "/EduPulse/public" under XAMPP. */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (str_ends_with($dir, '/actions')) {
        $dir = substr($dir, 0, -strlen('/actions'));
    }
    $dir = rtrim($dir, '/');
    return $dir === '.' ? '' : $dir;
}

/** Absolute URL path for a file inside public/. */
function url(string $path): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Queue one or more messages for the next page. $type is "success" or "error". */
function flash(string $type, string|array $messages): void
{
    foreach ((array) $messages as $message) {
        $_SESSION['flash'][$type][] = $message;
    }
}

/** @return array<string, string[]> */
function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** @return array{id: int, name: string, email: string, role: string, profile_picture: ?string}|null */
function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => (string) ($_SESSION['name'] ?? ''),
        'email' => (string) ($_SESSION['email'] ?? ''),
        'role' => (string) ($_SESSION['role'] ?? ''),
        'profile_picture' => $_SESSION['profile_picture'] ?? null,
    ];
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        flash('error', "That page is only for {$role}s.");
        redirect('index.php');
    }
    return $user;
}

/** Up to two uppercase initials, e.g. "Muhammad Fahim" → "MF". */
function initials(string $name): string
{
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($words === []) {
        return '?';
    }
    $letters = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $letters .= mb_strtoupper(mb_substr($word, 0, 1));
    }
    return $letters;
}

function is_demo(): bool
{
    return env('APP_DEMO') === 'true';
}

function configure_errors(string $env): void
{
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('display_errors', $env === 'local' ? '1' : '0');
}

/** Render the shared error page and stop. */
function render_error_page(int $status, string $title, string $message): never
{
    if (!headers_sent()) {
        http_response_code($status);
    }
    $page_title = $title;
    $error_title = $title;
    $error_message = $message;
    include __DIR__ . '/error_page.php';
    exit;
}

/** One-click demo logins: the buttons fill the normal login form and submit it. */
function demo_login_buttons(bool $enabled): string
{
    if (!$enabled) {
        return '';
    }
    $accounts = [
        ['instructor', 'instructor@edupulse.test', 'Instructor123!', 'fa-chalkboard-teacher'],
        ['student', 'student@edupulse.test', 'Student123!', 'fa-user-graduate'],
    ];
    $html = '<div class="demo-logins mt-4"><p class="text-muted small text-center mb-2">Just looking around? Use a demo account:</p><div class="d-grid gap-2">';
    foreach ($accounts as [$role, $email, $password, $icon]) {
        $html .= '<button type="button" class="btn btn-outline-secondary" data-demo-email="' . e($email) . '" data-demo-password="' . e($password) . '">'
            . '<i class="fas ' . $icon . '"></i> Log in as demo ' . $role . '</button>';
    }
    return $html . '</div></div>';
}

/** @return string[] */
function course_categories(): array
{
    return ['Programming', 'Database', 'Web Development', 'Mobile Development'];
}
