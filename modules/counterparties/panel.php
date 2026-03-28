<?php
/**
 * Counterparty sidebar panel — returns HTML fragment (no layout wrapper).
 * Used by AJAX: GET /counterparties/panel?id=X
 */
require_once __DIR__ . '/counterparties_bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit; }

$repo = new CounterpartyRepository();
$cp   = $repo->getById($id);
if (!$cp) { http_response_code(404); exit; }

$stats        = $repo->getOrderStats($id);
$contacts     = $repo->getContacts($id);
$relations    = $repo->getRelations($id);
$recentOrders = $repo->getRecentOrders($id, 20);
$activities   = $repo->getActivities($id, 50);
$groupMembers = ($cp['group_id']) ? $repo->getGroupMembers((int)$cp['group_id']) : array();
$groups       = $repo->getGroups();

$isCompany = in_array($cp['type'], array('company','fop','department','other'));
$isPerson  = ($cp['type'] === 'person');
$phone     = $cp['company_phone'] ? $cp['company_phone'] : $cp['person_phone'];
$email     = $cp['company_email'] ? $cp['company_email'] : $cp['person_email'];

$words = preg_split('/\s+/', $cp['name']);
if (count($words) >= 2) {
    $initials = mb_strtoupper(mb_substr($words[0],0,1,'UTF-8').mb_substr($words[1],0,1,'UTF-8'),'UTF-8');
} else {
    $initials = mb_strtoupper(mb_substr($cp['name'],0,2,'UTF-8'),'UTF-8');
}

$orderStatusLabels = array(
    'draft'=>'Чернетка','new'=>'Новий','confirmed'=>'Підтверджено',
    'in_progress'=>'В роботі','waiting_payment'=>'Очікує оплату',
    'paid'=>'Оплачено','shipped'=>'Відвантажено','completed'=>'Виконано','cancelled'=>'Скасовано',
);

require_once __DIR__ . '/views/panel.php';
