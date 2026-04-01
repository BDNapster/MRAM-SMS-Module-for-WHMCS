<?php
if (!defined("WHMCS")) die();

add_hook('TicketOpen', 1, function($vars) {
    try {
        if (empty($vars['userid'])) return;
        $client = mram_sms_get_client_details($vars['userid']);
        $phone = mram_sms_get_client_phone($vars['userid']);
        mram_sms_send_notification('TicketOpen', $vars['userid'], $phone, [
            'client_name' => $client['client_name'],
            'ticket_id' => $vars['ticketid'],
            'subject' => isset($vars['subject']) ? $vars['subject'] : '',
            'department' => isset($vars['deptname']) ? $vars['deptname'] : '',
            'priority' => isset($vars['priority']) ? $vars['priority'] : '',
        ]);
    } catch (\Exception $e) { logActivity("MRAM SMS TicketOpen error: " . $e->getMessage()); }
});
