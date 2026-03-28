<?php
require_once __DIR__ . '/counterparties_bootstrap.php';

$repo = new CounterpartyRepository();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /counterparties');
    exit;
}

$cp = $repo->getById($id);
if (!$cp) {
    http_response_code(404);
    require_once __DIR__ . '/../../pages/page-404.html';
    exit;
}

$tab      = isset($_GET['tab']) ? trim($_GET['tab']) : 'requisites';
$stats    = $repo->getOrderStats($id);
$contacts = $repo->getContacts($id);
$relations = $repo->getRelations($id);
$recentOrders = $repo->getRecentOrders($id, 50);
$activities   = $repo->getActivities($id);
$groupMembers = ($cp['group_id']) ? $repo->getGroupMembers((int)$cp['group_id']) : array();
$groups   = $repo->getGroups();

// Phone/email: company or person
$phone = $cp['company_phone'] ? $cp['company_phone'] : $cp['person_phone'];
$email = $cp['company_email'] ? $cp['company_email'] : $cp['person_email'];

require_once __DIR__ . '/views/view.php';
