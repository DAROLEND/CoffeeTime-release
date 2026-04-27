<?php
session_start();
require '../db/db.php';

$page      = 'gallery';
$pageTitle = 'Галерея — Coffee Time';
$customStyles = ['../static/css/gallery.css'];

/* ── Load photos from DB ── */
$photos = [];
$res = $conn->query("SELECT * FROM gallery ORDER BY created_at DESC");
if ($res) while ($row = $res->fetch_assoc()) $photos[] = $row;

$foodCount     = count(array_filter($photos, fn($p) => $p['category'] === 'food'));
$interiorCount = count(array_filter($photos, fn($p) => $p['category'] === 'interior'));

include '../includes/header.php';
?>

<main class="gallery-page">

  <!-- Hero -->
  <section class="gallery-hero">
    <h1 class="gallery-hero-title">Наша галерея</h1>
    <p class="gallery-hero-sub">Страви, атмосфера та затишок Coffee Time</p>
  </section>

  <!-- Filter tabs -->
  <div class="gallery-filters">
    <button class="gf-btn active" data-filter="all">
      Всі <span class="gf-count"><?= count($photos) ?></span>
    </button>
    <button class="gf-btn" data-filter="food">
      Їжа <span class="gf-count"><?= $foodCount ?></span>
    </button>
    <button class="gf-btn" data-filter="interior">
      Інтер'єр <span class="gf-count"><?= $interiorCount ?></span>
    </button>
  </div>

  <!-- Grid -->
  <div class="gallery-masonry" id="galleryGrid">
    <div class="g-sizer"></div>
    <?php foreach ($photos as $i => $photo): ?>
    <div class="g-item" data-cat="<?= $photo['category'] ?>" data-index="<?= $i ?>">
      <div class="g-inner">
        <img
          src="../static/images/gallery/<?= htmlspecialchars($photo['filename']) ?>"
          alt="<?= htmlspecialchars($photo['alt']) ?>"
          loading="lazy"
        >
        <div class="g-overlay">
          <svg class="g-zoom-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
            <circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="22" y2="22"/>
            <line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
          </svg>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <p class="gallery-empty" id="galleryEmpty" style="display:none">Немає фото у цій категорії</p>

</main>

<!-- Lightbox -->
<div class="lb-overlay" id="lbOverlay" role="dialog" aria-modal="true">
  <button class="lb-close" id="lbClose" aria-label="Закрити">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
  </button>
  <button class="lb-nav lb-prev" id="lbPrev" aria-label="Попереднє">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
  </button>
  <div class="lb-img-wrap">
    <img src="" alt="" id="lbImg" class="lb-img">
    <div class="lb-spinner" id="lbSpinner"></div>
  </div>
  <button class="lb-nav lb-next" id="lbNext" aria-label="Наступне">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
  </button>
  <div class="lb-footer">
    <span class="lb-counter" id="lbCounter"></span>
    <span class="lb-caption" id="lbCaption"></span>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://unpkg.com/imagesloaded@4/imagesloaded.pkgd.min.js"></script>
<script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>
<script src="../static/js/gallery.js"></script>
</body>
</html>
