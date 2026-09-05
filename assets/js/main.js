/**
 * Smart Nursery Management System - Main Interactive JavaScript
 * Simple, beginner-friendly Vanilla JavaScript for UI interactions,
 * search autocomplete, cart updates, and toast notifications.
 */

document.addEventListener('DOMContentLoaded', function() {

    // 1. Auto-dismiss Flash Alert Toasts
    var flashToasts = document.querySelectorAll('.flash-toast');
    for (var i = 0; i < flashToasts.length; i++) {
        (function(toast) {
            setTimeout(function() {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                toast.style.transition = 'all 0.4s ease';
                setTimeout(function() {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 400);
            }, 5000);
        })(flashToasts[i]);
    }

    // 2. Dropdown Toggles (Profile Menu & Notifications)
    function setupDropdown(buttonId, menuId) {
        var btn  = document.getElementById(buttonId);
        var menu = document.getElementById(menuId);
        if (!btn || !menu) return;

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });

        document.addEventListener('click', function(e) {
            if (!menu.contains(e.target) && !btn.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    }

    setupDropdown('user-menu-button', 'user-menu-dropdown');
    setupDropdown('notif-menu-button', 'notif-menu-dropdown');
    setupDropdown('mobile-menu-button', 'mobile-menu-drawer');

    // 2.1 Mark All Notifications Read
    var markAllNotifsBtn = document.getElementById('btn-mark-all-notifs');
    if (markAllNotifsBtn) {
        markAllNotifsBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            var baseUrl = window.SMART_NURSERY_CONFIG ? window.SMART_NURSERY_CONFIG.baseUrl : '';
            try {
                var res = await fetch(baseUrl + '/ajax_handler.php?action=mark_all_read');
                var data = await res.json();
                if (data.success) {
                    var badge = document.getElementById('notif-badge');
                    if (badge) badge.classList.add('hidden');
                    var pill = document.getElementById('notif-unread-count-pill');
                    if (pill) pill.textContent = '0';
                    markAllNotifsBtn.remove();

                    var notifLinks = document.querySelectorAll('#notif-list-container a');
                    for (var j = 0; j < notifLinks.length; j++) {
                        notifLinks[j].classList.remove('bg-emerald-50/35', 'hover:bg-emerald-50/70');
                        notifLinks[j].classList.add('hover:bg-slate-50');
                    }
                }
            } catch (err) {
                console.error('Failed to mark notifications read:', err);
            }
        });
    }

    // 3. Mobile Search Drawer Toggle
    var mobileSearchBtn = document.getElementById('mobile-search-toggle');
    var mobileSearchDrawer = document.getElementById('mobile-search-drawer');
    var mobileSearchInput = document.getElementById('nav-mobile-search-input');
    if (mobileSearchBtn && mobileSearchDrawer) {
        mobileSearchBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileSearchDrawer.classList.toggle('hidden');
            if (!mobileSearchDrawer.classList.contains('hidden') && mobileSearchInput) {
                setTimeout(function() { mobileSearchInput.focus(); }, 50);
            }
        });
    }

    // 4. Live Search Autocomplete
    function setupLiveSearch(inputId, resultsId) {
        var input = document.getElementById(inputId);
        var results = document.getElementById(resultsId);
        if (!input || !results) return;

        var debounceTimer = null;
        var baseUrl = window.SMART_NURSERY_CONFIG ? window.SMART_NURSERY_CONFIG.baseUrl : '';
        var isBn = window.SMART_NURSERY_CONFIG && window.SMART_NURSERY_CONFIG.lang === 'bn';

        async function performSearch() {
            var q = input.value.trim();
            if (q.length < 1) {
                results.innerHTML = '';
                results.classList.add('hidden');
                return;
            }

            // Show loading spinner
            results.innerHTML = '<div class="p-4 text-center text-xs text-slate-500 flex items-center justify-center space-x-2">' +
                '<i class="fa-solid fa-circle-notch fa-spin text-emerald-600"></i>' +
                '<span>' + (isBn ? 'গাছ খোঁজা হচ্ছে...' : 'Searching botanical catalog...') + '</span>' +
                '</div>';
            results.classList.remove('hidden');

            try {
                var res = await fetch(baseUrl + '/ajax_handler.php?action=live_search&q=' + encodeURIComponent(q));
                if (!res.ok) return;
                var data = await res.json();

                if (data.success && data.results && data.results.length > 0) {
                    var html = '<div class="px-4 py-2 bg-slate-50 border-b border-slate-100 flex items-center justify-between text-[11px] text-slate-500 font-bold">' +
                        '<span>' + (isBn ? 'প্রস্তাবিত গাছ' : 'Matching Specimens') + '</span>' +
                        '<span class="text-emerald-600 font-mono">' + data.results.length + (isBn ? ' টি পাওয়া গেছে' : ' found') + '</span>' +
                        '</div>';

                    for (var k = 0; k < data.results.length; k++) {
                        var r = data.results[k];
                        html += '<a href="' + r.url + '" class="flex items-center space-x-3 p-3 hover:bg-emerald-50/80 border-b border-slate-100 last:border-0 transition group">' +
                            '<img src="' + r.image + '" alt="' + escapeHtml(r.name) + '" class="w-11 h-11 rounded-xl object-cover shrink-0 border border-slate-200 group-hover:scale-105 transition" onerror="this.onerror=null;this.src=\'' + baseUrl + '/assets/images/plants/default-plant.jpg\';">' +
                            '<div class="flex-1 min-w-0">' +
                            '<h5 class="text-xs font-black text-slate-800 group-hover:text-emerald-700 truncate transition">' + escapeHtml(r.name) + '</h5>' +
                            '<p class="text-[10px] text-slate-400 font-semibold">' + escapeHtml(r.category) + '</p>' +
                            '</div>' +
                            '<span class="text-xs font-black text-emerald-700 shrink-0">' + r.price + '</span>' +
                            '</a>';
                    }

                    html += '<a href="' + baseUrl + '/shop.php?q=' + encodeURIComponent(q) + '" class="block p-3 text-center text-xs font-black text-white bg-emerald-600 hover:bg-emerald-500 transition shadow-xs">' +
                        (isBn ? 'সকল ফলাফল দেখুন' : 'View all results in shop') + ' &rarr;</a>';

                    results.innerHTML = html;
                    results.classList.remove('hidden');
                } else {
                    results.innerHTML = '<div class="p-6 text-center text-xs text-slate-500 space-y-1">' +
                        '<i class="fa-solid fa-leaf text-slate-300 text-xl block mb-1"></i>' +
                        '<p>' + (isBn ? 'কোনো গাছ পাওয়া যায়নি:' : 'No botanical matches found for') + ' "<strong>' + escapeHtml(q) + '</strong>"</p>' +
                        '<a href="' + baseUrl + '/shop.php" class="text-[11px] text-emerald-600 font-bold hover:underline block pt-1">' + (isBn ? 'ক্যাটালগ ব্রাউজ করুন' : 'Browse full catalog') + ' &rarr;</a>' +
                        '</div>';
                    results.classList.remove('hidden');
                }
            } catch (e) {
                results.classList.add('hidden');
            }
        }

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(performSearch, 200);
        });

        input.addEventListener('focus', function() {
            if (input.value.trim().length >= 1) {
                performSearch();
            }
        });

        document.addEventListener('click', function(e) {
            if (!results.contains(e.target) && e.target !== input) {
                results.classList.add('hidden');
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                results.classList.add('hidden');
            }
        });
    }

    setupLiveSearch('nav-desktop-search', 'desktop-search-results');
    setupLiveSearch('nav-mobile-search-input', 'mobile-search-results');

    // 5. Live Notification Polling
    var notifBadge = document.getElementById('notif-badge');
    var notifList  = document.getElementById('notif-list-container');

    if (notifBadge && window.SMART_NURSERY_CONFIG && window.SMART_NURSERY_CONFIG.userId) {
        async function pollNotifications() {
            try {
                var baseUrl = window.SMART_NURSERY_CONFIG.baseUrl || '';
                var res = await fetch(baseUrl + '/ajax_handler.php?action=get_notifications');
                if (!res.ok) return;
                var data = await res.json();

                if (data.success) {
                    if (data.unread_count > 0) {
                        notifBadge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                        notifBadge.classList.remove('hidden');
                    } else {
                        notifBadge.classList.add('hidden');
                    }

                    if (notifList && data.notifications && data.notifications.length > 0) {
                        var notifHtml = '';
                        for (var n = 0; n < data.notifications.length; n++) {
                            var notif = data.notifications[n];
                            notifHtml += '<a href="' + (notif.link_url || '#') + '" class="block p-3 hover:bg-slate-50 border-b border-slate-100 transition text-left">' +
                                '<p class="text-xs font-semibold text-slate-800">' + escapeHtml(notif.title) + '</p>' +
                                '<p class="text-xs text-slate-500 line-clamp-2 mt-0.5">' + escapeHtml(notif.message) + '</p>' +
                                '<span class="text-[10px] text-slate-400 mt-1 block">' + escapeHtml(notif.time_ago) + '</span>' +
                                '</a>';
                        }
                        notifList.innerHTML = notifHtml;
                    }
                }
            } catch (err) {
                // Ignore background polling network hiccups
            }
        }

        setInterval(pollNotifications, 10000);
    }
});

