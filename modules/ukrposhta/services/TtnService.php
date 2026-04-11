<?php
namespace Papir\Crm;

require_once __DIR__ . '/../UpDefaults.php';

/**
 * Ukrposhta TTN (shipment) lifecycle — create / update / delete / refresh.
 *
 * Flow for create():
 *   1. Build recipient address via POST /addresses  (postcode + optional street/house/flat)
 *   2. Build recipient client   via POST /clients   (type + name/fio + phone + addressId)
 *   3. Build shipment           via POST /shipments (sender uuid, recipient uuid, parcels…)
 *   4. Download the sticker PDF via GET  /forms/.../sticker and save to /docum/Ukrpost/{YYYY_MM}/{barcode}.pdf
 *   5. Store all the details into ttn_ukrposhta (camelCase legacy schema).
 *
 * Adapted from /var/sqript/Ukrposhta/send_ttn_urk.php and /var/sqript/UP.
 */
class TtnService
{
    const LABEL_DIR_FS  = '/var/www/menufold/data/www/officetorg.com.ua/docum/Ukrpost';
    const LABEL_URL_BASE = 'https://officetorg.com.ua/docum/Ukrpost';

    /**
     * Create a new TTN.
     *
     * @param array $p {
     *   customerorder_id, demand_id                 int|null
     *   postcode                                    string   (required, UP postindex of recipient warehouse or address)
     *   street, building, flat                      string   (optional, triggers W2D)
     *
     *   // Recipient identity — one of:
     *   recipient_last_name, recipient_first_name,
     *   recipient_middle_name, recipient_phone, recipient_email
     *   // OR legal entity:
     *   recipient_type            'INDIVIDUAL'|'PRIVATE_ENTREPRENEUR'|'COMPANY'
     *   recipient_name            string  (legal title)
     *   recipient_code            string  (tin=10 digits or edrpou=8 digits)
     *
     *   // Cargo
     *   weight, length, width, height    numeric (kg, cm)
     *   seats                             int    default 1
     *   seats_description                 string "30;22;3;1/25;22;3;0.5"  (L;W;H;weight) per seat
     *   description                       string
     *   declared_price                    numeric
     *   post_pay                          numeric (cash on delivery)
     *   paid_by                           'sender'|'recipient'
     *   type                              'STANDARD'|'EXPRESS'
     * }
     *
     * @return array { ok, ttn_id?, uuid?, barcode?, label?, error? }
     */
    public static function create(array $p)
    {
        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'Ukrposhta API не налаштовано (немає токенів)');

        $postcode = isset($p['postcode']) ? preg_replace('/[^0-9]/', '', (string)$p['postcode']) : '';
        if (!$postcode) return array('ok' => false, 'error' => 'Не вказано індекс відділення/адреси');

        $phone = self::checkPhone(isset($p['recipient_phone']) ? $p['recipient_phone'] : '');
        if (!$phone) return array('ok' => false, 'error' => 'Невірний телефон одержувача');

        // ── 1. Address ───────────────────────────────────────────────────────
        $street = isset($p['street']) ? trim($p['street']) : '';
        $house  = isset($p['building']) ? trim($p['building']) : '';
        $flat   = isset($p['flat']) ? trim($p['flat']) : '';
        $deliveryType = ($street && $house) ? 'W2D' : 'W2W';

        $addrBody = array('postcode' => $postcode);
        if ($street && $house) {
            $addrBody['street']          = $street;
            $addrBody['houseNumber']     = $house;
            $addrBody['apartmentNumber'] = $flat !== '' ? $flat : 1;
        }
        $addr = $api->createAddress($addrBody);
        if (!$addr['ok']) {
            return array('ok' => false, 'error' => 'Адреса: ' . $addr['error']);
        }
        $addressId = isset($addr['data']['id']) ? $addr['data']['id'] : null;
        if (!$addressId) return array('ok' => false, 'error' => 'Не отримано id адреси', 'raw' => $addr['raw']);

        // ── 2. Client (recipient) ────────────────────────────────────────────
        $agentBody = array(
            'addressId'   => $addressId,
            'phoneNumber' => $phone,
        );
        if (!empty($p['recipient_email'])) $agentBody['email'] = $p['recipient_email'];

