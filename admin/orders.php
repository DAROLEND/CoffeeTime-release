<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('orders_view');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/icons.php';

$pageTitle  = 'Замовлення';
$activePage = 'orders';

try {
    $conn->query("UPDATE orders SET status='new' WHERE status NOT IN ('new','processing','ready','done','cancelled')");
    $conn->query("ALTER TABLE orders MODIFY COLUMN status ENUM('new','processing','ready','done','cancelled') NOT NULL DEFAULT 'new'");
} catch (Exception $e) {}

$statusLabels = ['new' => 'Нове', 'processing' => 'В обробці', 'ready' => 'Готово', 'done' => 'Виконано', 'cancelled' => 'Скасовано'];
$nextLabels   = ['processing' => '→ В обробці', 'ready' => '→ Готово', 'done' => '✓ Виконано', 'cancelled' => '✕ Скасувати'];
$transitions  = [
    'new'        => ['processing', 'done', 'cancelled'],
    'processing' => ['ready', 'done', 'cancelled'],
    'ready'      => ['done'],
    'done'       => [],
    'cancelled'  => [],
];

function renderStatusCell(int $orderId, string $status, array $trans, array $nxtLbls, array $stLbls): string {
    if (!array_key_exists($status, $trans)) $status = 'new';
    $next  = $trans[$status];
    $label = $stLbls[$status];
    $out   = '<div class="status-cell"><span class="order-status-badge badge-' . $status . '">' . htmlspecialchars($label) . '</span>';
    if (!empty($next)) {
        foreach ($next as $ns) {
            $lbl = $nxtLbls[$ns] ?? ('→ ' . ($stLbls[$ns] ?? $ns));
            $out .= '<button class="status-pill-btn pill-' . $ns . '" data-order="' . $orderId . '" data-status="' . $ns . '">' . $lbl . '</button>';
        }
    }
    return $out . '</div>';
}

$filterStatus  = trim($_GET['status']  ?? '');
$filterPayment = trim($_GET['payment'] ?? '');
$filterMethod  = trim($_GET['method']  ?? '');
$filterType    = trim($_GET['type']    ?? '');
$filterSearch  = trim($_GET['search']  ?? '');
$dateFrom      = trim($_GET['date_from'] ?? '');
$dateTo        = trim($_GET['date_to']   ?? '');
$timeFrom      = isset($_GET['time_from']) && $_GET['time_from'] !== '' ? max(0, min(23, (int)$_GET['time_from'])) : '';
$timeTo        = isset($_GET['time_to'])   && $_GET['time_to']   !== '' ? max(0, min(23, (int)$_GET['time_to']))   : '';
$page          = max(1, (int)($_GET['p'] ?? 1));
$perPage       = 20;

$_today      = date('Y-m-d');
$_yesterday  = date('Y-m-d', strtotime('-1 day'));
$_weekStart  = date('Y-m-d', strtotime('-6 days'));
$_monthStart = date('Y-m-d', strtotime('-29 days'));
$activeQuickRange = ($dateFrom === '' && $dateTo === '') ? 'all' : '';
if ($dateFrom !== '' && $dateTo !== '') {
    if      ($dateFrom === $_today      && $dateTo === $_today)      $activeQuickRange = 'today';
    elseif  ($dateFrom === $_yesterday  && $dateTo === $_yesterday)  $activeQuickRange = 'yesterday';
    elseif  ($dateFrom === $_weekStart  && $dateTo === $_today)      $activeQuickRange = 'week';
    elseif  ($dateFrom === $_monthStart && $dateTo === $_today)      $activeQuickRange = 'month';
}

$where  = '1=1';
$params = [];
$types  = '';

if ($filterStatus && array_key_exists($filterStatus, $statusLabels)) {
    $where .= ' AND o.status=?'; $params[] = $filterStatus; $types .= 's';
}
if ($filterPayment === 'paid') {
    $where .= " AND o.payment_status='paid'";
} elseif ($filterPayment === 'cash') {
    $where .= " AND o.payment_method LIKE '%cash%'";
} elseif ($filterPayment === 'unpaid') {
    $where .= " AND o.payment_status NOT IN ('paid','cash') AND o.payment_method NOT LIKE '%cash%'";
}
if ($filterMethod === 'cash') {
    $where .= " AND o.payment_method LIKE '%cash%'";
} elseif ($filterMethod === 'liqpay') {
    $where .= " AND o.payment_method='card_online'";
} elseif ($filterMethod === 'card_pickup') {
    $where .= " AND o.payment_method='card_on_pickup'";
}
if ($filterType === 'takeout') {
    $where .= " AND (o.delivery_address IS NULL OR o.delivery_address='')";
} elseif ($filterType === 'hall') {
    $where .= " AND (o.delivery_address IS NOT NULL AND o.delivery_address!='')";
}
if ($filterSearch !== '') {
    $where .= ' AND (o.customer_name LIKE ? OR o.customer_surname LIKE ? OR o.phone LIKE ? OR o.order_id=?)';
    $like = '%' . $filterSearch . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = (int)$filterSearch;
    $types .= 'sssi';
}
if ($dateFrom !== '') { $where .= ' AND DATE(o.created_at)>=?'; $params[] = $dateFrom; $types .= 's'; }
if ($dateTo   !== '') { $where .= ' AND DATE(o.created_at)<=?'; $params[] = $dateTo;   $types .= 's'; }
if ($timeFrom !== '') { $where .= ' AND HOUR(o.created_at)>=?'; $params[] = (int)$timeFrom; $types .= 'i'; }
if ($timeTo   !== '') { $where .= ' AND HOUR(o.created_at)<=?'; $params[] = (int)$timeTo;   $types .= 'i'; }

