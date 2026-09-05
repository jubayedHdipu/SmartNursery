<?php
// Verified Nursery Directory (Bilingual)
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

$db = get_db();
$isBn = is_bangla();

// 1. Capture Filter and Search Inputs
$searchQuery  = trim($_GET['q'] ?? '');
$selectedCity = trim($_GET['city'] ?? '');
$sortBy       = trim($_GET['sort'] ?? 'rating_high');

// 2. Build Dynamic Prepared SQL Query
$whereClauses = ["vp.verification_status = 'verified'", "u.status = 'active'"];
$params = [];

if (!empty($searchQuery)) {
    $whereClauses[] = "(vp.nursery_name LIKE :q1 OR vp.nursery_name_bn LIKE :q1_bn OR vp.description LIKE :q2 OR vp.address LIKE :q3 OR u.name LIKE :q4)";
    $params['q1']    = '%' . $searchQuery . '%';
    $params['q1_bn'] = '%' . $searchQuery . '%';
    $params['q2']    = '%' . $searchQuery . '%';
    $params['q3']    = '%' . $searchQuery . '%';
    $params['q4']    = '%' . $searchQuery . '%';
}

if (!empty($selectedCity)) {
    $whereClauses[] = "vp.city = :city";
    $params['city'] = $selectedCity;
}

$whereSql = implode(' AND ', $whereClauses);

// 3. Determine Ordering Logic
switch ($sortBy) {
    case 'plants_high':
        $orderBy = "in_stock_plants DESC, vp.rating_avg DESC";
        break;
    case 'name_asc':
        $orderBy = "vp.nursery_name ASC";
        break;
    case 'newest':
        $orderBy = "vp.id DESC";
        break;
    case 'rating_high':
    default:
        $orderBy = "calculated_rating DESC, vp.rating_count DESC, vp.id ASC";
        break;
}

// 4. Fetch Nurseries with Subquery Metrics
$sql = "
    SELECT vp.*, u.name AS owner_name, u.email AS owner_email, u.phone AS user_phone,
           (SELECT COUNT(*) FROM products WHERE vendor_id = vp.id AND status = 'approved' AND stock_quantity > 0) AS in_stock_plants,
           (SELECT COUNT(*) FROM products WHERE vendor_id = vp.id AND status = 'approved') AS total_plants,
           (SELECT COUNT(r.id) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.vendor_id = vp.id) AS total_reviews,
           (SELECT COALESCE(AVG(r.rating), vp.rating_avg) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.vendor_id = vp.id) AS calculated_rating
    FROM vendor_profiles vp
    JOIN users u ON vp.user_id = u.id
    WHERE {$whereSql}
    ORDER BY {$orderBy}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$nurseries = $stmt->fetchAll();