        $rcpType = isset($p['recipient_type']) ? (string)$p['recipient_type'] : '';
        $code    = isset($p['recipient_code']) ? trim($p['recipient_code']) : '';
        $rcpNameUr = isset($p['recipient_name']) ? trim($p['recipient_name']) : '';

        if (!$rcpType) {
            if ($code && $rcpNameUr && strlen($code) === 10) $rcpType = 'PRIVATE_ENTREPRENEUR';
            elseif ($code && $rcpNameUr && strlen($code) === 8) $rcpType = 'COMPANY';
            else $rcpType = 'INDIVIDUAL';
        }

        if ($rcpType === 'PRIVATE_ENTREPRENEUR') {
            $agentBody['type'] = 'PRIVATE_ENTREPRENEUR';
            $agentBody['name'] = mb_substr($rcpNameUr, 0, 40);
            $agentBody['tin']  = $code;
        } elseif ($rcpType === 'COMPANY') {
            $agentBody['type']   = 'COMPANY';
            $agentBody['name']   = mb_substr($rcpNameUr, 0, 40);
            $agentBody['edrpou'] = $code;
        } else {
            $agentBody['type']       = 'INDIVIDUAL';
            $agentBody['firstName']  = isset($p['recipient_first_name'])  ? trim($p['recipient_first_name'])  : 'Ім\'я';
            $agentBody['lastName']   = isset($p['recipient_last_name'])   ? trim($p['recipient_last_name'])   : 'Прізвище';
            $agentBody['middleName'] = isset($p['recipient_middle_name']) ? trim($p['recipient_middle_name']) : 'Невідомо';
            foreach (array('firstName','lastName','middleName') as $_f) {
                if (empty($agentBody[$_f])) $agentBody[$_f] = '-';
            }
        }

        $client = $api->createClient($agentBody);
        if (!$client['ok']) {
            return array('ok' => false, 'error' => 'Клієнт: ' . $client['error']);
        }
        $recipientUuid = isset($client['data']['uuid']) ? $client['data']['uuid'] : null;
        if (!$recipientUuid) return array('ok' => false, 'error' => 'Не отримано uuid клієнта', 'raw' => $client['raw']);

        // ── 3. Shipment ──────────────────────────────────────────────────────
        $senderUuid        = UpDefaults::senderUuid();
        $senderAddressId   = UpDefaults::senderAddressId();
        $returnAddressId   = UpDefaults::returnAddressId();
        $shipmentType      = !empty($p['type']) ? strtoupper((string)$p['type']) : UpDefaults::shipmentType();
        $paidByRecipient   = true;
        if (isset($p['paid_by'])) {
            $paidByRecipient = (strtolower($p['paid_by']) === 'recipient');
        }

        $weightKg  = (float)(isset($p['weight']) && $p['weight'] !== '' ? $p['weight'] : UpDefaults::weight());
        $length    = (int)  (isset($p['length']) && $p['length'] !== '' ? $p['length'] : UpDefaults::length());
        $width     = (int)  (isset($p['width'])  && $p['width']  !== '' ? $p['width']  : UpDefaults::width());
        $height    = (int)  (isset($p['height']) && $p['height'] !== '' ? $p['height'] : UpDefaults::height());
        $seats     = max(1, (int)(isset($p['seats']) ? $p['seats'] : 1));
        $desc      = isset($p['description']) && $p['description'] !== '' ? (string)$p['description'] : UpDefaults::description();
        if (mb_strlen($desc) > 40) $desc = mb_substr($desc, 0, 40);
        $sum       = (float)(isset($p['declared_price']) ? $p['declared_price'] : 200);
        if ($sum <= 0) $sum = 200;
        $postPay   = (float)(isset($p['post_pay']) ? $p['post_pay'] : 0);

        $parcels = array();
        if (!empty($p['seats_description'])) {
            $parcels = self::parseSeats($p['seats_description'], $sum);
            $seats   = count($parcels) ?: $seats;
        }
        if (!$parcels) {
            if ($seats === 1) {
                $parcels[] = array(
                    'name'          => 'Канцелярські товари',
                    'weight'        => (int)round($weightKg * 1000),
                    'length'        => $length,
                    'width'         => $width,
                    'height'        => $height,
                    'declaredPrice' => $sum,
                );
            } else {
                for ($i = 1; $i <= $seats; $i++) {
                    $parcels[] = array(
                        'name'          => 'Канцелярські товари ' . $i,
                        'weight'        => (int)round($weightKg * 1000 / $seats),
                        'length'        => $length,
                        'width'         => $width,
                        'height'        => $height,
                        'declaredPrice' => round($sum / $seats, 2),
                        'parcelNumber'  => $i,
                    );
                }
                $shipmentType = 'EXPRESS';
            }
        }

