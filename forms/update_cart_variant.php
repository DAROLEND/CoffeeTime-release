<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$table   = $_POST['category']         ?? '';
$id      = (int)($_POST['id']         ?? 0);
$variant = trim($_POST['selected_variant'] ?? '');

$allowedTables = [
    'coffee_items', 'fast_food_items', 'pizza_items', 'mini_pizza_items',
    'cold_drink_items', 'dessert_items',
    'sushi_items', 'sushi_sets', 'salad_items', 'cake_items',
];

if (!in_array($table, $allowedTables, true) || $id <= 0 || $variant === '') {
    echo json_encode(['ok' => false]);
    exit;
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    echo json_encode(['ok' => false]);
    exit;
}

// Find the most recently added item matching category+id (last one in cart)
$found = null;
foreach ($_SESSION['cart'] as $i => $it) {
    if (isset($it['category'], $it['id']) && $it['category'] === $table && (int)$it['id'] === $id) {
        $found = $i;
    }
}

if ($found !== null) {
    $_SESSION['cart'][$found]['selected_variant'] = $variant;
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false]);
}
