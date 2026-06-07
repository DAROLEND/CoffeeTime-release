<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('orders');

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) { header('Location: orders.php'); exit; }

$stmt = $conn->prepare("
    SELECT o.order_id, o.status, o.total, o.phone, o.ready_time,
           o.payment_method, o.payment_status, o.customer_name,
           o.comment, o.created_at, o.user_id,
           u.email, u.client_name, u.client_surname
    FROM orders o
    LEFT JOIN users u ON u.client_id = o.user_id
    WHERE o.order_id = ?
");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$order) { header('Location: orders.php'); exit; }

$items = [];
$stmt = $conn->prepare("SELECT product_id, quantity, price, category, selected_size, selected_variant, cheese_crust FROM order_items WHERE order_id = ? ORDER BY id");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$rawItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$catAllowed = ['coffee_items','fast_food_items','pizza_items','cold_drink_items','dessert_items','sushi_items','sushi_sets','salad_items','cake_items','ice_cream_items','mini_pizza_items'];
foreach ($rawItems as $row) {
    $cat  = $row['category'];
    $pid  = (int)$row['product_id'];
    $name = '—';
    if (in_array($cat, $catAllowed)) {
        $s   = $conn->prepare("SELECT name AS nm FROM `$cat` WHERE id=?");
        if ($s) {
            $s->bind_param('i', $pid);
            $s->execute();
            $pr = $s->get_result()->fetch_assoc();
            $s->close();
            $name = $pr['nm'] ?? '—';
        }
    }
    $row['product_name'] = $name;
    $items[] = $row;
}

$rating = null;
$chk = $conn->query("SHOW TABLES LIKE 'order_ratings'");
if ($chk && $chk->num_rows > 0) {
    $s = $conn->prepare("SELECT rating, comment, created_at FROM order_ratings WHERE order_id=?");
    $s->bind_param('i', $orderId);
    $s->execute();
    $rating = $s->get_result()->fetch_assoc();
    $s->close();
}

$pmLabels = [
    'cash_on_pickup'  => 'При отриманні',
    'card_online'     => 'Картка онлайн (LiqPay)',
    'card_on_pickup'  => 'Картка у кафе',
];
$statusLabels = ['new' => 'Нове', 'done' => 'Виконано', 'cancelled' => 'Скасовано'];
$statusBadge  = ['new' => 'badge-new', 'done' => 'badge-ready', 'cancelled' => 'badge-cancelled'];
$payBadge     = [
    'paid'    => ['label' => 'Оплачено',     'cls' => 'badge-ready'],
    'pending' => ['label' => 'Не оплачено',  'cls' => 'badge-new'],
    ''        => ['label' => 'Не оплачено',  'cls' => 'badge-new'],
];

$clientName = trim(
    ($order['client_name'] ?? $order['customer_name'] ?? '') . ' ' .
    ($order['client_surname'] ?? '')
) ?: ($order['customer_name'] ?? '—');

$pageTitle  = 'Замовлення #' . $orderId;
$activePage = 'orders';
include 'includes/layout_top.php';
?>

<!-- Breadcrumb + back -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#b09070">
    <a href="orders.php" style="color:#8B4513;text-decoration:none;font-weight:600">Замовлення</a>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
    <span>#<?= $orderId ?></span>
  </div>
  <a href="orders.php" class="action-btn action-btn--ghost" style="font-size:12px;padding:6px 14px">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
    Назад до списку
  </a>
</div>

<!-- Page header -->
<div style="display:flex;align-items:center;gap:14px;margin-bottom:24px;flex-wrap:wrap">
  <h1 style="font-size:22px;font-weight:700;color:#2c1810;margin:0">Замовлення #<?= $orderId ?></h1>
  <span class="order-badge <?= $statusBadge[$order['status']] ?? 'badge-new' ?>" style="font-size:13px;padding:5px 14px">
    <?= $statusLabels[$order['status']] ?? $order['status'] ?>
  </span>
</div>

<!-- Info grid -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">

  <!-- Client card -->
  <div class="dash-section" style="padding:22px 24px">
    <div style="font-size:10px;font-weight:700;color:#b09070;text-transform:uppercase;letter-spacing:.08em;margin-bottom:16px">Клієнт</div>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#8B4513,#d4a96a);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;flex-shrink:0">
        <?= mb_strtoupper(mb_substr($clientName, 0, 1, 'UTF-8'), 'UTF-8') ?>
      </div>
      <div>
        <div style="font-weight:700;color:#2c1810;font-size:15px"><?= htmlspecialchars($clientName) ?></div>
        <?php if (!empty($order['email'])): ?>
          <div style="font-size:12px;color:#aaa"><?= htmlspecialchars($order['email']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($order['phone'])): ?>
    <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#555;padding:8px 0;border-top:1px solid #f0e8df">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#8B4513" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.58 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      <?= htmlspecialchars($order['phone']) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($order['user_id'])): ?>
    <div style="margin-top:10px">
      <a href="javascript:void(0)" style="font-size:12px;color:#8B4513;text-decoration:none">
        ID клієнта: #<?= (int)$order['user_id'] ?>
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Order info card -->
  <div class="dash-section" style="padding:22px 24px">
    <div style="font-size:10px;font-weight:700;color:#b09070;text-transform:uppercase;letter-spacing:.08em;margin-bottom:16px">Деталі замовлення</div>
    <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">

      <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:8px;border-bottom:1px solid #f0e8df">
        <span style="color:#aaa">Дата</span>
        <span style="font-weight:600;color:#2c1810"><?= date('d.m.Y · H:i', strtotime($order['created_at'])) ?></span>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:8px;border-bottom:1px solid #f0e8df">
        <span style="color:#aaa">Сума</span>
        <span style="font-weight:700;color:#8B4513;font-size:16px"><?= number_format((float)$order['total'], 0, ',', ' ') ?> ₴</span>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:8px;border-bottom:1px solid #f0e8df">
        <span style="color:#aaa">Оплата</span>
        <div style="display:flex;align-items:center;gap:6px">
          <span style="color:#555"><?= htmlspecialchars($pmLabels[$order['payment_method']] ?? $order['payment_method']) ?></span>
          <?php if ($order['payment_method'] === 'card_online'):
            $ps = $order['payment_status'] ?? ''; $pb = $payBadge[$ps] ?? $payBadge[''];
          ?>
            <span class="order-badge <?= $pb['cls'] ?>" style="font-size:10px;padding:2px 8px"><?= $pb['label'] ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($order['ready_time'])): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:8px;border-bottom:1px solid #f0e8df">
        <span style="color:#aaa">Час готовності</span>
        <span style="font-weight:600;color:#2c1810">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:middle;margin-right:3px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <?= htmlspecialchars($order['ready_time']) ?>
        </span>
      </div>
      <?php endif; ?>

      <?php if (!empty($order['comment'])): ?>
      <div style="padding-top:2px">
        <div style="color:#aaa;margin-bottom:4px">Коментар</div>
        <div style="background:#fdf6ee;border-radius:8px;padding:10px 12px;font-size:12px;color:#555;font-style:italic;line-height:1.5">
          💬 <?= nl2br(htmlspecialchars($order['comment'])) ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>

