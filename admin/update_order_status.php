<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('orders_edit');

header('Content-Type: application/json');

/* Auto-migrate ENUM to include processing + ready */
try {
    $conn->query("ALTER TABLE orders MODIFY COLUMN status ENUM('new','processing','ready','done','cancelled') NOT NULL DEFAULT 'new'");
} catch (Exception $e) {}

$data      = json_decode(file_get_contents('php://input'), true);
$orderId   = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$newStatus = trim($data['status'] ?? '');

$allStatuses = ['new', 'processing', 'ready', 'done', 'cancelled'];
if (!$orderId || !in_array($newStatus, $allStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Невірні дані']); exit;
}

$transitions = [
    'new'        => ['processing', 'done', 'cancelled'],
    'processing' => ['ready', 'done', 'cancelled'],
    'ready'      => ['done'],
    'done'       => [],
    'cancelled'  => [],
];

$stmt = $conn->prepare("SELECT status FROM orders WHERE order_id=?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Замовлення не знайдено']); exit;
}

$currentStatus = $row['status'];
$allowed = $transitions[$currentStatus] ?? [];

if (!in_array($newStatus, $allowed)) {
    $labels = ['new' => 'Нове', 'processing' => 'В обробці', 'ready' => 'Готово', 'done' => 'Виконано', 'cancelled' => 'Скасовано'];
    echo json_encode([
        'success' => false,
        'error'   => 'Перехід із «' . ($labels[$currentStatus] ?? $currentStatus) . '» в «' . ($labels[$newStatus] ?? $newStatus) . '» заборонений',
    ]); exit;
}

$stmt = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
$stmt->bind_param("si", $newStatus, $orderId);
$ok = $stmt->execute();
$stmt->close();

$labels   = ['new' => 'Нове', 'processing' => 'В обробці', 'ready' => 'Готово', 'done' => 'Виконано', 'cancelled' => 'Скасовано'];
$newCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='new'");
if ($r) $newCount = (int)$r->fetch_assoc()['c'];

echo json_encode([
    'success'      => $ok,
    'label'        => $labels[$newStatus] ?? $newStatus,
    'new_count'    => $newCount,
    'next_allowed' => $transitions[$newStatus] ?? [],
    'new_status'   => $newStatus,
]);
