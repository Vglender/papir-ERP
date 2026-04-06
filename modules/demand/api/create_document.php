<?php
/**
 * POST /demand/api/create_document
 * Creates a linked document from a demand (source).
 *
 * Params:
 *   demand_id  — source demand.id
 *   to_type    — target document type (salesreturn | return_logistics)
 *   link_type  — value for document_link.link_type
 *
 * For return_logistics also:
 *   return_type  — novaposhta_ttn | ukrposhta_ttn | manual | left_with_client
 *   ttn_number   — required when return_type is *_ttn
 *   description  — optional comment
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../demand_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$demandId = isset($_POST['demand_id']) ? (int)$_POST['demand_id']  : 0;
$toType   = isset($_POST['to_type'])   ? trim($_POST['to_type'])    : '';
$linkType = isset($_POST['link_type']) ? trim($_POST['link_type'])  : '';

if ($demandId <= 0 || $toType === '') {
    echo json_encode(array('ok' => false, 'error' => 'demand_id and to_type required'));
    exit;
}

// ── Load source demand ────────────────────────────────────────────────────────

$rDemand = Database::fetchRow('Papir',
    "SELECT d.*,
            o.id_ms   AS org_ms,
            cp.id_ms  AS cp_ms,
            co.organization_id AS order_org_id,
            co.store_id        AS order_store_id,
            co.id_ms           AS order_ms
     FROM demand d
     LEFT JOIN customerorder co ON co.id = d.customerorder_id
     LEFT JOIN organization  o  ON o.id  = co.organization_id
     LEFT JOIN counterparty  cp ON cp.id = d.counterparty_id
     WHERE d.id = {$demandId} AND d.deleted_at IS NULL
     LIMIT 1");

if (!$rDemand['ok'] || empty($rDemand['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Demand not found'));
    exit;
}
$demand = $rDemand['row'];

// ── Load demand items ─────────────────────────────────────────────────────────

$rItems = Database::fetchAll('Papir',
    "SELECT di.product_id,
            COALESCE(di.product_ms_id, pp.id_ms) AS product_ms_id,
            COALESCE(NULLIF(di.product_name,''), pd_uk.name, pd_ru.name, '') AS product_name,
            COALESCE(NULLIF(di.sku,''), pp.product_article, '') AS sku,
            di.quantity, di.price, di.discount_percent, di.vat_rate,
            di.sum_row, di.line_no
     FROM demand_item di
     LEFT JOIN product_papir pp        ON pp.product_id = di.product_id
     LEFT JOIN product_description pd_uk ON pd_uk.product_id = di.product_id AND pd_uk.language_id = 2
     LEFT JOIN product_description pd_ru ON pd_ru.product_id = di.product_id AND pd_ru.language_id = 1
     WHERE di.demand_id = {$demandId}
     ORDER BY di.line_no ASC");
$items = ($rItems['ok'] && !empty($rItems['rows'])) ? $rItems['rows'] : array();

// ── Helpers ───────────────────────────────────────────────────────────────────

function cd_uuid()
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}

function cd_link($fromType, $fromId, $toType, $toId, $linkType, $linkedSum)
{
    Database::insert('Papir', 'document_link', array(
        'from_type'  => $fromType,
        'from_id'    => $fromId,
        'to_type'    => $toType,
        'to_id'      => $toId,
        'link_type'  => $linkType,
        'linked_sum' => $linkedSum,
    ));
}

// ── CREATE salesreturn ────────────────────────────────────────────────────────

if ($toType === 'salesreturn') {

    $sumTotal = 0.0;
    foreach ($items as $it) { $sumTotal += (float)$it['sum_row']; }
    $sumTotal = round($sumTotal * 100) / 100;

    $rIns = Database::insert('Papir', 'salesreturn', array(
        'uuid'           => cd_uuid(),
        'source'         => 'papir',
        'moment'         => date('Y-m-d H:i:s'),
        'applicable'     => 0,
        'counterparty_id'=> !empty($demand['counterparty_id']) ? (int)$demand['counterparty_id'] : null,
        'demand_id'      => $demandId,
        'sum_total'      => $sumTotal,
        'sum_paid'       => 0,
        'description'    => $demand['description'] !== null ? $demand['description'] : null,
        'sync_state'     => 'new',
    ));
    if (!$rIns['ok'] || empty($rIns['insert_id'])) {
        echo json_encode(array('ok' => false, 'error' => 'Failed to create salesreturn'));
        exit;
    }
    $newId = (int)$rIns['insert_id'];

    // Copy items → salesreturn_item
    $ln = 1;
    foreach ($items as $it) {
        $pid   = !empty($it['product_id'])   ? (int)$it['product_id']  : null;
        $pmsId = !empty($it['product_ms_id']) ? $it['product_ms_id']   : null;
        $qty   = (float)$it['quantity'];
        $price = (float)$it['price'];
        $disc  = (float)$it['discount_percent'];
        $gross = round($qty * $price * 100) / 100;
        $discAmt = round($gross * $disc / 100 * 100) / 100;
        $sumRow  = round(($gross - $discAmt) * 100) / 100;

        $pmsEsc = $pmsId  ? "'" . Database::escape('Papir', $pmsId) . "'" : 'NULL';
        $pidSql = $pid    ? $pid : 'NULL';

        Database::query('Papir',
            "INSERT INTO salesreturn_item (salesreturn_id, line_no, product_id, product_ms_id, quantity, price, sum_row)
             VALUES ({$newId}, {$ln}, {$pidSql}, {$pmsEsc}, {$qty}, {$price}, {$sumRow})");
        $ln++;
    }

    // document_link: from=salesreturn → to=demand
    $ltype = $linkType !== '' ? $linkType : 'return';
    cd_link('salesreturn', $newId, 'demand', $demandId, $ltype, $sumTotal);

    // ── Push salesreturn to МойСклад ──────────────────────────────────────────

    $syncResult = array('ok' => false, 'error' => 'skipped');

    if (!empty($demand['id_ms']) && !empty($demand['org_ms'])) {
        $ms = new MoySkladApi();
        $base = $ms->getEntityBaseUrl();

        $payload = array(
            'uuid'         => cd_uuid(),
            'applicable'   => false,
            'organization' => array('meta' => array(
                'href' => $base . 'organization/' . $demand['org_ms'],
                'type' => 'organization', 'mediaType' => 'application/json',
            )),
            'demand' => array('meta' => array(
                'href' => $base . 'demand/' . $demand['id_ms'],
                'type' => 'demand', 'mediaType' => 'application/json',
            )),
        );

        if (!empty($demand['cp_ms'])) {
            $payload['agent'] = array('meta' => array(
                'href' => $base . 'counterparty/' . $demand['cp_ms'],
                'type' => 'counterparty', 'mediaType' => 'application/json',
            ));
        }
        if (!empty($demand['description'])) {
            $payload['description'] = $demand['description'];
        }

        $positions = array();
        foreach ($items as $it) {
            if (empty($it['product_ms_id'])) continue;
            $positions[] = array(
                'quantity'   => (float)$it['quantity'],
                'price'      => (int)round((float)$it['price'] * 100),
                'discount'   => (float)$it['discount_percent'],
                'vat'        => (float)$it['vat_rate'],
                'assortment' => array('meta' => array(
                    'href' => $base . 'product/' . $it['product_ms_id'],
                    'type' => 'product', 'mediaType' => 'application/json',
                )),
            );
        }
        if (!empty($positions)) $payload['positions'] = $positions;

        $msResult = $ms->querySend($base . 'salesreturn', $payload, 'POST');

        if (!empty($msResult['id'])) {
            Database::update('Papir', 'salesreturn',
                array('id_ms' => $msResult['id'], 'number' => !empty($msResult['name']) ? $msResult['name'] : null,
                      'sync_state' => 'synced'),
                array('id' => $newId));
            $syncResult = array('ok' => true, 'ms_id' => $msResult['id']);
        } elseif (!empty($msResult['errors'])) {
            $msgs = array();
            foreach ((array)$msResult['errors'] as $e) {
                $msgs[] = isset($e['error']) ? $e['error'] : json_encode($e);
            }
            $errMsg = implode('; ', $msgs);
            Database::update('Papir', 'salesreturn',
                array('sync_state' => 'error'), array('id' => $newId));
            $syncResult = array('ok' => false, 'error' => $errMsg);
        }
    }

    echo json_encode(array(
        'ok'   => true,
        'id'   => $newId,
        'type' => 'salesreturn',
        'sync' => $syncResult,
        'msg'  => 'Повернення покупця створено',
    ));
    exit;
}

// ── CREATE return_logistics ───────────────────────────────────────────────────

if ($toType === 'return_logistics') {

    $returnType  = isset($_POST['return_type'])  ? trim($_POST['return_type'])   : 'manual';
    $ttnNumber   = isset($_POST['ttn_number'])   ? trim($_POST['ttn_number'])    : '';
    $description = isset($_POST['description'])  ? trim($_POST['description'])   : '';

    $allowed = array('novaposhta_ttn', 'ukrposhta_ttn', 'manual', 'left_with_client');
    if (!in_array($returnType, $allowed)) {
        echo json_encode(array('ok' => false, 'error' => 'Invalid return_type'));
        exit;
    }
    if (in_array($returnType, array('novaposhta_ttn', 'ukrposhta_ttn')) && $ttnNumber === '') {
        echo json_encode(array('ok' => false, 'error' => 'Введіть номер ТТН'));
        exit;
    }

    $orderId = !empty($demand['customerorder_id']) ? (int)$demand['customerorder_id'] : null;

    $data = array(
        'demand_id'        => $demandId,
        'customerorder_id' => $orderId,
        'return_type'      => $returnType,
        'status'           => ($returnType === 'left_with_client') ? 'received' : 'expected',
    );
    if ($ttnNumber !== '') $data['return_ttn_number'] = $ttnNumber;
    if ($description !== '') $data['comment'] = $description;
    if ($returnType === 'left_with_client') $data['received_at'] = date('Y-m-d');

    $rIns = Database::insert('Papir', 'return_logistics', $data);
    if (!$rIns['ok'] || empty($rIns['insert_id'])) {
        echo json_encode(array('ok' => false, 'error' => 'Failed to create return_logistics'));
        exit;
    }
    $newId = (int)$rIns['insert_id'];

    // document_link: from=return_logistics → to=demand
    $ltype = $linkType !== '' ? $linkType : 'logistics';
    cd_link('return_logistics', $newId, 'demand', $demandId, $ltype, 0);

    echo json_encode(array(
        'ok'   => true,
        'id'   => $newId,
        'type' => 'return_logistics',
        'msg'  => 'Логістику повернення зареєстровано',
    ));
    exit;
}

// ── Unsupported type ──────────────────────────────────────────────────────────

echo json_encode(array(
    'ok'    => false,
    'error' => 'Тип документа "' . htmlspecialchars($toType) . '" не підтримується',
    'todo'  => true,
));