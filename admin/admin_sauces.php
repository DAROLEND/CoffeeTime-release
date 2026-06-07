<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/auth_check.php';
require_perm('products');

$pageTitle  = 'Соуси';
$activePage = 'sauces';

$_chk = $conn->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sauces' AND column_name='image'");
if ($_chk && $_chk->num_rows === 0) { $conn->query("ALTER TABLE sauces ADD COLUMN image VARCHAR(255) NOT NULL DEFAULT ''"); }
unset($_chk);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = trim($_POST['action'] ?? '');

    if ($action === 'add') {
        $name       = trim($_POST['name']       ?? '');
        $price      = floatval($_POST['price']  ?? 0);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $active     = isset($_POST['active']) ? 1 : 0;
        if ($name === '') { echo json_encode(['success'=>false,'error'=>'Назва обовʼязкова']); exit; }

        $imagePath = '';
        $uploadDir = __DIR__ . '/../static/images/menu_items/sauces/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (!empty($_POST['sauce_image_b64'])) {
            $fname = 'sauce_' . time() . '_' . mt_rand(1000,9999);
            $ext   = save_cropped_image($_POST['sauce_image_b64'], $uploadDir . $fname . '.jpg');
            if ($ext) $imagePath = 'static/images/menu_items/sauces/' . $fname . '.' . $ext;
        } elseif (!empty($_FILES['sauce_image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['sauce_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['sauce_image']['size'] <= 2*1024*1024) {
                $fname = 'sauce_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['sauce_image']['tmp_name'], $uploadDir . $fname))
                    $imagePath = 'static/images/menu_items/sauces/' . $fname;
            }
        }

        $stmt = $conn->prepare("INSERT INTO sauces (name, price, image, emoji, active, sort_order) VALUES (?, ?, ?, '', ?, ?)");
        $stmt->bind_param("sdsii", $name, $price, $imagePath, $active, $sort_order);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();
        echo json_encode(['success'=>true,'id'=>$newId,'image'=>$imagePath]);
        exit;
    }

    if ($action === 'update') {
        $id         = intval($_POST['id']        ?? 0);
        $name       = trim($_POST['name']        ?? '');
        $price      = floatval($_POST['price']   ?? 0);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $active     = isset($_POST['active']) ? 1 : 0;
        if (!$id || $name === '') { echo json_encode(['success'=>false,'error'=>'Невірні дані']); exit; }

        $uploadDir2 = __DIR__ . '/../static/images/menu_items/sauces/';
        if (!is_dir($uploadDir2)) mkdir($uploadDir2, 0755, true);
        $imgPath2 = '';
        if (!empty($_POST['sauce_image_b64'])) {
            $fname = 'sauce_' . time() . '_' . mt_rand(1000,9999);
            $ext   = save_cropped_image($_POST['sauce_image_b64'], $uploadDir2 . $fname . '.jpg');
            if ($ext) $imgPath2 = 'static/images/menu_items/sauces/' . $fname . '.' . $ext;
        } elseif (!empty($_FILES['sauce_image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['sauce_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['sauce_image']['size'] <= 2*1024*1024) {
                $fname = 'sauce_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['sauce_image']['tmp_name'], $uploadDir2 . $fname))
                    $imgPath2 = 'static/images/menu_items/sauces/' . $fname;
            }
        }
        if ($imgPath2) {
            $imgStmt = $conn->prepare("UPDATE sauces SET image=? WHERE id=?");
            $imgStmt->bind_param("si", $imgPath2, $id);
            $imgStmt->execute();
            $imgStmt->close();
        }

        $stmt = $conn->prepare("UPDATE sauces SET name=?, price=?, active=?, sort_order=? WHERE id=?");
        $stmt->bind_param("sdiii", $name, $price, $active, $sort_order, $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'toggle') {
        $id     = intval($_POST['id']     ?? 0);
        $active = intval($_POST['active'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false]); exit; }
        $stmt = $conn->prepare("UPDATE sauces SET active=? WHERE id=?");
        $stmt->bind_param("ii", $active, $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false]); exit; }
        $stmt = $conn->prepare("DELETE FROM sauces WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>true]);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']);
    exit;
}

$sauces = [];
$res = $conn->query("SELECT id, name, price, image, active, sort_order FROM sauces ORDER BY sort_order, id");
if ($res) while ($row = $res->fetch_assoc()) $sauces[] = $row;

require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-header">
  <div>
    <div class="aph-title">Соуси</div>
    <div class="aph-sub">Управління соусами до страв</div>
  </div>
  <button class="btn-primary" id="btnAddSauce" onclick="openAddForm()">+ Додати соус</button>
</div>

