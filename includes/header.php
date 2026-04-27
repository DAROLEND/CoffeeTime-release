<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/helpers.php';

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
  <?php if (!empty($pageTitle)): ?>
    <title><?= htmlspecialchars($pageTitle) ?></title>
  <?php endif; ?>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../static/css/style.css?v=4">
  <link rel="stylesheet" href="../static/css/footer.css?v=4">
  <?php if (!empty($customStyles)): ?>
    <?php foreach ($customStyles as $style): ?>
      <?php
        $path = str_replace('../', '', $style);
        $full = dirname(__DIR__) . '/' . $path;
        $ver  = file_exists($full) ? filemtime($full) : 4;
      ?>
      <link rel="stylesheet" href="<?= htmlspecialchars($style) ?>?v=<?= $ver ?>">
    <?php endforeach; ?>
  <?php endif; ?>
  <link rel="stylesheet" href="../static/css/animations.css">
  <script defer src="../static/js/animations.js"></script>
  <style>
    /* ── Nav user dropdown ── */
    .nav-user-menu { position: relative; }
    .nav-user-trigger {
      display: flex; align-items: center; gap: 8px;
      padding: 6px 12px 6px 6px; border-radius: 25px;
      background: rgba(255,255,255,0.1);
      border: 1.5px solid rgba(255,255,255,0.3);
      cursor: pointer; transition: background 0.2s;
      font-family: inherit;
    }
    .nav-user-trigger:hover { background: rgba(255,255,255,0.2); }
    .nav-user-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: #FFC107; color: #5a2d0c;
      font-size: 13px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; letter-spacing: 0.5px;
    }
    .nav-user-name  { color: #fff; font-size: 14px; font-weight: 600; }
    .nav-user-arrow { color: rgba(255,255,255,0.7); font-size: 11px; transition: transform 0.2s; }
    .nav-user-menu.open .nav-user-arrow { transform: rotate(180deg); }

    .nav-dropdown {
      display: none;
      position: absolute; top: calc(100% + 8px); right: 0;
      background: #fff; border-radius: 12px;
      border: 1px solid #ede5dd;
      box-shadow: 0 8px 32px rgba(0,0,0,0.15);
      min-width: 200px; overflow: hidden; z-index: 200;
    }
    .nav-user-menu.open .nav-dropdown {
      display: block;
      animation: dropIn 0.2s cubic-bezier(0.34,1.56,0.64,1) both;
    }
    @keyframes dropIn {
      from { opacity: 0; transform: translateY(-8px) scale(0.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .nav-dd-item {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 16px; font-size: 14px; color: #333;
      text-decoration: none; transition: background 0.15s;
    }
    .nav-dd-item:hover { background: #faf5f0; color: #8B4513; }
    .nav-dd-divider { border-top: 1px solid #f0e8df; }
    .nav-dd-logout         { color: #c62828; }
    .nav-dd-logout:hover   { background: #ffebee; color: #c62828; }
  </style>
</head>

<header class="site-header">
  <div class="header__inner container d-flex justify-content-between align-items-center">
    <div class="logo">
      <a href="../pages/index.php">
        <img src="../static/images/main/logo.svg" alt="Coffee Time">
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
        <?php /* СЕРТИФІКАТИ — тимчасово приховано
        <li><a href="../pages/giftcards.php"   class="<?= ($page==='giftcards')   ? 'active' : '' ?>">Cертифікати</a></li>
        */ ?>
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
        <?php
          $navUser     = $_SESSION['user'];
          $navFirst    = $navUser['client_name']    ?? '';
          $navLast     = $navUser['client_surname'] ?? '';
          $navInitials = mb_strtoupper(mb_substr($navFirst, 0, 1, 'UTF-8') . mb_substr($navLast, 0, 1, 'UTF-8'), 'UTF-8');
          if (!trim($navInitials)) $navInitials = mb_strtoupper(mb_substr($navUser['login'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8');
          $navName = trim("$navFirst $navLast") ?: ($navUser['login'] ?? '');
        ?>
        <div class="nav-user-menu" id="navUserMenu">
          <button class="nav-user-trigger" id="navUserTrigger" type="button">
            <span class="nav-user-avatar"><?= h($navInitials) ?></span>
            <span class="nav-user-name"><?= h($navName) ?></span>
            <span class="nav-user-arrow">▾</span>
          </button>
          <div class="nav-dropdown" id="navDropdown">
            <a href="../pages/profile.php" class="nav-dd-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Мій профіль
            </a>
            <a href="../pages/profile.php?tab=orders" class="nav-dd-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/></svg>
              Мої замовлення
            </a>
            <div class="nav-dd-divider"></div>
            <a href="../pages/logout.php" class="nav-dd-item nav-dd-logout">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              Вийти
            </a>
          </div>
        </div>
        <script>
        (function(){
          var menu = document.getElementById('navUserMenu');
          var trigger = document.getElementById('navUserTrigger');
          if (!menu || !trigger) return;
          trigger.addEventListener('click', function(e){
            e.stopPropagation();
            menu.classList.toggle('open');
          });
          document.addEventListener('click', function(e){
            if (!menu.contains(e.target)) menu.classList.remove('open');
          });
        })();
        </script>
      <?php else: ?>
        <a href="../forms/login.php"    class="auth-link auth-link--ghost">Увійти</a>
        <a href="../forms/register.php" class="auth-link">Реєстрація</a>
      <?php endif; ?>
    </div>
  </div>
</header>
