<?php
/**
 * МойСклад → Papir webhook для customerorder.
 * Принимает события CREATE/UPDATE/DELETE для customerorder.
 *
 * Relay: http://159.69.1.229/order_relay.php → https://papir.officetorg.com.ua/customerorder/webhook/moysklad
 * Логи: /var/www/papir/storage/ms_webhook_customerorder.log
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';
require_once __DIR__ . '/../../moysklad/src/WebhookCpHelper.php';
require_once __DIR__ . '/../MsAttributesParser.php';

function mswhk_order_log($msg) {
    @file_put_contents('/var/www/papir/storage/ms_webhook_customerorder.log',
        date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

mswhk_order_log('Incoming: ' . $raw);

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

$ms        = new MoySkladApi();
$processed = 0;
$errors    = array();

foreach ($body['events'] as $event) {
    $action = isset($event['action'])       ? strtoupper($event['action'])       : '';
    $type   = isset($event['meta']['type']) ? strtolower($event['meta']['type']) : '';
    $href   = isset($event['meta']['href']) ? (string)$event['meta']['href']     : '';

    if ($type !== 'customerorder') continue;

    $pos  = strrpos($href, '/');
    $msId = ($pos !== false) ? substr($href, $pos + 1) : '';

    if ($msId === '') { $errors[] = 'No UUID in href: ' . $href; continue; }

    if ($action === 'DELETE') {
        mswhk_order_delete($msId, $errors);
        $processed++;
        continue;
    }

    $docRaw = $ms->query($href . '?expand=agent,organization,state,owner,positions.assortment,attributes');
    $doc    = json_decode(json_encode($docRaw), true);

    if (empty($doc) || !empty($doc['errors'])) {
        $errors[] = 'Fetch failed: customerorder/' . $msId;
        mswhk_order_log('Fetch error ' . $msId . ': ' . json_encode($doc));
        continue;
    }

    try {
        mswhk_order_upsert($doc, $ms, $errors);
        $processed++;
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        mswhk_order_log('Exception ' . $msId . ': ' . $e->getMessage());
    }
}

mswhk_order_log('Done: processed=' . $processed . ' errors=' . count($errors));
exit;

/* ─────────────────────────────────────────────────────── */

function mswhk_order_uuid($href)
{
    $pos = strrpos((string)$href, '/');
    return ($pos !== false) ? substr($href, $pos + 1) : '';
}

