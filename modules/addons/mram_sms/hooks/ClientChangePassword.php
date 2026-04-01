<?php
if (!defined("WHMCS")) die();

add_hook('ClientChangePassword', 1, function($vars) {
    try {
        $client = mram_sms_get_client_details($vars['userid']);
        $phone = mram_sms_get_client_phone($vars['userid']);
        mram_sms_send_notification('ClientChangePassword', $vars['userid'], $phone, [
            'client_name' => $client['client_name'],
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS ClientChangePassword error: " . $e->getMessage()); }
});
