<?php
/**
 * LiqPay Server Callback (server_url)
 * LiqPay sends a POST request here after every payment event.
 * Must be accessible from the internet (won't work on localhost).
 *
 * Verify at: https://www.liqpay.ua/documentation/api/callback
 */
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/liqpay.php';

header('Content-Type: text/plain; charset=utf-8');

/* ── Read POST ── */
$data      = $_POST['data']      ?? '';
$signature = $_POST['signature'] ?? '';

if (!$data || !$signature) {
    http_response_code(400);
    exit('Missing data or signature');
}

/* ── Verify signature ── */
$liqpay = new LiqPay(LIQPAY_PUBLIC_KEY, LIQPAY_PRIVATE_KEY);

if (!$liqpay->verify_signature($data, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

/* ── Decode response ── */
$resp = $liqpay->decode_data_str($data);

$liqpayOrderId = $resp->order_id ?? '';   // e.g. "coffeetime_42"
$status        = $resp->status   ?? '';
$amount        = $resp->amount   ?? 0;

/* ── Extract our DB order_id ── */
// We prefix with 'coffeetime_' when creating the payment
$orderId = (int)str_replace('coffeetime_', '', $liqpayOrderId);
if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order_id');
}

/* ── Update orders table ── */
// Map LiqPay status → our payment_status
// Possible LiqPay statuses: success, sandbox, failure, error, wait_accept, reversed, etc.
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

/* ── Try to update payment_status (graceful if column missing) ── */
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
    // Only update payment_status
    $stmt = $conn->prepare(
        "UPDATE orders SET payment_status = ? WHERE order_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('si', $paymentStatus, $orderId);
        $stmt->execute();
        $stmt->close();
    }
}

http_response_code(200);
echo 'OK';
