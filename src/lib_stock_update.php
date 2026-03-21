<?php

require_once __DIR__ . '/../modules/database/database.php';

// MoySklad credentials and API URLs from internal module
$_msauth = require __DIR__ . '/../modules/moysklad/storage/moysklad_auth.php';
if (!defined('API_BASE_URL_ENTITY')) {
    define('API_BASE_URL_ENTITY', $_msauth['api_base_url_entity']);
}
if (!defined('API_BASE_URL_REPORT')) {
    define('API_BASE_URL_REPORT', $_msauth['api_base_url_report']);
}
$_ms_auth_string = $_msauth['auth'];
unset($_msauth);

if (!function_exists('ms_query')) {
    function ms_query($link, $type = null) {
        global $_ms_auth_string;
        usleep(66700);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_POST, 0);
        if ($type) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);
        }
        curl_setopt($curl, CURLOPT_USERPWD, $_ms_auth_string);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept-Encoding: gzip',
        ));
        $out = curl_exec($curl);
        curl_close($curl);
        if (strpos($out, "\x1f\x8b\x08") === 0) {
            return json_decode(gzdecode($out));
        }
        return json_decode($out);
    }
}

$_xlsxWriterPath = '/var/www/menufold/data/www/officetorg.com.ua/PI/exel/xlsxwriter.class.php';
if (file_exists($_xlsxWriterPath)) {
    require_once $_xlsxWriterPath;
}
unset($_xlsxWriterPath);

if (!function_exists('logLine')) {
    function logLine($text, $type = 'info') {
        echo '<div class="log-line log-' . $type . '">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '</div>';

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }
}

if (!function_exists('logProgress')) {
    function logProgress($text) {
        logLine($text, 'progress');
    }
}

if (!function_exists('logSuccess')) {
    function logSuccess($text) {
        logLine($text, 'success');
    }
}

if (!function_exists('logError')) {
    function logError($text) {
        logLine($text, 'error');
    }
}

if (!function_exists('logInfo')) {
    function logInfo($text) {
        logLine($text, 'info');
    }
}

function updateStockFromMs($writeExcel = true) {

    $start = microtime(true);
    $now = date('Y-m-d H:i:s');
    $date_start_sale = date('Y-m-d H:i:s', strtotime('-90 days'));

    $s = 0;
    $all_stock = 0;
    $stock = array();
    $stock_jump = array();
    $outcomes = array();

    $link = API_BASE_URL_REPORT . 'stock/all?filter=productFolder!=' . API_BASE_URL_ENTITY . 'productfolder/cfd356b4-4d78-11ec-0a80-09b500024ecb';
    $link_outcome = API_BASE_URL_REPORT . 'turnover/all?momentFrom=' . urlencode($date_start_sale);

    logInfo('=== START STOCK UPDATE ===');
    logInfo('Started at: ' . $now);

    $a = 0;
    while ($link_outcome) {
        $result_outcome = ms_query($link_outcome);
        $result_outcome = json_decode(json_encode($result_outcome), true);

        if (!empty($result_outcome['rows'])) {
            foreach ($result_outcome['rows'] as $value) {
                if (!empty($value['assortment']['code'])) {
                    $outcomes[] = array(
                        'code' => $value['assortment']['code'],
                        'outcome' => isset($value['outcome']['quantity']) ? $value['outcome']['quantity'] : 0
                    );
                }
            }
        }

        $a++;
        logProgress('Prepared outcome page: ' . $a);

        $link_outcome = isset($result_outcome['meta']['nextHref']) ? $result_outcome['meta']['nextHref'] : null;
    }

    logSuccess('Turnover rows loaded: ' . count($outcomes));

    while ($link) {
        $result = ms_query($link);
        $result = json_decode(json_encode($result), true);

        $left = isset($result['meta']['size']) ? ($result['meta']['size'] - $s) : 0;
        logProgress('Remaining: ' . $left);

        if (!empty($result['rows'])) {
            foreach ($result['rows'] as $value) {

                $href = isset($value['meta']['href']) ? $value['meta']['href'] : '';
                $id_product = preg_split('/[\\/]/', $href);
                $id_product_size = count($id_product);
                $id_product = $id_product[$id_product_size - 1];
                $id_product = str_replace('?expand=supplier', '', $id_product);

                if (!empty($value['code']) && isset($value['stock']) && $value['stock'] > 0) {

                    $stock[$s] = array(
                        'id_ms' => $id_product,
                        'model' => $value['code'],
                        'name' => isset($value['name']) ? $value['name'] : '',
                        'quantity' => isset($value['quantity']) ? (int)$value['quantity'] : 0,
                        'reserve' => isset($value['reserve']) ? (int)$value['reserve'] : 0,
                        'inTransit' => isset($value['inTransit']) ? (int)$value['inTransit'] : 0,
                        'stock' => isset($value['stock']) ? (int)$value['stock'] : 0,
                        'price' => isset($value['price']) ? number_format((float)($value['price'] / 100), 4, '.', '') : 0,
                        'salePrice' => isset($value['salePrice']) ? number_format((float)($value['salePrice'] / 100), 4, '.', '') : 0,
                        'stockDays' => isset($value['stockDays']) ? (int)$value['stockDays'] : 0,
                        'date' => $now,
                    );

                    $stock[$s]['sku'] = isset($value['article']) ? $value['article'] : null;
                    $stock[$s]['externalCode'] = isset($value['externalCode']) ? $value['externalCode'] : null;

                    $stock_jump[$s] = $stock[$s];

                    $all_stock += (float)($stock[$s]['stock'] * $stock[$s]['price']);

                    foreach ($outcomes as $out) {
                        if ($out['code'] == $stock[$s]['model']) {
                            $stock[$s]['outcome'] = $out['outcome'];
                            break;
                        }
                    }

                    $s++;

/*                     if ($s > 0 && $s % 100 == 0) {
                        logProgress('Prepared rows: ' . $s);
                    } */
                }
            }
        }

        $link = isset($result['meta']['nextHref']) ? $result['meta']['nextHref'] : null;
    }

    logSuccess('Stock rows prepared: ' . count($stock));
    logSuccess('Total stock sum prepared: ' . number_format($all_stock, 2, '.', ''));

    if (!empty($stock)) {
        Database::query('ms', "DELETE FROM `stock_`");

        foreach ($stock as $parametrs) {
            Database::insert('ms', 'stock_', $parametrs);
        }

        Database::query(
            'ms',
            "UPDATE `stock`
             SET `sum` = '" . number_format($all_stock, 2, '.', '') . "',
                 `date` = '" . $now . "'
             WHERE `number` = 1"
        );

        logSuccess('Table ms.stock_ updated successfully.');

        if ($writeExcel && class_exists('XLSXWriter')) {
            $writer = new XLSXWriter();
            $writer->writeSheet($stock_jump);
            $writer->writeToFile('/var/www/menufold/data/www/officetorg.com.ua/PI/output_all.xlsx');
            logSuccess('Excel written: /var/www/menufold/data/www/officetorg.com.ua/PI/output_all.xlsx');
        }
    } else {
        logError('No stock rows received. Table was not updated.');
    }

    logSuccess('Execution time: ' . round(microtime(true) - $start, 4) . ' sec.');
    logInfo('=== END STOCK UPDATE ===');

    return array(
        'rows' => count($stock),
        'sum' => number_format($all_stock, 2, '.', ''),
        'time' => round(microtime(true) - $start, 4)
    );
}

?>