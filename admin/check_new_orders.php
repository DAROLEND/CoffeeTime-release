<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json');

$count = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='new'");
if ($r) $count = (int)$r->fetch_assoc()['c'];

echo json_encode(['count' => $count]);
