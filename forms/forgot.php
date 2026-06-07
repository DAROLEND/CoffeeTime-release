<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Будь ласка, введіть вашу електронну пошту.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Неправильний формат електронної пошти.';
    } else {
        $stmt = $conn->prepare("SELECT client_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $token, $expires);
            $stmt->execute();

            $baseUrl   = rtrim(getenv('APP_URL') ?: 'http://localhost/CoffeeTime-release', '/');
            $resetLink = $baseUrl . '/forms/reset.php?token=' . urlencode($token);
            $fromName  = htmlspecialchars(getenv('MAIL_FROM_NAME') ?: 'Coffee Time');

            $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="uk">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#faf7f2;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f2;padding:32px 16px;">
  <tr><td align="center">
    <table width="100%" style="max-width:520px;background:#fff;border-radius:16px;border:1px solid #f0e8df;overflow:hidden;">
      <tr><td style="background:#FFC107;padding:24px 32px;text-align:center;">
        <p style="margin:0;font-size:28px;">☕</p>
        <p style="margin:6px 0 0;font-size:20px;font-weight:700;color:#5a2d00;">$fromName</p>
      </td></tr>
      <tr><td style="padding:32px;">
        <p style="margin:0 0 8px;font-size:22px;font-weight:700;color:#2c1810;">Відновлення пароля</p>
        <p style="margin:0 0 24px;font-size:15px;color:#666;line-height:1.6;">
          Ми отримали запит на скидання пароля для вашого акаунту.<br>
          Натисніть кнопку нижче — посилання дійсне протягом <strong>1 години</strong>.
        </p>
        <table cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
          <tr><td style="background:#FFC107;border-radius:12px;padding:0;">
            <a href="$resetLink"
               style="display:inline-block;padding:13px 36px;font-size:15px;font-weight:700;
                      color:#5a2d00;text-decoration:none;white-space:nowrap;">
              Змінити пароль
            </a>
          </td></tr>
        </table>
        <p style="margin:0;font-size:12px;color:#aaa;text-align:center;line-height:1.6;">
          Якщо ви не надсилали цей запит — просто ігноруйте цей лист.<br>
          Ваш пароль залишиться незмінним.
        </p>
      </td></tr>
      <tr><td style="padding:16px 32px;border-top:1px solid #f0e8df;text-align:center;">
        <p style="margin:0;font-size:12px;color:#bbb;">Маєте питання? Ми завжди поруч ☕</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = getenv('MAIL_HOST')     ?: 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('MAIL_USERNAME') ?: '';
                $mail->Password   = getenv('MAIL_PASSWORD') ?: '';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int)(getenv('MAIL_PORT') ?: 587);
                $mail->setFrom(
                    getenv('MAIL_FROM') ?: getenv('MAIL_USERNAME') ?: '',
                    getenv('MAIL_FROM_NAME') ?: 'Coffee Time'
                );
                $mail->addAddress($email);
                $mail->CharSet  = 'UTF-8';
                $mail->Subject  = 'Відновлення пароля — Coffee Time';
                $mail->isHTML(true);
                $mail->Body     = $htmlBody;
                $mail->AltBody  = "Щоб скинути пароль, перейдіть за посиланням: $resetLink\n\nПосилання дійсне 1 годину.";
                $mail->send();
                $success = true;
            } catch (Exception $e) {
                $error = 'Не вдалося надіслати листа. Спробуйте пізніше.';
            }
        } else {
            $success = true;
        }
    }
}

$pageTitle    = 'Відновлення пароля — Coffee Time';
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
        <h2 class="auth-left-title">Забули пароль?</h2>
        <p class="auth-left-sub">Не хвилюйтеся — введіть вашу пошту, і ми надішлемо посилання для відновлення доступу.</p>
      </div>

      <div class="auth-features">
        <div class="auth-feature">
          <?= icon('clock', 20, 'rgba(255,255,255,0.85)', 'auth-feature-icon') ?>
          <span>Лист надійде протягом хвилини</span>
        </div>
        <div class="auth-feature">
          <?= icon('lock', 20, 'rgba(255,255,255,0.85)', 'auth-feature-icon') ?>
          <span>Посилання дійсне 1 годину</span>
        </div>
        <div class="auth-feature">
          <?= icon('coffee-cup', 20, 'rgba(255,255,255,0.85)', 'auth-feature-icon') ?>
          <span>Після входу — замовляйте улюблений смак</span>
        </div>
      </div>
    </div>

    <!-- ── Right form panel ── -->
    <div class="auth-right" id="authRight">

      <?php if ($success): ?>
        <!-- success overlay -->
        <div class="auth-success show">
          <svg class="auth-success-icon" viewBox="0 0 72 72" fill="none">
            <circle class="check-circle" cx="36" cy="36" r="33"
              stroke="#4CAF50" stroke-width="3" fill="none"/>
            <path class="check-mark" d="M22 36l10 10 18-18"
              stroke="#4CAF50" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <p class="auth-success-text">Лист надіслано!</p>
          <p class="auth-success-sub">Перевірте вашу пошту та перейдіть за посиланням.</p>
          <a href="login.php" style="margin-top:8px;font-size:13px;color:#8B4513;font-weight:700;text-decoration:none;">
            ← Повернутися до входу
          </a>
        </div>

      <?php else: ?>

        <p class="auth-form-title">Відновлення пароля</p>

        <?php if ($error): ?>
          <div class="auth-error-block"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" id="forgotForm" novalidate>

          <div class="form-field" id="ff-email">
            <label for="email">Електронна пошта</label>
            <div class="input-wrap">
              <input
                id="email"
                name="email"
                type="email"
                placeholder="your@email.com"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                autocomplete="email"
                required
              >
              <span class="field-check">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M2 7l4 4 6-6" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
            </div>
            <span class="field-error">Введіть коректну електронну пошту</span>
          </div>

          <button type="submit" class="auth-submit" id="forgotBtn">
            Надіслати посилання
          </button>

        </form>

        <div class="auth-switch">
          Згадали пароль? <a href="login.php">Увійти</a>
        </div>

      <?php endif; ?>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
(function () {
  const form    = document.getElementById('forgotForm');
  const btn     = document.getElementById('forgotBtn');
  const ffEmail = document.getElementById('ff-email');
  const input   = document.getElementById('email');
  if (!form) return;

  /* shake on server error */
  const card = document.querySelector('.js-shake');
  if (card) {
    card.classList.remove('js-shake');
    const right = document.getElementById('authRight');
    if (right) { right.classList.add('shake'); setTimeout(() => right.classList.remove('shake'), 500); }
  }

  /* inline validation */
  function validateEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

  input.addEventListener('blur', () => {
    if (input.value === '') return;
    ffEmail.classList.toggle('valid',   validateEmail(input.value));
    ffEmail.classList.toggle('invalid', !validateEmail(input.value));
  });
  input.addEventListener('input', () => {
    if (ffEmail.classList.contains('invalid') && validateEmail(input.value)) {
      ffEmail.classList.remove('invalid');
      ffEmail.classList.add('valid');
    }
  });

  form.addEventListener('submit', (e) => {
    if (!validateEmail(input.value)) {
      e.preventDefault();
      ffEmail.classList.add('invalid');
      ffEmail.classList.remove('valid');
      input.focus();
      return;
    }
    btn.classList.add('loading');
  });
})();
</script>
</body>
</html>
