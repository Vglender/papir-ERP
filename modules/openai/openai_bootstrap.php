<?php
require_once __DIR__ . '/../../modules/database/database.php';
require_once __DIR__ . '/OpenAiClient.php';

// Загружаем ключ из gitignored файла
require_once __DIR__ . '/storage/openai_auth.php';

/**
 * Фабрика: возвращает готовый экземпляр OpenAiClient.
 *
 * Использование:
 *   require_once __DIR__ . '/../../modules/openai/openai_bootstrap.php';
 *   $ai = openai_client();
 *   $result = $ai->chat('Привет!');
 *
 * @return \Papir\Crm\OpenAiClient
 */
function openai_client() {
    global $OPENAI_API_KEY;
    return new \Papir\Crm\OpenAiClient($OPENAI_API_KEY);
}
