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
    die("Невідома категорія.");
}

$categoryFolders = [
    'coffee_items'      => 'coffee',
    'cold_drink_items'  => 'cold_drinks',
    'dessert_items'     => 'desserts',
    'fast_food_items'   => 'fast_food',
    'pizza_items'       => 'pizza',
    'giftcards'         => 'giftcards'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $imagePath = '';

    $subfolder = $categoryFolders[$category];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../static/images/menu_items/' . $subfolder . '/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('item_', true) . '.' . strtolower($ext);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = 'static/images/menu_items/' . $subfolder . '/' . $fileName;
        } else {
            die("Не вдалося зберегти зображення.");
        }
    } else {
        die("Помилка завантаження файлу: код " . $_FILES['image']['error']);
    }

    $stmt = $conn->prepare("INSERT INTO `$category` (name, description, price, image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssds", $name, $desc, $price, $imagePath);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_items.php?category=$category");
    exit;
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <title>Додати товар</title>
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
    <div class="add-form-container">
      <h1>Додати товар</h1>
      <form class="add-form" method="post" enctype="multipart/form-data">
          <label for="name">Назва товару:</label>
          <input type="text" name="name" id="name" required>

          <label for="description">Опис:</label>
          <textarea name="description" id="description" required></textarea>

          <label for="price">Ціна (₴):</label>
          <input type="number" step="0.01" name="price" id="price" required>

          <label for="image">Зображення:</label>
          <input type="file" name="image" id="image" required>

          <div class="form-actions">
              <button type="submit" class="btn green">Зберегти</button>
              <a href="manage_items.php?category=<?= htmlspecialchars($category) ?>" class="btn red">Назад</a>
          </div>
      </form>
    </div>
  </main>

  <footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> CoffeeTime. Усі права захищено.</p>
  </footer>
</body>
</html>
