<?php
// File: d:\code\nursery\process_register.php
// Description: Processes new user registration for both Customers and Nursery Vendors.
// Written in clean, step-by-step beginner PHP with plain-English comments.

// Step 1: Start PHP session for feedback alerts
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Step 2: Include configuration and helper functions
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';

// Step 3: Ensure request is an HTTP POST submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/register.php");
    exit;
}

// Step 4: Capture and sanitize submitted form fields
$roleName        = trim($_POST['role'] ?? 'customer');
$name            = trim($_POST['name'] ?? '');
$email           = trim($_POST['email'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$password        = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

// Vendor specific inputs
$nurseryName     = trim($_POST['nursery_name'] ?? '');
$nurseryAddress  = trim($_POST['nursery_address'] ?? '');

// Customer specific inputs
$shippingAddress = trim($_POST['shipping_address'] ?? '');

// Step 5: Input Validation Checks
if (empty($name) || empty($email) || empty($password)) {
    $_SESSION['flash_error'] = is_bangla() ? 'অনুগ্রহ করে সকল প্রয়োজনীয় তথ্য পূরণ করুন।' : 'Please complete all required fields.';
    header("Location: " . BASE_URL . "/register.php?role=" . urlencode($roleName));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = is_bangla() ? 'একটি সঠিক ইমেইল ঠিকানা প্রদান করুন।' : 'Please enter a valid email address.';
    header("Location: " . BASE_URL . "/register.php?role=" . urlencode($roleName));
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['flash_error'] = is_bangla() ? 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters long.';
    header("Location: " . BASE_URL . "/register.php?role=" . urlencode($roleName));
    exit;
}

if ($password !== $passwordConfirm) {
    $_SESSION['flash_error'] = is_bangla() ? 'পাসওয়ার্ড দুটি মেলেনি।' : 'Passwords do not match. Please retype carefully.';
    header("Location: " . BASE_URL . "/register.php?role=" . urlencode($roleName));
    exit;
}

if ($roleName === 'vendor' && empty($nurseryName)) {
    $_SESSION['flash_error'] = is_bangla() ? 'অনুগ্রহ করে আপনার নার্সারির নাম লিখুন।' : 'Please provide your Nursery Business Name.';
    header("Location: " . BASE_URL . "/register.php?role=vendor");
    exit;
}

try {
    // Step 6: Connect to MySQL database
    $db = get_db();

    // Step 7: Check if email already exists in database
    // Why it is necessary: Ensures every user has a unique email address for login
    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        $_SESSION['flash_error'] = is_bangla() 
            ? 'এই ইমেইল ঠিকানা দিয়ে ইতোমধ্যে একটি অ্যাকাউন্ট খোলা আছে।' 
            : 'An account with this email address already exists. Please log in.';
        header("Location: " . BASE_URL . "/register.php?role=" . urlencode($roleName));
        exit;
    }

    // Step 8: Look up the role ID for the selected role
    $targetRole = ($roleName === 'vendor') ? 'vendor' : 'customer';
    $roleStmt = $db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
    $roleStmt->execute([$targetRole]);
    $roleRow = $roleStmt->fetch();
    $roleId  = $roleRow ? (int)$roleRow['id'] : 4; // 4 = Customer default

    // Step 9: Hash the user's password using standard BCRYPT encryption
    // Why it is necessary: We NEVER store plain text passwords in the database
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Step 10: Set initial account status
    // Customers are active immediately; Vendors require admin verification
    $userStatus = ($targetRole === 'vendor') ? 'pending' : 'active';

    // Step 11: Use Database Transaction for safe multi-table insertion
    // Why it is necessary: If the profile insert fails, the user record is automatically rolled back
    $db->beginTransaction();

    // Insert user record
    $userStmt = $db->prepare("
        INSERT INTO users (role_id, name, email, password_hash, phone, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $userStmt->execute([
        $roleId,
        $name,
        $email,
        $passwordHash,
        $phone,
        $userStatus
    ]);

    $newUserId = (int)$db->lastInsertId();

    // Insert corresponding role profile
    if ($targetRole === 'vendor') {
        $vendorStmt = $db->prepare("
            INSERT INTO vendor_profiles (user_id, nursery_name, address, verification_status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $vendorStmt->execute([
            $newUserId,
            $nurseryName,
            $nurseryAddress ?: 'Dhaka'
        ]);
    } else {
        $customerStmt = $db->prepare("
            INSERT INTO customer_profiles (user_id, shipping_address, created_at)
            VALUES (?, ?, NOW())
        ");
        $customerStmt->execute([
            $newUserId,
            $shippingAddress ?: 'Dhaka'
        ]);
    }

    // Commit transaction permanently to database
    $db->commit();

    // Step 12: Dispatch welcome/onboarding email via SMTP
    try {
        require_once __DIR__ . '/services/Mailer.php';
        Mailer::sendWelcomeEmail([
            'name'         => $name,
            'email'        => $email,
            'role'         => $targetRole,
            'nursery_name' => $nurseryName,
            'phone'        => $phone
        ]);
    } catch (Throwable $mailEx) {
        error_log("Failed to dispatch registration welcome email: " . $mailEx->getMessage());
    }

    // Step 13: Set success notification and redirect to login page
    if ($targetRole === 'vendor') {
        $_SESSION['flash_success'] = is_bangla()
            ? 'নার্সারি রেজিস্ট্রেশন সফল হয়েছে! কনফার্মেশন ইমেইল পাঠানো হয়েছে। অ্যাডমিন অনুমোদনের পর আপনি লগইন করতে পারবেন।'
            : 'Vendor registration submitted successfully! A confirmation email has been sent. You can log in once approved by administration.';
    } else {
        $_SESSION['flash_success'] = is_bangla()
            ? 'অ্যাকাউন্ট সফলভাবে তৈরি হয়েছে! আপনার ইমেইলে একটি স্বাগতম বার্তা পাঠানো হয়েছে। অনুগ্রহ করে লগইন করুন।'
            : 'Account registered successfully! A welcome email has been sent to your inbox. Please log in with your credentials.';
    }

    header("Location: " . BASE_URL . "/login.php");
    exit;

} catch (Exception $e) {
    // If any error occurred, rollback transaction so no incomplete data is stored
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['flash_error'] = 'Registration error: ' . $e->getMessage();
    header("Location: " . BASE_URL . "/register.php?role=" . urlencode($roleName));
    exit;
}
