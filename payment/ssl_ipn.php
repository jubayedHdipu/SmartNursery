<?php
// SSLCommerz Instant Payment Notification (IPN)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/sslcommerz.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/inventory.php';
require_once __DIR__ . '/../helpers/notifications.php';
require_once __DIR__ . '/../services/SSLCommerz.php';

// IPN is strictly a background HTTP POST from SSLCommerz
$postData = $_POST;
$tranId = trim($postData['tran_id'] ?? '');
$valId  = trim($postData['val_id'] ?? '');

if (empty($tranId) || empty($valId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing tran_id or val_id']);
    exit;
}

$db = get_db();

try {
    // 1. Locate Order and Payment Record
    $stmt = $db->prepare("
        SELECT o.*, p.id AS payment_id, p.payment_status,
               vp.user_id AS vendor_user_id, u.name AS customer_name
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        JOIN vendor_profiles vp ON o.vendor_id = vp.id
        JOIN users u ON o.customer_id = u.id
        WHERE p.transaction_id = :txn
    ");
    $stmt->execute(['txn' => $tranId]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }

    $orderId = (int)$order['id'];

    // 2. If already completed, return 200 OK immediately (Idempotency)
    if ($order['payment_status'] === 'Completed') {
        http_response_code(200);
        echo json_encode(['status' => 'already_completed']);
        exit;
    }

    // 3. Validate with SSLCommerz API
    $sslService = new SSLCommerz();
    $valResult = $sslService->validatePayment($valId, (float)$order['grand_total'], 'BDT');

    if ($valResult['status'] === 'VALID') {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
        }

        $bankTranId = $postData['bank_tran_id'] ?? '';
        $cardType   = $postData['card_type'] ?? 'SSLCOMMERZ-IPN';
        $cardIssuer = $postData['card_issuer'] ?? 'SSLCommerz Gateway';
        $storeAmt   = (float)($postData['store_amount'] ?? $order['grand_total']);

        $payDetails = json_encode([
            'gateway'      => 'SSLCommerz IPN Webhook',
            'val_id'       => $valId,
            'bank_tran_id' => $bankTranId,
            'card_type'    => $cardType,
            'verified_at'  => date('Y-m-d H:i:s'),
            'ipn_payload'  => $postData
        ]);

        $db->prepare("
            UPDATE payments 
            SET payment_status  = 'Completed',
                payment_method  = 'sslcommerz',
                val_id          = :val_id,
                bank_tran_id    = :bank_tran_id,
                card_type       = :card_type,
                card_issuer     = :card_issuer,
                store_amount    = :store_amount,
                payment_details = :details,
                updated_at      = NOW()
            WHERE id = :pid
        ")->execute([
            'val_id'       => $valId,
            'bank_tran_id' => $bankTranId,
            'card_type'    => $cardType,
            'card_issuer'  => $cardIssuer,
            'store_amount' => $storeAmt,
            'details'      => $payDetails,
            'pid'          => $order['payment_id']
        ]);

        // Deduct inventory
        $itemsStmt = $db->prepare("SELECT product_id, quantity, product_name FROM order_items WHERE order_id = :oid");
        $itemsStmt->execute(['oid' => $orderId]);
        $orderItems = $itemsStmt->fetchAll();

        foreach ($orderItems as $item) {
            adjust_inventory(
                (int)$item['product_id'],
                -((int)$item['quantity']),
                'sale',
                "SSLCommerz IPN Payment for Order #{$order['order_number']}",
                $order['order_number'],
                $db
            );
        }

        $db->prepare("UPDATE orders SET order_status = 'Pending', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);

        $db->commit();

        // Send notifications
        create_notification(
            (int)$order['customer_id'],
            "Payment Confirmed (IPN) 🌿",
            "Payment for order #{$order['order_number']} verified via SSLCommerz IPN.",
            "customer/order-detail.php?id={$orderId}",
            'order'
        );

        create_notification(
            (int)$order['vendor_user_id'],
            "New Paid Order #{$order['order_number']}",
            "Order #{$order['order_number']} confirmed via SSLCommerz IPN.",
            "vendor/orders.php",
            'order'
        );

        log_audit('ssl_ipn_success', 'orders', $orderId, "SSLCommerz IPN verified Order #{$order['order_number']} (ValID: {$valId})");

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'IPN Processed Successfully']);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'validation_failed', 'message' => $valResult['message']]);
        exit;
    }

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("SSLCommerz IPN Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
