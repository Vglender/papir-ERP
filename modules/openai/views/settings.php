<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../modules/database/database.php';

// ─── Data ─────────────────────────────────────────────────────────────────────

$sitesR = Database::fetchAll('Papir', "SELECT site_id, code, badge, name FROM sites WHERE status=1 ORDER BY sort_order");
$sites  = ($sitesR['ok'] && !empty($sitesR['rows'])) ? $sitesR['rows'] : array();

$catsR = Database::fetchAll('Papir',
    "SELECT c.category_id AS id, c.parent_id, c.status, COALESCE(cd.name, '') AS name
     FROM categoria c
     LEFT JOIN category_description cd ON cd.category_id = c.category_id AND cd.language_id = 2
     ORDER BY c.parent_id, c.sort_order, c.category_id"
);
$allCategories = ($catsR['ok'] && !empty($catsR['rows'])) ? $catsR['rows'] : array();

$settingsUseCases = array(
    'content' => 'Контент (описи товарів)',
    'seo'     => 'SEO (категорії)',
    'chat'    => 'Чат',
);
$settingsData = array();
foreach (array_keys($settingsUseCases) as $uc) {
    $settingsData[$uc] = array();
    foreach ($sites as $site) {
        $siteId = (int)$site['site_id'];
        $ucEsc  = Database::escape('Papir', $uc);
        $r = Database::fetchRow('Papir',
            "SELECT instruction, context FROM ai_instructions
             WHERE entity_type='site' AND entity_id={$siteId} AND site_id={$siteId} AND use_case='{$ucEsc}'"
        );
        $row = ($r['ok'] && !empty($r['row'])) ? $r['row'] : array();
        $ctx = array();
        if (!empty($row['context'])) {
            $d = json_decode($row['context'], true);
            if (is_array($d)) { $ctx = $d; }
        }
        $settingsData[$uc][$siteId] = array(
            'instruction' => isset($row['instruction']) ? (string)$row['instruction'] : '',
            'model'       => isset($ctx['model'])       ? $ctx['model']              : 'gpt-4o-mini',
            'temperature' => isset($ctx['temperature']) ? (float)$ctx['temperature'] : 0.7,
            'max_tokens'  => isset($ctx['max_tokens'])  ? (int)$ctx['max_tokens']    : 800,
        );
    }
}

$availableModels = array(
    'gpt-4o-mini' => 'GPT-4o mini (швидкий, економний)',
    'gpt-4o'      => 'GPT-4o (потужний)',
    'gpt-4-turbo' => 'GPT-4 Turbo',
    'o1-mini'     => 'o1-mini (міркування)',
);

$firstSiteId = !empty($sites) ? (int)$sites[0]['site_id'] : 0;

$title     = 'AI';
$activeNav = 'integr';
$subNav    = 'ai';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
/* ── Layout ──────────────────────────────────────────────────────────────── */
.ai-toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.ai-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; flex-shrink: 0; }

/* ── Main tabs ───────────────────────────────────────────────────────────── */
.ai-tabs { display: flex; border-bottom: 2px solid var(--border); margin-bottom: 24px; }
.ai-tab-btn {
    padding: 8px 22px; font-size: 13px; font-weight: 500; cursor: pointer;
    border: none; background: none; color: var(--text-muted);
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    font-family: var(--font); transition: color .15s;
}
.ai-tab-btn:hover { color: var(--text); }
.ai-tab-btn.active { color: var(--blue); border-bottom-color: var(--blue); }
.ai-tab-panel { display: none; }
.ai-tab-panel.active { display: block; }

/* ── Sub-tabs (use_case / site) ──────────────────────────────────────────── */
.sub-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
.sub-tab-btn {
    padding: 6px 16px; font-size: 12px; font-weight: 500; cursor: pointer;
    border: none; background: none; color: var(--text-muted);
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    font-family: var(--font); transition: color .15s;
}
.sub-tab-btn:hover { color: var(--text); }
.sub-tab-btn.active { color: var(--blue); border-bottom-color: var(--blue); }
.sub-panel { display: none; }
.sub-panel.active { display: block; }

