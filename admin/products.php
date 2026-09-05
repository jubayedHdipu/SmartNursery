<?php
// Admin Product Approval & Moderation Queue

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role(['admin', 'owner']);
$currentUser = current_user();
$db = get_db();

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $productId = (int)($_POST['product_id'] ?? 0);
    $status = $_POST['status'] ?? 'approved';
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf) && $productId > 0) {
        $db->prepare("UPDATE products SET status = :status, updated_at = NOW() WHERE id = :id")->execute(['status' => $status, 'id' => $productId]);
        log_audit('moderate_product', 'product', $productId, "Admin marked product as {$status}");
        set_flash('success', "Product status updated to {$status}.");
    }
    redirect('admin/products.php');
}

// Fetch All Products
$products = $db->query("
    SELECT p.*, p.name_bn, c.name AS category_name, c.name_bn AS category_name_bn, vp.nursery_name, vp.nursery_name_bn,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN vendor_profiles vp ON p.vendor_id = vp.id
    ORDER BY FIELD(p.status, 'pending_approval', 'approved', 'rejected', 'archived'), p.id DESC
")->fetchAll();

$pageTitle = is_bangla() ? 'গাছপালার অনুমোদন ও মডারেশন কিউ' : 'Product Moderation Queue';
$activePage = 'products';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'প্ল্যাটফর্ম ব্যবস্থাপনা' : 'Catalog Governance' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'গাছের তালিকা অনুমোদন' : 'Product Moderation Queue' ?></h1>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span><?= is_bangla() ? 'মোট গাছের তালিকা' : 'Total Cataloged Plants' ?>: <strong class="text-slate-800"><?= format_num_i18n(count($products)) ?></strong></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4"><?= is_bangla() ? 'গাছ ও ক্যাটাগরি' : 'Plant & Category' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'নার্সারি বিক্রেতা' : 'Nursery Vendor' ?></th>
                            <th class="p-4"><?= __('cart.price') ?></th>
                            <th class="p-4"><?= is_bangla() ? 'মজুদ স্টক' : 'Stock' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'অবস্থা' : 'Status' ?></th>
                            <th class="p-4 text-right"><?= is_bangla() ? 'মডারেশন' : 'Moderation' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td class="p-4">
                                    <div class="flex items-center space-x-3">
                                        <img src="<?= sanitize(get_image_url($p['primary_image'])) ?>" 
                                             class="w-10 h-10 rounded-xl object-cover border border-slate-200 shrink-0">
                                        <div>
                                            <a href="<?= BASE_URL ?>/product.php?id=<?= $p['id'] ?>" target="_blank" class="font-bold text-slate-900 hover:text-emerald-600 block"><?= sanitize(product_display_name($p)) ?></a>
                                            <span class="text-[11px] text-slate-400"><?= sanitize(category_display_name($p['category_name'])) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 font-semibold text-slate-800"><?= sanitize(vendor_display_name($p['nursery_name'])) ?></td>
                                <td class="p-4 font-bold text-emerald-600"><?= format_currency($p['price']) ?></td>
                                <td class="p-4"><?= format_num_i18n($p['stock_quantity']) ?> <?= is_bangla() ? 'টি' : 'units' ?></td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $p['status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($p['status'] === 'pending_approval' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') ?>">
                                        <?= $p['status'] === 'approved' ? __('common.approved') : ($p['status'] === 'pending_approval' ? __('common.pending') : sanitize($p['status'])) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right space-x-1.5">
                                    <?php if ($p['status'] !== 'approved'): ?>
                                        <form action="products.php" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg shadow-xs transition">
                                                <?= __('common.approve', 'অনুমোদন') ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="products.php" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold rounded-lg">
                                                <?= __('common.reject', 'বাতিল') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