// 5. Fetch Unique Cities for City Filter
$citiesStmt = $db->query("SELECT DISTINCT city FROM vendor_profiles WHERE verification_status = 'verified' AND city IS NOT NULL AND city != '' ORDER BY city ASC");
$availableCities = $citiesStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = $isBn ? 'যাচাইকৃত নার্সারিসমূহ • ' . __('app.name') : 'Verified Botanical Nurseries • ' . __('app.name');

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
         ZONE 01 AESTHETIC: CINEMATIC HERO BANNER
         ============================================================================== -->
    <div class="relative bg-[#04130c] text-white py-16 sm:py-20 overflow-hidden border-b border-emerald-950/60">
        <!-- Ambient Glowing Orbs -->
        <div class="ambient-orb ambient-orb-emerald -top-20 -left-20"></div>
        <div class="ambient-orb ambient-orb-mint -bottom-20 -right-20"></div>
        <div class="atmospheric-mesh-bg"></div>
        <div class="architectural-grid-lines"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-5 z-10">
            <div class="inline-flex items-center space-x-2 bg-emerald-500/20 text-emerald-300 px-4 py-1.5 rounded-full text-xs font-bold border border-emerald-400/30 backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <i class="fa-solid fa-store text-emerald-400"></i>
                <span><?= $isBn ? '🌿 ১৫০+ ভেরিফাইড গ্রিনহাউজ • সার্টিফাইড চাষী নেটওয়ার্ক' : '🌿 150+ Verified Greenhouses • Certified Grower Network' ?></span>
            </div>

            <h1 class="text-3xl sm:text-5xl md:text-6xl font-black tracking-tight text-white leading-tight">
                <?= $isBn ? 'আমাদের সহযোগী <span class="hero-title-gradient">ভেরিফাইড নার্সারিসমূহ</span>' : 'Explore Verified <span class="hero-title-gradient">Nursery Storefronts</span>' ?>
            </h1>

            <p class="text-sm sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed">
                <?= $isBn 
                    ? 'দেশের শীর্ষ সার্টিফাইড গ্রিনহাউজ থেকে সরাসরি গাছ কিনুন, বিশেষজ্ঞ চাষীর সাথে কথা বলুন এবং শতভাগ সুস্থ চারার নিশ্চয়তা উপভোগ করুন।'
                    : 'Source healthy, climate-acclimated botanicals directly from accredited nurseries. Review verified grower credentials, inspect real-time plant inventories, and chat live with specialist growers.' ?>
            </p>

            <!-- Quick Telemetry Stats Bar -->
            <div class="grid grid-cols-3 gap-3 max-w-lg mx-auto pt-4">
                <div class="bg-black/40 backdrop-blur-md p-3 rounded-2xl border border-white/10 text-center">
                    <span class="text-xl sm:text-2xl font-black text-emerald-400"><?= format_num_i18n('150') ?>+</span>
                    <p class="text-[10px] text-slate-300 font-medium"><?= $isBn ? 'গ্রিনহাউজ' : 'Greenhouses' ?></p>
                </div>
                <div class="bg-black/40 backdrop-blur-md p-3 rounded-2xl border border-white/10 text-center">
                    <span class="text-xl sm:text-2xl font-black text-emerald-400"><?= format_num_i18n('99.4') ?>%</span>
                    <p class="text-[10px] text-slate-300 font-medium"><?= $isBn ? 'স্বাস্থ্য রেটিং' : 'Health Score' ?></p>
                </div>
                <div class="bg-black/40 backdrop-blur-md p-3 rounded-2xl border border-white/10 text-center">
                    <span class="text-xl sm:text-2xl font-black text-emerald-400"><?= format_num_i18n('100') ?>%</span>
                    <p class="text-[10px] text-slate-300 font-medium"><?= $isBn ? 'সুরক্ষিত ডেলিভারি' : 'Arrival Guarantee' ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ==============================================================================
         SEARCH & FILTER CONTROL BAR
         ============================================================================== -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-7 relative z-20">
        <div class="bg-white/95 backdrop-blur-xl p-4 sm:p-6 rounded-3xl border border-slate-200/90 shadow-xl shadow-slate-900/5">
            <form action="nurseries.php" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                
                <!-- Search Input -->
                <div class="md:col-span-5 relative">
                    <label for="nursery-search" class="absolute left-4 top-1/2 -translate-y-1/2 text-emerald-600 text-sm">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </label>
                    <input type="text" name="q" id="nursery-search" value="<?= sanitize($searchQuery) ?>" 
                           placeholder="<?= $isBn ? 'নার্সারির নাম বা এলাকা দিয়ে খুঁজুন...' : 'Search by nursery name, city, or specialty...' ?>"
                           class="w-full bg-slate-50 focus:bg-white border border-slate-200 rounded-2xl pl-11 pr-4 py-3 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                </div>

                <!-- City Filter -->
                <div class="md:col-span-3">
                    <select name="city" class="w-full bg-slate-50 focus:bg-white border border-slate-200 rounded-2xl px-4 py-3 text-xs sm:text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition cursor-pointer font-medium">
                        <option value=""><?= $isBn ? 'সকল লোকেশন (All Locations)' : 'All Locations & Cities' ?></option>
                        <?php foreach ($availableCities as $city): ?>
                            <option value="<?= sanitize($city) ?>" <?= $selectedCity === $city ? 'selected' : '' ?>>
                                <?= sanitize($city) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sorting Dropdown -->
                <div class="md:col-span-3">
                    <select name="sort" class="w-full bg-slate-50 focus:bg-white border border-slate-200 rounded-2xl px-4 py-3 text-xs sm:text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition cursor-pointer font-medium">
                        <option value="rating_high" <?= $sortBy === 'rating_high' ? 'selected' : '' ?>><?= $isBn ? 'সর্বোচ্চ রেটিং (Highest Rated)' : 'Highest Customer Rating' ?></option>
                        <option value="plants_high" <?= $sortBy === 'plants_high' ? 'selected' : '' ?>><?= $isBn ? 'সর্বাধিক গাছ (Most Plants)' : 'Most In-Stock Plants' ?></option>
                        <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>><?= $isBn ? 'নাম অনুযায়ী (A to Z)' : 'Name (A to Z)' ?></option>
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>><?= $isBn ? 'নতুন যুক্ত (Newest)' : 'Newest Nurseries' ?></option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="md:col-span-1">
                    <button type="submit" class="w-full h-full min-h-[46px] bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl shadow-md transition flex items-center justify-center cursor-pointer">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

            </form>

            <!-- Active Filter Status Bar -->
            <?php if (!empty($searchQuery) || !empty($selectedCity)): ?>
                <div class="flex flex-wrap items-center gap-2 pt-3 mt-3 border-t border-slate-100 text-xs">
                    <span class="text-slate-500 font-medium"><?= $isBn ? 'সক্রিয় ফিল্টার:' : 'Active Filters:' ?></span>
                    <?php if (!empty($searchQuery)): ?>
                        <span class="bg-emerald-50 text-emerald-800 border border-emerald-200 px-3 py-1 rounded-full font-bold">
                            "<?= sanitize($searchQuery) ?>"
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($selectedCity)): ?>
                        <span class="bg-emerald-50 text-emerald-800 border border-emerald-200 px-3 py-1 rounded-full font-bold">
                            <i class="fa-solid fa-location-dot mr-1"></i><?= sanitize($selectedCity) ?>
                        </span>
                    <?php endif; ?>
                    <a href="nurseries.php" class="text-rose-600 hover:underline font-bold ml-2 flex items-center space-x-1">
                        <i class="fa-solid fa-xmark"></i>
                        <span><?= $isBn ? 'রিসেট' : 'Clear Filters' ?></span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==============================================================================
         ZONE 02/04 AESTHETIC: ARCHITECTURAL NURSERY SHOWCASE CARDS
         ============================================================================== -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">

        <?php if (!empty($nurseries)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php foreach ($nurseries as $n): ?>
                    <?php 
                        $bannerUrl   = !empty($n['banner_image']) ? $n['banner_image'] : 'assets/images/nurseries/default-banner.jpg';
                        $logoUrl     = !empty($n['logo_image']) ? $n['logo_image'] : 'assets/images/nurseries/default-logo.jpg';
                        $ratingAvg   = round((float)($n['calculated_rating'] ?: $n['rating_avg']), 1);
                        $reviewsCt   = (int)$n['total_reviews'];
                        $displayName = ($isBn && !empty($n['nursery_name_bn'])) ? $n['nursery_name_bn'] : $n['nursery_name'];
                        $displayDesc = ($isBn && !empty($n['description_bn'])) ? $n['description_bn'] : ($n['description'] ?: 'Specialized botanical grower offering indoor, outdoor, and exotic plants.');
                        $displayAddr = ($isBn && !empty($n['address_bn'])) ? $n['address_bn'] : $n['address'];
                    ?>
                    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-md hover:shadow-2xl hover:border-emerald-400 hover:-translate-y-1.5 transition-all duration-300 overflow-hidden flex flex-col justify-between group">
                        
                        <!-- Top Banner Image Viewport -->
                        <div>
                            <div class="relative h-48 sm:h-56 w-full overflow-hidden bg-slate-900">
                                <img src="<?= sanitize(get_image_url($bannerUrl)) ?>" 
                                     alt="<?= sanitize($displayName) ?>" 
                                     class="w-full h-full object-cover group-hover:scale-108 transition duration-700">
                                
                                <!-- Dark Gradient Vignette -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-black/10"></div>

                                <!-- Floating Verification Badge -->
                                <div class="absolute top-4 left-4 flex flex-wrap gap-2 z-10">
                                    <span class="live-dispatch-badge">
                                        <div class="pulse-dot-green"></div>
                                        <span><?= $isBn ? 'ভেরিফাইড নার্সারি' : 'Verified Grower' ?></span>
                                    </span>
                                    <?php if ($ratingAvg >= 4.8): ?>
                                        <span class="bg-amber-500/90 backdrop-blur-md text-slate-950 text-[11px] font-extrabold px-3 py-1 rounded-full shadow-md flex items-center space-x-1 border border-amber-300/40">
                                            <i class="fa-solid fa-award"></i>
                                            <span><?= $isBn ? 'টপ রেটেড' : 'Top Rated' ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- City Tag -->
                                <div class="absolute top-4 right-4 z-10">
                                    <span class="bg-black/60 backdrop-blur-md text-white text-[11px] font-semibold px-3 py-1 rounded-full border border-white/20">
                                        <i class="fa-solid fa-location-dot text-emerald-400 mr-1"></i><?= sanitize($n['city']) ?>
                                    </span>
                                </div>

                                <!-- Monogram Logo & Identity -->
                                <div class="absolute bottom-4 left-4 right-4 flex items-end justify-between z-10">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-800 text-white font-black text-2xl sm:text-3xl flex items-center justify-center border-2 border-white shadow-xl shrink-0 group-hover:scale-105 transition">
                                            <?= strtoupper(substr($n['nursery_name'], 0, 1)) ?>
                                        </div>
                                        <div class="text-white truncate drop-shadow-md">
                                            <h3 class="text-lg sm:text-xl font-black leading-tight truncate">
                                                <?= sanitize($displayName) ?>
                                            </h3>
                                            <p class="text-xs text-slate-300 font-medium">
                                                <i class="fa-solid fa-user-tie text-[10px] mr-1 text-emerald-300"></i><?= sanitize($n['owner_name']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="hidden sm:block text-right">
                                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-bold border border-emerald-400/30">
                                            <?= format_num_i18n((int)$n['in_stock_plants']) ?> <?= $isBn ? 'গাছ' : 'Plants' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Body Information -->
                            <div class="p-6 space-y-4">
                                <p class="text-xs sm:text-sm text-slate-600 line-clamp-2 leading-relaxed">
                                    <?= sanitize($displayDesc) ?>
                                </p>

                                <!-- Telemetry Grid -->
                                <div class="grid grid-cols-2 gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-100 text-xs">
                                    <div>
                                        <span class="text-slate-400 font-medium block text-[11px]"><?= $isBn ? 'ঠিকানা' : 'Location' ?></span>
                                        <span class="font-bold text-slate-800 line-clamp-1" title="<?= sanitize($displayAddr) ?>">
                                            <i class="fa-solid fa-map-pin text-emerald-600 mr-1"></i><?= sanitize($displayAddr) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 font-medium block text-[11px]"><?= $isBn ? 'খোলা থাকে' : 'Opening Hours' ?></span>
                                        <span class="font-bold text-slate-800 line-clamp-1">
                                            <i class="fa-regular fa-clock text-teal-600 mr-1"></i><?= sanitize($n['opening_hours'] ?? 'Sat - Thu: 9:00 AM - 8:00 PM') ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 font-medium block text-[11px]"><?= $isBn ? 'স্টকে গাছ' : 'In Stock' ?></span>
                                        <span class="font-bold text-emerald-700">
                                            <i class="fa-solid fa-seedling mr-1"></i><?= format_num_i18n((int)$n['in_stock_plants']) ?> <?= $isBn ? 'টি প্রজাতি' : 'Species' ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 font-medium block text-[11px]"><?= $isBn ? 'গ্রাহক রেটিং' : 'Customer Rating' ?></span>
                                        <div class="flex items-center space-x-1.5 font-bold text-slate-800">
                                            <i class="fa-solid fa-star text-amber-500 text-xs"></i>
                                            <span><?= number_format($ratingAvg, 1) ?></span>
                                            <span class="text-[10px] text-slate-400 font-normal">(<?= format_num_i18n($reviewsCt) ?>)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="px-6 pb-6 pt-2 flex items-center space-x-3">
                            <a href="nursery_detail.php?id=<?= $n['id'] ?>" 
                               class="flex-1 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl shadow-md shadow-emerald-900/10 transition flex items-center justify-center space-x-2 text-xs sm:text-sm">
                                <i class="fa-solid fa-store"></i>
                                <span><?= $isBn ? 'নার্সারি স্টোরফ্রন্ট দেখুন' : 'Visit Nursery Storefront' ?></span>
                            </a>

                            <a href="shop.php?vendor=<?= $n['id'] ?>" 
                               title="<?= $isBn ? 'এই নার্সারির গাছ দেখুন' : 'Browse Nursery Catalog' ?>"
                               class="px-4 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-2xl transition flex items-center justify-center text-xs">
                                <i class="fa-solid fa-layer-group mr-1.5"></i>
                                <span><?= $isBn ? 'ক্যাটালগ' : 'Catalog' ?></span>
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center space-y-4 max-w-lg mx-auto shadow-sm">
                <div class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto text-3xl">
                    <i class="fa-solid fa-store-slash"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800"><?= $isBn ? 'কোনো নার্সারি পাওয়া যায়নি' : 'No Nurseries Found' ?></h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    <?= $isBn 
                        ? 'আপনার অনুসন্ধান বা ফিল্টারের সাথে মিলে এমন কোনো সক্রিয় নার্সারি পাওয়া যায়নি। ফিল্টার রিসেট করে পুনরায় চেষ্টা করুন।'
                        : 'We could not find any verified nurseries matching your filter criteria. Try clearing your search keyword or selected city.' ?>
                </p>
                <a href="nurseries.php" class="inline-block px-6 py-3 bg-emerald-600 text-white font-bold rounded-xl text-xs shadow-md hover:bg-emerald-500 transition">
                    <?= $isBn ? 'সকল নার্সারি দেখুন' : 'View All Nurseries' ?>
                </a>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
