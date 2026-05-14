-- ============================================================
-- RedWolf IT Officer Demo - Database Schema
-- MySQL 8.0
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Products table
-- ----------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price_hkd` DECIMAL(10,2) NOT NULL,
    `price_usd` DECIMAL(10,2) NOT NULL,
    `price_cny` DECIMAL(10,2) NOT NULL,
    `stock_qty` INT UNSIGNED NOT NULL DEFAULT 0,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT 'general',
    `sku` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('active','inactive','discontinued') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_created` (`created_at` DESC),
    INDEX `idx_sku` (`sku`),
    INDEX `idx_category` (`category`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Alerts table (monitoring)
-- ----------------------------
DROP TABLE IF EXISTS `alerts`;
CREATE TABLE `alerts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_type` VARCHAR(50) NOT NULL,
    `severity` ENUM('warning','critical') NOT NULL DEFAULT 'warning',
    `message` TEXT NOT NULL,
    `hostname` VARCHAR(255) DEFAULT NULL,
    `metric_value` DECIMAL(10,2) DEFAULT NULL,
    `threshold` DECIMAL(10,2) DEFAULT NULL,
    `status` ENUM('active','acked','resolved') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `acked_at` DATETIME DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_alert_type` (`alert_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Cart sessions table
-- ----------------------------
DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(255) NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_product` (`product_id`),
    CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Ticket classification history (AI)
-- ----------------------------
DROP TABLE IF EXISTS `ticket classifications`;
DROP TABLE IF EXISTS `ticket_classifications`;
CREATE TABLE `ticket_classifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_text` TEXT NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `confidence` DECIMAL(5,4) DEFAULT NULL,
    `model_used` VARCHAR(100) DEFAULT 'keyword',
    `response_time_ms` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Audit log (office tools)
-- ----------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` VARCHAR(100) DEFAULT NULL,
    `action` VARCHAR(255) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Seed: RedWolf Airsoft Products
-- ----------------------------
INSERT INTO `products` (`name`, `description`, `price_hkd`, `price_usd`, `price_cny`, `stock_qty`, `image_url`, `category`, `sku`, `status`) VALUES
('RedWolf M4A1 AEG', 'Full metal body, Version 2 gearbox, 400 FPS. Perfect for outdoor skirmishes.', 2680.00, 342.72, 2481.48, 25, '/assets/img/m4a1.jpg', 'rifles', 'RW-M4A1-001', 'active'),
('RedWolf GLOCK 17 GBB', 'Gas blowback pistol, full metal slide, 320 FPS. Excellent sidearm.', 1280.00, 163.68, 1185.19, 40, '/assets/img/glock17.jpg', 'pistols', 'RW-G17-002', 'active'),
('Tactical Plate Carrier', 'MOLLE-compatible plate carrier, adjustable size, includes front/back plates.', 680.00, 86.96, 629.63, 60, '/assets/img/platecarrier.jpg', 'gear', 'RW-PC-003', 'active'),
('RedWolf Sniper VSR-10', 'Bolt-action sniper rifle, 500 FPS, precision barrel. For long-range engagements.', 3200.00, 409.22, 2962.96, 15, '/assets/img/vsr10.jpg', 'rifles', 'RW-VSR10-004', 'active'),
('Hi-Cap Magazine M4', '300-round high capacity magazine for M4/M16 series.', 180.00, 23.02, 166.67, 200, '/assets/img/magm4.jpg', 'accessories', 'RW-MAG-M4-005', 'active'),
('BBs 0.25g 5000ct', 'Precision polished 6mm BBs, 0.25g weight. Perfect for outdoor use.', 120.00, 15.34, 111.11, 500, '/assets/img/bbs25.jpg', 'consumables', 'RW-BB25-006', 'active'),
('Tactical Flashlight X900', '1200 lumens, Picatinny mount, tactical tail switch. IPX6 waterproof.', 450.00, 57.54, 416.67, 35, '/assets/img/flashlight.jpg', 'accessories', 'RW-FL-007', 'active'),
('RedWolf P90 AEG', 'Compact bullpup design, 380 FPS, built-in red dot sight.', 2280.00, 291.56, 2111.11, 20, '/assets/img/p90.jpg', 'rifles', 'RW-P90-008', 'active'),
('Protective Goggles PRO', 'ANSI Z87.1 rated, anti-fog, ballistic rated lens. Full seal protection.', 280.00, 35.81, 259.26, 100, '/assets/img/goggles.jpg', 'gear', 'RW-GOG-009', 'active'),
('Speed Loader Universal', 'Loads 100 BBs at once, works with most standard mid-cap magazines.', 90.00, 11.51, 83.33, 300, '/assets/img/speedloader.jpg', 'accessories', 'RW-SL-010', 'active'),
('RedWolf AK-47 AEG', 'Full metal AK-47, Version 3 gearbox, 410 FPS. Classic design.', 2580.00, 329.92, 2388.89, 18, '/assets/img/ak47.jpg', 'rifles', 'RW-AK47-011', 'active'),
('Gas Canister Green Gas', '134a green gas, 750ml can. For GBB pistols and rifles.', 85.00, 10.87, 78.70, 400, '/assets/img/greengas.jpg', 'consumables', 'RW-GAS-012', 'active');

SET FOREIGN_KEY_CHECKS = 1;
