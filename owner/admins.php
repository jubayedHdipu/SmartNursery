<?php
// Owner Admin Accounts Management (Enhanced)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('owner');
$currentUser = current_user();
$db = get_db();

// Handle Actions (Create, Edit, Toggle, Delete)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf)) {
        if ($action === 'create_admin') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($name) || empty($email) || empty($password)) {
                $error = is_bangla() ? 'সকল তথ্য পূরণ করা আবশ্যক।' : 'All fields are required.';
            } else {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    $error = is_bangla() ? 'এই ইমেইলে ইতোমধ্যে একটি অ্যাকাউন্ট রয়েছে।' : 'An account with this email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare("
                        INSERT INTO users (role_id, name, email, password_hash, phone, status, created_at)
                        VALUES (2, :name, :email, :hash, :phone, 'active', NOW())
                    ")->execute(['name' => $name, 'email' => $email, 'hash' => $hash, 'phone' => $phone]);

                    $newId = $db->lastInsertId();
                    log_audit('create_admin', 'user', $newId, "Owner created new admin '{$name}' ({$email})");
                    set_flash('success', is_bangla() ? "অ্যাডমিনিস্ট্রেটর অ্যাকাউন্ট '{$name}' তৈরি হয়েছে।" : "Administrator account '{$name}' created.");
                    redirect('owner/admins.php');
                }
            }
        } elseif ($action === 'edit_admin') {
            $adminId = (int)($_POST['admin_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $status = trim($_POST['status'] ?? 'active');
            $password = trim($_POST['password'] ?? '');

            if ($adminId > 0 && !empty($name) && !empty($email)) {
                // Check if email belongs to someone else
                $chk = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $chk->execute(['email' => $email, 'id' => $adminId]);
                if ($chk->fetch()) {
                    $error = is_bangla() ? 'এই ইমেইলটি অন্য অ্যাকাউন্টে ব্যবহৃত হচ্ছে।' : 'This email address is already in use by another user.';
                } else {
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $db->prepare("UPDATE users SET name = :name, email = :email, phone = :phone, status = :status, password_hash = :hash WHERE id = :id AND role_id = 2")
                           ->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'status' => $status, 'hash' => $hash, 'id' => $adminId]);
                    } else {
                        $db->prepare("UPDATE users SET name = :name, email = :email, phone = :phone, status = :status WHERE id = :id AND role_id = 2")
                           ->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'status' => $status, 'id' => $adminId]);
                    }
                    log_audit('edit_admin', 'user', $adminId, "Owner updated admin details for '{$name}'");
                    set_flash('success', is_bangla() ? "অ্যাডমিন '{$name}' এর তথ্য সফলভাবে আপডেট হয়েছে।" : "Administrator '{$name}' info updated successfully.");
                    redirect('owner/admins.php');
                }
            }
        } elseif ($action === 'toggle_status') {
            $adminId = (int)($_POST['admin_id'] ?? 0);
            if ($adminId !== $currentUser['id']) {
                $db->prepare("UPDATE users SET status = IF(status='active', 'suspended', 'active') WHERE id = :id AND role_id = 2")->execute(['id' => $adminId]);
                log_audit('toggle_admin_status', 'user', $adminId, "Owner toggled admin status");
                set_flash('success', is_bangla() ? 'অ্যাডমিনের স্ট্যাটাস পরিবর্তন করা হয়েছে।' : 'Admin status updated.');
            }
            redirect('owner/admins.php');
        } elseif ($action === 'delete_admin') {
            $adminId = (int)($_POST['admin_id'] ?? 0);
            if ($adminId !== $currentUser['id']) {
                $db->prepare("DELETE FROM users WHERE id = :id AND role_id = 2")->execute(['id' => $adminId]);
                log_audit('delete_admin', 'user', $adminId, "Owner removed admin account #{$adminId}");
                set_flash('success', is_bangla() ? 'অ্যাডমিন অ্যাকাউন্ট মুছে ফেলা হয়েছে।' : 'Admin account removed successfully.');
            }
            redirect('owner/admins.php');
        }
    }
}

