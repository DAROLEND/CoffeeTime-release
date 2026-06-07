<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/auth_check.php';
require_perm('content');

$pageTitle  = 'Галерея';
$activePage = 'gallery';

$galleryDir = __DIR__ . '/../static/images/gallery/';
$galleryWeb = '../static/images/gallery/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data   = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        $stmt = $conn->prepare("SELECT filename FROM gallery WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $path = $galleryDir . $row['filename'];
            if (file_exists($path)) @unlink($path);
            $stmt = $conn->prepare("DELETE FROM gallery WHERE id=?");
            $stmt->bind_param("i", $id);
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => $ok]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    if ($action === 'set_category') {
        $id  = (int)($data['id'] ?? 0);
        $cat = in_array($data['category'] ?? '', ['food','interior']) ? $data['category'] : 'food';
        $stmt = $conn->prepare("UPDATE gallery SET category=? WHERE id=?");
        $stmt->bind_param("si", $cat, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }

    if ($action === 'set_alt') {
        $id  = (int)($data['id'] ?? 0);
        $alt = mb_substr(trim($data['alt'] ?? ''), 0, 255);
        $stmt = $conn->prepare("UPDATE gallery SET alt=? WHERE id=?");
        $stmt->bind_param("si", $alt, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

$errors   = [];
$uploaded = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $files       = $_FILES['photos'];
    $count       = is_array($files['name']) ? count($files['name']) : 0;
    $allowed_ext = ['jpg','jpeg','png','webp','gif'];
    $category    = in_array($_POST['category'] ?? '', ['food','interior']) ? $_POST['category'] : 'food';
    $alt         = mb_substr(trim($_POST['alt'] ?? ''), 0, 255);

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            $errors[] = htmlspecialchars($files['name'][$i]) . ': непідтримуваний формат.';
            continue;
        }
        $newName = uniqid('gallery_', true) . '.' . $ext;
        $dest    = $galleryDir . $newName;
        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $fileAlt = $alt ?: pathinfo($files['name'][$i], PATHINFO_FILENAME);
            $stmt = $conn->prepare("INSERT INTO gallery (filename, alt, category) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $newName, $fileAlt, $category);
            $stmt->execute();
            $stmt->close();
            $uploaded++;
        } else {
            $errors[] = 'Не вдалося зберегти ' . htmlspecialchars($files['name'][$i]);
        }
    }
    if ($uploaded > 0) { header("Location: admin_gallery.php?uploaded=$uploaded"); exit; }
}

$filterCat = trim($_GET['cat'] ?? '');
$allowed_cats = ['food', 'interior'];

$where = '';
$params = [];
$types  = '';
if (in_array($filterCat, $allowed_cats)) {
    $where    = "WHERE category=?";
    $params[] = $filterCat;
    $types    = 's';
}

$imageFiles = [];
$stmt = $conn->prepare("SELECT * FROM gallery $where ORDER BY created_at DESC");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $imageFiles[] = $row;
$stmt->close();

$counts = ['all' => 0, 'food' => 0, 'interior' => 0];
$r = $conn->query("SELECT category, COUNT(*) AS c FROM gallery GROUP BY category");
if ($r) while ($row = $r->fetch_assoc()) {
    $counts[$row['category']] = (int)$row['c'];
    $counts['all'] += (int)$row['c'];
}

include 'includes/layout_top.php';
?>

<?php if (isset($_GET['uploaded'])): ?>
  <div class="alert alert-success" style="margin-bottom:16px">
    Завантажено <?= (int)$_GET['uploaded'] ?> фото!
  </div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
  <div class="alert alert-error" style="margin-bottom:8px"><?= $e ?></div>
<?php endforeach; ?>

<!-- Section head -->
<div class="section-head" style="margin-bottom:18px">
  <h2 class="section-title">Фото галереї</h2>
  <span style="color:#999;font-size:13px"><?= $counts['all'] ?> фото</span>
</div>

