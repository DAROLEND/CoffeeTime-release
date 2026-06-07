<?php
require_once '../includes/session.php';
require_once '../includes/error_handler.php';
require_once '../db/db.php';
require_once '../includes/helpers.php';

/* If already logged in as admin — go straight to admin panel */
if (isset($_SESSION['admin'])) {
    header('Location: ../admin/dashboard.php');
    exit;
}
/* If logged in as regular user AND not coming from admin context — go to homepage */
$fromAdmin = str_contains($_SERVER['HTTP_REFERER'] ?? '', '/admin/');
if (isset($_SESSION['user']) && !$fromAdmin) {
    header('Location: ../pages/index.php');
    exit;
}

$error = '';

$ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$maxAttempts = 5;
$lockMinutes = 15;

// Clean up old attempts
$conn->query("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL {$lockMinutes} MINUTE");

// Count recent attempts from this IP
$ar = $conn->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE ip=? AND attempted_at > NOW() - INTERVAL {$lockMinutes} MINUTE");
$ar->bind_param('s', $ip);
$ar->execute();
$attempts = (int)$ar->get_result()->fetch_assoc()['c'];
$ar->close();

$isLocked = $attempts >= $maxAttempts;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $emailOrLogin = trim($_POST['email']    ?? '');
    $password     = trim($_POST['password'] ?? '');
    $remember     = isset($_POST['remember']);

    if ($isLocked) {
        $error = "Забагато невдалих спроб. Спробуйте через {$lockMinutes} хвилин.";
    }

    elseif ($emailOrLogin === '' || $password === '') {
        $error = 'Будь ласка, заповніть усі поля!';
    } else {
        $stmt = $conn->prepare("
            SELECT client_id, login, email, client_name, client_surname, client_PhoneNumber, password
            FROM users
            WHERE email = ? OR login = ?
        ");
        $stmt->bind_param('ss', $emailOrLogin, $emailOrLogin);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result(
                $client_id, $login, $email,
                $client_name, $client_surname, $client_PhoneNumber, $hashedPassword
            );
            $stmt->fetch();

            if (password_verify($password, $hashedPassword)) {
                session_regenerate_id(true);

                /* Check if this user is also an admin (by login == admin username) */
                $as = $conn->prepare("SELECT username, role, permissions FROM admin_users WHERE username = ? LIMIT 1");
                if ($as) {
                    $as->bind_param('s', $login);
                    $as->execute();
                    $adminRow = $as->get_result()->fetch_assoc();
                    $as->close();
                }

                if ($adminRow) {
                    $_SESSION['admin']       = $adminRow['username'];
                    $_SESSION['admin_role']  = $adminRow['role'];
                    $_SESSION['admin_perms'] = json_decode($adminRow['permissions'] ?? '[]', true) ?: [];
                    header('Location: ../admin/dashboard.php');
                    exit;
                }

                $_SESSION['user'] = compact(
                    'client_id','login','email',
                    'client_name','client_surname','client_PhoneNumber'
                );
                if ($remember) {
                    setcookie('remember_me', $emailOrLogin, time() + 7 * 24 * 3600, '/');
                }

                $redirectTo = $_SESSION['redirect_after_login'] ?? '../pages/index.php';
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirectTo);
                exit;
            } else {
                $error = 'Невірний пароль.';
                $la = $conn->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
                $la->bind_param('s', $ip); $la->execute(); $la->close();
            }
        } else {
            /* No regular user — check admin_users directly (admin-only account) */
            $stmt->close();
            $as = $conn->prepare("SELECT id, username, password, role, permissions FROM admin_users WHERE username = ? LIMIT 1");
            if (!$as) { $error = 'Помилка бази даних.'; goto end_login; }
            $as->bind_param('s', $emailOrLogin);
            $as->execute();
            $adminRow = $as->get_result()->fetch_assoc();
            $as->close();

            if ($adminRow && password_verify($password, $adminRow['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin']       = $adminRow['username'];
                $_SESSION['admin_role']  = $adminRow['role'];
                $_SESSION['admin_perms'] = json_decode($adminRow['permissions'] ?? '[]', true) ?: [];
                header('Location: ../admin/dashboard.php');
                exit;
            }

            $error = 'Користувача не знайдено.';
            $la = $conn->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
            $la->bind_param('s', $ip); $la->execute(); $la->close();
            $conn->close();
            goto end_login;
        }
        $stmt->close();
        $conn->close();
        end_login:
    }
}

$page         = 'login';
$pageTitle    = 'Авторизація — Coffee Time';
$customStyles = ['../static/css/auth.css'];
?>
<!DOCTYPE html>
<html lang="uk">
<?php include '../includes/header.php'; ?>
<body>

