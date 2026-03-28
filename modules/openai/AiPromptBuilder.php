<?php
namespace Papir\Crm;

use \Database;

/**
 * Збирає system prompt (пошарово) та user prompt (з даних товару/категорії).
 *
 * System prompt = технічна база (формат JSON + ліміти)
 *               + інструкція сайту
 *               + інструкція категорії  (якщо є)
 *               + інструкція товару     (якщо є)
 *
 * User prompt   = магазин + категорія-шлях + дані товару/категорії
 *               + атрибути + поточний опис + кастомна нотатка
 */
class AiPromptBuilder
{
    // ─── Конфігурація ────────────────────────────────────────────────────────

    /**
     * Повертає ввімкнені поля для сайту + типу сутності.
     *
     * @return array [ ['field_key'=>..., 'label'=>..., 'max_chars'=>...], ... ]
     */
    public static function getSiteFields($siteId, $entityType)
    {
        $et = Database::escape('Papir', $entityType);
        $r  = Database::fetchAll('Papir',
            "SELECT field_key, label, max_chars
             FROM ai_site_fields
             WHERE site_id = {$siteId} AND entity_type = '{$et}' AND is_enabled = 1
             ORDER BY sort_order ASC"
        );
        return ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();
    }

    /**
     * Повертає налаштування моделі (model, temperature, max_tokens)
     * із site-level запису ai_instructions.
     */
    public static function getSiteModelSettings($siteId, $useCase)
    {
        $uc = Database::escape('Papir', $useCase);
        $r  = Database::fetchRow('Papir',
            "SELECT context FROM ai_instructions
             WHERE entity_type = 'site' AND entity_id = {$siteId}
               AND site_id = {$siteId} AND use_case = '{$uc}'"
        );
        $defaults = array('model' => 'gpt-4o-mini', 'temperature' => 0.7, 'max_tokens' => 1200);
        if (!$r['ok'] || empty($r['row']['context'])) {
            return $defaults;
        }
        $ctx = json_decode($r['row']['context'], true);
        if (!is_array($ctx)) {
            return $defaults;
        }
        return array(
            'model'       => isset($ctx['model'])       ? $ctx['model']              : $defaults['model'],
            'temperature' => isset($ctx['temperature']) ? (float)$ctx['temperature'] : $defaults['temperature'],
            'max_tokens'  => isset($ctx['max_tokens'])  ? (int)$ctx['max_tokens']    : $defaults['max_tokens'],
        );
    }

    // ─── System prompt ────────────────────────────────────────────────────────

    /**
     * Отримує текст інструкції для сутності.
     * Пріоритет: site-specific → site_id=0 (fallback для всіх сайтів).
     */
    public static function getInstruction($entityType, $entityId, $siteId, $useCase)
    {
        $et  = Database::escape('Papir', $entityType);
        $uc  = Database::escape('Papir', $useCase);
        $id  = (int)$entityId;
        $sid = (int)$siteId;

        // Спробуємо site-specific
        $r = Database::fetchRow('Papir',
            "SELECT instruction FROM ai_instructions
             WHERE entity_type = '{$et}' AND entity_id = {$id}
               AND site_id = {$sid} AND use_case = '{$uc}'"
        );
        if ($r['ok'] && !empty($r['row']['instruction'])) {
            return trim((string)$r['row']['instruction']);
        }

        // Fallback: site_id=0 (universal)
        $r = Database::fetchRow('Papir',
            "SELECT instruction FROM ai_instructions
             WHERE entity_type = '{$et}' AND entity_id = {$id}
               AND site_id = 0 AND use_case = '{$uc}'"
        );
        if ($r['ok'] && !empty($r['row']['instruction'])) {
            return trim((string)$r['row']['instruction']);
        }

        return '';
    }

    /**
     * Будує технічну частину system prompt:
     * мова відповіді + JSON-схема з лімітами символів.
     */
    public static function buildTechnicalBase($siteId, $entityType, $languageName)
    {
        $fields = self::getSiteFields($siteId, $entityType);

        $jsonLines = array();
        foreach ($fields as $f) {
            $comment = $f['label'];
            if (!empty($f['max_chars'])) {
                $comment .= ', до ' . (int)$f['max_chars'] . ' символів';
            }
            $jsonLines[] = '  "' . $f['field_key'] . '": "..."  // ' . $comment;
        }

        $jsonBlock = "{\n" . implode(",\n", $jsonLines) . "\n}";

        return "Мова відповіді: {$languageName}.\n\n"
            . "Поверни відповідь ТІЛЬКИ у форматі JSON (без markdown, без коментарів, без пояснень):\n"
            . $jsonBlock;
    }

