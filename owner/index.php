<?php
// Owner Platform Analytics Dashboard (Bilingual)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('owner');
$currentUser = current_user();
$db = get_db();

// Platform Analytics Metrics
$totalGrossSales = (float)$db->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();
$platformCommissionRate = (float)get_setting('platform_commission_rate', '10.00');
$estimatedCommission = $totalGrossSales * ($platformCommissionRate / 100);

$totalOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalNurseriesCount = (int)$db->query("SELECT COUNT(*) FROM vendor_profiles WHERE verification_status = 'verified'")->fetchColumn();
$totalCustomersCount = (int)$db->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'customer'")->fetchColumn();

// Fetch Top Nurseries by Sales
$topNurseries = $db->query("
    SELECT vp.nursery_name, vp.city, vp.rating_avg,
           COUNT(o.id) as order_count,
           COALESCE(SUM(o.total_amount), 0) as gross_revenue
    FROM vendor_profiles vp
    LEFT JOIN orders o ON vp.id = o.vendor_id AND o.order_status != 'Cancelled'
    GROUP BY vp.id
    ORDER BY gross_revenue DESC
    LIMIT 5
")->fetchAll();

$pageTitle = __('owner.portal_title');
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Chart.js CDN for Analytics Visualization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-8 bg-slate-50">
        
        <div class="bg-gradient-to-r from-emerald-950 via-slate-900 to-teal-950 text-white rounded-3xl p-6 sm:p-8 shadow-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 block mb-1"><?= __('owner.portal_title') ?></span>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight"><?= __('owner.financial_overview') ?></h1>
                <p class="text-xs text-slate-300 mt-1"><?= __('owner.commission_rate') ?>: <strong class="text-emerald-400"><?= format_num_i18n($platformCommissionRate) ?>%</strong> • <?= __('owner.system_settings') ?></p>
            </div>

            <div class="flex items-center space-x-3">
                <a href="settings.php" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-xl shadow-md transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-sliders"></i>
                    <span><?= __('owner.system_settings') ?></span>
                </a>
            </div>
        </div>

        <!-- 4 Key Financial & Growth KPIs -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= __('owner.gross_revenue') ?></span>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-money-bill-trend-up"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-slate-900"><?= format_currency($totalGrossSales) ?></div>
                <span class="text-[11px] text-slate-400"><?= __('admin.orders_status') ?></span>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= __('owner.net_commission') ?></span>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-emerald-600"><?= format_currency($estimatedCommission) ?></div>
                <span class="text-[11px] text-emerald-700 font-semibold"><?= format_num_i18n($platformCommissionRate) ?>% <?= __('owner.commission_rate') ?></span>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= __('owner.registered_nurseries') ?></span>
                    <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-store"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-slate-900"><?= format_num_i18n($totalNurseriesCount) ?></div>
                <span class="text-[11px] text-slate-400"><?= __('common.active') ?></span>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= __('owner.customer_base') ?></span>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-slate-900"><?= format_num_i18n($totalCustomersCount) ?></div>
                <span class="text-[11px] text-slate-400"><?= __('auth.role_customer') ?></span>
            </div>

        </div>

        <!-- Top Nurseries by Sales -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                    <i class="fa-solid fa-trophy text-amber-500"></i>
                    <span><?= __('owner.top_nurseries') ?></span>
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="py-2.5"><?= __('vendor.nursery_name') ?></th>
                            <th class="py-2.5"><?= __('checkout.city') ?></th>
                            <th class="py-2.5"><?= __('vendor.rating') ?></th>
                            <th class="py-2.5"><?= __('admin.orders_status') ?></th>
                            <th class="py-2.5 text-right"><?= __('vendor.total_sales') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php foreach ($topNurseries as $tn): ?>
                            <tr>
                                <td class="py-3 font-bold text-slate-900"><?= sanitize(vendor_display_name($tn['nursery_name'])) ?></td>
                                <td class="py-3"><?= is_bangla() && ($tn['city'] === 'Dhaka' || empty($tn['city'])) ? 'ঢাকা' : sanitize($tn['city'] ?? 'Dhaka') ?></td>
                                <td class="py-3 text-amber-500 font-bold">★ <?= format_num_i18n($tn['rating_avg']) ?></td>
                                <td class="py-3"><?= format_num_i18n($tn['order_count']) ?></td>
                                <td class="py-3 text-right font-bold text-emerald-600"><?= format_currency($tn['gross_revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
