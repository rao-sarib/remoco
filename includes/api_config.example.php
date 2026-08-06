<?php
/**
 * REMOCO mobile API — configuration (template).
 *
 * SETUP:  copy this file to `api_config.php` in this same directory, then fill in
 *         your own values.
 *
 *     cp includes/api_config.example.php includes/api_config.php
 *
 * `includes/api_config.php` is git-ignored and lives outside public/, so it is
 * never reachable over HTTP. Environment variables take precedence over the
 * literals here.
 */

// ---------------------------------------------------------------------------
// Timezone — keep in step with the MySQL server so dates agree.
// ---------------------------------------------------------------------------
date_default_timezone_set(getenv('REMOCO_TIMEZONE') ?: 'UTC');

// ---------------------------------------------------------------------------
// Database (MySQL)
// ---------------------------------------------------------------------------
$host     = getenv('REMOCO_DB_HOST') ?: 'localhost';
$username = getenv('REMOCO_DB_USER') ?: 'root';
$password = getenv('REMOCO_DB_PASSWORD') ?: '';
$dbname   = getenv('REMOCO_DB_NAME') ?: 'remoco_db';

// ---------------------------------------------------------------------------
// API auth token secret
// ---------------------------------------------------------------------------
// Signs the bearer tokens the login endpoints issue. MUST be a long random
// string and MUST stay secret — anyone holding it can mint valid tokens for any
// account. Generate one with:  php -r "echo bin2hex(random_bytes(32));"
define('API_TOKEN_SECRET', getenv('API_TOKEN_SECRET') ?: 'replace-me-with-a-64-char-random-hex-string');

// How long an issued token stays valid, in seconds (default 7 days).
define('API_TOKEN_TTL', (int) (getenv('API_TOKEN_TTL') ?: 604800));

// ---------------------------------------------------------------------------
// CORS
// ---------------------------------------------------------------------------
// The Flutter app is not a browser origin, so no cross-origin allowance is
// needed by default. If you serve a web build from a known origin, list it here
// (e.g. 'https://app.example.com'); '*' is deliberately avoided.
define('API_ALLOWED_ORIGIN', getenv('API_ALLOWED_ORIGIN') ?: '');

// --- Agora RTC (video calls) ----------------------------------------------
// The App ID identifies the project and is delivered to the client by design.
define('AGORA_APP_ID', getenv('AGORA_APP_ID') ?: 'your-agora-app-id');
