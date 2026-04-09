<?php
/**
 * Toggle a MoySklad webhook: ON = create in MS API, OFF = delete from MS API.
 * Also saves the state + webhook IDs in integration_settings.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../IntegrationSettingsService.php';
require_once __DIR__ . '/../../auth/AuthService.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';

$user = \Papir\Crm\AuthService::getCurrentUser();
if (!$user || empty($user['is_admin'])) {
    echo json_encode(array('ok' => false, 'error' => 'Доступ заборонено'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$whKey  = isset($input['wh_key'])  ? trim($input['wh_key'])  : '';
$enable = !empty($input['enable']);

// Webhook definitions: wh_key => array of [entityType, action, relay_url]
$baseUrl = 'https://papir.officetorg.com.ua';
$webhookDefs = array(
    'wh_customerorder' => array(
        array('customerorder', 'CREATE', $baseUrl . '/customerorder/webhook/moysklad'),
        array('customerorder', 'UPDATE', $baseUrl . '/customerorder/webhook/moysklad'),
        array('customerorder', 'DELETE', $baseUrl . '/customerorder/webhook/moysklad'),
    ),
    'wh_demand' => array(
        array('demand', 'CREATE', $baseUrl . '/demand/webhook/moysklad'),
        array('demand', 'UPDATE', $baseUrl . '/demand/webhook/moysklad'),
        array('demand', 'DELETE', $baseUrl . '/demand/webhook/moysklad'),
    ),
    'wh_finance' => array(
        array('paymentin',  'CREATE', $baseUrl . '/finance/webhook/moysklad'),
        array('paymentin',  'UPDATE', $baseUrl . '/finance/webhook/moysklad'),
        array('paymentin',  'DELETE', $baseUrl . '/finance/webhook/moysklad'),
        array('paymentout', 'CREATE', $baseUrl . '/finance/webhook/moysklad'),
        array('paymentout', 'UPDATE', $baseUrl . '/finance/webhook/moysklad'),
        array('paymentout', 'DELETE', $baseUrl . '/finance/webhook/moysklad'),
        array('cashin',     'CREATE', $baseUrl . '/finance/webhook/moysklad'),
        array('cashin',     'UPDATE', $baseUrl . '/finance/webhook/moysklad'),
        array('cashin',     'DELETE', $baseUrl . '/finance/webhook/moysklad'),
        array('cashout',    'CREATE', $baseUrl . '/finance/webhook/moysklad'),
        array('cashout',    'UPDATE', $baseUrl . '/finance/webhook/moysklad'),
        array('cashout',    'DELETE', $baseUrl . '/finance/webhook/moysklad'),
    ),
    'wh_counterparty' => array(
        array('counterparty', 'CREATE', $baseUrl . '/counterparties/webhook/moysklad'),
        array('counterparty', 'UPDATE', $baseUrl . '/counterparties/webhook/moysklad'),
    ),
);

if (!isset($webhookDefs[$whKey])) {
    echo json_encode(array('ok' => false, 'error' => 'Unknown webhook key'));
    exit;
}

$ms = new MoySkladApi();
$errors = array();

if ($enable) {
    // Create webhooks in MS API
    $createdIds = array();
    foreach ($webhookDefs[$whKey] as $def) {
        $result = $ms->webhookCreate($def[0], $def[1], $def[2]);
        if (!empty($result['errors'])) {
            // May already exist — not fatal
            $errMsg = isset($result['errors'][0]['error']) ? $result['errors'][0]['error'] : 'unknown';
            $errors[] = $def[0] . '/' . $def[1] . ': ' . $errMsg;
        } elseif (!empty($result['id'])) {
            $createdIds[] = $result['id'];
        }
    }
    // Save IDs for future deletion
    IntegrationSettingsService::saveAll('moysklad', array(
        array('key' => $whKey, 'value' => '1', 'secret' => 0),
        array('key' => $whKey . '_ids', 'value' => implode(',', $createdIds), 'secret' => 0),
    ));
} else {
    // Delete webhooks from MS API
    $idsStr = IntegrationSettingsService::get('moysklad', $whKey . '_ids', '');
    if ($idsStr) {
        $ids = array_filter(explode(',', $idsStr));
        foreach ($ids as $whId) {
            $ms->webhookDelete(trim($whId));
        }
    }
    // Also try to find and delete by URL (in case IDs are lost)
    $existing = $ms->webhookList();
    $targetUrls = array();
    foreach ($webhookDefs[$whKey] as $def) {
        $targetUrls[$def[2] . '|' . $def[0] . '|' . $def[1]] = true;
    }
    foreach ($existing as $wh) {
        $k = (isset($wh['url']) ? $wh['url'] : '') . '|' . (isset($wh['entityType']) ? $wh['entityType'] : '') . '|' . (isset($wh['action']) ? $wh['action'] : '');
        if (isset($targetUrls[$k]) && !empty($wh['id'])) {
            $ms->webhookDelete($wh['id']);
        }
    }

    IntegrationSettingsService::saveAll('moysklad', array(
        array('key' => $whKey, 'value' => '0', 'secret' => 0),
        array('key' => $whKey . '_ids', 'value' => '', 'secret' => 0),
    ));
}

$resp = array('ok' => true, 'enabled' => $enable);
if ($errors) $resp['warnings'] = $errors;
echo json_encode($resp);