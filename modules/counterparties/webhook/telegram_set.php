<?php
/**
 * One-time helper: registers Telegram webhook URL.
 * Open in browser once: https://papir.officetorg.com.ua/counterparties/webhook/telegram_set
 */
require_once __DIR__ . '/../counterparties_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$webhookUrl = 'https://papir.officetorg.com.ua/counterparties/webhook/telegram_in';
$result     = TelegramBotService::setWebhook($webhookUrl);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
