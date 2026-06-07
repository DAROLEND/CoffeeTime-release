<?php
require_once '../includes/session.php';
require_once '../db/db.php';
require_once '../includes/helpers.php';

if (empty($_SESSION['user'])) {
    header('Location: ../forms/login.php'); exit;
}

$user   = $_SESSION['user'];
$userId = (int)$user['client_id'];

if (isset($_GET['repay'])) {
    $roid = (int)$_GET['repay'];
    $s = $conn->prepare(
        "SELECT order_id, total FROM orders
         WHERE order_id=? AND user_id=? AND payment_status IN ('pending','')"
    );
    $s->bind_param('ii', $roid, $userId);
    $s->execute();
    $ro = $s->get_result()->fetch_assoc();
    $s->close();
    if ($ro) {
        $_SESSION['pending_order_id']    = $ro['order_id'];
        $_SESSION['pending_order_total'] = $ro['total'];
        header('Location: ../liqpay_checkout.php?back=profile');
    } else {
        header('Location: profile.php?tab=orders');
    }
    exit;
}

$profileError  = '';
$passwordError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $phone     = trim($_POST['phone']      ?? '');
        $stmt = $conn->prepare("UPDATE users SET client_name=?, client_surname=?, client_PhoneNumber=? WHERE client_id=?");
        $stmt->bind_param('sssi', $firstName, $lastName, $phone, $userId);
        if ($stmt->execute()) {
            $_SESSION['user']['client_name']        = $firstName;
            $_SESSION['user']['client_surname']     = $lastName;
            $_SESSION['user']['client_PhoneNumber'] = $phone;
            $user = $_SESSION['user'];
            $_SESSION['prof_saved'] = 'profile';
            header('Location: profile.php?tab=settings'); exit;
        }
        $profileError = 'Помилка оновлення даних.';
        $stmt->close();

    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $passwordError = 'Заповніть усі поля.';
        } elseif ($new !== $confirm) {
            $passwordError = 'Паролі не збігаються.';
        } elseif (mb_strlen($new) < 6) {
            $passwordError = 'Мінімум 6 символів.';
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE client_id=?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $dbRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($dbRow && password_verify($current, $dbRow['password'])) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE client_id=?");
                $stmt->bind_param('si', $hash, $userId);
                $stmt->execute();
                $stmt->close();
                $_SESSION['prof_saved'] = 'password';
                header('Location: profile.php?tab=settings'); exit;
            }
            $passwordError = 'Неправильний поточний пароль.';
        }
    }
}

$savedFlag = $_SESSION['prof_saved'] ?? '';
unset($_SESSION['prof_saved']);

if (empty($user['created_at'])) {
    $stmt = $conn->prepare("SELECT created_at FROM users WHERE client_id=?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $user['created_at'] = $row['created_at'] ?? null;
    $_SESSION['user']['created_at'] = $user['created_at'];
}

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END), 0) AS tsum FROM orders WHERE user_id=? AND status != 'cancelled'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats      = $stmt->get_result()->fetch_assoc();
$stmt->close();
$orderCount = (int)$stats['cnt'];
$totalSpent = (float)$stats['tsum'];

$ukMonths = ['', 'січ.', 'лют.', 'бер.', 'квіт.', 'трав.', 'черв.', 'лип.', 'серп.', 'вер.', 'жовт.', 'лист.', 'груд.'];
$joinedAt = !empty($user['created_at'])
    ? ($ukMonths[(int)date('n', strtotime($user['created_at']))] . ' ' . date('Y', strtotime($user['created_at'])))
    : '—';

