<?php
if (!defined("WHMCS")) die();
use WHMCS\Database\Capsule;

add_hook('AfterModuleCreate', 1, function($vars) {
    try {
        $client = mram_sms_get_client_details($vars['userid']);
        $phone = mram_sms_get_client_phone($vars['userid']);
        $hosting = Capsule::table('tblhosting')->where('id', $vars['serviceid'])->first();
        mram_sms_send_notification('AfterModuleCreate', $vars['userid'], $phone, [
            'client_name' => $client['client_name'],
            'service' => $hosting ? $hosting->domain : '',
            'domain' => $hosting ? $hosting->domain : '',
            'server' => $hosting ? $hosting->server : '',
            'username' => $hosting ? $hosting->username : '',
            'password' => $hosting ? $hosting->password : '',
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS AfterModuleCreate error: " . $e->getMessage()); }
});
