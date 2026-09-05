<?php
// Unified Role-Based Notification Center

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/notifications.php';

require_login();
$currentUser = current_user();
$db = get_db();

// Handle filter category
$activeTab = trim($_GET['type'] ?? 'all');
$validTypes = ['all', 'order', 'product', 'vendor', 'delivery', 'chat', 'system'];
if (!in_array($activeTab, $validTypes, true)) {
    $activeTab = 'all';
}

// Fetch notifications
$allNotifications = get_user_all_notifications($currentUser['id'], $activeTab, 100);
$unreadCount = count_unread_notifications($currentUser['id']);
$totalCount = count($allNotifications);

// Role-specific hub title & metadata
$role = $currentUser['role_name'] ?? 'customer';

$hubMeta = match($role) {
    'owner' => [
        'title'    => is_bangla() ? 'ওনার নোটিফিকেশন সেন্টার' : 'Owner Notification Center',
        'subtitle' => is_bangla() ? 'প্ল্যাটফর্মের রাজস্ব, নতুন নার্সারি আবেদন ও সিস্টেম অডিট নোটিশ' : 'Platform revenue, partner applications & system audit alerts',
        'badge'    => 'System Owner'
    ],
    'admin' => [
        'title'    => is_bangla() ? 'অ্যাডমিন নোটিফিকেশন হাব' : 'Administrator Notification Hub',
        'subtitle' => is_bangla() ? 'ভেন্ডর মডারেশন, কুরিয়ার ফ্লিট ও অর্ডার ডিসপ্যাচ সতর্কতা' : 'Vendor moderation queue, courier fleet & fulfillment dispatches',
        'badge'    => 'System Admin'
    ],
    'vendor' => [
        'title'    => is_bangla() ? 'নার্সারি নোটিফিকেশন ও অর্ডার' : 'Nursery Storefront Alerts & Orders',
        'subtitle' => is_bangla() ? 'নতুন কাস্টমার অর্ডার, স্টক সতর্কতা ও গ্রাহক অনুসন্ধান' : 'Incoming customer orders, low stock warnings & plant care inquiries',
        'badge'    => 'Nursery Vendor'
    ],
    'delivery' => [
        'title'    => is_bangla() ? 'কুরিয়ার ডিসপ্যাচ ও রাইডার টাস্ক' : 'Courier Logistics & Rider Tasks',
        'subtitle' => is_bangla() ? 'পিকআপ অ্যাসাইনমেন্ট, ডেলিভারি রুট ও ক্যাশ অন ডেলিভারি' : 'Package pickups, delivery dropoff routes & cash reconciliations',
        'badge'    => 'Delivery Rider'
    ],
    default => [
        'title'    => is_bangla() ? 'আমার অর্ডার ও উদ্ভিদ বিজ্ঞপ্তি' : 'My Orders & Botanical Notifications',
        'subtitle' => is_bangla() ? 'অর্ডার স্ট্যাটাস, কুরিয়ার ট্র্যাকিং ও নার্সারির পরামর্শ' : 'Real-time order updates, live rider tracking & nursery plant advice',
        'badge'    => 'Plant Collector'
    ]
};

