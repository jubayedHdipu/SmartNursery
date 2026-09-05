<?php
/**
 * Smart Nursery Management System - Spatial Scrollytelling Botanical Walkthrough
 * An immersive 5-Zone greenhouse journey powered by GSAP, ScrollTrigger, Swiper 11, and PHP/MySQL.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

$pageTitle = __('home.hero_title_highlight') . ' • ' . __('app.name');

// 1. Injected Header Assets (GSAP, Swiper, Lucide, and Spatial CSS)
$extraHead = '
    <!-- Swiper 11 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <!-- AOS 2.3 CSS -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <!-- GLightbox CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">
    <!-- Ultra-Modern Botanical Theme CSS -->
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/ultra-modern-home.css?v=' . (file_exists(__DIR__ . '/assets/css/ultra-modern-home.css') ? filemtime(__DIR__ . '/assets/css/ultra-modern-home.css') : time()) . '">
    <!-- Botanical 3D Showcase & Specimen CSS -->
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/botanical-showcase.css?v=' . (file_exists(__DIR__ . '/assets/css/botanical-showcase.css') ? filemtime(__DIR__ . '/assets/css/botanical-showcase.css') : time()) . '">
    <!-- Spatial Scrollytelling Greenhouse CSS -->
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/spatial-greenhouse.css?v=' . (file_exists(__DIR__ . '/assets/css/spatial-greenhouse.css') ? filemtime(__DIR__ . '/assets/css/spatial-greenhouse.css') : time()) . '">
';

// 2. Injected Footer Scripts (GSAP & ScrollTrigger)
$extraFooter = '
    <!-- GSAP 3.12.5 & ScrollTrigger via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <!-- Swiper 11 JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- Vanilla-Tilt 3D JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.8.1/vanilla-tilt.min.js"></script>
    <!-- AOS JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- GLightbox JS -->
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Ultra-Modern Home Interactions -->
    <script src="' . BASE_URL . '/assets/js/home-interactions.js?v=' . (file_exists(__DIR__ . '/assets/js/home-interactions.js') ? filemtime(__DIR__ . '/assets/js/home-interactions.js') : time()) . '"></script>
    <!-- Botanical Gallery & Specimen Interaction JS -->
    <script src="' . BASE_URL . '/assets/js/gallery-interactions.js?v=' . (file_exists(__DIR__ . '/assets/js/gallery-interactions.js') ? filemtime(__DIR__ . '/assets/js/gallery-interactions.js') : time()) . '"></script>
    <!-- Spatial Scrollytelling Greenhouse Walkthrough JS -->
    <script src="' . BASE_URL . '/assets/js/greenhouse-walkthrough.js?v=' . (file_exists(__DIR__ . '/assets/js/greenhouse-walkthrough.js') ? filemtime(__DIR__ . '/assets/js/greenhouse-walkthrough.js') : time()) . '"></script>
';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$db = get_db();
$isBn = is_bangla();

// Fetch Active Categories
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY id ASC LIMIT 8")->fetchAll();

// Fetch Featured In-Stock Plants with Nursery Details
$featuredStmt = $db->query("
    SELECT p.*, p.name_bn, c.name AS category_name, c.name_bn AS category_name_bn, v.nursery_name, v.nursery_name_bn,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN vendor_profiles v ON p.vendor_id = v.id
    WHERE p.status = 'approved' AND p.is_featured = 1 AND p.stock_quantity > 0
    ORDER BY p.id ASC
    LIMIT 9
");
$featuredProducts = $featuredStmt->fetchAll();

// Pavilion Curated Specimens for Zone 02 3D Coverflow
$pavilionPlants = [
    [
        'id' => 1,
        'title' => $isBn ? 'মনস্টেরা ডেলিসিওসা' : 'Monstera Deliciosa',
        'scientific' => 'Monstera deliciosa',
        'nursery' => $isBn ? 'গ্রিন হেভেন নার্সারি' : 'Green Haven Nursery',
        'price' => '৳750',
        'stock' => 4,
        'sunlight' => 'Bright Indirect',
        'water' => '1x / Wk',
        'oxygen' => '99.4%',
        'img' => 'assets/images/plants/monstera.jpg'
    ],
    [
        'id' => 2,
        'title' => $isBn ? 'ফিডল লিফ ফিগ' : 'Fiddle Leaf Fig',
        'scientific' => 'Ficus lyrata',
        'nursery' => $isBn ? 'গুলশান বোটানিক্যাল স্টুডিও' : 'Gulshan Botanical Studio',
        'price' => '৳1,450',
        'stock' => 2,
        'sunlight' => 'High Lux',
        'water' => '1x / 10d',
        'oxygen' => '98.2%',
        'img' => 'assets/images/plants/fiddle-leaf-fig.jpg'
    ],
    [
        'id' => 3,
        'title' => $isBn ? 'ক্যালাথিয়া অরবিফোলিয়া' : 'Calathea Orbifolia',
        'scientific' => 'Goeppertia orbifolia',
        'nursery' => $isBn ? 'সিলেট ফার্ন নার্সারি' : 'Sylhet Fern Nursery',
        'price' => '৳920',
        'stock' => 5,
        'sunlight' => 'Filtered Light',
        'water' => 'Moist Soil',
        'oxygen' => '97.8%',
        'img' => 'assets/images/plants/calathea-orbifolia.jpg'
    ],
    [
        'id' => 4,
        'title' => $isBn ? 'স্নেক প্ল্যান্ট লরেন্টি' : 'Sansevieria Laurentii',
        'scientific' => 'Sansevieria trifasciata',
        'nursery' => $isBn ? 'গাজীপুর পিউরিফাইং নার্সারি' : 'Gazipur Purifying Nursery',
        'price' => '৳450',
        'stock' => 8,
        'sunlight' => 'Low to Bright',
        'water' => '1x / 14d',
        'oxygen' => '99.8%',
        'img' => 'assets/images/plants/snake-plant.jpg'
    ],
    [
        'id' => 5,
        'title' => $isBn ? 'রয়াল পার্পল অর্কিড' : 'Royal Purple Orchid',
        'scientific' => 'Phalaenopsis amabilis',
        'nursery' => $isBn ? 'বরিশাল ফ্লোরা নার্সারি' : 'Barisal Flora Nursery',
        'price' => '৳650',
        'stock' => 3,
        'sunlight' => 'Medium Sun',
        'water' => '1x / Wk',
        'oxygen' => '95.0%',
        'img' => 'assets/images/plants/purple-orchid.jpg'
    ],
    [
        'id' => 6,
        'title' => $isBn ? 'বটবৃক্ষ প্রাচীন বনসাই' : 'Banyan Heritage Bonsai',
        'scientific' => 'Ficus microcarpa',
        'nursery' => $isBn ? 'বগুড়া বনসাই সেন্টার' : 'Bogra Bonsai Center',
        'price' => '৳3,200',
        'stock' => 1,
        'sunlight' => 'Direct Sun',
        'water' => 'Daily Mist',
        'oxygen' => '96.5%',
        'img' => 'assets/images/plants/banyan-bonsai.jpg'
    ]
];

// Top 5 Verified Nurseries Based on Sales Volume & Ratings
$topNurseries = [
    [
        'id' => 1,
        'rank' => '01',
        'name' => $isBn ? 'গ্রিন হেভেন বোটানিক্যালস' : 'Green Haven Botanicals',
        'location' => $isBn ? 'ধানমন্ডি ও গাজীপুর ক্লাইমেট গ্রিনহাউজ' : 'Dhanmondi & Gazipur Smart Greenhouse',
        'tag' => $isBn ? '🔥 টপ সেলার • ১২৪০+ ডেলিভারি' : '🔥 Top Seller • 1,240+ Orders',
        'rating' => '4.9★ (1,240 Reviews)',
        'specialty' => $isBn ? 'স্মার্ট ক্লাইমেট ইনডোর হাউসপ্ল্যান্ট ও মনস্টেরা' : 'IoT-Monitored Indoor Foliage, Variegated Monstera & Air-Purifiers',
        'species' => '120+ Botanical Cultivars',
        'image' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?q=80&w=1200&auto=format&fit=crop',
        'fallback' => 'assets/images/nurseries/green-haven-banner.jpg',
        'tags' => ['Monstera', 'Fiddle Leaf', 'Air-Purifiers']
    ],
    [
        'id' => 2,
        'rank' => '02',
        'name' => $isBn ? 'গুলশান বোটানিক্যাল স্টুডিও' : 'Gulshan Botanical Studio',
        'location' => $isBn ? 'গুলশান-২, ঢাকা' : 'Gulshan-2, Dhaka',
        'tag' => $isBn ? '💎 লাক্সারি ভ্যারাইটি • ৯৮০+ ডেলিভারি' : '💎 Luxury Cultivars • 980+ Orders',
        'rating' => '5.0★ (890 Reviews)',
        'specialty' => $isBn ? 'দুর্লভ ভ্যারিগেটেড কালেকশন ও আর্কিটেকচারাল লিভিং আর্ট' : 'Rare Variegated Cuts, Philodendrons & Architectural Plant Sculptures',
        'species' => '85+ Premium Cultivars',
        'image' => 'https://images.unsplash.com/photo-1600411833196-7c1f6b1a8b90?q=80&w=1200&auto=format&fit=crop',
        'fallback' => 'assets/images/nurseries/gulshan-studio-banner.jpg',
        'tags' => ['Pink Princess', 'Rare Exotics', 'Living Art']
    ],
    [
        'id' => 3,
        'rank' => '03',
        'name' => $isBn ? 'আরবান জঙ্গল নার্সারি' : 'Urban Jungle Nursery',
        'location' => $isBn ? 'বনানী, ঢাকা' : 'Banani, Dhaka',
        'tag' => $isBn ? '🌿 সাকুলেন্ট স্পেশাল • ৮৪০+ ডেলিভারি' : '🌿 Succulent Specialist • 840+ Orders',
        'rating' => '4.8★ (650 Reviews)',
        'specialty' => $isBn ? 'প্রিমিয়াম সাকুলেন্ট, টেরাকোটা পট ও ব্যালকনি কালেকশন' : 'Drought-Proof Rosette Succulents, Balcony Bloomers & Artisan Pots',
        'species' => '60+ Acclimated Species',
        'image' => 'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?q=80&w=1200&auto=format&fit=crop',
        'fallback' => 'assets/images/nurseries/urban-jungle-banner.jpg',
        'tags' => ['Echeveria', 'Terracotta', 'Desk Plants']
    ],
    [
        'id' => 4,
        'rank' => '04',
        'name' => $isBn ? 'বগুড়া বনসাই সেন্টার' : 'Bogra Bonsai Heritage Center',
        'location' => $isBn ? 'বগুড়া সদর' : 'Bogra Heritage Hub',
        'tag' => $isBn ? '🏆 ১৫-২০ বছরের বনসাই • ৬২০+ অর্ডার' : '🏆 15-Yr Living Heritage • 620+ Orders',
        'rating' => '4.9★ (420 Reviews)',
        'specialty' => $isBn ? '১৫ থেকে ২০ বছরের সুপ্রাচীন শিকড়যুক্ত জীবন্ত বটবৃক্ষ বনসাই' : 'Aged Ficus Microcarpa, Banyan Bonsai & Botanical Miniature Trees',
        'species' => '45+ Heritage Sculptures',
        'image' => 'https://images.unsplash.com/photo-1512428813834-c702c7702b78?q=80&w=1200&auto=format&fit=crop',
        'fallback' => 'assets/images/plants/banyan-bonsai.jpg',
        'tags' => ['Banyan Bonsai', 'Ficus', 'Ancient Roots']
    ],
    [
        'id' => 5,
        'rank' => '05',
        'name' => $isBn ? 'সিলেট ফার্ন অ্যান্ড ফ্লোরা' : 'Sylhet Fern & Rainforest Flora',
        'location' => $isBn ? 'শ্রীমঙ্গল ও সিলেট' : 'Sreemangal, Sylhet',
        'tag' => $isBn ? '🌸 রেইনফরেস্ট ফার্ন • ৭৫০+ ডেলিভারি' : '🌸 Rainforest Canopy • 750+ Orders',
        'rating' => '4.9★ (530 Reviews)',
        'specialty' => $isBn ? 'আর্দ্রতাপ্রেমী পাহাড়ি ফার্ন, অর্কিড ও ক্যালাথিয়া অরবিফোলিয়া' : 'Rainforest Canopy Ferns, Exotic Orchids & Oversized Calathea',
        'species' => '75+ Rainforest Species',
        'image' => 'https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?q=80&w=1200&auto=format&fit=crop',
        'fallback' => 'assets/images/plants/calathea-orbifolia.jpg',
        'tags' => ['Thai Orchids', 'Fern Canopy', 'Pet Safe']
    ]
];
?>

<!-- Minimal Vertical Botanical Progress Trackline -->
<div class="spatial-progress-track">
    <div class="progress-stem-line"></div>
    <div class="progress-stem-fill"></div>

    <div class="progress-node is-active" data-target="zone-01">
        <span>01</span>
        <span class="progress-node-tooltip"><?= $isBn ? 'জোন ০১: অ্যাট্রিয়াম পোর্টাল' : 'Zone 01: Atrium Portal' ?></span>
    </div>
    <div class="progress-node" data-target="zone-02">
        <span>02</span>
        <span class="progress-node-tooltip"><?= $isBn ? 'জোন ০২: ট্রপিক্যাল প্যাভিলিয়ন' : 'Zone 02: Tropical Pavilion' ?></span>
    </div>
    <div class="progress-node" data-target="zone-03">
        <span>03</span>
        <span class="progress-node-tooltip"><?= $isBn ? 'জোন ০৩: AI প্ল্যান্ট ম্যাচমেকার' : 'Zone 03: AI Plant Matchmaker' ?></span>
    </div>
    <div class="progress-node" data-target="zone-04">
        <span>04</span>
        <span class="progress-node-tooltip"><?= $isBn ? 'জোন ০৪: শীর্ষ ৫ নার্সারি শোকেস' : 'Zone 04: Top 5 Nurseries' ?></span>
    </div>
    <div class="progress-node" data-target="zone-05">
        <span>05</span>
        <span class="progress-node-tooltip"><?= $isBn ? 'জোন ০৫: ইন-স্টক মার্কেটপ্লেস' : 'Zone 05: Marketplace' ?></span>
    </div>
</div>

<!-- Foreground Depth-of-Field Silhouette Leaf Layers (Walking Camera 3D Illusion) -->
<div class="spatial-leaf-layer">
    <svg class="dof-leaf dof-leaf-tl" viewBox="0 0 200 200" fill="none">
        <path d="M0 0 C60 20, 140 80, 180 180 C120 160, 40 120, 0 0 Z" fill="#042817" opacity="0.85"/>
        <path d="M0 0 C40 50, 80 130, 140 160 C80 130, 20 80, 0 0 Z" fill="#064e3b" opacity="0.6"/>
    </svg>
    <svg class="dof-leaf dof-leaf-tr" viewBox="0 0 200 200" fill="none">
        <path d="M200 0 C140 20, 60 80, 20 180 C80 160, 160 120, 200 0 Z" fill="#042817" opacity="0.85"/>
        <path d="M200 0 C160 50, 120 130, 60 160 C120 130, 180 80, 200 0 Z" fill="#064e3b" opacity="0.6"/>
    </svg>
    <svg class="dof-leaf dof-leaf-bl" viewBox="0 0 200 200" fill="none">
        <path d="M0 200 C60 180, 140 120, 180 20 C120 40, 40 80, 0 200 Z" fill="#042817" opacity="0.9"/>
    </svg>
    <svg class="dof-leaf dof-leaf-br" viewBox="0 0 200 200" fill="none">
        <path d="M200 200 C140 180, 60 120, 20 20 C80 40, 160 80, 200 200 Z" fill="#042817" opacity="0.9"/>
    </svg>
</div>

<!-- ==============================================================================
     ZONE 01: THE GREENHOUSE PORTAL & SUN-RAY ATRIUM (HERO)
     ============================================================================== -->
<section id="zone-01" class="zone-atrium-section">
    <!-- Universal Ambient Glowing Orbs -->
    <div class="ambient-orb ambient-orb-emerald -top-20 -left-20"></div>
    <div class="ambient-orb ambient-orb-mint top-1/2 -right-20"></div>

    <img src="<?= BASE_URL ?>/assets/images/nurseries/green-haven-banner.jpg" alt="Greenhouse Atrium" class="atrium-glass-bg" onerror="this.src='<?= BASE_URL ?>/assets/images/nurseries/default-banner.jpg';">
    <div class="atrium-sunray-mesh"></div>
    <div class="atrium-truss-overlay"></div>

    <!-- Sliding Glass Portal Doors -->
    <div class="atrium-portal-doors">
        <div class="portal-door portal-door-left"></div>
        <div class="portal-door portal-door-right"></div>
    </div>

    <!-- Center Atrium Welcome Plaque -->
    <div class="atrium-content-plaque">
        <div class="inline-flex items-center space-x-2 bg-emerald-500/20 text-emerald-300 px-4 py-1.5 rounded-full text-xs font-bold border border-emerald-400/30 mb-5">
            <i class="fa-solid fa-door-open text-emerald-400"></i>
            <span><?= $isBn ? 'স্মার্ট বোটানিক্যাল পোর্টাল • ওয়াকথ্রু শুরু' : 'Spatial Botanical Walkthrough • Zone 01' ?></span>
        </div>

        <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight leading-tight mb-4">
            <?= $isBn ? 'জীবন্ত গ্রিনহাউজ নেটওয়ার্কে <br><span class="hero-title-gradient">প্রবেশ করুন</span>' : 'Step Inside The <br><span class="hero-title-gradient">Living Greenhouse Network</span>' ?>
        </h1>

        <p class="text-slate-300 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed mb-8">
            <?= $isBn ? 'দেশের শীর্ষ সার্টিফাইড নার্সারি, ইন্টেলিজেন্ট এআই প্ল্যান্ট ম্যাচমেকার এবং শতভাগ সতেজ উদ্ভিদের এক অনন্য ভার্চুয়াল ওয়াকথ্রু।' : 'Experience an interactive journey through verified regional nurseries, algorithmic AI plant matchmaking, and curated botanicals.' ?>
        </p>

        <div class="flex flex-wrap items-center justify-center gap-4">
            <a href="#zone-02" class="px-8 py-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-sm rounded-2xl shadow-xl shadow-emerald-950/60 transition transform hover:-translate-y-1 flex items-center space-x-2">
                <i class="fa-solid fa-compass"></i>
                <span><?= $isBn ? 'ওয়াকথ্রু শুরু করুন' : 'Begin Walkthrough' ?></span>
            </a>
            <a href="<?= BASE_URL ?>/plant-matcher.php" class="px-8 py-4 bg-white/10 hover:bg-white/20 text-white font-bold text-sm rounded-2xl backdrop-blur-xl border border-white/20 transition flex items-center space-x-2">
                <i class="fa-solid fa-wand-magic-sparkles text-emerald-300"></i>
                <span><?= $isBn ? 'AI দিয়ে গাছ খুঁজুন' : 'Match with AI' ?></span>
            </a>
        </div>

        <div class="atrium-scroll-cue" onclick="document.getElementById('zone-02').scrollIntoView({behavior:'smooth'})">
            <div class="mouse-scroll-icon">
                <div class="mouse-scroll-wheel"></div>
            </div>
            <span><?= $isBn ? 'নিচে স্ক্রোল করে এগিয়ে যান ↓' : 'Scroll forward to enter greenhouse ↓' ?></span>
        </div>
    </div>
</section>

<!-- ==============================================================================
     ZONE 02: THE TROPICAL SPECIMEN PAVILION (CONTINUOUS 3D COVERFLOW AISLE)
     ============================================================================== -->
<section id="zone-02" class="zone-pavilion-section">
    <!-- Ambient Lighting & Architectural Grid -->
    <div class="ambient-orb ambient-orb-emerald top-10 -right-20"></div>
    <div class="architectural-grid-lines"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        
        <div class="text-center max-w-3xl mx-auto mb-10 space-y-2" data-aos="fade-up">
            <span class="text-xs font-bold text-emerald-700 uppercase tracking-widest block">
                <?= $isBn ? 'জোন ০২: আর্দ্র ট্রপিক্যাল আইল' : 'Zone 02: High-Humidity Tropical Aisle' ?>
            </span>
            <h2 class="text-3xl sm:text-4xl font-black text-slate-900">
                <?= $isBn ? 'ট্রপিক্যাল প্যাভিলিয়ন: <span class="title-gradient-emerald">বাছাইকৃত স্পেসিমেন কালেকশন</span>' : 'The Tropical Specimen Pavilion: <span class="title-gradient-emerald">Live 3D Plant Aisle</span>' ?>
            </h2>
            <p class="text-slate-600 text-xs sm:text-sm">
                <?= $isBn ? 'আইলের যেকোনো গাছের উপর কার্সর রাখলে ৩ডি ডেপথ ও লাইভ ইনভেন্টরি স্টক ফুটে উঠবে।' : 'Swipe or hover over specimens to review live telemetry meters, nursery origin, and 1-click cart triggers.' ?>
            </p>
        </div>

        <!-- 3D Coverflow Swiper Carousel -->
        <div class="swiper pavilion-swiper pavilion-swiper-container" data-aos="fade-up">
            <div class="swiper-wrapper">
                <?php foreach ($pavilionPlants as $plant): ?>
                    <div class="swiper-slide pavilion-slide-card js-tilt-card">
                        
                        <div class="pavilion-img-viewport">
                            <div class="absolute top-3 left-3 z-20">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-600/90 text-white backdrop-blur-md">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 animate-pulse mr-1"></span>
                                    <?= $plant['stock'] ?> <?= $isBn ? 'টি স্টকে আছে' : 'in Stock' ?>
                                </span>
                            </div>

                            <img src="<?= BASE_URL ?>/<?= sanitize($plant['img']) ?>" 
                                 alt="<?= sanitize($plant['title']) ?>" 
                                 onerror="this.src='<?= BASE_URL ?>/assets/images/plants/default-plant.jpg';">

                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-black/20"></div>

                            <div class="absolute bottom-2.5 left-3 right-3 flex items-center justify-between text-xs text-white">
                                <span class="font-bold truncate"><i class="fa-solid fa-store mr-1 text-emerald-300"></i><?= sanitize($plant['nursery']) ?></span>
                                <span class="font-extrabold text-emerald-300"><?= sanitize($plant['price']) ?></span>
                            </div>
                        </div>

                        <div class="p-5 space-y-3 bg-white">
                            <div>
                                <h3 class="font-black text-slate-900 text-base line-clamp-1"><?= sanitize($plant['title']) ?></h3>
                                <p class="text-[11px] text-emerald-700 italic font-mono"><?= sanitize($plant['scientific']) ?></p>
                            </div>

                            <div class="grid grid-cols-3 gap-1.5 text-center text-[10px]">
                                <div class="bg-emerald-50 p-2 rounded-xl border border-emerald-100 font-bold text-slate-700">
                                    <span class="text-amber-500 block"><i class="fa-solid fa-sun mr-0.5"></i>Sun</span>
                                    <span class="truncate"><?= sanitize($plant['sunlight']) ?></span>
                                </div>
                                <div class="bg-emerald-50 p-2 rounded-xl border border-emerald-100 font-bold text-slate-700">
                                    <span class="text-cyan-500 block"><i class="fa-solid fa-droplet mr-0.5"></i>Water</span>
                                    <span class="truncate"><?= sanitize($plant['water']) ?></span>
                                </div>
                                <div class="bg-emerald-50 p-2 rounded-xl border border-emerald-100 font-bold text-slate-700">
                                    <span class="text-emerald-500 block"><i class="fa-solid fa-wind mr-0.5"></i>NASA</span>
                                    <span class="truncate"><?= sanitize($plant['oxygen']) ?></span>
                                </div>
                            </div>

                            <div class="pt-2 flex items-center gap-2">
                                <button onclick="quickAddToCart(<?= $plant['id'] ?>)" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center space-x-1.5 cursor-pointer">
                                    <i class="fa-solid fa-cart-plus"></i>
                                    <span><?= $isBn ? 'কার্টে নিন' : 'Take to Cart' ?></span>
                                </button>
                                <a href="<?= BASE_URL ?>/product.php?id=<?= $plant['id'] ?>" class="px-3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition" title="Details">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination pavilion-pagination mt-6"></div>
        </div>

    </div>
</section>

<!-- ==============================================================================
     ZONE 03: THE AI PLANT MATCHER & BOTANICAL CONSULTANT STATION
     ============================================================================== -->
<section id="zone-03" class="zone-matcher-section">
    <!-- Atmospheric Glow & Ambient Lighting -->
    <div class="ambient-orb ambient-orb-mint top-10 -left-20"></div>
    <div class="ambient-orb ambient-orb-emerald -bottom-20 -right-20"></div>
    <div class="atmospheric-mesh-bg"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        
        <div class="text-center max-w-3xl mx-auto mb-14 space-y-2" data-aos="fade-up">
            <div class="inline-flex items-center space-x-2 bg-emerald-500/20 text-emerald-300 px-3.5 py-1.5 rounded-full text-xs font-bold border border-emerald-400/30">
                <i class="fa-solid fa-wand-magic-sparkles text-emerald-400"></i>
                <span><?= $isBn ? 'জোন ০৩: এআই প্ল্যান্ট ম্যাচমেকার' : 'Zone 03: AI Plant Matcher Station' ?></span>
            </div>
            <h2 class="text-3xl sm:text-4xl font-black text-white">
                <?= $isBn ? 'ইন্টেলিজেন্ট এআই ম্যাচমেকার: <span class="hero-title-gradient">আপনার রুমের পারফেক্ট গাছ</span>' : 'Gemini AI Plant Matcher: <span class="hero-title-gradient">Find Your Ideal Green Companion</span>' ?>
            </h2>
            <p class="text-slate-300 text-xs sm:text-sm">
                <?= $isBn ? 'আপনার ঘরের আলো, যত্নের সময় ও স্থানের সাথে মিলিয়ে মুহূর্তেই সবচেয়ে মানানসই গাছ খুঁজে নিন।' : 'Match botanical cultivars directly to your room sunlight, pet safety, and care commitments with Google Gemini AI.' ?>
            </p>
        </div>

        <div class="matcher-chamber-grid" data-aos="fade-up">
            
            <!-- Left: Laser Scanner & AI Morphology Chamber -->
            <div class="matcher-scanner-chamber">
                <div class="scanner-laser-beam"></div>
                
                <img id="matcher-scanner-image" src="<?= BASE_URL ?>/assets/images/plants/monstera.jpg" alt="Plant Matcher Specimen" class="matcher-scanner-img">

                <div id="matcher-tag-room" class="matcher-telemetry-tag top-6 left-6">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>● [Compatibility: 98.5%]</span>
                </div>
                <div id="matcher-tag-care" class="matcher-telemetry-tag bottom-6 left-8">
                    <i class="fa-solid fa-heart-pulse text-emerald-400"></i>
                    <span>● [Low Effort (1x / Week)]</span>
                </div>
                <div id="matcher-tag-safety" class="matcher-telemetry-tag top-12 right-6">
                    <i class="fa-solid fa-shield-cat text-teal-400"></i>
                    <span>● [Pet Safe (Elevated)]</span>
                </div>
            </div>

            <!-- Right: Interactive Room & Lifestyle Compatibility Selector -->
            <div class="space-y-5 bg-black/40 p-6 sm:p-8 rounded-3xl border border-emerald-500/20 backdrop-blur-xl">
                <div>
                    <h3 class="text-xl font-bold text-white mb-1"><?= $isBn ? 'আপনার স্পেস নির্বাচন করুন' : 'Select Your Space & Lifestyle' ?></h3>
                    <p class="text-xs text-slate-300"><?= $isBn ? 'যেকোনো অপশনে ক্লিক করে তাৎক্ষণিক লাইভ এআই প্রিভিউ দেখুন:' : 'Click any preset below to simulate instant real-time botanical matching:' ?></p>
                </div>

                <!-- Room Presets Grid -->
                <div class="grid grid-cols-2 gap-2.5">
                    <button type="button" class="matcher-preset-btn is-active" data-preset="living-room">
                        <span class="flex items-center space-x-2"><i class="fa-solid fa-couch text-emerald-400"></i><span><?= $isBn ? '🛋️ ড্রয়িং রুম' : '🛋️ Living Room' ?></span></span>
                        <i class="fa-solid fa-circle-check text-xs text-emerald-400"></i>
                    </button>
                    <button type="button" class="matcher-preset-btn" data-preset="balcony">
                        <span class="flex items-center space-x-2"><i class="fa-solid fa-sun text-amber-400"></i><span><?= $isBn ? '🌿 ব্যালকনি' : '🌿 Balcony Sun' ?></span></span>
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                    <button type="button" class="matcher-preset-btn" data-preset="work-desk">
                        <span class="flex items-center space-x-2"><i class="fa-solid fa-laptop text-sky-400"></i><span><?= $isBn ? '💻 পড়ার টেবিল' : '💻 Work Desk' ?></span></span>
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                    <button type="button" class="matcher-preset-btn" data-preset="bedroom">
                        <span class="flex items-center space-x-2"><i class="fa-solid fa-bed text-teal-400"></i><span><?= $isBn ? '🛏️ বেডরুম' : '🛏️ Bedroom Oasis' ?></span></span>
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                </div>

                <!-- Match Results Output Area -->
                <div id="matcher-live-result">
                    <div class="p-5 rounded-2xl bg-black/60 border border-emerald-400/50 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-emerald-400 font-bold uppercase tracking-wider"><?= $isBn ? 'স্মার্ট এআই ম্যাচ প্রোফাইল' : 'Gemini AI Match Profile' ?></span>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-mono text-[11px] font-bold">98.5% Compatibility</span>
                        </div>
                        <h4 class="text-base font-black text-white"><?= $isBn ? 'মনস্টেরা ডেলিসিওসা (Monstera)' : 'Monstera Deliciosa' ?></h4>
                        <p class="text-xs text-slate-300 leading-relaxed"><?= $isBn ? 'বৈঠকখানার বাতাস পরিশোধন ও আভিজাত্য বাড়াতে সেরা জাত।' : 'Top-rated living room architectural centerpiece with high oxygen delivery.' ?></p>
                        <div class="pt-2 flex items-center justify-between border-t border-white/10 text-xs text-emerald-300 font-semibold">
                            <span><i class="fa-solid fa-sun mr-1"></i><?= $isBn ? 'উজ্জ্বল পরোক্ষ আলো' : 'Bright Indirect Light' ?></span>
                            <a href="<?= BASE_URL ?>/plant-matcher.php" class="text-white hover:text-emerald-400 transition underline font-bold">
                                <?= $isBn ? 'পূর্ণাঙ্গ টেস্ট দিন' : 'Take Full AI Quiz' ?> &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="<?= BASE_URL ?>/plant-matcher.php" class="w-full py-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-lg transition flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span><?= $isBn ? 'সম্পূর্ণ AI প্ল্যান্ট ম্যাচমেকার চালান' : 'Launch Full AI Plant Matcher Tool' ?></span>
                    </a>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- ==============================================================================
     ZONE 04: TOP 5 NURSERIES SHOWCASE (INTERACTIVE LOOKBOOK ACCORDION)
     ============================================================================== -->
<section id="zone-04" class="zone-nursery-showcase-section">
    <!-- Ambient Glowing Orbs & Cinematic Atmospheric Lighting -->
    <div class="ambient-orb ambient-orb-emerald -top-20 -right-20"></div>
    <div class="ambient-orb ambient-orb-gold -bottom-20 -left-20"></div>
    <div class="atmospheric-mesh-bg"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        
        <div class="flex flex-col sm:flex-row items-center justify-between mb-12 gap-4" data-aos="fade-up">
            <div class="text-center sm:text-left space-y-1">
                <div class="inline-flex items-center space-x-2 bg-emerald-500/20 text-emerald-300 px-3.5 py-1.5 rounded-full text-xs font-bold border border-emerald-400/30">
                    <i class="fa-solid fa-store text-emerald-400"></i>
                    <span><?= $isBn ? 'জোন ০৪: শীর্ষ ৫ নার্সারি শোকেস' : 'Zone 04: Top 5 Verified Nurseries' ?></span>
                </div>
                <h2 class="text-3xl sm:text-4xl font-black text-white">
                    <?= $isBn ? 'সর্বোচ্চ বিক্রিত নার্সারি: <span class="hero-title-gradient">সার্টিফাইড গ্রোয়ার কালেকশন</span>' : 'Top 5 Certified Nurseries: <span class="hero-title-gradient">Best Selling Botanical Hubs</span>' ?>
                </h2>
                <p class="text-slate-300 text-xs sm:text-sm">
                    <?= $isBn ? 'দেশের শীর্ষ ৫টি সর্বোচ্চ প্রশংসিত ও ভেরিফাইড নার্সারি স্টোরফ্রন্টে সরাসরি ভিজিট করুন।' : 'Explore Bangladesh’s top 5 rated partner nurseries based on verified order volume and grower standards.' ?>
                </p>
            </div>

            <a href="<?= BASE_URL ?>/nurseries.php" class="px-6 py-3.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-2xl shadow-lg transition flex items-center space-x-2">
                <i class="fa-solid fa-store"></i>
                <span><?= $isBn ? 'সকল নার্সারি দেখুন' : 'Explore All Nurseries' ?></span>
            </a>
        </div>

        <!-- 5-Frame Nursery Accordion Track -->
        <div class="nursery-accordion-track" data-aos="fade-up" data-aos-delay="100">
            <?php foreach ($topNurseries as $idx => $nur): ?>
                <div class="nursery-frame-card <?= $idx === 0 ? 'is-active' : '' ?>" data-index="<?= $idx ?>">
                    
                    <!-- Nursery Cover Background Image -->
                    <img src="<?= sanitize($nur['image']) ?>" 
                         alt="<?= sanitize($nur['name']) ?>" 
                         class="nursery-frame-bg"
                         loading="lazy"
                         onerror="this.onerror=null; this.src='<?= BASE_URL ?>/<?= sanitize($nur['fallback']) ?>';">
                    
                    <div class="nursery-frame-overlay"></div>

                    <!-- Compressed State Vertical Rank & Name -->
                    <div class="nursery-vertical-indicator">
                        <span class="nursery-vertical-rank"><?= sanitize($nur['rank']) ?></span>
                        <span class="nursery-vertical-title"><?= sanitize($nur['name']) ?></span>
                    </div>

                    <!-- Expanded State Rich Nursery Card -->
                    <div class="nursery-expanded-card">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/15 pb-2.5">
                                <div>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-400">
                                        <i class="fa-solid fa-circle-check text-emerald-400"></i>
                                        <span><?= sanitize($nur['tag']) ?></span>
                                    </span>
                                    <h3 class="text-xl sm:text-2xl font-black text-white mt-0.5">
                                        <?= sanitize($nur['name']) ?>
                                    </h3>
                                    <p class="text-xs text-slate-300 flex items-center gap-1.5 mt-0.5">
                                        <i class="fa-solid fa-location-dot text-emerald-400"></i>
                                        <span><?= sanitize($nur['location']) ?></span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="px-3 py-1 bg-emerald-500/20 border border-emerald-400/40 rounded-full text-xs font-bold text-emerald-300">
                                        <?= sanitize($nur['rating']) ?>
                                    </span>
                                </div>
                            </div>

                            <p class="text-xs text-slate-200 line-clamp-2 leading-relaxed">
                                <?= sanitize($nur['specialty']) ?>
                            </p>

                            <!-- Specialty Tags -->
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <?php foreach ($nur['tags'] as $t): ?>
                                    <span class="px-2.5 py-1 rounded-lg bg-black/60 border border-white/10 text-[10px] font-semibold text-emerald-300">
                                        #<?= sanitize($t) ?>
                                    </span>
                                <?php endforeach; ?>
                                <span class="text-[11px] text-slate-400 font-mono ml-auto">
                                    <i class="fa-solid fa-leaf text-emerald-400 mr-1"></i><?= sanitize($nur['species']) ?>
                                </span>
                            </div>

                            <!-- Action CTA -->
                            <div class="pt-2 flex items-center justify-between border-t border-white/10">
                                <a href="<?= BASE_URL ?>/nursery_detail.php?id=<?= $nur['id'] ?>" class="px-5 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-xl shadow-md transition flex items-center space-x-2">
                                    <span><?= $isBn ? 'নার্সারি স্টোরফ্রন্ট দেখুন' : 'Visit Nursery Storefront' ?></span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/shop.php" class="text-xs font-bold text-emerald-400 hover:text-emerald-300 transition">
                                    <?= $isBn ? 'গাছের ক্যাটালগ' : 'Browse Catalog' ?> &rarr;
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<!-- ==============================================================================
     ZONE 05: THE OPEN VERANDA & CINEMATIC MARKETPLACE
     ============================================================================== -->
<section id="zone-05" class="zone-veranda-section">
    <!-- Ambient Radiant Glow Orbs & Subtle Grid Mesh -->
    <div class="ambient-orb ambient-orb-emerald top-20 -left-20"></div>
    <div class="ambient-orb ambient-orb-mint -bottom-20 -right-20"></div>
    <div class="architectural-grid-lines"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        
        <div class="text-center max-w-3xl mx-auto mb-10 space-y-2" data-aos="fade-up">
            <span class="text-xs font-bold text-emerald-700 uppercase tracking-widest block">
                <?= $isBn ? 'জোন ০৫: ওপেন বারান্দা ও রেজিস্ট্রি' : 'Zone 05: The Open Veranda & Marketplace' ?>
            </span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 leading-tight">
                <?= $isBn ? 'ইন-স্টক বোটানিক্যাল মার্কেটপ্লেস: <br><span class="title-gradient-emerald">শতভাগ সুস্থ চারার নিশ্চয়তা</span>' : 'Verified In-Stock Marketplace: <br><span class="title-gradient-emerald">Curated Botanical Specimens</span>' ?>
            </h2>
            <p class="text-slate-600 text-xs sm:text-sm max-w-2xl mx-auto">
                <?= $isBn ? 'দেশের সেরা নার্সারি থেকে সরাসরি বাছাই করা সুস্থ, জীবন্ত ও প্রিমিয়াম গাছপালা তাৎক্ষণিক ১-ক্লিকে অর্ডার করুন।' : 'Order hand-potted botanical cultivars directly from certified growers with zero root shock transit protection.' ?>
            </p>
        </div>

        <!-- Interactive Category Filter Tabs -->
        <div class="flex flex-wrap items-center justify-center gap-2.5 mb-12" data-aos="fade-up" data-aos-delay="100">
            <button type="button" class="marketplace-filter-pill is-active" data-cat="all">
                <span><?= $isBn ? '🌿 সকল গাছ' : '🌿 All Plants' ?></span>
            </button>
            <button type="button" class="marketplace-filter-pill" data-cat="1">
                <span><?= $isBn ? '🍃 ইনডোর গাছ' : '🍃 Indoor Foliage' ?></span>
            </button>
            <button type="button" class="marketplace-filter-pill" data-cat="2">
                <span><?= $isBn ? '⚡ এয়ার পিউরিফায়ার' : '⚡ Air Purifiers' ?></span>
            </button>
            <button type="button" class="marketplace-filter-pill" data-cat="3">
                <span><?= $isBn ? '💧 সাকুলেন্ট ও ক্যাকটাস' : '💧 Succulents & Cacti' ?></span>
            </button>
            <button type="button" class="marketplace-filter-pill" data-cat="5">
                <span><?= $isBn ? '💎 হেরিটেজ বনসাই' : '💎 Heritage Bonsai' ?></span>
            </button>
        </div>

        <!-- Specimen Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
            <?php foreach ($featuredProducts as $idx => $product): ?>
                <?php 
                    $primaryImg = get_image_url($product['primary_image']);
                    $altImg1 = $product['category_id'] == 1 ? 'assets/images/plants/fiddle-leaf-fig.jpg' : 'assets/images/plants/monstera.jpg';
                    $altImg2 = 'assets/images/plants/golden-pothos.jpg';
                ?>
                <div class="specimen-card group" data-cat="<?= $product['category_id'] ?>" data-aos="fade-up" data-aos-delay="<?= ($idx % 3) * 100 ?>">
                    
                    <div class="specimen-img-viewport">
                        <div class="absolute top-3 left-3 z-30">
                            <div class="live-dispatch-badge">
                                <div class="pulse-dot-green"></div>
                                <span><?= $isBn ? 'ভেরিফাইড ইন-স্টক (রেডি টু শিপ)' : 'Verified In-Stock (Ready to Ship)' ?></span>
                            </div>
                        </div>

                        <img src="<?= sanitize($primaryImg) ?>" 
                             alt="<?= sanitize(product_display_name($product)) ?>" 
                             class="specimen-main-img"
                             onerror="this.onerror=null; this.src='<?= BASE_URL ?>/assets/images/plants/default-plant.jpg';">

                        <!-- Mini Thumbnail Switcher Dots -->
                        <div class="specimen-thumb-strip">
                            <div class="specimen-thumb-dot is-active" data-src="<?= sanitize($primaryImg) ?>" title="Main View"></div>
                            <div class="specimen-thumb-dot" data-src="<?= BASE_URL ?>/<?= sanitize($altImg1) ?>" title="Leaf Detail"></div>
                            <div class="specimen-thumb-dot" data-src="<?= BASE_URL ?>/<?= sanitize($altImg2) ?>" title="Potted Angle"></div>
                        </div>

                        <!-- Hover Quick-Spec Frosted Overlay with SVG Meters -->
                        <div class="specimen-quick-overlay">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between text-xs text-emerald-300 font-bold border-b border-white/15 pb-2">
                                    <span><i class="fa-solid fa-shield-halved mr-1"></i><?= $isBn ? 'স্মার্ট পরিচর্যা মেট্রিক্স' : 'Smart Care Telemetry' ?></span>
                                    <span class="text-white">99.4% Health Score</span>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex justify-between text-[11px] text-slate-300">
                                        <span><i class="fa-solid fa-sun text-amber-400 mr-1"></i><?= $isBn ? 'আলোর মাত্রা' : 'Sunlight Optimal' ?></span>
                                        <span class="font-bold text-white"><?= sanitize(sunlight_i18n($product['sunlight_requirement'])) ?></span>
                                    </div>
                                    <div class="meter-bar-track">
                                        <div class="meter-bar-fill" style="--meter-val: 85%;"></div>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex justify-between text-[11px] text-slate-300">
                                        <span><i class="fa-solid fa-droplet text-cyan-400 mr-1"></i><?= $isBn ? 'পানি' : 'Water Cadence' ?></span>
                                        <span class="font-bold text-white"><?= sanitize($product['water_frequency']) ?></span>
                                    </div>
                                    <div class="meter-bar-track">
                                        <div class="meter-bar-fill" style="--meter-val: 65%;"></div>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex justify-between text-[11px] text-slate-300">
                                        <span><i class="fa-solid fa-wind text-emerald-400 mr-1"></i><?= $isBn ? 'নাসা অক্সিজেন' : 'NASA Air-Purify' ?></span>
                                        <span class="font-bold text-teal-300">98.2 / 100</span>
                                    </div>
                                    <div class="meter-bar-track">
                                        <div class="meter-bar-fill" style="--meter-val: 98%;"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-white/15 flex items-center justify-between">
                                <span class="text-[11px] text-emerald-300 truncate">
                                    <i class="fa-solid fa-store mr-1"></i><?= sanitize(vendor_display_name($product['nursery_name'])) ?>
                                </span>
                                <a href="<?= BASE_URL ?>/plant-matcher.php" class="px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white text-[10px] font-bold rounded-lg transition backdrop-blur-md">
                                    <?= $isBn ? 'AI ম্যাচমেকার' : 'AI Matcher' ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 flex-1 flex flex-col justify-between space-y-4 bg-white">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-emerald-700 font-bold uppercase tracking-wider text-[11px]">
                                    <?= sanitize(category_display_name($product['category_name'])) ?>
                                </span>
                                <span class="text-slate-400 text-[11px]">
                                    <i class="fa-solid fa-location-dot text-emerald-600 mr-1"></i><?= sanitize(vendor_display_name($product['nursery_name'])) ?>
                                </span>
                            </div>

                            <h3 class="font-extrabold text-slate-900 text-lg group-hover:text-emerald-700 transition line-clamp-1">
                                <a href="<?= BASE_URL ?>/product.php?id=<?= $product['id'] ?>"><?= sanitize(product_display_name($product)) ?></a>
                            </h3>

                            <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                <?= sanitize($product['short_description'] ?: $product['description']) ?>
                            </p>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                            <div>
                                <span class="text-xs text-slate-400 block font-semibold uppercase tracking-wider"><?= $isBn ? 'মূল্য' : 'Price' ?></span>
                                <span class="text-2xl font-black text-emerald-700">
                                    <?= format_currency($product['discount_price'] ?: $product['price']) ?>
                                </span>
                            </div>

                            <div class="flex items-center space-x-2">
                                <a href="<?= BASE_URL ?>/product.php?id=<?= $product['id'] ?>" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                                    <?= __('common.details') ?>
                                </a>
                                <button onclick="quickAddToCart(<?= $product['id'] ?>)" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-900/20 transition flex items-center space-x-1.5 cursor-pointer">
                                    <i class="fa-solid fa-basket-shopping"></i>
                                    <span><?= __('product.add_to_cart') ?></span>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- Trust Badges & Guarantee Bar -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16" data-aos="fade-up">
            <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 text-sm"><?= $isBn ? 'শক-প্রুফ সুরক্ষিত প্যাকেজিং' : 'Shock-Proof Transit Packaging' ?></h4>
                    <p class="text-xs text-slate-500"><?= $isBn ? 'ট্রানজিটে কোনো রুট শক ড্যামেজ নেই' : 'Zero root shock hydration packs' ?></p>
                </div>
            </div>

            <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-shield-heart"></i>
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 text-sm"><?= $isBn ? 'শতভাগ সুস্থ চারার নিশ্চয়তা' : '100% Live Arrival Guarantee' ?></h4>
                    <p class="text-xs text-slate-500"><?= $isBn ? '৭ দিনের রিপ্লেসমেন্ট সুবিধা' : '7-day replacement for damaged specimens' ?></p>
                </div>
            </div>

            <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 text-sm"><?= $isBn ? 'SSLCommerz সুরক্ষিত পেমেন্ট' : 'SSLCommerz Secured Checkout' ?></h4>
                    <p class="text-xs text-slate-500"><?= $isBn ? 'বিকাশ, নগদ ও কার্ডে পেমেন্ট' : 'bKash, Nagad, Visa, Mastercard accepted' ?></p>
                </div>
            </div>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

