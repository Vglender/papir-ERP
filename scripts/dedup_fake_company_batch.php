<?php
/**
 * Batch dedup: company без ЄДРПОУ + person з тим самим name+phone = баг МС.
 * Company зливається в person (бо це фактично фізособа).
 * Ціль = той хто має більше замовлень, при рівності — person.
 *
 * Usage: php scripts/dedup_fake_company_batch.php [--dry-run]
 */
$dryRun = in_array('--dry-run', $argv);

require_once __DIR__ . '/../modules/database/database.php';

if ($dryRun) echo "=== DRY RUN (no changes) ===\n\n";

// ── Find all pairs ──────────────────────────────────────────────────────────

$pairsR = Database::fetchAll('Papir',
    "SELECT c_comp.id AS company_id, c_comp.name AS company_name,
            c_pers.id AS person_id, c_pers.name AS person_name,
            COALESCE(NULLIF(cp_comp.phone,''), NULLIF(cc.phone,'')) AS comp_phone,
            cp_pers.phone AS pers_phone,
            (SELECT COUNT(*) FROM customerorder co WHERE co.counterparty_id = c_comp.id AND co.deleted_at IS NULL) AS comp_orders,
            (SELECT COUNT(*) FROM customerorder co WHERE co.counterparty_id = c_pers.id AND co.deleted_at IS NULL) AS pers_orders,
            c_comp.created_at AS comp_created, c_pers.created_at AS pers_created
     FROM counterparty c_comp
     JOIN counterparty_company cc ON cc.counterparty_id = c_comp.id
     LEFT JOIN counterparty_person cp_comp ON cp_comp.counterparty_id = c_comp.id
     JOIN counterparty c_pers ON c_pers.status = 1 AND c_pers.type = 'person' AND c_pers.id != c_comp.id
     JOIN counterparty_person cp_pers ON cp_pers.counterparty_id = c_pers.id
     WHERE c_comp.status = 1
       AND c_comp.type = 'company'
       AND (cc.okpo IS NULL OR TRIM(cc.okpo) = '')
       AND LOWER(TRIM(c_comp.name)) = LOWER(TRIM(c_pers.name))
       AND COALESCE(NULLIF(cp_comp.phone,''), NULLIF(cc.phone,'')) IS NOT NULL
       AND cp_pers.phone IS NOT NULL AND cp_pers.phone != ''
       AND RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
           cp_pers.phone,'+',''),'-',''),' ',''),'(',''),')',''),'.',''), 9)
         = RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
           COALESCE(NULLIF(cp_comp.phone,''), NULLIF(cc.phone,'')),'+',''),'-',''),' ',''),'(',''),')',''),'.',''), 9)
     ORDER BY c_comp.name");

if (!$pairsR['ok'] || empty($pairsR['rows'])) {
    echo "No pairs found.\n";
    exit;
}

echo "Found " . count($pairsR['rows']) . " pairs\n\n";

$mergeTables = array(
    'cp_messages',
    'customerorder',
    'customerorder_party',
    'customerorder_shipping',
    'demand',
    'salesreturn',
    'purchaseorder',
    'supply',
    'contract',
    'counterparty_activity',
    'counterparty_files',
    'cp_tasks',
    'cp_task_queue',
    'team_messages',
    'Counterparties_np',
    'leads',
);

$totalMerged = 0;

