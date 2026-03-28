<?php require_once __DIR__ . '/../../shared/layout.php'; ?>
<style>
.fin-wrap { max-width:1400px; margin:0 auto; padding:20px 16px 40px; }

/* Toolbar */
.fin-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.fin-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.fin-search-wrap { flex:1; min-width:160px; }
.fin-toolbar .btn        { height:34px; padding:0 12px; flex-shrink:0; }
.fin-toolbar .chip-input { min-height:34px; max-height:34px; overflow:hidden; }

/* Summary strip */
.fin-summary {
    display:flex; gap:12px; margin-bottom:10px;
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--radius); padding:10px 16px;
    flex-wrap:wrap;
}
.fin-sum-item { display:flex; flex-direction:column; gap:1px; min-width:120px; }
.fin-sum-label { font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.3px; }
.fin-sum-value { font-size:16px; font-weight:700; font-variant-numeric:tabular-nums; }
.fin-sum-value.green { color:var(--green); }
.fin-sum-value.red   { color:var(--red); }
.fin-sum-value.blue  { color:var(--blue); }
.fin-sum-sep { width:1px; background:var(--border); align-self:stretch; margin:0 4px; }

/* Table */
.fin-table-wrap { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.fin-table-wrap .crm-table td { vertical-align:top; }
.fin-moment { font-size:12px; white-space:nowrap; }
.fin-moment-date { font-weight:600; }
.fin-moment-time { color:var(--text-muted); }
.fin-doc { font-size:12px; color:var(--text-muted); font-family:monospace; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.fin-cp-name { font-weight:600; font-size:13px; }
.fin-cp-none { color:var(--text-muted); font-size:12px; }
.fin-desc { font-size:12px; color:var(--text-muted); max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.fin-sum-cell { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; font-weight:600; font-size:13px; }
.fin-sum-cell.in  { color:var(--green); }
.fin-sum-cell.out { color:var(--red); }
.fin-badge-in  { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:8px; font-size:11px; font-weight:600; background:#dcfce7; color:#166534; }
.fin-badge-out { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:8px; font-size:11px; font-weight:600; background:#fee2e2; color:#991b1b; }
</style>

<div class="fin-wrap">

    <!-- Toolbar -->
    <form method="get" action="/finance/cash" id="finForm">
        <div class="fin-toolbar">
            <h1>Каса</h1>

            <div class="fin-search-wrap">
                <div class="chip-input" id="finChipBox">
                    <input type="text" class="chip-typer" id="finChipTyper"
                           placeholder="Пошук за документом, описом, контрагентом…" autocomplete="off">
                    <div class="chip-actions">
                        <button type="button" class="chip-act-btn chip-act-clear hidden" id="finChipClear" title="Очистити">&#x2715;</button>
                        <button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="search" id="finSearchHidden" value="<?php echo ViewHelper::h($search); ?>">
            </div>

            <?php if ($direction): ?><input type="hidden" name="direction" value="<?php echo ViewHelper::h($direction); ?>"><?php endif; ?>
            <?php if ($dateFrom):  ?><input type="hidden" name="date_from" value="<?php echo ViewHelper::h($dateFrom); ?>"><?php endif; ?>
            <?php if ($dateTo):    ?><input type="hidden" name="date_to"   value="<?php echo ViewHelper::h($dateTo); ?>"><?php endif; ?>
            <input type="hidden" name="page" value="1">
        </div>
    </form>

    <!-- Filter bar -->
    <form method="get" action="/finance/cash" id="filterForm">
        <input type="hidden" name="search" value="<?php echo ViewHelper::h($search); ?>">
        <input type="hidden" name="page"   value="1">

        <div class="filter-bar">
            <div class="filter-bar-group">
                <span class="filter-bar-label">Напрям</span>
                <?php foreach (array('' => 'Всі', 'in' => '↓ Прихід', 'out' => '↑ Витрати') as $val => $label): ?>
                    <label class="filter-pill<?php echo ($direction === $val) ? ' active' : ''; ?>">
                        <input type="radio" name="direction" value="<?php echo $val; ?>"
                               class="js-filter-radio"
                               <?php echo ($direction === $val) ? 'checked' : ''; ?>>
                        <?php echo $label; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="filter-bar-sep"></div>
            <div class="filter-bar-group">
                <span class="filter-bar-label">Дата</span>
                <input type="date" name="date_from" value="<?php echo ViewHelper::h($dateFrom); ?>"
                       class="js-filter-date" style="height:26px;font-size:12px;padding:0 6px;border:1px solid var(--border-input);border-radius:var(--radius-sm);">
                <span style="color:var(--text-muted);font-size:12px;">—</span>
                <input type="date" name="date_to" value="<?php echo ViewHelper::h($dateTo); ?>"
                       class="js-filter-date" style="height:26px;font-size:12px;padding:0 6px;border:1px solid var(--border-input);border-radius:var(--radius-sm);">
            </div>
            <button type="button" class="filter-bar-gear" title="Налаштувати фільтри">
                <svg viewBox="0 0 16 16" fill="none"><path d="M6.5 2h3M8 2v1.5M13 6.5v3M13 8h-1.5M9.5 14h-3M8 14v-1.5M3 9.5v-3M3 8h1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/></svg>
            </button>
        </div>
    </form>

    <!-- Summary -->
    <div class="fin-summary">
        <div class="fin-sum-item">
            <span class="fin-sum-label">Прихід</span>
            <span class="fin-sum-value green">+ <?php echo number_format($summary['in'], 2, '.', ' '); ?></span>
        </div>
        <div class="fin-sum-sep"></div>
        <div class="fin-sum-item">
            <span class="fin-sum-label">Витрати</span>
            <span class="fin-sum-value red">− <?php echo number_format($summary['out'], 2, '.', ' '); ?></span>
        </div>
        <div class="fin-sum-sep"></div>
        <div class="fin-sum-item">
            <span class="fin-sum-label">Баланс</span>
            <?php $balance = $summary['in'] - $summary['out']; ?>
            <span class="fin-sum-value <?php echo $balance >= 0 ? 'blue' : 'red'; ?>">
                <?php echo ($balance >= 0 ? '+' : '−') . ' ' . number_format(abs($balance), 2, '.', ' '); ?>
            </span>
        </div>
        <div style="margin-left:auto;align-self:center;font-size:12px;color:var(--text-muted);">
            <?php echo number_format($total, 0, '.', ' '); ?> записів
        </div>
    </div>

    <!-- Table -->
    <div class="fin-table-wrap">
        <table class="crm-table">
            <thead>
                <tr>
                    <th style="width:90px">Дата</th>
                    <th style="width:140px">Документ</th>
                    <th style="width:70px">Напрям</th>
                    <th>Контрагент</th>
                    <th>Опис</th>
                    <th style="width:120px;text-align:right">Сума</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">Записів не знайдено</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                        $momentDate = $row['moment'] ? substr($row['moment'], 0, 10) : '—';
                        $momentTime = $row['moment'] ? substr($row['moment'], 11, 5) : '';
                        $descText   = trim((string)$row['description']);
                        $cpName     = trim((string)$row['cp_name']);
                    ?>
                    <tr>
                        <td>
                            <div class="fin-moment">
                                <div class="fin-moment-date"><?php echo ViewHelper::h($momentDate); ?></div>
                                <div class="fin-moment-time"><?php echo ViewHelper::h($momentTime); ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="fin-doc" title="<?php echo ViewHelper::h($row['doc_number']); ?>">
                                <?php echo ViewHelper::h($row['doc_number'] ?: '—'); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['direction'] === 'in'): ?>
                                <span class="fin-badge-in">↓ Прихід</span>
                            <?php else: ?>
                                <span class="fin-badge-out">↑ Витрати</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($cpName !== ''): ?>
                                <?php if ($row['cp_id']): ?>
                                    <a href="/counterparties/view?id=<?php echo (int)$row['cp_id']; ?>" class="fin-cp-name"><?php echo ViewHelper::h($cpName); ?></a>
                                <?php else: ?>
                                    <span class="fin-cp-name"><?php echo ViewHelper::h($cpName); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="fin-cp-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fin-desc" title="<?php echo ViewHelper::h($descText); ?>">
                                <?php echo ViewHelper::h($descText ?: '—'); ?>
                            </span>
                        </td>
                        <td class="fin-sum-cell <?php echo $row['direction']; ?>">
                            <?php echo $row['direction'] === 'in' ? '+' : '−'; ?>
                            <?php echo number_format((float)$row['sum'], 2, '.', ' '); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination" style="margin-top:14px;">
        <?php
        $baseUrl = '/finance/cash?' . http_build_query(array_filter(array(
            'search'    => $search,
            'direction' => $direction,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        )));
        $from = max(1, $page - 3);
        $to   = min($pages, $page + 3);
        ?>
        <a href="<?php echo $baseUrl; ?>&page=<?php echo max(1, $page-1); ?>"
           class="pag-nav<?php echo $page <= 1 ? ' pag-nav-dis' : ''; ?>">&#8592;</a>
        <?php if ($from > 1): ?><a href="<?php echo $baseUrl; ?>&page=1">1</a><span>…</span><?php endif; ?>
        <?php for ($p = $from; $p <= $to; $p++): ?>
            <a href="<?php echo $baseUrl; ?>&page=<?php echo $p; ?>"
               <?php echo $p === $page ? 'class="active"' : ''; ?>><?php echo $p; ?></a>
        <?php endfor; ?>
        <?php if ($to < $pages): ?><span>…</span><a href="<?php echo $baseUrl; ?>&page=<?php echo $pages; ?>"><?php echo $pages; ?></a><?php endif; ?>
        <a href="<?php echo $baseUrl; ?>&page=<?php echo min($pages, $page+1); ?>"
           class="pag-nav<?php echo $page >= $pages ? ' pag-nav-dis' : ''; ?>">&#8594;</a>
    </div>
    <?php endif; ?>

</div>

<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script>
(function () {
    ChipSearch.init('finChipBox', 'finChipTyper', 'finSearchHidden', null, {noComma: true});

    var clearBtn = document.getElementById('finChipClear');
    var chipBox  = document.getElementById('finChipBox');
    var typer    = document.getElementById('finChipTyper');
    var hidden   = document.getElementById('finSearchHidden');
    var form     = document.getElementById('finForm');
    if (clearBtn && chipBox && typer && hidden) {
        function updateClear() {
            var has = chipBox.querySelectorAll('.chip').length > 0 || typer.value.trim() !== '';
            clearBtn.classList.toggle('hidden', !has);
        }
        new MutationObserver(updateClear).observe(chipBox, {childList: true});
        typer.addEventListener('input', updateClear);
        clearBtn.addEventListener('click', function () {
            chipBox.querySelectorAll('.chip').forEach(function(c){ c.remove(); });
            typer.value = '';
            hidden.value = '';
            clearBtn.classList.add('hidden');
            var p = form.querySelector('input[name="page"]');
            if (p) p.value = 1;
            form.submit();
        });
        updateClear();
    }

    document.querySelectorAll('.js-filter-radio').forEach(function(el) {
        el.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    document.querySelectorAll('.js-filter-date').forEach(function(el) {
        el.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
}());
</script>
<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
