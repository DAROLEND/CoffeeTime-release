<?php
/**
 * Admin layout top — sidebar + topbar + opens <main class="admin-content">
 * Requires: $activePage, $pageTitle set before including
 *           $conn (DB) and $_SESSION['admin'] already available
 */
$adminUsername = $_SESSION['admin'] ?? 'Admin';
$adminInitial  = strtoupper(substr($adminUsername, 0, 1));

// New / pending orders count for badges (only real statuses)
$newOrdersCount = 0;
try {
    $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='new'");
    if ($r) $newOrdersCount = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) {}

// Recent reviews (last 7 days) count for badge
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
</head>
<body>
<div class="admin-wrapper">

  <!-- ═══════════ SIDEBAR ═══════════ -->
  <aside class="admin-sidebar">

    <div class="sidebar-brand">
      <img src="../static/images/main/logo-cup.svg" alt="Coffee Time" class="sidebar-logo">
      <div class="sidebar-subtitle">Панель адміна</div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Головне</div>

      <a href="dashboard.php" class="sidebar-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
        Головна
      </a>

      <a href="orders.php" class="sidebar-link <?= ($activePage ?? '') === 'orders' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
          <line x1="3" y1="6" x2="21" y2="6"/>
          <path d="M16 10a4 4 0 0 1-8 0"/>
        </svg>
        Замовлення
        <?php if ($newOrdersCount > 0): ?>
          <span class="nav-badge orders-badge<?= $newOrdersCount > 0 ? ' pulse' : '' ?>"><?= $newOrdersCount ?></span>
        <?php endif; ?>
      </a>

      <a href="manage_items.php" class="sidebar-link <?= ($activePage ?? '') === 'products' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
        </svg>
        Товари
      </a>

      <?php if (is_super()): ?>
      <div class="nav-section-label">Адміністрація</div>
      <a href="admin_users.php" class="sidebar-link <?= ($activePage ?? '') === 'admin_users' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Персонал
      </a>
      <?php endif; ?>

      <div class="nav-section-label">Контент</div>

      <a href="admin_reviews.php" class="sidebar-link <?= ($activePage ?? '') === 'reviews' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        Відгуки
        <?php if ($pendingReviews > 0): ?>
          <span class="nav-badge"><?= $pendingReviews ?></span>
        <?php endif; ?>
      </a>

      <a href="admin_gallery.php" class="sidebar-link <?= ($activePage ?? '') === 'gallery' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <polyline points="21 15 16 10 5 21"/>
        </svg>
        Галерея
      </a>

      <a href="hero_slides.php" class="sidebar-link <?= ($activePage ?? '') === 'hero_slides' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="7" width="20" height="10" rx="2"/>
          <path d="M17 7V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v2"/>
          <polyline points="9 12 12 15 15 12"/>
        </svg>
        Хіро слайдер
      </a>

      <a href="about_section.php" class="sidebar-link <?= ($activePage ?? '') === 'about_section' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Про нас
      </a>
    </nav>

    <div class="sidebar-bottom">
      <div class="sidebar-avatar"><?= htmlspecialchars($adminInitial) ?></div>
      <span class="sidebar-admin-name"><?= htmlspecialchars($adminUsername) ?></span>
      <a href="logout.php" class="sidebar-logout" title="Вийти">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </a>
    </div>

  </aside>
  <!-- ═══════════ /SIDEBAR ═══════════ -->

  <div class="admin-main">

    <!-- ── Top bar ── -->
    <header class="admin-topbar">
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></div>
      <div class="topbar-right">
        <a href="orders.php" class="topbar-bell" title="Нові замовлення">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span class="topbar-bell-badge orders-badge"><?= $newOrdersCount > 0 ? $newOrdersCount : '' ?></span>
        </a>
        <div class="topbar-admin">
          <div class="topbar-admin-avatar"><?= htmlspecialchars($adminInitial) ?></div>
          <span class="topbar-admin-name"><?= htmlspecialchars($adminUsername) ?></span>
        </div>
      </div>
    </header>
    <!-- ── /Top bar ── -->

    <main class="admin-content">
