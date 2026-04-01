<?php
/**
 * MRAM SMS - Hook Registration Loader
 * Automatically loads all hook files from the hooks/ directory
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/mram_sms_api.php';
require_once __DIR__ . '/functions.php';

$hookDir = __DIR__ . '/hooks/';
if (is_dir($hookDir)) {
    $files = glob($hookDir . '*.php');
    foreach ($files as $file) {
        require_once $file;
    }
}
