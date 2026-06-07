<?php
session_start();
require '../db/db.php';
header('Content-Type: application/json; charset=utf-8');

$allowed = ['coffee_items','fast_food_items','pizza_items','mini_pizza_items',
            'cold_drink_items','dessert_items','ice_cream_items',
            'sushi_items','sushi_sets','salad_items','cake_items','sauces'];

// Remove by session_index (preferred) or by category+id fallback
$si     = isset($_POST['session_index']) ? (int)$_POST['session_index'] : -1;
$cat    = $_POST['category'] ?? '';
$itemId = (int)($_POST['item_id'] ?? 0);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    echo json_encode(['ok' => false]); exit;
}

if ($si >= 0 && isset($_SESSION['cart'][$si])) {
    unset($_SESSION['cart'][$si]);
} elseif (in_array($cat, $allowed, true) && $itemId > 0) {
    foreach ($_SESSION['cart'] as $i => $ci) {
        if ($ci['category'] === $cat && (int)$ci['id'] === $itemId) {
            unset($_SESSION['cart'][$i]);
            break;
        }
    }
} else {
    echo json_encode(['ok' => false]); exit;
}

// Recalculate totals
$cartTotal = 0;
$cartCount = 0;
foreach ($_SESSION['cart'] as $ci) {
    $c = $ci['category'] ?? '';
    $i = (int)($ci['id'] ?? 0);
    $q = (int)($ci['quantity'] ?? 1);
    if (!in_array($c, $allowed, true) || $i <= 0) continue;
    $s = $conn->prepare("SELECT price FROM `$c` WHERE id = ?");
    $s->bind_param('i', $i);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$r) continue;
    $price = isset($ci['price_override']) ? (float)$ci['price_override'] : (float)$r['price'];
    $cartTotal += $price * $q;
    $cartCount += $q;
}

echo json_encode([
    'ok'         => true,
    'cart_total' => round($cartTotal, 2),
    'cart_count' => $cartCount,
], JSON_UNESCAPED_UNICODE);