<div class="auth-page">
  <div class="auth-card<?= $error ? ' js-shake' : '' ?>">

    <!-- ── Left decorative panel ── -->
    <div class="auth-left">
      <img src="../static/images/main/logo.svg" alt="Coffee Time" class="auth-logo">

      <div class="auth-left-middle">
        <h2 class="auth-left-title">З поверненням до Coffee Time</h2>
        <p class="auth-left-sub">Увійдіть, щоб зручно оформлювати замовлення та насолоджуватися улюбленим смаком.</p>
      </div>

      <div class="auth-features">
        <div class="auth-feature">
          <?= icon('coffee-cup', 20, 'rgba(255,255,255,0.85)', 'auth-feature-icon') ?>
          <span>Швидке замовлення в пару кліків</span>
        </div>
        <div class="auth-feature">
          <?= icon('receipt', 20, 'rgba(255,255,255,0.85)', 'auth-feature-icon') ?>
          <span>Історія ваших замовлень</span>
        </div>
        <div class="auth-feature">
          <?= icon('pizza', 20, 'rgba(255,255,255,0.85)', 'auth-feature-icon') ?>
          <span>Кава, піца, десерти — все в одному місці</span>
        </div>
      </div>
    </div>

    <!-- ── Right form panel ── -->
    <div class="auth-right" id="authRight">

      <?php if ($isLocked): ?>
        <div class="auth-error-block">
          Забагато невдалих спроб. Спробуйте через <?= $lockMinutes ?> хвилин.
        </div>
      <?php elseif ($error): ?>
        <div class="auth-error-block"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <h2 class="auth-form-title">Вхід</h2>

      <form method="post" id="loginForm" novalidate>
        <?= csrf_field() ?>

        <!-- Email / Login -->
        <div class="form-field" id="ffEmail">
          <label for="loginEmail">Електронна пошта або логін</label>
          <div class="input-wrap">
            <input
              id="loginEmail" name="email" type="text"
              value="<?= htmlspecialchars($_COOKIE['remember_me'] ?? '') ?>"
              autocomplete="username"
              placeholder="example@mail.com"
            >
            <span class="field-check">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M2 7l4 4 6-6" stroke="#4CAF50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
          </div>
          <span class="field-error">Заповніть це поле</span>
        </div>

        <!-- Password -->
        <div class="form-field" id="ffPassword">
          <label for="loginPassword">Пароль</label>
          <div class="pw-wrap">
            <input
              id="loginPassword" name="password" type="password"
              autocomplete="current-password"
              placeholder="••••••••"
            >
            <button type="button" class="eye-toggle" aria-label="Показати пароль" data-target="loginPassword">
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
          <span class="field-error">Введіть пароль</span>
        </div>

        <!-- Controls row -->
        <div class="auth-controls">
          <label class="cb-wrap">
            <input type="checkbox" name="remember" <?= isset($_COOKIE['remember_me']) ? 'checked' : '' ?>>
            <span class="cb-box">
              <svg class="cb-check" width="10" height="8" viewBox="0 0 10 8" fill="none">
                <path d="M1 4l3 3 5-6" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            Запам'ятати мене
          </label>
          <a href="forgot.php" class="forgot-link">Забули пароль?</a>
        </div>

        <button type="submit" class="auth-submit" id="loginSubmit">Увійти</button>

        <p class="auth-switch">
          Ще не маєте акаунту? <a href="register.php">Зареєструватися</a>
        </p>

      </form>
    </div><!-- /.auth-right -->

  </div><!-- /.auth-card -->
</div><!-- /.auth-page -->

<?php include '../includes/footer.php'; ?>

<script>
(function () {
  const right = document.getElementById('authRight');
  <?php if ($error || $isLocked): ?>
  right.classList.add('shake');
  right.addEventListener('animationend', () => right.classList.remove('shake'), { once: true });
  <?php endif; ?>

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

  function validate(fieldEl, inputEl, isValid) {
    fieldEl.classList.toggle('valid',   isValid);
    fieldEl.classList.toggle('invalid', !isValid);
  }

  const emailInp = document.getElementById('loginEmail');
  const passInp  = document.getElementById('loginPassword');
  const ffEmail  = document.getElementById('ffEmail');
  const ffPass   = document.getElementById('ffPassword');

  emailInp.addEventListener('input', () => {
    validate(ffEmail, emailInp, emailInp.value.trim().length >= 3);
  });
  passInp.addEventListener('input', () => {
    validate(ffPass, passInp, passInp.value.length >= 1);
  });

  document.getElementById('loginForm').addEventListener('submit', function (e) {
    const btn = document.getElementById('loginSubmit');
    const email = emailInp.value.trim();
    const pass  = passInp.value;
    let valid = true;

    if (email.length < 3) { ffEmail.classList.add('invalid'); valid = false; }
    if (pass.length < 1)  { ffPass.classList.add('invalid');  valid = false; }

    if (!valid) {
      e.preventDefault();
      right.classList.add('shake');
      right.addEventListener('animationend', () => right.classList.remove('shake'), { once: true });
      return;
    }

    btn.classList.add('loading');
  });
})();
</script>
</body>
</html>
