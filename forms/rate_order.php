<?php
require_once '../includes/session.php';
require_once '../db/db.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    echo json_encode(['ok' => false]); exit;
}

$userId  = (int)$_SESSION['user']['client_id'];
$orderId = (int)($_POST['order_id'] ?? 0);
$rating  = (int)($_POST['rating']   ?? 0);

if (!$orderId || $rating < 1 || $rating > 5) {
    echo json_encode(['ok' => false, 'msg' => 'invalid']); exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS order_ratings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT NOT NULL,
    user_id    INT NOT NULL,
    rating     TINYINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_user (order_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE order_ratings DROP COLUMN IF EXISTS comment");

$stmt = $conn->prepare(
    "SELECT order_id FROM orders WHERE order_id=? AND user_id=? AND status='done'"
);
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['ok' => false, 'msg' => 'not_found']); exit;
}
$stmt->close();

$stmt = $conn->prepare("
    INSERT INTO order_ratings (order_id, user_id, rating)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE rating=VALUES(rating), created_at=NOW()
");
$stmt->bind_param('iii', $orderId, $userId, $rating);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok]);
