-- Ручное применение pending миграций для локальной разработки
-- Выполните этот SQL в вашей БД iqot_platform

-- 1. Добавляем поля в reports (если их нет)
SET @dbname = DATABASE();
SET @tablename = "reports";

-- callback_url
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = 'callback_url') > 0,
  "SELECT 1",
  "ALTER TABLE reports ADD COLUMN callback_url VARCHAR(500) NULL AFTER status"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- error_code
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = 'error_code') > 0,
  "SELECT 1",
  "ALTER TABLE reports ADD COLUMN error_code VARCHAR(50) NULL AFTER summary"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- error_message
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = 'error_message') > 0,
  "SELECT 1",
  "ALTER TABLE reports ADD COLUMN error_message TEXT NULL AFTER error_code"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- pdf_content
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = 'pdf_content') > 0,
  "SELECT 1",
  "ALTER TABLE reports ADD COLUMN pdf_content LONGTEXT NULL AFTER file_path"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- pdf_expires_at
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = 'pdf_expires_at') > 0,
  "SELECT 1",
  "ALTER TABLE reports ADD COLUMN pdf_expires_at TIMESTAMP NULL AFTER pdf_content"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- n8n_report_id
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = 'n8n_report_id') > 0,
  "SELECT 1",
  "ALTER TABLE reports ADD COLUMN n8n_report_id BIGINT UNSIGNED NULL AFTER id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Создаем индексы если их нет
CREATE INDEX IF NOT EXISTS reports_status_index ON reports(status);
CREATE INDEX IF NOT EXISTS reports_n8n_report_id_index ON reports(n8n_report_id);

-- 2. Создаем таблицу public_catalog_items
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

-- 3. Отмечаем миграции как выполненные
INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES
('2026_01_19_000002_add_pdf_fields_to_reports_table', (SELECT IFNULL(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS temp)),
('2026_01_19_130000_add_n8n_report_id_to_reports_table', (SELECT IFNULL(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS temp2)),
('2026_01_21_120000_create_public_catalog_items_table', (SELECT IFNULL(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS temp3));

-- Готово!
SELECT 'Миграции применены успешно!' AS status;
