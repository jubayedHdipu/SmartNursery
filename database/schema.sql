-- ==============================================================================
-- SMART NURSERY MANAGEMENT SYSTEM - DATABASE SCHEMA & SEED DATA
-- Database: `smart_nursery`
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `smart_nursery` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smart_nursery`;

-- Disable foreign key checks during initialization
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. ROLES & USERS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `conversations`;
DROP TABLE IF EXISTS `delivery_assignments`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `delivery_staff_profiles`;
DROP TABLE IF EXISTS `customer_profiles`;
DROP TABLE IF EXISTS `vendor_profiles`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `dynamic_contents`;
DROP TABLE IF EXISTS `system_settings`;

-- Roles Table (5 System Roles)
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `display_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `avatar` VARCHAR(255) NULL,
  `status` ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. ROLE PROFILES
-- ------------------------------------------------------------------------------

-- Vendor Profiles (Nursery Stores)
CREATE TABLE `vendor_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `nursery_name` VARCHAR(150) NOT NULL,
  `nursery_name_bn` VARCHAR(150) NULL,
  `description` TEXT NULL,
  `description_bn` TEXT NULL,
  `address` VARCHAR(255) NOT NULL,
  `address_bn` VARCHAR(255) NULL,
  `city` VARCHAR(100) NOT NULL DEFAULT 'Dhaka',
  `opening_hours` VARCHAR(100) NOT NULL DEFAULT 'Sat - Thu: 9:00 AM - 8:00 PM',
  `contact_phone` VARCHAR(30) NULL,
  `banner_image` VARCHAR(255) NULL,
  `logo_image` VARCHAR(255) NULL,
  `verification_status` ENUM('pending', 'verified', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
  `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 5.00,
  `rating_count` INT NOT NULL DEFAULT 0,
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Profiles
CREATE TABLE `customer_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `shipping_address` VARCHAR(255) NULL,
  `city` VARCHAR(100) NOT NULL DEFAULT 'Dhaka',
  `postal_code` VARCHAR(20) NULL,
  `plant_experience_level` ENUM('beginner', 'intermediate', 'expert') DEFAULT 'beginner',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery Staff Profiles
CREATE TABLE `delivery_staff_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `vehicle_type` VARCHAR(50) NOT NULL DEFAULT 'Motorbike',
  `license_number` VARCHAR(50) NULL,
  `status` ENUM('available', 'busy', 'offline') NOT NULL DEFAULT 'available',
  `current_zone` VARCHAR(100) NOT NULL DEFAULT 'Central Zone',
  `total_deliveries` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. CATALOG & INVENTORY
-- ------------------------------------------------------------------------------

-- Categories Table
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products Table
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `scientific_name` VARCHAR(150) NULL,
  `short_description` VARCHAR(255) NULL,
  `description` TEXT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `discount_price` DECIMAL(10,2) NULL,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `sunlight_requirement` ENUM('Full Sun', 'Partial Shade', 'Low Light', 'Bright Indirect') NOT NULL DEFAULT 'Bright Indirect',
  `water_frequency` ENUM('Daily', 'Twice a week', 'Weekly', 'Bi-weekly') NOT NULL DEFAULT 'Weekly',
  `care_level` ENUM('Easy', 'Moderate', 'Expert') NOT NULL DEFAULT 'Easy',
  `indoor_outdoor` ENUM('Indoor', 'Outdoor', 'Both') NOT NULL DEFAULT 'Indoor',
  `is_pet_friendly` TINYINT(1) NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('pending_approval', 'approved', 'rejected', 'archived') NOT NULL DEFAULT 'approved',
  `admin_feedback` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendor_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Images Table
CREATE TABLE `product_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Transactions (Stock Audit Log)
CREATE TABLE `inventory_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `vendor_id` INT NOT NULL,
  `change_type` ENUM('restock', 'sale', 'order_cancellation', 'adjustment', 'damaged') NOT NULL,
  `quantity_change` INT NOT NULL,
  `previous_quantity` INT NOT NULL,
  `new_quantity` INT NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `reference_id` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendor_profiles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. ORDERS, PAYMENTS & DELIVERY
