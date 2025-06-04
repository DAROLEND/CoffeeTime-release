<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../db/db.php';

if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
    header("Location: ../forms/login.php");
    exit();
}

$user = $_SESSION['user'];
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$currentPassword || !$newPassword || !$confirmPassword) {
        $errorMessage = "❌ Заповніть усі поля.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = "❌ Нові паролі не збігаються.";
    } else {
        // Отримання хеш паролю з БД
        $stmt = $conn->prepare("SELECT password FROM users WHERE client_id = ?");
        $stmt->bind_param("i", $user['client_id']);
        $stmt->execute();
        $stmt->bind_result($hashedPassword);
        if ($stmt->fetch()) {
            if (password_verify($currentPassword, $hashedPassword)) {
                $stmt->close();

                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE client_id = ?");
                $stmt->bind_param("si", $newHashedPassword, $user['client_id']);
                if ($stmt->execute()) {
                    $successMessage = "✅ Пароль успішно змінено.";
                } else {
                    $errorMessage = "❌ Помилка при оновленні паролю: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMessage = "❌ Неправильний поточний пароль.";
            }
        } else {
            $errorMessage = "❌ Користувача не знайдено.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <title>Зміна паролю — Coffee Time</title>
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/profile.css">
  <link rel="stylesheet" href="../static/css/footer.css">
  <link rel="stylesheet" href="../static/css/change_password.css">
</head>
<body>

  <?php 
    $page = 'change_password';
    include '../includes/header.php'; 
  ?>

  <main class="profile">
    <h1>Зміна паролю</h1>

    <?php if ($successMessage): ?>
      <div class="notification success"><?= htmlspecialchars($successMessage) ?></div>
    <?php elseif ($errorMessage): ?>
      <div class="notification error"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <form method="post" class="profile-form">
      <label for="current_password">Поточний пароль:</label>
      <input type="password" id="current_password" name="current_password" required>

      <label for="new_password">Новий пароль:</label>
      <input type="password" id="new_password" name="new_password" required>

      <label for="confirm_password">Підтвердження нового паролю:</label>
      <input type="password" id="confirm_password" name="confirm_password" required>

     <div class="form-actions">
        <button type="submit" class="save-btn">Змінити пароль</button>
        <a href="../pages/profile.php" class="logout-btn">Назад до профілю</a>
        </div>

        <div class="forgot-link">
        <a href="../forms/forgot.php"> Забули пароль?</a>
    </div>

    </form>
  </main>

  <?php include '../includes/footer.php'; ?>
</body>
</html>