// Fetch Admins
$admins = $db->query("
    SELECT u.*, r.display_name AS role_display
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE r.name = 'admin'
    ORDER BY u.id ASC
")->fetchAll();

$pageTitle = is_bangla() ? 'অ্যাডমিনিস্ট্রেটর পরিচালনা' : 'Manage Administrator Accounts';
$activePage = 'admins';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-8 bg-slate-50">
        
        <div class="border-b border-slate-200 pb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'প্রশাসনিক অ্যাক্সেস নিয়ন্ত্রণ' : 'Administrative Access & Governance' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'অ্যাডমিনিস্ট্রেটর অ্যাকাউন্ট ম্যানেজার' : 'Administrator Accounts & Role Management' ?></h1>
            </div>
            <div>
                <span class="px-3.5 py-1.5 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-bold">
                    <?= format_num_i18n(count($admins)) ?> <?= is_bangla() ? 'জন সক্রিয় অ্যাডমিন' : 'Active Admins' ?>
                </span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold">
                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i><?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Create Admin Form -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3 flex items-center space-x-2">
                        <i class="fa-solid fa-user-plus text-emerald-600"></i>
                        <span><?= is_bangla() ? 'নতুন অ্যাডমিন তৈরি করুন' : 'Create New Administrator' ?></span>
                    </h3>

                    <form action="admins.php" method="POST" class="space-y-4 text-xs">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_admin">

                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'অ্যাডমিনের পুরো নাম' : 'Admin Full Name' ?> *</label>
                            <input type="text" name="name" required placeholder="e.g. Tanvir Hasan"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ইমেইল অ্যাড্রেস' : 'Email Address' ?> *</label>
                            <input type="email" name="email" required placeholder="admin@domain.com"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ফোন নম্বর' : 'Phone Number' ?></label>
                            <input type="text" name="phone" placeholder="+880 1700-000000"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'অ্যাক্সেস পাসওয়ার্ড' : 'Initial Password' ?> *</label>
                            <input type="password" name="password" required placeholder="Password123!"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-shield-halved text-xs"></i>
                            <span><?= is_bangla() ? 'অ্যাডমিন অ্যাকাউন্ট তৈরি করুন' : 'Create Administrator' ?></span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Admins List Table -->
            <div class="lg:col-span-8 space-y-4">
                <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span><?= is_bangla() ? 'নিযুক্ত সকল অ্যাডমিনিস্ট্রেটর' : 'Assigned Platform Administrators' ?>: <strong class="text-slate-800"><?= format_num_i18n(count($admins)) ?></strong></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                                    <th class="p-4"><?= is_bangla() ? 'অ্যাডমিনের নাম' : 'Admin Name' ?></th>
                                    <th class="p-4"><?= is_bangla() ? 'ইমেইল ও ফোন' : 'Contact Coordinates' ?></th>
                                    <th class="p-4"><?= is_bangla() ? 'স্ট্যাটাস' : 'Status' ?></th>
                                    <th class="p-4 text-right"><?= is_bangla() ? 'অ্যাকশন ও সম্পাদনা' : 'Actions & Edit' ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-slate-700">
                                <?php if (!empty($admins)): ?>
                                    <?php foreach ($admins as $a): ?>
                                        <tr>
                                            <td class="p-4">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs shrink-0">
                                                        <?= strtoupper(substr($a['name'], 0, 2)) ?>
                                                    </div>
                                                    <div>
                                                        <span class="font-bold text-slate-900 block"><?= sanitize($a['name']) ?></span>
                                                        <span class="text-[11px] text-slate-400">ID: #<?= $a['id'] ?> • <?= is_bangla() ? 'যুক্ত' : 'Joined' ?> <?= format_date($a['created_at']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <span class="font-mono text-slate-800 block text-xs"><?= sanitize($a['email']) ?></span>
                                                <span class="text-slate-500 text-[11px]"><i class="fa-solid fa-phone text-slate-400 mr-1"></i><?= sanitize($a['phone'] ?: 'N/A') ?></span>
                                            </td>
                                            <td class="p-4">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold <?= $a['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                                    <?= $a['status'] === 'active' ? __('common.active') : (is_bangla() ? 'স্থগিত' : 'Suspended') ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-right space-x-1.5">
                                                <!-- Edit Admin Trigger Button -->
                                                <button type="button" onclick="openEditAdminModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8') ?>)" 
                                                        class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold rounded-lg transition text-xs">
                                                    <i class="fa-solid fa-pen-to-square mr-1"></i><?= __('common.edit') ?>
                                                </button>

                                                <!-- Toggle Suspend/Active Form -->
                                                <form action="admins.php" method="POST" class="inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                                    <button type="submit" class="px-2.5 py-1.5 text-xs font-semibold rounded-lg <?= $a['status'] === 'active' ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' ?>">
                                                        <?= $a['status'] === 'active' ? (is_bangla() ? 'স্থগিত' : 'Suspend') : (is_bangla() ? 'সক্রিয়' : 'Activate') ?>
                                                    </button>
                                                </form>

                                                <!-- Delete Button -->
                                                <form action="admins.php" method="POST" onsubmit="return confirm('<?= is_bangla() ? 'এই অ্যাডমিন অ্যাকাউন্ট কি স্থায়ীভাবে মুছে ফেলতে চান?' : 'Permanently remove this administrator account?' ?>');" class="inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_admin">
                                                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                                    <button type="submit" class="px-2 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700" title="<?= is_bangla() ? 'মুছে ফেলুন' : 'Remove' ?>">
                                                        <i class="fa-regular fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="p-8 text-center text-slate-400">
                                            <?= is_bangla() ? 'কোন অ্যাডমিনিস্ট্রেটর অ্যাকাউন্ট পাওয়া যায়নি।' : 'No admin accounts found.' ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </main>

</div>

<!-- Interactive Edit Admin Modal -->
<div id="edit-admin-modal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 sm:p-8 space-y-5 animate-scale-in">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-user-pen text-emerald-600"></i>
                <span><?= is_bangla() ? 'অ্যাডমিন তথ্য সম্পাদনা করুন' : 'Edit Administrator Information' ?></span>
            </h3>
            <button type="button" onclick="closeEditAdminModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="admins.php" method="POST" class="space-y-4 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_admin">
            <input type="hidden" name="admin_id" id="modal_admin_id" value="">

            <div>
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'পুরো নাম' : 'Full Name' ?> *</label>
                <input type="text" name="name" id="modal_admin_name" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ইমেইল' : 'Email Address' ?> *</label>
                <input type="email" name="email" id="modal_admin_email" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ফোন নম্বর' : 'Phone Number' ?></label>
                <input type="text" name="phone" id="modal_admin_phone"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'অ্যাকাউন্ট স্ট্যাটাস' : 'Account Status' ?></label>
                <select name="status" id="modal_admin_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
                    <option value="active"><?= __('common.active') ?></option>
                    <option value="suspended"><?= is_bangla() ? 'স্থগিত (Suspended)' : 'Suspended' ?></option>
                </select>
            </div>

            <div class="pt-2 border-t border-slate-100">
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নতুন পাসওয়ার্ড সেট করুন (খালি রাখলে আগের পাসওয়ার্ড থাকবে)' : 'Reset Password (Leave blank to keep existing password)' ?></label>
                <input type="password" name="password" placeholder="••••••••"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="flex items-center justify-end space-x-3 pt-3">
                <button type="button" onclick="closeEditAdminModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl">
                    <?= __('common.cancel') ?>
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition">
                    <?= is_bangla() ? 'আপডেট সংরক্ষণ করুন' : 'Save Changes' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditAdminModal(admin) {
    document.getElementById('modal_admin_id').value = admin.id;
    document.getElementById('modal_admin_name').value = admin.name;
    document.getElementById('modal_admin_email').value = admin.email;
    document.getElementById('modal_admin_phone').value = admin.phone || '';
    document.getElementById('modal_admin_status').value = admin.status || 'active';
    document.getElementById('edit-admin-modal').classList.remove('hidden');
}

function closeEditAdminModal() {
    document.getElementById('edit-admin-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

