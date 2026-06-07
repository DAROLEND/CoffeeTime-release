<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('content');

$conn->query("CREATE TABLE IF NOT EXISTS hero_slides (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  image      VARCHAR(255) NOT NULL,
  label      VARCHAR(100) NOT NULL DEFAULT '',
  title      VARCHAR(255) NOT NULL DEFAULT '',
  subtitle   VARCHAR(255) NOT NULL DEFAULT '',
  sort_order TINYINT UNSIGNED DEFAULT 0,
  active     TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE hero_slides ADD COLUMN IF NOT EXISTS label VARCHAR(100) NOT NULL DEFAULT '' AFTER image");

$cnt = (int)$conn->query("SELECT COUNT(*) AS c FROM hero_slides")->fetch_assoc()['c'];
if ($cnt === 0) {
    $seeds = [
        ['static/images/categories/coffee_category.jpg', 'Кожен ковток — тепла історія', "Свіжозварена кава щоранку з любов'ю", 0],
        ['static/images/categories/dessert.jpg',          'Неможливо встояти…',           'Десерти власного приготування щодня',  1],
        ['static/images/categories/fast_food.jpg',        'Ідеальне комбо',               'Смачно, ситно і завжди свіже',         2],
    ];
    $ins = $conn->prepare("INSERT INTO hero_slides (image, title, subtitle, sort_order) VALUES (?,?,?,?)");
    foreach ($seeds as $s) {
        $ins->bind_param('sssi', $s[0], $s[1], $s[2], $s[3]);
        $ins->execute();
    }
    $ins->close();
}

$pageTitle  = 'Хіро слайдер';
$activePage = 'hero_slides';
$flash      = '';
$flashType  = 'success';

/* ═══════════════════════════════
   POST HANDLERS
═══════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $label    = trim($_POST['label']    ?? '');
        $title    = trim($_POST['title']    ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');

        if (!$title) {
            $flash = 'Заголовок обовʼязковий.';
            $flashType = 'error';
        } else {
            $imagePath = '';
            $uploadDir = __DIR__ . '/../static/images/slides/';

            if (!empty($_POST['image_b64'])) {
                $fname = 'slide_' . time() . '_' . bin2hex(random_bytes(4));
                $ext   = save_cropped_image($_POST['image_b64'], $uploadDir . $fname . '.jpg');
                if ($ext) $imagePath = 'static/images/slides/' . $fname . '.' . $ext;
                else { $flash = 'Помилка збереження зображення.'; $flashType = 'error'; }
            } elseif (!empty($_FILES['image']['name'])) {
                $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp'];
                if (!in_array($ext, $allowed)) {
                    $flash = 'Дозволені формати: JPG, PNG, WEBP.'; $flashType = 'error';
                } elseif ($_FILES['image']['size'] > 4 * 1024 * 1024) {
                    $flash = 'Файл занадто великий (макс 4 MB).'; $flashType = 'error';
                } else {
                    $fname = 'slide_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fname))
                        $imagePath = 'static/images/slides/' . $fname;
                    else { $flash = 'Не вдалося завантажити файл.'; $flashType = 'error'; }
                }
            }

            if (!$flash) {
                if (!$imagePath) {
                    $flash = 'Оберіть зображення.';
                    $flashType = 'error';
                } else {
                    $maxOrder = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0) AS m FROM hero_slides")->fetch_assoc()['m'];
                    $order = $maxOrder + 1;
                    $stmt  = $conn->prepare("INSERT INTO hero_slides (image, label, title, subtitle, sort_order) VALUES (?,?,?,?,?)");
                    $stmt->bind_param('ssssi', $imagePath, $label, $title, $subtitle, $order);
                    $stmt->execute();
                    $stmt->close();
                    $flash = 'Слайд додано.';
                }
            }
        }
    }

    if ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $label    = trim($_POST['label']    ?? '');
        $title    = trim($_POST['title']    ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');

        if ($id && $title) {
            $uploadDir = __DIR__ . '/../static/images/slides/';
            $newImg = '';
            if (!empty($_POST['image_b64'])) {
                $fname = 'slide_' . time() . '_' . bin2hex(random_bytes(4));
                $ext   = save_cropped_image($_POST['image_b64'], $uploadDir . $fname . '.jpg');
                if ($ext) $newImg = 'static/images/slides/' . $fname . '.' . $ext;
            } elseif (!empty($_FILES['image']['name'])) {
                $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp'];
                if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 4 * 1024 * 1024) {
                    $fname = 'slide_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fname))
                        $newImg = 'static/images/slides/' . $fname;
                }
            }
            if ($newImg) {
                $old = $conn->prepare("SELECT image FROM hero_slides WHERE id=?");
                $old->bind_param('i', $id); $old->execute();
                $oldRow = $old->get_result()->fetch_assoc(); $old->close();
                if ($oldRow && str_contains($oldRow['image'], 'slides/')) @unlink(__DIR__ . '/../' . $oldRow['image']);
                $stmt = $conn->prepare("UPDATE hero_slides SET image=?, label=?, title=?, subtitle=? WHERE id=?");
                $stmt->bind_param('ssssi', $newImg, $label, $title, $subtitle, $id);
            } else {
                $stmt = $conn->prepare("UPDATE hero_slides SET label=?, title=?, subtitle=? WHERE id=?");
                $stmt->bind_param('sssi', $label, $title, $subtitle, $id);
            }
            $stmt->execute(); $stmt->close();
            $flash = 'Збережено.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $sel = $conn->prepare("SELECT image FROM hero_slides WHERE id=?");
            $sel->bind_param('i', $id);
            $sel->execute();
            $row = $sel->get_result()->fetch_assoc();
            $sel->close();
            if ($row && str_contains($row['image'], 'slides/')) {
                @unlink(__DIR__ . '/../' . $row['image']);
            }
            $del = $conn->prepare("DELETE FROM hero_slides WHERE id=?");
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            $flash = 'Слайд видалено.';
        }
    }

    if ($action === 'toggle') {
        $isAjax = !empty($_POST['ajax']);
        $id = (int)($_POST['id'] ?? 0);
        $newActive = 0;
        if ($id) {
            $stmt = $conn->prepare("UPDATE hero_slides SET active = 1 - active WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $sel = $conn->prepare("SELECT active FROM hero_slides WHERE id=?");
            $sel->bind_param('i', $id);
            $sel->execute();
            $newActive = (int)$sel->get_result()->fetch_assoc()['active'];
            $sel->close();
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'active' => $newActive]);
            exit;
        }
        header('Location: hero_slides.php');
        exit;
    }

    if ($action === 'move') {
        $isAjax = !empty($_POST['ajax']);
        $id  = (int)($_POST['id']  ?? 0);
        $dir = $_POST['dir'] ?? '';
        if ($id && in_array($dir, ['up', 'down'])) {
            $all = [];
            $r2  = $conn->query("SELECT id, sort_order FROM hero_slides ORDER BY sort_order ASC, id ASC");
            while ($rw = $r2->fetch_assoc()) $all[] = $rw;

            $pos = array_search($id, array_column($all, 'id'));
            if ($pos !== false) {
                $swapPos = $dir === 'up' ? $pos - 1 : $pos + 1;
                if (isset($all[$swapPos])) {
                    [$all[$pos], $all[$swapPos]] = [$all[$swapPos], $all[$pos]];
                    $upd = $conn->prepare("UPDATE hero_slides SET sort_order=? WHERE id=?");
                    foreach ($all as $i => $item) {
                        $upd->bind_param('ii', $i, $item['id']);
                        $upd->execute();
                    }
                    $upd->close();
                }
            }
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        header('Location: hero_slides.php');
        exit;
    }

    /* Redirect back with flash via session */
    if ($flash) {
        $_SESSION['admin_flash']      = $flash;
        $_SESSION['admin_flash_type'] = $flashType;
    }
    header('Location: hero_slides.php');
    exit;
}

