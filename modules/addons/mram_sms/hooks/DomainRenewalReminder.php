<?php
if (!defined("WHMCS")) die();
use WHMCS\Database\Capsule;

add_hook('DomainRenewalReminder', 1, function($vars) {
    try {
        $domain = Capsule::table('tbldomains')->where('id', $vars['domainid'])->first();
        if (!$domain) return;
        $client = mram_sms_get_client_details($domain->userid);
        $phone = mram_sms_get_client_phone($domain->userid);
        $daysUntil = (strtotime($domain->expirydate) - time()) / 86400;
        mram_sms_send_notification('DomainRenewalReminder', $domain->userid, $phone, [
            'client_name' => $client['client_name'],
            'domain' => $domain->domain,
            'expiry_date' => $domain->expirydate,
            'days_until_expiry' => max(0, round($daysUntil)),
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS DomainRenewalReminder error: " . $e->getMessage()); }
});
