<?php

class PaymentDuplicateChecker
{
    /**
     * Перевіряє дублі по Papir.finance_bank.external_code
     * (замість ms.paymentin / ms.paymentout — зеркало більше не потрібне)
     */

    /**
     * Одиночна перевірка дубля
     */
    public function exists($type, $externalCode)
    {
        $direction = ($type === 'out') ? 'out' : 'in';

        $sql = "SELECT id FROM finance_bank
                WHERE external_code = '" . Database::escape('Papir', $externalCode) . "'
                  AND direction = '{$direction}'
                LIMIT 1";

        $row = Database::fetchRow('Papir', $sql);

        if (!$row['ok']) {
            throw new RuntimeException(
                'DB duplicate exists() failed: '
                . (isset($row['error']) ? $row['error'] : 'unknown error')
            );
        }

        return !empty($row['row']);
    }

    /**
     * Отримати id існуючого запису
     */
    public function findId($type, $externalCode)
    {
        $direction = ($type === 'out') ? 'out' : 'in';

        $sql = "SELECT id FROM finance_bank
                WHERE external_code = '" . Database::escape('Papir', $externalCode) . "'
                  AND direction = '{$direction}'
                LIMIT 1";

        $row = Database::fetchRow('Papir', $sql);

        if ($row['ok'] && !empty($row['row']['id'])) {
            return $row['row']['id'];
        }

        return null;
    }

    /**
     * Пакетно отримати вже існуючі externalCode з Papir.finance_bank
     */
    public function getExistingExternalCodes($type, array $externalCodes)
    {
        if (empty($externalCodes)) {
            return array();
        }

        $direction = ($type === 'out') ? 'out' : 'in';

        $escaped = array();
        foreach ($externalCodes as $code) {
            if ($code === null || $code === '') {
                continue;
            }
            $escaped[] = "'" . Database::escape('Papir', $code) . "'";
        }

        if (empty($escaped)) {
            return array();
        }

        $sql = "SELECT external_code FROM finance_bank
                WHERE external_code IN (" . implode(',', $escaped) . ")
                  AND direction = '{$direction}'";

        $rows = Database::fetchAll('Papir', $sql);

        if (!$rows['ok']) {
            throw new RuntimeException(
                'DB duplicate check failed: '
                . (isset($rows['error']) ? $rows['error'] : 'unknown error')
            );
        }

        if (empty($rows['rows'])) {
            return array();
        }

        $result = array();
        foreach ($rows['rows'] as $row) {
            if (isset($row['external_code'])) {
                $result[] = $row['external_code'];
            }
        }

        return $result;
    }

    /**
     * Пакетний відсів дублів по Papir.finance_bank
     */
    public function filterNotExistingInDb($type, array $payments)
    {
        if (empty($payments)) {
            return array(
                'new'        => array(),
                'duplicates' => array(),
            );
        }

        $codes = array();
        foreach ($payments as $payment) {
            if (!empty($payment['id_paid'])) {
                $codes[] = $payment['id_paid'];
            }
        }

        $existingCodes = $this->getExistingExternalCodes($type, $codes);
        $existingMap   = array_flip($existingCodes);

        $new        = array();
        $duplicates = array();

        foreach ($payments as $payment) {
            $code = isset($payment['id_paid']) ? $payment['id_paid'] : null;

            if ($code && isset($existingMap[$code])) {
                $duplicates[] = $payment;
            } else {
                $new[] = $payment;
            }
        }

        return array(
            'new'        => $new,
            'duplicates' => $duplicates,
        );
    }

    /**
     * Додаткова перевірка дублів безпосередньо в МойСклад API
     * (залишаємо як другу лінію захисту, але за замовчуванням вимкнена)
     */
    public function filterNotExistingInMs($type, array $payments, MoySkladApi $ms = null)
    {
        if (!$ms || empty($payments)) {
            return array(
                'new'        => $payments,
                'duplicates' => array(),
            );
        }

        $entity = ($type === 'out') ? 'paymentout' : 'paymentin';
        $new        = array();
        $duplicates = array();

        foreach ($payments as $payment) {
            $code = isset($payment['id_paid']) ? $payment['id_paid'] : null;

            if ($code) {
                $link     = $ms->getEntityBaseUrl() . $entity . '?filter=' . urlencode('externalCode=' . $code);
                $response = $ms->query($link);
                $response = json_decode(json_encode($response), true);

                if (!empty($response['rows'])) {
                    $duplicates[] = $payment;
                    continue;
                }
            }

            $new[] = $payment;
        }

        return array(
            'new'        => $new,
            'duplicates' => $duplicates,
        );
    }
}
