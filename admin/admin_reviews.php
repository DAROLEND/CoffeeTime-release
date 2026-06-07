<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('reviews');

$pageTitle  = 'Відгуки';
$activePage = 'reviews';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = trim($_POST['action'] ?? '');
    $rid    = (int)($_POST['id']    ?? 0);
    if ($rid && in_array($action, ['approved','declined','delete'])) {
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM site_reviews WHERE id=?");
        } else {
            $stmt = $conn->prepare("UPDATE site_reviews SET status=? WHERE id=?");
            $stmt->bind_param("si", $action, $rid);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success'=>true]);
            exit;
        }
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    $conn->prepare("DELETE FROM site_reviews WHERE id=?")->execute([$delId]);
    // fallback for older MySQLi
    $stmt = $conn->prepare("DELETE FROM site_reviews WHERE id=?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();
    $stmt->close();
    header('Location: admin_reviews.php?deleted=1'); exit;
}

$filterRating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filterStatus = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;

$where  = '1=1';
$params = [];
$types  = '';
if ($filterRating >= 1 && $filterRating <= 5) {
    $where .= ' AND r.rating=?'; $params[] = $filterRating; $types .= 'i';
}
if ($filterStatus && in_array($filterStatus, ['approved','declined','pending'])) {
    $where .= ' AND r.status=?'; $params[] = $filterStatus; $types .= 's';
}

$totalRows = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM site_reviews r WHERE $where");
if ($stmt) {
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalRows = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$reviews = [];
$sql = "
    SELECT r.id, r.name AS author, r.rating, r.text, r.created_at, r.status
    FROM site_reviews r
    WHERE $where
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $allTypes = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $reviews[] = $row;
    $stmt->close();
}

$adminTab = ($_GET['tab'] ?? '') === 'order_ratings' ? 'order_ratings' : 'site_reviews';

$totalCount = 0;
$avgRating  = 0.0;
$ratingDist = [];
$r = $conn->query("SELECT rating, COUNT(*) AS c FROM site_reviews GROUP BY rating ORDER BY rating DESC");
if ($r) while ($row = $r->fetch_assoc()) {
    $ratingDist[$row['rating']] = (int)$row['c'];
    $totalCount += $row['c'];
}
if ($totalCount > 0) {
    $ws = 0;
    foreach ($ratingDist as $stars => $cnt) $ws += $stars * $cnt;
    $avgRating = round($ws / $totalCount, 1);
}

