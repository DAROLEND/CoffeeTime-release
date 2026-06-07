<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('orders_edit');

header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$orderId = isset($data['order_id']) ? (int)$data['order_id'] : 0;

if (!$orderId) {
    echo json_encode(['success' => false, 'error' => 'Невірний ID']); exit;
}

/* Delete items first (no FK cascade assumed) */
$stmt = $conn->prepare("DELETE FROM order_items WHERE order_id=?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM orders WHERE order_id=?");
$stmt->bind_param("i", $orderId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
