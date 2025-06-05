<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../db/db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if ($token) {
    $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (strtotime($row['expires_at']) < time()) {
            $error = 'Термін дії токена вичерпано.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm'] ?? '';
            if ($password !== $confirm) {
                $error = 'Паролі не співпадають.';
            } elseif (strlen($password) < 6) {
                $error = 'Пароль повинен містити щонайменше 6 символів.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->bind_param("ss", $hashed, $row['email']);
                $stmt->execute();

                $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();

                $success = true;
            }
        }
    } else {
        $error = 'Недійсний токен.';
    }
} else {
    $error = 'Токен не вказано.';
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <title>Скидання пароля — Coffee Time</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/reset.css">
</head>
<body>
  <div class="reset-container">
    <h1>Скидання пароля</h1>

    <?php if ($success): ?>
      <p class="success">Пароль успішно змінено.<br><a href="../pages/index.php">→ Повернутися на головну</a></p>
    <?php elseif ($error): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (!$success && empty($error)): ?>
      <form method="post">
        <input type="password" name="password" placeholder="Новий пароль" required>
        <input type="password" name="confirm" placeholder="Підтвердіть пароль" required>
        <button type="submit">Зберегти</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
