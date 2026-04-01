<?php
/**
 * MRAM SMS API Wrapper
 * Handles all communication with msg.mram.com.bd
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class MramSmsApi
{
    private $apiKey;
    private $baseUrl = 'https://msg.mram.com.bd';
    private $lastError = '';
    private $lastResponse = '';

    // Error code meanings
    private $errorCodes = [
        '1002' => 'Sender Id/Masking Not Found',
        '1003' => 'API Not Found',
        '1004' => 'SPAM Detected',
        '1005' => 'Internal Error',
        '1006' => 'Internal Error',
        '1007' => 'Balance Insufficient',
        '1008' => 'Message is empty',
        '1009' => 'Message Type Not Set (text/unicode)',
        '1010' => 'Invalid User & Password',
        '1011' => 'Invalid User Id',
        '1012' => 'Invalid Number',
        '1013' => 'API limit error',
        '1014' => 'No matching template',
        '1015' => 'SMS Content Validation Fails',
        '1016' => 'IP address not allowed',
        '1019' => 'Sms Purpose Missing',
    ];

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Send SMS to one or more numbers
     *
     * @param string|array $numbers Phone number(s)
     * @param string $message SMS content
     * @param string $senderId Approved sender ID
     * @param string $type 'text' or 'unicode'
     * @param string $label 'transactional' or 'promotional'
     * @return array ['success' => bool, 'response' => string, 'shoot_id' => string, 'error_code' => string]
     */
    public function sendSms($numbers, $message, $senderId, $type = 'text', $label = 'transactional')
    {
        if (is_array($numbers)) {
            $numbers = implode('+', $numbers);
        }

        $params = [
            'api_key'  => $this->apiKey,
            'type'     => $type,
            'contacts' => $numbers,
            'senderid' => $senderId,
            'msg'      => $message,
            'label'    => $label,
        ];

        $url = $this->baseUrl . '/smsapi?' . http_build_query($params);
        $response = $this->makeRequest($url);

        $this->lastResponse = $response;

        // Parse response
        $result = [
            'success'    => false,
            'response'   => $response,
            'shoot_id'   => '',
            'error_code' => '',
        ];

        if ($response === false) {
            $result['error_code'] = 'CURL_ERROR';
            $this->lastError = 'Connection failed';
            return $result;
        }

        // Try JSON decode
        $json = json_decode($response, true);
        if ($json !== null) {
            if (isset($json['error'])) {
                $result['error_code'] = (string)$json['error'];
                $this->lastError = isset($this->errorCodes[$result['error_code']])
                    ? $this->errorCodes[$result['error_code']]
                    : 'Unknown error: ' . $result['error_code'];
                return $result;
            }
            if (isset($json['shoot_id']) || isset($json['status'])) {
                $result['success'] = true;
                $result['shoot_id'] = isset($json['shoot_id']) ? $json['shoot_id'] : '';
                return $result;
            }
        }

        // Check if response contains error code as plain number
        $trimmed = trim($response);
        if (is_numeric($trimmed) && isset($this->errorCodes[$trimmed])) {
            $result['error_code'] = $trimmed;
            $this->lastError = $this->errorCodes[$trimmed];
            return $result;
        }

        // If we got a non-error response, assume success
        if (!empty($trimmed) && !is_numeric($trimmed)) {
            $result['success'] = true;
            $result['shoot_id'] = $trimmed;
        } else {
            // Check if numeric value >= 1000 (could be shoot ID)
            if (is_numeric($trimmed) && (int)$trimmed >= 10000) {
                $result['success'] = true;
                $result['shoot_id'] = $trimmed;
            }
        }

        return $result;
    }

    /**
     * Get account balance
     */
    public function getBalance()
    {
        $url = $this->baseUrl . '/miscapi/' . $this->apiKey . '/getBalance';
        $response = $this->makeRequest($url);
        return $response !== false ? trim($response) : 'Error';
    }

    /**
     * Get SMS price
     */
    public function getPrice()
    {
        $url = $this->baseUrl . '/miscapi/' . $this->apiKey . '/getPrice';
        return $this->makeRequest($url);
    }

    /**
     * Get delivery reports
     * @param string $shootId Optional SMS shoot ID
     */
    public function getDeliveryReport($shootId = '')
    {
        if (!empty($shootId)) {
            $url = $this->baseUrl . '/miscapi/' . $this->apiKey . '/getDLR/' . urlencode($shootId);
        } else {
            $url = $this->baseUrl . '/miscapi/' . $this->apiKey . '/getDLR/getAll';
        }
        $response = $this->makeRequest($url);
        $json = json_decode($response, true);
        return $json !== null ? $json : $response;
    }

    /**
     * Get unread inbox replies
     */
    public function getUnreadReplies()
    {
        $url = $this->baseUrl . '/miscapi/' . $this->apiKey . '/getUnreadReplies';
        $response = $this->makeRequest($url);
        $json = json_decode($response, true);
        return $json !== null ? $json : $response;
    }

    /**
     * Get last error message
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Get last raw response
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Get error code meaning
     */
    public function getErrorMeaning($code)
    {
        return isset($this->errorCodes[$code]) ? $this->errorCodes[$code] : 'Unknown error';
    }

    /**
     * Make HTTP request via cURL
     */
    private function makeRequest($url, $method = 'GET', $postData = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WHMCS-MRAM-SMS/1.0');

        if ($method === 'POST' && $postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->lastError = curl_error($ch);
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $response;
    }
}
