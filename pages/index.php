<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require '../db/db.php';
require_once '../includes/helpers.php';

$heroSlides = [];
try {
    $_r = $conn->query("SELECT image, label, title AS text, subtitle AS sub FROM hero_slides WHERE active=1 ORDER BY sort_order ASC, id ASC");
    if ($_r) while ($_row = $_r->fetch_assoc()) $heroSlides[] = $_row;
} catch (Exception $_e) {}
/* Fallback if table missing or empty */
if (empty($heroSlides)) {
    $heroSlides = [
        ['image' => 'static/images/categories/coffee_category.webp', 'text' => 'Кожен ковток — тепла історія',  'sub' => "Свіжозварена кава щоранку з любов'ю"],
        ['image' => 'static/images/categories/dessert.webp',          'text' => 'Неможливо встояти…',            'sub' => 'Десерти власного приготування щодня'],
        ['image' => 'static/images/categories/fast_food.webp',        'text' => 'Ідеальне комбо',               'sub' => 'Смачно, ситно і завжди свіже'],
    ];
}

function fetchPopularItems($conn, array $tables, int $limit = 5): array {
  $items = [];
  foreach ($tables as $table) {
    $res = $conn->query("SELECT id, name, description, image, popularity, price FROM `$table` ORDER BY popularity DESC LIMIT $limit");
    while ($row = $res->fetch_assoc()) {
      $row['image'] = item_img($row['image'] ?? '');
      $row['table'] = $table;
      $items[] = $row;
    }
  }
  usort($items, fn($a, $b) => $b['popularity'] <=> $a['popularity']);
  return array_slice($items, 0, $limit);
}

function fetchTopOrderedItems(mysqli $conn, array $tables, int $limit = 3, int $days = 0): array {
  if (empty($tables)) return [];
  $placeholders = implode(',', array_fill(0, count($tables), '?'));
  $dateClause = $days > 0 ? "AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : '';
  $types  = str_repeat('s', count($tables)) . ($days > 0 ? 'ii' : 'i');
  $params = $days > 0 ? array_merge($tables, [$days, $limit]) : array_merge($tables, [$limit]);
  $stmt   = $conn->prepare(
    "SELECT oi.product_id, oi.category, SUM(oi.quantity) AS total_ordered
     FROM order_items oi
     INNER JOIN orders o ON oi.order_id = o.order_id
     WHERE oi.category IN ($placeholders) $dateClause
     GROUP BY oi.product_id, oi.category
     ORDER BY total_ordered DESC
     LIMIT ?"
  );
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  $items = [];
  foreach ($rows as $row) {
    $s = $conn->prepare("SELECT * FROM `{$row['category']}` WHERE id=?");
    $pid = (int)$row['product_id'];
    $s->bind_param('i', $pid);
    $s->execute();
    $prod = $s->get_result()->fetch_assoc();
    $s->close();
    if ($prod) {
      $prod['image'] = item_img($prod['image'] ?? '');
      $prod['table'] = $row['category'];
      $items[] = $prod;
    }
  }
  if (count($items) >= 3) return $items;
  $popular = fetchPopularItems($conn, $tables, $limit);
  // merge: popular items not already in $items go first as filler
  $existing = array_column($items, 'id');
  foreach ($popular as $p) {
    if (!in_array($p['id'], $existing)) $items[] = $p;
    if (count($items) >= $limit) break;
  }
  return $items;
}

$foodItems    = fetchTopOrderedItems($conn, ['fast_food_items', 'pizza_items'], 3);
$drinkItems   = fetchTopOrderedItems($conn, ['cold_drink_items', 'coffee_items'], 5, 7);
$dessertItems = fetchPopularItems($conn, ['dessert_items'], 3);

