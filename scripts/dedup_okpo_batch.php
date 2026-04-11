<?php
/**
 * Batch dedup by OKPO:
 * 1. For each OKPO with 2+ companies: merge all companies into the one with most orders
 *    (tie-break: latest created_at)
 * 2. Link all related persons as "employee" to the target company
 *
 * Usage: php scripts/dedup_okpo_batch.php [--dry-run]
 */
$dryRun = in_array('--dry-run', $argv);

require_once __DIR__ . '/../modules/database/database.php';

if ($dryRun) echo "=== DRY RUN (no changes) ===\n\n";

// ── Step 1: Find all OKPO groups with 2+ active companies ───────────────────

$okpoGroups = Database::fetchAll('Papir',
    "SELECT TRIM(cc.okpo) AS okpo,
            c.id, c.name, c.type, c.created_at,
            COALESCE(NULLIF(cp.phone,''), NULLIF(cc.phone,'')) AS phone,
            COALESCE(NULLIF(cp.email,''), NULLIF(cc.email,'')) AS email,
            (SELECT COUNT(*) FROM customerorder co WHERE co.counterparty_id = c.id AND co.deleted_at IS NULL) AS orders
     FROM counterparty c
     JOIN counterparty_company cc ON cc.counterparty_id = c.id
     LEFT JOIN counterparty_person cp ON cp.counterparty_id = c.id
     WHERE c.status = 1 AND cc.okpo IS NOT NULL AND cc.okpo != '' AND LENGTH(TRIM(cc.okpo)) >= 6
       AND TRIM(cc.okpo) IN (
         SELECT t.okpo FROM (
           SELECT TRIM(cc2.okpo) AS okpo
           FROM counterparty c2
           JOIN counterparty_company cc2 ON cc2.counterparty_id = c2.id
           WHERE c2.status = 1 AND cc2.okpo IS NOT NULL AND cc2.okpo != '' AND LENGTH(TRIM(cc2.okpo)) >= 6
           GROUP BY TRIM(cc2.okpo) HAVING COUNT(*) > 1
         ) t
       )
     ORDER BY TRIM(cc.okpo), orders DESC, c.created_at DESC");

if (!$okpoGroups['ok'] || empty($okpoGroups['rows'])) {
    echo "No OKPO duplicates found.\n";
    exit;
}

// Group by OKPO
$groups = array();
foreach ($okpoGroups['rows'] as $row) {
    $okpo = $row['okpo'];
    if (!isset($groups[$okpo])) $groups[$okpo] = array();
    $groups[$okpo][] = $row;
}

echo "Found " . count($groups) . " OKPO groups with duplicates\n\n";

// ── Step 2: Find persons linked to these companies by phone/email ───────────

$personsR = Database::fetchAll('Papir',
    "SELECT TRIM(cc.okpo) AS okpo, c_pers.id AS person_id, c_pers.name AS person_name,
            cp_pers.phone AS person_phone, cp_pers.email AS person_email
     FROM counterparty c_comp
     JOIN counterparty_company cc ON cc.counterparty_id = c_comp.id
     LEFT JOIN counterparty_person cp_comp ON cp_comp.counterparty_id = c_comp.id
     JOIN counterparty c_pers ON c_pers.status = 1 AND c_pers.type = 'person'
     JOIN counterparty_person cp_pers ON cp_pers.counterparty_id = c_pers.id
     WHERE c_comp.status = 1 AND cc.okpo IS NOT NULL AND TRIM(cc.okpo) != '' AND LENGTH(TRIM(cc.okpo)) >= 6
       AND TRIM(cc.okpo) IN ('" . implode("','", array_map(function($o) { return addslashes($o); }, array_keys($groups))) . "')
       AND c_pers.id != c_comp.id
       AND (
         (
           COALESCE(NULLIF(cp_comp.phone,''), NULLIF(cc.phone,'')) IS NOT NULL
           AND cp_pers.phone IS NOT NULL AND cp_pers.phone != ''
           AND LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cp_pers.phone,'+',''),'-',''),' ',''),'(',''),')',''),'.','')) >= 7
           AND RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cp_pers.phone,'+',''),'-',''),' ',''),'(',''),')',''),'.',''), 9)
             = RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                 COALESCE(NULLIF(cp_comp.phone,''), NULLIF(cc.phone,'')),'+',''),'-',''),' ',''),'(',''),')',''),'.',''), 9)
         )
         OR (
           COALESCE(NULLIF(cp_comp.email,''), NULLIF(cc.email,'')) IS NOT NULL
           AND cp_pers.email IS NOT NULL AND cp_pers.email != ''
           AND LOWER(TRIM(cp_pers.email)) = LOWER(TRIM(COALESCE(NULLIF(cp_comp.email,''), NULLIF(cc.email,''))))
         )
       )
     GROUP BY TRIM(cc.okpo), c_pers.id");

$personsByOkpo = array();
if ($personsR['ok']) {
    foreach ($personsR['rows'] as $row) {
        $okpo = $row['okpo'];
        if (!isset($personsByOkpo[$okpo])) $personsByOkpo[$okpo] = array();
        // Deduplicate by person_id
        $personsByOkpo[$okpo][$row['person_id']] = $row;
    }
}

// ── Step 3: Process each OKPO group ─────────────────────────────────────────

$totalMerged    = 0;
$totalLinked    = 0;
$totalGroups    = 0;

// Tables to transfer during merge (same as merge_dedup.php)
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

