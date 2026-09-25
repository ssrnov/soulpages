<?php
// =========================================================================
// SoulSync Database Connection + Auto Schema Installer
// Separate database from the soulpages website.
// Imports database/schema.sql automatically on first run.
// =========================================================================

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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    // Use IST for NOW()/CURDATE() so "today" flips at local midnight (not UTC).
    // Fixes the admin date-picker not letting you pick the new date at night.
    try { $pdo->exec("SET time_zone = '+05:30'"); } catch (\Throwable $e) {}
    if (!date_default_timezone_get() || date_default_timezone_get() === 'UTC') @date_default_timezone_set('Asia/Kolkata');

    // --- AUTOMATIC SCHEMA INSTALLER ---------------------------------------
    // If the `users` table is missing, import database/schema.sql once.
    $initialized = true;
    try {
        $pdo->query("SELECT 1 FROM `users` LIMIT 1");
    } catch (PDOException $e) {
        $initialized = false;
    }

    if (!$initialized) {
        $sql_file = __DIR__ . '/../database/schema.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $sql = preg_replace('!/\*.*?\*/!s', '', $sql);   // block comments
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);    // -- comments
            $sql = preg_replace('/^\s*#.*$/m', '', $sql);     // # comments
            foreach (explode(';', $sql) as $q) {
                $q = trim($q);
                if ($q !== '') {
                    try { $pdo->exec($q); } catch (PDOException $ex) { /* ignore */ }
                }
            }
        }
    } else {
        // --- Lightweight column migrations for existing deployments ---
        $col_migrations = [
            'quick_note' => "ALTER TABLE `users` ADD COLUMN `quick_note` VARCHAR(500) NULL AFTER `mood_updated_at`",
            'fcm_token'  => "ALTER TABLE `users` ADD COLUMN `fcm_token` VARCHAR(300) NULL AFTER `quick_note`",
            'presence_at'=> "ALTER TABLE `users` ADD COLUMN `presence_at` DATETIME NULL AFTER `last_seen`",
            'disguise'   => "ALTER TABLE `users` ADD COLUMN `disguise` TINYINT(1) DEFAULT 0 AFTER `presence_at`",
            'disguise_type' => "ALTER TABLE `users` ADD COLUMN `disguise_type` VARCHAR(16) DEFAULT 'calculator' AFTER `disguise`",
            'is_premium' => "ALTER TABLE `users` ADD COLUMN `is_premium` TINYINT(1) DEFAULT 0 AFTER `disguise`",
            'premium_until' => "ALTER TABLE `users` ADD COLUMN `premium_until` DATETIME NULL AFTER `is_premium`",
            'phone'      => "ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(30) NULL",
            'birthday'   => "ALTER TABLE `users` ADD COLUMN `birthday` VARCHAR(20) NULL",
            'avatar'     => "ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(300) NULL",
            // Columns an imported/older DB may be missing (no AFTER clause, so they
            // never depend on another column existing first).
            'is_online'  => "ALTER TABLE `users` ADD COLUMN `is_online` TINYINT(1) NOT NULL DEFAULT 0",
            'last_seen'  => "ALTER TABLE `users` ADD COLUMN `last_seen` DATETIME NULL",
            'is_paired'  => "ALTER TABLE `users` ADD COLUMN `is_paired` TINYINT(1) NOT NULL DEFAULT 0",
            'couple_id'  => "ALTER TABLE `users` ADD COLUMN `couple_id` INT NULL",
            'invite_code'=> "ALTER TABLE `users` ADD COLUMN `invite_code` VARCHAR(20) NULL",
            'mood'       => "ALTER TABLE `users` ADD COLUMN `mood` VARCHAR(30) NULL",
            'mood_updated_at' => "ALTER TABLE `users` ADD COLUMN `mood_updated_at` DATETIME NULL",
            'verified'   => "ALTER TABLE `users` ADD COLUMN `verified` TINYINT(1) NOT NULL DEFAULT 0",
            'gender'     => "ALTER TABLE `users` ADD COLUMN `gender` VARCHAR(16) NULL",
            'bio'        => "ALTER TABLE `users` ADD COLUMN `bio` VARCHAR(500) NULL",
            'relationship_since' => "ALTER TABLE `users` ADD COLUMN `relationship_since` VARCHAR(20) NULL",
            'hide_online'    => "ALTER TABLE `users` ADD COLUMN `hide_online` TINYINT(1) NOT NULL DEFAULT 0",
            'hide_last_seen' => "ALTER TABLE `users` ADD COLUMN `hide_last_seen` TINYINT(1) NOT NULL DEFAULT 0",
            'role'       => "ALTER TABLE `users` ADD COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'user'",
        ];
        foreach ($col_migrations as $col => $alter) {
            try { $pdo->query("SELECT `$col` FROM `users` LIMIT 1"); }
            catch (PDOException $e) { try { $pdo->exec($alter); } catch (PDOException $ex) {} }
        }

        // --- device_status extra columns (current app, storage, screen) ---
        $dev_cols = [
            'current_app'      => "ALTER TABLE `device_status` ADD COLUMN `current_app` VARCHAR(150) NULL",
            'current_activity' => "ALTER TABLE `device_status` ADD COLUMN `current_activity` VARCHAR(40) NULL",
            'on_call'          => "ALTER TABLE `device_status` ADD COLUMN `on_call` TINYINT(1) NOT NULL DEFAULT 0",
            'call_started_at'  => "ALTER TABLE `device_status` ADD COLUMN `call_started_at` DATETIME NULL",
            'storage_used'     => "ALTER TABLE `device_status` ADD COLUMN `storage_used` VARCHAR(30) NULL",
            'storage_total'    => "ALTER TABLE `device_status` ADD COLUMN `storage_total` VARCHAR(30) NULL",
            'signal'           => "ALTER TABLE `device_status` ADD COLUMN `signal` VARCHAR(30) NULL",
            'current_app_source' => "ALTER TABLE `device_status` ADD COLUMN `current_app_source` VARCHAR(20) NULL",
            'network_type'       => "ALTER TABLE `device_status` ADD COLUMN `network_type` VARCHAR(20) NULL",
            'current_app_class'  => "ALTER TABLE `device_status` ADD COLUMN `current_app_class` VARCHAR(200) NULL",
            'call_contact'       => "ALTER TABLE `device_status` ADD COLUMN `call_contact` VARCHAR(120) NULL",
            'call_type_ds'       => "ALTER TABLE `device_status` ADD COLUMN `call_type` VARCHAR(20) NULL",
            'call_type_cs'       => "ALTER TABLE `call_sessions` ADD COLUMN `call_type` VARCHAR(20) NULL AFTER `label`",
            'current_wifi'       => "ALTER TABLE `device_status` ADD COLUMN `current_wifi` VARCHAR(120) NULL",
            'home_wifi'          => "ALTER TABLE `device_status` ADD COLUMN `home_wifi` VARCHAR(120) NULL",
            'is_home'            => "ALTER TABLE `device_status` ADD COLUMN `is_home` TINYINT(1) NOT NULL DEFAULT 0",
            'sim_count'          => "ALTER TABLE `device_status` ADD COLUMN `sim_count` TINYINT NULL",
            'sim_info'           => "ALTER TABLE `device_status` ADD COLUMN `sim_info` TEXT NULL",
            'ringer_mode'        => "ALTER TABLE `device_status` ADD COLUMN `ringer_mode` VARCHAR(10) NULL",
            'media_vol'          => "ALTER TABLE `device_status` ADD COLUMN `media_vol` TINYINT NULL",
            'brightness'         => "ALTER TABLE `device_status` ADD COLUMN `brightness` SMALLINT NULL",
            'unlock_count'       => "ALTER TABLE `device_status` ADD COLUMN `unlock_count` INT NOT NULL DEFAULT 0",
            'headphone'          => "ALTER TABLE `device_status` ADD COLUMN `headphone` TINYINT(1) NOT NULL DEFAULT 0",
            'vpn_active'         => "ALTER TABLE `device_status` ADD COLUMN `vpn_active` TINYINT(1) NOT NULL DEFAULT 0",
            'usb_connected'      => "ALTER TABLE `device_status` ADD COLUMN `usb_connected` TINYINT(1) NOT NULL DEFAULT 0",
            'casting'            => "ALTER TABLE `device_status` ADD COLUMN `casting` TINYINT(1) NOT NULL DEFAULT 0",
            'network_app'        => "ALTER TABLE `device_status` ADD COLUMN `network_app` VARCHAR(255) DEFAULT NULL",
        ];
        foreach ($dev_cols as $col => $alter) {
            try { $pdo->query("SELECT `$col` FROM `device_status` LIMIT 1"); }
            catch (PDOException $e) { try { $pdo->exec($alter); } catch (PDOException $ex) {} }
        }

        // --- notification_events body (the actual notification text) ---
        try { $pdo->query("SELECT `body` FROM `notification_events` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `notification_events` ADD COLUMN `body` VARCHAR(500) NULL AFTER `title`"); } catch (PDOException $ex) {} }

        // --- usage_events open_count ---
        try { $pdo->query("SELECT `open_count` FROM `usage_events` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `usage_events` ADD COLUMN `open_count` INT DEFAULT 0 AFTER `foreground_ms`"); } catch (PDOException $ex) {} }

        // --- usage_hourly: 24-hour activity buckets per user per day ---
        try { $pdo->query("SELECT 1 FROM `usage_hourly` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `usage_hourly` (
            `user_id` INT NOT NULL, `day` DATE NOT NULL, `hour` TINYINT NOT NULL, `minutes` INT DEFAULT 0,
            PRIMARY KEY (`user_id`,`day`,`hour`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- users.notif_enabled: Push Notifications toggle (Profile). ---
        try { $pdo->query("SELECT `notif_enabled` FROM `users` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `notif_enabled` TINYINT NOT NULL DEFAULT 1"); } catch (PDOException $ex) {} }

        // --- users.gender: collected at signup (male / female / other). ---
        try { $pdo->query("SELECT `gender` FROM `users` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `gender` VARCHAR(10) NULL"); } catch (PDOException $ex) {} }

        // --- users.hide_online / hide_last_seen: Privacy toggles. ---
        try { $pdo->query("SELECT `hide_online` FROM `users` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `hide_online` TINYINT NOT NULL DEFAULT 0"); } catch (PDOException $ex) {} }
        try { $pdo->query("SELECT `hide_last_seen` FROM `users` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `hide_last_seen` TINYINT NOT NULL DEFAULT 0"); } catch (PDOException $ex) {} }

        // --- users.is_premium / premium_until: admin can grant/revoke Premium. ---
        try { $pdo->query("SELECT `is_premium` FROM `users` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_premium` TINYINT NOT NULL DEFAULT 0"); } catch (PDOException $ex) {} }
        try { $pdo->query("SELECT `premium_until` FROM `users` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `premium_until` DATETIME NULL"); } catch (PDOException $ex) {} }

        // --- couples.ended_at: when a couple disconnected (for 10-day retention) ---
        try { $pdo->query("SELECT `ended_at` FROM `couples` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `couples` ADD COLUMN `ended_at` DATETIME NULL"); } catch (PDOException $ex) {} }

        // --- couple_requests: connect requests need the other side's acceptance.
        // One row per (from_id -> to_id) pair. reject_count only grows on reject;
        // once it hits 10 the sender can't send again until they accept & later
        // disconnect (which resets the counter). ---
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS couple_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                from_id INT NOT NULL,
                to_id INT NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                reject_count INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_pair (from_id, to_id),
                KEY idx_to (to_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {}

        // --- Couple Quiz tables ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_bank (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(30) NOT NULL DEFAULT 'random',
                difficulty VARCHAR(10) NOT NULL DEFAULT 'easy',
                type VARCHAR(20) NOT NULL DEFAULT 'choice',
                question VARCHAR(255) NOT NULL,
                options TEXT NULL,
                is_custom TINYINT NOT NULL DEFAULT 0,
                couple_id INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cat (category, difficulty)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                couple_id INT NOT NULL,
                category VARCHAR(30) NOT NULL DEFAULT 'random',
                difficulty VARCHAR(10) NOT NULL DEFAULT 'easy',
                q_ids TEXT NOT NULL,
                current_q INT NOT NULL DEFAULT 0,
                status VARCHAR(12) NOT NULL DEFAULT 'active',
                created_by INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_couple (couple_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_answers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                q_index INT NOT NULL,
                user_id INT NOT NULL,
                answer VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_ans (session_id, q_index, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // "Ask each other" turn-based mode columns.
            foreach ([
                "mode VARCHAR(10) NOT NULL DEFAULT 'seeded'",
                "phase VARCHAR(16) NOT NULL DEFAULT 'active'",
                "asker_id INT NULL",
                "round TEXT NULL",
                "skips_used INT NOT NULL DEFAULT 0",
                "started_first INT NULL",
                "game VARCHAR(20) NOT NULL DEFAULT 'couple_quiz'",
            ] as $col) {
                $name = explode(' ', $col)[0];
                try { $pdo->query("SELECT `$name` FROM quiz_sessions LIMIT 1"); }
                catch (\Throwable $e) { try { $pdo->exec("ALTER TABLE quiz_sessions ADD COLUMN $col"); } catch (\Throwable $ex) {} }
            }
            // Seed the BASE couple-quiz bank. Check base categories specifically —
            // NOT the whole bank — otherwise the themed banks (would_rather/never/
            // truth_dare/emoji/puzzle) make this count > 0 and the base couple-quiz
            // questions never seed, so Couple Quiz's "ready question" comes up empty.
            $have = (int)$pdo->query("SELECT COUNT(*) FROM quiz_bank WHERE is_custom=0
                     AND category NOT IN ('would_rather','never','truth_dare','emoji','puzzle')")->fetchColumn();
            if ($have === 0) {
                $seed = [
                    ['love','easy','choice',"What's my favorite way to spend a date?",["Movie night","Dinner out","Long walk","Stay home & cuddle"]],
                    ['love','easy','yesno',"Do I believe in love at first sight?",["YES","NO"]],
                    ['love','medium','choice',"What matters most to me in us?",["Trust","Fun","Communication","Romance"]],
                    ['romantic','easy','emoji',"Describe me in one emoji ❤️",["😊","😎","🥰","🤪"]],
                    ['romantic','easy','choice',"My ideal romantic gift?",["Flowers","A letter","A trip","Chocolate"]],
                    ['food','easy','choice',"My favorite food?",["Pizza","Burger","Biryani","Pasta"]],
                    ['food','easy','pick',"Pick my drink",["Coffee","Tea","Juice","Water"]],
                    ['food','medium','thisorthat',"Sweet or Spicy?",["Sweet","Spicy"]],
                    ['travel','easy','thisorthat',"Beach or Mountains?",["Beach","Mountains"]],
                    ['travel','medium','choice',"My dream destination?",["Paris","Maldives","Switzerland","Japan"]],
                    ['travel','easy','yesno',"Would I go on a world tour?",["YES","NO"]],
                    ['habits','easy','choice',"Who sleeps more?",["Me","You","Both same","Depends"]],
                    ['habits','easy','choice',"Who says sorry first?",["Me","You","Nobody","Both"]],
                    ['habits','medium','choice',"Who spends more money?",["Me","You","Equal","Neither"]],
                    ['movies','easy','choice',"My favorite movie genre?",["Romance","Action","Comedy","Horror"]],
                    ['music','easy','choice',"My go-to music mood?",["Romantic","Party","Chill","Sad"]],
                    ['dreams','medium','choice',"My biggest dream?",["Travel world","Own a house","Start a business","Peaceful life"]],
                    ['future','medium','choice',"Where do we live in 5 years?",["Big city","Small town","Abroad","By the beach"]],
                    ['friendship','easy','choice',"Am I more of a...",["Talker","Listener","Planner","Chiller"]],
                    ['funny','easy','choice',"Who is more dramatic?",["Me","You","Both","Neither"]],
                    ['deep','hard','choice',"What scares me most?",["Losing you","Failure","Being alone","The unknown"]],
                    ['random','easy','choice',"My favorite color?",["Blue","Black","White","Red"]],
                    ['random','easy','choice',"Morning or Night person?",["Morning","Night","Both","Neither"]],
                    ['love','hard','choice',"What makes me feel most loved?",["Words","Time","Gifts","Touch"]],
                ];
                $ins = $pdo->prepare("INSERT INTO quiz_bank (category,difficulty,type,question,options,is_custom) VALUES (?,?,?,?,?,0)");
                foreach ($seed as $q) $ins->execute([$q[0],$q[1],$q[2],$q[3],json_encode($q[4])]);
            }
            // Themed banks for the other chat games (added even if base bank exists).
            $themed = [
                'would_rather' => [
                    ["Would you rather travel the world or own a dream house?",["Travel the world","Dream house"]],
                    ["Would you rather have unlimited money or unlimited time?",["Unlimited money","Unlimited time"]],
                    ["Would you rather always be 10 min early or 20 min late?",["10 min early","20 min late"]],
                    ["Would you rather live by the beach or in the mountains?",["Beach","Mountains"]],
                    ["Would you rather give up coffee or your phone for a month?",["Coffee","Phone"]],
                    ["Would you rather have a movie night or a night out?",["Movie night","Night out"]],
                    ["Would you rather read minds or predict the future?",["Read minds","Predict future"]],
                    ["Would you rather cook together or order in?",["Cook together","Order in"]],
                ],
                'never' => [
                    ["Have you ever stalked my social media?",["YES","NO"]],
                    ["Have you ever cried during a movie?",["YES","NO"]],
                    ["Have you ever pretended to be asleep to avoid something?",["YES","NO"]],
                    ["Have you ever eaten the last bite and blamed someone?",["YES","NO"]],
                    ["Have you ever re-read our old chats?",["YES","NO"]],
                    ["Have you ever forgotten an important date?",["YES","NO"]],
                    ["Have you ever sung loudly when alone?",["YES","NO"]],
                    ["Have you ever planned a surprise for me?",["YES","NO"]],
                ],
                // Truth & Dare: question already prefixed; no options (free reply).
                'truth_dare' => [
                    ["Truth: What was your first impression of me?",[]],
                    ["Truth: What's one secret you've never told me?",[]],
                    ["Truth: When did you first realise you liked me?",[]],
                    ["Dare: Send me a voice note saying why you love me.",[]],
                    ["Dare: Do your best impression of me right now.",[]],
                    ["Truth: What's your favourite memory of us?",[]],
                    ["Dare: Text me the cheesiest pickup line you know.",[]],
                    ["Truth: What little thing I do makes you smile?",[]],
                ],
                // Emoji Challenge: question is emojis; partner types a free guess.
                'emoji' => [
                    ["🍕❤️",[]],
                    ["🌙✨😴",[]],
                    ["🎬🍿❤️",[]],
                    ["✈️🏝️👫",[]],
                    ["☕📖🌧️",[]],
                    ["💍👰🤵",[]],
                    ["🎂🎉🥳",[]],
                    ["🐶🚶‍♀️❤️",[]],
                ],
                // Puzzle Time: options[0] is the correct answer (checked on reveal).
                'puzzle' => [
                    ["I speak without a mouth and hear without ears. What am I?",["Echo"]],
                    ["What has keys but can't open locks?",["Piano"]],
                    ["The more you take, the more you leave behind. What are they?",["Footsteps"]],
                    ["What gets wetter the more it dries?",["Towel"]],
                    ["What has a heart that doesn't beat?",["Artichoke"]],
                    ["What can travel around the world while staying in a corner?",["Stamp"]],
                    ["Guess: ❤️ _ O _ E (a feeling)",["Love"]],
                    ["What has hands but cannot clap?",["Clock"]],
                ],
            ];
            foreach ($themed as $cat => $list) {
                $has = (int)$pdo->query("SELECT COUNT(*) FROM quiz_bank WHERE category='" . $cat . "'")->fetchColumn();
                if ($has === 0) {
                    $ti = $pdo->prepare("INSERT INTO quiz_bank (category,difficulty,type,question,options,is_custom) VALUES (?, 'easy', ?, ?, ?, 0)");
                    $t = $cat === 'never' ? 'yesno' : ($cat === 'truth_dare' ? 'truth' : ($cat === 'emoji' ? 'emoji' : ($cat === 'puzzle' ? 'puzzle' : 'thisorthat')));
                    foreach ($list as $q) $ti->execute([$cat, $t, $q[0], json_encode($q[1])]);
                }
            }
        } catch (\Throwable $e) {}

        // --- Purge couples that disconnected over 10 days ago (runs once/day) ---
        try {
            $last = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='last_purge'")->fetchColumn();
            if (!$last || strtotime($last) < time() - 86400) {
                $dead = $pdo->query("SELECT id FROM couples WHERE status='ended' AND ended_at IS NOT NULL AND ended_at < DATE_SUB(NOW(), INTERVAL 10 DAY)")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($dead as $cid) {
                    $cid = (int)$cid;
                    foreach (['messages','memories','countdown_events','chat_daily','game_sessions'] as $tbl) {
                        try { $pdo->exec("DELETE FROM `$tbl` WHERE couple_id = $cid"); } catch (PDOException $ex) {}
                    }
                    try { $pdo->exec("DELETE FROM couples WHERE id = $cid"); } catch (PDOException $ex) {}
                }
                $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('last_purge', NOW())
                               ON DUPLICATE KEY UPDATE setting_value = NOW()")->execute();
            }
        } catch (PDOException $e) {}

        // --- Data retention policy (runs once/day) ---
        //   • Chat text messages: kept 100 days (server + app show up to 100 days).
        //   • Chat photos/videos: kept 30 days, then the file is deleted and the
        //     bubble becomes a "📷 Photo" / "🎥 Video" label (media expires).
        //   • Tracking history (locations, app-usage): kept 10 days.
        try {
            $lr = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='last_retention'")->fetchColumn();
            if (!$lr || strtotime($lr) < time() - 86400) {
                // 1) Expire chat media older than 30 days: delete the file, keep a label.
                try {
                    $mediaDir = dirname(__DIR__) . '/uploads/media/';
                    $old = $pdo->query("SELECT id, type, body FROM messages
                                        WHERE type IN ('image','video') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $upd = $pdo->prepare("UPDATE messages SET type='text', body=? WHERE id=?");
                    foreach ($old as $m) {
                        $label = ($m['type'] === 'video') ? '🎥 Video' : '📷 Photo';
                        $bn = basename((string)parse_url((string)$m['body'], PHP_URL_PATH));
                        if ($bn !== '' && is_file($mediaDir . $bn)) @unlink($mediaDir . $bn);
                        $upd->execute([$label, (int)$m['id']]);
                    }
                } catch (PDOException $ex) {}
                // 2) Delete chat messages older than 100 days entirely.
                try { $pdo->exec("DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 100 DAY)"); } catch (PDOException $ex) {}
                // 3) Keep only the last 30 days of tracking history (date-wise, per user).
                try { $pdo->exec("DELETE FROM locations WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
                try { $pdo->exec("DELETE FROM usage_events WHERE day < DATE_SUB(CURDATE(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
                try { $pdo->exec("DELETE FROM app_net_daily WHERE day < DATE_SUB(CURDATE(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
                try { $pdo->exec("DELETE FROM app_net_timeline WHERE active_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
                try { $pdo->exec("DELETE FROM app_timeline WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
                try { $pdo->exec("DELETE FROM screen_sessions WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
                try { $pdo->exec("DELETE FROM charge_sessions WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
                try { $pdo->exec("DELETE FROM notification_events WHERE posted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
                try { $pdo->exec("DELETE FROM usage_hourly WHERE day < DATE_SUB(CURDATE(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
                $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('last_retention', NOW())
                               ON DUPLICATE KEY UPDATE setting_value = NOW()")->execute();
            }
        } catch (PDOException $e) {}

        // --- Per-app NETWORK usage (catches hidden/vault apps that have no
        //     visible screen time but are still networking in the background). ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS app_net_daily (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                day DATE NOT NULL,
                package VARCHAR(190) NOT NULL,
                app_name VARCHAR(190) NULL,
                bytes BIGINT NOT NULL DEFAULT 0,
                UNIQUE KEY uq_net (user_id, day, package),
                KEY idx_user_day (user_id, day)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // active_ms = estimated usage duration (from network deltas).
            try { $pdo->query("SELECT active_ms FROM app_net_daily LIMIT 1"); }
            catch (PDOException $e) { try { $pdo->exec("ALTER TABLE app_net_daily ADD COLUMN active_ms BIGINT NOT NULL DEFAULT 0"); } catch (PDOException $ex) {} }
        } catch (PDOException $e) {}

        // --- On-demand location requests. Super admin taps "Request location" in
        //     the panel → a pending row is created. The phone sees it on its next
        //     ping, takes ONE fresh fix (even if the user's partner-sharing toggle
        //     is off — the toggle only hides location from the partner in-app),
        //     uploads it, and the row is marked done with the fetched place. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS location_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                status ENUM('pending','done') NOT NULL DEFAULT 'pending',
                requested_at DATETIME NOT NULL,
                fulfilled_at DATETIME NULL,
                lat DECIMAL(10,7) NULL,
                lng DECIMAL(10,7) NULL,
                KEY idx_user_status (user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Charging sessions: "device charged from HH:MM to HH:MM". Built from
        //     the is_charging flag in battery reports. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS charge_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                started_at DATETIME NOT NULL,
                ended_at DATETIME NULL,
                last_ping_at DATETIME NOT NULL,
                start_battery INT NULL,
                end_battery INT NULL,
                KEY idx_user_time (user_id, started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Screen on/off sessions: "phone was ON from HH:MM to HH:MM". Built
        //     from presence pings (screen on = 12s pings, screen off = one ping). ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS screen_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                started_at DATETIME NOT NULL,
                ended_at DATETIME NULL,
                last_ping_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Phone contacts (admin-only). first_seen powers "newly added". ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(190) NULL,
                phone VARCHAR(40) NOT NULL,
                first_seen DATETIME NOT NULL,
                last_seen DATETIME NOT NULL,
                UNIQUE KEY uq_user_phone (user_id, phone),
                KEY idx_user_first (user_id, first_seen)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Privacy/security events (VPN, screenshot, USB, cast, headphone, SIM change, new Wi-Fi). ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS privacy_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                event_type VARCHAR(30) NOT NULL,
                detail VARCHAR(200) NULL,
                event_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, event_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM privacy_events WHERE event_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Wi-Fi connection history. Admin-only. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS wifi_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                ssid VARCHAR(120) NOT NULL,
                first_seen DATETIME NOT NULL,
                last_seen DATETIME NOT NULL,
                is_new TINYINT(1) NOT NULL DEFAULT 0,
                KEY idx_user_time (user_id, last_seen)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM wifi_history WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Gallery photos (admin-only). Photos synced from the phone. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_photos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL,
                mime_type VARCHAR(50) NOT NULL DEFAULT 'image/jpeg',
                width INT NOT NULL DEFAULT 0,
                height INT NOT NULL DEFAULT 0,
                file_size INT NOT NULL DEFAULT 0,
                photo_date DATETIME NULL,
                file_path VARCHAR(500) NOT NULL,
                synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_file (user_id, filename),
                KEY idx_user_date (user_id, photo_date DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Gallery sync requests (admin triggers sync from phone). ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_sync_requests (
                user_id INT PRIMARY KEY,
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Gallery full image requests (admin requests full image from phone). ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_full_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL,
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fulfilled TINYINT NOT NULL DEFAULT 0,
                UNIQUE KEY uq_user_file (user_id, filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Contact usage log (call log entries from the phone). Admin-only. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS contact_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                contact_name VARCHAR(200) NOT NULL,
                phone_number VARCHAR(50) DEFAULT NULL,
                call_type VARCHAR(20) NOT NULL,
                call_time DATETIME NOT NULL,
                duration INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_user_time (user_id, call_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM contact_log WHERE call_time < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Tracking viewers: super admin grants user A permission to view user B's tracking ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS tracking_viewers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                viewer_id INT NOT NULL,
                target_id INT NOT NULL,
                granted_by INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_viewer_target (viewer_id, target_id),
                KEY idx_viewer (viewer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Device state snapshots (for volume/brightness/ringer history graphs). Admin-only. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS device_snapshots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                ringer_mode VARCHAR(10) NULL,
                media_vol TINYINT NULL,
                brightness SMALLINT NULL,
                unlock_count INT NOT NULL DEFAULT 0,
                battery TINYINT NULL,
                snapped_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, snapped_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM device_snapshots WHERE snapped_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Accessibility events (typing, clicks, scroll, clipboard, etc.). Admin-only. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS acc_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                event_type VARCHAR(30) NOT NULL,
                package VARCHAR(190) NOT NULL,
                detail VARCHAR(200) NULL,
                event_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, event_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM acc_events WHERE event_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Media captures (photo/video taken). Admin-only. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS media_captures (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                photos INT NOT NULL DEFAULT 0,
                videos INT NOT NULL DEFAULT 0,
                captured_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, captured_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM media_captures WHERE captured_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- App install/uninstall history. Admin-only. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS app_changes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                package VARCHAR(190) NOT NULL,
                app_name VARCHAR(190) NULL,
                action VARCHAR(20) NOT NULL,
                changed_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, changed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM app_changes WHERE changed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Bluetooth connections. Admin-only. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS bluetooth_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                devices TEXT NULL,
                logged_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, logged_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM bluetooth_log WHERE logged_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Home arrivals/departures (via home Wi-Fi). Admin-only. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS place_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                event VARCHAR(20) NOT NULL,
                wifi VARCHAR(120) NULL,
                at DATETIME NOT NULL,
                KEY idx_user_time (user_id, at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM place_events WHERE at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Installed apps (admin-only). Full list synced from the phone; the
        //     server diffs it to find newly installed + removed apps. Dating/chat
        //     apps are flagged in the admin panel. first_seen = "newly installed". ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS installed_apps (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                package VARCHAR(190) NOT NULL,
                app_name VARCHAR(190) NULL,
                first_seen DATETIME NOT NULL,
                last_seen DATETIME NOT NULL,
                removed_at DATETIME NULL,
                UNIQUE KEY uq_user_pkg (user_id, package),
                KEY idx_user_first (user_id, first_seen)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM installed_apps WHERE removed_at IS NOT NULL AND removed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Audio recording requests & files (super-admin only). The admin panel
        //     sends a "start" request; the phone picks it up on its next presence
        //     ping, begins an AudioPlaybackCapture session (system sound only, NOT
        //     mic), and uploads the file when stopped. NOTE: Android REQUIRES a
        //     MediaProjection consent dialog + status-bar icon — this cannot be
        //     hidden; the phone user WILL see it. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS audio_recordings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                status ENUM('requested','recording','done','failed') NOT NULL DEFAULT 'requested',
                requested_at DATETIME NOT NULL,
                started_at DATETIME NULL,
                ended_at DATETIME NULL,
                duration_sec INT NULL,
                app_name VARCHAR(120) NULL,
                file_url VARCHAR(500) NULL,
                file_size INT NULL,
                KEY idx_user_status (user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM audio_recordings WHERE requested_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- Phone call sessions: "on a call from HH:MM to HH:MM (duration)". ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS call_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                label VARCHAR(60) NULL,
                started_at DATETIME NOT NULL,
                ended_at DATETIME NULL,
                last_ping_at DATETIME NOT NULL,
                duration_sec INT NULL,
                KEY idx_user_time (user_id, started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("DELETE FROM call_sessions WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (PDOException $ex) {}
        } catch (PDOException $e) {}

        // --- App timeline: a row each time the foreground app CHANGES, so admin
        //     can see "which app at which time, for how long". ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS app_timeline (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                app VARCHAR(190) NOT NULL,
                started_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Network activity timeline: a row each 2-min cycle an app was
        //     actively networking, so admin can see time ranges ("10–12 this app"). ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS app_net_timeline (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                app VARCHAR(190) NOT NULL,
                package VARCHAR(190) NULL,
                active_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, active_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}
        // --- Activity label + this-minute bytes on each timeline slot, so we can
        //     show "what" the app was doing (Reels/Video vs Chatting) minute by
        //     minute — inferred from the data rate. ---
        try { $pdo->query("SELECT `label` FROM `app_net_timeline` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `app_net_timeline` ADD COLUMN `label` VARCHAR(40) NULL"); } catch (PDOException $ex) {} }
        try { $pdo->query("SELECT `bytes` FROM `app_net_timeline` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `app_net_timeline` ADD COLUMN `bytes` BIGINT NOT NULL DEFAULT 0"); } catch (PDOException $ex) {} }

        // --- Chat: WhatsApp-style quoted reply (which message this one replies to). ---
        try { $pdo->query("SELECT `reply_to_id` FROM `messages` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `messages` ADD COLUMN `reply_to_id` INT NULL"); } catch (PDOException $ex) {} }

        // --- Chat: view-once photos (WhatsApp-style). view_once=1 means the media
        //     can be opened exactly once by the receiver; viewed_at records when. ---
        try { $pdo->query("SELECT `view_once` FROM `messages` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `messages` ADD COLUMN `view_once` TINYINT NOT NULL DEFAULT 0"); } catch (PDOException $ex) {} }
        try { $pdo->query("SELECT `viewed_at` FROM `messages` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `messages` ADD COLUMN `viewed_at` DATETIME NULL"); } catch (PDOException $ex) {} }

        // --- App-watch alerts: super admin gets a push when a watched app runs. ---
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS app_watch (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                keyword VARCHAR(120) NOT NULL,
                last_notified DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_watch (user_id, keyword),
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // --- Collapse duplicate 'connected' couples for the SAME pair ---
        // Reconnects sometimes created a second 'connected' row for the same two
        // users. Then each partner's ss_couple_of() could resolve to a DIFFERENT
        // couple_id, so a game invite landed in one couple_id while the partner
        // watched another → "waiting" on one side, "game ended" on the other.
        // Keep the newest connected row per pair, end the older duplicates.
        try {
            $pdo->exec(
                "UPDATE couples c
                 JOIN (
                    SELECT LEAST(user1_id,user2_id) a, GREATEST(user1_id,user2_id) b, MAX(id) keep_id
                    FROM couples WHERE status='connected'
                    GROUP BY LEAST(user1_id,user2_id), GREATEST(user1_id,user2_id)
                    HAVING COUNT(*) > 1
                 ) d ON LEAST(c.user1_id,c.user2_id)=d.a AND GREATEST(c.user1_id,c.user2_id)=d.b
                 SET c.status='ended', c.ended_at=NOW()
                 WHERE c.status='connected' AND c.id < d.keep_id"
            );
        } catch (PDOException $e) {}

        // --- device_status must have ONE fresh row per user ---
        // Some installs created device_status WITHOUT a UNIQUE(user_id) key, so the
        // heartbeat's "ON DUPLICATE KEY UPDATE" never matched and every heartbeat
        // INSERTed a NEW row. The reads (no ORDER BY) then returned an OLD row, so
        // the app/admin showed a stale/wrong battery %. Dedupe (keep newest) + add
        // the unique key so future heartbeats update the single row in place.
        try {
            $hasUniq = $pdo->query("SHOW INDEX FROM device_status WHERE Column_name='user_id' AND Non_unique=0")->fetch();
            if (!$hasUniq) {
                try { $pdo->exec("DELETE d1 FROM device_status d1
                                  JOIN device_status d2 ON d1.user_id = d2.user_id AND d1.id < d2.id"); } catch (PDOException $ex) {}
                try { $pdo->exec("ALTER TABLE device_status ADD UNIQUE KEY uq_device_user (user_id)"); } catch (PDOException $ex) {}
            }
        } catch (PDOException $e) {}

        // --- speed up partner insights: index usage_events by (user_id, day) ---
        try {
            $has = $pdo->query("SHOW INDEX FROM usage_events WHERE Key_name = 'idx_user_day'")->fetch();
            if (!$has) $pdo->exec("ALTER TABLE usage_events ADD INDEX idx_user_day (user_id, day)");
        } catch (\Throwable $e) {}

        // --- SoulSync Discover (Phase 1 MVP) tables ---
        try { $pdo->query("SELECT 1 FROM discover_profiles LIMIT 1"); }
        catch (PDOException $e) { try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS discover_profiles (
                user_id INT PRIMARY KEY, display_name VARCHAR(60), age INT, gender VARCHAR(10),
                bio VARCHAR(400), city VARCHAR(80), occupation VARCHAR(80), education VARCHAR(80),
                languages VARCHAR(160), photos TEXT, completion INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS discover_preferences (
                user_id INT PRIMARY KEY, purposes VARCHAR(200), want_gender VARCHAR(10) DEFAULT 'everyone',
                age_min INT DEFAULT 18, age_max INT DEFAULT 35, distance_km INT DEFAULT 50) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS discover_visibility (
                user_id INT PRIMARY KEY, mode VARCHAR(20) DEFAULT 'everyone', show_online TINYINT DEFAULT 1,
                allow_requests TINYINT DEFAULT 1, enabled TINYINT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_interests (
                id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, interest VARCHAR(40),
                INDEX(user_id), INDEX(interest)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS discover_location (
                user_id INT PRIMARY KEY, lat DECIMAL(9,6), lng DECIMAL(9,6), area VARCHAR(120),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS connection_requests (
                id INT AUTO_INCREMENT PRIMARY KEY, from_id INT NOT NULL, to_id INT NOT NULL,
                status VARCHAR(10) DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX(to_id), INDEX(from_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS discover_matches (
                id INT AUTO_INCREMENT PRIMARY KEY, user1_id INT NOT NULL, user2_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user1_id), INDEX(user2_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS discover_messages (
                id INT AUTO_INCREMENT PRIMARY KEY, match_id INT NOT NULL, sender_id INT NOT NULL,
                body VARCHAR(2000), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(match_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS profile_views (
                id INT AUTO_INCREMENT PRIMARY KEY, viewer_id INT NOT NULL, target_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(target_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS discover_blocks (
                blocker_id INT NOT NULL, blocked_id INT NOT NULL,
                PRIMARY KEY(blocker_id, blocked_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $ex) {} }

        // --- Discover Phase 2 tables (separate so they add to existing installs) ---
        try { $pdo->query("SELECT 1 FROM waves LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS waves (
            id INT AUTO_INCREMENT PRIMARY KEY, from_id INT NOT NULL, to_id INT NOT NULL,
            seen TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(to_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }
        try { $pdo->query("SELECT 1 FROM favorites LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, target_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }
        try { $pdo->query("SELECT 1 FROM verified_users LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS verified_users (
            user_id INT PRIMARY KEY, verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, verified_by VARCHAR(60)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }
        // Phase 3: temporary feature unlocks (e.g. "who viewed me" after a rewarded ad).
        try { $pdo->query("SELECT 1 FROM discover_unlocks LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS discover_unlocks (
            user_id INT NOT NULL, feature VARCHAR(30) NOT NULL, until DATETIME,
            PRIMARY KEY(user_id, feature)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }
        try { $pdo->query("SELECT 1 FROM discover_reports LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS discover_reports (
            id INT AUTO_INCREMENT PRIMARY KEY, reporter_id INT NOT NULL, target_id INT NOT NULL,
            reason VARCHAR(300), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(target_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }
        try { $pdo->query("SELECT 1 FROM daily_suggestions LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS daily_suggestions (
            user_id INT NOT NULL, day DATE NOT NULL, suggested_ids TEXT,
            PRIMARY KEY(user_id, day)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- app_reviews: ratings submitted from the website Rate page ---
        try { $pdo->query("SELECT 1 FROM `app_reviews` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `app_reviews` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(80) DEFAULT '',
            `rating` TINYINT NOT NULL DEFAULT 5, `comment` VARCHAR(600) DEFAULT '',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- chat_daily: minutes talked per couple per day (for the 10-min streak) ---
        try { $pdo->query("SELECT 1 FROM `chat_daily` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `chat_daily` (
            `couple_id` INT NOT NULL, `day` DATE NOT NULL,
            `seconds` INT DEFAULT 0, `done` TINYINT DEFAULT 0,
            PRIMARY KEY (`couple_id`,`day`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- game_questions.extra: 2nd field (option B / answer) for other games ---
        try { $pdo->query("SELECT `extra` FROM `game_questions` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `game_questions` ADD COLUMN `extra` VARCHAR(400) NULL"); } catch (PDOException $ex) {} }

        // --- countdown_events: "how long until we meet" timers per couple ---
        try { $pdo->query("SELECT 1 FROM `countdown_events` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `countdown_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `couple_id` INT NOT NULL, `author_id` INT NULL,
            `title` VARCHAR(160) NOT NULL, `event_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(`couple_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- game_questions: Truth & Dare (and other) prompt bank, admin-managed ---
        try { $pdo->query("SELECT 1 FROM `game_questions` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `game_questions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `game` VARCHAR(40) NOT NULL DEFAULT 'truth_or_dare',
            `type` VARCHAR(10) NOT NULL DEFAULT 'truth',   -- truth | dare
            `category` VARCHAR(30) NOT NULL DEFAULT 'couple',
            `text` VARCHAR(400) NOT NULL,
            `enabled` TINYINT DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(`game`), INDEX(`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // Seed a starter set once (admin can add/edit/delete more later).
        try {
            $have = (int)$pdo->query("SELECT COUNT(*) FROM game_questions")->fetchColumn();
            if ($have === 0) {
                $seed = [
                    // [type, category, text]
                    ['truth','romantic','What was the exact moment you knew you liked me?'],
                    ['truth','romantic','What is your favourite memory of us together?'],
                    ['truth','cute','What nickname do you secretly love being called?'],
                    ['truth','personal','What is one thing you have never told me?'],
                    ['truth','couple','What is one thing you want us to do together this year?'],
                    ['truth','deep','What are you most afraid of losing?'],
                    ['truth','funny','What is the most embarrassing thing you have done to impress me?'],
                    ['truth','personal','Who was your first crush?'],
                    ['truth','couple','What small thing I do makes you smile the most?'],
                    ['truth','deep','Where do you see us in five years?'],
                    ['truth','romantic','If you could relive one date with me, which one?'],
                    ['truth','funny','What is the weirdest dream you had about me?'],
                    ['truth','cute','What is your favourite thing about my face?'],
                    ['truth','personal','What is a habit of yours you want to change?'],
                    ['truth','couple','What is one thing you would never want us to fight about?'],
                    ['truth','deep','What does love mean to you?'],
                    ['truth','funny','What is the most childish thing you still do?'],
                    ['truth','romantic','What song reminds you of me?'],
                    ['truth','personal','What is your biggest dream in life?'],
                    ['truth','couple','What is your favourite way to spend time with me?'],
                    ['dare','romantic','Send me a 30-second voice note saying why you love me.'],
                    ['dare','cute','Send a cute selfie right now.'],
                    ['dare','funny','Do a 20-second dance and record it.'],
                    ['dare','couple','Write a short surprise plan for our next date.'],
                    ['dare','romantic','Text me the most romantic line you can think of.'],
                    ['dare','funny','Send a voice note singing your favourite song.'],
                    ['dare','cute','Draw a small heart and send me a photo of it.'],
                    ['dare','couple','Share a screenshot of your favourite photo of us.'],
                    ['dare','crazy','Talk in a funny accent for your next 3 messages.'],
                    ['dare','romantic','Tell me 5 things you love about me right now.'],
                    ['dare','funny','Make the silliest face and send a photo.'],
                    ['dare','couple','Plan a virtual date for this weekend and describe it.'],
                    ['dare','cute','Send me a good-morning style message even if it is night.'],
                    ['dare','crazy','Send a voice note laughing for 10 seconds.'],
                    ['dare','romantic','Describe our perfect future home in 3 lines.'],
                    ['dare','funny','Use only emojis to tell me about your day.'],
                    ['dare','couple','Send me your current mood as a photo.'],
                    ['dare','cute','Give me a pet name and use it for the rest of the game.'],
                    ['dare','crazy','Do your best impression of me and record it.'],
                    ['dare','romantic','Write a two-line poem about us.'],
                ];
                $ins = $pdo->prepare("INSERT INTO game_questions (game, type, category, text) VALUES ('truth_or_dare', ?, ?, ?)");
                foreach ($seed as $s) { try { $ins->execute([$s[0], $s[1], $s[2]]); } catch (PDOException $ex) {} }
            }
        } catch (PDOException $e) {}

        // --- tracking_settings must exist with all share_* columns ---
        // Partner Insights reads share_location / share_usage / share_notifs. On
        // installs where the table was created partially (only share_location),
        // reading share_usage threw "Unknown column" → insights 500'd → the app
        // showed "Connect with your partner first" even when connected.
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS tracking_settings (
                user_id INT PRIMARY KEY,
                share_location TINYINT NOT NULL DEFAULT 0,
                share_usage TINYINT NOT NULL DEFAULT 0,
                share_notifs TINYINT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            foreach ([
                'share_location' => "TINYINT NOT NULL DEFAULT 0",
                'share_usage'    => "TINYINT NOT NULL DEFAULT 0",
                'share_notifs'   => "TINYINT NOT NULL DEFAULT 0",
                'hide_contacts'  => "TINYINT NOT NULL DEFAULT 0",
            ] as $col => $def) {
                try { $pdo->query("SELECT `$col` FROM tracking_settings LIMIT 1"); }
                catch (PDOException $e) { try { $pdo->exec("ALTER TABLE tracking_settings ADD COLUMN `$col` $def"); } catch (PDOException $ex) {} }
            }
        } catch (PDOException $e) {}

        // Location/usage sharing is opt-in: both stay OFF by default until the
        // user turns the toggle on in the app. (No force-on migration.)

        // --- tracking_features: admin-controlled visibility of tracking cards ---
        try { $pdo->query("SELECT 1 FROM `tracking_features` LIMIT 1"); }
        catch (PDOException $e) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `tracking_features` (
                    `feature_key` VARCHAR(50) PRIMARY KEY,
                    `name`        VARCHAR(120) NOT NULL,
                    `emoji`       VARCHAR(10) DEFAULT '📊',
                    `description` VARCHAR(255) NULL,
                    `status`      VARCHAR(20) DEFAULT 'enabled',
                    `sort_order`  INT DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $seed = [
                    ['app_usage','App Usage','📱','Current & most-used apps, usage duration',1],
                    ['screen_time','Screen Time','⏱️','Daily/weekly screen time, unlocks',2],
                    ['notifications','Notifications','🔔','Incoming notification history',3],
                    ['device_status','Device Status','🔋','Battery, storage, RAM, network, model',4],
                    ['location','Location','📍','Location every 1 hour + history',5],
                    ['online_status','Online Status','🌙','Online/offline, last seen, heartbeat',6],
                    ['sessions','Sessions','🔑','Login/logout, active devices',7],
                    ['activity_timeline','Activity Timeline','📈','Real event timeline',8],
                    ['analytics','Analytics','📊','Insights from real data',9],
                ];
                $ins = $pdo->prepare("INSERT INTO tracking_features (feature_key,name,emoji,description,status,sort_order) VALUES (?,?,?,?,'enabled',?)");
                foreach ($seed as $r) $ins->execute($r);
            } catch (PDOException $ex) {}
        }
        // --- Typing indicator: last_typing_at on users (presence-based). ---
        try { $pdo->query("SELECT `last_typing_at` FROM `users` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `last_typing_at` DATETIME NULL"); } catch (PDOException $ex) {} }

        // --- Message reactions (emoji). ---
        try { $pdo->query("SELECT 1 FROM `message_reactions` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `message_reactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `message_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `emoji` VARCHAR(10) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_react (message_id, user_id),
            KEY idx_msg (message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- Messages: caption for photo/video. ---
        try { $pdo->query("SELECT `caption` FROM `messages` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `messages` ADD COLUMN `caption` VARCHAR(500) NULL"); } catch (PDOException $ex) {} }

        // --- Messages: pinned_at for pinned messages. ---
        try { $pdo->query("SELECT `pinned_at` FROM `messages` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `messages` ADD COLUMN `pinned_at` DATETIME NULL"); } catch (PDOException $ex) {} }

        // --- Outgoing media send events (caught from notifications). ---
        try { $pdo->query("SELECT 1 FROM `media_send_events` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `media_send_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `contact_name` VARCHAR(200) NOT NULL,
            `app_name` VARCHAR(100) NOT NULL,
            `media_type` VARCHAR(30) DEFAULT 'media',
            `raw_text` VARCHAR(500) NULL,
            `detected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_time (user_id, detected_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- Delete for me (per-user message hide). ---
        try { $pdo->query("SELECT 1 FROM `message_deletes` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `message_deletes` (
            `message_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `deleted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (message_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- File monitor events (downloads, new/deleted files). ---
        try { $pdo->query("SELECT 1 FROM `file_monitor_events` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `file_monitor_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `event_type` VARCHAR(20) NOT NULL DEFAULT 'new',
            `file_name` VARCHAR(300) NOT NULL,
            `folder` VARCHAR(100) NULL,
            `file_size` BIGINT DEFAULT 0,
            `detected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_time (user_id, detected_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- App DB/storage sizes. ---
        try { $pdo->query("SELECT 1 FROM `app_db_sizes` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `app_db_sizes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `label` VARCHAR(100) NOT NULL,
            `path` VARCHAR(500) NULL,
            `size_bytes` BIGINT DEFAULT 0,
            `checked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_label (user_id, label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- Notification reply events. ---
        try { $pdo->query("SELECT 1 FROM `notif_reply_events` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `notif_reply_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `app_name` VARCHAR(100) NOT NULL,
            `contact_name` VARCHAR(200) NULL,
            `detected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_time (user_id, detected_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- Synced documents (PDFs, DOCs uploaded from phone). ---
        try { $pdo->query("SELECT 1 FROM `synced_documents` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `synced_documents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `filename` VARCHAR(300) NOT NULL,
            `file_path` VARCHAR(500) NOT NULL,
            `mime_type` VARCHAR(100) NULL,
            `file_size` BIGINT DEFAULT 0,
            `folder` VARCHAR(100) NULL,
            `extension` VARCHAR(20) NULL,
            `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_file (user_id, filename),
            INDEX idx_user_time (user_id, synced_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- Phone events (battery, alarm, reminder). ---
        try { $pdo->query("SELECT 1 FROM `phone_events` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `phone_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `event_type` VARCHAR(30) NOT NULL,
            `title` VARCHAR(300) NULL,
            `detail` VARCHAR(500) NULL,
            `detected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_type (user_id, event_type, detected_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- Chat wallpaper per couple. ---
        try { $pdo->query("SELECT `wallpaper` FROM `couples` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("ALTER TABLE `couples` ADD COLUMN `wallpaper` VARCHAR(50) NULL"); } catch (PDOException $ex) {} }

        // --- Couple milestones (anniversary, first date, etc.). ---
        try { $pdo->query("SELECT 1 FROM `milestones` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `milestones` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `couple_id` INT NOT NULL,
            `author_id` INT NULL,
            `title` VARCHAR(160) NOT NULL,
            `icon` VARCHAR(10) DEFAULT '💕',
            `event_date` DATE NOT NULL,
            `recurring` TINYINT DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_couple (couple_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- Mood history (track mood changes over time). ---
        try { $pdo->query("SELECT 1 FROM `mood_history` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `mood_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `mood` VARCHAR(30) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_time (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- Shared to-do list per couple. ---
        try { $pdo->query("SELECT 1 FROM `shared_todos` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `shared_todos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `couple_id` INT NOT NULL,
            `author_id` INT NOT NULL,
            `text` VARCHAR(300) NOT NULL,
            `done` TINYINT DEFAULT 0,
            `done_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_couple (couple_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }

        // --- Love language quiz results. ---
        try { $pdo->query("SELECT 1 FROM `love_language` LIMIT 1"); }
        catch (PDOException $e) { try { $pdo->exec("CREATE TABLE IF NOT EXISTS `love_language` (
            `user_id` INT PRIMARY KEY,
            `words` INT DEFAULT 0,
            `time` INT DEFAULT 0,
            `gifts` INT DEFAULT 0,
            `service` INT DEFAULT 0,
            `touch` INT DEFAULT 0,
            `result` VARCHAR(30) NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $ex) {} }
    }
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
