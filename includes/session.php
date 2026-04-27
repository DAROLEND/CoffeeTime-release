<?php
/**
 * Coffee Time — Secure session bootstrap
 *
 * Include ONCE at the very top of every entry-point PHP file,
 * BEFORE any output is sent (headers must still be available).
 *
 * Already included transitively through header.php for front-end pages.
 * Admin files and standalone AJAX endpoints include it directly.
 */

if (session_status() !== PHP_SESSION_NONE) {
    return; // Already started — nothing to do
}

/* ── Cookie security flags ─────────────────────────────────────────────────── */
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,            // Session cookie (expires on browser close)
    'path'     => '/',
    'domain'   => $cookieParams['domain'],
    'secure'   => false,        // Set to true when HTTPS is live on production
    'httponly' => true,         // JS cannot read the cookie
    'samesite' => 'Lax',        // Blocks cross-site POST; Strict breaks OAuth flows
]);

/* ── Strict mode: reject unrecognised session IDs ──────────────────────────── */
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');   // No session IDs in URLs
ini_set('session.gc_maxlifetime', '3600'); // 1-hour server-side lifetime

session_start();

/* ── Session fixation: regenerate after 30-minute idle ─────────────────────── */
$idleTimeout = 1800; // 30 minutes
if (
    isset($_SESSION['_last_activity']) &&
    (time() - $_SESSION['_last_activity']) > $idleTimeout
) {
    // Session is idle — regenerate the ID to limit reuse window
    session_regenerate_id(true);
}
$_SESSION['_last_activity'] = time();