-- ------------------------------------------------------------------------------

-- Orders Table
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `vendor_id` INT NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` DECIMAL(10,2) NOT NULL DEFAULT 60.00,
  `grand_total` DECIMAL(10,2) NOT NULL,
  `order_status` ENUM(
    'Pending',
    'Vendor Accepted',
    'Vendor Rejected',
    'Cancelled',
    'Preparing',
    'Ready for Pickup',
    'Assigned to Delivery',
    'Picked Up',
    'Out for Delivery',
    'Delivered'
  ) NOT NULL DEFAULT 'Pending',
  `shipping_name` VARCHAR(100) NOT NULL,
  `shipping_phone` VARCHAR(30) NOT NULL,
  `shipping_address` VARCHAR(255) NOT NULL,
  `shipping_city` VARCHAR(100) NOT NULL DEFAULT 'Dhaka',
  `notes` TEXT NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendor_profiles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items Table
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `quantity` INT NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments Table (Multi-Channel Payment Records)
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
  `val_id` VARCHAR(100) NULL,
  `bank_tran_id` VARCHAR(100) NULL,
  `card_type` VARCHAR(50) NULL,
  `card_issuer` VARCHAR(100) NULL,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'card',
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'BDT',
  `store_amount` DECIMAL(10,2) NULL,
  `payment_status` ENUM('Pending', 'Completed', 'Failed', 'Cancelled', 'Refunded') NOT NULL DEFAULT 'Pending',
  `payment_details` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery Assignments Table
CREATE TABLE `delivery_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL UNIQUE,
  `delivery_staff_id` INT NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `picked_up_at` TIMESTAMP NULL,
  `delivered_at` TIMESTAMP NULL,
  `delivery_notes` TEXT NULL,
  `proof_image` VARCHAR(255) NULL,
  `status` ENUM('assigned', 'picked_up', 'out_for_delivery', 'delivered', 'failed') NOT NULL DEFAULT 'assigned',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`delivery_staff_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. COMMUNICATION, NOTIFICATIONS & REVIEWS
-- ------------------------------------------------------------------------------

-- Conversations Table
CREATE TABLE `conversations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `vendor_id` INT NOT NULL,
  `product_id` INT NULL,
  `last_message_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_conversation` (`customer_id`, `vendor_id`),
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendor_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages Table
CREATE TABLE `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `message_text` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications Table
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `link_url` VARCHAR(255) NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'system',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verified Reviews Table (Enforce single review per customer-product-order)
CREATE TABLE `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `order_id` INT NOT NULL,
  `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `review_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_review_per_purchase` (`product_id`, `customer_id`, `order_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. CMS, SETTINGS & AUDIT
-- ------------------------------------------------------------------------------

