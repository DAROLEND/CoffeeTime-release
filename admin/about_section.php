<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/auth_check.php';
require_perm('content');

$conn->query("CREATE TABLE IF NOT EXISTS site_settings (
  `key`   VARCHAR(100) NOT NULL PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$defaults = [
    'about_title'        => 'Місце, де час зупиняється',
    'about_text'         => "Coffee Time — це затишне кафе в серці міста, де ми щодня готуємо свіжі десерти та каву з любов'ю. Ніяких заморожених напівфабрикатів — тільки справжнє та смачне.",
    'about_founded_year' => '2016',
    'about_menu_count'   => '50',
    'about_rating'       => '4.8',
    'about_photo'        => 'static/images/main/about-photo.png',
];
$ins = $conn->prepare("INSERT IGNORE INTO site_settings (`key`, `value`) VALUES (?, ?)");
foreach ($defaults as $k => $v) {
    $ins->bind_param('ss', $k, $v);
    $ins->execute();
}
$ins->close();

$pageTitle  = 'Про нас — головна';
$activePage = 'about_section';

/* ══ POST ══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['about_title', 'about_text', 'about_founded_year', 'about_menu_count', 'about_rating'];
    $upd = $conn->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        $upd->bind_param('ss', $f, $val);
        $upd->execute();
    }
    $upd->close();

    /* Photo upload — cropped (base64) or raw file */
    $uploadDir = __DIR__ . '/../static/images/main/';
    $savedPath = '';
    if (!empty($_POST['about_photo_b64'])) {
        $ext = save_cropped_image($_POST['about_photo_b64'], $uploadDir . 'about-photo.jpg');
        if ($ext) $savedPath = 'static/images/main/about-photo.' . $ext;
    } elseif (!empty($_FILES['about_photo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['about_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed) && $_FILES['about_photo']['size'] <= 4 * 1024 * 1024) {
            $fname = 'about-photo.' . $ext;
            if (move_uploaded_file($_FILES['about_photo']['tmp_name'], $uploadDir . $fname))
                $savedPath = 'static/images/main/' . $fname;
        }
    }
    if ($savedPath) {
        $upd2 = $conn->prepare("INSERT INTO site_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $upd2->bind_param('ss', ...['about_photo', $savedPath]);
        $upd2->execute();
        $upd2->close();
    }

    $_SESSION['admin_flash']      = 'Збережено.';
    $_SESSION['admin_flash_type'] = 'success';
    header('Location: about_section.php');
    exit;
}

$settings = [];
$r = $conn->query("SELECT `key`, `value` FROM site_settings WHERE `key` LIKE 'about_%'");
if ($r) while ($row = $r->fetch_assoc()) $settings[$row['key']] = $row['value'];
$s = fn(string $k) => htmlspecialchars($settings[$k] ?? $defaults[$k] ?? '');

/* Flash from session */
$flash = ''; $flashType = 'success';
if (!empty($_SESSION['admin_flash'])) {
    $flash     = $_SESSION['admin_flash'];
    $flashType = $_SESSION['admin_flash_type'] ?? 'success';
    unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);
}

$yearsOpen = (int)date('Y') - (int)($settings['about_founded_year'] ?? 2016);

include 'includes/layout_top.php';
?>

