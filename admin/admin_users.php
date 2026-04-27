<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_super();

$pageTitle  = 'Персонал';
$activePage = 'admin_users';

$flash = ''; $flashType = 'success';

/* ══ POST ══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── CREATE ── */
    if ($action === 'create') {
        $username    = trim($_POST['username']     ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $password    = $_POST['password']          ?? '';
        $perms       = array_keys(array_filter($_POST['perms'] ?? []));

        $err = '';
        if (strlen($username) < 3)   $err = 'Логін мінімум 3 символи.';
        if (strlen($password) < 6)   $err = $err ?: 'Пароль мінімум 6 символів.';

        if (!$err) {
            $chk = $conn->prepare("SELECT id FROM admin_users WHERE username=?");
            $chk->bind_param('s', $username);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) $err = 'Такий логін вже існує.';
            $chk->close();
        }

        if ($err) {
            $_SESSION['admin_flash']      = $err;
            $_SESSION['admin_flash_type'] = 'error';
        } else {
            $hash  = password_hash($password, PASSWORD_DEFAULT);
            $permsJson = json_encode($perms);
            $role  = 'staff';
            $stmt  = $conn->prepare("INSERT INTO admin_users (username, password, role, permissions, display_name) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $username, $hash, $role, $permsJson, $displayName);
            $stmt->execute();
            $stmt->close();
            $_SESSION['admin_flash'] = "Акаунт «{$username}» створено.";
        }
    }

    /* ── MY ACCOUNT (super edits own profile/password) ── */
    if ($action === 'my_account') {
        $displayName = trim($_POST['display_name'] ?? '');
        $newPass     = $_POST['new_password']      ?? '';
        $curPass     = $_POST['current_password']  ?? '';

        /* Verify current password */
        $chk = $conn->prepare("SELECT password FROM admin_users WHERE username=?");
        $chk->bind_param('s', $_SESSION['admin']);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$row || !password_verify($curPass, $row['password'])) {
            $_SESSION['admin_flash']      = 'Невірний поточний пароль.';
            $_SESSION['admin_flash_type'] = 'error';
        } elseif ($newPass && strlen($newPass) < 6) {
            $_SESSION['admin_flash']      = 'Новий пароль мінімум 6 символів.';
            $_SESSION['admin_flash_type'] = 'error';
        } else {
            if ($newPass) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admin_users SET display_name=?, password=? WHERE username=?");
                $stmt->bind_param('sss', $displayName, $hash, $_SESSION['admin']);
            } else {
                $stmt = $conn->prepare("UPDATE admin_users SET display_name=? WHERE username=?");
                $stmt->bind_param('ss', $displayName, $_SESSION['admin']);
            }
            $stmt->execute();
            $stmt->close();
            if ($displayName) $_SESSION['admin_display'] = $displayName;
            $_SESSION['admin_flash'] = 'Акаунт оновлено.';
        }
        header('Location: admin_users.php');
        exit;
    }

    /* ── EDIT PERMISSIONS ── */
    if ($action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $displayName = trim($_POST['display_name'] ?? '');
        $perms       = array_keys(array_filter($_POST['perms'] ?? []));
        $permsJson   = json_encode($perms);
        $newPass     = $_POST['new_password'] ?? '';

        /* Cannot edit super accounts */
        $check = $conn->prepare("SELECT role, username FROM admin_users WHERE id=?");
        $check->bind_param('i', $id);
        $check->execute();
        $target = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$target) {
            $_SESSION['admin_flash'] = 'Акаунт не знайдено.';
            $_SESSION['admin_flash_type'] = 'error';
        } elseif ($target['role'] === 'super' && $target['username'] !== $_SESSION['admin']) {
            $_SESSION['admin_flash'] = 'Не можна редагувати інших super-адмінів.';
            $_SESSION['admin_flash_type'] = 'error';
        } else {
            if ($newPass && strlen($newPass) >= 6) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admin_users SET display_name=?, permissions=?, password=? WHERE id=?");
                $stmt->bind_param('sssi', $displayName, $permsJson, $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE admin_users SET display_name=?, permissions=? WHERE id=?");
                $stmt->bind_param('ssi', $displayName, $permsJson, $id);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['admin_flash'] = 'Збережено.';
        }
    }

    /* ── DELETE ── */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        /* Cannot delete yourself or other supers */
        $check = $conn->prepare("SELECT role, username FROM admin_users WHERE id=?");
        $check->bind_param('i', $id);
        $check->execute();
        $target = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$target) {
            $_SESSION['admin_flash'] = 'Акаунт не знайдено.'; $_SESSION['admin_flash_type'] = 'error';
        } elseif ($target['username'] === $_SESSION['admin']) {
            $_SESSION['admin_flash'] = 'Не можна видалити власний акаунт.'; $_SESSION['admin_flash_type'] = 'error';
        } elseif ($target['role'] === 'super') {
            $_SESSION['admin_flash'] = 'Не можна видалити super-адміна.'; $_SESSION['admin_flash_type'] = 'error';
        } else {
            $del = $conn->prepare("DELETE FROM admin_users WHERE id=?");
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            $_SESSION['admin_flash'] = "Акаунт «{$target['username']}» видалено.";
        }
    }

    header('Location: admin_users.php');
    exit;
}

