<?php
/**
 * Shared page layout — body close.
 * Підключати в кінці кожного view.
 */
?>
<footer class="app-footer">Papir ERP &mdash; <?php echo date('Y'); ?></footer>
<!-- Backlog quick-add modal -->
<div id="blModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.4);align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:500px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.2)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <span style="font-weight:700;font-size:16px">&#128203; Бэклог</span>
      <button id="blModalClose" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9ca3af;line-height:1">&#215;</button>
    </div>
    <div style="display:flex;gap:8px;margin-bottom:10px">
      <select id="blQModule" style="flex:1;height:34px;padding:0 8px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">
        <option value="general">general</option>
        <option value="catalog">catalog</option>
        <option value="prices">prices</option>
        <option value="customerorder">customerorder</option>
        <option value="counterparties">counterparties</option>
        <option value="finance">finance</option>
        <option value="merchant">merchant</option>
        <option value="auth">auth</option>
        <option value="system">system</option>
      </select>
      <select id="blQType" style="width:120px;height:34px;padding:0 8px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">
        <option value="bug">&#128027; bug</option>
        <option value="plan">&#128203; plan</option>
        <option value="idea">&#128161; idea</option>
      </select>
    </div>
    <textarea id="blQText" rows="3" placeholder="&#1054;&#1087;&#1080;&#1096;&#1080; &#1073;&#1072;&#1075;, &#1110;&#1076;&#1077;&#1102; &#1072;&#1073;&#1086; &#1087;&#1083;&#1072;&#1085;&#8230;"
      style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;resize:vertical;box-sizing:border-box;font-family:inherit;margin-bottom:10px"></textarea>

    <!-- Screenshot drop zone -->
    <div id="blDropZone" style="border:2px dashed #d1d5db;border-radius:8px;padding:12px 10px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;margin-bottom:12px;font-size:13px;color:#9ca3af">
      <span id="blDropLabel">&#128247; Скріншот — вставте Ctrl+V, перетягніть або <u>виберіть файл</u></span>
      <img id="blScreenshotPreview" src="" alt="" style="display:none;max-width:100%;max-height:160px;border-radius:6px;margin-top:8px">
      <button id="blScreenshotRemove" type="button" style="display:none;margin-top:6px;background:none;border:none;color:#ef4444;cursor:pointer;font-size:12px">&#10005; Прибрати</button>
      <input type="file" id="blScreenshotFile" accept="image/*" style="display:none">
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button id="blModalClose2" class="btn btn-ghost">&#1057;&#1082;&#1072;&#1089;&#1091;&#1074;&#1072;&#1090;&#1080;</button>
      <button id="blQSave" class="btn btn-primary">&#1047;&#1073;&#1077;&#1088;&#1077;&#1075;&#1090;&#1080;</button>
    </div>
  </div>
