<?php
namespace Papir\Crm;

/**
 * Ukrposhta REST API client.
 *
 * Ukrposhta exposes 4 base URLs, each potentially needing a different token:
 *   ecom        — створення/редагування ТТН, агентів, адрес, реєстрів  → Authorization: Bearer {ecomToken}
 *                 + query param `token={userToken}` для методів, що вимагають авторизацію користувача
 *   forms       — друк наліпок та форм-103а                             → same auth as ecom
 *   tracking    — відстеження статусів                                  → Bearer {trackingToken}
 *   classifier  — довідник адрес/відділень                              → Bearer {userToken}
 *
 * Single API instance is shared across the module — tokens read from
 * integration_settings (set via the settings page).
 *
 * Usage:
 *   $api = UkrposhtaApi::getDefault();
 *   $res = $api->ecom('POST', 'shipments', $body);
 *   if ($res['ok']) { ... } else { error_log($res['error']); }
 */
class UkrposhtaApi
{
    const BASE_ECOM       = 'https://www.ukrposhta.ua/ecom/0.0.1/';
    const BASE_FORMS      = 'https://www.ukrposhta.ua/forms/ecom/0.0.1/';
    const BASE_TRACKING   = 'https://www.ukrposhta.ua/status-tracking/0.0.1/';
    const BASE_CLASSIFIER = 'https://www.ukrposhta.ua/address-classifier-ws/';
    const TIMEOUT         = 30;
    const LOG_FILE        = '/var/log/papir/up_api.log';

    /** @var string */
    private $ecomToken;
    /** @var string */
    private $userToken;
    /** @var string */
    private $trackingToken;

    public function __construct($ecomToken = '', $userToken = '', $trackingToken = '')
    {
        $this->ecomToken     = (string)$ecomToken;
        $this->userToken     = (string)$userToken;
        $this->trackingToken = (string)$trackingToken ?: (string)$ecomToken;
    }

    /**
     * Build a configured instance from integration_settings + default connection.
     * Returns null if no tokens are configured.
     */
    public static function getDefault()
    {
        $ecom = $user = $track = '';

        // Tokens may live in integration_connections (preferred — multi-account) or in integration_settings.
        $conn = \IntegrationSettingsService::getDefaultConnection('ukrposhta');
        if ($conn) {
            $ecom = (string)$conn['api_key'];
            $meta = isset($conn['metadata']) && is_array($conn['metadata']) ? $conn['metadata'] : array();
            $user = isset($meta['user_token'])     ? (string)$meta['user_token']     : '';
            $track = isset($meta['tracking_token']) ? (string)$meta['tracking_token'] : '';
        }

        // Fallback to integration_settings (legacy, also used for one-time seeding from old hardcoded values).
        if ($ecom === '')  $ecom  = \IntegrationSettingsService::get('ukrposhta', 'ecom_token', '');
        if ($user === '')  $user  = \IntegrationSettingsService::get('ukrposhta', 'user_token', '');
        if ($track === '') $track = \IntegrationSettingsService::get('ukrposhta', 'tracking_token', '');

        if ($ecom === '') return null;
        return new self($ecom, $user, $track);
    }

    public function hasUserToken()
    {
        return $this->userToken !== '';
    }

    // ── High-level helpers ──────────────────────────────────────────────────

    /**
     * POST /ecom/0.0.1/addresses  → create recipient address.
     * @param array $data postcode/street/houseNumber/apartmentNumber/cityId
     */
    public function createAddress($data)
    {
        return $this->ecom('POST', 'addresses', $data);
    }

    /**
     * POST /ecom/0.0.1/clients  → create (or get-or-create) recipient client.
     * @param array $data type/firstName/lastName/middleName/name/tin/edrpou/addressId/phoneNumber/email
     */
    public function createClient($data)
    {
        return $this->ecom('POST', 'clients', $data, array('token' => $this->userToken));
    }

