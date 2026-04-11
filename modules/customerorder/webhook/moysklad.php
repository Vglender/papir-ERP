<?php
require_once __DIR__ . '/../../integrations/AppRegistry.php';
AppRegistry::guard('moysklad');
require_once __DIR__ . '/../../integrations/IntegrationSettingsService.php';
require_once __DIR__ . '/../../integrations/MsExchangeGuard.php';
if (IntegrationSettingsService::get('moysklad', 'wh_customerorder', '1') !== '1') {
    header('Content-Type: application/json');
    echo json_encode(array('ok' => true, 'skipped' => true, 'reason' => 'webhook_disabled'));
    exit;
}

/**
 * МойСклад → Papir webhook для customerorder.
 * Принимает події CREATE/UPDATE/DELETE для customerorder.
 *
 * Relay: http://159.69.1.229/order_relay.php → https://papir.officetorg.com.ua/customerorder/webhook/moysklad
 * Логи: /var/www/papir/storage/ms_webhook_customerorder.log
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';
require_once __DIR__ . '/../../moysklad/src/WebhookCpHelper.php';
require_once __DIR__ . '/../MsAttributesParser.php';
require_once __DIR__ . '/../../shared/DocumentHistory.php';
require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';
require_once __DIR__ . '/../services/OrderFinanceHelper.php';
require_once __DIR__ . '/../services/OrderOrgResolver.php';

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
        if (MsExchangeGuard::isAllowed('order', 'D', 'from')) {
            mswhk_order_delete($msId, $errors);
            $processed++;
        }
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
    $waitCall  = ($desc !== null && mb_stripos($desc, 'Чекаю на дзвінок') !== false) ? 1 : 0;

    if ($msId === '') { $errors[] = 'Document missing id'; return; }

    $sumTotal    = isset($doc['sum'])         ? round((float)$doc['sum']         / 100, 2) : 0.0;

    // Контрагент — найти или создать
    $counterpartyId = null;
    if (!empty($doc['agent']['meta']['href'])) {
        $agentMs  = mswhk_order_uuid($doc['agent']['meta']['href']);
        $agentDoc = isset($doc['agent']) && is_array($doc['agent']) ? $doc['agent'] : array();
        $counterpartyId = mswhk_cp_resolve($agentMs, $agentDoc, 'mswhk_order_log');
    }

    // Организация: спочатку за UUID з МС, якщо не знайдено —
    // fallback через OrderOrgResolver (VAT-статус контрагента).
    $organizationId = null;
    if (!empty($doc['organization']['meta']['href'])) {
        $orgMs = mswhk_order_uuid($doc['organization']['meta']['href']);
        $r = Database::fetchRow('Papir',
            "SELECT id FROM organization WHERE id_ms = '" . Database::escape('Papir', $orgMs) . "' LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) $organizationId = (int)$r['row']['id'];
    }
    if ($organizationId === null) {
        $resolved = OrderOrgResolver::resolve($counterpartyId, '', (string)$desc);
        if (!empty($resolved['organization_id'])) {
            $organizationId = (int)$resolved['organization_id'];
        }
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

    // payment_status / shipment_status — розраховуються після upsert
    // через OrderFinanceHelper::recalc() із локальних документів

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
        'sum_total'       => $sumTotal,
        'description'     => $desc,
        'wait_call'       => $waitCall,
    );

    if ($parsedAttrs['delivery_method_id'] !== null) $data['delivery_method_id'] = $parsedAttrs['delivery_method_id'];
    if ($parsedAttrs['payment_method_id']  !== null) $data['payment_method_id']  = $parsedAttrs['payment_method_id'];

    if ($counterpartyId    !== null) $data['counterparty_id']     = $counterpartyId;
    if ($organizationId    !== null) $data['organization_id']     = $organizationId;
    // Статус НЕ пишемо для існуючих замовлень — Papir є джерелом правди.
    // Для нових — ставимо тільки якщо це ранній статус (draft/new/confirmed).
    if ($managerEmployeeId !== null) $data['manager_employee_id'] = $managerEmployeeId;

    $existing = Database::fetchRow('Papir',
        "SELECT id, status FROM customerorder WHERE id_ms = '" . Database::escape('Papir', $msId) . "' LIMIT 1");

    $isNew = false;

    if ($existing['ok'] && !empty($existing['row'])) {
        // UPDATE from MS — check CRUD guard
        if (!MsExchangeGuard::isAllowed('order', 'U', 'from')) {
            mswhk_order_log('CRUD SKIP update from MS for order ms=' . $msId);
            return;
        }

        $localId   = (int)$existing['row']['id'];
        $oldStatus = (string)$existing['row']['status'];

        // Статус з МС НЕ перезаписуємо — Papir керує статусами через сценарії та ТТН-тригери.
        // Логуємо для аудиту що МС хотів змінити, але не застосовуємо.
        if ($status !== null && $status !== $oldStatus) {
            mswhk_order_log('IGNORED status from MS: ' . $oldStatus . ' → ' . $status . ' for order id=' . $localId);
        }

        Database::update('Papir', 'customerorder', $data, array('id' => $localId));

        OrderFinanceHelper::recalc($localId);
        mswhk_order_log('Updated order id=' . $localId . ' ms=' . $msId . ' (status kept: ' . $oldStatus . ')');
    } else {
        // CREATE from MS — check CRUD guard
        if (!MsExchangeGuard::isAllowed('order', 'C', 'from')) {
            mswhk_order_log('CRUD SKIP create from MS for order ms=' . $msId);
            return;
        }

        $isNew = true;
        $data['uuid'] = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
        // Для нового замовлення: приймаємо тільки ранні статуси з МС.
        // Пізні (shipped/received/completed/return) ігноруємо — Papir вирішує сам.
        $earlyStatuses = array('draft', 'new', 'confirmed', 'waiting_payment', 'in_progress');
        if ($status !== null && in_array($status, $earlyStatuses)) {
            $data['status'] = $status;
        }
        if (!isset($data['status'])) $data['status'] = 'new';

        $ins = Database::insert('Papir', 'customerorder', $data);
        if (!$ins['ok']) { $errors[] = 'Insert failed for ' . $msId; return; }
        $localId = (int)$ins['insert_id'];
        OrderFinanceHelper::recalc($localId);
        mswhk_order_log('Inserted order id=' . $localId . ' ms=' . $msId);

        // Fire order_created trigger
        TriggerEngine::fire('order_created', array(
            'order'           => array_merge($data, array('id' => $localId)),
            'order_id'        => $localId,
            'counterparty_id' => isset($counterpartyId) ? (int)$counterpartyId : 0,
        ));
    }

    // Обновить last_activity_at контрагента — чтобы заказ поднял его в инбоксе
    if ($counterpartyId !== null && $moment !== null) {
        Database::query('Papir',
            "UPDATE counterparty
             SET last_activity_at = GREATEST(COALESCE(last_activity_at, '1970-01-01 00:00:00'), '" . Database::escape('Papir', $moment) . "')
             WHERE id = {$counterpartyId}"
        );
    }

    // Позиции синхронизируются из МС только при первичном импорте нового заказа.
    // Для существующих заказов Papir является источником правды по позициям —
    // вебхук МС обновляет только поля шапки (статус, оплаты, отгрузку).
    // Дані доставки: заглядаємо в oc_order і зберігаємо в customerorder_shipping
    if ($isNew) {
        mswhk_order_sync_shipping($localId, $number, $counterpartyId);
        mswhk_order_sync_items($localId, $doc, $ms);
    }
}

/**
 * Зберегти дані доставки з oc_order у customerorder_shipping.
 * Тимчасовий костиль — до імпорту заказів напряму (без МС).
 */
