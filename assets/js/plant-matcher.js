/**
 * Smart Nursery Management System - Plant Matcher Interactive Wizard (Bilingual)
 * Matches user lifestyle inputs with real in-stock catalog plants via algorithmic scoring.
 * Enhanced with responsive grid cards, unbreakable price labels, and spacious button controls.
 */

document.addEventListener('DOMContentLoaded', () => {
    const wizardForm = document.getElementById('ai-wizard-form');
    const resultsContainer = document.getElementById('ai-results-section');
    const resultsGrid = document.getElementById('ai-recommendations-grid');
    const loadingSpinner = document.getElementById('ai-loading-indicator');

    if (!wizardForm) return;

    wizardForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Collect Form Data
        const formData = new FormData(wizardForm);
        formData.append('action', 'ai_recommend');

        // Show loading state
        if (loadingSpinner) loadingSpinner.classList.remove('hidden');
        if (resultsContainer) resultsContainer.classList.add('hidden');

        try {
            const baseUrl = window.SMART_NURSERY_CONFIG?.baseUrl || '';
            const res = await fetch(`${baseUrl}/ajax_handler.php`, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (loadingSpinner) loadingSpinner.classList.add('hidden');

            if (data.success && data.recommendations && data.recommendations.length > 0) {
                renderRecommendations(data.recommendations);
                if (resultsContainer) {
                    resultsContainer.classList.remove('hidden');
                    resultsContainer.scrollIntoView({ behavior: 'smooth' });
                }
            } else {
                const isBn = window.SMART_NURSERY_CONFIG?.lang === 'bn';
                alert(data.error || (isBn ? 'বর্তমান স্টকে কোন উপযুক্ত গাছ পাওয়া যায়নি। অনুগ্রহ করে অন্যান্য ফিল্টার চেষ্টা করুন।' : 'No matching plants found in the current stock. Try broader preferences!'));
            }
        } catch (err) {
            if (loadingSpinner) loadingSpinner.classList.add('hidden');
            const isBn = window.SMART_NURSERY_CONFIG?.lang === 'bn';
            alert(isBn ? 'সার্ভারের সাথে সংযোগ করা সম্ভব হয়নি। অনুগ্রহ করে আবার চেষ্টা করুন।' : 'Failed to connect to the plant matcher service. Please try again.');
        }
    });

    function renderRecommendations(items) {
        if (!resultsGrid) return;
        const baseUrl = window.SMART_NURSERY_CONFIG?.baseUrl || '';
        const currency = window.SMART_NURSERY_CONFIG?.currency || '৳';
        const isBn = window.SMART_NURSERY_CONFIG?.lang === 'bn';

        const toBnNum = (n) => {
            const en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            const bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
            return String(n).replace(/[0-9]/g, d => bn[en.indexOf(d)]);
        };

        const catMap = {
            'Indoor Foliage': 'ইনডোর পাতা বাহার',
            'Air Purifying Plants': 'বাতাস পরিশোধক গাছ',
            'Air Purifying': 'বাতাস পরিশোধক গাছ',
            'Succulents & Cacti': 'সাকুলেন্ট ও ক্যাকটাস',
            'Flowering Beauties': 'ফুল গাছ ও সৌন্দর্য',
            'Bonsai & Exotics': 'বনসাই ও দুর্লভ প্রজাতি',
            'Medicinal & Herbs': 'ভেষজ ও ঔষধি গাছ',
            'Pots & Plant Care': 'টব ও পরিচর্যার সরঞ্জাম'
        };

        const vendorMap = {
            'Green Haven Botanicals Ltd.': 'গ্রিন হেভেন বোটানিক্যালস লি.',
            'Green Haven Nursery': 'গ্রিন হেভেন নার্সারি',
            'Urban Jungle Nursery': 'আরবান জঙ্গল নার্সারি'
        };

        resultsGrid.innerHTML = items.map(p => {
            const score = p.ai_match_score || 95;
            const scoreDisplay = isBn ? `${toBnNum(score)}% উপযুক্ত` : `${score}% Match`;
            const priceVal = parseFloat(p.discount_price || p.price).toFixed(2);
            const priceDisplay = isBn ? toBnNum(priceVal) : priceVal;
            const petSafeLabel = isBn ? 'পোষা প্রাণীর জন্য নিরাপদ' : 'Pet-Safe';
            const whyLabel = isBn ? 'কেন এটি উপযুক্ত:' : 'Why this was matched:';
            const priceLabel = isBn ? 'মূল্য' : 'Price';
            const viewDetailsLabel = isBn ? 'বিস্তারিত দেখুন' : 'View Details';
            const addLabel = isBn ? 'কার্টে যোগ' : 'Add to Cart';

            const displayName = isBn && p.name_bn ? p.name_bn : p.name;
            const displayCat = isBn ? (p.category_name_bn || catMap[p.category_name] || p.category_name || 'গাছ') : (p.category_name || 'Plant');
            const displayVendor = isBn ? (p.nursery_name_bn || vendorMap[p.nursery_name] || p.nursery_name || 'ভেরিফাইড নার্সারি') : (p.nursery_name || 'Verified Nursery');

            const fallbackImg = (baseUrl ? baseUrl.replace(/\/+$/, '') : '') + '/assets/images/plants/default-plant.jpg';
            let imgSrc = p.image_url || p.primary_image || fallbackImg;
            if (imgSrc && !imgSrc.startsWith('http://') && !imgSrc.startsWith('https://') && !imgSrc.startsWith('//') && !imgSrc.startsWith('data:')) {
                imgSrc = (baseUrl ? baseUrl.replace(/\/+$/, '') : '') + '/' + imgSrc.replace(/^\/+/, '');
            }

            return `
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col hover:-translate-y-1 group">
                
                <!-- Card Header Image & Badges -->
                <div class="relative h-64 bg-slate-100 overflow-hidden">
                    <img src="${imgSrc}" 
                         alt="${displayName}" 
                         onerror="this.onerror=null;this.src='${fallbackImg}'"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    
                    <!-- Top Match Badge -->
                    <div class="absolute top-4 left-4 bg-emerald-600/95 backdrop-blur-md text-white text-xs font-black px-3.5 py-1.5 rounded-full shadow-lg flex items-center space-x-1.5">
                        <i class="fa-solid fa-seedling text-emerald-200"></i>
                        <span>${scoreDisplay}</span>
                    </div>

                    ${parseInt(p.is_pet_friendly) === 1 ? `
                    <div class="absolute top-4 right-4 bg-amber-500/95 backdrop-blur-md text-white text-xs font-black px-3 py-1.5 rounded-full shadow-lg flex items-center space-x-1">
                        <i class="fa-solid fa-paw text-[10px]"></i>
                        <span>${petSafeLabel}</span>
                    </div>
                    ` : ''}
                </div>

                <!-- Card Content -->
                <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-400 font-bold uppercase tracking-wider">
                            <span>${displayCat}</span>
                            <span class="text-emerald-700 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-store text-[10px]"></i>
                                ${displayVendor}
                            </span>
                        </div>
                        
                        <h4 class="text-lg font-black text-slate-900 group-hover:text-emerald-600 transition-colors">
                            ${displayName}
                        </h4>

                        ${p.scientific_name ? `
                        <p class="text-xs text-slate-600 italic font-mono font-medium">${p.scientific_name}</p>
                        ` : ''}

                        <!-- Botanical Match Reason Box -->
                        <div class="p-3.5 bg-emerald-50/70 border border-emerald-100 rounded-2xl text-xs text-emerald-950 mt-3">
                            <span class="font-bold flex items-center gap-1 text-emerald-800 mb-1">
                                <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                ${whyLabel}
                            </span>
                            <p class="text-slate-800 leading-relaxed font-normal">${p.ai_reason || (isBn ? 'আপনার ঘরের আলো ও যত্নের পরিমাণের সাথে সম্পূর্ণ মানানসই।' : 'Perfect match for your lighting and care schedule.')}</p>
                        </div>
                    </div>

                    <!-- Price & Actions Section -->
                    <div class="pt-4 border-t border-slate-100 mt-2 space-y-3">
                        <!-- Dedicated Price Row (No wrapping) -->
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">${priceLabel}</span>
                            <div class="flex items-baseline gap-1 text-emerald-700 font-black text-xl whitespace-nowrap">
                                <span class="text-base font-bold text-emerald-600">${currency}</span>
                                <span>${priceDisplay}</span>
                            </div>
                        </div>

                        <!-- 50/50 Button Grid -->
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <a href="${baseUrl}/product.php?id=${p.id}" 
                               class="py-2.5 px-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs rounded-xl text-center transition flex items-center justify-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-600"></i>
                                <span class="whitespace-nowrap">${viewDetailsLabel}</span>
                            </a>
                            <button onclick="quickAddToCart(${p.id}, 1)" 
                                    class="py-2.5 px-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-700/20 transition flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-cart-plus text-[11px] text-emerald-200"></i>
                                <span class="whitespace-nowrap">${addLabel}</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
            `;
        }).join('');
    }
});
