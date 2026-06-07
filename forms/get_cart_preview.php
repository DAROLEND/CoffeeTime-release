<?php
ini_set('display_errors', '0');
error_reporting(0);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/db.php';

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    echo json_encode(['ok' => true, 'items' => [], 'total' => 0, 'count' => 0]);
    exit;
}

$allowed = ['coffee_items','fast_food_items','pizza_items','mini_pizza_items',
            'cold_drink_items','dessert_items','sushi_items','sushi_sets',
            'salad_items','cake_items','ice_cream_items','sauces'];

$items = [];
$total = 0;
$count = 0;

foreach ($_SESSION['cart'] as $si => $it) {
    $cat = $it['category'] ?? '';
    $id  = (int)($it['id']  ?? 0);
    $qty = (int)($it['quantity'] ?? 1);
    if (!in_array($cat, $allowed, true) || $id <= 0) continue;

    $s = $conn->prepare("SELECT name, price, image FROM `$cat` WHERE id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) continue;

    $price = isset($it['price_override']) ? (float)$it['price_override'] : (float)$row['price'];
    $rawImg = $row['image'] ?? '';
    $isDefault = ($rawImg === 'static/images/menu_items/default.jpg' || empty($rawImg));
    $items[] = [
        'session_index' => $si,
        'category'      => $cat,
        'id'            => $id,
        'name'          => $row['name'],
        'image'         => $isDefault ? '' : $rawImg,
        'price'         => round($price, 2),
        'qty'           => $qty,
    ];
}

foreach ($_SESSION['cart'] as $it) {
    $cat = $it['category'] ?? '';
    $id  = (int)($it['id'] ?? 0);
    $qty = (int)($it['quantity'] ?? 1);
    if (!in_array($cat, $allowed, true) || $id <= 0) continue;
    $s = $conn->prepare("SELECT price FROM `$cat` WHERE id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) continue;
    $price = isset($it['price_override']) ? (float)$it['price_override'] : (float)$row['price'];
    $total += $price * $qty;
    $count += $qty;
}

echo json_encode([
    'ok'    => true,
    'items' => $items,
    'total' => round($total, 2),
    'count' => $count,
], JSON_UNESCAPED_UNICODE);
