<?php
session_start();
require '../db/db.php';

if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($user = $res->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['admin'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Неправильний пароль.';
        }
    } else {
        $error = 'Користувача не знайдено.';
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Вхід в адмін-панель</title>
    <link rel="stylesheet" href="../static/css/admin-login.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <h2>Вхід адміністратора</h2>
            <?php if ($error): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="username">Логін</label>
                    <input type="text" name="username" id="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn-login">Увійти</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
