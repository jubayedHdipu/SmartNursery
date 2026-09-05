<?php
// SSLCommerz Payment Cancellation Handler

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

$postData = !empty($_POST) ? $_POST : $_GET;
$tranId = trim($postData['tran_id'] ?? '');

$db = get_db();
$orderId = 0;

if (!empty($tranId)) {
    $stmt = $db->prepare("
        SELECT o.id, o.order_number, p.id AS payment_id, p.payment_status 
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        WHERE p.transaction_id = :txn
    ");
    $stmt->execute(['txn' => $tranId]);
    $order = $stmt->fetch();

    if ($order) {
        $orderId = (int)$order['id'];

        if ($order['payment_status'] !== 'Completed') {
            $db->prepare("
                UPDATE payments 
                SET payment_status = 'Cancelled', 
                    payment_details = '{\"status\": \"CANCELLED\", \"note\": \"Customer cancelled on SSLCommerz gateway\"}',
                    updated_at = NOW()
                WHERE id = :pid
            ")->execute(['pid' => $order['payment_id']]);

            log_audit('ssl_payment_cancelled', 'orders', $orderId, "Customer cancelled SSLCommerz session for Order #{$order['order_number']}");
        }
    }
}

set_flash('info', 'Online payment was cancelled. You can retry or select another payment option.');

if ($orderId > 0) {
    redirect("payment.php?order_id={$orderId}");
} else {
    redirect("customer/index.php");
}
