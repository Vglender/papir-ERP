<?php
/**
 * GET /counterparties/api/get_related?counterparty_id=X
 *
 * Повертає звʼязки контрагента, потрібні для пікера пар «юрособа ↔ контактна особа»
 * у формі замовлення:
 *
 *   - для company/fop  → перелік контактних осіб (linked persons)
 *   - для person       → перелік юросіб, до яких прикріплений (linked companies)
 *
 * Відповідь:
 *   {
 *     ok: true,
 *     self: { id, name, type },
 *     contacts:  [ { id, name, phone, position, is_primary } ]   // якщо self = company/fop
 *     companies: [ { id, name, type, phone, job_title, is_primary } ] // якщо self = person
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$counterpartyId = isset($_GET['counterparty_id']) ? (int)$_GET['counterparty_id'] : 0;
if ($counterpartyId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'counterparty_id required'));
    exit;
}

$rSelf = Database::fetchRow('Papir',
    "SELECT id, name, type FROM counterparty WHERE id = {$counterpartyId} LIMIT 1");
if (!$rSelf['ok'] || empty($rSelf['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Контрагента не знайдено'));
    exit;
}
$self = $rSelf['row'];

$response = array(
    'ok'   => true,
    'self' => array(
        'id'   => (int)$self['id'],
        'name' => $self['name'],
        'type' => $self['type'],
    ),
    'contacts'  => array(),
    'companies' => array(),
);

if ($self['type'] === 'company' || $self['type'] === 'fop') {
    // contacts: persons linked as children
    $r = Database::fetchAll('Papir',
        "SELECT cr.id AS relation_id,
                cr.relation_type,
                cr.job_title,
                cr.is_primary,
                c.id, c.name,
                cp.phone, cp.position_name
           FROM counterparty_relation cr
           JOIN counterparty c ON c.id = cr.child_counterparty_id
      LEFT JOIN counterparty_person cp ON cp.counterparty_id = c.id
          WHERE cr.parent_counterparty_id = {$counterpartyId}
            AND c.type = 'person'
            AND c.status = 1
       ORDER BY cr.is_primary DESC, c.name ASC");
    if ($r['ok']) {
        foreach ($r['rows'] as $row) {
            $response['contacts'][] = array(
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'phone'      => isset($row['phone']) ? $row['phone'] : '',
                'position'   => !empty($row['position_name']) ? $row['position_name']
                               : (isset($row['job_title']) ? $row['job_title'] : ''),
                'is_primary' => (int)$row['is_primary'],
            );
        }
    }
} elseif ($self['type'] === 'person') {
    // legal entities the person is linked to (as child)
    $r = Database::fetchAll('Papir',
        "SELECT cr.id AS relation_id,
                cr.relation_type,
                cr.job_title,
                cr.is_primary,
                c.id, c.name, c.type,
                cc.phone
           FROM counterparty_relation cr
           JOIN counterparty c ON c.id = cr.parent_counterparty_id
      LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
          WHERE cr.child_counterparty_id = {$counterpartyId}
            AND c.type IN ('company','fop')
            AND c.status = 1
       ORDER BY cr.is_primary DESC, c.name ASC");
    if ($r['ok']) {
        foreach ($r['rows'] as $row) {
            $response['companies'][] = array(
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'type'       => $row['type'],
                'phone'      => isset($row['phone']) ? $row['phone'] : '',
                'job_title'  => isset($row['job_title']) ? $row['job_title'] : '',
                'is_primary' => (int)$row['is_primary'],
            );
        }
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
