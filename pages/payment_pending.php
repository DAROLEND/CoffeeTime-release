<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/config.php';

$orderId = (int)($_GET['order_id'] ?? $_SESSION['pending_order_id'] ?? 0);

if (!$orderId) {
    header('Location: cart.php');
    exit;
}

$checkUrl   = SITE_URL . '/check_payment_status.php?order_id=' . $orderId;
$successUrl = SITE_URL . '/pages/payment_success.php?order_id=' . $orderId;
$failureUrl = SITE_URL . '/pages/payment_failure.php?order_id=' . $orderId;
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Очікуємо оплату — Coffee Time</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/footer.css">
  <style>
    .pending-wrap {
      max-width: 520px;
      margin: 80px auto 60px;
      text-align: center;
      padding: 0 24px;
    }
    .pending-icon {
      width: 80px; height: 80px;
      border-radius: 50%;
      background: #fff8e1;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 28px;
      font-size: 38px;
    }
    .pending-wrap h1 {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 26px;
      color: #2c2c2a;
      margin-bottom: 12px;
    }
    .pending-wrap p {
      font-size: 15px;
      color: #666;
      line-height: 1.7;
      margin-bottom: 8px;
    }
    .pending-wrap .sub {
      font-size: 13px;
      color: #aaa;
      margin-bottom: 36px;
    }
    .pulse-dots span {
      display: inline-block;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #FFC107;
      margin: 0 3px;
      animation: pulse 1.2s infinite ease-in-out;
    }
    .pulse-dots span:nth-child(2) { animation-delay: .2s; }
    .pulse-dots span:nth-child(3) { animation-delay: .4s; }
    @keyframes pulse {
      0%, 80%, 100% { transform: scale(.75); opacity: .5; }
      40%            { transform: scale(1.1); opacity: 1; }
    }
    .btn-reopen {
      display: block;
      background: #FFC107;
      color: #5a2d0c;
      border: none;
      padding: 14px 32px;
      border-radius: 25px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      width: 100%;
      margin-bottom: 16px;
      text-decoration: none;
    }
    .btn-reopen:hover { background: #ffb300; }
    .btn-back {
      font-size: 14px;
      color: #8B4513;
      text-decoration: none;
      display: inline-block;
      margin-top: 4px;
    }
    .btn-back:hover { text-decoration: underline; }
    #statusMsg {
      font-size: 13px;
      color: #1565c0;
      background: #e3f2fd;
      border-radius: 8px;
      padding: 8px 14px;
      display: none;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
<?php
$page = 'payment_pending';
include '../includes/header.php';
?>

<main>
  <div class="pending-wrap">

    <div class="pending-icon">💳</div>

    <h1>Очікуємо оплату</h1>

    <p>Вікно оплати LiqPay відкрилось у новій вкладці.</p>
    <p class="sub">Після успішної оплати ця сторінка оновиться автоматично.</p>

    <div class="pulse-dots" style="margin-bottom:32px;">
      <span></span><span></span><span></span>
    </div>

    <div id="statusMsg"></div>

    <!-- "Open again" button — uses data/signature stored in session -->
    <form method="POST"
          action="https://www.liqpay.ua/api/3/checkout"
          accept-charset="utf-8"
          target="_blank"
          style="margin-bottom:16px;">
      <input type="hidden" name="data"      value="<?= h($_SESSION['liqpay_data']      ?? '') ?>">
      <input type="hidden" name="signature" value="<?= h($_SESSION['liqpay_signature'] ?? '') ?>">
      <button type="submit" class="btn-reopen">
        Відкрити вікно оплати ще раз
      </button>
    </form>

    <a href="checkout.php" class="btn-back">← Повернутись до оформлення</a>

  </div>
</main>

<?php include '../includes/footer.php'; ?>

<script>
(function () {
  const CHECK_URL   = <?= json_encode($checkUrl) ?>;
  const SUCCESS_URL = <?= json_encode($successUrl) ?>;
  const FAILURE_URL = <?= json_encode($failureUrl) ?>;
  const statusMsg   = document.getElementById('statusMsg');

  let checkCount = 0;

  const interval = setInterval(async () => {
    checkCount++;
    if (checkCount > 72) {          // stop after ~6 minutes
      clearInterval(interval);
      showMsg('Час очікування вичерпано. Перевірте стан замовлення або зверніться до підтримки.');
      return;
    }

    try {
      const res  = await fetch(CHECK_URL);
      const data = await res.json();

      if (data.status === 'paid' || data.status === 'success') {
        clearInterval(interval);
        showMsg('✅ Оплата підтверджена! Переходимо…');
        setTimeout(() => window.location.href = SUCCESS_URL, 800);

      } else if (data.status === 'failed' || data.status === 'failure' || data.status === 'error') {
        clearInterval(interval);
        showMsg('❌ Оплата не пройшла. Переходимо…');
        setTimeout(() => window.location.href = FAILURE_URL, 800);
      }
    } catch (e) { /* network error — silently retry */ }

  }, 5000);

  function showMsg(text) {
    statusMsg.textContent = text;
    statusMsg.style.display = 'block';
  }
})();
</script>
</body>
</html>
