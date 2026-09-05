<?php
// Dedicated Customer Management (Admin & Owner)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role(['admin', 'owner']);
$currentUser = current_user();
$db = get_db();

$error = '';
$searchQuery = trim($_GET['q'] ?? '');

// Handle Actions (Edit Customer, Toggle Status, Delete Customer)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf)) {
        if ($action === 'edit_customer') {
            $userId       = (int)($_POST['user_id'] ?? 0);
            $name         = trim($_POST['name'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $phone        = trim($_POST['phone'] ?? '');
            $address      = trim($_POST['shipping_address'] ?? '');
            $city         = trim($_POST['city'] ?? 'Dhaka');
            $expLevel     = trim($_POST['plant_experience_level'] ?? 'beginner');
            $status       = trim($_POST['status'] ?? 'active');
            $password     = trim($_POST['password'] ?? '');

            if ($userId > 0 && !empty($name) && !empty($email)) {
                // Check email uniqueness
                $chk = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $chk->execute(['email' => $email, 'id' => $userId]);
                if ($chk->fetch()) {
                    $error = is_bangla() ? 'এই ইমেইলটি অন্য একটি অ্যাকাউন্টে ব্যবহৃত হচ্ছে।' : 'This email address is already in use by another customer.';
                } else {
                    try {
                        $db->beginTransaction();

                        if (!empty($password)) {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $db->prepare("UPDATE users SET name = :name, email = :email, phone = :phone, status = :status, password_hash = :hash WHERE id = :id AND role_id = 4")->execute([
                                'name'   => $name,
                                'email'  => $email,
                                'phone'  => $phone,
                                'status' => $status,
                                'hash'   => $hash,
                                'id'     => $userId
                            ]);
                        } else {
                            $db->prepare("UPDATE users SET name = :name, email = :email, phone = :phone, status = :status WHERE id = :id AND role_id = 4")->execute([
                                'name'   => $name,
                                'email'  => $email,
                                'phone'  => $phone,
                                'status' => $status,
                                'id'     => $userId
                            ]);
                        }

                        // Update or insert customer profile
                        $cpCheck = $db->prepare("SELECT id FROM customer_profiles WHERE user_id = :uid");
                        $cpCheck->execute(['uid' => $userId]);
                        if ($cpCheck->fetch()) {
                            $db->prepare("
                                UPDATE customer_profiles 
                                SET shipping_address = :addr, city = :city, plant_experience_level = :exp
                                WHERE user_id = :uid
                            ")->execute([
                                'addr' => $address,
                                'city' => $city,
                                'exp'  => $expLevel,
                                'uid'  => $userId
                            ]);
                        } else {
                            $db->prepare("
                                INSERT INTO customer_profiles (user_id, shipping_address, city, plant_experience_level, created_at)
                                VALUES (:uid, :addr, :city, :exp, NOW())
                            ")->execute([
                                'uid'  => $userId,
                                'addr' => $address,
                                'city' => $city,
                                'exp'  => $expLevel
                            ]);
                        }

                        $db->commit();
                        log_audit('edit_customer', 'user', $userId, "Admin updated customer profile '{$name}'");
                        set_flash('success', is_bangla() ? "গ্রাহক '{$name}' এর তথ্য সফলভাবে আপডেট হয়েছে।" : "Customer profile '{$name}' updated successfully.");
                        redirect('admin/customers.php');
                    } catch (Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        $error = 'Failed to update customer: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'toggle_status') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                $db->prepare("UPDATE users SET status = IF(status='active', 'suspended', 'active') WHERE id = :id AND role_id = 4")->execute(['id' => $userId]);
                log_audit('toggle_customer_status', 'user', $userId, "Admin toggled customer status");
                set_flash('success', is_bangla() ? 'গ্রাহকের অ্যাকাউন্টের অবস্থা পরিবর্তিত হয়েছে।' : 'Customer account status updated.');
            }
            redirect('admin/customers.php');
        } elseif ($action === 'delete_customer') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                try {
                    $db->beginTransaction();
                    $db->prepare("DELETE FROM customer_profiles WHERE user_id = :uid")->execute(['uid' => $userId]);
                    $db->prepare("DELETE FROM users WHERE id = :id AND role_id = 4")->execute(['id' => $userId]);
                    $db->commit();
                    log_audit('delete_customer', 'user', $userId, "Admin removed customer account #{$userId}");
                    set_flash('success', is_bangla() ? 'গ্রাহক অ্যাকাউন্ট সফলভাবে মুছে ফেলা হয়েছে।' : 'Customer account removed successfully.');
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    set_flash('error', 'Could not delete customer: ' . $e->getMessage());
                }
            }
            redirect('admin/customers.php');
        }
    }
}

