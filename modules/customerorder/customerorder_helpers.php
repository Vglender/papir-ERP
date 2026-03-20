<?php
// modules/customerorder/customerorder_helpers.php

/**
 * ПОМОЩНИКИ для работы со справочниками
 * Не относятся напрямую к заказам, но нужны для формы
 */

/**
 * Получить банковские счета организации
 * @param int|null $organizationId ID организации или null для всех счетов
 * @return array Массив счетов
 */
function getOrganizationBankAccounts($organizationId = null) {
    if (!empty($organizationId)) {
        $orgId = (int)$organizationId;
        $sql = "
            SELECT oba.*,
                   org.name as organization_name
            FROM organization_bank_account oba
            LEFT JOIN organization org ON org.id = oba.organization_id
            WHERE oba.organization_id = {$orgId}
              AND oba.status = 1
            ORDER BY oba.is_default DESC, oba.id ASC
        ";
    } else {
        $sql = "
            SELECT oba.*,
                   org.name as organization_name
            FROM organization_bank_account oba
            LEFT JOIN organization org ON org.id = oba.organization_id
            WHERE oba.status = 1
            ORDER BY oba.organization_id, oba.is_default DESC, oba.id ASC
            LIMIT 50
        ";
    }
    
    $result = Database::fetchAll('Papir', $sql);
    if ($result['ok'] && !empty($result['rows'])) {
        return $result['rows'];
    }
    
    return array();
}

/**
 * Получить валюты для select
 */
function getCurrencies() {
    return array(
        array('code' => 'UAH', 'name' => 'Гривня'),
        array('code' => 'EUR', 'name' => 'Євро'),
        array('code' => 'USD', 'name' => 'Долар'),
    );
}

/**
 * Получить каналы продаж
 */
function getSalesChannels() {
    return array(
        array('code' => 'manual', 'name' => 'Ручне введення'),
        array('code' => 'site', 'name' => 'Сайт'),
        array('code' => 'marketplace', 'name' => 'Маркетплейс'),
        array('code' => 'api', 'name' => 'API'),
    );
}

// Другие helper функции...