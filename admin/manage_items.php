<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require '../db/db.php';

$category = $_GET['category'] ?? '';
$allowed = ['coffee_items', 'fast_food_items', 'pizza_items', 'cold_drink_items', 'dessert_items', 'giftcards'];

if (!in_array($category, $allowed)) {
    echo " Невідома категорія.";
    exit;
}

$categoryNames = [
    'coffee_items' => 'Кава',
    'fast_food_items' => 'Фаст-фуд',
    'pizza_items' => 'Піца',
    'cold_drink_items' => 'Охолоджені напої',
    'dessert_items' => 'Десерти',
    'giftcards' => 'Подарункові картки',
];

$catTitle = $categoryNames[$category];

// Отримати товари з відповідної таблиці
$result = $conn->query("SELECT * FROM `$category` ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title><?= $catTitle ?> — Управління</title>
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
    <h1><?= $catTitle ?> — Товари</h1>

    <a href="add_item.php?category=<?= $category ?>" class="btn green" style="margin-bottom: 1rem;">➕ Додати товар</a>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Назва</th>
                <th>Опис</th>
                <th>Ціна</th>
                <th>Зображення</th>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['name'] ?? $row['title']) ?></td>
                    <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                    <td><?= number_format($row['price'], 2) ?> ₴</td>
                    <td><img src="../<?= $row['image'] ?>" alt="img" style="width: 60px;"></td>
                    <td>
                        <a href="edit_item.php?category=<?= $category ?>&id=<?= $row['id'] ?>" class="btn blue">Редагувати</a>
                        <a href="delete_item.php?category=<?= $category ?>&id=<?= $row['id'] ?>" class="btn red" onclick="return confirm('Видалити цей товар?')">Видалити</a>
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
