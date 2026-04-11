<?php
/**
 * Prom.ua Public API client.
 *
 * Base URL is hardcoded (never changes).
 * Auth token is read from IntegrationSettingsService.
 *
 * API docs: https://public-api.docs.prom.ua/
 */

require_once __DIR__ . '/../integrations/IntegrationSettingsService.php';

class PromApi
{
    /** @var string  Base URL — never changes, hardcoded per CLAUDE.md rules */
    const BASE_URL = 'https://my.prom.ua/api/v1';

    /** @var string */
    private $token;

    /**
     * @param string|null $token  Override token (for testing). If null — reads from DB.
     */
    public function __construct($token = null)
    {
        if ($token !== null) {
            $this->token = $token;
        } else {
            $this->token = IntegrationSettingsService::get('prom', 'auth_token', '');
        }
    }

    // ── Orders ───────────────────────────────────────────────────────────────

    /**
     * GET /orders/list
     *
     * @param array $params  Optional: status, date_from, date_to,
     *                       last_modified_from, last_modified_to,
     *                       limit, last_id, sort_dir (asc|desc)
     * @return array
     */
    public function getOrders($params = array())
    {
        return $this->get('/orders/list', $params);
    }

    /**
     * GET /orders/{id}
     *
     * @param int $id
     * @return array
     */
    public function getOrder($id)
    {
        return $this->get('/orders/' . (int) $id);
    }

    /**
     * POST /orders/set_status
     *
     * @param array $ids              Order IDs
     * @param string $status          received|delivered|canceled|paid|waiting_loan_approval|loan_rejected|loan_approved
     * @param string|null $cancelReason  not_available|buyers_request|duplicate|invalid_phone_number|another|payment_not_received|no_product_variants
     * @param string|null $cancelText
     * @param int|null $customStatusId
     * @return array
     */
    public function setOrderStatus($ids, $status, $cancelReason = null, $cancelText = null, $customStatusId = null)
    {
        $body = array('ids' => $ids, 'status' => $status);
        if ($cancelReason !== null) $body['cancellation_reason'] = $cancelReason;
        if ($cancelText !== null)   $body['cancellation_text']   = $cancelText;
        if ($customStatusId !== null) {
            $body['custom_status_id'] = (int) $customStatusId;
            unset($body['status']);
        }
        return $this->post('/orders/set_status', $body);
    }

    /**
     * POST /orders/refund
     *
     * @param array $body
     * @return array
     */
    public function refundOrder($body)
    {
        return $this->post('/orders/refund', $body);
    }

    // ── Products ─────────────────────────────────────────────────────────────

    /**
     * GET /products/list
     *
     * @param array $params  Optional: group_id, limit, last_id,
     *                       last_modified_from, last_modified_to
     * @return array
     */
    public function getProducts($params = array())
    {
        return $this->get('/products/list', $params);
    }

    /**
     * GET /products/{id}
     *
     * @param int $id
     * @return array
     */
    public function getProduct($id)
    {
        return $this->get('/products/' . (int) $id);
    }

    /**
     * GET /products/by_external_id/{id}
     *
     * @param string $externalId
     * @return array
     */
    public function getProductByExternalId($externalId)
    {
        return $this->get('/products/by_external_id/' . urlencode($externalId));
    }

    /**
     * POST /products/edit
     *
     * Editable fields: id (required), name, description, keywords,
     * price, oldprice, discount{value,type,date_start,date_end},
     * prices[], presence (available|not_available|order),
     * in_stock (bool), quantity_in_stock, status (on_display|draft|deleted|not_on_display)
     *
     * @param int   $id
     * @param array $fields  Key-value pairs of fields to update
     * @return array
     */
    public function editProduct($id, $fields)
    {
        $fields['id'] = (int) $id;
        return $this->post('/products/edit', $fields);
    }

    /**
     * POST /products/edit_by_external_id
     *
     * @param string $externalId
     * @param array  $fields
     * @return array
     */
    public function editProductByExternalId($externalId, $fields)
    {
        $fields['external_id'] = $externalId;
        return $this->post('/products/edit_by_external_id', $fields);
    }

    /**
     * POST /products/import_url
     *
     * @param string $url  URL to import file
     * @return array
     */
    public function importProductsUrl($url)
    {
        return $this->post('/products/import_url', array('url' => $url));
    }

    /**
     * GET /products/import/status/{id}
     *
     * @param int $id  Import job ID
     * @return array
     */
    public function getImportStatus($id)
    {
        return $this->get('/products/import/status/' . (int) $id);
    }

    // ── Clients ──────────────────────────────────────────────────────────────

    /**
     * GET /clients/list
     *
     * @param array $params
     * @return array
     */
    public function getClients($params = array())
    {
        return $this->get('/clients/list', $params);
    }

