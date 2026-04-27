<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require '../db/db.php';

$heroSlides = [];
try {
    $_r = $conn->query("SELECT image, title AS text, subtitle AS sub FROM hero_slides WHERE active=1 ORDER BY sort_order ASC, id ASC");
    if ($_r) while ($_row = $_r->fetch_assoc()) $heroSlides[] = $_row;
} catch (Exception $_e) {}
/* Fallback if table missing or empty */
if (empty($heroSlides)) {
    $heroSlides = [
        ['image' => 'static/images/categories/coffee_category.jpg', 'text' => 'Кожен ковток — тепла історія',  'sub' => "Свіжозварена кава щоранку з любов'ю"],
        ['image' => 'static/images/categories/dessert.jpg',          'text' => 'Неможливо встояти…',            'sub' => 'Десерти власного приготування щодня'],
        ['image' => 'static/images/categories/fast_food.jpg',        'text' => 'Ідеальне комбо',               'sub' => 'Смачно, ситно і завжди свіже'],
    ];
}

function fetchPopularItems($conn, array $tables, int $limit = 5): array {
  $items = [];
  foreach ($tables as $table) {
    $res = $conn->query("SELECT id, name, description, image, popularity, price FROM `$table` ORDER BY popularity DESC LIMIT $limit");
    while ($row = $res->fetch_assoc()) {
      $row['image'] = '../' . ltrim($row['image'], '/');
      $row['table'] = $table;
      $items[] = $row;
    }
  }
  usort($items, fn($a, $b) => $b['popularity'] <=> $a['popularity']);
  return array_slice($items, 0, $limit);
}

function fetchTopOrderedItems(mysqli $conn, array $tables, int $limit = 3): array {
  if (empty($tables)) return [];
  $placeholders = implode(',', array_fill(0, count($tables), '?'));
  $types  = str_repeat('s', count($tables)) . 'i';
  $params = array_merge($tables, [$limit]);
  $stmt   = $conn->prepare(
    "SELECT oi.product_id, oi.category, SUM(oi.quantity) AS total_ordered
     FROM order_items oi
     INNER JOIN orders o ON oi.order_id = o.order_id
     WHERE oi.category IN ($placeholders)
     GROUP BY oi.product_id, oi.category
     ORDER BY total_ordered DESC
     LIMIT ?"
  );
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  $items   = [];
  $default = 'static/images/menu_items/default.jpg';
  foreach ($rows as $row) {
    $s = $conn->prepare("SELECT id, name, description, image, price FROM `{$row['category']}` WHERE id=?");
    $pid = (int)$row['product_id'];
    $s->bind_param('i', $pid);
    $s->execute();
    $prod = $s->get_result()->fetch_assoc();
    $s->close();
    if ($prod) {
      $prod['image'] = '../' . ltrim($prod['image'] ?: $default, '/');
      $prod['table'] = $row['category'];
      $items[] = $prod;
    }
  }
  return empty($items) ? fetchPopularItems($conn, $tables, $limit) : $items;
}

$foodItems    = fetchTopOrderedItems($conn, ['fast_food_items', 'pizza_items'], 3);
$drinkItems   = fetchPopularItems($conn, ['cold_drink_items', 'coffee_items'], 5);
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