        $shipmentBody = array(
            'sender'           => array('uuid' => $senderUuid),
            'recipient'        => array('uuid' => $recipientUuid),
            'senderAddressId'  => $senderAddressId,
            'returnAddressId'  => $returnAddressId,
            'deliveryType'     => $deliveryType,
            'paidByRecipient'  => $paidByRecipient,
            'weight'           => (int)round($weightKg * 1000),
            'length'           => $length,
            'width'            => $width,
            'height'           => $height,
            'description'      => $desc,
            'type'             => $shipmentType,
            'checkOnDelivery'  => 1,
            'onFailReceiveType'=> UpDefaults::onFailReceiveType(),
            'returnAfterStorageDays' => (int)UpDefaults::returnAfterStorageDays(),
            'parcels'          => $parcels,
        );

        if ($postPay > 0) {
            $shipmentBody['postPay'] = $postPay;
            $shipmentBody['transferPostPayToBankAccount'] = 1;
            $shipmentBody['postPayPaidByRecipient']       = 1;
        }

        $ship = $api->createShipment($shipmentBody);
        if (!$ship['ok']) {
            return array('ok' => false, 'error' => 'Shipment: ' . $ship['error']);
        }
        $shipmentData = $ship['data'];
        $uuid    = isset($shipmentData['uuid']) ? $shipmentData['uuid'] : '';
        $barcode = isset($shipmentData['parcels'][0]['barcode']) ? $shipmentData['parcels'][0]['barcode'] : '';
        if (!$uuid || !$barcode) {
            return array('ok' => false, 'error' => 'Не отримано uuid/barcode відправлення', 'raw' => $ship['raw']);
        }

        // ── 4. Sticker ───────────────────────────────────────────────────────
        $labelUrl = self::downloadSticker($api, $barcode);

        // ── 5. Persist to ttn_ukrposhta ──────────────────────────────────────
        $row = self::mapShipmentToRow($shipmentData, $senderUuid, $senderAddressId, $returnAddressId);
        if ($labelUrl) $row['label'] = $labelUrl;
        if (!empty($p['customerorder_id'])) $row['customerorder_id'] = (int)$p['customerorder_id'];
        if (!empty($p['demand_id']))        $row['demand_id']        = (int)$p['demand_id'];
        if (!empty($p['id_order']))  $row['id_order']  = $p['id_order'];
        if (!empty($p['id_demand'])) $row['id_demand'] = $p['id_demand'];
        if (!empty($p['id_agent']))  $row['id_agent']  = $p['id_agent'];
        if (!empty($p['id_owner']))  $row['id_owner']  = $p['id_owner'];

        $ttnId = UpTtnRepository::save($row);

        // Link to customerorder via document_link (matches NP behaviour, used by flow views)
        if (!empty($p['customerorder_id']) && $ttnId) {
            self::linkDocument($ttnId, (int)$p['customerorder_id'], 'customerorder');
        }
        if (!empty($p['demand_id']) && $ttnId) {
            self::linkDocument($ttnId, (int)$p['demand_id'], 'demand');
        }

