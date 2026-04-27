<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle  = 'Редагувати товар';
$activePage = 'products';

$allowed = ['coffee_items','fast_food_items','pizza_items','cold_drink_items','dessert_items','giftcards','sushi_items','sushi_sets','salad_items','cake_items'];
$categoryNames = [
    'coffee_items'     => 'Кава',
    'fast_food_items'  => 'Фаст-фуд',
    'pizza_items'      => 'Піца',
    'cold_drink_items' => 'Холодні напої',
    'dessert_items'    => 'Десерти',
    'giftcards'        => 'Подарункові картки',
    'sushi_items'      => 'Суші',
    'sushi_sets'       => 'Сети суші',
    'salad_items'      => 'Салати',
    'cake_items'       => 'Торти на замовлення',
];
$categoryFolders = [
    'coffee_items'     => 'coffee',
    'cold_drink_items' => 'cold_drinks',
    'dessert_items'    => 'desserts',
    'fast_food_items'  => 'fast_food',
    'pizza_items'      => 'pizza',
    'giftcards'        => 'giftcards',
];

$category = $_GET['category'] ?? '';
$id       = (int)($_GET['id'] ?? 0);

if (!in_array($category, $allowed) || $id <= 0) {
    header('Location: manage_items.php');
    exit;
}
$catTitle = $categoryNames[$category];

/* ── Load product ── */
$stmt = $conn->prepare("SELECT * FROM `$category` WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: manage_items.php?category=' . $category);
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name']        ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $price     = floatval($_POST['price']   ?? 0);
    $imagePath = $product['image'];

    if ($name === '') $errors[] = 'Введіть назву товару.';
    if ($price <= 0)  $errors[] = 'Ціна має бути більша за 0.';

    /* New image upload */
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed_ext)) {
            $errors[] = 'Дозволені формати: JPG, PNG, WebP, GIF.';
        } else {
            $subfolder = $categoryFolders[$category];
            $uploadDir = __DIR__ . '/../static/images/menu_items/' . $subfolder . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $fileName   = uniqid('item_', true) . '.' . $ext;
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                /* Delete old image if exists */
                if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
                    @unlink(__DIR__ . '/../' . $imagePath);
                }
                $imagePath = 'static/images/menu_items/' . $subfolder . '/' . $fileName;
            } else {
                $errors[] = 'Не вдалося зберегти зображення.';
            }
        }
    }

    if (empty($errors)) {
        $nameCol = ($category === 'giftcards') ? 'title' : 'name';
        $stmt = $conn->prepare("UPDATE `$category` SET `$nameCol`=?, description=?, price=?, image=? WHERE id=?");
        $stmt->bind_param("ssdsi", $name, $desc, $price, $imagePath, $id);
        $stmt->execute();
        $stmt->close();
        /* Redirect back to items list (modal flow or direct) */
        header("Location: manage_items.php?category=$category&saved=1");
        exit;
    }
}

include 'includes/layout_top.php';
?>

<div class="form-card">
  <div class="form-card-header">
    <h2>Редагувати — <?= htmlspecialchars($catTitle) ?></h2>
    <a href="manage_items.php?category=<?= $category ?>" class="btn-ghost">← Назад до списку</a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">Зміни збережено успішно!</div>
  <?php endif; ?>
  <?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="admin-form">
    <div class="form-row">
      <div class="form-col">
        <label class="form-label" for="name">Назва товару *</label>
        <input class="form-control" type="text" id="name" name="name"
               value="<?= htmlspecialchars($product['name'] ?? $product['title'] ?? '') ?>" required>
      </div>
      <div class="form-col">
        <label class="form-label" for="price">Ціна (₴) *</label>
        <input class="form-control" type="number" id="price" name="price" step="0.01" min="0"
               value="<?= htmlspecialchars((string)$product['price']) ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Опис</label>
      <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Поточне фото</label>
      <?php if (!empty($product['image'])): ?>
        <img src="../<?= htmlspecialchars($product['image']) ?>"
             alt="Поточне зображення"
             style="display:block;width:140px;height:140px;object-fit:cover;border-radius:10px;margin-bottom:12px;border:2px solid #e8ddd5">
      <?php else: ?>
        <p style="color:#999;font-size:13px;margin:0 0 12px">Зображення відсутнє</p>
      <?php endif; ?>
      <label class="form-label">Замінити фото</label>
      <div class="upload-zone">
        <input type="file" name="image" accept="image/*">
        <div class="upload-hint">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#c49a6c" stroke-width="1.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <span>Клікніть або перетягніть нове фото</span>
        </div>
        <div class="upload-preview" style="display:none"></div>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-primary">Зберегти зміни</button>
      <a href="manage_items.php?category=<?= $category ?>" class="btn-secondary">Скасувати</a>
      <a href="delete_item.php?category=<?= $category ?>&id=<?= $id ?>"
         class="btn-danger" style="margin-left:auto"
         onclick="return confirm('Видалити цей товар назавжди?')">Видалити товар</a>
    </div>
  </form>
</div>

<?php include 'includes/layout_bottom.php'; ?>
