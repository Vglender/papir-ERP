<?php

class MoySkladApi
{
    protected $auth;
    protected $apiBaseUrlEntity;
    protected $apiBaseUrlReport;

    public function __construct(array $config = array())
    {
        if (!$config) {
            $config = require __DIR__ . '/storage/moysklad_auth.php';
        }

        $this->auth = $config['auth'];
        $this->apiBaseUrlEntity = $config['api_base_url_entity'];
        $this->apiBaseUrlReport = $config['api_base_url_report'];
    }

    public function getAuth()
    {
        return $this->auth;
    }

    public function getEntityBaseUrl()
    {
        return $this->apiBaseUrlEntity;
    }

    public function getReportBaseUrl()
    {
        return $this->apiBaseUrlReport;
    }

    /**
     * Аналог ms_query_send($link, $data, $type)
     */
    public function querySend($link, $data, $type)
    {
        usleep(66700);

        $sendData = json_encode($data);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_POST, 0);
        curl_setopt($curl, CURLOPT_USERPWD, $this->auth);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept-Encoding: gzip'
        ));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);

        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $sendData);
        }

        $out = curl_exec($curl);
        curl_close($curl);

        if (strpos($out, "\x1f\x8b\x08") === 0) {
            $uncompressedData = gzdecode($out);
            return json_decode($uncompressedData);
        } else {
            $json = json_decode($out, JSON_UNESCAPED_UNICODE);
            return $json;
        }
    }

    /**
     * Аналог ms_query($link, $type = NULL)
     */
    public function query($link, $type = null)
    {
        usleep(66700);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_POST, 0);

        if ($type) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);
        }

        curl_setopt($curl, CURLOPT_USERPWD, $this->auth);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept-Encoding: gzip'
        ));

        $out = curl_exec($curl);

        curl_close($curl);

        if (strpos($out, "\x1f\x8b\x08") === 0) {
            $uncompressedData = gzdecode($out);
            return json_decode($uncompressedData);
        } else {
            $json = json_decode($out);
            return $json;
        }
    }

    /**
     * Аналог ms_add_image($entityId, $filename, $imagePath)
     */
    public function addImage($entityId, $filename, $imagePath)
    {
        $baseUrl = "https://api.moysklad.ru/api/remap/1.2/entity/product/{$entityId}/images";
        $base64Image = base64_encode(file_get_contents($imagePath));

        $payload = json_encode(array(
            'filename' => $filename,
            'content' => $base64Image
        ));

        $headers = array(
            'Content-Type: application/json',
            'Accept-Encoding: gzip'
        );

        $tryPost = function($url, $auth, $headers, $payload) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            return array($httpCode, $response);
        };

        list($httpCode, $response) = $tryPost($baseUrl, $this->auth, $headers, $payload);

        if ($httpCode == 308) {
            if (preg_match('/Location:\s*(.+)/i', $response, $matches)) {
                $newUrl = trim($matches[1]);
                list($httpCode2, $response2) = $tryPost($newUrl, $this->auth, $headers, $payload);
                return $response2;
            } else {
                echo "Redirect received but no Location header found.\n";
            }
        } else {
            return $response;
        }
    }

    /**
     * Аналог ms_query_send_print_doc($link, $data, $type)
     */
    public function querySendPrintDoc($link, $data, $type)
    {
        $sendData = json_encode($data);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_POST, 0);
        curl_setopt($curl, CURLOPT_USERPWD, $this->auth);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept-Encoding: gzip'
        ));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $sendData);

        $out = curl_exec($curl);
        curl_close($curl);

        if (strpos($out, "\x1f\x8b\x08") === 0) {
            $uncompressedData = gzdecode($out);
            $dataTmp = explode("\n", $uncompressedData);
        } else {
            $json = json_decode($out);
            $dataTmp = explode("\n", $json);
        }

        $dataTmp = explode("\n", $out);
        $location = $dataTmp[8];
        $location = trim(str_replace('Location:', '', $location));

        return $location;
    }
	
	public function getMetadataAttributes($document)
		{
			return $this->query($this->entityUrl($document . '/metadata/attributes'));
		}
		
	public function getCustomEntity($id)
		{
			return $this->query($this->entityUrl('customentity/' . $id));
		}	

	
	public function entityUrl($entity, $id = null)
		{
			return $this->apiBaseUrlEntity . $entity . ($id ? '/' . $id : '');
		}
		
	public function buildMeta($entity, $id, $type = null)
	{
		return [
			'meta' => [
				'href' => $this->entityUrl($entity, $id),
				'metadataHref' => $this->entityUrl($entity, 'metadata'),
				'type' => $type ?: $entity,
				'mediaType' => 'application/json',
			]
		];
	}
	
}

    // --------------------WRAPERs-------------------------------
	
	$ms = new MoySkladApi();

	function ms_query_send($link, $data, $type)
	{
		global $ms;
		return $ms->querySend($link, $data, $type);
	}

	function ms_query($link, $type = null)
	{
		global $ms;
		return $ms->query($link, $type);
	}

	function ms_add_image($entityId, $filename, $imagePath)
	{
		global $ms;
		return $ms->addImage($entityId, $filename, $imagePath);
	}

	function ms_query_send_print_doc($link, $data, $type)
	{
		global $ms;
		return $ms->querySendPrintDoc($link, $data, $type);
	}