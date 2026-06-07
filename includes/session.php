<?php
if (session_status() !== PHP_SESSION_NONE) {
    return; // Already started — nothing to do
}

$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,            // Session cookie (expires on browser close)
    'path'     => '/',
    'domain'   => $cookieParams['domain'],
    'secure'   => false,        // Set to true when HTTPS is live on production
    'httponly' => true,         // JS cannot read the cookie
    'samesite' => 'Lax',        // Blocks cross-site POST; Strict breaks OAuth flows
]);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');   // No session IDs in URLs
ini_set('session.gc_maxlifetime', '3600'); // 1-hour server-side lifetime

session_start();

$idleTimeout = 1800; // 30 minutes
if (
    isset($_SESSION['_last_activity']) &&
    (time() - $_SESSION['_last_activity']) > $idleTimeout
) {
    // Session is idle — regenerate the ID to limit reuse window
    session_regenerate_id(true);
}
$_SESSION['_last_activity'] = time();
