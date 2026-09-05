<?php
// Shopping Cart Management (Bilingual)

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$db = get_db();

// Handle Cart Updates via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($action === 'add' && $productId > 0) {
        $addQty = max(1, (int)($_POST['quantity'] ?? 1));
        $stmt = $db->prepare("
            SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
            FROM products p 
            WHERE p.id = :id AND p.status = 'approved'
        ");
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();

        if ($product) {
            $currentInCart = $_SESSION['cart'][$productId]['quantity'] ?? 0;
            $newTotal = $currentInCart + $addQty;

            if ($newTotal > (int)$product['stock_quantity']) {
                set_flash('error', __('product.stock_left', null, [':count' => $product['stock_quantity']]));
            } else {
                $price = !empty($product['discount_price']) ? (float)$product['discount_price'] : (float)$product['price'];
                $_SESSION['cart'][$productId] = [
                    'id'            => (int)$product['id'],
                    'name'          => $product['name'],
                    'name_bn'       => $product['name_bn'] ?? null,
                    'price'         => $price,
                    'quantity'      => $newTotal,
                    'image'         => get_image_url($product['primary_image']),
                    'vendor_id'     => (int)$product['vendor_id'],
                    'stock'         => (int)$product['stock_quantity']
                ];
                set_flash('success', is_bangla() ? 'গাছটি কার্টে যুক্ত করা হয়েছে!' : 'Plant added to shopping cart!');
            }
        } else {
            set_flash('error', 'Plant not found.');
        }
    } elseif ($action === 'update' && $productId > 0) {
        $newQty = max(1, (int)($_POST['quantity'] ?? 1));
        
        // Verify current live stock
        $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = :id AND status = 'approved'");
        $stmt->execute(['id' => $productId]);
        $stock = (int)$stmt->fetchColumn();

        if ($newQty > $stock) {
            set_flash('error', __('product.stock_left', null, [':count' => $stock]));
        } else {
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]['quantity'] = $newQty;
                set_flash('success', __('cart.cart_updated'));
            }
        }
    } elseif ($action === 'remove' && $productId > 0) {
        unset($_SESSION['cart'][$productId]);
        set_flash('info', __('cart.item_removed'));
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        set_flash('info', __('cart.item_removed'));
    }

    $redirectUrl = !empty($_POST['return_url']) ? $_POST['return_url'] : 'cart.php';
    redirect($redirectUrl);
}

// Calculate Totals
$cartItems = $_SESSION['cart'];
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$baseShipping = (float)get_setting('shipping_base_fee', '60.00');
$freeThreshold = (float)get_setting('free_shipping_threshold', '2000.00');
$shippingFee = ($subtotal >= $freeThreshold || empty($cartItems)) ? 0.00 : $baseShipping;
$grandTotal = $subtotal + $shippingFee;

$pageTitle = __('cart.title');
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="bg-slate-100 border-b border-slate-200 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?= __('cart.title') ?></h1>
        <p class="text-xs text-slate-500 mt-0.5"><?= __('cart.order_summary') ?></p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <?php if (!empty($cartItems)): ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Items Table -->
            <div class="lg:col-span-8 space-y-4">
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            <?= format_num_i18n(count($cartItems)) ?> <?= __('nav.cart') ?>
                        </span>
                        <form action="cart.php" method="POST" onsubmit="return confirm('Clear entire cart?');">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="text-xs text-rose-600 hover:underline font-semibold">
                                <?= __('common.delete') ?>
                            </button>
                        </form>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <?php foreach ($cartItems as $pid => $item): ?>
                            <?php $itemSubtotal = $item['price'] * $item['quantity']; ?>
                            <div class="p-6 flex flex-col sm:flex-row items-center sm:items-start space-y-4 sm:space-y-0 sm:space-x-5">
                                
                                <img src="<?= sanitize(get_image_url($item['image'])) ?>" alt="<?= sanitize(product_display_name($item)) ?>" 
                                     class="w-20 h-20 rounded-2xl object-cover border border-slate-200 shrink-0">

                                <div class="flex-1 space-y-1 text-center sm:text-left">
                                    <h3 class="font-bold text-slate-900 text-sm hover:text-emerald-600">
                                        <a href="product.php?id=<?= $pid ?>"><?= sanitize(product_display_name($item)) ?></a>
                                    </h3>
                                    <span class="text-xs font-bold text-emerald-600 block"><?= format_currency($item['price']) ?></span>
                                    <span class="text-[11px] text-slate-400"><?= __('product.stock_left', null, [':count' => $item['max_stock'] ?? 99]) ?></span>
                                </div>

                                <!-- Quantity Controls Form -->
                                <div class="flex items-center space-x-4">
                                    <form action="cart.php" method="POST" class="flex items-center border border-slate-200 rounded-xl bg-slate-50 p-1">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['max_stock'] ?? 99 ?>"
                                               onchange="this.form.submit()" 
                                               class="w-12 text-center text-xs font-bold bg-transparent border-none focus:outline-none">
                                    </form>

                                    <div class="text-right min-w-[80px]">
                                        <span class="font-bold text-slate-900 text-sm block"><?= format_currency($itemSubtotal) ?></span>
                                    </div>

                                    <form action="cart.php" method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                                        <button type="submit" class="p-2 text-slate-400 hover:text-rose-600 transition" title="<?= __('cart.remove') ?>">
                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                        </button>
                                    </form>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500 pt-2">
                    <a href="shop.php" class="text-emerald-700 hover:underline font-bold flex items-center space-x-1">
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        <span><?= __('cart.continue_shopping') ?></span>
                    </a>
                </div>
            </div>

            <!-- Order Summary Card -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">
                        <?= __('cart.order_summary') ?>
                    </h3>

                    <div class="space-y-3 text-xs text-slate-600">
                        <div class="flex justify-between">
                            <span><?= __('cart.items_subtotal') ?></span>
                            <span class="font-bold text-slate-900"><?= format_currency($subtotal) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span><?= __('cart.delivery_fee') ?></span>
                            <span class="font-bold <?= $shippingFee == 0 ? 'text-emerald-600' : 'text-slate-900' ?>">
                                <?= $shippingFee == 0 ? (is_bangla() ? 'ফ্রি' : 'FREE') : format_currency($shippingFee) ?>
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4 flex justify-between items-baseline">
                        <span class="text-sm font-bold text-slate-900"><?= __('cart.total') ?></span>
                        <span class="text-2xl font-black text-emerald-600"><?= format_currency($grandTotal) ?></span>
                    </div>

                    <a href="checkout.php" 
                       class="w-full py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-emerald-900/30 transition transform hover:-translate-y-0.5 flex items-center justify-center space-x-2 text-center">
                        <span><?= __('cart.proceed_checkout') ?></span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

        </div>
    <?php else: ?>
        <div class="bg-white rounded-3xl p-16 text-center border border-slate-200/80 shadow-sm max-w-lg mx-auto space-y-4">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-2xl">
                <i class="fa-solid fa-basket-shopping"></i>
            </div>
            <h2 class="text-xl font-bold text-slate-900"><?= __('cart.empty_title') ?></h2>
            <p class="text-xs text-slate-500 leading-relaxed"><?= __('cart.empty_desc') ?></p>
            <div class="pt-2 flex justify-center space-x-3">
                <a href="shop.php" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-xs transition">
                    <?= __('footer.all_plants') ?>
                </a>
                <a href="plant-matcher.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition">
                    <?= __('nav.ai_matcher') ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
