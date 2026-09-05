<?php
// File: d:\code\nursery\login.php
// Description: User Login Screen with Demo Role Switcher for quick viva/presentation testing.
// Clean, responsive, and beginner-friendly native PHP.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/helpers/functions.php';

// If user is already logged in, redirect them directly to their role dashboard
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $role = $_SESSION['role_name'] ?? 'customer';
    switch ($role) {
        case 'owner':    header("Location: " . BASE_URL . "/owner/index.php"); exit;
        case 'admin':    header("Location: " . BASE_URL . "/admin/index.php"); exit;
        case 'vendor':   header("Location: " . BASE_URL . "/vendor/index.php"); exit;
        case 'delivery': header("Location: " . BASE_URL . "/delivery/index.php"); exit;
        default:         header("Location: " . BASE_URL . "/customer/index.php"); exit;
    }
}

// Retrieve any flash messages passed from process_login.php
$flashError   = $_SESSION['flash_error'] ?? $_SESSION['error'] ?? '';
$flashSuccess = $_SESSION['flash_success'] ?? $_SESSION['success'] ?? '';
$flashInfo    = $_SESSION['flash_info'] ?? $_SESSION['info'] ?? '';
unset(
    $_SESSION['flash_error'], 
    $_SESSION['flash_success'], 
    $_SESSION['flash_info'], 
    $_SESSION['error'], 
    $_SESSION['success'], 
    $_SESSION['info']
);

$redirectParam = $_GET['redirect'] ?? '';
$pageTitle     = is_bangla() ? 'লগইন' : 'Sign In';

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">
    
    <!-- Ambient Green Glow Background Orbs -->
    <div class="absolute top-0 left-1/4 w-96 h-96 bg-emerald-400/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-teal-400/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
        
        <!-- Brand Logo & Header Title -->
        <div class="text-center">
            <a href="<?= BASE_URL ?>/index.php" class="inline-flex items-center space-x-2.5 group">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-600 via-teal-700 to-emerald-900 flex items-center justify-center text-white shadow-lg shadow-emerald-900/20 group-hover:scale-105 transition">
                    <i class="fa-solid fa-leaf text-2xl text-emerald-200"></i>
                </div>
                <span class="text-2xl font-black text-slate-900 tracking-tight">Smart<span class="text-emerald-600">Nursery</span></span>
            </a>
            <h2 class="mt-4 text-2xl font-black text-slate-900 tracking-tight">
                <?= is_bangla() ? 'আপনার অ্যাকাউন্টে প্রবেশ করুন' : 'Sign in to your account' ?>
            </h2>
            <p class="mt-1 text-xs text-slate-500 font-medium">
                <?= is_bangla() ? 'স্মার্ট নার্সারি ম্যানেজমেন্ট সিস্টেম' : 'Smart Nursery Management System' ?>
            </p>
        </div>

        <!-- Login Card -->
        <div class="mt-8 bg-white py-8 px-6 shadow-xl rounded-3xl sm:px-10 border border-slate-200/80 relative">
            
            <!-- Alert Notifications (Errors / Success) -->
            <?php if (!empty($flashError)): ?>
                <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold flex items-center space-x-2.5">
                    <i class="fa-solid fa-circle-exclamation text-base shrink-0"></i>
                    <span><?= htmlspecialchars($flashError) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashSuccess)): ?>
                <div class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 text-xs font-semibold flex items-center space-x-2.5">
                    <i class="fa-solid fa-circle-check text-base shrink-0 text-emerald-600"></i>
                    <span><?= htmlspecialchars($flashSuccess) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashInfo)): ?>
                <div class="mb-5 p-3.5 bg-sky-50 border border-sky-200 rounded-2xl text-sky-800 text-xs font-semibold flex items-center space-x-2.5">
                    <i class="fa-solid fa-circle-info text-base shrink-0 text-sky-600"></i>
                    <span><?= htmlspecialchars($flashInfo) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form (Submits directly to process_login.php) -->
            <form action="<?= BASE_URL ?>/process_login.php" method="POST" class="space-y-4">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectParam) ?>">

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                        <?= is_bangla() ? 'ইমেইল ঠিকানা' : 'Email Address' ?>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" name="email" id="email" required autocomplete="email"
                               placeholder="user@example.com"
                               class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-emerald-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                    </div>
                </div>

                <!-- Password Input with Show/Hide Toggle -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="password" class="block text-xs font-bold text-slate-700">
                            <?= is_bangla() ? 'পাসওয়ার্ড' : 'Password' ?>
                        </label>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-emerald-500 rounded-xl pl-9 pr-10 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs cursor-pointer">
                            <i id="eye-icon" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-900/20 transition flex items-center justify-center space-x-2 cursor-pointer">
                        <span><?= is_bangla() ? 'লগইন করুন' : 'Sign In' ?></span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </form>

            <!-- Registration Link -->
            <div class="mt-6 text-center text-xs text-slate-500">
                <?= is_bangla() ? 'কোনো অ্যাকাউন্ট নেই?' : "Don't have an account?" ?>
                <a href="<?= BASE_URL ?>/register.php" class="font-bold text-emerald-600 hover:text-emerald-700 underline ml-1">
                    <?= is_bangla() ? 'নতুন অ্যাকাউন্ট খুলুন' : 'Create an account' ?>
                </a>
            </div>

            <!-- ==========================================================
                 DEMO QUICK-LOGIN BUTTONS (Ideal for Viva / Defense)
                 ========================================================== -->
            <div class="mt-8 pt-6 border-t border-slate-100">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 text-center mb-3">
                    <?= is_bangla() ? '⚡ দ্রুত ডেমো লগইন (প্রেজেন্টেশনের জন্য)' : '⚡ Quick Demo Login (For Viva/Testing)' ?>
                </p>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="fillCredentials('alice@gmail.com', 'password123')" class="p-2 rounded-xl bg-slate-50 hover:bg-emerald-50 border border-slate-200 text-[11px] font-bold text-slate-700 text-left transition flex items-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-user text-emerald-600"></i>
                        <span>Customer (Alice)</span>
                    </button>
                    <button type="button" onclick="fillCredentials('greenhaven@nursery.com', 'password123')" class="p-2 rounded-xl bg-slate-50 hover:bg-emerald-50 border border-slate-200 text-[11px] font-bold text-slate-700 text-left transition flex items-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-store text-emerald-600"></i>
                        <span>Vendor (Green Haven)</span>
                    </button>
                    <button type="button" onclick="fillCredentials('admin@nursery.com', 'password123')" class="p-2 rounded-xl bg-slate-50 hover:bg-emerald-50 border border-slate-200 text-[11px] font-bold text-slate-700 text-left transition flex items-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-shield-halved text-emerald-600"></i>
                        <span>Admin</span>
                    </button>
                    <button type="button" onclick="fillCredentials('speedy@nursery.com', 'password123')" class="p-2 rounded-xl bg-slate-50 hover:bg-emerald-50 border border-slate-200 text-[11px] font-bold text-slate-700 text-left transition flex items-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-truck-fast text-emerald-600"></i>
                        <span>Delivery Rider</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Helper to toggle password visibility
function togglePasswordVisibility() {
    const pwd = document.getElementById('password');
    const eye = document.getElementById('eye-icon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        eye.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        pwd.type = 'password';
        eye.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Helper for 1-click Demo credentials auto-fill
function fillCredentials(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
