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

/* ── Verify order exists in DB ── */
$stmt = $conn->prepare("SELECT order_id, total FROM orders WHERE order_id = ?");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: pages/cart.php');
    exit;
}

/* ── Build LiqPay data + signature ── */
$liqpay    = new LiqPay(LIQPAY_PUBLIC_KEY, LIQPAY_PRIVATE_KEY);
$liqpayOid = 'coffeetime_' . $orderId;
$amount    = number_format((float)$order['total'], 2, '.', '');

$params = [
    'action'      => 'pay',
    'amount'      => $amount,
    'currency'    => 'UAH',
    'description' => 'Замовлення у Coffee Time #' . $orderId,
    'order_id'    => $liqpayOid,
    'version'     => '3',
    'result_url'  => SITE_URL . '/pages/payment_success.php',
    'server_url'  => SITE_URL . '/liqpay_callback.php',
    'language'    => 'uk',
];

if (LIQPAY_SANDBOX) {
    $params['sandbox'] = 1;
}

$data      = $liqpay->cnb_data($params);
$signature = $liqpay->cnb_signature($data);

/* ── Store in session so pending page can re-offer the button ── */
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
    .redirect-box { text-align: center; padding: 48px 32px; }
    .redirect-spinner {
      width: 52px; height: 52px;
      border: 3px solid #f0e8df;
      border-top-color: #FFC107;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin: 0 auto 24px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    h2 { font-size: 1.4rem; color: #2c1810; margin-bottom: 10px; }
    p  { font-size: 14px; color: #999; line-height: 1.6; }
    .liqpay-logo { margin-top: 28px; font-size: 13px; color: #bbb; }
    .liqpay-logo strong { color: #4ca800; font-weight: 700; }
  </style>
</head>
<body>

  <!-- Same-tab form submit — no user click required -->
  <form id="liqpayForm"
        method="POST"
        action="https://www.liqpay.ua/api/3/checkout"
        accept-charset="utf-8">
    <input type="hidden" name="data"      value="<?= h($data) ?>">
    <input type="hidden" name="signature" value="<?= h($signature) ?>">
  </form>

  <div class="redirect-box">
    <div class="redirect-spinner"></div>
    <h2>Переходимо до оплати…</h2>
    <p>Ви будете перенаправлені на захищену сторінку LiqPay.<br>
       Будь ласка, не закривайте вкладку.</p>
    <div class="liqpay-logo">Powered by <strong>LiqPay</strong></div>
  </div>

  <script>
    // Replace THIS page in the browser history with the pending-status page.
    // Result: after LiqPay, pressing "Back" goes to payment_pending, not here.
    history.replaceState(null, '', <?= json_encode($pendingUrl) ?>);

    // Auto-submit to LiqPay in the same tab — no click needed
    window.addEventListener('load', function () {
      document.getElementById('liqpayForm').submit();
    });
  </script>
</body>
</html>