/* Flash */
if (!empty($_SESSION['admin_flash'])) {
    $flash     = $_SESSION['admin_flash'];
    $flashType = $_SESSION['admin_flash_type'] ?? 'success';
    unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);
}

/* Load users */
$users = [];
$r = $conn->query("SELECT id, username, display_name, role, permissions FROM admin_users ORDER BY role='super' DESC, id ASC");
if ($r) while ($row = $r->fetch_assoc()) {
    $row['perms_arr'] = json_decode($row['permissions'] ?? '[]', true) ?: [];
    $users[] = $row;
}

$allPerms = all_perms();

include 'includes/layout_top.php';
?>

<?php if ($flash): ?>
  <div class="admin-alert admin-alert--<?= $flashType === 'error' ? 'error' : 'success' ?>">
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<!-- ══ MY ACCOUNT ══ -->
<div class="panel" style="margin-bottom:28px">
  <div class="panel-head"><h2 class="panel-title">Мій акаунт</h2></div>
  <form method="post" class="au-form">
    <input type="hidden" name="action" value="my_account">
    <div class="au-row2">
      <div class="au-field">
        <label>Логін</label>
        <input type="text" value="<?= htmlspecialchars($_SESSION['admin']) ?>" disabled style="background:#f5f5f5;color:#aaa">
      </div>
      <div class="au-field">
        <label>Відображуване імʼя</label>
        <input type="text" name="display_name" value="<?= htmlspecialchars($_SESSION['admin_display'] ?? '') ?>" placeholder="Як вас показувати в адмінці">
      </div>
    </div>
    <div class="au-row2">
      <div class="au-field">
        <label>Поточний пароль <span style="color:#e53935">*</span></label>
        <div class="pw-wrap">
          <input type="password" name="current_password" id="curPass" required placeholder="введіть поточний пароль">
          <button type="button" class="eye-btn" data-target="curPass">👁</button>
        </div>
      </div>
      <div class="au-field">
        <label>Новий пароль <small>(залиш порожнім щоб не міняти)</small></label>
        <div class="pw-wrap">
          <input type="password" name="new_password" id="newPass" placeholder="мінімум 6 символів" minlength="6">
          <button type="button" class="eye-btn" data-target="newPass">👁</button>
        </div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">💾 Зберегти</button>
  </form>
</div>

