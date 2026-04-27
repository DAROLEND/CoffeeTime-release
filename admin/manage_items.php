<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_perm('products');

$pageTitle  = 'Товари';
$activePage = 'products';

$allowed = ['coffee_items','fast_food_items','pizza_items','cold_drink_items','dessert_items','giftcards','sushi_items','sushi_sets','salad_items','cake_items'];
$categoryNames = [
    'coffee_items'     => 'Кава',
    'fast_food_items'  => 'Фаст-фуд',
    'pizza_items'      => 'Піца',
    'cold_drink_items' => 'Холодні напої',
    'dessert_items'    => 'Десерти',
    'giftcards'        => 'Подарункові картки',
    'sushi_items'      => 'Суші',
    'sushi_sets'       => 'Сети суші',
    'salad_items'      => 'Салати',
    'cake_items'       => 'Торти на замовлення',
];

$category = $_GET['category'] ?? 'coffee_items';
if (!in_array($category, $allowed)) $category = 'coffee_items';
$catTitle = $categoryNames[$category];

/* ── Popularity by product_id ── */
$popularity = [];
$r = $conn->query("SELECT product_id, SUM(quantity) AS sold FROM order_items WHERE category='$category' GROUP BY product_id");
if ($r) while ($row = $r->fetch_assoc()) $popularity[(int)$row['product_id']] = (int)$row['sold'];

/* ── Items ── */
$items = [];
$res = $conn->query("SELECT * FROM `$category` ORDER BY id DESC");
if ($res) while ($row = $res->fetch_assoc()) $items[] = $row;

/* ── Tab counts ── */
$tabCounts = [];
foreach ($allowed as $t) {
    $r2 = $conn->query("SELECT COUNT(*) AS c FROM `$t`");
    $tabCounts[$t] = $r2 ? (int)$r2->fetch_assoc()['c'] : 0;
}

/* giftcards uses 'title' not 'name' — normalize */
$nameCol = ($category === 'giftcards') ? 'title' : 'name';

include 'includes/layout_top.php';
?>

<div class="cat-tabs">
  <?php foreach ($allowed as $t): ?>
    <a href="manage_items.php?category=<?= $t ?>"
       class="cat-tab <?= $category===$t?'active':'' ?>">
      <?= $categoryNames[$t] ?>
      <span class="cat-tab-count"><?= $tabCounts[$t] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="section-head">
  <h2 class="section-title"><?= htmlspecialchars($catTitle) ?></h2>
  <button class="btn-add-item" onclick="openItemModal()">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Додати товар
  </button>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success" style="margin:0 0 16px">Товар збережено!</div>
<?php endif; ?>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Фото</th><th>Назва</th><th>Опис</th>
        <th>Ціна</th><th>Продажі</th><th>Дії</th>
      </tr>
    </thead>
    <tbody id="itemsTableBody">
      <?php foreach ($items as $item):
        $name = $item[$nameCol] ?? $item['name'] ?? '—';
        $desc = $item['description'] ?? '';
        $sold = $popularity[$item['id']] ?? 0;
      ?>
      <tr id="item-row-<?= $item['id'] ?>">
        <td>
          <?php if (!empty($item['image'])): ?>
            <img src="../<?= htmlspecialchars($item['image']) ?>" alt=""
                 style="width:52px;height:52px;object-fit:cover;border-radius:8px;border:1px solid #ede5dd">
          <?php else: ?>
            <div style="width:52px;height:52px;background:#f0e8df;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px">☕</div>
          <?php endif; ?>
        </td>
        <td><strong><?= htmlspecialchars($name) ?></strong></td>
        <td class="td-desc"><?= htmlspecialchars(mb_substr($desc,0,70)) ?><?= mb_strlen($desc)>70?'…':'' ?></td>
        <td><?= number_format($item['price'],2) ?> ₴</td>
        <td>
          <?php if ($sold > 0): ?>
            <span class="sold-badge"><?= $sold ?> шт</span>
          <?php else: ?>
            <span style="color:#ccc">—</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="item-actions" id="actions-<?= $item['id'] ?>">
            <button class="item-btn-edit"
                    onclick='openItemModal(<?= $item["id"] ?>, <?= htmlspecialchars(json_encode(array_merge($item, ["_nameCol" => $nameCol])), ENT_QUOTES) ?>)'>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Редагувати
            </button>
            <button class="item-btn-delete" onclick="confirmDelete(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($name)) ?>')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              Видалити
            </button>
          </div>
          <div class="delete-confirm" id="confirm-<?= $item['id'] ?>" style="display:none">
            <span>Видалити товар?</span>
            <button class="confirm-yes" onclick="doDelete(<?= $item['id'] ?>)">Так</button>
            <button class="confirm-no"  onclick="cancelDelete(<?= $item['id'] ?>)">Ні</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
        <tr><td colspan="6" style="text-align:center;color:#bbb;padding:32px">Товарів у цій категорії немає</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ══ Item Modal ══ -->