-- Dynamic Contents Table (Editable Homepage & Copy)
CREATE TABLE `dynamic_contents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `content_key` VARCHAR(100) NOT NULL UNIQUE,
  `page_section` VARCHAR(50) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `content_value` TEXT NOT NULL,
  `content_type` ENUM('text', 'html', 'json', 'image') NOT NULL DEFAULT 'text',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Settings Table
CREATE TABLE `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Logs Table
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` VARCHAR(50) NULL,
  `ip_address` VARCHAR(45) NULL,
  `details` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;


-- ==============================================================================
-- SEED DATA (REALISTIC SAMPLE RECORDS & INITIAL DATA)
-- All test user passwords are: Password123!
-- Hashed using password_hash('Password123!', PASSWORD_DEFAULT)
-- Hash: $2y$10$tMh4y1fBvRkg66zVzszj3ee2Zz04vL3R6u71Pj01fOaT38.80hWbW
-- ==============================================================================

-- Roles
INSERT INTO `roles` (`id`, `name`, `display_name`, `description`) VALUES
(1, 'owner', 'Owner / Super Admin', 'Platform owner with full system control, revenue analytics, and CMS management'),
(2, 'admin', 'Administrator', 'System moderator managing vendor verifications, catalog approvals, and orders'),
(3, 'vendor', 'Nursery Vendor', 'Nursery store owner managing plant listings, inventory, and order fulfillment'),
(4, 'customer', 'Customer', 'Plant enthusiast browsing nursery catalog, using AI recommender, and placing orders'),
(5, 'delivery', 'Delivery Staff', 'Logistics rider managing pickups, live deliveries, and customer drop-offs');

-- Users (All with password: password123)
INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password_hash`, `phone`, `avatar`, `status`) VALUES
(1, 1, 'MR Dipu', 'owner@nursery.com', '$2y$10$lLWYCW51FF37Zg7Q76FCzOQ7HAsdb03OqDEzWaB8U3ZvhrobvB1sy', '+8801711000001', 'avatar-owner.png', 'active'),
(2, 2, 'Tanvir Hasan (Admin)', 'admin@nursery.com', '$2y$10$lLWYCW51FF37Zg7Q76FCzOQ7HAsdb03OqDEzWaB8U3ZvhrobvB1sy', '+8801711000002', 'avatar-admin.png', 'active'),
(3, 3, 'Farhan Ahmed (Green Haven)', 'greenhaven@nursery.com', '$2y$10$lLWYCW51FF37Zg7Q76FCzOQ7HAsdb03OqDEzWaB8U3ZvhrobvB1sy', '+8801711000003', 'avatar-vendor1.png', 'active'),
(4, 3, 'Mahir Chowdhury (Urban Jungle)', 'urbanjungle@nursery.com', '$2y$10$lLWYCW51FF37Zg7Q76FCzOQ7HAsdb03OqDEzWaB8U3ZvhrobvB1sy', '+8801711000004', 'avatar-vendor2.png', 'active'),
(5, 4, 'Alice Johnson', 'alice@gmail.com', '$2y$10$lLWYCW51FF37Zg7Q76FCzOQ7HAsdb03OqDEzWaB8U3ZvhrobvB1sy', '+8801811000005', 'avatar-customer1.png', 'active'),
(6, 4, 'Bob Smith', 'bob@gmail.com', '$2y$10$lLWYCW51FF37Zg7Q76FCzOQ7HAsdb03OqDEzWaB8U3ZvhrobvB1sy', '+8801811000006', 'avatar-customer2.png', 'active'),
(7, 5, 'Speedy Courier Rider', 'speedy@nursery.com', '$2y$10$lLWYCW51FF37Zg7Q76FCzOQ7HAsdb03OqDEzWaB8U3ZvhrobvB1sy', '+8801911000007', 'avatar-delivery.png', 'active');

-- Profiles
INSERT INTO `vendor_profiles` (`id`, `user_id`, `nursery_name`, `nursery_name_bn`, `description`, `description_bn`, `address`, `address_bn`, `city`, `opening_hours`, `contact_phone`, `banner_image`, `logo_image`, `verification_status`, `rating_avg`, `rating_count`, `commission_rate`) VALUES
(1, 3, 'Green Haven Botanicals', 'গ্রিন হেভেন বোটানিক্যালস', 'Specializing in rare tropical indoor houseplants, air purifiers, and sustainable organic potting blends.', 'বিরল ট্রপিক্যাল ইনডোর হাউসপ্ল্যান্ট, এয়ার পিউরিফায়ার এবং জৈব পটিং মিশ্রণের বিশেষায়িত নার্সারি।', 'Plot 14, Road 7, Dhanmondi', 'প্লট ১৪, রোড ৭, ধানমন্ডি', 'Dhaka', 'Sat - Fri: 8:30 AM - 8:30 PM', '+880 1711-000003', 'assets/images/nurseries/green-haven-banner.jpg', 'assets/images/nurseries/green-haven-logo.jpg', 'verified', 4.90, 42, 10.00),
(2, 4, 'Urban Jungle Nursery', 'আরবান জঙ্গল নার্সারি', 'Premium succulents, bonsai specimens, flowering balcony plants, and handmade terracotta planters.', 'প্রিমিয়াম সাকুলেন্ট, বনসাই, বারান্দার ফুল গাছ এবং হাতে তৈরি টেরাকোটা পট।', 'House 22, Road 11, Banani', 'বাড়ি ২২, রোড ১১, বনানী', 'Dhaka', 'Sat - Thu: 9:00 AM - 9:00 PM (Fri: 2:00 PM - 9:00 PM)', '+880 1711-000004', 'assets/images/nurseries/urban-jungle-banner.jpg', 'assets/images/nurseries/urban-jungle-logo.jpg', 'verified', 4.75, 28, 10.00);

