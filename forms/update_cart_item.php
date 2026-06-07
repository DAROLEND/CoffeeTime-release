<?php
ini_set('display_errors', '0');
error_reporting(0);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/db.php';

$index = (int)($_POST['index'] ?? -1);

if ($index < 0 || !isset($_SESSION['cart'][$index])) {
    echo json_encode(['ok' => false, 'error' => 'invalid_index']);
    exit;
}

$item   = &$_SESSION['cart'][$index];
$table  = $item['category'] ?? '';
$itemId = (int)($item['id'] ?? 0);

$allowedTables = [
    'coffee_items', 'fast_food_items', 'pizza_items', 'mini_pizza_items',
    'cold_drink_items', 'dessert_items',
    'sushi_items', 'sushi_sets', 'salad_items', 'cake_items', 'ice_cream_items',
    'sauces',
];

if (!in_array($table, $allowedTables, true)) {
    echo json_encode(['ok' => false]);
    exit;
}

$existingQty = (int)($item['quantity'] ?? 1);
$qty = (isset($_POST['quantity']) && $_POST['quantity'] !== '')
    ? max(1, min(99, (int)$_POST['quantity']))
    : $existingQty;

if ($table === 'pizza_items') {
    $size  = in_array($_POST['selected_size'] ?? '', ['small', 'large']) ? $_POST['selected_size'] : 'small';
    $crust = (($_POST['cheese_crust'] ?? '0') === '1') ? 1 : 0;

    $s = $conn->prepare("SELECT price, price_large FROM pizza_items WHERE id = ?");
    $s->bind_param('i', $itemId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) { echo json_encode(['ok' => false]); exit; }

    $newPrice = ($size === 'large' ? (float)$row['price_large'] : (float)$row['price']);
    if ($crust) $newPrice += ($size === 'large' ? 100 : 65);

    $item['selected_size']  = $size;
    $item['cheese_crust']   = $crust;
    $item['price_override'] = round($newPrice, 2);
    $item['quantity']       = $qty;

} elseif ($table === 'fast_food_items' || $table === 'ice_cream_items') {
    $svRaw = trim($_POST['selected_variant'] ?? '');
    $svArr = ($svRaw !== '') ? json_decode($svRaw, true) : null;

    $s = $conn->prepare("SELECT price FROM `$table` WHERE id = ?");
    $s->bind_param('i', $itemId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) { echo json_encode(['ok' => false]); exit; }

    $priceDiff = is_array($svArr) ? (float)($svArr['price_diff'] ?? 0) : 0;
    $newPrice  = (float)$row['price'] + $priceDiff;

    if ($svRaw !== '') $item['selected_variant'] = $svRaw;
    else               unset($item['selected_variant']);

    $item['price_override'] = round($newPrice, 2);
    $item['quantity']       = $qty;

} elseif ($table === 'cake_items') {
    $weight = max(0.5, (float)($_POST['weight'] ?? 1.0));

    $s = $conn->prepare("SELECT price_per_kg, min_weight FROM cake_items WHERE id = ?");
    $s->bind_param('i', $itemId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) { echo json_encode(['ok' => false]); exit; }

    $weight = max((float)$row['min_weight'], $weight);
    $newPrice = round($weight * (float)$row['price_per_kg'], 2);

    $item['weight']         = $weight;
    $item['price_override'] = $newPrice;
    $item['quantity']       = 1;
    $qty = 1;

} else {
    $item['quantity'] = $qty;

    if (isset($item['price_override']) && $item['price_override'] > 0) {
        $newPrice = (float)$item['price_override'];
    } else {
        $s = $conn->prepare("SELECT price FROM `$table` WHERE id = ?");
        $s->bind_param('i', $itemId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        $newPrice = $row ? (float)$row['price'] : 0.0;
    }
}

echo json_encode([
    'ok'               => true,
    'new_price'        => round($newPrice, 2),
    'new_subtotal'     => round($newPrice * $qty, 2),
    'new_qty'          => $qty,
    'selected_size'    => $item['selected_size']    ?? null,
    'cheese_crust'     => isset($item['cheese_crust']) ? (int)$item['cheese_crust'] : null,
    'selected_variant' => $item['selected_variant'] ?? null,
], JSON_UNESCAPED_UNICODE);
exit;