    /**
     * GET /ecom/0.0.1/clients/phone?phoneNumber=...&countryISO3166=UA
     */
    public function findClientByPhone($phone)
    {
        return $this->ecom('GET', 'clients/phone', null, array(
            'token'          => $this->userToken,
            'countryISO3166' => 'UA',
            'phoneNumber'    => $phone,
        ));
    }

    /**
     * POST /ecom/0.0.1/shipments
     */
    public function createShipment($data)
    {
        return $this->ecom('POST', 'shipments', $data, array('token' => $this->userToken));
    }

    /**
     * PUT /ecom/0.0.1/shipments/barcode/{barcode}
     */
    public function updateShipment($barcode, $data)
    {
        return $this->ecom('PUT', 'shipments/barcode/' . rawurlencode($barcode), $data,
            array('token' => $this->userToken));
    }

    /**
     * DELETE /ecom/0.0.1/shipments/{uuid}
     */
    public function deleteShipment($uuid)
    {
        return $this->ecom('DELETE', 'shipments/' . rawurlencode($uuid), null,
            array('token' => $this->userToken));
    }

    /**
     * GET /ecom/0.0.1/shipments?senderUuid={uuid}
     */
    public function listShipmentsBySender($senderUuid)
    {
        return $this->ecom('GET', 'shipments', null, array(
            'token'      => $this->userToken,
            'senderUuid' => $senderUuid,
        ));
    }

    /**
     * POST /ecom/0.0.1/shipment-groups
     */
    public function createGroup($name, $clientUuid, $type = 'STANDARD')
    {
        return $this->ecom('POST', 'shipment-groups', array(
            'name'       => (string)$name,
            'clientUuid' => (string)$clientUuid,
            'type'       => (string)$type,
        ), array('token' => $this->userToken));
    }

    /**
     * POST /ecom/0.0.1/shipment-groups/{groupUuid}/shipments/{shipmentUuid}
     */
    public function addShipmentToGroup($groupUuid, $shipmentUuid)
    {
        return $this->ecom('POST',
            'shipment-groups/' . rawurlencode($groupUuid) . '/shipments/' . rawurlencode($shipmentUuid),
            null, array('token' => $this->userToken));
    }

    /**
     * DELETE /ecom/0.0.1/shipments/{shipmentUuid}/shipment-group
     */
    public function removeShipmentFromGroup($shipmentUuid)
    {
        return $this->ecom('DELETE',
            'shipments/' . rawurlencode($shipmentUuid) . '/shipment-group',
            null, array('token' => $this->userToken));
    }

    /**
     * GET /ecom/0.0.1/shipment-groups/{uuid}/shipments
     */
    public function getGroupShipments($groupUuid)
    {
        return $this->ecom('GET',
            'shipment-groups/' . rawurlencode($groupUuid) . '/shipments',
            null, array('token' => $this->userToken));
    }

    /**
     * GET /ecom/0.0.1/shipment-groups/clients/{clientUuid}
     */
    public function getGroupsByClient($clientUuid)
    {
        return $this->ecom('GET',
            'shipment-groups/clients/' . rawurlencode($clientUuid),
            null, array('token' => $this->userToken));
    }

    /**
     * GET /forms/ecom/0.0.1/shipments/{barcode}/sticker   → PDF
     */
    public function getSticker($barcode)
    {
        return $this->forms('GET', 'shipments/' . rawurlencode($barcode) . '/sticker', null, array(
            'token' => $this->userToken,
        ));
    }

    /**
     * GET /ecom/0.0.1/shipment-groups/{uuid}/form103a    → PDF
     */
    public function getGroupForm103a($groupUuid)
    {
        return $this->ecom('GET',
            'shipment-groups/' . rawurlencode($groupUuid) . '/form103a',
            null, array(
                'showSenderName'    => 'true',
                'size'              => 'SIZE_A4',
                'hideDeclaredPrice' => 'false',
                'token'             => $this->userToken,
            ), true);
    }

