<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$filename   = 'coffeetime_backup_' . date('Y-m-d_H-i-s') . '.sql';
$backupDir  = dirname(__DIR__) . '/backups/';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0750, true);
}

$tmpFile = $backupDir . $filename;

// Build mysqldump command
// Credentials come from db.php constants/variables
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'CoffeeTime';

$cmd = sprintf(
    'mysqldump --user=%s --password=%s --host=%s --single-transaction --routines %s > %s 2>&1',
    escapeshellarg($user),
    escapeshellarg($pass),
    escapeshellarg($host),
    escapeshellarg($db),
    escapeshellarg($tmpFile)
);

exec($cmd, $output, $exitCode);

if ($exitCode !== 0 || !file_exists($tmpFile)) {
    http_response_code(500);
    echo '<p>Помилка створення резервної копії. Перевірте, чи встановлено mysqldump.</p>';
    echo '<pre>' . h(implode("\n", $output)) . '</pre>';
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-store');
readfile($tmpFile);
unlink($tmpFile);
exit;
