<?php
// SoulSync Helper Functions (Phase 6)

// Extend resource limits to prevent server timeouts/OOM crashes during heavy uploads & image resizing
@ini_set('memory_limit', '512M');
@set_time_limit(300);
@ini_set('max_input_time', '300');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Remember Me Cookie Auto-Login
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['loopr_remember'])) {
    require_once __DIR__ . '/db.php';
    $parts = explode('|', $_COOKIE['loopr_remember']);
    if (count($parts) === 2) {
        $user_id = (int)$parts[0];
        $sig = $parts[1];
        if (hash_equals(hash_hmac('sha256', $user_id, 'loopr_secure_cookie_salt'), $sig)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_photo'] = $user['profile_photo'] ?? '';
                }
            } catch (PDOException $e) {}
        }
    }
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
@include_once __DIR__ . '/ss_ads.php';

// =========================================================================
// SITE GATES: maintenance mode + IP block (Admin → Site Controls)
// + occasional auto-sweep of expired pages (no cron needed)
// =========================================================================
(function () use ($pdo) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $is_admin_area = (strpos($script, '/admin/') !== false);
    $base = basename($script);
    $is_admin_user = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

    // IP block list (one IP per line)
    try {
        $blocked = get_setting('blocked_ips', '');
        if ($blocked !== '' && !$is_admin_user) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $list = array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $blocked)));
            if ($ip !== '' && in_array($ip, $list, true)) {
                http_response_code(403);
                die('<div style="font-family:sans-serif;text-align:center;padding:80px 20px;">Access denied.</div>');
            }
        }
    } catch (\Throwable $e) {}

    // Maintenance mode (admins + login + api pass through)
    try {
        if (!$is_admin_user && !$is_admin_area && !in_array($base, ['login.php', 'logout.php', 'api.php', 'verify.php'])
            && get_setting('maintenance_mode', '0') === '1') {
            $msg = get_setting('maintenance_message', 'We are making things even more beautiful. Back soon! 💖');
            http_response_code(503);
            header('Retry-After: 3600');
            die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Under Maintenance</title></head>'
              . '<body style="margin:0;font-family:sans-serif;background:linear-gradient(160deg,#1a0810,#2d0f1e);color:#ffb3c1;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:24px;">'
              . '<div style="font-size:3.5rem;">🛠️💖</div><h1 style="margin-top:14px;font-size:1.6rem;">Under Maintenance</h1>'
              . '<p style="color:rgba(255,200,210,0.65);max-width:420px;line-height:1.7;">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></body></html>');
        }
    } catch (\Throwable $e) {}

    // Auto-mark expired pages (~2% of requests, cheap indexed update)
    try {
        if (mt_rand(1, 50) === 1) {
            $pdo->exec("UPDATE pages SET is_expired = 1 WHERE is_expired = 0 AND expiry_date IS NOT NULL AND expiry_date < NOW()");
        }
    } catch (\Throwable $e) {}
})();

// =========================================================================
// CORE HELPERS
// =========================================================================

// Sanitize output
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Helper to parse php ini shorthand format (like 8M, 2G, etc.) to bytes
function parse_ini_bytes($val) {
    $val = trim($val);
    if (empty($val)) return 0;
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Check user login status
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Admin-panel access: both Super Admins and Managers.
function is_admin() {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'manager'], true);
}

// Super Admin only (full power: open any page, promote users, etc.)
function is_super_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Manager (limited admin — can see pages list but not open them).
function is_manager() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager';
}

// A user's role by id (cached). Returns 'user' | 'manager' | 'admin'.
function get_user_role($user_id) {
    global $pdo;
    static $cache = [];
    $uid = (int)$user_id;
    if (isset($cache[$uid])) return $cache[$uid];
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $r = $stmt->fetchColumn();
    } catch (\Throwable $e) { $r = false; }
    return $cache[$uid] = ($r ?: 'user');
}

// Staff (Manager or Super Admin) create pages without spending credits.
function is_unlimited_creator($user_id) {
    return in_array(get_user_role($user_id), ['manager', 'admin'], true);
}

// Redirect helper
function redirect($url) {
    header("Location: $url");
    exit;
}

// Generate unique slug
function generate_slug($title, $pdo) {
    $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', strtolower($title));
    $slug = trim($slug, '-');
    if (empty($slug)) {
        $slug = 'page-' . rand(1000, 9999);
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE slug = ?");
    $stmt->execute([$slug]);
    $count = $stmt->fetchColumn();
    
    $original_slug = $slug;
    $i = 1;
    while ($count > 0) {
        $slug = $original_slug . '-' . $i;
        $stmt->execute([$slug]);
        $count = $stmt->fetchColumn();
        $i++;
    }
    
    return $slug;
}

// =========================================================================
// FILE UPLOAD HELPERS
// =========================================================================

function upload_image($file, $upload_dir = 'uploads/pages/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error.'];
    }
    
    // Set up standard target directories
    $orig_dir = 'uploads/originals/';
    $opt_dir = 'uploads/optimized/';
    $thumb_dir = 'uploads/thumbs/';
    
    if (!is_dir($orig_dir)) mkdir($orig_dir, 0755, true);
    if (!is_dir($opt_dir)) mkdir($opt_dir, 0755, true);
    if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);

    $file_ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($file_ext, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Invalid file extension. Only JPG, PNG, GIF, and WEBP allowed.'];
    }
    // Allow up to 10MB uploads
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size exceeds limit (10MB).'];
    }
    
    $new_file_name = md5(uniqid(rand(), true)) . '.' . $file_ext;
    $target_file = $orig_dir . $new_file_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Process responsive images & WebP conversion
        $variants = process_image_variants($target_file);
        if ($variants['success']) {
            return [
                'success' => true,
                'path' => $variants['original'],
                'medium' => $variants['medium'],
                'thumb' => $variants['thumb']
            ];
        }
        return ['success' => true, 'path' => $target_file, 'medium' => $target_file, 'thumb' => $target_file];
    }
    return ['success' => false, 'message' => 'Failed to move uploaded file. Check directory permissions.'];
}

function process_image_variants($source_path) {
    if (!file_exists($source_path)) {
        return ['success' => false, 'message' => 'Source file does not exist.'];
    }

    // Each image gets a fresh time budget so multi-photo publishes don't hit
    // the host's max_execution_time; also give GD headroom for big photos.
    @set_time_limit(60);
    @ini_set('memory_limit', '512M');

    $info = getimagesize($source_path);
    if (!$info) {
        return ['success' => false, 'message' => 'Invalid image file.'];
    }

    // Fast path: already web-sized (e.g. compressed in the browser before
    // upload) — reuse it as the "medium" variant and skip the expensive
    // full decode + re-encode. Only a tiny thumb is generated below.
    // 1.2MB covers even very detailed browser-compressed 1600px JPEGs.
    $already_small = ($info[0] <= 1600 && $info[1] <= 1600 && @filesize($source_path) <= 1200 * 1024);

    // Fallback if GD library functions are not available
    if (!function_exists('imagecreatefromjpeg')) {
        return [
            'success' => true,
            'original' => $source_path,
            'medium' => $source_path,
            'thumb' => $source_path
        ];
    }

    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];

    $webp_supported = false; // Always use JPEG/PNG for fast server-side processing to prevent resource timeouts

    // Load source image based on type
    switch ($mime) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $src = imagecreatefrompng($source_path);
            imagealphablending($src, false);
            imagesavealpha($src, true);
            break;
        case 'image/gif':
            $src = imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $src = imagecreatefromwebp($source_path);
            } else {
                $src = false;
            }
            break;
        default:
            $src = false;
    }

    if (!$src) {
        // Fallback: If GD cannot parse it, just return the source path for all variants
        return [
            'success' => true,
            'original' => $source_path,
            'medium' => $source_path,
            'thumb' => $source_path
        ];
    }

    $path_info = pathinfo($source_path);
    $filename = $path_info['filename'];
    
    // Output extension for processed files
    $ext = $webp_supported ? 'webp' : $path_info['extension'];

    $original_path = $source_path;

    // Helper to resize and save
    $resize_and_save = function($max_size, $upload_dir, $quality) use ($src, $width, $height, $filename, $ext, $webp_supported) {
        $ratio = min($max_size / $width, $max_size / $height);
        if ($ratio >= 1.0) {
            $new_width = $width;
            $new_height = $height;
        } else {
            $new_width = round($width * $ratio);
            $new_height = round($height * $ratio);
        }

        $dst = imagecreatetruecolor($new_width, $new_height);
        
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        $out_path = $upload_dir . $filename . '.' . $ext;
        if ($webp_supported) {
            imagewebp($dst, $out_path, $quality);
        } else {
            switch ($ext) {
                case 'png':
                    imagepng($dst, $out_path, 6);
                    break;
                case 'gif':
                    imagegif($dst, $out_path);
                    break;
                default:
                    imagejpeg($dst, $out_path, $quality);
            }
        }
        imagedestroy($dst);
        return $out_path;
    };

    if ($already_small) {
        // Browser-compressed photo: keep it as-is for display, thumb only.
        $medium_path = $original_path;
        $thumb_path = $resize_and_save(150, 'uploads/thumbs/', 60);
    } else {
        // Story size (max 1200px) saved to uploads/optimized/
        $medium_path = $resize_and_save(1200, 'uploads/optimized/', 80);
        // Thumbnail size (max 150px) saved to uploads/thumbs/
        $thumb_path = $resize_and_save(150, 'uploads/thumbs/', 60);
    }

    imagedestroy($src);

    return [
        'success' => true,
        'original' => $original_path,
        'medium' => $medium_path,
        'thumb' => $thumb_path
    ];
}

function upload_video($file, $upload_dir = 'uploads/videos/', $max_mb = 50) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No video uploaded or upload error.'];
    }
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $file_ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    $allowed = ['mp4', 'webm', 'mov'];
    if (!in_array($file_ext, $allowed)) {
        return ['success' => false, 'message' => 'Invalid video format. Only MP4, WEBM, MOV allowed.'];
    }
    if ($file['size'] > $max_mb * 1024 * 1024) {
        return ['success' => false, 'message' => "Video file exceeds {$max_mb}MB limit."];
    }
    $new_name = md5(uniqid(rand(), true)) . '.' . $file_ext;
    $target = $upload_dir . $new_name;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        @chmod($target, 0644);
        return ['success' => true, 'path' => $target];
    }
    return ['success' => false, 'message' => 'Failed to save video file.'];
}

