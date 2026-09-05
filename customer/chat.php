<?php
// Customer Live Vendor Chat

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('customer');
$currentUser = current_user();
$db = get_db();

$vendorId = (int)($_GET['vendor_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);
$activeConvId = (int)($_GET['conv_id'] ?? 0);

// If vendor_id is given, find or create conversation
if ($vendorId > 0 && $activeConvId <= 0) {
    $cStmt = $db->prepare("SELECT id FROM conversations WHERE customer_id = :cid AND vendor_id = :vid");
    $cStmt->execute(['cid' => $currentUser['id'], 'vid' => $vendorId]);
    $existing = $cStmt->fetch();

    if ($existing) {
        $activeConvId = (int)$existing['id'];
        if ($productId > 0) {
            $db->prepare("UPDATE conversations SET product_id = :pid WHERE id = :id")->execute(['pid' => $productId, 'id' => $activeConvId]);
        }
    } else {
        $ins = $db->prepare("INSERT INTO conversations (customer_id, vendor_id, product_id, created_at, last_message_at) VALUES (:cid, :vid, :pid, NOW(), NOW())");
        $ins->execute(['cid' => $currentUser['id'], 'vid' => $vendorId, 'pid' => $productId > 0 ? $productId : null]);
        $activeConvId = (int)$db->lastInsertId();
    }
}

// Fetch Customer's Conversations List
$convsStmt = $db->prepare("
    SELECT c.*, vp.nursery_name, vp.nursery_name_bn, vp.address, vp.city, u.avatar AS vendor_avatar, u.phone AS vendor_phone,
           (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_message,
           (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND receiver_id = :uid AND is_read = 0) AS unread_count
    FROM conversations c
    JOIN vendor_profiles vp ON c.vendor_id = vp.id
    JOIN users u ON vp.user_id = u.id
    WHERE c.customer_id = :cid
    ORDER BY c.last_message_at DESC
");
$convsStmt->execute(['uid' => $currentUser['id'], 'cid' => $currentUser['id']]);
$conversations = $convsStmt->fetchAll();

// Select first conversation if none explicitly selected
if ($activeConvId <= 0 && !empty($conversations)) {
    $activeConvId = (int)$conversations[0]['id'];
}

// If customer has no active conversation, find or create one with the primary verified nursery
if ($activeConvId <= 0 && empty($conversations)) {
    $firstVendor = $db->query("SELECT id FROM vendor_profiles WHERE verification_status = 'verified' ORDER BY id ASC LIMIT 1")->fetch();
    if ($firstVendor) {
        $vid = (int)$firstVendor['id'];
        $ins = $db->prepare("INSERT INTO conversations (customer_id, vendor_id, product_id, created_at, last_message_at) VALUES (:cid, :vid, :pid, NOW(), NOW())");
        $ins->execute(['cid' => $currentUser['id'], 'vid' => $vid, 'pid' => $productId > 0 ? $productId : null]);
        $activeConvId = (int)$db->lastInsertId();
        
        $convsStmt->execute(['uid' => $currentUser['id'], 'cid' => $currentUser['id']]);
        $conversations = $convsStmt->fetchAll();
    }
}

// Fetch Active Conversation Details & Product Card
$activeConv = null;
$linkedProduct = null;

if ($activeConvId > 0) {
    $actStmt = $db->prepare("
        SELECT c.*, vp.nursery_name, vp.nursery_name_bn, vp.address AS vendor_address, vp.city AS vendor_city,
               u.name AS vendor_owner_name, u.phone AS vendor_phone, u.avatar AS vendor_avatar
        FROM conversations c
        JOIN vendor_profiles vp ON c.vendor_id = vp.id
        JOIN users u ON vp.user_id = u.id
        WHERE c.id = :id AND c.customer_id = :cid
    ");
    $actStmt->execute(['id' => $activeConvId, 'cid' => $currentUser['id']]);
    $activeConv = $actStmt->fetch();

    if ($activeConv && !empty($activeConv['product_id'])) {
        $pStmt = $db->prepare("
            SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
            FROM products p WHERE p.id = :pid
        ");
        $pStmt->execute(['pid' => $activeConv['product_id']]);
        $linkedProduct = $pStmt->fetch();
    }
}

$pageTitle = is_bangla() ? 'লাইভ নার্সারি চ্যাট' : 'Live Nursery Chat';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    
    <!-- Top Action Navigation Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-700 uppercase tracking-wider">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span><?= is_bangla() ? 'কাস্টমার মেসেজিং হাব' : 'Customer Messaging Hub' ?></span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 flex items-center space-x-2.5 mt-0.5">
                <span><?= is_bangla() ? 'নার্সারি সরাসরি বার্তা ও সহায়তা' : 'Nursery Direct Live Chat' ?></span>
                <span class="text-emerald-600 text-lg"><i class="fa-solid fa-comments"></i></span>
            </h1>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <a href="index.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200 shadow-2xs transition">
                <i class="fa-solid fa-clock-rotate-left mr-1 text-slate-400"></i><?= __('customer.order_history', 'অর্ডার হিস্টোরি') ?>
            </a>
            <a href="reviews.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200 shadow-2xs transition">
                <i class="fa-solid fa-star mr-1 text-amber-400"></i><?= __('customer.verified_reviews', 'রিভিউসমূহ') ?>
            </a>
            <a href="chat.php" class="px-3.5 py-2 bg-emerald-600 text-white font-bold rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <i class="fa-solid fa-comment-dots"></i>
                <span><?= is_bangla() ? 'লাইভ চ্যাট' : 'Live Chat' ?></span>
            </a>
            <a href="profile.php" class="px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-100 font-semibold rounded-xl border border-slate-200 shadow-2xs transition">
                <i class="fa-solid fa-user mr-1 text-slate-400"></i><?= __('customer.profile_settings', 'প্রোফাইল') ?>
            </a>
        </div>
    </div>

    <!-- Main WhatsApp-Style Split View -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-lg overflow-hidden grid grid-cols-1 md:grid-cols-12 min-h-[620px]">
        
        <!-- Left: Conversations Sidebar List -->
        <div class="md:col-span-4 lg:col-span-4 border-r border-slate-200 flex flex-col bg-slate-50/70">
            
            <!-- Sidebar Header & Live Search Box -->
            <div class="p-3.5 border-b border-slate-200/80 bg-white">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center space-x-2">
                        <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-comment"></i>
                        </div>
                        <h2 class="font-extrabold text-sm text-slate-900"><?= is_bangla() ? 'নার্সারি চ্যাট তালিকা' : 'Conversations' ?></h2>
                    </div>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                        <?= count($conversations) ?>
                    </span>
                </div>

                <!-- Instant Search Input -->
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" id="chat-conv-search" placeholder="<?= is_bangla() ? 'নার্সারি খুঁজুন...' : 'Search discussions...' ?>"
                           class="w-full bg-slate-100/90 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                </div>
            </div>

            <!-- Conversations Scrollable Feed -->
            <div class="divide-y divide-slate-100 overflow-y-auto flex-1 max-h-[520px]">
                <?php if (!empty($conversations)): ?>
                    <?php foreach ($conversations as $c): ?>
                        <?php 
                            $isActive = ($activeConvId === (int)$c['id']);
                            $displayName = vendor_display_name($c);
                            $lastMsgPreview = $c['last_message'] ?: (is_bangla() ? 'কথোপকথন শুরু হয়েছে' : 'Conversation started');
                        ?>
                        <a href="chat.php?conv_id=<?= $c['id'] ?>" 
                           data-title="<?= sanitize($displayName) ?>"
                           data-preview="<?= sanitize($lastMsgPreview) ?>"
                           class="conv-list-item block p-3.5 hover:bg-emerald-50/70 transition <?= $isActive ? 'bg-emerald-50/90 border-l-4 border-emerald-600 shadow-2xs' : 'bg-transparent' ?>">
                            <div class="flex items-start space-x-3">
                                <div class="relative shrink-0">
                                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-emerald-700 to-teal-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                        <i class="fa-solid fa-store"></i>
                                    </div>
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-emerald-500 border-2 border-white"></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-0.5">
                                        <h4 class="font-bold text-xs text-slate-900 truncate flex items-center space-x-1">
                                            <span><?= sanitize($displayName) ?></span>
                                            <i class="fa-solid fa-circle-check text-[10px] text-emerald-500 shrink-0" title="Verified Specialty Nursery"></i>
                                        </h4>
                                        <span class="text-[10px] font-medium text-slate-400 shrink-0 ml-1"><?= time_ago($c['last_message_at']) ?></span>
                                    </div>
                                    <p class="text-xs text-slate-500 truncate <?= $c['unread_count'] > 0 ? 'font-bold text-slate-900' : '' ?>">
                                        <?= sanitize($lastMsgPreview) ?>
                                    </p>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-[10px] text-slate-400 truncate">
                                            <i class="fa-solid fa-location-dot text-[9px] text-slate-300 mr-0.5"></i><?= sanitize($c['city'] ?: 'Dhaka') ?>
                                        </span>
                                        <?php if ($c['unread_count'] > 0): ?>
                                            <span class="bg-emerald-600 text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded-full shadow-2xs">
                                                <?= $c['unread_count'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-xs text-slate-400 flex flex-col items-center justify-center h-48 space-y-2">
                        <i class="fa-regular fa-comments text-3xl text-slate-300"></i>
                        <p><?= is_bangla() ? 'কোনো মেসেজ নেই। যেকোনো গাছের পেজ থেকে মেসেজ পাঠাতে পারেন!' : 'No active chats yet. Inquire directly on any plant page!' ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Modern WhatsApp / Messenger Chat Interface -->
        <div class="md:col-span-8 lg:col-span-8 flex flex-col justify-between bg-white relative">
            <?php if ($activeConv): ?>
                
                <!-- Chat Window Header -->
                <div class="p-3.5 sm:p-4 border-b border-slate-200/90 flex items-center justify-between bg-white z-10 shadow-2xs">
                    <div class="flex items-center space-x-3">
                        <div class="relative shrink-0">
                            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-700 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                <i class="fa-solid fa-store"></i>
                            </div>
                            <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white"></span>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-slate-900 text-sm flex items-center space-x-1.5">
                                <span><?= sanitize(vendor_display_name($activeConv)) ?></span>
                                <span class="text-emerald-600 text-xs"><i class="fa-solid fa-circle-check"></i></span>
                            </h3>
                            <div class="flex items-center space-x-2 text-[11px] text-emerald-600 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span><?= is_bangla() ? 'সক্রিয় আছেন • দ্রুত উত্তর প্রদানকারী' : 'Active now • Verified Nursery' ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Header Utility Buttons -->
                    <div class="flex items-center space-x-2">
                        <?php if (!empty($activeConv['vendor_phone'])): ?>
                            <a href="tel:<?= sanitize($activeConv['vendor_phone']) ?>" class="p-2 rounded-xl bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-600 text-xs transition flex items-center space-x-1" title="Call Nursery">
                                <i class="fa-solid fa-phone"></i>
                                <span class="hidden sm:inline font-bold"><?= sanitize($activeConv['vendor_phone']) ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attached Product Banner (if inquiry is tied to a botanical plant) -->
                <?php if ($linkedProduct): ?>
                    <div class="bg-emerald-50/90 border-b border-emerald-100 px-4 py-2.5 flex items-center justify-between text-xs z-10">
                        <div class="flex items-center space-x-3 truncate">
                            <img src="<?= sanitize(get_image_url($linkedProduct['primary_image'])) ?>" alt="Product" class="w-9 h-9 rounded-xl object-cover border border-emerald-200 shrink-0">
                            <div class="truncate">
                                <span class="font-bold text-slate-900 block truncate"><?= sanitize(product_display_name($linkedProduct)) ?></span>
                                <span class="text-emerald-700 font-extrabold text-[11px]"><?= format_currency($linkedProduct['price']) ?></span>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>/product.php?id=<?= $linkedProduct['id'] ?>" target="_blank" class="shrink-0 px-2.5 py-1 bg-white hover:bg-emerald-600 hover:text-white text-emerald-700 font-bold text-[11px] rounded-lg border border-emerald-200 shadow-2xs transition">
                            <?= is_bangla() ? 'গাছটি দেখুন &rarr;' : 'View Plant &rarr;' ?>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Messages Wall (Authentic WhatsApp Pattern Wallpaper) -->
                <div id="chat-messages-box" class="p-4 sm:p-6 overflow-y-auto flex-1 max-h-[380px] wa-chat-wallpaper space-y-1">
                    <!-- Populated in real-time by SmartNurseryChat engine -->
                </div>

                <!-- Quick Inquiry Suggestion Chips -->
                <div class="px-4 py-2 bg-slate-50 border-t border-slate-100 flex items-center space-x-2 overflow-x-auto no-scrollbar text-xs z-10">
                    <span class="text-[10px] font-bold uppercase text-slate-400 shrink-0"><?= is_bangla() ? 'দ্রুত প্রশ্ন:' : 'Quick:' ?></span>
                    <button type="button" onclick="setQuickReply(this)" class="chat-quick-chip shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-medium border border-slate-200 text-[11px] transition shadow-2xs cursor-pointer"
                            data-text="<?= is_bangla() ? 'এই গাছটি কি এখন স্টকে আছে?' : 'Is this plant available in fresh stock?' ?>">
                        🌿 <?= is_bangla() ? 'স্টকে আছে?' : 'In Stock?' ?>
                    </button>
                    <button type="button" onclick="setQuickReply(this)" class="chat-quick-chip shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-medium border border-slate-200 text-[11px] transition shadow-2xs cursor-pointer"
                            data-text="<?= is_bangla() ? 'ঢাকায় ডেলিভারি চার্জ এবং সময় কত লাগবে?' : 'What is the delivery timeline and charge to Dhaka?' ?>">
                        🚚 <?= is_bangla() ? 'ডেলিভারি সময়?' : 'Delivery Time?' ?>
                    </button>
                    <button type="button" onclick="setQuickReply(this)" class="chat-quick-chip shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-medium border border-slate-200 text-[11px] transition shadow-2xs cursor-pointer"
                            data-text="<?= is_bangla() ? 'গাছটির বাস্তব বা লাইভ কোনো ছবি দেখতে পারি?' : 'Can you share a live photo of the actual potted plant?' ?>">
                        📸 <?= is_bangla() ? 'বাস্তব ছবি?' : 'Live Photo?' ?>
                    </button>
                    <button type="button" onclick="setQuickReply(this)" class="chat-quick-chip shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-medium border border-slate-200 text-[11px] transition shadow-2xs cursor-pointer"
                            data-text="<?= is_bangla() ? 'গাছটির আলো এবং পানি দেওয়ার নিয়ম কী?' : 'What are the sunlight and watering care instructions?' ?>">
                        💧 <?= is_bangla() ? 'পরিচর্যার নিয়ম?' : 'Care Guide?' ?>
                    </button>
                </div>

                <!-- Floating Emoji Picker Tray -->
                <div id="chat-emoji-picker" class="hidden absolute left-4 bottom-16 bg-white rounded-2xl shadow-2xl border border-slate-200 p-3 z-50 w-72">
                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2 border-b border-slate-100 pb-1">
                        <?= is_bangla() ? 'ইমোজি বেছে নিন' : 'Botanical & Reaction Emojis' ?>
                    </div>
                    <div class="grid grid-cols-6 gap-2 text-xl text-center">
                        <button type="button" onclick="insertEmoji('🌿')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="🌿">🌿</button>
                        <button type="button" onclick="insertEmoji('🌱')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="🌱">🌱</button>
                        <button type="button" onclick="insertEmoji('🪴')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="🪴">🪴</button>
                        <button type="button" onclick="insertEmoji('🌸')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="🌸">🌸</button>
                        <button type="button" onclick="insertEmoji('🌺')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="🌺">🌺</button>
                        <button type="button" onclick="insertEmoji('🍀')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="🍀">🍀</button>
                        <button type="button" onclick="insertEmoji('💚')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="💚">💚</button>
                        <button type="button" onclick="insertEmoji('👍')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="👍">👍</button>
                        <button type="button" onclick="insertEmoji('❤️')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="❤️">❤️</button>
                        <button type="button" onclick="insertEmoji('✨')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="✨">✨</button>
                        <button type="button" onclick="insertEmoji('📦')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="📦">📦</button>
                        <button type="button" onclick="insertEmoji('🚚')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="🚚">🚚</button>
                        <button type="button" onclick="insertEmoji('💵')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="💵">💵</button>
                        <button type="button" onclick="insertEmoji('📍')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="📍">📍</button>
                        <button type="button" onclick="insertEmoji('📞')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="📞">📞</button>
                        <button type="button" onclick="insertEmoji('🙏')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="🙏">🙏</button>
                        <button type="button" onclick="insertEmoji('😊')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="😊">😊</button>
                        <button type="button" onclick="insertEmoji('🎉')" class="emoji-btn cursor-pointer p-1 hover:scale-125 transition transform" data-emoji="🎉">🎉</button>
                    </div>
                </div>

                <!-- Modern WhatsApp Style Input Form -->
                <form id="chat-send-form" class="p-3 sm:p-4 border-t border-slate-200/90 flex items-center space-x-2.5 bg-white relative z-20">
                    <!-- Emoji Toggle Button -->
                    <button type="button" id="chat-emoji-toggle" onclick="toggleEmojiTray(event)" class="p-2.5 rounded-full text-slate-500 hover:text-amber-500 hover:bg-slate-100 transition cursor-pointer" title="Add Emoji">
                        <i class="fa-regular fa-face-smile text-lg pointer-events-none"></i>
                    </button>

                    <!-- Text Message Input -->
                    <div class="flex-1 relative">
                        <input id="chat-message-input" type="text" placeholder="<?= is_bangla() ? 'আপনার বার্তা লিখুন (Enter চেপে পাঠান)...' : 'Type a message (Press Enter to send)...' ?>" required autocomplete="off"
                               class="w-full bg-slate-100/90 hover:bg-slate-100 border border-slate-200 rounded-full px-5 py-2.5 text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                    </div>

                    <!-- Send Action Button -->
                    <button type="submit" class="w-10 h-10 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center shadow-md shadow-emerald-700/20 hover:scale-105 active:scale-95 transition transform cursor-pointer" title="Send Message">
                        <i class="fa-solid fa-paper-plane text-sm ml-0.5 pointer-events-none"></i>
                    </button>
                </form>

                <!-- Initialize Chat Engine -->
                <script src="<?= BASE_URL ?>/assets/js/chat.js"></script>
                <script>
                    (function() {
                        function initSmartChat() {
                            if (typeof SmartNurseryChat !== 'undefined') {
                                window.activeSmartChat = new SmartNurseryChat({
                                    conversationId: <?= $activeConvId ?>,
                                    currentUserId: <?= $currentUser['id'] ?>,
                                    baseUrl: "<?= BASE_URL ?>"
                                });
                            }
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initSmartChat);
                        } else {
                            initSmartChat();
                        }
                    })();
                </script>

            <?php else: ?>
                <div class="p-16 text-center text-slate-400 text-xs flex flex-col items-center justify-center h-full space-y-3 wa-chat-wallpaper">
                    <div class="w-16 h-16 rounded-full bg-white/80 flex items-center justify-center text-emerald-600 text-2xl shadow-sm">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <h3 class="font-extrabold text-slate-800 text-base"><?= is_bangla() ? 'কথোপকথন নির্বাচন করুন' : 'Select a Discussion' ?></h3>
                    <p class="max-w-xs text-slate-500"><?= is_bangla() ? 'বাম পাশের তালিকা থেকে যেকোনো নার্সারির মেসেজে ক্লিক করে চ্যাট শুরু করুন।' : 'Choose a nursery conversation from the left sidebar to send and receive live messages.' ?></p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

