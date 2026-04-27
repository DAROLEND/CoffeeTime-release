<?php
require_once '../includes/session.php';
require_once '../db/db.php';
require_once '../includes/helpers.php';

if (empty($_SESSION['user'])) {
    header('Location: ../forms/login.php'); exit;
}

$user   = $_SESSION['user'];
$userId = (int)$user['client_id'];

/* ── POST handlers ── */
$profileError   = '';
$passwordError  = '';
$savedFlag      = '';   // 'profile' | 'password'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $phone     = trim($_POST['phone']      ?? '');
        $stmt = $conn->prepare("UPDATE users SET client_name=?, client_surname=?, client_PhoneNumber=? WHERE client_id=?");
        $stmt->bind_param('sssi', $firstName, $lastName, $phone, $userId);
        if ($stmt->execute()) {
            $_SESSION['user']['client_name']        = $firstName;
            $_SESSION['user']['client_surname']     = $lastName;
            $_SESSION['user']['client_PhoneNumber'] = $phone;
            $user = $_SESSION['user'];
            $_SESSION['prof_saved'] = 'profile';
            header('Location: profile.php?tab=settings'); exit;
        }
        $profileError = 'Помилка оновлення даних.';
        $stmt->close();

    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $passwordError = 'Заповніть усі поля.';
        } elseif ($new !== $confirm) {
            $passwordError = 'Паролі не збігаються.';
        } elseif (mb_strlen($new) < 6) {
            $passwordError = 'Мінімум 6 символів.';
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE client_id=?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $dbRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($dbRow && password_verify($current, $dbRow['password'])) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE client_id=?");
                $stmt->bind_param('si', $hash, $userId);
                $stmt->execute();
                $stmt->close();
                $_SESSION['prof_saved'] = 'password';
                header('Location: profile.php?tab=settings'); exit;
            }
            $passwordError = 'Неправильний поточний пароль.';
        }
    }
}

/* ── Flash success from PRG ── */
$savedFlag = $_SESSION['prof_saved'] ?? '';
unset($_SESSION['prof_saved']);

