<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require '../db/db.php';

$category = $_GET['category'] ?? '';
$id = $_GET['id'] ?? '';

if (!$category || !$id || !is_numeric($id)) {
    echo "Невірний запит.";
    exit;
}

$table = $conn->real_escape_string($category);

// Отримати поточні дані товару
$stmt = $conn->prepare("SELECT name, description, price, image FROM `$table` WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "Товар не знайдено.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'] ?? '';
    $desc  = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $imagePath = $product['image'];

    $stmt = $conn->prepare("UPDATE `$table` SET name = ?, description = ?, price = ?, image = ? WHERE id = ?");
    if (!$stmt) {
        die("Помилка запиту: " . $conn->error);
    }
    $stmt->bind_param("ssdsi", $name, $desc, $price, $imagePath, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: edit_item.php?category=$category&id=$id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <title>Редагувати товар</title>
  <link rel="stylesheet" href="../static/css/admin.css">
  <link rel="stylesheet" href="../static/css/edit_item.css">
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

  <main>
    <h1 style="text-align:center;">Редагувати товар</h1>
    <form method="post">
      <label for="name">Назва</label>
      <input type="text" name="name" id="name" required value="<?= htmlspecialchars($product['name']) ?>">

      <label for="description">Опис</label>
      <textarea name="description" id="description" rows="3"><?= htmlspecialchars($product['description']) ?></textarea>

      <label for="price">Ціна (₴)</label>
      <input type="number" name="price" id="price" step="0.01" required value="<?= $product['price'] ?>">

      <?php if ($product['image']): ?>
        <label>Поточне зображення:</label><br>
        <img src="../<?= htmlspecialchars($product['image']) ?>" class="preview" alt="Зображення">
      <?php endif; ?>

      <div class="form-actions">
        <button type="submit" class="btn green">Зберегти</button>
        <a href="manage_items.php?category=<?= $category ?>" class="btn red">↩ Назад</a>
      </div>
    </form>
  </main>

  <footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> CoffeeTime. Усі права захищено.</p>
  </footer>
</body>
</html>
