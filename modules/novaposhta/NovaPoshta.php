<?php
namespace Papir\Crm;

/**
 * Nova Poshta JSON-RPC API client.
 * Each sender has its own API key — pass it in the constructor.
 *
 * Usage:
 *   $np = new NovaPoshta($apiKey);
 *   $r  = $np->call('InternetDocument', 'save', array(...));
 *   if ($r['ok']) { $data = $r['data']; }
 *   else          { $error = $r['error']; }
 */
class NovaPoshta
{
    const BASE_URL = 'https://api.novaposhta.ua/v2.0/json/';
    const TIMEOUT  = 30;

    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Execute a JSON-RPC call.
     *
     * @param string $modelName        e.g. 'InternetDocument'
     * @param string $calledMethod     e.g. 'save'
     * @param array  $methodProperties request parameters
     * @return array ['ok'=>bool, 'data'=>array, 'error'=>string, 'warnings'=>array]
     */
    public function call($modelName, $calledMethod, $methodProperties = array())
    {
        $payload = json_encode(array(
            'apiKey'           => $this->apiKey,
            'modelName'        => $modelName,
            'calledMethod'     => $calledMethod,
            'language'         => 'uk',
            'methodProperties' => (object)$methodProperties,
        ));

        $ch = curl_init(self::BASE_URL);
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,        self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_ENCODING,       ''); // accept gzip

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $errStr   = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            return array('ok' => false, 'error' => 'cURL error ' . $errno . ': ' . $errStr, 'data' => array(), 'warnings' => array());
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return array('ok' => false, 'error' => 'Invalid JSON from NP API', 'data' => array(), 'warnings' => array());
        }

        $success  = !empty($decoded['success']);
        $data     = isset($decoded['data'])     ? (array)$decoded['data']     : array();
        $warnings = isset($decoded['warnings']) ? (array)$decoded['warnings'] : array();
        $errors   = isset($decoded['errors'])   ? (array)$decoded['errors']   : array();

        if (!$success) {
            $errMsg = !empty($errors) ? implode('; ', $errors) : 'Unknown NP API error';
            return array('ok' => false, 'error' => $errMsg, 'data' => $data, 'warnings' => $warnings);
        }

        return array('ok' => true, 'data' => $data, 'warnings' => $warnings, 'error' => '');
    }

    /**
     * Paginate through all pages of a method that supports Page/Limit.
     * Returns merged data array or error on first failure.
     *
     * @param string $modelName
     * @param string $calledMethod
     * @param array  $props        base properties (Page/Limit will be overwritten)
     * @param int    $pageSize
     * @return array ['ok'=>bool, 'data'=>array, 'error'=>string]
     */
    public function callAllPages($modelName, $calledMethod, $props = array(), $pageSize = 500)
    {
        $all  = array();
        $page = 1;

        do {
            $props['Page']  = $page;
            $props['Limit'] = $pageSize;
            $r = $this->call($modelName, $calledMethod, $props);
            if (!$r['ok']) {
                return $r;
            }
            $batch = $r['data'];
            if (empty($batch)) {
                break;
            }
            $all  = array_merge($all, $batch);
            $page++;
        } while (count($batch) >= $pageSize);

        return array('ok' => true, 'data' => $all, 'error' => '');
    }
}