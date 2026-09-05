<?php
// Notifications Helper with Role-Aware Routing & Formatting

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

// Creates an in-app notification record for a specific user
function create_notification(int $userId, string $title, string $message, ?string $linkUrl = null, string $type = 'system'): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, link_url, type, is_read, created_at)
            VALUES (:user_id, :title, :message, :link_url, :type, 0, NOW())
        ");
        return $stmt->execute([
            'user_id'  => $userId,
            'title'    => $title,
            'message'  => $message,
            'link_url' => $linkUrl,
            'type'     => $type
        ]);
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

// Retrieves all recent notifications (both unread & read) for a user
function get_user_notifications(int $userId, int $limit = 5): array {
    try {
        $db = get_db();
        // Check if user has zero notifications and seed role-specific samples
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid");
        $cntStmt->execute(['uid' => $userId]);
        if ((int)$cntStmt->fetchColumn() === 0) {
            seed_user_role_notifications($userId);
        }

        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY is_read ASC, created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to fetch notifications: " . $e->getMessage());
        return [];
    }
}

// Retrieves all notifications with optional category filter
function get_user_all_notifications(int $userId, ?string $category = null, int $limit = 50): array {
    try {
        $db = get_db();
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid");
        $cntStmt->execute(['uid' => $userId]);
        if ((int)$cntStmt->fetchColumn() === 0) {
            seed_user_role_notifications($userId);
        }

        if (!empty($category) && $category !== 'all') {
            $stmt = $db->prepare("
                SELECT * FROM notifications 
                WHERE user_id = :user_id AND type = :type
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':type', $category, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM notifications 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to fetch all notifications: " . $e->getMessage());
        return [];
    }
}

// Retrieves unread notifications for a user
function get_unread_notifications(int $userId, int $limit = 5): array {
    return get_user_notifications($userId, $limit);
}

// Retrieves total unread notification count
function count_unread_notifications(int $userId): int {
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute(['user_id' => $userId]);
        $count = (int)$stmt->fetchColumn();

        if ($count === 0) {
            // Check if user has no notifications at all
            $totalStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id");
            $totalStmt->execute(['user_id' => $userId]);
            if ((int)$totalStmt->fetchColumn() === 0) {
                seed_user_role_notifications($userId);
                $stmt->execute(['user_id' => $userId]);
                return (int)$stmt->fetchColumn();
            }
        }
        return $count;
    } catch (Exception $e) {
        return 0;
    }
}

// Marks a notification as read
function mark_notification_read(int $notificationId, int $userId): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        return $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    } catch (Exception $e) {
        return false;
    }
}

// Marks all notifications as read for a user
function mark_all_notifications_read(int $userId): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
        return $stmt->execute(['user_id' => $userId]);
    } catch (Exception $e) {
        return false;
    }
}

// Returns appropriate visual badge configuration for each notification type
function get_notification_style(string $type): array {
    return match($type) {
        'order' => [
            'icon'  => 'fa-boxes-packing',
            'bg'    => 'bg-emerald-50 text-emerald-600 border-emerald-200',
            'badge' => 'bg-emerald-100 text-emerald-800',
            'label' => 'Order'
        ],
        'product' => [
            'icon'  => 'fa-seedling',
            'bg'    => 'bg-teal-50 text-teal-600 border-teal-200',
            'badge' => 'bg-teal-100 text-teal-800',
            'label' => 'Inventory'
        ],
        'vendor' => [
            'icon'  => 'fa-store',
            'bg'    => 'bg-amber-50 text-amber-600 border-amber-200',
            'badge' => 'bg-amber-100 text-amber-800',
            'label' => 'Storefront'
        ],
        'delivery' => [
            'icon'  => 'fa-truck-fast',
            'bg'    => 'bg-sky-50 text-sky-600 border-sky-200',
            'badge' => 'bg-sky-100 text-sky-800',
            'label' => 'Logistics'
        ],
        'chat' => [
            'icon'  => 'fa-comments',
            'bg'    => 'bg-indigo-50 text-indigo-600 border-indigo-200',
            'badge' => 'bg-indigo-100 text-indigo-800',
            'label' => 'Message'
        ],
        default => [
            'icon'  => 'fa-bell',
            'bg'    => 'bg-slate-100 text-slate-600 border-slate-200',
            'badge' => 'bg-slate-100 text-slate-700',
            'label' => 'System'
        ]
    };
}