    /**
     * Збирає повний system prompt з усіх шарів.
     *
     * $params = array(
     *   'site_id'       => 1,
     *   'entity_type'   => 'product',   // 'product' | 'category'
     *   'category_id'   => 5,           // optional
     *   'product_id'    => 100,         // optional
     *   'use_case'      => 'content',
     *   'language_name' => 'Українська',
     * )
     */
    public static function buildSystemPrompt($params)
    {
        $siteId     = (int)$params['site_id'];
        $entityType = isset($params['entity_type'])   ? $params['entity_type']   : 'product';
        $categoryId = isset($params['category_id'])   ? (int)$params['category_id']  : 0;
        $productId  = isset($params['product_id'])    ? (int)$params['product_id']   : 0;
        $useCase    = isset($params['use_case'])      ? $params['use_case']      : 'content';
        $langName   = isset($params['language_name']) ? $params['language_name'] : 'Українська';

        $parts = array();

        // Шар 1: технічна база (формат + ліміти)
        $parts[] = self::buildTechnicalBase($siteId, $entityType, $langName);

        // Шар 1b: вимоги до форматування контенту (тільки для content)
        if ($useCase === 'content') {
            $parts[] = "Вимоги до тексту опису:\n"
                . "- Текст має бути SEO-оптимізованим: природно включати ключові слова, відповідати на пошукові запити покупців.\n"
                . "- По можливості підкреслювати переваги товару або категорії.\n"
                . "- Структурувати текст за допомогою HTML-тегів: <p>, <ul>, <li>, <strong>, <h2>, <h3>.\n"
                . "- Не використовувати символи Markdown (** * # тощо) — тільки HTML.\n"
                . "- Текст повинен читатися природно та бути корисним для покупця.";
        }

        // Шар 2: інструкція сайту
        $siteInstr = self::getInstruction('site', $siteId, $siteId, $useCase);
        if ($siteInstr !== '') {
            $parts[] = $siteInstr;
        }

        // Шар 3: інструкція категорії
        if ($categoryId > 0) {
            $catInstr = self::getInstruction('category', $categoryId, $siteId, $useCase);
            if ($catInstr !== '') {
                $parts[] = "Інструкція для категорії:\n" . $catInstr;
            }
        }

        // Шар 4: інструкція товару
        if ($productId > 0) {
            $prodInstr = self::getInstruction('product', $productId, $siteId, $useCase);
            if ($prodInstr !== '') {
                $parts[] = "Інструкція для товару:\n" . $prodInstr;
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    // ─── User prompt ──────────────────────────────────────────────────────────

    /**
     * Будує шлях категорії (хлібні крихти) через parent_id.
     * language_id — Papir language_id (1=ru, 2=uk).
     */
    private static function getCategoryPath($categoryId, $languageId)
    {
        if ($categoryId <= 0) {
            return '';
        }

        $names   = array();
        $current = (int)$categoryId;
        $visited = array();

        while ($current > 0 && !in_array($current, $visited)) {
            $visited[] = $current;
            $r = Database::fetchRow('Papir',
                "SELECT c.parent_id, COALESCE(cd.name, '') AS name
                 FROM categoria c
                 LEFT JOIN category_description cd
                   ON cd.category_id = c.category_id AND cd.language_id = {$languageId}
                 WHERE c.category_id = {$current}"
            );
            if (!$r['ok'] || empty($r['row'])) {
                break;
            }
            $name    = trim((string)$r['row']['name']);
            $current = (int)$r['row']['parent_id'];
            if ($name !== '') {
                array_unshift($names, $name);
            }
        }

        return implode(' / ', $names);
    }

    /**
     * Збирає user prompt для генерації контенту товару.
     *
     * $params = array(
     *   'product_id'  => 100,
     *   'site_id'     => 1,
     *   'language_id' => 2,    // Papir: 1=ru, 2=uk
     *   'category_id' => 5,    // Papir category_id (для хлібних крихт)
     *   'custom_note' => '',   // опціональна нотатка користувача
     * )
     */
    public static function buildProductUserPrompt($params)
    {
        $productId  = (int)$params['product_id'];
        $siteId     = (int)$params['site_id'];
        $languageId = (int)$params['language_id'];
        $categoryId = isset($params['category_id']) ? (int)$params['category_id'] : 0;
        $customNote = isset($params['custom_note']) ? trim((string)$params['custom_note']) : '';

        // Основна інформація про товар
        $r = Database::fetchRow('Papir',
            "SELECT pp.product_id, pp.product_article, pp.manufacturer_id,
                    COALESCE(NULLIF(pd.name,''), '') AS name,
                    COALESCE(NULLIF(pd.description,''), '') AS description
             FROM product_papir pp
             LEFT JOIN product_description pd
               ON pd.product_id = pp.product_id AND pd.language_id = {$languageId}
             WHERE pp.product_id = {$productId}"
        );

        if (!$r['ok'] || empty($r['row'])) {
            return '';
        }

        $row         = $r['row'];
        $productName = (string)$row['name'];
        $article     = (string)$row['product_article'];
        $currentDesc = trim(strip_tags((string)$row['description']));

        // Сайт
        $siteR    = Database::fetchRow('Papir', "SELECT name, url FROM sites WHERE site_id = {$siteId}");
        $siteName = ($siteR['ok'] && !empty($siteR['row'])) ? (string)$siteR['row']['name'] : '';
        $siteUrl  = ($siteR['ok'] && !empty($siteR['row'])) ? (string)$siteR['row']['url']  : '';

        // Виробник
        $mfrName = '';
        if (!empty($row['manufacturer_id'])) {
            $mfrR = Database::fetchRow('Papir',
                "SELECT name FROM manufacturers WHERE manufacturer_id = " . (int)$row['manufacturer_id']
            );
            if ($mfrR['ok'] && !empty($mfrR['row'])) {
                $mfrName = (string)$mfrR['row']['name'];
            }
        }

        // Шлях категорії
        $categoryPath = $categoryId > 0 ? self::getCategoryPath($categoryId, $languageId) : '';

        // Атрибути
        $attrsR = Database::fetchAll('Papir',
            "SELECT pad.attribute_name, pav.text
             FROM product_attribute_value pav
             JOIN product_attribute_description pad
               ON pad.attribute_id = pav.attribute_id AND pad.language_id = {$languageId}
             WHERE pav.product_id = {$productId}
               AND pav.site_id = 0
               AND pav.language_id = {$languageId}
               AND pav.text != ''
             ORDER BY pad.attribute_name ASC"
        );
        $attrs = ($attrsR['ok'] && !empty($attrsR['rows'])) ? $attrsR['rows'] : array();

        // Формуємо текст
        $lines = array();

        if ($siteName) {
            $lines[] = "Магазин: {$siteName} ({$siteUrl})";
        }
        if ($categoryPath) {
            $lines[] = "Категорія: {$categoryPath}";
        }

        $lines[] = '';
        $lines[] = 'Товар:';
        $lines[] = "  Назва: {$productName}";
        if ($article) {
            $lines[] = "  Артикул: {$article}";
        }
        if ($mfrName) {
            $lines[] = "  Виробник: {$mfrName}";
        }

        if (!empty($attrs)) {
            $lines[] = '';
            $lines[] = 'Характеристики:';
            foreach ($attrs as $a) {
                $lines[] = '  ' . $a['attribute_name'] . ': ' . $a['text'];
            }
        }

        if ($currentDesc !== '') {
            $lines[] = '';
            $lines[] = 'Поточний опис (для довідки):';
            $lines[] = mb_substr($currentDesc, 0, 600, 'UTF-8');
        }

        if ($customNote !== '') {
            $lines[] = '';
            $lines[] = 'Додаткові вказівки: ' . $customNote;
        }

        return implode("\n", $lines);
    }

    /**
     * Збирає user prompt для генерації контенту категорії.
     *
     * $params = array(
     *   'category_id' => 5,
     *   'site_id'     => 1,
     *   'language_id' => 2,
     *   'custom_note' => '',
     * )
     */
    public static function buildCategoryUserPrompt($params)
    {
        $categoryId = (int)$params['category_id'];
        $siteId     = (int)$params['site_id'];
        $languageId = (int)$params['language_id'];
        $customNote = isset($params['custom_note']) ? trim((string)$params['custom_note']) : '';

        $r = Database::fetchRow('Papir',
            "SELECT cd.name, cd.description_full, c.parent_id
             FROM categoria c
             LEFT JOIN category_description cd
               ON cd.category_id = c.category_id AND cd.language_id = {$languageId}
             WHERE c.category_id = {$categoryId}"
        );

        if (!$r['ok'] || empty($r['row'])) {
            return '';
        }

        $row      = $r['row'];
        $catName  = (string)$row['name'];
        $catDesc  = trim(strip_tags((string)$row['description_full']));
        $parentId = (int)$row['parent_id'];

        $parentPath = $parentId > 0 ? self::getCategoryPath($parentId, $languageId) : '';

        $siteR    = Database::fetchRow('Papir', "SELECT name, url FROM sites WHERE site_id = {$siteId}");
        $siteName = ($siteR['ok'] && !empty($siteR['row'])) ? (string)$siteR['row']['name'] : '';
        $siteUrl  = ($siteR['ok'] && !empty($siteR['row'])) ? (string)$siteR['row']['url']  : '';

        $lines = array();

        if ($siteName) {
            $lines[] = "Магазин: {$siteName} ({$siteUrl})";
        }
        if ($parentPath) {
            $lines[] = "Батьківський розділ: {$parentPath}";
        }

        $lines[] = '';
        $lines[] = "Категорія: {$catName}";

        if ($catDesc !== '') {
            $lines[] = '';
            $lines[] = 'Поточний опис (для довідки):';
            $lines[] = mb_substr($catDesc, 0, 600, 'UTF-8');
        }

        if ($customNote !== '') {
            $lines[] = '';
            $lines[] = 'Додаткові вказівки: ' . $customNote;
        }

        return implode("\n", $lines);
    }
}
