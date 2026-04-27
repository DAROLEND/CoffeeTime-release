<?php
require 'db.php';

$allowed = ['coffee_items', 'fast_food_items', 'pizza_items', 'cold_drink_items', 'dessert_items'];
$cat = $_GET['category'] ?? 'coffee_items';
if (!in_array($cat, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid category']);
    exit;
}

$stmt = $conn->prepare("SELECT id, name, description, image, price FROM `$cat`");
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows);
