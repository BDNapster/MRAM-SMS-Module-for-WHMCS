<?php
/**
 * MRAM SMS Helper Functions
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Send SMS using MRAM API and log it
 */
function mram_sms_send($phone, $message, $clientId = 0, $hookName = 'manual')
{
    require_once __DIR__ . '/mram_sms_api.php';

    // Get module settings
    $settings = mram_sms_get_settings();
    if (empty($settings['api_key']) || empty($settings['sender_id'])) {
        return ['success' => false, 'error' => 'Module not configured'];
    }

    // Format phone number
    $phone = mram_sms_format_phone($phone, $settings['country_code']);
    if (empty($phone)) {
        return ['success' => false, 'error' => 'Invalid phone number'];
    }

    // Send via API
    $api = new MramSmsApi($settings['api_key']);
    $result = $api->sendSms(
        $phone,
        $message,
        $settings['sender_id'],
        $settings['sms_type'] ?: 'text',
        $settings['sms_label'] ?: 'transactional'
    );

    // Log
    try {
        Capsule::table('mod_mram_sms_log')->insert([
            'client_id'    => $clientId,
            'phone'        => $phone,
            'message'      => $message,
            'hook_name'    => $hookName,
            'status'       => $result['success'] ? 'sent' : 'failed',
            'api_response' => substr($result['response'], 0, 255),
            'sms_shoot_id' => $result['shoot_id'],
            'error_code'   => $result['error_code'],
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    } catch (\Exception $e) {
        logActivity("MRAM SMS: Log insert error - " . $e->getMessage());
    }

    return $result;
}

/**
 * Send notification SMS for a hook event
 */
function mram_sms_send_notification($hookName, $clientId, $phone, $variables = [])
{
    // Get template
    $template = Capsule::table('mod_mram_sms_templates')
        ->where('hook_name', $hookName)
        ->where('is_active', 1)
        ->first();

    if (!$template) {
        return false;
    }

    // Parse template
    $message = mram_sms_parse_template($template->message_template, $variables);

    // Send to client
    if (!empty($phone)) {
        mram_sms_send($phone, $message, $clientId, $hookName);
    }

    // Send admin notification if enabled
    if ($template->admin_notify) {
        $settings = mram_sms_get_settings();
        if (!empty($settings['admin_phone'])) {
            $adminMsg = !empty($template->admin_template)
                ? mram_sms_parse_template($template->admin_template, $variables)
                : "[Admin] " . $message;
            mram_sms_send($settings['admin_phone'], $adminMsg, 0, $hookName . '_admin');
        }
    }

    return true;
}

/**
 * Parse template variables
 */
function mram_sms_parse_template($template, $variables)
{
    foreach ($variables as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    // Remove any unparsed variables
    $template = preg_replace('/\{[a-z_]+\}/', '', $template);
    return trim($template);
}

/**
 * Format phone number
 */
function mram_sms_format_phone($phone, $countryCode = '88')
{
    // Remove all non-numeric chars except +
    $phone = preg_replace('/[^0-9+]/', '', $phone);

    // Remove leading +
    $phone = ltrim($phone, '+');

    // If starts with 0, replace with country code
    if (substr($phone, 0, 1) === '0') {
        $phone = $countryCode . substr($phone, 1);
    }

    // If doesn't start with country code, add it
    if (!empty($countryCode) && substr($phone, 0, strlen($countryCode)) !== $countryCode) {
        $phone = $countryCode . $phone;
    }

    // Validate minimum length
    if (strlen($phone) < 10) {
        return '';
    }

    return $phone;
}

/**
 * Get module settings from WHMCS addon config
 */
function mram_sms_get_settings()
{
    static $settings = null;
    if ($settings !== null) return $settings;

    try {
        $addon = Capsule::table('tbladdonmodules')
            ->where('module', 'mram_sms')
            ->pluck('value', 'setting');
        $settings = [
            'api_key'      => isset($addon['api_key']) ? $addon['api_key'] : '',
            'sender_id'    => isset($addon['sender_id']) ? $addon['sender_id'] : '',
            'sms_type'     => isset($addon['sms_type']) ? $addon['sms_type'] : 'text',
            'admin_phone'  => isset($addon['admin_phone']) ? $addon['admin_phone'] : '',
            'sms_label'    => isset($addon['sms_label']) ? $addon['sms_label'] : 'transactional',
            'country_code' => isset($addon['country_code']) ? $addon['country_code'] : '88',
        ];
    } catch (\Exception $e) {
        $settings = [
            'api_key' => '', 'sender_id' => '', 'sms_type' => 'text',
            'admin_phone' => '', 'sms_label' => 'transactional', 'country_code' => '88',
        ];
    }
    return $settings;
}

/**
 * Get client phone number from WHMCS
 */
function mram_sms_get_client_phone($clientId)
{
    try {
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if ($client) {
            $phone = !empty($client->phonenumber) ? $client->phonenumber : '';
            return $phone;
        }
    } catch (\Exception $e) {
        logActivity("MRAM SMS: Error getting client phone - " . $e->getMessage());
    }
    return '';
}

/**
 * Get client details
 */
function mram_sms_get_client_details($clientId)
{
    try {
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if ($client) {
            return [
                'client_name' => trim($client->firstname . ' ' . $client->lastname),
                'first_name'  => $client->firstname,
                'last_name'   => $client->lastname,
                'email'       => $client->email,
                'phone'       => $client->phonenumber,
                'company'     => $client->companyname,
            ];
        }
    } catch (\Exception $e) {
        logActivity("MRAM SMS: Error getting client details - " . $e->getMessage());
    }
    return ['client_name' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'company' => ''];
}

/**
 * AJAX: Test SMS
 */
function mram_sms_ajax_test($vars)
{
    ob_clean();
    header('Content-Type: application/json');
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';

    if (empty($phone) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Phone and message required']);
        return;
    }

    $result = mram_sms_send($phone, $message, 0, 'test');
    echo json_encode([
        'success' => $result['success'],
        'error'   => $result['success'] ? '' : ($result['error_code'] ?: 'Send failed'),
    ]);
}

/**
 * AJAX: Send Bulk SMS
 */
function mram_sms_ajax_bulk($vars)
{
    ob_clean();
    header('Content-Type: application/json');
    $target = isset($_POST['target']) ? $_POST['target'] : 'custom';
    $numbers = isset($_POST['numbers']) ? $_POST['numbers'] : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $smsType = isset($_POST['sms_type']) ? $_POST['sms_type'] : 'text';

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message required']);
        return;
    }

    $phoneList = [];
    $settings = mram_sms_get_settings();

    if ($target === 'all_clients' || $target === 'active_clients') {
        $query = Capsule::table('tblclients');
        if ($target === 'active_clients') {
            $query->where('status', 'Active');
        }
        $clients = $query->whereNotNull('phonenumber')->where('phonenumber', '!=', '')->get();
        foreach ($clients as $client) {
            $formatted = mram_sms_format_phone($client->phonenumber, $settings['country_code']);
            if (!empty($formatted)) {
                $phoneList[] = ['phone' => $formatted, 'client_id' => $client->id];
            }
        }
    } else {
        // Parse custom numbers
        $raw = preg_split('/[,\n\r+]+/', $numbers);
        foreach ($raw as $num) {
            $num = trim($num);
            if (!empty($num)) {
                $formatted = mram_sms_format_phone($num, $settings['country_code']);
                if (!empty($formatted)) {
                    $phoneList[] = ['phone' => $formatted, 'client_id' => 0];
                }
            }
        }
    }

    if (empty($phoneList)) {
        echo json_encode(['success' => false, 'error' => 'No valid phone numbers']);
        return;
    }

    $sentCount = 0;
    foreach ($phoneList as $entry) {
        $result = mram_sms_send($entry['phone'], $message, $entry['client_id'], 'bulk');
        if ($result['success']) {
            $sentCount++;
        }
    }

    echo json_encode(['success' => true, 'count' => $sentCount, 'total' => count($phoneList)]);
}

/**
 * AJAX: Save template
 */
function mram_sms_ajax_save_template()
{
    ob_clean();
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $message = isset($_POST['message']) ? $_POST['message'] : '';

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid template']);
        return;
    }

    try {
        Capsule::table('mod_mram_sms_templates')->where('id', $id)->update([
            'message_template' => $message,
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * AJAX: Toggle template active/inactive
 */
function mram_sms_ajax_toggle_template()
{
    ob_clean();
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $active = isset($_POST['active']) ? (int)$_POST['active'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false]);
        return;
    }

    try {
        Capsule::table('mod_mram_sms_templates')->where('id', $id)->update([
            'is_active'  => $active,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * AJAX: Get client data with latest invoice for variable auto-fill
 */
function mram_sms_ajax_get_client_data()
{
    ob_clean();
    header('Content-Type: application/json');
    $clientId = isset($_REQUEST['client_id']) ? (int)$_REQUEST['client_id'] : 0;

    if ($clientId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid client ID']);
        return;
    }

    try {
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            echo json_encode(['success' => false, 'error' => 'Client not found']);
            return;
        }

        $settings = mram_sms_get_settings();
        $phone = mram_sms_format_phone($client->phonenumber, $settings['country_code']);

        // Get latest invoice
        $invoice = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->orderBy('id', 'desc')
            ->first();

        // Get company name from WHMCS settings
        $companyName = '';
        try {
            $companyRow = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->first();
            $companyName = $companyRow ? $companyRow->value : '';
        } catch (\Exception $e) {}

        $data = [
            'success'      => true,
            'client_name'  => trim($client->firstname . ' ' . $client->lastname),
            'first_name'   => $client->firstname,
            'last_name'    => $client->lastname,
            'client_email' => $client->email,
            'phone'        => $phone,
            'company_name' => $companyName,
            'invoice_id'   => $invoice ? $invoice->id : '',
            'amount'       => $invoice ? number_format($invoice->total, 2) : '',
            'currency'     => '',
            'due_date'     => $invoice ? $invoice->duedate : '',
        ];

        // Get currency
        if ($invoice) {
            $currency = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
            $data['currency'] = $currency ? $currency->code : 'BDT';
        }

        echo json_encode($data);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * AJAX: Send Manual SMS with variable replacement
 */
function mram_sms_ajax_send_manual($vars)
{
    ob_clean();
    header('Content-Type: application/json');
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

    if (empty($phone) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Phone and message required']);
        return;
    }

    // If client selected, do server-side variable replacement too
    if ($clientId > 0) {
        $clientData = mram_sms_get_client_details($clientId);
        $settings = mram_sms_get_settings();
        $companyName = '';
        try {
            $companyRow = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->first();
            $companyName = $companyRow ? $companyRow->value : '';
        } catch (\Exception $e) {}

        $invoice = Capsule::table('tblinvoices')->where('userid', $clientId)->orderBy('id', 'desc')->first();
        $currency = '';
        if ($invoice) {
            $client = Capsule::table('tblclients')->where('id', $clientId)->first();
            $curr = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
            $currency = $curr ? $curr->code : 'BDT';
        }

        $variables = [
            'client_name'  => $clientData['client_name'],
            'first_name'   => $clientData['first_name'],
            'last_name'    => $clientData['last_name'],
            'client_email' => $clientData['email'],
            'company_name' => $companyName,
            'invoice_id'   => $invoice ? $invoice->id : '',
            'amount'       => $invoice ? number_format($invoice->total, 2) : '',
            'currency'     => $currency,
            'due_date'     => $invoice ? $invoice->duedate : '',
        ];
        $message = mram_sms_parse_template($message, $variables);
    }

    $result = mram_sms_send($phone, $message, $clientId, 'manual');
    echo json_encode([
        'success' => $result['success'],
        'error'   => $result['success'] ? '' : ($result['error_code'] ?: 'Send failed'),
        'message_sent' => $message,
    ]);
}
