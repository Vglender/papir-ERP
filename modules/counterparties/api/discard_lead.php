<?php
/**
 * POST /counterparties/api/discard_lead
 * Mark lead as lost (spam/mistake)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$leadId = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
if ($leadId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'lead_id required'));
    exit;
}

$leadRepo = new LeadRepository();
$ok       = $leadRepo->discard($leadId);

echo json_encode(array('ok' => $ok));