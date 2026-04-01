<?php
/**
 * Синхронизация finance_bank → МойСклад.
 * Подключается из save_bank.php и delete_bank.php.
 *
 * Публичные функции:
 *   finance_ms_push($localId, $direction, $formData)  — CREATE или UPDATE в МС
 *   finance_ms_delete($idMs, $direction)               — DELETE в МС
 */

require_once __DIR__ . '/../../moysklad/moysklad_api.php';

/**
 * Собрать payload для МС из данных формы + справочников БД.
 *
 * $formData:
 *   moment, sum, doc_number, description, payment_purpose,
 *   cp_id, expense_category_id, direction, is_posted,
 *   organization_ms (опционально — из существующей записи)
 */
function _finance_ms_build_payload(array $formData, MoySkladApi $ms)
{
    $entityBase = $ms->getEntityBaseUrl();
    $direction  = isset($formData['direction']) ? $formData['direction'] : 'out';
    $isPosted   = isset($formData['is_posted']) ? (bool)$formData['is_posted'] : true;
    $sum        = isset($formData['sum'])        ? (float)$formData['sum']     : 0.0;
    $moment     = isset($formData['moment'])     ? (string)$formData['moment'] : date('Y-m-d H:i:s');
    $docNumber  = isset($formData['doc_number']) ? trim((string)$formData['doc_number']) : '';
    $desc       = isset($formData['description'])     ? trim((string)$formData['description'])     : '';
    $purpose    = isset($formData['payment_purpose']) ? trim((string)$formData['payment_purpose']) : '';
    $cpId       = isset($formData['cp_id'])           ? (int)$formData['cp_id']           : 0;
    $expCatId   = isset($formData['expense_category_id']) ? (int)$formData['expense_category_id'] : 0;
    $orgMs      = isset($formData['organization_ms']) ? trim((string)$formData['organization_ms']) : '';

    $payload = array(
        'applicable' => $isPosted,
        'moment'     => $moment,
        'sum'        => (int)round($sum * 100),
    );

    if ($docNumber !== '') {
        $payload['name'] = $docNumber;
    }
    if ($desc !== '') {
        $payload['description'] = $desc;
    }
    if ($purpose !== '') {
        $payload['paymentPurpose'] = $purpose;
    }

    // Организация
    if ($orgMs === '') {
        $orgRow = Database::fetchRow('Papir',
            "SELECT id_ms FROM organization WHERE id_ms IS NOT NULL AND id_ms != '' ORDER BY id LIMIT 1"
        );
        if ($orgRow['ok'] && $orgRow['row']) {
            $orgMs = (string)$orgRow['row']['id_ms'];
        }
    }
    if ($orgMs !== '') {
        $payload['organization'] = array('meta' => array(
            'href'        => $entityBase . 'organization/' . $orgMs,
            'type'        => 'organization',
            'mediaType'   => 'application/json',
        ));
    }

    // Контрагент (agent)
    // agent_ms_type хранит оригинальный тип МС: counterparty, employee, organization
    // Для ручных платежей из UI всегда counterparty; для webhook-платежей — из agent_ms_type
    $agentMsType = isset($formData['agent_ms_type']) && $formData['agent_ms_type'] !== ''
        ? (string)$formData['agent_ms_type']
        : 'counterparty';

    if ($cpId > 0) {
        $cpRow = Database::fetchRow('Papir',
            "SELECT id_ms FROM counterparty WHERE id = {$cpId} LIMIT 1"
        );
        if ($cpRow['ok'] && $cpRow['row'] && !empty($cpRow['row']['id_ms'])) {
            $cpIdMs = (string)$cpRow['row']['id_ms'];
            $payload['agent'] = array('meta' => array(
                'href'        => $entityBase . $agentMsType . '/' . $cpIdMs,
                'type'        => $agentMsType,
                'mediaType'   => 'application/json',
            ));
        }
    }

    // Статья расходов (только для paymentout)
    if ($direction === 'out' && $expCatId > 0) {
        $expRow = Database::fetchRow('Papir',
            "SELECT expense_item_ms FROM finance_expense_category WHERE id = {$expCatId} LIMIT 1"
        );
        if ($expRow['ok'] && $expRow['row'] && !empty($expRow['row']['expense_item_ms'])) {
            $expItemMs = (string)$expRow['row']['expense_item_ms'];
            $payload['expenseItem'] = array('meta' => array(
                'href'        => $entityBase . 'expenseitem/' . $expItemMs,
                'type'        => 'expenseitem',
                'mediaType'   => 'application/json',
            ));
        }
    }

    return $payload;
}

/**
 * Синхронизировать платёж в МС (CREATE или UPDATE).
 * После CREATE сохраняет id_ms обратно в finance_bank.
 *
 * Возвращает array('ok'=>bool, 'id_ms'=>string, 'error'=>string)
 */
