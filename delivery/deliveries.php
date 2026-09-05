<?php
// Delivery Staff Assigned Deliveries & Actions

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
    $pStmt = $db->prepare("SELECT id FROM delivery_staff_profiles WHERE user_id = :uid");
    $pStmt->execute(['uid' => $currentUser['id']]);
    $staffProfileId = (int)$pStmt->fetchColumn();

    if ($staffProfileId <= 0) {
        $db->prepare("
            INSERT INTO delivery_staff_profiles (user_id, vehicle_type, current_zone, license_number, total_deliveries, status, created_at)
            VALUES (:uid, 'Motorcycle', 'Dhaka Central', 'COURIER-PENDING', 0, 'available', NOW())
        ")->execute(['uid' => $currentUser['id']]);
        $staffProfileId = (int)$db->lastInsertId();
    }
} else {
    $staffProfileId = 0;
}

// Handle Status Transitions & First-to-Accept Claims POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf)) {
        if ($action === 'claim_delivery') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            if ($orderId > 0) {
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
                        $aChk = $db->prepare("SELECT id FROM delivery_assignments WHERE order_id = :id AND status IN ('assigned', 'picked_up', 'out_for_delivery', 'delivered')");
                        $aChk->execute(['id' => $orderId]);
                        if (!$aChk->fetch()) {
                            $db->prepare("
                                INSERT INTO delivery_assignments (order_id, delivery_staff_id, status, assigned_at)
                                VALUES (:oid, :did, 'assigned', NOW())
                                ON DUPLICATE KEY UPDATE delivery_staff_id = :did2, status = 'assigned', assigned_at = NOW()
                            ")->execute(['oid' => $orderId, 'did' => $currentUser['id'], 'did2' => $currentUser['id']]);

                            $db->prepare("UPDATE orders SET order_status = 'Assigned to Delivery', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);
                            
                            $db->commit();
                            notify_order_lifecycle($orderId, 'assigned', ['rider_name' => $currentUser['name'], 'rider_phone' => $currentUser['phone']]);
                            log_audit('rider_claim_order', 'order', $orderId, "Rider {$currentUser['name']} claimed open pickup for #{$ord['order_number']}");
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
        } else {
            $assignmentId = (int)($_POST['assignment_id'] ?? 0);
            if ($assignmentId > 0) {
                $aStmt = $db->prepare("
                    SELECT da.*, o.order_number, o.customer_id
                    FROM delivery_assignments da
                    JOIN orders o ON da.order_id = o.id
                    WHERE da.id = :id AND (da.delivery_staff_id = :did OR da.delivery_staff_id = :pid)
                ");
                $aStmt->execute(['id' => $assignmentId, 'did' => $currentUser['id'], 'pid' => $staffProfileId]);
                $assignment = $aStmt->fetch();

                if ($assignment) {
                    $orderId = $assignment['order_id'];
                    $orderNumber = $assignment['order_number'];
                    $customerId = $assignment['customer_id'];

                    if ($action === 'pickup') {
                        $db->prepare("UPDATE delivery_assignments SET status = 'picked_up', picked_up_at = NOW() WHERE id = :id")->execute(['id' => $assignmentId]);
                        $db->prepare("UPDATE orders SET order_status = 'Picked Up', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);
                        
                        notify_order_lifecycle($orderId, 'picked_up', ['rider_name' => $currentUser['name']]);
                        set_flash('success', "Order #{$orderNumber} marked as Picked Up.");
                    } elseif ($action === 'out_for_delivery') {
                        $db->prepare("UPDATE delivery_assignments SET status = 'out_for_delivery' WHERE id = :id")->execute(['id' => $assignmentId]);
                        $db->prepare("UPDATE orders SET order_status = 'Out for Delivery', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);

                        notify_order_lifecycle($orderId, 'out_for_delivery', ['rider_name' => $currentUser['name']]);
                        set_flash('success', "Order #{$orderNumber} marked as Out for Delivery.");
                    } elseif ($action === 'deliver') {
                        $notes = trim($_POST['delivery_notes'] ?? 'Handed over safely in person');

                        $db->beginTransaction();

                        $db->prepare("UPDATE delivery_assignments SET status = 'delivered', delivered_at = NOW(), delivery_notes = :notes WHERE id = :id")->execute(['notes' => $notes, 'id' => $assignmentId]);
                        $db->prepare("UPDATE orders SET order_status = 'Delivered', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);
                        $db->prepare("UPDATE delivery_staff_profiles SET total_deliveries = total_deliveries + 1 WHERE user_id = :uid")->execute(['uid' => $currentUser['id']]);

                        $db->commit();

                        notify_order_lifecycle($orderId, 'delivered');
                        log_audit('order_delivered', 'order', $orderId, "Rider completed delivery for #{$orderNumber}");
                        set_flash('success', "Order #{$orderNumber} marked as Delivered!");
                    }
                }
            }
        }
    }
    redirect('delivery/deliveries.php');
}

// Fetch Open Available Dispatches Pool
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

// Fetch Active Deliveries
if ($isDeliveryStaff) {
    $stmt = $db->prepare("
        SELECT da.*, o.order_number, o.grand_total, o.shipping_name, o.shipping_phone, o.shipping_address, o.shipping_city, o.notes AS order_notes,
               vp.nursery_name, vp.address AS vendor_address
        FROM delivery_assignments da
        JOIN orders o ON da.order_id = o.id
        JOIN vendor_profiles vp ON o.vendor_id = vp.id
        WHERE (da.delivery_staff_id = :did OR da.delivery_staff_id = :pid) AND da.status != 'delivered'
        ORDER BY da.id ASC
    ");
    $stmt->execute(['did' => $currentUser['id'], 'pid' => $staffProfileId]);
    $deliveries = $stmt->fetchAll();
} else {
    // Admin / Owner sees all fleet deliveries
    $stmt = $db->query("
        SELECT da.*, o.order_number, o.grand_total, o.shipping_name, o.shipping_phone, o.shipping_address, o.shipping_city, o.notes AS order_notes,
               vp.nursery_name, vp.address AS vendor_address, rider.name AS assigned_rider_name
        FROM delivery_assignments da
        JOIN orders o ON da.order_id = o.id
        JOIN vendor_profiles vp ON o.vendor_id = vp.id
        LEFT JOIN users rider ON da.delivery_staff_id = rider.id
        WHERE da.status != 'delivered'
        ORDER BY da.id ASC
    ");
    $deliveries = $stmt->fetchAll();
}

$pageTitle = __('delivery.assigned_runs');
$activePage = 'deliveries';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50">
        
        <div class="border-b border-slate-200 pb-5 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= __('delivery.active_tasks') ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= __('delivery.assigned_runs') ?></h1>
            </div>
            <a href="index.php" class="text-xs text-emerald-700 font-bold hover:underline">&larr; <?= __('delivery.dashboard_title') ?></a>
        </div>

        <?php if (!empty($openPool)): ?>
            <!-- Available Open Pool Section -->
            <div class="bg-gradient-to-br from-amber-500/10 via-white to-emerald-500/10 rounded-3xl p-6 border border-amber-400/80 shadow-lg shadow-amber-500/5 ring-1 ring-amber-400/50 space-y-4">
                <div class="flex items-center space-x-2.5 border-b border-slate-200/60 pb-3">
                    <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-bolt-lightning"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center space-x-2">
                            <span><?= __('delivery.available_pool') ?></span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 animate-pulse">
                                <?= format_num_i18n(count($openPool)) ?> <?= is_bangla() ? 'টি উন্মুক্ত' : 'Available' ?>
                            </span>
                        </h3>
                        <p class="text-[11px] text-slate-500"><?= __('delivery.pool_desc') ?></p>
                    </div>
                </div>

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

                            <form action="deliveries.php" method="POST" class="shrink-0 w-full md:w-auto">
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
            </div>
        <?php endif; ?>

        <div class="space-y-6">
            <?php 
            $focusedOrderId = (int)($_GET['order_id'] ?? 0);
            if (!empty($deliveries)): ?>
                <?php foreach ($deliveries as $del): 
                    $isFocused = ($focusedOrderId === (int)$del['order_id']);
                ?>
                    <div id="delivery-card-<?= (int)$del['order_id'] ?>" class="bg-white rounded-3xl p-6 sm:p-8 border <?= $isFocused ? 'border-sky-500 ring-2 ring-sky-400 shadow-md bg-sky-50/10' : 'border-slate-200 shadow-sm' ?> space-y-5 transition">
                        
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4 text-xs">
                            <div class="flex items-center space-x-3">
                                <span class="font-mono font-bold text-slate-900 text-sm">#<?= sanitize($del['order_number']) ?></span>
                                <span class="px-2.5 py-1 rounded-full font-bold uppercase text-[10px] tracking-wider <?= $del['status'] === 'out_for_delivery' ? 'bg-orange-100 text-orange-800' : 'bg-sky-100 text-sky-800' ?>">
                                    <?= str_replace('_', ' ', sanitize($del['status'])) ?>
                                </span>
                            </div>
                            <span class="text-slate-400 font-mono"><?= is_bangla() ? 'নিযুক্ত' : 'Assigned' ?> <?= time_ago($del['assigned_at']) ?></span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
                            
                            <!-- Pickup Location -->
                            <div class="bg-emerald-50/60 p-4 rounded-2xl border border-emerald-100 space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 block"><?= is_bangla() ? '১. নার্সারি পিকআপ ঠিকানা' : '1. Pickup Greenhouse Location' ?></span>
                                <h4 class="font-bold text-slate-900"><?= sanitize(vendor_display_name($del['nursery_name'])) ?></h4>
                                <p class="text-slate-600"><?= sanitize(vendor_display_address($del['vendor_address'])) ?></p>
                            </div>

                            <!-- Customer Dropoff -->
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block"><?= is_bangla() ? '২. কাস্টমারের গন্তব্য ঠিকানা' : '2. Customer Drop-off Destination' ?></span>
                                <h4 class="font-bold text-slate-900"><?= sanitize($del['shipping_name']) ?> (<a href="tel:<?= sanitize($del['shipping_phone']) ?>" class="text-emerald-600 hover:underline"><?= sanitize($del['shipping_phone']) ?></a>)</h4>
                                <p class="text-slate-600"><?= sanitize($del['shipping_address']) ?>, <?= sanitize($del['shipping_city']) ?></p>
                                <?php if ($del['order_notes']): ?>
                                    <p class="text-[11px] text-amber-700 italic">"<?= sanitize($del['order_notes']) ?>"</p>
                                <?php endif; ?>
                            </div>

                        </div>

                        <!-- One-Click Action Transition Controls -->
                        <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                            
                            <a href="<?= BASE_URL ?>/track-order.php?order_number=<?= urlencode($del['order_number']) ?>" target="_blank"
                               class="text-slate-500 hover:text-emerald-600 font-semibold flex items-center space-x-1">
                                <i class="fa-solid fa-route text-[10px]"></i>
                                <span>Preview Customer View</span>
                            </a>

                            <div class="flex items-center space-x-2">
                                <?php if ($del['status'] === 'assigned'): ?>
                                    <form action="deliveries.php" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="pickup">
                                        <input type="hidden" name="assignment_id" value="<?= $del['id'] ?>">
                                        <button type="submit" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-500 text-white font-bold rounded-xl shadow-xs transition flex items-center space-x-1.5">
                                            <i class="fa-solid fa-box-open"></i>
                                            <span>Mark Picked Up from Nursery</span>
                                        </button>
                                    </form>
                                <?php elseif ($del['status'] === 'picked_up'): ?>
                                    <form action="deliveries.php" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="out_for_delivery">
                                        <input type="hidden" name="assignment_id" value="<?= $del['id'] ?>">
                                        <button type="submit" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-500 text-white font-bold rounded-xl shadow-xs transition flex items-center space-x-1.5">
                                            <i class="fa-solid fa-motorcycle"></i>
                                            <span>Mark Out for Delivery</span>
                                        </button>
                                    </form>
                                <?php elseif ($del['status'] === 'out_for_delivery'): ?>
                                    <form action="deliveries.php" method="POST" class="flex items-center space-x-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="deliver">
                                        <input type="hidden" name="assignment_id" value="<?= $del['id'] ?>">
                                        <input type="text" name="delivery_notes" placeholder="Proof/Drop-off note (e.g. Handed to Alice)" required
                                               class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 w-60">
                                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-md transition flex items-center space-x-1.5">
                                            <i class="fa-solid fa-circle-check"></i>
                                            <span>Confirm Delivered</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-3xl p-16 text-center border border-slate-200 space-y-3">
                    <i class="fa-solid fa-circle-check text-4xl text-emerald-500"></i>
                    <h3 class="text-base font-bold text-slate-800">All assigned deliveries completed!</h3>
                    <p class="text-xs text-slate-500">You are on duty. New deliveries dispatched from nurseries will appear here automatically.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const focusedId = "<?= (int)$focusedOrderId ?>";
    if (focusedId && focusedId > 0) {
        const el = document.getElementById(`delivery-card-${focusedId}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
