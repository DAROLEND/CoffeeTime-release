<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('content');

/* ── Auto-create table ── */
$conn->query("CREATE TABLE IF NOT EXISTS hero_slides (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  image      VARCHAR(255) NOT NULL,
  title      VARCHAR(255) NOT NULL DEFAULT '',
  subtitle   VARCHAR(255) NOT NULL DEFAULT '',
  sort_order TINYINT UNSIGNED DEFAULT 0,
  active     TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Seed from hardcoded slides if table is empty ── */
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

    /* ── ADD ── */
    if ($action === 'add') {
        $title    = trim($_POST['title']    ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');

        if (!$title) {
            $flash = 'Заголовок обовʼязковий.';
            $flashType = 'error';
        } else {
            $imagePath = '';

            if (!empty($_FILES['image']['name'])) {
                $uploadDir = __DIR__ . '/../static/images/slides/';
                $ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp'];
                if (!in_array($ext, $allowed)) {
                    $flash = 'Дозволені формати: JPG, PNG, WEBP.';
                    $flashType = 'error';
                } elseif ($_FILES['image']['size'] > 4 * 1024 * 1024) {
                    $flash = 'Файл занадто великий (макс 4 MB).';
                    $flashType = 'error';
                } else {
                    $fname = 'slide_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fname)) {
                        $imagePath = 'static/images/slides/' . $fname;
                    } else {
                        $flash = 'Не вдалося завантажити файл.';
                        $flashType = 'error';
                    }
                }
            }

            if (!$flash) {
                if (!$imagePath) {
                    $flash = 'Оберіть зображення.';
                    $flashType = 'error';
                } else {
                    $maxOrder = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0) AS m FROM hero_slides")->fetch_assoc()['m'];
                    $order = $maxOrder + 1;
                    $stmt  = $conn->prepare("INSERT INTO hero_slides (image, title, subtitle, sort_order) VALUES (?,?,?,?)");
                    $stmt->bind_param('sssi', $imagePath, $title, $subtitle, $order);
                    $stmt->execute();
                    $stmt->close();
                    $flash = 'Слайд додано.';
                }
            }
        }
    }

    /* ── EDIT ── */
    if ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $title    = trim($_POST['title']    ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');

        if ($id && $title) {
            /* Optional new image */
            if (!empty($_FILES['image']['name'])) {
                $uploadDir = __DIR__ . '/../static/images/slides/';
                $ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp'];
                if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 4 * 1024 * 1024) {
                    $fname = 'slide_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fname)) {
                        /* Delete old file if it's in slides/ */
                        $old = $conn->prepare("SELECT image FROM hero_slides WHERE id=?");
                        $old->bind_param('i', $id);
                        $old->execute();
                        $oldRow = $old->get_result()->fetch_assoc();
                        $old->close();
                        if ($oldRow && str_contains($oldRow['image'], 'slides/')) {
                            @unlink(__DIR__ . '/../' . $oldRow['image']);
                        }
                        $newImg = 'static/images/slides/' . $fname;
                        $stmt = $conn->prepare("UPDATE hero_slides SET image=?, title=?, subtitle=? WHERE id=?");
                        $stmt->bind_param('sssi', $newImg, $title, $subtitle, $id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            } else {
                $stmt = $conn->prepare("UPDATE hero_slides SET title=?, subtitle=? WHERE id=?");
                $stmt->bind_param('ssi', $title, $subtitle, $id);
                $stmt->execute();
                $stmt->close();
            }
            $flash = 'Збережено.';
        }
    }

    /* ── DELETE ── */
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

    /* ── TOGGLE ACTIVE ── */
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->prepare("UPDATE hero_slides SET active = 1 - active WHERE id=?")->execute() || null;
            $stmt = $conn->prepare("UPDATE hero_slides SET active = 1 - active WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: hero_slides.php');
        exit;
    }

    /* ── MOVE (sort order) ── */
    if ($action === 'move') {
        $id  = (int)($_POST['id']        ?? 0);
        $dir = $_POST['dir'] ?? '';
        if ($id && in_array($dir, ['up', 'down'])) {
            /* Get all slides sorted */
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
        <div class="upload-zone">
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
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
          <form method="post" style="display:contents">
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="id" value="<?= $sl['id'] ?>">
            <button name="dir" value="up"   class="move-btn" title="Вгору"  <?= $i === 0 ? 'disabled' : '' ?>>▲</button>
            <button name="dir" value="down" class="move-btn" title="Вниз"   <?= $i === count($slides)-1 ? 'disabled' : '' ?>>▼</button>
          </form>
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
          <button class="btn btn-sm btn-outline" onclick="openEditModal(<?= $sl['id'] ?>, <?= htmlspecialchars(json_encode($sl['title'])) ?>, <?= htmlspecialchars(json_encode($sl['subtitle'])) ?>)">✏️ Редагувати</button>
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
        <div class="upload-zone">
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
          <div class="upload-zone__placeholder">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <span>Оберіть фото</span>
          </div>
          <div class="upload-preview" style="display:none"></div>
        </div>
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
/* ── Slide form layout ── */
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

/* ── Upload zone ── */
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

/* ── Slides list ── */
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

/* ── Buttons ── */
.btn { display:inline-block; padding:10px 20px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none; transition:all .2s; font-family:inherit; }
.btn-primary { background:#8B4513; color:#fff; }
.btn-primary:hover { background:#6d3410; }
.btn-outline { background:#fff; border:1.5px solid #e0d8d0; color:#555; }
.btn-outline:hover { border-color:#8B4513; color:#8B4513; }
.btn-danger  { background:#ffebee; color:#c62828; border:none; }
.btn-danger:hover { background:#ffcdd2; }
.btn-sm { padding:6px 12px; font-size:12px; }

/* ── Panel ── */
.panel { background:#fff; border-radius:14px; border:1px solid #f0e8df; overflow:hidden; }
.panel-head { padding:18px 24px 0; }
.panel-title { font-size:15px; font-weight:700; color:#2c2c2a; margin-bottom:12px; }

/* ── Alert ── */
.admin-alert { padding:12px 18px; border-radius:10px; font-size:14px; margin-bottom:20px; }
.admin-alert--success { background:#e8f5e9; color:#2e7d32; }
.admin-alert--error   { background:#ffebee; color:#c62828; }

/* ── Modal ── */
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
function openEditModal(id, title, subtitle) {
  document.getElementById('editId').value       = id;
  document.getElementById('editTitle').value    = title;
  document.getElementById('editSubtitle').value = subtitle;
  openModal('editModal');
}
</script>

<?php include 'includes/layout_bottom.php'; ?>
