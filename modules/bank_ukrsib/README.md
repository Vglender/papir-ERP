# Papir CRM — UKRSIB Bank Module

Модуль интеграции с **UKRSIBBANK Open Banking API**.

Модуль используется для:

- получения банковских выписок
- авторизации через OAuth2
- автоматического обновления access_token
- подписи запросов RSA SHA512

---

# Архитектура модуля

modules/bank_ukrsib/
│
├── ukrsib_api.php # основной API модуль
├── ukrsib_config.php # конфигурация
│
├── storage/
│ ├── ukrsib_private.pem # RSA приватный ключ
│ ├── ukrsib_tokens.json # access/refresh tokens
│ └── ukrsib_client_code.json # временный OAuth client_code
│
└── tools/
├── ukrsib_token_status.php # статус токена
└── ukrsib_token_exchange.php # получение initial tokens


# Конфигурация

Файл:
ukrsib_config.php

Содержит:

- client_id
- client_secret
- base_url
- пути к ключам
- список счетов

Пример:

```php
return array(
    'base_url' => 'https://business.ukrsibbank.com/morpheus',

    'client_id' => 'CLIENT_ID',
    'client_secret' => 'CLIENT_SECRET',

    'private_key' => __DIR__.'/storage/ukrsib_private.pem',

    'token_file' => __DIR__.'/storage/ukrsib_tokens.json',
    'client_code_file' => __DIR__.'/storage/ukrsib_client_code.json',

    'accounts' => array(
        array(
            'acc' => 'UA673510050000026003879283009_UAH'
        )
    )
);

Авторизация

Используется OAuth2.

Процесс:

Генерируется client_code

Пользователь проходит авторизацию в браузере

Получается access_token и refresh_token

Токены сохраняются в:

storage/ukrsib_tokens.json

Подпись запросов

Все запросы к API подписываются:

SHA512WithRSA

Источник подписи:
accounts|dateFrom|dateTo|firstResult|maxResult
Функция:
ukrsib_sign_string()


Основные функции

ukrsib_config()
Загружает конфигурацию модуля.
$config = ukrsib_config();

ukrsib_get_access_token()
Возвращает действующий access_token.
Если срок истек — автоматически обновляет через refresh_token.
$token = ukrsib_get_access_token();

ukrsib_refresh_access_token()
Обновляет access_token через refresh_token.

ukrsib_statement_request()
Выполняет запрос выписки к API.

$result = ukrsib_statement_request(
    $account,
    $dateFromMs,
    $dateToMs,
    $firstResult,
    $maxResult
);

request_ukrsib()
Главная функция получения платежей.
Возвращает массив операций.
$payments = request_ukrsib('2026-01-01');
Формат операций
Модуль возвращает унифицированный формат:
[
    _bank
    _acc
    _id
    _date
    _amount
    _currency
    _description
    _reference
    _counterparty
    _iban
    _counter_iban
]
Это позволяет CRM обрабатывать платежи одинаково для разных банков.


Вспомогательные функции
ukrsib_uuid_v4()
Генерация UUID.
Используется для:
X-Request-ID

ukrsib_date_to_ms()
Конвертирует дату в UNIX timestamp (milliseconds).
ukrsib_date_to_ms('2026-01-01')

ukrsib_now_ms()
Возвращает текущее время в миллисекундах.

Служебные страницы
token_status
tools/ukrsib_token_status.php
Показывает:
срок действия access_token
refresh_token
статус токена

token_exchange
tools/ukrsib_token_exchange.php
Используется для:
получения initial access_token
OAuth авторизации

Безопасность
Файлы:
storage/ukrsib_private.pem
storage/ukrsib_tokens.json
не должны быть доступны напрямую через web сервер.
Рекомендуется:
закрыть директорию .htaccess
или вынести storage за пределы web root

Использование в Papir CRM
Пример:
require_once 'modules/bank_ukrsib/ukrsib_api.php';
$payments = request_ukrsib('2026-01-01');

Планируемое развитие

В будущем возможно добавить:
поддержку нескольких счетов
webhook обработку платежей
унифицированный интерфейс банков
bank_request('ukrsib')
bank_request('mono')
bank_request('privat')


Автор

Papir CRM
Bank integration module