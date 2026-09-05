<?php
// Order Confirmation & Printable Invoice Receipt (Bilingual)

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

require_login();
$currentUser = current_user();
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    set_flash('error', 'Invalid order.');
    redirect('customer/index.php');
}

$db = get_db();

// Fetch Order, Payment, and Vendor info
$stmt = $db->prepare("
    SELECT o.*, p.transaction_id, p.payment_method, p.payment_status,
           vp.nursery_name, vp.nursery_name_bn, vp.address AS vendor_address, vp.address_bn AS vendor_address_bn
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    JOIN vendor_profiles vp ON o.vendor_id = vp.id
    WHERE o.id = :id AND (o.customer_id = :cid OR :role IN ('admin', 'owner'))
");
$stmt->execute([
    'id'   => $orderId,
    'cid'  => $currentUser['id'],
    'role' => $currentUser['role_name']
]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('customer/index.php');
}

// Fetch Order Items with product name_bn
$itemStmt = $db->prepare("
    SELECT oi.*, p.name_bn 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = :id
");
$itemStmt->execute(['id' => $orderId]);
$orderItems = $itemStmt->fetchAll();

$pageTitle = __('order.confirmed_title') . ' #' . $order['order_number'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    
    <?php 
    $isPaid = ($order['payment_status'] === 'Completed');
    ?>

    <!-- Status Banner (No Print) -->
    <?php if ($isPaid): ?>
        <div class="no-print bg-emerald-600 text-white rounded-3xl p-8 text-center space-y-3 shadow-xl shadow-emerald-900/20">
            <div class="w-16 h-16 rounded-full bg-white/20 text-white flex items-center justify-center mx-auto text-3xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight"><?= __('order.confirmed_title') ?></h1>
            <p class="text-xs sm:text-sm text-emerald-100 max-w-md mx-auto">
                <?= __('order.thank_you') ?> <strong>#<?= sanitize($order['order_number']) ?></strong> • <strong><?= sanitize(vendor_display_name($order)) ?></strong>.
            </p>
            <div class="pt-2 flex flex-wrap justify-center gap-3">
                <a href="track-order.php?order_number=<?= urlencode($order['order_number']) ?>" 
                   class="px-5 py-2.5 bg-white text-emerald-950 font-bold text-xs rounded-xl hover:bg-emerald-50 transition shadow-xs flex items-center space-x-1.5">
                    <i class="fa-solid fa-route"></i>
                    <span><?= __('order.track_live_order') ?></span>
                </a>
                <button onclick="window.print()" 
                        class="px-5 py-2.5 bg-emerald-700/80 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl border border-emerald-500/60 transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-print"></i>
                    <span><?= __('order.print_invoice') ?></span>
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="no-print bg-gradient-to-br from-amber-600 to-amber-700 text-white rounded-3xl p-8 text-center space-y-3 shadow-xl shadow-amber-900/20">
            <div class="w-16 h-16 rounded-full bg-white/20 text-white flex items-center justify-center mx-auto text-3xl">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight"><?= is_bangla() ? 'অর্ডার প্রস্তুত — পেমেন্ট বাকি' : 'Order Recorded — Payment Pending' ?></h1>
            <p class="text-xs sm:text-sm text-amber-100 max-w-md mx-auto">
                <?= is_bangla() ? 'আপনার অর্ডার #'.sanitize($order['order_number']).' সেভ হয়েছে। অর্ডারটি নিশ্চিত করতে অনুগ্রহ করে পেমেন্ট সম্পন্ন করুন।' : 'Your Order #'.sanitize($order['order_number']).' is recorded. Please complete payment to begin nursery packing & delivery.' ?>
            </p>
            <div class="pt-2 flex flex-wrap justify-center gap-3">
                <a href="payment.php?order_id=<?= $order['id'] ?>" 
                   class="px-6 py-3 bg-white text-emerald-950 font-extrabold text-xs rounded-xl hover:bg-emerald-50 transition shadow-lg flex items-center space-x-2">
                    <i class="fa-solid fa-credit-card"></i>
                    <span><?= is_bangla() ? 'এখনই পেমেন্ট করুন' : 'Proceed to Payment' ?> (<?= format_currency($order['grand_total']) ?>)</span>
                </a>
                <button onclick="window.print()" 
                        class="px-5 py-3 bg-amber-800/60 hover:bg-amber-800 text-white font-bold text-xs rounded-xl border border-amber-400/40 transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-print"></i>
                    <span><?= __('order.print_invoice') ?></span>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Printable Invoice Receipt Card -->
    <div class="bg-white p-8 sm:p-10 rounded-3xl border border-slate-200 shadow-sm space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-6">
            <div>
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-leaf"></i>
                    </div>
                    <span class="text-xl font-extrabold text-slate-900"><?= __('app.brand_title') ?><span class="text-emerald-600"><?= __('app.brand_subtitle') ?></span></span>
                </div>
                <p class="text-[11px] text-slate-400 mt-1"><?= __('order.order_number') ?>: #<?= sanitize($order['order_number']) ?></p>
            </div>
            
            <div class="text-left sm:text-right space-y-0.5">
                <span class="text-xs font-bold text-slate-900 uppercase tracking-wider block">#<?= sanitize($order['order_number']) ?></span>
                <span class="text-[11px] text-slate-400 block"><?= format_date($order['created_at']) ?></span>
                <?= render_order_status_badge($order['order_status']) ?>
            </div>
        </div>

        <!-- Addresses Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div class="space-y-1 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="font-bold text-slate-400 uppercase tracking-wider text-[10px] block"><?= __('checkout.recipient_info') ?></span>
                <h4 class="font-bold text-slate-900"><?= sanitize($order['shipping_name']) ?></h4>
                <p class="text-slate-600"><?= sanitize($order['shipping_phone']) ?></p>
                <p class="text-slate-600"><?= sanitize($order['shipping_address']) ?>, <?= sanitize($order['shipping_city']) ?></p>
            </div>

            <div class="space-y-1 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="font-bold text-slate-400 uppercase tracking-wider text-[10px] block"><?= __('product.verified_nursery') ?></span>
                <h4 class="font-bold text-slate-900"><?= sanitize(vendor_display_name($order)) ?></h4>
                <p class="text-slate-600"><?= sanitize(vendor_display_address($order['vendor_address'])) ?></p>
                <p class="text-slate-500 font-mono text-[11px]">Txn: <?= sanitize($order['transaction_id'] ?? 'COD-PENDING') ?></p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b-2 border-slate-200 text-slate-400 uppercase font-bold text-[10px]">
                        <th class="py-3"><?= __('cart.plant_item') ?></th>
                        <th class="py-3 text-center"><?= __('cart.price') ?></th>
                        <th class="py-3 text-center"><?= __('cart.quantity') ?></th>
                        <th class="py-3 text-right"><?= __('cart.subtotal') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-800">
                    <?php foreach ($orderItems as $it): ?>
                        <tr>
                            <td class="py-3.5 font-bold"><?= sanitize(is_bangla() && !empty($it['name_bn']) ? $it['name_bn'] : $it['product_name']) ?></td>
                            <td class="py-3.5 text-center"><?= format_currency($it['unit_price']) ?></td>
                            <td class="py-3.5 text-center font-bold"><?= format_num_i18n($it['quantity']) ?></td>
                            <td class="py-3.5 text-right font-bold"><?= format_currency($it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary Totals -->
        <div class="border-t border-slate-200 pt-4 flex justify-end">
            <div class="w-full sm:w-64 space-y-2 text-xs text-slate-600">
                <div class="flex justify-between">
                    <span><?= __('cart.items_subtotal') ?></span>
                    <span class="font-bold text-slate-900"><?= format_currency($order['total_amount']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span><?= __('cart.delivery_fee') ?></span>
                    <span class="font-bold text-slate-900"><?= format_currency($order['shipping_fee']) ?></span>
                </div>
                <div class="flex justify-between text-sm font-extrabold text-slate-900 pt-2 border-t border-slate-200">
                    <span><?= __('cart.total') ?></span>
                    <span class="text-lg text-emerald-600"><?= format_currency($order['grand_total']) ?></span>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-6 text-center text-[11px] text-slate-400">
            <?= __('footer.copyright') ?>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
