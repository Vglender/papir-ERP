<?php
/* @var int $totalCatalog */
/* @var int $totalRows */
/* @var int $totalPages */
/* @var int $page */
/* @var string $search */
/* @var string $sort */
/* @var string $order */
/* @var array $rows */
/* @var array|null $details */
/* @var array $state */
/* @var string $basePath */
$title     = 'Каталог';
$activeNav = 'catalog';
$subNav    = 'products';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
        .wrap {
            max-width: 1800px;
            margin: 0 auto;
            padding: 24px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .title {
            margin: 0 0 6px;
            font-size: 30px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef4ff;
            color: #1f4db8;
            margin-left: 6px;
        }
        .layout {
            display: grid;
            grid-template-columns: minmax(720px, 1fr) 460px;
            gap: 20px;
            align-items: start;
        }
        .card {
            background: #fff;
            border: 1px solid #d9e0ea;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .card h2, .card h3 {
            margin-top: 0;
        }
		.sticky-panel {
			position: sticky;
			top: var(--sticky-top);
			max-height: calc(100vh - var(--sticky-top));
			overflow-y: auto;
			padding-right: 6px;
		}
		.sticky-panel::-webkit-scrollbar {
			width: 8px;
		}

		.sticky-panel::-webkit-scrollbar-thumb {
			background: #cfd8e3;
			border-radius: 8px;
		}

		.sticky-panel::-webkit-scrollbar-track {
			background: transparent;
		}
		.filters {
			display: grid;
			grid-template-columns: minmax(260px, 1fr) 200px auto;
			gap: 10px;
			margin-bottom: 8px;
			align-items: end;
		}
		.filters-row2 {
			display: flex;
			align-items: center;
			gap: 6px;
			margin-bottom: 14px;
			flex-wrap: wrap;
		}
		.site-filter-label {
			font-size: 12px;
			font-weight: bold;
			color: #666;
			white-space: nowrap;
			margin-right: 2px;
		}
		/* .site-filter-pill → використовує .filter-pill з ui.css */
		/* Site badges in table — interactive toggles */
		.site-badges {
			display: flex;
			gap: 3px;
			flex-wrap: nowrap;
		}
		.site-badge {
			display: inline-block;
			padding: 2px 5px;
			border-radius: 3px;
			font-size: 10px;
			font-weight: 600;
			line-height: 1.5;
			white-space: nowrap;
			cursor: pointer;
			border: 1px solid transparent;
			transition: opacity .15s;
			user-select: none;
		}
		.site-badge:hover { opacity: .75; }
		.site-badge-off          { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
		.site-badge-mf           { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
		.site-badge-off.inactive { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
		.site-badge-mf.inactive  { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
		.site-badge.loading      { opacity: .4; cursor: wait; pointer-events: none; }
		/* Site toggles in product card */
		.prod-site-row {
			display: flex;
			align-items: center;
			gap: 5px;
			margin-top: 8px;
			flex-wrap: wrap;
		}
		.prod-site-badge {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			padding: 3px 8px;
			border-radius: 4px;
			font-size: 11px;
			font-weight: 600;
			cursor: pointer;
			border: 1px solid transparent;
			transition: opacity .15s;
			user-select: none;
		}
		.prod-site-badge:hover { opacity: .8; }
		.prod-site-badge.site-active-off  { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
		.prod-site-badge.site-active-mf   { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
		.prod-site-badge.site-inactive    { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
		.prod-site-badge.loading          { opacity: .4; cursor: wait; pointer-events: none; }
		.prod-site-badge.site-not-mapped  { background: #fff; color: #94a3b8; border-color: #cbd5e1; border-style: dashed; }
		.prod-site-badge.site-not-mapped:hover { border-color: #64748b; color: #475569; }
		.prod-site-dot { width:6px; height:6px; border-radius:50%; display:inline-block; background: currentColor; }
		/* BK status toggle */
		.bk-toggle-wrap {
			display: inline-flex;
			align-items: center;
			gap: 7px;
			margin-left: 14px;
			padding-left: 14px;
			border-left: 1px solid #e2e8f0;
		}
		.bk-toggle-label { font-size: 12px; color: #555; white-space: nowrap; }
		.bk-toggle {
			position: relative; width: 34px; height: 18px;
			display: inline-block; cursor: pointer; flex-shrink: 0;
		}
		.bk-toggle input { opacity: 0; width: 0; height: 0; }
		.bk-slider {
			position: absolute; inset: 0;
			background: #cbd5e1; border-radius: 18px;
			transition: background .2s;
		}
		.bk-slider::before {
			content: ''; position: absolute;
			width: 12px; height: 12px; left: 3px; top: 3px;
			background: #fff; border-radius: 50%;
			transition: transform .2s;
		}
		.bk-toggle input:checked + .bk-slider { background: #22c55e; }
		.bk-toggle input:checked + .bk-slider::before { transform: translateX(16px); }
        label {
            display: block;
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: bold;
        }
		input[type="text"]:not(.chip-typer),
		select {
			width: 100%;
			box-sizing: border-box;
			padding: 10px 12px;
			border: 1px solid #c8d1dd;
			border-radius: 8px;
			font-size: 14px;
			background: #fff;
		}
        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #c8d1dd;
            background: #fff;
            color: #222;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            box-sizing: border-box;
            text-align: center;
        }
        .btn-primary {
            background: #1f6feb;
            border-color: #1f6feb;
            color: #fff;
        }
        .btn-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-small {
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #e8edf3;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
        }
        th {
            background: #f8fafc;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
        }
        .sort-link {
            color: var(--text-muted);
            text-decoration: none;
        }
        .sort-link:hover { color: var(--text); }
        .sort-link.active { color: var(--text); font-weight: 600; }
        .num {
            white-space: nowrap;
        }
        .selected-row {
            background: #f0f6ff;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef4ff;
            color: #1f4db8;
        }
        .status-disabled {
            background: #f4f4f5;
            color: #666;
        }
        .status-action {
            background: #edfdf3;
            color: #157347;
        }
        .empty {
            color: #777;
        }
        .pagination {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #d9e0ea;
            border-radius: 8px;
            text-decoration: none;
            color: #222;
            background: #fff;
            font-size: 14px;
        }
        .pagination .current {
            background: #1f6feb;
            border-color: #1f6feb;
            color: #fff;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 8px 12px;
        }
        .info-grid .k {
            color: #666;
            font-size: 13px;
        }
        .info-grid .v {
            font-size: 14px;
            word-break: break-word;
        }
        .section {
            border-top: 1px solid #e8edf3;
            padding-top: 16px;
            margin-top: 16px;
        }
        .section:first-child {
            border-top: 0;
            padding-top: 0;
            margin-top: 0;
        }
        .section-collapsible > h3 {
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0;
        }
        .section-collapsible > h3::after {
            content: '▸';
            font-size: 13px;
            color: #9aaabb;
            transition: transform .2s;
            flex-shrink: 0;
        }
        .section-collapsible.open > h3::after {
            transform: rotate(90deg);
        }
        .section-collapsible > .section-body {
            display: none;
            margin-top: 12px;
        }
        .section-collapsible.open > .section-body {
            display: block;
        }
        /* editable field */
        .v-edit { display: flex; align-items: center; gap: 6px; }
        .edit-btn {
            background: none; border: none; cursor: pointer;
            color: #b0bec5; padding: 0; font-size: 14px; line-height: 1;
            flex-shrink: 0; transition: color .15s;
        }
        .edit-btn:hover { color: #4a90d9; }

        /* manufacturer modal */
        .mfr-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.35); z-index: 3000;
            align-items: center; justify-content: center;
        }
        .mfr-modal-overlay.open { display: flex; }
        .mfr-modal {
            background: #fff; border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0,0,0,.18);
            width: 360px; max-width: 95vw;
            display: flex; flex-direction: column; max-height: 90vh;
        }
        .mfr-modal-head {
            padding: 14px 16px 10px;
            border-bottom: 1px solid #e8edf3;
            display: flex; align-items: center; justify-content: space-between;
        }
        .mfr-modal-head h4 { margin: 0; font-size: 15px; }
        .mfr-modal-close {
            background: none; border: none; cursor: pointer;
            color: #999; font-size: 18px; line-height: 1; padding: 0;
        }
        .mfr-modal-close:hover { color: #333; }
        .mfr-modal-search-wrap {
            flex-shrink: 0; padding: 10px 16px 8px;
        }
        .mfr-modal-search {
            width: 100%; box-sizing: border-box;
            padding: 7px 10px; border: 1px solid #d0d9e3;
            border-radius: 5px; font-size: 13px; outline: none;
        }
        .mfr-modal-search:focus { border-color: #4a90d9; }
        .mfr-modal-list {
            overflow-y: auto; flex: 1;
            padding: 4px 0;
        }
        .mfr-modal-item {
            padding: 8px 16px; cursor: pointer; font-size: 13px;
            border-radius: 0; transition: background .1s;
        }
        .mfr-modal-item:hover { background: #f0f5fa; }
        .mfr-modal-item.selected {
            background: #e8f0fa; font-weight: 600; color: #2563c4;
        }
        .mfr-modal-item-none {
            padding: 8px 16px; font-size: 13px; color: #999; cursor: pointer;
        }
        .mfr-modal-item-none:hover { background: #fafafa; }
        .mfr-modal-footer {
            padding: 10px 16px;
            border-top: 1px solid #e8edf3;
            display: flex; justify-content: flex-end; gap: 8px;
        }
        .mfr-modal-save {
            background: #2563c4; color: #fff; border: none;
            border-radius: 5px; padding: 7px 18px; font-size: 13px;
            cursor: pointer;
        }
        .mfr-modal-save:hover { background: #1a4fa0; }
        .mfr-modal-cancel {
            background: none; color: #666; border: 1px solid #d0d9e3;
            border-radius: 5px; padding: 7px 14px; font-size: 13px;
            cursor: pointer;
        }
        .mfr-modal-cancel:hover { background: #f5f5f5; }
        .mfr-empty-msg { padding: 12px 16px; color: #999; font-size: 13px; }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 8px 12px;
            border: 1px solid #d9e0ea;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            font-size: 13px;
        }
        .tab-btn.active {
            background: #1f6feb;
            border-color: #1f6feb;
            color: #fff;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .scroll-box {
            max-height: 180px;
            overflow-y: auto;
            padding: 10px 12px;
            border: 1px solid #e8edf3;
            border-radius: 8px;
            background: #fafcff;
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .desc-html-box {
            max-height: 220px; overflow-y: auto; padding: 10px 12px;
            border: 1px solid #e8edf3; border-radius: 8px; background: #fafcff;
            font-size: 13px; line-height: 1.55; word-break: break-word; cursor: pointer;
        }
        .desc-html-box:hover { border-color: var(--blue-light); }
        .desc-html-box h1,.desc-html-box h2,.desc-html-box h3 { font-size: 13px; font-weight: 600; margin: 4px 0 3px; }
        .desc-html-box p  { margin: 0 0 5px; }
        .desc-html-box ul,.desc-html-box ol { margin: 0 0 5px; padding-left: 18px; }
        .desc-html-box li { margin-bottom: 2px; }
        .desc-html-box strong { font-weight: 600; }
        .desc-edit-ta {
            width: 100%; box-sizing: border-box; min-height: 140px; resize: vertical;
            padding: 8px 10px; border: 1px solid var(--blue-light); border-radius: 8px;
            font-size: 12px; font-family: monospace; outline: none; line-height: 1.4;
        }
        .desc-toggle-link { font-size: 11px; color: var(--blue); cursor: pointer; margin-left: 6px; text-decoration: underline; }
        .desc-save-row { display: flex; gap: 6px; margin-top: 4px; align-items: center; }
        .desc-save-status { font-size: 11px; color: var(--text-muted); }
        .mini-note {
            color: #666;
            font-size: 12px;
        }
        .photo-btn {
            font-size: 18px;
            line-height: 1;
            text-decoration: none;
        }
        .section-photo { padding: 0; }
        .prod-slider { position: relative; border-radius: var(--radius); overflow: hidden; }
        .prod-slider-slides { display: flex; transition: transform .28s ease; will-change: transform; }
        .prod-slider-slide { flex-shrink: 0; width: 100%; }
        .prod-slider-slide img { width: 100%; height: 210px; object-fit: contain; display: block; cursor: zoom-in; }
        .prod-ctrl { opacity: 0; transition: opacity .18s; pointer-events: none; }
        .prod-slider:hover .prod-ctrl { opacity: 1; pointer-events: auto; }
        .prod-slider-actions { position: absolute; top: 6px; right: 6px; display: flex; gap: 3px; }
        .prod-slider-act-btn { width: 24px; height: 24px; border-radius: var(--radius-sm); background: rgba(0,0,0,.42); color: #fff; border: none; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; padding: 0; }
        .prod-slider-act-btn:hover { background: rgba(0,0,0,.68); }
        .prod-slider-act-btn.del:hover { background: rgba(192,57,43,.8); }
        .prod-slider-arrow { position: absolute; top: 50%; transform: translateY(-50%); width: 28px; height: 28px; border-radius: 50%; background: rgba(255,255,255,.82); border: none; cursor: pointer; font-size: 22px; line-height: 1; display: flex; align-items: center; justify-content: center; color: #333; box-shadow: 0 1px 4px rgba(0,0,0,.22); padding: 0; }
        .prod-slider-arrow:hover { background: #fff; }
        .prod-slider-arrow-prev { left: 6px; }
        .prod-slider-arrow-next { right: 6px; }
        .prod-slider-counter { position: absolute; bottom: 7px; left: 50%; transform: translateX(-50%); font-size: 11px; background: rgba(0,0,0,.42); color: #fff; padding: 2px 8px; border-radius: 10px; white-space: nowrap; }
        .prod-slider-overlay { position: absolute; inset: 0; background: rgba(255,255,255,.65); display: none; align-items: center; justify-content: center; font-size: 12px; color: #888; }
        .prod-slider-overlay.active { display: flex; }
        .prod-img-empty { height: 80px; display: flex; align-items: center; justify-content: center; color: #bbb; font-size: 13px; border: 2px dashed var(--border); border-radius: var(--radius); }
        /* Footer: site badges + add button — always visible */
        .prod-img-footer { display: flex; align-items: center; gap: 6px; margin-top: 7px; min-height: 22px; }
        .prod-img-sites-row { display: flex; align-items: center; gap: 4px; flex: 1; flex-wrap: wrap; }
        .prod-img-sites-label { font-size: 11px; color: var(--text-muted); white-space: nowrap; }
        .prod-img-site { font-size: 10px; padding: 2px 7px; border-radius: 3px; cursor: pointer; user-select: none; border: 1px solid; line-height: 1.4; transition: background .12s, border-color .12s, color .12s; }
        .prod-img-site.active  { background: #e4f0fd; border-color: #5b9fd6; color: #1b6db0; }
        .prod-img-site.inactive { background: #f3f3f3; border-color: #d8d8d8; color: #aaa; }
        .prod-img-site.unavail { background: #f8f8f8; border-color: #ebebeb; color: #ccc; cursor: default; pointer-events: none; }
        .prod-slider-add-btn { width: 22px; height: 22px; border: 1px dashed var(--border); border-radius: var(--radius-sm); background: transparent; color: var(--text-muted); cursor: pointer; font-size: 18px; line-height: 1; display: flex; align-items: center; justify-content: center; padding: 0 0 1px; flex-shrink: 0; }
        .prod-slider-add-btn:hover { border-style: solid; border-color: var(--blue); color: var(--blue); }
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.68);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }
        .modal.open {
            display: flex;
        }
        .modal-content {
            position: relative;
            max-width: 92vw;
            max-height: 92vh;
        }
        .modal-image {
            max-width: 92vw;
            max-height: 92vh;
            border-radius: 12px;
            background: #fff;
        }
        .modal-close {
            position: absolute;
            right: -10px;
            top: -10px;
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 50%;
            background: #fff;
            cursor: pointer;
            font-size: 20px;
        }
        @media (max-width: 1200px) {
            .layout {
                grid-template-columns: 1fr;
            }
            .sticky-panel {
                position: static;
            }
        }
        @media (max-width: 800px) {
            .filters {
                grid-template-columns: 1fr;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

		.action-menu {
			position: relative;
			display: inline-block;
		}

		.action-menu-toggle {
			border: 1px solid #c8d1dd;
			background: #fff;
			border-radius: 8px;
			padding: 6px 10px;
			cursor: pointer;
			font-size: 16px;
		}

		.action-menu-dropdown {
			display: none;
			position: absolute;
			right: 0;
			top: 100%;
			margin-top: 6px;
			min-width: 180px;
			background: #fff;
			border: 1px solid #d9e0ea;
			border-radius: 10px;
			box-shadow: 0 8px 24px rgba(0,0,0,0.12);
			z-index: 50;
		}

		.action-menu.open .action-menu-dropdown {
			display: block;
		}

		.action-menu-dropdown a {
			display: block;
			padding: 10px 12px;
			color: #222;
			text-decoration: none;
			font-size: 14px;
		}

		.action-menu-dropdown a:hover {
			background: #f5f8fc;
		}
		tbody tr.js-row-click {
			cursor: pointer;
		}

		tbody tr.js-row-click:hover {
			background: #f8fbff;
		}

		#cell-popup {
			position: fixed;
			background: #fff;
			border: 1px solid #c8d1dd;
			border-radius: 6px;
			box-shadow: 0 4px 14px rgba(0,0,0,0.13);
			z-index: 9999;
			padding: 4px 0;
			min-width: 150px;
			display: none;
		}
		#cell-popup.open { display: block; }
		.cell-popup-btn {
			display: block;
			width: 100%;
			padding: 7px 14px;
			text-align: left;
			background: none;
			border: none;
			cursor: pointer;
			font-size: 13px;
			color: #24292f;
			white-space: nowrap;
		}
		.cell-popup-btn:hover { background: #f0f6ff; }
		.cell-popup-copied {
			display: none;
			padding: 5px 14px;
			font-size: 12px;
			color: #1a7f37;
		}
		.action-menu a {
			cursor: pointer;
		}
		.nav-arrows{
			display:flex;
			gap:8px;
			}

		/* Bulk BK status buttons */
		.bulk-sep { border-left: 1px solid #d9e0ea; height: 20px; display: inline-block; margin: 0 4px; align-self: center; }
		.btn-bk-disable { background: #fff1f1; color: #b42318; border-color: #fbc8c8; }
		.btn-bk-disable:hover:not(:disabled) { background: #ffe0e0; border-color: #e89898; }
		.btn-bk-enable  { background: #edfdf3; color: #157347; border-color: #a3e9bc; }
		.btn-bk-enable:hover:not(:disabled)  { background: #d4f7e3; border-color: #7dd9a0; }
		.btn-small:disabled { opacity: .45; cursor: not-allowed; }
		/* Bulk add-to-site modal */
		.bulk-site-modal { max-width: 400px; }
		.bulk-site-list { display: flex; flex-direction: column; gap: 10px; margin: 16px 0; }
		.bulk-site-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid #d9e0ea; border-radius: 8px; cursor: pointer; user-select: none; }
		.bulk-site-item:hover { background: #f5f8fc; }
		.bulk-site-item input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; }
		.bulk-site-item label { cursor: pointer; font-size: 14px; font-weight: 500; }
		.bulk-progress-bar { height: 6px; background: #e5eaf1; border-radius: 4px; margin: 12px 0 6px; overflow: hidden; }
		.bulk-progress-fill { height: 100%; background: #1f6feb; border-radius: 4px; width: 0; transition: width .2s; }
		.bulk-progress-text { font-size: 13px; color: #555; text-align: center; }
		.bulk-progress-log { max-height: 160px; overflow-y: auto; margin-top: 12px; font-size: 12px; color: #555; }
		.bulk-log-ok   { color: #157347; }
		.bulk-log-err  { color: #b42318; }
		.action-menu-dropdown .action-delete { color: #b42318; }
		.action-menu-dropdown .action-delete:hover { background: #fff1f1; }
		.action-menu-sep { height: 1px; background: #e8edf3; margin: 4px 0; }
		.module-links { display: flex; gap: 8px; flex-wrap: wrap; padding: 14px 0 4px; }
		#bulkClearSelection.has-selection {
			background: #fff4e5; border-color: #e6951a; color: #b26a00; font-weight: 600;
		}
		#bulkClearSelection.has-selection:hover { background: #ffe8c4; }

		/* Main photo sticker on slides */
		.slide-main-badges {
		    position: absolute; top: 5px; left: 5px;
		    display: flex; flex-direction: column; gap: 3px; z-index: 2;
		}
		.slide-main-badge {
		    font-size: 10px; font-weight: 700; padding: 1px 5px;
		    border-radius: 3px; line-height: 1.5; opacity: .88;
		}
		.slide-main-badge-off { background: #1e40af; color: #fff; }
		.slide-main-badge-mf  { background: #065f46; color: #fff; }
		/* Star button */
		.prod-slider-act-btn.star { color: #b26a00; }
		.prod-slider-act-btn.star:hover { background: #fff4e5; }
		/* Set-main modal */
		.set-main-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.38); z-index:4000; align-items:center; justify-content:center; }
		.set-main-overlay.open { display:flex; }
		.set-main-box { background:#fff; border-radius:10px; box-shadow:0 8px 32px rgba(0,0,0,.18); width:340px; max-width:95vw; padding:22px 22px 18px; }
		.set-main-title { font-size:15px; font-weight:700; margin:0 0 6px; }
		.set-main-hint  { font-size:13px; color:#666; margin:0 0 14px; }
		.set-main-sites { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
		.set-main-site-item { display:flex; align-items:center; gap:8px; font-size:14px; cursor:pointer; }
		.set-main-footer { display:flex; justify-content:flex-end; gap:8px; }


		/* ── Новий toolbar ─────────────────────────────────────────── */
		.cat-toolbar {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 10px;
		}
		.cat-toolbar-title {
			font-size: 18px;
			font-weight: 700;
			margin: 0;
			white-space: nowrap;
			color: var(--text);
			flex-shrink: 0;
		}
		.cat-search-wrap { flex: 1; min-width: 160px; }
		/* Normalize all toolbar interactive elements to same height */
		.cat-toolbar .btn { height: 34px; padding: 0 12px; }
		.cat-toolbar .chip-input { min-height: 34px; max-height: 34px; overflow: hidden; }
		/* split button */
		.cat-split-btn {
			display: flex; align-items: stretch;
			border: 1px solid var(--border-input);
			border-radius: var(--radius);
			overflow: visible;
			position: relative; flex-shrink: 0;
			background: var(--bg-card);
		}
		.cat-split-count {
			display: flex; align-items: center; justify-content: center;
			min-width: 34px; padding: 0 8px;
			font-size: 13px; font-weight: 600;
			color: var(--text-muted);
			background: var(--bg);
			border-right: 1px solid var(--border-input);
			border-radius: var(--radius) 0 0 var(--radius);
			user-select: none;
			transition: background .15s, color .15s;
		}
		.cat-split-count.has-sel {
			background: #fff4e5; color: var(--orange);
		}
		.cat-split-trigger {
			display: flex; align-items: center; gap: 5px;
			padding: 0 11px; height: 34px;
			border: none; background: transparent;
			color: var(--text); font-size: 13px;
			font-family: var(--font); cursor: pointer;
			border-radius: 0 var(--radius) var(--radius) 0;
			white-space: nowrap;
			transition: background .12s;
		}
		.cat-split-trigger:hover { background: var(--bg-hover); }
		.cat-split-trigger svg { width: 12px; height: 12px; opacity: .5; flex-shrink: 0; }
		/* dropdown */
		.cat-split-dd {
			display: none; position: absolute;
			top: calc(100% + 4px); right: 0;
			min-width: 210px;
			background: #fff;
			border: 1px solid var(--border);
			border-radius: var(--radius-lg);
			box-shadow: var(--shadow-modal);
			z-index: 500; padding: 4px 0;
		}
		.cat-split-btn.open .cat-split-dd { display: block; }
		.cat-dd-item {
			display: flex; align-items: center; gap: 8px;
			width: 100%; padding: 8px 14px;
			border: none; background: transparent;
			color: var(--text); font-size: 13px;
			font-family: var(--font); cursor: pointer;
			text-align: left; white-space: nowrap;
			transition: background .1s;
		}
		.cat-dd-item:hover { background: var(--bg-hover); }
		.cat-dd-item.danger { color: var(--red); }
		.cat-dd-item.danger:hover { background: var(--red-bg); }
		.cat-dd-sep { height: 1px; background: var(--border); margin: 4px 0; }
		.cat-dd-label {
			padding: 5px 14px 3px;
			font-size: 11px; font-weight: 600;
			color: var(--text-faint); text-transform: uppercase;
			letter-spacing: .5px;
		}
		/* print submenu */
		.cat-dd-print { position: relative; }
		.cat-dd-print > .cat-dd-item::after {
			content: '▸'; margin-left: auto; opacity: .4; font-size: 11px;
		}
		.cat-dd-print-sub {
			display: none; position: absolute;
			left: 100%; top: -4px;
			min-width: 190px;
			background: #fff;
			border: 1px solid var(--border);
			border-radius: var(--radius-lg);
			box-shadow: var(--shadow-modal);
			z-index: 501; padding: 4px 0;
		}
		.cat-dd-print:hover .cat-dd-print-sub { display: block; }
		/* .filter-bar і .filter-pill — з ui.css */

		/* Bulk BK confirm modal */
		.bk-confirm-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.38); z-index: 4000; align-items: center; justify-content: center; }
		.bk-confirm-overlay.open { display: flex; }
		.bk-confirm-box { background: #fff; border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,.18); width: 400px; max-width: 95vw; padding: 24px 24px 20px; }
		.bk-confirm-title { font-size: 16px; font-weight: bold; margin: 0 0 10px; }
		.bk-confirm-body  { font-size: 14px; color: #444; margin-bottom: 20px; line-height: 1.55; }
		.bk-confirm-footer { display: flex; justify-content: flex-end; gap: 8px; }
		.bk-confirm-ok-red   { background: #b42318; }
		.bk-confirm-ok-red:hover { background: #8f1b10; }
		.bk-confirm-ok-green { background: #157347; }
		.bk-confirm-ok-green:hover { background: #0f5233; }
		.cat-picker-item { padding: 8px 16px; cursor: pointer; font-size: 13px; }
		.cat-picker-item:hover { background: #f1f5f9; }
		.cat-picker-empty { padding: 16px; color: #94a3b8; font-size: 13px; text-align: center; }

			.nav-arrow{
			border:1px solid #c8d1dd;
			background:#fff;
			border-radius:8px;
			padding:6px 10px;
			cursor:pointer;
			font-size:16px;
			}
			tbody tr{
				transition:background .15s;
				}

				tbody tr:hover{
				background:#f3f7ff;
				}

		.top-status-badges {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}

		.status-pill {
			display: inline-block;
			padding: 4px 10px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: bold;
		}

		.status-pill-on {
			background: #edfdf3;
			color: #157347;
		}

		.status-pill-off {
			background: #fff1f1;
			color: #b42318;
		}

		.status-pill-action {
			background: #fff4e5;
			color: #b26a00;
		}

		.status-pill-stock {
			background: #eef4ff;
			color: #1f4db8;
		}
		.ean-barcode-wrapper{
			margin-top:18px;
			padding-top:14px;
			border-top:1px solid #eef2f6;
			text-align:center;
		}

		#eanBarcode{
			width:100%;
			height:80px;
			background:#fff;
			cursor:pointer;
		}

		#eanBarcode:hover{
			background:#f8fafc;
		}
		.ean-copy-hint{
			margin-top:6px;
			font-size:12px;
			color:#888;
		}
		.copy-toast{
			position:fixed;
			bottom:20px;
			right:20px;
			background:#1f6feb;
			color:#fff;
			padding:8px 14px;
			border-radius:8px;
			font-size:13px;
			opacity:0;
			transform:translateY(10px);
			transition:all .25s ease;
			pointer-events:none;
		}

		.copy-toast.show{
			opacity:1;
			transform:translateY(0);
		}
			.price-card {
				display: flex;
				flex-direction: column;
				gap: 14px;
			}

			.price-group {
				border: 1px solid #e8edf3;
				border-radius: 10px;
				padding: 12px;
				background: #fafcff;
			}

		.price-group-muted {
			background: #fbfbfc;
		}

		.price-group-title {
			font-size: 12px;
			font-weight: bold;
			color: #666;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			margin-bottom: 10px;
		}

		.price-main-row {
			display: grid;
			gap: 10px;
		}
		.price-main-row-2 {
			grid-template-columns: repeat(2, 1fr);
		}
		.price-main-row-3 {
			grid-template-columns: repeat(3, 1fr);
		}

		.price-sub-row {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 10px;
		}
		.price-sale-text {
			color: #b42318;
			font-weight: bold;
		}

		.price-main-item,
		.price-sub-item {
			background: #fff;
			border: 1px solid #eef2f6;
			border-radius: 8px;
			padding: 10px;
		}
		.price-main-item-muted {
			background: #f8f9fb;
		}
		.price-main-item-muted .price-label {
			color: #999;
		}
		.price-main-item-muted .price-value {
			color: #777;
			font-size: 14px;
		}
		.price-rrp-note {
			font-size: 12px;
			color: #aab3bf;
			margin-top: 6px;
			padding: 0 2px;
		}

		.price-label {
			font-size: 12px;
			color: #666;
			margin-bottom: 6px;
		}

		.price-value {
			font-size: 16px;
			font-weight: bold;
			line-height: 1.2;
		}

		.price-badge-sale {
			display: inline-block;
			padding: 6px 10px;
			border-radius: 8px;
			background: #fff1f1;
			color: #b42318;
			font-weight: bold;
		}

		.price-discounts {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
		}

		.discount-chip {
			display: inline-block;
			padding: 5px 10px;
			border-radius: 999px;
			background: #eef4ff;
			color: #1f4db8;
			font-size: 13px;
			white-space: nowrap;
		}

		.price-empty {
			color: #777;
			font-size: 14px;
		}
		.price-empty-note {
			color: #aab3bf;
			font-size: 13px;
			font-style: italic;
			padding: 2px 0 4px;
		}

		@media (max-width: 800px) {
			.price-main-row,
			.price-sub-row {
				grid-template-columns: 1fr;
			}
		}
		.stock-card {
			border: 1px solid #e8edf3;
			border-radius: 10px;
			padding: 12px;
			background: #fafcff;
		}

		.stock-grid {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 10px;
		}

		.stock-item {
			background: #fff;
			border: 1px solid #eef2f6;
			border-radius: 8px;
			padding: 10px;
		}

		.stock-label {
			font-size: 12px;
			color: #666;
			margin-bottom: 6px;
		}

		.stock-value {
			font-size: 18px;
			font-weight: bold;
			line-height: 1.2;
		}
		.content-card {
			display: flex;
			flex-direction: column;
			gap: 14px;
		}

		.content-field {
			display: flex;
			flex-direction: column;
			gap: 6px;
		}

		.content-label {
			font-size: 12px;
			font-weight: bold;
			color: #666;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		.content-inline-value {
			font-size: 15px;
			font-weight: bold;
			line-height: 1.35;
		}

		.seo-card {
			border: 1px solid #e8edf3;
			border-radius: 10px;
			background: #fafcff;
			padding: 12px;
		}

		.seo-card-title {
			font-size: 12px;
			font-weight: bold;
			color: #666;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			margin-bottom: 10px;
		}

		.seo-row {
			display: grid;
			grid-template-columns: 130px 1fr;
			gap: 10px;
			padding: 6px 0;
			border-bottom: 1px solid #eef2f6;
		}

		.seo-row:last-child {
			border-bottom: 0;
		}

		.seo-label {
			color: #666;
			font-size: 13px;
		}

		.seo-value {
			font-size: 14px;
			word-break: break-word;
		}
		.specs-card {
			border: 1px solid #e8edf3;
			border-radius: 10px;
			background: #fafcff;
			padding: 12px;
		}

		.spec-row {
			display: grid;
			grid-template-columns: 130px 1fr;
			gap: 10px;
			padding: 8px 0;
			border-bottom: 1px solid #eef2f6;
		}

		.spec-row:last-child {
			border-bottom: 0;
		}

		.spec-label {
			color: #666;
			font-size: 13px;
		}

		.spec-value {
			font-size: 14px;
			word-break: break-word;
		}
		@media (max-width: 800px) {
			.price-main-row-2,
			.price-main-row-3,
			.price-sub-row,
			.stock-grid,
			.seo-row,
			.spec-row {
				grid-template-columns: 1fr;
			}
		}
		/* ── Specs edit form ──────────────────────────────────── */
		.specs-form { display: flex; flex-direction: column; gap: 12px; }
		.specs-form-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
		.specs-form-label { min-width: 80px; font-size: 13px; color: var(--text-muted); }
		.specs-form-row input[type="number"] {
			width: 90px; padding: 5px 8px;
			border: 1px solid var(--border-input); border-radius: var(--radius);
			font-size: 13px; font-family: var(--font); outline: none;
		}
		.specs-form-row input[type="number"]:focus { border-color: var(--blue-light); }
		.specs-form-row select {
			padding: 5px 8px; border: 1px solid var(--border-input); border-radius: var(--radius);
			font-size: 13px; font-family: var(--font); outline: none; background: #fff; cursor: pointer;
		}
		.specs-form-row select:focus { border-color: var(--blue-light); }
		.specs-sep { color: var(--text-muted); font-size: 14px; padding: 0 2px; }
		/* ── Attributes panel ───────────────────────────────────── */
		.attr-site-tabs { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
		.attr-site-tab {
			padding: 5px 14px; border-radius: 6px; font-size: 13px; cursor: pointer;
			border: 1px solid var(--border-input); background: #fff; color: var(--text-muted);
		}
		.attr-site-tab.active { background: var(--blue); color: #fff; border-color: var(--blue); }
		.attr-lang-tabs { display: flex; gap: 4px; margin-bottom: 10px; }
		.attr-lang-tab {
			padding: 3px 12px; border-radius: 4px; font-size: 12px; cursor: pointer;
			border: 1px solid var(--border-input); background: #fff; color: var(--text-muted);
		}
		.attr-lang-tab.active { background: #e8f0fe; color: var(--blue); border-color: var(--blue-light); }
		.attr-value-input {
			width: 100%; box-sizing: border-box; padding: 5px 8px;
			border: 1px solid transparent; border-radius: var(--radius);
			font-size: 13px; font-family: var(--font); outline: none; background: transparent;
		}
		.attr-value-input:hover { border-color: var(--border-input); background: #fff; }
		.attr-value-input:focus { border-color: var(--blue-light); background: #fff; }
		.attr-add-row { display: flex; align-items: center; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
		.attr-add-search {
			flex: 1; min-width: 140px; padding: 5px 10px;
			border: 1px solid var(--border-input); border-radius: var(--radius);
			font-size: 13px; font-family: var(--font); outline: none;
		}
		.attr-add-search:focus { border-color: var(--blue-light); }
		.attr-add-list {
			flex: 1; min-width: 180px; padding: 5px 8px;
			border: 1px solid var(--border-input); border-radius: var(--radius);
			font-size: 13px; font-family: var(--font); outline: none; background: #fff; cursor: pointer;
		}
		.attr-add-list:focus { border-color: var(--blue-light); }
		.bulk-toolbar {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 12px;
			flex-wrap: wrap;
			margin-bottom: 14px;
			padding: 10px 12px;
			border: 1px solid #d9e0ea;
			border-radius: 10px;
			background: #f8fafc;
		}

		.bulk-left {
			font-size: 14px;
		}

		.bulk-actions {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}

		.row-selector {
			cursor: pointer;
		}

		/* SEO site×language tabs */
		.seo-site-tabs { display: flex; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--border); }
		.seo-site-tab {
			padding: 6px 14px; font-size: 13px; font-weight: 500; cursor: pointer;
			background: none; border: none; border-bottom: 2px solid transparent;
			margin-bottom: -2px; color: var(--text-muted);
		}
		.seo-site-tab.active { color: var(--blue); border-bottom-color: var(--blue); }
		.seo-site-pane { display: none; }
		.seo-site-pane.active { display: block; }
		.seo-site-url { font-size: 12px; color: var(--blue); word-break: break-all; }
		.seo-site-link {
		    display: inline-flex; align-items: center; gap: 5px;
		    text-decoration: none; color: var(--blue);
		    border: 1px solid var(--blue-light); border-radius: var(--radius);
		    padding: 3px 8px; font-size: 11px; font-weight: 600; letter-spacing: .03em;
		    transition: background .15s, color .15s;
		}
		.seo-site-link:hover { background: var(--blue); color: #fff; border-color: var(--blue); }
		.seo-site-link svg { flex-shrink: 0; }
    </style>
<div class="wrap">
    <div class="layout">
        <div class="card">
			<form method="get" action="/catalog" id="catalogFilterForm">
				<input type="hidden" name="sort" value="<?php echo ViewHelper::h($sort); ?>">
				<input type="hidden" name="order" value="<?php echo ViewHelper::h($order); ?>">
				<input type="hidden" name="page" value="1">
				<input type="hidden" name="site_filter" id="siteFilterHidden" value="<?php echo ViewHelper::h($siteFilterRaw); ?>">
				<?php if ($selected > 0) { ?>
					<input type="hidden" name="selected" value="<?php echo (int)$selected; ?>">
				<?php } ?>

				<!-- ── Toolbar row ── -->
				<div class="cat-toolbar">
					<h1 class="cat-toolbar-title">Товари
						<span style="font-size:12px;font-weight:400;color:var(--text-faint);margin-left:6px;"><?php echo (int)$totalRows; ?> / <?php echo (int)$totalCatalog; ?></span>
					</h1>

					<button type="button" class="btn btn-primary btn-sm" id="btnAddProduct">+ Додати</button>

					<div class="cat-search-wrap">
						<div class="chip-input" id="searchChipBox">
							<input type="text" class="chip-typer" id="searchChipTyper" placeholder="ID, артикул або назва…" autocomplete="off">
							<div class="chip-actions">
								<button type="button" class="chip-act-btn chip-act-clear hidden" id="chipClearBtn" title="Очистити">&#x2715;</button>
								<button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
									<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
								</button>
							</div>
						</div>
						<input type="hidden" name="search" id="search" value="<?php echo ViewHelper::h($search); ?>">
					</div>


					<!-- Split button: count | Змінити ▾ -->
					<div class="cat-split-btn" id="catSplitBtn">
						<span class="cat-split-count" id="selectedCount" title="Вибрано товарів">0</span>
						<button type="button" class="cat-split-trigger" id="catSplitTrigger">
							Змінити
							<svg viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
						</button>
						<div class="cat-split-dd" id="catSplitDd">
							<button type="button" class="cat-dd-item" id="bulkCopyIds">
								<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="5" y="5" width="9" height="9" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M11 5V3a2 2 0 0 0-2-2H3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="1.4"/></svg>
								Копіювати ID
							</button>
							<button type="button" class="cat-dd-item" id="bulkPriceList">
								<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M4 1h6l4 4v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/><path d="M9 1v4h4M5 9h6M5 12h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
								Прайс-лист
							</button>
							<div class="cat-dd-sep"></div>
							<button type="button" class="cat-dd-item" id="bulkAddToSite">
								<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 5v6M5 8h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
								Додати на сайт
							</button>
							<button type="button" class="cat-dd-item btn-bk-enable" id="bulkBkEnable">
								<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 8l4 4 8-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
								Увімкнути в БК
							</button>
							<button type="button" class="cat-dd-item btn-bk-disable" id="bulkBkDisable">
								<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
								Вимкнути в БК
							</button>
							<div class="cat-dd-sep"></div>
							<button type="button" class="cat-dd-item danger" id="bulkDelete">
								<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
								Видалити
							</button>
							<div class="cat-dd-sep"></div>
							<div class="cat-dd-label">Печать</div>
							<div class="cat-dd-print">
								<button type="button" class="cat-dd-item">
									<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M4 6V2h8v4M3 6h10a1 1 0 0 1 1 1v5H2V7a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/><path d="M4 11v3h8v-3" stroke="currentColor" stroke-width="1.4"/></svg>
									Друкована форма
								</button>
								<div class="cat-dd-print-sub">
									<button type="button" class="cat-dd-item" id="printPriceListPdf">Прайс-лист PDF</button>
									<button type="button" class="cat-dd-item" id="printLabelsPdf">Цінники PDF</button>
								</div>
							</div>
						</div>
					</div>

					<button type="button" class="btn btn-ghost btn-sm" id="bulkClearSelection" title="Скинути вибір">✕</button>
				</div>

				<!-- ── Filter bar ── -->
				<div class="filter-bar">
					<div class="filter-bar-group">
						<span class="filter-bar-label">Магазин</span>
						<?php
						$_checked = array();
						foreach ($checkedSiteFilter as $_v) $_checked[(string)$_v] = true;
						?>
						<label class="filter-pill <?php echo isset($_checked['bk']) ? 'active' : ''; ?>">
							<input type="checkbox" class="js-site-filter" value="bk" <?php echo isset($_checked['bk']) ? 'checked' : ''; ?>>
							БК
						</label>
						<?php foreach ($sites as $_site) { ?>
						<?php $_sfKey = (string)$_site['site_id']; ?>
						<label class="filter-pill <?php echo isset($_checked[$_sfKey]) ? 'active' : ''; ?>">
							<input type="checkbox" class="js-site-filter" value="<?php echo (int)$_site['site_id']; ?>" <?php echo isset($_checked[$_sfKey]) ? 'checked' : ''; ?>>
							<?php echo ViewHelper::h($_site['badge']); ?>
						</label>
						<?php } ?>
					</div>
					<!-- Gear: налаштування складу фільтрів (placeholder) -->
					<button type="button" class="filter-bar-gear" title="Налаштувати фільтри" id="filterBarGear">
						<svg viewBox="0 0 16 16" fill="none">
							<circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/>
							<path d="M8 1.5v1M8 13.5v1M1.5 8h1M13.5 8h1M3.4 3.4l.7.7M11.9 11.9l.7.7M11.9 3.4l-.7.7M4.1 11.9l-.7.7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
			</form>
            <table>
                <thead>
				 <tr>
					<th style="width:36px;">
						<input type="checkbox" id="selectAllRows">
					</th>
					<th><?php echo catalogSortLink('ID', 'product_id', $state, $basePath); ?></th>
					<th><?php echo catalogSortLink('Артикул', 'product_article', $state, $basePath); ?></th>
					<th><?php echo catalogSortLink('Название', 'name', $state, $basePath); ?></th>
					<th style="width:80px;"><?php echo catalogSortLink('Закупка', 'price_cost', $state, $basePath); ?></th>
					<th style="width:80px;"><?php echo catalogSortLink('Продажа', 'price_sale', $state, $basePath); ?></th>
					<th style="width:70px;">Action</th>
					<th style="width:60px;">Остаток</th>
					<th style="width:32px;">Фото</th>
					<th style="width:54px;">Сайти</th>
					<th>Дії</th>
				</tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)) { ?>
                    <?php foreach ($rows as $row) { ?>
                        <?php
                        $isSelected = ((int)$row['product_id'] === (int)$selected);
                        $selectUrl = ViewHelper::buildUrl($basePath, array(
                            'search'      => $search,
                            'site_filter' => $siteFilterRaw,
                            'sort'        => $sort,
                            'order'       => $order,
                            'page'        => $page,
                            'selected'    => (int)$row['product_id'],
                        ));
                        ?>
					<tr

						class="<?php echo $isSelected ? 'selected-row' : ''; ?> js-row-click"
						data-url="<?php echo ViewHelper::h($selectUrl); ?>"
						data-product-id="<?php echo (int)$row['product_id']; ?>"
						data-product-article="<?php echo ViewHelper::h((string)$row['product_article']); ?>"
						data-product-name="<?php echo ViewHelper::h((string)$row['name']); ?>"
						data-product-price="<?php echo ViewHelper::h($row['price_sale'] !== null ? (string)$row['price_sale'] : ''); ?>"
						data-product-action-price="<?php echo ViewHelper::h($row['action_price'] !== null ? (string)$row['action_price'] : ''); ?>"
					data-papir-id="<?php echo (int)$row['product_id']; ?>"
					data-bk-status="<?php echo (int)$row['status']; ?>"
					>

							<td>
								<input
									type="checkbox"
									class="row-selector"
									value="<?php echo (int)$row['product_id']; ?>"
								>
							</td>

							<td class="num"><?php echo (int)$row['product_id']; ?></td>
                            <td><?php echo renderValue($row['product_article']); ?></td>
                            <td><?php echo renderValue($row['name']); ?></td>
                            <td class="num"><?php echo renderPrice($row['price_cost']); ?></td>
                            <td class="num"><?php echo renderPrice($row['price_sale']); ?></td>
                            <td class="num">
                                <?php if ($row['action_price'] !== null) { ?>
                                    <span class="status-badge status-action"><?php echo renderPrice($row['action_price']); ?></span>
                                <?php } else { ?>
                                    —
                                <?php } ?>
                            </td>
                            <td class="num"><?php echo (int)$row['real_stock']; ?></td>
                            <td class="num">
                                <?php if ($row['main_image'] !== '') { ?>
                                    <a
                                        href="#"
                                        class="photo-btn js-open-image"
                                        data-image="<?php echo ViewHelper::h($row['main_image']); ?>"
                                        title="Открыть фото"
                                    >&#128065;</a>
                                <?php } else { ?>
                                    —
                                <?php } ?>
                            </td>
                            <td onclick="event.stopPropagation()">
                                <?php if (!empty($row['site_statuses'])) { ?>
                                <div class="site-badges">
                                    <?php foreach ($sites as $_s) { ?>
                                    <?php $_sid = (int)$_s['site_id']; ?>
                                    <?php if (isset($row['site_statuses'][$_sid])) { ?>
                                    <?php $_son = (int)$row['site_statuses'][$_sid]; ?>
                                    <span
                                        class="site-badge site-badge-<?php echo ViewHelper::h($_s['badge']); ?><?php echo $_son ? '' : ' inactive'; ?>"
                                        data-product-id="<?php echo (int)$row['product_id']; ?>"
                                        data-site-id="<?php echo $_sid; ?>"
                                        data-enabled="<?php echo $_son; ?>"
                                        data-bk-status="<?php echo (int)$row['status']; ?>"
                                        title="<?php echo ViewHelper::h($_s['name']); ?>: <?php echo $_son ? 'активен' : 'неактивен'; ?>"
                                    ><?php echo ViewHelper::h($_s['badge']); ?></span>
                                    <?php } ?>
                                    <?php } ?>
                                </div>
                                <?php } ?>
                            </td>
						<td>
							<div class="action-menu">
								<button type="button" class="action-menu-toggle">&#9776;</button>

								<div class="action-menu-dropdown">
									<a href="/prices?search=<?php echo (int)$row['product_id']; ?>" target="_blank">Прайси →</a>
									<a href="/prices/suppliers?search=<?php echo (int)$row['product_id']; ?>" target="_blank">Постачальники →</a>
									<a href="/action?search=<?php echo (int)$row['product_id']; ?>" target="_blank">Акція →</a>
									<div class="action-menu-sep"></div>
									<a href="#" class="action-delete js-row-delete" data-product-id="<?php echo (int)$row['product_id']; ?>">Видалити</a>
								</div>
							</div>
						</td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="11" class="empty">Данные не найдены.</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1) { ?>
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo ViewHelper::h(catalogPageLink($page - 1, $state, $basePath)); ?>">&#8592; Назад</a>
                    <?php } ?>

                    <?php
                    $startPage = $page - 3;
                    $endPage = $page + 3;

                    if ($startPage < 1) {
                        $startPage = 1;
                    }

                    if ($endPage > $totalPages) {
                        $endPage = $totalPages;
                    }
                    ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++) { ?>
                        <?php if ($p == $page) { ?>
                            <span class="current"><?php echo $p; ?></span>
                        <?php } else { ?>
                            <a href="<?php echo ViewHelper::h(catalogPageLink($p, $state, $basePath)); ?>"><?php echo $p; ?></a>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($page < $totalPages) { ?>
                        <a href="<?php echo ViewHelper::h(catalogPageLink($page + 1, $state, $basePath)); ?>">Вперёд &#8594;</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <div class="card sticky-panel">
            <?php if ($details !== null) { ?>
                <div class="section">
				<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;">

					<div style="flex:1;">
						<div style="font-size:22px;font-weight:bold;line-height:1.25;margin-bottom:8px;">
							<?php echo renderValue($details['name']); ?>
						</div>

						<div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
							<div class="top-status-badges" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
								<span class="status-pill <?php echo (int)$details['status'] === 1 ? 'status-pill-on' : 'status-pill-off'; ?>" id="bkStatusPill">
									<?php echo (int)$details['status'] === 1 ? 'Включен' : 'Отключен'; ?>
								</span>

								<?php if ($details['special'] !== null) { ?>
									<span class="status-pill status-pill-action">Акция</span>
								<?php } ?>

								<?php if ((int)$details['real_stock'] > 0) { ?>
									<span class="status-pill status-pill-stock">В наличии</span>
								<?php } else { ?>
									<span class="status-pill status-pill-off">Нет в наличии</span>
								<?php } ?>
							</div>

							<div class="bk-toggle-wrap">
								<span class="bk-toggle-label">БК</span>
								<label class="bk-toggle" title="Активность в базовом каталоге">
									<input type="checkbox" id="bkStatusToggle"
										<?php echo (int)$details['status'] === 1 ? 'checked' : ''; ?>
										data-product-id="<?php echo (int)$details['product_id']; ?>">
									<span class="bk-slider"></span>
								</label>
							</div>
						</div>

						<?php if (!empty($details['site_statuses'])) { ?>
						<div class="prod-site-row" id="prodSiteRow">
							<?php foreach ($details['site_statuses'] as $_ps) { ?>
							<?php
								$_psMapped = $_ps['site_product_id'] !== null;
								$_psOn     = $_psMapped ? (int)$_ps['status'] : 0;
								$_psBadge  = $_ps['badge'];
								if (!$_psMapped) {
									$_psClass = 'site-not-mapped';
									$_psTitle = ViewHelper::h($_ps['site_name']) . ': не на сайті — клік щоб додати';
								} elseif ($_psOn) {
									$_psClass = 'site-active-' . ViewHelper::h($_psBadge);
									$_psTitle = ViewHelper::h($_ps['site_name']) . ': активен — клик для переключения';
								} else {
									$_psClass = 'site-inactive';
									$_psTitle = ViewHelper::h($_ps['site_name']) . ': неактивен — клик для переключения';
								}
							?>
							<span
								class="prod-site-badge <?php echo $_psClass; ?>"
								data-product-id="<?php echo (int)$details['product_id']; ?>"
								data-site-id="<?php echo (int)$_ps['site_id']; ?>"
								data-mapped="<?php echo $_psMapped ? '1' : '0'; ?>"
								data-enabled="<?php echo $_psOn; ?>"
								data-bk-status="<?php echo (int)$details['status']; ?>"
								data-badge="<?php echo ViewHelper::h($_psBadge); ?>"
								data-site-name="<?php echo ViewHelper::h($_ps['site_name']); ?>"
								title="<?php echo $_psTitle; ?>"
							>
								<?php if (!$_psMapped) { ?><span style="font-size:12px;margin-right:2px">+</span><?php } else { ?><span class="prod-site-dot"></span><?php } ?>
								<?php echo ViewHelper::h($_ps['site_name']); ?>
							</span>
							<?php } ?>
						</div>
						<?php } ?>
					</div>

					<div class="nav-arrows">
						<button class="nav-arrow" id="navPrev" type="button">&#8592;</button>
						<button class="nav-arrow" id="navNext" type="button">&#8594;</button>
					</div>

				</div>



                    <div class="info-grid">
                        <div class="k">Производитель</div>
                        <div class="v v-edit">
                            <span id="mfrName"><?php echo renderValue($details['manufacturer_name']); ?></span>
                            <button class="edit-btn" id="mfrEditBtn" title="Змінити виробника" type="button"
                                data-product-id="<?php echo (int)$details['product_id']; ?>"
                                data-manufacturer-id="<?php echo (int)$details['manufacturer_id']; ?>">&#9998;</button>
                        </div>

						<div class="k">Категорія</div>
                        <div class="v v-edit">
                            <span id="catName"><?php echo renderValue($details['category_name']); ?></span>
                            <button class="edit-btn" id="catEditBtn" title="Змінити категорію" type="button"
                                data-product-id="<?php echo (int)$details['product_id']; ?>"
                                data-category-id="<?php echo (int)($details['categoria_id'] ? $details['categoria_id'] : 0); ?>">&#9998;</button>
                        </div>
                    </div>
                </div>

                <div class="section section-photo">
                    <?php
                    $_prodImagesJson = json_encode(isset($details['images']) ? $details['images'] : array());
                    $_prodIdOff = (int)(isset($details['id_off']) ? $details['id_off'] : 0);
                    $_prodIdMf  = (int)(isset($details['id_mf'])  ? $details['id_mf']  : 0);
                    $_mainPerSite = json_encode(isset($details['main_image_per_site']) ? $details['main_image_per_site'] : array());
                    ?>
                    <div class="prod-slider" id="prodSlider" style="display:none">
                        <div class="prod-slider-slides" id="prodSliderSlides"></div>
                        <div class="prod-slider-actions prod-ctrl">
                            <button type="button" class="prod-slider-act-btn star" id="prodImgSetMainBtn" title="Зробити головним" style="display:none">&#9733;</button>
                            <button type="button" class="prod-slider-act-btn rep" id="prodImgReplaceBtn" title="Замінити фото">&#9998;</button>
                            <button type="button" class="prod-slider-act-btn del" id="prodImgDeleteBtn" title="Видалити фото">&#10005;</button>
                        </div>
                        <button type="button" class="prod-slider-arrow prod-slider-arrow-prev prod-ctrl" id="prodSliderPrev" style="display:none">&#8249;</button>
                        <button type="button" class="prod-slider-arrow prod-slider-arrow-next prod-ctrl" id="prodSliderNext" style="display:none">&#8250;</button>
                        <div class="prod-slider-counter prod-ctrl" id="prodSliderCounter" style="display:none"></div>
                        <div class="prod-slider-overlay" id="prodSliderOverlay">Завантаження…</div>
                    </div>
                    <div class="prod-img-empty" id="prodImgEmpty">Фото відсутнє</div>
                    <!-- Footer: site badges (always visible) + add button -->
                    <div class="prod-img-footer">
                        <div class="prod-img-sites-row" id="prodSliderSites"></div>
                        <button type="button" class="prod-slider-add-btn" id="prodImgUploadBtn" title="Додати фото">+</button>
                    </div>
                    <!-- Set main photo modal -->
                    <div class="set-main-overlay" id="setMainOverlay">
                        <div class="set-main-box">
                            <p class="set-main-title">Зробити фото головним</p>
                            <p class="set-main-hint">Обери сайт де це фото стане головним:</p>
                            <div class="set-main-sites" id="setMainSiteList"></div>
                            <div class="set-main-footer">
                                <button class="btn" id="setMainCancel">Скасувати</button>
                                <button class="btn btn-primary" id="setMainConfirm">Застосувати</button>
                            </div>
                        </div>
                    </div>
                    <input type="file" id="prodImgFile" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
                    <input type="file" id="prodImgReplaceFile" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
                </div>

			<div class="section">
				<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px;">
					<h3 style="margin:0;">Цены</h3>
					<div style="display:flex;gap:6px;">
						<a href="/prices?search=<?php echo (int)$details['product_id']; ?>" target="_blank" class="btn btn-xs">Прайси →</a>
						<a href="/prices/suppliers?search=<?php echo (int)$details['product_id']; ?>" target="_blank" class="btn btn-xs">Постачальники →</a>
						<a href="/action?search=<?php echo (int)$details['product_id']; ?>" target="_blank" class="btn btn-xs">Акція →</a>
					</div>
				</div>

				<div class="price-card">

					<div class="price-group">
						<div class="price-main-row price-main-row-3">
							<div class="price-main-item">
								<div class="price-label">Продажа</div>
								<div class="price-value"><?php echo renderPrice($details['price_sale']); ?></div>
							</div>
							<div class="price-main-item">
								<div class="price-label">Акция</div>
								<div class="price-value">
									<?php if ($details['special'] !== null) { ?>
										<span class="price-sale-text"><?php echo renderPrice($details['special']['price']); ?></span>
									<?php } else { ?>—<?php } ?>
								</div>
							</div>
							<div class="price-main-item price-main-item-muted">
								<div class="price-label">Закупочная</div>
								<div class="price-value"><?php echo renderPrice($details['price_cost']); ?></div>
							</div>
						</div>
						<?php if (!empty($details['price_rrp']) && (float)$details['price_rrp'] > 0) { ?>
						<div class="price-rrp-note">RRP: <?php echo renderPrice($details['price_rrp']); ?></div>
						<?php } ?>
					</div>

					<?php if (!empty($details['discounts']['quantity_discounts'])) { ?>
					<div class="price-group">
						<div class="price-group-title">Скидки от количества</div>
						<div class="price-discounts">
							<?php foreach ($details['discounts']['quantity_discounts'] as $discount) { ?>
								<span class="discount-chip">
									от <?php echo (int)$discount['quantity']; ?> шт — <?php echo renderPrice($discount['price']); ?>
								</span>
							<?php } ?>
						</div>
					</div>
					<?php } else { ?>
					<div class="price-group">
						<div class="price-group-title">Скидки от количества</div>
						<div class="price-empty-note">Не рассчитаны</div>
					</div>
					<?php } ?>

<?php
$_hasWholesale = !empty($details['discounts']['wholesale_price']);
$_hasDealer    = !empty($details['discounts']['dealer_price']);
?>
					<div class="price-group">
						<div class="price-group-title">Спеццены</div>
						<?php if ($_hasWholesale || $_hasDealer) { ?>
						<div class="price-sub-row">
							<?php if ($_hasWholesale) { ?>
							<div class="price-sub-item">
								<div class="price-label">Оптовая</div>
								<div class="price-value"><?php echo renderPrice($details['discounts']['wholesale_price']); ?></div>
							</div>
							<?php } ?>
							<?php if ($_hasDealer) { ?>
							<div class="price-sub-item">
								<div class="price-label">Дилерская</div>
								<div class="price-value"><?php echo renderPrice($details['discounts']['dealer_price']); ?></div>
							</div>
							<?php } ?>
						</div>
						<?php } else { ?>
						<div class="price-empty-note">Не рассчитаны</div>
						<?php } ?>
					</div>

				</div>
			</div>

			<div class="section">
				<h3>Остатки</h3>
				<?php if ((int)$details['quantity'] > 0 || (int)$details['real_stock'] > 0 || (int)$details['virtual_stock'] > 0) { ?>
				<div class="stock-card">
					<div class="stock-grid">
						<div class="stock-item">
							<div class="stock-label">На сайте</div>
							<div class="stock-value"><?php echo (int)$details['quantity']; ?></div>
						</div>

						<div class="stock-item">
							<div class="stock-label">Реальный</div>
							<div class="stock-value"><?php echo (int)$details['real_stock']; ?></div>
						</div>

						<div class="stock-item">
							<div class="stock-label">Виртуальный</div>
							<div class="stock-value"><?php echo (int)$details['virtual_stock']; ?></div>
						</div>
					</div>
				</div>
				<?php } else { ?>
				<div class="stock-card">
					<div class="price-empty-note">Нет</div>
				</div>
				<?php } ?>
			</div>

                <!-- ── Контент: site × language ───────────────────────────── -->
                <div class="section section-collapsible">
                    <h3>Контент <?php if (!empty($details['seo'])): ?><button type="button" class="btn btn-xs" id="btnAiContentGen" style="vertical-align:middle;margin-left:6px" title="Генерувати контент через AI">&#9889; AI</button><?php endif; ?></h3>
                    <div class="section-body">
                    <?php if (!empty($details['seo'])): ?>
                        <!-- site tabs -->
                        <div class="seo-site-tabs">
                        <?php foreach ($details['seo'] as $si => $site): ?>
                            <button type="button"
                                class="seo-site-tab<?php echo $si === 0 ? ' active' : ''; ?>"
                                data-site="cnt-<?php echo (int)$site['site_id']; ?>">
                                <?php echo ViewHelper::h($site['name']); ?>
                            </button>
                        <?php endforeach; ?>
                        </div>
                        <!-- site panes -->
                        <?php foreach ($details['seo'] as $si => $site):
                            $sid = (int)$site['site_id'];
                        ?>
                        <div class="seo-site-pane<?php echo $si === 0 ? ' active' : ''; ?>"
                             data-site="cnt-<?php echo $sid; ?>">
                            <!-- lang tabs -->
                            <div class="tabs" style="margin-bottom:10px;">
                            <?php foreach (array(2=>'UK', 1=>'RU') as $lid => $lname): ?>
                                <button type="button"
                                    class="tab-btn<?php echo $lid === 2 ? ' active' : ''; ?>"
                                    data-tab="cnt-<?php echo $sid; ?>-<?php echo $lid; ?>">
                                    <?php echo $lname; ?>
                                </button>
                            <?php endforeach; ?>
                            </div>
                            <!-- lang panes -->
                            <?php foreach (array(2, 1) as $lid):
                                $s    = isset($site['langs'][$lid]) ? $site['langs'][$lid] : array();
                                $slug = isset($s['seo_url']) ? $s['seo_url'] : '';
                                $prodUrl = $slug !== '' ? rtrim($site['url'], '/') . '/' . $slug : '';
                            ?>
                            <div id="cnt-<?php echo $sid; ?>-<?php echo $lid; ?>"
                                 class="tab-pane<?php echo $lid === 2 ? ' active' : ''; ?>">
                                <div class="content-card">
                                    <div class="content-field">
                                        <div class="content-label">Назва</div>
                                        <div class="content-inline-value"><?php echo renderValue(isset($s['name']) ? $s['name'] : ''); ?></div>
                                    </div>
                                    <div class="content-field">
                                        <div class="content-label">
                                            Повний опис
                                            <span class="desc-toggle-link" id="cnt-desc-lnk-<?php echo $sid; ?>-<?php echo $lid; ?>" onclick="toggleProdDesc(<?php echo $sid; ?>,<?php echo $lid; ?>)">редагувати</span>
                                        </div>
                                        <div class="desc-html-box" id="cnt-desc-<?php echo $sid; ?>-<?php echo $lid; ?>" onclick="toggleProdDesc(<?php echo $sid; ?>,<?php echo $lid; ?>)"><?php
                                            $rawDesc = isset($s['description']) ? trim($s['description']) : '';
                                            echo $rawDesc !== '' ? $rawDesc : '<span style="color:var(--text-faint)">—</span>';
                                        ?></div>
                                        <div id="cnt-desc-edit-<?php echo $sid; ?>-<?php echo $lid; ?>" style="display:none">
                                            <textarea class="desc-edit-ta" id="cnt-desc-ta-<?php echo $sid; ?>-<?php echo $lid; ?>"><?php echo ViewHelper::h(isset($s['description']) ? $s['description'] : ''); ?></textarea>
                                            <div class="desc-save-row">
                                                <button class="btn btn-sm btn-primary" onclick="saveProdDesc(<?php echo $sid; ?>,<?php echo $lid; ?>)">Зберегти</button>
                                                <button class="btn btn-sm btn-ghost" onclick="toggleProdDesc(<?php echo $sid; ?>,<?php echo $lid; ?>)">Скасувати</button>
                                                <span class="desc-save-status" id="cnt-desc-st-<?php echo $sid; ?>-<?php echo $lid; ?>"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="content-field">
                                        <div class="content-label">Короткий опис</div>
                                        <div class="scroll-box"><?php echo renderValue(isset($s['short_description']) ? $s['short_description'] : ''); ?></div>
                                    </div>
                                    <div class="seo-card">
                                        <?php if ($prodUrl !== ''): ?>
                                        <div style="margin-bottom:10px;">
                                            <a href="<?php echo ViewHelper::h($prodUrl); ?>" target="_blank" rel="noopener" class="seo-site-link">
                                                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.5 2H3a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1V9.5M10 2h4m0 0v4m0-4L7 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                <?php echo ViewHelper::h(isset($site['badge']) ? strtoupper($site['badge']) : strtoupper($site['code'])); ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                        <div class="seo-row">
                                            <div class="seo-label">SEO URL</div>
                                            <div class="seo-value"><?php echo renderValue($slug); ?></div>
                                        </div>
                                        <div class="seo-row">
                                            <div class="seo-label">H1</div>
                                            <div class="seo-value"><?php echo renderValue(isset($s['seo_h1']) ? $s['seo_h1'] : ''); ?></div>
                                        </div>
                                        <div class="seo-row">
                                            <div class="seo-label">Meta title</div>
                                            <div class="seo-value" id="cnt-mt-<?php echo $sid; ?>-<?php echo $lid; ?>"><?php echo renderValue(isset($s['meta_title']) ? $s['meta_title'] : ''); ?></div>
                                        </div>
                                        <div class="seo-row">
                                            <div class="seo-label">Meta description</div>
                                            <div class="seo-value" id="cnt-md-<?php echo $sid; ?>-<?php echo $lid; ?>"><?php echo renderValue(isset($s['meta_description']) ? $s['meta_description'] : ''); ?></div>
                                        </div>
                                        <?php if (!empty($s['tag'])): ?>
                                        <div class="seo-row">
                                            <div class="seo-label">Теги</div>
                                            <div class="seo-value"><?php echo renderValue($s['tag']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div><!-- /section-body Контент -->
                </div><!-- /section Контент -->
			<div class="section section-collapsible" id="specsSection">
				<h3>Характеристики</h3>
				<div class="section-body">
				<div class="specs-form">
					<div class="specs-form-row">
						<span class="specs-form-label">Вага</span>
						<input type="number" id="specWeight" min="0" step="0.001"
							value="<?php echo (float)$details['weight'] > 0 ? ViewHelper::h(rtrim(rtrim(number_format((float)$details['weight'], 3, '.', ''), '0'), '.')) : ''; ?>"
							placeholder="0">
						<select id="specWeightClass">
							<?php foreach ($weightClasses as $wc): ?>
							<option value="<?php echo (int)$wc['weight_class_id']; ?>"<?php echo ((int)$wc['weight_class_id'] === (int)$details['weight_class_id']) ? ' selected' : ''; ?>><?php echo ViewHelper::h($wc['title']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="specs-form-row">
						<span class="specs-form-label">Д&nbsp;&times;&nbsp;Ш&nbsp;&times;&nbsp;В</span>
						<input type="number" id="specLength" min="0" step="0.01"
							value="<?php echo (float)$details['length'] > 0 ? ViewHelper::h(rtrim(rtrim(number_format((float)$details['length'], 2, '.', ''), '0'), '.')) : ''; ?>"
							placeholder="Д">
						<span class="specs-sep">&times;</span>
						<input type="number" id="specWidth" min="0" step="0.01"
							value="<?php echo (float)$details['width'] > 0 ? ViewHelper::h(rtrim(rtrim(number_format((float)$details['width'], 2, '.', ''), '0'), '.')) : ''; ?>"
							placeholder="Ш">
						<span class="specs-sep">&times;</span>
						<input type="number" id="specHeight" min="0" step="0.01"
							value="<?php echo (float)$details['height'] > 0 ? ViewHelper::h(rtrim(rtrim(number_format((float)$details['height'], 2, '.', ''), '0'), '.')) : ''; ?>"
							placeholder="В">
						<select id="specLengthClass">
							<?php foreach ($lengthClasses as $lc): ?>
							<option value="<?php echo (int)$lc['length_class_id']; ?>"<?php echo ((int)$lc['length_class_id'] === (int)$details['length_class_id']) ? ' selected' : ''; ?>><?php echo ViewHelper::h($lc['title']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<button class="btn btn-sm btn-primary" onclick="saveSpecs()">Зберегти</button>
					</div>
				</div>
				</div><!-- /section-body Характеристики -->
			</div><!-- /section Характеристики -->

			<div class="section section-collapsible" id="attrsSection">
				<h3>Атрибути</h3>
				<div class="section-body">
					<div id="attrsLoading" style="color:var(--text-muted);font-size:13px;padding:4px 0">Завантаження…</div>
					<div id="attrsContent" style="display:none"></div>
				</div>
			</div><!-- /section Атрибути -->
                <div class="section section-collapsible">
                    <h3>Ссылки и интеграции</h3>
                    <div class="section-body">
                    <div class="info-grid">
                        <div class="k">id_off</div>
                        <div class="v"><?php echo (int)$details['id_off']; ?></div>

                        <div class="k">product_id</div>
                        <div class="v"><?php echo (int)$details['product_id']; ?></div>

                        <div style="display:none"><span class="k">id_ms</span><span class="v"><?php echo renderValue($details['id_ms']); ?></span></div>

                        <div class="k">id_mf</div>
                        <div class="v"><?php echo renderValue($details['id_mf']); ?></div>

                        <div class="k">ТН ВЭД</div>
                        <div class="v"><?php echo renderValue($details['tnved']); ?></div>

                        <div class="k">Ед. изм.</div>
                        <div class="v"><?php echo renderValue($details['unit']); ?></div>

                        <div class="k">Упаковки</div>
                        <div class="v"><?php echo renderValue($details['packs']); ?></div>

                    </div><!-- /info-grid -->
                    </div><!-- /section-body Ссылки -->
                </div><!-- /section Ссылки -->
						<?php if (!empty($details['ean'])) { ?>
							<div class="ean-barcode-wrapper">
								<svg id="eanBarcode" data-ean="<?php echo ViewHelper::h($details['ean']); ?>"></svg>
							</div>
						<?php } ?>
		    </div>
            <?php } else { ?>
                <h2>Карточка товара</h2>
                <div class="mini-note">Товар не выбран.</div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="modal" id="imageModal">
    <div class="modal-content">
        <button type="button" class="modal-close" id="imageModalClose">&#215;</button>
        <img src="" alt="" class="modal-image" id="imageModalImg">
    </div>
</div>
<div class="modal" id="priceListModal">
	<div class="modal-content" style="width: 100%; max-width: 760px;">
		<div class="card" style="margin:0; padding:20px;">
			<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;">
				<h3 style="margin:0;">Прайс-лист по выбранным товарам</h3>
				<button type="button" class="modal-close" id="priceListModalClose" style="position:static;">&#215;</button>
			</div>

			<div class="mini-note" style="margin-bottom:10px;">
				Готовый текст можно скопировать и отправить клиенту.
			</div>

			<textarea id="priceListText" style="width:100%;min-height:320px;box-sizing:border-box;padding:12px;border:1px solid #d9e0ea;border-radius:10px;font:14px/1.5 Arial,sans-serif;resize:vertical;"></textarea>

			<div class="btn-row" style="margin-top:12px;">
				<button type="button" class="btn btn-primary" id="copyPriceList">Скопировать</button>
				<button type="button" class="btn" id="closePriceList">Закрыть</button>
			</div>
		</div>
	</div>
</div>

<script>

    var modal = document.getElementById('imageModal');
    var modalImg = document.getElementById('imageModalImg');
    var modalClose = document.getElementById('imageModalClose');
    var photoTriggers = document.querySelectorAll('.js-open-image');

	var rows = document.querySelectorAll('.js-row-click');
	var selectedRow = document.querySelector('.selected-row');

	var index = -1;

rows.forEach(function(r,i){
    if(r === selectedRow){
        index = i;
    }
});

document.getElementById('navPrev') && document.getElementById('navPrev').addEventListener('click',function(){

    if(index <= 0) return;

    var prev = rows[index-1].getAttribute('data-url');

    if(prev){
        window.location = prev;
    }

});

document.getElementById('navNext') && document.getElementById('navNext').addEventListener('click',function(){

    if(index === -1 || index >= rows.length-1) return;

    var next = rows[index+1].getAttribute('data-url');

    if(next){
        window.location = next;
    }

});

(function () {
	document.addEventListener('click', function (e) {

		if (e.target.classList.contains('action-menu-toggle')) {
			e.preventDefault();

			var menu = e.target.closest('.action-menu');

			document.querySelectorAll('.action-menu').forEach(function (m) {
				if (m !== menu) {
					m.classList.remove('open');
				}
			});

			menu.classList.toggle('open');
			return;
		}

		document.querySelectorAll('.action-menu').forEach(function (m) {
			if (!m.contains(e.target)) {
				m.classList.remove('open');
			}
		});

	});

	document.querySelectorAll('.js-row-click').forEach(function(row){

			row.addEventListener('click', function(e){

			if (e.target.closest('a') || e.target.closest('button') || e.target.closest('input')) {
				return;
			}

				var url = this.getAttribute('data-url');

				if (url) {
					window.location = url;
				}

			});

		});


    function openModal(src) {
        if (!src) {
            return;
        }
        modalImg.src = src;
        modal.classList.add('open');
    }

    function closeModal() {
        modal.classList.remove('open');
        modalImg.src = '';
    }

    for (var i = 0; i < photoTriggers.length; i++) {
        photoTriggers[i].addEventListener('click', function (e) {
            e.preventDefault();
            openModal(this.getAttribute('data-image') || this.getAttribute('src'));
        });
    }

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }


    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Language tab-btn — scoped to parent .tabs container
    var tabButtons = document.querySelectorAll('.tab-btn');
    for (var j = 0; j < tabButtons.length; j++) {
        tabButtons[j].addEventListener('click', function () {
            var target  = this.getAttribute('data-tab');
            var container = this.parentNode; // .tabs
            var btns  = container.querySelectorAll('.tab-btn');
            for (var k = 0; k < btns.length; k++) btns[k].classList.remove('active');
            this.classList.add('active');
            var pane = document.getElementById(target);
            if (pane) {
                // deactivate siblings (same parent as pane)
                var siblings = pane.parentNode.querySelectorAll('.tab-pane');
                for (var p = 0; p < siblings.length; p++) siblings[p].classList.remove('active');
                pane.classList.add('active');
            }
        });
    }

    // Site tabs (Контент section)
    var seoSiteTabs = document.querySelectorAll('.seo-site-tab');
    for (var s = 0; s < seoSiteTabs.length; s++) {
        seoSiteTabs[s].addEventListener('click', function () {
            var sid = this.getAttribute('data-site');
            var container = this.closest('.section-body');
            container.querySelectorAll('.seo-site-tab').forEach(function(t){ t.classList.remove('active'); });
            container.querySelectorAll('.seo-site-pane').forEach(function(p){ p.classList.remove('active'); });
            this.classList.add('active');
            var pane = container.querySelector('.seo-site-pane[data-site="' + sid + '"]');
            if (pane) pane.classList.add('active');
        });
    }

var STORAGE_KEY = 'papir_catalog_selected_products';

var selectAllRows = document.getElementById('selectAllRows');
var rowSelectors = document.querySelectorAll('.row-selector');
var selectedCount = document.getElementById('selectedCount');

var bulkCopyIds = document.getElementById('bulkCopyIds');
var bulkPriceList = document.getElementById('bulkPriceList');

var priceListModal = document.getElementById('priceListModal');
var priceListModalClose = document.getElementById('priceListModalClose');
var closePriceList = document.getElementById('closePriceList');
var copyPriceList = document.getElementById('copyPriceList');
var priceListText = document.getElementById('priceListText');

function loadSelectedProducts() {
    try {
        var raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        return {};
    }
}

function saveSelectedProducts() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(selectedProducts));
}

var selectedProducts = loadSelectedProducts();

function getSelectedIds() {
    return Object.keys(selectedProducts);
}

function getRowProductDataByCheckbox(checkbox) {
    var row = checkbox.closest('tr');
    if (!row) {
        return null;
    }

    var id = row.getAttribute('data-product-id') || '';
    if (!id) {
        return null;
    }

    return {
        id: id,
        article: row.getAttribute('data-product-article') || '',
        name: row.getAttribute('data-product-name') || '',
        price: row.getAttribute('data-product-price') || '',
        action_price: row.getAttribute('data-product-action-price') || ''
    };
}

function addSelectedProduct(product) {
    if (!product || !product.id) {
        return;
    }

    selectedProducts[product.id] = product;
    saveSelectedProducts();
}

function removeSelectedProduct(id) {
    if (!id) {
        return;
    }

    delete selectedProducts[id];
    saveSelectedProducts();
}

function syncCheckboxesFromStorage() {
    rowSelectors.forEach(function (checkbox) {
        checkbox.checked = !!selectedProducts[checkbox.value];
    });
}

function refreshSelectedCounter() {
    var ids = getSelectedIds();

    if (selectedCount) {
        selectedCount.textContent = ids.length;
        if (ids.length > 0) {
            selectedCount.classList.add('has-sel');
        } else {
            selectedCount.classList.remove('has-sel');
        }
    }

    if (selectAllRows) {
        var total = rowSelectors.length;
        var checked = 0;

        rowSelectors.forEach(function (cb) {
            if (cb.checked) {
                checked++;
            }
        });

        selectAllRows.checked = (total > 0 && checked === total);
        selectAllRows.indeterminate = (checked > 0 && checked < total);
    }
}

function openPriceListModal(text) {
    if (!priceListModal || !priceListText) {
        return;
    }

    priceListText.value = text;
    priceListModal.classList.add('open');
}

function closePriceListModal() {
    if (!priceListModal) {
        return;
    }

    priceListModal.classList.remove('open');
}

function formatPrice(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    var num = parseFloat(value);
    if (isNaN(num)) {
        return value;
    }

    return num.toFixed(2).replace('.', ',');
}

function buildPriceListText() {
    var ids = getSelectedIds();

    if (!ids.length) {
        return '';
    }

    var lines = ['Прайс по выбранным товарам:', ''];

    ids.forEach(function (id, index) {
        var item = selectedProducts[id];
        if (!item) {
            return;
        }

        var effectivePrice = item.action_price !== '' ? item.action_price : item.price_sale;
        var parts = [];

        parts.push((index + 1) + '.');

        if (item.article) {
            parts.push('[' + item.article + ']');
        }

        parts.push(item.name || ('Товар #' + item.id));
        parts.push('—');

        if (effectivePrice !== '') {
            parts.push(formatPrice(effectivePrice) + ' грн');
        } else {
            parts.push('цена не указана');
        }

        if (item.action_price !== '') {
            parts.push('(акция)');
        }

        lines.push(parts.join(' '));
    });

    lines.push('');
    lines.push('Цены актуальны на момент отправки.');

    return lines.join('\n');
}

rowSelectors.forEach(function (checkbox) {
    checkbox.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    checkbox.addEventListener('change', function () {
        var product = getRowProductDataByCheckbox(this);

        if (this.checked) {
            addSelectedProduct(product);
        } else {
            removeSelectedProduct(this.value);
        }

        refreshSelectedCounter();
    });
});

if (selectAllRows) {
    selectAllRows.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    selectAllRows.addEventListener('change', function () {
        var checked = this.checked;

        rowSelectors.forEach(function (checkbox) {
            checkbox.checked = checked;

            var product = getRowProductDataByCheckbox(checkbox);

            if (checked) {
                addSelectedProduct(product);
            } else {
                removeSelectedProduct(checkbox.value);
            }
        });

        refreshSelectedCounter();
    });
}



if (bulkCopyIds) {
    bulkCopyIds.addEventListener('click', function () {
        var ids = getSelectedIds();

        if (!ids.length) {
            alert('Сначала выбери товары.');
            return;
        }

        navigator.clipboard.writeText(ids.join(',')).then(function () {
            showCopyToast('ID скопированы');
        }).catch(function () {
            alert('Не удалось скопировать ID');
        });
    });
}



function buildPriceListTextFromServerItems(items) {
    if (!items || !items.length) {
        return '';
    }

    var blocks = [];

    items.forEach(function (item) {
        var lines = [];

        lines.push(item.name || ('Товар #' + item.id));

        var url = item.url_mff || item.url_off || null;
        if (url) {
            lines.push(url);
        }

        var retail = item.action_price !== null ? item.action_price : item.price_sale;
        if (retail !== null) {
            var priceStr = formatPrice(retail) + ' грн';
            if (item.action_price !== null && item.price_sale !== null) {
                priceStr += ' (знижка з ' + formatPrice(item.price_sale) + ' грн)';
            }
            lines.push('Ціна: ' + priceStr);
        }

        if (item.quantity_discounts && item.quantity_discounts.length) {
            item.quantity_discounts.forEach(function (discount) {
                lines.push('від ' + discount.quantity + ' шт — ' + formatPrice(discount.price) + ' грн');
            });
        }

        blocks.push(lines.join('\n'));
    });

    return blocks.join('\n\n');
}

if (bulkPriceList) {
    bulkPriceList.addEventListener('click', function () {
        var ids = getSelectedIds();

        if (!ids.length) {
            alert('Сначала выбери товары.');
            return;
        }

        fetch('/catalog-pricelist', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: 'product_ids=' + encodeURIComponent(ids.join(','))
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(function (text) {
            console.log('Price list raw response:', text);

            var data = JSON.parse(text);

            if (!data || !data.items || !data.items.length) {
                alert('Не удалось получить данные для прайс-листа.');
                return;
            }

            var textResult = buildPriceListTextFromServerItems(data.items);
            openPriceListModal(textResult);
        })
        .catch(function (err) {
            console.log('Price list error:', err);
            alert('Ошибка при загрузке прайс-листа: ' + err.message);
        });
    });
}

if (copyPriceList) {
    copyPriceList.addEventListener('click', function () {
        if (!priceListText || !priceListText.value) {
            return;
        }

        navigator.clipboard.writeText(priceListText.value).then(function () {
            showCopyToast('Прайс-лист скопирован');
        }).catch(function () {
            alert('Не удалось скопировать прайс-лист');
        });
    });
}

if (priceListModalClose) {
    priceListModalClose.addEventListener('click', closePriceListModal);
}

if (closePriceList) {
    closePriceList.addEventListener('click', closePriceListModal);
}

if (priceListModal) {
    priceListModal.addEventListener('click', function (e) {
        if (e.target === priceListModal) {
            closePriceListModal();
        }
    });
}

var bulkClearSelection = document.getElementById('bulkClearSelection');
if (bulkClearSelection) {
    bulkClearSelection.addEventListener('click', function () {
        selectedProducts = {};
        saveSelectedProducts();
        syncCheckboxesFromStorage();
        refreshSelectedCounter();
    });
}

syncCheckboxesFromStorage();
refreshSelectedCounter();

})();

var filterInput = document.getElementById('filter');
var searchForm  = document.getElementById('search') ? document.getElementById('search').closest('form') : null;

if (filterInput && searchForm) {
    filterInput.addEventListener('change', function () {
        var pageInput = searchForm.querySelector('input[name="page"]');
        if (pageInput) pageInput.value = 1;
        searchForm.submit();
    });
}


</script>

<!-- Manufacturer modal -->
<div class="mfr-modal-overlay" id="mfrModalOverlay">
    <div class="mfr-modal" id="mfrModal">
        <div class="mfr-modal-head">
            <h4>Виробник</h4>
            <button class="mfr-modal-close" id="mfrModalClose" type="button">&#10005;</button>
        </div>
        <div class="mfr-modal-search-wrap">
            <input class="mfr-modal-search" id="mfrSearch" type="text" placeholder="Пошук..." autocomplete="off">
        </div>
        <div class="mfr-modal-list" id="mfrList"></div>
        <div class="mfr-modal-footer">
            <button class="mfr-modal-cancel" id="mfrCancel" type="button">Скасувати</button>
            <button class="mfr-modal-save" id="mfrSave" type="button">Зберегти</button>
        </div>
    </div>
</div>

<!-- Category modal -->
<div class="mfr-modal-overlay" id="catModalOverlay">
    <div class="mfr-modal" id="catModal" style="width:440px;max-width:96vw">
        <div class="mfr-modal-head">
            <h4>Категорія</h4>
            <button class="mfr-modal-close" id="catModalClose" type="button">&#10005;</button>
        </div>
        <div id="catTreeContainer" style="height:540px;overflow:hidden;display:flex;flex-direction:column"></div>
        <div class="mfr-modal-footer">
            <a id="catManageLink" href="/categories" target="_blank"
               style="font-size:12px;color:#4a90d9;text-decoration:none;margin-right:auto;align-self:center">
                Управляти →
            </a>
            <button class="mfr-modal-cancel" id="catCancel" type="button">Скасувати</button>
            <button class="mfr-modal-save"   id="catSave"   type="button">Зберегти</button>
        </div>
    </div>
</div>

<script src="/modules/shared/category-tree.js?v=<?php echo filemtime(__DIR__ . '/../../shared/category-tree.js'); ?>"></script>
<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script src="/papir/assets/js/JsBarcode.all.min.js"></script>

<script>
	var eanBarcode = document.getElementById('eanBarcode');

		if (eanBarcode && typeof JsBarcode !== 'undefined') {

			var eanValue = eanBarcode.getAttribute('data-ean');

			if (eanValue) {

				try {
					JsBarcode(eanBarcode, eanValue, {
						format: "EAN13",
						displayValue: true,
						fontSize: 14,
						height: 60,
						margin: 0,
						width: 2
					});
				} catch (err) {
					console.log('Barcode render error:', err);
				}

				// копирование по клику
				eanBarcode.addEventListener('click', function(){

					navigator.clipboard.writeText(eanValue).then(function(){

						showCopyToast("EAN скопирован");

					}).catch(function(err){

						console.log("Copy failed:", err);

					});

				});

			}
	}
	function showCopyToast(text){

		var toast = document.createElement('div');

		toast.className = 'copy-toast';
		toast.innerText = text;

		document.body.appendChild(toast);

		setTimeout(function(){
			toast.classList.add('show');
		},20);

		setTimeout(function(){
			toast.classList.remove('show');

			setTimeout(function(){
				toast.remove();
			},250);

		},1500);

	}

// Manufacturer modal
(function() {
    var overlay    = document.getElementById('mfrModalOverlay');
    var editBtn    = document.getElementById('mfrEditBtn');
    var nameSpan   = document.getElementById('mfrName');
    var closeBtn   = document.getElementById('mfrModalClose');
    var cancelBtn  = document.getElementById('mfrCancel');
    var saveBtn    = document.getElementById('mfrSave');
    var searchEl   = document.getElementById('mfrSearch');
    var listEl     = document.getElementById('mfrList');

    if (!overlay || !editBtn) return;

    var allManufacturers = null;
    var selectedId = null;
    var productId  = 0;

    function openModal() {
        productId  = parseInt(editBtn.getAttribute('data-product-id'), 10) || 0;
        selectedId = parseInt(editBtn.getAttribute('data-manufacturer-id'), 10) || null;
        searchEl.value = '';
        overlay.classList.add('open');
        searchEl.focus();

        if (allManufacturers === null) {
            listEl.innerHTML = '<div class="mfr-empty-msg">Завантаження...</div>';
            fetch('/catalog/api/get_manufacturers')
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        allManufacturers = d.manufacturers;
                        renderList('');
                    } else {
                        listEl.innerHTML = '<div class="mfr-empty-msg">Помилка завантаження</div>';
                    }
                })
                .catch(function() {
                    listEl.innerHTML = '<div class="mfr-empty-msg">Помилка мережі</div>';
                });
        } else {
            renderList('');
        }
    }

    function closeModal() {
        overlay.classList.remove('open');
    }

    function renderList(query) {
        var tokens = query.toLowerCase().trim().split(/\s+/).filter(Boolean);
        var items = allManufacturers ? allManufacturers.filter(function(m) {
            if (!tokens.length) return true;
            var name = m.name.toLowerCase();
            return tokens.every(function(t) { return name.indexOf(t) !== -1; });
        }) : [];

        var html = '<div class="mfr-modal-item-none" data-id="0">— Не вказано</div>';
        items.forEach(function(m) {
            var id = parseInt(m.manufacturer_id, 10);
            var cls = 'mfr-modal-item' + (id === selectedId ? ' selected' : '');
            html += '<div class="' + cls + '" data-id="' + id + '">' + escHtml(m.name) + '</div>';
        });
        if (!items.length && q) {
            html += '<div class="mfr-empty-msg">Нічого не знайдено</div>';
        }
        listEl.innerHTML = html;

        listEl.querySelectorAll('[data-id]').forEach(function(el) {
            el.addEventListener('click', function() {
                selectedId = parseInt(this.getAttribute('data-id'), 10) || null;
                listEl.querySelectorAll('.selected').forEach(function(x) { x.classList.remove('selected'); });
                if (selectedId) this.classList.add('selected');
            });
        });

        // scroll selected into view
        var sel = listEl.querySelector('.selected');
        if (sel) { setTimeout(function() { sel.scrollIntoView({block:'nearest'}); }, 50); }
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function saveManufacturer() {
        var body = 'product_id=' + productId + '&manufacturer_id=' + (selectedId || 0);
        saveBtn.disabled = true;
        fetch('/catalog/api/save_manufacturer', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            saveBtn.disabled = false;
            if (d.ok) {
                // Update UI
                nameSpan.textContent = d.manufacturer_name || '—';
                editBtn.setAttribute('data-manufacturer-id', d.manufacturer_id || 0);
                closeModal();
                showCopyToast('Виробника збережено');
            } else {
                alert('Помилка: ' + (d.error || 'невідома'));
            }
        })
        .catch(function() {
            saveBtn.disabled = false;
            alert('Помилка мережі');
        });
    }

    editBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    saveBtn.addEventListener('click', saveManufacturer);

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
    });

    var _searchTimer = null;
    searchEl.addEventListener('input', function() {
        var val = this.value;
        clearTimeout(_searchTimer);
        _searchTimer = setTimeout(function() { renderList(val); }, 200);
    });
})();

// Collapsible sections
document.querySelectorAll('.section-collapsible > h3').forEach(function(h3) {
    h3.addEventListener('click', function() {
        var section = this.closest('.section-collapsible');
        section.classList.toggle('open');
        if (section.id === 'attrsSection' && section.classList.contains('open')) {
            initAttrs();
        }
    });
});

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ═══════════════════════════════════════════════════════════════════════
// Specs (weight / dimensions) save
// ═══════════════════════════════════════════════════════════════════════
<?php if ($selected > 0): ?>
function saveSpecs() {
    var productId   = <?php echo (int)$selected; ?>;
    var weight      = parseFloat(document.getElementById('specWeight').value)  || 0;
    var weightClass = parseInt(document.getElementById('specWeightClass').value, 10);
    var length      = parseFloat(document.getElementById('specLength').value)  || 0;
    var width       = parseFloat(document.getElementById('specWidth').value)   || 0;
    var height      = parseFloat(document.getElementById('specHeight').value)  || 0;
    var lengthClass = parseInt(document.getElementById('specLengthClass').value, 10);

    fetch('/catalog/api/save_product_specs', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + productId
            + '&weight=' + weight + '&weight_class_id=' + weightClass
            + '&length=' + length + '&width=' + width + '&height=' + height
            + '&length_class_id=' + lengthClass
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) showToast('Характеристики збережено');
        else showToast('Помилка: ' + (d.error || '?'));
    })
    .catch(function() { showToast('Помилка мережі'); });
}

// ═══════════════════════════════════════════════════════════════════════
// Attributes panel
// ═══════════════════════════════════════════════════════════════════════
var ATTRS = { loaded: false, data: null, activeSite: 0, activeLang: 0 };
var ATTRS_PRODUCT_ID = <?php echo (int)$selected; ?>;

function initAttrs() {
    if (ATTRS.loaded) return;
    fetch('/catalog/api/get_product_attributes?product_id=' + ATTRS_PRODUCT_ID)
    .then(function(r) { return r.json(); })
    .then(function(d) {
        document.getElementById('attrsLoading').style.display = 'none';
        if (!d.ok || !d.sites || !d.sites.length) {
            document.getElementById('attrsContent').innerHTML = '<span style="color:var(--text-muted);font-size:13px">Атрибути відсутні</span>';
            document.getElementById('attrsContent').style.display = '';
            return;
        }
        ATTRS.loaded     = true;
        ATTRS.data       = d;
        ATTRS.activeSite = d.sites[0].site_id;
        ATTRS.activeLang = d.sites[0].languages[0].language_id;
        renderAttrPanel();
        document.getElementById('attrsContent').style.display = '';
    })
    .catch(function() {
        document.getElementById('attrsLoading').textContent = 'Помилка завантаження';
    });
}

function renderAttrPanel() {
    var content = document.getElementById('attrsContent');
    var html = '';

    // Site tabs
    html += '<div class="attr-site-tabs">';
    ATTRS.data.sites.forEach(function(site) {
        var cls = 'attr-site-tab' + (site.site_id === ATTRS.activeSite ? ' active' : '');
        html += '<button class="' + cls + '" onclick="attrSetSite(' + site.site_id + ')">' + escHtml(site.name) + '</button>';
    });
    html += '</div>';

    // Find active site data
    var activeSiteData = null;
    ATTRS.data.sites.forEach(function(s) { if (s.site_id === ATTRS.activeSite) activeSiteData = s; });
    if (!activeSiteData) { content.innerHTML = html; return; }

    // Language tabs
    html += '<div class="attr-lang-tabs">';
    activeSiteData.languages.forEach(function(lang) {
        var cls = 'attr-lang-tab' + (lang.language_id === ATTRS.activeLang ? ' active' : '');
        html += '<button class="' + cls + '" onclick="attrSetLang(' + lang.language_id + ')">' + escHtml(lang.label) + '</button>';
    });
    html += '</div>';

    // Find active language data
    var activeLangData = null;
    activeSiteData.languages.forEach(function(l) { if (l.language_id === ATTRS.activeLang) activeLangData = l; });

    // Attribute table
    if (activeLangData && activeLangData.values.length > 0) {
        html += '<div style="overflow-x:auto"><table class="crm-table"><thead><tr>'
            + '<th>Назва</th><th>Значення</th><th style="width:36px"></th>'
            + '</tr></thead><tbody>';
        activeLangData.values.forEach(function(row) {
            html += '<tr>'
                + '<td style="font-size:13px;white-space:nowrap;padding:4px 8px">' + escHtml(row.attribute_name) + '</td>'
                + '<td style="padding:2px 4px"><input type="text" class="attr-value-input"'
                + ' data-attr-id="' + row.attribute_id + '"'
                + ' data-lang-id="' + activeLangData.language_id + '"'
                + ' value="' + escHtml(row.text) + '"'
                + ' onblur="attrSaveOnBlur(this)"></td>'
                + '<td style="padding:2px 4px;text-align:center">'
                + '<button class="btn btn-xs btn-ghost" style="color:var(--text-red)" title="Видалити" onclick="attrDelete(' + row.attribute_id + ')">&times;</button>'
                + '</td></tr>';
        });
        html += '</tbody></table></div>';
    } else {
        html += '<div style="color:var(--text-muted);font-size:13px;padding:8px 0">Немає значень для цієї мови</div>';
    }

    // Add new attribute row
    var availableForSite = activeSiteData.available_attrs || [];
    var presentIds = {};
    if (activeLangData) activeLangData.values.forEach(function(v) { presentIds[v.attribute_id] = true; });
    var langId4id  = activeLangData ? activeLangData.language_id : 0;
    var selectId   = 'attrAddSel-' + ATTRS.activeSite + '-' + langId4id;
    var searchId   = 'attrAddSrch-' + ATTRS.activeSite + '-' + langId4id;

    html += '<div class="attr-add-row">';
    html += '<input type="text" class="attr-add-search" id="' + searchId
        + '" placeholder="Пошук\u2026" oninput="attrFilterList(this,\'' + selectId + '\')">';
    html += '<select class="attr-add-list" id="' + selectId + '">'
        + '<option value="">\u2014 вибрати атрибут \u2014</option>';
    availableForSite.forEach(function(a) {
        if (!presentIds[a.attribute_id]) {
            html += '<option value="' + a.attribute_id + '">' + escHtml(a.attribute_name) + '</option>';
        }
    });
    html += '</select>';
    html += '<button class="btn btn-sm" onclick="attrAdd(\'' + selectId + '\')">Додати</button>';
    html += '</div>';

    content.innerHTML = html;
}

function attrSetSite(siteId) {
    ATTRS.activeSite = siteId;
    ATTRS.data.sites.forEach(function(s) {
        if (s.site_id === siteId && s.languages.length) {
            ATTRS.activeLang = s.languages[0].language_id;
        }
    });
    renderAttrPanel();
}

function attrSetLang(langId) {
    ATTRS.activeLang = langId;
    renderAttrPanel();
}

function attrFilterList(input, selectId) {
    var val = input.value.toLowerCase();
    var sel = document.getElementById(selectId);
    if (!sel) return;
    for (var i = 0; i < sel.options.length; i++) {
        var opt = sel.options[i];
        if (!opt.value) { opt.style.display = ''; continue; }
        opt.style.display = opt.text.toLowerCase().indexOf(val) >= 0 ? '' : 'none';
    }
}

function attrSaveOnBlur(input) {
    var attrId = parseInt(input.getAttribute('data-attr-id'), 10);
    var langId = parseInt(input.getAttribute('data-lang-id'), 10);
    var text   = input.value;
    _attrUpdateLocal(attrId, langId, text);
    fetch('/catalog/api/save_product_attribute', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + ATTRS_PRODUCT_ID
            + '&attribute_id=' + attrId
            + '&language_id=' + langId
            + '&text=' + encodeURIComponent(text)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (!d.ok) showToast('Помилка: ' + (d.error || '?')); })
    .catch(function() { showToast('Помилка мережі'); });
}

function attrDelete(attrId) {
    if (!confirm('Видалити атрибут з усіх мов?')) return;
    fetch('/catalog/api/delete_product_attribute', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + ATTRS_PRODUCT_ID + '&attribute_id=' + attrId
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            ATTRS.data.sites.forEach(function(site) {
                site.languages.forEach(function(lang) {
                    lang.values = lang.values.filter(function(v) { return v.attribute_id !== attrId; });
                });
            });
            renderAttrPanel();
            showToast('Атрибут видалено');
        } else {
            showToast('Помилка: ' + (d.error || '?'));
        }
    })
    .catch(function() { showToast('Помилка мережі'); });
}

function attrAdd(selectId) {
    var sel = document.getElementById(selectId);
    if (!sel || !sel.value) { showToast('Виберіть атрибут'); return; }
    var attrId   = parseInt(sel.value, 10);
    var attrName = sel.options[sel.selectedIndex].text;
    ATTRS.data.sites.forEach(function(site) {
        if (site.site_id !== ATTRS.activeSite) return;
        site.languages.forEach(function(lang) {
            var exists = lang.values.some(function(v) { return v.attribute_id === attrId; });
            if (!exists) {
                lang.values.push({ attribute_id: attrId, attribute_name: attrName, text: '' });
                lang.values.sort(function(a, b) { return a.attribute_name.localeCompare(b.attribute_name); });
            }
        });
    });
    renderAttrPanel();
    // Focus the new input
    var inputs = document.querySelectorAll('.attr-value-input');
    for (var i = 0; i < inputs.length; i++) {
        if (parseInt(inputs[i].getAttribute('data-attr-id'), 10) === attrId) {
            inputs[i].focus();
            break;
        }
    }
}

function _attrUpdateLocal(attrId, langId, text) {
    ATTRS.data.sites.forEach(function(site) {
        site.languages.forEach(function(lang) {
            if (lang.language_id !== langId) return;
            lang.values.forEach(function(v) { if (v.attribute_id === attrId) v.text = text; });
        });
    });
}

function toggleProdDesc(sid, lid) {
    var box  = document.getElementById('cnt-desc-' + sid + '-' + lid);
    var edit = document.getElementById('cnt-desc-edit-' + sid + '-' + lid);
    var ta   = document.getElementById('cnt-desc-ta-' + sid + '-' + lid);
    var lnk  = document.getElementById('cnt-desc-lnk-' + sid + '-' + lid);
    if (!box || !edit) return;
    var isEdit = edit.style.display !== 'none';
    if (isEdit) {
        // cancel — restore preview from textarea
        box.style.display = '';
        edit.style.display = 'none';
        if (lnk) lnk.textContent = 'редагувати';
    } else {
        // switch to edit
        if (ta) ta.value = box.innerHTML === '<span style="color:var(--text-faint)">—</span>' ? '' : box.innerHTML;
        box.style.display = 'none';
        edit.style.display = '';
        if (lnk) lnk.textContent = 'скасувати';
        if (ta) ta.focus();
    }
}

function saveProdDesc(sid, lid) {
    var ta  = document.getElementById('cnt-desc-ta-' + sid + '-' + lid);
    var box = document.getElementById('cnt-desc-' + sid + '-' + lid);
    var st  = document.getElementById('cnt-desc-st-' + sid + '-' + lid);
    var edit = document.getElementById('cnt-desc-edit-' + sid + '-' + lid);
    var lnk  = document.getElementById('cnt-desc-lnk-' + sid + '-' + lid);
    if (!ta) return;
    var html = ta.value;
    var pid  = <?php echo isset($details) ? (int)$details['product_id'] : 0; ?>;
    if (st) { st.textContent = 'Збереження…'; st.className = 'desc-save-status'; }
    var fields = {}; fields['description'] = html;
    fetch('/catalog/api/save_product_content', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'product_id=' + pid + '&site_id=' + sid + '&language_id=' + lid
            + '&fields=' + encodeURIComponent(JSON.stringify(fields))
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            box.innerHTML = html !== '' ? html : '<span style="color:var(--text-faint)">—</span>';
            box.style.display = '';
            edit.style.display = 'none';
            if (lnk) lnk.textContent = 'редагувати';
            if (st) { st.textContent = ''; }
        } else {
            if (st) { st.textContent = d.error || 'Помилка'; st.className = 'desc-save-status err'; }
        }
    })
    .catch(function() {
        if (st) { st.textContent = 'Помилка мережі'; st.className = 'desc-save-status err'; }
    });
}
<?php endif; ?>

// Product image slider
(function() {
    var sliderEl  = document.getElementById('prodSlider');
    var slidesEl  = document.getElementById('prodSliderSlides');
    var emptyEl   = document.getElementById('prodImgEmpty');
    var sitesEl   = document.getElementById('prodSliderSites');
    var prevBtn   = document.getElementById('prodSliderPrev');
    var nextBtn   = document.getElementById('prodSliderNext');
    var counterEl = document.getElementById('prodSliderCounter');
    var repBtn    = document.getElementById('prodImgReplaceBtn');
    var delBtn    = document.getElementById('prodImgDeleteBtn');
    var addBtn    = document.getElementById('prodImgUploadBtn');
    var fileInput = document.getElementById('prodImgFile');
    var repFile   = document.getElementById('prodImgReplaceFile');
    var overlay   = document.getElementById('prodSliderOverlay');
    if (!slidesEl) return;

    var SITES      = {1: 'off', 2: 'mff'};
    var SITE_AVAIL = {
        1: <?php echo (isset($_prodIdOff) && $_prodIdOff > 0) ? 'true' : 'false'; ?>,
        2: <?php echo (isset($_prodIdMf)  && $_prodIdMf  > 0) ? 'true' : 'false'; ?>
    };
    var _images    = <?php echo isset($_prodImagesJson) ? $_prodImagesJson : '[]'; ?>;
    var _productId = <?php echo isset($details) ? (int)$details['product_id'] : 0; ?>;
    var _cur       = 0;
    var MAIN_PER_SITE = <?php echo isset($_mainPerSite) ? $_mainPerSite : '{}'; ?>;
    var SITE_BADGE_CLASS = {1: 'slide-main-badge-off', 2: 'slide-main-badge-mf'};

    // Annotate _images with which sites each is main for
    function computeMainSites() {
        for (var i = 0; i < _images.length; i++) {
            _images[i].main_sites = [];
            for (var sid in MAIN_PER_SITE) {
                if (MAIN_PER_SITE[sid] === _images[i].path) {
                    _images[i].main_sites.push(parseInt(sid, 10));
                }
            }
        }
    }
    computeMainSites();

    function setLoading(on) { overlay.classList.toggle('active', on); }

    function goTo(idx) {
        if (!_images.length) return;
        _cur = (idx + _images.length) % _images.length;
        slidesEl.style.transform = 'translateX(-' + (_cur * 100) + '%)';
        if (_images.length > 1) counterEl.textContent = (_cur + 1) + ' / ' + _images.length;
        renderSiteBadges();
    }

    function renderSiteBadges() {
        sitesEl.innerHTML = '';
        if (_images.length === 0) {
            // Show unavailable / no-image state for all sites
            for (var sid in SITES) {
                (function(siteId) {
                    var avail = SITE_AVAIL[siteId];
                    var badge = document.createElement('span');
                    badge.className = 'prod-img-site ' + (avail ? 'inactive' : 'unavail');
                    badge.textContent = SITES[siteId];
                    badge.title = avail ? 'Немає фото для ' + SITES[siteId] : 'Товар відсутній на ' + SITES[siteId];
                    sitesEl.appendChild(badge);
                })(parseInt(sid, 10));
            }
            return;
        }
        var img = _images[_cur];
        for (var sid in SITES) {
            (function(siteId) {
                var avail = SITE_AVAIL[siteId];
                var isOn  = img.sites.indexOf(siteId) !== -1;
                var badge = document.createElement('span');
                badge.className = 'prod-img-site ' + (avail ? (isOn ? 'active' : 'inactive') : 'unavail');
                badge.textContent = SITES[siteId];
                badge.title = avail
                    ? (isOn ? 'Опубліковано на ' + SITES[siteId] + ' — клік щоб зняти' : 'Не опубліковано на ' + SITES[siteId] + ' — клік щоб опублікувати')
                    : 'Товар відсутній на ' + SITES[siteId];
                if (avail) badge.onclick = function() { toggleSite(img, siteId, !isOn, badge); };
                sitesEl.appendChild(badge);
            })(parseInt(sid, 10));
        }
    }

    function updateControls() {
        var count = _images.length;
        if (count === 0) {
            sliderEl.style.display = 'none';
            emptyEl.style.display  = '';
        } else {
            sliderEl.style.display = '';
            emptyEl.style.display  = 'none';
            var multi = count > 1;
            prevBtn.style.display   = multi ? '' : 'none';
            nextBtn.style.display   = multi ? '' : 'none';
            counterEl.style.display = multi ? '' : 'none';
            if (multi) counterEl.textContent = (_cur + 1) + ' / ' + count;
            var setMainBtn = document.getElementById('prodImgSetMainBtn');
            if (setMainBtn) setMainBtn.style.display = multi ? '' : 'none';
        }
        renderSiteBadges();
    }

    function rebuildSlides() {
        slidesEl.innerHTML = '';
        for (var i = 0; i < _images.length; i++) {
            (function(img) {
                var slide = document.createElement('div');
                slide.className = 'prod-slider-slide';
                slide.style.position = 'relative';
                var el = document.createElement('img');
                el.src = img.url;
                el.alt = '';
                el.setAttribute('data-zoom', img.url);
                slide.appendChild(el);
                // Main photo stickers
                if (img.main_sites && img.main_sites.length) {
                    var badgeWrap = document.createElement('div');
                    badgeWrap.className = 'slide-main-badges';
                    img.main_sites.forEach(function(sid) {
                        var b = document.createElement('span');
                        b.className = 'slide-main-badge ' + (SITE_BADGE_CLASS[sid] || '');
                        b.textContent = SITES[sid] || sid;
                        badgeWrap.appendChild(b);
                    });
                    slide.appendChild(badgeWrap);
                }
                slidesEl.appendChild(slide);
            })(_images[i]);
        }
        slidesEl.style.transform = 'translateX(-' + (_cur * 100) + '%)';
        updateControls();
    }

    // Zoom via event delegation — avoids cross-script-block scope issues
    sliderEl.addEventListener('click', function(e) {
        var t = e.target;
        if (t.tagName === 'IMG' && t.getAttribute('data-zoom')) {
            var m  = document.getElementById('imageModal');
            var mi = document.getElementById('imageModalImg');
            if (m && mi) { mi.src = t.getAttribute('data-zoom'); m.classList.add('open'); }
        }
    });

    function deleteImage() {
        if (!_images.length) return;
        if (!confirm('Видалити це фото?')) return;
        var imageId = _images[_cur].image_id;
        setLoading(true);
        var fd = new FormData();
        fd.append('entity_type', 'product');
        fd.append('image_id', imageId);
        fetch('/shared/api/delete_image', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                setLoading(false);
                if (!d.ok) { showToast(d.error || 'Помилка видалення'); return; }
                _images.splice(_cur, 1);
                if (_cur >= _images.length) _cur = Math.max(0, _images.length - 1);
                rebuildSlides();
                showToast('Фото видалено');
            })
            .catch(function() { setLoading(false); showToast('Помилка мережі'); });
    }

    function replaceImage(file) {
        if (!_images.length) return;
        var imageId = _images[_cur].image_id;
        var idx     = _cur;
        setLoading(true);
        var fd = new FormData();
        fd.append('entity_type', 'product');
        fd.append('image_id', imageId);
        fd.append('image', file);
        fetch('/shared/api/replace_image', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                setLoading(false);
                if (!d.ok) { showToast(d.error || 'Помилка заміни'); return; }
                _images[idx].path = d.data.path;
                _images[idx].url  = d.data.url;
                rebuildSlides();
                showToast('Фото замінено');
            })
            .catch(function() { setLoading(false); showToast('Помилка мережі'); });
    }

    function uploadImage(file) {
        setLoading(true);
        var fd = new FormData();
        fd.append('entity_type', 'product');
        fd.append('entity_id', _productId);
        fd.append('image', file);
        fetch('/shared/api/upload_image', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                setLoading(false);
                if (!d.ok) { showToast(d.error || 'Помилка завантаження'); return; }
                _images.push(d.data);
                _cur = _images.length - 1;
                rebuildSlides();
                var siteNames = (d.data.sites || []).map(function(s) { return SITES[s] || s; });
                var msg = siteNames.length
                    ? 'Фото додано → ' + siteNames.join(', ')
                    : 'Фото додано (не опубліковано — вкажіть сайт нижче)';
                showToast(msg);
            })
            .catch(function() { setLoading(false); showToast('Помилка мережі'); });
    }

    function toggleSite(imgObj, siteId, enable, badge) {
        sitesEl.style.pointerEvents = 'none';
        badge.className = 'prod-img-site ' + (enable ? 'active' : 'inactive') + ' loading';
        var fd = new FormData();
        fd.append('image_id', imgObj.image_id);
        fd.append('site_id', siteId);
        fd.append('enabled', enable ? 1 : 0);
        fetch('/shared/api/toggle_image_site', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                sitesEl.style.pointerEvents = '';
                if (!d.ok) { renderSiteBadges(); showToast(d.error || 'Помилка'); return; }
                if (enable) {
                    if (imgObj.sites.indexOf(siteId) === -1) imgObj.sites.push(siteId);
                } else {
                    imgObj.sites = imgObj.sites.filter(function(s) { return s !== siteId; });
                }
                renderSiteBadges();
                showToast(enable
                    ? 'Опубліковано на ' + SITES[siteId]
                    : 'Знято з ' + SITES[siteId]);
            })
            .catch(function() {
                sitesEl.style.pointerEvents = '';
                renderSiteBadges();
                showToast('Помилка мережі');
            });
    }

    prevBtn.addEventListener('click', function() { goTo(_cur - 1); });
    nextBtn.addEventListener('click', function() { goTo(_cur + 1); });
    delBtn.addEventListener('click', deleteImage);
    repBtn.addEventListener('click', function() { repFile.value = ''; repFile.click(); });
    addBtn.addEventListener('click', function() { fileInput.value = ''; fileInput.click(); });
    fileInput.addEventListener('change', function() { if (this.files && this.files[0]) uploadImage(this.files[0]); });
    repFile.addEventListener('change',   function() { if (this.files && this.files[0]) replaceImage(this.files[0]); });

    // ── Set main photo ────────────────────────────────────────────────────────
    var setMainBtn     = document.getElementById('prodImgSetMainBtn');
    var setMainOverlay = document.getElementById('setMainOverlay');
    var setMainCancel  = document.getElementById('setMainCancel');
    var setMainConfirm = document.getElementById('setMainConfirm');
    var setMainList    = document.getElementById('setMainSiteList');
    var _setMainChecked = {};

    function openSetMain() {
        if (!_images.length || _images.length < 2) return;
        var img = _images[_cur];
        _setMainChecked = {};
        setMainList.innerHTML = '';
        // Show only sites where: image is assigned AND site is available AND image is NOT already main
        for (var sid in SITES) {
            (function(siteId) {
                if (!SITE_AVAIL[siteId]) return;
                var isAssigned = img.sites.indexOf(siteId) !== -1;
                if (!isAssigned) return;
                var isAlreadyMain = img.main_sites && img.main_sites.indexOf(siteId) !== -1;
                if (isAlreadyMain) return;
                _setMainChecked[siteId] = true;
                var item = document.createElement('label');
                item.className = 'set-main-site-item';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = true;
                cb.value = siteId;
                cb.addEventListener('change', function() {
                    _setMainChecked[siteId] = cb.checked;
                });
                item.appendChild(cb);
                var lbl = document.createElement('span');
                lbl.textContent = SITES[siteId];
                item.appendChild(lbl);
                setMainList.appendChild(item);
            })(parseInt(sid, 10));
        }
        if (!setMainList.children.length) {
            showToast('Це фото вже є головним на всіх сайтах');
            return;
        }
        setMainOverlay.classList.add('open');
    }

    function closeSetMain() {
        setMainOverlay.classList.remove('open');
    }

    function applySetMain() {
        var img = _images[_cur];
        var siteIds = [];
        for (var sid in _setMainChecked) {
            if (_setMainChecked[sid]) siteIds.push(parseInt(sid, 10));
        }
        if (!siteIds.length) { closeSetMain(); return; }

        closeSetMain();
        setLoading(true);

        var pending = siteIds.length;
        var hasError = false;

        siteIds.forEach(function(siteId) {
            var fd = new FormData();
            fd.append('product_id', _productId);
            fd.append('image_id', img.image_id);
            fd.append('site_id', siteId);
            fetch('/catalog/api/set_main_image', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        MAIN_PER_SITE[siteId] = d.main_path;
                    } else {
                        hasError = true;
                        showToast('Помилка для ' + SITES[siteId] + ': ' + (d.error || '?'));
                    }
                    pending--;
                    if (pending === 0) {
                        setLoading(false);
                        computeMainSites();
                        rebuildSlides();
                        goTo(_cur);
                        if (!hasError) showToast('Головне фото оновлено');
                    }
                })
                .catch(function() {
                    hasError = true;
                    pending--;
                    if (pending === 0) {
                        setLoading(false);
                        computeMainSites();
                        rebuildSlides();
                        goTo(_cur);
                    }
                });
        });
    }

    if (setMainBtn)     setMainBtn.addEventListener('click', openSetMain);
    if (setMainCancel)  setMainCancel.addEventListener('click', closeSetMain);
    if (setMainConfirm) setMainConfirm.addEventListener('click', applySetMain);
    if (setMainOverlay) setMainOverlay.addEventListener('click', function(e) {
        if (e.target === setMainOverlay) closeSetMain();
    });

    // Swipe
    var _tx = 0;
    sliderEl.addEventListener('touchstart', function(e) { _tx = e.touches[0].clientX; }, {passive:true});
    sliderEl.addEventListener('touchend',   function(e) {
        var dx = e.changedTouches[0].clientX - _tx;
        if (Math.abs(dx) > 40) goTo(dx < 0 ? _cur + 1 : _cur - 1);
    }, {passive:true});

    rebuildSlides();
})();

</script>

<div id="cell-popup">
    <div class="cell-popup-copied" id="cell-popup-copied">&#10003; Скопировано</div>
    <button class="cell-popup-btn" id="cell-popup-copy">Скопировать</button>
    <button class="cell-popup-btn" id="cell-popup-edit">Открыть карточку</button>
</div>

<script>
// Cell action popup
(function() {
    var popup = document.getElementById('cell-popup');
    var btnCopy = document.getElementById('cell-popup-copy');
    var btnEdit = document.getElementById('cell-popup-edit');
    var copiedMsg = document.getElementById('cell-popup-copied');
    var currentCopyText = '';
    var currentRowUrl = '';

    function showPopup(e, copyText, rowUrl) {
        currentCopyText = copyText;
        currentRowUrl = rowUrl;
        copiedMsg.style.display = 'none';
        var x = e.clientX;
        var y = e.clientY;
        popup.style.left = x + 'px';
        popup.style.top = y + 'px';
        popup.classList.add('open');
        // adjust if outside viewport
        var rect = popup.getBoundingClientRect();
        if (rect.right > window.innerWidth - 8) {
            popup.style.left = (x - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight - 8) {
            popup.style.top = (y - rect.height) + 'px';
        }
    }

    function hidePopup() {
        popup.classList.remove('open');
    }


    btnCopy.addEventListener('click', function() {
        if (!currentCopyText) { hidePopup(); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(currentCopyText).then(function() {
                copiedMsg.style.display = 'block';
                setTimeout(hidePopup, 900);
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = currentCopyText;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            copiedMsg.style.display = 'block';
            setTimeout(hidePopup, 900);
        }
    });

    btnEdit.addEventListener('click', function() {
        hidePopup();
        if (currentRowUrl) {
            window.location = currentRowUrl;
        }
    });

    document.addEventListener('click', function(e) {
        if (!popup.contains(e.target)) {
            hidePopup();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { hidePopup(); }
    });
})();

// ── Category picker modal ─────────────────────────────────────────────────
(function() {
    var overlay  = document.getElementById('catModalOverlay');
    var editBtn  = document.getElementById('catEditBtn');
    var closeBtn = document.getElementById('catModalClose');
    var cancelBtn= document.getElementById('catCancel');
    var saveBtn  = document.getElementById('catSave');
    var treeWrap = document.getElementById('catTreeContainer');
    if (!overlay || !editBtn) return;

    var productId    = parseInt(editBtn.getAttribute('data-product-id'), 10);
    var currentCatId = parseInt(editBtn.getAttribute('data-category-id'), 10) || 0;
    var tree         = null;
    var catsLoaded   = false;
    var allCats      = [];

    // Papir categories embedded from PHP
    var PAPIR_CATS = <?php
        $catsForJs = array();
        $allCatsRes = Database::fetchAll('Papir',
            "SELECT c.category_id as id, cd.name, c.parent_id
             FROM categoria c
             JOIN category_description cd ON cd.category_id=c.category_id AND cd.language_id=2
             ORDER BY c.parent_id, c.sort_order, c.category_id"
        );
        if ($allCatsRes['ok'] && !empty($allCatsRes['rows'])) {
            foreach ($allCatsRes['rows'] as $cr) {
                $catsForJs[] = array(
                    'id'        => (int)$cr['id'],
                    'name'      => (string)$cr['name'],
                    'parent_id' => (int)$cr['parent_id'],
                );
            }
        }
        echo json_encode($catsForJs);
    ?>;

    function updateCatManageLink(id) {
        var lnk = document.getElementById('catManageLink');
        if (lnk) lnk.href = id ? '/categories?selected=' + id : '/categories';
    }

    function openModal() {
        overlay.classList.add('open');
        updateCatManageLink(currentCatId);
        if (!tree) {
            tree = new CategoryTree({
                container:  treeWrap,
                categories: PAPIR_CATS,
                selectedId: currentCatId,
                searchable: true,
                onSelect: function(id, name) {
                    saveBtn.disabled = false;
                    updateCatManageLink(id);
                }
            });
        } else {
            tree.setSelected(currentCatId);
        }
        tree.focus();
    }

    function closeModal() { overlay.classList.remove('open'); }

    editBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
    });

    saveBtn.addEventListener('click', function() {
        var sel = tree ? tree.getSelected() : null;
        if (!sel) return;
        saveBtn.disabled = true;

        fetch('/catalog/api/save_product_category', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: 'product_id=' + productId + '&category_id=' + sel.id
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            saveBtn.disabled = false;
if (!d.ok) { alert(d.error || 'Помилка збереження'); return; }
            currentCatId = d.category_id;
            editBtn.setAttribute('data-category-id', d.category_id);
            document.getElementById('catName').textContent = d.name || '—';
            closeModal();
        })
        .catch(function() { saveBtn.disabled = false; alert('Помилка мережі'); });
    });
})();

function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(function() { t.classList.remove('show'); }, 2200);
}

</script>
<script>
// Site filter checkboxes → hidden input → auto-submit
(function() {
    var checkboxes = document.querySelectorAll('.js-site-filter');
    var hidden     = document.getElementById('siteFilterHidden');
    var form       = document.getElementById('catalogFilterForm');

    function updateHidden() {
        var vals = [];
        var total = checkboxes.length;
        var checked = 0;
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) { vals.push(checkboxes[i].value); checked++; }
        }
        // All checked = send empty = no filter (show all)
        hidden.value = (checked === total) ? '' : vals.join(',');
        // Update pill styles
        for (var j = 0; j < checkboxes.length; j++) {
            var pill = checkboxes[j].parentElement;
            if (checkboxes[j].checked) {
                pill.classList.add('checked');
            } else {
                pill.classList.remove('checked');
            }
        }
    }

    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].addEventListener('change', function() {
            updateHidden();
            form.submit();
        });
    }
})();

// ── Site status toggles in table rows ────────────────────────────────────────
document.addEventListener('click', function(e) {
    var badge = e.target.closest('.site-badges .site-badge');
    if (!badge) return;
    e.stopPropagation();
    var productId  = parseInt(badge.dataset.productId, 10);
    var siteId     = parseInt(badge.dataset.siteId, 10);
    var enabled    = parseInt(badge.dataset.enabled, 10);
    var bkStatus   = parseInt(badge.dataset.bkStatus, 10);
    var newEnabled = enabled ? 0 : 1;
    if (newEnabled === 1 && bkStatus !== 1) {
        showToast('Нельзя включить: товар неактивен в БК');
        return;
    }
    badge.classList.add('loading');
    var fd = new FormData();
    fd.append('product_id', productId);
    fd.append('site_id', siteId);
    fd.append('enabled', newEnabled);
    fetch('/catalog/api/toggle_site_status', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            badge.classList.remove('loading');
            if (!d.ok) { showToast(d.error || 'Ошибка'); return; }
            badge.dataset.enabled = newEnabled;
            newEnabled ? badge.classList.remove('inactive') : badge.classList.add('inactive');
        })
        .catch(function() { badge.classList.remove('loading'); showToast('Ошибка сети'); });
});

// ── BK status toggle in product card ─────────────────────────────────────────
var bkToggle = document.getElementById('bkStatusToggle');
if (bkToggle) {
    bkToggle.addEventListener('change', function() {
        var productId = parseInt(this.dataset.productId, 10);
        var enabled   = this.checked ? 1 : 0;
        var pill      = document.getElementById('bkStatusPill');
        var siteRow   = document.getElementById('prodSiteRow');
        this.disabled = true;
        var fd = new FormData();
        fd.append('product_id', productId);
        fd.append('enabled', enabled);
        fetch('/catalog/api/toggle_bk_status', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                bkToggle.disabled = false;
                if (!d.ok) { bkToggle.checked = !enabled; showToast(d.error || 'Ошибка'); return; }
                if (pill) {
                    pill.textContent = enabled ? 'Включен' : 'Отключен';
                    pill.className = 'status-pill ' + (enabled ? 'status-pill-on' : 'status-pill-off');
                }
                if (siteRow) {
                    var badges = siteRow.querySelectorAll('.prod-site-badge');
                    for (var i = 0; i < badges.length; i++) {
                        badges[i].dataset.bkStatus = enabled ? '1' : '0';
                        if (!enabled) {
                            badges[i].dataset.enabled = '0';
                            badges[i].className = 'prod-site-badge site-inactive';
                        }
                    }
                }
                showToast(enabled ? 'Товар включён в БК' : 'Товар отключён в БК и на всех сайтах');
            })
            .catch(function() { bkToggle.disabled = false; bkToggle.checked = !enabled; showToast('Ошибка сети'); });
    });
}

// ── Site status toggles in product card ──────────────────────────────────────
var prodSiteRow = document.getElementById('prodSiteRow');
if (prodSiteRow) {
    prodSiteRow.addEventListener('click', function(e) {
        var badge = e.target.closest('.prod-site-badge');
        if (!badge) return;
        var productId = parseInt(badge.dataset.productId, 10);
        var siteId    = parseInt(badge.dataset.siteId, 10);
        var mapped    = badge.dataset.mapped === '1';
        var bkStatus  = parseInt(badge.dataset.bkStatus, 10);
        var badgeCode = badge.dataset.badge;
        var siteName  = badge.dataset.siteName || '';

        // Not on site yet → add to site
        if (!mapped) {
            if (bkStatus !== 1) {
                showToast('Нельзя добавить: товар неактивен в БК');
                return;
            }
            _addToSite(badge, productId, siteId, badgeCode, siteName, 0);
            return;
        }

        // Already mapped → toggle status
        var enabled    = parseInt(badge.dataset.enabled, 10);
        var newEnabled = enabled ? 0 : 1;
        if (newEnabled === 1 && bkStatus !== 1) {
            showToast('Нельзя включить: товар неактивен в БК');
            return;
        }
        badge.classList.add('loading');
        var fd = new FormData();
        fd.append('product_id', productId);
        fd.append('site_id', siteId);
        fd.append('enabled', newEnabled);
        fetch('/catalog/api/toggle_site_status', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                badge.classList.remove('loading');
                if (!d.ok) { showToast(d.error || 'Ошибка'); return; }
                badge.dataset.enabled = newEnabled;
                badge.className = 'prod-site-badge ' + (newEnabled ? 'site-active-' + badgeCode : 'site-inactive');
                showToast(newEnabled ? 'Включён на сайте' : 'Отключён на сайте');
            })
            .catch(function() { badge.classList.remove('loading'); showToast('Ошибка сети'); });
    });
}

// ── Add to site ───────────────────────────────────────────────────────────────
function _addToSite(badge, productId, siteId, badgeCode, siteName, categoryId) {
    badge.classList.add('loading');
    var fd = new FormData();
    fd.append('product_id', productId);
    fd.append('site_id', siteId);
    if (categoryId > 0) fd.append('category_id', categoryId);
    fetch('/catalog/api/add_to_site', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            badge.classList.remove('loading');
            if (d.ok) {
                badge.dataset.mapped  = '1';
                badge.dataset.enabled = '1';
                badge.innerHTML = '<span class="prod-site-dot"></span>' + siteName;
                badge.className = 'prod-site-badge site-active-' + badgeCode;
                badge.title     = siteName + ': активен — клик для переключения';
                showToast('Товар добавлен на ' + siteName);
                return;
            }
            if (d.error === 'category_required') {
                _showCategoryPicker(d.site_categories || [], siteName, function(catId) {
                    _addToSite(badge, productId, siteId, badgeCode, siteName, catId);
                });
                return;
            }
            showToast(d.error || 'Ошибка добавления');
        })
        .catch(function() { badge.classList.remove('loading'); showToast('Ошибка сети'); });
}

// ── Category picker modal ─────────────────────────────────────────────────────
(function() {
    var overlay   = document.getElementById('catPickerOverlay');
    if (!overlay) return;
    var searchEl  = document.getElementById('catPickerSearch');
    var listEl    = document.getElementById('catPickerList');
    var cancelBtn = document.getElementById('catPickerCancel');
    var titleEl   = document.getElementById('catPickerTitle');
    var _callback = null;
    var _cats     = [];

    window._showCategoryPicker = function(cats, siteName, cb) {
        _cats     = cats;
        _callback = cb;
        if (titleEl) titleEl.textContent = 'Категорія для ' + siteName;
        if (searchEl) searchEl.value = '';
        _renderCats('');
        overlay.style.display = 'flex';
        if (searchEl) setTimeout(function() { searchEl.focus(); }, 50);
    };

    function _renderCats(q) {
        listEl.innerHTML = '';
        var tokens = q.toLowerCase().split(/\s+/).filter(function(t) { return t.length > 0; });
        var shown  = 0;
        for (var i = 0; i < _cats.length; i++) {
            var c    = _cats[i];
            var name = (c.name || '').toLowerCase();
            var ok   = true;
            for (var j = 0; j < tokens.length; j++) {
                if (name.indexOf(tokens[j]) === -1) { ok = false; break; }
            }
            if (!ok) continue;
            var item = document.createElement('div');
            item.className    = 'cat-picker-item';
            item.textContent  = c.name;
            item.dataset.id   = c.category_id;
            listEl.appendChild(item);
            shown++;
            if (shown >= 200) break;
        }
        if (shown === 0) {
            var empty = document.createElement('div');
            empty.className   = 'cat-picker-empty';
            empty.textContent = 'Нічого не знайдено';
            listEl.appendChild(empty);
        }
    }

    if (searchEl) {
        searchEl.addEventListener('input', function() { _renderCats(this.value); });
    }
    listEl.addEventListener('click', function(e) {
        var item = e.target.closest('.cat-picker-item');
        if (!item) return;
        var catId = parseInt(item.dataset.id, 10);
        overlay.style.display = 'none';
        if (_callback) _callback(catId);
    });
    cancelBtn.addEventListener('click', function() { overlay.style.display = 'none'; });
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.style.display = 'none';
    });
})();
</script>
<div class="toast" id="toast"></div>

<!-- Category picker modal (add to site) -->
<div class="bk-confirm-overlay" id="catPickerOverlay" style="display:none">
    <div class="bk-confirm-box" style="width:480px;max-width:95vw">
        <div class="bk-confirm-title" id="catPickerTitle">Виберіть категорію</div>
        <div class="bk-confirm-body" style="padding:0">
            <input type="text" id="catPickerSearch" class="search-input" placeholder="Пошук категорії…"
                   style="width:100%;box-sizing:border-box;border-radius:0;border-left:0;border-right:0;border-top:0">
            <div id="catPickerList" style="max-height:320px;overflow-y:auto;padding:4px 0"></div>
        </div>
        <div class="bk-confirm-footer">
            <button class="mfr-modal-cancel" id="catPickerCancel" type="button">Скасувати</button>
        </div>
    </div>
</div>

<!-- Bulk BK confirm modal -->
<div class="bk-confirm-overlay" id="bulkBkConfirmOverlay">
    <div class="bk-confirm-box">
        <div class="bk-confirm-title" id="bulkBkConfirmTitle"></div>
        <div class="bk-confirm-body"  id="bulkBkConfirmBody"></div>
        <div class="bk-confirm-footer">
            <button class="mfr-modal-cancel" id="bulkBkConfirmCancel" type="button">Скасувати</button>
            <button class="mfr-modal-save"   id="bulkBkConfirmOk"     type="button">Підтвердити</button>
        </div>
    </div>
</div>

<!-- ── Bulk Add To Site modal ───────────────────────────────────────────────── -->
<div class="modal-overlay" id="bulkAddSiteOverlay" style="display:none;">
    <div class="modal-box bulk-site-modal">
        <div class="modal-head">
            <h3>Додати на сайт</h3>
            <button class="modal-close" id="bulkAddSiteClose">×</button>
        </div>
        <div id="baStepSelect">
            <div class="modal-body">
                <p style="margin:0 0 4px;color:#555;font-size:13px;">Вибрано товарів: <strong id="bulkAddSiteCount">0</strong></p>
                <p style="margin:0 0 12px;color:#555;font-size:13px;">Оберіть сайти для додавання:</p>
                <div class="bulk-site-list" id="bulkAddSiteList"></div>
            </div>
            <div class="modal-footer">
                <button class="btn" id="bulkAddSiteCancel">Скасувати</button>
                <button class="btn btn-primary" id="bulkAddSiteConfirm">Додати</button>
            </div>
        </div>
        <div id="baStepProgress" style="display:none;">
            <div class="modal-body">
                <p style="margin:0 0 8px;font-weight:600;">Виконується…</p>
                <div class="bulk-progress-bar"><div class="bulk-progress-fill" id="baProgressFill"></div></div>
                <div class="bulk-progress-text" id="baProgressText">0 / 0</div>
                <div class="bulk-progress-log" id="baProgressLog"></div>
            </div>
        </div>
        <div id="baStepDone" style="display:none;">
            <div class="modal-body">
                <p style="font-weight:600;margin:0 0 6px;">Готово</p>
                <p style="color:#555;margin:0;" id="baDoneText"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="bulkAddSiteDoneClose">Закрити</button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Bulk BK status toggle ─────────────────────────────────────────────────
(function () {
    var btnDisable  = document.getElementById('bulkBkDisable');
    var btnEnable   = document.getElementById('bulkBkEnable');
    var overlay     = document.getElementById('bulkBkConfirmOverlay');
    var titleEl     = document.getElementById('bulkBkConfirmTitle');
    var bodyEl      = document.getElementById('bulkBkConfirmBody');
    var cancelBtn   = document.getElementById('bulkBkConfirmCancel');
    var okBtn       = document.getElementById('bulkBkConfirmOk');

    if (!btnDisable || !btnEnable || !overlay) return;

    var pendingEnabled = 0;
    var BK_STORAGE_KEY = 'papir_catalog_selected_products';

    function getStoredIds() {
        try {
            var raw = localStorage.getItem(BK_STORAGE_KEY);
            return raw ? Object.keys(JSON.parse(raw)) : [];
        } catch (e) { return []; }
    }

    function refreshBkBtns() {
        var hasAny = getStoredIds().length > 0;
        btnDisable.disabled = !hasAny;
        btnEnable.disabled  = !hasAny;
    }

    // Watch selectedCount element for changes (refreshSelectedCounter updates it)
    var selectedCountEl = document.getElementById('selectedCount');
    if (selectedCountEl && window.MutationObserver) {
        var obs = new MutationObserver(refreshBkBtns);
        obs.observe(selectedCountEl, { childList: true, characterData: true, subtree: true });
    }
    refreshBkBtns();

    function getPapirIds() {
        return getStoredIds();
    }

    function nounProducts(n) {
        var m10 = n % 10, m100 = n % 100;
        if (m10 === 1 && m100 !== 11) return 'товар';
        if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return 'товари';
        return 'товарів';
    }

    function openConfirm(enabled) {
        var n = getPapirIds().length;
        if (!n) return;
        pendingEnabled = enabled;
        if (enabled) {
            titleEl.textContent = 'Увімкнути в БК — ' + n + ' ' + nounProducts(n);
            bodyEl.innerHTML    = 'Буде встановлено статус <strong>активний в БК</strong> для ' + n + ' ' + nounProducts(n) + '.<br>Статус на сайтах залишиться без змін.';
            okBtn.className     = 'mfr-modal-save bk-confirm-ok-green';
        } else {
            titleEl.textContent = 'Вимкнути в БК — ' + n + ' ' + nounProducts(n);
            bodyEl.innerHTML    = 'Буде встановлено статус <strong>неактивний в БК</strong> для ' + n + ' ' + nounProducts(n) + '.<br>Товари також будуть вимкнені на всіх підключених сайтах.';
            okBtn.className     = 'mfr-modal-save bk-confirm-ok-red';
        }
        overlay.classList.add('open');
    }

    function closeConfirm() { overlay.classList.remove('open'); }

    btnDisable.addEventListener('click', function () { openConfirm(0); });
    btnEnable.addEventListener('click',  function () { openConfirm(1); });
    cancelBtn.addEventListener('click',  closeConfirm);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeConfirm(); });

    okBtn.addEventListener('click', function () {
        var papirIds = getPapirIds();
        if (!papirIds.length) { closeConfirm(); return; }
        closeConfirm();

        var actionBtn = pendingEnabled ? btnEnable : btnDisable;
        var origText  = actionBtn.textContent;
        actionBtn.disabled  = true;
        actionBtn.textContent = 'Виконується…';

        var fd = new FormData();
        fd.append('product_ids', papirIds.join(','));
        fd.append('enabled', pendingEnabled);

        fetch('/catalog/api/bulk_toggle_bk_status', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                actionBtn.textContent = origText;
                refreshBkBtns();
                if (!d.ok) { showToast(d.error || 'Помилка'); return; }

                // Update table rows DOM
                var idOffMap = {};
                papirIds.forEach(function (pid, i) { idOffMap[pid] = true; });
                getStoredIds().forEach(function (idOff) {
                    var tr = document.querySelector('tr[data-product-id="' + idOff + '"]');
                    if (!tr) return;
                    tr.setAttribute('data-bk-status', pendingEnabled);
                    if (!pendingEnabled) {
                        tr.querySelectorAll('.site-badge').forEach(function (badge) {
                            badge.dataset.enabled = '0';
                            badge.classList.add('inactive');
                        });
                    }
                });

                // Update product card if it shows one of the affected products
                var bkToggle = document.getElementById('bkStatusToggle');
                if (bkToggle) {
                    var cardPid = bkToggle.getAttribute('data-product-id');
                    if (cardPid && idOffMap[cardPid]) {
                        bkToggle.checked = !!pendingEnabled;
                        if (!pendingEnabled) {
                            var siteRow = document.getElementById('prodSiteRow');
                            if (siteRow) {
                                siteRow.querySelectorAll('.prod-site-badge').forEach(function (b) {
                                    b.dataset.enabled = '0';
                                    b.className = 'prod-site-badge site-inactive';
                                });
                            }
                        }
                    }
                }

                showToast(pendingEnabled
                    ? ('Увімкнено в БК: ' + d.processed + ' ' + nounProducts(d.processed))
                    : ('Вимкнено в БК: ' + d.processed + ' ' + nounProducts(d.processed)));
            })
            .catch(function () {
                actionBtn.textContent = origText;
                refreshBkBtns();
                showToast('Помилка мережі');
            });
    });
}());

// ── Bulk Add To Site ──────────────────────────────────────────────────────────
(function () {
    var BK_STORAGE_KEY = 'papir_catalog_selected_products';
    function getStoredIds() {
        try { var r = localStorage.getItem(BK_STORAGE_KEY); return r ? Object.keys(JSON.parse(r)) : []; }
        catch (e) { return []; }
    }

    var SITES = <?php echo json_encode(array_values(array_map(function($s) {
        return array('site_id' => (int)$s['site_id'], 'name' => $s['name'], 'badge' => $s['badge']);
    }, $sites))); ?>;

    var btnAdd    = document.getElementById('bulkAddToSite');
    var btnDelete = document.getElementById('bulkDelete');
    var overlay   = document.getElementById('bulkAddSiteOverlay');
    if (!btnAdd || !overlay) return;

    // ── Refresh disabled state ─────────────────────────────────────────────
    function refreshAddBtn() {
        var hasAny = getStoredIds().length > 0;
        btnAdd.disabled    = !hasAny;
        btnDelete.disabled = !hasAny;
    }
    var countEl = document.getElementById('selectedCount');
    if (countEl && window.MutationObserver) {
        new MutationObserver(refreshAddBtn).observe(countEl, { childList: true, characterData: true, subtree: true });
    }
    refreshAddBtn();

    // ── Build site checkboxes ──────────────────────────────────────────────
    var siteListEl = document.getElementById('bulkAddSiteList');
    SITES.forEach(function (site) {
        var item = document.createElement('div');
        item.className = 'bulk-site-item';
        var cbId = 'baSite_' + site.site_id;
        item.innerHTML =
            '<input type="checkbox" id="' + cbId + '" value="' + site.site_id + '" checked>' +
            '<label for="' + cbId + '">' + site.name + ' <span class="badge badge-blue">' + site.badge + '</span></label>';
        item.addEventListener('click', function (e) {
            if (e.target.tagName !== 'INPUT') item.querySelector('input').click();
        });
        siteListEl.appendChild(item);
    });

    // ── Open modal ────────────────────────────────────────────────────────
    btnAdd.addEventListener('click', function () {
        var ids = getStoredIds();
        if (!ids.length) return;
        document.getElementById('bulkAddSiteCount').textContent = ids.length;
        showStep('select');
        overlay.style.display = 'flex';
    });

    document.getElementById('bulkAddSiteClose').addEventListener('click', closeModal);
    document.getElementById('bulkAddSiteCancel').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

    function closeModal() { overlay.style.display = 'none'; }

    function showStep(step) {
        document.getElementById('baStepSelect').style.display   = step === 'select'   ? '' : 'none';
        document.getElementById('baStepProgress').style.display = step === 'progress' ? '' : 'none';
        document.getElementById('baStepDone').style.display     = step === 'done'     ? '' : 'none';
    }

    // ── Start bulk add ────────────────────────────────────────────────────
    document.getElementById('bulkAddSiteConfirm').addEventListener('click', function () {
        var ids = getStoredIds();
        var siteIds = [];
        siteListEl.querySelectorAll('input[type=checkbox]:checked').forEach(function (cb) {
            siteIds.push(parseInt(cb.value));
        });
        if (!siteIds.length) { showToast('Оберіть хоча б один сайт'); return; }

        // Build task queue: all product × site combos
        var tasks = [];
        ids.forEach(function (pid) {
            siteIds.forEach(function (sid) { tasks.push({ pid: pid, sid: sid, categoryId: 0 }); });
        });

        var total  = tasks.length;
        var done   = 0;
        var ok     = 0;
        var errors = 0;
        // Per-site remembered category (so picker shows once per site)
        var siteCategories = {};

        showStep('progress');
        updateProgress(0, total);

        function updateProgress(n, t) {
            var pct = t > 0 ? Math.round(n / t * 100) : 0;
            document.getElementById('baProgressFill').style.width = pct + '%';
            document.getElementById('baProgressText').textContent = n + ' / ' + t;
        }

        function appendLog(text, isOk) {
            var el = document.createElement('div');
            el.className = isOk ? 'bulk-log-ok' : 'bulk-log-err';
            el.textContent = text;
            var logEl = document.getElementById('baProgressLog');
            logEl.appendChild(el);
            logEl.scrollTop = logEl.scrollHeight;
        }

        function getSiteName(sid) {
            for (var i = 0; i < SITES.length; i++) { if (SITES[i].site_id === sid) return SITES[i].name; }
            return 'Сайт ' + sid;
        }

        function runNext() {
            if (!tasks.length) {
                document.getElementById('baDoneText').textContent =
                    'Успішно: ' + ok + ', помилок: ' + errors + ' (всього: ' + total + ')';
                if (errors === 0) {
                    localStorage.removeItem(BK_STORAGE_KEY);
                    if (typeof refreshSelectedCounter === 'function') refreshSelectedCounter();
                }
                showStep('done');
                return;
            }
            var task = tasks.shift();
            doOne(task.pid, task.sid, siteCategories[task.sid] || 0, function (result) {
                done++;
                if (result.ok) { ok++; appendLog('✓ ID ' + task.pid + ' → ' + getSiteName(task.sid), true); }
                else           { errors++; appendLog('✗ ID ' + task.pid + ' → ' + getSiteName(task.sid) + ': ' + (result.error || '?'), false); }
                updateProgress(done, total);
                runNext();
            }, task.sid);
        }

        function doOne(pid, sid, categoryId, callback, origSid) {
            var fd = new FormData();
            fd.append('product_id', pid);
            fd.append('site_id', sid);
            if (categoryId > 0) fd.append('category_id', categoryId);
            fetch('/catalog/api/add_to_site', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok) { callback({ ok: true }); return; }
                    if (d.error === 'category_required') {
                        var siteName = getSiteName(sid);
                        // Pause queue — show picker once per site
                        window._showCategoryPicker(d.site_categories || [], siteName, function (catId) {
                            siteCategories[origSid] = catId; // remember for remaining products
                            doOne(pid, sid, catId, callback, origSid);
                        });
                        return;
                    }
                    callback({ ok: false, error: d.error });
                })
                .catch(function () { callback({ ok: false, error: 'мережа' }); });
        }

        runNext();
    });

    document.getElementById('bulkAddSiteDoneClose').addEventListener('click', closeModal);
}());

// ── Chip search input ─────────────────────────────────────────────────────────
ChipSearch.init('searchChipBox', 'searchChipTyper', 'search', null, {noComma: true});

// ── Chip clear button ─────────────────────────────────────────────────────────
(function () {
    var clearBtn  = document.getElementById('chipClearBtn');
    var chipBox   = document.getElementById('searchChipBox');
    var typer     = document.getElementById('searchChipTyper');
    var hidden    = document.getElementById('search');
    var searchForm = hidden ? hidden.closest('form') : null;
    if (!clearBtn || !chipBox || !typer || !hidden) return;

    function updateClearBtn() {
        var hasChips = chipBox.querySelectorAll('.chip').length > 0;
        var hasText  = typer.value.trim() !== '';
        if (hasChips || hasText) {
            clearBtn.classList.remove('hidden');
        } else {
            clearBtn.classList.add('hidden');
        }
    }

    // Observe chip additions/removals
    var observer = new MutationObserver(updateClearBtn);
    observer.observe(chipBox, { childList: true });

    // Also watch typer input
    typer.addEventListener('input', updateClearBtn);

    // Clear button click: remove all chips, clear typer, submit
    clearBtn.addEventListener('click', function () {
        // Remove all chip elements
        chipBox.querySelectorAll('.chip').forEach(function (c) { c.remove(); });
        typer.value = '';
        hidden.value = '';
        clearBtn.classList.add('hidden');
        // Submit the form (reset to page 1)
        if (searchForm) {
            var pageInput = searchForm.querySelector('input[name="page"]');
            if (pageInput) pageInput.value = 1;
            searchForm.submit();
        }
    });

    // Initial state
    updateClearBtn();
}());

// ── Split button "Змінити" ────────────────────────────────────────────────────
(function () {
    var btn     = document.getElementById('catSplitBtn');
    var trigger = document.getElementById('catSplitTrigger');
    if (!btn || !trigger) return;

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        btn.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
        if (!btn.contains(e.target)) btn.classList.remove('open');
    });
    // Close after action item click (but not print submenu trigger)
    document.getElementById('catSplitDd').addEventListener('click', function (e) {
        var item = e.target.closest('.cat-dd-item');
        if (item && !item.closest('.cat-dd-print') || (item && item.parentElement.classList.contains('cat-dd-print-sub'))) {
            btn.classList.remove('open');
        }
    });
}());

// ── Delete product (single row context menu) ──────────────────────────────────
(function () {
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.js-row-delete');
        if (!link) return;
        e.preventDefault();

        var productId = parseInt(link.getAttribute('data-product-id'), 10);
        if (!productId) return;

        if (!confirm('Видалити товар ID ' + productId + '?\nКаскад: сайт → фото → Papir. Дія незворотня.')) return;

        link.textContent = '...';
        link.style.pointerEvents = 'none';

        var fd = new FormData();
        fd.append('product_ids', productId);
        fetch('/catalog/api/delete_product', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    var row = link.closest('tr');
                    if (row) row.remove();
                    if (typeof showToast === 'function') showToast('Товар ' + productId + ' видалено');
                } else {
                    alert('Помилка: ' + (d.errors && d.errors[0] ? d.errors[0].error : d.error || '?'));
                    link.textContent = 'Видалити';
                    link.style.pointerEvents = '';
                }
            })
            .catch(function () {
                alert('Мережева помилка');
                link.textContent = 'Видалити';
                link.style.pointerEvents = '';
            });
    });
}());

// ── Bulk delete ───────────────────────────────────────────────────────────────
(function () {
    var BK_STORAGE_KEY = 'papir_catalog_selected_products';
    function getStoredIds() {
        try { var r = localStorage.getItem(BK_STORAGE_KEY); return r ? Object.keys(JSON.parse(r)) : []; }
        catch (e) { return []; }
    }
    function clearStoredIds() {
        localStorage.removeItem(BK_STORAGE_KEY);
    }

    var btnDelete = document.getElementById('bulkDelete');
    if (!btnDelete) return;

    btnDelete.addEventListener('click', function () {
        var ids = getStoredIds();
        if (!ids.length) return;

        if (!confirm('Видалити ' + ids.length + ' товар(ів)?\nКаскад: сайт → фото → Papir. Дія незворотня.')) return;

        btnDelete.disabled = true;
        btnDelete.textContent = 'Видалення...';

        var fd = new FormData();
        fd.append('product_ids', ids.join(','));
        fetch('/catalog/api/delete_product', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    clearStoredIds();
                    if (typeof showToast === 'function') showToast('Видалено ' + d.deleted + ' товар(ів)');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    var msg = 'Видалено: ' + d.deleted + ', помилок: ' + d.errors.length;
                    if (d.errors.length) {
                        msg += '\n' + d.errors.map(function(e) { return 'ID ' + e.product_id + ': ' + e.error; }).join('\n');
                    }
                    alert(msg);
                    btnDelete.disabled = false;
                    btnDelete.textContent = 'Видалити';
                }
            })
            .catch(function () {
                alert('Мережева помилка');
                btnDelete.disabled = false;
                btnDelete.textContent = 'Видалити';
            });
    });
}());
</script>

<?php if (!empty($details) && !empty($details['seo'])): ?>
<style>
.ai-gen-opts { display:flex; gap:16px; margin-bottom:14px; }
.ai-gen-opts-group { flex:1; }
.ai-gen-opts-group > b { display:block; font-size:12px; color:var(--text-muted); font-weight:500; margin-bottom:6px; }
.ai-gen-opt-row { display:flex; align-items:center; gap:6px; font-size:13px; margin-bottom:5px; }
.ai-gen-opt-row label { cursor:pointer; }
.ai-gen-prompt-toggle { font-size:12px; color:var(--blue-light); cursor:pointer; user-select:none; margin-bottom:6px; }
.ai-gen-prompt-box { font-size:11px; font-family:monospace; background:#f9fafb; border:1px solid var(--border); border-radius:var(--radius); padding:8px 10px; white-space:pre-wrap; max-height:180px; overflow-y:auto; margin-bottom:10px; display:none; }
.ai-gen-status { font-size:12px; color:var(--text-muted); margin-top:8px; min-height:18px; }
.ai-gen-status.ok  { color:#16a34a; }
.ai-gen-status.err { color:#dc2626; }
</style>

<!-- AI Content Generation Modal -->
<div class="modal-overlay" id="aiContentModal" style="display:none">
    <div class="modal-box" style="max-width:500px">
        <div class="modal-head">
            <span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:5px"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                Генерація контенту
            </span>
            <button type="button" class="modal-close" id="aiContentModalClose">&times;</button>
        </div>
        <div class="modal-body">
            <div class="ai-gen-opts">
                <div class="ai-gen-opts-group">
                    <b>Сайти</b>
                    <div id="aiCntSitesCont"></div>
                </div>
                <div class="ai-gen-opts-group">
                    <b>Мови</b>
                    <div id="aiCntLangsCont"></div>
                </div>
            </div>
            <div class="form-row">
                <label style="font-size:12px;color:var(--text-muted);font-weight:500;display:block;margin-bottom:4px">Нотатка (опціонально)</label>
                <textarea id="aiContentNote" rows="2" placeholder="Додаткові акценти для цього запуску&hellip;" style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid var(--border-input);border-radius:var(--radius);font-size:13px;font-family:var(--font);resize:vertical;outline:none"></textarea>
            </div>
            <div class="ai-gen-prompt-toggle" id="aiCntPromptToggle">&#9654; Переглянути промт</div>
            <div class="ai-gen-prompt-box" id="aiCntPromptBox"></div>
            <div class="ai-gen-status" id="aiCntStatus"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-sm" id="aiContentGenBtn">Згенерувати</button>
            <button type="button" class="btn btn-ghost btn-sm" id="aiContentClose2">Закрити</button>
        </div>
    </div>
</div>

<script>
(function () {
    var CATALOG_AI_PRODUCT_ID = <?php echo (int)$details['product_id']; ?>;
    var CATALOG_AI_SITES = <?php
        $jsSites = array();
        foreach ($details['seo'] as $s) {
            $jsSites[] = array('site_id' => (int)$s['site_id'], 'name' => (string)$s['name']);
        }
        echo json_encode($jsSites, JSON_UNESCAPED_UNICODE);
    ?>;
    var CATALOG_AI_LANGS = [{language_id:2,name:'UK'},{language_id:1,name:'RU'}];

    var modal      = document.getElementById('aiContentModal');
    var triggerBtn = document.getElementById('btnAiContentGen');

    function buildModal() {
        var sc = document.getElementById('aiCntSitesCont');
        var lc = document.getElementById('aiCntLangsCont');
        sc.innerHTML = '';
        lc.innerHTML = '';
        for (var si = 0; si < CATALOG_AI_SITES.length; si++) {
            var s = CATALOG_AI_SITES[si];
            var row = document.createElement('div');
            row.className = 'ai-gen-opt-row';
            row.innerHTML = '<input type="checkbox" id="aiCs_' + s.site_id + '" value="' + s.site_id + '" checked>'
                + '<label for="aiCs_' + s.site_id + '">' + s.name + '</label>';
            sc.appendChild(row);
        }
        for (var li = 0; li < CATALOG_AI_LANGS.length; li++) {
            var l = CATALOG_AI_LANGS[li];
            var row = document.createElement('div');
            row.className = 'ai-gen-opt-row';
            row.innerHTML = '<input type="checkbox" id="aiCl_' + l.language_id + '" value="' + l.language_id + '" checked>'
                + '<label for="aiCl_' + l.language_id + '">' + l.name + '</label>';
            lc.appendChild(row);
        }
        document.getElementById('aiContentNote').value = '';
        document.getElementById('aiCntStatus').textContent = '';
        document.getElementById('aiCntStatus').className = 'ai-gen-status';
        document.getElementById('aiCntPromptBox').style.display = 'none';
        document.getElementById('aiCntPromptBox').textContent = '';
        document.getElementById('aiCntPromptToggle').textContent = '\u25BA Переглянути промт';
        document.getElementById('aiContentGenBtn').disabled = false;
        document.getElementById('aiContentGenBtn').textContent = 'Згенерувати';
    }

    function openAiModal() {
        buildModal();
        modal.style.display = 'flex';
    }

    function closeAiModal() {
        modal.style.display = 'none';
    }

    if (triggerBtn) triggerBtn.addEventListener('click', openAiModal);
    document.getElementById('aiContentModalClose').addEventListener('click', closeAiModal);
    document.getElementById('aiContentClose2').addEventListener('click', closeAiModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeAiModal(); });

    // Prompt preview toggle
    document.getElementById('aiCntPromptToggle').addEventListener('click', function() {
        var box = document.getElementById('aiCntPromptBox');
        if (box.style.display === 'none') {
            box.style.display = 'block';
            this.textContent = '\u25BC Сховати промт';
            if (!box.textContent.trim()) loadPreview();
        } else {
            box.style.display = 'none';
            this.textContent = '\u25BA Переглянути промт';
        }
    });

    document.getElementById('aiContentNote').addEventListener('input', function() {
        var box = document.getElementById('aiCntPromptBox');
        if (box.style.display !== 'none') { box.textContent = ''; loadPreview(); }
    });

    function loadPreview() {
        var box = document.getElementById('aiCntPromptBox');
        var siteCbs = document.querySelectorAll('[id^="aiCs_"]:checked');
        var langCbs = document.querySelectorAll('[id^="aiCl_"]:checked');
        if (!siteCbs.length || !langCbs.length) { box.textContent = '\u2014'; return; }
        box.textContent = 'Завантаження\u2026';
        var sid = parseInt(siteCbs[0].value, 10);
        var lid = parseInt(langCbs[0].value, 10);
        var body = 'entity_type=product&entity_id=' + CATALOG_AI_PRODUCT_ID
            + '&site_id=' + sid + '&language_id=' + lid + '&use_case=content'
            + '&custom_note=' + encodeURIComponent(document.getElementById('aiContentNote').value)
            + '&return_prompt=1&preview_only=1';
        fetch('/ai/api/generate_content', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            box.textContent = d.ok
                ? '=== SYSTEM ===\n\n' + d.system_prompt + '\n\n=== USER ===\n\n' + d.user_prompt
                : (d.error || 'Помилка');
        })
        .catch(function() { box.textContent = 'Помилка мережі'; });
    }

    // Generate
    document.getElementById('aiContentGenBtn').addEventListener('click', function() {
        var siteCbs = document.querySelectorAll('[id^="aiCs_"]:checked');
        var langCbs = document.querySelectorAll('[id^="aiCl_"]:checked');
        var status  = document.getElementById('aiCntStatus');

        if (!siteCbs.length || !langCbs.length) {
            status.textContent = 'Оберіть хоча б один сайт і мову';
            status.className = 'ai-gen-status err';
            return;
        }

        var pairs = [];
        for (var si = 0; si < siteCbs.length; si++) {
            for (var li = 0; li < langCbs.length; li++) {
                pairs.push({site_id: parseInt(siteCbs[si].value,10), language_id: parseInt(langCbs[li].value,10)});
            }
        }

        var btn   = this;
        var note  = document.getElementById('aiContentNote').value;
        btn.disabled = true;
        btn.textContent = 'Генерація\u2026';
        status.textContent = '';
        status.className = 'ai-gen-status';

        var done = 0, errors = 0;

        function runNext(idx) {
            if (idx >= pairs.length) {
                btn.disabled = false;
                btn.textContent = 'Ще раз';
                if (errors === 0) {
                    status.textContent = '\u2713 Збережено ' + done + ' варіант(ів). Контент оновлено.';
                    status.className = 'ai-gen-status ok';
                } else {
                    status.textContent = done + ' збережено, ' + errors + ' помилок.';
                    status.className = 'ai-gen-status err';
                }
                return;
            }
            var pair = pairs[idx];
            status.textContent = 'Генерація ' + (idx + 1) + ' / ' + pairs.length + '\u2026';

            var body = 'entity_type=product&entity_id=' + CATALOG_AI_PRODUCT_ID
                + '&site_id=' + pair.site_id + '&language_id=' + pair.language_id + '&use_case=content';
            if (note) body += '&custom_note=' + encodeURIComponent(note);

            fetch('/ai/api/generate_content', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok || !d.fields) { errors++; runNext(idx + 1); return; }
                var saveBody = 'product_id=' + CATALOG_AI_PRODUCT_ID
                    + '&site_id=' + pair.site_id
                    + '&language_id=' + pair.language_id
                    + '&fields=' + encodeURIComponent(JSON.stringify(d.fields));
                fetch('/catalog/api/save_product_content', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: saveBody
                })
                .then(function(r2) { return r2.json(); })
                .then(function(d2) {
                    if (d2.ok) {
                        done++;
                        var sid = pair.site_id, lid = pair.language_id;
                        if (d.fields.description) {
                            var el = document.getElementById('cnt-desc-' + sid + '-' + lid);
                            if (el) el.innerHTML = d.fields.description;
                        }
                        if (d.fields.meta_title) {
                            var el = document.getElementById('cnt-mt-' + sid + '-' + lid);
                            if (el) el.textContent = d.fields.meta_title;
                        }
                        if (d.fields.meta_description) {
                            var el = document.getElementById('cnt-md-' + sid + '-' + lid);
                            if (el) el.textContent = d.fields.meta_description;
                        }
                    } else { errors++; }
                    runNext(idx + 1);
                })
                .catch(function() { errors++; runNext(idx + 1); });
            })
            .catch(function() { errors++; runNext(idx + 1); });
        }

        runNext(0);
    });
}());
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
