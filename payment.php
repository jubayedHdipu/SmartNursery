<?php
// Multi-Channel Payment Gateway

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/inventory.php';
require_once __DIR__ . '/helpers/notifications.php';

require_login();
$currentUser = current_user();
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    set_flash('error', 'Invalid order ID.');
    redirect('shop.php');
}

$db = get_db();

// Fetch Order and Payment Details
$stmt = $db->prepare("
    SELECT o.*, p.id AS payment_id, p.payment_status, p.transaction_id, p.payment_method,
           vp.user_id AS vendor_user_id, vp.nursery_name
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    JOIN vendor_profiles vp ON o.vendor_id = vp.id
    WHERE o.id = :id AND o.customer_id = :cid
");
$stmt->execute(['id' => $orderId, 'cid' => $currentUser['id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('customer/index.php');
}

if ($order['payment_status'] === 'Completed') {
    set_flash('info', 'Payment for this order has already been completed.');
    redirect("order-confirmation.php?order_id={$orderId}");
}

// Fetch Order Items
$itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :id");
$itemStmt->execute(['id' => $orderId]);
$orderItems = $itemStmt->fetchAll();

$error = '';
$simulatedStatus = '';

// Handle Payment Simulation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['simulation_action'] ?? 'success'; // 'success' or 'fail'
    $method = trim($_POST['payment_method'] ?? 'cod');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        if ($action === 'fail') {
            // Record failed payment
            $failTxn = 'TXN-FAIL-' . strtoupper(bin2hex(random_bytes(5)));
            $db->prepare("
                UPDATE payments 
                SET payment_status = 'Failed', payment_method = :method, transaction_id = :txn, 
                    payment_details = '{\"simulated\": true, \"result\": \"Declined by card issuer or invalid PIN\"}', updated_at = NOW()
                WHERE order_id = :oid
            ")->execute(['method' => $method, 'txn' => $failTxn, 'oid' => $orderId]);

            $error = 'Simulated Payment Failed: The mock gateway rejected this transaction. You may try again or choose another method.';
        } elseif ($action === 'success') {
            try {
                $db->beginTransaction();

                // 1. Generate Successful Transaction ID
                $successTxn = 'TXN-' . strtoupper($method) . '-' . strtoupper(bin2hex(random_bytes(5)));

                // 2. Update Payment Record
                $payStmt = $db->prepare("
                    UPDATE payments 
                    SET payment_status = 'Completed', payment_method = :method, transaction_id = :txn,
                        payment_details = :details, updated_at = NOW()
                    WHERE order_id = :oid
                ");
                $detailsJson = json_encode([
                    'simulated'    => true,
                    'gateway'      => strtoupper($method) . ' Gateway',
                    'auth_code'    => 'AUTH-' . rand(100000, 999999),
                    'timestamp'    => date('Y-m-d H:i:s'),
                    'currency'     => get_setting('currency_code', 'BDT')
                ]);
                $payStmt->execute([
                    'method'  => $method,
                    'txn'     => $successTxn,
                    'details' => $detailsJson,
                    'oid'     => $orderId
                ]);

                // 3. Deduct Inventory & Record Inventory Audit Transactions
                foreach ($orderItems as $item) {
                    adjust_inventory(
                        (int)$item['product_id'],
                        -((int)$item['quantity']),
                        'sale',
                        "Fulfillment for Order #{$order['order_number']}",
                        $order['order_number'],
                        $db
                    );
                }

                // 4. Update Order Status to Pending (ready for Vendor acceptance)
                $db->prepare("UPDATE orders SET order_status = 'Pending', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);

                $db->commit();

                // 5. Send In-App Notifications across all stakeholders
                notify_order_lifecycle($orderId, 'placed');

                if ($method === 'cod') {
                    set_flash('success', "Order #{$order['order_number']} confirmed with Cash on Delivery! Pay when you receive your plants.");
                } else {
                    set_flash('success', "Payment successfully processed! Order #{$order['order_number']} is placed.");
                }
                redirect("order-confirmation.php?order_id={$orderId}");
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Payment completion error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Secure Checkout Payment';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="bg-slate-900 text-white py-8 border-b border-slate-800">
    <div class="max-w-4xl mx-auto px-4 text-center space-y-2">
        <div class="inline-flex items-center space-x-2 bg-emerald-500/20 text-emerald-300 px-3.5 py-1 rounded-full text-xs font-bold border border-emerald-400/30">
            <i class="fa-solid fa-shield-halved text-emerald-400"></i>
            <span>256-Bit SSL Encrypted Checkout</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Payment Gateway</h1>
        <p class="text-xs text-slate-400">Select payment channel and complete checkout for Order #<?= sanitize($order['order_number']) ?></p>
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 py-10">
    
    <?php if ($error): ?>
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs flex items-center space-x-3">
            <i class="fa-solid fa-circle-exclamation text-rose-500 text-lg"></i>
            <span><?= sanitize($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
        
        <!-- Left: Payment Method Tabs & Form -->
        <div class="md:col-span-7 space-y-6">
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xl space-y-6">
                
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3">
                    Choose Payment Channel
                </h3>

                <!-- Method Switcher: Only SSLCommerz and Cash on Delivery -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs font-bold">
                    <button type="button" onclick="selectTab('sslcommerz')" id="btn-sslcommerz"
                            class="p-4 rounded-2xl border-2 border-emerald-600 bg-emerald-50 text-emerald-800 text-center transition flex flex-col items-center justify-center space-y-1.5 relative shadow-xs cursor-pointer">
                        <span class="absolute -top-2.5 right-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-[9px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider shadow-xs">Recommended</span>
                        <i class="fa-solid fa-shield-halved text-xl text-emerald-600"></i>
                        <span class="text-xs font-extrabold">SSLCommerz Gateway</span>
                        <span class="text-[10px] text-slate-500 font-normal">bKash, Nagad, Cards & Internet Banking</span>
                    </button>
                    <button type="button" onclick="selectTab('cod')" id="btn-cod"
                            class="p-4 rounded-2xl border-2 border-slate-200 text-slate-600 text-center transition flex flex-col items-center justify-center space-y-1.5 hover:bg-slate-50 cursor-pointer">
                        <i class="fa-solid fa-hand-holding-dollar text-xl text-slate-700"></i>
                        <span class="text-xs font-extrabold text-slate-800">Cash on Delivery</span>
                        <span class="text-[10px] text-slate-500 font-normal">Pay cash when your plants arrive</span>
                    </button>
                </div>

                <!-- 1. SSLCommerz Dedicated Section -->
                <div id="form-sslcommerz" class="space-y-4 bg-gradient-to-br from-emerald-50/70 via-teal-50/50 to-white p-5 rounded-2xl border border-emerald-200/80 shadow-xs">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                <i class="fa-solid fa-shield-halved"></i>
                            </span>
                            <div>
                                <h4 class="font-extrabold text-slate-900 text-sm">SSLCommerz Payment Gateway</h4>
                                <p class="text-[11px] text-slate-500">Pay securely via bKash, Nagad, Rocket, Cards & Net Banking</p>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full border border-emerald-300">Official Partner</span>
                    </div>

                    <!-- Channel Badges -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 text-[11px]">
                        <div class="bg-white p-2.5 rounded-xl border border-slate-200 flex items-center space-x-2">
                            <i class="fa-solid fa-mobile-retro text-pink-600"></i>
                            <span class="font-bold text-slate-700">bKash MFS</span>
                        </div>
                        <div class="bg-white p-2.5 rounded-xl border border-slate-200 flex items-center space-x-2">
                            <i class="fa-solid fa-wallet text-orange-600"></i>
                            <span class="font-bold text-slate-700">Nagad</span>
                        </div>
                        <div class="bg-white p-2.5 rounded-xl border border-slate-200 flex items-center space-x-2">
                            <i class="fa-brands fa-cc-visa text-blue-600"></i>
                            <span class="font-bold text-slate-700">VISA / MC</span>
                        </div>
                        <div class="bg-white p-2.5 rounded-xl border border-slate-200 flex items-center space-x-2">
                            <i class="fa-solid fa-landmark text-teal-600"></i>
                            <span class="font-bold text-slate-700">Internet Bank</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-600 bg-white/60 p-3 rounded-xl border border-slate-200/60 leading-relaxed">
                        Clicking the button below will securely redirect you to the <strong>SSLCommerz Hosted Checkout</strong> portal where you can authenticate your preferred Bangladeshi payment instrument.
                    </p>

                    <a href="<?= BASE_URL ?>/payment/ssl_initiate.php?order_id=<?= $orderId ?>" 
                       class="w-full py-4 bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white font-extrabold rounded-xl shadow-lg shadow-emerald-900/20 transition flex items-center justify-center space-x-2 text-sm cursor-pointer">
                        <i class="fa-solid fa-lock text-emerald-200"></i>
                        <span>Proceed to SSLCommerz (<?= format_currency($order['grand_total']) ?>)</span>
                    </a>
                </div>

                <!-- 2. Cash on Delivery Form -->
                <form id="payment-form" action="payment.php?order_id=<?= $orderId ?>" method="POST" class="space-y-4 text-xs">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payment_method" id="selected-method" value="sslcommerz">
                    <input type="hidden" name="simulation_action" id="simulation-action" value="success">

                    <div id="form-cod" class="hidden space-y-4 bg-emerald-50/60 p-5 rounded-2xl border border-emerald-200">
                        <div class="flex items-center space-x-2.5 text-emerald-800 font-bold text-sm">
                            <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                                <i class="fa-solid fa-hand-holding-dollar"></i>
                            </span>
                            <div>
                                <h4 class="font-extrabold text-slate-900 text-sm">Cash on Delivery (COD)</h4>
                                <p class="text-[11px] text-slate-500 font-normal">Pay directly in cash to our delivery rider upon handover</p>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600 bg-white/70 p-3.5 rounded-xl border border-emerald-100 leading-relaxed">
                            You will pay <strong class="text-emerald-700 font-bold"><?= format_currency($order['grand_total']) ?></strong> in cash to the delivery rider upon arrival.
                        </p>
                        <button type="button" onclick="submitSimulation('success')" 
                                class="w-full py-4 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded-xl shadow-lg shadow-slate-900/20 transition flex items-center justify-center space-x-2 text-sm cursor-pointer">
                            <i class="fa-solid fa-truck-ramp-box text-emerald-400"></i>
                            <span>Confirm Cash on Delivery Order (<?= format_currency($order['grand_total']) ?>)</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <!-- Right: Order Breakdown Receipt -->
        <div class="md:col-span-5 space-y-6">
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3">
                    Order #<?= sanitize($order['order_number']) ?>
                </h3>

                <div class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between">
                        <span>Items Subtotal</span>
                        <span class="font-bold text-slate-800"><?= format_currency($order['total_amount']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Delivery Fee</span>
                        <span class="font-bold text-slate-800"><?= format_currency($order['shipping_fee']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm font-extrabold text-slate-900 pt-2 border-t border-slate-100">
                        <span>Payable Total</span>
                        <span class="text-lg text-emerald-600"><?= format_currency($order['grand_total']) ?></span>
                    </div>
                </div>

                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60 text-[11px] space-y-1">
                    <span class="font-bold text-slate-700 block">Deliver to:</span>
                    <p class="text-slate-600"><?= sanitize($order['shipping_name']) ?> (<?= sanitize($order['shipping_phone']) ?>)</p>
                    <p class="text-slate-500"><?= sanitize($order['shipping_address']) ?>, <?= sanitize($order['shipping_city']) ?></p>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
function selectTab(method) {
    const selInput = document.getElementById('selected-method');
    if (selInput) selInput.value = method;

    const tabs = ['sslcommerz', 'cod'];
    tabs.forEach(t => {
        const btn = document.getElementById('btn-' + t);
        const form = document.getElementById('form-' + t);

        if (btn && form) {
            if (t === method) {
                btn.className = 'p-4 rounded-2xl border-2 border-emerald-600 bg-emerald-50 text-emerald-800 text-center transition flex flex-col items-center justify-center space-y-1.5 relative shadow-xs cursor-pointer';
                form.classList.remove('hidden');
            } else {
                btn.className = 'p-4 rounded-2xl border-2 border-slate-200 text-slate-600 text-center transition flex flex-col items-center justify-center space-y-1.5 hover:bg-slate-50 cursor-pointer';
                form.classList.add('hidden');
            }
        }
    });
}

function submitSimulation(action) {
    document.getElementById('simulation-action').value = action;
    document.getElementById('payment-form').submit();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