// Секція "Про нас" — з БД або дефолт
$aboutSettings = [
    'about_title'        => 'Місце, де час зупиняється',
    'about_text'         => "Coffee Time — це затишне кафе в серці міста, де ми щодня готуємо свіжі десерти та каву з любов'ю. Ніяких заморожених напівфабрикатів — тільки справжнє та смачне.",
    'about_founded_year' => '2016',
    'about_menu_count'   => '50',
    'about_rating'       => '4.8',
    'about_photo'        => 'static/images/main/about-photo.png',
];
try {
    $res = $conn->query("SELECT `key`, `value` FROM site_settings WHERE `key` LIKE 'about_%'");
    if ($res) while ($row = $res->fetch_assoc()) $aboutSettings[$row['key']] = $row['value'];
} catch (Exception $_e) {}

// Десерт дня — текст з site_settings + фото (кастомне або рандомне з БД)
$dessertBanner = [
    'label' => 'Щодня нове',
    'title' => 'Десерт дня',
    'desc'  => "Мусові торти, еклери та макарони —\nготуємо кожного ранку зі свіжих інгредієнтів",
    'btn'   => 'Дивитись десерти →',
    'image' => '',
];
try {
    $res = $conn->query("SELECT `key`, `value` FROM site_settings WHERE `key` LIKE 'dessert_banner_%'");
    if ($res) while ($row = $res->fetch_assoc()) {
        $k = str_replace('dessert_banner_', '', $row['key']);
        $dessertBanner[$k] = $row['value'];
    }
} catch (Exception $_e) {}

