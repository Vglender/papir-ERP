<?php

require_once '/var/sqript/vendor/autoload.php';

use Google\Client;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\RunReportRequest;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\Dimension;
use Google\Service\AnalyticsData\DateRange;
use Google\Service\AnalyticsData\OrderBy;
use Google\Service\AnalyticsData\DimensionOrderBy;
use Google\Service\AnalyticsData\MetricOrderBy;

if (!defined('GA_CREDENTIALS_FILE')) {
    define('GA_CREDENTIALS_FILE', __DIR__ . '/../../modules/prices/google_credentials.json');
}

class GoogleAnalyticsService
{
    private static $properties = array(
        'off' => '289347695',
        'mff' => '326281821',
    );

    public static function getClient()
    {
        $client = new Client();
        $client->setAuthConfig(GA_CREDENTIALS_FILE);
        $client->setScopes(array(AnalyticsData::ANALYTICS_READONLY));
        return $client;
    }

    /**
     * Зведені метрики за поточний і попередній період.
     * Повертає data (поточний) + prev (попередній) + diff (% зміна).
     */
    public static function getSummaryWithComparison($site, $period)
    {
        if (!isset(self::$properties[$site])) {
            return array('ok' => false, 'error' => 'Unknown site: ' . $site);
        }
        $propertyId = self::$properties[$site];

        $period = (int)$period;
        $from     = $period . 'daysAgo';
        $to       = 'today';
        $prevFrom = ($period * 2) . 'daysAgo';
        $prevTo   = ($period + 1) . 'daysAgo';

        try {
            $client    = self::getClient();
            $analytics = new AnalyticsData($client);

            $request = new RunReportRequest();
            $request->setMetrics(array(
                self::metric('sessions'),
                self::metric('totalUsers'),
                self::metric('screenPageViews'),
                self::metric('bounceRate'),
                self::metric('averageSessionDuration'),
            ));
            $request->setDateRanges(array(
                self::dateRange($from, $to, 'current'),
                self::dateRange($prevFrom, $prevTo, 'previous'),
            ));

            $response = $analytics->properties->runReport('properties/' . $propertyId, $request);
            $rows = $response->getRows();

            $cur  = array('sessions' => 0, 'users' => 0, 'pageviews' => 0, 'bounce_rate' => 0, 'avg_session_duration' => 0);
            $prev = $cur;

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $rangeName = $row->getDimensionValues()[0]->getValue();
                    $vals = $row->getMetricValues();
                    $data = array(
                        'sessions'             => (int)$vals[0]->getValue(),
                        'users'                => (int)$vals[1]->getValue(),
                        'pageviews'            => (int)$vals[2]->getValue(),
                        'bounce_rate'          => round((float)$vals[3]->getValue() * 100, 1),
                        'avg_session_duration' => round((float)$vals[4]->getValue()),
                    );
                    if ($rangeName === 'current')  { $cur  = $data; }
                    if ($rangeName === 'previous') { $prev = $data; }
                }
            }

            $diff = array();
            foreach ($cur as $key => $val) {
                $p = isset($prev[$key]) ? $prev[$key] : 0;
                $diff[$key] = $p > 0 ? round(($val - $p) / $p * 100, 1) : null;
            }

