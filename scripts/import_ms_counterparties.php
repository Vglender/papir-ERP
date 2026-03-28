<?php
/**
 * Імпорт контрагентів з ms.Counterparty → Papir.counterparty
 *
 * Запуск:
 *   php scripts/import_ms_counterparties.php --dry-run   # перевірка без запису
 *   php scripts/import_ms_counterparties.php             # реальний імпорт
 *
 * Ідемпотентний: пропускає вже імпортовані записи (по id_ms).
 * Можна запускати повторно — додає тільки нових.
 *
 * Логіка класифікації:
 *   A) legalTitle порожній              → person
 *   B) legalTitle + code 8 цифр        → company + person(contact) + relation
 *   C1) ФОП + name ≈ legalTitle        → fop (одна сутність)
 *   C2) ФОП + name ≠ legalTitle        → fop + person(contact) + relation
 *   D) legalTitle, не ФОП, немає code  → company + person(contact) + relation
 */

require_once __DIR__ . '/../modules/database/database.php';

$dryRun    = in_array('--dry-run', $argv);
$logFile   = '/tmp/import_ms_counterparties.log';
$myPid     = getmypid();
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

/**
 * Чи name і legalTitle — одна і та сама особа?
 * (для ФОП: "ФОП Іваненко Петро" vs "Петро Іваненко")
 */
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

/**
 * Розбити ПІБ на компоненти (Прізвище Ім'я По-батькові)
 */
function splitName($fullName) {
    $parts = preg_split('/\s+/u', trim($fullName));
    return array(
        'last_name'   => isset($parts[0]) ? $parts[0] : null,
        'first_name'  => isset($parts[1]) ? $parts[1] : null,
        'middle_name' => isset($parts[2]) ? $parts[2] : null,
    );
}

/**
 * Визначити тип компанії (fop/company) та очистити ОКПО
 */
function classifyLegal($legalTitle, $code) {
    $isFop = (bool)preg_match('/^фоп\s/iu', trim($legalTitle));

    $cleanCode = preg_replace('/\D/', '', (string)$code);
    if (strlen($cleanCode) === 10) {
        $isFop = true; // ІПН фізособи
    }
    if ($cleanCode === '') {
        $cleanCode = null;
    }

    return array(
        'is_fop'       => $isFop,
        'company_type' => $isFop ? 'fop' : 'company',
        'okpo'         => $cleanCode,
    );
}

function e($db, $val) {
    return Database::escape($db, (string)$val);
}

function nullOrStr($db, $val) {
    $val = trim((string)$val);
    return ($val === '') ? 'NULL' : "'" . e($db, $val) . "'";
}

/**
 * INSERT counterparty. Повертає новий id або false.
 */
function insertCounterparty($type, $name, $idMs, $createdAt, $updatedAt, $dryRun) {
    $uuid  = generateUuid();
    $nameE = e('Papir', $name);
    $idMsS = $idMs ? "'" . e('Papir', $idMs) . "'" : 'NULL';
    $creE  = "'" . e('Papir', $createdAt) . "'";
    $updE  = "'" . e('Papir', $updatedAt) . "'";

    if ($dryRun) return 1; // fake id

    $r = Database::query('Papir',
        "INSERT INTO counterparty (uuid, id_ms, type, status, name, source, created_at, updated_at)
         VALUES ('{$uuid}', {$idMsS}, '{$type}', 1, '{$nameE}', 'moysklad', {$creE}, {$updE})"
    );
    if (!$r['ok']) return false;

    $r2 = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() as id");
    return ($r2['ok'] && $r2['row']) ? (int)$r2['row']['id'] : false;
}

function insertCompany($cId, $companyType, $fullName, $okpo, $phone, $email, $dryRun) {
    if ($dryRun) return true;
    $ctE   = e('Papir', $companyType);
    $fnS   = nullOrStr('Papir', $fullName);
    $okpoS = nullOrStr('Papir', $okpo);
    $phS   = nullOrStr('Papir', $phone);
    $emS   = nullOrStr('Papir', $email);

    $r = Database::query('Papir',
        "INSERT INTO counterparty_company
         (counterparty_id, company_type, full_name, okpo, phone, email)
         VALUES ({$cId}, '{$ctE}', {$fnS}, {$okpoS}, {$phS}, {$emS})"
    );
    return $r['ok'];
}

