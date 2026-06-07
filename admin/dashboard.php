<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle  = 'Головна';
$activePage = 'dashboard';

$isStaffHome = !is_super() && !has_perm('orders_view');

$staffStats = [];
$catCounts  = [];

$catMap = [
    'coffee_items'     => 'Кава',
    'fast_food_items'  => 'Фаст-фуд',
    'pizza_items'      => 'Піца',
    'mini_pizza_items' => 'Міні-піца',
    'cold_drink_items' => 'Холодні напої',
    'ice_cream_items'  => 'Морозиво',
    'dessert_items'    => 'Десерти',
    'sushi_items'      => 'Суші',
    'sushi_sets'       => 'Сети суші',
    'salad_items'      => 'Салати',
    'cake_items'       => 'Торти на замовлення',
];

if (has_perm('products')) {
    $totalProducts = 0;
    foreach ($catMap as $tbl => $label) {
        try {
            $r = $conn->query("SELECT COUNT(*) AS c FROM `$tbl`");
            $cnt = $r ? (int)$r->fetch_assoc()['c'] : 0;
        } catch (Exception $e) { $cnt = 0; }
        $totalProducts += $cnt;
        $catCounts[$tbl] = ['label' => $label, 'count' => $cnt];
    }
    $totalSauces = 0;
    try {
        $r = $conn->query("SELECT COUNT(*) AS c FROM sauces");
        if ($r) $totalSauces = (int)$r->fetch_assoc()['c'];
    } catch (Exception $e) {}
    $staffStats['products_total'] = $totalProducts;
    $staffStats['sauces_total']   = $totalSauces;
}

if (has_perm('reviews')) {
    try {
        $r = $conn->query("SELECT COUNT(*) AS c FROM site_reviews");
        if ($r) $staffStats['reviews_total'] = (int)$r->fetch_assoc()['c'];
        $r = $conn->query("SELECT COUNT(*) AS c FROM site_reviews WHERE created_at > NOW() - INTERVAL 7 DAY");
        if ($r) $staffStats['reviews_week'] = (int)$r->fetch_assoc()['c'];
    } catch (Exception $e) {}
}

if (has_perm('content')) {
    try {
        $r = $conn->query("SELECT category, COUNT(*) AS c FROM gallery GROUP BY category");
        $galleryTotal = 0; $galleryCats = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $galleryCats[$row['category']] = (int)$row['c'];
            $galleryTotal += (int)$row['c'];
        }
        $staffStats['gallery_total'] = $galleryTotal;
        $staffStats['gallery_cats']  = $galleryCats;
    } catch (Exception $e) {}

    try {
        $r = $conn->query("SELECT COUNT(*) AS total, COALESCE(SUM(active),0) AS active_cnt FROM hero_slides");
        if ($r) {
            $row = $r->fetch_assoc();
            $staffStats['slides_total']  = (int)$row['total'];
            $staffStats['slides_active'] = (int)$row['active_cnt'];
        }
    } catch (Exception $e) {}

    try {
        $r = $conn->query("SELECT `key`, `value` FROM site_settings WHERE `key` IN ('about_title','about_photo','about_text')");
        if ($r) while ($row = $r->fetch_assoc()) {
            $staffStats['about_' . str_replace('about_', '', $row['key'])] = $row['value'];
        }
    } catch (Exception $e) {}
}

$todayOrders   = 0; $todayRevenue  = 0.0;
$yestOrders    = 0; $yestRevenue   = 0.0;
$todayClients  = 0; $yestClients   = 0;
$weekReviews   = 0; $prevReviews   = 0;

try {
    $r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(total),0) AS s FROM orders WHERE DATE(created_at)=CURDATE()");
    if ($r) { $row = $r->fetch_assoc(); $todayOrders = (int)$row['c']; $todayRevenue = (float)$row['s']; }

    $r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(total),0) AS s FROM orders WHERE DATE(created_at)=CURDATE()-INTERVAL 1 DAY");
    if ($r) { $row = $r->fetch_assoc(); $yestOrders = (int)$row['c']; $yestRevenue = (float)$row['s']; }

    $r = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM orders WHERE DATE(created_at)=CURDATE() AND user_id IS NOT NULL");
    if ($r) $todayClients = (int)$r->fetch_assoc()['c'];
    $r = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM orders WHERE DATE(created_at)=CURDATE()-INTERVAL 1 DAY AND user_id IS NOT NULL");
    if ($r) $yestClients = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) {}

try {
    $r = $conn->query("SELECT COUNT(*) AS c FROM site_reviews WHERE created_at > NOW() - INTERVAL 7 DAY");
    if ($r) $weekReviews = (int)$r->fetch_assoc()['c'];
    $r = $conn->query("SELECT COUNT(*) AS c FROM site_reviews WHERE created_at BETWEEN NOW()-INTERVAL 14 DAY AND NOW()-INTERVAL 7 DAY");
    if ($r) $prevReviews = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) {}

$totalClients = 0;
try {
    $r = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM orders WHERE user_id IS NOT NULL");
    if ($r) $totalClients = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) {}

$avgCheck     = $todayOrders > 0 ? round($todayRevenue / $todayOrders) : 0;
$avgCheckYest = $yestOrders  > 0 ? round($yestRevenue  / $yestOrders)  : 0;

function statCompare(int|float $today, int|float $yesterday, string $suffix = ''): string {
    $diff = $today - $yesterday;
    if ($diff > 0)  return '<span class="stat-compare stat-compare--up">↑ +' . number_format($diff, 0, ',', ' ') . $suffix . ' від вчора</span>';
    if ($diff < 0)  return '<span class="stat-compare stat-compare--down">↓ ' . number_format($diff, 0, ',', ' ') . $suffix . ' від вчора</span>';
    return '<span class="stat-compare stat-compare--eq">= як вчора</span>';
}

$hoursData = array_fill(0, 24, 0);
try {
    $r = $conn->query("SELECT HOUR(created_at) AS h, COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE() GROUP BY h");
    if ($r) while ($row = $r->fetch_assoc()) $hoursData[(int)$row['h']] = (int)$row['c'];
} catch (Exception $e) {}

