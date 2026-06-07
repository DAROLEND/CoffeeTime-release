<?php
session_start();
require '../db/db.php';
require_once '../includes/icons.php';

$tables = [
    'coffee_items'      => 'Кава',
    'fast_food_items'   => 'Фаст-фуд',
    'pizza_items'       => 'Піца',
    'cold_drink_items'  => 'Холодні напої',
    'dessert_items'     => 'Десерти',
    'sushi_items'       => 'Суші',
    'sushi_sets'        => 'Сети суші',
    'salad_items'       => 'Салати',
    'cake_items'        => 'Торти на замовлення',
    'sauces'            => 'Соуси',
];

if (isset($_GET['category']) && $_GET['category'] === 'mini_pizza_items') {
    header('Location: menu.php?category=pizza_items'); exit;
}
if (isset($_GET['category']) && $_GET['category'] === 'ice_cream_items') {
    header('Location: menu.php?category=dessert_items'); exit;
}
$current = $_GET['category'] ?? key($tables);
if (!isset($tables[$current])) $current = key($tables);

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES); }
function fmtPrice(float $p): string {
    return ($p == floor($p))
        ? number_format($p, 0, ',', ' ') . ' ₴'
        : number_format($p, 2, ',', ' ') . ' ₴';
}

$allItems = [];
$counts   = [];
foreach ($tables as $tbl => $label) {
    if ($tbl === 'sauces') continue; // populated separately below
    if ($tbl === 'cake_items') {
        $q = "SELECT id, name, description, image, price_per_kg AS price, min_weight, popularity FROM `$tbl` ORDER BY id";
    } elseif ($tbl === 'pizza_items') {
        $q = "SELECT id, name, description, image, price, price_large, sauce_type, is_spicy, has_size_choice, ingredients_tags, popularity FROM `$tbl` ORDER BY id";
    } elseif ($tbl === 'fast_food_items' || $tbl === 'ice_cream_items') {
        $q = "SELECT id, name, description, image, price, variant_options, popularity FROM `$tbl` ORDER BY id";
    } elseif ($tbl === 'coffee_items') {
        $q = "SELECT id, name, description, image, price, is_cold, popularity FROM `$tbl` ORDER BY id";
    } elseif ($tbl === 'sushi_items') {
        $q = "SELECT id, name, description, image, price, weight, popularity FROM `$tbl` ORDER BY id";
    } elseif ($tbl === 'sushi_sets') {
        $q = "SELECT id, name, description, image, price, weight, pieces, popularity FROM `$tbl` ORDER BY id";
    } else {
        $q = "SELECT id, name, description, image, price, popularity FROM `$tbl` ORDER BY id";
    }
    $stmt = $conn->prepare($q);
    $stmt->execute();
    $res = $stmt->get_result();
    $allItems[$tbl] = [];
    while ($row = $res->fetch_assoc()) $allItems[$tbl][] = $row;
    $counts[$tbl] = count($allItems[$tbl]);
    $stmt->close();
}

// Ice cream (merged into desserts section)
$icm = $conn->prepare("SELECT id, name, description, image, price, variant_options, popularity FROM ice_cream_items ORDER BY id");
$icm->execute();
$allItems['ice_cream_items'] = $icm->get_result()->fetch_all(MYSQLI_ASSOC);
$counts['ice_cream_items']   = count($allItems['ice_cream_items']);
$icm->close();

// Mini pizza (merged into pizza section)
$sm = $conn->prepare("SELECT id, name, description, image, price, sauce_type, is_spicy, ingredients_tags, popularity FROM mini_pizza_items ORDER BY id");
$sm->execute();
$r = $sm->get_result();
$allItems['mini_pizza_items'] = [];
while ($row = $r->fetch_assoc()) $allItems['mini_pizza_items'][] = $row;
$sm->close();

// Ingredient chips for pizza filter (collected from DB data)
// Tags excluded from the filter panel — too common (mozerella in every pizza) or too specific
$skipIngTags = ['моцарела', 'оливкова олія', '4 сири'];

function parseIngTags(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return [];
    // Try JSON first (e.g. '["курка","печериці"]'), fall back to comma-separated
    if ($raw[0] === '[') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return array_values(array_filter(array_map('trim', $decoded)));
    }
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

$allIngTags = [];
foreach (array_merge($allItems['pizza_items'], $allItems['mini_pizza_items']) as $pi) {
    if (!empty($pi['ingredients_tags'])) {
        foreach (parseIngTags($pi['ingredients_tags']) as $t) {
            if ($t !== '' && !in_array($t, $allIngTags, true) && !in_array(mb_strtolower($t), $skipIngTags, true))
                $allIngTags[] = $t;
        }
    }
}
sort($allIngTags);

