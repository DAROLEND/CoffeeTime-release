<?php
session_start();
require '../db/db.php';
header('Content-Type: application/json; charset=utf-8');

$allowed = ['coffee_items','fast_food_items','pizza_items', 'mini_pizza_items','cold_drink_items','dessert_items'];
$cat     = $_POST['category'] ?? '';
$itemId  = (int)($_POST['item_id'] ?? 0);
$action  = $_POST['action'] ?? '';

if (!in_array($cat, $allowed, true) || $itemId <= 0 || !in_array($action, ['increase','decrease','set_qty'], true)) {
    echo json_encode(['ok' => false]); exit;
}

// Find item in cart
$foundIdx = null;
foreach ($_SESSION['cart'] as $i => $ci) {
    if ($ci['category'] === $cat && (int)$ci['id'] === $itemId) {
        $foundIdx = $i; break;
    }
}
if ($foundIdx === null) { echo json_encode(['ok' => false]); exit; }

// Update quantity
if ($action === 'increase') {
    $_SESSION['cart'][$foundIdx]['quantity']++;
} elseif ($action === 'set_qty') {
    $setQty = max(1, min(99, (int)($_POST['qty'] ?? 1)));
    $_SESSION['cart'][$foundIdx]['quantity'] = $setQty;
} else {
    $_SESSION['cart'][$foundIdx]['quantity']--;
}

$newQty  = $_SESSION['cart'][$foundIdx]['quantity'];
$removed = false;

if ($newQty <= 0) {
    unset($_SESSION['cart'][$foundIdx]);
    $removed = true;
}

// Get item price for subtotal
$itemTotal = 0;
if (!$removed) {
    $s = $conn->prepare("SELECT price FROM `$cat` WHERE id = ?");
    $s->bind_param('i', $itemId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    $itemTotal = round(((float)($r['price'] ?? 0)) * $newQty, 2);
}

// Recalculate cart total
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
    'removed'    => $removed,
    'new_qty'    => $newQty,
    'item_total' => $itemTotal,
    'cart_total' => round($cartTotal, 2),
    'cart_count' => $cartCount,
]);
