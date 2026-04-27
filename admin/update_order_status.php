<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('orders_edit');

header('Content-Type: application/json');

$data      = json_decode(file_get_contents('php://input'), true);
$orderId   = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$newStatus = trim($data['status'] ?? '');

$allowed = ['new', 'done', 'cancelled'];
if (!$orderId || !in_array($newStatus, $allowed)) {
    echo json_encode(['success'=>false,'error'=>'Invalid input']); exit;
}

$stmt = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
$stmt->bind_param("si", $newStatus, $orderId);
$ok = $stmt->execute();
$stmt->close();

$labels   = ['new' => 'Нове', 'done' => 'Готово', 'cancelled' => 'Скасовано'];
$newCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='new'");
if ($r) $newCount = (int)$r->fetch_assoc()['c'];

echo json_encode([
    'success'   => $ok,
    'label'     => $labels[$newStatus] ?? $newStatus,
    'new_count' => $newCount,
]);
