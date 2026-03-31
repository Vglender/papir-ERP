<?php
/**
 * POST /counterparties/api/delete_order_delivery
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('ok'=>false,'error'=>'POST required')); exit; }
if (!\Papir\Crm\AuthService::isLoggedIn())  { echo json_encode(array('ok'=>false,'error'=>'Unauthorized'));   exit; }

$id      = isset($_POST['id'])               ? (int)$_POST['id']               : 0;
$orderId = isset($_POST['customerorder_id']) ? (int)$_POST['customerorder_id'] : 0;
if ($id <= 0 || $orderId <= 0) { echo json_encode(array('ok'=>false,'error'=>'Invalid params')); exit; }

$r = \Database::update('Papir','order_delivery', array('status'=>'cancelled'), array('id'=>$id,'customerorder_id'=>$orderId));
if (!$r['ok']) { echo json_encode(array('ok'=>false,'error'=>'DB error')); exit; }

$rOrd = \Database::fetchRow('Papir',"SELECT status FROM customerorder WHERE id={$orderId} AND deleted_at IS NULL");
if (!$rOrd['ok'] || empty($rOrd['row'])) { echo json_encode(array('ok'=>true,'reverted'=>false)); exit; }
$orderStatus = $rOrd['row']['status'];

if ($orderStatus === 'shipped' || $orderStatus === 'partially_shipped') {
    $activeCnt = 0;
    $rOdl = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM order_delivery od
         JOIN delivery_method dm ON dm.id=od.delivery_method_id
         WHERE od.customerorder_id={$orderId} AND od.status IN ('sent','delivered') AND dm.has_ttn=0");
    if ($rOdl['ok'] && !empty($rOdl['row'])) $activeCnt += (int)$rOdl['row']['cnt'];

    $rNp = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM document_link dl
         JOIN ttn_novaposhta tn ON tn.id=dl.from_id
         WHERE dl.from_type='ttn_np' AND dl.to_type='customerorder' AND dl.to_id={$orderId}
           AND (tn.deletion_mark IS NULL OR tn.deletion_mark=0)
           AND tn.state_define NOT IN (102,105)
           AND LOWER(tn.state_name) NOT LIKE '%відмов%'
           AND LOWER(tn.state_name) NOT LIKE '%отказ%'");
    if ($rNp['ok'] && !empty($rNp['row'])) $activeCnt += (int)$rNp['row']['cnt'];

    $rUp = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM document_link dl
         JOIN ttn_ukrposhta tu ON tu.id=dl.from_id
         WHERE dl.from_type='ttn_up' AND dl.to_type='customerorder' AND dl.to_id={$orderId}
           AND tu.lifecycle_status NOT IN ('RETURNED','RETURNING','CANCELLED','DELETED')");
    if ($rUp['ok'] && !empty($rUp['row'])) $activeCnt += (int)$rUp['row']['cnt'];

    if ($activeCnt === 0) {
        \Database::update('Papir','customerorder',array('status'=>'in_progress'),array('id'=>$orderId));
        \Database::insert('Papir','customerorder_history',array(
            'customerorder_id'=>$orderId,'event_type'=>'status_change','field_name'=>'status',
            'old_value'=>$orderStatus,'new_value'=>'in_progress','is_auto'=>1,
            'comment'=>'Автоматичний відкат: видалено останню активну доставку',
        ));
        \Papir\Crm\AuthService::log('status_change','customerorder',$orderId,'in_progress');
        echo json_encode(array('ok'=>true,'reverted'=>true,'new_status'=>'in_progress')); exit;
    }
}
echo json_encode(array('ok'=>true,'reverted'=>false));
