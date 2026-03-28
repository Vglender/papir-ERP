<?php

/**
 * Telegram Bot API wrapper.
 */
class TelegramBotService
{
    const TOKEN   = '932088148:AAFHo4RqBSep81y9JGtEiJuJHWEwfWHBR6Y';
    const API_URL = 'https://api.telegram.org/bot';

    /**
     * Send text message to a chat_id.
     * @return array ['ok'=>bool, 'error'=>string|null]
     */
    public static function sendMessage($chatId, $text)
    {
        $data = array(
            'chat_id'    => (string)$chatId,
            'text'       => $text,
            'parse_mode' => '',   // plain text
        );
        $result = self::call('sendMessage', $data);
        if ($result['ok']) {
            return array('ok' => true);
        }
        return array('ok' => false, 'error' => isset($result['description']) ? $result['description'] : 'Telegram error');
    }

    /**
     * Register webhook URL with Telegram.
     * Call once after deployment.
     */
    public static function setWebhook($url)
    {
        return self::call('setWebhook', array('url' => $url));
    }

    /**
     * Get current webhook info.
     */
    public static function getWebhookInfo()
    {
        return self::call('getWebhookInfo', array());
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private static function call($method, $data)
    {
        $url = self::API_URL . self::TOKEN . '/' . $method;
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return array('ok' => false, 'description' => 'No response from Telegram');
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : array('ok' => false, 'description' => 'Invalid JSON');
    }
}
