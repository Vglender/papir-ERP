<?php
/**
 * Backfill: map Prom.ua clients to Papir counterparties.
 *
 * For each Prom client (paginated):
 *   - Normalize phone(s)
 *   - Find counterparty by phone in counterparty_person.phone / phone_alt
 *   - If found and counterparty.site_customer_ids.prom != client.id → update
 *
 * Usage:
 *   php modules/prom/scripts/map_clients.php           # live
 *   php modules/prom/scripts/map_clients.php --dry-run
 *   php modules/prom/scripts/map_clients.php --limit=500
 */

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';
require_once __DIR__ . '/../PromApi.php';
require_once __DIR__ . '/../services/PromOrderImporter.php';

AppRegistry::guard('prom');

$argvList = isset($argv) && is_array($argv) ? $argv : array();
$dryRun   = in_array('--dry-run', $argvList);
$limit    = 0;
foreach ($argvList as $arg) {
    if (strpos($arg, '--limit=') === 0) $limit = (int)substr($arg, 8);
}

echo "=== Prom.ua client mapping ===" . PHP_EOL;
echo "Mode: " . ($dryRun ? "DRY RUN" : "LIVE") . PHP_EOL;
if ($limit > 0) echo "Limit: {$limit} clients" . PHP_EOL;
echo PHP_EOL;

$api      = new PromApi();
$importer = new PromOrderImporter();

$lastId    = null;
$page      = 0;
$pageSize  = 100;
$total     = 0;
$linked    = 0;
$alreadyOk = 0;
$noMatch   = 0;
$noPhone   = 0;
$conflicts = array();

while (true) {
    $page++;
    $params = array('limit' => $pageSize);
    if ($lastId !== null) $params['last_id'] = $lastId;

    $r = $api->getClients($params);
    if (empty($r['ok']) || empty($r['clients'])) {
        if (!empty($r['error'])) echo "API error on page {$page}: " . $r['error'] . PHP_EOL;
        break;
    }

    foreach ($r['clients'] as $client) {
        $total++;
        $clientId = (int)$client['id'];
        $lastId   = $clientId;

        $phones = isset($client['phones']) && is_array($client['phones']) ? $client['phones'] : array();
        if (empty($phones)) {
            $noPhone++;
            continue;
        }

        // Normalize and search by all phones
        $cpId = 0;
        foreach ($phones as $rawPhone) {
            $cpId = findCounterpartyByPhone($rawPhone);
            if ($cpId > 0) break;
        }

        if ($cpId === 0) {
            $noMatch++;
            continue;
        }

        // Check current state of site_customer_ids.prom
        $current = Database::fetchRow('Papir',
            "SELECT site_customer_ids FROM counterparty WHERE id = {$cpId}");
        $ids = !empty($current['row']['site_customer_ids'])
            ? json_decode($current['row']['site_customer_ids'], true) : array();
        if (!is_array($ids)) $ids = array();

        if (isset($ids['prom']) && (int)$ids['prom'] === $clientId) {
            $alreadyOk++;
            continue;
        }

        if (isset($ids['prom']) && (int)$ids['prom'] !== $clientId) {
            $conflicts[] = "cp#{$cpId}: prom=" . $ids['prom'] . " → " . $clientId;
        }

        if (!$dryRun) {
            $importer->linkPromClient($cpId, $clientId);
        }
        $linked++;

        if ($linked % 50 === 0) {
            echo "  ... linked {$linked} so far (page {$page})" . PHP_EOL;
        }
    }

    echo "Page {$page}: " . count($r['clients']) . " clients (last_id={$lastId}, total={$total})" . PHP_EOL;

    if ($limit > 0 && $total >= $limit) break;
    if (count($r['clients']) < $pageSize) break;

    usleep(150000);
}

echo PHP_EOL . "=== Result ===" . PHP_EOL;
echo "Processed:        {$total}" . PHP_EOL;
echo "Linked (new):     {$linked}" . ($dryRun ? " (dry run — not applied)" : "") . PHP_EOL;
echo "Already linked:   {$alreadyOk}" . PHP_EOL;
echo "No phone in Prom: {$noPhone}" . PHP_EOL;
echo "No match in Papir:{$noMatch}" . PHP_EOL;

if (!empty($conflicts)) {
    echo PHP_EOL . "Conflicts (overwritten): " . count($conflicts) . PHP_EOL;
    foreach (array_slice($conflicts, 0, 20) as $c) echo "  {$c}" . PHP_EOL;
}

// =====================================================================

function findCounterpartyByPhone($rawPhone)
{
    $digits = preg_replace('/[^0-9]/', '', (string)$rawPhone);
    if ($digits === '') return 0;
    if (strlen($digits) === 10 && $digits[0] === '0') {
        $digits = '38' . $digits;
    } elseif (strlen($digits) === 9) {
        $digits = '380' . $digits;
    }

    // Try both with and without + prefix
    $variants = array($digits, '+' . $digits);
    $vList = "'" . implode("','", array_map(function($v) {
        return Database::escape('Papir', $v);
    }, $variants)) . "'";

    $r = Database::fetchRow('Papir',
        "SELECT cp.id FROM counterparty cp
         JOIN counterparty_person cpp ON cpp.counterparty_id = cp.id
         WHERE (cpp.phone IN ({$vList}) OR cpp.phone_alt IN ({$vList}))
           AND cp.status = 1
         LIMIT 1");
    if (!empty($r['ok']) && !empty($r['row'])) {
        return (int)$r['row']['id'];
    }
    return 0;
}