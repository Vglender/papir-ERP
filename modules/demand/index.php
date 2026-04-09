<?php

require_once __DIR__ . '/demand_bootstrap.php';

$activeNav = 'sales';
$subNav    = 'demands';
$title     = 'Відвантаження';

$repo = new DemandRepository();

// Extract filters
$filters = array(
    'search'              => isset($_GET['search']) ? trim($_GET['search']) : null,
    'status'              => isset($_GET['status']) ? $_GET['status'] : null,
    'organization_id'     => isset($_GET['organization_id']) ? $_GET['organization_id'] : null,
    'manager_employee_id' => isset($_GET['manager_employee_id']) ? $_GET['manager_employee_id'] : null,
    'counterparty_id'     => isset($_GET['counterparty_id']) ? $_GET['counterparty_id'] : null,
    'date_from'           => isset($_GET['date_from']) ? $_GET['date_from'] : null,
    'date_to'             => isset($_GET['date_to']) ? $_GET['date_to'] : null,
    'sum_from'            => isset($_GET['sum_from']) ? $_GET['sum_from'] : null,
    'sum_to'              => isset($_GET['sum_to']) ? $_GET['sum_to'] : null,
);

$sort = array(
    'field' => isset($_GET['sort_field']) ? $_GET['sort_field'] : 'id',
    'dir'   => isset($_GET['sort_dir']) ? $_GET['sort_dir'] : 'DESC',
);

$page  = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = 50;

$listResult = $repo->getList($filters, $sort, $page, $limit);
$countResult = $repo->countList($filters);

$rows  = ($listResult['ok'] && !empty($listResult['rows'])) ? $listResult['rows'] : array();
$total = ($countResult['ok']) ? (int)$countResult['value'] : 0;
$totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;

// Reference data for filter dropdowns
$_rOrg = $repo->getOrganizations();
$filterOrganizations = ($_rOrg['ok'] && !empty($_rOrg['rows'])) ? $_rOrg['rows'] : array();

$_rEmp = $repo->getManagers();
$filterManagers = ($_rEmp['ok'] && !empty($_rEmp['rows'])) ? $_rEmp['rows'] : array();

// Resolve counterparty name if filtered by id
$filterCpName = '';
if (!empty($_GET['counterparty_id'])) {
    $_rCp = Database::fetchRow('Papir', "SELECT name FROM counterparty WHERE id=" . (int)$_GET['counterparty_id']);
    if ($_rCp['ok'] && !empty($_rCp['row'])) $filterCpName = $_rCp['row']['name'];
}

require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/index.php';
require_once __DIR__ . '/../shared/layout_end.php';