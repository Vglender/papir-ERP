<?php
/**
 * Batch AI content generation for all mapped categories.
 *
 * Generates: description (HTML), meta_title, meta_description, seo_h1
 * For: each site × language combination (off×UK, off×RU, mff×UK, mff×RU)
 * Saves to: category_seo + category_description.description_full
 * Cascades to: oc_category_description in off / mff
 *
 * Does NOT overwrite: seo_url (already set), category names
 *
 * Usage:
 *   php scripts/generate_category_content.php
 *   php scripts/generate_category_content.php --offset=50 --limit=100
 *   php scripts/generate_category_content.php --dry-run
 *   php scripts/generate_category_content.php --cat=626
 */

require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/openai/openai_bootstrap.php';

// ── CLI args ──────────────────────────────────────────────────────────────────
$offset   = 0;
$limit    = PHP_INT_MAX;
$dryRun   = false;
$onlyCat  = 0;

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--offset=(\d+)$/', $arg, $m)) { $offset  = (int)$m[1]; }
    if (preg_match('/^--limit=(\d+)$/',  $arg, $m)) { $limit   = (int)$m[1]; }
    if (preg_match('/^--cat=(\d+)$/',    $arg, $m)) { $onlyCat = (int)$m[1]; }
    if ($arg === '--dry-run') { $dryRun = true; }
}