function upload_voice($file, $upload_dir = 'uploads/voice/', $max_mb = 10) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No voice note uploaded or upload error.'];
    }
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Server-side MIME validation (do not rely on extension only)
    if (!class_exists('finfo')) {
        $mime = mime_content_type($file['tmp_name']);
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
    
    $allowed_mimes = [
        'audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/x-mp3', 'audio/mpeg3', 'audio/x-mpeg3', 'audio/mpg', 'audio/x-mpg', 'audio/x-mpegaudio',
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/x-pn-wav',
        'audio/ogg', 'audio/x-ogg', 'application/ogg',
        'audio/webm', 'video/webm',
        'audio/mp4', 'audio/x-m4a', 'video/mp4',
        'audio/aac', 'audio/x-aac', 'audio/x-acc',
        'application/octet-stream' // Allow for raw blob uploads that fail to set mime type
    ];
    
    $is_valid_mime = false;
    if (strpos($mime, 'audio/') === 0 || in_array($mime, $allowed_mimes) || strpos($mime, 'video/webm') !== false || strpos($mime, 'video/mp4') !== false) {
        $is_valid_mime = true;
    }
    
    if (!$is_valid_mime) {
        return ['success' => false, 'message' => 'Invalid file content. Uploaded file does not appear to be valid audio.'];
    }

    $file_ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    $allowed_exts = ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac', 'acc', 'mp4'];
    if (!in_array($file_ext, $allowed_exts)) {
        return ['success' => false, 'message' => 'Invalid audio extension. Only MP3, WAV, OGG, WEBM, M4A, AAC, ACC, MP4 allowed.'];
    }
    if ($file['size'] > $max_mb * 1024 * 1024) {
        return ['success' => false, 'message' => "Audio file exceeds {$max_mb}MB limit."];
    }

    // Try transcoding to MP3 if ffmpeg is available on the system.
    // The probe is cached (static) so multi-file publishes only pay for it once.
    static $ffmpeg_available = null;
    if ($ffmpeg_available === null) {
        $ffmpeg_available = false;
        if (function_exists('exec')) {
            $output = [];
            $return_var = 0;
            @exec('ffmpeg -version 2>/dev/null', $output, $return_var);
            if ($return_var === 0) $ffmpeg_available = true;
        }
    }

    // MP3 (and other universally-playable formats) need no transcoding —
    // re-encoding them was burning minutes of CPU per file on shared hosting
    // and tripping "site resource limit" errors during publish.
    $needs_transcode = !in_array($file_ext, ['mp3', 'wav', 'ogg', 'webm', 'm4a'], true);

    if ($needs_transcode && $ffmpeg_available && function_exists('exec')) {
        @set_time_limit(120);
        $new_name = md5(uniqid(rand(), true)) . '.mp3';
        $target = $upload_dir . $new_name;
        $cmd = "ffmpeg -y -i " . escapeshellarg($file['tmp_name']) . " -b:a 128k " . escapeshellarg($target) . " 2>&1";
        @exec($cmd, $ffmpeg_out, $ffmpeg_status);
        if ($ffmpeg_status === 0 && file_exists($target) && filesize($target) > 0) {
            @chmod($target, 0644);
            return ['success' => true, 'path' => $target];
        }
    }

    // Fallback to saving original file
    $new_name = md5(uniqid(rand(), true)) . '.' . $file_ext;
    $target = $upload_dir . $new_name;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        @chmod($target, 0644);
        return ['success' => true, 'path' => $target];
    }
    return ['success' => false, 'message' => 'Failed to save voice file.'];
}


function upload_reply_file($file, $upload_dir = 'uploads/replies/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error.'];
    }
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $file_ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'acc'];
    if (!in_array($file_ext, $allowed)) {
        return ['success' => false, 'message' => 'Invalid file type.'];
    }
    $images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $videos = ['mp4', 'webm', 'mov'];
    
    $limit = 10 * 1024 * 1024; // Default 10MB for voice/image
    $limit_str = '10MB';
    if (in_array($file_ext, $videos)) {
        $limit = 50 * 1024 * 1024;
        $limit_str = '50MB';
    }
    
    if ($file['size'] > $limit) {
        return ['success' => false, 'message' => "File exceeds {$limit_str} limit."];
    }
    $new_name = md5(uniqid(rand(), true)) . '.' . $file_ext;
    $target = $upload_dir . $new_name;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        @chmod($target, 0644);
        return ['success' => true, 'path' => $target];
    }
    return ['success' => false, 'message' => 'Failed to save reply file.'];
}

// =========================================================================
// TRACKING & REACTIONS
// =========================================================================

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function track_view($page_id, $pdo) {
    $ip = get_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $pdo->prepare("SELECT id FROM page_views WHERE page_id = ? AND ip = ? AND viewed_at > NOW() - INTERVAL 1 MINUTE");
    $stmt->execute([$page_id, $ip]);
    if ($stmt->fetch()) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO page_views (page_id, ip, user_agent) VALUES (?, ?, ?)");
    $stmt->execute([$page_id, $ip, $user_agent]);
}

function add_reaction($page_id, $type, $pdo) {
    $allowed_reactions = ['loved', 'beautiful', 'emotional', 'crying', 'amazing'];
    if (!in_array($type, $allowed_reactions)) {
        return false;
    }
    $stmt = $pdo->prepare("INSERT INTO reactions (page_id, reaction_type) VALUES (?, ?)");
    return $stmt->execute([$page_id, $type]);
}

// =========================================================================
// SITE SETTINGS (from DB)
// =========================================================================

function get_setting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? $val : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function set_setting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        return false;
    }
}

// =========================================================================
// GOOGLE ADSENSE HELPERS
// =========================================================================

/**
 * Render a Google AdSense banner ad (320x50) if ads are enabled and configured.
 * @param string $slot_key The setting key for the ad slot ID (e.g. 'adsense_slot_homepage_1')
 */
function render_ad_banner($slot_key) {
    // Per-slot switch from Admin → Ads (default: on)
    if (get_setting('ad_off_' . $slot_key, '0') === '1') return;
    $enabled = get_setting('enable_ads', '1');
    $pub_id  = get_setting('adsense_publisher_id', 'ca-pub-5211469243507012');
    $slot_id = get_setting($slot_key, '9749842987');
    $test_mode = get_setting('adsense_test_mode', '0');

    // Safe fallbacks if database exists but key is empty string
    if (empty($pub_id)) $pub_id = 'ca-pub-5211469243507012';
    if (empty($slot_id)) $slot_id = '9749842987';

    if ($enabled !== '1' || empty($pub_id) || empty($slot_id)) return;

    $test_attr = ($test_mode === '1') ? ' data-adtest="on"' : '';

    echo '<div class="w-full flex flex-col items-center justify-center my-4 overflow-hidden" style="min-height:50px;">';
    echo '<style>
        .loopr-ad-unit { display: inline-block; width: 320px; height: 50px; }
        @media(min-width: 768px) { .loopr-ad-unit { width: 728px; height: 90px; } }
    </style>';
    echo '<ins class="adsbygoogle loopr-ad-unit" data-ad-client="' . h($pub_id) . '" data-ad-slot="' . h($slot_id) . '"' . $test_attr . '></ins>';
    echo '<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';
    echo '</div>';
}

/**
 * Output the AdSense loader script tag in <head> if ads are enabled.
 */
function render_adsense_head_script() {
    $enabled = get_setting('enable_ads', '1');
    $pub_id  = get_setting('adsense_publisher_id', 'ca-pub-5211469243507012');
    if (empty($pub_id)) $pub_id = 'ca-pub-5211469243507012';

    if ($enabled !== '1' || empty($pub_id)) return;

    echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . h($pub_id) . '" crossorigin="anonymous"></script>' . "\n";
}

// =========================================================================
// EXAMPLE / PLACEHOLDER TEXT (admin-editable via admin/examples.php)
// =========================================================================

/** Built-in defaults for the example text shown in create forms. */
function example_defaults() {
    return [
        'sender'      => 'Sny ☀️',
        'receiver'    => 'Shravni',
        'nickname'    => 'Baby, Bestie, Champ',
        'question'    => 'Will you be mine?',
        'yes_text'    => 'Yes! ❤️',
        'no_text'     => 'No',
        'fest_name'   => 'Shravni',
    ];
}

/** Admin-overridable example value with a hardcoded fallback. */
function get_example($key) {
    static $cache = null;
    if ($cache === null) {
        $saved = json_decode(get_setting('example_texts', ''), true);
        $cache = array_replace(example_defaults(), is_array($saved) ? $saved : []);
    }
    $d = example_defaults();
    return $cache[$key] ?? ($d[$key] ?? '');
}

// =========================================================================
// TUTORIAL VIDEOS (admin sets a YouTube link per page type)
// =========================================================================

/** Page types that can have a "How to create this page" tutorial. */
function tutorial_pages() {
    return [
        'normal'     => '💌 Normal Page (Category)',
        'birthday'   => '🎂 Premium — Birthday',
        'proposal'   => '💍 Premium — Proposal',
        'sorry'      => '💔 Premium — Sorry',
        'couple'     => '👑 Premium — Couple Story',
        'festival'   => '🪔 Festival Page',
        'invitation' => '🎬 Video Invitation',
    ];
}

/** Admin-set YouTube link for a page type (empty if none). */
function get_tutorial($key) {
    static $c = null;
    if ($c === null) { $s = json_decode(get_setting('tutorials', ''), true); $c = is_array($s) ? $s : []; }
    return $c[$key] ?? '';
}

/** Extract the 11-char YouTube id from any watch/short/embed URL or bare id. */
function youtube_id($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|v/|live/))([\w-]{11})#', $url, $m)) return $m[1];
    if (preg_match('/^[\w-]{11}$/', $url)) return $url;
    return '';
}

