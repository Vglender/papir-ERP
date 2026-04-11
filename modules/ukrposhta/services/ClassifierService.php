<?php
namespace Papir\Crm;

/**
 * Ukrposhta address classifier — lazy-populated DB cache.
 *
 * Strategy:
 *   searchCity(q)          — local prefix search; якщо <3 результати, b’ємо API
 *                            `get_city_by_region_id_and_district_id_and_city_ua` та кешуємо
 *   getPostoffices(cityId) — якщо порожньо → API `get_postoffices_by_city_id`, кешуємо все відділення
 *   searchStreet(cityId,q) — якщо мало/порожньо → API `get_streets_by_city_id_and_street_ua`, кешуємо
 *
 * Відповіді класифікатора йдуть у форматі {"Entries": {"Entry": [ ... ]}}.
 * Поле Entry може бути обʼєктом (якщо один запис) — нормалізуємо через toList().
 */
class ClassifierService
{
    /** @return UkrposhtaApi|null */
    private static function api()
    {
        $conn = \IntegrationSettingsService::getDefaultConnection('ukrposhta');
        if (!$conn) return null;
        $ecom = (string)$conn['api_key'];
        $user = isset($conn['metadata']['user_token']) ? (string)$conn['metadata']['user_token'] : '';
        if ($user === '') return null;
        return new UkrposhtaApi($ecom, $user);
    }

    private static function toList($data)
    {
        if (!is_array($data)) return array();
        if (isset($data['Entries']['Entry'])) {
            $e = $data['Entries']['Entry'];
            if (is_array($e) && isset($e[0])) return $e;
            if (is_array($e)) return array($e); // single record
            return array();
        }
        if (isset($data[0])) return $data;
        return array();
    }

    // ── Cities ───────────────────────────────────────────────────────────────

    public static function searchCity($query, $limit = 20)
    {
        $local = UpClassifierRepository::searchCities($query, $limit);
        if (count($local) >= 3) return $local;

        $api = self::api();
        if (!$api) return $local;

        $r = $api->classifier('GET', 'get_city_by_region_id_and_district_id_and_city_ua', null, array(
            'city_ua' => $query,
        ));
        if (!$r['ok']) return $local;

        $rows = array();
        foreach (self::toList($r['data']) as $e) {
            $cid = isset($e['CITY_ID']) ? (int)$e['CITY_ID'] : 0;
            if (!$cid) continue;
            $rows[] = array(
                'city_id'       => $cid,
                'city_name'     => isset($e['CITY_UA'])      ? $e['CITY_UA']      : '',
                'city_type_ua'  => isset($e['CITYTYPE_UA'])  ? $e['CITYTYPE_UA']  : null,
                'region_id'     => isset($e['REGION_ID'])    ? (int)$e['REGION_ID'] : null,
                'region_name'   => isset($e['REGION_UA'])    ? $e['REGION_UA']    : null,
                'district_id'   => isset($e['DISTRICT_ID'])  ? (int)$e['DISTRICT_ID'] : null,
                'district_name' => isset($e['DISTRICT_UA'])  ? $e['DISTRICT_UA']  : null,
                'postcode'      => isset($e['POSTCODE'])     ? $e['POSTCODE']     : null,
                'koatuu'        => isset($e['CITY_KOATUU'])  ? $e['CITY_KOATUU']  : null,
            );
        }
        if ($rows) UpClassifierRepository::upsertCities($rows);

        return UpClassifierRepository::searchCities($query, $limit);
    }

    // ── Postoffices ──────────────────────────────────────────────────────────

    public static function getPostoffices($cityId, $forceRefresh = false)
    {
        $cityId = (int)$cityId;
        if (!$cityId) return array();

        if (!$forceRefresh && UpClassifierRepository::countPostofficesByCity($cityId) > 0) {
            return UpClassifierRepository::getPostofficesByCity($cityId);
        }

        $api = self::api();
        if (!$api) return UpClassifierRepository::getPostofficesByCity($cityId);

        $r = $api->classifier('GET', 'get_postoffices_by_city_id', null, array('city_id' => $cityId));
        if (!$r['ok']) return UpClassifierRepository::getPostofficesByCity($cityId);

        $rows = array();
        foreach (self::toList($r['data']) as $e) {
            $pid = isset($e['POSTOFFICE_ID']) ? (int)$e['POSTOFFICE_ID'] : 0;
            if (!$pid) continue;
            $typeLong = isset($e['TYPE_LONG']) ? $e['TYPE_LONG'] : (isset($e['POSTOFFICE_TYPE']) ? $e['POSTOFFICE_TYPE'] : null);
            $rows[] = array(
                'postoffice_id' => $pid,
                'city_id'       => $cityId,
                'name'          => isset($e['POSTOFFICE_NAME']) ? $e['POSTOFFICE_NAME'] : (isset($e['POSTNAME']) ? $e['POSTNAME'] : ''),
                'long_name'     => isset($e['POSTOFFICE_LONGNAME']) ? $e['POSTOFFICE_LONGNAME'] : null,
                'type_long'     => $typeLong,
                'postindex'     => isset($e['POSTCODE']) ? $e['POSTCODE'] : (isset($e['POSTINDEX']) ? $e['POSTINDEX'] : null),
                'street_vpz'    => isset($e['STREET_UA_VPZ']) ? $e['STREET_UA_VPZ'] : null,
                'longitude'     => isset($e['LONGITUDE']) ? (float)$e['LONGITUDE'] : null,
                'latitude'      => isset($e['LATITUDE'])  ? (float)$e['LATITUDE']  : null,
                'is_automatic'  => !empty($e['IS_AUTOMATED']) || (isset($typeLong) && stripos($typeLong, 'автомат') !== false) ? 1 : 0,
            );
        }
        if ($rows) UpClassifierRepository::upsertPostoffices($rows);

        return UpClassifierRepository::getPostofficesByCity($cityId);
    }

    // ── Streets ──────────────────────────────────────────────────────────────

    public static function searchStreet($cityId, $query, $limit = 20)
    {
        $cityId = (int)$cityId;
        if (!$cityId || trim((string)$query) === '') return array();

        $local = UpClassifierRepository::searchStreets($cityId, $query, $limit);
        if (count($local) >= 3) return $local;

        $api = self::api();
        if (!$api) return $local;

        $r = $api->classifier('GET', 'get_street_by_region_id_and_district_id_and_city_id_and_street_ua', null, array(
            'city_id'   => $cityId,
            'street_ua' => $query,
        ));
        if (!$r['ok']) return $local;

        $rows = array();
        foreach (self::toList($r['data']) as $e) {
            $sid = isset($e['STREET_ID']) ? (int)$e['STREET_ID'] : 0;
            if (!$sid) continue;
            $rows[] = array(
                'street_id'   => $sid,
                'city_id'     => $cityId,
                'street_name' => isset($e['STREET_UA'])     ? $e['STREET_UA']     : '',
                'street_type' => isset($e['STREETTYPE_UA']) ? $e['STREETTYPE_UA'] : null,
            );
        }
        if ($rows) UpClassifierRepository::upsertStreets($rows);

        return UpClassifierRepository::searchStreets($cityId, $query, $limit);
    }
}
