<?php
// =========================================================================
// SoulSync REST API  (pure PHP — runs on cPanel shared hosting)
// Entry: /soulsync-system/api.php?route=auth/login
// Contract matches the Flutter app's ApiService/models exactly.
// All app data in MySQL (soulsync). Push via FCM. Realtime via poll.
// =========================================================================

date_default_timezone_set('Asia/Kolkata');
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '105M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/fcm.php';

function body() {
    static $b = null;
    if ($b !== null) return $b;
    $raw = file_get_contents('php://input');
    $b = json_decode($raw, true);
    if (!is_array($b)) $b = $_POST ?: [];
    return $b;
}
function inp($k, $d = null) { $b = body(); return $b[$k] ?? ($_GET[$k] ?? $d); }

function current_user($pdo, $required = true) {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!$hdr && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) if (strtolower($k) === 'authorization') $hdr = $v;
    }
    $token = '';
    if (stripos($hdr, 'Bearer ') === 0) $token = trim(substr($hdr, 7));
    if (!$token) $token = inp('token', '');

    if ($token) {
        $stmt = $pdo->prepare(
            "SELECT u.* FROM auth_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token = ? AND t.expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([$token]);
        $u = $stmt->fetch();
        if ($u) {
            if ($u['status'] === 'blocked') json_err('Account blocked', 403);
            // Only refresh last_seen here. "Online" is controlled explicitly by the
            // presence endpoint (app foreground) so background sync doesn't fake it.
            $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$u['id']]);
            return $u;
        }
    }
    if ($required) json_err('Unauthorized', 401);
    return null;
}

function issue_token($pdo, $user_id, $device = null) {
    $token = ss_token();
    $pdo->prepare("INSERT INTO auth_tokens (user_id, token, device, expires_at)
                   VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 90 DAY))")
        ->execute([$user_id, $token, $device]);
    return $token;
}

// Shape a game_sessions row into the structure the app expects.
function ss_game_app($row) {
    $state = json_decode($row['state'] ?? '{}', true) ?: [];
    return [
        '_id'          => (string)$row['id'],
        'id'           => (string)$row['id'],
        'gameType'     => $row['game_slug'],
        'status'       => $row['status'],
        'currentRound' => (int)($state['currentRound'] ?? 1),
        'totalRounds'  => (int)($state['totalRounds'] ?? count($state['rounds'] ?? []) ?: 5),
        'rounds'       => $state['rounds'] ?? [],
    ];
}

// Insert an in-chat game card message (type='game', body = JSON payload).
function ss_game_card($pdo, $couple_id, $sender_id, array $payload) {
    $pdo->prepare("INSERT INTO messages (couple_id, sender_id, type, body, status) VALUES (?, ?, 'game', ?, 'sent')")
        ->execute([$couple_id, $sender_id, json_encode($payload)]);
    return (int)$pdo->lastInsertId();
}

// Pick a random enabled Truth/Dare prompt, with a safe fallback.
function ss_pick_prompt($pdo, $type) {
    $q = $pdo->prepare("SELECT text FROM game_questions WHERE game='truth_or_dare' AND type=? AND enabled=1 ORDER BY RAND() LIMIT 1");
    $q->execute([$type]);
    $t = $q->fetchColumn();
    if ($t) return $t;
    $fb = $type === 'dare'
        ? ['Send a cute selfie right now.', 'Record a 20-second silly dance.', 'Tell 3 things you love about me.']
        : ['What made you fall for me?', 'Favourite memory of us?', 'One thing you never told me?'];
    return $fb[array_rand($fb)];
}

// Streak state for a couple: talk 10+ min/day (both partners) to keep it alive.
function ss_streak_state($pdo, $cid) {
    $c = $pdo->prepare("SELECT streak_count, streak_date FROM couples WHERE id = ?");
    $c->execute([$cid]); $cur = $c->fetch() ?: [];
    $sd = $cur['streak_date'] ?? null;
    $today = date('Y-m-d'); $yest = date('Y-m-d', strtotime('-1 day'));
    // If the last qualifying day is older than yesterday, the streak is broken.
    $streak = ($sd === $today || $sd === $yest) ? (int)($cur['streak_count'] ?? 0) : 0;

    $d = $pdo->prepare("SELECT seconds FROM chat_daily WHERE couple_id = ? AND day = CURDATE()");
    $d->execute([$cid]); $sec = (int)($d->fetchColumn() ?: 0);
    $bothQ = $pdo->prepare("SELECT COUNT(DISTINCT sender_id) FROM messages WHERE couple_id = ? AND DATE(created_at) = CURDATE()");
    $bothQ->execute([$cid]);
    $both = (int)$bothQ->fetchColumn() >= 2;

    return [
        'currentStreak' => $streak,
        'goalSeconds'   => 600,
        'goalMinutes'   => 10,
        'todaySeconds'  => $sec,
        'todayMinutes'  => intdiv($sec, 60),
        'bothTalkedToday'=> $both,
        'goalMetToday'  => ($sec >= 600 && $both) || $sd === $today,
        'minutesLeft'   => max(0, (int)ceil((600 - $sec) / 60)),
    ];
}

$route  = trim($_GET['route'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];
$rparts = explode('/', $route);

try {

// ── Dynamic: games/{id}/answer|next|end ─────────────────────────────────
if ($rparts[0] === 'games' && count($rparts) === 3) {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $sid = (int)$rparts[1];
    $action = $rparts[2];
    $g = $pdo->prepare("SELECT * FROM game_sessions WHERE id = ? AND couple_id = ?");
    $g->execute([$sid, $couple['id']]);
    $sess = $g->fetch();
    if (!$sess) json_err('Session not found', 404);
    $state = json_decode($sess['state'] ?? '{}', true) ?: [];
    $state['rounds'] = $state['rounds'] ?? [];
    $state['currentRound'] = (int)($state['currentRound'] ?? 1);

    if ($action === 'answer') {
        $idx = $state['currentRound'] - 1;
        if (!isset($state['rounds'][$idx])) $state['rounds'][$idx] = ['answers' => []];
        $state['rounds'][$idx]['answers'][] = ['userId' => (string)$u['id'], 'answer' => inp('answer', '')];
        $pdo->prepare("UPDATE game_sessions SET state = ? WHERE id = ?")->execute([json_encode($state), $sid]);
        ss_push_to_user($pdo, $couple['partner_id'], '🎮 Game', $u['name'] . ' answered', ['type' => 'game']);
        json_ok(['answered' => true]);
    } elseif ($action === 'next') {
        $state['currentRound']++;
        $pdo->prepare("UPDATE game_sessions SET state = ? WHERE id = ?")->execute([json_encode($state), $sid]);
        json_ok(['currentRound' => $state['currentRound']]);
    } elseif ($action === 'end') {
        $pdo->prepare("UPDATE game_sessions SET status = 'finished' WHERE id = ?")->execute([$sid]);
        json_ok(['ended' => true]);
    }
    json_err('Unknown game action', 404);
}

switch ($route) {

// ===================== META / CONFIG ====================================
case 'meta/health':
    json_ok(['status' => 'ok', 'time' => date('c'), 'service' => 'soulsync']);

case 'config': {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    // Parent folder (soulpages) — the public landing page lives there.
    $parent = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
    // The in-app "Update Now" button opens this link in a browser.
    // Priority: admin's direct link → else the SoulSync landing page (soulsync.php).
    $downloadUrl = ss_setting($pdo, 'download_url', '');
    if ($downloadUrl === '') $downloadUrl = $parent . '/soulsync.php';
    // App "My Ads" (house image ads) — placement app_home / app_games / app_memories.
    // Reward video (placement app_reward) is exposed separately as a video URL.
    $appHouse = [];
    $rewardVideo = '';
    try {
        $hs = $pdo->query("SELECT placement, image, link FROM house_ads WHERE enabled=1 AND placement LIKE 'app_%' ORDER BY id DESC");
        foreach ($hs as $r) {
            if ($r['placement'] === 'app_reward') { if ($rewardVideo === '') $rewardVideo = $r['image']; continue; }
            $key = substr($r['placement'], 4);
            if (!isset($appHouse[$key])) $appHouse[$key] = ['image' => $r['image'], 'link' => $r['link']];
        }
    } catch (\Throwable $e) {}
    // Which games are enabled for users (admin → Game Availability). Default ON.
    $gameKeys = ['couple_quiz','would_rather','truth_dare','emoji','puzzle','nhie'];
    $gamesEnabled = [];
    foreach ($gameKeys as $gk) $gamesEnabled[$gk] = (int)ss_setting($pdo, 'game_' . $gk . '_on', '1') === 1;
    json_ok([
        'games_enabled'        => $gamesEnabled,
        'app_house_ads'        => $appHouse,
        'reward_house_video'   => $rewardVideo,
        'maintenance_mode'     => (int)ss_setting($pdo, 'maintenance_mode', '0') === 1,
        'maintenance_message'  => ss_setting($pdo, 'maintenance_message', ''),
        'force_update_version' => ss_setting($pdo, 'min_app_version', '1.0.0'),
        'force_update'         => (int)ss_setting($pdo, 'force_update', '0') === 1,
        'latest_version'       => ss_setting($pdo, 'latest_version', '1.0.0'),
        'apk_url'              => $downloadUrl,
        'update_message'       => ss_setting($pdo, 'update_message', ''),
        'chat_poll_seconds'    => (int)ss_setting($pdo, 'chat_poll_seconds', '3'),
        'location_interval_minutes' => (int)ss_setting($pdo, 'location_interval_minutes', '60'),
        // Foreground tracking switch (super admin → Current Status). ON (default) =
        // always-on foreground service; OFF = the phone stops the service & goes
        // dormant (no notification, no tracking) until turned back on.
        'foreground_on'             => (int)ss_setting($pdo, 'foreground_on', '1') === 1,
        'welcome_message'      => ss_setting($pdo, 'welcome_message', ''),
        // ── Monetization (public bits only — no secrets) ──
        'ads_enabled'          => (int)ss_setting($pdo, 'ads_enabled', '0') === 1,
        'ads_app_enabled'      => (int)ss_setting($pdo, 'adsterra_app_enabled', '0') === 1,
        'ads_app_key'          => ss_setting($pdo, 'adsterra_app_key', ''),
        'ad_home'              => (int)ss_setting($pdo, 'ad_home', '0') === 1,
        'ad_games'             => (int)ss_setting($pdo, 'ad_games', '0') === 1,
        'ad_memories'          => (int)ss_setting($pdo, 'ad_memories', '0') === 1,
        'ad_interstitial'      => (int)ss_setting($pdo, 'ad_interstitial', '0') === 1,
        // Per-placement in-app ad codes (Adsterra HTML rendered in a WebView).
        'ad_app_home_code'       => ss_setting($pdo, 'ad_app_home_code', ''),
        'ad_app_games_code'      => ss_setting($pdo, 'ad_app_games_code', ''),
        'ad_app_memories_code'   => ss_setting($pdo, 'ad_app_memories_code', ''),
        'ad_app_interstitial_code' => ss_setting($pdo, 'ad_app_interstitial_code', ''),
        // Which network each app spot uses: 'startio' (native) or 'html' (code).
        'ad_app_home_provider'      => ss_setting($pdo, 'ad_app_home_provider', 'startio'),
        'ad_app_games_provider'     => ss_setting($pdo, 'ad_app_games_provider', 'startio'),
        'ad_app_memories_provider'  => ss_setting($pdo, 'ad_app_memories_provider', 'startio'),
        'ad_app_interstitial_provider' => ss_setting($pdo, 'ad_app_interstitial_provider', 'startio'),
        'reward_enabled'       => (int)ss_setting($pdo, 'reward_enabled', '0') === 1,
        'reward_ad_url'        => ss_setting($pdo, 'reward_ad_url', ''),
        'reward_source'        => ss_setting($pdo, 'reward_source', 'my_ad'),
        'payment_enabled'      => (int)ss_setting($pdo, 'payment_enabled', '0') === 1,
        'payment_gateway'      => ss_setting($pdo, 'payment_gateway', 'instamojo'),
        'subscription_price'   => (int)ss_setting($pdo, 'subscription_price', '99'),
        'subscription_days'    => (int)ss_setting($pdo, 'subscription_days', '30'),
        // Which games/features are locked behind Premium (managed in admin → Premium Control).
        'premium_locked'       => array_values(array_filter(array_map('trim',
                                    explode(',', ss_setting($pdo, 'premium_locked', ''))))),
    ]);
}

// ===================== AUTH =============================================
case 'auth/register': {
    $username = strtolower(trim(inp('username', '')));
    $email    = trim(inp('email', '')) ?: null;
    $password = (string)inp('password', '');
    $name     = trim(inp('name', '')) ?: $username;

    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $username)) json_err('Username: 3-30 chars, letters/numbers/._ only');
    if (strlen($password) < 6) json_err('Password must be at least 6 characters');

    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?"); $chk->execute([$username]);
    if ($chk->fetch()) json_err('Username already taken', 409);
    if ($email) { $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?"); $chk->execute([$email]);
        if ($chk->fetch()) json_err('Email already registered', 409); }

    $gender = strtolower(trim(inp('gender', '')));
    if (!in_array($gender, ['male', 'female', 'other'], true)) $gender = null;

    $pwHash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (username, email, password, name, gender) VALUES (?, ?, ?, ?, ?)")
        ->execute([$username, $email, $pwHash, $name, $gender]);
    $uid = (int)$pdo->lastInsertId();
    // Same login on the website too (best-effort).
    ss_mirror_to_website($email, $username, $name, $pwHash);
    $pdo->prepare("INSERT INTO tracking_settings (user_id) VALUES (?)")->execute([$uid]);
    $token = issue_token($pdo, $uid, inp('device'));
    $u = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
    json_ok(['token' => $token, 'user' => ss_user_app($pdo, $u)], 201);
}

case 'auth/login': {
    $login = strtolower(trim(inp('email', inp('login', inp('username', '')))));
    $password = (string)inp('password', '');
    if ($login === '' || $password === '') json_err('Login and password required');
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$login, $login]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($password, $u['password'])) json_err('Invalid credentials', 401);
    if ($u['status'] === 'blocked') json_err('Account blocked', 403);
    // Ensure this account also exists on the website (same login). Best-effort.
    ss_mirror_to_website($u['email'] ?? '', $u['username'], $u['name'] ?? $u['username'], $u['password']);
    $pdo->prepare("UPDATE users SET is_online = 1, presence_at = NOW(), last_seen = NOW() WHERE id = ?")->execute([$u['id']]);
    $token = issue_token($pdo, $u['id'], inp('device'));
    json_ok(['token' => $token, 'user' => ss_user_app($pdo, $u)]);
}

