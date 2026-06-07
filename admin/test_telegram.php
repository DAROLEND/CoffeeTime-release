<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_super();

$token  = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$chatId = getenv('TELEGRAM_CHAT_ID')   ?: '';

$result = null;
$sent   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$token || !$chatId) {
        $result = ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN або TELEGRAM_CHAT_ID не встановлені'];
    } else {
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['chat_id' => $chatId, 'text' => '✅ Тест Coffee Time — Telegram працює!'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_errno($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($err) {
            $result = ['ok' => false, 'description' => 'curl error: ' . $err];
        } else {
            $result = json_decode($resp, true) ?: ['ok' => false, 'description' => 'Invalid JSON: ' . $resp];
        }
        $sent = !empty($result['ok']);
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<title>Тест Telegram</title>
<style>
  body { font-family: sans-serif; max-width: 520px; margin: 60px auto; padding: 0 20px; }
  h2 { margin-bottom: 20px; }
  .field { margin-bottom: 12px; font-size: 14px; }
  .val { font-family: monospace; background: #f5f5f5; padding: 4px 8px; border-radius: 4px; }
  .ok { color: #1a7f37; font-weight: 700; }
  .err { color: #d1242f; font-weight: 700; }
  button { padding: 10px 24px; background: #FFC107; border: none; border-radius: 8px;
           font-size: 15px; font-weight: 700; cursor: pointer; color: #5a2d0c; }
  pre { background: #f5f5f5; padding: 12px; border-radius: 8px; font-size: 12px; overflow: auto; }
  .back { display: inline-block; margin-top: 20px; font-size: 13px; color: #888; text-decoration: none; }
</style>
</head>
<body>
<h2>🔔 Тест Telegram</h2>

<div class="field">
  BOT_TOKEN:
  <span class="val">
    <?= $token ? substr($token, 0, 10) . '...' . substr($token, -4) : '<span class="err">НЕ ВСТАНОВЛЕНО</span>' ?>
  </span>
</div>
<div class="field">
  CHAT_ID:
  <span class="val">
    <?= $chatId ? htmlspecialchars($chatId) : '<span class="err">НЕ ВСТАНОВЛЕНО</span>' ?>
  </span>
</div>

<form method="post" style="margin-top: 24px;">
  <button type="submit">Надіслати тестове повідомлення</button>
</form>

<?php if ($result !== null): ?>
<div style="margin-top: 24px;">
  <?php if ($sent): ?>
    <p class="ok">✅ Повідомлення надіслано успішно!</p>
  <?php else: ?>
    <p class="err">❌ Помилка:</p>
    <pre><?= htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
  <?php endif; ?>
</div>
<?php endif; ?>

<a href="dashboard.php" class="back">← До дашборду</a>
</body>
</html>