INSERT INTO `customer_profiles` (`id`, `user_id`, `shipping_address`, `city`, `postal_code`, `plant_experience_level`) VALUES
(1, 5, 'House 56, Road 4, Sector 3, Uttara', 'Dhaka', '1230', 'beginner'),
(2, 6, 'Flat 4B, Gulshan Lake View, Road 84, Gulshan 2', 'Dhaka', '1212', 'intermediate');

INSERT INTO `delivery_staff_profiles` (`id`, `user_id`, `vehicle_type`, `license_number`, `status`, `current_zone`, `total_deliveries`) VALUES
(1, 7, 'Honda CB Shine (Motorbike)', 'DHAKA-METRO-HA-12345', 'available', 'Dhanmondi & Banani Corridor', 154);

-- Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `is_active`) VALUES
(1, 'Indoor Foliage', 'indoor-foliage', 'Vibrant lush foliage that thrives indoors with low to bright indirect light.', 'cat-indoor.jpg', 1),
(2, 'Air Purifying Plants', 'air-purifying', 'NASA-tested natural indoor air filters that eliminate toxins and refresh living spaces.', 'cat-airpurify.jpg', 1),
(3, 'Succulents & Cacti', 'succulents-cacti', 'Drought-tolerant architectural beauties requiring minimal care and maximum charm.', 'cat-succulents.jpg', 1),
(4, 'Flowering Beauties', 'flowering-beauties', 'Spectacular blooming plants that bring radiant colors and pleasant aromas.', 'cat-flowering.jpg', 1),
(5, 'Bonsai & Exotics', 'bonsai-exotics', 'Artfully sculpted living sculptures and rare specimen collector plants.', 'cat-bonsai.jpg', 1),
(6, 'Medicinal & Herbs', 'medicinal-herbs', 'Aromatic culinary herbs and traditional therapeutic medicinal botanicals.', 'cat-herbs.jpg', 1),
(7, 'Pots & Plant Care', 'pots-care', 'Handcrafted ceramic planters, terracotta pots, organic fertilizers, and watering tools.', 'cat-pots.jpg', 1);

