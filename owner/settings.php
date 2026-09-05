<?php
// Owner Global System & AI Settings (Dynamic)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('owner');
$currentUser = current_user();
$db = get_db();

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? 'save_settings';

    if (verify_csrf_token($csrf)) {
        if ($action === 'change_password') {
            $curPass  = $_POST['current_password'] ?? '';
            $newPass  = $_POST['new_password'] ?? '';
            $confPass = $_POST['confirm_password'] ?? '';

            if (empty($curPass) || empty($newPass)) {
                set_flash('error', is_bangla() ? 'বর্তমান এবং নতুন পাসওয়ার্ড প্রদান করুন।' : 'Current and new password are required.');
            } elseif ($newPass !== $confPass) {
                set_flash('error', is_bangla() ? 'নতুন পাসওয়ার্ড দুটি মেলেনি।' : 'New passwords do not match.');
            } elseif (strlen($newPass) < 6) {
                set_flash('error', is_bangla() ? 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters.');
            } else {
                $uStmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
                $uStmt->execute(['id' => $currentUser['id']]);
                $uRow = $uStmt->fetch();

                if (!password_verify($curPass, $uRow['password_hash'])) {
                    set_flash('error', is_bangla() ? 'বর্তমান পাসওয়ার্ডটি সঠিক নয়।' : 'Current password is incorrect.');
                } else {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $db->prepare("UPDATE users SET password_hash = :p WHERE id = :id")->execute([
                        'p'  => $newHash,
                        'id' => $currentUser['id']
                    ]);
                    log_audit('change_password', 'user', $currentUser['id'], 'Owner updated account password');
                    set_flash('success', is_bangla() ? 'পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে।' : 'Password changed successfully.');
                    redirect('owner/settings.php');
                }
            }
        } else {
            $ownerName = trim($_POST['site_owner_name'] ?? 'MR Dipu');

            // Handle Image Logo upload if provided
            $siteLogoImage = trim($_POST['site_logo_image'] ?? '');
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                $fileInfo = pathinfo($_FILES['logo_file']['name']);
                $ext = strtolower($fileInfo['extension'] ?? '');
                if (in_array($ext, $allowedExts)) {
                    $destDir = __DIR__ . '/../assets/images/branding/';
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0777, true);
                    }
                    $fileName = 'logo_' . time() . '.' . $ext;
                    $destPath = $destDir . $fileName;
                    if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $destPath)) {
                        $siteLogoImage = 'assets/images/branding/' . $fileName;
                    }
                }
            }

            // Handle Owner Avatar upload if provided
            $ownerAvatar = $currentUser['avatar'];
            if (isset($_FILES['owner_avatar_file']) && $_FILES['owner_avatar_file']['error'] === UPLOAD_ERR_OK) {
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                $fileInfo = pathinfo($_FILES['owner_avatar_file']['name']);
                $ext = strtolower($fileInfo['extension'] ?? '');
                if (in_array($ext, $allowedExts)) {
                    $destDir = __DIR__ . '/../assets/images/avatars/';
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0777, true);
                    }
                    $fileName = 'owner_' . time() . '.' . $ext;
                    $destPath = $destDir . $fileName;
                    if (move_uploaded_file($_FILES['owner_avatar_file']['tmp_name'], $destPath)) {
                        $ownerAvatar = 'assets/images/avatars/' . $fileName;
                    }
                }
            }

            $settingsToUpdate = [
                'site_name'                 => trim($_POST['site_name'] ?? 'Smart Nursery Ecosystem'),
                'site_name_bn'              => trim($_POST['site_name_bn'] ?? 'স্মার্ট নার্সারি ইকোসিস্টেম'),
                'site_brand_title'          => trim($_POST['site_brand_title'] ?? 'Smart'),
                'site_brand_title_bn'       => trim($_POST['site_brand_title_bn'] ?? 'স্মার্ট'),
                'site_brand_subtitle'       => trim($_POST['site_brand_subtitle'] ?? 'Nursery'),
                'site_brand_subtitle_bn'    => trim($_POST['site_brand_subtitle_bn'] ?? 'নার্সারি'),
                'site_tagline'              => trim($_POST['site_tagline'] ?? 'Curated Botanical Wonders & Smart Plant Care'),
                'site_tagline_bn'           => trim($_POST['site_tagline_bn'] ?? 'সবুজ নিকেতন ও আধুনিক বৃক্ষ পরিচর্যা'),
                'top_announcement_en'       => trim($_POST['top_announcement_en'] ?? '🌿 Smart Botanical Ecosystem • Smart Plant Matcher • Verified Local Specialty Nurseries'),
                'top_announcement_bn'       => trim($_POST['top_announcement_bn'] ?? '🌿 স্মার্ট বোটানিক্যাল ইকোসিস্টেম • স্মার্ট উদ্ভিদ ম্যাচিং • ভেরিফাইড স্থানীয় বিশেষায়িত নার্সারি'),
                'site_logo_type'            => trim($_POST['site_logo_type'] ?? 'icon'),
                'site_logo_icon'            => trim($_POST['site_logo_icon'] ?? 'fa-leaf'),
                'site_logo_image'           => $siteLogoImage,
                'site_owner_name'           => $ownerName,
                'contact_email'             => trim($_POST['contact_email'] ?? 'support@smartnursery.com'),
                'contact_phone'             => trim($_POST['contact_phone'] ?? '+880 1800-NURSERY'),
                'contact_address'           => trim($_POST['contact_address'] ?? 'Dhanmondi, Dhaka, Bangladesh'),
                'contact_address_bn'        => trim($_POST['contact_address_bn'] ?? 'ধানমন্ডি, ঢাকা, বাংলাদেশ'),
                'currency_symbol'           => trim($_POST['currency_symbol'] ?? '৳'),
                'currency_code'             => trim($_POST['currency_code'] ?? 'BDT'),
                'shipping_base_fee'         => (string)max(0, (float)($_POST['shipping_base_fee'] ?? 60)),
                'free_shipping_threshold'   => (string)max(0, (float)($_POST['free_shipping_threshold'] ?? 2000)),
                'platform_commission_rate'  => (string)max(0, (float)($_POST['platform_commission_rate'] ?? 10)),
                'sslcommerz_store_id'       => trim($_POST['sslcommerz_store_id'] ?? 'testbox'),
                'sslcommerz_store_password' => trim($_POST['sslcommerz_store_password'] ?? 'qwerty'),
                'sslcommerz_is_sandbox'     => isset($_POST['sslcommerz_is_sandbox']) ? '1' : '0'
            ];

            try {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_at)
                    VALUES (:k, :v, 'general', NOW())
                    ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = NOW()
                ");

                foreach ($settingsToUpdate as $key => $val) {
                    $stmt->execute(['k' => $key, 'v' => $val, 'v2' => $val]);
                }

                // Update Owner's Display Name & Avatar in users table
                $db->prepare("UPDATE users SET name = :name, avatar = :avatar WHERE id = :id")->execute([
                    'name'   => $ownerName,
                    'avatar' => $ownerAvatar,
                    'id'     => $currentUser['id']
                ]);

                log_audit('update_settings', 'system_settings', null, "Owner updated platform brand identity, economics & avatar");
                set_flash('success', is_bangla() ? 'প্ল্যাটফর্মের সকল ব্র্যান্ডিং, প্রোফাইল ছবি ও সিস্টেম সেটিংস সংরক্ষিত হয়েছে।' : 'Global system, owner profile, and brand identity saved successfully.');
                redirect('owner/settings.php');
            } catch (Exception $e) {
                set_flash('error', 'Failed to save settings: ' . $e->getMessage());
            }
        }
    }
}

