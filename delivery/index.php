<?php
// Delivery Staff Dashboard (Bilingual)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifications.php';

require_role(['delivery', 'admin', 'owner']);
$currentUser = current_user();
$db = get_db();
$isDeliveryStaff = ($currentUser['role_name'] === 'delivery');

if ($isDeliveryStaff) {
    // Fetch Profile (Auto-create if new rider)
    $pStmt = $db->prepare("SELECT * FROM delivery_staff_profiles WHERE user_id = :uid");
    $pStmt->execute(['uid' => $currentUser['id']]);
    $staffProfile = $pStmt->fetch();

    if (!$staffProfile) {
        $db->prepare("
            INSERT INTO delivery_staff_profiles (user_id, vehicle_type, current_zone, license_number, total_deliveries, status, created_at)
            VALUES (:uid, 'Motorcycle', 'Dhaka Central', 'COURIER-PENDING', 0, 'available', NOW())
        ")->execute(['uid' => $currentUser['id']]);
        
        $pStmt->execute(['uid' => $currentUser['id']]);
        $staffProfile = $pStmt->fetch();
    }
    $profileId = (int)($staffProfile['id'] ?? 0);
} else {
    // Admin / Owner Fleet Oversight Profile
    $staffProfile = [
        'vehicle_type' => is_bangla() ? 'লজিস্টিকস ও ফ্লিট নিয়ন্ত্রণ' : 'Fleet Logistics Control',
        'current_zone' => is_bangla() ? 'প্ল্যাটফর্ম-ব্যাপী এরিয়া' : 'Platform-Wide Fleet',
        'total_deliveries' => 0
    ];
    $profileId = 0;
}

// Handle First-to-Accept Claim POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_delivery') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf) && $orderId > 0) {
        try {
            $db->beginTransaction();

            $chk = $db->prepare("
                SELECT o.id, o.order_number, o.customer_id, o.vendor_id 
                FROM orders o
                WHERE o.id = :id AND o.order_status = 'Ready for Pickup'
                FOR UPDATE
            ");
            $chk->execute(['id' => $orderId]);
            $ord = $chk->fetch();

            if ($ord) {
                // Determine target rider (if admin is claiming, assign to rider #7 Speedy Courier)
                $claimantId = $isDeliveryStaff ? $currentUser['id'] : 7;
                $claimantName = $isDeliveryStaff ? $currentUser['name'] : 'Speedy Courier Rider';

                // Check if not already assigned
                $aChk = $db->prepare("SELECT id FROM delivery_assignments WHERE order_id = :id AND status IN ('assigned', 'picked_up', 'out_for_delivery', 'delivered')");
                $aChk->execute(['id' => $orderId]);
                if (!$aChk->fetch()) {
                    $db->prepare("
                        INSERT INTO delivery_assignments (order_id, delivery_staff_id, status, assigned_at)
                        VALUES (:oid, :did, 'assigned', NOW())
                        ON DUPLICATE KEY UPDATE delivery_staff_id = :did2, status = 'assigned', assigned_at = NOW()
                    ")->execute(['oid' => $orderId, 'did' => $claimantId, 'did2' => $claimantId]);

                    $db->prepare("UPDATE orders SET order_status = 'Assigned to Delivery', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);
                    
                    $db->commit();
                    notify_order_lifecycle($orderId, 'assigned', ['rider_name' => $claimantName]);
                    log_audit('rider_claim_order', 'order', $orderId, "Rider {$claimantName} claimed open pickup for #{$ord['order_number']}");
                    set_flash('success', is_bangla() ? "আপনি সফলভাবে অর্ডার #{$ord['order_number']} এর ডেলিভারি গ্রহণ করেছেন!" : "You successfully accepted delivery for order #{$ord['order_number']}!");
                } else {
                    $db->rollBack();
                    set_flash('error', is_bangla() ? "এই অর্ডারটি ইতোমধ্যে অন্য একজন রাইডার গ্রহণ করেছেন।" : "This order was already accepted by another courier.");
                }
            } else {
                $db->rollBack();
                set_flash('error', is_bangla() ? "এই অর্ডারটি গ্রহণের জন্য আর উন্মুক্ত নেই।" : "This order is no longer available for claiming.");
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            set_flash('error', 'Error claiming order: ' . $e->getMessage());
        }
    }
    redirect('delivery/index.php');
}

// Fetch Open Available Dispatches Pool (Ready for Pickup without active courier)
$openPool = $db->query("
    SELECT o.*, vp.nursery_name, vp.address AS vendor_address, vp.city AS vendor_city, vu.phone AS vendor_phone,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o
    JOIN vendor_profiles vp ON o.vendor_id = vp.id
    LEFT JOIN users vu ON vp.user_id = vu.id
    WHERE o.order_status = 'Ready for Pickup'
      AND NOT EXISTS (
          SELECT 1 FROM delivery_assignments da 
          WHERE da.order_id = o.id AND da.status IN ('assigned', 'picked_up', 'out_for_delivery', 'delivered')
      )
    ORDER BY o.id ASC
")->fetchAll();

// Metrics & Active Runs
if ($isDeliveryStaff) {
    $activeStmt = $db->prepare("SELECT COUNT(*) FROM delivery_assignments WHERE (delivery_staff_id = :uid OR delivery_staff_id = :pid) AND status IN ('assigned', 'picked_up', 'out_for_delivery')");
    $activeStmt->execute(['uid' => $currentUser['id'], 'pid' => $profileId]);
    $activeCount = (int)$activeStmt->fetchColumn();

    $completedStmt = $db->prepare("SELECT COUNT(*) FROM delivery_assignments WHERE (delivery_staff_id = :uid OR delivery_staff_id = :pid) AND status = 'delivered'");
    $completedStmt->execute(['uid' => $currentUser['id'], 'pid' => $profileId]);
    $completedCount = (int)$completedStmt->fetchColumn();

    // Fetch Active Deliveries Assigned to Current Delivery Staff
    $activeRunsStmt = $db->prepare("
        SELECT da.*, o.order_number, o.grand_total, o.shipping_name, o.shipping_phone, o.shipping_address, o.shipping_city,
               vp.nursery_name, vp.address AS vendor_address
        FROM delivery_assignments da
        JOIN orders o ON da.order_id = o.id
        JOIN vendor_profiles vp ON o.vendor_id = vp.id
        WHERE (da.delivery_staff_id = :uid OR da.delivery_staff_id = :pid) AND da.status IN ('assigned', 'picked_up', 'out_for_delivery')
        ORDER BY da.id DESC
    ");
    $activeRunsStmt->execute(['uid' => $currentUser['id'], 'pid' => $profileId]);
    $activeRuns = $activeRunsStmt->fetchAll();
} else {
    // Admin / Owner fleet metrics
    $activeStmt = $db->query("SELECT COUNT(*) FROM delivery_assignments WHERE status IN ('assigned', 'picked_up', 'out_for_delivery')");
    $activeCount = (int)$activeStmt->fetchColumn();

    $completedStmt = $db->query("SELECT COUNT(*) FROM delivery_assignments WHERE status = 'delivered'");
    $completedCount = (int)$completedStmt->fetchColumn();

    $activeRunsStmt = $db->query("
        SELECT da.*, o.order_number, o.grand_total, o.shipping_name, o.shipping_phone, o.shipping_address, o.shipping_city,
               vp.nursery_name, vp.address AS vendor_address, rider.name AS assigned_rider_name
        FROM delivery_assignments da
        JOIN orders o ON da.order_id = o.id
        JOIN vendor_profiles vp ON o.vendor_id = vp.id
        LEFT JOIN users rider ON da.delivery_staff_id = rider.id
        WHERE da.status IN ('assigned', 'picked_up', 'out_for_delivery')
        ORDER BY da.id DESC
    ");
    $activeRuns = $activeRunsStmt->fetchAll();
}

$pageTitle = __('delivery.portal_title');
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-8 bg-slate-50">
        
        <div class="bg-gradient-to-r from-emerald-950 via-slate-900 to-emerald-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 block mb-1"><?= __('delivery.portal_title') ?></span>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight"><?= sanitize($currentUser['name']) ?></h1>
                <p class="text-xs text-slate-300 mt-1"><?= __('delivery.assigned_runs') ?>: <strong class="text-emerald-300"><?= sanitize($staffProfile['vehicle_type'] ?? 'Motorbike') ?></strong> • Zone: <strong class="text-emerald-300"><?= sanitize($staffProfile['current_zone'] ?? 'Central Zone') ?></strong></p>
            </div>

            <a href="deliveries.php" class="px-5 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-xl shadow-md transition flex items-center space-x-1.5">
                <i class="fa-solid fa-route"></i>
                <span><?= __('delivery.assigned_runs') ?></span>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block"><?= __('delivery.active_tasks') ?></span>
                <span class="text-2xl font-extrabold text-slate-900"><?= format_num_i18n($activeCount) ?></span>
                <p class="text-[11px] text-amber-600 font-semibold"><?= __('delivery.pending_pickup') ?></p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block"><?= __('delivery.completed_deliveries') ?></span>
                <span class="text-2xl font-extrabold text-emerald-600"><?= format_num_i18n($completedCount) ?></span>
                <p class="text-[11px] text-slate-400"><?= __('order.status_delivered') ?></p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block"><?= __('common.status') ?></span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span> <?= __('common.active') ?>
                </span>
                <p class="text-[11px] text-slate-400"><?= __('nav.delivery_portal') ?></p>
            </div>
        </div>

        <!-- 1. Available Pickups (First-to-Accept Pool) -->
        <div class="bg-gradient-to-br from-amber-500/10 via-white to-emerald-500/10 rounded-3xl p-6 border <?= !empty($openPool) ? 'border-amber-400/80 shadow-lg shadow-amber-500/5 ring-1 ring-amber-400/50' : 'border-slate-200 shadow-xs' ?> space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/60 pb-3">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-bolt-lightning"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center space-x-2">
                            <span><?= __('delivery.available_pool') ?></span>
                            <?php if (!empty($openPool)): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 animate-pulse">
                                    <?= format_num_i18n(count($openPool)) ?> <?= is_bangla() ? 'টি উন্মুক্ত' : 'Available' ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-[11px] text-slate-500"><?= __('delivery.pool_desc') ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($openPool)): ?>
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach ($openPool as $op): ?>
                        <div class="p-5 rounded-2xl bg-white border border-amber-200/80 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4 hover:border-amber-400 transition">
                            <div class="space-y-2 flex-1 text-xs">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="font-mono font-bold text-slate-900 text-sm">#<?= sanitize($op['order_number']) ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800">
                                        <i class="fa-solid fa-clock mr-1"></i><?= is_bangla() ? 'পিকআপের জন্য প্রস্তুত' : 'Ready for Pickup' ?>
                                    </span>
                                    <span class="text-emerald-600 font-extrabold text-xs"><?= format_currency($op['grand_total']) ?></span>
                                    <span class="text-slate-400 text-[11px]">（<?= $op['item_count'] ?> items）</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-slate-600 pt-1">
                                    <div class="bg-emerald-50/50 p-2.5 rounded-xl border border-emerald-100">
                                        <span class="text-[10px] font-bold uppercase text-emerald-800 block"><i class="fa-solid fa-store mr-1"></i><?= __('delivery.pickup_address') ?>:</span>
                                        <strong class="text-slate-800"><?= sanitize(vendor_display_name($op['nursery_name'])) ?></strong>
                                        <p class="text-slate-500 text-[11px]"><?= sanitize(vendor_display_address($op['vendor_address'])) ?>, <?= sanitize($op['vendor_city']) ?></p>
                                    </div>
                                    <div class="bg-sky-50/50 p-2.5 rounded-xl border border-sky-100">
                                        <span class="text-[10px] font-bold uppercase text-sky-800 block"><i class="fa-solid fa-location-dot mr-1"></i><?= __('delivery.customer_address') ?>:</span>
                                        <strong class="text-slate-800"><?= sanitize($op['shipping_name']) ?> (<?= sanitize($op['shipping_phone']) ?>)</strong>
                                        <p class="text-slate-500 text-[11px]"><?= sanitize($op['shipping_address']) ?>, <?= sanitize($op['shipping_city']) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Claim / Accept Button Form -->
                            <form action="index.php" method="POST" class="shrink-0 w-full md:w-auto">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="claim_delivery">
                                <input type="hidden" name="order_id" value="<?= $op['id'] ?>">
                                <button type="submit" class="w-full md:w-auto px-5 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-md transition flex items-center justify-center space-x-1.5 transform hover:scale-102">
                                    <i class="fa-solid fa-bolt-lightning text-xs"></i>
                                    <span><?= __('delivery.claim_btn') ?></span>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-6 text-center text-xs text-slate-400 bg-white/60 rounded-2xl border border-dashed border-slate-200">
                    <i class="fa-solid fa-inbox text-2xl text-slate-300 block mb-1"></i>
                    <?= __('delivery.no_open_requests') ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Active Deliveries Queue -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2 border-b border-slate-100 pb-3">
                <i class="fa-solid fa-truck-fast text-emerald-600"></i>
                <span><?= __('delivery.assigned_runs') ?></span>
            </h3>

            <?php if (!empty($activeRuns)): ?>
                <div class="space-y-4">
                    <?php foreach ($activeRuns as $run): ?>
                        <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            <div class="space-y-2 flex-1 text-xs">
                                <div class="flex items-center space-x-3">
                                    <span class="font-mono font-bold text-slate-900 text-sm">#<?= sanitize($run['order_number']) ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-sky-100 text-sky-800">
                                        <?= sanitize($run['status']) ?>
                                    </span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-600">
                                    <p><strong><?= __('delivery.pickup_address') ?>:</strong> <?= sanitize(vendor_display_name($run['nursery_name'])) ?> (<?= sanitize(vendor_display_address($run['vendor_address'])) ?>)</p>
                                    <p><strong><?= __('delivery.customer_address') ?>:</strong> <?= sanitize($run['shipping_name']) ?> (<?= sanitize($run['shipping_phone']) ?>), <?= sanitize($run['shipping_address']) ?></p>
                                </div>
                            </div>

                            <a href="deliveries.php" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-xs transition">
                                <?= __('delivery.update_status_btn') ?> &rarr;
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-8 text-center text-xs text-slate-400">
                    <?= __('customer.no_orders_yet') ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
