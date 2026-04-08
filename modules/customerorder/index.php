<?php

require_once __DIR__ . '/customerorder_bootstrap.php';

$repository = new CustomerOrderRepository();
$service = new CustomerOrderService($repository);
$controller = new CustomerOrderController($service);

$result = $controller->index($_GET);

// Reference data for filter dropdowns
$_rOrg = Database::fetchAll('Papir', "SELECT id, COALESCE(NULLIF(short_name,''), name) AS label FROM organization WHERE status=1 ORDER BY name");
$filterOrganizations = ($_rOrg['ok'] && !empty($_rOrg['rows'])) ? $_rOrg['rows'] : array();

$_rEmp = Database::fetchAll('Papir',
    "SELECT e.id, COALESCE(NULLIF(e.full_name,''), u.display_name) AS label
     FROM employee e LEFT JOIN auth_users u ON u.employee_id = e.id
     WHERE e.status=1 ORDER BY label");
$filterManagers = ($_rEmp['ok'] && !empty($_rEmp['rows'])) ? $_rEmp['rows'] : array();

$_rAct = Database::fetchAll('Papir',
    "SELECT DISTINCT next_action AS code, next_action_label AS label
     FROM customerorder
     WHERE next_action IS NOT NULL AND next_action != '' AND deleted_at IS NULL");
$filterActions = ($_rAct['ok'] && !empty($_rAct['rows'])) ? $_rAct['rows'] : array();

$_rPm = Database::fetchAll('Papir', "SELECT id, code, name_uk FROM payment_method WHERE status=1 ORDER BY sort_order");
$filterPaymentMethods = ($_rPm['ok'] && !empty($_rPm['rows'])) ? $_rPm['rows'] : array();

$_rDm = Database::fetchAll('Papir', "SELECT id, code, name_uk FROM delivery_method WHERE status=1 ORDER BY sort_order");
$filterDeliveryMethods = ($_rDm['ok'] && !empty($_rDm['rows'])) ? $_rDm['rows'] : array();

// Resolve counterparty name if filtered by id
$filterCpName = '';
if (!empty($_GET['counterparty_id'])) {
    $_rCp = Database::fetchRow('Papir', "SELECT name FROM counterparty WHERE id=" . (int)$_GET['counterparty_id']);
    if ($_rCp['ok'] && !empty($_rCp['row'])) $filterCpName = $_rCp['row']['name'];
}

require __DIR__ . '/views/registry.php';