<?php if ($flash): ?>
  <div class="admin-alert admin-alert--<?= $flashType === 'error' ? 'error' : 'success' ?>">
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<div class="ab-grid">

  <!-- ── Form ── -->
  <div class="panel">
    <div class="panel-head"><h2 class="panel-title">Редагування</h2></div>
    <form method="post" enctype="multipart/form-data" class="ab-form">

      <div class="ab-field">
        <label>Заголовок секції</label>
        <input type="text" name="about_title" value="<?= $s('about_title') ?>" maxlength="120" required>
      </div>

      <div class="ab-field">
        <label>Текст</label>
        <textarea name="about_text" id="aboutText" maxlength="600"><?= $s('about_text') ?></textarea>
        <div class="ab-char-counter"><span id="charCount">0</span> / 600</div>
      </div>

      <div class="ab-row3">
        <div class="ab-field">
          <label>Рік заснування</label>
          <input type="number" name="about_founded_year" value="<?= $s('about_founded_year') ?>" min="1900" max="<?= date('Y') ?>">
          <small>Зараз відображається: <b><?= $yearsOpen ?> р.</b></small>
        </div>
        <div class="ab-field">
          <label>Позицій меню</label>
          <input type="number" name="about_menu_count" value="<?= $s('about_menu_count') ?>" min="1" max="999">
          <small>Показується як «<?= htmlspecialchars($settings['about_menu_count'] ?? '50') ?>+»</small>
        </div>
        <div class="ab-field">
          <label>Google рейтинг</label>
          <input type="number" name="about_rating" value="<?= $s('about_rating') ?>" min="1" max="5" step="0.1">
          <small>Показується як «<?= htmlspecialchars($settings['about_rating'] ?? '4.8') ?>★»</small>
        </div>
      </div>

      <div class="ab-field">
        <label>Фото <small>(залиш порожнім щоб не міняти · JPG, PNG, WEBP · до 4 MB)</small></label>
        <input type="hidden" name="about_photo_b64" id="about_photo_b64">
        <div class="upload-zone">
          <input type="file" name="about_photo" accept="image/jpeg,image/png,image/webp" data-crop-hidden="about_photo_b64">
          <div class="upload-zone__placeholder">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <span>Оберіть або перетягніть фото</span>
          </div>
          <div class="upload-preview" style="display:none"></div>
        </div>
      </div>

      <div style="padding:0 0 4px">
        <button type="submit" class="btn btn-primary">💾 Зберегти зміни</button>
      </div>

    </form>
  </div>

  <!-- ── Live preview ── -->
  <div class="panel ab-preview-panel">
    <div class="panel-head"><h2 class="panel-title">Прев'ю</h2></div>
    <div class="ab-preview">
      <p class="prev-label">Про нас</p>
      <h3 class="prev-title" id="prevTitle"><?= $s('about_title') ?></h3>
      <p class="prev-text"  id="prevText"><?= $s('about_text') ?></p>
      <div class="prev-stats">
        <div class="prev-stat">
          <span class="prev-num" id="prevYears"><?= $yearsOpen ?></span>
          <span class="prev-lbl">років на ринку</span>
        </div>
        <div class="prev-stat">
          <span class="prev-num" id="prevMenu"><?= htmlspecialchars($settings['about_menu_count'] ?? '50') ?>+</span>
          <span class="prev-lbl">позицій меню</span>
        </div>
        <div class="prev-stat">
          <span class="prev-num" id="prevRating"><?= htmlspecialchars($settings['about_rating'] ?? '4.8') ?>★</span>
          <span class="prev-lbl">Google рейтинг</span>
        </div>
      </div>
      <div class="prev-photo">
        <img id="prevPhoto" src="../<?= $s('about_photo') ?>" alt="">
      </div>
    </div>
  </div>

</div>

<style>
.ab-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
  align-items: start;
}
@media (max-width: 900px) { .ab-grid { grid-template-columns: 1fr; } }