function insertPerson($cId, $fullName, $phone, $email, $dryRun) {
    if ($dryRun) return true;
    $parts = splitName($fullName);
    $fnS   = nullOrStr('Papir', $fullName);
    $lnS   = nullOrStr('Papir', $parts['last_name']);
    $fiS   = nullOrStr('Papir', $parts['first_name']);
    $miS   = nullOrStr('Papir', $parts['middle_name']);
    $phS   = nullOrStr('Papir', $phone);
    $emS   = nullOrStr('Papir', $email);

    $r = Database::query('Papir',
        "INSERT INTO counterparty_person
         (counterparty_id, full_name, last_name, first_name, middle_name, phone, email)
         VALUES ({$cId}, {$fnS}, {$lnS}, {$fiS}, {$miS}, {$phS}, {$emS})"
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

// ── Реєстрація в background_jobs ──────────────────────────────────────────

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Імпорт контрагентів з МойСклад',
        'script'   => 'scripts/import_ms_counterparties.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN (без запису в БД) ===' : '=== РЕАЛЬНИЙ ІМПОРТ ===');

// ── Завантажити вже імпортовані id_ms (щоб не залежати від підзапиту в циклі) ──

out('Завантаження вже імпортованих id_ms...');
$importedR = Database::fetchAll('Papir', "SELECT id_ms FROM counterparty WHERE id_ms IS NOT NULL");
$imported  = array();
if ($importedR['ok']) {
    foreach ($importedR['rows'] as $row) {
        $imported[$row['id_ms']] = true;
    }
}
out('Вже в базі: ' . count($imported) . ' записів');

// ── Загальна кількість в ms ───────────────────────────────────────────────

$totalR = Database::fetchRow('ms', "SELECT COUNT(*) as cnt FROM Counterparty WHERE meta IS NOT NULL AND meta != ''");
$totalMs = ($totalR['ok'] && $totalR['row']) ? (int)$totalR['row']['cnt'] : 0;
out("Всього в ms.Counterparty: {$totalMs}");

$stats = array(
    'person'    => 0,
    'company'   => 0,
    'fop'       => 0,
    'relations' => 0,
    'skipped'   => 0,
    'errors'    => 0,
);

$offset = 0;

while (true) {
    $rows = Database::fetchAll('ms',
        "SELECT id, meta, name, companyType, phone, email, legalTitle, code, created, updated
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

        // Пропустити вже імпортовані
        if (isset($imported[$idMs])) {
            $stats['skipped']++;
            continue;
        }

        if ($name === '') $name = $legalTitle ?: '(без назви)';

        // ── Кейс A: немає legalTitle → фізлице ───────────────────────────
        if ($legalTitle === '') {
            $cId = insertCounterparty('person', $name, $idMs, $createdAt, $updatedAt, $dryRun);
            if ($cId === false) { $stats['errors']++; continue; }
            if (!insertPerson($cId, $name, $phone, $email, $dryRun)) { $stats['errors']++; continue; }
            $stats['person']++;
            $imported[$idMs] = true;
            continue;
        }

        // ── є legalTitle: визначаємо тип ─────────────────────────────────
        $cl          = classifyLegal($legalTitle, $code);
        $isFop       = $cl['is_fop'];
        $companyType = $cl['company_type'];
        $okpo        = $cl['okpo'];

        if ($isFop) {
            // ── Кейс C: ФОП ──────────────────────────────────────────────
            if (isSamePerson($name, $legalTitle)) {
                // C1: ФОП = те саме лице → один контрагент
                $cId = insertCounterparty('fop', $legalTitle, $idMs, $createdAt, $updatedAt, $dryRun);
                if ($cId === false) { $stats['errors']++; continue; }
                if (!insertCompany($cId, 'fop', $legalTitle, $okpo, $phone, $email, $dryRun)) { $stats['errors']++; continue; }
                $stats['fop']++;
            } else {
                // C2: ФОП + окрема контактна особа
                $cIdFop = insertCounterparty('fop', $legalTitle, $idMs, $createdAt, $updatedAt, $dryRun);
                if ($cIdFop === false) { $stats['errors']++; continue; }
                if (!insertCompany($cIdFop, 'fop', $legalTitle, $okpo, $phone, $email, $dryRun)) { $stats['errors']++; continue; }

                $cIdPerson = insertCounterparty('person', $name, null, $createdAt, $updatedAt, $dryRun);
                if ($cIdPerson === false) { $stats['errors']++; continue; }
                if (!insertPerson($cIdPerson, $name, $phone, $email, $dryRun)) { $stats['errors']++; continue; }

                insertRelation($cIdFop, $cIdPerson, $dryRun);
                $stats['person']++;
                $stats['relations']++;
                $stats['fop']++;
            }
        } else {
            // ── Кейс B/D: компанія + контактна особа ─────────────────────
            $cIdCompany = insertCounterparty('company', $legalTitle, $idMs, $createdAt, $updatedAt, $dryRun);
            if ($cIdCompany === false) { $stats['errors']++; continue; }
            if (!insertCompany($cIdCompany, 'company', $legalTitle, $okpo, $phone, $email, $dryRun)) { $stats['errors']++; continue; }

            $cIdPerson = insertCounterparty('person', $name, null, $createdAt, $updatedAt, $dryRun);
            if ($cIdPerson === false) { $stats['errors']++; continue; }
            if (!insertPerson($cIdPerson, $name, $phone, $email, $dryRun)) { $stats['errors']++; continue; }

            insertRelation($cIdCompany, $cIdPerson, $dryRun);
            $stats['company']++;
            $stats['person']++;
            $stats['relations']++;
        }

        $imported[$idMs] = true;
    }

    $offset += $batchSize;
    $processed = $stats['person'] + $stats['company'] + $stats['fop'] + $stats['skipped'];
    out("Оброблено ~{$processed}/{$totalMs} | "
        . "person={$stats['person']} company={$stats['company']} fop={$stats['fop']} "
        . "relations={$stats['relations']} skipped={$stats['skipped']} errors={$stats['errors']}");
}

out('');
out('=== ГОТОВО ===');
out("Фізлиці:    {$stats['person']}");
out("Компанії:   {$stats['company']}");
out("ФОП:        {$stats['fop']}");
out("Зв'язки:    {$stats['relations']}");
out("Пропущено:  {$stats['skipped']}");
out("Помилки:    {$stats['errors']}");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}