/**
 * Output a floating "How to create this page?" button + video modal for the
 * given tutorial key. No-op if the admin hasn't set a link. Include once
 * per editor page after setting nothing else up.
 */
function render_tutorial_button($key) {
    $vid = youtube_id(get_tutorial($key));
    if ($vid === '') return;
    $embed = 'https://www.youtube.com/embed/' . $vid . '?rel=0';
    ?>
<div id="tutFab" style="position:fixed;right:18px;bottom:18px;z-index:6000;">
  <button type="button" onclick="tutOpen()" style="display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,#f43f5e,#db2777);color:#fff;border:none;border-radius:50px;padding:12px 20px;font-family:Inter,sans-serif;font-weight:700;font-size:.85rem;box-shadow:0 12px 30px rgba(219,39,119,.4);cursor:pointer;">
    <span style="display:inline-flex;width:22px;height:22px;background:#fff;color:#db2777;border-radius:50%;align-items:center;justify-content:center;font-size:.7rem;">▶</span>
    How to create this page?
  </button>
</div>
<div id="tutModal" onclick="if(event.target===this)tutClose()" style="display:none;position:fixed;inset:0;z-index:6001;background:rgba(15,23,42,.8);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px;">
  <div style="width:100%;max-width:820px;background:#0b0b0f;border:1px solid rgba(244,63,94,.3);border-radius:20px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.6);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:linear-gradient(135deg,#1a0810,#2d0f1e);">
      <span style="color:#ffb3c1;font-family:Outfit,Inter,sans-serif;font-weight:800;font-size:.95rem;">🎥 How to create this page — step by step</span>
      <button type="button" onclick="tutClose()" style="background:rgba(255,255,255,.1);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1rem;">✕</button>
    </div>
    <div style="position:relative;width:100%;padding-top:56.25%;background:#000;">
      <iframe id="tutFrame" src="" title="Tutorial" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe>
    </div>
  </div>
</div>
<script>
var TUT_EMBED = <?= json_encode($embed) ?>;
function tutOpen(){ document.getElementById('tutFrame').src = TUT_EMBED + '&autoplay=1'; var m=document.getElementById('tutModal'); m.style.display='flex'; }
function tutClose(){ document.getElementById('tutFrame').src = ''; document.getElementById('tutModal').style.display='none'; }
document.addEventListener('keydown', function(e){ if(e.key==='Escape') tutClose(); });
</script>
<?php
}

// =========================================================================
// CREDITS & MONETIZATION
// =========================================================================

function get_user_credits($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? (int)$val : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function add_credits($user_id, $amount = 1) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
        return $stmt->execute([$amount, $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function use_credit($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT free_pages_used, credits FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if (!$user) return false;
        
        $free_limit = (int)get_setting('free_pages_per_user', DEFAULT_FREE_PAGES);
        if ((int)$user['free_pages_used'] < $free_limit) {
            $stmt_upd = $pdo->prepare("UPDATE users SET free_pages_used = free_pages_used + 1 WHERE id = ?");
            return $stmt_upd->execute([$user_id]);
        }
        
        $stmt_upd = $pdo->prepare("UPDATE users SET credits = credits - 1 WHERE id = ? AND credits > 0");
        return $stmt_upd->execute([$user_id]) && $stmt_upd->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function get_user_page_count($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// Credit costs: normal page = 1 credit, premium page = 5 credits. 1 credit = ₹10.
if (!defined('PREMIUM_CREDIT_COST')) define('PREMIUM_CREDIT_COST', 5);
if (!defined('NORMAL_CREDIT_COST')) define('NORMAL_CREDIT_COST', 1);

function can_create_page($user_id, $cost = 1) {
    if (is_unlimited_creator($user_id)) return true; // Managers & Super Admins: unlimited
    return (int)get_user_credits($user_id) >= (int)$cost;
}

function get_price_per_page() {
    return (int)get_setting('price_per_page', DEFAULT_PRICE_PAISE);
}

// =========================================================================
// CREDIT PLANS (admin-managed, stored as JSON in site_settings)
// =========================================================================

/**
 * Plans the user can buy on payment.php. Admin manages them in admin/plans.php.
 * Each plan: ['id','name','credits','price_paise','badge','active']
 */
function get_credit_plans($only_active = true) {
    $raw = json_decode(get_setting('credit_plans', ''), true);
    if (!is_array($raw) || empty($raw)) {
        // Sensible defaults: ₹10 = 1 token; bundles with small discounts
        $raw = [
            ['id' => 'p1',  'name' => 'Starter',   'credits' => 1,  'price_paise' => 1000, 'badge' => '',            'active' => true],
            ['id' => 'p5',  'name' => 'Lovebird',  'credits' => 5,  'price_paise' => 4500, 'badge' => 'Popular 💖',  'active' => true],
            ['id' => 'p10', 'name' => 'Soulmate',  'credits' => 10, 'price_paise' => 8000, 'badge' => 'Best Value ✨','active' => true],
        ];
    }
    $plans = [];
    foreach ($raw as $p) {
        if (empty($p['id']) || (int)($p['credits'] ?? 0) < 1 || (int)($p['price_paise'] ?? 0) < 100) continue;
        if ($only_active && empty($p['active'])) continue;
        $features = $p['features'] ?? [];
        if (is_string($features)) $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $features)), 'strlen'));
        $plans[] = [
            'id'          => (string)$p['id'],
            'name'        => (string)($p['name'] ?? 'Plan'),
            'credits'     => (int)$p['credits'],
            'price_paise' => (int)$p['price_paise'],
            'offer_paise' => max(0, (int)($p['offer_paise'] ?? 0)), // 0 = no offer price
            'badge'       => (string)($p['badge'] ?? ''),
            'features'    => is_array($features) ? array_slice($features, 0, 8) : [],
            'active'      => !empty($p['active']),
        ];
    }
    return $plans;
}

/** Global Flash Sale / Festival Offer — one click on/off from Admin → Credit Plans. */
function get_flash_sale() {
    $raw = json_decode(get_setting('flash_sale', ''), true);
    if (!is_array($raw)) $raw = [];
    $percent = max(0, min(90, (int)($raw['percent'] ?? 0)));
    return [
        'active'  => !empty($raw['active']) && $percent > 0,
        'name'    => (string)($raw['name'] ?? 'Flash Sale'),
        'percent' => $percent,
    ];
}

/** The price the user actually pays: offer price if set, then flash-sale % off. Min ₹1. */
function plan_effective_paise($plan) {
    $base = ($plan['offer_paise'] ?? 0) > 0 ? (int)$plan['offer_paise'] : (int)$plan['price_paise'];
    $sale = get_flash_sale();
    if ($sale['active']) {
        $base = (int)round($base * (100 - $sale['percent']) / 100);
    }
    return max(100, $base);
}

function get_credit_plan($plan_id) {
    foreach (get_credit_plans(true) as $p) {
        if ($p['id'] === (string)$plan_id) return $p;
    }
    return null;
}

// =========================================================================
// COUPONS (admin-managed, stored as JSON in site_settings)
// =========================================================================

function get_coupons() {
    $raw = json_decode(get_setting('coupons', ''), true);
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $c) {
        if (empty($c['code'])) continue;
        $out[] = [
            'code'        => strtoupper(trim($c['code'])),
            'type'        => ($c['type'] ?? 'percent') === 'flat' ? 'flat' : 'percent',
            'value'       => max(0, (float)($c['value'] ?? 0)),      // % or ₹
            'expiry'      => (string)($c['expiry'] ?? ''),            // Y-m-d or ''
            'usage_limit' => max(0, (int)($c['usage_limit'] ?? 0)),   // 0 = unlimited
            'used'        => max(0, (int)($c['used'] ?? 0)),
            'min_paise'   => max(0, (int)($c['min_paise'] ?? 0)),
            'max_disc_paise' => max(0, (int)($c['max_disc_paise'] ?? 0)), // 0 = no cap
            'active'      => !empty($c['active']),
        ];
    }
    return $out;
}

/** Returns the coupon array if valid & usable for $base_paise, else an error string. */
function validate_coupon($code, $base_paise) {
    $code = strtoupper(trim($code));
    if ($code === '') return 'Enter a coupon code.';
    foreach (get_coupons() as $c) {
        if ($c['code'] !== $code) continue;
        if (!$c['active']) return 'This coupon is not active.';
        if ($c['expiry'] !== '' && strtotime($c['expiry'] . ' 23:59:59') < time()) return 'This coupon has expired.';
        if ($c['usage_limit'] > 0 && $c['used'] >= $c['usage_limit']) return 'This coupon has reached its usage limit.';
        if ($c['min_paise'] > 0 && $base_paise < $c['min_paise']) return 'Minimum purchase for this coupon is ₹' . number_format($c['min_paise'] / 100) . '.';
        return $c;
    }
    return 'Invalid coupon code.';
}

function coupon_discount_paise($coupon, $base_paise) {
    $disc = $coupon['type'] === 'flat' ? (int)round($coupon['value'] * 100) : (int)round($base_paise * $coupon['value'] / 100);
    if ($coupon['max_disc_paise'] > 0) $disc = min($disc, $coupon['max_disc_paise']);
    return max(0, min($disc, $base_paise - 100)); // never below ₹1
}

function increment_coupon_usage($code) {
    $code = strtoupper(trim($code));
    $raw = json_decode(get_setting('coupons', ''), true);
    if (!is_array($raw)) return;
    foreach ($raw as &$c) {
        if (strtoupper(trim($c['code'] ?? '')) === $code) { $c['used'] = (int)($c['used'] ?? 0) + 1; break; }
    }
    set_setting('coupons', json_encode($raw, JSON_UNESCAPED_UNICODE));
}

// =========================================================================
// FESTIVAL PAGES
// =========================================================================

/**
 * Curated festival list with built-in 3-slide story experiences (f.php).
 * bg = page gradient, accent = highlight colour, particles = floating emojis,
 * slides = the 3 wish lines, wish = the personalised final greeting.
 */