// Sauces (add-on modal + standalone menu section)
$sauces = [];
$sq = $conn->prepare("SELECT id, name, price, image, emoji FROM sauces WHERE active=1 ORDER BY sort_order");
$sq->execute();
$sauces = $sq->get_result()->fetch_all(MYSQLI_ASSOC);
$sq->close();

// Reuse for menu section (renderCard needs description + popularity)
$allItems['sauces'] = array_map(fn($s) => $s + ['description' => '', 'popularity' => 0], $sauces);
$counts['sauces']   = count($allItems['sauces']);

$cartKeys = [];
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $ci) {
        if (isset($ci['category'], $ci['id']))
            $cartKeys[] = $ci['category'] . '_' . $ci['id'];
    }
}



function renderCard(array $item, string $tbl, string $label, array $cartKeys, int $cardIdx = 0): void {
    $inCart          = in_array($tbl . '_' . $item['id'], $cartKeys);
    $isPizza         = ($tbl === 'pizza_items');
    $isMiniPizza     = ($tbl === 'mini_pizza_items');
    $isPizzaType     = $isPizza || $isMiniPizza;
    $isFastFood      = ($tbl === 'fast_food_items');
    $isColdCoffee    = ($tbl === 'coffee_items') && !empty($item['is_cold']);
    $isIceCream      = ($tbl === 'ice_cream_items');
    $isSushi         = ($tbl === 'sushi_items');
    $isSushiSet      = ($tbl === 'sushi_sets');
    $sushiTags       = [];
    if (($isSushi || $isSushiSet) && !empty($item['weight'])) $sushiTags[] = $item['weight'];
    if ($isSushiSet && !empty($item['pieces']))               $sushiTags[] = (int)$item['pieces'] . ' шт';
    $hasFastFoodSize  = $isFastFood && !empty($item['variant_options'])
                        && (str_contains($item['variant_options'], '"type":"size"')
                            || str_contains($item['variant_options'], '"type":"filling"'));
    $hasSauceVariant  = $isFastFood && !empty($item['variant_options']) && !$hasFastFoodSize;
    $hasIceCreamScoop = $isIceCream && !empty($item['variant_options']);

    // FF size: compute price range and labels for display
    $ffSmallPrice = $ffLargePrice = (float)$item['price'];
    $ffSizeStr = '';
    if ($hasFastFoodSize) {
        $ffVo = json_decode($item['variant_options'], true);
        if (is_array($ffVo['options'] ?? null)) {
            $ffLabels = [];
            $maxDiff  = 0;
            foreach ($ffVo['options'] as $ffo) {
                $ffLabels[] = $ffo['label'] ?? '';
                $d = (float)($ffo['price_diff'] ?? 0);
                // For filling type with nested sizes, scan those too
                if (isset($ffo['sizes']) && is_array($ffo['sizes'])) {
                    foreach ($ffo['sizes'] as $sz) {
                        $td = $d + (float)($sz['price_diff'] ?? 0);
                        if ($td > $maxDiff) $maxDiff = $td;
                    }
                } else {
                    if ($d > $maxDiff) $maxDiff = $d;
                }
            }
            if ($maxDiff > 0) $ffLargePrice = $ffSmallPrice + $maxDiff;
            // Show labels only for 2-option type:"size" items (e.g. "Мала / Велика")
            // For filling type or 3+ options the card just shows the price range; modal has full list
            $ffSizeStr = (($ffVo['type'] ?? '') === 'size' && count($ffLabels) === 2)
                         ? implode(' / ', $ffLabels) : '';
        }
    }
    $hasSize         = $isPizza && !empty($item['has_size_choice']);
    $priceLarge      = $isPizza ? (float)($item['price_large'] ?? 0) : 0;
    $sauceType       = $isPizzaType ? ($item['sauce_type'] ?? 'tomato') : '';
    $isSpicy         = $isPizzaType ? (int)($item['is_spicy'] ?? 0) : 0;
    $ingTags         = $isPizzaType ? ($item['ingredients_tags'] ?? '') : '';
    $popularity      = (int)($item['popularity'] ?? 0);

    // Pizza-specific data attrs
    $pizzaDataAttrs = '';
    if ($isPizzaType) {
        // Parse ingredients_tags: handles both JSON arrays and comma-separated strings
        $tagsArr = parseIngTags($ingTags);
        $pizzaDataAttrs =
            ' data-sauce="'   . e($sauceType) . '"' .
            ' data-spicy="'   . $isSpicy . '"' .
            ' data-tags="'    . e(json_encode(array_values($tagsArr))) . '"';
    }

    $isDefault = ($item['image'] === 'static/images/menu_items/default.jpg' || empty($item['image']));
    $imgSrc    = $isDefault ? '' : '../' . e($item['image']);
?>
        <div id="item-<?= $item['id'] ?>"
             class="menu-card<?= $isPizzaType ? ' pizza-card' : '' ?><?= $cardIdx >= 9 ? ' lazy-hidden' : '' ?>"
             data-name="<?= e(mb_strtolower($item['name'])) ?>"
             data-desc="<?= e(mb_strtolower($item['description'] ?? '')) ?>"
             data-category="<?= e($tbl) ?>"
             data-price="<?= (float)$item['price'] ?>"
             data-ordered="<?= $popularity ?>"
             <?= $tbl === 'coffee_items' ? 'data-is-cold="' . (int)(!empty($item['is_cold'])) . '"' : '' ?>
             <?= $pizzaDataAttrs ?>>

          <div class="mc-img-zone">
            <?php if ($isDefault): ?>
              <div class="mc-no-img">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#d4c4b0" stroke-width="1.5" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span><?= e($item['name']) ?></span>
              </div>
            <?php else: ?>
              <img src="<?= $imgSrc ?>" alt="<?= e($item['name']) ?>" loading="lazy">
            <?php endif; ?>

            <?php if ($isPizzaType): ?>
              <?php if ($isSpicy): ?><span class="mc-badge mc-badge-spicy"><?= icon('filter-spicy', 16, '#e53935') ?> Гостра</span><?php endif; ?>
              <?php if ($sauceType === 'cream'): ?><span class="mc-badge mc-badge-cream">Вершковий</span>
              <?php elseif ($sauceType === 'bbq'): ?><span class="mc-badge mc-badge-bbq">BBQ</span><?php endif; ?>
              <?php if ($isMiniPizza): ?><span class="mc-badge mc-badge-mini">20 см</span><?php endif; ?>
            <?php endif; ?>
            <?php if ($isColdCoffee): ?><span class="mc-badge mc-badge-cold"><?= icon('snowflake', 16, '#1565c0') ?> Холодна</span><?php endif; ?>
            <?php if ($isIceCream): ?><span class="mc-badge mc-badge-icecream"><?= icon('icecream', 16, '#880e4f') ?> Морозиво</span><?php endif; ?>

            <div class="mc-img-overlay"
                 data-id="<?= (int)$item['id'] ?>"
                 data-cat="<?= e($tbl) ?>"
                 data-cat-label="<?= e($label) ?>"
                 data-name="<?= e($item['name']) ?>"
                 data-desc="<?= e($item['description'] ?? '') ?>"
                 data-price="<?= e(fmtPrice((float)$item['price'])) ?>"
                 data-price-per-kg="<?= $tbl === 'cake_items' ? (float)$item['price'] : '' ?>"
                 data-min-weight="<?= $tbl === 'cake_items' ? (float)($item['min_weight'] ?? 1) : '' ?>"
                 data-price-large="<?= $priceLarge ?>"
                 data-has-size="<?= $hasSize ? '1' : '0' ?>"
                 data-sauce-type="<?= e($sauceType) ?>"
                 data-is-fast-food="<?= $isFastFood ? '1' : '0' ?>"
                 data-is-ice-cream="<?= $isIceCream ? '1' : '0' ?>"
                 data-has-ff-size="<?= $hasFastFoodSize ? '1' : '0' ?>"
                 data-variant-options="<?= ($hasSauceVariant || $hasIceCreamScoop || $hasFastFoodSize) ? e($item['variant_options']) : '' ?>"
                 data-img="<?= $imgSrc ?>">
              <span class="mc-overlay-label">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Детальніше
              </span>
            </div>
          </div>

          <div class="mc-content">
            <h3 class="mc-name"><?= e($item['name']) ?></h3>
            <?php if (!empty($item['description'])): ?>
              <p class="mc-desc"><?= e($item['description']) ?></p>
            <?php endif; ?>
            <?php if ($isPizzaType && !empty($tagsArr)): ?>
            <div class="mc-tags">
              <?php foreach (array_slice($tagsArr, 0, 4) as $tag): ?>
                <span class="mc-tag"><?= e($tag) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($sushiTags)): ?>
            <div class="mc-tags mc-tags-sushi">
              <?php foreach ($sushiTags as $tag): ?>
                <span class="mc-tag mc-tag-sushi"><?= e($tag) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="mc-footer">
              <?php if ($isPizza && $priceLarge > 0 && $hasSize): ?>
                <div class="mc-price-dual">
                  <span class="mc-price"><?= fmtPrice((float)$item['price']) ?></span>
                  <span class="mc-price-sep">/</span>
                  <span class="mc-price mc-price-large"><?= fmtPrice($priceLarge) ?></span>
                  <span class="mc-price-sizes">30 / 40 см</span>
                </div>
              <?php elseif ($hasFastFoodSize && $ffLargePrice > $ffSmallPrice): ?>
                <div class="mc-price-dual">
                  <span class="mc-price"><?= fmtPrice($ffSmallPrice) ?></span>
                  <span class="mc-price-sep">/</span>
                  <span class="mc-price mc-price-large"><?= fmtPrice($ffLargePrice) ?></span>
                  <?php if ($ffSizeStr): ?><span class="mc-price-sizes"><?= e($ffSizeStr) ?></span><?php endif; ?>
                </div>
              <?php else: ?>
                <span class="mc-price"><?= fmtPrice((float)$item['price']) ?></span>
              <?php endif; ?>

              <?php if ($tbl === 'cake_items'): ?>
              <button class="mc-add-btn mc-order-btn"
                      data-id="<?= (int)$item['id'] ?>" data-cat="<?= e($tbl) ?>"
                      data-name="<?= e($item['name']) ?>"
                      data-price-per-kg="<?= (float)$item['price'] ?>"
                      data-min-weight="<?= (float)($item['min_weight'] ?? 1) ?>">Замовити</button>
              <?php elseif ($hasSize): ?>
              <button class="mc-add-btn mc-size-btn"
                      data-id="<?= (int)$item['id'] ?>" data-cat="<?= e($tbl) ?>"
                      data-name="<?= e($item['name']) ?>"
                      data-price="<?= (float)$item['price'] ?>"
                      data-price-large="<?= $priceLarge ?>"
                      data-sauce-type="<?= e($sauceType) ?>">Обрати</button>
              <?php elseif ($hasIceCreamScoop): ?>
              <button class="mc-add-btn mc-scoop-btn <?= $inCart ? 'in-cart' : '' ?>"
                      data-id="<?= (int)$item['id'] ?>" data-cat="<?= e($tbl) ?>"
                      data-name="<?= e($item['name']) ?>"><?= $inCart ? 'В кошику ✓' : 'Обрати' ?></button>
              <?php elseif ($hasFastFoodSize): ?>
              <button class="mc-add-btn mc-ff-size-btn <?= $inCart ? 'in-cart' : '' ?>"
                      data-id="<?= (int)$item['id'] ?>" data-cat="<?= e($tbl) ?>"
                      data-name="<?= e($item['name']) ?>"><?= $inCart ? 'В кошику ✓' : 'Обрати' ?></button>
              <?php else: ?>
              <button class="mc-add-btn <?= $inCart ? 'in-cart' : '' ?>"
                      data-id="<?= (int)$item['id'] ?>" data-cat="<?= e($tbl) ?>"
                      data-name="<?= e($item['name']) ?>"
                      <?= $hasSauceVariant ? 'data-variant-options="' . e($item['variant_options']) . '"' : '' ?>>
                <?= $inCart ? 'В кошику ✓' : 'Додати' ?>
              </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
