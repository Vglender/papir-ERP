<?php
$title     = 'Зв\'язок категорій';
$activeNav = 'catalog';
$subNav    = 'categories';
require_once __DIR__ . '/../../shared/layout.php';

function cmUrl($siteCode, $filter, $search, $page, $selected) {
    $p = array('site=' . urlencode($siteCode));
    if ($filter && $filter !== 'all') $p[] = 'filter=' . urlencode($filter);
    if ($search !== '')               $p[] = 'q='      . urlencode($search);
    if ($page > 1)                    $p[] = 'page='   . (int)$page;
    if ($selected > 0)                $p[] = 'selected=' . (int)$selected;
    return '/category-mapping?' . implode('&', $p);
}

// Mapped counts for filter badges
$totalCount    = count($siteCats);
$mappedCount   = count(array_filter(array_keys($siteCats), function($id) use ($mappings) { return isset($mappings[$id]); }));
$unmappedCount = $totalCount - $mappedCount;
?>
<style>
.cm-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 20px;
    align-items: start;
}
@media (max-width: 1000px) {
    .cm-layout { grid-template-columns: 1fr; }
    .cm-panel  { position: static !important; }
}
.cm-panel {
    position: sticky;
    top: var(--sticky-top);
    max-height: calc(100vh - var(--sticky-top));
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #cfd8e3 transparent;
}
.cm-panel::-webkit-scrollbar       { width: 6px; }
.cm-panel::-webkit-scrollbar-thumb { background: #cfd8e3; border-radius: 6px; }

.site-tabs { display: flex; gap: 4px; }
.site-tab {
    padding: 6px 18px; border-radius: var(--radius) var(--radius) 0 0;
    background: #eef2f7; color: var(--text-muted); font-size: 13px;
    text-decoration: none; font-weight: 500;
    border: 1px solid var(--border); border-bottom: none;
}
.site-tab.active { background: #fff; color: var(--blue); }

.filter-tabs { display: flex; gap: 4px; }
.filter-tab {
    padding: 4px 12px; border-radius: 20px; font-size: 12px;
    text-decoration: none; color: var(--text-muted); background: #f0f4f8;
}
.filter-tab.active { background: var(--blue); color: #fff; }
.filter-tab .cnt { font-size: 11px; opacity: .8; margin-left: 3px; }

.cm-cat-name   { font-weight: 500; }
.cm-cat-parent { font-size: 11px; color: var(--text-faint); margin-top: 2px; }

.panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.panel-head h2 { margin: 0; font-size: 15px; font-weight: 600; }
.panel-close { background: none; border: none; cursor: pointer; color: var(--text-faint); font-size: 20px; line-height: 1; padding: 0; }
.panel-close:hover { color: var(--text); }

.panel-empty { display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-height: 200px; color: var(--text-faint); text-align: center; padding: 32px; }
.panel-empty-icon { font-size: 36px; margin-bottom: 12px; opacity: .4; }

.current-mapping { padding: 9px 12px; background: #f0f9f0; border: 1px solid #c8e6c9;
    border-radius: var(--radius); margin-bottom: 12px; font-size: 13px; }
.current-mapping strong { color: #2e7d32; }

.papir-tree-wrap {
    border: 1px solid var(--border); border-radius: var(--radius); background: #fff;
    height: 380px; display: flex; flex-direction: column; overflow: hidden;
}

.panel-actions { display: flex; gap: 8px; padding-top: 12px;
    border-top: 1px solid var(--border); margin-top: 10px; }

.crm-table tbody tr.row-selected td { background: var(--blue-bg) !important; }
.badge-mapped   { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px;
    background: #e8f5e9; color: #388e3c; font-weight: 500; max-width: 180px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
.badge-unmapped { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px;
    background: #f5f5f5; color: #999; }
</style>

<div class="page-wrap-sm">

    <div class="breadcrumb">
        <a href="/catalog">Каталог</a> / Зв'язок категорій
    </div>

    <div class="site-tabs">
        <?php foreach ($sites as $s) { ?>
            <a href="<?php echo ViewHelper::h(cmUrl($s['code'], 'all', '', 1, 0)); ?>"
               class="site-tab <?php echo $s['code'] === $siteCode ? 'active' : ''; ?>">
                <?php echo ViewHelper::h($s['name']); ?>
            </a>
        <?php } ?>
    </div>

    <div class="card" style="border-top-left-radius:0">

        <div class="toolbar" style="border-top:none;padding-top:0">
            <div class="filter-tabs">
                <?php
                $filterDefs = array(
                    'all'      => array('label' => 'Всі',          'cnt' => $totalCount),
                    'mapped'   => array('label' => 'Зіставлені',   'cnt' => $mappedCount),
                    'unmapped' => array('label' => 'Незіставлені', 'cnt' => $unmappedCount),
                );
                foreach ($filterDefs as $fk => $fd) {
                    echo '<a href="' . ViewHelper::h(cmUrl($siteCode, $fk, $search, 1, 0)) . '"'
                       . ' class="filter-tab ' . ($filter === $fk ? 'active' : '') . '">'
                       . ViewHelper::h($fd['label'])
                       . '<span class="cnt">(' . $fd['cnt'] . ')</span></a>';
                }
                ?>
            </div>
            <form method="get" action="/category-mapping"
                  style="display:flex;gap:8px;align-items:center;margin-left:auto">
                <input type="hidden" name="site"   value="<?php echo ViewHelper::h($siteCode); ?>">
                <input type="hidden" name="filter" value="<?php echo ViewHelper::h($filter); ?>">
                <input class="search-input" type="text" name="q"
                       value="<?php echo ViewHelper::h($search); ?>"
                       placeholder="Пошук по назві категорії <?php echo ViewHelper::h($currentSite ? $currentSite['name'] : ''); ?>..."
                       autocomplete="off">
                <?php if ($search !== '') { ?>
                    <a href="<?php echo ViewHelper::h(cmUrl($siteCode, $filter, '', 1, 0)); ?>"
                       style="font-size:13px;color:var(--text-faint);text-decoration:none">✕</a>
                <?php } ?>
            </form>
        </div>

        <div class="cm-layout">

            <!-- ── Категорії сайту ───────────────────────── -->
            <div>
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th><?php echo ViewHelper::h($currentSite ? $currentSite['name'] : 'Сайт'); ?> (категорія)</th>
                            <th style="width:200px">Papir (прив'язка)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pageRows as $sc) {
                        $scId       = (int)$sc['category_id'];
                        $isSelected = ($scId === $selected);
                        $rowUrl     = cmUrl($siteCode, $filter, $search, $page, $scId);
                        $papirCatId = isset($mappings[$scId]) ? $mappings[$scId] : 0;
                        $papirCat   = $papirCatId ? (isset($papirCats[$papirCatId]) ? $papirCats[$papirCatId] : null) : null;
                        $parentName = $sc['parent_id'] && isset($siteCats[(int)$sc['parent_id']])
                                    ? $siteCats[(int)$sc['parent_id']]['name'] : '';
                    ?>
                        <tr class="clickable <?php echo $isSelected ? 'row-selected' : ''; ?>"
                            onclick="window.location='<?php echo ViewHelper::h($rowUrl); ?>'">
                            <td>
                                <div class="cm-cat-name"><?php echo ViewHelper::h($sc['name']); ?></div>
                                <?php if ($parentName) { ?>
                                    <div class="cm-cat-parent">↳ <?php echo ViewHelper::h($parentName); ?></div>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($papirCat) { ?>
                                    <span class="badge-mapped"
                                          id="badge-<?php echo $scId; ?>"
                                          title="<?php echo ViewHelper::h($papirCat['name']); ?>">
                                        <?php echo ViewHelper::h($papirCat['name']); ?>
                                    </span>
                                    <?php if (!empty($papirCat['parent_name'])) { ?>
                                        <div class="cm-cat-parent">↳ <?php echo ViewHelper::h($papirCat['parent_name']); ?></div>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="badge-unmapped" id="badge-<?php echo $scId; ?>">—</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($pageRows)) { ?>
                        <tr><td colspan="2" style="text-align:center;padding:24px;color:var(--text-faint)">Нічого не знайдено</td></tr>
                    <?php } ?>
                    </tbody>
                </table>

                <?php if ($pages > 1) { ?>
                <div class="pagination">
                    <?php
                    echo $page > 1
                        ? '<a href="' . ViewHelper::h(cmUrl($siteCode, $filter, $search, $page-1, 0)) . '">&#8592;</a>'
                        : '<span style="opacity:.3">&#8592;</span>';
                    for ($i = 1; $i <= $pages; $i++) {
                        if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
                            echo $i === $page
                                ? '<span class="cur">' . $i . '</span>'
                                : '<a href="' . ViewHelper::h(cmUrl($siteCode, $filter, $search, $i, 0)) . '">' . $i . '</a>';
                        } elseif (abs($i - $page) === 3) {
                            echo '<span class="dots">…</span>';
                        }
                    }
                    echo $page < $pages
                        ? '<a href="' . ViewHelper::h(cmUrl($siteCode, $filter, $search, $page+1, 0)) . '">&#8594;</a>'
                        : '<span style="opacity:.3">&#8594;</span>';
                    ?>
                </div>
                <?php } ?>
            </div>

            <!-- ── Панель Papir ───────────────────────────── -->
            <div class="cm-panel">
                <div class="card">
                <?php if ($panelSiteCat !== null) {
                    $panelParentName = $panelSiteCat['parent_id'] && isset($siteCats[(int)$panelSiteCat['parent_id']])
                                     ? $siteCats[(int)$panelSiteCat['parent_id']]['name'] : '';
                    $panelPapirCat   = $panelPapirCatId ? (isset($papirCats[$panelPapirCatId]) ? $papirCats[$panelPapirCatId] : null) : null;
                ?>
                    <div class="panel-head">
                        <h2><?php echo ViewHelper::h($panelSiteCat['name']); ?></h2>
                        <a href="<?php echo ViewHelper::h(cmUrl($siteCode, $filter, $search, $page, 0)); ?>"
                           class="panel-close">&#10005;</a>
                    </div>
                    <?php if ($panelParentName) { ?>
                        <div style="font-size:12px;color:var(--text-faint);margin-bottom:12px">
                            ↳ <?php echo ViewHelper::h($panelParentName); ?>
                        </div>
                    <?php } ?>

                    <div class="current-mapping" id="currentMapping"
                         style="<?php echo $panelPapirCat ? '' : 'display:none'; ?>">
                        Papir: <strong id="currentMappingName"><?php echo ViewHelper::h($panelPapirCat ? $panelPapirCat['name'] : ''); ?></strong>
                    </div>

                    <div class="papir-tree-wrap">
                        <div id="papirTreeContainer" style="flex:1;min-height:0;display:flex;flex-direction:column"></div>
                    </div>

                    <div id="panelError" class="modal-error" style="display:none;margin-top:8px"></div>

                    <div class="panel-actions">
                        <button type="button" class="btn btn-primary" id="btnSaveMapping"
                                style="flex:1" disabled>Зберегти</button>
                        <button type="button" class="btn btn-ghost" id="btnClearMapping"
                                <?php echo $panelPapirCatId ? '' : 'disabled'; ?>>Очистити</button>
                    </div>

                <?php } else { ?>
                    <div class="panel-empty">
                        <div class="panel-empty-icon">&#128279;</div>
                        <p>Оберіть категорію<br><strong><?php echo ViewHelper::h($currentSite ? $currentSite['name'] : ''); ?></strong><br>щоб зіставити з Papir</p>
                    </div>
                <?php } ?>
                </div>
            </div>

        </div>
    </div>

</div>

<div class="toast" id="toast"></div>

<script src="/modules/shared/category-tree.js?v=<?php echo filemtime(__DIR__ . '/../../shared/category-tree.js'); ?>"></script>
<script>
var SITE_ID          = <?php echo $siteId; ?>;
var SITE_CATEGORY_ID = <?php echo $selected; ?>;
var CURRENT_PAPIR_CAT_ID   = <?php echo $panelPapirCatId; ?>;
var CURRENT_PAPIR_CAT_NAME = <?php echo $panelPapirCat ? json_encode($panelPapirCat['name']) : 'null'; ?>;

// Papir categories data (from PHP, no AJAX needed)
var PAPIR_CATS = <?php
    $jsArr = array();
    foreach ($papirCats as $pc) {
        $jsArr[] = array(
            'id'          => (int)$pc['category_id'],
            'name'        => (string)$pc['name'],
            'parent_id'   => (int)$pc['parent_id'],
            'parent_name' => (string)($pc['parent_name'] ? $pc['parent_name'] : ''),
        );
    }
    echo json_encode($jsArr);
?>;

var selectedPapirCatId = CURRENT_PAPIR_CAT_ID;
var papirTree = null;

function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 1800);
}

// Init CategoryTree in right panel
(function() {
    var wrap = document.getElementById('papirTreeContainer');
    if (!wrap) return;
    papirTree = new CategoryTree({
        container:  wrap,
        categories: PAPIR_CATS,
        selectedId: CURRENT_PAPIR_CAT_ID || 0,
        searchable: true,
        onSelect: function(id) {
            selectedPapirCatId = id;
            document.getElementById('btnSaveMapping').disabled = false;
        }
    });
})();

// Save
(function() {
    var btn = document.getElementById('btnSaveMapping');
    if (!btn) return;
    btn.addEventListener('click', function() {
        if (!selectedPapirCatId) return;
        btn.disabled = true;
        var err = document.getElementById('panelError');

        fetch('/catalog/api/save_category_mapping', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: 'category_id='      + encodeURIComponent(selectedPapirCatId)
                + '&site_id='         + encodeURIComponent(SITE_ID)
                + '&site_category_id='+ encodeURIComponent(SITE_CATEGORY_ID)
                + '&action=save'
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.disabled = false;
            if (!d.ok) {
                if (err) { err.textContent = d.error || 'Помилка'; err.style.display = 'block'; }
                return;
            }
            if (err) err.style.display = 'none';
            showToast('Збережено');
            CURRENT_PAPIR_CAT_ID = selectedPapirCatId;

            var sel = papirTree ? papirTree.getSelected() : null;
            var catName = sel ? sel.name : ('#' + selectedPapirCatId);
            CURRENT_PAPIR_CAT_NAME = catName;

            var mapping = document.getElementById('currentMapping');
            var mname   = document.getElementById('currentMappingName');
            if (mapping) mapping.style.display = 'block';
            if (mname)   mname.textContent = catName;

            // Update badge in table row
            var badge = document.getElementById('badge-' + SITE_CATEGORY_ID);
            if (badge) { badge.className = 'badge-mapped'; badge.textContent = catName; }

            document.getElementById('btnClearMapping').disabled = false;
        })
        .catch(function() {
            btn.disabled = false;
            if (err) { err.textContent = 'Помилка мережі'; err.style.display = 'block'; }
        });
    });
})();

// Clear
(function() {
    var btn = document.getElementById('btnClearMapping');
    if (!btn) return;
    btn.addEventListener('click', function() {
        if (!confirm('Видалити зіставлення?')) return;
        btn.disabled = true;

        fetch('/catalog/api/save_category_mapping', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: 'category_id='      + encodeURIComponent(CURRENT_PAPIR_CAT_ID)
                + '&site_id='         + encodeURIComponent(SITE_ID)
                + '&action=clear'
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) { btn.disabled = false; return; }
            showToast('Очищено');
            selectedPapirCatId = 0;
            CURRENT_PAPIR_CAT_ID = 0;
            CURRENT_PAPIR_CAT_NAME = null;

            var mapping = document.getElementById('currentMapping');
            if (mapping) mapping.style.display = 'none';

            var badge = document.getElementById('badge-' + SITE_CATEGORY_ID);
            if (badge) { badge.className = 'badge-unmapped'; badge.textContent = '—'; }

            if (papirTree) papirTree.setSelected(0);
            document.getElementById('btnSaveMapping').disabled = true;
        })
        .catch(function() { btn.disabled = false; });
    });
})();

</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
