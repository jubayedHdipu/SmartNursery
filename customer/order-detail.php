<?php
// Customer Order Detail View

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('customer');
$currentUser = current_user();
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    redirect('customer/index.php');
}

$db = get_db();

// Fetch Order
$stmt = $db->prepare("
    SELECT o.*, p.transaction_id, p.payment_method, p.payment_status,
           vp.nursery_name, vp.address AS vendor_address, vp.id AS vendor_profile_id
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

// Fetch Items with plant name_bn
$itemStmt = $db->prepare("
    SELECT oi.*, p.name_bn 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = :id
");
$itemStmt->execute(['id' => $orderId]);
$orderItems = $itemStmt->fetchAll();

$pageTitle = __('order.order_number') . ' #' . $order['order_number'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

    <?php 
    $isOrderPaid = ($order['payment_status'] === 'Completed');
    $isOrderUnpaid = ($order['order_status'] === 'Pending' && !$isOrderPaid);
    ?>

    <!-- Unpaid / Incomplete Order Alert Banner -->
    <?php if ($isOrderUnpaid): ?>
        <div class="bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border-2 border-amber-300 rounded-3xl p-6 sm:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 shadow-sm">
            <div class="flex items-start sm:items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-xl shrink-0 shadow-md shadow-amber-900/10">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div class="space-y-1">
                    <div class="flex items-center space-x-2">
                        <span class="bg-amber-100 text-amber-900 font-extrabold text-[10px] uppercase px-2 py-0.5 rounded-full border border-amber-300">Action Required</span>
                        <h3 class="text-base font-extrabold text-slate-900"><?= is_bangla() ? 'অর্ডারটি অসম্পূর্ণ অবস্থায় রয়েছে' : 'Payment Incomplete / Pending' ?></h3>
                    </div>
                    <p class="text-xs text-slate-600 max-w-xl leading-relaxed">
                        <?= is_bangla() ? 'আপনার অর্ডারটি সেভ করা হয়েছে, কিন্তু পেমেন্ট সম্পন্ন হয়নি। নিচে ক্লিক করে SSLCommerz, বিকাশ, নগদ, কার্ড বা ক্যাশ অন ডেলিভারিতে অর্ডার সম্পন্ন করুন।' : 'Your order is recorded, but payment was not finalized. You can complete your checkout right now using SSLCommerz (bKash/Nagad/Cards) or alternate payment.' ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-3 shrink-0 w-full md:w-auto">
                <a href="<?= BASE_URL ?>/payment.php?order_id=<?= $order['id'] ?>" 
                   class="flex-1 md:flex-initial px-6 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-emerald-900/20 transition transform hover:-translate-y-0.5 flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-credit-card"></i>
                    <span><?= is_bangla() ? 'এখনই পেমেন্ট করুন' : 'Complete Payment Now' ?> (<?= format_currency($order['grand_total']) ?>)</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
        
        <!-- Left: Items Breakdown -->
        <div class="md:col-span-8 space-y-6">
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3">
                    <?= __('cart.plant_item') ?> (<?= format_num_i18n(count($orderItems)) ?>)
                </h3>

                <div class="divide-y divide-slate-100">
                    <?php foreach ($orderItems as $item): ?>
                        <div class="py-4 flex items-center justify-between text-xs">
                            <div class="space-y-1">
                                <h4 class="font-bold text-slate-900 text-sm">
                                    <a href="<?= BASE_URL ?>/product.php?id=<?= $item['product_id'] ?>" class="hover:text-emerald-600">
                                        <?= sanitize(is_bangla() && !empty($item['name_bn']) ? $item['name_bn'] : $item['product_name']) ?>
                                    </a>
                                </h4>
                                <span class="text-slate-400"><?= format_num_i18n($item['quantity']) ?> x <?= format_currency($item['unit_price']) ?></span>
                            </div>

                            <div class="text-right space-y-1">
                                <span class="font-bold text-slate-900 block"><?= format_currency($item['subtotal']) ?></span>
                                <?php if ($order['order_status'] === 'Delivered'): ?>
                                    <a href="<?= BASE_URL ?>/product.php?id=<?= $item['product_id'] ?>#reviews-section" class="text-[11px] text-emerald-700 font-bold hover:underline">
                                        <?= __('product.write_review') ?> &rarr;
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-slate-100 pt-4 space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between">
                        <span><?= __('cart.items_subtotal') ?></span>
                        <span class="font-bold text-slate-800"><?= format_currency($order['total_amount']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span><?= __('cart.delivery_fee') ?></span>
                        <span class="font-bold text-slate-800"><?= format_currency($order['shipping_fee']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm font-extrabold text-slate-900 pt-2 border-t border-slate-100">
                        <span><?= __('cart.total') ?></span>
                        <span class="text-lg text-emerald-600"><?= format_currency($order['grand_total']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Status & Shipping -->
        <div class="md:col-span-4 space-y-6">
            
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3">
                    <?= __('track.status_overview', 'অর্ডারের অবস্থা') ?>
                </h3>

                <div class="space-y-3">
                    <div>
                        <span class="text-slate-400 block text-[10px]"><?= __('order.order_status', 'অর্ডার স্ট্যাটাস') ?></span>
                        <?= render_order_status_badge($order['order_status']) ?>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]"><?= __('order.payment_status', 'পেমেন্ট অবস্থা') ?></span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold <?= $order['payment_status'] === 'Completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                            <?= sanitize($order['payment_status'] ?? 'Pending') ?> (<?= strtoupper($order['payment_method'] ?? 'Card') ?>)
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]"><?= __('track.order_date') ?></span>
                        <span class="font-bold text-slate-700"><?= format_date($order['created_at']) ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4 text-xs">
                <h3 class="font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3">
                    <?= __('checkout.recipient_info') ?>
                </h3>

                <div class="space-y-2 text-slate-600">
                    <p class="font-bold text-slate-900"><?= sanitize($order['shipping_name']) ?></p>
                    <p><i class="fa-solid fa-phone mr-1.5 text-slate-400"></i><?= sanitize($order['shipping_phone']) ?></p>
                    <p><i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i><?= sanitize($order['shipping_address']) ?>, <?= sanitize($order['shipping_city']) ?></p>
                </div>
            </div>

            <div class="bg-emerald-50/80 border border-emerald-200 rounded-3xl p-5 text-xs space-y-2">
                <span class="text-[10px] text-emerald-800 font-bold uppercase tracking-wider block"><?= __('product.verified_nursery') ?></span>
                <h4 class="font-bold text-slate-900"><?= sanitize(vendor_display_name($order['nursery_name'])) ?></h4>
                <p class="text-slate-600 text-[11px]"><?= sanitize(vendor_display_address($order['vendor_address'])) ?></p>
            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
