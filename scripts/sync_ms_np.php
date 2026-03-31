<?php
/**
 * Синк ТТН Нової Пошти: ms.np → Papir.ttn_novaposhta
 *
 * Запуск:
 *   php scripts/sync_ms_np.php --dry-run
 *   php scripts/sync_ms_np.php
 *
 * Логіка:
 *   - INSERT нові ТТН (по ref)
 *   - UPDATE змінені (ms.DateLastUpdatedStatus > наш updated_at)
 *   - Резолвить customerorder_id та demand_id через id_ms
 */

$_lockFp = fopen('/tmp/sync_ms_np.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) { echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL; exit(0); }

require_once __DIR__ . '/../modules/database/database.php';

$dryRun    = in_array('--dry-run', $argv);
$logFile   = '/tmp/sync_ms_np.log';
$myPid     = getmypid();
$batchSize = 1000;

function out($msg) { echo date('[H:i:s] ') . $msg . PHP_EOL; }
function e($v)     { return Database::escape('Papir', (string)$v); }
function nullOrStr($v, $max = 0) {
    $v = trim((string)$v);
    if ($v === '') return 'NULL';
    if ($max > 0 && mb_strlen($v, 'UTF-8') > $max) $v = mb_substr($v, 0, $max, 'UTF-8');
    return "'" . e($v) . "'";
}
function nullOrDec($v) {
    $v = trim((string)$v);
    return ($v === '') ? 'NULL' : (float)$v;
}
function nullOrInt($v) {
    $v = trim((string)$v);
    return ($v === '') ? 'NULL' : (int)$v;
}
function nullOrDate($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return 'NULL';
    return "'" . e($v) . "'";
}

// ── Реєстрація в background_jobs ─────────────────────────────────────────────

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синк ТТН Нової Пошти з МойСклад',
        'script'   => 'scripts/sync_ms_np.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК ТТН НОВОЇ ПОШТИ ===');

// ── Map: customerorder.id_ms → customerorder.id ───────────────────────────────

out('Завантаження map замовлень...');
$orderMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM customerorder WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $orderMap[$row['id_ms']] = (int)$row['id'];
out('Замовлень: ' . count($orderMap));

// ── Map: demand.id_ms → demand.id ────────────────────────────────────────────

out('Завантаження map відвантажень...');
$demandMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM demand WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $demandMap[$row['id_ms']] = (int)$row['id'];
out('Відвантажень: ' . count($demandMap));

// ── Існуючі ТТН: ref → updated_at ────────────────────────────────────────────

out('Завантаження існуючих ТТН...');
$existing = array(); // ref → ['id' => int, 'updated_at' => string]
$r = Database::fetchAll('Papir', "SELECT id, ref, updated_at FROM ttn_novaposhta");
if ($r['ok']) foreach ($r['rows'] as $row) {
    $existing[$row['ref']] = array('id' => (int)$row['id'], 'updated_at' => $row['updated_at']);
}
out('В нашій БД: ' . count($existing));

// ── Загальна кількість в ms ───────────────────────────────────────────────────

$totalR = Database::fetchRow('ms', "SELECT COUNT(*) as cnt FROM np WHERE Ref IS NOT NULL AND Ref != ''");
$total  = ($totalR['ok'] && $totalR['row']) ? (int)$totalR['row']['cnt'] : 0;
out("В ms.np: {$total}");

