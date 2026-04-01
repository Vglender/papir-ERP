<?php
// Variables from index.php: $rows, $total, $totalPages, $page, $limit,
//   $search, $orgId, $status, $dateFrom, $dateTo, $orgs, $filters

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
$syncColors = array(
    'synced'  => 'badge-green',
    'new'     => 'badge-gray',
    'changed' => 'badge-orange',
    'error'   => 'badge-red',
);

function demand_url($extra = array()) {
    $q = array_merge($_GET, $extra);
    foreach ($q as $k => $v) { if ($v === '' || $v === null || $v === 0 || $v === '0') unset($q[$k]); }
    $qs = http_build_query($q);
    return '/demand' . ($qs ? '?' . $qs : '');
}
?>
<style>
.demand-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.demand-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.demand-search-wrap { flex:1; min-width:160px; }
.demand-toolbar .btn        { height:34px; padding:0 12px; }
.demand-toolbar .chip-input { min-height:34px; max-height:34px; overflow:hidden; }
.demand-filter-dates { display:flex; align-items:center; gap:6px; }
.demand-filter-dates input[type=date] { height:28px; font-size:13px; padding:0 6px; border:1px solid #d1d5db; border-radius:5px; }
.demand-num-link { color:#1d4ed8; text-decoration:none; font-weight:600; }
.demand-num-link:hover { text-decoration:underline; }
</style>

<div class="page-wrap">

    <!-- Toolbar -->
    <form method="get" action="/demand" id="demandForm">
        <input type="hidden" name="page" value="1">
        <div class="demand-toolbar">
            <h1>Відвантаження</h1>
            <div class="demand-search-wrap">
                <div class="chip-input" id="demandChipBox">
                    <input type="text" class="chip-typer" id="demandChipTyper"
                           placeholder="ID, номер, контрагент…" autocomplete="off">
                    <div class="chip-actions">
                        <button type="button" class="chip-act-btn chip-act-clear hidden" id="demandChipClear" title="Очистити">&#x2715;</button>
                        <button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="search" id="demandSearchHidden" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <!-- Organization -->
            <div class="filter-bar-group">
                <span class="filter-bar-label">Організація</span>
                <select name="organization_id" onchange="this.form.submit()" style="height:28px;font-size:13px;padding:0 6px;border:1px solid #d1d5db;border-radius:5px;">
                    <option value="0">Всі</option>
                    <?php foreach ($orgs as $org): ?>
                    <option value="<?php echo (int)$org['id']; ?>" <?php echo ($orgId == $org['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($org['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-bar-sep"></div>
            <!-- Status pills -->
            <div class="filter-bar-group">
                <span class="filter-bar-label">Статус</span>
                <?php foreach ($statusLabels as $sv => $sl): ?>
                <label class="filter-pill <?php echo in_array($sv, $status) ? 'active' : ''; ?>">
                    <input type="checkbox" name="status[]" value="<?php echo $sv; ?>"
                           <?php echo in_array($sv, $status) ? 'checked' : ''; ?>
                           onchange="this.form.submit()"> <?php echo $sl; ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="filter-bar-sep"></div>
            <!-- Date range -->
            <div class="filter-bar-group demand-filter-dates">
                <span class="filter-bar-label">Дата</span>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>" onchange="this.form.submit()">
                <span class="text-muted">—</span>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>" onchange="this.form.submit()">
            </div>
            <button type="button" class="filter-bar-gear" title="Налаштування">
                <svg viewBox="0 0 16 16" fill="none" width="14" height="14"><path d="M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" stroke="currentColor" stroke-width="1.4"/><path d="M13.3 6.4l-.8-.5a5 5 0 0 0 0-1.8l.8-.5a.7.7 0 0 0 .2-.9l-.8-1.4a.7.7 0 0 0-.9-.3l-.8.5a5 5 0 0 0-1.6-.9V.7A.7.7 0 0 0 8.7 0H7.3a.7.7 0 0 0-.7.7v.9a5 5 0 0 0-1.6.9l-.8-.5a.7.7 0 0 0-.9.3L2.5 3.7a.7.7 0 0 0 .2.9l.8.5a5 5 0 0 0 0 1.8l-.8.5a.7.7 0 0 0-.2.9l.8 1.4c.2.3.6.4.9.3l.8-.5a5 5 0 0 0 1.6.9v.9c0 .4.3.7.7.7h1.4c.4 0 .7-.3.7-.7v-.9a5 5 0 0 0 1.6-.9l.8.5c.3.1.7 0 .9-.3l.8-1.4a.7.7 0 0 0-.2-.9Z" stroke="currentColor" stroke-width="1.3"/></svg>
            </button>
        </div>
    </form>

    <!-- Count -->
    <div style="font-size:13px;color:#6b7280;margin-bottom:8px;">
        Знайдено: <strong><?php echo number_format($total); ?></strong>
    </div>

    <!-- Table -->
    <table class="crm-table">
        <thead>
            <tr>
                <th style="width:60px">ID</th>
                <th>Номер</th>
                <th>Дата</th>
                <th>Контрагент</th>
                <th>Замовлення</th>
                <th>Організація</th>
                <th style="width:110px">Статус</th>
                <th style="text-align:right">Сума</th>
                <th style="width:80px">Синх.</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="9" style="text-align:center;color:#9ca3af;padding:32px 0;">Записів не знайдено</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr style="cursor:pointer" onclick="window.location='/demand/edit?id=<?php echo (int)$row['id']; ?>'">
                <td class="text-muted fs-12"><?php echo (int)$row['id']; ?></td>
                <td>
                    <a class="demand-num-link" href="/demand/edit?id=<?php echo (int)$row['id']; ?>" onclick="event.stopPropagation()">
                        <?php echo htmlspecialchars($row['number'] ?: '—', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </td>
                <td class="nowrap fs-12"><?php echo $row['moment'] ? substr($row['moment'], 0, 10) : '—'; ?></td>
                <td><?php echo htmlspecialchars($row['counterparty_name'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="fs-12">
                    <?php if (!empty($row['order_number'])): ?>
                    <a href="/customerorder/edit?id=<?php echo (int)$row['customerorder_id']; ?>" onclick="event.stopPropagation()" style="color:#1d4ed8;text-decoration:none;">
                        <?php echo htmlspecialchars($row['order_number'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="fs-12"><?php echo htmlspecialchars($row['org_name'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <?php $st = isset($row['status']) ? $row['status'] : 'new'; ?>
                    <span class="badge <?php echo isset($statusColors[$st]) ? $statusColors[$st] : 'badge-gray'; ?>">
                        <?php echo isset($statusLabels[$st]) ? $statusLabels[$st] : $st; ?>
                    </span>
                </td>
                <td style="text-align:right" class="nowrap">
                    <?php echo number_format((float)$row['sum_total'], 2, '.', ' '); ?>
                </td>
                <td>
                    <?php $ss = isset($row['sync_state']) ? $row['sync_state'] : 'new'; ?>
                    <span class="badge <?php echo isset($syncColors[$ss]) ? $syncColors[$ss] : 'badge-gray'; ?>" style="font-size:11px">
                        <?php echo $ss; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:16px;">
        <?php if ($page > 1): ?>
            <a href="<?php echo demand_url(array('page' => $page - 1)); ?>">&laquo;</a>
        <?php endif; ?>
        <?php
        $pStart = max(1, $page - 3);
        $pEnd   = min($totalPages, $page + 3);
        for ($p = $pStart; $p <= $pEnd; $p++):
        ?>
            <?php if ($p == $page): ?>
                <span class="current"><?php echo $p; ?></span>
            <?php else: ?>
                <a href="<?php echo demand_url(array('page' => $p)); ?>"><?php echo $p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?php echo demand_url(array('page' => $page + 1)); ?>">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script src="/modules/shared/chip-search.js?v=<?php echo @filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script>
(function () {
    ChipSearch.init('demandChipBox', 'demandChipTyper', 'demandSearchHidden',
        document.getElementById('demandForm'), {noComma: false});

    // Clear button
    var clearBtn = document.getElementById('demandChipClear');
    var chipBox  = document.getElementById('demandChipBox');
    var typer    = document.getElementById('demandChipTyper');
    var hidden   = document.getElementById('demandSearchHidden');
    var form     = document.getElementById('demandForm');

    function updateClearBtn() {
        var hasChips = chipBox.querySelectorAll('.chip').length > 0;
        var hasText  = typer.value.trim() !== '';
        if (hasChips || hasText) { clearBtn.classList.remove('hidden'); }
        else                     { clearBtn.classList.add('hidden'); }
    }
    var observer = new MutationObserver(updateClearBtn);
    observer.observe(chipBox, { childList: true });
    typer.addEventListener('input', updateClearBtn);

    clearBtn.addEventListener('click', function () {
        chipBox.querySelectorAll('.chip').forEach(function(c) { c.remove(); });
        typer.value = ''; hidden.value = '';
        clearBtn.classList.add('hidden');
        var pi = form.querySelector('input[name="page"]'); if (pi) pi.value = 1;
        form.submit();
    });
    updateClearBtn();
}());
</script>