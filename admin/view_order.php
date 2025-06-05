<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
require '../db/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Невірний ID замовлення.";
    exit;
}
$orderId = (int)$_GET['id'];

// Загальна інформація
$stmt = $conn->prepare("
    SELECT o.order_id, o.status, o.total, o.phone, o.ready_time, o.payment_method, o.customer_name, o.comment
    FROM orders o
    WHERE o.order_id = ?
");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "Замовлення не знайдено.";
    exit;
}

// Отримати товари
$items = [];
$stmt = $conn->prepare("
    SELECT product_id, quantity, price, category 
    FROM order_items 
    WHERE order_id = ?
");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $table = $row['category'];
    $prodId = (int)$row['product_id'];
    $name = '—';

    // Якщо звичайна таблиця з полем name
    if (in_array($table, ['coffee_items', 'pizza_items', 'fast_food_items', 'cold_drink_items', 'dessert_items'])) {
        $nameStmt = $conn->prepare("SELECT name FROM `$table` WHERE id = ?");
    }
    // Якщо це giftcards — витягуємо title
    elseif ($table === 'giftcards') {
        $nameStmt = $conn->prepare("SELECT title AS name FROM giftcards WHERE id = ?");
    }

    if (isset($nameStmt)) {
        $nameStmt->bind_param("i", $prodId);
        $nameStmt->execute();
        $nameRes = $nameStmt->get_result()->fetch_assoc();
        $name = $nameRes['name'] ?? '—';
        $nameStmt->close();
    }

    $row['product_name'] = $name;
    $items[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Замовлення #<?= $orderId ?></title>
    <link rel="stylesheet" href="../static/css/admin.css">
</head>
<body>
<header class="admin-header">
    <div class="logo">CoffeeTime Admin</div>
    <nav>
        <a href="dashboard.php">Головна</a>
        <a href="orders.php">Замовлення</a>
        <a href="logout.php" class="logout">Вийти</a>
    </nav>
</header>

<main class="admin-container">
    <h1>Замовлення #<?= $orderId ?></h1>
    <p><strong>Клієнт:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
    <p><strong>Телефон:</strong> <?= htmlspecialchars($order['phone']) ?></p>
    <p><strong>Коментар:</strong> <?= nl2br(htmlspecialchars($order['comment'])) ?></p>
    <p><strong>Час готовності:</strong> <?= htmlspecialchars($order['ready_time']) ?></p>
    <p><strong>Оплата:</strong> <?= htmlspecialchars($order['payment_method'] === 'cash_on_pickup' ? 'Оплата при отриманні' : 'Оплачено онлайн') ?></p>
    <p><strong>Статус:</strong> <?= htmlspecialchars($order['status']) ?></p>
    <p><strong>Сума:</strong> <?= number_format($order['total'], 2) ?> ₴</p>

    <h2>Товари у замовленні:</h2>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Назва товару</th>
                <th>Кількість</th>
                <th>Ціна</th>
                <th>Сума</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td><?= number_format($item['price'], 2) ?> ₴</td>
                <td><?= number_format($item['price'] * $item['quantity'], 2) ?> ₴</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 1rem;">
        <a href="update_order.php?id=<?= $orderId ?>&status=approved" class="btn green">Підтвердити</a>
        <a href="update_order.php?id=<?= $orderId ?>&status=declined" class="btn red">Відхилити</a>
        <a href="orders.php" class="btn gray">← Назад до списку</a>
    </div>
</main>

<footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> CoffeeTime. Усі права захищено.</p>
</footer>
</body>
</html>