<!-- Add / Edit form -->
<div class="admin-card sauce-panel" id="sauceFormPanel">
  <div class="sauce-panel__title" id="sfpTitle">Новий соус</div>
  <form id="sauceForm" onsubmit="saveForm(event)" enctype="multipart/form-data">
    <input type="hidden" id="sfId" name="id">
    <div class="sauce-panel__row">
      <div class="sauce-panel__field sauce-panel__field--grow">
        <label>Назва</label>
        <input type="text" id="sfName" name="name" placeholder="Кетчуп" required>
      </div>
      <div class="sauce-panel__field">
        <label>Ціна (₴)</label>
        <input type="number" id="sfPrice" name="price" placeholder="15" step="1" min="0" required>
      </div>
      <div class="sauce-panel__field sauce-panel__field--check">
        <label class="sauce-check">
          <input type="checkbox" id="sfActive" name="active" checked>
          <span>Активний</span>
        </label>
      </div>
    </div>
    <div class="sauce-panel__row" style="margin-top:10px;align-items:center">
      <div class="sauce-panel__field" style="flex:1">
        <label>Фото <small style="font-weight:400;text-transform:none;letter-spacing:0">(JPG/PNG/WEBP · до 2 MB · залиш порожнім щоб не міняти)</small></label>
        <input type="file" id="sfImage" name="sauce_image" accept="image/jpeg,image/png,image/webp" style="padding:6px 10px">
      </div>
      <div id="sfImgPreview" style="width:52px;height:52px;border-radius:8px;overflow:hidden;flex-shrink:0;display:none;background:#f5f0eb">
        <img id="sfImgPreviewImg" src="" style="width:100%;height:100%;object-fit:cover">
      </div>
    </div>
    <div class="sauce-panel__actions">
      <button type="submit" class="btn-primary">Зберегти</button>
      <button type="button" class="btn btn-ghost" onclick="closeForm()">Скасувати</button>
    </div>
  </form>
</div>

<!-- Table -->
<div class="admin-card" style="overflow:hidden">
  <?php if (empty($sauces)): ?>
    <div style="padding:48px;text-align:center;color:#bbb;font-size:14px">Соусів ще немає. Додайте перший!</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="admin-table" id="saucesTable">
      <thead>
        <tr>
          <th style="width:52px">#</th>
          <th style="width:60px">Фото</th>
          <th>Назва</th>
          <th style="width:130px">Ціна</th>
          <th style="width:130px">Статус</th>
          <th style="width:180px"></th>
        </tr>
      </thead>
      <tbody id="saucesBody">
        <?php foreach ($sauces as $i => $s): ?>
        <tr data-id="<?= $s['id'] ?>">
          <td style="color:#bbb;font-size:13px"><?= $i + 1 ?></td>
          <td>
            <?php if (!empty($s['image']) && !str_contains($s['image'], 'default.jpg') && file_exists(__DIR__ . '/../' . $s['image'])): ?>
              <img src="../<?= htmlspecialchars($s['image']) ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;display:block">
            <?php else: ?>
              <div style="width:40px;height:40px;border-radius:8px;background:#f5f0eb;display:flex;align-items:center;justify-content:center">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c8b9a8" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              </div>
            <?php endif; ?>
          </td>
          <td style="font-weight:600;color:#2c2c2a"><?= htmlspecialchars($s['name']) ?></td>
          <td><strong style="color:#5a2d0c"><?= number_format((float)$s['price'], 0) ?> ₴</strong></td>
          <td>
            <button class="sauce-toggle <?= $s['active'] ? 'sauce-toggle--on' : 'sauce-toggle--off' ?>"
                    data-id="<?= $s['id'] ?>" data-active="<?= $s['active'] ?>"
                    onclick="toggleSauce(this)">
              <?= $s['active'] ? 'Активний' : 'Вимкнено' ?>
            </button>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn-ghost btn-sm"
                      onclick='openEditForm(<?= $s["id"] ?>, <?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>
                Редагувати
              </button>
              <button class="sauce-del-btn btn-sm"
                      onclick="deleteSauce(<?= $s['id'] ?>, this)">
                Видалити
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<style>

