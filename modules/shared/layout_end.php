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

<style>
/* ── Global chat panel ───────────────────────────────────────────────────── */
#globalChatPanel { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.gc-hdr { display:flex; align-items:center; padding:0 14px; height:42px; border-bottom:1px solid #f0f0f0; flex-shrink:0; }
.gc-hdr-title { flex:1; font-size:13px; font-weight:700; color:#1a1a1a; }
.gc-hdr-close { border:none; background:none; cursor:pointer; color:#9ca3af; font-size:20px; line-height:1; padding:0 0 2px; transition:color .15s; }
.gc-hdr-close:hover { color:#374151; }

.gc-body { display:flex; flex:1; min-height:0; overflow:hidden; }

/* Left sidebar nav */
.gc-nav { width:50px; border-right:1px solid #f0f0f0; background:#f9fafb; display:flex; flex-direction:column; align-items:center; padding:8px 0; gap:2px; overflow-y:auto; flex-shrink:0; }
.gc-nav::-webkit-scrollbar { width:2px; }
.gc-nav::-webkit-scrollbar-thumb { background:#e5e7eb; }
.gc-nav-item { position:relative; width:36px; height:36px; border-radius:10px; border:none; background:transparent; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#6b7280; transition:background .12s,color .12s; flex-shrink:0; }
.gc-nav-item:hover:not(.active) { background:#f3f4f6; color:#374151; }
.gc-nav-item.active { background:#ede9fe; color:#7c3aed; }
.gc-nav-avatar { width:28px; height:28px; border-radius:50%; background:#e0e7ff; color:#4338ca; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; line-height:1; transition:background .12s,color .12s; }
.gc-nav-item.active .gc-nav-avatar { background:#7c3aed; color:#fff; }
.gc-nav-badge { position:absolute; top:1px; right:1px; min-width:14px; height:14px; border-radius:7px; background:#ef4444; color:#fff; font-size:9px; font-weight:700; padding:0 3px; line-height:14px; text-align:center; box-sizing:border-box; pointer-events:none; }
.gc-nav-sep { width:26px; height:1px; background:#e5e7eb; margin:4px 0; flex-shrink:0; }

/* Main area */
.gc-main { flex:1; display:flex; flex-direction:column; min-width:0; overflow:hidden; }

/* Input */
.gc-input-area { border-top:1px solid #f0f0f0; padding:7px 10px 8px; flex-shrink:0; display:flex; align-items:flex-end; gap:6px; background:#fff; }
.gc-input-area textarea { flex:1; border:1px solid #e5e7eb; border-radius:8px; padding:7px 10px; font-size:12px; font-family:inherit; resize:none; outline:none; line-height:1.45; box-sizing:border-box; min-height:34px; max-height:80px; }
.gc-input-area textarea:focus { border-color:#c4b5fd; }
.gc-input-send { width:32px; height:32px; border-radius:8px; border:none; background:#7c3aed; color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:background .15s; }
.gc-input-send:hover { background:#6d28d9; }
.gc-input-send:disabled { background:#d1d5db; cursor:not-allowed; }

/* ── Forward picker popup ─────────────────────────────────────────────────── */
.fwd-picker { position:fixed; z-index:10100; background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.18),0 0 0 1px rgba(0,0,0,.06); padding:6px; min-width:210px; }
.fwd-picker-title { font-size:10px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.5px; padding:4px 10px 6px; }
.fwd-picker-subtitle { font-size:10px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.5px; padding:6px 10px 4px; }
.fwd-picker-item { display:flex; align-items:center; gap:10px; width:100%; border:none; background:transparent; cursor:pointer; padding:7px 10px; border-radius:8px; font-size:13px; color:#374151; text-align:left; transition:background .1s; }
.fwd-picker-item:hover { background:#f5f3ff; }
.fwd-picker-icon { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.fwd-picker-avatar { width:30px; height:30px; border-radius:50%; background:#e0e7ff; color:#4338ca; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.fwd-picker-text { display:flex; flex-direction:column; gap:1px; }
.fwd-picker-name { font-size:13px; font-weight:500; color:#1a1a1a; line-height:1.2; }
.fwd-picker-hint { font-size:11px; color:#9ca3af; line-height:1.2; }
.fwd-picker-sep { height:1px; background:#f0f0f0; margin:4px 2px; }
</style>

<!-- ── Global team chat panel ────────────────────────────────────────────── -->
<div id="globalChatPanel" style="display:none;position:fixed;bottom:0;right:72px;width:360px;height:500px;background:#fff;border-radius:12px 12px 0 0;box-shadow:0 -4px 32px rgba(0,0,0,.14),0 0 0 1px rgba(0,0,0,.06);z-index:9000;flex-direction:column;overflow:hidden">
  <div class="gc-hdr">
    <span class="gc-hdr-title" id="tcTitle">Загальний чат</span>
    <button class="gc-hdr-close" onclick="GlobalChat.close()" title="Закрити">&#x2715;</button>
  </div>
  <div class="gc-body">
    <!-- Sidebar nav: general + employees -->
    <div class="gc-nav" id="tcTabs"></div>
    <!-- Messages + input -->
    <div class="gc-main">
      <div class="tc-msgs" id="tcMsgs" style="flex:1;overflow-y:auto;padding:10px 12px;display:flex;flex-direction:column;gap:6px;background:#fafafa"></div>
      <div id="tcFwdStrip" class="tc-fwd-strip" style="display:none">
        <div class="tc-fwd-strip-bar"></div>
        <div class="tc-fwd-strip-text"></div>
        <button type="button" class="tc-fwd-strip-close" onclick="TeamChat.clearFwd()">&#x2715;</button>
      </div>
      <div class="gc-input-area">
        <textarea id="tcInput" placeholder="Повідомлення…" rows="1"></textarea>
        <button class="gc-input-send" id="tcSendBtn" onclick="TeamChat.send()" title="Надіслати">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2" fill="currentColor" stroke="none"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<script src="/modules/shared/team-chat.js?v=<?php echo filemtime(__DIR__ . '/team-chat.js'); ?>"></script>
<script>
var GlobalChat = {
  _inited: false,
  _pendingFwd: null,

  toggle: function() {
    var panel = document.getElementById('globalChatPanel');
    if (!panel) return;
    var isOpen = panel.style.display === 'flex';
    panel.style.display = isOpen ? 'none' : 'flex';
    if (!isOpen) this._ensureInited();
  },

  close: function() {
    var panel = document.getElementById('globalChatPanel');
    if (panel) panel.style.display = 'none';
  },

  // Open panel, switch to mode, optionally pre-fill fwd
  openWith: function(mode, empId, fwdData) {
    var panel = document.getElementById('globalChatPanel');
    if (!panel) return;
    panel.style.display = 'flex';
    this._pendingFwd = fwdData || null;
    var self = this;
    if (!this._inited) {
      this._ensureInited(function() {
        self._applyMode(mode, empId);
        if (self._pendingFwd) { TeamChat.openFwdToTeam(self._pendingFwd); self._pendingFwd = null; }
      });
    } else {
      this._applyMode(mode, empId);
      if (fwdData) TeamChat.openFwdToTeam(fwdData);
    }
  },

  _applyMode: function(mode, empId) {
    if (mode === 'dm' && empId) {
      var emp = (TeamChat.employees || []).filter(function(e){ return e.id === empId; })[0];
      TeamChat._switchGlobalMode('dm', empId, emp ? emp.name : '');
    } else {
      TeamChat._switchGlobalMode('general', null, null);
    }
  },

  _ensureInited: function(cb) {
    if (this._inited) { if (cb) cb(); return; }
    this._inited = true;
    TeamChat.initGlobal({
      onUnreadChange: function(n) {
        var badge = document.getElementById('globalChatBadge');
        if (!badge) return;
        badge.textContent = n > 0 ? n : '';
        badge.classList.toggle('visible', n > 0);
      }
    });
    if (cb) {
      // initGlobal is async (fetch), wait a bit for employees to load
      setTimeout(cb, 600);
    }
  }
};

// Poll unread badge even when panel is closed
(function() {
  function pollBadge() {
    fetch('/counterparties/api/get_team_state')
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        var n = (d.general_unread || 0) + Object.keys(d.dm_unread || {}).reduce(function(s,k){ return s+(d.dm_unread[k]||0); }, 0);
        var badge = document.getElementById('globalChatBadge');
        if (!badge) return;
        badge.textContent = n > 0 ? n : '';
        badge.classList.toggle('visible', n > 0);
      }).catch(function(){});
  }
  pollBadge();
  setInterval(pollBadge, 15000);
}());
</script>
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
<script>
// Print queue badge
(function() {
    var link = document.getElementById('navPrintQueueLink');
    if (!link) return;
    fetch('/print/api/queue_count')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok || !d.count) return;
            var b = document.createElement('span');
            b.style.cssText = 'display:inline-block;background:#ef4444;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;line-height:16px;text-align:center;border-radius:8px;margin-left:5px;padding:0 4px;';
            b.textContent = d.count;
            link.appendChild(b);
        });
}());
</script>
</body>
</html>