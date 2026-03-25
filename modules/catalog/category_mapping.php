<?php
require_once __DIR__ . '/catalog_bootstrap.php';

// ── Sites ────────────────────────────────────────────────────────────────────
$sitesRes = Database::fetchAll('Papir', "SELECT * FROM sites WHERE status=1 ORDER BY sort_order");
$sites    = ($sitesRes['ok'] && !empty($sitesRes['rows'])) ? $sitesRes['rows'] : array();

$siteCode    = isset($_GET['site']) ? trim($_GET['site']) : '';
$currentSite = null;
foreach ($sites as $s) {
    if ($s['code'] === $siteCode) { $currentSite = $s; break; }
}
if ($currentSite === null && !empty($sites)) {
    $currentSite = $sites[0];
    $siteCode    = $currentSite['code'];
}

$siteId  = $currentSite ? (int)$currentSite['site_id'] : 0;
$dbAlias = $currentSite ? $currentSite['db_alias']     : '';
$langId  = $currentSite ? (int)$currentSite['lang_id'] : 2;

// ── Load ALL site categories (flat list with parent info) ────────────────────
$siteCats = array(); // category_id => row
if ($dbAlias) {
    $scRes = Database::fetchAll($dbAlias,
        "SELECT oc.category_id, ocd.name, oc.parent_id, oc.sort_order
         FROM oc_category oc
         LEFT JOIN oc_category_description ocd
               ON ocd.category_id = oc.category_id AND ocd.language_id = {$langId}
         WHERE oc.status = 1
         ORDER BY oc.parent_id, oc.sort_order, oc.category_id"
    );
    if ($scRes['ok'] && !empty($scRes['rows'])) {
        foreach ($scRes['rows'] as $r) {
            $siteCats[(int)$r['category_id']] = $r;
        }
    }
}

// ── Load mappings for this site (site_category_id → papir category_id) ───────
$mappings = array(); // site_category_id => category_id (Papir)
if ($siteId) {
    $mRes = Database::fetchAll('Papir',
        "SELECT site_category_id, category_id FROM category_site_mapping WHERE site_id={$siteId}"
    );
    if ($mRes['ok'] && !empty($mRes['rows'])) {
        foreach ($mRes['rows'] as $r) {
            $mappings[(int)$r['site_category_id']] = (int)$r['category_id'];
        }
    }
}

// ── Load Papir categories for reference panel ────────────────────────────────
$papirCats = array(); // category_id => row
$pcRes = Database::fetchAll('Papir',
    "SELECT c.category_id, cd.name, c.parent_id, p.name AS parent_name
     FROM categoria c
     JOIN category_description cd ON cd.category_id=c.category_id AND cd.language_id=2
     LEFT JOIN category_description p ON p.category_id=c.parent_id AND p.language_id=2
     ORDER BY c.parent_id, c.sort_order, c.category_id"
);
if ($pcRes['ok'] && !empty($pcRes['rows'])) {
    foreach ($pcRes['rows'] as $r) {
        $papirCats[(int)$r['category_id']] = $r;
    }
}

// ── Filter + search (applied to site categories) ─────────────────────────────
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['q'])      ? trim($_GET['q']) : '';

$filtered = array();
foreach ($siteCats as $scId => $sc) {
    $isMapped = isset($mappings[$scId]);
    if ($filter === 'mapped'   && !$isMapped) continue;
    if ($filter === 'unmapped' &&  $isMapped) continue;
    if ($search !== '') {
        $tokens = preg_split('/\s+/', mb_strtolower($search, 'UTF-8'));
        $name   = mb_strtolower((string)$sc['name'], 'UTF-8');
        $match  = true;
        foreach ($tokens as $tok) {
            if ($tok !== '' && mb_strpos($name, $tok) === false) { $match = false; break; }
        }
        if (!$match) continue;
    }
    $filtered[] = $sc;
}

// ── Paginate ─────────────────────────────────────────────────────────────────
$perPage = 50;
$total   = count($filtered);
$pages   = $total > 0 ? (int)ceil($total / $perPage) : 1;
$page    = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $pages)) : 1;
$offset  = ($page - 1) * $perPage;
$pageRows = array_slice($filtered, $offset, $perPage);

// ── Selected site category → panel ───────────────────────────────────────────
$selected    = isset($_GET['selected']) ? (int)$_GET['selected'] : 0;
$panelSiteCat    = isset($siteCats[$selected]) ? $siteCats[$selected] : null;
$panelPapirCatId = isset($mappings[$selected]) ? $mappings[$selected] : 0;

require_once __DIR__ . '/views/category_mapping.php';