$totalRows = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders o WHERE $where");
if ($stmt) {
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalRows = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$orders = [];
$stmt = $conn->prepare("
    SELECT o.order_id, o.customer_name, o.customer_surname, o.phone,
           o.total, o.status, o.payment_status, o.payment_method,
           o.created_at, o.delivery_address,
           COUNT(oi.id) AS items_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.order_id
    WHERE $where
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
if ($stmt) {
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($types . 'ii', ...$allParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $orders[] = $row;
    $stmt->close();
}

$statusCounts = [];
$r = $conn->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
if ($r) while ($row = $r->fetch_assoc()) $statusCounts[$row['status']] = $row['c'];

$paidCount   = 0; $cashCount = 0; $unpaidCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE payment_status='paid'");
if ($r) $paidCount = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE payment_method LIKE '%cash%'");
if ($r) $cashCount = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE payment_status NOT IN ('paid','cash') AND payment_method NOT LIKE '%cash%'");
if ($r) $unpaidCount = (int)$r->fetch_assoc()['c'];
$allCount = array_sum($statusCounts);

$preloadedDetails = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'order_id');
    $inList   = implode(',', array_map('intval', $orderIds));
    $itemRows = $conn->query("SELECT * FROM order_items WHERE order_id IN ($inList) ORDER BY order_id, id");
    $rawItems = [];
    if ($itemRows) while ($ir = $itemRows->fetch_assoc()) $rawItems[$ir['order_id']][] = $ir;

    $allowed_cats = ['coffee_items','fast_food_items','pizza_items','mini_pizza_items','cold_drink_items',
                     'dessert_items','sushi_items','sushi_sets','salad_items','cake_items',
                     'ice_cream_items'];

    foreach ($orderIds as $oid) {
        $items = $rawItems[$oid] ?? [];
        foreach ($items as &$it) {
            $cat = $it['category'] ?? '';
            $pid = (int)($it['product_id'] ?? 0);
            $it['product_name']  = '—';
            $it['product_image'] = '';
            if (in_array($cat, $allowed_cats) && $pid) {
                $s = $conn->prepare("SELECT name AS pn, image FROM `$cat` WHERE id=?");
                if ($s) {
                    $s->bind_param('i', $pid); $s->execute();
                    $row = $s->get_result()->fetch_assoc(); $s->close();
                    if ($row) {
                        $it['product_name']  = $row['pn'] ?? '—';
                        $rawImg = $row['image'] ?? '';
                        $it['product_image'] = (empty($rawImg) || $rawImg === 'static/images/menu_items/default.jpg') ? '' : $rawImg;
                    }
                }
            }
        }
        unset($it);
        $preloadedDetails[$oid] = $items;
    }
}

function statusInfo(string $s): array {
    return match($s) {
        'new'        => ['Нове',       'badge-new'],
        'processing' => ['В обробці',  'badge-processing'],
        'ready'      => ['Готово',     'badge-ready-order'],
        'done'       => ['Виконано',   'badge-done'],
        'cancelled'  => ['Скасовано',  'badge-cancelled'],
        default      => ['Нове',       'badge-new'],
    };
}

function paymentBadge(array $o): string {
    $ps = $o['payment_status'] ?? '';
    $pm = $o['payment_method']  ?? '';
    if ($ps === 'paid')            return '<span class="pay-tag pay-paid">'   . icon('paid-card',    13, '#2e7d32') . ' Оплачено онлайн</span>';
    if (str_contains($pm, 'cash')) return '<span class="pay-tag pay-cash">'   . icon('paid-cash',    13, '#f57f17') . ' При отриманні</span>';
    return                                '<span class="pay-tag pay-unpaid">' . icon('paid-pending', 13, '#9e9e9e') . ' Не оплачено</span>';
}

function renderOrderDetailsHtml(array $order, array $items): string {
    $esc = fn($v) => htmlspecialchars((string)($v ?? '—'), ENT_QUOTES, 'UTF-8');
    $pm  = $order['payment_method'] ?? '';
    $payMethod = match(true) {
        str_contains($pm, 'cash')    => 'При отриманні',
        $pm === 'card_online'        => 'Картка онлайн (LiqPay)',
        $pm === 'card_on_pickup'     => 'Картка у кафе',
        default                      => $esc($pm),
    };
    $isCash = str_contains($pm, 'cash');
    $ps = $order['payment_status'] ?? '';
    $payStatus = $isCash ? '' : match($ps) {
        'paid'    => '<span style="color:#2e7d32;font-weight:700">✓ Оплачено</span>',
        'pending' => '<span style="color:#e65100">⏳ Очікує оплати</span>',
        'failed'  => '<span style="color:#c62828">✗ Не оплачено</span>',
        default   => '',
    };
    $fullName = trim(($order['customer_name'] ?? '') . ' ' . ($order['customer_surname'] ?? '')) ?: '—';
    $sizeMap = ['small'=>'30 см','medium'=>'35 см','large'=>'40 см','xl'=>'XL'];
    $sizedCats = ['pizza_items','mini_pizza_items','sushi_sets'];

    ob_start(); ?>
<div class="od-wrap">
  <div class="od-info">
    <div class="od-block-title">Клієнт</div>
    <div class="od-row"><span class="od-key">Ім'я</span><span class="od-val"><?= $esc($fullName) ?></span></div>
    <div class="od-row"><span class="od-key">Телефон</span><span class="od-val"><?= $esc($order['phone'] ?? '') ?></span></div>
    <div class="od-row"><span class="od-key">Оплата</span><span class="od-val"><?= $payMethod ?></span></div>
    <?php if ($payStatus): ?><div class="od-row"><span class="od-key">Статус оплати</span><span class="od-val"><?= $payStatus ?></span></div><?php endif; ?>
    <?php if (!empty($order['ready_time'])): ?><div class="od-row"><span class="od-key">Час готовності</span><span class="od-val">⏰ <?= $esc($order['ready_time']) ?></span></div><?php endif; ?>
    <?php if (!empty($order['comment'])): ?><div class="od-row"><span class="od-key">Коментар</span><span class="od-val"><?= $esc($order['comment']) ?></span></div><?php endif; ?>
    <div class="od-row"><span class="od-key">Сума</span><span class="od-val" style="font-weight:700;color:#8B4513;font-size:15px"><?= number_format((float)$order['total'], 0, ',', ' ') ?> ₴</span></div>
  </div>
  <div class="od-items-wrap">
    <div class="od-block-title">Склад замовлення (<?= count($items) ?>)</div>
    <?php if ($items): ?>
    <div class="od-cards">
      <?php foreach ($items as $it):
        $name  = $it['product_name'] ?? '—';
        $img   = $it['product_image'] ?? '';
        $ltr   = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        $qty   = (int)($it['quantity'] ?? 1);
        $price = (float)($it['price'] ?? 0);
        $opts  = [];
        $rawSize = trim($it['selected_size'] ?? '');
        if ($rawSize !== '' && in_array($it['category'], $sizedCats)) $opts[] = $sizeMap[strtolower($rawSize)] ?? $rawSize;
        if (!empty($it['cheese_crust'])) $opts[] = 'Сирні бортики';
        $rawVar = trim($it['selected_variant'] ?? '');
        if ($rawVar !== '') {
            $d = json_decode($rawVar, true);
            if (is_array($d)) {
                $parts = [];
                if (!empty($d['filling_label'])) $parts[] = $d['filling_label'];
                if (!empty($d['size_label']))    $parts[] = $d['size_label'];
                if (empty($parts) && !empty($d['scoop_label'])) $parts[] = $d['scoop_label'];
                if (!empty($d['sauces']) && is_array($d['sauces'])) $parts[] = implode(', ', $d['sauces']);
                if ($parts) $opts[] = implode(' · ', $parts);
            } else { $opts[] = $rawVar; }
        }
      ?>
      <div class="od-card">
        <div class="od-card__img">
          <?php if ($img): ?><img src="../<?= htmlspecialchars($img) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;display:block">
          <?php else: ?><div class="od-card__placeholder"><?= $ltr ?></div><?php endif; ?>
        </div>
        <div class="od-card__body">
          <div class="od-card__name"><?= $esc($name) ?></div>
          <?php if ($opts): ?><div class="od-card__opts"><?= $esc(implode(' · ', $opts)) ?></div><?php endif; ?>
          <div class="od-card__price"><?= number_format($price,0,',',' ') ?> ₴ × <?= $qty ?> = <strong><?= number_format($qty*$price,0,',',' ') ?> ₴</strong></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p style="color:#aaa;font-size:13px;padding:8px 0">Товари не знайдено</p><?php endif; ?>
  </div>
</div>
    <?php return ob_get_clean();
}

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if (!$isAjax) include 'includes/layout_top.php';
?>
<?php if (!$isAjax): ?>
<!-- ══ Unified orders toolbar ══ -->
<div class="orders-toolbar">

  <!-- Row 1: filter chips -->
  <div class="toolbar-chips">
    <!-- Payment status -->
    <div class="chip-group">
      <?php
        $ptabs = ['' => ['Всі', $allCount], 'paid' => ['Оплачені', $paidCount], 'unpaid' => ['Не оплачені', $unpaidCount], 'cash' => ['Готівка', $cashCount]];
        foreach ($ptabs as $val => [$lbl, $cnt]):
          $active = ($filterPayment === $val);
      ?>
        <a href="#" class="fchip <?= $active ? 'fchip--on' : '' ?>" data-fkey="payment" data-fval="<?= htmlspecialchars($val) ?>">
          <?= $lbl ?><?php if ($cnt > 0): ?><span class="fchip-cnt"><?= $cnt ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <span class="chip-sep"></span>

    <!-- Payment method (toggleable) -->
    <div class="chip-group">
      <?php
        $methods = ['cash' => 'Готівка', 'liqpay' => 'Картка онлайн'];
        foreach ($methods as $val => $lbl):
          $active = ($filterMethod === $val);
      ?>
        <a href="#" class="fchip <?= $active ? 'fchip--on' : '' ?>" data-fkey="method" data-fval="<?= htmlspecialchars($val) ?>" data-toggle="1"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>

    <span class="chip-sep"></span>

    <!-- Order type (toggleable) -->
    <div class="chip-group">
      <?php
        $types_f = ['takeout' => 'З собою', 'hall' => 'В залі'];
        foreach ($types_f as $val => $lbl):
          $active = ($filterType === $val);
      ?>
        <a href="#" class="fchip <?= $active ? 'fchip--on' : '' ?>" data-fkey="type" data-fval="<?= htmlspecialchars($val) ?>" data-toggle="1"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>

    <?php
      $hasAnyFilter = $filterPayment || $filterMethod || $filterType || $filterStatus || $filterSearch || $dateFrom || $dateTo || $timeFrom !== '' || $timeTo !== '';
      if ($hasAnyFilter):
    ?>
      <a href="#" class="fchip fchip--reset" data-reset="1">✕ Скинути</a>
    <?php endif; ?>

    <span id="ordersCount" style="margin-left:auto;font-size:12px;color:#bbb;white-space:nowrap;align-self:center">
      <?= $totalRows ?> замовлень
    </span>
  </div>

  <!-- Row 2: search bar -->
  <form class="toolbar-search" method="get">
    <?php if ($filterPayment): ?><input type="hidden" name="payment" value="<?= htmlspecialchars($filterPayment) ?>"><?php endif; ?>
    <?php if ($filterMethod):  ?><input type="hidden" name="method"  value="<?= htmlspecialchars($filterMethod) ?>"><?php endif; ?>
    <?php if ($filterType):    ?><input type="hidden" name="type"    value="<?= htmlspecialchars($filterType) ?>"><?php endif; ?>

    <!-- Search -->
    <div class="ts-search">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="search" placeholder="Ім'я, телефон або #ID" value="<?= htmlspecialchars($filterSearch) ?>">
    </div>

    <!-- Status -->
    <select name="status" class="ts-select">
      <option value="">Всі статуси</option>
      <?php foreach ($statusLabels as $st => $lbl): $cnt = $statusCounts[$st] ?? 0; ?>
        <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $lbl ?><?= $cnt > 0 ? " ($cnt)" : '' ?></option>
      <?php endforeach; ?>
    </select>

    <!-- Date quick buttons + range -->
    <?php
      $hasDateFilter = ($dateFrom !== '' || $dateTo !== '');
      $noDateParams  = array_diff_key($_GET, array_flip(['date_from', 'date_to', 'p']));
      $noDateUrl     = 'orders.php' . ($noDateParams ? '?' . http_build_query($noDateParams) : '');
    ?>
    <div class="ts-date-group <?= $hasDateFilter ? 'ts-date-group--active' : '' ?>">
      <?php foreach (['today' => 'Сьогодні', 'yesterday' => 'Вчора', 'week' => 'Тиждень', 'month' => 'Місяць', 'all' => 'Весь час'] as $rKey => $rLabel): ?>
        <button type="button" class="fchip fchip--sm <?= $activeQuickRange === $rKey ? 'fchip--on' : '' ?>" data-range="<?= $rKey ?>"><?= $rLabel ?></button>
      <?php endforeach; ?>
      <input type="text" name="date_from" id="filterDateFrom" class="ts-date-input" value="<?= htmlspecialchars($dateFrom) ?>" placeholder="Від" autocomplete="off" readonly>
      <span class="ts-sep">—</span>
      <input type="text" name="date_to"   id="filterDateTo"   class="ts-date-input" value="<?= htmlspecialchars($dateTo) ?>"   placeholder="До" autocomplete="off" readonly>
      <?php if ($hasDateFilter): ?>
        <a href="#" class="ts-time-clear" data-clear-keys="date_from,date_to" title="Скинути фільтр дат">✕</a>
      <?php endif; ?>
    </div>

    <!-- Time range -->
    <?php
      $hasTimeFilter = ($timeFrom !== '' || $timeTo !== '');
      $noTimeParams  = array_diff_key($_GET, array_flip(['time_from', 'time_to', 'p']));
      $noTimeUrl     = 'orders.php' . ($noTimeParams ? '?' . http_build_query($noTimeParams) : '');
    ?>
    <div class="ts-time-group <?= $hasTimeFilter ? 'ts-time-group--active' : '' ?>">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?= $hasTimeFilter ? '#FFC107' : '#bbb' ?>" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <select name="time_from" class="ts-time-select">
        <option value="">00</option>
        <?php for ($h = 1; $h <= 23; $h++): ?>
          <option value="<?= $h ?>" <?= (string)$timeFrom === (string)$h ? 'selected' : '' ?>><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?></option>
        <?php endfor; ?>
      </select>
      <span class="ts-sep">—</span>
      <select name="time_to" class="ts-time-select">
        <option value="">23</option>
        <?php for ($h = 0; $h <= 22; $h++): ?>
          <option value="<?= $h ?>" <?= (string)$timeTo === (string)$h ? 'selected' : '' ?>><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?></option>
        <?php endfor; ?>
      </select>
      <?php if ($hasTimeFilter): ?>
        <a href="#" class="ts-time-clear" data-clear-keys="time_from,time_to" title="Скинути фільтр годин">✕</a>
      <?php endif; ?>
    </div>

    <button type="submit" class="ts-submit">Знайти</button>
  </form>

</div>
<?php endif; // !$isAjax toolbar ?>

<?php if ($isAjax) { ob_start(); } else { echo '<div id="ordersResults">'; } ?>
<!-- ══ Bulk action bar ══ -->
<div class="bulk-bar" id="bulkBar">
  <span class="bulk-bar__count">Обрано: <strong id="bulkCount">0</strong></span>
  <div class="bulk-bar__actions">
    <button class="bulk-btn bulk-btn--process" id="bulkProcessing">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.47"/></svg>
      В обробку
    </button>
    <button class="bulk-btn bulk-btn--done" id="bulkDone">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
      Виконано
    </button>
    <button class="bulk-btn bulk-btn--cancel" id="bulkCancel">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      Скасувати
    </button>
    <button class="bulk-btn bulk-btn--delete" id="bulkDelete">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
      Видалити
    </button>
  </div>
</div>

<!-- ══ Table ══ -->
<div class="table-wrap">
  <table class="admin-table" id="ordersTable">
    <thead>
      <tr>
        <th style="width:32px;padding-left:16px">
          <input type="checkbox" id="selectAll" class="order-checkbox" title="Виділити всі">
        </th>
        <th style="width:28px"></th>
        <th>#</th>
        <th>Клієнт</th>
        <th>Телефон</th>
        <th>К-ть</th>
        <th>Сума</th>
        <th>Оплата</th>
        <th style="min-width:230px">Статус</th>
        <th>Дата</th>
        <th style="width:48px">Дії</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o):
        $fullName = trim(($o['customer_name'] ?? '') . ' ' . ($o['customer_surname'] ?? '')) ?: '—';
      ?>
      <tr class="order-row" data-order-id="<?= $o['order_id'] ?>" data-status="<?= h($o['status']) ?>">
        <td style="padding-left:16px">
          <input type="checkbox" class="order-checkbox row-checkbox" value="<?= $o['order_id'] ?>">
        </td>
        <td>
          <button class="expand-btn" title="Деталі">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
        </td>
        <td>
          <a href="view_order.php?id=<?= $o['order_id'] ?>" style="font-weight:700;color:#8B4513;text-decoration:none">#<?= $o['order_id'] ?></a>
        </td>
        <td><?= htmlspecialchars($fullName) ?></td>
        <td style="font-size:12px;color:#666"><?= htmlspecialchars($o['phone'] ?? '—') ?></td>
        <td style="color:#888"><?= (int)$o['items_count'] ?></td>
        <td><strong><?= number_format((float)$o['total'], 0, ',', ' ') ?> ₴</strong></td>
        <td><?= paymentBadge($o) ?></td>
        <td><?= renderStatusCell($o['order_id'], $o['status'], $transitions, $nextLabels, $statusLabels) ?></td>
        <td style="font-size:12px;color:#999;white-space:nowrap"><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
        <td>
          <button class="btn-delete-order" data-order="<?= $o['order_id'] ?>" title="Видалити замовлення">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
          </button>
        </td>
      </tr>
      <tr class="order-details-row" id="details-<?= $o['order_id'] ?>">
        <td colspan="11">
          <div class="order-details-inner" data-loaded="1">
            <?php echo renderOrderDetailsHtml($o, $preloadedDetails[$o['order_id']] ?? []); ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
        <tr><td colspan="11" style="text-align:center;color:#bbb;padding:36px">Замовлень не знайдено</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ══ Pagination ══ -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page - 1])) ?>" class="page-btn">‹</a>
  <?php endif; ?>
  <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['p' => $i])) ?>"
       class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page + 1])) ?>" class="page-btn">›</a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
