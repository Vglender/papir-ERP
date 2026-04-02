<?php
/**
 * МойСклад → Papir webhook для demand (відвантаження).
 * URL в МС (через relay): http://159.69.1.229/demand_relay.php
 * Логи: /var/www/papir/storage/ms_webhook_demand.log
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';
require_once __DIR__ . '/../../moysklad/src/WebhookCpHelper.php';
require_once __DIR__ . '/../repositories/DemandRepository.php';

function mswhk_demand_log($msg) {
    @file_put_contents('/var/www/papir/storage/ms_webhook_demand.log',
        date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

mswhk_demand_log('Incoming: ' . $raw);

echo json_encode(array('ok' => true));
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    header('Content-Length: ' . ob_get_length());
    header('Connection: close');
    ob_end_flush();
    flush();
}

if (!is_array($body) || empty($body['events'])) exit;

$ms         = new MoySkladApi();
$repo       = new DemandRepository();
$processed  = 0;
$errors     = array();

// Status UUID → Papir enum
$stateMap = array(
    'ac913c39-eaa9-11eb-0a80-064900024c02' => 'shipped',
    '1786f816-7890-11ed-0a80-01d4001fe449' => 'robot',
    '313e4d03-eaad-11eb-0a80-0d7d0002b683' => 'assembling',
    'ac9137e7-eaa9-11eb-0a80-064900024c01' => 'assembled',
    '67350236-916f-11ec-0a80-084e0018453e' => 'transfer',
    '2e2bc3dc-5be1-11ee-0a80-0677000179a7' => 'arrived',
);

foreach ($body['events'] as $event) {
    $action = isset($event['action'])       ? strtoupper($event['action'])       : '';
    $type   = isset($event['meta']['type']) ? strtolower($event['meta']['type']) : '';
    $href   = isset($event['meta']['href']) ? (string)$event['meta']['href']     : '';

    if ($type !== 'demand') continue;

    $pos  = strrpos($href, '/');
    $msId = ($pos !== false) ? substr($href, $pos + 1) : '';
    if ($msId === '') { $errors[] = 'No UUID in href'; continue; }

    if ($action === 'DELETE') {
        $repo->markDeleted($msId);
        mswhk_demand_log('DELETE demand ms=' . $msId);
        $processed++;
        continue;
    }

    // Fetch full document from МС
    $docRaw = $ms->query($href . '?expand=agent,organization,state,positions.assortment');
    $doc    = json_decode(json_encode($docRaw), true);

    if (empty($doc) || !empty($doc['errors'])) {
        $errors[] = 'Fetch failed for ' . $msId;
        mswhk_demand_log('Fetch error for ' . $msId . ': ' . json_encode($doc));
        continue;
    }

    // Resolve counterparty_id — найти или создать
    $cpId = null;
    if (!empty($doc['agent']['meta']['href'])) {
        $cpMsId   = substr($doc['agent']['meta']['href'], strrpos($doc['agent']['meta']['href'], '/') + 1);
        $agentDoc = isset($doc['agent']) && is_array($doc['agent']) ? $doc['agent'] : array();
        $cpId     = mswhk_cp_resolve($cpMsId, $agentDoc, 'mswhk_demand_log');
    }

    // Resolve customerorder_id
    $customerorderId = null;
    if (!empty($doc['customerOrder']['meta']['href'])) {
        $coMsId = substr($doc['customerOrder']['meta']['href'], strrpos($doc['customerOrder']['meta']['href'], '/') + 1);
        $rCo = Database::fetchRow('Papir',
            "SELECT id FROM customerorder WHERE id_ms = '" . Database::escape('Papir', $coMsId) . "' LIMIT 1");
        if ($rCo['ok'] && !empty($rCo['row'])) $customerorderId = (int)$rCo['row']['id'];
    }

    // Status
    $status = 'shipped'; // fallback
    if (!empty($doc['state']['meta']['href'])) {
        $stateUuid = substr($doc['state']['meta']['href'], strrpos($doc['state']['meta']['href'], '/') + 1);
        if (isset($stateMap[$stateUuid])) $status = $stateMap[$stateUuid];
    }

    $data = array(
        'id_ms'           => $msId,
        'source'          => 'moysklad',
        'external_code'   => isset($doc['externalCode']) ? (string)$doc['externalCode'] : null,
        'number'          => isset($doc['name'])         ? mb_substr((string)$doc['name'], 0, 32, 'UTF-8') : null,
        'moment'          => isset($doc['moment'])       ? substr((string)$doc['moment'], 0, 19) : null,
        'applicable'      => !empty($doc['applicable']) ? 1 : 0,
        'counterparty_id' => $cpId,
        'customerorder_id'=> $customerorderId,
        'status'          => $status,
        'sum_total'       => isset($doc['sum'])       ? round((float)$doc['sum']       / 100, 2) : 0,
        'sum_vat'         => isset($doc['vatSum'])    ? round((float)$doc['vatSum']    / 100, 2) : 0,
        'sum_paid'        => isset($doc['payedSum'])  ? round((float)$doc['payedSum']  / 100, 2) : 0,
        'profit'          => isset($doc['profit'])    ? round((float)$doc['profit']    / 100, 2) : 0,
        'profit_real'     => isset($doc['profitReal'])? round((float)$doc['profitReal']/ 100, 2) : 0,
        'sales_channel'   => isset($doc['salesChannel']) ? mb_substr((string)$doc['salesChannel'], 0, 64, 'UTF-8') : null,
        'description'     => isset($doc['description']) ? (string)$doc['description'] : null,
        'sync_state'      => 'synced',
        'updated_at'      => isset($doc['updated']) ? substr((string)$doc['updated'], 0, 19) : date('Y-m-d H:i:s'),
    );

    $upsert = $repo->upsertFromMs($data);
    if (!$upsert['ok']) {
        $errors[] = 'Upsert failed for ' . $msId;
        continue;
    }

    // Get local id for items sync
    $rLocal = Database::fetchRow('Papir',
        "SELECT id FROM demand WHERE id_ms = '" . Database::escape('Papir', $msId) . "' LIMIT 1");
    $localId = ($rLocal['ok'] && !empty($rLocal['row'])) ? (int)$rLocal['row']['id'] : (isset($upsert['insert_id']) ? (int)$upsert['insert_id'] : 0);

    // Sync positions
    if ($localId && !empty($doc['positions']['rows'])) {
        $productMap = array();
        $rProd = Database::fetchAll('Papir', "SELECT product_id, id_ms FROM product_papir WHERE id_ms IS NOT NULL");
        if ($rProd['ok']) foreach ($rProd['rows'] as $pr) $productMap[$pr['id_ms']] = (int)$pr['product_id'];

        $positions = array();
        foreach ($doc['positions']['rows'] as $pos) {
            $prodMsId = null;
            if (!empty($pos['assortment']['meta']['href'])) {
                $prodMsId = substr($pos['assortment']['meta']['href'], strrpos($pos['assortment']['meta']['href'], '/') + 1);
            }
            $positions[] = array(
                'product_ms_id' => $prodMsId,
                'product_name'  => isset($pos['assortment']['name']) ? (string)$pos['assortment']['name'] : null,
                'sku'           => isset($pos['assortment']['article']) ? (string)$pos['assortment']['article'] : null,
                'quantity'      => isset($pos['quantity']) ? (float)$pos['quantity'] : 0,
                'price'         => isset($pos['price'])    ? round((float)$pos['price'] / 100, 2) : 0,
                'discount'      => isset($pos['discount']) ? (float)$pos['discount'] : 0,
                'vat'           => isset($pos['vat'])      ? (float)$pos['vat'] : 0,
                'sum_row'       => isset($pos['sum'])      ? round((float)$pos['sum'] / 100, 2) : 0,
            );
        }
        $repo->syncItemsFromMs($localId, $positions, $productMap);
    }

    $processed++;
    mswhk_demand_log(($action === 'CREATE' ? 'Inserted' : 'Updated') . ' demand id=' . $localId . ' ms=' . $msId . ' status=' . $status);
}

mswhk_demand_log('Done: processed=' . $processed . ' errors=' . count($errors));