            return array('ok' => true, 'data' => $cur, 'prev' => $prev, 'diff' => $diff);
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Метрики по днях для графіка.
     */
    public static function getByDate($site, $period)
    {
        if (!isset(self::$properties[$site])) {
            return array('ok' => false, 'error' => 'Unknown site: ' . $site);
        }
        $propertyId = self::$properties[$site];
        $from = (int)$period . 'daysAgo';

        try {
            $client    = self::getClient();
            $analytics = new AnalyticsData($client);

            $request = new RunReportRequest();
            $request->setMetrics(array(
                self::metric('sessions'),
                self::metric('totalUsers'),
                self::metric('screenPageViews'),
            ));
            $request->setDimensions(array(self::dimension('date')));
            $request->setDateRanges(array(self::dateRange($from, 'today')));

            $dimOrder = new DimensionOrderBy();
            $dimOrder->setDimensionName('date');
            $order = new OrderBy();
            $order->setDimension($dimOrder);
            $order->setDesc(false);
            $request->setOrderBys(array($order));

            $response = $analytics->properties->runReport('properties/' . $propertyId, $request);
            $rows = $response->getRows();
            $data = array();
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $d    = $row->getDimensionValues()[0]->getValue();
                    $vals = $row->getMetricValues();
                    $data[] = array(
                        'date'      => substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2),
                        'sessions'  => (int)$vals[0]->getValue(),
                        'users'     => (int)$vals[1]->getValue(),
                        'pageviews' => (int)$vals[2]->getValue(),
                    );
                }
            }
            return array('ok' => true, 'data' => $data);
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Топ сторінок.
     */
    public static function getTopPages($site, $period, $limit = 20)
    {
        if (!isset(self::$properties[$site])) {
            return array('ok' => false, 'error' => 'Unknown site: ' . $site);
        }
        $propertyId = self::$properties[$site];
        $from = (int)$period . 'daysAgo';

        try {
            $client    = self::getClient();
            $analytics = new AnalyticsData($client);

            $request = new RunReportRequest();
            $request->setMetrics(array(
                self::metric('screenPageViews'),
                self::metric('sessions'),
                self::metric('totalUsers'),
            ));
            $request->setDimensions(array(
                self::dimension('pagePath'),
                self::dimension('pageTitle'),
            ));
            $request->setDateRanges(array(self::dateRange($from, 'today')));
            $request->setLimit($limit);

            $mo = new MetricOrderBy(); $mo->setMetricName('screenPageViews');
            $order = new OrderBy(); $order->setMetric($mo); $order->setDesc(true);
            $request->setOrderBys(array($order));

            $response = $analytics->properties->runReport('properties/' . $propertyId, $request);
            $rows = $response->getRows();
            $data = array();
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $dims = $row->getDimensionValues();
                    $vals = $row->getMetricValues();
                    $data[] = array(
                        'path'      => $dims[0]->getValue(),
                        'title'     => $dims[1]->getValue(),
                        'pageviews' => (int)$vals[0]->getValue(),
                        'sessions'  => (int)$vals[1]->getValue(),
                        'users'     => (int)$vals[2]->getValue(),
                    );
                }
            }
            return array('ok' => true, 'data' => $data);
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Джерела трафіку (канали).
     */
    public static function getChannels($site, $period)
    {
        if (!isset(self::$properties[$site])) {
            return array('ok' => false, 'error' => 'Unknown site: ' . $site);
        }
        $propertyId = self::$properties[$site];
        $from = (int)$period . 'daysAgo';

        try {
            $client    = self::getClient();
            $analytics = new AnalyticsData($client);

            $request = new RunReportRequest();
            $request->setMetrics(array(self::metric('sessions'), self::metric('totalUsers')));
            $request->setDimensions(array(self::dimension('sessionDefaultChannelGroup')));
            $request->setDateRanges(array(self::dateRange($from, 'today')));
            $request->setLimit(10);

            $mo = new MetricOrderBy(); $mo->setMetricName('sessions');
            $order = new OrderBy(); $order->setMetric($mo); $order->setDesc(true);
            $request->setOrderBys(array($order));

            $response = $analytics->properties->runReport('properties/' . $propertyId, $request);
            $rows = $response->getRows();
            $data = array();
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $dims = $row->getDimensionValues();
                    $vals = $row->getMetricValues();
                    $data[] = array(
                        'channel'  => $dims[0]->getValue(),
                        'sessions' => (int)$vals[0]->getValue(),
                        'users'    => (int)$vals[1]->getValue(),
                    );
                }
            }
            return array('ok' => true, 'data' => $data);
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Географія — топ міст.
     */
    public static function getGeography($site, $period, $limit = 15)
    {
        if (!isset(self::$properties[$site])) {
            return array('ok' => false, 'error' => 'Unknown site: ' . $site);
        }
        $propertyId = self::$properties[$site];
        $from = (int)$period . 'daysAgo';

        try {
            $client    = self::getClient();
            $analytics = new AnalyticsData($client);

            $request = new RunReportRequest();
            $request->setMetrics(array(self::metric('sessions'), self::metric('totalUsers')));
            $request->setDimensions(array(self::dimension('city')));
            $request->setDateRanges(array(self::dateRange($from, 'today')));
            $request->setLimit($limit);

            $mo = new MetricOrderBy(); $mo->setMetricName('sessions');
            $order = new OrderBy(); $order->setMetric($mo); $order->setDesc(true);
            $request->setOrderBys(array($order));

            $response = $analytics->properties->runReport('properties/' . $propertyId, $request);
            $rows = $response->getRows();
            $data = array();
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $dims = $row->getDimensionValues();
                    $vals = $row->getMetricValues();
                    $data[] = array(
                        'city'     => $dims[0]->getValue(),
                        'sessions' => (int)$vals[0]->getValue(),
                        'users'    => (int)$vals[1]->getValue(),
                    );
                }
            }
            return array('ok' => true, 'data' => $data);
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * E-commerce: транзакції, дохід, конверсія.
     */
    public static function getEcommerce($site, $period)
    {
        if (!isset(self::$properties[$site])) {
            return array('ok' => false, 'error' => 'Unknown site: ' . $site);
        }
        $propertyId = self::$properties[$site];
        $from       = (int)$period . 'daysAgo';
        $prevFrom   = ((int)$period * 2) . 'daysAgo';
        $prevTo     = ((int)$period + 1) . 'daysAgo';

        try {
            $client    = self::getClient();
            $analytics = new AnalyticsData($client);

            $request = new RunReportRequest();
            $request->setMetrics(array(
                self::metric('transactions'),
                self::metric('purchaseRevenue'),
                self::metric('ecommercePurchases'),
                self::metric('sessionConversionRate'),
            ));
            $request->setDateRanges(array(
                self::dateRange($from, 'today', 'current'),
                self::dateRange($prevFrom, $prevTo, 'previous'),
            ));

            $response = $analytics->properties->runReport('properties/' . $propertyId, $request);
            $rows = $response->getRows();

            $cur  = array('transactions' => 0, 'revenue' => 0, 'purchases' => 0, 'conversion_rate' => 0);
            $prev = $cur;

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $rangeName = $row->getDimensionValues()[0]->getValue();
                    $vals = $row->getMetricValues();
                    $data = array(
                        'transactions'    => (int)$vals[0]->getValue(),
                        'revenue'         => round((float)$vals[1]->getValue(), 2),
                        'purchases'       => (int)$vals[2]->getValue(),
                        'conversion_rate' => round((float)$vals[3]->getValue() * 100, 2),
                    );
                    if ($rangeName === 'current')  { $cur  = $data; }
                    if ($rangeName === 'previous') { $prev = $data; }
                }
            }

            $diff = array();
            foreach ($cur as $key => $val) {
                $p = isset($prev[$key]) ? $prev[$key] : 0;
                $diff[$key] = $p > 0 ? round(($val - $p) / $p * 100, 1) : null;
            }

            return array('ok' => true, 'data' => $cur, 'prev' => $prev, 'diff' => $diff);
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private static function metric($name)
    {
        $m = new Metric(); $m->setName($name); return $m;
    }

    private static function dimension($name)
    {
        $d = new Dimension(); $d->setName($name); return $d;
    }

    private static function dateRange($from, $to, $name = null)
    {
        $dr = new DateRange();
        $dr->setStartDate($from);
        $dr->setEndDate($to);
        if ($name !== null) { $dr->setName($name); }
        return $dr;
    }

    /**
     * Замовлення по каналах трафіку (з off.oc_remarketing_orders + Papir.customerorder).
     * Доступно тільки для сайту off — на mff немає модуля remarketing.
     */
    public static function getOrdersByChannel($site, $period)
    {
        if ($site !== 'off') {
            return array('ok' => true, 'data' => array(), 'off_only' => true);
        }
        $period = (int)$period;
        $dateFrom = date('Y-m-d', strtotime("-{$period} days"));

        try {
            $sql = "
                SELECT
                    CASE
                        WHEN ro.gclid  != '' AND ro.gclid  IS NOT NULL THEN 'Google Ads'
                        WHEN ro.fbclid != '' AND ro.fbclid IS NOT NULL THEN 'Facebook Ads'
                        WHEN ro.utm_source != '' AND ro.utm_source IS NOT NULL
                             AND (LOWER(ro.utm_source) LIKE '%google%' AND LOWER(ro.utm_medium) LIKE '%organic%')
                             THEN 'Google Organic'
                        WHEN ro.utm_source != '' AND ro.utm_source IS NOT NULL
                             AND (LOWER(ro.utm_source) LIKE '%facebook%' OR LOWER(ro.utm_source) LIKE '%instagram%')
                             THEN 'Facebook Organic'
                        WHEN ro.utm_source != '' AND ro.utm_source IS NOT NULL THEN CONCAT(ro.utm_source, '/', ro.utm_medium)
                        WHEN ro.ga4_uuid != '' AND ro.ga4_uuid IS NOT NULL THEN 'Прямий'
                        ELSE 'Невідомо'
                    END AS channel,
                    COUNT(*)            AS orders_count,
                    SUM(co.sum_total)   AS revenue,
                    AVG(co.sum_total)   AS avg_check,
                    SUM(CASE WHEN co.status NOT IN ('cancelled') THEN 1 ELSE 0 END) AS valid_orders
                FROM `menufold_offtorg`.oc_remarketing_orders ro
                INNER JOIN Papir.customerorder co
                    ON co.number = CONCAT(ro.order_id, 'OFF')
                WHERE co.moment >= '{$dateFrom}'
                GROUP BY channel
                ORDER BY orders_count DESC
                LIMIT 20
            ";

            $r = Database::fetchAll('Papir', $sql);
            if (!$r['ok']) {
                return array('ok' => false, 'error' => 'DB error');
            }

            $data = array();
            foreach ($r['rows'] as $row) {
                $data[] = array(
                    'channel'      => $row['channel'],
                    'orders_count' => (int)$row['orders_count'],
                    'valid_orders' => (int)$row['valid_orders'],
                    'revenue'      => round((float)$row['revenue'], 2),
                    'avg_check'    => round((float)$row['avg_check'], 2),
                );
            }
            return array('ok' => true, 'data' => $data);
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

}
