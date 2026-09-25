-- Phase 7: Expiry, Storage, and Responsive Images Migration

-- 1. Add expiry and storage tracking to pages
ALTER TABLE `pages`
  ADD COLUMN `expiry_date` DATETIME NULL DEFAULT NULL AFTER `status`,
  ADD COLUMN `storage_bytes` BIGINT UNSIGNED DEFAULT 0 AFTER `expiry_date`,
  ADD COLUMN `is_expired` TINYINT(1) DEFAULT 0 AFTER `storage_bytes`;

-- Set default 10-day expiry for all existing published pages
UPDATE `pages` SET `expiry_date` = DATE_ADD(NOW(), INTERVAL 10 DAY) 
WHERE `status` = 'published' AND `expiry_date` IS NULL;

-- Index for cron job query optimization
CREATE INDEX `idx_pages_expiry` ON `pages` (`expiry_date`, `is_expired`, `status`);

-- 2. Add watch time and completion to page_views
ALTER TABLE `page_views`
  ADD COLUMN `watch_time_seconds` INT UNSIGNED DEFAULT 0 AFTER `user_agent`,
  ADD COLUMN `completed` TINYINT(1) DEFAULT 0 AFTER `watch_time_seconds`;

-- 3. Add responsive image variant columns to page_images
ALTER TABLE `page_images`
  ADD COLUMN `thumb_path` VARCHAR(255) NULL AFTER `image_path`,
  ADD COLUMN `medium_path` VARCHAR(255) NULL AFTER `thumb_path`;

-- 4. Create expiry extensions billing log table
CREATE TABLE IF NOT EXISTS `expiry_extensions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_id` INT NOT NULL,
  `user_id` INT NULL,
  `days_added` INT NOT NULL DEFAULT 1,
  `amount_paise` INT NOT NULL DEFAULT 100, -- ₹1 = 100 paise
  `payment_id` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Add default site settings for expiry
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
  ('default_expiry_days', '10'),
  ('expiry_extension_price_paise', '100')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
