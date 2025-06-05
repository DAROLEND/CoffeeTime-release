<?php
// forms/forgot.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
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
        // Перевіряємо чи існує email в базі
        $stmt = $conn->prepare("SELECT client_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Генеруємо токен
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Зберігаємо токен в БД
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $token, $expires);
            $stmt->execute();

            // Відправка листа з посиланням на скидання пароля
            $resetLink = "http://localhost/CoffeeTime-release/forms/reset.php?token=$token";

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'slabitskyia@gmail.com';
                $mail->Password = 'hlwd rzpb zfzp qgtp';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;


                $mail->setFrom('slabitskyia@gmail.com', 'Coffee Time');
                $mail->addAddress($email);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = 'Відновлення пароля — Coffee Time';
                $mail->Body    = "Щоб скинути пароль, перейдіть за посиланням: $resetLink";

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
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Відновлення пароля — Coffee Time</title>
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/forgot.css">
  <link rel="stylesheet" href="../static/css/footer.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>

  <main class="auth-container">
    <div class="auth-card">
      <h2>Відновлення пароля</h2>

      <?php if ($success): ?>
        <p class="auth-success">
          Якщо цей email зареєстрований, ми надішлемо вам листа з інструкціями.
        </p>
        <p class="register-cta">
          Повернутися до <a href="login.php" class="auth-link">Авторизації</a>
        </p>
      <?php else: ?>
        <?php if ($error): ?>
          <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post" class="auth-form" novalidate>
          <div class="input-group">
            <label for="email">Ваша електронна пошта</label>
            <input
              id="email"
              name="email"
              type="email"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required
            >
          </div>

          <button type="submit" class="auth-btn">Надіслати запит</button>

          <p class="register-cta">
            Повернутися до <a href="login.php" class="auth-link">Авторизації</a>
          </p>
        </form>
      <?php endif; ?>
    </div>
  </main>

  <?php include '../includes/footer.php'; ?>
</body>
</html>
