<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle  = 'Редагувати товар';
$activePage = 'products';

$allowed = ['coffee_items','fast_food_items','pizza_items','mini_pizza_items','cold_drink_items','ice_cream_items','dessert_items','sushi_items','sushi_sets','salad_items','cake_items'];
$categoryNames = [
    'coffee_items'      => 'Кава',
    'fast_food_items'   => 'Фаст-фуд',
    'pizza_items' => 'Піца', 'mini_pizza_items' => 'Міні-піца',
    'cold_drink_items'  => 'Холодні напої',
    'ice_cream_items'   => 'Морозиво',
    'dessert_items'     => 'Десерти',
    'sushi_items'       => 'Суші',
    'sushi_sets'        => 'Сети суші',
    'salad_items'       => 'Салати',
    'cake_items'        => 'Торти на замовлення',
];
$categoryFolders = [
    'coffee_items'      => 'coffee',
    'cold_drink_items'  => 'cold_drinks',
    'ice_cream_items'   => 'ice_cream',
    'dessert_items'     => 'desserts',
    'fast_food_items'   => 'fast_food',
    'pizza_items' => 'pizza', 'mini_pizza_items' => 'mini_pizza',
    'sushi_items'       => 'sushi',
    'sushi_sets'        => 'sushi',
    'salad_items'       => 'salads',
    'cake_items'        => 'cakes',
];

$category = $_GET['category'] ?? '';
$id       = (int)($_GET['id'] ?? 0);

if (!in_array($category, $allowed) || $id <= 0) {
    header('Location: manage_items.php');
    exit;
}
$catTitle = $categoryNames[$category];

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

    /* New image upload — cropped (base64) or raw file */
    $subfolder = $categoryFolders[$category];
    $uploadDir = __DIR__ . '/../static/images/menu_items/' . $subfolder . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $remoteBase = 'menu_items/' . $subfolder;

    $deleteOldImage = function() use (&$imagePath, $uploadDir, $subfolder) {
        if (!$imagePath) return;
        if (str_starts_with($imagePath, 'http')) {
            $parsed = parse_url($imagePath, PHP_URL_PATH);
            $prefix = '/storage/v1/object/public/' . SUPABASE_BUCKET . '/';
            if (str_starts_with($parsed, $prefix)) supabase_delete(substr($parsed, strlen($prefix)));
        } else {
            $local = __DIR__ . '/../' . $imagePath;
            if (file_exists($local)) @unlink($local);
        }
    };

    if (!empty($_POST['remove_image']) && $_POST['remove_image'] === '1' && empty($_POST['image_b64']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $deleteOldImage();
        $imagePath = '';
    } elseif (!empty($_POST['image_b64'])) {
        $fname = uniqid('item_', true);
        $saved = upload_image_b64($_POST['image_b64'], $uploadDir . $fname, $remoteBase . '/' . $fname);
        if ($saved) {
            $deleteOldImage();
            $imagePath = str_starts_with($saved, 'http') ? $saved
                : 'static/images/menu_items/' . $subfolder . '/' . $saved;
        } else {
            $errors[] = 'Помилка збереження зображення.';
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed_ext)) {
            $errors[] = 'Дозволені формати: JPG, PNG, WebP, GIF.';
        } else {
            $fileName = uniqid('item_', true) . '.' . $ext;
            $mime     = in_array($ext, ['png']) ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
            $saved    = upload_image($_FILES['image']['tmp_name'], $uploadDir . $fileName, $remoteBase . '/' . $fileName, $mime);
            if ($saved) {
                $deleteOldImage();
                $imagePath = str_starts_with($saved, 'http') ? $saved
                    : 'static/images/menu_items/' . $subfolder . '/' . $saved;
            } else {
                $errors[] = 'Не вдалося зберегти зображення.';
            }
        }
    }

    if (empty($errors)) {
        if ($category === 'ice_cream_items') {
            $diff2 = floatval($_POST['scoop_diff_2'] ?? 0);
            $diff3 = floatval($_POST['scoop_diff_3'] ?? 0);
            $variantOptions = json_encode([
                'type' => 'scoops', 'label' => 'Кількість кульок',
                'options' => [
                    ['id'=>'1','label'=>'1 кулька', 'price_diff'=>0],
                    ['id'=>'2','label'=>'2 кульки','price_diff'=>$diff2],
                    ['id'=>'3','label'=>'3 кульки','price_diff'=>$diff3],
                ]
            ]);
            $stmt = $conn->prepare("UPDATE ice_cream_items SET name=?, description=?, price=?, variant_options=?, image=? WHERE id=?");
            $stmt->bind_param("ssdssi", $name, $desc, $price, $variantOptions, $imagePath, $id);
        } elseif ($category === 'sushi_sets') {
            $pieces = max(0, (int)($_POST['pieces_count'] ?? 0));
            $stmt = $conn->prepare("UPDATE sushi_sets SET name=?, description=?, price=?, pieces_count=?, image=? WHERE id=?");
            $stmt->bind_param("ssdiis", $name, $desc, $price, $pieces, $imagePath, $id);
        } else {
            $stmt = $conn->prepare("UPDATE `$category` SET name=?, description=?, price=?, image=? WHERE id=?");
            $stmt->bind_param("ssdsi", $name, $desc, $price, $imagePath, $id);
        }
        $stmt->execute();
        $stmt->close();
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

    <?php if ($category === 'sushi_sets'): ?>
    <div class="form-group">
      <label class="form-label" for="pieces_count">Кількість штук у сеті</label>
      <input class="form-control" type="number" id="pieces_count" name="pieces_count"
             min="0" step="1" style="max-width:160px"
             value="<?= (int)($product['pieces_count'] ?? 0) ?>">
      <small style="color:#999;font-size:12px">Використовується для розрахунку часу приготування</small>
    </div>
    <?php endif; ?>

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
      <input type="hidden" name="image_b64" id="image_b64">
      <div class="upload-zone">
        <input type="file" name="image" accept="image/*" data-crop-hidden="image_b64">
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
