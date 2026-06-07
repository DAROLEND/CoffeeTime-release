<?php
require_once dirname(__DIR__) . '/includes/env.php';

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: '');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $socket = getenv('DB_SOCKET') ?: ini_get('mysqli.default_socket');
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3306, $socket);
    $conn->set_charset(DB_CHARSET);
} catch (mysqli_sql_exception $e) {
    error_log('[DB] MySQLi connection failed: ' . $e->getMessage());
    http_response_code(500);
    $page500 = dirname(__DIR__) . '/errors/500.php';
    file_exists($page500) ? include $page500 : die('Database connection error.');
    exit;
}

try {
    $pdoDsn = isset($socket) && $socket
        ? 'mysql:unix_socket=' . $socket . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET
        : 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO(
        $pdoDsn,
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
