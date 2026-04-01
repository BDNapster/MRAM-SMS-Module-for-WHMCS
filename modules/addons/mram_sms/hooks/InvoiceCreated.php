<?php
if (!defined("WHMCS")) die();
use WHMCS\Database\Capsule;

add_hook('InvoiceCreated', 1, function($vars) {
    $invoiceId = $vars['invoiceid'];
    try {
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) return;
        $client = mram_sms_get_client_details($invoice->userid);
        $phone = mram_sms_get_client_phone($invoice->userid);
        mram_sms_send_notification('InvoiceCreated', $invoice->userid, $phone, [
            'client_name' => $client['client_name'],
            'invoice_id' => $invoiceId,
            'amount' => $invoice->total,
            'currency' => getCurrency($invoice->userid)['code'] ?? 'BDT',
            'due_date' => $invoice->duedate,
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS InvoiceCreated error: " . $e->getMessage()); }
});
