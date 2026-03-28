<?php
header('Content-Type: application/json; charset=utf-8');

define('MERCHANT_STATS_ID', '121039527');

$result = array(
    'ok'          => false,
    'error'       => null,
    'account'     => null,   // website_claimed, issues[]
    'products'    => null,   // by destination: active/disapproved/pending/expiring
    'top_issues'  => array(),
    'feeds'       => array(),
    'performance' => null,   // clicks, impressions, ctr, orders
);

try {
    require_once '/var/sqript/vendor/autoload.php';
    require_once __DIR__ . '/../MerchantService.php';

    $client  = MerchantService::getClient();
    $service = new Google\Service\ShoppingContent($client);
    $mid     = MERCHANT_STATS_ID;

    // ── 1. Account status ─────────────────────────────────────────────────
    try {
        $acctStatus = $service->accountstatuses->get($mid, $mid);

        $accountIssues = array();
        foreach ((array)$acctStatus->getAccountLevelIssues() as $issue) {
            $accountIssues[] = array(
                'id'          => $issue->getId(),
                'title'       => $issue->getTitle(),
                'detail'      => $issue->getDetail(),
                'severity'    => $issue->getSeverity(),
                'destination' => $issue->getDestination(),
                'doc'         => $issue->getDocumentation(),
            );
        }

        // Product statistics per destination (Shopping Ads + Free Listings)
        $productStats = array();
        $topIssuesMap = array(); // code => {desc, numItems, servability}

        foreach ((array)$acctStatus->getProducts() as $pg) {
            $dest  = $pg->getDestination();
            $stats = $pg->getStatistics();
            if ($stats) {
                $productStats[$dest] = array(
                    'destination' => $dest,
                    'active'      => (int)$stats->getActive(),
                    'disapproved' => (int)$stats->getDisapproved(),
                    'pending'     => (int)$stats->getPending(),
                    'expiring'    => (int)$stats->getExpiring(),
                );
            }
            // Item-level issues
            foreach ((array)$pg->getItemLevelIssues() as $issue) {
                $code = $issue->getCode();
                if (!isset($topIssuesMap[$code])) {
                    $topIssuesMap[$code] = array(
                        'code'        => $code,
                        'description' => $issue->getDescription(),
                        'detail'      => $issue->getDetail(),
                        'servability' => $issue->getServability(),
                        'resolution'  => $issue->getResolution(),
                        'num_items'   => 0,
                        'doc'         => $issue->getDocumentation(),
                    );
                }
                $topIssuesMap[$code]['num_items'] += (int)$issue->getNumItems();
            }
        }

        // Sort issues by num_items desc
        uasort($topIssuesMap, function($a, $b) {
            return $b['num_items'] - $a['num_items'];
        });

        $result['account']    = array(
            'website_claimed' => (bool)$acctStatus->getWebsiteClaimed(),
            'issues'          => $accountIssues,
        );
        $result['products']   = array_values($productStats);
        $result['top_issues'] = array_values(array_slice($topIssuesMap, 0, 10));

    } catch (Exception $e) {
        $result['account'] = array('error' => $e->getMessage());
    }

    // ── 2. Datafeed statuses ──────────────────────────────────────────────
    try {
        $feeds = array();
        $feedResp = $service->datafeedstatuses->listDatafeedstatuses($mid);
        foreach ((array)$feedResp->getResources() as $feed) {
            $errCount  = 0;
            $warnCount = 0;
            foreach ((array)$feed->getErrors() as $e)   { $errCount  += (int)$e->getCount(); }
            foreach ((array)$feed->getWarnings() as $w) { $warnCount += (int)$w->getCount(); }
            $feeds[] = array(
                'id'              => $feed->getDatafeedId(),
                'status'          => $feed->getProcessingStatus(),
                'items_total'     => (int)$feed->getItemsTotal(),
                'items_valid'     => (int)$feed->getItemsValid(),
                'last_upload'     => $feed->getLastUploadDate(),
                'country'         => $feed->getCountry(),
                'language'        => $feed->getLanguage(),
                'errors'          => $errCount,
                'warnings'        => $warnCount,
            );
        }
        $result['feeds'] = $feeds;
    } catch (Exception $e) {
        $result['feeds'] = array('error' => $e->getMessage());
    }

    // ── 3. Performance: last 28 days ──────────────────────────────────────
    try {
        $dateTo   = date('Y-m-d');
        $dateFrom = date('Y-m-d', strtotime('-28 days'));

        // segments.date is required in SELECT for the API to return per-day rows
        $query = "SELECT segments.date, metrics.clicks, metrics.impressions, metrics.orders"
               . " FROM MerchantPerformanceView"
               . " WHERE segments.date BETWEEN '" . $dateFrom . "' AND '" . $dateTo . "'";

        $searchReq = new Google\Service\ShoppingContent\SearchRequest();
        $searchReq->setQuery($query);
        $searchReq->setPageSize(1000);

        $clicks = 0; $impressions = 0; $orders = 0;

        $pageToken = null;
        do {
            if ($pageToken) { $searchReq->setPageToken($pageToken); }
            $resp = $service->reports->search($mid, $searchReq);
            foreach ((array)$resp->getResults() as $row) {
                $m = $row->getMetrics();
                if ($m) {
                    $clicks      += (int)$m->getClicks();
                    $impressions += (int)$m->getImpressions();
                    $orders      += (int)$m->getOrders();
                }
            }
            $pageToken = $resp->getNextPageToken();
        } while ($pageToken);

        $ctr = ($impressions > 0) ? round($clicks / $impressions * 100, 2) : 0;

        $result['performance'] = array(
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'clicks'      => $clicks,
            'impressions' => $impressions,
            'ctr'         => $ctr,
            'orders'      => $orders,
        );
    } catch (Exception $e) {
        $result['performance'] = array('error' => $e->getMessage());
    }

    $result['ok'] = true;

} catch (Exception $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'merchant_auth_required::') === 0) {
        $result['error'] = 'Потрібна авторизація Google Merchant';
    } else {
        $result['error'] = $msg;
    }
}

echo json_encode($result);
