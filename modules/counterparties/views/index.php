<?php
$title     = 'Контрагенти';
$activeNav = 'prostor';
$subNav    = 'counterparties';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
/* ── Layout ─────────────────────────────────────────────────────────────── */
.cp-outer { max-width:1500px; margin:0 auto; padding:20px 16px 24px; }
.cp-body { display:flex; gap:16px; align-items:flex-start; }
.cp-list-col { flex:1; min-width:0; }
.cp-panel-col {
    width:480px; flex-shrink:0;
    background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg);
    position:sticky; top:16px;
    max-height:calc(100vh - 80px); overflow-y:auto;
    display:none;
    box-shadow:0 4px 20px rgba(0,0,0,.08);
}
.cp-panel-col.visible { display:block; }

/* ── Toolbar ────────────────────────────────────────────────────────────── */
.cp-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:12px; }
.cp-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.cp-search-wrap { flex:1; min-width:0; }
.cp-toolbar .btn        { height:34px; padding:0 12px; flex-shrink:0; }
.cp-toolbar .chip-input { min-height:34px; max-height:34px; overflow:hidden; }

/* ── Pagination arrows ──────────────────────────────────────────────────── */
.pagination .pag-nav     { padding:5px 9px; font-size:15px; line-height:1; }
.pagination .pag-nav-dis { opacity:.35; cursor:default; pointer-events:none; }

/* ── Panel resize handle ────────────────────────────────────────────────── */
#cpResizeHandle {
    position:fixed; z-index:1500;
    height:10px; cursor:ns-resize;
    display:none;
}
#cpResizeHandle::before {
    content:'';
    position:absolute; left:50%; top:50%;
    transform:translate(-50%,-50%);
    width:40px; height:4px; border-radius:2px;
    background:var(--border-input);
    opacity:0; transition:opacity .18s;
}
#cpResizeHandle:hover::before   { opacity:1; }
#cpResizeHandle.rh-active::before { opacity:1; background:var(--blue-light); }

