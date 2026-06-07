<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$period    = $_GET['period']    ?? 'all';
$date_from = $_GET['date_from'] ?? null;
$date_to   = $_GET['date_to']   ?? null;

$where  = '';
$params = [];
$types  = '';

switch ($period) {
    case 'week':
        $where = 'AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        break;
    case 'month':
        $where = 'AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        break;
    case 'year':
        $where = 'AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
        break;
    case 'custom':
        if ($date_from && $date_to) {
            $where  = 'AND DATE(o.created_at) BETWEEN ? AND ?';
            $params = [$date_from, $date_to];
            $types  = 'ss';
        }
        break;
}

$sql = "
    SELECT oi.product_id, oi.category,
           SUM(oi.quantity)                         AS total_qty,
           COUNT(DISTINCT oi.order_id)              AS orders_count,
           COALESCE(SUM(oi.quantity * oi.price), 0) AS total_revenue,
           ROUND(AVG(oi.price), 0)                  AS avg_unit_price
    FROM order_items oi
    LEFT JOIN orders o ON oi.order_id = o.order_id
    WHERE 1=1 $where
    GROUP BY oi.product_id, oi.category
    ORDER BY total_qty DESC
    LIMIT 5
";

$stmt = $conn->prepare($sql);
if ($types && $params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$allowed_cats = ['coffee_items','fast_food_items','pizza_items','mini_pizza_items','cold_drink_items',
                 'dessert_items','sushi_items','sushi_sets','salad_items','cake_items','ice_cream_items'];

$products = [];
foreach ($rows as $row) {
    $cat = $row['category'];
    $pid = (int)$row['product_id'];
    if (!in_array($cat, $allowed_cats)) continue;

    $s = $conn->prepare("SELECT name AS nm, image, price FROM `$cat` WHERE id=?");
    $s->bind_param('i', $pid);
    $s->execute();
    $prod = $s->get_result()->fetch_assoc();
    $s->close();

    if ($prod) {
        $rawImg    = $prod['image'] ?? '';
        $isDefault = (empty($rawImg) || $rawImg === 'static/images/menu_items/default.jpg');
        $name      = $prod['nm'];
        $image     = $isDefault ? '' : $rawImg;
        $unitPrice = number_format((float)$prod['price'], 0, '.', ' ');
    } else {
        $name      = 'Видалений товар';
        $image     = '';
        $unitPrice = number_format((float)$row['avg_unit_price'], 0, '.', ' ');
    }

    $products[] = [
        'name'             => $name,
        'image'            => $image,
        'unit_price'       => $unitPrice,
        'total_qty'        => (int)$row['total_qty'],
        'orders_count'     => (int)$row['orders_count'],
        'total_revenue_fmt'=> number_format((float)$row['total_revenue'], 0, '.', ' '),
        'deleted'          => !$prod,
    ];
}

echo json_encode(['success' => true, 'products' => $products]);
