<?php
// Vendor Product Catalog Management

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('vendor');
$currentUser = current_user();
$db = get_db();

// Fetch Vendor Profile
$vStmt = $db->prepare("SELECT id FROM vendor_profiles WHERE user_id = :uid");
$vStmt->execute(['uid' => $currentUser['id']]);
$vendor = $vStmt->fetch();
$vendorId = $vendor['id'];

// Handle Product Deletion / Archive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf) && $productId > 0) {
        $db->prepare("UPDATE products SET status = 'archived', updated_at = NOW() WHERE id = :id AND vendor_id = :vid")->execute(['id' => $productId, 'vid' => $vendorId]);
        log_audit('archive_product', 'product', $productId, "Vendor archived product ID {$productId}");
        set_flash('success', 'Plant listing archived.');
    }
    redirect('vendor/products.php');
}

// Fetch Vendor's Products
$stmt = $db->prepare("
    SELECT p.*, c.name AS category_name, c.name_bn AS category_name_bn,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.vendor_id = :vid AND p.status != 'archived'
    ORDER BY p.id DESC
");
$stmt->execute(['vid' => $vendorId]);
$products = $stmt->fetchAll();

$pageTitle = is_bangla() ? 'গাছপালার ক্যাটালগ পরিচালনা' : 'Manage Plant Catalog';
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
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'নার্সারি ইনভেন্টরি' : 'Nursery Inventory' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'আমার গাছের ক্যাটালগ' : 'My Plant Catalog' ?></h1>
            </div>

            <a href="product-add.php" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center space-x-2">
                <i class="fa-solid fa-wand-magic-sparkles text-emerald-200"></i>
                <span><?= is_bangla() ? 'নতুন গাছ যোগ করুন' : 'Add Plant Listing (AI Assistant)' ?></span>
            </a>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span><?= is_bangla() ? 'মোট সক্রিয় তালিকা' : 'Total Active Listings' ?>: <strong class="text-slate-800"><?= format_num_i18n(count($products)) ?></strong></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4"><?= is_bangla() ? 'গাছ ও বৈজ্ঞানিক নাম' : 'Plant & Scientific' ?></th>
                            <th class="p-4"><?= __('shop.category') ?></th>
                            <th class="p-4"><?= __('cart.price') ?></th>
                            <th class="p-4"><?= is_bangla() ? 'মজুদ স্টক' : 'Live Stock' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'অনুমোদন অবস্থা' : 'Approval Status' ?></th>
                            <th class="p-4 text-right"><?= is_bangla() ? 'অ্যাকশন' : 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td class="p-4">
                                        <div class="flex items-center space-x-3">
                                            <img src="<?= sanitize(get_image_url($p['primary_image'])) ?>" 
                                                 class="w-11 h-11 rounded-xl object-cover border border-slate-200 shrink-0">
                                            <div>
                                                <h4 class="font-bold text-slate-900 text-xs hover:text-emerald-600">
                                                    <a href="<?= BASE_URL ?>/product.php?id=<?= $p['id'] ?>" target="_blank"><?= sanitize(product_display_name($p)) ?></a>
                                                </h4>
                                                <span class="text-[11px] text-slate-400 italic"><?= sanitize($p['scientific_name'] ?: 'Botanical specimen') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4"><?= sanitize(category_display_name($p['category_name'])) ?></td>
                                    <td class="p-4 font-bold text-emerald-600"><?= format_currency($p['discount_price'] ?: $p['price']) ?></td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center px-2 py.0.5 rounded text-[11px] font-bold <?= $p['stock_quantity'] <= 5 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                            <?= format_num_i18n($p['stock_quantity']) ?> <?= is_bangla() ? 'টি' : 'units' ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <?php if ($p['status'] === 'approved'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800"><?= __('common.approved') ?></span>
                                        <?php elseif ($p['status'] === 'pending_approval'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800"><?= __('common.pending') ?></span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800"><?= sanitize($p['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-right space-x-2">
                                        <a href="product-edit.php?id=<?= $p['id'] ?>" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg">
                                            <?= __('common.edit') ?>
                                        </a>
                                        <form action="products.php" method="POST" onsubmit="return confirm('<?= is_bangla() ? 'এই গাছটি কি আর্কাইভ করতে চান?' : 'Archive this plant listing?' ?>');" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold rounded-lg">
                                                <?= is_bangla() ? 'আর্কাইভ' : 'Archive' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400">
                                    <?= is_bangla() ? 'কোন গাছ তালিকাভুক্ত করা হয়নি।' : 'No products found.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
