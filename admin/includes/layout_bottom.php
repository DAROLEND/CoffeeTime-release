    </main><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrapper -->

<script>
/* ── Toast ── */
function showAdminToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = 'admin-toast ' + type;
  t.textContent = msg;
  document.body.appendChild(t);
  requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
  setTimeout(() => {
    t.classList.remove('show');
    setTimeout(() => t.remove(), 300);
  }, 3500);
}

/* ── Count-up for stat cards ── */
function countUp(el, target, duration) {
  duration = duration || 800;
  const decimals = el.dataset.decimals ? parseInt(el.dataset.decimals) : 0;
  const suffix   = el.dataset.suffix || '';
  const prefix   = el.dataset.prefix || '';
  let current = 0;
  const step  = target / (duration / 16);
  const timer = setInterval(() => {
    current = Math.min(current + step, target);
    el.textContent = prefix + (decimals > 0
      ? current.toFixed(decimals)
      : Math.floor(current).toLocaleString('uk-UA')) + suffix;
    if (current >= target) clearInterval(timer);
  }, 16);
}
document.querySelectorAll('.stat-value[data-count]').forEach(el => {
  const target = parseFloat(el.dataset.count);
  if (!isNaN(target)) countUp(el, target);
});

/* ── AJAX status change (orders table) ── */
document.querySelectorAll('.status-select').forEach(function(sel) {
  sel.addEventListener('change', function() {
    const orderId  = this.dataset.orderId;
    const newStatus = this.value;
    const row      = this.closest('tr');
    const badge    = row ? row.querySelector('.status-badge') : null;

    fetch('update_order_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: parseInt(orderId), status: newStatus })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        if (badge) {
          badge.textContent = data.label;
          badge.className   = 'status-badge ' + data.css_class;
          badge.style.animation = 'none';
          badge.offsetWidth;
          badge.style.animation = 'statusPop 0.3s ease';
        }
        showAdminToast('Статус оновлено', 'success');
        // Update badges counters
        updateOrderBadges(data.new_count);
      } else {
        showAdminToast('Помилка оновлення', 'error');
      }
    })
    .catch(function() { showAdminToast('Помилка з\'єднання', 'error'); });
  });
});

/* ── Update order badge counts ── */
function updateOrderBadges(count) {
  document.querySelectorAll('.orders-badge').forEach(function(b) {
    b.textContent = count > 0 ? count : '';
    if (count > 0) {
      b.classList.add('pulse');
    } else {
      b.classList.remove('pulse');
      b.style.display = 'none';
    }
  });
}

/* ── Poll for new orders every 30 sec ── */
var _prevOrderCount = <?= (int)($newOrdersCount ?? 0) ?>;
setInterval(function() {
  fetch('check_new_orders.php')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.count > _prevOrderCount) {
        showAdminToast('🛍 Нове замовлення!', 'info');
      }
      _prevOrderCount = data.count;
      updateOrderBadges(data.count);
    })
    .catch(function() {});
}, 30000);

/* ── Modal helpers ── */
window.openModal = function(id) {
  var overlay = document.getElementById(id);
  if (overlay) overlay.classList.add('open');
};
window.closeModal = function(id) {
  var overlay = document.getElementById(id);
  if (overlay) overlay.classList.remove('open');
};
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
  overlay.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
  var closeBtn = overlay.querySelector('.modal-close');
  if (closeBtn) closeBtn.addEventListener('click', function() {
    overlay.classList.remove('open');
  });
});

/* ── Image upload preview ── */
document.querySelectorAll('.upload-zone input[type="file"]').forEach(function(input) {
  input.addEventListener('change', function() {
    var zone    = this.closest('.upload-zone');
    var preview = zone ? zone.querySelector('.upload-preview') : null;
    if (!preview || !this.files || !this.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
      preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
      preview.style.display = 'block';
    };
    reader.readAsDataURL(this.files[0]);
  });
});
</script>
</body>
</html>
