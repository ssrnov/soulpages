<?php
require_once 'includes/functions.php';

// Calculate absolute base URL to prevent clean URL path resolution bugs
$base_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_dir = rtrim($base_dir, '/') . '/';
$base_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $base_dir;

if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user = get_user_profile($user_id);

// Self-healing: auto-generate referral code if missing
if ($user && empty($user['referral_code'])) {
    $referral_code = generate_referral_code();
    $is_unique = false;
    while (!$is_unique) {
        $check = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $check->execute([$referral_code]);
        if (!$check->fetch()) {
            $is_unique = true;
        } else {
            $referral_code = generate_referral_code();
        }
    }
    $upd = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    $upd->execute([$referral_code, $user_id]);
    $user['referral_code'] = $referral_code;
}

// Check if user has tracking viewer permission
$hasTrackView = false;
try {
    $tvDb = new PDO("mysql:host=localhost;dbname=looprsi1_nothing;charset=utf8mb4", 'looprsi1_ssrnov', 'Jayshreeram@12345', [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
    $tvCheck = $tvDb->prepare("SELECT 1 FROM tracking_viewers WHERE viewer_id = (SELECT id FROM users WHERE email = ? LIMIT 1) LIMIT 1");
    $tvCheck->execute([$user['email'] ?? '']);
    $hasTrackView = (bool)$tvCheck->fetch();
} catch (\Throwable $e) {}

$credits = get_user_credits($user_id);
$page_count = get_user_page_count($user_id);
$free_limit = (int)get_setting('free_pages_per_user', DEFAULT_FREE_PAGES);
$price_rupees = get_price_per_page() / 100;

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token. Please try again.';
    } else {
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if (empty($name) || empty($email)) {
                $error_msg = 'Name and Email are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_msg = 'Please enter a valid email address.';
            } else {
                // Check email unique
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $error_msg = 'This email is already registered to another account.';
                } else {
                    // Check username unique
                    $username_taken = false;
                    if (!empty($username)) {
                        $stmt_u = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                        $stmt_u->execute([$username, $user_id]);
                        if ($stmt_u->fetch()) {
                            $username_taken = true;
                            $error_msg = 'Username is already taken.';
                        }
                    }
                    
                    if (!$username_taken) {
                        $stmt_upd = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ? WHERE id = ?");
                        if ($stmt_upd->execute([$name, !empty($username) ? $username : null, $email, $user_id])) {
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_email'] = $email;
                            $success_msg = 'Profile details updated successfully!';
                            $user = get_user_profile($user_id);
                        } else {
                            $error_msg = 'Failed to update profile details.';
                        }
                    }
                }
            }
        } elseif ($action === 'change_password') {
            $current_pwd = $_POST['current_password'] ?? '';
            $new_pwd = $_POST['new_password'] ?? '';
            $confirm_pwd = $_POST['confirm_password'] ?? '';
            
            if (empty($current_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
                $error_msg = 'All password fields are required.';
            } elseif ($new_pwd !== $confirm_pwd) {
                $error_msg = 'New passwords do not match.';
            } elseif (strlen($new_pwd) < 8) {
                $error_msg = 'New password must be at least 8 characters.';
            } else {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $db_pwd = $stmt->fetchColumn();
                
                if ($db_pwd && password_verify($current_pwd, $db_pwd)) {
                    $hashed = password_hash($new_pwd, PASSWORD_BCRYPT);
                    $stmt_upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt_upd->execute([$hashed, $user_id])) {
                        $success_msg = 'Password changed successfully!';
                    } else {
                        $error_msg = 'Failed to update password.';
                    }
                } else {
                    $error_msg = 'Incorrect current password.';
                }
            }
        } elseif ($action === 'upload_photo') {
            if (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $upload_res = upload_image($_FILES['profile_pic'], 'uploads/profiles/');
                if ($upload_res['success']) {
                    $photo_path = $upload_res['path'];
                    $stmt_upd = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    if ($stmt_upd->execute([$photo_path, $user_id])) {
                        $_SESSION['user_photo'] = $photo_path;
                        $success_msg = 'Profile picture updated successfully!';
                        $user = get_user_profile($user_id);
                    } else {
                        $error_msg = 'Database update failed.';
                    }
                } else {
                    $error_msg = $upload_res['message'];
                }
            } else {
                $error_msg = 'No file selected or upload error occurred.';
            }
        } elseif ($action === 'delete_account') {
            $password = $_POST['password'] ?? '';
            $confirm = isset($_POST['confirm_delete']) ? 1 : 0;
            
            if (empty($password)) {
                $error_msg = 'Please enter your password to confirm deletion.';
            } elseif (!$confirm) {
                $error_msg = 'You must check the confirmation checkbox.';
            } else {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $db_pwd = $stmt->fetchColumn();
                
                if ($db_pwd && password_verify($password, $db_pwd)) {
                    $pdo->beginTransaction();
                    try {
                        // Delete files associated with user pages
                        $pages_stmt = $pdo->prepare("SELECT id, video_url, voice_url, letter_voice_url FROM pages WHERE user_id = ?");
                        $pages_stmt->execute([$user_id]);
                        $user_pages = $pages_stmt->fetchAll();
                        
                        foreach ($user_pages as $p) {
                            if (!empty($p['video_url']) && file_exists($p['video_url'])) @unlink($p['video_url']);
                            if (!empty($p['voice_url']) && file_exists($p['voice_url'])) @unlink($p['voice_url']);
                            if (!empty($p['letter_voice_url']) && file_exists($p['letter_voice_url'])) @unlink($p['letter_voice_url']);
                            
                            $imgs = $pdo->prepare("SELECT image_path FROM page_images WHERE page_id = ?");
                            $imgs->execute([$p['id']]);
                            foreach ($imgs->fetchAll() as $img) {
                                if (file_exists($img['image_path'])) @unlink($img['image_path']);
                            }
                        }
                        
                        // Delete user (cascades database tables)
                        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                        $pdo->commit();
                        
                        // Clear cookie & session
                        session_destroy();
                        setcookie('loopr_remember', '', time() - 3600, '/');
                        redirect('index.php');
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_msg = 'Failed to delete account: ' . $e->getMessage();
                    }
                } else {
                    $error_msg = 'Incorrect password. Account deletion aborted.';
                }
            }
        }
    }
}

// Stats
$total_views = $pdo->prepare("SELECT COUNT(*) FROM page_views pv JOIN pages p ON pv.page_id = p.id WHERE p.user_id = ?");
$total_views->execute([$user_id]);
$total_views = (int)$total_views->fetchColumn();

$total_reactions = $pdo->prepare("SELECT COUNT(*) FROM reactions r JOIN pages p ON r.page_id = p.id WHERE p.user_id = ?");
$total_reactions->execute([$user_id]);
$total_reactions = (int)$total_reactions->fetchColumn();

$total_replies = $pdo->prepare("SELECT COUNT(*) FROM page_replies pr JOIN pages p ON pr.page_id = p.id WHERE p.user_id = ?");
$total_replies->execute([$user_id]);
$total_replies = (int)$total_replies->fetchColumn();

