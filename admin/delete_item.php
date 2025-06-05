<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require '../db/db.php';

$category = $_GET['category'] ?? '';
$id = $_GET['id'] ?? '';

$allowed = ['coffee_items', 'fast_food_items', 'pizza_items', 'cold_drink_items', 'dessert_items', 'giftcards'];

if (!in_array($category, $allowed) || !$id || !is_numeric($id)) {
    echo "Невірний запит.";
    exit;
}

// Отримати шлях до зображення (щоб видалити файл)
$stmt = $conn->prepare("SELECT image FROM `$category` WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$image = $result->fetch_assoc()['image'] ?? null;
$stmt->close();

// Видалити товар
$stmt = $conn->prepare("DELETE FROM `$category` WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Видалити зображення з диска
if ($image && file_exists(__DIR__ . '/../' . $image)) {
    unlink(__DIR__ . '/../' . $image);
}

header("Location: manage_items.php?category=$category");
exit;
?>