<?php
}

$groups = [
    'drinks'   => [
        'label' => 'Напої',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4z"/><path d="M7 3c.5.8 1 1 1 2M11 3c.5.8 1 1 1 2"/></svg>',
        'cats'  => ['coffee_items', 'cold_drink_items'],
    ],
    'food'     => [
        'label' => 'Їжа',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 .6.4 1 1 1h4v7"/></svg>',
        'cats'  => ['pizza_items', 'salad_items', 'dessert_items', 'cake_items'],
    ],
    'fastfood' => [
        'label' => 'Фаст-фуд',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11c0-4 3.6-7 8-7s8 3 8 7"/><path d="M3 11h18"/><path d="M3 15h18"/><path d="M5 19h14a2 2 0 0 0 2-2v-2H3v2a2 2 0 0 0 2 2z"/></svg>',
        'cats'  => ['fast_food_items', 'sauces'],
    ],
    'sushi'    => [
        'label' => 'Суші',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M3.5 8.5h4M16.5 8.5h4M3.5 15.5h4M16.5 15.5h4"/></svg>',
        'cats'  => ['sushi_items', 'sushi_sets'],
    ],
];
$currentGroup = 'drinks';
foreach ($groups as $gid => $g) {
    if (in_array($current, $g['cats'])) { $currentGroup = $gid; break; }
}

