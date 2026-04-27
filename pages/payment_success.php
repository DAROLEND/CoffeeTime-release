<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/liqpay.php';

/* ── Determine order_id & verify LiqPay signature if data present ── */
$orderId       = 0;
$paymentStatus = 'pending';
$liqpayData    = null;

// LiqPay POSTs data+signature to result_url (same format as callback)
if (!empty($_POST['data']) && !empty($_POST['signature'])) {
    $liqpay = new LiqPay(LIQPAY_PUBLIC_KEY, LIQPAY_PRIVATE_KEY);
    if ($liqpay->verify_signature($_POST['data'], $_POST['signature'])) {
        $liqpayData = $liqpay->decode_data_str($_POST['data']);
        $orderId    = (int)str_replace('coffeetime_', '', $liqpayData->order_id ?? '');
        $st         = $liqpayData->status ?? '';
        if (in_array($st, ['success', 'sandbox'], true)) {
            $paymentStatus = 'paid';
        } elseif (in_array($st, ['failure', 'error'], true)) {
            $paymentStatus = 'failed';
        }
    }
}

// Fallback: get order_id from session
if (!$orderId) {
    $orderId = (int)($_SESSION['pending_order_id'] ?? 0);
}

// If payment failed, redirect to failure page
if ($paymentStatus === 'failed') {
    header('Location: payment_failure.php');
    exit;
}