function mswhk_order_upsert(array $doc, MoySkladApi $ms, array &$errors)
{
    $msId      = isset($doc['id'])           ? trim((string)$doc['id'])              : '';
    $extCode   = isset($doc['externalCode']) ? trim((string)$doc['externalCode'])    : null;
    $number    = isset($doc['name'])         ? trim((string)$doc['name'])            : null;
    $moment    = isset($doc['moment'])       ? substr((string)$doc['moment'], 0, 19) : null;
    $desc      = isset($doc['description'])  ? (string)$doc['description']           : null;
    $applicable = !empty($doc['applicable']) ? 1 : 0;

    if ($msId === '') { $errors[] = 'Document missing id'; return; }

    $sumTotal    = isset($doc['sum'])         ? round((float)$doc['sum']         / 100, 2) : 0.0;
    $sumPaid     = isset($doc['payedSum'])    ? round((float)$doc['payedSum']    / 100, 2) : 0.0;
    $sumShipped  = isset($doc['shippedSum'])  ? round((float)$doc['shippedSum']  / 100, 2) : 0.0;
    $sumReserved = isset($doc['reservedSum']) ? round((float)$doc['reservedSum'] / 100, 2) : 0.0;

    // Контрагент — найти или создать
    $counterpartyId = null;
    if (!empty($doc['agent']['meta']['href'])) {
        $agentMs  = mswhk_order_uuid($doc['agent']['meta']['href']);
        $agentDoc = isset($doc['agent']) && is_array($doc['agent']) ? $doc['agent'] : array();
        $counterpartyId = mswhk_cp_resolve($agentMs, $agentDoc, 'mswhk_order_log');
    }

    // Организация
    $organizationId = null;
    if (!empty($doc['organization']['meta']['href'])) {
        $orgMs = mswhk_order_uuid($doc['organization']['meta']['href']);
        $r = Database::fetchRow('Papir',
            "SELECT id FROM organization WHERE id_ms = '" . Database::escape('Papir', $orgMs) . "' LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) $organizationId = (int)$r['row']['id'];
    }

    // Статус из UUID → order_status_ms_mapping
    $status = null;
    if (!empty($doc['state']['meta']['href'])) {
        $stateMs = mswhk_order_uuid($doc['state']['meta']['href']);
        $r = Database::fetchRow('Papir',
            "SELECT papir_code FROM order_status_ms_mapping WHERE ms_state_id = '" . Database::escape('Papir', $stateMs) . "' LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) $status = $r['row']['papir_code'];
    }

    // Менеджер
    $managerEmployeeId = null;
    if (!empty($doc['owner']['meta']['href'])) {
        $ownerMs = mswhk_order_uuid($doc['owner']['meta']['href']);
        $r = Database::fetchRow('Papir',
            "SELECT id FROM employee WHERE id_ms = '" . Database::escape('Papir', $ownerMs) . "' LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) $managerEmployeeId = (int)$r['row']['id'];
    }

    // payment_status
    if ($sumPaid <= 0) {
        $paymentStatus = 'not_paid';
    } elseif ($sumPaid >= $sumTotal && $sumTotal > 0) {
        $paymentStatus = 'paid';
    } else {
        $paymentStatus = 'partially_paid';
    }

    // shipment_status
    if ($sumShipped <= 0 && $sumReserved <= 0) {
        $shipmentStatus = 'not_shipped';
    } elseif ($sumShipped <= 0 && $sumReserved > 0) {
        $shipmentStatus = 'reserved';
    } elseif ($sumShipped > 0 && $sumShipped < $sumTotal) {
        $shipmentStatus = 'partially_shipped';
    } else {
        $shipmentStatus = 'shipped';
    }

    // Атрибути: спосіб доставки та оплати
    // МС повертає attributes як plain array (не {rows:[...]}), тому перевіряємо обидва варіанти
    $attrParser = new MsAttributesParser();
    if (!empty($doc['attributes']['rows']) && is_array($doc['attributes']['rows'])) {
        $attrs = $doc['attributes']['rows'];
    } elseif (!empty($doc['attributes']) && is_array($doc['attributes'])) {
        $attrs = $doc['attributes'];
    } else {
        $attrs = array();
    }
    $parsedAttrs = $attrParser->parse($attrs);

    $data = array(
        'id_ms'           => $msId,
        'number'          => $number,
        'moment'          => $moment,
        'applicable'      => $applicable,
        'source'          => 'moysklad',
        'sync_state'      => 'synced',
        'external_code'   => $extCode,
        'sum_paid'        => $sumPaid,
        'sum_shipped'     => $sumShipped,
        'sum_reserved'    => $sumReserved,
        'description'     => $desc,
        'payment_status'  => $paymentStatus,
        'shipment_status' => $shipmentStatus,
    );

    if ($parsedAttrs['delivery_method_id'] !== null) $data['delivery_method_id'] = $parsedAttrs['delivery_method_id'];
    if ($parsedAttrs['payment_method_id']  !== null) $data['payment_method_id']  = $parsedAttrs['payment_method_id'];

    if ($counterpartyId    !== null) $data['counterparty_id']     = $counterpartyId;
    if ($organizationId    !== null) $data['organization_id']     = $organizationId;
    if ($status            !== null) $data['status']              = $status;
    if ($managerEmployeeId !== null) $data['manager_employee_id'] = $managerEmployeeId;

    $existing = Database::fetchRow('Papir',
        "SELECT id, status FROM customerorder WHERE id_ms = '" . Database::escape('Papir', $msId) . "' LIMIT 1");

    if ($existing['ok'] && !empty($existing['row'])) {
        $localId   = (int)$existing['row']['id'];
        $oldStatus = (string)$existing['row']['status'];

        Database::update('Papir', 'customerorder', $data, array('id' => $localId));

        if ($status !== null && $status !== $oldStatus) {
            Database::insert('Papir', 'customerorder_history', array(
                'customerorder_id' => $localId,
                'event_type'       => 'status_change',
                'field_name'       => 'status',
                'old_value'        => $oldStatus,
                'new_value'        => $status,
                'is_auto'          => 1,
                'comment'          => 'webhook МС',
            ));
        }
        mswhk_order_log('Updated order id=' . $localId . ' ms=' . $msId . ' status=' . $status);
    } else {
        $data['uuid'] = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
        if (!isset($data['status'])) $data['status'] = 'new';

        $ins = Database::insert('Papir', 'customerorder', $data);
        if (!$ins['ok']) { $errors[] = 'Insert failed for ' . $msId; return; }
        $localId = (int)$ins['insert_id'];
        mswhk_order_log('Inserted order id=' . $localId . ' ms=' . $msId);
    }

    // Обновить last_activity_at контрагента — чтобы заказ поднял его в инбоксе
    if ($counterpartyId !== null && $moment !== null) {
        Database::query('Papir',
            "UPDATE counterparty
             SET last_activity_at = GREATEST(COALESCE(last_activity_at, '1970-01-01 00:00:00'), '" . Database::escape('Papir', $moment) . "')
             WHERE id = {$counterpartyId}"
        );
    }

    mswhk_order_sync_items($localId, $doc, $ms);
}

function mswhk_order_sync_items($localId, array $doc, MoySkladApi $ms)
{
    $positions = array();

    if (!empty($doc['positions']['rows']) && is_array($doc['positions']['rows'])) {
        $positions = $doc['positions']['rows'];
    }
    if (empty($positions) && !empty($doc['positions']['meta']['href'])) {
        $posRaw  = $ms->query($doc['positions']['meta']['href'] . '?limit=100&expand=assortment');
        $posData = json_decode(json_encode($posRaw), true);
        if (!empty($posData['rows'])) $positions = $posData['rows'];
    }

    if (empty($positions)) return;

    $existingItems = Database::fetchAll('Papir',
        "SELECT id, position_ms_id FROM customerorder_item WHERE customerorder_id = {$localId}");
    $existingByPositionMsId = array();
    if ($existingItems['ok']) {
        foreach ($existingItems['rows'] as $ei) {
            if ($ei['position_ms_id']) $existingByPositionMsId[$ei['position_ms_id']] = (int)$ei['id'];
        }
    }

    $lineNo      = 1;
    $seenItemIds = array();

    foreach ($positions as $pos) {
        $positionMsId = isset($pos['id']) ? (string)$pos['id'] : '';
        $qty         = isset($pos['quantity']) ? (float)$pos['quantity']               : 0.0;
        $price       = isset($pos['price'])    ? round((float)$pos['price'] / 100, 4)  : 0.0;
        $discountPct = isset($pos['discount']) ? (float)$pos['discount']               : 0.0;
        $vatRate     = isset($pos['vat'])      ? (float)$pos['vat']                    : 0.0;

        $productMsId = '';
        $productName = '';
        $sku         = '';
        $productId   = null;

        $assortment = isset($pos['assortment']) && is_array($pos['assortment']) ? $pos['assortment'] : array();
        if (!empty($assortment['id'])) {
            $productMsId = (string)$assortment['id'];
            $productName = isset($assortment['name'])    ? (string)$assortment['name']    : '';
            $sku         = isset($assortment['article']) ? (string)$assortment['article'] : '';

            $pr = Database::fetchRow('Papir',
                "SELECT product_id, product_article FROM product_papir WHERE id_ms = '" . Database::escape('Papir', $productMsId) . "' LIMIT 1");
            if ($pr['ok'] && !empty($pr['row'])) {
                $productId = (int)$pr['row']['product_id'];
                if ($sku === '') $sku = (string)$pr['row']['product_article'];
            }
        }

        $gross          = round($qty * $price, 2);
        $discountAmount = round($gross * $discountPct / 100, 2);
        $sumRow         = round($gross - $discountAmount, 2);
        $vatAmount      = $vatRate > 0 ? round($sumRow - $sumRow / (1 + $vatRate / 100), 2) : 0.0;

        $itemData = array(
            'customerorder_id'     => $localId,
            'line_no'              => $lineNo,
            'product_id'           => $productId,
            'position_ms_id'       => $positionMsId ?: null,
            'product_ms_id'        => $productMsId ?: null,
            'product_name'         => mb_substr($productName, 0, 255, 'UTF-8'),
            'sku'                  => mb_substr($sku, 0, 64, 'UTF-8'),
            'quantity'             => $qty,
            'price'                => $price,
            'discount_percent'     => $discountPct,
            'discount_amount'      => $discountAmount,
            'vat_rate'             => $vatRate,
            'vat_amount'           => $vatAmount,
            'sum_without_discount' => $gross,
            'sum_row'              => $sumRow,
        );

        if ($positionMsId !== '' && isset($existingByPositionMsId[$positionMsId])) {
            $itemId = $existingByPositionMsId[$positionMsId];
            Database::update('Papir', 'customerorder_item', $itemData, array('id' => $itemId));
            $seenItemIds[] = $itemId;
        } else {
            $ins = Database::insert('Papir', 'customerorder_item', $itemData);
            if ($ins['ok']) $seenItemIds[] = (int)$ins['insert_id'];
        }

        $lineNo++;
    }

    if (!empty($seenItemIds)) {
        $inClause = implode(',', $seenItemIds);
        Database::query('Papir',
            "DELETE FROM customerorder_item WHERE customerorder_id = {$localId} AND id NOT IN ({$inClause})");
    }

    // sum_items / sum_discount / sum_vat / sum_total оновлюються автоматично
    // тригерами trg_co_item_after_insert/update/delete на customerorder_item
}

function mswhk_order_delete($msId, array &$errors)
{
    $r = Database::query('Papir',
        "UPDATE customerorder SET deleted_at = NOW()
         WHERE id_ms = '" . Database::escape('Papir', $msId) . "' AND deleted_at IS NULL");
    mswhk_order_log('DELETE order ms=' . $msId
        . ' affected=' . (isset($r['affected_rows']) ? $r['affected_rows'] : '?'));
}
