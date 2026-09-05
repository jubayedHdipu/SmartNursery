/**
 * Smart Nursery Management System - Spatial Scrollytelling Botanical Walkthrough
 * Powered by GSAP, ScrollTrigger, Swiper 11, and Interactive Zone Controllers
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize GSAP & ScrollTrigger
    if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);

        // A. Foreground Silhouette Leaves Parallax (Walking Camera Dolly Effect)
        gsap.to('.dof-leaf-tl', {
            scrollTrigger: {
                trigger: '#zone-01',
                start: 'top top',
                end: 'bottom top',
                scrub: 1
            },
            xPercent: -120,
            yPercent: -80,
            scale: 2.2,
            opacity: 0,
            ease: 'none'
        });

        gsap.to('.dof-leaf-tr', {
            scrollTrigger: {
                trigger: '#zone-01',
                start: 'top top',
                end: 'bottom top',
                scrub: 1
            },
            xPercent: 120,
            yPercent: -80,
            scale: 2.4,
            opacity: 0,
            ease: 'none'
        });

        gsap.to('.dof-leaf-bl', {
            scrollTrigger: {
                trigger: '#zone-01',
                start: 'top top',
                end: 'bottom top',
                scrub: 1.2
            },
            xPercent: -100,
            yPercent: 100,
            scale: 2.5,
            opacity: 0,
            ease: 'none'
        });

        gsap.to('.dof-leaf-br', {
            scrollTrigger: {
                trigger: '#zone-01',
                start: 'top top',
                end: 'bottom top',
                scrub: 1.2
            },
            xPercent: 100,
            yPercent: 100,
            scale: 2.6,
            opacity: 0,
            ease: 'none'
        });

        // B. Entrance Sliding Portal Glass Doors (Zone 01 Exit)
        gsap.to('.portal-door-left', {
            scrollTrigger: {
                trigger: '#zone-01',
                start: 'top top',
                end: 'center top',
                scrub: 1
            },
            xPercent: -100,
            ease: 'power2.inOut'
        });

        gsap.to('.portal-door-right', {
            scrollTrigger: {
                trigger: '#zone-01',
                start: 'top top',
                end: 'center top',
                scrub: 1
            },
            xPercent: 100,
            ease: 'power2.inOut'
        });

        // C. Atrium Central Content Plaque Dolly In & Fade
        gsap.to('.atrium-content-plaque', {
            scrollTrigger: {
                trigger: '#zone-01',
                start: 'top top',
                end: 'bottom top',
                scrub: 0.8
            },
            scale: 1.25,
            opacity: 0,
            y: -80,
            ease: 'none'
        });

        // D. Floating Ambient Orbs Drift
        gsap.utils.toArray('.ambient-orb').forEach((orb, i) => {
            gsap.to(orb, {
                scrollTrigger: {
                    trigger: orb.closest('section') || document.body,
                    start: 'top bottom',
                    end: 'bottom top',
                    scrub: 1.5
                },
                y: (i % 2 === 0 ? -60 : 60),
                scale: 1.15,
                ease: 'none'
            });
        });
    }

    // =========================================================================
    // 2. ZONE PROGRESS TRACKLINE CONTROLLER
    // =========================================================================
    const progressNodes = document.querySelectorAll('.progress-node');
    const stemFillEl = document.querySelector('.progress-stem-fill');

    const zoneProgressMap = {
        'zone-01': '10%',
        'zone-02': '32%',
        'zone-03': '55%',
        'zone-04': '78%',
        'zone-05': '100%'
    };

    if (typeof ScrollTrigger !== 'undefined' && progressNodes.length > 0) {
        Object.keys(zoneProgressMap).forEach(zoneId => {
            const section = document.getElementById(zoneId);
            if (section) {
                ScrollTrigger.create({
                    trigger: section,
                    start: 'top center',
                    end: 'bottom center',
                    onEnter: () => {
                        if (stemFillEl) stemFillEl.style.height = zoneProgressMap[zoneId];
                        progressNodes.forEach(node => {
                            if (node.getAttribute('data-target') === zoneId) {
                                node.classList.add('is-active');
                            } else {
                                node.classList.remove('is-active');
                            }
                        });
                    },
                    onEnterBack: () => {
                        if (stemFillEl) stemFillEl.style.height = zoneProgressMap[zoneId];
                        progressNodes.forEach(node => {
                            if (node.getAttribute('data-target') === zoneId) {
                                node.classList.add('is-active');
                            } else {
                                node.classList.remove('is-active');
                            }
                        });
                    }
                });
            }
        });

        // Click to scroll
        progressNodes.forEach(node => {
            node.addEventListener('click', () => {
                const targetId = node.getAttribute('data-target');
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    // =========================================================================
    // 3. ZONE 02: TROPICAL PAVILION 3D COVERFLOW SWIPER
    // =========================================================================
    if (typeof Swiper !== 'undefined' && document.querySelector('.pavilion-swiper')) {
        new Swiper('.pavilion-swiper', {
            effect: 'coverflow',
            grabCursor: true,
            centeredSlides: true,
            slidesPerView: 'auto',
            coverflowEffect: {
                rotate: 22,
                stretch: 0,
                depth: 220,
                modifier: 1,
                slideShadows: false
            },
            loop: true,
            autoplay: {
                delay: 4500,
                disableOnInteraction: false
            },
            pagination: {
                el: '.pavilion-pagination',
                clickable: true
            }
        });
    }

    // =========================================================================
    // 4. ZONE 03: AI PLANT MATCHER & CONSULTANT SUITE
    // =========================================================================
    const isBangla = window.SMART_NURSERY_CONFIG?.lang === 'bn';
    const matcherPresetBtns = document.querySelectorAll('.matcher-preset-btn');
    const matcherOutput = document.getElementById('matcher-live-result');
    const matcherImg = document.getElementById('matcher-scanner-image');
    const matcherTagLiving = document.getElementById('matcher-tag-room');
    const matcherTagCare = document.getElementById('matcher-tag-care');
    const matcherTagSafety = document.getElementById('matcher-tag-safety');

    const matcherPresets = {
        'living-room': {
            name: isBangla ? 'মনস্টেরা ডেলিসিওসা (Monstera)' : 'Monstera Deliciosa',
            match: '98.5%',
            light: isBangla ? 'উজ্জ্বল পরোক্ষ আলো' : 'Bright Indirect Light',
            care: isBangla ? 'সহজ (সপ্তাহে ১ বার পানি)' : 'Low Effort (1x / Week)',
            safety: isBangla ? 'উঁচু স্ট্যান্ডে রাখুন' : 'Pet Safe (Elevated)',
            img: 'assets/images/plants/monstera.jpg',
            desc: isBangla ? 'বৈঠকখানার বাতাস পরিশোধন ও আভিজাত্য বাড়াতে সেরা জাত।' : 'Top-rated living room architectural centerpiece with high oxygen delivery.'
        },
        'balcony': {
            name: isBangla ? 'রয়াল থাই অর্কিড ও বোগেনভিলিয়া' : 'Royal Orchid & Desert Bougainvillea',
            match: '96.2%',
            light: isBangla ? 'সরাসরি রোদ / ব্যালকনি' : 'Full Sun / Open Air',
            care: isBangla ? 'মাঝারি (সপ্তাহে ২ বার পানি)' : 'Moderate (2x / Week)',
            safety: isBangla ? '১০০% নিরাপদ' : '100% Pet & Child Safe',
            img: 'assets/images/plants/purple-orchid.jpg',
            desc: isBangla ? 'ব্যালকনির রোদপ্রেমী রঙিন ফুলের জন্য অতুলনীয় সৌন্দর্য।' : 'Sun-tolerant prolific blooming cultivar, thriving in warm morning light.'
        },
        'work-desk': {
            name: isBangla ? 'ইচেভেরিয়া রোজ ও সাকুলেন্ট' : 'Echeveria Rose Succulent',
            match: '99.1%',
            light: isBangla ? 'টেবিল লাইট / ফিল্টার্ড সান' : 'Desk Lamp / Filtered Light',
            care: isBangla ? 'অত্যন্ত সহজ (১৫ দিনে ১ বার পানি)' : 'Zero Hassle (1x / 14 Days)',
            safety: isBangla ? 'সম্পূর্ণ নিরাপদ' : '100% Pet Safe',
            img: 'assets/images/plants/echeveria-elegans.jpg',
            desc: isBangla ? 'কম্পিউটারের পাশে বা ডেস্কে রাখার জন্য পারফেক্ট কমপ্যাক্ট সাকুলেন্ট।' : 'Compact drought-tolerant desktop plant with powdery jade-blue rosette.'
        },
        'bedroom': {
            name: isBangla ? 'স্নেক প্ল্যান্ট লরেন্টি (Oxygen Booster)' : 'Sansevieria Snake Plant Laurentii',
            match: '97.8%',
            light: isBangla ? 'কম আলোতেও বেঁচে থাকে' : 'Low to Medium Light',
            care: isBangla ? 'সহজ (১০ দিনে ১ বার পানি)' : 'Easy (1x / 10 Days)',
            safety: isBangla ? 'উঁচুতে রাখুন' : 'Nocturnal Oxygen Producer',
            img: 'assets/images/plants/snake-plant.jpg',
            desc: isBangla ? 'রাতে প্রচুর অক্সিজেন ছড়ায়, শোবার ঘরের শান্তির জন্য সেরা।' : 'NASA clean-air certified champion converting nocturnal CO2 into fresh oxygen.'
        }
    };

    matcherPresetBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            matcherPresetBtns.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');

            const presetKey = btn.getAttribute('data-preset');
            const data = matcherPresets[presetKey];
            if (!data) return;

            // Update Image
            if (matcherImg) {
                matcherImg.style.opacity = '0.3';
                matcherImg.style.transform = 'scale(0.96)';
                setTimeout(() => {
                    matcherImg.src = `${window.SMART_NURSERY_CONFIG?.baseUrl || ''}/${data.img}`;
                    matcherImg.style.opacity = '1';
                    matcherImg.style.transform = 'scale(1)';
                }, 180);
            }

            // Update Tags
            if (matcherTagLiving) matcherTagLiving.textContent = `● [Compatibility: ${data.match}]`;
            if (matcherTagCare) matcherTagCare.textContent = `● [${data.care}]`;
            if (matcherTagSafety) matcherTagSafety.textContent = `● [${data.safety}]`;

            // Update Output Card
            if (matcherOutput) {
                matcherOutput.innerHTML = `
                    <div class="p-5 rounded-2xl bg-black/60 border border-emerald-400/50 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-emerald-400 font-bold uppercase tracking-wider">${isBangla ? 'স্মার্ট এআই ম্যাচ ফলাফল' : 'Gemini AI Match Profile'}</span>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-mono text-[11px] font-bold">${data.match} Compatibility</span>
                        </div>
                        <h4 class="text-base font-black text-white">${data.name}</h4>
                        <p class="text-xs text-slate-300 leading-relaxed">${data.desc}</p>
                        <div class="pt-2 flex items-center justify-between border-t border-white/10 text-xs text-emerald-300 font-semibold">
                            <span><i class="fa-solid fa-sun mr-1"></i>${data.light}</span>
                            <a href="${window.SMART_NURSERY_CONFIG?.baseUrl || ''}/plant-matcher.php" class="text-white hover:text-emerald-400 transition underline font-bold">
                                ${isBangla ? 'পূর্ণাঙ্গ টেস্ট দিন' : 'Take Full AI Quiz'} &rarr;
                            </a>
                        </div>
                    </div>
                `;
            }
        });
    });

    // =========================================================================
    // 5. ZONE 04: TOP 5 NURSERIES ACCORDION SHOWCASE
    // =========================================================================
    const nurseryFrames = document.querySelectorAll('.nursery-frame-card');

    if (nurseryFrames.length > 0) {
        if (!document.querySelector('.nursery-frame-card.is-active')) {
            nurseryFrames[0].classList.add('is-active');
        }

        nurseryFrames.forEach(frame => {
            frame.addEventListener('mouseenter', () => {
                nurseryFrames.forEach(f => f.classList.remove('is-active'));
                frame.classList.add('is-active');
            });

            frame.addEventListener('click', (e) => {
                if (e.target.closest('a') || e.target.closest('button')) return;
                nurseryFrames.forEach(f => f.classList.remove('is-active'));
                frame.classList.add('is-active');
            });
        });
    }

    // =========================================================================
    // 6. ZONE 05: MARKETPLACE CATEGORY FILTER TABS
    // =========================================================================
    const filterPills = document.querySelectorAll('.marketplace-filter-pill');
    const specimenCards = document.querySelectorAll('.specimen-card');

    filterPills.forEach(pill => {
        pill.addEventListener('click', () => {
            filterPills.forEach(p => p.classList.remove('is-active'));
            pill.classList.add('is-active');

            const selectedCat = pill.getAttribute('data-cat');

            specimenCards.forEach(card => {
                const cardCat = card.getAttribute('data-cat');

                if (selectedCat === 'all' || cardCat === selectedCat) {
                    card.style.display = 'flex';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'scale(1)';
                    }, 50);
                } else {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 200);
                }
            });
        });
    });
});
