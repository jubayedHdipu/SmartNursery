<?php
// Vendor Verification & Moderation (Enhanced with Full Edit Capabilities)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifications.php';
require_once __DIR__ . '/../helpers/uploads.php';

require_role(['admin', 'owner']);
$currentUser = current_user();
$db = get_db();

// Handle Actions (Status Update & Full Vendor Info Edit)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf)) {
        if ($action === 'update_status') {
            $vendorId = (int)($_POST['vendor_id'] ?? 0);
            $status = $_POST['status'] ?? 'verified';

            if ($vendorId > 0) {
                $vStmt = $db->prepare("SELECT user_id, nursery_name FROM vendor_profiles WHERE id = :id");
                $vStmt->execute(['id' => $vendorId]);
                $vendor = $vStmt->fetch();

                if ($vendor) {
                    $db->prepare("UPDATE vendor_profiles SET verification_status = :status WHERE id = :id")->execute(['status' => $status, 'id' => $vendorId]);
                    
                    if ($status === 'verified') {
                        $db->prepare("UPDATE users SET status = 'active' WHERE id = :uid")->execute(['uid' => $vendor['user_id']]);
                        create_notification((int)$vendor['user_id'], "Nursery Store Verified! 🌿", "Congratulations! Your nursery store '{$vendor['nursery_name']}' has been approved by administration.", "vendor/index.php", 'system');
                    } elseif ($status === 'suspended') {
                        $db->prepare("UPDATE users SET status = 'suspended' WHERE id = :uid")->execute(['uid' => $vendor['user_id']]);
                    }

                    log_audit('moderate_vendor', 'vendor', $vendorId, "Admin updated status to {$status}");
                    set_flash('success', is_bangla() ? "নার্সারি '{$vendor['nursery_name']}' এর স্ট্যাটাস '{$status}' করা হয়েছে।" : "Vendor '{$vendor['nursery_name']}' marked as {$status}.");
                }
            }
            redirect('admin/vendors.php');
        } elseif ($action === 'edit_vendor') {
            $vendorId       = (int)($_POST['vendor_id'] ?? 0);
            $nurseryName    = trim($_POST['nursery_name'] ?? '');
            $nurseryNameBn  = trim($_POST['nursery_name_bn'] ?? '');
            $ownerName      = trim($_POST['owner_name'] ?? '');
            $ownerEmail     = trim($_POST['owner_email'] ?? '');
            $ownerPhone     = trim($_POST['owner_phone'] ?? '');
            $address        = trim($_POST['address'] ?? '');
            $addressBn      = trim($_POST['address_bn'] ?? '');
            $city           = trim($_POST['city'] ?? 'Dhaka');
            $desc           = trim($_POST['description'] ?? '');
            $descBn         = trim($_POST['description_bn'] ?? '');
            $status         = trim($_POST['verification_status'] ?? 'verified');
            $commissionRate = max(0, (float)($_POST['commission_rate'] ?? 10.0));
            $ratingAvg      = min(5.0, max(1.0, (float)($_POST['rating_avg'] ?? 5.0)));

            if ($vendorId > 0 && !empty($nurseryName) && !empty($ownerName)) {
                $vStmt = $db->prepare("SELECT * FROM vendor_profiles WHERE id = :id");
                $vStmt->execute(['id' => $vendorId]);
                $vendor = $vStmt->fetch();

                if ($vendor) {
                    try {
                        $db->beginTransaction();

                        $logoPath = $vendor['logo_image'];
                        $bannerPath = $vendor['banner_image'];

                        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                            $logoRes = handle_image_upload($_FILES['logo_file'], 'nurseries');
                            if ($logoRes['success']) {
                                $logoPath = $logoRes['filepath'];
                            }
                        }

                        if (isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
                            $bannerRes = handle_image_upload($_FILES['banner_file'], 'nurseries');
                            if ($bannerRes['success']) {
                                $bannerPath = $bannerRes['filepath'];
                            }
                        }

                        // Update User info
                        $db->prepare("UPDATE users SET name = :name, email = :email, phone = :phone WHERE id = :uid")->execute([
                            'name'  => $ownerName,
                            'email' => $ownerEmail,
                            'phone' => $ownerPhone,
                            'uid'   => $vendor['user_id']
                        ]);

                        // Update Vendor Profile
                        $db->prepare("
                            UPDATE vendor_profiles 
                            SET nursery_name = :nname, nursery_name_bn = :nname_bn,
                                address = :addr, address_bn = :addr_bn, city = :city,
                                description = :desc, description_bn = :desc_bn,
                                verification_status = :status, commission_rate = :comm,
                                rating_avg = :rating, logo_image = :logo, banner_image = :banner
                            WHERE id = :id
                        ")->execute([
                            'nname'    => $nurseryName,
                            'nname_bn' => $nurseryNameBn,
                            'addr'     => $address,
                            'addr_bn'  => $addressBn,
                            'city'     => $city,
                            'desc'     => $desc,
                            'desc_bn'  => $descBn,
                            'status'   => $status,
                            'comm'     => $commissionRate,
                            'rating'   => $ratingAvg,
                            'logo'     => $logoPath,
                            'banner'   => $bannerPath,
                            'id'       => $vendorId
                        ]);

                        $db->commit();
                        log_audit('edit_vendor', 'vendor', $vendorId, "Admin updated vendor details and media for '{$nurseryName}'");
                        set_flash('success', is_bangla() ? "নার্সারি '{$nurseryName}' এর সকল তথ্য ও ছবি সফলভাবে আপডেট হয়েছে।" : "Vendor '{$nurseryName}' details and media updated successfully.");
                        redirect('admin/vendors.php');
                    } catch (Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        $error = 'Failed to update vendor: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Fetch All Vendors
$vendors = $db->query("
    SELECT vp.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone, u.status AS user_status,
           (SELECT COUNT(*) FROM products WHERE vendor_id = vp.id) AS total_plants
    FROM vendor_profiles vp
    JOIN users u ON vp.user_id = u.id
    ORDER BY FIELD(vp.verification_status, 'pending', 'verified', 'suspended', 'rejected'), vp.id DESC
")->fetchAll();

$pageTitle = is_bangla() ? 'নার্সারি ও ভেন্ডর ব্যবস্থাপনা' : 'Vendor Moderation & Management';
$activePage = 'vendors';
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
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'ভেরিফিকেশন ও স্টোরফ্রন্ট নিয়ন্ত্রণ' : 'Verification Queue & Storefront Management' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'নার্সারি ভেন্ডর অ্যাক্রিডিটেশন ও প্রোফাইল নিয়ন্ত্রণ' : 'Vendor Accreditation & Store Information' ?></h1>
            </div>
            <span class="px-3.5 py-1.5 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-bold">
                <?= format_num_i18n(count($vendors)) ?> <?= is_bangla() ? 'টি নার্সারি পার্টনার' : 'Partner Nurseries' ?>
            </span>
        </div>

        <?php if ($error): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold">
                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i><?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span><?= is_bangla() ? 'মোট নিবন্ধিত নার্সারি' : 'Total Nurseries' ?>: <strong class="text-slate-800"><?= format_num_i18n(count($vendors)) ?></strong></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4"><?= is_bangla() ? 'নার্সারি স্টোর' : 'Nursery Store' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'স্বত্বাধিকারী ও যোগাযোগ' : 'Owner Contact' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'ঠিকানা ও শহর' : 'Location' ?></th>
                            <th class="p-4 text-center"><?= is_bangla() ? 'তালিকাভুক্ত গাছ' : 'Listed Plants' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'স্ট্যাটাস' : 'Status' ?></th>
                            <th class="p-4 text-right"><?= is_bangla() ? 'মডারেশন ও সম্পাদনা' : 'Moderation Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php if (!empty($vendors)): ?>
                            <?php foreach ($vendors as $v): ?>
                                <tr>
                                    <td class="p-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold flex items-center justify-center text-sm shrink-0">
                                                <i class="fa-solid fa-store"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-900 text-xs hover:text-emerald-600">
                                                    <?= sanitize(vendor_display_name($v)) ?>
                                                </h4>
                                                <?php if (is_bangla() && !empty($v['nursery_name_bn'])): ?>
                                                    <span class="text-[11px] text-slate-400 block"><?= sanitize($v['nursery_name']) ?></span>
                                                <?php endif; ?>
                                                <span class="text-[10px] text-amber-500 font-bold">★ <?= format_num_i18n($v['rating_avg']) ?> (<?= format_num_i18n($v['rating_count']) ?>)</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="font-bold text-slate-800 block"><?= sanitize($v['owner_name']) ?></span>
                                        <span class="text-slate-500 text-[11px] block"><?= sanitize($v['owner_email']) ?></span>
                                        <span class="text-slate-400 text-[11px]"><i class="fa-solid fa-phone mr-1"></i><?= sanitize($v['owner_phone'] ?: 'N/A') ?></span>
                                    </td>
                                    <td class="p-4">
                                        <p class="text-slate-700"><?= sanitize(vendor_display_address($v['address'])) ?></p>
                                        <span class="inline-block mt-0.5 text-[10px] px-2 py-0.5 rounded-md bg-slate-100 font-bold text-slate-600"><?= sanitize($v['city'] ?? 'Dhaka') ?></span>
                                    </td>
                                    <td class="p-4 text-center font-bold text-slate-900">
                                        <?= format_num_i18n($v['total_plants']) ?> <?= is_bangla() ? 'টি' : 'plants' ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $v['verification_status'] === 'verified' ? 'bg-emerald-100 text-emerald-800' : ($v['verification_status'] === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') ?>">
                                            <?= $v['verification_status'] === 'verified' ? (is_bangla() ? 'ভেরিফাইড' : 'Verified') : ($v['verification_status'] === 'pending' ? (is_bangla() ? 'অপেক্ষমাণ' : 'Pending') : sanitize($v['verification_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right space-x-1.5">
                                        <!-- Edit Vendor Details Button -->
                                        <button type="button" onclick="openEditVendorModal(<?= htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8') ?>)" 
                                                class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold rounded-lg transition text-xs">
                                            <i class="fa-solid fa-pen-to-square mr-1"></i><?= __('common.edit') ?>
                                        </button>

                                        <?php if ($v['verification_status'] !== 'verified'): ?>
                                            <form action="vendors.php" method="POST" class="inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                                                <input type="hidden" name="status" value="verified">
                                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg shadow-xs transition">
                                                    <?= is_bangla() ? 'অনুমোদন' : 'Approve' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($v['verification_status'] === 'verified'): ?>
                                            <form action="vendors.php" method="POST" onsubmit="return confirm('<?= is_bangla() ? 'এই ভেন্ডর অ্যাকাউন্ট কি স্থগিত করতে চান?' : 'Suspend this vendor account?' ?>');" class="inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                                                <input type="hidden" name="status" value="suspended">
                                                <button type="submit" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold rounded-lg">
                                                    <?= is_bangla() ? 'স্থগিত' : 'Suspend' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400">
                                    <?= is_bangla() ? 'কোন নার্সারি তালিকা পাওয়া যায়নি।' : 'No nurseries found.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<!-- Interactive Edit Vendor Modal -->
<div id="edit-vendor-modal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full p-6 sm:p-8 space-y-5 animate-scale-in max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-store text-emerald-600"></i>
                <span><?= is_bangla() ? 'নার্সারি ও ভেন্ডর তথ্য সম্পাদনা' : 'Edit Nursery & Vendor Profile' ?></span>
            </h3>
            <button type="button" onclick="closeEditVendorModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="vendors.php" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_vendor">
            <input type="hidden" name="vendor_id" id="modal_vendor_id" value="">

            <!-- Logo & Banner Upload -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 bg-slate-50 rounded-2xl border border-slate-200">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নার্সারি লোগো' : 'Nursery Logo' ?></label>
                    <input type="file" name="logo_file" accept="image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'কভার ব্যানার' : 'Cover Banner' ?></label>
                    <input type="file" name="banner_file" accept="image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নার্সারির নাম (English)' : 'Nursery Name (English)' ?> *</label>
                    <input type="text" name="nursery_name" id="modal_v_name" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নার্সারির নাম (বাংলা)' : 'Nursery Name (Bangla)' ?></label>
                    <input type="text" name="nursery_name_bn" id="modal_v_name_bn" placeholder="যেমন: গ্রিন হেভেন নার্সারি"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'স্বত্বাধিকারীর নাম' : 'Owner Contact Name' ?> *</label>
                    <input type="text" name="owner_name" id="modal_v_owner_name" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ইমেইল' : 'Email Address' ?> *</label>
                    <input type="email" name="owner_email" id="modal_v_owner_email" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'হটলাইন ফোন' : 'Hotline Phone' ?></label>
                    <input type="text" name="owner_phone" id="modal_v_owner_phone"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ঠিকানা (English)' : 'Physical Address (English)' ?> *</label>
                    <input type="text" name="address" id="modal_v_address" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'শহর' : 'City' ?> *</label>
                    <select name="city" id="modal_v_city" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
                        <option value="Dhaka">Dhaka (ঢাকা)</option>
                        <option value="Chittagong">Chittagong (চট্টগ্রাম)</option>
                        <option value="Sylhet">Sylhet (সিলেট)</option>
                        <option value="Rajshahi">Rajshahi (রাজশাহী)</option>
                        <option value="Khulna">Khulna (খুলনা)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ঠিকানা (বাংলা)' : 'Physical Address (Bangla)' ?></label>
                <input type="text" name="address_bn" id="modal_v_address_bn" placeholder="যেমন: প্লট ১৪, রোড ৭, ধানমন্ডি, ঢাকা"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নার্সারি বায়ো / বিবরণ (EN)' : 'Nursery Bio (EN)' ?></label>
                    <textarea name="description" id="modal_v_desc" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নার্সারি বায়ো / বিবরণ (BN)' : 'Nursery Bio (BN)' ?></label>
                    <textarea name="description_bn" id="modal_v_desc_bn" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ভেরিফিকেশন স্ট্যাটাস' : 'Verification Status' ?></label>
                    <select name="verification_status" id="modal_v_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
                        <option value="verified"><?= is_bangla() ? 'ভেরিফাইড ও সক্রিয় (Verified)' : 'Verified & Active' ?></option>
                        <option value="pending"><?= is_bangla() ? 'অপেক্ষমাণ (Pending)' : 'Pending Review' ?></option>
                        <option value="suspended"><?= is_bangla() ? 'স্থগিত (Suspended)' : 'Suspended' ?></option>
                        <option value="rejected"><?= is_bangla() ? 'বাতিল (Rejected)' : 'Rejected' ?></option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'কমিশন হার (%)' : 'Commission Rate (%)' ?></label>
                    <input type="number" step="0.1" name="commission_rate" id="modal_v_commission" value="10.0"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'স্টোর রেটিং (১ - ৫)' : 'Store Rating (1.0 - 5.0)' ?></label>
                    <input type="number" step="0.1" min="1" max="5" name="rating_avg" id="modal_v_rating" value="5.0"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeEditVendorModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl">
                    <?= __('common.cancel') ?>
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition">
                    <?= is_bangla() ? 'নার্সারি তথ্য আপডেট করুন' : 'Save Vendor Details' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditVendorModal(v) {
    document.getElementById('modal_vendor_id').value = v.id;
    document.getElementById('modal_v_name').value = v.nursery_name || '';
    document.getElementById('modal_v_name_bn').value = v.nursery_name_bn || '';
    document.getElementById('modal_v_owner_name').value = v.owner_name || '';
    document.getElementById('modal_v_owner_email').value = v.owner_email || '';
    document.getElementById('modal_v_owner_phone').value = v.owner_phone || '';
    document.getElementById('modal_v_address').value = v.address || '';
    document.getElementById('modal_v_address_bn').value = v.address_bn || '';
    document.getElementById('modal_v_city').value = v.city || 'Dhaka';
    document.getElementById('modal_v_desc').value = v.description || '';
    document.getElementById('modal_v_desc_bn').value = v.description_bn || '';
    document.getElementById('modal_v_status').value = v.verification_status || 'verified';
    document.getElementById('modal_v_commission').value = v.commission_rate || '10.0';
    document.getElementById('modal_v_rating').value = v.rating_avg || '5.0';
    document.getElementById('edit-vendor-modal').classList.remove('hidden');
}

function closeEditVendorModal() {
    document.getElementById('edit-vendor-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

