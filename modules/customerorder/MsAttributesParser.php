<?php
/**
 * Parses МойСклад customerorder attributes into Papir delivery_method_id / payment_method_id.
 */
class MsAttributesParser
{
    private $map;

    public function __construct()
    {
        $this->map = require __DIR__ . '/ms_attributes_map.php';
    }

    /**
     * Parse attributes array from МС customerorder document.
     * Returns array with keys: delivery_method_id (int|null), payment_method_id (int|null)
     */
    public function parse(array $attributes)
    {
        $deliveryMsId = $this->map['delivery_method_ms_id'];
        $paymentMsId  = $this->map['payment_method_ms_id'];

        $deliveryValue = null;
        $paymentValue  = null;

        foreach ($attributes as $attr) {
            $attrId = isset($attr['id']) ? (string)$attr['id'] : '';
            $val    = isset($attr['value']) ? trim((string)$attr['value']) : '';
            if ($val === '') continue;
            if ($attrId === $deliveryMsId) $deliveryValue = $val;
            if ($attrId === $paymentMsId)  $paymentValue  = $val;
        }

        return array(
            'delivery_method_id' => $this->resolveDelivery($deliveryValue),
            'payment_method_id'  => $this->resolvePayment($paymentValue),
        );
    }

    private function resolveDelivery($value)
    {
        if ($value === null || $value === '') return null;
        $key = mb_strtolower(trim($value), 'UTF-8');
        $map = $this->map['delivery_map'];
        if (isset($map[$key])) return (int)$map[$key];
        // partial match fallback
        foreach ($map as $needle => $id) {
            if (mb_strpos($key, $needle, 0, 'UTF-8') !== false
                || mb_strpos($needle, $key, 0, 'UTF-8') !== false) {
                return (int)$id;
            }
        }
        return null;
    }

    private function resolvePayment($value)
    {
        if ($value === null || $value === '') return null;
        $key = mb_strtolower(trim($value), 'UTF-8');
        $map = $this->map['payment_map'];
        if (isset($map[$key])) {
            $code = $map[$key];
        } else {
            $code = null;
            foreach ($map as $needle => $c) {
                if (mb_strpos($key, $needle, 0, 'UTF-8') !== false) { $code = $c; break; }
            }
        }
        if ($code === null) return null;

        $r = Database::fetchRow('Papir',
            "SELECT id FROM payment_method WHERE code = '" . Database::escape('Papir', $code) . "' LIMIT 1");
        return ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['id'] : null;
    }
}
