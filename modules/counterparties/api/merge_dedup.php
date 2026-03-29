<?php
/**
 * POST /counterparties/api/merge_dedup
 *
 * Зливає один або більше "джерел" в цільовий контрагент.
 *   - Переносить повідомлення (cp_messages)
 *   - Переносить замовлення (customerorder)
 *   - Оновлює ліди, що були злиті з джерелами
 *   - Переносить пропущені контактні поля (phone, email, telegram) з джерел у ціль
 *   - Деактивує джерела (status = 0)
 *
 * POST params:
 *   target_id    — int
 *   source_ids[] — array of int
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$targetId  = isset($_POST['target_id'])  ? (int)$_POST['target_id']  : 0;
$sourceIds = isset($_POST['source_ids']) && is_array($_POST['source_ids'])
    ? array_map('intval', $_POST['source_ids'])
    : array();

if ($targetId <= 0 || empty($sourceIds)) {
    echo json_encode(array('ok' => false, 'error' => 'target_id and source_ids required'));
    exit;
}

// Sanitize: remove target from sources, deduplicate, remove zeros
$sourceIds = array_values(array_unique(array_filter($sourceIds,
    function($id) use ($targetId) { return $id > 0 && $id !== $targetId; }
)));

if (empty($sourceIds)) {
    echo json_encode(array('ok' => false, 'error' => 'No valid source counterparties'));
    exit;
}

$cpRepo = new CounterpartyRepository();
$target = $cpRepo->getById($targetId);
if (!$target) {
    echo json_encode(array('ok' => false, 'error' => 'Target counterparty not found'));
    exit;
}

$sourcesSql = implode(',', $sourceIds);

// ── 1. Move messages ─────────────────────────────────────────────────────────
Database::query('Papir',
    "UPDATE cp_messages
     SET counterparty_id = {$targetId}
     WHERE counterparty_id IN ({$sourcesSql})");

// ── 2. Move orders ────────────────────────────────────────────────────────────
Database::query('Papir',
    "UPDATE customerorder
     SET counterparty_id = {$targetId}
     WHERE counterparty_id IN ({$sourcesSql})");

// ── 3. Update merged leads ────────────────────────────────────────────────────
Database::query('Papir',
    "UPDATE leads
     SET counterparty_id = {$targetId}
     WHERE counterparty_id IN ({$sourcesSql})");

// ── 4. Copy missing contact data from sources to target ───────────────────────

// Load target's existing contacts
$tPersonR  = Database::fetchRow('Papir',
    "SELECT id, phone, email FROM counterparty_person  WHERE counterparty_id = {$targetId} LIMIT 1");
$tCompanyR = Database::fetchRow('Papir',
    "SELECT id, phone, email FROM counterparty_company WHERE counterparty_id = {$targetId} LIMIT 1");

$tPerson   = ($tPersonR['ok']  && $tPersonR['row'])  ? $tPersonR['row']  : null;
$tCompany  = ($tCompanyR['ok'] && $tCompanyR['row']) ? $tCompanyR['row'] : null;
$tTgR      = Database::fetchRow('Papir',
    "SELECT telegram_chat_id FROM counterparty WHERE id = {$targetId} LIMIT 1");
$tTg       = ($tTgR['ok'] && $tTgR['row'] && !empty($tTgR['row']['telegram_chat_id']))
    ? $tTgR['row']['telegram_chat_id'] : null;

foreach ($sourceIds as $srcId) {
    $sPersonR  = Database::fetchRow('Papir',
        "SELECT phone, email FROM counterparty_person  WHERE counterparty_id = {$srcId} LIMIT 1");
    $sCompanyR = Database::fetchRow('Papir',
        "SELECT phone, email FROM counterparty_company WHERE counterparty_id = {$srcId} LIMIT 1");
    $sTgR = Database::fetchRow('Papir',
        "SELECT telegram_chat_id FROM counterparty WHERE id = {$srcId} LIMIT 1");

    $sPerson  = ($sPersonR['ok']  && $sPersonR['row'])  ? $sPersonR['row']  : null;
    $sCompany = ($sCompanyR['ok'] && $sCompanyR['row']) ? $sCompanyR['row'] : null;
    $sTg      = ($sTgR['ok'] && $sTgR['row']) ? $sTgR['row']['telegram_chat_id'] : null;

    // Telegram on main counterparty table
    if (!$tTg && $sTg) {
        Database::update('Papir', 'counterparty',
            array('telegram_chat_id' => $sTg),
            array('id' => $targetId));
        $tTg = $sTg;
    }

    // Person sub-table (for person/fop types)
    if ($tPerson && $tPerson['id']) {
        $upd = array();
        if (empty($tPerson['phone']) && !empty($sPerson['phone'])) {
            $upd['phone'] = $sPerson['phone'];
            $tPerson['phone'] = $sPerson['phone'];
        }
        if (empty($tPerson['email']) && !empty($sPerson['email'])) {
            $upd['email'] = $sPerson['email'];
            $tPerson['email'] = $sPerson['email'];
        }
        if (!empty($upd)) {
            Database::update('Papir', 'counterparty_person', $upd, array('id' => (int)$tPerson['id']));
        }
    }

    // Company sub-table (for company/fop types)
    if ($tCompany && $tCompany['id']) {
        $upd = array();
        if (empty($tCompany['phone']) && !empty($sCompany['phone'])) {
            $upd['phone'] = $sCompany['phone'];
            $tCompany['phone'] = $sCompany['phone'];
        }
        if (empty($tCompany['email']) && !empty($sCompany['email'])) {
            $upd['email'] = $sCompany['email'];
            $tCompany['email'] = $sCompany['email'];
        }
        if (!empty($upd)) {
            Database::update('Papir', 'counterparty_company', $upd, array('id' => (int)$tCompany['id']));
        }
    }
}

// ── 5. Deactivate sources ─────────────────────────────────────────────────────
Database::query('Papir',
    "UPDATE counterparty SET status = 0 WHERE id IN ({$sourcesSql})");

echo json_encode(array(
    'ok'        => true,
    'target_id' => $targetId,
    'merged'    => count($sourceIds),
));