-- Products (14 Realistic Botanical Items)
INSERT INTO `products` (`id`, `vendor_id`, `category_id`, `name`, `slug`, `scientific_name`, `short_description`, `description`, `price`, `discount_price`, `stock_quantity`, `sunlight_requirement`, `water_frequency`, `care_level`, `indoor_outdoor`, `is_pet_friendly`, `is_featured`, `status`) VALUES
(1, 1, 1, 'Monstera Deliciosa (Swiss Cheese Plant)', 'monstera-deliciosa', 'Monstera deliciosa', 'Iconic tropical split-leaf foliage perfect for living rooms and stylish offices.', 'The iconic Swiss Cheese plant is prized for its dramatic fenestrated leaves that develop natural holes as it matures. An effortless centerpiece that transforms any indoor corner into a lush urban jungle.', 850.00, 750.00, 24, 'Bright Indirect', 'Weekly', 'Easy', 'Indoor', 0, 1, 'approved'),
(2, 1, 2, 'Snake Plant Laurentii', 'snake-plant-laurentii', 'Sansevieria trifasciata', 'Indestructible air-purifying architectural plant with golden-yellow margins.', 'An virtually indestructible indoor plant recognized by NASA for filtering formaldehyde, benzene, and xylene. Thrives on neglect and produces oxygen throughout the night, making it ideal for bedrooms.', 450.00, 390.00, 35, 'Low Light', 'Bi-weekly', 'Easy', 'Indoor', 0, 1, 'approved'),
(3, 1, 2, 'Peace Lily (Spathiphyllum)', 'peace-lily', 'Spathiphyllum wallisii', 'Glossy dark green leaves with graceful pure white spathe blooms.', 'A classic indoor favorite that communicates its needs clearly by drooping slightly when thirsty. Exceptionally efficient at removing airborne toxins while producing striking white hooded flowers.', 550.00, NULL, 18, 'Low Light', 'Weekly', 'Easy', 'Indoor', 0, 1, 'approved'),
(4, 1, 1, 'Fiddle Leaf Fig (Ficus Lyrata)', 'fiddle-leaf-fig', 'Ficus lyrata', 'Statement violin-shaped leathery leaves creating a luxurious architectural presence.', 'A designer favorite featuring broad, violin-shaped, heavily veined upright leaves. Flourishes when placed in steady bright indirect sunlight away from harsh drafts.', 1650.00, 1450.00, 12, 'Bright Indirect', 'Weekly', 'Moderate', 'Indoor', 0, 1, 'approved'),
(5, 1, 1, 'Golden Pothos (Devil\'s Ivy)', 'golden-pothos', 'Epipremnum aureum', 'Fast-growing cascading vine with heart-shaped emerald leaves splashed in gold.', 'One of the easiest and most versatile houseplants. Trailing vines look breathtaking from hanging baskets, high shelves, or trained upward along moss poles.', 280.00, 240.00, 40, 'Low Light', 'Weekly', 'Easy', 'Indoor', 0, 1, 'approved'),
(6, 1, 2, 'Spider Plant (Chlorophytum)', 'spider-plant', 'Chlorophytum comosum', 'Safe for curious pets! Arching ribbon leaves producing cute cascading plantlets.', 'A completely pet-safe, resilient air-purifier featuring graceful arching green-and-white variegated blades. Readily sprouts adorable miniature plantlets (spiderettes) that you can easily propagate.', 320.00, NULL, 28, 'Bright Indirect', 'Weekly', 'Easy', 'Indoor', 1, 1, 'approved'),
(7, 2, 3, 'Zebra Haworthia Succulent', 'zebra-haworthia', 'Haworthiopsis fasciata', 'Compact rosette with striking raised horizontal white ridges resembling zebra stripes.', 'A charming miniature succulent perfect for desks, window ledges, and terrariums. Features firm, dark green pointed leaves adorned with textured pearly white bands.', 220.00, 190.00, 50, 'Bright Indirect', 'Bi-weekly', 'Easy', 'Indoor', 1, 1, 'approved'),
(8, 2, 3, 'Jade Plant (Money Tree)', 'jade-plant', 'Crassula ovata', 'Symbols of prosperity! Plump jade-green succulent leaves on woody bonsai-like stems.', 'Renowned as a feng shui symbol of good fortune and prosperity. Features fleshy, glossy oval leaves and a sturdy miniature tree structure that thrives with minimal watering.', 480.00, 420.00, 22, 'Full Sun', 'Bi-weekly', 'Easy', 'Both', 0, 1, 'approved'),
(9, 2, 4, 'Anthurium Red Flamingo Flower', 'anthurium-red', 'Anthurium andraeanum', 'Exotic glossy waxy red heart spathes that bloom continuously year-round.', 'One of the longest-lasting blooming indoor houseplants in existence. Its vivid lacquered crimson spathes and contrasting yellow spadix lend an instant exotic tropical vibe.', 780.00, NULL, 15, 'Bright Indirect', 'Weekly', 'Moderate', 'Indoor', 0, 1, 'approved'),
(10, 2, 5, 'Ficus Ginseng Bonsai', 'ficus-ginseng-bonsai', 'Ficus microcarpa', 'Sculptural aerial root base with dense compact canopy in a ceramic dish.', 'An excellent introductory bonsai characterized by substantial pot-bellied exposed roots and thick dark-green oval foliage. Pruned to perfection in an artisan glazed oval dish.', 1850.00, 1600.00, 8, 'Bright Indirect', 'Twice a week', 'Moderate', 'Indoor', 0, 1, 'approved'),
(11, 2, 6, 'Sweet Basil & Rosemary Herb Duo', 'basil-rosemary-duo', 'Ocimum basilicum & Salvia rosmarinus', 'Fresh organic kitchen garden starter pack bursting with aromatic culinary oils.', 'Fresh culinary essentials ready for your balcony or sunny kitchen windowsill. Enjoy organic aromatic basil leaves for fresh pesto and woodsy rosemary sprigs for savory roasting.', 350.00, 300.00, 30, 'Full Sun', 'Daily', 'Easy', 'Outdoor', 1, 0, 'approved'),
(12, 1, 6, 'Aloe Vera Medicinal Plant', 'aloe-vera', 'Aloe barbadensis miller', 'Cooling soothing therapeutic gel leaves for skincare, hydration, and minor burns.', 'An ancient medicinal succulent packed with cooling vitamins and enzymes. Plump gel-filled leaves can be harvested fresh for natural skin moisturization, soothing sun exposure, and hair masks.', 250.00, NULL, 45, 'Full Sun', 'Bi-weekly', 'Easy', 'Both', 0, 0, 'approved'),
(13, 2, 7, 'Minimalist Terracotta Cylinder Pot (8")', 'terracotta-cylinder-pot', 'Clay Craft Handcrafted', 'Breathable porous natural earthenware pot with drainage hole and saucer.', 'Hand-thrown natural terracotta pot providing optimal root aeration and moisture control. Neutral earthy tone complements any interior botanical aesthetic.', 380.00, 340.00, 25, 'Full Sun', 'Weekly', 'Easy', 'Both', 1, 0, 'approved'),
(14, 1, 1, 'Calathea Orbifolia', 'calathea-orbifolia', 'Goeppertia orbifolia', 'Show-stopping round leaves adorned with metallic silver-green brushstrokes.', 'A prized collector prayer plant featuring oversized circular leaves with striking silver-grey brushstroke patterns. Leaves gracefully fold upward at night in prayer-like meditation.', 950.00, 850.00, 10, 'Partial Shade', 'Twice a week', 'Expert', 'Indoor', 1, 1, 'approved');

