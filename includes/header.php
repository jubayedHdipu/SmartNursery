<?php
// Global Header

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifications.php';

$siteName = get_setting('site_name', APP_NAME);
$siteTagline = get_setting('site_tagline', APP_TAGLINE);
$currentUser = current_user();
$cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
$unreadNotifCount = $currentUser ? count_unread_notifications($currentUser['id']) : 0;
$notifications = $currentUser ? get_user_notifications($currentUser['id'], 5) : [];
$currency = get_setting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>" class="min-h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover healthy plants from verified nurseries, match with Smart Botanical Matcher, and track orders safely.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/favicon.svg">
    
    <!-- Google Fonts (Outfit, Inter & Hind Siliguri for flawless Bengali typography) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        emerald: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                            950: '#022c22',
                        }
                    },
                    fontFamily: {
                        sans: ['Hind Siliguri', 'Outfit', 'Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Custom Botanical Stylesheet (with automatic cache busting) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css?v=<?= file_exists(ROOT_PATH . '/assets/css/custom.css') ? filemtime(ROOT_PATH . '/assets/css/custom.css') : time() ?>">
    
    <!-- Page Specific Injections -->
    <?php if (isset($extraHead)) echo $extraHead; ?>

    <!-- Global JS Config Token -->
    <script>
        window.SMART_NURSERY_CONFIG = {
            baseUrl: "<?= BASE_URL ?>",
            userId: <?= $currentUser ? $currentUser['id'] : 'null' ?>,
            userRole: "<?= $currentUser ? $currentUser['role_name'] : '' ?>",
            currency: "<?= $currency ?>",
            lang: "<?= current_lang() ?>"
        };
    </script>
</head>
<body class="min-h-screen flex flex-col antialiased text-slate-800 bg-slate-50 selection:bg-emerald-500 selection:text-white">
<?php require_once __DIR__ . '/toast.php'; ?>
