<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../MerchantService.php';
require_once __DIR__ . '/../../database/database.php';

define('PERF_MID',   '121039527');
define('PERF_LIMIT', 50);

$result = array(
    'ok'           => false,
    'error'        => null,
    'date_from'    => '',
    'date_to'      => '',
    'shopping_ads' => array(),
    'free_listings'=> array(),
    'summary'      => array(),
);

// ── helpers ──────────────────────────────────────────────────────────────────

function perfFetchTop($service, $mid, $program, $dateFrom, $dateTo, $limit)
{
    $query = "SELECT segments.offer_id, metrics.clicks, metrics.impressions, metrics.ctr
              FROM MerchantPerformanceView
              WHERE segments.date BETWEEN '{$dateFrom}' AND '{$dateTo}'
                AND segments.program = '{$program}'
              ORDER BY metrics.clicks DESC LIMIT {$limit}";

    $req = new Google\Service\ShoppingContent\SearchRequest();
    $req->setQuery($query);
    $req->setPageSize($limit);

    $resp = $service->reports->search($mid, $req);
    $rows = array();
    foreach ((array)$resp->getResults() as $row) {
        $seg = $row->getSegments();
        $met = $row->getMetrics();
        if (!$seg || !$seg->getOfferId()) continue;
        $rows[] = array(
            'offer_id'    => $seg->getOfferId(),
            'clicks'      => (int)$met->getClicks(),
            'impressions' => (int)$met->getImpressions(),
            'ctr'         => round((float)$met->getCtr() * 100, 2),
        );
    }
    return $rows;
}

function perfFetchDaily($service, $mid, $dateFrom, $dateTo)
{
    $query = "SELECT segments.date, segments.program, metrics.clicks, metrics.impressions
              FROM MerchantPerformanceView
              WHERE segments.date BETWEEN '{$dateFrom}' AND '{$dateTo}'
              ORDER BY segments.date ASC";

    $req = new Google\Service\ShoppingContent\SearchRequest();
    $req->setQuery($query);
    $req->setPageSize(500);

    $resp = $service->reports->search($mid, $req);
    $rows = array();
    foreach ((array)$resp->getResults() as $row) {
        $seg = $row->getSegments();
        $met = $row->getMetrics();
        if (!$seg || !$seg->getDate()) continue;
        $dateObj = $seg->getDate();
        if (is_object($dateObj)) {
            $dateStr = sprintf('%04d-%02d-%02d',
                (int)$dateObj->getYear(), (int)$dateObj->getMonth(), (int)$dateObj->getDay());
        } else {
            $dateStr = (string)$dateObj;
        }
        $rows[] = array(
            'date'        => $dateStr,
            'program'     => $seg->getProgram(),
            'clicks'      => (int)$met->getClicks(),
            'impressions' => (int)$met->getImpressions(),
        );
    }
    return $rows;
}

function perfFetchSummary($service, $mid, $dateFrom, $dateTo)
{
    $query = "SELECT segments.program, metrics.clicks, metrics.impressions
              FROM MerchantPerformanceView
              WHERE segments.date BETWEEN '{$dateFrom}' AND '{$dateTo}'";

    $req = new Google\Service\ShoppingContent\SearchRequest();
    $req->setQuery($query);
    $req->setPageSize(10);

    $resp = $service->reports->search($mid, $req);
    $summary = array();
    foreach ((array)$resp->getResults() as $row) {
        $seg = $row->getSegments();
        $met = $row->getMetrics();
        if (!$seg) continue;
        $prog = $seg->getProgram();
        $summary[$prog] = array(
            'program'     => $prog,
            'clicks'      => (int)$met->getClicks(),
            'impressions' => (int)$met->getImpressions(),
            'ctr'         => ($met->getImpressions() > 0)
                ? round((int)$met->getClicks() / (int)$met->getImpressions() * 100, 2)
                : 0,
        );
    }
    return array_values($summary);
}

function perfEnrich($rows)
{
    if (empty($rows)) return $rows;

    $offerIds = array_map(function($r) { return (int)$r['offer_id']; }, $rows);
    $in = implode(',', $offerIds);

    $r = Database::fetchAll('Papir',
        "SELECT ps.site_product_id, pp.product_id, pp.product_article,
                COALESCE(NULLIF(pd2.name,''), NULLIF(pd1.name,''), '') AS name
         FROM product_site ps
         JOIN product_papir pp ON pp.product_id = ps.product_id
         LEFT JOIN product_description pd2 ON pd2.product_id = pp.product_id AND pd2.language_id = 2
         LEFT JOIN product_description pd1 ON pd1.product_id = pp.product_id AND pd1.language_id = 1
         WHERE ps.site_id = 1 AND ps.site_product_id IN ({$in})");

    $map = array();
    if ($r['ok'] && !empty($r['rows'])) {
        foreach ($r['rows'] as $row) {
            $map[(int)$row['site_product_id']] = $row;
        }
    }

    foreach ($rows as &$row) {
        $info = isset($map[(int)$row['offer_id']]) ? $map[(int)$row['offer_id']] : null;
        $row['product_id'] = $info ? (int)$info['product_id']     : null;
        $row['article']    = $info ? $info['product_article']      : null;
        $row['name']       = $info ? mb_substr($info['name'], 0, 80, 'UTF-8') : null;
    }
    return $rows;
}

// ── main ──────────────────────────────────────────────────────────────────────

try {
    $days     = isset($_GET['days']) ? max(1, min(90, (int)$_GET['days'])) : 28;
    $dateTo   = date('Y-m-d');
    $dateFrom = date('Y-m-d', strtotime("-{$days} days"));

    $result['date_from'] = $dateFrom;
    $result['date_to']   = $dateTo;

    $client  = MerchantService::getClient();
    $service = new Google\Service\ShoppingContent($client);
    $mid     = PERF_MID;

    $result['summary']       = perfFetchSummary($service, $mid, $dateFrom, $dateTo);
    $result['daily']         = perfFetchDaily($service, $mid, $dateFrom, $dateTo);
    $rowsShopping            = perfFetchTop($service, $mid, 'SHOPPING_ADS',  $dateFrom, $dateTo, PERF_LIMIT);
    $rowsFree                = perfFetchTop($service, $mid, 'FREE_PRODUCT_LISTING', $dateFrom, $dateTo, PERF_LIMIT);
    $result['shopping_ads']  = perfEnrich($rowsShopping);
    $result['free_listings'] = perfEnrich($rowsFree);
    $result['ok']            = true;

} catch (Exception $e) {
    $msg = $e->getMessage();
    $result['error'] = (strpos($msg, 'merchant_auth_required::') === 0)
        ? 'Потрібна авторизація Google' : $msg;
}

echo json_encode($result);