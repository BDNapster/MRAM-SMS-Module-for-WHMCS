<?php
if (!defined("WHMCS")) die();
use WHMCS\Database\Capsule;

add_hook('AfterRegistrarRegistration', 1, function($vars) {
    try {
        $domain = Capsule::table('tbldomains')->where('id', $vars['domainid'])->first();
        if (!$domain) return;
        $client = mram_sms_get_client_details($domain->userid);
        $phone = mram_sms_get_client_phone($domain->userid);
        mram_sms_send_notification('AfterRegistrarRegistration', $domain->userid, $phone, [
            'client_name' => $client['client_name'],
            'domain' => $domain->domain,
            'expiry_date' => $domain->expirydate,
            'registrar' => $domain->registrar,
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS AfterRegistrarRegistration error: " . $e->getMessage()); }
});