// Automatically seeds distinct role-based notifications for new or empty accounts
function seed_user_role_notifications(int $userId): void {
    try {
        $db = get_db();
        $uStmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id");
        $uStmt->execute(['id' => $userId]);
        $user = $uStmt->fetch();
        if (!$user) return;

        $role = $user['role_name'];

        switch ($role) {
            case 'owner':
                // Executive & Financial Overview ONLY (No operational/storefront admin noise!)
                create_notification(
                    $userId,
                    'Platform Revenue Milestone: ৳125,000',
                    'Total gross botanical commerce crossed ৳125,000 this month. Net commission earned: ৳12,500.',
                    'owner/index.php',
                    'order'
                );
                create_notification(
                    $userId,
                    'Daily Financial Settlement Summary',
                    '24 orders completed today across verified partner nurseries. Gross volume: ৳38,500.',
                    'owner/index.php',
                    'order'
                );
                create_notification(
                    $userId,
                    'Financial Audit & Payout Log',
                    'Bi-weekly vendor commission and merchant settlement ledger has been updated.',
                    'owner/audit-logs.php',
                    'system'
                );
                create_notification(
                    $userId,
                    'Platform Economics Configuration',
                    'Standard delivery fee (৳60) and 10% platform commission thresholds are active.',
                    'owner/settings.php',
                    'system'
                );
                break;

            case 'admin':
                create_notification(
                    $userId,
                    'Vendor Verification Pending',
                    'New nursery partner application awaiting inspection and approval.',
                    'admin/vendors.php',
                    'vendor'
                );
                create_notification(
                    $userId,
                    'Fulfillment Alert #ORD-2026-0001',
                    'Order packed and ready for courier rider dispatch confirmation.',
                    'admin/orders.php?order_id=1',
                    'order'
                );
                create_notification(
                    $userId,
                    'Courier Fleet Status: Dhaka Central',
                    'Delivery rider Kabir Hossain is on active dispatch duty.',
                    'admin/riders.php',
                    'delivery'
                );
                break;

            case 'vendor':
                create_notification(
                    $userId,
                    'New Botanical Order Received!',
                    'Order #ORD-2026-0001 placed for Ficus Burgundy & Monstera.',
                    'vendor/orders.php?order_id=1',
                    'order'
                );
                create_notification(
                    $userId,
                    'Low Stock Warning: Monstera',
                    'Monstera Deliciosa inventory is low (3 units remaining in greenhouse).',
                    'vendor/inventory.php?search=Monstera',
                    'product'
                );
                create_notification(
                    $userId,
                    'Storefront Verified Partner',
                    'Your nursery profile has been verified as a Trusted Botanical Partner.',
                    'vendor/profile.php',
                    'vendor'
                );
                create_notification(
                    $userId,
                    'Customer Botanical Inquiry',
                    'Customer asked about optimal watering frequency for indoor aroids.',
                    'vendor/chat.php?customer_id=5',
                    'chat'
                );
                break;

            case 'delivery':
                create_notification(
                    $userId,
                    'New Delivery Task Assigned',
                    'Order #ORD-2026-0001 is ready for nursery pickup at Dhanmondi.',
                    'delivery/deliveries.php?order_id=1',
                    'delivery'
                );
                create_notification(
                    $userId,
                    'Destination Route Updated',
                    'Customer address: House 14, Road 7, Gulshan-2, Dhaka for order #ORD-2026-0001.',
                    'delivery/deliveries.php?order_id=1',
                    'delivery'
                );
                create_notification(
                    $userId,
                    'Cash on Delivery Reconciled',
                    'Payment of ৳1,850 successfully synced with nursery accounts.',
                    'delivery/history.php?order_id=1',
                    'system'
                );
                break;

            default: // customer
                create_notification(
                    $userId,
                    'Order Confirmed & Preparing',
                    'Your plant order #ORD-2026-0001 is being carefully packaged by the nursery.',
                    'track-order.php?order_number=ORD-2026-0001',
                    'order'
                );
                create_notification(
                    $userId,
                    'Courier Out for Delivery',
                    'Rider Kabir Hossain has picked up your botanical package for safe delivery.',
                    'track-order.php?order_number=ORD-2026-0001',
                    'delivery'
                );
                create_notification(
                    $userId,
                    'Nursery Advice Message',
                    'Green Haven Botanicals replied to your plant care question.',
                    'customer/chat.php?vendor_id=1',
                    'chat'
                );
                break;
        }
    } catch (Exception $e) {
        error_log("Failed to seed notifications: " . $e->getMessage());
    }
}

