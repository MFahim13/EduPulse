<?php
declare(strict_types=1);

/** One random token per session, sent with every form. */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_valid(?string $token): bool
{
    $expected = $_SESSION['_csrf'] ?? null;
    return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
}

/** Stop the request with 403 unless the posted token matches the session. */
function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? null;
    if (!csrf_valid(is_string($token) ? $token : null)) {
        render_error_page(
            403,
            'Request blocked',
            'This form expired or was sent from somewhere else. Go back, reload the page and try again.'
        );
    }
}
