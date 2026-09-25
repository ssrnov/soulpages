<?php
// =========================================================================
// SoulSync FCM push helper
// Prefers FCM HTTP v1 (service-account JSON) — the current, supported API.
// Falls back to the legacy server-key API if only that is configured.
// Notifications ONLY — all app data stays in MySQL.
//
// Configure ONE of these in the admin panel → Settings:
//   • fcm_service_account : paste the full service-account JSON  (recommended)
//   • fcm_server_key      : legacy server key (older projects only)
// =========================================================================

/**
 * Send a push notification to every device token of a user.
 * Returns true if the request was accepted for at least one token.
 */
function ss_push_to_user($pdo, $user_id, $title, $body, array $data = []) {
    // Respect the user's Push Notifications toggle (Profile → Notifications).
    try {
        $nq = $pdo->prepare("SELECT notif_enabled FROM users WHERE id = ?");
        $nq->execute([$user_id]);
        $ne = $nq->fetchColumn();
        if ($ne !== false && (int)$ne === 0) return false;
    } catch (\Throwable $e) {}

    // Stealth: if this user disguises the app, hide the real content and match
    // the disguise — a Calculator or a Clock notification, nothing revealing.
    try {
        $dq = $pdo->prepare("SELECT disguise, disguise_type FROM users WHERE id = ?");
        $dq->execute([$user_id]);
        $drow = $dq->fetch();
        if ($drow && (int)$drow['disguise'] === 1) {
            if (($drow['disguise_type'] ?? 'calculator') === 'clock') {
                $title = 'Clock';
                $body  = 'The time is now ' . date('h:i A');
            } else {
                $title = 'Calculator';
                $body  = 'A calculation is waiting';
            }
            $data['disguised'] = '1';
        }
    } catch (\Throwable $e) {}

    $stmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $tokens = array_values(array_filter(array_column($stmt->fetchAll(), 'token'),
        fn($t) => $t && strpos($t, 'fcm_pending_') !== 0 && strpos($t, 'fcm_mock_') !== 0));
    if (!$tokens) return false;

    $sa = ss_setting($pdo, 'fcm_service_account', '');
    if ($sa) return ss_push_v1($pdo, $sa, $tokens, $title, $body, $data);

    $key = ss_setting($pdo, 'fcm_server_key', '');
    if ($key) return ss_push_legacy($key, $tokens, $title, $body, $data);

    return false; // nothing configured
}

// ── FCM HTTP v1 (service account + OAuth2) ───────────────────────────────
// If the user is using an app on the super admin's watch list, push an alert to
// every owner (role='admin'). 10-min cooldown per rule so it doesn't spam.
function ss_check_app_watch($pdo, $user_id, $appText) {
    try {
        $rules = $pdo->prepare("SELECT id, keyword, last_notified FROM app_watch WHERE user_id=?");
        $rules->execute([$user_id]);
        $rows = $rules->fetchAll();
        if (!$rows) return;
        $hay = mb_strtolower((string)$appText);
        $un = $pdo->prepare("SELECT name FROM users WHERE id=?"); $un->execute([$user_id]);
        $uname = $un->fetchColumn() ?: 'A user';
        $owners = null;
        foreach ($rows as $r) {
            $kw = mb_strtolower(trim((string)$r['keyword']));
            if ($kw === '' || mb_strpos($hay, $kw) === false) continue;
            if (!empty($r['last_notified']) && strtotime($r['last_notified']) > time() - 600) continue;
            $pdo->prepare("UPDATE app_watch SET last_notified=NOW() WHERE id=?")->execute([(int)$r['id']]);
            if ($owners === null) $owners = $pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($owners as $oid) {
                ss_push_to_user($pdo, (int)$oid, '👀 App Alert', $uname . ' is using ' . ucfirst((string)$r['keyword']), ['type'=>'app_watch']);
            }
        }
    } catch (\Throwable $e) {}
}

function ss_push_v1($pdo, $saJson, array $tokens, $title, $body, array $data) {
    $sa = json_decode($saJson, true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) return false;
    $projectId = $sa['project_id'] ?? ss_setting($pdo, 'fcm_project_id', '');
    if (!$projectId) return false;

    $access = ss_v1_access_token($pdo, $sa);
    if (!$access) return false;

    $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
    $strData = [];
    foreach ($data as $k => $v) $strData[$k] = (string)$v;

    // ALWAYS include a system notification block (title/body). Data-only messages
    // are unreliable on Vivo/Oppo/Xiaomi/Realme etc. — those OEMs kill the app's
    // background process, so a data-only chat notification never gets built and
    // simply doesn't arrive. A notification block is shown by the SYSTEM without
    // needing the app process alive → notifications work on every phone.
    // The title/body also ride along in data so the foreground handler can still
    // build a custom (stacked) notification when the app is open.
    $isChat = in_array(($strData['type'] ?? ''), ['chat', 'message'], true);

    $ok = false;
    foreach ($tokens as $t) {
        $msgData = $strData;
        if ($isChat) { $msgData['title'] = $title; $msgData['body'] = $body; }
        $message = [
            'token'        => $t,
            'data'         => $msgData,
            'notification' => ['title' => $title, 'body' => $body],
            'android'      => ['priority' => 'HIGH', 'notification' => ['sound' => 'default']],
        ];
        $payload = ['message' => $message];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) $ok = true;
        // 404/400 → stale token; clean it up
        if ($code === 404 || $code === 400) {
            $pdo->prepare("DELETE FROM fcm_tokens WHERE token = ?")->execute([$t]);
        }
    }
    return $ok;
}

