<?php
/**
 * GET /ukrposhta/api/print_registry?group_uuid=...
 * Downloads the Form-103a (реєстр ТТН) PDF from Ukrposhta and streams it to browser.
 */
require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    header('Content-Type: application/json'); echo json_encode(array('ok' => false, 'error' => 'auth')); exit;
}

$uuid = isset($_GET['group_uuid']) ? trim($_GET['group_uuid']) : '';
if (!$uuid) {
    header('Content-Type: application/json'); echo json_encode(array('ok' => false, 'error' => 'group_uuid required')); exit;
}

$r = \Papir\Crm\GroupService::downloadForm103a($uuid);
if (!$r['ok']) {
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => $r['error']));
    exit;
}

\Papir\Crm\UpGroupRepository::updateByUuid($uuid, array('printed' => 1));

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="up_registry_' . substr($uuid, 0, 8) . '.pdf"');
echo $r['raw'];