<?php
$selId  = $selected;
$selTpl = $tpl;

// Group templates by type
$byType = array();
foreach ($templates as $t) {
    $byType[$t['type_code']][] = $t;
}

$statusLabels = array(
    'draft'    => array('label' => 'Чернетка', 'class' => 'badge-gray'),
    'active'   => array('label' => 'Активний', 'class' => 'badge-green'),
    'archived' => array('label' => 'Архів',    'class' => 'badge-orange'),
);
?>
<style>
/* ── Page layout ──────────────────────────────────────────────────────────── */
.tpl-page       { display: flex; flex: 1; min-height: 0; overflow: hidden; }

/* ── Sidebar ──────────────────────────────────────────────────────────────── */
.tpl-sidebar    { width: 240px; flex-shrink: 0; display: flex; flex-direction: column;
                  border-right: 1px solid #e5e7eb; background: #fff; overflow: hidden;
                  transition: width .2s; }
.tpl-sidebar.collapsed { width: 0; }

.tpl-sidebar-head { display: flex; align-items: center; gap: 8px;
                    padding: 12px 14px 10px; border-bottom: 1px solid #f1f3f6; flex-shrink: 0; }
.tpl-sidebar-head h2 { margin: 0; font-size: 13px; font-weight: 700; color: #1e293b; flex: 1; }
.tpl-sidebar-head .btn { height: 28px; padding: 0 10px; font-size: 12px; }

.tpl-sidebar-body { flex: 1; overflow-y: auto; }
.tpl-type-group { border-bottom: 1px solid #f1f3f6; }
.tpl-type-head  { padding: 8px 12px 4px; font-size: 10px; font-weight: 700;
                  letter-spacing: .08em; color: #94a3b8; text-transform: uppercase; }
.tpl-row        { display: flex; align-items: center; gap: 6px; padding: 7px 12px;
                  cursor: pointer; font-size: 12.5px; }
.tpl-row:hover  { background: #f8fafc; }
.tpl-row.row-selected { background: #eef6ff; }
.tpl-row-name   { flex: 1; font-weight: 500; color: #1e293b; white-space: nowrap;
                  overflow: hidden; text-overflow: ellipsis; }
.tpl-row-ver    { font-size: 10px; color: #94a3b8; flex-shrink: 0; }
.tpl-empty-list { padding: 20px 12px; font-size: 13px; color: #94a3b8; }

/* ── Main editor area ────────────────────────────────────────────────────── */
.tpl-main       { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #f8fafc; min-width: 0; }

.tpl-topbar     { display: flex; align-items: center; gap: 8px; padding: 10px 16px;
                  background: #fff; border-bottom: 1px solid #e5e7eb; flex-shrink: 0; }
.tpl-topbar h1  { margin: 0; font-size: 15px; font-weight: 700; flex-shrink: 0; }
.tpl-toggle-btn { display: flex; align-items: center; justify-content: center;
                  width: 30px; height: 30px; border: 1px solid #e5e7eb; background: #fff;
                  border-radius: 6px; cursor: pointer; color: #64748b; flex-shrink: 0; }
.tpl-toggle-btn:hover { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
.tpl-topbar .btn { height: 30px; padding: 0 12px; font-size: 13px; }
</style>

<div class="tpl-page">

    <!-- ── Sidebar ──────────────────────────────────────────────────── -->
    <div class="tpl-sidebar" id="tplSidebar">
        <div class="tpl-sidebar-head">
            <h2>Шаблони</h2>
            <button class="btn btn-primary" id="tplAddBtn" type="button" title="Новий шаблон">+</button>
        </div>
        <div class="tpl-sidebar-body">
            <?php if (empty($templates)): ?>
                <div class="tpl-empty-list">Шаблонів ще немає</div>
            <?php else: ?>
            <?php foreach ($types as $type):
                if (!isset($byType[$type['code']])) { continue; }
            ?>
            <div class="tpl-type-group">
                <div class="tpl-type-head"><?php echo ViewHelper::h($type['name']); ?></div>
                <?php foreach ($byType[$type['code']] as $t):
                    $stInfo = isset($statusLabels[$t['status']]) ? $statusLabels[$t['status']] : array('label'=>$t['status'],'class'=>'badge-gray');
                ?>
                <div class="tpl-row <?php echo $t['id'] == $selId ? 'row-selected' : ''; ?>"
                     onclick="location.href='/print/templates?selected=<?php echo (int)$t['id']; ?>'">
                    <div class="tpl-row-name" title="<?php echo ViewHelper::h($t['name']); ?>"><?php echo ViewHelper::h($t['name']); ?></div>
                    <span class="tpl-row-ver">v<?php echo (int)$t['version']; ?></span>
                    <span class="badge <?php echo $stInfo['class']; ?>" style="font-size:9px;padding:1px 5px"><?php echo $stInfo['label']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Main ─────────────────────────────────────────────────────── -->
    <div class="tpl-main">
        <div class="tpl-topbar">
            <button class="tpl-toggle-btn" id="tplSidebarToggle" title="Сховати/показати список">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                    <rect x="2" y="2" width="4" height="12" rx="1" fill="currentColor" opacity=".4"/>
                    <rect x="8" y="2" width="6" height="3" rx="1" fill="currentColor"/>
                    <rect x="8" y="6.5" width="6" height="3" rx="1" fill="currentColor"/>
                    <rect x="8" y="11" width="6" height="3" rx="1" fill="currentColor"/>
                </svg>
            </button>
            <h1>Редактор шаблонів</h1>
        </div>

        <div id="tplEditorWrap" style="flex:1;overflow:hidden;display:flex;flex-direction:column;min-height:0">
            <?php if ($selTpl): ?>
            <?php $tplData = $selTpl; include __DIR__ . '/templates_editor.php'; ?>
            <?php else: ?>
            <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:15px">
                Оберіть шаблон зі списку або натисніть&nbsp;<strong>+</strong>&nbsp;щоб створити новий
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── New template modal ──────────────────────────────────────────────── -->
<div class="modal-overlay" id="tplNewModal" style="display:none">
    <div class="modal-box" style="max-width:420px">
        <div class="modal-head">
            <span>Новий шаблон</span>
            <button class="modal-close" type="button" onclick="document.getElementById('tplNewModal').style.display='none'">&#x2715;</button>
        </div>
        <div class="modal-body">
            <div class="org-form-row">
                <label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px">Тип документа *</label>
                <select id="newTplType" style="width:100%;font-size:13px">
                    <?php foreach ($types as $ty): ?>
                    <option value="<?php echo (int)$ty['id']; ?>"><?php echo ViewHelper::h($ty['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="org-form-row" style="margin-top:12px">
                <label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px">Назва *</label>
                <input type="text" id="newTplName" placeholder="Рахунок стандарт" style="width:100%;font-size:13px">
            </div>
            <div class="org-form-row" style="margin-top:12px">
                <label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px">Код (латиниця, без пробілів) *</label>
                <input type="text" id="newTplCode" placeholder="invoice_v1" style="width:100%;font-size:13px">
            </div>
            <div id="newTplError" class="modal-error" style="display:none;margin-top:8px"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" type="button" id="newTplSaveBtn">Створити</button>
            <button class="btn btn-ghost" type="button" onclick="document.getElementById('tplNewModal').style.display='none'">Скасувати</button>
        </div>
    </div>
</div>

<script>
(function () {
    // Sidebar toggle
    var sidebar = document.getElementById('tplSidebar');
    document.getElementById('tplSidebarToggle').addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
    });

    // Open new modal
    document.getElementById('tplAddBtn').addEventListener('click', function () {
        document.getElementById('tplNewModal').style.display = 'flex';
        setTimeout(function () { document.getElementById('newTplName').focus(); }, 50);
    });

    // Auto-generate code from name
    document.getElementById('newTplName').addEventListener('input', function () {
        var code = this.value.toLowerCase()
            .replace(/[їіє]/g, function (c) { return {ї:'yi',і:'i',є:'ye'}[c] || c; })
            .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
        document.getElementById('newTplCode').value = code || '';
    });

    // Create template
    document.getElementById('newTplSaveBtn').addEventListener('click', function () {
        var typeId = document.getElementById('newTplType').value;
        var name   = document.getElementById('newTplName').value.trim();
        var code   = document.getElementById('newTplCode').value.trim();
        var errEl  = document.getElementById('newTplError');
        if (!name || !code) {
            errEl.textContent = 'Заповніть всі поля';
            errEl.style.display = 'block';
            return;
        }
        var params = new URLSearchParams({
            type_id: typeId, name: name, code: code,
            html_body: defaultInvoiceHtml()
        });
        fetch('/print/api/save_template', { method: 'POST', body: params })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) { errEl.textContent = d.error || 'Помилка'; errEl.style.display = 'block'; return; }
            window.location.href = '/print/templates?selected=' + d.id;
        });
    });

    function defaultInvoiceHtml() {
        return '<!DOCTYPE html>\n<html>\n<head>\n<meta charset="UTF-8">\n<style>\nbody{font-family:Arial,sans-serif;font-size:13px;color:#000;margin:20px}\n.doc-title{text-align:center;font-size:16px;font-weight:bold;margin-bottom:20px}\n.parties{display:flex;gap:40px;margin-bottom:20px}\n.party{flex:1}\n.party h4{font-size:12px;font-weight:bold;margin:0 0 6px;border-bottom:1px solid #ccc;padding-bottom:4px}\n.doc-table{width:100%;border-collapse:collapse;margin-bottom:16px}\n.doc-table th,.doc-table td{border:1px solid #ccc;padding:5px 8px;font-size:12px}\n.doc-table th{background:#f0f0f0;font-weight:bold;text-align:center}\n.total-row{text-align:right;font-weight:bold;margin-bottom:4px}\n.sign-block{display:flex;justify-content:space-between;margin-top:40px;font-size:12px}\n</style>\n</head>\n<body>\n<div class="doc-title">РАХУНОК-ФАКТУРА № {{invoice.number}} від {{invoice.date}}</div>\n<div class="parties">\n  <div class="party">\n    <h4>ПОСТАЧАЛЬНИК</h4>\n    <div>{{seller.name}}</div>\n    <div>ЄДРПОУ: {{seller.okpo}}</div>\n    <div>{{seller.address}}</div>\n    <div>Тел: {{seller.phone}}</div>\n    <div>IBAN: {{seller.iban}}</div>\n    <div>{{seller.bank_name}}, МФО {{seller.mfo}}</div>\n  </div>\n  <div class="party">\n    <h4>ПОКУПЕЦЬ</h4>\n    <div>{{buyer.name}}</div>\n    <div>ЄДРПОУ: {{buyer.okpo}}</div>\n    <div>{{buyer.address}}</div>\n  </div>\n</div>\n<table class="doc-table">\n  <thead>\n    <tr>\n      <th style="width:32px">№</th>\n      <th>Найменування</th>\n      <th style="width:50px">Од.</th>\n      <th style="width:70px">Кількість</th>\n      <th style="width:90px">Ціна, грн</th>\n      <th style="width:100px">Сума, грн</th>\n    </tr>\n  </thead>\n  <tbody>\n    {{#lines}}\n    <tr>\n      <td style="text-align:center">{{num}}</td>\n      <td>{{description}}</td>\n      <td style="text-align:center">{{unit}}</td>\n      <td style="text-align:right">{{qty}}</td>\n      <td style="text-align:right">{{price}}</td>\n      <td style="text-align:right">{{total}}</td>\n    </tr>\n    {{/lines}}\n  </tbody>\n</table>\n<div class="total-row">Сума без ПДВ: {{total}} грн</div>\n<div class="total-row">ПДВ {{vat_rate}}%: {{vat_amount}} грн</div>\n<div class="total-row" style="font-size:14px">Всього до сплати: <strong>{{total_with_vat}} грн</strong></div>\n<p style="font-size:12px;margin-top:8px">{{total_text}}</p>\n<div class="sign-block">\n  <div>{{seller.director_title}}: ____________ {{seller.director_name}}</div>\n  <div>МП</div>\n</div>\n</body>\n</html>';
    }
}());
</script>