<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/error_handler.php';
// Prevent browser from caching this page (bfcache fix)
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/telegram.php';

$allowedTables = [
    'coffee_items', 'fast_food_items', 'pizza_items',
    'cold_drink_items', 'dessert_items', 'giftcards',
    'sushi_items', 'sushi_sets', 'salad_items', 'cake_items',
];

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

// GET request — clear any stale session errors, never run validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['error'], $_SESSION['flash_error']);
}

$cart = $_SESSION['cart'];
$user = $_SESSION['user'] ?? null;

/* ── Fetch cart items from DB ── */
$orderDetails = [];
$total = 0.0;

foreach ($cart as $ci) {
    $table = $ci['category'];
    $id    = (int)$ci['id'];
    if (!in_array($table, $allowedTables, true)) continue;

    $stmt = $conn->prepare("SELECT id, name, image, price FROM `$table` WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $row['quantity'] = $ci['quantity'];
        $row['category'] = $ci['category'];
        if (isset($ci['price_override'])) {
            $row['price']  = (float)$ci['price_override'];
            $row['weight'] = $ci['weight'] ?? 1;
        }
        $row['subtotal'] = $row['price'] * $ci['quantity'];
        $total += $row['subtotal'];
        $orderDetails[] = $row;
    }
    $stmt->close();
}

/* ── Form values (pre-fill from session or POST) ── */
$firstName = $_POST['first_name'] ?? ($user['client_name']        ?? '');
$lastName  = $_POST['last_name']  ?? ($user['client_surname']      ?? '');
$phone     = $_POST['phone']      ?? ($user['client_PhoneNumber']  ?? '');
$readyTime = $_POST['ready_time'] ?? '';
$comment   = $_POST['comment']    ?? '';
$payment   = $_POST['payment']    ?? '';

/* ── Café schedule data (used for JS output and server-side validation) ── */
$next          = get_next_available_time();
$schedule_json = json_encode(get_cafe_schedule());
$next_json     = json_encode($next ? [
    'time'     => $next['time'],
    'label'    => $next['date_label'] ?: $next['date'],
    'is_today' => $next['is_today'],
] : null);

