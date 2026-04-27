<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../db/db.php';

$allowedTables = [
    'coffee_items', 'fast_food_items', 'pizza_items',
    'cold_drink_items', 'dessert_items', 'giftcards',
    'sushi_items', 'sushi_sets', 'salad_items', 'cake_items',
];

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/* ── Add item (called from menu page links) ── */
if (isset($_GET['action'], $_GET['category'], $_GET['id']) && $_GET['action'] === 'add') {
    $table = $_GET['category'];
    $id    = (int)$_GET['id'];
    if (in_array($table, $allowedTables, true) && $id > 0) {
        $_SESSION['lastCategory'] = $table;
        $found = null;
        foreach ($_SESSION['cart'] as $i => $it) {
            if ($it['category'] === $table && (int)$it['id'] === $id) { $found = $i; break; }
        }
        if ($found !== null) {
            $_SESSION['cart'][$found]['quantity']++;
        } else {
            $_SESSION['cart'][] = ['category' => $table, 'id' => $id, 'quantity' => 1];
        }
    }
    header('Location: cart.php');
    exit;
}

/* ── Fetch cart items from DB ── */
$total = 0;
$items = [];
$stmt  = null;
$prevTable = '';

foreach ($_SESSION['cart'] as $it) {
    if (!isset($it['category'], $it['id']) || !in_array($it['category'], $allowedTables, true)) continue;
    $table = $it['category'];
    $id    = (int)$it['id'];
    if (!$stmt || $prevTable !== $table) {
        $stmt = $conn->prepare("SELECT id, name, description, image, price FROM `$table` WHERE id = ?");
        $prevTable = $table;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $row['quantity'] = $it['quantity'];
        $row['category'] = $table;
        // Cake items use weight-based price_override
        if (isset($it['price_override'])) {
            $row['price']    = (float)$it['price_override'];
            $row['weight']   = $it['weight'] ?? 1;
        }
        $row['subtotal'] = $row['price'] * $it['quantity'];
        $total += $row['subtotal'];
        $items[] = $row;
    }
}
if ($stmt) $stmt->close();

$backCat   = $_SESSION['lastCategory'] ?? 'coffee_items';
$itemCount = count($items);

// Ukrainian word declension for "товар"
function itemWord(int $n): string {
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n >= 11 && $n <= 19) return 'товарів';
    if ($n1 === 1) return 'товар';
    if ($n1 >= 2 && $n1 <= 4) return 'товари';
    return 'товарів';
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Кошик — Coffee Time</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../static/css/style.css">
  <link rel="stylesheet" href="../static/css/cart.css">
  <link rel="stylesheet" href="../static/css/footer.css">
  <link rel="stylesheet" href="../static/css/animations.css">
  <script defer src="../static/js/animations.js"></script>
</head>
<body>
<?php include '../includes/header.php'; ?>

