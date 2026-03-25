<?php
namespace Papir\Crm;

class OpenAiClient {

    const BASE_URL    = 'https://api.openai.com/v1/';
    const DEFAULT_MODEL = 'gpt-4o-mini';

    private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Отправить сообщение в Chat Completions и получить текст ответа.
     *
     * @param string      $prompt
     * @param string      $model
     * @param int|null    $maxTokens
     * @param float       $temperature
     * @return array ['ok'=>bool, 'text'=>string] | ['ok'=>false, 'error'=>string]
     */
    public function chat($prompt, $model = self::DEFAULT_MODEL, $maxTokens = null, $temperature = 0.7) {
        $data = array(
            'model'       => $model,
            'messages'    => array(
                array('role' => 'user', 'content' => $prompt),
            ),
            'temperature' => $temperature,
        );

        if (!is_null($maxTokens)) {
            $data['max_tokens'] = $maxTokens;
        }

        $result = $this->sendRequest('chat/completions', $data);

        if (!$result['ok']) {
            return $result;
        }

        $body = $result['body'];

        if (isset($body['error'])) {
            return array('ok' => false, 'error' => $body['error']['message']);
        }

        $text = isset($body['choices'][0]['message']['content'])
            ? trim($body['choices'][0]['message']['content'])
            : '';

        return array('ok' => true, 'text' => $text);
    }

    /**
     * Отправить запрос с системной инструкцией + пользовательским сообщением.
     *
     * @param string   $systemPrompt  Инструкция для ассистента
     * @param string   $userMessage   Данные от пользователя
     * @param string   $model
     * @param int|null $maxTokens
     * @param float    $temperature
     * @return array ['ok'=>bool, 'text'=>string] | ['ok'=>false, 'error'=>string]
     */
    public function chatWithSystem($systemPrompt, $userMessage, $model = self::DEFAULT_MODEL, $maxTokens = null, $temperature = 0.7) {
        $data = array(
            'model'       => $model,
            'messages'    => array(
                array('role' => 'system',  'content' => $systemPrompt),
                array('role' => 'user',    'content' => $userMessage),
            ),
            'temperature' => $temperature,
        );

        if (!is_null($maxTokens)) {
            $data['max_tokens'] = $maxTokens;
        }

        $result = $this->sendRequest('chat/completions', $data);

        if (!$result['ok']) {
            return $result;
        }

        $body = $result['body'];

        if (isset($body['error'])) {
            return array('ok' => false, 'error' => $body['error']['message']);
        }

        $text = isset($body['choices'][0]['message']['content'])
            ? trim($body['choices'][0]['message']['content'])
            : '';

        return array('ok' => true, 'text' => $text);
    }

    /**
     * Низкоуровневый HTTP запрос к OpenAI API.
     *
     * @param string $endpoint  Например: 'chat/completions'
     * @param array  $data
     * @return array ['ok'=>bool, 'body'=>array] | ['ok'=>false, 'error'=>string]
     */
    private function sendRequest($endpoint, $data) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => self::BASE_URL . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ),
            CURLOPT_TIMEOUT        => 60,
        ));

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return array('ok' => false, 'error' => 'cURL error: ' . $error);
        }

        $body = json_decode($response, true);
        if (!is_array($body)) {
            return array('ok' => false, 'error' => 'Invalid JSON response from OpenAI');
        }

        return array('ok' => true, 'body' => $body);
    }
}
