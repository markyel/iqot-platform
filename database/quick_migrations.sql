-- Быстрое применение миграций
-- Скопируйте этот SQL и выполните в phpMyAdmin/Adminer для БД iqot_platform

-- 1. Таблица public_catalog_items
CREATE TABLE IF NOT EXISTS `public_catalog_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_item_id` bigint unsigned NOT NULL,
  `name` varchar(500) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `article` varchar(100) DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit` varchar(50) NOT NULL DEFAULT 'шт.',
  `category` varchar(100) DEFAULT NULL,
  `product_type_id` bigint unsigned DEFAULT NULL,
  `product_type_name` varchar(100) DEFAULT NULL,
  `domain_id` bigint unsigned DEFAULT NULL,
  `domain_name` varchar(100) DEFAULT NULL,
  `external_request_id` bigint unsigned NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `offers_count` int NOT NULL DEFAULT '0',
  `min_price` decimal(15,2) DEFAULT NULL,
  `max_price` decimal(15,2) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'RUB',
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_catalog_items_external_item_id_unique` (`external_item_id`),
  KEY `public_catalog_items_external_item_id_index` (`external_item_id`),
  KEY `public_catalog_items_product_type_id_index` (`product_type_id`),
  KEY `public_catalog_items_domain_id_index` (`domain_id`),
  KEY `public_catalog_items_offers_count_index` (`offers_count`),
  KEY `public_catalog_items_is_published_index` (`is_published`),
  KEY `public_catalog_items_product_type_id_domain_id_index` (`product_type_id`,`domain_id`),
  FULLTEXT KEY `public_catalog_items_name_brand_article_fulltext` (`name`,`brand`,`article`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Отмечаем миграции как выполненные
INSERT IGNORE INTO `migrations` (`migration`, `batch`)
SELECT '2026_01_19_000002_add_pdf_fields_to_reports_table', MAX(batch) + 1 FROM migrations
UNION ALL
SELECT '2026_01_19_130000_add_n8n_report_id_to_reports_table', MAX(batch) + 1 FROM migrations
UNION ALL
SELECT '2026_01_21_120000_create_public_catalog_items_table', MAX(batch) + 1 FROM migrations;
