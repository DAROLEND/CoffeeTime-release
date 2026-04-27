<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) { echo '<p style="color:#c00;padding:12px">Невірний ID</p>'; exit; }

$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id=?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { echo '<p style="color:#c00;padding:12px">Замовлення не знайдено</p>'; exit; }

$stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id=?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$rawItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$allowed_cats = ['coffee_items','fast_food_items','pizza_items','cold_drink_items','dessert_items','giftcards','sushi_items','sushi_sets','salad_items','cake_items'];
$items = [];
foreach ($rawItems as $it) {
    $cat  = $it['category'];
    $pid  = (int)$it['product_id'];
    $name = '—';
    if (in_array($cat, $allowed_cats)) {
        $nameCol = ($cat === 'giftcards') ? 'title' : 'name';
        $s = $conn->prepare("SELECT `$nameCol` AS product_name FROM `$cat` WHERE id=?");
        $s->bind_param("i", $pid);
        $s->execute();
        $row  = $s->get_result()->fetch_assoc();
        $s->close();
        $name = $row['product_name'] ?? '—';
    }
    $it['product_name'] = $name;
    $items[] = $it;
}

function esc(mixed $v): string {
    return htmlspecialchars((string)($v ?? '—'), ENT_QUOTES, 'UTF-8');
}

$payMethod = match(true) {
    str_contains($order['payment_method'] ?? '', 'cash') => '💵 Готівка при отриманні',
    str_contains($order['payment_method'] ?? '', 'card') => '💳 Картка (онлайн)',
    default => esc($order['payment_method']),
};
$payStatus = match($order['payment_status'] ?? '') {
    'paid'    => '<span style="color:#2e7d32;font-weight:700">✓ Оплачено</span>',
    'pending' => '<span style="color:#e65100">⏳ Очікує оплати</span>',
    'failed'  => '<span style="color:#c62828">✕ Не оплачено</span>',
    default   => esc($order['payment_status']),
};

$fullName = trim(($order['customer_name'] ?? '') . ' ' . ($order['customer_surname'] ?? '')) ?: '—';
?>
<div class="od-grid">

  <!-- Left: customer info -->
  <div>
    <div class="od-block-title">Клієнт</div>
    <div class="od-row"><span class="od-key">Ім'я</span><span class="od-val"><?= esc($fullName) ?></span></div>
    <div class="od-row"><span class="od-key">Телефон</span><span class="od-val"><?= esc($order['phone']) ?></span></div>
    <?php if (!empty($order['comment'])): ?>
    <div class="od-row"><span class="od-key">Коментар</span><span class="od-val"><?= esc($order['comment']) ?></span></div>
    <?php endif; ?>
    <div class="od-row"><span class="od-key">Час</span><span class="od-val"><?= esc($order['ready_time']) ?></span></div>
    <div class="od-row"><span class="od-key">Оплата</span><span class="od-val"><?= $payMethod ?></span></div>
    <div class="od-row"><span class="od-key">Статус оплати</span><span class="od-val"><?= $payStatus ?></span></div>
  </div>

  <!-- Right: order items -->
  <div>
    <div class="od-items-title">Склад замовлення</div>
    <?php if (!empty($items)): ?>
      <?php foreach ($items as $it): ?>
      <div class="od-item-row">
        <span class="od-item-name"><?= esc($it['product_name']) ?></span>
        <span class="od-item-qty"><?= (int)$it['quantity'] ?> шт.</span>
        <span class="od-item-price"><?= number_format($it['quantity'] * $it['price'], 0, ',', ' ') ?> ₴</span>
      </div>
      <?php endforeach; ?>
      <div class="od-total-row">
        <span>Разом:</span>
        <span><?= number_format($order['total'], 0, ',', ' ') ?> ₴</span>
      </div>
    <?php else: ?>
      <p style="color:#aaa;font-size:13px">Товари не знайдено</p>
    <?php endif; ?>
  </div>

</div>