function get_festivals() {
    return [
        ['slug' => 'independence', 'name' => 'Independence Day', 'emoji' => '🇮🇳', 'desc' => 'Celebrate freedom with patriotic wishes, tricolour themes and heartfelt messages.',
         'bg' => 'linear-gradient(180deg,#050d1a 0%,#0a1a2e 45%,#1a1005 100%)', 'accent' => '#ff9933', 'accent2' => '#138808',
         'particles' => ['🇮🇳','🧡','🤍','💚','✨'],
         'loading_text' => 'Preparing your patriotic wishes…',
         'slides' => [
             "🇮🇳 Happy Independence Day\nToday we celebrate the spirit of freedom, the courage of our heroes, and the pride of being Indian.\nJai Hind!",
             "A heartfelt salute to every freedom fighter, every soldier, and every brave soul who made our freedom possible.\nTheir sacrifices will always be remembered. 🇮🇳",
             "May our nation continue to grow with peace, unity, hope, and endless success.\nHappy Independence Day! 🇮🇳",
         ],
         'wish' => 'Happy Independence Day',
         'wish_body' => "Freedom is our pride,\nUnity is our strength,\nIndia is our identity.\n\nWishing you and your family\na joyful, peaceful,\nand prosperous Independence Day.",
         'sign_off' => 'Jai Hind! 🇮🇳',
         'gen_text' => 'Creating your patriotic page…'],
        ['slug' => 'diwali',      'name' => 'Diwali',          'emoji' => '🪔', 'desc' => 'Lights, diyas & warm wishes for the festival of lights.',
         'bg' => 'linear-gradient(180deg,#1a0b02 0%,#2d1505 55%,#180a1a 100%)', 'accent' => '#f4c56b', 'accent2' => '#e8405a',
         'particles' => ['🪔','✨','🎆','💛','🌟'],
         'slides' => ["May a thousand diyas light up your path… 🪔", "May every corner of your home glow with joy and prosperity. ✨", "This Diwali, may your heart shine the brightest of all. 🌟"],
         'wish' => 'Happy Diwali'],
        ['slug' => 'holi',        'name' => 'Holi',            'emoji' => '🎨', 'desc' => 'A splash of colours and playful festive wishes.',
         'bg' => 'linear-gradient(180deg,#1a0a2e 0%,#2d0f3a 55%,#0a1a2e 100%)', 'accent' => '#ec4899', 'accent2' => '#38bdf8',
         'particles' => ['🎨','💜','💙','💚','🧡','💖'],
         'slides' => ["Let every colour of Holi paint your life with happiness… 🎨", "Pink for love, yellow for joy, green for new beginnings. 💖💛💚", "May your year be as colourful as today. 🌈"],
         'wish' => 'Happy Holi'],
        ['slug' => 'rakhi',       'name' => 'Raksha Bandhan',  'emoji' => '🪢', 'desc' => 'For the sibling bond — sweet, funny and heartfelt.',
         'bg' => 'linear-gradient(180deg,#2d0f1e 0%,#3a1226 55%,#1a0810 100%)', 'accent' => '#f4c56b', 'accent2' => '#e8405a',
         'particles' => ['🪢','💝','✨','🎀'],
         'slides' => ["A thread so small, a bond so unbreakable… 🪢", "Through every fight and every laugh, you are my forever person.", "This Rakhi, my prayer is simple — your happiness, always. 💝"],
         'wish' => 'Happy Raksha Bandhan'],
        ['slug' => 'eid',         'name' => 'Eid',             'emoji' => '🌙', 'desc' => 'Elegant Eid Mubarak greetings with warmth and blessings.',
         'bg' => 'linear-gradient(180deg,#0a1a2e 0%,#12233a 55%,#0a0f1a 100%)', 'accent' => '#f4c56b', 'accent2' => '#34d399',
         'particles' => ['🌙','⭐','✨','🕌'],
         'slides' => ["As the crescent moon rises, so do a thousand blessings… 🌙", "May your prayers be answered and your home be filled with peace.", "Eid Mubarak — from my heart to yours. ⭐"],
         'wish' => 'Eid Mubarak'],
        ['slug' => 'christmas',   'name' => 'Christmas',       'emoji' => '🎄', 'desc' => 'Cozy, snowy and magical Christmas wishes.',
         'bg' => 'linear-gradient(180deg,#0a1f1a 0%,#0d2b22 55%,#1a0a10 100%)', 'accent' => '#f87171', 'accent2' => '#34d399',
         'particles' => ['🎄','❄️','🎁','⭐','☃️'],
         'slides' => ["Snowflakes, fairy lights and warm hearts… 🎄", "May your home smell of cookies and sound of laughter. 🎁", "Merry Christmas — may the magic stay all year. ❄️"],
         'wish' => 'Merry Christmas'],
        ['slug' => 'newyear',     'name' => 'New Year',        'emoji' => '🎆', 'desc' => 'Fireworks, countdowns and fresh beginnings.',
         'bg' => 'linear-gradient(180deg,#0a0a2e 0%,#1a1240 55%,#0a0518 100%)', 'accent' => '#f4c56b', 'accent2' => '#a78bfa',
         'particles' => ['🎆','🎇','✨','🥂','⭐'],
         'slides' => ["3… 2… 1… a brand new chapter begins! 🎆", "365 blank pages — may you fill them with your best story yet.", "Happy New Year — dream bigger, shine brighter. 🥂"],
         'wish' => 'Happy New Year'],
        ['slug' => 'valentine',   'name' => "Valentine's Day", 'emoji' => '💘', 'desc' => 'Express your love with a beautiful personalized page.',
         'bg' => 'linear-gradient(180deg,#2d0f1e 0%,#3a0d20 55%,#12030b 100%)', 'accent' => '#e8405a', 'accent2' => '#ff8fa3',
         'particles' => ['💘','❤️','🌹','💕','✨'],
         'slides' => ["Some feelings can't wait for the right moment… 💘", "Every love story is beautiful, but ours is my favourite. 🌹", "Happy Valentine's Day — you have my whole heart. ❤️"],
         'wish' => "Happy Valentine's Day"],
        ['slug' => 'karwachauth', 'name' => 'Karwa Chauth',    'emoji' => '🌕', 'desc' => 'Celebrate love, devotion and togetherness.',
         'bg' => 'linear-gradient(180deg,#1a0a2e 0%,#2d1505 55%,#12030b 100%)', 'accent' => '#f4c56b', 'accent2' => '#e8405a',
         'particles' => ['🌕','✨','💫','🪔'],
         'slides' => ["Waiting for the moon, thinking only of you… 🌕", "A fast of love, a prayer of a lifetime together.", "May our bond grow stronger with every moonrise. 💫"],
         'wish' => 'Happy Karwa Chauth'],
        ['slug' => 'navratri',    'name' => 'Navratri',        'emoji' => '🥁', 'desc' => 'Nine nights of devotion, dance and celebration.',
         'bg' => 'linear-gradient(180deg,#2d0f1e 0%,#3a1226 55%,#0a1a2e 100%)', 'accent' => '#ec4899', 'accent2' => '#f4c56b',
         'particles' => ['🥁','💃','✨','🌺','🪩'],
         'slides' => ["Nine nights of colour, rhythm and devotion… 🥁", "May Maa Durga fill your life with strength and joy. 🌺", "Let's dance the garba of gratitude tonight! 💃"],
         'wish' => 'Happy Navratri'],
    ];
}

function get_festival($slug) {
    foreach (get_festivals() as $f) {
        if ($f['slug'] === $slug) return $f;
    }
    return null;
}

/** Which festival is featured right now (calendar-based, admin list order is the fallback). */
function get_featured_festival_slug() {
    $m = (int)date('n');
    $d = (int)date('j');
    if ($m === 8 && $d <= 15) return 'independence';
    $map = [1 => 'newyear', 2 => 'valentine', 3 => 'holi', 8 => 'rakhi', 9 => 'navratri', 10 => 'diwali', 11 => 'diwali', 12 => 'christmas'];
    return $map[$m] ?? 'independence';
}

/** "Pages created today" style counter: real count + a stable seeded boost per day. */
function festival_counter($slug) {
    global $pdo;
    $real = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE category = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$slug]);
        $real = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}
    $seed = crc32($slug . date('Y-m-d'));
    $boost = 800 + ($seed % 14000); // stable per festival per day
    return $real + $boost;
}

/**
 * Per-festival status set by the admin. Stored as one JSON map in site_settings.
 * Statuses: enabled | coming_soon | maintenance | disabled (hidden)
 */
function get_festival_statuses() {
    $raw = json_decode(get_setting('festival_statuses', ''), true);
    return is_array($raw) ? $raw : [];
}

function get_festival_status($slug) {
    $all = get_festival_statuses();
    $st = $all[$slug] ?? 'enabled'; // experiences are built-in, so live by default
    return in_array($st, ['enabled', 'coming_soon', 'maintenance', 'disabled']) ? $st : 'enabled';
}

// =========================================================================
// REFERRAL SYSTEM
// =========================================================================

