<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <title>Адмін-панель — CoffeeTime</title>
  <link rel="stylesheet" href="../static/css/admin-dashboard.css">
</head>
<body>
  <div class="dashboard-container">
    <h1>Панель адміністратора</h1>

    <div class="admin-actions">

      <div class="card">
        <h2>Товари</h2>
        <ul>
          <li><a href="manage_items.php?category=coffee_items">Кава</a></li>
          <li><a href="manage_items.php?category=fast_food_items">Фаст-фуд</a></li>
          <li><a href="manage_items.php?category=pizza_items">Піца</a></li>
          <li><a href="manage_items.php?category=cold_drink_items">Охолоджені напої</a></li>
          <li><a href="manage_items.php?category=dessert_items">Десерти</a></li>
          <li><a href="manage_items.php?category=giftcards">Подарункові картки</a></li>
        </ul>
      </div>

      <div class="card">
        <h2>Замовлення</h2>
        <a href="orders.php" class="btn">Переглянути всі замовлення</a>
      </div>

      <div class="card logout-card">
        <h2>Сесія</h2>
        <a href="logout.php" class="btn btn-red">Вийти</a>
      </div>

    </div>
  </div>
</body>
</html>
