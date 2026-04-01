<?php
if (!defined("WHMCS")) die();

add_hook('ClientAreaRegister', 1, function($vars) {
    try {
        $client = mram_sms_get_client_details($vars['userid']);
        $phone = mram_sms_get_client_phone($vars['userid']);
        $whmcsUrl = \WHMCS\Config\Setting::getValue('SystemURL') ?: '';
        mram_sms_send_notification('ClientAreaRegister', $vars['userid'], $phone, [
            'client_name' => $client['client_name'],
            'email' => $client['email'],
            'whmcs_url' => $whmcsUrl,
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS ClientAreaRegister error: " . $e->getMessage()); }
});
