<?php
class AttributeRepository {

    /**
     * Список атрибутов с фильтрацией и поиском.
     */
    public static function getList($search = '', $groupId = 0) {
        $where = 'WHERE 1=1';

        if ($groupId > 0) {
            $where .= ' AND pa.group_id = ' . (int)$groupId;
        }

        if ($search !== '') {
            $s = Database::escape('Papir', mb_strtolower(trim($search), 'UTF-8'));
            $where .= " AND (
                CAST(pa.attribute_id AS CHAR) LIKE '%{$s}%'
                OR LOWER(COALESCE(d_uk.attribute_name,'')) LIKE '%{$s}%'
                OR LOWER(COALESCE(d_ru.attribute_name,'')) LIKE '%{$s}%'
            )";
        }

        return Database::fetchAll('Papir',
            "SELECT
                pa.attribute_id,
                pa.group_id,
                pa.sort_order,
                pa.status,
                ag_uk.name  AS group_name_uk,
                ag_ru.name  AS group_name_ru,
                d_uk.attribute_name AS name_uk,
                d_ru.attribute_name AS name_ru,
                (SELECT COUNT(*) FROM product_attribute_value pav
                 WHERE pav.attribute_id = pa.attribute_id AND pav.site_id = 0) AS values_count,
                asm_off.site_attribute_id AS off_attr_id,
                asm_mff.site_attribute_id AS mff_attr_id
             FROM product_attribute pa
             LEFT JOIN attribute_group_description ag_uk ON ag_uk.group_id = pa.group_id AND ag_uk.language_id = 2
             LEFT JOIN attribute_group_description ag_ru ON ag_ru.group_id = pa.group_id AND ag_ru.language_id = 1
             LEFT JOIN product_attribute_description d_uk ON d_uk.attribute_id = pa.attribute_id AND d_uk.language_id = 2
             LEFT JOIN product_attribute_description d_ru ON d_ru.attribute_id = pa.attribute_id AND d_ru.language_id = 1
             LEFT JOIN attribute_site_mapping asm_off ON asm_off.attribute_id = pa.attribute_id AND asm_off.site_id = 1
             LEFT JOIN attribute_site_mapping asm_mff ON asm_mff.attribute_id = pa.attribute_id AND asm_mff.site_id = 2
             {$where}
             ORDER BY pa.group_id, COALESCE(d_uk.attribute_name, d_ru.attribute_name)"
        );
    }

    /**
     * Один атрибут по ID.
     */
    public static function getOne($attributeId) {
        $aid = (int)$attributeId;
        return Database::fetchRow('Papir',
            "SELECT
                pa.attribute_id, pa.group_id, pa.sort_order, pa.status,
                d_uk.attribute_name AS name_uk,
                d_ru.attribute_name AS name_ru,
                asm_off.site_attribute_id AS off_attr_id,
                asm_mff.site_attribute_id AS mff_attr_id,
                (SELECT COUNT(*) FROM product_attribute_value pav WHERE pav.attribute_id = {$aid}) AS values_count
             FROM product_attribute pa
             LEFT JOIN product_attribute_description d_uk ON d_uk.attribute_id = pa.attribute_id AND d_uk.language_id = 2
             LEFT JOIN product_attribute_description d_ru ON d_ru.attribute_id = pa.attribute_id AND d_ru.language_id = 1
             LEFT JOIN attribute_site_mapping asm_off ON asm_off.attribute_id = pa.attribute_id AND asm_off.site_id = 1
             LEFT JOIN attribute_site_mapping asm_mff ON asm_mff.attribute_id = pa.attribute_id AND asm_mff.site_id = 2
             WHERE pa.attribute_id = {$aid}"
        );
    }

    /**
     * Найти потенциальные дубли: атрибуты с похожим названием.
     */
    public static function findDuplicates($attributeId) {
        $aid = (int)$attributeId;

        // Получить имена текущего атрибута
        $cur = Database::fetchAll('Papir',
            "SELECT attribute_name FROM product_attribute_description WHERE attribute_id = {$aid}"
        );
        if (!$cur['ok'] || empty($cur['rows'])) return array();

        $conditions = array();
        foreach ($cur['rows'] as $row) {
            $name = trim((string)$row['attribute_name']);
            if ($name === '') continue;
            // Берём первые 5 токенов для нечёткого поиска
            $tokens = preg_split('/[\s,\/]+/u', mb_strtolower($name, 'UTF-8'));
            $tokens = array_filter(array_slice($tokens, 0, 3), function($t) { return mb_strlen($t, 'UTF-8') > 2; });
            foreach ($tokens as $token) {
                $t = Database::escape('Papir', $token);
                $conditions[] = "LOWER(COALESCE(d_uk.attribute_name,'')) LIKE '%{$t}%'";
                $conditions[] = "LOWER(COALESCE(d_ru.attribute_name,'')) LIKE '%{$t}%'";
            }
        }
        if (empty($conditions)) return array();

        $condSql = implode(' OR ', array_unique($conditions));

        $r = Database::fetchAll('Papir',
            "SELECT pa.attribute_id,
                    d_uk.attribute_name AS name_uk,
                    d_ru.attribute_name AS name_ru,
                    ag_uk.name AS group_name_uk,
                    (SELECT COUNT(*) FROM product_attribute_value pav WHERE pav.attribute_id = pa.attribute_id) AS values_count
             FROM product_attribute pa
             LEFT JOIN product_attribute_description d_uk ON d_uk.attribute_id = pa.attribute_id AND d_uk.language_id = 2
             LEFT JOIN product_attribute_description d_ru ON d_ru.attribute_id = pa.attribute_id AND d_ru.language_id = 1
             LEFT JOIN attribute_group_description ag_uk ON ag_uk.group_id = pa.group_id AND ag_uk.language_id = 2
             WHERE pa.attribute_id != {$aid} AND ({$condSql})
             ORDER BY pa.attribute_id
             LIMIT 20"
        );
        return $r['ok'] ? $r['rows'] : array();
    }

    /**
     * Объединить атрибут-источник в атрибут-цель.
     * Все значения товаров и маппинги переносятся на target, source удаляется.
     */
    public static function merge($sourceId, $targetId) {
        $src = (int)$sourceId;
        $tgt = (int)$targetId;
        if ($src === $tgt) return array('ok' => false, 'error' => 'same id');

        // Перенести значения товаров (INSERT IGNORE — target имеет приоритет)
        Database::query('Papir',
            "INSERT IGNORE INTO product_attribute_value (product_id, attribute_id, language_id, site_id, text, status)
             SELECT product_id, {$tgt}, language_id, site_id, text, status
             FROM product_attribute_value WHERE attribute_id = {$src}"
        );
        Database::query('Papir',
            "DELETE FROM product_attribute_value WHERE attribute_id = {$src}"
        );

        // Перенести маппинги на сайты (если у target ещё нет для этого site)
        Database::query('Papir',
            "INSERT IGNORE INTO attribute_site_mapping (attribute_id, site_id, site_attribute_id)
             SELECT {$tgt}, site_id, site_attribute_id
             FROM attribute_site_mapping WHERE attribute_id = {$src}"
        );
        Database::query('Papir',
            "DELETE FROM attribute_site_mapping WHERE attribute_id = {$src}"
        );

        // Каскад на сайты — до удаления, пока маппинги ещё есть
        AttributeCascadeHelper::cascadeMergeAttribute($src, $tgt);

        // Удалить описания и сам атрибут
        Database::query('Papir', "DELETE FROM product_attribute_description WHERE attribute_id = {$src}");
        Database::query('Papir', "DELETE FROM product_attribute WHERE attribute_id = {$src}");

        return array('ok' => true);
    }

    /**
     * Все группы.
     */
    public static function getGroups() {
        $r = Database::fetchAll('Papir',
            "SELECT ag.group_id, ag.sort_order, ag.status,
                    agd_uk.name AS name_uk, agd_ru.name AS name_ru,
                    COUNT(pa.attribute_id) AS attrs_count
             FROM attribute_group ag
             LEFT JOIN attribute_group_description agd_uk ON agd_uk.group_id = ag.group_id AND agd_uk.language_id = 2
             LEFT JOIN attribute_group_description agd_ru ON agd_ru.group_id = ag.group_id AND agd_ru.language_id = 1
             LEFT JOIN product_attribute pa ON pa.group_id = ag.group_id
             GROUP BY ag.group_id
             ORDER BY ag.sort_order, ag.group_id"
        );
        return $r['ok'] ? $r['rows'] : array();
    }
}
