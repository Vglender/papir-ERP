<?php

require_once __DIR__ . '/../integrations/IntegrationSettingsService.php';

/**
 * Telegram Bot API wrapper.
 */
class TelegramBotService
{
    const API_URL = 'https://api.telegram.org/bot';

    private static $token = null;

    private static function getToken()
    {
        if (self::$token === null) {
            self::$token = IntegrationSettingsService::get('telegram', 'bot_token', '');
        }
        return self::$token;
    }

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
     * Send photo to a chat_id. Caption is optional.
     * If non-image file, falls back to sendMessage with just the caption/text.
     * @return array ['ok'=>bool, 'error'=>string|null]
     */
    public static function sendPhoto($chatId, $photoUrl, $caption = '')
    {
        $data = array(
            'chat_id' => (string)$chatId,
            'photo'   => $photoUrl,
        );
        if ($caption !== '') {
            $data['caption'] = $caption;
        }
        $result = self::call('sendPhoto', $data);
        if ($result['ok']) {
            return array('ok' => true);
        }
        return array('ok' => false, 'error' => isset($result['description']) ? $result['description'] : 'Telegram error');
    }

    /**
     * Send document (any file type) to a chat_id.
     * @return array ['ok'=>bool, 'error'=>string|null]
     */
    public static function sendDocument($chatId, $fileUrl, $caption = '')
    {
        $data = array(
            'chat_id'  => (string)$chatId,
            'document' => $fileUrl,
        );
        if ($caption !== '') {
            $data['caption'] = $caption;
        }
        $result = self::call('sendDocument', $data);
        if ($result['ok']) {
            return array('ok' => true);
        }
        return array('ok' => false, 'error' => isset($result['description']) ? $result['description'] : 'Telegram error');
    }

    /**
     * Download a file from Telegram by file_id and save it to CRM media storage.
     * Returns public HTTPS URL or null on failure.
     */
    public static function downloadAndSaveFile($fileId)
    {
        // Get file path from Telegram
        $result = self::call('getFile', array('file_id' => $fileId));
        if (empty($result['ok']) || empty($result['result']['file_path'])) {
            return null;
        }
        $filePath    = $result['result']['file_path'];
        $downloadUrl = 'https://api.telegram.org/file/bot' . self::getToken() . '/' . $filePath;

        // Download
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $fileData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!$fileData || $httpCode !== 200) return null;

        // Determine extension
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!$ext) $ext = 'jpg';

        // Save
        $dir      = '/var/www/menufold/data/www/officetorg.com.ua/image/crm/messages/';
        $filename = date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
        if (@file_put_contents($dir . $filename, $fileData) === false) return null;

        return 'https://officetorg.com.ua/image/crm/messages/' . $filename;
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
        $url = self::API_URL . self::getToken() . '/' . $method;
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