</div>
<div class="toast" id="_blGlobalToast"></div>
<script>
// Global showToast fallback — used on pages that don't define their own
if (typeof window.showToast !== 'function') {
    window.showToast = function(msg, isError) {
        var t = document.getElementById('_blGlobalToast');
        if (!t) return;
        t.textContent = msg;
        t.style.background = isError ? '#dc2626' : '';
        t.classList.add('show');
        clearTimeout(t._toastTimer);
        t._toastTimer = setTimeout(function() { t.classList.remove('show'); }, 2500);
    };
}
</script>
<script>
(function() {
    var modal      = document.getElementById('blModal');
    var btn        = document.getElementById('blQuickBtn');
    var close1     = document.getElementById('blModalClose');
    var close2     = document.getElementById('blModalClose2');
    var save       = document.getElementById('blQSave');
    var text       = document.getElementById('blQText');
    var dropZone   = document.getElementById('blDropZone');
    var dropLabel  = document.getElementById('blDropLabel');
    var preview    = document.getElementById('blScreenshotPreview');
    var removeBtn  = document.getElementById('blScreenshotRemove');
    var fileInput  = document.getElementById('blScreenshotFile');
    var modSel     = document.getElementById('blQModule');
    if (!modal || !btn) return;

    var _screenshotFile = null; // pending File object

    // ── Screenshot helpers ────────────────────────────────────────────────────
    function setScreenshot(file) {
        if (!file || !file.type.match(/^image\//)) return;
        _screenshotFile = file;
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            dropLabel.style.display = 'none';
            removeBtn.style.display = 'inline-block';
            dropZone.style.borderColor = '#6366f1';
            dropZone.style.background  = '#f5f3ff';
        };
        reader.readAsDataURL(file);
    }
    function clearScreenshot() {
        _screenshotFile = null;
        preview.src = ''; preview.style.display = 'none';
        dropLabel.style.display = ''; removeBtn.style.display = 'none';
        dropZone.style.borderColor = '#d1d5db'; dropZone.style.background = '';
        fileInput.value = '';
    }

    // Click on drop zone → open file picker
    dropZone.addEventListener('click', function(e) {
        if (e.target === removeBtn) return;
        fileInput.click();
    });
    fileInput.addEventListener('change', function() {
        if (fileInput.files[0]) setScreenshot(fileInput.files[0]);
    });
    removeBtn.addEventListener('click', function(e) { e.stopPropagation(); clearScreenshot(); });

    // Drag-and-drop
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault(); dropZone.style.borderColor = '#6366f1'; dropZone.style.background = '#f5f3ff';
    });
    dropZone.addEventListener('dragleave', function() {
        if (!_screenshotFile) { dropZone.style.borderColor = '#d1d5db'; dropZone.style.background = ''; }
    });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        var f = e.dataTransfer.files[0];
        if (f) setScreenshot(f);
    });

    // Ctrl+V — paste image from clipboard
    document.addEventListener('paste', function(e) {
        if (modal.style.display === 'none') return;
        var items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.match(/^image\//)) {
                setScreenshot(items[i].getAsFile());
                break;
            }
        }
    });

    // ── Module auto-detect ────────────────────────────────────────────────────
    var urlModule = (function() {
        var p = location.pathname;
        if (p.indexOf('/catalog') === 0 || p.indexOf('/categories') === 0 || p.indexOf('/manufacturers') === 0 || p.indexOf('/attributes') === 0) return 'catalog';
        if (p.indexOf('/prices') === 0 || p.indexOf('/action') === 0) return 'prices';
        if (p.indexOf('/customerorder') === 0) return 'customerorder';
        if (p.indexOf('/counterparties') === 0) return 'counterparties';
        if (p.indexOf('/finance') === 0 || p.indexOf('/payments') === 0) return 'finance';
        if (p.indexOf('/integr') === 0) return 'merchant';
        if (p.indexOf('/auth') === 0) return 'auth';
        if (p.indexOf('/system') === 0) return 'system';
        return 'general';
    }());
    if (modSel) modSel.value = urlModule;

    // ── Modal open/close ──────────────────────────────────────────────────────
    function openModal() {
        modal.style.display = 'flex';
        setTimeout(function() { text.focus(); }, 50);
    }
    function closeModal() {
        modal.style.display = 'none';
        text.value = '';
        clearScreenshot();
    }

    btn.addEventListener('click', openModal);
    close1.addEventListener('click', closeModal);
    close2.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'b' && !e.shiftKey) { e.preventDefault(); openModal(); }
    });
    text.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) save.click();
    });

    // ── Save ──────────────────────────────────────────────────────────────────
    save.addEventListener('click', function() {
        var t = text.value.trim();
        if (!t) { text.focus(); return; }
        save.disabled = true;

        function doSave(screenshotPath) {
            var body = 'module='     + encodeURIComponent(modSel.value)
                     + '&type='      + encodeURIComponent(document.getElementById('blQType').value)
                     + '&text='      + encodeURIComponent(t)
                     + '&screenshot='+ encodeURIComponent(screenshotPath || '');
            fetch('/system/api/backlog_add', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body })
            .then(function(r){ return r.json(); }).then(function(d) {
                save.disabled = false;
                if (d.ok) { closeModal(); showToast('Збережено в бэклог ✓'); }
                else showToast('Помилка: ' + (d.error||''), true);
            }).catch(function() { save.disabled = false; showToast('Помилка', true); });
        }

        if (_screenshotFile) {
            var fd = new FormData();
            fd.append('screenshot', _screenshotFile);
            fetch('/system/api/backlog_upload_screenshot', { method:'POST', body: fd })
            .then(function(r){ return r.json(); }).then(function(d) {
                if (d.ok) { doSave(d.path); }
                else { save.disabled = false; showToast('Помилка завантаження скріншоту: ' + (d.error||''), true); }
            }).catch(function() { save.disabled = false; showToast('Помилка завантаження', true); });
        } else {
            doSave('');
        }
    });
}());
</script>
</body>
</html>