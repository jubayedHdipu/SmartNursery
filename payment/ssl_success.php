<?php
// SSLCommerz Payment Success Callback

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/sslcommerz.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/inventory.php';
require_once __DIR__ . '/../helpers/notifications.php';
require_once __DIR__ . '/../services/SSLCommerz.php';

// Capture SSLCommerz Callback Parameters (supports both POST and GET)
$postData = !empty($_POST) ? $_POST : $_GET;

$tranId     = trim($postData['tran_id'] ?? '');
$valId      = trim($postData['val_id'] ?? '');
$amount     = (float)($postData['amount'] ?? 0);
$currency   = trim($postData['currency'] ?? 'BDT');
$cardType   = trim($postData['card_type'] ?? 'SSLCOMMERZ');
$cardIssuer = trim($postData['card_issuer'] ?? ($postData['card_brand'] ?? 'SSLCommerz Gateway'));
$bankTranId = trim($postData['bank_tran_id'] ?? '');
$storeAmt   = (float)($postData['store_amount'] ?? $amount);

if (empty($tranId)) {
    set_flash('error', 'Invalid payment callback: Missing transaction reference.');
    redirect('shop.php');
}

$db = get_db();

// 1. Locate Order and Payment Record in Database
$stmt = $db->prepare("
    SELECT o.*, p.id AS payment_id, p.payment_status, p.transaction_id,
           vp.user_id AS vendor_user_id, vp.nursery_name, u.name AS customer_name
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN vendor_profiles vp ON o.vendor_id = vp.id
    JOIN users u ON o.customer_id = u.id
    WHERE p.transaction_id = :txn
");
$stmt->execute(['txn' => $tranId]);
$order = $stmt->fetch();

if (!$order) {
    // If not matched by tran_id, attempt fallback match by order_id from custom value_a
    $valOrderId = (int)($postData['value_a'] ?? 0);
    if ($valOrderId > 0) {
        $stmtFallback = $db->prepare("
            SELECT o.*, p.id AS payment_id, p.payment_status, p.transaction_id,
                   vp.user_id AS vendor_user_id, vp.nursery_name, u.name AS customer_name
            FROM orders o
            LEFT JOIN payments p ON o.id = p.order_id
            JOIN vendor_profiles vp ON o.vendor_id = vp.id
            JOIN users u ON o.customer_id = u.id
            WHERE o.id = :id
        ");
        $stmtFallback->execute(['id' => $valOrderId]);
        $order = $stmtFallback->fetch();
    }
}

if (!$order) {
    set_flash('error', "No order found corresponding to transaction reference '{$tranId}'.");
    redirect('shop.php');
}

$orderId = (int)$order['id'];

// 2. Idempotency Check: If already completed, gracefully redirect
if ($order['payment_status'] === 'Completed') {
    set_flash('info', 'Payment for this order was already confirmed.');
    redirect("order-confirmation.php?order_id={$orderId}");
}

// 3. Verify Payment with SSLCommerz Validation Server
$sslService = new SSLCommerz();
$validationResult = $sslService->validatePayment($valId, (float)$order['grand_total'], 'BDT');

if ($validationResult['status'] !== 'VALID') {
    // Validation Failed or Mismatched
    error_log("SSLCommerz Validation Failed for Txn {$tranId}: " . $validationResult['message']);
    
    // Mark Payment as Failed
    $db->prepare("
        UPDATE payments 
        SET payment_status = 'Failed', payment_details = :details, updated_at = NOW() 
        WHERE order_id = :oid
    ")->execute([
        'details' => json_encode(['validation_error' => $validationResult['message'], 'callback_data' => $postData]),
        'oid'     => $orderId
    ]);

    set_flash('error', 'Payment verification failed: ' . $validationResult['message']);
    redirect("payment/ssl_fail.php?tran_id={$tranId}");
}

// 4. Payment is Fully Validated — Execute Atomic Database Updates
try {
    if (!$db->inTransaction()) {
        $db->beginTransaction();
    }

    // A. Update Payment Record to Completed with Audit Identifiers
    $payDetails = json_encode([
        'gateway'       => 'SSLCommerz v4 API',
        'val_id'        => $valId,
        'bank_tran_id'  => $bankTranId,
        'card_type'     => $cardType,
        'card_issuer'   => $cardIssuer,
        'currency'      => $currency,
        'store_amount'  => $storeAmt,
        'paid_amount'   => (float)$order['grand_total'],
        'verified_at'   => date('Y-m-d H:i:s'),
        'raw_response'  => $validationResult['data'] ?? []
    ]);

    $updatePayStmt = $db->prepare("
        UPDATE payments 
        SET payment_status = 'Completed',
            payment_method = 'sslcommerz',
            val_id         = :val_id,
            bank_tran_id   = :bank_tran_id,
            card_type      = :card_type,
            card_issuer    = :card_issuer,
            currency       = :currency,
            store_amount   = :store_amount,
            payment_details = :details,
            updated_at     = NOW()
        WHERE order_id = :oid
    ");
    $updatePayStmt->execute([
        'val_id'       => $valId,
        'bank_tran_id' => $bankTranId,
        'card_type'    => $cardType,
        'card_issuer'  => $cardIssuer,
        'currency'     => $currency,
        'store_amount' => $storeAmt,
        'details'      => $payDetails,
        'oid'          => $orderId
    ]);

    // B. Fetch Order Items & Decrement Inventory Under Row Locks
    $itemsStmt = $db->prepare("SELECT product_id, quantity, product_name FROM order_items WHERE order_id = :oid");
    $itemsStmt->execute(['oid' => $orderId]);
    $orderItems = $itemsStmt->fetchAll();

    foreach ($orderItems as $item) {
        adjust_inventory(
            (int)$item['product_id'],
            -((int)$item['quantity']),
            'sale',
            "SSLCommerz Online Payment for Order #{$order['order_number']}",
            $order['order_number'],
            $db
        );
    }

    // C. Update Order Status to Pending (ready for Vendor to accept & prepare)
    $db->prepare("UPDATE orders SET order_status = 'Pending', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);

    $db->commit();

    // 5. Clear Cart Session
    $_SESSION['cart'] = [];

    // 6. Dispatch System Notifications across all stakeholders
    notify_order_lifecycle($orderId, 'placed');

    // Log Audit Record
    log_audit('ssl_payment_success', 'orders', $orderId, "SSLCommerz payment confirmed for Order #{$order['order_number']} (ValID: {$valId})");

    set_flash('success', "Payment successfully processed! Order #{$order['order_number']} is confirmed.");
    redirect("order-confirmation.php?order_id={$orderId}");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("SSLCommerz Success Handling Error: " . $e->getMessage());
    set_flash('error', 'Error finalizing order payment: ' . $e->getMessage());
    redirect("payment.php?order_id={$orderId}");
}
