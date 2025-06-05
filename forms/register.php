<?php
// pages/register.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../db/db.php';

// Якщо вже залогінені — редірект на головну
if (isset($_SESSION['user'])) {
    header('Location: ../pages/index.php');
    exit();
}

$errors   = [];
$success  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Збір та чистка даних
    $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $login    = trim($_POST['login']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    // Базова валідація
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

    // Перевірка унікальності email і login
    if (empty($errors)) {
        $stmt = $conn->prepare("
            SELECT client_id 
              FROM users 
             WHERE login = ? 
                OR email = ?
            LIMIT 1
        ");
        $stmt->bind_param('ss', $login, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            // можемо уточнити, що саме зайняте
            $stmt->bind_result($existingId);
            $stmt->fetch();
            $errors[] = 'Користувач із таким логіном або email вже існує.';
        }
        $stmt->close();
    }

    // Реєструємо нового користувача
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users 
              (email, login, password, created_at) 
            VALUES 
              (?,     ?,     ?,        NOW())
        ");
        $stmt->bind_param('sss', $email, $login, $hash);
        if ($stmt->execute()) {
            $success = 'Ви успішно зареєстровані! <a href="login.php" class="auth-link">Увійти</a>';
        } else {
            $errors[] = 'Помилка під час реєстрації: ' . $stmt->error;
        }
        $stmt->close();
    }
}
$page = 'register';
include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Реєстрація — Coffee Time</title>
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/register.css">
  <link rel="stylesheet" href="../static/css/footer.css">
</head>
<body>
  <main class="auth-container">
    <h1>Реєстрація</h1>

    <?php if ($errors): ?>
      <div class="auth-message error">
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php elseif ($success): ?>
      <div class="auth-message success">
        <?= $success ?>
      </div>
    <?php endif; ?>

    <form method="post" class="auth-form" novalidate>
      <div class="form-group">
        <label for="email">Електронна пошта</label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
          required
        >
      </div>

      <div class="form-group">
        <label for="login">Логін</label>
        <input 
          type="text" 
          id="login" 
          name="login" 
          minlength="3"
          value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" 
          required
        >
      </div>

      <div class="form-group">
        <label for="password">Пароль</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          minlength="6"
          required
        >
        <small>мінімум 6 символів</small>
      </div>

      <div class="form-group">
        <label for="confirm">Підтвердіть пароль</label>
        <input 
          type="password" 
          id="confirm" 
          name="confirm" 
          minlength="6"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary">Зареєструватися</button>

      <p class="auth-footer">
        Вже маєте акаунт? <a href="login.php" class="auth-link">Увійти</a>
      </p>
    </form>
  </main>

  <?php include '../includes/footer.php'; ?>
</body>
</html>