/* ── Fetch created_at if not in session ── */
if (empty($user['created_at'])) {
    $stmt = $conn->prepare("SELECT created_at FROM users WHERE client_id=?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $user['created_at'] = $row['created_at'] ?? null;
    $_SESSION['user']['created_at'] = $user['created_at'];
}

/* ── Stats ── */
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS tsum FROM orders WHERE user_id=?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats      = $stmt->get_result()->fetch_assoc();
$stmt->close();
$orderCount = (int)$stats['cnt'];
$totalSpent = (float)$stats['tsum'];

/* ── Member since (Ukrainian month abbreviation) ── */
$ukMonths = ['', 'січ.', 'лют.', 'бер.', 'квіт.', 'трав.', 'черв.', 'лип.', 'серп.', 'вер.', 'жовт.', 'лист.', 'груд.'];
$joinedAt = !empty($user['created_at'])
    ? ($ukMonths[(int)date('n', strtotime($user['created_at']))] . ' ' . date('Y', strtotime($user['created_at'])))
    : '—';

/* ── Orders ── */
$stmt = $conn->prepare("
    SELECT o.order_id, o.created_at, o.total, o.status,
           o.payment_method, o.payment_status,
           o.ready_time, o.comment, COUNT(oi.id) AS items_count
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT 20
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Avatar initials ── */
$firstName = $user['client_name']    ?? '';
$lastName  = $user['client_surname'] ?? '';
$initials  = mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'), 'UTF-8');
if (!trim($initials)) {
    $initials = mb_strtoupper(mb_substr($user['login'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8');
}
$displayName = trim("$firstName $lastName") ?: ($user['login'] ?? '');

/* ── Active tab ── */
$activeTab = (($_GET['tab'] ?? '') === 'settings') ? 'settings' : 'orders';

/* ── Badge helpers ── */
function profPayBadge(array $o): string {
    $ps = $o['payment_status'] ?? '';
    $pm = $o['payment_method']  ?? '';
    if ($ps === 'paid')              return '<span class="ppay ppay-paid">💳 Оплачено</span>';
    if (str_contains($pm, 'cash'))   return '<span class="ppay ppay-cash">💵 Готівка</span>';
    return '<span class="ppay ppay-pending">⏳ Не оплачено</span>';
}
function profStatusBadge(string $s): string {
    return match($s) {
        'done'      => '<span class="pstat pstat-done">✅ Готово</span>',
        'cancelled' => '<span class="pstat pstat-cancelled">❌ Скасовано</span>',
        default     => '<span class="pstat pstat-new">🆕 Нове</span>',
    };
}

$page         = 'profile';
$pageTitle    = 'Профіль — Coffee Time';
$customStyles = ['../static/css/profile.css'];
?>
<!DOCTYPE html>
<html lang="uk">
<?php include '../includes/header.php'; ?>
<body>

<div class="prof-wrap">

  <!-- ════ LEFT SIDEBAR ════ -->
  <aside class="prof-sidebar">

    <div class="prof-avatar"><?= h($initials) ?></div>
    <div class="prof-name"><?= h($displayName) ?></div>
    <div class="prof-email"><?= h($user['email'] ?? '') ?></div>
    <?php if (!empty($user['client_PhoneNumber'])): ?>
      <div class="prof-phone"><?= h($user['client_PhoneNumber']) ?></div>
    <?php endif; ?>

    <hr class="prof-divider">

    <div class="prof-stats">
      <div class="prof-stat">
        <span class="prof-stat-label">Замовлень</span>
        <span class="prof-stat-val" data-countup="<?= $orderCount ?>">0</span>
      </div>
      <div class="prof-stat">
        <span class="prof-stat-label">Витрачено</span>
        <span class="prof-stat-val" data-countup="<?= (int)round($totalSpent) ?>" data-suffix="₴">0</span>
      </div>
      <div class="prof-stat">
        <span class="prof-stat-label">З нами з</span>
        <span class="prof-stat-val"><?= h($joinedAt) ?></span>
      </div>
    </div>

    <hr class="prof-divider">

    <a href="../pages/logout.php" class="prof-logout-btn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Вийти
    </a>

  </aside>

  <!-- ════ RIGHT MAIN ════ -->
  <main class="prof-main">

    <!-- Tab nav -->
    <div class="prof-tabs">
      <button class="prof-tab <?= $activeTab === 'orders'   ? 'active' : '' ?>" data-tab="orders">Мої замовлення</button>
      <button class="prof-tab <?= $activeTab === 'settings' ? 'active' : '' ?>" data-tab="settings">Налаштування</button>
    </div>

    <!-- ══ TAB: Orders ══ -->
    <div class="prof-tab-panel <?= $activeTab === 'orders' ? 'active' : '' ?>" id="panel-orders">

      <?php if (empty($orders)): ?>
        <div class="prof-empty">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/>
          </svg>
          <p class="prof-empty-title">У вас ще немає замовлень</p>
          <p class="prof-empty-sub">Додайте щось смачне з нашого меню</p>
          <a href="../pages/menu.php" class="prof-empty-btn">Перейти до меню</a>
        </div>
      <?php else: ?>
        <?php foreach ($orders as $i => $o): ?>
        <div class="order-card" data-status="<?= h($o['status']) ?>" data-reveal
             style="--reveal-delay:<?= $i * 0.06 ?>s">

          <div class="order-card__head">
            <div>
              <div class="order-card__id">#<?= (int)$o['order_id'] ?></div>
              <div class="order-card__date"><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></div>
            </div>
            <div class="order-card__badges">
              <?= profPayBadge($o) ?>
              <?= profStatusBadge($o['status']) ?>
            </div>
          </div>

          <div class="order-card__body">
            <span class="order-card__meta">
              <?= (int)$o['items_count'] ?> позиц.&nbsp;·&nbsp;<strong><?= number_format($o['total'], 0, ',', ' ') ?>&nbsp;₴</strong><?php if (!empty($o['ready_time'])): ?>&nbsp;·&nbsp;<?= h($o['ready_time']) ?><?php endif; ?>
            </span>
            <button class="order-card__toggle" data-order-id="<?= (int)$o['order_id'] ?>">Деталі ▾</button>
          </div>

          <div class="order-card__details" id="details-<?= (int)$o['order_id'] ?>">
            <div class="order-card__details-inner">
              <?php if (!empty($o['comment'])): ?>
                <div class="order-card__comment">💬 <?= h($o['comment']) ?></div>
              <?php endif; ?>
              <div class="oi-loading">Завантаження…</div>
            </div>
          </div>

        </div>
        <?php endforeach; ?>
      <?php endif; ?>

    </div><!-- /panel-orders -->

    <!-- ══ TAB: Settings ══ -->
    <div class="prof-tab-panel <?= $activeTab === 'settings' ? 'active' : '' ?>" id="panel-settings">

      <!-- Personal data -->
      <div class="prof-section">
        <div class="prof-section__title">Особисті дані</div>

        <?php if ($savedFlag === 'profile'): ?>
          <div class="prof-alert prof-alert--success">✓ Профіль оновлено</div>
        <?php elseif ($profileError): ?>
          <div class="prof-alert prof-alert--error"><?= h($profileError) ?></div>
        <?php endif; ?>

        <form method="post" id="profileForm" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="profile">

          <div class="prof-form-row">
            <div class="prof-field">
              <label>Ім'я</label>
              <input type="text" name="first_name" value="<?= h($user['client_name'] ?? '') ?>" placeholder="Ім'я">
            </div>
            <div class="prof-field">
              <label>Прізвище</label>
              <input type="text" name="last_name" value="<?= h($user['client_surname'] ?? '') ?>" placeholder="Прізвище">
            </div>
          </div>

          <div class="prof-field">
            <label>Телефон</label>
            <input type="tel" name="phone" value="<?= h($user['client_PhoneNumber'] ?? '') ?>" placeholder="+38 (0XX) XXX-XX-XX">
          </div>

          <button type="submit" class="prof-btn prof-btn--yellow" id="profileSaveBtn">Зберегти зміни</button>
        </form>
      </div>

      <!-- Security -->
      <div class="prof-section">
        <div class="prof-section__title">Безпека</div>

        <?php if ($savedFlag === 'password'): ?>
          <div class="prof-alert prof-alert--success">✓ Пароль успішно змінено</div>
        <?php elseif ($passwordError): ?>
          <div class="prof-alert prof-alert--error"><?= h($passwordError) ?></div>
        <?php endif; ?>

        <form method="post" id="passwordForm" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="password">

          <div class="prof-field">
            <label>Поточний пароль</label>
            <div class="pw-wrap">
              <input type="password" name="current_password" id="pwCurrent" placeholder="••••••••" autocomplete="current-password">
              <button type="button" class="eye-btn" data-target="pwCurrent">
                <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <div class="prof-field">
            <label>Новий пароль</label>
            <div class="pw-wrap">
              <input type="password" name="new_password" id="pwNew" placeholder="••••••••" autocomplete="new-password">
              <button type="button" class="eye-btn" data-target="pwNew">
                <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <div class="pw-strength" id="pwStrength" style="display:none">
              <div class="pw-strength-bar"><span></span><span></span><span></span><span></span></div>
              <div class="pw-strength-label" id="pwStrengthLabel"></div>
            </div>
          </div>

          <div class="prof-field">
            <label>Підтвердіть новий пароль</label>
            <div class="pw-wrap">
              <input type="password" name="confirm_password" id="pwConfirm" placeholder="••••••••" autocomplete="new-password">
              <button type="button" class="eye-btn" data-target="pwConfirm">
                <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <button type="submit" class="prof-btn prof-btn--brown" id="passwordSaveBtn">Змінити пароль</button>
        </form>
      </div>

    </div><!-- /panel-settings -->

  </main>
</div>

<?php include '../includes/footer.php'; ?>

<script>
(function () {
'use strict';

/* ── Tab switching ── */
document.querySelectorAll('.prof-tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    var target = this.dataset.tab;
    if (this.classList.contains('active')) return;

    document.querySelectorAll('.prof-tab').forEach(function (t) { t.classList.remove('active'); });
    this.classList.add('active');

    var url = new URL(window.location);
    url.searchParams.set('tab', target);
    history.replaceState(null, '', url);

    document.querySelectorAll('.prof-tab-panel').forEach(function (p) {
      p.classList.remove('active');
    });
    var next = document.getElementById('panel-' + target);
    if (next) next.classList.add('active');
  });
});

/* ── Count-up animation ── */
function countUp(el, target, ms) {
  if (target === 0) { el.textContent = '0' + (el.dataset.suffix ? ' ' + el.dataset.suffix : ''); return; }
  var suffix = el.dataset.suffix ? ' ' + el.dataset.suffix : '';
  var step   = target / (ms / 16);
  var cur    = 0;
  var timer  = setInterval(function () {
    cur = Math.min(cur + step, target);
    el.textContent = Math.round(cur).toLocaleString('uk-UA') + suffix;
    if (cur >= target) clearInterval(timer);
  }, 16);
}
document.querySelectorAll('[data-countup]').forEach(function (el) {
  countUp(el, parseInt(el.dataset.countup, 10), 800);
});

/* ── Stagger reveal (IntersectionObserver) ── */
var revealObs = new IntersectionObserver(function (entries) {
  entries.forEach(function (entry) {
    if (entry.isIntersecting) {
      entry.target.classList.add('revealed');
      revealObs.unobserve(entry.target);
    }
  });
}, { threshold: 0.08 });
document.querySelectorAll('[data-reveal]').forEach(function (el) { revealObs.observe(el); });

/* ── Order details expand / collapse ── */
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.order-card__toggle');
  if (!btn) return;

  var orderId    = btn.dataset.orderId;
  var detailsEl  = document.getElementById('details-' + orderId);
  var isOpen     = detailsEl.classList.contains('open');

  if (isOpen) {
    detailsEl.classList.remove('open');
    btn.textContent = 'Деталі ▾';
  } else {
    detailsEl.classList.add('open');
    btn.textContent = 'Сховати ▴';

    if (!detailsEl.dataset.loaded) {
      detailsEl.dataset.loaded = '1';
      var inner = detailsEl.querySelector('.order-card__details-inner');

      fetch('../pages/get_order_items.php?order_id=' + orderId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var loader = inner.querySelector('.oi-loading');
          if (loader) loader.remove();

          if (!data.items || !data.items.length) {
            inner.insertAdjacentHTML('beforeend', '<p class="oi-empty">Товари не знайдено</p>');
            return;
          }

          var emoji = {
            coffee_items: '☕', fast_food_items: '🍔', pizza_items: '🍕',
            cold_drink_items: '🥤', dessert_items: '🍰', giftcards: '🎁',
            sushi_items: '🍣', sushi_sets: '🎎', salad_items: '🥗',
            cake_items: '🎂'
          };

          var html = data.items.map(function (item) {
            var total = (item.quantity * item.price).toLocaleString('uk-UA', { maximumFractionDigits: 0 });
            return '<div class="oi-row">' +
              '<span class="oi-icon">' + (emoji[item.category] || '🛍') + '</span>' +
              '<span class="oi-name">' + item.name + '</span>' +
              '<span class="oi-qty">×' + item.quantity + '</span>' +
              '<span class="oi-price">' + total + ' ₴</span>' +
            '</div>';
          }).join('');

          inner.insertAdjacentHTML('beforeend', html);
        })
        .catch(function () {
          var loader = detailsEl.querySelector('.oi-loading');
          if (loader) loader.textContent = 'Помилка завантаження';
        });
    }
  }
});

/* ── Eye toggles ── */
document.querySelectorAll('.eye-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var inp  = document.getElementById(this.dataset.target);
    var show = this.querySelector('.eye-show');
    var hide = this.querySelector('.eye-hide');
    if (inp.type === 'password') {
      inp.type = 'text';
      show.style.display = 'none'; hide.style.display = 'block';
    } else {
      inp.type = 'password';
      show.style.display = 'block'; hide.style.display = 'none';
    }
  });
});