$orderId      = 0;
$errorMessage = '';

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$firstName || !$lastName || !$phone || !$readyTime || !$payment) {
        $errorMessage = "Будь ласка, заповніть усі обов'язкові поля.";
    } else {
        /* Working hours validation */
        preg_match('/(\d{2}:\d{2})$/', trim($readyTime), $_tm);
        $pickup_time = $_tm[1] ?? '';

        if (!$pickup_time) {
            $errorMessage = 'Вкажіть коректний час готовності';
        } else {
            $now_tz  = new DateTime('now', new DateTimeZone('Europe/Kiev'));
            $minTime = clone $now_tz;
            $minTime->modify('+10 minutes');

            [$h, $m] = explode(':', $pickup_time);

            // Build the order datetime starting from today
            $orderDate = clone $now_tz;
            $orderDate->setTime((int)$h, (int)$m, 0);

            // If the chosen time has already passed (or is within 10 min),
            // treat it as tomorrow — this handles "08:00 tomorrow" correctly
            // when the café is currently closed and JS snapped to next morning.
            if ($orderDate < $minTime) {
                $orderDate->modify('+1 day');
            }

            // Now check whether the café is actually open at that datetime
            if (!is_cafe_open_at($orderDate)) {
                $next_t  = get_next_available_time();
                $suggest = $next_t
                    ? ' Найближчий час: ' . ($next_t['date_label'] ? $next_t['date_label'] . ' ' : '') . $next_t['time']
                    : '';
                $errorMessage = 'Кафе не працює в цей час.' . $suggest;
            }
        }

        if (!$errorMessage) {
            $userIdParam = isset($user['client_id']) ? (int)$user['client_id'] : null;
            $sql = "INSERT INTO orders
                      (user_id, total, delivery_address, phone, status,
                       customer_name, customer_surname, comment, ready_time, payment_method)
                    VALUES (?, ?, '', ?, 'pending', ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('idssssss',
                $userIdParam, $total, $phone,
                $firstName, $lastName, $comment, $readyTime, $payment
            );

            if ($stmt->execute()) {
                $orderId = $stmt->insert_id;
                $stmt->close();

                foreach ($orderDetails as $item) {
                    $productId = (int)$item['id'];
                    $quantity  = (int)$item['quantity'];
                    $price     = (float)$item['price'];
                    $category  = $item['category'];
                    $table     = $item['category'];

                    $stmt2 = $conn->prepare(
                        "INSERT INTO order_items (order_id, product_id, category, quantity, price)
                         VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt2->bind_param('iisid', $orderId, $productId, $category, $quantity, $price);
                    $stmt2->execute();
                    $stmt2->close();

                    $upd = $conn->prepare("UPDATE `$table` SET popularity = popularity + ? WHERE id = ?");
                    $upd->bind_param('ii', $quantity, $productId);
                    $upd->execute();
                    $upd->close();
                }

                /* ── Telegram notification ── */
                notify_new_order(
                    $orderId, $firstName, $lastName, $phone,
                    $readyTime, $payment, $total,
                    array_map(fn($it) => [
                        'name'     => $it['name'],
                        'quantity' => $it['quantity'],
                        'price'    => $it['price'],
                    ], $orderDetails)
                );

                if ($payment === 'card_online') {
                    /* ── Card: save order then redirect to LiqPay ── */
                    unset($_SESSION['cart']);
                    $_SESSION['pending_order_id']    = $orderId;
                    $_SESSION['pending_order_total'] = $total;

                    // Try to mark order as pending_payment (graceful if columns missing)
                    $liqpayOid = 'coffeetime_' . $orderId;
                    try {
                        $upd = $conn->prepare(
                            "UPDATE orders SET payment_status = 'pending', liqpay_order_id = ? WHERE order_id = ?"
                        );
                        if ($upd) {
                            $upd->bind_param('si', $liqpayOid, $orderId);
                            $upd->execute();
                            $upd->close();
                        }
                    } catch (\Exception $e) {
                        // Columns not yet migrated — run the SQL in config.php comments
                    }

                    header('Location: ../liqpay_checkout.php');
                    exit;
                }

                /* ── Cash: PRG redirect to success page ── */
                unset($_SESSION['cart']);
                $_SESSION['pending_order_id'] = $orderId;
                header('Location: payment_success.php');
                exit;
            } else {
                $errorMessage = "Помилка при оформленні: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Оформлення замовлення — Coffee Time</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/checkout.css">
  <link rel="stylesheet" href="../static/css/footer.css">
</head>
<body>
<?php
  $page = 'checkout';
  include '../includes/header.php';
?>

<main class="checkout-page">

  <?php if (!empty($_SESSION['flash_error'])): ?>
  <div style="background:#ffebee;color:#c62828;padding:12px 20px;border-radius:8px;
              margin-bottom:20px;font-size:14px;border:1px solid #f5c6c6;">
    ⚠️ <?= h($_SESSION['flash_error']) ?>
    <?php unset($_SESSION['flash_error']); ?>
  </div>
  <?php endif; ?>

  <!-- ── Breadcrumb ── -->
  <nav class="breadcrumb" aria-label="Навігація">
    <a href="cart.php">Кошик</a>
    <span class="bc-sep">›</span>
    <span class="bc-current">Оформлення</span>
    <span class="bc-sep">›</span>
    <span class="bc-future">Підтвердження</span>
  </nav>

  <!-- ── Main layout ── -->
  <div class="checkout-layout" id="checkoutLayout">

    <!-- Left: form -->
    <div class="checkout-form-col">

      <?php if ($errorMessage): ?>
        <div class="checkout-error"><?= htmlspecialchars($errorMessage) ?></div>
      <?php endif; ?>

      <?php
        $hasCakes = false;
        foreach ($orderDetails as $od) { if ($od['category'] === 'cake_items') { $hasCakes = true; break; } }
      ?>
      <?php if ($hasCakes): ?>
        <div style="background:#fff8e1;border-radius:10px;padding:14px 18px;border-left:4px solid #FFC107;margin-bottom:20px;font-size:14px;line-height:1.55;color:#5a4000;">
          Торти на замовлення готуються від 1 до 3 днів. Менеджер зв'яжеться з вами після підтвердження замовлення для уточнення деталей.
        </div>
      <?php endif; ?>

      <form method="post" id="checkoutForm" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="ready_time" id="readyTimeHidden"
               value="<?= htmlspecialchars($readyTime) ?>">

        <!-- Section 1: Contact -->
        <div class="form-section" id="section1">
          <h2 class="section-title">
            <span class="section-num">1</span> Контактні дані
          </h2>
          <div class="form-row">
            <div>
              <div class="field-group" id="fg-first">
                <input type="text" id="first_name" name="first_name" placeholder=" "
                       value="<?= htmlspecialchars($firstName, ENT_QUOTES) ?>"
                       autocomplete="given-name">
                <label for="first_name">Ім'я *</label>
              </div>
              <div class="field-msg" id="msg-first"></div>
            </div>
            <div>
              <div class="field-group" id="fg-last">
                <input type="text" id="last_name" name="last_name" placeholder=" "
                       value="<?= htmlspecialchars($lastName, ENT_QUOTES) ?>"
                       autocomplete="family-name">
                <label for="last_name">Прізвище *</label>
              </div>
              <div class="field-msg" id="msg-last"></div>
            </div>
          </div>
          <div class="field-group" id="fg-phone">
            <input type="tel" id="phone" name="phone" placeholder=" "
                   value="<?= htmlspecialchars($phone, ENT_QUOTES) ?>"
                   autocomplete="tel">
            <label for="phone">Телефон *</label>
          </div>
          <div class="field-msg" id="msg-phone"></div>
        </div>

        <!-- Section 2: Time -->
        <div class="form-section" id="section2">
          <h2 class="section-title">
            <span class="section-num">2</span> Час отримання
          </h2>
          <div class="time-chips" id="timeChips">
            <button type="button" class="time-chip active" data-offset="15">
              <?php if ($next && $next['is_today']): ?>
                Якнайшвидше (~<?= h($next['time']) ?>)
              <?php elseif ($next): ?>
                Перший час: <?= h($next['date_label']) ?> <?= h($next['time']) ?>
              <?php else: ?>
                Кафе зачинене
              <?php endif; ?>
            </button>
            <button type="button" class="time-chip" data-offset="30">+30 хв</button>
            <button type="button" class="time-chip" data-offset="60">+1 год</button>
            <button type="button" class="time-chip" data-offset="custom">Вибрати час</button>
          </div>
          <div class="time-custom" id="timeCustom">
            <div class="field-group" id="fg-time">
              <input type="time" id="readyTimeInput" placeholder=" " step="900">
              <label for="readyTimeInput">Оберіть час *</label>
            </div>
            <div class="field-msg" id="msg-time"></div>
          </div>
          <p class="time-hint">Пн–Пт 08:00–20:00 &nbsp;·&nbsp; Сб 10:00–20:00 &nbsp;·&nbsp; Нд 12:00–20:00</p>
        </div>

        <!-- Section 3: Comment -->
        <div class="form-section" id="section3">
          <h2 class="section-title">
            <span class="section-num">3</span> Коментар до замовлення
          </h2>
          <div class="field-group" id="fg-comment">
            <textarea id="comment" name="comment" placeholder=" " rows="3"><?= htmlspecialchars($comment) ?></textarea>
            <label for="comment">Додаткові побажання (необов'язково)</label>
          </div>
        </div>

        <!-- Section 4: Payment -->
        <div class="form-section" id="section4">
          <h2 class="section-title">
            <span class="section-num">4</span> Спосіб оплати
          </h2>
          <div class="payment-cards" id="paymentCards">
            <label class="payment-card">
              <input type="radio" name="payment" value="cash_on_pickup"
                     <?= $payment === 'cash_on_pickup' ? 'checked' : '' ?>>
              <span class="pay-checkmark">
                <svg viewBox="0 0 12 10"><polyline points="1.5 5 4.5 8.5 10.5 1.5"/></svg>
              </span>
              <div class="pay-icon">💵</div>
              <div class="pay-name">Готівка</div>
              <div class="pay-desc">Оплата при отриманні</div>
            </label>
            <label class="payment-card">
              <input type="radio" name="payment" value="card_online"
                     <?= $payment === 'card_online' ? 'checked' : '' ?>>
              <span class="pay-checkmark">
                <svg viewBox="0 0 12 10"><polyline points="1.5 5 4.5 8.5 10.5 1.5"/></svg>
              </span>
              <div class="pay-icon">💳</div>
              <div class="pay-name">Картка</div>
              <div class="pay-desc">Visa / Mastercard</div>
            </label>
          </div>
          <div class="field-msg" id="msg-payment"></div>

          <!-- LiqPay info (shown when "Картка" is selected) -->
          <div class="liqpay-info" id="liqpayInfo">
            <span class="liqpay-lock">🔒</span>
            <p>Ви будете перенаправлені на захищену сторінку оплати LiqPay.
               Дані вашої картки обробляються безпечно за стандартом
               <strong>PCI DSS</strong>.</p>
          </div>

        </div>

      </form>
    </div><!-- /.checkout-form-col -->

    <!-- Right: order summary -->
    <div class="checkout-summary-col">
      <div class="checkout-summary-box" id="checkoutSummaryBox">
        <h3 class="summary-title">Ваше замовлення</h3>

        <ul class="order-items-list">
          <?php foreach ($orderDetails as $it): ?>
          <li class="order-item">
            <img src="../<?= htmlspecialchars($it['image'], ENT_QUOTES) ?>"
                 alt="<?= htmlspecialchars($it['name'], ENT_QUOTES) ?>"
                 loading="lazy">
            <div class="order-item-info">
              <div class="order-item-name"><?= htmlspecialchars($it['name'], ENT_QUOTES) ?></div>
              <div class="order-item-qty"><?= (int)$it['quantity'] ?> шт.</div>
            </div>
            <div class="order-item-price">
              <?= number_format($it['subtotal'], 0, ',', ' ') ?> ₴
            </div>
          </li>
          <?php endforeach; ?>
        </ul>

        <div class="summary-divider"></div>

        <div class="summary-total-row">
          <span class="summary-total-label">Загальна сума:</span>
          <span class="summary-total-price"><?= number_format($total, 0, ',', ' ') ?> ₴</span>
        </div>

        <button type="submit" form="checkoutForm" class="btn-checkout" id="checkoutBtn">
          Оформити замовлення
        </button>
      </div>
    </div>

  </div><!-- /.checkout-layout -->
</main>

<?php include '../includes/footer.php'; ?>

<script>
(function () {
  'use strict';


  /* ────────────────────────────────────────────────
     1. REVEAL ANIMATIONS
  ──────────────────────────────────────────────── */
  const sections = document.querySelectorAll('.form-section');
  sections.forEach((s, i) => {
    setTimeout(() => s.classList.add('visible'), 100 + i * 110);
  });
  const summaryBox = document.getElementById('checkoutSummaryBox');
  if (summaryBox) setTimeout(() => summaryBox.classList.add('visible'), 150);

  /* ────────────────────────────────────────────────
     2. PHONE MASK  →  +38 (0XX) XXX-XX-XX
  ──────────────────────────────────────────────── */
  const phoneInput = document.getElementById('phone');
  if (phoneInput) {
    phoneInput.addEventListener('input', function () {
      let d = this.value.replace(/\D/g, '');
      // Strip country code variations
      if (d.startsWith('380')) d = d.slice(2);
      else if (d.startsWith('38')) d = d.slice(2);
      // Ensure starts with 0
      if (d.length > 0 && !d.startsWith('0')) d = '0' + d;
      d = d.slice(0, 10);

      let out = '';
      if (d.length > 0) out = '+38 (' + d.slice(0, Math.min(3, d.length));
      if (d.length > 3) out += ') ' + d.slice(3, Math.min(6, d.length));
      if (d.length > 6) out += '-' + d.slice(6, Math.min(8, d.length));
      if (d.length > 8) out += '-' + d.slice(8, 10);
      this.value = out;
    });
    // Format pre-filled value on load
    if (phoneInput.value) phoneInput.dispatchEvent(new Event('input'));
  }

  /* ────────────────────────────────────────────────
     3. TIME CHIPS (schedule-aware)
  ──────────────────────────────────────────────── */
  const schedule      = <?= $schedule_json ?>;
  const nextAvailable = <?= $next_json ?>;

  const timeChipsEl  = document.getElementById('timeChips');
  const timeCustomEl = document.getElementById('timeCustom');
  const rtInput      = document.getElementById('readyTimeInput');
  const rtHidden     = document.getElementById('readyTimeHidden');

  // Check if café is open at a given Date (dow: 1=Mon…7=Sun)
  function isCafeOpenAt(date) {
    const dow = date.getDay() === 0 ? 7 : date.getDay();
    const day = schedule[dow];
    if (!day) return false;
    const [oh, om] = day.open.split(':').map(Number);
    const [ch, cm] = day.close.split(':').map(Number);
    const open  = new Date(date); open.setHours(oh, om, 0, 0);
    const close = new Date(date); close.setHours(ch, cm, 0, 0);
    return date >= open && date < close;
  }

  // Find next open 15-min slot at or after fromDate
  function getNextOpenTime(fromDate) {
    const d = new Date(fromDate);
    // round up to next 15-min boundary
    const rem = d.getMinutes() % 15;
    if (rem !== 0) d.setMinutes(d.getMinutes() + (15 - rem));
    d.setSeconds(0, 0);
    for (let i = 0; i < 8 * 24 * 4; i++) {
      if (isCafeOpenAt(d)) return d;
      d.setMinutes(d.getMinutes() + 15);
    }
    return null;
  }

  function fmtTime(date) {
    return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
  }

  function isToday(date) {
    const n = new Date();
    return date.getDate() === n.getDate() && date.getMonth() === n.getMonth()
        && date.getFullYear() === n.getFullYear();
  }

  function fmtDateLabel(date) {
    if (isToday(date)) return '';
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowDay = new Date(tomorrow.getFullYear(), tomorrow.getMonth(), tomorrow.getDate());
    const checkDay    = new Date(date.getFullYear(),     date.getMonth(),     date.getDate());
    if (checkDay.getTime() === tomorrowDay.getTime()) return 'завтра ';
    return date.toLocaleDateString('uk-UA', {day: 'numeric', month: 'short'}) + ' ';
  }

  function showTimeMsg(type, msg) {
    const el = document.getElementById('msg-time');
    if (!el) return;
    el.textContent = (type === 'error' ? '⚠️ ' : 'ℹ️ ') + msg;
    el.className = 'field-msg ' + (type === 'error' ? 'err' : 'info');
  }
  function hideTimeMsg() {
    const el = document.getElementById('msg-time');
    if (el) { el.textContent = ''; el.className = 'field-msg'; }
  }

  // Init hidden value from PHP-computed next available time
  if (rtHidden && !rtHidden.value && nextAvailable) {
    rtHidden.value = nextAvailable.time;
  }

  if (timeChipsEl) {
    timeChipsEl.addEventListener('click', (e) => {
      const chip = e.target.closest('.time-chip');
      if (!chip) return;

      document.querySelectorAll('.time-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      hideTimeMsg();

      const offset = chip.dataset.offset;

      if (offset === 'custom') {
        timeCustomEl.classList.add('show');
        rtHidden.value = rtInput ? rtInput.value : '';
        return;
      }

      timeCustomEl.classList.remove('show');

      if (offset === '15') {
        // "Якнайшвидше"
        if (!nextAvailable) {
          showTimeMsg('error', 'Кафе зачинене найближчим часом. Оберіть час вручну.');
          rtHidden.value = '';
          return;
        }
        rtHidden.value = nextAvailable.time;
        if (!nextAvailable.is_today) {
          showTimeMsg('info', 'Кафе відчиняється: ' + nextAvailable.label + ' о ' + nextAvailable.time);
        }
      } else {
        // "+30 хв" or "+1 год"
        const mins = parseInt(offset, 10);
        const target = new Date();
        target.setMinutes(target.getMinutes() + mins);

        const available = getNextOpenTime(target);
        if (!available) {
          showTimeMsg('error', 'Немає доступного часу найближчим часом.');
          rtHidden.value = '';
          return;
        }

        rtHidden.value = fmtTime(available);

        if (!isToday(available)) {
          showTimeMsg('info', 'Найближчий час: ' + fmtDateLabel(available) + fmtTime(available));
        }
      }
    });
  }

  if (rtInput) {
    rtInput.addEventListener('change', function () {
      rtHidden.value = this.value;
      validateTime();
    });
  }

  /* ────────────────────────────────────────────────
     4. REAL-TIME VALIDATION
  ──────────────────────────────────────────────── */
  function setField(fgId, msgId, state, text) {
    const fg  = document.getElementById(fgId);
    const msg = document.getElementById(msgId);
    if (!fg) return;
    fg.classList.remove('co-valid', 'co-error');
    if (state === 'valid') fg.classList.add('co-valid');
    if (state === 'error') fg.classList.add('co-error');
    if (msg) {
      msg.textContent = text || '';
      msg.className = 'field-msg ' + (state === 'valid' ? 'ok' : state === 'error' ? 'err' : '');
    }
  }

  function validateName(inputId, fgId, msgId) {
    const inp = document.getElementById(inputId);
    if (!inp) return true;
    const v = inp.value.trim();
    if (v.length < 2) {
      setField(fgId, msgId, 'error', 'Мінімум 2 символи');
      return false;
    }
    setField(fgId, msgId, 'valid', '');
    return true;
  }

  function validatePhone() {
    if (!phoneInput) return true;
    const v = phoneInput.value;
    const ok = /^\+38 \(0\d{2}\) \d{3}-\d{2}-\d{2}$/.test(v);
    if (!ok) {
      setField('fg-phone', 'msg-phone', 'error', 'Формат: +38 (0XX) XXX-XX-XX');
      return false;
    }
    setField('fg-phone', 'msg-phone', 'valid', '');
    return true;
  }

  function validateTime() {
    const val = rtHidden ? rtHidden.value : '';
    if (!val) {
      setField('fg-time', 'msg-time', 'error', 'Оберіть час отримання');
      return false;
    }
    setField('fg-time', 'msg-time', 'valid', '');
    return true;
  }

  function validatePayment() {
    const chosen = document.querySelector('input[name="payment"]:checked');
    const msg = document.getElementById('msg-payment');
    if (!chosen) {
      if (msg) { msg.textContent = 'Оберіть спосіб оплати'; msg.className = 'field-msg err'; }
      return false;
    }
    if (msg) { msg.textContent = ''; msg.className = 'field-msg'; }
    return true;
  }

  // Attach blur listeners
  const fnEl = document.getElementById('first_name');
  const lnEl = document.getElementById('last_name');
  if (fnEl) fnEl.addEventListener('blur', () => validateName('first_name', 'fg-first', 'msg-first'));
  if (lnEl) lnEl.addEventListener('blur', () => validateName('last_name',  'fg-last',  'msg-last'));
  if (phoneInput) phoneInput.addEventListener('blur', validatePhone);
  const liqpayInfo = document.getElementById('liqpayInfo');
  const checkoutBtnEl = document.getElementById('checkoutBtn');

  function updatePaymentUI() {
    const chosen = document.querySelector('input[name="payment"]:checked');
    const isCard = chosen && chosen.value === 'card_online';

    // Toggle LiqPay info block
    if (liqpayInfo) {
      liqpayInfo.classList.toggle('show', isCard);
    }
    // Change button text
    if (checkoutBtnEl) {
      checkoutBtnEl.textContent = isCard
        ? 'Перейти до оплати →'
        : 'Оформити замовлення';
    }
  }

  document.querySelectorAll('input[name="payment"]').forEach(r => {
    r.addEventListener('change', () => { validatePayment(); updatePaymentUI(); });
  });

  // Run on load in case payment is pre-selected
  updatePaymentUI();

  // Textarea: floating label + auto-grow (no resize handle)
  const commentEl = document.getElementById('comment');
  if (commentEl) {
    function syncComment() {
      if (commentEl.value.trim()) commentEl.classList.add('has-value');
      else commentEl.classList.remove('has-value');
    }
    function growComment() {
      commentEl.style.height = 'auto';
      commentEl.style.height = commentEl.scrollHeight + 'px';
    }
    commentEl.addEventListener('input', () => { syncComment(); growComment(); });
    syncComment();
    growComment();
  }

  /* ────────────────────────────────────────────────
     5. FORM SUBMIT
  ──────────────────────────────────────────────── */
  const checkoutForm = document.getElementById('checkoutForm');
  const checkoutBtn  = document.getElementById('checkoutBtn');

  if (checkoutForm) {
    checkoutForm.addEventListener('submit', (e) => {
      const ok = [
        validateName('first_name', 'fg-first', 'msg-first'),
        validateName('last_name',  'fg-last',  'msg-last'),
        validatePhone(),
        validatePayment(),
      ].every(Boolean);

      if (!ok) {
        e.preventDefault();
        // Scroll to first error
        const firstErr = document.querySelector('.co-error input, .co-error textarea');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }

      // Refresh time right before submit in case time has passed
      const activeChip = document.querySelector('.time-chip.active');
      if (activeChip && activeChip.dataset.offset !== 'custom') {
        const off = parseInt(activeChip.dataset.offset, 10);
        if (off === 15) {
          if (nextAvailable) rtHidden.value = nextAvailable.time;
        } else {
          const target = new Date();
          target.setMinutes(target.getMinutes() + off);
          const snapped = getNextOpenTime(target);
          if (snapped) rtHidden.value = fmtTime(snapped);
        }
      }

      if (checkoutBtn) {
        checkoutBtn.classList.add('loading');
        checkoutBtn.textContent = '';
      }
    });
  }

  /* ────────────────────────────────────────────────
     6. BFCACHE FIX
     If browser restores this page from cache (e.g. after back button),
     redirect to cart immediately — cart is likely empty after an order.
  ──────────────────────────────────────────────── */
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      window.location.replace('cart.php');
    }
  });

})();
</script>
</body>
</html>