// Fetch pages with stats
$stmt = $pdo->prepare("SELECT p.*, 
    (SELECT COUNT(*) FROM page_views WHERE page_id = p.id) as view_count,
    (SELECT COUNT(*) FROM reactions WHERE page_id = p.id) as reaction_count,
    (SELECT COUNT(*) FROM page_replies WHERE page_id = p.id) as reply_count,
    (SELECT COUNT(DISTINCT ip) FROM page_views WHERE page_id = p.id) as unique_visitors,
    (SELECT IFNULL(AVG(watch_time_seconds), 0) FROM page_views WHERE page_id = p.id) as avg_watch_time,
    (SELECT IFNULL(SUM(completed) * 100.0 / NULLIF(COUNT(*), 0), 0) FROM page_views WHERE page_id = p.id) as completion_rate
    FROM pages p WHERE p.user_id = ? ORDER BY p.created_at DESC");
$stmt->execute([$user_id]);
$pages = $stmt->fetchAll();

// Payment history
$payment_history = [];
try {
    $payments = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? AND status = 'captured' ORDER BY created_at DESC LIMIT 10");
    $payments->execute([$user_id]);
    $payment_history = $payments->fetchAll();
} catch (PDOException $e) {
    // payments table doesn't exist yet or other DB error
}

// Referral stats
$ref_stats = get_referral_stats($user_id);
$categories = get_categories(true);

// Fetch unread count for badge
if (is_admin()) {
    $unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM page_replies WHERE is_read = 0");
    $unread_stmt->execute();
} else {
    $unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM page_replies pr JOIN pages p ON pr.page_id = p.id WHERE p.user_id = ? AND pr.is_read = 0");
    $unread_stmt->execute([$user_id]);
}
$initial_unread_count = (int)$unread_stmt->fetchColumn();

$payment_msg = $_GET['payment'] ?? '';
$csrf_token = generate_csrf_token();

function format_watch_time($seconds) {
    $seconds = round($seconds);
    if ($seconds <= 0) return '0s';
    if ($seconds < 60) {
        return $seconds . 's';
    }
    $minutes = floor($seconds / 60);
    $remaining_seconds = $seconds % 60;
    if ($remaining_seconds === 0) {
        return $minutes . 'm';
    }
    return $minutes . 'm ' . $remaining_seconds . 's';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - SoulSync</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], heading: ['Outfit', 'sans-serif'] } } }
        }
    </script>
    <style>
        /* ═══ SOULPAGES LIGHT PINK GLOBAL THEME ═══ */
        :root {
            --bg-darkest:    #ffffff;
            --bg-dark:       #fcfcfc;
            --bg-mid:        #f8fafc;
            --bg-card:       rgba(255, 20, 120, 0.01);
            --border-glow:   rgba(236, 72, 153, 0.1);
            --pink-hot:      #db2777;
            --pink-rose:     #e11d48;
            --pink-soft:     #f43f5e;
            --pink-pale:     #fdf2f8;
            --white-pure:    #0f172a;
            --white-soft:    #f8fafc;
            --text-primary:  #0f172a;
            --text-secondary:#475569;
            --text-muted:    #64748b;
        }

        /* Force light background */
        body {
            background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important;
            background-attachment: fixed;
            color: var(--text-primary) !important;
            min-height: 100vh;
        }

        /* Global Typography Contrast Fix */
        body, p, label, li, span:not(.sp-gradient-text):not(.text-transparent) {
            color: #334155 !important; /* slate-700 */
        }
        h1, h2, h3, h4, h5, h6 {
            color: #0f172a !important; /* slate-900 */
        }

        /* Buttons & Badges (White Text) */
        .sp-btn-primary, .sp-btn-primary *, button[type="submit"], button[type="submit"] *, .sp-btn-primary span {
            color: #ffffff !important;
        }

        /* Gradient & Transparent text preservation */
        .sp-gradient-text, .bg-clip-text, .text-transparent {
            color: transparent !important;
            -webkit-text-fill-color: transparent !important;
        }

        /* Interactive & Alert Text Color Overrides */
        .text-pink-500, .text-pink-650, .text-pink-600, .text-pink-400, .text-pink-300, .hover\:text-pink-500:hover, .hover\:text-pink-400:hover {
            color: #db2777 !important;
        }
        .text-purple-400, .text-purple-500, .hover\:text-purple-400:hover {
            color: #7c3aed !important;
        }
        .text-emerald-400, .text-green-400, .text-emerald-500 {
            color: #059669 !important;
        }
        .text-red-400, .text-red-500 {
            color: #dc2626 !important;
        }

        /* Glass card light */
        .sp-glass, .glass-card {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(236, 72, 153, 0.12) !important;
            border-radius: 24px !important;
            transition: all 0.3s ease;
        }
        .sp-glass:hover {
            border-color: rgba(236, 72, 153, 0.3) !important;
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.06), 0 20px 40px rgba(0,0,0,0.02) !important;
        }

        /* Active tab styling override */
        .tab-btn {
            color: #64748b !important;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .tab-btn:hover {
            color: #0f172a !important;
        }
        .tab-btn.active {
            border-color: #db2777 !important;
            color: #db2777 !important;
            font-weight: 700;
        }

        /* Primary button */
        .sp-btn-primary {
            background: linear-gradient(135deg, #db2777 0%, #e11d48 100%) !important;
            color: white !important;
            font-weight: 700;
            border-radius: 16px;
            padding: 14px 32px;
            border: none;
            box-shadow: 0 8px 25px rgba(219,39,119,0.2) !important;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .sp-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(219,39,119,0.3) !important;
            opacity: 0.95;
        }

        /* Pink gradient text */
        .sp-gradient-text {
            background: linear-gradient(135deg, #be185d 0%, #db2777 40%, #e11d48 70%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Form elements & details lists dark-to-light card blocks */
        .bg-slate-900\/40, .bg-slate-950\/40, .bg-slate-950\/60, .bg-slate-900\/60, .bg-slate-900, .bg-slate-950, .bg-slate-850 {
            background-color: #ffffff !important;
        }
        .border-slate-800, .border-slate-850, .border-slate-900 {
            border-color: #cbd5e1 !important;
        }
        input[type="text"], input[type="number"], textarea, select {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #db2777 !important;
            box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15) !important;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%); color: #0f172a; min-height: 100vh;" class="font-sans flex flex-col antialiased">
<?= function_exists("ss_web_ad") ? ss_web_ad("dashboard") : "" ?>

    <!-- Emoji Rain -->
    <?php render_emoji_rain(); ?>

    <!-- Header -->
    <header style="background: rgba(255,255,255,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(236,72,153,0.12);" class="h-16 flex items-center sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full flex items-center justify-between">
            <a href="index.php" class="text-2xl font-extrabold font-heading sp-gradient-text tracking-wide">SoulSync</a>
            <div class="flex items-center gap-4">
                <?php if (is_admin()): ?>
                    <a href="admin/index.php" class="inline-flex items-center bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/20 text-purple-400 font-bold text-xs px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl transition">
                        Admin Portal 🛠️
                    </a>
                <?php endif; ?>
                <a href="create.php" class="hidden sm:inline-flex items-center gap-2 bg-gradient-to-r from-pink-500 to-purple-500 hover:from-pink-400 hover:to-purple-400 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-lg shadow-pink-500/20 transition-all hover:-translate-y-0.5">
                    <span>+</span> Create Page
                </a>
                <!-- Notification Bell -->
                <div class="relative">
                    <button id="notification-bell" onclick="toggleNotificationDropdown()" class="relative p-2 text-slate-400 hover:text-white rounded-full bg-white/5 border border-white/10 hover:bg-white/20 transition flex items-center justify-center">
                        <span class="text-base select-none">🔔</span>
                        <span id="notification-badge" class="absolute top-0.5 right-0.5 w-2 h-2 bg-rose-500 rounded-full border border-slate-950 hidden"></span>
                    </button>
                    <!-- Dropdown Panel -->
                    <div id="notification-dropdown" class="absolute right-0 mt-3 w-80 max-h-96 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl z-50 py-3 hidden">
                        <div class="px-4 pb-2 border-b border-slate-800 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-300">Notifications</span>
                            <button onclick="markAllNotificationsRead()" class="text-[10px] text-pink-400 hover:underline">Mark all as read</button>
                        </div>
                        <div id="notification-list" class="divide-y divide-slate-800/40 max-h-80 overflow-y-auto">
                            <p class="text-xs text-slate-500 py-6 text-center">Loading notifications...</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="<?= h($user['profile_photo']) ?>" class="w-8 h-8 rounded-full border-2 border-pink-500/30" alt="">
                    <?php else: ?>
                        <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-xs">👤</div>
                    <?php endif; ?>
                    <span class="text-sm font-semibold text-pink-200 hidden sm:inline"><?= h($user['name']) ?></span>
                </div>
                <a href="logout.php" class="text-xs text-pink-300 hover:text-white transition">Logout</a>
            </div>
        </div>
    </header>

    <!-- Sub Navigation Tabs -->
    <div class="border-b border-slate-900 bg-slate-950/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex space-x-6">
            <button onclick="switchTab('dashboard')" id="tab-btn-dashboard" class="tab-btn active py-4 text-sm font-medium transition whitespace-nowrap">Dashboard</button>
            <button onclick="switchTab('replies')" id="tab-btn-replies" class="tab-btn py-4 text-sm font-medium transition whitespace-nowrap flex items-center gap-1.5">
                Replies Inbox
                <span id="unread-badge" class="<?= $initial_unread_count > 0 ? '' : 'hidden' ?> bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $initial_unread_count ?></span>
            </button>
            <button onclick="switchTab('settings')" id="tab-btn-settings" class="tab-btn py-4 text-sm font-medium transition whitespace-nowrap">Profile Settings</button>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <?php if ($payment_msg === 'success'): ?>
            <div class="bg-green-500/10 border border-green-500/20 rounded-2xl p-4 mb-6 text-center">
                <p class="text-green-400 font-semibold">🎉 Payment successful! You've earned +1 page credit. Start creating!</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-green-500/15 border border-green-500/30 text-green-400 p-4 rounded-2xl mb-6 text-sm">
                ✅ <?= h($success_msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="bg-red-500/15 border border-red-500/30 text-red-400 p-4 rounded-2xl mb-6 text-sm">
                ⚠️ <?= h($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- TAB 1: DASHBOARD VIEW -->
        <div id="tab-content-dashboard">
            <!-- Welcome -->
            <div class="mb-8 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold font-heading text-white">Welcome back, <?= h($user['name']) ?> 👋</h1>
                    <p class="text-slate-400 text-sm mt-1">Here's your page analytics and account overview.</p>
                </div>
                <div class="sm:hidden">
                    <a href="create.php" class="flex items-center justify-center gap-2 w-full bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold text-sm py-3.5 rounded-2xl shadow-lg shadow-pink-500/20">
                        <span>+</span> Create New Page
                    </a>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="glass-card stat-glow-pink rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-slate-500 uppercase tracking-wider font-semibold">Total Views</span>
                        <span class="text-lg">👁️</span>
                    </div>
                    <p class="text-3xl font-extrabold font-heading text-pink-400"><?= number_format($total_views) ?></p>
                </div>
                <div class="glass-card stat-glow-purple rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-slate-500 uppercase tracking-wider font-semibold">Reactions</span>
                        <span class="text-lg">❤️</span>
                    </div>
                    <p class="text-3xl font-extrabold font-heading text-purple-400"><?= number_format($total_reactions) ?></p>
                </div>
                <div class="glass-card stat-glow-blue rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-slate-500 uppercase tracking-wider font-semibold">Replies</span>
                        <span class="text-lg">💬</span>
                    </div>
                    <p class="text-3xl font-extrabold font-heading text-blue-400"><?= number_format($total_replies) ?></p>
                </div>
                <div class="glass-card stat-glow-green rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-slate-500 uppercase tracking-wider font-semibold">Credits</span>
                        <span class="text-lg">✨</span>
                    </div>
                    <p class="text-3xl font-extrabold font-heading text-green-400"><?= $credits ?></p>
                    <p class="text-xs text-slate-600 mt-1"><?= (int)$user['free_pages_used'] ?>/<?= $free_limit ?> free used</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 sm:grid-cols-<?= $hasTrackView ? '3' : '2' ?> gap-4 mb-8">
                <?php if ($hasTrackView): ?>
                <a href="app/viewer/" class="glass-card rounded-2xl p-5 flex items-center gap-4 hover:border-pink-500/30 transition-all group" style="border:1px solid rgba(168,85,247,.2)">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500/20 to-pink-500/20 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">📡</div>
                    <div>
                        <div class="font-bold text-white text-sm">Track View</div>
                        <div class="text-xs text-slate-500">View tracking data</div>
                    </div>
                </a>
                <?php endif; ?>
                <?php
                $appDownloadUrl = 'https://soulsyncc.site/app/SoulSync.apk';
                try {
                    $dlDb = new PDO("mysql:host=localhost;dbname=looprsi1_nothing;charset=utf8mb4", 'looprsi1_ssrnov', 'Jayshreeram@12345', [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
                    $dlRow = $dlDb->query("SELECT setting_value FROM site_settings WHERE setting_key='app_update'")->fetch();
                    if ($dlRow) { $dlData = json_decode($dlRow['setting_value'], true); $appDownloadUrl = $dlData['download_url'] ?? $dlData['web_download_link'] ?? $appDownloadUrl; }
                } catch (\Throwable $e) {}
                ?>
                <a href="<?= h($appDownloadUrl) ?>" target="_blank" class="glass-card rounded-2xl p-5 flex items-center gap-4 hover:border-green-500/30 transition-all group" style="border:1px solid rgba(34,197,94,.15)">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">📲</div>
                    <div>
                        <div class="font-bold text-white text-sm">Download App</div>
                        <div class="text-xs text-slate-500">Get SoulSync for Android</div>
                    </div>
                </a>
                <a href="support.php" class="glass-card rounded-2xl p-5 flex items-center gap-4 hover:border-blue-500/30 transition-all group" style="border:1px solid rgba(59,130,246,.15)">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">💬</div>
                    <div>
                        <div class="font-bold text-white text-sm">Help & Support</div>
                        <div class="text-xs text-slate-500">Send us a message</div>
                    </div>
                </a>
            </div>

            <!-- Pages Section -->
            <div class="mb-8">
                <h2 class="text-xl font-extrabold font-heading text-white mb-4">Your Pages</h2>
                
                <?php if (empty($pages)): ?>
                    <div class="glass-card rounded-3xl p-12 text-center">
                        <div class="text-5xl mb-4">💌</div>
                        <h3 class="text-lg font-bold text-white mb-2">No pages yet</h3>
                        <p class="text-slate-400 text-sm mb-6">Create your first emotional page for someone special!</p>
                        <a href="create.php" class="inline-flex bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold text-sm px-6 py-3 rounded-xl shadow-lg shadow-pink-500/20 hover:-translate-y-0.5 transition-all">Create Your First Page →</a>
                    </div>
                <?php else: ?>
                    <div class="grid md:grid-cols-2 gap-4">
                        <?php foreach ($pages as $p): ?>
                            <?php 
                            $cat = $categories[$p['category']] ?? ['icon' => '📄', 'name' => 'Page', 'status' => 'enabled']; 
                            if (($cat['status'] ?? 'enabled') === 'hidden' && !is_admin()) {
                                continue;
                            }
                            
                            $days_left = null;
                            $expiry_badge_class = '';
                            $expiry_badge_text = '';
                            $is_expired_page = false;

                            if ($p['status'] === 'published') {
                                if (!empty($p['expiry_date'])) {
                                    $expiry_time = strtotime($p['expiry_date']);
                                    $time_left = $expiry_time - time();
                                    if ($time_left < 0) {
                                        $is_expired_page = true;
                                        $expiry_badge_class = 'bg-slate-500/10 text-slate-400 border border-slate-500/20';
                                        $expiry_badge_text = 'Expired';
                                    } else {
                                        $days_left = ceil($time_left / 86400);
                                        if ($days_left < 2) {
                                            $expiry_badge_class = 'bg-red-500/10 text-red-400 border border-red-500/20 animate-pulse';
                                            $expiry_badge_text = 'Expires today';
                                        } elseif ($days_left <= 5) {
                                            $expiry_badge_class = 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20';
                                            $expiry_badge_text = "Expires in $days_left days";
                                        } else {
                                            $expiry_badge_class = 'bg-green-500/10 text-green-400 border border-green-500/20';
                                            $expiry_badge_text = "Expires in $days_left days";
                                        }
                                    }
                                } else {
                                    $expiry_badge_class = 'bg-green-500/10 text-green-400 border border-green-500/20';
                                    $expiry_badge_text = 'Active';
                                }
                            }

                            $storage_formatted = '0 B';
                            if (isset($p['storage_bytes'])) {
                                $bytes = (int)$p['storage_bytes'];
                                if ($bytes > 0) {
                                    $units = ['B', 'KB', 'MB', 'GB'];
                                    $pow = floor(log($bytes, 1024));
                                    $pow = min($pow, count($units) - 1);
                                    $storage_formatted = round($bytes / pow(1024, $pow), 1) . ' ' . $units[$pow];
                                }
                            }
                            ?>
                            <div class="glass-card rounded-2xl p-5 hover:border-pink-500/20 transition-all flex flex-col justify-between" id="page-card-<?= $p['id'] ?>">
                                <div>
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center gap-3">
                                            <span class="text-2xl"><?= $cat['icon'] ?></span>
                                            <div>
                                                <h3 class="text-sm font-bold text-white"><?= h($p['title']) ?></h3>
                                                <p class="text-xs text-slate-500"><?= $cat['name'] ?> • <?= date('M d, Y', strtotime($p['created_at'])) ?></p>
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-end gap-1.5">
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider <?= $p['status'] === 'published' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20' ?>"><?= $p['status'] ?></span>
                                            <?php if ($p['status'] === 'published'): ?>
                                                <span id="expiry-badge-<?= $p['id'] ?>" class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider <?= $expiry_badge_class ?>"><?= $expiry_badge_text ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Stats Row -->
                                    <div class="flex items-center gap-4 mb-3 text-xs text-slate-400">
                                        <span>👁️ <?= $p['view_count'] ?> views</span>
                                        <span>❤️ <?= $p['reaction_count'] ?> reactions</span>
                                        <span>💬 <?= $p['reply_count'] ?> replies</span>
                                    </div>

                                    <!-- Advanced Analytics Grid -->
                                    <?php if ($p['status'] === 'published'): ?>
                                        <div class="mt-3 mb-4 pt-3 border-t border-slate-800/60 grid grid-cols-2 gap-2 text-center text-[11px]">
                                            <div class="bg-slate-900/40 rounded-xl p-2 border border-slate-800/40 flex flex-col justify-center">
                                                <span class="text-[9px] text-slate-500 uppercase font-semibold">Unique Visitors</span>
                                                <span class="font-bold text-slate-200 mt-0.5"><?= number_format($p['unique_visitors']) ?></span>
                                            </div>
                                            <div class="bg-slate-900/40 rounded-xl p-2 border border-slate-800/40 flex flex-col justify-center">
                                                <span class="text-[9px] text-slate-500 uppercase font-semibold">Avg Watch Time</span>
                                                <span class="font-bold text-slate-200 mt-0.5"><?= format_watch_time($p['avg_watch_time']) ?></span>
                                            </div>
                                            <div class="bg-slate-900/40 rounded-xl p-2 border border-slate-800/40 flex flex-col justify-center">
                                                <span class="text-[9px] text-slate-500 uppercase font-semibold">Completion Rate</span>
                                                <span class="font-bold text-pink-400 mt-0.5"><?= round($p['completion_rate'], 1) ?>%</span>
                                            </div>
                                            <div class="bg-slate-900/40 rounded-xl p-2 border border-slate-800/40 flex flex-col justify-center">
                                                <span class="text-[9px] text-slate-500 uppercase font-semibold">Storage Used</span>
                                                <span class="font-bold text-slate-200 mt-0.5"><?= $storage_formatted ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-3 mb-4 pt-3 border-t border-slate-800/60 flex items-center justify-between text-[11px] text-slate-500 px-1">
                                            <span>Storage: <?= $storage_formatted ?></span>
                                            <span>Publish page to track metrics</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2 flex-wrap border-t border-slate-900/80 pt-3">
                                    <a href="p.php?s=<?= h($p['slug']) ?>" target="_blank" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1.5 rounded-lg transition">Preview</a>
                                    <?php
                                        $tpl = $p['template'] ?? '';
                                        if ($tpl === 'cinematic_birthday')      $edit_link = 'customize-birthday.php?slug=';
                                        elseif ($tpl === 'proposal_cinematic')  $edit_link = 'customize-proposal.php?slug=';
                                        elseif ($tpl === 'sorry_cinematic')     $edit_link = 'customize-sorry.php?slug=';
                                        elseif ($tpl === 'couple_story')        $edit_link = 'customize-couple.php?slug=';
                                        else                                    $edit_link = 'edit-preview.php?slug=';
                                    ?>
                                    <a href="<?= $edit_link . h($p['slug']) ?>" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1.5 rounded-lg transition">Edit</a>
                                    <?php if ($p['status'] === 'published'): ?>
                                        <button onclick="copyLink('<?= h($p['slug']) ?>')" class="text-xs bg-pink-500/10 hover:bg-pink-500/20 text-pink-400 px-3 py-1.5 rounded-lg transition">Share</button>
                                        <button onclick="openExtendModal(<?= $p['id'] ?>, '<?= h(addslashes($p['title'])) ?>', '<?= !empty($p['expiry_date']) ? h(date('Y-m-d H:i:s', strtotime($p['expiry_date']))) : '' ?>')" class="text-xs bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 px-3 py-1.5 rounded-lg transition">Extend</button>
                                    <?php endif; ?>
                                    <?php if ($p['reply_count'] > 0): ?>
                                        <button onclick="toggleReplies(<?= $p['id'] ?>)" class="text-xs bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 px-3 py-1.5 rounded-lg transition">Replies (<?= $p['reply_count'] ?>)</button>
                                    <?php endif; ?>
                                    <button onclick="deletePage(<?= $p['id'] ?>)" class="text-xs bg-red-500/10 hover:bg-red-500/20 text-red-400 px-3 py-1.5 rounded-lg transition ml-auto">Delete</button>
                                </div>

                                <!-- Replies Panel (hidden) -->
                                <div id="replies-<?= $p['id'] ?>" class="hidden mt-4 border-t border-slate-800 pt-4 space-y-3 max-h-60 overflow-y-auto">
                                    <p class="text-xs text-slate-500">Loading replies...</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Account & Referrals -->
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Account Info -->
                <div class="glass-card rounded-2xl p-6 flex flex-col justify-between">
                    <div>
                        <h3 class="text-lg font-bold font-heading text-white mb-4">Account Credits</h3>
                        <div class="flex items-center gap-3 mb-4">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="<?= h($user['profile_photo']) ?>" class="w-12 h-12 rounded-full border-2 border-pink-500/20" alt="">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-slate-800 flex items-center justify-center text-lg">👤</div>
                            <?php endif; ?>
                            <div>
                                <p class="font-semibold text-white"><?= h($user['name']) ?></p>
                                <p class="text-xs text-slate-500"><?= h($user['email']) ?></p>
                            </div>
                        </div>
                        <div class="border-t border-slate-800/80 pt-3 space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-slate-500">Pages Created</span><span class="text-white font-bold"><?= $page_count ?></span></div>
                            <div class="flex justify-between"><span class="text-slate-500">Free Pages Used</span><span class="text-white font-bold"><?= min($page_count, $free_limit) ?>/<?= $free_limit ?></span></div>
                            <div class="flex justify-between"><span class="text-slate-500">Credits Remaining</span><span class="text-green-400 font-bold"><?= $credits ?></span></div>
                        </div>
                        <?php if (!empty($payment_history)): ?>
                            <div class="border-t border-slate-800/80 mt-4 pt-3">
                                <p class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-2">Recent Payments</p>
                                <?php foreach ($payment_history as $pay): ?>
                                    <div class="flex justify-between text-xs py-1">
                                        <span class="text-slate-400"><?= date('M d, Y', strtotime($pay['created_at'])) ?></span>
                                        <span class="text-green-400 font-bold">₹<?= number_format($pay['amount'] / 100) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($user['free_pages_used'] >= $free_limit && $credits <= 0): ?>
                        <a href="payment.php" class="block w-full text-center bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold text-sm py-3 rounded-xl mt-4 hover:-translate-y-0.5 transition-all shadow-lg shadow-pink-500/20">Buy Credits (₹<?= number_format($price_rupees) ?>/page)</a>
                    <?php endif; ?>
                </div>

                <!-- Referral Section -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="text-lg font-bold font-heading text-white mb-4">🎁 Refer & Earn</h3>
                    <p class="text-sm text-slate-400 mb-4">Invite friends and get <span class="text-pink-400 font-bold">+1 free credit</span> for each friend who joins!</p>
                    
                    <div class="bg-slate-900/80 rounded-xl p-4 mb-4">
                        <p class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-2">Your Referral Code</p>
                        <div class="flex items-center gap-2">
                            <code id="ref-code" class="flex-grow text-lg font-mono font-bold text-pink-400 bg-slate-800 rounded-lg px-4 py-2 text-center tracking-widest"><?= h($user['referral_code'] ?? '') ?></code>
                            <button onclick="copyRefCode()" class="bg-pink-500/10 hover:bg-pink-500/20 text-pink-400 p-2.5 rounded-lg transition" title="Copy">
                                📋
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mb-4">
                        <button onclick="copyRefLink()" class="flex-grow bg-gradient-to-r from-pink-500 to-purple-500 hover:from-pink-400 hover:to-purple-400 text-white font-bold text-xs py-2.5 rounded-xl transition-all hover:-translate-y-0.5 shadow-lg shadow-pink-500/20">📋 Copy Referral Link</button>
                        <a href="https://wa.me/?text=Hey!%20Create%20beautiful%20emotional%20pages%20on%20SoulSync!%20Use%20my%20referral%20link:%20<?= urlencode(SITE_URL . '/signup.php?ref=' . ($user['referral_code'] ?? '')) ?>" target="_blank" class="bg-green-500/10 hover:bg-green-500/20 text-green-400 font-bold text-xs py-2.5 px-4 rounded-xl transition">WhatsApp</a>
                    </div>

                    <div class="border-t border-slate-800 pt-3 flex items-center gap-6 text-sm">
                        <div>
                            <p class="text-2xl font-extrabold font-heading text-white"><?= (int)($ref_stats['total'] ?? 0) ?></p>
                            <p class="text-xs text-slate-500">Friends Joined</p>
                        </div>
                        <div>
                            <p class="text-2xl font-extrabold font-heading text-green-400"><?= (int)($ref_stats['credits_earned'] ?? 0) ?></p>
                            <p class="text-xs text-slate-500">Credits Earned</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: PROFILE SETTINGS VIEW -->
        <div id="tab-content-settings" class="hidden">
            <h2 class="text-xl font-extrabold font-heading text-white mb-6">Profile Settings</h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left column: profile pic upload -->
                <div class="glass-card rounded-2xl p-6 text-center space-y-4 h-fit">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-2">Profile Photo</h3>
                    <div class="flex flex-col items-center">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img src="<?= h($user['profile_photo']) ?>" class="w-32 h-32 rounded-full border-4 border-slate-800 object-cover shadow-xl mb-4" alt="">
                        <?php else: ?>
                            <div class="w-32 h-32 rounded-full bg-slate-800 flex items-center justify-center text-4xl mb-4">👤</div>
                        <?php endif; ?>
                        
                        <form method="POST" action="dashboard.php" enctype="multipart/form-data" class="w-full space-y-3">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                            <input type="hidden" name="action" value="upload_photo">
                            
                            <label class="block cursor-pointer">
                                <span class="sr-only">Choose profile photo</span>
                                <input type="file" name="profile_pic" required accept="image/*" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-900 file:text-slate-300 hover:file:bg-slate-800">
                            </label>
                            
                            <button type="submit" class="w-full py-2 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-semibold text-xs rounded-xl shadow transition hover:opacity-90">
                                Upload Photo 🖼️
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Middle/Right column: forms -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Personal Info Card -->
                    <div class="glass-card rounded-2xl p-6">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-4">Personal Details</h3>
                        <form method="POST" action="dashboard.php" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div>
                                <label for="name" class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Full Name</label>
                                <input type="text" id="name" name="name" required value="<?= h($user['name']) ?>" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-4 py-2.5 text-xs outline-none text-white">
                            </div>
                            
                            <div>
                                <label for="username" class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Username</label>
                                <input type="text" id="username" name="username" value="<?= h($user['username'] ?? '') ?>" placeholder="Choose username" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-4 py-2.5 text-xs outline-none text-white">
                            </div>

                            <div>
                                <label for="email" class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Email Address</label>
                                <input type="email" id="email" name="email" required value="<?= h($user['email']) ?>" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-4 py-2.5 text-xs outline-none text-white">
                            </div>
                            
                            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-semibold text-xs rounded-xl shadow hover:opacity-90 transition">
                                Save Profile Details
                            </button>
                        </form>
                    </div>
                    
                    <!-- Change Password Card -->
                    <div class="glass-card rounded-2xl p-6">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-4">Change Password</h3>
                        <form method="POST" action="dashboard.php" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div>
                                <label for="current_password" class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-4 py-2.5 text-xs outline-none text-white">
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-xs font-semibold text-slate-400 mb-2 uppercase">New Password</label>
                                <input type="password" id="new_password" name="new_password" required minlength="8" placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-4 py-2.5 text-xs outline-none text-white">
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-4 py-2.5 text-xs outline-none text-white">
                            </div>
                            
                            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-semibold text-xs rounded-xl shadow hover:opacity-90 transition">
                                Update Password
                            </button>
                        </form>
                    </div>

                    <!-- Delete Account Card -->
                    <div class="glass-card rounded-2xl p-6 border-red-500/20 hover:border-red-500/30">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-red-400 mb-2 flex items-center gap-2">
                            <span>⚠️</span> Danger Zone: Delete Account
                        </h3>
                        <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                            Deleting your account is permanent. All pages you have generated, reaction statistics, page views, paid credits, and payment history will be deleted instantly and cannot be recovered.
                        </p>
                        
                        <form method="POST" action="dashboard.php" class="space-y-4" onsubmit="return confirmAccountDeletion()">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                            <input type="hidden" name="action" value="delete_account">
                            
                            <div>
                                <label for="delete_password" class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Confirm with password</label>
                                <input type="password" id="delete_password" name="password" required placeholder="Enter password to confirm" class="w-full bg-slate-900 border border-slate-800 focus:border-red-500 rounded-xl px-4 py-2.5 text-xs outline-none text-white">
                            </div>
                            
                            <div class="flex items-start gap-2 py-1">
                                <input type="checkbox" id="confirm_delete" name="confirm_delete" required class="mt-0.5 rounded border-red-950 bg-slate-900 text-red-500 focus:ring-0 focus:ring-offset-0">
                                <label for="confirm_delete" class="text-xs text-slate-500 leading-tight select-none cursor-pointer">
                                    I understand that this action is irreversible and permanently destroys my data.
                                </label>
                            </div>
                            
                            <button type="submit" class="px-6 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold text-xs rounded-xl shadow transition">
                                Permanently Delete My Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: REPLIES INBOX VIEW -->
        <div id="tab-content-replies" class="hidden">
            <div class="mb-6 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-extrabold font-heading text-white">Replies Inbox</h2>
                    <p class="text-slate-400 text-sm mt-1">Manage replies received from all your active pages.</p>
                </div>
                <div>
                    <button onclick="markAllRepliesRead()" class="w-full sm:w-auto px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl shadow transition">
                        Mark All as Read
                    </button>
                </div>
            </div>

            <!-- Inbox Replies List -->
            <div class="space-y-4" id="inbox-list">
                <!-- Loaded dynamically via JavaScript -->
            </div>
        </div>

    </main>

    <!-- Expiry Extension Modal -->
    <div id="extend-modal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-350">
        <div class="glass-card w-full max-w-md rounded-3xl p-6 mx-4 relative transform translate-y-6 transition-transform duration-355 bg-slate-950/90 border border-slate-800/80 shadow-2xl">
            <button onclick="closeExtendModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-xl">✕</button>
            <h3 class="text-xl font-extrabold font-heading text-white mb-2">Extend Page Lifespan ⏳</h3>
            <p class="text-xs text-slate-400 mb-4" id="extend-page-info">Extend lifespan of "<span id="extend-page-title" class="text-pink-400 font-semibold"></span>". Current expiry: <span id="extend-page-expiry" class="text-slate-300 font-semibold"></span></p>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2.5 uppercase tracking-wider">Choose Extension Duration</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="extend_days" value="10" checked class="sr-only peer" onchange="updateExtensionCost()">
                            <div class="bg-slate-900/60 border border-slate-800/80 peer-checked:border-pink-500 peer-checked:bg-pink-500/10 rounded-2xl p-3 text-center transition-all hover:bg-slate-850">
                                <span class="block text-lg font-bold text-white font-heading">10</span>
                                <span class="block text-[9px] text-slate-500 peer-checked:text-pink-300 uppercase mt-0.5 tracking-wider">Days</span>
                                <span class="block text-xs font-bold text-emerald-400 mt-1">1 Credit</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="extend_days" value="20" class="sr-only peer" onchange="updateExtensionCost()">
                            <div class="bg-slate-900/60 border border-slate-800/80 peer-checked:border-pink-500 peer-checked:bg-pink-500/10 rounded-2xl p-3 text-center transition-all hover:bg-slate-850">
                                <span class="block text-lg font-bold text-white font-heading">20</span>
                                <span class="block text-[9px] text-slate-500 peer-checked:text-pink-300 uppercase mt-0.5 tracking-wider">Days</span>
                                <span class="block text-xs font-bold text-emerald-400 mt-1">2 Credits</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="extend_days" value="30" class="sr-only peer" onchange="updateExtensionCost()">
                            <div class="bg-slate-900/60 border border-slate-800/80 peer-checked:border-pink-500 peer-checked:bg-pink-500/10 rounded-2xl p-3 text-center transition-all hover:bg-slate-850">
                                <span class="block text-lg font-bold text-white font-heading">30</span>
                                <span class="block text-[9px] text-slate-500 peer-checked:text-pink-300 uppercase mt-0.5 tracking-wider">Days</span>
                                <span class="block text-xs font-bold text-emerald-400 mt-1">3 Credits</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="bg-slate-900/40 border border-slate-850 rounded-2xl p-4 text-xs space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Your Current Credits:</span>
                        <span id="user-current-credits" class="font-bold text-white"><?= $credits ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Extension Cost:</span>
                        <span id="extension-cost" class="font-bold text-emerald-400">1 Credit</span>
                    </div>
                    <div class="border-t border-slate-800 pt-2 flex justify-between">
                        <span class="text-slate-400 font-semibold">Remaining Balance:</span>
                        <span id="user-remaining-credits" class="font-bold text-white"><?= $credits - 1 ?></span>
                    </div>
                </div>

                <div id="extend-error" class="bg-red-500/10 border border-red-500/20 text-red-400 p-3 rounded-xl text-xs hidden"></div>

                <div class="flex items-center gap-3 pt-2">
                    <button onclick="closeExtendModal()" class="flex-1 bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 font-semibold py-3 rounded-xl transition">Cancel</button>
                    <button id="extend-submit-btn" onclick="submitExtendExpiry()" class="flex-1 bg-gradient-to-r from-pink-500 to-purple-500 hover:from-pink-400 hover:to-purple-400 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-pink-500/20">Extend Now</button>
                </div>
                
                <div id="buy-credits-prompt" class="text-center text-xs text-slate-500 hidden pt-1">
                    Need more credits? <a href="payment.php" class="text-pink-400 hover:underline">Buy credits here</a>.
                </div>
            </div>
        </div>
    </div>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-[10px] space-y-1">
        <p>&copy; <?= date('Y') ?> SoulSync. All rights reserved.</p>
        <p>Need help? Visit our <a href="support.php" class="text-pink-400 hover:underline">Help & Support</a> page</p>
    </footer>

    <script>
        const baseUrl = '<?= $base_url ?>';
        
    // Tab Toggling
    function switchTab(tabId) {
        const tabDashboard = document.getElementById('tab-content-dashboard');
        const tabSettings = document.getElementById('tab-content-settings');
        const tabReplies = document.getElementById('tab-content-replies');
        
        const btnDashboard = document.getElementById('tab-btn-dashboard');
        const btnSettings = document.getElementById('tab-btn-settings');
        const btnReplies = document.getElementById('tab-btn-replies');
        
        // Hide all
        tabDashboard.classList.add('hidden');
        tabSettings.classList.add('hidden');
        tabReplies.classList.add('hidden');
        
        btnDashboard.classList.remove('active');
        btnSettings.classList.remove('active');
        btnReplies.classList.remove('active');
        
        if (tabId === 'settings') {
            tabSettings.classList.remove('hidden');
            btnSettings.classList.add('active');
            localStorage.setItem('dashboard_tab', 'settings');
        } else if (tabId === 'replies') {
            tabReplies.classList.remove('hidden');
            btnReplies.classList.add('active');
            localStorage.setItem('dashboard_tab', 'replies');
            loadInboxReplies();
        } else {
            tabDashboard.classList.remove('hidden');
            btnDashboard.classList.add('active');
            localStorage.setItem('dashboard_tab', 'dashboard');
        }
    }
    
    // Remember Tab on Refresh
    window.onload = function() {
        updateUnreadBadge();
        const savedTab = localStorage.getItem('dashboard_tab');
        if (savedTab === 'settings') {
            switchTab('settings');
        } else if (savedTab === 'replies') {
            switchTab('replies');
        }
    };

    function copyLink(slug) {
        const url = baseUrl + 'p/' + slug;
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied! Share it with your loved one 💕');
        });
    }

    function copyRefCode() {
        const code = document.getElementById('ref-code').textContent;
        navigator.clipboard.writeText(code).then(() => alert('Referral code copied!'));
    }

    function copyRefLink() {
        const code = document.getElementById('ref-code').textContent;
        const url = baseUrl + 'signup.php?ref=' + code;
        navigator.clipboard.writeText(url).then(() => alert('Referral link copied! Share it with friends 🎉'));
    }

    function deletePage(pageId) {
        if (!confirm('Are you sure you want to delete this page? This cannot be undone.')) return;
        fetch(baseUrl + 'api.php?action=delete_page', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'page_id=' + pageId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('page-card-' + pageId).remove();
                updateUnreadBadge();
            } else {
                alert(data.error || 'Failed to delete page.');
            }
        });
    }

    function toggleReplies(pageId) {
        const panel = document.getElementById('replies-' + pageId);
        if (panel.classList.contains('hidden')) {
            panel.classList.remove('hidden');
            panel.innerHTML = '<p class="text-xs text-slate-500 text-center py-2">Loading replies & decisions...</p>';
            
            Promise.all([
                fetch(baseUrl + 'api.php?action=get_replies&page_id=' + pageId).then(r => r.json()),
                fetch(baseUrl + 'api.php?action=get_interactive_replies&page_id=' + pageId).then(r => r.json())
            ])
            .then(([repliesRes, interactiveRes]) => {
                let html = '';
                
                // Render Interactive Ending Replies
                if (interactiveRes.success && interactiveRes.replies && interactiveRes.replies.length > 0) {
                    html += '<div class="space-y-2 mb-4">';
                    html += '<h5 class="text-[10px] font-bold text-pink-400 uppercase tracking-wider mb-2">Interactive Choices (Yes/No)</h5>';
                    html += interactiveRes.replies.map(r => {
                        const isYes = r.selected_answer === 'yes';
                        const badgeColor = isYes ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                        const answerText = isYes ? (r.positive_button_text || 'YES ❤️') : (r.negative_button_text || 'NO');
                        
                        return '<div class="flex items-start justify-between bg-slate-900/40 rounded-xl p-3 border border-slate-800/50">' +
                            '<div class="min-w-0 flex-grow">' +
                                '<div class="flex items-center gap-2 mb-1">' +
                                    '<span class="text-xs font-bold text-slate-200">' + escapeHtml(r.visitor_name) + '</span>' +
                                    '<span class="text-[9px] px-2 py-0.5 rounded-full border ' + badgeColor + '">' + escapeHtml(answerText) + '</span>' +
                                '</div>' +
                                '<p class="text-[11px] text-slate-400">Question: "' + escapeHtml(r.question) + '"</p>' +
                                '<p class="text-[10px] text-slate-500 mt-1 flex flex-wrap gap-x-2 gap-y-1">' +
                                    '<span>Attempts: ' + (parseInt(r.no_click_count) || 0) + '</span> &bull; ' +
                                    '<span>Browser: ' + escapeHtml(r.browser || 'Unknown') + '</span> &bull; ' +
                                    '<span>Device: ' + escapeHtml(r.device || 'Unknown') + '</span>' +
                                '</p>' +
                            '</div>' +
                            '<button onclick="deleteInteractiveReply(' + r.id + ', ' + pageId + ')" class="text-red-500/60 hover:text-red-400 text-xs ml-2">&times;</button>' +
                        '</div>';
                    }).join('');
                    html += '</div>';
                }
                
                // Render Media Replies
                if (repliesRes.success && repliesRes.replies && repliesRes.replies.length > 0) {
                    html += '<div class="space-y-2">';
                    html += '<h5 class="text-[10px] font-bold text-blue-400 uppercase tracking-wider mb-2">Media & Message Responses</h5>';
                    html += repliesRes.replies.map(r => {
                        let content = '';
                        const typeIcons = {text: '📝', emoji: '😊', voice: '🎤', image: '🖼️', video: '📹'};
                        const icon = typeIcons[r.reply_type] || '💬';
                        
                        if (r.reply_type === 'text' || r.reply_type === 'emoji') {
                            content = '<p class="text-xs text-slate-300">' + escapeHtml(r.message || '') + '</p>';
                        } else if (r.reply_type === 'voice' && r.voice_path) {
                            content = '<audio controls class="w-full h-8" src="' + r.voice_path + '"></audio>';
                        } else if (r.reply_type === 'image' && r.image_path) {
                            content = '<a href="' + r.image_path + '" target="_blank"><img src="' + r.image_path + '" class="w-24 h-24 object-cover rounded-lg"></a>';
                        } else if (r.reply_type === 'video' && r.video_path) {
                            content = '<video controls class="w-full max-w-xs rounded-lg" src="' + r.video_path + '"></video>';
                        }
                        
                        return '<div class="flex items-start gap-3 bg-slate-900/50 rounded-xl p-3 border border-slate-800/40">' +
                            '<span class="text-xs flex-shrink-0">' + icon + '</span>' +
                            '<div class="flex-grow min-w-0">' +
                                '<div class="flex items-center justify-between mb-1">' +
                                    '<span class="text-xs font-semibold text-slate-300">' + escapeHtml(r.visitor_name || 'Anonymous') + '</span>' +
                                    '<span class="text-[9px] text-slate-600">' + new Date(r.created_at).toLocaleDateString() + '</span>' +
                                '</div>' +
                                content +
                            '</div>' +
                            '<button onclick="deleteReply(' + r.id + ', ' + pageId + ')" class="text-red-500/50 hover:text-red-400 text-xs flex-shrink-0">&times;</button>' +
                        '</div>';
                    }).join('');
                    html += '</div>';
                }
                
                if (html === '') {
                    panel.innerHTML = '<p class="text-xs text-slate-600 text-center py-2">No responses received yet.</p>';
                } else {
                    panel.innerHTML = html;
                }
            })
            .catch(() => {
                panel.innerHTML = '<p class="text-xs text-red-400 text-center py-2">Error loading replies.</p>';
            });
        } else {
            panel.classList.add('hidden');
        }
    }

    function deleteInteractiveReply(replyId, pageId) {
        if (!confirm('Delete this interactive reply record?')) return;
        fetch(baseUrl + 'api.php?action=delete_interactive_reply', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'reply_id=' + replyId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Reload replies panel
                const panel = document.getElementById('replies-' + pageId);
                panel.classList.add('hidden');
                toggleReplies(pageId);
            } else {
                alert(data.error || 'Failed to delete reply.');
            }
        });
    }

    function deleteReply(replyId, pageId) {
        if (!confirm('Delete this reply?')) return;
        fetch(baseUrl + 'api.php?action=delete_reply', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'reply_id=' + replyId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toggleReplies(pageId);
                setTimeout(() => toggleReplies(pageId), 100);
                updateUnreadBadge();
            }
        });
    }

    function loadInboxReplies() {
        const inboxContainer = document.getElementById('inbox-list');
        if (!inboxContainer) return;
        
        inboxContainer.innerHTML = '<p class="text-sm text-slate-500 py-8 text-center animate-pulse">Loading inbox replies...</p>';
        
        fetch(baseUrl + 'api.php?action=get_replies')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.replies.length > 0) {
                inboxContainer.innerHTML = data.replies.map(r => {
                    const isUnread = parseInt(r.is_read) === 0;
                    const typeIcons = {text: '📝', emoji: '😊', voice: '🎤', image: '🖼️', video: '📹'};
                    const icon = typeIcons[r.reply_type] || '💬';
                    
                    let mediaContent = '';
                    if (r.reply_type === 'text' || r.reply_type === 'emoji') {
                        // handled inline or in message
                    } else if (r.reply_type === 'voice' && r.voice_path) {
                        mediaContent = `
                            <div class="mt-2.5">
                                <audio controls class="w-full max-w-md h-9 rounded-lg" src="${r.voice_path}"></audio>
                            </div>`;
                    } else if (r.reply_type === 'image' && r.image_path) {
                        mediaContent = `
                            <div class="mt-2.5">
                                <a href="${r.image_path}" target="_blank" class="inline-block group relative rounded-xl overflow-hidden border border-slate-800">
                                    <img src="${r.image_path}" class="w-32 h-32 object-cover group-hover:scale-105 transition-transform duration-300">
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-xs text-white">View Full</div>
                                </a>
                            </div>`;
                    } else if (r.reply_type === 'video' && r.video_path) {
                        mediaContent = `
                            <div class="mt-2.5">
                                <video controls class="w-full max-w-sm rounded-xl border border-slate-800" src="${r.video_path}"></video>
                            </div>`;
                    }

                    const badgeHtml = isUnread 
                        ? `<span class="bg-rose-500/10 text-rose-400 border border-rose-500/20 text-[10px] font-bold px-2 py-0.5 rounded-full inline-block">New</span>` 
                        : '';
                        
                    const markReadBtn = isUnread
                        ? `<button onclick="markReplyRead(${r.id})" class="text-xs bg-slate-850 hover:bg-slate-800 text-slate-200 border border-slate-700 px-2.5 py-1.5 rounded-lg transition">Mark as Read</button>`
                        : '';

                    const escapedMessage = r.message ? escapeHtml(r.message) : '';

                    return `
                        <div class="glass-card rounded-2xl p-5 hover:border-slate-800/80 transition-all flex flex-col md:flex-row md:items-start justify-between gap-4 ${isUnread ? 'border-l-4 border-l-rose-500' : ''}">
                            <div class="flex-grow min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="text-lg">${icon}</span>
                                    <span class="text-sm font-bold text-white">${escapeHtml(r.visitor_name || 'Anonymous')}</span>
                                    ${badgeHtml}
                                    <span class="text-xs text-slate-500">on <a href="p.php?s=${r.page_slug}" target="_blank" class="text-pink-400 hover:underline">${escapeHtml(r.page_title)}</a></span>
                                </div>
                                <p class="text-xs text-slate-500 mb-2">${new Date(r.created_at).toLocaleString()}</p>
                                ${escapedMessage ? `<p class="text-sm text-slate-300 whitespace-pre-wrap leading-relaxed">${escapedMessage}</p>` : ''}
                                ${mediaContent}
                            </div>
                            <div class="flex items-center gap-2 mt-2 md:mt-0 self-end md:self-start flex-shrink-0">
                                ${markReadBtn}
                                <button onclick="deleteInboxReply(${r.id})" class="text-xs bg-red-500/10 hover:bg-red-500/20 text-red-400 px-2.5 py-1.5 rounded-lg transition">Delete</button>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                inboxContainer.innerHTML = `
                    <div class="glass-card rounded-3xl p-12 text-center">
                        <div class="text-5xl mb-4">📥</div>
                        <h3 class="text-lg font-bold text-white mb-2">Your inbox is empty</h3>
                        <p class="text-slate-400 text-sm">When visitors reply to your pages, they will show up here.</p>
                    </div>
                `;
            }
        });
    }

    function markReplyRead(replyId) {
        fetch(baseUrl + 'api.php?action=mark_reply_read', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'reply_id=' + replyId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadInboxReplies();
                updateUnreadBadge();
            } else {
                alert(data.error || 'Failed to mark reply as read.');
            }
        });
    }

    function markAllRepliesRead() {
        if (!confirm('Mark all replies as read?')) return;
        fetch(baseUrl + 'api.php?action=mark_all_read', {
            method: 'POST'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadInboxReplies();
                updateUnreadBadge();
            } else {
                alert(data.error || 'Failed to mark all replies as read.');
            }
        });
    }

    function deleteInboxReply(replyId) {
        if (!confirm('Are you sure you want to delete this reply? This will permanently delete the reply and its media files.')) return;
        fetch(baseUrl + 'api.php?action=delete_reply', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'reply_id=' + replyId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadInboxReplies();
                updateUnreadBadge();
            } else {
                alert(data.error || 'Failed to delete reply.');
            }
        });
    }

    function updateUnreadBadge() {
        fetch(baseUrl + 'api.php?action=get_unread_count')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('unread-badge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function confirmAccountDeletion() {
        const pass = document.getElementById('delete_password').value;
        if (!pass) return false;
        return confirm('Are you absolutely sure you want to permanently delete your account and all pages? This action is irreversible!');
    }

    // Expiry Extension Logic
    let currentExtendPageId = null;

    function openExtendModal(pageId, pageTitle, currentExpiry) {
        currentExtendPageId = pageId;
        document.getElementById('extend-page-title').textContent = pageTitle;
        
        let expiryStr = 'Never expires';
        if (currentExpiry) {
            const date = new Date(currentExpiry);
            expiryStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }
        document.getElementById('extend-page-expiry').textContent = expiryStr;
        
        // Set 10 days default
        const radios = document.getElementsByName('extend_days');
        for (let r of radios) {
            if (r.value === "10") r.checked = true;
        }
        
        updateExtensionCost();
        document.getElementById('extend-error').classList.add('hidden');
        
        const modal = document.getElementById('extend-modal');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.glass-card').classList.remove('translate-y-6');
        }, 50);
    }

    function closeExtendModal() {
        const modal = document.getElementById('extend-modal');
        modal.classList.add('opacity-0');
        modal.querySelector('.glass-card').classList.add('translate-y-6');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 350);
    }

    function updateExtensionCost() {
        const userCredits = parseInt(document.getElementById('user-current-credits').textContent);
        const radios = document.getElementsByName('extend_days');
        let days = 10;
        for (let r of radios) {
            if (r.checked) {
                days = parseInt(r.value);
                break;
            }
        }
        const cost = Math.ceil(days / 10);
        document.getElementById('extension-cost').textContent = cost + (cost === 1 ? ' Credit' : ' Credits');
        
        const remaining = userCredits - cost;
        const remEl = document.getElementById('user-remaining-credits');
        remEl.textContent = remaining;
        
        const buyPrompt = document.getElementById('buy-credits-prompt');
        const submitBtn = document.getElementById('extend-submit-btn');
        if (remaining < 0) {
            remEl.classList.add('text-red-400');
            buyPrompt.classList.remove('hidden');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            remEl.classList.remove('text-red-400');
            buyPrompt.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    function submitExtendExpiry() {
        if (!currentExtendPageId) return;
        
        const radios = document.getElementsByName('extend_days');
        let days = 10;
        for (let r of radios) {
            if (r.checked) {
                days = parseInt(r.value);
                break;
            }
        }
        
        const submitBtn = document.getElementById('extend-submit-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Extending...';
        
        const formData = new URLSearchParams();
        formData.append('page_id', currentExtendPageId);
        formData.append('days', days);
        
        fetch(baseUrl + 'api.php?action=extend_expiry', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        })
        .then(r => r.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Extend Now';
            
            if (data.success) {
                closeExtendModal();
                alert('Lifespan extended successfully! New expiry: ' + data.new_expiry);
                window.location.reload();
            } else {
                const errEl = document.getElementById('extend-error');
                errEl.textContent = data.error || 'Failed to extend lifespan.';
                errEl.classList.remove('hidden');
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Extend Now';
            alert('Network error. Please try again.');
        });
    }

    // Notification System JavaScript
    let notificationDropdownOpen = false;

    function toggleNotificationDropdown() {
        const dropdown = document.getElementById('notification-dropdown');
        if (!dropdown) return;
        
        notificationDropdownOpen = !notificationDropdownOpen;
        if (notificationDropdownOpen) {
            dropdown.classList.remove('hidden');
            loadNotifications();
        } else {
            dropdown.classList.add('hidden');
        }
    }

    function loadNotifications() {
        const list = document.getElementById('notification-list');
        if (!list) return;
        
        fetch(baseUrl + 'api.php?action=get_notifications')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Update badge
                const badge = document.getElementById('notification-badge');
                if (badge) {
                    if (data.unread_count > 0) {
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
                
                if (data.notifications && data.notifications.length > 0) {
                    list.innerHTML = data.notifications.map(n => {
                        const isRead = parseInt(n.is_read) === 1;
                        const itemBg = isRead ? 'bg-transparent' : 'bg-pink-500/5';
                        const dotColor = isRead ? 'bg-transparent' : 'bg-pink-500 animate-pulse';
                        
                        return '<div onclick="handleNotificationClick(' + n.id + ', \'' + escapeHtml(n.page_slug || '') + '\')" class="p-3 flex items-start gap-2 cursor-pointer hover:bg-slate-800/40 transition ' + itemBg + '">' +
                            '<span class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0 ' + dotColor + '"></span>' +
                            '<div class="flex-grow min-w-0">' +
                                '<p class="text-xs text-slate-300 leading-normal">' + escapeHtml(n.message) + '</p>' +
                                '<span class="text-[9px] text-slate-600 mt-1 block">' + new Date(n.created_at).toLocaleString() + '</span>' +
                            '</div>' +
                        '</div>';
                    }).join('');
                } else {
                    list.innerHTML = '<p class="text-xs text-slate-500 py-6 text-center text-center">No notifications yet</p>';
                }
            }
        });
    }

    function handleNotificationClick(notifId, pageSlug) {
        // Mark as read
        fetch(baseUrl + 'api.php?action=mark_notification_read', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'notification_id=' + notifId
        })
        .then(() => {
            loadNotifications();
            // Redirect to page slug or just reload dashboard
            if (pageSlug) {
                alert('Notification read! Directing you to your page details.');
                window.location.reload();
            }
        });
    }

    function markAllNotificationsRead() {
        if (!confirm('Mark all notifications as read?')) return;
        fetch(baseUrl + 'api.php?action=mark_all_notifications_read', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        });
    }

    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        const bell = document.getElementById('notification-bell');
        const dropdown = document.getElementById('notification-dropdown');
        if (bell && dropdown && !bell.contains(e.target) && !dropdown.contains(e.target)) {
            notificationDropdownOpen = false;
            dropdown.classList.add('hidden');
        }
    });

    // Check notifications on load
    document.addEventListener('DOMContentLoaded', () => {
        // Check once initially for badge state
        fetch(baseUrl + 'api.php?action=get_notifications')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.unread_count > 0) {
                const badge = document.getElementById('notification-badge');
                if (badge) badge.classList.remove('hidden');
            }
        });
    });
    </script>



    <!-- Firebase JS SDK & Analytics -->
    <script type="importmap">
      {
        "imports": {
          "firebase/app": "https://www.gstatic.com/firebasejs/12.15.0/firebase-app.js",
          "firebase/analytics": "https://www.gstatic.com/firebasejs/12.15.0/firebase-analytics.js"
        }
      }
    </script>
    <script type="module">
      import { initializeApp } from "firebase/app";
      import { getAnalytics } from "firebase/analytics";

      const firebaseConfig = {
        apiKey: "AIzaSyB-UcbJripzj5BYfXNZzGVGNRvp6fdpzdk",
        authDomain: "loopr-5afff.firebaseapp.com",
        projectId: "loopr-5afff",
        storageBucket: "loopr-5afff.firebasestorage.app",
        messagingSenderId: "461317839365",
        appId: "1:461317839365:web:5a7e62412a085120edda6c",
        measurementId: "G-E64N7NW2HW"
      };

      const app = initializeApp(firebaseConfig);
      const analytics = getAnalytics(app);
    </script>
<script src="assets/js/aac-playback-fix.js"></script>
<?= function_exists("ss_web_sticky") ? ss_web_sticky() : "" ?>
<?= function_exists("ss_web_overlays") ? ss_web_overlays() : "" ?>
</body>
</html>

