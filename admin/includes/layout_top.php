<?php
require_once __DIR__ . '/../../includes/icons.php';
$adminUsername = $_SESSION['admin'] ?? 'Admin';
$adminInitial  = strtoupper(substr($adminUsername, 0, 1));

// New orders count for badges — only for admins with orders access
$newOrdersCount = 0;
$notifOrders = [];
$canSeeOrders = has_perm('orders_view') || ($_SESSION['admin_role'] ?? '') === 'super';
if ($canSeeOrders) {
    try {
        $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='new'");
        if ($r) $newOrdersCount = (int)$r->fetch_assoc()['c'];
    } catch (Exception $e) {}

    try {
        $r = $conn->query("
            SELECT order_id, customer_name, customer_surname, total, created_at
            FROM orders WHERE status='new'
            ORDER BY created_at DESC LIMIT 5
        ");
        if ($r) while ($row = $r->fetch_assoc()) $notifOrders[] = $row;
    } catch (Exception $e) {}
}

// Recent reviews count
$pendingReviews = 0;
try {
    $r = $conn->query("SELECT COUNT(*) AS c FROM site_reviews WHERE created_at > NOW() - INTERVAL 7 DAY");
    if ($r) $pendingReviews = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'Адмін') ?> — Coffee Time</title>
  <link rel="stylesheet" href="../static/css/admin.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/uk.js"></script>
  <script>
  window.fpBuildYearSelect = function(sd, ds, fp) {
    var wrapper   = fp.calendarContainer.querySelector('.numInputWrapper');
    var yearInput = fp.calendarContainer.querySelector('input.cur-year');
    if (!wrapper || !yearInput) return;

    yearInput.style.display = 'none';
    wrapper.querySelectorAll('span').forEach(function(s) { s.style.display = 'none'; });

    var cur = fp.currentYear;
    var sel = document.createElement('select');
    sel.className = 'fp-year-sel';
    for (var y = cur - 20; y <= cur + 5; y++) {
      var o = document.createElement('option');
      o.value = y; o.textContent = y;
      if (y === cur) o.selected = true;
      sel.appendChild(o);
    }
    wrapper.appendChild(sel);

    sel.addEventListener('change', function() { fp.changeYear(+this.value); });

    fp.calendarContainer.querySelectorAll('.flatpickr-prev-month,.flatpickr-next-month')
      .forEach(function(btn) {
        btn.addEventListener('click', function() {
          setTimeout(function() { sel.value = fp.currentYear; }, 30);
        });
      });
  };
  </script>
</head>
<body>
<div class="admin-wrapper">

  <!-- ═══════════ SIDEBAR ═══════════ -->
  <aside class="admin-sidebar" id="adminSidebar">

    <!-- Collapse tab — lives outside .sidebar-inner so overflow:visible on aside works -->
    <button class="sidebar-collapser" id="sidebarToggle" title="Згорнути/розгорнути">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
    </button>

  <div class="sidebar-inner">

    <div class="sidebar-brand">
      <img src="../static/images/main/logo-cup.svg" alt="Coffee Time" class="sidebar-logo">
      <div class="sidebar-subtitle">Панель адміна</div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Головне</div>

      <a href="dashboard.php" class="sidebar-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>" data-tooltip="Головна">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
        <span class="sl-text">Головна</span>
      </a>

      <?php if (has_perm('orders_view')): ?>
      <a href="orders.php" class="sidebar-link <?= ($activePage ?? '') === 'orders' ? 'active' : '' ?>" data-tooltip="Замовлення">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
          <line x1="3" y1="6" x2="21" y2="6"/>
          <path d="M16 10a4 4 0 0 1-8 0"/>
        </svg>
        <span class="sl-text">Замовлення</span>
        <?php if ($newOrdersCount > 0): ?>
          <span class="nav-badge orders-badge<?= $newOrdersCount > 0 ? ' pulse' : '' ?>"><?= $newOrdersCount ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <?php if (has_perm('products')): ?>
      <a href="manage_items.php" class="sidebar-link <?= ($activePage ?? '') === 'products' ? 'active' : '' ?>" data-tooltip="Товари">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
        </svg>
        <span class="sl-text">Товари</span>
      </a>
      <a href="admin_sauces.php" class="sidebar-link <?= ($activePage ?? '') === 'sauces' ? 'active' : '' ?>" data-tooltip="Соуси">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
          <path d="M8 12s1.5 2 4 2 4-2 4-2"/>
          <line x1="9" y1="9" x2="9.01" y2="9"/>
          <line x1="15" y1="9" x2="15.01" y2="9"/>
        </svg>
        <span class="sl-text">Соуси</span>
      </a>
      <?php endif; ?>

      <?php if (is_super()): ?>
      <div class="nav-section-label">Адміністрація</div>
      <a href="admin_users.php" class="sidebar-link <?= ($activePage ?? '') === 'admin_users' ? 'active' : '' ?>" data-tooltip="Персонал">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        <span class="sl-text">Персонал</span>
      </a>
      <?php endif; ?>

      <?php if (has_perm('reviews') || has_perm('content')): ?>
      <div class="nav-section-label">Контент</div>
      <?php endif; ?>

      <?php if (has_perm('reviews')): ?>
      <a href="admin_reviews.php" class="sidebar-link <?= ($activePage ?? '') === 'reviews' ? 'active' : '' ?>" data-tooltip="Відгуки">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        <span class="sl-text">Відгуки</span>
        <?php if ($pendingReviews > 0): ?>
          <span class="nav-badge"><?= $pendingReviews ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <?php if (has_perm('content')): ?>
      <a href="admin_gallery.php" class="sidebar-link <?= ($activePage ?? '') === 'gallery' ? 'active' : '' ?>" data-tooltip="Галерея">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <polyline points="21 15 16 10 5 21"/>
        </svg>
        <span class="sl-text">Галерея</span>
      </a>
      <a href="hero_slides.php" class="sidebar-link <?= ($activePage ?? '') === 'hero_slides' ? 'active' : '' ?>" data-tooltip="Хіро слайдер">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="7" width="20" height="10" rx="2"/>
          <path d="M17 7V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v2"/>
          <polyline points="9 12 12 15 15 12"/>
        </svg>
        <span class="sl-text">Хіро слайдер</span>
      </a>
      <a href="about_section.php" class="sidebar-link <?= ($activePage ?? '') === 'about_section' ? 'active' : '' ?>" data-tooltip="Про нас">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span class="sl-text">Про нас</span>
      </a>
      <a href="dessert_banner.php" class="sidebar-link <?= ($activePage ?? '') === 'dessert_banner' ? 'active' : '' ?>" data-tooltip="Десерт дня">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8h1a4 4 0 0 1 0 8h-1"/>
          <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>
          <line x1="6" y1="1" x2="6" y2="4"/>
          <line x1="10" y1="1" x2="10" y2="4"/>
          <line x1="14" y1="1" x2="14" y2="4"/>
        </svg>
        <span class="sl-text">Десерт дня</span>
      </a>
      <?php endif; ?>

    </nav>

    <div class="sidebar-bottom">
      <div class="sidebar-avatar" id="sidebarAvatar"><?= htmlspecialchars($adminInitial) ?></div>
      <div class="sidebar-bottom-info">
        <span class="sidebar-admin-name"><?= htmlspecialchars($adminUsername) ?></span>
        <a href="logout.php" class="sidebar-logout-inline">Вийти</a>
      </div>
    </div>

  </div><!-- /.sidebar-inner -->

  </aside>
  <!-- ═══════════ /SIDEBAR ═══════════ -->

  <!-- Inline: apply collapsed state before first paint to prevent flash -->
  <script>
  (function(){
    var sb = document.getElementById('adminSidebar');
    if (!sb || window.innerWidth <= 700) return;
    if (localStorage.getItem('sb_collapsed') !== '0') {
      sb.classList.add('collapsed', 'sb-notransition');
    }
    requestAnimationFrame(function(){
      requestAnimationFrame(function(){ sb.classList.remove('sb-notransition'); });
    });
  })();
  </script>

  <!-- Mobile overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="admin-main">

    <!-- ── Top bar ── -->
    <header class="admin-topbar">
      <div style="display:flex;align-items:center;gap:8px">
        <button class="topbar-hamburger" id="hamburgerBtn" aria-label="Меню">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="3" y1="6"  x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
        <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></div>
      </div>
      <div class="topbar-right">

        <!-- ── Notifications bell (orders access only) ── -->
        <?php if ($canSeeOrders): ?>
        <div class="notif-wrap" id="notifWrap">
          <button class="topbar-bell" id="notifBtn" title="Нові замовлення" type="button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <?php if ($newOrdersCount > 0): ?>
            <span class="topbar-bell-badge orders-badge"><?= $newOrdersCount ?></span>
            <?php endif; ?>
          </button>

          <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-header">
              <span class="notif-title">Нові замовлення</span>
              <?php if ($newOrdersCount > 0): ?>
                <span class="notif-count"><?= $newOrdersCount ?></span>
              <?php endif; ?>
            </div>
            <div class="notif-list">
              <?php if (empty($notifOrders)): ?>
                <div class="notif-empty">Нових замовлень немає</div>
              <?php else: ?>
                <?php foreach ($notifOrders as $no):
                  $nName = trim(($no['customer_name'] ?? '') . ' ' . ($no['customer_surname'] ?? '')) ?: 'Анонім';
                  $nTime = date('H:i', strtotime($no['created_at']));
                  $isToday = date('Y-m-d', strtotime($no['created_at'])) === date('Y-m-d');
                  $nDateLabel = $isToday ? $nTime : date('d.m H:i', strtotime($no['created_at']));
                ?>
                <a href="view_order.php?id=<?= $no['order_id'] ?>" class="notif-item">
                  <div class="notif-item__dot"></div>
                  <div class="notif-item__body">
                    <span class="notif-item__id">#<?= $no['order_id'] ?></span>
                    <span class="notif-item__name"><?= htmlspecialchars($nName) ?></span>
                    <span class="notif-item__price"><?= number_format((float)$no['total'], 0, ',', ' ') ?> ₴</span>
                  </div>
                  <span class="notif-item__time"><?= $nDateLabel ?></span>
                </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <a href="orders.php" class="notif-footer">Переглянути всі замовлення →</a>
          </div>
        </div>
        <!-- ── /Notifications bell ── -->
        <?php endif; ?>

        <div class="topbar-admin">
          <div class="topbar-admin-avatar"><?= htmlspecialchars($adminInitial) ?></div>
          <span class="topbar-admin-name"><?= htmlspecialchars($adminUsername) ?></span>
        </div>
      </div>
    </header>
    <!-- ── /Top bar ── -->

    <main class="admin-content">

<script>
(function() {
  var btn      = document.getElementById('notifBtn');
  var dropdown = document.getElementById('notifDropdown');
  if (!btn || !dropdown) return;

  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    dropdown.classList.toggle('notif-dropdown--open');
  });

  document.addEventListener('click', function(e) {
    if (!document.getElementById('notifWrap').contains(e.target)) {
      dropdown.classList.remove('notif-dropdown--open');
    }
  });

  var knownCount = parseInt('<?= (int)$newOrdersCount ?>', 10) || 0;

  function playBeep() {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.type = 'sine'; osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.18, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
      osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.35);
    } catch (e) {}
  }

  function showNewOrderToast(count) {
    var el = document.createElement('div');
    el.className = 'admin-toast success';
    el.innerHTML = 'Нове замовлення! Всього нових: <strong>' + count + '</strong> &nbsp;<a href="orders.php" style="color:#fff;text-decoration:underline">Переглянути →</a>';
    el.style.cssText = 'max-width:380px;line-height:1.5';
    document.body.appendChild(el);
    requestAnimationFrame(function() { el.classList.add('show'); });
    setTimeout(function() {
      el.classList.remove('show');
      setTimeout(function() { el.remove(); }, 350);
    }, 6000);
  }

  function updateBadges(count) {
    document.querySelectorAll('.orders-badge').forEach(function(b) {
      b.textContent = count > 0 ? count : '';
      b.style.display = count > 0 ? '' : 'none';
      b.classList.toggle('pulse', count > 0);
    });
  }

  function pollOrders() {
    fetch('check_new_orders.php')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var newCount = data.count || 0;
        if (newCount > knownCount) {
          playBeep();
          showNewOrderToast(newCount);
        }
        if (newCount !== knownCount) {
          updateBadges(newCount);
          knownCount = newCount;
        }
      })
      .catch(function() {});
  }

  setInterval(pollOrders, 30000);
})();
</script>

