<?php
/**
 * GET /counterparties/api/find_dedup_groups[?offset=0&limit=50]
 *
 * Знаходить групи потенційних дублікатів контрагентів.
 * SQL-агрегація по email / phone / okpo — не завантажує всі 100K записів.
 * Union-Find тільки по парах-дублікатах (~25K), не по всьому реєстру.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$limit  = isset($_GET['limit'])  ? min(200, max(1, (int)$_GET['limit'])) : 50;

// ── Step 1: Find duplicate sets via SQL ───────────────────────────────────────

// Email duplicates (person_email or company_email)
$emailR = Database::fetchAll('Papir',
    "SELECT GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',') AS ids,
            LOWER(TRIM(COALESCE(NULLIF(cp.email,''), NULLIF(cc.email,'')))) AS match_val
     FROM counterparty c
     LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
     LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
     WHERE c.status = 1
       AND COALESCE(NULLIF(cp.email,''), NULLIF(cc.email,'')) IS NOT NULL
     GROUP BY LOWER(TRIM(COALESCE(NULLIF(cp.email,''), NULLIF(cc.email,''))))
     HAVING COUNT(*) > 1");

// Phone duplicates — normalize via REPLACE (MySQL 5.7 compat, no REGEXP_REPLACE)
// Normalize: strip +,-, ,( ) then take last 9 chars
$phoneR = Database::fetchAll('Papir',
    "SELECT GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',') AS ids,
            RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
              COALESCE(NULLIF(cp.phone,''), NULLIF(cc.phone,'')),
            '+',''),'-',''),' ',''),'(',''),')',''),'.',''), 9) AS match_val
     FROM counterparty c
     LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
     LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
     WHERE c.status = 1
       AND COALESCE(NULLIF(cp.phone,''), NULLIF(cc.phone,'')) IS NOT NULL
       AND LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
             COALESCE(NULLIF(cp.phone,''), NULLIF(cc.phone,'')),
           '+',''),'-',''),' ',''),'(',''),')',''),'.','')) >= 7
     GROUP BY RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
              COALESCE(NULLIF(cp.phone,''), NULLIF(cc.phone,'')),
            '+',''),'-',''),' ',''),'(',''),')',''),'.',''), 9)
     HAVING COUNT(*) > 1");

// Also phone_alt
$phoneAltR = Database::fetchAll('Papir',
    "SELECT GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',') AS ids,
            RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cp.phone_alt,
            '+',''),'-',''),' ',''),'(',''),')',''),'.',''), 9) AS match_val
     FROM counterparty c
     JOIN counterparty_person cp ON cp.counterparty_id = c.id
     WHERE c.status = 1
       AND cp.phone_alt IS NOT NULL AND cp.phone_alt != ''
       AND LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cp.phone_alt,
           '+',''),'-',''),' ',''),'(',''),')',''),'.','')) >= 7
     GROUP BY RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cp.phone_alt,
              '+',''),'-',''),' ',''),'(',''),')',''),'.',''), 9)
     HAVING COUNT(*) > 1");

// OKPO / INN duplicates
$okpoR = Database::fetchAll('Papir',
    "SELECT GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',') AS ids,
            TRIM(cc.okpo) AS match_val, 'okpo' AS mtype
     FROM counterparty c
     JOIN counterparty_company cc ON cc.counterparty_id = c.id
     WHERE c.status = 1 AND cc.okpo IS NOT NULL AND cc.okpo != ''
       AND LENGTH(TRIM(cc.okpo)) >= 6
     GROUP BY TRIM(cc.okpo)
     HAVING COUNT(*) > 1
     UNION ALL
     SELECT GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',') AS ids,
            TRIM(cc.inn) AS match_val, 'inn' AS mtype
     FROM counterparty c
     JOIN counterparty_company cc ON cc.counterparty_id = c.id
     WHERE c.status = 1 AND cc.inn IS NOT NULL AND cc.inn != ''
       AND LENGTH(TRIM(cc.inn)) >= 8
     GROUP BY TRIM(cc.inn)
     HAVING COUNT(*) > 1");

// ── Step 2: Build Union-Find on duplicate pairs only ──────────────────────────

$parent  = array();
$ufRank  = array();

function _uf_find(&$p, $x)
{
    if ($p[$x] !== $x) $p[$x] = _uf_find($p, $p[$x]);
    return $p[$x];
}
function _uf_union(&$p, &$r, $x, $y)
{
    $rx = _uf_find($p, $x); $ry = _uf_find($p, $y);
    if ($rx === $ry) return;
    if ($r[$rx] < $r[$ry]) { $t = $rx; $rx = $ry; $ry = $t; }
    $p[$ry] = $rx;
    if ($r[$rx] === $r[$ry]) $r[$rx]++;
}
function _ensureNode(&$p, &$r, $id)
{
    if (!isset($p[$id])) { $p[$id] = $id; $r[$id] = 0; }
}

// pairReasons[minId_maxId] => [['type'=>..., 'val'=>...], ...]
$pairReasons = array();

$processSqlGroups = function($rows, $matchType) use (&$parent, &$ufRank, &$pairReasons) {
    if (!$rows['ok']) return;
    foreach ($rows['rows'] as $row) {
        if (!$row['match_val']) continue;
        $ids = array_map('intval', explode(',', $row['ids']));
        if (count($ids) < 2) continue;
        // Ensure all nodes exist
        foreach ($ids as $id) _ensureNode($parent, $ufRank, $id);
        // Union all with the first
        $first = $ids[0];
        for ($i = 1; $i < count($ids); $i++) {
            $pKey = min($first, $ids[$i]) . '_' . max($first, $ids[$i]);
            if (!isset($pairReasons[$pKey])) $pairReasons[$pKey] = array();
            $pairReasons[$pKey][] = array('type' => $matchType, 'val' => $row['match_val']);
            _uf_union($parent, $ufRank, $first, $ids[$i]);
        }
    }
};

$processSqlGroups($okpoR,     'okpo');   // most reliable
$processSqlGroups($emailR,    'email');
$processSqlGroups($phoneR,    'phone');
$processSqlGroups($phoneAltR, 'phone');

if (empty($parent)) {
    echo json_encode(array('ok' => true, 'total' => 0, 'offset' => $offset, 'limit' => $limit, 'groups' => array()));
    exit;
}

// ── Step 3: Collect groups (2+ members) ──────────────────────────────────────

$rawGroups = array();
foreach (array_keys($parent) as $id) {
    $root = _uf_find($parent, $id);
    if (!isset($rawGroups[$root])) $rawGroups[$root] = array();
    $rawGroups[$root][$id] = true;
}
$rawGroups = array_filter($rawGroups, function($ids) { return count($ids) >= 2; });

// Compute match_types per group and collect reasons
$groupMeta = array();
foreach ($rawGroups as $root => $idsMap) {
    $ids        = array_keys($idsMap);
    $reasons    = array();
    $matchTypes = array();
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $pKey = min($ids[$i], $ids[$j]) . '_' . max($ids[$i], $ids[$j]);
            if (isset($pairReasons[$pKey])) {
                foreach ($pairReasons[$pKey] as $r) {
                    $reasons[]        = $r;
                    $matchTypes[$r['type']] = true;
                }
            }
        }
    }
    // Deduplicate reasons
    $reasonsDedup = array();
    $seen = array();
    foreach ($reasons as $r) {
        $key = $r['type'] . ':' . $r['val'];
        if (!isset($seen[$key])) { $reasonsDedup[] = $r; $seen[$key] = true; }
    }
    $groupMeta[$root] = array(
        'ids'         => $ids,
        'match_types' => array_keys($matchTypes),
        'reasons'     => $reasonsDedup,
    );
}

// ── Step 4: Sort (okpo > email > phone) ──────────────────────────────────────

$scoreGroup = function($g) {
    if (in_array('okpo', $g['match_types'])) return 3;
    if (in_array('email', $g['match_types'])) return 2;
    return 1;
};
$groupList = array_values($groupMeta);
usort($groupList, function($a, $b) use ($scoreGroup) {
    return $scoreGroup($b) - $scoreGroup($a);
});

$total = count($groupList);

// ── Step 5: Paginate ──────────────────────────────────────────────────────────

$page   = array_slice($groupList, $offset, $limit);

if (empty($page)) {
    echo json_encode(array('ok' => true, 'total' => $total, 'offset' => $offset, 'limit' => $limit, 'groups' => array()));
    exit;
}

// ── Step 6: Fetch member details for this page only ───────────────────────────

$pageIds = array();
foreach ($page as $g) {
    foreach ($g['ids'] as $id) $pageIds[$id] = true;
}
$pageIdsSql = implode(',', array_keys($pageIds));

$cpR = Database::fetchAll('Papir',
    "SELECT c.id, c.name, c.type,
            cp.phone  AS person_phone, cp.email  AS person_email,
            cc.phone  AS company_phone, cc.email AS company_email,
            cc.okpo,  cc.inn,
            c.telegram_chat_id
     FROM counterparty c
     LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
     LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
     WHERE c.id IN ({$pageIdsSql})");

$cpById = array();
if ($cpR['ok']) {
    foreach ($cpR['rows'] as $row) $cpById[(int)$row['id']] = $row;
}

// ── Step 7: Fetch msg counts + order counts for page members ──────────────────

$msgR = Database::fetchAll('Papir',
    "SELECT counterparty_id, COUNT(*) AS cnt FROM cp_messages
     WHERE counterparty_id IN ({$pageIdsSql}) GROUP BY counterparty_id");
$msgCounts = array();
if ($msgR['ok']) foreach ($msgR['rows'] as $r) $msgCounts[(int)$r['counterparty_id']] = (int)$r['cnt'];

$ordR = Database::fetchAll('Papir',
    "SELECT counterparty_id, COUNT(*) AS cnt, MAX(moment) AS last_at
     FROM customerorder WHERE counterparty_id IN ({$pageIdsSql}) AND deleted_at IS NULL
     GROUP BY counterparty_id");
$ordCounts = array(); $ordLast = array();
if ($ordR['ok']) {
    foreach ($ordR['rows'] as $r) {
        $ordCounts[(int)$r['counterparty_id']] = (int)$r['cnt'];
        $ordLast[(int)$r['counterparty_id']]   = $r['last_at'];
    }
}

// ── Step 8: Build final output ────────────────────────────────────────────────

$output = array();
foreach ($page as $g) {
    $members = array();
    foreach ($g['ids'] as $id) {
        $cp    = isset($cpById[$id]) ? $cpById[$id] : array();
        $phone = !empty($cp['person_phone'])  ? $cp['person_phone']  : (isset($cp['company_phone'])  ? $cp['company_phone']  : '');
        $email = !empty($cp['person_email'])  ? $cp['person_email']  : (isset($cp['company_email'])  ? $cp['company_email']  : '');

        $members[] = array(
            'id'            => $id,
            'name'          => isset($cp['name'])          ? $cp['name']          : '—',
            'type'          => isset($cp['type'])          ? $cp['type']          : '',
            'phone'         => $phone,
            'email'         => $email,
            'okpo'          => isset($cp['okpo'])          ? $cp['okpo']          : '',
            'inn'           => isset($cp['inn'])           ? $cp['inn']           : '',
            'telegram'      => isset($cp['telegram_chat_id']) ? $cp['telegram_chat_id'] : '',
            'msg_count'     => isset($msgCounts[$id])   ? $msgCounts[$id]   : 0,
            'order_count'   => isset($ordCounts[$id])   ? $ordCounts[$id]   : 0,
            'last_order_at' => isset($ordLast[$id])     ? $ordLast[$id]     : null,
        );
    }

    // Sort members: highest activity first (default target suggestion)
    usort($members, function($a, $b) {
        if ($b['order_count'] !== $a['order_count']) return $b['order_count'] - $a['order_count'];
        return $b['msg_count'] - $a['msg_count'];
    });

    $output[] = array(
        'ids'         => $g['ids'],
        'match_types' => $g['match_types'],
        'reasons'     => array_map(function($r) { return $r['type'] . ':' . $r['val']; }, $g['reasons']),
        'members'     => $members,
    );
}

echo json_encode(array(
    'ok'     => true,
    'total'  => $total,
    'offset' => $offset,
    'limit'  => $limit,
    'groups' => $output,
));