function generate_referral_code() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function process_referral($new_user_id, $referral_code) {
    global $pdo;
    if (empty($referral_code)) return false;
    
    try {
        // Find referrer by code
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND id != ?");
        $stmt->execute([$referral_code, $new_user_id]);
        $referrer = $stmt->fetch();
        
        if (!$referrer) return false;
        
        // Check if referral already exists
        $check = $pdo->prepare("SELECT id FROM referrals WHERE referred_user_id = ?");
        $check->execute([$new_user_id]);
        if ($check->fetch()) return false;
        
        // Create referral record
        $ins = $pdo->prepare("INSERT INTO referrals (user_id, referred_user_id, reward_given) VALUES (?, ?, 1)");
        $ins->execute([$referrer['id'], $new_user_id]);
        
        // Award credit to referrer
        add_credits($referrer['id'], 1);
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function get_referral_stats($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(reward_given) as credits_earned FROM referrals WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return ['total' => 0, 'credits_earned' => 0];
    }
}

// =========================================================================
// USER HELPERS
// =========================================================================

function get_user_profile($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

// =========================================================================
// REPLY HELPERS
// =========================================================================

function get_page_replies($page_id, $limit = 50) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM page_replies WHERE page_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$page_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function get_page_reply_count($page_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM page_replies WHERE page_id = ?");
        $stmt->execute([$page_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// =========================================================================
// THEMES CONFIGURATION
// =========================================================================
function get_themes() {
    return [
        'romantic' => [
            'name' => 'Romantic',
            'icon' => '🌹',
            'bg_class' => 'bg-gradient-to-b from-rose-50 via-pink-50 to-red-50',
            'dark_bg' => 'from-rose-950 via-rose-900 to-red-950',
            'text_primary' => 'text-rose-900',
            'text_secondary' => 'text-rose-600',
            'text_muted' => 'text-rose-400',
            'accent' => '#e11d48',
            'accent_light' => 'rgba(225,29,72,0.1)',
            'card_bg' => 'bg-white/50',
            'card_border' => 'border-rose-200/50',
            'btn_gradient' => 'from-rose-500 to-pink-500',
            'btn_shadow' => 'shadow-rose-500/25',
            'font' => 'Playfair Display',
            'particles' => ['❤️', '💕', '🌹', '✨'],
            'overlay_emoji' => '💌',
        ],
        'dark' => [
            'name' => 'Dark',
            'icon' => '🌑',
            'bg_class' => 'bg-gradient-to-b from-slate-950 via-gray-900 to-zinc-950',
            'dark_bg' => 'from-slate-950 via-gray-900 to-zinc-950',
            'text_primary' => 'text-white',
            'text_secondary' => 'text-slate-300',
            'text_muted' => 'text-slate-500',
            'accent' => '#a855f7',
            'accent_light' => 'rgba(168,85,247,0.15)',
            'card_bg' => 'bg-white/5',
            'card_border' => 'border-white/10',
            'btn_gradient' => 'from-purple-500 to-indigo-500',
            'btn_shadow' => 'shadow-purple-500/25',
            'font' => 'Outfit',
            'particles' => ['✨', '💫', '⭐', '🌟'],
            'overlay_emoji' => '🌙',
        ],
        'luxury' => [
            'name' => 'Luxury',
            'icon' => '👑',
            'bg_class' => 'bg-gradient-to-b from-amber-50 via-yellow-50 to-orange-50',
            'dark_bg' => 'from-stone-950 via-amber-950 to-yellow-950',
            'text_primary' => 'text-amber-900',
            'text_secondary' => 'text-amber-700',
            'text_muted' => 'text-amber-500',
            'accent' => '#d97706',
            'accent_light' => 'rgba(217,119,6,0.1)',
            'card_bg' => 'bg-white/60',
            'card_border' => 'border-amber-200/50',
            'btn_gradient' => 'from-amber-500 to-yellow-500',
            'btn_shadow' => 'shadow-amber-500/25',
            'font' => 'Playfair Display',
            'particles' => ['✨', '👑', '💎', '⭐'],
            'overlay_emoji' => '💎',
        ],
        'rose_garden' => [
            'name' => 'Rose Garden',
            'icon' => '🌷',
            'bg_class' => 'bg-gradient-to-b from-emerald-50 via-green-50 to-rose-50',
            'dark_bg' => 'from-emerald-950 via-green-950 to-rose-950',
            'text_primary' => 'text-emerald-900',
            'text_secondary' => 'text-emerald-700',
            'text_muted' => 'text-emerald-500',
            'accent' => '#059669',
            'accent_light' => 'rgba(5,150,105,0.1)',
            'card_bg' => 'bg-white/50',
            'card_border' => 'border-emerald-200/50',
            'btn_gradient' => 'from-emerald-500 to-teal-500',
            'btn_shadow' => 'shadow-emerald-500/25',
            'font' => 'Playfair Display',
            'particles' => ['🌹', '🌷', '🌸', '🍃'],
            'overlay_emoji' => '🌷',
        ],
        'minimal' => [
            'name' => 'Minimal',
            'icon' => '◻️',
            'bg_class' => 'bg-gradient-to-b from-gray-50 via-white to-gray-100',
            'dark_bg' => 'from-neutral-950 via-neutral-900 to-neutral-950',
            'text_primary' => 'text-gray-900',
            'text_secondary' => 'text-gray-600',
            'text_muted' => 'text-gray-400',
            'accent' => '#374151',
            'accent_light' => 'rgba(55,65,81,0.08)',
            'card_bg' => 'bg-white/70',
            'card_border' => 'border-gray-200',
            'btn_gradient' => 'from-gray-800 to-gray-900',
            'btn_shadow' => 'shadow-gray-500/15',
            'font' => 'Inter',
            'particles' => ['·', '•', '○', '◦'],
            'overlay_emoji' => '📄',
        ],
        'galaxy' => [
            'name' => 'Galaxy',
            'icon' => '🌌',
            'bg_class' => 'bg-gradient-to-b from-indigo-950 via-purple-950 to-violet-950',
            'dark_bg' => 'from-indigo-950 via-purple-950 to-violet-950',
            'text_primary' => 'text-white',
            'text_secondary' => 'text-violet-200',
            'text_muted' => 'text-violet-400',
            'accent' => '#7c3aed',
            'accent_light' => 'rgba(124,58,237,0.15)',
            'card_bg' => 'bg-white/5',
            'card_border' => 'border-violet-500/20',
            'btn_gradient' => 'from-violet-500 to-purple-500',
            'btn_shadow' => 'shadow-violet-500/25',
            'font' => 'Outfit',
            'particles' => ['⭐', '🌟', '💫', '✨'],
            'overlay_emoji' => '🚀',
        ],
        'cute_pink' => [
            'name' => 'Cute Pink',
            'icon' => '🎀',
            'bg_class' => 'bg-gradient-to-b from-pink-50 via-fuchsia-50 to-pink-100',
            'dark_bg' => 'from-pink-950 via-fuchsia-950 to-pink-950',
            'text_primary' => 'text-pink-900',
            'text_secondary' => 'text-pink-600',
            'text_muted' => 'text-pink-400',
            'accent' => '#ec4899',
            'accent_light' => 'rgba(236,72,153,0.1)',
            'card_bg' => 'bg-white/60',
            'card_border' => 'border-pink-200/50',
            'btn_gradient' => 'from-pink-400 to-fuchsia-400',
            'btn_shadow' => 'shadow-pink-400/25',
            'font' => 'Outfit',
            'particles' => ['💗', '🎀', '🫧', '💖'],
            'overlay_emoji' => '🎀',
        ],
        'elegant' => [
            'name' => 'Elegant',
            'icon' => '🖤',
            'bg_class' => 'bg-gradient-to-b from-slate-50 via-blue-50 to-indigo-50',
            'dark_bg' => 'from-slate-950 via-blue-950 to-indigo-950',
            'text_primary' => 'text-slate-800',
            'text_secondary' => 'text-slate-600',
            'text_muted' => 'text-slate-400',
            'accent' => '#4338ca',
            'accent_light' => 'rgba(67,56,202,0.08)',
            'card_bg' => 'bg-white/50',
            'card_border' => 'border-indigo-200/40',
            'btn_gradient' => 'from-indigo-600 to-blue-600',
            'btn_shadow' => 'shadow-indigo-500/20',
            'font' => 'Playfair Display',
            'particles' => ['✨', '◆', '❖', '✦'],
            'overlay_emoji' => '✉️',
        ],
        'birthday' => [
            'name' => 'Birthday',
            'icon' => '🎉',
            'bg_class' => 'bg-gradient-to-b from-violet-50 via-purple-50 to-orange-50',
            'dark_bg' => 'from-violet-950 via-purple-950 to-orange-950',
            'text_primary' => 'text-violet-900',
            'text_secondary' => 'text-violet-600',
            'text_muted' => 'text-purple-400',
            'accent' => '#7c3aed',
            'accent_light' => 'rgba(124,58,237,0.1)',
            'card_bg' => 'bg-white/60',
            'card_border' => 'border-violet-200/40',
            'btn_gradient' => 'from-violet-500 to-orange-500',
            'btn_shadow' => 'shadow-violet-500/20',
            'font' => 'Outfit',
            'particles' => ['🎈', '🎂', '🎁', '🎉'],
            'overlay_emoji' => '🎁',
        ],
        'friendship' => [
            'name' => 'Friendship',
            'icon' => '🌻',
            'bg_class' => 'bg-gradient-to-b from-yellow-50 via-orange-50 to-amber-50',
            'dark_bg' => 'from-yellow-950 via-orange-950 to-amber-950',
            'text_primary' => 'text-orange-900',
            'text_secondary' => 'text-orange-600',
            'text_muted' => 'text-yellow-500',
            'accent' => '#ea580c',
            'accent_light' => 'rgba(234,88,12,0.1)',
            'card_bg' => 'bg-white/60',
            'card_border' => 'border-orange-200/40',
            'btn_gradient' => 'from-yellow-500 to-orange-500',
            'btn_shadow' => 'shadow-orange-500/20',
            'font' => 'Outfit',
            'particles' => ['🌻', '⭐', '😊', '🤝'],
            'overlay_emoji' => '🤗',
        ],
        'anime' => [
            'name' => 'Anime',
            'icon' => '🌸',
            'bg_class' => 'bg-gradient-to-b from-pink-50 via-sky-50 to-cyan-50',
            'dark_bg' => 'from-pink-950 via-sky-950 to-cyan-950',
            'text_primary' => 'text-pink-900',
            'text_secondary' => 'text-sky-700',
            'text_muted' => 'text-pink-400',
            'accent' => '#ec4899',
            'accent_light' => 'rgba(236,72,153,0.1)',
            'card_bg' => 'bg-white/50',
            'card_border' => 'border-pink-200/30',
            'btn_gradient' => 'from-pink-400 to-cyan-400',
            'btn_shadow' => 'shadow-pink-400/20',
            'font' => 'Outfit',
            'particles' => ['🌸', '🩷', '✿', '❀'],
            'overlay_emoji' => '🌸',
        ],
        'modern' => [
            'name' => 'Modern',
            'icon' => '💠',
            'bg_class' => 'bg-gradient-to-b from-teal-50 via-cyan-50 to-indigo-50',
            'dark_bg' => 'from-teal-950 via-cyan-950 to-indigo-950',
            'text_primary' => 'text-teal-900',
            'text_secondary' => 'text-teal-600',
            'text_muted' => 'text-teal-400',
            'accent' => '#0d9488',
            'accent_light' => 'rgba(13,148,136,0.1)',
            'card_bg' => 'bg-white/40',
            'card_border' => 'border-teal-200/30',
            'btn_gradient' => 'from-teal-500 to-indigo-500',
            'btn_shadow' => 'shadow-teal-500/20',
            'font' => 'Outfit',
            'particles' => ['💠', '◈', '✦', '◇'],
            'overlay_emoji' => '💠',
        ],
    ];
}

function get_premium_slides() {
    return [
        ['type' => 'premium_memory_reveal', 'key' => 'premium_memory_reveal', 'title' => 'Memory Reveal', 'subtitle' => 'Our special memories unlocked', 'is_optional' => true],
        ['type' => 'premium_heart_formation', 'key' => 'premium_heart_formation', 'title' => 'Heart Formation', 'subtitle' => 'Thousands of particles coalesce', 'is_optional' => true],
        ['type' => 'premium_collage_explosion', 'key' => 'premium_collage_explosion', 'title' => 'Collage Explosion', 'subtitle' => 'Memories flying together', 'is_optional' => true],
        ['type' => 'premium_star_sky', 'key' => 'premium_star_sky', 'title' => 'Star Memory Sky', 'subtitle' => 'Memories shining in the stars', 'is_optional' => true],
        ['type' => 'premium_memory_book', 'key' => 'premium_memory_book', 'title' => 'Memory Book', 'subtitle' => 'Turn the pages of our story', 'is_optional' => true],
        ['type' => 'premium_reasons_special', 'key' => 'premium_reasons_special', 'title' => 'Why You Are Special', 'subtitle' => 'Reasons why you are so important', 'is_optional' => true],
        ['type' => 'premium_puzzle_reveal', 'key' => 'premium_puzzle_reveal', 'title' => 'Photo Puzzle Reveal', 'subtitle' => 'Solving our picture together', 'is_optional' => true],
        ['type' => 'premium_memory_timeline', 'key' => 'premium_memory_timeline', 'title' => 'Memory Timeline', 'subtitle' => 'Our milestone journey', 'is_optional' => true],
        ['type' => 'premium_mosaic_heart', 'key' => 'premium_mosaic_heart', 'title' => 'Photo Mosaic Heart', 'subtitle' => 'Every piece belongs to you', 'is_optional' => true],
        ['type' => 'premium_our_chats', 'key' => 'premium_our_chats', 'title' => 'Our Chats', 'subtitle' => 'Screenshots from our story', 'is_optional' => true]
    ];
}

function get_categories($include_inactive = false) {
    global $pdo;
    try {
        $sql = "SELECT * FROM categories";
        if (!$include_inactive) {
            $sql .= " WHERE status = 'enabled'";
        }
        $sql .= " ORDER BY featured DESC, display_order ASC";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();
        if ($rows) {
            $categories = [];
            foreach ($rows as $row) {
                $slides = [];
                if (!empty($row['slides'])) {
                    $slides = json_decode($row['slides'], true);
                    if (!is_array($slides)) {
                        $slides = [];
                    }
                }
                $categories[$row['slug']] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'icon' => $row['icon'],
                    'description' => $row['description'],
                    'status' => $row['status'] ?? 'enabled',
                    'featured' => (int)($row['featured'] ?? 0),
                    'display_order' => (int)($row['display_order'] ?? 0),
                    'default_theme' => $row['theme'],
                    'default_title' => $row['default_title'] ?? '',
                    'default_letter' => $row['default_letter'] ?? '',
                    'default_question' => $row['default_question'] ?? '',
                    'font' => $row['font'] ?? 'Outfit',
                    'accent_hex' => $row['accent_hex'] ?? '#ec4899',
                    'music_url' => $row['music_url'] ?? '',
                    'slides' => $slides,
                    'interactive_question' => $row['interactive_question'] ?? '',
                    'interactive_yes_text' => $row['interactive_yes_text'] ?? 'Yes! ❤️',
                    'interactive_no_text' => $row['interactive_no_text'] ?? 'No',
                    'notification_msg' => $row['notification_msg'] ?? '',
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ];
            }
            return $categories;
        }
    } catch (PDOException $e) {
        // Fallback if DB query fails during migration
    }
    return [];
}

// Get a specific category config
function get_category($key) {
    global $pdo;
    static $cat_cache = [];
    if (isset($cat_cache[$key])) {
        return $cat_cache[$key];
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row) {
            $slides = [];
            if (!empty($row['slides'])) {
                $slides = json_decode($row['slides'], true);
                if (!is_array($slides)) {
                    $slides = [];
                }
            }
            $cat_data = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'icon' => $row['icon'],
                'description' => $row['description'],
                'status' => $row['status'] ?? 'enabled',
                'featured' => (int)($row['featured'] ?? 0),
                'display_order' => (int)($row['display_order'] ?? 0),
                'default_theme' => $row['theme'],
                'default_title' => $row['default_title'] ?? '',
                'default_letter' => $row['default_letter'] ?? '',
                'default_question' => $row['default_question'] ?? '',
                'font' => $row['font'] ?? 'Outfit',
                'accent_hex' => $row['accent_hex'] ?? '#ec4899',
                'music_url' => $row['music_url'] ?? '',
                'slides' => $slides,
                'interactive_question' => $row['interactive_question'] ?? '',
                'interactive_yes_text' => $row['interactive_yes_text'] ?? 'Yes! ❤️',
                'interactive_no_text' => $row['interactive_no_text'] ?? 'No',
                'notification_msg' => $row['notification_msg'] ?? '',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
            $cat_cache[$key] = $cat_data;
            return $cat_data;
        }
    } catch (PDOException $e) {}
    
    // Final fallback
    $all = get_categories(true);
    return ($all[$key] ?? reset($all)) ?: [];
}

// Get theme config
function get_theme($key) {
    $themes = get_themes();
    return $themes[$key] ?? $themes['romantic'];
}

// Decode slide_data JSON safely
function decode_slide_data($json_string) {
    if (empty($json_string)) return [];
    $data = json_decode($json_string, true);
    return is_array($data) ? $data : [];
}

// Get a slide data value by key with a fallback
function get_slide_value($slide_data, $key, $default = '') {
    return isset($slide_data[$key]) && !empty(trim($slide_data[$key])) ? $slide_data[$key] : $default;
}

// Get cards array from slide_data
function get_slide_cards($slide_data, $key, $defaults = []) {
    if (isset($slide_data[$key]) && is_array($slide_data[$key]) && count($slide_data[$key]) > 0) {
        return $slide_data[$key];
    }
    return $defaults;
}

// Available reactions
function get_reactions() {
    return [
        'loved' => ['emoji' => '❤️', 'label' => 'Loved It'],
        'beautiful' => ['emoji' => '😍', 'label' => 'Beautiful'],
        'emotional' => ['emoji' => '🥹', 'label' => 'Emotional'],
        'crying' => ['emoji' => '😭', 'label' => 'Made Me Cry'],
        'amazing' => ['emoji' => '🔥', 'label' => 'Amazing'],
    ];
}

// Available destinations for Invite Out category
function get_destinations() {
    return [
        'cafe' => ['name' => 'Café ☕', 'emoji' => '☕', 'bg' => 'from-amber-100 to-orange-100'],
        'movie' => ['name' => 'Movie 🎬', 'emoji' => '🎬', 'bg' => 'from-violet-100 to-purple-100'],
        'dinner' => ['name' => 'Dinner 🍽️', 'emoji' => '🍽️', 'bg' => 'from-rose-100 to-red-100'],
        'long_drive' => ['name' => 'Long Drive 🚗', 'emoji' => '🚗', 'bg' => 'from-sky-100 to-blue-100'],
        'custom' => ['name' => 'Custom Plan ✨', 'emoji' => '✨', 'bg' => 'from-teal-100 to-emerald-100'],
    ];
}

// =========================================================================
// SECURITY & SESSION HELPERS
// =========================================================================

// CSRF Protection
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Session Security: Regenerate session ID to prevent session fixation
function regenerate_user_session() {
    session_regenerate_id(true);
}

// Simple Rate Limiting for auth endpoints
function is_rate_limited($key, $max_attempts = 5, $decay_seconds = 60) {
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    $now = time();
    if (!isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = [];
    }
    // Clean old attempts
    $_SESSION['rate_limits'][$key] = array_filter($_SESSION['rate_limits'][$key], function($timestamp) use ($now, $decay_seconds) {
        return ($now - $timestamp) < $decay_seconds;
    });
    
    if (count($_SESSION['rate_limits'][$key]) >= $max_attempts) {
        return true;
    }
    
    $_SESSION['rate_limits'][$key][] = $now;
    return false;
}

/**
 * Calculate total disk storage of a page (images, videos, voices, replies)
 */
function calculate_page_storage($page_id, $pdo) {
    $bytes = 0;
    
    // 1. Fetch main voice & video
    $stmt = $pdo->prepare("SELECT voice_url, letter_voice_url, video_url FROM pages WHERE id = ?");
    $stmt->execute([$page_id]);
    $p = $stmt->fetch();
    if ($p) {
        if (!empty($p['voice_url']) && file_exists($p['voice_url'])) $bytes += filesize($p['voice_url']);
        if (!empty($p['letter_voice_url']) && file_exists($p['letter_voice_url'])) $bytes += filesize($p['letter_voice_url']);
        if (!empty($p['video_url']) && file_exists($p['video_url'])) $bytes += filesize($p['video_url']);
    }
    
    // 2. Fetch page images (including thumb & medium)
    $stmt_img = $pdo->prepare("SELECT image_path, thumb_path, medium_path FROM page_images WHERE page_id = ?");
    $stmt_img->execute([$page_id]);
    foreach ($stmt_img->fetchAll() as $img) {
        if (!empty($img['image_path']) && file_exists($img['image_path'])) $bytes += filesize($img['image_path']);
        if (!empty($img['thumb_path']) && file_exists($img['thumb_path'])) $bytes += filesize($img['thumb_path']);
        if (!empty($img['medium_path']) && file_exists($img['medium_path'])) $bytes += filesize($img['medium_path']);
    }
    
    // 3. Fetch page videos
    $stmt_vid = $pdo->prepare("SELECT video_path FROM page_videos WHERE page_id = ?");
    $stmt_vid->execute([$page_id]);
    foreach ($stmt_vid->fetchAll() as $vid) {
        if (!empty($vid['video_path']) && file_exists($vid['video_path'])) $bytes += filesize($vid['video_path']);
    }
    
    // 4. Fetch reply files (voices, images, videos)
    $stmt_rep = $pdo->prepare("SELECT voice_path, image_path, video_path FROM page_replies WHERE page_id = ?");
    $stmt_rep->execute([$page_id]);
    foreach ($stmt_rep->fetchAll() as $rep) {
        if (!empty($rep['voice_path']) && file_exists($rep['voice_path'])) $bytes += filesize($rep['voice_path']);
        if (!empty($rep['image_path']) && file_exists($rep['image_path'])) $bytes += filesize($rep['image_path']);
        if (!empty($rep['video_path']) && file_exists($rep['video_path'])) $bytes += filesize($rep['video_path']);
    }
    
    return $bytes;
}

/**
 * Return optimized story version path if available, else original
 */
function img_story($img) {
    if (is_array($img)) {
        return !empty($img['medium_path']) ? $img['medium_path'] : $img['image_path'];
    }
    return $img;
}

/**
 * Render Emoji Rain HTML container and assets
 */
function render_emoji_rain() {
    ?>
    <!-- SoulPages Emoji Rain Container -->
    <div id="emoji-rain-container"></div>

    <!-- SoulPages Emoji Rain Styles -->
    <style>
        #emoji-rain-container {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 9999; /* Render on top of everything but allow clicks to pass through */
            overflow: hidden;
        }
        @keyframes emojiFloatUp {
            0%   { transform: translateY(105vh) translateX(var(--drift-start, 0px)) rotate(0deg) scale(var(--sc,1)); opacity: 0; }
            10%  { opacity: 0.7; }
            90%  { opacity: 0.7; }
            100% { transform: translateY(-15vh) translateX(var(--drift-end, 50px)) rotate(var(--rot,360deg)) scale(var(--sc,1)); opacity: 0; }
        }
        @keyframes emojiFloatDown {
            0%   { transform: translateY(-15vh) translateX(var(--drift-start, 0px)) rotate(0deg) scale(var(--sc,1)); opacity: 0; }
            10%  { opacity: 0.7; }
            90%  { opacity: 0.7; }
            100% { transform: translateY(105vh) translateX(var(--drift-end, 50px)) rotate(var(--rot,360deg)) scale(var(--sc,1)); opacity: 0; }
        }
        @keyframes emojiFloatRight {
            0%   { transform: translateX(-15vw) translateY(var(--drift-start, 0px)) rotate(0deg) scale(var(--sc,1)); opacity: 0; }
            10%  { opacity: 0.7; }
            90%  { opacity: 0.7; }
            100% { transform: translateX(105vw) translateY(var(--drift-end, 50px)) rotate(var(--rot,360deg)) scale(var(--sc,1)); opacity: 0; }
        }
        @keyframes emojiFloatLeft {
            0%   { transform: translateX(105vw) translateY(var(--drift-start, 0px)) rotate(0deg) scale(var(--sc,1)); opacity: 0; }
            10%  { opacity: 0.7; }
            90%  { opacity: 0.7; }
            100% { transform: translateX(-15vw) translateY(var(--drift-end, 50px)) rotate(var(--rot,360deg)) scale(var(--sc,1)); opacity: 0; }
        }
        .emoji-particle {
            position: absolute;
            font-size: var(--size, 18px);
            animation-duration: var(--dur, 8s);
            animation-timing-function: linear;
            animation-iteration-count: infinite;
            animation-delay: var(--delay, 0s);
            pointer-events: none;
            user-select: none;
            filter: blur(0.3px);
            will-change: transform, opacity;
        }
    </style>

    <!-- SoulPages Emoji Rain Script -->
    <script>
        (function() {
            function initEmojiRain() {
                const emojis = ['💖','🌸','✨','💕','🌷','💌','🥀','💗','🎀','🌺','💞','⭐','🦋','💫','🩷'];
                const container = document.getElementById('emoji-rain-container');
                if (!container) return;

                // Clear any existing children to prevent duplication
                container.innerHTML = '';

                const EMOJI_COUNT = window.innerWidth < 768 ? 36 : 72;
                const directions = ['up', 'down', 'left', 'right'];
                
                for (let i = 0; i < EMOJI_COUNT; i++) {
                    const el = document.createElement('div');
                    el.className = 'emoji-particle';
                    el.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                    
                    const dir = directions[Math.floor(Math.random() * directions.length)];
                    const size = Math.random() * 14 + 12; // 12-26px
                    const dur  = Math.random() * 12 + 8;   // 8-20s
                    const delay = Math.random() * -20;      // stagger starts
                    const rot  = Math.random() * 720 - 360;
                    const sc   = Math.random() * 0.6 + 0.7;
                    const driftStart = Math.random() * 80 - 40;
                    const driftEnd = Math.random() * 240 - 120;

                    let posStyle = '';
                    if (dir === 'up') {
                        posStyle = `
                            bottom: -60px;
                            left: ${Math.random() * 100}%;
                            animation-name: emojiFloatUp;
                        `;
                    } else if (dir === 'down') {
                        posStyle = `
                            top: -60px;
                            left: ${Math.random() * 100}%;
                            animation-name: emojiFloatDown;
                        `;
                    } else if (dir === 'right') {
                        posStyle = `
                            left: -60px;
                            top: ${Math.random() * 100}%;
                            animation-name: emojiFloatRight;
                        `;
                    } else if (dir === 'left') {
                        posStyle = `
                            right: -60px;
                            top: ${Math.random() * 100}%;
                            animation-name: emojiFloatLeft;
                        `;
                    }
                    
                    el.style.cssText = `
                        --size: ${size}px;
                        --dur: ${dur}s;
                        --delay: ${delay}s;
                        --rot: ${rot}deg;
                        --sc: ${sc};
                        --drift-start: ${driftStart}px;
                        --drift-end: ${driftEnd}px;
                        font-size: ${size}px;
                        animation-delay: ${delay}s;
                        opacity: 0;
                        ${posStyle}
                    `;
                    container.appendChild(el);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initEmojiRain);
            } else {
                initEmojiRain();
            }
        })();
    </script>
    <?php
}

// =========================================================================
// EMAIL VERIFICATION & SMTP FUNCTIONS
// =========================================================================

function send_smtp_email($to, $subject, $message_html, $from = SMTP_USER, $from_name = SITE_NAME) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $username = SMTP_USER;
    $password = SMTP_PASS;
    $secure = SMTP_SECURE; // 'ssl', 'tls', or ''

    $remote = $host;
    if (strtolower($secure) === 'ssl') {
        $remote = 'ssl://' . $host;
    }
    
    $socket = @fsockopen($remote, $port, $errno, $errstr, 15);
    if (!$socket) {
        return false;
    }

    fgets($socket, 512);
    
    fwrite($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    $response = fgets($socket, 512);
    while (substr($response, 3, 1) === '-') {
        $response = fgets($socket, 512);
    }

    if (strtolower($secure) === 'tls') {
        fwrite($socket, "STARTTLS\r\n");
        $response = fgets($socket, 512);
        if (strpos($response, '220') !== 0) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        fwrite($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $response = fgets($socket, 512);
        while (substr($response, 3, 1) === '-') {
            $response = fgets($socket, 512);
        }
    }

    if (!empty($username) && !empty($password)) {
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 512);
        if (strpos($response, '334') !== 0) {
            fclose($socket);
            return false;
        }

        fwrite($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 512);
        if (strpos($response, '334') !== 0) {
            fclose($socket);
            return false;
        }

        fwrite($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 512);
        if (strpos($response, '235') !== 0) {
            fclose($socket);
            return false;
        }
    }

    fwrite($socket, "MAIL FROM:<$from>\r\n");
    fgets($socket, 512);
    fwrite($socket, "RCPT TO:<$to>\r\n");
    fgets($socket, 512);

    fwrite($socket, "DATA\r\n");
    fgets($socket, 512);

    $headers = email_headers($from_name, $from, $to);
    $headers[] = "To: <$to>";
    $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";

    fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $message_html . "\r\n.\r\n");
    $response = fgets($socket, 512);
    
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return (strpos($response, '250') === 0);
}

/** Sending domain used for From / Message-ID (aligned with SMTP account to help SPF/DKIM). */
function email_domain() {
    if (defined('SMTP_USER') && strpos(SMTP_USER, '@') !== false) return substr(strrchr(SMTP_USER, '@'), 1);
    $h = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return preg_replace('/^www\./', '', $h);
}

/** Deliverability headers shared by SMTP + mail() paths (reduces spam scoring). */
function email_headers($from_name, $from, $to) {
    $domain = email_domain();
    $support = 'noreply@' . $domain;
    return [
        "MIME-Version: 1.0",
        "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from>",
        "Reply-To: <$support>",
        "Return-Path: <$from>",
        "Message-ID: <" . bin2hex(random_bytes(12)) . "@" . $domain . ">",
        "Date: " . date('r'),
        "List-Unsubscribe: <mailto:$support?subject=unsubscribe>",
        "X-Priority: 3",
        "X-Mailer: SoulPages",
        "Content-Type: text/html; charset=UTF-8",
    ];
}

function send_email($to, $subject, $body_html) {
    $log_dir = dirname(__DIR__) . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $verification_link = '';
    if (preg_match('/href=\'([^\'\"]+)\'|href=\"([^\'\"]+)\"/', $body_html, $matches)) {
        $verification_link = $matches[1] ?: $matches[2];
    }

    $log_msg = "[" . date('Y-m-d H:i:s') . "] To: $to | Subject: $subject\n";
    if (!empty($verification_link)) {
        $log_msg .= "Link: $verification_link\n";
    }
    $log_msg .= "---------------------------------------------------------\n";
    @file_put_contents($log_dir . '/sent_emails.log', $log_msg, FILE_APPEND);

    if (defined('SMTP_HOST') && !empty(SMTP_HOST) && defined('SMTP_USER') && !empty(SMTP_USER) && defined('SMTP_PASS') && SMTP_PASS !== 'YOUR_EMAIL_PASSWORD') {
        if (send_smtp_email($to, $subject, $body_html)) {
            return true;
        }
    }

    $from_email = defined('SMTP_USER') ? SMTP_USER : 'no-reply@' . email_domain();
    $headers = email_headers(SITE_NAME, $from_email, $to);
    return @mail($to, $subject, $body_html, implode("\r\n", $headers));
}

/** Ensure the users table has the verification_otp column (safe, idempotent). */
function ensure_otp_column($pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    try { $pdo->exec("ALTER TABLE users ADD COLUMN verification_otp VARCHAR(10) NULL"); } catch (\Throwable $e) { /* already exists */ }
}

/** Ensure the users table has the verify_requested_at column (manual-verify requests). */
function ensure_verify_request_column($pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    try { $pdo->exec("ALTER TABLE users ADD COLUMN verify_requested_at DATETIME NULL"); } catch (\Throwable $e) { /* already exists */ }
}

/** How many users are waiting for a manual admin verification. */
function count_verify_requests($pdo) {
    try { return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email_verified = 0 AND verify_requested_at IS NOT NULL")->fetchColumn(); }
    catch (\Throwable $e) { return 0; }
}

// =========================================================================
// WEB PUSH — notify the installed admin app (works even when it's closed)
// =========================================================================

function b64url_encode($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function b64url_decode($d) { return base64_decode(strtr($d, '-_', '+/') . str_repeat('=', (4 - strlen($d) % 4) % 4)); }

/** Generate the server's VAPID keypair once and store it (idempotent). */
function ensure_vapid_keys() {
    if (get_setting('vapid_public', '') !== '' && get_setting('vapid_private', '') !== '') return;
    if (!function_exists('openssl_pkey_new')) return;
    $res = @openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$res) return;
    @openssl_pkey_export($res, $priv);
    $det = openssl_pkey_get_details($res);
    // raw uncompressed public point (0x04 || X || Y) taken from the public PEM
    $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $det['key'] ?? ''));
    $point = substr($der, -65);
    if ($priv && strlen($point) === 65) {
        set_setting('vapid_private', $priv);
        set_setting('vapid_public', b64url_encode($point));
    }
}
function vapid_public_key() { ensure_vapid_keys(); return get_setting('vapid_public', ''); }

