<?php
session_start();
require '../db/db.php'; // Підключення до бази даних

$page = 'reviews';
$pageTitle = 'Відгуки — Coffee Time';

$sort = $_GET['sort'] ?? 'best';
$orderClause = "ORDER BY rating DESC, created_at DESC";

switch ($sort) {
    case 'worst':
        $orderClause = "ORDER BY rating ASC, created_at DESC";
        break;
    case 'newest':
        $orderClause = "ORDER BY created_at DESC";
        break;
    case 'oldest':
        $orderClause = "ORDER BY created_at ASC";
        break;
}

// ==== Збереження локального відгуку ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['text'], $_POST['rating'])) {
    $name = trim($_POST['name']);
    $text = trim($_POST['text']);
    $rating = (int)$_POST['rating'];

    if ($name && $text && $rating > 0 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO site_reviews (name, text, rating) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $name, $text, $rating);
        $stmt->execute();
        $stmt->close();
        header('Location: reviews.php');
        exit;
    }
}

// ==== Отримання Google-відгуків ====
$apiKey = 'AIzaSyBGJuxpOPG4gdBn4BF3hBdXiLiHsa7nAJs';
$placeId = 'ChIJpSEn64eEMUcR5GJBQPZZnpg';

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://places.googleapis.com/v1/places/$placeId?fields=reviews&key=$apiKey",
    CURLOPT_RETURNTRANSFER => true
]);
$response = curl_exec($curl);
curl_close($curl);
$googleData = json_decode($response, true);
$googleReviews = $googleData['reviews'] ?? [];

usort($googleReviews, function($a, $b) use ($sort) {
    $ra = $a['rating'] ?? 0;
    $rb = $b['rating'] ?? 0;
    $da = strtotime($a['publishTime'] ?? '2000-01-01');
    $db = strtotime($b['publishTime'] ?? '2000-01-01');

    switch ($sort) {
        case 'worst':
            return $ra <=> $rb;
        case 'newest':
            return $db <=> $da;
        case 'oldest':
            return $da <=> $db;
        case 'best':
        default:
            return $rb <=> $ra;
    }
});


// ==== Отримання локальних відгуків ====
$siteReviews = [];
$res = $conn->query("SELECT name, text, rating, created_at FROM site_reviews $orderClause");
while ($row = $res->fetch_assoc()) {
    $siteReviews[] = $row;
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
  <link rel="stylesheet" href="../static/css/reviews.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<main class="page-content">
  <h1 style="text-align: center">Відгуки наших клієнтів</h1>

  <div style="text-align: center; margin-bottom: 1rem; position: relative;">
    <button onclick="toggleSortDropdown()" class="sort-button">Сортувати ▾</button>
    <div id="sortDropdown" class="sort-dropdown">
      <a href="reviews.php?sort=best">Спочатку кращі</a>
      <a href="reviews.php?sort=worst">Спочатку гірші</a>
      <a href="reviews.php?sort=newest">Спочатку новіші</a>
      <a href="reviews.php?sort=oldest">Спочатку давніші</a>
    </div>
  </div>

  <div class="expand-grid">
    <?php foreach ($googleReviews as $review): ?>
      <div class="testimonial-item">
        <div class="testimonial-author">
          <?= htmlspecialchars($review['authorAttribution']['displayName'] ?? 'Aнонім') ?>
          <span style="display:block; font-size: 0.8rem; color: #888;">
            <?= isset($review['publishTime']) ? date('d.m.Y', strtotime($review['publishTime'])) : '' ?> — взято з Google Maps
          </span>
          <div class="stars-static">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <?= $i <= round($review['rating'] ?? 0) ? '★' : '☆' ?>
            <?php endfor; ?>
          </div>
        </div>
        <p><?= htmlspecialchars($review['originalText']['text'] ?? 'Невідомий відгук') ?></p>
      </div>
    <?php endforeach; ?>

    <?php foreach ($siteReviews as $r): ?>
      <div class="testimonial-item">
        <div class="testimonial-author">
          <?= htmlspecialchars($r['name']) ?> (<?= date('d.m.Y', strtotime($r['created_at'])) ?>)
          <div class="stars-static">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <?= $i <= (int)$r['rating'] ? '★' : '☆' ?>
            <?php endfor; ?>
          </div>
        </div>
        <p><?= htmlspecialchars($r['text']) ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <hr>

  <section class="leave-review">
    <h2>Залишити свій відгук</h2>
    <form method="post" class="review-form" onsubmit="return validateReviewForm()">
      <div class="form-group rating-full-row">
        <label>Оцінка:</label>
        <div class="stars" id="starRating">
          <span data-value="1">&#9734;</span>
          <span data-value="2">&#9734;</span>
          <span data-value="3">&#9734;</span>
          <span data-value="4">&#9734;</span>
          <span data-value="5">&#9734;</span>
        </div>
        <input type="hidden" name="rating" id="ratingInput" value="0" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="name">Ваше ім’я:</label>
          <input type="text" name="name" id="name" required>
        </div>
        <div class="form-group flex-grow">
          <label for="text">Відгук:</label>
          <textarea name="text" id="text" rows="4" required></textarea>
        </div>
      </div>
      <button type="submit"> Надіслати </button>
    </form>
  </section>
</main>

<?php include '../includes/footer.php'; ?>
<script src='../static/js/reviews.js'></script>
</body>
</html>
