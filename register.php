<?php
// File: d:\code\nursery\register.php
// Description: User Registration Screen with Role Switcher (Customer vs Nursery Vendor).
// Clean, responsive, and beginner-friendly native PHP.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/helpers/functions.php';

// If user is already logged in, redirect to home
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

$selectedRole = $_GET['role'] ?? 'customer';
$pageTitle    = is_bangla() ? 'অ্যাকাউন্ট তৈরি করুন' : 'Create an Account';

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">
    
    <!-- Ambient Green Glow Background Orbs -->
    <div class="absolute top-0 right-1/4 w-96 h-96 bg-emerald-400/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-1/4 w-96 h-96 bg-teal-400/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="sm:mx-auto sm:w-full sm:max-w-lg relative z-10">
        
        <!-- Brand Logo & Header -->
        <div class="text-center">
            <a href="<?= BASE_URL ?>/index.php" class="inline-flex items-center space-x-2.5 group">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-600 via-teal-700 to-emerald-900 flex items-center justify-center text-white shadow-lg shadow-emerald-900/20 group-hover:scale-105 transition">
                    <i class="fa-solid fa-leaf text-2xl text-emerald-200"></i>
                </div>
                <span class="text-2xl font-black text-slate-900 tracking-tight">Smart<span class="text-emerald-600">Nursery</span></span>
            </a>
            <h2 class="mt-4 text-2xl font-black text-slate-900 tracking-tight">
                <?= is_bangla() ? 'নতুন অ্যাকাউন্ট খুলুন' : 'Join our botanical community' ?>
            </h2>
            <p class="mt-1 text-xs text-slate-500 font-medium">
                <?= is_bangla() ? 'গাছ ক্রেতা অথবা নার্সারি বিক্রেতা হিসেবে নিবন্ধন করুন' : 'Register as a Plant Lover or Nursery Merchant' ?>
            </p>
        </div>

        <!-- Registration Card -->
        <div class="mt-8 bg-white py-8 px-6 shadow-xl rounded-3xl sm:px-10 border border-slate-200/80 relative">
            
            <!-- Alert Notifications (Errors) -->
            <?php if (!empty($flashError)): ?>
                <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold flex items-center space-x-2.5">
                    <i class="fa-solid fa-circle-exclamation text-base shrink-0"></i>
                    <span><?= htmlspecialchars($flashError) ?></span>
                </div>
            <?php endif; ?>

            <!-- Registration Form (Submits directly to process_register.php) -->
            <form action="<?= BASE_URL ?>/process_register.php" method="POST" class="space-y-4">
                
                <!-- Account Role Selection (Customer vs Vendor) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">
                        <?= is_bangla() ? 'অ্যাকাউন্টের ধরন নির্বাচন করুন' : 'Select Account Type' ?>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex items-center p-3 rounded-2xl border cursor-pointer transition has-checked:border-emerald-500 has-checked:bg-emerald-50/50 has-checked:ring-2 has-checked:ring-emerald-500/20">
                            <input type="radio" name="role" value="customer" <?= $selectedRole !== 'vendor' ? 'checked' : '' ?> onchange="toggleRoleFields('customer')" class="text-emerald-600 focus:ring-emerald-500">
                            <div class="ml-2.5">
                                <span class="block text-xs font-black text-slate-800"><?= is_bangla() ? 'গাছ ক্রেতা' : 'Plant Customer' ?></span>
                                <span class="block text-[10px] text-slate-400"><?= is_bangla() ? 'গাছ কিনুন ও যত্ন নিন' : 'Shop plants & get tips' ?></span>
                            </div>
                        </label>

                        <label class="relative flex items-center p-3 rounded-2xl border cursor-pointer transition has-checked:border-emerald-500 has-checked:bg-emerald-50/50 has-checked:ring-2 has-checked:ring-emerald-500/20">
                            <input type="radio" name="role" value="vendor" <?= $selectedRole === 'vendor' ? 'checked' : '' ?> onchange="toggleRoleFields('vendor')" class="text-emerald-600 focus:ring-emerald-500">
                            <div class="ml-2.5">
                                <span class="block text-xs font-black text-slate-800"><?= is_bangla() ? 'নার্সারি বিক্রেতা' : 'Nursery Vendor' ?></span>
                                <span class="block text-[10px] text-slate-400"><?= is_bangla() ? 'গাছ বিক্রি ও ব্যবসা' : 'Sell & manage stock' ?></span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Full Name Input -->
                <div>
                    <label for="name" class="block text-xs font-bold text-slate-700 mb-1">
                        <?= is_bangla() ? 'পূর্ণ নাম' : 'Full Name' ?> *
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <input type="text" name="name" id="name" required placeholder="John Doe"
                               class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-emerald-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                    </div>
                </div>

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                        <?= is_bangla() ? 'ইমেইল ঠিকানা' : 'Email Address' ?> *
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" name="email" id="email" required placeholder="user@example.com"
                               class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-emerald-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                    </div>
                </div>

                <!-- Phone Input -->
                <div>
                    <label for="phone" class="block text-xs font-bold text-slate-700 mb-1">
                        <?= is_bangla() ? 'ফোন নম্বর' : 'Phone Number' ?>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">
                            <i class="fa-solid fa-phone"></i>
                        </span>
                        <input type="text" name="phone" id="phone" placeholder="+880 1700-000000"
                               class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-emerald-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                    </div>
                </div>

                <!-- Vendor-Only Fields: Nursery Name & Address -->
                <div id="vendor-fields" class="<?= $selectedRole === 'vendor' ? '' : 'hidden' ?> space-y-4 p-4 rounded-2xl bg-emerald-50/40 border border-emerald-200">
                    <div>
                        <label for="nursery_name" class="block text-xs font-bold text-emerald-900 mb-1">
                            <?= is_bangla() ? 'নার্সারির বাণিজ্যিক নাম' : 'Nursery Business Name' ?> *
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-emerald-600 text-xs pointer-events-none">
                                <i class="fa-solid fa-store"></i>
                            </span>
                            <input type="text" name="nursery_name" id="nursery_name" placeholder="e.g. Green Valley Botanical Garden"
                                   class="w-full bg-white border border-emerald-300 focus:border-emerald-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label for="nursery_address" class="block text-xs font-bold text-emerald-900 mb-1">
                            <?= is_bangla() ? 'নার্সারির অবস্থান / ঠিকানা' : 'Nursery Address' ?>
                        </label>
                        <input type="text" name="nursery_address" id="nursery_address" placeholder="e.g. Plot 4, Botanical Road, Savar, Dhaka"
                               class="w-full bg-white border border-emerald-300 focus:border-emerald-500 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <!-- Password & Confirm Password Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="password" class="block text-xs font-bold text-slate-700 mb-1">
                            <?= is_bangla() ? 'পাসওয়ার্ড' : 'Password' ?> *
                        </label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required placeholder="••••••••" minlength="6"
                                   class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-emerald-500 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                        </div>
                    </div>

                    <div>
                        <label for="password_confirm" class="block text-xs font-bold text-slate-700 mb-1">
                            <?= is_bangla() ? 'পুনরায় পাসওয়ার্ড' : 'Confirm' ?> *
                        </label>
                        <div class="relative">
                            <input type="password" name="password_confirm" id="password_confirm" required placeholder="••••••••" minlength="6"
                                   class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-emerald-500 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-900/20 transition flex items-center justify-center space-x-2 cursor-pointer">
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span><?= is_bangla() ? 'নিবন্ধন সম্পন্ন করুন' : 'Complete Registration' ?></span>
                    </button>
                </div>
            </form>

            <!-- Login Link -->
            <div class="mt-6 text-center text-xs text-slate-500">
                <?= is_bangla() ? 'ইতোমধ্যে অ্যাকাউন্ট আছে?' : 'Already have an account?' ?>
                <a href="<?= BASE_URL ?>/login.php" class="font-bold text-emerald-600 hover:text-emerald-700 underline ml-1">
                    <?= is_bangla() ? 'লগইন করুন' : 'Sign In instead' ?>
                </a>
            </div>

        </div>
    </div>
</div>

<script>
// Toggle vendor specific fields when user switches between Customer and Vendor
function toggleRoleFields(role) {
    const vendorFields = document.getElementById('vendor-fields');
    const nurseryNameInput = document.getElementById('nursery_name');
    if (role === 'vendor') {
        vendorFields.classList.remove('hidden');
        nurseryNameInput.setAttribute('required', 'required');
    } else {
        vendorFields.classList.add('hidden');
        nurseryNameInput.removeAttribute('required');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