$stmt = $conn->prepare("
    SELECT o.order_id, o.created_at, o.total, o.status,
           o.payment_method, o.payment_status,
           o.ready_time, o.comment, COUNT(oi.id) AS items_count
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ? AND o.status != 'cancelled'
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($orders) {
    $orderIds     = array_column($orders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types        = str_repeat('i', count($orderIds));
    $stmt = $conn->prepare("
        SELECT order_id, product_id, category
        FROM order_items
        WHERE order_id IN ($placeholders)
        ORDER BY order_id, id ASC
    ");
    $stmt->bind_param($types, ...$orderIds);
    $stmt->execute();
    $allRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $byOrder = [];
    foreach ($allRows as $r) {
        $oid = $r['order_id'];
        if (!isset($byOrder[$oid])) $byOrder[$oid] = [];
        if (count($byOrder[$oid]) < 2) $byOrder[$oid][] = $r;
    }

    $catAllowed = ['coffee_items','fast_food_items','pizza_items','cold_drink_items','dessert_items','sushi_items','sushi_sets','salad_items','cake_items','ice_cream_items','mini_pizza_items'];
    $previewNames = [];
    foreach ($byOrder as $oid => $rows) {
        $names = [];
        foreach ($rows as $r) {
            $cat     = $r['category'];
            $pid     = (int)$r['product_id'];
            if (!in_array($cat, $catAllowed)) { $names[] = 'Позиція'; continue; }
            $s = $conn->prepare("SELECT name AS nm FROM `$cat` WHERE id=?");
            if ($s) {
                $s->bind_param('i', $pid);
                $s->execute();
                $pr = $s->get_result()->fetch_assoc();
                $s->close();
                if (!empty($pr['nm'])) $names[] = $pr['nm'];
            }
        }
        $previewNames[$oid] = $names;
    }

    foreach ($orders as &$o) {
        $oid = $o['order_id'];
        $names = $previewNames[$oid] ?? [];
        $o['preview_names']     = $names;
        $o['preview_remaining'] = max(0, (int)$o['items_count'] - count($names));
    }
    unset($o);
}

$ratings = [];
if ($orders) {
    $chk = $conn->query("SHOW TABLES LIKE 'order_ratings'");
    if ($chk && $chk->num_rows > 0) {
        $rids  = array_column($orders, 'order_id');
        $rplc  = implode(',', array_fill(0, count($rids), '?'));
        $rtyp  = 'i' . str_repeat('i', count($rids));
        $stmt  = $conn->prepare("SELECT order_id, rating FROM order_ratings WHERE user_id=? AND order_id IN ($rplc)");
        $stmt->bind_param($rtyp, ...array_merge([$userId], $rids));
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $rr) {
            $ratings[$rr['order_id']] = $rr;
        }
        $stmt->close();
    }
}

$today   = new DateTime('today');
$weekAgo = (new DateTime())->modify('-7 days')->setTime(0, 0, 0);
$grouped = ['today' => [], 'week' => [], 'earlier' => []];
foreach ($orders as $o) {
    $dt = new DateTime($o['created_at']);
    if ($dt >= $today)       $grouped['today'][]   = $o;
    elseif ($dt >= $weekAgo) $grouped['week'][]    = $o;
    else                     $grouped['earlier'][] = $o;
}
$groupLabels = ['today' => 'Сьогодні', 'week' => 'Цього тижня', 'earlier' => 'Раніше'];

$firstName   = $user['client_name']    ?? '';
$lastName    = $user['client_surname'] ?? '';
$initials    = mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'), 'UTF-8');
if (!trim($initials)) $initials = mb_strtoupper(mb_substr($user['login'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8');
$displayName = trim("$firstName $lastName") ?: ($user['login'] ?? '');

$activeTab = (($_GET['tab'] ?? '') === 'settings') ? 'settings' : 'orders';

function profPayBadge(array $o): string {
    $ps = $o['payment_status'] ?? '';
    $pm = $o['payment_method']  ?? '';
    if ($ps === 'paid')             return '<span class="ppay ppay-paid">Оплачено</span>';
    if (str_contains($pm, 'cash')) return '<span class="ppay ppay-cash">Готівка</span>';
    return '<span class="ppay ppay-pending">Не оплачено</span>';
}
function profStatusBadge(string $s): string {
    $labels = [
        'processing' => 'В обробці',
        'ready'      => 'Готово',
        'done'       => 'Виконано',
    ];
    if (!isset($labels[$s])) return '';
    return '<span class="pstat pstat-' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($labels[$s], ENT_QUOTES, 'UTF-8') . '</span>';
}
function needsPayment(array $o): bool {
    if (($o['payment_method'] ?? '') !== 'card_online') return false;
    if (in_array($o['payment_status'] ?? '', ['paid'], true)) return false;
    if (($o['status'] ?? '') === 'cancelled') return false;
    // Показуємо "Оплатити" тільки для замовлень до 24 годин
    $age = time() - strtotime($o['created_at'] ?? '');
    return $age < 86400;
}

$page         = 'profile';
$pageTitle    = 'Профіль — Coffee Time';
$customStyles = ['../static/css/profile.css'];
?>
<!DOCTYPE html>
<html lang="uk">
<?php include '../includes/header.php'; ?>
<body class="<?= $activeTab === 'settings' ? 'tab-settings' : '' ?>">

<?php if ($savedFlag): ?>
<div class="prof-toast prof-toast--success" id="profToast">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
  <?= $savedFlag === 'profile' ? 'Профіль успішно оновлено' : 'Пароль успішно змінено' ?>
</div>
<?php endif; ?>

<div class="prof-wrap" id="profWrap">

  <!-- ════ SIDEBAR ════ -->
  <aside class="prof-sidebar">

    <div class="prof-avatar"><?= h($initials) ?></div>

    <div class="prof-name"><?= h($displayName) ?></div>
    <div class="prof-email"><?= h($user['email'] ?? '') ?></div>
    <?php if (!empty($user['client_PhoneNumber'])): ?>
      <div class="prof-phone"><?= h($user['client_PhoneNumber']) ?></div>
    <?php endif; ?>

    <hr class="prof-divider">

    <div class="prof-stats-grid">
      <button class="prof-stat-card" id="statOrders" title="Переглянути замовлення">
        <div class="prof-stat-card__val" data-countup="<?= $orderCount ?>">0</div>
        <div class="prof-stat-card__label">Замовлень</div>
      </button>
      <button class="prof-stat-card" id="statSpent" title="Переглянути замовлення">
        <div class="prof-stat-card__val" data-countup="<?= (int)round($totalSpent) ?>" data-suffix="₴">0</div>
        <div class="prof-stat-card__label">Витрачено</div>
      </button>
    </div>

    <div class="prof-since-pill">
      <span class="prof-since-dot"></span>
      З нами з <?= h($joinedAt) ?>
    </div>

    <hr class="prof-divider">

    <a href="../pages/logout.php" class="prof-logout-btn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Вийти
    </a>

  </aside>

  <!-- ════ MAIN ════ -->
  <main class="prof-main">

    <div class="prof-tabs-wrap">
      <div class="prof-tabs">
        <button class="prof-tab <?= $activeTab === 'orders'   ? 'active' : '' ?>" data-tab="orders">Мої замовлення</button>
        <button class="prof-tab <?= $activeTab === 'settings' ? 'active' : '' ?>" data-tab="settings">Налаштування</button>
      </div>
    </div>

    <!-- ══ Orders ══ -->
    <div class="prof-tab-panel <?= $activeTab === 'orders' ? 'active' : '' ?>" id="panel-orders">

      <?php if (empty($orders)): ?>
        <div class="prof-empty">
          <div class="prof-empty__icon">
            <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#D4A853" stroke-width="1.4" stroke-linecap="round">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/>
            </svg>
          </div>
          <p class="prof-empty-title">Замовлень ще немає</p>
          <p class="prof-empty-sub">Додайте щось смачне з нашого меню ☕</p>
          <a href="../pages/menu.php" class="prof-empty-btn">Перейти до меню</a>
        </div>
      <?php else: ?>

        <div class="orders-filter-bar" id="ordersFilterBar">
          <div class="ofilter-quick">
            <button class="ofilter-btn active" data-filter="all">Усі</button>
            <button class="ofilter-btn" data-filter="today">Сьогодні</button>
            <button class="ofilter-btn" data-filter="week">Тиждень</button>
            <button class="ofilter-btn" data-filter="month">Місяць</button>
          </div>
          <div class="ofilter-range">
            <input type="date" id="filterFrom" class="ofilter-date" title="від">
            <span class="ofilter-sep">—</span>
            <input type="date" id="filterTo"   class="ofilter-date" title="до">
          </div>
          <button class="ofilter-clear" id="filterClearBtn">✕ Скинути</button>
        </div>
        <div class="orders-empty-filter" id="ordersEmptyFilter" style="display:none">
          Замовлень за вибраний період не знайдено
        </div>

        <div class="orders-grid" id="ordersGrid">
        <?php foreach ($grouped as $groupKey => $groupOrders):
            if (empty($groupOrders)) continue;
            $isEarlier = ($groupKey === 'earlier');
        ?>

          <?php if ($isEarlier): ?>
          <div class="order-group order-group--collapsible" id="groupEarlier">
            <div class="order-group__label order-group__label--toggle" id="groupEarlierToggle">
              <?= $groupLabels[$groupKey] ?>
              <span class="order-group__count"><?= count($groupOrders) ?></span>
              <svg class="order-group__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="order-group__body" id="groupEarlierBody">
              <div class="orders-grid-inner">
              <?php foreach ($groupOrders as $idx => $o):
                  $isPending      = needsPayment($o);
                  $existingRating = $ratings[$o['order_id']] ?? null;
                  $isDone         = ($o['status'] === 'done');
                  $previewNames   = $o['preview_names'] ?? [];
                  if (count($previewNames) >= 2)      $dishLine = h($previewNames[0]) . ', ' . h($previewNames[1]);
                  elseif (count($previewNames) === 1) $dishLine = h($previewNames[0]);
                  else                                $dishLine = 'Замовлення';
                  $remaining = (int)($o['preview_remaining'] ?? 0);
                  if ($isPending) $cardColor = 'red';
                  elseif ($o['payment_status'] === 'paid' || $o['status'] === 'done') $cardColor = 'green';
                  else $cardColor = 'neutral';
              ?>
              <div class="order-card"
                   data-status="<?= h($o['status']) ?>"
                   data-color="<?= $cardColor ?>"
                   data-date="<?= date('Y-m-d', strtotime($o['created_at'])) ?>"
                   style="--reveal-delay:<?= $idx * 0.06 ?>s">

                <div class="order-card__top">
                  <div class="order-card__top-left">
                    <div class="order-card__num">#<?= (int)$o['order_id'] ?> &middot; <?= (int)$o['items_count'] ?> поз.</div>
                    <div class="order-card__dish"><?= $dishLine ?></div>
                    <?php if ($remaining > 0): ?>
                      <div class="order-card__dish-extra">та ще <?= $remaining ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="order-card__price"><?= number_format((float)$o['total'], 0, ',', ' ') ?> ₴</div>
                </div>

                <div class="order-card__meta">
                  <div class="order-card__tags">
                    <?php if ($isPending): ?><span class="ppay ppay-pending">Не оплачено</span>
                    <?php else: ?><?= profPayBadge($o) ?><?php endif; ?>
                    <?= profStatusBadge($o['status']) ?>
                  </div>
                  <div class="order-card__time">
                    <?= date('d.m.Y · H:i', strtotime($o['created_at'])) ?>
                    <?php if (!empty($o['ready_time'])): ?>
                      · <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> до <?= h($o['ready_time']) ?>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="order-card__footer">
                  <?php if ($isDone): ?>
                  <div class="order-card__rating<?= $existingRating ? ' order-card__rating--done' : '' ?>" id="rate-<?= (int)$o['order_id'] ?>">
                    <?php if ($existingRating): ?>
                      <div class="ocr-result">
                        <div class="ocr-stars-static"><?= str_repeat('★', (int)$existingRating['rating']) ?><span class="ocr-stars-empty"><?= str_repeat('★', 5-(int)$existingRating['rating']) ?></span></div>
                      </div>
                    <?php else: ?>
                      <div class="ocr-prompt">
                        <span class="ocr-label">Оцінити:</span>
                        <div class="ocr-stars" data-order="<?= (int)$o['order_id'] ?>">
                          <?php for ($si = 1; $si <= 5; $si++): ?><button type="button" class="ocr-star" data-val="<?= $si ?>">★</button><?php endfor; ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                  <div class="oc-btn-group">
                    <?php if ($isPending): ?>
                      <a href="profile.php?repay=<?= (int)$o['order_id'] ?>" class="oc-btn oc-btn--pay">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Оплатити
                      </a>
                    <?php endif; ?>
                    <button class="oc-btn oc-btn--details" data-order-id="<?= (int)$o['order_id'] ?>">
                      Деталі
                      <svg class="toggle-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                  </div>
                </div>

                <div class="order-card__details" id="details-<?= (int)$o['order_id'] ?>">
                  <div class="order-card__details-inner">
                    <?php if (!empty($o['comment'])): ?>
                      <div class="order-card__comment">💬 <?= h($o['comment']) ?></div>
                    <?php endif; ?>
                    <div class="oi-loading">Завантаження…</div>
                  </div>
                </div>

              </div>
              <?php endforeach; ?>
              </div>
            </div>
          </div>

          <?php else: ?>
          <div class="order-group">
            <div class="order-group__label"><?= $groupLabels[$groupKey] ?></div>
            <?php foreach ($groupOrders as $idx => $o):
                $isPending      = needsPayment($o);
                $existingRating = $ratings[$o['order_id']] ?? null;
                $isDone         = ($o['status'] === 'done');
                $previewNames   = $o['preview_names'] ?? [];
                if (count($previewNames) >= 2)      $dishLine = h($previewNames[0]) . ', ' . h($previewNames[1]);
                elseif (count($previewNames) === 1) $dishLine = h($previewNames[0]);
                else                                $dishLine = 'Замовлення';
                $remaining = (int)($o['preview_remaining'] ?? 0);
                if ($isPending) $cardColor = 'red';
                elseif ($o['payment_status'] === 'paid' || $o['status'] === 'done') $cardColor = 'green';
                else $cardColor = 'neutral';
            ?>
            <div class="order-card"
                 data-status="<?= h($o['status']) ?>"
                 data-color="<?= $cardColor ?>"
                 data-date="<?= date('Y-m-d', strtotime($o['created_at'])) ?>"
                 data-reveal
                 style="--reveal-delay:<?= $idx * 0.05 ?>s">

              <div class="order-card__top">
                <div class="order-card__top-left">
                  <div class="order-card__num">#<?= (int)$o['order_id'] ?> &middot; <?= (int)$o['items_count'] ?> поз.</div>
                  <div class="order-card__dish"><?= $dishLine ?></div>
                  <?php if ($remaining > 0): ?>
                    <div class="order-card__dish-extra">та ще <?= $remaining ?></div>
                  <?php endif; ?>
                </div>
                <div class="order-card__price"><?= number_format((float)$o['total'], 0, ',', ' ') ?> ₴</div>
              </div>

              <div class="order-card__meta">
                <div class="order-card__tags">
                  <?php if ($isPending): ?><span class="ppay ppay-pending">Не оплачено</span>
                  <?php else: ?><?= profPayBadge($o) ?><?php endif; ?>
                  <?= profStatusBadge($o['status']) ?>
                </div>
                <div class="order-card__time">
                  <?= date('d.m.Y · H:i', strtotime($o['created_at'])) ?>
                  <?php if (!empty($o['ready_time'])): ?>
                    · <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> до <?= h($o['ready_time']) ?>
                  <?php endif; ?>
                </div>
              </div>

              <div class="order-card__footer">
                <?php if ($isDone): ?>
                <div class="order-card__rating<?= $existingRating ? ' order-card__rating--done' : '' ?>" id="rate-<?= (int)$o['order_id'] ?>">
                  <?php if ($existingRating): ?>
                    <div class="ocr-result">
                      <div class="ocr-stars-static"><?= str_repeat('★', (int)$existingRating['rating']) ?><span class="ocr-stars-empty"><?= str_repeat('★', 5-(int)$existingRating['rating']) ?></span></div>
                    </div>
                  <?php else: ?>
                    <div class="ocr-prompt">
                      <span class="ocr-label">Оцінити:</span>
                      <div class="ocr-stars" data-order="<?= (int)$o['order_id'] ?>">
                        <?php for ($si = 1; $si <= 5; $si++): ?><button type="button" class="ocr-star" data-val="<?= $si ?>">★</button><?php endfor; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="oc-btn-group">
                  <?php if ($isPending): ?>
                    <a href="profile.php?repay=<?= (int)$o['order_id'] ?>" class="oc-btn oc-btn--pay">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                      Оплатити
                    </a>
                  <?php endif; ?>
                  <button class="oc-btn oc-btn--details" data-order-id="<?= (int)$o['order_id'] ?>">
                    Деталі
                    <svg class="toggle-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                  </button>
                </div>
              </div>

              <div class="order-card__details" id="details-<?= (int)$o['order_id'] ?>">
                <div class="order-card__details-inner">
                  <?php if (!empty($o['comment'])): ?>
                    <div class="order-card__comment">💬 <?= h($o['comment']) ?></div>
                  <?php endif; ?>
                  <div class="oi-loading">Завантаження…</div>
                </div>
              </div>

            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

        <?php endforeach; ?>
        </div>

      <?php endif; ?>
    </div>

    <!-- ══ Settings ══ -->
    <div class="prof-tab-panel <?= $activeTab === 'settings' ? 'active' : '' ?>" id="panel-settings">

      <div class="prof-section">
        <div class="prof-section__title">Особисті дані</div>

        <?php if ($profileError): ?>
          <div class="prof-alert prof-alert--error"><?= h($profileError) ?></div>
        <?php endif; ?>

        <form method="post" id="profileForm" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="profile">
          <div class="prof-form-row">
            <div class="prof-field">
              <label>Ім'я</label>
              <input type="text" name="first_name" value="<?= h($user['client_name'] ?? '') ?>" placeholder="Ім'я">
            </div>
            <div class="prof-field">
              <label>Прізвище</label>
              <input type="text" name="last_name" value="<?= h($user['client_surname'] ?? '') ?>" placeholder="Прізвище">
            </div>
          </div>
          <div class="prof-field">
            <label>Телефон</label>
            <input type="tel" name="phone" value="<?= h($user['client_PhoneNumber'] ?? '') ?>" placeholder="+38 (0XX) XXX-XX-XX">
          </div>
          <button type="submit" class="prof-btn prof-btn--dark" id="profileSaveBtn">Зберегти зміни</button>
        </form>
      </div>

      <div class="prof-section">
        <div class="prof-section__title">Безпека</div>

        <?php if ($passwordError): ?>
          <div class="prof-alert prof-alert--error"><?= h($passwordError) ?></div>
        <?php endif; ?>

        <form method="post" id="passwordForm" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="password">

          <div class="prof-field">
            <label>Поточний пароль</label>
            <div class="pw-wrap">
              <input type="password" name="current_password" id="pwCurrent" placeholder="••••••••" autocomplete="current-password">
              <button type="button" class="eye-btn" data-target="pwCurrent">
                <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <div class="prof-field">
            <label>Новий пароль</label>
            <div class="pw-wrap">
              <input type="password" name="new_password" id="pwNew" placeholder="••••••••" autocomplete="new-password">
              <button type="button" class="eye-btn" data-target="pwNew">
                <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <div class="pw-strength" id="pwStrength" style="display:none">
              <div class="pw-strength-bar"><span></span><span></span><span></span><span></span></div>
              <div class="pw-strength-label" id="pwStrengthLabel"></div>
            </div>
          </div>

          <div class="prof-field">
            <label>Підтвердіть новий пароль</label>
            <div class="pw-wrap">
              <input type="password" name="confirm_password" id="pwConfirm" placeholder="••••••••" autocomplete="new-password">
              <button type="button" class="eye-btn" data-target="pwConfirm">
                <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <button type="submit" class="prof-btn prof-btn--dark" id="passwordSaveBtn">Змінити пароль</button>
        </form>
      </div>

    </div>

  </main>
</div>

<?php include '../includes/footer.php'; ?>

<script>
(function () {
'use strict';

var toast = document.getElementById('profToast');
if (toast) {
  setTimeout(function () { toast.classList.add('prof-toast--out'); }, 3200);
  setTimeout(function () { toast.remove(); }, 3700);
}

// Strip entry animations after they complete — prevents re-trigger on tab switch
(function () {
  var once = function (el) {
    if (!el) return;
    el.addEventListener('animationend', function () { el.style.animation = 'none'; }, { once: true });
  };
  once(document.querySelector('.prof-sidebar'));
  once(document.querySelector('.prof-main'));
})();

function switchTab(target) {
  document.querySelectorAll('.prof-tab').forEach(function (t) {
    t.classList.toggle('active', t.dataset.tab === target);
  });
  document.querySelectorAll('.prof-tab-panel').forEach(function (p) {
    p.classList.toggle('active', p.id === 'panel-' + target);
  });
  document.body.classList.toggle('tab-settings', target === 'settings');
  var url = new URL(window.location);
  url.searchParams.set('tab', target);
  history.replaceState(null, '', url);
}
document.querySelectorAll('.prof-tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    if (!this.classList.contains('active')) switchTab(this.dataset.tab);
  });
});

['statOrders', 'statSpent'].forEach(function (id) {
  var el = document.getElementById(id);
  if (el) el.addEventListener('click', function () { switchTab('orders'); });
});

function countUp(el, target, ms) {
  var suffix = el.dataset.suffix ? ' ' + el.dataset.suffix : '';
  if (target === 0) { el.textContent = '0' + suffix; return; }
  var step = target / (ms / 16);
  var cur = 0;
  var timer = setInterval(function () {
    cur = Math.min(cur + step, target);
    el.textContent = Math.round(cur).toLocaleString('uk-UA') + suffix;
    if (cur >= target) clearInterval(timer);
  }, 16);
}
document.querySelectorAll('[data-countup]').forEach(function (el) {
  countUp(el, parseInt(el.dataset.countup, 10), 900);
});

var revealObs = new IntersectionObserver(function (entries) {
  entries.forEach(function (entry) {
    if (entry.isIntersecting) { entry.target.classList.add('revealed'); revealObs.unobserve(entry.target); }
  });
}, { threshold: 0.05 });
document.querySelectorAll('[data-reveal]').forEach(function (el) { revealObs.observe(el); });

var groupEarlier       = document.getElementById('groupEarlier');
var groupEarlierToggle = document.getElementById('groupEarlierToggle');
var groupEarlierBody   = document.getElementById('groupEarlierBody');
if (groupEarlier && groupEarlierToggle && groupEarlierBody) {
  // Auto-expand if it's the only group with orders
  var otherGroups = document.querySelectorAll('.orders-grid > .order-group:not(.order-group--collapsible)');
  var otherHasOrders = false;
  otherGroups.forEach(function (g) { if (g.querySelectorAll('.order-card').length > 0) otherHasOrders = true; });
  if (!otherHasOrders) {
    groupEarlier.classList.add('expanded');
    groupEarlierBody.style.maxHeight = 'none';
    groupEarlierBody.querySelectorAll('.order-card').forEach(function (c) { c.classList.add('revealed'); });
  }

  groupEarlierToggle.addEventListener('click', function () {
    var isExpanded = groupEarlier.classList.contains('expanded');
    if (isExpanded) {
      groupEarlier.classList.remove('expanded');
      groupEarlierBody.style.maxHeight = groupEarlierBody.scrollHeight + 'px';
      requestAnimationFrame(function () { groupEarlierBody.style.maxHeight = '0'; });
    } else {
      groupEarlier.classList.add('expanded');
      var cards = groupEarlierBody.querySelectorAll('.order-card');
      cards.forEach(function (card, i) {
        card.style.setProperty('--reveal-delay', (i * 0.06) + 's');
      });
      groupEarlierBody.style.maxHeight = 'none';
      var targetH = groupEarlierBody.scrollHeight;
      groupEarlierBody.style.maxHeight = '0';
      requestAnimationFrame(function () {
        groupEarlierBody.style.maxHeight = targetH + 'px';
        cards.forEach(function (card) {
          if (!card.classList.contains('revealed')) {
            requestAnimationFrame(function () {
              requestAnimationFrame(function () { card.classList.add('revealed'); });
            });
          }
        });
      });
      groupEarlierBody.addEventListener('transitionend', function onEnd(e) {
        if (e.propertyName === 'max-height' && groupEarlier.classList.contains('expanded')) {
          groupEarlierBody.style.maxHeight = 'none';
        }
        groupEarlierBody.removeEventListener('transitionend', onEnd);
      });
    }
  });
}

document.addEventListener('click', function (e) {
  var btn = e.target.closest('.oc-btn--details');
  if (!btn) return;

  var orderId   = btn.dataset.orderId;
  var detailsEl = document.getElementById('details-' + orderId);
  if (!detailsEl) return;
  var isOpen  = detailsEl.classList.contains('open');
  var chevron = btn.querySelector('.toggle-chevron');

  if (isOpen) {
    detailsEl.classList.remove('open');
    if (chevron) chevron.style.transform = '';
    btn.classList.remove('active');
  } else {
    detailsEl.classList.add('open');
    if (chevron) chevron.style.transform = 'rotate(180deg)';
    btn.classList.add('active');

    if (!detailsEl.dataset.loaded) {
      detailsEl.dataset.loaded = '1';
      var inner = detailsEl.querySelector('.order-card__details-inner');

      fetch('../pages/get_order_items.php?order_id=' + orderId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var loader = inner.querySelector('.oi-loading');
          if (loader) loader.remove();

          if (!data.items || !data.items.length) {
            inner.insertAdjacentHTML('beforeend', '<p class="oi-empty">Товари не знайдено</p>');
            return;
          }

          var html = data.items.map(function (item) {
            var total  = (item.quantity * item.price).toLocaleString('uk-UA', { maximumFractionDigits: 0 });
            var letter = (item.name || '?').charAt(0).toUpperCase();
            var imgHtml = item.image
              ? '<img class="oi-img" src="../' + item.image + '" alt="" loading="lazy"'
                + ' onerror="this.style.display=\'none\';this.nextSibling.style.display=\'flex\'">'
                + '<div class="oi-img-ph" style="display:none">' + letter + '</div>'
              : '<div class="oi-img-ph">' + letter + '</div>';
            return '<div class="oi-row">'
              + '<div class="oi-img-wrap">' + imgHtml + '</div>'
              + '<span class="oi-name">' + item.name + '</span>'
              + '<span class="oi-qty">×' + item.quantity + '</span>'
              + '<span class="oi-price">' + total + ' ₴</span>'
              + '</div>';
          }).join('');

          inner.insertAdjacentHTML('beforeend', '<div class="oi-list">' + html + '</div>');
        })
        .catch(function () {
          var loader = detailsEl.querySelector('.oi-loading');
          if (loader) loader.textContent = 'Помилка завантаження';
        });
    }
  }
});

document.querySelectorAll('.eye-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var inp  = document.getElementById(this.dataset.target);
    var show = this.querySelector('.eye-show');
    var hide = this.querySelector('.eye-hide');
    if (inp.type === 'password') {
      inp.type = 'text';
      show.style.display = 'none'; hide.style.display = 'block';
    } else {
      inp.type = 'password';
      show.style.display = 'block'; hide.style.display = 'none';
    }
  });
});

