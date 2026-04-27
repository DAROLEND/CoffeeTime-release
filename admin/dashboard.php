<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle  = 'Головна';
$activePage = 'dashboard';

/* ── Stats ── */
$todayOrders  = 0;
$todayRevenue = 0.0;
$totalClients = 0;
$weekReviews  = 0;

$r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(total),0) AS s FROM orders WHERE DATE(created_at)=CURDATE()");
if ($r) { $row = $r->fetch_assoc(); $todayOrders = (int)$row['c']; $todayRevenue = (float)$row['s']; }

$r = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM orders WHERE user_id IS NOT NULL");
if ($r) $totalClients = (int)$r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM site_reviews WHERE created_at > NOW() - INTERVAL 7 DAY");
if ($r) $weekReviews = (int)$r->fetch_assoc()['c'];

/* ── Recent orders ── */
$recentOrders = [];
$r = $conn->query("
    SELECT o.order_id, o.customer_name, o.customer_surname, o.phone,
           o.total, o.status, o.payment_status, o.payment_method, o.created_at,
           COUNT(oi.id) AS items_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.order_id
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT 10
");
if ($r) while ($row = $r->fetch_assoc()) $recentOrders[] = $row;

/* ── Top products — handle giftcards.title vs others.name ── */
$topProducts = [];
$r = $conn->query("
    SELECT oi.product_id, oi.category,
           SUM(oi.quantity) AS sold,
           COUNT(DISTINCT oi.order_id) AS orders_count,
           COALESCE(SUM(oi.quantity * oi.price), 0) AS total_revenue
    FROM order_items oi
    GROUP BY oi.product_id, oi.category
    ORDER BY sold DESC
    LIMIT 5
");
if ($r) {
    $allowed_cats = ['coffee_items','fast_food_items','pizza_items','cold_drink_items','dessert_items','giftcards','sushi_items','sushi_sets','salad_items','cake_items'];
    while ($row = $r->fetch_assoc()) {
        $cat = $row['category'];
        $pid = (int)$row['product_id'];
        if (!in_array($cat, $allowed_cats)) continue;
        $nameCol = ($cat === 'giftcards') ? 'title' : 'name';
        $s2 = $conn->prepare("SELECT `$nameCol` AS product_name, image FROM `$cat` WHERE id = ?");
        $s2->bind_param("i", $pid);
        $s2->execute();
        $prod = $s2->get_result()->fetch_assoc();
        $s2->close();
        $row['name']  = $prod['product_name'] ?? '—';
        $row['image'] = $prod['image']         ?? '';
        $topProducts[] = $row;
    }
}

function statusInfo(string $s): array {
    return match($s) {
        'new'       => ['Нове',      'badge-new'],
        'done'      => ['Готово',    'badge-done'],
        'cancelled' => ['Скасовано', 'badge-cancelled'],
        default     => ['Нове',      'badge-new'],
    };
}

function paymentBadge(array $o): string {
    $ps = $o['payment_status'] ?? '';
    $pm = $o['payment_method']  ?? '';
    if ($ps === 'paid') {
        return '<span class="pay-tag pay-paid">💳 Оплачено</span>';
    }
    if (str_contains($pm, 'cash') || $pm === 'cash') {
        return '<span class="pay-tag pay-cash">💵 Готівка</span>';
    }
    return '<span class="pay-tag pay-unpaid">⏳ Не оплачено</span>';
}

include 'includes/layout_top.php';
?>

<div class="stats-grid">

  <div class="stat-card">
    <div class="stat-icon-box" style="background:#fff8e6">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#FFC107" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
        <line x1="3" y1="6" x2="21" y2="6"/>
        <path d="M16 10a4 4 0 0 1-8 0"/>
      </svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Замовлення сьогодні</div>
      <div class="stat-value" data-count="<?= $todayOrders ?>"><?= $todayOrders ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-box" style="background:#e8f5e9">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Виручка сьогодні</div>
      <div class="stat-value" data-count="<?= $todayRevenue ?>" data-decimals="2" data-suffix=" ₴">
        <?= number_format($todayRevenue, 2) ?> ₴
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-box" style="background:#e3f2fd">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
        <circle cx="12" cy="7" r="4"/>
      </svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Клієнтів усього</div>
      <div class="stat-value" data-count="<?= $totalClients ?>"><?= $totalClients ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-box" style="background:#fce4ec">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#E91E63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
      </svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Відгуки за тиждень</div>
      <div class="stat-value" data-count="<?= $weekReviews ?>"><?= $weekReviews ?></div>
    </div>
  </div>

</div>

<div class="dashboard-grid">

  <div class="dash-section">
    <div class="section-head">
      <h2 class="section-title">Останні замовлення</h2>
      <a href="orders.php" class="btn-ghost btn-sm">Всі →</a>
    </div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>#</th><th>Клієнт</th><th>Телефон</th>
            <th>Оплата</th><th>Сума</th><th>Статус</th><th>Дата</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentOrders as $o):
            [$label, $badgeClass] = statusInfo($o['status']);
            $fullName = trim(($o['customer_name'] ?? '') . ' ' . ($o['customer_surname'] ?? '')) ?: '—';
          ?>
          <tr>
            <td><strong>#<?= $o['order_id'] ?></strong></td>
            <td><?= htmlspecialchars($fullName) ?></td>
            <td style="color:#666;font-size:12px"><?= htmlspecialchars($o['phone'] ?? '—') ?></td>
            <td><?= paymentBadge($o) ?></td>
            <td><strong><?= number_format($o['total'], 2) ?> ₴</strong></td>
            <td><span class="order-badge <?= $badgeClass ?>"><?= $label ?></span></td>
            <td style="color:#999;font-size:12px"><?= date('d.m H:i', strtotime($o['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentOrders)): ?>
            <tr><td colspan="7" style="text-align:center;color:#bbb;padding:32px">Замовлень ще немає</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="dash-section dash-section--narrow">
    <div class="section-head" style="align-items:flex-start;flex-wrap:wrap;gap:10px;">
      <h2 class="section-title">Топ товарів</h2>
      <div class="top-filter-tabs" id="topFilterTabs">
        <button class="top-ftab active" data-period="all">Весь час</button>
        <button class="top-ftab" data-period="month">Місяць</button>
        <button class="top-ftab" data-period="week">Тиждень</button>
      </div>
    </div>

    <ul class="top-list" id="topProductsList">
    <?php if (empty($topProducts)): ?>
      <li style="color:#bbb;font-size:13px;padding:16px 0;list-style:none;">Даних поки немає</li>
    <?php else: ?>
      <?php
        $rankColors = ['#FFC107', '#aaaaaa', '#cd7f32'];
        foreach ($topProducts as $i => $p):
          $rankStyle = 'color:' . ($rankColors[$i] ?? '#ccc');
      ?>
      <li class="top-item">
        <span class="top-rank" style="<?= $rankStyle ?>"><?= $i + 1 ?></span>
        <?php if (!empty($p['image'])): ?>
          <img src="../<?= htmlspecialchars($p['image']) ?>" class="top-thumb" alt="">
        <?php else: ?>
          <div class="top-thumb top-thumb--empty" style="font-size:14px;color:#aaa">—</div>
        <?php endif; ?>
        <div style="flex:1;min-width:0;">
          <div class="top-name"><?= htmlspecialchars($p['name']) ?></div>
          <div style="font-size:11px;color:#aaa;margin-top:1px;"><?= (int)$p['orders_count'] ?> замовлень</div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div class="top-sold"><?= (int)$p['sold'] ?> шт</div>
          <div style="font-size:11px;color:#aaa;"><?= number_format((float)$p['total_revenue'], 0, '.', ' ') ?> грн</div>
        </div>
      </li>
      <?php endforeach; ?>
    <?php endif; ?>
    </ul>
  </div>

</div>

<script>
(function() {
  const tabs = document.querySelectorAll('.top-ftab');
  const list = document.getElementById('topProductsList');
  if (!tabs.length || !list) return;

  tabs.forEach(btn => {
    btn.addEventListener('click', async function() {
      tabs.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      list.style.opacity = '0.45';
      list.style.pointerEvents = 'none';
      try {
        const r    = await fetch('get_top_products.php?period=' + this.dataset.period);
        const data = await r.json();
        if (data.success) renderTopProducts(list, data.products);
      } catch (_) {}
      list.style.opacity = '1';
      list.style.pointerEvents = 'auto';
    });
  });

  function renderTopProducts(container, products) {
    if (!products.length) {
      container.innerHTML = '<li style="color:#bbb;font-size:13px;padding:16px 0;list-style:none;">Даних за цей період немає</li>';
      return;
    }
    const rankColors = ['#FFC107', '#aaaaaa', '#cd7f32'];
    container.innerHTML = products.map((p, i) => {
      const color  = rankColors[i] || '#ccc';
      const img    = p.image
        ? `<img src="../${p.image}" class="top-thumb" alt="" style="object-fit:cover;border-radius:8px;">`
        : `<div class="top-thumb top-thumb--empty" style="font-size:14px;color:#aaa;">—</div>`;
      return `<li class="top-item" style="animation:rowFadeIn .25s ease ${i * .05}s both;">
        <span class="top-rank" style="color:${color}">${i + 1}</span>
        ${img}
        <div style="flex:1;min-width:0;">
          <div class="top-name">${p.name}</div>
          <div style="font-size:11px;color:#aaa;margin-top:1px;">${p.orders_count} замовлень</div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div class="top-sold">${p.total_qty} шт</div>
          <div style="font-size:11px;color:#aaa;">${p.total_revenue_fmt} грн</div>
        </div>
      </li>`;
    }).join('');
  }
})();
</script>

<?php include 'includes/layout_bottom.php'; ?>