// Десерт дня — рандомне фото з БД
$dessertBannerImg = null;
$res = $conn->query("SELECT image FROM dessert_items ORDER BY RAND() LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
  $dessertBannerImg = '../' . ltrim($row['image'], '/');
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
            <span class="hero-label">Спробуй зараз</span>
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
        <span class="benefit-icon">☕</span>
        <div>
          <p class="benefit-title">Свіжозварена кава</p>
          <p class="benefit-sub">Щоранку нова обсмажка</p>
        </div>
      </div>
      <div class="benefit-block">
        <span class="benefit-icon">🍰</span>
        <div>
          <p class="benefit-title">Десерти щодня</p>
          <p class="benefit-sub">Мусові, еклери, макарони</p>
        </div>
      </div>
      <div class="benefit-block">
        <span class="benefit-icon">🌿</span>
        <div>
          <p class="benefit-title">Свіжі інгредієнти</p>
          <p class="benefit-sub">Без консервантів</p>
        </div>
      </div>
      <div class="benefit-block">
        <span class="benefit-icon">🕗</span>
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
            <span class="card-badge">🔥 Хіт</span>
            <img
              src="<?= htmlspecialchars($item['image']) ?>"
              alt="<?= htmlspecialchars($item['name']) ?>"
              loading="lazy"
            >
            <div class="card-info">
              <p class="card-name"><?= htmlspecialchars($item['name']) ?></p>
              <?php if (!empty($item['description'])): ?>
                <p class="card-desc"><?= htmlspecialchars(mb_substr($item['description'], 0, 72, 'UTF-8')) ?><?= mb_strlen($item['description'], 'UTF-8') > 72 ? '…' : '' ?></p>
              <?php endif; ?>
              <div class="card-footer">
                <span class="card-price"><?= number_format((float)$item['price'], 0) ?> грн</span>
                <button
                  class="btn-add-cart"
                  data-category="<?= htmlspecialchars($item['table']) ?>"
                  data-id="<?= (int)$item['id'] ?>"
                >Додати в кошик</button>
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
      <p class="banner-label">Щодня нове</p>
      <h2>Десерт дня</h2>
      <p class="dessert-banner__desc">
        Мусові торти, еклери та макарони —<br>
        готуємо кожного ранку зі свіжих інгредієнтів
      </p>
      <a href="../pages/menu.php?category=dessert_items" class="banner-btn">Дивитись десерти →</a>
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
      <p class="section-subtitle">За кількістю замовлень цього тижня</p>
      <?php if (count($drinkItems) < 4): ?>
        <div class="product-grid">
          <?php foreach ($drinkItems as $item): ?>
            <div class="item-card drink-item">
              <img
                src="<?= htmlspecialchars($item['image']) ?>"
                alt="<?= htmlspecialchars($item['name']) ?>"
                loading="lazy"
              >
              <div class="card-info">
                <p class="card-name"><?= htmlspecialchars($item['name']) ?></p>
                <?php if (!empty($item['description'])): ?>
                  <p class="card-desc"><?= htmlspecialchars(mb_substr($item['description'], 0, 72, 'UTF-8')) ?><?= mb_strlen($item['description'], 'UTF-8') > 72 ? '…' : '' ?></p>
                <?php endif; ?>
                <div class="card-footer">
                  <span class="card-price"><?= number_format((float)$item['price'], 0) ?> грн</span>
                  <button
                    class="btn-add-cart"
                    data-category="<?= htmlspecialchars($item['table']) ?>"
                    data-id="<?= (int)$item['id'] ?>"
                  >Додати в кошик</button>
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
                  <img
                    src="<?= htmlspecialchars($item['image']) ?>"
                    alt="<?= htmlspecialchars($item['name']) ?>"
                    loading="lazy"
                  >
                  <div class="card-info">
                    <p class="card-name"><?= htmlspecialchars($item['name']) ?></p>
                    <?php if (!empty($item['description'])): ?>
                      <p class="card-desc"><?= htmlspecialchars(mb_substr($item['description'], 0, 72, 'UTF-8')) ?><?= mb_strlen($item['description'], 'UTF-8') > 72 ? '…' : '' ?></p>
                    <?php endif; ?>
                    <div class="card-footer">
                      <span class="card-price"><?= number_format((float)$item['price'], 0) ?> грн</span>
                      <button
                        class="btn-add-cart"
                        data-category="<?= htmlspecialchars($item['table']) ?>"
                        data-id="<?= (int)$item['id'] ?>"
                      >Додати в кошик</button>
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
            <img
              src="<?= htmlspecialchars($item['image']) ?>"
              alt="<?= htmlspecialchars($item['name']) ?>"
              loading="lazy"
            >
            <div class="card-info">
              <p class="card-name"><?= htmlspecialchars($item['name']) ?></p>
              <?php if (!empty($item['description'])): ?>
                <p class="card-desc"><?= htmlspecialchars(mb_substr($item['description'], 0, 72, 'UTF-8')) ?><?= mb_strlen($item['description'], 'UTF-8') > 72 ? '…' : '' ?></p>
              <?php endif; ?>
              <div class="card-footer">
                <span class="card-price"><?= number_format((float)$item['price'], 0) ?> грн</span>
                <button
                  class="btn-add-cart"
                  data-category="<?= htmlspecialchars($item['table']) ?>"
                  data-id="<?= (int)$item['id'] ?>"
                >Додати в кошик</button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="desserts-cta">
        <a href="../pages/menu.php?category=dessert_items" class="btn-outline">Дивитись всі десерти →</a>
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
    </div>
  </section>

</main>

<?php include '../includes/footer.php'; ?>
<script src="../static/js/header.js"></script>
<script src="../static/js/slider.js"></script>
<script src="../static/js/scroll-reveal.js"></script>
<script>
document.querySelectorAll('.btn-add-cart').forEach(btn => {
  btn.addEventListener('click', async e => {
    e.stopPropagation();
    const data = new FormData();
    data.append('category', btn.dataset.category);
    data.append('id',       btn.dataset.id);
    try {
      const res  = await fetch('../forms/add_to_cart.php', { method: 'POST', body: data });
      const json = await res.json();
      if (!json.ok) return;
      let badge = document.querySelector('.cart-count');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'cart-count';
        document.querySelector('.cart-wrapper')?.appendChild(badge);
      }
      badge.textContent = json.count;
      btn.classList.add('added');
      btn.textContent = '✓ Додано';
      if (window.showToast) window.showToast('✓ Додано до кошика');
      setTimeout(() => {
        btn.classList.remove('added');
        btn.textContent = 'Додати в кошик';
      }, 1500);
    } catch (_) {}
  });
});
</script>
</body>
</html>
