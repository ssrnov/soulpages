<?php
// SoulSync Database Configuration

$db_host = 'localhost';
$db_name = 'looprsi1_nothing';
$db_user = 'looprsi1_ssrnov';
$db_pass = 'Jayshreeram@12345';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // --- AUTOMATIC DATABASE SCHEMA INSTALLER ---
    // Check if the 'users' table exists. If not, auto-import database/schema.sql
    $db_initialized = true;
    try {
        $pdo->query("SELECT 1 FROM `users` LIMIT 1");
    } catch (PDOException $e) {
        $db_initialized = false;
    }
    
    if (!$db_initialized) {
        $sql_file = __DIR__ . '/../database/schema.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            
            // Clean SQL: remove comments
            $sql = preg_replace('!/\*.*?\*/!s', '', $sql); // Multi-line comments
            $sql = preg_replace('/^\s*--.*$/m', '', $sql); // Single-line double dash comments
            $sql = preg_replace('/^\s*#.*$/m', '', $sql);  // Single-line hash comments
            
            // Split queries by semicolon
            $queries = explode(';', $sql);
            
            foreach ($queries as $q) {
                $q = trim($q);
                if (!empty($q)) {
                    $pdo->exec($q);
                }
            }
        }
    }
    
    // --- AUTOMATIC COLUMN MIGRATION (for existing deployments) ---
    if ($db_initialized) {
        // --- Users table new columns ---
        $user_migrations = [
            'template'          => "ALTER TABLE `pages` ADD COLUMN `template` VARCHAR(50) NULL AFTER `category`",
            'nickname'          => "ALTER TABLE `pages` ADD COLUMN `nickname` VARCHAR(100) NULL AFTER `receiver_name`",
            'relationship_date' => "ALTER TABLE `pages` ADD COLUMN `relationship_date` VARCHAR(50) NULL AFTER `nickname`",
            'slide_data'        => "ALTER TABLE `pages` ADD COLUMN `slide_data` LONGTEXT NULL AFTER `letter_text`",
            'theme'             => "ALTER TABLE `pages` ADD COLUMN `theme` VARCHAR(50) DEFAULT 'romantic' AFTER `music_url`",
            'video_url'         => "ALTER TABLE `pages` ADD COLUMN `video_url` VARCHAR(255) NULL AFTER `proposal_question`",
            'voice_url'         => "ALTER TABLE `pages` ADD COLUMN `voice_url` VARCHAR(255) NULL AFTER `video_url`",
            'letter_voice_url'  => "ALTER TABLE `pages` ADD COLUMN `letter_voice_url` VARCHAR(255) NULL AFTER `voice_url`",
            'destination'       => "ALTER TABLE `pages` ADD COLUMN `destination` VARCHAR(100) NULL AFTER `letter_voice_url`",
            'password'          => "ALTER TABLE `pages` ADD COLUMN `password` VARCHAR(255) NULL AFTER `destination`",
            'photo_fit_mode'    => "ALTER TABLE `pages` ADD COLUMN `photo_fit_mode` VARCHAR(20) DEFAULT 'cover' AFTER `password`",
            // --- Universal Interactive Reply System columns ---
            'interactive_ending'    => "ALTER TABLE `pages` ADD COLUMN `interactive_ending` TINYINT(1) DEFAULT 1 AFTER `photo_fit_mode`",
            'interactive_question'  => "ALTER TABLE `pages` ADD COLUMN `interactive_question` VARCHAR(255) NULL AFTER `interactive_ending`",
            'interactive_yes_text'  => "ALTER TABLE `pages` ADD COLUMN `interactive_yes_text` VARCHAR(100) NULL AFTER `interactive_question`",
            'interactive_no_text'   => "ALTER TABLE `pages` ADD COLUMN `interactive_no_text` VARCHAR(100) NULL AFTER `interactive_yes_text`",
            'interactive_funny_no'  => "ALTER TABLE `pages` ADD COLUMN `interactive_funny_no` TINYINT(1) DEFAULT 1 AFTER `interactive_no_text`",
            'interactive_ask_name'  => "ALTER TABLE `pages` ADD COLUMN `interactive_ask_name` TINYINT(1) DEFAULT 1 AFTER `interactive_funny_no`",
        ];
        
        foreach ($user_migrations as $col_name => $alter_sql) {
            try {
                $pdo->query("SELECT `$col_name` FROM `pages` LIMIT 1");
            } catch (PDOException $e) {
                try { $pdo->exec($alter_sql); } catch (PDOException $ex) {}
            }
        }

        // --- Phase 7: Pages table new columns ---
        $phase7_pages_migrations = [
            'expiry_date'   => "ALTER TABLE `pages` ADD COLUMN `expiry_date` DATETIME NULL DEFAULT NULL AFTER `status`",
            'storage_bytes' => "ALTER TABLE `pages` ADD COLUMN `storage_bytes` BIGINT UNSIGNED DEFAULT 0 AFTER `expiry_date`",
            'is_expired'    => "ALTER TABLE `pages` ADD COLUMN `is_expired` TINYINT(1) DEFAULT 0 AFTER `storage_bytes`"
        ];
        foreach ($phase7_pages_migrations as $col_name => $alter_sql) {
            try {
                $pdo->query("SELECT `$col_name` FROM `pages` LIMIT 1");
            } catch (PDOException $e) {
                try { 
                    $pdo->exec($alter_sql); 
                    if ($col_name === 'expiry_date') {
                        $pdo->exec("UPDATE `pages` SET `expiry_date` = DATE_ADD(NOW(), INTERVAL 10 DAY) WHERE `status` = 'published' AND `expiry_date` IS NULL");
                    }
                } catch (PDOException $ex) {}
            }
        }

        // --- Voice Note metadata migrations ---
        $voice_metadata_migrations = [
            'voice_duration'          => "ALTER TABLE `pages` ADD COLUMN `voice_duration` INT NULL DEFAULT NULL AFTER `voice_url`",
            'voice_size'              => "ALTER TABLE `pages` ADD COLUMN `voice_size` INT NULL DEFAULT NULL AFTER `voice_duration`",
            'voice_created_at'        => "ALTER TABLE `pages` ADD COLUMN `voice_created_at` TIMESTAMP NULL DEFAULT NULL AFTER `voice_size`",
            'letter_voice_duration'   => "ALTER TABLE `pages` ADD COLUMN `letter_voice_duration` INT NULL DEFAULT NULL AFTER `letter_voice_url`",
            'letter_voice_size'       => "ALTER TABLE `pages` ADD COLUMN `letter_voice_size` INT NULL DEFAULT NULL AFTER `letter_voice_duration`",
            'letter_voice_created_at' => "ALTER TABLE `pages` ADD COLUMN `letter_voice_created_at` TIMESTAMP NULL DEFAULT NULL AFTER `letter_voice_size`"
        ];
        foreach ($voice_metadata_migrations as $col_name => $alter_sql) {
            try {
                $pdo->query("SELECT `$col_name` FROM `pages` LIMIT 1");
            } catch (PDOException $e) {
                try { $pdo->exec($alter_sql); } catch (PDOException $ex) {}
            }
        }

        // --- Phase 7: page_images new columns ---
        $phase7_images_migrations = [
            'thumb_path'  => "ALTER TABLE `page_images` ADD COLUMN `thumb_path` VARCHAR(255) NULL AFTER `image_path`",
            'medium_path' => "ALTER TABLE `page_images` ADD COLUMN `medium_path` VARCHAR(255) NULL AFTER `thumb_path`"
        ];
        foreach ($phase7_images_migrations as $col_name => $alter_sql) {
            try {
                $pdo->query("SELECT `$col_name` FROM `page_images` LIMIT 1");
            } catch (PDOException $e) {
                try { $pdo->exec($alter_sql); } catch (PDOException $ex) {}
            }
        }

        // --- Phase 7: page_views new columns ---
        $phase7_views_migrations = [
            'watch_time_seconds' => "ALTER TABLE `page_views` ADD COLUMN `watch_time_seconds` INT UNSIGNED DEFAULT 0 AFTER `user_agent`",
            'completed'          => "ALTER TABLE `page_views` ADD COLUMN `completed` TINYINT(1) DEFAULT 0 AFTER `watch_time_seconds`"
        ];
        foreach ($phase7_views_migrations as $col_name => $alter_sql) {
            try {
                $pdo->query("SELECT `$col_name` FROM `page_views` LIMIT 1");
            } catch (PDOException $e) {
                try { $pdo->exec($alter_sql); } catch (PDOException $ex) {}
            }
        }
        
        // --- Phase 7: music_library new columns ---
        $phase7_music_migrations = [
            'status'      => "ALTER TABLE `music_library` ADD COLUMN `status` VARCHAR(20) DEFAULT 'approved' AFTER `category`",
            'is_private'  => "ALTER TABLE `music_library` ADD COLUMN `is_private` TINYINT(1) DEFAULT 0 AFTER `status`",
            'uploaded_by' => "ALTER TABLE `music_library` ADD COLUMN `uploaded_by` INT NULL AFTER `is_private`"
        ];
        foreach ($phase7_music_migrations as $col_name => $alter_sql) {
            try {
                $pdo->query("SELECT `$col_name` FROM `music_library` LIMIT 1");
            } catch (PDOException $e) {
                try { $pdo->exec($alter_sql); } catch (PDOException $ex) {}
            }
        }
        try {
            $pdo->exec("ALTER TABLE `music_library` ADD CONSTRAINT `fk_music_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL");
        } catch (PDOException $ex) {}
        
        // --- Phase 6: Users table Google/credits columns ---
        $user_col_migrations = [
            'profile_photo'      => "ALTER TABLE `users` ADD COLUMN `profile_photo` VARCHAR(500) NULL AFTER `password`",
            'status'             => "ALTER TABLE `users` ADD COLUMN `status` VARCHAR(20) DEFAULT 'active' AFTER `role`",
            'email_verified'     => "ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) DEFAULT 0 AFTER `status`",
            'verification_token' => "ALTER TABLE `users` ADD COLUMN `verification_token` VARCHAR(100) NULL AFTER `email_verified`",
            'referral_code'      => "ALTER TABLE `users` ADD COLUMN `referral_code` VARCHAR(20) NULL UNIQUE AFTER `verification_token`",
            'username'           => "ALTER TABLE `users` ADD COLUMN `username` VARCHAR(100) NULL UNIQUE AFTER `name`",
            'free_pages_used'    => "ALTER TABLE `users` ADD COLUMN `free_pages_used` INT DEFAULT 0 AFTER `referral_code`",
            'credits'            => "ALTER TABLE `users` ADD COLUMN `credits` INT DEFAULT 0 AFTER `free_pages_used`",
            'reset_token'        => "ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(255) NULL AFTER `credits`",
            'reset_expires'      => "ALTER TABLE `users` ADD COLUMN `reset_expires` DATETIME NULL AFTER `reset_token`",
            'updated_at'         => "ALTER TABLE `users` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`"
        ];
        
        foreach ($user_col_migrations as $col_name => $alter_sql) {
            try {
                $pdo->query("SELECT `$col_name` FROM `users` LIMIT 1");
            } catch (PDOException $e) {
                try { $pdo->exec($alter_sql); } catch (PDOException $ex) {}
            }
        }
        
        // --- Phase 6: New tables auto-creation ---
        $new_tables = [
            'site_settings' => "CREATE TABLE IF NOT EXISTS `site_settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                `setting_value` TEXT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            
            'payments' => "CREATE TABLE IF NOT EXISTS `payments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `razorpay_order_id` VARCHAR(100) NULL,
                `razorpay_payment_id` VARCHAR(100) NULL,
                `razorpay_signature` VARCHAR(255) NULL,
                `amount` INT NOT NULL DEFAULT 0,
                `currency` VARCHAR(10) DEFAULT 'INR',
                `status` VARCHAR(20) DEFAULT 'created',
                `credits_added` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'referrals' => "CREATE TABLE IF NOT EXISTS `referrals` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `referred_user_id` INT NOT NULL,
                `reward_given` TINYINT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`referred_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'page_videos' => "CREATE TABLE IF NOT EXISTS `page_videos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `page_id` INT NOT NULL,
                `video_path` VARCHAR(255) NOT NULL,
                `position` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'page_replies' => "CREATE TABLE IF NOT EXISTS `page_replies` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `page_id` INT NOT NULL,
                `visitor_name` VARCHAR(100) NULL,
                `reply_type` VARCHAR(20) NOT NULL,
                `message` TEXT NULL,
                `voice_path` VARCHAR(255) NULL,
                `image_path` VARCHAR(255) NULL,
                `video_path` VARCHAR(255) NULL,
                `ip` VARCHAR(45) NULL,
                `is_read` TINYINT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'expiry_extensions' => "CREATE TABLE IF NOT EXISTS `expiry_extensions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `page_id` INT NOT NULL,
                `user_id` INT NULL,
                `days_added` INT NOT NULL DEFAULT 1,
                `amount_paise` INT NOT NULL DEFAULT 100,
                `payment_id` VARCHAR(100) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'categories' => "CREATE TABLE IF NOT EXISTS `categories` (
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
                `interactive_question` VARCHAR(255) NULL,
                `interactive_yes_text` VARCHAR(100) NULL,
                `interactive_no_text` VARCHAR(100) NULL,
                `notification_msg` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'interactive_replies' => "CREATE TABLE IF NOT EXISTS `interactive_replies` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `page_id` INT NOT NULL,
                `category` VARCHAR(50) NOT NULL,
                `visitor_name` VARCHAR(100) DEFAULT 'Anonymous',
                `question` TEXT NOT NULL,
                `selected_answer` VARCHAR(20) NOT NULL,
                `positive_button_text` VARCHAR(100) NULL,
                `negative_button_text` VARCHAR(100) NULL,
                `no_click_count` INT DEFAULT 0,
                `browser` VARCHAR(255) NULL,
                `device` VARCHAR(100) NULL,
                `country` VARCHAR(100) NULL,
                `city` VARCHAR(100) NULL,
                `visitor_ip` VARCHAR(45) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'notifications' => "CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `page_id` INT NULL,
                `type` VARCHAR(50) NOT NULL,
                `message` TEXT NOT NULL,
                `is_read` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
        
        // Auto-migrate: Drop page_replies if it exists but uses the old schema structure
        try {
            $pdo->query("SELECT `visitor_name` FROM `page_replies` LIMIT 1");
        } catch (PDOException $e) {
            $table_exists = false;
            try {
                $pdo->query("SELECT 1 FROM `page_replies` LIMIT 1");
                $table_exists = true;
            } catch (PDOException $ex) {}
            
            if ($table_exists) {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `page_replies`");
                } catch (PDOException $ex) {}
            }
        }
        
        foreach ($new_tables as $table_name => $create_sql) {
            try {
                $pdo->query("SELECT 1 FROM `$table_name` LIMIT 1");
            } catch (PDOException $e) {
                try { $pdo->exec($create_sql); } catch (PDOException $ex) {}
            }
        }
        
        // Migrate credits from user_credits table to users.credits if it exists
        try {
            $table_check = $pdo->query("SHOW TABLES LIKE 'user_credits'")->fetch();
            if ($table_check) {
                $credits_stmt = $pdo->query("SELECT user_id, credits FROM user_credits");
                $all_credits = $credits_stmt->fetchAll();
                if (!empty($all_credits)) {
                    $upd_stmt = $pdo->prepare("UPDATE users SET credits = ? WHERE id = ?");
                    foreach ($all_credits as $uc) {
                        $upd_stmt->execute([$uc['credits'], $uc['user_id']]);
                    }
                }
                $pdo->exec("DROP TABLE IF EXISTS `user_credits`");
            }
        } catch (PDOException $e) {
            // Ignore migration error
        }
        
        // Seed site_settings defaults if table is empty
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `site_settings`")->fetchColumn();
            if ($count == 0) {
                $defaults = [
                    ['price_per_page', '1000'],
                    ['free_pages_per_user', '1'],
                    ['razorpay_key_id', ''],
                    ['razorpay_key_secret', ''],
                    ['google_client_id', ''],
                    ['google_client_secret', ''],
                    ['adsense_publisher_id', ''],
                    ['site_title', 'SoulSync - Express Your Feelings Beautifully'],
                    ['site_description', 'Create emotional interactive webpages for proposals, birthdays, apologies and more.'],
                    ['enable_ads', '0'],
                    ['adsense_test_mode', '1'],
                    ['currency', 'INR'],
                    ['default_expiry_days', '10'],
                    ['expiry_extension_price_paise', '100'],
                ];
                $ins = $pdo->prepare("INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
                foreach ($defaults as $d) {
                    $ins->execute([$d[0], $d[1]]);
                }
            }
        } catch (PDOException $e) {}
        
        // Seed and sync categories table
        try {
            $premium = [
                ['type' => 'premium_memory_reveal', 'key' => 'premium_memory_reveal', 'title' => 'Memory Reveal', 'subtitle' => 'Our special memories unlocked', 'is_optional' => true],
                ['type' => 'premium_heart_formation', 'key' => 'premium_heart_formation', 'title' => 'Heart Formation', 'subtitle' => 'Thousands of particles coalesce', 'is_optional' => true],
                ['type' => 'premium_collage_explosion', 'key' => 'premium_collage_explosion', 'title' => 'Collage Explosion', 'subtitle' => 'Memories flying together', 'is_optional' => true],
                ['type' => 'premium_star_sky', 'key' => 'premium_star_sky', 'title' => 'Star Memory Sky', 'subtitle' => 'Memories shining in the stars', 'is_optional' => true],
                ['type' => 'premium_memory_book', 'key' => 'premium_memory_book', 'title' => 'Memory Book', 'subtitle' => 'Turn the pages of our story', 'is_optional' => true],
                ['type' => 'premium_reasons_special', 'key' => 'premium_reasons_special', 'title' => 'Why You Are Special', 'subtitle' => 'Reasons why you are so important', 'is_optional' => true],
                ['type' => 'premium_puzzle_reveal', 'key' => 'premium_puzzle_reveal', 'title' => 'Photo Puzzle Reveal', 'subtitle' => 'Solving our picture together', 'is_optional' => true],
                ['type' => 'premium_memory_timeline', 'key' => 'premium_memory_timeline', 'title' => 'Memory Timeline', 'subtitle' => 'Our milestone journey', 'is_optional' => true],
                ['type' => 'premium_mosaic_heart', 'key' => 'premium_mosaic_heart', 'title' => 'Photo Mosaic Heart', 'subtitle' => 'Every piece belongs to you', 'is_optional' => true],
                ['type' => 'premium_our_chats', 'key' => 'premium_our_chats', 'title' => 'Our Chats', 'subtitle' => 'Screenshots from our story', 'is_optional' => true],
            ];

            $default_categories = [
                [
                    'name' => 'Proposal',
                    'slug' => 'proposal',
                    'icon' => '🌹',
                    'description' => 'Express your feelings, share memories, and ask them out with an interactive experience.',
                    'theme' => 'romantic',
                    'default_title' => 'My Secret Confession for You',
                    'default_letter' => "My dearest [receiver],\n\nI've been thinking about the right words for so long — and I realize there are no perfect words, only honest ones. From the moment you entered my life, everything became a little brighter, a little warmer, and infinitely more beautiful.\n\nEvery smile we share, every conversation, and even the quiet moments of connection mean the world to me. You are my favorite thought, my safest place, and the person I want to share all my tomorrows with.\n\nI wrote this letter because my heart couldn't keep these feelings to itself anymore. I want you to know how deeply you are loved, just as you are...",
                    'default_question' => 'Will You Be Mine? 💖',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#e11d48',
                    'music_url' => '',
                    'status' => 'enabled',
                    'interactive_question' => 'Will you be mine forever? ❤️',
                    'interactive_yes_text' => 'YES ❤️',
                    'interactive_no_text' => 'NO 😭',
                    'notification_msg' => '❤️ Someone accepted your proposal!',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Hey [receiver] ❤️', 'subtitle' => 'Someone spent time creating something special for you.', 'btn' => 'Open My Heart', 'animation' => 'typewriter'],
                        ['type' => 'proposal_rose_cinematic', 'title' => 'A Rose for You 🌹', 'subtitle' => 'Watch it bloom...', 'animation' => 'zoomIn'],
                        ['type' => 'text_story', 'key' => 'first_impression', 'title' => 'First Impression', 'subtitle' => 'How it all started...', 'default' => "The first time I saw you, something in me just clicked...", 'show_photo' => true, 'animation' => 'fadeIn'],
                        ['type' => 'proposal_constellation', 'title' => 'Our Memory Stars ✨', 'subtitle' => 'Watch the sky tell our story', 'animation' => 'fadeIn'],
                        ['type' => 'proposal_heart_formation', 'title' => 'My Heart For You 💖', 'subtitle' => 'Watch what my feelings become', 'animation' => 'fadeIn'],
                        ['type' => 'proposal_love_letter', 'title' => 'Words From My Soul ✍️', 'subtitle' => 'Written just for you', 'animation' => 'fadeIn'],
                        ['type' => 'proposal_future_portal', 'title' => 'Our Future Together 🌀', 'subtitle' => 'Step through the portal', 'animation' => 'zoomIn'],
                        ['type' => 'proposal_countdown', 'title' => 'Counting Every Moment 💓', 'subtitle' => 'Since I fell for you', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Photo Gallery', 'subtitle' => 'Our beautiful moments together', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'key' => 'proposal_letter', 'title' => 'My Heart Speaks', 'subtitle' => 'A Message Just For You', 'btn' => 'Read Every Word', 'animation' => 'fade', 'show_photo' => false],
                        ['type' => 'voice_message', 'title' => 'Hear My Voice 🎤', 'subtitle' => 'I recorded something just for you', 'animation' => 'fadeIn'],
                        ['type' => 'video_message', 'title' => 'A Special Video For You 🎬', 'subtitle' => 'Press play to watch', 'animation' => 'fadeIn'],
                        ['type' => 'proposal_ring_cinematic', 'title' => 'Something Special 💍', 'subtitle' => 'A moment you will never forget', 'animation' => 'zoomIn'],
                    ], $premium, [
                        ['type' => 'proposal_final_cinematic', 'title' => 'My Ultimate Question 💖', 'subtitle' => 'The moment of truth', 'animation' => 'bounceIn'],
                        ['type' => 'proposal_celebration', 'title' => 'Our Story Begins 🌹', 'subtitle' => 'Forever starts now', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Sorry',
                    'slug' => 'sorry',
                    'icon' => '😔',
                    'description' => 'Apologize with a heartfelt story, admit your mistake, and ask for forgiveness.',
                    'theme' => 'elegant',
                    'default_title' => 'I Am Really Sorry...',
                    'default_letter' => "I know I made a mistake, and I feel terrible about it. Our relationship means everything to me, and I hate knowing that I caused you pain or disappointed you. I created this page to sincerely apologize and ask for your forgiveness. Let's make things right again.",
                    'default_question' => 'Can You Forgive Me? 🥺',
                    'font' => 'Outfit',
                    'accent_hex' => '#3b82f6',
                    'music_url' => '',
                    'status' => 'enabled',
                    'interactive_question' => 'Will you forgive me?',
                    'interactive_yes_text' => 'Forgiven ❤️',
                    'interactive_no_text' => 'Need More Time',
                    'notification_msg' => '🥺 Someone forgave you!',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Hey [receiver]...', 'subtitle' => 'I need 2 minutes of your time. Please.', 'btn' => 'I\'m Listening', 'animation' => 'typewriter'],
                        ['type' => 'sorry_broken_heart_repair', 'title' => 'Mend My Heart 🩹', 'subtitle' => 'Tap the heart pieces to repair the cracks', 'animation' => 'fadeIn'],
                        ['type' => 'sorry_forgiveness_letter', 'title' => 'A Letter of Forgiveness 💌', 'subtitle' => 'Tap the envelope to open', 'animation' => 'zoomIn'],
                        ['type' => 'sorry_blooming_rose', 'title' => 'A Rose Blooms Again 🌹', 'subtitle' => 'Tap to bring it back to life', 'animation' => 'fadeIn'],
                        ['type' => 'sorry_reaching_hands', 'title' => 'Reaching Out To You 🤝', 'subtitle' => 'Drag to bring the hands together', 'animation' => 'fadeIn'],
                        ['type' => 'sorry_memories_restored', 'title' => 'Our Memories ✨', 'subtitle' => 'Tap each memory to restore it', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Beautiful Memories', 'subtitle' => 'Remember when we were happy?', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Apology Letter', 'animation' => 'typewriter'],
                        ['type' => 'voice_message', 'title' => 'Hear My Apology 🎤', 'subtitle' => 'I recorded my sincere words for you', 'animation' => 'fadeIn'],
                        ['type' => 'video_message', 'title' => 'Video Apology 🎬', 'subtitle' => 'Watch my heartfelt apology', 'animation' => 'fadeIn'],
                    ], $premium, [
                        ['type' => 'universal_interactive', 'animation' => 'bounceIn'],
                    ])
                ],
                [
                    'name' => 'Birthday Wish',
                    'slug' => 'birthday',
                    'icon' => '🎂',
                    'description' => 'Send beautiful birthday surprises, interactive candles to blow, and customized cards.',
                    'theme' => 'retro',
                    'default_title' => 'Happy Birthday [Receiver]! 🎉',
                    'default_letter' => "On this special day, I want to remind you of how wonderful you are. You bring so much light and joy to everyone around you. I hope this year brings you endless reasons to smile, happiness that never fades, and the success you truly deserve.",
                    'default_question' => 'Happy Birthday! 🎂',
                    'font' => 'Outfit',
                    'accent_hex' => '#ea580c',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Happy Birthday [receiver]! 🎂', 'subtitle' => 'Someone wants to surprise you on your special day.', 'btn' => 'Unwrap Surprise', 'animation' => 'typewriter'],
                        ['type' => 'birthday_pop_balloons', 'title' => 'Pop the Balloons 🎈', 'subtitle' => 'Tap the balloons to pop them and find hidden wishes!', 'animation' => 'bounceIn'],
                        ['type' => 'birthday_blow_candles', 'title' => 'Make a Wish 🎂', 'subtitle' => 'Hold your breath, make a wish, and click to blow the candles!', 'animation' => 'fadeIn'],
                        ['type' => 'birthday_cake_cutting', 'title' => 'Cake Cutting Time 🍰', 'subtitle' => 'Swipe or click to cut the virtual cake!', 'animation' => 'zoomIn'],
                        ['type' => 'gallery', 'title' => 'Our Best Moments', 'subtitle' => 'Year full of amazing memories', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'A Special Birthday Message', 'animation' => 'typewriter'],
                        ['type' => 'voice_message', 'title' => 'Voice Greetings 🎤', 'subtitle' => 'Press play to hear my voice message', 'animation' => 'fadeIn'],
                        ['type' => 'video_message', 'title' => 'Video Surprise 🎬', 'subtitle' => 'A special video captured just for you', 'animation' => 'fadeIn'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🎉 Happy Birthday! 🎉', 'subtitle' => 'Have the most fantastic year ahead!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Love Letter',
                    'slug' => 'loveletter',
                    'icon' => '🥰',
                    'description' => 'Write a timeless aesthetic love letter, complete with custom ink write-ins and wax seals.',
                    'theme' => 'romantic',
                    'default_title' => 'A Letter From My Soul',
                    'default_letter' => "Writing this is my way of keeping our feelings close. No matter what is happening, knowing you are in my life gives me peace. You are my home, my favorite distraction, and my best decision. I love you, and I will keep choosing you, every single day.",
                    'default_question' => 'I love you to the moon and back ❤️',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#db2777',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'A Letter for [receiver] 🥰', 'subtitle' => 'A sealed message of love is waiting for you.', 'btn' => 'Break the Wax Seal', 'animation' => 'typewriter'],
                        ['type' => 'love_letter_envelope_open', 'title' => 'Opening Envelope ✉️', 'subtitle' => 'Unfolding the feelings stored inside...', 'animation' => 'zoomIn'],
                        ['type' => 'love_letter_handwrite', 'title' => 'Writing Ink ✍️', 'subtitle' => 'Watch the ink capture my feelings...', 'animation' => 'fadeIn'],
                        ['type' => 'love_letter_scrapbook', 'title' => 'Our Scrapbook 📖', 'subtitle' => 'Flipping through the visual journal of our hearts', 'animation' => 'fadeIn'],
                        ['type' => 'love_letter_timeline', 'title' => 'Milestones of Love ⏳', 'subtitle' => 'A history of us growing together', 'animation' => 'slideUp'],
                        ['type' => 'love_letter_folding', 'title' => 'Sending a Kiss 💋', 'subtitle' => 'Fold the envelope and blow a kiss response', 'animation' => 'bounceIn'],
                        ['type' => 'gallery', 'title' => 'Captured Hearts', 'subtitle' => 'Moments we froze in time', 'animation' => 'slideshow'],
                    ], $premium, [
                        ['type' => 'love_letter_reactions_finale', 'title' => 'You Are My Everything ❤️', 'subtitle' => 'Forever and always.', 'animation' => 'heartbeat'],
                    ])
                ],
                [
                    'name' => 'Maan Jao Yaar 🥺',
                    'slug' => 'maanjao',
                    'icon' => '🥺',
                    'description' => 'Playful interactive page to cajole your angry partner with an angry meter, bribes, and quizzes.',
                    'theme' => 'retro',
                    'default_title' => 'Maan Jao Na Please... 🥺',
                    'default_letter' => "I am extremely sorry for being annoying. I know I was wrong, and I promise to make it up to you. I can't stay quiet when you are angry. Here are 8 bribe options and a cute smile request, please maan jao na!",
                    'default_question' => 'Maan Jao Na? 🥺',
                    'font' => 'Outfit',
                    'accent_hex' => '#db2777',
                    'music_url' => '',
                    'status' => 'hidden',
                    'interactive_question' => 'Maan jao na please? 🥺',
                    'interactive_yes_text' => 'Haan, Maan Gayi! ❤️',
                    'interactive_no_text' => 'No, Try Harder',
                    'notification_msg' => '🥺 Someone accepted your apology!',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Maan Jao Please... 🥺', 'subtitle' => '[sender] is trying their best to make you smile.', 'btn' => 'See Their Effort', 'animation' => 'typewriter'],
                        ['type' => 'mana_lo_angry_meter', 'title' => 'Angry Meter 😤', 'subtitle' => 'Are you still this angry at me?', 'animation' => 'fadeIn'],
                        ['type' => 'mana_lo_emergency', 'title' => 'Emergency Alert 🚨', 'subtitle' => 'Crisis detected! Immediate attention required!', 'animation' => 'bounceIn'],
                        ['type' => 'mana_lo_rescue', 'title' => 'Memory Rescue 🩹', 'subtitle' => 'Dragging our favorite memories to heal the fight', 'animation' => 'fadeIn'],
                        ['type' => 'mana_lo_miss_things', 'title' => 'Things I Miss About Us 📝', 'subtitle' => 'A list of what is missing without you', 'animation' => 'slideUp'],
                        ['type' => 'mana_lo_bribe', 'title' => 'Choose Your Bribe 🎁', 'subtitle' => 'I will buy you whatever you choose from these gift boxes!', 'animation' => 'zoomIn'],
                        ['type' => 'mana_lo_quiz', 'title' => 'Truth Quiz 🧐', 'subtitle' => 'Playful quiz to resolve the conflict', 'animation' => 'fadeIn'],
                        ['type' => 'mana_lo_unlock', 'title' => 'Unlock My Heart 🔑', 'subtitle' => 'Drag the key to the lock to release the apology', 'animation' => 'fadeIn'],
                        ['type' => 'mana_lo_smile', 'title' => 'Smile Please! 😊', 'subtitle' => 'Can I get a little smile now?', 'animation' => 'heartbeat'],
                        ['type' => 'gallery', 'title' => 'Our Happy Face', 'subtitle' => 'When we are not fighting', 'animation' => 'slideshow'],
                    ], $premium, [
                        ['type' => 'universal_interactive', 'animation' => 'bounceIn'],
                    ])
                ],
                [
                    'name' => 'Friendship',
                    'slug' => 'friendship',
                    'icon' => '🤝',
                    'description' => 'Celebrate your best friend with a spinning wheel, flip-cards highlights, and an honorary friendship stamp.',
                    'theme' => 'retro',
                    'default_title' => 'To My Partner In Crime',
                    'default_letter' => "Life is a lot more fun with you around. Thank you for the endless laughs, the late-night talks, the bad advice we actually followed, and for always having my back when things got messy. You are more than a friend, you are family.",
                    'default_question' => 'Best Friends Forever? 🤝',
                    'font' => 'Outfit',
                    'accent_hex' => '#06b6d4',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Hey Bestie! 🤝', 'subtitle' => '[sender] wants to celebrate your amazing bond.', 'btn' => 'Start Celebration', 'animation' => 'typewriter'],
                        ['type' => 'friendship_wheel', 'title' => 'Spin the Friendship Wheel 🎡', 'subtitle' => 'Spin to find what makes you the absolute best duo!', 'animation' => 'zoomIn'],
                        ['type' => 'friendship_moment_cards', 'title' => 'Our Core Memories 🃏', 'subtitle' => 'Hover/Tap to flip the cards and see what makes us special', 'animation' => 'fadeIn'],
                        ['type' => 'friendship_badge', 'title' => 'BFF Stamp Award 🏆', 'subtitle' => 'Here is your official certificate for being a awesome friend!', 'animation' => 'bounceIn'],
                        ['type' => 'gallery', 'title' => 'Partner In Crime Gallery', 'subtitle' => 'Crazy photos of our wild adventures', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'A Message to My Best Friend', 'animation' => 'typewriter'],
                        ['type' => 'voice_message', 'title' => 'Audio Message 🎤', 'subtitle' => 'Hear what I have to say', 'animation' => 'fadeIn'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🤝 Friends Forever! 🤝', 'subtitle' => 'Through thick and thin, always together!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Anniversary',
                    'slug' => 'anniversary',
                    'icon' => '💕',
                    'description' => 'Mark your relationship milestone with a time counter ticker, milestone list, and memory flipbook.',
                    'theme' => 'elegant',
                    'default_title' => 'Happy Anniversary My Love',
                    'default_letter' => "Happy anniversary! Reflecting on the time we've spent together makes me realize how incredibly lucky I am. Every single milestone, every trip, every quiet moment at home has built the beautiful story we share. Thank you for loving me.",
                    'default_question' => 'Happy Anniversary! 💕',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#db2777',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Happy Anniversary! 💕', 'subtitle' => '[sender] created a milestone walk down memory lane.', 'btn' => 'Begin Journey', 'animation' => 'typewriter'],
                        ['type' => 'anniversary_together_counter', 'title' => 'Time Spent Together ⏰', 'subtitle' => 'Counting every second since we fell in love', 'animation' => 'zoomIn'],
                        ['type' => 'anniversary_timeline_unlock', 'title' => 'Our Milestones 📍', 'subtitle' => 'A walk through the key events of our relationship', 'animation' => 'slideUp'],
                        ['type' => 'anniversary_memory_book', 'title' => 'Anniversary Scrapbook 📖', 'subtitle' => 'Turning pages on our favorite memories', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Our Journey in Pictures', 'subtitle' => 'Photos that define us', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Heartfelt Letter', 'animation' => 'typewriter'],
                        ['type' => 'voice_message', 'title' => 'A Voice Note 🎤', 'subtitle' => 'My anniversary message to you', 'animation' => 'fadeIn'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '💕 Happy Anniversary! 💕', 'subtitle' => 'Here is to many more years of love and happiness!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Party Invite',
                    'slug' => 'invite',
                    'icon' => '🎉',
                    'description' => 'Invite someone out in style with a date idea spinner and a reveal map.',
                    'theme' => 'retro',
                    'default_title' => 'Let\'s Go Out Together!',
                    'default_letter' => "I want to take you out on a date. I have created this invitation so we can decide where to go and when. Check out the date spinner and map, and let me know your thoughts!",
                    'default_question' => 'Are you free this weekend? 📅',
                    'font' => 'Outfit',
                    'accent_hex' => '#2563eb',
                    'music_url' => '',
                    'status' => 'enabled',
                    'interactive_question' => 'Are you free this weekend? 📅',
                    'interactive_yes_text' => 'Yes, Count Me In! 👍',
                    'interactive_no_text' => 'I\'m Busy',
                    'notification_msg' => '🎉 Someone accepted your invitation!',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'An Invitation for You! ✉️', 'subtitle' => 'You are cordially invited to a special day out.', 'btn' => 'Open Invite', 'animation' => 'typewriter'],
                        ['type' => 'invite_date_wheel', 'title' => 'Spin for Date Idea 🎡', 'subtitle' => 'Spin the wheel to find the perfect plan for us!', 'animation' => 'zoomIn'],
                        ['type' => 'invite_map_reveal', 'title' => 'Destination Reveal 📍', 'subtitle' => 'Tap the map pin to reveal the secret location!', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Where We Could Go', 'subtitle' => 'Sneak peek of the plans', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'A Special Note', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'universal_interactive', 'animation' => 'bounceIn'],
                    ])
                ],
                [
                    'name' => 'Patch-Up',
                    'slug' => 'patchup',
                    'icon' => '💔',
                    'description' => 'Rebuild broken bridges, slide key locks to unlock relationships, and start fresh.',
                    'theme' => 'galaxy',
                    'default_title' => 'Let\'s Patch Up...',
                    'default_letter' => "Our fight was stupid, and being distant hurts. I want to build a bridge back to us. Let's unlock our hearts, clear the air, and focus on what made us happy. I am ready to listen and move forward.",
                    'default_question' => 'Can we patch up and forget the fight? 🥺',
                    'font' => 'Outfit',
                    'accent_hex' => '#7c3aed',
                    'music_url' => '',
                    'status' => 'enabled',
                    'interactive_question' => 'Can we patch up and forget the fight? 🥺',
                    'interactive_yes_text' => 'Let\'s Patch Up! ❤️',
                    'interactive_no_text' => 'No',
                    'notification_msg' => '💔 Someone patched up with you!',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Let\'s Fix Things... 💔', 'subtitle' => 'A private message to mend our connection.', 'btn' => 'Let\'s Talk', 'animation' => 'typewriter'],
                        ['type' => 'patchup_rebuild_bridge', 'title' => 'Rebuild Our Bridge 🌉', 'subtitle' => 'Tap the boards to rebuild the path back to each other', 'animation' => 'fadeIn'],
                        ['type' => 'patchup_lock_key', 'title' => 'Unlock Our Hearts 🔐', 'subtitle' => 'Drag the key to unlock our happy relationship status', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'When Things Were Perfect', 'subtitle' => 'Our best moments together', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Honest Thoughts', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'universal_interactive', 'animation' => 'bounceIn'],
                    ])
                ],
                [
                    'name' => 'Miss You',
                    'slug' => 'missyou',
                    'icon' => '🥺',
                    'description' => 'A sweet starry sky collection game where users catch star reasons and read moon messages.',
                    'theme' => 'galaxy',
                    'default_title' => 'I Miss You So Much...',
                    'default_letter' => "Every time I look at the moon, I think of you. My days feel incomplete without your laughter and our little talks. I created this page just to show you how much I wish you were next to me right now.",
                    'default_question' => 'Do you miss me too? 🥺',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#4338ca',
                    'music_url' => '',
                    'status' => 'enabled',
                    'interactive_question' => 'Do you miss me too? 🥺',
                    'interactive_yes_text' => 'I Miss You Too! ❤️',
                    'interactive_no_text' => 'No',
                    'notification_msg' => '🥺 Someone misses you too!',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Thinking of You... 🌌', 'subtitle' => 'A message floating in the night sky is waiting for you.', 'btn' => 'Look at the Stars', 'animation' => 'typewriter'],
                        ['type' => 'miss_you_star_collection', 'title' => 'Catch My Missing Stars ⭐', 'subtitle' => 'Tap the falling stars to reveal why I miss you so much', 'animation' => 'fadeIn'],
                        ['type' => 'miss_you_moon_message', 'title' => 'Wishes Under the Moon 🌙', 'subtitle' => 'Scroll to read what I wish for every night', 'animation' => 'zoomIn'],
                        ['type' => 'gallery', 'title' => 'Times I Miss The Most', 'subtitle' => 'Pictures of us together', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Missing Letter', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'universal_interactive', 'animation' => 'bounceIn'],
                    ])
                ],
                [
                    'name' => 'Congratulations',
                    'slug' => 'congrats',
                    'icon' => '🎓',
                    'description' => 'Applaud academic, career, or life achievements with trophy reveals and clickable fireworks.',
                    'theme' => 'elegant',
                    'default_title' => 'Huge Congratulations to You!',
                    'default_letter' => "I am incredibly proud of your achievement! You have worked so hard to get here, and seeing your efforts pay off is absolutely amazing. You deserve all the success, happiness, and celebrations coming your way. Cheers to you!",
                    'default_question' => 'Time to celebrate! 🥂',
                    'font' => 'Outfit',
                    'accent_hex' => '#ea580c',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Congratulations! 🏆', 'subtitle' => 'A special award presentation is waiting for you.', 'btn' => 'Reveal Award', 'animation' => 'typewriter'],
                        ['type' => 'congrats_trophy_reveal', 'title' => 'You Did It! 🎓', 'subtitle' => 'Click the trophy box to unlock your achievement award', 'animation' => 'zoomIn'],
                        ['type' => 'congrats_fireworks', 'title' => 'Launch Celebration 🎆', 'subtitle' => 'Tap the screen to launch beautiful colorful fireworks!', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Your Path To Success', 'subtitle' => 'Pictures of your hard work and achievements', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'A Proud Message', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🎉 Huge Congrats! 🎉', 'subtitle' => 'So proud of you! Here is to the next big milestone!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Crush Confession',
                    'slug' => 'crush',
                    'icon' => '😍',
                    'description' => 'Confess your feelings with sliding text envelopes, wipe fog messages, and a heartbeat scale.',
                    'theme' => 'romantic',
                    'default_title' => 'I Have a Secret Confession...',
                    'default_letter' => "I have kept this to myself for a while, but I need to tell you. I have a huge crush on you. Your smile makes me happy, your vibe is amazing, and I get butterflies every time you are around. I created this just to tell you my secret.",
                    'default_question' => 'Will you go out on a coffee date with me? ☕',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#db2777',
                    'music_url' => '',
                    'status' => 'enabled',
                    'interactive_question' => 'Will you go out on a coffee date with me? ☕',
                    'interactive_yes_text' => 'I\'d Love To! 🥰',
                    'interactive_no_text' => 'Let\'s Be Friends',
                    'notification_msg' => '😍 Someone accepted your crush confession!',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'A Secret Message... 🤫', 'subtitle' => '[sender] has a confession they\'ve been hiding.', 'btn' => 'Unlock Confession', 'animation' => 'typewriter'],
                        ['type' => 'crush_secret_envelope', 'title' => 'Unlock the Envelope ✉️', 'subtitle' => 'Slide the seal to read the hidden confession', 'animation' => 'zoomIn'],
                        ['type' => 'crush_hidden_message', 'title' => 'Wipe the Screen Fog 🌫️', 'subtitle' => 'Wipe the fog away to see the hidden message', 'animation' => 'fadeIn'],
                        ['type' => 'crush_heartbeat', 'title' => 'My Heartbeat Scale 💓', 'subtitle' => 'Tap and hold the heart to check my heartbeat when you are near', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Pictures of You', 'subtitle' => 'Moments you shined the brightest', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Full Confession', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'universal_interactive', 'animation' => 'bounceIn'],
                    ])
                ],
                [
                    'name' => 'Surprise Reveal',
                    'slug' => 'surprise',
                    'icon' => '🎁',
                    'description' => 'Unwrap gifts, scratch coins, and watch countdown tickers to reveal surprises.',
                    'theme' => 'retro',
                    'default_title' => 'I Have a Big Surprise for You!',
                    'default_letter' => "Surprise! I have been planning this for a while and couldn't wait to show you. Scratch the card and open the box below to see what I got for you. I hope it makes your day!",
                    'default_question' => 'Did I surprise you? 🎁',
                    'font' => 'Outfit',
                    'accent_hex' => '#ea580c',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'A Surprise Awaits! 🎁', 'subtitle' => 'Someone has prepared a special surprise reveal for you.', 'btn' => 'Reveal Surprise', 'animation' => 'typewriter'],
                        ['type' => 'surprise_mystery_box', 'title' => 'Unwrap the Present 📦', 'subtitle' => 'Tap the box repeatedly to unwrap the surprise gift!', 'animation' => 'zoomIn'],
                        ['type' => 'surprise_scratch_card', 'title' => 'Scratch to Reveal 🪙', 'subtitle' => 'Scratch the metallic coin card to find the hidden gift note!', 'animation' => 'fadeIn'],
                        ['type' => 'surprise_countdown_reveal', 'title' => 'The Big Reveal Countdown ⏰', 'subtitle' => 'Hold on tight... the timer is counting down to the reveal!', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Surprise Snippets', 'subtitle' => 'A teaser of what is waiting for you', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Secret Message', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🎁 Surprise Revealed! 🎁', 'subtitle' => 'Hope you loved it! Have a wonderful day!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Girlfriend Birthday Special',
                    'slug' => 'girlfriend_birthday',
                    'icon' => '🎂',
                    'description' => 'Surprise your girlfriend with aesthetic balloon pops, blowing candles, cake cutting, and sweet messages.',
                    'theme' => 'romantic',
                    'default_title' => 'Happy Birthday My Princess 💖',
                    'default_letter' => "Happy birthday to the most beautiful, caring, and amazing girlfriend in the world. You are my dream come true. On your special day, I want to remind you of how much I love you and how grateful I am to have you in my life. Blow the candles and let's celebrate!",
                    'default_question' => 'Happy Birthday Baby! 🎂',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#db2777',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Happy Birthday My Love! 🎂', 'subtitle' => 'A private birthday surprise created by [sender] just for you.', 'btn' => 'Enter Surprise Room', 'animation' => 'typewriter'],
                        ['type' => 'birthday_pop_balloons', 'title' => 'Pop Balloons for Wishes 🎈', 'subtitle' => 'Pop these balloons to find special loving wishes!', 'animation' => 'bounceIn'],
                        ['type' => 'birthday_blow_candles', 'title' => 'Make a Big Wish 🎂', 'subtitle' => 'Make a wish, my princess, and click to blow the candles!', 'animation' => 'fadeIn'],
                        ['type' => 'birthday_cake_cutting', 'title' => 'Cut the Cake 🍰', 'subtitle' => 'Swipe to cut the cake I got for you!', 'animation' => 'zoomIn'],
                        ['type' => 'gallery', 'title' => 'Our Beautiful Moments 📸', 'subtitle' => 'Photos of us that I cherish forever', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Birthday Letter to You 💌', 'animation' => 'typewriter'],
                        ['type' => 'voice_message', 'title' => 'Hear My Voice Notes 🎤', 'subtitle' => 'I recorded a special message for you', 'animation' => 'fadeIn'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🎉 Happy Birthday Princess! 🎉', 'subtitle' => 'May all your dreams come true! I love you!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Marriage Proposal',
                    'slug' => 'marriage_proposal',
                    'icon' => '💍',
                    'description' => 'A premium proposal page with rose cinematics, memory constellations, heartbeat scales, and a ring reveal.',
                    'theme' => 'elegant',
                    'default_title' => 'Will You Marry Me?',
                    'default_letter' => "We have walked side by side, shared laughter and tears, and built a connection that feels absolute. I cannot picture my future without you in it. I want to build a life, a home, and a family with you. I want to grow old holding your hand. Will you do me the honor of marrying me?",
                    'default_question' => 'Will you marry me and make me the happiest person? 💍',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#db2777',
                    'music_url' => '',
                    'status' => 'enabled',
                    'interactive_question' => 'Will you marry me? 💍',
                    'interactive_yes_text' => 'Yes, A Million Times Yes! ❤️',
                    'interactive_no_text' => 'No',
                    'notification_msg' => '💍 Someone said YES to your marriage proposal!',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'A Lifetime Question... 💍', 'subtitle' => 'Please open this private message only if your heart is ready.', 'btn' => 'Open Proposal', 'animation' => 'typewriter'],
                        ['type' => 'proposal_rose_cinematic', 'title' => 'A Rose For My Future 🌹', 'subtitle' => 'Watch the flower of our love bloom...', 'animation' => 'zoomIn'],
                        ['type' => 'proposal_constellation', 'title' => 'Our Milestone Constellation ✨', 'subtitle' => 'Our connection is written in the stars', 'animation' => 'fadeIn'],
                        ['type' => 'proposal_heart_formation', 'title' => 'My Heart is Yours 💖', 'subtitle' => 'Every single piece belongs to you', 'animation' => 'fadeIn'],
                        ['type' => 'proposal_countdown', 'title' => 'Time Spent Loving You ⏰', 'subtitle' => 'Since the day our paths crossed', 'animation' => 'fadeIn'],
                        ['type' => 'proposal_ring_cinematic', 'title' => 'The Ring Box 💍', 'subtitle' => 'Click to open the ring box...', 'animation' => 'zoomIn'],
                        ['type' => 'gallery', 'title' => 'Our Lifelong Gallery 📸', 'subtitle' => 'A snapshot of our journey together', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Proposal Letter ✍️', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'universal_interactive', 'animation' => 'bounceIn'],
                    ])
                ],
                [
                    'name' => 'Relationship Journey',
                    'slug' => 'relationship_journey',
                    'icon' => '❤️',
                    'description' => 'Showcase your relationship statistics, milestone timeline, scrapbook, and counter.',
                    'theme' => 'romantic',
                    'default_title' => 'Our Beautiful Journey',
                    'default_letter' => "Look at how far we've come! From a simple conversation to this beautiful bond, every step of our journey has been worth it. I cherish all our silly moments, our deep talks, and the way we've grown together. Here is to our beautiful relationship journey!",
                    'default_question' => 'Ready for our next chapter? ❤️',
                    'font' => 'Outfit',
                    'accent_hex' => '#db2777',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Our Relationship Journey ❤️', 'subtitle' => 'Walk down the timeline of our love.', 'btn' => 'Walk the Journey', 'animation' => 'typewriter'],
                        ['type' => 'anniversary_together_counter', 'title' => 'Days of Togetherness ⏰', 'subtitle' => 'Counting every happy second together', 'animation' => 'zoomIn'],
                        ['type' => 'anniversary_timeline_unlock', 'title' => 'Our Journey Milestones 📍', 'subtitle' => 'Key milestones we reached together', 'animation' => 'slideUp'],
                        ['type' => 'anniversary_memory_book', 'title' => 'Memory Flip Journal 📖', 'subtitle' => 'Flipping through our favorite diary pages', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Captured Love 📸', 'subtitle' => 'Our favorite photos as a couple', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Message to You', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '❤️ Forever Together ❤️', 'subtitle' => 'Every day with you is a new adventure. I love you!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Couple Story',
                    'slug' => 'couple_story',
                    'icon' => '👩‍❤️‍👨',
                    'description' => 'A sweet scrapbook representation of a couple\'s story, with flip-cards highlights and stamp awards.',
                    'theme' => 'elegant',
                    'default_title' => 'The Story of Us',
                    'default_letter' => "Every couple has a story, but ours is my absolute favorite. It is filled with inside jokes, comforting hugs, small struggles we overcame, and a love that grew stronger day by day. I created this storybook just to celebrate 'Us'.",
                    'default_question' => 'I love our story 👩‍❤️‍👨',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#db2777',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'The Story of Us 👩‍❤️‍👨', 'subtitle' => 'A virtual scrapbook of our couple story.', 'btn' => 'Open Storybook', 'animation' => 'typewriter'],
                        ['type' => 'love_letter_scrapbook', 'title' => 'Couple Scrapbook 📖', 'subtitle' => 'Turning the pages of our shared life', 'animation' => 'fadeIn'],
                        ['type' => 'friendship_moment_cards', 'title' => 'Highlights of Us 🃏', 'subtitle' => 'Tap these cards to see key aspects of our bond', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Our Snapshot Vault 📸', 'subtitle' => 'Frozen frames of our happiest days', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Letter For Us 💌', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '👩‍❤️‍👨 Forever & Always 👩‍❤️‍👨', 'subtitle' => 'Our love story continues to unfold.', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Festival Wishes',
                    'slug' => 'festival_wishes',
                    'icon' => '🎊',
                    'description' => 'Send beautiful festive greetings with celebration music, personalized letters, and interactive fireworks.',
                    'theme' => 'retro',
                    'default_title' => 'Warm Festive Wishes for You!',
                    'default_letter' => "May this festive season bring you and your family abundance, happiness, and good health. I created this special page to send you my warmest blessings. May your home be filled with light and laughter!",
                    'default_question' => 'Happy Festive Season! 🎊',
                    'font' => 'Outfit',
                    'accent_hex' => '#d97706',
                    'music_url' => '',
                    'status' => 'enabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Festive Greetings! 🎊', 'subtitle' => '[sender] has sent you a special blessing card.', 'btn' => 'Open Blessings', 'animation' => 'typewriter'],
                        ['type' => 'congrats_fireworks', 'title' => 'Festive Sparklers 🎆', 'subtitle' => 'Tap the screen to launch celebratory crackers!', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Celebration Gallery 📸', 'subtitle' => 'Festive moments and decorations', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Festive Message 💌', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🌟 Happy Celebrations! 🌟', 'subtitle' => 'Wishing you peace and prosperity!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Wedding Wishes',
                    'slug' => 'wedding',
                    'icon' => '💍',
                    'description' => 'Congratulate a newlywed couple with custom registries, story details, and wedding background music.',
                    'theme' => 'elegant',
                    'default_title' => 'Happy Wedding Day! 💒',
                    'default_letter' => "Wishing you a lifetime of love, joy, and companionship. Today is the start of a beautiful adventure. May your bond grow stronger with each passing year, and may your home always be filled with peace and warmth.",
                    'default_question' => 'Happy Married Life! 💒',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#d97706',
                    'music_url' => '',
                    'status' => 'hidden',
                    'slides' => [
                        ['type' => 'welcome', 'title' => 'Dear [receiver] 💒', 'subtitle' => '[sender] has a special wedding message for you.', 'btn' => 'Open Wishes', 'animation' => 'typewriter'],
                        ['type' => 'gallery', 'title' => 'Couple Gallery 📸', 'subtitle' => 'Beautiful moments of the lovely couple', 'animation' => 'slideshow'],
                        ['type' => 'text_story', 'key' => 'best_memories', 'title' => 'Best Memories Together', 'subtitle' => 'Moments that brought you here', 'default' => "From the day you first met to this beautiful moment, every step of your journey has been leading to this. Your love story inspires everyone around you.", 'show_photo' => true, 'animation' => 'fadeIn'],
                        ['type' => 'letter', 'title' => 'Wedding Message 💌', 'animation' => 'typewriter'],
                        ['type' => 'video_message', 'title' => 'Video Wishes 🎬', 'subtitle' => 'A special video message for the couple', 'animation' => 'fadeIn'],
                        ['type' => 'celebration', 'title' => '💒 Happy Wedding! 💒', 'subtitle' => 'May your love last forever!', 'animation' => 'confetti'],
                    ]
                ],
                [
                    'name' => 'Long Distance Love',
                    'slug' => 'long_distance',
                    'icon' => '🌍',
                    'description' => 'Bridge the distance with a heartfelt page about missing each other, meeting counters, and future plans.',
                    'theme' => 'galaxy',
                    'default_title' => 'Across The Distance',
                    'default_letter' => "The miles between us mean nothing because what we have is stronger than any distance. Every day apart is a day closer to being together again. I carry you in my heart wherever I go, and no amount of distance can change that.",
                    'default_question' => 'Together soon? 🌍',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#7c3aed',
                    'music_url' => '',
                    'status' => 'hidden',
                    'slides' => [
                        ['type' => 'welcome', 'title' => 'Hey [receiver] 🌍', 'subtitle' => 'Even miles apart, someone is thinking about you right now.', 'btn' => 'Read Their Heart', 'animation' => 'typewriter'],
                        ['type' => 'text_story', 'key' => 'map_distance', 'title' => 'The Distance Between Us 📍', 'subtitle' => 'But our hearts are closer than ever', 'default' => "There are thousands of miles between us, different time zones, different skies. But every night when I look at the stars, I know we are looking at the same moon. That makes the distance feel a little smaller.", 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Our Memories Together 📸', 'subtitle' => 'Pictures that bridge the distance', 'animation' => 'slideshow'],
                        ['type' => 'voice_message', 'title' => 'Voice Notes 🎤', 'subtitle' => 'Hear my voice from across the miles', 'animation' => 'fadeIn'],
                        ['type' => 'counter', 'title' => 'Countdown To Meeting ⏰', 'subtitle' => 'Every day brings us closer', 'animation' => 'zoomIn'],
                        ['type' => 'text_story', 'key' => 'future_plans', 'title' => 'Future Plans 🌟', 'subtitle' => 'What awaits us when we reunite', 'default' => "When we finally meet again, I am never letting go. We have so many plans — places to visit, food to try, memories to create. The wait will be worth every second.", 'animation' => 'fadeIn'],
                        ['type' => 'reactions_finale', 'title' => 'I Love You Across The Miles 🌍', 'subtitle' => 'Distance means nothing when someone means everything.', 'emoji' => 'academic', 'animation' => 'heartbeat'],
                    ]
                ],
                [
                    'name' => 'Parents Appreciation',
                    'slug' => 'parents',
                    'icon' => '🙏',
                    'description' => 'Thank your parents with heartfelt words, memories, and a voice message.',
                    'theme' => 'rose_garden',
                    'default_title' => 'Thank You Mom & Dad',
                    'default_letter' => "Dear Mom and Dad, I don't say this enough, but thank you. Thank you for the sacrifices you made, the love you gave unconditionally, the lessons you taught, and the person you raised me to be. I am who I am because of you, and I am grateful every single day.",
                    'default_question' => 'I love you Mom & Dad 🙏',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#059669',
                    'music_url' => '',
                    'status' => 'hidden',
                    'slides' => [
                        ['type' => 'welcome', 'title' => 'Dear Mom & Dad 🙏', 'subtitle' => '[sender] wants to tell you something from the heart.', 'btn' => 'Read Their Heart', 'animation' => 'typewriter'],
                        ['type' => 'text_story', 'key' => 'childhood_memories', 'title' => 'Childhood Memories 👶', 'subtitle' => 'Where your love shaped who I am', 'default' => "From my first steps to my first day of school, you were there for everything. Your patience, your love, your unwavering support — those are the foundation of who I am today.", 'show_photo' => true, 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Photo Gallery 📸', 'subtitle' => 'Memories with the best parents', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'A Letter For You 💌', 'animation' => 'typewriter'],
                        ['type' => 'voice_message', 'title' => 'Voice Message 🎤', 'subtitle' => 'Hear what I want to say', 'animation' => 'fadeIn'],
                        ['type' => 'celebration', 'title' => '🙏 Thank You Mom & Dad 🙏', 'subtitle' => 'I love you more than words can say!', 'animation' => 'confetti'],
                    ]
                ],
                // Seasonal Categories (Disabled/hidden by default)
                [
                    'name' => 'Valentine Special',
                    'slug' => 'valentine_special',
                    'icon' => '💝',
                    'description' => 'Celebrate Valentine\'s Day with dynamic hearts, envelope reveals, and letters of love.',
                    'theme' => 'romantic',
                    'default_title' => 'My Happy Valentine Wish For You 💝',
                    'default_letter' => "Happy Valentine's Day! You make my heart skip a beat and fill my days with warm sunshine. Having you as my Valentine is the greatest gift. I created this special page just to say: I love you, and you mean the world to me.",
                    'default_question' => 'Will you be my Valentine forever? 💝',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#e11d48',
                    'music_url' => '',
                    'status' => 'disabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Happy Valentine\'s Day! 💝', 'subtitle' => 'A special Valentine surprise is waiting inside.', 'btn' => 'Open Valentine', 'animation' => 'typewriter'],
                        ['type' => 'proposal_heart_formation', 'title' => 'My Heart Beats for You 💖', 'subtitle' => 'Watch the particles form my feelings', 'animation' => 'fadeIn'],
                        ['type' => 'love_letter_envelope_open', 'title' => 'A Valentine Envelope 💌', 'subtitle' => 'Break the wax seal to read...', 'animation' => 'zoomIn'],
                        ['type' => 'gallery', 'title' => 'Our Sweet Moments 📸', 'subtitle' => 'Happy memories of us', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'A Valentine Confession ✍️', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '💝 Happy Valentine\'s Day! 💝', 'subtitle' => 'I love you to the moon and back!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'New Year Wishes',
                    'slug' => 'new_year_wishes',
                    'icon' => '✨',
                    'description' => 'Send dynamic New Year wishes with interactive sparklers and customizable fireworks.',
                    'theme' => 'galaxy',
                    'default_title' => 'Happy New Year! ✨',
                    'default_letter' => "Wishing you a spectacular New Year filled with amazing opportunities, joy, prosperity, and success. May this year bring you closer to all your dreams and fill your heart with laughter!",
                    'default_question' => 'Ready for a new chapter? 🥂',
                    'font' => 'Outfit',
                    'accent_hex' => '#7c3aed',
                    'music_url' => '',
                    'status' => 'disabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Happy New Year! ✨', 'subtitle' => 'Someone has sent you a magical New Year greeting.', 'btn' => 'Launch Year', 'animation' => 'typewriter'],
                        ['type' => 'congrats_fireworks', 'title' => 'New Year Sparklers 🎆', 'subtitle' => 'Tap to burst beautiful fireworks!', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Memories of the Year 📸', 'subtitle' => 'Looking back at our happy moments', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My New Year Note 💌', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '✨ Happy New Year! ✨', 'subtitle' => 'May this year be your best one yet! Cheers!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Christmas Wishes',
                    'slug' => 'christmas_wishes',
                    'icon' => '🎄',
                    'description' => 'Unwrap Christmas presents and read holiday greetings with festive winter themes.',
                    'theme' => 'retro',
                    'default_title' => 'Merry Christmas! 🎄',
                    'default_letter' => "Wishing you a warm, peaceful, and Merry Christmas. May your holiday season be filled with love, laughter, cozy moments, and beautiful surprises under the tree. Merry Christmas to you and your loved ones!",
                    'default_question' => 'Merry Christmas! 🎁',
                    'font' => 'Outfit',
                    'accent_hex' => '#ea580c',
                    'music_url' => '',
                    'status' => 'disabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Merry Christmas! 🎄', 'subtitle' => 'A special holiday present is waiting for you.', 'btn' => 'Unwrap Present', 'animation' => 'typewriter'],
                        ['type' => 'surprise_mystery_box', 'title' => 'Open Your Gift Box 🎁', 'subtitle' => 'Tap the gift box repeatedly to reveal the holiday surprise!', 'animation' => 'zoomIn'],
                        ['type' => 'gallery', 'title' => 'Winter Memories 📸', 'subtitle' => 'Cozy moments spent together', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Christmas Message 💌', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🎄 Merry Christmas! 🎄', 'subtitle' => 'Wishing you peace, joy, and a happy holiday season!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Diwali Wishes',
                    'slug' => 'diwali_wishes',
                    'icon' => '🪔',
                    'description' => 'Brighten Diwali with sparkling diyas, interactive crackers, and warm light themes.',
                    'theme' => 'retro',
                    'default_title' => 'Happy Diwali! 🪔',
                    'default_letter' => "Wishing you and your family a very Happy, Safe, and Prosperous Diwali. May the divine light of Diwali diyas guide you towards success, good health, and peace. Have a wonderful sparkling festival!",
                    'default_question' => 'Happy Diwali! 🪔',
                    'font' => 'Outfit',
                    'accent_hex' => '#ea580c',
                    'music_url' => '',
                    'status' => 'disabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Shubh Deepavali! 🪔', 'subtitle' => 'A glowing festival greeting is waiting for you.', 'btn' => 'Light Diwali Diyas', 'animation' => 'typewriter'],
                        ['type' => 'congrats_fireworks', 'title' => 'Diwali Crackers & Sparklers 🎆', 'subtitle' => 'Tap the screen to light up the festive sky!', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Festive Snapshots 📸', 'subtitle' => 'Happy celebrations and sweets', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Diwali Blessings 💌', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🪔 Happy Diwali! 🪔', 'subtitle' => 'May your life be filled with brightness and joy!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Eid Wishes',
                    'slug' => 'eid_wishes',
                    'icon' => '🌙',
                    'description' => 'Send beautiful Eid Mubarak greetings with crescent moons and interactive festive sparklers.',
                    'theme' => 'galaxy',
                    'default_title' => 'Eid Mubarak! 🌙',
                    'default_letter' => "Eid Mubarak to you and your family! May the blessings of Allah fill your life with happiness, peace, prosperity, and success. Wishing you a wonderful day of celebrations, good food, and love!",
                    'default_question' => 'Eid Mubarak! 🌙',
                    'font' => 'Playfair Display',
                    'accent_hex' => '#059669',
                    'music_url' => '',
                    'status' => 'disabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Eid Mubarak! 🌙', 'subtitle' => '[sender] has sent you a special Eid greeting.', 'btn' => 'Open Greeting', 'animation' => 'typewriter'],
                        ['type' => 'congrats_fireworks', 'title' => 'Eid Celebrations 🎆', 'subtitle' => 'Tap the sky to launch sparkling lights!', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Eid Joy Moments 📸', 'subtitle' => 'Beautiful moments of gathering and sharing', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'My Eid Message 💌', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🌙 Eid Mubarak! 🌙', 'subtitle' => 'Wishing you peace, happiness, and countless blessings!', 'animation' => 'confetti'],
                    ])
                ],
                [
                    'name' => 'Raksha Bandhan Wishes',
                    'slug' => 'rakshabandhan_wishes',
                    'icon' => '🤝',
                    'description' => 'Celebrate sibling love with customized memory cards, letters, and celebration animations.',
                    'theme' => 'retro',
                    'default_title' => 'Happy Raksha Bandhan!',
                    'default_letter' => "Happy Raksha Bandhan! Through all our fights, shared secrets, and endless teasings, our bond has remained the strongest. Thank you for being the best sibling I could ask for. I am always here for you, no matter what.",
                    'default_question' => 'Happy Rakshi! 🤝',
                    'font' => 'Outfit',
                    'accent_hex' => '#06b6d4',
                    'music_url' => '',
                    'status' => 'disabled',
                    'slides' => array_merge([
                        ['type' => 'welcome', 'title' => 'Happy Raksha Bandhan! 🤝', 'subtitle' => 'A sweet memory card from your sibling is waiting.', 'btn' => 'Read Message', 'animation' => 'typewriter'],
                        ['type' => 'friendship_moment_cards', 'title' => 'Sibling Fights & Love 🃏', 'subtitle' => 'Tap cards to flip and read why you are so annoying yet loved!', 'animation' => 'fadeIn'],
                        ['type' => 'gallery', 'title' => 'Our Sibling Album 📸', 'subtitle' => 'Embarrassing childhood photos of us', 'animation' => 'slideshow'],
                        ['type' => 'letter', 'title' => 'A Heartfelt Note', 'animation' => 'typewriter'],
                    ], $premium, [
                        ['type' => 'celebration', 'title' => '🤝 Happy Raksha Bandhan! 🤝', 'subtitle' => 'Our bond is forever! I love you!', 'animation' => 'confetti'],
                    ])
                ]
            ];

            // Migration / Sync check: Execute only once via site setting flag to ensure high performance
            $synced = false;
            try {
                $val = $pdo->query("SELECT `setting_value` FROM `site_settings` WHERE `setting_key` = 'categories_synced_v8'")->fetchColumn();
                if ($val === '1') {
                    $synced = true;
                }
            } catch (PDOException $e) {}

            // --- Categories table: add interactive columns migration ---
            $cat_interactive_migrations = [
                'interactive_question' => "ALTER TABLE `categories` ADD COLUMN `interactive_question` VARCHAR(255) NULL AFTER `slides`",
                'interactive_yes_text' => "ALTER TABLE `categories` ADD COLUMN `interactive_yes_text` VARCHAR(100) NULL AFTER `interactive_question`",
                'interactive_no_text'  => "ALTER TABLE `categories` ADD COLUMN `interactive_no_text` VARCHAR(100) NULL AFTER `interactive_yes_text`",
                'notification_msg'     => "ALTER TABLE `categories` ADD COLUMN `notification_msg` VARCHAR(255) NULL AFTER `interactive_no_text`",
            ];
            foreach ($cat_interactive_migrations as $col_name => $alter_sql) {
                try {
                    $pdo->query("SELECT `$col_name` FROM `categories` LIMIT 1");
                } catch (PDOException $e) {
                    try { $pdo->exec($alter_sql); } catch (PDOException $ex) {}
                }
            }

            // Enforce seeding if categories table is empty
            $has_categories = false;
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();
                if ($count > 0) {
                    $has_categories = true;
                }
            } catch (PDOException $e) {}

            if (!$synced || !$has_categories) {
                // Fetch existing categories to see what we have
                $existing_slugs = $pdo->query("SELECT `slug` FROM `categories`")->fetchAll(PDO::FETCH_COLUMN);

                $order = 1;
                foreach ($default_categories as $c) {
                    $slides_json = json_encode($c['slides']);
                    if (!in_array($c['slug'], $existing_slugs)) {
                        // Insert new category
                        $ins = $pdo->prepare("INSERT INTO `categories` 
                            (`name`, `slug`, `icon`, `description`, `status`, `featured`, `display_order`, `theme`, `default_title`, `default_letter`, `default_question`, `font`, `accent_hex`, `music_url`, `slides`, `interactive_question`, `interactive_yes_text`, `interactive_no_text`, `notification_msg`) 
                            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([
                            $c['name'],
                            $c['slug'],
                            $c['icon'],
                            $c['description'],
                            $c['status'],
                            $order,
                            $c['theme'],
                            $c['default_title'],
                            $c['default_letter'],
                            $c['default_question'],
                            $c['font'],
                            $c['accent_hex'],
                            $c['music_url'],
                            $slides_json,
                            $c['interactive_question'] ?? $c['default_question'] ?? '',
                            $c['interactive_yes_text'] ?? 'Yes! ❤️',
                            $c['interactive_no_text'] ?? 'No',
                            $c['notification_msg'] ?? '',
                        ]);
                    } else {
                        // Update existing category defaults to match new emojis/names
                        $upd = $pdo->prepare("UPDATE `categories` SET 
                            `name` = ?, 
                            `icon` = ?, 
                            `description` = ?, 
                            `theme` = ?, 
                            `default_title` = ?, 
                            `default_letter` = ?, 
                            `default_question` = ?, 
                            `font` = ?, 
                            `accent_hex` = ?, 
                            `slides` = ?,
                            `interactive_question` = COALESCE(`interactive_question`, ?),
                            `interactive_yes_text` = COALESCE(`interactive_yes_text`, ?),
                            `interactive_no_text` = COALESCE(`interactive_no_text`, ?),
                            `notification_msg` = COALESCE(`notification_msg`, ?)
                            WHERE `slug` = ?");
                        $upd->execute([
                            $c['name'],
                            $c['icon'],
                            $c['description'],
                            $c['theme'],
                            $c['default_title'],
                            $c['default_letter'],
                            $c['default_question'],
                            $c['font'],
                            $c['accent_hex'],
                            $slides_json,
                            $c['interactive_question'] ?? $c['default_question'] ?? '',
                            $c['interactive_yes_text'] ?? 'Yes! ❤️',
                            $c['interactive_no_text'] ?? 'No',
                            $c['notification_msg'] ?? '',
                            $c['slug']
                        ]);
                    }
                    $order++;
                }

                // Update flag in site settings
                $pdo->exec("INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('categories_synced_v8', '1') ON DUPLICATE KEY UPDATE `setting_value` = '1'");
            }
        } catch (PDOException $e) {}
    }
    // -------------------------------------------
    
} catch (PDOException $e) {
    // If the database setup fails, show a clean message for cPanel deployment guide
    die("<div style='font-family: sans-serif; padding: 20px; max-width: 500px; margin: 50px auto; border: 1px solid #fee2e2; background: #fef2f2; border-radius: 8px;'>
        <h3 style='color: #dc2626; margin-top: 0;'>Database Connection Failed</h3>
        <p style='color: #4b5563; font-size: 14px; line-height: 1.5;'>Please make sure you have created a MySQL database in cPanel and updated database credentials in <code>includes/db.php</code>.</p>
        <p style='font-size: 12px; color: #9ca3af;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>
    </div>");
}
