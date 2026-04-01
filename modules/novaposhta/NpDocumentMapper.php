<?php
namespace Papir\Crm;

/**
 * Maps NovaPoshta InternetDocument.getDocumentList API response
 * fields to ttn_novaposhta DB columns.
 */
class NpDocumentMapper
{
    /**
     * Parse NP date string → MySQL datetime or null.
     * Handles ISO "2026-04-01 16:28:45", "dd.mm.yyyy", and filters "0001-01-01" nulls.
     */
    public static function parseDate($str)
    {
        if (!$str) return null;
        // NP sometimes returns zeroed dates
        if (substr($str, 0, 4) === '0001') return null;

        $ts = strtotime($str);
        if ($ts && $ts > 0) return date('Y-m-d H:i:s', $ts);

        // dd.mm.yyyy[ HH:ii:ss]
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2}):(\d{2}))?/', $str, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1]
                 . (isset($m[4]) ? ' ' . $m[4] . ':' . $m[5] . ':' . $m[6] : ' 00:00:00');
        }
        return null;
    }

    /**
     * Build the date range params for getDocumentList call.
     * NP expects only d.m.Y (no time component).
     *
     * @param int|null $days   last N calendar days (null if $hours set)
     * @param int|null $hours  last N hours
     * @return array ['DateTimeFrom'=>'dd.mm.yyyy', 'DateTimeTo'=>'dd.mm.yyyy']
     */
    public static function buildDateRange($days, $hours)
    {
        $dateTo = date('d.m.Y');
        if ($hours !== null) {
            $dateFrom = date('d.m.Y', strtotime('-' . (int)$hours . ' hours'));
        } else {
            $dateFrom = date('d.m.Y', strtotime('-' . (int)$days . ' days'));
        }
        return array('DateTimeFrom' => $dateFrom, 'DateTimeTo' => $dateTo);
    }

    /**
     * Map a single getDocumentList item to ttn_novaposhta columns.
     * Returns an array suitable for INSERT or UPDATE.
     *
     * @param array  $doc        Single item from NP API data[]
     * @param string $senderRef  np_sender.Ref of the sender
     * @return array|null  null if Ref is missing
     */
    public static function map($doc, $senderRef)
    {
        $npRef = isset($doc['Ref']) ? $doc['Ref'] : '';
        if (!$npRef) return null;

        // Scan sheet ref: try UUID field first, then look up by Number
        $scanSheetRef = null;
        if (!empty($doc['ScanSheetREF'])) $scanSheetRef = $doc['ScanSheetREF'];
        if (!empty($doc['ScanSheetRef'])) $scanSheetRef = $doc['ScanSheetRef'];
        if (!$scanSheetRef && !empty($doc['ScanSheetNumber'])) {
            $ssNum = \Database::escape('Papir', $doc['ScanSheetNumber']);
            $r = \Database::fetchRow('Papir',
                "SELECT Ref FROM np_scan_sheets WHERE Number = '{$ssNum}' LIMIT 1");
            if ($r['ok'] && $r['row']) $scanSheetRef = $r['row']['Ref'];
        }

        // COD: NP's AfterpaymentOnGoodsCost is the накладний платіж displayed in our UI
        $cod = isset($doc['AfterpaymentOnGoodsCost']) && $doc['AfterpaymentOnGoodsCost'] != ''
            ? (float)$doc['AfterpaymentOnGoodsCost'] : null;

        // Prefer EstimatedDeliveryDate, fall back to ScheduledDeliveryDate
        $estDelivery = self::parseDate(
            !empty($doc['EstimatedDeliveryDate']) ? $doc['EstimatedDeliveryDate'] :
           (!empty($doc['ScheduledDeliveryDate']) ? $doc['ScheduledDeliveryDate'] : null)
        );

        $moment = self::parseDate(isset($doc['DateTime']) ? $doc['DateTime'] : null);
        if (!$moment) $moment = self::parseDate(isset($doc['EWDateCreated']) ? $doc['EWDateCreated'] : null);

        $recipientName = '';
        if (!empty($doc['RecipientContactPerson'])) $recipientName = $doc['RecipientContactPerson'];
        elseif (!empty($doc['RecipientFullName']))   $recipientName = $doc['RecipientFullName'];

        return array(
            'ref'                       => $npRef,
            'int_doc_number'            => isset($doc['IntDocNumber'])              ? $doc['IntDocNumber']              : null,
            'moment'                    => $moment,
            'ew_date_created'           => self::parseDate(isset($doc['EWDateCreated'])         ? $doc['EWDateCreated']         : null),
            'estimated_delivery_date'   => $estDelivery,
            'date_first_day_storage'    => self::parseDate(isset($doc['DateFirstDayStorage'])   ? $doc['DateFirstDayStorage']   : null),
            'arrived'                   => self::parseDate(isset($doc['RecipientDateTime'])     ? $doc['RecipientDateTime']     : null),
            'state_id'                  => isset($doc['StateId'])                   ? (int)$doc['StateId']               : null,
            'state_name'                => !empty($doc['StateName'])                ? $doc['StateName']                  : null,
            'state_define'              => isset($doc['StateId'])                   ? (int)$doc['StateId']               : null,
            'date_last_updated_status'  => self::parseDate(isset($doc['DateLastUpdatedStatus']) ? $doc['DateLastUpdatedStatus'] : null),
            'recipient_contact_person'  => $recipientName                          ?: null,
            'city_recipient_desc'       => !empty($doc['CityRecipientDescription']) ? $doc['CityRecipientDescription']  : null,
            'recipients_phone'          => !empty($doc['RecipientsPhone'])          ? $doc['RecipientsPhone']            : null,
            'recipient_address_desc'    => !empty($doc['RecipientAddressDescription']) ? $doc['RecipientAddressDescription'] : null,
            'city_sender_desc'          => !empty($doc['CitySenderDescription'])    ? $doc['CitySenderDescription']      : null,
            'sender_contact_person'     => !empty($doc['SenderContactPerson'])      ? $doc['SenderContactPerson']        : null,
            'phone_sender'              => !empty($doc['SendersPhone'])             ? $doc['SendersPhone']               : null,
            'cost'                      => isset($doc['Cost'])      && $doc['Cost']      !== '' ? (float)$doc['Cost']      : null,
            'cost_on_site'              => isset($doc['CostOnSite']) && $doc['CostOnSite'] !== '' ? (float)$doc['CostOnSite'] : null,
            'backward_delivery_money'   => $cod,
            'afterpayment_on_goods_cost'=> $cod,
            'weight'                    => isset($doc['Weight'])      && $doc['Weight']      !== '' ? (float)$doc['Weight']      : null,
            'seats_amount'              => isset($doc['SeatsAmount']) && $doc['SeatsAmount'] !== '' ? (int)$doc['SeatsAmount']   : null,
            'service_type'              => !empty($doc['ServiceType'])   ? $doc['ServiceType']   : null,
            'payment_method'            => !empty($doc['PaymentMethod']) ? $doc['PaymentMethod'] : null,
            'payer_type'                => !empty($doc['PayerType'])     ? $doc['PayerType']     : null,
            'sender_ref'                => $senderRef,
            'scan_sheet_ref'            => $scanSheetRef,
            'deletion_mark'             => (isset($doc['DeletionMark']) && $doc['DeletionMark'] == '1') ? 1 : 0,
            'updated_at'                => date('Y-m-d H:i:s'),
        );
    }

    /**
     * Fields that are safe to UPDATE on existing records.
     * Excludes: customerorder_id, demand_id, id_ms_order, id_ms_demand
     * scan_sheet_ref is handled separately (only set if DB value is null)
     */
    public static function updateFields($mapped, $existingScanSheetRef)
    {
        $upd = array(
            'int_doc_number'           => $mapped['int_doc_number'],
            'ew_date_created'          => $mapped['ew_date_created'],
            'state_id'                 => $mapped['state_id'],
            'state_name'               => $mapped['state_name'],
            'state_define'             => $mapped['state_define'],
            'date_last_updated_status' => $mapped['date_last_updated_status'],
            'estimated_delivery_date'  => $mapped['estimated_delivery_date'],
            'date_first_day_storage'   => $mapped['date_first_day_storage'],
            'arrived'                  => $mapped['arrived'],
            'recipient_contact_person' => $mapped['recipient_contact_person'],
            'city_recipient_desc'      => $mapped['city_recipient_desc'],
            'recipients_phone'         => $mapped['recipients_phone'],
            'recipient_address_desc'   => $mapped['recipient_address_desc'],
            'city_sender_desc'         => $mapped['city_sender_desc'],
            'sender_contact_person'    => $mapped['sender_contact_person'],
            'phone_sender'             => $mapped['phone_sender'],
            'cost'                     => $mapped['cost'],
            'cost_on_site'             => $mapped['cost_on_site'],
            'backward_delivery_money'  => $mapped['backward_delivery_money'],
            'afterpayment_on_goods_cost'=> $mapped['afterpayment_on_goods_cost'],
            'weight'                   => $mapped['weight'],
            'seats_amount'             => $mapped['seats_amount'],
            'service_type'             => $mapped['service_type'],
            'payment_method'           => $mapped['payment_method'],
            'payer_type'               => $mapped['payer_type'],
            'updated_at'               => date('Y-m-d H:i:s'),
        );
        // scan_sheet_ref: set only when NP has a value and DB currently has none
        if ($mapped['scan_sheet_ref'] && !$existingScanSheetRef) {
            $upd['scan_sheet_ref'] = $mapped['scan_sheet_ref'];
        }
        return $upd;
    }
}