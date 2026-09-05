<?php
// Botanical Catalog & Specimen Marketplace (Bilingual)
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

$db = get_db();
$isBn = is_bangla();

// Filter Parameters
$searchQuery    = trim($_GET['q'] ?? '');
$selectedCat    = (int)($_GET['category'] ?? 0);
$selectedSun    = trim($_GET['sunlight'] ?? '');
$selectedCare   = trim($_GET['care'] ?? '');
$selectedVendor = (int)($_GET['vendor'] ?? 0);
$selectedPet    = isset($_GET['pet_friendly']) ? 1 : 0;
$inStockOnly    = isset($_GET['in_stock']) ? 1 : 0;
$minPrice       = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : 0;
$maxPrice       = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : 5000;
$sortBy         = trim($_GET['sort'] ?? 'newest');

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset  = ($page - 1) * $perPage;

// Base Query
$whereClauses = ["p.status = 'approved'"];
$params = [];

if (!empty($searchQuery)) {
    $whereClauses[] = "(p.name LIKE :q1 OR p.name_bn LIKE :q1_bn OR p.scientific_name LIKE :q2 OR p.description LIKE :q3)";
    $params['q1'] = '%' . $searchQuery . '%';
    $params['q1_bn'] = '%' . $searchQuery . '%';
    $params['q2'] = '%' . $searchQuery . '%';
    $params['q3'] = '%' . $searchQuery . '%';
}

if ($selectedCat > 0) {
    $whereClauses[] = "p.category_id = :cat_id";
    $params['cat_id'] = $selectedCat;
}

if (!empty($selectedSun)) {
    $whereClauses[] = "p.sunlight_requirement = :sunlight";
    $params['sunlight'] = $selectedSun;
}

if (!empty($selectedCare)) {
    $whereClauses[] = "p.care_level = :care";
    $params['care'] = $selectedCare;
}

if ($selectedVendor > 0) {
    $whereClauses[] = "p.vendor_id = :vendor_id";
    $params['vendor_id'] = $selectedVendor;
}

if ($selectedPet) {
    $whereClauses[] = "p.is_pet_friendly = 1";
}

if ($inStockOnly) {
    $whereClauses[] = "p.stock_quantity > 0";
}

if ($minPrice > 0) {
    $whereClauses[] = "COALESCE(p.discount_price, p.price) >= :min_price";
    $params['min_price'] = $minPrice;
}

if ($maxPrice < 5000) {
    $whereClauses[] = "COALESCE(p.discount_price, p.price) <= :max_price";
    $params['max_price'] = $maxPrice;
}

$whereSql = implode(' AND ', $whereClauses);

// Sorting
$orderBy = "p.id DESC";
switch ($sortBy) {
    case 'price_low':
        $orderBy = "COALESCE(p.discount_price, p.price) ASC";
        break;
    case 'price_high':
        $orderBy = "COALESCE(p.discount_price, p.price) DESC";
        break;
    case 'name_asc':
        $orderBy = "p.name ASC";
        break;
    case 'featured':
        $orderBy = "p.is_featured DESC, p.id DESC";
        break;
    default:
        $orderBy = "p.is_featured DESC, p.id ASC";
        break;
}

// Total Count for Pagination
$countSql = "SELECT COUNT(*) FROM products p WHERE {$whereSql}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalProducts / $perPage));

