-- Migration: Category Control System (Phase 8)
-- Description: Create categories table and configure schema

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'enabled', -- enabled, disabled, hidden, maintenance
  `featured` TINYINT DEFAULT 0,
  `display_order` INT DEFAULT 0,
  `theme` VARCHAR(100) DEFAULT 'romantic',
  `default_title` VARCHAR(255) NULL,
  `default_letter` TEXT NULL,
  `default_question` VARCHAR(255) NULL,
  `font` VARCHAR(100) NULL,
  `accent_hex` VARCHAR(50) NULL,
  `music_url` VARCHAR(255) NULL,
  `slides` LONGTEXT NULL, -- JSON formatted default slides & animations
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: The 16 default categories and their complex nested slides configuration
-- are automatically seeded into this table via PHP upon the first page load 
-- (see auto-installer logic in includes/db.php).