var pwNew         = document.getElementById('pwNew');
var pwStrengthEl  = document.getElementById('pwStrength');
var pwStrengthLbl = document.getElementById('pwStrengthLabel');
var pwSegs        = pwStrengthEl ? pwStrengthEl.querySelectorAll('.pw-strength-bar span') : [];
var strengthCfg   = [null,
  { label: 'Слабкий',   color: '#f44336' },
  { label: 'Середній',  color: '#FF9800' },
  { label: 'Сильний',   color: '#FFC107' },
  { label: 'Відмінний', color: '#4CAF50' },
];
function calcStrength(pw) {
  var s = 0;
  if (pw.length >= 6) s++;
  if (pw.length >= 10) s++;
  if (/[A-ZА-ЯІЇЄҐ]/u.test(pw)) s++;
  if (/[0-9]/.test(pw)) s++;
  if (/[^a-zA-Zа-яА-ЯіїєґІЇЄҐ0-9]/u.test(pw)) s++;
  return Math.min(4, Math.max(1, s));
}
if (pwNew) {
  pwNew.addEventListener('input', function () {
    if (!this.value) { pwStrengthEl.style.display = 'none'; return; }
    pwStrengthEl.style.display = 'block';
    var score = calcStrength(this.value);
    var cfg   = strengthCfg[score];
    pwSegs.forEach(function (seg, i) { seg.style.background = i < score ? cfg.color : '#e8e0d8'; });
    pwStrengthLbl.textContent = cfg.label;
    pwStrengthLbl.style.color = cfg.color;
  });
}