// ── Register in background_jobs ───────────────────────────────────────────────
$logFile = '/tmp/gen_cats.log';
$myPid   = getmypid();
if (!$dryRun) {
    $jobTitle = 'Генерація контенту категорій';
    if ($onlyCat > 0)  $jobTitle .= ' (cat=' . $onlyCat . ')';
    if ($offset > 0)   $jobTitle .= ' (offset=' . $offset . ')';
    Database::insert('Papir', 'background_jobs', array(
        'title'    => $jobTitle,
        'script'   => 'scripts/generate_category_content.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

// ── Site lang mapping (Papir language_id → site language_id) ─────────────────
$slR = Database::fetchAll('Papir', 'SELECT site_id, language_id, site_lang_id FROM site_languages');
$siteLangMap = array();
foreach ($slR['rows'] as $sl) {
    $siteLangMap[(int)$sl['site_id']][(int)$sl['language_id']] = (int)$sl['site_lang_id'];
}

// ── Languages ─────────────────────────────────────────────────────────────────
$languages = array(
    array('language_id' => 2, 'name' => 'Українська', 'code' => 'uk'),
    array('language_id' => 1, 'name' => 'Русский',    'code' => 'ru'),
);

// ── Load categories ───────────────────────────────────────────────────────────
$whereExtra = $onlyCat > 0 ? " AND c.category_id = {$onlyCat}" : '';

$sql = "
    SELECT c.category_id, c.category_off, c.category_mf,
           COALESCE(NULLIF(cd_uk.name,''), '') AS name_uk,
           COALESCE(NULLIF(cd_ru.name,''), '') AS name_ru,
           GROUP_CONCAT(DISTINCT csm.site_id ORDER BY csm.site_id) AS site_ids
    FROM categoria c
    LEFT JOIN category_description cd_uk ON cd_uk.category_id = c.category_id AND cd_uk.language_id = 2
    LEFT JOIN category_description cd_ru ON cd_ru.category_id = c.category_id AND cd_ru.language_id = 1
    JOIN category_site_mapping csm ON csm.category_id = c.category_id
    WHERE c.status = 1{$whereExtra}
    GROUP BY c.category_id
    ORDER BY c.category_id
    LIMIT {$limit} OFFSET {$offset}
";

$catsR = Database::fetchAll('Papir', $sql);
if (!$catsR['ok'] || empty($catsR['rows'])) {
    echo "No categories found.\n";
    exit(0);
}

$cats  = $catsR['rows'];
$total = count($cats);

echo str_repeat('=', 60) . "\n";
echo "Category AI generation\n";
echo "Categories: {$total} | offset: {$offset} | dry-run: " . ($dryRun ? 'YES' : 'no') . "\n";
echo str_repeat('=', 60) . "\n";

// ── Init OpenAI client ────────────────────────────────────────────────────────
$ai = $dryRun ? null : openai_client();

// ── Helpers ───────────────────────────────────────────────────────────────────
function esc($db, $v) { return Database::escape($db, $v); }

function callAi($ai, $siteId, $catId, $langId, $langName, $useCase) {
    $systemPrompt = \Papir\Crm\AiPromptBuilder::buildSystemPrompt(array(
        'site_id'       => $siteId,
        'entity_type'   => 'category',
        'category_id'   => $catId,
        'use_case'      => $useCase,
        'language_name' => $langName,
    ));
    $userPrompt = \Papir\Crm\AiPromptBuilder::buildCategoryUserPrompt(array(
        'category_id' => $catId,
        'site_id'     => $siteId,
        'language_id' => $langId,
    ));

    if (trim($userPrompt) === '') {
        return array('ok' => false, 'error' => 'empty user prompt');
    }

    $modelSettings = \Papir\Crm\AiPromptBuilder::getSiteModelSettings($siteId, $useCase);
    $result = $ai->chatWithSystem(
        $systemPrompt,
        $userPrompt,
        $modelSettings['model'],
        $modelSettings['max_tokens'],
        $modelSettings['temperature']
    );

    if (!$result['ok']) {
        return $result;
    }

    $text = trim($result['text']);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```\s*$/',        '', $text);
    $parsed = json_decode($text, true);

    if (!is_array($parsed)) {
        return array('ok' => false, 'error' => 'invalid JSON: ' . substr($text, 0, 80));
    }

    return array('ok' => true, 'fields' => $parsed);
}

function saveCategorySeo($catId, $siteId, $langId, $fields) {
    $desc   = isset($fields['description'])      ? trim($fields['description'])      : '';
    $mt     = isset($fields['meta_title'])       ? trim($fields['meta_title'])       : '';
    $md     = isset($fields['meta_description']) ? trim($fields['meta_description']) : '';
    $h1     = isset($fields['seo_h1'])           ? trim($fields['seo_h1'])           : '';

    // Upsert category_seo (do NOT overwrite seo_url)
    Database::query('Papir',
        "INSERT INTO category_seo
            (category_id, site_id, language_id, description, meta_title, meta_description, seo_h1)
         VALUES (
            {$catId}, {$siteId}, {$langId},
            '" . esc('Papir', $desc) . "',
            '" . esc('Papir', $mt)   . "',
            '" . esc('Papir', $md)   . "',
            '" . esc('Papir', $h1)   . "'
         )
         ON DUPLICATE KEY UPDATE
            description      = '" . esc('Papir', $desc) . "',
            meta_title       = '" . esc('Papir', $mt)   . "',
            meta_description = '" . esc('Papir', $md)   . "',
            seo_h1           = '" . esc('Papir', $h1)   . "'"
    );
}

function cascadeToSite($dbAlias, $siteCatId, $siteLangId, $fields, $isOff) {
    if ($siteCatId <= 0 || $siteLangId <= 0) return;

    $desc = isset($fields['description'])      ? trim($fields['description'])      : '';
    $mt   = isset($fields['meta_title'])       ? trim($fields['meta_title'])       : '';
    $md   = isset($fields['meta_description']) ? trim($fields['meta_description']) : '';
    $h1   = isset($fields['seo_h1'])           ? trim($fields['seo_h1'])           : '';

    $setH1 = $isOff ? ", meta_h1='" . esc($dbAlias, $h1) . "'" : '';

    Database::query($dbAlias,
        "UPDATE oc_category_description
         SET description='"     . esc($dbAlias, $desc) . "',
             meta_title='"      . esc($dbAlias, $mt)   . "',
             meta_description='" . esc($dbAlias, $md)  . "'
             {$setH1}
         WHERE category_id = {$siteCatId} AND language_id = {$siteLangId}"
    );
}

// ── Main loop ─────────────────────────────────────────────────────────────────
$done   = 0;
$errors = 0;
$timeStart = time();

foreach ($cats as $idx => $cat) {
    $catId    = (int)$cat['category_id'];
    $catOffId = (int)$cat['category_off'];
    $catMffId = (int)$cat['category_mf'];
    $siteIds  = array_map('intval', explode(',', $cat['site_ids']));
    $nameUk   = $cat['name_uk'];

    $num = $offset + $idx + 1;
    echo "\n[{$num}/{$total}] cat={$catId} \"{$nameUk}\" sites=[" . implode(',', $siteIds) . "]\n";

    // Per language: track if description_full already saved (site-independent master)
    $descFullSaved = array();

    foreach ($siteIds as $siteId) {
        $dbAlias   = ($siteId === 1) ? 'off' : 'mff';
        $siteCatId = ($siteId === 1) ? $catOffId : $catMffId;

        foreach ($languages as $lang) {
            $langId   = (int)$lang['language_id'];
            $langName = $lang['name'];
            $langCode = $lang['code'];

            $siteLangId = isset($siteLangMap[$siteId][$langId]) ? $siteLangMap[$siteId][$langId] : 0;

            if ($dryRun) {
                echo "  [DRY] site={$siteId} lang={$langId} ({$langCode}) → would generate\n";
                continue;
            }

            // Generate content
            $r = callAi($ai, $siteId, $catId, $langId, $langName, 'content');

            if (!$r['ok']) {
                echo "  ERR site={$siteId} lang={$langId}: " . $r['error'] . "\n";
                $errors++;
                usleep(500000);
                continue;
            }

            $fields = $r['fields'];

            // Save to category_seo
            saveCategorySeo($catId, $siteId, $langId, $fields);

            // Cascade to oc_category_description
            cascadeToSite($dbAlias, $siteCatId, $siteLangId, $fields, ($siteId === 1));

            // Save description_full to category_description (master, first site wins per lang)
            if (!isset($descFullSaved[$langId]) && !empty($fields['description'])) {
                Database::query('Papir',
                    "INSERT INTO category_description (category_id, language_id, description_full)
                     VALUES ({$catId}, {$langId}, '" . esc('Papir', trim($fields['description'])) . "')
                     ON DUPLICATE KEY UPDATE
                       description_full = '" . esc('Papir', trim($fields['description'])) . "'"
                );
                $descFullSaved[$langId] = true;
            }

            $mt = isset($fields['meta_title']) ? mb_substr($fields['meta_title'], 0, 50, 'UTF-8') : '';
            echo "  OK  site={$siteId} lang={$langId} ({$langCode}) mt=\"{$mt}\"\n";
            $done++;

            // Pause between API calls
            usleep(300000); // 300ms
        }
    }
}

$elapsed = time() - $timeStart;
echo "\n" . str_repeat('=', 60) . "\n";
echo "DONE: {$done} generated, {$errors} errors, {$elapsed}s elapsed\n";
echo str_repeat('=', 60) . "\n";

// Mark job as done
if (!$dryRun) {
    $finalStatus = ($errors === 0) ? 'done' : 'done';
    Database::query('Papir',
        "UPDATE background_jobs SET status='{$finalStatus}', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}