function finance_ms_push($localId, array $formData, $existingIdMs = '')
{
    $direction  = isset($formData['direction']) ? $formData['direction'] : 'out';
    $entityType = ($direction === 'in') ? 'paymentin' : 'paymentout';

    $ms         = new MoySkladApi();
    $entityBase = $ms->getEntityBaseUrl();
    $payload    = _finance_ms_build_payload($formData, $ms);

    if ($existingIdMs !== '' && $existingIdMs !== null) {
        // UPDATE
        $raw    = $ms->querySend($entityBase . $entityType . '/' . $existingIdMs, $payload, 'PUT');
        $result = json_decode(json_encode($raw), true);

        if (!empty($result['errors'])) {
            $errMsg = isset($result['errors'][0]['error']) ? $result['errors'][0]['error'] : json_encode($result['errors']);
            return array('ok' => false, 'error' => 'МС PUT error: ' . $errMsg);
        }
        return array('ok' => true, 'id_ms' => $existingIdMs);

    } else {
        // CREATE
        $raw    = $ms->querySend($entityBase . $entityType, $payload, 'POST');
        $result = json_decode(json_encode($raw), true);

        if (!empty($result['errors'])) {
            $errMsg = isset($result['errors'][0]['error']) ? $result['errors'][0]['error'] : json_encode($result['errors']);
            return array('ok' => false, 'error' => 'МС POST error: ' . $errMsg);
        }
        if (empty($result['id'])) {
            return array('ok' => false, 'error' => 'МС не вернул id');
        }

        $newIdMs = (string)$result['id'];
        // Сохранить id_ms обратно в finance_bank
        Database::update('Papir', 'finance_bank',
            array('id_ms' => $newIdMs),
            array('id'    => $localId)
        );
        return array('ok' => true, 'id_ms' => $newIdMs);
    }
}

/**
 * Собрать payload для МС (cashin/cashout).
 * Отличие от bank: agent передаётся через agent_ms UUID напрямую (не через cp_id).
 */
function _finance_ms_cash_build_payload(array $formData, MoySkladApi $ms)
{
    $entityBase  = $ms->getEntityBaseUrl();
    $direction   = isset($formData['direction']) ? $formData['direction'] : 'out';
    $isPosted    = isset($formData['is_posted']) ? (bool)$formData['is_posted'] : true;
    $sum         = isset($formData['sum'])        ? (float)$formData['sum']     : 0.0;
    $moment      = isset($formData['moment'])     ? (string)$formData['moment'] : date('Y-m-d H:i:s');
    $docNumber   = isset($formData['doc_number']) ? trim((string)$formData['doc_number']) : '';
    $desc        = isset($formData['description'])     ? trim((string)$formData['description'])     : '';
    $purpose     = isset($formData['payment_purpose']) ? trim((string)$formData['payment_purpose']) : '';
    $agentMs     = isset($formData['agent_ms'])        ? trim((string)$formData['agent_ms'])        : '';
    $agentMsType = isset($formData['agent_ms_type']) && $formData['agent_ms_type'] !== ''
        ? (string)$formData['agent_ms_type']
        : 'counterparty';
    $expCatId    = isset($formData['expense_category_id']) ? (int)$formData['expense_category_id'] : 0;
    $orgMs       = isset($formData['organization_ms']) ? trim((string)$formData['organization_ms']) : '';

    $payload = array(
        'applicable' => $isPosted,
        'moment'     => $moment,
        'sum'        => (int)round($sum * 100),
    );

    if ($docNumber !== '') {
        $payload['name'] = $docNumber;
    }
    if ($desc !== '') {
        $payload['description'] = $desc;
    }
    if ($purpose !== '') {
        $payload['paymentPurpose'] = $purpose;
    }

    // Организация
    if ($orgMs === '') {
        $orgRow = Database::fetchRow('Papir',
            "SELECT id_ms FROM organization WHERE id_ms IS NOT NULL AND id_ms != '' ORDER BY id LIMIT 1"
        );
        if ($orgRow['ok'] && $orgRow['row']) {
            $orgMs = (string)$orgRow['row']['id_ms'];
        }
    }
    if ($orgMs !== '') {
        $payload['organization'] = array('meta' => array(
            'href'      => $entityBase . 'organization/' . $orgMs,
            'type'      => 'organization',
            'mediaType' => 'application/json',
        ));
    }

    // Агент — UUID напрямую из finance_cash.agent_ms
    if ($agentMs !== '') {
        $payload['agent'] = array('meta' => array(
            'href'      => $entityBase . $agentMsType . '/' . $agentMs,
            'type'      => $agentMsType,
            'mediaType' => 'application/json',
        ));
    }

    // Статья расходов (только для cashout)
    if ($direction === 'out' && $expCatId > 0) {
        $expRow = Database::fetchRow('Papir',
            "SELECT expense_item_ms FROM finance_expense_category WHERE id = {$expCatId} LIMIT 1"
        );
        if ($expRow['ok'] && $expRow['row'] && !empty($expRow['row']['expense_item_ms'])) {
            $expItemMs = (string)$expRow['row']['expense_item_ms'];
            $payload['expenseItem'] = array('meta' => array(
                'href'      => $entityBase . 'expenseitem/' . $expItemMs,
                'type'      => 'expenseitem',
                'mediaType' => 'application/json',
            ));
        }
    }

    return $payload;
}

