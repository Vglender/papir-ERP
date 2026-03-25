<?php
require_once __DIR__ . '/catalog_bootstrap.php';

$selected = Request::getInt('selected', 0);

// All categories for tree (id, parent_id, name UA)
$treeRes = Database::fetchAll('Papir',
    "SELECT c.category_id as id, c.parent_id, c.status, cd.name
     FROM categoria c
     LEFT JOIN category_description cd ON cd.category_id = c.category_id AND cd.language_id = 2
     ORDER BY c.parent_id, c.sort_order, c.category_id"
);
$treeCats = $treeRes['ok'] ? $treeRes['rows'] : array();

// Load sites and languages (always needed for SEO card)
$sitesRes = Database::fetchAll('Papir', "SELECT site_id, name, code, url, db_alias FROM sites WHERE status=1 ORDER BY sort_order, site_id");
$allSites = $sitesRes['ok'] ? $sitesRes['rows'] : array();

$langsRes = Database::fetchAll('Papir', "SELECT language_id, code, name FROM languages ORDER BY sort_order, language_id");
$allLanguages = $langsRes['ok'] ? $langsRes['rows'] : array();

// Helper: build seo map for a category
function buildInitialSeoMap($categoryId, $allSites, $allLanguages) {
    $seoMap = array();
    foreach ($allSites as $site) {
        $sid = (int)$site['site_id'];
        $seoMap[$sid] = array();
        foreach ($allLanguages as $lang) {
            $lid = (int)$lang['language_id'];
            $seoMap[$sid][$lid] = array(
                'meta_title'       => '',
                'meta_description' => '',
                'description'      => '',
                'seo_h1'           => '',
                'seo_url'          => '',
                'cat_url'          => '',
                'cat_name'         => '',
            );
        }
    }

    $seoRes = Database::fetchAll('Papir',
        "SELECT site_id, language_id, meta_title, meta_description, description, seo_h1, seo_url, cat_name
         FROM category_seo WHERE category_id = " . (int)$categoryId
    );
    if ($seoRes['ok']) {
        foreach ($seoRes['rows'] as $row) {
            $sid = (int)$row['site_id'];
            $lid = (int)$row['language_id'];
            if (isset($seoMap[$sid][$lid])) {
                $seoMap[$sid][$lid]['meta_title']       = (string)$row['meta_title'];
                $seoMap[$sid][$lid]['meta_description'] = (string)$row['meta_description'];
                $seoMap[$sid][$lid]['description']      = (string)$row['description'];
                $seoMap[$sid][$lid]['seo_h1']           = (string)$row['seo_h1'];
                $seoMap[$sid][$lid]['seo_url']          = (string)$row['seo_url'];
                $seoMap[$sid][$lid]['cat_name']         = (string)$row['cat_name'];
            }
        }
    }

    // Build cat_url for language_id=1 per site
    $ancestorRes = Database::fetchAll('Papir', "SELECT category_id, parent_id FROM categoria");
    $parentMap = array();
    if ($ancestorRes['ok']) {
        foreach ($ancestorRes['rows'] as $row) {
            $parentMap[(int)$row['category_id']] = (int)$row['parent_id'];
        }
    }
    $ancestorIds = array();
    $cur = (int)$categoryId;
    $depth = 20;
    while ($cur > 0 && $depth-- > 0) {
        array_unshift($ancestorIds, $cur);
        $cur = isset($parentMap[$cur]) ? $parentMap[$cur] : 0;
    }

    if (!empty($ancestorIds)) {
        $idList = implode(',', $ancestorIds);
        foreach ($allSites as $site) {
            $sid     = (int)$site['site_id'];
            $siteUrl = rtrim((string)$site['url'], '/');
            $slugRes = Database::fetchAll('Papir',
                "SELECT category_id, seo_url FROM category_seo
                 WHERE site_id = {$sid} AND language_id = 1 AND category_id IN ({$idList})"
            );
            $slugMap = array();
            if ($slugRes['ok']) {
                foreach ($slugRes['rows'] as $sr) {
                    $slugMap[(int)$sr['category_id']] = (string)$sr['seo_url'];
                }
            }
            $parts = array();
            $valid = true;
            foreach ($ancestorIds as $aid) {
                $slug = isset($slugMap[$aid]) ? $slugMap[$aid] : '';
                if ($slug === '') { $valid = false; break; }
                $parts[] = $slug;
            }
            if ($valid && !empty($parts) && isset($seoMap[$sid][1])) {
                $seoMap[$sid][1]['cat_url'] = $siteUrl . '/' . implode('/', $parts);
            }
        }
    }

    return $seoMap;
}

