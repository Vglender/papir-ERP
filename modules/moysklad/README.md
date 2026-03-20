MoySklad API Module

Модуль moysklad предназначен для взаимодействия с API учетной системы МойСклад из проекта Papir CRM.

Модуль реализует базовые функции для выполнения REST-запросов к API МойСклад и служит основой для дальнейших утилит работы с:

товарами

контрагентами

заказами

документами

отчетами

изображениями товаров

печатными формами документов

Структура модуля
modules/
└── moysklad
    ├── README.md
    ├── moysklad_api.php
    ├── storage
    │   └── moysklad_auth.php
    └── tools
        └── test_connection.php
Описание файлов
Файл	Назначение
moysklad_api.php	Основной API-класс для работы с МойСклад
storage/moysklad_auth.php	Конфигурация подключения и авторизации
tools/test_connection.php	Утилита для проверки подключения
README.md	Документация модуля
Конфигурация

Файл:

storage/moysklad_auth.php
<?php

return array(
    'auth' => 'login:password',
    'api_base_url_entity' => 'https://api.moysklad.ru/api/remap/1.2/entity/',
    'api_base_url_report' => 'https://api.moysklad.ru/api/remap/1.2/report/',
);
Параметры
Параметр	Назначение
auth	Basic auth логин и пароль
api_base_url_entity	базовый URL для entity API
api_base_url_report	базовый URL для отчетов
Подключение модуля
require_once '/var/www/papir/modules/moysklad/moysklad_api.php';

$ms = new MoySkladApi();
Базовые методы
query()

Выполняет обычный GET-запрос к API.

Синтаксис
$ms->query($link, $type = null);
Пример
$result = $ms->query(
    $ms->getEntityBaseUrl() . 'product?limit=10'
);
querySend()

Отправляет запрос с телом (POST / PUT / DELETE).

Синтаксис
$ms->querySend($link, $data, $type);
Пример создания товара
$data = array(
    'name' => 'Тестовый товар'
);

$result = $ms->querySend(
    $ms->getEntityBaseUrl() . 'product',
    $data,
    'POST'
);
addImage()

Добавляет изображение к товару.

МойСклад использует endpoint:

/entity/product/{id}/images
Синтаксис
$ms->addImage($entityId, $filename, $imagePath);
Пример
$ms->addImage(
    $productId,
    'photo.jpg',
    '/var/www/images/photo.jpg'
);

Изображение автоматически:

читается с диска

кодируется в base64

отправляется в API

Модуль также обрабатывает HTTP 308 redirect, который иногда возвращает API МойСклад.

querySendPrintDoc()

Получает URL печатной формы документа.

МойСклад сначала возвращает redirect, содержащий ссылку на PDF.

Метод извлекает Location из заголовков.

Синтаксис
$ms->querySendPrintDoc($link, $data, $type);
Пример
$link = $ms->getEntityBaseUrl() . 'customerorder/metadata/embeddedtemplate/print';

$location = $ms->querySendPrintDoc(
    $link,
    $data,
    'POST'
);

Возвращается:

https://online.moysklad.ru/api/remap/.../print/....
Особенности реализации
Ограничение частоты запросов

Каждый запрос содержит задержку:

usleep(66700)

Это примерно 15 запросов в секунду, что соответствует ограничениям API МойСклад.

Поддержка gzip

API иногда возвращает gzip-ответ.

Модуль автоматически:

gzdecode()

и возвращает JSON-объект.

Пример получения товаров
$ms = new MoySkladApi();

$result = $ms->query(
    $ms->getEntityBaseUrl() . 'product?limit=100'
);

foreach ($result->rows as $product) {

    echo $product->name . "\n";

}
Пример обновления товара
$data = array(
    'name' => 'Новое название товара'
);

$ms->querySend(
    $ms->getEntityBaseUrl() . 'product/' . $productId,
    $data,
    'PUT'
);
Утилиты

В папке tools размещаются вспомогательные скрипты.

Пример:

tools/test_connection.php
require_once __DIR__ . '/../moysklad_api.php';

$ms = new MoySkladApi();

$link = $ms->getEntityBaseUrl() . 'product?limit=1';

$result = $ms->query($link);

print_r($result);
План развития модуля

В дальнейшем в модуль будут добавлены:

Работа с товарами
product
variant
assortment
Документы
customerorder
demand
invoiceout
supply
Контрагенты
counterparty
organization
Отчеты
stock
sales
profit
Дополнительные утилиты

пагинация

массовый импорт

синхронизация каталога

загрузка изображений

экспорт документов

Связь с Papir CRM

Модуль используется для:

синхронизации каталога товаров

получения остатков

получения себестоимости

загрузки изображений

получения документов

Версия
Module: moysklad
API version: remap 1.2
Project: Papir CRM