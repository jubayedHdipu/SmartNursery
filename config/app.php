<?php
// Application Configuration

// 1. Session Initialization & Cookie Security

// PHP sessions store user data (e.g. cart, login state) on the server.
// Here we enforce HTTP-Only cookies so client-side JavaScript cannot steal session IDs.
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', 1); // Protects session cookie from XSS attacks
        ini_set('session.use_only_cookies', 1); // Prevents session fixation via URL query strings
        session_start();
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
    } elseif (PHP_SAPI === 'cli' && session_id() === '') {
        @session_start();
    }
}

// 2. Timezone Settings

date_default_timezone_set('Asia/Dhaka'); // GMT+6 timezone for accurate order timestamps

// Centralized Language & Translation Management
require_once __DIR__ . '/../helpers/languages.php';

// 3. Application Metadata Constants

define('APP_NAME', 'Smart Nursery Ecosystem');
define('APP_TAGLINE', 'Curated Botanical Wonders & Smart Plant Care');
define('APP_VERSION', '1.0.0');

// Filesystem paths on the server
define('ROOT_PATH', dirname(__DIR__));
define('UPLOADS_PATH', ROOT_PATH . '/assets/images');
define('IMAGES_PATH', ROOT_PATH . '/assets/images');

// 4. Dynamic BASE_URL Auto-Detection

// This allows the app to work seamlessly out-of-the-box in any local or live folder.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Determine root directory from the current executing script
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = dirname($scriptName);
if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
    $scriptDir = '';
}

// Strip role/submodule directories (e.g., /admin, /vendor, /customer, /payment) to find the application's root web path
$basePath = preg_replace('#/(admin|vendor|customer|delivery|owner|includes|helpers|config|payment|services|database|tests)$#i', '', $scriptDir);
$baseUrl = rtrim($protocol . $host . '/' . ltrim($basePath, '/'), '/');
define('BASE_URL', $baseUrl);

// 6. Production-Ready Error Logging

// In development/production, log errors cleanly to a file instead of breaking user UI
error_reporting(E_ALL);
ini_set('display_errors', 0);

