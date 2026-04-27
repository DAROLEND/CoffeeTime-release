<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle  = 'Додати товар';
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
    'sushi_items'      => 'sushi',
    'sushi_sets'       => 'sushi',
    'salad_items'      => 'salads',
    'cake_items'       => 'cakes',
];

$category = $_GET['category'] ?? '';
if (!in_array($category, $allowed)) {
    header('Location: manage_items.php');
    exit;
}
$catTitle = $categoryNames[$category];

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']        ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $price      = floatval($_POST['price']   ?? 0);
    $pricePerKg = floatval($_POST['price_per_kg'] ?? 0);
    $minWeight  = floatval($_POST['min_weight']   ?? 1);
    $isCake     = ($category === 'cake_items');

    if ($name === '') $errors[] = 'Введіть назву товару.';
    if ($isCake) {
        if ($pricePerKg <= 0) $errors[] = 'Ціна за кг має бути більша за 0.';
        if ($minWeight  <= 0) $minWeight = 1.0;
    } else {
        if ($price <= 0) $errors[] = 'Ціна має бути більша за 0.';
    }
    $imageOptional = ($isCake === false);
    $hasImage = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;
    if (!$hasImage) $errors[] = 'Оберіть зображення для завантаження.';

    if (empty($errors)) {
        $subfolder = $categoryFolders[$category] ?? 'other';
        $uploadDir = __DIR__ . '/../static/images/menu_items/' . $subfolder . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed_ext)) {
            $errors[] = 'Дозволені формати: JPG, PNG, WebP, GIF.';
        } else {
            $fileName   = uniqid('item_', true) . '.' . $ext;
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $imagePath = 'static/images/menu_items/' . $subfolder . '/' . $fileName;
                if ($isCake) {
                    $stmt = $conn->prepare("INSERT INTO cake_items (name, description, price_per_kg, min_weight, image) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssdds", $name, $desc, $pricePerKg, $minWeight, $imagePath);
                } else {
                    $nameCol = ($category === 'giftcards') ? 'title' : 'name';
                    $stmt = $conn->prepare("INSERT INTO `$category` (`$nameCol`, description, price, image) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssds", $name, $desc, $price, $imagePath);
                }
                $stmt->execute();
                $stmt->close();
                header("Location: manage_items.php?category=$category&saved=1");
                exit;
            } else {
                $errors[] = 'Не вдалося зберегти зображення.';
            }
        }
    }
}

include 'includes/layout_top.php';
?>

<div class="form-card">
  <div class="form-card-header">
    <h2>Новий товар — <?= htmlspecialchars($catTitle) ?></h2>
    <a href="manage_items.php?category=<?= $category ?>" class="btn-ghost">← Назад</a>
  </div>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="admin-form">
    <div class="form-row">
      <div class="form-col">
        <label class="form-label" for="name">Назва товару *</label>
        <input class="form-control" type="text" id="name" name="name"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
      </div>
      <?php if ($category === 'cake_items'): ?>
      <div class="form-col">
        <label class="form-label" for="price_per_kg">Ціна за кг (₴) *</label>
        <input class="form-control" type="number" id="price_per_kg" name="price_per_kg" step="10" min="0"
               value="<?= htmlspecialchars($_POST['price_per_kg'] ?? '1000') ?>" required>
      </div>
      <?php else: ?>
      <div class="form-col">
        <label class="form-label" for="price">Ціна (₴) *</label>
        <input class="form-control" type="number" id="price" name="price" step="0.01" min="0"
               value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($category === 'cake_items'): ?>
    <div class="form-row">
      <div class="form-col">
        <label class="form-label" for="min_weight">Мінімальна вага (кг)</label>
        <input class="form-control" type="number" id="min_weight" name="min_weight" step="0.5" min="0.5"
               value="<?= htmlspecialchars($_POST['min_weight'] ?? '1') ?>">
      </div>
      <div class="form-col">
        <label class="form-label">Тип замовлення</label>
        <input class="form-control" type="text" value="Замовлення під запит" disabled style="color:#aaa;">
      </div>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label class="form-label" for="description">Опис</label>
      <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Зображення *</label>
      <div class="upload-zone">
        <input type="file" name="image" accept="image/*" required>
        <div class="upload-hint">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#c49a6c" stroke-width="1.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <span>Клікніть або перетягніть фото</span>
        </div>
        <div class="upload-preview" style="display:none"></div>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-primary">Зберегти товар</button>
      <a href="manage_items.php?category=<?= $category ?>" class="btn-secondary">Скасувати</a>
    </div>
  </form>
</div>

<?php include 'includes/layout_bottom.php'; ?>
