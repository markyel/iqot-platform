-- Миграция для добавления полей валидации заявок
-- Дата: 2025-12-23
-- Описание: Добавление контактной информации и полей валидации позиций

-- ============================================
-- 1. Добавление полей в таблицу requests
-- ============================================

ALTER TABLE `requests` 
ADD COLUMN `company_name` VARCHAR(255) NULL AFTER `description`,
ADD COLUMN `company_address` VARCHAR(500) NULL AFTER `company_name`,
ADD COLUMN `inn` VARCHAR(12) NULL AFTER `company_address`,
ADD COLUMN `kpp` VARCHAR(9) NULL AFTER `inn`,
ADD COLUMN `contact_person` VARCHAR(255) NULL AFTER `kpp`,
ADD COLUMN `contact_phone` VARCHAR(20) NULL AFTER `contact_person`;

-- ============================================
-- 2. Добавление полей в таблицу request_items
-- ============================================

ALTER TABLE `request_items` 
ADD COLUMN `equipment_type` ENUM('lift', 'escalator') NULL AFTER `name`,
ADD COLUMN `equipment_brand` VARCHAR(255) NULL AFTER `equipment_type`,
ADD COLUMN `manufacturer_article` VARCHAR(255) NULL AFTER `brand`;

-- ============================================
-- Проверка результата
-- ============================================

-- Проверить структуру таблицы requests
-- DESCRIBE requests;

-- Проверить структуру таблицы request_items
-- DESCRIBE request_items;
