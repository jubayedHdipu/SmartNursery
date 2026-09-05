<?php
// ============================================================
// FILE: ajax_handler.php
// PURPOSE: Central AJAX action processor for plant matching,
//          live search, add to cart, live chat, and notifications.
// ============================================================

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/notifications.php';
require_once __DIR__ . '/helpers/plant_matcher.php';

$action = $_REQUEST['action'] ?? '';
$user   = current_user();

try {
    $db = get_db();

    switch ($action) {

        // ======================================================
        // 1. SMART PLANT MATCHING
        // ======================================================
        case 'match_plants':
        case 'ai_recommend':
            $preferences = [
                'room_type'    => trim($_POST['room_type'] ?? 'Living Room'),
                'sunlight'     => trim($_POST['sunlight'] ?? 'Bright Indirect'),
                'care_level'   => trim($_POST['care_level'] ?? 'Easy'),
                'pet_friendly' => !empty($_POST['pet_friendly']),
                'purpose'      => trim($_POST['purpose'] ?? 'Decor & Air Purification')
            ];

            $recommendations = get_plant_recommendations($preferences);

            echo json_encode([
                'success'         => true,
                'recommendations' => $recommendations
            ]);
            break;


        // ======================================================
        // 2. VENDOR PLANT DESCRIPTION GENERATOR
        // ======================================================
        case 'botanical_description':
        case 'ai_description':
            if (!$user || $user['role_name'] !== 'vendor') {
                echo json_encode(['success' => false, 'error' => 'Only registered vendors can generate plant descriptions.']);
                exit;
            }

            $plantName      = trim($_POST['name'] ?? '');
            $scientificName = trim($_POST['scientific_name'] ?? '');
            $categoryName   = trim($_POST['category'] ?? 'Indoor Foliage');
            $careLevel      = trim($_POST['care_level'] ?? 'Easy');
            $sunlightReq    = trim($_POST['sunlight'] ?? 'Bright Indirect');
            $highlightsText = trim($_POST['highlights'] ?? 'Lush healthy foliage and air purification');

            if (empty($plantName)) {
                echo json_encode(['success' => false, 'error' => 'Please enter a plant name first.']);
                exit;
            }

            $plantData = [
                'name'            => $plantName,
                'scientific_name' => $scientificName,
                'category'        => $categoryName,
                'care_level'      => $careLevel,
                'sunlight'        => $sunlightReq,
                'highlights'      => $highlightsText
            ];

            $description = generate_product_description_botanical($plantData);

            echo json_encode([
                'success'     => true,
                'description' => $description
            ]);
            break;


        // ======================================================
        // 3. LIVE AUTOCOMPLETE SEARCH
        // ======================================================
        case 'live_search':
            $keyword = trim($_REQUEST['q'] ?? '');

            if (empty($keyword)) {
                echo json_encode(['success' => true, 'results' => []]);
                exit;
            }

            $searchSql = "
                SELECT p.id, p.name, p.name_bn, p.price, p.discount_price, p.category_id,
                       c.name AS category_name, c.name_bn AS category_name_bn,
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.status = 'approved'
                  AND (p.name LIKE ? OR p.name_bn LIKE ? OR p.scientific_name LIKE ? OR c.name LIKE ? OR c.name_bn LIKE ?)
                ORDER BY p.id DESC
                LIMIT 6
            ";

            $searchTerm = '%' . $keyword . '%';
            $stmt = $db->prepare($searchSql);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $rows = $stmt->fetchAll();

            $formattedResults = [];
            foreach ($rows as $row) {
                $displayName    = product_display_name($row);
                $displayCat     = category_display_name(['name' => $row['category_name'], 'name_bn' => $row['category_name_bn']]);
                $effectivePrice = $row['discount_price'] ? $row['discount_price'] : $row['price'];

                $formattedResults[] = [
                    'id'       => (int)$row['id'],
                    'name'     => $displayName,
                    'price'    => format_currency($effectivePrice),
                    'category' => $displayCat,
                    'image'    => get_image_url($row['primary_image']),
                    'url'      => BASE_URL . '/product.php?id=' . $row['id']
                ];
            }

            echo json_encode([
                'success' => true, 
                'results' => $formattedResults
            ]);
            break;


        // ======================================================
        // 4. ADD ITEM TO CART
        // ======================================================
        case 'add_to_cart':
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity  = (int)($_POST['quantity'] ?? 1);
            if ($quantity < 1) {
                $quantity = 1;
            }

            $stmt = $db->prepare("
                SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
                FROM products p 
                WHERE p.id = ? AND p.status = 'approved'
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                echo json_encode(['success' => false, 'error' => 'Plant not found or unavailable.']);
                exit;
            }

            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            $currentCartQty = $_SESSION['cart'][$productId]['quantity'] ?? 0;
            $newTotalQty    = $currentCartQty + $quantity;

            if ($newTotalQty > $product['stock_quantity']) {
                echo json_encode([
                    'success' => false, 
                    'error'   => "Only {$product['stock_quantity']} units available in stock."
                ]);
                exit;
            }

            $itemPrice = $product['discount_price'] ? $product['discount_price'] : $product['price'];

            $_SESSION['cart'][$productId] = [
                'id'            => $product['id'],
                'name'          => $product['name'],
                'name_bn'       => $product['name_bn'] ?? null,
                'price'         => (float)$itemPrice,
                'quantity'      => $newTotalQty,
                'image'         => get_image_url($product['primary_image']),
                'vendor_id'     => (int)$product['vendor_id'],
                'max_stock'     => (int)$product['stock_quantity']
            ];

            $totalCount = 0;
            foreach ($_SESSION['cart'] as $cartItem) {
                $totalCount += (int)$cartItem['quantity'];
            }

            echo json_encode([
                'success'    => true,
                'message'    => "Added {$product['name']} to cart!",
                'cart_count' => $totalCount
            ]);
            break;


        // ======================================================
        // 5. GET CHAT MESSAGES
        // ======================================================
        case 'get_messages':
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'Login required to view messages.']);
                exit;
            }

            $convId = (int)($_GET['conversation_id'] ?? 0);
            $lastId = (int)($_GET['last_id'] ?? 0);

            if ($convId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid conversation ID.']);
                exit;
            }

            $convStmt = $db->prepare("
                SELECT * FROM conversations 
                WHERE id = ? 
                  AND (customer_id = ? OR vendor_id IN (SELECT id FROM vendor_profiles WHERE user_id = ?))
            ");
            $convStmt->execute([$convId, $user['id'], $user['id']]);
            $conversation = $convStmt->fetch();

            if (!$conversation) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized conversation access.']);
                exit;
            }

            $msgStmt = $db->prepare("
                SELECT m.*, u.name AS sender_name 
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.conversation_id = ? AND m.id > ?
                ORDER BY m.id ASC
            ");
            $msgStmt->execute([$convId, $lastId]);
            $newMessages = $msgStmt->fetchAll();

            $markReadStmt = $db->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0
            ");
            $markReadStmt->execute([$convId, $user['id']]);

            $formattedMsgs = [];
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            foreach ($newMessages as $m) {
                $msgDate = date('Y-m-d', strtotime($m['created_at']));
                if ($msgDate === $today) {
                    $dateLabel = 'Today';
                } elseif ($msgDate === $yesterday) {
                    $dateLabel = 'Yesterday';
                } else {
                    $dateLabel = date('M d, Y', strtotime($m['created_at']));
                }

                $formattedMsgs[] = [
                    'id'             => (int)$m['id'],
                    'sender_id'      => (int)$m['sender_id'],
                    'sender_name'    => $m['sender_name'],
                    'message_text'   => $m['message_text'],
                    'is_read'        => (int)$m['is_read'],
                    'created_at'     => $m['created_at'],
                    'date_label'     => $dateLabel,
                    'time_formatted' => date('h:i A', strtotime($m['created_at']))
                ];
            }

            echo json_encode([
                'success'  => true, 
                'messages' => $formattedMsgs
            ]);
            break;


        // ======================================================
        // 6. SEND CHAT MESSAGE
        // ======================================================
        case 'send_message':
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'Login required to send message.']);
                exit;
            }

            $convId  = (int)($_POST['conversation_id'] ?? 0);
            $msgText = trim($_POST['message'] ?? '');

            if ($convId <= 0 || empty($msgText)) {
                echo json_encode(['success' => false, 'error' => 'Message text cannot be empty.']);
                exit;
            }

            $convStmt = $db->prepare("
                SELECT c.*, vp.user_id AS vendor_user_id 
                FROM conversations c
                JOIN vendor_profiles vp ON c.vendor_id = vp.id
                WHERE c.id = ?
            ");
            $convStmt->execute([$convId]);
            $conv = $convStmt->fetch();

            if (!$conv) {
                echo json_encode(['success' => false, 'error' => 'Conversation not found.']);
                exit;
            }

            if ($user['id'] == $conv['customer_id']) {
                $receiverId = $conv['vendor_user_id'];
            } elseif ($user['id'] == $conv['vendor_user_id']) {
                $receiverId = $conv['customer_id'];
            } else {
                echo json_encode(['success' => false, 'error' => 'Unauthorized participant.']);
                exit;
            }

            $insertStmt = $db->prepare("
                INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $insertStmt->execute([
                $convId,
                $user['id'],
                $receiverId,
                $msgText
            ]);

            $newMsgId = (int)$db->lastInsertId();

            $db->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?")->execute([$convId]);

            create_notification(
                $receiverId,
                "New message from " . $user['name'] . " 💬",
                mb_substr($msgText, 0, 70) . (mb_strlen($msgText) > 70 ? '...' : ''),
                ($user['role_name'] === 'vendor' ? 'customer/chat.php' : 'vendor/chat.php') . "?conv_id=" . $convId,
                'chat'
            );

            echo json_encode([
                'success' => true,
                'message' => [
                    'id'             => $newMsgId,
                    'sender_id'      => (int)$user['id'],
                    'sender_name'    => $user['name'],
                    'message_text'   => $msgText,
                    'is_read'        => 0,
                    'date_label'     => 'Today',
                    'time_formatted' => date('h:i A')
                ]
            ]);
            break;


        // ======================================================
        // 7. GET NOTIFICATIONS
        // ======================================================
        case 'get_notifications':
            if (!$user) {
                echo json_encode(['success' => true, 'unread_count' => 0, 'notifications' => []]);
                exit;
            }

            $unreadCount = count_unread_notifications($user['id']);
            $rawList     = get_unread_notifications($user['id'], 6);

            $formatted = [];
            foreach ($rawList as $n) {
                $formatted[] = [
                    'id'        => (int)$n['id'],
                    'title'     => $n['title'],
                    'message'   => $n['message'],
                    'link_url'  => $n['link_url'],
                    'type'      => $n['type'],
                    'time_ago'  => time_ago($n['created_at'])
                ];
            }

            echo json_encode([
                'success'       => true,
                'unread_count'  => $unreadCount,
                'notifications' => $formatted
            ]);
            break;


        // ======================================================
        // 8. NOTIFICATION ACTIONS (MARK READ)
        // ======================================================
        case 'mark_all_read':
        case 'mark_all_notifications_read':
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'Login required.']);
                exit;
            }
            mark_all_notifications_read($user['id']);
            echo json_encode(['success' => true]);
            break;

        case 'mark_read':
        case 'mark_notification_read':
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'Login required.']);
                exit;
            }
            $notifId = (int)($_REQUEST['id'] ?? 0);
            if ($notifId > 0) {
                mark_notification_read($notifId, $user['id']);
            }
            echo json_encode(['success' => true]);
            break;

        case 'open_notification':
            $notifId = (int)($_GET['id'] ?? 0);
            $redirectUrl = BASE_URL;

            if ($user && $notifId > 0) {
                $nStmt = $db->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
                $nStmt->execute([$notifId, $user['id']]);
                $notif = $nStmt->fetch();

                if ($notif) {
                    mark_notification_read($notifId, $user['id']);
                    if (!empty($notif['link_url'])) {
                        $link = $notif['link_url'];
                        if (strpos($link, 'http') === 0) {
                            $redirectUrl = $link;
                        } else {
                            $redirectUrl = BASE_URL . '/' . ltrim($link, '/');
                        }
                    }
                }
            }

            header("Location: " . $redirectUrl);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action requested.']);
            break;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error'   => 'Server error: ' . $e->getMessage()
    ]);
}
