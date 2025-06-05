<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Підключення до БД
require '../db/db.php';

$page      = 'giftcards';
$pageTitle = 'Подарункові сертифікати — Coffee Time';

// Отримання сертифікатів з БД
$query = "SELECT * FROM giftcards";
$result = mysqli_query($conn, $query);

$giftcards = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $giftcards[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/footer.css">
</head>
<body>
  <?php require '../includes/header.php'; ?>

  <main class="page-content">
    <h1 class = "maintext-gift ">Подарункові сертифікати</h1>

    <div class="gift-grid">
      <?php foreach ($giftcards as $card): ?>
        <?php
          $url = "/CoffeeTime-release/pages/cart.php?action=add&category=giftcards&id=" . (int)$card['id'];
        ?>
        <div class="gift-item">
          <img src="../<?= htmlspecialchars($card['image']) ?>" alt="Coffee Time">
          <div class="card-content">
            <h3><?= htmlspecialchars($card['title']) ?></h3>
            <p><?= (int)$card['price'] ?> ₴</p>
            <a href="<?= $url ?>" class="gift-btn">Купити</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <?php require '../includes/footer.php'; ?>
</body>
</html>
