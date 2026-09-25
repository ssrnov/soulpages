<?php
// Serves the latest uploaded SoulSync APK (published from the admin App Update page).
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Admin can close the public download with the switch on the App Update page.
$downloadEnabled = (int)ss_setting($pdo, 'download_enabled', '0') === 1;

// If a direct link is set (APK hosted anywhere), send the user straight to it.
$directLink = trim((string)ss_setting($pdo, 'download_url', ''));
if ($downloadEnabled && $directLink !== '' && preg_match('#^https?://#i', $directLink)) {
    header('Location: ' . $directLink, true, 302);
    exit;
}

$apk = ss_setting($pdo, 'apk_filename', '');
$path = __DIR__ . '/uploads/' . $apk;

// Fallback: if no filename is set (or missing), auto-pick the newest .apk in uploads/.
if (!$apk || !is_file($path)) {
    $files = glob(__DIR__ . '/uploads/*.apk') ?: [];
    if ($files) {
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $path = $files[0];
        $apk = basename($path);
    }
}

if ($downloadEnabled && $apk && is_file($path)) {
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="SoulSync.apk"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache');
    readfile($path);
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>SoulSync</title>'
   . '<div style="font-family:Inter,system-ui,sans-serif;background:#0f0b1e;color:#fff;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center">'
   . '<div><div style="font-size:3rem">💜</div><h2>SoulSync app is coming soon</h2>'
   . '<p style="color:#aaa">The download will be available here shortly.</p>'
   . '<a href="soulsync.php" style="color:#ec4899">← Back</a></div></div>';
