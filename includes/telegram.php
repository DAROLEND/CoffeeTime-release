<?php
require_once __DIR__ . '/env.php';

define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
define('TELEGRAM_CHAT_ID',   getenv('TELEGRAM_CHAT_ID')   ?: '');

if (!function_exists('send_telegram')) {
function send_telegram(string $message, string $parseMode = 'HTML'): mixed {
    if (!TELEGRAM_BOT_TOKEN) return false;

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

    if ($err) {
        error_log('[Telegram] curl error ' . $err);
        return false;
    }

    return json_decode($result, true);
}
}

if (!function_exists('notify_new_order')) {
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

if (!function_exists('notify_order_from_db')) {
// Used by liqpay_callback.php after successful card payment
function notify_order_from_db(int $orderId, mysqli $conn): void {
    $stmt = $conn->prepare(
        "SELECT customer_name, customer_surname, phone, ready_time, payment_method, total
         FROM orders WHERE order_id = ?"
    );
    if (!$stmt) return;
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) return;

    $allowed = ['coffee_items','fast_food_items','pizza_items','mini_pizza_items',
                'cold_drink_items','dessert_items','sushi_items','sushi_sets',
                'salad_items','cake_items','ice_cream_items'];

    $siStmt = $conn->prepare(
        "SELECT product_id, category, quantity, price FROM order_items WHERE order_id = ?"
    );
    $siStmt->bind_param('i', $orderId);
    $siStmt->execute();
    $rows = $siStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $siStmt->close();

    $items = [];
    foreach ($rows as $row) {
        $cat  = $row['category'];
        $pid  = (int)$row['product_id'];
        $name = '—';
        if (in_array($cat, $allowed, true)) {
            $s = $conn->prepare("SELECT name AS nm FROM `$cat` WHERE id=?");
            if ($s) {
                $s->bind_param('i', $pid);
                $s->execute();
                $p = $s->get_result()->fetch_assoc();
                $s->close();
                $name = $p['nm'] ?? '—';
            }
        }
        $items[] = [
            'name'     => $name,
            'quantity' => (int)$row['quantity'],
            'price'    => (float)$row['price'],
        ];
    }

    notify_new_order(
        $orderId,
        $order['customer_name']    ?? '',
        $order['customer_surname'] ?? '',
        $order['phone']            ?? '',
        $order['ready_time']       ?? '',
        $order['payment_method']   ?? '',
        (float)$order['total'],
        $items
    );
}
}
