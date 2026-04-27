<?php
/**
 * Coffee Time — Admin authentication gate
 *
 * Include at the top of EVERY admin/*.php file (after db.php):
 *
 *   require_once __DIR__ . '/auth_check.php';
 *
 * This file:
 *  1. Starts/joins the session securely
 *  2. Checks $_SESSION['admin'] is set
 *  3. Verifies the username still exists in admin_users
 *  4. Loads role + permissions into session
 *  5. For AJAX requests returns JSON 401; for normal requests redirects to login
 */

if (session_status() === PHP_SESSION_NONE) {
    $cp = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $cp['domain'],
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

/* ── Migrate admin_users table if needed ── */
if (isset($conn)) {
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM admin_users");
    if ($r) while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];

    if (!in_array('role', $cols)) {
        $conn->query("ALTER TABLE admin_users ADD COLUMN role ENUM('super','staff') NOT NULL DEFAULT 'staff'");
        // First existing admin becomes super
        $conn->query("UPDATE admin_users SET role='super' ORDER BY id ASC LIMIT 1");
    }
    if (!in_array('permissions', $cols)) {
        $conn->query("ALTER TABLE admin_users ADD COLUMN permissions TEXT NOT NULL DEFAULT '[]'");
    }
    if (!in_array('display_name', $cols)) {
        $conn->query("ALTER TABLE admin_users ADD COLUMN display_name VARCHAR(100) NOT NULL DEFAULT ''");
    }
}

$_isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/* ── 1. Session check ── */
if (empty($_SESSION['admin'])) {
    if ($_isAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    header('Location: ../forms/login.php');
    exit;
}

/* ── 2. DB verification + load role/perms ── */
if (!isset($conn)) {
    error_log('[auth_check] $conn not available');
    http_response_code(500);
    exit;
}

$_adminUsername = $_SESSION['admin'];
$_as = $conn->prepare("SELECT username, role, permissions, display_name FROM admin_users WHERE username = ? LIMIT 1");
$_as->bind_param('s', $_adminUsername);
$_as->execute();
$_adminRow = $_as->get_result()->fetch_assoc();
$_as->close();

if (!$_adminRow) {
    session_destroy();
    if ($_isAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Session revoked']);
        exit;
    }
    header('Location: ../forms/login.php');
    exit;
}

/* Store role + permissions in session (refresh every request) */
$_SESSION['admin_role']  = $_adminRow['role'] ?? 'staff';
$_SESSION['admin_perms'] = json_decode($_adminRow['permissions'] ?? '[]', true) ?: [];
if (!empty($_adminRow['display_name'])) {
    $_SESSION['admin_display'] = $_adminRow['display_name'];
}

require_once __DIR__ . '/includes/perm.php';

unset($_isAjax, $_adminUsername, $_as, $_adminRow);