// Main Query with Limit & Offset
$sql = "
    SELECT p.*, p.name_bn, c.name AS category_name, c.name_bn AS category_name_bn, v.nursery_name, v.nursery_name_bn,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN vendor_profiles v ON p.vendor_id = v.id
    WHERE {$whereSql}
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch Categories & Vendors for filter sidebar
$allCategories = $db->query("SELECT id, name, name_bn FROM categories WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$allVendors = $db->query("SELECT id, nursery_name, nursery_name_bn FROM vendor_profiles WHERE verification_status = 'verified' ORDER BY nursery_name ASC")->fetchAll();

$pageTitle = $isBn ? 'বোটানিক্যাল মার্কেটপ্লেস ক্যাটালগ • ' . __('app.name') : 'Botanical Catalog & Specimens • ' . __('app.name');

$extraHead = '
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/ultra-modern-home.css?v=' . (file_exists(__DIR__ . '/assets/css/ultra-modern-home.css') ? filemtime(__DIR__ . '/assets/css/ultra-modern-home.css') : time()) . '">
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/botanical-showcase.css?v=' . (file_exists(__DIR__ . '/assets/css/botanical-showcase.css') ? filemtime(__DIR__ . '/assets/css/botanical-showcase.css') : time()) . '">
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/spatial-greenhouse.css?v=' . (file_exists(__DIR__ . '/assets/css/spatial-greenhouse.css') ? filemtime(__DIR__ . '/assets/css/spatial-greenhouse.css') : time()) . '">
';

$extraFooter = '
    <script src="' . BASE_URL . '/assets/js/gallery-interactions.js?v=' . (file_exists(__DIR__ . '/assets/js/gallery-interactions.js') ? filemtime(__DIR__ . '/assets/js/gallery-interactions.js') : time()) . '"></script>
';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="relative bg-slate-50 text-slate-800 min-h-screen">
    
    <!-- ==============================================================================
         ZONE 01 AESTHETIC: CINEMATIC CATALOG HERO
         ============================================================================== -->
    <div class="relative bg-[#04130c] text-white py-14 sm:py-18 overflow-hidden border-b border-emerald-950/60">
        <!-- Universal Ambient Glowing Orbs -->
        <div class="ambient-orb ambient-orb-emerald -top-20 -left-20"></div>
        <div class="ambient-orb ambient-orb-mint -bottom-20 -right-20"></div>
        <div class="atmospheric-mesh-bg"></div>
        <div class="architectural-grid-lines"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-4 z-10">
            <div class="inline-flex items-center space-x-2 bg-emerald-500/20 text-emerald-300 px-4 py-1.5 rounded-full text-xs font-bold border border-emerald-400/30 backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <i class="fa-solid fa-layer-group text-emerald-400"></i>
                <span><?= $isBn ? '🌿 ইন-স্টক বোটানিক্যাল স্পেসিমেন ক্যাটালগ' : '🌿 Verified In-Stock Botanical Catalog' ?></span>
            </div>

            <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-white leading-tight">
                <?= $isBn ? 'বাছাইকৃত উদ্ভিদ <span class="hero-title-gradient">মার্কেটপ্লেস</span>' : 'Curated Botanical <span class="hero-title-gradient">Specimen Marketplace</span>' ?>
            </h1>

            <p class="text-sm sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed">
                <?= $isBn 
                    ? 'সার্টিফাইড নার্সারি থেকে সংগৃহীত সুস্থ চারা, এআই প্ল্যান্ট কেয়ার স্পেক্স ও দ্রুত সুরক্ষিত ডেলিভারি।'
                    : 'Explore premium indoor foliage, NASA-certified air purifiers, and living heritage bonsai acclimated for your space.' ?>
            </p>

            <!-- Category Pills Bar -->
            <div class="flex flex-wrap items-center justify-center gap-2 pt-3">
                <a href="shop.php" class="px-4 py-2 rounded-full text-xs font-bold transition <?= $selectedCat === 0 ? 'bg-emerald-500 text-slate-950 shadow-md' : 'bg-black/50 text-slate-300 hover:text-white border border-white/10' ?>">
                    <?= $isBn ? '🌿 সকল প্রজাতি' : '🌿 All Species' ?>
                </a>
                <?php foreach ($allCategories as $cat): ?>
                    <a href="shop.php?category=<?= $cat['id'] ?>" class="px-4 py-2 rounded-full text-xs font-bold transition <?= $selectedCat === (int)$cat['id'] ? 'bg-emerald-500 text-slate-950 shadow-md' : 'bg-black/50 text-slate-300 hover:text-white border border-white/10' ?>">
                        <?= sanitize(category_display_name($cat)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ==============================================================================
         CATALOG & FILTERS CONTAINER
         ============================================================================== -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Filters Sidebar -->
            <div class="lg:col-span-3 space-y-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-md space-y-6 sticky top-24">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center space-x-1.5">
                            <i class="fa-solid fa-sliders text-emerald-600"></i>
                            <span><?= __('shop.filters') ?></span>
                        </span>
                        <a href="shop.php" class="text-[11px] font-bold text-rose-500 hover:underline"><?= __('shop.reset_filters') ?></a>
                    </div>

                    <form action="shop.php" method="GET" class="space-y-4 text-xs">
                        
                        <!-- Search Keyword -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= __('common.search') ?></label>
                            <input type="text" name="q" value="<?= sanitize($searchQuery) ?>" placeholder="<?= __('nav.search_placeholder') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <!-- Category -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= __('shop.category') ?></label>
                            <select name="category" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value=""><?= __('shop.all_categories') ?></option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $selectedCat === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= sanitize(category_display_name($cat)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Sunlight Requirement -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= __('product.sunlight') ?></label>
                            <select name="sunlight" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value=""><?= __('shop.all_sunlight') ?></option>
                                <option value="Bright Indirect" <?= $selectedSun === 'Bright Indirect' ? 'selected' : '' ?>><?= __('sunlight.bright_indirect') ?></option>
                                <option value="Low Light" <?= $selectedSun === 'Low Light' ? 'selected' : '' ?>><?= __('sunlight.low_light') ?></option>
                                <option value="Partial Shade" <?= $selectedSun === 'Partial Shade' ? 'selected' : '' ?>><?= __('sunlight.partial_shade') ?></option>
                                <option value="Full Sun" <?= $selectedSun === 'Full Sun' ? 'selected' : '' ?>><?= __('sunlight.direct') ?></option>
                            </select>
                        </div>

                        <!-- Care Difficulty -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= __('product.care_level') ?></label>
                            <select name="care" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value=""><?= __('shop.all_care_levels') ?></option>
                                <option value="Easy" <?= $selectedCare === 'Easy' ? 'selected' : '' ?>><?= __('care.easy') ?></option>
                                <option value="Moderate" <?= $selectedCare === 'Moderate' ? 'selected' : '' ?>><?= __('care.moderate') ?></option>
                                <option value="Expert" <?= $selectedCare === 'Expert' ? 'selected' : '' ?>><?= __('care.expert') ?></option>
                            </select>
                        </div>

                        <!-- Verified Nursery Vendor -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= __('product.verified_nursery') ?></label>
                            <select name="vendor" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value=""><?= __('shop.all_vendors') ?></option>
                                <?php foreach ($allVendors as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= $selectedVendor === (int)$v['id'] ? 'selected' : '' ?>>
                                        <?= sanitize(vendor_display_name($v)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Price Range Slider -->
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="font-bold text-slate-700"><?= __('shop.price_range') ?></label>
                                <span id="price-val" class="font-mono font-bold text-emerald-700"><?= format_currency($maxPrice) ?></span>
                            </div>
                            <input type="range" name="max_price" min="100" max="5000" step="50" value="<?= $maxPrice ?>" 
                                   oninput="document.getElementById('price-val').innerText = '৳' + this.value"
                                   class="w-full accent-emerald-600">
                        </div>

                        <!-- Checkbox Flags -->
                        <div class="space-y-2 pt-2 border-t border-slate-100">
                            <label class="flex items-center space-x-2 text-slate-700 cursor-pointer">
                                <input type="checkbox" name="pet_friendly" value="1" <?= $selectedPet ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500">
                                <span class="font-medium">🐾 <?= __('product.pet_friendly') ?></span>
                            </label>
                            <label class="flex items-center space-x-2 text-slate-700 cursor-pointer">
                                <input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500">
                                <span class="font-medium">📦 <?= __('product.in_stock') ?></span>
                            </label>
                        </div>

                        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl shadow-md transition flex items-center justify-center space-x-1.5 cursor-pointer">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <span><?= __('shop.search_btn') ?></span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Catalog Product Grid -->
            <div class="lg:col-span-9 space-y-6">
                
                <!-- Result Header & Sort Bar -->
                <div class="bg-white p-4 rounded-3xl border border-slate-200/90 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                    <span class="text-slate-700 font-bold">
                        <?= __('shop.showing_results', null, [':count' => $totalProducts]) ?>
                    </span>

                    <div class="flex items-center space-x-2">
                        <span class="text-slate-400 font-medium"><?= __('shop.sort_by') ?>:</span>
                        <select onchange="location = this.value;" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-semibold cursor-pointer">
                            <option value="<?= url_with(['sort' => 'featured', 'page' => 1]) ?>" <?= $sortBy === 'featured' ? 'selected' : '' ?>><?= __('shop.sort_newest') ?></option>
                            <option value="<?= url_with(['sort' => 'price_low', 'page' => 1]) ?>" <?= $sortBy === 'price_low' ? 'selected' : '' ?>><?= __('shop.sort_price_asc') ?></option>
                            <option value="<?= url_with(['sort' => 'price_high', 'page' => 1]) ?>" <?= $sortBy === 'price_high' ? 'selected' : '' ?>><?= __('shop.sort_price_desc') ?></option>
                            <option value="<?= url_with(['sort' => 'name_asc', 'page' => 1]) ?>" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>><?= __('shop.sort_name') ?></option>
                        </select>
                    </div>
                </div>

                <!-- ==============================================================================
                     ZONE 02/05 AESTHETIC: ARCHITECTURAL SPECIMEN CARDS GRID
                     ============================================================================== -->
                <?php if (empty($products)): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 p-12 text-center space-y-4 shadow-sm">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl mx-auto">
                            <i class="fa-solid fa-seedling"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800"><?= __('shop.no_plants_found') ?></h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">
                            <?= __('shop.try_adjusting') ?>
                        </p>
                        <a href="shop.php" class="inline-block px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md transition">
                            <?= __('shop.reset_filters') ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                        <?php foreach ($products as $idx => $product): ?>
                            <?php 
                                $primaryImg = get_image_url($product['primary_image']);
                                $altImg1 = $product['category_id'] == 1 ? 'assets/images/plants/fiddle-leaf-fig.jpg' : 'assets/images/plants/monstera.jpg';
                                $altImg2 = 'assets/images/plants/golden-pothos.jpg';
                            ?>
                            <div class="specimen-card group" data-aos="fade-up" data-aos-delay="<?= ($idx % 3) * 50 ?>">
                                
                                <div class="specimen-img-viewport">
                                    <div class="absolute top-3 left-3 z-30">
                                        <div class="live-dispatch-badge">
                                            <div class="pulse-dot-green"></div>
                                            <span><?= $product['stock_quantity'] > 0 ? ($isBn ? 'ইন-স্টক' : 'Verified In-Stock') : ($isBn ? 'স্টক শেষ' : 'Out of Stock') ?></span>
                                        </div>
                                    </div>

                                    <img src="<?= sanitize($primaryImg) ?>" 
                                         alt="<?= sanitize(product_display_name($product)) ?>" 
                                         class="specimen-main-img"
                                         onerror="this.onerror=null; this.src='<?= BASE_URL ?>/assets/images/plants/default-plant.jpg';">

                                    <!-- Mini Thumbnail Switcher Dots -->
                                    <div class="specimen-thumb-strip">
                                        <div class="specimen-thumb-dot is-active" data-src="<?= sanitize($primaryImg) ?>" title="Main Angle"></div>
                                        <div class="specimen-thumb-dot" data-src="<?= BASE_URL ?>/<?= sanitize($altImg1) ?>" title="Leaf Detail"></div>
                                        <div class="specimen-thumb-dot" data-src="<?= BASE_URL ?>/<?= sanitize($altImg2) ?>" title="Potted Angle"></div>
                                    </div>

                                    <!-- Quick-Spec Frosted Overlay with SVG Meters -->
                                    <div class="specimen-quick-overlay">
                                        <div class="space-y-3">
                                            <div class="flex items-center justify-between text-xs text-emerald-300 font-bold border-b border-white/15 pb-2">
                                                <span><i class="fa-solid fa-shield-halved mr-1"></i><?= $isBn ? 'স্মার্ট মেট্রিক্স' : 'Specimen Telemetry' ?></span>
                                                <span class="text-white">99.4% Health</span>
                                            </div>

                                            <div class="space-y-1">
                                                <div class="flex justify-between text-[11px] text-slate-300">
                                                    <span><i class="fa-solid fa-sun text-amber-400 mr-1"></i><?= $isBn ? 'আলো' : 'Sunlight' ?></span>
                                                    <span class="font-bold text-white"><?= sanitize(sunlight_i18n($product['sunlight_requirement'])) ?></span>
                                                </div>
                                                <div class="meter-bar-track">
                                                    <div class="meter-bar-fill" style="--meter-val: 85%;"></div>
                                                </div>
                                            </div>

                                            <div class="space-y-1">
                                                <div class="flex justify-between text-[11px] text-slate-300">
                                                    <span><i class="fa-solid fa-droplet text-cyan-400 mr-1"></i><?= $isBn ? 'পানি' : 'Water' ?></span>
                                                    <span class="font-bold text-white"><?= sanitize($product['water_frequency']) ?></span>
                                                </div>
                                                <div class="meter-bar-track">
                                                    <div class="meter-bar-fill" style="--meter-val: 65%;"></div>
                                                </div>
                                            </div>

                                            <div class="space-y-1">
                                                <div class="flex justify-between text-[11px] text-slate-300">
                                                    <span><i class="fa-solid fa-wind text-emerald-400 mr-1"></i><?= $isBn ? 'অক্সিজেন' : 'NASA Score' ?></span>
                                                    <span class="font-bold text-teal-300">98.2 / 100</span>
                                                </div>
                                                <div class="meter-bar-track">
                                                    <div class="meter-bar-fill" style="--meter-val: 98%;"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pt-2 border-t border-white/15 flex items-center justify-between">
                                            <span class="text-[11px] text-emerald-300 truncate">
                                                <i class="fa-solid fa-store mr-1"></i><?= sanitize(vendor_display_name($product['nursery_name'])) ?>
                                            </span>
                                            <a href="plant-matcher.php" class="px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white text-[10px] font-bold rounded-lg transition backdrop-blur-md">
                                                <?= $isBn ? 'AI ম্যাচ' : 'AI Match' ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-5 flex-1 flex flex-col justify-between space-y-3 bg-white">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-emerald-700 font-bold uppercase tracking-wider text-[10px]">
                                                <?= sanitize(category_display_name($product['category_name'])) ?>
                                            </span>
                                            <span class="text-slate-400 text-[10px]">
                                                <i class="fa-solid fa-location-dot text-emerald-600 mr-1"></i><?= sanitize(vendor_display_name($product['nursery_name'])) ?>
                                            </span>
                                        </div>

                                        <h3 class="font-black text-slate-900 text-base group-hover:text-emerald-700 transition line-clamp-1">
                                            <a href="product.php?id=<?= $product['id'] ?>"><?= sanitize(product_display_name($product)) ?></a>
                                        </h3>

                                        <?php if ($product['scientific_name']): ?>
                                            <p class="text-[11px] text-slate-400 italic font-serif"><?= sanitize($product['scientific_name']) ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                                        <div>
                                            <span class="text-[10px] text-slate-400 block font-semibold uppercase"><?= $isBn ? 'মূল্য' : 'Price' ?></span>
                                            <span class="text-xl font-black text-emerald-700">
                                                <?= format_currency($product['discount_price'] ?: $product['price']) ?>
                                            </span>
                                        </div>

                                        <div class="flex items-center space-x-1.5">
                                            <a href="product.php?id=<?= $product['id'] ?>" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                                                <?= __('common.details') ?>
                                            </a>
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <button onclick="quickAddToCart(<?= $product['id'] ?>)" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center space-x-1 cursor-pointer" title="<?= __('product.add_to_cart') ?>">
                                                    <i class="fa-solid fa-basket-shopping"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-[10px] text-rose-500 font-bold px-2 py-1 bg-rose-50 rounded-lg"><?= __('product.out_of_stock') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Modern Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="flex items-center justify-center space-x-2 pt-8">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php $pageUrl = "shop.php?" . http_build_query(array_merge($_GET, ['page' => $i])); ?>
                                <a href="<?= $pageUrl ?>" 
                                   class="w-10 h-10 flex items-center justify-center rounded-2xl text-xs font-black transition <?= $page === $i ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100' ?>">
                                    <?= format_num_i18n($i) ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
