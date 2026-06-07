    </main><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrapper -->

<script>

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

var _prevOrderCount = <?= (int)($newOrdersCount ?? 0) ?>;
setInterval(function() {
  fetch('check_new_orders.php')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.count > _prevOrderCount) {
        showAdminToast('Нове замовлення!', 'info');
      }
      _prevOrderCount = data.count;
      updateOrderBadges(data.count);
    })
    .catch(function() {});
}, 30000);

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

(function() {
  var cropperInstance = null;
  var cropTarget      = null;
  /* cropTarget: { hiddenInput, previewEl, fileInput, livePreviewSel, ratio, originalDataUrl } */

  var modal = document.createElement('div');
  modal.id = 'imgCropModal';
  modal.innerHTML = [
    '<div class="icm-backdrop"></div>',
    '<div class="icm-dialog">',
    '  <div class="icm-header">',
    '    <span class="icm-title">Редагування фото</span>',
    '    <button class="icm-close" id="icmClose" type="button">',
    '      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    '    </button>',
    '  </div>',
    '  <div class="icm-body"><img id="icmImg" src=""></div>',
    '  <div class="icm-toolbar">',
    '    <button type="button" id="icmRotL"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>Вліво</button>',
    '    <button type="button" id="icmRotR"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>Вправо</button>',
    '    <button type="button" id="icmZoomIn"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></button>',
    '    <button type="button" id="icmZoomOut"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg></button>',
    '    <button type="button" id="icmReset"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.47"/></svg>Скинути</button>',
    '    <div class="icm-toolbar-sep"></div>',
    '    <span class="icm-hint">Тягни рамку · Scroll = зум</span>',
    '  </div>',
    '  <div class="icm-footer">',
    '    <button type="button" id="icmCancel" class="icm-btn icm-btn--sec">Скасувати</button>',
    '    <button type="button" id="icmApply"  class="icm-btn icm-btn--pri">Застосувати</button>',
    '  </div>',
    '</div>'
  ].join('');
  document.body.appendChild(modal);

  var st = document.createElement('style');
  st.textContent = [
    '#imgCropModal{display:none;position:fixed;inset:0;z-index:9999;}',
    '#imgCropModal.open{display:flex;align-items:center;justify-content:center;}',
    '.icm-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.72);}',
    '.icm-dialog{position:relative;background:#fff;border-radius:16px;width:min(900px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.45);}',
    '.icm-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #f0e8df;flex-shrink:0;}',
    '.icm-title{font-size:15px;font-weight:700;color:#2c2c2a;}',
    '.icm-close{background:none;border:none;cursor:pointer;color:#888;padding:4px;border-radius:6px;display:flex;}',
    '.icm-close:hover{background:#f5f0ea;color:#333;}',
    '.icm-body{flex:1;overflow:hidden;background:#1a1a1a;min-height:340px;}',
    '.icm-body img{display:block;max-width:100%;}',
    '.icm-toolbar{display:flex;align-items:center;gap:6px;padding:10px 14px;border-top:1px solid #f0e8df;background:#fafaf8;flex-shrink:0;flex-wrap:wrap;}',
    '.icm-toolbar button{display:inline-flex;align-items:center;gap:4px;background:#fff;border:1.5px solid #e0d8d0;border-radius:8px;padding:6px 11px;font-size:13px;color:#555;cursor:pointer;font-family:inherit;transition:all .15s;}',
    '.icm-toolbar button:hover{border-color:#8B4513;color:#8B4513;background:#fdf8f4;}',
    '#icmZoomIn,#icmZoomOut{padding:6px 9px;}',
    '.icm-toolbar-sep{flex:1;}',
    '.icm-hint{font-size:11px;color:#bbb;}',
    '.icm-footer{display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #f0e8df;flex-shrink:0;}',
    '.icm-btn{padding:10px 24px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;border:none;font-family:inherit;transition:all .2s;}',
    '.icm-btn--sec{background:#f5f0ea;color:#555;}.icm-btn--sec:hover{background:#eae0d8;}',
    '.icm-btn--pri{background:#8B4513;color:#fff;}.icm-btn--pri:hover{background:#6d3410;}',
    /* Re-crop button inside upload-zone preview */
    '.upload-recrop{position:relative;z-index:3;display:inline-flex;align-items:center;gap:5px;margin-top:8px;background:#fff;border:1.5px solid #e0d8d0;border-radius:8px;padding:5px 12px;font-size:12px;color:#8B4513;cursor:pointer;font-family:inherit;transition:all .15s;}',
    '.upload-recrop:hover{background:#fdf8f4;border-color:#8B4513;}',
  ].join('');
  document.head.appendChild(st);

  function parseRatio(r) {
    if (!r) return NaN;
    var p = r.toString().split('/');
    return p.length === 2 ? parseFloat(p[0]) / parseFloat(p[1]) : parseFloat(p[0]);
  }

  function closeCropper() {
    modal.classList.remove('open');
    if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
    cropTarget = null;
  }

  function launchCropper(dataUrl, ratio) {
    var img = document.getElementById('icmImg');
    img.src = dataUrl;
    modal.classList.add('open');
    if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
    setTimeout(function() {
      cropperInstance = new Cropper(img, {
        aspectRatio: parseRatio(ratio),
        viewMode: 1,
        dragMode: 'move',
        background: false,
        autoCropArea: 0.95,
        responsive: true,
        checkCrossOrigin: false,
      });
    }, 80);
  }

  function openCropper(file, hiddenInput, previewEl, fileInput, ratio, livePreviewSel) {
    var reader = new FileReader();
    reader.onload = function(e) {
      cropTarget = {
        hiddenInput:    hiddenInput,
        previewEl:      previewEl,
        fileInput:      fileInput,
        ratio:          ratio,
        livePreviewSel: livePreviewSel || null,
        originalDataUrl: e.target.result,
        onApply:        null,
      };
      launchCropper(e.target.result, ratio);
    };
    reader.readAsDataURL(file);
  }

  /* Public API: open cropper with a dataUrl directly (for manual "Edit" buttons) */
  window.openImgCropper = function(dataUrl, opts) {
    opts = opts || {};
    cropTarget = {
      hiddenInput:     opts.hiddenInput || null,
      previewEl:       opts.previewEl   || null,
      fileInput:       null,
      ratio:           opts.ratio       || null,
      livePreviewSel:  opts.livePreviewSel || null,
      originalDataUrl: dataUrl,
      onApply:         opts.onApply     || null,
    };
    launchCropper(dataUrl, opts.ratio || null);
  };

  document.getElementById('icmClose').addEventListener('click',  closeCropper);
  document.getElementById('icmCancel').addEventListener('click', closeCropper);
  modal.querySelector('.icm-backdrop').addEventListener('click', closeCropper);
  document.getElementById('icmRotL').addEventListener('click',   function() { if (cropperInstance) cropperInstance.rotate(-90); });
  document.getElementById('icmRotR').addEventListener('click',   function() { if (cropperInstance) cropperInstance.rotate(90); });
  document.getElementById('icmZoomIn').addEventListener('click', function() { if (cropperInstance) cropperInstance.zoom(0.1); });
  document.getElementById('icmZoomOut').addEventListener('click',function() { if (cropperInstance) cropperInstance.zoom(-0.1); });
  document.getElementById('icmReset').addEventListener('click',  function() { if (cropperInstance) cropperInstance.reset(); });

  document.getElementById('icmApply').addEventListener('click', function() {
    if (!cropperInstance || !cropTarget) return;
    var canvas = cropperInstance.getCroppedCanvas({ maxWidth: 2400, maxHeight: 2400, imageSmoothingQuality: 'high' });
    if (!canvas) return;
    var b64 = canvas.toDataURL('image/jpeg', 0.92);

    /* Save to hidden input */
    if (cropTarget.hiddenInput) cropTarget.hiddenInput.value = b64;

    /* Update upload-zone preview + add "Переобрати" button */
    if (cropTarget.previewEl) {
      var saved = cropTarget; /* closure */
      cropTarget.previewEl.innerHTML =
        '<img src="' + b64 + '" style="max-height:110px;border-radius:8px;object-fit:cover;display:block;">' +
        '<button type="button" class="upload-recrop">' +
          '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
          'Переобрати' +
        '</button>';
      cropTarget.previewEl.style.display = 'block';
      /* Bind re-crop button */
      cropTarget.previewEl.querySelector('.upload-recrop').addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        cropTarget = saved;
        launchCropper(saved.originalDataUrl, saved.ratio);
      });
    }

    /* Update live preview element if specified */
    if (cropTarget.livePreviewSel) {
      var liveEl = document.querySelector(cropTarget.livePreviewSel);
      if (liveEl) { liveEl.src = b64; liveEl.style.display = ''; }
    }

    /* Custom onApply callback (e.g. modal photo section) */
    if (typeof cropTarget.onApply === 'function') cropTarget.onApply(b64);

    /* Clear file input so only base64 is submitted */
    if (cropTarget.fileInput) { try { cropTarget.fileInput.value = ''; } catch(e) {} }

    closeCropper();
  });

  /* Wire up every upload-zone file input */
  function bindUploadZones() {
    document.querySelectorAll('.upload-zone input[type="file"]:not([data-cropper-bound])').forEach(function(input) {
      input.setAttribute('data-cropper-bound', '1');
      input.addEventListener('change', function() {
        if (!this.files || !this.files[0]) return;
        var file           = this.files[0];
        var zone           = this.closest('.upload-zone');
        var previewEl      = zone ? zone.querySelector('.upload-preview') : null;
        var ratio          = zone ? (zone.dataset.cropRatio || null) : null;
        var livePreviewSel = zone ? (zone.dataset.livePreview || null) : null;
        var hiddenId       = this.dataset.cropHidden;
        var hiddenInp      = hiddenId ? document.getElementById(hiddenId) : null;
        var fileInput      = this;

        if (!previewEl) return;

        /* Show plain preview + "Редагувати" button — cropper opens only on click */
        var reader = new FileReader();
        reader.onload = function(e) {
          var dataUrl = e.target.result;
          previewEl.innerHTML =
            '<img src="' + dataUrl + '" style="max-height:110px;border-radius:8px;object-fit:cover;display:block;">' +
            '<button type="button" class="upload-recrop">' +
              '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
              'Редагувати' +
            '</button>';
          previewEl.style.display = 'block';

          previewEl.querySelector('.upload-recrop').addEventListener('click', function(ev) {
            ev.stopPropagation(); ev.preventDefault();
            if (window.Cropper) {
              openCropper(file, hiddenInp, previewEl, fileInput, ratio, livePreviewSel);
            }
          });
        };
        reader.readAsDataURL(file);
      });
    });
  }
  bindUploadZones();
  window.bindUploadZones = bindUploadZones;
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>

<!-- Scroll to top button -->
<button class="admin-scroll-top" id="adminScrollTop" aria-label="Вгору" title="Вгору">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
</button>

<script>

(function () {
  var btn = document.getElementById('adminScrollTop');
  if (!btn) return;
  window.addEventListener('scroll', function () {
    btn.classList.toggle('visible', window.scrollY > 300);
  }, { passive: true });
  btn.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();

</script>
</body>
</html>