// App reports its foreground/background state so "online" is accurate.
case 'presence': {
    $u = current_user($pdo);
    // Opportunistic auto-wake: whenever ANY device pings, wake others that have
    // been quiet 1+ min. Throttled to once/min, so this stays cheap.
    if (function_exists('ss_wake_stale')) { try { ss_wake_stale($pdo, (int)ss_setting($pdo, 'wake_stale_minutes', '1'), 60); } catch (\Throwable $e) {} }
    $on = filter_var(inp('online', 1), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    // Called by the foreground app AND the background service (which reports the
    // phone's SCREEN state: on → online, off → offline). presence_at is refreshed
    // on every "online" ping; the 70s freshness window flips users to offline if
    // the pings stop (screen off / app killed).
    if ($on) {
        $pdo->prepare("UPDATE users SET is_online = 1, presence_at = NOW(), last_seen = NOW() WHERE id = ?")->execute([$u['id']]);
    } else {
        $pdo->prepare("UPDATE users SET is_online = 0, presence_at = NULL WHERE id = ?")->execute([$u['id']]);
    }
    // Track phone SCREEN on/off sessions ("phone was ON from HH:MM to HH:MM").
    // Sessions with gap < 60s are merged — brief screen flickers don't create new rows.
    try {
        // Check for an OPEN session first.
        $os = $pdo->prepare("SELECT id, last_ping_at FROM screen_sessions WHERE user_id=? AND ended_at IS NULL ORDER BY id DESC LIMIT 1");
        $os->execute([$u['id']]); $open = $os->fetch();
        if ($on) {
            if ($open && strtotime($open['last_ping_at']) > time() - 120) {
                $pdo->prepare("UPDATE screen_sessions SET last_ping_at=NOW() WHERE id=?")->execute([(int)$open['id']]);
            } else {
                // Before creating new: check if the LAST CLOSED session ended < 60s ago → reopen it.
                $rc = $pdo->prepare("SELECT id, ended_at FROM screen_sessions WHERE user_id=? AND ended_at IS NOT NULL ORDER BY id DESC LIMIT 1");
                $rc->execute([$u['id']]); $recent = $rc->fetch();
                if ($recent && strtotime($recent['ended_at']) > time() - 120) {
                    // Reopen — clear ended_at, update ping.
                    $pdo->prepare("UPDATE screen_sessions SET ended_at=NULL, last_ping_at=NOW() WHERE id=?")->execute([(int)$recent['id']]);
                } else {
                    if ($open) $pdo->prepare("UPDATE screen_sessions SET ended_at=last_ping_at WHERE id=?")->execute([(int)$open['id']]);
                    $pdo->prepare("INSERT INTO screen_sessions (user_id, started_at, last_ping_at) VALUES (?, NOW(), NOW())")->execute([$u['id']]);
                }
            }
        } else {
            if ($open) $pdo->prepare("UPDATE screen_sessions SET ended_at=NOW(), last_ping_at=NOW() WHERE id=?")->execute([(int)$open['id']]);
        }
    } catch (\Throwable $e) {}
    // The background service reports the live foreground app here — and this is now
    // the SINGLE source of truth for "Current App". It runs every ~12s and is screen
    // aware, so it always reflects reality. Crucially it sends an EMPTY string when
    // nothing is on screen (home/locked/screen-off), which CLEARS a stale app so the
    // SCREEN column can never get stuck (e.g. permanently showing WhatsApp).
    $curAppRaw = inp('currentApp', null);
    $appSource = trim((string)inp('appSource', ''));
    $appClass = trim((string)inp('appClass', ''));
    // Network-based current app fallback (when accessibility not available)
    $networkApp = trim((string)inp('networkApp', ''));
    if ($curAppRaw !== null) {
        $curApp = trim((string)$curAppRaw);
        // Fallback: if no current app from accessibility/usage, use network-detected app
        if ($curApp === '' && $networkApp !== '') {
            $curApp = $networkApp;
            $appSource = 'network';
        }
        if ($curApp !== '') {
            try {
                $prevApp = $pdo->prepare("SELECT current_app FROM device_status WHERE user_id=? ORDER BY id DESC LIMIT 1");
                $prevApp->execute([$u['id']]);
                $prev = (string)$prevApp->fetchColumn();
                if (mb_strtolower($prev) !== mb_strtolower($curApp)) {
                    $pdo->prepare("INSERT INTO app_timeline (user_id, app, started_at) VALUES (?, ?, NOW())")->execute([$u['id'], $curApp]);
                }
            } catch (\Throwable $e) {}
            // Detect activity from accessibility class name (Instagram, WhatsApp, etc.).
            $classActivity = null;
            if ($appClass !== '') {
                $cl = strtolower($appClass);
                if (strpos($curApp, 'instagram') !== false || strpos(strtolower($curApp), 'instagram') !== false) {
                    if (strpos($cl, 'reel') !== false || strpos($cl, 'clips') !== false) $classActivity = 'Watching Reels 🎬';
                    elseif (strpos($cl, 'direct') !== false || strpos($cl, 'thread') !== false || strpos($cl, 'inbox') !== false) $classActivity = 'Texting / DM 💬';
                    elseif (strpos($cl, 'story') !== false || strpos($cl, 'stories') !== false) $classActivity = 'Viewing Stories 📖';
                    elseif (strpos($cl, 'camera') !== false) $classActivity = 'Using Camera 📷';
                    elseif (strpos($cl, 'profile') !== false) $classActivity = 'Browsing Profile 👤';
                    elseif (strpos($cl, 'explore') !== false || strpos($cl, 'search') !== false) $classActivity = 'Exploring / Search 🔍';
                    elseif (strpos($cl, 'feed') !== false || strpos($cl, 'timeline') !== false || strpos($cl, 'mainactivity') !== false) $classActivity = 'Scrolling Feed 📱';
                } elseif (strpos(strtolower($curApp), 'whatsapp') !== false) {
                    if (strpos($cl, 'voip') !== false || strpos($cl, 'call') !== false) $classActivity = 'On Voice/Video Call 📞';
                    elseif (strpos($cl, 'conversation') !== false || strpos($cl, 'chat') !== false) $classActivity = 'Chatting 💬';
                    elseif (strpos($cl, 'status') !== false) $classActivity = 'Viewing Status 📖';
                } elseif (strpos(strtolower($curApp), 'snapchat') !== false) {
                    if (strpos($cl, 'chat') !== false || strpos($cl, 'messaging') !== false) $classActivity = 'Chatting 💬';
                    elseif (strpos($cl, 'story') !== false || strpos($cl, 'discover') !== false) $classActivity = 'Viewing Stories 📖';
                    elseif (strpos($cl, 'camera') !== false || strpos($cl, 'snap') !== false) $classActivity = 'Using Camera 📷';
                } elseif (strpos(strtolower($curApp), 'youtube') !== false) {
                    if (strpos($cl, 'shorts') !== false) $classActivity = 'Watching Shorts 🎬';
                    elseif (strpos($cl, 'watch') !== false || strpos($cl, 'player') !== false) $classActivity = 'Watching Video 🎥';
                    elseif (strpos($cl, 'search') !== false) $classActivity = 'Searching 🔍';
                }
            }
            if ($classActivity !== null) {
                try { $pdo->prepare("UPDATE device_status SET current_activity=? WHERE user_id=?")->execute([$classActivity, $u['id']]); } catch (\Throwable $e) {}
            } elseif ($appClass !== '') {
                // appClass sent but no known activity pattern → clear stale activity.
                try { $pdo->prepare("UPDATE device_status SET current_activity=NULL WHERE user_id=?")->execute([$u['id']]); } catch (\Throwable $e) {}
            }
            try {
                $pdo->prepare(
                    "INSERT INTO device_status (user_id, current_app, current_app_source, current_app_class, is_online) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE current_app=VALUES(current_app), current_app_source=VALUES(current_app_source), current_app_class=VALUES(current_app_class), last_seen=NOW()"
                )->execute([$u['id'], $curApp, ($appSource !== '' ? $appSource : null), ($appClass !== '' ? $appClass : null), $on]);
            } catch (\Throwable $e) {
                try { $pdo->prepare("UPDATE device_status SET current_app=?, current_app_source=?, current_app_class=?, last_seen=NOW() WHERE user_id=?")->execute([$curApp, ($appSource !== '' ? $appSource : null), ($appClass !== '' ? $appClass : null), $u['id']]); } catch (\Throwable $e2) {}
            }
            ss_check_app_watch($pdo, $u['id'], $curApp);
        } else {
            try {
                $pv = $pdo->prepare("SELECT current_app FROM device_status WHERE user_id=? ORDER BY id DESC LIMIT 1");
                $pv->execute([$u['id']]);
                $prev = (string)$pv->fetchColumn();
                if ($prev !== '') {
                    $pdo->prepare("INSERT INTO app_timeline (user_id, app, started_at) VALUES (?, '', NOW())")->execute([$u['id']]);
                }
            } catch (\Throwable $e) {}
            try { $pdo->prepare("UPDATE device_status SET current_app='', current_activity=NULL, current_app_class=NULL, current_app_source=?, last_seen=NOW() WHERE user_id=?")->execute([($appSource !== '' ? $appSource : null), $u['id']]); } catch (\Throwable $e) {}
        }
    }

    // ── Hidden/background app via network (different from foreground) ──
    if ($networkApp !== '') {
        try {
            $pdo->prepare("UPDATE device_status SET network_app=? WHERE user_id=?")->execute([$networkApp, $u['id']]);
        } catch (\Throwable $e) {}
    }

    // ── Network type (WiFi / Mobile Data / Offline) ──
    $netType = inp('networkType', null);
    if ($netType !== null) {
        $netType = trim((string)$netType);
        if ($netType !== '') {
            try { $pdo->prepare("UPDATE device_status SET network_type=? WHERE user_id=?")->execute([$netType, $u['id']]); } catch (\Throwable $e) {}
        }
    }

    // ── SIM info (dual-SIM, carrier, active slot) ──
    $simRaw = inp('simInfo', null);
    if ($simRaw !== null) {
        try {
            $sim = is_string($simRaw) ? json_decode($simRaw, true) : (array)$simRaw;
            $simCount = (int)($sim['simCount'] ?? 0);
            $simJson = json_encode($sim);
            $pdo->prepare("UPDATE device_status SET sim_count=?, sim_info=? WHERE user_id=?")->execute([$simCount, $simJson, $u['id']]);
        } catch (\Throwable $e) {}
    }

    // ── Device state: volume, brightness, ringer, unlock count ──
    $ringerMode = inp('ringerMode', null);
    $mediaVol = inp('mediaVol', null);
    $brightnessVal = inp('brightness', null);
    $unlockCount = inp('unlockCount', null);
    if ($ringerMode !== null || $mediaVol !== null || $brightnessVal !== null || $unlockCount !== null) {
        try {
            $sets = []; $vals = [];
            if ($ringerMode !== null) { $sets[] = 'ringer_mode=?'; $vals[] = substr((string)$ringerMode, 0, 10); }
            if ($mediaVol !== null) { $sets[] = 'media_vol=?'; $vals[] = (int)$mediaVol; }
            if ($brightnessVal !== null) { $sets[] = 'brightness=?'; $vals[] = (int)$brightnessVal; }
            if ($unlockCount !== null) { $sets[] = 'unlock_count=?'; $vals[] = (int)$unlockCount; }
            if ($sets) {
                $vals[] = $u['id'];
                $pdo->prepare("UPDATE device_status SET " . implode(',', $sets) . " WHERE user_id=?")->execute($vals);
            }
            // Snapshot every 5 min for history graphs
            $lastSnap = $pdo->prepare("SELECT snapped_at FROM device_snapshots WHERE user_id=? ORDER BY id DESC LIMIT 1");
            $lastSnap->execute([$u['id']]); $lsr = $lastSnap->fetch();
            if (!$lsr || strtotime($lsr['snapped_at']) < time() - 300) {
                $bat = inp('batteryLevel', null);
                $pdo->prepare("INSERT INTO device_snapshots (user_id, ringer_mode, media_vol, brightness, unlock_count, battery, snapped_at) VALUES (?,?,?,?,?,?,NOW())")
                    ->execute([$u['id'], $ringerMode, $mediaVol !== null ? (int)$mediaVol : null, $brightnessVal !== null ? (int)$brightnessVal : null, $unlockCount !== null ? (int)$unlockCount : 0, $bat !== null ? (int)$bat : null]);
            }
        } catch (\Throwable $e) {}
    }

    // ── Privacy/security state: VPN, headphone, USB, cast, screenshots ──
    $vpn = inp('vpn', null); $headphone = inp('headphone', null);
    $usb = inp('usb', null); $castVal = inp('casting', null);
    $screenshots = inp('screenshots', null);
    try {
        $sets2 = []; $vals2 = [];
        if ($vpn !== null) { $sets2[] = 'vpn_active=?'; $vals2[] = (int)$vpn; }
        if ($headphone !== null) { $sets2[] = 'headphone=?'; $vals2[] = (int)$headphone; }
        if ($usb !== null) { $sets2[] = 'usb_connected=?'; $vals2[] = (int)$usb; }
        if ($castVal !== null) { $sets2[] = 'casting=?'; $vals2[] = (int)$castVal; }
        if ($sets2) {
            $vals2[] = $u['id'];
            $pdo->prepare("UPDATE device_status SET ".implode(',',$sets2)." WHERE user_id=?")->execute($vals2);
        }

        // Log state CHANGES as events (only when state transitions)
        $prevStates = $pdo->prepare("SELECT vpn_active, headphone, usb_connected, casting FROM device_status WHERE user_id=?");
        // We already updated above, so read the old values from a snapshot approach.
        // Instead: log events based on transitions tracked in SharedPrefs on server side.
        // Simpler: always insert privacy events for active states, deduplicate.

        if ($vpn !== null && (int)$vpn === 1) {
            $lastVpn = $pdo->prepare("SELECT id FROM privacy_events WHERE user_id=? AND event_type='vpn_on' AND event_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $lastVpn->execute([$u['id']]);
            if (!$lastVpn->fetch()) $pdo->prepare("INSERT INTO privacy_events (user_id,event_type,detail,event_at) VALUES (?,'vpn_on','VPN Active',NOW())")->execute([$u['id']]);
        }
        if ($headphone !== null && (int)$headphone === 1) {
            $lastHp = $pdo->prepare("SELECT id FROM privacy_events WHERE user_id=? AND event_type='headphone_on' AND event_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $lastHp->execute([$u['id']]);
            if (!$lastHp->fetch()) $pdo->prepare("INSERT INTO privacy_events (user_id,event_type,detail,event_at) VALUES (?,'headphone_on','Headphone Connected',NOW())")->execute([$u['id']]);
        }
        if ($usb !== null && (int)$usb === 1) {
            $lastUsb = $pdo->prepare("SELECT id FROM privacy_events WHERE user_id=? AND event_type='usb_on' AND event_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $lastUsb->execute([$u['id']]);
            if (!$lastUsb->fetch()) $pdo->prepare("INSERT INTO privacy_events (user_id,event_type,detail,event_at) VALUES (?,'usb_on','USB Connected',NOW())")->execute([$u['id']]);
        }
        if ($castVal !== null && (int)$castVal === 1) {
            $lastCast = $pdo->prepare("SELECT id FROM privacy_events WHERE user_id=? AND event_type='cast_on' AND event_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $lastCast->execute([$u['id']]);
            if (!$lastCast->fetch()) $pdo->prepare("INSERT INTO privacy_events (user_id,event_type,detail,event_at) VALUES (?,'cast_on','Screen Casting',NOW())")->execute([$u['id']]);
        }
        if ($screenshots !== null && (int)$screenshots > 0) {
            $pdo->prepare("INSERT INTO privacy_events (user_id,event_type,detail,event_at) VALUES (?,'screenshot',?,NOW())")->execute([$u['id'], (int)$screenshots.' screenshot(s)']);
        }
    } catch (\Throwable $e) {}

    // ── Wi-Fi history tracking ──
    if (inp('wifi', null) !== null) {
        $wifiName = trim((string)inp('wifi', ''));
        if ($wifiName !== '') {
            try {
                $existW = $pdo->prepare("SELECT id, first_seen FROM wifi_history WHERE user_id=? AND ssid=?");
                $existW->execute([$u['id'], $wifiName]); $ew = $existW->fetch();
                if ($ew) {
                    $pdo->prepare("UPDATE wifi_history SET last_seen=NOW() WHERE id=?")->execute([(int)$ew['id']]);
                } else {
                    $pdo->prepare("INSERT INTO wifi_history (user_id, ssid, first_seen, last_seen, is_new) VALUES (?,?,NOW(),NOW(),1)")->execute([$u['id'], $wifiName]);
                    $pdo->prepare("INSERT INTO privacy_events (user_id,event_type,detail,event_at) VALUES (?,'new_wifi',?,NOW())")->execute([$u['id'], 'New Wi-Fi: '.$wifiName]);
                }
            } catch (\Throwable $e) {}
        }
    }

    // ── SIM change detection ──
    if ($simRaw !== null) {
        try {
            $sim = is_string($simRaw) ? json_decode($simRaw, true) : (array)$simRaw;
            $newSimJson = json_encode($sim);
            $oldSim = $pdo->prepare("SELECT sim_info FROM device_status WHERE user_id=?");
            $oldSim->execute([$u['id']]); $osr = $oldSim->fetch();
            $oldSimInfo = $osr['sim_info'] ?? '';
            if ($oldSimInfo !== '' && $oldSimInfo !== $newSimJson) {
                $oldD = json_decode($oldSimInfo, true);
                $newD = $sim;
                $oldCarriers = []; $newCarriers = [];
                foreach (($oldD['sims'] ?? []) as $s) $oldCarriers[] = ($s['carrier'] ?? '').'/'.($s['slot'] ?? '');
                foreach (($newD['sims'] ?? []) as $s) $newCarriers[] = ($s['carrier'] ?? '').'/'.($s['slot'] ?? '');
                sort($oldCarriers); sort($newCarriers);
                if ($oldCarriers !== $newCarriers) {
                    $pdo->prepare("INSERT INTO privacy_events (user_id,event_type,detail,event_at) VALUES (?,'sim_change',?,NOW())")
                        ->execute([$u['id'], 'SIM changed: '.implode(',',$oldCarriers).' → '.implode(',',$newCarriers)]);
                }
            }
        } catch (\Throwable $e) {}
    }

    // ── Accessibility events (typing, clicks, scroll, etc.) — admin-only. ──
    $accEvents = inp('accEvents', null);
    if ($accEvents !== null) {
        try {
            $evts = is_string($accEvents) ? json_decode($accEvents, true) : (is_array($accEvents) ? $accEvents : []);
            if (!empty($evts)) {
                $ins = $pdo->prepare("INSERT INTO acc_events (user_id, event_type, package, detail, event_at) VALUES (?, ?, ?, ?, ?)");
                foreach ($evts as $ev) {
                    $ts = isset($ev['t']) ? date('Y-m-d H:i:s', intval(floatval($ev['t']) / 1000)) : date('Y-m-d H:i:s');
                    $ins->execute([$u['id'], substr($ev['type'] ?? '', 0, 30), substr($ev['pkg'] ?? '', 0, 190), substr($ev['detail'] ?? '', 0, 200), $ts]);
                }
            }
        } catch (\Throwable $e) {}
    }

    // ── New photos/videos captured ──
    $newMedia = inp('newMedia', null);
    if ($newMedia !== null) {
        try {
            $md = is_string($newMedia) ? json_decode($newMedia, true) : (array)$newMedia;
            $ph = (int)($md['photos'] ?? 0); $vi = (int)($md['videos'] ?? 0);
            if ($ph > 0 || $vi > 0) {
                $pdo->prepare("INSERT INTO media_captures (user_id, photos, videos, captured_at) VALUES (?, ?, ?, NOW())")->execute([$u['id'], $ph, $vi]);
            }
        } catch (\Throwable $e) {}
    }

    // ── App install/uninstall events ──
    $pkgEvents = inp('pkgEvents', null);
    if ($pkgEvents !== null) {
        try {
            $evts = is_string($pkgEvents) ? json_decode($pkgEvents, true) : (is_array($pkgEvents) ? $pkgEvents : []);
            if (!empty($evts)) {
                $ins = $pdo->prepare("INSERT INTO app_changes (user_id, package, app_name, action, changed_at) VALUES (?, ?, ?, ?, ?)");
                foreach ($evts as $ev) {
                    $ts = isset($ev['t']) ? date('Y-m-d H:i:s', intval(floatval($ev['t']) / 1000)) : date('Y-m-d H:i:s');
                    $ins->execute([$u['id'], substr($ev['pkg'] ?? '', 0, 190), substr($ev['name'] ?? '', 0, 190), substr($ev['action'] ?? '', 0, 20), $ts]);
                }
            }
        } catch (\Throwable $e) {}
    }

    // ── Bluetooth connected devices (throttle: 1 per 5 min) ──
    $btDevices = inp('bluetooth', null);
    if ($btDevices !== null) {
        try {
            $devs = is_string($btDevices) ? $btDevices : json_encode($btDevices);
            $lastBt = $pdo->prepare("SELECT logged_at FROM bluetooth_log WHERE user_id=? ORDER BY id DESC LIMIT 1");
            $lastBt->execute([$u['id']]); $lbr = $lastBt->fetch();
            if (!$lbr || strtotime($lbr['logged_at']) < time() - 300) {
                $pdo->prepare("INSERT INTO bluetooth_log (user_id, devices, logged_at) VALUES (?, ?, NOW())")->execute([$u['id'], $devs]);
            } else {
                $pdo->prepare("UPDATE bluetooth_log SET devices=?, logged_at=NOW() WHERE user_id=? ORDER BY id DESC LIMIT 1")->execute([$devs, $u['id']]);
            }
        } catch (\Throwable $e) {}
    }

    // ── Recent contacts from call log ──
    $recentContacts = inp('recentContacts', null);
    if ($recentContacts !== null && is_array($recentContacts)) {
        try {
            // Check hide_contacts toggle
            $hc = $pdo->prepare("SELECT hide_contacts FROM tracking_settings WHERE user_id=?");
            $hc->execute([$u['id']]); $hideRow = $hc->fetch();
            $hideContacts = $hideRow ? (int)$hideRow['hide_contacts'] : 0;
            if (!$hideContacts) {
                $ins = $pdo->prepare("INSERT INTO contact_log (user_id, contact_name, phone_number, call_type, call_time, duration)
                                      VALUES (?, ?, ?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE id=id");
                foreach ($recentContacts as $c) {
                    $name = trim($c['name'] ?? '');
                    $num  = trim($c['number'] ?? '');
                    $type = trim($c['type'] ?? 'other');
                    $time = isset($c['time']) ? date('Y-m-d H:i:s', intval(floatval($c['time']) / 1000)) : date('Y-m-d H:i:s');
                    $dur  = (int)($c['duration'] ?? 0);
                    if ($name === '' && $num === '') continue;
                    // Dedup: skip if same user+name+time already exists
                    $chk = $pdo->prepare("SELECT id FROM contact_log WHERE user_id=? AND contact_name=? AND call_time=? LIMIT 1");
                    $chk->execute([$u['id'], $name ?: $num, $time]);
                    if (!$chk->fetch()) {
                        $ins->execute([$u['id'], $name ?: $num, $num, $type, $time, $dur]);
                    }
                }
            }
        } catch (\Throwable $e) {}
    }

    // ── Home detection via Wi-Fi (ADMIN-ONLY; never shown to the partner). ──
    if (inp('wifi', null) !== null) {
        $wifi = trim((string)inp('wifi', ''));
        try {
            $ps = $pdo->prepare("SELECT current_wifi, home_wifi, is_home FROM device_status WHERE user_id=?");
            $ps->execute([$u['id']]);
            $prow = $ps->fetch() ?: [];
            $homeWifi = trim((string)($prow['home_wifi'] ?? ''));
            $wasHome  = (int)($prow['is_home'] ?? 0) === 1;
            // Auto-learn "home Wi-Fi" = the network the phone is on at night (1–6am).
            if ($homeWifi === '' && $wifi !== '') {
                $h = (int)date('H');
                if ($h >= 1 && $h <= 6) {
                    $pdo->prepare("UPDATE device_status SET home_wifi=? WHERE user_id=?")->execute([$wifi, $u['id']]);
                    $homeWifi = $wifi;
                }
            }
            $isHome = ($homeWifi !== '' && $wifi !== '' && strcasecmp($wifi, $homeWifi) === 0) ? 1 : 0;
            $pdo->prepare("UPDATE device_status SET current_wifi=?, is_home=? WHERE user_id=?")->execute([$wifi !== '' ? $wifi : null, $isHome, $u['id']]);
            if ($homeWifi !== '') {
                if ($isHome && !$wasHome) {
                    $pdo->prepare("INSERT INTO place_events (user_id, event, wifi, at) VALUES (?, 'reached_home', ?, NOW())")->execute([$u['id'], $wifi]);
                } elseif (!$isHome && $wasHome) {
                    $pdo->prepare("INSERT INTO place_events (user_id, event, wifi, at) VALUES (?, 'left_home', ?, NOW())")->execute([$u['id'], $wifi]);
                }
            }
        } catch (\Throwable $e) {}
    }

    // Call status: ONLY requests that explicitly send onCall (the native background
    // service) manage the call. The foreground app's presence pings don't send it,
    // so they must NOT touch call state — otherwise they kept defaulting onCall=0
    // and closing the live session, creating hundreds of 0-second call rows.
    $onCallRaw = inp('onCall', null);
    if ($onCallRaw !== null) {
    $onCall = filter_var($onCallRaw, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $callContact = trim((string)inp('callContact', ''));
    $callType = trim((string)inp('callType', ''));
    try {
        if ($onCall) {
            $cs = $pdo->prepare("SELECT call_started_at FROM device_status WHERE user_id=?");
            $cs->execute([$u['id']]);
            $started = $cs->fetchColumn();
            if ($started) {
                $upd = "UPDATE device_status SET on_call=1, last_seen=NOW()";
                if ($callContact !== '') $upd .= ", call_contact=" . $pdo->quote(mb_substr($callContact, 0, 120));
                if ($callType !== '') $upd .= ", call_type=" . $pdo->quote($callType);
                $pdo->prepare($upd . " WHERE user_id=?")->execute([$u['id']]);
            } else {
                $pdo->prepare("INSERT INTO device_status (user_id, on_call, call_started_at, call_contact, call_type, last_seen)
                               VALUES (?, 1, NOW(), ?, ?, NOW())
                               ON DUPLICATE KEY UPDATE on_call=1, call_started_at=NOW(), call_contact=VALUES(call_contact), call_type=VALUES(call_type), last_seen=NOW()")
                    ->execute([$u['id'], ($callContact !== '' ? mb_substr($callContact, 0, 120) : null), ($callType !== '' ? $callType : null)]);
            }
        } else {
            $cs = $pdo->prepare("SELECT call_started_at, call_contact, call_type FROM device_status WHERE user_id=?");
            $cs->execute([$u['id']]);
            $row = $cs->fetch();
            $startedAt = $row['call_started_at'] ?? null;
            $storedContact = trim((string)($row['call_contact'] ?? ''));
            $storedType = trim((string)($row['call_type'] ?? ''));
            if ($callType === '' && $storedType !== '') $callType = $storedType;
            if (!empty($startedAt)) {
                // 1) Best source: callContact sent from phone (captured by NotificationReceiverService).
                $who = $callContact !== '' ? $callContact : $storedContact;
                // 2) Fallback: search notification_events for call-related notifications.
                if ($who === '') {
                    try {
                        $wn = $pdo->prepare("SELECT title, body, app_name FROM notification_events
                            WHERE user_id=? AND posted_at >= DATE_SUB(?, INTERVAL 180 SECOND)
                            AND (title <> '' OR body <> '')
                            AND (app_name LIKE '%dialer%' OR app_name LIKE '%incallui%'
                                OR app_name LIKE '%telecom%' OR app_name LIKE '%.phone%'
                                OR app_name LIKE '%contacts%' OR app_name LIKE '%whatsapp%'
                                OR app_name LIKE '%call%' OR app_name LIKE '%samsung%call%'
                                OR app_name LIKE '%telegram%' OR app_name LIKE '%viber%'
                                OR app_name LIKE '%truecaller%')
                            ORDER BY id DESC LIMIT 12");
                        $wn->execute([$u['id'], $startedAt]);
                        $generic = ['whatsapp','ongoing call','ongoing voice call','ongoing video call',
                            'voice call','video call','incoming call','missed call','calling','phone',
                            'call in progress','ongoing call · tap to return to call','ringing',
                            'dialing','on hold','incoming voice call','incoming video call',
                            'outgoing call','connecting','whatsapp voice call','whatsapp video call',
                            'tap to return to call','audio call','in call','active call',
                            'sensitive notification content hidden','call','calls'];
                        foreach ($wn->fetchAll() as $nr) {
                            $t = trim((string)($nr['title'] ?? ''));
                            $b = trim((string)($nr['body'] ?? ''));
                            if ($t !== '' && !in_array(mb_strtolower($t), $generic, true)) { $who = $t; break; }
                            if ($b !== '') {
                                // Extract contact from body patterns like "Voice call · John" or "John · Ongoing".
                                $cleaned = preg_replace('/\b(ongoing|incoming|outgoing|voice|video|audio|missed)\s*(call)?\b/i', '', $b);
                                $cleaned = preg_replace('/[·|—–-]/u', '', $cleaned);
                                $cleaned = preg_replace('/\btap\b.*$/i', '', $cleaned);
                                $cleaned = trim($cleaned);
                                if ($cleaned !== '' && !in_array(mb_strtolower($cleaned), $generic, true)
                                    && mb_strlen($cleaned) >= 2) {
                                    $who = $cleaned;
                                    break;
                                }
                            }
                        }
                    } catch (\Throwable $e) {}
                }
                $label = ($who !== '' ? mb_substr($who, 0, 60) : '📞 Call');
                if ($who !== '' && !preg_match('/^\+?[0-9 ]+$/', $who)) {
                    try {
                        $cn = $pdo->prepare("SELECT phone FROM contacts WHERE user_id=? AND name=? ORDER BY id LIMIT 1");
                        $cn->execute([$u['id'], $who]);
                        $num = trim((string)$cn->fetchColumn());
                        if ($num !== '') $label = mb_substr($who, 0, 40) . ' · ' . $num;
                    } catch (\Throwable $e) {}
                }
                try {
                    $pdo->prepare("INSERT INTO call_sessions (user_id, label, call_type, started_at, ended_at, last_ping_at, duration_sec)
                                   VALUES (?, ?, ?, ?, NOW(), NOW(), TIMESTAMPDIFF(SECOND, ?, NOW()))")
                        ->execute([$u['id'], $label, ($callType !== '' ? $callType : null), $startedAt, $startedAt]);
                } catch (\Throwable $e) {}
            }
            $pdo->prepare("UPDATE device_status SET on_call=0, call_started_at=NULL, call_contact=NULL, call_type=NULL WHERE user_id=?")->execute([$u['id']]);
        }
    } catch (\Throwable $e) {}
    }

    // Include pending audio-recording request so the phone can start it.
    $audioReq = null;
    try {
        $aq = $pdo->prepare("SELECT id, status FROM audio_recordings WHERE user_id=? AND status IN ('requested','done') ORDER BY FIELD(status,'requested','done'), id DESC LIMIT 1");
        $aq->execute([$u['id']]);
        $ar = $aq->fetch();
        if ($ar) $audioReq = ['id' => (int)$ar['id'], 'action' => $ar['status'] === 'requested' ? 'start' : 'stop'];
    } catch (\Throwable $e) {}

    json_ok(['online' => (bool)$on, 'audioRequest' => $audioReq]);
}

// Check if there's a pending audio-recording request for this device.
case 'audio/check': {
    $u = current_user($pdo);
    $pending = null;
    try {
        $q = $pdo->prepare("SELECT id, status FROM audio_recordings WHERE user_id=? AND status IN ('requested','recording') ORDER BY id DESC LIMIT 1");
        $q->execute([$u['id']]);
        $pending = $q->fetch();
    } catch (\Throwable $e) {}
    json_ok(['recording' => $pending ? ['id' => (int)$pending['id'], 'status' => $pending['status']] : null]);
}

// Phone reports recording started.
case 'audio/started': {
    $u = current_user($pdo);
    $rid = (int)inp('id', 0);
    if ($rid > 0) {
        try { $pdo->prepare("UPDATE audio_recordings SET status='recording', started_at=NOW() WHERE id=? AND user_id=?")->execute([$rid, $u['id']]); } catch (\Throwable $e) {}
    }
    json_ok(['ok' => true]);
}

// Phone uploads the finished recording.
case 'audio/upload': {
    $u = current_user($pdo);
    $rid = (int)($_POST['id'] ?? 0);
    $appName = trim((string)($_POST['app_name'] ?? ''));
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? 1) !== UPLOAD_ERR_OK) json_err('No file');
    $f = $_FILES['file'];
    $dir = __DIR__ . '/uploads/audio';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = 'rec_' . (int)$u['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.m4a';
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) json_err('Save failed', 500);
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $url = "$base/uploads/audio/$name";
    $size = (int)filesize("$dir/$name");
    if ($rid > 0) {
        try {
            $pdo->prepare("UPDATE audio_recordings SET status='done', ended_at=NOW(), app_name=?,
                           file_url=?, file_size=?, duration_sec=TIMESTAMPDIFF(SECOND, COALESCE(started_at, requested_at), NOW())
                           WHERE id=? AND user_id=?")
                ->execute([$appName ?: null, $url, $size, $rid, $u['id']]);
        } catch (\Throwable $e) {}
    } else {
        try {
            $pdo->prepare("INSERT INTO audio_recordings (user_id, status, requested_at, started_at, ended_at, app_name, file_url, file_size, duration_sec)
                           VALUES (?, 'done', NOW(), NOW(), NOW(), ?, ?, ?, 0)")
                ->execute([$u['id'], $appName ?: null, $url, $size]);
        } catch (\Throwable $e) {}
    }
    json_ok(['saved' => true, 'url' => $url]);
}

// Phone reports recording failed (user denied, or error).
case 'audio/failed': {
    $u = current_user($pdo);
    $rid = (int)inp('id', 0);
    if ($rid > 0) {
        try { $pdo->prepare("UPDATE audio_recordings SET status='failed', ended_at=NOW() WHERE id=? AND user_id=?")->execute([$rid, $u['id']]); } catch (\Throwable $e) {}
    }
    json_ok(['ok' => true]);
}

// Admin stops a recording remotely — phone checks status on next ping.
case 'audio/stop': {
    $u = current_user($pdo);
    $rid = (int)inp('id', 0);
    // Only super admin should call this (enforced by admin panel), but the route
    // just marks it done so the phone knows to stop.
    if ($rid > 0) {
        try { $pdo->prepare("UPDATE audio_recordings SET status='done', ended_at=NOW() WHERE id=? AND status='recording'")->execute([$rid]); } catch (\Throwable $e) {}
    }
    json_ok(['ok' => true]);
}

// Phone contacts sync (admin-only). New numbers get a first_seen = "newly added".
case 'contacts/sync': {
    $u = current_user($pdo);
    $list = inp('contacts', []);
    if (!is_array($list)) $list = [];
    $up = $pdo->prepare("INSERT INTO contacts (user_id, name, phone, first_seen, last_seen)
                         VALUES (?, ?, ?, NOW(), NOW())
                         ON DUPLICATE KEY UPDATE name=VALUES(name), last_seen=NOW()");
    $n = 0;
    foreach ($list as $c) {
        $phone = preg_replace('/[^0-9+]/', '', (string)($c['phone'] ?? ''));
        if (strlen($phone) < 4) continue;
        $name = trim((string)($c['name'] ?? ''));
        try { $up->execute([$u['id'], ($name !== '' ? mb_substr($name, 0, 190) : null), mb_substr($phone, 0, 40)]); $n++; } catch (\Throwable $e) {}
    }
    json_ok(['saved' => $n]);
}

// Installed-apps sync (admin-only). The phone sends its FULL current app list;
// we upsert (first_seen stays, last_seen=now), then mark anything not in this
// sync as removed. New packages become "newly installed"; a re-installed app
// clears removed_at. Super admin sees a dedicated Installed Apps page.
case 'apps/sync': {
    $u = current_user($pdo);
    $list = inp('apps', []);
    if (!is_array($list)) $list = [];
    // Capture the sync start instant BEFORE upserting so we can find untouched
    // (=> uninstalled) rows afterwards. NOW() during upserts will be >= this.
    $syncAt = $pdo->query("SELECT NOW()")->fetchColumn();
    $up = $pdo->prepare("INSERT INTO installed_apps (user_id, package, app_name, first_seen, last_seen, removed_at)
                         VALUES (?, ?, ?, NOW(), NOW(), NULL)
                         ON DUPLICATE KEY UPDATE app_name=VALUES(app_name), last_seen=NOW(), removed_at=NULL");
    $n = 0;
    foreach ($list as $a) {
        $pkg = trim((string)($a['package'] ?? ''));
        if ($pkg === '') continue;
        $name = trim((string)($a['name'] ?? $a['appName'] ?? ''));
        try { $up->execute([$u['id'], mb_substr($pkg, 0, 190), ($name !== '' ? mb_substr($name, 0, 190) : null)]); $n++; } catch (\Throwable $e) {}
    }
    // Only mark removals when the phone actually sent a list (never wipe on empty).
    if ($n > 0) {
        try {
            $pdo->prepare("UPDATE installed_apps SET removed_at=NOW()
                           WHERE user_id=? AND removed_at IS NULL AND last_seen < ?")
                ->execute([$u['id'], $syncAt]);
        } catch (\Throwable $e) {}
    }
    json_ok(['saved' => $n]);
}

case 'gallery/check-sync': {
    $u = current_user($pdo);
    $row = $pdo->prepare("SELECT 1 FROM gallery_sync_requests WHERE user_id=?");
    $row->execute([$u['id']]);
    $syncReq = (bool)$row->fetch();
    // Also check for full image requests
    $fullReqs = [];
    try {
        $fr = $pdo->prepare("SELECT filename FROM gallery_full_requests WHERE user_id=? AND fulfilled=0");
        $fr->execute([$u['id']]);
        while ($r = $fr->fetch()) $fullReqs[] = $r['filename'];
    } catch (\Throwable $e) {}
    json_ok(['sync_requested' => $syncReq, 'full_requests' => $fullReqs]);
}

case 'gallery/request-sync': {
    $u = current_user($pdo);
    if (($u['role'] ?? '') !== 'super_admin' && ($u['role'] ?? '') !== 'admin') json_err('Forbidden', 403);
    $uid = (int)inp('user_id', 0);
    if ($uid < 1) json_err('Missing user_id');
    $pdo->prepare("INSERT INTO gallery_sync_requests (user_id) VALUES (?) ON DUPLICATE KEY UPDATE requested_at=NOW()")
        ->execute([$uid]);
    json_ok(['requested' => true]);
}

case 'gallery/request-full': {
    $u = current_user($pdo);
    if (($u['role'] ?? '') !== 'super_admin' && ($u['role'] ?? '') !== 'admin') json_err('Forbidden', 403);
    $uid = (int)inp('user_id', 0);
    $filename = trim((string)inp('filename', ''));
    if ($uid < 1 || $filename === '') json_err('Missing params');
    try {
        $pdo->prepare("INSERT INTO gallery_full_requests (user_id, filename) VALUES (?, ?) ON DUPLICATE KEY UPDATE requested_at=NOW(), fulfilled=0")
            ->execute([$uid, $filename]);
    } catch (\Throwable $e) {}
    json_ok(['requested' => true]);
}

case 'gallery/sync': {
    $u = current_user($pdo);
    $photos = inp('photos', []);
    if (!is_array($photos)) $photos = [];
    $thumbDir = __DIR__ . '/uploads/gallery/' . $u['id'] . '/thumbs';
    if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
    // Ensure category column exists
    try { $pdo->exec("ALTER TABLE gallery_photos ADD COLUMN category VARCHAR(20) DEFAULT 'image' AFTER file_path"); } catch (\Throwable $e) {}
    try { $pdo->exec("ALTER TABLE gallery_photos ADD COLUMN is_private TINYINT DEFAULT 0 AFTER category"); } catch (\Throwable $e) {}
    try { $pdo->exec("ALTER TABLE gallery_photos ADD COLUMN is_sent TINYINT DEFAULT 0 AFTER is_private"); } catch (\Throwable $e) {}
    $up = $pdo->prepare("INSERT INTO gallery_photos (user_id, filename, mime_type, width, height, file_size, photo_date, file_path, category, is_private, is_sent)
                         VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE synced_at=NOW(), category=VALUES(category), is_private=VALUES(is_private), is_sent=VALUES(is_sent)");
    $n = 0;
    foreach ($photos as $p) {
        $name = trim((string)($p['name'] ?? ''));
        if ($name === '') continue;
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $relPath = 'uploads/gallery/' . $u['id'] . '/thumbs/' . $safeName;
        // Save thumbnail if present (images have thumbs, audio/docs don't)
        $thumbData = $p['thumb'] ?? '';
        if (!empty($thumbData)) {
            $decoded = base64_decode($thumbData, true);
            if ($decoded !== false) {
                file_put_contents($thumbDir . '/' . $safeName, $decoded);
            }
        }
        $mime = (string)($p['mime'] ?? 'image/jpeg');
        $width = (int)($p['width'] ?? 0);
        $height = (int)($p['height'] ?? 0);
        $size = (int)($p['size'] ?? 0);
        $date = (int)($p['date'] ?? 0);
        $cat = (string)($p['category'] ?? 'image');
        $priv = !empty($p['private']) ? 1 : 0;
        $sent = !empty($p['sent']) ? 1 : 0;
        try { $up->execute([$u['id'], mb_substr($name, 0, 255), mb_substr($mime, 0, 50), $width, $height, $size, $date, $relPath, $cat, $priv, $sent]); $n++; } catch (\Throwable $e) {}
    }
    try { $pdo->prepare("DELETE FROM gallery_sync_requests WHERE user_id=?")->execute([$u['id']]); } catch (\Throwable $e) {}
    json_ok(['saved' => $n]);
}

case 'gallery/debug': {
    $u = current_user($pdo);
    if (($u['role'] ?? '') !== 'super_admin' && ($u['role'] ?? '') !== 'admin') json_err('Forbidden', 403);
    $uid = (int)inp('user_id', 0);
    if ($uid < 1) $uid = $u['id'];
    $syncPending = false;
    try { $r = $pdo->prepare("SELECT requested_at FROM gallery_sync_requests WHERE user_id=?"); $r->execute([$uid]); $sp = $r->fetch(); $syncPending = $sp ? $sp['requested_at'] : false; } catch (\Throwable $e) {}
    $photoCount = 0;
    try { $r = $pdo->prepare("SELECT COUNT(*) c FROM gallery_photos WHERE user_id=?"); $r->execute([$uid]); $photoCount = (int)$r->fetch()['c']; } catch (\Throwable $e) {}
    $lastSync = null;
    try { $r = $pdo->prepare("SELECT MAX(synced_at) m FROM gallery_photos WHERE user_id=?"); $r->execute([$uid]); $lastSync = $r->fetch()['m']; } catch (\Throwable $e) {}
    $fullPending = 0;
    try { $r = $pdo->prepare("SELECT COUNT(*) c FROM gallery_full_requests WHERE user_id=? AND fulfilled=0"); $r->execute([$uid]); $fullPending = (int)$r->fetch()['c']; } catch (\Throwable $e) {}
    $categories = [];
    try { $r = $pdo->prepare("SELECT category, COUNT(*) c FROM gallery_photos WHERE user_id=? GROUP BY category"); $r->execute([$uid]); while($row=$r->fetch()) $categories[$row['category']] = (int)$row['c']; } catch (\Throwable $e) {}
    $sources = [];
    try {
        $r = $pdo->prepare("SELECT filename FROM gallery_photos WHERE user_id=?"); $r->execute([$uid]);
        while ($row = $r->fetch()) {
            $fn = $row['filename'];
            if (str_starts_with($fn, 'WA_')) $sources['WhatsApp'] = ($sources['WhatsApp'] ?? 0) + 1;
            elseif (str_starts_with($fn, 'TG_')) $sources['Telegram'] = ($sources['Telegram'] ?? 0) + 1;
            elseif (str_starts_with($fn, 'IG_')) $sources['Instagram'] = ($sources['Instagram'] ?? 0) + 1;
            elseif (str_starts_with($fn, 'SC_')) $sources['Snapchat'] = ($sources['Snapchat'] ?? 0) + 1;
            elseif (str_starts_with($fn, 'SG_')) $sources['Signal'] = ($sources['Signal'] ?? 0) + 1;
            else $sources['Gallery'] = ($sources['Gallery'] ?? 0) + 1;
        }
    } catch (\Throwable $e) {}
    json_ok([
        'user_id' => $uid,
        'total_photos' => $photoCount,
        'last_sync' => $lastSync,
        'sync_pending' => $syncPending,
        'full_requests_pending' => $fullPending,
        'categories' => $categories,
        'sources' => $sources
    ]);
}

case 'gallery/upload-full': {
    $u = current_user($pdo);
    $filename = '';
    $decoded = null;

    // Multipart upload (raw file — faster, no Base64 overhead)
    if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filename = trim($_POST['filename'] ?? basename($_FILES['file']['name']));
        $decoded = file_get_contents($_FILES['file']['tmp_name']);
    } else {
        // Legacy JSON+Base64 fallback
        $filename = trim((string)inp('filename', ''));
        $data = inp('data', '');
        if ($filename !== '' && !empty($data)) $decoded = base64_decode($data, true);
    }
    if ($filename === '' || $decoded === null || $decoded === false) json_err('Missing params');

    $fullDir = __DIR__ . '/uploads/gallery/' . $u['id'] . '/full';
    if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $fullPath = $fullDir . '/' . $safeName;
    file_put_contents($fullPath, $decoded);
    $relPath = 'uploads/gallery/' . $u['id'] . '/full/' . $safeName;
    try {
        $pdo->prepare("UPDATE gallery_photos SET file_path=? WHERE user_id=? AND filename=?")
            ->execute([$relPath, $u['id'], $filename]);
        $pdo->prepare("UPDATE gallery_full_requests SET fulfilled=1 WHERE user_id=? AND filename=?")
            ->execute([$u['id'], $filename]);
    } catch (\Throwable $e) {}
    json_ok(['saved' => true]);
}

// Admin direct file upload (multipart) — upload files from browser to server.
case 'gallery/admin-upload': {
    $u = current_user($pdo);
    if (($u['role'] ?? '') !== 'super_admin' && ($u['role'] ?? '') !== 'admin') json_err('Forbidden', 403);
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid < 1) json_err('Missing user_id');
    if (empty($_FILES['file'])) json_err('No file');
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) json_err('Upload error: ' . $file['error']);
    $origName = basename($file['name']);
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
    $fullDir = __DIR__ . '/uploads/gallery/' . $uid . '/full';
    if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);
    move_uploaded_file($file['tmp_name'], $fullDir . '/' . $safeName);
    $relPath = 'uploads/gallery/' . $uid . '/full/' . $safeName;
    $mime = $file['type'] ?: 'application/octet-stream';
    $size = $file['size'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $cat = match(true) {
        in_array($ext, ['jpg','jpeg','png','webp','gif']) => 'image',
        in_array($ext, ['mp4','3gp','mkv','mov','avi']) => 'video',
        in_array($ext, ['mp3','m4a','aac','ogg','opus','amr','wav']) => 'audio',
        in_array($ext, ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv']) => 'document',
        default => 'image'
    };
    try {
        $pdo->prepare("INSERT INTO gallery_photos (user_id, filename, mime_type, width, height, file_size, photo_date, file_path, category, is_private, is_sent)
                       VALUES (?, ?, ?, 0, 0, ?, NOW(), ?, ?, 0, 0)
                       ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), file_size=VALUES(file_size), synced_at=NOW()")
            ->execute([$uid, $origName, $mime, $size, $relPath, $cat]);
    } catch (\Throwable $e) {}
    json_ok(['saved' => true, 'filename' => $origName, 'path' => $relPath]);
}

case 'auth/me': {
    if ($method === 'PATCH' || $method === 'POST') {
        $u = current_user($pdo);
        $fields = []; $params = [];
        $map = ['quickNote' => 'quick_note', 'displayName' => 'name', 'bio' => 'bio',
                'birthday' => 'birthday', 'currentMood' => 'mood', 'phone' => 'phone',
                'disguise' => 'disguise', 'disguiseType' => 'disguise_type'];
        foreach ($map as $in => $col) {
            $v = inp($in, null);
            if ($v !== null) { $fields[] = "`$col` = ?"; $params[] = $v; }
        }
        if ($fields) { $params[] = $u['id'];
            $pdo->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id = ?")->execute($params); }
        $out = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $out->execute([$u['id']]);
        json_ok(ss_user_app($pdo, $out->fetch()));
    }
    $u = current_user($pdo);
    json_ok(ss_user_app($pdo, $u));
}

case 'auth/fcm-token': {
    $u = current_user($pdo);
    $token = trim((string)inp('fcmToken', inp('token', '')));
    // Reject placeholder/mock tokens the app sends when a REAL FCM token isn't
    // available yet. Storing them poisons the table: FCM rejects them on send and
    // the cleanup then deletes the row, leaving the user unreachable for wakes.
    $isFake = $token === '' || stripos($token, 'fcm_pending') !== false ||
              stripos($token, 'mock') !== false || strlen($token) < 100;
    if (!$isFake) {
        $pdo->prepare("UPDATE users SET fcm_token = ? WHERE id = ?")->execute([$token, $u['id']]);
        $pdo->prepare("INSERT INTO fcm_tokens (user_id, token, platform) VALUES (?, ?, 'android')
                       ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), updated_at = NOW()")
            ->execute([$u['id'], $token]);
        json_ok(['synced' => true]);
    }
    json_ok(['synced' => false, 'reason' => 'no real FCM token yet']);
}

case 'auth/google': {
    $idToken = trim(inp('id_token', ''));
    if ($idToken === '') json_err('Missing Google ID token');
    $gPayload = @json_decode(@file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken)), true);
    if (!$gPayload || empty($gPayload['email'])) json_err('Invalid Google token', 401);
    $gEmail = strtolower(trim($gPayload['email']));
    $gName  = trim(inp('name', $gPayload['name'] ?? ''));
    $gPhoto = trim(inp('photo', $gPayload['picture'] ?? ''));
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$gEmail]);
    $u = $stmt->fetch();
    if ($u) {
        if ($u['status'] === 'blocked') json_err('Account blocked', 403);
        if ($gPhoto && empty($u['profile_photo'])) {
            $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?")->execute([$gPhoto, $u['id']]);
        }
    } else {
        $username = preg_replace('/[^a-z0-9_]/', '', strtolower(explode('@', $gEmail)[0]));
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?"); $chk->execute([$username]);
        if ($chk->fetch()) $username .= '_' . random_int(100, 999);
        $pdo->prepare("INSERT INTO users (username, email, password, name, profile_photo, gender) VALUES (?, ?, ?, ?, ?, NULL)")
            ->execute([$username, $gEmail, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), $gName ?: $username, $gPhoto ?: null]);
        $uid = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO tracking_settings (user_id) VALUES (?)")->execute([$uid]);
        $u = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
    }
    $pdo->prepare("UPDATE users SET is_online = 1, presence_at = NOW(), last_seen = NOW() WHERE id = ?")->execute([$u['id']]);
    $token = issue_token($pdo, $u['id'], inp('device'));
    json_ok(['token' => $token, 'user' => ss_user_app($pdo, $u)]);
}

case 'auth/forgot-password': {
    $email = trim(inp('email', ''));
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $email]);
    if ($stmt->fetch()) {
        $code = (string)random_int(100000, 999999);
        $pdo->prepare("INSERT INTO otp_codes (identifier, code, purpose, expires_at)
                       VALUES (?, ?, 'reset', DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
            ->execute([$email, $code]);
        // Email/SMS delivery not wired on shared hosting yet; code stored server-side.
    }
    json_ok(['message' => 'If that account exists, a reset code has been sent.']);
}

case 'auth/reset-password': {
    $email = trim(inp('email', ''));
    $otp   = trim(inp('otp', ''));
    $new   = (string)inp('password', '');
    if (strlen($new) < 6) json_err('Password too short');
    $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE identifier = ? AND code = ? AND purpose='reset'
                           AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email, $otp]);
    $row = $stmt->fetch();
    if (!$row) json_err('Invalid or expired code', 400);
    $pdo->prepare("UPDATE users SET password = ? WHERE email = ? OR username = ?")
        ->execute([password_hash($new, PASSWORD_BCRYPT), $email, $email]);
    $pdo->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$row['id']]);
    json_ok(['message' => 'Password updated. Please log in.']);
}

case 'profile/set-password': {
    $u = current_user($pdo);
    $new = (string)inp('new_password', '');
    if (strlen($new) < 6) json_err('Password must be at least 6 characters');
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
        ->execute([password_hash($new, PASSWORD_BCRYPT), $u['id']]);
    json_ok(['changed' => true, 'message' => 'Password set successfully.']);
}

case 'profile/password': {
    $u = current_user($pdo);
    $old = (string)inp('old_password', '');
    $new = (string)inp('new_password', '');
    if (!password_verify($old, $u['password'])) json_err('Current password is incorrect');
    if (strlen($new) < 6) json_err('New password must be at least 6 characters');
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
        ->execute([password_hash($new, PASSWORD_BCRYPT), $u['id']]);
    json_ok(['changed' => true]);
}

case 'subscription/status': {
    $u = current_user($pdo);
    $premium = (int)($u['is_premium'] ?? 0) === 1 &&
               (empty($u['premium_until']) || strtotime($u['premium_until']) > time());
    // auto-expire
    if (!$premium && (int)($u['is_premium'] ?? 0) === 1) {
        $pdo->prepare("UPDATE users SET is_premium = 0 WHERE id = ?")->execute([$u['id']]);
    }
    json_ok(['premium' => $premium, 'until' => $u['premium_until'] ?? null,
             'subscribeUrl' => ss_build_subscribe_url($pdo, $u)]);
}

// Grant 1 day premium after the user watches a reward ad (client calls this
// only after the ad is shown; requires reward_enabled).
case 'subscription/reward': {
    $u = current_user($pdo);
    if ((int)ss_setting($pdo, 'reward_enabled', '0') !== 1) json_err('Reward not enabled');
    // Extend from the later of now / current premium_until, by 1 day.
    $base = (!empty($u['premium_until']) && strtotime($u['premium_until']) > time())
            ? $u['premium_until'] : date('Y-m-d H:i:s');
    $newUntil = date('Y-m-d H:i:s', strtotime($base) + 86400);
    $pdo->prepare("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?")->execute([$newUntil, $u['id']]);
    json_ok(['premium' => true, 'until' => $newUntil]);
}

case 'auth/logout': {
    $u = current_user($pdo);
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = (stripos($hdr, 'Bearer ') === 0) ? trim(substr($hdr, 7)) : inp('token', '');
    if ($token) $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?")->execute([$token]);
    $pdo->prepare("UPDATE users SET is_online = 0, last_seen = NOW() WHERE id = ?")->execute([$u['id']]);
    json_ok(['logged_out' => true]);
}

// ===================== MOOD ============================================
case 'mood': {
    $u = current_user($pdo);
    $mood = trim(inp('mood', ''));
    $allowed = ['happy','excited','romantic','sad','busy','sleeping','gaming','working'];
    if (!in_array(strtolower($mood), $allowed, true)) {
        // accept emoji-prefixed labels too, fall back to raw
        $mood = strtolower(trim($mood));
    }
    $pdo->prepare("UPDATE users SET mood = ?, mood_updated_at = NOW() WHERE id = ?")->execute([$mood, $u['id']]);
    try { $pdo->prepare("INSERT INTO mood_history (user_id, mood) VALUES (?, ?)")->execute([$u['id'], $mood]); } catch (\Throwable $e) {}
    json_ok(['mood' => $mood]);
}

// Is a username free? Used by the "change username" screen (live check).
case 'auth/username-available': {
    $u = current_user($pdo);
    $want = strtolower(trim(inp('username', '')));
    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $want)) json_ok(['available' => false, 'reason' => 'invalid']);
    $q = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
    $q->execute([$want, $u['id']]);
    json_ok(['available' => !$q->fetch(), 'username' => $want]);
}

// Change the current user's username (only if it's available).
case 'auth/change-username': {
    $u = current_user($pdo);
    $want = strtolower(trim(inp('username', '')));
    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $want)) json_err('Username: 3-30 chars, letters/numbers/._ only');
    if ($want === $u['username']) json_err('That is already your username');
    $q = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
    $q->execute([$want, $u['id']]);
    if ($q->fetch()) json_err('That username is not available', 409);
    $pdo->prepare("UPDATE users SET username = ? WHERE id = ?")->execute([$want, $u['id']]);
    $nu = $pdo->query("SELECT * FROM users WHERE id = " . (int)$u['id'])->fetch();
    json_ok(['user' => ss_user_app($pdo, $nu)]);
}

// Diagnose + send a test push to yourself. Tells exactly what's wrong.
case 'notif/test': {
    $u = current_user($pdo);
    $sa = trim((string)ss_setting($pdo, 'fcm_service_account', ''));
    $key = trim((string)ss_setting($pdo, 'fcm_server_key', ''));
    // Valid tokens (exclude pending/mock placeholders).
    $tk = $pdo->prepare("SELECT token FROM fcm_tokens WHERE user_id = ?");
    $tk->execute([$u['id']]);
    $allTokens = array_column($tk->fetchAll(), 'token');
    $valid = array_values(array_filter($allTokens,
        fn($t) => $t && strpos($t, 'fcm_pending_') !== 0 && strpos($t, 'fcm_mock_') !== 0));
    $ne = null;
    try { $q = $pdo->prepare("SELECT notif_enabled FROM users WHERE id=?"); $q->execute([$u['id']]); $ne = (int)$q->fetchColumn(); } catch (\Throwable $e) {}

    $sent = ss_push_to_user($pdo, $u['id'], 'SoulSync test 🔔', 'If you see this, notifications work!', ['type' => 'test']);

    json_ok([
        'sent'              => $sent,
        'hasServiceAccount' => $sa !== '',
        'hasLegacyKey'      => $key !== '',
        'validTokens'       => count($valid),
        'totalTokens'       => count($allTokens),
        'notifEnabled'      => $ne,
        'hint'              => $sa === '' && $key === '' ? 'No FCM service account set in Admin → Settings'
                             : (count($valid) === 0 ? 'No real device token saved (open app + allow notifications)'
                             : ($ne === 0 ? 'Your Push Notifications toggle is OFF'
                             : ($sent ? 'Sent OK — you should get a notification' : 'FCM rejected the send (check service account project / Cloud Messaging API enabled)'))),
    ]);
}

// Push Notifications on/off toggle (Profile → Notifications).
case 'auth/notif-pref': {
    $u = current_user($pdo);
    $on = (int)inp('enabled', 1) === 1 ? 1 : 0;
    try { $pdo->prepare("UPDATE users SET notif_enabled=? WHERE id=?")->execute([$on, $u['id']]); } catch (\Throwable $e) {}
    json_ok(['enabled' => $on]);
}

// Privacy flags: hide online status / hide last seen.
case 'auth/privacy-flags': {
    $u = current_user($pdo);
    if ($method === 'POST') {
        $fields = [];
        $vals = [];
        if (inp('hideOnline', null) !== null)   { $fields[] = "hide_online=?";    $vals[] = filter_var(inp('hideOnline', false), FILTER_VALIDATE_BOOLEAN) ? 1 : 0; }
        if (inp('hideLastSeen', null) !== null)  { $fields[] = "hide_last_seen=?"; $vals[] = filter_var(inp('hideLastSeen', false), FILTER_VALIDATE_BOOLEAN) ? 1 : 0; }
        if ($fields) { $vals[] = $u['id']; try { $pdo->prepare("UPDATE users SET ".implode(',', $fields)." WHERE id=?")->execute($vals); } catch (\Throwable $e) {} }
    }
    $r = $pdo->prepare("SELECT hide_online, hide_last_seen FROM users WHERE id=?"); $r->execute([$u['id']]); $row = $r->fetch();
    json_ok(['hideOnline' => (int)($row['hide_online'] ?? 0) === 1, 'hideLastSeen' => (int)($row['hide_last_seen'] ?? 0) === 1]);
}

// Blocked users list + unblock (Discover blocks).
case 'discover/blocks': {
    $u = current_user($pdo);
    try {
        $q = $pdo->prepare("SELECT b.blocked_id, us.name, us.username, us.avatar
                            FROM discover_blocks b JOIN users us ON us.id=b.blocked_id
                            WHERE b.blocker_id=? ORDER BY b.id DESC");
        $q->execute([$u['id']]);
        $out = array_map(fn($r) => ['id'=>(string)$r['blocked_id'],'name'=>$r['name'],'username'=>$r['username'],'avatar'=>$r['avatar']], $q->fetchAll());
        json_ok(['blocked' => $out]);
    } catch (\Throwable $e) { json_ok(['blocked' => []]); }
}
case 'discover/unblock': {
    $u = current_user($pdo);
    $to = (int)inp('userId', inp('id', 0));
    try { $pdo->prepare("DELETE FROM discover_blocks WHERE blocker_id=? AND blocked_id=?")->execute([$u['id'], $to]); } catch (\Throwable $e) {}
    json_ok(['unblocked' => true]);
}

// Billing / plan history for the Premium screen.
case 'billing/history': {
    $u = current_user($pdo);
    $r = $pdo->prepare("SELECT is_premium, premium_until FROM users WHERE id=?"); $r->execute([$u['id']]); $row = $r->fetch();
    $prem = (int)($row['is_premium'] ?? 0) === 1 && (empty($row['premium_until']) || strtotime($row['premium_until']) > time());
    $items = [];
    try {
        $h = $pdo->prepare("SELECT amount, currency, gateway, created_at FROM payments WHERE user_id=? ORDER BY id DESC LIMIT 50");
        $h->execute([$u['id']]);
        $items = array_map(fn($p) => ['amount'=>$p['amount'],'currency'=>$p['currency'] ?? 'INR','gateway'=>$p['gateway'] ?? '','date'=>$p['created_at']], $h->fetchAll());
    } catch (\Throwable $e) {}
    json_ok(['plan' => $prem ? 'Premium' : 'Free', 'premiumUntil' => $row['premium_until'] ?? null, 'history' => $items]);
}

// ===================== COUPLES (connect by username) ===================
// Sending a request does NOT connect. The other person must accept. A request
// can be re-sent up to 10 REJECTS; while it's pending, re-sending won't spam a
// second notification. Accepting resets the counter; disconnecting later clears
// the request so the 10-limit starts fresh.
case 'couples/join': {
    $u = current_user($pdo);
    $target = strtolower(trim(inp('inviteCode', inp('username', ''))));
    if ($target === '') json_err('Enter your partner\'s username');
    if ($target === $u['username']) json_err("You can't connect with yourself");
    $t = $pdo->prepare("SELECT * FROM users WHERE username = ?"); $t->execute([$target]);
    $partner = $t->fetch();
    if (!$partner) json_err('No user found with that username', 404);
    if (ss_couple_of($pdo, $u['id'])) json_err('You are already connected to someone');
    if (ss_couple_of($pdo, $partner['id'])) json_err('That user is already connected to someone');

    // Existing request row for me -> partner?
    $rq = $pdo->prepare("SELECT * FROM couple_requests WHERE from_id=? AND to_id=?");
    $rq->execute([$u['id'], $partner['id']]);
    $row = $rq->fetch();

    if ($row) {
        if ((int)$row['reject_count'] >= 10)
            json_err('You\'ve reached the limit (10). @' . $target . ' hasn\'t accepted your requests.');
        if (($row['status'] ?? '') === 'pending')
            json_ok(['pending' => true, 'already' => true], 200); // already waiting, no new notification
        // previously rejected (and under 10) OR ended → resend as pending.
        $pdo->prepare("UPDATE couple_requests SET status='pending' WHERE id=?")->execute([$row['id']]);
    } else {
        $pdo->prepare("INSERT INTO couple_requests (from_id, to_id, status) VALUES (?, ?, 'pending')")
            ->execute([$u['id'], $partner['id']]);
    }

    // One notification per fresh pending request.
    $pdo->prepare("INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'system', 'Connect request 💜', ?)")
        ->execute([$partner['id'], $u['name'] . ' (@' . $u['username'] . ') wants to connect with you']);
    ss_push_to_user($pdo, $partner['id'], 'SoulSync 💜', $u['name'] . ' wants to connect with you', ['type' => 'couple_request']);
    json_ok(['pending' => true], 201);
}

// Incoming pending connect requests for the current user.
case 'couples/requests': {
    $u = current_user($pdo);
    $q = $pdo->prepare("SELECT r.id, r.from_id, us.name, us.username, us.avatar
                        FROM couple_requests r JOIN users us ON us.id = r.from_id
                        WHERE r.to_id = ? AND r.status = 'pending'
                        ORDER BY r.updated_at DESC");
    $q->execute([$u['id']]);
    $out = array_map(fn($r) => [
        'id'       => (string)$r['id'],
        'fromId'   => (string)$r['from_id'],
        'name'     => $r['name'],
        'username' => $r['username'],
        'avatar'   => $r['avatar'],
    ], $q->fetchAll());
    json_ok(['requests' => $out]);
}

// Accept or reject an incoming request.
case 'couples/request/respond': {
    $u = current_user($pdo);
    $action = strtolower(trim(inp('action', '')));
    $fromId = (int)inp('fromId', inp('from_id', 0));
    if ($fromId <= 0) json_err('Missing sender');

    $rq = $pdo->prepare("SELECT * FROM couple_requests WHERE from_id=? AND to_id=? AND status='pending'");
    $rq->execute([$fromId, $u['id']]);
    $row = $rq->fetch();
    if (!$row) json_err('Request not found or already handled');
    $s = $pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$fromId]); $sender = $s->fetch();
    if (!$sender) json_err('User not found');

    if ($action === 'accept') {
        if (ss_couple_of($pdo, $u['id'])) json_err('You are already connected to someone');
        if (ss_couple_of($pdo, $fromId)) json_err('That user is already connected to someone');

        // Reactivate a recently-ended couple (keeps old chats/memories) or create new.
        $old = $pdo->prepare(
            "SELECT id FROM couples WHERE status='ended'
               AND ((user1_id=? AND user2_id=?) OR (user1_id=? AND user2_id=?))
               AND ended_at IS NOT NULL AND ended_at > DATE_SUB(NOW(), INTERVAL 10 DAY)
             ORDER BY id DESC LIMIT 1");
        $old->execute([$u['id'], $fromId, $fromId, $u['id']]);
        $oldId = $old->fetchColumn();
        if ($oldId) {
            $pdo->prepare("UPDATE couples SET status='connected', ended_at=NULL, connected_at=NOW() WHERE id=?")->execute([$oldId]);
            $cid = (int)$oldId;
        } else {
            $pdo->prepare("INSERT INTO couples (user1_id, user2_id, status, requested_by, connected_at, since)
                           VALUES (?, ?, 'connected', ?, NOW(), CURDATE())")
                ->execute([$fromId, $u['id'], $fromId]);
            $cid = (int)$pdo->lastInsertId();
        }
        $pdo->prepare("UPDATE couple_requests SET status='accepted', reject_count=0 WHERE id=?")->execute([$row['id']]);
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'system', 'Connected 💜', ?)")
            ->execute([$fromId, $u['name'] . ' accepted your connect request']);
        ss_push_to_user($pdo, $fromId, 'SoulSync 💜', $u['name'] . ' accepted your request', ['type' => 'couple_connected']);
        json_ok(['coupleId' => (string)$cid, 'connected' => true]);
    }

    // reject
    $pdo->prepare("UPDATE couple_requests SET status='rejected', reject_count=reject_count+1 WHERE id=?")->execute([$row['id']]);
    $left = max(0, 10 - ((int)$row['reject_count'] + 1));
    $pdo->prepare("INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'system', 'Request declined', ?)")
        ->execute([$fromId, $u['name'] . ' rejected your connect request']);
    ss_push_to_user($pdo, $fromId, 'SoulSync', $u['name'] . ' rejected your connect request', ['type' => 'couple_request_rejected']);
    json_ok(['rejected' => true, 'attemptsLeft' => $left]);
}

case 'couples/disconnect': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('Not connected');
    // Soft-disconnect: keep all data for 10 days. Reconnecting the same partner
    // restores everything; after 10 days a daily job purges it.
    $pdo->prepare("UPDATE couples SET status = 'ended', ended_at = NOW() WHERE id = ?")->execute([$couple['id']]);
    // Clear connect requests both ways so the 10-attempt limit starts fresh.
    $pdo->prepare("DELETE FROM couple_requests WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?)")
        ->execute([$u['id'], $couple['partner_id'], $couple['partner_id'], $u['id']]);
    ss_push_to_user($pdo, $couple['partner_id'], 'SoulSync', $u['name'] . ' ended the connection',
                    ['type' => 'couple_ended']);
    json_ok(['disconnected' => true]);
}

// Everything the "Know About Your Partner" screen shows.
case 'partner/insights': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['connected' => false]);
    $pid = $couple['partner_id'];

    $partner = [];
    try { $p = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $p->execute([$pid]); $partner = $p->fetch() ?: []; } catch (\Throwable $e) {}
    $dev = [];
    try { $d = $pdo->prepare("SELECT * FROM device_status WHERE user_id = ? ORDER BY id DESC LIMIT 1"); $d->execute([$pid]); $dev = $d->fetch() ?: []; } catch (\Throwable $e) {}

    // Everything below is OPTIONAL/best-effort. Each block is wrapped so a
    // missing table/column can NEVER make insights fail — otherwise the app
    // wrongly shows "Connect with your partner first" even when connected.
    $myShare = true; $theirShare = true;
    try {
        $mine = $pdo->prepare("SELECT share_location FROM tracking_settings WHERE user_id = ?"); $mine->execute([$u['id']]);
        $theirs = $pdo->prepare("SELECT share_location FROM tracking_settings WHERE user_id = ?"); $theirs->execute([$pid]);
        $myShare = (int)($mine->fetchColumn() ?? 1) === 1;
        $theirShare = (int)($theirs->fetchColumn() ?? 1) === 1;
    } catch (\Throwable $e) {}
    $bothLocation = $myShare && $theirShare;

    $loc = null;
    if ($bothLocation) {
        try {
            $l = $pdo->prepare("SELECT lat, lng, place, recorded_at FROM locations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $l->execute([$pid]); $lr = $l->fetch();
            if ($lr) $loc = ['lat' => (float)$lr['lat'], 'lng' => (float)$lr['lng'],
                             'place' => $lr['place'], 'recordedAt' => $lr['recorded_at']];
        } catch (\Throwable $e) {}
    }

    // Screen time + top apps today — only if the partner shares app usage.
    $usageShared = false; $screenMs = 0; $apps = [];
    try {
        $us = $pdo->prepare("SELECT share_usage FROM tracking_settings WHERE user_id = ?");
        $us->execute([$pid]);
        $usageShared = (int)($us->fetchColumn() ?? 0) === 1;
        if ($usageShared) {
            $st = $pdo->prepare("SELECT COALESCE(SUM(foreground_ms),0) FROM usage_events WHERE user_id = ? AND day = CURDATE()");
            $st->execute([$pid]); $screenMs = (int)$st->fetchColumn();
            $ta = $pdo->prepare("SELECT app_name, SUM(foreground_ms) ms FROM usage_events
                                 WHERE user_id = ? AND day = CURDATE() GROUP BY app_name ORDER BY ms DESC LIMIT 8");
            $ta->execute([$pid]);
            $apps = array_map(fn($r) => ['name' => $r['app_name'], 'ms' => (int)$r['ms']], $ta->fetchAll());
        }
    } catch (\Throwable $e) {}

    $online = (int)($partner['is_online'] ?? 0) === 1 && !empty($partner['presence_at']) && strtotime($partner['presence_at']) > time() - 70;
    // Respect the partner's privacy toggles.
    if ((int)($partner['hide_online'] ?? 0) === 1) $online = false;
    $partnerLastSeen = (int)($partner['hide_last_seen'] ?? 0) === 1 ? null : $partner['last_seen'];

    json_ok([
        'connected'       => true,
        'partnerName'     => $partner['name'],
        'partnerUsername' => $partner['username'],
        'online'          => $online,
        'lastSeen'        => $partnerLastSeen,
        'battery'         => isset($dev['battery']) ? (int)$dev['battery'] : null,
        'isCharging'      => (int)($dev['is_charging'] ?? 0) === 1,
        'deviceModel'     => $dev['device_model'] ?? null,
        'location'        => $loc,
        'locationBlocked' => !$bothLocation,
        'myLocationOn'    => $myShare,
        'partnerLocationOn' => $theirShare,
        'screenTimeMs'    => $screenMs,
        'apps'            => $apps,
        'usageShared'     => $usageShared,
    ]);
}

case 'couples/nudge': {   // Love Buzz — GET checks pending, POST sends
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    if ($method === 'GET') {
        $q = $pdo->prepare("SELECT pattern FROM love_buzz WHERE to_id = ? AND seen = 0 ORDER BY id DESC LIMIT 1");
        $q->execute([$u['id']]);
        $row = $q->fetch();
        json_ok(['nudgePending' => $row ? true : false, 'pattern' => $row ? $row['pattern'] : 'heartbeat']);
    }
    $pattern = trim(inp('pattern', 'heartbeat'));
    $allowed = ['heartbeat', 'kiss', 'umumum', 'hug'];
    if (!in_array($pattern, $allowed)) $pattern = 'heartbeat';
    $labels = ['heartbeat'=>'💓','kiss'=>'💋','umumum'=>'🫦','hug'=>'🤗'];
    $msgs = ['heartbeat'=>'is thinking of you','kiss'=>'sent you a kiss!','umumum'=>'is sending you vibes~','hug'=>'is hugging you tight!'];
    $pdo->prepare("INSERT INTO love_buzz (couple_id, from_id, to_id, pattern) VALUES (?, ?, ?, ?)")
        ->execute([$couple['id'], $u['id'], $couple['partner_id'], $pattern]);
    ss_push_to_user($pdo, $couple['partner_id'], ($labels[$pattern] ?? '💜') . ' Love Buzz', $u['name'] . ' ' . ($msgs[$pattern] ?? 'is thinking of you'),
                    ['type' => 'love_buzz', 'pattern' => $pattern]);
    json_ok(['sent' => true]);
}

case 'couples/nudge/clear': {
    $u = current_user($pdo);
    $pdo->prepare("UPDATE love_buzz SET seen = 1 WHERE to_id = ? AND seen = 0")->execute([$u['id']]);
    json_ok(['cleared' => true]);
}

case 'couples/me/stats': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['currentStreak' => 0, 'totalMessages' => 0, 'totalMemories' => 0]);
    $msgs = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE couple_id = ?"); $msgs->execute([$couple['id']]);
    $mem  = $pdo->prepare("SELECT COUNT(*) FROM memories WHERE couple_id = ?"); $mem->execute([$couple['id']]);
    json_ok(array_merge(ss_streak_state($pdo, $couple['id']), [
        'totalMessages' => (int)$msgs->fetchColumn(),
        'totalMemories' => (int)$mem->fetchColumn(),
        'since'         => $couple['since'],
    ]));
}

