<?php
session_start();
require '../db/db.php';
header('Content-Type: application/json; charset=utf-8');

$allowed = ['coffee_items','fast_food_items','pizza_items','cold_drink_items','dessert_items'];
$cat     = $_POST['category'] ?? '';
$itemId  = (int)($_POST['item_id'] ?? 0);

if (!in_array($cat, $allowed, true) || $itemId <= 0) {
    echo json_encode(['ok' => false]); exit;
}

// Find and remove item
foreach ($_SESSION['cart'] as $i => $ci) {
    if ($ci['category'] === $cat && (int)$ci['id'] === $itemId) {
        unset($_SESSION['cart'][$i]);
        break;
    }
}
$_SESSION['cart'] = array_values($_SESSION['cart']);

// Recalculate totals
$cartTotal = 0;
$cartCount = 0;
foreach ($_SESSION['cart'] as $ci) {
    if (!in_array($ci['category'], $allowed, true)) continue;
    $s = $conn->prepare("SELECT price FROM `{$ci['category']}` WHERE id = ?");
    $s->bind_param('i', $ci['id']);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    $cartTotal += ((float)($r['price'] ?? 0)) * $ci['quantity'];
    $cartCount += $ci['quantity'];
}

echo json_encode([
    'ok'         => true,
    'cart_total' => round($cartTotal, 2),
    'cart_count' => $cartCount,
]);