/* ── Instructions tab ────────────────────────────────────────────────────── */
.instr-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 16px;
    align-items: start;
}
.instr-tree-wrap {
    position: sticky;
    top: calc(var(--sticky-top) + 4px);
    height: calc(100vh - var(--sticky-top) - 80px);
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: #fff;
    overflow: hidden;
}
.instr-tree-search {
    padding: 8px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.instr-tree-search input {
    width: 100%; box-sizing: border-box;
    padding: 6px 10px;
    border: 1px solid var(--border-input); border-radius: var(--radius);
    font-size: 12px; font-family: var(--font); outline: none;
}
.instr-tree-search input:focus { border-color: var(--blue-light); }
/* chip-typer внутри chip-input не должен иметь собственную рамку */
.instr-tree-search .chip-typer {
    border: none; padding: 0; background: transparent;
    width: auto; flex: 1; min-width: 80px;
}
.instr-tree-search .chip-typer:focus { border: none; }

/* ── Unified search dropdown ─────────────────────────────────────────────── */
.ai-search-wrap { position: relative; }
.ai-search-dropdown {
    position: absolute; top: calc(100% + 2px); left: 0; right: 0;
    background: #fff; border: 1px solid var(--border);
    border-radius: var(--radius); box-shadow: 0 4px 12px rgba(0,0,0,.12);
    z-index: 200; max-height: 320px; overflow-y: auto;
}
.ai-sdrop-label {
    padding: 5px 10px 3px;
    font-size: 10px; font-weight: 600; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .05em;
    border-top: 1px solid var(--border);
}
.ai-sdrop-label:first-child { border-top: none; }
.ai-sdrop-item {
    display: flex; align-items: baseline; gap: 8px;
    padding: 6px 10px; cursor: pointer; font-size: 12px; line-height: 1.3;
}
.ai-sdrop-item:hover { background: #f0f4ff; }
.ai-sdrop-badge {
    font-size: 10px; font-weight: 600; padding: 1px 5px;
    border-radius: 3px; flex-shrink: 0;
}
.ai-sdrop-badge-cat  { background: #e0e7ff; color: #3730a3; }
.ai-sdrop-badge-prod { background: #dcfce7; color: #166534; }
.ai-sdrop-name { flex: 1; }
.ai-sdrop-sub  { font-size: 11px; color: var(--text-muted); flex-shrink: 0; }
.ai-sdrop-empty { padding: 12px 10px; font-size: 12px; color: var(--text-muted); text-align: center; }
.instr-tree-scroll { flex: 1; overflow-y: auto; padding: 6px 0; }

/* ── Category tree nodes ─────────────────────────────────────────────────── */
.ct-node { margin: 0; }
.ct-row {
    display: flex; align-items: center; gap: 4px;
    padding: 4px 8px; cursor: pointer; user-select: none;
    border-radius: 4px; margin: 1px 4px;
    font-size: 12px;
}
.ct-row:hover { background: var(--bg-hover, #f5f5f5); }
.ct-row.ct-selected { background: #eff6ff; color: var(--blue); }
.ct-toggle {
    width: 14px; flex-shrink: 0;
    font-size: 10px; color: var(--text-muted);
    cursor: pointer; text-align: center;
}
.ct-name { flex: 1; line-height: 1.3; }
.ct-name.ct-disabled { color: var(--text-faint, #bbb); }
.ct-children { padding-left: 14px; }

/* ── Instruction editor (right panel) ───────────────────────────────────── */
.instr-editor { min-height: 200px; }
.instr-empty {
    color: var(--text-muted); font-size: 13px; text-align: center;
    padding: 60px 20px;
    border: 1px dashed var(--border); border-radius: var(--radius); background: #fafafa;
}
.instr-cat-head {
    display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
}
.instr-cat-head h3 { font-size: 15px; font-weight: 600; margin: 0; flex: 1; }

.instr-uc-tabs { margin-bottom: 0; }

.instr-site-panel { margin-top: 0; }
.instr-textarea {
    width: 100%; box-sizing: border-box; padding: 8px 10px;
    border: 1px solid var(--border-input); border-radius: var(--radius);
    font-size: 13px; font-family: var(--font); line-height: 1.5;
    resize: vertical; outline: none; min-height: 120px;
}
.instr-textarea:focus { border-color: var(--blue-light); }
.instr-save-row { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
.instr-status { font-size: 12px; color: var(--text-muted); }
.instr-status.ok  { color: #16a34a; }
.instr-status.err { color: #dc2626; }

/* ── Products section ────────────────────────────────────────────────────── */
.products-section { margin-top: 20px; border-top: 1px solid var(--border); padding-top: 16px; }
.products-section-head {
    display: flex; align-items: center; gap: 8px; margin-bottom: 10px;
}
.products-section-head h4 { font-size: 13px; font-weight: 600; margin: 0; }
.products-loading { color: var(--text-muted); font-size: 12px; padding: 12px 0; }
.products-empty   { color: var(--text-muted); font-size: 12px; padding: 12px 0; }
.products-tbl { font-size: 12px; }
.products-tbl td { padding: 5px 8px; }
.products-tbl tr:hover { background: #f5f5f5; cursor: pointer; }

/* ── Log tab ─────────────────────────────────────────────────────────────── */
.log-filters {
    display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;
    margin-bottom: 14px;
}
.log-filters .filter-group { display: flex; flex-direction: column; gap: 4px; }
.log-filters label { font-size: 11px; color: var(--text-muted); font-weight: 500; }
.log-filters select {
    padding: 6px 10px; border: 1px solid var(--border-input);
    border-radius: var(--radius); font-size: 13px; font-family: var(--font);
    outline: none; background: #fff;
}
.log-filters select:focus { border-color: var(--blue-light); }
.log-pagination { display: flex; align-items: center; gap: 12px; margin-top: 14px; font-size: 13px; }
.log-pagination .page-info { color: var(--text-muted); font-size: 12px; }
.log-bulk-toolbar {
    display: none; align-items: center; gap: 10px;
    padding: 7px 12px; background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: var(--radius); margin-bottom: 8px; font-size: 13px;
}
.log-bulk-toolbar.visible { display: flex; }
.log-bulk-count { color: var(--blue); font-weight: 600; flex: 1; }
.log-cb { width: 15px; height: 15px; cursor: pointer; flex-shrink: 0; }

/* ── Settings tab (site cards) ───────────────────────────────────────────── */
.sites-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 20px;
    align-items: start;
}
.ai-card-head { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.ai-card-head h3 { font-size: 14px; font-weight: 600; margin: 0; }
.ai-field label {
    display: block; font-size: 12px; color: var(--text-muted);
    font-weight: 500; margin-bottom: 4px;
}
.ai-field { margin-bottom: 12px; }
.ai-field textarea {
    width: 100%; box-sizing: border-box; padding: 8px 10px;
    border: 1px solid var(--border-input); border-radius: var(--radius);
    font-size: 13px; font-family: var(--font); line-height: 1.5;
    resize: vertical; outline: none;
}
.ai-field textarea:focus { border-color: var(--blue-light); }
.ai-field select, .ai-field input[type="number"] {
    width: 100%; box-sizing: border-box; padding: 7px 10px;
    border: 1px solid var(--border-input); border-radius: var(--radius);
    font-size: 13px; font-family: var(--font); outline: none;
    background: #fff;
}
.ai-field select:focus, .ai-field input[type="number"]:focus { border-color: var(--blue-light); }
.model-row {
    display: grid; grid-template-columns: 1fr 90px 110px;
    gap: 10px; margin-bottom: 14px;
}
.ai-footer { display: flex; align-items: center; gap: 10px; }
.save-status { font-size: 12px; color: var(--text-muted); }
.save-status.ok  { color: #16a34a; }
.save-status.err { color: #dc2626; }

/* ── Product instruction modal ───────────────────────────────────────────── */
.prod-modal-head { display: flex; align-items: flex-start; gap: 8px; }
.prod-modal-head h3 { font-size: 14px; font-weight: 600; margin: 0; flex: 1; line-height: 1.3; }
.prod-modal-article { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

/* ── Log detail modal ────────────────────────────────────────────────────── */
.log-detail-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.log-detail-meta .meta-item { font-size: 12px; color: var(--text-muted); }
.log-detail-meta .meta-item strong { color: var(--text); }
.log-section-head {
    display: flex; align-items: center; justify-content: space-between;
    margin: 12px 0 6px;
}
.log-section-head h4 {
    font-size: 11px; font-weight: 600; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .05em; margin: 0;
}
/* ── Icon buttons (copy / save / clear) ─────────────────────────────────── */
.pre-actions { display: flex; gap: 4px; }
.pre-action-btn {
    width: 24px; height: 24px; padding: 0;
    border: 1px solid var(--border); border-radius: var(--radius);
    background: #fff; cursor: pointer; color: var(--text-muted);
    display: flex; align-items: center; justify-content: center;
    transition: color .15s, border-color .15s, background .15s;
    flex-shrink: 0;
}
.pre-action-btn:hover { color: var(--blue); border-color: var(--blue-light); background: #f0f7ff; }
.pre-action-btn.danger:hover { color: var(--red); border-color: #fca5a5; background: #fff5f5; }
.pre-action-btn svg { display: block; }
/* ── Textarea toolbar ────────────────────────────────────────────────────── */
.instr-toolbar { display: flex; justify-content: flex-end; gap: 4px; margin-bottom: 6px; }
.log-detail-pre {
    background: #f8f9fa; border: 1px solid var(--border);
    border-radius: var(--radius); padding: 10px 12px;
    font-size: 11px; font-family: monospace; line-height: 1.5;
    white-space: pre-wrap; word-break: break-word;
    max-height: 280px; overflow-y: auto;
    margin: 0;
}

/* ── Chat placeholder ────────────────────────────────────────────────────── */
.badge-future { background: #f3f4f6; color: #9ca3af; font-size: 11px; padding: 2px 8px; border-radius: 10px; }
</style>

<div class="page-wrap-lg">
    <div class="ai-toolbar">
        <h1>AI</h1>
    </div>

    <!-- Main tabs -->
    <div class="ai-tabs">
        <button class="ai-tab-btn" data-tab="instructions">Інструкції</button>
        <button class="ai-tab-btn" data-tab="log">Лог</button>
        <button class="ai-tab-btn active" data-tab="settings">Налаштування</button>
        <button class="ai-tab-btn" data-tab="chat">Чат</button>
    </div>

    <!-- ══ TAB: Інструкції ══════════════════════════════════════════════════ -->
    <div class="ai-tab-panel" id="tab-instructions">
        <!-- Use-case sub-tabs -->
        <div class="sub-tabs instr-uc-tabs" id="instrUcTabs">
            <button class="sub-tab-btn active" data-uc="content">Контент (товари)</button>
            <button class="sub-tab-btn" data-uc="seo">SEO (категорії)</button>
        </div>

        <div class="instr-layout">
            <!-- Tree -->
            <div class="instr-tree-wrap">
                <div class="instr-tree-search">
                    <form id="aiSearchForm" style="margin:0">
                        <div class="ai-search-wrap">
                            <div class="chip-input" id="aiSearchBox">
                                <input type="text" class="chip-typer" id="aiSearchTyper"
                                       placeholder="Пошук категорії або товару…"
                                       autocomplete="off">
                            </div>
                            <input type="hidden" id="aiSearchHidden" value="">
                            <div class="ai-search-dropdown" id="aiSearchDropdown" style="display:none"></div>
                        </div>
                    </form>
                </div>
                <div class="instr-tree-scroll" id="categoryTreeEl"></div>
            </div>

            <!-- Editor -->
            <div class="instr-editor" id="instrEditor">
                <div class="instr-empty" id="instrEmpty">
                    Оберіть категорію в дереві щоб редагувати інструкції
                </div>
                <div id="instrPanel" style="display:none">
                    <div class="instr-cat-head">
                        <h3 id="instrCatName"></h3>
                    </div>

                    <!-- Site tabs for category instruction -->
                    <div class="card" style="margin-bottom:12px">
                        <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:10px">
                            Інструкція категорії
                        </div>
                        <div class="sub-tabs" id="instrSiteTabs" style="margin-bottom:14px">
                            <?php foreach ($sites as $i => $site): ?>
                            <button class="sub-tab-btn<?php echo ($i===0)?' active':''; ?>"
                                    data-siteid="<?php echo (int)$site['site_id']; ?>">
                                <?php echo ViewHelper::h($site['badge']); ?>
                                — <?php echo ViewHelper::h($site['name']); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($sites as $i => $site): ?>
                        <?php $sid = (int)$site['site_id']; ?>
                        <div class="instr-site-panel<?php echo ($i===0)?'':' hidden-site'; ?>"
                             id="instrSitePanel_<?php echo $sid; ?>"
                             <?php echo ($i!==0)?'style="display:none"':''; ?>>
                            <textarea class="instr-textarea"
                                      id="catInstrTA_<?php echo $sid; ?>"
                                      rows="6"
                                      placeholder="Інструкція для цієї категорії (цей сайт). Наприклад: Товари цієї категорії — це канцтовари для школярів. Акцентуй на зносостійкості та яскравих кольорах."></textarea>
                            <div class="instr-save-row">
                                <button class="btn btn-primary btn-sm"
                                        onclick="saveCategoryInstr(<?php echo $sid; ?>)">
                                    Зберегти
                                </button>
                                <span class="instr-status" id="catInstrStatus_<?php echo $sid; ?>"></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Products in category -->
                    <div class="products-section">
                        <div class="products-section-head">
                            <h4>Товари в категорії</h4>
                            <span class="text-muted fs-12" id="productsCount"></span>
                        </div>
                        <div id="productsTableWrap"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ TAB: Лог ═════════════════════════════════════════════════════════ -->
    <div class="ai-tab-panel" id="tab-log">
        <div class="log-filters">
            <div class="filter-group">
                <label>Тип сутності</label>
                <select id="logFilterType">
                    <option value="">Всі</option>
                    <option value="product">Товар</option>
                    <option value="category">Категорія</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Сайт</label>
                <select id="logFilterSite">
                    <option value="0">Всі сайти</option>
                    <?php foreach ($sites as $site): ?>
                    <option value="<?php echo (int)$site['site_id']; ?>">
                        <?php echo ViewHelper::h($site['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Use case</label>
                <select id="logFilterUc">
                    <option value="">Всі</option>
                    <option value="content">Контент</option>
                    <option value="seo">SEO</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Статус</label>
                <select id="logFilterStatus">
                    <option value="">Всі</option>
                    <option value="generated">OK</option>
                    <option value="rejected">Помилка</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Дата від</label>
                <input type="date" id="logFilterDateFrom" style="padding:6px 10px;border:1px solid var(--border-input);border-radius:var(--radius);font-size:13px;font-family:var(--font);outline:none" oninput="loadLog(0)">
            </div>
            <div class="filter-group">
                <label>Дата до</label>
                <input type="date" id="logFilterDateTo" style="padding:6px 10px;border:1px solid var(--border-input);border-radius:var(--radius);font-size:13px;font-family:var(--font);outline:none" oninput="loadLog(0)">
            </div>
            <div class="filter-group" style="justify-content:flex-end">
                <label>&nbsp;</label>
                <div class="btn-row">
                    <button class="btn btn-sm" onclick="loadLog(0)">Застосувати</button>
                    <button class="btn btn-ghost btn-sm" onclick="resetLogFilters()">Скинути</button>
                </div>
            </div>
        </div>

        <div class="log-bulk-toolbar" id="logBulkToolbar">
            <span class="log-bulk-count" id="logBulkCount">Вибрано 0</span>
            <button class="btn btn-danger btn-sm" onclick="deleteSelectedLogs()">Видалити вибрані</button>
            <button class="btn btn-ghost btn-sm" onclick="clearLogSelection()">Скасувати</button>
        </div>

        <div class="card" style="padding:0;overflow:hidden">
            <table class="crm-table" id="logTable" style="margin:0">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" class="log-cb" id="logSelectAll" title="Вибрати всі"></th>
                        <th style="width:60px">#</th>
                        <th>Тип</th>
                        <th>Назва</th>
                        <th>Сайт</th>
                        <th style="width:90px">Use case</th>
                        <th style="width:80px">Статус</th>
                        <th style="width:140px">Дата</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:20px">
                        Завантаження…
                    </td></tr>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="logPagination" style="display:none;margin-top:12px"></div>
        </div>
    </div>

    <!-- ══ TAB: Налаштування ════════════════════════════════════════════════ -->
    <div class="ai-tab-panel active" id="tab-settings">
        <!-- Use-case tabs -->
        <div class="sub-tabs" id="settingsUcTabs">
            <?php $firstUc = true; foreach ($settingsUseCases as $uc => $ucLabel): ?>
            <button class="sub-tab-btn<?php echo $firstUc?' active':''; ?>"
                    data-settingsuc="<?php echo $uc; ?>">
                <?php echo ViewHelper::h($ucLabel); ?>
            </button>
            <?php $firstUc = false; endforeach; ?>
        </div>

        <?php $firstUc = true; foreach ($settingsUseCases as $uc => $ucLabel): ?>
        <div class="sub-panel<?php echo $firstUc?' active':''; ?>"
             id="settingsUcPanel_<?php echo $uc; ?>">
            <?php if ($uc === 'chat'): ?>
            <div class="card" style="color:var(--text-muted);font-size:13px;padding:24px;text-align:center">
                Модуль чату з покупцями — в розробці. Тут будуть налаштування системного промту для AI-консультанта.
            </div>
            <?php else: ?>
            <div class="sites-grid">
                <?php foreach ($sites as $site):
                    $siteId = (int)$site['site_id'];
                    $key    = $uc . '_' . $siteId;
                    $d      = $settingsData[$uc][$siteId];
                ?>
                <div class="card">
                    <div class="ai-card-head">
                        <h3><?php echo ViewHelper::h($site['name']); ?></h3>
                        <span class="badge badge-blue"><?php echo ViewHelper::h($site['badge']); ?></span>
                    </div>

                    <div class="ai-field">
                        <label>Системна інструкція сайту (system prompt)</label>
                        <textarea id="instr-<?php echo $key; ?>" rows="8"
                            placeholder="Загальна інструкція для цього сайту…"
                        ><?php echo ViewHelper::h($d['instruction']); ?></textarea>
                    </div>

                    <div class="model-row">
                        <div class="ai-field" style="margin:0">
                            <label>Модель</label>
                            <select id="model-<?php echo $key; ?>">
                                <?php foreach ($availableModels as $mval => $mlabel): ?>
                                <option value="<?php echo $mval; ?>"<?php echo ($d['model']===$mval)?' selected':''; ?>>
                                    <?php echo ViewHelper::h($mlabel); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ai-field" style="margin:0">
                            <label>Температура</label>
                            <input type="number" id="temp-<?php echo $key; ?>"
                                   min="0" max="2" step="0.1"
                                   value="<?php echo number_format($d['temperature'],1); ?>">
                        </div>
                        <div class="ai-field" style="margin:0">
                            <label>Макс. токенів</label>
                            <input type="number" id="maxtok-<?php echo $key; ?>"
                                   min="100" max="4000" step="100"
                                   value="<?php echo (int)$d['max_tokens']; ?>">
                        </div>
                    </div>

                    <div class="ai-footer">
                        <button class="btn btn-primary btn-sm"
                                onclick="saveSettings('<?php echo $uc; ?>', <?php echo $siteId; ?>)">
                            Зберегти
                        </button>
                        <span class="save-status" id="status-<?php echo $key; ?>"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php $firstUc = false; endforeach; ?>
    </div>

    <!-- ══ TAB: Чат ═════════════════════════════════════════════════════════ -->
    <div class="ai-tab-panel" id="tab-chat">
        <?php
        // Chat instruction is global (entity_id=1, site_id=1)
        $chatSiteId = $firstSiteId > 0 ? $firstSiteId : 1;
        $chatD      = isset($settingsData['chat'][$chatSiteId]) ? $settingsData['chat'][$chatSiteId] : array(
            'instruction' => '', 'model' => 'gpt-4o-mini', 'temperature' => 0.7, 'max_tokens' => 400,
        );
        ?>
        <div class="card" style="max-width:700px">
            <div class="ai-card-head" style="margin-bottom:14px">
                <h3 style="font-size:15px;font-weight:600;margin:0">AI-асистент чату (CRM)</h3>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px">
                Ця інструкція використовується кнопкою <strong>✨ AI</strong> у панелі чату контрагента.
                AI отримує контекст клієнта (назва, закази, останні повідомлення) і формує підказку для відповіді.
            </p>

            <div class="ai-field">
                <label>Системна інструкція</label>
                <textarea id="chatAiInstruction" rows="8"
                    placeholder="Ти — AI-асистент менеджера компанії Papir. Допомагай формулювати відповіді клієнтам — коротко, ввічливо, по суті."
                ><?php echo ViewHelper::h($chatD['instruction']); ?></textarea>
            </div>

            <div class="model-row" style="margin-top:10px">
                <div class="ai-field" style="margin:0">
                    <label>Модель</label>
                    <select id="chatAiModel">
                        <?php foreach ($availableModels as $mval => $mlabel): ?>
                        <option value="<?php echo $mval; ?>"<?php echo ($chatD['model']===$mval)?' selected':''; ?>>
                            <?php echo ViewHelper::h($mlabel); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ai-field" style="margin:0">
                    <label>Температура</label>
                    <input type="number" id="chatAiTemp" min="0" max="2" step="0.1"
                           value="<?php echo number_format($chatD['temperature'],1); ?>">
                </div>
                <div class="ai-field" style="margin:0">
                    <label>Макс. токенів</label>
                    <input type="number" id="chatAiMaxTok" min="100" max="2000" step="100"
                           value="<?php echo isset($chatD['max_tokens']) ? (int)$chatD['max_tokens'] : 400; ?>">
                </div>
            </div>

            <div class="ai-footer" style="margin-top:14px">
                <button class="btn btn-primary btn-sm"
                        onclick="saveChatSettings(<?php echo $chatSiteId; ?>)">Зберегти</button>
                <span class="save-status" id="status-chat"></span>
            </div>
        </div>
    </div>
</div>

<!-- ══ Modal: Product instruction ══════════════════════════════════════════ -->
<div class="modal-overlay" id="prodModal" style="display:none">
    <div class="modal-box" style="width:560px">
        <div class="modal-head">
            <div class="prod-modal-head">
                <div>
                    <h3 id="prodModalName">Товар</h3>
                    <div class="prod-modal-article" id="prodModalArticle"></div>
                </div>
            </div>
            <button class="modal-close" onclick="closeProdModal()">✕</button>
        </div>
        <div class="modal-body">
            <!-- Use-case tabs -->
            <div class="sub-tabs" id="prodModalUcTabs" style="margin-bottom:14px">
                <button class="sub-tab-btn active" data-produc="content">Контент</button>
                <button class="sub-tab-btn" data-produc="seo">SEO</button>
            </div>

            <!-- Site tabs -->
            <div class="sub-tabs" id="prodModalSiteTabs" style="margin-bottom:14px">
                <?php foreach ($sites as $i => $site): ?>
                <button class="sub-tab-btn<?php echo ($i===0)?' active':''; ?>"
                        data-prodsiteid="<?php echo (int)$site['site_id']; ?>">
                    <?php echo ViewHelper::h($site['badge']); ?>
                    — <?php echo ViewHelper::h($site['name']); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="instr-toolbar">
                <button class="pre-action-btn danger" title="Очистити" onclick="document.getElementById('prodInstrTA').value=''">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
                <button class="pre-action-btn" title="Копіювати" onclick="copyPreText('prodInstrTA')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
                <button class="pre-action-btn" title="Зберегти як файл" onclick="savePreText('prodInstrTA','instruction.txt')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </button>
            </div>
            <textarea class="instr-textarea" id="prodInstrTA" rows="7"
                placeholder="Специфічна інструкція для цього товару (необов'язково). Перевизначає або доповнює інструкцію категорії."></textarea>
            <div class="instr-save-row" style="margin-top:10px">
                <button class="btn btn-primary btn-sm" onclick="saveProductInstr()">Зберегти</button>
                <span class="instr-status" id="prodInstrStatus"></span>
            </div>
        </div>
    </div>
</div>

<!-- ══ Modal: Log detail ════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="logModal" style="display:none">
    <div class="modal-box" style="width:700px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column">
        <div class="modal-head" style="flex-shrink:0">
            <span id="logModalTitle">Деталі генерації</span>
            <button class="modal-close" onclick="closeLogModal()">✕</button>
        </div>
        <div class="modal-body" style="overflow-y:auto;flex:1">
            <div class="log-detail-meta" id="logModalMeta"></div>
            <div class="log-detail-section">
                <div class="log-section-head">
                    <h4>System Prompt</h4>
                    <div class="pre-actions">
                        <button class="pre-action-btn" title="Копіювати" onclick="copyPreText('logModalSystem')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                        <button class="pre-action-btn" title="Зберегти як файл" onclick="savePreText('logModalSystem','system-prompt.txt')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </button>
                    </div>
                </div>
                <pre class="log-detail-pre" id="logModalSystem"></pre>
            </div>
            <div class="log-detail-section">
                <div class="log-section-head">
                    <h4>User Prompt</h4>
                    <div class="pre-actions">
                        <button class="pre-action-btn" title="Копіювати" onclick="copyPreText('logModalUser')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                        <button class="pre-action-btn" title="Зберегти як файл" onclick="savePreText('logModalUser','user-prompt.txt')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </button>
                    </div>
                </div>
                <pre class="log-detail-pre" id="logModalUser"></pre>
            </div>
            <div class="log-detail-section">
                <div class="log-section-head">
                    <h4>Відповідь AI</h4>
                    <div class="pre-actions">
                        <button class="pre-action-btn" title="Копіювати" onclick="copyPreText('logModalResponse')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                        <button class="pre-action-btn" title="Зберегти як файл" onclick="savePreText('logModalResponse','ai-response.txt')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </button>
                    </div>
                </div>
                <pre class="log-detail-pre" id="logModalResponse"></pre>
            </div>
        </div>
    </div>
</div>

<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script>
// ═══════════════════════════════════════════════════════════════════════════
// State
// ═══════════════════════════════════════════════════════════════════════════
var AI_STATE = {
    instrUseCase: 'content',
    instrSiteId:  <?php echo $firstSiteId; ?>,
    selectedCatId: 0,
    selectedCatName: '',
    prodModalProductId: 0,
    prodModalUseCase: 'content',
    prodModalSiteId: <?php echo $firstSiteId; ?>,
    logOffset: 0,
    logLimit: 20,
    logTotal: 0
};

// ═══════════════════════════════════════════════════════════════════════════
// Main tabs
// ═══════════════════════════════════════════════════════════════════════════
document.querySelectorAll('.ai-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var tab = this.getAttribute('data-tab');
        document.querySelectorAll('.ai-tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.ai-tab-panel').forEach(function(p) { p.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
        if (tab === 'log') { loadLog(0); }
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// Sub-tabs: instructions use_case
// ═══════════════════════════════════════════════════════════════════════════
document.querySelectorAll('#instrUcTabs .sub-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#instrUcTabs .sub-tab-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        AI_STATE.instrUseCase = this.getAttribute('data-uc');
        if (AI_STATE.selectedCatId > 0) {
            loadAllSiteCategoryInstrs();
        }
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// Sub-tabs: instruction site tabs
// ═══════════════════════════════════════════════════════════════════════════
document.querySelectorAll('#instrSiteTabs .sub-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var siteId = parseInt(this.getAttribute('data-siteid'), 10);
        document.querySelectorAll('#instrSiteTabs .sub-tab-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        AI_STATE.instrSiteId = siteId;
        document.querySelectorAll('[id^="instrSitePanel_"]').forEach(function(p) { p.style.display = 'none'; });
        var panel = document.getElementById('instrSitePanel_' + siteId);
        if (panel) { panel.style.display = ''; }
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// Sub-tabs: settings use_case
// ═══════════════════════════════════════════════════════════════════════════
document.querySelectorAll('#settingsUcTabs .sub-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var uc = this.getAttribute('data-settingsuc');
        document.querySelectorAll('#settingsUcTabs .sub-tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('[id^="settingsUcPanel_"]').forEach(function(p) { p.classList.remove('active'); });
        this.classList.add('active');
        var panel = document.getElementById('settingsUcPanel_' + uc);
        if (panel) { panel.classList.add('active'); }
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// CategoryTree
// ═══════════════════════════════════════════════════════════════════════════
var TREE_DATA = <?php echo json_encode($allCategories, JSON_UNESCAPED_UNICODE); ?>;

var CategoryTree = (function() {
    var treeEl = null;
    var selectedId = 0;
    var onSelectCb = null;
    var treeMap = {};

    function buildTree(items) {
        treeMap = {};
        var roots = [];
        items.forEach(function(item) {
            treeMap[item.id] = {
                id: parseInt(item.id, 10),
                parent_id: parseInt(item.parent_id, 10) || 0,
                name: item.name || '',
                status: parseInt(item.status, 10),
                children: []
            };
        });
        items.forEach(function(item) {
            var node = treeMap[item.id];
            if (node.parent_id && treeMap[node.parent_id]) {
                treeMap[node.parent_id].children.push(node);
            } else {
                roots.push(node);
            }
        });
        return roots;
    }

    function makeRow(node) {
        var row = document.createElement('div');
        row.className = 'ct-row' + (node.id === selectedId ? ' ct-selected' : '');
        row.setAttribute('data-id', node.id);

        var toggle = document.createElement('span');
        toggle.className = 'ct-toggle';

        var name = document.createElement('span');
        name.className = 'ct-name' + (node.status == 0 ? ' ct-disabled' : '');
        name.textContent = node.name || '(без назви)';

        row.appendChild(toggle);
        row.appendChild(name);
        row.addEventListener('click', function() {
            treeEl.querySelectorAll('.ct-row').forEach(function(r) { r.classList.remove('ct-selected'); });
            row.classList.add('ct-selected');
            selectedId = node.id;
            if (onSelectCb) { onSelectCb(node.id, node.name); }
        });

        return { row: row, toggle: toggle };
    }

    function renderNode(node, depth) {
        var wrapper = document.createElement('div');
        wrapper.className = 'ct-node';

        var parts = makeRow(node);
        wrapper.appendChild(parts.row);

        if (node.children && node.children.length > 0) {
            var childrenEl = document.createElement('div');
            childrenEl.className = 'ct-children';
            // Top-level expanded, nested collapsed
            var expanded = depth < 1;
            if (!expanded) { childrenEl.style.display = 'none'; }

            node.children.forEach(function(child) {
                childrenEl.appendChild(renderNode(child, depth + 1));
            });
            wrapper.appendChild(childrenEl);

            parts.toggle.innerHTML = expanded ? '&#9662;' : '&#9656;';
            parts.toggle.style.cursor = 'pointer';
            parts.toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                var open = childrenEl.style.display !== 'none';
                childrenEl.style.display = open ? 'none' : '';
                parts.toggle.innerHTML = open ? '&#9656;' : '&#9662;';
            });
        } else {
            parts.toggle.innerHTML = '&nbsp;';
        }

        return wrapper;
    }

    function renderFull(items) {
        var roots = buildTree(items);
        treeEl.innerHTML = '';
        roots.forEach(function(root) {
            treeEl.appendChild(renderNode(root, 0));
        });
    }

    function renderFlat(items) {
        treeEl.innerHTML = '';
        items.forEach(function(item) {
            var row = document.createElement('div');
            row.className = 'ct-row' + (parseInt(item.id,10) === selectedId ? ' ct-selected' : '');
            row.setAttribute('data-id', item.id);
            var toggle = document.createElement('span');
            toggle.className = 'ct-toggle';
            toggle.innerHTML = '&nbsp;';
            var name = document.createElement('span');
            name.className = 'ct-name' + (parseInt(item.status,10) == 0 ? ' ct-disabled' : '');
            name.textContent = item.name || '(без назви)';
            row.appendChild(toggle);
            row.appendChild(name);
            row.addEventListener('click', function() {
                treeEl.querySelectorAll('.ct-row').forEach(function(r) { r.classList.remove('ct-selected'); });
                row.classList.add('ct-selected');
                selectedId = parseInt(item.id, 10);
                if (onSelectCb) { onSelectCb(selectedId, item.name); }
            });
            treeEl.appendChild(row);
        });
    }

    return {
        init: function(el, items, onSelect) {
            treeEl = el;
            onSelectCb = onSelect;
            renderFull(items);
        },
        search: function(q) {
            if (!q) {
                renderFull(TREE_DATA);
                return;
            }
            q = q.toLowerCase();
            var filtered = TREE_DATA.filter(function(item) {
                return item.name && item.name.toLowerCase().indexOf(q) !== -1;
            });
            renderFlat(filtered);
        },
        setSelected: function(id) { selectedId = id; },
        selectById: function(id) {
            id = parseInt(id, 10);
            // Build path from node to root
            var path = [];
            var cur = id;
            var safety = 0;
            while (cur && treeMap[cur] && safety++ < 60) {
                path.unshift(cur);
                cur = treeMap[cur].parent_id;
            }
            // Expand all ancestors so the target row is visible
            for (var i = 0; i < path.length - 1; i++) {
                var arow = treeEl.querySelector('.ct-row[data-id="' + path[i] + '"]');
                if (!arow) { continue; }
                var achildren = arow.parentNode ? arow.parentNode.querySelector('.ct-children') : null;
                var atoggle   = arow.querySelector('.ct-toggle');
                if (achildren && achildren.style.display === 'none') {
                    achildren.style.display = '';
                    if (atoggle) { atoggle.innerHTML = '&#9662;'; }
                }
            }
            // Highlight target
            treeEl.querySelectorAll('.ct-row').forEach(function(r) { r.classList.remove('ct-selected'); });
            selectedId = id;
            var targetRow = treeEl.querySelector('.ct-row[data-id="' + id + '"]');
            if (targetRow) {
                targetRow.classList.add('ct-selected');
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            if (onSelectCb && treeMap[id]) {
                onSelectCb(id, treeMap[id].name);
            }
        }
    };
})();

CategoryTree.init(
    document.getElementById('categoryTreeEl'),
    TREE_DATA,
    function(catId, catName) {
        AI_STATE.selectedCatId   = catId;
        AI_STATE.selectedCatName = catName;
        document.getElementById('instrEmpty').style.display = 'none';
        document.getElementById('instrPanel').style.display = '';
        document.getElementById('instrCatName').textContent = catName;
        loadAllSiteCategoryInstrs();
        loadProductsInCategory(catId);
    }
);

// ═══════════════════════════════════════════════════════════════════════════
// Unified search — ChipSearch + dropdown
// ═══════════════════════════════════════════════════════════════════════════
var _searchTimer = null;

var aiSearchForm = document.getElementById('aiSearchForm');
// ChipSearch вызывает form.submit() при Enter в пустом тайпере
aiSearchForm.submit = function() { doSearchFromChips(); };

// Init ChipSearch ПЕРВЫМ + отключаем запятую как триггер чипа
ChipSearch.init('aiSearchBox', 'aiSearchTyper', 'aiSearchHidden', aiSearchForm, { noComma: true });

// Наш listener — после ChipSearch, hidden.value уже обновлён
aiSearchForm.addEventListener('submit', function(e) {
    e.preventDefault();
    doSearchFromChips();
});

// Живой поиск пока чип не зафиксирован (while typing)
document.getElementById('aiSearchTyper').addEventListener('input', function() {
    var typerVal = this.value.trim();
    clearTimeout(_searchTimer);
    if (typerVal.length < 2) {
        // Если есть зафиксированные чипы — показать их результаты
        var existing = document.getElementById('aiSearchHidden').value.trim();
        if (existing) { _searchTimer = setTimeout(function() { doSearch(existing); }, 100); }
        else { closeSearchDrop(); }
        return;
    }
    // OR: текущий ввод + все зафиксированные чипы
    var existing = document.getElementById('aiSearchHidden').value.trim();
    var fullQ = existing ? existing + ',' + typerVal : typerVal;
    _searchTimer = setTimeout(function() { doSearch(fullQ); }, 250);
});

document.getElementById('aiSearchTyper').addEventListener('blur', function() {
    setTimeout(closeSearchDrop, 200);
});

// Поиск по всем зафиксированным чипам (OR между ними)
function doSearchFromChips() {
    var val = document.getElementById('aiSearchHidden').value.trim();
    if (!val) { closeSearchDrop(); return; }
    doSearch(val);
}

function doSearch(q) {
    fetch('/ai/api/search?q=' + encodeURIComponent(q))
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) { renderSearchDrop(d.categories || [], d.products || []); }
    })
    .catch(function() {});
}

function renderSearchDrop(categories, products) {
    var dd = document.getElementById('aiSearchDropdown');
    dd.innerHTML = '';

    if (categories.length === 0 && products.length === 0) {
        dd.innerHTML = '<div class="ai-sdrop-empty">Нічого не знайдено</div>';
        dd.style.display = '';
        return;
    }

    if (categories.length > 0) {
        var lbl = document.createElement('div');
        lbl.className = 'ai-sdrop-label';
        lbl.textContent = 'Категорії';
        dd.appendChild(lbl);
        categories.forEach(function(cat) {
            var item = document.createElement('div');
            item.className = 'ai-sdrop-item';
            item.innerHTML = '<span class="ai-sdrop-badge ai-sdrop-badge-cat">К</span>'
                + '<span class="ai-sdrop-name">' + escHtml(cat.name || '') + '</span>';
            item.addEventListener('click', function() {
                document.getElementById('aiSearchTyper').value = '';
                closeSearchDrop();
                CategoryTree.selectById(parseInt(cat.id, 10));
            });
            dd.appendChild(item);
        });
    }

    if (products.length > 0) {
        var lbl2 = document.createElement('div');
        lbl2.className = 'ai-sdrop-label';
        lbl2.textContent = 'Товари';
        dd.appendChild(lbl2);
        products.forEach(function(p) {
            var item = document.createElement('div');
            item.className = 'ai-sdrop-item';
            item.innerHTML = '<span class="ai-sdrop-badge ai-sdrop-badge-prod">Т</span>'
                + '<span class="ai-sdrop-name">' + escHtml(p.name || '(без назви)') + '</span>'
                + (p.product_article ? '<span class="ai-sdrop-sub">' + escHtml(p.product_article) + '</span>' : '');
            item.addEventListener('click', function() {
                document.getElementById('aiSearchTyper').value = '';
                closeSearchDrop();
                openProdModal(parseInt(p.product_id, 10), p.name || '', p.product_article || '');
            });
            dd.appendChild(item);
        });
    }

    dd.style.display = '';
}

function closeSearchDrop() {
    var dd = document.getElementById('aiSearchDropdown');
    dd.style.display = 'none';
    dd.innerHTML = '';
}

// ═══════════════════════════════════════════════════════════════════════════
// Category instruction: load all sites
// ═══════════════════════════════════════════════════════════════════════════
var SITES_LIST = <?php
    $sitesList = array();
    foreach ($sites as $s) {
        $sitesList[] = array('site_id' => (int)$s['site_id'], 'badge' => $s['badge'], 'name' => $s['name']);
    }
    echo json_encode($sitesList, JSON_UNESCAPED_UNICODE);
?>;

function loadAllSiteCategoryInstrs() {
    SITES_LIST.forEach(function(site) {
        loadCategoryInstr(site.site_id);
    });
}

function loadCategoryInstr(siteId) {
    var ta = document.getElementById('catInstrTA_' + siteId);
    if (!ta) { return; }
    fetch('/ai/api/get_instruction?entity_type=category'
        + '&entity_id=' + AI_STATE.selectedCatId
        + '&site_id='   + siteId
        + '&use_case='  + AI_STATE.instrUseCase
    )
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) { ta.value = d.data.instruction || ''; }
    })
    .catch(function() {});
}

function saveCategoryInstr(siteId) {
    if (!AI_STATE.selectedCatId) { return; }
    var ta       = document.getElementById('catInstrTA_' + siteId);
    var statusEl = document.getElementById('catInstrStatus_' + siteId);
    if (!ta || !statusEl) { return; }

    statusEl.className = 'instr-status';
    statusEl.textContent = 'Збереження…';

    var body = 'entity_type=category'
        + '&entity_id='   + AI_STATE.selectedCatId
        + '&site_id='     + siteId
        + '&use_case='    + encodeURIComponent(AI_STATE.instrUseCase)
        + '&instruction=' + encodeURIComponent(ta.value);

    fetch('/ai/api/save_instruction', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            statusEl.className = 'instr-status ok';
            statusEl.textContent = 'Збережено';
            setTimeout(function() { statusEl.textContent = ''; }, 2500);
        } else {
            statusEl.className = 'instr-status err';
            statusEl.textContent = d.error || 'Помилка';
        }
    })
    .catch(function() {
        statusEl.className = 'instr-status err';
        statusEl.textContent = 'Мережева помилка';
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// Products in category
// ═══════════════════════════════════════════════════════════════════════════
function loadProductsInCategory(catId) {
    var wrap = document.getElementById('productsTableWrap');
    var count = document.getElementById('productsCount');
    wrap.innerHTML = '<div class="products-loading">Завантаження…</div>';
    count.textContent = '';

    fetch('/ai/api/get_products_in_category?category_id=' + catId + '&language_id=2')
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) {
            wrap.innerHTML = '<div class="products-empty text-muted">Помилка завантаження</div>';
            return;
        }
        var products = d.products || [];
        if (products.length === 0) {
            wrap.innerHTML = '<div class="products-empty">Товарів у цій категорії не знайдено</div>';
            return;
        }
        count.textContent = '(' + products.length + ')';
        renderProductsTable(products, wrap);
    })
    .catch(function() {
        wrap.innerHTML = '<div class="products-empty text-muted">Помилка завантаження</div>';
    });
}

function renderProductsTable(products, container) {
    var tbl = document.createElement('table');
    tbl.className = 'crm-table products-tbl';
    tbl.innerHTML = '<thead><tr><th>Артикул</th><th>Назва</th></tr></thead>';
    var tbody = document.createElement('tbody');
    products.forEach(function(p) {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td class="nowrap text-muted">' + escHtml(p.product_article || '') + '</td>'
                     + '<td>' + escHtml(p.name || '(без назви)') + '</td>';
        tr.title = 'Подвійний клік — редагувати інструкцію товару';
        tr.addEventListener('dblclick', function() {
            openProdModal(parseInt(p.product_id, 10), p.name || '', p.product_article || '');
        });
        tbody.appendChild(tr);
    });
    tbl.appendChild(tbody);
    container.innerHTML = '';
    container.appendChild(tbl);
}

// ═══════════════════════════════════════════════════════════════════════════
// Product instruction modal
// ═══════════════════════════════════════════════════════════════════════════
function openProdModal(productId, name, article) {
    AI_STATE.prodModalProductId = productId;
    AI_STATE.prodModalUseCase   = 'content';
    AI_STATE.prodModalSiteId    = SITES_LIST.length > 0 ? SITES_LIST[0].site_id : 0;

    document.getElementById('prodModalName').textContent    = name || '(без назви)';
    document.getElementById('prodModalArticle').textContent = article ? 'Арт. ' + article : '';
    document.getElementById('prodInstrStatus').textContent  = '';
    document.getElementById('prodInstrTA').value = '';

    // Reset use_case tabs
    document.querySelectorAll('#prodModalUcTabs .sub-tab-btn').forEach(function(b) {
        b.classList.toggle('active', b.getAttribute('data-produc') === 'content');
    });
    // Reset site tabs
    document.querySelectorAll('#prodModalSiteTabs .sub-tab-btn').forEach(function(b, idx) {
        b.classList.toggle('active', idx === 0);
    });

    document.getElementById('prodModal').style.display = 'flex';
    loadProductInstr();
}

function closeProdModal() {
    document.getElementById('prodModal').style.display = 'none';
}

document.querySelectorAll('#prodModalUcTabs .sub-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#prodModalUcTabs .sub-tab-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        AI_STATE.prodModalUseCase = this.getAttribute('data-produc');
        loadProductInstr();
    });
});

document.querySelectorAll('#prodModalSiteTabs .sub-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#prodModalSiteTabs .sub-tab-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        AI_STATE.prodModalSiteId = parseInt(this.getAttribute('data-prodsiteid'), 10);
        loadProductInstr();
    });
});

function loadProductInstr() {
    var ta = document.getElementById('prodInstrTA');
    ta.value = '';
    fetch('/ai/api/get_instruction?entity_type=product'
        + '&entity_id=' + AI_STATE.prodModalProductId
        + '&site_id='   + AI_STATE.prodModalSiteId
        + '&use_case='  + AI_STATE.prodModalUseCase
    )
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) { ta.value = d.data.instruction || ''; }
    })
    .catch(function() {});
}

function saveProductInstr() {
    var ta       = document.getElementById('prodInstrTA');
    var statusEl = document.getElementById('prodInstrStatus');

    statusEl.className = 'instr-status';
    statusEl.textContent = 'Збереження…';

    var body = 'entity_type=product'
        + '&entity_id='   + AI_STATE.prodModalProductId
        + '&site_id='     + AI_STATE.prodModalSiteId
        + '&use_case='    + encodeURIComponent(AI_STATE.prodModalUseCase)
        + '&instruction=' + encodeURIComponent(ta.value);

    fetch('/ai/api/save_instruction', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            statusEl.className = 'instr-status ok';
            statusEl.textContent = 'Збережено';
            setTimeout(function() { statusEl.textContent = ''; }, 2500);
        } else {
            statusEl.className = 'instr-status err';
            statusEl.textContent = d.error || 'Помилка';
        }
    })
    .catch(function() {
        statusEl.className = 'instr-status err';
        statusEl.textContent = 'Мережева помилка';
    });
}

// Close modal on overlay click
document.getElementById('prodModal').addEventListener('click', function(e) {
    if (e.target === this) { closeProdModal(); }
});

// ═══════════════════════════════════════════════════════════════════════════
// Log tab
// ═══════════════════════════════════════════════════════════════════════════
function loadLog(offset) {
    AI_STATE.logOffset = offset;
    var entityType = document.getElementById('logFilterType').value;
    var siteId     = document.getElementById('logFilterSite').value;
    var useCase    = document.getElementById('logFilterUc').value;
    var status     = document.getElementById('logFilterStatus').value;
    var dateFrom   = document.getElementById('logFilterDateFrom').value;
    var dateTo     = document.getElementById('logFilterDateTo').value;

    var url = '/ai/api/get_log?offset=' + offset + '&limit=' + AI_STATE.logLimit
        + (entityType ? '&entity_type=' + encodeURIComponent(entityType) : '')
        + (siteId > 0 ? '&site_id=' + siteId : '')
        + (useCase  ? '&use_case=' + encodeURIComponent(useCase)  : '')
        + (status   ? '&status='   + encodeURIComponent(status)   : '')
        + (dateFrom ? '&date_from=' + encodeURIComponent(dateFrom) : '')
        + (dateTo   ? '&date_to='   + encodeURIComponent(dateTo)   : '');

    var tbody = document.getElementById('logTableBody');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:16px">Завантаження…</td></tr>';
    document.getElementById('logPagination').style.display = 'none';

    fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:16px">Помилка</td></tr>';
            return;
        }
        AI_STATE.logTotal = d.total;
        renderLogTable(d.rows);
        renderLogPagination();
    })
    .catch(function() {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:16px">Помилка</td></tr>';
    });
}

function renderLogTable(rows) {
    var tbody = document.getElementById('logTableBody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:24px">Записів не знайдено</td></tr>';
        clearLogSelection();
        return;
    }
    var html = '';
    rows.forEach(function(row) {
        var typeBadge = row.entity_type === 'product'
            ? '<span class="badge badge-blue">товар</span>'
            : '<span class="badge badge-orange">категорія</span>';
        var statusBadge = row.status === 'generated'
            ? '<span class="badge badge-green">OK</span>'
            : '<span class="badge badge-red">err</span>';
        var siteBadge = row.site_badge
            ? '<span class="badge">' + escHtml(row.site_badge) + '</span>'
            : '';
        var entityInfo = (row.entity_name ? escHtml(row.entity_name) : '') +
            ' <span class="text-muted fs-12">#' + row.entity_id + '</span>';
        html += '<tr style="cursor:pointer" data-log-id="' + row.log_id + '">'
            + '<td onclick="event.stopPropagation()"><input type="checkbox" class="log-cb log-row-cb" data-id="' + row.log_id + '" onchange="updateLogBulkToolbar()"></td>'
            + '<td class="text-muted fs-12" onclick="openLogDetail(' + row.log_id + ')">' + row.log_id + '</td>'
            + '<td onclick="openLogDetail(' + row.log_id + ')">' + typeBadge + '</td>'
            + '<td onclick="openLogDetail(' + row.log_id + ')">' + entityInfo + '</td>'
            + '<td onclick="openLogDetail(' + row.log_id + ')">' + siteBadge + '</td>'
            + '<td class="text-muted fs-12" onclick="openLogDetail(' + row.log_id + ')">' + escHtml(row.use_case) + '</td>'
            + '<td onclick="openLogDetail(' + row.log_id + ')">' + statusBadge + '</td>'
            + '<td class="text-muted fs-12 nowrap" onclick="openLogDetail(' + row.log_id + ')">' + escHtml(row.created_at || '') + '</td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
    // Reset select-all checkbox
    var selAll = document.getElementById('logSelectAll');
    if (selAll) selAll.checked = false;
}

function renderLogPagination() {
    var pg    = document.getElementById('logPagination');
    var total = AI_STATE.logTotal;
    var limit = AI_STATE.logLimit;
    if (total <= limit) { pg.style.display = 'none'; return; }

    var offset     = AI_STATE.logOffset;
    var totalPages = Math.ceil(total / limit);
    var curPage    = Math.floor(offset / limit) + 1;
    var html = '';

    // Prev
    if (curPage > 1) {
        html += '<button class="page-btn" onclick="loadLog(' + ((curPage - 2) * limit) + ')">&laquo;</button>';
    } else {
        html += '<button class="page-btn" disabled>&laquo;</button>';
    }

    // Page numbers with ellipsis
    var pages = [];
    for (var p = 1; p <= totalPages; p++) {
        if (p === 1 || p === totalPages || (p >= curPage - 2 && p <= curPage + 2)) {
            pages.push(p);
        }
    }
    var prev = 0;
    for (var i = 0; i < pages.length; i++) {
        var p = pages[i];
        if (prev && p - prev > 1) {
            html += '<span class="page-btn page-ellipsis">…</span>';
        }
        if (p === curPage) {
            html += '<button class="page-btn active">' + p + '</button>';
        } else {
            html += '<button class="page-btn" onclick="loadLog(' + ((p - 1) * limit) + ')">' + p + '</button>';
        }
        prev = p;
    }

    // Next
    if (curPage < totalPages) {
        html += '<button class="page-btn" onclick="loadLog(' + (curPage * limit) + ')">&raquo;</button>';
    } else {
        html += '<button class="page-btn" disabled>&raquo;</button>';
    }

    pg.innerHTML = html;
    pg.style.display = 'flex';
}

function resetLogFilters() {
    document.getElementById('logFilterType').value   = '';
    document.getElementById('logFilterSite').value   = '0';
    document.getElementById('logFilterUc').value     = '';
    document.getElementById('logFilterStatus').value = '';
    document.getElementById('logFilterDateFrom').value = '';
    document.getElementById('logFilterDateTo').value   = '';
    loadLog(0);
}

// ── Log detail modal ──────────────────────────────────────────────────────
function openLogDetail(logId) {
    fetch('/ai/api/get_log_entry?log_id=' + logId)
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) { return; }
        var row = d.row;
        document.getElementById('logModalTitle').textContent = 'Генерація #' + row.log_id;

        var meta = '';
        meta += '<span class="meta-item"><strong>Тип:</strong> ' + escHtml(row.entity_type) + ' #' + row.entity_id + '</span>';
        if (row.site_badge) {
            meta += '<span class="meta-item"><strong>Сайт:</strong> ' + escHtml(row.site_badge) + '</span>';
        }
        meta += '<span class="meta-item"><strong>Use case:</strong> ' + escHtml(row.use_case) + '</span>';
        meta += '<span class="meta-item"><strong>Статус:</strong> ' + escHtml(row.status) + '</span>';
        meta += '<span class="meta-item"><strong>Дата:</strong> ' + escHtml(row.created_at || '') + '</span>';
        document.getElementById('logModalMeta').innerHTML = meta;

        // Split prompt into system + user parts
        var promptFull = row.prompt || '';
        var sysPrompt  = '';
        var userPrompt = '';
        var splitIdx   = promptFull.indexOf('=== USER ===');
        if (splitIdx !== -1) {
            sysPrompt  = promptFull.substring(0, splitIdx).replace(/^=== SYSTEM ===\s*/i, '').trim();
            userPrompt = promptFull.substring(splitIdx).replace(/^=== USER ===\s*/i, '').trim();
        } else {
            sysPrompt = promptFull;
        }

        document.getElementById('logModalSystem').textContent   = sysPrompt;
        document.getElementById('logModalUser').textContent     = userPrompt;
        document.getElementById('logModalResponse').textContent = row.response_raw || '';

        document.getElementById('logModal').style.display = 'flex';
    })
    .catch(function() {});
}

function closeLogModal() {
    document.getElementById('logModal').style.display = 'none';
}

document.getElementById('logModal').addEventListener('click', function(e) {
    if (e.target === this) { closeLogModal(); }
});

// ── Log bulk selection ────────────────────────────────────────────────────
document.getElementById('logSelectAll').addEventListener('change', function() {
    var checked = this.checked;
    document.querySelectorAll('.log-row-cb').forEach(function(cb) { cb.checked = checked; });
    updateLogBulkToolbar();
});

function updateLogBulkToolbar() {
    var cbs  = document.querySelectorAll('.log-row-cb:checked');
    var all  = document.querySelectorAll('.log-row-cb');
    var toolbar = document.getElementById('logBulkToolbar');
    var selAll  = document.getElementById('logSelectAll');
    document.getElementById('logBulkCount').textContent = 'Вибрано ' + cbs.length;
    toolbar.classList.toggle('visible', cbs.length > 0);
    selAll.checked = all.length > 0 && cbs.length === all.length;
    selAll.indeterminate = cbs.length > 0 && cbs.length < all.length;
}

function clearLogSelection() {
    document.querySelectorAll('.log-row-cb').forEach(function(cb) { cb.checked = false; });
    var selAll = document.getElementById('logSelectAll');
    if (selAll) { selAll.checked = false; selAll.indeterminate = false; }
    document.getElementById('logBulkToolbar').classList.remove('visible');
}

function deleteSelectedLogs() {
    var ids = [];
    document.querySelectorAll('.log-row-cb:checked').forEach(function(cb) {
        ids.push(cb.getAttribute('data-id'));
    });
    if (!ids.length) return;
    if (!confirm('Видалити ' + ids.length + ' запис(ів) логу?')) return;

    fetch('/ai/api/delete_logs', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ids=' + encodeURIComponent(ids.join(','))
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            showToast('Видалено ' + d.deleted + ' записів');
            loadLog(AI_STATE.logOffset);
        } else {
            showToast('Помилка: ' + (d.error || 'невідома'));
        }
    })
    .catch(function() { showToast('Помилка мережі'); });
}

function copyPreText(elId) {
    var el = document.getElementById(elId);
    if (!el) return;
    var text = el.tagName === 'TEXTAREA' ? el.value : el.textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() { showToast('Скопійовано'); }, function() { showToast('Помилка копіювання'); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); showToast('Скопійовано'); } catch(e) { showToast('Помилка копіювання'); }
        document.body.removeChild(ta);
    }
}

function savePreText(elId, filename) {
    var el = document.getElementById(elId);
    if (!el) return;
    var text = el.tagName === 'TEXTAREA' ? el.value : el.textContent;
    var blob = new Blob([text], {type: 'text/plain;charset=utf-8'});
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// ═══════════════════════════════════════════════════════════════════════════
// Settings tab
// ═══════════════════════════════════════════════════════════════════════════
function saveSettings(useCase, siteId) {
    var key     = useCase + '_' + siteId;
    var instr   = document.getElementById('instr-'  + key).value;
    var model   = document.getElementById('model-'  + key).value;
    var temp    = document.getElementById('temp-'   + key).value;
    var maxTok  = document.getElementById('maxtok-' + key).value;
    var statusEl = document.getElementById('status-' + key);

    statusEl.className = 'save-status';
    statusEl.textContent = 'Збереження…';

    var body = 'entity_type=site'
        + '&entity_id='    + encodeURIComponent(siteId)
        + '&use_case='     + encodeURIComponent(useCase)
        + '&instruction='  + encodeURIComponent(instr)
        + '&model='        + encodeURIComponent(model)
        + '&temperature='  + encodeURIComponent(temp)
        + '&max_tokens='   + encodeURIComponent(maxTok);

    fetch('/ai/api/save_settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            statusEl.className = 'save-status ok';
            statusEl.textContent = 'Збережено';
            setTimeout(function() { statusEl.textContent = ''; }, 2500);
        } else {
            statusEl.className = 'save-status err';
            statusEl.textContent = d.error || 'Помилка';
        }
    })
    .catch(function() {
        statusEl.className = 'save-status err';
        statusEl.textContent = 'Мережева помилка';
    });
}

// Chat AI settings
function saveChatSettings(siteId) {
    var instr    = document.getElementById('chatAiInstruction').value;
    var model    = document.getElementById('chatAiModel').value;
    var temp     = document.getElementById('chatAiTemp').value;
    var maxTok   = document.getElementById('chatAiMaxTok').value;
    var statusEl = document.getElementById('status-chat');

    statusEl.className = 'save-status';
    statusEl.textContent = 'Збереження…';

    var body = 'entity_type=site'
        + '&entity_id='    + encodeURIComponent(siteId)
        + '&use_case=chat'
        + '&instruction='  + encodeURIComponent(instr)
        + '&model='        + encodeURIComponent(model)
        + '&temperature='  + encodeURIComponent(temp)
        + '&max_tokens='   + encodeURIComponent(maxTok);

    fetch('/ai/api/save_settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            statusEl.className = 'save-status ok';
            statusEl.textContent = 'Збережено';
            setTimeout(function() { statusEl.textContent = ''; }, 2500);
        } else {
            statusEl.className = 'save-status err';
            statusEl.textContent = d.error || 'Помилка';
        }
    })
    .catch(function() {
        statusEl.className = 'save-status err';
        statusEl.textContent = 'Мережева помилка';
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
