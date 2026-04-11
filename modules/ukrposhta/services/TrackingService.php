<?php
namespace Papir\Crm;

/**
 * Ukrposhta status tracking — batch + single.
 *
 * API: POST /status-tracking/0.0.1/statuses/last  (body = array of barcodes)
 *      GET  /status-tracking/0.0.1/statuses/last?barcode=...
 *
 * Event code → lifecycle_status mapping is the same that the legacy
 * /var/sqript/Ukrposhta/Upd_ukr_ttn.php and /var/sqript/UP used, so old
 * and new rows stay consistent.
 *
 * When a status actually changes, TriggerEngine fires 'ttn_status_changed'
 * so the order-side scenario engine reacts to Ukrposhta shipments the
 * exact same way it reacts to Nova Poshta (migration 012).
 */
class TrackingService
{
    // NP-compatible state_define values that scenarios already know about.
    // We map UP lifecycle to NP numeric codes so shared scenario conditions
    // (e.g. "state_define IN (4,5,6)") work for both carriers.
    private static $NP_EQUIVALENT = array(
        'CREATED'       => 1,    // Чернетка
        'REGISTERED'    => 1,    // Не передана відправником → NP "draft"
        'UNKNOWN'       => 1,    // Невідомий — трактуємо як draft, щоб трекінг підхопив
        'DELIVERING'    => 5,    // В дорозі
        'FORWARDING'    => 5,
        'IN_DEPARTMENT' => 7,    // Прибуло на відділення
        'STORAGE'       => 8,    // На зберіганні
        'DELIVERED'     => 9,    // Отримана
        'RETURNING'     => 10,   // Повертається до відправника
        'RETURNED'      => 11,   // Повернення отримано
        'CANCELLED'     => 106,  // Відмовлено
        'DELETED'       => 2,    // Видалена
    );

    /**
     * Track a single TTN by local id.
     */
    public static function trackOne($ttnId)
    {
        $ttn = UpTtnRepository::getById($ttnId);
        if (!$ttn)          return array('ok' => false, 'error' => 'TTN not found');
        if (!$ttn['barcode']) return array('ok' => false, 'error' => 'Barcode missing');

        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'API not configured');