/* ── Table ──────────────────────────────────────────────────────────────── */
.cp-table-wrap { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.crm-table tr.cp-row { cursor:pointer; transition:background .1s; }
.crm-table tr.cp-row:hover { background:var(--bg-hover); }
.crm-table tr.cp-row.selected { background:var(--blue-bg); }
.cp-name-cell { display:flex; align-items:center; gap:8px; }
.cp-type-icon {
    width:30px; height:30px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:11px; font-weight:700;
}
.cp-type-icon.company { background:#dbeafe; color:#1d4ed8; }
.cp-type-icon.fop     { background:#ffedd5; color:#c2410c; }
.cp-type-icon.person  { background:#f3f4f6; color:#4b5563; }
.cp-type-icon.other   { background:#f3f4f6; color:#4b5563; }
.cp-nm { font-weight:600; font-size:13px; }
.cp-stat-col { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
.cp-ltv { font-weight:600; color:var(--green); }

/* ── Panel styles ───────────────────────────────────────────────────────── */
.cpp-header {
    display:flex; align-items:center; gap:10px;
    padding:14px 16px; border-bottom:1px solid var(--border);
    position:sticky; top:0; background:var(--bg-card); z-index:2;
}
.cpp-avatar {
    width:40px; height:40px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:14px; font-weight:700;
}
.cpp-avatar.company { background:#dbeafe; color:#1d4ed8; }
.cpp-avatar.fop     { background:#ffedd5; color:#c2410c; }
.cpp-avatar.person  { background:#f3f4f6; color:#374151; }
.cpp-avatar.other   { background:#f3f4f6; color:#374151; }
.cpp-name-block { flex:1; min-width:0; }
.cpp-name { font-size:14px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.cpp-badges { display:flex; gap:4px; flex-wrap:wrap; margin-top:3px; }
.cpp-header-btns { display:flex; gap:6px; flex-shrink:0; }

.cpp-quick-contacts {
    display:flex; gap:8px; flex-wrap:wrap; padding:8px 16px;
    border-bottom:1px solid var(--border); background:var(--bg-header);
}
.cpp-contact-pill {
    display:inline-flex; align-items:center; gap:4px;
    font-size:12px; color:var(--text-muted); text-decoration:none;
}
.cpp-contact-pill:hover { color:var(--blue); }

.cpp-stats {
    display:grid; grid-template-columns:repeat(4,1fr);
    border-bottom:1px solid var(--border);
}
.cpp-stat {
    padding:10px 8px; text-align:center; border-right:1px solid var(--border);
}
.cpp-stat:last-child { border-right:none; }
.cpp-stat-val { display:block; font-size:15px; font-weight:700; line-height:1; margin-bottom:2px; }
.cpp-stat-val.green { color:var(--green); }
.cpp-stat-lbl { display:block; font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.3px; }

.cpp-tabs-nav {
    display:flex; border-bottom:2px solid var(--border); padding:0 12px;
    position:sticky; top:68px; background:var(--bg-card); z-index:1;
}
.cpp-tab {
    padding:8px 12px; font-size:12px; font-weight:600; color:var(--text-muted);
    background:none; border:none; border-bottom:2px solid transparent; margin-bottom:-2px;
    cursor:pointer; white-space:nowrap; transition:color .1s,border-color .1s;
    display:inline-flex; align-items:center; gap:4px;
}
.cpp-tab:hover { color:var(--text); }
.cpp-tab.active { color:var(--blue); border-bottom-color:var(--blue); }
.cpp-tab-cnt {
    background:var(--bg-hover); color:var(--text-muted);
    border-radius:8px; font-size:10px; padding:0 5px; font-weight:400;
}

.cpp-panel { padding:14px 16px; }
.cpp-panel.hidden { display:none; }

.cpp-field { margin-bottom:10px; }
.cpp-field label {
    display:block; font-size:11px; font-weight:600; color:var(--text-muted);
    text-transform:uppercase; letter-spacing:.3px; margin-bottom:3px;
}
.cpp-field input,.cpp-field textarea,.cpp-field select {
    width:100%; padding:6px 9px; border:1px solid var(--border-input);
    border-radius:var(--radius-sm); font-size:13px; font-family:var(--font);
    background:#fff; outline:none; transition:border-color .12s;
}
.cpp-field input:focus,.cpp-field textarea:focus { border-color:var(--blue-light); }
.cpp-field textarea { resize:vertical; min-height:52px; }
.cpp-field-row { display:grid; grid-template-columns:1fr 1fr; gap:0 10px; }

.cpp-save-row { display:flex; align-items:center; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid var(--border); }
.cpp-save-status { font-size:12px; }
.cpp-form-err { background:var(--red-bg); border:1px solid var(--red-border); color:var(--red); padding:6px 10px; border-radius:var(--radius-sm); font-size:12px; margin-bottom:10px; }

.cpp-contact-row { display:flex; align-items:flex-start; gap:8px; padding:8px 0; border-bottom:1px solid var(--border); }
.cpp-contact-row:last-of-type { border-bottom:none; }
.cpp-contact-av { width:28px; height:28px; border-radius:50%; background:var(--bg-hover); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:var(--text-muted); flex-shrink:0; }
.cpp-contact-body { flex:1; min-width:0; }
.cpp-contact-nm { font-weight:600; font-size:13px; }
.cpp-contact-role { font-size:11px; color:var(--text-muted); margin:1px 0; }
.cpp-contact-links { display:flex; gap:8px; font-size:12px; }
.cpp-contact-links a { color:var(--text-muted); }
.cpp-contact-links a:hover { color:var(--blue); }

.cpp-doc-row { display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border-bottom:1px solid var(--border); }
.cpp-doc-row:last-of-type { border-bottom:none; }
.cpp-empty { padding:24px 0; text-align:center; color:var(--text-muted); font-size:13px; }

/* Loading skeleton */
.cpp-loading {
    display:flex; align-items:center; justify-content:center;
    height:200px; color:var(--text-muted); font-size:13px;
}

/* Add btn */
.btn-ghost { background:transparent; border-color:transparent; color:var(--text-muted); }
.btn-ghost:hover { background:var(--bg-hover); border-color:var(--border); }

/* ── Mini relation graph ──────────────────────────────────────────────────── */
.rg-mini-wrap { padding:16px; }
.rg-mini-group { display:flex; align-items:center; gap:6px; flex-wrap:wrap; padding:7px 10px; margin-bottom:14px; background:var(--blue-bg); border:1px solid #bfdbfe; border-radius:var(--radius-sm); }
.rg-mini-group-lbl { font-size:10px; font-weight:700; color:var(--blue); text-transform:uppercase; flex-shrink:0; margin-right:4px; }
.rg-mini-group-node { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; background:var(--bg-card); border:1px solid var(--border); border-radius:10px; font-size:11px; font-weight:600; text-decoration:none; color:var(--text); white-space:nowrap; }
.rg-mini-group-node:hover { border-color:var(--blue-light); text-decoration:none; }
.rg-mini-group-node.rg-self { border-color:var(--blue); color:var(--blue); background:var(--blue-bg); }
.rg-mini-group-sep { color:var(--border-input); font-size:14px; }
.rg-mini-main { position:relative; display:flex; flex-direction:column; align-items:center; gap:32px; min-height:60px; }
.rg-mini-svg  { position:absolute; top:0; left:0; pointer-events:none; overflow:visible; }
.rg-mini-row  { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; position:relative; z-index:1; width:100%; }
.rg-mini-nw   { position:relative; }
.rg-mini-node { display:flex; flex-direction:column; align-items:center; padding:8px 6px 7px; width:86px; text-align:center; border:1.5px solid var(--border); border-radius:var(--radius); background:var(--bg-card); text-decoration:none; color:var(--text); transition:border-color .15s, transform .1s; }
.rg-mini-node:not(.rg-self):hover { border-color:var(--blue-light); transform:translateY(-2px); text-decoration:none; }
.rg-mini-node.company { border-color:#bfdbfe; }
.rg-mini-node.fop     { border-color:#fed7aa; }
.rg-mini-node.rg-self { border-color:var(--blue); background:var(--blue-bg); pointer-events:none; }
.rg-mini-av   { width:30px; height:30px; border-radius:50%; margin-bottom:5px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; }
.rg-mini-node.company .rg-mini-av, .rg-mini-node.rg-self .rg-mini-av { background:#dbeafe; color:#1d4ed8; }
.rg-mini-node.fop    .rg-mini-av { background:#ffedd5; color:#c2410c; }
.rg-mini-node.person .rg-mini-av { background:#f3f4f6; color:#374151; }
.rg-mini-node.other  .rg-mini-av { background:#f3f4f6; color:#374151; }
.rg-mini-name { font-size:10px; font-weight:700; line-height:1.2; word-break:break-word; margin-bottom:2px; }
.rg-mini-role { font-size:9px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:80px; }
.rg-mini-empty { text-align:center; padding:24px 16px; color:var(--text-muted); font-size:13px; }

/* ── Event feed + chat ────────────────────────────────────────────────────── */
.cpp-feed-wrap  { display:flex; flex-direction:column; }
.cpp-feed-list  { flex:1; overflow-y:auto; padding:12px 16px; }
.cpp-feed-note  { background:var(--bg-hover); border-radius:var(--radius); padding:8px 12px; margin-bottom:10px; }
.cpp-feed-note-meta { font-size:10px; color:var(--text-muted); margin-bottom:3px; }
.cpp-feed-note-text { font-size:13px; line-height:1.5; white-space:pre-wrap; word-break:break-word; }
.cpp-feed-order { display:flex; gap:10px; align-items:flex-start; padding:7px 0; border-bottom:1px solid var(--border); }
.cpp-feed-order:last-child { border-bottom:none; }
.cpp-feed-ord-icon { width:24px; height:24px; border-radius:50%; background:#dbeafe; color:#1d4ed8; display:flex; align-items:center; justify-content:center; font-size:11px; flex-shrink:0; margin-top:1px; }
.cpp-feed-ord-body { flex:1; min-width:0; }
.cpp-feed-ord-title { font-size:12px; font-weight:600; display:flex; gap:8px; align-items:center; }
.cpp-feed-ord-title a { color:var(--text); }
.cpp-feed-ord-title a:hover { color:var(--blue); }
.cpp-feed-ord-meta { font-size:11px; color:var(--text-muted); margin-top:2px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
.cpp-feed-empty { text-align:center; padding:32px 16px; color:var(--text-muted); font-size:13px; }
.cpp-chat-input { border-top:1px solid var(--border); padding:10px 16px; display:flex; gap:8px; align-items:flex-end; flex-shrink:0; }
.cpp-chat-input textarea { flex:1; padding:7px 10px; border:1px solid var(--border-input); border-radius:var(--radius-sm); font-size:13px; font-family:var(--font); resize:vertical; min-height:56px; max-height:200px; overflow-y:auto; line-height:1.4; }
.cpp-chat-input textarea:focus { border-color:var(--blue-light); outline:none; }

/* ── Chat channel tabs ───────────────────────────────────────────────────── */
.cpp-ch-tabs { display:flex; gap:0; border-bottom:1px solid var(--border); padding:0 16px; background:var(--bg-header); flex-shrink:0; }
.cpp-ch-tab { padding:7px 11px; font-size:12px; font-weight:600; color:var(--text-muted); background:none; border:none; border-bottom:2px solid transparent; margin-bottom:-1px; cursor:pointer; transition:color .1s,border-color .1s; }
.cpp-ch-tab:hover { color:var(--text); }
.cpp-ch-tab.active { color:var(--blue); border-bottom-color:var(--blue); }

/* ── Chat messages ───────────────────────────────────────────────────────── */
.cpp-chat-wrap { display:flex; flex-direction:column; }
.cpp-msgs { flex:1; overflow-y:auto; padding:10px 14px; display:flex; flex-direction:column; gap:4px; background:var(--bg-body,#f8f9fb); }
.cpp-msgs-loading,.cpp-msgs-empty { color:var(--text-muted); font-size:12px; text-align:center; padding:20px 0; }
.cpp-msg { display:flex; flex-direction:column; max-width:85%; }
.cpp-msg-in  { align-self:flex-start; }
.cpp-msg-out { align-self:flex-end; }
.cpp-msg-bubble { padding:7px 11px; border-radius:12px; font-size:13px; line-height:1.45; word-break:break-word; white-space:pre-wrap; }
.cpp-msg-in  .cpp-msg-bubble { background:#e9ecef; color:var(--text); border-bottom-left-radius:3px; }
.cpp-msg-out .cpp-msg-bubble { background:var(--blue); color:#fff; border-bottom-right-radius:3px; }
.cpp-msg-meta { font-size:10px; color:var(--text-muted); margin-top:2px; padding:0 2px; }
.cpp-msg-out .cpp-msg-meta { text-align:right; }

/* ── Template chips ──────────────────────────────────────────────────────── */
.cpp-tpl-row { display:flex; flex-wrap:wrap; gap:5px; padding:6px 14px; border-top:1px solid var(--border); flex-shrink:0; min-height:0; background:var(--bg-card); }
.cpp-tpl-chip { padding:3px 10px; font-size:11px; font-weight:600; background:var(--bg-hover); border:1px solid var(--border); border-radius:10px; cursor:pointer; white-space:nowrap; color:var(--text); transition:background .12s,border-color .12s; }
.cpp-tpl-chip:hover { background:var(--blue-bg); border-color:var(--blue-light); color:var(--blue); }
.cpp-chat-input-ch textarea { height:48px; min-height:48px; }

/* ── Unread badge ────────────────────────────────────────────────────────── */
.cpp-tab-unread { background:#ef4444; color:#fff; font-weight:700; }

/* ── Emoji picker ────────────────────────────────────────────────────────── */
.cpp-emoji-picker {
    position:absolute; bottom:calc(100% + 6px); left:0; right:0;
    background:#fff; border:1px solid var(--border); border-radius:var(--radius);
    box-shadow:0 4px 16px rgba(0,0,0,.14); z-index:300;
    display:grid; grid-template-columns:repeat(8,1fr); gap:0; padding:6px;
}
.cpp-emoji-item {
    padding:5px; text-align:center; font-size:18px; cursor:pointer;
    border-radius:4px; line-height:1;
}
.cpp-emoji-item:hover { background:var(--bg-hover); }

/* ── Chat icon buttons column ────────────────────────────────────────────── */
.cpp-icon-btn { display:flex; align-items:center; justify-content:center; }
</style>

<div class="cp-outer">
<div class="cp-toolbar">
    <h1>Контрагенти</h1>
    <button class="btn btn-primary" id="btnAddCp" type="button">+ Додати</button>
    <div class="cp-search-wrap">
        <form method="get" action="/counterparties" id="cpFilterForm">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="type"   id="typeHidden" value="<?php echo htmlspecialchars($type); ?>">
            <div class="chip-input" id="searchChipBox">
                <input type="text" class="chip-typer" id="searchChipTyper"
                       placeholder="ID, назва, ЄДРПОУ, телефон…" autocomplete="off">
                <div class="chip-actions">
                    <button type="button" class="chip-act-btn chip-act-clear hidden" id="chipClearBtn" title="Очистити">&#x2715;</button>
                    <button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </button>
                </div>
            </div>
            <input type="hidden" name="search" id="searchHidden" value="<?php echo htmlspecialchars($search); ?>">
        </form>
    </div>
</div>

<div class="filter-bar">
    <div class="filter-bar-group">
        <span class="filter-bar-label">Тип</span>
        <?php
        $typeOpts = array('' => 'Всі', 'company' => 'Юрлиця', 'fop' => 'ФОП', 'person' => 'Фізособи');
        foreach ($typeOpts as $val => $label):
            $active = ($type === $val) ? ' active' : '';
        ?>
        <label class="filter-pill js-type-pill<?php echo $active; ?>">
            <input type="radio" name="cp_type_pill" value="<?php echo $val; ?>"<?php echo ($type===$val)?' checked':''; ?>>
            <?php echo $label; ?>
        </label>
        <?php endforeach; ?>
    </div>
    <button type="button" class="filter-bar-gear" title="Налаштувати фільтри">
        <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/>
            <path d="M8 1v1.5M8 13.5V15M15 8h-1.5M2.5 8H1M12.95 3.05l-1.06 1.06M4.11 11.89l-1.06 1.06M12.95 12.95l-1.06-1.06M4.11 4.11 3.05 3.05" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
    </button>
</div>

<?php if ($total > 0): ?>
<div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;">
    Знайдено: <strong><?php echo $total; ?></strong>
</div>
<?php endif; ?>

<div class="cp-body">

<!-- ── List ──────────────────────────────────────────────────────────────── -->
<div class="cp-list-col">
<div class="cp-table-wrap">
<table class="crm-table">
<thead>
<tr>
    <th>Контрагент</th>
    <th>Телефон</th>
    <th class="cp-stat-col">Замовлень</th>
    <th class="cp-stat-col">LTV</th>
</tr>
</thead>
<tbody id="cpTableBody">
<?php if (empty($rows)): ?>
<tr><td colspan="4" style="text-align:center; padding:32px; color:var(--text-muted);">
    Контрагентів не знайдено
</td></tr>
<?php else: ?>
<?php foreach ($rows as $row):
    $typeKey  = $row['type'];
    $initial  = mb_strtoupper(mb_substr($row['name'],0,1,'UTF-8'),'UTF-8');
    $iconCls  = in_array($typeKey,array('company','fop','person')) ? $typeKey : 'other';
    $phone    = $row['phone'] ? $row['phone'] : '';
?>
<tr class="cp-row" data-id="<?php echo $row['id']; ?>">
    <td>
        <div class="cp-name-cell">
            <div class="cp-type-icon <?php echo $iconCls; ?>"><?php echo $initial; ?></div>
            <div>
                <div class="cp-nm"><?php echo htmlspecialchars($row['name']); ?></div>
                <div style="font-size:11px; color:var(--text-muted)">
                    <?php echo CounterpartyRepository::typeLabel($typeKey); ?>
                    <?php if ($row['group_name']): ?>
                        · <span style="color:var(--blue)"><?php echo htmlspecialchars($row['group_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </td>
    <td><?php echo $phone ? '<span class="nowrap">'.htmlspecialchars($phone).'</span>' : '<span class="text-faint">—</span>'; ?></td>
    <td class="cp-stat-col">
        <?php echo $row['order_count'] > 0 ? (int)$row['order_count'] : '<span class="text-faint">0</span>'; ?>
    </td>
    <td class="cp-stat-col">
        <?php echo $row['ltv'] > 0
            ? '<span class="cp-ltv">₴'.number_format((float)$row['ltv'],0,'.', ' ').'</span>'
            : '<span class="text-faint">—</span>'; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<?php if ($pages > 1):
    // Build clean URL without empty params
    function cpPageUrl($s, $t, $p) {
        $ps = array();
        if ($s !== '') $ps['search'] = $s;
        if ($t !== '') $ps['type']   = $t;
        if ($p >  1)   $ps['page']   = $p;
        return '/counterparties' . ($ps ? '?' . http_build_query($ps) : '');
    }
    // Collect page numbers to show (always show 1, last, and ±2 around current)
    $shown = array();
    for ($p = 1; $p <= $pages; $p++) {
        if ($p === 1 || $p === $pages || ($p >= $page - 2 && $p <= $page + 2)) {
            $shown[] = $p;
        }
    }
?>
<div class="pagination" style="margin-top:16px">
    <?php if ($page > 1): ?>
        <a href="<?php echo cpPageUrl($search,$type,$page-1); ?>" class="pag-nav" title="Попередня">&#8592;</a>
    <?php else: ?>
        <span class="pag-nav pag-nav-dis">&#8592;</span>
    <?php endif; ?>

    <?php $prev = null; foreach ($shown as $p):
        if ($prev !== null && $p > $prev + 1): ?>
            <span class="dots">…</span>
        <?php endif; ?>
        <a href="<?php echo cpPageUrl($search,$type,$p); ?>"
           class="<?php echo ($p===$page)?'cur':''; ?>"><?php echo $p; ?></a>
    <?php $prev = $p; endforeach; ?>

    <?php if ($page < $pages): ?>
        <a href="<?php echo cpPageUrl($search,$type,$page+1); ?>" class="pag-nav" title="Наступна">&#8594;</a>
    <?php else: ?>
        <span class="pag-nav pag-nav-dis">&#8594;</span>
    <?php endif; ?>
</div>
<?php endif; ?>
</div><!-- /cp-list-col -->

<!-- ── Sidebar panel ─────────────────────────────────────────────────────── -->
<div class="cp-panel-col" id="cpPanelCol">
    <div class="cpp-loading" id="cpPanelLoading">Завантаження…</div>
</div>

</div><!-- /cp-body -->

<!-- Resize handle: positioned by JS at the bottom of cpPanelCol -->
<div id="cpResizeHandle" title="Потягніть щоб змінити висоту"></div>
</div><!-- /cp-outer -->


<!-- ══ Modal: New counterparty ══════════════════════════════════════════════ -->
<div class="modal-overlay hidden" id="modalAdd">
    <div class="modal-box" style="width:460px">
        <div class="modal-head">
            <span>Новий контрагент</span>
            <button class="modal-close" id="modalAddClose">&#x2715;</button>
        </div>
        <div class="modal-body">
            <div id="modalAddError" class="modal-error hidden"></div>
            <div class="form-row" style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Тип</label>
                <div style="display:flex;gap:8px" id="typeSelector">
                    <label class="type-radio-btn selected" data-type="company"><input type="radio" name="new_type" value="company" checked>Юрлицо</label>
                    <label class="type-radio-btn" data-type="fop"><input type="radio" name="new_type" value="fop">ФОП</label>
                    <label class="type-radio-btn" data-type="person"><input type="radio" name="new_type" value="person">Фізособа</label>
                </div>
            </div>
            <div class="form-row"><label>Назва / ПІБ <span style="color:var(--red)">*</span></label><input type="text" id="newName" autofocus></div>
            <div class="form-row" id="fieldOkpo"><label>ЄДРПОУ</label><input type="text" id="newOkpo" maxlength="12"></div>
            <div class="form-row"><label>Телефон</label><input type="text" id="newPhone"></div>
            <div class="form-row"><label>Email</label><input type="email" id="newEmail"></div>
        </div>
        <div class="modal-footer">
            <button class="btn" id="modalAddCancel">Скасувати</button>
            <button class="btn btn-primary" id="modalAddSave">Створити →</button>
        </div>
    </div>
</div>

<style>
.type-radio-btn { display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border:1px solid var(--border-input);border-radius:var(--radius-sm);cursor:pointer;font-size:13px;transition:all .12s; }
.type-radio-btn input { display:none; }
.type-radio-btn.selected { border-color:var(--blue);background:var(--blue-bg);color:var(--blue);font-weight:600; }
</style>

<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script>
(function() {
    var panelCol   = document.getElementById('cpPanelCol');
    var tableBody  = document.getElementById('cpTableBody');
    var selectedId = 0;

    // ── Chip Search ──────────────────────────────────────────────────────────
    ChipSearch.init('searchChipBox','searchChipTyper','searchHidden', null, {noComma:false});

    var typeHidden = document.getElementById('typeHidden');
    var form       = document.getElementById('cpFilterForm');

    // Type filter pills
    document.querySelectorAll('.js-type-pill input[type=radio]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.js-type-pill').forEach(function(l) { l.classList.remove('active'); });
            this.closest('.js-type-pill').classList.add('active');
            typeHidden.value = this.value;
            form.querySelector('[name=page]').value = 1;
            form.submit();
        });
    });

    var clearBtn = document.getElementById('chipClearBtn');
    var chipBox  = document.getElementById('searchChipBox');
    var typer    = document.getElementById('searchChipTyper');
    var hidden   = document.getElementById('searchHidden');
    function updateClear() {
        var has = chipBox.querySelectorAll('.chip').length > 0 || typer.value.trim() !== '';
        clearBtn.classList.toggle('hidden', !has);
    }
    new MutationObserver(updateClear).observe(chipBox, {childList:true});
    typer.addEventListener('input', updateClear);
    clearBtn.addEventListener('click', function() {
        chipBox.querySelectorAll('.chip').forEach(function(c){c.remove();});
        typer.value = ''; hidden.value = '';
        form.querySelector('[name=page]').value = 1;
        form.submit();
    });
    updateClear();

    // ── Open panel ───────────────────────────────────────────────────────────
    function openPanel(id) {
        selectedId = id;

        // Highlight row
        tableBody.querySelectorAll('tr.cp-row').forEach(function(r){
            r.classList.toggle('selected', parseInt(r.dataset.id, 10) === id);
        });

        // Reset chat-stretch styles; apply saved manual height if set
        var _savedH = parseInt(localStorage.getItem('cp_panel_h'), 10);
        panelCol.style.overflowY = '';
        if (_savedH > 100) {
            var _maxH = window.innerHeight - panelCol.getBoundingClientRect().top - 8;
            panelCol.style.height    = Math.min(_savedH, _maxH) + 'px';
            panelCol.style.maxHeight = 'none';
        } else {
            panelCol.style.height    = '';
            panelCol.style.maxHeight = '';
        }

        // Show loading
        panelCol.classList.add('visible');
        panelCol.innerHTML = '<div class="cpp-loading">Завантаження…</div>';
        positionResizeHandle();

        history.replaceState(null, '', '/counterparties?<?php echo http_build_query(array_filter(array('search'=>$search,'type'=>$type,'page'=>$page))); ?>&selected=' + id);

        fetch('/counterparties/panel?id=' + id)
            .then(function(r) { return r.text(); })
            .then(function(html) {
                panelCol.innerHTML = html;
                panelCol.querySelectorAll('script').forEach(function(oldScript) {
                    var newScript = document.createElement('script');
                    newScript.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
                positionResizeHandle();
            })
            .catch(function() {
                panelCol.innerHTML = '<div class="cpp-loading" style="color:var(--red)">Помилка завантаження</div>';
                positionResizeHandle();
            });
    }

    function closePanel() {
        panelCol.classList.remove('visible');
        panelCol.innerHTML = '<div class="cpp-loading">Завантаження…</div>';
        panelCol.style.height    = '';
        panelCol.style.maxHeight = '';
        panelCol.style.overflowY = '';
        selectedId = 0;
        positionResizeHandle();
        tableBody.querySelectorAll('tr.cp-row').forEach(function(r){ r.classList.remove('selected'); });
        history.replaceState(null, '', '/counterparties<?php echo ($search||$type||$page>1) ? '?'.http_build_query(array_filter(array('search'=>$search,'type'=>$type,'page'=>$page))) : ''; ?>');
    }

    // Row click
    tableBody.addEventListener('click', function(e) {
        var row = e.target.closest('tr.cp-row');
        if (!row) return;
        var id = parseInt(row.dataset.id, 10);
        if (id === selectedId) {
            closePanel();
        } else {
            openPanel(id);
        }
    });

    // Panel close event (from panel's close button)
    document.addEventListener('cpPanelClose', closePanel);

    // ── Resize handle ────────────────────────────────────────────────────────
    var resizeHandle  = document.getElementById('cpResizeHandle');
    var isResizing    = false;
    var resizeStartY  = 0;
    var resizeStartH  = 0;
    var CP_H_KEY      = 'cp_panel_h';

    function positionResizeHandle() {
        if (!panelCol.classList.contains('visible')) {
            resizeHandle.style.display = 'none';
            return;
        }
        var rect = panelCol.getBoundingClientRect();
        resizeHandle.style.display = 'block';
        resizeHandle.style.left    = rect.left  + 'px';
        resizeHandle.style.width   = rect.width + 'px';
        resizeHandle.style.top     = (rect.bottom - 5) + 'px';
    }

    resizeHandle.addEventListener('mousedown', function(e) {
        e.preventDefault();
        isResizing   = true;
        resizeStartY = e.clientY;
        resizeStartH = panelCol.getBoundingClientRect().height;
        document.body.style.cursor     = 'ns-resize';
        document.body.style.userSelect = 'none';
        resizeHandle.classList.add('rh-active');
    });

    document.addEventListener('mousemove', function(e) {
        if (!isResizing) return;
        var delta = e.clientY - resizeStartY;
        var newH  = Math.max(150, resizeStartH + delta);
        var maxH  = window.innerHeight - panelCol.getBoundingClientRect().top - 4;
        newH = Math.min(newH, maxH);
        panelCol.style.height    = newH + 'px';
        panelCol.style.maxHeight = 'none';
        positionResizeHandle();
    });

    document.addEventListener('mouseup', function() {
        if (!isResizing) return;
        isResizing = false;
        document.body.style.cursor     = '';
        document.body.style.userSelect = '';
        resizeHandle.classList.remove('rh-active');
        localStorage.setItem(CP_H_KEY, Math.round(panelCol.getBoundingClientRect().height));
        positionResizeHandle();
    });

    window.addEventListener('scroll',   positionResizeHandle, { passive: true });
    window.addEventListener('resize',   function() { positionResizeHandle(); });

    // Open from URL ?selected=X
    var urlParams = new URLSearchParams(window.location.search);
    var preselected = parseInt(urlParams.get('selected'), 10);
    if (preselected > 0) {
        openPanel(preselected);
    }

    // ── Add modal ────────────────────────────────────────────────────────────
    var modal      = document.getElementById('modalAdd');
    var btnAdd     = document.getElementById('btnAddCp');
    var btnClose   = document.getElementById('modalAddClose');
    var btnCancel  = document.getElementById('modalAddCancel');
    var btnSave    = document.getElementById('modalAddSave');
    var errBox     = document.getElementById('modalAddError');
    var fieldOkpo  = document.getElementById('fieldOkpo');
    var curType    = 'company';

    btnAdd.addEventListener('click', function(){ modal.classList.remove('hidden'); document.getElementById('newName').focus(); });
    btnClose.addEventListener('click',  function(){ modal.classList.add('hidden'); });
    btnCancel.addEventListener('click', function(){ modal.classList.add('hidden'); });
    modal.addEventListener('click', function(e){ if(e.target===modal) modal.classList.add('hidden'); });

    document.getElementById('typeSelector').addEventListener('click', function(e) {
        var btn = e.target.closest('.type-radio-btn');
        if (!btn) return;
        document.querySelectorAll('.type-radio-btn').forEach(function(b){ b.classList.remove('selected'); });
        btn.classList.add('selected');
        btn.querySelector('input').checked = true;
        curType = btn.dataset.type;
        fieldOkpo.style.display = curType === 'person' ? 'none' : '';
    });

    btnSave.addEventListener('click', function() {
        var name = document.getElementById('newName').value.trim();
        if (!name) { errBox.textContent='Введіть назву'; errBox.classList.remove('hidden'); return; }
        errBox.classList.add('hidden');
        btnSave.disabled = true;
        var fd = new FormData();
        fd.append('type', curType); fd.append('name', name);
        fd.append('phone', document.getElementById('newPhone').value.trim());
        fd.append('email', document.getElementById('newEmail').value.trim());
        var okpo = document.getElementById('newOkpo').value.trim();
        if (okpo) fd.append('okpo', okpo);
        fetch('/counterparties/api/save_counterparty',{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(d){
                if (d.ok) {
                    modal.classList.add('hidden');
                    openPanel(d.id);
                    // Optionally reload list after short delay
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    errBox.textContent = d.error||'Помилка';
                    errBox.classList.remove('hidden');
                    btnSave.disabled = false;
                }
            });
    });
}());
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
