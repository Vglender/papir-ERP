<?php
require_once __DIR__ . '/../../modules/database/database.php';
require_once __DIR__ . '/OpenAiClient.php';
require_once __DIR__ . '/AiPromptBuilder.php';

/**
 * Фабрика: возвращает готовый экземпляр OpenAiClient.
 *
 * Ключ читается непосредственно из файла через include в локальной области,
 * чтобы не зависеть от global-переменных и порядка загрузки файлов.
 *
 * @return \Papir\Crm\OpenAiClient
 */
function openai_client() {
    static $key = null;
    if ($key === null) {
        $OPENAI_API_KEY = '';
        include __DIR__ . '/storage/openai_auth.php';
        $key = (string)$OPENAI_API_KEY;
    }
    return new \Papir\Crm\OpenAiClient($key);
}
