<?php
require_once '../includes/session.php';
require_once '../includes/error_handler.php';
require_once '../db/db.php';
require_once '../includes/helpers.php';

if (isset($_SESSION['user'])) {
    header('Location: ../pages/index.php');
    exit();
}

$errors            = [];
$successRegistered = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $login    = trim($_POST['login']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введіть коректну електронну пошту.';
    }
    if (strlen($login) < 3) {
        $errors[] = 'Логін має містити щонайменше 3 символи.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Пароль має містити принаймні 6 символів.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Паролі не співпадають.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            SELECT client_id FROM users
            WHERE login = ? OR email = ? LIMIT 1
        ");
        $stmt->bind_param('ss', $login, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Користувач із таким логіном або email вже існує.';
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (email, login, password, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param('sss', $email, $login, $hash);
        if ($stmt->execute()) {
            $successRegistered = true;
        } else {
            $errors[] = 'Помилка під час реєстрації: ' . $stmt->error;
        }
        $stmt->close();
    }
}

$page         = 'register';
$pageTitle    = 'Реєстрація — Coffee Time';
$customStyles = ['../static/css/auth.css'];
?>
<!DOCTYPE html>
<html lang="uk">
<?php include '../includes/header.php'; ?>
<body>

<div class="auth-page">
  <div class="auth-card">

    <!-- ── Left decorative panel ── -->
    <div class="auth-left">
      <img src="../static/images/main/logo.svg" alt="Coffee Time" class="auth-logo">

      <div class="auth-left-middle">
        <h2 class="auth-left-title">Приєднуйся до Coffee Time</h2>
        <p class="auth-left-sub">Реєстрація займає менше хвилини — і кава вже чекає на тебе.</p>
      </div>

      <div class="auth-features">
        <div class="auth-feature">
          <span class="auth-feature-icon">☕</span>
          <span>Замовляй каву та їжу онлайн</span>
        </div>
        <div class="auth-feature">
          <span class="auth-feature-icon">🧾</span>
          <span>Зберігай історію замовлень</span>
        </div>
        <div class="auth-feature">
          <span class="auth-feature-icon">🍕</span>
          <span>Кава, піца, десерти — все в одному місці</span>
        </div>
      </div>
    </div>

    <!-- ── Right form panel ── -->
    <div class="auth-right" id="authRight">

      <!-- Success overlay -->
      <div class="auth-success" id="authSuccess">
        <svg class="auth-success-icon" viewBox="0 0 72 72" fill="none">
          <circle class="check-circle" cx="36" cy="36" r="32"
            stroke="#4CAF50" stroke-width="3" fill="none"
            stroke-linecap="round"/>
          <path class="check-mark" d="M22 37l10 10 18-20"
            stroke="#4CAF50" stroke-width="3.5"
            stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        </svg>
        <p class="auth-success-text">Реєстрація успішна!</p>
        <p class="auth-success-sub">Переходимо до входу…</p>
      </div>

      <?php if ($errors): ?>
        <div class="auth-error-block">
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <h2 class="auth-form-title">Реєстрація</h2>

      <form method="post" id="registerForm" novalidate>
        <?= csrf_field() ?>

        <!-- Email -->
        <div class="form-field" id="ffEmail">
          <label for="regEmail">Електронна пошта</label>
          <div class="input-wrap">
            <input
              id="regEmail" name="email" type="email"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              autocomplete="email"
              placeholder="example@mail.com"
            >
            <span class="field-check">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M2 7l4 4 6-6" stroke="#4CAF50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
          </div>
          <span class="field-error" id="errEmail">Введіть коректну пошту</span>
        </div>

        <!-- Login -->
        <div class="form-field" id="ffLogin">
          <label for="regLogin">Логін</label>
          <div class="input-wrap">
            <input
              id="regLogin" name="login" type="text"
              value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
              autocomplete="username"
              placeholder="мінімум 3 символи"
            >
            <span class="field-check">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M2 7l4 4 6-6" stroke="#4CAF50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
          </div>
          <span class="field-error" id="errLogin">Мінімум 3 символи, лише літери, цифри, _</span>
        </div>

        <!-- Password -->
        <div class="form-field" id="ffPassword">
          <label for="regPassword">Пароль</label>
          <div class="pw-wrap">
            <input
              id="regPassword" name="password" type="password"
              autocomplete="new-password"
              placeholder="мінімум 6 символів"
            >
            <button type="button" class="eye-toggle" aria-label="Показати пароль" data-target="regPassword">
              <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
          <span class="field-error" id="errPassword">Мінімум 6 символів</span>
          <!-- Strength meter -->
          <div class="pw-strength" id="pwStrength">
            <div class="pw-bar" id="pwBar1"></div>
            <div class="pw-bar" id="pwBar2"></div>
            <div class="pw-bar" id="pwBar3"></div>
            <div class="pw-bar" id="pwBar4"></div>
          </div>
          <div class="pw-strength-label" id="pwLabel"></div>
        </div>

        <!-- Confirm password -->
        <div class="form-field" id="ffConfirm">
          <label for="regConfirm">Підтвердіть пароль</label>
          <div class="pw-wrap">
            <input
              id="regConfirm" name="confirm" type="password"
              autocomplete="new-password"
              placeholder="повторіть пароль"
            >
            <button type="button" class="eye-toggle" aria-label="Показати пароль" data-target="regConfirm">
              <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
          <span class="field-error" id="errConfirm">Паролі не співпадають</span>
        </div>

        <button type="submit" class="auth-submit" id="regSubmit">Зареєструватися</button>

        <p class="auth-switch">
          Вже маєте акаунт? <a href="login.php">Увійти</a>
        </p>

      </form>
    </div><!-- /.auth-right -->

  </div><!-- /.auth-card -->
</div><!-- /.auth-page -->

<?php include '../includes/footer.php'; ?>

<script>
(function () {

  /* ── Eye toggles ── */
  document.querySelectorAll('.eye-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const inp  = document.getElementById(btn.dataset.target);
      const show = btn.querySelector('.eye-show');
      const hide = btn.querySelector('.eye-hide');
      if (inp.type === 'password') {
        inp.type = 'text';
        show.style.display = 'none';
        hide.style.display = 'block';
      } else {
        inp.type = 'password';
        show.style.display = 'block';
        hide.style.display = 'none';
      }
    });
  });

  /* ── Password strength ── */
  const bars  = [
    document.getElementById('pwBar1'),
    document.getElementById('pwBar2'),
    document.getElementById('pwBar3'),
    document.getElementById('pwBar4'),
  ];
  const pwLabel = document.getElementById('pwLabel');
  const levels  = ['','weak','fair','good','strong'];
  const labels  = ['','Слабкий','Середній','Добрий','Надійний'];

  function calcStrength(pw) {
    let score = 0;
    if (pw.length >= 6)  score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw) || /[^A-Za-z0-9]/.test(pw)) score++;
    return Math.min(4, score);
  }

  function updateStrength(pw) {
    const s = pw.length === 0 ? 0 : calcStrength(pw);
    bars.forEach((b, i) => {
      b.className = 'pw-bar';
      if (i < s) b.classList.add(levels[s]);
    });
    pwLabel.textContent  = labels[s] || '';
    pwLabel.className    = 'pw-strength-label' + (s ? ' ' + levels[s] : '');
  }

  /* ── Field refs ── */
  const emailInp   = document.getElementById('regEmail');
  const loginInp   = document.getElementById('regLogin');
  const passInp    = document.getElementById('regPassword');
  const confirmInp = document.getElementById('regConfirm');
  const ffEmail    = document.getElementById('ffEmail');
  const ffLogin    = document.getElementById('ffLogin');
  const ffPassword = document.getElementById('ffPassword');
  const ffConfirm  = document.getElementById('ffConfirm');
  const right      = document.getElementById('authRight');

  function setField(el, valid) {
    el.classList.toggle('valid',   valid);
    el.classList.toggle('invalid', !valid);
  }

  const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const loginRe = /^[A-Za-zА-Яа-яЇїІіЄєҐґ0-9_]{3,}$/;

  emailInp.addEventListener('input', () => {
    setField(ffEmail, emailRe.test(emailInp.value.trim()));
  });
  loginInp.addEventListener('input', () => {
    setField(ffLogin, loginRe.test(loginInp.value.trim()));
  });
  passInp.addEventListener('input', () => {
    updateStrength(passInp.value);
    setField(ffPassword, passInp.value.length >= 6);
    if (confirmInp.value.length > 0) {
      setField(ffConfirm, confirmInp.value === passInp.value);
    }
  });
  confirmInp.addEventListener('input', () => {
    setField(ffConfirm, confirmInp.value === passInp.value && confirmInp.value.length > 0);
  });

  /* ── Submit ── */
  document.getElementById('registerForm').addEventListener('submit', function (e) {
    const v1 = emailRe.test(emailInp.value.trim());
    const v2 = loginRe.test(loginInp.value.trim());
    const v3 = passInp.value.length >= 6;
    const v4 = confirmInp.value === passInp.value && confirmInp.value.length > 0;

    setField(ffEmail,    v1);
    setField(ffLogin,    v2);
    setField(ffPassword, v3);
    setField(ffConfirm,  v4);

    if (!v1 || !v2 || !v3 || !v4) {
      e.preventDefault();
      right.classList.add('shake');
      right.addEventListener('animationend', () => right.classList.remove('shake'), { once: true });
      return;
    }

    document.getElementById('regSubmit').classList.add('loading');
  });

  /* ── Success overlay (server confirmed registration) ── */
  <?php if ($successRegistered): ?>
  const overlay = document.getElementById('authSuccess');
  overlay.classList.add('show');
  setTimeout(() => { window.location.href = 'login.php'; }, 2800);
  <?php endif; ?>

  /* ── Shake on server errors ── */
  <?php if ($errors): ?>
  right.classList.add('shake');
  right.addEventListener('animationend', () => right.classList.remove('shake'), { once: true });
  <?php endif; ?>

})();
</script>
</body>
</html>
