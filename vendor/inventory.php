<?php
// Vendor Inventory Tracking & Stock Adjustments

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/inventory.php';

require_role('vendor');
$currentUser = current_user();
$db = get_db();

// Fetch Vendor Profile
$vStmt = $db->prepare("SELECT id FROM vendor_profiles WHERE user_id = :uid");
$vStmt->execute(['uid' => $currentUser['id']]);
$vendor = $vStmt->fetch();
$vendorId = $vendor['id'];

// Handle Manual Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qtyChange = (int)($_POST['quantity_change'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Manual Nursery Adjustment');
    $changeType = trim($_POST['change_type'] ?? 'adjustment');
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf) && $productId > 0 && $qtyChange !== 0) {
        try {
            adjust_inventory(
                $productId,
                $qtyChange,
                $changeType,
                $reason,
                'ADJ-' . date('Ymd') . '-' . rand(100, 999)
            );
            log_audit('adjust_inventory', 'product', $productId, "Vendor adjusted stock by {$qtyChange}. Reason: {$reason}");
            set_flash('success', "Stock updated successfully.");
        } catch (Exception $e) {
            set_flash('error', $e->getMessage());
        }
    }
    redirect('vendor/inventory.php');
}

// Fetch Vendor's Products Stock
$stockStmt = $db->prepare("
    SELECT p.id, p.name, p.name_bn, p.scientific_name, p.price, p.stock_quantity, c.name AS category_name, c.name_bn AS category_name_bn
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.vendor_id = :vid AND p.status = 'approved'
    ORDER BY p.stock_quantity ASC
");
$stockStmt->execute(['vid' => $vendorId]);
$stockList = $stockStmt->fetchAll();

// Fetch Recent Inventory Transactions Log
$txnStmt = $db->prepare("
    SELECT it.*, p.name AS product_name, p.name_bn AS product_name_bn
    FROM inventory_transactions it
    JOIN products p ON it.product_id = p.id
    WHERE p.vendor_id = :vid
    ORDER BY it.id DESC
    LIMIT 20
");
$txnStmt->execute(['vid' => $vendorId]);
$recentTxns = $txnStmt->fetchAll();

$pageTitle = is_bangla() ? 'লাইভ স্টক ও ইনভেন্টরি ম্যানেজমেন্ট' : 'Live Stock & Inventory Management';
$activePage = 'inventory';
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
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'গুদাম ও ইনভেন্টরি' : 'Warehouse & Stock' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'লাইভ স্টক নিয়ন্ত্রণ' : 'Live Inventory Control' ?></h1>
            </div>
        </div>

        <!-- Plant Stock Balance Table -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2 border-b border-slate-100 pb-3">
                <i class="fa-solid fa-boxes-stacked text-emerald-600"></i>
                <span><?= is_bangla() ? 'গাছপালার বর্তমান স্টক ব্যালেন্স' : 'Live Plant Stock Balances' ?></span>
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="py-3"><?= is_bangla() ? 'গাছের নাম' : 'Plant Name' ?></th>
                            <th class="py-3"><?= __('shop.category') ?></th>
                            <th class="py-3"><?= is_bangla() ? 'বর্তমান মজুদ' : 'Current Balance' ?></th>
                            <th class="py-3 text-right"><?= is_bangla() ? 'দ্রুত স্টক পরিবর্তন' : 'Quick Stock Adjustment' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php foreach ($stockList as $s): ?>
                            <tr>
                                <td class="py-3">
                                    <span class="font-bold text-slate-900 block"><?= sanitize(product_display_name($s)) ?></span>
                                    <span class="text-[11px] text-slate-400 italic"><?= sanitize($s['scientific_name'] ?: '') ?></span>
                                </td>
                                <td class="py-3"><?= sanitize(category_display_name($s['category_name'])) ?></td>
                                <td class="py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= $s['stock_quantity'] <= 5 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                        <?= format_num_i18n($s['stock_quantity']) ?> <?= is_bangla() ? 'টি' : 'units' ?>
                                    </span>
                                </td>
                                <td class="py-3 text-right">
                                    <form action="inventory.php" method="POST" class="inline-flex items-center space-x-2 justify-end">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="adjust_stock">
                                        <input type="hidden" name="product_id" value="<?= $s['id'] ?>">
                                        
                                        <select name="change_type" class="bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-[11px]">
                                            <option value="restock"><?= is_bangla() ? '+ নতুন যোগ' : '+ Restock' ?></option>
                                            <option value="adjustment"><?= is_bangla() ? 'সমন্বয়' : 'Adjustment' ?></option>
                                            <option value="damaged"><?= is_bangla() ? '- ক্ষতি / বাদ' : '- Damaged / Loss' ?></option>
                                        </select>

                                        <input type="number" name="quantity_change" placeholder="± Qty" required 
                                               class="w-16 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-[11px] text-center font-bold">

                                        <input type="text" name="reason" placeholder="Reason (e.g. Greenhouse batch)" required 
                                               class="w-36 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-[11px]">

                                        <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg shadow-xs transition text-[11px]">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transaction Logs Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden space-y-4 p-6">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                <i class="fa-solid fa-clock-rotate-left text-emerald-600"></i>
                <span>Inventory Audit Transactions Log</span>
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="py-2.5">Date & Time</th>
                            <th class="py-2.5">Plant</th>
                            <th class="py-2.5">Change Type</th>
                            <th class="py-2.5 text-center">Change</th>
                            <th class="py-2.5 text-center">Balance (Old &rarr; New)</th>
                            <th class="py-2.5">Reason / Reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-600">
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td class="py-2.5 text-slate-400 font-mono text-[11px]"><?= format_date($t['created_at']) ?></td>
                                    <td class="py-2.5 font-bold text-slate-800"><?= sanitize($t['product_name']) ?></td>
                                    <td class="py-2.5">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $t['quantity_change'] > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                            <?= sanitize($t['change_type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-center font-bold <?= $t['quantity_change'] > 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                                        <?= $t['quantity_change'] > 0 ? '+' . $t['quantity_change'] : $t['quantity_change'] ?>
                                    </td>
                                    <td class="py-2.5 text-center text-slate-500 font-mono">
                                        <?= $t['previous_quantity'] ?> &rarr; <strong class="text-slate-800"><?= $t['new_quantity'] ?></strong>
                                    </td>
                                    <td class="py-2.5">
                                        <span class="text-slate-700"><?= sanitize($t['reason']) ?></span>
                                        <?php if ($t['reference_id']): ?>
                                            <span class="text-[10px] text-slate-400 font-mono block">Ref: <?= sanitize($t['reference_id']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400">No inventory transactions logged yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
