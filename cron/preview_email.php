<?php
/**
 * Temporary preview — delete after review
 * Open: http://localhost/CoffeeTime-release/cron/preview_email.php
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/includes/env.php';
load_env(ROOT . '/.env');

function format_pickup(string $readyTime): string {
    $dt  = new DateTime($readyTime . ':00', new DateTimeZone('Europe/Kiev'));
    $day = $dt->format('d.m.Y');
    $t   = $dt->format('H:i');
    $today    = (new DateTime('now', new DateTimeZone('Europe/Kiev')))->format('Y-m-d');
    $tomorrow = (new DateTime('+1 day', new DateTimeZone('Europe/Kiev')))->format('Y-m-d');
    $dateStr  = $dt->format('Y-m-d');
    if ($dateStr === $today)    return "сьогодні о $t";
    if ($dateStr === $tomorrow) return "завтра о $t";
    return "$day о $t";
}

function email_template(string $name, string $headline, string $body, int $orderId, string $pickup): string {
    $phone     = htmlspecialchars(getenv('CAFE_PHONE')     ?: '');
    $instagram = htmlspecialchars(getenv('CAFE_INSTAGRAM') ?: '');
    $fromName  = htmlspecialchars(getenv('MAIL_FROM_NAME') ?: 'Coffee Time');

    $contactLine = '';
    if ($phone) {
        $contactLine .= "<a href=\"tel:$phone\" style=\"color:#b07840;text-decoration:none;\">$phone</a>";
    }
    if ($instagram) {
        if ($contactLine) $contactLine .= ' &nbsp;·&nbsp; ';
        $contactLine .= "<a href=\"$instagram\" style=\"color:#b07840;text-decoration:none;\">Instagram</a>";
    }
    $footerText = $contactLine
        ? "Маєте питання? $contactLine ☕"
        : "Маєте питання? Відповімо на будь-який запит ☕";

    return <<<HTML
<!DOCTYPE html>
<html lang="uk">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#faf7f2;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f2;padding:32px 16px;">
  <tr><td align="center">
    <table width="100%" style="max-width:520px;background:#fff;border-radius:16px;border:1px solid #f0e8df;overflow:hidden;">
      <!-- Header -->
      <tr><td style="background:#FFC107;padding:24px 32px;text-align:center;">
        <p style="margin:0;font-size:28px;">☕</p>
        <p style="margin:6px 0 0;font-size:20px;font-weight:700;color:#5a2d00;">$fromName</p>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:32px;">
        <p style="margin:0 0 8px;font-size:22px;font-weight:700;color:#2c1810;">$headline</p>
        <p style="margin:0 0 20px;font-size:15px;color:#666;line-height:1.6;">Привіт, $name! $body</p>
        <!-- Time badge -->
        <table cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
          <tr><td style="background:#fff8e1;border:1px solid #ffe082;border-radius:50px;padding:10px 28px;
                         font-size:15px;font-weight:700;color:#8B4513;white-space:nowrap;">
            ⏰ $pickup
          </td></tr>
        </table>
        <p style="margin:0;font-size:13px;color:#aaa;text-align:center;">Замовлення №$orderId</p>
      </td></tr>
      <!-- Footer -->
      <tr><td style="padding:16px 32px;border-top:1px solid #f0e8df;text-align:center;">
        <p style="margin:0;font-size:12px;color:#bbb;">$footerText</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Switch variant via ?v=1/2/3 ───────────────────────────────────────────────
$v = (int)($_GET['v'] ?? 1);

$name    = 'Іван';
$orderId = 42;
$pickup  = format_pickup(date('Y-m-d H:i', strtotime('+2 hours')));  // today ~2h

if ($v === 1) {
    // "Скоро" — до 3 годин
    $headline = 'Зовсім скоро!';
    $body = "Ваше замовлення вже готується. Чекаємо вас о <strong>$pickup</strong> 🎉";
} elseif ($v === 2) {
    // Звичайне нагадування
    $pickup  = format_pickup(date('Y-m-d H:i', strtotime('+1 day 10:00')));
    $headline = 'Нагадуємо про ваше замовлення';
    $body = "Не забудьте — ваше замовлення заплановано на <strong>$pickup</strong>.<br>"
          . "Ми почнемо готувати заздалегідь, щоб усе було свіжим саме до вашого приходу.";
} else {
    // Завтра вранці
    $pickup  = format_pickup(date('Y-m-d', strtotime('+2 days')) . ' 09:30');
    $headline = 'Нагадуємо про ваше замовлення';
    $body = "Не забудьте — ваше замовлення заплановано на <strong>$pickup</strong>.<br>"
          . "Ми почнемо готувати заздалегідь, щоб усе було свіжим саме до вашого приходу.";
}

echo email_template($name, $headline, $body, $orderId, $pickup);