$dessertBannerImg = null;
if (!empty($dessertBanner['image'])) {
    $dessertBannerImg = '../' . ltrim($dessertBanner['image'], '/');
} else {
    $res = $conn->query("SELECT image FROM dessert_items ORDER BY RAND() LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) $dessertBannerImg = '../' . ltrim($row['image'], '/');
}

// Відгуки — тільки з текстом; fallback якщо немає
$reviews      = [];
$totalReviews = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM site_reviews");
if ($res) $totalReviews = (int)$res->fetch_assoc()['cnt'];

$res = $conn->query("SELECT name, text, rating FROM site_reviews WHERE LENGTH(text) > 20 ORDER BY rating DESC, created_at DESC LIMIT 3");
if ($res) $reviews = $res->fetch_all(MYSQLI_ASSOC);
if (empty($reviews)) {
  $res = $conn->query("SELECT name, text, rating FROM site_reviews ORDER BY created_at DESC LIMIT 3");
  if ($res) $reviews = $res->fetch_all(MYSQLI_ASSOC);
}

// Колір аватара по першій літері імені
function reviewAvatarColor(string $name): string {
  $ch = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
  static $map = [
    'А'=>'#8B4513','Б'=>'#8B4513','В'=>'#8B4513','Г'=>'#8B4513','Д'=>'#8B4513',
    'Е'=>'#d4a96a','Є'=>'#d4a96a','Ж'=>'#d4a96a','З'=>'#d4a96a','И'=>'#d4a96a',
    'І'=>'#d4a96a','Ї'=>'#d4a96a','Й'=>'#d4a96a','К'=>'#d4a96a','Л'=>'#d4a96a',
    'М'=>'#5a2d0c','Н'=>'#5a2d0c','О'=>'#5a2d0c','П'=>'#5a2d0c','Р'=>'#5a2d0c','С'=>'#5a2d0c',
  ];
  return $map[$ch] ?? '#c4956a';
}
?>
<!DOCTYPE html>
<html lang="uk">
<?php
$page         = 'home';
$pageTitle    = 'Головна | Coffee Time';
$customStyles = [
  '../static/css/slider.css',
  '../static/css/slider_food.css',
  '../static/css/homepage.css',
];
$preloadHeroImage = $heroSlides[0]['image'] ?? '';
include '../includes/header.php';
?>
<body>
<main>

  <!-- ===== HERO ===== -->
  <section class="hero">
    <div class="slider">

      <?php foreach ($heroSlides as $i => $s): ?>
        <div class="slide<?= $i === 0 ? ' active' : '' ?>"
             style="background-image: url('../<?= htmlspecialchars($s['image']) ?>')">
          <div class="hero-text">
            <?php if (!empty($s['label'])): ?><span class="hero-label"><?= htmlspecialchars($s['label']) ?></span><?php endif; ?>
            <h1><?= htmlspecialchars($s['text']) ?></h1>
            <p class="hero-subtitle"><?= htmlspecialchars($s['sub']) ?></p>
            <a href="../pages/menu.php" class="hero-cta">Переглянути меню →</a>
          </div>
        </div>
      <?php endforeach; ?>

      <button class="arrow left" aria-label="Попередній слайд"></button>
      <button class="arrow right" aria-label="Наступний слайд"></button>

      <div class="slider-controls">
        <?php foreach ($heroSlides as $i => $_): ?>
          <span class="dot<?= $i === 0 ? ' active' : '' ?>"></span>
        <?php endforeach; ?>
      </div>

      <div class="hero-wave">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 72" preserveAspectRatio="none">
          <path d="M0,0 C480,72 960,0 1440,0 L1440,72 L0,72 Z" fill="#fdf6ee"/>
        </svg>
      </div>
    </div>
  </section>

  <!-- ===== BENEFITS STRIP ===== -->
  <section class="benefits-strip">
    <div class="container">
      <div class="benefit-block">
        <?= icon('coffee-cup', 36, '#8B4513', 'benefit-icon') ?>
        <div>
          <p class="benefit-title">Свіжозварена кава</p>
          <p class="benefit-sub">Щоранку нова обсмажка</p>
        </div>
      </div>
      <div class="benefit-block">
        <?= icon('cake', 36, '#8B4513', 'benefit-icon') ?>
        <div>
          <p class="benefit-title">Десерти щодня</p>
          <p class="benefit-sub">Мусові, еклери, макарони</p>
        </div>
      </div>
      <div class="benefit-block">
        <?= icon('leaf', 36, '#8B4513', 'benefit-icon') ?>
        <div>
          <p class="benefit-title">Свіжі інгредієнти</p>
          <p class="benefit-sub">Без консервантів</p>
        </div>
      </div>
      <div class="benefit-block">
        <?= icon('clock', 36, '#8B4513', 'benefit-icon') ?>
        <div>
          <p class="benefit-title">Графік роботи</p>
          <p class="benefit-sub">Пн–Пт 8:00–20:00</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== КРАЩА ЇЖА ===== -->
  <section class="home-section">
    <div class="container">
      <h2>Краща їжа</h2>
      <div class="product-grid">
        <?php foreach ($foodItems as $item): ?>
          <div class="item-card food-item">
            <span class="card-badge"><?= icon('fire', 13, '#e65100') ?> Хіт</span>
            <?php if ($item['image']): ?>
            <img src="<?= htmlspecialchars($item['image']) ?>"
                 alt="<?= htmlspecialchars($item['name']) ?>"
                 loading="lazy">
            <?php else: ?>
            <div class="item-no-img" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="card-info">
              <p class="card-name"><?= htmlspecialchars($item['name']) ?></p>
              <?php if (!empty($item['description'])): ?>
                <p class="card-desc"><?= htmlspecialchars(mb_substr($item['description'], 0, 72, 'UTF-8')) ?><?= mb_strlen($item['description'], 'UTF-8') > 72 ? '…' : '' ?></p>
              <?php endif; ?>
              <div class="card-footer">
                <span class="card-price"><?= number_format((float)$item['price'], 0) ?> грн</span>
                <a href="menu.php?category=<?= htmlspecialchars($item['table']) ?>&amp;scroll_to=<?= (int)$item['id'] ?>"
                   class="btn-add-cart">Переглянути в меню →</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===== БАНЕР "ДЕСЕРТ ДНЯ" ===== -->
  <section class="dessert-banner">
    <div class="dessert-banner__text">
      <?php if (!empty($dessertBanner['label'])): ?>
        <p class="banner-label"><?= htmlspecialchars($dessertBanner['label']) ?></p>
      <?php endif; ?>
      <h2><?= htmlspecialchars($dessertBanner['title']) ?></h2>
      <p class="dessert-banner__desc">
        <?= nl2br(htmlspecialchars($dessertBanner['desc'])) ?>
      </p>
      <a href="../pages/menu.php?category=dessert_items" class="banner-btn"><?= htmlspecialchars($dessertBanner['btn']) ?></a>
    </div>
    <div class="dessert-banner__photo<?= $dessertBannerImg ? ' has-photo' : '' ?>">
      <?php if ($dessertBannerImg): ?>
        <img src="<?= htmlspecialchars($dessertBannerImg) ?>" alt="Десерт дня">
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== НАЙПОПУЛЯРНІШІ НАПОЇ ===== -->
  <section class="home-section">
    <div class="container">
      <h2>Обирають найчастіше</h2>
      <p class="section-subtitle">Найпопулярніші напої та кава</p>
      <?php if (count($drinkItems) < 4): ?>
        <div class="product-grid">
          <?php foreach ($drinkItems as $item): ?>
            <div class="item-card drink-item">
              <?php if ($item['image']): ?>
              <img src="<?= htmlspecialchars($item['image']) ?>"
                   alt="<?= htmlspecialchars($item['name']) ?>"
                   loading="lazy">
              <?php else: ?>
              <div class="item-no-img" aria-hidden="true"></div>
              <?php endif; ?>
              <div class="card-info">
                <p class="card-name"><?= htmlspecialchars($item['name']) ?></p>
                <?php if (!empty($item['description'])): ?>
                  <p class="card-desc"><?= htmlspecialchars(mb_substr($item['description'], 0, 72, 'UTF-8')) ?><?= mb_strlen($item['description'], 'UTF-8') > 72 ? '…' : '' ?></p>
                <?php endif; ?>
                <div class="card-footer">
                  <span class="card-price"><?= number_format((float)$item['price'], 0) ?> грн</span>
                  <a href="menu.php?category=<?= htmlspecialchars($item['table']) ?>&amp;scroll_to=<?= (int)$item['id'] ?>"
                     class="btn-add-cart">Переглянути в меню →</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="slider category-slider">
          <button class="arrow left"  aria-label="Попередній">&#10094;</button>
          <button class="arrow right" aria-label="Наступний">&#10095;</button>
          <div class="slider-track">
            <?php foreach ($drinkItems as $item): ?>
              <div class="slide">
                <div class="item-card drink-item">
                  <?php if ($item['image']): ?>
                  <img src="<?= htmlspecialchars($item['image']) ?>"
                       alt="<?= htmlspecialchars($item['name']) ?>"
                       loading="lazy">
                  <?php else: ?>
                  <div class="item-no-img" aria-hidden="true"></div>
                  <?php endif; ?>
                  <div class="card-info">
                    <p class="card-name"><?= htmlspecialchars($item['name']) ?></p>
                    <?php if (!empty($item['description'])): ?>
                      <p class="card-desc"><?= htmlspecialchars(mb_substr($item['description'], 0, 72, 'UTF-8')) ?><?= mb_strlen($item['description'], 'UTF-8') > 72 ? '…' : '' ?></p>
                    <?php endif; ?>
                    <div class="card-footer">
                      <span class="card-price"><?= number_format((float)$item['price'], 0) ?> грн</span>
                      <a href="menu.php?category=<?= htmlspecialchars($item['table']) ?>&amp;scroll_to=<?= (int)$item['id'] ?>"
                         class="btn-add-cart">Переглянути в меню →</a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== ПРО НАС ===== -->
  <?php
    $yearsOpen   = (int)date('Y') - (int)($aboutSettings['about_founded_year'] ?? 2016);
    $menuCount   = (int)($aboutSettings['about_menu_count'] ?? 50);
    $rating      = (float)($aboutSettings['about_rating'] ?? 4.8);
    $aboutPhoto  = '../' . ltrim($aboutSettings['about_photo'] ?? 'static/images/main/about-photo.png', '/');
  ?>
  <section class="home-section about-section">
    <div class="container">
      <div class="about-content">
        <p class="about-label">Про нас</p>
        <h2><?= htmlspecialchars($aboutSettings['about_title']) ?></h2>
        <p class="about-text"><?= htmlspecialchars($aboutSettings['about_text']) ?></p>
        <div class="about-stats">
          <div class="stat-item">
            <span class="stat-number" data-count="<?= $yearsOpen ?>"><?= $yearsOpen ?></span>
            <span class="stat-label">років на ринку</span>
          </div>
          <div class="stat-item">
            <span class="stat-number" data-count="<?= $menuCount ?>" data-suffix="+"><?= $menuCount ?>+</span>
            <span class="stat-label">позицій меню</span>
          </div>
          <div class="stat-item">
            <span class="stat-number" data-count="<?= $rating ?>" data-suffix="★" data-decimals="1"><?= $rating ?>★</span>
            <span class="stat-label">Google рейтинг</span>
          </div>
        </div>
      </div>
      <div class="about-photo">
        <img src="<?= htmlspecialchars($aboutPhoto) ?>" alt="Coffee Time кафе">
      </div>
    </div>
  </section>

  <!-- ===== НАШІ ДЕСЕРТИ ===== -->
  <section class="home-section dessert-featured">
    <div class="container">
      <h2>Наші десерти</h2>
      <p class="section-subtitle">Готуємо щоранку — мусові торти, еклери, макарони, тарти</p>
      <div class="product-grid">
        <?php foreach ($dessertItems as $item): ?>
          <div class="item-card dessert-item">
            <?php if ($item['image']): ?>
            <img src="<?= htmlspecialchars($item['image']) ?>"
                 alt="<?= htmlspecialchars($item['name']) ?>"
                 loading="lazy">
            <?php else: ?>
            <div class="item-no-img" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="card-info">
              <p class="card-name"><?= htmlspecialchars($item['name']) ?></p>
              <?php if (!empty($item['description'])): ?>
                <p class="card-desc"><?= htmlspecialchars(mb_substr($item['description'], 0, 72, 'UTF-8')) ?><?= mb_strlen($item['description'], 'UTF-8') > 72 ? '…' : '' ?></p>
              <?php endif; ?>
              <div class="card-footer">
                <span class="card-price"><?= number_format((float)$item['price'], 0) ?> грн</span>
                <a href="menu.php?category=<?= htmlspecialchars($item['table']) ?>&amp;scroll_to=<?= (int)$item['id'] ?>"
                   class="btn-add-cart">Переглянути в меню →</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===== ВІДГУКИ ===== -->
  <section class="home-section reviews-section">
    <div class="container">
      <h2>Що кажуть наші гості</h2>
      <p class="section-subtitle">
        <?= $totalReviews >= 10
          ? 'На основі ' . $totalReviews . ' відгуків'
          : 'Реальні відгуки наших відвідувачів' ?>
      </p>
      <div class="reviews-grid">

        <?php foreach ($reviews as $r):
            $stars  = str_repeat('★', (int)($r['rating'] ?? 5)) . str_repeat('☆', 5 - (int)($r['rating'] ?? 5));
            $role   = $r['role'] ?? ($r['author_title'] ?? '');
            $initial = mb_strtoupper(mb_substr($r['name'], 0, 1, 'UTF-8'), 'UTF-8');
        ?>
        <div class="review-card">
          <div class="review-avatar" style="background: <?= reviewAvatarColor($r['name']) ?>">
            <?= htmlspecialchars($initial) ?>
          </div>
          <div class="review-stars"><?= $stars ?></div>
          <p class="review-text">"<?= htmlspecialchars($r['text']) ?>"</p>
          <div class="review-author">
            <span class="review-name"><?= htmlspecialchars($r['name']) ?></span>
            <?php if ($role): ?>
              <span class="review-role"><?= htmlspecialchars($role) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>

      </div>
      <div class="reviews-cta">
        <a href="../pages/reviews.php" class="btn-outline">
          Переглянути всі відгуки<?= $totalReviews > 0 ? ' (' . $totalReviews . ')' : '' ?> →
        </a>
      </div>
      <div class="reviews-leave-cta">
        <a href="../pages/reviews.php#leave-review" class="btn-leave-review">
          Залишити відгук
        </a>
      </div>
    </div>
  </section>

</main>

<?php include '../includes/footer.php'; ?>
<script src="../static/js/slider.js"></script>
<script src="../static/js/scroll-reveal.js"></script>
</body>
</html>
