<?php
/**
 * Mobile API bootstrap.
 *
 * Every endpoint requires this file first. It:
 *   - loads configuration from includes/api_config.php (outside the web root),
 *   - opens the PDO connection,
 *   - sets the JSON content type and CORS policy,
 *   - and exposes the token + authorization helpers used to guard each endpoint.
 *
 * Authentication is a stateless bearer token (an HMAC-signed JSON payload, i.e.
 * a JWT in shape) so the Flutter client can authenticate without cookies and
 * without any third-party library. The login endpoints mint a token; every other
 * endpoint requires one and derives the caller's identity from it rather than
 * trusting IDs supplied in the request.
 */

// Locate the config, which lives outside the web root. Supports both the repo
// layout (public/api → ../../includes) and a flat deployment (api → ../includes).
// Required at file scope (not inside a function) so $host/$username/$password/
// $dbname land in the scope every endpoint shares with this bootstrap.
$_remoco_cfg = null;
foreach ([__DIR__ . '/../../includes/api_config.php', __DIR__ . '/../includes/api_config.php'] as $_c) {
    if (is_file($_c)) { $_remoco_cfg = $_c; break; }
}
if ($_remoco_cfg === null) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Service not configured.']);
    exit;
}
require_once $_remoco_cfg;

header('Content-Type: application/json; charset=utf-8');

// CORS: never '*'. Echo a single configured origin only when it matches.
if (defined('API_ALLOWED_ORIGIN') && API_ALLOWED_ORIGIN !== '') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === API_ALLOWED_ORIGIN) {
        header('Access-Control-Allow-Origin: ' . API_ALLOWED_ORIGIN);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Send a JSON response and stop.
 */
function api_respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

/**
 * Uniform error. Detail is logged, never returned — clients get a generic message.
 */
function api_error(string $message, int $status = 400, ?Throwable $e = null): void
{
    if ($e !== null) {
        error_log('[remoco-api] ' . $message . ' :: ' . $e->getMessage());
    }
    api_respond(['status' => 'error', 'message' => $message], $status);
}

/* ----------------------------------------------------------------- database */

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    api_error('Service temporarily unavailable.', 503, $e);
}

/* -------------------------------------------------------------- token layer */

/** base64url without padding. */
function b64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function b64url_decode(string $txt): string
{
    return base64_decode(strtr($txt, '-_', '+/'));
}

/**
 * Mint a signed token for a set of claims. Adds iat/exp automatically.
 *
 * @param array $claims e.g. ['sub' => 42, 'type' => 'employee',
 *                            'company_id' => 'ABC123', 'designation' => 'Team Lead']
 */
function issue_token(array $claims): string
{
    $now = time();
    $payload = $claims + ['iat' => $now, 'exp' => $now + API_TOKEN_TTL];
    $body = b64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sig  = b64url_encode(hash_hmac('sha256', $body, API_TOKEN_SECRET, true));
    return $body . '.' . $sig;
}

/**
 * Verify a token's signature and expiry. Returns the claims, or null if invalid.
 */
function verify_token(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$body, $sig] = $parts;
    $expected = b64url_encode(hash_hmac('sha256', $body, API_TOKEN_SECRET, true));
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    $claims = json_decode(b64url_decode($body), true);
    if (!is_array($claims) || !isset($claims['exp']) || time() >= (int) $claims['exp']) {
        return null;
    }
    return $claims;
}

/**
 * Read the bearer token from the Authorization header.
 */
function bearer_token(): ?string
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if ($hdr === '' && function_exists('apache_request_headers')) {
        $all = apache_request_headers();
        $hdr = $all['Authorization'] ?? $all['authorization'] ?? '';
    }
    if (preg_match('/Bearer\s+(.+)/i', $hdr, $m)) {
        return trim($m[1]);
    }
    return null;
}

/**
 * Guard: require a valid token and return its claims, or answer 401 and stop.
 * Every protected endpoint calls this first.
 */
function require_auth(): array
{
    $token = bearer_token();
    if ($token === null) {
        api_error('Authentication required.', 401);
    }
    $claims = verify_token($token);
    if ($claims === null) {
        api_error('Your session has expired. Please sign in again.', 401);
    }
    return $claims;
}

/** Require the caller to be a company (admin) token. */
function require_company(): array
{
    $c = require_auth();
    if (($c['type'] ?? '') !== 'company') {
        api_error('This action is not available for your account.', 403);
    }
    return $c;
}

/** Require an employee token; optionally restrict to one or more designations. */
function require_employee(array $designations = []): array
{
    $c = require_auth();
    if (($c['type'] ?? '') !== 'employee') {
        api_error('This action is not available for your account.', 403);
    }
    if ($designations && !in_array($c['designation'] ?? '', $designations, true)) {
        api_error('This action is not available for your role.', 403);
    }
    return $c;
}

/**
 * The company the caller belongs to — from the token, never the request.
 */
function caller_company(array $claims): string
{
    return (string) ($claims['company_id'] ?? '');
}

/* --------------------------------------------------------- request payload */

/**
 * Decode a JSON request body once. Returns [] if absent or malformed.
 */
function json_body(): array
{
    static $cache = null;
    if ($cache === null) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '', true);
        $cache = is_array($decoded) ? $decoded : [];
    }
    return $cache;
}

/**
 * Confirm a task belongs to the caller's company (tenancy check for task-scoped
 * endpoints). Returns the task row, or answers and stops.
 */
function require_task_in_company(PDO $pdo, int $task_id, string $company_id): array
{
    $stmt = $pdo->prepare(
        "SELECT t.* FROM tasks t
         LEFT JOIN employees pm ON t.assigned_by  = pm.employee_id
         LEFT JOIN employees tl ON t.team_lead_id = tl.employee_id
         LEFT JOIN employees m1 ON t.tm1 = m1.employee_id
         LEFT JOIN employees m2 ON t.tm2 = m2.employee_id
         LEFT JOIN employees m3 ON t.tm3 = m3.employee_id
         WHERE t.task_id = ?
           AND ? IN (pm.company_id, tl.company_id, m1.company_id, m2.company_id, m3.company_id)"
    );
    $stmt->execute([$task_id, $company_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        api_error('Task not found.', 404);
    }
    return $task;
}

/**
 * Confirm the caller is a participant of a chat (its PM, TL, or a member).
 * Returns the chat row, or answers and stops.
 */
function require_chat_participant(PDO $pdo, int $chat_id, int $employee_id): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM chats
         WHERE chat_id = ? AND ? IN (pm_id, tl_id, tm1_id, tm2_id, tm3_id)"
    );
    $stmt->execute([$chat_id, $employee_id]);
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$chat) {
        api_error('Chat not found.', 404);
    }
    return $chat;
}