-- Product Images (Local Self-Contained Assets)
INSERT INTO `product_images` (`product_id`, `image_path`, `is_primary`, `sort_order`) VALUES
(1, 'assets/images/plants/monstera.jpg', 1, 1),
(2, 'assets/images/plants/snake-plant.jpg', 1, 1),
(3, 'assets/images/plants/peace-lily.jpg', 1, 1),
(4, 'assets/images/plants/fiddle-leaf-fig.jpg', 1, 1),
(5, 'assets/images/plants/golden-pothos.jpg', 1, 1),
(6, 'assets/images/plants/spider-plant.jpg', 1, 1),
(7, 'assets/images/plants/zebra-haworthia.jpg', 1, 1),
(8, 'assets/images/plants/jade-plant.jpg', 1, 1),
(9, 'assets/images/plants/anthurium-red.jpg', 1, 1),
(10, 'assets/images/plants/ficus-bonsai.jpg', 1, 1),
(11, 'assets/images/plants/basil-rosemary.jpg', 1, 1),
(12, 'assets/images/plants/aloe-vera.jpg', 1, 1),
(13, 'assets/images/plants/terracotta-pot.jpg', 1, 1),
(14, 'assets/images/plants/calathea-orbifolia.jpg', 1, 1);

-- Inventory Transactions Seed
INSERT INTO `inventory_transactions` (`product_id`, `vendor_id`, `change_type`, `quantity_change`, `previous_quantity`, `new_quantity`, `reason`, `reference_id`) VALUES
(1, 1, 'restock', 25, 0, 25, 'Initial nursery stock batch', 'PO-2026-001'),
(1, 1, 'sale', -1, 25, 24, 'Order #ORD-2026-1001 fulfillment', 'ORD-2026-1001'),
(2, 1, 'restock', 35, 0, 35, 'Seasonal propagation restock', 'PO-2026-002'),
(4, 1, 'restock', 15, 0, 15, 'Large nursery stock intake', 'PO-2026-003'),
(7, 2, 'restock', 50, 0, 50, 'Greenhouse seedling production', 'PO-2026-004');

