<?php
require_once '../includes/session.php';
require_once '../includes/error_handler.php';
require '../db/db.php';
require_once '../includes/helpers.php';

$page         = 'reviews';
$pageTitle    = 'Відгуки — Coffee Time';
$customStyles = ['../static/css/reviews.css'];

/* ── Параметри ────────────────────────────────────── */
$sortOptions = ['newest', 'oldest', 'best', 'worst'];
$sort   = in_array($_GET['sort'] ?? '', $sortOptions) ? $_GET['sort'] : 'newest';
$filter = isset($_GET['filter']) ? (int)$_GET['filter'] : 0;
if ($filter < 0 || $filter > 5) $filter = 0;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 6;

/* ── POST: додати відгук ──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!isset($_SESSION['user'])) {
        header('Location: reviews.php');
        exit;
    }
    $name   = trim($_POST['name'] ?? '');
    $text   = trim($_POST['text'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);

    if (mb_strlen($name) >= 2 && mb_strlen($text) >= 10 && $rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO site_reviews (name, text, rating) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $name, $text, $rating);
        $stmt->execute();
        $stmt->close();
        $_SESSION['reviewed'] = true;
        header('Location: reviews.php?success=1&sort=' . urlencode($sort));
        exit;
    }
}

$showSuccess = isset($_GET['success']);

/* ── Статистика (всі відгуки) ─────────────────────── */
$statsRow   = $conn->query("SELECT ROUND(AVG(rating),1) as avg_r, COUNT(*) as total FROM site_reviews")->fetch_assoc();
$avgRating  = (float)($statsRow['avg_r']  ?? 0);
$totalCount = (int)($statsRow['total'] ?? 0);

$distribution = [];
for ($i = 5; $i >= 1; $i--) {
    $ds = $conn->prepare("SELECT COUNT(*) as cnt FROM site_reviews WHERE rating = ?");
    $ds->bind_param('i', $i);
    $ds->execute();
    $distribution[$i] = (int)$ds->get_result()->fetch_assoc()['cnt'];
    $ds->close();
}

/* ── Відфільтровані + пагіновані відгуки ─────────── */
$orderMap = [
    'newest' => 'created_at DESC',
    'oldest' => 'created_at ASC',
    'best'   => 'rating DESC, created_at DESC',
    'worst'  => 'rating ASC, created_at DESC',
];
$orderSQL = $orderMap[$sort]; // safe: validated against whitelist above

// Use parameterized queries — $filter is safe as int but we bind it anyway
if ($filter > 0) {
    $cs = $conn->prepare("SELECT COUNT(*) as cnt FROM site_reviews WHERE rating = ?");
    $cs->bind_param('i', $filter);
    $cs->execute();
    $filteredTotal = (int)$cs->get_result()->fetch_assoc()['cnt'];
    $cs->close();
} else {
    $filteredTotal = $totalCount;
}

$totalPages = max(1, (int)ceil($filteredTotal / $perPage));
$pageNum    = min($pageNum, $totalPages);
$offset     = ($pageNum - 1) * $perPage;

$reviews = [];
if ($filter > 0) {
    $rs = $conn->prepare("SELECT name, text, rating, created_at FROM site_reviews WHERE rating = ? ORDER BY $orderSQL LIMIT ? OFFSET ?");
    $rs->bind_param('iii', $filter, $perPage, $offset);
} else {
    $rs = $conn->prepare("SELECT name, text, rating, created_at FROM site_reviews ORDER BY $orderSQL LIMIT ? OFFSET ?");
    $rs->bind_param('ii', $perPage, $offset);
}
$rs->execute();
$res = $rs->get_result();
while ($row = $res->fetch_assoc()) $reviews[] = $row;
$rs->close();