<script>

(function() {
  var sidebar   = document.getElementById('adminSidebar');
  var overlay   = document.getElementById('sidebarOverlay');
  var hamburger = document.getElementById('hamburgerBtn');
  if (!sidebar) return;

  /* Floating tooltip (appended to body to escape overflow clipping) */
  var tip = document.createElement('div');
  tip.className = 'sb-tooltip';
  document.body.appendChild(tip);

  function setCollapsed(collapsed) {
    sidebar.classList.toggle('collapsed', collapsed);
    localStorage.setItem('sb_collapsed', collapsed ? '1' : '0');
    tip.style.opacity = '0';
  }

  var collapser = document.getElementById('sidebarToggle');
  collapser && collapser.addEventListener('click', function() {
    setCollapsed(!sidebar.classList.contains('collapsed'));
  });

  hamburger && hamburger.addEventListener('click', function() {
    sidebar.classList.add('mobile-open');
    overlay && overlay.classList.add('visible');
  });

  overlay && overlay.addEventListener('click', function() {
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('visible');
  });

  sidebar.querySelectorAll('.sidebar-link[data-tooltip]').forEach(function(link) {
    link.addEventListener('mouseenter', function() {
      if (!sidebar.classList.contains('collapsed') || window.innerWidth <= 700) return;
      var rect = link.getBoundingClientRect();
      tip.textContent = link.dataset.tooltip;
      tip.style.top  = Math.round(rect.top + rect.height / 2) + 'px';
      tip.style.opacity = '1';
    });
    link.addEventListener('mouseleave', function() { tip.style.opacity = '0'; });
    link.addEventListener('click',      function() { tip.style.opacity = '0'; });
  });

  var avatar = document.getElementById('sidebarAvatar');
  avatar && avatar.addEventListener('click', function() {
    if (!sidebar.classList.contains('collapsed')) return;
    if (confirm('Вийти з панелі адміна?')) {
      window.location.href = 'logout.php';
    }
  });

  var _lastW = window.innerWidth;
  window.addEventListener('resize', function() {
    var w = window.innerWidth;
    if (w === _lastW) return;
    _lastW = w;
    if (w <= 700) {
      sidebar.classList.remove('collapsed', 'mobile-open');
      overlay && overlay.classList.remove('visible');
      tip.style.opacity = '0';
    }
  });
})();
</script>
