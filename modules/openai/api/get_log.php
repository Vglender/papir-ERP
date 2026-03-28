<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

$entityType = isset($_GET['entity_type']) ? trim($_GET['entity_type']) : '';
$siteId     = isset($_GET['site_id'])     ? (int)$_GET['site_id']     : 0;
$useCase    = isset($_GET['use_case'])    ? trim($_GET['use_case'])    : '';
$status     = isset($_GET['status'])      ? trim($_GET['status'])      : '';
$dateFrom   = isset($_GET['date_from'])   ? trim($_GET['date_from'])   : '';
$dateTo     = isset($_GET['date_to'])     ? trim($_GET['date_to'])     : '';
$offset     = isset($_GET['offset'])      ? max(0, (int)$_GET['offset']) : 0;
$limit      = isset($_GET['limit'])       ? min(50, max(1, (int)$_GET['limit'])) : 20;

$where = array('1=1');

if (in_array($entityType, array('product', 'category'))) {
    $et = Database::escape('Papir', $entityType);
    $where[] = "l.entity_type = '{$et}'";
}
if ($siteId > 0) {
    $where[] = "l.site_id = {$siteId}";
}
if (in_array($useCase, array('content', 'seo', 'chat'))) {
    $uc = Database::escape('Papir', $useCase);
    $where[] = "l.use_case = '{$uc}'";
}
if (in_array($status, array('generated', 'rejected'))) {
    $st = Database::escape('Papir', $status);
    $where[] = "l.status = '{$st}'";
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = "l.created_at >= '" . Database::escape('Papir', $dateFrom) . " 00:00:00'";
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = "l.created_at <= '" . Database::escape('Papir', $dateTo) . " 23:59:59'";
}

$whereStr = implode(' AND ', $where);

$countR = Database::fetchRow('Papir',
    "SELECT COUNT(*) AS cnt FROM ai_generation_log l WHERE {$whereStr}"
);
$total = ($countR['ok'] && !empty($countR['row'])) ? (int)$countR['row']['cnt'] : 0;

$rowsR = Database::fetchAll('Papir',
    "SELECT l.id AS log_id, l.entity_type, l.entity_id, l.site_id, l.language_id,
            l.use_case, l.status, l.tokens_used, l.created_at,
            s.name AS site_name, s.badge AS site_badge,
            CASE
              WHEN l.entity_type = 'category' THEN (
                SELECT cd.name FROM category_description cd
                WHERE cd.category_id = l.entity_id AND cd.language_id = 2 LIMIT 1
              )
              WHEN l.entity_type = 'product' THEN (
                SELECT COALESCE(NULLIF(pd.name,''), '') FROM product_description pd
                WHERE pd.product_id = l.entity_id AND pd.language_id = 2 LIMIT 1
              )
              ELSE ''
            END AS entity_name
     FROM ai_generation_log l
     LEFT JOIN sites s ON s.site_id = l.site_id
     WHERE {$whereStr}
     ORDER BY l.id DESC
     LIMIT {$limit} OFFSET {$offset}"
);

$rows = ($rowsR['ok'] && !empty($rowsR['rows'])) ? $rowsR['rows'] : array();

echo json_encode(array(
    'ok'     => true,
    'rows'   => $rows,
    'total'  => $total,
    'offset' => $offset,
    'limit'  => $limit,
), JSON_UNESCAPED_UNICODE);
