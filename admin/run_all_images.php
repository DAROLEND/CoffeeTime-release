<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_super();

$conn->set_charset('utf8mb4');
header('Content-Type: text/plain; charset=utf-8');

if (!empty($_GET['check'])) {
    $tables = ['fast_food_items','cold_drink_items','salad_items','cake_items','ice_cream_items','sauces'];
    foreach ($tables as $t) {
        echo "=== $t ===\n";
        $r = $conn->query("SELECT id, name, image FROM `$t` ORDER BY id");
        while ($row = $r->fetch_assoc()) echo $row['id'] . "\t[" . $row['name'] . "]\t" . $row['image'] . "\n";
        echo "\n";
    }
    exit;
}

$updates = [
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/strips.webp',           5],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/onion circles.webp',    7],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/nagets.webp',           8],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/potato balls.webp',     10],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/potato by village.webp',12],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/hot-dog.webp',          15],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/american hot-dog.webp', 16],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/lavash.webp',           17],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/corn.webp',             22],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/panini.webp',           23],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/cheesburger.webp',      13],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/burger.webp',           14],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/potato free.webp',      11],
    ["UPDATE fast_food_items SET image=? WHERE id=?",  'static/images/menu_items/fast_food/cheese sticks.webp',     6],

    ["UPDATE cold_drink_items SET image=? WHERE id=?", 'static/images/menu_items/cold_drinks/mohito.webp',         4],
    ["UPDATE cold_drink_items SET image=? WHERE id=?", 'static/images/menu_items/cold_drinks/milk coctail.webp',   5],
    ["UPDATE cold_drink_items SET image=? WHERE id=?", 'static/images/menu_items/cold_drinks/fruit coctail.webp',  6],
    ["UPDATE cold_drink_items SET image=? WHERE id=?", 'static/images/menu_items/cold_drinks/bubble tea.webp',     7],
    ["UPDATE cold_drink_items SET image=? WHERE id=?", 'static/images/menu_items/cold_drinks/kakao bubble.webp',   8],

    ["UPDATE salad_items SET image=? WHERE id=?",      'static/images/menu_items/salads/tsezar with losos.webp',   1],
    ["UPDATE salad_items SET image=? WHERE id=?",      'static/images/menu_items/salads/greece salad.webp',        2],
    ["UPDATE salad_items SET image=? WHERE id=?",      'static/images/menu_items/salads/iceberg.webp',             3],
    ["UPDATE salad_items SET image=? WHERE id=?",      'static/images/menu_items/salads/tsezar wuth chicken.webp', 4],
    ["UPDATE salad_items SET image=? WHERE id=?",      'static/images/menu_items/salads/hunter salad.webp',        5],
    ["UPDATE salad_items SET image=? WHERE id=?",      'static/images/menu_items/salads/salad with tuna.webp',     6],

    ["UPDATE cake_items SET image=? WHERE id=?",       'static/images/menu_items/desserts/medovik.webp',           1],
    ["UPDATE cake_items SET image=? WHERE id=?",       'static/images/menu_items/desserts/napolen.webp',           2],
    ["UPDATE cake_items SET image=? WHERE id=?",       'static/images/menu_items/cakes/choco cake.webp',           3],
    ["UPDATE cake_items SET image=? WHERE id=?",       'static/images/menu_items/cakes/child cake.webp',           5],

    ["UPDATE ice_cream_items SET image=? WHERE id=?",  'static/images/menu_items/ice_cream/ice cream.webp',        1],

    ["UPDATE sauces SET image=? WHERE id=?",           'static/images/menu_items/sauces/sauce ketchup.webp',       1],
    ["UPDATE sauces SET image=? WHERE id=?",           'static/images/menu_items/sauces/sauce bbq.webp',           2],
    ["UPDATE sauces SET image=? WHERE id=?",           'static/images/menu_items/sauces/sauce heese.webp',         3],
    ["UPDATE sauces SET image=? WHERE id=?",           'static/images/menu_items/sauces/sauce sour-sweet.webp',    4],
    ["UPDATE sauces SET image=? WHERE id=?",           'static/images/menu_items/sauces/sauce garlic.webp',        5],
    ["UPDATE sauces SET image=? WHERE id=?",           'static/images/menu_items/sauces/sauce mustard.webp',       6],
    ["UPDATE sauces SET image=? WHERE id=?",           'static/images/menu_items/sauces/sauce mayo.webp',          7],
];

$ok = 0; $fail = 0;
foreach ($updates as [$sql, $img, $id]) {
    $s = $conn->prepare($sql);
    $s->bind_param('si', $img, $id);
    $s->execute();
    if ($s->affected_rows > 0) { $ok++; echo "OK: id=$id ($img)\n"; }
    else { $fail++; echo "MISS: id=$id\n"; }
    $s->close();
}
echo "\nDone. $ok updated, $fail skipped (already set).\n";
