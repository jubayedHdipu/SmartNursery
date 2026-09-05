<?php
// Vendor Store Profile Settings & Media Management

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/uploads.php';

require_role('vendor');
$currentUser = current_user();
$db = get_db();

// Fetch Vendor Profile
$vStmt = $db->prepare("SELECT * FROM vendor_profiles WHERE user_id = :uid");
$vStmt->execute(['uid' => $currentUser['id']]);
$vendor = $vStmt->fetch();

// Count vendor's active listings
$plantCountStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = :vid AND status != 'archived'");
$plantCountStmt->execute(['vid' => $vendor['id']]);
$totalPlants = (int)$plantCountStmt->fetchColumn();

$error = '';
$pwdError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = is_bangla() ? 'নিরাপত্তা সেশন শেষ হয়েছে।' : 'Security session expired.';
    } elseif ($action === 'update_profile') {
        $nurseryName   = trim($_POST['nursery_name'] ?? '');
        $nurseryNameBn = trim($_POST['nursery_name_bn'] ?? '');
        $desc          = trim($_POST['description'] ?? '');
        $descBn        = trim($_POST['description_bn'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $addressBn     = trim($_POST['address_bn'] ?? '');
        $city          = trim($_POST['city'] ?? 'Dhaka');
        $ownerName     = trim($_POST['name'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $contactPhone  = trim($_POST['contact_phone'] ?? $phone);
        $openingHours  = trim($_POST['opening_hours'] ?? 'Sat - Fri: 8:30 AM - 8:30 PM');

        $logoPath   = $vendor['logo_image'];
        $bannerPath = $vendor['banner_image'];

        // Handle Shop Logo Upload
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $logoRes = handle_image_upload($_FILES['logo_file'], 'nurseries');
            if ($logoRes['success']) {
                $logoPath = $logoRes['filepath'];
            } else {
                $error = $logoRes['error'];
            }
        }

        // Handle Shop Banner Upload
        if (empty($error) && isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
            $bannerRes = handle_image_upload($_FILES['banner_file'], 'nurseries');
            if ($bannerRes['success']) {
                $bannerPath = $bannerRes['filepath'];
            } else {
                $error = $bannerRes['error'];
            }
        }

        if (empty($error)) {
            if (empty($nurseryName) || empty($address) || empty($ownerName)) {
                $error = is_bangla() ? 'নার্সারির নাম, ঠিকানা এবং স্বত্বাধিকারীর নাম আবশ্যক।' : 'Nursery name, address, and owner name are required.';
            } else {
                try {
                    $db->beginTransaction();

                    $db->prepare("UPDATE users SET name = :name, phone = :phone WHERE id = :id")->execute([
                        'name'  => $ownerName,
                        'phone' => $phone,
                        'id'    => $currentUser['id']
                    ]);

                    $db->prepare("
                        UPDATE vendor_profiles 
                        SET nursery_name = :nname, nursery_name_bn = :nname_bn,
                            description = :desc, description_bn = :desc_bn,
                            address = :addr, address_bn = :addr_bn, city = :city,
                            opening_hours = :hours, contact_phone = :cphone,
                            logo_image = :logo, banner_image = :banner
                        WHERE id = :id
                    ")->execute([
                        'nname'    => $nurseryName,
                        'nname_bn' => $nurseryNameBn,
                        'desc'     => $desc,
                        'desc_bn'  => $descBn,
                        'addr'     => $address,
                        'addr_bn'  => $addressBn,
                        'city'     => $city,
                        'hours'    => $openingHours,
                        'cphone'   => $contactPhone,
                        'logo'     => $logoPath,
                        'banner'   => $bannerPath,
                        'id'       => $vendor['id']
                    ]);

                    $db->commit();
                    log_audit('update_vendor_profile', 'vendor', $vendor['id'], "Vendor updated store profile '{$nurseryName}'");
                    set_flash('success', is_bangla() ? 'নার্সারির লোগো, ব্যানার ও স্টোরফ্রন্ট তথ্য সফলভাবে আপডেট হয়েছে!' : 'Nursery store logo, banner, and profile updated successfully!');
                    redirect('vendor/profile.php');
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $error = 'Update failed: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'change_password') {
        $curPass  = $_POST['current_password'] ?? '';
        $newPass  = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        if (empty($curPass) || empty($newPass)) {
            $pwdError = is_bangla() ? 'বর্তমান এবং নতুন পাসওয়ার্ড প্রদান করুন।' : 'Current and new password are required.';
        } elseif ($newPass !== $confPass) {
            $pwdError = is_bangla() ? 'নতুন পাসওয়ার্ড দুটি মেলেনি।' : 'New passwords do not match.';
        } elseif (strlen($newPass) < 6) {
            $pwdError = is_bangla() ? 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে باشد।' : 'Password must be at least 6 characters.';
        } else {
            $uStmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
            $uStmt->execute(['id' => $currentUser['id']]);
            $uRow = $uStmt->fetch();

            if (!password_verify($curPass, $uRow['password_hash'])) {
                $pwdError = is_bangla() ? 'বর্তমান পাসওয়ার্ডটি সঠিক নয়।' : 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password_hash = :p WHERE id = :id")->execute([
                    'p'  => $newHash,
                    'id' => $currentUser['id']
                ]);
                log_audit('change_password', 'user', $currentUser['id'], 'Vendor updated account password');
                set_flash('success', is_bangla() ? 'পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে।' : 'Password changed successfully.');
                redirect('vendor/profile.php');
            }
        }
    }
}

$pageTitle = is_bangla() ? 'নার্সারি স্টোরফ্রন্ট সেটিংস' : 'Storefront Profile Settings';
$activePage = 'profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50">
        
        <div class="border-b border-slate-200 pb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'স্টোরফ্রন্ট ব্র্যান্ডিং ও ব্যবস্থাপনা' : 'Storefront Branding & Settings' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'আমার নার্সারি প্রোফাইল' : 'Nursery Store Profile' ?></h1>
            </div>
            <div class="flex items-center space-x-2">
                <a href="<?= BASE_URL ?>/nursery_detail.php?id=<?= $vendor['id'] ?>" target="_blank" class="px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-xs rounded-xl border border-emerald-200 flex items-center space-x-1.5 transition">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    <span><?= is_bangla() ? 'পাবলিক পেজ দেখুন' : 'View Public Store' ?></span>
                </a>
                <span class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-bold <?= $vendor['verification_status'] === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                    <i class="fa-solid <?= $vendor['verification_status'] === 'verified' ? 'fa-circle-check text-emerald-600' : 'fa-hourglass-half text-amber-600' ?> mr-1.5"></i>
                    <?= $vendor['verification_status'] === 'verified' ? (is_bangla() ? 'ভেরিফাইড পার্টনার' : 'Verified Partner') : (is_bangla() ? 'ভেরিফিকেশন অপেক্ষমাণ' : 'Pending Verification') ?>
                </span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold">
                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i><?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($pwdError): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold">
                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i><?= sanitize($pwdError) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Left 8 Cols: Full Profile Edit Form -->
            <div class="lg:col-span-8 space-y-6">
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                            <i class="fa-solid fa-store text-emerald-600"></i>
                            <span><?= is_bangla() ? 'নার্সারির তথ্য, লোগো ও কভার ব্যানার' : 'Store Information, Logo & Cover Banner' ?></span>
                        </h3>
                    </div>

                    <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-5 text-xs">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <!-- Branding Media Upload Section -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-slate-50/90 rounded-2xl border border-slate-200">
                            <!-- Shop Logo Upload -->
                            <div class="space-y-1.5">
                                <label class="block font-bold text-slate-800">
                                    <i class="fa-solid fa-image text-emerald-600 mr-1"></i>
                                    <?= is_bangla() ? 'নার্সারি লোগো (Shop Logo)' : 'Nursery Logo' ?>
                                </label>
                                <div class="flex items-center space-x-3">
                                    <img id="logo-preview-img" 
                                         src="<?= sanitize(get_image_url($vendor['logo_image'])) ?>" 
                                         alt="Nursery Logo" 
                                         class="w-12 h-12 rounded-xl object-cover border-2 border-emerald-500/30 shadow-xs shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <input type="file" name="logo_file" id="input_logo_file" accept="image/*" 
                                               class="w-full text-[11px] text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                                        <p class="text-[10px] text-slate-400 mt-0.5"><?= is_bangla() ? 'JPG, PNG, WEBP (সর্বোচ্চ ৩MB)' : 'JPG, PNG, WEBP (Max 3MB)' ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Shop Banner Upload -->
                            <div class="space-y-1.5">
                                <label class="block font-bold text-slate-800">
                                    <i class="fa-solid fa-panorama text-emerald-600 mr-1"></i>
                                    <?= is_bangla() ? 'কভার ব্যানার (Store Banner)' : 'Cover Banner' ?>
                                </label>
                                <div class="space-y-1.5">
                                    <div class="h-12 w-full rounded-xl overflow-hidden bg-slate-200 border border-slate-300">
                                        <img id="banner-preview-img" 
                                             src="<?= sanitize(get_image_url($vendor['banner_image'])) ?>" 
                                             alt="Nursery Banner" 
                                             class="w-full h-full object-cover">
                                    </div>
                                    <input type="file" name="banner_file" id="input_banner_file" accept="image/*" 
                                           class="w-full text-[11px] text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                                </div>
                            </div>
                        </div>

                        <!-- Name inputs -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নার্সারি ব্যবসার নাম (English)' : 'Nursery Business Name (English)' ?> *</label>
                                <input type="text" name="nursery_name" id="input_nursery_name" required value="<?= sanitize($vendor['nursery_name']) ?>"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নার্সারি ব্যবসার নাম (বাংলা)' : 'Nursery Business Name (Bangla)' ?></label>
                                <input type="text" name="nursery_name_bn" id="input_nursery_name_bn" value="<?= sanitize($vendor['nursery_name_bn'] ?? '') ?>" placeholder="যেমন: গ্রিন হেভেন নার্সারি"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>

                        <!-- Owner & Hotline Phone -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'স্বত্বাধিকারীর নাম' : 'Owner Contact Name' ?> *</label>
                                <input type="text" name="name" id="input_owner_name" required value="<?= sanitize($currentUser['name']) ?>"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'হটলাইন ফোন' : 'Public Hotline / Phone' ?> *</label>
                                <input type="text" name="phone" id="input_phone" required value="<?= sanitize($vendor['contact_phone'] ?: $currentUser['phone']) ?>" placeholder="+880 1700-000000"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>

                        <!-- Opening Hours & City -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'খোলা থাকার সময়' : 'Greenhouse Opening Hours' ?></label>
                                <input type="text" name="opening_hours" id="input_opening_hours" value="<?= sanitize($vendor['opening_hours'] ?? 'Sat - Fri: 8:30 AM - 8:30 PM') ?>" placeholder="Sat - Fri: 8:30 AM - 8:30 PM"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'শহর' : 'City' ?> *</label>
                                <select name="city" id="input_city" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                                    <option value="Dhaka" <?= ($vendor['city'] ?? '') === 'Dhaka' ? 'selected' : '' ?>>Dhaka (ঢাকা)</option>
                                    <option value="Chittagong" <?= ($vendor['city'] ?? '') === 'Chittagong' ? 'selected' : '' ?>>Chittagong (চট্টগ্রাম)</option>
                                    <option value="Sylhet" <?= ($vendor['city'] ?? '') === 'Sylhet' ? 'selected' : '' ?>>Sylhet (সিলেট)</option>
                                    <option value="Rajshahi" <?= ($vendor['city'] ?? '') === 'Rajshahi' ? 'selected' : '' ?>>Rajshahi (রাজশাহী)</option>
                                    <option value="Khulna" <?= ($vendor['city'] ?? '') === 'Khulna' ? 'selected' : '' ?>>Khulna (খুলনা)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Location Address -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ঠিকানা (English)' : 'Physical Address (EN)' ?> *</label>
                                <input type="text" name="address" id="input_address" required value="<?= sanitize($vendor['address']) ?>"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ঠিকানা (বাংলা)' : 'Physical Address (Bangla)' ?></label>
                                <input type="text" name="address_bn" id="input_address_bn" value="<?= sanitize($vendor['address_bn'] ?? '') ?>" placeholder="যেমন: প্লট ১৪, রোড ৭, ধানমন্ডি"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>

                        <!-- Description Bio -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'বিবরণ (English)' : 'Nursery Bio (EN)' ?></label>
                                <textarea name="description" id="input_description" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"><?= sanitize($vendor['description']) ?></textarea>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'বিবরণ (বাংলা)' : 'Nursery Bio (Bangla)' ?></label>
                                <textarea name="description_bn" id="input_description_bn" rows="2" placeholder="নার্সারির বিশেষত্ব..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"><?= sanitize($vendor['description_bn'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <button type="submit" class="px-5 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-sm transition flex items-center space-x-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span><?= is_bangla() ? 'তথ্য সংরক্ষণ করুন' : 'Save Details' ?></span>
                        </button>
                    </form>
                </div>

                <!-- Quick Password Change Card -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3 flex items-center space-x-2">
                        <i class="fa-solid fa-lock text-emerald-600"></i>
                        <span><?= is_bangla() ? 'নিরাপত্তা ও পাসওয়ার্ড পরিবর্তন' : 'Account Security & Password Change' ?></span>
                    </h3>

                    <form action="profile.php" method="POST" class="space-y-4 text-xs">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'বর্তমান পাসওয়ার্ড' : 'Current Password' ?> *</label>
                                <input type="password" name="current_password" required placeholder="••••••••"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নতুন পাসওয়ার্ড' : 'New Password' ?> *</label>
                                <input type="password" name="new_password" required placeholder="••••••••"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নিশ্চিতকরণ' : 'Confirm Password' ?> *</label>
                                <input type="password" name="confirm_password" required placeholder="••••••••"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>

                        <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-xs transition">
                            <?= is_bangla() ? 'পাসওয়ার্ড পরিবর্তন' : 'Update Password' ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right 4 Cols: Compact Standard Storefront Preview -->
            <div class="lg:col-span-4 space-y-4 sticky top-24">
                
                <!-- Live Nursery Card (Standard Compact Dimensions) -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-lg overflow-hidden flex flex-col transition">
                    <!-- Banner Header -->
                    <div class="h-32 w-full bg-slate-900 relative overflow-hidden">
                        <img id="live-preview-banner" 
                             src="<?= sanitize(get_image_url($vendor['banner_image'])) ?>" 
                             alt="Nursery Banner Preview" 
                             class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-black/20"></div>
                        <span class="absolute top-3 right-3 text-[10px] font-bold bg-white/95 text-slate-800 px-2.5 py-1 rounded-full shadow-xs backdrop-blur-xs">
                            <i class="fa-solid fa-clock mr-1 text-emerald-600"></i><span id="live-preview-hours"><?= sanitize($vendor['opening_hours']) ?></span>
                        </span>
                        <div class="absolute top-3 left-3">
                            <span class="text-[10px] font-bold bg-emerald-600 text-white px-2.5 py-1 rounded-full shadow-xs flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                <?= is_bangla() ? 'লাইভ প্রিভিউ' : 'Live Storefront' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Profile Info Body -->
                    <div class="p-5 pt-0 relative space-y-3">
                        
                        <!-- Floating Logo & Rating Row -->
                        <div class="flex items-end justify-between relative z-10 -mt-8 mb-1">
                            <img id="live-preview-logo" 
                                 src="<?= sanitize(get_image_url($vendor['logo_image'])) ?>" 
                                 alt="Nursery Logo Preview" 
                                 class="w-16 h-16 rounded-2xl object-cover border-4 border-white shadow-xl bg-white shrink-0 relative z-20">
                            <span class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-xl border border-amber-200/80 shadow-2xs flex items-center gap-1.5">
                                <i class="fa-solid fa-star text-[11px] text-amber-500"></i>
                                <span><?= format_num_i18n($vendor['rating_avg']) ?> (<?= format_num_i18n($vendor['rating_count']) ?>)</span>
                            </span>
                        </div>

                        <!-- Nursery Name & Location (Clean Non-Overlapping Spacing) -->
                        <div class="pt-1">
                            <h4 id="live-preview-name" class="font-extrabold text-base text-slate-900 leading-snug">
                                <?= sanitize(vendor_display_name($vendor)) ?>
                            </h4>
                            <p id="live-preview-address" class="text-xs text-emerald-700 font-semibold flex items-center gap-1.5 mt-1">
                                <i class="fa-solid fa-location-dot text-[11px] shrink-0"></i>
                                <span class="truncate"><?= sanitize(vendor_display_address($vendor['address'])) ?></span>
                            </p>
                        </div>

                        <!-- Bio Story -->
                        <p id="live-preview-desc" class="text-xs text-slate-600 line-clamp-3 leading-relaxed bg-slate-50 p-3 rounded-2xl border border-slate-100">
                            <?= sanitize(is_bangla() && !empty($vendor['description_bn']) ? $vendor['description_bn'] : $vendor['description']) ?>
                        </p>

                        <!-- Compact Stats Row -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                            <span class="text-emerald-700 font-bold flex items-center gap-1.5">
                                <i class="fa-solid fa-seedling text-[13px]"></i>
                                <span><?= format_num_i18n($totalPlants) ?> <?= is_bangla() ? 'টি গাছ' : 'plants listed' ?></span>
                            </span>
                            <span class="font-mono text-slate-600 text-[11px] flex items-center gap-1.5 bg-slate-100/70 px-2.5 py-1 rounded-lg" id="live-preview-phone">
                                <i class="fa-solid fa-phone text-[10px] text-emerald-600"></i>
                                <span><?= sanitize($vendor['contact_phone'] ?: $currentUser['phone']) ?></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Small Info Summary Pill -->
                <div class="p-3.5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs flex items-center justify-between text-xs text-slate-600">
                    <span class="text-slate-400 text-[11px]"><?= is_bangla() ? 'সদস্য হয়েছেন' : 'Member Since' ?>: <strong class="text-slate-700"><?= format_date($vendor['created_at']) ?></strong></span>
                    <span class="text-[11px] bg-slate-100 px-2 py-0.5 rounded-md font-semibold text-slate-700" id="summary-city"><?= sanitize($vendor['city'] ?? 'Dhaka') ?></span>
                </div>

            </div>

        </div>

    </main>

</div>

<script>
// Instant Real-Time Two-Way Live Preview Synchronization
document.addEventListener('DOMContentLoaded', () => {
    const isBn = <?= is_bangla() ? 'true' : 'false' ?>;

    const inputName = document.getElementById('input_nursery_name');
    const inputNameBn = document.getElementById('input_nursery_name_bn');
    const inputAddress = document.getElementById('input_address');
    const inputAddressBn = document.getElementById('input_address_bn');
    const inputCity = document.getElementById('input_city');
    const inputPhone = document.getElementById('input_phone');
    const inputHours = document.getElementById('input_opening_hours');
    const inputDesc = document.getElementById('input_description');
    const inputDescBn = document.getElementById('input_description_bn');

    const liveName = document.getElementById('live-preview-name');
    const liveAddress = document.getElementById('live-preview-address');
    const liveHours = document.getElementById('live-preview-hours');
    const livePhone = document.getElementById('live-preview-phone');
    const liveDesc = document.getElementById('live-preview-desc');
    const summaryCity = document.getElementById('summary-city');

    // Update Storefront Name
    const syncName = () => {
        if (isBn && inputNameBn && inputNameBn.value.trim()) {
            liveName.textContent = inputNameBn.value.trim();
        } else if (inputName && inputName.value.trim()) {
            liveName.textContent = inputName.value.trim();
        }
    };
    if (inputName) inputName.addEventListener('input', syncName);
    if (inputNameBn) inputNameBn.addEventListener('input', syncName);

    // Update Address & City
    const syncAddress = () => {
        const addr = (isBn && inputAddressBn && inputAddressBn.value.trim()) ? inputAddressBn.value.trim() : (inputAddress?.value.trim() || '');
        const city = inputCity?.value || 'Dhaka';
        if (liveAddress) {
            liveAddress.innerHTML = `<i class="fa-solid fa-location-dot text-[11px] shrink-0"></i><span class="truncate">${addr || city}</span>`;
        }
        if (summaryCity) summaryCity.textContent = city;
    };
    if (inputAddress) inputAddress.addEventListener('input', syncAddress);
    if (inputAddressBn) inputAddressBn.addEventListener('input', syncAddress);
    if (inputCity) inputCity.addEventListener('change', syncAddress);

    // Update Phone
    if (inputPhone && livePhone) {
        inputPhone.addEventListener('input', () => {
            livePhone.textContent = inputPhone.value.trim() || '—';
        });
    }

    // Update Opening Hours
    if (inputHours && liveHours) {
        inputHours.addEventListener('input', () => {
            liveHours.textContent = inputHours.value.trim() || '8:30 AM - 8:30 PM';
        });
    }

    // Update Description
    const syncDesc = () => {
        const d = (isBn && inputDescBn && inputDescBn.value.trim()) ? inputDescBn.value.trim() : (inputDesc?.value.trim() || 'No description provided.');
        if (liveDesc) liveDesc.textContent = d;
    };
    if (inputDesc) inputDesc.addEventListener('input', syncDesc);
    if (inputDescBn) inputDescBn.addEventListener('input', syncDesc);

    // Image Upload Previews
    const setupImgPreview = (inputId, targetIds) => {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    targetIds.forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.src = e.target.result;
                    });
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    };

    setupImgPreview('input_logo_file', ['logo-preview-img', 'live-preview-logo']);
    setupImgPreview('input_banner_file', ['banner-preview-img', 'live-preview-banner']);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
