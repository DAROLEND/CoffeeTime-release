<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';
$_S  = SITE_URL;   // full URL — for links, forms, emails
$_SP = SITE_PATH;  // root-relative path — for CSS/JS/images

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
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= $_SP ?>/static/css/style.css?v=7">
  <link rel="stylesheet" href="<?= $_SP ?>/static/css/footer.css?v=4">
  <?php if (!empty($customStyles)): ?>
    <?php foreach ($customStyles as $style): ?>
      <?php
        $path = str_replace('../', '', $style);
        $full = dirname(__DIR__) . '/' . $path;
        $ver  = file_exists($full) ? filemtime($full) : 4;
        $href = $_SP . '/' . $path;
      ?>
      <link rel="stylesheet" href="<?= h($href) ?>?v=<?= $ver ?>">
    <?php endforeach; ?>
  <?php endif; ?>
  <link rel="stylesheet" href="<?= $_SP ?>/static/css/animations.css">
  <script defer src="<?= $_SP ?>/static/js/animations.js"></script>
  <script defer src="<?= $_SP ?>/static/js/header.js"></script>
  <style>

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

    .cart-wrapper { position: relative; }
    .cart-mini-dropdown {
      position: absolute;
      top: calc(100% + 14px);
      right: -8px;
      width: 300px;
      background: #fff;
      border-radius: 16px;
      border: 1px solid #ede5dd;
      box-shadow: 0 12px 40px rgba(0,0,0,0.18);
      z-index: 500;
      opacity: 0;
      pointer-events: none;
      transform: translateY(-8px) scale(0.97);
      transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.34,1.4,0.64,1);
    }
    .cart-mini-dropdown::before {
      content: '';
      position: absolute;
      top: -6px; right: 22px;
      width: 12px; height: 12px;
      background: #fff;
      border-top: 1px solid #ede5dd;
      border-left: 1px solid #ede5dd;
      transform: rotate(45deg);
    }
    .cart-mini-dropdown.open {
      opacity: 1;
      pointer-events: auto;
      transform: translateY(0) scale(1);
    }
    .cmd-inner { padding: 16px; min-height: 80px; background: #faf5f0; border-radius: 16px; }
    .cmd-scroll-wrap {
      position: relative;
      background: #fff;
      border-radius: 10px;
      padding: 0 8px;
      margin: 0 -8px;
    }
    .cmd-scroll-wrap::after {
      content: '';
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 28px;
      background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.92));
      pointer-events: none;
      border-radius: 0 0 4px 4px;
    }
    @keyframes cmdFadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    .cmd-items, .cmd-footer, .cmd-empty {
      animation: cmdFadeIn 0.2s ease both;
    }
    .cmd-loading {
      display: flex; align-items: center; justify-content: center;
      padding: 20px;
    }
    @keyframes cmdSpin { to { transform: rotate(360deg); } }
    .cmd-spinner {
      width: 24px; height: 24px;
      border: 2.5px solid #f0e8df;
      border-top-color: #8B4513;
      border-radius: 50%;
      animation: cmdSpin 0.7s linear infinite;
    }
    .cmd-item {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 0;
      border-bottom: 1px solid #f5ede4;
      font-size: 13px;
      transition: opacity 0.22s ease, transform 0.22s ease, max-height 0.3s ease,
                  padding 0.3s ease, border-color 0.3s ease;
      overflow: hidden;
    }
    .cmd-item.removing {
      opacity: 0;
      transform: translateX(18px);
      max-height: 0 !important;
      padding-top: 0; padding-bottom: 0;
      border-color: transparent;
    }
    .cmd-item:last-child { border-bottom: none; }
    .cmd-item-img {
      width: 40px; height: 40px; border-radius: 8px;
      object-fit: cover; flex-shrink: 0;
      background: #f5ede4;
    }
    .cmd-item-placeholder {
      width: 40px; height: 40px; border-radius: 8px;
      flex-shrink: 0; background: #f5ede4;
      display: flex; align-items: center; justify-content: center;
      color: #c9b9a8;
    }
    .cmd-items {
      max-height: 280px;
      overflow-y: auto;
      overscroll-behavior: contain;
      scrollbar-width: thin;
      scrollbar-color: #e0d0c4 transparent;
      padding-right: 12px;
      margin-right: -12px;
    }
    .cmd-items::-webkit-scrollbar { width: 4px; }
    .cmd-items::-webkit-scrollbar-track { background: transparent; }
    .cmd-items::-webkit-scrollbar-thumb { background: #e0d0c4; border-radius: 4px; }
    .cmd-item-info { flex: 1; min-width: 0; }
    .cmd-item-name {
      font-weight: 600; color: #2c1810;
      overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
    }
    .cmd-item-qty { font-size: 12px; color: #aaa; margin-top: 2px; }
    .cmd-item-price { font-weight: 700; color: #8B4513; white-space: nowrap; flex-shrink: 0; }
    .cmd-remove-btn {
      background: none; border: none; cursor: pointer;
      color: #ccc; font-size: 18px; line-height: 1;
      padding: 0 2px; flex-shrink: 0;
      transition: color 0.15s;
    }
    .cmd-remove-btn:hover { color: #c62828; }
    .cmd-more { font-size: 12px; color: #aaa; text-align: center; padding: 6px 0 2px; }
    .cmd-empty p { text-align: center; color: #aaa; font-size: 14px; padding: 16px 0 8px; margin: 0; }
    .cmd-footer {
      margin: 0 -16px -16px;
      padding: 14px 16px 16px;
      background: #faf5f0;
      border-radius: 0 0 16px 16px;
    }
    .cmd-total {
      display: flex; justify-content: space-between; align-items: center;
      font-size: 14px; color: #555; margin-bottom: 10px;
    }
    .cmd-total-price { font-size: 16px; font-weight: 700; color: #8B4513; }
    .cmd-checkout-btn {
      display: block; padding: 10px;
      background: #FFC107; color: #5a2d0c;
      text-align: center; border-radius: 25px;
      font-weight: 700; font-size: 14px;
      text-decoration: none;
      transition: background 0.2s ease, transform 0.15s ease;
    }
    .cmd-checkout-btn:hover { background: #e6ac00; transform: translateY(-1px); }
  </style>
<?php if (!empty($_ENV['GA_ID']) || !empty(getenv('GA_ID'))): ?>
<?php $gaId = $_ENV['GA_ID'] ?? getenv('GA_ID'); ?>
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaId) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= htmlspecialchars($gaId) ?>');
</script>
<?php endif; ?>
</head>

<header class="site-header">
  <div class="header__inner container d-flex justify-content-between align-items-center">
    <div class="logo">
      <a href="<?= $_S ?>/pages/index.php">
        <img src="<?= $_SP ?>/static/images/main/logo.svg" alt="Coffee Time">
      </a>
    </div>

    <nav class="site-nav">
      <ul class="nav-list d-flex gap-3 mb-0">
        <?php $page ??= ''; ?>
        <li><a href="<?= $_S ?>/pages/index.php"       class="<?= ($page==='home')        ? 'active' : '' ?>">Головна</a></li>
        <li><a href="<?= $_S ?>/pages/menu.php"        class="<?= ($page==='menu')        ? 'active' : '' ?>">Меню</a></li>
        <li><a href="<?= $_S ?>/pages/gallery.php"     class="<?= ($page==='gallery')     ? 'active' : '' ?>">Галерея</a></li>
        <li><a href="<?= $_S ?>/pages/reviews.php"     class="<?= ($page==='reviews')     ? 'active' : '' ?>">Відгуки</a></li>
      </ul>
    </nav>

    <div class="auth-buttons d-flex gap-2 align-items-center">
      <button class="menu-toggle" aria-label="Відкрити меню">
        <span class="bar"></span>
        <span class="bar"></span>
        <span class="bar"></span>
      </button>
      <div class="cart-wrapper position-relative" id="cartDropdownTrigger">
        <a href="<?= $_S ?>/pages/cart.php">
          <img src="<?= $_SP ?>/static/images/main/cart.png" alt="Кошик">
          <?php if ($badgeCount > 0): ?>
            <span class="cart-count position-absolute top-0 start-100 translate-middle badge bg-danger" id="navCartBadge">
              <?= $badgeCount ?>
            </span>
          <?php endif; ?>
        </a>
        <div class="cart-mini-dropdown" id="cartMiniDropdown" aria-hidden="true">
          <div class="cmd-inner">
            <div class="cmd-loading">
              <div class="cmd-spinner"></div>
            </div>
            <div class="cmd-scroll-wrap"><div class="cmd-items" style="display:none"></div></div>
            <div class="cmd-empty" style="display:none">
              <p>Кошик порожній</p>
            </div>
            <div class="cmd-footer" style="display:none">
              <div class="cmd-total">
                <span>Разом:</span>
                <strong class="cmd-total-price"></strong>
              </div>
              <a href="<?= $_S ?>/pages/cart.php" class="cmd-checkout-btn">Перейти до кошика</a>
            </div>
          </div>
        </div>
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
            <a href="<?= $_S ?>/pages/profile.php" class="nav-dd-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/></svg>
              Мої замовлення
            </a>
            <a href="<?= $_S ?>/pages/profile.php?tab=settings" class="nav-dd-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
              Налаштування
            </a>
            <div class="nav-dd-divider"></div>
            <a href="<?= $_S ?>/pages/logout.php" class="nav-dd-item nav-dd-logout">
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
        <a href="<?= $_S ?>/forms/login.php"    class="auth-link auth-link--ghost">Увійти</a>
        <a href="<?= $_S ?>/forms/register.php" class="auth-link">Реєстрація</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<script>
(function(){
  var trigger  = document.getElementById('cartDropdownTrigger');
  var dropdown = document.getElementById('cartMiniDropdown');
  if (!trigger || !dropdown) return;

  var loaded    = false;
  var hideTimer = null;

  function openDropdown() {
    clearTimeout(hideTimer);
    dropdown.classList.add('open');
    loadCart();
  }

  function scheduleClose() {
    clearTimeout(hideTimer);
    hideTimer = setTimeout(function(){ dropdown.classList.remove('open'); }, 280);
  }

  // Disable hover dropdown on touch devices
  if (window.matchMedia('(hover: hover)').matches) {
    trigger.addEventListener('mouseenter', openDropdown);
    trigger.addEventListener('mouseleave', scheduleClose);
    dropdown.addEventListener('mouseenter', function(){ clearTimeout(hideTimer); });
    dropdown.addEventListener('mouseleave', scheduleClose);
  }

  // Передзавантажуємо вміст у фоні — щоб перший показ був плавним
  setTimeout(loadCart, 900);

  // Коли додано/видалено товар — перезавантажуємо превью
  window.addEventListener('cartUpdated', function() {
    loaded = false;
    if (dropdown.classList.contains('open')) {
      resetUI();
      loadCart();
    }
  });

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function resetUI() {
    var loading = dropdown.querySelector('.cmd-loading');
    var itemsEl = dropdown.querySelector('.cmd-items');
    var emptyEl = dropdown.querySelector('.cmd-empty');
    var footer  = dropdown.querySelector('.cmd-footer');
    if (loading) loading.style.display = '';
    if (itemsEl) { itemsEl.innerHTML = ''; itemsEl.style.display = 'none'; }
    if (emptyEl) emptyEl.style.display = 'none';
    if (footer)  footer.style.display  = 'none';
  }

  function renderCart(data) {
    var loading = dropdown.querySelector('.cmd-loading');
    var itemsEl = dropdown.querySelector('.cmd-items');
    var emptyEl = dropdown.querySelector('.cmd-empty');
    var footer  = dropdown.querySelector('.cmd-footer');

    if (loading) loading.style.display = 'none';

    if (!data.ok || !data.items || data.items.length === 0) {
      if (emptyEl) emptyEl.style.display = '';
      if (footer)  footer.style.display  = 'none';
      return;
    }

    var base = '<?= $_S ?>/';
    var noPhotoSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>';
    var blank1px = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    var html = '';
    data.items.forEach(function(it) {
      var thumb = it.image
        ? '<img class="cmd-item-img" src="' + base + escHtml(it.image) + '" alt=""'
          + ' onerror="this.onerror=null;this.src=\'' + blank1px + '\'">'
        : '<span class="cmd-item-placeholder">' + noPhotoSvg + '</span>';
      html += '<div class="cmd-item">'
        + thumb
        + '<div class="cmd-item-info">'
        + '<div class="cmd-item-name">' + escHtml(it.name) + '</div>'
        + '<div class="cmd-item-qty">' + it.qty + ' \u0448\u0442.</div>'
        + '</div>'
        + '<div class="cmd-item-price">' + Math.round(it.price * it.qty) + ' \u20b4</div>'
        + '<button class="cmd-remove-btn" data-si="' + it.session_index + '" title="\u0412\u0438\u0434\u0430\u043b\u0438\u0442\u0438">\u00d7</button>'
        + '</div>';
    });
    if (itemsEl) { itemsEl.innerHTML = html; itemsEl.style.display = ''; }
    if (emptyEl) emptyEl.style.display = 'none';

    var totalEl = dropdown.querySelector('.cmd-total-price');
    if (totalEl) totalEl.textContent = Math.round(data.total) + ' \u20b4';
    if (footer)  footer.style.display = '';

    itemsEl.querySelectorAll('.cmd-remove-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault(); e.stopPropagation();
        removeItem(parseInt(this.dataset.si, 10));
      });
    });
  }

  function loadCart() {
    if (loaded) return;
    loaded = true;
    fetch('<?= $_S ?>/forms/get_cart_preview.php')
      .then(function(r) { return r.json(); })
      .then(renderCart)
      .catch(function() { loaded = false; });
  }

  function removeItem(si) {
    var row = dropdown.querySelector('.cmd-remove-btn[data-si="' + si + '"]');
    if (row) {
      var item = row.closest('.cmd-item');
      if (item) {
        item.style.maxHeight = item.offsetHeight + 'px';
        void item.offsetWidth;
        item.classList.add('removing');
      }
    }
    var fd = new FormData();
    fd.append('session_index', si);
    fetch('<?= $_S ?>/forms/remove_from_cart.php', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok) return;
        var badge = document.getElementById('navCartBadge');
        if (badge) {
          if (data.cart_count > 0) {
            badge.textContent = data.cart_count;
            badge.style.display = '';
          } else {
            badge.style.display = 'none';
          }
        }
        loaded = false;
        resetUI();
        loadCart();
      })
      .catch(function() {});
  }
})();
</script>
