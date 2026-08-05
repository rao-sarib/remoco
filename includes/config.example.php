<?php
/**
 * REMOCO — application configuration (template).
 *
 * SETUP:  copy this file to `config.php` in this same directory, then fill in
 *         your own values.
 *
 *     cp includes/config.example.php includes/config.php
 *
 * `includes/config.php` is listed in .gitignore and must never be committed.
 * It sits outside public/ so it is not reachable over HTTP.
 *
 * Every value below may instead be supplied as a real environment variable
 * (see .env.example for the variable names). Environment variables take
 * precedence; the literals here are the local-development fallback.
 */

// ---------------------------------------------------------------------------
// Timezone
// ---------------------------------------------------------------------------
// Set this to match your MySQL server's timezone. Date comparisons happen in
// SQL and PHP only formats what it reads back, so the two disagreeing is not
// immediately visible - but it will produce an off-by-one date the first time
// any code compares a PHP date with a stored one. Check the server with:
//
//     SELECT @@system_time_zone, @@session.time_zone;
//
date_default_timezone_set(getenv('REMOCO_TIMEZONE') ?: 'UTC');

// ---------------------------------------------------------------------------
// Database (MySQL)
// ---------------------------------------------------------------------------
// These four variable names are used directly by the page scripts, so they
// are intentionally plain variables rather than constants.

$host     = getenv('REMOCO_DB_HOST') ?: 'localhost';
$username = getenv('REMOCO_DB_USER') ?: 'root';
$password = getenv('REMOCO_DB_PASSWORD') ?: '';
$dbname   = getenv('REMOCO_DB_NAME') ?: 'remoco_db';

// ---------------------------------------------------------------------------
// Firebase Realtime Database — powers the in-app chat
// ---------------------------------------------------------------------------
// These values come from Firebase Console > Project settings > Your apps.
// The web configuration is delivered to the browser by the Firebase SDK; access
// to the Realtime Database is governed by your project's Security Rules.

define('FIREBASE_API_KEY',             getenv('FIREBASE_API_KEY')             ?: 'your-firebase-web-api-key');
define('FIREBASE_AUTH_DOMAIN',         getenv('FIREBASE_AUTH_DOMAIN')         ?: 'your-project.firebaseapp.com');
define('FIREBASE_PROJECT_ID',          getenv('FIREBASE_PROJECT_ID')          ?: 'your-project-id');
define('FIREBASE_STORAGE_BUCKET',      getenv('FIREBASE_STORAGE_BUCKET')      ?: 'your-project.firebasestorage.app');
define('FIREBASE_MESSAGING_SENDER_ID', getenv('FIREBASE_MESSAGING_SENDER_ID') ?: 'your-messaging-sender-id');
define('FIREBASE_APP_ID',              getenv('FIREBASE_APP_ID')              ?: 'your-firebase-app-id');

// Realtime Database endpoints.
//
// Two endpoints are configurable: the default (`*.firebaseio.com`), used by the
// dashboards, and the regional one (`*.<region>.firebasedatabase.app`), used by
// the chat pages. Set whichever your project uses; they may be the same value.
// See docs/ROADMAP.md.

define('FIREBASE_DATABASE_URL',          getenv('FIREBASE_DATABASE_URL')          ?: 'https://your-project-default-rtdb.firebaseio.com');
define('FIREBASE_DATABASE_URL_REGIONAL', getenv('FIREBASE_DATABASE_URL_REGIONAL') ?: 'https://your-project-default-rtdb.your-region.firebasedatabase.app');

// ---------------------------------------------------------------------------
// Agora RTC — powers peer-to-peer video calls
// ---------------------------------------------------------------------------
// Both values come from Agora Console > Project Management. Tokens issued there
// carry an expiry, so refresh AGORA_TOKEN when it lapses. Generating tokens
// server-side from the App Certificate is on the roadmap — see docs/ROADMAP.md.

define('AGORA_APP_ID', getenv('AGORA_APP_ID') ?: 'your-agora-app-id');
define('AGORA_TOKEN',  getenv('AGORA_TOKEN')  ?: 'your-agora-temp-token');