/* ── Password strength ── */
var pwNew          = document.getElementById('pwNew');
var pwStrengthEl   = document.getElementById('pwStrength');
var pwStrengthLbl  = document.getElementById('pwStrengthLabel');
var pwSegs         = pwStrengthEl ? pwStrengthEl.querySelectorAll('.pw-strength-bar span') : [];

var strengthCfg = [null,
  { label: 'Слабкий',   color: '#f44336' },
  { label: 'Середній',  color: '#FF9800' },
  { label: 'Сильний',   color: '#FFC107' },
  { label: 'Відмінний', color: '#4CAF50' },
];

function calcStrength(pw) {
  var s = 0;
  if (pw.length >= 6)  s++;
  if (pw.length >= 10) s++;
  if (/[A-ZА-ЯІЇЄҐ]/u.test(pw)) s++;
  if (/[0-9]/.test(pw)) s++;
  if (/[^a-zA-Zа-яА-ЯіїєґІЇЄҐ0-9]/u.test(pw)) s++;
  return Math.min(4, Math.max(1, s));
}

if (pwNew) {
  pwNew.addEventListener('input', function () {
    if (!this.value) { pwStrengthEl.style.display = 'none'; return; }
    pwStrengthEl.style.display = 'block';
    var score = calcStrength(this.value);
    var cfg   = strengthCfg[score];
    pwSegs.forEach(function (seg, i) { seg.style.background = i < score ? cfg.color : '#e8e0d8'; });
    pwStrengthLbl.textContent = cfg.label;
    pwStrengthLbl.style.color = cfg.color;
  });
}

/* ── Submit loading state ── */
document.getElementById('profileForm')?.addEventListener('submit', function () {
  document.getElementById('profileSaveBtn').classList.add('loading');
});
document.getElementById('passwordForm')?.addEventListener('submit', function () {
  document.getElementById('passwordSaveBtn').classList.add('loading');
});

})();
</script>
</body>
</html>
