<?php
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/liqpay.php';
require_once __DIR__ . '/includes/telegram.php';

header('Content-Type: text/plain; charset=utf-8');

$data      = $_POST['data']      ?? '';
$signature = $_POST['signature'] ?? '';

if (!$data || !$signature) {
    http_response_code(400);
    exit('Missing data or signature');
}

$liqpay = new LiqPay(LIQPAY_PUBLIC_KEY, LIQPAY_PRIVATE_KEY);

if (!$liqpay->verify_signature($data, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

$resp = $liqpay->decode_data_str($data);

$liqpayOrderId = $resp->order_id ?? '';   // e.g. "coffeetime_42"
$status        = $resp->status   ?? '';
$amount        = $resp->amount   ?? 0;

// Orders are prefixed with 'coffeetime_' to avoid ID collisions in LiqPay dashboard
$orderId = (int)str_replace('coffeetime_', '', $liqpayOrderId);
if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order_id');
}

$paymentStatus = 'pending';
$orderStatus   = null;

if (in_array($status, ['success', 'sandbox'], true)) {
    $paymentStatus = 'paid';
    $orderStatus   = 'new';
} elseif (in_array($status, ['failure', 'error'], true)) {
    $paymentStatus = 'failed';
    $orderStatus   = 'cancelled';
} elseif ($status === 'reversed') {
    $paymentStatus = 'failed';
}

if ($orderStatus) {
    $stmt = $conn->prepare(
        "UPDATE orders
         SET payment_status = ?, status = ?, paid_at = ?
         WHERE order_id = ?"
    );
    if ($stmt) {
        $paidAt = ($paymentStatus === 'paid') ? date('Y-m-d H:i:s') : null;
        $stmt->bind_param('sssi', $paymentStatus, $orderStatus, $paidAt, $orderId);
        $stmt->execute();
        $stmt->close();
    }
} else {
    $stmt = $conn->prepare(
        "UPDATE orders SET payment_status = ? WHERE order_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('si', $paymentStatus, $orderId);
        $stmt->execute();
        $stmt->close();
    }
}

if ($paymentStatus === 'paid') {
    notify_order_from_db($orderId, $conn);
}

http_response_code(200);
echo 'OK';
