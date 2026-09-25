<?php
// =========================================================================
// SoulSync shared helpers (used by api.php and admin panel)
// =========================================================================

// Optional website-login sync uses a SEPARATE database. This server runs a single
// app database only, so it's disabled — returns null and the account-mirror steps
// safely skip (the app works fully). To re-enable later, fill the credentials and
// set $ENABLE_WEBSITE_SYNC = true.
if (!function_exists('ss_website_pdo')) {
    function ss_website_pdo() {
        static $w = false;
        if ($w !== false) return $w;
        $ENABLE_WEBSITE_SYNC = false;
        if (!$ENABLE_WEBSITE_SYNC) { $w = null; return $w; }
        try {
            $w = new PDO("mysql:host=localhost;dbname=WEBSITE_DB;charset=utf8mb4",
                'WEBSITE_USER', 'WEBSITE_PASS',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_TIMEOUT => 3]);
        } catch (\Throwable $e) { $w = null; }
        return $w;
    }
}

// Mirror an account into the website users table so the SAME id/password works
// on the website too. Needs an email (website login is by email). Safe: never
// throws, never overwrites an existing account.
if (!function_exists('ss_mirror_to_website')) {
    function ss_mirror_to_website($email, $username, $name, $passwordHash) {
        if (empty($email)) return; // website login requires an email
        $w = ss_website_pdo(); if (!$w) return;
        try {
            $q = $w->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
            $q->execute([$email, $username]);
            if ($q->fetch()) return; // already exists on the website
            $ref = 'SS' . substr(strtoupper(bin2hex(random_bytes(3))), 0, 6);
            $w->prepare("INSERT INTO users (name, username, email, password, role, status, referral_code, email_verified)
                         VALUES (?, ?, ?, ?, 'user', 'active', ?, 1)")
              ->execute([$name ?: $username, $username, $email, $passwordHash, $ref]);
        } catch (\Throwable $e) { /* ignore — app must not break if the mirror fails */ }
    }
}

// Great-circle distance in km between two lat/lng points (Discover nearby).
if (!function_exists('ss_haversine')) {
    function ss_haversine($lat1, $lng1, $lat2, $lng2) {
        $r = 6371; // km
        $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
        return $r * 2 * atan2(sqrt($a), sqrt(1-$a));
    }
}

// Discover feed builder — powers Nearby / Recommended / Online / New tabs.
if (!function_exists('ss_discover_feed')) {
    function ss_discover_feed($pdo, $me, array $opts = []) {
        $uid = (int)$me['id'];
        $mode = $opts['mode'] ?? 'nearby';
        $ml = $pdo->prepare("SELECT lat,lng FROM discover_location WHERE user_id=?"); $ml->execute([$uid]); $myLoc = $ml->fetch();
        $pf = $pdo->prepare("SELECT * FROM discover_preferences WHERE user_id=?"); $pf->execute([$uid]); $pref = $pf->fetch() ?: [];
        $maxKm  = (int)($opts['maxKm'] ?? ($pref['distance_km'] ?? 50));
        $want   = $opts['gender'] ?? ($pref['want_gender'] ?? 'everyone');
        $ageMin = (int)($opts['ageMin'] ?? ($pref['age_min'] ?? 18));
        $ageMax = (int)($opts['ageMax'] ?? ($pref['age_max'] ?? 99));
        $onlineOnly   = !empty($opts['onlineOnly']);
        $verifiedOnly = !empty($opts['verifiedOnly']);

        $mi = $pdo->prepare("SELECT interest FROM user_interests WHERE user_id=?"); $mi->execute([$uid]);
        $myInts = array_map('strtolower', array_column($mi->fetchAll(), 'interest'));
        $fav = $pdo->prepare("SELECT target_id FROM favorites WHERE user_id=?"); $fav->execute([$uid]);
        $myFavs = array_map('strval', array_column($fav->fetchAll(), 'target_id'));

        $sql = "SELECT p.user_id, p.display_name, p.age, p.gender, p.city, p.bio, p.completion, p.created_at,
                       l.lat, l.lng, l.area, uu.avatar, uu.is_online, uu.presence_at,
                       (SELECT 1 FROM verified_users vu WHERE vu.user_id=p.user_id) AS verified
                FROM discover_profiles p
                JOIN discover_visibility v ON v.user_id=p.user_id AND v.enabled=1 AND v.mode<>'hidden'
                LEFT JOIN discover_location l ON l.user_id=p.user_id
                LEFT JOIN users uu ON uu.id=p.user_id
                WHERE p.user_id<>? AND p.age BETWEEN ? AND ?
                  AND p.user_id NOT IN (SELECT blocked_id FROM discover_blocks WHERE blocker_id=?)";
        $args = [$uid, $ageMin, $ageMax, $uid];
        if ($want !== 'everyone') { $sql .= " AND p.gender=?"; $args[] = $want; }
        if ($verifiedOnly) $sql .= " AND EXISTS (SELECT 1 FROM verified_users vu WHERE vu.user_id=p.user_id)";
        $sql .= ($mode === 'new') ? " ORDER BY p.created_at DESC" : "";
        $sql .= " LIMIT 300";
        $q = $pdo->prepare($sql); $q->execute($args);

        $out = [];
        foreach ($q->fetchAll() as $r) {
            $online = (int)($r['is_online'] ?? 0) === 1 && !empty($r['presence_at']) && strtotime($r['presence_at']) > time() - 70;
            if (($onlineOnly || $mode === 'online') && !$online) continue;
            $dist = null;
            if ($myLoc && $r['lat'] !== null) {
                $dist = ss_haversine((float)$myLoc['lat'], (float)$myLoc['lng'], (float)$r['lat'], (float)$r['lng']);
                if ($mode === 'nearby' && $dist > $maxKm) continue;
            }
            $ti = $pdo->prepare("SELECT interest FROM user_interests WHERE user_id=?"); $ti->execute([$r['user_id']]);
            $theirInts = array_map('strtolower', array_column($ti->fetchAll(), 'interest'));
            $common = array_intersect($myInts, $theirInts);
            $union  = count(array_unique(array_merge($myInts, $theirInts))) ?: 1;
            $overlap = count($common) / $union;
            $distClose = $dist === null ? 0.5 : max(0, 1 - ($dist / max(1, $maxKm)));
            $score = (int)round(40*$overlap + 25*$distClose + 5*(($r['completion'] ?? 0)/100) + ($online?5:0) + 15 + 10);
            $out[] = [
                'id' => (string)$r['user_id'], 'name' => $r['display_name'], 'age' => (int)$r['age'],
                'city' => $r['city'], 'avatar' => $r['avatar'], 'online' => $online,
                'verified' => (bool)$r['verified'], 'favorited' => in_array((string)$r['user_id'], $myFavs, true),
                'distanceKm' => $dist === null ? null : round($dist, 1), 'area' => $r['area'],
                'interests' => array_slice($theirInts, 0, 4), 'match' => min(99, max(40, $score)),
            ];
        }
        if ($mode === 'recommended') usort($out, fn($a,$b) => $b['match'] <=> $a['match']);
        elseif ($mode === 'nearby')  usort($out, fn($a,$b) => ($a['distanceKm'] ?? 9999) <=> ($b['distanceKm'] ?? 9999));
        elseif ($mode === 'online')  usort($out, fn($a,$b) => $b['match'] <=> $a['match']);
        return array_slice($out, 0, 60);
    }
}

// Auto-earned Discover badges from interests / verification / join date.
if (!function_exists('ss_discover_badges')) {
    function ss_discover_badges($interests, $verified, $created) {
        $b = [];
        if ($verified) $b[] = '✅ Verified';
        $ints = array_map('strtolower', (array)$interests);
        if (in_array('gaming', $ints, true))      $b[] = '🎮 Gamer';
        if (in_array('music', $ints, true))       $b[] = '🎵 Music Lover';
        if (in_array('photography', $ints, true)) $b[] = '📸 Photographer';
        if (in_array('travel', $ints, true))      $b[] = '✈️ Traveler';
        if (in_array('gym', $ints, true))         $b[] = '💪 Fitness';
        if ($created && strtotime($created) < strtotime('-30 days')) $b[] = '⭐ Early Member';
        return $b;
    }
}

if (!function_exists('json_ok')) {
    function json_ok($data = [], $code = 200) {
        http_response_code($code);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
}
if (!function_exists('json_err')) {
    function json_err($msg, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
}

function ss_token() {
    return bin2hex(random_bytes(20)); // 40 chars
}

if (!defined('SS_PAY_SECRET')) define('SS_PAY_SECRET', 'ssync_pay_9f2a7c1e5b3d80_loopr');

// Signed one-hour token → identifies the user on the subscribe.php payment page.
function ss_build_subscribe_url($pdo, $u) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $payload = rtrim(strtr(base64_encode(json_encode(['uid' => (int)$u['id'], 'exp' => time() + 3600])), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $payload, SS_PAY_SECRET);
    return $base . '/subscribe.php?t=' . $payload . '.' . $sig;
}
function ss_verify_subscribe_token($t) {
    $p = explode('.', (string)$t);
    if (count($p) !== 2) return null;
    if (!hash_equals(hash_hmac('sha256', $p[0], SS_PAY_SECRET), $p[1])) return null;
    $d = json_decode(base64_decode(strtr($p[0], '-_', '+/')), true);
    if (!is_array($d) || ($d['exp'] ?? 0) < time()) return null;
    return (int)$d['uid'];
}

function ss_setting($pdo, $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query("SELECT setting_key, setting_value FROM app_settings") as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (\Throwable $e) {}
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function ss_set_setting($pdo, $key, $value) {
    $stmt = $pdo->prepare(
        "INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->execute([$key, $value]);
}

/**
 * Guess what a user was doing inside an app for one minute, from how much data
 * it moved that minute. Only well-known consumer apps are classified — system,
 * Google-service and background apps return '' so they never show up. Thresholds
 * come from typical real-world data rates (Reels/video burn MBs a minute, texting
 * burns KBs). It's a heuristic (a smart guess), not proof.
 *
 * @param string $package  app package name (for app-aware thresholds)
 * @param int    $bpm      bytes used by the app in that minute
 */
// A dedicated video / streaming app. When one of these is the FOREGROUND app we
// call it "Watching" even at ~0 data — a downloaded/offline movie uses no data
// but is clearly being watched. Detected via the on-screen app, not the rate.
if (!function_exists('ss_is_video_app')) {
function ss_is_video_app($package) {
    $p = strtolower((string)$package);
    foreach ([
        'netflix','primevideo','amazon.avod','youtube','hotstar','jiocinema','sonyliv',
        'zee5','aha','mxtech','mxplayer','videoplayer','vlc','jiotv','airtelxstream',
        'discoveryplus','erosnow','voot','altbalaji','sunnxt','hoichoi'
    ] as $k) if (strpos($p, $k) !== false) return true;
    return false;
}
}

if (!function_exists('ss_activity_label')) {
function ss_activity_label($package, $bpm) {
    $bpm = (int)$bpm;
    $pkg = strtolower((string)$package);
    $KB = 1024; $MB = 1024 * 1024;

    // ---- Instagram: Reels ≈ 3–10 MB/min, feed ≈ 0.5–2 MB/min, DMs ≈ KBs ----
    if (strpos($pkg, 'instagram') !== false) {
        if ($bpm >= 2 * $MB)   return 'Watching Reels';
        if ($bpm >= 500 * $KB) return 'Scrolling Feed';
        if ($bpm >= 10 * $KB)  return 'Texting / DM';
        return '';
    }
    // ---- YouTube: video AND Shorts are both "watching" (can't split by rate) ----
    if (strpos($pkg, 'youtube') !== false) {
        if ($bpm >= 3 * $MB)   return 'Watching Video';
        if ($bpm >= 500 * $KB) return 'Watching (low-q)';
        if ($bpm >= 40 * $KB)  return 'Browsing YouTube';
        return '';
    }
    // ---- Short-video apps ----
    if (strpos($pkg, 'tiktok') !== false || strpos($pkg, 'musically') !== false ||
        strpos($pkg, 'moj') !== false || strpos($pkg, 'josh') !== false ||
        strpos($pkg, 'sharechat') !== false || strpos($pkg, 'snackvideo') !== false) {
        if ($bpm >= 2 * $MB)   return 'Watching Reels';
        if ($bpm >= 400 * $KB) return 'Scrolling';
        return '';
    }
    // ---- Snapchat ----
    if (strpos($pkg, 'snapchat') !== false) {
        if ($bpm >= 2 * $MB)   return 'Watching / Video';
        if ($bpm >= 300 * $KB) return 'Stories / Snaps';
        if ($bpm >= 10 * $KB)  return 'Chatting';
        return '';
    }
    // ---- Facebook ----
    if (strpos($pkg, 'facebook') !== false || strpos($pkg, 'katana') !== false) {
        if ($bpm >= 2 * $MB)   return 'Watching Reels/Video';
        if ($bpm >= 500 * $KB) return 'Scrolling Feed';
        if ($bpm >= 10 * $KB)  return 'Browsing';
        return '';
    }
    // ---- WhatsApp: video call ≈ 3–6 MB, voice ≈ 0.5–1 MB, media send spikes ----
    if (strpos($pkg, 'whatsapp') !== false) {
        if ($bpm >= 3 * $MB)   return 'Video Call';
        if ($bpm >= 400 * $KB) return 'Voice Call';
        if ($bpm >= 150 * $KB) return 'Sending Media';
        if ($bpm >= 4 * $KB)   return 'Texting';
        return '';
    }
    // ---- Other messengers ----
    if (strpos($pkg, 'telegram') !== false || strpos($pkg, 'signal') !== false ||
        strpos($pkg, 'messenger') !== false || strpos($pkg, 'discord') !== false) {
        if ($bpm >= 3 * $MB)   return 'Video Call';
        if ($bpm >= 400 * $KB) return 'Voice / Media';
        if ($bpm >= 4 * $KB)   return 'Texting';
        return '';
    }
    // ---- Streaming (Netflix, Hotstar, Prime, etc.) ----
    // These apps are for watching — any notable data almost always = watching.
    // Lower threshold catches "Save Data" / low-quality streaming too. NOTE: a
    // DOWNLOADED / offline title uses ~0 data, so it can't be seen here at all.
    if (strpos($pkg, 'netflix') !== false || strpos($pkg, 'hotstar') !== false ||
        strpos($pkg, 'primevideo') !== false || strpos($pkg, 'jiocinema') !== false ||
        strpos($pkg, 'sonyliv') !== false || strpos($pkg, 'zee5') !== false ||
        strpos($pkg, 'aha') !== false) {
        if ($bpm >= 1500 * $KB) return 'Watching Video';
        if ($bpm >= 150 * $KB)  return 'Watching (low-q/buffered)';
        return '';
    }
    // ---- Music ----
    if (strpos($pkg, 'spotify') !== false || strpos($pkg, 'gaana') !== false ||
        strpos($pkg, 'wynk') !== false || strpos($pkg, 'jiosaavn') !== false ||
        strpos($pkg, 'ytmusic') !== false || strpos($pkg, 'music.youtube') !== false) {
        if ($bpm >= 20 * $KB) return 'Listening Music';
        return '';
    }
    // ---- Twitter/X & Reddit ----
    if ($pkg === 'com.twitter.android' || strpos($pkg, 'twitter') !== false ||
        $pkg === 'com.x.android' || strpos($pkg, 'reddit') !== false) {
        if ($bpm >= 2 * $MB)   return 'Watching Video';
        if ($bpm >= 300 * $KB) return 'Scrolling Feed';
        if ($bpm >= 10 * $KB)  return 'Browsing';
        return '';
    }
    // ---- Browsers ----
    if (strpos($pkg, 'chrome') !== false || strpos($pkg, 'firefox') !== false ||
        strpos($pkg, 'opera') !== false || strpos($pkg, 'brave') !== false ||
        strpos($pkg, 'browser') !== false || strpos($pkg, 'duckduckgo') !== false) {
        if ($bpm >= 2 * $MB)   return 'Watching Video';
        if ($bpm >= 200 * $KB) return 'Browsing';
        return '';
    }

    // Anything else — system apps, Google services, launchers, background
    // sync, unknown apps — is deliberately hidden (returns nothing).
    return '';
}
}

// Return the couple row + partner for a user, or null.
// All 'connected' couple ids that contain this user, as a safe SQL IN() list
// (comma-joined ints, or '0'). Games look up sessions across ALL of them so a
// legacy duplicate couple row never splits partners onto different couple_ids
// (one side "waiting", the other "game ended").
function ss_couple_ids($pdo, $user_id) {
    $ids = [];
    try {
        $st = $pdo->prepare("SELECT id FROM couples WHERE (user1_id=? OR user2_id=?) AND status='connected'");
        $st->execute([$user_id, $user_id]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (\Throwable $e) {}
    return $ids ? implode(',', $ids) : '0';
}

function ss_couple_of($pdo, $user_id) {
    $stmt = $pdo->prepare(
        "SELECT * FROM couples
         WHERE (user1_id = ? OR user2_id = ?) AND status = 'connected'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$user_id, $user_id]);
    $c = $stmt->fetch();
    if (!$c) return null;
    $c['partner_id'] = ($c['user1_id'] == $user_id) ? $c['user2_id'] : $c['user1_id'];
    return $c;
}

// App-facing user shape (camelCase — matches the Flutter User.fromJson model).
function ss_user_app($pdo, $u) {
    if (!$u) return null;
    $couple = ss_couple_of($pdo, $u['id']);
    // Online = the app pinged presence within the last 70s (foreground only).
    $online = (int)($u['is_online'] ?? 0) === 1 &&
              !empty($u['presence_at']) && strtotime($u['presence_at']) > time() - 70;
    return [
        'id'          => (string)$u['id'],
        'username'    => $u['username'],
        'email'       => $u['email'] ?? '',
        'phone'       => $u['phone'] ?? null,
        'bio'         => $u['bio'] ?? null,
        'birthday'    => $u['birthday'] ?? null,
        'displayName' => $u['name'] ?? $u['username'],
        'avatar'      => $u['avatar'] ?? ($u['avatar_url'] ?? null),
        'isPaired'    => $couple ? true : false,
        'coupleId'    => $couple ? (string)$couple['id'] : null,
        'relationshipSince' => $couple['since'] ?? ($u['relationship_since'] ?? null),
        'inviteCode'  => $u['username'],           // partners connect using the username
        'currentMood' => $u['mood'] ?? 'happy',
        'quickNote'   => $u['quick_note'] ?? null,
        'isOnline'    => $online,
        'isPremium'   => (int)($u['is_premium'] ?? 0) === 1 &&
                         (empty($u['premium_until']) || strtotime($u['premium_until']) > time()),
        'premiumUntil'=> $u['premium_until'] ?? null,
        'gender'      => $u['gender'] ?? null,
        'notifEnabled'=> (int)($u['notif_enabled'] ?? 1) === 1,
        'hideOnline'  => (int)($u['hide_online'] ?? 0) === 1,
        'hideLastSeen'=> (int)($u['hide_last_seen'] ?? 0) === 1,
        'verified'    => (function() use ($pdo, $u) {
            try { return (int)$pdo->query("SELECT 1 FROM verified_users WHERE user_id=".(int)$u['id']." LIMIT 1")->fetchColumn() === 1; }
            catch (\Throwable $e) { return false; }
        })(),
    ];
}

// Build the full Couple-Quiz state for the app from a session row.
function ss_quiz_state($pdo, $meId, $session) {
    if (!$session) return ['active' => false];
    $qids = json_decode($session['q_ids'] ?? '[]', true) ?: [];
    $total = count($qids);
    $idx = (int)$session['current_q'];
    $status = $session['status'];

    // Current question.
    $question = null;
    if ($status === 'active' && isset($qids[$idx])) {
        $qs = $pdo->prepare("SELECT * FROM quiz_bank WHERE id = ?");
        $qs->execute([(int)$qids[$idx]]);
        $q = $qs->fetch();
        if ($q) $question = [
            'id' => (string)$q['id'], 'question' => $q['question'],
            'type' => $q['type'], 'category' => $q['category'],
            'options' => json_decode($q['options'] ?? '[]', true) ?: [],
        ];
    }

    // Answers for the current question.
    $myAns = null; $partnerAnswered = false; $partnerAns = null;
    $ar = $pdo->prepare("SELECT user_id, answer FROM quiz_answers WHERE session_id = ? AND q_index = ?");
    $ar->execute([(int)$session['id'], $idx]);
    foreach ($ar->fetchAll() as $r) {
        if ((int)$r['user_id'] === (int)$meId) $myAns = $r['answer'];
        else { $partnerAnswered = true; $partnerAns = $r['answer']; }
    }
    $revealed = ($myAns !== null && $partnerAnswered);

    // Score so far (indexes where both answered).
    $all = $pdo->prepare("SELECT q_index, user_id, answer FROM quiz_answers WHERE session_id = ?");
    $all->execute([(int)$session['id']]);
    $byIdx = [];
    foreach ($all->fetchAll() as $r) $byIdx[(int)$r['q_index']][] = $r['answer'];
    $completed = 0; $matches = 0;
    foreach ($byIdx as $ansList) {
        if (count($ansList) >= 2) {
            $completed++;
            if (mb_strtolower(trim($ansList[0])) === mb_strtolower(trim($ansList[1]))) $matches++;
        }
    }
    $pct = $total > 0 ? (int)round($matches / $total * 100) : 0;

    return [
        'active'          => in_array($status, ['active', 'paused'], true),
        'sessionId'       => (string)$session['id'],
        'status'          => $status,
        'category'        => $session['category'],
        'index'           => $idx,
        'total'           => $total,
        'question'        => $question,
        'myAnswer'        => $myAns,
        'partnerAnswered' => $partnerAnswered,
        'partnerAnswer'   => $revealed ? $partnerAns : null,
        'revealed'        => $revealed,
        'matched'         => $revealed ? (mb_strtolower(trim((string)$myAns)) === mb_strtolower(trim((string)$partnerAns))) : null,
        'completed'       => $completed,
        'matches'         => $matches,
        'percent'         => $pct,
        'createdBy'       => (string)$session['created_by'],
    ];
}

// State for the turn-based "Ask each other" quiz mode.
function ss_quiz_ask_state($pdo, $meId, $session) {
    if (!$session) return ['active' => false];
    $phase = $session['phase'] ?? 'active';
    $asker = $session['asker_id'] ? (int)$session['asker_id'] : null;
    $round = json_decode($session['round'] ?? 'null', true);
    $iAmAsker = ($asker !== null && $asker === (int)$meId);

    // Inviter name.
    $inv = $pdo->prepare("SELECT name FROM users WHERE id=?"); $inv->execute([(int)$session['created_by']]);
    $inviterName = $inv->fetchColumn() ?: 'Partner';
    $askerName = null;
    if ($asker) { $an = $pdo->prepare("SELECT name FROM users WHERE id=?"); $an->execute([$asker]); $askerName = $an->fetchColumn(); }

    $answer = null; $answerKey = null; $correct = null;
    if (is_array($round)) {
        // Answer visible to the asker always, to everyone once revealed.
        if ($phase === 'revealed' || $iAmAsker) $answer = $round['answer'] ?? null;
        // Correct answer (puzzle) revealed to everyone once answered.
        if (!empty($round['answerKey']) && ($phase === 'revealed' || $iAmAsker)) {
            $answerKey = $round['answerKey'];
            if (!empty($round['answer'])) {
                $correct = mb_strtolower(trim((string)$round['answer'])) === mb_strtolower(trim((string)$answerKey));
            }
        }
    }

    return [
        'active'      => !in_array($phase, ['stopped', 'finished'], true),
        'mode'        => 'ask',
        'game'        => $session['game'] ?? 'couple_quiz',
        'sessionId'   => (string)$session['id'],
        'phase'       => $phase,
        'askerId'     => $asker !== null ? (string)$asker : null,
        'iAmAsker'    => $iAmAsker,
        'askerName'   => $askerName,
        'createdBy'   => (string)$session['created_by'],
        'inviterName' => $inviterName,
        'question'    => is_array($round) ? ($round['question'] ?? null) : null,
        'options'     => is_array($round) ? ($round['options'] ?? []) : [],
        'answer'      => $answer,
        'answerKey'   => $answerKey,
        'correct'     => $correct,
        'answered'    => is_array($round) && !empty($round['answer']),
        'skipsLeft'   => max(0, 3 - (int)($session['skips_used'] ?? 0)),
    ];
}

// App-facing message shape.
function ss_msg_app($m) {
    $viewOnce = !empty($m['view_once']) && (int)$m['view_once'] === 1;
    $viewed   = $viewOnce && !empty($m['viewed_at']);
    $out = [
        'id'        => (string)$m['id'],
        'senderId'  => (string)$m['sender_id'],
        // View-once media URL is NEVER sent in the list — it's revealed only once
        // via chat/view-once/open, so it can't be re-fetched or cached.
        'content'   => $viewOnce ? '' : ($m['body'] ?? ''),
        'type'      => $m['type'] ?? 'text',
        'status'    => $m['status'] ?? 'sent',
        'createdAt' => str_replace(' ', 'T', (string)$m['created_at']),
        'viewOnce'  => $viewOnce,
        'viewed'    => $viewed,
    ];
    // WhatsApp-style quoted reply preview (present when the row was joined to the
    // replied message in chat/messages).
    if (!empty($m['reply_to_id'])) {
        $rt = $m['reply_type'] ?? 'text';
        $out['replyToId']       = (string)$m['reply_to_id'];
        $out['replyToText']     = $rt === 'image' ? '📷 Photo' : ($rt === 'video' ? '🎥 Video' : ($rt === 'album' ? '📷 Photos' : ($m['reply_body'] ?? '')));
        $out['replyToSenderId'] = (string)($m['reply_sender'] ?? '');
    }
    if (!empty($m['caption'])) $out['caption'] = (string)$m['caption'];
    if (!empty($m['pinned_at'])) $out['pinnedAt'] = str_replace(' ', 'T', (string)$m['pinned_at']);
    if (isset($m['reactions'])) $out['reactions'] = $m['reactions'];
    return $out;
}

// App-facing memory shape.
function ss_memory_app($m) {
    return [
        'id'          => (string)$m['id'],
        'title'       => $m['title'] ?? '',
        'description' => $m['body'] ?? null,
        'mediaUrl'    => $m['media_url'] ?? '',
        'mediaType'   => $m['type'] ?? 'image',
        'memoryDate'  => str_replace(' ', 'T', (string)($m['event_date'] ?: $m['created_at'])),
    ];
}

// Public shape of a user row.
function ss_user_public($u) {
    if (!$u) return null;
    return [
        'id'            => (int)$u['id'],
        'username'      => $u['username'],
        'name'          => $u['name'],
        'email'         => $u['email'] ?? null,
        'phone'         => $u['phone'] ?? null,
        'bio'           => $u['bio'] ?? null,
        'birthday'      => $u['birthday'] ?? null,
        'avatar_url'    => $u['avatar_url'] ?? null,
        'avatar_color'  => $u['avatar_color'] ?? '#F7A8B8',
        'mood'          => $u['mood'] ?? 'happy',
        'is_online'     => (int)($u['is_online'] ?? 0),
        'last_seen'     => $u['last_seen'] ?? null,
        'created_at'    => $u['created_at'] ?? null,
    ];
}