$stats  = array('inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0);
$offset = 0;

while (true) {
    $rows = Database::fetchAll('ms',
        "SELECT Ref, IntDocNumber, id_ord, id_demand, id_owner,
                DateTime, DateLastUpdatedStatus, EstimatedDeliveryDate,
                EWDateCreated, DateFirstDayStorage, arrived,
                StateId, StateName, state_define,
                RecipientsPhone, RecipientAddress, RecipientAddressDescription,
                CitySenderDescription, CityRecipientDescription,
                ServiceType, PaymentMethod, PayerType,
                Cost, CostOnSite, BackwardDeliveryMoney, AfterpaymentOnGoodsCost,
                StatePayId, ExpressWaybillPaymentStatus,
                Weight, SeatsAmount,
                Sender, PhoneSender, SenderContactPerson, RecipientContactPerson,
                DeletionMark
         FROM np
         WHERE Ref IS NOT NULL AND Ref != ''
         ORDER BY Ref
         LIMIT {$batchSize} OFFSET {$offset}"
    );

    if (!$rows['ok'] || empty($rows['rows'])) break;

    foreach ($rows['rows'] as $row) {
        $ref       = trim((string)$row['Ref']);
        $updatedMs = $row['DateLastUpdatedStatus'] ? (string)$row['DateLastUpdatedStatus'] : '';

        // ── Резолв FK ────────────────────────────────────────────────────────
        $idMsOrder  = trim((string)$row['id_ord']);
        $idMsDemand = trim((string)$row['id_demand']);
        $orderId    = ($idMsOrder  && isset($orderMap[$idMsOrder]))   ? $orderMap[$idMsOrder]   : null;
        $demandId   = ($idMsDemand && isset($demandMap[$idMsDemand])) ? $demandMap[$idMsDemand] : null;

        // ── SQL значення ─────────────────────────────────────────────────────
        $refS           = nullOrStr($ref, 36);
        $intDocS        = nullOrStr($row['IntDocNumber'], 20);
        $idMsOrderS     = nullOrStr($idMsOrder, 36);
        $idMsDemandS    = nullOrStr($idMsDemand, 36);
        $orderIdSql     = $orderId  ? $orderId  : 'NULL';
        $demandIdSql    = $demandId ? $demandId : 'NULL';

        $stateIdSql     = nullOrInt($row['StateId']);
        $stateNameS     = nullOrStr($row['StateName'], 256);
        $stateDefSql    = nullOrInt($row['state_define']);

        $momentSql      = nullOrDate($row['DateTime']);
        $ewDateSql      = nullOrDate($row['EWDateCreated']);
        $lastUpdSql     = nullOrDate($row['DateLastUpdatedStatus']);
        $estDelivSql    = nullOrDate($row['EstimatedDeliveryDate']);
        $firstStorSql   = nullOrDate($row['DateFirstDayStorage']);
        $arrivedSql     = nullOrDate($row['arrived']);
        $updSql         = $updatedMs ? "'" . e($updatedMs) . "'" : 'NOW()';

        $phoneRecS      = nullOrStr($row['RecipientsPhone'], 20);
        $recipAddrS     = nullOrStr($row['RecipientAddress'], 36);
        $recipAddrDescS = nullOrStr($row['RecipientAddressDescription'], 512);
        $citySenderS    = nullOrStr($row['CitySenderDescription'], 64);
        $cityRecipS     = nullOrStr($row['CityRecipientDescription'], 64);
        $serviceTypeS   = nullOrStr($row['ServiceType'], 32);
        $payMethodS     = nullOrStr($row['PaymentMethod'], 16);
        $payerTypeS     = nullOrStr($row['PayerType'], 32);

        $cost           = nullOrDec($row['Cost']);
        $costOnSite     = nullOrDec($row['CostOnSite']);
        $backwardMoney  = nullOrDec($row['BackwardDeliveryMoney']);
        $afterpayment   = nullOrDec($row['AfterpaymentOnGoodsCost']);
        $statePayId     = (int)$row['StatePayId'];
        $ewPayStatus    = (int)$row['ExpressWaybillPaymentStatus'];

        $weight         = nullOrDec($row['Weight']);
        $seats          = nullOrInt($row['SeatsAmount']);

        $senderRefS     = nullOrStr($row['Sender'], 36);
        $phoneSenderS   = nullOrStr($row['PhoneSender'], 20);
        $senderCpS      = nullOrStr($row['SenderContactPerson'], 128);
        $recipCpS       = nullOrStr($row['RecipientContactPerson'], 128);
        $delMark        = (int)$row['DeletionMark'];
        $idOwnerS       = nullOrStr($row['id_owner'], 36);

        // ── UPDATE ────────────────────────────────────────────────────────────
        if (isset($existing[$ref])) {
            if ($updatedMs && $updatedMs <= $existing[$ref]['updated_at']) {
                $stats['skipped']++;
                continue;
            }
            if (!$dryRun) {
                $r2 = Database::query('Papir',
                    "UPDATE ttn_novaposhta SET
                     int_doc_number={$intDocS}, id_ms_order={$idMsOrderS}, id_ms_demand={$idMsDemandS},
                     customerorder_id={$orderIdSql}, demand_id={$demandIdSql},
                     state_id={$stateIdSql}, state_name={$stateNameS}, state_define={$stateDefSql},
                     moment={$momentSql}, ew_date_created={$ewDateSql},
                     date_last_updated_status={$lastUpdSql}, estimated_delivery_date={$estDelivSql},
                     date_first_day_storage={$firstStorSql}, arrived={$arrivedSql},
                     recipients_phone={$phoneRecS}, recipient_address={$recipAddrS},
                     recipient_address_desc={$recipAddrDescS},
                     city_sender_desc={$citySenderS}, city_recipient_desc={$cityRecipS},
                     service_type={$serviceTypeS}, payment_method={$payMethodS}, payer_type={$payerTypeS},
                     cost={$cost}, cost_on_site={$costOnSite},
                     backward_delivery_money={$backwardMoney}, afterpayment_on_goods_cost={$afterpayment},
                     state_pay_id={$statePayId}, express_waybill_pay_status={$ewPayStatus},
                     weight={$weight}, seats_amount={$seats},
                     sender_ref={$senderRefS}, phone_sender={$phoneSenderS},
                     sender_contact_person={$senderCpS}, recipient_contact_person={$recipCpS},
                     deletion_mark={$delMark}, id_owner={$idOwnerS}, updated_at={$updSql}
                     WHERE ref={$refS}"
                );
                if (!$r2['ok']) { $stats['errors']++; continue; }

                // Синк document_link — INSERT IGNORE на випадок якщо запис ще не існує
                if ($orderId) {
                    $ttnId = $existing[$ref]['id'];
                    Database::query('Papir',
                        "INSERT IGNORE INTO document_link
                         (from_type, from_id, from_ms_id, to_type, to_id, to_ms_id, created_at)
                         VALUES ('ttn_np', {$ttnId}, {$refS}, 'customerorder', {$orderIdSql}, {$idMsOrderS}, NOW())"
                    );
                }
            }
            $existing[$ref]['updated_at'] = $updatedMs;
            $stats['updated']++;
            continue;
        }

        // ── INSERT ────────────────────────────────────────────────────────────
        $newTtnId = 0;
        if (!$dryRun) {
            $r2 = Database::query('Papir',
                "INSERT INTO ttn_novaposhta
                 (ref, int_doc_number, id_ms_order, id_ms_demand,
                  customerorder_id, demand_id,
                  state_id, state_name, state_define,
                  moment, ew_date_created, date_last_updated_status,
                  estimated_delivery_date, date_first_day_storage, arrived,
                  recipients_phone, recipient_address, recipient_address_desc,
                  city_sender_desc, city_recipient_desc,
                  service_type, payment_method, payer_type,
                  cost, cost_on_site, backward_delivery_money, afterpayment_on_goods_cost,
                  state_pay_id, express_waybill_pay_status,
                  weight, seats_amount,
                  sender_ref, phone_sender, sender_contact_person, recipient_contact_person,
                  deletion_mark, id_owner, updated_at)
                 VALUES
                 ({$refS}, {$intDocS}, {$idMsOrderS}, {$idMsDemandS},
                  {$orderIdSql}, {$demandIdSql},
                  {$stateIdSql}, {$stateNameS}, {$stateDefSql},
                  {$momentSql}, {$ewDateSql}, {$lastUpdSql},
                  {$estDelivSql}, {$firstStorSql}, {$arrivedSql},
                  {$phoneRecS}, {$recipAddrS}, {$recipAddrDescS},
                  {$citySenderS}, {$cityRecipS},
                  {$serviceTypeS}, {$payMethodS}, {$payerTypeS},
                  {$cost}, {$costOnSite}, {$backwardMoney}, {$afterpayment},
                  {$statePayId}, {$ewPayStatus},
                  {$weight}, {$seats},
                  {$senderRefS}, {$phoneSenderS}, {$senderCpS}, {$recipCpS},
                  {$delMark}, {$idOwnerS}, {$updSql})"
            );
            if (!$r2['ok']) { $stats['errors']++; continue; }

            // Отримуємо id нової ТТН і одразу синкуємо document_link
            $rLast = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS id");
            $newTtnId = ($rLast['ok'] && $rLast['row']) ? (int)$rLast['row']['id'] : 0;
            if ($newTtnId > 0 && $orderId) {
                Database::query('Papir',
                    "INSERT IGNORE INTO document_link
                     (from_type, from_id, from_ms_id, to_type, to_id, to_ms_id, created_at)
                     VALUES ('ttn_np', {$newTtnId}, {$refS}, 'customerorder', {$orderIdSql}, {$idMsOrderS}, NOW())"
                );
            }
        }

        $existing[$ref] = array('id' => $newTtnId, 'updated_at' => $updatedMs);
        $stats['inserted']++;
    }

    $offset += $batchSize;
    $done = $stats['inserted'] + $stats['updated'] + $stats['skipped'] + $stats['errors'];
    if ($done % 10000 < $batchSize || $offset % 10000 === 0) {
        out("~{$done}/{$total} | +{$stats['inserted']} upd={$stats['updated']} skip={$stats['skipped']} err={$stats['errors']}");
    }
}

out('');
out('=== ГОТОВО ===');
out("Додано:    {$stats['inserted']}");
out("Оновлено:  {$stats['updated']}");
out("Пропущено: {$stats['skipped']}");
out("Помилки:   {$stats['errors']}");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}