foreach ($pairsR['rows'] as $pair) {
    $compId     = (int)$pair['company_id'];
    $persId     = (int)$pair['person_id'];
    $compOrders = (int)$pair['comp_orders'];
    $persOrders = (int)$pair['pers_orders'];

    // Target: more orders wins; tie → person wins (correct type)
    if ($compOrders > $persOrders) {
        $targetId = $compId;
        $sourceId = $persId;
        $targetLabel = "company #{$compId} ({$compOrders} зам.)";
        $sourceLabel = "person #{$persId} ({$persOrders} зам.)";
    } else {
        $targetId = $persId;
        $sourceId = $compId;
        $targetLabel = "person #{$persId} ({$persOrders} зам.)";
        $sourceLabel = "company #{$compId} ({$compOrders} зам.)";
    }

    echo "{$pair['company_name']}  →  ціль: {$targetLabel}, злити: {$sourceLabel}\n";

    if ($dryRun) {
        $totalMerged++;
        continue;
    }

    // Transfer all related data
    foreach ($mergeTables as $table) {
        Database::query('Papir',
            "UPDATE {$table} SET counterparty_id = {$targetId}
             WHERE counterparty_id = {$sourceId}");
    }

    // Copy missing contact data
    $tPersonR  = Database::fetchRow('Papir',
        "SELECT id, phone, email FROM counterparty_person WHERE counterparty_id = {$targetId} LIMIT 1");
    $tCompanyR = Database::fetchRow('Papir',
        "SELECT id, phone, email FROM counterparty_company WHERE counterparty_id = {$targetId} LIMIT 1");
    $tPerson   = ($tPersonR['ok']  && $tPersonR['row'])  ? $tPersonR['row']  : null;
    $tCompany  = ($tCompanyR['ok'] && $tCompanyR['row']) ? $tCompanyR['row'] : null;

    $sPersonR  = Database::fetchRow('Papir',
        "SELECT phone, email FROM counterparty_person WHERE counterparty_id = {$sourceId} LIMIT 1");
    $sCompanyR = Database::fetchRow('Papir',
        "SELECT phone, email FROM counterparty_company WHERE counterparty_id = {$sourceId} LIMIT 1");
    $sPerson   = ($sPersonR['ok']  && $sPersonR['row'])  ? $sPersonR['row']  : null;
    $sCompany  = ($sCompanyR['ok'] && $sCompanyR['row']) ? $sCompanyR['row'] : null;

    $sTgR = Database::fetchRow('Papir',
        "SELECT telegram_chat_id FROM counterparty WHERE id = {$sourceId} LIMIT 1");
    $sTg = ($sTgR['ok'] && $sTgR['row'] && !empty($sTgR['row']['telegram_chat_id']))
        ? $sTgR['row']['telegram_chat_id'] : null;
    if ($sTg) {
        $tTgR = Database::fetchRow('Papir',
            "SELECT telegram_chat_id FROM counterparty WHERE id = {$targetId} LIMIT 1");
        if (!$tTgR['row']['telegram_chat_id']) {
            Database::update('Papir', 'counterparty', array('telegram_chat_id' => $sTg), array('id' => $targetId));
        }
    }

    if ($tPerson && $tPerson['id']) {
        $upd = array();
        if (empty($tPerson['phone']) && !empty($sPerson['phone'])) $upd['phone'] = $sPerson['phone'];
        if (empty($tPerson['email']) && !empty($sPerson['email'])) $upd['email'] = $sPerson['email'];
        if (empty($tPerson['phone']) && !empty($sCompany['phone'])) $upd['phone'] = $sCompany['phone'];
        if (empty($tPerson['email']) && !empty($sCompany['email'])) $upd['email'] = $sCompany['email'];
        if (!empty($upd)) Database::update('Papir', 'counterparty_person', $upd, array('id' => (int)$tPerson['id']));
    }
    if ($tCompany && $tCompany['id']) {
        $upd = array();
        if (empty($tCompany['phone']) && !empty($sCompany['phone'])) $upd['phone'] = $sCompany['phone'];
        if (empty($tCompany['email']) && !empty($sCompany['email'])) $upd['email'] = $sCompany['email'];
        if (empty($tCompany['phone']) && !empty($sPerson['phone'])) $upd['phone'] = $sPerson['phone'];
        if (empty($tCompany['email']) && !empty($sPerson['email'])) $upd['email'] = $sPerson['email'];
        if (!empty($upd)) Database::update('Papir', 'counterparty_company', $upd, array('id' => (int)$tCompany['id']));
    }

    // Deactivate source
    Database::query('Papir',
        "UPDATE counterparty SET status = 0 WHERE id = {$sourceId}");

    $totalMerged++;
}

echo "\n════════════════════════════════════════════════════════\n";
echo "Злито пар: {$totalMerged}\n";
if ($dryRun) echo "\n(DRY RUN — жодних змін не внесено)\n";