$pageTitle = $hubMeta['title'];
$activePage = 'notifications';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex flex-col <?= in_array($role, ['owner', 'admin', 'vendor', 'delivery']) ? 'lg:flex-row' : '' ?>">
    
    <!-- Sidebar for Portal Roles -->
    <?php if (in_array($role, ['owner', 'admin', 'vendor', 'delivery'])): ?>
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <?php endif; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50 <?= !in_array($role, ['owner', 'admin', 'vendor', 'delivery']) ? 'max-w-6xl mx-auto w-full' : '' ?>">
        
        <!-- Header Banner -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= $hubMeta['badge'] ?></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                    <span class="text-xs font-semibold text-slate-500"><?= format_num_i18n($unreadCount) ?> <?= is_bangla() ? 'টি অপঠিত নোটিফিকেশন' : 'unread notifications' ?></span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900"><?= $hubMeta['title'] ?></h1>
                <p class="text-xs sm:text-sm text-slate-600"><?= $hubMeta['subtitle'] ?></p>
            </div>

            <div class="flex items-center space-x-3 shrink-0">
                <?php if ($unreadCount > 0): ?>
                    <button id="page-btn-mark-all-read" class="px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-xs rounded-xl border border-emerald-200 flex items-center space-x-2 transition cursor-pointer">
                        <i class="fa-solid fa-check-double text-xs"></i>
                        <span><?= is_bangla() ? 'সব পঠিত করুন' : 'Mark All as Read' ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Category Tabs -->
        <div class="flex items-center space-x-2 overflow-x-auto pb-2 text-xs scrollbar-none">
            <a href="notifications.php?type=all" 
               class="px-4 py-2 rounded-xl font-bold transition shrink-0 <?= $activeTab === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                <i class="fa-solid fa-bell mr-1.5"></i>
                <?= is_bangla() ? 'সব বিজ্ঞপ্তি' : 'All Notifications' ?>
            </a>

            <a href="notifications.php?type=order" 
               class="px-4 py-2 rounded-xl font-bold transition shrink-0 <?= $activeTab === 'order' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                <i class="fa-solid fa-boxes-packing mr-1.5"></i>
                <?= is_bangla() ? 'অর্ডার ও ডেলিভারি' : 'Orders & Shipments' ?>
            </a>

            <?php if (in_array($role, ['vendor', 'admin', 'owner'])): ?>
                <a href="notifications.php?type=product" 
                   class="px-4 py-2 rounded-xl font-bold transition shrink-0 <?= $activeTab === 'product' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                    <i class="fa-solid fa-seedling mr-1.5"></i>
                    <?= is_bangla() ? 'ইনভেন্টরি ও স্টক' : 'Inventory & Plants' ?>
                </a>
            <?php endif; ?>

            <?php if (in_array($role, ['owner', 'admin', 'vendor'])): ?>
                <a href="notifications.php?type=vendor" 
                   class="px-4 py-2 rounded-xl font-bold transition shrink-0 <?= $activeTab === 'vendor' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                    <i class="fa-solid fa-store mr-1.5"></i>
                    <?= is_bangla() ? 'নার্সারি পার্টনার' : 'Storefront & Nursery' ?>
                </a>
            <?php endif; ?>

            <?php if (in_array($role, ['delivery', 'admin', 'owner'])): ?>
                <a href="notifications.php?type=delivery" 
                   class="px-4 py-2 rounded-xl font-bold transition shrink-0 <?= $activeTab === 'delivery' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                    <i class="fa-solid fa-truck-fast mr-1.5"></i>
                    <?= is_bangla() ? 'কুরিয়ার লজিস্টিক' : 'Courier Logistics' ?>
                </a>
            <?php endif; ?>

            <a href="notifications.php?type=chat" 
               class="px-4 py-2 rounded-xl font-bold transition shrink-0 <?= $activeTab === 'chat' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                <i class="fa-solid fa-comments mr-1.5"></i>
                <?= is_bangla() ? 'মেসেজ ও চ্যাট' : 'Messages & Inquiries' ?>
            </a>

            <a href="notifications.php?type=system" 
               class="px-4 py-2 rounded-xl font-bold transition shrink-0 <?= $activeTab === 'system' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                <i class="fa-solid fa-shield-halved mr-1.5"></i>
                <?= is_bangla() ? 'সিস্টেম সতর্কতা' : 'System Alerts' ?>
            </a>
        </div>

        <!-- Notifications List -->
        <div class="space-y-3" id="notifications-list-wrapper">
            <?php if (!empty($allNotifications)): ?>
                <?php foreach ($allNotifications as $n): 
                    $style = get_notification_style($n['type'] ?? 'system');
                    $isUnread = empty($n['is_read']);
                    $openUrl = BASE_URL . '/ajax_handler.php?action=open_notification&id=' . (int)$n['id'];
                ?>
                    <div id="notif-card-<?= (int)$n['id'] ?>" class="bg-white rounded-3xl border <?= $isUnread ? 'border-emerald-300 shadow-sm bg-gradient-to-r from-emerald-50/20 via-white to-white' : 'border-slate-200 shadow-2xs' ?> p-5 transition hover:shadow-md group flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        
                        <!-- Left Side: Icon & Notification Info (Clickable) -->
                        <a href="<?= $openUrl ?>" class="flex items-start space-x-4 min-w-0 flex-1 cursor-pointer">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 border <?= $style['bg'] ?> text-base shadow-2xs group-hover:scale-105 transition">
                                <i class="fa-solid <?= $style['icon'] ?>"></i>
                            </div>

                            <div class="space-y-1 min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-extrabold text-slate-900 group-hover:text-emerald-700 transition">
                                        <?= sanitize($n['title']) ?>
                                    </h3>
                                    <span class="text-[10px] font-bold uppercase tracking-wider <?= $style['badge'] ?> px-2 py-0.5 rounded-md">
                                        <?= $style['label'] ?>
                                    </span>
                                    <?php if ($isUnread): ?>
                                        <span id="badge-unread-<?= (int)$n['id'] ?>" class="text-[10px] font-bold bg-emerald-500 text-white px-2 py-0.5 rounded-full flex items-center gap-1">
                                            <span class="w-1 h-1 rounded-full bg-white animate-pulse"></span>
                                            <?= is_bangla() ? 'নতুন' : 'New' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <p class="text-xs text-slate-600 leading-relaxed">
                                    <?= sanitize($n['message']) ?>
                                </p>

                                <div class="flex items-center space-x-3 text-[11px] text-slate-400 pt-1">
                                    <span class="flex items-center gap-1 font-medium">
                                        <i class="fa-regular fa-clock text-[10px]"></i>
                                        <?= time_ago($n['created_at']) ?>
                                    </span>
                                    <span>•</span>
                                    <span><?= format_date($n['created_at']) ?></span>
                                </div>
                            </div>
                        </a>

                        <!-- Right Side: Action Buttons -->
                        <div class="flex items-center space-x-2 shrink-0 self-end sm:self-center">
                            <?php if ($isUnread): ?>
                                <button type="button" 
                                        onclick="markSingleRead(<?= (int)$n['id'] ?>)" 
                                        id="btn-mark-<?= (int)$n['id'] ?>"
                                        class="px-3 py-2 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-600 font-semibold text-xs rounded-xl transition cursor-pointer flex items-center gap-1">
                                    <i class="fa-solid fa-check text-[10px]"></i>
                                    <span><?= is_bangla() ? 'পঠিত' : 'Mark Read' ?></span>
                                </button>
                            <?php endif; ?>

                            <a href="<?= $openUrl ?>" 
                               class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                                <span><?= is_bangla() ? 'কাজটি খুলুন' : 'Open Task' ?></span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-2xs space-y-4">
                    <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto text-2xl">
                        <i class="fa-solid fa-bell-slash"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="font-bold text-base text-slate-800"><?= is_bangla() ? 'কোনো বিজ্ঞপ্তি পাওয়া যায়নি' : 'No Notifications Found' ?></h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto"><?= is_bangla() ? 'আপনার নির্বাচিত ক্যাটাগরিতে এই মুহূর্তে কোনো নতুন বিজ্ঞপ্তি নেই।' : 'You are all caught up! There are no notifications in this category.' ?></p>
                    </div>
                    <div>
                        <a href="notifications.php?type=all" class="inline-flex items-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition">
                            <?= is_bangla() ? 'সব বিজ্ঞপ্তি দেখুন' : 'View All Notifications' ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

</div>

<script>
async function markSingleRead(notifId) {
    const baseUrl = window.SMART_NURSERY_CONFIG?.baseUrl || '';
    try {
        const res = await fetch(`${baseUrl}/ajax_handler.php?action=mark_read&id=${notifId}`);
        const data = await res.json();
        if (data.success) {
            const card = document.getElementById(`notif-card-${notifId}`);
            if (card) {
                card.classList.remove('border-emerald-300', 'bg-gradient-to-r', 'from-emerald-50/20');
                card.classList.add('border-slate-200');
            }
            const badge = document.getElementById(`badge-unread-${notifId}`);
            if (badge) badge.remove();
            const btn = document.getElementById(`btn-mark-${notifId}`);
            if (btn) btn.remove();
        }
    } catch (err) {
        console.error(err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const pageMarkAllBtn = document.getElementById('page-btn-mark-all-read');
    if (pageMarkAllBtn) {
        pageMarkAllBtn.addEventListener('click', async () => {
            const baseUrl = window.SMART_NURSERY_CONFIG?.baseUrl || '';
            try {
                const res = await fetch(`${baseUrl}/ajax_handler.php?action=mark_all_read`);
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                console.error(err);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
