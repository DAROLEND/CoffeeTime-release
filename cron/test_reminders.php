#!/usr/bin/env php
<?php
/**
 * Test script — run once to verify reminders work.
 * Usage: php cron/test_reminders.php
 * DELETE this file after testing.
 */

define('ROOT', dirname(__DIR__));
require_once ROOT . '/includes/env.php';
load_env(ROOT . '/.env');

// XAMPP needs the socket explicitly from CLI
$host   = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'CoffeeTime';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';

$conn = new mysqli($host, $dbUser, $dbPass, $dbName, 3306, $socket);
if ($conn->connect_error) {
    die("DB error: " . $conn->connect_error . "\n");
}
$conn->set_charset('utf8mb4');
echo "✓ DB connected\n\n";

// ── 1. Find a real order to attach test reminders to ────────────────────────
$row = $conn->query("SELECT order_id, customer_name, customer_email FROM orders ORDER BY order_id DESC LIMIT 1")->fetch_assoc();
if (!$row) {
    die("No orders found — place at least one order first.\n");
}
$orderId = (int)$row['order_id'];
$email   = $row['customer_email'] ?: null;
echo "Using order #$orderId (email: " . ($email ?: 'none') . ")\n\n";

// ── 2. Insert test reminders due RIGHT NOW ───────────────────────────────────
$now = date('Y-m-d H:i:s');
$types = ['telegram_admin'];
if ($email) $types[] = 'email_customer';

$conn->query("DELETE FROM order_reminders WHERE order_id = $orderId AND status = 'pending'");

$stmt = $conn->prepare("INSERT INTO order_reminders (order_id, type, send_at) VALUES (?, ?, ?)");
foreach ($types as $type) {
    $stmt->bind_param('iss', $orderId, $type, $now);
    $stmt->execute();
    echo "  Inserted: $type → send_at = $now\n";
}
$stmt->close();
echo "\n";

// ── 3. Run the cron script ───────────────────────────────────────────────────
echo "── Running cron/send_reminders.php ──────────────────────\n";

// Patch the DB connection in the cron script to use the socket
putenv("DB_SOCKET=$socket");
// We need to override the connection — include a patched version
$_SERVER['argv'] = [$_SERVER['argv'][0]];

// Run as separate process so it uses its own DB connection
$php    = PHP_BINARY;
$script = ROOT . '/cron/send_reminders.php';
$output = shell_exec("$php $script 2>&1");
echo $output . "\n";

// ── 4. Check result in DB ────────────────────────────────────────────────────
echo "── Result in DB ──────────────────────────────────────────\n";
$res = $conn->query("SELECT id, type, status, sent_at, fail_reason FROM order_reminders WHERE order_id = $orderId ORDER BY id DESC LIMIT 5");
while ($r = $res->fetch_assoc()) {
    $status = $r['status'];
    $icon   = $status === 'sent' ? '✓' : ($status === 'failed' ? '✗' : '⏳');
    echo "  $icon [{$r['id']}] {$r['type']} → $status";
    if ($r['sent_at'])    echo " (sent: {$r['sent_at']})";
    if ($r['fail_reason']) echo " — ERROR: {$r['fail_reason']}";
    echo "\n";
}

$conn->close();
echo "\nDone.\n";