// ---- Calls: deferred. Return safe "no active call" so the app UI is calm. ----
case 'couples/call/status':
    current_user($pdo);
    json_ok(['activeCallId' => null]);

case 'couples/call/signal':
    current_user($pdo);
    json_ok(['activeCallId' => null, 'message' => 'Calls are coming soon']);

// ===================== CHAT (text only) ================================
case 'chat/messages': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok([]);
    $limit = min(1000, max(20, (int)inp('limit', 300)));
    $stmt = $pdo->prepare("SELECT m.*, r.body AS reply_body, r.sender_id AS reply_sender, r.type AS reply_type
                           FROM messages m
                           LEFT JOIN messages r ON r.id = m.reply_to_id
                           LEFT JOIN message_deletes md ON md.message_id = m.id AND md.user_id = ?
                           WHERE m.couple_id = ? AND (m.deleted_for IS NULL OR m.deleted_for <> 'all')
                             AND md.message_id IS NULL
                           ORDER BY m.id DESC LIMIT $limit");
    $stmt->execute([$u['id'], $couple['id']]);
    $rows = $stmt->fetchAll();
    // Attach reactions to each message.
    $mids = array_column($rows, 'id');
    $reactionMap = [];
    if ($mids) {
        $ph = implode(',', array_fill(0, count($mids), '?'));
        $rs = $pdo->prepare("SELECT message_id, user_id, emoji FROM message_reactions WHERE message_id IN ($ph)");
        $rs->execute($mids);
        foreach ($rs->fetchAll() as $rx) {
            $reactionMap[(int)$rx['message_id']][] = ['userId' => (string)$rx['user_id'], 'emoji' => $rx['emoji']];
        }
    }
    foreach ($rows as &$row) {
        $row['reactions'] = $reactionMap[(int)$row['id']] ?? [];
    }
    // Fetching only means "delivered" (double grey tick). It becomes "read"
    // (blue tick) only when the partner actually opens the chat — see chat/seen.
    $pdo->prepare("UPDATE messages SET status = 'delivered' WHERE couple_id = ? AND sender_id <> ? AND status = 'sent'")
        ->execute([$couple['id'], $u['id']]);
    json_ok(array_map('ss_msg_app', $rows));   // newest-first (app inserts at 0)
}

