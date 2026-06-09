<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_super();
$conn->set_charset('utf8mb4');
header('Content-Type: text/plain; charset=utf-8');

$items = [
    ['Міні Салямі', 'Моцарела, салямі',  'static/images/menu_items/mini_pizza/mini-salami.webp', 75.00, 'tomato', 0, 'салямі'],
    ['Міні Сирна',  'Моцарела, 4 сири',  'static/images/menu_items/mini_pizza/mini-cheese.webp', 75.00, 'cream',  0, '4 сири'],
];

foreach ($items as [$name, $desc, $img, $price, $sauce, $spicy, $tags]) {
    $s = $conn->prepare("INSERT INTO mini_pizza_items (name, description, image, price, sauce_type, is_spicy, ingredients_tags) VALUES (?,?,?,?,?,?,?)");
    $s->bind_param('sssdsss', $name, $desc, $img, $price, $sauce, $spicy, $tags);
    $s->execute();
    echo ($s->affected_rows > 0 ? "OK: $name\n" : "FAIL: $name\n");
    $s->close();
}
echo "Done.\n";