/** Convert an ECDSA DER signature to the raw 64-byte R||S form JWT needs. */
function ecdsa_der_to_raw($der) {
    $off = 0;
    if (($der[$off++] ?? '') !== "\x30") return false;
    $len = ord($der[$off++]);
    if ($len & 0x80) { $n = $len & 0x7f; $len = 0; while ($n-- > 0) $len = ($len << 8) | ord($der[$off++]); }
    if (($der[$off++] ?? '') !== "\x02") return false;
    $rlen = ord($der[$off++]); $r = substr($der, $off, $rlen); $off += $rlen;
    if (($der[$off++] ?? '') !== "\x02") return false;
    $slen = ord($der[$off++]); $s = substr($der, $off, $slen);
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return $r . $s;
}

/** Signed VAPID JWT for the given push-service origin (audience). */
function vapid_jwt($audience) {
    ensure_vapid_keys();
    $priv = get_setting('vapid_private', '');
    if ($priv === '') return '';
    $header  = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = b64url_encode(json_encode(['aud' => $audience, 'exp' => time() + 43200, 'sub' => 'mailto:noreply@' . email_domain()]));
    $input = $header . '.' . $payload;
    $der = '';
    if (!@openssl_sign($input, $der, $priv, OPENSSL_ALGO_SHA256)) return '';
    $raw = ecdsa_der_to_raw($der);
    if ($raw === false) return '';
    return $input . '.' . b64url_encode($raw);
}