/**
 * Синхронизировать кассовый платёж в МС (CREATE или UPDATE).
 * После CREATE сохраняет id_ms обратно в finance_cash.
 */
function finance_ms_cash_push($localId, array $formData, $existingIdMs = '')
{
    $direction  = isset($formData['direction']) ? $formData['direction'] : 'out';
    $entityType = ($direction === 'in') ? 'cashin' : 'cashout';

    $ms         = new MoySkladApi();
    $entityBase = $ms->getEntityBaseUrl();
    $payload    = _finance_ms_cash_build_payload($formData, $ms);

    if ($existingIdMs !== '' && $existingIdMs !== null) {
        // UPDATE
        $raw    = $ms->querySend($entityBase . $entityType . '/' . $existingIdMs, $payload, 'PUT');
        $result = json_decode(json_encode($raw), true);

        if (!empty($result['errors'])) {
            $errMsg = isset($result['errors'][0]['error']) ? $result['errors'][0]['error'] : json_encode($result['errors']);
            return array('ok' => false, 'error' => 'МС PUT error: ' . $errMsg);
        }
        return array('ok' => true, 'id_ms' => $existingIdMs);

    } else {
        // CREATE
        $raw    = $ms->querySend($entityBase . $entityType, $payload, 'POST');
        $result = json_decode(json_encode($raw), true);

        if (!empty($result['errors'])) {
            $errMsg = isset($result['errors'][0]['error']) ? $result['errors'][0]['error'] : json_encode($result['errors']);
            return array('ok' => false, 'error' => 'МС POST error: ' . $errMsg);
        }
        if (empty($result['id'])) {
            return array('ok' => false, 'error' => 'МС не вернул id');
        }

        $newIdMs = (string)$result['id'];
        Database::update('Papir', 'finance_cash',
            array('id_ms' => $newIdMs),
            array('id'    => $localId)
        );
        return array('ok' => true, 'id_ms' => $newIdMs);
    }
}

/**
 * Удалить кассовый платёж из МС по id_ms.
 * МС требует сначала снять проведение, потом удалить.
 */
function finance_ms_cash_delete($idMs, $direction)
{
    if ($idMs === '' || $idMs === null) {
        return array('ok' => true);
    }

    $entityType = ($direction === 'in') ? 'cashin' : 'cashout';
    $ms         = new MoySkladApi();
    $entityBase = $ms->getEntityBaseUrl();
    $url        = $entityBase . $entityType . '/' . $idMs;

    // Шаг 1: снять проведение
    $ms->querySend($url, array('applicable' => false), 'PUT');

    // Шаг 2: удалить
    $raw    = $ms->querySend($url, null, 'DELETE');
    if ($raw === null || $raw === '') {
        return array('ok' => true);
    }
    $result = json_decode(json_encode($raw), true);
    if (!empty($result['errors'])) {
        $errMsg = isset($result['errors'][0]['error']) ? $result['errors'][0]['error'] : json_encode($result['errors']);
        return array('ok' => false, 'error' => 'МС DELETE error: ' . $errMsg);
    }
    return array('ok' => true);
}

/**
 * Удалить платёж из МС по id_ms.
 * МС требует сначала снять проведение, потом удалить.
 *
 * Возвращает array('ok'=>bool, 'error'=>string)
 */
function finance_ms_delete($idMs, $direction)
{
    if ($idMs === '' || $idMs === null) {
        return array('ok' => true); // нечего удалять
    }

    $entityType = ($direction === 'in') ? 'paymentin' : 'paymentout';
    $ms         = new MoySkladApi();
    $entityBase = $ms->getEntityBaseUrl();
    $url        = $entityBase . $entityType . '/' . $idMs;

    // Шаг 1: снять проведение (applicable=false) — иначе МС не даёт удалить
    $ms->querySend($url, array('applicable' => false), 'PUT');

    // Шаг 2: удалить
    $raw    = $ms->querySend($url, null, 'DELETE');
    // DELETE возвращает пустое тело (204) при успехе
    if ($raw === null || $raw === '') {
        return array('ok' => true);
    }
    $result = json_decode(json_encode($raw), true);
    if (!empty($result['errors'])) {
        $errMsg = isset($result['errors'][0]['error']) ? $result['errors'][0]['error'] : json_encode($result['errors']);
        return array('ok' => false, 'error' => 'МС DELETE error: ' . $errMsg);
    }
    return array('ok' => true);
}