<?php
// Administrator Dashboard (Bilingual)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role(['admin', 'owner']);
$currentUser = current_user();
$db = get_db();

// Admin KPIs
$pendingVendors = (int)$db->query("SELECT COUNT(*) FROM vendor_profiles WHERE verification_status = 'pending'")->fetchColumn();
$pendingProducts = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 'pending_approval'")->fetchColumn();
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalCategories = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Recent Pending Approvals
$pendingVendorList = $db->query("
    SELECT vp.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone
    FROM vendor_profiles vp
    JOIN users u ON vp.user_id = u.id
    WHERE vp.verification_status = 'pending'
    LIMIT 5
")->fetchAll();

$pageTitle = __('admin.portal_title');
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-8 bg-slate-50">
        
        <div class="bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 block mb-1"><?= __('admin.portal_title') ?></span>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight"><?= __('admin.portal_title') ?></h1>
                <p class="text-xs text-slate-300 mt-1"><?= __('auth.role_admin') ?>: <strong class="text-white"><?= sanitize($currentUser['name']) ?></strong></p>
            </div>

            <div class="flex items-center space-x-3">
                <a href="vendors.php" class="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-xl shadow-md transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-store"></i>
                    <span><?= __('admin.vendor_approvals') ?></span>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block"><?= __('admin.vendor_approvals') ?></span>
                <span class="text-2xl font-extrabold <?= $pendingVendors > 0 ? 'text-amber-600' : 'text-slate-900' ?>"><?= format_num_i18n($pendingVendors) ?></span>
                <p class="text-[11px] text-slate-400"><?= __('order.status_pending') ?></p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block"><?= __('admin.product_approvals') ?></span>
                <span class="text-2xl font-extrabold <?= $pendingProducts > 0 ? 'text-amber-600' : 'text-slate-900' ?>"><?= format_num_i18n($pendingProducts) ?></span>
                <p class="text-[11px] text-slate-400"><?= __('order.status_pending') ?></p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block"><?= __('admin.orders_status') ?></span>
                <span class="text-2xl font-extrabold text-emerald-600"><?= format_num_i18n($totalOrders) ?></span>
                <p class="text-[11px] text-slate-400"><?= __('common.details') ?></p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block"><?= __('admin.categories') ?></span>
                <span class="text-2xl font-extrabold text-slate-900"><?= format_num_i18n($totalCategories) ?></span>
                <p class="text-[11px] text-slate-400"><?= __('common.active') ?></p>
            </div>
        </div>

        <!-- Pending Vendor Queue -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                    <i class="fa-solid fa-clock text-amber-500"></i>
                    <span><?= __('admin.vendor_approvals') ?></span>
                </h3>
                <a href="vendors.php" class="text-xs text-emerald-700 font-bold hover:underline"><?= __('common.details') ?> &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="py-2.5"><?= __('vendor.nursery_name') ?></th>
                            <th class="py-2.5"><?= __('auth.role_vendor') ?></th>
                            <th class="py-2.5"><?= __('checkout.city') ?></th>
                            <th class="py-2.5"><?= __('common.date') ?></th>
                            <th class="py-2.5 text-right"><?= __('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php if (!empty($pendingVendorList)): ?>
                            <?php foreach ($pendingVendorList as $pv): ?>
                                <tr>
                                    <td class="py-3 font-bold text-slate-900"><?= sanitize($pv['nursery_name']) ?></td>
                                    <td class="py-3"><?= sanitize($pv['owner_name']) ?> (<?= sanitize($pv['owner_email']) ?>)</td>
                                    <td class="py-3"><?= sanitize($pv['address']) ?></td>
                                    <td class="py-3 text-slate-400"><?= time_ago($pv['created_at']) ?></td>
                                    <td class="py-3 text-right">
                                        <a href="vendors.php?id=<?= $pv['id'] ?>" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg shadow-xs transition">
                                            <?= __('common.details') ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400"><?= __('customer.no_orders_yet') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