-- Orders Seed
INSERT INTO `orders` (`id`, `order_number`, `customer_id`, `vendor_id`, `total_amount`, `discount_amount`, `shipping_fee`, `grand_total`, `order_status`, `shipping_name`, `shipping_phone`, `shipping_address`, `shipping_city`, `notes`) VALUES
(1, 'ORD-2026-1001', 5, 1, 750.00, 0.00, 60.00, 810.00, 'Delivered', 'Alice Johnson', '+8801811000005', 'House 56, Road 4, Sector 3, Uttara', 'Dhaka', 'Please handle the leafy foliage carefully.'),
(2, 'ORD-2026-1002', 5, 2, 610.00, 0.00, 60.00, 670.00, 'Out for Delivery', 'Alice Johnson', '+8801811000005', 'House 56, Road 4, Sector 3, Uttara', 'Dhaka', 'Ring doorbell upon arrival.'),
(3, 'ORD-2026-1003', 6, 1, 1450.00, 0.00, 60.00, 1510.00, 'Vendor Accepted', 'Bob Smith', '+8801811000006', 'Flat 4B, Gulshan Lake View, Road 84, Gulshan 2', 'Dhaka', 'Deliver after 4 PM.');

-- Order Items
INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`) VALUES
(1, 1, 'Monstera Deliciosa (Swiss Cheese Plant)', 750.00, 1, 750.00),
(2, 7, 'Zebra Haworthia Succulent', 190.00, 1, 190.00),
(2, 8, 'Jade Plant (Money Tree)', 420.00, 1, 420.00),
(3, 4, 'Fiddle Leaf Fig (Ficus Lyrata)', 1450.00, 1, 1450.00);

-- Payments
INSERT INTO `payments` (`order_id`, `transaction_id`, `payment_method`, `amount`, `payment_status`, `payment_details`) VALUES
(1, 'TXN-BKASH-789012', 'bkash', 810.00, 'Completed', '{"gateway": "bKash Mock", "sender": "01811000005", "time": "2026-08-15 10:30:00"}'),
(2, 'TXN-CARD-445566', 'card', 670.00, 'Completed', '{"gateway": "Visa Card Mock", "card_last4": "4242", "auth_code": "AUTH9876"}'),
(3, 'TXN-COD-998877', 'cod', 1510.00, 'Pending', '{"gateway": "Cash on Delivery", "notes": "Collect on delivery"}');

-- Delivery Assignments
INSERT INTO `delivery_assignments` (`order_id`, `delivery_staff_id`, `assigned_at`, `picked_up_at`, `delivered_at`, `delivery_notes`, `status`) VALUES
(1, 7, '2026-08-15 11:00:00', '2026-08-15 12:30:00', '2026-08-15 14:15:00', 'Delivered safely in person to Alice at Uttara.', 'delivered'),
(2, 7, '2026-08-16 09:00:00', '2026-08-16 10:15:00', NULL, 'Rider on route to Uttara delivery location.', 'out_for_delivery');

-- Purchase-Verified Review (Customer 5 reviewed Product 1 from delivered Order 1)
INSERT INTO `reviews` (`product_id`, `customer_id`, `order_id`, `rating`, `review_text`) VALUES
(1, 5, 1, 5, 'Arrived in pristine lush condition with multiple new leaf shoots! The fenestrations are gorgeous and the packaging protected the leaves completely.');

-- Conversations & Messages
INSERT INTO `conversations` (`id`, `customer_id`, `vendor_id`, `product_id`) VALUES
(1, 5, 1, 1);

INSERT INTO `messages` (`conversation_id`, `sender_id`, `receiver_id`, `message_text`, `is_read`) VALUES
(1, 5, 3, 'Hello! How frequently should I mist the Monstera leaves during hot summers?', 1),
(1, 3, 5, 'Hi Alice! Misting 2-3 times a week or keeping a humidity pebble tray underneath works wonders to prevent crisp tips.', 1);

-- Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `link_url`, `type`, `is_read`) VALUES
(5, 'Order Delivered! 🎉', 'Your order #ORD-2026-1001 for Monstera Deliciosa has been delivered safely.', 'customer/order-detail.php?id=1', 'order', 1),
(3, 'New Order Received', 'New order #ORD-2026-1003 placed by Bob Smith for Fiddle Leaf Fig.', 'vendor/orders.php', 'order', 0),
(7, 'New Delivery Assigned', 'You have been assigned order #ORD-2026-1002 for delivery.', 'delivery/deliveries.php', 'system', 0);

-- System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('site_name', 'Smart Nursery Ecosystem', 'general', 'Platform public brand name'),
('site_tagline', 'Curated Botanical Wonders & Smart Plant Care', 'general', 'Hero tagline'),
('contact_email', 'support@smartnursery.com', 'general', 'Official customer support email'),
('contact_phone', '+880 1800-NURSERY', 'general', 'Customer hotline phone'),
('currency_symbol', '৳', 'localization', 'Currency display symbol (BDT Taka)'),
('currency_code', 'BDT', 'localization', 'Currency code'),
('shipping_base_fee', '60.00', 'ecommerce', 'Standard base delivery fee per order'),
('free_shipping_threshold', '2000.00', 'ecommerce', 'Order total qualifying for free shipping'),
('platform_commission_rate', '10.00', 'finance', 'Platform commission percentage charged to vendors');

-- Dynamic Contents (Editable CMS Copy)
INSERT INTO `dynamic_contents` (`content_key`, `page_section`, `title`, `content_value`, `content_type`) VALUES
('hero_title', 'homepage_hero', 'Bring Nature Indoors with Intelligent Plant Care', 'Discover curated plants from verified nurseries, matched to your living space by our Smart Botanical Matcher.', 'text'),
('hero_badge', 'homepage_hero', '🌿 Green Living Simplified', '🌿 Green Living Simplified', 'text'),
('hero_cta_primary', 'homepage_hero', 'Explore Botanical Shop', 'shop.php', 'text'),
('hero_cta_secondary', 'homepage_hero', 'Try Smart Plant Matcher', 'plant-matcher.php', 'text'),
('feature_1_title', 'homepage_features', 'Smart Botanical Matcher', 'Find the exact plants that will flourish in your specific room light, humidity, and pet-friendly lifestyle.', 'text'),
('feature_2_title', 'homepage_features', 'Verified Local Nurseries', 'Source healthy, acclimated botanicals directly from accredited specialty growers.', 'text'),
('feature_3_title', 'homepage_features', 'Safe Concurrency & Tracking', 'Real-time stock locking, secure multi-channel payments, and live stepped milestone delivery tracking.', 'text'),
('faq_1_q', 'homepage_faq', 'How does the Smart Plant Matcher choose plants?', 'Our botanical algorithm analyzes your room direction, light level, watering routine, and pet status, then queries real in-stock database plants with personalized explanations on why each plant fits your lifestyle.', 'text'),
('faq_2_q', 'homepage_faq', 'Can I cancel my order?', 'Yes! You can cancel your order at zero charge directly from your customer dashboard as long as the status is still "Pending".', 'text'),
('faq_3_q', 'homepage_faq', 'How are reviews verified?', 'Reviews are strictly restricted to customers who purchased and received that exact item in a delivered order, preventing fake ratings.', 'text');