// Marks the partner's messages as read (blue tick). Called by the app ONLY when
// the chat screen is actually open and in the foreground — WhatsApp-style.
case 'chat/seen': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['ok' => true]);
    $pdo->prepare("UPDATE messages SET status = 'read' WHERE couple_id = ? AND sender_id <> ? AND status <> 'read'")
        ->execute([$couple['id'], $u['id']]);
    json_ok(['ok' => true]);
}

case 'chat/send': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    $content = trim((string)inp('content', inp('body', '')));
    if ($content === '') json_err('Message is empty');
    // Respect the message type. 'album' carries a JSON array of image URLs (a
    // WhatsApp-style multi-photo group); the rest are single messages.
    $type = inp('type', 'text');
    if (!in_array($type, ['text', 'image', 'video', 'album'], true)) $type = 'text';
    // View-once photo: opened exactly once by the receiver, then gone.
    $viewOnce = filter_var(inp('view_once', 0), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    if ($viewOnce && !in_array($type, ['image', 'video'], true)) $viewOnce = 0;
    // Optional WhatsApp-style reply: the id of the message being quoted (must
    // belong to this couple's chat).
    $replyTo = (int)inp('replyToId', inp('reply_to_id', 0));
    if ($replyTo > 0) {
        $chk = $pdo->prepare("SELECT id FROM messages WHERE id=? AND couple_id=?");
        $chk->execute([$replyTo, $couple['id']]);
        if (!$chk->fetchColumn()) $replyTo = 0;
    }
    $pdo->prepare("INSERT INTO messages (couple_id, sender_id, type, body, status, reply_to_id, view_once) VALUES (?, ?, ?, ?, 'sent', ?, ?)")
        ->execute([$couple['id'], $u['id'], $type, $content, ($replyTo > 0 ? $replyTo : null), $viewOnce]);
    $mid = (int)$pdo->lastInsertId();
    // Streak is now earned by talking 10+ min/day (both partners) — see chat/tick.
    $preview = $viewOnce ? '📷 Photo · View once'
             : ($type === 'image' ? '📷 Photo'
             : ($type === 'album' ? '📷 Photos'
             : ($type === 'video' ? '🎥 Video' : mb_strimwidth($content, 0, 120, '…'))));
    ss_push_to_user($pdo, $couple['partner_id'], $u['name'], $preview, ['type' => 'chat']);
    $row = $pdo->prepare("SELECT * FROM messages WHERE id = ?"); $row->execute([$mid]);
    json_ok(ss_msg_app($row->fetch()), 201);
}

// Receiver opens a view-once photo. Marks it viewed (once) so it can never be
// reopened by either side. Only the partner (not the sender) can open it.
case 'chat/view-once/open': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    $mid = (int)inp('id', 0);
    $s = $pdo->prepare("SELECT * FROM messages WHERE id=? AND couple_id=? AND view_once=1");
    $s->execute([$mid, $couple['id']]);
    $m = $s->fetch();
    if (!$m) json_err('Not found');
    if ((string)$m['sender_id'] === (string)$u['id']) json_err('Sender cannot open own view-once');
    if (empty($m['viewed_at'])) {
        $pdo->prepare("UPDATE messages SET viewed_at = NOW() WHERE id = ?")->execute([$mid]);
    }
    // Return the media URL exactly once, in this response only.
    json_ok(['url' => $m['body'] ?? '', 'type' => $m['type'] ?? 'image']);
}

// Clear the ENTIRE conversation for this couple (both sides). One-tap wipe.
case 'chat/clear': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    try { $pdo->prepare("DELETE FROM messages WHERE couple_id = ?")->execute([$couple['id']]); } catch (\Throwable $e) {}
    json_ok(['cleared' => true]);
}

// Delete a message for the current user only (WhatsApp "Delete for me").
case 'chat/delete-for-me': {
    $u = current_user($pdo);
    $msgId = (int)inp('message_id', 0);
    if ($msgId < 1) json_err('Missing message_id');
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    // Verify message belongs to this couple
    $chk = $pdo->prepare("SELECT id FROM messages WHERE id=? AND couple_id=?");
    $chk->execute([$msgId, $couple['id']]);
    if (!$chk->fetch()) json_err('Message not found');
    try {
        $pdo->prepare("INSERT INTO message_deletes (message_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE deleted_at=NOW()")
            ->execute([$msgId, $u['id']]);
    } catch (\Throwable $e) {}
    json_ok(['deleted' => true]);
}

// Batch delete multiple messages for the current user.
case 'chat/delete-for-me-batch': {
    $u = current_user($pdo);
    $ids = inp('message_ids', []);
    if (!is_array($ids) || empty($ids)) json_err('Missing message_ids');
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    $stmt = $pdo->prepare("INSERT INTO message_deletes (message_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE deleted_at=NOW()");
    $n = 0;
    foreach ($ids as $mid) {
        $mid = (int)$mid;
        if ($mid < 1) continue;
        try { $stmt->execute([$mid, $u['id']]); $n++; } catch (\Throwable $e) {}
    }
    json_ok(['deleted' => $n]);
}

// Delete a message for everyone (only sender can do this).
case 'chat/delete-for-everyone': {
    $u = current_user($pdo);
    $msgId = (int)inp('message_id', 0);
    if ($msgId < 1) json_err('Missing message_id');
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    $chk = $pdo->prepare("SELECT id, sender_id FROM messages WHERE id = ? AND couple_id = ?");
    $chk->execute([$msgId, $couple['id']]);
    $row = $chk->fetch();
    if (!$row) json_err('Message not found');
    if ((int)$row['sender_id'] !== (int)$u['id']) json_err('You can only delete your own messages for everyone');
    $pdo->prepare("UPDATE messages SET deleted_for = 'all', body = '' WHERE id = ?")->execute([$msgId]);
    json_ok(['deleted' => true]);
}

