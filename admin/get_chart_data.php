<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json');

$date = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'invalid date']);
    exit;
}

$hours = array_fill(0, 24, 0);
$stmt = $conn->prepare("SELECT HOUR(created_at) AS h, COUNT(*) AS c FROM orders WHERE DATE(created_at) = ? GROUP BY h");
$stmt->bind_param('s', $date);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $hours[(int)$row['h']] = (int)$row['c'];
$stmt->close();

echo json_encode(['success' => true, 'data' => array_values($hours)]);
