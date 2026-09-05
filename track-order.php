<?php
// Live Order Status Tracker & Milestone Timeline (Bilingual)
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

$db = get_db();
$isBn = is_bangla();
$orderNumber = trim($_GET['order_number'] ?? '');
$order = null;
$deliveryInfo = null;

if (!empty($orderNumber)) {
    $stmt = $db->prepare("
        SELECT o.*, vp.nursery_name, vp.nursery_name_bn, vp.address AS vendor_address,
               p.payment_status, p.payment_method
        FROM orders o
        JOIN vendor_profiles vp ON o.vendor_id = vp.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.order_number = :num
    ");
    $stmt->execute(['num' => $orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        // Fetch Delivery assignment info
        $delStmt = $db->prepare("
            SELECT da.*, u.name AS rider_name, u.phone AS rider_phone, dsp.vehicle_type
            FROM delivery_assignments da
            JOIN users u ON da.delivery_staff_id = u.id
            LEFT JOIN delivery_staff_profiles dsp ON u.id = dsp.user_id
            WHERE da.order_id = :oid
        ");
        $delStmt->execute(['oid' => $order['id']]);
        $deliveryInfo = $delStmt->fetch();
    }
}

// Milestone Stages Definition
$stages = [
    'Pending'              => ['title' => __('track.step_pending'), 'icon' => 'fa-receipt', 'desc' => $isBn ? 'অর্ডার তৈরি ও প্রাথমিক যাচাই সম্পন্ন' : 'Order placed & system verified'],
    'Vendor Accepted'      => ['title' => __('track.step_accepted'), 'icon' => 'fa-store', 'desc' => $isBn ? 'নার্সারি চারা স্টক প্রস্তুত করছে' : 'Nursery accepted & preparing stock'],
    'Preparing'            => ['title' => __('track.step_preparing'), 'icon' => 'fa-leaf', 'desc' => $isBn ? 'স্বাস্থ্য পরীক্ষা ও শক-প্রুফ হাইড্রেশন প্যাক' : 'Plant health check & root-shock pack'],
    'Ready for Pickup'     => ['title' => __('track.step_ready_for_pickup'), 'icon' => 'fa-boxes-packing', 'desc' => $isBn ? 'নার্সারি কিউতে প্যাকেজিং সম্পূর্ণ' : 'Sealed & ready in nursery bay'],
    'Assigned to Delivery' => ['title' => __('track.courier_assigned'), 'icon' => 'fa-motorcycle', 'desc' => $isBn ? 'সার্টিফাইড ডেলিভারি রাইডার নিয়োজিত' : 'Climate courier assigned to pickup'],
    'Picked Up'            => ['title' => __('track.step_in_transit'), 'icon' => 'fa-box-open', 'desc' => $isBn ? 'গাছ নিরাপদ ডেলিভারির ট্রানজিটে রয়েছে' : 'Foliage in secured transit'],
    'Out for Delivery'     => ['title' => __('track.step_out_for_delivery'), 'icon' => 'fa-truck-fast', 'desc' => $isBn ? 'আপনার গন্তব্যে পৌঁছানোর পথে' : 'Courier arriving at your address'],
    'Delivered'            => ['title' => __('track.step_delivered'), 'icon' => 'fa-circle-check', 'desc' => $isBn ? 'সফলভাবে হস্তান্তর ও লাইভ গ্যারান্টি কার্যকর' : 'Delivered with 100% Live Guarantee']
];

$stageKeys = array_keys($stages);

$currentStatus = $order['order_status'] ?? '';
$currentIndex = array_search($currentStatus, $stageKeys);
if ($currentIndex === false) {
    $currentIndex = 0;
}

$pageTitle = $isBn ? 'লাইভ অর্ডার ট্র্যাকিং • ' . __('app.name') : 'Live Order Tracker • ' . __('app.name');

$extraHead = '
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/ultra-modern-home.css?v=' . (file_exists(__DIR__ . '/assets/css/ultra-modern-home.css') ? filemtime(__DIR__ . '/assets/css/ultra-modern-home.css') : time()) . '">
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/botanical-showcase.css?v=' . (file_exists(__DIR__ . '/assets/css/botanical-showcase.css') ? filemtime(__DIR__ . '/assets/css/botanical-showcase.css') : time()) . '">
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/spatial-greenhouse.css?v=' . (file_exists(__DIR__ . '/assets/css/spatial-greenhouse.css') ? filemtime(__DIR__ . '/assets/css/spatial-greenhouse.css') : time()) . '">
';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="relative bg-slate-50 text-slate-800 min-h-screen">
    
    <!-- ==============================================================================
         ZONE 01 AESTHETIC: CINEMATIC ORDER TRACKING HERO
         ============================================================================== -->
    <div class="relative bg-[#04130c] text-white py-16 sm:py-20 overflow-hidden border-b border-emerald-950/60">
        <!-- Universal Ambient Glowing Orbs -->
        <div class="ambient-orb ambient-orb-emerald -top-20 -left-20"></div>
        <div class="ambient-orb ambient-orb-mint -bottom-20 -right-20"></div>
        <div class="atmospheric-mesh-bg"></div>
        <div class="architectural-grid-lines"></div>

        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 text-center space-y-5 z-10">
            <div class="inline-flex items-center space-x-2 bg-emerald-500/20 text-emerald-300 px-4 py-1.5 rounded-full text-xs font-bold border border-emerald-400/30 backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <i class="fa-solid fa-truck-fast text-emerald-400"></i>
                <span><?= $isBn ? '📦 রিয়েল-টাইম বোটানিক্যাল ট্র্যাকিং' : '📦 Real-Time Botanical Dispatch Logistics' ?></span>
            </div>

            <h1 class="text-3xl sm:text-5xl md:text-6xl font-black tracking-tight text-white leading-tight">
                <?= $isBn ? 'আপনার অর্ডারের <span class="hero-title-gradient">লাইভ অবস্থান জানুন</span>' : 'Track Your Living Plant <span class="hero-title-gradient">Every Step of the Way</span>' ?>
            </h1>

            <p class="text-sm sm:text-base text-slate-300 max-w-xl mx-auto leading-relaxed">
                <?= $isBn 
                    ? 'গ্রিনহাউজ প্যাকেজিং থেকে সরাসরি আপনার ড্রয়িংরুম পর্যন্ত প্রতিটি ধাপের লাইভ আপডেট ট্র্যাক করুন।'
                    : 'Monitor nursery root-shock preparation, climate-controlled courier handoff, and doorstep delivery.' ?>
            </p>
            
            <!-- Live Search Floating Input Bar -->
            <form action="track-order.php" method="GET" class="max-w-md mx-auto flex items-center bg-black/60 backdrop-blur-xl rounded-2xl p-2 border border-emerald-500/40 shadow-2xl shadow-emerald-950/50">
                <i class="fa-solid fa-receipt text-emerald-400 pl-3"></i>
                <input type="text" name="order_number" value="<?= sanitize($orderNumber) ?>" placeholder="<?= $isBn ? 'অর্ডার নম্বর লিখুন (যেমন: ORD-2026-XXXX)' : 'Enter Order Number (e.g. ORD-2026-XXXX)' ?>" required
                       class="flex-1 bg-transparent px-3 py-2 text-xs text-white placeholder-slate-400 focus:outline-none font-mono uppercase">
                <button type="submit" class="px-5 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs rounded-xl shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span><?= $isBn ? 'ট্র্যাক' : 'Track' ?></span>
                </button>
            </form>
        </div>
    </div>

    <!-- ==============================================================================
         ORDER STATUS & TIMELINE CORRIDOR
         ============================================================================== -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 relative z-20 pb-16 space-y-8">
        
        <?php if ($order): ?>
            <!-- Order Summary Card -->
            <div class="bg-white/95 backdrop-blur-xl p-6 sm:p-8 rounded-3xl border border-slate-200/90 shadow-xl shadow-slate-900/5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
                <div class="space-y-1">
                    <div class="flex items-center space-x-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400"><?= __('order.order_number') ?></span>
                        <span class="font-mono font-black text-emerald-800 text-base">#<?= sanitize($order['order_number']) ?></span>
                    </div>
                    <h2 class="text-xl font-black text-slate-900"><?= sanitize(vendor_display_name($order['nursery_name'])) ?></h2>
                    <p class="text-xs text-slate-500 flex items-center space-x-1.5">
                        <i class="fa-regular fa-calendar text-emerald-600"></i>
                        <span><?= format_date($order['created_at']) ?></span>
                        <span>•</span>
                        <i class="fa-solid fa-location-dot text-emerald-600"></i>
                        <span><?= sanitize($order['vendor_address']) ?></span>
                    </p>
                </div>

                <div class="text-left sm:text-right space-y-2">
                    <div>
                        <?= render_order_status_badge($order['order_status']) ?>
                    </div>
                    <span class="text-xs text-slate-500 block font-medium">
                        <?= __('cart.total') ?>: <strong class="text-xl font-black text-emerald-700"><?= format_currency($order['grand_total']) ?></strong>
                    </span>
                </div>
            </div>

            <!-- Milestone Progress Stem Timeline -->
            <?php if ($order['order_status'] === 'Cancelled' || $order['order_status'] === 'Vendor Rejected'): ?>
                <div class="bg-rose-50 border border-rose-200 p-8 rounded-3xl text-center space-y-3 text-rose-800 shadow-sm">
                    <i class="fa-solid fa-circle-xmark text-5xl text-rose-500"></i>
                    <h3 class="text-lg font-black"><?= render_order_status_badge($order['order_status']) ?></h3>
                    <p class="text-xs text-rose-600 max-w-md mx-auto"><?= sanitize($order['cancelled_reason'] ?? 'Order fulfillment could not proceed.') ?></p>
                </div>
            <?php else: ?>
                <div class="bg-white/95 backdrop-blur-xl p-6 sm:p-10 rounded-3xl border border-slate-200/90 shadow-xl shadow-slate-900/5 space-y-8">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                            <i class="fa-solid fa-route text-emerald-600"></i>
                            <span><?= $isBn ? 'লাইভ ডিসপ্যাচ ও ডেলিভারি টাইমলাইন' : 'Live Dispatch & Logistics Milestone Timeline' ?></span>
                        </h3>
                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                            <?= $isBn ? 'ধাপ ' . format_num_i18n($currentIndex + 1) . ' / ৮' : 'Stage ' . ($currentIndex + 1) . ' of 8' ?>
                        </span>
                    </div>

                    <div class="relative pl-6 sm:pl-8 border-l-2 border-emerald-200/80 space-y-8 ml-3">
                        <?php foreach ($stages as $statusKey => $st): ?>
                            <?php 
                            $stageIndex = array_search($statusKey, $stageKeys);
                            $isPassed = $stageIndex <= $currentIndex;
                            $isCurrent = $stageIndex === $currentIndex;
                            ?>
                            <div class="relative flex items-start space-x-4">
                                
                                <!-- Glowing Step Node -->
                                <div class="absolute -left-[35px] sm:-left-[43px] w-10 h-10 rounded-2xl flex items-center justify-center text-xs font-bold transition-all shadow-md <?= $isCurrent ? 'bg-emerald-600 text-white ring-4 ring-emerald-300 animate-pulse' : ($isPassed ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400 border border-slate-200') ?>">
                                    <i class="fa-solid <?= $st['icon'] ?> text-sm"></i>
                                </div>

                                <div class="space-y-1 pt-1 pl-2">
                                    <h4 class="text-xs sm:text-sm font-black <?= $isPassed ? 'text-slate-900' : 'text-slate-400' ?>">
                                        <?= $st['title'] ?>
                                        <?php if ($isCurrent): ?>
                                            <span class="ml-2 text-[10px] px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 uppercase tracking-widest font-black"><?= __('common.active') ?></span>
                                        <?php endif; ?>
                                    </h4>
                                    <p class="text-[11px] <?= $isPassed ? 'text-slate-600' : 'text-slate-400' ?>"><?= $st['desc'] ?></p>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Assigned Courier Telemetry Card (if assigned) -->
            <?php if ($deliveryInfo): ?>
                <div class="bg-gradient-to-r from-emerald-950 to-teal-900 text-white p-6 sm:p-8 rounded-3xl border border-emerald-500/30 shadow-xl flex flex-col sm:flex-row items-center justify-between gap-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-500 text-slate-950 flex items-center justify-center text-2xl font-black shadow-lg">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black text-emerald-400 uppercase tracking-widest block"><?= __('track.courier_assigned') ?></span>
                            <h4 class="text-lg font-black text-white"><?= sanitize($deliveryInfo['rider_name']) ?></h4>
                            <span class="text-xs text-slate-300 flex items-center space-x-1.5 mt-0.5">
                                <i class="fa-solid fa-phone text-emerald-400"></i>
                                <a href="tel:<?= sanitize($deliveryInfo['rider_phone']) ?>" class="underline hover:text-white font-mono"><?= sanitize($deliveryInfo['rider_phone']) ?></a>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($deliveryInfo['delivery_notes'])): ?>
                        <div class="text-xs bg-black/40 p-4 rounded-2xl border border-white/10 text-slate-200 max-w-sm">
                            <span class="font-bold text-emerald-300 block text-[11px] mb-1"><?= __('delivery.proof_note') ?>:</span>
                            <?= sanitize($deliveryInfo['delivery_notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php elseif (!empty($orderNumber)): ?>
            <!-- Not Found State -->
            <div class="bg-white p-12 rounded-3xl text-center border border-slate-200 space-y-4 max-w-md mx-auto shadow-sm">
                <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center text-2xl mx-auto">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <h3 class="text-lg font-black text-slate-900"><?= $isBn ? 'অর্ডার পাওয়া যায়নি' : 'Order Not Found' ?></h3>
                <p class="text-xs text-slate-500 leading-relaxed"><?= $isBn ? 'প্রদত্ত অর্ডার নম্বরের সাথে কোনো রেকর্ড মেলেনি। অনুগ্রহ করে নম্বরটি সঠিকভাবে চেক করুন।' : 'We could not locate any order with this ID. Please double check your order number.' ?></p>
            </div>
        <?php else: ?>
            <!-- Info Cards for Initial State -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-md space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-leaf"></i>
                    </div>
                    <h4 class="font-black text-slate-900 text-sm"><?= $isBn ? '১. রুট-শক প্রতিরোধক প্যাকেজিং' : '1. Root-Shock Transit Guard' ?></h4>
                    <p class="text-xs text-slate-500 leading-relaxed"><?= $isBn ? 'বিশেষ হাইড্রেশন প্যাক ও এয়ার পকেটের মাধ্যমে চারা সুরক্ষিত রাখা হয়।' : 'Every botanical is encased in moisture hydration packs to prevent transit stress.' ?></p>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-md space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-motorcycle"></i>
                    </div>
                    <h4 class="font-black text-slate-900 text-sm"><?= $isBn ? '২. ডেডিকেটেড প্ল্যান্ট কুরিয়ার' : '2. Dedicated Green Couriers' ?></h4>
                    <p class="text-xs text-slate-500 leading-relaxed"><?= $isBn ? 'প্রশিক্ষিত রাইডাররা গাছ সোজা ও সুরক্ষিত রেখে সরাসরি ডেলিভারি দেয়।' : 'Specialized riders ensure plants are transported upright and climate-stable.' ?></p>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-md space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-shield-heart"></i>
                    </div>
                    <h4 class="font-black text-slate-900 text-sm"><?= $isBn ? '৩. ১০০% সুস্থ চারার নিশ্চয়তা' : '3. 100% Live Arrival Guarantee' ?></h4>
                    <p class="text-xs text-slate-500 leading-relaxed"><?= $isBn ? 'ডেলিভারির পর কোনো ক্ষতি হলে তাৎক্ষণিক রিপ্লেসমেন্ট সুবিধা।' : '7-day botanical guarantee for hassle-free replacements if damaged.' ?></p>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