.sauce-panel {
  display: none;
  margin-bottom: 16px;
  padding: 22px 24px;
  animation: sfpIn .18s ease;
}
.sauce-panel.visible { display: block; }
@keyframes sfpIn {
  from { opacity:0; transform:translateY(-6px); }
  to   { opacity:1; transform:translateY(0); }
}
.sauce-panel__title {
  font-size: 15px; font-weight: 700; color: #5a2d0c; margin-bottom: 18px;
}
.sauce-panel__row {
  display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
}
.sauce-panel__field { display: flex; flex-direction: column; gap: 5px; }
.sauce-panel__field--grow { flex: 1; min-width: 180px; }
.sauce-panel__field label {
  font-size: 11px; font-weight: 600; color: #999; text-transform: uppercase; letter-spacing: .04em;
}
.sauce-panel__field input[type=text],
.sauce-panel__field input[type=number] {
  padding: 8px 11px; border: 1.5px solid #e0d8d0; border-radius: 9px;
  font-size: 13.5px; outline: none; transition: border-color .15s;
  font-family: inherit; width: 100%; box-sizing: border-box;
}
.sauce-panel__field input:focus { border-color: #8B4513; }
.sauce-panel__field--check { justify-content: flex-end; padding-bottom: 2px; }
.sauce-check {
  display: flex; align-items: center; gap: 7px;
  font-size: 13px; color: #555; cursor: pointer; user-select: none;
}
.sauce-check input { accent-color: #8B4513; width: 15px; height: 15px; cursor: pointer; }
.sauce-panel__actions { display: flex; gap: 8px; margin-top: 16px; }

.sauce-toggle {
  display: inline-flex; align-items: center;
  padding: 4px 12px; border-radius: 20px; border: none;
  font-size: 12px; font-weight: 600; cursor: pointer; transition: .15s;
}
.sauce-toggle--on  { background: #e6f9ee; color: #2e7d32; }
.sauce-toggle--off { background: #fdecea; color: #c62828; }

.sauce-del-btn {
  padding: 5px 12px; border-radius: 8px;
  background: none; border: 1.5px solid #f5c6c6;
  color: #c62828; font-size: 12.5px; font-weight: 600;
  cursor: pointer; transition: background .15s, border-color .15s;
}
.sauce-del-btn:hover { background: #fdecea; border-color: #e57373; }

.btn-primary {
  padding: 9px 20px; background: #8B4513; color: #fff; border: none;
  border-radius: 10px; font-size: 13.5px; font-weight: 600;
  cursor: pointer; transition: background .15s; font-family: inherit;
}
.btn-primary:hover { background: #6b3410; }
</style>

<script>
var formPanel = document.getElementById('sauceFormPanel');
var editMode  = false;

var sfImageInput   = document.getElementById('sfImage');
var sfImgPreview   = document.getElementById('sfImgPreview');
var sfImgPreviewImg = document.getElementById('sfImgPreviewImg');

sfImageInput && sfImageInput.addEventListener('change', function () {
  if (!this.files || !this.files[0]) return;
  var reader = new FileReader();
  reader.onload = function (e) {
    sfImgPreviewImg.src = e.target.result;
    sfImgPreview.style.display = 'block';
  };
  reader.readAsDataURL(this.files[0]);
});

function openAddForm() {
  editMode = false;
  document.getElementById('sfpTitle').textContent = 'Новий соус';
  document.getElementById('sfId').value    = '';
  document.getElementById('sfName').value  = '';
  document.getElementById('sfPrice').value = '';
  document.getElementById('sfActive').checked = true;
  sfImageInput.value = '';
  sfImgPreview.style.display = 'none';
  formPanel.classList.add('visible');
  setTimeout(function(){ document.getElementById('sfName').focus(); }, 50);
}

function openEditForm(id, data) {
  editMode = true;
  document.getElementById('sfpTitle').textContent = 'Редагування: ' + (data.name || '');
  document.getElementById('sfId').value    = id;
  document.getElementById('sfName').value  = data.name  || '';
  document.getElementById('sfPrice').value = data.price || '';
  document.getElementById('sfActive').checked = (data.active == 1);
  sfImageInput.value = '';
  if (data.image) {
    sfImgPreviewImg.src = '../' + data.image;
    sfImgPreview.style.display = 'block';
  } else {
    sfImgPreview.style.display = 'none';
  }
  formPanel.classList.add('visible');
  formPanel.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

function closeForm() {
  formPanel.classList.remove('visible');
}

function saveForm(e) {
  e.preventDefault();
  var form = document.getElementById('sauceForm');
  var data = new FormData(form);
  data.append('action', editMode ? 'update' : 'add');
  if (document.getElementById('sfActive').checked) data.set('active','1');
  else data.delete('active');

  fetch('admin_sauces.php', {
    method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:data
  })
  .then(function(r){ return r.json(); })
  .then(function(res) {
    if (res.success) { closeForm(); location.reload(); }
    else alert(res.error || 'Помилка збереження');
  });
}

function toggleSauce(btn) {
  var id     = parseInt(btn.dataset.id);
  var active = parseInt(btn.dataset.active);
  var newVal = active ? 0 : 1;
  fetch('admin_sauces.php', {
    method:'POST',
    headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
    body:'action=toggle&id='+id+'&active='+newVal
  })
  .then(function(r){ return r.json(); })
  .then(function(res) {
    if (res.success) {
      btn.dataset.active = newVal;
      btn.className = 'sauce-toggle ' + (newVal ? 'sauce-toggle--on' : 'sauce-toggle--off');
      btn.textContent = newVal ? 'Активний' : 'Вимкнено';
    }
  });
}

function deleteSauce(id, btn) {
  if (!confirm('Видалити цей соус?')) return;
  fetch('admin_sauces.php', {
    method:'POST',
    headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
    body:'action=delete&id='+id
  })
  .then(function(r){ return r.json(); })
  .then(function(res) {
    if (res.success) {
      var row = btn.closest('tr');
      if (row) { row.style.transition='opacity .25s'; row.style.opacity='0'; setTimeout(function(){ row.remove(); },250); }
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
