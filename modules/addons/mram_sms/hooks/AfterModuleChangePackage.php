<?php
if (!defined("WHMCS")) die();
use WHMCS\Database\Capsule;

add_hook('AfterModuleChangePackage', 1, function($vars) {
    try {
        $client = mram_sms_get_client_details($vars['userid']);
        $phone = mram_sms_get_client_phone($vars['userid']);
        $hosting = Capsule::table('tblhosting')->where('id', $vars['serviceid'])->first();
        $product = $hosting ? Capsule::table('tblproducts')->where('id', $hosting->packageid)->first() : null;
        mram_sms_send_notification('AfterModuleChangePackage', $vars['userid'], $phone, [
            'client_name' => $client['client_name'],
            'service' => $hosting ? $hosting->domain : '',
            'domain' => $hosting ? $hosting->domain : '',
            'package' => $product ? $product->name : '',
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS AfterModuleChangePackage error: " . $e->getMessage()); }
});