foreach ($groups as $okpo => $companies) {
    // Target = first in array (sorted by orders DESC, created_at DESC)
    $target    = $companies[0];
    $targetId  = (int)$target['id'];
    $sourceIds = array();
    for ($i = 1; $i < count($companies); $i++) {
        $sourceIds[] = (int)$companies[$i]['id'];
    }

    $persons = isset($personsByOkpo[$okpo]) ? array_values($personsByOkpo[$okpo]) : array();

    echo "── ЄДРПОУ: {$okpo} ─────────────────────────────────────\n";
    echo "   Ціль: #{$targetId} {$target['name']} ({$target['orders']} замовл.)\n";
    if (!empty($sourceIds)) {
        foreach ($sourceIds as $sid) {
            $sName = '';
            foreach ($companies as $c) {
                if ((int)$c['id'] === $sid) { $sName = $c['name'] . ' (' . $c['orders'] . ' замовл.)'; break; }
            }
            echo "   Злити: #{$sid} {$sName}\n";
        }
    }
    if (!empty($persons)) {
        foreach ($persons as $p) {
            echo "   Зв'язати: #{$p['person_id']} {$p['person_name']} → employee\n";
        }
    }

    if ($dryRun) {
        echo "\n";
        $totalGroups++;
        $totalMerged += count($sourceIds);
        $totalLinked += count($persons);
        continue;
    }

    // ── Merge companies ──────────────────────────────────────────────────────
    if (!empty($sourceIds)) {
        $sourcesSql = implode(',', $sourceIds);

        // Transfer all related data to target
        foreach ($mergeTables as $table) {
            Database::query('Papir',
                "UPDATE {$table} SET counterparty_id = {$targetId}
                 WHERE counterparty_id IN ({$sourcesSql})");
        }

        // Copy missing contact data
        $tPersonR  = Database::fetchRow('Papir',
            "SELECT id, phone, email FROM counterparty_person WHERE counterparty_id = {$targetId} LIMIT 1");
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
                "SELECT phone, email FROM counterparty_person WHERE counterparty_id = {$srcId} LIMIT 1");
            $sCompanyR = Database::fetchRow('Papir',
                "SELECT phone, email FROM counterparty_company WHERE counterparty_id = {$srcId} LIMIT 1");
            $sTgR = Database::fetchRow('Papir',
                "SELECT telegram_chat_id FROM counterparty WHERE id = {$srcId} LIMIT 1");

            $sPerson  = ($sPersonR['ok']  && $sPersonR['row'])  ? $sPersonR['row']  : null;
            $sCompany = ($sCompanyR['ok'] && $sCompanyR['row']) ? $sCompanyR['row'] : null;
            $sTg      = ($sTgR['ok'] && $sTgR['row']) ? $sTgR['row']['telegram_chat_id'] : null;

            if (!$tTg && $sTg) {
                Database::update('Papir', 'counterparty', array('telegram_chat_id' => $sTg), array('id' => $targetId));
                $tTg = $sTg;
            }
            if ($tPerson && $tPerson['id']) {
                $upd = array();
                if (empty($tPerson['phone']) && !empty($sPerson['phone'])) {
                    $upd['phone'] = $sPerson['phone']; $tPerson['phone'] = $sPerson['phone'];
                }
                if (empty($tPerson['email']) && !empty($sPerson['email'])) {
                    $upd['email'] = $sPerson['email']; $tPerson['email'] = $sPerson['email'];
                }
                if (!empty($upd)) {
                    Database::update('Papir', 'counterparty_person', $upd, array('id' => (int)$tPerson['id']));
                }
            }
            if ($tCompany && $tCompany['id']) {
                $upd = array();
                if (empty($tCompany['phone']) && !empty($sCompany['phone'])) {
                    $upd['phone'] = $sCompany['phone']; $tCompany['phone'] = $sCompany['phone'];
                }
                if (empty($tCompany['email']) && !empty($sCompany['email'])) {
                    $upd['email'] = $sCompany['email']; $tCompany['email'] = $sCompany['email'];
                }
                if (!empty($upd)) {
                    Database::update('Papir', 'counterparty_company', $upd, array('id' => (int)$tCompany['id']));
                }
            }
        }

        // Deactivate sources
        Database::query('Papir',
            "UPDATE counterparty SET status = 0 WHERE id IN ({$sourcesSql})");

        $totalMerged += count($sourceIds);
    }

    // ── Link persons as employees ────────────────────────────────────────────
    foreach ($persons as $p) {
        $personId = (int)$p['person_id'];
        // Check if relation already exists
        $existsR = Database::fetchRow('Papir',
            "SELECT id FROM counterparty_relation
             WHERE parent_counterparty_id = {$targetId} AND child_counterparty_id = {$personId}
             LIMIT 1");
        if ($existsR['ok'] && $existsR['row']) {
            echo "   (зв'язок #{$personId} вже існує, пропускаю)\n";
            continue;
        }

        Database::insert('Papir', 'counterparty_relation', array(
            'parent_counterparty_id' => $targetId,
            'child_counterparty_id'  => $personId,
            'relation_type'          => 'employee',
            'department_name'        => '',
            'job_title'              => '',
            'is_primary'             => 0,
            'comment'                => 'Auto-linked during OKPO dedup',
        ));
        $totalLinked++;
    }

    $totalGroups++;
    echo "\n";
}

echo "════════════════════════════════════════════════════════\n";
echo "Оброблено груп:    {$totalGroups}\n";
echo "Злито компаній:    {$totalMerged}\n";
echo "Прив'язано фізосіб: {$totalLinked}\n";
if ($dryRun) echo "\n(DRY RUN — жодних змін не внесено)\n";