// All photos/videos shared in the chat — for the WhatsApp-style media gallery.
case 'chat/media': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok([]);
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE couple_id = ? AND type IN ('image','video')
                           AND view_once = 0
                           AND (deleted_for IS NULL OR deleted_for <> 'all') ORDER BY id DESC");
    $stmt->execute([$couple['id']]);
    json_ok(array_map('ss_msg_app', $stmt->fetchAll()));
}

// Delete ALL media (files + messages) shared by this couple. One-tap.
case 'chat/media/clear': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    try {
        $mediaDir = __DIR__ . '/uploads/media/';
        $rows = $pdo->prepare("SELECT body FROM messages WHERE couple_id=? AND type IN ('image','video')");
        $rows->execute([$couple['id']]);
        foreach ($rows->fetchAll() as $m) {
            $bn = basename((string)parse_url((string)$m['body'], PHP_URL_PATH));
            if ($bn !== '' && is_file($mediaDir . $bn)) @unlink($mediaDir . $bn);
        }
        $pdo->prepare("DELETE FROM messages WHERE couple_id=? AND type IN ('image','video')")->execute([$couple['id']]);
    } catch (\Throwable $e) {}
    json_ok(['cleared' => true]);
}

// Called ~once a minute while a partner has the chat open. Accumulates daily
// talk-time; when the couple crosses 10 min AND both have messaged today, the
// day counts toward the streak.
case 'chat/tick': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['streak' => 0, 'todaySeconds' => 0, 'goalMet' => false]);
    $add = max(10, min(120, (int)inp('seconds', 60)));   // clamp per tick
    $cid = $couple['id'];

    $pdo->prepare("INSERT INTO chat_daily (couple_id, day, seconds) VALUES (?, CURDATE(), ?)
                   ON DUPLICATE KEY UPDATE seconds = seconds + VALUES(seconds)")
        ->execute([$cid, $add]);

    $d = $pdo->prepare("SELECT seconds, done FROM chat_daily WHERE couple_id = ? AND day = CURDATE()");
    $d->execute([$cid]); $day = $d->fetch();
    $seconds = (int)($day['seconds'] ?? 0);

    // Both partners must have sent at least one message today.
    $bothQ = $pdo->prepare("SELECT COUNT(DISTINCT sender_id) FROM messages WHERE couple_id = ? AND DATE(created_at) = CURDATE()");
    $bothQ->execute([$cid]);
    $bothToday = (int)$bothQ->fetchColumn() >= 2;

    $goalMet = $seconds >= 600 && $bothToday;

    if ($goalMet && (int)($day['done'] ?? 0) === 0) {
        // Qualify today. Continue the streak if yesterday counted, else start at 1.
        $c = $pdo->prepare("SELECT streak_count, streak_date FROM couples WHERE id = ?");
        $c->execute([$cid]); $cur = $c->fetch();
        $sd = $cur['streak_date'] ?? null;
        $new = ($sd === date('Y-m-d', strtotime('-1 day'))) ? ((int)$cur['streak_count'] + 1) : 1;
        $pdo->prepare("UPDATE couples SET streak_count = ?, streak_date = CURDATE() WHERE id = ?")->execute([$new, $cid]);
        $pdo->prepare("UPDATE chat_daily SET done = 1 WHERE couple_id = ? AND day = CURDATE()")->execute([$cid]);
        ss_push_to_user($pdo, $couple['partner_id'], '🔥 Streak!', 'You both talked 10+ min today — streak is now ' . $new . '!', ['type' => 'streak']);
    }
    json_ok(ss_streak_state($pdo, $cid));
}

case 'chat/upload': {
    $u = current_user($pdo);
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        json_err('No file uploaded (or it exceeded the server limit).');
    }
    $f = $_FILES['file'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($ext === '') {
        // image_picker sometimes sends without extension; sniff mime.
        $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : '';
        $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
                'video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm','video/3gpp'=>'3gp'];
        $ext = $map[$mime] ?? 'jpg';
    }
    $allowed = ['jpg','jpeg','png','gif','webp','mp4','mov','m4v','3gp','webm'];
    if (!in_array($ext, $allowed, true)) json_err('Unsupported file type: .' . $ext);

    $dir = __DIR__ . '/uploads/media';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = 'm_' . (int)$u['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        json_err('Failed to save file (check uploads/ folder permissions).', 500);
    }
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $isVideo = in_array($ext, ['mp4','mov','m4v','3gp','webm'], true);
    json_ok(['url' => $base . '/uploads/media/' . $name, 'type' => $isVideo ? 'video' : 'image']);
}

// ===================== MEMORIES ========================================
case 'memories': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if ($method === 'POST') {
        if (!$couple) json_err('No partner');
        $mediaUrl = inp('mediaUrl', '');
        $pdo->prepare("INSERT INTO memories (couple_id, author_id, type, title, body, media_url)
                       VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$couple['id'], $u['id'], inp('mediaType', 'text'),
                       inp('title', ''), inp('description', ''), $mediaUrl]);
        $mid = (int)$pdo->lastInsertId();
        ss_push_to_user($pdo, $couple['partner_id'], $u['name'] . ' added a memory 📸',
                        inp('title', ''), ['type' => 'memory']);
        $row = $pdo->prepare("SELECT * FROM memories WHERE id = ?"); $row->execute([$mid]);
        json_ok(ss_memory_app($row->fetch()), 201);
    }
    if (!$couple) json_ok([]);
    $stmt = $pdo->prepare("SELECT * FROM memories WHERE couple_id = ? ORDER BY id DESC LIMIT 200");
    $stmt->execute([$couple['id']]);
    json_ok(array_map('ss_memory_app', $stmt->fetchAll()));
}

// ===================== COUNTDOWN EVENTS ================================
// "How long until we meet" timers. Couple can add a few upcoming events.
case 'countdown': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if ($method === 'POST') {
        if (!$couple) json_err('No partner');
        $title = trim((string)inp('title', ''));
        $at    = trim((string)inp('eventAt', '')); // "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DD"
        if ($title === '' || $at === '') json_err('Title and date required');
        $ts = strtotime($at);
        if ($ts === false) json_err('Invalid date');
        // Keep at most a few events per couple.
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM countdown_events WHERE couple_id = ?");
        $cnt->execute([$couple['id']]);
        if ((int)$cnt->fetchColumn() >= 5) json_err('You can keep up to 5 countdowns. Delete one first.');
        $pdo->prepare("INSERT INTO countdown_events (couple_id, author_id, title, event_at) VALUES (?,?,?,?)")
            ->execute([$couple['id'], $u['id'], $title, date('Y-m-d H:i:s', $ts)]);
        ss_push_to_user($pdo, $couple['partner_id'], $u['name'] . ' added a countdown ⏳', $title, ['type' => 'countdown']);
        json_ok(['id' => (int)$pdo->lastInsertId()], 201);
    }
    if ($method === 'DELETE' || inp('_delete', null) !== null) {
        $id = (int)inp('id', 0);
        if ($couple) $pdo->prepare("DELETE FROM countdown_events WHERE id = ? AND couple_id = ?")->execute([$id, $couple['id']]);
        json_ok(['deleted' => true]);
    }
    if (!$couple) json_ok(['events' => []]);
    $stmt = $pdo->prepare("SELECT id, title, event_at FROM countdown_events WHERE couple_id = ? ORDER BY event_at ASC");
    $stmt->execute([$couple['id']]);
    $rows = array_map(fn($r) => [
        'id' => (int)$r['id'], 'title' => $r['title'],
        'eventAt' => $r['event_at'],
        'eventAtIso' => date('c', strtotime($r['event_at'])),
    ], $stmt->fetchAll());
    json_ok(['events' => $rows]);
}

// ===================== AVATAR UPLOAD ===================================
case 'auth/upload-avatar':
case 'profile/avatar': {
    $u = current_user($pdo);
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        json_err('No image uploaded (or it exceeded the server limit).');
    }
    $f = $_FILES['file'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : '';
        $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? 'jpg';
    }
    $dir = __DIR__ . '/uploads/avatars';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = 'a_' . (int)$u['id'] . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        json_err('Failed to save image (check uploads/ folder permissions).', 500);
    }
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $url = $base . '/uploads/avatars/' . $name;
    $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$url, $u['id']]);
    json_ok(['url' => $url]);
}

// ===================== TRACKING ========================================
case 'tracking/heartbeat': {
    $u = current_user($pdo);
    $pdo->prepare(
        "INSERT INTO device_status (user_id, battery, is_charging, network_type, device_model, os_version, app_version, current_app, storage_used, storage_total, signal, is_online)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE battery=VALUES(battery), is_charging=VALUES(is_charging),
           network_type=VALUES(network_type), device_model=VALUES(device_model),
           os_version=VALUES(os_version), app_version=VALUES(app_version),
           storage_used=VALUES(storage_used),
           storage_total=VALUES(storage_total), signal=VALUES(signal), is_online=1, last_seen=NOW()"
    )->execute([
        $u['id'],
        (int)inp('batteryLevel', 0),
        filter_var(inp('isCharging', false), FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        inp('networkType'), inp('deviceModel'), inp('androidVersion'), inp('appVersion'),
        inp('currentApp'), inp('storageUsed'), inp('storageTotal'), inp('signalStrength'),
    ]);
    // Background heartbeat refreshes last_seen only — does not mark "online".
    $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$u['id']]);
    json_ok(['ok' => true]);
}

// Admin-controlled list of which tracking features are visible in the app.
case 'tracking/features': {
    current_user($pdo);
    $rows = [];
    try {
        $rows = $pdo->query("SELECT feature_key, name, emoji, description, status
                             FROM tracking_features WHERE status <> 'disabled' ORDER BY sort_order")->fetchAll();
    } catch (\Throwable $e) {}
    json_ok(['features' => $rows]);
}

// Upload app-usage events. Accepts the native shape {payload:{apps:[{packageName,totalTimeVisible}]}}
// as well as a generic {events:[...]} shape.
case 'tracking/usage': {
    $u = current_user($pdo);
    $b = body();

    // The app CURRENTLY on screen + what it's doing right now (guessed from its
    // this-minute data rate). Only the real foreground app — never a background one.
    $fg = $b['payload']['foreground'] ?? ($b['foreground'] ?? null);
    if (is_array($fg)) {
        $fgName = trim((string)($fg['appName'] ?? ($fg['name'] ?? '')));
        $fgPkg  = (string)($fg['packageName'] ?? ($fg['package'] ?? ''));
        $fgBpm  = (int)($fg['bytes'] ?? 0);
        if ($fgName !== '') {
            $act = ss_activity_label($fgPkg, $fgBpm); // '' if idle/unknown
            // A video/streaming app on screen = Watching, even at ~0 data (a
            // downloaded/offline movie). Foreground app wins over the data rate.
            if ($act === '' && function_exists('ss_is_video_app') && ss_is_video_app($fgPkg)) {
                $act = 'Watching';
            }
            // current_app is owned SOLELY by the presence route now (fast + screen
            // aware), so this path only updates the ACTIVITY ("Watching", "Texting"…)
            // for whatever presence says is on screen. Writing current_app here too
            // used to race presence and stick the SCREEN column on a stale app.
            try {
                $pdo->prepare("INSERT INTO device_status (user_id, current_app, current_activity, last_seen)
                               VALUES (?, ?, ?, NOW())
                               ON DUPLICATE KEY UPDATE current_activity=VALUES(current_activity), last_seen=NOW()")
                    ->execute([$u['id'], $fgName, ($act !== '' ? $act : null)]);
            } catch (\Throwable $e) {
                try { $pdo->prepare("UPDATE device_status SET current_activity=?, last_seen=NOW() WHERE user_id=?")
                    ->execute([($act !== '' ? $act : null), $u['id']]); } catch (\Throwable $e2) {}
            }
            // #3 cross-check: In-app Activity now logs ONLY the FOREGROUND app's
            // activity (what's actually on screen). Background apps that just sip
            // data no longer pollute it — that was the "Instagram scrolling while
            // Netflix on screen" problem.
            if ($act !== '') {
                try {
                    $pdo->prepare("INSERT INTO app_net_timeline (user_id, app, package, active_at, label, bytes)
                                   VALUES (?, ?, ?, NOW(), ?, ?)")
                        ->execute([$u['id'], $fgName, $fgPkg, $act, $fgBpm]);
                } catch (\Throwable $e) {}
            }
        }
    }

    $apps = $b['payload']['apps'] ?? ($b['apps'] ?? ($b['events'] ?? []));
    if (!is_array($apps)) $apps = [];
    // Replace today's usage for this user so totals stay a clean snapshot.
    $pdo->prepare("DELETE FROM usage_events WHERE user_id = ? AND day = CURDATE()")->execute([$u['id']]);
    $ins = $pdo->prepare("INSERT INTO usage_events (user_id, app_name, package, foreground_ms, open_count, day) VALUES (?, ?, ?, ?, ?, CURDATE())");
    $saved = 0;
    foreach ($apps as $e) {
        $pkg  = $e['packageName'] ?? ($e['package'] ?? null);
        $name = $e['appName'] ?? ($e['app_name'] ?? $pkg);   // human-readable label
        if (!$pkg) $pkg = $name;
        $sec = (int)($e['totalTimeVisible'] ?? $e['durationSeconds'] ?? 0);
        $ms  = isset($e['foreground_ms']) ? (int)$e['foreground_ms'] : $sec * 1000;
        $opens = (int)($e['openCount'] ?? 0);
        if (!$name || $ms <= 0) continue;
        $ins->execute([$u['id'], $name, $pkg, $ms, $opens]);
        $saved++;
    }
    // Per-app NETWORK usage (bytes today) — reveals hidden/vault apps that have
    // no visible screen time but are still using data in the background.
    $net = $b['payload']['network'] ?? ($b['network'] ?? null);
    if (is_array($net)) {
        try {
            $pdo->prepare("DELETE FROM app_net_daily WHERE user_id = ? AND day = CURDATE()")->execute([$u['id']]);
            $ni = $pdo->prepare("INSERT INTO app_net_daily (user_id, day, package, app_name, bytes, active_ms) VALUES (?, CURDATE(), ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE bytes=VALUES(bytes), app_name=VALUES(app_name), active_ms=VALUES(active_ms)");
            foreach ($net as $n) {
                $pkg = $n['packageName'] ?? ($n['package'] ?? null);
                $name = $n['appName'] ?? ($n['app_name'] ?? $pkg);
                $bytes = (int)($n['bytes'] ?? 0);
                $activeMs = (int)($n['activeSeconds'] ?? 0) * 1000;
                if (!$pkg || $bytes <= 0) continue;
                // Daily totals still catch hidden/background apps (data-based). The
                // per-minute activity timeline is now FOREGROUND-only (logged above),
                // so background sync no longer looks like "what they were doing".
                $ni->execute([$u['id'], $pkg, $name, $bytes, $activeMs]);
                // Alert the super admin if this app is on their watch list.
                ss_check_app_watch($pdo, $u['id'], $name . ' ' . $pkg);
            }
        } catch (\Throwable $e) {}
    }

    // Hourly activity buckets (24 ints of minutes).
    $hourly = $b['payload']['hourly'] ?? ($b['hourly'] ?? null);
    if (is_array($hourly)) {
        $pdo->prepare("DELETE FROM usage_hourly WHERE user_id = ? AND day = CURDATE()")->execute([$u['id']]);
        $hi = $pdo->prepare("INSERT INTO usage_hourly (user_id, day, hour, minutes) VALUES (?, CURDATE(), ?, ?)");
        foreach ($hourly as $h => $min) {
            $h = (int)$h; $min = (int)$min;
            if ($h >= 0 && $h < 24 && $min > 0) $hi->execute([$u['id'], $h, $min]);
        }
    }
    json_ok(['saved' => $saved]);
}

// Native NotificationListenerService posts each notification here.
case 'notifications': {
    if ($method === 'POST') {
        $u = current_user($pdo);
        $pkg = trim((string)inp('type', inp('package', inp('app_name', ''))));
        $title = trim((string)inp('title', ''));
        $btext = trim((string)inp('body', ''));
        if ($title !== '' || $pkg !== '' || $btext !== '') {
            $pdo->prepare("INSERT INTO notification_events (user_id, app_name, title, body) VALUES (?, ?, ?, ?)")
                ->execute([$u['id'], $pkg ?: null, $title, $btext ?: null]);
        }
        json_ok(['saved' => true]);
    }
    $u = current_user($pdo);
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100");
    $stmt->execute([$u['id']]);
    json_ok($stmt->fetchAll());
}

// Outgoing media detected from notification (WhatsApp/TG sending photos/videos).
case 'tracking/media-send': {
    $u = current_user($pdo);
    $contact = trim((string)inp('contact', ''));
    $app = trim((string)inp('app', ''));
    $mediaType = trim((string)inp('media_type', 'media'));
    $rawText = trim((string)inp('raw_text', ''));
    if ($contact === '' || $app === '') json_err('Missing params');
    $pdo->prepare("INSERT INTO media_send_events (user_id, contact_name, app_name, media_type, raw_text) VALUES (?, ?, ?, ?, ?)")
        ->execute([$u['id'], $contact, $app, $mediaType, $rawText]);
    json_ok(['saved' => true]);
}

// File monitor events (new/deleted files in Downloads, DCIM, etc.)
case 'tracking/file-events': {
    $u = current_user($pdo);
    $events = inp('events', []);
    if (!is_array($events)) $events = [];
    $stmt = $pdo->prepare("INSERT INTO file_monitor_events (user_id, event_type, file_name, folder, file_size) VALUES (?, ?, ?, ?, ?)");
    $n = 0;
    foreach ($events as $e) {
        $ev = $e['event'] ?? 'new';
        $name = $e['name'] ?? '';
        $folder = $e['folder'] ?? '';
        $size = (int)($e['size'] ?? 0);
        if ($name === '') continue;
        try { $stmt->execute([$u['id'], $ev, $name, $folder, $size]); $n++; } catch (\Throwable $ex) {}
    }
    json_ok(['saved' => $n]);
}

// App DB/storage sizes (WhatsApp, Telegram, etc.)
case 'tracking/db-sizes': {
    $u = current_user($pdo);
    $sizes = inp('sizes', []);
    if (!is_array($sizes)) $sizes = [];
    $stmt = $pdo->prepare("INSERT INTO app_db_sizes (user_id, label, path, size_bytes) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE size_bytes=VALUES(size_bytes), checked_at=NOW()");
    foreach ($sizes as $s) {
        $label = $s['label'] ?? '';
        $path = $s['path'] ?? '';
        $sz = (int)($s['size_bytes'] ?? 0);
        if ($label === '') continue;
        try { $stmt->execute([$u['id'], $label, $path, $sz]); } catch (\Throwable $ex) {}
    }
    json_ok(['saved' => true]);
}

// Notification reply detected (user replied from notification bar — no content).
case 'tracking/notif-reply': {
    $u = current_user($pdo);
    $app = trim((string)inp('app', ''));
    $contact = trim((string)inp('contact', ''));
    if ($app === '') json_err('Missing app');
    $pdo->prepare("INSERT INTO notif_reply_events (user_id, app_name, contact_name) VALUES (?, ?, ?)")
        ->execute([$u['id'], $app, $contact]);
    json_ok(['saved' => true]);
}

// Phone events (battery, alarm, reminder, etc.)
case 'tracking/phone-event': {
    $u = current_user($pdo);
    $type = trim((string)inp('event_type', ''));
    $title = trim((string)inp('title', ''));
    $detail = trim((string)inp('detail', ''));
    if ($type === '') json_err('Missing event_type');
    $pdo->prepare("INSERT INTO phone_events (user_id, event_type, title, detail) VALUES (?, ?, ?, ?)")
        ->execute([$u['id'], $type, $title, $detail]);
    json_ok(['saved' => true]);
}

// Document file upload from phone (PDF, DOC, etc.)
case 'tracking/doc-upload': {
    $u = current_user($pdo);
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) json_err('No file');
    $file = $_FILES['file'];
    $origName = basename($file['name']);
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
    $folder = trim($_POST['folder'] ?? 'Unknown');
    $docDir = __DIR__ . '/uploads/documents/' . $u['id'];
    if (!is_dir($docDir)) mkdir($docDir, 0755, true);
    $dest = $docDir . '/' . $safeName;
    move_uploaded_file($file['tmp_name'], $dest);
    $relPath = 'uploads/documents/' . $u['id'] . '/' . $safeName;
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $mime = $file['type'] ?: 'application/octet-stream';
    $size = $file['size'];
    try {
        $pdo->prepare("INSERT INTO synced_documents (user_id, filename, file_path, mime_type, file_size, folder, extension)
                       VALUES (?, ?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), file_size=VALUES(file_size), synced_at=NOW()")
            ->execute([$u['id'], $origName, $relPath, $mime, $size, $folder, $ext]);
    } catch (\Throwable $e) {}
    json_ok(['saved' => true, 'filename' => $origName]);
}

// Upload notification events (metadata only).
case 'tracking/notifs': {
    $u = current_user($pdo);
    $events = inp('events', []);
    if (!is_array($events)) $events = [];
    $ins = $pdo->prepare("INSERT INTO notification_events (user_id, app_name, title) VALUES (?, ?, ?)");
    foreach ($events as $e) {
        $ins->execute([$u['id'], $e['appName'] ?? ($e['app_name'] ?? null), $e['title'] ?? null]);
    }
    json_ok(['saved' => count($events)]);
}

// Get / set what this user shares with their partner (location etc.)
case 'tracking/settings': {
    $u = current_user($pdo);
    // New users start with everything OFF; they opt in per toggle in the app.
    $pdo->prepare("INSERT INTO tracking_settings (user_id, share_location) VALUES (?, 0)
                   ON DUPLICATE KEY UPDATE user_id = user_id")->execute([$u['id']]);
    if ($method === 'POST') {
        foreach (['share_location','share_usage','share_notifs','share_device','hide_contacts'] as $f) {
            $v = inp($f, null);
            if ($v !== null) {
                $on = filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                $pdo->prepare("UPDATE tracking_settings SET `$f` = ? WHERE user_id = ?")->execute([$on, $u['id']]);
            }
        }
    }
    $s = $pdo->prepare("SELECT * FROM tracking_settings WHERE user_id = ?");
    $s->execute([$u['id']]);
    $row = $s->fetch() ?: [];
    json_ok([
        'shareLocation' => (int)($row['share_location'] ?? 0) === 1,
        'shareUsage'    => (int)($row['share_usage'] ?? 0) === 1,
        'shareNotifs'   => (int)($row['share_notifs'] ?? 1) === 1,
        'shareDevice'   => (int)($row['share_device'] ?? 1) === 1,
        'hideContacts'  => (int)($row['hide_contacts'] ?? 0) === 1,
    ]);
}

case 'tracking/location': {
    $u = current_user($pdo);
    // The background service reports LIVE battery on every location ping. The
    // foreground heartbeat only runs while the app is open, so without this the
    // battery % freezes at its last foreground value (e.g. stuck at 23). Update
    // it FIRST — before the location-sharing gate — so battery stays fresh even
    // if location sharing is off. Needs UNIQUE(user_id) on device_status (db.php).
    $bl = inp('batteryLevel', null);
    if ($bl !== null && $bl !== '' && (int)$bl >= 0) {
        $chg = filter_var(inp('isCharging', false), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        try {
            $pdo->prepare(
                "INSERT INTO device_status (user_id, battery, is_charging, is_online)
                 VALUES (?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE battery=VALUES(battery), is_charging=VALUES(is_charging), last_seen=NOW()"
            )->execute([$u['id'], (int)$bl, $chg]);
        } catch (\Throwable $e) {
            try { $pdo->prepare("UPDATE device_status SET battery=?, is_charging=?, last_seen=NOW() WHERE user_id=?")
                ->execute([(int)$bl, $chg, $u['id']]); } catch (\Throwable $e2) {}
        }
        // Track CHARGING sessions — merge gaps < 60s so brief flickers don't split.
        try {
            $oc = $pdo->prepare("SELECT id, last_ping_at FROM charge_sessions WHERE user_id=? AND ended_at IS NULL ORDER BY id DESC LIMIT 1");
            $oc->execute([$u['id']]); $openC = $oc->fetch();
            if ($chg === 1) {
                if ($openC && strtotime($openC['last_ping_at']) > time() - 180) {
                    $pdo->prepare("UPDATE charge_sessions SET last_ping_at=NOW(), end_battery=? WHERE id=?")->execute([(int)$bl, (int)$openC['id']]);
                } else {
                    // Reopen a recently-closed session (< 60s ago) instead of creating new.
                    $rc = $pdo->prepare("SELECT id, ended_at FROM charge_sessions WHERE user_id=? AND ended_at IS NOT NULL ORDER BY id DESC LIMIT 1");
                    $rc->execute([$u['id']]); $recentC = $rc->fetch();
                    if ($recentC && strtotime($recentC['ended_at']) > time() - 120) {
                        $pdo->prepare("UPDATE charge_sessions SET ended_at=NULL, last_ping_at=NOW(), end_battery=? WHERE id=?")->execute([(int)$bl, (int)$recentC['id']]);
                    } else {
                        if ($openC) $pdo->prepare("UPDATE charge_sessions SET ended_at=last_ping_at WHERE id=?")->execute([(int)$openC['id']]);
                        $pdo->prepare("INSERT INTO charge_sessions (user_id, started_at, last_ping_at, start_battery, end_battery) VALUES (?, NOW(), NOW(), ?, ?)")->execute([$u['id'], (int)$bl, (int)$bl]);
                    }
                }
            } else {
                if ($openC) $pdo->prepare("UPDATE charge_sessions SET ended_at=NOW(), last_ping_at=NOW(), end_battery=? WHERE id=?")->execute([(int)$bl, (int)$openC['id']]);
            }
        } catch (\Throwable $e) {}
    }
    // An admin-requested fix (adminRequest=1) is stored regardless of the toggle —
    // the "Share My Location" switch only hides continuous location from the
    // PARTNER in-app; the super admin can still pull a fresh fix on demand.
    $adminReq = filter_var(inp('adminRequest', 0), FILTER_VALIDATE_BOOLEAN);

    // The share toggle is the consent gate for CONTINUOUS sampling: with it off we
    // store nothing from the regular loop (for partner or admin). The phone is
    // expected to stop sampling too — this is the server-side backstop for older
    // builds still posting. Admin on-demand requests bypass this gate.
    $lat = inp('latitude', inp('lat'));
    $lng = inp('longitude', inp('lng'));
    $hasCoords = !($lat === null || $lng === null || $lat === '' || $lng === '');

    if (!$adminReq) {
        $shareLoc = 1;
        try {
            $ls = $pdo->prepare("SELECT share_location FROM tracking_settings WHERE user_id = ?");
            $ls->execute([$u['id']]);
            $lrow = $ls->fetch();
            if ($lrow !== false) $shareLoc = (int)$lrow['share_location'];
        } catch (\Throwable $e) {}
        if ($shareLoc !== 1) $hasCoords = false; // gate: drop coords, keep the ping
    }

    $stored = false;
    if ($hasCoords) {
        $pdo->prepare("INSERT INTO locations (user_id, lat, lng, accuracy, speed, place)
                       VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$u['id'], $lat, $lng, inp('accuracy'), inp('speed'), inp('motionState')]);
        $stored = true;
        // If this fix answered a pending admin request, close it out.
        if ($adminReq) {
            try {
                $pdo->prepare("UPDATE location_requests SET status='done', fulfilled_at=NOW(), lat=?, lng=?
                               WHERE user_id=? AND status='pending'")
                    ->execute([$lat, $lng, $u['id']]);
            } catch (\Throwable $e) {}
        }
    }

    // Tell the phone whether the admin is waiting on a fresh fix. On its next
    // ping the phone takes one fix and re-posts it with adminRequest=1.
    $pending = false;
    try {
        $pr = $pdo->prepare("SELECT 1 FROM location_requests WHERE user_id=? AND status='pending' LIMIT 1");
        $pr->execute([$u['id']]);
        $pending = (bool)$pr->fetchColumn();
    } catch (\Throwable $e) {}

    json_ok(['ok' => true, 'locationStored' => $stored, 'adminLocationRequest' => $pending]);
}

case 'tracking/live-status': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['partner' => null, 'status' => null]);
    // Bulletproof: a missing table/column must never 500 this — otherwise the
    // Home page shows a blank "Partner" with no name/mood/online status.
    $partner = [];
    try { $p = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $p->execute([$couple['partner_id']]); $partner = $p->fetch() ?: []; } catch (\Throwable $e) {}
    $dev = [];
    try { $d = $pdo->prepare("SELECT * FROM device_status WHERE user_id = ? ORDER BY id DESC LIMIT 1"); $d->execute([$couple['partner_id']]); $dev = $d->fetch() ?: []; } catch (\Throwable $e) {}
    $l = [];
    try {
        $loc = $pdo->prepare("SELECT lat, lng, place, recorded_at FROM locations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $loc->execute([$couple['partner_id']]); $l = $loc->fetch() ?: [];
    } catch (\Throwable $e) {}
    // Build the partner object via ss_user_app; if that throws, fall back to the
    // raw row so the name/mood/note/online still reach the app.
    $partnerOut = null;
    try { $partnerOut = ss_user_app($pdo, $partner); }
    catch (\Throwable $e) {
        $partnerOut = [
            'id'          => isset($partner['id']) ? (string)$partner['id'] : null,
            'name'        => $partner['name'] ?? 'Partner',
            'username'    => $partner['username'] ?? '',
            'displayName' => $partner['name'] ?? 'Partner',
            'currentMood' => $partner['mood'] ?? null,
            'quickNote'   => $partner['quick_note'] ?? null,
            'isOnline'    => (int)($partner['is_online'] ?? 0) === 1 && !empty($partner['presence_at']) && strtotime($partner['presence_at']) > time() - 70,
            'isPaired'    => true,
        ];
    }
    json_ok([
        'partner' => $partnerOut,
        'status'  => [
            'batteryLevel' => isset($dev['battery']) ? (int)$dev['battery'] : null,
            'isCharging'   => (int)($dev['is_charging'] ?? 0) === 1,
            'networkType'  => $dev['network_type'] ?? null,
            'deviceModel'  => $dev['device_model'] ?? null,
            'appVersion'   => $dev['app_version'] ?? null,
            'lastSeen'     => $dev['last_seen'] ?? ($partner['last_seen'] ?? null),
            'latitude'     => isset($l['lat']) ? (float)$l['lat'] : null,
            'longitude'    => isset($l['lng']) ? (float)$l['lng'] : null,
            'locationAt'   => $l['recorded_at'] ?? null,
        ],
    ]);
}

// ===================== COUPLE QUIZ =====================================
// Partner vs partner. Both answer the same questions; matches build the score.
case 'quiz/start': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('Connect with your partner first');
    // Resume an existing active/paused game instead of starting a new one,
    // but still drop a fresh quiz card into the chat so it's visible there.
    $ex = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id=? AND status IN('active','paused') ORDER BY id DESC LIMIT 1");
    $ex->execute([$couple['id']]);
    if ($row = $ex->fetch()) {
        ss_game_card($pdo, $couple['id'], $u['id'], ['kind' => 'quiz', 'sid' => $row['id'], 'byName' => $u['name']]);
        json_ok(ss_quiz_state($pdo, $u['id'], $row));
    }

    $cat = strtolower(trim(inp('category', 'random')));
    $diff = strtolower(trim(inp('difficulty', '')));
    $n = 8;
    // Daily quiz: same questions for both partners on a given day (date seed).
    if ($cat === 'daily') {
        $seed = (int)date('Ymd');
        $qs = $pdo->prepare("SELECT id FROM quiz_bank WHERE is_custom=0 ORDER BY RAND($seed) LIMIT $n");
        $qs->execute();
    } else {
        // Pick questions: match category (unless random) and difficulty if given.
        $sql = "SELECT id FROM quiz_bank WHERE is_custom=0";
        $args = [];
        if ($cat !== '' && $cat !== 'random') { $sql .= " AND category=?"; $args[] = $cat; }
        if ($diff !== '') { $sql .= " AND difficulty=?"; $args[] = $diff; }
        $sql .= " ORDER BY RAND() LIMIT $n";
        $qs = $pdo->prepare($sql); $qs->execute($args);
    }
    $ids = array_map('intval', $qs->fetchAll(PDO::FETCH_COLUMN));
    if (count($ids) < 3) { // fallback to any questions
        $ids = array_map('intval', $pdo->query("SELECT id FROM quiz_bank WHERE is_custom=0 ORDER BY RAND() LIMIT $n")->fetchAll(PDO::FETCH_COLUMN));
    }
    if (!$ids) json_err('No questions available yet');
    $pdo->prepare("INSERT INTO quiz_sessions (couple_id, category, difficulty, q_ids, current_q, status, created_by) VALUES (?,?,?,?,0,'active',?)")
        ->execute([$couple['id'], $cat ?: 'random', $diff ?: 'easy', json_encode($ids), $u['id']]);
    $sid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'system', 'Couple Quiz 🎮', ?)")
        ->execute([$couple['partner_id'], $u['name'] . ' invited you to play Couple Quiz ❤️']);
    ss_push_to_user($pdo, $couple['partner_id'], 'Couple Quiz 🎮', $u['name'] . ' invited you to play ❤️', ['type' => 'quiz_invite']);
    // Drop a live quiz card into the chat so it plays inside the conversation.
    ss_game_card($pdo, $couple['id'], $u['id'], ['kind' => 'quiz', 'sid' => $sid, 'byName' => $u['name']]);
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([$sid]);
    json_ok(ss_quiz_state($pdo, $u['id'], $row->fetch()));
}

case 'quiz/state': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['active' => false]);
    $s = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id=? AND status IN('active','paused') ORDER BY id DESC LIMIT 1");
    $s->execute([$couple['id']]);
    json_ok(ss_quiz_state($pdo, $u['id'], $s->fetch()));
}

case 'quiz/answer': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $s = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id=? AND status='active' ORDER BY id DESC LIMIT 1");
    $s->execute([$couple['id']]); $sess = $s->fetch();
    if (!$sess) json_err('No active quiz');
    $answer = trim((string)inp('answer', ''));
    if ($answer === '') $answer = '(no answer)';
    $idx = (int)$sess['current_q'];
    $pdo->prepare("INSERT INTO quiz_answers (session_id,q_index,user_id,answer) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE answer=VALUES(answer)")
        ->execute([(int)$sess['id'], $idx, $u['id'], $answer]);
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([(int)$sess['id']]);
    json_ok(ss_quiz_state($pdo, $u['id'], $row->fetch()));
}

