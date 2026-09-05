<?php
// File: d:\code\nursery\process_login.php
// Description: Processes the user login form submission, verifies password hash, and sets session variables.
// Written in clean, step-by-step beginner PHP with plain-English comments.

// Step 1: Start the PHP session so we can store user login state across pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Step 2: Include configuration and helper functions
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';

// Step 3: Check if the request is an HTTP POST submission
// Why it is necessary: Prevents direct GET access to the processing script
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

// Step 4: Capture and sanitize submitted email and password
$email       = trim($_POST['email'] ?? '');
$password    = $_POST['password'] ?? '';
$redirectUrl = trim($_POST['redirect'] ?? '');

// Step 5: Validate that both fields are filled
if (empty($email) || empty($password)) {
    $_SESSION['flash_error'] = is_bangla() ? 'অনুগ্রহ করে ইমেইল এবং পাসওয়ার্ড দুটিই পূরণ করুন।' : 'Please enter both your email address and password.';
    header("Location: " . BASE_URL . "/login.php" . (!empty($redirectUrl) ? "?redirect=" . urlencode($redirectUrl) : ""));
    exit;
}

try {
    // Step 6: Connect to MySQL database
    $db = get_db();

    // Step 7: Prepare a secure SQL query to find user by email and join their role name
    // Why it is necessary: Using ? prepared statements completely prevents SQL Injection attacks
    $stmt = $db->prepare("
        SELECT u.*, r.name AS role_name, r.display_name AS role_display
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Step 8: Verify if user exists AND if the submitted password matches the encrypted hash in the database
    // Why it is necessary: password_verify safely compares plain password with BCRYPT hash without ever exposing the hash
    if ($user && password_verify($password, $user['password_hash'])) {

        // Step 9: Check if account status is active
        if ($user['status'] === 'suspended') {
            $_SESSION['flash_error'] = is_bangla() ? 'আপনার অ্যাকাউন্টটি স্থগিত করা হয়েছে।' : 'Your account has been suspended by administration.';
            header("Location: " . BASE_URL . "/login.php");
            exit;
        }

        if ($user['status'] === 'pending') {
            $_SESSION['flash_error'] = is_bangla() ? 'আপনার নার্সারি অ্যাকাউন্টটি অনুমোদনের অপেক্ষায় রয়েছে।' : 'Your vendor account registration is pending admin approval.';
            header("Location: " . BASE_URL . "/login.php");
            exit;
        }

        // Step 10: Security: Regenerate session ID to protect against Session Fixation attacks
        session_regenerate_id(true);

        // Step 11: Save user identity into $_SESSION superglobal
        // Why it is necessary: Keeps the user logged in across the website
        $_SESSION['user_id']        = (int)$user['id'];
        $_SESSION['user_name']      = $user['name'];
        $_SESSION['user_email']     = $user['email'];
        $_SESSION['role_name']      = $user['role_name'];
        $_SESSION['user_role']      = $user['role_name'];
        $_SESSION['role_display']   = $user['role_display'];
        $_SESSION['logged_in_time'] = time();

        // Step 12: Set friendly welcome notification
        $_SESSION['flash_success'] = is_bangla() 
            ? "স্বাগতম, {$user['name']}! সফলভাবে লগইন হয়েছে।" 
            : "Welcome back, {$user['name']}! You have logged in successfully.";

        // Step 13: Handle custom redirect URL if user was trying to access a protected page
        if (!empty($redirectUrl)) {
            if (str_starts_with($redirectUrl, 'http')) {
                header("Location: " . $redirectUrl);
            } else {
                header("Location: " . BASE_URL . '/' . ltrim($redirectUrl, '/'));
            }
            exit;
        }

        // Step 14: Redirect user to their role-specific dashboard portal
        switch ($user['role_name']) {
            case 'owner':
                header("Location: " . BASE_URL . "/owner/index.php");
                break;
            case 'admin':
                header("Location: " . BASE_URL . "/admin/index.php");
                break;
            case 'vendor':
                header("Location: " . BASE_URL . "/vendor/index.php");
                break;
            case 'delivery':
                header("Location: " . BASE_URL . "/delivery/index.php");
                break;
            case 'customer':
            default:
                header("Location: " . BASE_URL . "/customer/index.php");
                break;
        }
        exit;

    } else {
        // Step 15: Invalid credentials - return user back to login with error message
        $_SESSION['flash_error'] = is_bangla() 
            ? 'ভুল ইমেইল অথবা পাসওয়ার্ড প্রদান করা হয়েছে।' 
            : 'Invalid email address or password. Please try again.';
        header("Location: " . BASE_URL . "/login.php" . (!empty($redirectUrl) ? "?redirect=" . urlencode($redirectUrl) : ""));
        exit;
    }

} catch (Exception $e) {
    // Step 16: Catch unexpected database errors
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    header("Location: " . BASE_URL . "/login.php");
    exit;
}
