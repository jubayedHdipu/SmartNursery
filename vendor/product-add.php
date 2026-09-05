<?php
// Add Plant Listing with Botanical Care Profile

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/uploads.php';
require_once __DIR__ . '/../helpers/inventory.php';

require_role('vendor');
$currentUser = current_user();
$db = get_db();

// Fetch Vendor Profile
$vStmt = $db->prepare("SELECT id FROM vendor_profiles WHERE user_id = :uid");
$vStmt->execute(['uid' => $currentUser['id']]);
$vendor = $vStmt->fetch();
$vendorId = $vendor['id'];

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
    $stock = max(0, (int)($_POST['stock_quantity'] ?? 10));
    $sunlight = trim($_POST['sunlight_requirement'] ?? 'Bright Indirect');
    $water = trim($_POST['water_frequency'] ?? 'Weekly');
    $care = trim($_POST['care_level'] ?? 'Easy');
    $indoorOutdoor = trim($_POST['indoor_outdoor'] ?? 'Indoor');
    $petFriendly = !empty($_POST['is_pet_friendly']) ? 1 : 0;
    $shortDesc = trim($_POST['short_description'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = 'Security session expired.';
    } elseif (empty($name) || $price <= 0 || empty($desc)) {
        $error = 'Please provide the plant name, valid price, and botanical description.';
    } else {
        try {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-')) . '-' . rand(100, 999);

            $db->beginTransaction();

            $insStmt = $db->prepare("
                INSERT INTO products 
                (vendor_id, category_id, name, name_bn, slug, scientific_name, short_description, description, price, discount_price, stock_quantity, sunlight_requirement, water_frequency, care_level, indoor_outdoor, is_pet_friendly, is_featured, status, created_at)
                VALUES 
                (:vid, :cid, :name, :nameBn, :slug, :sci, :sdesc, :desc, :price, :dprice, :stock, :sun, :water, :care, :io, :pet, 0, 'approved', NOW())
            ");
            $insStmt->execute([
                'vid'    => $vendorId,
                'cid'    => $categoryId,
                'name'   => $name,
                'nameBn' => $nameBn ?: null,
                'slug'   => $slug,
                'sci'    => $scientific,
                'sdesc'  => $shortDesc ?: mb_substr($desc, 0, 120),
                'desc'   => $desc,
                'price'  => $price,
                'dprice' => $discountPrice,
                'stock'  => $stock,
                'sun'    => $sunlight,
                'water'  => $water,
                'care'   => $care,
                'io'     => $indoorOutdoor,
                'pet'    => $petFriendly
            ]);

            $newProdId = (int)$db->lastInsertId();

            // Handle Image Upload or URL
            $imagePath = $imageUrl ?: 'assets/images/plants/default-plant.jpg';
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $uploadRes = handle_image_upload($_FILES['product_image'], 'plants');
                if ($uploadRes['success']) {
                    $imagePath = $uploadRes['filepath'];
                }
            }

            $imgStmt = $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order, created_at) VALUES (:pid, :path, 1, 1, NOW())");
            $imgStmt->execute(['pid' => $newProdId, 'path' => $imagePath]);

            // Initial Inventory Audit Log
            if ($stock > 0) {
                $logStmt = $db->prepare("
                    INSERT INTO inventory_transactions (product_id, vendor_id, change_type, quantity_change, previous_quantity, new_quantity, reason, reference_id, created_at)
                    VALUES (:pid, :vid, 'restock', :qty, 0, :new_qty, 'Initial Product Intake', 'INIT-RESTOCK', NOW())
                ");
                $logStmt->execute([
                    'pid'     => $newProdId,
                    'vid'     => $vendorId,
                    'qty'     => $stock,
                    'new_qty' => $stock
                ]);
            }

            $db->commit();
            log_audit('create_product', 'product', $newProdId, "Vendor listed '{$name}'");
            set_flash('success', "Plant '{$name}' has been added to your catalog!");
            redirect('vendor/products.php');
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = 'Failed to add plant: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Plant Listing';
$activePage = 'product-add';
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
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Plant Inventory</span>
                <h1 class="text-2xl font-extrabold text-slate-900">Add New Plant Listing</h1>
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
            <form action="product-add.php" method="POST" enctype="multipart/form-data" class="space-y-6 text-xs">
                <?= csrf_field() ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Common Plant Name (English) *</label>
                        <input type="text" id="plant-name" name="name" required placeholder="e.g. Monstera Deliciosa"
                               value="<?= sanitize($_POST['name'] ?? '') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">বাংলা নাম / Bangla Name <span class="font-normal text-slate-400">(ঐচ্ছিক)</span></label>
                        <input type="text" name="name_bn" placeholder="যেমন: মনস্টেরা ডেলিসিওসা"
                               value="<?= sanitize($_POST['name_bn'] ?? '') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                               style="font-family: 'Hind Siliguri', sans-serif;">
                        <p class="text-[11px] text-slate-400 mt-1">বাংলা ভাষায় গাছের নাম লিখুন। BN মোডে এই নামটি দেখানো হবে।</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Scientific / Botanical Name</label>
                        <input type="text" id="plant-scientific" name="scientific_name" placeholder="e.g. Monstera deliciosa"
                               value="<?= sanitize($_POST['scientific_name'] ?? '') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Botanical Category</label>
                        <select id="plant-category" name="category_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Price (৳) *</label>
                        <input type="number" step="0.01" name="price" required placeholder="750.00"
                               value="<?= sanitize($_POST['price'] ?? '') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Discount Price (৳ Optional)</label>
                        <input type="number" step="0.01" name="discount_price" placeholder="650.00"
                               value="<?= sanitize($_POST['discount_price'] ?? '') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Initial Stock (Units) *</label>
                        <input type="number" name="stock_quantity" value="<?= sanitize($_POST['stock_quantity'] ?? '15') ?>" min="0" required
                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Sunlight Needs</label>
                        <select id="plant-sunlight" name="sunlight_requirement" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                            <option value="Bright Indirect">Bright Indirect</option>
                            <option value="Low Light">Low Light</option>
                            <option value="Partial Shade">Partial Shade</option>
                            <option value="Full Sun">Full Sun</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Care Difficulty</label>
                        <select id="plant-care" name="care_level" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                            <option value="Easy">Easy Care</option>
                            <option value="Moderate">Moderate</option>
                            <option value="Expert">Expert Level</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Watering Cadence</label>
                        <select name="water_frequency" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                            <option value="Weekly">Weekly</option>
                            <option value="Daily">Daily</option>
                            <option value="Twice a week">Twice a week</option>
                            <option value="Bi-weekly">Bi-weekly (Fortnightly)</option>
                        </select>
                    </div>
                </div>

                <!-- Pet Safe & Indoor/Outdoor Flags -->
                <div class="flex flex-wrap items-center gap-6">
                    <label class="flex items-center space-x-2 text-slate-700 font-semibold cursor-pointer">
                        <input type="checkbox" name="is_pet_friendly" value="1" class="rounded text-emerald-600 focus:ring-emerald-500">
                        <span>🐾 100% Non-Toxic & Pet Safe</span>
                    </label>

                    <div class="flex items-center space-x-2">
                        <span class="font-bold text-slate-700">Environment:</span>
                        <select name="indoor_outdoor" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1 text-slate-800">
                            <option value="Indoor">Indoor Only</option>
                            <option value="Outdoor">Outdoor Only</option>
                            <option value="Both">Both Indoor & Outdoor</option>
                        </select>
                    </div>
                </div>

                <!-- Botanical Description Auto-Fill Callout -->
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
                              placeholder="Write or click the button above to auto-generate a sales-ready botanical care profile..."
                              class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 leading-relaxed"></textarea>
                    <p id="botanical-status-note" class="text-[11px] text-emerald-700 hidden"></p>
                </div>

                <!-- Product Image Upload or URL -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Upload Photo (JPG, PNG, WEBP)</label>
                        <input type="file" name="product_image" accept="image/*"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-slate-700">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Or Direct Image Path / URL</label>
                        <input type="text" name="image_url" placeholder="assets/images/plants/..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center space-x-3">
                    <button type="submit" class="px-8 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold rounded-xl shadow-md transition">
                        Publish Plant to Catalog
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
    if (!name) {
        alert('Please enter the Common Plant Name first to auto-fill the care profile!');
        document.getElementById('plant-name').focus();
        return;
    }

    const sci = document.getElementById('plant-scientific').value.trim();
    const cat = document.getElementById('plant-category').options[document.getElementById('plant-category').selectedIndex].text;
    const sun = document.getElementById('plant-sunlight').value;
    const care = document.getElementById('plant-care').value;
    const btn = document.getElementById('btn-botanical-gen');
    const note = document.getElementById('botanical-status-note');
    const textarea = document.getElementById('plant-description');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i><span>Generating Template...</span>';
    note.classList.remove('hidden');
    note.textContent = '🌿 Formatting botanical care profile...';

    try {
        const formData = new FormData();
        formData.append('action', 'botanical_description');
        formData.append('name', name);
        formData.append('scientific_name', sci);
        formData.append('category', cat);
        formData.append('sunlight', sun);
        formData.append('care_level', care);

        const res = await fetch('<?= BASE_URL ?>/ajax_handler.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        if (data.success && data.description) {
            textarea.value = data.description;
            note.textContent = '✅ Botanical profile generated! You can review or customize it above.';
        } else {
            alert(data.error || 'Failed to generate description.');
            note.classList.add('hidden');
        }
    } catch (err) {
        alert('Network error while generating profile.');
        note.classList.add('hidden');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-file-lines text-emerald-200"></i><span>Auto-Fill Care Profile Template</span>';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