    /**
     * GET /clients/{id}
     *
     * @param int $id
     * @return array
     */
    public function getClient($id)
    {
        return $this->get('/clients/' . (int) $id);
    }

    // ── Messages ─────────────────────────────────────────────────────────────

    /**
     * GET /messages/list
     *
     * @param array $params
     * @return array
     */
    public function getMessages($params = array())
    {
        return $this->get('/messages/list', $params);
    }

    /**
     * GET /messages/{id}
     *
     * @param int $id
     * @return array
     */
    public function getMessage($id)
    {
        return $this->get('/messages/' . (int) $id);
    }

    /**
     * POST /messages/set_status
     *
     * @param array $body
     * @return array
     */
    public function setMessageStatus($body)
    {
        return $this->post('/messages/set_status', $body);
    }

    /**
     * POST /messages/reply
     *
     * @param array $body
     * @return array
     */
    public function replyMessage($body)
    {
        return $this->post('/messages/reply', $body);
    }

    // ── Groups ───────────────────────────────────────────────────────────────

    /**
     * GET /groups/list
     *
     * @return array
     */
    public function getGroups()
    {
        return $this->get('/groups/list');
    }

    // ── Delivery ─────────────────────────────────────────────────────────────

    /**
     * GET /delivery_options/list
     *
     * @return array
     */
    public function getDeliveryOptions()
    {
        return $this->get('/delivery_options/list');
    }

    /**
     * POST /delivery/save_declaration_id
     *
     * @param int    $orderId
     * @param string $declarationId   TTN number
     * @param string $deliveryType    nova_poshta|ukrposhta|meest
     * @return array
     */
    public function saveDeclarationId($orderId, $declarationId, $deliveryType = 'nova_poshta')
    {
        return $this->post('/delivery/save_declaration_id', array(
            'order_id'       => (int) $orderId,
            'declaration_id' => $declarationId,
            'delivery_type'  => $deliveryType,
        ));
    }

    // ── Payment ──────────────────────────────────────────────────────────────

    /**
     * GET /payment_options/list
     *
     * @return array
     */
    public function getPaymentOptions()
    {
        return $this->get('/payment_options/list');
    }

    // ── Order Status Options ─────────────────────────────────────────────────

    /**
     * GET /order_status_options/list
     *
     * @return array
     */
    public function getOrderStatusOptions()
    {
        return $this->get('/order_status_options/list');
    }

    // ── Chat ─────────────────────────────────────────────────────────────────

    /**
     * GET /chat/rooms
     *
     * @param array $params
     * @return array
     */
    public function getChatRooms($params = array())
    {
        return $this->get('/chat/rooms', $params);
    }

    /**
     * GET /chat/messages_history
     *
     * @param array $params
     * @return array
     */
    public function getChatHistory($params = array())
    {
        return $this->get('/chat/messages_history', $params);
    }

    /**
     * POST /chat/send_message
     *
     * @param array $body
     * @return array
     */
    public function sendChatMessage($body)
    {
        return $this->post('/chat/send_message', $body);
    }

    /**
     * POST /chat/send_file
     *
     * @param array $body
     * @return array
     */
    public function sendChatFile($body)
    {
        return $this->post('/chat/send_file', $body);
    }

    /**
     * POST /chat/mark_message_read
     *
     * @param array $body
     * @return array
     */
    public function markChatRead($body)
    {
        return $this->post('/chat/mark_message_read', $body);
    }

    // ── HTTP transport ───────────────────────────────────────────────────────

    /**
     * @param string $path
     * @param array  $params  Query string params
     * @return array
     */
    private function get($path, $params = array())
    {
        $url = self::BASE_URL . $path;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url);
    }

    /**
     * @param string $path
     * @param array  $body  JSON body
     * @return array
     */
    private function post($path, $body = array())
    {
        return $this->request('POST', self::BASE_URL . $path, $body);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array|null $body
     * @return array  Decoded JSON response or error array
     */
    private function request($method, $url, $body = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'Accept: application/json',
        ));

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return array('ok' => false, 'error' => 'cURL error: ' . $error, 'http_code' => 0);
        }

        $data = json_decode($response, true);
        if ($data === null) {
            return array('ok' => false, 'error' => 'Invalid JSON response', 'http_code' => $httpCode, 'raw' => $response);
        }

        if ($httpCode >= 400) {
            $errMsg = isset($data['message']) ? $data['message'] : 'HTTP ' . $httpCode;
            return array('ok' => false, 'error' => $errMsg, 'http_code' => $httpCode, 'data' => $data);
        }

        $data['ok'] = true;
        $data['http_code'] = $httpCode;
        return $data;
    }
}
