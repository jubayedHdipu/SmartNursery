<?php
// Customer Profile & Shipping Address Settings (Bilingual & Avatar Upload)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/uploads.php';

require_role('customer');
$currentUser = current_user();
$db = get_db();

// Fetch Customer Profile
$pStmt = $db->prepare("SELECT * FROM customer_profiles WHERE user_id = :uid");
$pStmt->execute(['uid' => $currentUser['id']]);
$profile = $pStmt->fetch();

$error = '';
$pwdError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = is_bangla() ? 'নিরাপত্তা সেশন শেষ হয়েছে।' : 'Security session expired.';
    } elseif ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $shippingAddress = trim($_POST['shipping_address'] ?? '');
        $city = trim($_POST['city'] ?? 'Dhaka');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $experience = trim($_POST['plant_experience_level'] ?? 'beginner');

        $avatarPath = $currentUser['avatar'] ?? null;

        // Handle Avatar File Upload
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $avatarRes = handle_image_upload($_FILES['avatar_file'], 'avatars');
            if ($avatarRes['success']) {
                $avatarPath = $avatarRes['filepath'];
            } else {
                $error = $avatarRes['error'];
            }
        }

        if (empty($error)) {
            if (empty($name)) {
                $error = is_bangla() ? 'নাম আবশ্যক।' : 'Name cannot be empty.';
            } else {
                try {
                    $db->beginTransaction();

                    // Update Users table
                    $db->prepare("UPDATE users SET name = :name, phone = :phone, avatar = :avatar, updated_at = NOW() WHERE id = :id")->execute([
                        'name'   => $name,
                        'phone'  => $phone,
                        'avatar' => $avatarPath,
                        'id'     => $currentUser['id']
                    ]);

                    // Update Customer Profiles table
                    if ($profile) {
                        $db->prepare("
                            UPDATE customer_profiles 
                            SET shipping_address = :addr, city = :city, postal_code = :postal, plant_experience_level = :exp
                            WHERE user_id = :uid
                        ")->execute([
                            'addr'   => $shippingAddress,
                            'city'   => $city,
                            'postal' => $postalCode,
                            'exp'    => $experience,
                            'uid'    => $currentUser['id']
                        ]);
                    } else {
                        $db->prepare("
                            INSERT INTO customer_profiles (user_id, shipping_address, city, postal_code, plant_experience_level)
                            VALUES (:uid, :addr, :city, :postal, :exp)
                        ")->execute([
                            'uid'    => $currentUser['id'],
                            'addr'   => $shippingAddress,
                            'city'   => $city,
                            'postal' => $postalCode,
                            'exp'    => $experience
                        ]);
                    }

                    $db->commit();
                    set_flash('success', is_bangla() ? 'প্রোফাইল ও ছবি সফলভাবে সংরক্ষিত হয়েছে।' : 'Profile photo and shipping details updated successfully.');
                    redirect('customer/profile.php');
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
            $pwdError = is_bangla() ? 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters.';
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
                log_audit('change_password', 'user', $currentUser['id'], 'Customer updated account password');
                set_flash('success', is_bangla() ? 'পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে।' : 'Password changed successfully.');
                redirect('customer/profile.php');
            }
        }
    }
}