/** Send a (payload-less) push "tickle" to one subscription. Returns HTTP code. */
function webpush_send($sub) {
    $endpoint = is_array($sub) ? ($sub['endpoint'] ?? '') : '';
    if ($endpoint === '' || !function_exists('curl_init')) return 0;
    $p = parse_url($endpoint);
    if (empty($p['scheme']) || empty($p['host'])) return 0;
    $jwt = vapid_jwt($p['scheme'] . '://' . $p['host']);
    if ($jwt === '') return 0;
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Authorization: vapid t=' . $jwt . ', k=' . vapid_public_key(), 'TTL: 3600', 'Content-Length: 0'],
        CURLOPT_POSTFIELDS => '',
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

// Stored subscriptions (keyed by endpoint hash) in site_settings.
function get_push_subs() { $s = json_decode(get_setting('push_subs', '{}'), true); return is_array($s) ? $s : []; }
function save_push_subs($subs) { set_setting('push_subs', json_encode($subs, JSON_UNESCAPED_SLASHES)); }
function add_push_sub($sub) {
    if (empty($sub['endpoint'])) return;
    $subs = get_push_subs();
    $subs[md5($sub['endpoint'])] = ['endpoint' => $sub['endpoint']];
    save_push_subs($subs);
}

/** Push to every registered admin device; prune dead subscriptions. */
function push_all($title = '', $body = '') {
    $subs = get_push_subs();
    $changed = false;
    foreach ($subs as $k => $sub) {
        $code = webpush_send($sub);
        if ($code === 404 || $code === 410) { unset($subs[$k]); $changed = true; }
    }
    if ($changed) save_push_subs($subs);
    return count($subs);
}

/** Notify the admin app + email that a user requested manual verification. */
function notify_admin_verify_request($user) {
    push_all();  // wakes the installed admin app → shows a notification
    $panel = rtrim(defined('SITE_URL') ? SITE_URL : '', '/') . '/admin/verify-requests.php';
    $name = $user['name'] ?? 'A user';
    $email = $user['email'] ?? '';
    $admin_email = get_setting('admin_notify_email', '') ?: ('noreply@' . email_domain());
    $body = "<div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;background:#fff;border-radius:16px;border:1px solid #f1e3ea;color:#1e293b;'>"
        . "<h2 style='color:#db2777;margin:0 0 12px;'>🙋 New verification request</h2>"
        . "<p style='font-size:14px;'><b>Name:</b> " . htmlspecialchars($name) . "<br><b>Email:</b> " . htmlspecialchars($email) . "</p>"
        . "<div style='text-align:center;margin:22px 0;'><a href='" . $panel . "' style='background:linear-gradient(135deg,#db2777,#e11d48);color:#fff;text-decoration:none;font-weight:bold;padding:12px 30px;border-radius:12px;display:inline-block;'>Open Verify Requests</a></div>"
        . "<p style='font-size:11px;color:#94a3b8;text-align:center;'>" . SITE_NAME . " admin alert</p></div>";
    @send_email($admin_email, "🙋 Verification request from " . $name, $body);
    return true;
}

/** Random 6-digit verification code. */
function make_otp() { return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); }