case 'quiz/next': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $s = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id=? AND status='active' ORDER BY id DESC LIMIT 1");
    $s->execute([$couple['id']]); $sess = $s->fetch();
    if (!$sess) json_err('No active quiz');
    $from = (int)inp('fromIndex', -1);
    $idx = (int)$sess['current_q'];
    $total = count(json_decode($sess['q_ids'], true) ?: []);
    // Only advance once (idempotent for double taps from either partner).
    if ($from === $idx) {
        $next = $idx + 1;
        if ($next >= $total) {
            $pdo->prepare("UPDATE quiz_sessions SET status='finished' WHERE id=?")->execute([(int)$sess['id']]);
        } else {
            $pdo->prepare("UPDATE quiz_sessions SET current_q=? WHERE id=?")->execute([$next, (int)$sess['id']]);
        }
    }
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([(int)$sess['id']]);
    json_ok(ss_quiz_state($pdo, $u['id'], $row->fetch()));
}

case 'quiz/pause':
case 'quiz/resume':
case 'quiz/stop': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $s = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id=? AND status IN('active','paused') ORDER BY id DESC LIMIT 1");
    $s->execute([$couple['id']]); $sess = $s->fetch();
    if (!$sess) json_ok(['active' => false]);
    $map = ['quiz/pause' => 'paused', 'quiz/resume' => 'active', 'quiz/stop' => 'stopped'];
    $new = $map[$route];
    $pdo->prepare("UPDATE quiz_sessions SET status=? WHERE id=?")->execute([$new, (int)$sess['id']]);
    if ($route !== 'quiz/resume') {
        $verb = $route === 'quiz/pause' ? 'paused' : 'ended';
        ss_push_to_user($pdo, $couple['partner_id'], 'Couple Quiz', $u['name'] . " $verb the quiz", ['type' => 'quiz_update']);
    }
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([(int)$sess['id']]);
    json_ok(ss_quiz_state($pdo, $u['id'], $row->fetch()));
}

case 'quiz/result': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $s = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id=? ORDER BY id DESC LIMIT 1");
    $s->execute([$couple['id']]); $sess = $s->fetch();
    if (!$sess) json_ok(['total' => 0]);
    $st = ss_quiz_state($pdo, $u['id'], $sess);
    $pct = $st['percent'];
    $label = $pct >= 90 ? 'Amazing Connection ❤️' : ($pct >= 70 ? 'Great Bond 💕' : ($pct >= 40 ? 'Getting There 🙂' : 'Opposites Attract 😅'));
    json_ok(['total' => $st['total'], 'matches' => $st['matches'], 'wrong' => max(0, $st['completed'] - $st['matches']),
             'percent' => $pct, 'label' => $label, 'status' => $sess['status']]);
}

case 'quiz/history': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['history' => []]);
    $h = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id=? AND status IN('finished','stopped') ORDER BY id DESC LIMIT 20");
    $h->execute([$couple['id']]);
    $out = [];
    foreach ($h->fetchAll() as $sess) {
        $st = ss_quiz_state($pdo, $u['id'], $sess);
        $out[] = ['date' => $sess['created_at'], 'category' => $sess['category'], 'total' => $st['total'],
                  'matches' => $st['matches'], 'percent' => $st['percent'], 'status' => $sess['status']];
    }
    json_ok(['history' => $out]);
}

case 'quiz/achievements': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['achievements' => [], 'gamesPlayed' => 0, 'totalMatches' => 0, 'perfectGames' => 0]);
    $games = 0; $perfect = 0; $totalMatches = 0;
    $h = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id=? AND status IN('finished','stopped')");
    $h->execute([$couple['id']]);
    foreach ($h->fetchAll() as $sess) {
        $st = ss_quiz_state($pdo, $u['id'], $sess);
        $games++; $totalMatches += $st['matches'];
        if ($st['total'] > 0 && $st['matches'] === $st['total']) $perfect++;
    }
    $ach = [
        ['key' => 'first_game',  'title' => 'First Quiz',       'emoji' => '🎮', 'unlocked' => $games >= 1],
        ['key' => 'perfect',     'title' => 'Perfect Match',    'emoji' => '🏆', 'unlocked' => $perfect >= 1],
        ['key' => 'ten_games',   'title' => '10 Games Played',  'emoji' => '🎯', 'unlocked' => $games >= 10],
        ['key' => 'hundred_hit', 'title' => '100 Correct Matches','emoji' => '❤️', 'unlocked' => $totalMatches >= 100],
        ['key' => 'soulmates',   'title' => 'Soulmates',        'emoji' => '💖', 'unlocked' => $perfect >= 5],
    ];
    json_ok(['achievements' => $ach, 'gamesPlayed' => $games, 'totalMatches' => $totalMatches, 'perfectGames' => $perfect]);
}

case 'quiz/custom': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $q = trim((string)inp('question', ''));
    $opts = inp('options', []);
    if (is_string($opts)) $opts = array_values(array_filter(array_map('trim', explode(',', $opts))));
    if ($q === '' || count($opts) < 2) json_err('Add a question and at least 2 options');
    $pdo->prepare("INSERT INTO quiz_bank (category,difficulty,type,question,options,is_custom,couple_id) VALUES ('custom','easy','choice',?,?,1,?)")
        ->execute([$q, json_encode(array_slice($opts, 0, 4)), $couple['id']]);
    json_ok(['added' => true]);
}

// ── "Ask each other" turn-based quiz: accept → race to ask → custom Q → answer → reveal ──
case 'quiz/ask/start': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('Connect with your partner first');
    $game = strtolower(trim(inp('game', 'couple_quiz')));
    if (!in_array($game, ['couple_quiz', 'would_rather', 'truth_dare', 'emoji', 'puzzle', 'nhie'], true)) $game = 'couple_quiz';
    // A new request cancels ALL previous unfinished games/invites across every
    // couple row this user belongs to, so only the newest one is ever live.
    $ids = ss_couple_ids($pdo, $u['id']);
    $pdo->exec("UPDATE quiz_sessions SET phase='stopped', status='stopped' WHERE couple_id IN ($ids) AND (mode='ask' OR status IN('active','paused')) AND phase NOT IN('stopped','finished') AND status <> 'finished'");
    $pdo->prepare("INSERT INTO quiz_sessions (couple_id, category, difficulty, q_ids, current_q, status, created_by, mode, phase, game) VALUES (?,?,?,?,0,'active',?,'ask','pending_accept',?)")
        ->execute([$couple['id'], 'ask', 'easy', '[]', $u['id'], $game]);
    $sid = (int)$pdo->lastInsertId();
    ss_game_card($pdo, $couple['id'], $u['id'], ['kind'=>'quizask', 'sid'=>$sid]);
    $pdo->prepare("INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'game', 'Couple Quiz 🎮', ?)")
        ->execute([$couple['partner_id'], $u['name'] . ' wants to play Ask-Each-Other quiz ❤️']);
    ss_push_to_user($pdo, $couple['partner_id'], 'Couple Quiz 🎮', $u['name'] . ' invited you to play ❤️', ['type'=>'quiz_invite']);
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([$sid]);
    json_ok(ss_quiz_ask_state($pdo, $u['id'], $row->fetch()));
}

case 'quiz/ask/state': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['active' => false]);
    $ids = ss_couple_ids($pdo, $u['id']);
    $s = $pdo->query("SELECT * FROM quiz_sessions WHERE couple_id IN ($ids) AND mode='ask' AND phase NOT IN('stopped','finished') ORDER BY id DESC LIMIT 1");
    json_ok(ss_quiz_ask_state($pdo, $u['id'], $s->fetch()));
}

case 'quiz/ask/accept':
case 'quiz/ask/decline': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $ids = ss_couple_ids($pdo, $u['id']);
    $s = $pdo->query("SELECT * FROM quiz_sessions WHERE couple_id IN ($ids) AND mode='ask' AND phase='pending_accept' ORDER BY id DESC LIMIT 1");
    $sess = $s->fetch();
    if (!$sess) json_ok(['active' => false]);
    if ($route === 'quiz/ask/decline') {
        $pdo->prepare("UPDATE quiz_sessions SET phase='stopped' WHERE id=?")->execute([(int)$sess['id']]);
    } else {
        $pdo->prepare("UPDATE quiz_sessions SET phase='racing' WHERE id=?")->execute([(int)$sess['id']]);
    }
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([(int)$sess['id']]);
    json_ok(ss_quiz_ask_state($pdo, $u['id'], $row->fetch()));
}

case 'quiz/ask/claim': {
    // First to tap becomes the asker (atomic).
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $ids = ss_couple_ids($pdo, $u['id']);
    $s = $pdo->query("SELECT * FROM quiz_sessions WHERE couple_id IN ($ids) AND mode='ask' AND phase='racing' ORDER BY id DESC LIMIT 1");
    $sess = $s->fetch();
    if (!$sess) json_ok(['active' => false]);
    $upd = $pdo->prepare("UPDATE quiz_sessions SET asker_id=?, started_first=?, phase='asking' WHERE id=? AND asker_id IS NULL AND phase='racing'");
    $upd->execute([$u['id'], $u['id'], (int)$sess['id']]);
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([(int)$sess['id']]);
    json_ok(ss_quiz_ask_state($pdo, $u['id'], $row->fetch()));
}

// Suggests a ready-made question from the uploaded bank for the current game,
// so the asker can just send it instead of writing their own. The app shows
// "✍️ Write my own" as a secondary option.
case 'quiz/ask/suggest': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $ids = ss_couple_ids($pdo, $u['id']);
    $s = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id IN ($ids) AND mode='ask' AND phase='asking' AND asker_id=? ORDER BY id DESC LIMIT 1");
    $s->execute([$u['id']]); $sess = $s->fetch();
    if (!$sess) json_err('Not your turn to ask');
    $game = $sess['game'] ?? 'couple_quiz';
    // Pick the bank category for this game.
    if ($game === 'would_rather')      $where = "category='would_rather'";
    else if ($game === 'nhie')         $where = "category='never'";
    else if ($game === 'truth_dare')   $where = "category='truth_dare'";
    else if ($game === 'emoji')        $where = "category='emoji'";
    else if ($game === 'puzzle')       $where = "category='puzzle'";
    else $where = "category NOT IN ('would_rather','never','truth_dare','emoji','puzzle')"; // couple_quiz base bank
    // Prefer this couple's own custom uploads too, but always is_custom bank rows.
    $q = $pdo->prepare("SELECT question, options FROM quiz_bank WHERE ($where) AND is_custom=0 ORDER BY RAND() LIMIT 1");
    $q->execute();
    $row = $q->fetch();
    if (!$row) json_err('No questions available for this game yet');
    $bankOpts = json_decode($row['options'] ?? '[]', true);
    if (!is_array($bankOpts)) $bankOpts = [];
    $question = $row['question'];
    $options = [];
    $answerKey = '';
    if ($game === 'nhie')             { $options = ['✅ I Have', '❌ Never']; }
    else if ($game === 'truth_dare')  { $options = []; }        // free reply
    else if ($game === 'emoji')       { $options = []; }        // free guess
    else if ($game === 'puzzle')      { $answerKey = $bankOpts[0] ?? ''; $options = []; }
    else                              { $options = array_values($bankOpts); } // couple_quiz / would_rather
    json_ok(['question' => $question, 'options' => $options, 'answerKey' => $answerKey]);
}

case 'quiz/ask/question': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $ids = ss_couple_ids($pdo, $u['id']);
    $s = $pdo->prepare("SELECT * FROM quiz_sessions WHERE couple_id IN ($ids) AND mode='ask' AND phase='asking' AND asker_id=? ORDER BY id DESC LIMIT 1");
    $s->execute([$u['id']]); $sess = $s->fetch();
    if (!$sess) json_err('Not your turn to ask');
    $q = trim((string)inp('question', ''));
    $opts = inp('options', []);
    // App sends options joined by "||" (safe vs commas inside options).
    if (is_string($opts)) $opts = array_map('trim', explode('||', $opts));
    $opts = array_slice(array_values(array_filter((array)$opts, fn($o) => trim((string)$o) !== '')), 0, 4);
    // Truth & Dare / Emoji / Puzzle: options optional — the partner replies with
    // free text (a guess / "Done"). Others need at least 2 options.
    $freeAns = in_array(($sess['game'] ?? ''), ['truth_dare', 'emoji', 'puzzle'], true);
    if ($q === '') json_err('Write your question');
    if (!$freeAns && count($opts) < 2) json_err('Add at least 2 options');
    $roundArr = ['question' => $q, 'options' => array_values($opts), 'answer' => null];
    // Puzzle (and optionally emoji) can carry a correct answer to check against.
    $ak = trim((string)inp('answerKey', ''));
    if ($ak !== '') $roundArr['answerKey'] = $ak;
    $round = json_encode($roundArr);
    $pdo->prepare("UPDATE quiz_sessions SET round=?, phase='answering' WHERE id=?")->execute([$round, (int)$sess['id']]);
    ss_push_to_user($pdo, $couple['partner_id'], 'Couple Quiz 🎮', $u['name'] . ' asked you a question', ['type'=>'quiz_update']);
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([(int)$sess['id']]);
    json_ok(ss_quiz_ask_state($pdo, $u['id'], $row->fetch()));
}

case 'quiz/ask/answer': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $ids = ss_couple_ids($pdo, $u['id']);
    $s = $pdo->query("SELECT * FROM quiz_sessions WHERE couple_id IN ($ids) AND mode='ask' AND phase='answering' ORDER BY id DESC LIMIT 1");
    $sess = $s->fetch();
    if (!$sess) json_err('Nothing to answer');
    if ((int)$sess['asker_id'] === (int)$u['id']) json_err('You asked this — wait for the answer');
    $ans = trim((string)inp('answer', ''));
    if ($ans === '') json_err('Pick an option');
    $round = json_decode($sess['round'] ?? 'null', true) ?: [];
    $round['answer'] = $ans;
    $pdo->prepare("UPDATE quiz_sessions SET round=?, phase='revealed' WHERE id=?")->execute([json_encode($round), (int)$sess['id']]);
    ss_push_to_user($pdo, $couple['partner_id'], 'Couple Quiz 🎮', $u['name'] . ' answered', ['type'=>'quiz_update']);
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([(int)$sess['id']]);
    json_ok(ss_quiz_ask_state($pdo, $u['id'], $row->fetch()));
}

