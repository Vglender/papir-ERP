<?php
namespace Papir\Crm;

/**
 * DB access for Ukrposhta address classifier cache.
 */
class UpClassifierRepository
{
    public static function searchCities($query, $limit = 20)
    {
        $q = trim((string)$query);
        if ($q === '') return array();
        $e = \Database::escape('Papir', $q);
        $lim = max(1, min(100, (int)$limit));
        $sql = "SELECT * FROM ukrposhta_cities
                WHERE city_name LIKE '{$e}%' OR city_name LIKE '% {$e}%'
                ORDER BY
                    CASE WHEN city_name LIKE '{$e}%' THEN 0 ELSE 1 END,
                    CHAR_LENGTH(city_name),
                    city_name
                LIMIT {$lim}";
        $r = \Database::fetchAll('Papir', $sql);
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function getCity($cityId)
    {
        $r = \Database::fetchRow('Papir', "SELECT * FROM ukrposhta_cities WHERE city_id = " . (int)$cityId . " LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function upsertCities(array $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['city_id'])) continue;
            \Database::upsertOne('Papir', 'ukrposhta_cities', $row, array('city_id'));
        }
    }

    public static function getPostofficesByCity($cityId)
    {
        $r = \Database::fetchAll('Papir',
            "SELECT * FROM ukrposhta_postoffices WHERE city_id = " . (int)$cityId . " ORDER BY name");
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function countPostofficesByCity($cityId)
    {
        $r = \Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM ukrposhta_postoffices WHERE city_id = " . (int)$cityId);
        return ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;
    }

    public static function upsertPostoffices(array $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['postoffice_id'])) continue;
            \Database::upsertOne('Papir', 'ukrposhta_postoffices', $row, array('postoffice_id'));
        }
    }

    public static function searchStreets($cityId, $query, $limit = 20)
    {
        $q = trim((string)$query);
        if ($q === '' || !$cityId) return array();
        $e = \Database::escape('Papir', $q);
        $lim = max(1, min(100, (int)$limit));
        $sql = "SELECT * FROM ukrposhta_streets
                WHERE city_id = " . (int)$cityId . "
                  AND (street_name LIKE '{$e}%' OR street_name LIKE '% {$e}%')
                ORDER BY CHAR_LENGTH(street_name), street_name
                LIMIT {$lim}";
        $r = \Database::fetchAll('Papir', $sql);
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function countStreetsByCity($cityId)
    {
        $r = \Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM ukrposhta_streets WHERE city_id = " . (int)$cityId);
        return ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;
    }

    public static function upsertStreets(array $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['street_id'])) continue;
            \Database::upsertOne('Papir', 'ukrposhta_streets', $row, array('street_id'));
        }
    }
}
