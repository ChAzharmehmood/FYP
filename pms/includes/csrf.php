<?php
/** CSRF protection: one token per session, required on every state-changing request. */
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Returns true when the submitted token matches (POST field or X-CSRF-Token header). */
function csrf_check(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/**
 * Abort the request unless the CSRF token is valid. Call at the top of every POST handler.
 * Responds 400 (Apache turns unregistered codes such as 419 into 500, so 400 is used).
 */
function csrf_verify(): void
{
    if (csrf_check()) {
        return;
    }
    error_page(400, 'Form expired', 'Your form session expired or the request was invalid. Go back, reload the page and try again.');
}
