<?php
// Real SSLCommerz Payment Gateway Service

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/sslcommerz.php';

class SSLCommerz
{
    private string $storeId;
    private string $storePassword;
    private bool $isSandbox;
    private string $currency;
    private string $initUrl;
    private string $validationUrl;
    private string $queryUrl;

    public function __construct()
    {
        $config = get_sslcommerz_config();
        $this->storeId        = $config['store_id'];
        $this->storePassword  = $config['store_password'];
        $this->isSandbox      = (bool)$config['is_sandbox'];
        $this->currency       = $config['currency'];
        $this->initUrl        = $config['init_url'];
        $this->validationUrl  = $config['validation_url'];
        $this->queryUrl       = $config['query_url'];
    }

    // Initiates a live payment session with SSLCommerz Gateway.
    public function initiatePayment(array $params): array
    {
        // Assemble mandatory SSLCommerz Gateway Parameters (v4 API)
        $postData = array_merge([
            'store_id'         => $this->storeId,
            'store_passwd'     => $this->storePassword,
            'currency'         => $this->currency,
            'success_url'      => SSLCOMMERZ_SUCCESS_URL,
            'fail_url'         => SSLCOMMERZ_FAIL_URL,
            'cancel_url'       => SSLCOMMERZ_CANCEL_URL,
            'ipn_url'          => SSLCOMMERZ_IPN_URL,
            'shipping_method'  => 'NO',
            'num_of_item'      => 1,
            'product_name'     => 'Live Plants & Garden Goods',
            'product_category' => 'Live Plants',
            'product_profile'  => 'general',
            'cus_country'      => 'Bangladesh',
        ], $params);

        // Execute live cURL POST to SSLCommerz API
        $response = $this->sendPostRequest($this->initUrl, $postData);

        if ($response['success']) {
            $data = json_decode($response['body'], true);

            if (is_array($data) && isset($data['status']) && strtoupper($data['status']) === 'SUCCESS' && !empty($data['GatewayPageURL'])) {
                return [
                    'status'         => 'SUCCESS',
                    'GatewayPageURL' => $data['GatewayPageURL'],
                    'sessionkey'     => $data['sessionkey'] ?? '',
                    'data'           => $data
                ];
            }

            // Failure reason from SSLCommerz API response
            $failedReason = $data['failedreason'] ?? ($data['message'] ?? 'SSLCommerz session initialization failed.');
            error_log("SSLCommerz Gateway Init Error: " . $failedReason);

            return [
                'status'  => 'FAILED',
                'message' => 'SSLCommerz Error: ' . $failedReason
            ];
        }

        // cURL / Network error
        error_log("SSLCommerz cURL Error: " . ($response['error'] ?? 'Connection timeout'));

        return [
            'status'  => 'FAILED',
            'message' => 'Unable to connect to SSLCommerz live payment servers. ' . ($response['error'] ?? 'Please check internet connection.')
        ];
    }

    // Validates a transaction with SSLCommerz server using val_id.
    public function validatePayment(string $valId, float $expectedAmount, string $expectedCurrency = 'BDT'): array
    {
        if (empty($valId)) {
            return [
                'status'  => 'FAILED',
                'message' => 'Missing SSLCommerz validation ID (val_id).'
            ];
        }

        // Build validation URL with query parameters
        $validationUrl = $this->validationUrl . '?' . http_build_query([
            'val_id'       => $valId,
            'store_id'     => $this->storeId,
            'store_passwd' => $this->storePassword,
            'v'            => '1',
            'format'       => 'json'
        ]);

        $response = $this->sendGetRequest($validationUrl);

        if (!$response['success']) {
            return [
                'status'  => 'FAILED',
                'message' => 'Failed to reach SSLCommerz validation server: ' . ($response['error'] ?? '')
            ];
        }

        $data = json_decode($response['body'], true);

        if (!is_array($data)) {
            return [
                'status'  => 'FAILED',
                'message' => 'Invalid response received from SSLCommerz validation server.'
            ];
        }

        $sslStatus = strtoupper($data['status'] ?? '');

        // 1. Verify Status is VALID or VALIDATED
        if ($sslStatus !== 'VALID' && $sslStatus !== 'VALIDATED') {
            return [
                'status'  => 'FAILED',
                'message' => 'SSLCommerz payment validation rejected with status: ' . ($data['status'] ?? 'UNKNOWN')
            ];
        }

        // 2. Verify Currency Match
        $returnedCurrency = strtoupper($data['currency'] ?? '');
        if ($returnedCurrency !== strtoupper($expectedCurrency)) {
            return [
                'status'  => 'FAILED',
                'message' => "Currency mismatch! Expected {$expectedCurrency}, but SSLCommerz reported {$returnedCurrency}."
            ];
        }

        // 3. Verify Amount Match (Allow ±0.5 BDT float precision tolerance)
        $paidAmount = (float)($data['amount'] ?? 0);
        if (abs($paidAmount - $expectedAmount) > 0.5) {
            return [
                'status'  => 'FAILED',
                'message' => "Payment amount mismatch! Expected ৳" . number_format($expectedAmount, 2) . ", but paid amount was ৳" . number_format($paidAmount, 2) . "."
            ];
        }

        return [
            'status'  => 'VALID',
            'message' => 'SSLCommerz transaction verified and validated successfully.',
            'data'    => $data
        ];
    }

    // Executes HTTP POST Request via cURL.
    private function sendPostRequest(string $url, array $fields): array
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_POST, 1);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_USERAGENT, 'SmartNursery-SSLCommerz/4.0');

        $body = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($error || $code !== 200) {
            return [
                'success' => false,
                'body'    => $body ?: '',
                'code'    => $code,
                'error'   => $error ?: ("HTTP status " . $code)
            ];
        }

        return [
            'success' => true,
            'body'    => $body,
            'code'    => $code,
            'error'   => ''
        ];
    }

    // Executes HTTP GET Request via cURL.
    private function sendGetRequest(string $url): array
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_USERAGENT, 'SmartNursery-SSLCommerz/4.0');

        $body = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($error || $code !== 200) {
            return [
                'success' => false,
                'body'    => $body ?: '',
                'code'    => $code,
                'error'   => $error ?: ("HTTP status " . $code)
            ];
        }

        return [
            'success' => true,
            'body'    => $body,
            'code'    => $code,
            'error'   => ''
        ];
    }
}
