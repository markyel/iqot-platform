-- ============================================
-- Полная настройка базы данных IQOT
-- Дата: 2025-12-23
-- ============================================

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS `iqot` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `iqot`;

-- ============================================
-- Таблица: users
-- ============================================
CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица: password_reset_tokens
-- ============================================
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица: sessions
-- ============================================
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица: suppliers (Поставщики)
-- ============================================
CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `brands` json DEFAULT NULL,
  `categories` json DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_responses` int(11) DEFAULT 0,
  `response_rate` decimal(5,2) DEFAULT 0.00,
  `avg_response_time` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_email_unique` (`email`),
  KEY `suppliers_is_active_index` (`is_active`),
  KEY `suppliers_rating_index` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица: requests (Заявки)
-- ============================================
CREATE TABLE `requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` varchar(500) DEFAULT NULL,
  `inn` varchar(12) DEFAULT NULL,
  `kpp` varchar(9) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `status` enum('draft','pending','sending','collecting','completed','cancelled') NOT NULL DEFAULT 'draft',
  `items_count` int(11) NOT NULL DEFAULT 0,
  `suppliers_count` int(11) NOT NULL DEFAULT 0,
  `offers_count` int(11) NOT NULL DEFAULT 0,
  `collection_started_at` timestamp NULL DEFAULT NULL,
  `collection_ended_at` timestamp NULL DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `requests_code_unique` (`code`),
  KEY `requests_status_index` (`status`),
  KEY `requests_code_index` (`code`),
  KEY `requests_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица: request_suppliers (Связь заявок и поставщиков)
-- ============================================
CREATE TABLE `request_suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('pending','sent','delivered','opened','responded','bounced') NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_suppliers_request_id_supplier_id_unique` (`request_id`,`supplier_id`),
  KEY `request_suppliers_status_index` (`status`),
  KEY `request_suppliers_supplier_id_foreign` (`supplier_id`),
  CONSTRAINT `request_suppliers_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_suppliers_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица: request_items (Позиции заявок)
-- ============================================
CREATE TABLE `request_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(500) NOT NULL,
  `equipment_type` enum('lift','escalator') DEFAULT NULL,
  `equipment_brand` varchar(255) DEFAULT NULL,
  `article` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `manufacturer_article` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `description` text,
  `min_price` decimal(10,2) DEFAULT NULL,
  `avg_price` decimal(10,2) DEFAULT NULL,
  `max_price` decimal(10,2) DEFAULT NULL,
  `offers_count` int(11) NOT NULL DEFAULT 0,
  `best_supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `request_items_request_id_foreign` (`request_id`),
  KEY `request_items_best_supplier_id_foreign` (`best_supplier_id`),
  CONSTRAINT `request_items_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_items_best_supplier_id_foreign` FOREIGN KEY (`best_supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица: offers (Предложения от поставщиков)
-- ============================================
CREATE TABLE `offers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `request_item_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'RUB',
  `delivery_time` int(11) DEFAULT NULL,
  `delivery_cost` decimal(10,2) DEFAULT NULL,
  `availability` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `offers_request_id_foreign` (`request_id`),
  KEY `offers_request_item_id_foreign` (`request_item_id`),
  KEY `offers_supplier_id_foreign` (`supplier_id`),
  CONSTRAINT `offers_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offers_request_item_id_foreign` FOREIGN KEY (`request_item_id`) REFERENCES `request_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offers_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица: reports (Отчёты)
-- ============================================
CREATE TABLE `reports` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `status` enum('pending','generating','ready','failed') NOT NULL DEFAULT 'pending',
  `format` enum('xlsx','pdf','csv') NOT NULL DEFAULT 'xlsx',
  `data` json DEFAULT NULL,
  `error_message` text,
  `generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reports_status_index` (`status`),
  KEY `reports_request_id_foreign` (`request_id`),
  KEY `reports_user_id_foreign` (`user_id`),
  CONSTRAINT `reports_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица: migrations (служебная)
-- ============================================
CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Записываем информацию о миграциях
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_suppliers_table', 1),
('0001_01_01_000002_create_requests_table', 1),
('0001_01_01_000003_create_items_and_offers_table', 1),
('0001_01_01_000004_create_reports_table', 1),
('2025_12_23_000001_add_validation_fields_to_requests_and_items', 1);

-- ============================================
-- Готово!
-- ============================================
