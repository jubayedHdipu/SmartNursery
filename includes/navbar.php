<?php
// Main Public & Customer Navigation (Bilingual)

require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth.php';

$currentUser = current_user();
$cartItems = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_column($cartItems, 'quantity'));
$notifications = $currentUser ? get_unread_notifications($currentUser['id'], 5) : [];
$unreadNotifCount = $currentUser ? count_unread_notifications($currentUser['id']) : 0;
$activeLang = current_lang();

// Dynamic Platform Brand Settings
$siteLogoType      = get_setting('site_logo_type', 'icon');
$siteLogoIcon      = get_setting('site_logo_icon', 'fa-leaf');
$siteLogoImage     = get_setting('site_logo_image', '');
$brandTitle        = is_bangla() ? get_setting('site_brand_title_bn', __('app.brand_title')) : get_setting('site_brand_title', __('app.brand_title'));
$brandSubtitle     = is_bangla() ? get_setting('site_brand_subtitle_bn', __('app.brand_subtitle')) : get_setting('site_brand_subtitle', __('app.brand_subtitle'));
$brandTag          = is_bangla() ? get_setting('site_tagline_bn', __('app.brand_tag')) : get_setting('site_tagline', __('app.brand_tag'));
$announcementText  = is_bangla() ? get_setting('top_announcement_bn', __('app.top_announcement')) : get_setting('top_announcement_en', __('app.top_announcement'));
?>

<!-- ==============================================================================
     TOP ANNOUNCEMENT BAR & LANGUAGE SWITCHER
     ============================================================================== -->
<div class="bg-[#03150d] text-emerald-300 text-xs py-2 px-4 font-medium border-b border-emerald-900/60 relative z-[100] flex items-center shadow-xs">
    <!-- Centered announcement text -->
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none px-4">
        <div class="flex items-center space-x-2 truncate max-w-full">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-ping shrink-0"></span>
            <span class="text-center truncate text-[11px] font-semibold text-emerald-200"><?= sanitize($announcementText) ?></span>
        </div>
    </div>

    <!-- Spacer left (keeps switcher on right) -->
    <div class="flex-1"></div>

    <!-- Top Bar Language Switcher -->
    <div class="flex items-center space-x-2 relative z-10">
        <span class="text-emerald-400/80 text-[11px] hidden sm:inline"><i class="fa-solid fa-globe mr-1"></i><?= __('lang.switch_label') ?>:</span>
        <div class="inline-flex rounded-xl bg-black/40 p-0.5 border border-emerald-500/30 text-[11px] backdrop-blur-md">
            <a href="<?= lang_switch_url('en') ?>" class="px-2.5 py-0.5 rounded-lg transition <?= $activeLang === 'en' ? 'bg-emerald-500 text-slate-950 font-black shadow-xs' : 'text-emerald-300 hover:text-white' ?>">
                EN
            </a>
            <a href="<?= lang_switch_url('bn') ?>" class="px-2.5 py-0.5 rounded-lg transition <?= $activeLang === 'bn' ? 'bg-emerald-500 text-slate-950 font-black shadow-xs' : 'text-emerald-300 hover:text-white' ?>">
                বাংলা
            </a>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MAIN GLASSMORPHIC NAVIGATION BAR
     ============================================================================== -->