// Unified multi-party order lifecycle notification dispatcher
function notify_order_lifecycle(int $orderId, string $event, array $extra = []): void {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT o.*, 
                   u.name AS customer_name, u.phone AS customer_phone,
                   vp.nursery_name, vp.user_id AS vendor_user_id, vp.contact_phone AS vendor_phone,
                   vp.address AS vendor_address, vp.city AS vendor_city
            FROM orders o
            JOIN users u ON o.customer_id = u.id
            JOIN vendor_profiles vp ON o.vendor_id = vp.id
            WHERE o.id = :id
        ");
        $stmt->execute(['id' => $orderId]);
        $ord = $stmt->fetch();
        if (!$ord) return;

        $ordNum   = $ord['order_number'];
        $custUid  = (int)$ord['customer_id'];
        $vendUid  = (int)$ord['vendor_user_id'];
        $total    = number_format((float)$ord['grand_total'], 2);
        $custLink = "track-order.php?order_number=" . urlencode($ordNum);
        $vendLink = "vendor/orders.php?order_id=" . $orderId;
        $riderLink = "delivery/deliveries.php?order_id=" . $orderId;

        // Fetch assigned rider if any
        $rStmt = $db->prepare("
            SELECT da.*, u.name AS rider_name, u.phone AS rider_phone, u.id AS rider_user_id
            FROM delivery_assignments da
            JOIN users u ON da.delivery_staff_id = u.id
            WHERE da.order_id = :oid
            ORDER BY da.id DESC LIMIT 1
        ");
        $rStmt->execute(['oid' => $orderId]);
        $rider = $rStmt->fetch();

        // Fetch Owner user id
        $ownerStmt = $db->query("
            SELECT u.id FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE r.name = 'owner' LIMIT 1
        ");
        $ownerUid = $ownerStmt ? (int)$ownerStmt->fetchColumn() : 0;

        switch ($event) {
            case 'placed':
                // 1. Customer Notification
                create_notification(
                    $custUid,
                    "Order #{$ordNum} Confirmed! 🌱",
                    "Your plant order has been confirmed with {$ord['nursery_name']}. Total: ৳{$total}.",
                    $custLink,
                    'order'
                );

                // 2. Vendor Notification
                create_notification(
                    $vendUid,
                    "New Order #{$ordNum} Received! 📦",
                    "Customer {$ord['customer_name']} placed order #{$ordNum} (৳{$total}). Please inspect & prepare plants.",
                    $vendLink,
                    'order'
                );

                // 3. Available Delivery Staff
                $availRiders = $db->query("
                    SELECT u.id FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE r.name = 'delivery' AND u.status = 'active'
                ")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($availRiders as $rUid) {
                    create_notification(
                        (int)$rUid,
                        "New Delivery Task Available! 🛵",
                        "Order #{$ordNum} at {$ord['nursery_name']} ({$ord['shipping_city']}) is ready for pickup claiming.",
                        "delivery/index.php",
                        'delivery'
                    );
                }

                // 4. Owner Revenue Notification (Executive revenue alert only)
                if ($ownerUid > 0) {
                    $commRate = (float)get_setting('platform_commission_rate', '10.0');
                    $comm = number_format(((float)$ord['total_amount'] * ($commRate / 100)), 2);
                    create_notification(
                        $ownerUid,
                        "New Platform Sale: ৳{$total}",
                        "Order #{$ordNum} confirmed at {$ord['nursery_name']}. Net platform commission: ৳{$comm}.",
                        "owner/index.php",
                        'order'
                    );
                }
                break;

            case 'processing':
            case 'accepted':
                create_notification(
                    $custUid,
                    "Plants Being Prepared! 🌿",
                    "{$ord['nursery_name']} has accepted order #{$ordNum} and is packaging your botanical specimens.",
                    $custLink,
                    'order'
                );
                break;

            case 'ready_for_pickup':
            case 'shipped':
                create_notification(
                    $custUid,
                    "Package Packed & Ready! 📦",
                    "Your botanical order #{$ordNum} is securely packaged and waiting for courier dispatch.",
                    $custLink,
                    'order'
                );
                if ($rider && !empty($rider['rider_user_id'])) {
                    create_notification(
                        (int)$rider['rider_user_id'],
                        "Pickup Ready at Greenhouse! 🛵",
                        "Order #{$ordNum} is packed and ready for collection at {$ord['nursery_name']} ({$ord['vendor_address']}).",
                        $riderLink,
                        'delivery'
                    );
                }
                break;

            case 'assigned':
            case 'rider_claimed':
                $riderName = $extra['rider_name'] ?? ($rider['rider_name'] ?? 'Courier Rider');
                $riderPhone = $extra['rider_phone'] ?? ($rider['rider_phone'] ?? '');
                create_notification(
                    $custUid,
                    "Courier Assigned! 🛵",
                    "Rider {$riderName} " . ($riderPhone ? "({$riderPhone}) " : "") . "has been assigned to deliver order #{$ordNum}.",
                    $custLink,
                    'delivery'
                );
                if ($rider && !empty($rider['rider_user_id'])) {
                    create_notification(
                        (int)$rider['rider_user_id'],
                        "Delivery Task Confirmed! 📍",
                        "You claimed delivery for order #{$ordNum}. Pickup: {$ord['nursery_name']}, {$ord['vendor_address']}.",
                        $riderLink,
                        'delivery'
                    );
                }
                break;

            case 'picked_up':
                $riderName = $extra['rider_name'] ?? ($rider['rider_name'] ?? 'Courier Rider');
                create_notification(
                    $custUid,
                    "Package Picked Up! 🌿",
                    "Rider {$riderName} has collected your plants from {$ord['nursery_name']} and is en route.",
                    $custLink,
                    'delivery'
                );
                create_notification(
                    $vendUid,
                    "Order #{$ordNum} Dispatched 🚚",
                    "Rider {$riderName} picked up package for order #{$ordNum} from your greenhouse.",
                    $vendLink,
                    'order'
                );
                break;

            case 'out_for_delivery':
                $riderName = $extra['rider_name'] ?? ($rider['rider_name'] ?? 'Courier Rider');
                create_notification(
                    $custUid,
                    "Out for Delivery! 🛵",
                    "Rider {$riderName} is arriving at your address soon with order #{$ordNum}! Track live on map.",
                    $custLink,
                    'delivery'
                );
                break;

            case 'delivered':
                // Customer gets completion & direct order detail view
                create_notification(
                    $custUid,
                    "Order Delivered Safely! 🎉",
                    "Your plant order #{$ordNum} was delivered! Enjoy your botanical greenery 🌱",
                    "customer/order-detail.php?id={$orderId}",
                    'order'
                );
                // Vendor gets payout credit alert
                create_notification(
                    $vendUid,
                    "Order #{$ordNum} Delivered! ৳",
                    "Order #{$ordNum} successfully delivered. Settlement credit of ৳{$total} recorded.",
                    $vendLink,
                    'order'
                );
                // Rider gets completion alert
                if ($rider && !empty($rider['rider_user_id'])) {
                    create_notification(
                        (int)$rider['rider_user_id'],
                        "Delivery Completed! 🌟",
                        "Order #{$ordNum} marked as delivered. Reconciled in delivery history.",
                        "delivery/history.php?order_id={$orderId}",
                        'delivery'
                    );
                }
                // Owner gets gross platform completion alert
                if ($ownerUid > 0) {
                    $commRate = (float)get_setting('platform_commission_rate', '10.0');
                    $comm = number_format(((float)$ord['total_amount'] * ($commRate / 100)), 2);
                    create_notification(
                        $ownerUid,
                        "Sale Completed: Order #{$ordNum}",
                        "Order #{$ordNum} delivered (৳{$total}). Commission retained: ৳{$comm}.",
                        "owner/index.php",
                        'order'
                    );
                }
                break;

            case 'cancelled':
                $reason = $extra['reason'] ?? 'Order was cancelled.';
                create_notification(
                    $custUid,
                    "Order #{$ordNum} Cancelled ❌",
                    "Your order #{$ordNum} has been cancelled. Reason: {$reason}",
                    $custLink,
                    'order'
                );
                create_notification(
                    $vendUid,
                    "Order #{$ordNum} Cancelled",
                    "Order #{$ordNum} was cancelled and stock was restored.",
                    $vendLink,
                    'order'
                );
                break;
        }
    } catch (Exception $e) {
        error_log("Failed in notify_order_lifecycle: " . $e->getMessage());
    }
}