<!-- Upload zone -->
<form method="post" enctype="multipart/form-data" id="galleryUploadForm">
  <div class="gallery-upload-zone" id="galleryDropZone">
    <input type="file" name="photos[]" id="galleryFileInput" accept="image/*" multiple>
    <div class="gallery-upload-icon">
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#d4a96a" stroke-width="1.5" stroke-linecap="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/>
        <line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
    </div>
    <p class="gallery-upload-title">Перетягніть фото сюди</p>
    <p class="gallery-upload-sub">або натисніть для вибору · PNG, JPG, WebP до 5 МБ</p>
    <div class="gallery-upload-previews" id="galleryPreviews"></div>
  </div>

  <!-- Upload options (shown after file pick) -->
  <div class="gallery-upload-options" id="galleryUploadOptions" style="display:none">
    <div class="guo-field">
      <label class="im-label">Категорія</label>
      <div class="guo-cat-btns">
        <label class="guo-cat-btn">
          <input type="radio" name="category" value="food" checked>
          <span>Їжа</span>
        </label>
        <label class="guo-cat-btn">
          <input type="radio" name="category" value="interior">
          <span>Інтер'єр</span>
        </label>
      </div>
    </div>
    <div class="guo-field">
      <label class="im-label" for="altInput">Підпис (необов'язково)</label>
      <input type="text" name="alt" id="altInput" class="im-control" placeholder="напр. Піца маргарита">
    </div>
    <button type="submit" class="im-btn-save" style="align-self:flex-end">
      Завантажити
    </button>
  </div>
</form>

<!-- Category filter tabs -->
<div class="cat-tabs" style="margin:20px 0 16px">
  <?php
  $catTabs = ['' => 'Всі', 'food' => 'Їжа', 'interior' => 'Інтер\'єр'];
  $catKeys = ['all', 'food', 'interior'];
  $i = 0;
  foreach ($catTabs as $val => $lbl):
  ?>
    <a href="admin_gallery.php<?= $val ? '?cat='.$val : '' ?>"
       class="cat-tab <?= $filterCat === $val ? 'active' : '' ?>">
      <?= $lbl ?>
      <span class="cat-tab-count"><?= $counts[$catKeys[$i]] ?></span>
    </a>
  <?php $i++; endforeach; ?>
</div>

<!-- Gallery grid -->
<?php if (empty($imageFiles)): ?>
  <p style="color:#bbb;text-align:center;padding:48px;font-size:14px">Галерея порожня</p>
<?php else: ?>
<div class="gallery-grid" id="galleryGrid">
  <?php foreach ($imageFiles as $img): ?>
  <div class="gallery-cell" id="gcell-<?= $img['id'] ?>">
    <img src="<?= $galleryWeb . htmlspecialchars($img['filename']) ?>" alt="" loading="lazy">

    <!-- Category badge -->
    <div class="gc-badge gc-badge--<?= $img['category'] ?>" id="gbadge-<?= $img['id'] ?>">
      <?= $img['category'] === 'food' ? 'Їжа' : 'Інтер\'єр' ?>
    </div>

    <div class="photo-overlay">
      <!-- Change category -->
      <button class="gc-action-btn gc-cat-toggle"
              title="Змінити категорію"
              onclick="toggleCategory(<?= $img['id'] ?>, '<?= $img['category'] ?>', this)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
      </button>
      <!-- Delete -->
      <button class="delete-photo-btn"
              title="Видалити"
              onclick="deletePhoto(<?= $img['id'] ?>, this)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
var galleryDir = '<?= $galleryWeb ?>';

var dropZone   = document.getElementById('galleryDropZone');
var fileInput  = document.getElementById('galleryFileInput');
var uploadOpts = document.getElementById('galleryUploadOptions');

fileInput.addEventListener('change', function () {
  if (!this.files || !this.files.length) return;
  showPreviews(this.files);
  uploadOpts.style.display = 'flex';
});

dropZone.addEventListener('dragover',  function (e) { e.preventDefault(); this.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', function ()  { this.classList.remove('drag-over'); });
dropZone.addEventListener('drop', function (e) {
  e.preventDefault(); this.classList.remove('drag-over');
  var dt = e.dataTransfer;
  if (dt.files && dt.files.length) {
    fileInput.files = dt.files;
    showPreviews(dt.files);
    uploadOpts.style.display = 'flex';
  }
});

function showPreviews(files) {
  var p = document.getElementById('galleryPreviews');
  p.innerHTML = '';
  Array.from(files).slice(0, 8).forEach(function (f) {
    var r = new FileReader();
    r.onload = function (e) {
      var img = document.createElement('img');
      img.src = e.target.result;
      img.className = 'upload-preview-thumb';
      p.appendChild(img);
    };
    r.readAsDataURL(f);
  });
}

function toggleCategory(id, currentCat, btn) {
  var newCat = currentCat === 'food' ? 'interior' : 'food';
  fetch('admin_gallery.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({action:'set_category', id: id, category: newCat})
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    if (!data.success) return;
    btn.closest('.gallery-cell').dataset.cat = newCat;
    var badge = document.getElementById('gbadge-' + id);
    if (badge) {
      badge.className = 'gc-badge gc-badge--' + newCat;
      badge.textContent = newCat === 'food' ? 'Їжа' : 'Інтер\'єр';
    }
    /* Update btn data */
    btn.setAttribute('onclick', "toggleCategory(" + id + ", '" + newCat + "', this)");
    if (typeof showAdminToast === 'function')
      showAdminToast('Категорію змінено на: ' + (newCat === 'food' ? 'Їжа' : "Інтер'єр"), 'success');
  });
}

function deletePhoto(id, btn) {
  if (!confirm('Видалити це фото?')) return;
  var cell = document.getElementById('gcell-' + id);
  fetch('admin_gallery.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({action:'delete', id: id})
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    if (data.success) {
      cell.classList.add('deleting');
      setTimeout(function(){ cell.remove(); }, 350);
      if (typeof showAdminToast === 'function') showAdminToast('Фото видалено', 'success');
    }
  });
}

document.getElementById('galleryGrid') && document.getElementById('galleryGrid').addEventListener('click', function (e) {
  if (e.target.closest('.delete-photo-btn') || e.target.closest('.gc-cat-toggle')) return;
  var cell = e.target.closest('.gallery-cell');
  if (!cell) return;
  var img = cell.querySelector('img');
  if (!img) return;
  var lb = document.createElement('div');
  lb.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:zoom-out;backdrop-filter:blur(4px)';
  var lbImg = document.createElement('img');
  lbImg.src = img.src;
  lbImg.style.cssText = 'max-width:90vw;max-height:90vh;border-radius:10px;box-shadow:0 8px 48px rgba(0,0,0,.7)';
  lb.appendChild(lbImg);
  lb.addEventListener('click', function(){ lb.remove(); });
  document.addEventListener('keydown', function esc(e){ if(e.key==='Escape'){lb.remove();document.removeEventListener('keydown',esc);} });
  document.body.appendChild(lb);
});
</script>

<?php include 'includes/layout_bottom.php'; ?>
