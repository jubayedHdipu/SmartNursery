<?php
// ============================================================
// FILE: helpers/functions.php
// PURPOSE: Core helper functions for formatting, security,
//          badges, and general utility tasks across the project.
// ============================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

// ============================================================
// 1. INPUT & STRING SANITIZATION
// What it does: Escapes special characters to prevent XSS attacks.
// ============================================================
function sanitize($data): string {
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// 2. PRODUCT & CATEGORY LOCALIZATION
// What they do: Return the Bangla or English name depending on active language.
// ============================================================
function product_display_name(array $product): string {
    if (is_bangla() && !empty($product['name_bn'])) {
        return $product['name_bn'];
    }
    if (isset($product['name'])) {
        return $product['name'];
    }
    return '';
}

function category_display_name($category): string {
    $name = '';
    if (is_array($category)) {
        if (is_bangla() && !empty($category['name_bn'])) {
            return $category['name_bn'];
        }
        $name = $category['name'] ?? '';
    } else {
        $name = (string)$category;
    }

    if (!is_bangla()) {
        return $name;
    }

    $map = [
        'Indoor Foliage'        => 'ইনডোর পাতা বাহার',
        'Air Purifying Plants'  => 'বাতাস পরিশোধক গাছ',
        'Air Purifying'         => 'বাতাস পরিশোধক গাছ',
        'Succulents & Cacti'    => 'সাকুলেন্ট ও ক্যাকটাস',
        'Flowering Beauties'    => 'ফুল গাছ ও সৌন্দর্য',
        'Flowering Houseplants' => 'ফুল গাছ ও সৌন্দর্য',
        'Bonsai & Exotics'      => 'বনসাই ও দুর্লভ প্রজাতি',
        'Bonsai & Trees'        => 'বনসাই ও দুর্লভ প্রজাতি',
        'Medicinal & Herbs'     => 'ভেষজ ও ঔষধি গাছ',
        'Herbs & Medicinal'     => 'ভেষজ ও ঔষধি গাছ',
        'Pots & Plant Care'     => 'টব ও পরিচর্যার সরঞ্জাম',
        'Pots & Planters'       => 'টব ও প্ল্যান্টার',
        'Orchids'               => 'অর্কিড সংগ্রহ'
    ];

    if (isset($map[$name])) {
        return $map[$name];
    }
    return $name;
}

// ============================================================
// 3. CATEGORY ICONS
// What it does: Returns the matching FontAwesome icon class for a category.
// ============================================================
function category_icon($nameOrId): string {
    $str = strtolower(trim((string)$nameOrId));

    if (strpos($str, 'indoor') !== false || strpos($str, 'foliage') !== false || $str === '1') {
        return 'fa-solid fa-leaf';
    }
    if (strpos($str, 'purifying') !== false || strpos($str, 'air') !== false || $str === '2') {
        return 'fa-solid fa-wind';
    }
    if (strpos($str, 'succulent') !== false || strpos($str, 'cacti') !== false || strpos($str, 'cactus') !== false || $str === '3') {
        return 'fa-solid fa-sun';
    }
    if (strpos($str, 'flower') !== false || strpos($str, 'flowering') !== false || $str === '4') {
        return 'fa-solid fa-spa';
    }
    if (strpos($str, 'bonsai') !== false || strpos($str, 'exotics') !== false || strpos($str, 'tree') !== false || $str === '5') {
        return 'fa-solid fa-tree';
    }
    if (strpos($str, 'medicinal') !== false || strpos($str, 'herb') !== false || $str === '6') {
        return 'fa-solid fa-mortar-pestle';
    }
    if (strpos($str, 'pot') !== false || strpos($str, 'planter') !== false || strpos($str, 'care') !== false || $str === '7') {
        return 'fa-solid fa-bucket';
    }
    if (strpos($str, 'orchid') !== false || $str === '8') {
        return 'fa-solid fa-seedling';
    }
    return 'fa-solid fa-leaf';
}

// ============================================================
// 4. PLANT CARE TRANSLATIONS
// ============================================================
function sunlight_i18n(string $sunlight): string {
    if (!is_bangla()) {
        return $sunlight;
    }
    $clean = strtolower(trim($sunlight));
    if ($clean === 'bright indirect') {
        return __('sunlight.bright_indirect', 'উজ্জ্বল পরোক্ষ আলো');
    }
    if ($clean === 'full sun' || $clean === 'direct' || $clean === 'direct sun') {
        return __('sunlight.direct', 'সরাসরি রোদ');
    }
    if ($clean === 'partial shade' || $clean === 'partial sun') {
        return __('sunlight.partial_shade', 'আংশিক ছায়া');
    }
    if ($clean === 'low light' || $clean === 'low') {
        return __('sunlight.low_light', 'কম আলো / ছায়া');
    }
    return $sunlight;
}

function water_i18n(string $water): string {
    if (!is_bangla()) {
        return $water;
    }
    $clean = strtolower(trim($water));
    if ($clean === 'daily') {
        return __('water.daily', 'প্রতিদিন');
    }
    if ($clean === 'weekly') {
        return __('water.weekly', 'সপ্তাহে ১-২ বার');
    }
    if ($clean === 'twice a week') {
        return 'সপ্তাহে ২ বার';
    }
    if ($clean === 'bi-weekly' || $clean === 'biweekly') {
        return __('water.biweekly', 'দুই সপ্তাহে একবার');
    }
    if ($clean === 'monthly') {
        return __('water.monthly', 'মাসে একবার');
    }
    return $water;
}

function indoor_outdoor_i18n(string $io): string {
    if (!is_bangla()) {
        return $io;
    }
    $clean = strtolower(trim($io));
    if ($clean === 'indoor') {
        return 'ইনডোর (ঘরের ভেতর)';
    }
    if ($clean === 'outdoor') {
        return 'আউটডোর (বাইরে)';
    }
    if ($clean === 'both') {
        return 'ইনডোর ও আউটডোর';
    }
    return $io;
}

// ============================================================
// 5. VENDOR DETAILS LOCALIZATION
// ============================================================
function vendor_display_name($vendor): string {
    if (is_array($vendor)) {
        if (is_bangla() && !empty($vendor['nursery_name_bn'])) {
            return $vendor['nursery_name_bn'];
        }
        $name = $vendor['nursery_name'] ?? '';
    } else {
        $name = (string)$vendor;
    }

    if (!is_bangla()) {
        return $name;
    }

    $map = [
        'Green Haven Botanicals Ltd.' => 'গ্রিন হেভেন বোটানিক্যালস লি.',
        'Green Haven Nursery'         => 'গ্রিন হেভেন নার্সারি',
        'Urban Jungle Nursery'        => 'আরবান জঙ্গল নার্সারি'
    ];

    if (isset($map[$name])) {
        return $map[$name];
    }
    return $name;
}

function vendor_display_address($vendor): string {
    if (is_array($vendor)) {
        if (is_bangla() && !empty($vendor['address_bn'])) {
            return $vendor['address_bn'];
        }
        $address = $vendor['address'] ?? '';
    } else {
        $address = (string)$vendor;
    }

    if (!is_bangla()) {
        return $address;
    }

    $map = [
        'Plot 14, Road 7, Dhanmondi' => 'প্লট ১৪, রোড ৭, ধানমন্ডি, ঢাকা',
        'House 22, Road 11, Banani'  => 'বাড়ি ২২, রোড ১১, বনানী, ঢাকা',
        'Dhaka, Bangladesh'          => 'ঢাকা, বাংলাদেশ'
    ];

    if (isset($map[$address])) {
        return $map[$address];
    }
    return $address;
}

// ============================================================
// 6. NAVIGATION & REDIRECT HELPERS
// ============================================================
function url_with(array $params, ?string $base = null): string {
    $current = $_GET;
    $merged = array_merge($current, $params);
    $qs = http_build_query($merged);
    
    $path = $base;
    if ($path === null) {
        $path = basename($_SERVER['PHP_SELF'] ?? 'shop.php');
    }
    
    if (!empty($qs)) {
        return $path . '?' . $qs;
    }
    return $path;
}

function redirect(string $path): void {
    if (strpos($path, 'http') === 0) {
        header("Location: " . $path);
    } else {
        header("Location: " . BASE_URL . '/' . ltrim($path, '/'));
    }
    exit;
}

// ============================================================
// 7. FLASH MESSAGES (Session-based 1-time alerts)
// ============================================================
function set_flash(string $type, string $message): void {
    $_SESSION['flash_' . $type] = $message;
}

function get_flash(string $type): ?string {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

// ============================================================
// 8. FORMATTING HELPERS (Currency, Date, Relative Time)
// ============================================================
function format_currency($amount): string {
    $symbol = get_setting('currency_symbol', '৳');
    $formatted = number_format((float)$amount, 2);
    if (is_bangla()) {
        $formatted = bn_num($formatted);
    }
    return $symbol . ' ' . $formatted;
}

function format_date(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (empty($datetime)) {
        return is_bangla() ? 'প্রযোজ্য নয়' : 'N/A';
    }
    $timestamp = strtotime($datetime);
    $formatted = date($format, $timestamp);
    if (is_bangla()) {
        $formatted = bn_num($formatted);
    }
    return $formatted;
}

function time_ago($time): string {
    $timestamp = is_numeric($time) ? (int)$time : strtotime($time);
    $diff = time() - $timestamp;

    if (is_bangla()) {
        if ($diff < 60) {
            return 'এইমাত্র';
        }
        if ($diff < 3600) {
            $mins = round($diff / 60);
            return bn_num($mins) . ' মিনিট আগে';
        }
        if ($diff < 86400) {
            $hours = round($diff / 3600);
            return bn_num($hours) . ' ঘণ্টা আগে';
        }
        if ($diff < 604800) {
            $days = round($diff / 86400);
            return bn_num($days) . ' দিন আগে';
        }
        return bn_num(date('d/m/Y', $timestamp));
    }

    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 604800) {
        $days = round($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    return date('M j, Y', $timestamp);
}

// ============================================================
// 9. SYSTEM SETTINGS & DYNAMIC CONTENT
// ============================================================
$GLOBALS['_system_settings_cache'] = null;

function get_setting(string $key, string $default = ''): string {
    if ($GLOBALS['_system_settings_cache'] === null) {
        $GLOBALS['_system_settings_cache'] = [];
        try {
            $db = get_db();
            $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $GLOBALS['_system_settings_cache'][$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            return $default;
        }
    }

    if (isset($GLOBALS['_system_settings_cache'][$key])) {
        return $GLOBALS['_system_settings_cache'][$key];
    }
    return $default;
}

$GLOBALS['_dynamic_contents_cache'] = null;

function get_content(string $key, string $default = ''): string {
    if ($GLOBALS['_dynamic_contents_cache'] === null) {
        $GLOBALS['_dynamic_contents_cache'] = [];
        try {
            $db = get_db();
            $stmt = $db->query("SELECT content_key, content_value FROM dynamic_contents");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $GLOBALS['_dynamic_contents_cache'][$row['content_key']] = $row['content_value'];
            }
        } catch (Exception $e) {
            return $default;
        }
    }

    if (isset($GLOBALS['_dynamic_contents_cache'][$key])) {
        return $GLOBALS['_dynamic_contents_cache'][$key];
    }
    return $default;
}

// ============================================================
// 10. AUDIT LOGGING
// What it does: Records important actions into the audit_logs table.
// ============================================================
function log_audit(string $action, string $entityType, $entityId = null, ?string $details = null): void {
    try {
        $db = get_db();
        $user = current_user();
        $userId = $user ? (int)$user['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, details)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            (string)$entityId,
            $ip,
            $details
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

// ============================================================
// 11. UI BADGES (Care, Sunlight, Order Status)
// ============================================================
function render_care_badge(string $level): string {
    $clean = strtolower($level);
    if ($clean === 'easy') {
        $label = __('care.easy', 'Easy Care');
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="fa-solid fa-leaf mr-1 text-[10px]"></i> ' . htmlspecialchars($label) . '</span>';
    }
    if ($clean === 'moderate') {
        $label = __('care.moderate', 'Moderate Care');
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200"><i class="fa-solid fa-droplet mr-1 text-[10px]"></i> ' . htmlspecialchars($label) . '</span>';
    }
    if ($clean === 'expert') {
        $label = __('care.expert', 'Expert Care');
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 border border-rose-200"><i class="fa-solid fa-crown mr-1 text-[10px]"></i> ' . htmlspecialchars($label) . '</span>';
    }
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">' . htmlspecialchars($level) . '</span>';
}

function render_sunlight_badge(string $sunlight): string {
    if ($sunlight === 'Full Sun') {
        $label = __('sunlight.direct', 'Full Sun');
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-900 border border-amber-300"><i class="fa-solid fa-sun mr-1 text-amber-600 text-[10px]"></i> ' . htmlspecialchars($label) . '</span>';
    }
    if ($sunlight === 'Partial Shade') {
        $label = __('sunlight.partial_shade', 'Partial Shade');
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-900 border border-teal-300"><i class="fa-solid fa-cloud-sun mr-1 text-teal-600 text-[10px]"></i> ' . htmlspecialchars($label) . '</span>';
    }
    if ($sunlight === 'Low Light') {
        $label = __('sunlight.low_light', 'Low Light');
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 border border-slate-300"><i class="fa-solid fa-moon mr-1 text-slate-600 text-[10px]"></i> ' . htmlspecialchars($label) . '</span>';
    }
    // Bright Indirect default
    $label = __('sunlight.bright_indirect', 'Bright Indirect');
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-lime-100 text-lime-900 border border-lime-300"><i class="fa-solid fa-sun-plant-wilt mr-1 text-lime-600 text-[10px]"></i> ' . htmlspecialchars($label) . '</span>';
}

function render_order_status_badge(string $status): string {
    $colors = [
        'Pending'              => 'bg-amber-100 text-amber-800 border-amber-200',
        'Vendor Accepted'      => 'bg-sky-100 text-sky-800 border-sky-200',
        'Vendor Rejected'      => 'bg-rose-100 text-rose-800 border-rose-200',
        'Cancelled'            => 'bg-slate-200 text-slate-700 border-slate-300',
        'Preparing'            => 'bg-indigo-100 text-indigo-800 border-indigo-200',
        'Ready for Pickup'     => 'bg-purple-100 text-purple-800 border-purple-200',
        'Assigned to Delivery' => 'bg-blue-100 text-blue-800 border-blue-200',
        'Picked Up'            => 'bg-teal-100 text-teal-800 border-teal-200',
        'Out for Delivery'     => 'bg-orange-100 text-orange-800 border-orange-200',
        'Delivered'            => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-semibold'
    ];

    $style = $colors[$status] ?? 'bg-slate-100 text-slate-800 border-slate-200';
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border ' . $style . '">' . htmlspecialchars($status) . '</span>';
}

// ============================================================
// 12. IMAGE URL RESOLVERS (With fallback to default images)
// ============================================================
function get_image_url(?string $path, ?string $fallback = null): string {
    $defaultFallback = BASE_URL . '/assets/images/plants/default-plant.jpg';
    $fallbackUrl = $fallback ? $fallback : $defaultFallback;

    if (empty($path)) {
        return $fallbackUrl;
    }

    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }

    if (strpos($path, '/') === 0) {
        return BASE_URL . $path;
    }

    if (file_exists(ROOT_PATH . '/' . $path)) {
        return BASE_URL . '/' . ltrim($path, '/');
    }

    if (file_exists(ROOT_PATH . '/assets/images/' . $path)) {
        return BASE_URL . '/assets/images/' . ltrim($path, '/');
    }

    if (file_exists(ROOT_PATH . '/assets/images/plants/' . $path)) {
        return BASE_URL . '/assets/images/plants/' . ltrim($path, '/');
    }

    if (file_exists(ROOT_PATH . '/assets/images/nurseries/' . $path)) {
        return BASE_URL . '/assets/images/nurseries/' . ltrim($path, '/');
    }

    if (file_exists(ROOT_PATH . '/assets/images/categories/' . $path)) {
        return BASE_URL . '/assets/images/categories/' . ltrim($path, '/');
    }

    if (file_exists(ROOT_PATH . '/assets/images/branding/' . $path)) {
        return BASE_URL . '/assets/images/branding/' . ltrim($path, '/');
    }

    if (file_exists(ROOT_PATH . '/assets/images/avatars/' . $path)) {
        return BASE_URL . '/assets/images/avatars/' . ltrim($path, '/');
    }

    return $fallbackUrl;
}

function get_avatar_url(?string $path): string {
    if (!empty($path) && (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0)) {
        return $path;
    }
    if (!empty($path) && file_exists(ROOT_PATH . '/' . $path)) {
        return BASE_URL . '/' . ltrim($path, '/');
    }
    if (!empty($path) && file_exists(ROOT_PATH . '/assets/images/avatars/' . $path)) {
        return BASE_URL . '/assets/images/avatars/' . ltrim($path, '/');
    }
    return BASE_URL . '/assets/images/avatars/default-avatar.svg';
}