document.getElementById('profileForm')?.addEventListener('submit', function () {
  document.getElementById('profileSaveBtn').classList.add('loading');
});
document.getElementById('passwordForm')?.addEventListener('submit', function () {
  document.getElementById('passwordSaveBtn').classList.add('loading');
});

(function () {
  var filterBtns   = document.querySelectorAll('.ofilter-btn');
  var filterFrom   = document.getElementById('filterFrom');
  var filterTo     = document.getElementById('filterTo');
  var clearBtn     = document.getElementById('filterClearBtn');
  var emptyMsg     = document.getElementById('ordersEmptyFilter');
  var activeFilter = 'all';

  function todayStr() {
    return new Date().toISOString().slice(0, 10);
  }
  function daysAgo(n) {
    var d = new Date(); d.setDate(d.getDate() - n);
    return d.toISOString().slice(0, 10);
  }
  function firstOfMonth() {
    var d = new Date(); d.setDate(1);
    return d.toISOString().slice(0, 10);
  }

  function applyFilter(from, to) {
    var hasRange  = from || to;
    var anyVisible = false;

    /* today/week groups (display:contents) */
    document.querySelectorAll('.orders-grid > .order-group:not(.order-group--collapsible)').forEach(function (grp) {
      var label   = grp.querySelector('.order-group__label');
      var cards   = grp.querySelectorAll('.order-card');
      var grpVis  = 0;
      cards.forEach(function (c) {
        var d = c.dataset.date || '';
        var ok = !hasRange || ((!from || d >= from) && (!to || d <= to));
        c.style.display = ok ? '' : 'none';
        if (ok) { grpVis++; anyVisible = true; }
      });
      if (label) label.style.display = grpVis ? '' : 'none';
    });

    /* earlier group (collapsible) */
    if (groupEarlier && groupEarlierBody) {
      var eCards  = groupEarlierBody.querySelectorAll('.order-card');
      var eVis    = 0;
      eCards.forEach(function (c) {
        var d = c.dataset.date || '';
        var ok = !hasRange || ((!from || d >= from) && (!to || d <= to));
        c.style.display = ok ? '' : 'none';
        if (ok) { eVis++; anyVisible = true; }
      });
      groupEarlier.style.display = eVis ? '' : 'none';
      if (eVis && hasRange && !groupEarlier.classList.contains('expanded')) {
        groupEarlierToggle.click();
      } else if (!hasRange && groupEarlier.classList.contains('expanded')) {
        groupEarlierToggle.click();
      }
    }

    if (emptyMsg) emptyMsg.style.display = (!anyVisible && hasRange) ? '' : 'none';
    if (clearBtn) clearBtn.classList.toggle('visible', !!hasRange);
  }

  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      filterBtns.forEach(function (b) { b.classList.remove('active'); });
      this.classList.add('active');
      activeFilter = this.dataset.filter;
      var from = '', to = '';
      var t = todayStr();
      if (activeFilter === 'today')  { from = t;              to = t; }
      if (activeFilter === 'week')   { from = daysAgo(6);     to = t; }
      if (activeFilter === 'month')  { from = firstOfMonth(); to = t; }
      if (filterFrom) filterFrom.value = '';
      if (filterTo)   filterTo.value   = '';
      applyFilter(from, to);
    });
  });

  function onDateInput() {
    filterBtns.forEach(function (b) { b.classList.remove('active'); });
    applyFilter(filterFrom ? filterFrom.value : '', filterTo ? filterTo.value : '');
  }
  if (filterFrom) filterFrom.addEventListener('change', onDateInput);
  if (filterTo)   filterTo.addEventListener('change', onDateInput);

  if (clearBtn) clearBtn.addEventListener('click', function () {
    filterBtns.forEach(function (b) { b.classList.remove('active'); });
    document.querySelector('.ofilter-btn[data-filter="all"]').classList.add('active');
    if (filterFrom) filterFrom.value = '';
    if (filterTo)   filterTo.value   = '';
    applyFilter('', '');
  });
})();

