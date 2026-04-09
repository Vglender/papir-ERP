<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$categoryId  = isset($_POST['category_id'])  ? (int)$_POST['category_id']       : 0;
$status      = isset($_POST['status'])       ? (int)$_POST['status']            : 0;
$sortOrder   = isset($_POST['sort_order'])   ? (int)$_POST['sort_order']        : 0;
$nameUa      = isset($_POST['name_ua'])      ? trim($_POST['name_ua'])          : '';
$nameRu      = isset($_POST['name_ru'])      ? trim($_POST['name_ru'])          : '';
$descUa      = isset($_POST['desc_ua'])      ? (string)$_POST['desc_ua']        : '';
$descRu      = isset($_POST['desc_ru'])      ? (string)$_POST['desc_ru']        : '';

if ($categoryId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'category_id required'));
    exit;
}
if ($nameUa === '') {
    echo json_encode(array('ok' => false, 'error' => 'Назва (UA) обов\'язкова'));
    exit;
}

require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';

// 1. Update categoria
Database::update('Papir', 'categoria',
    array('status' => $status, 'sort_order' => $sortOrder),
    array('category_id' => $categoryId)
);

function esc($db, $v) { return Database::escape($db, $v); }

// 2. Upsert category_description lang=1 (RU)
Database::query('Papir',
    "INSERT INTO category_description (category_id, language_id, name, description_full)
     VALUES ({$categoryId}, 1, '" . esc('Papir', $nameRu) . "', '" . esc('Papir', $descRu) . "')
     ON DUPLICATE KEY UPDATE
       name='" . esc('Papir', $nameRu) . "',
       description_full='" . esc('Papir', $descRu) . "'"
);

// 3. Upsert category_description lang=2 (UA)
Database::query('Papir',
    "INSERT INTO category_description (category_id, language_id, name, description_full)
     VALUES ({$categoryId}, 2, '" . esc('Papir', $nameUa) . "', '" . esc('Papir', $descUa) . "')
     ON DUPLICATE KEY UPDATE
       name='" . esc('Papir', $nameUa) . "',
       description_full='" . esc('Papir', $descUa) . "'"
);

// 4. Cascade names + description → all active sites
$siteMappings = Database::fetchAll('Papir',
    "SELECT csm.site_id, csm.site_category_id
     FROM category_site_mapping csm
     JOIN sites s ON s.site_id = csm.site_id AND s.status = 1
     WHERE csm.category_id = {$categoryId}"
);

if ($siteMappings['ok'] && !empty($siteMappings['rows'])) {
    $sync = new SiteSyncService();

    // Build descriptions with Papir language_ids (mapped by transport)
    $langMap = Database::fetchAll('Papir', "SELECT site_id, language_id, site_lang_id FROM site_languages");
    $langBySite = array();
    if ($langMap['ok']) {
        foreach ($langMap['rows'] as $lm) {
            $langBySite[(int)$lm['site_id']][(int)$lm['language_id']] = (int)$lm['site_lang_id'];
        }
    }

    foreach ($siteMappings['rows'] as $sm) {
        $siteId    = (int)$sm['site_id'];
        $siteCatId = (int)$sm['site_category_id'];
        if ($siteCatId <= 0) continue;

        $langs = isset($langBySite[$siteId]) ? $langBySite[$siteId] : array();
        $descriptions = array();

        // RU (Papir lang=1)
        if (isset($langs[1])) {
            $descriptions[] = array('language_id' => $langs[1], 'name' => $nameRu, 'description' => $descRu);
        }
        // UA (Papir lang=2)
        if (isset($langs[2])) {
            $descriptions[] = array('language_id' => $langs[2], 'name' => $nameUa, 'description' => $descUa);
        }

        $sync->categoryUpdate($siteId, $siteCatId, array(), $descriptions);
    }
}

echo json_encode(array('ok' => true, 'name' => $nameUa));
