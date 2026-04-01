<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/database.php';

/**
 * Аналіз покриття Automated Discounts у фіді Merchant Center.
 *
 * Логіка відповідає FeedGenerator::outputItem:
 *   - cost_of_goods_sold    → price_purchase > 0
 *   - auto_pricing_min_price → price_purchase > 0
 *       AND GREATEST(price_sale*0.60, price_purchase*1.20) <= price_sale*0.95
 *
 * Критерії потрапляння у фід: status=1 + product_site(site_id) + price_sale > 0
 */

// Базовий WHERE для «потрапляє у фід»
function adBuildWhere($siteId)
{
    $sid = (int)$siteId;
    return "pp.status = 1
        AND pp.price_sale > 0
        AND EXISTS (
            SELECT 1 FROM product_site ps
            WHERE ps.product_id = pp.product_id AND ps.site_id = {$sid}
        )";
}

function adGetSiteStats($siteId)
{
    $where = adBuildWhere($siteId);

    // Загальна кількість
    $r = Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM product_papir pp WHERE {$where}");
    $total = ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;
    if ($total === 0) {
        return array('total' => 0, 'with_auto' => 0, 'with_cost_only' => 0, 'no_cost' => 0, 'cost_exceeds' => 0);
    }

    // з auto_pricing_min_price
    $r2 = Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM product_papir pp
         WHERE {$where}
           AND pp.price_purchase > 0
           AND GREATEST(pp.price_sale * 0.60, pp.price_purchase * 1.20) <= pp.price_sale * 0.95");
    $withAuto = ($r2['ok'] && $r2['row']) ? (int)$r2['row']['cnt'] : 0;

    // є собівартість, але auto_pricing не виходить (мін_ціна > 95% від ціни продажу)
    $r3 = Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM product_papir pp
         WHERE {$where}
           AND pp.price_purchase > 0
           AND GREATEST(pp.price_sale * 0.60, pp.price_purchase * 1.20) > pp.price_sale * 0.95");
    $costExceeds = ($r3['ok'] && $r3['row']) ? (int)$r3['row']['cnt'] : 0;

    // є собівартість, але не auto (тільки cost_of_goods_sold без auto_pricing)
    $withCostOnly = $costExceeds; // усі з ціною але без авто-прайсингу

    // немає собівартості взагалі
    $r4 = Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM product_papir pp
         WHERE {$where}
           AND (pp.price_purchase IS NULL OR pp.price_purchase <= 0)");
    $noCost = ($r4['ok'] && $r4['row']) ? (int)$r4['row']['cnt'] : 0;

    return array(
        'total'          => $total,
        'with_auto'      => $withAuto,
        'with_cost_only' => $withCostOnly,
        'no_cost'        => $noCost,
        'cost_exceeds'   => $costExceeds,
    );
}

function adGetProblemProducts($siteId, $type, $limit = 30)
{
    $sid   = (int)$siteId;
    $limit = (int)$limit;
    $where = adBuildWhere($siteId);

    if ($type === 'no_cost') {
        $extraWhere = "AND (pp.price_purchase IS NULL OR pp.price_purchase <= 0)";
    } elseif ($type === 'cost_exceeds') {
        $extraWhere = "AND pp.price_purchase > 0
                       AND GREATEST(pp.price_sale * 0.60, pp.price_purchase * 1.20) > pp.price_sale * 0.95";
    } else {
        return array();
    }

    $r = Database::fetchAll('Papir',
        "SELECT
             pp.product_id,
             pp.product_article,
             pp.price_sale,
             pp.price_purchase,
             pp.price_rrp,
             COALESCE(NULLIF(pd2.name,''), NULLIF(pd1.name,''), '') AS name,
             ps.site_product_id AS site_pid
         FROM product_papir pp
         JOIN product_site ps ON ps.product_id = pp.product_id AND ps.site_id = {$sid}
         LEFT JOIN product_description pd2 ON pd2.product_id = pp.product_id AND pd2.language_id = 2
         LEFT JOIN product_description pd1 ON pd1.product_id = pp.product_id AND pd1.language_id = 1
         WHERE {$where} {$extraWhere}
         ORDER BY pp.price_sale DESC
         LIMIT {$limit}");

    if (!$r['ok'] || empty($r['rows'])) return array();

    $rows = array();
    foreach ($r['rows'] as $row) {
        $price    = (float)$row['price_sale'];
        $cost     = (float)$row['price_purchase'];
        $minPrice = null;
        $reason   = '';

        if ($cost > 0) {
            $minByDiscount = round($price * 0.60, 2);
            $minByCost     = round($cost * 1.20, 2);
            $minPrice      = max($minByDiscount, $minByCost);
            $maxAllowed    = round($price * 0.95, 2);
            if ($minPrice > $maxAllowed) {
                if ($minByCost > $minByDiscount) {
                    $reason = 'Мін. по собівартості (' . number_format($minByCost, 2) . ') > 95% ціни (' . number_format($maxAllowed, 2) . ')';
                } else {
                    $reason = 'Мін. по знижці (' . number_format($minByDiscount, 2) . ') > 95% ціни';
                }
            }
        } else {
            $reason = 'Собівартість не вказана';
        }

        $rows[] = array(
            'product_id'   => (int)$row['product_id'],
            'article'      => $row['product_article'],
            'name'         => $row['name'],
            'price_sale'   => $price,
            'price_cost'   => $cost > 0 ? $cost : null,
            'min_price'    => $minPrice,
            'reason'       => $reason,
            'site_pid'     => (int)$row['site_pid'],
        );
    }
    return $rows;
}

// ── Основна логіка ────────────────────────────────────────────────────────────

$result = array(
    'ok'   => false,
    'off'  => null,
    'mff'  => null,
    'error' => null,
);

try {
    $type   = isset($_GET['type'])    ? (string)$_GET['type']    : '';
    $site   = isset($_GET['site'])    ? (string)$_GET['site']    : '';
    $limit  = isset($_GET['limit'])   ? (int)$_GET['limit']      : 30;

    // Режим: завантажити список проблемних товарів
    if ($type !== '' && in_array($site, array('off', 'mff'))) {
        $siteId = ($site === 'mff') ? 2 : 1;
        $rows   = adGetProblemProducts($siteId, $type, min($limit, 200));
        echo json_encode(array('ok' => true, 'rows' => $rows, 'count' => count($rows)));
        exit;
    }

    // Режим: загальна статистика для обох сайтів
    $result['off'] = adGetSiteStats(1);
    $result['mff'] = adGetSiteStats(2);
    $result['ok']  = true;

} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result);