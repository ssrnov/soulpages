<?php
// =========================================================================
// SoulSync Admin — bootstrap, session, and auth guard.
// Now part of soulpages: authenticated by the SAME soulpages admin login
// (same site, shared session). No separate SoulSync login / SSO anymore.
//   role 'admin'  → owner (sees everything)
//   role 'manager'→ manager (restricted sections hidden)
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

// --- Session timeout (30 min inactivity) ---
$maxIdle = 1800;
if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > $maxIdle) {
    session_unset(); session_destroy();
    header('Location: ../../admin/login.php?timeout=1'); exit;
}
$_SESSION['_last_activity'] = time();

// --- Regenerate session ID periodically (every 15 min) ---
if (!isset($_SESSION['_created'])) $_SESSION['_created'] = time();
if (time() - $_SESSION['_created'] > 900) {
    session_regenerate_id(true);
    $_SESSION['_created'] = time();
}

require_once __DIR__ . '/../includes/db.php';       // SoulSync DB (soulsync)
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/fcm.php';

function ss_admin_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Auth guard: must be logged into the soulpages admin as admin/manager ---
$__role = $_SESSION['user_role'] ?? '';
if (!in_array($__role, ['admin', 'manager'], true)) {
    // Not an admin session → send to the soulpages admin login.
    header('Location: ../../admin/login.php');
    exit;
}

$SS_ADMIN_NAME = $_SESSION['user_name'] ?? 'Admin';
$SS_ROLE = ($__role === 'admin') ? 'owner' : 'manager';

function ss_is_owner() { return (($_SESSION['user_role'] ?? '') === 'admin'); }

// Managers may NOT see App Usage / Notifications / Tracking / Location.
function ss_require_owner() {
    if (!ss_is_owner()) { header('Location: index.php'); exit; }
}