// Helper: build site_settings (status/sort_order from each site's oc_category)
function buildInitialSiteSettings($catRow, $allSites) {
    $siteSettings = array();
    foreach ($allSites as $site) {
        $sid      = (int)$site['site_id'];
        $siteCode = (string)$site['code'];
        $siteCatId = 0;
        if ($siteCode === 'off') {
            $siteCatId = (int)$catRow['category_off'];
        } elseif ($siteCode === 'mff') {
            $siteCatId = (int)$catRow['category_mf'];
        }
        $siteSettings[$sid] = array('status' => 0, 'sort_order' => 0);
        if ($siteCatId > 0) {
            $dbAlias = ($siteCode === 'off') ? 'off' : (($siteCode === 'mff') ? 'mff' : '');
            if ($dbAlias) {
                $scRes = Database::fetchRow($dbAlias,
                    "SELECT status, sort_order FROM oc_category WHERE category_id = {$siteCatId}"
                );
                if ($scRes['ok'] && !empty($scRes['row'])) {
                    $siteSettings[$sid]['status']     = (int)$scRes['row']['status'];
                    $siteSettings[$sid]['sort_order'] = (int)$scRes['row']['sort_order'];
                }
            }
        }
    }
    return $siteSettings;
}

// Initial selected category (embedded as JSON for JS)
$initialCat = null;
if ($selected > 0) {
    $r = Database::fetchRow('Papir',
        "SELECT c.category_id, c.parent_id, c.status, c.sort_order,
                c.image, c.category_off, c.category_mf,
                p.name as parent_name
         FROM categoria c
         LEFT JOIN category_description p ON p.category_id = c.parent_id AND p.language_id = 2
         WHERE c.category_id = {$selected}"
    );
    if ($r['ok'] && !empty($r['row'])) {
        $initialCat = $r['row'];

        $rUa = Database::fetchRow('Papir',
            "SELECT name, name_full FROM category_description WHERE category_id = {$selected} AND language_id = 2"
        );
        $rRu = Database::fetchRow('Papir',
            "SELECT name FROM category_description WHERE category_id = {$selected} AND language_id = 1"
        );
        $chRes = Database::fetchRow('Papir', "SELECT COUNT(*) as cnt FROM categoria WHERE parent_id = {$selected}");

        $initialCat['ua']            = ($rUa['ok'] && !empty($rUa['row'])) ? $rUa['row'] : array();
        $initialCat['ru']            = ($rRu['ok'] && !empty($rRu['row'])) ? $rRu['row'] : array();
        $initialCat['children_count'] = ($chRes['ok'] && !empty($chRes['row'])) ? (int)$chRes['row']['cnt'] : 0;
        $initialCat['seo']           = buildInitialSeoMap($selected, $allSites, $allLanguages);
        $initialCat['site_settings'] = buildInitialSiteSettings($initialCat, $allSites);
        $initialCat['sites']         = $allSites;
        $initialCat['languages']     = $allLanguages;

        $imgsRes = Database::fetchAll('Papir',
            "SELECT image_id, image FROM category_images WHERE category_id = {$selected} ORDER BY sort_order, image_id"
        );
        $initialImages = array();
        if ($imgsRes['ok']) {
            foreach ($imgsRes['rows'] as $img) {
                $initialImages[] = array(
                    'image_id' => (int)$img['image_id'],
                    'image'    => (string)$img['image'],
                    'url'      => 'https://officetorg.com.ua/image/' . ltrim((string)$img['image'], '/'),
                );
            }
        }
        $initialCat['images'] = $initialImages;
    }
}

require_once __DIR__ . '/views/categories.php';
