<?php
namespace Papir\Crm;

/**
 * Local-first reference data: cities, warehouses, streets, areas.
 * Read from local tables; soft-upsert when new data arrives from NP API.
 */
class NpReferenceRepository
{
    // ── Cities ────────────────────────────────────────────────────────────────

    public static function searchCities($query, $limit = 20)
    {
        $query = trim($query);
        if ($query === '') return array();

        $tokens = preg_split('/\s+/u', mb_strtolower($query, 'UTF-8'));
        $tokens = array_filter($tokens, function($t) { return $t !== ''; });

        $parts = array();
        foreach ($tokens as $tok) {
            $e = \Database::escape('Papir', $tok);
            $parts[] = "(LOWER(Description) LIKE '%{$e}%' OR LOWER(DescriptionRu) LIKE '%{$e}%')";
        }
        $whereStr = implode(' AND ', $parts);

        $r = \Database::fetchAll('Papir',
            "SELECT Ref, Description, DescriptionRu, Area, SettlementTypeDescription, IsBranch
             FROM novaposhta_cities
             WHERE {$whereStr}
             ORDER BY
               CASE WHEN LOWER(Description) LIKE '" . \Database::escape('Papir', mb_strtolower($query, 'UTF-8')) . "%' THEN 0 ELSE 1 END,
               Description
             LIMIT " . (int)$limit);
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function getCityByRef($ref)
    {
        $e = \Database::escape('Papir', $ref);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM novaposhta_cities WHERE Ref = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function upsertCity($cityData)
    {
        $ref = isset($cityData['Ref']) ? $cityData['Ref'] : '';
        if (!$ref) return false;
        $data = array(
            'Description'                  => isset($cityData['Description'])                  ? $cityData['Description']                  : null,
            'DescriptionRu'                => isset($cityData['DescriptionRu'])                ? $cityData['DescriptionRu']                : null,
            'Ref'                          => $ref,
            'Area'                         => isset($cityData['Area'])                         ? $cityData['Area']                         : null,
            'SettlementType'               => isset($cityData['SettlementType'])               ? $cityData['SettlementType']               : null,
            'IsBranch'                     => isset($cityData['IsBranch'])                     ? $cityData['IsBranch']                     : null,
            'CityID'                       => isset($cityData['CityID'])                       ? $cityData['CityID']                       : null,
            'SettlementTypeDescriptionRu'  => isset($cityData['SettlementTypeDescriptionRu'])  ? $cityData['SettlementTypeDescriptionRu']  : null,
            'SettlementTypeDescription'    => isset($cityData['SettlementTypeDescription'])    ? $cityData['SettlementTypeDescription']    : null,
        );
        return \Database::upsertOne('Papir', 'novaposhta_cities', $data, array('Ref'));
    }

    // ── Warehouses ────────────────────────────────────────────────────────────

    public static function searchWarehouses($cityRef, $query = '', $limit = 30)
    {
        $ec = \Database::escape('Papir', $cityRef);
        $where = array("CityRef = '{$ec}'");

        if ($query !== '') {
            $tokens = preg_split('/\s+/u', mb_strtolower($query, 'UTF-8'));
            $tokens = array_filter($tokens, function($t) { return $t !== ''; });
            foreach ($tokens as $tok) {
                $e = \Database::escape('Papir', $tok);
                $where[] = "(LOWER(Description) LIKE '%{$e}%' OR LOWER(ShortAddress) LIKE '%{$e}%')";
            }
        }

        $r = \Database::fetchAll('Papir',
            "SELECT Ref, Description, ShortAddress, Number, TypeOfWarehouse,
                    TotalMaxWeightAllowed, CategoryOfWarehouse, PostomatFor
             FROM np_warehouses
             WHERE " . implode(' AND ', $where) . "
             ORDER BY Number
             LIMIT " . (int)$limit);
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function getWarehouseByRef($ref)
    {
        $e = \Database::escape('Papir', $ref);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM np_warehouses WHERE Ref = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function upsertWarehouse($wh)
    {
        $ref = isset($wh['Ref']) ? $wh['Ref'] : '';
        if (!$ref) return false;
        $data = array(
            'Ref'                   => $ref,
            'SiteKey'               => isset($wh['SiteKey'])               ? $wh['SiteKey']               : null,
            'Description'           => isset($wh['Description'])           ? $wh['Description']           : null,
            'DescriptionRu'         => isset($wh['DescriptionRu'])         ? $wh['DescriptionRu']         : null,
            'ShortAddress'          => isset($wh['ShortAddress'])          ? $wh['ShortAddress']          : null,
            'TypeOfWarehouse'       => isset($wh['TypeOfWarehouse'])       ? $wh['TypeOfWarehouse']       : null,
            'Number'                => isset($wh['Number'])                ? $wh['Number']                : null,
            'CityRef'               => isset($wh['CityRef'])               ? $wh['CityRef']               : null,
            'CityDescription'       => isset($wh['CityDescription'])       ? $wh['CityDescription']       : null,
            'SettlementRef'         => isset($wh['SettlementRef'])         ? $wh['SettlementRef']         : null,
            'PostalCodeUA'          => isset($wh['PostalCodeUA'])          ? $wh['PostalCodeUA']          : null,
            'TotalMaxWeightAllowed' => isset($wh['TotalMaxWeightAllowed']) ? $wh['TotalMaxWeightAllowed'] : null,
            'CategoryOfWarehouse'   => isset($wh['CategoryOfWarehouse'])   ? $wh['CategoryOfWarehouse']   : null,
            'WarehouseStatus'       => isset($wh['WarehouseStatus'])       ? $wh['WarehouseStatus']       : null,
            'PostomatFor'           => isset($wh['PostomatFor'])           ? $wh['PostomatFor']           : null,
        );
        return \Database::upsertOne('Papir', 'np_warehouses', $data, array('Ref'));
    }

    // ── Streets ───────────────────────────────────────────────────────────────

    public static function searchStreets($cityRef, $query, $limit = 20)
    {
        $ec = \Database::escape('Papir', $cityRef);
        $where = array("CityRef = '{$ec}'");

        if ($query !== '') {
            $tokens = preg_split('/\s+/u', mb_strtolower($query, 'UTF-8'));
            $tokens = array_filter($tokens, function($t) { return $t !== ''; });
            foreach ($tokens as $tok) {
                $e = \Database::escape('Papir', $tok);
                $where[] = "LOWER(Description) LIKE '%{$e}%'";
            }
        }

        $r = \Database::fetchAll('Papir',
            "SELECT Ref, Description, StreetsType, CityRef
             FROM street_np
             WHERE " . implode(' AND ', $where) . "
             ORDER BY Description
             LIMIT " . (int)$limit);
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function getStreetByRef($ref)
    {
        $e = \Database::escape('Papir', $ref);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM street_np WHERE Ref = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function upsertStreet($street)
    {
        $ref = isset($street['Ref']) ? $street['Ref'] : '';
        if (!$ref) return false;
        $data = array(
            'Ref'         => $ref,
            'Description' => isset($street['Description']) ? $street['Description'] : null,
            'CityRef'     => isset($street['CityRef'])     ? $street['CityRef']     : null,
            'StreetsType' => isset($street['StreetsType'])  ? $street['StreetsType'] : null,
        );
        return \Database::upsertOne('Papir', 'street_np', $data, array('Ref'));
    }
}