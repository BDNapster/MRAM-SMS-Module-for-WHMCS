<?php
if (!defined("WHMCS")) die();
use WHMCS\Database\Capsule;

add_hook('TicketAdminReply', 1, function($vars) {
    try {
        $ticket = Capsule::table('tbltickets')->where('id', $vars['ticketid'])->first();
        if (!$ticket || empty($ticket->userid)) return;
        $client = mram_sms_get_client_details($ticket->userid);
        $phone = mram_sms_get_client_phone($ticket->userid);
        mram_sms_send_notification('TicketAdminReply', $ticket->userid, $phone, [
            'client_name' => $client['client_name'],
            'ticket_id' => $vars['ticketid'],
            'subject' => $ticket->title,
            'reply_message' => isset($vars['message']) ? mb_substr(strip_tags($vars['message']), 0, 100) : '',
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS TicketAdminReply error: " . $e->getMessage()); }
});
