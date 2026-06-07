<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) { echo '<p style="color:#c00;padding:12px">Невірний ID</p>'; exit; }

$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id=?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { echo '<p style="color:#c00;padding:12px">Замовлення не знайдено</p>'; exit; }

$stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id=? ORDER BY id");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$rawItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$allowed_cats = ['coffee_items','fast_food_items','pizza_items','mini_pizza_items','cold_drink_items','dessert_items','sushi_items','sushi_sets','salad_items','cake_items','ice_cream_items'];
$items = [];
foreach ($rawItems as $it) {
    $cat  = $it['category'] ?? '';
    $pid  = (int)($it['product_id'] ?? 0);
    $it['product_name'] = '—';
    $it['product_image'] = '';
    if (in_array($cat, $allowed_cats)) {
        $s = $conn->prepare("SELECT name AS product_name, image FROM `$cat` WHERE id=?");
        if ($s) {
            $s->bind_param("i", $pid);
            $s->execute();
            $row = $s->get_result()->fetch_assoc();
            $s->close();
            if ($row) {
                $it['product_name']  = $row['product_name'] ?? '—';
                $rawImg = $row['image'] ?? '';
                $isDefault = empty($rawImg) || $rawImg === 'static/images/menu_items/default.jpg';
                $it['product_image'] = $isDefault ? '' : $rawImg;
            }
        }
    }
    $items[] = $it;
}

function esc(mixed $v): string {
    return htmlspecialchars((string)($v ?? '—'), ENT_QUOTES, 'UTF-8');
}

$payMethod = match(true) {
    str_contains($order['payment_method'] ?? '', 'cash') => 'При отриманні',
    $order['payment_method'] === 'card_online'           => 'Картка онлайн (LiqPay)',
    $order['payment_method'] === 'card_on_pickup'        => 'Картка у кафе',
    default => esc($order['payment_method']),
};
/* For cash orders payment_status is irrelevant — don't show it */
$isCashOrder = str_contains($order['payment_method'] ?? '', 'cash');
$payStatus = $isCashOrder ? null : match($order['payment_status'] ?? '') {
    'paid'    => '<span style="color:#2e7d32;font-weight:700">✓ Оплачено</span>',
    'pending' => '<span style="color:#e65100">⏳ Очікує оплати</span>',
    'failed'  => '<span style="color:#c62828">✗ Не оплачено</span>',
    ''        => null,
    default   => esc($order['payment_status']),
};

$fullName = trim(($order['customer_name'] ?? '') . ' ' . ($order['customer_surname'] ?? '')) ?: '—';

?>
<div class="od-wrap">

  <!-- Left: client info -->
  <div class="od-info">
    <div class="od-block-title">Клієнт</div>
    <div class="od-row"><span class="od-key">Ім'я</span><span class="od-val"><?= esc($fullName) ?></span></div>
    <div class="od-row"><span class="od-key">Телефон</span><span class="od-val"><?= esc($order['phone']) ?></span></div>
    <div class="od-row"><span class="od-key">Оплата</span><span class="od-val"><?= $payMethod ?></span></div>
    <?php if ($payStatus !== null): ?>
    <div class="od-row"><span class="od-key">Статус оплати</span><span class="od-val"><?= $payStatus ?></span></div>
    <?php endif; ?>
    <?php if (!empty($order['ready_time'])): ?>
    <div class="od-row"><span class="od-key">Час готовності</span><span class="od-val">⏰ <?= esc($order['ready_time']) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($order['comment'])): ?>
    <div class="od-row"><span class="od-key">Коментар</span><span class="od-val od-val--comment"><?= esc($order['comment']) ?></span></div>
    <?php endif; ?>
    <div class="od-row"><span class="od-key">Сума</span><span class="od-val" style="font-weight:700;color:#8B4513;font-size:15px"><?= number_format((float)$order['total'], 0, ',', ' ') ?> ₴</span></div>
  </div>

  <!-- Right: product mini-cards -->
  <div class="od-items-wrap">
    <div class="od-block-title">Склад замовлення (<?= count($items) ?>)</div>
    <?php if (!empty($items)): ?>
    <div class="od-cards">
      <?php foreach ($items as $it):
        $name      = $it['product_name'];
        $imgSrc    = $it['product_image'];
        $firstLtr  = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        $qty       = (int)($it['quantity'] ?? 1);
        $price     = (float)($it['price'] ?? 0);
        $lineTotal = $qty * $price;
        $sizeMap = [
            'small'  => '30 см',
            'medium' => '35 см',
            'large'  => '40 см',
            'xl'     => 'XL',
        ];
        $sizedCategories = ['pizza_items', 'mini_pizza_items', 'sushi_sets'];
        $opts = [];

        /* Pizza size */
        $rawSize = trim($it['selected_size'] ?? '');
        if ($rawSize !== '' && in_array($it['category'], $sizedCategories)) {
            $opts[] = $sizeMap[strtolower($rawSize)] ?? $rawSize;
        }

        /* Cheese crust (pizza) */
        if (!empty($it['cheese_crust'])) {
            $opts[] = 'Сирні бортики';
        }

        /* Variant JSON — decode all known structures */
        $rawVariant = trim($it['selected_variant'] ?? '');
        if ($rawVariant !== '') {
            $d = json_decode($rawVariant, true);
            if (is_array($d)) {
                $parts = [];
                /* filling+size combo */
                if (!empty($d['filling_label'])) $parts[] = $d['filling_label'];
                if (!empty($d['size_label']))    $parts[] = $d['size_label'];
                /* simple scoop / size / flavor */
                if (empty($parts) && !empty($d['scoop_label'])) $parts[] = $d['scoop_label'];
                /* sauces array */
                if (!empty($d['sauces']) && is_array($d['sauces'])) $parts[] = implode(', ', $d['sauces']);
                if (!empty($parts)) $opts[] = implode(' · ', $parts);
            } else {
                $opts[] = $rawVariant;
            }
        }
      ?>
      <div class="od-card">
        <div class="od-card__img">
          <?php if ($imgSrc): ?>
            <img src="../<?= htmlspecialchars($imgSrc) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;display:block">
          <?php else: ?>
            <div class="od-card__placeholder"><?= $firstLtr ?></div>
          <?php endif; ?>
        </div>
        <div class="od-card__body">
          <div class="od-card__name"><?= esc($name) ?></div>
          <?php if (!empty($opts)): ?>
            <div class="od-card__opts"><?= esc(implode(' · ', $opts)) ?></div>
          <?php endif; ?>
          <div class="od-card__price"><?= number_format($price, 0, ',', ' ') ?> ₴ × <?= $qty ?> = <strong><?= number_format($lineTotal, 0, ',', ' ') ?> ₴</strong></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p style="color:#aaa;font-size:13px;padding:8px 0">Товари не знайдено</p>
    <?php endif; ?>
  </div>

</div>
