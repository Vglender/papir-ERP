<?php
// Variables from edit.php: $demand (array), $items (array), $id (int)
$isNew = empty($demand);

$statusLabels = array(
    'new'        => 'Нове',
    'assembling' => 'Збирання',
    'assembled'  => 'Зібрано',
    'shipped'    => 'Відвантажено',
    'arrived'    => 'Прибуло',
    'transfer'   => 'Транзит',
    'robot'      => 'Робот',
);
$statusColors = array(
    'new'        => 'badge-gray',
    'assembling' => 'badge-orange',
    'assembled'  => 'badge-blue',
    'shipped'    => 'badge-green',
    'arrived'    => 'badge-green',
    'transfer'   => 'badge-blue',
    'robot'      => 'badge-orange',
);

function dh($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<style>
.demand-edit-wrap { max-width:1100px; margin:0 auto; padding:20px 16px; }
.demand-breadcrumb { font-size:13px; color:#6b7280; margin-bottom:14px; }
.demand-breadcrumb a { color:#1d4ed8; text-decoration:none; }
.demand-breadcrumb a:hover { text-decoration:underline; }

.demand-head-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:18px 20px; margin-bottom:16px; }
.demand-head-card.doc-editing { background:#fefce8; border-color:#fde68a; }

/* View mode */
.demand-meta { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.demand-meta-item { font-size:13px; color:#6b7280; }
.demand-meta-item strong { color:#111827; }

/* Edit mode form */
.demand-edit-form { display:none; margin-top:16px; }
.doc-editing .demand-edit-form { display:block; }
.doc-editing .demand-view-meta { display:none; }
.demand-form-grid { display:grid; grid-template-columns:180px 120px 1fr; gap:12px; align-items:start; }
.demand-form-grid label { font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
.demand-form-grid select,
.demand-form-grid input[type=datetime-local] {
    width:100%; height:34px; font-size:13px; padding:0 8px;
    border:1px solid #d1d5db; border-radius:5px; box-sizing:border-box;
}
.demand-form-grid textarea {
    width:100%; font-size:13px; padding:6px 8px;
    border:1px solid #d1d5db; border-radius:5px; box-sizing:border-box;
    resize:vertical; min-height:60px;
}

/* Action bar */
.demand-action-bar { display:flex; align-items:center; gap:8px; margin-top:14px; flex-wrap:wrap; }
.demand-save-bar { display:none; align-items:center; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid #fde68a; }
.doc-editing .demand-save-bar { display:flex; }
.doc-editing .demand-btn-edit { display:none; }

/* Items table */
.demand-items-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; }
.demand-items-card .card-head { padding:12px 16px; border-bottom:1px solid #e5e7eb; font-weight:600; font-size:14px; display:flex; align-items:center; gap:8px; }
.demand-items-table { width:100%; border-collapse:collapse; font-size:13px; }
.demand-items-table th { padding:8px 10px; text-align:left; font-size:12px; font-weight:600; color:#6b7280; border-bottom:1px solid #e5e7eb; background:#f9fafb; white-space:nowrap; }
.demand-items-table td { padding:8px 10px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
.demand-items-table tr:last-child td { border-bottom:none; }
.demand-items-table tr:hover td { background:#f9fafb; }
.demand-totals-row td { font-weight:600; background:#f9fafb; border-top:2px solid #e5e7eb !important; }
</style>

<div class="demand-edit-wrap" id="demandContainer">

    <div class="demand-breadcrumb">
        <a href="/demand">Відвантаження</a>
        <?php if (!$isNew): ?>
        &rsaquo; <?php echo dh(!empty($demand['number']) ? $demand['number'] : '#' . $id); ?>
        <?php else: ?>
        &rsaquo; Нове
        <?php endif; ?>
    </div>

    <?php if ($isNew): ?>
    <div class="card" style="padding:40px;text-align:center;color:#6b7280;">
        Відвантаження створюються в МойСклад та синхронізуються через вебхук.
    </div>
    <?php else: ?>

    <div class="demand-head-card" id="demandHeadCard">
        <!-- Title row -->
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:260px;">
                <div style="font-size:20px;font-weight:700;margin-bottom:8px;">
                    <?php echo dh($demand['number'] ?: '—'); ?>
                    <span id="statusBadge" class="badge <?php echo isset($statusColors[$demand['status']]) ? $statusColors[$demand['status']] : 'badge-gray'; ?>">
                        <?php echo isset($statusLabels[$demand['status']]) ? $statusLabels[$demand['status']] : $demand['status']; ?>
                    </span>
                </div>

                <!-- View mode meta -->
                <div class="demand-view-meta demand-meta" id="viewMeta">
                    <?php if (!empty($demand['moment'])): ?>
                    <div class="demand-meta-item">Дата: <strong id="viewMoment"><?php echo substr($demand['moment'], 0, 16); ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($demand['counterparty_name'])): ?>
                    <div class="demand-meta-item">Контрагент: <strong><?php echo dh($demand['counterparty_name']); ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($demand['order_number'])): ?>
                    <div class="demand-meta-item">
                        Замовлення:
                        <a href="/customerorder/edit?id=<?php echo (int)$demand['customerorder_id']; ?>" style="color:#1d4ed8;font-weight:600;text-decoration:none;">
                            <?php echo dh($demand['order_number']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($demand['org_name'])): ?>
                    <div class="demand-meta-item">Організація: <strong><?php echo dh($demand['org_name']); ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($demand['description'])): ?>
                    <div class="demand-meta-item" style="width:100%">
                        <span id="viewDesc"><?php echo nl2br(dh($demand['description'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Financial summary -->
            <div style="min-width:200px;text-align:right;">
                <div style="font-size:22px;font-weight:700;margin-bottom:4px;">
                    <?php echo number_format((float)$demand['sum_total'], 2, '.', ' '); ?> грн
                </div>
                <?php if ((float)$demand['sum_vat'] > 0): ?>
                <div class="text-muted fs-12">ПДВ: <?php echo number_format((float)$demand['sum_vat'], 2, '.', ' '); ?></div>
                <?php endif; ?>
                <?php if ((float)$demand['sum_paid'] > 0): ?>
                <div class="text-muted fs-12">Оплачено: <?php echo number_format((float)$demand['sum_paid'], 2, '.', ' '); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit mode form -->
        <div class="demand-edit-form" id="editForm">
            <div class="demand-form-grid">
                <div>
                    <label>Статус</label>
                    <select id="editStatus">
                        <?php foreach ($statusLabels as $sv => $sl): ?>
                        <option value="<?php echo $sv; ?>" <?php echo ($demand['status'] === $sv) ? 'selected' : ''; ?>>
                            <?php echo $sl; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Проведено</label>
                    <select id="editApplicable">
                        <option value="1" <?php echo !empty($demand['applicable']) ? 'selected' : ''; ?>>Так</option>
                        <option value="0" <?php echo empty($demand['applicable']) ? 'selected' : ''; ?>>Ні</option>
                    </select>
                </div>
                <div>
                    <label>Опис</label>
                    <textarea id="editDescription"><?php echo dh($demand['description'] ?: ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Action bar -->
        <div class="demand-action-bar">
            <button type="button" class="btn btn-ghost btn-sm demand-btn-edit" id="editBtn" onclick="enterEdit()">
                ✎ Редагувати
            </button>
            <?php if (!empty($demand['id_ms'])): ?>
            <a href="https://online.moysklad.ru/app/#demand/edit?id=<?php echo dh($demand['id_ms']); ?>"
               target="_blank" class="btn btn-ghost btn-sm">
                Відкрити в МС ↗
            </a>
            <?php endif; ?>
            <span class="text-muted fs-12" style="margin-left:auto;">
                <?php $ss = $demand['sync_state']; ?>
                <span id="syncBadge" class="badge <?php
                    $sc = array('synced'=>'badge-green','new'=>'badge-gray','changed'=>'badge-orange','error'=>'badge-red');
                    echo isset($sc[$ss]) ? $sc[$ss] : 'badge-gray';
                ?>" style="font-size:11px"><?php echo $ss; ?></span>
                <?php if (!empty($demand['sync_error'])): ?>
                <span class="text-red fs-12" title="<?php echo dh($demand['sync_error']); ?>"> ⚠ <?php echo dh(mb_substr($demand['sync_error'], 0, 60, 'UTF-8')); ?></span>
                <?php endif; ?>
            </span>
        </div>

        <!-- Save bar (visible in edit mode) -->
        <div class="demand-save-bar" id="saveBar">
            <button type="button" class="btn btn-primary btn-sm" id="saveBtn" onclick="saveChanges()">
                💾 Зберегти і синхронізувати
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="cancelEdit()">Скасувати</button>
        </div>
    </div>

    <!-- Items card -->
    <div class="demand-items-card">
        <div class="card-head">
            Позиції
            <span class="text-muted" style="font-weight:400;font-size:13px;">(<?php echo count($items); ?>)</span>
        </div>
        <table class="demand-items-table">
            <thead>
                <tr>
                    <th style="width:32px">#</th>
                    <th>Товар</th>
                    <th>Артикул</th>
                    <th style="text-align:right;width:80px">К-сть</th>
                    <th style="text-align:right;width:100px">Ціна</th>
                    <th style="text-align:right;width:70px">Знижка</th>
                    <th style="text-align:right;width:110px">Сума</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px 0;">Позиції відсутні</td></tr>
            <?php else: ?>
                <?php $totalQty = 0; $totalSum = 0; ?>
                <?php foreach ($items as $item): ?>
                <?php $totalQty += (float)$item['quantity']; $totalSum += (float)$item['sum_row']; ?>
                <tr>
                    <td class="text-muted fs-12"><?php echo (int)$item['line_no']; ?></td>
                    <td>
                        <?php $name = !empty($item['name']) ? $item['name'] : $item['product_name']; ?>
                        <?php if (!empty($item['product_id'])): ?>
                        <a href="/catalog?search=<?php echo (int)$item['product_id']; ?>" target="_blank"
                           style="color:#111827;text-decoration:none;">
                            <?php echo dh($name ?: '—'); ?>
                        </a>
                        <?php else: ?>
                        <?php echo dh($name ?: '—'); ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted fs-12"><?php echo dh($item['article'] ?: $item['sku'] ?: '—'); ?></td>
                    <td style="text-align:right"><?php echo rtrim(rtrim(number_format((float)$item['quantity'], 3, '.', ''), '0'), '.'); ?></td>
                    <td style="text-align:right"><?php echo number_format((float)$item['price'], 2, '.', ' '); ?></td>
                    <td style="text-align:right">
                        <?php echo (float)$item['discount_percent'] > 0 ? number_format((float)$item['discount_percent'], 1) . '%' : '—'; ?>
                    </td>
                    <td style="text-align:right;font-weight:500"><?php echo number_format((float)$item['sum_row'], 2, '.', ' '); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="demand-totals-row">
                    <td colspan="3" style="text-align:right;color:#6b7280;">Разом:</td>
                    <td style="text-align:right"><?php echo rtrim(rtrim(number_format($totalQty, 3, '.', ''), '0'), '.'); ?></td>
                    <td></td><td></td>
                    <td style="text-align:right"><?php echo number_format($totalSum, 2, '.', ' '); ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php endif; // !isNew ?>
</div>

<script>
var DEMAND_ID = <?php echo $id; ?>;
var statusLabels = <?php echo json_encode($statusLabels); ?>;
var statusColors = <?php echo json_encode($statusColors); ?>;

function enterEdit() {
    document.getElementById('demandHeadCard').classList.add('doc-editing');
}

function cancelEdit() {
    document.getElementById('demandHeadCard').classList.remove('doc-editing');
}

function saveChanges() {
    var btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.textContent = 'Збереження…';

    var params = new URLSearchParams();
    params.append('id',          DEMAND_ID);
    params.append('status',      document.getElementById('editStatus').value);
    params.append('applicable',  document.getElementById('editApplicable').value);
    params.append('description', document.getElementById('editDescription').value);

    fetch('/demand/api/save', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    params.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled = false;
        btn.textContent = '💾 Зберегти і синхронізувати';

        if (!res.ok) {
            showToast('Помилка: ' + (res.error || 'невідома'), true);
            return;
        }

        // Update UI from response
        var d = res.demand || {};

        // Status badge
        var st = d.status || document.getElementById('editStatus').value;
        var badge = document.getElementById('statusBadge');
        badge.textContent  = statusLabels[st] || st;
        badge.className    = 'badge ' + (statusColors[st] || 'badge-gray');

        // Sync badge
        var ss = d.sync_state || 'synced';
        var ssMap = { synced:'badge-green', new:'badge-gray', changed:'badge-orange', error:'badge-red' };
        var sb = document.getElementById('syncBadge');
        sb.textContent = ss;
        sb.className   = 'badge ' + (ssMap[ss] || 'badge-gray');
        sb.style.fontSize = '11px';

        cancelEdit();
        showToast('Збережено та синхронізовано з МС ✓');
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = '💾 Зберегти і синхронізувати';
        showToast('Помилка мережі', true);
    });
}
</script>