if ($isAjax) {
    $html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode(['html' => $html, 'total' => $totalRows]);
    exit;
}
?>
<?php if (!$isAjax): ?></div><!-- /#ordersResults --><?php endif; ?>

<script>
(function () {
  'use strict';

  var TRANSITIONS = {
    new:        { label: 'Нове',      next: ['processing', 'done', 'cancelled'] },
    processing: { label: 'В обробці', next: ['ready', 'done', 'cancelled'] },
    ready:      { label: 'Готово',    next: ['done'] },
    done:       { label: 'Виконано',  next: [] },
    cancelled:  { label: 'Скасовано', next: [] },
  };
  var NEXT_LABELS = { processing: '→ В обробці', ready: '→ Готово', done: '✓ Виконано', cancelled: '✕ Скасувати' };

  function showToast(msg, type) {
    var el = document.querySelector('.admin-toast');
    if (el) el.remove();
    el = document.createElement('div');
    el.className = 'admin-toast ' + (type || 'success');
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(function() { el.classList.add('show'); });
    setTimeout(function() {
      el.classList.remove('show');
      setTimeout(function() { el.remove(); }, 300);
    }, 3000);
  }

  function renderStatusCell(orderId, status) {
    var t = TRANSITIONS[status] || TRANSITIONS['new'];
    var html = '<div class="status-cell"><span class="order-status-badge badge-' + status + '">' + t.label + '</span>';
    if (t.next.length) {
      t.next.forEach(function(ns) {
        var lbl = NEXT_LABELS[ns] || ('→ ' + (TRANSITIONS[ns] ? TRANSITIONS[ns].label : ns));
        html += '<button class="status-pill-btn pill-' + ns + '" data-order="' + orderId + '" data-status="' + ns + '">' + lbl + '</button>';
      });
    }
    return html + '</div>';
  }

  function updateSidebarBadge(n) {
    document.querySelectorAll('.orders-badge').forEach(function(b) {
      b.textContent = n > 0 ? n : '';
      b.style.display = n > 0 ? '' : 'none';
    });
  }

  function updateTabCounts() {
    fetch('get_order_counts.php')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        document.querySelectorAll('.payment-tab').forEach(function(tab) {
          var badge = tab.querySelector('.pay-tab-count');
          if (!badge) return;
          var href = tab.getAttribute('href') || '';
          if (href.includes('payment=paid'))        badge.textContent = data.paid   ?? badge.textContent;
          else if (href.includes('payment=unpaid')) badge.textContent = data.unpaid ?? badge.textContent;
          else if (href.includes('payment=cash'))   badge.textContent = data.cash   ?? badge.textContent;
          else                                      badge.textContent = data.all    ?? badge.textContent;
        });
        if (data.new !== undefined) updateSidebarBadge(data.new);
      })
      .catch(function() {});
  }

  var _delRow = null, _delOrderId = null;
  var delEl = document.createElement('div');
  delEl.id = 'deleteConfirmPopup';
  delEl.className = 'scp scp--danger';
  delEl.style.display = 'none';
  delEl.innerHTML = '<p class="scp-text">Видалити замовлення назавжди?</p><div class="scp-btns"><button class="scp-yes scp-yes--danger">Видалити</button><button class="scp-no">Скасувати</button></div>';
  document.body.appendChild(delEl);

  delEl.querySelector('.scp-no').onclick = function() { delEl.style.display = 'none'; };
  document.addEventListener('click', function(e) {
    if (delEl.style.display !== 'none' && !delEl.contains(e.target) && !e.target.closest('.btn-delete-order')) delEl.style.display = 'none';
  });

  delEl.querySelector('.scp-yes').onclick = async function() {
    var row = _delRow, orderId = _delOrderId;
    if (!row || !orderId) return;
    delEl.querySelector('.scp-text').textContent = 'Видалення…';
    delEl.querySelector('.scp-btns').style.display = 'none';
    try {
      var r    = await fetch('delete_order.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({order_id: orderId}) });
      var data = await r.json();
      delEl.style.display = 'none';
      delEl.querySelector('.scp-text').textContent = 'Видалити замовлення назавжди?';
      delEl.querySelector('.scp-btns').style.display = '';
      if (data.success) {
        row.style.animation = 'rowDelete .35s ease forwards';
        var detRow = document.getElementById('details-' + orderId);
        setTimeout(function() { row.remove(); if (detRow) detRow.remove(); }, 380);
        showToast('Замовлення #' + orderId + ' видалено', 'info');
        updateTabCounts();
      } else {
        showToast('Помилка видалення', 'error');
      }
    } catch(ex) {
      delEl.style.display = 'none';
      showToast("Помилка з'єднання", 'error');
    }
  };

  /* ══ BULK ACTIONS ══ */
  function getCheckedIds() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(function(cb) { return parseInt(cb.value); });
  }

  function updateBulkBar() {
    var bb  = document.getElementById('bulkBar');
    var bc  = document.getElementById('bulkCount');
    var sa  = document.getElementById('selectAll');
    var ids = getCheckedIds();
    var all = document.querySelectorAll('.row-checkbox');
    if (!bb) return;
    bb.classList.toggle('bulk-bar--visible', ids.length > 0);
    if (bc) bc.textContent = ids.length;
    if (sa) {
      sa.indeterminate = ids.length > 0 && ids.length < all.length;
      sa.checked = ids.length > 0 && ids.length === all.length;
    }
  }

  async function bulkAction(status) {
    var ids = getCheckedIds();
    if (!ids.length) return;
    var labelMap = { processing:'В обробку', done:'Виконано', cancelled:'Скасовано' };
    if (!confirm('Змінити статус ' + ids.length + ' замовлень → «' + (labelMap[status] || status) + '»?')) return;
    try {
      var r    = await fetch('bulk_order_status.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({order_ids: ids, status: status}) });
      var data = await r.json();
      if (data.updated > 0) {
        showToast('Оновлено: ' + data.updated + (data.skipped ? ', пропущено: ' + data.skipped : ''), 'success');
        setTimeout(function() { ajaxLoad(location.search.replace(/^\?/, ''), false); }, 400);
      } else {
        showToast('Жодне замовлення не оновлено (недозволені переходи)', 'error');
      }
    } catch(ex) { showToast("Помилка з'єднання", 'error'); }
  }

  async function bulkDelete() {
    var ids = getCheckedIds();
    if (!ids.length) return;
    if (!confirm('Видалити ' + ids.length + ' замовлень назавжди?')) return;
    var ok = 0;
    for (var i = 0; i < ids.length; i++) {
      try {
        var r = await fetch('delete_order.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({order_id: ids[i]}) });
        var d = await r.json();
        if (d.success) ok++;
      } catch(ex) {}
    }
    showToast('Видалено: ' + ok + ' з ' + ids.length, ok > 0 ? 'success' : 'error');
    setTimeout(function() { ajaxLoad(location.search.replace(/^\?/, ''), false); }, 400);
  }

  /* bulk buttons live in #ordersResults → rebind after each AJAX load */
  function rebindBulkButtons() {
    var bb = document.getElementById('bulkProcessing');
    var bd = document.getElementById('bulkDone');
    var bc = document.getElementById('bulkCancel');
    var bx = document.getElementById('bulkDelete');
    var sa = document.getElementById('selectAll');
    if (bb) bb.onclick = function() { bulkAction('processing'); };
    if (bd) bd.onclick = function() { bulkAction('done'); };
    if (bc) bc.onclick = function() { bulkAction('cancelled'); };
    if (bx) bx.onclick = function() { bulkDelete(); };
  }
  rebindBulkButtons();

  /* ══ AJAX FILTERING ══ */
  function syncRangeButtons(qs) {
    var now  = new Date();
    var td   = fmtDate(now);
    var yd   = new Date(now); yd.setDate(yd.getDate() - 1);  var ydStr = fmtDate(yd);
    var wk   = new Date(now); wk.setDate(wk.getDate() - 6);  var wkStr = fmtDate(wk);
    var mo   = new Date(now); mo.setDate(mo.getDate() - 29); var moStr = fmtDate(mo);

    var p    = new URLSearchParams(qs);
    var from = p.get('date_from') || '';
    var to   = p.get('date_to')   || '';
    var active = '';
    if      (from === '' && to === '')       active = 'all';
    else if (from === td    && to === td)    active = 'today';
    else if (from === ydStr && to === ydStr) active = 'yesterday';
    else if (from === wkStr && to === td)    active = 'week';
    else if (from === moStr && to === td)    active = 'month';
    document.querySelectorAll('[data-range]').forEach(function(btn) {
      btn.classList.toggle('fchip--on', btn.dataset.range === active);
    });
  }

  function ajaxLoad(params, pushUrl) {
    var qs = typeof params === 'string' ? params : new URLSearchParams(params).toString();
    if (pushUrl !== false) history.pushState(null, '', qs ? '?' + qs : location.pathname);

    syncRangeButtons(qs);

    var target = document.getElementById('ordersResults');
    target.style.opacity = '0.45';
    target.style.pointerEvents = 'none';

    fetch('orders.php?' + qs + '&ajax=1')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        target.innerHTML = data.html;
        target.style.opacity = '';
        target.style.pointerEvents = '';
        var countEl = document.getElementById('ordersCount');
        if (countEl) countEl.textContent = data.total + ' замовлень';
        rebindResults();
        rebindBulkButtons();
      })
      .catch(function() {
        target.style.opacity = '';
        target.style.pointerEvents = '';
      });
  }

  /* Re-bind events after results are replaced */
  function rebindResults() {
    /* status pills */
    var tbl = document.getElementById('ordersTable');
    if (!tbl) return;

    tbl.addEventListener('click', function(e) {
      var btn = e.target.closest('.status-pill-btn');
      if (!btn) return;
      e.stopPropagation();
      var row = btn.closest('tr.order-row');
      var orderId = parseInt(btn.dataset.order);
      var newStatus = btn.dataset.status;
      var tLabel = (TRANSITIONS[newStatus] || {}).label || newStatus;
      if (!confirm('Змінити статус на «' + tLabel + '»?')) return;
      btn.disabled = true;
      fetch('update_order_status.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({order_id: orderId, status: newStatus}) })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!data.success) { showToast(data.error || 'Помилка оновлення', 'error'); btn.disabled = false; return; }
          row.setAttribute('data-status', newStatus);
          var cell = row.querySelector('.status-cell');
          if (cell) cell.outerHTML = renderStatusCell(orderId, newStatus);
          showToast('Статус: ' + tLabel, 'success');
          updateTabCounts();
        })
        .catch(function() { showToast("Помилка з'єднання", 'error'); btn.disabled = false; });
    });

    /* delete buttons */
    tbl.addEventListener('click', function(e) {
      var btn = e.target.closest('.btn-delete-order');
      if (!btn) return;
      e.stopPropagation();
      _delRow = btn.closest('tr.order-row');
      _delOrderId = parseInt(btn.dataset.order);
      delEl.style.display = 'block';
      var rect = btn.getBoundingClientRect();
      delEl.style.top  = (rect.bottom + window.scrollY + 6) + 'px';
      delEl.style.left = Math.max(8, rect.right - delEl.offsetWidth) + 'px';
    });

    /* expand/detail rows */
    tbl.addEventListener('click', function(e) {
      var btn = e.target.closest('.expand-btn');
      if (!btn) return;
      var row = btn.closest('.order-row');
      var orderId = row.dataset.orderId;
      var detRow = document.getElementById('details-' + orderId);
      var inner  = detRow.querySelector('.order-details-inner');
      var isOpen = detRow.classList.contains('open');
      document.querySelectorAll('.order-details-row.open').forEach(function(r) {
        r.classList.remove('open');
        var oId = r.id.replace('details-', '');
        var eb = document.querySelector('.order-row[data-order-id="' + oId + '"] .expand-btn');
        if (eb) eb.classList.remove('rotated');
      });
      if (!isOpen) {
        detRow.classList.add('open');
        btn.classList.add('rotated');
        if (!inner.dataset.loaded) {
          inner.innerHTML = '<div class="order-details-loading">Завантаження…</div>';
          fetch('get_order_details.php?id=' + orderId)
            .then(function(r) { return r.text(); })
            .then(function(html) { inner.innerHTML = html; inner.dataset.loaded = '1'; });
        }
      }
    });

    /* checkboxes */
    var selectAllNew = document.getElementById('selectAll');
    if (selectAllNew) {
      selectAllNew.addEventListener('change', function() {
        document.querySelectorAll('.row-checkbox').forEach(function(cb) { cb.checked = selectAllNew.checked; });
        updateBulkBar();
      });
    }
    tbl.addEventListener('change', function(e) {
      if (e.target.classList.contains('row-checkbox')) updateBulkBar();
    });

    /* pagination links */
    document.querySelectorAll('.pagination .page-btn').forEach(function(a) {
      a.addEventListener('click', function(e) {
        e.preventDefault();
        var qs = (this.getAttribute('href') || '').replace(/^\?/, '');
        ajaxLoad(qs);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  }

  /* ══ CURRENT FILTER STATE ══
     Єдине джерело правди — завжди будуємо URL з цього об'єкта. */
  var currentFilter = {};
  (new URLSearchParams(location.search)).forEach(function(v, k) {
    if (k !== 'p' && v !== '') currentFilter[k] = v;
  });

  function doFilter(updates) {
    if (updates) Object.assign(currentFilter, updates);
    /* очищаємо порожні значення */
    Object.keys(currentFilter).forEach(function(k) {
      if (currentFilter[k] === '' || currentFilter[k] === undefined) delete currentFilter[k];
    });
    var qs = new URLSearchParams(Object.assign({}, currentFilter, { p: 1 })).toString();
    /* оновлюємо відображення дат у Flatpickr */
    var df = currentFilter.date_from || null;
    var dt = currentFilter.date_to   || null;
    if (fpFrom) { fpFrom.setDate(df, false); filterDateFrom.value = df || ''; }
    if (fpTo)   { fpTo.setDate(dt, false);   filterDateTo.value   = dt || ''; }
    /* оновлюємо статус-селект форми */
    var sel = document.querySelector('.toolbar-search select[name="status"]');
    if (sel) sel.value = currentFilter.status || '';
    /* оновлюємо поле пошуку */
    var srch = document.querySelector('.toolbar-search input[name="search"]');
    if (srch) srch.value = currentFilter.search || '';
    /* оновлюємо підсвічення чіпів */
    document.querySelectorAll('.toolbar-chips a.fchip[data-fkey]').forEach(function(a) {
      var key = a.dataset.fkey, val = a.dataset.fval;
      a.classList.toggle('fchip--on', (currentFilter[key] || '') === val);
    });
    syncRangeButtons(qs);
    ajaxLoad(qs);
  }

  var searchForm = document.querySelector('.toolbar-search');
  searchForm.addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    currentFilter.search    = fd.get('search')    || '';
    currentFilter.status    = fd.get('status')    || '';
    currentFilter.date_from = fd.get('date_from') || '';
    currentFilter.date_to   = fd.get('date_to')   || '';
    currentFilter.time_from = fd.get('time_from') || '';
    currentFilter.time_to   = fd.get('time_to')   || '';
    doFilter();
  });

  document.querySelector('.orders-toolbar').addEventListener('click', function(e) {
    var link = e.target.closest('a.fchip[data-fkey], a.fchip[data-reset], a.ts-time-clear[data-clear-keys]');
    if (!link) return;
    e.preventDefault();

    if (link.dataset.reset) {
      currentFilter = {};
      if (fpFrom) { fpFrom.clear(); filterDateFrom.value = ''; }
      if (fpTo)   { fpTo.clear();   filterDateTo.value   = ''; }
      ajaxLoad('');
      syncRangeButtons('');
      document.querySelectorAll('.toolbar-chips a.fchip[data-fkey]').forEach(function(a) {
        a.classList.toggle('fchip--on', a.dataset.fval === '' || a.dataset.fval === (a.dataset.fkey === 'payment' ? '' : undefined));
        if (a.dataset.fkey === 'payment') a.classList.toggle('fchip--on', a.dataset.fval === '');
        else a.classList.remove('fchip--on');
      });
      return;
    }

    if (link.dataset.clearKeys) {
      var upd = {};
      link.dataset.clearKeys.split(',').forEach(function(k) { upd[k.trim()] = ''; });
      doFilter(upd);
      return;
    }

    /* fchip з data-fkey */
    var key = link.dataset.fkey;
    var val = link.dataset.fval;
    /* toggle: якщо вже активний — скидаємо */
    if (link.dataset.toggle && (currentFilter[key] || '') === val) val = '';
    var upd = {};
    upd[key] = val;
    doFilter(upd);
  });

  var filterDateFrom = document.getElementById('filterDateFrom');
  var filterDateTo   = document.getElementById('filterDateTo');
  var fpFrom = flatpickr(filterDateFrom, {
    locale: 'uk', dateFormat: 'Y-m-d', disableMobile: true,
    defaultDate: filterDateFrom.value || null,
    onReady: window.fpBuildYearSelect,
    onChange: function(sel, str) { filterDateTo._flatpickr.set('minDate', str || null); }
  });
  var fpTo = flatpickr(filterDateTo, {
    locale: 'uk', dateFormat: 'Y-m-d', disableMobile: true,
    defaultDate: filterDateTo.value || null,
    onReady: window.fpBuildYearSelect,
    onChange: function(sel, str) { filterDateFrom._flatpickr.set('maxDate', str || null); }
  });

  function fmtDate(d) {
    var y = d.getFullYear(), m = String(d.getMonth()+1).padStart(2,'0'), dd = String(d.getDate()).padStart(2,'0');
    return y + '-' + m + '-' + dd;
  }
  document.querySelectorAll('[data-range]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var now   = new Date();
      var today = fmtDate(now);
      var range = this.dataset.range;
      var from, to;
      if (range === 'all') {
        doFilter({ date_from: '', date_to: '' }); return;
      } else if (range === 'today') {
        from = today; to = today;
      } else if (range === 'yesterday') {
        var yy = new Date(now); yy.setDate(yy.getDate() - 1); from = to = fmtDate(yy);
      } else if (range === 'week') {
        var w = new Date(now); w.setDate(w.getDate() - 6); from = fmtDate(w); to = today;
      } else if (range === 'month') {
        var mo = new Date(now); mo.setDate(mo.getDate() - 29); from = fmtDate(mo); to = today;
      }
      doFilter({ date_from: from, date_to: to });
    });
  });

  rebindResults();

  window.addEventListener('popstate', function() {
    currentFilter = {};
    (new URLSearchParams(location.search)).forEach(function(v, k) {
      if (k !== 'p' && v !== '') currentFilter[k] = v;
    });
    ajaxLoad(location.search.replace(/^\?/, ''), false);
    syncRangeButtons(location.search.replace(/^\?/, ''));
  });

})();
</script>

<?php include 'includes/layout_bottom.php'; ?>
