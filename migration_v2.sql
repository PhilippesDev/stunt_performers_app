-- ============================================================================
-- SCRIPT DE MIGRATION BASE DE DONNÉES (v1.0 -> v2.0 Rétrocompatible)
-- Description : Conversion progressive vers InnoDB, uniformisation des collations,
--               création des tables relationnelles et contraintes sans casser le code PHP existant.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- 1. Nettoyage des tables de sauvegarde et doublons inutilisés
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `admin_auth_tokens`;
DROP TABLE IF EXISTS `loyalty_points_backup_20260119_131926`;
DROP TABLE IF EXISTS `loyalty_points_backup_before_split`;
DROP TABLE IF EXISTS `order_cancellation_reasons`;
DROP TABLE IF EXISTS `seller_loyalty_points`;
DROP TABLE IF EXISTS `user_loyalty_points`;

-- 2. Uniformisation de la Table `admins`
ALTER TABLE `admins` 
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  CHANGE COLUMN `email` `email` VARCHAR(150) NOT NULL,
  CHANGE COLUMN `expiration` `expiration` DATETIME DEFAULT NULL;

-- 3. Uniformisation de la Table `users`
ALTER TABLE `users` 
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  CHANGE COLUMN `phone` `phone` VARCHAR(30) NOT NULL,
  CHANGE COLUMN `email` `email` VARCHAR(150) DEFAULT NULL;

-- 4. Conversion des Tables MyISAM vers InnoDB & Collation utf8mb4_unicode_ci
ALTER TABLE `notifications` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `order_cancellations` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `scanned_orders` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `categories` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `subcategories` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `regions` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `products` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `product_media` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `product_variants` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `transactions` ENGINE=InnoDB CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 5. Mise à jour de la table `orders` (Types et statut)
ALTER TABLE `orders` 
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  CHANGE COLUMN `customer_phone` `customer_phone` VARCHAR(30) NOT NULL,
  CHANGE COLUMN `transaction_id` `transaction_id` VARCHAR(100) DEFAULT NULL,
  CHANGE COLUMN `otpvalidated` `otpvalidated` TINYINT(1) NOT NULL DEFAULT 0,
  MODIFY COLUMN `status` ENUM('pending','cancelled','delivered','paid','shipped','refunded') DEFAULT 'pending';

-- 6. Ajout des colonnes relationnelles sur `products`
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `category_id` INT(10) UNSIGNED DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `subcategory_id` INT(10) UNSIGNED DEFAULT NULL AFTER `category_id`;

-- 7. Création de la Table `order_items` pour la Normalisation des Lignes de Commande
CREATE TABLE IF NOT EXISTS `order_items` (
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

-- Migration automatique des données de `orders` vers `order_items`
INSERT INTO `order_items` (
  `order_id`, `product_id`, `variant_id`, `unit_type`, `unit_price`, `quantity`, 
  `total_item_amount`, `applied_discount_percentage`, `selected_color`, `selected_size`, 
  `selected_variant_key`, `variant_details`
)
SELECT 
  o.id AS order_id,
  o.product_id,
  o.variant_id,
  IFNULL(o.unit_type, 'pcs') AS unit_type,
  o.total_amount AS unit_price,
  IFNULL(o.quantity, 1) AS quantity,
  o.total_amount AS total_item_amount,
  IFNULL(o.discount_percent, 0) AS applied_discount_percentage,
  o.selected_color,
  o.selected_size,
  o.selected_variant_key,
  o.variant_details
FROM `orders` o
WHERE o.product_id IS NOT NULL AND o.product_id > 0
  AND NOT EXISTS (SELECT 1 FROM `order_items` oi WHERE oi.order_id = o.id);

-- 8. Création de la Table `product_regions` pour Normaliser la Disponibilité Régionale
CREATE TABLE IF NOT EXISTS `product_regions` (
  `product_id` INT(11) NOT NULL,
  `region_id` INT(11) NOT NULL,
  PRIMARY KEY (`product_id`, `region_id`),
  KEY `idx_prod_reg_region` (`region_id`),
  CONSTRAINT `fk_prod_reg_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prod_reg_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Ajout des Clés Étrangères Manquantes
ALTER TABLE `notifications` 
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `order_cancellations` 
  ADD CONSTRAINT `fk_cancel_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cancel_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `seller_reviews` 
  ADD CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
