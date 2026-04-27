<?php
/**
 * Coffee Time — Telegram order notifications
 *
 * Setup:
 *  1. Message @BotFather → /newbot → copy the token into .env
 *  2. Start a chat with your new bot (send it any message)
 *  3. Open https://api.telegram.org/bot<TOKEN>/getUpdates
 *  4. Find  "chat":{"id": ...}  — put that in .env as TELEGRAM_CHAT_ID
 */

require_once __DIR__ . '/env.php';

define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
define('TELEGRAM_CHAT_ID',   getenv('TELEGRAM_CHAT_ID')   ?: '');

/**
 * Send a plain or HTML-formatted message to the configured chat.
 * Returns the decoded API response on success, false on curl failure.
 */
if (!function_exists('send_telegram')) {
function send_telegram(string $message, string $parseMode = 'HTML'): mixed {
    if (TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
        return false; // Not configured yet — fail silently
    }

    $url  = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id'    => TELEGRAM_CHAT_ID,
        'text'       => $message,
        'parse_mode' => $parseMode,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $result = curl_exec($ch);
    $err    = curl_errno($ch);
    curl_close($ch);

    if ($err) {
        error_log('[Telegram] curl error ' . $err);
        return false;
    }

    return json_decode($result, true);
}
}

if (!function_exists('notify_new_order')) {
/**
 * Build and send the "new order" notification.
 *
 * @param int    $orderId
 * @param string $firstName
 * @param string $lastName
 * @param string $phone
 * @param string $readyTime  HH:MM
 * @param string $payment    cash_on_pickup | card_online
 * @param float  $total
 * @param array  $items      Each: ['name'=>string, 'quantity'=>int, 'price'=>float]
 */
function notify_new_order(
    int    $orderId,
    string $firstName,
    string $lastName,
    string $phone,
    string $readyTime,
    string $payment,
    float  $total,
    array  $items
): void {
    $payLabel = ($payment === 'card_online') ? '💳 Картка (онлайн)' : '💵 Готівка';

    $itemLines = '';
    foreach ($items as $it) {
        $itemLines .= sprintf(
            "  • %s × %d — %s ₴\n",
            htmlspecialchars($it['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            (int)$it['quantity'],
            number_format((float)$it['price'] * (int)$it['quantity'], 0, ',', ' ')
        );
    }

    $message = sprintf(
        "🛍 <b>Нове замовлення #%d</b>\n\n"
        . "👤 <b>%s %s</b>\n"
        . "📞 %s\n"
        . "🕐 Час готовності: <b>%s</b>\n"
        . "💳 Оплата: %s\n\n"
        . "📋 <b>Товари:</b>\n%s\n"
        . "💰 <b>Сума: %s ₴</b>",
        $orderId,
        htmlspecialchars($firstName, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        htmlspecialchars($lastName,  ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        htmlspecialchars($phone, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        htmlspecialchars($readyTime, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        $payLabel,
        $itemLines,
        number_format($total, 0, ',', ' ')
    );

    send_telegram($message);
}
}
