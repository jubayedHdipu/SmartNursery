<?php
// Language & Translation Helper

// Initialize or detect active language
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// 1. Language switch via URL parameter (e.g. ?lang=bn or ?lang=en) or POST
if (isset($_GET['lang']) && in_array(strtolower($_GET['lang']), ['en', 'bn'], true)) {
    $_SESSION['lang'] = strtolower($_GET['lang']);
    if (!headers_sent()) {
        @setcookie('smart_nursery_lang', $_SESSION['lang'], time() + (86400 * 30), '/'); // 30 days cookie
    }
} elseif (isset($_POST['lang']) && in_array(strtolower($_POST['lang']), ['en', 'bn'], true)) {
    $_SESSION['lang'] = strtolower($_POST['lang']);
    if (!headers_sent()) {
        @setcookie('smart_nursery_lang', $_SESSION['lang'], time() + (86400 * 30), '/');
    }
} elseif (!isset($_SESSION['lang'])) {
    if (isset($_COOKIE['smart_nursery_lang']) && in_array(strtolower($_COOKIE['smart_nursery_lang']), ['en', 'bn'], true)) {
        $_SESSION['lang'] = strtolower($_COOKIE['smart_nursery_lang']);
    } else {
        $_SESSION['lang'] = 'en'; // Default to English
    }
}

// Returns the currently active language code ('en' or 'bn')
function current_lang(): string {
    return $_SESSION['lang'] ?? 'en';
}

// Checks if current language is Bangla
function is_bangla(): bool {
    return current_lang() === 'bn';
}

// Sets the active application language
function set_lang(string $lang): void {
    if (in_array(strtolower($lang), ['en', 'bn'], true)) {
        $_SESSION['lang'] = strtolower($lang);
        if (!headers_sent()) {
            @setcookie('smart_nursery_lang', $_SESSION['lang'], time() + (86400 * 30), '/');
        }
    }
}

// Loads and caches translation arrays
function load_translations(string $lang): array {
    static $dictionaries = [];

    if (!isset($dictionaries[$lang])) {
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        if (file_exists($file)) {
            $dictionaries[$lang] = require $file;
        } else {
            $dictionaries[$lang] = [];
        }
    }

    return $dictionaries[$lang];
}

// Translates a key into the active language with optional parameter replacements.
function __(string $key, ?string $default = null, array $replace = []): string {
    $currentLang = current_lang();
    $translations = load_translations($currentLang);

    // Direct key lookup
    $text = $translations[$key] ?? null;

    // Fallback to English if active language doesn't have it
    if ($text === null && $currentLang !== 'en') {
        $enTranslations = load_translations('en');
        $text = $enTranslations[$key] ?? null;
    }

    // Ultimate fallback: clean human-readable text
    if ($text === null) {
        if ($default !== null) {
            $text = $default;
        } else {
            $parts = explode('.', $key);
            $lastPart = end($parts);
            $text = ucwords(str_replace('_', ' ', $lastPart));
        }
    }

    // Handle replacements
    if (!empty($replace)) {
        foreach ($replace as $placeholder => $value) {
            // If in Bangla and value is numeric, convert to Bangla numerals
            if ($currentLang === 'bn' && is_numeric($value)) {
                $value = bn_num($value);
            }
            $text = str_replace($placeholder, (string)$value, $text);
        }
    }

    return $text;
}

// Converts English numerals (0-9) to Bangla numerals (০-৯)
function bn_num($number): string {
    $enDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $bnDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    return str_replace($enDigits, $bnDigits, (string)$number);
}

// Formats a localized number depending on current language
function format_num_i18n($number): string {
    return is_bangla() ? bn_num($number) : (string)$number;
}

// Generates current URL with updated ?lang= query parameter for language switcher
function lang_switch_url(string $targetLang): string {
    $targetLang = strtolower($targetLang);
    $params = $_GET ?? [];
    $params['lang'] = $targetLang;
    $queryString = http_build_query($params);

    $reqUri = $_SERVER['REQUEST_URI'] ?? '';
    $uri = strtok($reqUri, '?');
    
    if (empty($uri)) {
        $uri = basename($_SERVER['PHP_SELF'] ?? 'index.php');
    }

    return htmlspecialchars($uri . '?' . $queryString, ENT_QUOTES, 'UTF-8');
}
