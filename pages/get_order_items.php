<?php
require_once '../includes/session.php';
require_once '../db/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    echo json_encode(['items' => []]); exit;
}

$userId  = (int)$_SESSION['user']['client_id'];
$orderId = (int)($_GET['order_id'] ?? 0);

if (!$orderId) { echo json_encode(['items' => []]); exit; }

/* Verify the order belongs to this user */
$stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id=? AND user_id=?");
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['items' => []]); exit;
}
$stmt->close();

/* Fetch items */
$stmt = $conn->prepare("SELECT product_id, category, quantity, price FROM order_items WHERE order_id=?");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$rawItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$allowed = ['coffee_items', 'fast_food_items', 'pizza_items', 'cold_drink_items', 'dessert_items', 'giftcards', 'sushi_items', 'sushi_sets', 'salad_items', 'cake_items'];
$items   = [];

foreach ($rawItems as $it) {
    $cat  = $it['category'];
    $pid  = (int)$it['product_id'];
    $name = '—';

    if (in_array($cat, $allowed)) {
        $nameCol = ($cat === 'giftcards') ? 'title' : 'name';
        $s = $conn->prepare("SELECT `$nameCol` AS nm FROM `$cat` WHERE id=?");
        $s->bind_param('i', $pid);
        $s->execute();
        $row  = $s->get_result()->fetch_assoc();
        $s->close();
        $name = $row['nm'] ?? '—';
    }

    $items[] = [
        'name'     => $name,
        'category' => $cat,
        'quantity' => (int)$it['quantity'],
        'price'    => (float)$it['price'],
    ];
}

echo json_encode(['items' => $items]);
