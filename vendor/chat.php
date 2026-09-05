<?php
// Vendor Customer Inquiries Live Chat

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('vendor');
$currentUser = current_user();
$db = get_db();

// Fetch Vendor Profile
$vStmt = $db->prepare("SELECT id, nursery_name FROM vendor_profiles WHERE user_id = :uid");
$vStmt->execute(['uid' => $currentUser['id']]);
$vendor = $vStmt->fetch();
$vendorId = $vendor['id'];

$activeConvId = (int)($_GET['conv_id'] ?? 0);

// Fetch Conversations for this Vendor
$convsStmt = $db->prepare("
    SELECT c.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email, u.avatar AS customer_avatar,
           (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_message,
           (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND receiver_id = :uid AND is_read = 0) AS unread_count
    FROM conversations c
    JOIN users u ON c.customer_id = u.id
    WHERE c.vendor_id = :vid
    ORDER BY c.last_message_at DESC
");
$convsStmt->execute(['uid' => $currentUser['id'], 'vid' => $vendorId]);
$conversations = $convsStmt->fetchAll();

if ($activeConvId <= 0 && !empty($conversations)) {
    $activeConvId = (int)$conversations[0]['id'];
}

// Fetch Active Conversation Details & Product Card
$activeConv = null;
$linkedProduct = null;

if ($activeConvId > 0) {
    $actStmt = $db->prepare("
        SELECT c.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone, u.avatar AS customer_avatar
        FROM conversations c
        JOIN users u ON c.customer_id = u.id
        WHERE c.id = :id AND c.vendor_id = :vid
    ");
    $actStmt->execute(['id' => $activeConvId, 'vid' => $vendorId]);
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

$pageTitle = is_bangla() ? 'গ্রাহক বার্তা ও লাইভ চ্যাট' : 'Customer Inquiries & Live Chat';
$activePage = 'chat';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-4 sm:p-8 space-y-6 bg-slate-50">
        
        <div class="border-b border-slate-200 pb-4">
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-700 uppercase tracking-wider">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span><?= is_bangla() ? 'রিয়েল-টাইম যোগাযোগ হাব' : 'Real-Time Communication Hub' ?></span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 flex items-center space-x-2 mt-0.5">
                <span><?= is_bangla() ? 'গ্রাহকদের অনুসন্ধান ও চ্যাট' : 'Customer Inquiries & Live Chat' ?></span>
                <span class="text-emerald-600 text-lg"><i class="fa-solid fa-comments"></i></span>
            </h1>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-lg overflow-hidden grid grid-cols-1 md:grid-cols-12 min-h-[620px]">
            
            <!-- Left: Conversation List -->
            <div class="md:col-span-4 lg:col-span-4 border-r border-slate-200 flex flex-col bg-slate-50/70">
                
                <!-- Search Box in Sidebar -->
                <div class="p-3.5 border-b border-slate-200/80 bg-white">
                    <div class="flex items-center justify-between mb-2.5">
                        <div class="flex items-center space-x-2">
                            <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">
                                <i class="fa-solid fa-inbox"></i>
                            </div>
                            <h2 class="font-extrabold text-sm text-slate-900"><?= is_bangla() ? 'গ্রাহক তালিকা' : 'Customer Chats' ?></h2>
                        </div>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                            <?= count($conversations) ?>
                        </span>
                    </div>

                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" id="chat-conv-search" placeholder="<?= is_bangla() ? 'গ্রাহকের নাম খুঁজুন...' : 'Search customers...' ?>"
                               class="w-full bg-slate-100/90 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                    </div>
                </div>

                <div class="divide-y divide-slate-100 overflow-y-auto flex-1 max-h-[520px]">
                    <?php if (!empty($conversations)): ?>
                        <?php foreach ($conversations as $c): ?>
                            <?php 
                                $isActive = ($activeConvId === (int)$c['id']);
                                $lastMsgPreview = $c['last_message'] ?: (is_bangla() ? 'নতুন কথোপকথন' : 'New conversation opened');
                            ?>
                            <a href="chat.php?conv_id=<?= $c['id'] ?>" 
                               data-title="<?= sanitize($c['customer_name']) ?>"
                               data-preview="<?= sanitize($lastMsgPreview) ?>"
                               class="conv-list-item block p-3.5 hover:bg-emerald-50/70 transition <?= $isActive ? 'bg-emerald-50/90 border-l-4 border-emerald-600 shadow-2xs' : 'bg-transparent' ?>">
                                <div class="flex items-start space-x-3">
                                    <div class="relative shrink-0">
                                        <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                            <?= strtoupper(substr($c['customer_name'], 0, 1)) ?>
                                        </div>
                                        <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-emerald-500 border-2 border-white"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-0.5">
                                            <h4 class="font-bold text-xs text-slate-900 truncate">
                                                <?= sanitize($c['customer_name']) ?>
                                            </h4>
                                            <span class="text-[10px] font-medium text-slate-400 shrink-0 ml-1"><?= time_ago($c['last_message_at']) ?></span>
                                        </div>
                                        <p class="text-xs text-slate-500 truncate <?= $c['unread_count'] > 0 ? 'font-bold text-slate-900' : '' ?>">
                                            <?= sanitize($lastMsgPreview) ?>
                                        </p>
                                        <div class="flex items-center justify-between mt-1">
                                            <span class="text-[10px] text-slate-400 truncate">
                                                <i class="fa-solid fa-phone text-[9px] text-slate-300 mr-0.5"></i><?= sanitize($c['customer_phone'] ?: 'Online') ?>
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
                            <p><?= is_bangla() ? 'এখনও কোনো গ্রাহক মেসেজ পাঠায়নি।' : 'No customer inquiries received yet.' ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Active Chat Window -->
            <div class="md:col-span-8 lg:col-span-8 flex flex-col justify-between bg-white relative">
                <?php if ($activeConv): ?>
                    
                    <!-- Chat Header -->
                    <div class="p-3.5 sm:p-4 border-b border-slate-200/90 flex items-center justify-between bg-white z-10 shadow-2xs">
                        <div class="flex items-center space-x-3">
                            <div class="relative shrink-0">
                                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                    <?= strtoupper(substr($activeConv['customer_name'], 0, 1)) ?>
                                </div>
                                <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white"></span>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-slate-900 text-sm flex items-center space-x-1.5">
                                    <span><?= sanitize($activeConv['customer_name']) ?></span>
                                    <span class="text-emerald-600 text-xs"><i class="fa-solid fa-circle-check"></i></span>
                                </h3>
                                <div class="flex items-center space-x-2 text-[11px] text-slate-400 font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span><?= sanitize($activeConv['customer_phone'] ?: $activeConv['customer_email']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Actions -->
                        <div class="flex items-center space-x-2">
                            <?php if (!empty($activeConv['customer_phone'])): ?>
                                <a href="tel:<?= sanitize($activeConv['customer_phone']) ?>" class="p-2 rounded-xl bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-600 text-xs transition flex items-center space-x-1" title="Call Customer">
                                    <i class="fa-solid fa-phone"></i>
                                    <span class="hidden sm:inline font-bold"><?= sanitize($activeConv['customer_phone']) ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Attached Product Banner (if customer inquired about a specific plant) -->
                    <?php if ($linkedProduct): ?>
                        <div class="bg-emerald-50/90 border-b border-emerald-100 px-4 py-2.5 flex items-center justify-between text-xs z-10">
                            <div class="flex items-center space-x-3 truncate">
                                <img src="<?= sanitize(get_image_url($linkedProduct['primary_image'])) ?>" alt="Product" class="w-9 h-9 rounded-xl object-cover border border-emerald-200 shrink-0">
                                <div class="truncate">
                                    <span class="font-bold text-slate-900 block truncate"><?= sanitize(product_display_name($linkedProduct)) ?></span>
                                    <span class="text-emerald-700 font-extrabold text-[11px]"><?= format_currency($linkedProduct['price']) ?> • <?= is_bangla() ? 'স্টক:' : 'Stock:' ?> <?= format_num_i18n($linkedProduct['stock_quantity']) ?></span>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>/product.php?id=<?= $linkedProduct['id'] ?>" target="_blank" class="shrink-0 px-2.5 py-1 bg-white hover:bg-emerald-600 hover:text-white text-emerald-700 font-bold text-[11px] rounded-lg border border-emerald-200 shadow-2xs transition">
                                <?= is_bangla() ? 'গাছটি দেখুন &rarr;' : 'View Plant &rarr;' ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Messages Wallpaper Feed -->
                    <div id="chat-messages-box" class="p-4 sm:p-6 overflow-y-auto flex-1 max-h-[380px] wa-chat-wallpaper space-y-1">
                        <!-- Populated by SmartNurseryChat -->
                    </div>

                    <!-- Quick Vendor Response Chips -->
                    <div class="px-4 py-2 bg-slate-50 border-t border-slate-100 flex items-center space-x-2 overflow-x-auto no-scrollbar text-xs z-10">
                        <span class="text-[10px] font-bold uppercase text-slate-400 shrink-0"><?= is_bangla() ? 'দ্রুত উত্তর:' : 'Quick Replies:' ?></span>
                        <button type="button" onclick="setQuickReply(this)" class="chat-quick-chip shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-medium border border-slate-200 text-[11px] transition shadow-2xs cursor-pointer"
                                data-text="<?= is_bangla() ? 'জী, গাছটি আমাদের নার্সারিতে ফ্রেশ স্টকে রয়েছে এবং সম্পূর্ণ সুস্থ।' : 'Yes, this plant is available in fresh, healthy stock in our nursery.' ?>">
                            🌿 <?= is_bangla() ? 'স্টকে আছে' : 'In Stock' ?>
                        </button>
                        <button type="button" onclick="setQuickReply(this)" class="chat-quick-chip shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-medium border border-slate-200 text-[11px] transition shadow-2xs cursor-pointer"
                                data-text="<?= is_bangla() ? 'অর্ডার করার ২৪ থেকে ৪৮ ঘণ্টার মধ্যে নিরাপদ প্যাকেজিংসহ হোম ডেলিভারি পৌঁছে যাবে।' : 'Orders are dispatched within 24-48 hours with secure botanical packaging.' ?>">
                            🚚 <?= is_bangla() ? '২৪-৪৮ ঘণ্টা ডেলিভারি' : '24-48h Delivery' ?>
                        </button>
                        <button type="button" onclick="setQuickReply(this)" class="chat-quick-chip shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-medium border border-slate-200 text-[11px] transition shadow-2xs cursor-pointer"
                                data-text="<?= is_bangla() ? 'গাছটি উজ্জ্বল পরোক্ষ আলো পছন্দ করে এবং মাটিতে আর্দ্রতা বুঝে সপ্তাহে ২ বার পানি দিতে হবে।' : 'This plant thrives in bright indirect sunlight. Water twice a week when topsoil dries.' ?>">
                            💧 <?= is_bangla() ? 'পরিচর্যার তথ্য' : 'Care Details' ?>
                        </button>
                        <button type="button" onclick="setQuickReply(this)" class="chat-quick-chip shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-medium border border-slate-200 text-[11px] transition shadow-2xs cursor-pointer"
                                data-text="<?= is_bangla() ? 'আপনার আগ্রহের জন্য ধন্যবাদ! কোনো প্রশ্ন থাকলে নিঃসঙ্কোচে জানাতে পারেন।' : 'Thank you for reaching out! Please let us know if you need any more details.' ?>">
                            🙏 <?= is_bangla() ? 'ধন্যবাদ' : 'Thank you' ?>
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

                    <!-- Modern WhatsApp Style Input -->
                    <form id="chat-send-form" class="p-3 sm:p-4 border-t border-slate-200/90 flex items-center space-x-2.5 bg-white relative z-20">
                        <!-- Emoji Toggle Button -->
                        <button type="button" id="chat-emoji-toggle" onclick="toggleEmojiTray(event)" class="p-2.5 rounded-full text-slate-500 hover:text-amber-500 hover:bg-slate-100 transition cursor-pointer" title="Add Emoji">
                            <i class="fa-regular fa-face-smile text-lg pointer-events-none"></i>
                        </button>

                        <div class="flex-1 relative">
                            <input id="chat-message-input" type="text" placeholder="<?= is_bangla() ? 'গ্রাহকের বার্তার উত্তর লিখুন (Enter চেপে পাঠান)...' : 'Type a reply to customer (Press Enter to send)...' ?>" required autocomplete="off"
                                   class="w-full bg-slate-100/90 hover:bg-slate-100 border border-slate-200 rounded-full px-5 py-2.5 text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                        </div>

                        <button type="submit" class="w-10 h-10 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center shadow-md shadow-emerald-700/20 hover:scale-105 active:scale-95 transition transform cursor-pointer" title="Send Reply">
                            <i class="fa-solid fa-paper-plane text-sm ml-0.5 pointer-events-none"></i>
                        </button>
                    </form>

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
                        <h3 class="font-extrabold text-slate-800 text-base"><?= is_bangla() ? 'গ্রাহকের চ্যাট নির্বাচন করুন' : 'Select a Customer Conversation' ?></h3>
                        <p class="max-w-xs text-slate-500"><?= is_bangla() ? 'বাম পাশের তালিকা থেকে যেকোনো গ্রাহকের অনুসন্ধানে ক্লিক করে উত্তর পাঠান।' : 'Choose a conversation from the left sidebar to reply to inquiries in real time.' ?></p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

