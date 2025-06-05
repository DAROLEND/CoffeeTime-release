<?php
session_start();
$page = 'gallery';
$pageTitle = 'Галерея — Coffee Time';
$customStyles = [
  '../static/css/gallery.css'
];
include '../includes/header.php';
?>

<main class="page-content">
  <h1 style="text-align: center;">Галерея</h1>

  <div class="gallery-grid" id="gallery">
    <div class="grid-sizer"></div>
    <?php for ($i = 1; $i <= 10; $i++): ?>
      <div class="gallery-item">
        <img src="../static/images/gallery/photo<?= $i ?>.png" alt="Фото <?= $i ?>">
      </div>
    <?php endfor; ?>
  </div>
</main>

<?php include '../includes/footer.php'; ?>

<script src="https://unpkg.com/imagesloaded@4/imagesloaded.pkgd.min.js"></script>
<script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>
<script src="../static/js/gallery.js"></script>
</body>
</html>
