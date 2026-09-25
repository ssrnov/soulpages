<?php
// SoulSync — wake phones that stopped reporting.
//
// Sends a silent FCM "wake" push to every logged-in user whose last device ping
// is older than the threshold, so the app revives its tracking service without
// being opened. Pairs with the on-device foreground service + watchdog.
//
// Run every ~5 minutes via cron:
//   */5 * * * * php /home/USER/public_html/soulpages/app/cron/wake_stale.php
//
// Requires the FCM service-account JSON in admin Settings. A user "Force stop"
// blocks delivery until the app is opened again — no server can work around that.

if (php_sapi_name() !== 'cli' && isset($_SERVER['REMOTE_ADDR'])) {
    // Allow a browser/cron-URL trigger too, but keep it lightly guarded.
    if (($_GET['key'] ?? '') !== 'soulsync_wake') { http_response_code(403); die('Forbidden'); }
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/fcm.php';

// How stale (minutes) before we consider a phone "gone quiet". Default 1.
$staleMin = (int)ss_setting($pdo, 'wake_stale_minutes', '1');

$log = function ($m) { echo '[' . date('Y-m-d H:i:s') . "] $m\n"; };

try {
    // No throttle here — the cron interval already paces it.
    $woke = ss_wake_stale($pdo, $staleMin, 0);
    $log("Done. Woke $woke stale device(s) (threshold {$staleMin} min).");
} catch (\Throwable $e) {
    $log('ERROR: ' . $e->getMessage());
}
