<?php
/**
 * Session bootstrap.
 *
 * Every entry point requires this file first. It centralises three things:
 *
 *   1. Hardened session cookie parameters (HttpOnly, SameSite, Secure over TLS).
 *      These must be set BEFORE the session starts, which is why this file has
 *      to be required at the very top of a page - ahead of any output.
 *   2. Starting the session exactly once, whether the page is served directly or
 *      included by a dashboard shell.
 *   3. A per-session CSRF token plus the helpers used to emit and verify it.
 *
 * Requiring this file more than once in a request is safe.
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   // only advertised over TLS, so plain-HTTP dev still works
        'httponly' => true,       // no JavaScript in this app reads the session cookie
        'samesite' => 'Lax',      // blocks cross-site POST while keeping normal navigation
    ]);

    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * The raw token, for cases that build their own markup or JavaScript.
 */
function csrf_token(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Hidden input to drop straight into a <form>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Token appended to a URL, for the few links that trigger a state change.
 */
function csrf_query(): string
{
    return 'csrf_token=' . urlencode(csrf_token());
}

/**
 * Constant-time check of a submitted token. Accepts POST body, query string, or
 * the X-CSRF-Token header (used by the AJAX endpoints).
 */
function csrf_valid(?string $candidate = null): bool
{
    if ($candidate === null) {
        $candidate = $_POST['csrf_token']
            ?? $_GET['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';
    }
    $expected = $_SESSION['csrf_token'] ?? '';

    return is_string($candidate) && $candidate !== '' && $expected !== ''
        && hash_equals($expected, $candidate);
}

/**
 * Guard for page requests: stop the request if the token is absent or wrong.
 */
function csrf_require_page(): void
{
    if (!csrf_valid()) {
        http_response_code(419);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Your session has expired or the request could not be verified.\n"
           . "Please go back, reload the page and try again.";
        exit;
    }
}

/**
 * Guard for the JSON endpoints, which must answer in JSON rather than HTML.
 */
function csrf_require_json(): void
{
    if (!csrf_valid()) {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Request could not be verified. Please reload the page.',
        ]);
        exit;
    }
}
