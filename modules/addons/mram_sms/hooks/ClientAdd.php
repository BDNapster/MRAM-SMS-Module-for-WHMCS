<?php
if (!defined("WHMCS")) die();

add_hook('ClientAdd', 1, function($vars) {
    try {
        $client = mram_sms_get_client_details($vars['userid']);
        $phone = mram_sms_get_client_phone($vars['userid']);
        mram_sms_send_notification('ClientAdd', $vars['userid'], $phone, [
            'client_name' => $client['client_name'],
            'email' => $client['email'],
            'phone' => $client['phone'],
            'company' => $client['company'],
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS ClientAdd error: " . $e->getMessage()); }
});