$orRatings = []; $orTotal = 0; $orAvg = 0.0;
$conn->query("CREATE TABLE IF NOT EXISTS order_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, user_id INT NOT NULL,
    rating TINYINT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_user (order_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$_chk = $conn->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='order_ratings' AND column_name='comment'");
if ($_chk && $_chk->num_rows > 0) { $conn->query("ALTER TABLE order_ratings DROP COLUMN comment"); }
unset($_chk);
if ($adminTab === 'order_ratings') {
    $orPage    = max(1, (int)($_GET['p'] ?? 1));
    $orPerPage = 25;
    $orOffset  = ($orPage - 1) * $orPerPage;
    $orRes = $conn->query("SELECT COUNT(*) AS c FROM order_ratings");
    if ($orRes) $orTotal = (int)$orRes->fetch_assoc()['c'];
    $orPages = max(1, (int)ceil($orTotal / $orPerPage));
    $orPage  = min($orPage, $orPages);
    if ($orTotal > 0) {
        $ws = 0; $orDist = [];
        $r2 = $conn->query("SELECT rating, COUNT(*) AS c FROM order_ratings GROUP BY rating");
        if ($r2) while ($row = $r2->fetch_assoc()) { $orDist[$row['rating']] = (int)$row['c']; $ws += $row['rating'] * $row['c']; }
        $orAvg = round($ws / $orTotal, 1);
    }
    $stmt = $conn->prepare("
        SELECT r.id, r.order_id, r.rating, r.created_at,
               CONCAT(u.client_name,' ',u.client_surname) AS uname, u.email
        FROM order_ratings r
        LEFT JOIN users u ON u.client_id = r.user_id
        ORDER BY r.created_at DESC LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $orPerPage, $orOffset);
    $stmt->execute();
    $orRatings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$avatarColors = ['#c0392b','#e67e22','#27ae60','#2980b9','#8e44ad','#16a085','#d35400'];
function rvColor(string $name): string {
    global $avatarColors;
    return $avatarColors[abs(crc32($name)) % count($avatarColors)];
}

include 'includes/layout_top.php';
?>

<!-- Tab switcher -->
<div style="display:flex;gap:6px;margin-bottom:22px">
  <a href="admin_reviews.php?tab=site_reviews"
     style="padding:9px 20px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;transition:all .2s;
            <?= $adminTab==='site_reviews' ? 'background:#8B4513;color:#fff;box-shadow:0 2px 8px rgba(139,69,19,.25)' : 'background:#f5f0ea;color:#8B6040' ?>">
    Відгуки сайту
  </a>
  <a href="admin_reviews.php?tab=order_ratings"
     style="padding:9px 20px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;transition:all .2s;
            <?= $adminTab==='order_ratings' ? 'background:#8B4513;color:#fff;box-shadow:0 2px 8px rgba(139,69,19,.25)' : 'background:#f5f0ea;color:#8B6040' ?>">
    Оцінки замовлень
  </a>
</div>

<?php if ($adminTab === 'order_ratings'): ?>
<!-- ═══ Order ratings tab ═══ -->
<div class="stats-grid stats-grid--three" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#fce4ec">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#E91E63" stroke-width="2" stroke-linecap="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    </div>
    <div class="stat-text"><div class="stat-label">Середній рейтинг</div><div class="stat-value"><?= number_format($orAvg, 1) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#e3f2fd">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
    </div>
    <div class="stat-text"><div class="stat-label">Оцінок всього</div><div class="stat-value"><?= $orTotal ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#e8f5e9">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="stat-text"><div class="stat-label">За цей тиждень</div>
      <?php $orWk = (int)($conn->query("SELECT COUNT(*) AS c FROM order_ratings WHERE created_at > NOW() - INTERVAL 7 DAY")->fetch_assoc()['c'] ?? 0); ?>
      <div class="stat-value"><?= $orWk ?></div>
    </div>
  </div>
</div>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Замовлення</th>
        <th>Клієнт</th>
        <th>Оцінка</th>
        <th class="col-hide-mobile">Дата</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($orRatings as $or):
      $uname  = trim($or['uname']) ?: ($or['email'] ?? 'Клієнт');
      $stars  = (int)$or['rating'];
    ?>
      <tr>
        <td><a href="view_order.php?id=<?= (int)$or['order_id'] ?>" style="color:#8B4513;font-weight:700;text-decoration:none">#<?= (int)$or['order_id'] ?></a></td>
        <td style="font-size:13px">
          <?= htmlspecialchars($uname) ?>
          <?php if ($or['email']): ?><div style="font-size:11px;color:#aaa"><?= htmlspecialchars($or['email']) ?></div><?php endif; ?>
        </td>
        <td><span style="color:#FFC107;letter-spacing:1px;font-size:15px"><?= str_repeat('★',$stars) ?></span><span style="color:#ddd;letter-spacing:1px;font-size:15px"><?= str_repeat('★',5-$stars) ?></span></td>
        <td class="col-hide-mobile" style="font-size:12px;color:#999;white-space:nowrap"><?= date('d.m.Y H:i', strtotime($or['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($orRatings)): ?>
      <tr><td colspan="4" style="text-align:center;color:#bbb;padding:32px">Оцінок ще немає</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (($orPages ?? 1) > 1): ?>
<div class="pagination">
  <?php if ($orPage > 1): ?><a href="?tab=order_ratings&p=<?= $orPage-1 ?>" class="page-btn">‹</a><?php endif; ?>
  <?php for ($i=max(1,$orPage-2);$i<=min($orPages,$orPage+2);$i++): ?>
    <a href="?tab=order_ratings&p=<?= $i ?>" class="page-btn <?= $i===$orPage?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($orPage < $orPages): ?><a href="?tab=order_ratings&p=<?= $orPage+1 ?>" class="page-btn">›</a><?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ═══ Site reviews tab ═══ -->
<!-- Stats row -->
<div class="stats-grid stats-grid--three">
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#fce4ec">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#E91E63" stroke-width="2" stroke-linecap="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Середній рейтинг</div>
      <div class="stat-value"><?= number_format($avgRating, 1) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#e3f2fd">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">Всього відгуків</div>
      <div class="stat-value" data-count="<?= $totalCount ?>"><?= $totalCount ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon-box" style="background:#e8f5e9">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="stat-text">
      <div class="stat-label">За цей тиждень</div>
      <?php
        $wk = 0;
        $rw = $conn->query("SELECT COUNT(*) AS c FROM site_reviews WHERE created_at > NOW() - INTERVAL 7 DAY");
        if ($rw) $wk = (int)$rw->fetch_assoc()['c'];
      ?>
      <div class="stat-value" data-count="<?= $wk ?>"><?= $wk ?></div>
    </div>
  </div>
</div>

<!-- Rating distribution -->
<div class="dash-section" style="margin-bottom:20px">
  <h3 class="section-title" style="margin-bottom:14px">Розподіл оцінок</h3>
  <div class="rating-dist">
    <?php for ($s = 5; $s >= 1; $s--):
      $cnt = $ratingDist[$s] ?? 0;
      $pct = $totalCount > 0 ? round($cnt / $totalCount * 100) : 0;
    ?>
    <div class="rating-dist-row">
      <span class="rating-star-label"><?= $s ?>★</span>
      <div class="rating-bar-bg">
        <div class="rating-bar-fill" style="width:<?= $pct ?>%"></div>
      </div>
      <span class="rating-bar-count"><?= $cnt ?></span>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- Filters -->
<div class="orders-toolbar">
  <div class="toolbar-chips">

    <!-- Rating chips -->
    <div class="chip-group">
      <?php
        $ratingTabs = [0 => 'Всі оцінки', 5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★'];
        foreach ($ratingTabs as $val => $lbl):
          $active = ($filterRating === $val);
          $url = '?' . http_build_query(array_merge($_GET, ['rating' => $val ?: '', 'tab' => $adminTab]));
      ?>
        <a href="<?= htmlspecialchars($url) ?>" class="fchip <?= $active ? 'fchip--on' : '' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>

    <span class="chip-sep"></span>

    <!-- Status chips -->
    <div class="chip-group">
      <?php
        $statusTabs = ['' => 'Всі статуси', 'approved' => 'Схвалені', 'pending' => 'Очікують', 'declined' => 'Відхилені'];
        foreach ($statusTabs as $val => $lbl):
          $active = ($filterStatus === $val);
          $url = '?' . http_build_query(array_merge($_GET, ['status' => $val, 'tab' => $adminTab]));
      ?>
        <a href="<?= htmlspecialchars($url) ?>" class="fchip <?= $active ? 'fchip--on' : '' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($filterRating || $filterStatus): ?>
      <a href="admin_reviews.php?tab=<?= htmlspecialchars($adminTab) ?>" class="fchip fchip--reset">✕ Скинути</a>
    <?php endif; ?>

    <span style="margin-left:auto;font-size:12px;color:#bbb;white-space:nowrap;align-self:center">
      Знайдено: <strong><?= $totalRows ?></strong>
    </span>
  </div>
</div>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success" style="margin-bottom:14px">Відгук видалено.</div>
<?php endif; ?>

<!-- Reviews table -->
<div class="table-wrap">
  <table class="admin-table" id="reviewsTable">
    <thead>
      <tr>
        <th>Автор</th>
        <th>Оцінка</th>
        <th>Текст</th>
        <th>Дата</th>
        <th>Статус</th>
        <th>Дії</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reviews as $rv):
        $author  = $rv['author'] ?: 'Анонім';
        $initial = mb_strtoupper(mb_substr($author, 0, 1));
        $color   = rvColor($author);
        $stars   = (int)$rv['rating'];
        $status  = $rv['status'] ?? 'approved';
      ?>
      <tr id="rv-row-<?= $rv['id'] ?>">
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="review-avatar" style="background:<?= $color ?>;width:34px;height:34px;font-size:13px;flex-shrink:0">
              <?= htmlspecialchars($initial) ?>
            </div>
            <span style="font-weight:600;font-size:13px"><?= htmlspecialchars($author) ?></span>
          </div>
        </td>
        <td>
          <span style="color:#FFC107;letter-spacing:1px">
            <?= str_repeat('★', $stars) ?><span style="color:#ddd"><?= str_repeat('★', 5-$stars) ?></span>
          </span>
        </td>
        <td style="max-width:280px;font-size:13px;color:#555">
          <?= htmlspecialchars(mb_substr($rv['text'] ?? '', 0, 100)) ?><?= mb_strlen($rv['text'] ?? '') > 100 ? '…' : '' ?>
        </td>
        <td style="font-size:12px;color:#999"><?= date('d.m.Y', strtotime($rv['created_at'])) ?></td>
        <td>
          <?php if ($status === 'approved'): ?>
            <span class="order-badge badge-ready">Схвалено</span>
          <?php elseif ($status === 'declined'): ?>
            <span class="order-badge badge-cancelled">Відхилено</span>
          <?php else: ?>
            <span class="order-badge badge-new">На модерації</span>
          <?php endif; ?>
        </td>
        <td>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php if ($status !== 'approved'): ?>
            <button class="rv-btn-approve" onclick="reviewAction(<?= $rv['id'] ?>,'approved',this)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              Схвалити
            </button>
            <?php endif; ?>
            <?php if ($status !== 'declined'): ?>
            <button class="rv-btn-decline" onclick="reviewAction(<?= $rv['id'] ?>,'declined',this)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Відхилити
            </button>
            <?php endif; ?>
            <button class="rv-btn-delete" onclick="reviewAction(<?= $rv['id'] ?>,'delete',this)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              Видалити
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($reviews)): ?>
        <tr><td colspan="6" style="text-align:center;color:#bbb;padding:32px">Відгуків не знайдено</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?<?= http_build_query(array_merge($_GET,['p'=>$page-1])) ?>" class="page-btn">‹</a>
  <?php endif; ?>
  <?php for ($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET,['p'=>$i])) ?>"
       class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?>
    <a href="?<?= http_build_query(array_merge($_GET,['p'=>$page+1])) ?>" class="page-btn">›</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
function reviewAction(id, action, btn) {
  if (action === 'delete' && !confirm('Видалити цей відгук?')) return;
  var row = document.getElementById('rv-row-' + id);
  var fd = new FormData();
  fd.append('action', action);
  fd.append('id',     id);
  fetch('admin_reviews.php', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: fd
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    if (!data.success) return;
    if (action === 'delete') {
      row.classList.add('deleting');
      setTimeout(function(){ row.remove(); }, 350);
      if (typeof showAdminToast === 'function') showAdminToast('Відгук видалено', 'success');
    } else if (action === 'approved') {
      row.querySelector('td:nth-child(5)').innerHTML = '<span class="order-badge badge-ready">Схвалено</span>';
      var decBtn = row.querySelector('.rv-btn-approve');
      if (decBtn) decBtn.remove();
      if (typeof showAdminToast === 'function') showAdminToast('Відгук схвалено', 'success');
    } else if (action === 'declined') {
      row.querySelector('td:nth-child(5)').innerHTML = '<span class="order-badge badge-cancelled">Відхилено</span>';
      var decBtn = row.querySelector('.rv-btn-decline');
      if (decBtn) decBtn.remove();
      if (typeof showAdminToast === 'function') showAdminToast('Відгук відхилено', 'info');
    }
  })
  .catch(function(){ if (typeof showAdminToast === 'function') showAdminToast('Помилка запиту', 'error'); });
}
</script>

<?php endif; ?>

<?php include 'includes/layout_bottom.php'; ?>