$pageTitle = is_bangla() ? 'আমার প্রোফাইল ও ঠিকানা' : 'My Profile & Address';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 pb-5 gap-4">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'কাস্টমার প্যানেল' : 'Customer Account' ?></span>
            <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'প্রোফাইল ও ডেলিভারি ঠিকানা' : 'Profile & Delivery Address' ?></h1>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <a href="index.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200"><?= is_bangla() ? 'অর্ডার ইতিহাস' : 'Order History' ?></a>
            <a href="reviews.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200"><?= is_bangla() ? 'রিভিউসমূহ' : 'My Reviews' ?></a>
            <a href="chat.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200"><?= is_bangla() ? 'নার্সারি চ্যাট' : 'Vendor Messages' ?></a>
            <a href="profile.php" class="px-3.5 py-2 bg-emerald-600 text-white font-bold rounded-xl shadow-xs"><?= is_bangla() ? 'প্রোফাইল' : 'Profile' ?></a>
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

    <!-- Main Profile Edit Form -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-6">
        <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-6 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <!-- Profile Avatar Section -->
            <div class="flex items-center space-x-6 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="relative group">
                    <img id="avatar-preview-img" 
                         src="<?= sanitize(get_avatar_url($currentUser['avatar'] ?? null)) ?>" 
                         alt="Avatar" 
                         class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-md bg-white">
                </div>
                <div class="space-y-1">
                    <label class="block font-bold text-slate-800"><?= is_bangla() ? 'প্রোফাইল ছবি পরিবর্তন' : 'Change Profile Avatar' ?></label>
                    <input type="file" name="avatar_file" accept="image/*" 
                           onchange="previewAvatar(this)"
                           class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                    <p class="text-[10px] text-slate-400"><?= is_bangla() ? 'JPG, PNG, WEBP ফরম্যাট (সর্বোচ্চ ৩MB)' : 'JPG, PNG, WEBP format (Max 3MB)' ?></p>
                </div>
            </div>

            <!-- Name and Email -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'পূর্ণ নাম' : 'Full Name' ?> *</label>
                    <input type="text" name="name" required value="<?= sanitize($_POST['name'] ?? $currentUser['name']) ?>" 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ইমেইল অ্যাড্রেস (স্থির)' : 'Email Address (Fixed)' ?></label>
                    <input type="email" value="<?= sanitize($currentUser['email']) ?>" readonly 
                           class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-500 cursor-not-allowed">
                </div>
            </div>

            <!-- Phone and Plant Experience -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'মোবাইল নম্বর' : 'Phone Number' ?></label>
                    <input type="text" name="phone" value="<?= sanitize($_POST['phone'] ?? $currentUser['phone']) ?>" placeholder="+880 1700-000000"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'গাছপালার পরিচর্যার অভিজ্ঞতা' : 'Plant Experience Level' ?></label>
                    <select name="plant_experience_level" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="beginner" <?= ($profile['plant_experience_level'] ?? '') === 'beginner' ? 'selected' : '' ?>><?= is_bangla() ? 'নবীন (নতুন শুরু করেছি)' : 'Beginner (Just starting)' ?></option>
                        <option value="intermediate" <?= ($profile['plant_experience_level'] ?? '') === 'intermediate' ? 'selected' : '' ?>><?= is_bangla() ? 'মাঝারি (৫টির বেশি গাছ আছে)' : 'Intermediate (Own 5+ plants)' ?></option>
                        <option value="expert" <?= ($profile['plant_experience_level'] ?? '') === 'expert' ? 'selected' : '' ?>><?= is_bangla() ? 'অভিজ্ঞ বোটানিস্ট' : 'Expert Botanist / Enthusiast' ?></option>
                    </select>
                </div>
            </div>

            <!-- Shipping Address -->
            <div>
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ডিফল্ট ডেলিভারি ঠিকানা' : 'Default Delivery Address' ?></label>
                <input type="text" name="shipping_address" value="<?= sanitize($_POST['shipping_address'] ?? ($profile['shipping_address'] ?? '')) ?>" 
                       placeholder="<?= is_bangla() ? 'বাড়ি নং, রোড নং, এলাকা' : 'House #, Road #, Area' ?>"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <!-- City and Postal Code -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'শহর' : 'City' ?></label>
                    <select name="city" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                        <option value="Dhaka" <?= ($profile['city'] ?? 'Dhaka') === 'Dhaka' ? 'selected' : '' ?>>Dhaka (ঢাকা)</option>
                        <option value="Chittagong" <?= ($profile['city'] ?? '') === 'Chittagong' ? 'selected' : '' ?>>Chittagong (চট্টগ্রাম)</option>
                        <option value="Sylhet" <?= ($profile['city'] ?? '') === 'Sylhet' ? 'selected' : '' ?>>Sylhet (সিলেট)</option>
                        <option value="Rajshahi" <?= ($profile['city'] ?? '') === 'Rajshahi' ? 'selected' : '' ?>>Rajshahi (রাজশাহী)</option>
                        <option value="Khulna" <?= ($profile['city'] ?? '') === 'Khulna' ? 'selected' : '' ?>>Khulna (খুলনা)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'পোস্টাল কোড' : 'Postal Code' ?></label>
                    <input type="text" name="postal_code" value="<?= sanitize($_POST['postal_code'] ?? ($profile['postal_code'] ?? '')) ?>" placeholder="1230"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <button type="submit" class="px-6 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-md transition flex items-center space-x-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span><?= is_bangla() ? 'প্রোফাইল পরিবর্তন সংরক্ষণ করুন' : 'Save Profile Changes' ?></span>
            </button>
        </form>
    </div>

    <!-- Password Change Card -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3 flex items-center space-x-2">
            <i class="fa-solid fa-lock text-emerald-600"></i>
            <span><?= is_bangla() ? 'পাসওয়ার্ড পরিবর্তন' : 'Change Account Password' ?></span>
        </h3>

        <form action="profile.php" method="POST" class="space-y-4 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'বর্তমান পাসওয়ার্ড' : 'Current Password' ?> *</label>
                    <input type="password" name="current_password" required placeholder="••••••••"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নতুন পাসওয়ার্ড' : 'New Password' ?> *</label>
                    <input type="password" name="new_password" required placeholder="••••••••"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'পাসওয়ার্ড নিশ্চিতকরণ' : 'Confirm Password' ?> *</label>
                    <input type="password" name="confirm_password" required placeholder="••••••••"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-xs transition">
                <?= is_bangla() ? 'পাসওয়ার্ড আপডেট করুন' : 'Update Password' ?>
            </button>
        </form>
    </div>

</div>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatar-preview-img').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
