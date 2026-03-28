<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Split by comma → individual terms, min 2 chars each
$terms = array_values(array_filter(
    array_map('trim', explode(',', $q)),
    function($t) { return mb_strlen($t, 'UTF-8') >= 2; }
));

if (empty($terms)) {
    echo json_encode(array('ok' => true, 'categories' => array(), 'products' => array()));
    exit;
}

// ── Build OR conditions ───────────────────────────────────────────────────

$catConditions  = array();
$prodConditions = array();

foreach ($terms as $term) {
    $esc = Database::escape('Papir', $term);
    // Integer term → exact ID match
    if (preg_match('/^\d+$/', $term)) {
        $catConditions[]  = "c.category_id = " . (int)$term;
        $prodConditions[] = "pp.product_id = " . (int)$term;
    }
    // Text term → AND по пробельным токенам
    $tokens = array_filter(preg_split('/\s+/u', mb_strtolower($term, 'UTF-8')));
    $catTokenParts  = array();
    $prodTokenParts = array();
    foreach ($tokens as $token) {
        $te = Database::escape('Papir', $token);
        $catTokenParts[]  = "LOWER(COALESCE(cd.name,'')) LIKE '%{$te}%'";
        $prodTokenParts[] = "(LOWER(COALESCE(NULLIF(pd.name,''),'')) LIKE '%{$te}%'"
                          . " OR LOWER(COALESCE(pp.product_article,'')) LIKE '%{$te}%')";
    }
    if (!empty($catTokenParts)) {
        $catConditions[]  = '(' . implode(' AND ', $catTokenParts) . ')';
    }
    if (!empty($prodTokenParts)) {
        $prodConditions[] = '(' . implode(' AND ', $prodTokenParts) . ')';
    }
}

// ── Categories ────────────────────────────────────────────────────────────

$categories = array();
if (!empty($catConditions)) {
    $catWhere = implode(' OR ', $catConditions);
    $catsR = Database::fetchAll('Papir',
        "SELECT c.category_id AS id, COALESCE(cd.name, '') AS name
         FROM categoria c
         LEFT JOIN category_description cd
           ON cd.category_id = c.category_id AND cd.language_id = 2
         WHERE {$catWhere}
         ORDER BY cd.name ASC
         LIMIT 15"
    );
    $categories = ($catsR['ok'] && !empty($catsR['rows'])) ? $catsR['rows'] : array();
}

// ── Products ──────────────────────────────────────────────────────────────

$products = array();
if (!empty($prodConditions)) {
    $prodWhere = implode(' OR ', $prodConditions);
    $prodsR = Database::fetchAll('Papir',
        "SELECT pp.product_id, pp.product_article,
                COALESCE(NULLIF(pd.name,''), '') AS name
         FROM product_papir pp
         LEFT JOIN product_description pd
           ON pd.product_id = pp.product_id AND pd.language_id = 2
         WHERE {$prodWhere}
         ORDER BY name ASC
         LIMIT 15"
    );
    $products = ($prodsR['ok'] && !empty($prodsR['rows'])) ? $prodsR['rows'] : array();
}

echo json_encode(array(
    'ok'         => true,
    'categories' => $categories,
    'products'   => $products,
), JSON_UNESCAPED_UNICODE);
