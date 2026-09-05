<?php
// Single Plant Details & Verified Reviews (Bilingual)

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

$db = get_db();
$productId = (int)($_GET['id'] ?? 0);
$currentUser = current_user();

if ($productId <= 0) {
    set_flash('error', 'Plant not found.');
    redirect('shop.php');
}

// Fetch Product Details
$stmt = $db->prepare("
    SELECT p.*, p.name_bn, c.name AS category_name, c.slug AS category_slug,
           vp.id AS vendor_profile_id, vp.nursery_name, vp.address AS vendor_address, vp.user_id AS vendor_user_id,
           vp.rating_avg AS vendor_rating
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN vendor_profiles vp ON p.vendor_id = vp.id
    WHERE p.id = :id AND p.status = 'approved'
");
$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Plant listing not found or is currently archived.');
    redirect('shop.php');
}

// Fetch Product Images
$imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, sort_order ASC");
$imgStmt->execute(['id' => $productId]);
$images = $imgStmt->fetchAll();
$primaryImage = !empty($images) ? $images[0]['image_path'] : 'assets/images/plants/default-plant.jpg';

// Handle Verified Review Submission
$reviewError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    require_login();
    $rating = (int)($_POST['rating'] ?? 5);
    $reviewText = trim($_POST['review_text'] ?? '');
    $orderId = (int)($_POST['order_id'] ?? 0);
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $reviewError = 'Security token invalid.';
    } elseif ($rating < 1 || $rating > 5) {
        $reviewError = 'Please select a rating between 1 and 5 stars.';
    } elseif (empty($reviewText)) {
        $reviewError = 'Please write a brief review.';
    } else {
        // Enforce Purchase Verification Rule
        $verifyOrderStmt = $db->prepare("
            SELECT o.id 
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = :order_id 
              AND o.customer_id = :cust_id 
              AND oi.product_id = :prod_id 
              AND o.order_status = 'Delivered'
            LIMIT 1
        ");
        $verifyOrderStmt->execute([
            'order_id' => $orderId,
            'cust_id'  => $currentUser['id'],
            'prod_id'  => $productId
        ]);

        if (!$verifyOrderStmt->fetch()) {
            $reviewError = 'Verification failed: You can only review products that have been delivered to you.';
        } else {
            try {
                $insRev = $db->prepare("
                    INSERT INTO reviews (product_id, customer_id, order_id, rating, review_text, created_at)
                    VALUES (:pid, :cid, :oid, :rating, :text, NOW())
                ");
                $insRev->execute([
                    'pid'    => $productId,
                    'cid'    => $currentUser['id'],
                    'oid'    => $orderId,
                    'rating' => $rating,
                    'text'   => $reviewText
                ]);

                // Update Vendor Average Rating
                $avgStmt = $db->prepare("
                    SELECT AVG(r.rating) as avg_r, COUNT(r.id) as cnt_r
                    FROM reviews r
                    JOIN products p ON r.product_id = p.id
                    WHERE p.vendor_id = :vendor_id
                ");
                $avgStmt->execute(['vendor_id' => $product['vendor_profile_id']]);
                $revStats = $avgStmt->fetch();

                if ($revStats) {
                    $db->prepare("UPDATE vendor_profiles SET rating_avg = :avg, rating_count = :cnt WHERE id = :id")->execute([
                        'avg' => round((float)$revStats['avg_r'], 2),
                        'cnt' => (int)$revStats['cnt_r'],
                        'id'  => $product['vendor_profile_id']
                    ]);
                }

                set_flash('success', 'Thank you! Your purchase-verified review has been posted.');
                redirect('product.php?id=' . $productId);
            } catch (Exception $e) {
                $reviewError = 'You have already reviewed this product from that order.';
            }
        }
    }
}

// Fetch Reviews for this Product
$revStmt = $db->prepare("
    SELECT r.*, u.name AS customer_name, u.avatar AS customer_avatar
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    WHERE r.product_id = :pid
    ORDER BY r.id DESC
");
$revStmt->execute(['pid' => $productId]);
$reviews = $revStmt->fetchAll();
$avgRating = count($reviews) > 0 ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 5.0;

// Check if logged-in customer is eligible to review
$eligibleOrder = null;
if ($currentUser && $currentUser['role_name'] === 'customer') {
    $eligStmt = $db->prepare("
        SELECT o.id, o.order_number
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN reviews r ON (r.order_id = o.id AND r.product_id = oi.product_id AND r.customer_id = o.customer_id)
        WHERE o.customer_id = :cid 
          AND oi.product_id = :pid 
          AND o.order_status = 'Delivered' 
          AND r.id IS NULL
        LIMIT 1
    ");
    $eligStmt->execute(['cid' => $currentUser['id'], 'pid' => $productId]);
    $eligibleOrder = $eligStmt->fetch();
}

$pageTitle = product_display_name($product);
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="flex-1 w-full flex flex-col">
<!-- Breadcrumb -->
<div class="bg-slate-100/80 border-b border-slate-200 py-3 text-xs text-slate-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center space-x-2">
        <a href="index.php" class="hover:text-emerald-700"><?= __('nav.home') ?></a>
        <span>/</span>
        <a href="shop.php" class="hover:text-emerald-700"><?= __('shop.title') ?></a>
        <span>/</span>
        <a href="shop.php?category=<?= $product['category_id'] ?>" class="hover:text-emerald-700"><?= sanitize(category_display_name($product['category_name'])) ?></a>
        <span>/</span>
        <span class="text-slate-800 font-bold truncate max-w-xs"><?= sanitize(product_display_name($product)) ?></span>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <!-- Product Showcase -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        
        <!-- Left: Image Gallery -->
        <div class="lg:col-span-6 space-y-4">
            <div class="bg-white rounded-3xl p-4 border border-slate-200 shadow-sm overflow-hidden relative">
                <img id="main-product-image" src="<?= sanitize(get_image_url($primaryImage)) ?>" alt="<?= sanitize(product_display_name($product)) ?>" 
                     class="w-full h-96 sm:h-[450px] object-cover rounded-2xl"
                     onerror="this.onerror=null; this.src='<?= BASE_URL ?>/assets/images/plants/default-plant.jpg';">
                
                <div class="absolute top-7 left-7 flex flex-col space-y-1.5">
                    <?= render_care_badge($product['care_level']) ?>
                    <?php if ($product['is_pet_friendly']): ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-teal-100 text-teal-800 border border-teal-200 shadow-xs">
                            <i class="fa-solid fa-paw mr-1 text-[10px]"></i> <?= __('product.pet_safe') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thumbnail Selector (if multiple images) -->
            <?php if (count($images) > 1): ?>
                <div class="flex items-center space-x-3 overflow-x-auto pb-2">
                    <?php foreach ($images as $img): ?>
                        <button type="button" onclick="document.getElementById('main-product-image').src='<?= sanitize(get_image_url($img['image_path'])) ?>'"
                                class="w-16 h-16 rounded-xl overflow-hidden border-2 border-slate-200 hover:border-emerald-500 transition shrink-0">
                            <img src="<?= sanitize(get_image_url($img['image_path'])) ?>" class="w-full h-full object-cover"
                                 onerror="this.onerror=null; this.src='<?= BASE_URL ?>/assets/images/plants/default-plant.jpg';">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Botanical Specifications & Actions -->
        <div class="lg:col-span-6 space-y-6">
            
            <div>
                <div class="flex items-center space-x-2 text-xs text-emerald-700 font-semibold mb-1.5">
                    <span><?= sanitize(category_display_name($product['category_name'])) ?></span>
                    <span>•</span>
                    <span><?= sanitize(indoor_outdoor_i18n($product['indoor_outdoor'])) ?></span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
                    <?= sanitize(product_display_name($product)) ?>
                </h1>
                <?php if ($product['scientific_name']): ?>
                    <p class="text-sm text-slate-400 italic mt-0.5"><?= sanitize($product['scientific_name']) ?></p>
                <?php endif; ?>

                <!-- Rating and Review Summary -->
                <div class="flex items-center space-x-3 mt-3 text-xs">
                    <div class="flex items-center text-amber-400">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <i class="fa-solid fa-star <?= $s <= round($avgRating) ? 'text-amber-400' : 'text-slate-200' ?> text-xs"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="font-bold text-slate-700"><?= format_num_i18n($avgRating) ?> / <?= format_num_i18n('5.0') ?></span>
                    <span class="text-slate-400">•</span>
                    <a href="#reviews-section" class="text-emerald-700 hover:underline font-semibold"><?= format_num_i18n(count($reviews)) ?> <?= __('product.reviews') ?></a>
                </div>
            </div>

            <!-- Pricing Box -->
            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-500 block"><?= __('product.unit_price') ?></span>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-3xl font-black text-emerald-600">
                            <?= format_currency($product['discount_price'] ?: $product['price']) ?>
                        </span>
                        <?php if ($product['discount_price']): ?>
                            <span class="text-sm text-slate-400 line-through"><?= format_currency($product['price']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-right">
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                            <?= __('product.stock_left', null, [':count' => $product['stock_quantity']]) ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                            <?= __('product.out_of_stock') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Botanical Care Attributes Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block"><?= __('product.sunlight') ?></span>
                    <p class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                        <i class="fa-solid fa-sun text-amber-500"></i>
                        <span><?= sanitize(sunlight_i18n($product['sunlight_requirement'])) ?></span>
                    </p>
                </div>
                <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block"><?= __('product.water') ?></span>
                    <p class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                        <i class="fa-solid fa-droplet text-sky-500"></i>
                        <span><?= sanitize(water_i18n($product['water_frequency'])) ?></span>
                    </p>
                </div>
                <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block"><?= __('product.care_level') ?></span>
                    <p class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                        <i class="fa-solid fa-leaf text-emerald-500"></i>
                        <span><?= sanitize(is_bangla() ? ($product['care_level'] === 'Easy' ? 'সহজ পরিচর্যা' : ($product['care_level'] === 'Moderate' ? 'মাঝারি পরিচর্যা' : 'বিশেষ যত্ন')) : $product['care_level']) ?></span>
                    </p>
                </div>
            </div>

            <!-- Plant Description Text -->
            <div class="text-xs text-slate-600 leading-relaxed space-y-2 border-t border-b border-slate-100 py-4">
                <h4 class="font-bold text-slate-900 uppercase tracking-wider text-[11px]"><?= __('product.description') ?></h4>
                <p><?= nl2br(sanitize($product['description'])) ?></p>
            </div>

            <!-- Add to Cart / Buy Form -->
            <?php if ($product['stock_quantity'] > 0): ?>
                <div class="flex items-center space-x-4 pt-2">
                    <div class="flex items-center border border-slate-200 rounded-xl bg-white p-1">
                        <button type="button" onclick="adjustQty(-1)" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center font-bold text-slate-700 transition">-</button>
                        <input id="item-qty-input" type="number" value="1" min="1" max="<?= $product['stock_quantity'] ?>" 
                               class="w-12 text-center text-xs font-bold border-none focus:outline-none bg-transparent">
                        <button type="button" onclick="adjustQty(1)" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center font-bold text-slate-700 transition">+</button>
                    </div>

                    <button type="button" onclick="quickAddToCart(<?= $product['id'] ?>, parseInt(document.getElementById('item-qty-input').value))"
                            class="flex-1 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-900/30 transition transform hover:-translate-y-0.5 flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-cart-plus"></i>
                        <span><?= __('product.add_to_cart') ?></span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Nursery Vendor Card -->
            <div class="bg-emerald-50/70 border border-emerald-200/80 rounded-2xl p-4 flex flex-wrap sm:flex-nowrap items-center justify-between gap-3">
                <a href="nursery_detail.php?id=<?= $product['vendor_profile_id'] ?>" class="flex items-center space-x-3 group/vendor flex-1">
                    <div class="w-10 h-10 rounded-xl bg-emerald-700 text-white font-bold flex items-center justify-center text-sm group-hover/vendor:scale-105 transition">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <div>
                        <span class="text-[10px] text-emerald-800 font-bold uppercase tracking-wider block"><?= __('product.verified_nursery') ?></span>
                        <h4 class="text-xs font-bold text-slate-900 group-hover/vendor:text-emerald-700 transition flex items-center space-x-1">
                            <span><?= sanitize(vendor_display_name($product['nursery_name'])) ?></span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-emerald-600"></i>
                        </h4>
                        <span class="text-[10px] text-slate-500"><i class="fa-solid fa-location-dot text-emerald-600 mr-1"></i><?= sanitize(vendor_display_address($product['vendor_address'])) ?></span>
                    </div>
                </a>
                <div class="flex items-center space-x-2">
                    <a href="nursery_detail.php?id=<?= $product['vendor_profile_id'] ?>" 
                       class="px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition shadow-xs">
                        <span><?= is_bangla() ? 'স্টোরফ্রন্ট' : 'Storefront' ?></span>
                    </a>
                    <a href="customer/chat.php?vendor_id=<?= $product['vendor_profile_id'] ?>&product_id=<?= $product['id'] ?>" 
                       class="px-3 py-2 bg-white hover:bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold text-xs rounded-xl transition flex items-center space-x-1 shadow-xs">
                        <i class="fa-regular fa-comment-dots"></i>
                        <span><?= __('nav.vendor_chat') ?></span>
                    </a>
                </div>
            </div>

        </div>

    </div>

    <!-- Reviews Section -->
    <div id="reviews-section" class="mt-16 pt-10 border-t border-slate-200 space-y-8">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold text-emerald-600 uppercase tracking-widest block"><?= __('product.reviews') ?></span>
                <h2 class="text-2xl font-extrabold text-slate-900"><?= __('product.reviews') ?></h2>
            </div>

            <?php if ($eligibleOrder): ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                    <i class="fa-solid fa-circle-check mr-1.5 text-emerald-600"></i> <?= __('product.write_review') ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($reviewError): ?>
            <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs">
                <?= sanitize($reviewError) ?>
            </div>
        <?php endif; ?>

        <!-- Eligible Review Form for Verified Purchases -->
        <?php if ($eligibleOrder): ?>
            <div class="bg-white p-6 rounded-2xl border-2 border-emerald-500/40 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                    <i class="fa-solid fa-pen text-emerald-600"></i>
                    <span><?= __('product.write_review') ?> (<?= __('order.order_number') ?>: #<?= sanitize($eligibleOrder['order_number']) ?>)</span>
                </h3>

                <form action="product.php?id=<?= $product['id'] ?>" method="POST" class="space-y-4 text-xs">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="submit_review">
                    <input type="hidden" name="order_id" value="<?= $eligibleOrder['id'] ?>">

                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= __('product.rating') ?> (1 - 5)</label>
                        <select name="rating" class="w-full sm:w-48 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800">
                            <option value="5">⭐⭐⭐⭐⭐ 5</option>
                            <option value="4">⭐⭐⭐⭐ 4</option>
                            <option value="3">⭐⭐⭐ 3</option>
                            <option value="2">⭐⭐ 2</option>
                            <option value="1">⭐ 1</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= __('product.your_review') ?></label>
                        <textarea name="review_text" rows="3" required placeholder="<?= __('product.your_review') ?>..."
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                    </div>

                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition">
                        <?= __('product.submit_review') ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Reviews List -->
        <div class="space-y-4">
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $rev): ?>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-8 h-8 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-xs">
                                    <?= strtoupper(substr($rev['customer_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-800 block"><?= sanitize($rev['customer_name']) ?></span>
                                    <span class="text-[10px] text-emerald-600 font-semibold"><i class="fa-solid fa-circle-check mr-1"></i><?= __('common.approved') ?></span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <div class="flex text-amber-400 text-xs">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="fa-solid fa-star <?= $s <= $rev['rating'] ? 'text-amber-400' : 'text-slate-200' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-[10px] text-slate-400"><?= time_ago($rev['created_at']) ?></span>
                            </div>
                        </div>

                        <p class="text-xs text-slate-600 pl-10 leading-relaxed">
                            <?= nl2br(sanitize($rev['review_text'])) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 bg-slate-50 rounded-2xl text-center border border-slate-200 text-xs text-slate-400">
                    <?= __('product.no_reviews') ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
