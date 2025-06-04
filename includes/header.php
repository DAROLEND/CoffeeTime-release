<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$badgeCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $ci) {
        $badgeCount += isset($ci['quantity']) ? (int)$ci['quantity'] : 0;
    }
}
?>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/footer.css">
  <?php if (!empty($customStyles)): ?>
    <?php foreach ($customStyles as $style): ?>
      <link rel="stylesheet" href="<?= htmlspecialchars($style) ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>

<header class="site-header">
  <div class="header__inner container d-flex justify-content-between align-items-center">
    <div class="logo">
      <a href="../pages/index.php">
        <img src="../static/images/main/logo.png" alt="Coffee Time">
      </a>
    </div>

    <button class="menu-toggle" aria-label="Відкрити меню">
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="bar"></span>
    </button>

    <nav class="site-nav">
      <ul class="nav-list d-flex gap-3 mb-0">
        <li><a href="../pages/index.php"       class="<?= ($page==='home')        ? 'active' : '' ?>">Головна</a></li>
        <li><a href="../pages/menu.php"        class="<?= ($page==='menu')        ? 'active' : '' ?>">Меню</a></li>
        <li><a href="../pages/reservation.php" class="<?= ($page==='reservation') ? 'active' : '' ?>">Бронювати</a></li>
        <li><a href="../pages/giftcards.php"   class="<?= ($page==='giftcards')   ? 'active' : '' ?>">Cертифікати</a></li>
        <li><a href="../pages/gallery.php"     class="<?= ($page==='gallery')     ? 'active' : '' ?>">Галерея</a></li>
        <li><a href="../pages/reviews.php"     class="<?= ($page==='reviews')     ? 'active' : '' ?>">Відгуки</a></li>
      </ul>
    </nav>

    <div class="auth-buttons d-flex gap-2 align-items-center">
      <div class="cart-wrapper position-relative">
        <a href="../pages/cart.php">
          <img src="../static/images/main/cart.png" alt="Кошик">
          <?php if ($badgeCount > 0): ?>
            <span class="cart-count position-absolute top-0 start-100 translate-middle badge bg-danger">
              <?= $badgeCount ?>
            </span>
          <?php endif; ?>
        </a>
      </div>

      <?php if (isset($_SESSION['user'])): ?>
        <a href="../pages/profile.php" class="auth-link btn btn-outline-primary btn-sm">Профіль</a>
      <?php else: ?>
        <a href="../forms/login.php"    class="auth-link btn btn-outline-secondary btn-sm">Log in</a>
        <a href="../forms/register.php" class="auth-link btn btn-primary btn-sm">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</header>
