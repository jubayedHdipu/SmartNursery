<?php
// Smart Plant Matcher (Bilingual)
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';

$isBn = is_bangla();
$pageTitle = $isBn ? 'স্মার্ট এআই প্ল্যান্ট ম্যাচমেকার • ' . __('app.name') : 'Smart AI Plant Matcher • ' . __('app.name');

$extraHead = '
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/ultra-modern-home.css?v=' . (file_exists(__DIR__ . '/assets/css/ultra-modern-home.css') ? filemtime(__DIR__ . '/assets/css/ultra-modern-home.css') : time()) . '">
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/botanical-showcase.css?v=' . (file_exists(__DIR__ . '/assets/css/botanical-showcase.css') ? filemtime(__DIR__ . '/assets/css/botanical-showcase.css') : time()) . '">
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/spatial-greenhouse.css?v=' . (file_exists(__DIR__ . '/assets/css/spatial-greenhouse.css') ? filemtime(__DIR__ . '/assets/css/spatial-greenhouse.css') : time()) . '">
';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="relative bg-slate-50 text-slate-800 min-h-screen">
    
    <!-- ==============================================================================
         ZONE 01 AESTHETIC: CINEMATIC AI MATCHER HERO
         ============================================================================== -->
    <div class="relative bg-[#04130c] text-white py-16 sm:py-20 overflow-hidden border-b border-emerald-950/60">
        <!-- Universal Ambient Glowing Orbs -->
        <div class="ambient-orb ambient-orb-emerald -top-20 -left-20"></div>
        <div class="ambient-orb ambient-orb-mint -bottom-20 -right-20"></div>
        <div class="atmospheric-mesh-bg"></div>
        <div class="architectural-grid-lines"></div>

        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 text-center space-y-4 z-10">
            <div class="inline-flex items-center space-x-2 bg-emerald-500/20 text-emerald-300 px-4 py-1.5 rounded-full text-xs font-bold border border-emerald-400/30 backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <i class="fa-solid fa-wand-magic-sparkles text-emerald-400"></i>
                <span><?= $isBn ? '🤖 জেমিনি এআই বোটানিক্যাল কনসালট্যান্ট' : '🤖 Gemini AI Botanical Matchmaker' ?></span>
            </div>

            <h1 class="text-3xl sm:text-5xl md:text-6xl font-black tracking-tight text-white leading-tight">
                <?= $isBn ? 'আপনার স্পেসের জন্য <span class="hero-title-gradient">নিখুঁত উদ্ভিদ খুঁজুন</span>' : 'Match Your Room to the <span class="hero-title-gradient">Perfect Living Specimen</span>' ?>
            </h1>

            <p class="text-sm sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed">
                <?= $isBn 
                    ? 'আপনার ঘরের সূর্যালোক, দৈনিক পরিচর্যার সময় এবং পোষা প্রাণীর নিরাপত্তা অনুযায়ী ১০০% উপযোগী ও স্বাস্থ্যকর গাছ স্বয়ংক্রিয়ভাবে খুঁজে নিন।'
                    : 'Tell us about your room lighting, watering cadence, and pet environment. Our AI algorithm instantly analyzes real in-stock nursery inventories to find your highest-compatibility botanical companion.' ?>
            </p>
        </div>
    </div>

    <!-- ==============================================================================
         DIAGNOSTIC WORKBENCH & INTERACTIVE QUESTIONNAIRE
         ============================================================================== -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-20 pb-16 space-y-12">
        
        <!-- Questionnaire Form Card -->
        <div class="bg-white/95 backdrop-blur-xl rounded-3xl p-6 sm:p-10 border border-slate-200/90 shadow-2xl shadow-slate-900/10 space-y-8">
            
            <div class="border-b border-slate-100 pb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center space-x-2.5">
                        <i class="fa-solid fa-clipboard-question text-emerald-600"></i>
                        <span><?= $isBn ? 'লাইফস্টাইল ও স্পেস বিশ্লেষণ' : 'Lifestyle & Climate Diagnostics' ?></span>
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1"><?= $isBn ? 'সঠিক অপশনগুলো নির্বাচন করুন এবং তাৎক্ষণিক এআই ম্যাচ ফলাফল দেখুন।' : 'Complete the 4-step botanical assessment below for instant match results.' ?></p>
                </div>
                <span class="inline-flex items-center px-3 py-1 bg-emerald-50 text-emerald-800 rounded-full text-xs font-bold border border-emerald-200">
                    <i class="fa-solid fa-bolt text-amber-500 mr-1.5"></i> <?= $isBn ? 'তাৎক্ষণিক ফলাফল' : 'Instant Analysis' ?>
                </span>
            </div>

            <form id="ai-wizard-form" class="space-y-8">
                
                <!-- Question 1: Space / Room Type -->
                <div class="space-y-3">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">
                        <?= $isBn ? '১. আপনার রুম বা স্পেসের ধরন নির্বাচন করুন' : 'Step 1: Select Your Room / Space Type' ?>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 text-xs">
                        <label class="cursor-pointer group">
                            <input type="radio" name="room_type" value="Living Room" checked class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-couch text-2xl text-slate-500 peer-checked:text-emerald-700 transition"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? '🛋️ ড্রয়িং রুম' : '🛋️ Living Room' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="room_type" value="Bedroom" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-bed text-2xl text-slate-500 peer-checked:text-emerald-700 transition"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? '🛏️ বেডরুম' : '🛏️ Bedroom Oasis' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="room_type" value="Balcony / Patio" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-sun text-2xl text-slate-500 peer-checked:text-emerald-700 transition"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? '🌿 ব্যালকনি' : '🌿 Balcony Sun' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="room_type" value="Office Desk" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-laptop text-2xl text-slate-500 peer-checked:text-emerald-700 transition"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? '💻 কাজের টেবিল' : '💻 Work Desk' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="room_type" value="Kitchen / Dining" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-kitchen-set text-2xl text-slate-500 peer-checked:text-emerald-700 transition"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? '🍳 কিচেন / ডাইনিং' : '🍳 Kitchen / Dining' ?></span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Question 2: Sunlight Level -->
                <div class="space-y-3">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">
                        <?= $isBn ? '২. আপনার রুমের সূর্যালোকের মাত্রা' : 'Step 2: Natural Sunlight Exposure' ?>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                        <label class="cursor-pointer group">
                            <input type="radio" name="sunlight" value="Bright Indirect" checked class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-cloud-sun text-2xl text-amber-500"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? 'উজ্জ্বল পরোক্ষ আলো' : 'Bright Indirect Light' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="sunlight" value="Low Light" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-moon text-2xl text-slate-500"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? 'স্বল্প আলো / ছায়া' : 'Low Light / Shaded' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="sunlight" value="Partial Shade" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-umbrella text-2xl text-teal-600"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? 'আংশিক রোদ' : 'Partial Sun & Shade' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="sunlight" value="Full Sun" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-sun text-2xl text-amber-600"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? 'সরাসরি প্রখর রোদ' : 'Direct Full Sun' ?></span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Question 3: Care Dedication Level -->
                <div class="space-y-3">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">
                        <?= $isBn ? '৩. দৈনিক বা সাপ্তাহিক পরিচর্যার সময়' : 'Step 3: Care & Maintenance Commitment' ?>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <label class="cursor-pointer group">
                            <input type="radio" name="care_level" value="Easy" checked class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-mug-hot text-2xl text-emerald-600"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? 'সহজ (ব্যস্তদের জন্য)' : 'Easy (Low Effort)' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="care_level" value="Moderate" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-calendar-check text-2xl text-teal-600"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? 'মাঝারি (সাপ্তাহিক যত্ন)' : 'Moderate (Weekly Care)' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="care_level" value="Expert" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex flex-col items-center justify-center space-y-2 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-medal text-2xl text-amber-600"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? 'বিশেষজ্ঞ (উচ্চ যত্ন)' : 'Expert (Daily Enthusiast)' ?></span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Question 4: Pet Safety & Living Constraints -->
                <div class="space-y-3">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">
                        <?= $isBn ? '৪. ঘরে পোষা প্রাণী (বিড়াল/কুকুর) আছে কি?' : 'Step 4: Pet Safety Compatibility' ?>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <label class="cursor-pointer group">
                            <input type="radio" name="pet_friendly" value="1" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex items-center justify-center space-x-3 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-shield-dog text-2xl text-emerald-600"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? 'হ্যাঁ, ১০০% পেট-ফ্রেন্ডলি হতে হবে' : 'Yes, Must Be 100% Pet-Safe' ?></span>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="pet_friendly" value="0" checked class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 text-center transition-all flex items-center justify-center space-x-3 hover:border-emerald-300 hover:bg-slate-50 shadow-xs">
                                <i class="fa-solid fa-leaf text-2xl text-slate-500"></i>
                                <span class="font-bold text-slate-800 text-xs sm:text-sm"><?= $isBn ? 'না, যেকোনো চারা চলবে' : 'No Pet Constraints' ?></span>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" 
                        class="w-full py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm sm:text-base rounded-2xl shadow-xl shadow-emerald-900/20 transition-all flex items-center justify-center space-x-2.5 cursor-pointer">
                    <i class="fa-solid fa-wand-magic-sparkles text-emerald-200 text-lg"></i>
                    <span><?= $isBn ? 'এআই ম্যাচমেকার স্ক্যান শুরু করুন' : 'Run Gemini AI Compatibility Match' ?></span>
                </button>
            </form>

        </div>

        <!-- Loading Indicator -->
        <div id="ai-loading-indicator" class="hidden text-center py-12 space-y-4">
            <div class="inline-block p-5 rounded-3xl bg-emerald-50 text-emerald-600 border border-emerald-200 shadow-lg animate-pulse">
                <i class="fa-solid fa-wand-magic-sparkles text-4xl"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800"><?= $isBn ? 'জেমিনি এআই আপনার স্পেস বিশ্লেষণ করছে...' : 'Gemini AI is analyzing live nursery catalog...' ?></h3>
            <p class="text-xs text-slate-500 max-w-sm mx-auto"><?= $isBn ? 'অনুকূল সূর্যালোক, অক্সিজেন রেটিং ও সার্টিফাইড স্টক খোঁজা হচ্ছে' : 'Cross-referencing light metrics, oxygen emission, and accredited inventory.' ?></p>
        </div>

        <!-- Results Section -->
        <div id="ai-results-section" class="hidden space-y-8">
            <div class="text-center space-y-2">
                <div class="inline-flex items-center space-x-2 bg-emerald-50 text-emerald-800 px-3.5 py-1.5 rounded-full text-xs font-bold border border-emerald-200">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                    <span><?= $isBn ? 'ম্যাচ ফলাফল প্রস্তুত' : 'Matches Found' ?></span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900"><?= $isBn ? 'আপনার জন্য প্রস্তাবিত উদ্ভিদ' : 'AI-Recommended Botanical Specimens' ?></h2>
                <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto"><?= $isBn ? 'নিচের গাছগুলো আপনার নির্বাচিত স্পেস ও লাইফস্টাইলের সাথে সর্বোচ্চ সামঞ্জস্যপূর্ণ।' : 'These specimens offer the highest survival index and beauty for your space.' ?></p>
            </div>

            <div id="ai-recommendations-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <!-- Dynamic botanical cards inserted by plant-matcher.js -->
            </div>
        </div>

    </div>

</div>

<script src="<?= BASE_URL ?>/assets/js/plant-matcher.js?v=<?= file_exists(__DIR__ . '/assets/js/plant-matcher.js') ? filemtime(__DIR__ . '/assets/js/plant-matcher.js') : time() ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
