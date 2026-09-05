<?php
// Edit Plant Listing

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/uploads.php';

require_role('vendor');
$currentUser = current_user();
$db = get_db();

// Fetch Vendor Profile
$vStmt = $db->prepare("SELECT id FROM vendor_profiles WHERE user_id = :uid");
$vStmt->execute(['uid' => $currentUser['id']]);
$vendor = $vStmt->fetch();
$vendorId = $vendor['id'];

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    redirect('vendor/products.php');
}

// Fetch Product
$stmt = $db->prepare("SELECT * FROM products WHERE id = :id AND vendor_id = :vid");
$stmt->execute(['id' => $productId, 'vid' => $vendorId]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Plant listing not found.');
    redirect('vendor/products.php');
}

// Fetch Categories
$categories = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $nameBn = trim($_POST['name_bn'] ?? '');
    $scientific = trim($_POST['scientific_name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 1);
    $price = (float)($_POST['price'] ?? 0);
    $discountPrice = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $sunlight = trim($_POST['sunlight_requirement'] ?? 'Bright Indirect');
    $water = trim($_POST['water_frequency'] ?? 'Weekly');
    $care = trim($_POST['care_level'] ?? 'Easy');
    $indoorOutdoor = trim($_POST['indoor_outdoor'] ?? 'Indoor');
    $petFriendly = !empty($_POST['is_pet_friendly']) ? 1 : 0;
    $desc = trim($_POST['description'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = 'Security token invalid.';
    } elseif (empty($name) || $price <= 0 || empty($desc)) {
        $error = 'Please complete all required fields.';
    } else {
        try {
            $upd = $db->prepare("
                UPDATE products 
                SET category_id = :cid, name = :name, name_bn = :nameBn, scientific_name = :sci, description = :desc,
                    price = :price, discount_price = :dprice, sunlight_requirement = :sun,
                    water_frequency = :water, care_level = :care, indoor_outdoor = :io,
                    is_pet_friendly = :pet, updated_at = NOW()
                WHERE id = :id AND vendor_id = :vid
            ");
            $upd->execute([
                'cid'    => $categoryId,
                'name'   => $name,
                'nameBn' => $nameBn ?: null,
                'sci'    => $scientific,
                'desc'   => $desc,
                'price'  => $price,
                'dprice' => $discountPrice,
                'sun'    => $sunlight,
                'water'  => $water,
                'care'   => $care,
                'io'     => $indoorOutdoor,
                'pet'    => $petFriendly,
                'id'     => $productId,
                'vid'    => $vendorId
            ]);

            // Handle optional new image upload
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $uploadRes = handle_image_upload($_FILES['product_image'], 'plants');
                if ($uploadRes['success']) {
                    $newPath = $uploadRes['filepath'];
                    $chk = $db->prepare("SELECT id FROM product_images WHERE product_id = :pid AND is_primary = 1");
                    $chk->execute(['pid' => $productId]);
                    if ($chk->fetch()) {
                        $db->prepare("UPDATE product_images SET image_path = :path WHERE product_id = :pid AND is_primary = 1")->execute(['path' => $newPath, 'pid' => $productId]);
                    } else {
                        $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order, created_at) VALUES (:pid, :path, 1, 1, NOW())")->execute(['pid' => $productId, 'path' => $newPath]);
                    }
                }
            }

            log_audit('update_product', 'product', $productId, "Vendor updated '{$name}'");
            set_flash('success', "Plant '{$name}' has been updated.");
            redirect('vendor/products.php');
        } catch (Exception $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Plant Listing';
$activePage = 'products';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50">
        
        <div class="flex items-center justify-between border-b border-slate-200 pb-5">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Product Management</span>
                <h1 class="text-2xl font-extrabold text-slate-900">Edit Plant Listing</h1>
            </div>
            <a href="products.php" class="text-xs text-slate-500 hover:text-slate-800 flex items-center space-x-1">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Catalog</span>
            </a>
        </div>

        <?php if ($error): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs">
                <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs">
            <form action="product-edit.php?id=<?= $productId ?>" method="POST" enctype="multipart/form-data" class="space-y-6 text-xs">
                <?= csrf_field() ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Common Plant Name (English) *</label>
                        <input type="text" id="plant-name" name="name" required value="<?= sanitize($product['name']) ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">বাংলা নাম / Bangla Name <span class="font-normal text-slate-400">(ঐচ্ছিক)</span></label>
                        <input type="text" name="name_bn" placeholder="যেমন: মনস্টেরা ডেলিসিওসা"
                               value="<?= sanitize($product['name_bn'] ?? '') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                               style="font-family: 'Hind Siliguri', sans-serif;">
                        <p class="text-[11px] text-slate-400 mt-1">বাংলা ভাষায় গাছের নাম লিখুন। BN মোডে এই নামটি দেখানো হবে।</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Scientific / Botanical Name</label>
                        <input type="text" id="plant-scientific" name="scientific_name" value="<?= sanitize($product['scientific_name'] ?? '') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Botanical Category</label>
                        <select id="plant-category" name="category_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Price (৳) *</label>
                        <input type="number" step="0.01" name="price" required value="<?= $product['price'] ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Discount Price (৳ Optional)</label>
                        <input type="number" step="0.01" name="discount_price" value="<?= $product['discount_price'] ?? '' ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Sunlight Needs</label>
                        <select id="plant-sunlight" name="sunlight_requirement" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                            <option value="Bright Indirect" <?= $product['sunlight_requirement'] === 'Bright Indirect' ? 'selected' : '' ?>>Bright Indirect</option>
                            <option value="Low Light" <?= $product['sunlight_requirement'] === 'Low Light' ? 'selected' : '' ?>>Low Light</option>
                            <option value="Partial Shade" <?= $product['sunlight_requirement'] === 'Partial Shade' ? 'selected' : '' ?>>Partial Shade</option>
                            <option value="Full Sun" <?= $product['sunlight_requirement'] === 'Full Sun' ? 'selected' : '' ?>>Full Sun</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Care Difficulty</label>
                        <select id="plant-care" name="care_level" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                            <option value="Easy" <?= $product['care_level'] === 'Easy' ? 'selected' : '' ?>>Easy Care</option>
                            <option value="Moderate" <?= $product['care_level'] === 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                            <option value="Expert" <?= $product['care_level'] === 'Expert' ? 'selected' : '' ?>>Expert Level</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Watering Cadence</label>
                        <select name="water_frequency" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                            <option value="Weekly" <?= $product['water_frequency'] === 'Weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="Daily" <?= $product['water_frequency'] === 'Daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="Twice a week" <?= $product['water_frequency'] === 'Twice a week' ? 'selected' : '' ?>>Twice a week</option>
                            <option value="Bi-weekly" <?= $product['water_frequency'] === 'Bi-weekly' ? 'selected' : '' ?>>Bi-weekly</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-slate-700">Botanical Description *</label>
                        <button type="button" onclick="generateBotanicalDescription()" id="btn-botanical-gen"
                                class="px-3.5 py-1.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-[11px] rounded-xl shadow-xs transition flex items-center space-x-1.5">
                            <i class="fa-solid fa-file-lines text-emerald-200"></i>
                            <span>Auto-Fill Care Profile Template</span>
                        </button>
                    </div>
                    <textarea id="plant-description" name="description" rows="5" required 
                              class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 leading-relaxed"><?= sanitize($product['description']) ?></textarea>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Update Photo (Optional)</label>
                    <input type="file" name="product_image" accept="image/*" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-slate-700">
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center space-x-3">
                    <button type="submit" class="px-8 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold rounded-xl shadow-md transition">
                        Save Changes
                    </button>
                    <a href="products.php" class="px-5 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition">
                        Cancel
                    </a>
                </div>

            </form>
        </div>

    </main>

</div>

<script>
async function generateBotanicalDescription() {
    const name = document.getElementById('plant-name').value.trim();
    const sci = document.getElementById('plant-scientific').value.trim();
    const cat = document.getElementById('plant-category').options[document.getElementById('plant-category').selectedIndex].text;
    const sun = document.getElementById('plant-sunlight').value;
    const care = document.getElementById('plant-care').value;
    const textarea = document.getElementById('plant-description');

    try {
        const formData = new FormData();
        formData.append('action', 'botanical_description');
        formData.append('name', name);
        formData.append('scientific_name', sci);
        formData.append('category', cat);
        formData.append('sunlight', sun);
        formData.append('care_level', care);

        const res = await fetch('<?= BASE_URL ?>/ajax_handler.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.description) {
            textarea.value = data.description;
        }
    } catch (err) {
        alert('Could not generate description template.');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