<!-- ══ CREATE FORM ══ -->
<div class="panel" style="margin-bottom:28px">
  <div class="panel-head"><h2 class="panel-title">Додати акаунт персоналу</h2></div>
  <form method="post" class="au-form">
    <input type="hidden" name="action" value="create">

    <div class="au-row2">
      <div class="au-field">
        <label>Логін <span style="color:#e53935">*</span></label>
        <input type="text" name="username" placeholder="login123" minlength="3" required>
      </div>
      <div class="au-field">
        <label>Відображуване імʼя</label>
        <input type="text" name="display_name" placeholder="Марія К.">
      </div>
    </div>

    <div class="au-row2">
      <div class="au-field">
        <label>Пароль <span style="color:#e53935">*</span></label>
        <input type="password" name="password" placeholder="мінімум 6 символів" minlength="6" required>
      </div>
    </div>

    <div class="au-field">
      <label>Права доступу</label>
      <div class="perm-grid">
        <?php foreach ($allPerms as $key => $label): ?>
          <label class="perm-check">
            <input type="checkbox" name="perms[<?= $key ?>]" value="1">
            <span><?= htmlspecialchars($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">+ Створити акаунт</button>
  </form>
</div>

<!-- ══ USERS LIST ══ -->
<div class="panel">
  <div class="panel-head"><h2 class="panel-title">Акаунти (<?= count($users) ?>)</h2></div>
  <div class="au-list">
    <?php foreach ($users as $u): ?>
      <?php
        $isSelf   = $u['username'] === $_SESSION['admin'];
        $isSuper  = $u['role'] === 'super';
        $initial  = mb_strtoupper(mb_substr($u['display_name'] ?: $u['username'], 0, 1, 'UTF-8'), 'UTF-8');
      ?>
      <div class="au-row">
        <div class="au-avatar <?= $isSuper ? 'au-avatar--super' : '' ?>"><?= htmlspecialchars($initial) ?></div>

        <div class="au-info">
          <div class="au-name">
            <?= htmlspecialchars($u['display_name'] ?: $u['username']) ?>
            <?php if ($u['display_name']): ?>
              <span class="au-login">@<?= htmlspecialchars($u['username']) ?></span>
            <?php endif; ?>
            <?= $isSelf ? '<span class="au-badge au-badge--you">Це ви</span>' : '' ?>
            <span class="au-badge <?= $isSuper ? 'au-badge--super' : 'au-badge--staff' ?>">
              <?= $isSuper ? 'Super' : 'Staff' ?>
            </span>
          </div>
          <?php if (!$isSuper): ?>
            <div class="au-perms">
              <?php if (empty($u['perms_arr'])): ?>
                <span class="au-perm-none">Немає прав</span>
              <?php else: ?>
                <?php foreach ($u['perms_arr'] as $p): ?>
                  <span class="au-perm-tag"><?= htmlspecialchars($allPerms[$p] ?? $p) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="au-perms"><span class="au-perm-tag au-perm-tag--all">Повний доступ</span></div>
          <?php endif; ?>
        </div>

        <div class="au-actions">
          <?php if (!$isSuper): ?>
            <button class="btn btn-sm btn-outline"
              onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">✏️ Редагувати</button>
            <form method="post" onsubmit="return confirm('Видалити акаунт «<?= htmlspecialchars($u['username']) ?>»?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">🗑</button>
            </form>
          <?php elseif ($isSelf): ?>
            <span style="font-size:12px;color:#bbb">Ваш акаунт</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══ EDIT MODAL ══ -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box" style="max-width:520px">
    <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    <h3 style="font-size:16px;font-weight:700;color:#2c2c2a;margin-bottom:20px">Редагувати акаунт</h3>
    <form method="post" class="au-form">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editUserId">

      <div class="au-row2">
        <div class="au-field">
          <label>Логін</label>
          <input type="text" id="editUsername" disabled style="background:#f5f5f5;color:#aaa">
        </div>
        <div class="au-field">
          <label>Відображуване імʼя</label>
          <input type="text" name="display_name" id="editDisplayName">
        </div>
      </div>

      <div class="au-field">
        <label>Новий пароль <small>(залиш порожнім щоб не міняти)</small></label>
        <input type="password" name="new_password" placeholder="мінімум 6 символів" minlength="6">
      </div>

      <div class="au-field">
        <label>Права доступу</label>
        <div class="perm-grid" id="editPermGrid">
          <?php foreach ($allPerms as $key => $label): ?>
            <label class="perm-check">
              <input type="checkbox" name="perms[<?= $key ?>]" value="1" data-perm="<?= $key ?>">
              <span><?= htmlspecialchars($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:4px">
        <button type="submit" class="btn btn-primary">Зберегти</button>
        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Скасувати</button>
      </div>
    </form>
  </div>
</div>

<style>
.panel { background:#fff; border-radius:14px; border:1px solid #f0e8df; overflow:hidden; }
.panel-head { padding:18px 24px 0; }
.panel-title { font-size:15px; font-weight:700; color:#2c2c2a; margin-bottom:14px; }

.au-form { padding: 4px 24px 24px; display:flex; flex-direction:column; gap:16px; }
.au-row2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:600px) { .au-row2 { grid-template-columns:1fr; } }
.au-field label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:6px; }
.au-field input[type="text"],
.au-field input[type="password"] {
  width:100%; padding:10px 14px; font-size:14px;
  border:1.5px solid #e0d8d0; border-radius:10px;
  outline:none; font-family:inherit; transition:border-color .2s;
}
.au-field input:focus { border-color:#8B4513; }

/* Permissions grid */
.perm-grid { display:flex; flex-direction:column; gap:8px; margin-top:2px; }
.perm-check {
  display:flex; align-items:center; gap:10px;
  padding:10px 14px; border-radius:10px;
  border:1.5px solid #e0d8d0; cursor:pointer;
  transition:border-color .15s, background .15s;
  user-select:none;
}
.perm-check:hover { border-color:#8B4513; background:#fdf8f4; }
.perm-check input[type="checkbox"] { width:16px; height:16px; accent-color:#8B4513; flex-shrink:0; }
.perm-check span { font-size:13px; color:#444; }

/* Users list */
.au-list { padding: 0 24px 20px; }
.au-row {
  display:flex; align-items:center; gap:16px;
  padding:14px 0; border-bottom:1px solid #f5ede6;
}
.au-row:last-child { border-bottom:none; }

.au-avatar {
  width:40px; height:40px; border-radius:50%;
  background:linear-gradient(135deg,#c2855a,#e8b88a);
  color:#fff; font-size:16px; font-weight:700;
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.au-avatar--super { background:linear-gradient(135deg,#8B4513,#d4a96a); }

.au-info { flex:1; min-width:0; }
.au-name { font-size:14px; font-weight:700; color:#2c2c2a; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.au-login { font-size:12px; color:#aaa; font-weight:400; }

.au-badge { font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; }
.au-badge--you   { background:#fff8e1; color:#f57f17; }
.au-badge--super { background:#fff3e0; color:#e65100; }
.au-badge--staff { background:#f3e5f5; color:#6a1b9a; }

.au-perms { display:flex; flex-wrap:wrap; gap:4px; margin-top:5px; }
.au-perm-tag {
  font-size:11px; padding:2px 8px; border-radius:10px;
  background:#f0e8df; color:#8B4513; white-space:nowrap;
}
.au-perm-tag--all { background:#e8f5e9; color:#2e7d32; }
.au-perm-none { font-size:12px; color:#bbb; }

.au-actions { display:flex; gap:8px; flex-shrink:0; align-items:center; }

/* Password wrap */
.pw-wrap { position:relative; }
.pw-wrap input { width:100%; padding:10px 40px 10px 14px; font-size:14px; border:1.5px solid #e0d8d0; border-radius:10px; outline:none; font-family:inherit; transition:border-color .2s; }
.pw-wrap input:focus { border-color:#8B4513; }
.eye-btn { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:15px; opacity:.5; line-height:1; padding:2px; }
.eye-btn:hover { opacity:1; }

/* Buttons */
.btn { display:inline-block; padding:10px 20px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none; transition:all .2s; font-family:inherit; }
.btn-primary { background:#8B4513; color:#fff; }
.btn-primary:hover { background:#6d3410; }
.btn-outline { background:#fff; border:1.5px solid #e0d8d0; color:#555; }
.btn-outline:hover { border-color:#8B4513; color:#8B4513; }
.btn-danger  { background:#ffebee; color:#c62828; border:none; }
.btn-danger:hover { background:#ffcdd2; }
.btn-sm { padding:6px 12px; font-size:12px; }

/* Modal */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:500; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:28px; position:relative; max-height:90vh; overflow-y:auto; }
.modal-close { position:absolute; top:14px; right:14px; background:none; border:none; font-size:18px; cursor:pointer; color:#aaa; }
.modal-close:hover { color:#333; }

/* Alert */
.admin-alert { padding:12px 18px; border-radius:10px; font-size:14px; margin-bottom:20px; }
.admin-alert--success { background:#e8f5e9; color:#2e7d32; }
.admin-alert--error   { background:#ffebee; color:#c62828; }
</style>

<script>
/* Eye toggle for password fields */
document.querySelectorAll('.eye-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var inp = document.getElementById(this.dataset.target);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    this.style.opacity = inp.type === 'text' ? '1' : '.5';
  });
});

function openEditModal(u) {
  document.getElementById('editUserId').value      = u.id;
  document.getElementById('editUsername').value    = u.username;
  document.getElementById('editDisplayName').value = u.display_name || '';

  document.querySelectorAll('#editPermGrid input[data-perm]').forEach(function(cb) {
    cb.checked = (u.perms_arr || []).indexOf(cb.dataset.perm) !== -1;
  });

  openModal('editModal');
}
</script>

<?php include 'includes/layout_bottom.php'; ?>
