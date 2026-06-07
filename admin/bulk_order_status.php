<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('orders_edit');

header('Content-Type: application/json');

$data      = json_decode(file_get_contents('php://input'), true);
$orderIds  = array_filter(array_map('intval', $data['order_ids'] ?? []));
$newStatus = trim($data['status'] ?? '');

$allStatuses = ['new', 'processing', 'ready', 'done', 'cancelled'];
if (empty($orderIds) || !in_array($newStatus, $allStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Невірні дані']); exit;
}

$transitions = [
    'new'        => ['processing', 'done', 'cancelled'],
    'processing' => ['ready', 'done', 'cancelled'],
    'ready'      => ['done'],
    'done'       => [],
    'cancelled'  => [],
];

$updated = 0;
$skipped = 0;

foreach ($orderIds as $orderId) {
    $stmt = $conn->prepare("SELECT status FROM orders WHERE order_id=?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !in_array($newStatus, $transitions[$row['status']] ?? [])) {
        $skipped++;
        continue;
    }

    $stmt = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
    $stmt->bind_param("si", $newStatus, $orderId);
    $stmt->execute();
    $stmt->close();
    $updated++;
}

$newCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='new'");
if ($r) $newCount = (int)$r->fetch_assoc()['c'];

$labels = ['new' => 'Нове', 'processing' => 'В обробці', 'ready' => 'Готово', 'done' => 'Виконано', 'cancelled' => 'Скасовано'];

echo json_encode([
    'success'   => $updated > 0,
    'updated'   => $updated,
    'skipped'   => $skipped,
    'new_count' => $newCount,
    'label'     => $labels[$newStatus] ?? $newStatus,
]);
