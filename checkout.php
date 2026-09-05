<?php
// Concurrency-Protected Checkout (Bilingual)

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

require_login();
$currentUser = current_user();
$cartItems = $_SESSION['cart'] ?? [];

if (empty($cartItems)) {
    set_flash('error', __('cart.empty_title'));
    redirect('shop.php');
}

$db = get_db();

// Fetch saved customer profile
$profileStmt = $db->prepare("SELECT * FROM customer_profiles WHERE user_id = :uid");
$profileStmt->execute(['uid' => $currentUser['id']]);
$customerProfile = $profileStmt->fetch();

// Calculate Totals
$subtotal = 0;
$vendorId = null;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    if (!$vendorId && !empty($item['vendor_id'])) {
        $vendorId = (int)$item['vendor_id'];
    }
}

if (!$vendorId) {
    $vendorId = 1;
}

$baseShipping = (float)get_setting('shipping_base_fee', '60.00');
$freeThreshold = (float)get_setting('free_shipping_threshold', '2000.00');
$shippingFee = ($subtotal >= $freeThreshold) ? 0.00 : $baseShipping;
$grandTotal = $subtotal + $shippingFee;

$error = '';

// Handle Checkout Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shippingName = trim($_POST['shipping_name'] ?? '');
    $shippingPhone = trim($_POST['shipping_phone'] ?? '');
    $shippingAddress = trim($_POST['shipping_address'] ?? '');
    $shippingCity = trim($_POST['shipping_city'] ?? 'Dhaka');
    $notes = trim($_POST['notes'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = 'Security session expired. Please refresh.';
    } elseif (empty($shippingName) || empty($shippingPhone) || empty($shippingAddress)) {
        $error = 'Please complete all required shipping fields.';
    } else {
        try {
            $db->beginTransaction();

            // 1. Verify Stock for every cart item under Row Lock
            foreach ($cartItems as $pid => $item) {
                $stockStmt = $db->prepare("SELECT id, name, price, discount_price, stock_quantity FROM products WHERE id = :id FOR UPDATE");
                $stockStmt->execute(['id' => $pid]);
                $liveProduct = $stockStmt->fetch();

                if (!$liveProduct) {
                    throw new Exception("Product '{$item['name']}' is no longer available.");
                }

                if ($liveProduct['stock_quantity'] < $item['quantity']) {
                    throw new Exception("Only {$liveProduct['stock_quantity']} units remaining for '{$liveProduct['name']}'.");
                }
            }

            // 2. Generate Unique Order Number
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            // 3. Insert Order Record
            $orderStmt = $db->prepare("
                INSERT INTO orders 
                (order_number, customer_id, vendor_id, total_amount, discount_amount, shipping_fee, grand_total, order_status, shipping_name, shipping_phone, shipping_address, shipping_city, notes, created_at)
                VALUES 
                (:order_number, :customer_id, :vendor_id, :total_amount, 0.00, :shipping_fee, :grand_total, 'Pending', :shipping_name, :shipping_phone, :shipping_address, :shipping_city, :notes, NOW())
            ");
            $orderStmt->execute([
                'order_number'     => $orderNumber,
                'customer_id'      => $currentUser['id'],
                'vendor_id'        => $vendorId,
                'total_amount'     => $subtotal,
                'shipping_fee'     => $shippingFee,
                'grand_total'      => $grandTotal,
                'shipping_name'    => $shippingName,
                'shipping_phone'   => $shippingPhone,
                'shipping_address' => $shippingAddress,
                'shipping_city'    => $shippingCity,
                'notes'            => $notes
            ]);

            $newOrderId = (int)$db->lastInsertId();

            // 4. Insert Order Items
            $itemStmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, subtotal, created_at)
                VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity, :subtotal, NOW())
            ");

            foreach ($cartItems as $pid => $item) {
                $itemSubtotal = $item['price'] * $item['quantity'];
                $itemStmt->execute([
                    'order_id'     => $newOrderId,
                    'product_id'   => $pid,
                    'product_name' => $item['name'],
                    'unit_price'   => $item['price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => $itemSubtotal
                ]);
            }

            // 5. Create Pending Payment Record
            $txnId = 'TXN-INIT-' . strtoupper(bin2hex(random_bytes(6)));
            $payStmt = $db->prepare("
                INSERT INTO payments (order_id, transaction_id, payment_method, amount, payment_status, created_at)
                VALUES (:order_id, :transaction_id, 'card', :amount, 'Pending', NOW())
            ");
            $payStmt->execute([
                'order_id'       => $newOrderId,
                'transaction_id' => $txnId,
                'amount'         => $grandTotal
            ]);

            $db->commit();

            $_SESSION['cart'] = [];
            redirect("payment.php?order_id={$newOrderId}");
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$pageTitle = __('checkout.title');
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="bg-slate-100 border-b border-slate-200 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?= __('checkout.title') ?></h1>
        <p class="text-xs text-slate-500 mt-0.5"><?= __('checkout.recipient_info') ?></p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <?php if ($error): ?>
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs flex items-center space-x-3">
            <i class="fa-solid fa-circle-exclamation text-rose-500 text-lg"></i>
            <span><?= sanitize($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="checkout.php" method="POST">
        <?= csrf_field() ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Left: Delivery Shipping Form -->
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
                    <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2 border-b border-slate-100 pb-3">
                        <i class="fa-solid fa-truck text-emerald-600"></i>
                        <span><?= __('checkout.recipient_info') ?></span>
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('checkout.full_name') ?> *</label>
                            <input type="text" name="shipping_name" required 
                                   value="<?= sanitize($_POST['shipping_name'] ?? $currentUser['name']) ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('checkout.phone_number') ?> *</label>
                            <input type="text" name="shipping_phone" required 
                                   value="<?= sanitize($_POST['shipping_phone'] ?? $currentUser['phone']) ?>"
                                   placeholder="+880 1800-000000"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('checkout.delivery_address') ?> *</label>
                        <input type="text" name="shipping_address" required 
                               value="<?= sanitize($_POST['shipping_address'] ?? ($customerProfile['shipping_address'] ?? '')) ?>"
                               placeholder="House #, Road #, Area"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('checkout.city') ?> *</label>
                            <select name="shipping_city" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="Dhaka" selected><?= is_bangla() ? 'ঢাকা (Dhaka)' : 'Dhaka' ?></option>
                                <option value="Chittagong"><?= is_bangla() ? 'চট্টগ্রাম (Chittagong)' : 'Chittagong' ?></option>
                                <option value="Sylhet"><?= is_bangla() ? 'সিলেট (Sylhet)' : 'Sylhet' ?></option>
                                <option value="Rajshahi"><?= is_bangla() ? 'রাজশাহী (Rajshahi)' : 'Rajshahi' ?></option>
                                <option value="Khulna"><?= is_bangla() ? 'খুলনা (Khulna)' : 'Khulna' ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('checkout.delivery_notes') ?></label>
                            <input type="text" name="notes" placeholder="<?= __('checkout.delivery_notes') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Order Review & Submit -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">
                        <?= __('checkout.order_summary') ?> (<?= format_num_i18n(count($cartItems)) ?>)
                    </h3>

                    <div class="max-h-60 overflow-y-auto space-y-3 divide-y divide-slate-100 pr-1">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="pt-2 flex items-center justify-between text-xs">
                                <div class="flex items-center space-x-3">
                                    <img src="<?= sanitize($item['image']) ?>" class="w-10 h-10 rounded-lg object-cover">
                                    <div>
                                        <h4 class="font-bold text-slate-800"><?= sanitize(product_display_name($item)) ?></h4>
                                        <span class="text-[11px] text-slate-400"><?= format_num_i18n($item['quantity']) ?> x <?= format_currency($item['price']) ?></span>
                                    </div>
                                </div>
                                <span class="font-bold text-slate-800"><?= format_currency($item['price'] * $item['quantity']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-t border-slate-100 pt-4 space-y-2 text-xs text-slate-600">
                        <div class="flex justify-between">
                            <span><?= __('cart.items_subtotal') ?></span>
                            <span class="font-bold text-slate-900"><?= format_currency($subtotal) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span><?= __('cart.delivery_fee') ?></span>
                            <span class="font-bold <?= $shippingFee == 0 ? 'text-emerald-600' : 'text-slate-900' ?>">
                                <?= $shippingFee == 0 ? (is_bangla() ? 'ফ্রি' : 'FREE') : format_currency($shippingFee) ?>
                            </span>
                        </div>
                        <div class="flex justify-between text-sm font-extrabold text-slate-900 pt-2 border-t border-slate-100">
                            <span><?= __('cart.total') ?></span>
                            <span class="text-xl text-emerald-600"><?= format_currency($grandTotal) ?></span>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs rounded-2xl shadow-xl shadow-emerald-900/30 transition transform hover:-translate-y-0.5 flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-lock text-emerald-200"></i>
                        <span><?= __('checkout.place_order') ?> &rarr;</span>
                    </button>
                </div>
            </div>

        </div>
    </form>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
