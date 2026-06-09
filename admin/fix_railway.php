<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_super();
$conn->set_charset('utf8mb4');
header('Content-Type: text/plain; charset=utf-8');

$log = [];

/* ── 1. Delete unwanted items ── */
$conn->query("DELETE FROM fast_food_items WHERE id=9");
$log[] = "DELETE Соус порційний (fast_food id=9): " . $conn->affected_rows . " rows";

$conn->query("DELETE FROM pizza_items WHERE id=66");
$log[] = "DELETE Хлібні палички (pizza id=66): " . $conn->affected_rows . " rows";

$conn->query("DELETE FROM mini_pizza_items");
$log[] = "DELETE all mini_pizza_items: " . $conn->affected_rows . " rows";

/* ── 2. Fix pizza images by name (IDs differ on Railway) ── */
$pizzaImages = [
    'Салямі'          => 'static/images/menu_items/pizza/salami.webp',
    "М'ясна"          => 'static/images/menu_items/pizza/miasna.webp',
    'З куркою'        => 'static/images/menu_items/pizza/with chicken.webp',
    "Мега м'ясна"     => 'static/images/menu_items/pizza/mega miasna.webp',
    'Діабла'          => 'static/images/menu_items/pizza/diabla.webp',
    'З картоплею Фрі' => 'static/images/menu_items/pizza/with potato free.webp',
    'Прошуто'         => 'static/images/menu_items/pizza/proshuto.webp',
    'Весняна BBQ'     => 'static/images/menu_items/pizza/spring bbq.webp',
    'Козацька BBQ'    => 'static/images/menu_items/pizza/cossacks bbq.webp',
    '4 сезони'        => 'static/images/menu_items/pizza/4 seasons.webp',
    'Шинка гриби'     => 'static/images/menu_items/pizza/shinka-mushrooms.webp',
    'Цезар'           => 'static/images/menu_items/pizza/tsezar.webp',
    'Гавайська'       => 'static/images/menu_items/pizza/havaian.webp',
    'Мисливська'      => 'static/images/menu_items/pizza/hunter.webp',
    '4 види сиру'     => 'static/images/menu_items/pizza/4 cheese.webp',
    'З лососем'       => 'static/images/menu_items/pizza/losos.webp',
    'Палермо'         => 'static/images/menu_items/pizza/palermo.webp',
    'Салямі плюс'     => 'static/images/menu_items/pizza/salami+.webp',
    'Чікен'           => 'static/images/menu_items/pizza/chicken.webp',
    'Поло'            => 'static/images/menu_items/pizza/polo.webp',
    'Чілійська'       => 'static/images/menu_items/pizza/chiliyska.webp',
    'Авокадочка'      => 'static/images/menu_items/pizza/avocadochka.webp',
    'З тунцем'        => 'static/images/menu_items/pizza/tynets.webp',
];

$ok = 0; $miss = 0;
foreach ($pizzaImages as $name => $img) {
    $s = $conn->prepare("UPDATE pizza_items SET image=? WHERE name=? AND (image LIKE '%default%' OR image='')");
    $s->bind_param('ss', $img, $name);
    $s->execute();
    if ($s->affected_rows > 0) { $ok++; echo "OK pizza: $name\n"; }
    else { $miss++; echo "SKIP pizza: $name (no default or not found)\n"; }
    $s->close();
}
$log[] = "Pizza images: $ok updated, $miss skipped";

foreach ($log as $l) echo "\n$l";
echo "\n\nDone.\n";
