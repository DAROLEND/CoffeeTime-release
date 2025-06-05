<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require '../db/db.php';

// Фільтрування лише замовлення зі статусом pending
$query = "SELECT o.order_id, o.customer_name, o.total, o.created_at 
          FROM orders o 
          WHERE o.status = 'pending'
          ORDER BY o.created_at DESC";

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Замовлення — Адмінка</title>
    <link rel="stylesheet" href="../static/css/admin.css">
</head>
<body>
<header class="admin-header">
    <div class="logo">CoffeeTime Admin</div>
    <nav>
        <a href="dashboard.php">Головна</a>
        <a href="orders.php" class="active">Замовлення</a>
        <a href="logout.php" class="logout">Вийти</a>
    </nav>
</header>

<main class="admin-container">
    <h1>Усі замовлення (в обробці)</h1>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ім’я замовника</th>
                <th>Дата створення</th>
                <th>Сума</th>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['order_id'] ?></td>
                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                <td><?= date('d.m.Y H:i', strtotime($row['created_at'])) ?></td>
                <td><?= number_format($row['total'], 2) ?> ₴</td>
                <td>
                    <a href="view_order.php?id=<?= $row['order_id'] ?>" class="btn blue">Перейти до замовлення</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</main>

<footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> CoffeeTime. Усі права захищено.</p>
</footer>
</body>
</html>