        return array(
            'ok'       => true,
            'ttn_id'   => $ttnId,
            'uuid'     => $uuid,
            'barcode'  => $barcode,
            'label'    => $labelUrl,
            'delivery_price' => isset($shipmentData['deliveryPrice']) ? $shipmentData['deliveryPrice'] : null,
            'delivery_date'  => isset($shipmentData['deliveryDate'])  ? $shipmentData['deliveryDate']  : null,
        );
    }

    /**
     * Update an existing TTN (limited — UP allows PUT /shipments/barcode/{barcode}
     * with partial body). We only support changing weight, parcels, description.
     */
    public static function update($ttnId, array $changes)
    {
        $ttn = UpTtnRepository::getById($ttnId);
        if (!$ttn) return array('ok' => false, 'error' => 'TTN not found');
        if (!$ttn['barcode']) return array('ok' => false, 'error' => 'TTN has no barcode yet');
        if (in_array($ttn['lifecycle_status'], UpTtnRepository::$FINAL_STATES, true)) {
            return array('ok' => false, 'error' => 'Статус "' . $ttn['lifecycle_status'] . '" не дозволяє редагування');
        }

        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'API not configured');

        $body = array();
        if (isset($changes['description'])) $body['description'] = mb_substr((string)$changes['description'], 0, 40);
        if (isset($changes['weight']))      $body['weight']      = (int)round(((float)$changes['weight']) * 1000);
        if (isset($changes['length']))      $body['length']      = (int)$changes['length'];
        if (isset($changes['width']))       $body['width']       = (int)$changes['width'];
        if (isset($changes['height']))      $body['height']      = (int)$changes['height'];
        if (isset($changes['declared_price'])) {
            $body['parcels'] = array(array('declaredPrice' => (float)$changes['declared_price']));
        }

        if ($body) {
            $r = $api->updateShipment($ttn['barcode'], $body);
            if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);
        }

        // Patch local row directly (also accept manual link changes)
        $local = array();
        foreach (array('description','weight','length','width','height','declaredPrice','postPayUah','customerorder_id','demand_id') as $f) {
            $key = strtolower(preg_replace('/([A-Z])/', '_$1', $f));
            if (isset($changes[$key])) $local[$f] = $changes[$key];
            elseif (isset($changes[$f])) $local[$f] = $changes[$f];
        }
        if (isset($changes['weight']))      $local['weight']        = (int)round(((float)$changes['weight']) * 1000);
        if (isset($changes['declared_price'])) $local['declaredPrice'] = (float)$changes['declared_price'];
        if (isset($changes['post_pay']))    $local['postPayUah']    = (float)$changes['post_pay'];
        if ($local) UpTtnRepository::updateById($ttnId, $local);

        return array('ok' => true);
    }

    /**
     * Delete TTN — спочатку віддалено в UP, потім локально.
     *
     * Правило: видаляємо з нашої БД ТІЛЬКИ при успішній відповіді UP API
     * (200 OK або 404 — тобто посилка вже не існує на сервері). Будь-яка
     * інша помилка = лишаємо рядок як є і повертаємо error, щоб користувач
     * бачив реальну причину (наприклад, «в реєстрі», «закрита група»).
     *
     * Якщо ТТН прив'язана до реєстру — спершу віддалено відключаємо через
     * removeShipmentFromGroup, і лише після цього DELETE /shipments/{uuid}.
     */
    public static function delete($ttnId)
    {
        $ttn = UpTtnRepository::getById($ttnId);
        if (!$ttn) return array('ok' => false, 'error' => 'TTN not found');

        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'API not configured');

        $uuid = !empty($ttn['uuid']) ? $ttn['uuid'] : '';

        // Якщо є uuid — працюємо з UP.
        if ($uuid) {
            // 1. Якщо ТТН у реєстрі — спершу знімаємо з групи на UP-стороні.
            $groupUuid = UpGroupLinkRepository::getGroupUuid($uuid);
            if ($groupUuid) {
                $rm = $api->removeShipmentFromGroup($uuid);
                if (!$rm['ok'] && (int)$rm['http'] !== 404) {
                    return array(
                        'ok'    => false,
                        'error' => 'Не вдалося відʼєднати від реєстру на UP: ' . $rm['error'],
                    );
                }
                // Локальний лінк знімаємо лише після успіху API.
                UpGroupLinkRepository::unlinkShipment($uuid);
            }

            // 2. DELETE /shipments/{uuid}
            $r = $api->deleteShipment($uuid);
            if (!$r['ok'] && (int)$r['http'] !== 404) {
                // API відмовив (ТТН уже в роботі перевізника, закритий реєстр тощо)
                // → НЕ чіпаємо локальний рядок, повертаємо помилку.
                return array('ok' => false, 'error' => $r['error']);
            }
        }

        // 3. API-сторону закрили (або UUID не було зовсім) — чистимо локально.
        UpGroupLinkRepository::unlinkShipment($uuid);
        UpTtnRepository::deleteById($ttnId);
        self::unlinkDocument($ttnId);
        return array('ok' => true);
    }

    /**
     * Refresh a single TTN from Ukrposhta API (re-fetch shipment record).
     */
    public static function refresh($ttnId)
    {
        $ttn = UpTtnRepository::getById($ttnId);
        if (!$ttn) return array('ok' => false, 'error' => 'TTN not found');

        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'API not configured');

        $r = $api->ecom('GET', 'shipments/' . rawurlencode($ttn['uuid']), null,
            array('token' => '')); // token will be overridden by default user token inside ecom()
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        $row = self::mapShipmentToRow($r['data'],
            $ttn['sender_uuid'], $ttn['senderAddressId'], $ttn['returnAddressId']);
        $row['customerorder_id'] = $ttn['customerorder_id'];
        $row['demand_id']        = $ttn['demand_id'];
        UpTtnRepository::updateById($ttnId, $row);
        return array('ok' => true, 'data' => $r['data']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Parse "L;W;H;weight/L;W;H;weight" per-seat description.
     */
    private static function parseSeats($input, $totalSum)
    {
        $seats  = array();
        $places = explode('/', (string)$input);
        $n = max(1, count($places));
        $i = 0;
        foreach ($places as $place) {
            $parts = explode(';', $place);
            if (count($parts) < 4) continue;
            $i++;
            list($length, $width, $height, $weight) = $parts;
            $seats[] = array(
                'name'          => 'Канцелярські товари ' . $i,
                'length'        => (float)$length,
                'width'         => (float)$width,
                'height'        => (float)$height,
                'weight'        => (int)round(((float)$weight) * 1000),
                'declaredPrice' => round($totalSum / $n, 2),
            );
        }
        return $seats;
    }

    private static function checkPhone($phone)
    {
        $digits = preg_replace('/[^0-9]/', '', (string)$phone);
        if ($digits === '') return '';
        if (strlen($digits) === 10 && $digits[0] === '0') $digits = '38' . $digits;
        if (strlen($digits) === 9)                         $digits = '380' . $digits;
        if (strlen($digits) !== 12)                        return '';
        return $digits;
    }

    /**
     * Map API shipment response → ttn_ukrposhta row.
     * Mirrors the flattenObject() behaviour from /var/sqript/Ukrposhta/send_ttn_urk.php.
     */
    public static function mapShipmentToRow(array $d, $senderUuid = null, $senderAddressId = null, $returnAddressId = null)
    {
        $sender    = isset($d['sender'])    && is_array($d['sender'])    ? $d['sender']    : array();
        $recipient = isset($d['recipient']) && is_array($d['recipient']) ? $d['recipient'] : array();
        $parcels   = isset($d['parcels'])   && is_array($d['parcels'])   ? $d['parcels']   : array();
        $p0        = isset($parcels[0]) ? $parcels[0] : array();

        $senderAddr = isset($sender['addresses'][0]['address']) && is_array($sender['addresses'][0]['address'])
                    ? $sender['addresses'][0]['address'] : array();
        $rcpAddr    = isset($recipient['addresses'][0]['address']) && is_array($recipient['addresses'][0]['address'])
                    ? $recipient['addresses'][0]['address'] : array();

        $row = array(
            'uuid'                 => isset($d['uuid']) ? $d['uuid'] : '',
            'type'                 => isset($d['type']) ? $d['type'] : null,
            'barcode'              => isset($p0['barcode']) ? $p0['barcode'] : null,

            'sender_uuid'          => $senderUuid ?: (isset($sender['uuid']) ? $sender['uuid'] : null),
            'sender_name'          => isset($sender['name']) ? $sender['name'] : null,
            'senderAddressId'      => $senderAddressId !== null ? $senderAddressId
                                     : (isset($d['senderAddressId']) ? $d['senderAddressId'] : null),
            'returnAddressId'      => $returnAddressId !== null ? $returnAddressId
                                     : (isset($d['returnAddressId']) ? $d['returnAddressId'] : null),

            'recipient_uuid'       => isset($recipient['uuid']) ? $recipient['uuid'] : null,
            'recipient_name'       => isset($recipient['name']) ? $recipient['name'] : null,
            'recipient_phoneNumber'=> isset($recipient['phoneNumber']) ? $recipient['phoneNumber'] : null,
            'recipientAddressId'   => isset($d['recipientAddressId']) ? $d['recipientAddressId']
                                    : (isset($rcpAddr['id']) ? $rcpAddr['id'] : null),

            'sender_phoneNumber'   => isset($sender['phoneNumber']) ? $sender['phoneNumber'] : null,
            'sender_city'          => isset($senderAddr['city']) ? $senderAddr['city'] : null,
            'recipient_city'       => isset($rcpAddr['city'])    ? $rcpAddr['city']    : null,
            'postcode'             => isset($rcpAddr['postcode']) ? $rcpAddr['postcode'] : null,

            'deliveryType'         => isset($d['deliveryType']) ? $d['deliveryType'] : null,
            'weight'               => isset($d['weight']) ? (string)$d['weight'] : (isset($p0['weight']) ? (string)$p0['weight'] : null),
            'length'               => isset($d['length']) ? (string)$d['length'] : null,
            'width'                => isset($d['width'])  ? (string)$d['width']  : null,
            'height'               => isset($d['height']) ? (string)$d['height'] : null,

            'declaredPrice'        => isset($p0['declaredPrice']) ? (float)$p0['declaredPrice']
                                     : (isset($d['declaredPrice']) ? (float)$d['declaredPrice'] : 0),
            'deliveryPrice'        => isset($d['deliveryPrice']) ? (float)$d['deliveryPrice'] : 0,
            'postPayUah'           => isset($d['postPay']) ? (float)$d['postPay'] : 0,
            'description'          => isset($d['description']) ? $d['description'] : null,

            'lifecycle_status'     => isset($d['lifecycle']['status']) ? $d['lifecycle']['status']
                                     : (isset($d['lifecycle_status']) ? $d['lifecycle_status'] : 'CREATED'),
            'lifecycle_statusDate' => self::normalizeDate(
                isset($d['lifecycle']['statusDate']) ? $d['lifecycle']['statusDate']
              : (isset($d['lifecycle_statusDate']) ? $d['lifecycle_statusDate']
              : (isset($d['createDate']) ? $d['createDate'] : null))
            ),
            'created_date'         => self::normalizeDate(
                isset($d['createDate']) ? $d['createDate']
              : (isset($d['lifecycle']['statusDate']) ? $d['lifecycle']['statusDate'] : null)
            ) ?: date('Y-m-d H:i:s'),
            'lastModified'         => date('Y-m-d H:i:s'),
        );

        return $row;
    }

    private static function normalizeDate($v)
    {
        if (!$v) return null;
        $ts = is_numeric($v) ? (int)$v : strtotime((string)$v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    /**
     * Download sticker PDF to /docum/Ukrpost/{YYYY_MM}/{barcode}.pdf → return public URL.
     */
    public static function downloadSticker(UkrposhtaApi $api, $barcode, $date = null)
    {
        $barcode = preg_replace('/[^A-Za-z0-9]/', '', (string)$barcode);
        if (!$barcode) return '';

        $yearMonth = $date ? date('Y_m', strtotime($date)) : date('Y_m');
        $dir = self::LABEL_DIR_FS . '/' . $yearMonth;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = $dir . '/' . $barcode . '.pdf';

        $r = $api->getSticker($barcode);
        if (!$r['ok'] || !$r['raw']) return '';
        @file_put_contents($path, $r['raw']);
        if (filesize($path) < 1000) { @unlink($path); return ''; }

        return self::LABEL_URL_BASE . '/' . $yearMonth . '/' . $barcode . '.pdf';
    }

    // ── document_link helper (matches NP pattern, from_type='ttn_up') ───────

    private static function linkDocument($ttnId, $entityId, $entityType)
    {
        if (!$ttnId || !$entityId) return;
        $r = \Database::fetchRow('Papir', "SHOW TABLES LIKE 'document_link'");
        if (!($r['ok'] && $r['row'])) return;
        \Database::execute('Papir',
            "INSERT IGNORE INTO document_link (from_type, from_id, to_type, to_id, created_at)
             VALUES ('ttn_up', " . (int)$ttnId . ", '" . \Database::escape('Papir', $entityType) . "', " . (int)$entityId . ", NOW())");
    }

    private static function unlinkDocument($ttnId)
    {
        if (!$ttnId) return;
        $r = \Database::fetchRow('Papir', "SHOW TABLES LIKE 'document_link'");
        if (!($r['ok'] && $r['row'])) return;
        \Database::execute('Papir',
            "DELETE FROM document_link WHERE from_type = 'ttn_up' AND from_id = " . (int)$ttnId);
    }
}