(function () {
  document.addEventListener('mouseover', function (e) {
    var star = e.target.closest('.ocr-star');
    if (!star) return;
    var wrap = star.closest('.ocr-stars');
    var val  = parseInt(star.dataset.val, 10);
    wrap.querySelectorAll('.ocr-star').forEach(function (s) {
      s.classList.toggle('hovered', parseInt(s.dataset.val, 10) <= val);
    });
  });
  document.addEventListener('mouseout', function (e) {
    var star = e.target.closest('.ocr-star');
    if (!star) return;
    var wrap    = star.closest('.ocr-stars');
    var selVal  = parseInt(wrap.dataset.selected || '0', 10);
    wrap.querySelectorAll('.ocr-star').forEach(function (s) {
      s.classList.remove('hovered');
      s.classList.toggle('selected', parseInt(s.dataset.val, 10) <= selVal);
    });
  });

  document.addEventListener('click', function (e) {
    var star = e.target.closest('.ocr-star');
    if (!star) return;

    var wrap      = star.closest('.ocr-stars');
    var ratingDiv = wrap.closest('.order-card__rating');
    var orderId   = wrap.dataset.order;
    var val       = parseInt(star.dataset.val, 10);

    if (ratingDiv.dataset.saving) return;

    wrap.dataset.selected = val;
    wrap.querySelectorAll('.ocr-star').forEach(function (s) {
      s.classList.toggle('selected', parseInt(s.dataset.val, 10) <= val);
      s.classList.remove('hovered');
    });

    ratingDiv.dataset.saving = '1';
    wrap.style.pointerEvents = 'none';
    wrap.style.opacity = '0.6';

    var fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('rating',   val);

    fetch('../forms/rate_order.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) {
          delete ratingDiv.dataset.saving;
          wrap.style.pointerEvents = '';
          wrap.style.opacity = '';
          return;
        }
        var stars = str_repeat_stars(val) + '<span class="ocr-stars-empty">' + str_repeat_stars(5 - val) + '</span>';
        ratingDiv.innerHTML = '<div class="ocr-result"><div class="ocr-stars-static">' + stars + '</div></div>';
        ratingDiv.classList.add('order-card__rating--done');
      })
      .catch(function () {
        delete ratingDiv.dataset.saving;
        wrap.style.pointerEvents = '';
        wrap.style.opacity = '';
      });
  });

  function str_repeat_stars(n) { return Array(n + 1).join('★'); }
  function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
})();

})();
</script>
</body>
</html>
