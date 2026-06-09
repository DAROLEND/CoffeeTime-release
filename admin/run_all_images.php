<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_super();

$conn->set_charset('utf8mb4');
header('Content-Type: text/plain; charset=utf-8');

$updates = [
    // fast_food_items — default / empty
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/strips.webp',          'Стріпси'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/onion circles.webp',   'Цибулеві кільця'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/nagets.webp',          'Нагетси'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/potato balls.webp',    'Картопляні кульки'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/potato by village.webp','Картопля по-селянськи'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/hot-dog.webp',         'Французький хот-дог'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/american hot-dog.webp','Американський хот-дог'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/lavash.webp',          'Лаваш'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/corn.webp',            'Кукурудза з сиром'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/panini.webp',          'Паніні'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/cheesburger.webp',     'Чізбургер'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/burger.webp',          'Гамбургер'],
    ["UPDATE fast_food_items SET image=? WHERE name=?",  'static/images/menu_items/fast_food/potato free.webp',     'Картопля Фрі'],

    // cold_drink_items
    ["UPDATE cold_drink_items SET image=? WHERE name=?", 'static/images/menu_items/cold_drinks/mohito.webp',        'Мохіто'],
    ["UPDATE cold_drink_items SET image=? WHERE name=?", 'static/images/menu_items/cold_drinks/milk coctail.webp',  'Молочний коктейль'],
    ["UPDATE cold_drink_items SET image=? WHERE name=?", 'static/images/menu_items/cold_drinks/fruit coctail.webp', 'Фруктовий коктейль'],
    ["UPDATE cold_drink_items SET image=? WHERE name=?", 'static/images/menu_items/cold_drinks/bubble tea.webp',    'Бабл ті'],
    ["UPDATE cold_drink_items SET image=? WHERE name=?", 'static/images/menu_items/cold_drinks/kakao bubble.webp',  'Какао бабл'],

    // salad_items
    ["UPDATE salad_items SET image=? WHERE name=?",      'static/images/menu_items/salads/tsezar with losos.webp',  'Цезар з лососем'],
    ["UPDATE salad_items SET image=? WHERE name=?",      'static/images/menu_items/salads/greece salad.webp',       'Грецький'],
    ["UPDATE salad_items SET image=? WHERE name=?",      'static/images/menu_items/salads/iceberg.webp',            'Айсберг'],
    ["UPDATE salad_items SET image=? WHERE name=?",      'static/images/menu_items/salads/tsezar wuth chicken.webp','Цезар з куркою'],
    ["UPDATE salad_items SET image=? WHERE name=?",      'static/images/menu_items/salads/hunter salad.webp',       'Мисливський'],
    ["UPDATE salad_items SET image=? WHERE name=?",      'static/images/menu_items/salads/salad with tuna.webp',    'З тунцем'],

    // cake_items (Медовик і Наполеон зберігаються в desserts/)
    ["UPDATE cake_items SET image=? WHERE name=?",       'static/images/menu_items/desserts/medovik.webp',          'Медовик'],
    ["UPDATE cake_items SET image=? WHERE name=?",       'static/images/menu_items/desserts/napolen.webp',          'Наполеон'],
    ["UPDATE cake_items SET image=? WHERE name=?",       'static/images/menu_items/cakes/choco cake.webp',          'Шоколадний'],
    ["UPDATE cake_items SET image=? WHERE name=?",       'static/images/menu_items/cakes/child cake.webp',          'Дитячий торт'],

    // ice_cream_items
    ["UPDATE ice_cream_items SET image=? WHERE name=?",  'static/images/menu_items/ice_cream/ice cream.webp',       'Морозиво'],

    // sauces
    ["UPDATE sauces SET image=? WHERE name=?",           'static/images/menu_items/sauces/sauce ketchup.webp',      'Кетчуп'],
    ["UPDATE sauces SET image=? WHERE name=?",           'static/images/menu_items/sauces/sauce bbq.webp',          'Барбекю'],
    ["UPDATE sauces SET image=? WHERE name=?",           'static/images/menu_items/sauces/sauce heese.webp',        'Сирний'],
    ["UPDATE sauces SET image=? WHERE name=?",           'static/images/menu_items/sauces/sauce sour-sweet.webp',   'Кисло-солодкий'],
    ["UPDATE sauces SET image=? WHERE name=?",           'static/images/menu_items/sauces/sauce garlic.webp',       'Часниковий'],
    ["UPDATE sauces SET image=? WHERE name=?",           'static/images/menu_items/sauces/sauce mustard.webp',      'Гірчиця'],
    ["UPDATE sauces SET image=? WHERE name=?",           'static/images/menu_items/sauces/sauce mayo.webp',         'Майонез'],
];

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
