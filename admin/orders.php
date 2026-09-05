<?php
// Admin Platform Orders Oversight

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifications.php';

require_role(['admin', 'owner']);
$currentUser = current_user();
$db = get_db();

// Handle Rider Assignment from Admin
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_rider') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $riderId = (int)($_POST['rider_id'] ?? 0);
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf) && $orderId > 0 && $riderId > 0) {
        $ordStmt = $db->prepare("SELECT * FROM orders WHERE id = :id");
        $ordStmt->execute(['id' => $orderId]);
        $order = $ordStmt->fetch();

        $rStmt = $db->prepare("SELECT name FROM users WHERE id = :id AND role_id = 5");
        $rStmt->execute(['id' => $riderId]);
        $rider = $rStmt->fetch();

        if ($order && $rider) {
            $db->prepare("
                INSERT INTO delivery_assignments (order_id, delivery_staff_id, status, assigned_at)
                VALUES (:oid, :did, 'assigned', NOW())
                ON DUPLICATE KEY UPDATE delivery_staff_id = :did2, status = 'assigned', assigned_at = NOW()
            ")->execute(['oid' => $orderId, 'did' => $riderId, 'did2' => $riderId]);

            if (in_array($order['order_status'], ['Pending', 'Vendor Accepted', 'Preparing', 'Ready for Pickup'])) {
                $db->prepare("UPDATE orders SET order_status = 'Assigned to Delivery', updated_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);
            }

            // Dispatch notification to Customer and Rider
            notify_order_lifecycle($orderId, 'assigned', ['rider_name' => $rider['name'], 'rider_phone' => $rider['phone']]);

            log_audit('admin_assign_rider', 'order', $orderId, "Admin assigned order #{$order['order_number']} to rider {$rider['name']}");
            set_flash('success', is_bangla() ? "অর্ডার #{$order['order_number']} সফলভাবে রাইডার '{$rider['name']}' এর আন্ডারে অ্যাসাইন করা হয়েছে।" : "Order #{$order['order_number']} assigned to courier rider {$rider['name']}.");
        }
    }
    redirect('admin/orders.php' . (!empty($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

// Fetch Active Delivery Couriers for Selection
$riders = $db->query("
    SELECT u.id AS user_id, u.name, u.phone,
           dsp.vehicle_type, dsp.current_zone, dsp.status AS staff_status,
           (SELECT COUNT(*) FROM delivery_assignments da WHERE (da.delivery_staff_id = u.id OR da.delivery_staff_id = dsp.id) AND da.status IN ('assigned', 'picked_up', 'out_for_delivery')) AS active_tasks
    FROM users u
    LEFT JOIN delivery_staff_profiles dsp ON u.id = dsp.user_id
    WHERE u.role_id = 5 AND u.status = 'active'
    ORDER BY active_tasks ASC, u.id ASC
")->fetchAll();

// Filter Status
$statusFilter = trim($_GET['status'] ?? '');
$where = '1=1';
$params = [];

if (!empty($statusFilter)) {
    $where = "o.order_status = :status";
    $params['status'] = $statusFilter;
}

$stmt = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.phone AS customer_phone,
           vp.nursery_name,
           p.payment_method, p.payment_status,
           da.status AS delivery_status, da.delivery_staff_id,
           rider.name AS rider_name, rider.phone AS rider_phone
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    JOIN vendor_profiles vp ON o.vendor_id = vp.id
    LEFT JOIN payments p ON o.id = p.order_id
    LEFT JOIN delivery_assignments da ON o.id = da.order_id
    LEFT JOIN users rider ON da.delivery_staff_id = rider.id
    WHERE {$where}
    ORDER BY o.id DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = is_bangla() ? 'সকল অর্ডার মনিটরিং' : 'Orders Oversight';
$activePage = 'orders';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'প্ল্যাটফর্ম লেনদেন ও অর্ডার' : 'Platform Transactions' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'সকল অর্ডার মনিটরিং ও ডিসপ্যাচ' : 'Orders Monitoring & Dispatch' ?></h1>
                <p class="text-xs text-slate-500 mt-0.5"><?= is_bangla() ? 'অর্ডারের লাইফসাইকেল ট্র্যাক করুন এবং সরাসরি রাইডার অ্যাসাইন করুন' : 'Track order lifecycles, monitor payments, and manage courier dispatches' ?></p>
            </div>

            <!-- Status Filter -->
            <div class="flex items-center space-x-2 text-xs">
                <span class="text-slate-500 font-medium"><?= is_bangla() ? 'স্ট্যাটাস ফিল্টার:' : 'Filter Status:' ?></span>
                <select onchange="window.location.href='orders.php?status=' + this.value" 
                        class="bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value=""><?= is_bangla() ? 'সকল স্ট্যাটাস' : 'All Statuses' ?></option>
                    <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Vendor Accepted" <?= $statusFilter === 'Vendor Accepted' ? 'selected' : '' ?>>Vendor Accepted</option>
                    <option value="Preparing" <?= $statusFilter === 'Preparing' ? 'selected' : '' ?>>Preparing</option>
                    <option value="Ready for Pickup" <?= $statusFilter === 'Ready for Pickup' ? 'selected' : '' ?>>Ready for Pickup</option>
                    <option value="Assigned to Delivery" <?= $statusFilter === 'Assigned to Delivery' ? 'selected' : '' ?>>Assigned to Delivery</option>
                    <option value="Out for Delivery" <?= $statusFilter === 'Out for Delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                    <option value="Delivered" <?= $statusFilter === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4"><?= is_bangla() ? 'অর্ডার #' : 'Order #' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'গ্রাহক' : 'Customer' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'নার্সারি দোকান' : 'Nursery Store' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'মোট মূল্য' : 'Grand Total' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'অর্ডার স্ট্যাটাস' : 'Order Status' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'ডেলিভারি রাইডার' : 'Courier Rider' ?></th>
                            <th class="p-4 text-right"><?= is_bangla() ? 'অ্যাকশন ও ট্র্যাকিং' : 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td class="p-4 font-mono font-bold text-slate-900">#<?= sanitize($o['order_number']) ?></td>
                                    <td class="p-4"><?= sanitize($o['shipping_name']) ?><br><span class="text-slate-400 text-[11px]"><?= sanitize($o['shipping_phone']) ?></span></td>
                                    <td class="p-4 font-semibold text-slate-800"><?= sanitize(vendor_display_name($o['nursery_name'])) ?></td>
                                    <td class="p-4 font-bold text-emerald-600"><?= format_currency($o['grand_total']) ?></td>
                                    <td class="p-4"><?= render_order_status_badge($o['order_status']) ?></td>
                                    <td class="p-4">
                                        <?php if (!empty($o['rider_name'])): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                                <i class="fa-solid fa-motorcycle mr-1 text-sky-600"></i><?= sanitize($o['rider_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-[11px]"><?= is_bangla() ? 'বরাদ্দ হয়নি' : 'Unassigned' ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-right space-x-1.5">
                                        <!-- Assign / Reassign Rider Button -->
                                        <?php if ($o['order_status'] !== 'Cancelled'): ?>
                                            <button type="button" 
                                                    onclick="openAdminAssignModal(<?= $o['id'] ?>, '<?= sanitize($o['order_number']) ?>', <?= (int)($o['delivery_staff_id'] ?? 0) ?>, '<?= sanitize($o['shipping_city']) ?>')"
                                                    class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold rounded-lg transition text-xs inline-flex items-center space-x-1">
                                                <i class="fa-solid fa-motorcycle text-[10px]"></i>
                                                <span><?= !empty($o['rider_name']) ? (is_bangla() ? 'রাইডার বদল' : 'Change') : (is_bangla() ? 'রাইডার দিন' : 'Assign') ?></span>
                                            </button>
                                        <?php endif; ?>

                                        <a href="<?= BASE_URL ?>/track-order.php?order_number=<?= urlencode($o['order_number']) ?>" target="_blank"
                                           class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg inline-flex items-center space-x-1" title="Track Timeline">
                                            <i class="fa-solid fa-route text-[10px]"></i>
                                            <span class="hidden sm:inline"><?= is_bangla() ? 'ট্র্যাকিং' : 'Track' ?></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400"><?= is_bangla() ? 'কোন অর্ডার পাওয়া যায়নি।' : 'No orders match the selected filter.' ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<!-- Interactive Admin Assign Rider Modal -->
<div id="admin-assign-modal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 sm:p-8 space-y-5 animate-scale-in">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-motorcycle text-emerald-600"></i>
                <span><?= is_bangla() ? 'অর্ডারে রাইডার বরাদ্দ করুন' : 'Assign Delivery Courier' ?></span>
            </h3>
            <button type="button" onclick="closeAdminAssignModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="orders.php" method="POST" class="space-y-4 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_rider">
            <input type="hidden" name="order_id" id="admin_assign_order_id" value="">

            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-1">
                <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider"><?= is_bangla() ? 'অর্ডার নম্বর' : 'Selected Order' ?></span>
                <p class="font-bold text-slate-800" id="admin_assign_order_info">#ORD-...</p>
                <p class="text-slate-500 text-[11px]" id="admin_assign_city_info">Dhaka</p>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ডেলিভারি রাইডার বেছে নিন' : 'Select Delivery Courier' ?> *</label>
                <select name="rider_id" id="admin_rider_select" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 text-xs focus:bg-white focus:ring-2 focus:ring-emerald-500">
                    <option value=""><?= is_bangla() ? '-- একজন রাইডার নির্বাচন করুন --' : '-- Choose a courier rider --' ?></option>
                    <?php if (!empty($riders)): ?>
                        <?php foreach ($riders as $rd): ?>
                            <option value="<?= $rd['user_id'] ?>">
                                <?= sanitize($rd['name']) ?> (<?= sanitize($rd['vehicle_type'] ?? 'Motorcycle') ?>) • Zone: <?= sanitize($rd['current_zone'] ?? 'Dhaka') ?> • <?= $rd['active_tasks'] ?> active
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeAdminAssignModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl">
                    <?= __('common.cancel') ?>
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition">
                    <?= is_bangla() ? 'রাইডার নিশ্চিত করুন' : 'Assign Courier' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdminAssignModal(orderId, orderNumber, currentRiderId, city) {
    document.getElementById('admin_assign_order_id').value = orderId;
    document.getElementById('admin_assign_order_info').innerText = '#' + orderNumber;
    document.getElementById('admin_assign_city_info').innerText = city ? ('City: ' + city) : '';
    if (currentRiderId > 0) {
        document.getElementById('admin_rider_select').value = currentRiderId;
    } else {
        document.getElementById('admin_rider_select').selectedIndex = 0;
    }
    document.getElementById('admin-assign-modal').classList.remove('hidden');
}

function closeAdminAssignModal() {
    document.getElementById('admin-assign-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

