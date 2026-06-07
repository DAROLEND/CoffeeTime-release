<?php
require_once __DIR__ . '/db/db.php';

$tables = ['fast_food_items', 'pizza_items', 'dessert_items', 'cold_drink_items', 'coffee_items'];
$total = 0;
foreach ($tables as $table) {
    $r = $conn->query("UPDATE `$table` SET image = REPLACE(image, '.png', '.webp') WHERE image LIKE '%.png'");
    if ($r) $total += $conn->affected_rows;
}
echo "Done. Updated $total rows.";