// Fetch Current Settings
$currentSettings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = is_bangla() ? 'সিস্টেম ও ব্র্যান্ডিং সেটিংস' : 'System & Brand Settings';
$activePage = 'settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$curLogoType = $currentSettings['site_logo_type'] ?? 'icon';
$curLogoIcon = $currentSettings['site_logo_icon'] ?? 'fa-leaf';
$curLogoImg  = $currentSettings['site_logo_image'] ?? '';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-8 bg-slate-50">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'প্ল্যাটফর্ম কাস্টমাইজেশন' : 'Platform Configuration' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'সিস্টেম সেটিংস, লোগো ও ব্র্যান্ড নিয়ন্ত্রণ' : 'System Branding, Logo & AI Engine' ?></h1>
                <p class="text-xs text-slate-500 mt-0.5"><?= is_bangla() ? 'প্ল্যাটফর্মের নাম, লোগো, ব্যানার টেক্সট, কমিশন ও এআই কন্ট্রোল' : 'Easily manage platform name, logo, announcement banners, commission, and AI engine' ?></p>
            </div>

            <div class="flex items-center space-x-2">
                <a href="<?= BASE_URL ?>/index.php" target="_blank" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[11px]"></i>
                    <span><?= is_bangla() ? 'লাইভ সাইট দেখুন' : 'View Live Site' ?></span>
                </a>
            </div>
        </div>

        <form action="settings.php" method="POST" enctype="multipart/form-data" class="space-y-8 max-w-4xl text-xs">
            <?= csrf_field() ?>

            <!-- 1. Brand Identity & Logo Customization -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                        <i class="fa-solid fa-paintbrush text-emerald-600"></i>
                        <span><?= is_bangla() ? 'ব্র্যান্ডের নাম, লোগো ও আইকন নির্বাচন' : 'Brand Identity, Logo & Visual Customization' ?></span>
                    </h3>
                </div>

                <!-- Logo Type Selector & Visual Preview Box -->
                <div class="p-5 bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl border border-emerald-200 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center space-x-4">
                        <div id="preview-logo-box" class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-800 flex items-center justify-center text-white text-2xl shadow-md shadow-emerald-900/20 shrink-0">
                            <i id="preview-icon" class="fa-solid <?= sanitize($curLogoIcon) ?>"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block"><?= is_bangla() ? 'লাইভ লোগো প্রিভিউ' : 'Live Header Logo Preview' ?></span>
                            <h4 id="preview-brand-text" class="text-base font-extrabold text-slate-900">
                                <?= sanitize($currentSettings['site_brand_title'] ?? 'Smart') ?><span class="text-emerald-600"><?= sanitize($currentSettings['site_brand_subtitle'] ?? 'Nursery') ?></span>
                            </h4>
                            <p id="preview-tagline" class="text-[11px] text-slate-500"><?= sanitize($currentSettings['site_tagline'] ?? 'Curated Botanical Ecosystem') ?></p>
                        </div>
                    </div>

                    <!-- Preset Botanical Icons -->
                    <div class="space-y-1.5 text-right">
                        <span class="text-[11px] font-bold text-slate-700 block"><?= is_bangla() ? 'দ্রুত আইকন নির্বাচন করুন:' : 'Choose Preset Botanical Icon:' ?></span>
                        <div class="flex items-center justify-end space-x-1.5 flex-wrap">
                            <?php 
                            $icons = ['fa-leaf', 'fa-seedling', 'fa-tree', 'fa-spa', 'fa-wind', 'fa-clover', 'fa-feather', 'fa-shield-halved'];
                            foreach ($icons as $ic): 
                            ?>
                                <button type="button" onclick="selectIcon('<?= $ic ?>')" 
                                        class="w-8 h-8 rounded-xl border <?= $curLogoIcon === $ic ? 'border-emerald-600 bg-emerald-100 text-emerald-800 ring-2 ring-emerald-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' ?> flex items-center justify-center text-xs transition">
                                    <i class="fa-solid <?= $ic ?>"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="site_logo_icon" id="site_logo_icon" value="<?= sanitize($curLogoIcon) ?>">
                    </div>
                </div>

                <!-- Logo Image Upload Option -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'লোগোর ধরন' : 'Logo Display Style' ?></label>
                        <select name="site_logo_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                            <option value="icon" <?= $curLogoType === 'icon' ? 'selected' : '' ?>><?= is_bangla() ? 'বোটানিক্যাল আইকন (প্রস্তাবিত)' : 'Botanical Vector Icon (Recommended)' ?></option>
                            <option value="image" <?= $curLogoType === 'image' ? 'selected' : '' ?>><?= is_bangla() ? 'কাস্টম ইমেজ লোগো' : 'Custom Image File Logo' ?></option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'কাস্টম লোগো আপলোড (ঐচ্ছিক)' : 'Upload Custom Image Logo (Optional)' ?></label>
                        <input type="file" name="logo_file" accept="image/*" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-slate-900 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500">
                        <input type="hidden" name="site_logo_image" value="<?= sanitize($curLogoImg) ?>">
                    </div>
                </div>

                <!-- English & Bangla Platform Names -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'প্ল্যাটফর্মের পুরো নাম (English)' : 'Full Platform Name (English)' ?> *</label>
                        <input type="text" name="site_name" value="<?= sanitize($currentSettings['site_name'] ?? 'Smart Nursery Ecosystem') ?>" required
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'প্ল্যাটফর্মের পুরো নাম (বাংলা)' : 'Full Platform Name (Bangla)' ?> *</label>
                        <input type="text" name="site_name_bn" value="<?= sanitize($currentSettings['site_name_bn'] ?? 'স্মার্ট নার্সারি ইকোসিস্টেম') ?>" required
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <!-- Brand Split Names -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Brand Title (EN)</label>
                        <input type="text" name="site_brand_title" value="<?= sanitize($currentSettings['site_brand_title'] ?? 'Smart') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Brand Subtitle (EN)</label>
                        <input type="text" name="site_brand_subtitle" value="<?= sanitize($currentSettings['site_brand_subtitle'] ?? 'Nursery') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">ব্র্যান্ড নাম ১ (BN)</label>
                        <input type="text" name="site_brand_title_bn" value="<?= sanitize($currentSettings['site_brand_title_bn'] ?? 'স্মার্ট') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">ব্র্যান্ড নাম ২ (BN)</label>
                        <input type="text" name="site_brand_subtitle_bn" value="<?= sanitize($currentSettings['site_brand_subtitle_bn'] ?? 'নার্সারি') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                    </div>
                </div>

                <!-- Taglines -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tagline (English)</label>
                        <input type="text" name="site_tagline" value="<?= sanitize($currentSettings['site_tagline'] ?? 'Curated Botanical Wonders & AI-Powered Plant Care') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">স্লোগান / ট্যাগলাইন (বাংলা)</label>
                        <input type="text" name="site_tagline_bn" value="<?= sanitize($currentSettings['site_tagline_bn'] ?? 'সবুজ নিকেতন ও আধুনিক বৃক্ষ পরিচর্যা') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                </div>

                <!-- Top Announcement Banners -->
                <div class="space-y-3 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'টপ ব্যানার নোটিশ (English)' : 'Top Header Announcement (English)' ?></label>
                        <input type="text" name="top_announcement_en" value="<?= sanitize($currentSettings['top_announcement_en'] ?? '🌿 Smart Botanical Ecosystem • Smart Plant Matcher • Verified Local Specialty Nurseries') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'টপ ব্যানার নোটিশ (বাংলা)' : 'Top Header Announcement (Bangla)' ?></label>
                        <input type="text" name="top_announcement_bn" value="<?= sanitize($currentSettings['top_announcement_bn'] ?? '🌿 স্মার্ট বোটানিক্যাল ইকোসিস্টেম • স্মার্ট উদ্ভিদ ম্যাচিং • ভেরিফাইড স্থানীয় বিশেষায়িত নার্সারি') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                </div>

                <!-- Owner Profile Identity & Avatar -->
                <div class="pt-4 border-t border-slate-100 space-y-4">
                    <div class="flex items-center space-x-4 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                        <img id="owner-avatar-preview" 
                             src="<?= sanitize(get_avatar_url($currentUser['avatar'])) ?>" 
                             alt="Owner Avatar" 
                             class="w-16 h-16 rounded-2xl object-cover border-2 border-emerald-500/30 shadow-xs bg-white shrink-0">
                        <div class="flex-1 min-w-0">
                            <label class="block font-bold text-slate-800 mb-1"><?= is_bangla() ? 'ওনার প্রোফাইল ছবি আপলোড' : 'Owner Profile Photo' ?></label>
                            <input type="file" name="owner_avatar_file" accept="image/*" 
                                   onchange="previewImg(this, 'owner-avatar-preview')"
                                   class="w-full text-[11px] text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                            <p class="text-[10px] text-slate-400 mt-0.5"><?= is_bangla() ? 'JPG, PNG, WEBP (সর্বোচ্চ ৩MB)' : 'JPG, PNG, WEBP (Max 3MB)' ?></p>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'প্ল্যাটফর্ম ওনার / স্বত্বাধিকারীর নাম' : 'Platform Owner Name' ?> *</label>
                        <input type="text" name="site_owner_name" value="<?= sanitize($currentSettings['site_owner_name'] ?? $currentUser['name']) ?>" required
                               class="w-full sm:w-1/2 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 font-bold focus:bg-white focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
            </div>

            <!-- 2. Contact Coordinates -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-5">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3 flex items-center space-x-2">
                    <i class="fa-solid fa-headset text-emerald-600"></i>
                    <span><?= is_bangla() ? 'যোগাযোগ ও সাপোর্ট তথ্য' : 'Support & Official Contact Coordinates' ?></span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'অফিসিয়াল সাপোর্ট ইমেইল' : 'Support Email' ?></label>
                        <input type="email" name="contact_email" value="<?= sanitize($currentSettings['contact_email'] ?? 'support@smartnursery.com') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'হটলাইন ফোন নম্বর' : 'Customer Hotline Phone' ?></label>
                        <input type="text" name="contact_phone" value="<?= sanitize($currentSettings['contact_phone'] ?? '+880 1800-NURSERY') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'হেডকোয়ার্টার ঠিকানা (EN)' : 'Headquarters Address (EN)' ?></label>
                        <input type="text" name="contact_address" value="<?= sanitize($currentSettings['contact_address'] ?? 'Dhanmondi, Dhaka, Bangladesh') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'হেডকোয়ার্টার ঠিকানা (বাংলা)' : 'Headquarters Address (BN)' ?></label>
                        <input type="text" name="contact_address_bn" value="<?= sanitize($currentSettings['contact_address_bn'] ?? 'ধানমন্ডি, ঢাকা, বাংলাদেশ') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                </div>
            </div>

            <!-- 3. Economics & E-Commerce -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-5">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3 flex items-center space-x-2">
                    <i class="fa-solid fa-coins text-emerald-600"></i>
                    <span><?= is_bangla() ? 'প্ল্যাটফর্ম কমিশন ও ডেলিভারি ফি' : 'E-Commerce & Platform Economics' ?></span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'প্ল্যাটফর্ম কমিশন রেট (%)' : 'Platform Commission Rate (%)' ?></label>
                        <input type="number" step="0.1" name="platform_commission_rate" value="<?= sanitize($currentSettings['platform_commission_rate'] ?? '10.00') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'মুদ্রার প্রতীক' : 'Currency Symbol' ?></label>
                        <input type="text" name="currency_symbol" value="<?= sanitize($currentSettings['currency_symbol'] ?? '৳') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'কারেন্সি কোড' : 'Currency Code' ?></label>
                        <input type="text" name="currency_code" value="<?= sanitize($currentSettings['currency_code'] ?? 'BDT') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'বেস ডেলিভারি চার্জ (৳)' : 'Base Delivery Shipping Fee (৳)' ?></label>
                        <input type="number" step="0.01" name="shipping_base_fee" value="<?= sanitize($currentSettings['shipping_base_fee'] ?? '60.00') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ফ্রি ডেলিভারি যোগ্যতা সীমা (৳)' : 'Free Shipping Qualification Threshold (৳)' ?></label>
                        <input type="number" step="0.01" name="free_shipping_threshold" value="<?= sanitize($currentSettings['free_shipping_threshold'] ?? '2000.00') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900">
                    </div>
                </div>
            </div>

            <!-- 4. SSLCommerz Real Online Payment Gateway Configuration -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm shadow-sm">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                <?= is_bangla() ? 'এসএসএলকমার্জ পেমেন্ট গেটওয়ে কনফিগারেশন' : 'SSLCommerz Real Payment Gateway Configuration' ?>
                            </h3>
                            <p class="text-[11px] text-slate-500">
                                <?= is_bangla() ? 'বিকাশ, নগদ, রকেট, কার্ড ও নেট ব্যাংকিং অনলাইন লেনদেন নিয়ন্ত্রণ' : 'Configure bKash, Nagad, Rocket, VISA/Mastercard, and Internet Banking credentials' ?>
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold <?= !empty($currentSettings['sslcommerz_is_sandbox']) || !isset($currentSettings['sslcommerz_is_sandbox']) ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                        <?= !empty($currentSettings['sslcommerz_is_sandbox']) || !isset($currentSettings['sslcommerz_is_sandbox']) ? '● Official Sandbox (Test Mode)' : '● Real Live Production' ?>
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            <?= is_bangla() ? 'স্টোর আইডি (Store ID)' : 'SSLCommerz Store ID' ?>
                        </label>
                        <input type="text" name="sslcommerz_store_id" value="<?= sanitize($currentSettings['sslcommerz_store_id'] ?? 'testbox') ?>" placeholder="e.g. testbox or your_merchant_id"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <p class="text-[10px] text-slate-400 mt-1">Default official sandbox Store ID: <code class="text-emerald-700 font-bold">testbox</code></p>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            <?= is_bangla() ? 'স্টোর পাসওয়ার্ড / সিক্রেট কী' : 'SSLCommerz Store Password' ?>
                        </label>
                        <input type="password" name="sslcommerz_store_password" value="<?= sanitize($currentSettings['sslcommerz_store_password'] ?? 'qwerty') ?>" placeholder="e.g. qwerty"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <p class="text-[10px] text-slate-400 mt-1">Default official sandbox password: <code class="text-emerald-700 font-bold">qwerty</code></p>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <label class="flex items-center space-x-3 text-slate-800 font-semibold cursor-pointer">
                        <input type="checkbox" name="sslcommerz_is_sandbox" value="1" <?= (!isset($currentSettings['sslcommerz_is_sandbox']) || !empty($currentSettings['sslcommerz_is_sandbox'])) ? 'checked' : '' ?> class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500">
                        <div>
                            <span class="block text-xs font-bold text-slate-800"><?= is_bangla() ? 'অফিসিয়াল স্যান্ডবক্স টেস্ট মোড ব্যবহার করুন' : 'Enable Official SSLCommerz Sandbox Mode' ?></span>
                            <span class="block text-[10px] text-slate-500"><?= is_bangla() ? 'আনচেক করলে সরাসরি লাইভ প্রোডাকশন গেটওয়ে (securepay.sslcommerz.com) চালু হবে।' : 'Uncheck this box to switch directly to Real Live Production (https://securepay.sslcommerz.com).' ?></span>
                        </div>
                    </label>
                    <a href="https://developer.sslcommerz.com/registration/" target="_blank" class="text-[11px] text-emerald-700 hover:underline font-bold flex items-center space-x-1 shrink-0">
                        <span>Merchant Portal</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                    </a>
                </div>
            </div>

            <!-- 5. Smart Botanical Recommendation Engine Configuration -->
            <div class="bg-gradient-to-br from-emerald-950/5 to-teal-950/10 p-6 sm:p-8 rounded-3xl border-2 border-emerald-500/40 shadow-xs space-y-5">
                <div class="flex items-center justify-between border-b border-emerald-200/60 pb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-900 flex items-center space-x-2">
                        <i class="fa-solid fa-seedling text-emerald-600"></i>
                        <span><?= is_bangla() ? 'স্মার্ট বোটানিক্যাল ম্যাচিং ইঞ্জিন' : 'Smart Botanical Matcher Engine' ?></span>
                    </h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                        <?= is_bangla() ? 'অ্যালগরিদমিক ইঞ্জিন (সক্রিয়)' : 'Deterministic SQL Engine (Active)' ?>
                    </span>
                </div>

                <div class="p-4 bg-white/80 rounded-2xl border border-emerald-200/60 text-xs text-slate-700 space-y-2">
                    <p class="font-semibold text-emerald-900"><?= is_bangla() ? '🌿 অ্যালগরিদমিক ম্যাচিং পদ্ধতি:' : '🌿 Pure Algorithmic Botanical Logic:' ?></p>
                    <p class="leading-relaxed"><?= is_bangla() ? 'সিস্টেমটি সম্পূর্ণ স্থানীয় ডাটাবেজ কোয়েরির মাধ্যমে ব্যবহারকারীর ঘরের আলো, যত্নের সময় ও পোষা প্রাণীর সুরক্ষার সাথে স্টকে থাকা গাছের বৈশিষ্ট্য মিলিয়ে সঠিক গাছ নির্বাচন করে।' : 'The recommendation engine calculates compatibility scores directly from MySQL catalog parameters (sunlight, care level, room, and toxicity), providing instant, reliable, zero-latency recommendations.' ?></p>
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <button type="submit" class="px-8 py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold rounded-2xl shadow-lg shadow-emerald-900/20 transition transform hover:-translate-y-0.5">
                    <?= is_bangla() ? 'সব সেটিংস ও প্রোফাইল সংরক্ষণ করুন' : 'Save All Global Settings & Profile' ?>
                </button>
            </div>
        </form>

        <!-- Owner Password Change Card -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3 flex items-center space-x-2">
                <i class="fa-solid fa-lock text-emerald-600"></i>
                <span><?= is_bangla() ? 'ওনার অ্যাকাউন্ট নিরাপত্তা ও পাসওয়ার্ড পরিবর্তন' : 'Owner Security & Password Change' ?></span>
            </h3>

            <form action="settings.php" method="POST" class="space-y-4 text-xs">
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

                <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-xs transition">
                    <?= is_bangla() ? 'পাসওয়ার্ড পরিবর্তন' : 'Update Password' ?>
                </button>
            </form>
        </div>

    </main>

</div>

<script>
function selectIcon(iconClass) {
    document.getElementById('site_logo_icon').value = iconClass;
    const preview = document.getElementById('preview-icon');
    if (preview) {
        preview.className = 'fa-solid ' + iconClass;
    }
}

function previewImg(input, targetId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(targetId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

