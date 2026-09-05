<?php
// Global Luxury Botanical Footer (Bilingual)
require_once __DIR__ . '/../helpers/functions.php';

$siteName     = get_setting('site_name', APP_NAME);
$contactEmail = get_setting('contact_email', 'support@smartnursery.com');
$contactPhone = get_setting('contact_phone', '+880 1800-NURSERY');
$isBn         = is_bangla();
?>

<!-- ==============================================================================
     GLOBAL LUXURY BOTANICAL FOOTER
     ============================================================================== -->
<footer class="footer-dark-forest text-slate-300 text-xs mt-auto relative z-20 border-t border-emerald-950/60 bg-[#04130c]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        
        <!-- Newsletter Subscription Banner -->
        <div class="bg-black/40 backdrop-blur-xl rounded-3xl p-8 sm:p-12 mb-16 border border-white/10 relative overflow-hidden" data-aos="fade-up">
            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                
                <div class="lg:col-span-6 space-y-2">
                    <div class="inline-flex items-center space-x-2 bg-emerald-500/20 text-emerald-300 px-3 py-1 rounded-full text-xs font-bold border border-emerald-400/30">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span><?= $isBn ? 'বোটানিক্যাল বুলেটিন' : 'Botanical Gazette' ?></span>
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-black text-white">
                        <?= $isBn ? 'নতুন গাছ ও বিশেষ অফারের <span class="hero-title-gradient">খবর সবার আগে পান</span>' : 'Get Seasonal Care Tips & <span class="hero-title-gradient">Exclusive Botanical Drops</span>' ?>
                    </h3>
                    <p class="text-slate-300 text-xs sm:text-sm">
                        <?= $isBn ? 'প্রতি সপ্তাহে বিশেষজ্ঞ মালির পরামর্শ, জলবায়ু সতর্কতা ও বিশেষ ছাড় সরাসরি আপনার ইমেইলে।' : 'Join 12,000+ plant lovers receiving weekly microclimate insights and discounts.' ?>
                    </p>
                </div>

                <div class="lg:col-span-6">
                    <form id="global-newsletter-form" onsubmit="event.preventDefault(); if(typeof showToast==='function'){showToast('<?= $isBn ? 'ধন্যবাদ! আপনি সফলভাবে সাবস্ক্রাইব করেছেন।' : 'Thank you for subscribing to Botanical Gazette!' ?>', 'success');} this.reset();" class="newsletter-input-wrap">
                        <input type="email" required placeholder="<?= $isBn ? 'আপনার ইমেইল ঠিকানা লিখুন...' : 'Enter your work or personal email...' ?>" class="newsletter-input">
                        <button type="submit" class="newsletter-btn">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span><?= $isBn ? 'সাবস্ক্রাইব' : 'Subscribe' ?></span>
                        </button>
                    </form>
                </div>

            </div>
        </div>

        <!-- Footer Columns -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
            
            <!-- Brand Column -->
            <div class="space-y-4 md:col-span-1">
                <div class="flex items-center space-x-3">
                    <?php 
                    $siteLogoType  = get_setting('site_logo_type', 'icon');
                    $siteLogoIcon  = get_setting('site_logo_icon', 'fa-leaf');
                    $siteLogoImage = get_setting('site_logo_image', '');
                    $brandTitle    = $isBn ? get_setting('site_brand_title_bn', __('app.brand_title')) : get_setting('site_brand_title', __('app.brand_title'));
                    $brandSubtitle = $isBn ? get_setting('site_brand_subtitle_bn', __('app.brand_subtitle')) : get_setting('site_brand_subtitle', __('app.brand_subtitle'));
                    ?>
                    <?php if ($siteLogoType === 'image' && !empty($siteLogoImage)): ?>
                        <img src="<?= sanitize(get_image_url($siteLogoImage)) ?>" alt="Logo" class="w-10 h-10 rounded-2xl object-cover shadow-lg shadow-emerald-950">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 flex items-center justify-center text-white shadow-lg shadow-emerald-950">
                            <i class="fa-solid <?= sanitize($siteLogoIcon) ?> text-lg text-emerald-200"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="text-lg font-black text-white tracking-tight block"><?= sanitize($brandTitle) ?><span class="text-emerald-400"><?= sanitize($brandSubtitle) ?></span></span>
                        <span class="text-[10px] font-semibold text-emerald-400 uppercase tracking-widest block"><?= __('app.brand_tag') ?></span>
                    </div>
                </div>
                <p class="text-slate-400 text-xs leading-relaxed">
                    <?= sanitize($isBn ? get_setting('site_tagline_bn', __('footer.description')) : get_setting('site_tagline', __('footer.description'))) ?>
                </p>
                <div class="flex items-center space-x-3 text-slate-300 pt-2">
                    <span class="w-9 h-9 rounded-xl bg-emerald-950/80 border border-emerald-800/60 flex items-center justify-center hover:text-emerald-300 hover:bg-emerald-900 transition cursor-pointer"><i class="fa-brands fa-facebook-f"></i></span>
                    <span class="w-9 h-9 rounded-xl bg-emerald-950/80 border border-emerald-800/60 flex items-center justify-center hover:text-emerald-300 hover:bg-emerald-900 transition cursor-pointer"><i class="fa-brands fa-instagram"></i></span>
                    <span class="w-9 h-9 rounded-xl bg-emerald-950/80 border border-emerald-800/60 flex items-center justify-center hover:text-emerald-300 hover:bg-emerald-900 transition cursor-pointer"><i class="fa-brands fa-github"></i></span>
                </div>
            </div>

            <!-- Botanical Quick Links -->
            <div>
                <h4 class="text-white font-bold text-xs uppercase tracking-wider mb-4 flex items-center space-x-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span><?= __('footer.shop_heading') ?></span>
                </h4>
                <ul class="space-y-2.5">
                    <li><a href="<?= BASE_URL ?>/shop.php" class="hover:text-emerald-400 transition flex items-center space-x-1.5"><i class="fa-solid fa-chevron-right text-[9px] text-emerald-600"></i><span><?= __('footer.all_plants') ?></span></a></li>
                    <li><a href="<?= BASE_URL ?>/shop.php?category=1" class="hover:text-emerald-400 transition flex items-center space-x-1.5"><i class="fa-solid fa-chevron-right text-[9px] text-emerald-600"></i><span><?= __('footer.indoor_foliage') ?></span></a></li>
                    <li><a href="<?= BASE_URL ?>/shop.php?category=2" class="hover:text-emerald-400 transition flex items-center space-x-1.5"><i class="fa-solid fa-chevron-right text-[9px] text-emerald-600"></i><span><?= __('footer.air_purifying') ?></span></a></li>
                    <li><a href="<?= BASE_URL ?>/shop.php?category=3" class="hover:text-emerald-400 transition flex items-center space-x-1.5"><i class="fa-solid fa-chevron-right text-[9px] text-emerald-600"></i><span><?= __('footer.succulents_cacti') ?></span></a></li>
                    <li><a href="<?= BASE_URL ?>/shop.php?category=5" class="hover:text-emerald-400 transition flex items-center space-x-1.5"><i class="fa-solid fa-chevron-right text-[9px] text-emerald-600"></i><span><?= $isBn ? 'বনসাই ও দুর্লভ প্রজাতি' : 'Bonsai & Exotics' ?></span></a></li>
                </ul>
            </div>

            <!-- Smart Portals & Tools -->
            <div>
                <h4 class="text-white font-bold text-xs uppercase tracking-wider mb-4 flex items-center space-x-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span><?= __('footer.features_heading') ?></span>
                </h4>
                <ul class="space-y-2.5">
                    <li><a href="<?= BASE_URL ?>/plant-matcher.php" class="text-emerald-400 font-semibold hover:underline flex items-center space-x-1.5"><i class="fa-solid fa-wand-magic-sparkles text-[10px]"></i><span><?= __('nav.ai_matcher') ?></span></a></li>
                    <li><a href="<?= BASE_URL ?>/track-order.php" class="hover:text-emerald-400 transition flex items-center space-x-1.5"><i class="fa-solid fa-truck-fast text-[10px] text-emerald-600"></i><span><?= __('nav.track_order') ?></span></a></li>
                    <li><a href="<?= BASE_URL ?>/register.php?role=vendor" class="hover:text-emerald-400 transition flex items-center space-x-1.5"><i class="fa-solid fa-store text-[10px] text-emerald-600"></i><span><?= __('footer.partner_vendor') ?></span></a></li>
                    <li><a href="<?= BASE_URL ?>/nurseries.php" class="hover:text-emerald-400 transition flex items-center space-x-1.5"><i class="fa-solid fa-building text-[10px] text-emerald-600"></i><span><?= __('nav.nurseries') ?></span></a></li>
                    <li><a href="<?= BASE_URL ?>/login.php" class="hover:text-emerald-400 transition flex items-center space-x-1.5"><i class="fa-solid fa-user text-[10px] text-emerald-600"></i><span><?= __('footer.account_signin') ?></span></a></li>
                </ul>
            </div>

            <!-- Support & Guarantee -->
            <div>
                <h4 class="text-white font-bold text-xs uppercase tracking-wider mb-4 flex items-center space-x-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span><?= __('footer.support_heading') ?></span>
                </h4>
                <ul class="space-y-2.5 text-xs text-slate-300">
                    <li class="flex items-center space-x-2">
                        <i class="fa-solid fa-envelope text-emerald-400 w-4"></i>
                        <span><?= sanitize($contactEmail) ?></span>
                    </li>
                    <li class="flex items-center space-x-2">
                        <i class="fa-solid fa-phone text-emerald-400 w-4"></i>
                        <span><?= sanitize($contactPhone) ?></span>
                    </li>
                    <li class="flex items-center space-x-2">
                        <i class="fa-solid fa-location-dot text-emerald-400 w-4"></i>
                        <span><?= __('footer.location') ?></span>
                    </li>
                </ul>
                <div class="mt-4 p-3.5 rounded-2xl bg-emerald-950/50 border border-emerald-800/40 text-[11px] text-slate-300">
                    <span class="font-bold text-emerald-400 block mb-0.5"><?= __('footer.guarantee_title') ?></span>
                    <?= __('footer.guarantee_desc') ?>
                </div>
            </div>

        </div>

        <!-- Bottom Copyright & Links -->
        <div class="border-t border-emerald-900/60 pt-6 flex flex-col sm:flex-row items-center justify-between text-[11px] text-slate-400 space-y-2 sm:space-y-0">
            <p>&copy; <?= $isBn ? bn_num(date('Y')) : date('Y') ?> <?= sanitize($siteName) ?>. <?= __('footer.copyright') ?></p>
            <div class="flex items-center space-x-4">
                <a href="<?= BASE_URL ?>/shop.php" class="text-slate-400 hover:text-emerald-400 transition"><?= __('nav.browse_plants') ?></a>
                <span>•</span>
                <a href="<?= BASE_URL ?>/plant-matcher.php" class="text-slate-400 hover:text-emerald-400 transition"><?= __('nav.ai_matcher') ?></a>
                <span>•</span>
                <a href="<?= BASE_URL ?>/nurseries.php" class="text-slate-400 hover:text-emerald-400 transition"><?= __('nav.nurseries') ?></a>
            </div>
        </div>
    </div>
</footer>

<!-- Toast Notifications Container -->
<div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col space-y-3 pointer-events-none"></div>

<!-- Scripts -->
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= file_exists(__DIR__ . '/../assets/js/main.js') ? filemtime(__DIR__ . '/../assets/js/main.js') : time() ?>"></script>

<?php if (isset($extraFooter)) echo $extraFooter; ?>

</body>
</html>
