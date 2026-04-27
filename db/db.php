<?php
/**
 * Coffee Time — Database connection
 *
 * Provides:
 *   $conn  (mysqli)  — used by all existing queries
 *   $pdo   (PDO)     — available for new security-sensitive code
 *
 * Credentials come exclusively from .env → never hardcoded here.
 */

require_once dirname(__DIR__) . '/includes/env.php';

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: '');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

/* ── MySQLi connection ($conn) — used by existing code ─────────────────────── */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset(DB_CHARSET);
} catch (mysqli_sql_exception $e) {
    error_log('[DB] MySQLi connection failed: ' . $e->getMessage());
    http_response_code(500);
    $page500 = dirname(__DIR__) . '/errors/500.php';
    file_exists($page500) ? include $page500 : die('Database connection error.');
    exit;
}

/* ── PDO connection ($pdo) — for new security-focused code ─────────────────── */
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[DB] PDO connection failed: ' . $e->getMessage());
    http_response_code(500);
    $page500 = dirname(__DIR__) . '/errors/500.php';
    file_exists($page500) ? include $page500 : die('Database connection error.');
    exit;
}