$page         = 'menu';
$pageTitle    = 'Меню — Coffee Time';
$customStyles = ['../static/css/menu.css'];
include '../includes/header.php';
?>

<main class="menu-page">

  <div class="menu-hero">
    <h1 class="menu-title">Наше меню</h1>
    <p class="menu-subtitle">Свіжа кава, смачна їжа та затишна атмосфера</p>
  </div>

  <div class="menu-search-wrap">
    <svg class="msw-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input type="text" id="menuSearch" class="menu-search-input"
           placeholder="Пошук по меню…" autocomplete="off" spellcheck="false">
    <button class="msw-clear" id="menuSearchClear" aria-label="Очистити" style="display:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>
  </div>

  <!-- ── Tabs: outside menu-body so sticky spans full width ── -->
  <div class="menu-tabs-outer" id="menuTabsOuter">
    <div class="menu-tabs-wrap" id="menuTabsWrap">
      <div class="menu-nav">

        <!-- Level 1: Main group tabs -->
        <div class="menu-main-tabs">
          <?php foreach ($groups as $gid => $g): ?>
          <button class="mmt-btn <?= $gid === $currentGroup ? 'active' : '' ?>" data-group="<?= e($gid) ?>">
            <span class="mmt-icon"><?= $g['icon'] ?></span>
            <span class="mmt-label"><?= e($g['label']) ?></span>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Level 2: Subcategory tabs per group (skip if only 1 category) -->
        <?php foreach ($groups as $gid => $g):
          $visibleCats = array_filter($g['cats'], fn($t) => isset($tables[$t]));
          if (count($visibleCats) <= 1) continue;
        ?>
        <div class="menu-sub-tabs<?= $gid === $currentGroup ? ' active' : '' ?>" data-group="<?= e($gid) ?>">
          <?php foreach ($g['cats'] as $tbl):
            if (!isset($tables[$tbl])) continue;
            $subLabel = $tables[$tbl];
            $tabCount = ($tbl === 'pizza_items')
              ? $counts['pizza_items'] + count($allItems['mini_pizza_items'])
              : ($tbl === 'dessert_items'
                  ? ($counts['dessert_items'] ?? 0) + ($counts['ice_cream_items'] ?? 0)
                  : ($counts[$tbl] ?? 0));
          ?>
          <button class="menu-tab<?= $tbl === $current ? ' active' : '' ?>"
                  data-cat="<?= e($tbl) ?>" role="tab"
                  aria-selected="<?= $tbl === $current ? 'true' : 'false' ?>">
            <?= e($subLabel) ?><span class="tab-badge"><?= $tabCount ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

      </div>
    </div>
  </div>

  <div class="menu-body">

    <!-- ══════════════════════════════════════════
         FILTER BAR — always shown, sort for all
         pizza-specific controls shown/hidden by JS
    ══════════════════════════════════════════ -->
    <div id="menu-filter-bar" style="display:none; margin:12px 0 4px;">
      <div class="mfb-inner">

        <!-- Сортування — ALWAYS shown, always first -->
        <div class="filter-dropdown" style="position:relative;">
          <button class="filter-btn" id="sortFilterBtn" data-filter="sort">
            <?= icon('sort', 18, 'currentColor') ?>
            <span id="sortBtnLabel">Сортування</span>
            <svg class="fd-arrow" width="12" height="12" viewBox="0 0 12 12"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
          </button>
          <div class="filter-dropdown-menu" id="sortMenu">
            <div class="sort-option active" data-sort="default"><?= icon('default-sort', 16, 'currentColor', 'fo-icon') ?> За замовчуванням</div>
            <div class="sort-option" data-sort="price_asc"><?= icon('arrow-up', 16, 'currentColor', 'fo-icon') ?> Ціна: від дешевших</div>
            <div class="sort-option" data-sort="price_desc"><?= icon('arrow-down', 16, 'currentColor', 'fo-icon') ?> Ціна: від дорожчих</div>
            <div class="sort-option" data-sort="popular"><?= icon('trending', 16, 'currentColor', 'fo-icon') ?> За популярністю</div>
          </div>
        </div>

        <!-- Separator + температурний фільтр (coffee only) -->
        <div class="mfb-sep" id="coffee-sep" style="display:none;"></div>
        <div class="filter-dropdown" id="coffee-temp-wrap" style="display:none;position:relative;">
          <button class="filter-btn" id="coffeeTempBtn" data-filter="coffee-temp">
            <?= icon('coffee-cup', 18, 'currentColor') ?>
            <span id="coffeeTempLabel">Тип</span>
            <svg class="fd-arrow" width="12" height="12" viewBox="0 0 12 12"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
          </button>
          <div class="filter-dropdown-menu" id="coffeeTempMenu">
            <div class="filter-option active" data-value="all"><?= icon('coffee-cup', 16, 'currentColor', 'fo-icon') ?> Всі</div>
            <div class="filter-option" data-value="hot"><?= icon('coffee-cup', 16, '#c0623d', 'fo-icon') ?> Тепла</div>
            <div class="filter-option" data-value="cold"><?= icon('snowflake', 16, '#1565c0', 'fo-icon') ?> Холодна</div>
          </div>
        </div>

        <!-- Separator (pizza only) -->
        <div class="mfb-sep" id="pizza-sep" style="display:none;"></div>

        <!-- Соус (pizza only) -->
        <div class="filter-dropdown" id="sauce-filter-wrap" style="display:none;position:relative;">
          <button class="filter-btn" id="sauceFilterBtn" data-filter="sauce">
            <?= icon('filter-sauce-tomato', 18, 'currentColor') ?>
            <span id="sauceBtnLabel">Соус</span>
            <svg class="fd-arrow" width="12" height="12" viewBox="0 0 12 12"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
          </button>
          <div class="filter-dropdown-menu" id="sauceMenu">
            <div class="filter-option active" data-value="all"><?= icon('filter-sauce-tomato', 16, 'currentColor', 'fo-icon') ?> Всі</div>
            <div class="filter-option" data-value="tomato"><?= icon('filter-sauce-tomato', 16, '#c0392b', 'fo-icon') ?> Томатний</div>
            <div class="filter-option" data-value="cream"><?= icon('filter-sauce-cream', 16, '#b8860b', 'fo-icon') ?> Вершковий</div>
            <div class="filter-option" data-value="bbq"><?= icon('filter-bbq', 16, '#6d2f00', 'fo-icon') ?> BBQ</div>
          </div>
        </div>

        <!-- Склад (pizza only) -->
        <div class="filter-dropdown" id="ingredients-filter-wrap" style="display:none;position:relative;">
          <button class="filter-btn" id="ingFilterBtn" data-filter="ingredients">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="11" cy="11" r="8"/><path d="M8 11h6M11 8v6"/></svg>
            <span id="ingBtnLabel">Склад</span>
            <svg class="fd-arrow" width="12" height="12" viewBox="0 0 12 12"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
          </button>
          <div class="filter-dropdown-menu fdm-wide" id="ingMenu">
            <div class="fdm-hint">Мультивибір інгредієнтів</div>
            <div class="fdm-chips">
              <?php foreach ($allIngTags as $tag): ?>
                <button class="ingredient-chip" data-tag="<?= e(mb_strtolower(trim($tag)))?>"><?= e($tag) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Гострі toggle (pizza only) -->
        <button class="filter-btn" id="spicy-toggle" style="display:none;"><?= icon('filter-spicy', 18, 'currentColor') ?> Гострі</button>

        <!-- Active filter badges -->
        <div id="active-filters-bar" style="display:none;">
          <!-- JS populates here -->
        </div>

      </div>
    </div><!-- /#menu-filter-bar -->

    <!-- ── Category sections ── -->
    <?php foreach ($tables as $tbl => $label):
      $isEmpty = ($tbl === 'pizza_items')
        ? (empty($allItems['pizza_items']) && empty($allItems['mini_pizza_items']))
        : empty($allItems[$tbl]);
    ?>
    <section class="menu-cat-section <?= $tbl === $current ? 'active' : '' ?>"
             data-cat="<?= e($tbl) ?>" data-label="<?= e($label) ?>" id="cat-<?= e($tbl) ?>">

      <?php if ($isEmpty): ?>
        <div class="menu-empty-state">
          <div class="mes-icon">
            <svg width="72" height="72" viewBox="0 0 72 72" fill="none">
              <circle cx="36" cy="36" r="32" stroke="#e8ddd5" stroke-width="2"/>
              <path d="M22 36c0-7.732 6.268-14 14-14s14 6.268 14 14-6.268 14-14 14-14-6.268-14-14z" stroke="#d4c4b8" stroke-width="2"/>
              <path d="M30 36h12M36 30v12" stroke="#d4c4b8" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </div>
          <h3>Ця категорія поки порожня</h3>
          <p>Скоро тут з'являться нові позиції</p>
        </div>
      <?php else: ?>
        <div class="menu-grid" id="grid-<?= e($tbl) ?>">
          <?php $ci = 0; foreach ($allItems[$tbl] as $item): renderCard($item, $tbl, $label, $cartKeys, $ci++); endforeach; ?>
          <?php if ($tbl === 'pizza_items'):
            foreach ($allItems['mini_pizza_items'] as $item): renderCard($item, 'mini_pizza_items', 'Міні-піца', $cartKeys, $ci++); endforeach;
          endif; ?>
          <?php if ($tbl === 'dessert_items'):
            foreach ($allItems['ice_cream_items'] as $item): renderCard($item, 'ice_cream_items', 'Морозиво', $cartKeys, $ci++); endforeach;
          endif; ?>
        </div>

        <?php if ($tbl === 'pizza_items'): ?>
        <div id="no-pizza-results" class="no-pizza-results" style="display:none">
          <span class="npr-icon"></span>
          <p>Нічого не знайдено за заданими фільтрами</p>
          <button class="pf-reset-link" id="noResultsReset">Скинути фільтри</button>
        </div>
        <?php endif; ?>

        <div class="menu-search-empty" id="sem-<?= e($tbl) ?>" style="display:none">
          <span class="mse-emoji"></span>
          <p class="mse-text"></p>
          <button class="mse-reset">Скинути пошук</button>
        </div>
      <?php endif; ?>

    </section>
    <?php endforeach; ?>

  </div><!-- /.menu-body -->