<div class="modal-overlay" id="itemModalOverlay">
  <div class="modal item-modal">
    <button class="modal-close-btn" onclick="closeItemModal()">✕</button>
    <h3 class="item-modal-title" id="itemModalTitle">Додати товар</h3>

    <form id="itemModalForm" method="post" enctype="multipart/form-data"
          action="add_item.php?category=<?= $category ?>">
      <input type="hidden" name="_item_id"  id="itemModalId"     value="">
      <input type="hidden" name="_action"   id="itemModalAction" value="add">

      <div class="item-modal-grid">
        <div class="form-group">
          <label class="im-label">Назва *</label>
          <input class="im-control" type="text" name="name" id="imName" required>
        </div>
        <div class="form-group">
          <label class="im-label">Ціна (₴) *</label>
          <input class="im-control" type="number" name="price" id="imPrice" step="0.01" min="0" required>
        </div>
      </div>

      <div class="form-group" style="margin-top:18px">
        <label class="im-label">Опис</label>
        <textarea class="im-control im-textarea" name="description" id="imDesc"></textarea>
      </div>

      <div class="form-group" style="margin-top:18px">
        <label class="im-label">Фото</label>
        <div class="im-photo-zone" id="imPhotoZone">
          <!-- Filled by JS -->
          <div class="im-photo-placeholder" id="imPhotoPlaceholder">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#c49a6c" stroke-width="1.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <span>Клікніть або перетягніть фото</span>
          </div>
          <img id="imPhotoPreview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:10px">
          <div class="im-photo-label" id="imPhotoLabel" style="display:none">Поточне фото</div>
          <input type="file" name="image" id="imImage" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%">
        </div>
      </div>

      <div class="im-modal-footer">
        <button type="button" class="im-btn-cancel" onclick="closeItemModal()">Скасувати</button>
        <button type="submit" class="im-btn-save">Зберегти</button>
      </div>
    </form>
  </div>
</div>

<script>
var currentCategory = '<?= $category ?>';

function openItemModal(id, item) {
  var overlay = document.getElementById('itemModalOverlay');
  var form    = document.getElementById('itemModalForm');
  var nameCol = item && item._nameCol ? item._nameCol : 'name';

  if (id && item) {
    document.getElementById('itemModalTitle').textContent = 'Редагувати товар';
    document.getElementById('itemModalId').value          = id;
    document.getElementById('itemModalAction').value      = 'edit';
    document.getElementById('imName').value               = item[nameCol] || item.name || '';
    document.getElementById('imPrice').value              = item.price || '';
    var descEl = document.getElementById('imDesc');
    descEl.value = item.description || '';
    descEl.style.height = 'auto';
    descEl.style.height = Math.max(80, descEl.scrollHeight) + 'px';
    form.action = 'edit_item.php?category=' + currentCategory + '&id=' + id;

    if (item.image) {
      document.getElementById('imPhotoPreview').src     = '../' + item.image;
      document.getElementById('imPhotoPreview').style.display = 'block';
      document.getElementById('imPhotoPlaceholder').style.display = 'none';
      document.getElementById('imPhotoLabel').style.display = 'block';
    } else {
      resetPhotoZone();
    }
  } else {
    document.getElementById('itemModalTitle').textContent = 'Додати товар';
    document.getElementById('itemModalId').value          = '';
    document.getElementById('itemModalAction').value      = 'add';
    document.getElementById('imName').value               = '';
    document.getElementById('imPrice').value              = '';
    document.getElementById('imDesc').value               = '';
    document.getElementById('imDesc').style.height        = '80px';
    form.action = 'add_item.php?category=' + currentCategory;
    resetPhotoZone();
  }

  overlay.classList.add('open');
}

function resetPhotoZone() {
  document.getElementById('imPhotoPreview').src          = '';
  document.getElementById('imPhotoPreview').style.display = 'none';
  document.getElementById('imPhotoPlaceholder').style.display = 'flex';
  document.getElementById('imPhotoLabel').style.display  = 'none';
}

function closeItemModal() {
  document.getElementById('itemModalOverlay').classList.remove('open');
}
document.getElementById('itemModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeItemModal();
});

/* Auto-resize textarea */
document.getElementById('imDesc').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.max(80, this.scrollHeight) + 'px';
});

/* Image preview on select */
document.getElementById('imImage').addEventListener('change', function() {
  if (!this.files || !this.files[0]) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    var preview = document.getElementById('imPhotoPreview');
    preview.src = e.target.result;
    preview.style.display = 'block';
    document.getElementById('imPhotoPlaceholder').style.display = 'none';
    document.getElementById('imPhotoLabel').style.display = 'block';
    document.getElementById('imPhotoLabel').textContent = 'Нове фото';
  };
  reader.readAsDataURL(this.files[0]);
});

/* Inline delete */
function confirmDelete(id) {
  document.getElementById('actions-'+id).style.display = 'none';
  document.getElementById('confirm-'+id).style.display = 'flex';
}
function cancelDelete(id) {
  document.getElementById('confirm-'+id).style.display = 'none';
  document.getElementById('actions-'+id).style.display = 'flex';
}
function doDelete(id) {
  var row = document.getElementById('item-row-'+id);
  row.classList.add('deleting');
  fetch('ajax_delete_item.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({id: id, category: currentCategory})
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    if (data.success) {
      setTimeout(function(){ row.remove(); }, 350);
      if (typeof showAdminToast === 'function') showAdminToast('Товар видалено', 'success');
    } else {
      row.classList.remove('deleting');
      cancelDelete(id);
      if (typeof showAdminToast === 'function') showAdminToast('Помилка видалення', 'error');
    }
  })
  .catch(function(){ row.classList.remove('deleting'); cancelDelete(id); });
}
</script>

<?php include 'includes/layout_bottom.php'; ?>
