<?php
$title     = htmlspecialchars($cp['name']) . ' — Контрагент';
$activeNav = 'prostor';
$subNav    = 'counterparties';
require_once __DIR__ . '/../../shared/layout.php';

$isCompany = in_array($cp['type'], array('company', 'fop', 'department', 'other'));
$isPerson  = ($cp['type'] === 'person');

// Avatar initials (up to 2 chars)
$words = preg_split('/\s+/', $cp['name']);
if (count($words) >= 2) {
    $initials = mb_strtoupper(mb_substr($words[0],0,1,'UTF-8') . mb_substr($words[1],0,1,'UTF-8'),'UTF-8');
} else {
    $initials = mb_strtoupper(mb_substr($cp['name'],0,2,'UTF-8'),'UTF-8');
}

// Status label for orders
$orderStatusLabels = array(
    'draft'=>'Чернетка','new'=>'Новий','confirmed'=>'Підтверджено',
    'in_progress'=>'В роботі','waiting_payment'=>'Очікує оплату',
    'paid'=>'Оплачено','partially_shipped'=>'Частково відвантажено',
    'shipped'=>'Відвантажено','completed'=>'Виконано','cancelled'=>'Скасовано',
);
?>
<style>
/* ── Profile layout ──────────────────────────────────────────────────────── */
.cp-view { max-width:1200px; margin:0 auto; padding:20px 16px 40px; }

