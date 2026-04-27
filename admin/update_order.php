<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

if (!isset($_GET['id'], $_GET['status']) || !is_numeric($_GET['id'])) {
    echo "Невірні параметри.";
    exit;
}

$orderId = (int)$_GET['id'];
$status = trim(strtolower($_GET['status'])); // очищення пробілів і нормалізація регістру

// Список дозволених значень
$allowedStatuses = ['pending', 'approved', 'declined'];

if (!in_array($status, $allowedStatuses, true)) {
    echo "Недопустимий статус: $status";
    exit;
}

$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
if (!$stmt) {
    echo "Помилка SQL: " . $conn->error;
    exit;
}

$stmt->bind_param("si", $status, $orderId);

if (!$stmt->execute()) {
    echo "Помилка виконання: " . $stmt->error;
    $stmt->close();
    exit;
}
$stmt->close();

header("Location: orders.php");
exit;
