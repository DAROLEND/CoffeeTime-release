<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('orders_view');
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Замовлення';
$activePage = 'orders';

/* ── 3 statuses only ── */
$statusLabels = ['new' => 'Нове', 'done' => 'Готово', 'cancelled' => 'Скасовано'];

/* ── Filters ── */
$filterStatus  = trim($_GET['status']  ?? '');
$filterPayment = trim($_GET['payment'] ?? '');
$filterSearch  = trim($_GET['search']  ?? '');
$dateFrom      = trim($_GET['date_from'] ?? '');
$dateTo        = trim($_GET['date_to']   ?? '');
$page          = max(1, (int)($_GET['p'] ?? 1));
$perPage       = 20;

$where  = '1=1';
$params = [];
$types  = '';

if ($filterStatus && array_key_exists($filterStatus, $statusLabels)) {
    $where .= ' AND o.status=?'; $params[] = $filterStatus; $types .= 's';
}
if ($filterPayment === 'paid') {
    $where .= " AND o.payment_status='paid'";
} elseif ($filterPayment === 'cash') {
    $where .= " AND o.payment_method LIKE '%cash%'";
} elseif ($filterPayment === 'unpaid') {
    $where .= " AND o.payment_status NOT IN ('paid','cash') AND o.payment_method NOT LIKE '%cash%'";
}
if ($filterSearch !== '') {
    $where .= ' AND (o.customer_name LIKE ? OR o.customer_surname LIKE ? OR o.phone LIKE ? OR o.order_id=?)';
    $like = '%'.$filterSearch.'%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = (int)$filterSearch;
    $types .= 'sssi';
}
if ($dateFrom !== '') { $where .= ' AND DATE(o.created_at)>=?'; $params[] = $dateFrom; $types .= 's'; }
if ($dateTo   !== '') { $where .= ' AND DATE(o.created_at)<=?'; $params[] = $dateTo;   $types .= 's'; }

