<?php
session_start();
require '../db/db.php';

$tables = [
    'coffee_items'     => 'Кава',
    'fast_food_items'  => 'Фаст-фуд',
    'pizza_items'      => 'Піца',
    'cold_drink_items' => 'Холодні напої',
    'dessert_items'    => 'Десерти',
    'sushi_items'      => 'Суші',
    'sushi_sets'       => 'Сети суші',
    'salad_items'      => 'Салати',
    'cake_items'       => 'Торти на замовлення',
];
$current = $_GET['category'] ?? key($tables);
if (!isset($tables[$current])) $current = key($tables);

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES); }
function fmtPrice(float $p): string {
    return ($p == floor($p))
        ? number_format($p, 0, ',', ' ') . ' ₴'
        : number_format($p, 2, ',', ' ') . ' ₴';
}

/* ── Fetch all items for all categories ── */
$allItems = [];
$counts   = [];
foreach ($tables as $tbl => $label) {
    if ($tbl === 'cake_items') {
        $stmt = $conn->prepare("SELECT id, name, description, image, price_per_kg AS price, min_weight FROM `$tbl` ORDER BY id");
    } else {
        $stmt = $conn->prepare("SELECT id, name, description, image, price FROM `$tbl` ORDER BY id");
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $allItems[$tbl] = [];
    while ($row = $res->fetch_assoc()) $allItems[$tbl][] = $row;
    $counts[$tbl] = count($allItems[$tbl]);
    $stmt->close();
}

/* ── Cart keys for "already added" state ── */
$cartKeys = [];
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $ci) {
        if (isset($ci['category'], $ci['id']))
            $cartKeys[] = $ci['category'] . '_' . $ci['id'];
    }
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

  <!-- ── Search ── -->
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

  <!-- ── Single content wrapper: tabs + grid, aligned at 1100px ── -->
  <div class="menu-body">

  <!-- ── Category tabs ── -->
  <div class="menu-tabs-wrap" id="menuTabsWrap">
    <nav class="menu-tabs" id="menuTabs" role="tablist">
      <div class="tab-indicator" id="tabIndicator"></div>
      <?php foreach ($tables as $tbl => $label): ?>
        <button class="menu-tab <?= $tbl === $current ? 'active' : '' ?>"
                data-cat="<?= e($tbl) ?>" role="tab"
                aria-selected="<?= $tbl === $current ? 'true' : 'false' ?>">
          <?= e($label) ?>
          <span class="tab-badge"><?= $counts[$tbl] ?></span>
        </button>
      <?php endforeach; ?>
    </nav>
  </div>

  <!-- ── Category sections ── -->
  <?php foreach ($tables as $tbl => $label): ?>
  <section class="menu-cat-section <?= $tbl === $current ? 'active' : '' ?>"
           data-cat="<?= e($tbl) ?>" id="cat-<?= e($tbl) ?>">

    <?php if (empty($allItems[$tbl])): ?>
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
        <?php foreach ($allItems[$tbl] as $item):
          $inCart = in_array($tbl . '_' . $item['id'], $cartKeys);
        ?>
        <div class="menu-card"
             data-name="<?= e(mb_strtolower($item['name'])) ?>"
             data-desc="<?= e(mb_strtolower($item['description'] ?? '')) ?>">

          <?php
            $isDefault = ($item['image'] === 'static/images/menu_items/default.jpg' || empty($item['image']));
            $imgSrc    = $isDefault ? '' : '../' . e($item['image']);
          ?>
          <div class="mc-img-zone">
            <?php if ($isDefault): ?>
              <div class="mc-no-img">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#d4c4b0" stroke-width="1.5" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span><?= e($item['name']) ?></span>
              </div>
            <?php else: ?>
              <img src="<?= $imgSrc ?>" alt="<?= e($item['name']) ?>" loading="lazy">
            <?php endif; ?>
            <div class="mc-img-overlay"
                 data-id="<?= (int)$item['id'] ?>"
                 data-cat="<?= e($tbl) ?>"
                 data-cat-label="<?= e($label) ?>"
                 data-name="<?= e($item['name']) ?>"
                 data-desc="<?= e($item['description'] ?? '') ?>"
                 data-price="<?= e(fmtPrice((float)$item['price'])) ?>"
                 data-price-per-kg="<?= $tbl === 'cake_items' ? (float)$item['price'] : '' ?>"
                 data-min-weight="<?= $tbl === 'cake_items' ? (float)($item['min_weight'] ?? 1) : '' ?>"
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
            <div class="mc-footer">
              <span class="mc-price"><?= fmtPrice((float)$item['price']) ?></span>
              <?php if ($tbl === 'cake_items'): ?>
              <button class="mc-add-btn mc-order-btn"
                      data-id="<?= (int)$item['id'] ?>"
                      data-cat="<?= e($tbl) ?>"
                      data-name="<?= e($item['name']) ?>"
                      data-price-per-kg="<?= (float)$item['price'] ?>"
                      data-min-weight="<?= (float)($item['min_weight'] ?? 1) ?>">
                Замовити
              </button>
              <?php else: ?>
              <button class="mc-add-btn <?= $inCart ? 'in-cart' : '' ?>"
                      data-id="<?= (int)$item['id'] ?>"
                      data-cat="<?= e($tbl) ?>"
                      data-name="<?= e($item['name']) ?>">
                <?= $inCart ? 'В кошику ✓' : 'Додати' ?>
              </button>
              <?php endif; ?>
            </div>
          </div>

        </div>
        <?php endforeach; ?>
      </div>

      <div class="menu-search-empty" id="sem-<?= e($tbl) ?>" style="display:none">
        <span class="mse-emoji">😕</span>
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
      <span class="im-price" id="imModalPrice"></span>
      <div class="im-footer">
        <!-- Regular quantity selector -->
        <div class="im-qty" id="imQtyWrap">
          <button class="im-qty-btn" id="imQtyMinus">−</button>
          <span class="im-qty-val" id="imQtyVal">1</span>
          <button class="im-qty-btn" id="imQtyPlus">+</button>
        </div>
        <!-- Cake weight selector -->
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

<!-- ── Toast ── -->
<div class="menu-toast" id="menuToast"></div>

<?php include '../includes/footer.php'; ?>
<script>window._cartKeys = <?= json_encode($cartKeys) ?>;</script>
<script src="../static/js/menu.js"></script>
</body>
</html>