// Helper: Escape HTML strings
function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}

// Global Quick Toast Dispatcher
function showToast(message, type) {
    if (!type) type = 'success';
    var container = document.getElementById('toast-container') || createToastContainer();
    var toast = document.createElement('div');
    var bgClass = type === 'success' ? 'bg-emerald-700 text-white' : (type === 'error' ? 'bg-rose-700 text-white' : 'bg-slate-800 text-white');
    var icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');

    toast.className = bgClass + ' shadow-xl rounded-xl px-4 py-3 text-sm flex items-center space-x-3 mb-2 animate-bounce transition-all duration-300 pointer-events-auto';
    toast.innerHTML = '<i class="fa-solid ' + icon + '"></i><span class="font-medium">' + escapeHtml(message) + '</span>';

    container.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 4000);
}

function createToastContainer() {
    var div = document.createElement('div');
    div.id = 'toast-container';
    div.className = 'fixed bottom-5 right-5 z-50 flex flex-col items-end pointer-events-none';
    document.body.appendChild(div);
    return div;
}

// Product Quantity Increment / Decrement Helper
function adjustQty(change) {
    var input = document.getElementById('item-qty-input');
    if (!input) return;
    var current = parseInt(input.value) || 1;
    var min = parseInt(input.min) || 1;
    var max = parseInt(input.max) || 999;
    var next = current + change;
    if (next >= min && next <= max) {
        input.value = next;
    }
}

// Global Quick Add to Cart via AJAX
async function quickAddToCart(productId, quantity) {
    if (!quantity) quantity = 1;
    try {
        var baseUrl = window.SMART_NURSERY_CONFIG ? window.SMART_NURSERY_CONFIG.baseUrl : '';
        var formData = new FormData();
        formData.append('action', 'add_to_cart');
        formData.append('product_id', productId);
        formData.append('quantity', quantity);

        var res = await fetch(baseUrl + '/ajax_handler.php', {
            method: 'POST',
            body: formData
        });

        var data = await res.json();
        if (data.success) {
            showToast(data.message || 'Plant added to cart!', 'success');
            var cartCountBadge = document.getElementById('cart-count-badge');
            if (cartCountBadge) {
                cartCountBadge.textContent = data.cart_count;
                cartCountBadge.classList.remove('hidden');
            }
        } else {
            showToast(data.error || 'Failed to add item to cart.', 'error');
        }
    } catch (err) {
        showToast('Network error while adding plant to cart.', 'error');
    }
}