/* ── Count ── */
$totalRows = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders o WHERE $where");
if ($stmt) {
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalRows = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

/* ── Orders ── */
$orders = [];
$stmt = $conn->prepare("
    SELECT o.order_id, o.customer_name, o.customer_surname, o.phone,
           o.total, o.status, o.payment_status, o.payment_method,
           o.created_at, COUNT(oi.id) AS items_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.order_id
    WHERE $where
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
if ($stmt) {
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($types.'ii', ...$allParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $orders[] = $row;
    $stmt->close();
}

/* ── Status counts ── */
$statusCounts = [];
$r = $conn->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
if ($r) while ($row = $r->fetch_assoc()) $statusCounts[$row['status']] = $row['c'];

/* ── Payment counts for tabs ── */
$paidCount   = 0; $cashCount   = 0; $unpaidCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE payment_status='paid'");
if ($r) $paidCount = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE payment_method LIKE '%cash%'");
if ($r) $cashCount = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE payment_status NOT IN ('paid','cash') AND payment_method NOT LIKE '%cash%'");
if ($r) $unpaidCount = (int)$r->fetch_assoc()['c'];
$allCount = array_sum($statusCounts);

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
    if ($ps === 'paid') return '<span class="pay-tag pay-paid">💳 Оплачено</span>';
    if (str_contains($pm, 'cash')) return '<span class="pay-tag pay-cash">💵 Готівка</span>';
    return '<span class="pay-tag pay-unpaid">⏳ Не оплачено</span>';
}

include 'includes/layout_top.php';
?>

<!-- ══ Payment tabs ══ -->
<div class="payment-tabs">
  <?php
    $ptabs = [
      ''       => ['Всі',          $allCount],
      'paid'   => ['💳 Оплачені',  $paidCount],
      'unpaid' => ['⏳ Не оплачені',$unpaidCount],
      'cash'   => ['💵 Готівка',   $cashCount],
    ];
    foreach ($ptabs as $val => [$lbl, $cnt]):
      $active = ($filterPayment === $val);
      $href = '?' . http_build_query(array_merge($_GET, ['payment' => $val, 'p' => 1]));
  ?>
    <a href="<?= $href ?>" class="payment-tab <?= $active ? 'active' : '' ?>">
      <?= $lbl ?>
      <?php if ($cnt > 0): ?><span class="pay-tab-count"><?= $cnt ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- ══ Filter bar ══ -->
<form class="orders-filter" method="get">
  <?php if ($filterPayment): ?>
    <input type="hidden" name="payment" value="<?= htmlspecialchars($filterPayment) ?>">
  <?php endif; ?>
  <div class="orders-filter__search">
    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" name="search" class="filter-input filter-search"
           placeholder="Ім'я, телефон або #ID"
           value="<?= htmlspecialchars($filterSearch) ?>">
  </div>
  <select name="status" class="filter-select">
    <option value="">Всі статуси</option>
    <?php foreach ($statusLabels as $st => $lbl):
      $cnt = $statusCounts[$st] ?? 0;
    ?>
      <option value="<?= $st ?>" <?= $filterStatus===$st?'selected':'' ?>>
        <?= $lbl ?><?= $cnt > 0 ? " ($cnt)" : '' ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div class="filter-date-range">
    <label class="date-label">З:</label>
    <input type="date" name="date_from" class="filter-select" value="<?= htmlspecialchars($dateFrom) ?>">
    <label class="date-label">По:</label>
    <input type="date" name="date_to"   class="filter-select" value="<?= htmlspecialchars($dateTo) ?>">
  </div>
  <button type="submit" class="filter-btn-search">Знайти</button>
  <?php if ($filterStatus || $filterSearch || $dateFrom || $dateTo): ?>
    <a href="orders.php<?= $filterPayment ? '?payment='.$filterPayment : '' ?>" class="btn-ghost btn-sm">✕ Скинути</a>
  <?php endif; ?>
</form>
<div class="filter-info">Знайдено: <strong><?= $totalRows ?></strong> замовлень</div>

<!-- ══ Table ══ -->
<div class="table-wrap">
  <table class="admin-table" id="ordersTable">
    <thead>
      <tr>
        <th style="width:36px"></th>
        <th>#</th>
        <th>Клієнт</th>
        <th>Телефон</th>
        <th>К-ть</th>
        <th>Сума</th>
        <th>Оплата</th>
        <th>Статус</th>
        <th>Дата</th>
        <th>Дії</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o):
        [$label, $badgeClass] = statusInfo($o['status']);
        $fullName = trim(($o['customer_name'] ?? '') . ' ' . ($o['customer_surname'] ?? '')) ?: '—';
      ?>
      <tr class="order-row" data-order-id="<?= $o['order_id'] ?>" data-status="<?= h($o['status']) ?>">
        <td>
          <button class="expand-btn" title="Деталі">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
        </td>
        <td><strong>#<?= $o['order_id'] ?></strong></td>
        <td><?= htmlspecialchars($fullName) ?></td>
        <td style="font-size:12px;color:#666"><?= htmlspecialchars($o['phone'] ?? '—') ?></td>
        <td><?= (int)$o['items_count'] ?></td>
        <td><strong><?= number_format($o['total'], 2) ?> ₴</strong></td>
        <td><?= paymentBadge($o) ?></td>
        <td>
          <span class="status-badge <?= $badgeClass ?>"><?= $label ?></span>
        </td>
        <td style="font-size:12px;color:#999"><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
        <td>
          <div class="status-actions" data-order-id="<?= $o['order_id'] ?>">
            <?php if ($o['status'] !== 'done'): ?>
            <button class="btn-status-action btn-done"
                    data-order="<?= $o['order_id'] ?>" data-status="done"
                    title="Позначити готовим">✓ Готово</button>
            <?php endif; ?>
            <?php if ($o['status'] !== 'new'): ?>
            <button class="btn-status-action btn-new"
                    data-order="<?= $o['order_id'] ?>" data-status="new"
                    title="Повернути в нові">↩ Нове</button>
            <?php endif; ?>
            <?php if ($o['status'] !== 'cancelled'): ?>
            <button class="btn-status-action btn-cancel"
                    data-order="<?= $o['order_id'] ?>" data-status="cancelled"
                    title="Скасувати">✕</button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <tr class="order-details-row" id="details-<?= $o['order_id'] ?>">
        <td colspan="10">
          <div class="order-details-inner">
            <div class="order-details-loading">Завантаження...</div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
        <tr><td colspan="10" style="text-align:center;color:#bbb;padding:36px">Замовлень не знайдено</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ══ Pagination ══ -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page - 1])) ?>" class="page-btn">‹</a>
  <?php endif; ?>
  <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET,['p'=>$i])) ?>"
       class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page + 1])) ?>" class="page-btn">›</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
