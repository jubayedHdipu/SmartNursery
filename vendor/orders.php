<?php
// Vendor Order Fulfillment Workflow

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/inventory.php';
require_once __DIR__ . '/../helpers/notifications.php';

require_role('vendor');
$currentUser = current_user();
$db = get_db();

// Fetch Vendor Profile
$vStmt = $db->prepare("SELECT id, nursery_name FROM vendor_profiles WHERE user_id = :uid");
$vStmt->execute(['uid' => $currentUser['id']]);
$vendor = $vStmt->fetch();
$vendorId = $vendor['id'];

// Handle Workflow Status Updates
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'];
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf) && $orderId > 0) {
        $ordStmt = $db->prepare("SELECT * FROM orders WHERE id = :id AND vendor_id = :vid");
        $ordStmt->execute(['id' => $orderId, 'vid' => $vendorId]);
        $order = $ordStmt->fetch();

        if ($order) {
            $newStatus = '';
            $msg = '';

            if ($action === 'accept' && $order['order_status'] === 'Pending') {
                $newStatus = 'Vendor Accepted';
                $db->prepare("UPDATE orders SET order_status = :status, updated_at = NOW() WHERE id = :id")->execute(['status' => $newStatus, 'id' => $orderId]);
                notify_order_lifecycle($orderId, 'accepted');
                log_audit('order_status_change', 'order', $orderId, "Vendor accepted order #{$order['order_number']}");
                set_flash('success', is_bangla() ? "অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।" : "Order accepted successfully.");
            } elseif ($action === 'prepare' && in_array($order['order_status'], ['Vendor Accepted', 'Pending'])) {
                $newStatus = 'Preparing';
                $db->prepare("UPDATE orders SET order_status = :status, updated_at = NOW() WHERE id = :id")->execute(['status' => $newStatus, 'id' => $orderId]);
                notify_order_lifecycle($orderId, 'processing');
                log_audit('order_status_change', 'order', $orderId, "Vendor is preparing plants for order #{$order['order_number']}");
                set_flash('success', is_bangla() ? "অর্ডারের চারাগাছ প্রস্তুতকরণ শুরু হয়েছে।" : "Order is now in preparation.");
            } elseif ($action === 'ready_pickup' && in_array($order['order_status'], ['Preparing', 'Vendor Accepted', 'Pending'])) {
                $dispatchType = $_POST['dispatch_type'] ?? 'pool';
                $selectedRiderId = (int)($_POST['rider_id'] ?? 0);

                if ($dispatchType === 'manual' && $selectedRiderId > 0) {
                    $riderId = $selectedRiderId;
                    $rInfo = $db->query("SELECT name, phone FROM users WHERE id = {$riderId}")->fetch();
                    $riderName = $rInfo['name'] ?? 'Courier Rider';
                    $riderPhone = $rInfo['phone'] ?? '';

                    $db->prepare("
                        INSERT INTO delivery_assignments (order_id, delivery_staff_id, status, assigned_at)
                        VALUES (:oid, :did, 'assigned', NOW())
                        ON DUPLICATE KEY UPDATE delivery_staff_id = :did2, status = 'assigned', assigned_at = NOW()
                    ")->execute(['oid' => $orderId, 'did' => $riderId, 'did2' => $riderId]);

                    $newStatus = 'Assigned to Delivery';
                    $db->prepare("UPDATE orders SET order_status = :status, updated_at = NOW() WHERE id = :id")->execute(['status' => $newStatus, 'id' => $orderId]);
                    notify_order_lifecycle($orderId, 'assigned', ['rider_name' => $riderName, 'rider_phone' => $riderPhone]);
                    log_audit('order_assigned_rider', 'order', $orderId, "Assigned to rider {$riderName}");
                    set_flash('success', is_bangla() ? "অর্ডারটি রাইডার {$riderName} কে অ্যাসাইন করা হয়েছে।" : "Order assigned to rider {$riderName}.");
                } else {
                    $newStatus = 'Ready for Pickup';
                    $db->prepare("UPDATE orders SET order_status = :status, updated_at = NOW() WHERE id = :id")->execute(['status' => $newStatus, 'id' => $orderId]);
                    notify_order_lifecycle($orderId, 'ready_for_pickup');
                    log_audit('order_ready_pickup', 'order', $orderId, "Order #{$order['order_number']} is ready for open courier pickup");
                    set_flash('success', is_bangla() ? "অর্ডারটি কুরিয়ারের জন্য প্রস্তুত হিসেবে চিহ্নিত করা হয়েছে।" : "Order marked as Ready for Pickup.");
                }
            } elseif ($action === 'reject') {
                $rejectReason = trim($_POST['reason'] ?? 'Out of seasonal stock or greenhouse closed');

                // Return stock
                $itemStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :id");
                $itemStmt->execute(['id' => $orderId]);
                $items = $itemStmt->fetchAll();

                foreach ($items as $it) {
                    adjust_inventory((int)$it['product_id'], (int)$it['quantity'], 'order_cancellation', "Order #{$order['order_number']} rejected", $order['order_number'], $db);
                }

                $db->prepare("UPDATE orders SET order_status = 'Vendor Rejected', cancelled_reason = :reason, updated_at = NOW() WHERE id = :id")->execute(['reason' => $rejectReason, 'id' => $orderId]);
                notify_order_lifecycle($orderId, 'cancelled', ['reason' => $rejectReason]);
                log_audit('order_rejected', 'order', $orderId, "Vendor rejected order #{$order['order_number']}: {$rejectReason}");
                set_flash('error', is_bangla() ? "অর্ডারটি বাতিল করা হয়েছে।" : "Order rejected.");
            }
        }
    }
    redirect('vendor/orders.php');
}

