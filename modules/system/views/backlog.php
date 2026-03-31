<?php
$r = Database::fetchAll('Papir',
    "SELECT id, module, type, text, screenshot, created_at
     FROM backlog WHERE resolved_at IS NULL
     ORDER BY module, type, created_at"
);
$items = ($r['ok'] && $r['rows']) ? $r['rows'] : array();

$grouped = array();
foreach ($items as $item) {
    $grouped[$item['module']][] = $item;
}

$moduleOptions = array('general','catalog','prices','customerorder','counterparties','finance','merchant','auth','system');
$typeOptions   = array('bug' => '🐛 bug', 'plan' => '📋 plan', 'idea' => '💡 idea');
?>
<style>
.bl-row td { vertical-align: middle; }
.bl-row .bl-view { display: flex; align-items: center; gap: 6px; }
.bl-row .bl-edit-form { display: none; align-items: center; gap: 6px; flex-wrap: wrap; }
.bl-row.editing .bl-view { display: none; }
.bl-row.editing .bl-edit-form { display: flex; }
.bl-edit-text { flex: 1; min-width: 200px; height: 32px; padding: 0 8px;
    border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; font-family: inherit; }
.bl-edit-sel { height: 32px; padding: 0 6px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
.bl-actions { display: flex; gap: 4px; justify-content: flex-end; white-space: nowrap; }
</style>

<div class="page-wrap">

  <div class="page-head">
    <h1>Бэклог</h1>
    <span class="text-muted" style="font-size:13px;margin-left:8px"><?php echo count($items); ?> відкритих</span>
  </div>

  <!-- Форма добавления -->
  <div class="card" style="margin-bottom:20px;padding:16px">
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Модуль</label>
        <select id="blModule" style="height:34px;padding:0 8px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">
          <?php foreach ($moduleOptions as $m): ?>
          <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Тип</label>
        <select id="blType" style="height:34px;padding:0 8px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">
          <?php foreach ($typeOptions as $val => $lbl): ?>
          <option value="<?php echo $val; ?>"><?php echo $lbl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1;min-width:240px">
        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Опис</label>
        <input type="text" id="blText" placeholder="Що потрібно зробити або виправити…"
               style="width:100%;height:34px;padding:0 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box">
      </div>
      <button class="btn btn-primary" id="blAddBtn" style="height:34px">Додати</button>
    </div>
  </div>

  <!-- Список -->
  <?php if (empty($grouped)): ?>
    <div class="card" style="padding:32px;text-align:center;color:#9ca3af">Бэклог порожній 🎉</div>
  <?php else: ?>
    <?php
    $typeBadge = array(
        'bug'  => '<span class="badge badge-red">bug</span>',
        'plan' => '<span class="badge badge-blue">plan</span>',
        'idea' => '<span class="badge badge-orange">idea</span>',
    );
    foreach ($grouped as $module => $moduleItems): ?>
      <div class="card" style="margin-bottom:14px">
        <div style="padding:12px 16px 8px;font-weight:700;font-size:14px;border-bottom:1px solid #f3f4f6;color:#374151">
          <?php echo htmlspecialchars($module); ?>
          <span class="badge badge-gray" style="margin-left:6px"><?php echo count($moduleItems); ?></span>
        </div>
        <table class="crm-table" style="margin:0">
          <tbody>
          <?php foreach ($moduleItems as $it): ?>
            <?php $ssUrl = !empty($it['screenshot']) ? 'https://officetorg.com.ua/' . htmlspecialchars($it['screenshot']) : ''; ?>
            <tr class="bl-row" data-id="<?php echo (int)$it['id']; ?>"
                data-text="<?php echo htmlspecialchars($it['text'], ENT_QUOTES); ?>"
                data-type="<?php echo htmlspecialchars($it['type']); ?>"
                data-module="<?php echo htmlspecialchars($it['module']); ?>">
              <td style="width:80px">
                <!-- view mode -->
                <div class="bl-view">
                  <?php echo isset($typeBadge[$it['type']]) ? $typeBadge[$it['type']] : htmlspecialchars($it['type']); ?>
                </div>
                <!-- edit mode -->
                <div class="bl-edit-form">
                  <select class="bl-edit-sel bl-e-type">
                    <?php foreach ($typeOptions as $val => $lbl): ?>
                    <option value="<?php echo $val; ?>"<?php echo $it['type'] === $val ? ' selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </td>
              <td>
                <!-- view mode -->
                <div class="bl-view">
                  <?php echo htmlspecialchars($it['text']); ?>
                  <?php if ($ssUrl): ?>
                  <div style="margin-top:6px">
                    <a href="<?php echo $ssUrl; ?>" target="_blank">
                      <img src="<?php echo $ssUrl; ?>" alt="скріншот"
                           style="max-width:260px;max-height:120px;border-radius:6px;border:1px solid #e5e7eb;cursor:zoom-in">
                    </a>
                  </div>
                  <?php else: ?>
                  <div class="bl-no-screenshot" style="margin-top:4px">
                    <label style="font-size:11px;color:#d1d5db;cursor:pointer" title="Додати скріншот">
                      &#128247; <u>додати скріншот</u>
                      <input type="file" accept="image/*" class="bl-ss-upload" style="display:none">
                    </label>
                  </div>
                  <?php endif; ?>
                </div>
                <!-- edit mode -->
                <div class="bl-edit-form">
                  <input type="text" class="bl-edit-text bl-e-text" value="<?php echo htmlspecialchars($it['text'], ENT_QUOTES); ?>">
                  <select class="bl-edit-sel bl-e-module">
                    <?php foreach ($moduleOptions as $m): ?>
                    <option value="<?php echo $m; ?>"<?php echo $it['module'] === $m ? ' selected' : ''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </td>
              <td style="width:100px;color:#9ca3af;font-size:12px">
                <div class="bl-view"><?php echo substr($it['created_at'], 0, 10); ?></div>
              </td>
              <td style="width:180px">
                <div class="bl-actions">
                  <!-- view mode buttons -->
                  <button class="btn btn-ghost btn-xs bl-done-btn" data-id="<?php echo (int)$it['id']; ?>" title="Позначити як вирішене">&#10003; Вирішити</button>
                  <button class="btn btn-ghost btn-xs bl-edit-btn" title="Редагувати">&#9998;</button>
                  <button class="btn btn-ghost btn-xs bl-del-btn" style="color:#ef4444" data-id="<?php echo (int)$it['id']; ?>" title="Видалити">&#10005;</button>
                  <!-- edit mode buttons (hidden by default) -->
                  <button class="btn btn-primary btn-xs bl-save-btn" style="display:none">Зберегти</button>
                  <button class="btn btn-ghost btn-xs bl-cancel-btn" style="display:none">Скасувати</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<script>
(function() {
    // ── Add ──────────────────────────────────────────────────────────────────
    document.getElementById('blAddBtn').addEventListener('click', function() {
        var text = document.getElementById('blText').value.trim();
        if (!text) { document.getElementById('blText').focus(); return; }
        var body = 'module=' + encodeURIComponent(document.getElementById('blModule').value)
                 + '&type='  + encodeURIComponent(document.getElementById('blType').value)
                 + '&text='  + encodeURIComponent(text);
        fetch('/system/api/backlog_add', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body })
        .then(function(r){ return r.json(); }).then(function(d) {
            if (d.ok) { showToast('Додано'); setTimeout(function(){ location.reload(); }, 500); }
            else showToast('Помилка: ' + (d.error||''), true);
        });
    });
    document.getElementById('blText').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('blAddBtn').click();
    });

    // ── Row actions ──────────────────────────────────────────────────────────
    document.querySelectorAll('.bl-row').forEach(function(tr) {
        var id        = tr.dataset.id;
        var doneBtn   = tr.querySelector('.bl-done-btn');
        var editBtn   = tr.querySelector('.bl-edit-btn');
        var delBtn    = tr.querySelector('.bl-del-btn');
        var saveBtn   = tr.querySelector('.bl-save-btn');
        var cancelBtn = tr.querySelector('.bl-cancel-btn');
        var eText     = tr.querySelector('.bl-e-text');
        var eType     = tr.querySelector('.bl-e-type');
        var eMod      = tr.querySelector('.bl-e-module');

        // View-mode buttons
        var viewBtns = [doneBtn, editBtn, delBtn];
        // Edit-mode buttons
        var editBtns = [saveBtn, cancelBtn];

        function enterEdit() {
            tr.classList.add('editing');
            viewBtns.forEach(function(b){ b.style.display = 'none'; });
            editBtns.forEach(function(b){ b.style.display = ''; });
            eText.focus();
            eText.select();
        }
        function exitEdit() {
            tr.classList.remove('editing');
            viewBtns.forEach(function(b){ b.style.display = ''; });
            editBtns.forEach(function(b){ b.style.display = 'none'; });
        }

        // Done
        doneBtn.addEventListener('click', function() {
            fetch('/system/api/backlog_done', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id='+id })
            .then(function(r){ return r.json(); }).then(function(d) {
                if (d.ok) { tr.style.opacity='0.3'; setTimeout(function(){ location.reload(); }, 400); }
                else showToast('Помилка', true);
            });
        });

        // Edit
        editBtn.addEventListener('click', enterEdit);

        // Cancel
        cancelBtn.addEventListener('click', function() {
            eText.value = tr.dataset.text;
            eType.value = tr.dataset.type;
            eMod.value  = tr.dataset.module;
            exitEdit();
        });

        // Save
        saveBtn.addEventListener('click', function() {
            var newText = eText.value.trim();
            if (!newText) { eText.focus(); return; }
            saveBtn.disabled = true;
            var body = 'id=' + id
                     + '&text='   + encodeURIComponent(newText)
                     + '&type='   + encodeURIComponent(eType.value)
                     + '&module=' + encodeURIComponent(eMod.value);
            fetch('/system/api/backlog_update', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body })
            .then(function(r){ return r.json(); }).then(function(d) {
                saveBtn.disabled = false;
                if (d.ok) { showToast('Збережено'); setTimeout(function(){ location.reload(); }, 400); }
                else showToast('Помилка: ' + (d.error||''), true);
            }).catch(function(){ saveBtn.disabled = false; showToast('Помилка', true); });
        });

        // Save on Enter
        eText.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') saveBtn.click();
            if (e.key === 'Escape') cancelBtn.click();
        });

        // Upload screenshot to existing entry
        var ssUpload = tr.querySelector('.bl-ss-upload');
        if (ssUpload) {
            ssUpload.addEventListener('change', function() {
                var file = ssUpload.files[0];
                if (!file) return;
                var fd = new FormData();
                fd.append('screenshot', file);
                fetch('/system/api/backlog_upload_screenshot', { method:'POST', body: fd })
                .then(function(r){ return r.json(); }).then(function(d) {
                    if (!d.ok) { showToast('Помилка: ' + (d.error||''), true); return; }
                    // Save path to DB
                    var body = 'id=' + id + '&screenshot=' + encodeURIComponent(d.path);
                    fetch('/system/api/backlog_update', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
                    .then(function(r){ return r.json(); }).then(function(d2) {
                        if (d2.ok) { showToast('Скріншот додано'); setTimeout(function(){ location.reload(); }, 400); }
                        else showToast('Помилка збереження', true);
                    });
                }).catch(function(){ showToast('Помилка завантаження', true); });
            });
        }

        // Delete
        delBtn.addEventListener('click', function() {
            if (!confirm('Видалити цей запис з бэклогу?')) return;
            fetch('/system/api/backlog_delete', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id='+id })
            .then(function(r){ return r.json(); }).then(function(d) {
                if (d.ok) {
                    var card = tr.closest('.card');
                    tr.remove();
                    // якщо в картці більше немає рядків — прибрати всю картку
                    if (card && card.querySelectorAll('tbody tr').length === 0) {
                        card.remove();
                    }
                    showToast('Видалено');
                } else {
                    showToast('Помилка', true);
                }
            });
        });
    });
}());
</script>