(function () {
  'use strict';

  /* ── Status labels ── */
  var STATUS_LABELS = { new: 'Нове', done: 'Готово', cancelled: 'Скасовано' };
  var STATUS_BADGE  = { new: 'badge-new', done: 'badge-done', cancelled: 'badge-cancelled' };

  /* ── Toast ── */
  function showToast(message, type) {
    var existing = document.querySelector('.admin-toast');
    if (existing) existing.remove();
    var colors = {
      success: '#4CAF50', error: '#f44336', info: '#FFC107'
    };
    var t = document.createElement('div');
    t.className = 'admin-toast';
    t.textContent = message;
    t.style.cssText =
      'position:fixed;bottom:24px;right:24px;z-index:9999;' +
      'background:#2c2c2a;color:#fff;padding:12px 20px;' +
      'border-radius:10px;font-size:14px;font-family:inherit;' +
      'border-left:4px solid ' + (colors[type] || colors.success) + ';' +
      'box-shadow:0 4px 16px rgba(0,0,0,.2);' +
      'animation:toastIn .3s ease;max-width:320px;';
    document.body.appendChild(t);
    setTimeout(function () {
      t.style.animation = 'toastOut .3s ease forwards';
      setTimeout(function () { t.remove(); }, 300);
    }, 3000);
  }

  /* ── Build action buttons HTML ── */
  function actionButtonsHTML(orderId, currentStatus) {
    var h = '';
    if (currentStatus !== 'done')
      h += '<button class="btn-status-action btn-done" data-order="' + orderId + '" data-status="done" title="Позначити готовим">✓ Готово</button>';
    if (currentStatus !== 'new')
      h += '<button class="btn-status-action btn-new" data-order="' + orderId + '" data-status="new" title="Повернути в нові">↩ Нове</button>';
    if (currentStatus !== 'cancelled')
      h += '<button class="btn-status-action btn-cancel" data-order="' + orderId + '" data-status="cancelled" title="Скасувати">✕</button>';
    return h;
  }

  /* ── Update sidebar "Нові" badge ── */
  function updateSidebarBadge(newCount) {
    document.querySelectorAll('.orders-badge').forEach(function (b) {
      b.textContent = newCount > 0 ? newCount : '';
      b.style.display = newCount > 0 ? '' : 'none';
    });
  }

  /* ── Update payment-tab counts ── */
  function updateTabCounts() {
    fetch('get_order_counts.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var tabs = document.querySelectorAll('.payment-tab');
        tabs.forEach(function (tab) {
          var href  = tab.getAttribute('href') || '';
          var badge = tab.querySelector('.pay-tab-count');
          if (!badge) return;
          if (href.includes('payment=paid'))    badge.textContent = data.paid    ?? badge.textContent;
          else if (href.includes('payment=unpaid')) badge.textContent = data.unpaid ?? badge.textContent;
          else if (href.includes('payment=cash'))   badge.textContent = data.cash   ?? badge.textContent;
          else badge.textContent = data.all ?? badge.textContent; // "Всі" tab
        });
        if (data.new !== undefined) updateSidebarBadge(data.new);
      })
      .catch(function () {});
  }

  /* ── Fixed confirm popup (appended to body — zero impact on table layout) ── */
  var _cpRow = null, _cpContainer = null, _cpStatus = null;

  var cpEl = document.createElement('div');
  cpEl.id = 'statusConfirmPopup';
  cpEl.className = 'scp';
  cpEl.style.display = 'none';
  cpEl.innerHTML =
    '<p class="scp-text"></p>' +
    '<div class="scp-btns">' +
      '<button class="scp-yes">Так, змінити</button>' +
      '<button class="scp-no">Скасувати</button>' +
    '</div>';
  document.body.appendChild(cpEl);

  function cpShow(anchorBtn, text) {
    cpEl.querySelector('.scp-text').textContent = text;
    cpEl.querySelector('.scp-btns').style.display = '';
    cpEl.querySelector('.scp-yes').disabled = false;
    cpEl.style.display = 'block';
    /* Position below anchor button */
    var rect = anchorBtn.getBoundingClientRect();
    var popW = cpEl.offsetWidth;
    var left = Math.max(8, rect.right - popW);
    cpEl.style.top  = (rect.bottom + 6) + 'px';
    cpEl.style.left = left + 'px';
  }

  function cpHide() {
    cpEl.style.display = 'none';
    if (_cpRow) { _cpRow.style.outline = 'none'; _cpRow = null; }
    _cpContainer = null; _cpStatus = null;
  }

  cpEl.querySelector('.scp-no').onclick = cpHide;

  /* Close on outside click */
  document.addEventListener('click', function (e) {
    if (cpEl.style.display !== 'none' &&
        !cpEl.contains(e.target) &&
        !e.target.closest('.btn-status-action')) {
      cpHide();
    }
  });

  /* Confirm → AJAX */
  cpEl.querySelector('.scp-yes').onclick = async function () {
    var row = _cpRow, container = _cpContainer, newStatus = _cpStatus;
    if (!row || !container || !newStatus) return;

    cpEl.querySelector('.scp-text').textContent = 'Збереження…';
    cpEl.querySelector('.scp-btns').style.display = 'none';

    try {
      var r = await fetch('update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: row.dataset.orderId, status: newStatus })
      });
      var data = await r.json();

      cpHide();

      if (data.success) {
        row.setAttribute('data-status', newStatus);

        /* Flash */
        row.classList.remove('row-flash');
        void row.offsetWidth;
        row.classList.add('row-flash');

        /* Badge */
        var badge = row.querySelector('.status-badge');
        if (badge) {
          badge.className = 'status-badge ' + (STATUS_BADGE[newStatus] || '');
          badge.textContent = STATUS_LABELS[newStatus] || newStatus;
          badge.classList.remove('badge-pop');
          void badge.offsetWidth;
          badge.classList.add('badge-pop');
        }

        /* Rebuild buttons */
        container.innerHTML = actionButtonsHTML(row.dataset.orderId, newStatus);

        showToast('✓ Статус оновлено: ' + (STATUS_LABELS[newStatus] || newStatus), 'success');
        updateTabCounts();
      } else {
        showToast('Помилка оновлення', 'error');
      }
    } catch (ex) {
      cpHide();
      showToast("Помилка з'єднання", 'error');
    }
  };

  /* ── Status action buttons (event delegation) ── */
  document.getElementById('ordersTable').addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-status-action');
    if (!btn) return;
    e.stopPropagation();

    _cpRow       = btn.closest('tr.order-row');
    _cpContainer = btn.closest('.status-actions');
    _cpStatus    = btn.dataset.status;

    _cpRow.style.outline = '2px solid #FFC107';
    _cpRow.style.outlineOffset = '-2px';

    cpShow(btn, 'Змінити статус на "' + (STATUS_LABELS[_cpStatus] || _cpStatus) + '"?');
  });

  /* ── Expand / collapse detail row ── */
  document.getElementById('ordersTable').addEventListener('click', function (e) {
    var btn = e.target.closest('.expand-btn');
    if (!btn) return;

    var row     = btn.closest('.order-row');
    var orderId = row.dataset.orderId;
    var detRow  = document.getElementById('details-' + orderId);
    var inner   = detRow.querySelector('.order-details-inner');
    var isOpen  = detRow.classList.contains('open');

    /* Close all open rows */
    document.querySelectorAll('.order-details-row.open').forEach(function (r) {
      r.classList.remove('open');
      var oId = r.id.replace('details-', '');
      var eb  = document.querySelector('.order-row[data-order-id="' + oId + '"] .expand-btn');
      if (eb) eb.classList.remove('rotated');
    });

    if (!isOpen) {
      detRow.classList.add('open');
      btn.classList.add('rotated');
      if (!inner.dataset.loaded) {
        inner.innerHTML = '<div class="order-details-loading">Завантаження…</div>';
        fetch('get_order_details.php?id=' + orderId)
          .then(function (r) { return r.text(); })
          .then(function (html) { inner.innerHTML = html; inner.dataset.loaded = '1'; });
      }
    }
  });

})();
</script>

<?php include 'includes/layout_bottom.php'; ?>