    /**
     * GET /status-tracking/0.0.1/statuses/last?barcode=...
     */
    public function trackOne($barcode)
    {
        return $this->tracking('GET', 'statuses/last', null, array('barcode' => $barcode));
    }

    /**
     * POST /status-tracking/0.0.1/statuses/last  (body = array of barcodes)
     */
    public function trackBatch(array $barcodes)
    {
        return $this->tracking('POST', 'statuses/last', array_values($barcodes));
    }

    // ── Low-level namespace methods ─────────────────────────────────────────

    public function ecom($method, $path, $body = null, $query = array(), $rawBinary = false)
    {
        return $this->request($method, self::BASE_ECOM . ltrim($path, '/'), $body, $query, $this->ecomToken, $rawBinary);
    }

    public function forms($method, $path, $body = null, $query = array())
    {
        return $this->request($method, self::BASE_FORMS . ltrim($path, '/'), $body, $query, $this->ecomToken, true);
    }

    public function tracking($method, $path, $body = null, $query = array())
    {
        return $this->request($method, self::BASE_TRACKING . ltrim($path, '/'), $body, $query, $this->trackingToken);
    }

    public function classifier($method, $path, $body = null, $query = array())
    {
        return $this->request($method, self::BASE_CLASSIFIER . ltrim($path, '/'), $body, $query, $this->userToken);
    }

    // ── Core HTTP ────────────────────────────────────────────────────────────

    private function request($method, $url, $body, $query, $token, $rawBinary = false)
    {
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $headers = array(
            'Authorization: Bearer ' . $token,
            'Accept: ' . ($rawBinary ? 'application/pdf' : 'application/json'),
        );

        $payload = null;
        if ($body !== null) {
            $payload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $errStr   = curl_error($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            $msg = 'cURL error ' . $errno . ': ' . $errStr;
            self::logError($method, $url, 0, $msg, $body);
            return array('ok' => false, 'http' => 0, 'error' => $msg, 'data' => array(), 'raw' => '');
        }

        if ($rawBinary) {
            $ok = ($code >= 200 && $code < 300);
            if (!$ok) self::logError($method, $url, $code, mb_substr((string)$response, 0, 300), $body);
            return array('ok' => $ok, 'http' => $code, 'data' => array(), 'raw' => $response, 'error' => $ok ? '' : ('HTTP ' . $code));
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) && $response !== '' && $response !== null) {
            self::logError($method, $url, $code, 'Invalid JSON: ' . mb_substr((string)$response, 0, 200), $body);
            return array('ok' => false, 'http' => $code, 'error' => 'Invalid JSON from Ukrposhta API', 'data' => array(), 'raw' => $response);
        }

        $ok = ($code >= 200 && $code < 300);
        if (!$ok) {
            $errMsg = 'HTTP ' . $code;
            if (is_array($decoded)) {
                if (isset($decoded['message']))   $errMsg .= ': ' . $decoded['message'];
                elseif (isset($decoded['error'])) $errMsg .= ': ' . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
                elseif (isset($decoded['errors'])) {
                    $errMsg .= ': ' . (is_string($decoded['errors']) ? $decoded['errors'] : json_encode($decoded['errors']));
                }
            }
            self::logError($method, $url, $code, $errMsg, $body);
            return array('ok' => false, 'http' => $code, 'error' => $errMsg, 'data' => is_array($decoded) ? $decoded : array(), 'raw' => $response);
        }

        return array('ok' => true, 'http' => $code, 'error' => '', 'data' => is_array($decoded) ? $decoded : array(), 'raw' => $response);
    }

    private static function logError($method, $url, $code, $msg, $body)
    {
        $dir = dirname(self::LOG_FILE);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $line = '[' . date('Y-m-d H:i:s') . '] ERROR ' . strtoupper($method) . ' ' . $url
              . ' | ' . $code . ' | ' . $msg;
        if ($body !== null) {
            $bodyLog = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
            $line .= ' | body: ' . mb_substr($bodyLog, 0, 500);
        }
        @error_log($line . PHP_EOL, 3, self::LOG_FILE);
    }
}