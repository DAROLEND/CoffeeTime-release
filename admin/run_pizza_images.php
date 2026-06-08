<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_super();

$conn->set_charset('utf8mb4');

$updates = [
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/salami.webp',           'Салямі'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/miasna.webp',           "М'ясна"],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/with chicken.webp',     'З куркою'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/mega miasna.webp',      "Мега м'ясна"],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/diabla.webp',           'Діабла'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/with potato free.webp', 'З картоплею Фрі'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/proshuto.webp',         'Прошуто'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/spring bbq.webp',       'Весняна BBQ'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/cossacks bbq.webp',     'Козацька BBQ'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/4 seasons.webp',        '4 сезони'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/shinka-mushrooms.webp', 'Шинка гриби'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/tsezar.webp',           'Цезар'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/havaian.webp',          'Гавайська'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/hunter.webp',           'Мисливська'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/4 cheese.webp',         '4 види сиру'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/losos.webp',            'З лососем'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/palermo.webp',          'Палермо'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/salami+.webp',          'Салямі плюс'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/chicken.webp',          'Чікен'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/polo.webp',             'Поло'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/chiliyska.webp',        'Чілійська'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/avocadochka.webp',      'Авокадочка'],
    ["UPDATE pizza_items SET image=? WHERE name=?",      'static/images/menu_items/pizza/tynets.webp',           'З тунцем'],
    ["UPDATE mini_pizza_items SET image=? WHERE name=?", 'static/images/menu_items/mini_pizza/mini-salami.webp', 'Міні Салямі'],
    ["UPDATE mini_pizza_items SET image=? WHERE name=?", 'static/images/menu_items/mini_pizza/mini-cheese.webp', 'Міні Сирна'],
];

header('Content-Type: text/plain; charset=utf-8');
$ok = 0; $fail = 0;
foreach ($updates as [$sql, $img, $name]) {
    $s = $conn->prepare($sql);
    $s->bind_param('ss', $img, $name);
    $s->execute();
    if ($s->affected_rows > 0) { $ok++; echo "OK: $name\n"; }
    else { $fail++; echo "MISS: $name\n"; }
    $s->close();
}
echo "\nDone. $ok updated, $fail not found.\n";