// Build Search Query for Customers
$whereClause = "WHERE u.role_id = 4";
$params = [];
if (!empty($searchQuery)) {
    $whereClause .= " AND (u.name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q OR cp.shipping_address LIKE :q OR cp.city LIKE :q)";
    $params['q'] = '%' . $searchQuery . '%';
}

// Fetch Customers List with Order Counts and Lifetime Value
$cQuery = "
    SELECT u.id AS user_id, u.name, u.email, u.phone, u.status AS account_status, u.created_at,
           cp.id AS profile_id, cp.shipping_address, cp.city, cp.plant_experience_level,
           (SELECT COUNT(*) FROM orders WHERE customer_id = u.id) AS total_orders,
           (SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE customer_id = u.id AND order_status != 'Cancelled') AS total_spent
    FROM users u
    LEFT JOIN customer_profiles cp ON u.id = cp.user_id
    {$whereClause}
    ORDER BY u.id DESC
";
$stmt = $db->prepare($cQuery);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Metrics Summary
$totalCustomers = count($customers);
$totalOrdersAll = 0;
$totalRevenueAll = 0;
$activeCount = 0;

foreach ($customers as $c) {
    $totalOrdersAll += (int)($c['total_orders'] ?? 0);
    $totalRevenueAll += (float)($c['total_spent'] ?? 0);
    if ($c['account_status'] === 'active') {
        $activeCount++;
    }
}

