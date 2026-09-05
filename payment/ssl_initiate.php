<?php
// SSLCommerz Payment Session Initiator

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/sslcommerz.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../services/SSLCommerz.php';

require_login();
$currentUser = current_user();
$orderId = (int)($_GET['order_id'] ?? ($_POST['order_id'] ?? 0));

if ($orderId <= 0) {
    set_flash('error', 'Invalid order ID provided for payment.');
    redirect('customer/index.php');
}

$db = get_db();

// 1. Fetch Order and Associated Payment Record
$stmt = $db->prepare("
    SELECT o.*, p.id AS payment_id, p.payment_status, p.transaction_id,
           u.email AS customer_email, u.name AS customer_full_name, u.phone AS customer_phone
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    JOIN users u ON o.customer_id = u.id
    WHERE o.id = :id AND o.customer_id = :cid
");
$stmt->execute(['id' => $orderId, 'cid' => $currentUser['id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found or permission denied.');
    redirect('customer/index.php');
}

if ($order['payment_status'] === 'Completed') {
    set_flash('info', 'Payment for this order has already been verified.');
    redirect("order-confirmation.php?order_id={$orderId}");
}

// 2. Fetch Order Items summary
$itemStmt = $db->prepare("SELECT product_name, quantity, subtotal FROM order_items WHERE order_id = :id");
$itemStmt->execute(['id' => $orderId]);
$orderItems = $itemStmt->fetchAll();

$itemNames = array_map(function($item) {
    return $item['product_name'] . ' (x' . $item['quantity'] . ')';
}, $orderItems);
$productSummary = !empty($itemNames) ? substr(implode(', ', $itemNames), 0, 200) : 'Botanical Plants';

// 3. Generate a Unique Transaction ID for SSLCommerz
$tranId = 'SSL-ORD' . $orderId . '-' . time() . '-' . strtoupper(bin2hex(random_bytes(2)));

try {
    // 4. Update or Insert Pending Payment Record
    $checkPay = $db->prepare("SELECT id FROM payments WHERE order_id = :oid");
    $checkPay->execute(['oid' => $orderId]);
    $existingPay = $checkPay->fetch();

    if ($existingPay) {
        $upPay = $db->prepare("
            UPDATE payments 
            SET transaction_id = :txn, payment_method = 'sslcommerz', amount = :amt, 
                currency = 'BDT', payment_status = 'Pending', updated_at = NOW()
            WHERE order_id = :oid
        ");
        $upPay->execute([
            'txn' => $tranId,
            'amt' => $order['grand_total'],
            'oid' => $orderId
        ]);
    } else {
        $insPay = $db->prepare("
            INSERT INTO payments (order_id, transaction_id, payment_method, amount, currency, payment_status, created_at)
            VALUES (:oid, :txn, 'sslcommerz', :amt, 'BDT', 'Pending', NOW())
        ");
        $insPay->execute([
            'oid' => $orderId,
            'txn' => $tranId,
            'amt' => $order['grand_total']
        ]);
    }

    // 5. Construct SSLCommerz Payload
    $sslPayload = [
        'total_amount'     => (float)$order['grand_total'],
        'currency'         => 'BDT',
        'tran_id'          => $tranId,
        'success_url'      => SSLCOMMERZ_SUCCESS_URL,
        'fail_url'         => SSLCOMMERZ_FAIL_URL,
        'cancel_url'       => SSLCOMMERZ_CANCEL_URL,
        'ipn_url'          => SSLCOMMERZ_IPN_URL,
        
        // Customer Information
        'cus_name'         => !empty($order['shipping_name']) ? $order['shipping_name'] : $currentUser['name'],
        'cus_email'        => !empty($order['customer_email']) ? $order['customer_email'] : $currentUser['email'],
        'cus_add1'         => !empty($order['shipping_address']) ? $order['shipping_address'] : 'Dhaka, Bangladesh',
        'cus_city'         => !empty($order['shipping_city']) ? $order['shipping_city'] : 'Dhaka',
        'cus_state'        => 'Dhaka',
        'cus_postcode'     => '1200',
        'cus_country'      => 'Bangladesh',
        'cus_phone'        => !empty($order['shipping_phone']) ? $order['shipping_phone'] : ($currentUser['phone'] ?? '01711000000'),
        
        // Shipment Information
        'shipping_method'  => 'YES',
        'ship_name'        => $order['shipping_name'],
        'ship_add1'        => $order['shipping_address'],
        'ship_city'        => $order['shipping_city'],
        'ship_postcode'    => '1200',
        'ship_country'     => 'Bangladesh',

        // Product Information
        'product_name'     => $productSummary,
        'product_category' => 'Live Plants & Nursery Goods',
        'product_profile'  => 'physical-goods',
        'num_of_item'      => count($orderItems),

        // Custom Reference Parameters Passed Back in Callback
        'value_a'          => (string)$orderId,
        'value_b'          => (string)$currentUser['id'],
        'value_c'          => (string)$order['order_number']
    ];

    // 6. Request Payment Gateway URL from SSLCommerz Service
    $sslService = new SSLCommerz();
    $result = $sslService->initiatePayment($sslPayload);

    if ($result['status'] === 'SUCCESS' && !empty($result['GatewayPageURL'])) {
        // Log initiation audit
        log_audit('ssl_payment_initiated', 'orders', $orderId, "SSLCommerz session initiated for Order #{$order['order_number']} (Txn: {$tranId})");
        
        // Cleanly redirect customer to SSLCommerz Gateway
        header("Location: " . $result['GatewayPageURL']);
        exit;
    } else {
        $errMsg = $result['message'] ?? 'Unable to connect to SSLCommerz payment gateway.';
        set_flash('error', $errMsg);
        redirect("payment.php?order_id={$orderId}");
    }
} catch (Exception $e) {
    error_log("SSLCommerz Initiate Exception: " . $e->getMessage());
    set_flash('error', 'Payment initiation encountered an error: ' . $e->getMessage());
    redirect("payment.php?order_id={$orderId}");
}
