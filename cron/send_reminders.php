#!/usr/bin/env php
<?php
/**
 * Coffee Time — Reminder cron job
 * Run every 15 minutes:
 *   * * * * /usr/bin/php /path/to/CoffeeTime-release/cron/send_reminders.php >> /tmp/ct_reminders.log 2>&1
 * (add to crontab with: crontab -e)
 *
 * Finds pending reminders with send_at <= NOW() and sends them.
 */

define('ROOT', dirname(__DIR__));

require_once ROOT . '/includes/env.php';
load_env(ROOT . '/.env');

require_once ROOT . '/db/db.php';
require_once ROOT . '/includes/telegram.php';
require_once ROOT . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$tz = new DateTimeZone('Europe/Kiev');
date_default_timezone_set('Europe/Kiev');

// ── Fetch due reminders ──────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT r.id, r.order_id, r.type, r.send_at,
           o.customer_name, o.customer_surname, o.customer_email,
           o.phone, o.ready_time, o.total, o.payment_method, o.order_type
    FROM order_reminders r
    JOIN orders o ON o.order_id = r.order_id
    WHERE r.status = 'pending'
      AND r.send_at <= NOW()
    ORDER BY r.send_at
    LIMIT 50
");
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($rows)) {
    echo date('Y-m-d H:i:s') . " — no reminders due\n";
    exit;
}

echo date('Y-m-d H:i:s') . " — processing " . count($rows) . " reminder(s)\n";

foreach ($rows as $r) {
    $success = false;
    $failReason = null;

    try {
        if ($r['type'] === 'telegram_admin') {
            $success = send_reminder_telegram($r);
        } else {
            $success = send_reminder_email($r);
        }
    } catch (Throwable $e) {
        $failReason = substr($e->getMessage(), 0, 255);
        error_log('[Reminder] ' . $r['id'] . ': ' . $e->getMessage());
    }

    // Mark as sent or failed
    $status = $success ? 'sent' : 'failed';
    $now    = date('Y-m-d H:i:s');
    $upd = $conn->prepare(
        "UPDATE order_reminders SET status=?, sent_at=?, fail_reason=? WHERE id=?"
    );
    $upd->bind_param('sssi', $status, $now, $failReason, $r['id']);
    $upd->execute();
    $upd->close();

    echo "  [{$r['id']}] order #{$r['order_id']} {$r['type']} → $status\n";
}

// ── Telegram to admin ────────────────────────────────────────────────────────
function send_reminder_telegram(array $r): bool {
    $name     = trim($r['customer_name'] . ' ' . $r['customer_surname']);
    $phone    = $r['phone'];
    $pickup   = format_pickup($r['ready_time']);
    $total    = number_format((float)$r['total'], 2) . ' ₴';
    $orderId  = $r['order_id'];

    // Detect how far away pickup is for context message
    $pickupDt = new DateTime($r['ready_time'] . ':00', new DateTimeZone('Europe/Kiev'));
    $now      = new DateTime('now', new DateTimeZone('Europe/Kiev'));
    $diff     = $pickupDt->diff($now);
    $hoursLeft = ($diff->days * 24) + $diff->h;

    if ($hoursLeft <= 3) {
        $urgency = '🔴 <b>Скоро!</b> Починайте готувати';
    } elseif ($hoursLeft <= 6) {
        $urgency = '🟡 Нагадування — сьогодні';
    } else {
        $urgency = '🔔 Нагадування — завтра';
    }

    $msg = "$urgency\n\n"
         . "📦 Замовлення <b>#$orderId</b>\n"
         . "👤 $name\n"
         . "📞 <a href=\"tel:$phone\">$phone</a>\n"
         . "⏰ Готовність: <b>$pickup</b>\n"
         . "💰 Сума: $total";

    $result = send_telegram($msg);
    return $result !== false;
}

// ── Email to customer ────────────────────────────────────────────────────────
function send_reminder_email(array $r): bool {
    if (empty($r['customer_email'])) return false;

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = getenv('MAIL_HOST')     ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('MAIL_USERNAME') ?: '';
    $mail->Password   = getenv('MAIL_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)(getenv('MAIL_PORT') ?: 587);
    $mail->CharSet    = 'UTF-8';

    $fromEmail = getenv('MAIL_FROM')      ?: getenv('MAIL_USERNAME');
    $fromName  = getenv('MAIL_FROM_NAME') ?: 'Coffee Time';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($r['customer_email'], trim($r['customer_name'] . ' ' . $r['customer_surname']));

    $pickup  = format_pickup($r['ready_time']);
    $name    = htmlspecialchars($r['customer_name']);
    $orderId = (int)$r['order_id'];

    // Detect context for subject line
    $pickupDt  = new DateTime($r['ready_time'] . ':00', new DateTimeZone('Europe/Kiev'));
    $now       = new DateTime('now', new DateTimeZone('Europe/Kiev'));
    $hoursLeft = ($pickupDt->getTimestamp() - $now->getTimestamp()) / 3600;

    if ($hoursLeft <= 3) {
        $subject = "☕ Ваше замовлення №$orderId готується — до зустрічі!";
        $headline = 'Зовсім скоро!';
        $body = "Ваше замовлення вже готується. Чекаємо вас о <strong>$pickup</strong> 🎉";
    } else {
        $subject = "⏰ Нагадування про замовлення №$orderId в Coffee Time";
        $headline = 'Нагадуємо про ваше замовлення';
        $body = "Не забудьте — ваше замовлення заплановано на <strong>$pickup</strong>.<br>"
              . "Ми почнемо готувати заздалегідь, щоб усе було свіжим саме до вашого приходу.";
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = email_template($name, $headline, $body, $orderId, $pickup);
    $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));

    $mail->send();
    return true;
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function format_pickup(string $readyTime): string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $readyTime)) {
        return $readyTime; // fallback for old format
    }
    $dt  = new DateTime($readyTime . ':00', new DateTimeZone('Europe/Kiev'));
    $day = $dt->format('d.m.Y');
    $t   = $dt->format('H:i');
    // Make it human-readable
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