/* ── Fetch order from DB ── */
$order = null;
if ($orderId > 0) {
    $stmt = $conn->prepare(
        "SELECT order_id, total, customer_name, customer_surname,
                ready_time, payment_method, payment_status, created_at
         FROM orders WHERE order_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Clean up session
unset($_SESSION['pending_order_id'], $_SESSION['pending_order_total']);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Замовлення прийнято — Coffee Time</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/footer.css">
  <link rel="stylesheet" href="../static/css/animations.css">
  <style>
    .success-page-wrap {
      max-width: 560px;
      margin: 48px auto 80px;
      padding: 0 20px;
    }
    .success-card {
      background: #fff;
      border-radius: 20px;
      border: 1px solid #f0e8df;
      padding: 52px 40px 44px;
      box-shadow: 0 4px 28px rgba(139,69,19,0.08);
      text-align: center;
    }
    /* SVG checkmark */
    .sv-icon { margin-bottom: 28px; }
    .sv-svg { width: 88px; height: 88px; }
    .sv-circle {
      stroke: #4caf50; stroke-width: 2; fill: none;
      stroke-dasharray: 157; stroke-dashoffset: 157;
      animation: svDraw 0.6s cubic-bezier(.65,0,.45,1) forwards;
    }
    .sv-check {
      stroke: #4caf50; stroke-width: 3;
      stroke-linecap: round; stroke-linejoin: round; fill: none;
      stroke-dasharray: 48; stroke-dashoffset: 48;
      animation: svDraw 0.35s 0.65s cubic-bezier(.65,0,.45,1) forwards;
    }
    @keyframes svDraw { to { stroke-dashoffset: 0; } }

    .sv-title {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: clamp(1.5rem, 3vw, 2rem);
      color: #2c1810;
      margin: 0 0 10px;
    }
    .sv-sub {
      font-size: 15px;
      color: #888;
      margin: 0 0 28px;
      line-height: 1.6;
    }
    /* Order details grid */
    .sv-details {
      background: #fdf9f5;
      border: 1px solid #f0e8df;
      border-radius: 12px;
      padding: 20px 24px;
      text-align: left;
      margin-bottom: 28px;
    }
    .sv-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 14px;
      color: #666;
      padding: 7px 0;
      border-bottom: 1px solid #f4ede4;
    }
    .sv-row:last-child { border-bottom: none; }
    .sv-row strong { color: #2c2c2a; font-weight: 600; }
    .sv-status-paid {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      color: #4caf50;
      font-weight: 600;
      font-size: 13px;
    }
    .sv-status-pending {
      color: #FFA726;
      font-weight: 600;
      font-size: 13px;
    }
    /* Actions */
    .sv-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      justify-content: center;
    }
    .sv-btn-primary {
      padding: 13px 30px;
      background: #FFC107;
      color: #5a2d00;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 700;
      font-size: 14px;
      box-shadow: 0 4px 12px rgba(255,193,7,0.35);
      transition: background .2s, transform .2s;
    }
    .sv-btn-primary:hover { background: #e6ac00; transform: translateY(-1px); }
    .sv-btn-outline {
      padding: 13px 30px;
      border: 1.5px solid #d4a96a;
      color: #8B4513;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: background .2s;
    }
    .sv-btn-outline:hover { background: #fdf6ee; }

    /* Confetti */
    .confetti-piece {
      position: fixed; pointer-events: none; z-index: 9999; border-radius: 2px;
      animation: coConfetti var(--dur,2.4s) var(--delay,0s) ease-in both;
    }
    @keyframes coConfetti {
      0%   { transform: translateY(-30px) rotate(0deg); opacity: 1; }
      80%  { opacity: 1; }
      100% { transform: translateY(105vh) rotate(640deg); opacity: 0; }
    }
  </style>
  <script defer src="../static/js/animations.js"></script>
</head>
<body>
<?php include '../includes/header.php'; ?>

<main class="success-page-wrap">
  <div class="success-card">

    <!-- Animated checkmark -->
    <div class="sv-icon">
      <svg class="sv-svg" viewBox="0 0 52 52">
        <circle class="sv-circle" cx="26" cy="26" r="25"/>
        <path  class="sv-check"  d="M14 27l7.5 7.5 16.5-17"/>
      </svg>
    </div>

    <h2 class="sv-title">Замовлення прийнято! 🎉</h2>
    <p class="sv-sub">
      Дякуємо за замовлення в Coffee Time.<br>
      Ми вже розпочали готувати для вас ☕
    </p>

    <?php if ($order): ?>
    <div class="sv-details">
      <div class="sv-row">
        <span>Номер замовлення</span>
        <strong>#<?= (int)$order['order_id'] ?></strong>
      </div>
      <div class="sv-row">
        <span>Сума оплати</span>
        <strong><?= number_format((float)$order['total'], 0, ',', ' ') ?> ₴</strong>
      </div>
      <div class="sv-row">
        <span>Статус оплати</span>
        <?php
          $ps = $order['payment_status'] ?? 'pending';
          if ($ps === 'paid'):
        ?>
          <span class="sv-status-paid">✓ Оплачено</span>
        <?php elseif ($order['payment_method'] === 'cash_on_pickup'): ?>
          <span class="sv-status-pending">Готівка при отриманні</span>
        <?php else: ?>
          <span class="sv-status-pending">Очікує підтвердження</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($order['ready_time'])): ?>
      <div class="sv-row">
        <span>Час готовності</span>
        <strong>🕐 <?= htmlspecialchars($order['ready_time']) ?></strong>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="sv-actions">
      <a href="index.php"  class="sv-btn-primary">На головну</a>
      <a href="menu.php"   class="sv-btn-outline">Продовжити покупки</a>
    </div>
  </div>
</main>

<?php include '../includes/footer.php'; ?>

<script>
(function () {
  // Confetti
  const colors = ['#FFC107','#FF7043','#66BB6A','#42A5F5','#AB47BC','#FF8A65'];
  for (let i = 0; i < 80; i++) {
    const el  = document.createElement('div');
    el.className = 'confetti-piece';
    const dur   = (1.6 + Math.random() * 1.6).toFixed(2);
    const delay = (Math.random() * 0.8).toFixed(2);
    const size  = (6 + Math.random() * 7).toFixed(1);
    el.style.cssText = [
      `left:${(Math.random()*100).toFixed(1)}vw`,
      `top:-20px`,
      `width:${size}px`,`height:${size}px`,
      `background:${colors[i % colors.length]}`,
      `--dur:${dur}s`,`--delay:${delay}s`,
      `border-radius:${Math.random() > .45 ? '50%' : '2px'}`,
    ].join(';');
    document.body.appendChild(el);
    setTimeout(() => el.remove(), (parseFloat(dur) + parseFloat(delay) + 0.3) * 1000);
  }

  // Remove cart badge
  const badge = document.querySelector('.cart-count');
  if (badge) badge.remove();
})();
</script>
</body>
</html>
