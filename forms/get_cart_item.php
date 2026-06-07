<?php
ini_set('display_errors', '0');
error_reporting(0);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$allowedTables = [
    'coffee_items', 'fast_food_items', 'pizza_items', 'mini_pizza_items',
    'cold_drink_items', 'dessert_items',
    'sushi_items', 'sushi_sets', 'salad_items', 'cake_items', 'ice_cream_items',
];

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    echo json_encode(['ok' => false, 'error' => 'empty_cart']);
    exit;
}

$cartEntry = null;
$cartIndex = -1;

$directIndex = isset($_GET['index']) ? (int)$_GET['index'] : -1;
if ($directIndex >= 0 && isset($_SESSION['cart'][$directIndex])) {
    $cartEntry = $_SESSION['cart'][$directIndex];
    $cartIndex = $directIndex;
} else {
    /* Fallback: lookup by category + id + variant */
    $category = trim($_GET['category'] ?? '');
    $id       = (int)($_GET['id']       ?? 0);
    $variant  = trim($_GET['variant']   ?? '');

    if (!in_array($category, $allowedTables, true) || $id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid_params']);
        exit;
    }

    foreach ($_SESSION['cart'] as $si => $it) {
        if (($it['category'] ?? '') !== $category || (int)($it['id'] ?? 0) !== $id) continue;
        if ($variant !== '') {
            if (($it['selected_variant'] ?? '') === $variant) {
                $cartEntry = $it; $cartIndex = $si; break;
            }
        } else {
            if ($category === 'pizza_items') {
                $reqSize  = trim($_GET['selected_size']  ?? '');
                $reqCrust = (int)($_GET['cheese_crust']  ?? 0);
                if (($it['selected_size']  ?? '') === $reqSize &&
                    (int)($it['cheese_crust'] ?? 0) === $reqCrust) {
                    $cartEntry = $it; $cartIndex = $si; break;
                }
            } else {
                $cartEntry = $it; $cartIndex = $si; break;
            }
        }
    }
}

if ($cartEntry === null) {
    echo json_encode(['ok' => false, 'error' => 'item_not_found']);
    exit;
}

/* Resolve category and id from cart entry for DB query */
$category = $cartEntry['category'] ?? '';
$id       = (int)($cartEntry['id'] ?? 0);

/* Fetch product data from DB */
if ($category === 'pizza_items') {
    $s = $conn->prepare("SELECT id, name, description, image, price, price_large, has_size_choice FROM pizza_items WHERE id = ?");
} elseif ($category === 'fast_food_items') {
    $s = $conn->prepare("SELECT id, name, description, image, price, variant_options FROM fast_food_items WHERE id = ?");
} elseif ($category === 'ice_cream_items') {
    $s = $conn->prepare("SELECT id, name, description, image, price, variant_options FROM ice_cream_items WHERE id = ?");
} elseif ($category === 'cake_items') {
    $s = $conn->prepare("SELECT id, name, description, image, price_per_kg, min_weight FROM cake_items WHERE id = ?");
} else {
    $s = $conn->prepare("SELECT id, name, description, image, price FROM `$category` WHERE id = ?");
}

$s->bind_param('i', $id);
$s->execute();
$row = $s->get_result()->fetch_assoc();
$s->close();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'db_not_found']);
    exit;
}

$result = [
    'ok'             => true,
    'cart_index'     => $cartIndex,
    'category'       => $category,
    'id'             => (int)$row['id'],
    'name'           => $row['name']        ?? '',
    'desc'           => $row['description'] ?? '',
    'image'          => item_img($row['image'] ?? ''),
    'price'          => isset($row['price']) ? (float)$row['price'] : 0.0,
    'quantity'         => (int)($cartEntry['quantity']  ?? 1),
    'selected_size'    => $cartEntry['selected_size']    ?? null,
    'cheese_crust'     => isset($cartEntry['cheese_crust'])     ? (int)$cartEntry['cheese_crust']     : 0,
    'selected_variant' => $cartEntry['selected_variant'] ?? null,
    'weight'           => isset($cartEntry['weight'])           ? (float)$cartEntry['weight']         : null,
    'price_override'   => isset($cartEntry['price_override'])   ? (float)$cartEntry['price_override'] : null,
];

if ($category === 'pizza_items') {
    $result['price_large']     = (float)($row['price_large']     ?? 0);
    $result['has_size_choice'] = (int)  ($row['has_size_choice'] ?? 1);
}
if ($category === 'fast_food_items' || $category === 'ice_cream_items') {
    $result['variant_options'] = $row['variant_options'] ?? null;
}
if ($category === 'cake_items') {
    $result['price_per_kg'] = (float)($row['price_per_kg'] ?? 0);
    $result['min_weight']   = (float)($row['min_weight']   ?? 1);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
