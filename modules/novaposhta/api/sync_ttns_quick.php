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

        // Collect scan sheet refs seen in this page to auto-create missing stubs
        $pageScanSheets = array(); // ssRef => ssNumber (may be empty)

        foreach ($docs as $doc) {
            $mapped = \Papir\Crm\NpDocumentMapper::map($doc, $senderRef);
            if (!$mapped) continue;
            $npRef = $mapped['ref'];

            // Collect scan sheet info for stub creation
            if (!empty($mapped['scan_sheet_ref'])) {
                $ssRef = $mapped['scan_sheet_ref'];
                if (!isset($pageScanSheets[$ssRef])) {
                    $ssNum = !empty($doc['ScanSheetNumber']) ? $doc['ScanSheetNumber'] : null;
                    $pageScanSheets[$ssRef] = $ssNum;
                }
            }

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
                        : (float)(isset($mapped['declared_value']) ? $mapped['declared_value'] : 0);
                    \Papir\Crm\TtnService::autoMatchOrder($ttnId, $mapped['recipients_phone'], $sum);
                }
            }
        }

        // Auto-create np_scan_sheets stubs for registries seen in NP but missing in our DB
        if (!empty($pageScanSheets)) {
            $ssRefs = array_keys($pageScanSheets);
            $ssInList = implode("','", array_map(function($ref) {
                return \Database::escape('Papir', $ref);
            }, $ssRefs));
            $rExistSs = \Database::fetchAll('Papir',
                "SELECT Ref FROM np_scan_sheets WHERE Ref IN ('{$ssInList}')");
            $existingSsRefs = array();
            if ($rExistSs['ok']) {
                foreach ($rExistSs['rows'] as $row) $existingSsRefs[$row['Ref']] = true;
            }
            foreach ($pageScanSheets as $ssRef => $ssNum) {
                if (isset($existingSsRefs[$ssRef])) continue;
                // Status 'unknown' stub — real Number/status will be set after syncList (↻ Синхр. on registries page)
                // Use 'closed' so this stub is never auto-selected as the "current open" registry
                \Papir\Crm\ScanSheetRepository::save(array(
                    'Ref'        => $ssRef,
                    'Number'     => $ssNum,
                    'DateTime'   => date('Y-m-d H:i:s'),
                    'Count'      => 0,
                    'sender_ref' => $senderRef,
                    'status'     => 'closed',
                ));
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