        $r = $api->trackOne($ttn['barcode']);
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        self::applyStatus($ttn, $r['data']);
        return array('ok' => true, 'data' => $r['data']);
    }

    /**
     * Batch track a list of TTN rows (from UpTtnRepository::getForTracking).
     * Uses the POST /statuses/last endpoint (up to 100 per request).
     */
    public static function trackBatch(array $ttns)
    {
        $updated = 0;
        $errors  = array();

        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'updated' => 0, 'errors' => array('API not configured'));

        $byBarcode = array();
        foreach ($ttns as $t) {
            if (!empty($t['barcode'])) $byBarcode[$t['barcode']] = $t;
        }
        $barcodes = array_keys($byBarcode);
        if (!$barcodes) return array('ok' => true, 'updated' => 0, 'errors' => array());

        foreach (array_chunk($barcodes, 100) as $chunk) {
            $r = (count($chunk) === 1)
                ? $api->trackOne($chunk[0])
                : $api->trackBatch($chunk);

            // Якщо батч упав з 404 — хоч один barcode не зареєстрований
            // в системі UP (драфт/видалений). Пробуємо поштучно: апдейтимо
            // знайдені, а для 404-рядків просто бампаємо lastModified,
            // щоб вони скотилися в хвіст черги і не блокували наступний батч.
            if (!$r['ok']) {
                if ((int)$r['http'] === 404 && count($chunk) > 1) {
                    foreach ($chunk as $bc) {
                        $one = $api->trackOne($bc);
                        if ($one['ok'] && isset($byBarcode[$bc])) {
                            if (self::applyStatus($byBarcode[$bc], $one['data'])) $updated++;
                        } elseif (isset($byBarcode[$bc]) && (int)$one['http'] === 404) {
                            UpTtnRepository::updateById((int)$byBarcode[$bc]['id'], array(
                                'lastModified' => date('Y-m-d H:i:s'),
                            ));
                        }
                        usleep(50000);
                    }
                    continue;
                }
                $errors[] = 'batch(' . count($chunk) . '): ' . $r['error'];
                continue;
            }

            // Single-result and batch shapes differ.
            $results = $r['data'];
            if (count($chunk) === 1) $results = array($results);
            foreach ($results as $status) {
                if (!is_array($status) || empty($status['barcode'])) continue;
                $bc = $status['barcode'];
                if (isset($byBarcode[$bc])) {
                    if (self::applyStatus($byBarcode[$bc], $status)) $updated++;
                }
            }
            usleep(80000);
        }

        return array('ok' => true, 'updated' => $updated, 'errors' => $errors);
    }

    /**
     * Apply a single tracking result to a TTN row.
     * @return bool true if anything was updated
     */
    private static function applyStatus(array $ttn, $status)
    {
        if (!is_array($status) || empty($status)) return false;

        $event      = isset($status['event'])     ? (string)$status['event']     : '';
        $event      = preg_replace('/\s+/', '', $event);
        $eventName  = isset($status['eventName']) ? (string)$status['eventName'] : '';
        $dateRaw    = isset($status['date']) ? $status['date'] : null;
        $dateSql    = null;
        if ($dateRaw) {
            $ts = is_numeric($dateRaw) ? (int)$dateRaw : strtotime((string)$dateRaw);
            if ($ts) $dateSql = date('Y-m-d H:i:s', $ts);
        }

        $newLifecycle = self::getLifecycleFromEvent($event);
        $oldLifecycle = $ttn['lifecycle_status'] ?: 'CREATED';

        // Невідомий код: якщо в БД уже є валідний стан — не перетираємо.
        // Інакше (CREATED/REGISTERED/UNKNOWN) — фіксуємо UNKNOWN,
        // щоб його було видно в статистиці і підхопили наступним проходом.
        if (!$newLifecycle || $newLifecycle === 'UNKNOWN') {
            $draftish = array('CREATED','REGISTERED','UNKNOWN','');
            $newLifecycle = in_array($oldLifecycle, $draftish, true) ? 'UNKNOWN' : $oldLifecycle;
        }

        $changed = ($newLifecycle !== $oldLifecycle);
        $patch = array(
            'lifecycle_status'     => $newLifecycle,
            'lifecycle_statusDate' => $dateSql ?: $ttn['lifecycle_statusDate'],
            'state_description'    => $eventName ?: $ttn['state_description'],
            'eventName'            => $eventName ?: $ttn['eventName'],
            'lastModified'         => date('Y-m-d H:i:s'),
        );

        UpTtnRepository::updateById((int)$ttn['id'], $patch);

        if ($changed) {
            self::fireTtnStatusChanged($ttn, $oldLifecycle, $newLifecycle, $eventName);
        }

        return true;
    }

    /**
     * UP event code → lifecycle status.
     *
     * Коди — з офіційного довідника Укрпошти (group 1xxxx = прийом,
     * 2xxxx = сортування/транзит, 3xxxx = доставка, 4xxxx = вручення/повернення).
     * Якщо з'явиться новий невідомий код — повертаємо 'UNKNOWN' (а не null),
     * щоб рядок не залипав у CREATED і був підхоплений повторним трекінгом.
     */
    public static function getLifecycleFromEvent($event)
    {
        $event = (string)$event;
        if ($event === '') return null;

        switch ($event) {
            // ── 1xxxx: реєстрація / прийом ───────────────────────────────
            case '10100':  // Прийнято від відправника
            case '10200':  // Очікує прийому на пошті
            case '10300':  // Передано оператору
            case '10500':  // Прийнято до пересилання
                return 'REGISTERED';

            case '10400':  // Відмова від послуги
            case '10600':  // Послуга відмінена
            case '10601':  // Відкликано відправником
            case '10602':  // Анульовано
            case '10610':
                return 'CANCELLED';

            // ── 2xxxx: сортування / транзит ──────────────────────────────
            case '20100':  // Прийнято до сортування
            case '20500':  // Сортування завершено
            case '20700':
            case '20800':
            case '20900':
            case '21500':  // Передано на доставку
                return 'DELIVERING';

            case '21400':  // На зберіганні
                return 'STORAGE';

            case '21700':  // Прибуло у відділення
                return 'IN_DEPARTMENT';

            // ── 3xxxx: доставка / переадресація ──────────────────────────
            case '31100':  // У транзитному вузлі
                return 'DELIVERING';

            case '31200':  // Повертається до відправника
                return 'RETURNING';

            case '31300':  // Переадресовано
            case '31400':  // Переадресовано (інший варіант)
                return 'FORWARDING';

            // ── 4xxxx: вручення / повернення ─────────────────────────────
            case '41000':  // Вручено адресату
            case '48000':  // Вручено (інший тип)
                return 'DELIVERED';

            case '41010':  // Повернено відправнику
            case '41020':
                return 'RETURNED';
        }

        // Невідомий код — позначаємо явно, щоб трекінг бачив такі рядки
        // і повторював запит при наступному проході.
        return 'UNKNOWN';
    }

    /**
     * Fire ttn_status_changed via TriggerEngine. Only fires when linked to an order.
     */
    private static function fireTtnStatusChanged($ttn, $oldStatus, $newStatus, $stateName)
    {
        $orderId = isset($ttn['customerorder_id']) ? (int)$ttn['customerorder_id'] : 0;
        $cpId    = 0;

        if (!$orderId) {
            // Try via document_link (same as NP tracking)
            $r = \Database::fetchRow('Papir',
                "SELECT dl.to_id AS order_id, co.counterparty_id
                 FROM document_link dl
                 JOIN customerorder co ON co.id = dl.to_id
                 WHERE dl.from_type = 'ttn_up' AND dl.from_id = " . (int)$ttn['id']
               . "   AND dl.to_type = 'customerorder'
                 LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) {
                $orderId = (int)$r['row']['order_id'];
                $cpId    = (int)$r['row']['counterparty_id'];
            }
        }
        if (!$orderId) return;

        if (!$cpId) {
            $r = \Database::fetchRow('Papir',
                "SELECT counterparty_id FROM customerorder WHERE id = " . $orderId . " LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) $cpId = (int)$r['row']['counterparty_id'];
        }

        if (!class_exists('TriggerEngine', false)) {
            $p = __DIR__ . '/../../counterparties/counterparties_bootstrap.php';
            if (file_exists($p)) require_once $p;
        }
        if (!class_exists('TriggerEngine', false)) return;

        $npEq = isset(self::$NP_EQUIVALENT[$newStatus]) ? self::$NP_EQUIVALENT[$newStatus] : 0;
        $npOldEq = isset(self::$NP_EQUIVALENT[$oldStatus]) ? self::$NP_EQUIVALENT[$oldStatus] : 0;

        \TriggerEngine::fire('ttn_status_changed', array(
            'order_id'        => $orderId,
            'counterparty_id' => $cpId,
            'ttn' => array(
                'id'               => (int)$ttn['id'],
                'int_doc_number'   => $ttn['barcode'],
                'barcode'          => $ttn['barcode'],
                'carrier'          => 'ukrposhta',
                'old_lifecycle'    => $oldStatus,
                'new_lifecycle'    => $newStatus,
                'old_state_define' => $npOldEq,
                'new_state_define' => $npEq,
                'state_name'       => $stateName,
            ),
        ));
    }
}