function mswhk_order_sync_shipping($localId, $orderNumber, $counterpartyId)
{
    if (!$orderNumber) return;
    if (!preg_match('/^(\d+)(OFF|MFF)$/i', $orderNumber, $m)) return;

    $ocOrderId = (int)$m[1];
    $db = (strtoupper($m[2]) === 'MFF') ? 'mff' : 'off';

    $rOc = Database::fetchRow($db,
        "SELECT o.shipping_firstname, o.shipping_lastname, o.telephone,
                o.shipping_city, o.shipping_address_1,
                o.shipping_method, o.shipping_code, o.novaposhta_cn_ref, o.shipping_postcode,
                sf.shipping_street, sf.shipping_house, sf.shipping_flat, sf.no_call
         FROM oc_order o
         LEFT JOIN oc_order_simple_fields sf ON sf.order_id = o.order_id
         WHERE o.order_id = {$ocOrderId} LIMIT 1");

    if (!$rOc['ok'] || empty($rOc['row'])) return;
    $oc = $rOc['row'];

    $hasData = !empty($oc['shipping_city']) || !empty($oc['shipping_address_1'])
            || !empty($oc['shipping_firstname']) || !empty($oc['telephone']);
    if (!$hasData) return;

    // Перевірити чи вже є
    $rEx = Database::fetchRow('Papir',
        "SELECT id FROM customerorder_shipping WHERE customerorder_id = {$localId} LIMIT 1");
    if ($rEx['ok'] && !empty($rEx['row'])) return;

    $noCall = (!empty($oc['no_call']) && mb_strtolower(trim($oc['no_call']), 'UTF-8') !== 'так') ? 1 : 0;

    Database::insert('Papir', 'customerorder_shipping', array(
        'customerorder_id'      => $localId,
        'counterparty_id'       => $counterpartyId ? (int)$counterpartyId : null,
        'recipient_first_name'  => $oc['shipping_firstname'] ?: null,
        'recipient_last_name'   => $oc['shipping_lastname'] ?: null,
        'recipient_phone'       => $oc['telephone'] ?: null,
        'city_name'             => $oc['shipping_city'] ?: null,
        'branch_name'           => $oc['shipping_address_1'] ?: null,
        'np_warehouse_ref'      => $oc['novaposhta_cn_ref'] ?: null,
        'street'                => !empty($oc['shipping_street']) ? mb_substr($oc['shipping_street'], 0, 128, 'UTF-8') : null,
        'house'                 => !empty($oc['shipping_house']) ? mb_substr($oc['shipping_house'], 0, 128, 'UTF-8') : null,
        'flat'                  => !empty($oc['shipping_flat']) ? mb_substr($oc['shipping_flat'], 0, 128, 'UTF-8') : null,
        'postcode'              => !empty($oc['shipping_postcode']) ? $oc['shipping_postcode'] : null,
        'delivery_code'         => $oc['shipping_code'] ?: null,
        'delivery_method_name'  => $oc['shipping_method'] ? trim($oc['shipping_method']) : null,
        'no_call'               => $noCall,
        'source'                => 'site_' . $db,
    ));
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
        "SELECT id, position_ms_id, product_id, updated_at FROM customerorder_item WHERE customerorder_id = {$localId}");
    $existingByPositionMsId = array();
    // Fallback map: items without position_ms_id, indexed by product_id (queue — for duplicate products)
    $existingByProductId    = array();
    // Map id → updated_at for recency check
    $itemUpdatedAt          = array();
    if ($existingItems['ok']) {
        foreach ($existingItems['rows'] as $ei) {
            $itemUpdatedAt[(int)$ei['id']] = $ei['updated_at'];
            if ($ei['position_ms_id']) {
                $existingByPositionMsId[$ei['position_ms_id']] = (int)$ei['id'];
            } elseif ($ei['product_id']) {
                // Items saved from Papir (no position_ms_id): queue by product_id
                if (!isset($existingByProductId[(int)$ei['product_id']])) {
                    $existingByProductId[(int)$ei['product_id']] = array();
                }
                $existingByProductId[(int)$ei['product_id']][] = (int)$ei['id'];
            }
        }
    }

    // Window within which a Papir-side save is considered "fresh" (seconds).
    // Webhook calls arriving in this window will NOT overwrite quantity/price
    // for items that were just saved via Papir UI to prevent race-condition rollback.
    $freshWindowSec = 30;
    $nowTs          = time();

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
            } elseif ($sku !== '') {
                // Fallback: lookup by article (SKU) when id_ms not found in product_papir
                $pr2 = Database::fetchRow('Papir',
                    "SELECT product_id FROM product_papir WHERE product_article = '" . Database::escape('Papir', $sku) . "' LIMIT 1");
                if ($pr2['ok'] && !empty($pr2['row'])) {
                    $productId = (int)$pr2['row']['product_id'];
                }
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
            // Matched by position_ms_id — standard path
            $itemId = $existingByPositionMsId[$positionMsId];
            Database::update('Papir', 'customerorder_item', $itemData, array('id' => $itemId));
            $seenItemIds[] = $itemId;
        } elseif ($productId !== null && isset($existingByProductId[$productId]) && !empty($existingByProductId[$productId])) {
            // Matched by product_id — item was saved from Papir (no position_ms_id yet)
            $itemId = array_shift($existingByProductId[$productId]);
            // Always persist position_ms_id so future webhooks match properly
            $itemData['position_ms_id'] = $positionMsId ?: null;

            // Guard: if this item was updated very recently from Papir, skip overwriting
            // quantity/price to avoid rolling back a user's just-saved change
            $updatedAt = isset($itemUpdatedAt[$itemId]) ? $itemUpdatedAt[$itemId] : null;
            if ($updatedAt && ($nowTs - strtotime($updatedAt)) < $freshWindowSec) {
                // Only update metadata, not the values the user just set
                $safeData = array(
                    'line_no'        => $itemData['line_no'],
                    'position_ms_id' => $itemData['position_ms_id'],
                    'product_ms_id'  => $itemData['product_ms_id'],
                    'product_name'   => $itemData['product_name'],
                    'sku'            => $itemData['sku'],
                );
                Database::update('Papir', 'customerorder_item', $safeData, array('id' => $itemId));
                mswhk_order_log('Skipped qty/price overwrite for item id=' . $itemId . ' (updated ' . $updatedAt . ', fresh window)');
            } else {
                Database::update('Papir', 'customerorder_item', $itemData, array('id' => $itemId));
            }
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
