<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require '../db/db.php';

$heroSlides = [
  ['image' => 'static/images/categories/coffee_category.jpg', 'text' => 'Кожен ковток — тепла історія'],
  ['image' => 'static/images/categories/dessert.jpg',          'text' => 'Неможливо встояти…'],
  ['image' => 'static/images/categories/fast_food.jpg',        'text' => 'Ідеальне комбо'],
];

// Слайдери: їжа, напої, десерти
$sliders = [
  'Краща їжа' => [
    'tables' => ['fast_food_items', 'pizza_items', 'dessert_items'],
    'css' => 'food-item'
  ],
  'Найпопулярніші напої' => [
    'tables' => ['cold_drink_items', 'coffee_items'],
    'css' => 'drink-item'
  ],
  'Наші десерти' => [
    'tables' => ['dessert_items'],
    'css' => 'dessert-item'
  ]
];

function fetchPopularItems($conn, array $tables, int $limit =5 ): array {
  $items = [];
  foreach ($tables as $table) {
    $res = $conn->query("SELECT name, image, popularity FROM `$table` ORDER BY popularity DESC LIMIT $limit");
    while ($row = $res->fetch_assoc()) {
      $row['image'] = '../' . ltrim($row['image'], '/');
      $row['css'] = $table;
      $items[] = $row;
    }
  }
  // Сортуємо всі товари по популярності
  usort($items, fn($a, $b) => $b['popularity'] <=> $a['popularity']);
  return $items;
}
?>
<!DOCTYPE html>
<html lang="uk">
<?php
$page = 'home';
$pageTitle = 'Головна | Coffee Time';
$customStyles = [
  '../static/css/slider.css',
  '../static/css/slider_food.css'
];
include '../includes/header.php';
?>
<main>
  <section class="hero">
    <div class="slider">
      <button class="arrow left">&#10094;</button>
      <button class="arrow right">&#10095;</button>
      <?php foreach ($heroSlides as $i => $s): ?>
        <div class="slide<?= $i === 0 ? ' active' : '' ?>" style="background-image: url('../<?= $s['image'] ?>')">
          <div class="hero-text"><h1><?= $s['text'] ?></h1></div>
        </div>
      <?php endforeach; ?>
      <div class="slider-controls">
        <?php foreach ($heroSlides as $i => $_): ?>
          <span class="dot<?= $i === 0 ? ' active' : '' ?>"></span>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php foreach ($sliders as $title => $conf): ?>
    <?php $popularItems = fetchPopularItems($conn, $conf['tables']); ?>
    <section>
      <h2><?= $title ?></h2>
      <div class="slider category-slider">
        <button class="arrow left">&#10094;</button>
        <button class="arrow right">&#10095;</button>
        <div class="slider-track">
          <?php foreach ($popularItems as $item): ?>
            <div class="slide">
              <div class="item-card <?= htmlspecialchars($conf['css']) ?>">
                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>
</main>

<?php include '../includes/footer.php'; ?>
<script src="../static/js/header.js"></script>
<script src="../static/js/slider.js"></script>
<script src="../static/js/scroll-reveal.js"></script>
</body>
</html>
