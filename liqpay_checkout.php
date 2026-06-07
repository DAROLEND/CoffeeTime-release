<?php
/**
 * LiqPay Checkout — opens the LiqPay payment page in a NEW TAB,
 * then redirects the current tab to the pending-payment waiting page.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/error_handler.php';
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/liqpay.php';

$orderId = (int)($_SESSION['pending_order_id']    ?? 0);
$total   = (float)($_SESSION['pending_order_total'] ?? 0);

if (!$orderId || $total <= 0) {
    header('Location: pages/cart.php');
    exit;
}

$stmt = $conn->prepare("SELECT order_id, total FROM orders WHERE order_id = ?");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: pages/cart.php');
    exit;
}

$liqpayReady = LIQPAY_PUBLIC_KEY && LIQPAY_PRIVATE_KEY && APP_ENV !== 'development';
if (!$liqpayReady) {
    $_SESSION['pending_order_id'] = $orderId;
    $_SESSION['dev_payment_skip'] = true;
    header('Location: pages/payment_success.php');
    exit;
}

$liqpay    = new LiqPay(LIQPAY_PUBLIC_KEY, LIQPAY_PRIVATE_KEY);
$liqpayOid = 'coffeetime_' . $orderId;
$amount    = number_format((float)$order['total'], 2, '.', '');

$isLocalhost = str_contains(SITE_URL, 'localhost')
           || str_contains(SITE_URL, '127.0.0.1')
           || preg_match('#https?://192\.168\.\d+\.\d+#', SITE_URL)
           || preg_match('#https?://10\.\d+\.\d+\.\d+#', SITE_URL)
           || preg_match('#https?://172\.(1[6-9]|2\d|3[01])\.\d+\.\d+#', SITE_URL);

$params = [
    'action'      => 'pay',
    'amount'      => (float)$amount,
    'currency'    => 'UAH',
    'description' => 'Замовлення у Coffee Time #' . $orderId,
    'order_id'    => $liqpayOid,
    'version'     => 3,
    'language'    => 'uk',
];

// LiqPay блокує localhost у result_url та server_url — додаємо тільки на продакшні
if (!$isLocalhost) {
    $params['result_url'] = SITE_URL . '/pages/payment_success.php';
    $params['server_url'] = SITE_URL . '/liqpay_callback.php';
}

if (LIQPAY_SANDBOX) {
    $params['sandbox'] = 1;
}

$data      = $liqpay->cnb_data($params);
$signature = $liqpay->cnb_signature($data);

$_SESSION['liqpay_data']      = $data;
$_SESSION['liqpay_signature'] = $signature;

$pendingUrl = SITE_URL . '/pages/payment_pending.php?order_id=' . urlencode($orderId);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Перехід до оплати — Coffee Time</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      min-height: 100vh;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      background: #fdf8f3;
      font-family: 'Lato', sans-serif;
    }
    .pay-box {
      text-align: center;
      padding: 48px 40px;
      background: #fff;
      border-radius: 20px;
      border: 1px solid #f0e8df;
      box-shadow: 0 4px 28px rgba(139,69,19,0.08);
      max-width: 420px;
      width: 90%;
    }
    .pay-icon { font-size: 48px; margin-bottom: 20px; }
    h2 { font-size: 1.35rem; color: #2c1810; margin-bottom: 10px; }
    .pay-sub { font-size: 14px; color: #999; line-height: 1.6; margin-bottom: 28px; }
    .pay-amount {
      font-size: 2rem; font-weight: 700;
      color: #8B4513; margin-bottom: 28px;
    }
    .pay-btn {
      display: block; width: 100%;
      padding: 16px 40px;
      background: #FFC107; color: #5a2d0c;
      border: none; border-radius: 50px;
      font-size: 16px; font-weight: 700;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(255,193,7,0.4);
      opacity: 0;
      transform: translateY(12px);
      transition: background .2s, transform .35s ease, opacity .35s ease;
      pointer-events: none;
    }
    .pay-btn.visible {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
    }
    .pay-btn:hover { background: #e6ac00; transform: translateY(-2px); }
    .pay-back {
      display: inline-block; margin-top: 16px;
      font-size: 13px; color: #aaa; text-decoration: none;
    }
    .pay-back:hover { color: #8B4513; }
    .liqpay-badge { margin-top: 24px; font-size: 12px; color: #ccc; }
    .liqpay-badge strong { color: #4ca800; }
  </style>
</head>
<body>

  <div class="pay-box">
    <div class="pay-icon">💳</div>
    <h2>Оплата замовлення #<?= (int)$orderId ?></h2>
    <p class="pay-sub" id="paySubText">Переходимо до сторінки оплати…</p>
    <div class="pay-amount"><?= number_format((float)$order['total'], 0, ',', ' ') ?> ₴</div>

    <form id="liqpayForm" method="POST"
          action="https://www.liqpay.ua/api/3/checkout"
          accept-charset="utf-8">
      <input type="hidden" name="data"      value="<?= h($data) ?>">
      <input type="hidden" name="signature" value="<?= h($signature) ?>">
      <button type="submit" class="pay-btn" id="payBtn" style="margin-top:8px;">Перейти до оплати →</button>
    </form>

    <?php if ($isLocalhost): ?>
    <p style="font-size:12px;color:#bbb;margin-top:20px;">
      Після оплати поверніться на сайт вручну:<br>
      <a href="pages/payment_success.php" style="color:#8B4513;">
        → Сторінка підтвердження замовлення
      </a>
    </p>
    <?php endif; ?>
    <?php
        $fromProfile = ($_GET['back'] ?? '') === 'profile';
        $backHref    = $fromProfile ? 'pages/profile.php?tab=orders' : 'pages/checkout.php?cancel_order=' . (int)$orderId;
        $backLabel   = $fromProfile ? '← Повернутися до замовлень' : '← Повернутися до оформлення';
    ?>
    <a href="<?= h($backHref) ?>" class="pay-back"><?= $backLabel ?></a>

    <div class="liqpay-badge">Powered by <strong>LiqPay</strong> · PCI DSS</div>
  </div>

<script>
(function() {
  var form    = document.getElementById('liqpayForm');
  var btn     = document.getElementById('payBtn');
  var subText = document.getElementById('paySubText');
  var orderId = <?= (int)$orderId ?>;
  var stKey   = 'liqpay_sent_' + orderId;
  if (!form) return;

  function showBtn(msg, delay) {
    if (msg && subText) subText.textContent = msg;
    setTimeout(function() {
      if (btn) btn.classList.add('visible');
    }, delay || 0);
  }

  function autoSubmit() {
    var dots = 0;
    var iv = setInterval(function() {
      dots = (dots + 1) % 4;
      if (subText) subText.textContent = 'Переходимо до сторінки оплати' + '.'.repeat(dots);
    }, 400);
    setTimeout(function() {
      clearInterval(iv);
      sessionStorage.setItem(stKey, '1');
      form.submit();
      /* Якщо сабміт заблокований (popup blocker) — кнопка з'являється через 2 сек */
      showBtn('Якщо сторінка оплати не відкрилась — натисніть кнопку нижче.', 2000);
    }, 800);
  }

  window.addEventListener('pageshow', function(e) {
    if (e.persisted) {
      showBtn('Якщо хочете оплатити — натисніть кнопку нижче.', 300);
      return;
    }
    if (sessionStorage.getItem(stKey)) {
      showBtn('Якщо сторінка оплати не відкрилась — натисніть кнопку нижче.', 300);
      return;
    }
    autoSubmit();
  });
})();
</script>
</body>
</html>
