<?php

function schedule_reminders(
    mysqli $conn,
    int    $orderId,
    string $readyTime,
    ?string $customerEmail
): void {
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $readyTime)) return;

    $tz     = new DateTimeZone('Europe/Kiev');
    $now    = new DateTime('now', $tz);
    $pickup = new DateTime($readyTime . ':00', $tz);

    $diffSeconds = $pickup->getTimestamp() - $now->getTimestamp();
    if ($diffSeconds <= 0) return;

    $diffHours = $diffSeconds / 3600;
    $diffDays  = $diffSeconds / 86400;

    $reminders = [];

    // 2 h before — skip if pickup is before 09:00 to avoid early-morning noise
    if ($diffHours >= 3) {
        $twoHourBefore = (clone $pickup)->modify('-2 hours');
        if ((int)$pickup->format('H') >= 9) {
            $reminders[] = ['type' => 'telegram_admin',  'send_at' => clone $twoHourBefore];
            if ($customerEmail) {
                $reminders[] = ['type' => 'email_customer', 'send_at' => clone $twoHourBefore];
            }
        }
    }

    if ($diffDays >= 1) {
        $eveningBefore = (clone $pickup)->setTime(19, 0, 0)->modify('-1 day');
        if ($eveningBefore > $now) {
            $reminders[] = ['type' => 'telegram_admin',  'send_at' => clone $eveningBefore];
            if ($customerEmail) {
                $reminders[] = ['type' => 'email_customer', 'send_at' => clone $eveningBefore];
            }
        }
    }

    if ($diffDays >= 2) {
        $eveningTwoBefore = (clone $pickup)->setTime(19, 0, 0)->modify('-2 days');
        if ($eveningTwoBefore > $now) {
            $reminders[] = ['type' => 'telegram_admin',  'send_at' => clone $eveningTwoBefore];
            if ($customerEmail) {
                $reminders[] = ['type' => 'email_customer', 'send_at' => clone $eveningTwoBefore];
            }
        }
    }

    if (empty($reminders)) return;

    $stmt = $conn->prepare(
        "INSERT INTO order_reminders (order_id, type, send_at) VALUES (?, ?, ?)"
    );
    foreach ($reminders as $r) {
        $type   = $r['type'];
        $sendAt = $r['send_at']->format('Y-m-d H:i:s');
        $stmt->bind_param('iss', $orderId, $type, $sendAt);
        $stmt->execute();
    }
    $stmt->close();
}
