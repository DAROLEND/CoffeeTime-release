<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db/db.php';

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = false;
$email   = '';

if ($token === '') {
    $error = 'Токен не вказано.';
} else {
    $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $email = $row['email'];
        if (strtotime($row['expires_at']) < time()) {
            $error = 'Термін дії посилання вичерпано. Запросіть нове.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm']  ?? '';

            if (strlen($password) < 6) {
                $error = 'Пароль повинен містити щонайменше 6 символів.';
            } elseif ($password !== $confirm) {
                $error = 'Паролі не співпадають.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $upd->bind_param("ss", $hashed, $email);
                $upd->execute();
                $upd->close();

                $del = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                $del->bind_param("s", $token);
                $del->execute();
                $del->close();

                $success = true;
            }
        }
    } else {
        $error = 'Недійсне або вже використане посилання.';
    }
    $stmt->close();
}

$pageTitle    = 'Новий пароль — Coffee Time';
$customStyles = ['../static/css/auth.css'];
?>
<!DOCTYPE html>
<html lang="uk">
<?php include '../includes/header.php'; ?>
<body>

<div class="auth-page">
  <div class="auth-card<?= $error && !$success ? ' js-shake' : '' ?>">

    <!-- ── Left decorative panel ── -->
    <div class="auth-left">
      <img src="../static/images/main/logo.svg" alt="Coffee Time" class="auth-logo">

      <div class="auth-left-middle">
        <h2 class="auth-left-title">Новий пароль</h2>
        <p class="auth-left-sub">Придумайте надійний пароль — і повертайтеся насолоджуватися улюбленим смаком.</p>
      </div>

      <div class="auth-features">
        <div class="auth-feature">
          <?= icon('lock', 20, 'rgba(255,255,255,0.85)', 'auth-feature-icon') ?>
          <span>Мінімум 6 символів</span>
        </div>
        <div class="auth-feature">
          <?= icon('receipt', 20, 'rgba(255,255,255,0.85)', 'auth-feature-icon') ?>
          <span>Збережіть пароль у надійному місці</span>
        </div>
        <div class="auth-feature">
          <?= icon('coffee-cup', 20, 'rgba(255,255,255,0.85)', 'auth-feature-icon') ?>
          <span>Після збереження — одразу в кав'ярню</span>
        </div>
      </div>
    </div>

    <!-- ── Right form panel ── -->
    <div class="auth-right" id="authRight">

      <?php if ($success): ?>
        <div class="auth-success show">
          <svg class="auth-success-icon" viewBox="0 0 72 72" fill="none">
            <circle class="check-circle" cx="36" cy="36" r="33"
              stroke="#4CAF50" stroke-width="3" fill="none"/>
            <path class="check-mark" d="M22 36l10 10 18-18"
              stroke="#4CAF50" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <p class="auth-success-text">Пароль змінено!</p>
          <p class="auth-success-sub">Тепер ви можете увійти з новим паролем.</p>
          <a href="login.php" style="margin-top:8px;font-size:13px;color:#8B4513;font-weight:700;text-decoration:none;">
            → Увійти
          </a>
        </div>

      <?php elseif ($error && $email === ''): ?>
        <!-- Token-level error — no form to show -->
        <p class="auth-form-title">Помилка</p>
        <div class="auth-error-block"><?= htmlspecialchars($error) ?></div>
        <div class="auth-switch">
          <a href="forgot.php">← Запросити нове посилання</a>
        </div>

      <?php else: ?>

        <p class="auth-form-title">Введіть новий пароль</p>

        <?php if ($error): ?>
          <div class="auth-error-block"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" id="resetForm" novalidate>

          <div class="form-field" id="ff-password">
            <label for="password">Новий пароль</label>
            <div class="pw-wrap input-wrap">
              <input id="password" name="password" type="password"
                placeholder="Мінімум 6 символів" required autocomplete="new-password">
              <button type="button" class="eye-toggle" id="eyeBtn1" aria-label="Показати пароль">
                <svg id="eyeIcon1" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
            <div class="pw-strength" id="pwStrength">
              <div class="pw-bar" id="bar1"></div>
              <div class="pw-bar" id="bar2"></div>
              <div class="pw-bar" id="bar3"></div>
              <div class="pw-bar" id="bar4"></div>
            </div>
            <div class="pw-strength-label" id="pwLabel"></div>
            <span class="field-error">Пароль занадто короткий</span>
          </div>

          <div class="form-field" id="ff-confirm">
            <label for="confirm">Підтвердіть пароль</label>
            <div class="pw-wrap input-wrap">
              <input id="confirm" name="confirm" type="password"
                placeholder="Повторіть пароль" required autocomplete="new-password">
              <button type="button" class="eye-toggle" id="eyeBtn2" aria-label="Показати пароль">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
            <span class="field-check">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M2 7l4 4 6-6" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            <span class="field-error">Паролі не співпадають</span>
          </div>

          <button type="submit" class="auth-submit" id="resetBtn">
            Зберегти пароль
          </button>

        </form>

      <?php endif; ?>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
