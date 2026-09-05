<?php
// Delivery Rider Management (Admin & Owner)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role(['admin', 'owner']);
$currentUser = current_user();
$db = get_db();

$error = '';

// Handle Actions (Add Rider, Edit Rider, Toggle Status, Delete Rider)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf)) {
        if ($action === 'add_rider') {
            $name          = trim($_POST['name'] ?? '');
            $email         = trim($_POST['email'] ?? '');
            $phone         = trim($_POST['phone'] ?? '');
            $password      = $_POST['password'] ?? '';
            $vehicleType   = trim($_POST['vehicle_type'] ?? 'Motorcycle');
            $currentZone   = trim($_POST['current_zone'] ?? 'Dhaka Central');
            $licenseNumber = trim($_POST['license_number'] ?? '');

            if (empty($name) || empty($email) || empty($password)) {
                $error = is_bangla() ? 'নাম, ইমেইল এবং পাসওয়ার্ড পূরণ করা আবশ্যক।' : 'Name, email, and password are required.';
            } else {
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    $error = is_bangla() ? 'এই ইমেইলে ইতোমধ্যে একটি অ্যাকাউন্ট রয়েছে।' : 'An account with this email address already exists.';
                } else {
                    try {
                        $db->beginTransaction();

                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $uStmt = $db->prepare("
                            INSERT INTO users (role_id, name, email, password_hash, phone, status, created_at)
                            VALUES (5, :name, :email, :hash, :phone, 'active', NOW())
                        ");
                        $uStmt->execute([
                            'name'  => $name,
                            'email' => $email,
                            'hash'  => $hash,
                            'phone' => $phone
                        ]);
                        $newUserId = (int)$db->lastInsertId();

                        // Insert Delivery Staff Profile
                        $pStmt = $db->prepare("
                            INSERT INTO delivery_staff_profiles (user_id, vehicle_type, license_number, status, current_zone, total_deliveries, created_at)
                            VALUES (:uid, :vehicle, :license, 'available', :zone, 0, NOW())
                        ");
                        $pStmt->execute([
                            'uid'     => $newUserId,
                            'vehicle' => $vehicleType,
                            'license' => $licenseNumber,
                            'zone'    => $currentZone
                        ]);

                        $db->commit();
                        log_audit('create_rider', 'user', $newUserId, "Admin created new delivery rider '{$name}'");
                        set_flash('success', is_bangla() ? "নতুন রাইডার '{$name}' সফলভাবে নিবন্ধিত হয়েছে।" : "Delivery rider '{$name}' added successfully.");
                        redirect('admin/riders.php');
                    } catch (Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        $error = 'Failed to create rider: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'edit_rider') {
            $userId        = (int)($_POST['user_id'] ?? 0);
            $profileId     = (int)($_POST['profile_id'] ?? 0);
            $name          = trim($_POST['name'] ?? '');
            $email         = trim($_POST['email'] ?? '');
            $phone         = trim($_POST['phone'] ?? '');
            $vehicleType   = trim($_POST['vehicle_type'] ?? 'Motorcycle');
            $currentZone   = trim($_POST['current_zone'] ?? 'Dhaka Central');
            $licenseNumber = trim($_POST['license_number'] ?? '');
            $staffStatus   = trim($_POST['staff_status'] ?? 'available');
            $accountStatus = trim($_POST['account_status'] ?? 'active');
            $password      = trim($_POST['password'] ?? '');

            if ($userId > 0 && !empty($name) && !empty($email)) {
                // Check email uniqueness
                $chk = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $chk->execute(['email' => $email, 'id' => $userId]);
                if ($chk->fetch()) {
                    $error = is_bangla() ? 'এই ইমেইলটি অন্য একটি অ্যাকাউন্টে ব্যবহৃত হচ্ছে।' : 'This email address is already in use by another user.';
                } else {
                    try {
                        $db->beginTransaction();

                        if (!empty($password)) {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $db->prepare("UPDATE users SET name = :name, email = :email, phone = :phone, status = :status, password_hash = :hash WHERE id = :id")->execute([
                                'name'   => $name,
                                'email'  => $email,
                                'phone'  => $phone,
                                'status' => $accountStatus,
                                'hash'   => $hash,
                                'id'     => $userId
                            ]);
                        } else {
                            $db->prepare("UPDATE users SET name = :name, email = :email, phone = :phone, status = :status WHERE id = :id")->execute([
                                'name'   => $name,
                                'email'  => $email,
                                'phone'  => $phone,
                                'status' => $accountStatus,
                                'id'     => $userId
                            ]);
                        }

                        // Update or insert staff profile
                        $pCheck = $db->prepare("SELECT id FROM delivery_staff_profiles WHERE user_id = :uid");
                        $pCheck->execute(['uid' => $userId]);
                        if ($pCheck->fetch()) {
                            $db->prepare("
                                UPDATE delivery_staff_profiles
                                SET vehicle_type = :vehicle, license_number = :license, status = :sstatus, current_zone = :zone
                                WHERE user_id = :uid
                            ")->execute([
                                'vehicle' => $vehicleType,
                                'license' => $licenseNumber,
                                'sstatus' => $staffStatus,
                                'zone'    => $currentZone,
                                'uid'     => $userId
                            ]);
                        } else {
                            $db->prepare("
                                INSERT INTO delivery_staff_profiles (user_id, vehicle_type, license_number, status, current_zone, total_deliveries, created_at)
                                VALUES (:uid, :vehicle, :license, :sstatus, :zone, 0, NOW())
                            ")->execute([
                                'uid'     => $userId,
                                'vehicle' => $vehicleType,
                                'license' => $licenseNumber,
                                'sstatus' => $staffStatus,
                                'zone'    => $currentZone
                            ]);
                        }

                        $db->commit();
                        log_audit('edit_rider', 'user', $userId, "Admin updated delivery rider '{$name}'");
                        set_flash('success', is_bangla() ? "রাইডার '{$name}' এর তথ্য সফলভাবে আপডেট হয়েছে।" : "Delivery rider '{$name}' updated successfully.");
                        redirect('admin/riders.php');
                    } catch (Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        $error = 'Failed to update rider: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'toggle_status') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                $db->prepare("UPDATE users SET status = IF(status='active', 'suspended', 'active') WHERE id = :id AND role_id = 5")->execute(['id' => $userId]);
                log_audit('toggle_rider_status', 'user', $userId, "Admin toggled rider status");
                set_flash('success', is_bangla() ? 'রাইডারের অ্যাকাউন্টের অবস্থা পরিবর্তিত হয়েছে।' : 'Rider account status updated.');
            }
            redirect('admin/riders.php');
        } elseif ($action === 'delete_rider') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                try {
                    $db->beginTransaction();
                    $db->prepare("DELETE FROM delivery_staff_profiles WHERE user_id = :uid")->execute(['uid' => $userId]);
                    $db->prepare("DELETE FROM users WHERE id = :id AND role_id = 5")->execute(['id' => $userId]);
                    $db->commit();
                    log_audit('delete_rider', 'user', $userId, "Admin removed delivery rider #{$userId}");
                    set_flash('success', is_bangla() ? 'রাইডার অ্যাকাউন্ট সফলভাবে মুছে ফেলা হয়েছে।' : 'Rider account removed successfully.');
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    set_flash('error', 'Could not delete rider: ' . $e->getMessage());
                }
            }
            redirect('admin/riders.php');
        }
    }
}