<main class="cart-page">

  <?php if (!empty($_SESSION['flash_error'])): ?>
  <div style="background:#ffebee;color:#c62828;padding:12px 20px;border-radius:8px;
              margin-bottom:20px;font-size:14px;border:1px solid #f5c6c6;">
    ⚠️ <?= h($_SESSION['flash_error']) ?>
    <?php unset($_SESSION['flash_error']); ?>
  </div>
  <?php endif; ?>

  <!-- ── Header ── -->
  <div class="cart-header">
    <h1>Ваша корзина</h1>
    <p class="cart-count-label" id="cartCountLabel">
      <?= $itemCount > 0
        ? $itemCount . ' ' . itemWord($itemCount)
        : 'Порожньо' ?>
    </p>
  </div>

  <!-- ── Empty state ── -->
  <div class="cart-empty" id="cartEmpty" <?= $itemCount > 0 ? 'style="display:none"' : '' ?>>
    <div class="cart-empty-icon">🛒</div>
    <h2>Ваша корзина порожня</h2>
    <p>Додайте щось смачне з нашого меню</p>
    <a href="menu.php?category=<?= urlencode($backCat) ?>" class="empty-menu-btn">
      Перейти до меню
    </a>
  </div>

  <!-- ── Main layout ── -->
  <div class="cart-layout" id="cartLayout" <?= $itemCount === 0 ? 'style="display:none"' : '' ?>>

    <!-- Left: items list -->
    <div class="cart-items-col" id="cartItemsList">
      <?php foreach ($items as $i => $it): ?>
      <div class="cart-item"
           data-category="<?= htmlspecialchars($it['category'], ENT_QUOTES) ?>"
           data-item-id="<?= (int)$it['id'] ?>"
           data-price="<?= number_format((float)$it['price'], 2, '.', '') ?>">

        <img src="../<?= htmlspecialchars($it['image'], ENT_QUOTES) ?>"
             alt="<?= htmlspecialchars($it['name'], ENT_QUOTES) ?>"
             loading="lazy">

        <div class="cart-item-body">
          <h3><?= htmlspecialchars($it['name'], ENT_QUOTES) ?></h3>
          <?php if (!empty($it['description'])): ?>
            <p class="item-desc"><?= htmlspecialchars($it['description'], ENT_QUOTES) ?></p>
          <?php endif; ?>

          <div class="qty-control">
            <button class="qty-btn qty-dec"
                    data-action="decrease"
                    <?= $it['quantity'] <= 1 ? 'disabled' : '' ?>
                    aria-label="Зменшити">−</button>
            <input type="number" class="qty-value"
                   value="<?= (int)$it['quantity'] ?>"
                   min="1" max="99"
                   aria-label="Кількість товару">
            <button class="qty-btn qty-inc"
                    data-action="increase"
                    aria-label="Збільшити">+</button>
          </div>
        </div>

        <div class="cart-item-right">
          <span class="item-subtotal">
            <?= number_format($it['subtotal'], 0, ',', ' ') ?> ₴
          </span>
          <button class="remove-btn" aria-label="Видалити товар">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6M14 11v6"/>
              <path d="M9 6V4h6v2"/>
            </svg>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Right: summary panel -->
    <div class="cart-summary-col">
      <div class="cart-summary-box" id="cartSummaryBox">

        <h3>Підсумок</h3>

        <div class="summary-row">
          <span>Товарів:</span>
          <span id="summaryCount">
            <?php
              $totalQty = array_sum(array_column($items, 'quantity'));
              echo $totalQty . ' шт.';
            ?>
          </span>
        </div>
        <div class="summary-row muted">
          <span>Знижка:</span>
          <span>0 ₴</span>
        </div>

        <div class="summary-divider"></div>

        <div class="summary-total-row">
          <span>Загальна сума:</span>
          <span class="cart-total" id="cartTotal">
            <?= number_format($total, 0, ',', ' ') ?> ₴
          </span>
        </div>

        <form action="checkout.php" method="post" id="checkoutForm">
          <?= csrf_field() ?>
          <button type="submit" class="checkout-btn" id="checkoutBtn">
            Оформити замовлення
          </button>
        </form>

        <a href="menu.php?category=<?= urlencode($backCat) ?>"
           class="back-to-menu-btn">
          Повернутись до меню
        </a>

        <div class="clear-cart-wrap">
          <button class="clear-cart-link" id="clearCartBtn">Очистити корзину</button>
          <div class="clear-confirm" id="clearConfirm" style="display:none">
            <span>Ви впевнені?</span>
            <button class="clear-confirm-yes" id="clearYes">Так, очистити</button>
            <button class="clear-confirm-no" id="clearNo">Скасувати</button>
          </div>
        </div>

      </div>
    </div>

  </div><!-- /.cart-layout -->
</main>

<?php include '../includes/footer.php'; ?>

