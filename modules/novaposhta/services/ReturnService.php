<?php
namespace Papir\Crm;

/**
 * Returns (повернення) via NP AdditionalService API.
 */
class ReturnService
{
    /**
     * Check if a return is possible for a given TTN.
     */
    public static function checkPossibility($ttnId)
    {
        $ttn = TtnRepository::getById($ttnId);
        if (!$ttn || !$ttn['sender_api']) {
            return array('ok' => false, 'error' => 'TTN not found');
        }

        $np = new NovaPoshta($ttn['sender_api']);
        $r  = $np->call('AdditionalService', 'checkPossibilityCreateReturn',
            array('Number' => $ttn['int_doc_number']));

        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        $data = isset($r['data'][0]) ? $r['data'][0] : array();
        $possible = !empty($data['Possible']);

        return array(
            'ok'        => true,
            'possible'  => $possible,
            'data'      => $data,
        );
    }

    /**
     * Create a return order.
     *
     * @param int    $ttnId
     * @param string $returnAddressRef   Warehouse ref to return to (sender's address)
     * @param string $payerType          Sender|Recipient
     * @param string $paymentMethod      Cash|NonCash
     * @param string $returnReasonRef    UUID from getReturnReasons()
     * @param string $subtypeReasonRef   UUID from getReturnReasonsSubtypes()
     */
    public static function create($ttnId, $params)
    {
        $ttn = TtnRepository::getById($ttnId);
        if (!$ttn || !$ttn['sender_api']) {
            return array('ok' => false, 'error' => 'TTN not found');
        }

        $np = new NovaPoshta($ttn['sender_api']);

        $returnProps = array(
            'Number'             => $ttn['int_doc_number'],
            'PayerType'          => isset($params['payer_type'])        ? $params['payer_type']        : 'Recipient',
            'PaymentMethod'      => isset($params['payment_method'])    ? $params['payment_method']    : 'Cash',
            'Recipient'          => isset($params['recipient_ref'])     ? $params['recipient_ref']     : $ttn['sender_ref'],
            'RecipientContactPerson' => isset($params['contact_ref'])   ? $params['contact_ref']       : '',
            'RecipientAddressRef'=> isset($params['return_address_ref'])? $params['return_address_ref']: '',
            'ServiceType'        => isset($params['service_type'])      ? $params['service_type']      : 'WarehouseWarehouse',
            'ReturnAddressRef'   => isset($params['return_address_ref'])? $params['return_address_ref']: '',
        );

        if (!empty($params['return_reason_ref'])) {
            $returnProps['Reason'] = $params['return_reason_ref'];
        }
        if (!empty($params['subtype_reason_ref'])) {
            $returnProps['SubtypeReason'] = $params['subtype_reason_ref'];
        }

        $r = $np->call('AdditionalService', 'save', $returnProps);
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        return array('ok' => true, 'data' => isset($r['data'][0]) ? $r['data'][0] : array());
    }

    /**
     * Get return reason codes for UI.
     */
    public static function getReasons($senderRef)
    {
        $sender = SenderRepository::getByRef($senderRef);
        if (!$sender) return array('ok' => false, 'error' => 'Sender not found');

        $np = new NovaPoshta($sender['api']);
        $r  = $np->call('AdditionalService', 'getReturnReasons', array());
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        return array('ok' => true, 'data' => $r['data']);
    }
}