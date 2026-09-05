<?php
// SSLCommerz Gateway Configuration

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/../helpers/functions.php';

// 1. SSLCommerz Merchant Credentials (Live DB Settings with Fallbacks)

$dbStoreId   = get_setting('sslcommerz_store_id', '');
$dbStorePass = get_setting('sslcommerz_store_password', '');
$dbIsSandbox = get_setting('sslcommerz_is_sandbox', '');

// Merchant Store ID
if (!defined('SSLCOMMERZ_STORE_ID')) {
    define('SSLCOMMERZ_STORE_ID', !empty($dbStoreId) ? $dbStoreId : 'teama6a8bc8f86c3a3'); 
}

// Merchant Store Password / Secret Key
if (!defined('SSLCOMMERZ_STORE_PASSWORD')) {
    define('SSLCOMMERZ_STORE_PASSWORD', !empty($dbStorePass) ? $dbStorePass : 'QtUKpjzpmG6H'); 
}

// Toggle Sandbox vs Live Production:
// - true: SSLCommerz Official Sandbox (https://sandbox.sslcommerz.com)
// - false: SSLCommerz Real Live Banking (https://securepay.sslcommerz.com)
if (!defined('SSLCOMMERZ_IS_SANDBOX')) {
    if ($dbIsSandbox !== '') {
        define('SSLCOMMERZ_IS_SANDBOX', (bool)(int)$dbIsSandbox);
    } else {
        define('SSLCOMMERZ_IS_SANDBOX', true); 
    }
}

// Default currency code (BDT Taka for Bangladeshi local transactions)
if (!defined('SSLCOMMERZ_CURRENCY')) {
    define('SSLCOMMERZ_CURRENCY', get_setting('currency_code', 'BDT')); 
}

// 2. Real SSLCommerz API Gateway Endpoints (v4)

if (SSLCOMMERZ_IS_SANDBOX) {
    define('SSLCOMMERZ_INIT_URL', 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php');
    define('SSLCOMMERZ_VALIDATION_URL', 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php');
    define('SSLCOMMERZ_TRANSACTION_QUERY_URL', 'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php');
} else {
    define('SSLCOMMERZ_INIT_URL', 'https://securepay.sslcommerz.com/gwprocess/v4/api.php');
    define('SSLCOMMERZ_VALIDATION_URL', 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php');
    define('SSLCOMMERZ_TRANSACTION_QUERY_URL', 'https://securepay.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php');
}

// 3. Application Callback URLs

define('SSLCOMMERZ_SUCCESS_URL', BASE_URL . '/payment/ssl_success.php');
define('SSLCOMMERZ_FAIL_URL',    BASE_URL . '/payment/ssl_fail.php');
define('SSLCOMMERZ_CANCEL_URL',  BASE_URL . '/payment/ssl_cancel.php');
define('SSLCOMMERZ_IPN_URL',     BASE_URL . '/payment/ssl_ipn.php');

// Returns an array containing the complete SSLCommerz runtime configuration.
function get_sslcommerz_config(): array {
    return [
        'store_id'         => SSLCOMMERZ_STORE_ID,
        'store_password'   => SSLCOMMERZ_STORE_PASSWORD,
        'is_sandbox'       => SSLCOMMERZ_IS_SANDBOX,
        'currency'         => SSLCOMMERZ_CURRENCY,
        'init_url'         => SSLCOMMERZ_INIT_URL,
        'validation_url'   => SSLCOMMERZ_VALIDATION_URL,
        'query_url'        => SSLCOMMERZ_TRANSACTION_QUERY_URL,
        'success_url'      => SSLCOMMERZ_SUCCESS_URL,
        'fail_url'         => SSLCOMMERZ_FAIL_URL,
        'cancel_url'       => SSLCOMMERZ_CANCEL_URL,
        'ipn_url'          => SSLCOMMERZ_IPN_URL,
    ];
}
