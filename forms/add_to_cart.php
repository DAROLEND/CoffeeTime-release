<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/db.php';

$allowedTables = [
    'coffee_items', 'fast_food_items', 'pizza_items', 'mini_pizza_items',
    'cold_drink_items', 'dessert_items',
    'sushi_items', 'sushi_sets', 'salad_items', 'cake_items', 'ice_cream_items',
    'sauces',
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

if ($table === 'pizza_items' || $table === 'mini_pizza_items') {
    $selectedSize  = in_array($_POST['selected_size'] ?? '', ['small','large']) ? $_POST['selected_size'] : 'small';
    $cheeseCrust   = !empty($_POST['cheese_crust']) && $_POST['cheese_crust'] === '1' ? 1 : 0;
    $inBox         = !empty($_POST['takeaway'])        && $_POST['takeaway']        === '1' ? 1 : 0;
    $priceOverride = !empty($_POST['price_override']) ? (float)$_POST['price_override'] : null;

    if ($priceOverride !== null) {
        // Verify price from DB
        if ($table === 'mini_pizza_items') {
            $sp = $conn->prepare("SELECT price FROM mini_pizza_items WHERE id=?");
        } else {
            $col = ($selectedSize === 'large') ? 'price_large' : 'price';
            $sp  = $conn->prepare("SELECT price, price_large FROM pizza_items WHERE id=?");
        }
        $sp->bind_param('i', $id);
        $sp->execute();
        $prow = $sp->get_result()->fetch_assoc();
        $sp->close();
        if (!$prow) { echo json_encode(['ok' => false]); exit; }

        if ($table === 'mini_pizza_items') {
            $col = 'price';
        }
        $basePrice = (float)$prow[$col];
        if ($cheeseCrust && $table === 'pizza_items') {
            $basePrice += ($selectedSize === 'large') ? 100 : 65;
        }
        $priceOverride = $basePrice;
    }

    // Pizza can be added multiple times with different sizes
    // Use size+crust as unique key (same pizza, different size = different cart item)
    $found = null;
    foreach ($_SESSION['cart'] as $i => $it) {
        if (isset($it['category'], $it['id']) &&
            ($it['category'] === 'pizza_items' || $it['category'] === 'mini_pizza_items') &&
            (int)$it['id'] === $id &&
            ($it['selected_size'] ?? 'small') === $selectedSize &&
            ($it['cheese_crust'] ?? 0) === $cheeseCrust) {
            $found = $i;
            break;
        }
    }

    $entry = [
        'category'      => $table,
        'id'            => $id,
        'quantity'      => $qty,
        'selected_size' => $selectedSize,
        'cheese_crust'  => $cheeseCrust,
    ];
    if ($priceOverride !== null) $entry['price_override'] = $priceOverride;

    if ($found !== null) {
        $_SESSION['cart'][$found]['quantity'] += $qty;
    } else {
        $_SESSION['cart'][] = $entry;
    }

    $total = array_sum(array_column($_SESSION['cart'], 'quantity'));
    echo json_encode(['ok' => true, 'count' => $total]);
    exit;
}

if ($table === 'sauces') {
    $s = $conn->prepare("SELECT id, name, price FROM sauces WHERE id=? AND active=1");
    $s->bind_param('i', $id);
    $s->execute();
    $sauce = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$sauce) { echo json_encode(['ok' => false]); exit; }

    $found = null;
    foreach ($_SESSION['cart'] as $i => $it) {
        if (isset($it['category'], $it['id']) && $it['category'] === 'sauces' && (int)$it['id'] === $id) {
            $found = $i; break;
        }
    }
    if ($found !== null) {
        $_SESSION['cart'][$found]['quantity'] += $qty;
    } else {
        $_SESSION['cart'][] = ['category' => 'sauces', 'id' => $id, 'quantity' => $qty];
    }
    $total = array_sum(array_column($_SESSION['cart'], 'quantity'));
    echo json_encode(['ok' => true, 'count' => $total]);
    exit;
}

if ($table === 'ice_cream_items') {
    $selectedVariant = trim($_POST['selected_variant'] ?? '');
    $varArr    = $selectedVariant ? json_decode($selectedVariant, true) : null;
    $priceDiff = (is_array($varArr) && isset($varArr['price_diff'])) ? (float)$varArr['price_diff'] : 0;

    $sp = $conn->prepare("SELECT price FROM ice_cream_items WHERE id=?");
    $sp->bind_param('i', $id);
    $sp->execute();
    $prow = $sp->get_result()->fetch_assoc();
    $sp->close();
    if (!$prow) { echo json_encode(['ok' => false]); exit; }

    $priceOverride = (float)$prow['price'] + $priceDiff;

    $found = null;
    foreach ($_SESSION['cart'] as $ci => $it) {
        if (isset($it['category'], $it['id']) && $it['category'] === 'ice_cream_items'
            && (int)$it['id'] === $id
            && ($it['selected_variant'] ?? '') === $selectedVariant) {
            $found = $ci;
            break;
        }
    }

    if ($found !== null) {
        $_SESSION['cart'][$found]['quantity'] += $qty;
    } else {
        $entry = ['category' => $table, 'id' => $id, 'quantity' => $qty, 'price_override' => $priceOverride];
        if ($selectedVariant !== '') $entry['selected_variant'] = $selectedVariant;
        $_SESSION['cart'][] = $entry;
    }

    $total = array_sum(array_column($_SESSION['cart'], 'quantity'));
    echo json_encode(['ok' => true, 'count' => $total]);
    exit;
}

$selectedVariant = trim($_POST['selected_variant'] ?? '');
$priceOverride   = !empty($_POST['price_override']) ? (float)$_POST['price_override'] : null;

// Same variant = same cart slot; different variants = separate slots
$found = null;
foreach ($_SESSION['cart'] as $i => $it) {
    if (isset($it['category'], $it['id'])
        && $it['category'] === $table
        && (int)$it['id'] === $id
        && ($it['selected_variant'] ?? '') === $selectedVariant) {
        $found = $i;
        break;
    }
}

if ($found !== null) {
    $_SESSION['cart'][$found]['quantity'] += $qty;
} else {
    $entry = ['category' => $table, 'id' => $id, 'quantity' => $qty];
    if ($selectedVariant !== '') $entry['selected_variant'] = $selectedVariant;
    if ($priceOverride !== null) $entry['price_override'] = $priceOverride;
    $_SESSION['cart'][] = $entry;
}

$total = array_sum(array_column($_SESSION['cart'], 'quantity'));
echo json_encode(['ok' => true, 'count' => $total]);
