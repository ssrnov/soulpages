<?php
// =========================================================================
// SoulSync Configuration
// Replace all placeholder values before deploying to production.
// =========================================================================

// --- Firebase Authentication ---
define('FIREBASE_API_KEY', 'AIzaSyB-UcbJripzj5BYfXNZzGVGNRvp6fdpzdk');
define('FIREBASE_PROJECT_ID', 'loopr-5afff');

// --- Google OAuth 2.0 (Legacy — now handled by Firebase Auth) ---
define('GOOGLE_CLIENT_ID', '619239393964-epm3b0dbfm0b2c3lgdbg0bu432qfa2nk.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI', 'https://soulsyncc.site/auth/google-callback.php');

// --- Razorpay ---
define('RAZORPAY_KEY_ID', 'YOUR_RAZORPAY_KEY_ID');
define('RAZORPAY_KEY_SECRET', 'YOUR_RAZORPAY_KEY_SECRET');

// --- Site ---
define('SITE_NAME', 'SoulSync');

// Determine SITE_URL dynamically to prevent issues on localhost, subdirectories, or unconfigured domains
if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
    define('SITE_URL', 'https://yourdomain.com'); // Fallback for CLI/cron
} else {
    $config_project_root = str_replace('\\', '/', dirname(__DIR__));
    $config_script_filename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $config_script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    $config_base_path = '';
    if (!empty($config_script_filename) && !empty($config_script_name)) {
        if (strpos($config_script_filename, $config_project_root) === 0) {
            $config_rel_path = substr($config_script_filename, strlen($config_project_root));
            if (!empty($config_rel_path) && substr($config_script_name, -strlen($config_rel_path)) === $config_rel_path) {
                $config_base_path = substr($config_script_name, 0, strlen($config_script_name) - strlen($config_rel_path));
            }
        }
    }
    $config_base_path = rtrim($config_base_path, '/');
    $config_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://');
    $config_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('SITE_URL', $config_protocol . $config_host . $config_base_path);
}

define('SITE_TAGLINE', 'Express Your Feelings Beautifully');

// --- Defaults (overridden by site_settings table if present) ---
define('DEFAULT_FREE_PAGES', 1);
define('DEFAULT_PRICE_PAISE', 1000); // ₹10 = 1000 paise

// --- SMTP Configuration for Emails ---
define('SMTP_HOST', 'mail.loopr.site');
define('SMTP_PORT', 465);
define('SMTP_USER', 'noreply@soulsyncc.site');
define('SMTP_PASS', 'Jayshreeram@12345'); // Change to your actual email password
define('SMTP_SECURE', 'ssl'); // 'ssl' (port 465) or 'tls' (port 587)
