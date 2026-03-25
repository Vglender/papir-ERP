<?php
/**
 * AttributeCascadeHelper
 *
 * Каскадирует изменения атрибутов из Papir на сайты (off / mff).
 * Все методы читают attribute_site_mapping и site_languages — жёсткого
 * хардкода сайтов нет, работает для любого количества сайтов в Papir.sites.
 */
class AttributeCascadeHelper {

    /**
     * Кэш: site_id → [ papir_lang_id => site_lang_id ]
     */
    private static $langMap = null;

    /**
     * Кэш: site_id → db_alias
     */
    private static $siteAliases = null;

    // ── Инициализация кэшей ────────────────────────────────────────────────

    private static function loadCaches() {
        if (self::$langMap !== null) return;

        self::$langMap     = array();
        self::$siteAliases = array();

        $sites = Database::fetchAll('Papir',
            "SELECT site_id, db_alias FROM sites WHERE status = 1"
        );
        if ($sites['ok']) {
            foreach ($sites['rows'] as $s) {
                self::$siteAliases[(int)$s['site_id']] = (string)$s['db_alias'];
            }
        }

        $langs = Database::fetchAll('Papir',
            "SELECT site_id, language_id, site_lang_id FROM site_languages"
        );
        if ($langs['ok']) {
            foreach ($langs['rows'] as $l) {
                $sid = (int)$l['site_id'];
                if (!isset(self::$langMap[$sid])) self::$langMap[$sid] = array();
                self::$langMap[$sid][(int)$l['language_id']] = (int)$l['site_lang_id'];
            }
        }
    }

    /**
     * Получить site_lang_id для пары (site_id, papir_language_id).
     * Возвращает 0 если маппинг не найден.
     */
    private static function siteLang($siteId, $papirLangId) {
        self::loadCaches();
        if (isset(self::$langMap[$siteId][$papirLangId])) {
            return self::$langMap[$siteId][$papirLangId];
        }
        return 0;
    }

    /**
     * db_alias сайта по site_id.
     */
    private static function dbAlias($siteId) {
        self::loadCaches();
        return isset(self::$siteAliases[$siteId]) ? self::$siteAliases[$siteId] : '';
    }

    /**
     * Маппинги атрибута: [ site_id => site_attribute_id ]
     */
    private static function getMappings($attributeId) {
        $r = Database::fetchAll('Papir',
            "SELECT site_id, site_attribute_id FROM attribute_site_mapping
             WHERE attribute_id = " . (int)$attributeId
        );
        $map = array();
        if ($r['ok']) {
            foreach ($r['rows'] as $row) {
                $map[(int)$row['site_id']] = (int)$row['site_attribute_id'];
            }
        }
        return $map;
    }

    // ── Публичный API ──────────────────────────────────────────────────────

    /**
     * Каскад: переименование атрибута.
     * Обновляет oc_attribute_description на всех сайтах где есть маппинг.
     *
     * @param int    $attributeId   Papir attribute_id
     * @param array  $names         [ papir_language_id => name_string ]
     */
    public static function cascadeAttributeName($attributeId, $names) {
        $mappings = self::getMappings($attributeId);
        foreach ($mappings as $siteId => $siteAttrId) {
            $db = self::dbAlias($siteId);
            if (!$db) continue;
            foreach ($names as $papirLangId => $name) {
                $siteLangId = self::siteLang($siteId, $papirLangId);
                if (!$siteLangId) continue;
                $nameEsc = Database::escape($db, $name);
                Database::query($db,
                    "UPDATE oc_attribute_description
                     SET name = '{$nameEsc}'
                     WHERE attribute_id = {$siteAttrId} AND language_id = {$siteLangId}"
                );
            }
        }
    }

    /**
     * Каскад: объединение атрибутов.
     * На каждом сайте: oc_product_attribute source → target (с дедупликацией),
     * затем удаляет source из oc_attribute + oc_attribute_description.
     *
     * @param int $sourceAttrId  Papir attribute_id источника
     * @param int $targetAttrId  Papir attribute_id цели
     */
    public static function cascadeMergeAttribute($sourceAttrId, $targetAttrId) {
        $srcMappings = self::getMappings($sourceAttrId);
        $tgtMappings = self::getMappings($targetAttrId);

        foreach ($srcMappings as $siteId => $srcSiteAttrId) {
            $db = self::dbAlias($siteId);
            if (!$db) continue;

            // Если у target нет маппинга на этом сайте — src маппинг уже
            // переедет в Papir через merge() в Repository, ничего не делаем.
            if (!isset($tgtMappings[$siteId])) continue;

            $tgtSiteAttrId = $tgtMappings[$siteId];

            // Удалить дубли (у которых target уже есть)
            Database::query($db,
                "DELETE FROM oc_product_attribute
                 WHERE attribute_id = {$srcSiteAttrId}
                   AND (product_id, language_id) IN (
                       SELECT product_id, language_id FROM (
                           SELECT product_id, language_id
                           FROM oc_product_attribute
                           WHERE attribute_id = {$tgtSiteAttrId}
                       ) AS has_target
                   )"
            );

            // Переназначить оставшиеся
            Database::query($db,
                "UPDATE oc_product_attribute
                 SET attribute_id = {$tgtSiteAttrId}
                 WHERE attribute_id = {$srcSiteAttrId}"
            );

            // Удалить сам атрибут с сайта
            Database::query($db,
                "DELETE FROM oc_attribute_description WHERE attribute_id = {$srcSiteAttrId}"
            );
            Database::query($db,
                "DELETE FROM oc_attribute WHERE attribute_id = {$srcSiteAttrId}"
            );
        }
    }

    /**
     * Каскад: переименование значения атрибута.
     *
     * @param int    $attributeId   Papir attribute_id
     * @param string $oldText
     * @param string $newText
     * @param int    $papirLangId   0 = все языки
     */
    public static function cascadeRenameValue($attributeId, $oldText, $newText, $papirLangId = 0) {
        $mappings = self::getMappings($attributeId);
        foreach ($mappings as $siteId => $siteAttrId) {
            $db = self::dbAlias($siteId);
            if (!$db) continue;

            $oldEsc = Database::escape($db, $oldText);
            $newEsc = Database::escape($db, $newText);
            $langSql = '';

            if ($papirLangId > 0) {
                $siteLangId = self::siteLang($siteId, $papirLangId);
                if ($siteLangId) {
                    $langSql = " AND language_id = {$siteLangId}";
                }
            }

            // Убрать дубли (товары у которых target уже есть)
            Database::query($db,
                "DELETE FROM oc_product_attribute
                 WHERE attribute_id = {$siteAttrId}{$langSql}
                   AND text = '{$oldEsc}'
                   AND (product_id, attribute_id, language_id) IN (
                       SELECT product_id, attribute_id, language_id FROM (
                           SELECT product_id, attribute_id, language_id
                           FROM oc_product_attribute
                           WHERE attribute_id = {$siteAttrId}{$langSql} AND text = '{$newEsc}'
                       ) AS has_new
                   )"
            );

            Database::query($db,
                "UPDATE oc_product_attribute
                 SET text = '{$newEsc}'
                 WHERE attribute_id = {$siteAttrId}{$langSql} AND text = '{$oldEsc}'"
            );
        }
    }

    /**
     * Каскад: объединение значений (source → target).
     * Идентично cascadeRenameValue — переносит oldText → newText.
     */
    public static function cascadeMergeValue($attributeId, $sourceText, $targetText, $papirLangId = 0) {
        self::cascadeRenameValue($attributeId, $sourceText, $targetText, $papirLangId);
    }
}
