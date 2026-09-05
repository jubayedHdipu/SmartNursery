<?php
// Administrator Account Profile & Avatar Settings

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/uploads.php';

require_role('admin');
$currentUser = current_user();
$db = get_db();

// Fetch Admin Stats
$totalVendors = (int)$db->query("SELECT COUNT(*) FROM vendor_profiles")->fetchColumn();
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

$error = '';
$pwdError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = is_bangla() ? 'নিরাপত্তা সেশন শেষ হয়েছে।' : 'Security session expired.';
    } elseif ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $avatarPath = $currentUser['avatar'];

        // Handle Admin Avatar Upload
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $upRes = handle_image_upload($_FILES['avatar_file'], 'avatars');
            if ($upRes['success']) {
                $avatarPath = $upRes['filepath'];
            } else {
                $error = $upRes['error'];
            }
        }

        if (empty($error)) {
            if (empty($name)) {
                $error = is_bangla() ? 'পূর্ণ নাম আবশ্যক।' : 'Full name is required.';
            } else {
                try {
                    $db->prepare("UPDATE users SET name = :name, phone = :phone, avatar = :avatar WHERE id = :id")->execute([
                        'name'   => $name,
                        'phone'  => $phone,
                        'avatar' => $avatarPath,
                        'id'     => $currentUser['id']
                    ]);

                    log_audit('update_admin_profile', 'user', $currentUser['id'], 'Admin updated profile & avatar');
                    set_flash('success', is_bangla() ? 'অ্যাডমিন প্রোফাইল ও ছবি সফলভাবে সংরক্ষিত হয়েছে!' : 'Admin profile and avatar updated successfully!');
                    redirect('admin/profile.php');
                } catch (Exception $e) {
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
                log_audit('change_password', 'user', $currentUser['id'], 'Admin updated account password');
                set_flash('success', is_bangla() ? 'পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে।' : 'Password changed successfully.');
                redirect('admin/profile.php');
            }
        }
    }
}

$pageTitle = is_bangla() ? 'অ্যাডমিন প্রোফাইল ও সেটিংস' : 'Admin Profile Settings';
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
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'সিস্টেম অ্যাডমিনিস্ট্রেশন' : 'System Administration' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'অ্যাডমিন প্রোফাইল' : 'Admin Profile Settings' ?></h1>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-800">
                    <i class="fa-solid fa-shield-halved text-emerald-600 mr-1.5"></i>
                    <?= is_bangla() ? 'সিস্টেম অ্যাডমিনিস্ট্রেটর' : 'Administrator Privileges' ?>
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
            
            <!-- Left 8 Cols: Edit Profile Form -->
            <div class="lg:col-span-8 space-y-6">
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                            <i class="fa-solid fa-user-shield text-emerald-600"></i>
                            <span><?= is_bangla() ? 'অ্যাডমিন ব্যক্তিগত তথ্য ও প্রোফাইল ছবি' : 'Admin Personal Info & Avatar' ?></span>
                        </h3>
                    </div>

                    <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-5 text-xs">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <!-- Avatar Upload Box -->
                        <div class="flex items-center space-x-4 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                            <img id="avatar-preview-img" 
                                 src="<?= sanitize(get_avatar_url($currentUser['avatar'])) ?>" 
                                 alt="Admin Avatar" 
                                 class="w-16 h-16 rounded-2xl object-cover border-2 border-emerald-500/30 shadow-xs bg-white shrink-0">
                            <div class="flex-1 min-w-0">
                                <label class="block font-bold text-slate-800 mb-1"><?= is_bangla() ? 'প্রোফাইল ছবি পরিবর্তন' : 'Change Profile Avatar' ?></label>
                                <input type="file" name="avatar_file" id="input_avatar_file" accept="image/*" 
                                       onchange="previewImg(this, 'avatar-preview-img')"
                                       class="w-full text-[11px] text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                                <p class="text-[10px] text-slate-400 mt-0.5"><?= is_bangla() ? 'JPG, PNG, WEBP (সর্বোচ্চ ৩MB)' : 'JPG, PNG, WEBP (Max 3MB)' ?></p>
                            </div>
                        </div>

                        <!-- Name & Email -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'পূর্ণ নাম' : 'Full Name' ?> *</label>
                                <input type="text" name="name" required value="<?= sanitize($currentUser['name']) ?>"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ইমেইল অ্যাড্রেস (স্থির)' : 'Email Address (Fixed)' ?></label>
                                <input type="email" value="<?= sanitize($currentUser['email']) ?>" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-500 cursor-not-allowed">
                            </div>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'মোবাইল নম্বর' : 'Mobile Phone' ?></label>
                            <input type="text" name="phone" value="<?= sanitize($currentUser['phone']) ?>" placeholder="+880 1700-000000"
                                   class="w-full sm:w-1/2 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <button type="submit" class="px-5 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-sm transition flex items-center space-x-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span><?= is_bangla() ? 'তথ্য সংরক্ষণ করুন' : 'Save Profile Details' ?></span>
                        </button>
                    </form>
                </div>

                <!-- Password Change Card -->
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

            <!-- Right 4 Cols: Admin Stats Card -->
            <div class="lg:col-span-4 space-y-4 sticky top-24">
                
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center space-x-3">
                        <img id="sidebar-admin-avatar" 
                             src="<?= sanitize(get_avatar_url($currentUser['avatar'])) ?>" 
                             class="w-14 h-14 rounded-2xl object-cover border-2 border-emerald-500/30 shadow-xs shrink-0">
                        <div class="min-w-0">
                            <h4 class="font-extrabold text-sm text-slate-900 truncate"><?= sanitize($currentUser['name']) ?></h4>
                            <span class="text-[11px] text-emerald-700 font-semibold block"><?= is_bangla() ? 'সিস্টেম অ্যাডমিনিস্ট্রেটর' : 'Administrator' ?></span>
                            <span class="text-[10px] text-slate-400 truncate block"><?= sanitize($currentUser['email']) ?></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 text-xs">
                        <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-100 text-center">
                            <span class="text-[10px] text-slate-500 font-semibold block"><?= is_bangla() ? 'পার্টনার নার্সারি' : 'Vendors' ?></span>
                            <strong class="text-emerald-700 text-lg font-black"><?= format_num_i18n($totalVendors) ?></strong>
                        </div>
                        <div class="p-3 bg-sky-50 rounded-2xl border border-sky-100 text-center">
                            <span class="text-[10px] text-slate-500 font-semibold block"><?= is_bangla() ? 'মোট অর্ডার' : 'Total Orders' ?></span>
                            <strong class="text-sky-700 text-lg font-black"><?= format_num_i18n($totalOrders) ?></strong>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 space-y-2 text-xs text-slate-600">
                        <div class="flex justify-between">
                            <span><?= is_bangla() ? 'অ্যাক্সেস লেভেল' : 'Access Level' ?></span>
                            <strong class="text-emerald-700 font-bold">Full Admin</strong>
                        </div>
                        <div class="flex justify-between">
                            <span><?= is_bangla() ? 'যুক্ত হওয়ার তারিখ' : 'Member Since' ?></span>
                            <strong class="text-slate-800"><?= format_date($currentUser['created_at']) ?></strong>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </main>

</div>

<script>
function previewImg(input, targetId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(targetId).src = e.target.result;
            const sidebarAvatar = document.getElementById('sidebar-admin-avatar');
            if (sidebarAvatar) sidebarAvatar.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