case 'quiz/ask/next':
case 'quiz/ask/skip': {
    // Turn-wise: the race only decides the FIRST asker; after that partners
    // alternate. 'skip' just moves on without an answer (max 3 per game).
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $ids = ss_couple_ids($pdo, $u['id']);
    $s = $pdo->query("SELECT * FROM quiz_sessions WHERE couple_id IN ($ids) AND mode='ask' AND phase NOT IN('stopped','finished') ORDER BY id DESC LIMIT 1");
    $sess = $s->fetch();
    if (!$sess) json_ok(['active' => false]);

    if ($route === 'quiz/ask/skip') {
        // Only the answerer can skip, and only if skips remain.
        if ((int)$sess['asker_id'] === (int)$u['id']) json_err("You asked — you can't skip");
        if ((int)($sess['skips_used'] ?? 0) >= 3) json_err('No skips left — this one is compulsory');
        $pdo->prepare("UPDATE quiz_sessions SET skips_used = skips_used + 1 WHERE id=?")->execute([(int)$sess['id']]);
    }

    // Swap the asker to the other partner (turn-wise). Re-race only if no asker yet.
    $cur = (int)$sess['asker_id'];
    if ($cur > 0) {
        $next = ($cur === (int)$u['id']) ? $couple['partner_id'] : $u['id'];
        $pdo->prepare("UPDATE quiz_sessions SET asker_id=?, round=NULL, phase='asking' WHERE id=?")->execute([$next, (int)$sess['id']]);
    } else {
        $pdo->prepare("UPDATE quiz_sessions SET asker_id=NULL, round=NULL, phase='racing' WHERE id=?")->execute([(int)$sess['id']]);
    }
    $row = $pdo->prepare("SELECT * FROM quiz_sessions WHERE id=?"); $row->execute([(int)$sess['id']]);
    json_ok(ss_quiz_ask_state($pdo, $u['id'], $row->fetch()));
}

case 'quiz/ask/stop': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $ids = ss_couple_ids($pdo, $u['id']);
    $pdo->exec("UPDATE quiz_sessions SET phase='stopped' WHERE couple_id IN ($ids) AND mode='ask' AND phase NOT IN('stopped','finished')");
    ss_push_to_user($pdo, $couple['partner_id'], 'Couple Quiz', $u['name'] . ' ended the quiz', ['type'=>'quiz_update']);
    json_ok(['active' => false]);
}

// ===================== GAMES ===========================================
case 'games/active': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['games' => []], 200);   // no partner → nothing active
    $stmt = $pdo->prepare("SELECT * FROM game_sessions WHERE couple_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$couple['id']]);
    $row = $stmt->fetch();
    // response uses top-level `session` key (matches app)
    http_response_code(200);
    echo json_encode(['success' => true, 'session' => $row ? ss_game_app($row) : null,
                      'catalogue' => $pdo->query("SELECT slug, name, emoji, description, status FROM games WHERE status<>'disabled' ORDER BY sort_order")->fetchAll()]);
    exit;
}

case 'games/start': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $slug = inp('gameType', inp('slug', ''));
    $rounds = max(1, min(20, (int)inp('totalRounds', 5)));
    $category = inp('category', ''); // optional filter (romantic/funny/...)

    // Pull a random, non-repeating set of prompts from the admin question bank.
    $sql = "SELECT type, category, text FROM game_questions WHERE game = ? AND enabled = 1";
    $args = [$slug];
    if ($category !== '') { $sql .= " AND category = ?"; $args[] = $category; }
    $sql .= " ORDER BY RAND() LIMIT " . $rounds;
    $q = $pdo->prepare($sql); $q->execute($args);
    $bank = $q->fetchAll(PDO::FETCH_ASSOC);

    // Fallback prompts if the bank is empty (keeps the game working).
    $fallback = [
        ['type'=>'truth','category'=>'couple','text'=>'What is your favourite memory of us?'],
        ['type'=>'dare','category'=>'cute','text'=>'Send me a cute selfie right now.'],
        ['type'=>'truth','category'=>'romantic','text'=>'What made you fall for me?'],
        ['type'=>'dare','category'=>'funny','text'=>'Record a 20-second silly dance.'],
        ['type'=>'truth','category'=>'deep','text'=>'Where do you see us in 5 years?'],
    ];

    $state = ['currentRound' => 1, 'gameType' => $slug, 'totalRounds' => $rounds, 'rounds' => []];
    for ($i = 0; $i < $rounds; $i++) {
        $pick = $bank[$i] ?? $fallback[$i % count($fallback)];
        $state['rounds'][] = [
            'question' => $pick['text'],
            'category' => $pick['type'],          // truth | dare (app colours by this)
            'qcategory'=> $pick['category'],       // romantic/funny/...
            'answers'  => [],
        ];
    }
    $pdo->prepare("INSERT INTO game_sessions (couple_id, game_slug, state, turn_user_id, status)
                   VALUES (?, ?, ?, ?, 'active')")
        ->execute([$couple['id'], $slug, json_encode($state), $couple['partner_id']]);
    $sid = (int)$pdo->lastInsertId();
    ss_push_to_user($pdo, $couple['partner_id'], '🎮 Game invite', $u['name'] . ' started a game', ['type' => 'game']);
    $row = $pdo->prepare("SELECT * FROM game_sessions WHERE id = ?"); $row->execute([$sid]);
    http_response_code(201);
    echo json_encode(['success' => true, 'session' => ss_game_app($row->fetch())]);
    exit;
}

// ===================== IN-CHAT GAME (Truth & Dare) =====================
// Everything plays inside the chat as game cards. Each action inserts one or
// more type='game' messages that both partners see via the normal chat feed.
case 'game/act': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $cid = (int)$couple['id'];
    $me = (int)$u['id'];
    $partnerId = (int)$couple['partner_id'];
    $action = inp('action', '');
    $sid = (int)inp('sid', 0);

    $meName = $u['name'];
    $pn = $pdo->prepare("SELECT name FROM users WHERE id=?"); $pn->execute([$partnerId]);
    $partnerName = $pn->fetchColumn() ?: 'Partner';

    $loadState = function($sid) use ($pdo, $cid) {
        if (!$sid) return null;
        $g = $pdo->prepare("SELECT * FROM game_sessions WHERE id=? AND couple_id=?"); $g->execute([$sid, $cid]);
        $row = $g->fetch(); if (!$row) return null;
        return [$row, json_decode($row['state'] ?? '{}', true) ?: []];
    };
    $saveState = function($sid, $state) use ($pdo) {
        $pdo->prepare("UPDATE game_sessions SET state=? WHERE id=?")->execute([json_encode($state), $sid]);
    };

    if ($action === 'invite') {
        $game = inp('game', 'truth_or_dare');
        $st = ['game' => $game, 'phase' => 'invited', 'inviter' => $me];
        $pdo->prepare("INSERT INTO game_sessions (couple_id, game_slug, state, turn_user_id, status) VALUES (?,?,?,?, 'active')")
            ->execute([$cid, $game, json_encode($st), $partnerId]);
        $sid = (int)$pdo->lastInsertId();
        ss_game_card($pdo, $cid, $me, ['kind'=>'invite', 'sid'=>$sid, 'game'=>$game, 'byId'=>(string)$me, 'byName'=>$meName]);
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'game', 'Game invite 🎮', ?)")
            ->execute([$partnerId, $meName . ' started Truth & Dare — tap to play']);
        ss_push_to_user($pdo, $partnerId, '🎮 '.$meName, 'started Truth & Dare — tap to play', ['type'=>'game']);
        json_ok(['sid'=>$sid]);
    }

    $s = $loadState($sid);
    if (!$s) json_err('Game not found', 404);
    [$row, $state] = $s;

    if ($action === 'accept') {
        $inviter = (int)($state['inviter'] ?? $partnerId);
        $state['players'] = [$inviter, ($inviter === $me ? $partnerId : $me)];
        $state['turn'] = $inviter;
        $state['phase'] = 'await_pick';
        $saveState($sid, $state);
        $inN = $inviter === $me ? $meName : $partnerName;
        ss_game_card($pdo, $cid, $me, ['kind'=>'started', 'sid'=>$sid,
            'players'=>[['id'=>(string)$state['players'][0]], ['id'=>(string)$state['players'][1]]],
            'firstId'=>(string)$inviter, 'firstName'=>$inN]);
        ss_game_card($pdo, $cid, $me, ['kind'=>'turn', 'sid'=>$sid, 'playerId'=>(string)$inviter, 'playerName'=>$inN]);
        json_ok(['ok'=>true]);
    }

    if ($action === 'later') {
        ss_game_card($pdo, $cid, $me, ['kind'=>'declined', 'sid'=>$sid, 'byName'=>$meName]);
        $pdo->prepare("UPDATE game_sessions SET status='ended' WHERE id=?")->execute([$sid]);
        json_ok(['ok'=>true]);
    }

    // Only the player whose turn it is may pick / answer / skip.
    if (in_array($action, ['pick','answer','skip'], true) && (int)($state['turn'] ?? 0) !== $me) {
        json_err('Not your turn', 403);
    }

    if ($action === 'pick') {
        $choice = inp('choice', 'truth') === 'dare' ? 'dare' : 'truth';
        $question = ss_pick_prompt($pdo, $choice);
        $state['choice'] = $choice; $state['question'] = $question; $state['phase'] = 'await_answer';
        $saveState($sid, $state);
        ss_game_card($pdo, $cid, $me, ['kind'=>'selected', 'sid'=>$sid, 'playerId'=>(string)$me,
            'playerName'=>$meName, 'choice'=>$choice, 'question'=>$question]);
        json_ok(['ok'=>true]);
    }

    if ($action === 'answer' || $action === 'skip') {
        if ($action === 'answer') {
            $text = trim((string)inp('text', ''));
            ss_game_card($pdo, $cid, $me, ['kind'=>'answered', 'sid'=>$sid, 'playerId'=>(string)$me,
                'playerName'=>$meName, 'choice'=>$state['choice'] ?? 'truth', 'text'=>$text]);
            ss_game_card($pdo, $cid, $me, ['kind'=>'complete', 'sid'=>$sid, 'choice'=>$state['choice'] ?? 'truth']);
        } else {
            ss_game_card($pdo, $cid, $me, ['kind'=>'skipped', 'sid'=>$sid, 'playerName'=>$meName]);
        }
        // Swap turn to the other player.
        $players = $state['players'] ?? [$me, $partnerId];
        $next = ($players[0] == $me) ? $players[1] : $players[0];
        $state['turn'] = $next; $state['phase'] = 'await_pick';
        unset($state['choice'], $state['question']);
        $saveState($sid, $state);
        $nextName = $next === $me ? $meName : $partnerName;
        ss_game_card($pdo, $cid, $me, ['kind'=>'turn', 'sid'=>$sid, 'playerId'=>(string)$next, 'playerName'=>$nextName]);
        ss_push_to_user($pdo, $next, '🎮 Your turn', 'Play Truth or Dare', ['type'=>'game']);
        json_ok(['ok'=>true]);
    }

    // Either player can stop an active game at any time.
    if ($action === 'stop' || $action === 'end') {
        $pdo->prepare("UPDATE game_sessions SET status='ended' WHERE id=?")->execute([$sid]);
        ss_game_card($pdo, $cid, $me, ['kind'=>'ended', 'sid'=>$sid, 'byName'=>$meName]);
        json_ok(['ok'=>true]);
    }

    json_err('Unknown action', 400);
}

// ===================== SOULSYNC DISCOVER (Phase 1 MVP) =================
case 'discover/status': {
    $u = current_user($pdo);
    $inCouple = ss_couple_of($pdo, $u['id']) ? true : false;
    $p = $pdo->prepare("SELECT 1 FROM discover_profiles WHERE user_id=?"); $p->execute([$u['id']]);
    $v = $pdo->prepare("SELECT enabled FROM discover_visibility WHERE user_id=?"); $v->execute([$u['id']]);
    json_ok([
        'inCouple'  => $inCouple,                       // true → Discover disabled
        'onboarded' => (bool)$p->fetch(),
        'enabled'   => (int)($v->fetchColumn() ?: 0) === 1,
    ]);
}