/* ── Relation graph ─────────────────────────────────────────────────────── */
.rg-wrap { padding:24px 16px; }
.rg-group-strip {
    display:flex; align-items:center; gap:0;
    background:var(--blue-bg); border:1px solid #bfdbfe;
    border-radius:var(--radius); padding:12px 16px; margin-bottom:32px;
}
.rg-group-lbl { font-size:11px; font-weight:700; color:var(--blue); text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; margin-right:16px; }
.rg-group-nodes { display:flex; gap:8px; flex-wrap:wrap; }
.rg-group-node {
    display:inline-flex; align-items:center; gap:7px;
    padding:6px 10px; background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--radius-sm); text-decoration:none; color:var(--text);
    font-size:12px; font-weight:600; transition:border-color .12s;
}
.rg-group-node:hover { border-color:var(--blue-light); text-decoration:none; }
.rg-group-node.rg-self { border-color:var(--blue); background:var(--blue-bg); color:var(--blue); }
.rg-group-node .mini-av {
    width:22px; height:22px; border-radius:50%; display:flex; align-items:center;
    justify-content:center; font-size:10px; font-weight:700; flex-shrink:0;
}
.rg-group-node.rg-self .mini-av { background:#bfdbfe; color:#1d4ed8; }
.rg-group-node:not(.rg-self) .mini-av { background:var(--bg-hover); color:var(--text-muted); }
.rg-group-sep { color:var(--border-input); margin:0 2px; font-size:16px; align-self:center; }

.rg-main {
    position:relative; display:flex; flex-direction:column;
    align-items:center; gap:56px; min-height:120px;
}
.rg-svg {
    position:absolute; top:0; left:0; width:100%; height:100%;
    pointer-events:none; overflow:visible;
}
.rg-row { display:flex; gap:20px; justify-content:center; flex-wrap:wrap; position:relative; z-index:1; }
.rg-node {
    display:flex; flex-direction:column; align-items:center;
    padding:14px 14px 12px; width:130px; text-align:center;
    border:2px solid var(--border); border-radius:var(--radius);
    background:var(--bg-card); text-decoration:none; color:var(--text);
    transition:border-color .15s, box-shadow .15s, transform .1s;
    cursor:pointer;
}
.rg-node:hover { border-color:var(--blue-light); box-shadow:0 4px 12px rgba(37,99,196,.12); transform:translateY(-2px); text-decoration:none; }
.rg-node.company { border-color:#bfdbfe; }
.rg-node.fop     { border-color:#fed7aa; }
.rg-node.rg-self { border-color:var(--blue); border-width:2px; background:var(--blue-bg); pointer-events:none; }
.rg-av {
    width:42px; height:42px; border-radius:50%; margin-bottom:8px;
    display:flex; align-items:center; justify-content:center;
    font-size:15px; font-weight:700;
}
.rg-node.company .rg-av, .rg-node.rg-self .rg-av { background:#dbeafe; color:#1d4ed8; }
.rg-node.fop .rg-av     { background:#ffedd5; color:#c2410c; }
.rg-node.person .rg-av  { background:#f3f4f6; color:#374151; }
.rg-node.other .rg-av   { background:#f3f4f6; color:#374151; }
.rg-name { font-size:12px; font-weight:700; line-height:1.3; margin-bottom:4px; word-break:break-word; }
.rg-type { font-size:10px; color:var(--text-muted); }
.rg-role {
    margin-top:5px; font-size:10px; font-weight:600;
    background:var(--blue-bg); color:var(--blue);
    padding:1px 7px; border-radius:8px; white-space:nowrap;
}
.rg-role.role-dir  { background:#dcfce7; color:#166534; }
.rg-role.role-acc  { background:#fef9c3; color:#854d0e; }
.rg-role.role-mgr  { background:#f3e8ff; color:#6b21a8; }
.rg-contact { font-size:10px; color:var(--text-faint); margin-top:3px; }
.rg-dir-badge {
    position:absolute; top:-8px; left:50%; transform:translateX(-50%);
    font-size:9px; font-weight:700; background:var(--green); color:#fff;
    padding:1px 6px; border-radius:8px; white-space:nowrap;
}
.rg-node-wrap { position:relative; }
.rg-empty { text-align:center; padding:40px; color:var(--text-muted); font-size:13px; }

/* Header */
.cp-profile-hdr {
    display:flex; align-items:flex-start; gap:16px;
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--radius-lg); padding:20px 24px; margin-bottom:16px;
}
.cp-avatar-lg {
    width:60px; height:60px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:20px; font-weight:700;
}
.cp-avatar-lg.company { background:#dbeafe; color:#1d4ed8; }
.cp-avatar-lg.fop     { background:#ffedd5; color:#c2410c; }
.cp-avatar-lg.person  { background:#f3f4f6; color:#374151; }
.cp-avatar-lg.other   { background:#f3f4f6; color:#374151; }
.cp-hdr-main { flex:1; min-width:0; }
.cp-hdr-main h1 { margin:0 0 4px; font-size:22px; font-weight:700; line-height:1.2; }
.cp-hdr-badges { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-bottom:6px; }
.cp-hdr-contacts { display:flex; gap:16px; flex-wrap:wrap; font-size:13px; color:var(--text-muted); margin-top:6px; }
.cp-hdr-contacts a { color:var(--text-muted); }
.cp-hdr-contacts a:hover { color:var(--blue); }
.cp-hdr-contacts span { display:inline-flex; align-items:center; gap:4px; }
.cp-hdr-actions { display:flex; gap:8px; flex-shrink:0; align-items:flex-start; }

/* Stats strip */
.cp-stats-row {
    display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px;
}
.cp-stat-box {
    background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius);
    padding:14px 16px;
}
.cp-stat-box .val { font-size:22px; font-weight:700; line-height:1; margin-bottom:3px; }
.cp-stat-box .lbl { font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.3px; }
.cp-stat-box .val.green { color:var(--green); }
.cp-stat-box .val.muted { color:var(--text-faint); font-size:16px; }

/* Tabs */
.cp-tabs-nav {
    display:flex; gap:0; border-bottom:2px solid var(--border);
    margin-bottom:16px; overflow-x:auto;
}
.cp-tab-lnk {
    padding:8px 16px; font-size:13px; font-weight:600; color:var(--text-muted);
    text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px;
    white-space:nowrap; transition:color .12s, border-color .12s;
}
.cp-tab-lnk:hover { color:var(--text); text-decoration:none; }
.cp-tab-lnk.active { color:var(--blue); border-bottom-color:var(--blue); }

.cp-tab-panel { display:none; }
.cp-tab-panel.active { display:block; }

/* Form layout */
.cp-form-grid {
    display:grid; grid-template-columns:1fr 1fr; gap:0 20px;
}
.cp-form-grid .form-row { margin-bottom:12px; }
.cp-form-grid .form-row.full { grid-column:1/-1; }
.form-row label { display:block; font-size:12px; font-weight:600; color:var(--text-muted); margin-bottom:4px; }
.form-row input[type=text],.form-row input[type=email],.form-row input[type=date],
.form-row select,.form-row textarea {
    width:100%; padding:7px 10px; border:1px solid var(--border-input);
    border-radius:var(--radius-sm); font-size:13px; font-family:var(--font);
    background:#fff; outline:none; transition:border-color .12s;
}
.form-row input:focus,.form-row select:focus,.form-row textarea:focus { border-color:var(--blue-light); }
.form-row textarea { resize:vertical; min-height:60px; }

/* Type segmented control */
.cp-type-seg { display:flex; gap:0; margin-bottom:16px; border:1px solid var(--border-input); border-radius:var(--radius-sm); overflow:hidden; width:fit-content; }
.cp-type-btn { padding:6px 16px; font-size:12px; font-weight:600; border:none; background:#fff; color:var(--text-muted); cursor:pointer; border-right:1px solid var(--border-input); font-family:var(--font); transition:background .12s,color .12s; }
.cp-type-btn:last-child { border-right:none; }
.cp-type-btn.active { background:var(--blue); color:#fff; }
.cp-type-btn:not(.active):hover { background:var(--blue-bg); color:var(--blue); }
.cp-form-section-hd { grid-column:1/-1; font-size:12px; font-weight:700; color:var(--text); margin-top:4px; padding-top:12px; border-top:1px solid var(--border); }

/* Contacts tab */
.cp-contact-cards { display:flex; flex-direction:column; gap:10px; }
.cp-contact-card {
    background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius);
    padding:12px 16px; display:flex; align-items:flex-start; gap:12px;
}
.cp-contact-avatar {
    width:38px; height:38px; border-radius:50%; background:var(--bg-hover);
    display:flex; align-items:center; justify-content:center;
    font-size:13px; font-weight:700; color:var(--text-muted); flex-shrink:0;
}
.cp-contact-info { flex:1; min-width:0; }
.cp-contact-name { font-weight:600; margin-bottom:2px; }
.cp-contact-role { font-size:11px; color:var(--text-muted); margin-bottom:4px; }
.cp-contact-row { font-size:12px; color:var(--text-muted); display:flex; gap:12px; flex-wrap:wrap; }
.cp-contact-row a { color:var(--text-muted); }
.cp-contact-row a:hover { color:var(--blue); }
.cp-contact-actions { flex-shrink:0; display:flex; gap:6px; }

/* Relations tab */
.cp-group-box {
    background:var(--blue-bg); border:1px solid #bfdbfe; border-radius:var(--radius);
    padding:16px; margin-bottom:16px;
}
.cp-group-box h4 { margin:0 0 10px; font-size:13px; font-weight:700; color:var(--blue); }
.cp-group-members { display:flex; flex-wrap:wrap; gap:8px; }
.cp-group-member {
    display:flex; align-items:center; gap:8px; padding:7px 12px;
    background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-sm);
    text-decoration:none; color:var(--text); transition:border-color .12s, background .12s;
}
.cp-group-member:hover { border-color:var(--blue-light); background:var(--bg-hover); text-decoration:none; }
.cp-group-member.current { border-color:var(--blue); background:var(--blue-bg); }
.cp-group-member .icon { width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.cp-group-member .icon.company { background:#dbeafe; color:#1d4ed8; }
.cp-group-member .icon.fop     { background:#ffedd5; color:#c2410c; }
.cp-rel-table { width:100%; border-collapse:collapse; }
.cp-rel-table td { padding:8px 10px; border-bottom:1px solid var(--border); font-size:13px; }
.cp-rel-table tr:last-child td { border-bottom:none; }

/* Orders tab */
.cp-orders-table { width:100%; border-collapse:collapse; }
.cp-orders-table th { padding:8px 12px; border-bottom:2px solid var(--border); font-size:11px; text-align:left; text-transform:uppercase; letter-spacing:.3px; color:var(--text-muted); font-weight:600; }
.cp-orders-table td { padding:8px 12px; border-bottom:1px solid var(--border); font-size:13px; }
.cp-orders-table tr:last-child td { border-bottom:none; }
.cp-orders-table a { color:var(--text); font-weight:600; }
.cp-orders-table a:hover { color:var(--blue); }

/* Group selector in form */
.cp-group-row { display:flex; gap:8px; align-items:center; }
.cp-group-row select { flex:1; }
.cp-group-row .btn { height:32px; padding:0 10px; font-size:12px; flex-shrink:0; }

/* Info display (Реквізити view-mode) */
.cp-info-section { margin-bottom:18px; }
.cp-info-section:last-child { margin-bottom:0; }
.cp-info-section-hd { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid var(--border); }
.cp-info-pairs { display:grid; grid-template-columns:150px 1fr; gap:7px 16px; }
.cp-info-pairs .lbl { font-size:12px; font-weight:600; color:var(--text-muted); }
.cp-info-pairs .val { font-size:13px; }
.cp-info-pairs a { color:var(--blue); }
.cp-req-empty { text-align:center; padding:28px; color:var(--text-muted); font-size:13px; }

/* Activity feed */
.act-add-wrap { margin-bottom:16px; }
.act-add-wrap textarea { width:100%; padding:8px 10px; border:1px solid var(--border-input); border-radius:var(--radius-sm); font-size:13px; font-family:var(--font); resize:vertical; min-height:56px; box-sizing:border-box; }
.act-add-wrap textarea:focus { border-color:var(--blue-light); outline:none; }
.act-add-row { display:flex; gap:8px; margin-top:8px; align-items:flex-end; }
.act-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
.act-item:last-child { border-bottom:none; }
.act-icon { width:32px; height:32px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:15px; margin-top:2px; }
.act-icon.note  { background:#f3f4f6; }
.act-icon.order { background:#dbeafe; }
.act-body { flex:1; min-width:0; }
.act-meta { font-size:11px; color:var(--text-muted); margin-bottom:3px; }
.act-content { font-size:13px; line-height:1.5; white-space:pre-wrap; word-break:break-word; }
.act-order-info { font-size:13px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.act-empty { text-align:center; padding:32px; color:var(--text-muted); font-size:13px; }

/* Toast is in ui.css */

/* ── Relations CRUD ───────────────────────────────────────────────── */
.rl-toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.rl-toolbar h3 { margin:0; font-size:14px; font-weight:700; }
.rl-add-form { background:var(--bg-page); border:1px solid var(--border); border-radius:var(--radius-sm); padding:14px 16px; margin-bottom:14px; display:none; }
.rl-add-form.open { display:block; }
.rl-form-row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:10px; }
.rl-form-row:last-child { margin-bottom:0; }
.rl-form-group { display:flex; flex-direction:column; gap:4px; }
.rl-form-group label { font-size:11px; font-weight:600; color:var(--text-muted); }
.rl-form-group input, .rl-form-group select { height:32px; padding:0 9px; border:1px solid var(--border-input); border-radius:var(--radius-sm); font-size:13px; font-family:var(--font); background:#fff; }
.rl-form-group input:focus, .rl-form-group select:focus { border-color:var(--blue-light); outline:none; }
.rl-cp-picker-wrap { position:relative; flex:1; min-width:220px; }
.rl-cp-picker-wrap input { width:100%; box-sizing:border-box; }
.rl-cp-dd { position:absolute; top:100%; left:0; right:0; z-index:200; background:#fff; border:1px solid var(--border-input); border-top:none; border-radius:0 0 var(--radius-sm) var(--radius-sm); max-height:200px; overflow-y:auto; box-shadow:var(--shadow); }
.rl-cp-opt { padding:8px 10px; font-size:13px; cursor:pointer; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
.rl-cp-opt:last-child { border-bottom:none; }
.rl-cp-opt:hover { background:var(--blue-bg); }
.rl-cp-opt .rl-cp-badge { font-size:10px; font-weight:600; padding:1px 5px; border-radius:3px; background:#e5e7eb; color:#6b7280; flex-shrink:0; }
.rl-dir-toggle { display:flex; align-items:center; gap:6px; height:32px; white-space:nowrap; font-size:12px; color:var(--text-muted); flex-shrink:0; }
.rl-dir-toggle button { border:none; background:var(--border); border-radius:var(--radius-sm); padding:4px 8px; cursor:pointer; font-size:14px; line-height:1; }
.rl-dir-toggle button:hover { background:var(--blue-bg); }
.rl-primary-wrap { display:flex; align-items:center; gap:6px; height:32px; font-size:12px; font-weight:600; white-space:nowrap; cursor:pointer; }
.rl-form-actions { display:flex; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid var(--border); }

/* Relations table */
.rl-table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:16px; }
.rl-table th { font-size:11px; font-weight:600; color:var(--text-muted); text-align:left; padding:0 8px 8px; border-bottom:2px solid var(--border); white-space:nowrap; }
.rl-table td { padding:9px 8px; border-bottom:1px solid var(--border); vertical-align:middle; }
.rl-table tr:last-child td { border-bottom:none; }
.rl-cp-name { font-weight:600; color:var(--text); text-decoration:none; }
.rl-cp-name:hover { color:var(--blue); }
.rl-type-badge { display:inline-block; font-size:10px; font-weight:600; padding:1px 6px; border-radius:10px; background:#e5e7eb; color:#6b7280; margin-left:5px; vertical-align:middle; }
.rl-type-badge.company { background:#dbeafe; color:#1d4ed8; }
.rl-type-badge.fop     { background:#e0f2fe; color:#0369a1; }
.rl-type-badge.person  { background:#f3e8ff; color:#7c3aed; }
.rl-role-pill { display:inline-block; font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px; background:#f3f4f6; color:#374151; }
.rl-role-pill.role-dir { background:#dcfce7; color:#15803d; }
.rl-role-pill.role-acc { background:#fef9c3; color:#a16207; }
.rl-role-pill.role-mgr { background:#ede9fe; color:#6d28d9; }
.rl-dir-arrow { font-size:16px; color:var(--text-muted); padding:0 4px; }
.rl-primary-star { color:var(--blue); font-size:14px; }
.rl-del-btn { border:none; background:none; cursor:pointer; padding:4px 6px; color:#d1d5db; border-radius:4px; line-height:1; }
.rl-del-btn:hover { color:var(--red); background:#fef2f2; }
.rl-row-actions { white-space:nowrap; display:flex; align-items:center; gap:2px; }
.rl-edit-btn { font-size:11px; color:var(--blue); opacity:0; transition:opacity .15s; }
.rl-table tr:hover .rl-edit-btn { opacity:1; }
.rl-form-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted); margin-bottom:12px; }
.rl-cp-picker-wrap { display:flex; }
.rl-cp-picker-wrap select { border-radius:4px 0 0 4px; flex-shrink:0; }
.rl-cp-picker-wrap input  { border-radius:0 4px 4px 0; border-left:none; flex:1; }
.rl-empty { text-align:center; padding:24px; color:var(--text-muted); font-size:13px; }

/* Graph section label */
.rg-section-hd { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:12px; padding-top:4px; border-top:1px solid var(--border); }

/* ── Files tab ─────────────────────────────────────────────────────────── */
.fl-toolbar { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.fl-toolbar h3 { margin:0; font-size:15px; font-weight:700; flex-shrink:0; }
.fl-filters { display:flex; gap:8px; flex:1; }
.fl-sel { height:32px; padding:0 8px; border:1px solid var(--border-input); border-radius:var(--radius-sm); font-size:13px; font-family:var(--font); background:#fff; cursor:pointer; }
.fl-upload-form { background:var(--bg-page); border:1px solid var(--border); border-radius:var(--radius-sm); padding:14px 16px; margin-bottom:14px; display:none; }
.fl-upload-form.open { display:block; }
.fl-upload-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px; }
.fl-fg { display:flex; flex-direction:column; gap:4px; }
.fl-fg label { font-size:11px; font-weight:600; color:var(--text-muted); }
.fl-fg input[type=text], .fl-fg select { height:32px; padding:0 9px; border:1px solid var(--border-input); border-radius:var(--radius-sm); font-size:13px; font-family:var(--font); }
.fl-fg input[type=file] { font-size:12px; padding:4px 0; }
.fl-required { color:var(--red); }
.fl-upload-actions { display:flex; align-items:center; gap:8px; }
.fl-err { color:var(--red); font-size:12px; margin-bottom:10px; }

/* File list table */
.fl-table { width:100%; border-collapse:collapse; font-size:13px; }
.fl-table th { font-size:11px; font-weight:600; color:var(--text-muted); text-align:left; padding:0 8px 8px; border-bottom:2px solid var(--border); white-space:nowrap; }
.fl-table td { padding:9px 8px; border-bottom:1px solid var(--border); vertical-align:middle; }
.fl-table tr:last-child td { border-bottom:none; }
.fl-file-icon { font-size:18px; line-height:1; }
.fl-file-name { font-weight:600; color:var(--text); text-decoration:none; }
.fl-file-name:hover { color:var(--blue); }
.fl-comment { font-size:11px; color:var(--text-muted); margin-top:2px; }
.fl-type-badge { display:inline-block; font-size:10px; font-weight:600; padding:2px 7px; border-radius:10px; background:#e5e7eb; color:#6b7280; }
.fl-size { color:var(--text-muted); white-space:nowrap; }
.fl-date { color:var(--text-muted); white-space:nowrap; }
.fl-del-btn { border:none; background:none; cursor:pointer; padding:4px 6px; color:#d1d5db; border-radius:4px; line-height:1; opacity:0; transition:opacity .15s; }
.fl-table tr:hover .fl-del-btn { opacity:1; }
.fl-del-btn:hover { color:var(--red); background:#fef2f2; }
.fl-empty { text-align:center; padding:32px; color:var(--text-muted); font-size:13px; }
.fl-loading { text-align:center; padding:24px; color:var(--text-muted); font-size:13px; }
.cpp-msg-bubble .chat-link { color:inherit; text-decoration:underline; word-break:break-all; opacity:.9; }
.cpp-msg-bubble .chat-link:hover { opacity:1; }
</style>

<div class="cp-view">

<!-- Back + breadcrumb -->
<div style="display:flex; align-items:center; gap:10px; margin-bottom:12px">
    <a href="/counterparties?select=<?php echo (int)$cp['id']; ?>" class="btn btn-sm" style="gap:5px; display:inline-flex; align-items:center;">
        <svg width="14" height="14" fill="none" viewBox="0 0 16 16"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Повернутись
    </a>
    <span style="color:var(--border-input)">|</span>
    <span class="breadcrumb" style="margin:0"><?php echo htmlspecialchars($cp['name']); ?></span>
</div>

<!-- Profile header -->
<div class="cp-profile-hdr">
    <div class="cp-avatar-lg <?php echo htmlspecialchars($cp['type']); ?>"><?php echo $initials; ?></div>
    <div class="cp-hdr-main">
        <h1><?php echo htmlspecialchars($cp['name']); ?></h1>
        <div class="cp-hdr-badges">
            <span class="badge <?php echo CounterpartyRepository::typeBadgeClass($cp['type']); ?>">
                <?php echo CounterpartyRepository::typeLabel($cp['type']); ?>
            </span>
            <?php if ($cp['status']): ?>
                <span class="badge badge-green">Активний</span>
            <?php else: ?>
                <span class="badge badge-red">Архів</span>
            <?php endif; ?>
            <?php if ($cp['group_name']): ?>
                <a href="/counterparties?group_id=<?php echo (int)$cp['group_id']; ?>" class="badge" style="background:var(--blue-bg);color:var(--blue);border:1px solid #bfdbfe;">
                    <?php echo htmlspecialchars($cp['group_name']); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php if ($phone || $email || (isset($cp['website']) && $cp['website'])): ?>
        <div class="cp-hdr-contacts">
            <?php if ($phone): ?>
                <span><svg width="13" height="13" fill="none" viewBox="0 0 16 16"><path d="M3 2h3l1 4-1.5 1.5a11 11 0 0 0 3 3L10 9l4 1v3a1 1 0 0 1-1 1A13 13 0 0 1 2 3a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/></svg>
                    <a href="tel:<?php echo htmlspecialchars($phone); ?>"><?php echo htmlspecialchars($phone); ?></a></span>
            <?php endif; ?>
            <?php if ($email): ?>
                <span><svg width="13" height="13" fill="none" viewBox="0 0 16 16"><rect x="1.5" y="3.5" width="13" height="9" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M1.5 5l6.5 5 6.5-5" stroke="currentColor" stroke-width="1.4"/></svg>
                    <a href="mailto:<?php echo htmlspecialchars($email); ?>"><?php echo htmlspecialchars($email); ?></a></span>
            <?php endif; ?>
            <?php if (!empty($cp['website'])): ?>
                <span><svg width="13" height="13" fill="none" viewBox="0 0 16 16"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M1.5 8h13M8 1.5a10 10 0 0 1 0 13M8 1.5a10 10 0 0 0 0 13" stroke="currentColor" stroke-width="1.4"/></svg>
                    <a href="<?php echo htmlspecialchars($cp['website']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($cp['website']); ?></a></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="cp-hdr-actions">
        <button class="btn" id="btnToggleStatus" data-id="<?php echo $id; ?>" data-status="<?php echo $cp['status']; ?>">
            <?php echo $cp['status'] ? 'В архів' : 'Відновити'; ?>
        </button>
    </div>
</div>

<!-- Stats strip -->
<div class="cp-stats-row">
    <div class="cp-stat-box">
        <div class="val <?php echo $stats['order_count'] > 0 ? '' : 'muted'; ?>">
            <?php echo (int)$stats['order_count']; ?>
        </div>
        <div class="lbl">Замовлень</div>
    </div>
    <div class="cp-stat-box">
        <div class="val <?php echo $stats['ltv'] > 0 ? 'green' : 'muted'; ?>">
            <?php echo $stats['ltv'] > 0 ? '₴' . number_format((float)$stats['ltv'], 0, '.', ' ') : '—'; ?>
        </div>
        <div class="lbl">Загальна сума (LTV)</div>
    </div>
    <div class="cp-stat-box">
        <div class="val <?php echo $stats['avg_check'] > 0 ? '' : 'muted'; ?>">
            <?php echo $stats['avg_check'] > 0 ? '₴' . number_format((float)$stats['avg_check'], 0, '.', ' ') : '—'; ?>
        </div>
        <div class="lbl">Середній чек</div>
    </div>
    <div class="cp-stat-box">
        <?php if ($stats['last_order_at']): ?>
            <?php
            $lastDt = new DateTime($stats['last_order_at']);
            $now    = new DateTime();
            $diff   = $now->diff($lastDt);
            if ($diff->days === 0)       $lastStr = 'Сьогодні';
            elseif ($diff->days === 1)   $lastStr = 'Вчора';
            elseif ($diff->days <= 7)    $lastStr = $diff->days . ' дн. тому';
            elseif ($diff->days <= 30)   $lastStr = (int)($diff->days/7) . ' тиж. тому';
            elseif ($diff->days <= 365)  $lastStr = (int)($diff->days/30) . ' міс. тому';
            else                         $lastStr = (int)($diff->days/365) . ' р. тому';
            ?>
            <div class="val" style="font-size:15px"><?php echo $lastStr; ?></div>
        <?php else: ?>
            <div class="val muted">—</div>
        <?php endif; ?>
        <div class="lbl">Останнє замовлення</div>
    </div>
</div>

<!-- Tabs nav -->
<div class="cp-tabs-nav">
    <a href="#requisites" class="cp-tab-lnk <?php echo ($tab==='requisites'||!$tab)?'active':''; ?>" data-tab="requisites">Реквізити</a>
    <?php if ($isCompany): ?>
    <a href="#contacts" class="cp-tab-lnk <?php echo $tab==='contacts'?'active':''; ?>" data-tab="contacts">
        Контакти <?php if (count($contacts)): ?><span style="font-size:11px;color:var(--text-faint)">(<?php echo count($contacts); ?>)</span><?php endif; ?>
    </a>
    <?php endif; ?>
    <a href="#relations" class="cp-tab-lnk <?php echo $tab==='relations'?'active':''; ?>" data-tab="relations">Зв'язки</a>
    <a href="#documents" class="cp-tab-lnk <?php echo $tab==='documents'?'active':''; ?>" data-tab="documents">
        Документи <?php if ($stats['order_count']>0): ?><span style="font-size:11px;color:var(--text-faint)">(<?php echo (int)$stats['order_count']; ?>)</span><?php endif; ?>
    </a>
    <a href="#files" class="cp-tab-lnk <?php echo $tab==='files'?'active':''; ?>" data-tab="files">Файли</a>
    <a href="#activity" class="cp-tab-lnk <?php echo $tab==='activity'?'active':''; ?>" data-tab="activity">Активність</a>
    <a href="#analytics" class="cp-tab-lnk <?php echo $tab==='analytics'?'active':''; ?>" data-tab="analytics">Аналітика</a>
</div>

<!-- ═══ Tab: Реквізити ══════════════════════════════════════════════════════ -->
<div class="cp-tab-panel <?php echo ($tab==='requisites'||!$tab)?'active':''; ?>" id="panel-requisites">
<div class="card" style="padding:24px">

    <!-- Tab header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3 style="margin:0; font-size:15px; font-weight:700">Реквізити</h3>
        <button class="btn btn-sm" id="btnEditToggle">Редагувати</button>
    </div>

    <!-- View mode -->
    <div id="reqViewMode">
    <?php
    $hasReqData = false;
    if ($isCompany) {
        $hasReqData = $cp['short_name'] || $cp['full_legal_name'] || $cp['okpo'] || $cp['inn'] || $cp['vat_number']
                   || $cp['iban'] || $cp['bank_name'] || $cp['mfo']
                   || $cp['company_phone'] || $cp['company_email'] || $cp['website']
                   || $cp['legal_address'] || $cp['actual_address'] || $cp['description'];
    } else {
        $hasReqData = $cp['last_name'] || $cp['first_name'] || $cp['person_phone'] || $cp['phone_alt']
                   || $cp['person_email'] || $cp['telegram'] || $cp['viber'] || $cp['description'];
    }
    ?>
    <?php if (!$hasReqData && !$cp['group_name']): ?>
        <div class="cp-req-empty">Реквізити ще не заповнені. Натисніть «Редагувати».</div>
    <?php elseif ($isCompany): ?>
        <?php if ($cp['short_name'] || $cp['full_legal_name'] || $cp['okpo'] || $cp['inn'] || $cp['vat_number']): ?>
        <div class="cp-info-section">
            <div class="cp-info-section-hd">Ідентифікація</div>
            <div class="cp-info-pairs">
                <?php if ($cp['short_name']): ?>
                <span class="lbl">Скорочена назва</span><span class="val"><?php echo htmlspecialchars($cp['short_name']); ?></span>
                <?php endif; ?>
                <?php if ($cp['full_legal_name']): ?>
                <span class="lbl">Повна назва</span><span class="val"><?php echo htmlspecialchars($cp['full_legal_name']); ?></span>
                <?php endif; ?>
                <?php if ($cp['okpo']): ?>
                <span class="lbl">ЄДРПОУ</span><span class="val"><?php echo htmlspecialchars($cp['okpo']); ?></span>
                <?php endif; ?>
                <?php if ($cp['inn']): ?>
                <span class="lbl">ІПН</span><span class="val"><?php echo htmlspecialchars($cp['inn']); ?></span>
                <?php endif; ?>
                <?php if ($cp['vat_number']): ?>
                <span class="lbl">Номер ПДВ</span><span class="val"><?php echo htmlspecialchars($cp['vat_number']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($cp['iban'] || $cp['bank_name'] || $cp['mfo']): ?>
        <div class="cp-info-section">
            <div class="cp-info-section-hd">Банківські реквізити</div>
            <div class="cp-info-pairs">
                <?php if ($cp['iban']): ?>
                <span class="lbl">IBAN</span><span class="val"><?php echo htmlspecialchars($cp['iban']); ?></span>
                <?php endif; ?>
                <?php if ($cp['bank_name']): ?>
                <span class="lbl">Банк</span><span class="val"><?php echo htmlspecialchars($cp['bank_name']); ?></span>
                <?php endif; ?>
                <?php if ($cp['mfo']): ?>
                <span class="lbl">МФО</span><span class="val"><?php echo htmlspecialchars($cp['mfo']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($cp['company_phone'] || $cp['company_email'] || $cp['website'] || $cp['legal_address'] || $cp['actual_address']): ?>
        <div class="cp-info-section">
            <div class="cp-info-section-hd">Контакти та адреси</div>
            <div class="cp-info-pairs">
                <?php if ($cp['company_phone']): ?>
                <span class="lbl">Телефон</span><span class="val"><a href="tel:<?php echo htmlspecialchars($cp['company_phone']); ?>"><?php echo htmlspecialchars($cp['company_phone']); ?></a></span>
                <?php endif; ?>
                <?php if ($cp['company_email']): ?>
                <span class="lbl">Email</span><span class="val"><a href="mailto:<?php echo htmlspecialchars($cp['company_email']); ?>"><?php echo htmlspecialchars($cp['company_email']); ?></a></span>
                <?php endif; ?>
                <?php if ($cp['website']): ?>
                <span class="lbl">Сайт</span><span class="val"><a href="<?php echo htmlspecialchars($cp['website']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($cp['website']); ?></a></span>
                <?php endif; ?>
                <?php if ($cp['legal_address']): ?>
                <span class="lbl">Юр. адреса</span><span class="val"><?php echo htmlspecialchars($cp['legal_address']); ?></span>
                <?php endif; ?>
                <?php if ($cp['actual_address']): ?>
                <span class="lbl">Факт. адреса</span><span class="val"><?php echo htmlspecialchars($cp['actual_address']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($cp['description']): ?>
        <div class="cp-info-section">
            <div class="cp-info-section-hd">Нотатки</div>
            <div style="font-size:13px; white-space:pre-wrap; line-height:1.5"><?php echo htmlspecialchars($cp['description']); ?></div>
        </div>
        <?php endif; ?>

    <?php elseif ($isPerson): ?>
        <?php if ($cp['last_name'] || $cp['first_name'] || $cp['middle_name'] || $cp['position_name'] || $cp['birth_date']): ?>
        <div class="cp-info-section">
            <div class="cp-info-section-hd">Особиста інформація</div>
            <div class="cp-info-pairs">
                <?php if ($cp['first_name']): ?>
                <span class="lbl">Ім'я</span><span class="val"><?php echo htmlspecialchars($cp['first_name']); ?></span>
                <?php endif; ?>
                <?php if ($cp['last_name']): ?>
                <span class="lbl">Прізвище</span><span class="val"><?php echo htmlspecialchars($cp['last_name']); ?></span>
                <?php endif; ?>
                <?php if ($cp['middle_name']): ?>
                <span class="lbl">По батькові</span><span class="val"><?php echo htmlspecialchars($cp['middle_name']); ?></span>
                <?php endif; ?>
                <?php if ($cp['position_name']): ?>
                <span class="lbl">Посада</span><span class="val"><?php echo htmlspecialchars($cp['position_name']); ?></span>
                <?php endif; ?>
                <?php if ($cp['birth_date'] && $cp['birth_date'] !== '0000-00-00'): ?>
                <span class="lbl">Дата народження</span><span class="val"><?php echo htmlspecialchars($cp['birth_date']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($cp['person_phone'] || $cp['phone_alt'] || $cp['person_email'] || $cp['telegram'] || $cp['viber']): ?>
        <div class="cp-info-section">
            <div class="cp-info-section-hd">Контакти</div>
            <div class="cp-info-pairs">
                <?php if ($cp['person_phone']): ?>
                <span class="lbl">Телефон</span><span class="val"><a href="tel:<?php echo htmlspecialchars($cp['person_phone']); ?>"><?php echo htmlspecialchars($cp['person_phone']); ?></a></span>
                <?php endif; ?>
                <?php if ($cp['phone_alt']): ?>
                <span class="lbl">Телефон (дод.)</span><span class="val"><a href="tel:<?php echo htmlspecialchars($cp['phone_alt']); ?>"><?php echo htmlspecialchars($cp['phone_alt']); ?></a></span>
                <?php endif; ?>
                <?php if ($cp['person_email']): ?>
                <span class="lbl">Email</span><span class="val"><a href="mailto:<?php echo htmlspecialchars($cp['person_email']); ?>"><?php echo htmlspecialchars($cp['person_email']); ?></a></span>
                <?php endif; ?>
                <?php if ($cp['telegram']): ?>
                <span class="lbl">Telegram</span><span class="val"><?php echo htmlspecialchars($cp['telegram']); ?></span>
                <?php endif; ?>
                <?php if ($cp['viber']): ?>
                <span class="lbl">Viber</span><span class="val"><?php echo htmlspecialchars($cp['viber']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($cp['description']): ?>
        <div class="cp-info-section">
            <div class="cp-info-section-hd">Нотатки</div>
            <div style="font-size:13px; white-space:pre-wrap; line-height:1.5"><?php echo htmlspecialchars($cp['description']); ?></div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

        <?php if ($cp['group_name']): ?>
        <div class="cp-info-section">
            <div class="cp-info-pairs">
                <span class="lbl">Група компаній</span>
                <span class="val">
                    <a href="/counterparties?group_id=<?php echo (int)$cp['group_id']; ?>"><?php echo htmlspecialchars($cp['group_name']); ?></a>
                    <?php if ($cp['group_is_head']): ?><span style="font-size:11px;color:var(--text-muted)"> (Головна)</span><?php endif; ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div><!-- /reqViewMode -->

    <!-- Edit mode (hidden by default) -->
    <div id="reqEditMode" class="hidden">
    <div id="reqFormError" class="modal-error hidden" style="margin-bottom:12px"></div>

    <?php
    $initPhone = ($cp['type'] === 'person') ? (string)$cp['person_phone'] : (string)$cp['company_phone'];
    $initEmail = ($cp['type'] === 'person') ? (string)$cp['person_email'] : (string)$cp['company_email'];
    $initNotes = ($cp['type'] === 'person') ? (string)$cp['person_notes'] : (string)$cp['company_notes'];
    ?>
    <!-- Type selector -->
    <input type="hidden" id="fType" value="<?php echo htmlspecialchars($cp['type']); ?>">
    <div class="cp-type-seg" id="fTypeSegs">
        <button type="button" class="cp-type-btn" data-type="company">Юридична особа</button>
        <button type="button" class="cp-type-btn" data-type="fop">ФОП</button>
        <button type="button" class="cp-type-btn" data-type="person">Фізична особа</button>
    </div>

    <div class="cp-form-grid" id="fFormGrid">

        <!-- Назва: company + fop -->
        <div class="form-row full fg-company fg-fop">
            <label>Назва / ПІБ ФОП</label>
            <input type="text" id="fName" value="<?php echo htmlspecialchars($cp['name']); ?>">
        </div>

        <!-- ПІБ: person only -->
        <div class="form-row fg-person">
            <label>Ім'я</label>
            <input type="text" id="fFirstName" value="<?php echo htmlspecialchars((string)$cp['first_name']); ?>">
        </div>
        <div class="form-row fg-person">
            <label>Прізвище</label>
            <input type="text" id="fLastName" value="<?php echo htmlspecialchars((string)$cp['last_name']); ?>">
        </div>
        <div class="form-row fg-person">
            <label>По батькові</label>
            <input type="text" id="fMiddleName" value="<?php echo htmlspecialchars((string)$cp['middle_name']); ?>">
        </div>
        <div class="form-row fg-person">
            <label>Посада</label>
            <input type="text" id="fPosition" value="<?php echo htmlspecialchars((string)$cp['position_name']); ?>">
        </div>

        <!-- Реквізити: company only -->
        <div class="cp-form-section-hd fg-company">Реквізити</div>
        <div class="form-row fg-company">
            <label>Коротка назва</label>
            <input type="text" id="fShortName" value="<?php echo htmlspecialchars((string)$cp['short_name']); ?>">
        </div>
        <div class="form-row fg-company">
            <label>Повна юридична назва</label>
            <input type="text" id="fFullName" value="<?php echo htmlspecialchars((string)$cp['full_legal_name']); ?>">
        </div>
        <div class="form-row fg-company">
            <label>ЄДРПОУ</label>
            <input type="text" id="fOkpo" value="<?php echo htmlspecialchars((string)$cp['okpo']); ?>" maxlength="12" placeholder="12345678">
        </div>

        <!-- ІПН + ПДВ: company + fop -->
        <div class="form-row fg-company fg-fop">
            <label>ІПН</label>
            <input type="text" id="fInn" value="<?php echo htmlspecialchars((string)$cp['inn']); ?>" maxlength="12" placeholder="1234567890">
        </div>
        <div class="form-row fg-company fg-fop">
            <label>Свідоцтво ПДВ</label>
            <input type="text" id="fVat" value="<?php echo htmlspecialchars((string)$cp['vat_number']); ?>" placeholder="№ свідоцтва або НЕ ПЛАТНИК">
        </div>

        <!-- Банківські: company + fop -->
        <div class="cp-form-section-hd fg-company fg-fop">Банківські реквізити</div>
        <div class="form-row full fg-company fg-fop">
            <label>IBAN</label>
            <input type="text" id="fIban" value="<?php echo htmlspecialchars((string)$cp['iban']); ?>" placeholder="UA…" maxlength="34">
        </div>
        <div class="form-row fg-company fg-fop">
            <label>Банк</label>
            <input type="text" id="fBankName" value="<?php echo htmlspecialchars((string)$cp['bank_name']); ?>">
        </div>
        <div class="form-row fg-company fg-fop">
            <label>МФО</label>
            <input type="text" id="fMfo" value="<?php echo htmlspecialchars((string)$cp['mfo']); ?>" maxlength="10">
        </div>

        <!-- Контакти: всі -->
        <div class="cp-form-section-hd">Контакти</div>
        <div class="form-row">
            <label>Телефон</label>
            <input type="text" id="fPhone" value="<?php echo htmlspecialchars($initPhone); ?>">
        </div>
        <div class="form-row fg-person">
            <label>Телефон (дод.)</label>
            <input type="text" id="fPhoneAlt" value="<?php echo htmlspecialchars((string)$cp['phone_alt']); ?>">
        </div>
        <div class="form-row">
            <label>Email</label>
            <input type="email" id="fEmail" value="<?php echo htmlspecialchars($initEmail); ?>">
        </div>
        <div class="form-row fg-company">
            <label>Сайт</label>
            <input type="text" id="fWebsite" value="<?php echo htmlspecialchars((string)$cp['website']); ?>" placeholder="https://…">
        </div>
        <div class="form-row fg-person">
            <label>Дата народження</label>
            <input type="date" id="fBirthDate" value="<?php echo htmlspecialchars((string)$cp['birth_date']); ?>">
        </div>
        <div class="form-row fg-person">
            <label>Telegram</label>
            <input type="text" id="fTelegram" value="<?php echo htmlspecialchars((string)$cp['telegram']); ?>" placeholder="@username">
        </div>
        <div class="form-row fg-person">
            <label>Viber</label>
            <input type="text" id="fViber" value="<?php echo htmlspecialchars((string)$cp['viber']); ?>">
        </div>

        <!-- Адреси: company + fop -->
        <div class="cp-form-section-hd fg-company fg-fop">Адреси</div>
        <div class="form-row full fg-company fg-fop">
            <label>Юридична адреса</label>
            <input type="text" id="fLegalAddr" value="<?php echo htmlspecialchars((string)$cp['legal_address']); ?>">
        </div>
        <div class="form-row full fg-company fg-fop">
            <label>Фактична адреса</label>
            <input type="text" id="fActualAddr" value="<?php echo htmlspecialchars((string)$cp['actual_address']); ?>">
        </div>

        <!-- Нотатки: всі -->
        <div class="form-row full">
            <label>Нотатки</label>
            <textarea id="fNotes" rows="3"><?php echo htmlspecialchars($initNotes); ?></textarea>
        </div>

        <!-- Група: company only -->
        <div class="form-row full fg-company" style="border-top:1px solid var(--border); padding-top:12px; margin-top:4px">
            <label>Група компаній</label>
            <div class="cp-group-row">
                <select id="fGroupId">
                    <option value="0">— Без групи —</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo ((int)$cp['group_id'] === (int)$g['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:400;cursor:pointer;flex-shrink:0">
                    <input type="checkbox" id="fGroupIsHead" <?php echo $cp['group_is_head'] ? 'checked' : ''; ?>>
                    Головна компанія групи
                </label>
                <button class="btn btn-ghost btn-sm" id="btnNewGroup" type="button" title="Створити нову групу">+ Нова група</button>
            </div>
        </div>

        <div class="form-row full">
            <label>Опис / коментар</label>
            <textarea id="fDescription" rows="2"><?php echo htmlspecialchars((string)$cp['description']); ?></textarea>
        </div>
    </div>

    <div style="display:flex; gap:8px; margin-top:16px; align-items:center">
        <button class="btn btn-primary" id="btnSaveReq">Зберегти</button>
        <button class="btn" id="btnCancelReq">Скасувати</button>
        <span id="reqSaveStatus" style="font-size:12px; color:var(--text-muted)"></span>
    </div>
    </div><!-- /reqEditMode -->
</div>
</div>


<?php if ($isCompany): ?>
<!-- ═══ Tab: Контакти ════════════════════════════════════════════════════════ -->
<div class="cp-tab-panel <?php echo $tab==='contacts'?'active':''; ?>" id="panel-contacts">
<div class="card" style="padding:24px">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px">
        <h3 style="margin:0; font-size:15px; font-weight:700">Контактні особи</h3>
        <button class="btn btn-primary btn-sm" id="btnAddContact">+ Додати контакт</button>
    </div>

    <div class="cp-contact-cards" id="contactsList">
        <?php if (empty($contacts)): ?>
        <div style="text-align:center; padding:32px; color:var(--text-muted); font-size:13px" id="contactsEmpty">
            Контактних осіб ще немає. Натисніть «+ Додати контакт».
        </div>
        <?php else: ?>
        <?php foreach ($contacts as $c):
            $cInit = mb_strtoupper(mb_substr($c['name'],0,1,'UTF-8'),'UTF-8');
            $roleLabel = CounterpartyRepository::relationTypeLabel($c['relation_type']);
        ?>
        <div class="cp-contact-card" data-rel-id="<?php echo $c['relation_id']; ?>">
            <div class="cp-contact-avatar"><?php echo $cInit; ?></div>
            <div class="cp-contact-info">
                <div class="cp-contact-name">
                    <a href="/counterparties/view?id=<?php echo $c['id']; ?>" style="color:var(--text)">
                        <?php echo htmlspecialchars($c['name']); ?>
                    </a>
                    <?php if ($c['is_primary']): ?>
                        <span class="badge badge-blue" style="font-size:10px; margin-left:4px">Основний</span>
                    <?php endif; ?>
                </div>
                <div class="cp-contact-role">
                    <?php echo htmlspecialchars($roleLabel); ?>
                    <?php if ($c['job_title']): ?> — <?php echo htmlspecialchars($c['job_title']); ?><?php endif; ?>
                </div>
                <div class="cp-contact-row">
                    <?php if ($c['phone']): ?><a href="tel:<?php echo htmlspecialchars($c['phone']); ?>"><?php echo htmlspecialchars($c['phone']); ?></a><?php endif; ?>
                    <?php if ($c['email']): ?><a href="mailto:<?php echo htmlspecialchars($c['email']); ?>"><?php echo htmlspecialchars($c['email']); ?></a><?php endif; ?>
                    <?php if ($c['telegram']): ?><span>TG: <?php echo htmlspecialchars($c['telegram']); ?></span><?php endif; ?>
                </div>
            </div>
            <div class="cp-contact-actions">
                <a href="/counterparties/view?id=<?php echo $c['id']; ?>" class="btn btn-sm" title="Відкрити картку">↗</a>
                <button class="btn btn-sm btn-danger btn-del-contact" data-rel-id="<?php echo $c['relation_id']; ?>" title="Видалити зв'язок">✕</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>
<?php endif; ?>


<!-- ═══ Tab: Зв'язки ════════════════════════════════════════════════════════ -->
<div class="cp-tab-panel <?php echo $tab==='relations'?'active':''; ?>" id="panel-relations">
<div class="card rg-wrap">

<?php
function rgRoleClass($relType) {
    if ($relType === 'director')   return 'role-dir';
    if ($relType === 'accountant') return 'role-acc';
    if ($relType === 'manager')    return 'role-mgr';
    return '';
}
$allRelations = array_merge($contacts, $relations);
$relTypeOptions = array(
    // person ↔ company
    'contact_person'     => 'Контактна особа',
    'director'           => 'Директор',
    'accountant'         => 'Бухгалтер',
    'manager'            => 'Менеджер',
    'signer'             => 'Підписант',
    'employee'           => 'Співробітник',
    'buyer'              => 'Покупець',
    'receiver'           => 'Отримувач',
    'payer'              => 'Платник',
    'department_contact' => 'Контакт підрозділу',
    // company ↔ company
    'subsidiary'         => 'Дочірня компанія',
    'branch'             => 'Філія',
    'partner'            => 'Партнер',
    'supplier'           => 'Постачальник',
    'client'             => 'Клієнт',
    'other'              => 'Інше',
);
?>

<!-- ── CRUD section ───────────────────────────────────────────────────── -->
<div class="rl-toolbar">
    <h3>Зв'язки та контакти</h3>
    <button class="btn btn-primary btn-sm" id="btnAddRelation" type="button">+ Додати зв'язок</button>
</div>

<!-- Add/Edit form (hidden by default) -->
<div class="rl-add-form" id="rlAddForm">
    <div class="rl-form-title" id="rlFormTitle">Новий зв'язок</div>
    <input type="hidden" id="rlRelId" value="">
    <div id="rlAddErr" style="display:none;color:var(--red);font-size:12px;margin-bottom:8px"></div>
    <div class="rl-form-row">
        <!-- Counterparty picker -->
        <div class="rl-form-group" style="flex:1;min-width:200px" id="rlCpPickerGroup">
            <label>Контрагент</label>
            <div class="rl-cp-picker-wrap">
                <select id="rlSearchScope" style="height:32px;font-size:12px;border:1px solid var(--border);border-radius:4px 0 0 4px;padding:0 6px;background:#fff;cursor:pointer">
                    <option value="">Усі</option>
                    <option value="person">Фізособи</option>
                    <option value="business">Юрособи та ФОП</option>
                </select>
                <input type="text" id="rlCpSearch" placeholder="Пошук за назвою або ID…" autocomplete="off" style="border-radius:0 4px 4px 0;border-left:none">
                <div class="rl-cp-dd" id="rlCpDd" style="display:none"></div>
            </div>
        </div>
        <input type="hidden" id="rlCpId" value="">
        <!-- Role -->
        <div class="rl-form-group">
            <label>Роль / тип зв'язку</label>
            <select id="rlRelType">
                <?php foreach ($relTypeOptions as $v => $l): ?>
                <option value="<?php echo $v; ?>"><?php echo $l; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- Job title -->
        <div class="rl-form-group">
            <label>Посада / уточнення</label>
            <input type="text" id="rlJobTitle" placeholder="напр. Головний бухгалтер" style="width:180px">
        </div>
        <!-- Is primary -->
        <label class="rl-primary-wrap">
            <input type="checkbox" id="rlIsPrimary">
            Основний
        </label>
    </div>
    <!-- Direction row (only for new relations) -->
    <div class="rl-dir-toggle" id="rlDirWrap">
        <span id="rlDirLabel"></span>
        <button type="button" id="rlDirSwap" title="Змінити напрямок">⇄</button>
    </div>
    <div class="rl-form-actions">
        <button class="btn btn-primary btn-sm" id="rlBtnSave" type="button">Зберегти</button>
        <button class="btn btn-ghost btn-sm" id="rlBtnCancel" type="button">Скасувати</button>
    </div>
</div>

<!-- Relations table -->
<?php if (empty($allRelations)): ?>
<div class="rl-empty">Зв'язків ще немає. Натисніть «+ Додати зв'язок».</div>
<?php else: ?>
<table class="rl-table">
    <thead>
        <tr>
            <th>Контрагент</th>
            <th>Роль</th>
            <th>Посада</th>
            <th></th><!-- is_primary -->
            <th>Напрям</th>
            <th></th><!-- edit/delete -->
        </tr>
    </thead>
    <tbody>
    <?php foreach ($allRelations as $rel):
        $rType = $rel['type'];
        $rBadgeCls = in_array($rType, array('company','fop','person')) ? $rType : '';
        $rTypeLabel = CounterpartyRepository::typeLabel($rType);
        $roleLabel  = CounterpartyRepository::relationTypeLabel($rel['relation_type']);
        $roleCls    = rgRoleClass($rel['relation_type']);
        $dir        = isset($rel['direction']) ? $rel['direction'] : 'outgoing';
    ?>
    <tr data-rel-id="<?php echo (int)$rel['relation_id']; ?>">
        <td>
            <a href="/counterparties/view?id=<?php echo (int)$rel['id']; ?>" class="rl-cp-name">
                <?php echo htmlspecialchars($rel['name']); ?>
            </a>
            <span class="rl-type-badge <?php echo $rBadgeCls; ?>"><?php echo $rTypeLabel; ?></span>
        </td>
        <td><span class="rl-role-pill <?php echo $roleCls ? 'role-dir' === $roleCls ? 'role-dir' : ($roleCls === 'role-acc' ? 'role-acc' : 'role-mgr') : ''; ?>"><?php echo $roleLabel; ?></span></td>
        <td style="color:var(--text-muted)"><?php echo htmlspecialchars((string)$rel['job_title']); ?></td>
        <td><?php if ($rel['is_primary']): ?><span class="rl-primary-star" title="Основний">★</span><?php endif; ?></td>
        <td class="rl-dir-arrow" title="<?php echo $dir === 'outgoing' ? 'Поточний → Інший' : 'Інший → Поточний'; ?>">
            <?php echo $dir === 'outgoing' ? '→' : '←'; ?>
        </td>
        <td class="rl-row-actions">
            <button class="rl-edit-btn btn btn-ghost btn-xs"
                data-id="<?php echo (int)$rel['relation_id']; ?>"
                data-other-id="<?php echo (int)$rel['id']; ?>"
                data-other-name="<?php echo htmlspecialchars($rel['name'], ENT_QUOTES); ?>"
                data-rel-type="<?php echo htmlspecialchars($rel['relation_type'], ENT_QUOTES); ?>"
                data-job-title="<?php echo htmlspecialchars((string)$rel['job_title'], ENT_QUOTES); ?>"
                data-is-primary="<?php echo $rel['is_primary'] ? '1' : '0'; ?>"
                data-dir="<?php echo $dir === 'outgoing' ? 'out' : 'in'; ?>"
                title="Редагувати">Змінити</button>
            <button class="rl-del-btn" data-id="<?php echo (int)$rel['relation_id']; ?>" title="Видалити зв'язок">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── Graph section ──────────────────────────────────────────────────── -->
<?php if (!empty($groupMembers) || !empty($contacts) || !empty($relations)): ?>
<div class="rg-section-hd">Схема зв'язків</div>

<?php if (!empty($groupMembers)): ?>
<div class="rg-group-strip">
    <div class="rg-group-lbl">Група: <?php echo htmlspecialchars($cp['group_name']); ?></div>
    <div class="rg-group-nodes">
        <?php foreach ($groupMembers as $gi => $m):
            $mInit  = mb_strtoupper(mb_substr($m['name'],0,1,'UTF-8'),'UTF-8');
            $isSelf = ((int)$m['id'] === $id);
        ?>
        <?php if ($gi > 0): ?><span class="rg-group-sep">—</span><?php endif; ?>
        <a href="/counterparties/view?id=<?php echo $m['id']; ?>" class="rg-group-node <?php echo $isSelf ? 'rg-self' : ''; ?>">
            <div class="mini-av"><?php echo $mInit; ?></div>
            <?php echo htmlspecialchars($m['name']); ?>
            <?php if ($m['group_is_head']): ?><span style="font-size:9px;color:var(--blue);font-weight:400"> ★</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="rg-main" id="rgMain">
    <svg class="rg-svg" id="rgSvg"></svg>
    <div class="rg-row" id="rgRowSelf">
        <div class="rg-node-wrap">
            <div class="rg-node rg-self <?php echo htmlspecialchars($cp['type']); ?>" id="rgNodeSelf">
                <div class="rg-av"><?php echo $initials; ?></div>
                <div class="rg-name"><?php echo htmlspecialchars($cp['name']); ?></div>
                <div class="rg-type"><?php echo CounterpartyRepository::typeLabel($cp['type']); ?></div>
            </div>
        </div>
    </div>

    <?php $allChildren = array_merge($contacts, $relations); ?>
    <?php if (!empty($allChildren)): ?>
    <div class="rg-row" id="rgRowChildren">
        <?php foreach ($contacts as $c):
            $cInit    = mb_strtoupper(mb_substr($c['name'],0,1,'UTF-8'),'UTF-8');
            $roleClass = rgRoleClass($c['relation_type']);
            $roleLabel = CounterpartyRepository::relationTypeLabel($c['relation_type']);
        ?>
        <div class="rg-node-wrap" data-child-of="rgNodeSelf">
            <?php if ($c['is_primary']): ?><div class="rg-dir-badge">Основний</div><?php endif; ?>
            <a href="/counterparties/view?id=<?php echo $c['id']; ?>" class="rg-node person">
                <div class="rg-av"><?php echo $cInit; ?></div>
                <div class="rg-name"><?php echo htmlspecialchars($c['name']); ?></div>
                <div class="rg-role <?php echo $roleClass; ?>"><?php echo $roleLabel; ?></div>
                <?php if ($c['job_title']): ?>
                    <div class="rg-contact"><?php echo htmlspecialchars($c['job_title']); ?></div>
                <?php elseif (!empty($c['phone'])): ?>
                    <div class="rg-contact"><?php echo htmlspecialchars($c['phone']); ?></div>
                <?php endif; ?>
            </a>
        </div>
        <?php endforeach; ?>

        <?php foreach ($relations as $rel):
            $rInit    = mb_strtoupper(mb_substr($rel['name'],0,1,'UTF-8'),'UTF-8');
            $rCls     = in_array($rel['type'],array('company','fop','person')) ? $rel['type'] : 'other';
            $roleLabel = CounterpartyRepository::relationTypeLabel($rel['relation_type']);
        ?>
        <div class="rg-node-wrap" data-child-of="rgNodeSelf">
            <a href="/counterparties/view?id=<?php echo $rel['id']; ?>" class="rg-node <?php echo $rCls; ?>">
                <div class="rg-av"><?php echo $rInit; ?></div>
                <div class="rg-name"><?php echo htmlspecialchars($rel['name']); ?></div>
                <div class="rg-role <?php echo rgRoleClass($rel['relation_type']); ?>"><?php echo $roleLabel; ?></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div><!-- /rg-main -->

<script>
(function() {
    function drawRelLines() {
        var main = document.getElementById('rgMain');
        var svg  = document.getElementById('rgSvg');
        var self = document.getElementById('rgNodeSelf');
        if (!main || !svg || !self) return;
        svg.innerHTML = '';
        svg.style.width  = main.offsetWidth  + 'px';
        svg.style.height = main.offsetHeight + 'px';
        var mr = main.getBoundingClientRect();
        var sr = self.getBoundingClientRect();
        var sx = sr.left - mr.left + sr.width / 2;
        var sy = sr.top  - mr.top  + sr.height;
        main.querySelectorAll('[data-child-of="rgNodeSelf"] .rg-node').forEach(function(node) {
            var nr  = node.getBoundingClientRect();
            var nx  = nr.left - mr.left + nr.width / 2;
            var ny  = nr.top  - mr.top;
            var midY = sy + (ny - sy) * 0.5;
            var d  = 'M'+sx+' '+sy+' C'+sx+' '+midY+','+nx+' '+midY+','+nx+' '+ny;
            var path = document.createElementNS('http://www.w3.org/2000/svg','path');
            path.setAttribute('d', d);
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', '#d0d9e3');
            path.setAttribute('stroke-width', '2');
            svg.appendChild(path);
        });
    }
    document.querySelectorAll('.cp-tab-lnk[data-tab="relations"]').forEach(function(btn) {
        btn.addEventListener('click', function() { setTimeout(drawRelLines, 60); });
    });
    var relPanel = document.getElementById('panel-relations');
    if (relPanel && relPanel.classList.contains('active')) {
        if (document.readyState === 'complete') drawRelLines();
        else window.addEventListener('load', drawRelLines);
    }
    window.addEventListener('resize', drawRelLines);
}());
</script>

<?php endif; ?>

<!-- ── CRUD JS ────────────────────────────────────────────────────────── -->
<script>
(function() {
    var cpId    = <?php echo $id; ?>;
    var cpName  = <?php echo json_encode($cp['name']); ?>;

    // Direction state: 'out' = current→other (current=parent), 'in' = other→current (current=child)
    var dir     = 'out';
    var selCpId = 0;
    var searchTimer = null;

    // ── DOM refs ──
    var btnAdd      = document.getElementById('btnAddRelation');
    var form        = document.getElementById('rlAddForm');
    var btnCancel   = document.getElementById('rlBtnCancel');
    var relIdInp    = document.getElementById('rlRelId');
    var formTitle   = document.getElementById('rlFormTitle');
    var cpPickerGrp = document.getElementById('rlCpPickerGroup');
    var dirWrap     = document.getElementById('rlDirWrap');
    var searchInp   = document.getElementById('rlCpSearch');
    var cpDd        = document.getElementById('rlCpDd');
    var cpIdInp     = document.getElementById('rlCpId');
    var scopeSel    = document.getElementById('rlSearchScope');

    function updateDirLabel() {
        var other = searchInp.value || '\u2026';
        var lbl   = document.getElementById('rlDirLabel');
        if (dir === 'out') lbl.textContent = cpName + '  \u2192  ' + other;
        else               lbl.textContent = other + '  \u2192  ' + cpName;
    }

    function openForm(editMode) {
        form.classList.add('open');
        btnAdd.style.display = 'none';
        dirWrap.style.display     = editMode ? 'none' : '';
        cpPickerGrp.style.display = editMode ? 'none' : '';
        if (!editMode) { updateDirLabel(); searchInp.focus(); }
    }

    function resetForm() {
        selCpId = 0;
        dir     = 'out';
        relIdInp.value  = '';
        searchInp.value = '';
        cpIdInp.value   = '';
        document.getElementById('rlJobTitle').value = '';
        document.getElementById('rlIsPrimary').checked = false;
        document.getElementById('rlRelType').selectedIndex = 0;
        document.getElementById('rlAddErr').style.display = 'none';
        cpDd.style.display = 'none';
        formTitle.textContent = 'Новий зв\u2019язок';
    }

    if (btnAdd) btnAdd.addEventListener('click', function() {
        resetForm();
        openForm(false);
    });
    if (btnCancel) btnCancel.addEventListener('click', function() {
        form.classList.remove('open');
        btnAdd.style.display = '';
        resetForm();
    });

    document.getElementById('rlDirSwap').addEventListener('click', function() {
        dir = (dir === 'out') ? 'in' : 'out';
        updateDirLabel();
    });

    // ── Counterparty picker ──
    searchInp.addEventListener('input', function() {
        var q = this.value.trim();
        updateDirLabel();
        if (q.length < 1) { cpDd.style.display = 'none'; return; }
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() { doSearch(q); }, 250);
    });

    searchInp.addEventListener('blur', function() {
        setTimeout(function() { cpDd.style.display = 'none'; }, 180);
    });

    function doSearch(q) {
        var scope = scopeSel ? scopeSel.value : '';
        fetch('/counterparties/api/search?q=' + encodeURIComponent(q) + '&exclude=' + cpId + (scope ? '&type=' + scope : ''))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            cpDd.innerHTML = '';
            if (!d.ok || !d.items || !d.items.length) { cpDd.style.display = 'none'; return; }
            d.items.forEach(function(item) {
                var opt = document.createElement('div');
                opt.className = 'rl-cp-opt';
                var badgeCls = (item.type === 'company' || item.type === 'fop' || item.type === 'person') ? item.type : '';
                opt.innerHTML = '<span class="rl-cp-badge ' + badgeCls + '">' + escHtml(item.type_label || item.type) + '</span>'
                              + '<span>' + escHtml(item.name) + '</span>';
                opt.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    selCpId = item.id;
                    cpIdInp.value      = item.id;
                    searchInp.value    = item.name;
                    cpDd.style.display = 'none';
                    updateDirLabel();
                });
                cpDd.appendChild(opt);
            });
            cpDd.style.display = 'block';
        })
        .catch(function() { cpDd.style.display = 'none'; });
    }

    // ── Edit button ──
    document.querySelectorAll('.rl-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var b = this.dataset;
            resetForm();
            relIdInp.value = b.id;
            // Pre-fill counterparty name (readonly in edit mode)
            searchInp.value = b.otherName;
            cpIdInp.value   = b.otherId;
            selCpId = parseInt(b.otherId, 10);
            // Pre-fill rel type
            var sel = document.getElementById('rlRelType');
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === b.relType) { sel.selectedIndex = i; break; }
            }
            document.getElementById('rlJobTitle').value     = b.jobTitle || '';
            document.getElementById('rlIsPrimary').checked  = b.isPrimary === '1';
            dir = b.dir || 'out';
            formTitle.textContent = '\u0420\u0435\u0434\u0430\u0433\u0443\u0432\u0430\u0442\u0438 \u0437\u0432\u2019\u044f\u0437\u043e\u043a';
            openForm(true);
        });
    });

    // ── Save ──
    document.getElementById('rlBtnSave').addEventListener('click', function() {
        var errBox  = document.getElementById('rlAddErr');
        var saveBtn = document.getElementById('rlBtnSave');
        errBox.style.display = 'none';

        var relType  = document.getElementById('rlRelType').value;
        var editRelId = parseInt(relIdInp.value, 10);

        if (!relType) { errBox.textContent = 'Оберіть тип зв\u2019язку'; errBox.style.display = 'block'; return; }

        var fd = new FormData();
        fd.append('relation_type', relType);
        fd.append('job_title',    document.getElementById('rlJobTitle').value.trim());
        if (document.getElementById('rlIsPrimary').checked) fd.append('is_primary', '1');

        if (editRelId > 0) {
            // Update mode — only role/job fields, not participants
            fd.append('id', editRelId);
        } else {
            // Create mode — need participants
            var otherId = parseInt(cpIdInp.value, 10);
            if (!otherId) { errBox.textContent = 'Оберіть контрагента'; errBox.style.display = 'block'; return; }
            fd.append('parent_id', (dir === 'out') ? cpId : otherId);
            fd.append('child_id',  (dir === 'out') ? otherId : cpId);
        }

        saveBtn.disabled = true;
        fetch('/counterparties/api/save_relation', { method:'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            saveBtn.disabled = false;
            if (!res.ok) { errBox.textContent = res.error || 'Помилка'; errBox.style.display = 'block'; return; }
            window.location.href = '/counterparties/view?id=' + cpId + '&tab=relations';
        })
        .catch(function() {
            saveBtn.disabled = false;
            errBox.textContent = 'Помилка мережі'; errBox.style.display = 'block';
        });
    });

    // ── Delete ──
    document.querySelectorAll('.rl-del-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var relId = parseInt(this.dataset.id, 10);
            if (!relId || !confirm('Видалити цей зв\u2019язок?')) return;
            var fd = new FormData();
            fd.append('id', relId);
            fetch('/counterparties/api/delete_relation', { method:'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) window.location.href = '/counterparties/view?id=' + cpId + '&tab=relations';
                else alert(res.error || 'Помилка видалення');
            });
        });
    });

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}());
</script>

</div>
</div>


<!-- ═══ Tab: Документи ══════════════════════════════════════════════════════ -->
<div class="cp-tab-panel <?php echo $tab==='documents'?'active':''; ?>" id="panel-documents">
<div class="card" style="padding:24px">
    <h3 style="margin:0 0 16px; font-size:15px; font-weight:700">Замовлення</h3>
    <?php if (empty($recentOrders)): ?>
        <div style="text-align:center; padding:32px; color:var(--text-muted); font-size:13px">Замовлень ще немає.</div>
    <?php else: ?>
    <table class="cp-orders-table">
        <colgroup>
            <col style="width:35%">
            <col style="width:18%">
            <col style="width:22%">
            <col style="width:25%">
        </colgroup>
        <thead>
            <tr>
                <th>№ Замовлення</th>
                <th>Дата</th>
                <th style="text-align:right">Сума</th>
                <th>Статус</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recentOrders as $ord):
            $statusLabel = isset($orderStatusLabels[$ord['status']]) ? $orderStatusLabels[$ord['status']] : $ord['status'];
        ?>
        <tr>
            <td><a href="/customerorder/edit?id=<?php echo $ord['id']; ?>"><?php echo htmlspecialchars($ord['number']); ?></a></td>
            <td style="color:var(--text-muted)"><?php echo htmlspecialchars(substr($ord['moment'],0,10)); ?></td>
            <td style="text-align:right; font-variant-numeric:tabular-nums">₴<?php echo number_format((float)$ord['sum_total'],0,'.', ' '); ?></td>
            <td><span class="badge badge-gray"><?php echo $statusLabel; ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($stats['order_count'] > count($recentOrders)): ?>
    <div style="margin-top:12px; text-align:center">
        <a href="/customerorder?counterparty_id=<?php echo $id; ?>" class="btn btn-sm">Всі <?php echo (int)$stats['order_count']; ?> замовлення →</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div>


<!-- ═══ Tab: Файли ═══════════════════════════════════════════════════════════ -->
<div class="cp-tab-panel <?php echo $tab==='files'?'active':''; ?>" id="panel-files">
<div class="card" style="padding:20px 24px">

    <!-- Toolbar -->
    <div class="fl-toolbar">
        <h3>Файли</h3>
        <div class="fl-filters">
            <select id="flTypeFilter" class="fl-sel">
                <option value="">Всі типи</option>
            </select>
            <select id="flSortSel" class="fl-sel">
                <option value="desc">Нові спочатку</option>
                <option value="asc">Старі спочатку</option>
            </select>
        </div>
        <button class="btn btn-primary btn-sm" id="flBtnUpload" type="button">+ Завантажити</button>
    </div>

    <!-- Upload form -->
    <div class="fl-upload-form" id="flUploadForm">
        <div id="flUploadErr" class="fl-err" style="display:none"></div>
        <div class="fl-upload-row">
            <div class="fl-fg">
                <label>Файл <span class="fl-required">*</span></label>
                <input type="file" id="flFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.svg,.webp,.ai,.psd,.eps,.indd,.cdr,.zip,.rar,.7z,.txt,.csv,.odt,.ods">
            </div>
            <div class="fl-fg">
                <label>Тип документа</label>
                <select id="flTypeSelect" class="fl-sel">
                    <option value="0">— Без типу —</option>
                </select>
            </div>
            <div class="fl-fg" style="flex:1">
                <label>Коментар</label>
                <input type="text" id="flComment" placeholder="необов'язково" style="width:100%">
            </div>
        </div>
        <div class="fl-upload-actions">
            <button class="btn btn-primary btn-sm" id="flBtnSave" type="button">Зберегти</button>
            <button class="btn btn-ghost btn-sm" id="flBtnCancel" type="button">Скасувати</button>
            <span id="flProgress" style="display:none;font-size:12px;color:var(--text-muted)">Завантаження…</span>
        </div>
    </div>

    <!-- File list -->
    <div id="flList">
        <div class="fl-loading">Завантаження…</div>
    </div>

</div>
</div>

<!-- ═══ Tab: Активність ══════════════════════════════════════════════════════ -->
<div class="cp-tab-panel <?php echo $tab==='activity'?'active':''; ?>" id="panel-activity">
<div class="card" style="padding:20px 24px">
    <div class="act-add-wrap">
        <textarea id="actNoteText" placeholder="Залишити нотатку…" rows="2"></textarea>
        <div class="act-add-row">
            <button class="btn btn-primary btn-sm" id="actAddBtn">Додати нотатку</button>
            <span id="actAddStatus" style="font-size:12px; color:var(--text-muted)"></span>
        </div>
    </div>

    <?php
    // Build combined timeline: notes + orders
    $timeline = array();
    foreach ($activities as $act) {
        $timeline[] = array('kind' => 'note', 'date' => $act['created_at'], 'data' => $act);
    }
    foreach ($recentOrders as $ord) {
        $timeline[] = array('kind' => 'order', 'date' => $ord['moment'], 'data' => $ord);
    }
    usort($timeline, function($a, $b) { return strcmp($b['date'], $a['date']); });
    ?>

    <?php if (empty($timeline)): ?>
        <div class="act-empty">Активність поки відсутня. Залиште першу нотатку.</div>
    <?php else: ?>
    <div class="act-timeline" id="actTimeline">
        <?php foreach ($timeline as $item):
            $dtRaw = $item['date'];
            $dtObj = new DateTime($dtRaw);
            $now   = new DateTime();
            $diff  = $now->diff($dtObj);
            if ($diff->days === 0)      $dtStr = 'Сьогодні, ' . $dtObj->format('H:i');
            elseif ($diff->days === 1)  $dtStr = 'Вчора, '    . $dtObj->format('H:i');
            elseif ($diff->days <= 30)  $dtStr = $diff->days . ' дн. тому';
            else                        $dtStr = $dtObj->format('d.m.Y');
        ?>
        <?php if ($item['kind'] === 'note'): ?>
        <div class="act-item" data-act-id="<?php echo (int)$item['data']['id']; ?>">
            <div class="act-icon note">📝</div>
            <div class="act-body">
                <div class="act-meta"><?php echo $dtStr; ?> · Нотатка</div>
                <div class="act-content"><?php echo htmlspecialchars($item['data']['content']); ?></div>
            </div>
        </div>
        <?php else:
            $ord = $item['data'];
            $sl  = isset($orderStatusLabels[$ord['status']]) ? $orderStatusLabels[$ord['status']] : $ord['status'];
        ?>
        <div class="act-item">
            <div class="act-icon order">🛒</div>
            <div class="act-body">
                <div class="act-meta"><?php echo $dtStr; ?> · Замовлення</div>
                <div class="act-order-info">
                    <a href="/customerorder/edit?id=<?php echo $ord['id']; ?>" style="font-weight:600"><?php echo htmlspecialchars($ord['number']); ?></a>
                    <span style="font-variant-numeric:tabular-nums">₴<?php echo number_format((float)$ord['sum_total'],0,'.', ' '); ?></span>
                    <span class="badge badge-gray" style="font-size:11px"><?php echo $sl; ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>


<!-- ═══ Tab: Аналітика ══════════════════════════════════════════════════════ -->
<div class="cp-tab-panel <?php echo $tab==='analytics'?'active':''; ?>" id="panel-analytics">
<div class="card" style="padding:24px">
    <div style="text-align:center; padding:48px; color:var(--text-muted)">
        <svg width="48" height="48" fill="none" viewBox="0 0 48 48" style="margin-bottom:12px;opacity:.25"><rect x="4" y="28" width="8" height="16" rx="2" fill="currentColor"/><rect x="16" y="18" width="8" height="26" rx="2" fill="currentColor"/><rect x="28" y="10" width="8" height="34" rx="2" fill="currentColor"/><rect x="40" y="22" width="8" height="22" rx="2" fill="currentColor"/></svg>
        <div style="font-size:15px; font-weight:600; margin-bottom:6px">Аналітика — Фаза 2</div>
        <div style="font-size:13px">Тут будуть графіки активності, топ-товари, LTV-динаміка та порівняльний аналіз</div>
    </div>
</div>
</div>

</div><!-- /cp-view -->


<!-- ══ Modal: Add contact person ══════════════════════════════════════════ -->
<div class="modal-overlay hidden" id="modalContact">
    <div class="modal-box" style="width:520px">
        <div class="modal-head">
            <span>Додати контактну особу</span>
            <button class="modal-close" id="modalContactClose">&#x2715;</button>
        </div>
        <div class="modal-body">
            <div id="modalContactError" class="modal-error hidden"></div>

            <div style="display:flex; gap:8px; margin-bottom:14px">
                <button class="btn btn-sm <?php echo ''; ?> active" id="btnContactNew" style="flex:1">Нова особа</button>
                <button class="btn btn-sm" id="btnContactExisting" style="flex:1">Обрати існуючу</button>
            </div>

            <div id="contactNewForm">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 12px">
                    <div class="form-row">
                        <label>Ім'я</label>
                        <input type="text" id="cFirstName" placeholder="Олег">
                    </div>
                    <div class="form-row">
                        <label>Прізвище</label>
                        <input type="text" id="cLastName" placeholder="Іванченко">
                    </div>
                    <div class="form-row">
                        <label>По батькові</label>
                        <input type="text" id="cMiddleName">
                    </div>
                    <div class="form-row">
                        <label>Посада</label>
                        <input type="text" id="cPosition" placeholder="Менеджер із закупівель">
                    </div>
                    <div class="form-row">
                        <label>Телефон</label>
                        <input type="text" id="cPhone" placeholder="+38 (0XX) XXX-XX-XX">
                    </div>
                    <div class="form-row">
                        <label>Email</label>
                        <input type="email" id="cEmail">
                    </div>
                </div>
            </div>

            <div id="contactExistingForm" style="display:none">
                <div class="form-row">
                    <label>Пошук особи</label>
                    <input type="text" id="cExistSearch" placeholder="Пошук за ім'ям або телефоном…">
                </div>
                <div id="cExistResults" style="max-height:180px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius-sm); display:none"></div>
                <input type="hidden" id="cExistId">
                <div id="cExistSelected" style="display:none; font-size:13px; color:var(--green); margin-top:6px"></div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 12px; margin-top:8px">
                <div class="form-row">
                    <label>Тип зв'язку</label>
                    <select id="cRelType">
                        <option value="contact_person">Контактна особа</option>
                        <option value="director">Директор</option>
                        <option value="accountant">Бухгалтер</option>
                        <option value="manager">Менеджер</option>
                        <option value="signer">Підписант</option>
                        <option value="other">Інше</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Посада (в зв'язку)</label>
                    <input type="text" id="cJobTitle" placeholder="Директор з продажів">
                </div>
                <div class="form-row" style="grid-column:1/-1">
                    <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;cursor:pointer">
                        <input type="checkbox" id="cIsPrimary"> Основний контакт
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" id="modalContactCancel">Скасувати</button>
            <button class="btn btn-primary" id="modalContactSave">Зберегти</button>
        </div>
    </div>
</div>

<!-- ══ Modal: New group ══════════════════════════════════════════════════════ -->
<div class="modal-overlay hidden" id="modalGroup">
    <div class="modal-box" style="width:380px">
        <div class="modal-head">
            <span>Нова група компаній</span>
            <button class="modal-close" onclick="document.getElementById('modalGroup').classList.add('hidden')">&#x2715;</button>
        </div>
        <div class="modal-body">
            <div id="modalGroupError" class="modal-error hidden"></div>
            <div class="form-row">
                <label>Назва групи <span style="color:var(--red)">*</span></label>
                <input type="text" id="newGroupName" placeholder="Холдинг «Альфа-Офіс»">
            </div>
            <div class="form-row">
                <label>Опис</label>
                <textarea id="newGroupDesc" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="document.getElementById('modalGroup').classList.add('hidden')">Скасувати</button>
            <button class="btn btn-primary" id="btnSaveGroup">Створити</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 1800);
}

(function() {
    var cpId = <?php echo $id; ?>;

    function showToastLocal(msg, ok) {
        showToast(msg);
    }

    // ── Type switcher ────────────────────────────────────────────────────────
    function switchType(type) {
        document.getElementById('fType').value = type;
        var grid = document.getElementById('fFormGrid');
        // Hide all typed groups first
        grid.querySelectorAll('.fg-company, .fg-fop, .fg-person').forEach(function(el) {
            el.style.display = 'none';
        });
        // Show groups matching selected type
        grid.querySelectorAll('.fg-' + type).forEach(function(el) {
            el.style.display = '';
        });
        // Update button states
        document.querySelectorAll('.cp-type-btn').forEach(function(b) {
            b.classList.toggle('active', b.dataset.type === type);
        });
    }

    document.querySelectorAll('.cp-type-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { switchType(this.dataset.type); });
    });

    // Init with current type
    switchType(document.getElementById('fType').value);

    // ── Requisites view/edit toggle ──────────────────────────────────────────
    var btnEditToggle = document.getElementById('btnEditToggle');
    var reqViewMode   = document.getElementById('reqViewMode');
    var reqEditMode   = document.getElementById('reqEditMode');
    var btnCancelReq  = document.getElementById('btnCancelReq');

    if (btnEditToggle && reqViewMode && reqEditMode) {
        btnEditToggle.addEventListener('click', function() {
            reqEditMode.classList.remove('hidden');
            reqViewMode.classList.add('hidden');
            btnEditToggle.style.display = 'none';
        });
        btnCancelReq.addEventListener('click', function() {
            reqEditMode.classList.add('hidden');
            reqViewMode.classList.remove('hidden');
            btnEditToggle.style.display = '';
        });
    }

    // ── Activity — add note ───────────────────────────────────────────────────
    var actAddBtn    = document.getElementById('actAddBtn');
    var actNoteText  = document.getElementById('actNoteText');
    var actAddStatus = document.getElementById('actAddStatus');
    var actTimeline  = document.getElementById('actTimeline');

    if (actAddBtn && actNoteText) {
        actAddBtn.addEventListener('click', function() {
            var content = actNoteText.value.trim();
            if (!content) { actNoteText.focus(); return; }
            actAddBtn.disabled = true;
            var fd = new FormData();
            fd.append('id', cpId);
            fd.append('content', content);
            fetch('/counterparties/api/add_activity', {method:'POST', body:fd})
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    actAddBtn.disabled = false;
                    if (d.ok) {
                        actNoteText.value = '';
                        // Prepend new item to timeline
                        var now = new Date();
                        var timeStr = 'Сьогодні, ' + ('0'+now.getHours()).slice(-2) + ':' + ('0'+now.getMinutes()).slice(-2);
                        var div = document.createElement('div');
                        div.className = 'act-item';
                        div.setAttribute('data-act-id', d.id);
                        div.innerHTML = '<div class="act-icon note">📝</div>'
                            + '<div class="act-body">'
                            + '<div class="act-meta">' + timeStr + ' · Нотатка</div>'
                            + '<div class="act-content">' + d.content.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>'
                            + '</div>';
                        if (actTimeline) {
                            actTimeline.insertBefore(div, actTimeline.firstChild);
                        } else {
                            // Create timeline container if didn't exist (was empty state)
                            var emptyEl = document.querySelector('#panel-activity .act-empty');
                            if (emptyEl) emptyEl.remove();
                            var newTimeline = document.createElement('div');
                            newTimeline.className = 'act-timeline';
                            newTimeline.id = 'actTimeline';
                            newTimeline.appendChild(div);
                            document.querySelector('#panel-activity .card').appendChild(newTimeline);
                            actTimeline = newTimeline;
                        }
                        if (typeof showToast === 'function') showToast('Нотатку додано');
                    } else {
                        actAddStatus.textContent = d.error || 'Помилка';
                    }
                });
        });
    }

    // ── Tab switching ────────────────────────────────────────────────────────
    document.querySelectorAll('.cp-tab-lnk').forEach(function(lnk) {
        lnk.addEventListener('click', function(e) {
            e.preventDefault();
            var tab = this.dataset.tab;
            document.querySelectorAll('.cp-tab-lnk').forEach(function(l){ l.classList.remove('active'); });
            document.querySelectorAll('.cp-tab-panel').forEach(function(p){ p.classList.remove('active'); });
            this.classList.add('active');
            var panel = document.getElementById('panel-' + tab);
            if (panel) panel.classList.add('active');
            history.replaceState(null, '', '/counterparties/view?id=' + cpId + '&tab=' + tab);
        });
    });

    // ── Save requisites ──────────────────────────────────────────────────────
    document.getElementById('btnSaveReq').addEventListener('click', function() {
        var btn    = this;
        var errBox = document.getElementById('reqFormError');
        var status = document.getElementById('reqSaveStatus');
        var type   = document.getElementById('fType').value;

        var fd = new FormData();
        fd.append('id',   cpId);
        fd.append('type', type);
        fd.append('description', document.getElementById('fDescription').value);
        fd.append('phone', document.getElementById('fPhone').value.trim());
        fd.append('email', document.getElementById('fEmail').value.trim());
        fd.append('notes', document.getElementById('fNotes').value);

        if (type === 'person') {
            var fn = document.getElementById('fFirstName').value.trim();
            var ln = document.getElementById('fLastName').value.trim();
            var mn = document.getElementById('fMiddleName').value.trim();
            fd.append('name',          [fn, ln, mn].filter(Boolean).join(' '));
            fd.append('first_name',    fn);
            fd.append('last_name',     ln);
            fd.append('middle_name',   mn);
            fd.append('position_name', document.getElementById('fPosition').value.trim());
            fd.append('phone_alt',     document.getElementById('fPhoneAlt').value.trim());
            fd.append('birth_date',    document.getElementById('fBirthDate').value);
            fd.append('telegram',      document.getElementById('fTelegram').value.trim());
            fd.append('viber',         document.getElementById('fViber').value.trim());
        } else {
            fd.append('name',          document.getElementById('fName').value.trim());
            fd.append('inn',           document.getElementById('fInn').value.trim());
            fd.append('vat_number',    document.getElementById('fVat').value.trim());
            fd.append('iban',          document.getElementById('fIban').value.trim());
            fd.append('bank_name',     document.getElementById('fBankName').value.trim());
            fd.append('mfo',           document.getElementById('fMfo').value.trim());
            fd.append('legal_address', document.getElementById('fLegalAddr').value);
            fd.append('actual_address',document.getElementById('fActualAddr').value);
            if (type === 'company') {
                fd.append('short_name',  document.getElementById('fShortName').value);
                fd.append('full_name',   document.getElementById('fFullName').value);
                fd.append('okpo',        document.getElementById('fOkpo').value.trim());
                fd.append('website',     document.getElementById('fWebsite').value.trim());
                fd.append('group_id',    document.getElementById('fGroupId').value);
                if (document.getElementById('fGroupIsHead').checked) fd.append('group_is_head', '1');
            }
        }

        if (!fd.get('name')) {
            errBox.textContent = type === 'person' ? 'Введіть імʼя або прізвище' : 'Назва обовʼязкова';
            errBox.classList.remove('hidden');
            return;
        }
        errBox.classList.add('hidden');
        btn.disabled = true;
        btn.textContent = 'Збереження…';
        status.textContent = '';

        fetch('/counterparties/api/save_counterparty', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                btn.textContent = 'Зберегти';
                if (d.ok) {
                    showToast('Збережено');
                    // Switch back to view mode and reload to reflect new values
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    errBox.textContent = d.error || 'Помилка';
                    errBox.classList.remove('hidden');
                }
            });
    });

    // ── Toggle status ────────────────────────────────────────────────────────
    document.getElementById('btnToggleStatus').addEventListener('click', function() {
        var btn = this;
        var curStatus = parseInt(btn.dataset.status, 10);
        var newStatus = curStatus ? 0 : 1;
        if (!confirm(newStatus ? 'Відновити контрагента?' : 'Перенести контрагента в архів?')) return;

        var fd = new FormData();
        fd.append('id', cpId);
        fd.append('status', newStatus);
        fetch('/counterparties/api/save_counterparty', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.ok) location.reload();
                else showToast(d.error || 'Помилка');
            });
    });

    // ── New group ────────────────────────────────────────────────────────────
    var btnNewGroup = document.getElementById('btnNewGroup');
    if (btnNewGroup) {
        btnNewGroup.addEventListener('click', function() {
            document.getElementById('modalGroup').classList.remove('hidden');
            document.getElementById('newGroupName').focus();
        });
    }
    document.getElementById('btnSaveGroup').addEventListener('click', function() {
        var name = document.getElementById('newGroupName').value.trim();
        if (!name) { document.getElementById('newGroupName').focus(); return; }
        var fd = new FormData();
        fd.append('name', name);
        fd.append('description', document.getElementById('newGroupDesc').value);
        fetch('/counterparties/api/save_group', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    // Add option to select
                    var sel = document.getElementById('fGroupId');
                    var opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name;
                    opt.selected = true;
                    sel.appendChild(opt);
                    document.getElementById('modalGroup').classList.add('hidden');
                    document.getElementById('newGroupName').value = '';
                    document.getElementById('newGroupDesc').value = '';
                    showToast('Групу створено');
                } else {
                    document.getElementById('modalGroupError').textContent = d.error || 'Помилка';
                    document.getElementById('modalGroupError').classList.remove('hidden');
                }
            });
    });

    <?php if ($isCompany): ?>
    // ── Contacts ─────────────────────────────────────────────────────────────
    var modalContact     = document.getElementById('modalContact');
    var btnAddContact    = document.getElementById('btnAddContact');
    var modalContactClose= document.getElementById('modalContactClose');
    var modalContactCancel = document.getElementById('modalContactCancel');
    var btnSaveContact   = document.getElementById('modalContactSave');
    var contactErrBox    = document.getElementById('modalContactError');
    var contactNewForm   = document.getElementById('contactNewForm');
    var contactExistForm = document.getElementById('contactExistingForm');
    var contactMode      = 'new'; // 'new' | 'existing'
    var selectedExistId  = 0;

    btnAddContact.addEventListener('click', function() {
        modalContact.classList.remove('hidden');
        contactMode = 'new';
        contactNewForm.style.display = '';
        contactExistForm.style.display = 'none';
        document.getElementById('btnContactNew').classList.add('active');
        document.getElementById('btnContactExisting').classList.remove('active');
        document.getElementById('cLastName').focus();
    });
    modalContactClose.addEventListener('click', function() { modalContact.classList.add('hidden'); });
    modalContactCancel.addEventListener('click', function() { modalContact.classList.add('hidden'); });
    modalContact.addEventListener('click', function(e) { if (e.target === modalContact) modalContact.classList.add('hidden'); });

    document.getElementById('btnContactNew').addEventListener('click', function() {
        contactMode = 'new';
        this.classList.add('active');
        document.getElementById('btnContactExisting').classList.remove('active');
        contactNewForm.style.display = '';
        contactExistForm.style.display = 'none';
    });
    document.getElementById('btnContactExisting').addEventListener('click', function() {
        contactMode = 'existing';
        this.classList.add('active');
        document.getElementById('btnContactNew').classList.remove('active');
        contactNewForm.style.display = 'none';
        contactExistForm.style.display = '';
        document.getElementById('cExistSearch').focus();
    });

    // Search existing persons
    var existSearchTimer;
    document.getElementById('cExistSearch').addEventListener('input', function() {
        clearTimeout(existSearchTimer);
        var q = this.value.trim();
        existSearchTimer = setTimeout(function() {
            if (!q) return;
            fetch('/counterparties/api/search?q=' + encodeURIComponent(q) + '&type=person')
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    var box = document.getElementById('cExistResults');
                    box.innerHTML = '';
                    if (!d.items || !d.items.length) {
                        box.innerHTML = '<div style="padding:10px 12px; color:var(--text-muted); font-size:13px">Нічого не знайдено</div>';
                    } else {
                        d.items.forEach(function(item) {
                            var div = document.createElement('div');
                            div.style.cssText = 'padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--border); font-size:13px;';
                            div.innerHTML = '<strong>' + item.name + '</strong>' +
                                (item.phone ? ' <span style="color:var(--text-muted)">' + item.phone + '</span>' : '');
                            div.addEventListener('click', function() {
                                selectedExistId = item.id;
                                document.getElementById('cExistId').value = item.id;
                                document.getElementById('cExistSelected').textContent = '✓ Обрано: ' + item.name;
                                document.getElementById('cExistSelected').style.display = '';
                                box.style.display = 'none';
                            });
                            div.addEventListener('mouseenter', function(){ this.style.background='var(--bg-hover)'; });
                            div.addEventListener('mouseleave', function(){ this.style.background=''; });
                            box.appendChild(div);
                        });
                    }
                    box.style.display = '';
                });
        }, 300);
    });

    btnSaveContact.addEventListener('click', function() {
        contactErrBox.classList.add('hidden');

        if (contactMode === 'new') {
            var lastName  = document.getElementById('cLastName').value.trim();
            var firstName = document.getElementById('cFirstName').value.trim();
            if (!lastName && !firstName) {
                contactErrBox.textContent = "Введіть ім'я або прізвище";
                contactErrBox.classList.remove('hidden');
                return;
            }
            var fullName = (lastName + ' ' + firstName + ' ' + document.getElementById('cMiddleName').value.trim()).trim();

            this.disabled = true;
            var self = this;

            // Step 1: create person counterparty
            var fd1 = new FormData();
            fd1.append('type',          'person');
            fd1.append('name',          fullName);
            fd1.append('last_name',     lastName);
            fd1.append('first_name',    firstName);
            fd1.append('middle_name',   document.getElementById('cMiddleName').value.trim());
            fd1.append('position_name', document.getElementById('cPosition').value.trim());
            fd1.append('phone',         document.getElementById('cPhone').value.trim());
            fd1.append('email',         document.getElementById('cEmail').value.trim());

            fetch('/counterparties/api/save_counterparty', {method:'POST', body:fd1})
                .then(function(r){ return r.json(); })
                .then(function(d1) {
                    if (!d1.ok) {
                        contactErrBox.textContent = d1.error || 'Помилка';
                        contactErrBox.classList.remove('hidden');
                        self.disabled = false;
                        return;
                    }
                    // Step 2: create relation
                    saveRelation(cpId, d1.id, self, fullName);
                });
        } else {
            // Existing person
            var existId = parseInt(document.getElementById('cExistId').value, 10);
            if (!existId) {
                contactErrBox.textContent = 'Оберіть особу';
                contactErrBox.classList.remove('hidden');
                return;
            }
            this.disabled = true;
            saveRelation(cpId, existId, this, document.getElementById('cExistSearch').value);
        }
    });

    function saveRelation(parentId, childId, btn, personName) {
        var fd2 = new FormData();
        fd2.append('parent_id',     parentId);
        fd2.append('child_id',      childId);
        fd2.append('relation_type', document.getElementById('cRelType').value);
        fd2.append('job_title',     document.getElementById('cJobTitle').value.trim());
        if (document.getElementById('cIsPrimary').checked) fd2.append('is_primary', '1');

        fetch('/counterparties/api/save_relation', {method:'POST', body:fd2})
            .then(function(r){ return r.json(); })
            .then(function(d2) {
                btn.disabled = false;
                if (d2.ok) {
                    modalContact.classList.add('hidden');
                    showToast('Контакт додано');
                    setTimeout(function(){ location.reload(); }, 600);
                } else {
                    var ce = document.getElementById('modalContactError');
                    ce.textContent = d2.error || 'Помилка зв\'язку';
                    ce.classList.remove('hidden');
                }
            });
    }

    // Delete contact relation
    document.getElementById('contactsList').addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-del-contact');
        if (!btn) return;
        if (!confirm("Видалити зв'язок з контактною особою?")) return;
        var relId = btn.dataset.relId;
        var fd = new FormData();
        fd.append('id', relId);
        fetch('/counterparties/api/delete_relation', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    btn.closest('.cp-contact-card').remove();
                    showToast('Зв\'язок видалено');
                }
            });
    });
    <?php endif; ?>

    // Delete non-contact relations
    document.querySelectorAll('.btn-del-relation').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm("Видалити зв'язок?")) return;
            var relId = btn.dataset.relId;
            var fd = new FormData();
            fd.append('id', relId);
            fetch('/counterparties/api/delete_relation', {method:'POST', body:fd})
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    if (d.ok) { btn.closest('tr').remove(); showToast('Видалено'); }
                });
        });
    });

}());
</script>

<!-- ── Files tab JS ──────────────────────────────────────────────────────── -->
<script>
(function() {
    var cpId        = <?php echo $id; ?>;
    var flPanel     = document.getElementById('panel-files');
    if (!flPanel) return;

    var flList      = document.getElementById('flList');
    var flTypeFilter = document.getElementById('flTypeFilter');
    var flSortSel   = document.getElementById('flSortSel');
    var flUploadForm = document.getElementById('flUploadForm');
    var flBtnUpload = document.getElementById('flBtnUpload');
    var flBtnCancel = document.getElementById('flBtnCancel');
    var flBtnSave   = document.getElementById('flBtnSave');
    var flTypeSelect = document.getElementById('flTypeSelect');
    var flFileInput = document.getElementById('flFileInput');
    var flComment   = document.getElementById('flComment');
    var flProgress  = document.getElementById('flProgress');
    var flErr       = document.getElementById('flUploadErr');

    var allTypes    = [];
    var loaded      = false;

    // Load when tab becomes active
    document.querySelectorAll('.cp-tab-lnk[data-tab="files"]').forEach(function(lnk) {
        lnk.addEventListener('click', function() {
            if (!loaded) { loaded = true; loadFiles(); }
        });
    });
    // Also load if tab is already active on page load
    if (flPanel.classList.contains('active')) { loaded = true; loadFiles(); }

    function loadFiles() {
        var typeId = flTypeFilter.value || '';
        var sort   = flSortSel.value || 'desc';
        var url    = '/counterparties/api/get_files?id=' + cpId
                   + (typeId ? '&type_id=' + typeId : '')
                   + '&sort=' + sort;
        flList.innerHTML = '<div class="fl-loading">Завантаження\u2026</div>';
        fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) { flList.innerHTML = '<div class="fl-empty">Помилка завантаження</div>'; return; }
            // Populate type filters once
            if (allTypes.length === 0 && d.types && d.types.length) {
                allTypes = d.types;
                populateTypeSelects(d.types);
            }
            renderFiles(d.files);
        })
        .catch(function() { flList.innerHTML = '<div class="fl-empty">Помилка мережі</div>'; });
    }

    function populateTypeSelects(types) {
        var optHtml = '<option value="">Всі типи</option>';
        var optHtml2 = '<option value="0">\u2014 Без типу \u2014</option>';
        types.forEach(function(t) {
            optHtml  += '<option value="' + t.id + '">' + escHtml(t.name) + '</option>';
            optHtml2 += '<option value="' + t.id + '">' + escHtml(t.name) + '</option>';
        });
        flTypeFilter.innerHTML = optHtml;
        flTypeSelect.innerHTML = optHtml2;
    }

    function formatSize(bytes) {
        bytes = parseInt(bytes, 10) || 0;
        if (bytes < 1024)        return bytes + ' Б';
        if (bytes < 1024*1024)   return Math.round(bytes/1024) + ' КБ';
        return (bytes/1024/1024).toFixed(1) + ' МБ';
    }

    function fileIcon(mime, name) {
        var ext = (name || '').split('.').pop().toLowerCase();
        if (ext === 'pdf')                        return '\uD83D\uDCC4';
        if (/(doc|docx|odt)/.test(ext))           return '\uD83D\uDCDD';
        if (/(xls|xlsx|ods|csv)/.test(ext))       return '\uD83D\uDCC8';
        if (/(ppt|pptx)/.test(ext))               return '\uD83D\uDCCA';
        if (/(jpg|jpeg|png|gif|svg|webp)/.test(ext)) return '\uD83D\uDDBC\uFE0F';
        if (/(ai|psd|eps|indd|cdr)/.test(ext))    return '\uD83C\uDFA8';
        if (/(zip|rar|7z)/.test(ext))             return '\uD83D\uDDC2\uFE0F';
        return '\uD83D\uDCC1';
    }

    function renderFiles(files) {
        if (!files || !files.length) {
            flList.innerHTML = '<div class="fl-empty">Файлів ще немає. Натисніть «+ Завантажити».</div>';
            return;
        }
        var rows = '';
        files.forEach(function(f) {
            var icon    = fileIcon(f.mime_type, f.original_name);
            var dateStr = f.uploaded_at ? f.uploaded_at.substr(0,16).replace('T',' ') : '';
            var comment = f.comment ? '<div class="fl-comment">' + escHtml(f.comment) + '</div>' : '';
            rows += '<tr>'
                + '<td style="width:32px;text-align:center"><span class="fl-file-icon">' + icon + '</span></td>'
                + '<td><a href="/counterparties/api/download_file?id=' + f.id + '" class="fl-file-name" target="_blank">'
                +     escHtml(f.original_name) + '</a>' + comment + '</td>'
                + '<td><span class="fl-type-badge">' + escHtml(f.type_name || 'Інше') + '</span></td>'
                + '<td class="fl-size">' + formatSize(f.file_size) + '</td>'
                + '<td class="fl-date">' + escHtml(dateStr) + '</td>'
                + '<td><button class="fl-del-btn" data-id="' + f.id + '" title="\u0412\u0438\u0434\u0430\u043b\u0438\u0442\u0438">'
                +     '<svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
                + '</button></td>'
                + '</tr>';
        });
        flList.innerHTML = '<table class="fl-table">'
            + '<thead><tr><th></th><th>Назва</th><th>Тип</th><th>Розмір</th><th>Дата</th><th></th></tr></thead>'
            + '<tbody>' + rows + '</tbody></table>';

        // Bind delete buttons
        flList.querySelectorAll('.fl-del-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Видалити файл?')) return;
                var fid = parseInt(this.dataset.id, 10);
                var fd = new FormData();
                fd.append('id', fid);
                fetch('/counterparties/api/delete_file', { method:'POST', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (res.ok) { loadFiles(); if (typeof showToast === 'function') showToast('Файл видалено'); }
                    else alert(res.error || 'Помилка');
                });
            });
        });
    }

    // ── Upload form ──
    flBtnUpload.addEventListener('click', function() {
        flUploadForm.classList.add('open');
        flBtnUpload.style.display = 'none';
        // Ensure type selects are populated
        if (allTypes.length === 0) {
            fetch('/counterparties/api/get_files?id=' + cpId + '&sort=desc')
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.types && d.types.length) { allTypes = d.types; populateTypeSelects(d.types); }
                renderFiles(d.files || []);
            });
        }
    });

    flBtnCancel.addEventListener('click', function() {
        flUploadForm.classList.remove('open');
        flBtnUpload.style.display = '';
        flFileInput.value = '';
        flComment.value   = '';
        flErr.style.display = 'none';
    });

    flBtnSave.addEventListener('click', function() {
        flErr.style.display = 'none';
        if (!flFileInput.files || !flFileInput.files.length) {
            flErr.textContent = 'Оберіть файл';
            flErr.style.display = 'block';
            return;
        }
        var fd = new FormData();
        fd.append('id',       cpId);
        fd.append('type_id',  flTypeSelect.value || '0');
        fd.append('comment',  flComment.value.trim());
        fd.append('file',     flFileInput.files[0]);

        flBtnSave.disabled    = true;
        flProgress.style.display = '';
        fetch('/counterparties/api/upload_file', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(res) {
            flBtnSave.disabled    = false;
            flProgress.style.display = 'none';
            if (!res.ok) {
                flErr.textContent   = res.error || 'Помилка';
                flErr.style.display = 'block';
                return;
            }
            flUploadForm.classList.remove('open');
            flBtnUpload.style.display = '';
            flFileInput.value = '';
            flComment.value   = '';
            loadFiles();
            if (typeof showToast === 'function') showToast('Файл завантажено');
        })
        .catch(function() {
            flBtnSave.disabled    = false;
            flProgress.style.display = 'none';
            flErr.textContent   = 'Помилка мережі';
            flErr.style.display = 'block';
        });
    });

    // Filters
    flTypeFilter.addEventListener('change', loadFiles);
    flSortSel.addEventListener('change',   loadFiles);

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}());
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