(function () {
  const form      = document.getElementById('resetForm');
  const btn       = document.getElementById('resetBtn');
  if (!form) return;

  const pwInput   = document.getElementById('password');
  const cfInput   = document.getElementById('confirm');
  const ffPw      = document.getElementById('ff-password');
  const ffCf      = document.getElementById('ff-confirm');
  const bars      = [document.getElementById('bar1'), document.getElementById('bar2'),
                     document.getElementById('bar3'), document.getElementById('bar4')];
  const pwLabel   = document.getElementById('pwLabel');

  /* shake on server error */
  const card = document.querySelector('.js-shake');
  if (card) {
    card.classList.remove('js-shake');
    const right = document.getElementById('authRight');
    if (right) { right.classList.add('shake'); setTimeout(() => right.classList.remove('shake'), 500); }
  }

  /* password strength */
  function strength(v) {
    let s = 0;
    if (v.length >= 6)  s++;
    if (v.length >= 10) s++;
    if (/[A-Z]/.test(v) || /[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    return s;
  }
  const lvl = ['', 'weak', 'fair', 'good', 'strong'];
  const lbl = ['', 'Слабкий', 'Непоганий', 'Добрий', 'Надійний'];

  pwInput.addEventListener('input', () => {
    const s = strength(pwInput.value);
    bars.forEach((b, i) => { b.className = 'pw-bar' + (i < s ? ' ' + lvl[s] : ''); });
    pwLabel.textContent  = lbl[s] || '';
    pwLabel.className    = 'pw-strength-label' + (s ? ' ' + lvl[s] : '');
    if (ffPw.classList.contains('invalid') && pwInput.value.length >= 6) {
      ffPw.classList.remove('invalid'); ffPw.classList.add('valid');
    }
    validateConfirm();
  });

  function validateConfirm() {
    if (!cfInput.value) return;
    const ok = cfInput.value === pwInput.value;
    ffCf.classList.toggle('valid',   ok);
    ffCf.classList.toggle('invalid', !ok);
  }
  cfInput.addEventListener('input', validateConfirm);
  cfInput.addEventListener('blur',  validateConfirm);

  /* eye toggles */
  document.getElementById('eyeBtn1').addEventListener('click', () => {
    pwInput.type = pwInput.type === 'password' ? 'text' : 'password';
  });
  document.getElementById('eyeBtn2').addEventListener('click', () => {
    cfInput.type = cfInput.type === 'password' ? 'text' : 'password';
  });

  form.addEventListener('submit', (e) => {
    let ok = true;
    if (pwInput.value.length < 6) {
      ffPw.classList.add('invalid'); ffPw.classList.remove('valid'); ok = false;
    }
    if (cfInput.value !== pwInput.value) {
      ffCf.classList.add('invalid'); ffCf.classList.remove('valid'); ok = false;
    }
    if (!ok) { e.preventDefault(); return; }
    btn.classList.add('loading');
  });
})();
</script>
</body>
</html>