<script>
(function () {
  'use strict';

  /* ── Helpers ── */
  function fmt(n) {
    return Math.round(n).toLocaleString('uk-UA') + ' ₴';
  }
  function itemWord(n) {
    n = Math.abs(n) % 100;
    const n1 = n % 10;
    if (n >= 11 && n <= 19) return 'товарів';
    if (n1 === 1) return 'товар';
    if (n1 >= 2 && n1 <= 4) return 'товари';
    return 'товарів';
  }
  function flash(el) {
    el.classList.remove('flash');
    void el.offsetWidth;
    el.classList.add('flash');
    setTimeout(() => el.classList.remove('flash'), 380);
  }
  function showToast(msg) {
    if (window.showToast) { window.showToast(msg); return; }
    // fallback
    const t = document.createElement('div');
    t.className = 'ct-toast show';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2500);
  }
  function updateNavBadge(count) {
    let badge = document.querySelector('.cart-count');
    if (count <= 0) { if (badge) badge.remove(); return; }
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'cart-count';
      document.querySelector('.cart-wrapper')?.appendChild(badge);
    }
    badge.textContent = count;
    badge.classList.remove('pop');
    void badge.offsetWidth;
    badge.classList.add('pop');
    setTimeout(() => badge.classList.remove('pop'), 320);
  }
  function updateSummary(total, count) {
    const totalEl = document.getElementById('cartTotal');
    const countLbl = document.getElementById('cartCountLabel');
    const summaryCount = document.getElementById('summaryCount');

    if (totalEl) { totalEl.textContent = fmt(total); flash(totalEl); }
    if (countLbl) countLbl.textContent = count > 0 ? count + ' ' + itemWord(count) : 'Порожньо';
    if (summaryCount) summaryCount.textContent = count + ' шт.';
    updateNavBadge(count);
  }

  function showEmpty() {
    const layout = document.getElementById('cartLayout');
    const empty  = document.getElementById('cartEmpty');
    const lbl    = document.getElementById('cartCountLabel');
    updateNavBadge(0);

    // 1. Fade count label text change
    if (lbl) {
      lbl.style.transition = 'opacity 0.18s ease';
      lbl.style.opacity    = '0';
      setTimeout(() => {
        lbl.textContent   = 'Порожньо';
        lbl.style.opacity = '1';
      }, 190);
    }

    // 2. Fade out the whole 2-column layout
    if (layout) {
      layout.style.transition = 'opacity 0.3s ease';
      layout.style.opacity    = '0';

      setTimeout(() => {
        layout.style.display    = 'none';
        layout.style.transition = '';
        layout.style.opacity    = '';

        // 3. Fade in empty state
        if (empty) {
          empty.style.opacity    = '0';
          empty.style.display    = '';
          setTimeout(() => {
            empty.style.transition = 'opacity 0.36s ease';
            empty.style.opacity    = '1';
          }, 16);
        }
      }, 320);
    }
  }

  /* ── Entry point ── */
  const list = document.getElementById('cartItemsList');
  if (!list) return;

  /* ── Stagger load animation ── */
  document.querySelectorAll('.cart-item').forEach((item, i) => {
    setTimeout(() => item.classList.add('visible'), 80 + i * 90);
  });
  const summaryBox = document.getElementById('cartSummaryBox');
  if (summaryBox) summaryBox.classList.add('visible');

  /* ── Quantity buttons ── */
  list.addEventListener('click', async (e) => {
    const btn = e.target.closest('.qty-btn');
    if (!btn) return;

    const row      = btn.closest('.cart-item');
    const category = row.dataset.category;
    const itemId   = row.dataset.itemId;
    const action   = btn.dataset.action;

    btn.disabled = true;

    const body = new FormData();
    body.append('category', category);
    body.append('item_id', itemId);
    body.append('action', action);

    try {
      const res  = await fetch('../forms/update_cart.php', { method: 'POST', body });
      const json = await res.json();
      if (!json.ok) return;

      if (json.removed) {
        animateRemove(row, () => {
          row.remove();
          updateSummary(json.cart_total, json.cart_count);
          if (document.querySelectorAll('.cart-item').length === 0) showEmpty();
        });
        showToast('Товар видалено з кошика');
        return;
      }

      // Update qty display
      const qtyEl   = row.querySelector('.qty-value');
      const decBtn  = row.querySelector('.qty-dec');
      const subEl   = row.querySelector('.item-subtotal');

      if (qtyEl) {
        qtyEl.classList.remove('bumping');
        void qtyEl.offsetWidth;
        qtyEl.value = json.new_qty;
        qtyEl.classList.add('bumping');
        setTimeout(() => qtyEl.classList.remove('bumping'), 300);
      }
      if (decBtn) decBtn.disabled = json.new_qty <= 1;
      if (subEl)  { subEl.textContent = fmt(json.item_total); flash(subEl); }

      updateSummary(json.cart_total, json.cart_count);

    } catch (_) {
      console.error('Cart update failed');
    } finally {
      btn.disabled = false;
    }
  });

  /* ── Remove button ── */
  list.addEventListener('click', async (e) => {
    const btn = e.target.closest('.remove-btn');
    if (!btn) return;

    const row      = btn.closest('.cart-item');
    const category = row.dataset.category;
    const itemId   = row.dataset.itemId;

    const body = new FormData();
    body.append('category', category);
    body.append('item_id', itemId);

    animateRemove(row, async () => {
      row.remove();
      try {
        const res  = await fetch('../forms/remove_from_cart.php', { method: 'POST', body });
        const json = await res.json();
        if (json.ok) {
          updateSummary(json.cart_total, json.cart_count);
        }
      } catch (_) {}
      if (document.querySelectorAll('.cart-item').length === 0) showEmpty();
    });
    showToast('Товар видалено з кошика');
  });

  function animateRemove(row, cb) {
    row.classList.add('removing');
    setTimeout(cb, 340);
  }

  /* ── Direct qty input ── */
  async function applyQtyInput(input) {
    const row      = input.closest('.cart-item');
    const category = row.dataset.category;
    const itemId   = row.dataset.itemId;

    let qty = parseInt(input.value, 10);
    if (isNaN(qty) || qty < 1) qty = 1;
    if (qty > 99) qty = 99;
    input.value = qty;  /* snap to valid range */

    const decBtn = row.querySelector('.qty-dec');
    const subEl  = row.querySelector('.item-subtotal');

    const body = new FormData();
    body.append('category', category);
    body.append('item_id',  itemId);
    body.append('action',   'set_qty');
    body.append('qty',      qty);

    try {
      const res  = await fetch('../forms/update_cart.php', { method: 'POST', body });
      const json = await res.json();
      if (!json.ok) return;

      if (decBtn) decBtn.disabled = qty <= 1;
      if (subEl)  { subEl.textContent = fmt(json.item_total); flash(subEl); }

      input.classList.remove('bumping');
      void input.offsetWidth;
      input.classList.add('bumping');
      setTimeout(() => input.classList.remove('bumping'), 300);

      updateSummary(json.cart_total, json.cart_count);
    } catch (_) {
      console.error('qty set failed');
    }
  }

  /* blur — підтверджує введене значення */
  list.addEventListener('change', (e) => {
    if (e.target.classList.contains('qty-value')) applyQtyInput(e.target);
  });

  /* Enter — знімає фокус (що викличе change) */
  list.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.classList.contains('qty-value')) {
      e.preventDefault();
      e.target.blur();
    }
  });

  /* виділяємо весь текст при фокусі — зручно для швидкого перезапису */
  list.addEventListener('focusin', (e) => {
    if (e.target.classList.contains('qty-value')) e.target.select();
  });

  /* ── Clear cart ── */
  const clearBtn     = document.getElementById('clearCartBtn');
  const clearConfirm = document.getElementById('clearConfirm');
  const clearYes     = document.getElementById('clearYes');
  const clearNo      = document.getElementById('clearNo');

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      clearBtn.style.display = 'none';
      clearConfirm.style.display = 'flex';
    });
  }
  if (clearNo) {
    clearNo.addEventListener('click', () => {
      clearConfirm.style.display = 'none';
      clearBtn.style.display = '';
    });
  }
  if (clearYes) {
    clearYes.addEventListener('click', async () => {
      const rows = document.querySelectorAll('.cart-item');
      // Stagger slide-out for each item
      rows.forEach((r, i) => {
        setTimeout(() => r.classList.add('removing'), i * 60);
      });
      try {
        await fetch('../forms/clear_cart.php', { method: 'POST' });
      } catch (_) {}
      // After all items are gone, showEmpty handles layout fade-out + empty fade-in
      const afterItems = 60 * rows.length + 340;
      setTimeout(showEmpty, afterItems);
    });
  }

  /* ── Checkout loading state ── */
  const checkoutForm = document.getElementById('checkoutForm');
  const checkoutBtn  = document.getElementById('checkoutBtn');
  if (checkoutForm && checkoutBtn) {
    checkoutForm.addEventListener('submit', () => {
      checkoutBtn.classList.add('loading');
      checkoutBtn.textContent = '';
    });
  }

})();
</script>
</body>
</html>
