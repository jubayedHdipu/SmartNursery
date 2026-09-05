<?php
// File: d:\code\nursery\helpers\check_auth.php
// Description: Simple authentication & role guard functions to protect dashboard pages.
// Each protected page includes this file and calls one simple line at the top.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

// ==============================================================================
// 1. BASIC LOGIN CHECK
// What it does: Checks if the user is logged in.
// Why it is necessary: Prevents unauthenticated guests from viewing private pages.
// Usage: check_logged_in();
// ==============================================================================
function check_logged_in(): void {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $_SESSION['flash_error'] = 'Please log in to access this page.';
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        header("Location: " . BASE_URL . "/login.php?redirect=" . urlencode($currentUrl));
        exit;
    }
}

// ==============================================================================
// 2. ROLE-BASED ACCESS GUARDS
// What they do: Verify that the logged-in user possesses the required role.
// Why it is necessary: Ensures customers cannot enter admin panels, vendors only see their store, etc.
// ==============================================================================

// Customer Page Guard
function check_customer_auth(): void {
    check_logged_in();
    $role = $_SESSION['role_name'] ?? '';
    if ($role !== 'customer') {
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }
}

// Nursery Vendor Page Guard
function check_vendor_auth(): void {
    check_logged_in();
    $role = $_SESSION['role_name'] ?? '';
    if ($role !== 'vendor') {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

// Admin Page Guard (Admins & Owners can access)
function check_admin_auth(): void {
    check_logged_in();
    $role = $_SESSION['role_name'] ?? '';
    if ($role !== 'admin' && $role !== 'owner') {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

// Delivery Rider Page Guard
function check_delivery_auth(): void {
    check_logged_in();
    $role = $_SESSION['role_name'] ?? '';
    if ($role !== 'delivery') {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

// System Owner Page Guard
function check_owner_auth(): void {
    check_logged_in();
    $role = $_SESSION['role_name'] ?? '';
    if ($role !== 'owner') {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}