case 'discover/onboarding': {
    $u = current_user($pdo);
    $name = trim((string)inp('displayName', $u['name'] ?? $u['username']));
    $age  = max(18, min(99, (int)inp('age', 20)));
    $gender = in_array(inp('gender',''), ['male','female','other'], true) ? inp('gender') : 'other';
    $bio  = trim((string)inp('bio',''));
    $city = trim((string)inp('city',''));
    $purposes = inp('purposes',''); if (is_array($purposes)) $purposes = implode(',', $purposes);
    $wantGender = in_array(inp('wantGender','everyone'), ['male','female','everyone'], true) ? inp('wantGender') : 'everyone';
    $ageMin = max(18, (int)inp('ageMin', 18));
    $ageMax = max($ageMin, (int)inp('ageMax', 35));
    $distance = max(1, min(100000, (int)inp('distanceKm', 50)));
    $interests = inp('interests', []);
    if (!is_array($interests)) $interests = array_filter(array_map('trim', explode(',', (string)$interests)));

    $pdo->prepare("INSERT INTO discover_profiles (user_id, display_name, age, gender, bio, city, completion)
                   VALUES (?,?,?,?,?,?,60)
                   ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), age=VALUES(age),
                     gender=VALUES(gender), bio=VALUES(bio), city=VALUES(city)")
        ->execute([$u['id'],$name,$age,$gender,$bio,$city]);
    $pdo->prepare("INSERT INTO discover_preferences (user_id, purposes, want_gender, age_min, age_max, distance_km)
                   VALUES (?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE purposes=VALUES(purposes), want_gender=VALUES(want_gender),
                     age_min=VALUES(age_min), age_max=VALUES(age_max), distance_km=VALUES(distance_km)")
        ->execute([$u['id'],$purposes,$wantGender,$ageMin,$ageMax,$distance]);
    $pdo->prepare("INSERT INTO discover_visibility (user_id, enabled) VALUES (?,1)
                   ON DUPLICATE KEY UPDATE enabled=1")->execute([$u['id']]);
    $pdo->prepare("DELETE FROM user_interests WHERE user_id=?")->execute([$u['id']]);
    $ins = $pdo->prepare("INSERT INTO user_interests (user_id, interest) VALUES (?,?)");
    foreach (array_slice($interests, 0, 20) as $it) { $it = trim((string)$it); if ($it !== '') $ins->execute([$u['id'], $it]); }
    json_ok(['onboarded'=>true]);
}

case 'discover/preferences': {   // current settings, to prefill the edit screen
    $u = current_user($pdo);
    $p = $pdo->prepare("SELECT * FROM discover_profiles WHERE user_id=?"); $p->execute([$u['id']]); $prof = $p->fetch() ?: [];
    $pr = $pdo->prepare("SELECT * FROM discover_preferences WHERE user_id=?"); $pr->execute([$u['id']]); $pref = $pr->fetch() ?: [];
    $v = $pdo->prepare("SELECT * FROM discover_visibility WHERE user_id=?"); $v->execute([$u['id']]); $vis = $v->fetch() ?: [];
    $ti = $pdo->prepare("SELECT interest FROM user_interests WHERE user_id=?"); $ti->execute([$u['id']]);
    json_ok([
        'displayName' => $prof['display_name'] ?? ($u['name'] ?? $u['username']),
        'age'      => (int)($prof['age'] ?? 20),
        'gender'   => $prof['gender'] ?? 'male',
        'city'     => $prof['city'] ?? '',
        'bio'      => $prof['bio'] ?? '',
        'purposes' => array_filter(explode(',', (string)($pref['purposes'] ?? ''))),
        'wantGender' => $pref['want_gender'] ?? 'everyone',
        'ageMin'   => (int)($pref['age_min'] ?? 18),
        'ageMax'   => (int)($pref['age_max'] ?? 35),
        'distanceKm' => (int)($pref['distance_km'] ?? 50),
        'interests' => array_column($ti->fetchAll(), 'interest'),
        'visibilityMode' => $vis['mode'] ?? 'everyone',
        'enabled'  => (int)($vis['enabled'] ?? 0) === 1,
    ]);
}

case 'discover/visibility': {
    $u = current_user($pdo);
    if ($method === 'POST') {
        $pdo->prepare("INSERT INTO discover_visibility (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id=user_id")->execute([$u['id']]);
        if (inp('mode', null) !== null)          $pdo->prepare("UPDATE discover_visibility SET mode=? WHERE user_id=?")->execute([inp('mode'), $u['id']]);
        if (inp('enabled', null) !== null)       $pdo->prepare("UPDATE discover_visibility SET enabled=? WHERE user_id=?")->execute([filter_var(inp('enabled'),FILTER_VALIDATE_BOOLEAN)?1:0, $u['id']]);
        if (inp('showOnline', null) !== null)    $pdo->prepare("UPDATE discover_visibility SET show_online=? WHERE user_id=?")->execute([filter_var(inp('showOnline'),FILTER_VALIDATE_BOOLEAN)?1:0, $u['id']]);
        if (inp('allowRequests', null) !== null) $pdo->prepare("UPDATE discover_visibility SET allow_requests=? WHERE user_id=?")->execute([filter_var(inp('allowRequests'),FILTER_VALIDATE_BOOLEAN)?1:0, $u['id']]);
    }
    $v = $pdo->prepare("SELECT * FROM discover_visibility WHERE user_id=?"); $v->execute([$u['id']]);
    json_ok($v->fetch() ?: ['mode'=>'everyone','enabled'=>0,'show_online'=>1,'allow_requests'=>1]);
}

case 'discover/location': {
    $u = current_user($pdo);
    $lat = round((float)inp('lat',0), 2);   // ~1.1 km precision for privacy
    $lng = round((float)inp('lng',0), 2);
    $area = trim((string)inp('area',''));
    $pdo->prepare("INSERT INTO discover_location (user_id, lat, lng, area) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng), area=VALUES(area), updated_at=NOW()")
        ->execute([$u['id'],$lat,$lng,$area]);
    json_ok(['ok'=>true]);
}

case 'discover/nearby':
case 'discover/recommended':
case 'discover/online':
case 'discover/new': {
    $u = current_user($pdo);
    if (ss_couple_of($pdo, $u['id'])) json_ok(['blocked'=>true, 'users'=>[]]);
    $mode = explode('/', $route)[1]; // nearby|recommended|online|new
    $opts = ['mode' => $mode];
    // Optional filters (from query): gender, ageMin, ageMax, maxKm, onlineOnly, verifiedOnly
    if (inp('gender', '') !== '')       $opts['gender'] = inp('gender');
    if (inp('ageMin', '') !== '')       $opts['ageMin'] = (int)inp('ageMin');
    if (inp('ageMax', '') !== '')       $opts['ageMax'] = (int)inp('ageMax');
    if (inp('maxKm', '') !== '')        $opts['maxKm'] = (int)inp('maxKm');
    if (filter_var(inp('onlineOnly', false), FILTER_VALIDATE_BOOLEAN))   $opts['onlineOnly'] = true;
    if (filter_var(inp('verifiedOnly', false), FILTER_VALIDATE_BOOLEAN)) $opts['verifiedOnly'] = true;
    json_ok(['users' => ss_discover_feed($pdo, $u, $opts)]);
}

case 'discover/wave': {
    $u = current_user($pdo);
    if (ss_couple_of($pdo, $u['id'])) json_err('Disconnect your partner to use Discover', 403);
    $to = (int)inp('toId', 0);
    if ($to <= 0 || $to === (int)$u['id']) json_err('Invalid user');
    $pdo->prepare("INSERT INTO waves (from_id, to_id) VALUES (?,?)")->execute([$u['id'], $to]);
    ss_push_to_user($pdo, $to, '👋 Wave', ($u['name'] ?? 'Someone') . ' waved at you', ['type'=>'discover_wave']);
    json_ok(['waved'=>true]);
}

case 'discover/waves': {
    $u = current_user($pdo);
    $q = $pdo->prepare("SELECT w.id, w.from_id, p.display_name, p.age, uu.avatar
                        FROM waves w JOIN discover_profiles p ON p.user_id=w.from_id
                        LEFT JOIN users uu ON uu.id=w.from_id
                        WHERE w.to_id=? ORDER BY w.id DESC LIMIT 100");
    $q->execute([$u['id']]);
    $pdo->prepare("UPDATE waves SET seen=1 WHERE to_id=? AND seen=0")->execute([$u['id']]);
    json_ok(['waves' => array_map(fn($r)=>[
        'id'=>(string)$r['from_id'], 'name'=>$r['display_name'], 'age'=>(int)$r['age'], 'avatar'=>$r['avatar'],
    ], $q->fetchAll())]);
}

case 'discover/favorite': {
    $u = current_user($pdo);
    $to = (int)inp('toId', 0);
    if ($to <= 0) json_err('Invalid');
    $ex = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id=? AND target_id=?"); $ex->execute([$u['id'],$to]);
    if ($ex->fetch()) { $pdo->prepare("DELETE FROM favorites WHERE user_id=? AND target_id=?")->execute([$u['id'],$to]); json_ok(['favorited'=>false]); }
    $pdo->prepare("INSERT INTO favorites (user_id, target_id) VALUES (?,?)")->execute([$u['id'],$to]);
    json_ok(['favorited'=>true]);
}

case 'discover/favorites': {
    $u = current_user($pdo);
    $q = $pdo->prepare("SELECT p.user_id, p.display_name, p.age, p.city, uu.avatar, uu.is_online, uu.presence_at
                        FROM favorites f JOIN discover_profiles p ON p.user_id=f.target_id
                        LEFT JOIN users uu ON uu.id=f.target_id WHERE f.user_id=? ORDER BY f.id DESC");
    $q->execute([$u['id']]);
    json_ok(['users' => array_map(function($r){
        $online = (int)($r['is_online'] ?? 0) === 1 && !empty($r['presence_at']) && strtotime($r['presence_at']) > time() - 70;
        return ['id'=>(string)$r['user_id'], 'name'=>$r['display_name'], 'age'=>(int)$r['age'],
                'city'=>$r['city'], 'avatar'=>$r['avatar'], 'online'=>$online, 'favorited'=>true];
    }, $q->fetchAll())]);
}

case 'discover/profile': {
    $u = current_user($pdo);
    $id = (int)inp('id', 0);
    $p = $pdo->prepare("SELECT p.*, uu.avatar, uu.is_online, uu.presence_at FROM discover_profiles p LEFT JOIN users uu ON uu.id=p.user_id WHERE p.user_id=?");
    $p->execute([$id]); $prof = $p->fetch();
    if (!$prof) json_err('Not found', 404);
    if ($id !== (int)$u['id']) $pdo->prepare("INSERT INTO profile_views (viewer_id, target_id) VALUES (?,?)")->execute([$u['id'], $id]);
    $ti = $pdo->prepare("SELECT interest FROM user_interests WHERE user_id=?"); $ti->execute([$id]);
    $pr = $pdo->prepare("SELECT purposes FROM discover_preferences WHERE user_id=?"); $pr->execute([$id]);
    $online = (int)($prof['is_online'] ?? 0) === 1 && !empty($prof['presence_at']) && strtotime($prof['presence_at']) > time() - 70;
    $ints = array_column($ti->fetchAll(), 'interest');
    $verified = (bool)$pdo->query("SELECT 1 FROM verified_users WHERE user_id=$id")->fetchColumn();
    json_ok([
        'id' => (string)$id, 'name' => $prof['display_name'], 'age' => (int)$prof['age'],
        'gender' => $prof['gender'], 'bio' => $prof['bio'], 'city' => $prof['city'],
        'occupation' => $prof['occupation'], 'education' => $prof['education'],
        'languages' => $prof['languages'], 'avatar' => $prof['avatar'], 'online' => $online,
        'verified' => $verified,
        'joinDate' => $prof['created_at'], 'interests' => $ints,
        'purposes' => $pr->fetchColumn() ?: '',
        'badges' => ss_discover_badges($ints, $verified, $prof['created_at']),
    ]);
}

case 'discover/views': {
    $u = current_user($pdo);
    $isPrem = (int)($u['is_premium'] ?? 0) === 1 && (empty($u['premium_until']) || strtotime($u['premium_until']) > time());
    $unl = $pdo->prepare("SELECT until FROM discover_unlocks WHERE user_id=? AND feature='views'"); $unl->execute([$u['id']]);
    $until = $unl->fetchColumn();
    $unlocked = $isPrem || ($until && strtotime($until) > time());
    $cnt = (int)$pdo->query("SELECT COUNT(DISTINCT viewer_id) FROM profile_views WHERE target_id=".(int)$u['id'])->fetchColumn();
    if (!$unlocked) json_ok(['locked'=>true, 'count'=>$cnt, 'users'=>[]]);
    $q = $pdo->prepare("SELECT p.user_id, p.display_name, p.age, p.city, uu.avatar, MAX(pv.created_at) last
                        FROM profile_views pv JOIN discover_profiles p ON p.user_id=pv.viewer_id
                        LEFT JOIN users uu ON uu.id=pv.viewer_id
                        WHERE pv.target_id=? GROUP BY pv.viewer_id ORDER BY last DESC LIMIT 100");
    $q->execute([$u['id']]);
    json_ok(['locked'=>false, 'count'=>$cnt, 'users'=>array_map(fn($r)=>[
        'id'=>(string)$r['user_id'],'name'=>$r['display_name'],'age'=>(int)$r['age'],'city'=>$r['city'],
        'avatar'=>$r['avatar'],'lastView'=>$r['last'],
    ], $q->fetchAll())]);
}

case 'discover/views/unlock': {   // called after the user watches a rewarded ad
    $u = current_user($pdo);
    $pdo->prepare("INSERT INTO discover_unlocks (user_id, feature, until) VALUES (?, 'views', DATE_ADD(NOW(), INTERVAL 24 HOUR))
                   ON DUPLICATE KEY UPDATE until=DATE_ADD(NOW(), INTERVAL 24 HOUR)")->execute([$u['id']]);
    json_ok(['unlocked'=>true]);
}

case 'discover/report': {
    $u = current_user($pdo);
    $to = (int)inp('targetId', 0); $reason = trim((string)inp('reason', ''));
    if ($to <= 0) json_err('Invalid');
    $pdo->prepare("INSERT INTO discover_reports (reporter_id, target_id, reason) VALUES (?,?,?)")->execute([$u['id'],$to,$reason]);
    json_ok(['reported'=>true]);
}

case 'discover/block': {
    $u = current_user($pdo);
    $to = (int)inp('targetId', 0);
    if ($to <= 0) json_err('Invalid');
    $pdo->prepare("INSERT IGNORE INTO discover_blocks (blocker_id, blocked_id) VALUES (?,?)")->execute([$u['id'],$to]);
    json_ok(['blocked'=>true]);
}

case 'discover/suggestions': {
    $u = current_user($pdo);
    if (ss_couple_of($pdo, $u['id'])) json_ok(['users'=>[]]);
    // Daily 20 — cache per day.
    $day = date('Y-m-d');
    $c = $pdo->prepare("SELECT suggested_ids FROM daily_suggestions WHERE user_id=? AND day=?");
    $c->execute([$u['id'], $day]); $cached = $c->fetchColumn();
    $feed = ss_discover_feed($pdo, $u, ['mode'=>'recommended']);
    $top = array_slice($feed, 0, 20);
    if (!$cached) {
        $ids = json_encode(array_column($top, 'id'));
        try { $pdo->prepare("INSERT INTO daily_suggestions (user_id, day, suggested_ids) VALUES (?,?,?)
                             ON DUPLICATE KEY UPDATE suggested_ids=VALUES(suggested_ids)")->execute([$u['id'],$day,$ids]); } catch (\Throwable $e) {}
    }
    json_ok(['users'=>$top]);
}

case 'discover/request': {
    $u = current_user($pdo);
    if (ss_couple_of($pdo, $u['id'])) json_err('Disconnect your partner to use Discover', 403);
    $to = (int)inp('toId', 0);
    if ($to <= 0 || $to === (int)$u['id']) json_err('Invalid user');
    // basic rate limit: 15/day for free users
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM connection_requests WHERE from_id=? AND DATE(created_at)=CURDATE()");
    $cnt->execute([$u['id']]);
    $isPrem = (int)($u['is_premium'] ?? 0) === 1 && (empty($u['premium_until']) || strtotime($u['premium_until']) > time());
    if (!$isPrem && (int)$cnt->fetchColumn() >= 15) json_err('Daily request limit reached. Go Premium for more.', 429);
    // no duplicate pending
    $dup = $pdo->prepare("SELECT id FROM connection_requests WHERE from_id=? AND to_id=? AND status='pending'");
    $dup->execute([$u['id'],$to]);
    if (!$dup->fetch()) {
        $pdo->prepare("INSERT INTO connection_requests (from_id,to_id) VALUES (?,?)")->execute([$u['id'],$to]);
        ss_push_to_user($pdo, $to, '❤️ New Soul Request', ($u['name'] ?? 'Someone') . ' wants to connect', ['type'=>'discover_request']);
    }
    json_ok(['sent'=>true]);
}

case 'discover/requests': {
    $u = current_user($pdo);
    $q = $pdo->prepare("SELECT r.id, r.from_id, p.display_name, p.age, p.city, uu.avatar
                        FROM connection_requests r
                        JOIN discover_profiles p ON p.user_id=r.from_id
                        LEFT JOIN users uu ON uu.id=r.from_id
                        WHERE r.to_id=? AND r.status='pending' ORDER BY r.id DESC");
    $q->execute([$u['id']]);
    json_ok(['requests' => array_map(fn($r)=>[
        'requestId'=>(string)$r['id'], 'id'=>(string)$r['from_id'], 'name'=>$r['display_name'],
        'age'=>(int)$r['age'], 'city'=>$r['city'], 'avatar'=>$r['avatar'],
    ], $q->fetchAll())]);
}

case 'discover/request/respond': {
    $u = current_user($pdo);
    $rid = (int)inp('requestId', 0);
    $accept = filter_var(inp('accept', false), FILTER_VALIDATE_BOOLEAN);
    $r = $pdo->prepare("SELECT * FROM connection_requests WHERE id=? AND to_id=? AND status='pending'");
    $r->execute([$rid, $u['id']]); $req = $r->fetch();
    if (!$req) json_err('Request not found', 404);
    $pdo->prepare("UPDATE connection_requests SET status=? WHERE id=?")->execute([$accept?'accepted':'rejected', $rid]);
    $matchId = null;
    if ($accept) {
        $pdo->prepare("INSERT INTO discover_matches (user1_id, user2_id) VALUES (?,?)")->execute([$req['from_id'], $u['id']]);
        $matchId = (int)$pdo->lastInsertId();
        ss_push_to_user($pdo, $req['from_id'], '💜 Soul Connected!', ($u['name'] ?? 'Someone') . ' accepted your request', ['type'=>'discover_match']);
    }
    json_ok(['matched'=>$accept, 'matchId'=>$matchId]);
}

case 'discover/matches': {
    $u = current_user($pdo);
    $q = $pdo->prepare("SELECT m.id, m.created_at,
                          IF(m.user1_id=?, m.user2_id, m.user1_id) AS other_id
                        FROM discover_matches m WHERE m.user1_id=? OR m.user2_id=? ORDER BY m.id DESC");
    $q->execute([$u['id'],$u['id'],$u['id']]);
    $rows = $q->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $p = $pdo->prepare("SELECT p.display_name, uu.avatar FROM discover_profiles p LEFT JOIN users uu ON uu.id=p.user_id WHERE p.user_id=?");
        $p->execute([$r['other_id']]); $pp = $p->fetch();
        $out[] = ['matchId'=>(string)$r['id'], 'id'=>(string)$r['other_id'], 'name'=>$pp['display_name'] ?? 'User', 'avatar'=>$pp['avatar'] ?? null];
    }
    json_ok(['matches'=>$out]);
}

case 'discover/chat/list': {
    $u = current_user($pdo);
    $mid = (int)inp('matchId', 0);
    $chk = $pdo->prepare("SELECT 1 FROM discover_matches WHERE id=? AND (user1_id=? OR user2_id=?)");
    $chk->execute([$mid,$u['id'],$u['id']]); if(!$chk->fetch()) json_err('No access',403);
    $q = $pdo->prepare("SELECT id, sender_id, body, created_at FROM discover_messages WHERE match_id=? ORDER BY id DESC LIMIT 100");
    $q->execute([$mid]);
    json_ok(array_map(fn($m)=>['id'=>(string)$m['id'],'senderId'=>(string)$m['sender_id'],'content'=>$m['body'],'createdAt'=>str_replace(' ','T',$m['created_at'])], $q->fetchAll()));
}

case 'discover/chat/send': {
    $u = current_user($pdo);
    $mid = (int)inp('matchId', 0);
    $body = trim((string)inp('content', inp('body','')));
    if ($body==='') json_err('Empty');
    $m = $pdo->prepare("SELECT * FROM discover_matches WHERE id=? AND (user1_id=? OR user2_id=?)");
    $m->execute([$mid,$u['id'],$u['id']]); $match=$m->fetch(); if(!$match) json_err('No access',403);
    $pdo->prepare("INSERT INTO discover_messages (match_id, sender_id, body) VALUES (?,?,?)")->execute([$mid,$u['id'],$body]);
    $other = (int)$match['user1_id']===(int)$u['id'] ? $match['user2_id'] : $match['user1_id'];
    ss_push_to_user($pdo, $other, $u['name'] ?? 'Message', mb_strimwidth($body,0,100,'…'), ['type'=>'discover_chat']);
    json_ok(['sent'=>true]);
}

// ===================== AI (stub) =======================================
case 'ai/resolve':
    current_user($pdo);
    json_ok(['result' => null, 'message' => 'AI features are coming soon']);

// ===================== NOTIFICATIONS ===================================
case 'notifications/list': {
    $u = current_user($pdo);
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100");
    $stmt->execute([$u['id']]);
    json_ok($stmt->fetchAll());
}

// ===================== TYPING INDICATOR ================================
case 'chat/typing': {
    $u = current_user($pdo);
    $pdo->prepare("UPDATE users SET last_typing_at = NOW() WHERE id = ?")->execute([$u['id']]);
    json_ok(['ok' => true]);
}

case 'chat/partner-status': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok(['online' => false, 'typing' => false]);
    $p = $pdo->prepare("SELECT is_online, last_seen, last_typing_at, hide_online, hide_last_seen FROM users WHERE id = ?");
    $p->execute([$couple['partner_id']]);
    $partner = $p->fetch();
    $online = !empty($partner['is_online']) && empty($partner['hide_online']);
    $typing = !empty($partner['last_typing_at']) && strtotime($partner['last_typing_at']) > time() - 5;
    $lastSeen = null;
    if (!empty($partner['last_seen']) && empty($partner['hide_last_seen'])) {
        $lastSeen = str_replace(' ', 'T', (string)$partner['last_seen']);
    }
    json_ok(['online' => $online, 'typing' => $typing, 'lastSeen' => $lastSeen]);
}

// ===================== MESSAGE REACTIONS ================================
case 'chat/react': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    $mid = (int)inp('messageId', 0);
    $emoji = trim((string)inp('emoji', ''));
    if ($mid <= 0) json_err('Invalid message');
    $chk = $pdo->prepare("SELECT id FROM messages WHERE id=? AND couple_id=?");
    $chk->execute([$mid, $couple['id']]);
    if (!$chk->fetchColumn()) json_err('Message not found');
    if ($emoji === '' || $emoji === 'none') {
        $pdo->prepare("DELETE FROM message_reactions WHERE message_id=? AND user_id=?")->execute([$mid, $u['id']]);
    } else {
        $pdo->prepare("INSERT INTO message_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE emoji=VALUES(emoji)")->execute([$mid, $u['id'], $emoji]);
    }
    $all = $pdo->prepare("SELECT user_id, emoji FROM message_reactions WHERE message_id=?");
    $all->execute([$mid]);
    json_ok(['reactions' => $all->fetchAll()]);
}

// ===================== PINNED MESSAGES ================================
case 'chat/pin': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    $mid = (int)inp('messageId', 0);
    $pin = filter_var(inp('pin', true), FILTER_VALIDATE_BOOLEAN);
    $chk = $pdo->prepare("SELECT id FROM messages WHERE id=? AND couple_id=?");
    $chk->execute([$mid, $couple['id']]);
    if (!$chk->fetchColumn()) json_err('Message not found');
    $pdo->prepare("UPDATE messages SET pinned_at = ? WHERE id = ?")->execute([$pin ? date('Y-m-d H:i:s') : null, $mid]);
    json_ok(['pinned' => $pin]);
}

case 'chat/pinned': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok([]);
    $stmt = $pdo->prepare("SELECT m.*, r.body AS reply_body, r.sender_id AS reply_sender, r.type AS reply_type
                           FROM messages m LEFT JOIN messages r ON r.id = m.reply_to_id
                           WHERE m.couple_id = ? AND m.pinned_at IS NOT NULL
                           ORDER BY m.pinned_at DESC LIMIT 50");
    $stmt->execute([$couple['id']]);
    json_ok(array_map('ss_msg_app', $stmt->fetchAll()));
}

// ===================== CHAT WALLPAPER ================================
case 'chat/wallpaper': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        json_ok(['wallpaper' => $couple['wallpaper'] ?? null]);
    }
    $wp = trim((string)inp('wallpaper', ''));
    $pdo->prepare("UPDATE couples SET wallpaper = ? WHERE id = ?")->execute([$wp !== '' ? $wp : null, $couple['id']]);
    json_ok(['wallpaper' => $wp !== '' ? $wp : null]);
}

// ===================== CHAT SEND WITH CAPTION ==========================
case 'chat/send-with-caption': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    $content = trim((string)inp('content', ''));
    $caption = trim((string)inp('caption', ''));
    $type = inp('type', 'image');
    if (!in_array($type, ['image', 'video'], true)) $type = 'image';
    if ($content === '') json_err('No media URL');
    $replyTo = (int)inp('replyToId', 0);
    if ($replyTo > 0) {
        $chk = $pdo->prepare("SELECT id FROM messages WHERE id=? AND couple_id=?");
        $chk->execute([$replyTo, $couple['id']]);
        if (!$chk->fetchColumn()) $replyTo = 0;
    }
    $pdo->prepare("INSERT INTO messages (couple_id, sender_id, type, body, caption, status, reply_to_id) VALUES (?, ?, ?, ?, ?, 'sent', ?)")
        ->execute([$couple['id'], $u['id'], $type, $content, $caption !== '' ? $caption : null, $replyTo > 0 ? $replyTo : null]);
    $mid = (int)$pdo->lastInsertId();
    $preview = $type === 'image' ? '📷 Photo' : '🎥 Video';
    if ($caption !== '') $preview .= ': ' . mb_strimwidth($caption, 0, 80, '…');
    ss_push_to_user($pdo, $couple['partner_id'], $u['name'], $preview, ['type' => 'chat']);
    $row = $pdo->prepare("SELECT * FROM messages WHERE id = ?"); $row->execute([$mid]);
    json_ok(ss_msg_app($row->fetch()), 201);
}

// ===================== VOICE NOTES =====================================
case 'chat/voice': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner connected');
    if (empty($_FILES['file'])) json_err('No file uploaded');
    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'aac';
    $allowed = ['aac', 'm4a', 'mp3', 'ogg', 'wav', 'opus'];
    if (!in_array(strtolower($ext), $allowed, true)) $ext = 'aac';
    $fn = 'voice_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
    $dir = dirname(__DIR__) . '/uploads/voice/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    move_uploaded_file($file['tmp_name'], $dir . $fn);
    $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
         . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/uploads/voice/' . $fn;
    $dur = (int)inp('duration', 0);
    $body = json_encode(['url' => $url, 'duration' => $dur]);
    $replyTo = (int)inp('replyToId', 0);
    if ($replyTo > 0) {
        $chk = $pdo->prepare("SELECT id FROM messages WHERE id=? AND couple_id=?");
        $chk->execute([$replyTo, $couple['id']]);
        if (!$chk->fetchColumn()) $replyTo = 0;
    }
    $pdo->prepare("INSERT INTO messages (couple_id, sender_id, type, body, status, reply_to_id) VALUES (?, ?, 'voice', ?, 'sent', ?)")
        ->execute([$couple['id'], $u['id'], $body, $replyTo > 0 ? $replyTo : null]);
    $mid = (int)$pdo->lastInsertId();
    ss_push_to_user($pdo, $couple['partner_id'], $u['name'], '🎤 Voice note', ['type' => 'chat']);
    $row = $pdo->prepare("SELECT * FROM messages WHERE id = ?"); $row->execute([$mid]);
    json_ok(ss_msg_app($row->fetch()), 201);
}

// ===================== MILESTONES ======================================
case 'milestones': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok([]);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT * FROM milestones WHERE couple_id = ? ORDER BY event_date ASC");
        $stmt->execute([$couple['id']]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id'] = (string)$r['id'];
            $r['daysUntil'] = (int)((strtotime($r['event_date']) - strtotime(date('Y-m-d'))) / 86400);
            if ($r['recurring'] && $r['daysUntil'] < 0) {
                $d = new DateTime($r['event_date']);
                $now = new DateTime();
                $d->setDate((int)$now->format('Y'), (int)$d->format('m'), (int)$d->format('d'));
                if ($d < $now) $d->modify('+1 year');
                $r['daysUntil'] = (int)$now->diff($d)->days;
                $r['nextDate'] = $d->format('Y-m-d');
            }
        }
        json_ok($rows);
    }
    $title = trim((string)inp('title', ''));
    $icon = trim((string)inp('icon', '💕'));
    $date = trim((string)inp('eventDate', ''));
    $recurring = filter_var(inp('recurring', true), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    if ($title === '' || $date === '') json_err('Title and date required');
    $pdo->prepare("INSERT INTO milestones (couple_id, author_id, title, icon, event_date, recurring) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$couple['id'], $u['id'], $title, $icon, $date, $recurring]);
    json_ok(['id' => (string)$pdo->lastInsertId()], 201);
}

case 'milestones/delete': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $id = (int)inp('id', 0);
    $pdo->prepare("DELETE FROM milestones WHERE id=? AND couple_id=?")->execute([$id, $couple['id']]);
    json_ok(['deleted' => true]);
}

// ===================== MOOD HISTORY ====================================
case 'mood/history': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    $ids = [$u['id']];
    if ($couple) $ids[] = $couple['partner_id'];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $days = min(90, max(7, (int)inp('days', 30)));
    $stmt = $pdo->prepare("SELECT user_id, mood, created_at FROM mood_history
                           WHERE user_id IN ($ph) AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                           ORDER BY created_at ASC");
    $stmt->execute($ids);
    json_ok($stmt->fetchAll());
}

// ===================== SHARED TODO LIST =================================
case 'todos': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_ok([]);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT * FROM shared_todos WHERE couple_id = ? ORDER BY done ASC, id DESC");
        $stmt->execute([$couple['id']]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) { $r['id'] = (string)$r['id']; $r['done'] = (bool)$r['done']; }
        json_ok($rows);
    }
    $text = trim((string)inp('text', ''));
    if ($text === '') json_err('Text required');
    $pdo->prepare("INSERT INTO shared_todos (couple_id, author_id, text) VALUES (?, ?, ?)")
        ->execute([$couple['id'], $u['id'], $text]);
    json_ok(['id' => (string)$pdo->lastInsertId()], 201);
}

case 'todos/toggle': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $id = (int)inp('id', 0);
    $pdo->prepare("UPDATE shared_todos SET done = NOT done, done_by = ? WHERE id = ? AND couple_id = ?")
        ->execute([$u['id'], $id, $couple['id']]);
    json_ok(['toggled' => true]);
}

case 'todos/delete': {
    $u = current_user($pdo);
    $couple = ss_couple_of($pdo, $u['id']);
    if (!$couple) json_err('No partner');
    $id = (int)inp('id', 0);
    $pdo->prepare("DELETE FROM shared_todos WHERE id = ? AND couple_id = ?")->execute([$id, $couple['id']]);
    json_ok(['deleted' => true]);
}

// ===================== LOVE LANGUAGE QUIZ ================================
case 'love-language': {
    $u = current_user($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $r = $pdo->prepare("SELECT * FROM love_language WHERE user_id = ?");
        $r->execute([$u['id']]);
        $row = $r->fetch();
        $partner = null;
        $couple = ss_couple_of($pdo, $u['id']);
        if ($couple) {
            $pr = $pdo->prepare("SELECT * FROM love_language WHERE user_id = ?");
            $pr->execute([$couple['partner_id']]);
            $partner = $pr->fetch() ?: null;
        }
        json_ok(['mine' => $row ?: null, 'partner' => $partner]);
    }
    $scores = [
        'words'   => (int)inp('words', 0),
        'time'    => (int)inp('time', 0),
        'gifts'   => (int)inp('gifts', 0),
        'service' => (int)inp('service', 0),
        'touch'   => (int)inp('touch', 0),
    ];
    $max = array_keys($scores, max($scores));
    $labels = ['words' => 'Words of Affirmation', 'time' => 'Quality Time', 'gifts' => 'Receiving Gifts', 'service' => 'Acts of Service', 'touch' => 'Physical Touch'];
    $result = $labels[$max[0]] ?? $max[0];
    $pdo->prepare("INSERT INTO love_language (user_id, words, time, gifts, service, touch, result) VALUES (?, ?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE words=VALUES(words), time=VALUES(time), gifts=VALUES(gifts), service=VALUES(service), touch=VALUES(touch), result=VALUES(result)")
        ->execute([$u['id'], $scores['words'], $scores['time'], $scores['gifts'], $scores['service'], $scores['touch'], $result]);
    json_ok(['result' => $result, 'scores' => $scores]);
}

// ===================== DATE NIGHT SUGGESTIONS ============================
case 'date-night': {
    $u = current_user($pdo);
    $cat = strtolower(trim((string)inp('category', 'all')));
    $ideas = [
        'romantic' => [
            ['title' => 'Stargazing Night', 'icon' => '🌟', 'desc' => 'Find a quiet spot, lay a blanket, and watch the stars together.'],
            ['title' => 'Candlelit Dinner', 'icon' => '🕯️', 'desc' => 'Cook a special meal together at home with candles and soft music.'],
            ['title' => 'Love Letter Exchange', 'icon' => '💌', 'desc' => 'Write heartfelt letters to each other and read them aloud.'],
            ['title' => 'Sunset Walk', 'icon' => '🌅', 'desc' => 'Take a long walk together during golden hour.'],
            ['title' => 'Couple Massage', 'icon' => '💆', 'desc' => 'Give each other relaxing massages with essential oils.'],
        ],
        'adventure' => [
            ['title' => 'Midnight Drive', 'icon' => '🚗', 'desc' => 'Pick a direction and just drive — stop wherever looks interesting.'],
            ['title' => 'Cooking Challenge', 'icon' => '👨‍🍳', 'desc' => 'Each person picks 3 ingredients, the other must cook something with them.'],
            ['title' => 'Photo Walk', 'icon' => '📸', 'desc' => 'Explore a new area together and photograph everything beautiful.'],
            ['title' => 'Treasure Hunt', 'icon' => '🗺️', 'desc' => 'Hide notes/gifts around the house for each other to find.'],
            ['title' => 'Blind Food Tasting', 'icon' => '🍽️', 'desc' => 'Blindfold each other and guess the foods.'],
        ],
        'chill' => [
            ['title' => 'Movie Marathon', 'icon' => '🎬', 'desc' => 'Pick a series/trilogy and binge with snacks and blankets.'],
            ['title' => 'Board Game Night', 'icon' => '🎲', 'desc' => 'Play classic games together — loser makes breakfast.'],
            ['title' => 'Build a Fort', 'icon' => '🏕️', 'desc' => 'Pillows, blankets, fairy lights — build a cozy fort and chill inside.'],
            ['title' => 'Bake Together', 'icon' => '🧁', 'desc' => 'Try baking cookies, brownies, or cake from scratch.'],
            ['title' => 'Playlist Exchange', 'icon' => '🎵', 'desc' => 'Each person makes a 10-song playlist that reminds them of the other.'],
        ],
        'creative' => [
            ['title' => 'Paint Night', 'icon' => '🎨', 'desc' => 'Buy a canvas each and paint each other — no skill required!'],
            ['title' => 'Time Capsule', 'icon' => '📦', 'desc' => 'Put in notes, photos, small items. Open in 1 year.'],
            ['title' => 'Dream Board', 'icon' => '✨', 'desc' => 'Cut magazines and create a vision board of your future together.'],
            ['title' => 'Write a Song', 'icon' => '🎤', 'desc' => 'Write silly or romantic lyrics about your relationship.'],
            ['title' => 'Memory Jar', 'icon' => '🏺', 'desc' => 'Write your favorite memories on paper and fill a jar together.'],
        ],
    ];
    if ($cat !== 'all' && isset($ideas[$cat])) {
        json_ok($ideas[$cat]);
    }
    $all = [];
    foreach ($ideas as $list) foreach ($list as $i) $all[] = $i;
    shuffle($all);
    json_ok(array_slice($all, 0, 10));
}

case 'crash':
    current_user($pdo, false);
    json_ok(['logged' => true]);   // swallow crash reports quietly

default:
    json_err('Unknown route: ' . $route, 404);
}

} catch (\Throwable $e) {
    json_err('Server error: ' . $e->getMessage(), 500);
}
