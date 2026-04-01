<?php
if (!defined("WHMCS")) die();
use WHMCS\Database\Capsule;

add_hook('InvoicePaid', 1, function($vars) {
    $invoiceId = $vars['invoiceid'];
    try {
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) return;
        $client = mram_sms_get_client_details($invoice->userid);
        $phone = mram_sms_get_client_phone($invoice->userid);
        mram_sms_send_notification('InvoicePaid', $invoice->userid, $phone, [
            'client_name' => $client['client_name'],
            'invoice_id' => $invoiceId,
            'amount' => $invoice->total,
            'currency' => getCurrency($invoice->userid)['code'] ?? 'BDT',
            'payment_method' => $invoice->paymentmethod,
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS InvoicePaid error: " . $e->getMessage()); }
});
