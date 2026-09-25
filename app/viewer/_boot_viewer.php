<?php
// =========================================================================
// TrackView viewer — bootstrap, session, and auth guard.
// Mirrors app/admin/_boot.php but authenticates via $_SESSION['viewer_id']
// (tracking_viewers table) instead of the soulpages admin session, and
// restricts the visible user to whichever target the viewer is allowed to see.
// =========================================================================
date_default_timezone_set('Asia/Kolkata');

// --- Session hardening ---
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// --- Security headers ---
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://accounts.google.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-ancestors 'none';");
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Session timeout (20 min inactivity) ---
$maxIdle = 1200;
if (isset($_SESSION['_viewer_last']) && (time() - $_SESSION['_viewer_last']) > $maxIdle) {
    unset($_SESSION['viewer_id']);
}
$_SESSION['_viewer_last'] = time();

function ss_admin_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Auth guard: must be a logged-in tracking viewer ---
if (empty($_SESSION['viewer_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, name, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['viewer_id']]);
$__viewer = $stmt->fetch();
if (!$__viewer) {
    unset($_SESSION['viewer_id']);
    header('Location: login.php');
    exit;
}

$VIEWER_NAME = $__viewer['name'] ?: $__viewer['username'];

// --- Load this viewer's assigned targets ---
$stmt = $pdo->prepare("SELECT tv.target_id, u.username, u.name FROM tracking_viewers tv JOIN users u ON u.id = tv.target_id WHERE tv.viewer_id = ? ORDER BY u.name");
$stmt->execute([$__viewer['id']]);
$VIEWER_TARGETS = $stmt->fetchAll();

if (empty($VIEWER_TARGETS)) {
    // No access granted at all — show a simple message instead of crashing pages
    // that assume a valid $uid.
    $VIEWER_TID = 0;
} else {
    $requested = (int)($_GET['user'] ?? 0);
    $allowed = false;
    foreach ($VIEWER_TARGETS as $t) { if ((int)$t['target_id'] === $requested) { $allowed = true; break; } }
    $VIEWER_TID = $allowed ? $requested : (int)$VIEWER_TARGETS[0]['target_id'];
}

// Force every included admin-page query to operate on the viewer's allowed target.
$uid = $VIEWER_TID;
$_GET['user'] = (string)$VIEWER_TID;

// The copied tracking pages call these two (from admin/_boot.php). In viewer
// context every viewer is effectively "owner" of their OWN allowed target only
// (enforced above by forcing $uid = $VIEWER_TID), so these are harmless no-ops.
if (!function_exists('ss_is_owner'))     { function ss_is_owner() { return true; } }
if (!function_exists('ss_require_owner')){ function ss_require_owner() { /* no-op in viewer context */ } }
$SS_ADMIN_NAME = $VIEWER_NAME;
$SS_ROLE = 'owner';
