<?php
// Customer Purchase-Verified Reviews Manager

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('customer');
$currentUser = current_user();
$db = get_db();

// Fetch Customer's Submitted Reviews
$revStmt = $db->prepare("
    SELECT r.*, p.name AS product_name, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           o.order_number
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    JOIN orders o ON r.order_id = o.id
    WHERE r.customer_id = :cid
    ORDER BY r.id DESC
");
$revStmt->execute(['cid' => $currentUser['id']]);
$myReviews = $revStmt->fetchAll();

// Fetch Delivered Products Eligible for Review (Delivered and not reviewed yet)
$pendingRevStmt = $db->prepare("
    SELECT oi.product_id, oi.product_name, o.id AS order_id, o.order_number,
           (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN reviews r ON (r.order_id = o.id AND r.product_id = oi.product_id AND r.customer_id = o.customer_id)
    WHERE o.customer_id = :cid 
      AND o.order_status = 'Delivered'
      AND r.id IS NULL
    GROUP BY oi.product_id, o.id
");
$pendingRevStmt->execute(['cid' => $currentUser['id']]);
$eligibleReviews = $pendingRevStmt->fetchAll();

$pageTitle = 'My Verified Reviews';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    
    <div class="flex items-center justify-between border-b border-slate-200 pb-5">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Customer Space</span>
            <h1 class="text-2xl font-extrabold text-slate-900">Purchase-Verified Botanical Reviews</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <a href="index.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200">Order History</a>
            <a href="reviews.php" class="px-3.5 py-2 bg-emerald-600 text-white font-bold rounded-xl shadow-xs">Verified Reviews</a>
            <a href="chat.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200">Vendor Messages</a>
            <a href="profile.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200">Profile & Address</a>
        </div>
    </div>

    <!-- Eligible Items Awaiting Review -->
    <?php if (!empty($eligibleReviews)): ?>
        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 p-6 rounded-3xl border border-emerald-200 space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-900 flex items-center space-x-2">
                <i class="fa-solid fa-star text-amber-500"></i>
                <span>Delivered Plants Waiting for Your Feedback</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach ($eligibleReviews as $el): ?>
                    <div class="bg-white p-4 rounded-2xl border border-emerald-100 shadow-xs flex items-center justify-between gap-3 text-xs">
                        <div class="flex items-center space-x-3">
                            <img src="<?= sanitize(get_image_url($el['primary_image'])) ?>" 
                                 class="w-12 h-12 rounded-xl object-cover">
                            <div>
                                <h4 class="font-bold text-slate-900"><?= sanitize($el['product_name']) ?></h4>
                                <span class="text-[10px] text-slate-400 font-mono">Order #<?= sanitize($el['order_number']) ?></span>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>/product.php?id=<?= $el['product_id'] ?>#reviews-section" 
                           class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-xs transition shrink-0">
                            Write Review
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reviews History -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3">
            My Past Reviews (<?= count($myReviews) ?>)
        </h3>

        <?php if (!empty($myReviews)): ?>
            <div class="space-y-4">
                <?php foreach ($myReviews as $r): ?>
                    <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/50 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <img src="<?= sanitize(get_image_url($r['primary_image'])) ?>" 
                                     class="w-10 h-10 rounded-xl object-cover">
                                <div>
                                    <h4 class="font-bold text-slate-900 text-sm"><?= sanitize($r['product_name']) ?></h4>
                                    <span class="text-[10px] text-slate-400 font-mono">Order #<?= sanitize($r['order_number']) ?></span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <div class="flex text-amber-400">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="fa-solid fa-star <?= $s <= $r['rating'] ? 'text-amber-400' : 'text-slate-200' ?> text-xs"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-slate-400 text-[10px]"><?= time_ago($r['created_at']) ?></span>
                            </div>
                        </div>

                        <p class="text-slate-700 pl-13 leading-relaxed">
                            <?= nl2br(sanitize($r['review_text'])) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-8 text-center text-slate-400 text-xs">
                You have not submitted any reviews yet.
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
