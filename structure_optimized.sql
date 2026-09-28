-- ============================================================================
-- Base de Données Optimisée & Rétrocompatible : seraphin_ecommerce (v2.0)
-- Description : Architecture InnoDB haute performance, avec intégrité référentielle
--               complète (Clés Etrangères) et rétrocompatibilité 100% avec le code PHP actuel.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `seraphin_ecommerce` 
  DEFAULT CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `seraphin_ecommerce`;

-- --------------------------------------------------------
-- 1. Table `users` (Utilisateurs : Acheteurs et Vendeurs)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('buyer','seller') DEFAULT 'buyer',
  `status` ENUM('active','inactive') DEFAULT 'active',
  `credibility_score` INT(11) NOT NULL DEFAULT 100,
  `profile_pic` VARCHAR(255) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_phone` (`phone`),
  KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table `admins` (Authentification Administrateurs Unifiée)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) DEFAULT NULL,
  `expiration` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `idx_admins_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table `categories` (Catégories)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `icon_class` VARCHAR(100) DEFAULT NULL,
  `image` VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Table `subcategories` (Sous-Catégories)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `subcategories`;
CREATE TABLE `subcategories` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT(10) UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subcat_category` (`category_id`),
  CONSTRAINT `fk_subcategory_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table `regions` (Régions)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `regions`;
CREATE TABLE `regions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `province` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_regions_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table `products` (Catalogue Produits - Rétrocompatible & Relationnel)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `category_id` INT(10) UNSIGNED DEFAULT NULL,
  `subcategory_id` INT(10) UNSIGNED DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `currency` ENUM('USD','CDF') DEFAULT 'USD',
  `product_condition` ENUM('new','used') DEFAULT 'new',
  `unit_type` ENUM('pcs','kg','meters','liters') NOT NULL DEFAULT 'pcs',
  `quantity` DECIMAL(10,3) NOT NULL,
  `min_order` DECIMAL(10,3) DEFAULT NULL,
  `discount_threshold` DECIMAL(10,3) DEFAULT NULL,
  `discount_percent` TINYINT(3) UNSIGNED DEFAULT 0,
  `category` LONGTEXT DEFAULT NULL,
  `regions` LONGTEXT DEFAULT NULL,
  `defects` LONGTEXT DEFAULT NULL,
  `colors` LONGTEXT DEFAULT NULL,
  `shoe_sizes` LONGTEXT DEFAULT NULL,
  `child_sizes` LONGTEXT DEFAULT NULL,
  `adult_sizes` LONGTEXT DEFAULT NULL,
  `specifications` LONGTEXT DEFAULT NULL,
  `price_mode` ENUM('uniform','by_color','by_size','by_color_size') NOT NULL DEFAULT 'uniform',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_user` (`user_id`),
  KEY `idx_products_cat_id` (`category_id`),
  KEY `idx_products_subcat_id` (`subcategory_id`),
  KEY `idx_products_price` (`price`),
  FULLTEXT KEY `ft_products_search` (`name`, `description`),
  CONSTRAINT `fk_products_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Table `product_regions` (Liaison Normalisée Produit-Région)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_regions`;