// Fetch All Delivery Staff with Profiles and Active Assignments
$riders = $db->query("
    SELECT u.id AS user_id, u.name, u.email, u.phone, u.status AS account_status, u.created_at,
           dsp.id AS profile_id, dsp.vehicle_type, dsp.license_number, dsp.status AS staff_status, dsp.current_zone,
           (SELECT COUNT(*) FROM delivery_assignments da WHERE (da.delivery_staff_id = u.id OR da.delivery_staff_id = dsp.id) AND da.status = 'delivered') AS total_deliveries,
           (SELECT COUNT(*) FROM delivery_assignments da WHERE (da.delivery_staff_id = u.id OR da.delivery_staff_id = dsp.id) AND da.status IN ('assigned', 'picked_up', 'out_for_delivery')) AS active_tasks
    FROM users u
    LEFT JOIN delivery_staff_profiles dsp ON u.id = dsp.user_id
    WHERE u.role_id = 5
    ORDER BY u.id DESC
")->fetchAll();

// Calculate Summary Statistics
$totalRiders = count($riders);
$availableRiders = 0;
$busyRiders = 0;
$totalDeliveredSum = 0;

foreach ($riders as $r) {
    if (($r['staff_status'] ?? '') === 'available' && $r['account_status'] === 'active' && (int)$r['active_tasks'] === 0) {
        $availableRiders++;
    } elseif (($r['staff_status'] ?? '') === 'busy' || (int)$r['active_tasks'] > 0) {
        $busyRiders++;
    }
    $totalDeliveredSum += (int)($r['total_deliveries'] ?? 0);
}

$pageTitle = is_bangla() ? 'ডেলিভারি রাইডার ব্যবস্থাপনা' : 'Delivery Riders & Logistics';
$activePage = 'riders';
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
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600"><?= is_bangla() ? 'লজিস্টিকস ও কুরিয়ার পরিচালনা' : 'Logistics & Dispatch Operations' ?></span>
                <h1 class="text-2xl font-extrabold text-slate-900"><?= is_bangla() ? 'ডেলিভারি রাইডার ও কুরিয়ার টিম' : 'Delivery Riders & Logistics Team' ?></h1>
                <p class="text-xs text-slate-500 mt-0.5"><?= is_bangla() ? 'রাইডার যুক্ত করুন, তথ্য সম্পাদনা করুন এবং সক্রিয় ডেলিভারি পর্যবেক্ষণ করুন' : 'Add new couriers, edit profiles, reset credentials, and monitor delivery assignments' ?></p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-3.5 py-1.5 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-bold">
                    <?= format_num_i18n($totalRiders) ?> <?= is_bangla() ? 'জন রাইডার নিবন্ধিত' : 'Registered Couriers' ?>
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
                    <i class="fa-solid fa-motorcycle"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><?= is_bangla() ? 'মোট রাইডার' : 'Total Fleet' ?></span>
                    <strong class="text-xl font-extrabold text-slate-900"><?= format_num_i18n($totalRiders) ?></strong>
                </div>
            </div>

            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><?= is_bangla() ? 'প্রস্তুত / ফ্রি রাইডার' : 'Available Now' ?></span>
                    <strong class="text-xl font-extrabold text-teal-700"><?= format_num_i18n($availableRiders) ?></strong>
                </div>
            </div>

            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-route"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><?= is_bangla() ? 'ডেলিভারিতে ব্যস্ত' : 'On Active Trips' ?></span>
                    <strong class="text-xl font-extrabold text-amber-700"><?= format_num_i18n($busyRiders) ?></strong>
                </div>
            </div>

            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-boxes-packing"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><?= is_bangla() ? 'মোট সম্পন্ন ডেলিভারি' : 'Total Delivered' ?></span>
                    <strong class="text-xl font-extrabold text-indigo-700"><?= format_num_i18n($totalDeliveredSum) ?></strong>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Left 4 Cols: Add New Rider Form -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3 flex items-center space-x-2">
                        <i class="fa-solid fa-user-plus text-emerald-600"></i>
                        <span><?= is_bangla() ? 'নতুন রাইডার যুক্ত করুন' : 'Add Delivery Courier' ?></span>
                    </h3>

                    <form action="riders.php" method="POST" class="space-y-4 text-xs">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_rider">

                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'রাইডারের পুরো নাম' : 'Rider Full Name' ?> *</label>
                            <input type="text" name="name" required placeholder="e.g. Rahim Uddin"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ইমেইল অ্যাড্রেস' : 'Email Address' ?> *</label>
                            <input type="email" name="email" required placeholder="rider@domain.com"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ফোন নম্বর' : 'Hotline Phone' ?> *</label>
                            <input type="text" name="phone" required placeholder="+880 1700-000000"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'যানবাহন' : 'Vehicle' ?></label>
                                <select name="vehicle_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                                    <option value="Motorcycle">Motorcycle (মোটরসাইকেল)</option>
                                    <option value="Bicycle">Bicycle (বাইসাইকেল)</option>
                                    <option value="Electric Scooter">Electric Scooter (ই-স্কুটার)</option>
                                    <option value="Delivery Van">Delivery Van (ডেলিভারি ভ্যান)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'কাজের জোন' : 'Zone' ?></label>
                                <input type="text" name="current_zone" value="Dhaka Central" placeholder="e.g. Dhanmondi"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'লাইসেন্স / এনআইডি নম্বর' : 'License / NID No.' ?></label>
                            <input type="text" name="license_number" placeholder="DL-DHAKA-10294"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'লগইন পাসওয়ার্ড' : 'Initial Password' ?> *</label>
                            <input type="password" name="password" required placeholder="Password123!"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span><?= is_bangla() ? 'রাইডার যুক্ত করুন' : 'Register Delivery Courier' ?></span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right 8 Cols: Delivery Riders Table -->
            <div class="lg:col-span-8 space-y-4">
                <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span><?= is_bangla() ? 'নিযুক্ত রাইডার তালিকা' : 'Active Delivery Fleet' ?>: <strong class="text-slate-800"><?= format_num_i18n(count($riders)) ?></strong></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                                    <th class="p-4"><?= is_bangla() ? 'রাইডারের নাম ও ইমেইল' : 'Courier Name' ?></th>
                                    <th class="p-4"><?= is_bangla() ? 'যানবাহন ও জোন' : 'Vehicle & Zone' ?></th>
                                    <th class="p-4 text-center"><?= is_bangla() ? 'ডেলিভারি সংখ্যা' : 'Deliveries' ?></th>
                                    <th class="p-4"><?= is_bangla() ? 'স্ট্যাটাস' : 'Availability & Status' ?></th>
                                    <th class="p-4 text-right"><?= is_bangla() ? 'অ্যাকশন ও সম্পাদনা' : 'Actions' ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-slate-700">
                                <?php if (!empty($riders)): ?>
                                    <?php foreach ($riders as $r): ?>
                                        <tr>
                                            <td class="p-4">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-10 h-10 rounded-2xl bg-sky-100 text-sky-800 font-bold flex items-center justify-center text-sm shrink-0">
                                                        <i class="fa-solid fa-motorcycle"></i>
                                                    </div>
                                                    <div>
                                                        <span class="font-bold text-slate-900 block"><?= sanitize($r['name']) ?></span>
                                                        <span class="text-slate-500 text-[11px] block"><?= sanitize($r['email']) ?></span>
                                                        <span class="text-slate-400 text-[10px]"><i class="fa-solid fa-phone mr-1"></i><?= sanitize($r['phone'] ?: 'N/A') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <div class="space-y-1">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                                        <i class="fa-solid fa-bicycle mr-1 text-slate-500"></i>
                                                        <?= sanitize($r['vehicle_type'] ?? 'Motorcycle') ?>
                                                    </span>
                                                    <span class="block text-[11px] text-slate-500"><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i><?= sanitize($r['current_zone'] ?? 'Dhaka Central') ?></span>
                                                </div>
                                            </td>
                                            <td class="p-4 text-center">
                                                <span class="font-bold text-slate-900 block"><?= format_num_i18n((int)($r['total_deliveries'] ?? 0)) ?></span>
                                                <?php if ((int)$r['active_tasks'] > 0): ?>
                                                    <span class="inline-block mt-0.5 text-[9px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold">
                                                        <?= format_num_i18n($r['active_tasks']) ?> <?= is_bangla() ? 'টি চলমান' : 'active' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-slate-400"><?= is_bangla() ? 'সম্পন্ন' : 'completed' ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4">
                                                <div class="space-y-1">
                                                    <!-- Staff availability badge -->
                                                    <?php 
                                                    $sStatus = $r['staff_status'] ?? 'available';
                                                    $sColor = $sStatus === 'available' ? 'bg-emerald-100 text-emerald-800' : ($sStatus === 'busy' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700');
                                                    ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $sColor ?>">
                                                        <?= $sStatus === 'available' ? __('admin.available') : ($sStatus === 'busy' ? __('admin.busy') : __('admin.offline')) ?>
                                                    </span>
                                                    
                                                    <!-- Account status badge -->
                                                    <?php if ($r['account_status'] === 'suspended'): ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 block w-fit">
                                                            <?= is_bangla() ? 'অ্যাকাউন্ট স্থগিত' : 'Suspended' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="p-4 text-right space-x-1.5">
                                                <!-- Edit Rider Trigger Button -->
                                                <button type="button" onclick="openEditRiderModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)" 
                                                        class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold rounded-lg transition text-xs">
                                                    <i class="fa-solid fa-pen-to-square mr-1"></i><?= __('common.edit') ?>
                                                </button>

                                                <!-- Toggle Suspend/Active Form -->
                                                <form action="riders.php" method="POST" class="inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
                                                    <button type="submit" class="px-2.5 py-1.5 text-xs font-semibold rounded-lg <?= $r['account_status'] === 'active' ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' ?>">
                                                        <?= $r['account_status'] === 'active' ? (is_bangla() ? 'স্থগিত' : 'Suspend') : (is_bangla() ? 'সক্রিয়' : 'Activate') ?>
                                                    </button>
                                                </form>

                                                <!-- Delete Rider Button -->
                                                <form action="riders.php" method="POST" onsubmit="return confirm('<?= is_bangla() ? 'এই রাইডার অ্যাকাউন্টটি কি মুছে ফেলতে চান?' : 'Permanently remove this courier rider account?' ?>');" class="inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_rider">
                                                    <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
                                                    <button type="submit" class="px-2 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700" title="<?= is_bangla() ? 'মুছে ফেলুন' : 'Delete' ?>">
                                                        <i class="fa-regular fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-slate-400">
                                            <?= is_bangla() ? 'কোন রাইডার পাওয়া যায়নি।' : 'No delivery couriers found.' ?>
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

<!-- Interactive Edit Rider Modal -->
<div id="edit-rider-modal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-xl w-full p-6 sm:p-8 space-y-5 animate-scale-in max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-motorcycle text-emerald-600"></i>
                <span><?= is_bangla() ? 'রাইডারের তথ্য সম্পাদনা' : 'Edit Courier Rider Profile' ?></span>
            </h3>
            <button type="button" onclick="closeEditRiderModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="riders.php" method="POST" class="space-y-4 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_rider">
            <input type="hidden" name="user_id" id="modal_r_user_id" value="">
            <input type="hidden" name="profile_id" id="modal_r_profile_id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'রাইডারের নাম' : 'Rider Full Name' ?> *</label>
                    <input type="text" name="name" id="modal_r_name" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ইমেইল অ্যাড্রেস' : 'Email Address' ?> *</label>
                    <input type="email" name="email" id="modal_r_email" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'ফোন নম্বর' : 'Phone Number' ?> *</label>
                    <input type="text" name="phone" id="modal_r_phone" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'লাইসেন্স / এনআইডি' : 'License / NID' ?></label>
                    <input type="text" name="license_number" id="modal_r_license"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'যানবাহনের ধরন' : 'Vehicle Type' ?></label>
                    <select name="vehicle_type" id="modal_r_vehicle" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
                        <option value="Motorcycle">Motorcycle (মোটরসাইকেল)</option>
                        <option value="Bicycle">Bicycle (বাইসাইকেল)</option>
                        <option value="Electric Scooter">Electric Scooter (ই-স্কুটার)</option>
                        <option value="Delivery Van">Delivery Van (ডেলিভারি ভ্যান)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'কাজের জোন / এরিয়া' : 'Operating Delivery Zone' ?></label>
                    <input type="text" name="current_zone" id="modal_r_zone"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'কাজের প্রাপ্যতা (Availability)' : 'Courier Availability' ?></label>
                    <select name="staff_status" id="modal_r_staff_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
                        <option value="available"><?= __('admin.available') ?></option>
                        <option value="busy"><?= __('admin.busy') ?></option>
                        <option value="offline"><?= __('admin.offline') ?></option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1"><?= is_bangla() ? 'অ্যাকাউন্ট স্ট্যাটাস' : 'Account Status' ?></label>
                    <select name="account_status" id="modal_r_account_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-900">
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
                <button type="button" onclick="closeEditRiderModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl">
                    <?= __('common.cancel') ?>
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition">
                    <?= is_bangla() ? 'রাইডার তথ্য সংরক্ষণ করুন' : 'Save Changes' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditRiderModal(r) {
    document.getElementById('modal_r_user_id').value = r.user_id;
    document.getElementById('modal_r_profile_id').value = r.profile_id || '';
    document.getElementById('modal_r_name').value = r.name || '';
    document.getElementById('modal_r_email').value = r.email || '';
    document.getElementById('modal_r_phone').value = r.phone || '';
    document.getElementById('modal_r_vehicle').value = r.vehicle_type || 'Motorcycle';
    document.getElementById('modal_r_zone').value = r.current_zone || 'Dhaka Central';
    document.getElementById('modal_r_license').value = r.license_number || '';
    document.getElementById('modal_r_staff_status').value = r.staff_status || 'available';
    document.getElementById('modal_r_account_status').value = r.account_status || 'active';
    document.getElementById('edit-rider-modal').classList.remove('hidden');
}

function closeEditRiderModal() {
    document.getElementById('edit-rider-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