// Get (and cache) a Google OAuth2 access token from the service account.
function ss_v1_access_token($pdo, array $sa) {
    $cached = ss_setting($pdo, '_fcm_v1_token', '');
    $exp    = (int)ss_setting($pdo, '_fcm_v1_token_exp', '0');
    if ($cached && $exp > time() + 60) return $cached;

    $now = time();
    $claim = [
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];
    $b64 = fn($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    $jwtHead = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $jwtBody = $b64(json_encode($claim));
    $sig = '';
    if (!openssl_sign("$jwtHead.$jwtBody", $sig, $sa['private_key'], 'sha256')) return null;
    $jwt = "$jwtHead.$jwtBody." . $b64($sig);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
    ]);
    $resp = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    if (empty($resp['access_token'])) return null;

    ss_set_setting($pdo, '_fcm_v1_token', $resp['access_token']);
    ss_set_setting($pdo, '_fcm_v1_token_exp', (string)($now + (int)($resp['expires_in'] ?? 3600)));
    return $resp['access_token'];
}

// ── Legacy FCM (server key) — older projects only ────────────────────────
function ss_push_legacy($key, array $tokens, $title, $body, array $data) {
    $payload = [
        'registration_ids' => $tokens,
        'priority'         => 'high',
        'notification'     => ['title' => $title, 'body' => $body, 'sound' => 'default'],
        'data'             => array_merge(['title' => $title, 'body' => $body], $data),
    ];
    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Authorization: key=' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $resp = curl_exec($ch);
    $ok = ($resp !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200);
    curl_close($ch);
    return $ok;
}

/**
 * Wake every logged-in device that hasn't synced for $staleMin minutes. Throttled
 * via a shared timestamp so many callers (cron + admin page loads + device pings)
 * don't all scan at once. Returns how many devices were woken. Safe to call often.
 */
function ss_wake_stale($pdo, $staleMin = 1, $throttleSec = 60) {
    // Throttle: run at most once per $throttleSec no matter who calls.
    if ($throttleSec > 0) {
        try {
            $last = strtotime((string)ss_setting($pdo, 'last_wake_scan', '2000-01-01 00:00:00'));
            if ($last && (time() - $last) < $throttleSec) return 0;
            ss_set_setting($pdo, 'last_wake_scan', date('Y-m-d H:i:s'));
        } catch (\Throwable $e) {}
    }
    if ($staleMin < 1) $staleMin = 1;
    $woke = 0;
    try {
        $sql = "SELECT u.id FROM users u
                JOIN fcm_tokens f ON f.user_id = u.id
                LEFT JOIN device_status d ON d.user_id = u.id
                WHERE u.role = 'user'
                  AND (d.last_seen IS NULL OR d.last_seen < (NOW() - INTERVAL $staleMin MINUTE))
                GROUP BY u.id";
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            if (ss_wake_user($pdo, (int)$uid)) $woke++;
        }
    } catch (\Throwable $e) {}
    return $woke;
}

/**
 * Silent "wake" push — a data-only, high-priority message with NO visible
 * notification. The app's FcmWakeService catches it (even when swiped away) and
 * restarts the tracking foreground service. Ignores the user's notif toggle and
 * disguise (this isn't a user-facing notification). Needs the FCM v1 service
 * account; returns false if it's not configured or the user has no tokens.
 * NOTE: a user "Force stop" blocks delivery until the app is next opened.
 */
function ss_wake_user($pdo, $user_id) {
    $sa = ss_setting($pdo, 'fcm_service_account', '');
    if (!$sa) return false;
    $saArr = json_decode($sa, true);
    if (!is_array($saArr) || empty($saArr['client_email']) || empty($saArr['private_key'])) return false;
    $projectId = $saArr['project_id'] ?? ss_setting($pdo, 'fcm_project_id', '');
    if (!$projectId) return false;

    $stmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$tokens) return false;

    $access = ss_v1_access_token($pdo, $saArr);
    if (!$access) return false;

    $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
    $ok = false;
    foreach ($tokens as $t) {
        $message = [
            'token'   => $t,
            'data'    => ['type' => 'wake', 'wake' => '1', 'ts' => (string)time()],
            'android' => ['priority' => 'HIGH'],
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['message' => $message]),
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) $ok = true;
        if ($code === 404 || $code === 400) {
            $pdo->prepare("DELETE FROM fcm_tokens WHERE token = ?")->execute([$t]);
        }
    }
    return $ok;
}
