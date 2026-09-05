<?php
// Customer Orders Dashboard (Bilingual)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('customer');
$currentUser = current_user();
$db = get_db();

// Handle Order Cancellation (allowed only if 'Pending')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf) && $orderId > 0) {
        $chkStmt = $db->prepare("SELECT id, order_status, order_number FROM orders WHERE id = :id AND customer_id = :cid");
        $chkStmt->execute(['id' => $orderId, 'cid' => $currentUser['id']]);
        $ord = $chkStmt->fetch();

        if ($ord && $ord['order_status'] === 'Pending') {
            $db->prepare("UPDATE orders SET order_status = 'Cancelled', cancelled_reason = 'Cancelled by Customer', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);
            log_audit('order_cancelled', 'order', $orderId, "Customer cancelled order #{$ord['order_number']}");
            set_flash('success', "Order #{$ord['order_number']} has been cancelled.");
        } else {
            set_flash('error', "Only orders in 'Pending' status can be cancelled.");
        }
    }
    redirect('customer/index.php');
}

// Fetch Customer's Orders with Payment Status
$stmt = $db->prepare("
    SELECT o.*, p.payment_status, p.payment_method, p.transaction_id, vp.nursery_name,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS total_items
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    JOIN vendor_profiles vp ON o.vendor_id = vp.id
    WHERE o.customer_id = :cid
    ORDER BY o.id DESC
");
$stmt->execute(['cid' => $currentUser['id']]);
$orders = $stmt->fetchAll();

$pageTitle = __('customer.my_orders');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    
    <!-- Header & Subnav -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= __('customer.portal_title') ?></span>
            <h1 class="text-2xl font-extrabold text-slate-900"><?= __('customer.my_orders') ?></h1>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <a href="index.php" class="px-3.5 py-2 bg-emerald-600 text-white font-bold rounded-xl shadow-xs"><?= __('customer.order_history') ?></a>
            <a href="reviews.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200"><?= __('customer.my_reviews') ?></a>
            <a href="chat.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200"><?= __('customer.messages') ?></a>
            <a href="profile.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200"><?= __('customer.saved_address') ?></a>
        </div>
    </div>

    <!-- Orders List -->
    <?php if (!empty($orders)): ?>
        <div class="space-y-4">
            <?php foreach ($orders as $o): 
                $isPaid = ($o['payment_status'] === 'Completed');
                $isUnpaid = ($o['order_status'] === 'Pending' && !$isPaid);
            ?>
                <div class="bg-white rounded-3xl p-6 border <?= $isUnpaid ? 'border-amber-300 shadow-md bg-amber-50/20' : 'border-slate-200/80 shadow-xs' ?> flex flex-col md:flex-row items-start md:items-center justify-between gap-6 hover:border-emerald-300 transition">
                    
                    <div class="space-y-2 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="font-mono font-bold text-sm text-slate-900">#<?= sanitize($o['order_number']) ?></span>
                            <?= render_order_status_badge($o['order_status']) ?>

                            <?php if ($isPaid): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    <i class="fa-solid fa-circle-check mr-1 text-[10px] text-emerald-600"></i>
                                    <?= is_bangla() ? 'পরিশোধিত' : 'Paid' ?> (<?= strtoupper($o['payment_method'] ?? 'Online') ?>)
                                </span>
                            <?php elseif ($isUnpaid): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">
                                    <i class="fa-solid fa-clock mr-1 text-amber-700"></i>
                                    <?= is_bangla() ? 'পেমেন্ট বাকি' : 'Payment Pending' ?>
                                </span>
                            <?php endif; ?>

                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs text-slate-500 font-medium"><?= format_date($o['created_at']) ?></span>
                        </div>

                        <div class="text-xs text-slate-600 space-y-0.5">
                            <p><i class="fa-solid fa-store text-emerald-600 mr-1.5"></i><?= __('product.verified_nursery') ?>: <strong><?= sanitize(vendor_display_name($o['nursery_name'])) ?></strong></p>
                            <p><i class="fa-solid fa-location-dot text-slate-400 mr-1.5"></i><?= __('checkout.delivery_address') ?>: <?= sanitize($o['shipping_address']) ?>, <?= sanitize($o['shipping_city']) ?></p>
                            <p class="text-slate-400 text-[11px]"><?= format_num_i18n($o['total_items']) ?> <?= __('cart.plant_item') ?></p>
                        </div>
                    </div>

                    <div class="text-left md:text-right space-y-2 shrink-0">
                        <div>
                            <span class="text-xs text-slate-400 block"><?= __('cart.total') ?></span>
                            <span class="text-lg font-bold text-emerald-600"><?= format_currency($o['grand_total']) ?></span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <?php if ($isUnpaid): ?>
                                <a href="<?= BASE_URL ?>/payment.php?order_id=<?= $o['id'] ?>" 
                                   class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-extrabold rounded-xl shadow-md shadow-emerald-900/20 transition transform hover:-translate-y-0.5 flex items-center space-x-1.5">
                                    <i class="fa-solid fa-credit-card"></i>
                                    <span><?= is_bangla() ? 'পেমেন্ট করুন' : 'Pay Now' ?> (<?= format_currency($o['grand_total']) ?>)</span>
                                </a>
                            <?php endif; ?>

                            <a href="order-detail.php?id=<?= $o['id'] ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                                <?= __('common.details') ?>
                            </a>

                            <?php if ($isPaid || $o['order_status'] !== 'Pending'): ?>
                                <a href="<?= BASE_URL ?>/track-order.php?order_number=<?= urlencode($o['order_number']) ?>" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-lg transition flex items-center space-x-1">
                                    <i class="fa-solid fa-route text-[10px]"></i>
                                    <span><?= __('track.title') ?></span>
                                </a>
                            <?php endif; ?>

                            <?php if ($o['order_status'] === 'Pending'): ?>
                                <form action="index.php" method="POST" onsubmit="return confirm('Cancel this pending order?');" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel_order">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <button type="submit" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-lg border border-rose-200 transition">
                                        <?= __('common.cancel') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-3xl p-16 text-center border border-slate-200 space-y-3">
            <i class="fa-solid fa-receipt text-4xl text-slate-300"></i>
            <h3 class="text-base font-bold text-slate-800"><?= __('customer.no_orders_yet') ?></h3>
            <p class="text-xs text-slate-500">Discover fresh plants from verified nurseries and start your indoor jungle today.</p>
            <a href="<?= BASE_URL ?>/shop.php" class="inline-block px-5 py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-xs mt-2">
                <?= __('footer.all_plants') ?>
            </a>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
