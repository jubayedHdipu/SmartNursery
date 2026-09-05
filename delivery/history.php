<?php
// Delivery Staff Completed Runs History

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role(['delivery', 'admin', 'owner']);
$currentUser = current_user();
$db = get_db();
$isDeliveryStaff = ($currentUser['role_name'] === 'delivery');

if ($isDeliveryStaff) {
    $pStmt = $db->prepare("SELECT id FROM delivery_staff_profiles WHERE user_id = :uid");
    $pStmt->execute(['uid' => $currentUser['id']]);
    $staffProfileId = (int)$pStmt->fetchColumn();

    // Fetch Completed Deliveries for this rider
    $stmt = $db->prepare("
        SELECT da.*, o.order_number, o.grand_total, o.shipping_name, o.shipping_address, o.shipping_city,
               vp.nursery_name
        FROM delivery_assignments da
        JOIN orders o ON da.order_id = o.id
        JOIN vendor_profiles vp ON o.vendor_id = vp.id
        WHERE (da.delivery_staff_id = :did OR da.delivery_staff_id = :pid) AND da.status = 'delivered'
        ORDER BY da.delivered_at DESC
    ");
    $stmt->execute(['did' => $currentUser['id'], 'pid' => $staffProfileId]);
    $history = $stmt->fetchAll();
} else {
    // Admin / Owner fleet completed deliveries
    $stmt = $db->query("
        SELECT da.*, o.order_number, o.grand_total, o.shipping_name, o.shipping_address, o.shipping_city,
               vp.nursery_name, rider.name AS assigned_rider_name
        FROM delivery_assignments da
        JOIN orders o ON da.order_id = o.id
        JOIN vendor_profiles vp ON o.vendor_id = vp.id
        LEFT JOIN users rider ON da.delivery_staff_id = rider.id
        WHERE da.status = 'delivered'
        ORDER BY da.delivered_at DESC
    ");
    $history = $stmt->fetchAll();
}

$pageTitle = is_bangla() ? 'ডেলিভারি ইতিহাস' : 'Delivery History';
$activePage = 'history';

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50">
        
        <div class="border-b border-slate-200 pb-5">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Completed Logs</span>
            <h1 class="text-2xl font-extrabold text-slate-900">Delivery Performance History</h1>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Total Completed: <strong class="text-slate-800"><?= count($history) ?></strong></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4">Order #</th>
                            <th class="p-4">Nursery Pickup</th>
                            <th class="p-4">Customer Destination</th>
                            <th class="p-4">Delivered Time</th>
                            <th class="p-4">Delivery Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php if (!empty($history)): ?>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td class="p-4 font-mono font-bold text-slate-900">#<?= sanitize($h['order_number']) ?></td>
                                    <td class="p-4"><?= sanitize($h['nursery_name']) ?></td>
                                    <td class="p-4"><?= sanitize($h['shipping_name']) ?> (<?= sanitize($h['shipping_city']) ?>)</td>
                                    <td class="p-4 font-mono text-slate-500"><?= format_date($h['delivered_at']) ?></td>
                                    <td class="p-4 text-emerald-700"><?= sanitize($h['delivery_notes'] ?: 'Completed successfully') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400">No completed delivery history records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