</div>

<!-- Items table -->
<div class="dash-section" style="padding:22px 24px;margin-bottom:20px">
  <div style="font-size:10px;font-weight:700;color:#b09070;text-transform:uppercase;letter-spacing:.08em;margin-bottom:16px">
    Позиції замовлення
    <span style="background:#f0e8df;color:#8B6040;padding:2px 8px;border-radius:10px;margin-left:8px;font-size:10px"><?= count($items) ?></span>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Назва</th>
        <th style="text-align:center">Кількість</th>
        <th style="text-align:right">Ціна</th>
        <th style="text-align:right">Сума</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item):
        $itemOpts = [];
        $itemCat  = $item['category'] ?? '';

        /* Pizza size */
        $rawSz = trim($item['selected_size'] ?? '');
        $sizedCats = ['pizza_items','mini_pizza_items','sushi_sets'];
        if ($rawSz !== '' && in_array($itemCat, $sizedCats)) {
            $szMap = ['small'=>'30 см','medium'=>'35 см','large'=>'40 см','xl'=>'XL'];
            $itemOpts[] = $szMap[strtolower($rawSz)] ?? $rawSz;
        }

        /* Cheese crust */
        if (!empty($item['cheese_crust'])) $itemOpts[] = 'Сирні бортики';

        /* Variant JSON */
        $rawV = trim($item['selected_variant'] ?? '');
        if ($rawV !== '') {
            $d = json_decode($rawV, true);
            if (is_array($d)) {
                $vp = [];
                if (!empty($d['filling_label'])) $vp[] = $d['filling_label'];
                if (!empty($d['size_label']))    $vp[] = $d['size_label'];
                if (empty($vp) && !empty($d['scoop_label'])) $vp[] = $d['scoop_label'];
                if (!empty($d['sauces']) && is_array($d['sauces'])) $vp[] = implode(', ', $d['sauces']);
                if (!empty($vp)) $itemOpts[] = implode(' · ', $vp);
            } else {
                $itemOpts[] = $rawV;
            }
        }
    ?>
      <tr>
        <td>
          <div style="font-weight:600;color:#2c1810"><?= htmlspecialchars($item['product_name']) ?></div>
          <?php if (!empty($itemOpts)): ?>
            <div style="font-size:11px;color:#8B6040;margin-top:2px"><?= htmlspecialchars(implode(' · ', $itemOpts)) ?></div>
          <?php endif; ?>
        </td>
        <td style="text-align:center">×<?= (int)$item['quantity'] ?></td>
        <td style="text-align:right;color:#555"><?= number_format((float)$item['price'], 0, ',', ' ') ?> ₴</td>
        <td style="text-align:right;font-weight:700;color:#8B4513"><?= number_format((float)$item['price'] * (int)$item['quantity'], 0, ',', ' ') ?> ₴</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" style="text-align:right;font-weight:700;color:#555;border-top:2px solid #f0e8df;padding-top:12px">Разом:</td>
        <td style="text-align:right;font-weight:700;font-size:16px;color:#8B4513;border-top:2px solid #f0e8df;padding-top:12px"><?= number_format((float)$order['total'], 0, ',', ' ') ?> ₴</td>
      </tr>
    </tfoot>
  </table>
</div>

<!-- Client rating (if exists) -->
<?php if ($rating): ?>
<div class="dash-section" style="padding:22px 24px;margin-bottom:20px">
  <div style="font-size:10px;font-weight:700;color:#b09070;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px">Оцінка клієнта</div>
  <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <div style="font-size:22px;letter-spacing:2px;font-family:serif;line-height:1">
      <span style="color:#FFC107"><?= str_repeat('★', (int)$rating['rating']) ?></span><span style="color:#e0d5c9"><?= str_repeat('★', 5-(int)$rating['rating']) ?></span>
    </div>
    <div style="font-size:13px;font-weight:700;color:#8B4513"><?= (int)$rating['rating'] ?>/5</div>
    <?php if (!empty($rating['comment'])): ?>
      <div style="flex:1;font-size:13px;color:#666;font-style:italic;background:#fdf6ee;border-radius:10px;padding:8px 14px">
        "<?= htmlspecialchars($rating['comment']) ?>"
      </div>
    <?php endif; ?>
    <div style="font-size:12px;color:#bbb;margin-left:auto"><?= date('d.m.Y H:i', strtotime($rating['created_at'])) ?></div>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/layout_bottom.php'; ?>
