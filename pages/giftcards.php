<?php
/* СЕРТИФІКАТИ — тимчасово приховано

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
*/
?>
<?php /* СЕРТИФІКАТИ — сторінка тимчасово відключена */ ?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Coffee Time</title>
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/footer.css">
</head>
<body>
  <?php
  session_start();
  $page = 'giftcards';
  require '../includes/header.php';
  ?>
  <main class="page-content" style="text-align:center; padding: 60px 20px;">
    <h1>Сторінка тимчасово недоступна</h1>
    <p><a href="index.php">Повернутися на головну</a></p>
  </main>
  <?php require '../includes/footer.php'; ?>
</body>
</html>
