<?php
// Nursery Vendor Dashboard (Bilingual)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('vendor');
$currentUser = current_user();
$db = get_db();

// Fetch Vendor Profile
$vStmt = $db->prepare("SELECT * FROM vendor_profiles WHERE user_id = :uid");
$vStmt->execute(['uid' => $currentUser['id']]);
$vendor = $vStmt->fetch();

if (!$vendor) {
    set_flash('error', 'Vendor profile missing.');
    redirect('index.php');
}

$vendorId = $vendor['id'];

// Fetch KPI Metrics with safe parameterized queries
$prodStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = :vid AND status != 'archived'");
$prodStmt->execute(['vid' => $vendorId]);
$totalProducts = (int)$prodStmt->fetchColumn();

$pendingStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE vendor_id = :vid AND order_status = 'Pending'");
$pendingStmt->execute(['vid' => $vendorId]);
$pendingOrders = (int)$pendingStmt->fetchColumn();

$salesStmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE vendor_id = :vid AND order_status NOT IN ('Cancelled', 'Vendor Rejected')");
$salesStmt->execute(['vid' => $vendorId]);
$totalSales = (float)$salesStmt->fetchColumn();

$lowStockStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = :vid AND stock_quantity <= 5 AND status = 'approved'");
$lowStockStmt->execute(['vid' => $vendorId]);
$lowStockCount = (int)$lowStockStmt->fetchColumn();

// Fetch Recent Incoming Orders for this Vendor
$recentOrdersStmt = $db->prepare("
    SELECT o.*, u.name as customer_name
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    WHERE o.vendor_id = :vid
    ORDER BY o.id DESC
    LIMIT 5
");
$recentOrdersStmt->execute(['vid' => $vendorId]);
$recentOrders = $recentOrdersStmt->fetchAll();

$pageTitle = __('vendor.portal_title');
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-8 bg-slate-50">
        
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-emerald-900 via-teal-900 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 block mb-1"><?= __('vendor.portal_title') ?></span>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight"><?= sanitize($vendor['nursery_name']) ?></h1>
                <p class="text-xs text-slate-300 mt-1"><?= __('vendor.verification_status') ?>: <strong class="text-emerald-400 uppercase"><?= sanitize($vendor['verification_status']) ?></strong> • <?= __('vendor.rating') ?>: <strong class="text-amber-400">★ <?= format_num_i18n($vendor['rating_avg']) ?></strong></p>
            </div>

            <div class="flex items-center space-x-3">
                <a href="product-add.php" class="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-xl shadow-md transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span><?= __('vendor.add_plant') ?></span>
                </a>
            </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= __('vendor.total_sales') ?></span>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-slate-900"><?= format_currency($totalSales) ?></div>
                <span class="text-[11px] text-emerald-600 font-semibold"><?= __('admin.orders_status') ?></span>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= __('vendor.pending_orders') ?></span>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-slate-900"><?= format_num_i18n($pendingOrders) ?></div>
                <span class="text-[11px] text-amber-600 font-semibold"><?= __('order.status_pending') ?></span>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= __('vendor.catalog_plants') ?></span>
                    <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-seedling"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-slate-900"><?= format_num_i18n($totalProducts) ?></div>
                <span class="text-[11px] text-slate-400"><?= __('shop.title') ?></span>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= __('vendor.low_stock') ?></span>
                    <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold <?= $lowStockCount > 0 ? 'text-rose-600' : 'text-slate-900' ?>"><?= format_num_i18n($lowStockCount) ?></div>
                <span class="text-[11px] text-slate-400">&le; <?= format_num_i18n(5) ?></span>
            </div>

        </div>

        <!-- Recent Incoming Orders Table -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                    <i class="fa-solid fa-boxes-packing text-emerald-600"></i>
                    <span><?= __('vendor.incoming_orders') ?></span>
                </h3>
                <a href="orders.php" class="text-xs text-emerald-700 font-bold hover:underline"><?= __('common.details') ?> &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="py-2.5"><?= __('order.order_number') ?></th>
                            <th class="py-2.5"><?= __('auth.role_customer') ?></th>
                            <th class="py-2.5"><?= __('cart.total') ?></th>
                            <th class="py-2.5"><?= __('order.status') ?></th>
                            <th class="py-2.5"><?= __('common.date') ?></th>
                            <th class="py-2.5 text-right"><?= __('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $ro): ?>
                                <tr>
                                    <td class="py-3 font-mono font-bold text-slate-900">#<?= sanitize($ro['order_number']) ?></td>
                                    <td class="py-3"><?= sanitize($ro['customer_name']) ?></td>
                                    <td class="py-3 font-bold text-emerald-600"><?= format_currency($ro['grand_total']) ?></td>
                                    <td class="py-3"><?= render_order_status_badge($ro['order_status']) ?></td>
                                    <td class="py-3 text-slate-400"><?= time_ago($ro['created_at']) ?></td>
                                    <td class="py-3 text-right">
                                        <a href="orders.php?order_id=<?= $ro['id'] ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-lg">
                                            <?= __('common.details') ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400"><?= __('customer.no_orders_yet') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
