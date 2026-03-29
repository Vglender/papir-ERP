<?php
/**
 * Синк контрагентів: ms.Counterparty → Papir.counterparty
 *
 * Запуск:
 *   php scripts/sync_ms_counterparties.php --dry-run
 *   php scripts/sync_ms_counterparties.php
 *
 * Що робить:
 *   1. INSERT — нові записи з ms, яких ще немає в нашій БД (по id_ms)
 *   2. UPDATE — записи з ms де updated > наш updated_at (тільки source='moysklad')
 *   3. ARCHIVE — якщо archived='true' в ms → status=0 у нас (не видаляємо)
 *
 * Призначений для крону: запускати раз на кілька годин під час тестування.
 */

$_lockFp = fopen('/tmp/sync_ms_counterparties.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) { echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL; exit(0); }

require_once __DIR__ . '/../modules/database/database.php';

$dryRun  = in_array('--dry-run', $argv);
$logFile = '/tmp/sync_ms_counterparties.log';
$myPid   = getmypid();
$batchSize = 500;

function out($msg) {
    echo date('[H:i:s] ') . $msg . PHP_EOL;
}

function generateUuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function isSamePerson($name, $legalTitle) {
    $normalize = function($s) {
        $s = preg_replace('/^(фоп|тов|тзов|пп|чп|фг|гф|сфо)\s+/iu', '', trim($s));
        $tokens = preg_split('/[\s\-]+/u', mb_strtolower($s, 'UTF-8'));
        return array_values(array_filter($tokens, function($t) {
            return mb_strlen($t, 'UTF-8') > 1;
        }));
    };
    $nameTokens  = $normalize($name);
    $titleTokens = $normalize($legalTitle);
    if (empty($nameTokens) || empty($titleTokens)) return false;
    $matches = 0;
    foreach ($nameTokens as $t) {
        foreach ($titleTokens as $tt) {
            if ($t === $tt || (mb_strlen($t, 'UTF-8') > 3 && strpos($tt, $t) !== false)) {
                $matches++;
                break;
            }
        }
    }
    return $matches >= min(2, count($nameTokens));
}

function splitName($fullName) {
    $parts = preg_split('/\s+/u', trim($fullName));
    return array(
        'last_name'   => isset($parts[0]) ? $parts[0] : null,
        'first_name'  => isset($parts[1]) ? $parts[1] : null,
        'middle_name' => isset($parts[2]) ? $parts[2] : null,
    );
}

function classifyLegal($legalTitle, $code) {
    $isFop = (bool)preg_match('/^фоп\s/iu', trim($legalTitle));
    $cleanCode = preg_replace('/\D/', '', (string)$code);
    if (strlen($cleanCode) === 10) $isFop = true;
    if ($cleanCode === '') $cleanCode = null;
    return array(
        'is_fop'       => $isFop,
        'company_type' => $isFop ? 'fop' : 'company',
        'okpo'         => $cleanCode,
    );
}

function e($db, $val) {
    return Database::escape($db, (string)$val);
}

function nullOrStr($db, $val, $maxLen = 0) {
    $val = trim((string)$val);
    if ($val === '') return 'NULL';
    if ($maxLen > 0 && mb_strlen($val, 'UTF-8') > $maxLen) {
        $val = mb_substr($val, 0, $maxLen, 'UTF-8');
    }
    return "'" . e($db, $val) . "'";
}

function insertCounterparty($type, $name, $idMs, $createdAt, $updatedAt, $dryRun) {
    $uuid  = generateUuid();
    $idMsS = $idMs ? "'" . e('Papir', $idMs) . "'" : 'NULL';
    $creE  = "'" . e('Papir', $createdAt) . "'";
    $updE  = "'" . e('Papir', $updatedAt) . "'";
    if ($dryRun) return 1;
    $r = Database::query('Papir',
        "INSERT INTO counterparty (uuid, id_ms, type, status, name, source, created_at, updated_at)
         VALUES ('{$uuid}', {$idMsS}, '{$type}', 1, " . nullOrStr('Papir', $name, 255) . ", 'moysklad', {$creE}, {$updE})"
    );
    if (!$r['ok']) return false;
    $r2 = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() as id");
    return ($r2['ok'] && $r2['row']) ? (int)$r2['row']['id'] : false;
}

function insertCompany($cId, $companyType, $fullName, $okpo, $phone, $email, $dryRun) {
    if ($dryRun) return true;
    $r = Database::query('Papir',
        "INSERT INTO counterparty_company (counterparty_id, company_type, full_name, okpo, phone, email)
         VALUES ({$cId}, '" . e('Papir', $companyType) . "', " . nullOrStr('Papir', $fullName, 255) . ", "
        . nullOrStr('Papir', $okpo) . ", " . nullOrStr('Papir', $phone) . ", " . nullOrStr('Papir', $email) . ")"
    );
    return $r['ok'];
}

function insertPerson($cId, $fullName, $phone, $email, $dryRun) {
    if ($dryRun) return true;
    $parts = splitName($fullName);
    $r = Database::query('Papir',
        "INSERT INTO counterparty_person (counterparty_id, full_name, last_name, first_name, middle_name, phone, email)
         VALUES ({$cId}, " . nullOrStr('Papir', $fullName) . ", "
        . nullOrStr('Papir', $parts['last_name']) . ", "
        . nullOrStr('Papir', $parts['first_name']) . ", "
        . nullOrStr('Papir', $parts['middle_name']) . ", "
        . nullOrStr('Papir', $phone) . ", "
        . nullOrStr('Papir', $email) . ")"
    );
    return $r['ok'];
}

function insertRelation($parentId, $childId, $dryRun) {
    if ($dryRun) return true;
    $r = Database::query('Papir',
        "INSERT INTO counterparty_relation
         (parent_counterparty_id, child_counterparty_id, relation_type, is_primary)
         VALUES ({$parentId}, {$childId}, 'contact_person', 1)"
    );
    return $r['ok'];
}

// ── Реєстрація в background_jobs ─────────────────────────────────────────────

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синк контрагентів з МойСклад',
        'script'   => 'scripts/sync_ms_counterparties.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК КОНТРАГЕНТІВ ===');

// ── Завантажити існуючі записи: id_ms → {id, updated_at, type} ───────────────

out('Завантаження існуючих записів...');
$existingR = Database::fetchAll('Papir',
    "SELECT id, id_ms, type, updated_at FROM counterparty WHERE id_ms IS NOT NULL"
);
$existing = array(); // id_ms → array(id, updated_at, type)
if ($existingR['ok']) {
    foreach ($existingR['rows'] as $row) {
        $existing[$row['id_ms']] = array(
            'id'         => (int)$row['id'],
            'updated_at' => $row['updated_at'],
            'type'       => $row['type'],
        );
    }
}
out('В нашій базі: ' . count($existing) . ' записів з id_ms');

// ── Загальна кількість ────────────────────────────────────────────────────────

$totalR = Database::fetchRow('ms', "SELECT COUNT(*) as cnt FROM Counterparty WHERE meta IS NOT NULL AND meta != ''");
$totalMs = ($totalR['ok'] && $totalR['row']) ? (int)$totalR['row']['cnt'] : 0;
out("В ms.Counterparty: {$totalMs}");

$stats = array(
    'inserted'  => 0,
    'updated'   => 0,
    'archived'  => 0,
    'skipped'   => 0,
    'errors'    => 0,
);

$offset = 0;

while (true) {
    $rows = Database::fetchAll('ms',
        "SELECT id, meta, name, companyType, phone, email, legalTitle, code, created, updated, archived
         FROM Counterparty
         WHERE meta IS NOT NULL AND meta != ''
         ORDER BY id
         LIMIT {$batchSize} OFFSET {$offset}"
    );

    if (!$rows['ok'] || empty($rows['rows'])) break;

    foreach ($rows['rows'] as $row) {
        $idMs       = trim((string)$row['meta']);
        $name       = trim((string)$row['name']);
        $phone      = trim((string)$row['phone']);
        $email      = trim((string)$row['email']);
        $legalTitle = trim((string)$row['legalTitle']);
        $code       = trim((string)$row['code']);
        $createdAt  = $row['created'] ? (string)$row['created'] : date('Y-m-d H:i:s');
        $updatedAt  = $row['updated'] ? (string)$row['updated'] : $createdAt;
        $isArchived = ($row['archived'] === 'true');

        if ($name === '') $name = $legalTitle ?: '(без назви)';

        // ── UPDATE: запис існує ───────────────────────────────────────────────
        if (isset($existing[$idMs])) {
            $rec = $existing[$idMs];

            // Архівований → деактивувати
            if ($isArchived) {
                if (!$dryRun) {
                    Database::query('Papir',
                        "UPDATE counterparty SET status=0, updated_at='" . e('Papir', $updatedAt) . "'
                         WHERE id={$rec['id']} AND source='moysklad' AND status=1"
                    );
                }
                $stats['archived']++;
                continue;
            }

            // Порівнюємо час оновлення
            if ($updatedAt <= $rec['updated_at']) {
                $stats['skipped']++;
                continue;
            }

            // Оновлюємо тільки записи з source='moysklad' (не чіпаємо ручні правки)
            if (!$dryRun) {
                Database::query('Papir',
                    "UPDATE counterparty SET name=" . nullOrStr('Papir', $name, 255) . ", "
                    . "status=1, updated_at='" . e('Papir', $updatedAt) . "' "
                    . "WHERE id={$rec['id']} AND source='moysklad'"
                );
            }

            // Оновити деталі (company або person)
            if ($rec['type'] === 'person') {
                $parts = splitName($name);
                if (!$dryRun) {
                    Database::query('Papir',
                        "UPDATE counterparty_person SET
                         full_name=" . nullOrStr('Papir', $name) . ",
                         last_name=" . nullOrStr('Papir', $parts['last_name']) . ",
                         first_name=" . nullOrStr('Papir', $parts['first_name']) . ",
                         middle_name=" . nullOrStr('Papir', $parts['middle_name']) . ",
                         phone=" . nullOrStr('Papir', $phone) . ",
                         email=" . nullOrStr('Papir', $email) . "
                         WHERE counterparty_id={$rec['id']}"
                    );
                }
            } else {
                // company або fop
                $cl = classifyLegal($legalTitle, $code);
                if (!$dryRun) {
                    Database::query('Papir',
                        "UPDATE counterparty_company SET
                         full_name=" . nullOrStr('Papir', $legalTitle ?: $name) . ",
                         okpo=" . nullOrStr('Papir', $cl['okpo']) . ",
                         phone=" . nullOrStr('Papir', $phone) . ",
                         email=" . nullOrStr('Papir', $email) . "
                         WHERE counterparty_id={$rec['id']}"
                    );
                }
            }

            $stats['updated']++;
            continue;
        }

        // ── INSERT: новий запис ───────────────────────────────────────────────

        if ($isArchived) {
            // Архівовані нові не імпортуємо
            $stats['skipped']++;
            continue;
        }

        if ($legalTitle === '') {
            // Кейс A: фізлице
            $cId = insertCounterparty('person', $name, $idMs, $createdAt, $updatedAt, $dryRun);
            if ($cId === false) { $stats['errors']++; continue; }
            if (!insertPerson($cId, $name, $phone, $email, $dryRun)) { $stats['errors']++; continue; }
            $stats['inserted']++;
            $existing[$idMs] = array('id' => $cId, 'updated_at' => $updatedAt, 'type' => 'person');
            continue;
        }

        $cl          = classifyLegal($legalTitle, $code);
        $isFop       = $cl['is_fop'];
        $companyType = $cl['company_type'];
        $okpo        = $cl['okpo'];

        if ($isFop) {
            if (isSamePerson($name, $legalTitle)) {
                // C1: ФОП = одна особа
                $cId = insertCounterparty('fop', $legalTitle, $idMs, $createdAt, $updatedAt, $dryRun);
                if ($cId === false) { $stats['errors']++; continue; }
                if (!insertCompany($cId, 'fop', $legalTitle, $okpo, $phone, $email, $dryRun)) { $stats['errors']++; continue; }
                $stats['inserted']++;
                $existing[$idMs] = array('id' => $cId, 'updated_at' => $updatedAt, 'type' => 'fop');
            } else {
                // C2: ФОП + окремий контакт
                $cIdFop = insertCounterparty('fop', $legalTitle, $idMs, $createdAt, $updatedAt, $dryRun);
                if ($cIdFop === false) { $stats['errors']++; continue; }
                if (!insertCompany($cIdFop, 'fop', $legalTitle, $okpo, $phone, $email, $dryRun)) { $stats['errors']++; continue; }
                $cIdPerson = insertCounterparty('person', $name, null, $createdAt, $updatedAt, $dryRun);
                if ($cIdPerson === false) { $stats['errors']++; continue; }
                if (!insertPerson($cIdPerson, $name, $phone, $email, $dryRun)) { $stats['errors']++; continue; }
                insertRelation($cIdFop, $cIdPerson, $dryRun);
                $stats['inserted'] += 2;
                $existing[$idMs] = array('id' => $cIdFop, 'updated_at' => $updatedAt, 'type' => 'fop');
            }
        } else {
            // B/D: компанія + контакт
            $cIdCompany = insertCounterparty('company', $legalTitle, $idMs, $createdAt, $updatedAt, $dryRun);
            if ($cIdCompany === false) { $stats['errors']++; continue; }
            if (!insertCompany($cIdCompany, 'company', $legalTitle, $okpo, $phone, $email, $dryRun)) { $stats['errors']++; continue; }
            $cIdPerson = insertCounterparty('person', $name, null, $createdAt, $updatedAt, $dryRun);
            if ($cIdPerson === false) { $stats['errors']++; continue; }
            if (!insertPerson($cIdPerson, $name, $phone, $email, $dryRun)) { $stats['errors']++; continue; }
            insertRelation($cIdCompany, $cIdPerson, $dryRun);
            $stats['inserted'] += 2;
            $existing[$idMs] = array('id' => $cIdCompany, 'updated_at' => $updatedAt, 'type' => 'company');
        }
    }

    $offset += $batchSize;
    $processed = $stats['inserted'] + $stats['updated'] + $stats['archived'] + $stats['skipped'];
    out("~{$processed}/{$totalMs} | +{$stats['inserted']} upd={$stats['updated']} arch={$stats['archived']} skip={$stats['skipped']} err={$stats['errors']}");
}

out('');
out('=== ГОТОВО ===');
out("Додано:      {$stats['inserted']}");
out("Оновлено:   {$stats['updated']}");
out("Архівовано: {$stats['archived']}");
out("Пропущено:  {$stats['skipped']}");
out("Помилки:    {$stats['errors']}");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}
