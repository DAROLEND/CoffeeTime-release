<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../forms/login.php');
    exit;
}

$user = $_SESSION['user'];
$allowed = ['indoor', 'terrace'];
$location = $_REQUEST['location'] ?? null;
$success = '';
$error = '';

$defaultName = $user['client_name'] ?? '';
$defaultPhone = $user['client_PhoneNumber'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loc = in_array($_POST['location'], $allowed) ? $_POST['location'] : 'indoor';
    $tables = array_filter(explode(',', $_POST['tables'] ?? ''), 'strlen');
    $dt = $_POST['datetime'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($tables) || !$dt || !$name || !$phone) {
        $error = 'Заповніть усі поля і виберіть хоча б один стіл.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO reservations (user_id, table_number, location, reservation_datetime, client_name, client_phone)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($tables as $t) {
            $tn = (int)$t;
            $stmt->bind_param('iissss', $user['client_id'], $tn, $loc, $dt, $name, $phone);
            $stmt->execute();
        }
        $stmt->close();
        $success = 'Ваші столи №' . implode(',', $tables) . " заброньовані на {$dt}.";
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<?php
$page = 'reservation';
$pageTitle = 'Бронювання | Coffee Time';
$customStyles = [
  '../static/css/reservation.css',
];
include '../includes/header.php';
?>
<main class="reservation-container">
  <h1>Бронювання столика</h1>

  <?php if ($success): ?>
    <div class="notification success"><?= htmlspecialchars($success) ?></div>
  <?php elseif ($error): ?>
    <div class="notification error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$location): ?>
    <div class="reservation-categories">
      <div class="reservation-option">
        <a href="?location=indoor">
          <img src="../static/images/main/indoor.jpg" alt="У приміщенні">
        </a>
        <h3>У приміщенні</h3>
        <a href="?location=indoor" class="reserve-button">Обрати</a>
      </div>
      <div class="reservation-option">
        <a href="?location=terrace">
          <img src="../static/images/main/terasa.jpg" alt="На терасі">
        </a>
        <h3>На терасі</h3>
        <a href="?location=terrace" class="reserve-button">Обрати</a>
      </div>
    </div>
  <?php else:
    $max = $location === 'terrace' ? 15 : 10;
    $stmt = $conn->prepare("SELECT table_number, reservation_datetime FROM reservations WHERE location=?");
    $stmt->bind_param('s', $location);
    $stmt->execute();
    $rs = $stmt->get_result();
    $booked = [];
    while ($r = $rs->fetch_assoc()) {
        $booked[(int)$r['table_number']] = date('d.m.Y H:i', strtotime($r['reservation_datetime']));
    }
    $stmt->close();
  ?>
    <p>Оберіть столи <?= $location === 'terrace' ? 'на терасі' : 'в залі' ?>:</p>
    <div class="table-grid">
      <?php
        for ($i = 1; $i <= $max; $i++) {
            $isBooked = isset($booked[$i]);
            $cls = $isBooked ? 'booked' : 'available';
            $dataTitle = $isBooked ? ' data-title="Заброньовано на: ' . htmlspecialchars($booked[$i]) . '"' : '';
            echo "<div class=\"table {$cls}\" data-table=\"{$i}\"{$dataTitle}>{$i}</div>";

        }
      ?>
    </div>

    <div class="reservation-footer">
      <form id="reserve-form" method="post">
        <input type="hidden" name="location" value="<?= htmlspecialchars($location) ?>">
        <input type="hidden" name="tables" id="f-tables">
        <label for="datetime">Дата і час:</label>
        <input type="datetime-local" id="datetime" name="datetime" required>

        <label for="name">Ім’я:</label>
        <input type="text" name="name" id="name" value="<?= htmlspecialchars($defaultName) ?>" required>

        <label for="phone">Телефон:</label>
        <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($defaultPhone) ?>" required>

        <button type="submit" class="btn" id="reserve-btn" disabled>Підтвердити</button>
      </form>
    </div>

    <script src="../static/js/reservation.js"></script>
  <?php endif; ?>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
