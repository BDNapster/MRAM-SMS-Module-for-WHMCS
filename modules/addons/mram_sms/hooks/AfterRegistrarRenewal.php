<?php
if (!defined("WHMCS")) die();
use WHMCS\Database\Capsule;

add_hook('AfterRegistrarRenewal', 1, function($vars) {
    try {
        $domain = Capsule::table('tbldomains')->where('id', $vars['domainid'])->first();
        if (!$domain) return;
        $client = mram_sms_get_client_details($domain->userid);
        $phone = mram_sms_get_client_phone($domain->userid);
        mram_sms_send_notification('AfterRegistrarRenewal', $domain->userid, $phone, [
            'client_name' => $client['client_name'],
            'domain' => $domain->domain,
            'expiry_date' => $domain->expirydate,
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS AfterRegistrarRenewal error: " . $e->getMessage()); }
});