/* ── Хелпери ──────────────────────────────────────── */
function avatarColor(string $name): string {
    $code    = mb_ord(mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8'), 'UTF-8');
    $palette = ['#d4a96a', '#8B4513', '#FFC107', '#c4956a'];
    return $palette[$code % 4];
}

function buildUrl(array $overrides): string {
    $base = ['sort' => $_GET['sort'] ?? 'newest', 'filter' => (int)($_GET['filter'] ?? 0), 'p' => 1];
    $params = array_merge($base, $overrides);
    if ((int)$params['filter'] === 0)  unset($params['filter']);
    if ($params['sort'] === 'newest')  unset($params['sort']);
    if ((int)($params['p'] ?? 1) <= 1) unset($params['p']);
    return 'reviews.php' . (empty($params) ? '' : '?' . http_build_query($params));
}

function renderStarsHtml(float $rating): string {
    $full  = (int)floor($rating);
    $empty = 5 - $full;
    return str_repeat('<span class="star-full">★</span>', $full)
         . str_repeat('<span class="star-empty">★</span>', $empty);
}
?>
<!DOCTYPE html>
<html lang="uk">
<?php include '../includes/header.php'; ?>
<body>
<main class="reviews-page">

  <!-- ═══ HEADER + STATS ═══ -->
  <section class="rv-header">
    <div class="container">
      <h1 class="rv-title">Відгуки наших клієнтів</h1>

      <?php if ($totalCount > 0): ?>
      <div class="rv-summary">

        <div class="rv-avg">
          <span class="rv-avg-number" data-value="<?= number_format($avgRating, 1, '.', '') ?>"><?= number_format($avgRating, 1, '.', '') ?></span>
          <div class="rv-avg-stars"><?= renderStarsHtml($avgRating) ?></div>
          <span class="rv-avg-label">
            на основі <?= $totalCount ?>
            <?= ($totalCount % 10 === 1 && $totalCount % 100 !== 11) ? 'відгуку' : 'відгуків' ?>
          </span>
        </div>

        <div class="rv-bars">
          <?php for ($i = 5; $i >= 1; $i--):
            $pct = $totalCount > 0 ? round($distribution[$i] / $totalCount * 100) : 0;
          ?>
          <div class="rv-bar-row">
            <span class="rv-bar-label"><?= $i ?>★</span>
            <div class="rv-bar-track">
              <div class="rv-bar-fill" style="width:0" data-width="<?= $pct ?>"></div>
            </div>
            <span class="rv-bar-pct"><?= $pct ?>%</span>
          </div>
          <?php endfor; ?>
        </div>

      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ═══ CONTROLS ═══ -->
  <section class="rv-controls">
    <div class="container">

      <div class="rv-controls-top">
        <div class="rv-tabs">
          <?php
          $tabs = ['newest' => 'Нові', 'oldest' => 'Старі', 'best' => 'Висока оцінка', 'worst' => 'Низька оцінка'];
          foreach ($tabs as $val => $label): ?>
            <a href="<?= buildUrl(['sort' => $val, 'p' => 1]) ?>"
               class="rv-tab<?= $sort === $val ? ' rv-tab--active' : '' ?>"><?= $label ?></a>
          <?php endforeach; ?>
        </div>

        <a href="https://www.google.com/maps/search/Coffee+Time" target="_blank" class="rv-google-link">
          Більше відгуків на Google Maps
        </a>
      </div>

      <div class="rv-filter-tabs">
        <span class="rv-filter-label">Фільтр:</span>
        <a href="<?= buildUrl(['filter' => 0, 'p' => 1]) ?>"
           class="rv-tab<?= $filter === 0 ? ' rv-tab--active' : '' ?>">Всі</a>
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <a href="<?= buildUrl(['filter' => $i, 'p' => 1]) ?>"
             class="rv-tab<?= $filter === $i ? ' rv-tab--active' : '' ?>"><?= str_repeat('★', $i) ?></a>
        <?php endfor; ?>
      </div>

    </div>
  </section>

  <!-- ═══ GRID ═══ -->
  <section class="rv-grid-section">
    <div class="container">

      <?php if (empty($reviews)): ?>
        <p class="rv-empty">Відгуків не знайдено.</p>
      <?php else: ?>
      <div class="rv-grid">
        <?php foreach ($reviews as $r):
          $initial = mb_strtoupper(mb_substr($r['name'], 0, 1, 'UTF-8'), 'UTF-8');
          $color   = avatarColor($r['name']);
          $date    = date('d.m.Y', strtotime($r['created_at']));
          $full    = (int)$r['rating'];
          $rawText = $r['text'];
          $isLong  = mb_strlen($rawText) > 120;
          $short   = $isLong ? htmlspecialchars(mb_substr($rawText, 0, 120)) . '...' : htmlspecialchars($rawText);
        ?>
        <div class="rv-card">
          <div class="rv-card-top">
            <div class="rv-avatar" style="background:<?= $color ?>"><?= $initial ?></div>
            <span class="rv-name"><?= htmlspecialchars($r['name']) ?></span>
            <span class="rv-date"><?= $date ?></span>
          </div>
          <div class="rv-stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <span class="<?= $i <= $full ? 'star-full' : 'star-empty' ?>">★</span>
            <?php endfor; ?>
          </div>
          <p class="rv-text"
             data-full="<?= htmlspecialchars($rawText) ?>"
             data-short="<?= $short ?>"
             data-long="<?= $isLong ? '1' : '0' ?>"><?= $short ?></p>
          <?php if ($isLong): ?>
            <button class="rv-read-more" type="button">читати далі</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Пагінація -->
      <?php if ($totalPages > 1): ?>
      <nav class="rv-pagination">
        <?php if ($pageNum > 1): ?>
          <a href="<?= buildUrl(['p' => $pageNum - 1]) ?>" class="rv-page-btn">← Попередня</a>
        <?php else: ?>
          <span class="rv-page-btn rv-page-btn--disabled">← Попередня</span>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="<?= buildUrl(['p' => $i]) ?>"
             class="rv-page-btn<?= $i === $pageNum ? ' rv-page-btn--active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pageNum < $totalPages): ?>
          <a href="<?= buildUrl(['p' => $pageNum + 1]) ?>" class="rv-page-btn">Наступна →</a>
        <?php else: ?>
          <span class="rv-page-btn rv-page-btn--disabled">Наступна →</span>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

    </div>
  </section>

  <!-- ═══ ФОРМА ═══ -->
  <section class="rv-form-section">
    <div class="container">

      <?php if ($showSuccess): ?>
        <div class="rv-success" id="successMsg">
          ✓ Дякуємо! Ваш відгук успішно додано.
        </div>
      <?php endif; ?>

      <?php if (!isset($_SESSION['user'])): ?>
        <!-- Не авторизований -->
        <div class="rv-auth-prompt">
          <p class="rv-auth-text">Увійдіть щоб залишити відгук</p>
          <a href="../forms/login.php" class="rv-auth-btn">Увійти</a>
        </div>

      <?php elseif (!empty($_SESSION['reviewed'])): ?>
        <!-- Вже залишив відгук -->
        <div class="rv-already">
          <span class="rv-already-icon">✓</span>
          <p>Ви вже залишили відгук. Дякуємо за вашу думку!</p>
        </div>

      <?php else: ?>
        <!-- Форма -->
        <div class="rv-form-wrap">
          <h2>Залишити свій відгук</h2>
          <form method="post" class="rv-form" id="reviewForm" novalidate>
            <?= csrf_field() ?>

            <div class="rv-field" id="starsField">
              <label class="rv-label">Оцінка:</label>
              <div class="rv-star-picker" id="starPicker">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="rv-star" data-value="<?= $i ?>">★</span>
                <?php endfor; ?>
              </div>
              <input type="hidden" name="rating" id="ratingInput" value="0">
              <p class="rv-star-hint" id="starHint">&nbsp;</p>
              <p class="rv-field-error" id="ratingError" hidden>Оберіть оцінку</p>
            </div>

            <div class="rv-field" id="nameField">
              <label class="rv-label" for="reviewName">Ваше ім'я:</label>
              <input type="text" name="name" id="reviewName" class="rv-input"
                     placeholder="Ваше ім'я (мінімум 2 символи)" autocomplete="name">
              <p class="rv-field-error" id="nameError" hidden>Ім'я занадто коротке</p>
            </div>

            <div class="rv-field" id="textField">
              <label class="rv-label" for="reviewText">Відгук:</label>
              <textarea name="text" id="reviewText" class="rv-input rv-textarea"
                        placeholder="Розкажіть що вам сподобалось або що можна покращити... (мінімум 10 символів)"></textarea>
              <p class="rv-field-error" id="textError" hidden>Відгук занадто короткий</p>
            </div>

            <button type="submit" class="rv-submit">Надіслати</button>
          </form>
        </div>
      <?php endif; ?>

    </div>
  </section>

</main>

<?php include '../includes/footer.php'; ?>
<script src="../static/js/header.js"></script>
<script src="../static/js/reviews.js"></script>
</body>
</html>
