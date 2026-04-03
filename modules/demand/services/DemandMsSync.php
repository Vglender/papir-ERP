<?php
/**
 * DemandMsSync — Papir → МойСклад
 * POST (create) або PUT (update) відвантаження в МС.
 */
class DemandMsSync
{
    private $ms;

    // Papir status → МС state UUID
    private static $statusMap = array(
        'assembling' => '313e4d03-eaad-11eb-0a80-0d7d0002b683',
        'assembled'  => 'ac9137e7-eaa9-11eb-0a80-064900024c01',
        'shipped'    => 'ac913c39-eaa9-11eb-0a80-064900024c02',
        'arrived'    => '2e2bc3dc-5be1-11ee-0a80-0677000179a7',
        'transfer'   => '67350236-916f-11ec-0a80-084e0018453e',
        'robot'      => '1786f816-7890-11ed-0a80-01d4001fe449',
    );

    public function __construct()
    {
        $this->ms = new MoySkladApi();
    }

    public function push($demandId)
    {
        $demandId = (int)$demandId;

        $r = Database::fetchRow('Papir',
            "SELECT d.*, o.id_ms AS org_ms, cp.id_ms AS cp_ms,
                    co.id_ms AS order_ms,
                    COALESCE(st.id_ms,
                        (SELECT id_ms FROM store WHERE id = 1 LIMIT 1)) AS store_ms
             FROM demand d
             LEFT JOIN customerorder co ON co.id = d.customerorder_id
             LEFT JOIN organization o   ON o.id  = co.organization_id
             LEFT JOIN counterparty cp  ON cp.id = d.counterparty_id
             LEFT JOIN store st         ON st.id = co.store_id
             WHERE d.id = {$demandId} AND d.deleted_at IS NULL
             LIMIT 1");

        if (!$r['ok'] || empty($r['row'])) {
            return array('ok' => false, 'error' => 'Demand not found: ' . $demandId);
        }
        $demand = $r['row'];

        $ri = Database::fetchAll('Papir',
            "SELECT di.*,
                    COALESCE(di.product_ms_id, pp.id_ms) AS resolved_product_ms_id
             FROM demand_item di
             LEFT JOIN product_papir pp ON pp.product_id = di.product_id
             WHERE di.demand_id = {$demandId}
             ORDER BY di.line_no");
        $items = ($ri['ok'] && !empty($ri['rows'])) ? $ri['rows'] : array();

        $payload = $this->buildPayload($demand, $items);
        if (!$payload['ok']) {
            $this->markError($demandId, $payload['error']);
            return array('ok' => false, 'error' => $payload['error']);
        }

        $entityBase = $this->ms->getEntityBaseUrl();
        $msId = !empty($demand['id_ms']) ? $demand['id_ms'] : null;

        if ($msId) {
            $result  = $this->ms->querySend($entityBase . 'demand/' . $msId, $payload['data'], 'PUT');
            $created = false;
        } else {
            $result  = $this->ms->querySend($entityBase . 'demand', $payload['data'], 'POST');
            $created = true;
        }

        $result = json_decode(json_encode($result), true);

        if (empty($result) || !empty($result['errors'])) {
            $errMsg = $this->extractError($result);
            $this->markError($demandId, $errMsg);
            return array('ok' => false, 'error' => $errMsg);
        }

        $returnedMsId = !empty($result['id'])     ? (string)$result['id']     : $msId;
        $returnedNum  = !empty($result['name'])   ? (string)$result['name']   : null;

        $upd = array('sync_state' => 'synced', 'sync_error' => null);
        if ($created && $returnedMsId) $upd['id_ms']  = $returnedMsId;
        if ($returnedNum)              $upd['number'] = $returnedNum;
        Database::update('Papir', 'demand', $upd, array('id' => $demandId));

        return array('ok' => true, 'ms_id' => $returnedMsId, 'number' => $returnedNum, 'created' => $created);
    }

    private function buildPayload(array $demand, array $items)
    {
        $entityBase = $this->ms->getEntityBaseUrl();

        if (empty($demand['org_ms'])) {
            return array('ok' => false, 'error' => 'organization.id_ms is empty');
        }
        if (empty($demand['store_ms'])) {
            return array('ok' => false, 'error' => 'store.id_ms is empty');
        }

        $data = array(
            'applicable'   => !empty($demand['applicable']),
            'organization' => array('meta' => array(
                'href'      => $entityBase . 'organization/' . $demand['org_ms'],
                'type'      => 'organization',
                'mediaType' => 'application/json',
            )),
            'store' => array('meta' => array(
                'href'      => $entityBase . 'store/' . $demand['store_ms'],
                'type'      => 'store',
                'mediaType' => 'application/json',
            )),
        );

        if (!empty($demand['moment'])) $data['moment'] = $demand['moment'];
        if (!empty($demand['description'])) $data['description'] = $demand['description'];

        if (!empty($demand['cp_ms'])) {
            $data['agent'] = array('meta' => array(
                'href'      => $entityBase . 'counterparty/' . $demand['cp_ms'],
                'type'      => 'counterparty',
                'mediaType' => 'application/json',
            ));
        }

        if (!empty($demand['order_ms'])) {
            $data['customerOrder'] = array('meta' => array(
                'href'      => $entityBase . 'customerorder/' . $demand['order_ms'],
                'type'      => 'customerorder',
                'mediaType' => 'application/json',
            ));
        }

        $statusUuid = isset(self::$statusMap[$demand['status']]) ? self::$statusMap[$demand['status']] : null;
        if ($statusUuid) {
            $data['state'] = array('meta' => array(
                'href'      => $entityBase . 'demand/metadata/states/' . $statusUuid,
                'type'      => 'state',
                'mediaType' => 'application/json',
            ));
        }

        $positions = array();
        foreach ($items as $item) {
            $prodMsId = !empty($item['resolved_product_ms_id']) ? $item['resolved_product_ms_id'] : null;
            if (!$prodMsId) continue;
            $positions[] = array(
                'quantity' => (float)$item['quantity'],
                'price'    => (int)round((float)$item['price'] * 100),
                'discount' => (float)$item['discount_percent'],
                'vat'      => (float)$item['vat_rate'],
                'assortment' => array('meta' => array(
                    'href'      => $entityBase . 'product/' . $prodMsId,
                    'type'      => 'product',
                    'mediaType' => 'application/json',
                )),
            );
        }
        if (!empty($positions)) $data['positions'] = $positions;

        return array('ok' => true, 'data' => $data);
    }

    private function markError($id, $msg)
    {
        Database::update('Papir', 'demand',
            array('sync_state' => 'error', 'sync_error' => mb_substr($msg, 0, 1000, 'UTF-8')),
            array('id' => $id));
    }

    private function extractError($result)
    {
        if (empty($result)) return 'Empty response from MoySklad';
        if (!empty($result['errors'])) {
            $msgs = array();
            foreach ((array)$result['errors'] as $e) {
                $msgs[] = isset($e['error']) ? $e['error'] : json_encode($e);
            }
            return implode('; ', $msgs);
        }
        return 'Unknown MoySklad error';
    }
}