// Fetch Active Delivery Couriers for Dispatch Selection
$riders = $db->query("
    SELECT u.id AS user_id, u.name, u.phone,
           dsp.vehicle_type, dsp.current_zone, dsp.status AS staff_status,
           (SELECT COUNT(*) FROM delivery_assignments da WHERE (da.delivery_staff_id = u.id OR da.delivery_staff_id = dsp.id) AND da.status IN ('assigned', 'picked_up', 'out_for_delivery')) AS active_tasks
    FROM users u
    LEFT JOIN delivery_staff_profiles dsp ON u.id = dsp.user_id
    WHERE u.role_id = 5 AND u.status = 'active'
    ORDER BY active_tasks ASC, u.id ASC
")->fetchAll();

// Fetch Vendor Orders
$ordersStmt = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.phone AS customer_phone,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count,
           da.delivery_staff_id, rider.name AS assigned_rider_name
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    LEFT JOIN delivery_assignments da ON o.id = da.order_id
    LEFT JOIN users rider ON da.delivery_staff_id = rider.id
    WHERE o.vendor_id = :vid
    ORDER BY o.id DESC
");
$ordersStmt->execute(['vid' => $vendorId]);
$orders = $ordersStmt->fetchAll();

$pageTitle = is_bangla() ? 'অর্ডার ও ডেলিভারি ব্যবস্থাপনা' : 'Orders & Fulfillment';
$activePage = 'orders';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50">
        
        <div class="border-b border-slate-200 pb-5">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Order Management</span>
            <h1 class="text-2xl font-extrabold text-slate-900">Incoming Orders & Fulfillment</h1>
            <p class="text-xs text-slate-500 mt-0.5">Accept incoming orders, pack plants, and dispatch to courier</p>
        </div>

        <div class="space-y-4">
            <?php 
            $focusedOrderId = (int)($_GET['order_id'] ?? 0);
            if (!empty($orders)): ?>
                <?php foreach ($orders as $o): 
                    $isFocused = ($focusedOrderId === (int)$o['id']);
                ?>
                    <div id="order-card-<?= (int)$o['id'] ?>" class="bg-white rounded-3xl p-6 border <?= $isFocused ? 'border-emerald-500 ring-2 ring-emerald-400 shadow-md bg-emerald-50/10' : 'border-slate-200/80 shadow-xs' ?> space-y-4 transition">
                        
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3 text-xs">
                            <div class="flex items-center space-x-3">
                                <span class="font-mono font-bold text-slate-900 text-sm">#<?= sanitize($o['order_number']) ?></span>
                                <?= render_order_status_badge($o['order_status']) ?>
                            </div>
                            <span class="text-slate-400 font-mono"><?= format_date($o['created_at']) ?></span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Customer</span>
                                <h4 class="font-bold text-slate-800"><?= sanitize($o['shipping_name']) ?></h4>
                                <p class="text-slate-500"><?= sanitize($o['shipping_phone']) ?></p>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Delivery Destination</span>
                                <p class="text-slate-700"><?= sanitize($o['shipping_address']) ?>, <?= sanitize($o['shipping_city']) ?></p>
                                <?php if ($o['notes']): ?>
                                    <p class="text-[11px] text-amber-700 mt-0.5">Note: <?= sanitize($o['notes']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="text-left md:text-right">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Total Amount</span>
                                <span class="text-base font-bold text-emerald-600"><?= format_currency($o['grand_total']) ?></span>
                                <span class="text-[11px] text-slate-400 block"><?= $o['item_count'] ?> Botanical item(s)</span>
                            </div>
                        </div>

                        <!-- Workflow Actions Bar -->
                        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                            
                            <div class="flex items-center space-x-2">
                                <a href="<?= BASE_URL ?>/track-order.php?order_number=<?= urlencode($o['order_number']) ?>" target="_blank"
                                   class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition flex items-center space-x-1">
                                    <i class="fa-solid fa-route text-[10px]"></i>
                                    <span>Track Timeline</span>
                                </a>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <?php if ($o['order_status'] === 'Pending'): ?>
                                    <!-- Accept Button -->
                                    <form action="orders.php" method="POST" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition">
                                            <i class="fa-solid fa-check mr-1"></i> Accept Order
                                        </button>
                                    </form>

                                    <!-- Reject Button -->
                                    <form action="orders.php" method="POST" onsubmit="return confirm('Reject this order and refund customer?');" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <button type="submit" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-xl border border-rose-200 transition">
                                            Reject
                                        </button>
                                    </form>
                                <?php elseif ($o['order_status'] === 'Vendor Accepted'): ?>
                                    <form action="orders.php" method="POST" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="prepare">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl shadow-xs transition">
                                            <i class="fa-solid fa-leaf mr-1"></i> Mark Preparing (Packing)
                                        </button>
                                    </form>
                                <?php elseif ($o['order_status'] === 'Preparing'): ?>
                                    <button type="button" 
                                            onclick="openDispatchModal(<?= $o['id'] ?>, '<?= sanitize($o['order_number']) ?>', '<?= sanitize($o['shipping_city']) ?>', '<?= sanitize($o['shipping_address']) ?>')"
                                            class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl shadow-xs transition flex items-center space-x-1.5">
                                        <i class="fa-solid fa-box-open"></i>
                                        <span><?= __('vendor.mark_ready') ?></span>
                                    </button>
                                <?php elseif (!empty($o['assigned_rider_name'])): ?>
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                        <i class="fa-solid fa-motorcycle mr-1.5 text-sky-600"></i>
                                        <span><?= is_bangla() ? 'বরাদ্দকৃত রাইডার: ' : 'Assigned Courier: ' ?><?= sanitize($o['assigned_rider_name']) ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 italic text-[11px]">Lifecycle: Handed to Courier logistics</span>
                                <?php endif; ?>
                            </div>

                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-3xl p-16 text-center border border-slate-200 space-y-3">
                    <i class="fa-solid fa-boxes-packing text-4xl text-slate-300"></i>
                    <h3 class="text-base font-bold text-slate-800"><?= is_bangla() ? 'কোন অপেক্ষমাণ অর্ডার নেই' : 'No incoming orders in fulfillment queue' ?></h3>
                    <p class="text-xs text-slate-500"><?= is_bangla() ? 'গ্রাহকরা আপনার নার্সারি থেকে অর্ডার করলে তা এখানে দৃশ্যমান হবে।' : 'Orders placed by customers for your nursery will appear here.' ?></p>
                </div>
            <?php endif; ?>
        </div>

    </main>

</div>

<!-- Interactive Dispatch Courier Modal -->
<div id="dispatch-modal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 sm:p-8 space-y-5 animate-scale-in">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-truck-fast text-emerald-600"></i>
                <span><?= __('vendor.dispatch_modal_title') ?></span>
            </h3>
            <button type="button" onclick="closeDispatchModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="orders.php" method="POST" class="space-y-4 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ready_pickup">
            <input type="hidden" name="order_id" id="dispatch_order_id" value="">

            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-1">
                <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider"><?= is_bangla() ? 'অর্ডার নম্বর ও গন্তব্য' : 'Order & Destination' ?></span>
                <p class="font-bold text-slate-800" id="dispatch_order_info">#ORD-...</p>
                <p class="text-slate-500 text-[11px]" id="dispatch_dest_info">Dhaka</p>
            </div>

            <div class="space-y-3">
                <label class="block font-bold text-slate-700"><?= is_bangla() ? 'রাইডার নির্ধারণ পদ্ধতি নির্বাচন করুন' : 'Choose Courier Dispatch Method' ?>:</label>
                
                <!-- Option 1: First-to-Accept Open Pool Broadcast (Recommended) -->
                <label class="flex items-start space-x-3 p-3.5 rounded-2xl border border-emerald-200 bg-emerald-50/50 cursor-pointer hover:bg-emerald-50 transition">
                    <input type="radio" name="dispatch_type" value="pool" checked onchange="toggleRiderDropdown(false)" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                    <div>
                        <strong class="text-slate-900 font-bold block flex items-center space-x-1.5">
                            <i class="fa-solid fa-bolt-lightning text-amber-500"></i>
                            <span><?= is_bangla() ? 'ফার্স্ট-টু-এক্সেপ্ট ব্রডকাস্ট (সবার আগে যে গ্রহণ করবেন - প্রস্তাবিত)' : 'First-to-Accept Dispatch Pool (Recommended)' ?></span>
                        </strong>
                        <span class="text-slate-500 text-[11px] block mt-0.5"><?= is_bangla() ? 'সংশ্লিষ্ট জোনের সকল সক্রিয় রাইডারের কাছে নোটিফিকেশন যাবে এবং যিনি সবার আগে গ্রহণ করবেন অর্ডারটি তার নামে লক হয়ে যাবে।' : 'Broadcasts to all active delivery riders. The first courier to accept the pickup request secures the order run.' ?></span>
                    </div>
                </label>

                <!-- Option 2: Manual Select -->
                <label class="flex items-start space-x-3 p-3.5 rounded-2xl border border-slate-200 bg-white cursor-pointer hover:bg-slate-50 transition">
                    <input type="radio" name="dispatch_type" value="manual" onchange="toggleRiderDropdown(true)" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                    <div>
                        <strong class="text-slate-900 font-bold block flex items-center space-x-1.5">
                            <i class="fa-solid fa-user-check text-emerald-600"></i>
                            <span><?= is_bangla() ? 'সরাসরি নির্দিষ্ট রাইডার নির্ধারণ' : 'Directly Assign Specific Courier' ?></span>
                        </strong>
                        <span class="text-slate-500 text-[11px] block mt-0.5"><?= is_bangla() ? 'তালিকা থেকে সরাসরি আপনার পছন্দের রাইডার বেছে নিন।' : 'Manually pick a preferred courier rider from your fleet list.' ?></span>
                    </div>
                </label>
            </div>

            <!-- Manual Rider Select Dropdown -->
            <div id="manual-rider-select" class="hidden space-y-1.5 pt-2">
                <label class="block font-bold text-slate-700"><?= is_bangla() ? 'ডেলিভারি রাইডার বেছে নিন' : 'Select Courier Rider' ?> *</label>
                <select name="rider_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 text-xs focus:bg-white focus:ring-2 focus:ring-emerald-500">
                    <?php if (!empty($riders)): ?>
                        <?php foreach ($riders as $rd): ?>
                            <option value="<?= $rd['user_id'] ?>">
                                <?= sanitize($rd['name']) ?> • <?= sanitize($rd['vehicle_type'] ?? 'Motorcycle') ?> • Zone: <?= sanitize($rd['current_zone'] ?? 'Dhaka') ?> (<?= $rd['active_tasks'] ?> active)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="7">Speedy Courier Rider (Default)</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeDispatchModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl">
                    <?= __('common.cancel') ?>
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                    <span><?= is_bangla() ? 'রাইডারের জন্য প্রস্তুত নিশ্চিত করুন' : 'Confirm Courier Dispatch' ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDispatchModal(orderId, orderNumber, city, address) {
    document.getElementById('dispatch_order_id').value = orderId;
    document.getElementById('dispatch_order_info').innerText = '#' + orderNumber;
    document.getElementById('dispatch_dest_info').innerText = address + ', ' + city;
    document.getElementById('dispatch-modal').classList.remove('hidden');
}

function closeDispatchModal() {
    document.getElementById('dispatch-modal').classList.add('hidden');
}

function toggleRiderDropdown(show) {
    const dropdown = document.getElementById('manual-rider-select');
    if (show) {
        dropdown.classList.remove('hidden');
    } else {
        dropdown.classList.add('hidden');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const focusedId = "<?= (int)$focusedOrderId ?>";
    if (focusedId && focusedId > 0) {
        const el = document.getElementById(`order-card-${focusedId}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

