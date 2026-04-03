<?php
/**
 * POST /novaposhta/api/sync_ttns_quick
 * Quick sync of recent TTNs from NP API (last N hours).
 * Used by the "Sync" button on /novaposhta/ttns.
 * Runs inline (not background) — fast because date window is small.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$hours     = isset($_POST['hours']) ? min((int)$_POST['hours'], 240) : 26;
$dateRange = \Papir\Crm\NpDocumentMapper::buildDateRange(null, $hours);

$senders = \Papir\Crm\SenderRepository::getAll();
if (empty($senders)) {
    echo json_encode(array('ok' => false, 'error' => 'Відправників не знайдено'));
    exit;
}

$totalInserted = 0;
$totalUpdated  = 0;
$errors        = array();

foreach ($senders as $sender) {
    $senderRef = $sender['Ref'];
    $np        = new \Papir\Crm\NovaPoshta($sender['api']);
    $page      = 1;
    $pageSize  = 500;

    do {
        $r = $np->call('InternetDocument', 'getDocumentList', array(
            'DateTimeFrom' => $dateRange['DateTimeFrom'],
            'DateTimeTo'   => $dateRange['DateTimeTo'],
            'Page'         => $page,
            'Limit'        => $pageSize,
        ));

        if (!$r['ok']) {
            $errors[] = $sender['Description'] . ': ' . $r['error'];
            break;
        }

        $docs = $r['data'];
        if (empty($docs)) break;

        // Batch-check existing refs
        $pageRefs = array();
        foreach ($docs as $doc) {
            if (!empty($doc['Ref'])) $pageRefs[] = $doc['Ref'];
        }

        $existingMap = array();
        if (!empty($pageRefs)) {
            $inList = implode("','", array_map(function ($ref) {
                return \Database::escape('Papir', $ref);
            }, $pageRefs));
            $rExist = \Database::fetchAll('Papir',
                "SELECT id, ref, customerorder_id, demand_id, scan_sheet_ref, deletion_mark
                 FROM ttn_novaposhta WHERE ref IN ('{$inList}')");
            if ($rExist['ok']) {
                foreach ($rExist['rows'] as $row) $existingMap[$row['ref']] = $row;
            }
        }

        foreach ($docs as $doc) {
            $mapped = \Papir\Crm\NpDocumentMapper::map($doc, $senderRef);
            if (!$mapped) continue;
            $npRef = $mapped['ref'];

            if (isset($existingMap[$npRef])) {
                $existing = $existingMap[$npRef];
                if ($mapped['deletion_mark'] && !$existing['deletion_mark']) {
                    \Database::update('Papir', 'ttn_novaposhta',
                        array('deletion_mark' => 1, 'updated_at' => date('Y-m-d H:i:s')),
                        array('id' => (int)$existing['id']));
                    continue;
                }
                if ($existing['deletion_mark']) continue;
                $upd = \Papir\Crm\NpDocumentMapper::updateFields($mapped, $existing['scan_sheet_ref']);
                \Database::update('Papir', 'ttn_novaposhta', $upd, array('id' => (int)$existing['id']));
                $totalUpdated++;
            } else {
                $rIns = \Database::insert('Papir', 'ttn_novaposhta', $mapped);
                $totalInserted++;

                // Спроба авто-матчингу нової ТТН до заказу
                if ($rIns['ok'] && !empty($mapped['recipients_phone'])) {
                    $ttnId = (int)$rIns['insert_id'];
                    $sum = (!empty($mapped['backward_delivery_money']) && $mapped['backward_delivery_money'] > 0)
                        ? (float)$mapped['backward_delivery_money']
                        : (float)(isset($mapped['cost']) ? $mapped['cost'] : 0);
                    \Papir\Crm\TtnService::autoMatchOrder($ttnId, $mapped['recipients_phone'], $sum);
                }
            }
        }

        $page++;
        if (count($docs) < $pageSize) break;

    } while (true);
}

echo json_encode(array(
    'ok'       => true,
    'inserted' => $totalInserted,
    'updated'  => $totalUpdated,
    'hours'    => $hours,
    'errors'   => $errors,
));