.panel { background:#fff; border-radius:14px; border:1px solid #f0e8df; overflow:hidden; }
.panel-head { padding:18px 24px 0; }
.panel-title { font-size:15px; font-weight:700; color:#2c2c2a; margin-bottom:12px; }

.ab-form { padding: 4px 24px 24px; display:flex; flex-direction:column; gap:18px; }

.ab-field label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:6px; }
.ab-field small { display:block; font-size:11px; color:#aaa; margin-top:4px; }
.ab-field input[type="text"],
.ab-field input[type="number"],
.ab-field textarea {
  width:100%; padding:10px 14px; font-size:14px;
  border:1.5px solid #e0d8d0; border-radius:10px;
  outline:none; font-family:inherit; resize:none;
  transition: border-color .2s;
}
.ab-field textarea { min-height:80px; overflow:hidden; line-height:1.6; }
.ab-char-counter { font-size:11px; color:#bbb; text-align:right; margin-top:4px; transition: color .2s; }
.ab-char-counter.near { color:#e67e22; }
.ab-char-counter.full { color:#e53935; }
.ab-field input:focus, .ab-field textarea:focus { border-color:#8B4513; }

.ab-row3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
@media (max-width: 600px) { .ab-row3 { grid-template-columns:1fr; } }

/* Upload */
.upload-zone {
  position:relative; border:2px dashed #e0d8d0; border-radius:12px;
  padding:20px; text-align:center; cursor:pointer; min-height:100px;
  display:flex; align-items:center; justify-content:center;
  transition: border-color .2s, background .2s;
}
.upload-zone:hover { border-color:#8B4513; background:#fdf8f4; }
.upload-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; z-index:2; }
.upload-zone__placeholder svg   { margin:0 auto 6px; }
.upload-zone__placeholder span  { display:block; font-size:13px; color:#888; }
.upload-preview img { max-height:100px; border-radius:8px; object-fit:cover; }

/* Buttons */
.btn { display:inline-block; padding:10px 22px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none; transition:all .2s; font-family:inherit; }
.btn-primary { background:#8B4513; color:#fff; }
.btn-primary:hover { background:#6d3410; }

/* Alert */
.admin-alert { padding:12px 18px; border-radius:10px; font-size:14px; margin-bottom:20px; }
.admin-alert--success { background:#e8f5e9; color:#2e7d32; }
.admin-alert--error   { background:#ffebee; color:#c62828; }

/* Preview */
.ab-preview-panel { position:sticky; top:80px; }
.ab-preview { padding:16px 20px 20px; }
.prev-label { font-size:11px; font-weight:700; color:#8B4513; text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; }
.prev-title { font-size:17px; font-weight:700; color:#2c2c2a; margin-bottom:10px; line-height:1.3; }
.prev-text  { font-size:13px; color:#666; line-height:1.6; margin-bottom:14px; }
.prev-stats { display:flex; gap:12px; margin-bottom:16px; }
.prev-stat  { flex:1; text-align:center; background:#faf5f0; border-radius:10px; padding:10px 6px; }
.prev-num   { display:block; font-size:18px; font-weight:700; color:#8B4513; }
.prev-lbl   { display:block; font-size:10px; color:#aaa; margin-top:2px; }
.prev-photo img { width:100%; border-radius:10px; object-fit:cover; max-height:160px; }
</style>

<script>
/* Auto-resize textarea */
const textarea    = document.getElementById('aboutText');
const charCount   = document.getElementById('charCount');
const charCounter = charCount?.closest('.ab-char-counter');

function autoResize() {
  textarea.style.height = 'auto';
  textarea.style.height = textarea.scrollHeight + 'px';
}
function updateCounter() {
  const len = textarea.value.length;
  charCount.textContent = len;
  charCounter.classList.toggle('near', len >= 480 && len < 580);
  charCounter.classList.toggle('full', len >= 580);
}
textarea.addEventListener('input', () => { autoResize(); updateCounter(); });
autoResize(); updateCounter();

/* Live preview update */
const titleInp  = document.querySelector('[name="about_title"]');
const textInp   = document.querySelector('[name="about_text"]');
const yearInp   = document.querySelector('[name="about_founded_year"]');
const menuInp   = document.querySelector('[name="about_menu_count"]');
const ratingInp = document.querySelector('[name="about_rating"]');
const photoInp  = document.querySelector('[name="about_photo"]');

const pTitle  = document.getElementById('prevTitle');
const pText   = document.getElementById('prevText');
const pYears  = document.getElementById('prevYears');
const pMenu   = document.getElementById('prevMenu');
const pRating = document.getElementById('prevRating');
const pPhoto  = document.getElementById('prevPhoto');

titleInp.addEventListener('input',  () => pTitle.textContent = titleInp.value);
textInp.addEventListener('input',   () => pText.textContent  = textInp.value);
yearInp.addEventListener('input',   () => pYears.textContent  = new Date().getFullYear() - parseInt(yearInp.value || 2016));
menuInp.addEventListener('input',   () => pMenu.textContent   = (menuInp.value || '0') + '+');
ratingInp.addEventListener('input', () => pRating.textContent = (ratingInp.value || '0') + '★');

photoInp.addEventListener('change', function () {
  if (!this.files || !this.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => { pPhoto.src = e.target.result; };
  reader.readAsDataURL(this.files[0]);
  /* Also update upload-zone preview */
  const zone    = this.closest('.upload-zone');
  const preview = zone?.querySelector('.upload-preview');
  if (preview) {
    const r2 = new FileReader();
    r2.onload = e => { preview.innerHTML = '<img src="' + e.target.result + '">'; preview.style.display = 'block'; };
    r2.readAsDataURL(this.files[0]);
  }
});
</script>

<?php include 'includes/layout_bottom.php'; ?>