<nav class="sticky top-0 z-[90] glass-nav transition-all duration-300">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20 gap-3">
            
            <!-- Left: Brand Logo & Primary Nav Links -->
            <div class="flex items-center space-x-4 xl:space-x-6 shrink-0">
                <a href="<?= BASE_URL ?>/index.php" class="flex items-center space-x-2.5 shrink-0 group">
                    <?php if ($siteLogoType === 'image' && !empty($siteLogoImage)): ?>
                        <img src="<?= sanitize(get_image_url($siteLogoImage)) ?>" alt="Logo" class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl object-cover shadow-lg shadow-emerald-950/20 group-hover:scale-105 transition transform border border-emerald-500/20">
                    <?php else: ?>
                        <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-gradient-to-br from-emerald-600 via-teal-700 to-emerald-900 flex items-center justify-center text-white shadow-lg shadow-emerald-950/20 group-hover:scale-105 transition transform border border-emerald-400/30 shrink-0">
                            <i class="fa-solid <?= sanitize($siteLogoIcon) ?> text-lg sm:text-xl text-emerald-200"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="text-lg sm:text-xl font-black tracking-tight text-slate-900 block leading-tight">
                            <?= sanitize($brandTitle) ?><span class="text-emerald-600"><?= sanitize($brandSubtitle) ?></span>
                        </span>
                        <span class="text-[9px] sm:text-[10px] font-bold text-emerald-800 uppercase tracking-widest block"><?= sanitize($brandTag) ?></span>
                    </div>
                </a>

                <!-- Primary Nav Links for Extra Large Screens (>= 1280px) -->
                <div class="hidden xl:flex items-center space-x-1 shrink-0">
                    <a href="<?= BASE_URL ?>/index.php" class="nav-link-pill <?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-house text-xs text-emerald-600"></i>
                        <span><?= __('nav.home') ?></span>
                    </a>
                    
                    <a href="<?= BASE_URL ?>/nurseries.php" class="nav-link-pill <?= (basename($_SERVER['PHP_SELF']) === 'nurseries.php') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-store text-xs text-emerald-600"></i>
                        <span><?= __('nav.nurseries', 'Nurseries') ?></span>
                    </a>

                    <a href="<?= BASE_URL ?>/shop.php" class="nav-link-pill <?= (basename($_SERVER['PHP_SELF']) === 'shop.php') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-layer-group text-xs text-emerald-600"></i>
                        <span><?= __('nav.browse_plants') ?></span>
                    </a>

                    <!-- Smart Plant Matcher with Luminous Highlighter Beam -->
                    <a href="<?= BASE_URL ?>/plant-matcher.php" class="nav-matcher-highlighter group" title="<?= __('nav.ai_matcher') ?>">
                        <span class="highlighter-ray"></span>
                        <i class="fa-solid fa-wand-magic-sparkles matcher-sparkle-icon text-xs"></i>
                        <span><?= __('nav.ai_matcher') ?></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping ml-0.5"></span>
                    </a>

                    <a href="<?= BASE_URL ?>/track-order.php" class="nav-link-pill <?= (basename($_SERVER['PHP_SELF']) === 'track-order.php') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-truck-fast text-xs text-emerald-600"></i>
                        <span><?= __('nav.track_order') ?></span>
                    </a>
                </div>

                <!-- Primary Nav Links for Standard Large Screens (1024px - 1279px) -->
                <div class="hidden lg:flex xl:hidden items-center space-x-1 shrink-0">
                    <a href="<?= BASE_URL ?>/shop.php" class="nav-link-pill <?= (basename($_SERVER['PHP_SELF']) === 'shop.php') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-layer-group text-xs text-emerald-600"></i>
                        <span><?= __('nav.browse_plants') ?></span>
                    </a>

                    <a href="<?= BASE_URL ?>/plant-matcher.php" class="nav-matcher-highlighter group" title="<?= __('nav.ai_matcher') ?>">
                        <span class="highlighter-ray"></span>
                        <i class="fa-solid fa-wand-magic-sparkles matcher-sparkle-icon text-xs"></i>
                        <span><?= __('nav.ai_matcher') ?></span>
                    </a>

                    <a href="<?= BASE_URL ?>/nurseries.php" class="nav-link-pill <?= (basename($_SERVER['PHP_SELF']) === 'nurseries.php') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-store text-xs text-emerald-600"></i>
                        <span><?= __('nav.nurseries', 'Nurseries') ?></span>
                    </a>
                </div>
            </div>

            <!-- ==========================================================
                 CENTER: DEDICATED FLEXIBLE LIVE SEARCH BAR (Ample Space)
                 ========================================================== -->
            <div class="hidden md:flex flex-1 min-w-[140px] max-w-xs lg:max-w-sm mx-1.5 lg:mx-3 relative z-50" id="desktop-search-container">
                <form action="<?= BASE_URL ?>/shop.php" method="GET" class="w-full relative flex items-center">
                    <label for="nav-desktop-search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-emerald-600 pointer-events-none text-xs z-20">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </label>
                    <input type="text" name="q" id="nav-desktop-search" autocomplete="off" placeholder="<?= __('nav.search_placeholder') ?>" 
                           class="w-full bg-slate-100/90 hover:bg-slate-100 focus:bg-white border border-slate-200/90 focus:border-emerald-500 rounded-full pl-10 pr-10 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-inner transition relative z-10">
                    <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center text-xs shadow-md transition z-20 cursor-pointer" title="<?= __('common.search') ?>">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>

                <!-- Live Search Floating Results Dropdown -->
                <div id="desktop-search-results" class="hidden absolute left-0 sm:-left-6 w-[360px] sm:w-[420px] top-full mt-2.5 bg-white/95 backdrop-blur-2xl rounded-3xl shadow-2xl border border-slate-200/90 py-2 z-[100] overflow-hidden max-h-96 overflow-y-auto"></div>
            </div>

            <!-- Right: Action Controls (User Profile / Auth, Notifs, Cart, Mobile Menu) -->
            <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">

                <!-- Mobile Search Toggle Icon Button -->
                <button id="mobile-search-toggle" type="button" class="md:hidden nav-action-btn" title="<?= __('common.search') ?>">
                    <i class="fa-solid fa-magnifying-glass text-base"></i>
                </button>

                <!-- Language Pill for Compact Screens -->
                <div class="flex md:hidden items-center bg-slate-100 rounded-xl p-0.5 border border-slate-200 text-xs">
                    <a href="<?= lang_switch_url('en') ?>" class="px-2 py-1 rounded-lg <?= $activeLang === 'en' ? 'bg-emerald-600 text-white font-bold' : 'text-slate-600' ?>">EN</a>
                    <a href="<?= lang_switch_url('bn') ?>" class="px-2 py-1 rounded-lg <?= $activeLang === 'bn' ? 'bg-emerald-600 text-white font-bold' : 'text-slate-600' ?>">বাং</a>
                </div>

                <!-- Cart Button (Before Profile Name) -->
                <a href="<?= BASE_URL ?>/cart.php" class="nav-action-btn group" title="<?= __('nav.cart') ?>">
                    <i class="fa-solid fa-bag-shopping text-base group-hover:scale-110 transition"></i>
                    <span id="cart-count-badge" class="<?= $cartCount > 0 ? '' : 'hidden' ?> absolute -top-1 -right-1 bg-emerald-600 text-white text-[10px] font-black w-4.5 h-4.5 rounded-full flex items-center justify-center border-2 border-white shadow-md">
                        <?= format_num_i18n($cartCount) ?>
                    </span>
                </a>

                <?php if ($currentUser): ?>
                    <!-- Notifications Dropdown (Before Profile Name) -->
                    <div class="relative mr-1">
                        <button id="notif-menu-button" type="button" class="nav-action-btn group cursor-pointer" title="<?= __('nav.notifications') ?>">
                            <i class="fa-regular fa-bell text-base group-hover:scale-110 transition"></i>
                            <span id="notif-badge" class="<?= $unreadNotifCount > 0 ? '' : 'hidden' ?> absolute -top-0.5 -right-0.5 bg-rose-500 text-white text-[10px] font-black w-4.5 h-4.5 rounded-full flex items-center justify-center border-2 border-white shadow-md animate-pulse">
                                <?= $unreadNotifCount > 9 ? '9+' : format_num_i18n($unreadNotifCount) ?>
                            </span>
                        </button>

                        <!-- Notification Menu Box -->
                        <div id="notif-menu-dropdown" class="hidden absolute right-0 mt-3 w-84 sm:w-96 bg-white rounded-3xl shadow-2xl border border-slate-200/90 py-0 z-50 overflow-hidden">
                            <!-- Header with Role Indicator & Mark Read -->
                            <div class="px-5 py-3.5 bg-slate-50/90 border-b border-slate-100 flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                        <?= is_bangla() ? 'বিজ্ঞপ্তিসমূহ' : 'Notifications' ?>
                                    </span>
                                    <span id="notif-unread-count-pill" class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">
                                        <?= format_num_i18n($unreadNotifCount) ?>
                                    </span>
                                </div>
                                <?php if ($unreadNotifCount > 0): ?>
                                    <button id="btn-mark-all-notifs" type="button" class="text-[11px] font-bold text-emerald-600 hover:text-emerald-700 transition cursor-pointer">
                                        <?= is_bangla() ? 'সব পঠিত হিসেবে চিহ্নিত করুন' : 'Mark all read' ?>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Scrollable Notifications List -->
                            <div id="notif-list-container" class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach ($notifications as $n): 
                                        $style = get_notification_style($n['type'] ?? 'system');
                                        $isUnread = empty($n['is_read']);
                                        $openUrl = BASE_URL . '/ajax_handler.php?action=open_notification&id=' . (int)$n['id'];
                                    ?>
                                        <a href="<?= $openUrl ?>" class="flex items-start space-x-3 p-3.5 <?= $isUnread ? 'bg-emerald-50/35 hover:bg-emerald-50/70' : 'hover:bg-slate-50' ?> transition group">
                                            <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 border <?= $style['bg'] ?> text-xs shadow-2xs">
                                                <i class="fa-solid <?= $style['icon'] ?>"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between gap-1">
                                                    <p class="text-xs font-bold text-slate-800 group-hover:text-emerald-700 truncate"><?= sanitize($n['title']) ?></p>
                                                    <?php if ($isUnread): ?>
                                                        <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-[11px] text-slate-600 line-clamp-2 mt-0.5 leading-snug"><?= sanitize($n['message']) ?></p>
                                                <div class="flex items-center justify-between mt-1 text-[10px] text-slate-400">
                                                    <span><?= time_ago($n['created_at']) ?></span>
                                                    <span class="font-bold text-[9px] uppercase tracking-wider <?= $style['badge'] ?> px-1.5 py-0.2 rounded-md">
                                                        <?= $style['label'] ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-8 text-center text-slate-400 text-xs">
                                        <i class="fa-solid fa-bell-slash text-2xl text-slate-300 mb-2 block"></i>
                                        <?= __('nav.no_unread_notifs') ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Footer Notification Links -->
                            <?php
                            $roleWorkspaceUrl = match($currentUser['role_name'] ?? '') {
                                'owner'    => BASE_URL . '/owner/index.php',
                                'admin'    => BASE_URL . '/admin/index.php',
                                'vendor'   => BASE_URL . '/vendor/orders.php',
                                'delivery' => BASE_URL . '/delivery/index.php',
                                default    => BASE_URL . '/customer/index.php'
                            };
                            ?>
                            <div class="p-3 bg-slate-50 border-t border-slate-100 flex flex-col space-y-2 text-center">
                                <a href="<?= BASE_URL ?>/notifications.php" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center space-x-1.5">
                                    <span><?= is_bangla() ? 'সব বিজ্ঞপ্তি দেখুন (সব পৃষ্ঠা)' : 'Show All Notifications' ?></span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                                <a href="<?= $roleWorkspaceUrl ?>" class="text-[11px] font-semibold text-slate-500 hover:text-emerald-700 transition flex items-center justify-center space-x-1">
                                    <i class="fa-solid fa-gauge-high text-[10px]"></i>
                                    <span><?= is_bangla() ? 'কাজের ড্যাশবোর্ডে যান' : 'Go to Workspace Portal' ?></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Dropdown (Positioned After Cart & Notifications) -->
                    <div class="relative">
                        <button id="user-menu-button" type="button" class="flex items-center gap-2 pl-2 pr-3.5 py-1.5 rounded-2xl bg-slate-100/90 hover:bg-slate-200/80 border border-slate-200/90 transition shadow-2xs cursor-pointer select-none group shrink-0" title="<?= sanitize($currentUser['name']) ?>">
                            <?php if (!empty($currentUser['avatar'])): ?>
                                <img src="<?= sanitize(get_avatar_url($currentUser['avatar'])) ?>" alt="<?= sanitize($currentUser['name']) ?>" 
                                     class="w-9 h-9 rounded-xl object-cover shadow-2xs border border-emerald-400/50 shrink-0"
                                     style="width: 36px; height: 36px; min-width: 36px; min-height: 36px; max-width: 36px; max-height: 36px; object-fit: cover;">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white font-black text-xs flex items-center justify-center shadow-2xs shrink-0"
                                     style="width: 36px; height: 36px; min-width: 36px; min-height: 36px;">
                                    <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="hidden md:flex flex-col text-left justify-center min-w-0">
                                <span class="text-xs font-bold text-slate-800 tracking-tight leading-snug truncate max-w-[120px] block"><?= sanitize($currentUser['name']) ?></span>
                                <span class="text-[9px] font-extrabold text-emerald-600 uppercase tracking-wider leading-tight block mt-0.5"><?= __('role.' . $currentUser['role_name'], sanitize($currentUser['role_display'])) ?></span>
                            </div>
                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 hidden md:inline shrink-0 ml-1 transition-transform duration-200 group-hover:text-emerald-600"></i>
                        </button>

                        <!-- User Dropdown Menu -->
                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-3xl shadow-2xl border border-slate-100 py-2 z-50">
                            <div class="px-4 py-2.5 border-b border-slate-100">
                                <p class="text-xs font-bold text-slate-800"><?= sanitize($currentUser['name']) ?></p>
                                <p class="text-[11px] text-slate-400 truncate"><?= sanitize($currentUser['email']) ?></p>
                            </div>

                            <!-- Role-Specific Dashboard Links -->
                            <?php if ($currentUser['role_name'] === 'owner'): ?>
                                <a href="<?= BASE_URL ?>/owner/index.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-chart-line text-emerald-600 w-4"></i>
                                    <span><?= __('nav.owner_dashboard') ?></span>
                                </a>
                                <a href="<?= BASE_URL ?>/owner/settings.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-sliders text-emerald-600 w-4"></i>
                                    <span><?= __('nav.system_settings') ?></span>
                                </a>
                            <?php elseif ($currentUser['role_name'] === 'admin'): ?>
                                <a href="<?= BASE_URL ?>/admin/index.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-shield-halved text-emerald-600 w-4"></i>
                                    <span><?= __('nav.admin_panel') ?></span>
                                </a>
                                <a href="<?= BASE_URL ?>/admin/vendors.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-store text-emerald-600 w-4"></i>
                                    <span><?= __('nav.vendor_approvals') ?></span>
                                </a>
                                <a href="<?= BASE_URL ?>/admin/profile.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-user-gear text-emerald-600 w-4"></i>
                                    <span><?= is_bangla() ? 'অ্যাডমিন প্রোফাইল' : 'Admin Profile' ?></span>
                                </a>
                            <?php elseif ($currentUser['role_name'] === 'vendor'): ?>
                                <a href="<?= BASE_URL ?>/vendor/index.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-store text-emerald-600 w-4"></i>
                                    <span><?= __('nav.nursery_portal') ?></span>
                                </a>
                                <a href="<?= BASE_URL ?>/vendor/product-add.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-plus-circle text-emerald-600 w-4"></i>
                                    <span><?= __('nav.add_plant_ai') ?></span>
                                </a>
                                <a href="<?= BASE_URL ?>/vendor/orders.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-boxes-packing text-emerald-600 w-4"></i>
                                    <span><?= __('nav.incoming_orders') ?></span>
                                </a>
                                <a href="<?= BASE_URL ?>/vendor/profile.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-shop text-emerald-600 w-4"></i>
                                    <span><?= __('vendor.store_profile') ?></span>
                                </a>
                            <?php elseif ($currentUser['role_name'] === 'delivery'): ?>
                                <a href="<?= BASE_URL ?>/delivery/index.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-truck-fast text-emerald-600 w-4"></i>
                                    <span><?= __('nav.rider_deliveries') ?></span>
                                </a>
                                <a href="<?= BASE_URL ?>/delivery/profile.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-id-badge text-emerald-600 w-4"></i>
                                    <span><?= is_bangla() ? 'রাইডার প্রোফাইল' : 'Rider Profile' ?></span>
                                </a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/customer/index.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-bag-shopping text-emerald-600 w-4"></i>
                                    <span><?= __('nav.my_orders') ?></span>
                                </a>
                                <a href="<?= BASE_URL ?>/customer/chat.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-comments text-emerald-600 w-4"></i>
                                    <span><?= __('nav.vendor_chat') ?></span>
                                </a>
                                <a href="<?= BASE_URL ?>/customer/profile.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fa-solid fa-user-pen text-emerald-600 w-4"></i>
                                    <span><?= is_bangla() ? 'প্রোফাইল সেটিংস' : 'Profile Settings' ?></span>
                                </a>
                            <?php endif; ?>

                            <div class="border-t border-slate-100 my-1"></div>

                            <a href="<?= BASE_URL ?>/logout.php" class="flex items-center space-x-2.5 px-4 py-2.5 text-xs text-rose-600 hover:bg-rose-50 font-bold">
                                <i class="fa-solid fa-arrow-right-from-bracket w-4"></i>
                                <span><?= __('nav.sign_out') ?></span>
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Guest Login / Register -->
                    <div class="flex items-center space-x-2">
                        <a href="<?= BASE_URL ?>/login.php" class="px-4 py-2.5 rounded-2xl text-xs font-bold text-slate-700 hover:text-emerald-700 hover:bg-slate-100 transition">
                            <?= __('nav.sign_in') ?>
                        </a>
                        <a href="<?= BASE_URL ?>/register.php" class="px-4 py-2.5 rounded-2xl text-xs font-black text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-900/20 transition">
                            <?= __('nav.register') ?>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Mobile Menu Hamburger Button -->
                <button id="mobile-menu-button" type="button" class="lg:hidden nav-action-btn" title="Toggle Navigation">
                    <i class="fa-solid fa-bars text-base"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Expandable Search Bar Drawer -->
    <div id="mobile-search-drawer" class="hidden md:hidden border-t border-slate-200 bg-white/95 backdrop-blur-md px-4 py-3 shadow-md">
        <form action="<?= BASE_URL ?>/shop.php" method="GET" class="w-full relative flex items-center">
            <label for="nav-mobile-search-input" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-emerald-600 pointer-events-none text-xs z-20">
                <i class="fa-solid fa-magnifying-glass"></i>
            </label>
            <input type="text" name="q" id="nav-mobile-search-input" autocomplete="off" placeholder="<?= __('nav.search_placeholder') ?>" 
                   class="w-full bg-slate-100 border border-slate-200 rounded-2xl pl-10 pr-20 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 relative z-10">
            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md transition z-20 cursor-pointer">
                <?= __('common.search') ?>
            </button>
        </form>
        <div id="mobile-search-results" class="hidden mt-2 bg-white rounded-2xl shadow-lg border border-slate-100 divide-y divide-slate-100 max-h-60 overflow-y-auto"></div>
    </div>

    <!-- Mobile Drawer Navigation -->
    <div id="mobile-menu-drawer" class="hidden lg:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-6 space-y-2 shadow-xl">
        <form action="<?= BASE_URL ?>/shop.php" method="GET" class="w-full relative mb-3 flex items-center">
            <input type="text" name="q" placeholder="<?= __('nav.search_placeholder') ?>" 
                   class="w-full bg-slate-100 border border-slate-200 rounded-2xl pl-9 pr-20 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <button type="submit" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600 p-1 cursor-pointer">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </button>
            <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-xs">
                <?= __('common.search') ?>
            </button>
        </form>
        <a href="<?= BASE_URL ?>/index.php" class="block px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-800 hover:bg-emerald-50"><i class="fa-solid fa-house text-emerald-600 mr-2 w-4"></i><?= __('nav.home') ?></a>
        <a href="<?= BASE_URL ?>/nurseries.php" class="block px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-800 hover:bg-emerald-50"><i class="fa-solid fa-store text-emerald-600 mr-2 w-4"></i><?= __('nav.nurseries', 'Nurseries') ?></a>
        <a href="<?= BASE_URL ?>/shop.php" class="block px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-800 hover:bg-emerald-50"><i class="fa-solid fa-layer-group text-emerald-600 mr-2 w-4"></i><?= __('nav.browse_plants') ?></a>
        
        <!-- Mobile Smart Matcher Button -->
        <div class="pt-1 pb-1">
            <a href="<?= BASE_URL ?>/plant-matcher.php" class="nav-matcher-highlighter w-full justify-center text-center">
                <span class="highlighter-ray"></span>
                <i class="fa-solid fa-wand-magic-sparkles matcher-sparkle-icon text-xs"></i>
                <span><?= __('nav.ai_matcher') ?></span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping ml-1"></span>
            </a>
        </div>

        <a href="<?= BASE_URL ?>/track-order.php" class="block px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-800 hover:bg-emerald-50"><i class="fa-solid fa-truck-fast text-emerald-600 mr-2 w-4"></i><?= __('nav.track_order') ?></a>
        <a href="<?= BASE_URL ?>/cart.php" class="block px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-800 hover:bg-emerald-50"><i class="fa-solid fa-bag-shopping text-emerald-600 mr-2 w-4"></i><?= __('nav.cart') ?> (<?= format_num_i18n($cartCount) ?>)</a>
    </div>
</nav>
