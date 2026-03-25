<?php

require_once __DIR__ . '/catalog_bootstrap.php';

$perPage  = 30;
$page     = isset($_GET['page'])     ? max(1, (int)$_GET['page'])  : 1;
$search   = isset($_GET['q'])        ? trim($_GET['q'])             : '';
$selected = isset($_GET['selected']) ? $_GET['selected']            : null; // ID або 'new'

$where = '1=1';
if ($search !== '') {
    $s     = Database::escape('Papir', $search);
    $where = "m.name LIKE '%{$s}%'";
}

$countResult = Database::fetchRow('Papir',
    "SELECT COUNT(*) AS cnt FROM manufacturers m WHERE {$where}"
);
$total  = ($countResult['ok'] && isset($countResult['row']['cnt'])) ? (int)$countResult['row']['cnt'] : 0;
$pages  = $total > 0 ? (int)ceil($total / $perPage) : 1;
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$result = Database::fetchAll('Papir',
    "SELECT m.manufacturer_id, m.name, m.off_id, m.mff_id, m.image,
            COUNT(pp.product_id)            AS total_products,
            COALESCE(SUM(pp.status = 1), 0) AS active_products
     FROM manufacturers m
     LEFT JOIN product_papir pp ON pp.manufacturer_id = m.manufacturer_id
     WHERE {$where}
     GROUP BY m.manufacturer_id
     ORDER BY m.name ASC
     LIMIT {$perPage} OFFSET {$offset}"
);
$manufacturers = ($result['ok'] && !empty($result['rows'])) ? $result['rows'] : array();

// Панель — завантажуємо обраного виробника
$panel = null;
if ($selected === 'new') {
    $panel = array(
        'manufacturer_id' => 0,
        'name'            => '',
        'description'     => '',
        'image'           => '',
        'off_id'          => '',
        'mff_id'          => '',
        'total_products'  => 0,
        'active_products' => 0,
    );
} elseif ($selected !== null && (int)$selected > 0) {
    $selId  = (int)$selected;
    $selRes = Database::fetchRow('Papir',
        "SELECT m.manufacturer_id, m.name, m.description, m.image, m.off_id, m.mff_id,
                COUNT(pp.product_id)            AS total_products,
                COALESCE(SUM(pp.status = 1), 0) AS active_products
         FROM manufacturers m
         LEFT JOIN product_papir pp ON pp.manufacturer_id = m.manufacturer_id
         WHERE m.manufacturer_id = {$selId}
         GROUP BY m.manufacturer_id"
    );
    if ($selRes['ok'] && !empty($selRes['row'])) {
        $panel = $selRes['row'];
    }
}

require_once __DIR__ . '/views/manufacturers.php';