$pageTitle = is_bangla() ? 'গ্রাহক ব্যবস্থাপনা' : 'Customer Management';
$activePage = 'customers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-8 bg-slate-50">
        
        <!-- Top Title Bar -->
        <div class="border-b border-slate-200 pb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'গ্রাহক ও ক্রেতা নিয়ন্ত্রণ' : 'Customer Relations & Moderation' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'নিবন্ধিত গ্রাহক ও ক্রেতা তালিকা' : 'Registered Customers Directory' ?></h1>
                <p class="text-xs text-slate-500 mt-0.5"><?= is_bangla() ? 'গাছপ্রেমী ক্রেতাদের প্রোফাইল, ঠিকানা, অর্ডার হিস্টোরি ও অ্যাকাউন্ট নিয়ন্ত্রণ করুন' : 'Manage registered plant buyers, contact coordinates, shipping addresses, and purchase history' ?></p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-3.5 py-1.5 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-bold">
                    <?= format_num_i18n($totalCustomers) ?> <?= is_bangla() ? 'জন গ্রাহক নিবন্ধিত' : 'Registered Customers' ?>
                </span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold">
                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i><?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><?= is_bangla() ? 'মোট গ্রাহক' : 'Total Customers' ?></span>
                    <strong class="text-xl font-extrabold text-slate-900"><?= format_num_i18n($totalCustomers) ?></strong>
                </div>
            </div>

            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><?= is_bangla() ? 'সক্রিয় অ্যাকাউন্ট' : 'Active Accounts' ?></span>
                    <strong class="text-xl font-extrabold text-teal-700"><?= format_num_i18n($activeCount) ?></strong>
                </div>
            </div>

            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><?= is_bangla() ? 'মোট অর্ডার সম্পন্ন' : 'Total Orders Placed' ?></span>
                    <strong class="text-xl font-extrabold text-amber-700"><?= format_num_i18n($totalOrdersAll) ?></strong>
                </div>
            </div>

            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><?= is_bangla() ? 'মোট কেনাকাটা মূল্য' : 'Lifetime GMV' ?></span>
                    <strong class="text-xl font-extrabold text-indigo-700"><?= format_currency($totalRevenueAll) ?></strong>
                </div>
            </div>
        </div>

        <!-- Search & Filter Card -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
            <form action="customers.php" method="GET" class="flex-1 flex items-center space-x-2 w-full">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= sanitize($searchQuery) ?>" placeholder="<?= is_bangla() ? 'গ্রাহকের নাম, ইমেইল, ফোন বা ঠিকানা দিয়ে খুঁজুন...' : 'Search by customer name, email, phone, or address...' ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition">
                    <?= __('common.search') ?>
                </button>
                <?php if (!empty($searchQuery)): ?>
                    <a href="customers.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-xs rounded-xl transition">
                        <?= is_bangla() ? 'রিসেট' : 'Clear' ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Customers List Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span><?= is_bangla() ? 'গ্রাহক ফলাফল' : 'Customer Results' ?>: <strong class="text-slate-800"><?= format_num_i18n(count($customers)) ?></strong></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4"><?= is_bangla() ? 'গ্রাহকের নাম' : 'Customer Name' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'যোগাযোগ ও ঠিকানা' : 'Coordinates & Location' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'অভিজ্ঞতা' : 'Plant Level' ?></th>
                            <th class="p-4 text-center"><?= is_bangla() ? 'অর্ডার ও ব্যয়' : 'Orders & Spent' ?></th>
                            <th class="p-4"><?= is_bangla() ? 'স্ট্যাটাস' : 'Account Status' ?></th>
                            <th class="p-4 text-right"><?= is_bangla() ? 'অ্যাকশন ও সম্পাদনা' : 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td class="p-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-sm shrink-0">
                                                <i class="fa-solid fa-user"></i>
                                            </div>
                                            <div>
                                                <span class="font-bold text-slate-900 block"><?= sanitize($c['name']) ?></span>
                                                <span class="text-slate-500 text-[11px] block"><?= sanitize($c['email']) ?></span>
                                                <span class="text-slate-400 text-[10px]"><?= is_bangla() ? 'যুক্ত' : 'Joined' ?> <?= format_date($c['created_at']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="text-slate-800 font-medium block"><i class="fa-solid fa-phone mr-1 text-slate-400"></i><?= sanitize($c['phone'] ?: 'N/A') ?></span>
                                        <p class="text-slate-500 text-[11px] truncate max-w-xs"><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i><?= sanitize($c['shipping_address'] ?: 'Dhaka, Bangladesh') ?></p>
                                    </td>
                                    <td class="p-4">
                                        <?php 
                                        $lvl = $c['plant_experience_level'] ?? 'beginner';
                                        $lvlBadge = $lvl === 'expert' ? 'bg-purple-100 text-purple-800' : ($lvl === 'intermediate' ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-700');
                                        ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $lvlBadge ?>">
                                            <?= is_bangla() ? ($lvl === 'expert' ? 'অভিজ্ঞ (Expert)' : ($lvl === 'intermediate' ? 'মধ্যম (Intermediate)' : 'নতুন (Beginner)')) : ucfirst($lvl) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="font-bold text-slate-900 block"><?= format_num_i18n((int)($c['total_orders'] ?? 0)) ?> <?= is_bangla() ? 'টি অর্ডার' : 'orders' ?></span>
                                        <span class="text-[11px] text-emerald-600 font-extrabold"><?= format_currency((float)($c['total_spent'] ?? 0)) ?></span>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $c['account_status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                            <?= $c['account_status'] === 'active' ? __('common.active') : (is_bangla() ? 'স্থগিত' : 'Suspended') ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right space-x-1.5">
                                        <!-- Edit Customer Trigger Button -->
                                        <button type="button" onclick="openEditCustomerModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)" 
                                                class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold rounded-lg transition text-xs">
                                            <i class="fa-solid fa-pen-to-square mr-1"></i><?= __('common.edit') ?>
                                        </button>

                                        <!-- Toggle Suspend/Active Form -->
                                        <form action="customers.php" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $c['user_id'] ?>">
                                            <button type="submit" class="px-2.5 py-1.5 text-xs font-semibold rounded-lg <?= $c['account_status'] === 'active' ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' ?>">
                                                <?= $c['account_status'] === 'active' ? (is_bangla() ? 'স্থগিত' : 'Suspend') : (is_bangla() ? 'সক্রিয়' : 'Activate') ?>
                                            </button>
                                        </form>

                                        <!-- Delete Customer Button -->
                                        <form action="customers.php" method="POST" onsubmit="return confirm('<?= is_bangla() ? 'এই গ্রাহক অ্যাকাউন্টটি কি মুছে ফেলতে চান?' : 'Permanently remove this customer account?' ?>');" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_customer">
                                            <input type="hidden" name="user_id" value="<?= $c['user_id'] ?>">
                                            <button type="submit" class="px-2 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700" title="<?= is_bangla() ? 'মুছে ফেলুন' : 'Delete' ?>">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400">
                                    <?= is_bangla() ? 'কোন গ্রাহক রেকর্ড পাওয়া যায়নি।' : 'No customer records found.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<!-- Interactive Edit Customer Modal -->
<div id="edit-customer-modal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-xl w-full p-6 sm:p-8 space-y-5 animate-scale-in max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-user-pen text-emerald-600"></i>
                <span><?= is_bangla() ? 'গ্রাহকের তথ্য ও প্রোফাইল সম্পাদনা' : 'Edit Customer Information' ?></span>
            </h3>
            <button type="button" onclick="closeEditCustomerModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="customers.php" method="POST" class="space-y-4 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_customer">
            <input type="hidden" name="user_id" id="modal_c_user_id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'গ্রাহকের নাম' : 'Full Name' ?> *</label>
                    <input type="text" name="name" id="modal_c_name" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ইমেইল অ্যাড্রেস' : 'Email Address' ?> *</label>
                    <input type="email" name="email" id="modal_c_email" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ফোন নম্বর' : 'Phone Number' ?></label>
                    <input type="text" name="phone" id="modal_c_phone"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'শহর' : 'City' ?></label>
                    <select name="city" id="modal_c_city" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
                        <option value="Dhaka">Dhaka (ঢাকা)</option>
                        <option value="Chittagong">Chittagong (চট্টগ্রাম)</option>
                        <option value="Sylhet">Sylhet (সিলেট)</option>
                        <option value="Rajshahi">Rajshahi (রাজশাহী)</option>
                        <option value="Khulna">Khulna (খুলনা)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'শিপিং ঠিকানা' : 'Default Delivery Address' ?></label>
                <input type="text" name="shipping_address" id="modal_c_address" placeholder="House, Road, Area, City"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'গাছ পরিচর্যার অভিজ্ঞতা' : 'Gardening Experience' ?></label>
                    <select name="plant_experience_level" id="modal_c_exp" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
                        <option value="beginner"><?= is_bangla() ? 'নতুন (Beginner)' : 'Beginner' ?></option>
                        <option value="intermediate"><?= is_bangla() ? 'মধ্যম (Intermediate)' : 'Intermediate' ?></option>
                        <option value="expert"><?= is_bangla() ? 'অভিজ্ঞ (Expert)' : 'Expert' ?></option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'অ্যাকাউন্ট স্ট্যাটাস' : 'Account Status' ?></label>
                    <select name="status" id="modal_c_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
                        <option value="active"><?= __('common.active') ?></option>
                        <option value="suspended"><?= is_bangla() ? 'স্থগিত (Suspended)' : 'Suspended' ?></option>
                    </select>
                </div>
            </div>

            <div class="pt-2 border-t border-slate-100">
                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'নতুন পাসওয়ার্ড সেট করুন (খালি রাখলে আগের পাসওয়ার্ড থাকবে)' : 'Reset Password (Leave blank to keep existing password)' ?></label>
                <input type="password" name="password" placeholder="••••••••"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeEditCustomerModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl">
                    <?= __('common.cancel') ?>
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition">
                    <?= is_bangla() ? 'গ্রাহক তথ্য সংরক্ষণ করুন' : 'Save Changes' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCustomerModal(c) {
    document.getElementById('modal_c_user_id').value = c.user_id;
    document.getElementById('modal_c_name').value = c.name || '';
    document.getElementById('modal_c_email').value = c.email || '';
    document.getElementById('modal_c_phone').value = c.phone || '';
    document.getElementById('modal_c_address').value = c.shipping_address || '';
    document.getElementById('modal_c_city').value = c.city || 'Dhaka';
    document.getElementById('modal_c_exp').value = c.plant_experience_level || 'beginner';
    document.getElementById('modal_c_status').value = c.account_status || 'active';
    document.getElementById('edit-customer-modal').classList.remove('hidden');
}

function closeEditCustomerModal() {
    document.getElementById('edit-customer-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