CREATE TABLE `product_regions` (
  `product_id` INT(11) NOT NULL,
  `region_id` INT(11) NOT NULL,
  PRIMARY KEY (`product_id`, `region_id`),
  KEY `idx_prod_reg_region` (`region_id`),
  CONSTRAINT `fk_prod_reg_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prod_reg_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Table `product_media` (Médias Produits)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_media`;
CREATE TABLE `product_media` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_type` ENUM('image','video') NOT NULL,
  `is_color_image` TINYINT(1) DEFAULT 0,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_product` (`product_id`),
  CONSTRAINT `fk_media_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Table `product_variants` (Variantes Produits)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `variant_key` VARCHAR(255) NOT NULL,
  `variant_type` ENUM('default','color','size','color_size') NOT NULL,
  `color` VARCHAR(50) DEFAULT NULL,
  `size` VARCHAR(50) DEFAULT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `quantity` DECIMAL(10,3) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_variants_product` (`product_id`),
  CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. Table `orders` (Commandes - Totalement Rétrocompatible & Optimisée)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `variant_id` INT(11) DEFAULT NULL,
  `variant_key` VARCHAR(255) DEFAULT NULL,
  `variant_details` LONGTEXT DEFAULT NULL,
  `variant_keys` LONGTEXT DEFAULT NULL,
  `variant_type` VARCHAR(50) DEFAULT NULL,
  `user_id` INT(11) NOT NULL,
  `seller_id` INT(11) NOT NULL,
  `customer_name` VARCHAR(255) NOT NULL,
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `channel` VARCHAR(50) DEFAULT NULL,
  `quantity` INT(11) DEFAULT NULL,
  `customer_address` VARCHAR(255) NOT NULL,
  `customer_phone` VARCHAR(30) NOT NULL,
  `size_number` INT(11) DEFAULT NULL,
  `size_letter` VARCHAR(5) DEFAULT NULL,
  `color` VARCHAR(50) DEFAULT NULL,
  `selected_variant_key` VARCHAR(255) DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `original_amount` DECIMAL(12,2) DEFAULT NULL,
  `status` ENUM('pending','cancelled','delivered','paid','shipped','refunded') DEFAULT 'pending',
  `qr_code` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `kilogrammes` DECIMAL(10,2) DEFAULT NULL,
  `metres` DECIMAL(10,2) DEFAULT NULL,
  `litres` DECIMAL(10,2) DEFAULT NULL,
  `unit_type` VARCHAR(50) DEFAULT NULL,
  `ordered_quantity` INT(11) DEFAULT NULL,
  `ordered_kilogrammes` DECIMAL(10,2) DEFAULT NULL,
  `ordered_metres` DECIMAL(10,2) DEFAULT NULL,
  `ordered_litres` DECIMAL(10,2) DEFAULT NULL,
  `applied_discount_percentage` DECIMAL(5,2) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `otpvalidated` TINYINT(1) NOT NULL DEFAULT 0,
  `temp_order_id` INT(10) UNSIGNED DEFAULT NULL,
  `otp_updated_at` DATETIME DEFAULT NULL,
  `selected_color` VARCHAR(50) DEFAULT NULL,
  `selected_size` VARCHAR(255) DEFAULT NULL,
  `discount_applied` TINYINT(1) DEFAULT 0,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `savings_amount` DECIMAL(12,2) DEFAULT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `refund_status` VARCHAR(50) DEFAULT 'none',
  `seller_status` ENUM('pending','accepted','refused') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_seller` (`seller_id`),
  KEY `idx_orders_product` (`product_id`),
  KEY `idx_orders_variant` (`variant_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_analytics` (`seller_id`, `status`, `created_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. Table `order_items` (Nouvelle Table Lignes de Commande pour Panier Multi-Produits)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `variant_id` INT(11) DEFAULT NULL,
  `unit_type` VARCHAR(50) NOT NULL DEFAULT 'pcs',
  `unit_price` DECIMAL(12,2) NOT NULL,
  `quantity` DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  `total_item_amount` DECIMAL(12,2) NOT NULL,
  `applied_discount_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `selected_color` VARCHAR(50) DEFAULT NULL,
  `selected_size` VARCHAR(50) DEFAULT NULL,
  `selected_variant_key` VARCHAR(255) DEFAULT NULL,
  `variant_details` LONGTEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_items_order` (`order_id`),
  KEY `idx_items_product` (`product_id`),
  KEY `idx_items_variant` (`variant_id`),
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 12. Table `temp_orders` (Brouillons de Commandes)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `temp_orders`;
CREATE TABLE `temp_orders` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT(10) UNSIGNED NOT NULL,
  `user_id` INT(10) UNSIGNED NOT NULL,
  `seller_id` INT(10) UNSIGNED NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(30) NOT NULL,
  `customer_address` TEXT NOT NULL,
  `region` VARCHAR(100) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `unit_value` DECIMAL(10,2) NOT NULL,
  `unit_type` VARCHAR(30) NOT NULL,
  `selected_size` VARCHAR(30) DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `original_amount` DECIMAL(10,2) NOT NULL,
  `discount_applied` TINYINT(1) NOT NULL DEFAULT 0,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `color_quantities` LONGTEXT DEFAULT NULL,
  `otpvalidated` INT(10) UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `currency` VARCHAR(10) NOT NULL,
  `status` ENUM('paid','unpaid') NOT NULL DEFAULT 'unpaid',
  `variant_details` LONGTEXT DEFAULT NULL,
  `variant_keys` LONGTEXT DEFAULT NULL,
  `variant_type` VARCHAR(50) DEFAULT NULL,
  `selected_color` VARCHAR(255) DEFAULT NULL,
  `selected_variant_key` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 13. Table `success_orders` (Historique des Commandes Réussies)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `success_orders`;
CREATE TABLE `success_orders` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_address` VARCHAR(255) NOT NULL,
  `customer_phone` VARCHAR(30) NOT NULL,
  `seller_id` INT(10) UNSIGNED NOT NULL,
  `product_id` INT(10) UNSIGNED NOT NULL,
  `unit_value` DECIMAL(10,2) NOT NULL,
  `unit_type` VARCHAR(50) NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `transaction_id` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 14. Table `transactions` (Transactions Financières)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `buyer_id` INT(11) NOT NULL,
  `seller_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pending','completed','failed') DEFAULT 'pending',
  `commission` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trans_order` (`order_id`),
  KEY `idx_trans_buyer` (`buyer_id`),
  KEY `idx_trans_seller` (`seller_id`),
  CONSTRAINT `fk_trans_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trans_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trans_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 15. Table `seller_reviews` (Avis et Notes)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `seller_reviews`;
CREATE TABLE `seller_reviews` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `buyer_id` INT(11) NOT NULL,
  `seller_id` INT(11) NOT NULL,
  `rating` INT(11) NOT NULL,
  `comment` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order_review` (`order_id`),
  KEY `idx_reviews_buyer` (`buyer_id`),
  KEY `idx_reviews_seller` (`seller_id`),
  CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 16. Table `notifications` (Notifications In-App - InnoDB)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `title` VARCHAR(255) DEFAULT NULL,
  `type` VARCHAR(50) DEFAULT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `action_data` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 17. Table `loyalty_points` (Points de Fidélité Unifiés - InnoDB)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `loyalty_points`;
CREATE TABLE `loyalty_points` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `seller_id` INT(11) DEFAULT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `points` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seller` (`seller_id`),
  UNIQUE KEY `uq_user` (`user_id`),
  CONSTRAINT `fk_loyalty_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loyalty_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 18. Table `order_cancellations` (Annulations - InnoDB)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_cancellations`;
CREATE TABLE `order_cancellations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) DEFAULT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `cancel_reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cancel_order` (`order_id`),
  KEY `idx_cancel_user` (`user_id`),
  CONSTRAINT `fk_cancel_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cancel_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 19. Table `scanned_orders` (Scans QR Code - InnoDB)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `scanned_orders`;
CREATE TABLE `scanned_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` VARCHAR(255) NOT NULL,
  `scanned_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 20. Table `size_suggestions` (Suggestions Tailles)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `size_suggestions`;
CREATE TABLE `size_suggestions` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `size` VARCHAR(10) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_size` (`size`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 21. Table `validation` (Validation / Checkpoints)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `validation`;
CREATE TABLE `validation` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_val_order` (`order_id`),
  CONSTRAINT `fk_val_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
