<?php
/**
 * AJAX endpoint — returns the payment status for a given order.
 * Only accessible for the order that belongs to the current session.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/db/db.php';

header('Content-Type: application/json; charset=utf-8');

$orderId = (int)($_GET['order_id'] ?? 0);

// Must match the pending order stored in session — prevents enumeration
if (!$orderId || (int)($_SESSION['pending_order_id'] ?? 0) !== $orderId) {
    echo json_encode(['status' => 'unknown']);
    exit;
}

$stmt = $conn->prepare("SELECT payment_status FROM orders WHERE order_id = ? LIMIT 1");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['status' => 'unknown']);
    exit;
}

echo json_encode(['status' => $order['payment_status'] ?? 'pending']);
