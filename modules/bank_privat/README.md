# bank_privat

Модуль для работы с API выписок PrivatBank по юридическим счетам.

## Структура

- `privat_api.php` — основной PHP-класс для работы с API
- `storage/privat_accounts.php` — список счетов, id, token, user_agent
- `tools/privat_settings.php` — тест запроса серверных настроек
- `tools/privat_transactions_test.php` — тест получения транзакций за дату

## Подключение

```php
require_once __DIR__ . '/privat_api.php';

$api = new PrivatApi(array(
    'default_user_agent' => 'Papir',
));

$api->loadAccountsFromFile(__DIR__ . '/storage/privat_accounts.php');

Основные методы

Получить настройки сервера
$settings = $api->getSettings();

Получить транзакции за день
$transactions = $api->getTransactionsByDate('2026-03-13');

Получить транзакции за период
$transactions = $api->getTransactions('2026-03-01', '2026-03-13');

Получить балансы за период
$balances = $api->getBalances('2026-03-01', '2026-03-13');

Получить промежуточные транзакции
$transactions = $api->getInterimTransactions();

Получить финальные транзакции
$transactions = $api->getFinalTransactions();

Для уникального идентификатора платежной инструкции использовать конкатенацию:
$externalId = $transaction['REF'] . $transaction['REFN'];
Либо через метод:
$externalId = $api->buildTransactionExternalId($transaction);

Важно

модуль поддерживает пагинацию через followId

если exist_next_page = true, следующая пачка загружается автоматически

limit ограничивается значением до 500

токены и счета следует хранить только в storage/privat_accounts.php

id header поддерживается как опциональный


---

# Как использовать в проекте

Если хочешь вызывать как у тебя сейчас была функция `request_pb_ur($date_from)`, можно сделать тонкую обёртку, например в любом твоём сервисе:

```php
require_once '/var/www/papir/modules/bank_privat/privat_api.php';

function request_pb_ur($dateFrom)
{
    $api = new PrivatApi(array(
        'default_user_agent' => 'Papir',
        'default_limit' => 100,
    ));

    $api->loadAccountsFromFile('/var/www/papir/modules/bank_privat/storage/privat_accounts.php');

    return $api->getTransactionsByDate($dateFrom);
}