</main>

<!-- ── Item detail modal ── -->
<div class="item-modal-overlay" id="itemModalOverlay">
  <div class="item-modal" id="itemModal">
    <button class="item-modal-close" id="itemModalClose" aria-label="Закрити">✕</button>
    <div class="im-img-col">
      <img id="imModalImg" src="" alt="">
    </div>
    <div class="im-info-col">
      <span class="im-cat-badge" id="imModalCatBadge"></span>
      <h2 class="im-name" id="imModalName"></h2>
      <p class="im-desc" id="imModalDesc"></p>
      <div class="im-divider"></div>

      <!-- Pizza size -->
      <div class="im-size-wrap" id="imSizeWrap" style="display:none">
        <div class="im-option-label">Розмір:</div>
        <div class="im-size-btns">
          <button class="im-size-btn active" data-size="small" id="imSizeSmall">
            30 см<br><span class="im-size-price" id="imSizePriceSmall"></span>
          </button>
          <button class="im-size-btn" data-size="large" id="imSizeLarge">
            40 см<br><span class="im-size-price" id="imSizePriceLarge"></span>
          </button>
        </div>
      </div>

      <!-- Cheese crust (compact row) -->
      <div class="pizza-option-row" id="imCrustWrap" style="display:none">
        <label>
          <input type="checkbox" id="imCheeseCrust" style="display:none">
          <div class="cb-box" id="crustCbBox"></div>
          <span>Сирний бортик</span>
          <span class="crust-price-label" id="crustPriceLabel">+65 ₴</span>
        </label>
      </div>

      <!-- Takeaway -->
      <div class="im-extra-wrap" id="imInboxWrap" style="display:none">
        <label class="im-checkbox-label">
          <input type="checkbox" id="imInBox">
          <span>З собою</span>
        </label>
      </div>

      <!-- Ice cream scoops / FF filling selector -->
      <div id="imScoopsWrap" style="display:none">
        <div class="im-option-label" id="imScoopsLabel">Кількість кульок:</div>
        <div class="im-scoops-grid" id="imScoopsGrid">
          <!-- JS populates scoop/filling buttons here -->
        </div>
      </div>

      <!-- FF filling size sub-section (animated, shown only when filling has sizes) -->
      <div id="imFfSizeWrap" class="im-size-wrap im-ff-size-sub" style="display:none">
        <div class="im-option-label">Розмір:</div>
        <div class="im-size-btns" id="imFfSizeGrid">
          <!-- JS populates size buttons here -->
        </div>
      </div>

      <!-- Add-on sauces (fast food) -->
      <div id="imSaucesWrap" style="display:none">
        <div class="im-option-label">Додати соус:</div>
        <div class="im-sauces-grid">
          <?php foreach ($sauces as $sauce): ?>
          <label class="im-sauce-chip">
            <input type="checkbox" class="sauce-checkbox"
                   data-sauce-id="<?= (int)$sauce['id'] ?>"
                   data-sauce-name="<?= e($sauce['name']) ?>"
                   data-sauce-price="<?= (float)$sauce['price'] ?>"
                   style="display:none">
            <span><?= e($sauce['emoji']) ?> <?= e($sauce['name']) ?></span>
            <?php if ($sauce['price'] > 0): ?>
            <span class="im-sauce-price">+<?= (int)$sauce['price'] ?> ₴</span>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <span class="im-price" id="imModalPrice"></span>
      <div class="im-footer">
        <div class="im-qty" id="imQtyWrap">
          <button class="im-qty-btn" id="imQtyMinus">−</button>
          <span class="im-qty-val" id="imQtyVal">1</span>
          <button class="im-qty-btn" id="imQtyPlus">+</button>
        </div>
        <div class="im-weight-wrap" id="imWeightWrap" style="display:none;">
          <label class="im-weight-label">Вага (кг):</label>
          <div class="im-qty">
            <button class="im-qty-btn" id="imWeightMinus">−</button>
            <span class="im-qty-val" id="imWeightVal">1</span>
            <button class="im-qty-btn" id="imWeightPlus">+</button>
          </div>
          <span class="im-weight-total" id="imWeightTotal"></span>
        </div>
        <button class="im-add-btn" id="imModalAdd">Додати в кошик</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Sauce popup (fast-food card button) ── -->
