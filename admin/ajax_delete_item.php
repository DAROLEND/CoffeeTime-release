<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json');

$data     = json_decode(file_get_contents('php://input'), true);
$id       = isset($data['id'])       ? (int)$data['id']       : 0;
$category = isset($data['category']) ? trim($data['category']) : '';

$allowed = ['coffee_items','fast_food_items','pizza_items','cold_drink_items','dessert_items','giftcards','sushi_items','sushi_sets','salad_items','cake_items'];
if (!$id || !in_array($category, $allowed)) {
    echo json_encode(['success'=>false,'error'=>'Invalid input']); exit;
}

/* Get image path */
$stmt = $conn->prepare("SELECT image FROM `$category` WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* Delete from DB */
$stmt = $conn->prepare("DELETE FROM `$category` WHERE id=?");
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$stmt->close();

/* Delete image file */
if ($ok && !empty($row['image'])) {
    $imgPath = __DIR__ . '/../' . $row['image'];
    if (file_exists($imgPath)) @unlink($imgPath);
}

echo json_encode(['success' => $ok]);