$weekData = []; $weekLabels = [];
try {
    $r = $conn->query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM orders WHERE created_at >= CURDATE()-INTERVAL 6 DAY GROUP BY d ORDER BY d");
    if ($r) {
        $rawWeek = [];
        while ($row = $r->fetch_assoc()) $rawWeek[$row['d']] = (int)$row['c'];
        for ($i = 6; $i >= 0; $i--) {
            $dateKey = date('Y-m-d', strtotime("-$i days"));
            $weekData[]   = $rawWeek[$dateKey] ?? 0;
            $weekLabels[] = date('d.m', strtotime($dateKey));
        }
    }
} catch (Exception $e) {}

$monthData = []; $monthLabels = [];
try {
    $r = $conn->query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM orders WHERE created_at >= CURDATE()-INTERVAL 29 DAY GROUP BY d ORDER BY d");
    if ($r) {
        $rawMonth = [];
        while ($row = $r->fetch_assoc()) $rawMonth[$row['d']] = (int)$row['c'];
        for ($i = 29; $i >= 0; $i--) {
            $dateKey = date('Y-m-d', strtotime("-$i days"));
            $monthData[]   = $rawMonth[$dateKey] ?? 0;
            $monthLabels[] = date('d.m', strtotime($dateKey));
        }
    }
} catch (Exception $e) {}