<div class="sauce-popup" id="saucePopup">
  <div class="sp-header">
    <span class="sp-title" id="spTitle">Який соус бажаєте?</span>
    <button class="sp-close" id="spClose" aria-label="Закрити">✕</button>
  </div>
  <div class="sp-options" id="spOptions"></div>
</div>

<!-- ── Sauce picker modal (add-on sauces) ── -->
<div class="sm-overlay" id="smOverlay" role="dialog" aria-modal="true">
  <div class="sm-box">
    <div class="sm-head">
      <div>
        <div class="sm-title">Додати соус?</div>
        <div class="sm-subtitle" id="smSubtitle"></div>
      </div>
      <button class="sm-close" id="smClose" type="button" aria-label="Закрити">✕</button>
    </div>
    <div class="sm-list" id="smList">
      <?php foreach ($sauces as $sc): ?>
      <div class="sm-row"
           data-sauce-id="<?= (int)$sc['id'] ?>"
           data-sauce-name="<?= e($sc['name']) ?>"
           data-sauce-price="<?= (float)$sc['price'] ?>">
        <button class="sm-toggle" type="button">
          <?php
            $scImg = !empty($sc['image']) && !str_contains($sc['image'], 'default.jpg') && file_exists(__DIR__ . '/../' . $sc['image']);
          ?>
          <?php if ($scImg): ?>
            <img src="../<?= e($sc['image']) ?>" class="sm-img" alt="">
          <?php else: ?>
            <span class="sm-img sm-img--empty"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c8b9a8" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></span>
          <?php endif; ?>
          <span class="sm-name"><?= e($sc['name']) ?></span>
          <span class="sm-price<?= (float)$sc['price'] <= 0 ? ' sm-price--free' : '' ?>">
            <?= (float)$sc['price'] > 0 ? '+' . (int)$sc['price'] . ' ₴' : 'Безкоштовно' ?>
          </span>
        </button>
        <div class="sm-check">
          <svg class="sm-check-icon" width="10" height="8" viewBox="0 0 10 8" fill="none">
            <path d="M1 4l3 3 5-6" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="sm-qty">
          <button class="sm-qty-btn sm-minus" type="button" aria-label="Менше">−</button>
          <span class="sm-qty-val">1</span>
          <button class="sm-qty-btn sm-plus" type="button" aria-label="Більше">+</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="sm-footer">
      <button class="sm-skip" id="smSkip" type="button">Пропустити</button>
      <button class="sm-confirm" id="smConfirm" type="button">Додати в кошик</button>
    </div>
  </div>
