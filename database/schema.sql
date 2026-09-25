-- Loopr MySQL Schema (Phase 6)
-- Suitable for any cPanel or shared hosting

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(100) NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NULL,
  `role` VARCHAR(20) DEFAULT 'user', -- 'admin' or 'user'
  `profile_photo` VARCHAR(500) NULL,
  `free_pages_used` INT DEFAULT 0,
  `credits` INT DEFAULT 0,
  `reset_token` VARCHAR(255) NULL,
  `reset_expires` DATETIME NULL,
  `status` VARCHAR(20) DEFAULT 'active', -- 'active' or 'suspended'
  `referral_code` VARCHAR(20) NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `razorpay_order_id` VARCHAR(100) NULL,
  `razorpay_payment_id` VARCHAR(100) NULL,
  `razorpay_signature` VARCHAR(255) NULL,
  `amount` INT NOT NULL DEFAULT 0, -- Amount in paise
  `currency` VARCHAR(10) DEFAULT 'INR',
  `status` VARCHAR(20) DEFAULT 'created', -- created, captured, failed, refunded
  `credits_added` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `referrals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL, -- The referrer
  `referred_user_id` INT NOT NULL, -- The new user
  `reward_given` TINYINT DEFAULT 0, -- 1 if credit awarded
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`referred_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `music_library` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `category` VARCHAR(50) NOT NULL,
  `template` VARCHAR(50) NULL,
  `title` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `sender_name` VARCHAR(100) NOT NULL,
  `receiver_name` VARCHAR(100) NOT NULL,
  `nickname` VARCHAR(100) NULL,
  `relationship_date` VARCHAR(50) NULL,
  `letter_text` TEXT NOT NULL,
  `slide_data` LONGTEXT NULL,
  `music_url` VARCHAR(255) NULL,
  `theme` VARCHAR(50) DEFAULT 'romantic',
  `accent_color` VARCHAR(50) DEFAULT '#ec4899',
  `font_style` VARCHAR(50) DEFAULT 'Playfair Display',
  `proposal_question` VARCHAR(255) DEFAULT 'Will you go out with me?',
  `video_url` VARCHAR(255) NULL,
  `voice_url` VARCHAR(255) NULL,
  `letter_voice_url` VARCHAR(255) NULL,
  `destination` VARCHAR(100) NULL,
  `password` VARCHAR(255) NULL,
  `status` VARCHAR(20) DEFAULT 'draft',
  `guest_session_id` VARCHAR(100) NULL,
  `expiry_date` DATETIME NULL DEFAULT NULL,
  `storage_bytes` BIGINT UNSIGNED DEFAULT 0,
  `is_expired` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `thumb_path` VARCHAR(255) NULL,
  `medium_path` VARCHAR(255) NULL,
  `position` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_videos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_id` INT NOT NULL,
  `video_path` VARCHAR(255) NOT NULL,
  `position` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_replies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_id` INT NOT NULL,
  `visitor_name` VARCHAR(100) NULL,
  `reply_type` VARCHAR(20) NOT NULL DEFAULT 'text', -- text, voice, image, video, emoji
  `message` TEXT NULL,
  `voice_path` VARCHAR(255) NULL,
  `image_path` VARCHAR(255) NULL,
  `video_path` VARCHAR(255) NULL,
  `ip` VARCHAR(45) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_views` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_id` INT NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NULL,
  `watch_time_seconds` INT UNSIGNED DEFAULT 0,
  `completed` TINYINT(1) DEFAULT 0,
  `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_id` INT NOT NULL,
  `reaction_type` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expiry_extensions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_id` INT NOT NULL,
  `user_id` INT NULL,
  `days_added` INT NOT NULL DEFAULT 1,
  `amount_paise` INT NOT NULL DEFAULT 100,
  `payment_id` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default Music Library
INSERT INTO `music_library` (`title`, `file_path`, `category`) VALUES
('Romantic Piano Instrumental', 'assets/music/romantic_piano.mp3', 'proposal'),
('Love Acoustic Guitar', 'assets/music/love_acoustic.mp3', 'proposal'),
('Happy Birthday Pop', 'assets/music/happy_birthday.mp3', 'birthday'),
('Happy Anniversary Waltz', 'assets/music/anniversary_waltz.mp3', 'anniversary'),
('Sad Melancholy Violin', 'assets/music/sorry_violin.mp3', 'sorry'),
('Chill Lo-fi Beat', 'assets/music/lofi_chill.mp3', 'all'),
('Friendship Anthem', 'assets/music/friendship_anthem.mp3', 'friendship'),
('Celebration Fireworks', 'assets/music/celebration.mp3', 'congratulations'),
('Emotional Piano Ballad', 'assets/music/emotional_piano.mp3', 'miss_you'),
('Dreamy Sunset Vibes', 'assets/music/sunset_vibes.mp3', 'crush'),
('Gentle Apology Strings', 'assets/music/apology_strings.mp3', 'patchup'),
('Surprise Party Mix', 'assets/music/surprise_mix.mp3', 'surprise'),
('Wedding Bells', 'assets/music/wedding_bells.mp3', 'wedding'),
('Long Distance Piano', 'assets/music/long_distance.mp3', 'long_distance'),
('Grateful Heart', 'assets/music/grateful_heart.mp3', 'parents');

-- Seed default Administrator (ssrnov@gmail.com / Jayshreeram@12345)
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES
('Super Admin', 'ssrnov@gmail.com', '$2b$10$TiUCXoRNnPsN8MhfGwdoF.P0xqzVlRaTt2siO2E6rWuhc.4H65e5e', 'admin');

-- Seed default Site Settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('price_per_page', '1000'),
('free_pages_per_user', '1'),
('razorpay_key_id', ''),
('razorpay_key_secret', ''),
('google_client_id', ''),
('google_client_secret', ''),
('adsense_publisher_id', ''),
('site_title', 'Loopr - Express Your Feelings Beautifully'),
('site_description', 'Create emotional interactive webpages for proposals, birthdays, apologies and more. No coding needed.'),
('enable_ads', '0'),
('default_expiry_days', '10'),
('expiry_extension_price_paise', '100'),
('currency', 'INR');

-- Category Control System Table
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'enabled',
  `featured` TINYINT DEFAULT 0,
  `display_order` INT DEFAULT 0,
  `theme` VARCHAR(100) DEFAULT 'romantic',
  `default_title` VARCHAR(255) NULL,
  `default_letter` TEXT NULL,
  `default_question` VARCHAR(255) NULL,
  `font` VARCHAR(100) NULL,
  `accent_hex` VARCHAR(50) NULL,
  `music_url` VARCHAR(255) NULL,
  `slides` LONGTEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