/* Read flash from session */
if (!empty($_SESSION['admin_flash'])) {
    $flash     = $_SESSION['admin_flash'];
    $flashType = $_SESSION['admin_flash_type'] ?? 'success';
    unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);
}

/* Fetch all slides */
$slides = [];
$r = $conn->query("SELECT * FROM hero_slides ORDER BY sort_order ASC, id ASC");
if ($r) while ($row = $r->fetch_assoc()) $slides[] = $row;

include 'includes/layout_top.php';
?>

<?php if ($flash): ?>
  <div class="admin-alert admin-alert--<?= $flashType === 'error' ? 'error' : 'success' ?>">
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════
     ADD SLIDE FORM
══════════════════════════════════════ -->
<div class="panel" style="margin-bottom:28px">
  <div class="panel-head">
    <h2 class="panel-title">Додати слайд</h2>
  </div>
  <form method="post" enctype="multipart/form-data" class="slide-form">
    <input type="hidden" name="action" value="add">

    <div class="sf-row">
      <div class="sf-field sf-field--upload">
        <label>Зображення <span class="req">*</span></label>
        <input type="hidden" name="image_b64" id="slide_add_b64">
        <div class="upload-zone" data-crop-ratio="16/9">
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-crop-hidden="slide_add_b64">
          <div class="upload-zone__placeholder">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <span>Оберіть або перетягніть фото</span>
            <small>JPG, PNG, WEBP · до 4 MB</small>
          </div>
          <div class="upload-preview" style="display:none"></div>
        </div>
      </div>

      <div class="sf-field sf-field--text">
        <div class="form-field">
          <label>Мітка <small style="color:#bbb;font-weight:400">(над заголовком, необов'язково)</small></label>
          <input type="text" name="label" placeholder="Спробуй зараз" maxlength="60">
        </div>
        <div class="form-field" style="margin-top:14px">
          <label>Заголовок <span class="req">*</span></label>
          <input type="text" name="title" placeholder="Кожен ковток — тепла історія" maxlength="100" required>
        </div>
        <div class="form-field" style="margin-top:14px">
          <label>Підзаголовок</label>
          <input type="text" name="subtitle" placeholder="Свіжозварена кава щоранку з любов'ю" maxlength="160">
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:18px">+ Додати слайд</button>
      </div>
    </div>
  </form>
</div>

<!-- ══════════════════════════════════════
     SLIDES LIST
══════════════════════════════════════ -->
<div class="panel">
  <div class="panel-head">
    <h2 class="panel-title">Поточні слайди (<?= count($slides) ?>)</h2>
  </div>

  <?php if (empty($slides)): ?>
    <p style="padding:24px;color:#999;text-align:center">Слайдів ще немає.</p>
  <?php else: ?>
  <div class="slides-list">
    <?php foreach ($slides as $i => $sl): ?>
      <div class="slide-row <?= $sl['active'] ? '' : 'slide-row--inactive' ?>">

        <!-- Drag handle / order -->
        <div class="slide-order">
          <button type="button" class="move-btn" data-id="<?= $sl['id'] ?>" data-dir="up"   title="Вгору" <?= $i === 0 ? 'disabled' : '' ?>>▲</button>
          <button type="button" class="move-btn" data-id="<?= $sl['id'] ?>" data-dir="down" title="Вниз"  <?= $i === count($slides)-1 ? 'disabled' : '' ?>>▼</button>
          <span class="slide-num"><?= $i + 1 ?></span>
        </div>

        <!-- Preview -->
        <div class="slide-thumb">
          <img src="../<?= htmlspecialchars($sl['image']) ?>" alt="">
        </div>

        <!-- Text -->
        <div class="slide-texts">
          <div class="slide-title"><?= htmlspecialchars($sl['title']) ?></div>
          <div class="slide-sub"><?= htmlspecialchars($sl['subtitle']) ?></div>
        </div>

        <!-- Active toggle -->
        <form method="post" class="slide-toggle-form">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $sl['id'] ?>">
          <button type="submit" class="toggle-btn <?= $sl['active'] ? 'toggle-btn--on' : 'toggle-btn--off' ?>" title="<?= $sl['active'] ? 'Активний — натисни щоб вимкнути' : 'Вимкнений — натисни щоб увімкнути' ?>">
            <?= $sl['active'] ? 'Активний' : 'Вимкнений' ?>
          </button>
        </form>

        <!-- Edit / Delete -->
        <div class="slide-actions">
          <button class="btn btn-sm btn-outline" onclick="openEditModal(<?= $sl['id'] ?>, <?= htmlspecialchars(json_encode($sl['label'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sl['title'])) ?>, <?= htmlspecialchars(json_encode($sl['subtitle'])) ?>)">✏️ Редагувати</button>
          <form method="post" onsubmit="return confirm('Видалити цей слайд?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $sl['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">🗑 Видалити</button>
          </form>
        </div>

      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════
     EDIT MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    <h3 class="modal-title">Редагувати слайд</h3>
    <form method="post" enctype="multipart/form-data" class="slide-form" style="margin-top:20px">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">

      <div class="form-field">
        <label>Нове зображення <small>(залиш порожнім щоб не міняти)</small></label>
        <input type="hidden" name="image_b64" id="slide_edit_b64">
        <div class="upload-zone" data-crop-ratio="16/9">
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-crop-hidden="slide_edit_b64">
          <div class="upload-zone__placeholder">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <span>Оберіть фото</span>
          </div>
          <div class="upload-preview" style="display:none"></div>
        </div>
      </div>

      <div class="form-field" style="margin-top:14px">
        <label>Мітка <small style="color:#bbb;font-weight:400">(над заголовком, необов'язково)</small></label>
        <input type="text" name="label" id="editLabel" maxlength="60">
      </div>
      <div class="form-field" style="margin-top:14px">
        <label>Заголовок</label>
        <input type="text" name="title" id="editTitle" maxlength="100" required>
      </div>
      <div class="form-field" style="margin-top:14px">
        <label>Підзаголовок</label>
        <input type="text" name="subtitle" id="editSubtitle" maxlength="160">
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn-primary">Зберегти</button>
        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Скасувати</button>
      </div>
    </form>
  </div>
</div>

<style>

.sf-row {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 24px;
  padding: 20px 24px 24px;
}
@media (max-width: 700px) { .sf-row { grid-template-columns: 1fr; } }

.form-field label  { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:6px; }
.form-field input[type="text"] {
  width:100%; padding:10px 14px; font-size:14px;
  border:1.5px solid #e0d8d0; border-radius:10px;
  outline:none; font-family:inherit;
  transition: border-color .2s;
}
.form-field input[type="text"]:focus { border-color:#8B4513; }
.req { color:#e53935; }

.upload-zone {
  position: relative;
  border: 2px dashed #e0d8d0;
  border-radius: 12px;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  min-height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.upload-zone:hover { border-color: #8B4513; background: #fdf8f4; }
.upload-zone input[type="file"] {
  position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 2;
}
.upload-zone__placeholder { pointer-events: none; }
.upload-zone__placeholder svg { margin: 0 auto 8px; }
.upload-zone__placeholder span { display:block; font-size:13px; color:#888; }
.upload-zone__placeholder small { font-size:11px; color:#bbb; margin-top:4px; display:block; }
.upload-preview img { max-height:120px; max-width:100%; border-radius:8px; object-fit:cover; }

.slides-list { padding: 0 24px 24px; }
.slide-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 0;
  border-bottom: 1px solid #f0e8df;
}
.slide-row:last-child { border-bottom: none; }
.slide-row--inactive { opacity: .5; }

.slide-order { display:flex; flex-direction:column; align-items:center; gap:2px; min-width:32px; }
.slide-num   { font-size:11px; color:#bbb; font-weight:600; }
.move-btn {
  background: none; border: none; cursor: pointer;
  font-size: 10px; color: #aaa; padding: 2px 4px; line-height:1;
  transition: color .15s;
}
.move-btn:hover:not(:disabled) { color: #8B4513; }
.move-btn:disabled { opacity: .3; cursor: default; }

.slide-thumb { width: 100px; height: 62px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: #f5ede0; }
.slide-thumb img { width:100%; height:100%; object-fit:cover; }

.slide-texts { flex: 1; min-width: 0; }
.slide-title { font-size:14px; font-weight:700; color:#2c2c2a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.slide-sub   { font-size:12px; color:#999; margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

.toggle-btn {
  padding: 5px 14px; border-radius: 20px; border: none;
  font-size: 12px; font-weight: 600; cursor: pointer;
  transition: background .2s;
}
.toggle-btn--on  { background: #e8f5e9; color: #2e7d32; }
.toggle-btn--off { background: #f5f5f5; color: #999; }
.toggle-btn--on:hover  { background: #c8e6c9; }
.toggle-btn--off:hover { background: #eeeeee; }

.slide-toggle-form { flex-shrink: 0; }

.slide-actions { display:flex; gap:8px; flex-shrink:0; }

.btn { display:inline-block; padding:10px 20px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none; transition:all .2s; font-family:inherit; }
.btn-primary { background:#8B4513; color:#fff; }
.btn-primary:hover { background:#6d3410; }
.btn-outline { background:#fff; border:1.5px solid #e0d8d0; color:#555; }
.btn-outline:hover { border-color:#8B4513; color:#8B4513; }
.btn-danger  { background:#ffebee; color:#c62828; border:none; }
.btn-danger:hover { background:#ffcdd2; }
.btn-sm { padding:6px 12px; font-size:12px; }

.panel { background:#fff; border-radius:14px; border:1px solid #f0e8df; overflow:hidden; }
.panel-head { padding:18px 24px 0; }
.panel-title { font-size:15px; font-weight:700; color:#2c2c2a; margin-bottom:12px; }

.admin-alert { padding:12px 18px; border-radius:10px; font-size:14px; margin-bottom:20px; }
.admin-alert--success { background:#e8f5e9; color:#2e7d32; }
.admin-alert--error   { background:#ffebee; color:#c62828; }

.modal-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.45); z-index:500;
  align-items:center; justify-content:center;
}
.modal-overlay.open { display:flex; }
.modal-box {
  background:#fff; border-radius:16px; padding:28px;
  width:min(500px,92vw); position:relative;
  max-height:90vh; overflow-y:auto;
}
.modal-close {
  position:absolute; top:14px; right:14px;
  background:none; border:none; font-size:18px;
  cursor:pointer; color:#aaa; line-height:1;
}
.modal-close:hover { color:#333; }
.modal-title { font-size:16px; font-weight:700; color:#2c2c2a; }
</style>

<script>
function openEditModal(id, label, title, subtitle) {
  document.getElementById('editId').value       = id;
  document.getElementById('editLabel').value    = label;
  document.getElementById('editTitle').value    = title;
  document.getElementById('editSubtitle').value = subtitle;
  openModal('editModal');
}

(function () {

  document.querySelector('.slides-list')?.addEventListener('click', function (e) {
    const btn = e.target.closest('.move-btn');
    if (!btn || btn.disabled) return;

    const id  = btn.dataset.id;
    const dir = btn.dataset.dir;
    const row = btn.closest('.slide-row');
    const list = this;
    const rows = Array.from(list.querySelectorAll('.slide-row'));
    const idx  = rows.indexOf(row);
    const swapIdx = dir === 'up' ? idx - 1 : idx + 1;
    if (swapIdx < 0 || swapIdx >= rows.length) return;

    const other = rows[swapIdx];

    /* Lock buttons during animation */
    list.querySelectorAll('.move-btn').forEach(b => b.disabled = true);

    /* Animate swap using real pixel positions */
    const aRect = row.offsetTop;
    const bRect = other.offsetTop;
    const aDelta = bRect - aRect;
    const bDelta = aRect - bRect;

    row.style.transition   = 'transform 0.28s cubic-bezier(0.4,0,0.2,1)';
    other.style.transition = 'transform 0.28s cubic-bezier(0.4,0,0.2,1)';
    row.style.transform    = `translateY(${aDelta}px)`;
    other.style.transform  = `translateY(${bDelta}px)`;

    row.addEventListener('transitionend', function onEnd() {
      row.removeEventListener('transitionend', onEnd);
      row.style.transition   = '';
      row.style.transform    = '';
      other.style.transition = '';
      other.style.transform  = '';

      /* Swap DOM nodes */
      if (dir === 'up') {
        list.insertBefore(row, other);
      } else {
        list.insertBefore(other, row);
      }

      /* Reindex numbers and disabled states */
      const updated = Array.from(list.querySelectorAll('.slide-row'));
      updated.forEach((r, i) => {
        r.querySelector('.slide-num').textContent = i + 1;
        const up   = r.querySelector('.move-btn[data-dir="up"]');
        const down = r.querySelector('.move-btn[data-dir="down"]');
        if (up)   up.disabled   = i === 0;
        if (down) down.disabled = i === updated.length - 1;
      });
    }, { once: true });

    /* Fire AJAX in parallel */
    const fd = new FormData();
    fd.append('action', 'move');
    fd.append('id', id);
    fd.append('dir', dir);
    fd.append('ajax', '1');
    fetch('hero_slides.php', { method: 'POST', body: fd });
  });

  document.querySelectorAll('.slide-toggle-form').forEach(form => {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const id  = this.querySelector('input[name="id"]').value;
      const btn = this.querySelector('.toggle-btn');
      const row = this.closest('.slide-row');

      const fd = new FormData();
      fd.append('action', 'toggle');
      fd.append('id', id);
      fd.append('ajax', '1');

      fetch('hero_slides.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          const on = data.active === 1;
          btn.className   = 'toggle-btn ' + (on ? 'toggle-btn--on' : 'toggle-btn--off');
          btn.textContent = on ? 'Активний' : 'Вимкнений';
          btn.title       = on ? 'Активний — натисни щоб вимкнути' : 'Вимкнений — натисни щоб увімкнути';
          row.classList.toggle('slide-row--inactive', !on);
        });
    });
  });

  document.querySelectorAll('.upload-zone').forEach(zone => {
    const input   = zone.querySelector('input[type="file"]');
    const preview = zone.querySelector('.upload-preview');
    const ph      = zone.querySelector('.upload-zone__placeholder');
    if (!input || !preview) return;
    input.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      preview.innerHTML = `<img src="${url}" alt="">`;
      preview.style.display = 'block';
      if (ph) ph.style.display = 'none';
    });
  });
})();
</script>

<?php include 'includes/layout_bottom.php'; ?>