</div>

<!-- ── Toast ── -->
<div class="menu-toast" id="menuToast"></div>

<?php include '../includes/footer.php'; ?>
<script>
window._cartKeys   = <?= json_encode($cartKeys) ?>;
window._currentCat = <?= json_encode($current) ?>;
window._scrollTo   = <?= (int)($_GET['scroll_to'] ?? 0) ?>;
</script>
<script src="../static/js/menu.js?v=<?= filemtime(__DIR__ . '/../static/js/menu.js') ?>"></script>
<script>
(function() {
  var itemId = window._scrollTo;
  if (!itemId) return;
  window._scrollTo = 0; /* clear immediately so re-opens don't re-trigger */
  var el = document.getElementById('item-' + itemId);
  if (!el) return;
  el.classList.remove('lazy-hidden');
  window.addEventListener('load', function() {
    setTimeout(function() {
      var stickyBar = document.querySelector('.menu-tabs-outer');
      var stickyH   = stickyBar ? stickyBar.offsetHeight : 60;
      var offset    = el.getBoundingClientRect().top + window.pageYOffset - 76 - stickyH - 20;
      window.scrollTo({ top: Math.max(0, offset), behavior: 'smooth' });
      el.style.outline      = '2px solid #FFC107';
      el.style.borderRadius = '14px';
      setTimeout(function() {
        el.style.transition = 'outline-color 0.6s ease';
        el.style.outline    = '2px solid transparent';
      }, 900);
    }, 200);
  });
})();
</script>
</body>
</html>