function send_verification_email($user_email, $token, $user_name = '', $otp = '') {
    $verification_link = SITE_URL . "/verify.php?token=" . $token;
    $name = !empty($user_name) ? htmlspecialchars($user_name) : 'there';

    // Code in the subject → visible in the inbox/spam list without even opening.
    $subject = $otp !== '' ? ($otp . " is your " . SITE_NAME . " verification code") : ("Verify Your Email - " . SITE_NAME);

    $otp_block = '';
    if ($otp !== '') {
        $otp_block = "
        <p style='color:#475569; font-size:14px; line-height:1.6; text-align:center; margin:26px 0 8px;'>Or enter this 6-digit code on the verification page:</p>
        <div style='text-align:center; margin:0 0 8px;'>
            <span style='display:inline-block; font-family:monospace; font-size:34px; font-weight:800; letter-spacing:10px; color:#0f172a; background:#fdf2f8; border:2px dashed #f9a8d4; border-radius:14px; padding:14px 22px;'>" . htmlspecialchars($otp) . "</span>
        </div>";
    }

    $body_html = "
    <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; padding: 28px 24px; background:#ffffff; border-radius: 18px; border: 1px solid #f1e3ea; color:#1e293b;'>
        <h2 style='color:#db2777; text-align:center; font-size:22px; margin:0 0 18px;'>Welcome to " . SITE_NAME . "!</h2>
        <p style='color:#334155; font-size:14px; line-height:1.6; margin:0 0 10px;'>Hello " . $name . ",</p>
        <p style='color:#334155; font-size:14px; line-height:1.6; margin:0 0 8px;'>Thanks for signing up! Confirm your email to activate your account. Just tap the button below:</p>
        <div style='text-align:center; margin:24px 0;'>
            <a href='" . $verification_link . "' style='background:linear-gradient(135deg,#db2777,#e11d48); color:#ffffff; text-decoration:none; font-weight:bold; padding:13px 34px; border-radius:12px; display:inline-block;'>Verify My Email</a>
        </div>
        " . $otp_block . "
        <p style='color:#64748b; font-size:12px; line-height:1.6; text-align:center; margin:18px 0 0;'>If the button doesn't work, copy this link into your browser:<br>
        <a href='" . $verification_link . "' style='color:#db2777;'>" . $verification_link . "</a></p>
        <hr style='border:0; border-top:1px solid #f1e3ea; margin:24px 0;'>
        <p style='color:#94a3b8; font-size:11px; text-align:center; margin:0;'>Didn't sign up? You can safely ignore this email. &middot; Made with 💖 by " . SITE_NAME . "</p>
    </div>
    ";

    return send_email($user_email, $subject, $body_html);
}
