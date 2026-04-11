<?php
/**
 * GET /counterparties/api/find_dedup_for?id=123
 *
 * Знаходить потенційні дублікати для конкретного контрагента.
 * Шукає збіги по email, phone, phone_alt, okpo, inn.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$cpId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$cpId) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

// ── Step 1: Fetch target counterparty contact data ───────────────────────────

$cpR = Database::fetchRow('Papir',
    "SELECT c.id, c.name, c.type, c.telegram_chat_id,
            cp.phone  AS person_phone, cp.email  AS person_email, cp.phone_alt,
            cc.phone  AS company_phone, cc.email AS company_email,
            cc.okpo, cc.inn
     FROM counterparty c
     LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
     LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
     WHERE c.id = {$cpId}");

if (!$cpR['ok'] || empty($cpR['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Контрагент не знайдений'));
    exit;
}

$target = $cpR['row'];
$phone    = !empty($target['person_phone'])  ? $target['person_phone']  : $target['company_phone'];
$email    = !empty($target['person_email'])  ? $target['person_email']  : $target['company_email'];
$phoneAlt = !empty($target['phone_alt'])     ? $target['phone_alt']     : '';
$okpo     = !empty($target['okpo'])          ? trim($target['okpo'])    : '';
$inn      = !empty($target['inn'])           ? trim($target['inn'])     : '';

// Phone normalization expression for MySQL
$phoneNorm = "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, '+',''),'-',''),' ',''),'(',''),')',''),'.',''), 9)";

// ── Step 2: Search for matches ──────────────────────────────────────────────

$matchedIds = array();  // id => array of reasons

// -- Email match --
if (!empty($email)) {
    $emailLower = Database::escape('Papir', strtolower(trim($email)));
    $emailR = Database::fetchAll('Papir',
        "SELECT c.id
         FROM counterparty c
         LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
         LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
         WHERE c.status = 1 AND c.id != {$cpId}
           AND LOWER(TRIM(COALESCE(NULLIF(cp.email,''), NULLIF(cc.email,'')))) = '{$emailLower}'");
    if ($emailR['ok']) {
        foreach ($emailR['rows'] as $row) {
            $id = (int)$row['id'];
            if (!isset($matchedIds[$id])) $matchedIds[$id] = array();
            $matchedIds[$id][] = array('type' => 'email', 'val' => $email);
        }
    }
}

// -- Phone match --
if (!empty($phone)) {
    $phoneClean = preg_replace('/\D/', '', $phone);
    if (strlen($phoneClean) >= 7) {
        $phoneLast9 = substr($phoneClean, -9);
        $phoneLast9Esc = Database::escape('Papir', $phoneLast9);
        $phoneExpr = sprintf($phoneNorm, "COALESCE(NULLIF(cp.phone,''), NULLIF(cc.phone,''))");
        $phoneR = Database::fetchAll('Papir',
            "SELECT c.id
             FROM counterparty c
             LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             WHERE c.status = 1 AND c.id != {$cpId}
               AND COALESCE(NULLIF(cp.phone,''), NULLIF(cc.phone,'')) IS NOT NULL
               AND {$phoneExpr} = '{$phoneLast9Esc}'");
        if ($phoneR['ok']) {
            foreach ($phoneR['rows'] as $row) {
                $id = (int)$row['id'];
                if (!isset($matchedIds[$id])) $matchedIds[$id] = array();
                $matchedIds[$id][] = array('type' => 'phone', 'val' => $phone);
            }
        }

        // Also check against phone_alt of others
        $phoneAltExpr = sprintf($phoneNorm, "cp.phone_alt");
        $phoneAltR = Database::fetchAll('Papir',
            "SELECT c.id
             FROM counterparty c
             JOIN counterparty_person cp ON cp.counterparty_id = c.id
             WHERE c.status = 1 AND c.id != {$cpId}
               AND cp.phone_alt IS NOT NULL AND cp.phone_alt != ''
               AND {$phoneAltExpr} = '{$phoneLast9Esc}'");
        if ($phoneAltR['ok']) {
            foreach ($phoneAltR['rows'] as $row) {
                $id = (int)$row['id'];
                if (!isset($matchedIds[$id])) $matchedIds[$id] = array();
                $matchedIds[$id][] = array('type' => 'phone', 'val' => $phone);
            }
        }
    }
}

// -- Phone alt match --
if (!empty($phoneAlt)) {
    $phoneAltClean = preg_replace('/\D/', '', $phoneAlt);
    if (strlen($phoneAltClean) >= 7) {
        $altLast9 = substr($phoneAltClean, -9);
        $altLast9Esc = Database::escape('Papir', $altLast9);

        // Check against primary phones of others
        $phoneExpr = sprintf($phoneNorm, "COALESCE(NULLIF(cp.phone,''), NULLIF(cc.phone,''))");
        $altR = Database::fetchAll('Papir',
            "SELECT c.id
             FROM counterparty c
             LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             WHERE c.status = 1 AND c.id != {$cpId}
               AND COALESCE(NULLIF(cp.phone,''), NULLIF(cc.phone,'')) IS NOT NULL
               AND {$phoneExpr} = '{$altLast9Esc}'");
        if ($altR['ok']) {
            foreach ($altR['rows'] as $row) {
                $id = (int)$row['id'];
                if (!isset($matchedIds[$id])) $matchedIds[$id] = array();
                $matchedIds[$id][] = array('type' => 'phone', 'val' => $phoneAlt);
            }
        }

        // Check against phone_alt of others
        $phoneAltExpr2 = sprintf($phoneNorm, "cp.phone_alt");
        $altR2 = Database::fetchAll('Papir',
            "SELECT c.id
             FROM counterparty c
             JOIN counterparty_person cp ON cp.counterparty_id = c.id
             WHERE c.status = 1 AND c.id != {$cpId}
               AND cp.phone_alt IS NOT NULL AND cp.phone_alt != ''
               AND {$phoneAltExpr2} = '{$altLast9Esc}'");
        if ($altR2['ok']) {
            foreach ($altR2['rows'] as $row) {
                $id = (int)$row['id'];
                if (!isset($matchedIds[$id])) $matchedIds[$id] = array();
                $matchedIds[$id][] = array('type' => 'phone', 'val' => $phoneAlt);
            }
        }
    }
}

// -- OKPO match --
if (!empty($okpo) && strlen($okpo) >= 6) {
    $okpoEsc = Database::escape('Papir', $okpo);
    $okpoR = Database::fetchAll('Papir',
        "SELECT c.id
         FROM counterparty c
         JOIN counterparty_company cc ON cc.counterparty_id = c.id
         WHERE c.status = 1 AND c.id != {$cpId}
           AND TRIM(cc.okpo) = '{$okpoEsc}'");
    if ($okpoR['ok']) {
        foreach ($okpoR['rows'] as $row) {
            $id = (int)$row['id'];
            if (!isset($matchedIds[$id])) $matchedIds[$id] = array();
            $matchedIds[$id][] = array('type' => 'okpo', 'val' => $okpo);
        }
    }
}

// -- INN match --
if (!empty($inn) && strlen($inn) >= 8) {
    $innEsc = Database::escape('Papir', $inn);
    $innR = Database::fetchAll('Papir',
        "SELECT c.id
         FROM counterparty c
         JOIN counterparty_company cc ON cc.counterparty_id = c.id
         WHERE c.status = 1 AND c.id != {$cpId}
           AND TRIM(cc.inn) = '{$innEsc}'");
    if ($innR['ok']) {
        foreach ($innR['rows'] as $row) {
            $id = (int)$row['id'];
            if (!isset($matchedIds[$id])) $matchedIds[$id] = array();
            $matchedIds[$id][] = array('type' => 'okpo', 'val' => $inn);
        }
    }
}

// ── Step 3: No matches found ─────────────────────────────────────────────────

if (empty($matchedIds)) {
    echo json_encode(array('ok' => true, 'total' => 0, 'groups' => array()));
    exit;
}

// ── Step 4: Fetch member details ─────────────────────────────────────────────

$allIds = array_merge(array($cpId), array_keys($matchedIds));
$allIdsSql = implode(',', $allIds);

$detR = Database::fetchAll('Papir',
    "SELECT c.id, c.name, c.type,
            cp.phone  AS person_phone, cp.email  AS person_email,
            cc.phone  AS company_phone, cc.email AS company_email,
            cc.okpo,  cc.inn,
            c.telegram_chat_id
     FROM counterparty c
     LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
     LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
     WHERE c.id IN ({$allIdsSql})");

$cpById = array();
if ($detR['ok']) {
    foreach ($detR['rows'] as $row) $cpById[(int)$row['id']] = $row;
}

// Activity stats
$msgR = Database::fetchAll('Papir',
    "SELECT counterparty_id, COUNT(*) AS cnt FROM cp_messages
     WHERE counterparty_id IN ({$allIdsSql}) GROUP BY counterparty_id");
$msgCounts = array();
if ($msgR['ok']) foreach ($msgR['rows'] as $r) $msgCounts[(int)$r['counterparty_id']] = (int)$r['cnt'];

$ordR = Database::fetchAll('Papir',
    "SELECT counterparty_id, COUNT(*) AS cnt, MAX(moment) AS last_at
     FROM customerorder WHERE counterparty_id IN ({$allIdsSql}) AND deleted_at IS NULL
     GROUP BY counterparty_id");
$ordCounts = array(); $ordLast = array();
if ($ordR['ok']) {
    foreach ($ordR['rows'] as $r) {
        $ordCounts[(int)$r['counterparty_id']] = (int)$r['cnt'];
        $ordLast[(int)$r['counterparty_id']]   = $r['last_at'];
    }
}

// ── Step 5: Build output ─────────────────────────────────────────────────────

// Collect all match types and reasons
$matchTypes = array();
$reasons    = array();
$seen       = array();
foreach ($matchedIds as $id => $rList) {
    foreach ($rList as $r) {
        $matchTypes[$r['type']] = true;
        $key = $r['type'] . ':' . $r['val'];
        if (!isset($seen[$key])) {
            $reasons[] = $key;
            $seen[$key] = true;
        }
    }
}

// Build member list: target first, then matches sorted by activity
$buildMember = function($id) use ($cpById, $msgCounts, $ordCounts, $ordLast) {
    $cp    = isset($cpById[$id]) ? $cpById[$id] : array();
    $mPhone = !empty($cp['person_phone'])  ? $cp['person_phone']  : (isset($cp['company_phone'])  ? $cp['company_phone']  : '');
    $mEmail = !empty($cp['person_email'])  ? $cp['person_email']  : (isset($cp['company_email'])  ? $cp['company_email']  : '');

    return array(
        'id'            => $id,
        'name'          => isset($cp['name'])              ? $cp['name']              : '—',
        'type'          => isset($cp['type'])              ? $cp['type']              : '',
        'phone'         => $mPhone,
        'email'         => $mEmail,
        'okpo'          => isset($cp['okpo'])              ? $cp['okpo']              : '',
        'inn'           => isset($cp['inn'])               ? $cp['inn']               : '',
        'telegram'      => isset($cp['telegram_chat_id']) ? $cp['telegram_chat_id'] : '',
        'msg_count'     => isset($msgCounts[$id])   ? $msgCounts[$id]   : 0,
        'order_count'   => isset($ordCounts[$id])   ? $ordCounts[$id]   : 0,
        'last_order_at' => isset($ordLast[$id])     ? $ordLast[$id]     : null,
    );
};

$members = array();
$members[] = $buildMember($cpId); // target counterparty first

$otherMembers = array();
foreach (array_keys($matchedIds) as $id) {
    $otherMembers[] = $buildMember($id);
}
usort($otherMembers, function($a, $b) {
    if ($b['order_count'] !== $a['order_count']) return $b['order_count'] - $a['order_count'];
    return $b['msg_count'] - $a['msg_count'];
});
$members = array_merge($members, $otherMembers);

$allGroupIds = array_merge(array($cpId), array_keys($matchedIds));

$group = array(
    'ids'         => $allGroupIds,
    'match_types' => array_keys($matchTypes),
    'reasons'     => $reasons,
    'members'     => $members,
);

echo json_encode(array(
    'ok'     => true,
    'total'  => 1,
    'groups' => array($group),
));
