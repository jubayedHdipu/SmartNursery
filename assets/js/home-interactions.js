/**
 * Smart Nursery Management System - Ultra-Modern Home Interactions
 * Handles Swiper Hero, 3D Vanilla-Tilt, Masonry Filters, GLightbox,
 * AI Matchmaker Mini-Tool & Micro-Animations.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Lucide Icons
    if (window.lucide) {
        window.lucide.createIcons();
    }

    // 2. Initialize AOS (Animate on Scroll)
    if (window.AOS) {
        window.AOS.init({
            duration: 800,
            once: true,
            offset: 50,
            easing: 'ease-out-cubic'
        });
    }

    // 3. Glassmorphic Header Scroll Effect (Clean Frosted Glass)
    const navbar = document.querySelector('nav.glass-nav') || document.querySelector('nav');
    if (navbar) {
        const handleNavScroll = () => {
            if (window.scrollY > 30) {
                navbar.classList.add('glass-nav-scrolled');
            } else {
                navbar.classList.remove('glass-nav-scrolled');
            }
        };
        window.addEventListener('scroll', handleNavScroll, { passive: true });
        handleNavScroll();
    }

    // 4. Cinematic Hero Swiper Slider
    const heroSwiperEl = document.querySelector('.hero-swiper');
    if (heroSwiperEl && window.Swiper) {
        const progressCircle = document.querySelector('.autoplay-progress svg');
        const progressContent = document.querySelector('.autoplay-progress span');

        const heroSwiper = new Swiper('.hero-swiper', {
            loop: true,
            speed: 1000,
            parallax: true,
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
            autoplay: {
                delay: 6500,
                disableOnInteraction: false
            },
            pagination: {
                el: '.hero-swiper-pagination',
                clickable: true
            },
            navigation: {
                nextEl: '.hero-next-btn',
                prevEl: '.hero-prev-btn'
            },
            on: {
                autoplayTimeLeft(s, time, progress) {
                    if (progressCircle) {
                        progressCircle.style.setProperty('--progress', 1 - progress);
                    }
                    if (progressContent) {
                        progressContent.textContent = `${Math.ceil(time / 1000)}s`;
                    }
                },
                slideChangeTransitionStart() {
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }
            }
        });
    }

    // 5. Interactive 3D Vanilla-Tilt on Showcase Cards
    if (window.VanillaTilt) {
        const tiltCards = document.querySelectorAll('.js-tilt-card, .showcase-3d-card');
        VanillaTilt.init(tiltCards, {
            max: 14,
            perspective: 1200,
            scale: 1.02,
            speed: 800,
            glare: true,
            'max-glare': 0.25,
            gyroscope: true
        });
    }

    // 6. Filterable Masonry Gallery
    const filterButtons = document.querySelectorAll('.gallery-filter-btn');
    const galleryItems = document.querySelectorAll('.masonry-item');

    if (filterButtons.length > 0 && galleryItems.length > 0) {
        filterButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                filterButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const filterValue = btn.getAttribute('data-filter') || 'all';

                galleryItems.forEach(item => {
                    const category = item.getAttribute('data-category') || '';
                    if (filterValue === 'all' || category.includes(filterValue)) {
                        item.classList.remove('is-hidden');
                        item.style.opacity = '0';
                        item.style.transform = 'scale(0.94)';
                        setTimeout(() => {
                            item.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                            item.style.opacity = '1';
                            item.style.transform = 'scale(1)';
                        }, 40);
                    } else {
                        item.classList.add('is-hidden');
                    }
                });

                // Refresh GLightbox instance so only visible slides are navigable
                if (window.galleryLightbox) {
                    window.galleryLightbox.reload();
                }
            });
        });
    }

    // 7. GLightbox Initialization
    if (window.GLightbox) {
        window.galleryLightbox = GLightbox({
            selector: '.glightbox',
            touchNavigation: true,
            loop: true,
            autoplayVideos: false,
            openEffect: 'zoom',
            closeEffect: 'fade',
            skin: 'clean',
            descPosition: 'bottom'
        });
    }

    // 8. AI Matchmaker Interactive Mini-Tool
    const aiMatcherContainer = document.getElementById('ai-matcher-widget');
    if (aiMatcherContainer) {
        const matchState = {
            light: 'indirect',
            care: 'easy',
            space: 'living'
        };

        const botanicalPool = [
            {
                id: 1,
                name: 'Monstera Deliciosa',
                name_bn: 'মনস্টেরা ডেলিসিওসা',
                scientific: 'Monstera deliciosa',
                image: 'assets/images/plants/monstera.jpg',
                price: 750,
                nursery: 'Green Haven Nursery',
                nursery_bn: 'গ্রিন হেভেন নার্সারি',
                light: ['indirect', 'low'],
                care: ['easy', 'daily'],
                space: ['living', 'balcony'],
                matchScore: 98,
                careLevel: 'Easy',
                careLevel_bn: 'সহজ পরিচর্যা',
                sunlight: 'Bright Indirect',
                sunlight_bn: 'উজ্জ্বল আলো',
                petSafe: false,
                purifying: true,
                badge: 'Top Oxygen Booster'
            },
            {
                id: 2,
                name: 'Snake Plant Laurentii',
                name_bn: 'স্নেক প্ল্যান্ট লরেন্টি',
                scientific: 'Sansevieria trifasciata',
                image: 'assets/images/plants/snake-plant.jpg',
                price: 450,
                nursery: 'Urban Jungle Hub',
                nursery_bn: 'আরবান জঙ্গল হাব',
                light: ['low', 'indirect', 'full_sun'],
                care: ['easy'],
                space: ['desk', 'living'],
                matchScore: 99,
                careLevel: 'Very Easy',
                careLevel_bn: 'খুবই সহজ',
                sunlight: 'Low to Bright',
                sunlight_bn: 'স্বল্প আলোতেও টিকে',
                petSafe: false,
                purifying: true,
                badge: 'Indestructible'
            },
            {
                id: 3,
                name: 'Peace Lily Supreme',
                name_bn: 'পিস লিলি সুপ্রিম',
                scientific: 'Spathiphyllum wallisii',
                image: 'assets/images/plants/peace-lily.jpg',
                price: 580,
                nursery: 'Gazipur Purifying Nursery',
                nursery_bn: 'গাজীপুর পিউরিফাইং নার্সারি',
                light: ['low', 'indirect'],
                care: ['easy', 'daily'],
                space: ['living', 'desk'],
                matchScore: 96,
                careLevel: 'Moderate',
                careLevel_bn: 'মাঝারি',
                sunlight: 'Filtered Light',
                sunlight_bn: 'ছায়াঘেরা আলো',
                petSafe: false,
                purifying: true,
                badge: 'Toxin Filter #1'
            },
            {
                id: 4,
                name: 'Golden Pothos Totem',
                name_bn: 'গোল্ডেন প্যাথোস মানিপ্ল্যান্ট',
                scientific: 'Epipremnum aureum',
                image: 'assets/images/plants/golden-pothos.jpg',
                price: 380,
                nursery: 'Dhanmondi Rooftop Haven',
                nursery_bn: 'ধানমন্ডি রুফটপ হেভেন',
                light: ['low', 'indirect'],
                care: ['easy'],
                space: ['desk', 'living', 'balcony'],
                matchScore: 97,
                careLevel: 'Easy',
                careLevel_bn: 'সহজ',
                sunlight: 'Any Indirect',
                sunlight_bn: 'পরোক্ষ আলো',
                petSafe: false,
                purifying: true,
                badge: 'Fast Grower'
            },
            {
                id: 5,
                name: 'Fiddle Leaf Fig Classic',
                name_bn: 'ফিডল লিফ ফিগ',
                scientific: 'Ficus lyrata',
                image: 'assets/images/plants/fiddle-leaf-fig.jpg',
                price: 1450,
                nursery: 'Gulshan Botanical Studio',
                nursery_bn: 'গুলশান বোটানিক্যাল স্টুডিও',
                light: ['indirect', 'full_sun'],
                care: ['daily'],
                space: ['living', 'balcony'],
                matchScore: 94,
                careLevel: 'Dedicated',
                careLevel_bn: 'বিশেষ যত্ন প্রয়োজন',
                sunlight: 'Bright Light',
                sunlight_bn: 'উজ্জ্বল রোদ',
                petSafe: false,
                purifying: true,
                badge: 'Architectural Statement'
            },
            {
                id: 6,
                name: 'Areca Palm Luxury',
                name_bn: 'আরেকা পাম লাক্সারি',
                scientific: 'Dypsis lutescens',
                image: 'assets/images/plants/areca-palm.jpg',
                price: 890,
                nursery: 'Coxs Palms & Foliage',
                nursery_bn: 'কক্স পামস অ্যান্ড ফলিয়েজ',
                light: ['indirect', 'full_sun'],
                care: ['easy', 'daily'],
                space: ['living', 'balcony'],
                matchScore: 95,
                careLevel: 'Easy',
                careLevel_bn: 'সহজ',
                sunlight: 'Bright Sunlight',
                sunlight_bn: 'উজ্জ্বল আলো',
                petSafe: true,
                purifying: true,
                badge: 'Pet Safe & Lush'
            },
            {
                id: 7,
                name: 'Banyan Bonsai Masterpiece',
                name_bn: 'বটবৃক্ষ বনসাই মাস্টারপিস',
                scientific: 'Ficus microcarpa',
                image: 'assets/images/plants/banyan-bonsai.jpg',
                price: 3200,
                nursery: 'Bogra Bonsai Center',
                nursery_bn: 'বগুড়া বনসাই সেন্টার',
                light: ['full_sun', 'indirect'],
                care: ['daily'],
                space: ['desk', 'balcony'],
                matchScore: 93,
                careLevel: 'Expert',
                careLevel_bn: 'অভিজ্ঞদের জন্য',
                sunlight: 'Direct Sun',
                sunlight_bn: 'সরাসরি রোদ',
                petSafe: true,
                purifying: false,
                badge: 'Living Heritage'
            },
            {
                id: 8,
                name: 'Aloe Vera Therapeutic',
                name_bn: 'অ্যালোভেরা ঔষধি ঘৃতকুমারী',
                scientific: 'Aloe barbadensis miller',
                image: 'assets/images/plants/aloe-vera.jpg',
                price: 250,
                nursery: 'Aroma Herbal Farm',
                nursery_bn: 'অ্যারোমা হারবাল ফার্ম',
                light: ['full_sun', 'indirect'],
                care: ['easy'],
                space: ['desk', 'balcony'],
                matchScore: 99,
                careLevel: 'Easy',
                careLevel_bn: 'খুব সহজ',
                sunlight: 'Sun Loving',
                sunlight_bn: 'রোদে ভালো থাকে',
                petSafe: false,
                purifying: true,
                badge: 'Medicinal Miracle'
            }
        ];

        const isBangla = window.SMART_NURSERY_CONFIG?.lang === 'bn';
        const currency = window.SMART_NURSERY_CONFIG?.currency || '৳';
        const baseUrl = window.SMART_NURSERY_CONFIG?.baseUrl || '';

        const resultsContainer = document.getElementById('ai-match-results');

        const calculateMatches = () => {
            const scored = botanicalPool.map(plant => {
                let score = 70;
                if (plant.light.includes(matchState.light)) score += 12;
                if (plant.care.includes(matchState.care)) score += 10;
                if (plant.space.includes(matchState.space)) score += 8;
                return { ...plant, calculatedScore: Math.min(score, 99) };
            });

            scored.sort((a, b) => b.calculatedScore - a.calculatedScore);
            return scored.slice(0, 3);
        };

        const renderMatches = () => {
            if (!resultsContainer) return;
            const top3 = calculateMatches();

            resultsContainer.style.opacity = '0';
            resultsContainer.style.transform = 'translateY(12px)';

            setTimeout(() => {
                resultsContainer.innerHTML = top3.map((plant, idx) => `
                    <div class="ai-result-card-light group" data-aos="fade-up" data-aos-delay="${idx * 100}">
                        <div class="relative h-52 bg-slate-100 overflow-hidden">
                            <img src="${baseUrl}/${plant.image}" 
                                 alt="${plant.name}" 
                                 class="w-full h-full object-cover group-hover:scale-108 transition duration-700"
                                 onerror="this.onerror=null; this.src='${baseUrl}/assets/images/plants/default-plant.jpg';">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-black/20"></div>
                            
                            <!-- Match Score Pill -->
                            <div class="absolute top-3 right-3">
                                <span class="ai-match-score-badge flex items-center space-x-1 shadow-md">
                                    <i class="fa-solid fa-sparkles text-xs"></i>
                                    <span>${plant.calculatedScore}% ${isBangla ? 'ম্যাচ' : 'Match'}</span>
                                </span>
                            </div>

                            <!-- Pet Safe / Purifying Pill -->
                            <div class="absolute top-3 left-3 flex flex-col gap-1">
                                ${plant.petSafe ? `
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-600/90 text-white backdrop-blur-md">
                                        <i class="fa-solid fa-paw mr-1 text-[9px]"></i> ${isBangla ? 'পোষ্যবান্ধব' : 'Pet Safe'}
                                    </span>` : ''
                                }
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-600/90 text-white backdrop-blur-md">
                                    <i class="fa-solid fa-wind mr-1 text-[9px]"></i> ${plant.badge}
                                </span>
                            </div>

                            <div class="absolute bottom-2.5 left-3 right-3 flex items-center justify-between text-xs text-white">
                                <span class="truncate font-medium"><i class="fa-solid fa-store mr-1 text-emerald-300"></i>${isBangla ? plant.nursery_bn : plant.nursery}</span>
                            </div>
                        </div>

                        <div class="p-6 space-y-3 bg-white">
                            <div>
                                <h4 class="font-extrabold text-slate-900 text-base group-hover:text-emerald-600 transition line-clamp-1">
                                    <a href="${baseUrl}/product.php?id=${plant.id}">${isBangla ? plant.name_bn : plant.name}</a>
                                </h4>
                                <p class="text-[11px] text-emerald-700 font-medium italic">${plant.scientific}</p>
                            </div>

                            <!-- Care Specs Bar -->
                            <div class="grid grid-cols-2 gap-2 pt-1 text-[11px] text-slate-600">
                                <div class="bg-emerald-50/80 p-2 rounded-xl border border-emerald-100 flex items-center space-x-1.5 font-medium">
                                    <i class="fa-solid fa-sun text-amber-500 shrink-0"></i>
                                    <span class="truncate">${isBangla ? plant.sunlight_bn : plant.sunlight}</span>
                                </div>
                                <div class="bg-emerald-50/80 p-2 rounded-xl border border-emerald-100 flex items-center space-x-1.5 font-medium">
                                    <i class="fa-solid fa-heart-pulse text-emerald-600 shrink-0"></i>
                                    <span class="truncate">${isBangla ? plant.careLevel_bn : plant.careLevel}</span>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-semibold uppercase tracking-wider">${isBangla ? 'মূল্য' : 'Price'}</span>
                                    <span class="text-xl font-black text-emerald-600">${currency}${plant.price.toLocaleString()}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <a href="${baseUrl}/product.php?id=${plant.id}" 
                                       class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                                        ${isBangla ? 'বিস্তারিত' : 'Specs'}
                                    </a>
                                    <button onclick="if(window.quickAddToCart){window.quickAddToCart(${plant.id});}else{window.location.href='${baseUrl}/product.php?id=${plant.id}';}" 
                                            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-900/20 transition flex items-center space-x-1.5 cursor-pointer">
                                        <i class="fa-solid fa-basket-shopping"></i>
                                        <span>${isBangla ? 'অর্ডার' : 'Adopt'}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');

                resultsContainer.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                resultsContainer.style.opacity = '1';
                resultsContainer.style.transform = 'translateY(0)';

                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }, 200);
        };

        // Attach Click Listeners to Matcher Selectors
        const stepButtons = document.querySelectorAll('.matchmaker-step-btn-light, .matchmaker-step-btn');
        stepButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const stepKey = btn.getAttribute('data-step');
                const stepVal = btn.getAttribute('data-value');

                if (stepKey && stepVal) {
                    document.querySelectorAll(`.matchmaker-step-btn-light[data-step="${stepKey}"], .matchmaker-step-btn[data-step="${stepKey}"]`).forEach(b => {
                        b.classList.remove('is-selected');
                    });
                    btn.classList.add('is-selected');

                    matchState[stepKey] = stepVal;
                    renderMatches();
                }
            });
        });

        // Initial Render
        renderMatches();
    }

    // 9. Interactive Newsletter Subscription Form
    const newsletterForm = document.getElementById('home-newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = newsletterForm.querySelector('input[type="email"]');
            const submitBtn = newsletterForm.querySelector('button[type="submit"]');
            if (!input || !input.value) return;

            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-sm"></i>';

            setTimeout(() => {
                submitBtn.innerHTML = '<i class="fa-solid fa-check mr-1 text-sm"></i> Subscribed!';
                submitBtn.classList.remove('bg-emerald-500');
                submitBtn.classList.add('bg-emerald-400');
                input.value = '';

                if (typeof window.showToast === 'function') {
                    window.showToast('🌿 Thank you for subscribing to our Botanical Gazette!', 'success');
                }

                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                    submitBtn.classList.add('bg-emerald-500');
                    submitBtn.classList.remove('bg-emerald-400');
                }, 4000);
            }, 700);
        });
    }

    // 10. Live Hotspot Card Hover Tracking
    const hotspots = document.querySelectorAll('.hotspot-beacon');
    hotspots.forEach(hotspot => {
        hotspot.addEventListener('mouseenter', () => {
            const tooltip = hotspot.querySelector('.hotspot-tooltip');
            if (tooltip) {
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateX(-50%) translateZ(60px) scale(1.05)';
            }
        });
        hotspot.addEventListener('mouseleave', () => {
            const tooltip = hotspot.querySelector('.hotspot-tooltip');
            if (tooltip) {
                tooltip.style.opacity = '0.95';
                tooltip.style.transform = 'translateX(-50%) translateZ(40px) scale(1)';
            }
        });
    });
});
