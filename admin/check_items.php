<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_super();
$conn->set_charset('utf8mb4');
header('Content-Type: text/plain; charset=utf-8');

$tables = ['fast_food_items','pizza_items','mini_pizza_items','cold_drink_items','salad_items','dessert_items','cake_items','ice_cream_items','sauces'];
foreach ($tables as $t) {
    echo "=== $t ===\n";
    $r = $conn->query("SELECT id, name, image FROM `$t` ORDER BY id");
    while ($row = $r->fetch_assoc()) echo $row['id'] . "\t" . $row['name'] . "\t" . $row['image'] . "\n";
    echo "\n";
}
