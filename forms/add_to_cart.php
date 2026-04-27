<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/db.php';

$allowedTables = [
    'coffee_items', 'fast_food_items', 'pizza_items',
    'cold_drink_items', 'dessert_items', 'giftcards',
    'sushi_items', 'sushi_sets', 'salad_items', 'cake_items',
];

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$table = $_POST['category'] ?? '';
$id    = (int)($_POST['id'] ?? 0);
$qty   = max(1, min(99, (int)($_POST['quantity'] ?? 1)));

if (!in_array($table, $allowedTables, true) || $id <= 0) {
    echo json_encode(['ok' => false]);
    exit;
}

/* ── Cake items: weight-based pricing ── */
if ($table === 'cake_items') {
    $weight = max(1.0, (float)($_POST['weight'] ?? 1.0));
    $s = $conn->prepare("SELECT price_per_kg, min_weight FROM cake_items WHERE id=?");
    $s->bind_param('i', $id);
    $s->execute();
    $cake = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$cake) { echo json_encode(['ok' => false]); exit; }

    $weight        = max((float)$cake['min_weight'], $weight);
    $priceOverride = round($weight * (float)$cake['price_per_kg'], 2);

    // One cake order = one entry (replace if exists)
    foreach ($_SESSION['cart'] as $i => $it) {
        if (isset($it['category'], $it['id']) && $it['category'] === 'cake_items' && (int)$it['id'] === $id) {
            $_SESSION['cart'][$i]['weight']         = $weight;
            $_SESSION['cart'][$i]['price_override'] = $priceOverride;
            $_SESSION['cart'][$i]['quantity']        = 1;
            $total = array_sum(array_column($_SESSION['cart'], 'quantity'));
            echo json_encode(['ok' => true, 'count' => $total]);
            exit;
        }
    }

    $_SESSION['cart'][] = [
        'category'       => $table,
        'id'             => $id,
        'quantity'       => 1,
        'weight'         => $weight,
        'price_override' => $priceOverride,
    ];

    $total = array_sum(array_column($_SESSION['cart'], 'quantity'));
    echo json_encode(['ok' => true, 'count' => $total]);
    exit;
}

/* ── Regular items ── */
$found = null;
foreach ($_SESSION['cart'] as $i => $it) {
    if (isset($it['category'], $it['id']) && $it['category'] === $table && (int)$it['id'] === $id) {
        $found = $i;
        break;
    }
}

if ($found !== null) {
    $_SESSION['cart'][$found]['quantity'] += $qty;
} else {
    $_SESSION['cart'][] = ['category' => $table, 'id' => $id, 'quantity' => $qty];
}

$total = array_sum(array_column($_SESSION['cart'], 'quantity'));
echo json_encode(['ok' => true, 'count' => $total]);
