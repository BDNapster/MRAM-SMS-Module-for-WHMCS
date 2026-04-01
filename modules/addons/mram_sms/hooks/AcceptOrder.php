<?php
if (!defined("WHMCS")) die();
use WHMCS\Database\Capsule;

add_hook('AcceptOrder', 1, function($vars) {
    $orderId = $vars['orderid'];
    try {
        $order = Capsule::table('tblorders')->where('id', $orderId)->first();
        if (!$order) return;
        $client = mram_sms_get_client_details($order->userid);
        $phone = mram_sms_get_client_phone($order->userid);
        mram_sms_send_notification('AcceptOrder', $order->userid, $phone, [
            'client_name' => $client['client_name'],
            'order_id' => $orderId,
            'order_num' => $order->ordernum,
            'amount' => $order->amount,
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS AcceptOrder error: " . $e->getMessage()); }
});
