<?php
// File: d:\code\nursery\logout.php
// Description: Securely terminates the current user session and redirects to the login screen.
// Beginner-friendly procedural PHP with educational comments.

// Step 1: Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Step 2: Include configuration constants
require_once __DIR__ . '/config/app.php';

// Step 3: Clear all session variables in memory
// Why it is necessary: Removes user_id, user_name, role_name from server memory
$_SESSION = [];
session_unset();

// Step 4: Delete the session cookie from the browser if cookies are used
// Why it is necessary: Completely invalidates the session identifier on client side
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Step 5: Destroy the session on the server
session_destroy();

// Step 6: Start a clean new session to show a friendly logout message
session_start();
$_SESSION['flash_info'] = 'You have logged out safely. See you again!';

// Step 7: Redirect user to the login screen
header("Location: " . BASE_URL . "/login.php");
exit;
