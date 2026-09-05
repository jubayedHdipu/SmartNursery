<?php
// SSLCommerz Payment Failure Handler

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

$postData = !empty($_POST) ? $_POST : $_GET;

$tranId     = trim($postData['tran_id'] ?? '');
$status     = trim($postData['status'] ?? 'FAILED');
$errorMsg   = trim($postData['error'] ?? ($postData['failedreason'] ?? 'Payment authorization was cancelled or declined by the payment provider.'));

$db = get_db();
$order = null;
$orderId = 0;

if (!empty($tranId)) {
    $stmt = $db->prepare("
        SELECT o.*, p.id AS payment_id, p.payment_status 
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        WHERE p.transaction_id = :txn
    ");
    $stmt->execute(['txn' => $tranId]);
    $order = $stmt->fetch();

    if ($order) {
        $orderId = (int)$order['id'];

        // Mark payment as Failed if not already Completed
        if ($order['payment_status'] !== 'Completed') {
            $db->prepare("
                UPDATE payments 
                SET payment_status = 'Failed', 
                    payment_details = :details, 
                    updated_at = NOW() 
                WHERE id = :pid
            ")->execute([
                'details' => json_encode(['status' => 'FAILED', 'error' => $errorMsg, 'time' => date('Y-m-d H:i:s')]),
                'pid'     => $order['payment_id']
            ]);

            log_audit('ssl_payment_failed', 'orders', $orderId, "SSLCommerz payment failed for Order #{$order['order_number']}: {$errorMsg}");
        }
    }
}

$pageTitle = 'Payment Failed - Smart Nursery';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="max-w-2xl mx-auto px-4 py-16">
    <div class="bg-white rounded-3xl border border-rose-100 shadow-xl overflow-hidden p-8 sm:p-10 text-center space-y-6">
        
        <!-- Error Icon Badge -->
        <div class="inline-flex items-center justify-center w-20 h-20 bg-rose-50 text-rose-500 rounded-full border-2 border-rose-200/80 shadow-md">
            <i class="fa-solid fa-circle-xmark text-4xl"></i>
        </div>

        <div class="space-y-2">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Payment Unsuccessful</h1>
            <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto">
                Your online transaction could not be completed by SSLCommerz. Don't worry, your plant items are saved in your order.
            </p>
        </div>

        <!-- Failure Details Card -->
        <div class="bg-rose-50/60 border border-rose-200/70 rounded-2xl p-5 text-left text-xs space-y-3">
            <div class="flex items-center justify-between border-b border-rose-200/60 pb-2.5">
                <span class="font-bold text-rose-900">Transaction Status:</span>
                <span class="font-extrabold uppercase text-rose-600 bg-rose-100 px-2.5 py-0.5 rounded-full text-[10px]">Failed</span>
            </div>
            
            <?php if ($order): ?>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Order Reference:</span>
                    <span class="font-mono font-bold text-slate-800">#<?= sanitize($order['order_number']) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Order Payable Amount:</span>
                    <span class="font-bold text-slate-900"><?= format_currency($order['grand_total']) ?></span>
                </div>
            <?php endif; ?>

            <div class="pt-2 border-t border-rose-200/60">
                <span class="font-bold text-slate-700 block mb-1">Reason:</span>
                <p class="text-rose-700 bg-white/80 p-2.5 rounded-xl border border-rose-200 font-medium">
                    <?= sanitize($errorMsg) ?>
                </p>
            </div>
        </div>

        <!-- Recovery Actions -->
        <div class="pt-4 space-y-3">
            <?php if ($orderId > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a href="<?= BASE_URL ?>/payment/ssl_initiate.php?order_id=<?= $orderId ?>" 
                       class="py-3.5 px-6 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold rounded-2xl shadow-lg shadow-emerald-900/20 transition flex items-center justify-center space-x-2 text-xs">
                        <i class="fa-solid fa-rotate-right"></i>
                        <span>Retry SSLCommerz Payment</span>
                    </a>
                    
                    <a href="<?= BASE_URL ?>/payment.php?order_id=<?= $orderId ?>" 
                       class="py-3.5 px-6 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-2xl shadow-md transition flex items-center justify-center space-x-2 text-xs">
                        <i class="fa-solid fa-wallet"></i>
                        <span>Change Payment Method</span>
                    </a>
                </div>
            <?php endif; ?>

            <div>
                <a href="<?= BASE_URL ?>/customer/index.php" 
                   class="inline-block py-2.5 px-5 text-slate-500 hover:text-slate-800 font-semibold text-xs transition">
                    <i class="fa-solid fa-house mr-1.5"></i>Go to Customer Dashboard
                </a>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
