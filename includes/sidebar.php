<?php
// Unified Dashboard Sidebar (Bilingual)

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

$currentUser = current_user();
$role = $currentUser['role_name'] ?? 'customer';
$activePage = $activePage ?? 'dashboard';
$activeLang = current_lang();
?>

<aside class="w-full lg:w-64 bg-slate-900 text-slate-200 flex flex-col justify-between border-r border-slate-800 shrink-0">
    
    <div class="p-5 space-y-6">
        
        <!-- User Brief Card -->
        <div class="bg-slate-800/80 rounded-2xl p-4 border border-slate-700/60 flex items-center space-x-3">
            <?php if (!empty($currentUser['avatar'])): ?>
                <img src="<?= sanitize(get_avatar_url($currentUser['avatar'])) ?>" class="w-10 h-10 rounded-xl object-cover shadow-xs border border-emerald-500/40 shrink-0">
            <?php else: ?>
                <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white font-bold flex items-center justify-center shadow-xs text-sm shrink-0">
                    <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="overflow-hidden">
                <h4 class="text-xs font-bold text-white truncate"><?= sanitize($currentUser['name'] ?? 'User') ?></h4>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 uppercase tracking-wider">
                    <?= __('role.' . $role, sanitize($currentUser['role_display'] ?? ucfirst($role))) ?>
                </span>
            </div>
        </div>

        <!-- Navigation Links by Role -->
        <nav class="space-y-1 text-xs font-medium">

            <?php if ($role === 'owner'): ?>
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-3 pt-2 pb-1"><?= __('owner.dashboard_title') ?></div>
                <a href="<?= BASE_URL ?>/owner/index.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'dashboard' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-chart-pie w-4 text-center"></i>
                    <span><?= __('owner.analytics') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/owner/admins.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'admins' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-user-shield w-4 text-center"></i>
                    <span><?= __('owner.admin_managers') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/owner/cms.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'cms' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-pen-nib w-4 text-center"></i>
                    <span><?= __('owner.cms') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/owner/settings.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'settings' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-sliders w-4 text-center"></i>
                    <span><?= __('owner.settings') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/owner/audit-logs.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'audit' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i>
                    <span><?= __('owner.audit_logs') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/notifications.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'notifications' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-bell w-4 text-center"></i>
                    <span><?= is_bangla() ? 'বিজ্ঞপ্তিসমূহ' : 'Notifications' ?></span>
                </a>

            <?php elseif ($role === 'admin'): ?>
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-3 pt-2 pb-1"><?= __('admin.dashboard_title') ?></div>
                <a href="<?= BASE_URL ?>/admin/index.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'dashboard' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-gauge-high w-4 text-center"></i>
                    <span><?= __('nav.admin_panel') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/admin/vendors.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'vendors' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-store w-4 text-center"></i>
                    <span><?= __('admin.vendor_approvals') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/admin/products.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'products' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-leaf w-4 text-center"></i>
                    <span><?= __('admin.product_approvals') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/admin/categories.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'categories' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-layer-group w-4 text-center"></i>
                    <span><?= __('admin.categories') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/admin/orders.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'orders' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-receipt w-4 text-center"></i>
                    <span><?= __('admin.all_orders') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/admin/customers.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'customers' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-users w-4 text-center"></i>
                    <span><?= __('admin.customers') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/admin/riders.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'riders' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-motorcycle w-4 text-center"></i>
                    <span><?= __('admin.riders') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/notifications.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'notifications' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-bell w-4 text-center"></i>
                    <span><?= is_bangla() ? 'বিজ্ঞপ্তিসমূহ' : 'Notifications' ?></span>
                </a>
                <a href="<?= BASE_URL ?>/admin/profile.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'profile' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-user-gear w-4 text-center"></i>
                    <span><?= is_bangla() ? 'অ্যাডমিন প্রোফাইল' : 'Admin Profile' ?></span>
                </a>

            <?php elseif ($role === 'vendor'): ?>
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-3 pt-2 pb-1"><?= __('vendor.dashboard_title') ?></div>
                <a href="<?= BASE_URL ?>/vendor/index.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'dashboard' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center"></i>
                    <span><?= __('vendor.sales_overview') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/vendor/products.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'products' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-seedling w-4 text-center"></i>
                    <span><?= __('vendor.plant_catalog') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/vendor/product-add.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'product-add' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-wand-magic-sparkles text-emerald-400 w-4 text-center"></i>
                    <span><?= __('vendor.add_plant_ai') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/vendor/inventory.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'inventory' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-boxes-stacked w-4 text-center"></i>
                    <span><?= __('vendor.inventory_mgmt') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/vendor/orders.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'orders' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-boxes-packing w-4 text-center"></i>
                    <span><?= __('vendor.orders_fulfillment') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/vendor/chat.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'chat' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-comments w-4 text-center"></i>
                    <span><?= __('vendor.customer_chat') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/notifications.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'notifications' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-bell w-4 text-center"></i>
                    <span><?= is_bangla() ? 'বিজ্ঞপ্তিসমূহ' : 'Notifications' ?></span>
                </a>
                <a href="<?= BASE_URL ?>/vendor/profile.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'profile' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-shop w-4 text-center"></i>
                    <span><?= __('vendor.store_profile') ?></span>
                </a>

            <?php elseif ($role === 'delivery'): ?>
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-3 pt-2 pb-1"><?= __('delivery.dashboard_title') ?></div>
                <a href="<?= BASE_URL ?>/delivery/index.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'dashboard' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-motorcycle w-4 text-center"></i>
                    <span><?= __('delivery.dashboard_title') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/delivery/deliveries.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'deliveries' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-route w-4 text-center"></i>
                    <span><?= __('delivery.active_tasks') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/delivery/history.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'history' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-clipboard-check w-4 text-center"></i>
                    <span><?= __('delivery.history') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/notifications.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'notifications' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-bell w-4 text-center"></i>
                    <span><?= is_bangla() ? 'বিজ্ঞপ্তিসমূহ' : 'Notifications' ?></span>
                </a>
                <a href="<?= BASE_URL ?>/delivery/profile.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl transition <?= $activePage === 'profile' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-900/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i class="fa-solid fa-id-badge w-4 text-center"></i>
                    <span><?= is_bangla() ? 'রাইডার প্রোফাইল' : 'Rider Profile' ?></span>
                </a>
            <?php endif; ?>

        </nav>
    </div>

    <!-- Bottom Actions & Language Switcher -->
    <div class="p-5 border-t border-slate-800 space-y-3 text-xs">
        
        <!-- Sidebar Language Toggle -->
        <div class="flex items-center justify-between bg-slate-800/80 p-1.5 rounded-xl border border-slate-700/50">
            <span class="text-slate-400 text-[11px] px-1"><i class="fa-solid fa-globe mr-1"></i><?= __('lang.switch_label') ?>:</span>
            <div class="inline-flex rounded-lg bg-slate-900 p-0.5 text-[11px]">
                <a href="<?= lang_switch_url('en') ?>" class="px-2 py-0.5 rounded-md transition <?= $activeLang === 'en' ? 'bg-emerald-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">EN</a>
                <a href="<?= lang_switch_url('bn') ?>" class="px-2 py-0.5 rounded-md transition <?= $activeLang === 'bn' ? 'bg-emerald-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">বাং</a>
            </div>
        </div>

        <a href="<?= BASE_URL ?>/index.php" class="flex items-center space-x-2 text-slate-400 hover:text-emerald-400 px-2 py-1.5 transition">
            <i class="fa-solid fa-arrow-left text-[11px]"></i>
            <span><?= __('common.back_to_store') ?></span>
        </a>
        <a href="<?= BASE_URL ?>/logout.php" class="flex items-center space-x-2 text-rose-400 hover:text-rose-300 px-2 py-1.5 transition font-semibold">
            <i class="fa-solid fa-arrow-right-from-bracket text-[11px]"></i>
            <span><?= __('common.logout') ?></span>
        </a>
    </div>

</aside>
