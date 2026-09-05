<?php
// Single Nursery Profile & Dedicated Catalog

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

$db = get_db();
$vendorId = (int)($_GET['id'] ?? ($_GET['vendor_id'] ?? 0));

if ($vendorId <= 0) {
    set_flash('error', 'Invalid nursery ID requested.');
    redirect('nurseries.php');
}

// 1. Fetch Vendor Profile & Owner Information
$vStmt = $db->prepare("
    SELECT vp.*, u.name AS owner_name, u.email AS owner_email, u.phone AS user_phone, u.avatar AS owner_avatar,
           (SELECT COUNT(*) FROM products WHERE vendor_id = vp.id AND status = 'approved') AS total_catalog_count,
           (SELECT COUNT(*) FROM products WHERE vendor_id = vp.id AND status = 'approved' AND stock_quantity > 0) AS in_stock_count,
           (SELECT COUNT(r.id) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.vendor_id = vp.id) AS total_store_reviews,
           (SELECT COALESCE(AVG(r.rating), vp.rating_avg) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.vendor_id = vp.id) AS calculated_rating
    FROM vendor_profiles vp
    JOIN users u ON vp.user_id = u.id
    WHERE vp.id = :id
");
$vStmt->execute(['id' => $vendorId]);
$vendor = $vStmt->fetch();

if (!$vendor) {
    set_flash('error', 'Nursery store not found or is inactive.');
    redirect('nurseries.php');
}

// 2. Capture Catalog Filter Parameters
$searchQuery    = trim($_GET['q'] ?? '');
$selectedCat    = (int)($_GET['category'] ?? 0);
$selectedSun    = trim($_GET['sunlight'] ?? '');
$selectedCare   = trim($_GET['care'] ?? '');
$selectedPet    = isset($_GET['pet_friendly']) ? 1 : 0;
$inStockOnly    = isset($_GET['in_stock']) ? 1 : 0;
$minPrice       = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : 0;
$maxPrice       = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : 5000;
$sortBy         = trim($_GET['sort'] ?? 'featured');

// 3. Catalog Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset  = ($page - 1) * $perPage;

// 4. Build Scoped Prepared SQL Query for this Nursery's Products
$whereClauses = ["p.vendor_id = :vendor_id", "p.status = 'approved'"];
$params = ['vendor_id' => $vendorId];

if (!empty($searchQuery)) {
    $whereClauses[] = "(p.name LIKE :q1 OR p.name_bn LIKE :q1_bn OR p.scientific_name LIKE :q2 OR p.description LIKE :q3)";
    $params['q1']    = '%' . $searchQuery . '%';
    $params['q1_bn'] = '%' . $searchQuery . '%';
    $params['q2']    = '%' . $searchQuery . '%';
    $params['q3']    = '%' . $searchQuery . '%';
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

// 5. Sorting Rules
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
    case 'newest':
        $orderBy = "p.id DESC";
        break;
    case 'featured':
    default:
        $orderBy = "p.is_featured DESC, p.id DESC";
        break;
}

// 6. Count Scoped Products for Pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSql}");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalProducts / $perPage));

// 7. Execute Main Catalog Query with Limit & Offset
$sql = "
    SELECT p.*, c.name AS category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE {$whereSql}
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
";
$pStmt = $db->prepare($sql);
$pStmt->execute($params);
$products = $pStmt->fetchAll();

// 8. Fetch Categories available in this specific nursery for the filter tabs
$catStmt = $db->prepare("
    SELECT DISTINCT c.id, c.name, COUNT(p.id) AS count_in_nursery
    FROM categories c
    JOIN products p ON c.id = p.category_id
    WHERE p.vendor_id = :vid AND p.status = 'approved'
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
");
$catStmt->execute(['vid' => $vendorId]);
$storeCategories = $catStmt->fetchAll();

// 9. Fetch Verified Store Reviews for this Nursery
$revStmt = $db->prepare("
    SELECT r.*, u.name AS reviewer_name, u.avatar AS reviewer_avatar, p.name AS reviewed_product_name, p.id AS reviewed_product_id,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS product_thumb
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    JOIN users u ON r.customer_id = u.id
    WHERE p.vendor_id = :vid
    ORDER BY r.id DESC
    LIMIT 10
");
$revStmt->execute(['vid' => $vendorId]);
$storeReviews = $revStmt->fetchAll();

// Calculate review star rating breakdown (5, 4, 3, 2, 1)
$ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$totalReviewsCount = count($storeReviews);
foreach ($storeReviews as $rev) {
    $star = (int)$rev['rating'];
    if (isset($ratingCounts[$star])) {
        $ratingCounts[$star]++;
    }
}

// Visual and Textual Identifiers
$bannerUrl   = !empty($vendor['banner_image']) ? $vendor['banner_image'] : 'assets/images/nurseries/default-banner.jpg';
$logoUrl     = !empty($vendor['logo_image']) ? $vendor['logo_image'] : 'assets/images/nurseries/default-logo.jpg';
$ratingAvg   = round((float)($vendor['calculated_rating'] ?: $vendor['rating_avg']), 1);
$displayName = (is_bangla() && !empty($vendor['nursery_name_bn'])) ? $vendor['nursery_name_bn'] : $vendor['nursery_name'];
$displayDesc = (is_bangla() && !empty($vendor['description_bn'])) ? $vendor['description_bn'] : ($vendor['description'] ?: 'Specialized botanical grower offering indoor, outdoor, and exotic plants.');
$displayAddr = (is_bangla() && !empty($vendor['address_bn'])) ? $vendor['address_bn'] : $vendor['address'];
$storePhone  = !empty($vendor['contact_phone']) ? $vendor['contact_phone'] : ($vendor['user_phone'] ?? '+880 1800-NURSERY');

$pageTitle = $displayName . ' - ' . (is_bangla() ? 'নার্সারি স্টোরফ্রন্ট' : 'Nursery Storefront');
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- 1. Hero / Header Storefront Banner Section -->
<div class="relative bg-slate-950 text-white">
    
    <!-- Panoramic Banner Image -->
    <div class="relative h-64 sm:h-80 md:h-96 w-full overflow-hidden bg-slate-900">
        <img src="<?= sanitize(get_image_url($bannerUrl)) ?>" 
             alt="<?= sanitize($displayName) ?>" 
             class="w-full h-full object-cover opacity-60">
        
        <!-- Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/50 to-transparent"></div>

        <!-- Breadcrumbs (Top Left) -->
        <div class="absolute top-6 left-4 sm:left-8 flex items-center space-x-2 text-xs font-semibold text-slate-300 bg-black/50 backdrop-blur-md px-3.5 py-1.5 rounded-full border border-white/10">
            <a href="index.php" class="hover:text-white"><?= __('nav.home') ?></a>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
            <a href="nurseries.php" class="hover:text-white"><?= is_bangla() ? 'নার্সারিসমূহ' : 'Nurseries' ?></a>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
            <span class="text-emerald-400 font-bold"><?= sanitize($displayName) ?></span>
        </div>
    </div>

    <!-- Store Profile Header Container (Overlaps Banner) -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-24 sm:-mt-28 relative z-10 pb-8">
        <div class="bg-slate-900/90 backdrop-blur-xl border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
            
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                
                <!-- Logo & Business Name -->
                <div class="flex items-center space-x-4 sm:space-x-6">
                    <div class="relative shrink-0">
                        <img src="<?= sanitize(get_image_url($logoUrl)) ?>" 
                             alt="<?= sanitize($displayName) ?> Logo" 
                             class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl object-cover border-4 border-slate-800 shadow-xl bg-white">
                        <span class="absolute -bottom-1 -right-1 bg-emerald-500 text-white w-7 h-7 rounded-full flex items-center justify-center text-xs shadow-md border-2 border-slate-900" title="Verified Nursery Store">
                            <i class="fa-solid fa-check"></i>
                        </span>
                    </div>

                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                                <?= sanitize($displayName) ?>
                            </h1>
                            <span class="bg-emerald-500/20 text-emerald-300 text-[11px] font-extrabold px-3 py-0.5 rounded-full border border-emerald-400/30">
                                <i class="fa-solid fa-shield-halved text-emerald-400 mr-1"></i><?= is_bangla() ? 'ভেরিফাইড পার্টনার' : 'Verified Partner' ?>
                            </span>
                        </div>

                        <p class="text-xs text-slate-300 flex items-center space-x-2">
                            <span><i class="fa-solid fa-user-tie text-emerald-400 mr-1"></i><?= sanitize($vendor['owner_name']) ?></span>
                            <span class="text-slate-600">•</span>
                            <span><i class="fa-solid fa-location-dot text-emerald-400 mr-1"></i><?= sanitize($displayAddr) ?>, <?= sanitize($vendor['city']) ?></span>
                        </p>
                    </div>
                </div>

                <!-- Action Buttons: Chat, Call, Share -->
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <a href="customer/chat.php?vendor_id=<?= $vendorId ?>" 
                       class="flex-1 md:flex-none px-5 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold rounded-2xl shadow-lg shadow-emerald-900/30 transition flex items-center justify-center space-x-2 text-xs sm:text-sm">
                        <i class="fa-solid fa-comments"></i>
                        <span><?= is_bangla() ? 'নার্সারির সাথে চ্যাট' : 'Chat with Nursery' ?></span>
                    </a>

                    <a href="tel:<?= sanitize($storePhone) ?>" 
                       class="px-4 py-3 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-2xl border border-slate-700 transition flex items-center justify-center space-x-1.5 text-xs sm:text-sm">
                        <i class="fa-solid fa-phone text-emerald-400"></i>
                        <span><?= is_bangla() ? 'কল করুন' : 'Call Store' ?></span>
                    </a>

                    <button type="button" onclick="shareStorefront()" 
                            class="px-4 py-3 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-2xl border border-slate-700 transition flex items-center justify-center space-x-1.5 text-xs sm:text-sm" title="Share Store Link">
                        <i class="fa-solid fa-share-nodes text-teal-400"></i>
                        <span class="hidden sm:inline"><?= is_bangla() ? 'শেয়ার' : 'Share' ?></span>
                    </button>
                </div>

            </div>

            <!-- Description Text -->
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed border-t border-slate-800/80 pt-4">
                <?= sanitize($displayDesc) ?>
            </p>

            <!-- Storefront Quick Metrics Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                <div class="bg-slate-800/60 p-3.5 rounded-2xl border border-slate-700/60 text-center">
                    <span class="text-[11px] text-slate-400 font-medium block"><?= is_bangla() ? 'মোট গাছের তালিকা' : 'Botanical Listings' ?></span>
                    <span class="text-lg font-black text-emerald-400"><?= format_num_i18n((int)$vendor['total_catalog_count']) ?></span>
                </div>
                <div class="bg-slate-800/60 p-3.5 rounded-2xl border border-slate-700/60 text-center">
                    <span class="text-[11px] text-slate-400 font-medium block"><?= is_bangla() ? 'স্টকে প্রস্তুত' : 'In Stock Now' ?></span>
                    <span class="text-lg font-black text-emerald-300"><?= format_num_i18n((int)$vendor['in_stock_count']) ?></span>
                </div>
                <div class="bg-slate-800/60 p-3.5 rounded-2xl border border-slate-700/60 text-center">
                    <span class="text-[11px] text-slate-400 font-medium block"><?= is_bangla() ? 'গড় গ্রাহক রেটিং' : 'Average Rating' ?></span>
                    <span class="text-lg font-black text-amber-400 flex items-center justify-center space-x-1">
                        <i class="fa-solid fa-star text-xs"></i>
                        <span><?= number_format($ratingAvg, 1) ?></span>
                    </span>
                </div>
                <div class="bg-slate-800/60 p-3.5 rounded-2xl border border-slate-700/60 text-center">
                    <span class="text-[11px] text-slate-400 font-medium block"><?= is_bangla() ? 'খোলা থাকে' : 'Opening Hours' ?></span>
                    <span class="text-xs font-bold text-slate-200 line-clamp-1 mt-1"><?= sanitize($vendor['opening_hours'] ?? 'Sat - Thu: 9AM - 8PM') ?></span>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- 2. Catalog & Scoped Products Section -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left Column: Faceted Filters Sidebar -->
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-6 sticky top-24">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                        <i class="fa-solid fa-sliders text-emerald-600"></i>
                        <span><?= is_bangla() ? 'ক্যাটালগ ফিল্টার' : 'Store Catalog Filters' ?></span>
                    </h3>
                    <a href="nursery_detail.php?id=<?= $vendorId ?>" class="text-[11px] text-rose-600 hover:underline font-semibold">
                        <?= is_bangla() ? 'রিসেট' : 'Reset' ?>
                    </a>
                </div>

                <form action="nursery_detail.php" method="GET" class="space-y-5 text-xs">
                    <input type="hidden" name="id" value="<?= $vendorId ?>">

                    <!-- Search within Nursery -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-700"><?= is_bangla() ? 'এই নার্সারিতে খুঁজুন' : 'Search in this store' ?></label>
                        <div class="relative">
                            <input type="text" name="q" value="<?= sanitize($searchQuery) ?>" 
                                   placeholder="<?= is_bangla() ? 'গাছের নাম...' : 'Plant name...' ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                        </div>
                    </div>

                    <!-- Categories Filter -->
                    <?php if (!empty($storeCategories)): ?>
                        <div class="space-y-2">
                            <label class="block font-bold text-slate-700"><?= is_bangla() ? 'ক্যাটাগরি' : 'Plant Category' ?></label>
                            <select name="category" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="0"><?= is_bangla() ? 'সকল ক্যাটাগরি' : 'All Categories' ?></option>
                                <?php foreach ($storeCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $selectedCat === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($cat['name']) ?> (<?= $cat['count_in_nursery'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Sunlight Filter -->
                    <div class="space-y-2">
                        <label class="block font-bold text-slate-700"><?= is_bangla() ? 'সূর্যালোকের চাহিদা' : 'Sunlight Need' ?></label>
                        <select name="sunlight" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value=""><?= is_bangla() ? 'যেকোনো আলো' : 'Any Sunlight' ?></option>
                            <option value="Bright Indirect" <?= $selectedSun === 'Bright Indirect' ? 'selected' : '' ?>>Bright Indirect</option>
                            <option value="Full Sun" <?= $selectedSun === 'Full Sun' ? 'selected' : '' ?>>Full Sun</option>
                            <option value="Low Light" <?= $selectedSun === 'Low Light' ? 'selected' : '' ?>>Low Light</option>
                            <option value="Partial Shade" <?= $selectedSun === 'Partial Shade' ? 'selected' : '' ?>>Partial Shade</option>
                        </select>
                    </div>

                    <!-- Care Level Filter -->
                    <div class="space-y-2">
                        <label class="block font-bold text-slate-700"><?= is_bangla() ? 'যত্নের মাত্রা' : 'Care Difficulty' ?></label>
                        <select name="care" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value=""><?= is_bangla() ? 'যেকোনো যত্ন' : 'Any Care Level' ?></option>
                            <option value="Easy" <?= $selectedCare === 'Easy' ? 'selected' : '' ?>>Easy (Beginner)</option>
                            <option value="Moderate" <?= $selectedCare === 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                            <option value="Expert" <?= $selectedCare === 'Expert' ? 'selected' : '' ?>>Expert (Collector)</option>
                        </select>
                    </div>

                    <!-- In Stock Checkbox -->
                    <div class="pt-2 border-t border-slate-100 space-y-2">
                        <label class="flex items-center space-x-2.5 cursor-pointer">
                            <input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?> class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-slate-700 font-semibold"><?= is_bangla() ? 'শুধুমাত্র স্টকে থাকা গাছ' : 'In Stock Only' ?></span>
                        </label>
                        <label class="flex items-center space-x-2.5 cursor-pointer">
                            <input type="checkbox" name="pet_friendly" value="1" <?= $selectedPet ? 'checked' : '' ?> class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-slate-700 font-semibold"><?= is_bangla() ? 'পোষা প্রাণী বান্ধব' : '🐾 Pet Friendly' ?></span>
                        </label>
                    </div>

                    <!-- Submit Filter Button -->
                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-filter"></i>
                        <span><?= is_bangla() ? 'ফিল্টার প্রয়োগ করুন' : 'Apply Store Filters' ?></span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: Products Grid & Header -->
        <div class="lg:col-span-9 space-y-6">
            
            <!-- Store Catalog Header Bar -->
            <div class="bg-white p-4 sm:p-5 rounded-3xl border border-slate-200/80 shadow-xs flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base sm:text-lg font-black text-slate-900 flex items-center space-x-2">
                        <span><?= is_bangla() ? 'উপলব্ধ বোটানিক্যাল গাছপালা' : 'Available Botanical Plants' ?></span>
                        <span class="text-xs font-semibold bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full">
                            <?= format_num_i18n($totalProducts) ?> <?= is_bangla() ? 'টি গাছ' : 'items' ?>
                        </span>
                    </h2>
                </div>

                <!-- Sorting Form -->
                <form action="nursery_detail.php" method="GET" class="flex items-center space-x-2 text-xs">
                    <input type="hidden" name="id" value="<?= $vendorId ?>">
                    <?php if (!empty($searchQuery)): ?><input type="hidden" name="q" value="<?= sanitize($searchQuery) ?>"><?php endif; ?>
                    <?php if ($selectedCat > 0): ?><input type="hidden" name="category" value="<?= $selectedCat ?>"><?php endif; ?>
                    <label class="text-slate-500 font-medium whitespace-nowrap"><?= is_bangla() ? 'সর্ট করুন:' : 'Sort by:' ?></label>
                    <select name="sort" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="featured" <?= $sortBy === 'featured' ? 'selected' : '' ?>><?= is_bangla() ? 'ফিচার্ড (Featured)' : 'Featured & Popular' ?></option>
                        <option value="price_low" <?= $sortBy === 'price_low' ? 'selected' : '' ?>><?= is_bangla() ? 'দাম: কম থেকে বেশি' : 'Price: Low to High' ?></option>
                        <option value="price_high" <?= $sortBy === 'price_high' ? 'selected' : '' ?>><?= is_bangla() ? 'দাম: বেশি থেকে কম' : 'Price: High to Low' ?></option>
                        <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>><?= is_bangla() ? 'নাম (A to Z)' : 'Name (A to Z)' ?></option>
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>><?= is_bangla() ? 'নতুন আগত' : 'New Arrivals' ?></option>
                    </select>
                </form>
            </div>

            <!-- Products Listing Grid -->
            <?php if (!empty($products)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                    <?php foreach ($products as $p): ?>
                        <?php
                            $imgUrl = !empty($p['primary_image']) ? $p['primary_image'] : 'assets/images/plants/default-plant.jpg';
                            $pName = (is_bangla() && !empty($p['name_bn'])) ? $p['name_bn'] : $p['name'];
                            $hasDiscount = !empty($p['discount_price']) && (float)$p['discount_price'] < (float)$p['price'];
                            $effectivePrice = $hasDiscount ? (float)$p['discount_price'] : (float)$p['price'];
                            $discountPercent = $hasDiscount ? round((((float)$p['price'] - (float)$p['discount_price']) / (float)$p['price']) * 100) : 0;
                            $inStock = (int)$p['stock_quantity'] > 0;
                        ?>
                        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300 overflow-hidden flex flex-col justify-between group">
                            
                            <div>
                                <!-- Image Container -->
                                <div class="relative h-48 sm:h-52 w-full overflow-hidden bg-slate-100">
                                    <a href="product.php?id=<?= $p['id'] ?>">
                                        <img src="<?= sanitize(get_image_url($imgUrl)) ?>" 
                                             alt="<?= sanitize($pName) ?>" 
                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                    </a>

                                    <!-- Top Badges -->
                                    <div class="absolute top-3 left-3 flex flex-col gap-1.5">
                                        <?php if ($hasDiscount): ?>
                                            <span class="bg-rose-500 text-white text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-md">
                                                -<?= $discountPercent ?>% OFF
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($p['is_pet_friendly']): ?>
                                            <span class="bg-emerald-600/90 backdrop-blur-md text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full shadow-md" title="Pet Friendly">
                                                🐾 Safe
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Stock Status Indicator -->
                                    <div class="absolute bottom-3 right-3">
                                        <?php if ($inStock): ?>
                                            <span class="bg-white/90 backdrop-blur-md text-emerald-800 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full shadow-xs border border-emerald-200">
                                                <?= (int)$p['stock_quantity'] ?> in stock
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-rose-600 text-white text-[10px] font-extrabold px-2.5 py-0.5 rounded-full shadow-md">
                                                Sold Out
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Card Body -->
                                <div class="p-4 sm:p-5 space-y-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 block">
                                        <?= sanitize($p['category_name']) ?>
                                    </span>

                                    <h3 class="text-sm font-bold text-slate-900 line-clamp-1 group-hover:text-emerald-700 transition">
                                        <a href="product.php?id=<?= $p['id'] ?>"><?= sanitize($pName) ?></a>
                                    </h3>

                                    <?php if (!empty($p['scientific_name'])): ?>
                                        <p class="text-[11px] text-slate-400 italic line-clamp-1"><?= sanitize($p['scientific_name']) ?></p>
                                    <?php endif; ?>

                                    <!-- Care & Sunlight Meta -->
                                    <div class="flex items-center space-x-2 text-[10px] text-slate-500 pt-1">
                                        <span class="bg-slate-100 px-2 py-0.5 rounded-md"><i class="fa-solid fa-sun text-amber-500 mr-1"></i><?= sanitize($p['sunlight_requirement']) ?></span>
                                        <span class="bg-slate-100 px-2 py-0.5 rounded-md"><i class="fa-solid fa-droplet text-blue-500 mr-1"></i><?= sanitize($p['care_level']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Action / Price Bar -->
                            <div class="p-4 sm:p-5 pt-0 flex items-center justify-between border-t border-slate-100 mt-2">
                                <div>
                                    <span class="text-base font-black text-slate-900 block"><?= format_currency($effectivePrice) ?></span>
                                    <?php if ($hasDiscount): ?>
                                        <span class="text-[10px] text-slate-400 line-through"><?= format_currency($p['price']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($inStock): ?>
                                    <button type="button" onclick="quickAddToCart(<?= $p['id'] ?>)" 
                                            class="w-9 h-9 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center shadow-md shadow-emerald-900/10 transition cursor-pointer" 
                                            title="<?= __('common.add_to_cart') ?>">
                                        <i class="fa-solid fa-cart-plus text-xs"></i>
                                    </button>
                                <?php else: ?>
                                    <button disabled class="w-9 h-9 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center cursor-not-allowed">
                                        <i class="fa-solid fa-ban text-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination Controls -->
                <?php if ($totalPages > 1): ?>
                    <div class="pt-8 flex items-center justify-center space-x-2">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php 
                                $queryParams = $_GET;
                                $queryParams['page'] = $i;
                                $pageLink = 'nursery_detail.php?' . http_build_query($queryParams);
                            ?>
                            <a href="<?= $pageLink ?>" 
                               class="w-10 h-10 rounded-xl font-bold text-xs flex items-center justify-center transition <?= $page === $i ? 'bg-emerald-600 text-white shadow-md' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' ?>">
                                <?= format_num_i18n($i) ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty Scoped Products State -->
                <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center space-y-4">
                    <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto text-2xl">
                        <i class="fa-solid fa-seedling"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800"><?= is_bangla() ? 'কোনো গাছ পাওয়া যায়নি' : 'No Plants Match Filters' ?></h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        <?= is_bangla() ? 'এই নার্সারিতে আপনার নির্বাচিত ফিল্টারের কোনো গাছ পাওয়া যায়নি। ফিল্টার রিসেট করে দেখুন।' : 'There are currently no plants matching your active search and filter criteria in this nursery store.' ?>
                    </p>
                    <a href="nursery_detail.php?id=<?= $vendorId ?>" class="inline-block px-4 py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-xs">
                        <?= is_bangla() ? 'ফিল্টার রিসেট' : 'Clear Store Filters' ?>
                    </a>
                </div>
            <?php endif; ?>

        </div>

    </div>

</div>

<!-- 3. Customer Verified Reviews Section -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 border-t border-slate-200">
    <div class="bg-white p-6 sm:p-10 rounded-3xl border border-slate-200/80 shadow-sm space-y-8">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'প্রমাণিত ফিডব্যাক' : 'Verified Buyer Feedback' ?></span>
                <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'এই নার্সারির গ্রাহক রিভিউ' : 'Customer Reviews for this Nursery' ?></h3>
            </div>

            <div class="flex items-center space-x-3 bg-slate-50 px-4 py-2 rounded-2xl border border-slate-200/60">
                <span class="text-2xl font-black text-slate-900"><?= number_format($ratingAvg, 1) ?></span>
                <div>
                    <div class="flex text-amber-400 text-xs">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <i class="fa-solid fa-star <?= $s <= round($ratingAvg) ? 'text-amber-400' : 'text-slate-200' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="text-[10px] text-slate-400 font-semibold"><?= format_num_i18n($totalReviewsCount) ?> <?= is_bangla() ? 'টি রিভিউ' : 'Verified Reviews' ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($storeReviews)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($storeReviews as $r): ?>
                    <div class="bg-slate-50/70 p-5 rounded-2xl border border-slate-200/70 space-y-3 flex flex-col justify-between">
                        <div class="space-y-2">
                            <!-- Star Rating & Review Date -->
                            <div class="flex items-center justify-between">
                                <div class="flex text-amber-400 text-xs">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="fa-solid fa-star <?= $s <= (int)$r['rating'] ? 'text-amber-400' : 'text-slate-200' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-[10px] text-slate-400"><?= time_ago($r['created_at']) ?></span>
                            </div>

                            <!-- Comment -->
                            <p class="text-xs text-slate-700 leading-relaxed italic">
                                "<?= sanitize($r['review_text']) ?>"
                            </p>
                        </div>

                        <!-- Reviewer & Plant Tag -->
                        <div class="pt-3 border-t border-slate-200/50 flex items-center justify-between text-[11px]">
                            <div class="flex items-center space-x-2">
                                <div class="w-6 h-6 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-[10px]">
                                    <?= strtoupper(substr($r['reviewer_name'], 0, 1)) ?>
                                </div>
                                <span class="font-bold text-slate-800"><?= sanitize($r['reviewer_name']) ?></span>
                                <span class="text-emerald-600 text-[10px]" title="Purchase Verified"><i class="fa-solid fa-circle-check"></i></span>
                            </div>

                            <a href="product.php?id=<?= $r['reviewed_product_id'] ?>" class="text-emerald-700 hover:underline font-semibold truncate max-w-[150px]">
                                <?= sanitize($r['reviewed_product_name']) ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-8 text-center text-slate-400 text-xs space-y-2">
                <i class="fa-regular fa-comment-dots text-3xl text-slate-300"></i>
                <p><?= is_bangla() ? 'এই নার্সারির জন্য এখনো কোনো গ্রাহক রিভিউ জমা হয়নি।' : 'No reviews recorded yet for this nursery. Be the first to purchase and review!' ?></p>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Simple Toast Notification Script for Link Sharing -->
<script>
function shareStorefront() {
    const url = window.location.href;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            alert("🌿 Storefront URL copied to clipboard!");
        });
    } else {
        alert("Store URL: " + url);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