$recentOrders = [];
try {
    $r = $conn->query("
        SELECT o.order_id, o.customer_name, o.customer_surname, o.phone,
               o.total, o.status, o.payment_status, o.payment_method, o.created_at,
               COUNT(oi.id) AS items_count
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.order_id
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    if ($r) while ($row = $r->fetch_assoc()) $recentOrders[] = $row;
} catch (Exception $e) {}

$topProducts = [];
try {
$r = $conn->query("
    SELECT oi.product_id, oi.category,
           SUM(oi.quantity)                         AS sold,
           COUNT(DISTINCT oi.order_id)              AS orders_count,
           COALESCE(SUM(oi.quantity * oi.price), 0) AS total_revenue,
           ROUND(AVG(oi.price), 0)                  AS avg_unit_price
    FROM order_items oi
    GROUP BY oi.product_id, oi.category
    ORDER BY sold DESC
    LIMIT 5
");
if ($r) {
    $allowed_cats = ['coffee_items','fast_food_items','pizza_items','mini_pizza_items','cold_drink_items','dessert_items','sushi_items','sushi_sets','salad_items','cake_items','ice_cream_items'];
    while ($row = $r->fetch_assoc()) {
        $cat = $row['category'];
        $pid = (int)$row['product_id'];
        if (!in_array($cat, $allowed_cats)) continue;
        try {
        $s2 = $conn->prepare("SELECT name AS product_name, image FROM `$cat` WHERE id=?");
        $s2->bind_param("i", $pid);
        $s2->execute();
        $prod = $s2->get_result()->fetch_assoc();
        $s2->close();
        } catch (Exception $e) { $prod = null; }
        if ($prod) {
            $rawImg    = $prod['image'] ?? '';
            $isDefault = empty($rawImg) || $rawImg === 'static/images/menu_items/default.jpg';
            $row['name']       = $prod['product_name'];
            $row['image']      = $isDefault ? '' : $rawImg;
            $row['unit_price'] = number_format((float)$row['avg_unit_price'], 0, '.', ' ');
            $row['deleted']    = false;
        } else {
            $row['name']       = 'Видалений товар';
            $row['image']      = '';
            $row['unit_price'] = number_format((float)$row['avg_unit_price'], 0, '.', ' ');
            $row['deleted']    = true;
        }
        $topProducts[] = $row;
    }
}
} catch (Exception $e) {}

function statusInfo(string $s): array {
    return match($s) {
        'new'        => ['Нове',      'badge-new'],
        'processing' => ['В обробці', 'badge-processing'],
        'ready'      => ['Готово',    'badge-ready'],
        'done'       => ['Виконано',  'badge-done'],
        'cancelled'  => ['Скасовано', 'badge-cancelled'],
        default      => ['Нове',      'badge-new'],
    };
}

function paymentBadge(array $o): string {
    $ps = $o['payment_status'] ?? '';
    $pm = $o['payment_method']  ?? '';
    if ($ps === 'paid') return '<span class="pay-tag pay-paid">' . icon('paid-card', 13, '#2e7d32') . ' Оплачено</span>';
    if (str_contains($pm, 'cash') || $pm === 'cash') return '<span class="pay-tag pay-cash">' . icon('paid-cash', 13, '#f57f17') . ' Готівка</span>';
    return '<span class="pay-tag pay-unpaid">' . icon('paid-pending', 13, '#9e9e9e') . ' Не оплачено</span>';
}

include 'includes/layout_top.php';

if ($isStaffHome):
?>

<!-- Welcome banner -->
<div class="sh-welcome">
  <div class="sh-welcome__left">
    <div class="sh-welcome__avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['admin'] ?? 'A', 0, 1))) ?></div>
    <div>
      <div class="sh-welcome__name">Вітаємо, <?= htmlspecialchars($_SESSION['admin_display'] ?? $_SESSION['admin'] ?? 'Адмін') ?>!</div>
      <div class="sh-welcome__role">
        <?php
          $roleLabels = [];
          if (has_perm('products')) $roleLabels[] = 'Товари';
          if (has_perm('reviews'))  $roleLabels[] = 'Відгуки';
          if (has_perm('content'))  $roleLabels[] = 'Контент';
          echo implode(' · ', $roleLabels) ?: 'Адмін';
        ?>
      </div>
    </div>
  </div>
  <div class="sh-welcome__date"><?= date('d.m.Y') ?>, <?= ['Нд','Пн','Вт','Ср','Чт','Пт','Сб'][date('w')] ?></div>
</div>

<!-- Stats row -->
<?php if (!empty($staffStats)): ?>
<div class="sh-stats-row">
  <?php if (isset($staffStats['products_total'])): ?>
  <div class="sh-stat">
    <div class="sh-stat__val"><?= $staffStats['products_total'] ?></div>
    <div class="sh-stat__lbl">Товарів всього</div>
    <div class="sh-stat__sub">у всіх категоріях</div>
  </div>
  <div class="sh-stat">
    <div class="sh-stat__val"><?= count($catCounts) ?></div>
    <div class="sh-stat__lbl">Категорій</div>
    <div class="sh-stat__sub">в меню</div>
  </div>
  <div class="sh-stat">
    <div class="sh-stat__val"><?= $staffStats['sauces_total'] ?></div>
    <div class="sh-stat__lbl">Соусів</div>
    <div class="sh-stat__sub">додаткові інгредієнти</div>
  </div>
  <?php endif; ?>
  <?php if (isset($staffStats['reviews_total'])): ?>
  <div class="sh-stat">
    <div class="sh-stat__val"><?= $staffStats['reviews_total'] ?></div>
    <div class="sh-stat__lbl">Відгуків всього</div>
    <div class="sh-stat__sub"><?= $staffStats['reviews_week'] ?? 0 ?> за тиждень</div>
  </div>
  <?php endif; ?>
  <?php if (isset($staffStats['gallery_total'])): ?>
  <div class="sh-stat">
    <div class="sh-stat__val"><?= $staffStats['gallery_total'] ?></div>
    <div class="sh-stat__lbl">Фото в галереї</div>
    <?php $gc = $staffStats['gallery_cats'] ?? []; ?>
    <div class="sh-stat__sub">
      <?= ($gc['food'] ?? 0) ?> їжа · <?= ($gc['interior'] ?? 0) ?> інтер'єр
    </div>
  </div>
  <?php endif; ?>
  <?php if (isset($staffStats['slides_total'])): ?>
  <div class="sh-stat">
    <div class="sh-stat__val">
      <?= $staffStats['slides_active'] ?><span style="font-size:14px;font-weight:500;color:#aaa"> / <?= $staffStats['slides_total'] ?></span>
    </div>
    <div class="sh-stat__lbl">Слайдів активних</div>
    <div class="sh-stat__sub">з <?= $staffStats['slides_total'] ?> всього</div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Quick nav cards -->
<div class="sh-section-title">Швидкий перехід</div>
<div class="staff-home-grid">

<?php if (has_perm('products') && !empty($catCounts)): ?>
  <!-- Full-width categories card -->
  <div class="shc-cat-card" style="grid-column:1/-1">
    <div class="shc-cat-header">
      <div class="shc-cat-header__left">
        <div class="shc-icon" style="background:#fff8e6;width:40px;height:40px;border-radius:10px">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFC107" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
          </svg>
        </div>
        <div>
          <div class="shc-title">Товари по категоріях</div>
          <div class="shc-sub">Всього <?= $staffStats['products_total'] ?> позицій</div>
        </div>
      </div>
      <a href="manage_items.php" class="shc-cat-btn">Управляти →</a>
    </div>
    <div class="shc-cat-grid">
      <?php foreach ($catCounts as $tbl => $info): ?>
      <a href="manage_items.php?category=<?= $tbl ?>" class="shc-cat-item">
        <span class="shc-cat-name"><?= htmlspecialchars($info['label']) ?></span>
        <span class="shc-cat-count"><?= $info['count'] ?></span>
      </a>
      <?php endforeach; ?>
      <a href="admin_sauces.php" class="shc-cat-item shc-cat-item--sauces">
        <span class="shc-cat-name">🫙 Соуси</span>
        <span class="shc-cat-count shc-cat-count--sauces"><?= $staffStats['sauces_total'] ?? 0 ?></span>
      </a>
    </div>
  </div>
  <!-- Sauces card -->
  <a href="admin_sauces.php" class="staff-home-card">
    <div class="shc-icon" style="background:#fef3e2">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e6851a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
        <path d="M8 12s1.5 2 4 2 4-2 4-2"/>
        <line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>
      </svg>
    </div>
    <div>
      <div class="shc-title">Соуси</div>
      <div class="shc-sub">Додаткові інгредієнти до страв</div>
    </div>
    <div class="shc-badge"><?= $staffStats['sauces_total'] ?> шт</div>
  </a>
<?php endif; ?>

<?php if (has_perm('reviews')): ?>
  <a href="admin_reviews.php" class="staff-home-card">
    <div class="shc-icon" style="background:#fce4ec">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#E91E63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
      </svg>
    </div>
    <div>
      <div class="shc-title">Відгуки</div>
      <div class="shc-sub">Модерація відгуків клієнтів</div>
    </div>
    <?php if (isset($staffStats['reviews_week']) && $staffStats['reviews_week'] > 0): ?>
    <div class="shc-badge shc-badge--new"><?= $staffStats['reviews_week'] ?> нових</div>
    <?php endif; ?>
  </a>
<?php endif; ?>

<?php if (has_perm('content')): ?>
  <!-- Full-width content breakdown card -->
  <div class="shc-cat-card" style="grid-column:1/-1">
    <div class="shc-cat-header">
      <div class="shc-cat-header__left">
        <div class="shc-icon" style="background:#e3f2fd;width:40px;height:40px;border-radius:10px">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
          </svg>
        </div>
        <div>
          <div class="shc-title">Контент сайту</div>
          <div class="shc-sub">Галерея, слайдер та сторінка «Про нас»</div>
        </div>
      </div>
    </div>

    <div class="shc-content-grid">

      <!-- Gallery block -->
      <a href="admin_gallery.php" class="shc-content-block">
        <div class="shc-cb-header">
          <div class="shc-icon" style="background:#e3f2fd;width:36px;height:36px;border-radius:9px;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
            </svg>
          </div>
          <div>
            <div class="shc-cb-title">Галерея</div>
            <div class="shc-cb-sub">Фото сайту</div>
          </div>
          <div class="shc-cb-count"><?= $staffStats['gallery_total'] ?? 0 ?></div>
        </div>
        <?php $gc = $staffStats['gallery_cats'] ?? []; $gt = max(1, $staffStats['gallery_total'] ?? 1); ?>
        <div class="shc-cb-cats">
          <div class="shc-cb-cat-row">
            <span class="shc-cb-cat-lbl">Їжа</span>
            <div class="shc-cb-bar"><div class="shc-cb-bar-fill" style="width:<?= round(($gc['food'] ?? 0) / $gt * 100) ?>%;background:#2196F3"></div></div>
            <span class="shc-cb-cat-val"><?= $gc['food'] ?? 0 ?></span>
          </div>
          <div class="shc-cb-cat-row">
            <span class="shc-cb-cat-lbl">Інтер'єр</span>
            <div class="shc-cb-bar"><div class="shc-cb-bar-fill" style="width:<?= round(($gc['interior'] ?? 0) / $gt * 100) ?>%;background:#90CAF9"></div></div>
            <span class="shc-cb-cat-val"><?= $gc['interior'] ?? 0 ?></span>
          </div>
        </div>
      </a>

      <!-- Slider block -->
      <a href="hero_slides.php" class="shc-content-block">
        <div class="shc-cb-header">
          <div class="shc-icon" style="background:#e8f5e9;width:36px;height:36px;border-radius:9px;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="7" width="20" height="10" rx="2"/>
              <path d="M17 7V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v2"/>
              <polyline points="9 12 12 15 15 12"/>
            </svg>
          </div>
          <div>
            <div class="shc-cb-title">Хіро слайдер</div>
            <div class="shc-cb-sub">Головний банер</div>
          </div>
          <div class="shc-cb-count"><?= $staffStats['slides_total'] ?? 0 ?></div>
        </div>
        <?php
          $sTotal  = max(1, $staffStats['slides_total']  ?? 0);
          $sActive = $staffStats['slides_active'] ?? 0;
          $sPct    = $sTotal > 0 ? round($sActive / $sTotal * 100) : 0;
        ?>
        <div class="shc-cb-cats">
          <div class="shc-cb-cat-row">
            <span class="shc-cb-cat-lbl">Активних</span>
            <div class="shc-cb-bar"><div class="shc-cb-bar-fill" style="width:<?= $sPct ?>%;background:#4CAF50"></div></div>
            <span class="shc-cb-cat-val"><?= $sActive ?> / <?= $staffStats['slides_total'] ?? 0 ?></span>
          </div>
          <div style="margin-top:10px">
            <?php for ($i = 0; $i < ($staffStats['slides_total'] ?? 0); $i++): ?>
              <span class="shc-slide-dot <?= $i < $sActive ? 'shc-slide-dot--on' : '' ?>"></span>
            <?php endfor; ?>
            <?php if (($staffStats['slides_total'] ?? 0) === 0): ?>
              <span style="font-size:12px;color:#bbb">Слайди відсутні</span>
            <?php endif; ?>
          </div>
        </div>
      </a>

      <!-- About block -->
      <a href="about_section.php" class="shc-content-block">
        <div class="shc-cb-header">
          <div class="shc-icon" style="background:#f3e5f5;width:36px;height:36px;border-radius:9px;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9C27B0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </div>
          <div>
            <div class="shc-cb-title">Про нас</div>
            <div class="shc-cb-sub">Секція на сайті</div>
          </div>
          <div class="shc-cb-status <?= !empty($staffStats['about_title']) ? 'shc-cb-status--ok' : 'shc-cb-status--warn' ?>">
            <?= !empty($staffStats['about_title']) ? '✓ Налаштовано' : '! Не заповнено' ?>
          </div>
        </div>
        <?php if (!empty($staffStats['about_title'])): ?>
        <div class="shc-cb-about-preview">
          <div class="shc-cb-about-title"><?= htmlspecialchars($staffStats['about_title']) ?></div>
          <?php if (!empty($staffStats['about_text'])): ?>
          <div class="shc-cb-about-text"><?= htmlspecialchars(mb_substr($staffStats['about_text'], 0, 90)) ?>…</div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </a>

    </div><!-- /.shc-content-grid -->
  </div>
<?php endif; ?>

</div>

<style>
/* Welcome banner */
.sh-welcome {
  display: flex; align-items: center; justify-content: space-between;
  background: #fff; border-radius: 16px; padding: 22px 26px;
  border: 1.5px solid #ede5dd; margin-bottom: 18px;
}
.sh-welcome__left { display: flex; align-items: center; gap: 16px; }
.sh-welcome__avatar {
  width: 52px; height: 52px; border-radius: 50%;
  background: linear-gradient(135deg, #8B4513, #c26d2a);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.sh-welcome__name { font-size: 20px; font-weight: 800; color: #2c2c2a; }
.sh-welcome__role { font-size: 13px; color: #999; margin-top: 2px; }
.sh-welcome__date { font-size: 13px; color: #bbb; font-weight: 500; }

/* Stats row */
.sh-stats-row {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 14px; margin-bottom: 24px;
}
.sh-stat {
  background: #fff; border-radius: 14px; padding: 18px 20px;
  border: 1.5px solid #ede5dd;
}
.sh-stat__val { font-size: 28px; font-weight: 800; color: #3d1f07; line-height: 1; }
.sh-stat__lbl { font-size: 12px; font-weight: 600; color: #555; margin-top: 6px; }
.sh-stat__sub { font-size: 11px; color: #bbb; margin-top: 2px; }

/* Section title */
.sh-section-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: #bbb; margin-bottom: 12px;
}

/* Nav cards */
.staff-home-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 14px;
}
.staff-home-card {
  background: #fff; border-radius: 16px; padding: 20px;
  display: flex; flex-direction: column; gap: 10px;
  border: 1.5px solid #ede5dd;
  text-decoration: none; color: inherit;
  transition: box-shadow 0.18s, transform 0.18s, border-color 0.18s;
  position: relative;
}
.staff-home-card:hover {
  box-shadow: 0 8px 28px rgba(0,0,0,0.09);
  transform: translateY(-2px);
  border-color: #d4b896;
}

</style>

<?php else: ?>

<div class="stats-grid <?= (is_super() || has_perm('reviews')) ? 'stats-grid--five' : 'stats-grid--four' ?>">

  <!-- Замовлення сьогодні -->
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#fff8e6">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#FFC107" stroke-width="2" stroke-linecap="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Замовлення сьогодні</div>
      <div class="stat-value"><?= $todayOrders ?></div>
      <?= statCompare($todayOrders, $yestOrders) ?>
    </div>
  </div>

  <!-- Виручка сьогодні -->
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#e8f5e9">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Виручка сьогодні</div>
      <div class="stat-value" style="font-size:22px"><?= number_format($todayRevenue, 0, ',', ' ') ?> ₴</div>
      <?= statCompare($todayRevenue, $yestRevenue, ' ₴') ?>
    </div>
  </div>

  <!-- Середній чек -->
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#f3e5f5">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#9C27B0" stroke-width="2" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Середній чек</div>
      <div class="stat-value" style="font-size:22px"><?= $avgCheck > 0 ? number_format($avgCheck, 0, ',', ' ') . ' ₴' : '—' ?></div>
      <?= $avgCheckYest > 0 ? statCompare($avgCheck, $avgCheckYest, ' ₴') : '<span class="stat-compare stat-compare--eq">за сьогодні</span>' ?>
    </div>
  </div>

  <!-- Клієнти -->
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#e3f2fd">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Клієнтів усього</div>
      <div class="stat-value"><?= $totalClients ?></div>
      <?= statCompare($todayClients, $yestClients, ' сьогодні') ?>
    </div>
  </div>

  <!-- Відгуки (тільки якщо є права) -->
  <?php if (is_super() || has_perm('reviews')): ?>
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#fce4ec">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#E91E63" stroke-width="2" stroke-linecap="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Відгуки за тиждень</div>
      <div class="stat-value"><?= $weekReviews ?></div>
      <?= statCompare($weekReviews, $prevReviews, ' vs тиждень тому') ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<div class="dashboard-grid">

  <!-- Left: chart + recent orders -->
  <div style="display:flex;flex-direction:column;gap:18px">

    <!-- Orders chart -->
    <div class="dash-section">
      <div class="section-head">
        <h2 class="section-title">Графік замовлень</h2>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <div class="chart-tabs" id="chartTabs">
            <button class="chart-tab active" data-mode="today">Сьогодні</button>
            <button class="chart-tab" data-mode="week">Тиждень</button>
            <button class="chart-tab" data-mode="month">Місяць</button>
            <button class="chart-tab" data-mode="day">День</button>
          </div>
          <span id="chartDayPickerWrap" style="display:none">
            <input type="text" id="chartDayPicker"
                   value="<?= date('Y-m-d') ?>"
                   style="border:1px solid #e0d6cd;border-radius:8px;padding:5px 10px;font-size:12px;color:#3a2a1a;background:#fff;cursor:pointer;font-family:inherit;outline:none;transition:border-color .2s;width:130px" readonly>
          </span>
        </div>
      </div>
      <canvas id="ordersChart" style="display:block;transition:opacity 0.13s ease"></canvas>
    </div>

    <!-- Recent orders -->
    <div class="dash-section">
      <div class="section-head">
        <h2 class="section-title">Останні замовлення</h2>
        <a href="orders.php" class="btn-ghost btn-sm">Всі →</a>
      </div>
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th><th>Клієнт</th><th>Телефон</th>
              <th>Оплата</th><th>Сума</th><th>Статус</th><th>Дата</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentOrders as $o):
              [$label, $badgeClass] = statusInfo($o['status']);
              $fullName = trim(($o['customer_name'] ?? '') . ' ' . ($o['customer_surname'] ?? '')) ?: '—';
            ?>
            <tr>
              <td><a href="view_order.php?id=<?= $o['order_id'] ?>" style="font-weight:700;color:#8B4513;text-decoration:none">#<?= $o['order_id'] ?></a></td>
              <td><?= htmlspecialchars($fullName) ?></td>
              <td style="color:#666;font-size:12px"><?= htmlspecialchars($o['phone'] ?? '—') ?></td>
              <td><?= paymentBadge($o) ?></td>
              <td><strong><?= number_format((float)$o['total'], 0, ',', ' ') ?> ₴</strong></td>
              <td><span class="order-status-badge <?= $badgeClass ?>"><?= $label ?></span></td>
              <td style="color:#999;font-size:12px"><?= date('d.m H:i', strtotime($o['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentOrders)): ?>
              <tr><td colspan="7" style="text-align:center;color:#bbb;padding:32px">Замовлень ще немає</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Right: top products -->
  <div class="dash-section dash-section--narrow">
    <div class="section-head" style="align-items:flex-start;flex-wrap:wrap;gap:10px">
      <h2 class="section-title">Топ товарів</h2>
      <div class="top-filter-tabs" id="topFilterTabs">
        <button class="top-ftab active" data-period="all">Весь час</button>
        <button class="top-ftab" data-period="month">Місяць</button>
        <button class="top-ftab" data-period="week">Тиждень</button>
        <button class="top-ftab" data-period="custom">Власний</button>
      </div>
    </div>
    <div id="customRangeRow" style="display:none;padding:10px 0 4px;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="text" id="topDateFrom" placeholder="Від" autocomplete="off" readonly style="border:1px solid #e0d6cd;border-radius:8px;padding:6px 10px;font-size:13px;color:#3a2a1a;background:#fff;cursor:pointer;width:120px">
      <span style="color:#aaa;font-size:13px">—</span>
      <input type="text" id="topDateTo" placeholder="До" autocomplete="off" readonly style="border:1px solid #e0d6cd;border-radius:8px;padding:6px 10px;font-size:13px;color:#3a2a1a;background:#fff;cursor:pointer;width:120px">
      <button id="applyCustomRange" style="padding:6px 14px;background:#6b3a1f;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Застосувати</button>
    </div>

    <ul class="top-list" id="topProductsList">
    <?php if (empty($topProducts)): ?>
      <li style="color:#bbb;font-size:13px;padding:16px 0;list-style:none">Даних поки немає</li>
    <?php else: ?>
      <?php
        $rankColors = ['#FFC107', '#aaaaaa', '#cd7f32'];
        foreach ($topProducts as $i => $p):
          $rankStyle = 'color:' . ($rankColors[$i] ?? '#ccc');
          $firstLtr  = mb_strtoupper(mb_substr($p['name'], 0, 1, 'UTF-8'), 'UTF-8');
      ?>
      <li class="top-item" <?= $p['deleted'] ? 'style="opacity:.55"' : '' ?>>
        <span class="top-rank" style="<?= $rankStyle ?>"><?= $i + 1 ?></span>
        <?php if (!empty($p['image'])): ?>
          <img src="../<?= htmlspecialchars($p['image']) ?>" class="top-thumb" alt="" style="object-fit:cover;border-radius:8px">
        <?php else: ?>
          <div class="top-thumb top-thumb--letter"><?= $firstLtr ?></div>
        <?php endif; ?>
        <div style="flex:1;min-width:0">
          <div class="top-name" <?= $p['deleted'] ? 'style="color:#aaa;font-style:italic"' : '' ?>><?= htmlspecialchars($p['name']) ?></div>
          <div style="font-size:11px;color:#aaa;margin-top:1px"><?= (int)$p['orders_count'] ?> замовлень · <?= htmlspecialchars($p['unit_price']) ?> грн/шт</div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div class="top-sold"><?= (int)$p['sold'] ?> шт</div>
          <div style="font-size:11px;color:#aaa"><?= number_format((float)$p['total_revenue'], 0, '.', ' ') ?> грн</div>
        </div>
      </li>
      <?php endforeach; ?>
    <?php endif; ?>
    </ul>
  </div>

</div>

<!-- ══ Products & content overview (shown when permissions exist) ══ -->
<?php if (has_perm('products') && !empty($catCounts)): ?>
<div style="margin-top:18px">
  <div class="shc-cat-card">
    <div class="shc-cat-header">
      <div class="shc-cat-header__left">
        <div class="shc-icon" style="background:#fff8e6;width:40px;height:40px;border-radius:10px">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFC107" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
          </svg>
        </div>
        <div>
          <div class="shc-title">Товари по категоріях</div>
          <div class="shc-sub">Всього <?= $staffStats['products_total'] ?> позицій у меню</div>
        </div>
      </div>
      <a href="manage_items.php" class="shc-cat-btn">Управляти →</a>
    </div>
    <div class="shc-cat-grid">
      <?php foreach ($catCounts as $tbl => $info): ?>
      <a href="manage_items.php?category=<?= $tbl ?>" class="shc-cat-item">
        <span class="shc-cat-name"><?= htmlspecialchars($info['label']) ?></span>
        <span class="shc-cat-count"><?= $info['count'] ?></span>
      </a>
      <?php endforeach; ?>
      <a href="admin_sauces.php" class="shc-cat-item shc-cat-item--sauces">
        <span class="shc-cat-name">🫙 Соуси</span>
        <span class="shc-cat-count shc-cat-count--sauces"><?= $staffStats['sauces_total'] ?? 0 ?></span>
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (has_perm('content')): ?>
<div style="margin-top:14px">
  <div class="shc-cat-card">
    <div class="shc-cat-header">
      <div class="shc-cat-header__left">
        <div class="shc-icon" style="background:#e3f2fd;width:40px;height:40px;border-radius:10px">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
          </svg>
        </div>
        <div>
          <div class="shc-title">Контент сайту</div>
          <div class="shc-sub">Галерея, слайдер та сторінка «Про нас»</div>
        </div>
      </div>
    </div>
    <div class="shc-content-grid">

      <a href="admin_gallery.php" class="shc-content-block">
        <div class="shc-cb-header">
          <div class="shc-icon" style="background:#e3f2fd;width:36px;height:36px;border-radius:9px;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
            </svg>
          </div>
          <div>
            <div class="shc-cb-title">Галерея</div>
            <div class="shc-cb-sub">Фото сайту</div>
          </div>
          <div class="shc-cb-count"><?= $staffStats['gallery_total'] ?? 0 ?></div>
        </div>
        <?php $gc = $staffStats['gallery_cats'] ?? []; $gt = max(1, $staffStats['gallery_total'] ?? 1); ?>
        <div class="shc-cb-cats">
          <div class="shc-cb-cat-row">
            <span class="shc-cb-cat-lbl">Їжа</span>
            <div class="shc-cb-bar"><div class="shc-cb-bar-fill" style="width:<?= round(($gc['food'] ?? 0) / $gt * 100) ?>%;background:#2196F3"></div></div>
            <span class="shc-cb-cat-val"><?= $gc['food'] ?? 0 ?></span>
          </div>
          <div class="shc-cb-cat-row">
            <span class="shc-cb-cat-lbl">Інтер'єр</span>
            <div class="shc-cb-bar"><div class="shc-cb-bar-fill" style="width:<?= round(($gc['interior'] ?? 0) / $gt * 100) ?>%;background:#90CAF9"></div></div>
            <span class="shc-cb-cat-val"><?= $gc['interior'] ?? 0 ?></span>
          </div>
        </div>
      </a>

      <a href="hero_slides.php" class="shc-content-block">
        <div class="shc-cb-header">
          <div class="shc-icon" style="background:#e8f5e9;width:36px;height:36px;border-radius:9px;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="7" width="20" height="10" rx="2"/>
              <path d="M17 7V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v2"/>
              <polyline points="9 12 12 15 15 12"/>
            </svg>
          </div>
          <div>
            <div class="shc-cb-title">Хіро слайдер</div>
            <div class="shc-cb-sub">Головний банер</div>
          </div>
          <div class="shc-cb-count"><?= $staffStats['slides_total'] ?? 0 ?></div>
        </div>
        <?php
          $sTotal  = max(1, $staffStats['slides_total']  ?? 0);
          $sActive = $staffStats['slides_active'] ?? 0;
          $sPct    = $sTotal > 0 ? round($sActive / $sTotal * 100) : 0;
        ?>
        <div class="shc-cb-cats">
          <div class="shc-cb-cat-row">
            <span class="shc-cb-cat-lbl">Активних</span>
            <div class="shc-cb-bar"><div class="shc-cb-bar-fill" style="width:<?= $sPct ?>%;background:#4CAF50"></div></div>
            <span class="shc-cb-cat-val"><?= $sActive ?> / <?= $staffStats['slides_total'] ?? 0 ?></span>
          </div>
          <div style="margin-top:10px">
            <?php for ($i = 0; $i < ($staffStats['slides_total'] ?? 0); $i++): ?>
              <span class="shc-slide-dot <?= $i < $sActive ? 'shc-slide-dot--on' : '' ?>"></span>
            <?php endfor; ?>
            <?php if (($staffStats['slides_total'] ?? 0) === 0): ?>
              <span style="font-size:12px;color:#bbb">Слайди відсутні</span>
            <?php endif; ?>
          </div>
        </div>
      </a>

      <a href="about_section.php" class="shc-content-block">
        <div class="shc-cb-header">
          <div class="shc-icon" style="background:#f3e5f5;width:36px;height:36px;border-radius:9px;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9C27B0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </div>
          <div>
            <div class="shc-cb-title">Про нас</div>
            <div class="shc-cb-sub">Секція на сайті</div>
          </div>
          <div class="shc-cb-status <?= !empty($staffStats['about_title']) ? 'shc-cb-status--ok' : 'shc-cb-status--warn' ?>">
            <?= !empty($staffStats['about_title']) ? '✓ Налаштовано' : '! Не заповнено' ?>
          </div>
        </div>
        <?php if (!empty($staffStats['about_title'])): ?>
        <div class="shc-cb-about-preview">
          <div class="shc-cb-about-title"><?= htmlspecialchars($staffStats['about_title']) ?></div>
          <?php if (!empty($staffStats['about_text'])): ?>
          <div class="shc-cb-about-text"><?= htmlspecialchars(mb_substr($staffStats['about_text'], 0, 90)) ?>…</div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </a>

    </div>
  </div>
</div>
<?php endif; ?>

<script>
(function() {

  /* ══ Canvas Chart ══ */
  var canvas         = document.getElementById('ordersChart');
  var chartTabs      = document.querySelectorAll('.chart-tab');
  var dayPicker      = document.getElementById('chartDayPicker');
  var dayPickerWrap  = document.getElementById('chartDayPickerWrap');

  var hoursData   = <?= json_encode(array_values($hoursData)) ?>;
  var weekData    = <?= json_encode($weekData) ?>;
  var weekLabels  = <?= json_encode($weekLabels) ?>;
  var monthData   = <?= json_encode($monthData) ?>;
  var monthLabels = <?= json_encode($monthLabels) ?>;

  var animFrame = null;
  var curLabels = null;
  var curValues = null;

  function todayLabels() {
    var l = [];
    for (var i = 0; i < 24; i++) l.push(i < 10 ? '0' + i : String(i));
    return l;
  }

  function measureW() {
    canvas.style.width = '1px';
    var par = canvas.parentNode;
    var cs  = window.getComputedStyle(par);
    return Math.floor(par.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight));
  }

  function renderBars(ctx, W, H, labels, values, fixedMax) {
    var pad = { top:24, right:16, bottom:38, left:40 };
    var cW  = W - pad.left - pad.right;
    var cH  = H - pad.top  - pad.bottom;
    var max = fixedMax !== undefined ? fixedMax : (Math.max.apply(null, values) || 1);

    ctx.clearRect(0, 0, W, H);

    /* grid lines + Y labels */
    ctx.strokeStyle = '#f0e8df'; ctx.lineWidth = 1;
    for (var i = 0; i <= 4; i++) {
      var gy = pad.top + cH - cH/4*i;
      ctx.beginPath(); ctx.moveTo(pad.left, gy); ctx.lineTo(pad.left+cW, gy); ctx.stroke();
      ctx.fillStyle = '#bbb'; ctx.font = '10px sans-serif'; ctx.textAlign = 'right';
      ctx.fillText(Math.round(max/4*i), pad.left-6, gy+4);
    }

    /* bars */
    var n = labels.length, gap = labels.length > 14 ? 2 : 4;
    var bW = Math.max(3, (cW - gap*(n-1)) / n);
    for (var j = 0; j < n; j++) {
      var bh   = cH * (values[j] / max);
      var bx   = pad.left + j*(bW+gap);
      var by   = pad.top  + cH - bh;

      if (bh > 0) {
        var grad = ctx.createLinearGradient(0, by, 0, pad.top+cH);
        grad.addColorStop(0,   '#E8A838');
        grad.addColorStop(0.6, '#D4A853');
        grad.addColorStop(1,   'rgba(212,168,83,0.18)');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.roundRect(bx, by, bW, Math.max(bh,1), [3,3,0,0]);
        ctx.fill();

        /* value label above bar (only when bar is tall enough) */
        if (bh > 16 && values[j] > 0) {
          ctx.fillStyle = 'rgba(139,69,19,0.7)';
          ctx.font = 'bold 9px sans-serif';
          ctx.textAlign = 'center';
          ctx.fillText(Math.round(values[j]), bx+bW/2, by-3);
        }
      }

      /* X label */
      ctx.fillStyle = '#aaa'; ctx.font = '9px sans-serif'; ctx.textAlign = 'center';
      var lbl = String(labels[j]);
      if (n > 12 && j % 3 !== 0) lbl = '';
      ctx.fillText(lbl, bx+bW/2, H-pad.bottom+13);
    }
  }

  function drawChart(labels, values) {
    var dpr = window.devicePixelRatio || 1;
    var W   = measureW();
    var H   = 220;
    canvas.width        = W * dpr;
    canvas.height       = H * dpr;
    canvas.style.width  = W + 'px';
    canvas.style.height = H + 'px';
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    renderBars(ctx, W, H, labels, values);
  }

  function animateChart(labels, values) {
    if (animFrame) { cancelAnimationFrame(animFrame); animFrame = null; }
    curLabels = labels; curValues = values;
    var dpr = window.devicePixelRatio || 1;
    var W   = measureW();
    var H   = 220;
    canvas.width        = W * dpr;
    canvas.height       = H * dpr;
    canvas.style.width  = W + 'px';
    canvas.style.height = H + 'px';
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var max = Math.max.apply(null, values) || 1;
    var t0  = null, dur = 450;
    function ease(t) { return 1 - Math.pow(1-t, 3); }
    function step(ts) {
      if (!t0) t0 = ts;
      var p = Math.min(1, (ts-t0)/dur);
      var e = ease(p);
      /* Pass fixed max so bars actually grow from 0, not stay at proportion */
      renderBars(ctx, W, H, labels, values.map(function(v){ return v*e; }), max);
      if (p < 1) animFrame = requestAnimationFrame(step);
      else { animFrame = null; renderBars(ctx, W, H, labels, values, max); }
    }
    animFrame = requestAnimationFrame(step);
  }

  function switchChart(labels, values) {
    if (animFrame) { cancelAnimationFrame(animFrame); animFrame = null; }
    curLabels = labels; curValues = values;
    canvas.style.opacity = '0.05';
    setTimeout(function() {
      canvas.style.opacity = '1';
      animateChart(labels, values);
    }, 130);
  }

  /* Initial draw */
  curLabels = todayLabels(); curValues = hoursData;
  drawChart(curLabels, curValues);

  /* Tab clicks */
  chartTabs.forEach(function(tab) {
    tab.addEventListener('click', function() {
      chartTabs.forEach(function(t){ t.classList.remove('active'); });
      this.classList.add('active');
      var mode = this.dataset.mode;
      if (mode === 'day') {
        dayPickerWrap.style.display = '';
        if (dayPicker.value) loadDayChart(dayPicker.value);
      } else {
        dayPickerWrap.style.display = 'none';
        if      (mode === 'week')  switchChart(weekLabels,    weekData);
        else if (mode === 'month') switchChart(monthLabels,   monthData);
        else                       switchChart(todayLabels(),  hoursData);
      }
    });
  });

  /* Day picker */
  function loadDayChart(date) {
    fetch('get_chart_data.php?date=' + encodeURIComponent(date))
      .then(function(r){ return r.json(); })
      .then(function(d){ if (d.success) switchChart(todayLabels(), d.data); })
      .catch(function(){});
  }

  /* Flatpickr: chart day picker */
  flatpickr(dayPicker, {
    locale: 'uk',
    dateFormat: 'Y-m-d',
    disableMobile: true,
    maxDate: 'today',
    defaultDate: new Date(),
    onReady: window.fpBuildYearSelect,
    onChange: function(sel, dateStr) { if (dateStr) loadDayChart(dateStr); }
  });

  /* Resize — no animation, just redraw */
  window.addEventListener('resize', function() {
    if (curLabels && curValues) drawChart(curLabels, curValues);
  });

  /* Redraw after layout settles (fixes wrong width on mobile first paint) */
  requestAnimationFrame(function() {
    requestAnimationFrame(function() {
      if (curLabels && curValues) drawChart(curLabels, curValues);
    });
  });

  /* ══ Top products filter ══ */
  const tabs       = document.querySelectorAll('.top-ftab');
  const list       = document.getElementById('topProductsList');
  const customRow  = document.getElementById('customRangeRow');
  const dateFrom   = document.getElementById('topDateFrom');
  const dateTo     = document.getElementById('topDateTo');
  const applyBtn   = document.getElementById('applyCustomRange');
  if (!tabs.length || !list) return;

  /* Flatpickr: top products date range */
  flatpickr(dateFrom, { locale:'uk', dateFormat:'Y-m-d', disableMobile:true, maxDate:'today',
    onReady: window.fpBuildYearSelect,
    onChange: function(sel, str) { if (dateTo._flatpickr) dateTo._flatpickr.set('minDate', str||null); }
  });
  flatpickr(dateTo, { locale:'uk', dateFormat:'Y-m-d', disableMobile:true, maxDate:'today',
    onReady: window.fpBuildYearSelect,
    onChange: function(sel, str) { if (dateFrom._flatpickr) dateFrom._flatpickr.set('maxDate', str||null); }
  });

  async function loadPeriod(period, from, to) {
    list.style.opacity = '0.45';
    list.style.pointerEvents = 'none';
    try {
      let url = 'get_top_products.php?period=' + encodeURIComponent(period);
      if (period === 'custom' && from && to)
        url += '&date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to);
      const r    = await fetch(url);
      const data = await r.json();
      if (data.success) renderTopProducts(list, data.products);
    } catch (_) {}
    list.style.opacity = '1';
    list.style.pointerEvents = 'auto';
  }

  tabs.forEach(btn => {
    btn.addEventListener('click', function() {
      tabs.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      if (this.dataset.period === 'custom') { customRow.style.display = 'flex'; }
      else { customRow.style.display = 'none'; loadPeriod(this.dataset.period); }
    });
  });

  applyBtn && applyBtn.addEventListener('click', function() {
    if (!dateFrom.value || !dateTo.value) return;
    loadPeriod('custom', dateFrom.value, dateTo.value);
  });

  function renderTopProducts(container, products) {
    if (!products.length) {
      container.innerHTML = '<li style="color:#bbb;font-size:13px;padding:16px 0;list-style:none">Даних за цей період немає</li>';
      return;
    }
    const rankColors = ['#FFC107', '#aaaaaa', '#cd7f32'];
    container.innerHTML = products.map((p, i) => {
      const color     = rankColors[i] || '#ccc';
      const firstLtr  = (p.name || '?').charAt(0).toUpperCase();
      const img       = p.image
        ? `<img src="../${p.image}" class="top-thumb" alt="" style="object-fit:cover;border-radius:8px">`
        : `<div class="top-thumb top-thumb--letter">${firstLtr}</div>`;
      const nameStyle = p.deleted ? 'color:#aaa;font-style:italic' : '';
      const liStyle   = `animation:rowFadeIn .25s ease ${i * .05}s both${p.deleted ? ';opacity:.55' : ''}`;
      return `<li class="top-item" style="${liStyle}">
        <span class="top-rank" style="color:${color}">${i + 1}</span>
        ${img}
        <div style="flex:1;min-width:0">
          <div class="top-name" style="${nameStyle}">${p.name}</div>
          <div style="font-size:11px;color:#aaa;margin-top:1px">${p.orders_count} замовлень · ${p.unit_price} грн/шт</div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div class="top-sold">${p.total_qty} шт</div>
          <div style="font-size:11px;color:#aaa">${p.total_revenue_fmt} грн</div>
        </div>
      </li>`;
    }).join('');
  }

})();
</script>

<?php endif; /* end staff/full dashboard split */

include 'includes/layout_bottom.php'; ?>
