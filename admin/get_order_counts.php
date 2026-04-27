<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$counts = [];
$counts['all']       = (int)$conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$counts['new']       = (int)$conn->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetch_row()[0];
$counts['done']      = (int)$conn->query("SELECT COUNT(*) FROM orders WHERE status='done'")->fetch_row()[0];
$counts['cancelled'] = (int)$conn->query("SELECT COUNT(*) FROM orders WHERE status='cancelled'")->fetch_row()[0];
$counts['paid']      = (int)$conn->query("SELECT COUNT(*) FROM orders WHERE payment_status='paid'")->fetch_row()[0];
$counts['cash']      = (int)$conn->query("SELECT COUNT(*) FROM orders WHERE payment_method LIKE '%cash%'")->fetch_row()[0];
$counts['unpaid']    = (int)$conn->query("SELECT COUNT(*) FROM orders WHERE payment_status NOT IN ('paid','cash') AND payment_method NOT LIKE '%cash%'")->fetch_row()[0];

echo json_encode($counts);
