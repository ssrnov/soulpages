<?php
require_once 'includes/functions.php';

// Calculate absolute base URL to prevent clean URL relative path 404 bugs
$base_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_dir = rtrim($base_dir, '/') . '/';
$base_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $base_dir;

$slug = $_GET['s'] ?? '';
if (empty($slug)) {
    // Check if clean URL rewrite was used (URI check fallback)
    $uri_parts = explode('/p/', $_SERVER['REQUEST_URI']);
    if (count($uri_parts) > 1) {
        $slug = explode('?', $uri_parts[1])[0];
    }
}

if (empty($slug)) {
    redirect('index.php');
}

// Fetch Page Details
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if ($page) {
    // Dynamic Self-Healing Permissions for Video, Voice, and Slide media assets
    if (!empty($page['video_url'])) {
        $local_video = __DIR__ . '/' . $page['video_url'];
        if (file_exists($local_video) && (fileperms($local_video) & 0777) !== 0644) {
            @chmod($local_video, 0644);
        }
    }
    if (!empty($page['voice_url'])) {
        $local_voice = __DIR__ . '/' . $page['voice_url'];
        if (file_exists($local_voice) && (fileperms($local_voice) & 0777) !== 0644) {
            @chmod($local_voice, 0644);
        }
    }
    
    // Check all slides media
    $s_data = json_decode($page['slide_data'] ?? '', true);
    if (is_array($s_data)) {
        array_walk_recursive($s_data, function($val) {
            if (is_string($val) && strpos($val, 'uploads/') === 0) {
                $local_s_file = __DIR__ . '/' . $val;
                if (file_exists($local_s_file) && (fileperms($local_s_file) & 0777) !== 0644) {
                    @chmod($local_s_file, 0644);
                }
            }
        });
    }
}

$photo_fit_mode = !empty($page['photo_fit_mode']) && in_array($page['photo_fit_mode'], ['cover', 'contain']) ? $page['photo_fit_mode'] : 'cover';

if (!$page) {
    die("<div style='font-family: sans-serif; text-align: center; padding: 50px;'>
        <h2>Card Not Found</h2>
        <p>This special page does not exist or has been removed.</p>
        <a href='<?= $base_url ?>index.php'>Create your own at SoulSync</a>
    </div>");
}

// --- Standalone premium templates (bypass the DB slide engine) ---
if (($page['template'] ?? '') === 'cinematic_birthday') {
    require_once __DIR__ . '/templates/cinematic_birthday.php';
    render_cinematic_birthday($page);
    exit;
}
if (($page['template'] ?? '') === 'proposal_cinematic') {
    require_once __DIR__ . '/templates/proposal_cinematic.php';
    render_proposal_cinematic($page);
    exit;
}
if (($page['template'] ?? '') === 'sorry_cinematic') {
    require_once __DIR__ . '/templates/sorry_cinematic.php';
    render_sorry_cinematic($page);
    exit;
}
if (($page['template'] ?? '') === 'couple_story') {
    require_once __DIR__ . '/templates/couple_story.php';
    render_couple_story($page);
    exit;
}
if (($page['template'] ?? '') === 'festival') {
    redirect('f.php?s=' . urlencode($page['slug']));
}

$is_owner = false;
if (is_logged_in() && ($page['user_id'] == $_SESSION['user_id'] || is_super_admin())) {
    $is_owner = true;
} elseif ($page['guest_session_id'] === session_id()) {
    $is_owner = true;
}

// Expiry Check
$is_expired = false;
if ($page['is_expired'] === 1 || $page['is_expired'] === '1') {
    $is_expired = true;
} elseif (!empty($page['expiry_date']) && strtotime($page['expiry_date']) < time()) {
    $is_expired = true;
}

if ($is_expired) {
    $is_owner = false;
    if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) {
        $is_owner = true;
    } elseif ($page['guest_session_id'] === session_id()) {
        $is_owner = true;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Page Expired - SoulSync</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;600&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; background-color: #0c0a09; color: #f5f5f4; }
            .heading { font-family: 'Outfit', sans-serif; }
        </style>
    </head>
    <body class="flex items-center justify-center min-h-screen px-4">
        <div class="max-w-md w-full text-center bg-zinc-900/60 backdrop-blur-md border border-zinc-800 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <div class="absolute -top-12 -left-12 w-24 h-24 bg-pink-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-12 -right-12 w-24 h-24 bg-rose-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="text-6xl mb-6">⏳</div>
            <h2 class="heading text-2xl font-extrabold text-white mb-2">Memory Archived</h2>
            <p class="text-sm text-zinc-400 mb-6 leading-relaxed">
                This page was created with a default lifespan of 10 days and has reached its expiration date.
            </p>
            
            <?php if ($is_owner): ?>
                <div class="bg-zinc-950/60 border border-zinc-800 rounded-2xl p-4 mb-6">
                    <p class="text-xs text-zinc-400 mb-3">You are the creator of this page. You can extend it for another 10 days using 1 credit.</p>
                    <button id="btn-extend-page" data-id="<?= $page['id'] ?>" class="w-full py-3 bg-pink-600 hover:bg-pink-500 active:scale-[0.98] text-white font-bold text-xs uppercase tracking-wider rounded-xl transition shadow-lg shadow-pink-600/10">
                        Extend by 10 Days
                    </button>
                    <p id="extend-status" class="text-xs font-semibold text-pink-400 mt-2 hidden"></p>
                </div>
                
                <script>
                    document.getElementById('btn-extend-page').addEventListener('click', async function() {
                        const btn = this;
                        const pageId = btn.dataset.id;
                        const status = document.getElementById('extend-status');
                        
                        btn.disabled = true;
                        btn.textContent = 'Extending...';
                        
                        try {
                            const formData = new FormData();
                            formData.append('page_id', pageId);
                            formData.append('days', 10);
                            
                            const res = await fetch('api.php?action=extend_expiry', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await res.json();
                            if (result.success) {
                                status.textContent = 'Extension successful! Reloading...';
                                status.classList.remove('hidden', 'text-red-400');
                                status.classList.add('text-green-400');
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                status.textContent = result.error || 'Failed to extend page.';
                                status.classList.remove('hidden', 'text-green-400');
                                status.classList.add('text-red-400');
                                btn.disabled = false;
                                btn.textContent = 'Extend by 10 Days';
                            }
                        } catch (err) {
                            status.textContent = 'Network error. Try again.';
                            status.classList.remove('hidden', 'text-green-400');
                            status.classList.add('text-red-400');
                            btn.disabled = false;
                            btn.textContent = 'Extend by 10 Days';
                        }
                    });
                </script>
            <?php endif; ?>
            
            <a href="index.php" class="inline-block text-xs font-bold text-pink-500 hover:text-pink-400 transition hover:underline">
                Create a new SoulSync page
            </a>
        </div>
    <script src="<?= $base_url ?>assets/js/aac-play.js"></script>
</body>
    </html>
    <?php
    exit;
}

$creator_referral_code = '';
$creator_profile_photo = '';
if (!empty($page['user_id'])) {
    $c_stmt = $pdo->prepare("SELECT referral_code, profile_photo FROM users WHERE id = ?");
    $c_stmt->execute([$page['user_id']]);
    $creator_row = $c_stmt->fetch();
    if ($creator_row) {
        $creator_referral_code = $creator_row['referral_code'] ?: '';
        $creator_profile_photo = $creator_row['profile_photo'] ?: '';
    }
}

// If visitor is not logged in and creator has a referral code, set the referral cookie/session
if (!is_logged_in() && !empty($creator_referral_code)) {
    if (!isset($_COOKIE['sp_ref'])) {
        setcookie('sp_ref', $creator_referral_code, time() + (30 * 24 * 60 * 60), '/');
    }
    if (!isset($_SESSION['referral_code'])) {
        $_SESSION['referral_code'] = $creator_referral_code;
    }
}

// Check draft visibility
if ($page['status'] === 'draft') {
    $session_id = session_id();
    $is_owner = false;
    if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) {
        $is_owner = true;
    } elseif ($page['guest_session_id'] === $session_id) {
        $is_owner = true;
    }
    
    if (!$is_owner) {
        die("<div style='font-family: sans-serif; text-align: center; padding: 50px;'>
            <h2>Card Draft Incomplete</h2>
            <p>This page is currently a draft and can only be viewed by its creator.</p>
        </div>");
    }
}

// Password Protection Check
$password_required = !empty($page['password']);
$password_verified = false;
if ($password_required) {
    if (isset($_POST['page_password'])) {
        if (password_verify($_POST['page_password'], $page['password'])) {
            $password_verified = true;
            $_SESSION['page_unlocked_' . $page['id']] = true;
        }
    }
    if (isset($_SESSION['page_unlocked_' . $page['id']])) {
        $password_verified = true;
    }
}

// Track page view (only if not password-blocked)
if (!$password_required || $password_verified) {
    track_view($page['id'], $pdo);
}

// Fetch Page Images
$stmt_img = $pdo->prepare("SELECT * FROM page_images WHERE page_id = ? ORDER BY position");
$stmt_img->execute([$page['id']]);
$images = $stmt_img->fetchAll();

// Get category and theme config
$cat_key = $page['category'];
$cat_info = get_category($cat_key);

if (($cat_info['status'] ?? 'enabled') === 'hidden') {
    if (!is_logged_in() || !is_admin()) {
        $base_url = get_setting('site_url', '');
        if (empty($base_url)) {
            $base_url = 'index.php';
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Page Unavailable - SoulSync</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], heading: ['Outfit', 'sans-serif'] } } } }
            </script>
            <style>
                body { background-color: #0f172a; background-image: radial-gradient(at 0% 0%, rgba(236,72,153,0.05) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(59,130,246,0.05) 0px, transparent 50%); }
                .glass-card { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.08); }
            </style>
        </head>
        <body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex items-center justify-center p-4 antialiased">
            <div class="glass-card rounded-3xl p-8 max-w-md w-full text-center shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-red-500 to-pink-500"></div>
                <div class="w-16 h-16 bg-red-500/10 border border-red-500/20 text-red-500 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-6">
                    🔒
                </div>
                <h1 class="text-2xl font-extrabold font-heading text-white mb-3">Page Unavailable</h1>
                <p class="text-slate-400 text-sm leading-relaxed mb-6">This page belongs to a hidden category and is currently unavailable to view.</p>
                <a href="<?= htmlspecialchars($base_url) ?>" class="inline-flex items-center justify-center px-6 py-2.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-xl text-xs font-semibold hover:bg-slate-800 hover:text-white transition duration-200">
                    &larr; Back to Homepage
                </a>
            </div>
        <script src="<?= $base_url ?>assets/js/aac-play.js"></script>
</body>
        </html>
        <?php
        exit;
    }
}

if (($cat_info['status'] ?? 'enabled') === 'maintenance') {
    if (!is_logged_in() || !is_admin()) {
        $base_url = get_setting('site_url', '');
        if (empty($base_url)) {
            $base_url = 'index.php';
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Category Unavailable - SoulSync</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], heading: ['Outfit', 'sans-serif'] } } } }
            </script>
            <style>
                body { background-color: #0f172a; background-image: radial-gradient(at 0% 0%, rgba(236,72,153,0.05) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(59,130,246,0.05) 0px, transparent 50%); }
                .glass-card { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.08); }
            </style>
        </head>
        <body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex items-center justify-center p-4 antialiased">
            <div class="glass-card rounded-3xl p-8 max-w-md w-full text-center shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-amber-500 to-orange-500"></div>
                <div class="w-16 h-16 bg-amber-500/10 border border-amber-500/20 text-amber-500 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-6">
                    🚧
                </div>
                <h1 class="text-2xl font-extrabold font-heading text-white mb-3">Currently Unavailable</h1>
                <p class="text-slate-400 text-sm leading-relaxed mb-6">This category is currently unavailable. Please check again later.</p>
                <a href="<?= htmlspecialchars($base_url) ?>" class="inline-flex items-center justify-center px-6 py-2.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-xl text-xs font-semibold hover:bg-slate-800 hover:text-white transition duration-200">
                    &larr; Back to Homepage
                </a>
            </div>
        <script src="<?= $base_url ?>assets/js/aac-play.js"></script>
</body>
        </html>
        <?php
        exit;
    }
}

$theme_key = $page['theme'] ?? $cat_info['default_theme'] ?? 'romantic';
$theme = get_theme($theme_key);
$slides = $cat_info['slides'] ?? [];
$slide_data = decode_slide_data($page['slide_data'] ?? '');

// Auto-fallback optional premium slides images/video to main photos/video if empty
$premium_slide_keys = [
    'premium_memory_reveal',
    'premium_heart_formation',
    'premium_collage_explosion',
    'premium_floating_memories',
    'premium_star_sky',
    'premium_memory_book',
    'premium_reasons_special',
    'premium_puzzle_reveal',
    'premium_memory_timeline',
    'premium_mosaic_heart',
    'premium_photo_spotlight',
    'premium_our_chats'
];

foreach ($premium_slide_keys as $psk) {
    if (empty($slide_data[$psk . '_images']) && !empty($images)) {
        $s_imgs = [];
        foreach ($images as $img) {
            $s_imgs[] = [
                'original' => $img['image_path'],
                'medium' => $img['medium_path'] ?: $img['image_path'],
                'thumb' => $img['thumb_path'] ?: $img['image_path'],
                'caption' => '',
                'date' => '',
                'memory' => ''
            ];
        }
        $slide_data[$psk . '_images'] = $s_imgs;
    }
    if (empty($slide_data[$psk . '_video']) && !empty($page['video_url'])) {
        $slide_data[$psk . '_video'] = $page['video_url'];
    }
}

$special_date_enabled = !isset($slide_data['special_date_enabled']) || ((int)$slide_data['special_date_enabled'] === 1);
$filtered_slides = [];
if (is_array($slides)) {
    foreach ($slides as $slide) {
        $slide_type = $slide['type'];
        if (in_array($slide_type, ['counter', 'proposal_countdown', 'anniversary_together_counter', 'premium_love_counter']) && !$special_date_enabled) {
            continue;
        }
        $enabled_key = ($slide['key'] ?? $slide['type']) . '_enabled';
        
        // Standard optional slides (enabled by default, can be explicitly disabled via 0)
        if (in_array($slide_type, ['gallery', 'letter', 'voice_message', 'video_message'])) {
            $is_enabled = !isset($slide_data[$enabled_key]) || ((int)$slide_data[$enabled_key] === 1);
            if (!$is_enabled) {
                continue;
            }
        } 
        // Premium optional slides (disabled by default, must be explicitly enabled via 1)
        elseif (!empty($slide['is_optional'])) {
            $is_enabled = isset($slide_data[$enabled_key]) && ((int)$slide_data[$enabled_key] === 1);
            if (!$is_enabled) {
                continue;
            }
        }
        $filtered_slides[] = $slide;
    }
}
$slides = array_values($filtered_slides);
if ((int)($page['interactive_ending'] ?? 1) === 1 && (int)($page['interactive_ask_name'] ?? 1) === 1) {
    array_splice($slides, 1, 0, [['type' => 'visitor_name_input', 'key' => 'visitor_name_prompt', 'title' => 'Before we begin...', 'subtitle' => 'What is your name?']]);
}
$reactions_list = get_reactions();

// Fetch all music library tracks
$stmt_music = $pdo->query("SELECT * FROM music_library ORDER BY title");
$music_list = $stmt_music->fetchAll();

// Font selection
$font_family = $page['font_style'] ?? $theme['font'] ?? 'Outfit';
$font_css = $font_family === 'Playfair Display' ? "'Playfair Display', serif" : ($font_family === 'Outfit' ? "'Outfit', sans-serif" : "'Inter', sans-serif");

// Helper: replace placeholders in text
function rpl($text, $page) {
    $text = str_replace('[receiver]', h($page['receiver_name']), $text);
    $text = str_replace('[sender]', h($page['sender_name']), $text);
    $text = str_replace('[question]', h($page['proposal_question']), $text);
    $text = str_replace('[nickname]', h($page['nickname'] ?? $page['receiver_name']), $text);
    return $text;
}

function renderSlideVoicePlayer($slide_num, $slide_key, $slide_data, $theme, $base_url) {
    global $page, $creator_profile_photo;
    $voice_url = $slide_data[$slide_key . '_voice'] ?? '';
    if (empty($voice_url)) return;
    ?>
    <div class="w-full max-w-sm px-4 mb-6 z-10 text-left">
        <div class="glass-dark border border-white/10 rounded-3xl p-4 space-y-3">
            <div class="flex items-center space-x-3">
                <?php if (!empty($creator_profile_photo) && file_exists($creator_profile_photo)): ?>
                    <img src="<?= h($base_url . $creator_profile_photo) ?>" class="w-10 h-10 rounded-full object-cover border border-white/10 shadow-md">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-pink-500 to-rose-500 flex items-center justify-center font-extrabold text-white text-sm shadow-md uppercase border border-white/10">
                        <?= h(mb_substr($page['sender_name'] ?? 'S', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h4 class="text-xs font-bold text-white"><?= h($page['sender_name'] ?? 'Sny ☀️') ?></h4>
                    <p class="text-[9px] text-slate-400 font-semibold tracking-wide">Voice Message &bull; tap play &rtrif;</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3 bg-black/30 rounded-2xl p-2.5">
                <audio id="slide-voice-element-<?= $slide_num ?>" src="<?= h($base_url . $voice_url) ?>" class="hidden" preload="none"></audio>
                <button type="button" onclick="toggleSlideVoice(<?= $slide_num ?>)" id="slide-voice-play-<?= $slide_num ?>" class="w-9 h-9 rounded-full bg-pink-500 hover:bg-pink-600 active:scale-95 text-white flex items-center justify-center shadow-lg transition flex-shrink-0">
                    <span id="slide-voice-icon-<?= $slide_num ?>" class="text-xs select-none">▶</span>
                </button>
                <div class="flex-grow flex flex-col justify-center">
                    <!-- Audio Waveform Visualizer -->
                    <div class="flex items-center justify-between space-x-0.5 h-6 cursor-pointer opacity-70 hover:opacity-100 transition" onclick="seekSlideVoiceByWaveform(event, <?= $slide_num ?>)">
                        <div class="h-2 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-4 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-3 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-5 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-2 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-4 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-5 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-3 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-6 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-4 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-3 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-5 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-2 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-4 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-5 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-3 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-6 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-4 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-2 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                        <div class="h-3 w-[3px] bg-slate-700 rounded-full slide-voice-bar-<?= $slide_num ?>"></div>
                    </div>
                </div>
                <span id="slide-voice-time-<?= $slide_num ?>" class="text-[9px] font-mono text-slate-400 select-none flex-shrink-0">0:00</span>
            </div>
        </div>
    </div>
    <?php
}

function inCategoryShape($category, $r, $c) {
    // 10x10 Matrix mapping
    // Heart Shape
    $heart = [
        [0,0,1,1,0,0,1,1,0,0],
        [0,1,1,1,1,1,1,1,1,0],
        [1,1,1,1,1,1,1,1,1,1],
        [1,1,1,1,1,1,1,1,1,1],
        [1,1,1,1,1,1,1,1,1,1],
        [0,1,1,1,1,1,1,1,1,0],
        [0,0,1,1,1,1,1,1,0,0],
        [0,0,0,1,1,1,1,0,0,0],
        [0,0,0,0,1,1,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0]
    ];
    
    // Cake Shape
    $cake = [
        [0,0,0,0,1,1,0,0,0,0],
        [0,0,0,0,1,1,0,0,0,0],
        [0,0,1,1,1,1,1,1,0,0],
        [0,1,1,1,1,1,1,1,1,0],
        [0,1,1,1,1,1,1,1,1,0],
        [1,1,1,1,1,1,1,1,1,1],
        [1,1,1,1,1,1,1,1,1,1],
        [1,1,1,1,1,1,1,1,1,1],
        [0,1,1,1,1,1,1,1,1,0],
        [0,0,0,0,0,0,0,0,0,0]
    ];

    // Sad face
    $face = [
        [0,0,1,1,1,1,1,1,0,0],
        [0,1,1,1,1,1,1,1,1,0],
        [1,1,0,0,1,1,0,0,1,1],
        [1,1,0,0,1,1,0,0,1,1],
        [1,1,1,1,1,1,1,1,1,1],
        [1,1,1,1,0,0,1,1,1,1],
        [1,1,1,0,1,1,0,1,1,1],
        [0,1,1,1,1,1,1,1,1,0],
        [0,0,1,1,1,1,1,1,0,0],
        [0,0,0,0,0,0,0,0,0,0]
    ];

    // Star Shape
    $star = [
        [0,0,0,0,1,1,0,0,0,0],
        [0,0,0,1,1,1,1,0,0,0],
        [0,0,0,1,1,1,1,0,0,0],
        [1,1,1,1,1,1,1,1,1,1],
        [0,1,1,1,1,1,1,1,1,0],
        [0,0,1,1,1,1,1,1,0,0],
        [0,0,1,1,1,1,1,1,0,0],
        [0,1,1,1,0,0,1,1,1,0],
        [1,1,1,0,0,0,0,1,1,1],
        [0,0,0,0,0,0,0,0,0,0]
    ];

    $shape = $heart;
    if (in_array($category, ['birthday', 'surprise'])) {
        $shape = $cake;
    } elseif (in_array($category, ['sorry', 'patchup', 'mana_lo'])) {
        $shape = $face;
    } elseif (in_array($category, ['friendship', 'miss_you', 'congratulations'])) {
        $shape = $star;
    }
    
    return isset($shape[$r][$c]) && $shape[$r][$c] === 1;
}

// Is dark theme?
$is_dark = in_array($theme_key, ['dark', 'galaxy']);

// Compute OpenGraph Image
$og_image = '';
$default_og = get_setting('og_image_url', '');

// Use first page image if available
if (!empty($images) && !empty($images[0]['image_path'])) {
    $first_image = $images[0]['image_path'];
    if (strpos($first_image, 'http') === 0) {
        $og_image = $first_image;
    } else {
        $first_image = ltrim($first_image, '/');
        $og_image = SITE_URL . '/' . $first_image;
    }
}

// Fallback to default global site OG Image
if (empty($og_image) && !empty($default_og)) {
    if (strpos($default_og, 'http') === 0) {
        $og_image = $default_og;
    } else {
        $default_og = ltrim($default_og, '/');
        $og_image = SITE_URL . '/' . $default_og;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page['title']) ?></title>
    <!-- OpenGraph (OG) Meta Tags for Social Sharing -->
    <meta property="og:title" content="<?= h($page['title']) ?>">
    <meta property="og:description" content="A special surprise page created just for <?= h($page['receiver_name']) ?>.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h(SITE_URL . '/p.php?s=' . $page['slug']) ?>">
    <?php if (!empty($og_image)): ?>
        <meta property="og:image" content="<?= h($og_image) ?>">
    <?php endif; ?>
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($page['title']) ?>">
    <meta name="twitter:description" content="A special surprise page created just for <?= h($page['receiver_name']) ?>.">
    <?php if (!empty($og_image)): ?>
        <meta name="twitter:image" content="<?= h($og_image) ?>">
    <?php endif; ?>
    <meta name="description" content="A special surprise page created just for <?= h($page['receiver_name']) ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@400;600;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <!-- SoulSync Performance Scoring -->
    <script src="assets/js/performance.js"></script>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Canvas Confetti -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                        serif: ['Playfair Display', 'serif'],
                    }
                }
            }
        }
    </script>
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        html, body {
            overscroll-behavior-y: none;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        /* Mobile: lock scroll to story container */
        @media (max-width: 767px) {
            html, body {
                overflow: hidden;
                height: 100%;
                height: -webkit-fill-available;
            }
        }
        /* Desktop: allow natural scroll */
        @media (min-width: 768px) {
            html, body {
                overflow: auto;
                min-height: 100vh;
            }
        }
        body {
            font-family: <?= $font_css ?>;
            background-color: #14001a;
        }
        /* Desktop: beautiful dark gradient background behind phone frame */
        @media (min-width: 768px) {
            body {
                background: linear-gradient(135deg, #14001a 0%, #23002b 50%, #3a0046 100%) !important;
                display: flex;
                align-items: flex-start;
                justify-content: center;
                padding: 40px 20px;
                gap: 32px;
            }
        }


        /* Performance mode overrides */
        .disable-blur .glass, .disable-blur .glass-dark {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            background: rgba(255, 255, 255, 0.95);
        }
        .disable-blur .glass-dark {
            background: rgba(18, 18, 18, 0.95);
        }
        .disable-shadows * {
            box-shadow: none !important;
            text-shadow: none !important;
        }
        .reduced-motion * {
            animation-delay: 0s !important;
            animation-duration: 0s !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0s !important;
            scroll-behavior: auto !important;
        }
        .slide-pane {
            will-change: transform, opacity;
            contain: layout style paint;
        }
        .particle {
            will-change: transform;
        }
        .story-container {
            max-width: 430px;
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }
        /* Desktop: phone-frame presentation */
        @media (min-width: 768px) {
            .story-container {
                max-width: 430px;
                width: 430px;
                flex-shrink: 0;
                height: calc(100vh - 80px);
                min-height: 700px;
                max-height: 900px;
                border-radius: 44px;
                border: 12px solid #1c1917; /* Space black/charcoal frame */
                background-color: #0c0a09 !important; /* Dark screen backing */
                box-shadow:
                    0 0 0 2px rgba(255,255,255,0.05), /* Outer highlight */
                    0 25px 60px rgba(0,0,0,0.8),       /* Outer depth shadow */
                    0 0 100px rgba(236,72,153,0.15);   /* Purple ambient backing glow */
                overflow: hidden;
                margin: 0;
            }
        }
        /* Removed ad padding-bottom to keep slides full-view */

        /* ═══ DESKTOP SIDE PANEL ═══ */
        #desktop-side-panel {
            display: none;
        }
        @media (min-width: 768px) {
            #desktop-side-panel {
                display: flex;
                flex-direction: column;
                gap: 20px;
                width: 280px;
                flex-shrink: 0;
                padding-top: 24px;
                align-self: flex-start;
                position: sticky;
                top: 40px;
            }
            #desktop-side-panel .side-card {
                background: rgba(255,255,255,0.04);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(255,255,255,0.08);
                border-radius: 20px;
                padding: 20px;
            }
        }

        /* Premium Slides Custom Styling */
        .perspective-1200 {
            perspective: 1200px;
            -webkit-perspective: 1200px;
        }
        .transform-style-preserve-3d {
            transform-style: preserve-3d;
            -webkit-transform-style: preserve-3d;
        }
        .backface-hidden {
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }
        .animate-twinkle {
            animation: premium-twinkle 1.8s infinite ease-in-out alternate;
        }
        @keyframes premium-twinkle {
            0% { opacity: 0.35; transform: scale(0.85); box-shadow: 0 0 4px #fff; }
            100% { opacity: 1; transform: scale(1.15); box-shadow: 0 0 14px #fff; }
        }
        .animate-drift {
            animation-name: premium-drift;
            animation-iteration-count: infinite;
            animation-timing-function: linear;
        }
        @keyframes premium-drift {
            0% {
                transform: translateY(0) rotate(0deg) translateX(0);
                opacity: 0;
            }
            10% { opacity: 0.85; }
            90% { opacity: 0.85; }
            100% {
                transform: translateY(-700px) rotate(360deg) translateX(30px);
                opacity: 0;
            }
        }
        .timeline-scroll-container::-webkit-scrollbar {
            width: 4px;
        }
        .timeline-scroll-container::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
        }
        .timeline-scroll-container::-webkit-scrollbar-thumb {
            background: rgba(236,72,153,0.3);
            border-radius: 10px;
        }
        .typing-cursor::after {
            content: '|';
            animation: cursor-pulse 0.8s infinite;
        }
        @keyframes cursor-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }


        /* Image Redesign Animations */
        @keyframes premium-image-entrance {
            0% {
                filter: blur(15px);
                transform: scale(0.9);
                opacity: 0;
            }
            35% {
                filter: blur(8px);
                transform: scale(1.05);
                opacity: 0.5;
            }
            70% {
                filter: blur(0px);
                transform: scale(1);
                opacity: 1;
            }
            100% {
                filter: blur(0px);
                transform: scale(1) translateY(-4px); /* Stage 4: Float */
                opacity: 1;
            }
        }
        @keyframes continuous-float {
            0%, 100% { transform: translateY(-4px); }
            50% { transform: translateY(2px); }
        }
        .live-photo-entrance {
            animation: premium-image-entrance 1.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards,
                       continuous-float 4s ease-in-out infinite 1.8s;
        }

        /* ═══ Phase 8 Immersive Animations ═══ */
        @keyframes premium-blur-sharp {
            0% {
                filter: blur(25px);
                transform: scale(0.93);
                opacity: 0;
            }
            35% {
                filter: blur(12px);
                transform: scale(1.03);
                opacity: 0.75;
            }
            70% {
                filter: blur(0px);
                transform: scale(1.05);
                opacity: 1;
            }
            100% {
                filter: blur(0px);
                transform: scale(1.05);
                opacity: 1;
            }
        }
        @keyframes continuous-float-premium {
            0%, 100% {
                transform: scale(1.05) translateY(0px) rotate(0deg);
            }
            50% {
                transform: scale(1.05) translateY(-8px) rotate(0.5deg);
            }
        }
        .premium-photo-anim {
            animation: premium-blur-sharp 3.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards,
                       continuous-float-premium 6s ease-in-out infinite 3.5s;
        }
        @keyframes premium-caption-fade {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .premium-caption-anim {
            opacity: 0;
            animation: premium-caption-fade 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            animation-delay: 2.5s;
        }

        /* Gallery Layouts */
        .gallery-hero-container {
            position: relative;
            width: 100%;
            height: 60vh;
            height: 60dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 2rem;
        }
        .gallery-hero-blur-bg {
            position: absolute;
            inset: -20px;
            background-size: cover;
            background-position: center;
            filter: blur(25px) brightness(0.35);
            opacity: 0.9;
            z-index: 0;
            transform: scale(1.1);
        }
        .gallery-hero-wrapper {
            position: relative;
            z-index: 10;
            width: 90%;
            height: 90%;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .gallery-hero-overlay-caption {
            position: absolute;
            bottom: 0;
            inset-x: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.4) 60%, transparent 100%);
            padding: 1.25rem 1rem;
            text-align: center;
            color: #fff;
            z-index: 20;
        }
        .gallery-split-container {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            width: 100%;
            height: 100%;
            justify-content: center;
            padding: 0.25rem;
        }
        .gallery-split-card {
            position: relative;
            width: 100%;
            height: 27vh;
            height: 27dvh;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .gallery-collage-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            width: 100%;
            height: 100%;
            align-content: center;
            padding: 0.25rem;
        }
        .gallery-collage-card {
            position: relative;
            border-radius: 1.25rem;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            aspect-ratio: 1;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .gallery-collage-card.span-two {
            grid-column: span 2;
            aspect-ratio: 16/10;
        }

        /* Local Voice Note Background Particles */
        @keyframes voiceFallLocal {
            0% { transform: translateY(-10%) rotate(0deg) scale(var(--sc, 1)); opacity: 0; }
            10% { opacity: var(--op, 0.6); }
            90% { opacity: var(--op, 0.6); }
            100% { transform: translateY(110%) rotate(360deg) scale(var(--sc, 1)); opacity: 0; }
        }
        @keyframes voiceFloatLocal {
            0% { transform: translateY(110%) rotate(0deg) scale(var(--sc, 1)); opacity: 0; }
            10% { opacity: var(--op, 0.6); }
            90% { opacity: var(--op, 0.6); }
            100% { transform: translateY(-10%) rotate(360deg) scale(var(--sc, 1)); opacity: 0; }
        }
        @keyframes voiceTwinkleLocal {
            0%, 100% { opacity: 0.15; transform: scale(0.8) rotate(0deg); }
            50% { opacity: 0.85; transform: scale(1.2) rotate(180deg); }
        }
        .voice-local-particle {
            position: absolute;
            pointer-events: none;
            z-index: 1;
            will-change: transform, opacity;
        }
        .voice-local-particle.falling {
            animation: voiceFallLocal var(--dur, 6s) linear infinite;
        }
        .voice-local-particle.floating-up {
            animation: voiceFloatLocal var(--dur, 6s) linear infinite;
        }
        .voice-local-particle.twinkling {
            animation: voiceTwinkleLocal var(--dur, 4s) ease-in-out infinite;
        }

        /* Video custom container & transitions */
        .video-container-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 1rem 0;
            perspective: 1200px;
        }
        .video-poster-cover {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            z-index: 15;
            transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .play-btn-glow {
            animation: playGlow 2s infinite ease-in-out;
            z-index: 16;
        }
        @keyframes playGlow {
            0%, 100% { box-shadow: 0 0 15px rgba(236, 72, 153, 0.6), 0 0 30px rgba(236, 72, 153, 0.4); }
            50% { box-shadow: 0 0 25px rgba(236, 72, 153, 0.9), 0 0 50px rgba(236, 72, 153, 0.6); }
        }


        /* Particle animations */
        .particle {
            position: fixed;
            bottom: -60px;
            animation: floatUp var(--dur, 8s) linear infinite;
            pointer-events: none;
            z-index: 1;
            opacity: 0;
        }
        #particle-container.slow-particles .particle {
            animation-duration: calc(var(--dur, 8s) * 2.5) !important;
        }
        @keyframes floatUp {
            0%   { transform: translateY(0) rotate(0deg) scale(var(--sc,1)); opacity: 0; }
            8%   { opacity: 0.6; }
            90%  { opacity: 0.5; }
            100% { transform: translateY(-110vh) rotate(360deg) scale(var(--sc,1)); opacity: 0; }
        }

        /* Slide System */
        #story-body {
            position: relative;
            overflow: hidden;
        }
        .slide-pane {
            display: none;
            opacity: 0;
            width: 100%;
            height: calc(var(--vh, 1vh) * 100);
            overflow: hidden;
            box-sizing: border-box;
            position: relative;
            padding-top: max(1.5rem, env(safe-area-inset-top));
            padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
            padding-left: max(1.5rem, env(safe-area-inset-left));
            padding-right: max(1.5rem, env(safe-area-inset-right));
        }
        @media (min-width: 640px) {
            .slide-pane {
                padding-top: max(2rem, env(safe-area-inset-top));
                padding-bottom: max(2rem, env(safe-area-inset-bottom));
                padding-left: max(2rem, env(safe-area-inset-left));
                padding-right: max(2rem, env(safe-area-inset-right));
            }
        }
        @media (min-width: 768px) {
            .slide-pane {
                height: 100% !important;
            }
        }
        .slide-pane.active {
            display: flex;
            opacity: 1;
        }
        .slide-pane.transitioning {
            display: flex !important;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 10;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        .slide-content-wrapper {
            width: 100%;
            height: 100%;
            max-width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
            transform-origin: top center;
            will-change: transform;
        }
        @media (min-width: 768px) {
            .slide-content-wrapper.premium-wide-wrapper {
                max-width: 100% !important;
            }
        }

        /* --- Transitions --- */
        /* 1. Fade */
        .enter-fade { animation: fadeIn 0.75s ease-in-out forwards; }
        .exit-fade { animation: fadeOut 0.75s ease-in-out forwards; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }

        /* 2. Slide */
        .enter-slide { animation: slideInRight 0.75s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .exit-slide { animation: slideOutLeft 0.75s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }
        @keyframes slideOutLeft { from { transform: translateX(0); } to { transform: translateX(-100%); } }

        /* 3. Zoom */
        .enter-zoom { animation: zoomIn 0.75s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
        .exit-zoom { animation: zoomOut 0.75s ease-in forwards; }
        @keyframes zoomIn { from { opacity: 0; transform: scale(0.85); } to { opacity: 1; transform: scale(1); } }
        @keyframes zoomOut { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(1.15); } }

        /* 4. Parallax */
        .enter-parallax { animation: parallaxIn 0.75s cubic-bezier(0.16, 1, 0.3, 1) forwards; z-index: 20 !important; }
        .exit-parallax { animation: parallaxOut 0.75s cubic-bezier(0.16, 1, 0.3, 1) forwards; z-index: 5 !important; }
        @keyframes parallaxIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        @keyframes parallaxOut { from { transform: translateX(0); } to { transform: translateX(-30%); opacity: 0.6; } }

        /* 5. Flip */
        .story-container { perspective: 1200px; }
        .enter-flip { animation: flipIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
        .exit-flip { animation: flipOut 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
        @keyframes flipIn { from { transform: rotateY(-180deg); opacity: 0; } to { transform: rotateY(0deg); opacity: 1; } }
        @keyframes flipOut { from { transform: rotateY(0deg); opacity: 1; } to { transform: rotateY(180deg); opacity: 0; } }

        /* 6. Storybook Page Turn */
        .enter-pageturn { animation: pageTurnIn 0.85s cubic-bezier(0.25, 1, 0.5, 1) forwards; transform-origin: left center; z-index: 20 !important; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
        .exit-pageturn { animation: pageTurnOut 0.85s cubic-bezier(0.25, 1, 0.5, 1) forwards; transform-origin: left center; z-index: 5 !important; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
        @keyframes pageTurnIn { from { transform: rotateY(-90deg); opacity: 0; } to { transform: rotateY(0deg); opacity: 1; } }
        @keyframes pageTurnOut { from { transform: rotateY(0deg); opacity: 1; } to { transform: rotateY(90deg); opacity: 0; } }

        /* Typewriter cursor */
        .typewriter-cursor::after {
            content: '|';
            animation: blink 1s step-end infinite;
            color: <?= $theme['accent'] ?>;
        }
        @keyframes blink { 50% { opacity: 0; } }

        /* Heartbeat */
        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            15% { transform: scale(1.15); }
            30% { transform: scale(1); }
            45% { transform: scale(1.1); }
        }
        .animate-heartbeat { animation: heartbeat 1.4s ease-in-out infinite; }

        /* Countdown pulse */
        @keyframes countPulse {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Gift box shake */
        @keyframes giftShake {
            0%, 100% { transform: rotate(0deg); }
            10% { transform: rotate(-8deg); }
            20% { transform: rotate(8deg); }
            30% { transform: rotate(-6deg); }
            40% { transform: rotate(6deg); }
            50% { transform: rotate(0deg); }
        }
        .gift-shake { animation: giftShake 0.8s ease-in-out infinite; }
        .gift-opened { animation: none; transform: scale(1.2); transition: all 0.5s; }

        /* Scrollbar hide */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Glass effect */
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .glass-dark { background: rgba(0,0,0,0.3); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.08); }

        /* Card entrance stagger */
        .card-enter { opacity: 0; transform: translateY(20px); }
        .card-enter.visible { opacity: 1; transform: translateY(0); transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); }

        /* Counter digits */
        .counter-digit {
            display: inline-flex; align-items: center; justify-content: center;
            width: 56px; height: 72px; border-radius: 16px; font-weight: 800; font-size: 28px;
            background: <?= $is_dark ? 'rgba(255,255,255,0.06)' : 'rgba(255,255,255,0.6)' ?>;
            border: 1px solid <?= $is_dark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.06)' ?>;
        }

        /* 3D Flip Card & Interactive Styles */
        .perspective-container { perspective: 1000px; }
        .card-flipper { transform-style: preserve-3d; }
        .backface-hidden { backface-visibility: hidden; -webkit-backface-visibility: hidden; }
        .balloon { transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes floatSine {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(3deg); }
        }
        .balloon-area .balloon { animation: floatSine 4s ease-in-out infinite; }
        .rose-petals { transition: transform 1s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .petal { transition: transform 1s ease-out; }
        .animate-spin-slow { animation: spin 12s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* --- Skeleton loader --- */
        .skeleton-loader {
            position: relative;
            overflow: hidden;
            background-color: rgba(156, 163, 175, 0.15);
        }
        .skeleton-loader::after {
            content: "";
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            transform: translateX(-100%);
            background-image: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.1) 20%,
                rgba(255, 255, 255, 0.25) 60%,
                rgba(255, 255, 255, 0) 100%
            );
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }

        /* --- Custom Video Player --- */
        .custom-video-player {
            opacity: 0;
            transform: scale(0.92);
            transition: all 0.75s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 0 0 rgba(236, 72, 153, 0);
        }
        .custom-video-player.video-revealed {
            opacity: 1;
            transform: scale(1);
            box-shadow: 0 0 25px rgba(236, 72, 153, 0.45);
        }
        .custom-video-player.video-playing {
            transform: scale(1.02);
            box-shadow: 0 0 35px rgba(236, 72, 153, 0.6);
        }
        .custom-video-player.video-blurred video {
            filter: blur(12px);
            transform: scale(0.98);
        }
        .custom-video-player video {
            transition: filter 0.6s ease, transform 0.6s ease;
        }
        @keyframes play-glow-pulse {
            0%, 100% {
                box-shadow: 0 0 15px rgba(236, 72, 153, 0.6);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 30px rgba(236, 72, 153, 0.9);
                transform: scale(1.08);
            }
        }
        .play-btn-glow {
            animation: play-glow-pulse 2s infinite ease-in-out;
        }
        .custom-video-player .play-btn-overlay {
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s;
        }
        .custom-video-player.video-revealed .play-btn-overlay {
            opacity: 1;
            transform: scale(1);
        }

        /* --- Floating Music Widget --- */
        #music-widget {
            transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1), height 0.4s cubic-bezier(0.16, 1, 0.3, 1), border-radius 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        #music-widget.expanded {
            width: 260px;
            height: 260px;
            border-radius: 24px;
        }
        #music-widget.expanded #music-collapsed-view {
            display: none;
        }
        #music-widget.expanded #music-expanded-view {
            opacity: 1;
            pointer-events: auto;
        }



        /* --- Video poster cover --- */
        .video-poster-cover {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            z-index: 15;
            transition: opacity 0.5s ease;
            border-radius: inherit;
        }
        .video-poster-cover.opacity-0 {
            opacity: 0;
            pointer-events: none;
        }


        @keyframes floatSlow {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-12px) rotate(3deg); }
        }
        .float-memory-img {
            animation: floatSlow var(--dur, 6s) ease-in-out infinite;
            animation-delay: var(--delay, 0s);
            transition: transform 0.3s;
        }
        .float-memory-img:hover {
            transform: scale(1.05) !important;
            z-index: 40 !important;
        }
        .polaroid-drop-card {
            opacity: 0;
            transform: translateY(-150%) rotate(var(--rot, 0deg));
            transition: transform 1.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 1.2s;
        }
        .polaroid-drop-card.dropped {
            opacity: 1;
            transform: translateY(0) rotate(var(--rot, 0deg));
        }
        .scrapbook-card {
            opacity: 0;
            transform: scale(0.5) rotate(var(--rot, 0deg));
            transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.8s;
        }
        .scrapbook-card.revealed {
            opacity: 1;
            transform: scale(1) rotate(var(--rot, 0deg));
        }
        .scrapbook-tape {
            background: rgba(254, 240, 138, 0.45);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transform: rotate(var(--tape-rot, -15deg));
        }
        .cinematic-carousel-img {
            transition: transform 8s linear, opacity 1s ease-in-out, filter 1s ease-in-out;
            transform: scale(1.15);
            filter: blur(15px);
            opacity: 0;
        }
        .cinematic-carousel-img.active {
            transform: scale(1.0);
            filter: blur(0);
            opacity: 1;
        }
        .wall-card {
            opacity: 0;
            transform: scale(0.7) rotateY(90deg);
            transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.8s;
        }
        .wall-card.revealed {
            opacity: 1;
            transform: scale(1) rotateY(0deg);
        }

        /* --- Category-Specific Photo Animations --- */
        .balloon-photo-card {
            opacity: 0;
            transform: translateY(120vh);
            transition: transform 3s cubic-bezier(0.1, 0.8, 0.2, 1), opacity 2s;
        }
        .balloon-photo-card.lifted {
            opacity: 1;
            transform: translateY(0);
        }
        .proposal-photo-card {
            opacity: 0;
            transform: scale(0.2) rotate(-20deg);
            transition: transform 1s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 1s;
        }
        .proposal-photo-card.revealed {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }
        .sorry-photo-card {
            opacity: 0;
            filter: blur(25px);
            transform: scale(0.9);
            transition: filter 2s ease-out, transform 1.5s cubic-bezier(0.16, 1, 0.3, 1), opacity 1.5s;
        }
        .sorry-photo-card.wiped {
            opacity: 1;
            filter: blur(0px);
            transform: scale(1);
        }
        .raindrop-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.1);
            pointer-events: none;
        }
        .timeline-line {
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 4px;
            transform: translateX(-50%);
            background: linear-gradient(to bottom, transparent, var(--accent, #e11d48) 15%, var(--accent, #e11d48) 85%, transparent);
            opacity: 0;
            transition: opacity 1.5s;
        }
        .timeline-line.visible { opacity: 0.8; }
        .timeline-node {
            opacity: 0;
            transform: scale(0);
            transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .timeline-node.revealed {
            opacity: 1;
            transform: scale(1);
        }
        .timeline-photo-left {
            opacity: 0;
            transform: translateX(-60px);
            transition: all 1s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .timeline-photo-right {
            opacity: 0;
            transform: translateX(60px);
            transition: all 1s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .timeline-photo-left.revealed, .timeline-photo-right.revealed {
            opacity: 1;
            transform: translateX(0);
        }
        .friendship-photo-card {
            opacity: 1;
            transform: translate(0, 0) scale(1) rotate(var(--rot, 0deg));
            transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1), z-index 0.6s step-end;
        }
        .friendship-photo-card.thrown {
            transform: translate(140%, 40px) scale(0.9) rotate(35deg);
            z-index: 5 !important;
        }
        .friendship-sticker {
            position: absolute;
            transform: rotate(var(--sticker-rot, 0deg));
            font-size: 11px;
            font-weight: 800;
            padding: 3px 6px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .missyou-star {
            cursor: pointer;
            transition: transform 0.3s, filter 0.3s;
        }
        .missyou-star:hover {
            transform: scale(1.2);
            filter: drop-shadow(0 0 8px #fef08a);
        }
        .missyou-photo-card {
            opacity: 0;
            transform: scale(0.5);
            filter: blur(10px);
            transition: all 1s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .missyou-photo-card.revealed {
            opacity: 1;
            transform: scale(1);
            filter: blur(0);
        }

        /* Lightbox modal */
        #photo-modal {
            backdrop-filter: blur(8px);
        }

        /* ═══ GALLERY CONTAINER SIZING FIXES ═══ */
        /* Hero - single image fills the space */
        .gallery-hero-container {
            width: 100%;
            height: 100%;
            min-height: 52vh;
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        .gallery-hero-blur-bg {
            position: absolute;
            inset: -20px;
            background-size: cover;
            background-position: center;
            filter: blur(20px);
            transform: scale(1.1);
            opacity: 0.5;
        }
        .gallery-hero-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 100%;
            min-height: 52vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gallery-hero-wrapper img {
            width: 100%;
            height: 100%;
            min-height: 52vh;
            object-fit: cover;
            border-radius: 24px;
        }
        /* ═══ PREMIUM UNIFIED GALLERY SYSTEM ═══ */
        .premium-gallery-card {
            position: relative;
            border-radius: 1.5rem;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            opacity: 0;
            animation: premium-gallery-entrance 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards, 
                       premium-gallery-float 5s ease-in-out infinite 1.4s;
        }

        .premium-gallery-card:hover {
            transform: translateY(-8px) scale(1.03) !important;
            box-shadow: 0 20px 40px rgba(244, 63, 94, 0.25), 0 0 15px rgba(244, 63, 94, 0.1);
            border-color: rgba(244, 63, 94, 0.3);
            z-index: 99 !important;
        }

        /* Aspect classification helpers */
        .premium-gallery-card.is-portrait::before,
        .premium-gallery-card.is-landscape::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.35); /* Contrast tint overlay */
            backdrop-filter: blur(12px) saturate(120%);
            z-index: 1;
        }

        .premium-gallery-card.is-portrait img,
        .premium-gallery-card.is-landscape img {
            position: relative;
            z-index: 2;
        }

        /* 1 Photo Layout: Full Screen Hero Card */
        .premium-gallery-card.hero-card {
            width: 95%;
            height: 55vh;
            max-height: 55dvh;
            min-height: 300px;
            margin: auto;
        }

        /* 2 Photos Layout: Split height stacked cards */
        .premium-gallery-card.stacked-card {
            width: 100%;
            height: 26vh;
            max-height: 26dvh;
            min-height: 180px;
        }

        /* Gallery viewport constraint */
        .gallery-style-wrapper {
            max-height: calc(100vh - 180px);
            max-height: calc(100dvh - 180px);
        }

        /* 3 Photos Layout: Magazine Featured Collage */
        .premium-gallery-card.magazine-featured-card {
            height: 100%;
            min-height: 280px;
        }
        .premium-gallery-card.magazine-supporting-card {
            height: 100%;
            min-height: 130px;
        }

        /* 4 Photos Layout: Balanced 2x2 Grid */
        .premium-gallery-card.grid-card {
            width: 100%;
            aspect-ratio: 1;
            min-height: 160px;
        }

        /* Masonry Layout */
        .premium-gallery-card.masonry-card {
            display: inline-block;
            width: 100%;
            min-height: 180px;
        }

        /* Scrapbook Layout */
        .premium-gallery-card.scrapbook-wall-card {
            width: 170px;
            height: 170px;
            transform: rotate(var(--rot, 0deg));
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        @media (min-width: 640px) {
            .premium-gallery-card.scrapbook-wall-card {
                width: 220px;
                height: 220px;
            }
        }

        /* Animations */
        @keyframes premium-gallery-entrance {
            0% {
                opacity: 0;
                transform: translateY(30px) scale(0.9) rotate(var(--rot, 0deg));
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1) rotate(var(--rot, 0deg));
            }
        }

        @keyframes premium-gallery-float {
            0%, 100% {
                transform: translateY(0) rotate(var(--rot, 0deg));
            }
            50% {
                transform: translateY(-8px) rotate(calc(var(--rot, 0deg) + 1.5deg));
            }
        }

        /* Background Particle styles */
        .birthday-bg-particle,
        .premium-bg-particle {
            position: absolute;
            pointer-events: none;
            opacity: 0.8;
            will-change: transform, opacity;
            z-index: 0;
        }

        /* Balloon/Particle rise animation */
        @keyframes balloon-rise {
            0% {
                transform: translateY(105vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.8;
            }
            90% {
                opacity: 0.8;
            }
            100% {
                transform: translateY(-20vh) translateX(var(--drift, 20px)) rotate(var(--rot, 15deg));
                opacity: 0;
            }
        }

        /* Golden Sparkle glow animation */
        @keyframes golden-sparkle {
            0%, 100% {
                transform: scale(0.5);
                opacity: 0.1;
            }
            50% {
                transform: scale(1.2);
                opacity: 0.8;
                text-shadow: 0 0 10px rgba(253, 224, 71, 0.8);
            }
        }
        
        /* Hearts rise animation */
        @keyframes heart-drift {
            0% {
                transform: translateY(105vh) scale(0.6);
                opacity: 0;
            }
            20% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-10vh) scale(1.1) translateX(var(--drift, -20px));
                opacity: 0;
            }
        }

        /* Falling Animation Keyframes (For sorry/tears) */
        @keyframes item-fall {
            0% {
                transform: translateY(-10vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.7;
            }
            90% {
                opacity: 0.7;
            }
            100% {
                transform: translateY(110vh) translateX(var(--drift, 20px)) rotate(var(--rot, 15deg));
                opacity: 0;
            }
        }

        /* Split - 2 images side by side */
        .gallery-split-container {
            display: flex;
            gap: 10px;
            width: 100%;
            height: 54vh;
            min-height: 300px;
        }
        .gallery-split-container .split-photo-card {
            flex: 1;
            border-radius: 20px;
            overflow: hidden;
            min-height: 300px;
        }
        .gallery-split-container .split-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        /* Collage - 3-4 images in a rich grid */
        .gallery-collage-container {
            display: grid;
            gap: 8px;
            width: 100%;
            min-height: 54vh;
        }
        .gallery-collage-container.collage-3 {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
        }
        .gallery-collage-container.collage-3 .collage-photo-card:first-child {
            grid-column: 1 / -1;
        }
        .gallery-collage-container.collage-4 {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
        }
        .gallery-collage-container .collage-photo-card {
            border-radius: 16px;
            overflow: hidden;
            min-height: 160px;
        }
        .gallery-collage-container .collage-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        /* Wall - grid style, bigger cells */
        .gallery-render-area.grid-cols-2 {
            gap: 8px !important;
            padding: 4px !important;
        }
        .wall-card img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 14px;
        }
        /* Polaroid / Friendship - bigger wrapper */
        .gallery-polaroid-wrapper {
            position: relative;
            width: min(85vw, 340px);
            height: 50vh;
            min-height: 300px;
        }
        @media (min-width: 768px) {
            .gallery-polaroid-wrapper {
                width: 340px;
                height: 420px;
            }
        }



        /* Particles rising for proposal & raindrop animation */
        @keyframes heartRise {
            0% { transform: translateY(0) scale(0.6); opacity: 0; }
            10% { opacity: 0.8; }
            90% { opacity: 0.8; }
            100% { transform: translateY(-300px) translateX(var(--drift, 20px)) scale(1.2); opacity: 0; }
        }
        @keyframes rainFall {
            from { transform: translateY(0); }
            to { transform: translateY(350px); }
        }
        
        /* Cake cinematic animations */
        @keyframes cakeBounce {
            0%, 100% { transform: scale(1.08) translateY(0); }
            50% { transform: scale(1.08) translateY(-8px); }
        }
        .animate-cake-bounce {
            animation: cakeBounce 3s ease-in-out infinite;
        }

        /* 🌹 PROPOSAL CINEMATIC ANIMATIONS CSS */
        .cinematic-overlay {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            background-color: #000;
            z-index: 100;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            transition: opacity 0.8s ease-in-out;
        }

        /* 1. Rose Bloom */
        @keyframes rosePetalUnfold {
            0% { transform: scale(0.3) rotate(0deg); opacity: 0; }
            40% { opacity: 1; }
            100% { transform: scale(1) rotate(var(--petal-rotation)); opacity: 1; }
        }
        @keyframes roseGlow {
            0%, 100% { filter: drop-shadow(0 0 15px rgba(244,63,94,0.3)); }
            50% { filter: drop-shadow(0 0 35px rgba(244,63,94,0.6)); }
        }
        @keyframes roseParticleFloat {
            0% { transform: translate(0,0) scale(1); opacity: 0.8; }
            100% { transform: translate(var(--px), var(--py)) scale(0); opacity: 0; }
        }
        .rose-petal-anim {
            animation: rosePetalUnfold 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
        .rose-glow-pulse {
            animation: roseGlow 3s ease-in-out infinite;
        }

        /* 2. Constellation & 3. Heart Formation & 8. Final Proposal Canvas */
        .cinematic-canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
        }
        .polaroid-constellation {
            position: absolute;
            background: white;
            padding: 8px 8px 24px 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border-radius: 4px;
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            transform-origin: center;
            cursor: pointer;
            z-index: 20;
        }
        .polaroid-constellation img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 2px;
        }
        .polaroid-constellation:hover {
            transform: scale(1.15) rotate(0deg) !important;
            z-index: 50;
            box-shadow: 0 15px 35px rgba(244,63,94,0.4);
        }

        /* 4. Love Letter */
        .parchment-paper {
            width: 90%;
            max-width: 420px;
            background: linear-gradient(135deg, #fef3c7, #fde68a, #f5deb3);
            background-image: repeating-linear-gradient(
                0deg, transparent, transparent 28px, rgba(139,90,43,0.08) 28px, rgba(139,90,43,0.08) 29px
            );
            box-shadow: 0 20px 40px rgba(0,0,0,0.6), inset 0 0 40px rgba(139,90,43,0.15);
            border-radius: 12px;
            border: 1px solid rgba(139,90,43,0.25);
            padding: 24px;
            position: relative;
            transform: translateY(100vh);
            transition: transform 1.2s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 20;
            font-family: 'Dancing Script', 'Playfair Display', serif;
        }
        @keyframes penBobble {
            0%, 100% { transform: rotate(-30deg) translateY(0); }
            50% { transform: rotate(-25deg) translateY(-2px); }
        }
        .quill-pen {
            animation: penBobble 0.2s linear infinite;
        }

        /* 5. Future Dreams Portal */
        @keyframes rotateClockwise {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes rotateCounterClockwise {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(-360deg); }
        }
        .portal-ring-1 {
            animation: rotateClockwise 25s linear infinite;
            transform-origin: center;
        }
        .portal-ring-2 {
            animation: rotateCounterClockwise 18s linear infinite;
            transform-origin: center;
        }
        .portal-ring-3 {
            animation: rotateClockwise 8s linear infinite;
            transform-origin: center;
        }
        .dream-card {
            position: absolute;
            left: 50%;
            top: 50%;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(244,63,94,0.3);
            border-radius: 16px;
            padding: 14px 18px;
            color: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0;
            transform: translate(calc(-50% + var(--tx, 0px)), calc(-50% + var(--ty, 0px))) scale(var(--scale, 0.5));
            transition: transform 1s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 1s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            z-index: 30;
            --scale: 0.5;
        }
        .dream-card.active {
            --scale: 1;
            opacity: 1;
        }
        .dream-card:hover {
            border-color: rgba(244,63,94,0.8);
            box-shadow: 0 15px 35px rgba(244,63,94,0.3);
            --scale: 1.05;
        }

        /* 6. Feelings Countdown Heartbeat */
        @keyframes counterHeartbeat {
            0%, 100% { transform: scale(1); }
            15% { transform: scale(1.25); }
            30% { transform: scale(1); }
            45% { transform: scale(1.15); }
            60% { transform: scale(1); }
        }
        .countdown-heart-beat {
            animation: counterHeartbeat var(--beat-duration, 1.5s) ease-in-out infinite;
        }

        /* 7. Ring Box */
        @keyframes spotlightMove {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-45%, -52%) scale(1.05); }
        }
        .ring-spotlight {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0) 70%);
            pointer-events: none;
            z-index: 5;
            animation: spotlightMove 8s ease-in-out infinite;
        }
        @keyframes ringRayRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .ring-light-ray {
            position: absolute;
            width: 2px;
            height: 300px;
            background: linear-gradient(to top, rgba(255,215,0,0.5), transparent);
            transform-origin: bottom center;
            bottom: 50%;
            left: 50%;
            z-index: 12;
            opacity: 0;
            transition: opacity 1s;
        }
        .ring-light-rays-active {
            animation: ringRayRotate 20s linear infinite;
        }
        @keyframes diamondShimmer {
            0%, 100% { fill: rgba(255,255,255,0.8); }
            50% { fill: rgba(255,255,255,1); filter: drop-shadow(0 0 4px #fff); }
        }
        .diamond-facet {
            animation: diamondShimmer 1.5s ease-in-out infinite;
            animation-delay: var(--shimmer-delay, 0s);
        }

        /* 8. Final Proposal */
        @keyframes emojiPetalFall {
            0% { transform: translateY(-50px) translateX(0) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(110vh) translateX(var(--drift, 50px)) rotate(360deg); opacity: 0; }
        }
        .falling-petal-emoji {
            position: absolute;
            top: -50px;
            font-size: 24px;
            pointer-events: none;
            z-index: 15;
            animation: emojiPetalFall var(--dur, 8s) linear forwards;
        }
        .polaroid-heart-shape {
            position: absolute;
            background: white;
            padding: 4px 4px 12px 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            border-radius: 2px;
            transition: all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            transform-origin: center;
            z-index: 20;
            width: 50px;
            height: 50px;
        }
        .polaroid-heart-shape img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 1px;
        }

        /* 9. Final Celebration */
        .floating-celebration-photo {
            position: absolute;
            background: white;
            padding: 6px 6px 18px 6px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            border-radius: 4px;
            transition: all 1s ease-in-out;
            z-index: 20;
            width: 80px;
            height: 80px;
            opacity: 0;
        }
        .floating-celebration-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 2px;
        }

        /* 💌 LOVE LETTER CINEMATIC ANIMATIONS CSS */
        .love-paper-unfolded {
            width: 92%;
            max-width: 440px;
            background: linear-gradient(135deg, #fffaf0, #fdf5e6, #faebd7);
            background-image: repeating-linear-gradient(
                0deg, transparent, transparent 27px, rgba(139,90,43,0.06) 27px, rgba(139,90,43,0.06) 28px
            );
            box-shadow: 0 25px 50px rgba(0,0,0,0.5), inset 0 0 50px rgba(139,90,43,0.12);
            border-radius: 8px;
            border: 1px solid rgba(139,90,43,0.2);
            padding: 30px 24px;
            position: relative;
            transform: scale(0.8) translateY(100vh);
            opacity: 0;
            transition: transform 1.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 1.2s ease-out;
            z-index: 20;
            font-family: 'Dancing Script', 'Playfair Display', serif;
        }
        .love-paper-unfolded.active {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
        
        /* 1. Envelope Opening CSS */
        .envelope-cinematic-wrapper {
            position: relative;
            width: 280px;
            height: 200px;
            perspective: 1000px;
            cursor: pointer;
            z-index: 20;
        }
        .envelope-back {
            position: absolute;
            inset: 0;
            background: #e2cca9;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            border: 1px solid #c8b18a;
        }
        .envelope-flap-3d {
            position: absolute;
            top: 0;
            left: 0;
            width: 280px;
            height: 110px;
            background: #d4b584;
            clip-path: polygon(0 0, 100% 0, 50% 100%);
            border-radius: 12px 12px 0 0;
            transform-origin: top center;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 25;
            backface-visibility: hidden;
            border-top: 1px solid #c8b18a;
        }
        .envelope-flap-3d.open {
            transform: rotateX(180deg);
            z-index: 5;
        }
        .envelope-front-sides {
            position: absolute;
            inset: 0;
            background: #dfc296;
            clip-path: polygon(0 0, 0 100%, 50% 50%, 100% 100%, 100% 0);
            border-radius: 12px;
            z-index: 22;
            box-shadow: inset 0 -5px 15px rgba(0,0,0,0.05);
        }
        .envelope-front-bottom {
            position: absolute;
            inset: 0;
            background: #e8cc9f;
            clip-path: polygon(0 100%, 100% 100%, 50% 45%);
            border-radius: 12px;
            z-index: 23;
            box-shadow: inset 0 -8px 10px rgba(0,0,0,0.04);
        }
        .wax-seal-3d {
            position: absolute;
            top: 85px;
            left: 115px;
            width: 50px;
            height: 50px;
            background: radial-gradient(circle, #b91c1c 0%, #7f1d1d 80%, #450a0a 100%);
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3), inset 0 2px 4px rgba(255,255,255,0.2);
            z-index: 26;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fef08a;
            font-size: 20px;
            font-weight: bold;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.4s;
            cursor: pointer;
            border: 2px solid #991b1b;
        }
        .wax-seal-3d:hover {
            transform: scale(1.1);
        }
        .wax-seal-3d.cracked {
            transform: scale(0) rotate(185deg);
            opacity: 0;
        }
        .envelope-letter-slide {
            position: absolute;
            bottom: 10px;
            left: 15px;
            width: 250px;
            height: 170px;
            background: #fafaf9;
            border-radius: 6px;
            box-shadow: inset 0 0 10px rgba(139,90,43,0.05);
            z-index: 10;
            transition: transform 1.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.8s;
            transform: translateY(0);
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border: 1px solid #e7e5e4;
        }
        .envelope-letter-slide.slide-out {
            transform: translateY(-160px) scale(0.95);
            z-index: 30;
        }
        
        /* 2. Scrapbook Page Flip CSS */
        .scrapbook-wrapper {
            position: relative;
            width: 92%;
            max-width: 480px;
            height: 380px;
            perspective: 1500px;
            z-index: 20;
        }
        @media (min-width: 768px) {
            .scrapbook-wrapper {
                max-width: 580px;
                height: 460px;
            }
        }
        .scrapbook-book {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            transition: transform 0.5s;
        }
        .scrapbook-cover-left, .scrapbook-cover-right {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 50%;
            background: #5c3f2b;
            border: 4px solid #3d2719;
            border-radius: 8px 0 0 8px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            z-index: 5;
        }
        .scrapbook-cover-left {
            left: 0;
            transform-origin: right center;
        }
        .scrapbook-cover-right {
            right: 0;
            transform-origin: left center;
            border-radius: 0 8px 8px 0;
        }
        .scrapbook-sheet {
            position: absolute;
            top: 10px;
            bottom: 10px;
            left: 50%;
            width: 45%;
            height: calc(100% - 20px);
            background: #faf6eb;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            border-radius: 0 4px 4px 0;
            transform-origin: left center;
            transform-style: preserve-3d;
            transition: transform 0.8s cubic-bezier(0.25, 1, 0.5, 1);
            z-index: 10;
        }
        .scrapbook-sheet.flipped {
            transform: rotateY(-180deg);
            z-index: 15;
        }
        .scrapbook-page-front, .scrapbook-page-back {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            padding: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-color: #faf6eb;
            border-left: 1px solid rgba(0,0,0,0.05);
        }
        .scrapbook-page-back {
            transform: rotateY(180deg);
            background-color: #f7f2e4;
            border-right: 1px solid rgba(0,0,0,0.05);
        }
        .scrapbook-tape {
            position: absolute;
            width: 70px;
            height: 20px;
            background: rgba(253, 224, 71, 0.45);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transform: rotate(-10deg);
            top: 5px;
            left: 30px;
            z-index: 5;
        }
        .scrapbook-polaroid {
            background: white;
            padding: 6px 6px 16px 6px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            border: 1px solid rgba(0,0,0,0.05);
            transform: rotate(var(--rot, 2deg));
            max-width: 90%;
            margin: 0 auto;
        }
        .scrapbook-polaroid img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 1px;
        }
        @media (min-width: 768px) {
            .scrapbook-polaroid img {
                height: 170px;
            }
        }
        .scrapbook-desc {
            font-family: 'Dancing Script', 'Playfair Display', serif;
            font-size: 14px;
            color: #5c4033;
            text-align: center;
            margin-top: 6px;
        }

        /* 3. Timeline Path Scroll CSS */
        .timeline-cinematic-scroll {
            width: 100%;
            height: 50vh;
            overflow-y: auto;
            position: relative;
            padding: 20px 0;
            mask-image: linear-gradient(to bottom, transparent, #000 15%, #000 85%, transparent);
            -webkit-mask-image: linear-gradient(to bottom, transparent, #000 15%, #000 85%, transparent);
        }
        .timeline-track-line {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 2px;
            background: rgba(225,29,72,0.15);
            transform: translateX(-50%);
            z-index: 5;
        }
        .timeline-track-progress {
            position: absolute;
            top: 0;
            left: 50%;
            width: 2px;
            background: linear-gradient(to bottom, #e11d48, #ec4899);
            transform: translateX(-50%);
            z-index: 6;
            height: 0%;
            transition: height 0.3s ease-out;
            box-shadow: 0 0 10px rgba(225,29,72,0.5);
        }
        .timeline-milestone {
            position: relative;
            width: 100%;
            margin-bottom: 80px;
            z-index: 10;
            display: flex;
            justify-content: center;
            opacity: 0.3;
            transform: scale(0.9);
            transition: all 0.6s ease-out;
        }
        .timeline-milestone.active {
            opacity: 1;
            transform: scale(1);
        }
        .timeline-badge-node {
            position: absolute;
            left: 50%;
            top: 10px;
            width: 16px;
            height: 16px;
            background: #fff;
            border: 3px solid #e11d48;
            border-radius: 50%;
            transform: translateX(-50%);
            z-index: 15;
            transition: all 0.4s;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .timeline-milestone.active .timeline-badge-node {
            background: #e11d48;
            border-color: #fff;
            box-shadow: 0 0 12px #e11d48;
            transform: translateX(-50%) scale(1.3);
        }
        .timeline-card-content {
            width: 80%;
            max-width: 320px;
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            transition: border-color 0.4s;
        }
        .timeline-milestone.active .timeline-card-content {
            border-color: rgba(225,29,72,0.4);
            background: rgba(255,255,255,0.08);
            box-shadow: 0 12px 30px rgba(225,29,72,0.1);
        }

        /* 4. Future Dreams Bubble Parallax */
        @keyframes floatDreamBubble {
            0% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-15px) scale(1.02); }
            100% { transform: translateY(0) scale(1); }
        }
        .love-dream-bubble {
            position: absolute;
            background: rgba(251, 113, 133, 0.08);
            backdrop-filter: blur(10px);
            border: 1.5px solid rgba(251, 113, 133, 0.35);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 15px 35px rgba(225,29,72,0.15), inset 0 0 20px rgba(255,255,255,0.05);
            transition: all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            animation: floatDreamBubble 6s ease-in-out infinite;
            animation-delay: var(--float-delay, 0s);
        }
        .love-dream-bubble:hover {
            border-color: rgba(251, 113, 133, 0.85);
            background: rgba(251, 113, 133, 0.15);
            box-shadow: 0 20px 45px rgba(225,29,72,0.3);
            transform: scale(1.1) !important;
        }
        .love-dream-bubble.revealed {
            border-color: #f43f5e;
            background: radial-gradient(circle, rgba(244,63,94,0.2) 0%, rgba(225,29,72,0.05) 100%);
            box-shadow: 0 25px 50px rgba(244,63,94,0.4);
        }

        /* 5. 3D Folding Letter CSS */
        .folding-letter-container {
            width: 290px;
            height: 360px;
            perspective: 1200px;
            position: relative;
            z-index: 20;
            cursor: pointer;
        }
        .folding-letter-sheet-3d {
            width: 100%;
            height: 100%;
            position: absolute;
            transform-style: preserve-3d;
            transition: transform 1.2s;
        }
        .folding-panel {
            position: absolute;
            width: 100%;
            height: 120px;
            background: #faf8f5;
            border: 1px dashed rgba(139,90,43,0.15);
            backface-visibility: hidden;
            box-sizing: border-box;
            background-image: repeating-linear-gradient(
                0deg, transparent, transparent 27px, rgba(139,90,43,0.03) 27px, rgba(139,90,43,0.03) 28px
            );
            box-shadow: inset 0 0 15px rgba(139,90,43,0.05);
        }
        .folding-panel-top {
            top: 0;
            transform-origin: bottom center;
            transition: transform 1s ease-in-out;
            z-index: 3;
            border-bottom: 2px dashed rgba(139,90,43,0.2);
            border-radius: 6px 6px 0 0;
        }
        .folding-panel-top.folded {
            transform: rotateX(-179deg);
        }
        .folding-panel-middle {
            top: 120px;
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 10px;
        }
        .folding-panel-bottom {
            top: 240px;
            transform-origin: top center;
            transition: transform 1s ease-in-out;
            z-index: 4;
            border-top: 2px dashed rgba(139,90,43,0.2);
            border-radius: 0 0 6px 6px;
        }
        .folding-panel-bottom.folded {
            transform: rotateX(179deg);
        }
        
        /* 6. Voice Wave Visualizer */
        .love-voice-glow-button {
            position: relative;
            z-index: 10;
        }
        .love-voice-glow-ring {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(1);
            width: 90px;
            height: 90px;
            background: radial-gradient(circle, rgba(225,29,72,0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s;
            animation: spotlightMove 3s ease-in-out infinite;
        }
        .love-voice-glow-ring.playing {
            animation: roseGlow 1.5s ease-in-out infinite;
            width: 130px;
            height: 130px;
        }

        /* ═══════════════════════════════════════════════════
           MANA LO YAAR CATEGORY CINEMATIC CSS
           ═══════════════════════════════════════════════════ */
        @keyframes sirenFlash {
            0%, 100% { background-color: #160a0f; }
            50% { background-color: #3b0c16; }
        }
        .mana-lo-siren-bg {
            animation: sirenFlash 2s ease-in-out infinite;
        }
        @keyframes sirenPulseBorder {
            0%, 100% { box-shadow: 0 0 15px rgba(239, 68, 68, 0.2), inset 0 0 15px rgba(239, 68, 68, 0.1); }
            50% { box-shadow: 0 0 35px rgba(239, 68, 68, 0.6), inset 0 0 25px rgba(239, 68, 68, 0.3); border-color: rgba(239, 68, 68, 0.6); }
        }
        .mana-lo-siren-border {
            border: 2px solid rgba(239, 68, 68, 0.3);
            animation: sirenPulseBorder 1.5s ease-in-out infinite;
        }
        @keyframes giftWiggle {
            0%, 100% { transform: rotate(0deg); }
            20% { transform: rotate(-8deg) scale(1.05); }
            40% { transform: rotate(6deg) scale(1.05); }
            60% { transform: rotate(-5deg) scale(1.02); }
            80% { transform: rotate(3deg) scale(1.02); }
        }
        .gift-wiggle-hover:hover, .gift-wiggle-active {
            animation: giftWiggle 0.6s ease-in-out infinite;
            cursor: pointer;
        }
        @keyframes angryShake {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            10% { transform: translate(-3px, -3px) rotate(-0.8deg); }
            20% { transform: translate(3px, 0px) rotate(0.8deg); }
            30% { transform: translate(-2px, 3px) rotate(0deg); }
            40% { transform: translate(3px, 2px) rotate(-0.8deg); }
            50% { transform: translate(-3px, -2px) rotate(0.8deg); }
            60% { transform: translate(2px, 3px) rotate(0deg); }
            75% { transform: translate(-3px, 2px) rotate(-0.8deg); }
            90% { transform: translate(3px, -3px) rotate(0.8deg); }
        }
        .angry-shake-active {
            animation: angryShake 0.1s linear infinite;
        }
        /* Things I Miss: Card Flipper CSS */
        .manalo-card-flipper-container {
            perspective: 1000px;
            width: 140px;
            height: 200px;
        }
        .manalo-card-flipper {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }
        .manalo-card-flipper.flipped {
            transform: rotateY(180deg);
        }
        .manalo-card-front, .manalo-card-back {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border: 1px solid rgba(255,255,255,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px;
            overflow: hidden;
        }
        .manalo-card-front {
            background: linear-gradient(135deg, rgba(244,63,94,0.15) 0%, rgba(225,29,72,0.05) 100%);
            backdrop-filter: blur(10px);
            border-color: rgba(244,63,94,0.3);
        }
        .manalo-card-back {
            background: linear-gradient(135deg, rgba(30,41,59,0.95) 0%, rgba(15,23,42,0.98) 100%);
            transform: rotateY(180deg);
            border-color: rgba(251,113,133,0.2);
        }
        /* Lock & Key styling */
        .lock-container-wrap {
            position: relative;
            width: 280px;
            height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .heart-lock-svg {
            width: 140px;
            height: 140px;
            fill: #fda4af;
            stroke: #e11d48;
            stroke-width: 4px;
            transition: all 0.5s;
            filter: drop-shadow(0 10px 20px rgba(225,29,72,0.3));
        }
        .heart-lock-svg.unlocked {
            fill: #fecdd3;
            stroke: #22c55e;
            filter: drop-shadow(0 15px 30px rgba(34,197,94,0.4));
            transform: scale(1.05);
        }
        .heart-key-draggable {
            position: absolute;
            width: 60px;
            height: 60px;
            cursor: grab;
            z-index: 50;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.3));
            touch-action: none;
            transition: transform 0.1s;
        }
        .heart-key-draggable:active {
            cursor: grabbing;
            transform: scale(1.1);
        }
        /* Floating Emojis */
        @keyframes floatPlayfulEmoji {
            0% { transform: translateY(0) rotate(0deg) scale(1); opacity: 0; }
            10% { opacity: 0.8; }
            90% { opacity: 0.8; }
            100% { transform: translateY(-500px) rotate(var(--rot-deg, 180deg)) scale(1.2); opacity: 0; }
        }
        .floating-playful-emoji {
            position: absolute;
            pointer-events: none;
            animation: floatPlayfulEmoji 5s linear forwards;
            z-index: 15;
        }
        /* Premium: Our Chats Styles */
        .chat-moments-scroll::-webkit-scrollbar {
            display: none;
        }
        .chat-moments-scroll {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .chat-moment-tag {
            transition: all 0.3s ease;
        }
        .chat-bubble {
            max-width: 85%;
            word-wrap: break-word;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .chat-bubble.show {
            opacity: 1;
            transform: translateY(0);
        }
        .chat-bubble-left {
            background-color: #27272a; /* zinc-800 */
            border-bottom-left-radius: 4px;
            color: #f4f4f5;
        }
        .chat-bubble-right {
            border-bottom-right-radius: 4px;
            color: #ffffff;
        }
        .chat-bubble-center {
            background-color: transparent;
            color: #a1a1aa; /* Zinc 400 */
            font-style: italic;
            font-size: 0.75rem;
            text-align: center;
            max-width: 100%;
            width: 100%;
        }
        /* Waveform active bar */
        .slide-voice-bar-active {
            background-color: #ec4899 !important; /* bg-pink-500 */
        }

        /* Ambient background glow circle animations */
        @keyframes driftAmbient1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(8vw, 4vh) scale(1.08); }
            66% { transform: translate(-4vw, 12vh) scale(0.92); }
        }
        @keyframes driftAmbient2 {
            0%, 100% { transform: translate(0, 0) scale(1.08); }
            33% { transform: translate(-6vw, -8vh) scale(0.92); }
            66% { transform: translate(10vw, 4vh) scale(1); }
        }
        @keyframes driftAmbient3 {
            0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
            50% { transform: translate(-46%, -54%) rotate(180deg) scale(1.1); }
        }
        .animate-ambient-1 {
            animation: driftAmbient1 25s ease-in-out infinite;
        }
        .animate-ambient-2 {
            animation: driftAmbient2 30s ease-in-out infinite;
        }
        .animate-ambient-3 {
            animation: driftAmbient3 35s ease-in-out infinite;
        }
    </style>
    <?php render_adsense_head_script(); ?>
</head>
<body class="flex justify-center items-start min-h-screen" style="background: #14001a; color: white;">

    <!-- Emoji Rain -->
    <?php render_emoji_rain(); ?>

    <!-- AMBIENT BACKGROUND GLOW CIRCLES -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute w-[40vw] h-[40vw] max-w-[600px] max-h-[600px] rounded-full blur-[120px] opacity-[0.25] animate-ambient-1" style="background: radial-gradient(circle, #f43f5e 0%, transparent 80%); top: 10%; left: 5%;"></div>
        <div class="absolute w-[35vw] h-[35vw] max-w-[500px] max-h-[500px] rounded-full blur-[100px] opacity-[0.20] animate-ambient-2" style="background: radial-gradient(circle, #ec4899 0%, transparent 80%); bottom: 15%; right: 10%;"></div>
        <div class="absolute w-[30vw] h-[30vw] max-w-[400px] max-h-[400px] rounded-full blur-[90px] opacity-[0.15] animate-ambient-3" style="background: radial-gradient(circle, #a855f7 0%, transparent 80%); top: 40%; left: 50%; transform: translate(-50%, -50%);"></div>
    </div>

    <!-- FLOATING BACKGROUND PARTICLES -->
    <div id="particle-container" class="fixed inset-0 pointer-events-none z-30 overflow-hidden" style="z-index: 30;"></div>

    <!-- AUDIO PLAYER -->
    <?php $bg_music_url = !empty($page['music_url']) ? h($base_url . $page['music_url']) : ''; ?>
    <audio id="story-audio" loop preload="none" <?= !empty($bg_music_url) ? 'src="' . $bg_music_url . '"' : '' ?>></audio>

    <?php if ($password_required && !$password_verified): ?>
    <!-- ════════════ PASSWORD PROTECTION SCREEN ════════════ -->
    <div class="story-container <?= $theme['bg_class'] ?> w-full flex flex-col justify-center items-center p-8 text-center relative z-10 min-h-screen">
        <div class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-3xl p-8 max-w-sm w-full shadow-2xl">
            <div class="text-5xl mb-6">🔒</div>
            <h2 class="text-xl font-bold <?= $theme['text_primary'] ?> mb-2">This Page Is Protected</h2>
            <p class="text-sm <?= $theme['text_muted'] ?> mb-6">Enter the password to view this surprise.</p>
            <form method="POST" class="space-y-4">
                <input type="password" name="page_password" required autofocus
                       class="w-full px-4 py-3 rounded-xl border <?= $is_dark ? 'bg-white/5 border-white/10 text-white' : 'bg-white/60 border-gray-200 text-gray-800' ?> text-center text-lg font-mono tracking-widest outline-none focus:ring-2 focus:ring-[<?= $theme['accent'] ?>]"
                       placeholder="• • • • • •">
                <?php if (isset($_POST['page_password'])): ?>
                    <p class="text-red-500 text-xs font-semibold">Incorrect password. Try again.</p>
                <?php endif; ?>
                <button type="submit" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-xl shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition">
                    Unlock 🔓
                </button>
            </form>
        </div>
        <div class="text-center text-[10px] <?= $theme['text_muted'] ?> font-semibold tracking-wider mt-8">
            Created with SoulSync
        </div>
    </div>

    <?php else: ?>

    <!-- ════════════ BYPASS OVERLAY COVER CARD ════════════ -->
    <div id="autoplay-overlay" class="fixed inset-0 z-50 bg-[#06000f] flex flex-col justify-center items-center p-6 text-center">
        <!-- Ad Banner (top of loading screen) -->
        <div class="absolute top-0 left-0 right-0 z-20">
            <?php render_ad_banner('adsense_slot_published_top'); ?>
        </div>
        <!-- Ambient glows inside overlay -->
        <div class="absolute top-1/4 left-1/4 w-72 h-72 bg-pink-500/10 rounded-full blur-[80px] pointer-events-none"></div>
        <div class="absolute bottom-1/4 right-1/4 w-72 h-72 bg-purple-500/8 rounded-full blur-[80px] pointer-events-none"></div>
        
        <!-- Preloader Loading View -->
        <div id="preloader-loading-view" class="max-w-xs w-full flex flex-col items-center relative z-10">
            <!-- Center Spinner / Heart -->
            <div class="relative w-32 h-32 flex items-center justify-center mb-6">
                <div class="absolute inset-0 rounded-full border-4 border-t-pink-500 border-r-transparent border-b-violet-500 border-l-transparent animate-spin duration-1000"></div>
                <div class="absolute inset-3 rounded-full border-4 border-t-transparent border-r-purple-500 border-b-transparent border-l-cyan-400 animate-spin duration-700 reverse"></div>
                <div class="text-3xl animate-pulse">💖</div>
            </div>
            <h3 class="text-lg font-bold text-white mb-2 font-heading tracking-wide">Preparing Your Story...</h3>
            <p id="preloader-status-text" class="text-[10px] text-pink-200/40 mb-6 font-mono tracking-wider h-4 uppercase">Loading assets...</p>
            
            <!-- Custom Progress Bar -->
            <div class="w-full h-1.5 bg-white/5 rounded-full overflow-hidden mb-2 relative border border-pink-500/10">
                <div id="preloader-progress-bar" class="h-full bg-gradient-to-r from-pink-500 to-rose-500 w-0 transition-all duration-300 ease-out"></div>
            </div>
            <span id="preloader-percentage" class="text-xs font-semibold text-pink-400 font-mono">0%</span>
        </div>

        <!-- Preloader Ready/Open View (hidden initially) -->
        <div id="preloader-ready-view" class="max-w-xs bg-white/5 border border-pink-500/20 rounded-3xl p-8 shadow-2xl relative backdrop-blur-xl hidden transform scale-95 opacity-0 transition-all duration-500">
            <div class="text-5xl mb-6 animate-bounce">💌</div>
            <h2 class="text-xl font-bold text-white mb-2 font-heading">You received a special message 💌</h2>
            <p class="text-xs text-pink-200/50 mb-6 leading-relaxed">
                <?= h($page['sender_name']) ?> has created a beautiful story for you. Tap below to begin.
            </p>
            <button onclick="startStory()" class="w-full py-4 bg-gradient-to-r from-pink-500 to-rose-500 hover:opacity-95 text-white font-bold rounded-2xl text-sm transition shadow-lg shadow-pink-500/25">
                Open Story 💌
            </button>
        </div>


    </div>

    <!-- ════════════ MAIN STORY CONTAINER ════════════ -->
    <div class="story-container <?= $theme['bg_class'] ?> w-full flex flex-col relative z-10 min-h-screen border-x <?= $is_dark ? 'border-white/5' : 'border-black/5' ?>" id="story-body">
        
        <?php if ($page['status'] === 'draft'): ?>
            <div class="absolute top-0 left-0 right-0 bg-yellow-500/90 text-stone-950 text-center py-1 text-[10px] font-bold uppercase tracking-wider z-40">
                Preview Mode (Draft Only)
            </div>
        <?php endif; ?>

        <!-- ═══ MUSIC CONTROL FLOATING WIDGET ═══ -->
        <div id="music-widget" class="absolute bottom-6 right-6 z-40 bg-[#0d0018]/85 text-white border border-pink-500/30 flex flex-col justify-between shadow-2xl transition-all duration-500 overflow-hidden w-12 h-12 rounded-full" onclick="expandMusicWidget(event)">
            <!-- Collapsed Icon -->
            <div id="music-collapsed-view" class="w-12 h-12 flex items-center justify-center text-lg cursor-pointer shrink-0">
                🎵
            </div>
            
            <!-- Expanded Dashboard View (hidden by default via height/overflow) -->
            <div id="music-expanded-view" class="flex flex-col p-4 space-y-3 w-full h-[212px] opacity-0 transition-opacity duration-300 pointer-events-none">
                <!-- Track Title & Close -->
                <div class="flex items-center justify-between">
                    <div class="text-left overflow-hidden mr-2">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Background Music</p>
                        <p id="music-track-title" class="text-xs font-bold truncate">Default Track</p>
                    </div>
                    <button onclick="collapseMusicWidget(event)" class="text-slate-400 hover:text-white text-xs font-bold p-1">✕</button>
                </div>

                <!-- Change Track Select Dropdown -->
                <div class="flex flex-col space-y-1">
                    <select id="music-track-select" onchange="selectTrackFromDropdown(event)" class="w-full text-[10px] bg-black/80 border border-pink-500/20 rounded p-1 text-white focus:outline-none cursor-pointer">
                        <!-- Populated by JS -->
                    </select>
                </div>
                
                <!-- Main Control Buttons -->
                <div class="flex items-center justify-center space-x-4">
                    <button onclick="prevTrack(event)" class="text-sm hover:text-pink-400 transition" title="Prev Track">⏮</button>
                    <button id="music-widget-play" onclick="toggleMusicFromWidget(event)" class="w-10 h-10 rounded-full bg-pink-500 hover:bg-pink-600 text-white flex items-center justify-center text-sm font-bold shadow-md transition" title="Play/Pause">▶</button>
                    <button onclick="nextTrack(event)" class="text-sm hover:text-pink-400 transition" title="Next Track">⏭</button>
                </div>
                
                <!-- Volume Control & Loop -->
                <div class="flex items-center justify-between space-x-2 text-xs">
                    <button id="music-widget-mute" onclick="toggleMuteFromWidget(event)" class="text-sm" title="Mute/Unmute">🔊</button>
                    <input id="music-volume-slider" type="range" min="0" max="1" step="0.05" value="1" oninput="setMusicVolume(event)" class="w-24 h-1 bg-gray-600 rounded-lg appearance-none cursor-pointer accent-pink-500">
                    <button id="music-widget-loop" onclick="toggleLoopFromWidget(event)" class="px-2 py-0.5 rounded text-[9px] font-bold border border-slate-700 bg-slate-800 text-slate-400 hover:text-white transition" title="Loop Track">LOOP</button>
                </div>
            </div>
        </div>

        <!-- ═══ SLIDE PROGRESS BAR ═══ -->
        <div class="absolute top-0 left-0 right-0 z-30 h-1 <?= $is_dark ? 'bg-white/5' : 'bg-black/5' ?>">
            <div id="slide-progress" class="h-full bg-gradient-to-r <?= $theme['btn_gradient'] ?> transition-all duration-500" style="width: 0%"></div>
        </div>

        <?php
        // ═══════════════════════════════════════════════════════════
        // RENDER ALL SLIDES DYNAMICALLY BASED ON CATEGORY DEFINITION
        // ═══════════════════════════════════════════════════════════
        $total_slides = count($slides) + 1;
        foreach ($slides as $idx => $slide):
            $slide_num = $idx + 1;
            $is_active = ($idx === 0) ? 'active' : '';
            $slide_type = $slide['type'];
            
            // Allow customization overrides from slide_data, prioritizing slide's unique key over dynamic index-based keys
            $slide_key = $slide['key'] ?? "slide_{$slide_num}";
            
            // Setup main page asset fallbacks for premium slides if slide-specific ones are not present
            if (empty($slide_data[$slide_key . '_images']) && empty($slide_data["slide_{$slide_num}_images"]) && !empty($images)) {
                $mapped = array_map(function($img) {
                    return [
                        'original' => $img['image_path'],
                        'medium' => $img['medium_path'] ?: $img['image_path'],
                        'thumb' => $img['thumb_path'] ?: $img['image_path'],
                        'caption' => '',
                        'date' => '',
                        'memory' => ''
                    ];
                }, $images);
                $slide_data[$slide_key . '_images'] = $mapped;
                $slide_data["slide_{$slide_num}_images"] = $mapped;
            }
            
            if (empty($slide_data[$slide_key . '_video']) && empty($slide_data["slide_{$slide_num}_video"]) && !empty($page['video_url'])) {
                $slide_data[$slide_key . '_video'] = $page['video_url'];
                $slide_data["slide_{$slide_num}_video"] = $page['video_url'];
            }
            
            if (empty($slide_data[$slide_key . '_voice']) && empty($slide_data["slide_{$slide_num}_voice"]) && !empty($page['voice_url'])) {
                $slide_data[$slide_key . '_voice'] = $page['voice_url'];
                $slide_data["slide_{$slide_num}_voice"] = $page['voice_url'];
            }
            
            $title_override = get_slide_value($slide_data, "{$slide_key}_title", '');
            if (empty($title_override)) {
                $title_override = get_slide_value($slide_data, "slide_{$slide_num}_title", $slide['title'] ?? '');
            }
            $subtitle_override = get_slide_value($slide_data, "{$slide_key}_subtitle", '');
            if (empty($subtitle_override)) {
                $subtitle_override = get_slide_value($slide_data, "slide_{$slide_num}_subtitle", $slide['subtitle'] ?? '');
            }
            $btn_override = get_slide_value($slide_data, "{$slide_key}_btn", '');
            if (empty($btn_override)) {
                $btn_override = get_slide_value($slide_data, "slide_{$slide_num}_btn", $slide['btn'] ?? 'Continue');
            }
            $anim_override = get_slide_value($slide_data, "{$slide_key}_animation", '');
            if (empty($anim_override)) {
                $anim_override = get_slide_value($slide_data, "slide_{$slide_num}_animation", $slide['animation'] ?? 'fadeIn');
            }
            $bg_override = get_slide_value($slide_data, "{$slide_key}_bg", '');
            if (empty($bg_override)) {
                $bg_override = get_slide_value($slide_data, "slide_{$slide_num}_bg", $theme['bg_class'] ?? '');
            }
            
            // Dynamic cinematic backgrounds to cover the full slide-pane padding
            $cinematic_bgs = [
                'proposal_rose_cinematic' => 'bg-black',
                'proposal_constellation' => 'bg-slate-950',
                'proposal_heart_formation' => 'bg-stone-950',
                'proposal_love_letter' => 'bg-[#1e1510]',
                'proposal_future_portal' => 'bg-slate-950',
                'proposal_ring_cinematic' => 'bg-slate-950',
                'love_letter_envelope' => 'bg-[#120c08]',
                'love_letter_envelope_open' => 'bg-[#120c08]',
                'love_letter_handwrite' => 'bg-[#1e1510]',
                'love_letter_scrapbook' => 'bg-[#1a120b]',
                'love_letter_future_dreams' => 'bg-[#0a070e]',
                'love_letter_dreams' => 'bg-[#0a070e]',
                'love_letter_voice_wave' => 'bg-[#0f0a07]',
                'love_letter_folding' => 'bg-[#120c08]',
                'reactions_finale' => 'bg-slate-950',
                'restored_finale' => 'bg-slate-950',
                'cake_blowout' => 'bg-slate-950',
                'birthday_pop_balloons' => 'bg-slate-950',
                'birthday_memory_board' => 'bg-slate-950',
                'birthday_wish_board' => 'bg-slate-950',
                'birthday_blow_candles' => 'bg-slate-950',
            ];
            if (isset($cinematic_bgs[$slide_type])) {
                $bg_override = $cinematic_bgs[$slide_type];
            }
            
            $slide_title = rpl($title_override, $page);
            $slide_subtitle = rpl($subtitle_override, $page);
            $slide_anim = $anim_override;
        ?>

        <?php 
        // Render custom slide background music if configured
        $slide_music = $slide_data[$slide_key . '_music'] ?? '';
        ?>
        <div class="slide-pane <?= $is_active ?> <?= h($bg_override) ?>" id="slide-<?= $slide_num ?>" data-slide="<?= $slide_num ?>" data-slide-type="<?= h($slide_type) ?>" data-slide-music="<?= !empty($slide_music) ? h($slide_music) : '' ?>">
            <?php 
            $is_premium_wide = in_array($slide_type, [
                'premium_collage_explosion', 
                'premium_floating_memories', 
                'premium_star_sky', 
                'premium_memory_book', 
                'premium_puzzle_reveal', 
                'premium_mosaic_heart'
            ]);
            $wrapper_class = $is_premium_wide ? 'slide-content-wrapper premium-wide-wrapper' : 'slide-content-wrapper';
            ?>
            <div class="<?= $wrapper_class ?>">

            <?php // ────────── WELCOME SLIDE ──────────
            if ($slide_type === 'welcome'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <div class="text-6xl mb-6 animate-heartbeat"><?= h($cat_info['icon'] ?? '🌹') ?></div>
                    <h3 class="text-xs font-extrabold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?> mb-3">A Special Page For You</h3>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-4 <?= $theme['text_primary'] ?> leading-tight" id="welcome-title-<?= $slide_num ?>"></h1>
                    <p class="text-sm <?= $theme['text_secondary'] ?> max-w-xs leading-relaxed"><?= $slide_subtitle ?></p>
                </div>
                <div class="text-center pt-6">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-sm uppercase tracking-wider transition shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 active:scale-95">
                        <?= h($btn_override) ?> →
                    </button>
                </div>

            <?php // ────────── VISITOR NAME INPUT SLIDE ──────────
            elseif ($slide_type === 'visitor_name_input'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full">
                    <div class="text-5xl mb-6">👋</div>
                    <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mb-3 font-heading">Before we continue...</h2>
                    <p class="text-xs <?= $theme['text_muted'] ?> max-w-xs mb-8 leading-relaxed">What should we call you? We'll let <?= h($page['sender_name']) ?> know you opened their page.</p>
                    <div class="w-full max-w-xs px-4">
                        <input type="text" id="visitor-name-field" class="w-full text-center px-4 py-3 bg-white/5 border border-white/10 focus:border-pink-500 rounded-2xl outline-none text-white font-semibold text-sm transition" placeholder="Enter your name">
                    </div>
                </div>
                <div class="text-center pt-6 w-full max-w-xs px-4 mx-auto">
                    <button onclick="saveVisitorName(<?= $slide_num ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider transition shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 active:scale-95">
                        Continue &rarr;
                    </button>
                </div>

            <?php // ────────── TEXT STORY SLIDE ──────────
            elseif ($slide_type === 'text_story'): 
                $story_key = $slide['key'] ?? '';
                $story_default = $slide['default'] ?? '';
                $story_text = get_slide_value($slide_data, $story_key, $story_default);
                $show_photo = $slide['show_photo'] ?? false;
            ?>
                <div class="flex-grow flex flex-col justify-center">
                    <div class="text-center mb-6">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <?php if ($show_photo && count($images) > 0): ?>
                        <div class="w-full aspect-[4/3] rounded-3xl overflow-hidden mb-6 shadow-lg <?= $theme['card_border'] ?> border">
                            <img src="<?= h($base_url . img_story($images[0])) ?>" class="w-full h-full object-<?= $photo_fit_mode ?> <?= $photo_fit_mode === 'contain' ? 'bg-black/15' : '' ?> live-photo-entrance" alt="Memory">
                        </div>
                    <?php endif; ?>
                    <div class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-3xl p-5 sm:p-6 shadow-sm">
                        <p class="text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap"><?= h($story_text) ?></p>
                    </div>
                </div>
                <div class="text-center pt-6">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                        Continue →
                    </button>
                </div>

            <?php // ────────── CARDS SLIDE ──────────
            elseif ($slide_type === 'cards'):
                $cards_key = $slide['key'] ?? '';
                $cards_defaults = $slide['defaults'] ?? [];
                $cards = get_slide_cards($slide_data, $cards_key, $cards_defaults);
            ?>
                <div class="flex-grow flex flex-col justify-center">
                    <div class="text-center mb-6">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="space-y-3 cards-container" data-slide="<?= $slide_num ?>">
                        <?php foreach ($cards as $ci => $card_text): ?>
                            <div class="card-enter <?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-2xl p-4 text-center" style="transition-delay: <?= $ci * 150 ?>ms">
                                <p class="text-sm font-semibold <?= $theme['text_primary'] ?>"><?= h($card_text) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="text-center pt-6">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                        Continue →
                    </button>
                </div>

            <?php // ────────── GALLERY SLIDE ──────────
            elseif ($slide_type === 'gallery'): 
                $page_category = $page['category'] ?? 'surprise';
            ?>
                <div class="flex-grow flex flex-col justify-center relative" id="gallery-container-<?= $slide_num ?>" data-category="<?= h($page_category) ?>" data-page-id="<?= (int)$page['id'] ?>">
                    <div class="text-center mb-4 z-10 relative">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>

                    <?php if (count($images) > 0): ?>
                        <!-- Standard gallery container for JS animations -->
                        <div class="gallery-style-wrapper relative w-full flex-grow flex items-center justify-center overflow-hidden">
                            <!-- Shimmer Skeleton for images -->
                            <div class="gallery-loader flex items-center justify-center space-x-2 absolute inset-0 z-20 bg-slate-900/10 backdrop-blur-sm rounded-3xl" id="gallery-loader-<?= $slide_num ?>">
                                <div class="w-2.5 h-2.5 bg-[<?= $theme['accent'] ?>] rounded-full animate-bounce" style="animation-delay: 0s"></div>
                                <div class="w-2.5 h-2.5 bg-[<?= $theme['accent'] ?>] rounded-full animate-bounce" style="animation-delay: 0.15s"></div>
                                <div class="w-2.5 h-2.5 bg-[<?= $theme['accent'] ?>] rounded-full animate-bounce" style="animation-delay: 0.3s"></div>
                            </div>

                            <div class="gallery-render-area w-full h-full relative overflow-y-auto overflow-x-hidden" id="gallery-render-<?= $slide_num ?>">
                                <!-- Rendered dynamically by JS -->
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="w-full aspect-square <?= $is_dark ? 'bg-white/5 border-white/10' : 'bg-white/40 border-gray-200/50' ?> border rounded-3xl flex items-center justify-center text-5xl shadow-inner">
                            <?= h($cat_info['icon'] ?? '🌹') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-center pt-3 pb-1 z-10 relative flex-shrink-0">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                        Continue →
                    </button>
                </div>

            <?php // ────────── LETTER SLIDE (TYPEWRITER) ──────────
            elseif ($slide_type === 'letter'): ?>
                <div class="flex-grow flex flex-col justify-between">
                    <div class="text-center mb-4">
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?>"><?= $slide_title ?></h2>
                    </div>
                    <div class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-3xl p-5 sm:p-6 shadow-sm flex-grow max-h-[45vh] md:max-h-[60vh] overflow-y-auto no-scrollbar mb-4 flex flex-col justify-start">
                        <!-- Embedded Voice note if available -->
                        <?php 
                        $letter_audio_url = '';
                        if (!empty($page['letter_voice_url'])) {
                            $letter_audio_url = $page['letter_voice_url'];
                        } elseif (!empty($page['voice_url'])) {
                            // Fallback to voice_url only if there is no dedicated voice/audio slide in this category's slides
                            $has_dedicated_voice_slide = false;
                            if (isset($slides) && is_array($slides)) {
                                foreach ($slides as $s) {
                                    if ($s['type'] === 'voice_message' || $s['type'] === 'miss_you_voice_wave') {
                                        $has_dedicated_voice_slide = true;
                                        break;
                                    }
                                }
                            }
                            if (!$has_dedicated_voice_slide) {
                                $letter_audio_url = $page['voice_url'];
                            }
                        }
                        if (!empty($letter_audio_url)): 
                        ?>
                            <div class="mb-4 border-b <?= $is_dark ? 'border-white/10' : 'border-black/10' ?> pb-4 w-full custom-audio-player" data-player-id="letter-voice-<?= $slide_num ?>">
                                <p class="text-[10px] uppercase font-bold text-left tracking-wider <?= $theme['text_muted'] ?> mb-2">Listen to Letter 🎧</p>
                                <audio id="custom-audio-element-letter-voice-<?= $slide_num ?>" src="<?= h($base_url . $letter_audio_url) ?>" class="hidden" preload="none"></audio>
                                <div class="flex items-center space-x-3 w-full">
                                    <button type="button" onclick="toggleCustomAudio('letter-voice-<?= $slide_num ?>')" id="custom-audio-play-letter-voice-<?= $slide_num ?>" class="w-8 h-8 rounded-full bg-pink-500 hover:bg-pink-600 text-white flex items-center justify-center text-xs font-bold shadow-md transition focus:outline-none">
                                        ▶
                                    </button>
                                    <button type="button" onclick="changePlaybackSpeed('letter-voice-<?= $slide_num ?>')" id="custom-audio-speed-letter-voice-<?= $slide_num ?>" class="text-[9px] font-bold px-2 py-1 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 transition shadow-sm">
                                        1x
                                    </button>
                                    <div class="flex-grow flex flex-col">
                                        <div class="relative w-full h-1 bg-gray-700/50 rounded-full cursor-pointer" onclick="seekCustomAudio(event, 'letter-voice-<?= $slide_num ?>')">
                                            <div id="custom-audio-progress-letter-voice-<?= $slide_num ?>" class="h-full bg-pink-500 rounded-full w-0"></div>
                                        </div>
                                        <div class="flex justify-between items-center text-[8px] text-slate-400 mt-1 font-mono">
                                            <span id="custom-audio-current-letter-voice-<?= $slide_num ?>">0:00</span>
                                            <span id="custom-audio-duration-letter-voice-<?= $slide_num ?>">0:00</span>
                                        </div>
                                    </div>
                                </div>
                                <div id="custom-audio-wave-letter-voice-<?= $slide_num ?>" class="flex items-end justify-center space-x-0.5 h-6 mt-2 opacity-50 w-full transition-opacity duration-300 cursor-pointer" onclick="seekCustomAudioByWaveform(event, 'letter-voice-<?= $slide_num ?>')" data-slide-num="<?= $slide_num ?>">
                                    <!-- Generated by JS -->
                                </div>
                            </div>
                        <?php endif; ?>

                        <div>
                            <p id="typewriter-<?= $slide_num ?>" class="text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap typewriter-cursor"></p>
                            <p id="signature-<?= $slide_num ?>" class="text-xs font-bold <?= $theme['text_muted'] ?> mt-6 text-right opacity-0 transition-opacity duration-500">— With love, <?= h($page['sender_name']) ?></p>
                        </div>
                    </div>
                    <div class="text-center">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" id="letter-btn-<?= $slide_num ?>" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition opacity-0 pointer-events-none active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── COUNTER SLIDE ──────────
            elseif ($slide_type === 'counter'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mb-2"><?= $slide_title ?></h2>
                    <p class="text-sm <?= $theme['text_muted'] ?> mb-8"><?= $slide_subtitle ?></p>
                    <div id="counter-display-<?= $slide_num ?>" class="flex space-x-2 mb-4 <?= $theme['text_primary'] ?>">
                        <!-- Filled by JS -->
                    </div>
                    <p class="text-xs <?= $theme['text_muted'] ?> uppercase tracking-widest font-bold mt-2">Days Together</p>
                    <?php if ($special_date_enabled && !empty($page['relationship_date'])): ?>
                        <p class="text-xs <?= $theme['text_muted'] ?> mt-4">Since <?= h($page['relationship_date']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="text-center pt-6">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                        Continue →
                    </button>
                </div>

            <?php // ────────── COUNTDOWN 3-2-1 SLIDE ──────────
            elseif ($slide_type === 'countdown_321'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <h2 class="text-xl font-bold <?= $theme['text_primary'] ?> mb-6"><?= $slide_title ?></h2>
                    <div id="countdown-display-<?= $slide_num ?>" class="text-8xl font-extrabold <?= $theme['text_primary'] ?> font-heading" style="opacity:0"></div>
                    <p class="text-sm <?= $theme['text_muted'] ?> mt-4"><?= $slide_subtitle ?></p>
                </div>

            <?php // ────────── BIRTHDAY REVEAL SLIDE ──────────
            elseif ($slide_type === 'birthday_reveal'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <div class="text-7xl mb-6 animate-bounce">🎂</div>
                    <h1 class="text-3xl font-extrabold <?= $theme['text_primary'] ?> mb-4 font-heading leading-tight"><?= $slide_title ?></h1>
                    <p class="text-sm <?= $theme['text_secondary'] ?> max-w-xs leading-relaxed"><?= $slide_subtitle ?></p>
                </div>
                <div class="text-center pt-6">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                        See Your Surprise →
                    </button>
                </div>

            <?php // ────────── CAKE BLOWOUT SLIDE ──────────
            elseif ($slide_type === 'cake_blowout'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <h2 class="text-xl font-bold <?= $theme['text_primary'] ?> mb-2"><?= $slide_title ?></h2>
                    <p class="text-sm <?= $theme['text_muted'] ?> mb-6"><?= $slide_subtitle ?></p>
                    <div class="cursor-pointer" onclick="blowCandle(<?= $slide_num ?>)" id="cake-container-<?= $slide_num ?>">
                        <svg viewBox="0 0 200 200" class="w-52 h-52 mx-auto">
                            <ellipse cx="100" cy="165" rx="75" ry="15" fill="<?= $is_dark ? '#374151' : '#e2e8f0' ?>"/>
                            <rect x="45" y="85" width="110" height="70" rx="12" fill="<?= $theme['accent'] ?>"/>
                            <rect x="45" y="110" width="110" height="45" rx="0" fill="<?= $theme['accent'] ?>" opacity="0.7"/>
                            <path d="M45 85 Q 58 98 70 85 Q 82 98 95 85 Q 108 98 120 85 Q 132 98 145 85 Q 155 98 155 85 L155 92 L45 92 Z" fill="#ffffff"/>
                            <rect x="96" y="45" width="8" height="40" fill="#fb7185" rx="3"/>
                            <path id="candle-flame-<?= $slide_num ?>" d="M100 20 C107 32 107 42 100 48 C93 42 93 32 100 20 Z" fill="#f59e0b" class="animate-pulse"/>
                        </svg>
                        <p class="text-xs font-semibold mt-4 animate-pulse" style="color: <?= $theme['accent'] ?>">🎂 Tap the candle to blow it out!</p>
                    </div>
                </div>

            <?php // ────────── GIFT BOX SLIDE ──────────
            elseif ($slide_type === 'gift_box'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <h2 class="text-xl font-bold <?= $theme['text_primary'] ?> mb-2"><?= $slide_title ?></h2>
                    <p class="text-sm <?= $theme['text_muted'] ?> mb-8"><?= $slide_subtitle ?></p>
                    <div class="cursor-pointer gift-shake" id="gift-box-<?= $slide_num ?>" onclick="openGift(<?= $slide_num ?>)">
                        <div class="text-[120px] leading-none select-none">🎁</div>
                    </div>
                    <p class="text-xs font-semibold mt-6 animate-pulse" style="color: <?= $theme['accent'] ?>" id="gift-hint-<?= $slide_num ?>">Tap the gift box to open!</p>
                </div>

            <?php // ────────── DESTINATION REVEAL SLIDE ──────────
            elseif ($slide_type === 'destination_reveal'):
                $dest_key = $page['destination'] ?? 'cafe';
                $destinations = get_destinations();
                $dest = $destinations[$dest_key] ?? $destinations['cafe'];
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <h2 class="text-xl font-bold <?= $theme['text_primary'] ?> mb-2"><?= $slide_title ?></h2>
                    <p class="text-sm <?= $theme['text_muted'] ?> mb-8"><?= $slide_subtitle ?></p>
                    <div id="dest-reveal-<?= $slide_num ?>" class="opacity-0 transition-all duration-700 scale-75">
                        <div class="text-[100px] leading-none mb-4"><?= $dest['emoji'] ?></div>
                        <h3 class="text-2xl font-extrabold <?= $theme['text_primary'] ?> font-heading"><?= h($dest['name']) ?></h3>
                    </div>
                </div>
                <div class="text-center pt-6">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                        Continue →
                    </button>
                </div>

            <?php // ────────── VIDEO MESSAGE SLIDE ──────────
            elseif ($slide_type === 'video_message'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <h2 class="text-xl font-bold <?= $theme['text_primary'] ?> mb-2"><?= $slide_title ?></h2>
                    <p class="text-sm <?= $theme['text_muted'] ?> mb-6"><?= $slide_subtitle ?></p>
                    <?php if (!empty($page['video_url'])): ?>
                        <!-- Video Container with Entrance Reveal Styles -->
                        <div class="relative w-full aspect-video rounded-3xl overflow-hidden shadow-lg border <?= $theme['card_border'] ?> bg-black custom-video-player group transition-all duration-300 video-blurred" data-player-id="video-<?= $slide_num ?>">
                            
                            <!-- Top Slide Video Title Overlay -->
                            <div class="absolute top-0 inset-x-0 bg-gradient-to-b from-black/85 via-black/40 to-transparent p-4 flex items-center justify-between opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-300 z-35 pointer-events-none">
                                <span class="text-[10px] font-bold tracking-wide uppercase text-pink-300 drop-shadow-md">Video Message</span>
                                <span class="text-xs font-semibold truncate text-white drop-shadow-md max-w-[70%]"><?= h($slide_title) ?></span>
                            </div>

                            <!-- Loading Spinner -->
                            <div id="video-loader-video-<?= $slide_num ?>" class="absolute inset-0 flex items-center justify-center bg-black/60 z-30 transition-opacity duration-300 hidden">
                                <div class="w-10 h-10 border-4 border-pink-500 border-t-transparent rounded-full animate-spin"></div>
                            </div>

                            <!-- Netflix-style Poster Cover -->
                            <?php 
                            $video_poster_url = '';
                            if (!empty($images) && isset($images[0])) {
                                $video_poster_url = img_story($images[0]['image_path'] ?? $images[0]);
                            }
                            ?>
                            <?php if (!empty($video_poster_url)): ?>
                                <div id="custom-video-poster-video-<?= $slide_num ?>" class="video-poster-cover" style="background-image: url('<?= h($base_url . $video_poster_url) ?>')"></div>
                            <?php endif; ?>

                            <video id="custom-video-element-video-<?= $slide_num ?>" playsinline class="w-full h-full object-contain cursor-pointer" preload="metadata" onclick="toggleCustomVideo('video-<?= $slide_num ?>')" src="<?= h(strpos($page['video_url'], 'http') === 0 ? $page['video_url'] : $base_url . $page['video_url']) ?>" onerror="document.getElementById('video-play-error-video-<?= $slide_num ?>') && document.getElementById('video-play-error-video-<?= $slide_num ?>').classList.remove('hidden');">
                                Your browser does not support HTML5 video.
                            </video>
                            
                            <!-- Custom play/pause center overlay button with reveal animation and pulse glow -->
                            <div id="custom-video-overlay-video-<?= $slide_num ?>" class="absolute inset-0 flex items-center justify-center bg-black/35 transition-opacity duration-300 z-20 cursor-pointer" onclick="toggleCustomVideo('video-<?= $slide_num ?>')">
                                <button type="button" class="play-btn-overlay play-btn-glow w-16 h-16 rounded-full bg-pink-500/90 text-white flex items-center justify-center text-2xl font-bold shadow-lg transition-transform duration-300 transform group-hover:scale-110 pointer-events-auto">
                                    ▶
                                </button>
                            </div>

                            <!-- Custom bottom control bar -->
                            <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent p-3 flex flex-col opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-300 z-30">
                                <!-- Progress Bar -->
                                <div class="relative w-full h-1.5 bg-gray-600 rounded-full mb-2 cursor-pointer group/progress" onclick="seekCustomVideo(event, 'video-<?= $slide_num ?>')">
                                    <div id="custom-video-progress-video-<?= $slide_num ?>" class="h-full bg-pink-500 rounded-full w-0 transition-all duration-100 relative"></div>
                                </div>
                                <div class="flex items-center justify-between text-xs text-white">
                                    <div class="flex items-center space-x-3">
                                        <button type="button" onclick="toggleCustomVideo('video-<?= $slide_num ?>')" id="custom-video-play-btn-video-<?= $slide_num ?>" class="font-bold focus:outline-none hover:text-pink-400">
                                            ▶
                                        </button>
                                        <span class="font-mono text-[10px] text-gray-300" id="custom-video-time-video-<?= $slide_num ?>">0:00 / 0:00</span>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <!-- Volume controls -->
                                        <div class="flex items-center space-x-1 group/volume">
                                            <button type="button" onclick="toggleMuteCustomVideo('video-<?= $slide_num ?>')" id="custom-video-mute-video-<?= $slide_num ?>" class="focus:outline-none hover:text-pink-400">
                                                🔇
                                            </button>
                                            <input type="range" min="0" max="1" step="0.1" value="1" id="custom-video-volume-video-<?= $slide_num ?>" class="w-12 h-1 bg-white/20 rounded-lg appearance-none cursor-pointer accent-pink-500 transition-all focus:outline-none opacity-0 group-hover/volume:opacity-100 w-0 group-hover/volume:w-12" oninput="setVolumeCustomVideo(this.value, 'video-<?= $slide_num ?>')">
                                        </div>
                                        <!-- Playback Speed -->
                                        <button type="button" onclick="cycleSpeedCustomVideo('video-<?= $slide_num ?>')" id="custom-video-speed-video-<?= $slide_num ?>" class="focus:outline-none hover:text-pink-400 font-mono text-[10px] bg-white/10 px-2 py-0.5 rounded">
                                            1x
                                        </button>
                                        <!-- PiP -->
                                        <button type="button" onclick="togglePipCustomVideo('video-<?= $slide_num ?>')" id="custom-video-pip-video-<?= $slide_num ?>" class="focus:outline-none hover:text-pink-400">
                                            📺
                                        </button>
                                        <!-- Fullscreen -->
                                        <button type="button" onclick="fullscreenCustomVideo('video-<?= $slide_num ?>')" class="focus:outline-none hover:text-pink-400">
                                            ⛶
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="w-full aspect-video <?= $is_dark ? 'bg-white/5 border-white/10' : 'bg-white/40 border-gray-200/50' ?> border rounded-3xl flex flex-col items-center justify-center">
                            <span class="text-4xl mb-2">🎬</span>
                            <span class="text-xs <?= $theme['text_muted'] ?>">No video uploaded</span>
                        </div>
                    <?php endif; ?>
                    <p id="video-play-error-video-<?= $slide_num ?>" class="hidden text-[10px] text-red-400 font-semibold mt-2 text-center px-4 leading-relaxed">⚠️ Video playback failed. <a href="<?= h(strpos($page['video_url'], 'http') === 0 ? $page['video_url'] : $base_url . $page['video_url']) ?>" target="_blank" class="underline text-pink-400 font-bold hover:text-pink-300">Tap here to open/download it directly 🍿</a></p>

                </div>
                <div class="text-center pt-6">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                        <?= h($btn_override) ?> →
                    </button>
                </div>
 
            <?php // ────────── VOICE MESSAGE SLIDE ──────────
            elseif ($slide_type === 'voice_message'): ?>
                <div class="absolute inset-0 z-0 overflow-hidden rounded-3xl">
                    <!-- Background Memory Slideshow -->
                    <div id="voice-bg-slideshow-<?= $slide_num ?>" class="absolute inset-0 z-0 opacity-0 transition-opacity duration-1000 pointer-events-none bg-black">
                        <?php 
                        if (!empty($images)):
                            foreach ($images as $g_idx => $g_img):
                                $img_url = img_story($g_img['image_path']);
                        ?>
                            <div class="absolute inset-0 bg-cover bg-center transition-opacity duration-1000 opacity-0 voice-slide-img" style="background-image: url('<?= h($base_url . $img_url) ?>')"></div>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                        <div class="absolute inset-0 bg-black/70 backdrop-blur-[6px]"></div>
                    </div>
                </div>

                <div class="flex-grow flex flex-col justify-between items-center text-center w-full h-full z-10 relative py-8 px-4 custom-audio-player" data-player-id="voice-<?= $slide_num ?>">
                    <!-- Top 20% Info -->
                    <div class="h-[20%] flex flex-col items-center justify-center space-y-1.5 w-full mt-2">
                        <?php if (!empty($creator_profile_photo) && file_exists($creator_profile_photo)): ?>
                            <img src="<?= h($base_url . $creator_profile_photo) ?>" class="w-16 h-16 rounded-full object-cover border-2 border-pink-500/40 shadow-xl animate-heartbeat">
                        <?php else: ?>
                            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-pink-500 to-rose-500 flex items-center justify-center font-extrabold text-white text-2xl shadow-xl uppercase border-2 border-pink-500/40 animate-heartbeat">
                                <?= h(mb_substr($page['sender_name'] ?? 'S', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <p class="text-[10px] <?= $theme['text_muted'] ?> font-bold uppercase tracking-widest">Voice Note From</p>
                            <h2 class="text-lg font-extrabold <?= $theme['text_primary'] ?> mt-0.5"><?= h($page['sender_name']) ?></h2>
                            <p class="text-[10px] text-slate-400 font-semibold mt-0.5 italic"><?= h($slide_subtitle) ?></p>
                        </div>
                    </div>

                    <!-- Middle 60% Content -->
                    <div class="h-[60%] flex flex-col items-center justify-center relative w-full my-auto">
                        <!-- Local Particle Container -->
                        <div id="voice-particle-container-<?= $slide_num ?>" class="absolute inset-0 pointer-events-none overflow-hidden z-0 rounded-2xl"></div>
                        
                        <!-- Glowing ring background behind wave -->
                        <div class="absolute w-56 h-56 rounded-full blur-3xl pointer-events-none transition-all duration-1000 z-0 bg-pink-500/15" id="voice-glow-ring-voice-<?= $slide_num ?>"></div>
                        
                        <!-- Waveform area -->
                        <div class="relative w-full max-w-sm px-6 flex flex-col items-center justify-center z-10">
                            <!-- Custom visualizer classes -->
                            <div id="custom-audio-wave-voice-<?= $slide_num ?>" class="flex items-center justify-between space-x-1.5 h-24 w-full cursor-pointer" onclick="seekCustomAudioByWaveform(event, 'voice-<?= $slide_num ?>')" data-slide-num="<?= $slide_num ?>">
                                <!-- Generated by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Bottom 20% Controls -->
                    <div class="h-[20%] flex flex-col items-center justify-center w-full space-y-3">
                        <!-- Seek/Progress bar with duration times -->
                        <div class="w-full max-w-sm px-6">
                            <div class="relative w-full h-1.5 bg-gray-700/50 rounded-full cursor-pointer mb-2" onclick="seekCustomAudio(event, 'voice-<?= $slide_num ?>')">
                                <div id="custom-audio-progress-voice-<?= $slide_num ?>" class="h-full bg-pink-500 rounded-full w-0 transition-all duration-100"></div>
                            </div>
                            <div class="flex justify-between items-center text-[10px] text-slate-400 font-mono">
                                <span id="custom-audio-current-voice-<?= $slide_num ?>">0:00</span>
                                <span id="custom-audio-duration-voice-<?= $slide_num ?>">0:00</span>
                            </div>
                        </div>
                        
                        <!-- Player Controls -->
                        <div class="flex items-center justify-center space-x-8 w-full max-w-sm">
                            <!-- Playback Speed -->
                            <button type="button" onclick="changePlaybackSpeed('voice-<?= $slide_num ?>')" id="custom-audio-speed-voice-<?= $slide_num ?>" class="text-[10px] font-bold px-3 py-1.5 rounded-full <?= $is_dark ? 'bg-white/10 text-slate-300 hover:bg-white/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?> transition shadow-sm">
                                1.0x
                            </button>

                            <!-- Play/Pause -->
                            <button type="button" onclick="toggleCustomAudio('voice-<?= $slide_num ?>')" id="custom-audio-play-voice-<?= $slide_num ?>" class="w-14 h-14 rounded-full bg-pink-500 hover:bg-pink-600 text-white flex items-center justify-center text-xl font-bold shadow-lg transform hover:scale-105 active:scale-95 transition-all duration-300 focus:outline-none ring-4 ring-pink-500/30">
                                ▶
                            </button>

                            <!-- Continue button -->
                            <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="text-[10px] font-bold px-3 py-1.5 rounded-full bg-pink-500/10 hover:bg-pink-500/20 text-pink-500 transition shadow-sm">
                                <?= h($btn_override) ?>
                            </button>
                        </div>

                        <?php if (!empty($page['voice_url'])): ?>
                            <audio id="custom-audio-element-voice-<?= $slide_num ?>" src="<?= h($base_url . $page['voice_url']) ?>" class="hidden" preload="none" playsinline
                                onerror="this.dataset.loadFailed='1'; document.getElementById('voice-load-error-<?= $slide_num ?>') && (document.getElementById('voice-load-error-<?= $slide_num ?>').classList.remove('hidden'));"
                            ></audio>
                            <p id="voice-load-error-<?= $slide_num ?>" class="hidden text-[10px] text-red-400 font-semibold mt-2 text-center">⚠️ Voice note unavailable. Check your connection.</p>
                        <?php endif; ?>
                    </div>
                </div>


            <?php // ────────── INTERACTIVE CHOICE SLIDE ──────────
            elseif ($slide_type === 'interactive_choice'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center" id="choice-container-<?= $slide_num ?>">
                    <div class="text-5xl mb-6"><?= $slide['emoji'] ?? '🥺' ?></div>
                    <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mb-8 font-heading max-w-xs leading-tight" id="choice-prompt-<?= $slide_num ?>">
                        <?= h($page['proposal_question']) ?>
                    </h2>
                    <div class="relative w-full h-32 flex justify-center items-center">
                        <button onclick="handleYes(<?= $slide_num ?>)" id="yes-btn-<?= $slide_num ?>" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-sm shadow-lg <?= $theme['btn_shadow'] ?> mr-4 active:scale-95 transition z-10">
                            <?= h($slide['yes_text'] ?? 'Yes! ❤️') ?>
                        </button>
                        <button id="no-btn-<?= $slide_num ?>" class="px-8 py-3 <?= $is_dark ? 'bg-white/10 text-white/70' : 'bg-slate-200 text-slate-700' ?> font-bold rounded-2xl text-sm transition absolute runaway-btn">
                            <?= h($slide['no_text'] ?? 'No') ?>
                        </button>
                    </div>
                </div>
                <!-- Success state (hidden initially) -->
                <div class="flex-grow flex flex-col justify-between items-center text-center hidden w-full" id="choice-success-<?= $slide_num ?>">
                    <div class="flex-grow flex flex-col justify-center items-center w-full">
                        <div class="text-6xl mb-6 animate-bounce"><?= $slide['success_emoji'] ?? '🥰' ?></div>
                        <h2 class="text-2xl font-extrabold <?= $theme['text_primary'] ?> mb-2 font-heading"><?= rpl($slide['success_title'] ?? 'Thank You!', $page) ?></h2>
                        <p class="text-sm <?= $theme['text_muted'] ?> max-w-xs mb-8"><?= rpl($slide['success_msg'] ?? '', $page) ?></p>
                        <!-- Reactions -->
                        <div class="space-y-4">
                            <span class="block text-[10px] font-bold <?= $theme['text_muted'] ?> uppercase tracking-widest">Send A Reaction</span>
                            <div class="flex space-x-2 justify-center">
                                <?php foreach ($reactions_list as $rkey => $rval): ?>
                                    <button onclick="sendReaction('<?= $rkey ?>')" class="w-12 h-12 <?= $is_dark ? 'bg-white/5 border-white/10' : 'bg-white/60 border-white' ?> border hover:scale-110 rounded-2xl text-xl flex items-center justify-center transition shadow-sm"><?= $rval['emoji'] ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full max-w-xs">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 animate-pulse">
                            Reply to <?= h($page['sender_name']) ?> 💌
                        </button>
                    </div>
                </div>

            <?php // ────────── UNIVERSAL INTERACTIVE SLIDE ──────────
            elseif ($slide_type === 'universal_interactive'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full relative z-20" id="choice-container-<?= $slide_num ?>">
                    <div class="text-6xl mb-6 animate-heartbeat select-none">
                        <?php
                        $cat_emoji = $cat_info['icon'] ?? '💝';
                        echo h($cat_emoji);
                        ?>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold <?= $theme['text_primary'] ?> mb-8 font-heading max-w-xs leading-tight" id="choice-prompt-<?= $slide_num ?>">
                        <?= h($page['interactive_question'] ?? $page['proposal_question']) ?>
                    </h2>
                    
                    <div class="w-full h-32 flex justify-center items-center" id="interactive-button-area-<?= $slide_num ?>">
                        <button onclick="handleUniversalYes(<?= $slide_num ?>)" id="yes-btn-<?= $slide_num ?>" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-sm shadow-lg <?= $theme['btn_shadow'] ?> mr-4 active:scale-95 transition z-10">
                            <?= h(!empty($page['interactive_yes_text']) ? $page['interactive_yes_text'] : 'Yes! ❤️') ?>
                        </button>
                        
                        <?php if ((int)($page['interactive_funny_no'] ?? 1) === 1): ?>
                            <button id="no-btn-<?= $slide_num ?>" onmouseover="dodgeNoButton(event, <?= $slide_num ?>)" onclick="dodgeNoButton(event, <?= $slide_num ?>)" class="px-8 py-3 <?= $is_dark ? 'bg-white/10 text-white/70' : 'bg-slate-200 text-slate-700' ?> font-bold rounded-2xl text-sm transition z-10" style="transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);">
                                <?= h(!empty($page['interactive_no_text']) ? $page['interactive_no_text'] : 'No') ?>
                            </button>
                        <?php else: ?>
                            <button onclick="handleUniversalNo(<?= $slide_num ?>)" id="no-btn-<?= $slide_num ?>" class="px-8 py-3 <?= $is_dark ? 'bg-white/10 text-white/70' : 'bg-slate-200 text-slate-700' ?> font-bold rounded-2xl text-sm transition z-10">
                                <?= h(!empty($page['interactive_no_text']) ? $page['interactive_no_text'] : 'No') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Success State -->
                <div class="flex-grow flex flex-col justify-between items-center text-center hidden w-full relative z-20" id="choice-success-<?= $slide_num ?>">
                    <div class="flex-grow flex flex-col justify-center items-center w-full">
                        <div class="text-6xl mb-6 animate-bounce">🥰🎉</div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold <?= $theme['text_primary'] ?> mb-3 font-heading">
                            <?php
                            $cat_slug = $page['category'] ?? '';
                            if ($cat_slug === 'sorry' || $cat_slug === 'maanjao' || $cat_slug === 'patchup') {
                                echo "Forgiven! 🤗";
                            } elseif ($cat_slug === 'invite') {
                                echo "It's a Date! 🥳";
                            } elseif ($cat_slug === 'missyou') {
                                echo "Hugs! 🤗";
                            } else {
                                echo "Confession Accepted! 💖";
                            }
                            ?>
                        </h2>
                        <p class="text-sm <?= $theme['text_muted'] ?> max-w-xs mb-8 leading-relaxed">
                            <?php
                            if ($cat_slug === 'sorry' || $cat_slug === 'maanjao' || $cat_slug === 'patchup') {
                                echo "Thank you for giving " . h($page['sender_name']) . " another chance.";
                            } elseif ($cat_slug === 'invite') {
                                echo "Awesome! See you soon. Detailed plans will follow.";
                            } elseif ($cat_slug === 'missyou') {
                                echo "Sending you the biggest virtual hug across the sky.";
                            } else {
                                echo "Forever starts today! Congratulations!";
                            }
                            ?>
                        </p>
                        
                        <!-- Reactions -->
                        <div class="space-y-4">
                            <span class="block text-[10px] font-bold <?= $theme['text_muted'] ?> uppercase tracking-widest">Send A Reaction</span>
                            <div class="flex space-x-2 justify-center">
                                <?php foreach ($reactions_list as $rkey => $rval): ?>
                                    <button onclick="sendReaction('<?= $rkey ?>')" class="w-12 h-12 <?= $is_dark ? 'bg-white/5 border-white/10' : 'bg-white/60 border-white' ?> border hover:scale-110 rounded-2xl text-xl flex items-center justify-center transition shadow-sm"><?= $rval['emoji'] ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full max-w-xs mx-auto">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 animate-pulse">
                            Reply to <?= h($page['sender_name']) ?> 💌
                        </button>
                    </div>
                </div>

            <?php // ────────── CELEBRATION SLIDE ──────────
            elseif ($slide_type === 'celebration'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <div class="text-7xl mb-6 animate-bounce"><?= h($cat_info['icon'] ?? '🎉') ?></div>
                    <h1 class="text-3xl font-extrabold <?= $theme['text_primary'] ?> mb-4 font-heading leading-tight"><?= $slide_title ?></h1>
                    <p class="text-sm <?= $theme['text_secondary'] ?> max-w-xs mb-8"><?= $slide_subtitle ?></p>
                    <!-- Reactions -->
                    <div class="space-y-4">
                        <span class="block text-[10px] font-bold <?= $theme['text_muted'] ?> uppercase tracking-widest">Send A Reaction</span>
                        <div class="flex space-x-2 justify-center">
                            <?php foreach ($reactions_list as $rkey => $rval): ?>
                                <button onclick="sendReaction('<?= $rkey ?>')" class="w-12 h-12 <?= $is_dark ? 'bg-white/5 border-white/10' : 'bg-white/60 border-white' ?> border hover:scale-110 rounded-2xl text-xl flex items-center justify-center transition shadow-sm"><?= $rval['emoji'] ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="text-center pt-6 w-full max-w-xs mx-auto">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 animate-pulse">
                        Reply to <?= h($page['sender_name']) ?> 💌
                    </button>
                    <a href="<?= $base_url ?>index.php" class="mt-3 px-6 py-3 bg-white/10 hover:bg-white/15 text-white font-bold rounded-2xl text-xs uppercase tracking-wider border border-white/20 active:scale-95 transition flex items-center justify-center gap-2">
                        ✨ Create Your Own Story
                    </a>
                </div>

            <?php // ────────── REACTIONS FINALE SLIDE ──────────
            elseif ($slide_type === 'reactions_finale'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center">
                    <div class="text-6xl mb-6 animate-heartbeat"><?= $slide['emoji'] ?? '❤️' ?></div>
                    <h2 class="text-2xl font-extrabold <?= $theme['text_primary'] ?> mb-2 font-heading"><?= $slide_title ?></h2>
                    <p class="text-sm <?= $theme['text_muted'] ?> max-w-xs mb-8"><?= $slide_subtitle ?></p>
                    <!-- Reactions -->
                    <div class="space-y-4">
                        <span class="block text-[10px] font-bold <?= $theme['text_muted'] ?> uppercase tracking-widest">Send A Reaction</span>
                        <div class="flex space-x-2 justify-center">
                            <?php foreach ($reactions_list as $rkey => $rval): ?>
                                <button onclick="sendReaction('<?= $rkey ?>')" class="w-12 h-12 <?= $is_dark ? 'bg-white/5 border-white/10' : 'bg-white/60 border-white' ?> border hover:scale-110 rounded-2xl text-xl flex items-center justify-center transition shadow-sm"><?= $rval['emoji'] ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="text-center pt-6 w-full max-w-xs mx-auto">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 animate-pulse">
                        Reply to <?= h($page['sender_name']) ?> 💌
                    </button>
                    <a href="<?= $base_url ?>index.php" class="mt-3 px-6 py-3 bg-white/10 hover:bg-white/15 text-white font-bold rounded-2xl text-xs uppercase tracking-wider border border-white/20 active:scale-95 transition flex items-center justify-center gap-2">
                        ✨ Create Your Own Story
                    </a>
                </div>

            <?php // ────────── 1. BIRTHDAY: POP BALLOONS ──────────
            elseif ($slide_type === 'birthday_pop_balloons'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow h-80 overflow-hidden" id="balloon-container-<?= $slide_num ?>">
                        <div class="balloon-area absolute inset-x-0 bottom-0 top-0 flex justify-around items-end pb-8">
                            <?php
                            $balloons_memories = [
                                "You make my world brighter! 🌟",
                                "Grateful for all your laughter! 😂",
                                "To many more adventures together! 🚀",
                                "Your smile is my favorite thing! 😊",
                                "Wishing you the absolute best today! 🎉"
                            ];
                            foreach($balloons_memories as $bi => $mem):
                                $colors = ['#f43f5e', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'];
                                $color = $colors[$bi % 5];
                            ?>
                                <div class="balloon cursor-pointer relative flex flex-col items-center" 
                                     style="--color: <?= $color ?>; animation-delay: <?= $bi * 0.3 ?>s;"
                                     onclick="popBalloon(this, <?= $slide_num ?>, '<?= addslashes(h($mem)) ?>')">
                                    <div class="balloon-body w-12 h-16 rounded-full flex items-center justify-center text-white font-bold select-none shadow-md" style="background-color: <?= $color ?>;">🎈</div>
                                    <div class="balloon-string w-0.5 h-10 bg-gray-400"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="balloon-message-<?= $slide_num ?>" class="absolute inset-x-4 top-1/3 text-center <?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-2xl p-4 hidden animate-fadeIn shadow-lg z-20">
                            <p class="text-sm font-semibold text-pink-500" id="balloon-msg-text-<?= $slide_num ?>"></p>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="balloon-btn-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Pop all balloons to continue (0/5)
                        </button>
                    </div>
                </div>

            <?php // ────────── 2. BIRTHDAY: BLOW CANDLES ──────────
            elseif ($slide_type === 'birthday_blow_candles'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center z-10" id="blow-container-<?= $slide_num ?>">
                        <div class="cake-area relative cursor-pointer transform scale-110" onclick="blowCandleTrigger(<?= $slide_num ?>)">
                            <div class="candle-flames flex justify-center space-x-6 mb-2">
                                <div class="flame-wrapper relative">
                                    <div class="candle-wick w-1 h-5 bg-stone-700 mx-auto"></div>
                                    <div class="candle-flame active w-5 h-8 bg-amber-500 rounded-full animate-pulse absolute -top-7 left-1/2 -translate-x-1/2" id="flame-1-<?= $slide_num ?>"></div>
                                </div>
                                <div class="flame-wrapper relative">
                                    <div class="candle-wick w-1 h-5 bg-stone-700 mx-auto"></div>
                                    <div class="candle-flame active w-5 h-8 bg-amber-500 rounded-full animate-pulse absolute -top-7 left-1/2 -translate-x-1/2" id="flame-2-<?= $slide_num ?>"></div>
                                </div>
                                <div class="flame-wrapper relative">
                                    <div class="candle-wick w-1 h-5 bg-stone-700 mx-auto"></div>
                                    <div class="candle-flame active w-5 h-8 bg-amber-500 rounded-full animate-pulse absolute -top-7 left-1/2 -translate-x-1/2" id="flame-3-<?= $slide_num ?>"></div>
                                </div>
                            </div>
                            <div class="w-64 h-36 bg-pink-400 rounded-t-3xl border-b-[12px] border-pink-500 relative flex items-center justify-center shadow-lg">
                                <div class="w-full h-5 bg-white/70 absolute top-5 rounded-full"></div>
                                <span class="text-5xl select-none">🎂</span>
                            </div>
                        </div>
                        <div class="mt-6 space-y-2 w-full max-w-xs">
                            <button onclick="blowCandleTrigger(<?= $slide_num ?>)" id="blow-btn-<?= $slide_num ?>" class="w-full py-3 bg-gradient-to-r from-blue-500 to-indigo-500 text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg hover:opacity-95 transition">
                                💨 Blow Candles! (Or Tap Cake)
                            </button>
                            <button onclick="requestMicBlow(<?= $slide_num ?>)" id="mic-btn-<?= $slide_num ?>" class="w-full py-2 bg-white/10 text-xs font-semibold rounded-2xl border border-white/20 text-white hover:bg-white/20 transition">
                                🎙️ Enable Mic Blow Detection
                            </button>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="blow-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            <?= h($btn_override) ?>
                        </button>
                    </div>
                </div>

            <?php // ────────── 3. BIRTHDAY: CAKE CUTTING ──────────
            elseif ($slide_type === 'birthday_cake_cutting'): ?>
                <!-- Immersive Fullscreen Cinematic Cake-Cutting Container -->
                <div id="cake-cinematic-container-<?= $slide_num ?>" class="absolute inset-0 w-full h-full bg-black z-[100] flex flex-col justify-center items-center overflow-hidden pointer-events-none opacity-0 select-none transition-all duration-[1000ms]" style="background: radial-gradient(circle at center, rgba(12, 10, 24, 1) 0%, rgba(0, 0, 0, 1) 100%);">
                    
                    <!-- Spotlight overlay -->
                    <div id="cinematic-spotlight-<?= $slide_num ?>" class="absolute inset-0 bg-radial-spotlight opacity-0 transition-opacity duration-[3000ms] pointer-events-none" style="background: radial-gradient(circle at 50% 65%, rgba(255, 180, 200, 0.15) 0%, rgba(0, 0, 0, 0) 65%);"></div>
                    
                    <!-- Fireworks canvas -->
                    <canvas id="cinematic-fireworks-<?= $slide_num ?>" class="absolute inset-0 w-full h-full pointer-events-none z-10"></canvas>
                    
                    <!-- Balloon container -->
                    <div id="cinematic-balloons-<?= $slide_num ?>" class="absolute inset-0 w-full h-full pointer-events-none overflow-hidden z-20"></div>

                    <!-- Floating memory photos layer -->
                    <div id="cinematic-memories-<?= $slide_num ?>" class="absolute inset-0 w-full h-full pointer-events-none z-30"></div>

                    <!-- Top Message Area -->
                    <div class="absolute top-[10%] inset-x-6 text-center z-40 flex flex-col items-center">
                        <span id="cinematic-subtitle-<?= $slide_num ?>" class="text-[10px] font-bold uppercase tracking-[0.3em] text-pink-400/80 mb-2 opacity-0 transform -translate-y-4 transition-all duration-[1000ms]"><?= $slide_subtitle ?></span>
                        <h2 id="cinematic-title-<?= $slide_num ?>" class="text-2xl sm:text-3xl font-extrabold font-heading text-transparent bg-clip-text bg-gradient-to-r from-amber-200 via-pink-400 to-amber-200 drop-shadow-[0_4px_12px_rgba(244,63,94,0.3)] min-h-[40px]"></h2>
                    </div>

                    <!-- Immersive Interactive Cake area -->
                    <div id="cinematic-viewport-<?= $slide_num ?>" class="relative flex flex-col justify-center items-center w-full max-w-lg aspect-square scale-90 opacity-0 transform translate-y-24 transition-all duration-[1500ms] z-40">
                        
                        <!-- 3D Shadow -->
                        <div class="absolute bottom-[22%] w-[260px] h-[35px] bg-black/60 blur-md rounded-full transform scale-x-110 pointer-events-none"></div>
                        
                        <!-- Main interactive SVG cake -->
                        <div id="cinematic-cake-wrapper-<?= $slide_num ?>" class="relative w-full h-[65%] flex justify-center items-center transition-transform duration-[1500ms]" style="perspective: 1200px;">
                            <svg viewBox="0 0 500 600" class="w-full h-full select-none">
                              <!-- SVG DEFS -->
                              <defs>
                                <radialGradient id="cake-top-grad-<?= $slide_num ?>" cx="50%" cy="50%" r="50%">
                                  <stop offset="0%" stop-color="#fff5f8" />
                                  <stop offset="65%" stop-color="#ffcedc" />
                                  <stop offset="100%" stop-color="#f43f5e" />
                                </radialGradient>
                                <linearGradient id="cake-left-side-<?= $slide_num ?>" x1="0%" y1="0%" x2="100%" y2="0%">
                                  <stop offset="0%" stop-color="#991b1b" />
                                  <stop offset="50%" stop-color="#dc2626" />
                                  <stop offset="100%" stop-color="#b91c1c" />
                                </linearGradient>
                                <linearGradient id="cake-right-side-<?= $slide_num ?>" x1="0%" y1="0%" x2="100%" y2="0%">
                                  <stop offset="0%" stop-color="#b91c1c" />
                                  <stop offset="50%" stop-color="#dc2626" />
                                  <stop offset="100%" stop-color="#7f1d1d" />
                                </linearGradient>
                                <linearGradient id="sponge-layer-<?= $slide_num ?>" x1="0%" y1="0%" x2="0%" y2="100%">
                                  <stop offset="0%" stop-color="#451a03" />
                                  <stop offset="30%" stop-color="#3b1301" />
                                  <stop offset="70%" stop-color="#1c0a00" />
                                  <stop offset="100%" stop-color="#451a03" />
                                </linearGradient>
                                <linearGradient id="cream-layer-<?= $slide_num ?>" x1="0%" y1="0%" x2="0%" y2="100%">
                                  <stop offset="0%" stop-color="#ffffff" />
                                  <stop offset="100%" stop-color="#fff0f5" />
                                </linearGradient>
                                <radialGradient id="flame-grad-<?= $slide_num ?>" cx="50%" cy="40%" r="60%">
                                  <stop offset="0%" stop-color="#ffffff" />
                                  <stop offset="20%" stop-color="#fef08a" />
                                  <stop offset="50%" stop-color="#f59e0b" />
                                  <stop offset="100%" stop-color="#ef4444" stop-opacity="0" />
                                </radialGradient>
                                <filter id="flame-glow-<?= $slide_num ?>" x="-50%" y="-50%" width="200%" height="200%">
                                  <feGaussianBlur stdDeviation="8" result="blur" />
                                  <feComposite in="SourceGraphic" in2="blur" operator="over" />
                                </filter>
                              </defs>

                              <!-- Left Half Group -->
                              <g id="svg-cake-left-<?= $slide_num ?>" style="transition: transform 1.5s cubic-bezier(0.25, 1, 0.5, 1); transform-origin: 250px 320px;">
                                <!-- Plate left -->
                                <path d="M250,510 L70,510 C70,528 150,542 250,542 Z" fill="#4c1d95" opacity="0.8" />
                                <path d="M250,500 L90,500 C90,514 160,525 250,525 Z" fill="#f5f3ff" opacity="0.95" />

                                <!-- Sponge Base left -->
                                <path d="M250,500 L120,500 C120,430 120,360 120,320 L250,320 Z" fill="url(#cake-left-side-<?= $slide_num ?>)" />
                                
                                <!-- Top Ellipse left -->
                                <path d="M250,320 L120,320 A130,45 0 0,1 250,275 Z" fill="url(#cake-top-grad-<?= $slide_num ?>)" />

                                <!-- Frosting drips left -->
                                <path d="M120,320 Q130,355 140,320 Q150,365 160,320 Q175,350 190,320 Q205,375 220,320 Q235,345 250,320" fill="none" stroke="#ffffff" stroke-width="9" stroke-linecap="round" />
                                <path d="M120,320 Q130,355 140,320 Q150,365 160,320 Q175,350 190,320 Q205,375 220,320 Q235,345 250,320 Z" fill="#ffffff" opacity="0.9" />

                                <!-- Cherries/Whipped cream left -->
                                <circle cx="150" cy="295" r="9" fill="#991b1b" />
                                <circle cx="150" cy="295" r="8" fill="#ef4444" />
                                <path d="M148,287 Q153,265 168,275" fill="none" stroke="#15803d" stroke-width="2" />
                                <circle cx="200" cy="285" r="9" fill="#991b1b" />
                                <circle cx="200" cy="285" r="8" fill="#ef4444" />
                                <path d="M198,277 Q203,255 218,265" fill="none" stroke="#15803d" stroke-width="2" />

                                <!-- Inside Cut-Face Left (revealed on split) -->
                                <g id="svg-cut-face-left-<?= $slide_num ?>" style="opacity: 0; transition: opacity 0.5s;">
                                  <path d="M250,320 L250,500 L234,480 L234,300 Z" fill="url(#sponge-layer-<?= $slide_num ?>)" />
                                  <polygon points="250,350 250,362 234,342 234,330" fill="url(#cream-layer-<?= $slide_num ?>)" />
                                  <polygon points="250,390 250,402 234,382 234,370" fill="#f43f5e" />
                                  <polygon points="250,430 250,442 234,422 234,410" fill="url(#cream-layer-<?= $slide_num ?>)" />
                                  <polygon points="250,470 250,482 234,462 234,450" fill="url(#cream-layer-<?= $slide_num ?>)" />
                                </g>

                                <!-- Candles left (Candle 1 & 2) -->
                                <g id="svg-candle-1-<?= $slide_num ?>" style="opacity: 0; transition: opacity 0.5s;">
                                  <rect x="155" y="210" width="8" height="75" fill="#3b82f6" rx="2" />
                                  <path d="M155,210 L163,210 L163,222 L155,222 Z" fill="#ffffff" />
                                  <line x1="159" y1="210" x2="159" y2="200" stroke="#f43f5e" stroke-width="2" />
                                  <path id="svg-flame-1-<?= $slide_num ?>" d="M159,200 C165,190 165,178 159,165 C153,178 153,190 159,200 Z" fill="url(#flame-grad-<?= $slide_num ?>)" filter="url(#flame-glow-<?= $slide_num ?>)" style="opacity: 0; transform-origin: 159px 200px;" class="animate-pulse" />
                                </g>
                                <g id="svg-candle-2-<?= $slide_num ?>" style="opacity: 0; transition: opacity 0.5s;">
                                  <rect x="205" y="200" width="8" height="80" fill="#a855f7" rx="2" />
                                  <path d="M205,200 L213,200 L213,212 L205,212 Z" fill="#ffffff" />
                                  <line x1="209" y1="200" x2="209" y2="190" stroke="#f43f5e" stroke-width="2" />
                                  <path id="svg-flame-2-<?= $slide_num ?>" d="M209,190 C215,180 215,168 209,155 C203,168 203,180 209,190 Z" fill="url(#flame-grad-<?= $slide_num ?>)" filter="url(#flame-glow-<?= $slide_num ?>)" style="opacity: 0; transform-origin: 209px 190px;" class="animate-pulse" />
                                </g>
                              </g>

                              <!-- Right Half Group -->
                              <g id="svg-cake-right-<?= $slide_num ?>" style="transition: transform 1.5s cubic-bezier(0.25, 1, 0.5, 1); transform-origin: 250px 320px;">
                                <!-- Plate right -->
                                <path d="M250,510 L430,510 C430,528 350,542 250,542 Z" fill="#4c1d95" opacity="0.8" />
                                <path d="M250,500 L410,500 C410,514 340,525 250,525 Z" fill="#f5f3ff" opacity="0.95" />

                                <!-- Sponge Base right -->
                                <path d="M250,500 L380,500 C380,430 380,360 380,320 L250,320 Z" fill="url(#cake-right-side-<?= $slide_num ?>)" />
                                
                                <!-- Top Ellipse right -->
                                <path d="M250,320 L380,320 A130,45 0 0,0 250,275 Z" fill="url(#cake-top-grad-<?= $slide_num ?>)" />

                                <!-- Frosting drips right -->
                                <path d="M250,320 Q265,345 280,320 Q295,375 310,320 Q325,350 340,320 Q350,365 360,320 Q370,355 380,320" fill="none" stroke="#ffffff" stroke-width="9" stroke-linecap="round" />
                                <path d="M250,320 Q265,345 280,320 Q295,375 310,320 Q325,350 340,320 Q350,365 360,320 Q370,355 380,320 Z" fill="#ffffff" opacity="0.9" />

                                <!-- Cherries/Whipped cream right -->
                                <circle cx="300" cy="285" r="9" fill="#991b1b" />
                                <circle cx="300" cy="285" r="8" fill="#ef4444" />
                                <path d="M298,277 Q303,255 318,265" fill="none" stroke="#15803d" stroke-width="2" />
                                <circle cx="350" cy="295" r="9" fill="#991b1b" />
                                <circle cx="350" cy="295" r="8" fill="#ef4444" />
                                <path d="M348,287 Q353,265 368,275" fill="none" stroke="#15803d" stroke-width="2" />

                                <!-- Inside Cut-Face Right (revealed on split) -->
                                <g id="svg-cut-face-right-<?= $slide_num ?>" style="opacity: 0; transition: opacity 0.5s;">
                                  <path d="M250,320 L250,500 L266,480 L266,300 Z" fill="url(#sponge-layer-<?= $slide_num ?>)" />
                                  <polygon points="250,350 250,362 266,342 266,330" fill="url(#cream-layer-<?= $slide_num ?>)" />
                                  <polygon points="250,390 250,402 266,382 266,370" fill="#f43f5e" />
                                  <polygon points="250,430 250,442 266,422 266,410" fill="url(#cream-layer-<?= $slide_num ?>)" />
                                  <polygon points="250,470 250,482 266,462 266,450" fill="url(#cream-layer-<?= $slide_num ?>)" />
                                </g>

                                <!-- Candles right (Candle 3, 4 & 5) -->
                                <g id="svg-candle-3-<?= $slide_num ?>" style="opacity: 0; transition: opacity 0.5s;">
                                  <rect x="246" y="190" width="8" height="85" fill="#f43f5e" rx="2" />
                                  <path d="M246,190 L254,190 L254,202 L246,202 Z" fill="#ffffff" />
                                  <line x1="250" y1="190" x2="250" y2="180" stroke="#f43f5e" stroke-width="2" />
                                  <path id="svg-flame-3-<?= $slide_num ?>" d="M250,180 C256,170 256,158 250,145 C244,158 244,170 250,180 Z" fill="url(#flame-grad-<?= $slide_num ?>)" filter="url(#flame-glow-<?= $slide_num ?>)" style="opacity: 0; transform-origin: 250px 180px;" class="animate-pulse" />
                                </g>
                                <g id="svg-candle-4-<?= $slide_num ?>" style="opacity: 0; transition: opacity 0.5s;">
                                  <rect x="286" y="200" width="8" height="80" fill="#10b981" rx="2" />
                                  <path d="M286,200 L294,200 L294,212 L286,212 Z" fill="#ffffff" />
                                  <line x1="290" y1="200" x2="290" y2="190" stroke="#f43f5e" stroke-width="2" />
                                  <path id="svg-flame-4-<?= $slide_num ?>" d="M290,190 C296,180 296,168 290,155 C284,168 284,180 290,190 Z" fill="url(#flame-grad-<?= $slide_num ?>)" filter="url(#flame-glow-<?= $slide_num ?>)" style="opacity: 0; transform-origin: 290px 190px;" class="animate-pulse" />
                                </g>
                                <g id="svg-candle-5-<?= $slide_num ?>" style="opacity: 0; transition: opacity 0.5s;">
                                  <rect x="336" y="210" width="8" height="75" fill="#f59e0b" rx="2" />
                                  <path d="M336,210 L344,210 L344,222 L336,222 Z" fill="#ffffff" />
                                  <line x1="340" y1="210" x2="340" y2="200" stroke="#f43f5e" stroke-width="2" />
                                  <path id="svg-flame-5-<?= $slide_num ?>" d="M340,200 C346,190 346,178 340,165 C334,178 334,190 340,200 Z" fill="url(#flame-grad-<?= $slide_num ?>)" filter="url(#flame-glow-<?= $slide_num ?>)" style="opacity: 0; transform-origin: 340px 200px;" class="animate-pulse" />
                                </g>
                              </g>
                            </svg>
                        </div>

                        <!-- 3D Knife element -->
                        <div id="cinematic-knife-<?= $slide_num ?>" class="absolute pointer-events-none opacity-0 select-none transition-opacity duration-300 z-50" style="width: 140px; height: 50px; transform: translateX(200px) translateY(-80px) rotate(45deg); transform-origin: 20px 25px;">
                            <svg viewBox="0 0 150 50" class="w-full h-full drop-shadow-[0_8px_16px_rgba(0,0,0,0.5)]">
                              <defs>
                                <linearGradient id="blade-shine-<?= $slide_num ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                  <stop offset="0%" stop-color="#f3f4f6" />
                                  <stop offset="35%" stop-color="#9ca3af" />
                                  <stop offset="45%" stop-color="#ffffff" />
                                  <stop offset="55%" stop-color="#d1d5db" />
                                  <stop offset="100%" stop-color="#4b5563" />
                                </linearGradient>
                                <linearGradient id="handle-grad-<?= $slide_num ?>" x1="0%" y1="0%" x2="0%" y2="100%">
                                  <stop offset="0%" stop-color="#78350f" />
                                  <stop offset="100%" stop-color="#451a03" />
                                </linearGradient>
                              </defs>
                              <path d="M15,25 L100,10 L100,40 L15,25 Z" fill="url(#blade-shine-<?= $slide_num ?>)" />
                              <path d="M15,25 L100,10 L100,15 L22,25 Z" fill="#ffffff" opacity="0.6" />
                              <rect x="100" y="18" width="45" height="14" rx="4" fill="url(#handle-grad-<?= $slide_num ?>)" />
                              <rect x="98" y="12" width="4" height="26" rx="1" fill="#d1d5db" />
                            </svg>
                        </div>
                        
                        <!-- Drag cutting indicator line/arrow -->
                        <div id="cinematic-drag-indicator-<?= $slide_num ?>" class="absolute inset-x-0 top-[35%] bottom-[32%] w-1 border-l-2 border-dashed border-pink-400/40 left-1/2 -translate-x-1/2 opacity-0 transition-opacity duration-500 pointer-events-none flex flex-col justify-between items-center py-2">
                            <span class="text-[9px] text-pink-300 font-bold bg-pink-900/60 px-1.5 py-0.5 rounded backdrop-blur">DRAG DOWN</span>
                            <span class="text-xl animate-bounce text-pink-400">▼</span>
                        </div>
                        
                        <!-- Drag gesture capture overlay -->
                        <div id="cinematic-drag-overlay-<?= $slide_num ?>" class="absolute inset-0 w-full h-full cursor-ns-resize z-55 hidden"></div>
                    </div>

                    <!-- Bottom interactive/prompt area -->
                    <div class="absolute bottom-[10%] inset-x-6 text-center z-40">
                        <p id="cinematic-hint-<?= $slide_num ?>" class="text-xs font-semibold text-pink-400/80 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] opacity-0 transition-opacity duration-[1000ms] animate-pulse">Loading surprise...</p>
                        
                        <button id="cinematic-continue-<?= $slide_num ?>" onclick="finishCakeCinematic(<?= $slide_num ?>)" class="mx-auto mt-6 px-10 py-4 bg-gradient-to-r from-pink-500 via-rose-500 to-amber-500 text-white font-extrabold rounded-2xl text-sm uppercase tracking-wider shadow-[0_0_30px_rgba(244,63,94,0.5)] border border-pink-400/20 active:scale-95 hover:opacity-95 transition-all duration-300 transform scale-0 opacity-0 hidden">
                            Continue Celebration ❤️
                        </button>
                    </div>

                </div>

            <?php // ────────── 4. PROPOSAL: ROSE REVEAL ──────────
            elseif ($slide_type === 'proposal_rose_reveal'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="rose-container-<?= $slide_num ?>">
                        <div class="rose-bloom-wrapper relative w-48 h-48 cursor-pointer flex justify-center items-center animate-pulse" onclick="bloomRoseTrigger(<?= $slide_num ?>)">
                            <svg viewBox="0 0 100 100" class="w-36 h-36" id="rose-svg-<?= $slide_num ?>">
                                <path d="M50 50 Q48 75 50 90" stroke="green" stroke-width="4" fill="none"/>
                                <path d="M50 70 Q30 65 40 55 Q48 60 50 70" fill="green"/>
                                <path d="M50 60 Q70 55 60 45 Q52 50 50 60" fill="green"/>
                                <g class="rose-petals origin-center" id="rose-petals-<?= $slide_num ?>">
                                    <path d="M50 20 C40 10 30 30 50 50 C70 30 60 10 50 20 Z" fill="#f43f5e" class="petal petal-1 origin-center"/>
                                    <path d="M50 25 C35 15 35 40 50 50 C65 40 65 15 50 25 Z" fill="#e11d48" class="petal petal-2 origin-center scale-90 rotate-45"/>
                                    <path d="M50 25 C35 15 35 40 50 50 C65 40 65 15 50 25 Z" fill="#be123c" class="petal petal-3 origin-center scale-90 -rotate-45"/>
                                </g>
                            </svg>
                        </div>
                        <p class="text-xs font-semibold mt-4 animate-pulse" style="color: <?= $theme['accent'] ?>" id="rose-hint-<?= $slide_num ?>">Tap the rose to make it bloom 🌹</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="rose-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Make the Rose Bloom
                        </button>
                    </div>
                </div>

            <?php // ────────── 5. PROPOSAL: HEART ASSEMBLY ──────────
            elseif ($slide_type === 'proposal_heart_assembly'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center overflow-hidden" id="heart-assembly-<?= $slide_num ?>">
                        <div class="heart-target relative w-48 h-48 border-2 border-dashed border-rose-300/40 rounded-full flex justify-center items-center">
                            <span class="text-8xl opacity-10 select-none">❤️</span>
                            <div class="heart-piece cursor-pointer absolute text-6xl select-none transition-all duration-500 ease-out z-10" 
                                 id="heart-left-<?= $slide_num ?>"
                                 style="transform: translate(-90px, -40px) rotate(-35deg);"
                                 onclick="snapHeartPiece('left', <?= $slide_num ?>)">💔</div>
                            <div class="heart-piece cursor-pointer absolute text-6xl select-none transition-all duration-500 ease-out z-10" 
                                 id="heart-right-<?= $slide_num ?>"
                                 style="transform: translate(90px, 40px) rotate(25deg);"
                                 onclick="snapHeartPiece('right', <?= $slide_num ?>)">💔</div>
                        </div>
                        <p class="text-xs font-semibold mt-6 animate-pulse" style="color: <?= $theme['accent'] ?>" id="heart-hint-<?= $slide_num ?>">Tap the pieces to mend my heart 🩹</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="heart-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Mend the Heart to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 6. PROPOSAL: LOVE METER ──────────
            elseif ($slide_type === 'proposal_love_meter'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="love-meter-<?= $slide_num ?>">
                        <div class="love-percentage text-6xl font-extrabold text-rose-500 font-heading mb-4 animate-pulse" id="love-pct-<?= $slide_num ?>">0%</div>
                        <div class="relative w-32 h-32 flex justify-center items-center mb-8">
                            <svg viewBox="0 0 32 29.6" class="w-full h-full text-rose-100 dark:text-rose-950/20 fill-current">
                                <path d="M23.6,0c-3.4,0-6.3,2.7-7.6,5.6C14.7,2.7,11.8,0,8.4,0C3.8,0,0,3.8,0,8.4c0,9.4,9.5,11.9,16,21.2
                                    c6.1-9.3,16-12.1,16-21.2C32,3.8,28.2,0,23.6,0z"/>
                            </svg>
                            <div class="absolute inset-0 overflow-hidden" id="heart-fill-clip-<?= $slide_num ?>" style="clip-path: inset(100% 0px 0px 0px);">
                                <svg viewBox="0 0 32 29.6" class="w-full h-full text-rose-600 fill-current">
                                    <path d="M23.6,0c-3.4,0-6.3,2.7-7.6,5.6C14.7,2.7,11.8,0,8.4,0C3.8,0,0,3.8,0,8.4c0,9.4,9.5,11.9,16,21.2
                                        c6.1-9.3,16-12.1,16-21.2C32,3.8,28.2,0,23.6,0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="w-full max-w-xs px-4">
                            <input type="range" min="0" max="100" value="0" class="w-full h-2 bg-rose-200 rounded-lg appearance-none cursor-pointer accent-rose-600 focus:outline-none" 
                                   id="love-slider-<?= $slide_num ?>"
                                   oninput="updateLoveMeter(this.value, <?= $slide_num ?>)"
                                   onchange="checkLoveMeter(this.value, <?= $slide_num ?>)">
                        </div>
                        <p class="text-xs font-semibold mt-4 text-rose-400">Slide it to 100%! 💕</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="love-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Fill the Heart to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 7. PROPOSAL: RING BOX REVEAL ──────────
            elseif ($slide_type === 'proposal_final_box'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="ring-box-container-<?= $slide_num ?>">
                        <div class="ring-box-wrapper relative w-48 h-48 cursor-pointer flex flex-col justify-center items-center" onclick="openRingBox(<?= $slide_num ?>)">
                            <div class="ring-box-lid w-32 h-16 bg-amber-600 rounded-t-3xl shadow-md border-b-2 border-amber-700 transition-transform duration-500 origin-bottom" id="ring-lid-<?= $slide_num ?>"></div>
                            <div class="ring-box-base w-32 h-16 bg-amber-700 rounded-b-3xl shadow-lg relative flex items-center justify-center">
                                <div class="diamond-ring text-4xl absolute -top-8 animate-bounce opacity-0 transition-opacity duration-500" id="ring-diamond-<?= $slide_num ?>">💍</div>
                            </div>
                        </div>
                        <p class="text-xs font-semibold mt-6 animate-pulse" style="color: <?= $theme['accent'] ?>" id="ring-hint-<?= $slide_num ?>">Tap the box to open 💍</p>
                    </div>
                    <div class="flex-grow flex-col justify-center items-center text-center hidden w-full" id="ring-question-<?= $slide_num ?>">
                        <div class="text-5xl mb-6 animate-bounce">💍💖</div>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mb-8 font-heading max-w-xs leading-tight" id="proposal-prompt-<?= $slide_num ?>">
                            <?= h($page['proposal_question']) ?>
                        </h2>
                        <div class="relative w-full h-32 flex justify-center items-center">
                            <button onclick="handleProposalYes(<?= $slide_num ?>)" id="proposal-yes-btn-<?= $slide_num ?>" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-sm shadow-lg <?= $theme['btn_shadow'] ?> mr-4 active:scale-95 transition z-10">
                                <?= h($slide['yes_text'] ?? 'Yes! ❤️') ?>
                            </button>
                            <button id="proposal-no-btn-<?= $slide_num ?>" class="px-8 py-3 <?= $is_dark ? 'bg-white/10 text-white/70' : 'bg-slate-200 text-slate-700' ?> font-bold rounded-2xl text-sm transition absolute proposal-runaway-btn">
                                <?= h($slide['no_text'] ?? 'No') ?>
                            </button>
                        </div>
                    </div>
                    <div class="flex-grow flex flex-col justify-between items-center text-center hidden w-full animate-fadeIn" id="proposal-success-<?= $slide_num ?>">
                        <div class="flex-grow flex flex-col justify-center items-center w-full">
                            <div class="text-6xl mb-6 animate-bounce"><?= $slide['success_emoji'] ?? '🥰' ?></div>
                            <h2 class="text-2xl font-extrabold <?= $theme['text_primary'] ?> mb-2 font-heading"><?= rpl($slide['success_title'] ?? 'Yes! 💖', $page) ?></h2>
                            <p class="text-sm <?= $theme['text_muted'] ?> max-w-xs mb-8"><?= rpl($slide['success_msg'] ?? '', $page) ?></p>
                            <div class="space-y-4">
                                <span class="block text-[10px] font-bold <?= $theme['text_muted'] ?> uppercase tracking-widest">Send A Reaction</span>
                                <div class="flex space-x-2 justify-center">
                                    <?php foreach ($reactions_list as $rkey => $rval): ?>
                                        <button onclick="sendReaction('<?= $rkey ?>')" class="w-12 h-12 <?= $is_dark ? 'bg-white/5 border-white/10' : 'bg-white/60 border-white' ?> border hover:scale-110 rounded-2xl text-xl flex items-center justify-center transition shadow-sm"><?= $rval['emoji'] ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-center pt-6 w-full max-w-xs">
                            <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 animate-pulse">
                                Reply to <?= h($page['sender_name']) ?> 💌
                            </button>
                        </div>
                    </div>
                </div>

            <?php // ────────── PROPOSAL: ROSE CINEMATIC ──────────
            elseif ($slide_type === 'proposal_rose_cinematic'): ?>
                <div id="rose-cinematic-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000">
                    <div id="rose-cinematic-glow-<?= $slide_num ?>" class="absolute w-[300px] h-[300px] rounded-full bg-rose-500/10 blur-[80px] pointer-events-none transition-all duration-[2000ms] scale-50 opacity-0 z-30"></div>
                    <div id="rose-cinematic-particles-<?= $slide_num ?>" class="absolute inset-0 pointer-events-none z-50"></div>
                    
                    <div class="relative w-64 h-[350px] flex justify-center items-center z-40 transform scale-125">
                        <svg id="rose-cinematic-svg-<?= $slide_num ?>" viewBox="0 0 400 500" class="w-full h-full drop-shadow-[0_0_35px_rgba(244,63,94,0.4)]">
                            <path d="M200 480 Q190 320 200 180" stroke="#065f46" stroke-width="6" fill="none" id="rose-stem-path-<?= $slide_num ?>" stroke-dasharray="350" stroke-dashoffset="350" style="transition: stroke-dashoffset 1.5s ease-out;"></path>
                            <path d="M200 380 C150 380 140 330 200 320" fill="#047857" opacity="0" id="rose-leaf-1-<?= $slide_num ?>" style="transition: opacity 1s;"></path>
                            <path d="M200 280 C250 280 260 230 200 220" fill="#047857" opacity="0" id="rose-leaf-2-<?= $slide_num ?>" style="transition: opacity 1s;"></path>
                            
                            <!-- Outer Petals -->
                            <path d="M200 180 C100 120 140 50 200 90 C260 50 300 120 200 180 Z" fill="url(#rose-grad-outer-<?= $slide_num ?>)" class="rose-petal-path" opacity="0" style="--petal-rotation: 12deg; transform-origin: 200px 180px;"></path>
                            <path d="M200 180 C110 100 130 30 200 80 C270 30 290 100 200 180 Z" fill="url(#rose-grad-outer-<?= $slide_num ?>)" class="rose-petal-path" opacity="0" style="--petal-rotation: -12deg; transform-origin: 200px 180px;"></path>
                            
                            <!-- Middle Petals -->
                            <path d="M200 180 C120 130 150 70 200 100 C250 70 280 130 200 180 Z" fill="url(#rose-grad-mid-<?= $slide_num ?>)" class="rose-petal-path" opacity="0" style="--petal-rotation: 25deg; transform-origin: 200px 180px;"></path>
                            <path d="M200 180 C120 130 150 70 200 100 C250 70 280 130 200 180 Z" fill="url(#rose-grad-mid-<?= $slide_num ?>)" class="rose-petal-path" opacity="0" style="--petal-rotation: -25deg; transform-origin: 200px 180px;"></path>
                            <path d="M200 180 C130 140 160 80 200 110 C240 80 270 140 200 180 Z" fill="url(#rose-grad-mid-<?= $slide_num ?>)" class="rose-petal-path" opacity="0" style="--petal-rotation: 5deg; transform-origin: 200px 180px;"></path>
                            
                            <!-- Inner Petals -->
                            <path d="M200 180 C140 140 170 90 200 115 C230 90 260 140 200 180 Z" fill="url(#rose-grad-inner-<?= $slide_num ?>)" class="rose-petal-path" opacity="0" style="--petal-rotation: 40deg; transform-origin: 200px 180px;"></path>
                            <path d="M200 180 C140 140 170 90 200 115 C230 90 260 140 200 180 Z" fill="url(#rose-grad-inner-<?= $slide_num ?>)" class="rose-petal-path" opacity="0" style="--petal-rotation: -40deg; transform-origin: 200px 180px;"></path>
                            <circle cx="200" cy="140" r="10" fill="url(#rose-grad-center-<?= $slide_num ?>)" class="rose-petal-path" opacity="0" style="--petal-rotation: 0deg; transform-origin: 200px 180px;"></circle>
                            
                            <defs>
                                <radialGradient id="rose-grad-outer-<?= $slide_num ?>" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#fda4af"></stop>
                                    <stop offset="70%" stop-color="#be123c"></stop>
                                    <stop offset="100%" stop-color="#9f1239"></stop>
                                </radialGradient>
                                <radialGradient id="rose-grad-mid-<?= $slide_num ?>" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#f43f5e"></stop>
                                    <stop offset="80%" stop-color="#9f1239"></stop>
                                    <stop offset="100%" stop-color="#881337"></stop>
                                </radialGradient>
                                <radialGradient id="rose-grad-inner-<?= $slide_num ?>" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#e11d48"></stop>
                                    <stop offset="100%" stop-color="#4c0519"></stop>
                                </radialGradient>
                                <radialGradient id="rose-grad-center-<?= $slide_num ?>" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#fda4af"></stop>
                                    <stop offset="100%" stop-color="#881337"></stop>
                                </radialGradient>
                            </defs>
                        </svg>
                    </div>

                    <div class="absolute bottom-[10%] inset-x-6 text-center z-50 flex flex-col items-center">
                        <h2 id="rose-cinematic-name-<?= $slide_num ?>" class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-pink-400 via-amber-200 to-rose-400 font-heading tracking-wide mb-3 min-h-[40px]"></h2>
                        <p id="rose-cinematic-message-<?= $slide_num ?>" class="text-sm font-medium text-pink-100/90 max-w-xs leading-relaxed min-h-[50px]"></p>
                        
                        <button id="rose-cinematic-continue-<?= $slide_num ?>" onclick="finishRoseCinematic(<?= $slide_num ?>)" class="mt-8 px-10 py-4 bg-gradient-to-r from-rose-500 to-pink-600 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(244,63,94,0.5)] border border-rose-400/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Open My Soul ❤️
                        </button>
                    </div>
                </div>

            <?php // ────────── PROPOSAL: CONSTELLATION ──────────
            elseif ($slide_type === 'proposal_constellation'): ?>
                <div id="constellation-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-slate-950">
                    <canvas id="constellation-canvas-<?= $slide_num ?>" class="cinematic-canvas z-10"></canvas>
                    <div id="constellation-photos-<?= $slide_num ?>" class="absolute inset-0 pointer-events-auto z-20 overflow-hidden"></div>
                    
                    <div class="absolute top-[8%] inset-x-6 text-center z-30">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>

                    <div class="absolute bottom-[8%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <button id="constellation-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(99,102,241,0.5)] border border-indigo-400/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Step Into My Heart ✨
                        </button>
                    </div>
                </div>

            <?php // ────────── PROPOSAL: HEART FORMATION ──────────
            elseif ($slide_type === 'proposal_heart_formation'): ?>
                <div id="heart-formation-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-stone-950">
                    <canvas id="heart-formation-canvas-<?= $slide_num ?>" class="cinematic-canvas z-10"></canvas>
                    <div id="heart-formation-gallery-<?= $slide_num ?>" class="absolute inset-0 pointer-events-auto z-20 overflow-hidden flex flex-col justify-center items-center">
                        <h2 id="heart-formation-text-<?= $slide_num ?>" class="text-2xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-rose-400 to-pink-300 font-heading tracking-wide text-center opacity-0 scale-75 transition-all duration-[1500ms] pointer-events-none mb-4 max-w-xs">My Heart Belongs to You</h2>
                        <div id="heart-formation-photos-<?= $slide_num ?>" class="relative w-48 h-48 flex justify-center items-center pointer-events-none"></div>
                    </div>

                    <div class="absolute top-[8%] inset-x-6 text-center z-30">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-rose-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>

                    <div class="absolute bottom-[8%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <button id="heart-formation-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-rose-500 to-pink-500 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(244,63,94,0.5)] border border-rose-400/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Read My Letter ✍️
                        </button>
                    </div>
                </div>

            <?php // ────────── PROPOSAL: LOVE LETTER ──────────
            elseif ($slide_type === 'proposal_love_letter'): ?>
                <div id="love-letter-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#1e1510]">
                    <div id="love-letter-paper-<?= $slide_num ?>" class="parchment-paper">
                        <div class="overflow-y-auto max-h-[60vh] pr-2 space-y-4 text-[#5c3e21] text-lg leading-relaxed relative" id="love-letter-text-container-<?= $slide_num ?>">
                            <svg id="love-letter-pen-<?= $slide_num ?>" class="quill-pen absolute w-8 h-8 pointer-events-none transition-all duration-[80ms] z-30 hidden" viewBox="0 0 100 100" style="transform: rotate(-30deg);">
                                <path d="M90 10 L40 60 C35 65 30 75 30 80 C30 75 40 70 45 65 L95 15 Z" fill="#5c3e21"></path>
                                <path d="M30 80 L20 90 L25 85 Z" fill="#000"></path>
                            </svg>
                            <div id="love-letter-lines-<?= $slide_num ?>" class="space-y-4"></div>
                        </div>
                    </div>

                    <div class="absolute bottom-[6%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <button id="love-letter-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-amber-700 to-amber-900 text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            See Our Future Together 🌀
                        </button>
                    </div>
                </div>

            <?php // ────────── PROPOSAL: FUTURE PORTAL ──────────
            elseif ($slide_type === 'proposal_future_portal'): ?>
                <div id="portal-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-slate-950">
                    <div id="portal-ring-container-<?= $slide_num ?>" class="relative w-64 h-64 flex justify-center items-center transition-all duration-[2000ms] scale-75 z-20">
                        <div class="absolute w-44 h-44 rounded-full blur-md z-30" id="portal-center-<?= $slide_num ?>" style="background: radial-gradient(circle, #fff 0%, #4338ca 50%, transparent 100%);"></div>
                        
                        <svg class="w-full h-full text-indigo-400 drop-shadow-[0_0_35px_rgba(99,102,241,0.5)]" viewBox="0 0 200 200">
                            <circle cx="100" cy="100" r="90" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="15 8" class="portal-ring-1"></circle>
                            <circle cx="100" cy="100" r="75" fill="none" stroke="#ec4899" stroke-width="1.5" stroke-dasharray="25 15" class="portal-ring-2"></circle>
                            <circle cx="100" cy="100" r="60" fill="none" stroke="#fff" stroke-width="1" stroke-dasharray="10 5" class="portal-ring-3"></circle>
                        </svg>
                    </div>

                    <div id="portal-cards-<?= $slide_num ?>" class="absolute inset-0 pointer-events-auto z-30"></div>

                    <div class="absolute top-[8%] inset-x-6 text-center z-40">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-indigo-300/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>

                    <div class="absolute bottom-[8%] inset-x-6 text-center z-40 flex flex-col items-center">
                        <button id="portal-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(99,102,241,0.5)] border border-indigo-400/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Count the Moments ⏱️
                        </button>
                    </div>
                </div>

            <?php // ────────── PROPOSAL: COUNTDOWN ──────────
            elseif ($slide_type === 'proposal_countdown'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full">
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                    <h2 class="text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1 font-heading tracking-wide mb-8"><?= $slide_title ?></h2>
                    
                    <div class="relative w-full max-w-sm px-6 py-8 rounded-3xl <?= $is_dark ? 'glass-dark border border-white/5' : 'glass border border-black/5' ?> shadow-2xl flex flex-col items-center">
                        <div id="proposal-countdown-display-<?= $slide_num ?>" class="grid grid-cols-3 gap-3 w-full">
                            <div class="flex flex-col items-center p-3 rounded-2xl bg-black/25">
                                <span id="countdown-days-<?= $slide_num ?>" class="text-3xl font-black <?= $theme['text_primary'] ?> font-heading">000</span>
                                <span class="text-[9px] uppercase tracking-wider <?= $theme['text_muted'] ?> font-bold mt-1">Days</span>
                            </div>
                            <div class="flex flex-col items-center p-3 rounded-2xl bg-black/25">
                                <span id="countdown-hours-<?= $slide_num ?>" class="text-3xl font-black <?= $theme['text_primary'] ?> font-heading">00</span>
                                <span class="text-[9px] uppercase tracking-wider <?= $theme['text_muted'] ?> font-bold mt-1">Hours</span>
                            </div>
                            <div class="flex flex-col items-center p-3 rounded-2xl bg-black/25">
                                <span id="countdown-minutes-<?= $slide_num ?>" class="text-3xl font-black <?= $theme['text_primary'] ?> font-heading">00</span>
                                <span class="text-[9px] uppercase tracking-wider <?= $theme['text_muted'] ?> font-bold mt-1">Minutes</span>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-center items-center">
                            <span id="countdown-heart-<?= $slide_num ?>" class="text-5xl drop-shadow-[0_0_15px_rgba(244,63,94,0.6)] cursor-pointer select-none countdown-heart-beat">❤️</span>
                        </div>
                    </div>
                </div>

                <div class="text-center pt-6 w-full z-10">
                    <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-4 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                        Relive Our Memories 💖
                    </button>
                </div>

            <?php // ────────── PROPOSAL: RING BOX CINEMATIC ──────────
            elseif ($slide_type === 'proposal_ring_cinematic'): ?>
                <div id="ring-cinematic-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-slate-950">
                    <div class="ring-spotlight"></div>
                    
                    <div id="ring-cinematic-rays-<?= $slide_num ?>" class="absolute inset-0 pointer-events-none z-10 flex justify-center items-center">
                        <?php for ($ri = 0; $ri < 12; $ri++): ?>
                            <div class="ring-light-ray" style="transform: rotate(<?= $ri * 30 ?>deg);"></div>
                        <?php endfor; ?>
                    </div>

                    <div class="relative w-64 h-64 flex flex-col justify-center items-center z-20 cursor-pointer transition-transform duration-500 scale-100 active:scale-95" id="ring-cinematic-interactive-<?= $slide_num ?>" onclick="openRingCinematic(<?= $slide_num ?>)">
                        <svg viewBox="0 0 200 200" class="w-48 h-48 drop-shadow-[0_10px_25px_rgba(0,0,0,0.8)]">
                            <g id="ring-box-lid-<?= $slide_num ?>" style="transition: transform 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); transform-origin: 100px 100px;">
                                <path d="M40 100 Q40 50 100 50 Q160 50 160 100 Z" fill="url(#box-velvet-grad-<?= $slide_num ?>)" stroke="#1e293b" stroke-width="2"></path>
                                <path d="M50 80 Q100 70 150 80" fill="none" stroke="#f59e0b" stroke-width="1.5" opacity="0.6"></path>
                            </g>

                            <path d="M40 100 L160 100 Q165 140 100 150 Q35 140 40 100 Z" fill="url(#box-velvet-dark-<?= $slide_num ?>)" stroke="#0f172a" stroke-width="2"></path>
                            <ellipse cx="100" cy="120" rx="30" ry="8" fill="#1e0000" opacity="0.9"></ellipse>

                            <g id="ring-box-diamond-g-<?= $slide_num ?>" class="opacity-0 scale-0 origin-center transition-all duration-[1200ms]" style="transform-origin: 100px 120px;">
                                <circle cx="100" cy="112" r="16" fill="none" stroke="#fbbf24" stroke-width="4.5" filter="drop-shadow(0 0 3px rgba(251,191,36,0.5))"></circle>
                                <path d="M92 98 L108 98 L103 105 L97 105 Z" fill="#94a3b8"></path>
                                <g transform="translate(100, 93) scale(0.9)">
                                    <polygon points="0,-12 -12,-2 -5,10 5,10 12,-2" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="0.5"></polygon>
                                    <polygon points="0,-12 -12,-2 -4,-2" fill="#fff" class="diamond-facet" style="--shimmer-delay: 0s;"></polygon>
                                    <polygon points="0,-12 -4,-2 4,-2" fill="#f1f5f9" class="diamond-facet" style="--shimmer-delay: 0.3s;"></polygon>
                                    <polygon points="0,-12 4,-2 12,-2" fill="#cbd5e1" class="diamond-facet" style="--shimmer-delay: 0.6s;"></polygon>
                                    <polygon points="-12,-2 -5,10 -4,-2" fill="#e2e8f0" class="diamond-facet" style="--shimmer-delay: 0.9s;"></polygon>
                                    <polygon points="-4,-2 -5,10 5,10 4,-2" fill="#fff" class="diamond-facet" style="--shimmer-delay: 0.1s;"></polygon>
                                    <polygon points="4,-2 5,10 12,-2" fill="#94a3b8" class="diamond-facet" style="--shimmer-delay: 0.4s;"></polygon>
                                </g>
                            </g>

                            <path d="M40 100 Q35 140 100 150 Q165 140 160 100 Z" fill="url(#box-velvet-grad-<?= $slide_num ?>)" opacity="0.3"></path>

                            <defs>
                                <linearGradient id="box-velvet-grad-<?= $slide_num ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#31102f"></stop>
                                    <stop offset="50%" stop-color="#4c0519"></stop>
                                    <stop offset="100%" stop-color="#1c000f"></stop>
                                </linearGradient>
                                <linearGradient id="box-velvet-dark-<?= $slide_num ?>" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" stop-color="#2a000d"></stop>
                                    <stop offset="100%" stop-color="#0a0005"></stop>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>

                    <div class="absolute bottom-[10%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <p id="ring-cinematic-hint-<?= $slide_num ?>" class="text-xs font-semibold text-rose-400/80 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] animate-pulse">Tap the velvet box to open 💍</p>
                        
                        <button id="ring-cinematic-continue-<?= $slide_num ?>" onclick="finishRingCinematic(<?= $slide_num ?>)" class="mt-8 px-10 py-4 bg-gradient-to-r from-amber-500 via-rose-500 to-pink-500 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_35px_rgba(244,63,94,0.6)] border border-rose-400/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Step Into The Moment 💖
                        </button>
                    </div>
                </div>

            <?php // ────────── PROPOSAL: FINAL CINEMATIC ──────────
            elseif ($slide_type === 'proposal_final_cinematic'): ?>
                <div id="final-cinematic-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-slate-950">
                    <canvas id="final-stars-canvas-<?= $slide_num ?>" class="cinematic-canvas z-10"></canvas>
                    <div id="final-petals-<?= $slide_num ?>" class="absolute inset-0 pointer-events-none z-15"></div>
                    <div id="final-photo-heart-<?= $slide_num ?>" class="absolute inset-0 pointer-events-none z-20 overflow-hidden"></div>
                    
                    <div class="absolute inset-x-6 top-[30%] text-center z-30 flex flex-col items-center justify-center">
                        <h2 id="final-proposal-text-<?= $slide_num ?>" class="text-3xl sm:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-rose-400 via-pink-200 to-amber-200 font-heading leading-tight max-w-xs mb-8 min-h-[80px]"></h2>
                        
                        <div id="final-proposal-options-<?= $slide_num ?>" class="relative w-full h-32 flex justify-center items-center opacity-0 transition-opacity duration-500 pointer-events-auto">
                            <button id="final-yes-btn-<?= $slide_num ?>" onclick="handleFinalProposalYes(<?= $slide_num ?>)" class="px-10 py-4 bg-gradient-to-r from-rose-500 via-pink-500 to-rose-500 text-white font-black rounded-2xl text-sm shadow-[0_0_40px_rgba(244,63,94,0.5)] border border-rose-400/20 active:scale-95 transition-transform duration-200 mr-4 z-40">
                                YES! ❤️
                            </button>
                            <button id="final-no-btn-<?= $slide_num ?>" class="px-8 py-3.5 bg-slate-800/80 border border-slate-700/50 text-slate-300 font-bold rounded-2xl text-xs active:scale-95 transition-transform duration-200 absolute z-30">
                                No 😅
                            </button>
                        </div>
                    </div>

                    <div id="final-celebration-<?= $slide_num ?>" class="absolute inset-0 bg-transparent flex flex-col justify-center items-center text-center p-6 z-50 pointer-events-none opacity-0 scale-75 transition-all duration-[1500ms]">
                        <div class="text-7xl mb-6 animate-bounce">🥰🎉</div>
                        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-rose-400 via-pink-300 to-amber-200 font-heading tracking-wide mb-3">I Knew You'd Say Yes! 💖</h2>
                        <p id="final-success-message-<?= $slide_num ?>" class="text-pink-100/90 text-sm max-w-xs leading-relaxed mb-8">You just made me the happiest person alive! Forever starts now.</p>
                        
                        <div class="space-y-4 pointer-events-auto">
                            <span class="block text-[10px] font-bold text-pink-400/70 uppercase tracking-widest">Send A Reaction</span>
                            <div class="flex space-x-2 justify-center">
                                <?php foreach ($reactions_list as $rkey => $rval): ?>
                                    <button onclick="sendReaction('<?= $rkey ?>')" class="w-12 h-12 bg-white/5 border border-white/10 hover:scale-110 rounded-2xl text-xl flex items-center justify-center transition shadow-sm"><?= $rval['emoji'] ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button id="final-celebration-continue-<?= $slide_num ?>" onclick="finishFinalProposal(<?= $slide_num ?>)" class="mt-8 px-10 py-4 bg-gradient-to-r from-rose-500 to-pink-600 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(244,63,94,0.5)] border border-rose-400/20 active:scale-95 transition duration-300 pointer-events-auto">
                            See Our Story Begin 🌹
                        </button>
                    </div>
                </div>

            <?php // ────────── PROPOSAL: CELEBRATION ──────────
            elseif ($slide_type === 'proposal_celebration'): ?>
                <div id="celebration-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-gradient-to-br from-rose-950 via-slate-950 to-pink-950">
                    <canvas id="celebration-confetti-<?= $slide_num ?>" class="cinematic-canvas z-10"></canvas>
                    <div id="celebration-photos-<?= $slide_num ?>" class="absolute inset-0 pointer-events-auto z-20 overflow-hidden"></div>
                    
                    <div class="absolute inset-x-6 top-[20%] text-center z-30 flex flex-col items-center">
                        <div class="text-6xl mb-4 animate-pulse">🌹💑✨</div>
                        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-rose-400 via-amber-200 to-pink-400 font-heading mb-3">Our Forever Starts Now</h2>
                        <p id="celebration-quote-<?= $slide_num ?>" class="text-sm italic text-pink-200/90 font-serif max-w-xs leading-relaxed min-h-[40px]"></p>
                    </div>

                    <div class="absolute bottom-[10%] inset-x-6 text-center z-30 flex flex-col items-center space-y-3 pointer-events-auto">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-4 bg-gradient-to-r from-rose-500 via-pink-500 to-rose-500 text-white font-black rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(244,63,94,0.5)] active:scale-95 transition">
                            Leave a Message Back 💌
                        </button>
                        
                        <a href="<?= $base_url ?>index.php" class="w-full py-4 bg-white/10 hover:bg-white/15 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider border border-white/20 active:scale-95 transition flex items-center justify-center gap-2">
                            ✨ Create Your Own Story
                        </a>
                    </div>
                </div>

            <?php // ────────── 8. SORRY: BROKEN HEART REPAIR ──────────
            elseif ($slide_type === 'sorry_broken_heart_repair'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center animate-pulse" id="sorry-heart-container-<?= $slide_num ?>">
                        <div class="relative w-48 h-48 cursor-pointer flex justify-center items-center" onclick="repairSorryHeart(<?= $slide_num ?>)">
                            <svg viewBox="0 0 32 29.6" class="w-full h-full text-stone-500 fill-current transition-colors duration-1000" id="sorry-heart-svg-<?= $slide_num ?>">
                                <path d="M23.6,0c-3.4,0-6.3,2.7-7.6,5.6C14.7,2.7,11.8,0,8.4,0C3.8,0,0,3.8,0,8.4c0,9.4,9.5,11.9,16,21.2
                                    c6.1-9.3,16-12.1,16-21.2C32,3.8,28.2,0,23.6,0z"/>
                            </svg>
                            <svg viewBox="0 0 100 100" class="absolute inset-0 w-full h-full stroke-stone-850 stroke-[3] fill-none transition-opacity duration-1000 z-10" id="sorry-heart-cracks-<?= $slide_num ?>">
                                <path d="M50 15 L45 35 L55 50 L48 65 L50 85" />
                                <path d="M30 40 L45 35 L38 55" />
                                <path d="M70 40 L55 50 L62 65" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold mt-6 animate-pulse" style="color: <?= $theme['accent'] ?>" id="sorry-heart-hint-<?= $slide_num ?>">Tap the heart to mend its cracks 🩹</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="sorry-heart-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Mend the Cracks to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── SORRY: FORGIVENESS LETTER ──────────
            elseif ($slide_type === 'sorry_forgiveness_letter'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="forgive-envelope-container-<?= $slide_num ?>">
                        <div class="relative w-48 h-36 bg-slate-900 border border-slate-800 rounded-2xl flex items-center justify-center cursor-pointer shadow-2xl transition hover:scale-105 active:scale-95" id="forgive-envelope-<?= $slide_num ?>" onclick="openForgivenessLetter(<?= $slide_num ?>)">
                            <div class="absolute inset-0 bg-gradient-to-br from-slate-900/50 to-slate-950/90 rounded-2xl"></div>
                            <!-- Wax Seal -->
                            <div class="w-12 h-12 bg-rose-500 rounded-full border border-rose-400 flex items-center justify-center text-xl shadow-lg select-none z-10 hover:animate-pulse" id="forgive-seal-<?= $slide_num ?>">💌</div>
                            
                            <!-- Letter Paper (slides out upwards) -->
                            <div class="absolute inset-x-4 top-2 bottom-2 bg-stone-100 rounded-xl p-4 text-[#3a2010] shadow-inner transform translate-y-0 transition-transform duration-1000 ease-out z-0 hidden flex-col justify-center items-center" id="forgive-paper-<?= $slide_num ?>">
                                <p class="text-xs font-serif font-semibold italic text-center leading-relaxed" id="forgive-text-<?= $slide_num ?>"></p>
                            </div>
                        </div>
                        <p class="text-xs font-medium text-slate-500 mt-6 animate-pulse" id="forgive-hint-<?= $slide_num ?>">Tap the seal to open the letter 💌</p>
                    </div>
                    
                    <div class="text-center pt-6 w-full">
                        <button id="forgive-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Read the Letter to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── SORRY: BLOOMING ROSE ──────────
            elseif ($slide_type === 'sorry_blooming_rose'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="blooming-rose-container-<?= $slide_num ?>">
                        <div class="w-32 h-32 cursor-pointer flex items-center justify-center relative" onclick="bloomApologyRose(<?= $slide_num ?>)">
                            <div class="text-7xl filter grayscale transition-all duration-1000 ease-out select-none transform rotate-45" id="rose-flower-<?= $slide_num ?>">🥀</div>
                        </div>
                        <p class="text-xs font-medium text-slate-500 mt-6 animate-pulse" id="rose-hint-<?= $slide_num ?>">Tap to bloom the rose 🌹</p>
                    </div>
                    
                    <div class="text-center pt-6 w-full">
                        <button id="rose-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Bring the Rose to Life to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── SORRY: REACHING HANDS ──────────
            elseif ($slide_type === 'sorry_reaching_hands'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center overflow-hidden" id="reaching-hands-area-<?= $slide_num ?>">
                        <div class="w-full max-w-xs h-32 relative bg-slate-900/20 border border-slate-800 rounded-3xl flex items-center justify-between px-6 overflow-hidden">
                            <!-- Left Hand (sender) -->
                            <div class="text-4xl select-none z-10 transform translate-x-0 transition-transform" id="sender-hand-<?= $slide_num ?>">🫱</div>
                            <!-- Right Hand (receiver) - Draggable -->
                            <div class="text-4xl cursor-grab active:cursor-grabbing select-none z-20 absolute right-6" id="receiver-hand-<?= $slide_num ?>" style="touch-action: none;">🫲</div>
                        </div>
                        <p class="text-xs font-medium text-slate-500 mt-6 animate-pulse" id="hands-hint-<?= $slide_num ?>">Drag the right hand to reach out 🤝</p>
                    </div>
                    
                    <div class="text-center pt-6 w-full">
                        <button id="hands-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Reach Out to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── SORRY: MEMORIES RESTORED ──────────
            elseif ($slide_type === 'sorry_memories_restored'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="w-full flex-grow flex items-center justify-center px-2">
                        <?php if (count($images) > 0): ?>
                            <div class="grid grid-cols-3 gap-3 w-full max-w-xs" id="sorry-memories-grid-<?= $slide_num ?>">
                                <?php
                                $display_limit = min(count($images), 3);
                                for ($mi = 0; $mi < $display_limit; $mi++):
                                ?>
                                    <div class="aspect-square bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden cursor-pointer relative shadow-lg transform hover:scale-105 active:scale-95 transition duration-300" onclick="restoreApologyMemory(this, <?= $mi ?>, <?= $slide_num ?>, <?= $display_limit ?>)">
                                        <img src="<?= h($base_url . img_story($images[$mi])) ?>" class="w-full h-full object-cover filter grayscale opacity-45 transition-all duration-700 pointer-events-none">
                                        <div class="absolute inset-0 flex items-center justify-center text-base select-none z-10 pointer-events-none" id="memory-lock-<?= $mi ?>-<?= $slide_num ?>">🔒</div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-3 gap-3 w-full max-w-xs" id="sorry-memories-grid-<?= $slide_num ?>">
                                <?php for ($mi = 0; $mi < 3; $mi++): ?>
                                    <div class="aspect-square bg-slate-900 border border-slate-800 rounded-2xl flex items-center justify-center cursor-pointer relative shadow-lg transform hover:scale-105 active:scale-95 transition duration-300" onclick="restoreApologyMemory(this, <?= $mi ?>,  <?= $slide_num ?>, 3)">
                                        <div class="text-xl filter grayscale opacity-45 transition-all duration-700 select-none">✨</div>
                                        <div class="absolute inset-0 flex items-center justify-center text-base select-none z-10 pointer-events-none" id="memory-lock-<?= $mi ?>-<?= $slide_num ?>">🔒</div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs font-medium text-slate-500 mt-6 animate-pulse" id="memories-hint-<?= $slide_num ?>">Tap each memory to restore color ✨</p>
                    
                    <div class="text-center pt-6 w-full">
                        <button id="memories-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Restore All Memories to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 1. WELCOME ──────────
            elseif ($slide_type === 'love_letter_welcome'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    <div class="flex-grow flex flex-col justify-center items-center">
                        <div class="text-6xl mb-6 animate-pulse">💌</div>
                        <p class="text-sm leading-relaxed <?= $theme['text_secondary'] ?> max-w-xs mb-6">A private message has been written just for you. Open it with an open heart.</p>
                    </div>
                    <div class="text-center w-full z-10">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-4 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 active:scale-95 transition">
                            <?= $slide_btn ?: 'Open Letter' ?>
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 2. ENVELOPE OPEN ──────────
            elseif ($slide_type === 'love_letter_envelope_open'): ?>
                <div id="envelope-cinematic-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#120c08] z-[100]">
                    <div class="absolute top-[8%] inset-x-6 text-center z-30">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="envelope-cinematic-wrapper" id="envelope-wrapper-<?= $slide_num ?>" onclick="openLoveEnvelopeCinematic(<?= $slide_num ?>)">
                        <div class="envelope-back"></div>
                        <div class="envelope-letter-slide" id="envelope-letter-slide-<?= $slide_num ?>">
                            <p class="text-[11px] font-bold text-rose-800 font-serif leading-relaxed text-center">My Dearest <?= h($page['receiver_name']) ?>,</p>
                            <p class="text-[8px] text-stone-500 font-serif mt-2 text-center">I have wanted to tell you this for so long...</p>
                        </div>
                        <div class="envelope-front-sides"></div>
                        <div class="envelope-front-bottom"></div>
                        <div class="envelope-flap-3d" id="envelope-flap-3d-<?= $slide_num ?>"></div>
                        <div class="wax-seal-3d" id="wax-seal-3d-<?= $slide_num ?>">⚜️</div>
                    </div>
                    
                    <div class="absolute bottom-[8%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <p id="envelope-cinematic-hint-<?= $slide_num ?>" class="text-xs font-semibold text-rose-400/80 animate-pulse">Tap the wax seal to break it ✉️</p>
                        <button id="envelope-cinematic-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-amber-600 to-amber-800 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(217,119,6,0.4)] border border-amber-500/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Read My Heart ✍️
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 3. HANDWRITING ──────────
            elseif ($slide_type === 'love_letter_handwrite'): ?>
                <div id="love-letter-handwrite-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#1e1510] z-[100]">
                    <div id="love-letter-paper-<?= $slide_num ?>" class="love-paper-unfolded">
                        <div class="overflow-y-auto max-h-[50vh] pr-2 space-y-4 text-[#5c3e21] text-lg leading-relaxed relative" id="love-letter-handwrite-text-<?= $slide_num ?>">
                            <svg id="love-letter-quill-<?= $slide_num ?>" class="quill-pen absolute w-8 h-8 pointer-events-none transition-all duration-[80ms] z-30 hidden" viewBox="0 0 100 100" style="transform: rotate(-30deg);">
                                <path d="M90 10 L40 60 C35 65 30 75 30 80 C30 75 40 70 45 65 L95 15 Z" fill="#5c3e21"></path>
                                <path d="M30 80 L20 90 L25 85 Z" fill="#000"></path>
                            </svg>
                            <div id="love-letter-handwrite-lines-<?= $slide_num ?>" class="space-y-4"></div>
                        </div>
                    </div>
                    
                    <div class="absolute bottom-[6%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <button id="love-letter-handwrite-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-amber-700 to-amber-900 text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Open Scrapbook 📖
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 4. SCRAPBOOK ──────────
            elseif ($slide_type === 'love_letter_scrapbook'): ?>
                <div id="scrapbook-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#1a120b] z-[100]">
                    <div class="absolute top-[5%] inset-x-6 text-center z-30">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-amber-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="scrapbook-wrapper">
                        <div class="scrapbook-book" id="scrapbook-book-<?= $slide_num ?>">
                            <div class="scrapbook-cover-left"></div>
                            <div class="scrapbook-cover-right"></div>
                            
                            <?php 
                            $scrapbook_images = array_slice($images, 0, 5);
                            $page_count = 3; 
                            for ($sp = 0; $sp < $page_count; $sp++): 
                                $front_img = $scrapbook_images[$sp * 2] ?? null;
                                $back_img = $scrapbook_images[$sp * 2 + 1] ?? null;
                            ?>
                                <div class="scrapbook-sheet" id="scrapbook-sheet-<?= $slide_num ?>-<?= $sp ?>" style="z-index: <?= 10 - $sp ?>;">
                                    <div class="scrapbook-page-front">
                                        <div class="scrapbook-tape"></div>
                                        <?php if ($front_img): ?>
                                            <div class="scrapbook-polaroid">
                                                <img src="<?= h($base_url . $front_img['image_path']) ?>" class="object-cover">
                                                <p class="scrapbook-desc"><?= h($front_img['caption'] ?: 'Cherished Moment ❤️') ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-full h-full flex flex-col justify-center items-center text-stone-400 text-xs font-serif">
                                                <span class="text-3xl mb-2">📸</span>
                                                A memory to keep
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-[9px] text-stone-400 text-right font-mono">Page <?= $sp * 2 + 1 ?></div>
                                    </div>
                                    <div class="scrapbook-page-back">
                                        <div class="scrapbook-tape" style="transform: rotate(8deg); left: auto; right: 30px;"></div>
                                        <?php if ($back_img): ?>
                                            <div class="scrapbook-polaroid" style="--rot: -3deg;">
                                                <img src="<?= h($base_url . $back_img['image_path']) ?>" class="object-cover">
                                                <p class="scrapbook-desc"><?= h($back_img['caption'] ?: 'Togetherness 💕') ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-full h-full flex flex-col justify-center items-center text-stone-400 text-xs font-serif">
                                                <span class="text-3xl mb-2">✨</span>
                                                Always you & me
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-[9px] text-stone-400 text-left font-mono">Page <?= $sp * 2 + 2 ?></div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="absolute bottom-[5%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <p id="scrapbook-hint-<?= $slide_num ?>" class="text-xs font-semibold text-amber-300/80 animate-pulse mb-3">Tap on the right pages to flip them 📖</p>
                        <button id="scrapbook-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-amber-600 to-amber-800 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(217,119,6,0.4)] border border-amber-500/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Continue Story 🗓️
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 5. TIMELINE ──────────
            elseif ($slide_type === 'love_letter_timeline'): ?>
                <div id="timeline-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#0f0a07] z-[100]">
                    <div class="absolute top-[6%] inset-x-6 text-center z-30">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-rose-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="timeline-cinematic-scroll w-full px-4" id="timeline-scroll-<?= $slide_num ?>" onscroll="handleTimelineScroll(<?= $slide_num ?>)">
                        <div class="timeline-track-line"></div>
                        <div class="timeline-track-progress" id="timeline-progress-<?= $slide_num ?>"></div>
                        
                        <?php 
                        $milestones = [
                            ['title' => 'Where It All Began 🗺️', 'desc' => 'The day our paths crossed and everything changed.'],
                            ['title' => 'First Real Connection 💬', 'desc' => 'Hours turned to minutes talking to you.'],
                            ['title' => 'Building Our Bond ⚓', 'desc' => 'Realizing you were the anchor in my life.'],
                            ['title' => 'My Favorite Memory 📸', 'desc' => 'One of the best moments we ever shared.'],
                            ['title' => 'Forever & Always ♾️', 'desc' => 'Looking forward to writing the rest of our story.']
                        ];
                        $milestone_images = array_slice($images, 0, 5);
                        foreach ($milestones as $mi => $ms):
                            $ms_img = $milestone_images[$mi] ?? null;
                        ?>
                            <div class="timeline-milestone flex flex-col items-center" id="timeline-ms-<?= $slide_num ?>-<?= $mi ?>">
                                <div class="timeline-badge-node"></div>
                                <div class="timeline-card-content mt-4 flex flex-col items-center">
                                    <?php if ($ms_img): ?>
                                        <img src="<?= h($base_url . $ms_img['image_path']) ?>" class="w-full h-32 object-cover rounded-xl mb-3 shadow-md">
                                    <?php endif; ?>
                                    <h3 class="text-sm font-bold text-rose-300 font-heading mb-1 text-center"><?= $ms['title'] ?></h3>
                                    <p class="text-xs text-stone-300 text-center font-serif leading-relaxed"><?= $ms['desc'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="absolute bottom-[6%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <p id="timeline-hint-<?= $slide_num ?>" class="text-xs font-semibold text-rose-400/80 animate-pulse mb-3">Scroll down to trace our timeline 🗓️</p>
                        <button id="timeline-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-rose-600 to-pink-700 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(225,29,72,0.4)] border border-rose-500/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            See Future Dreams 🌟
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 6. FUTURE DREAMS ──────────
            elseif ($slide_type === 'love_letter_future_dreams'): ?>
                <div id="future-dreams-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#0a070e] z-[100]">
                    <div class="absolute top-[8%] inset-x-6 text-center z-40">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-300/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div id="future-dreams-bubbles-<?= $slide_num ?>" class="absolute inset-0 pointer-events-auto z-20 overflow-hidden"></div>
                    
                    <div class="absolute bottom-[8%] inset-x-6 text-center z-40 flex flex-col items-center">
                        <p id="future-dreams-hint-<?= $slide_num ?>" class="text-xs font-semibold text-pink-400/80 animate-pulse mb-3">Tap the floating bubbles to reveal my dreams for us ✨</p>
                        <button id="future-dreams-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(147,51,234,0.4)] border border-purple-500/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Hear My Message 🎙️
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 7. VOICE WAVE ──────────
            elseif ($slide_type === 'love_letter_voice_wave'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1 font-heading tracking-wide"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center w-full max-w-sm px-6 py-8 rounded-3xl <?= $is_dark ? 'glass-dark border border-white/5' : 'glass border border-black/5' ?> shadow-2xl relative">
                        <?php if (!empty($page['voice_url'])): ?>
                            <audio id="voice-audio-element-<?= $slide_num ?>" src="<?= h($base_url . $page['voice_url']) ?>" class="hidden" preload="none"></audio>
                        <?php endif; ?>
                        <canvas id="voice-wave-canvas-<?= $slide_num ?>" class="w-full h-24 mb-8" style="background: transparent;"></canvas>
                        
                        <div class="love-voice-glow-ring" id="voice-glow-ring-<?= $slide_num ?>"></div>
                        <button onclick="toggleLoveVoice(<?= $slide_num ?>)" id="voice-play-btn-<?= $slide_num ?>" class="love-voice-glow-button w-20 h-20 bg-rose-500 hover:bg-rose-600 text-white rounded-full flex items-center justify-center shadow-lg hover:scale-105 active:scale-95 transition focus:outline-none">
                            <span id="voice-btn-icon-<?= $slide_num ?>" class="text-2xl">▶️</span>
                        </button>
                        <p id="voice-duration-label-<?= $slide_num ?>" class="text-xs font-semibold mt-6 text-rose-400">Play my recorded message</p>
                    </div>
                    
                    <div class="text-center pt-6 w-full z-10">
                        <button id="voice-wave-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-4 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 active:scale-95 transition">
                            Watch Video Message 🎬
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 8. VIDEO MESSAGE ──────────
            elseif ($slide_type === 'love_letter_video_message'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="relative w-full aspect-video rounded-3xl overflow-hidden shadow-2xl border border-rose-200/20 bg-black custom-video-player group transition-all duration-300 video-blurred" data-player-id="video-<?= $slide_num ?>" id="video-container-<?= $slide_num ?>">
                        <?php if (!empty($page['video_url'])): ?>
                            <!-- Top Slide Video Title Overlay -->
                            <div class="absolute top-0 inset-x-0 bg-gradient-to-b from-black/85 via-black/40 to-transparent p-4 flex items-center justify-between opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-300 z-35 pointer-events-none">
                                <span class="text-[10px] font-bold tracking-wide uppercase text-pink-300 drop-shadow-md">Video Message</span>
                                <span class="text-xs font-semibold truncate text-white drop-shadow-md max-w-[70%]"><?= h($slide_title) ?></span>
                            </div>

                            <!-- Loading Spinner -->
                            <div id="video-loader-video-<?= $slide_num ?>" class="absolute inset-0 flex items-center justify-center bg-black/60 z-30 transition-opacity duration-300 hidden">
                                <div class="w-10 h-10 border-4 border-pink-500 border-t-transparent rounded-full animate-spin"></div>
                            </div>

                            <!-- Netflix-style Poster Cover -->
                            <?php 
                            $video_poster_url = '';
                            if (!empty($images) && isset($images[0])) {
                                $video_poster_url = img_story($images[0]['image_path'] ?? $images[0]);
                            }
                            ?>
                            <?php if (!empty($video_poster_url)): ?>
                                <div id="custom-video-poster-video-<?= $slide_num ?>" class="video-poster-cover" style="background-image: url('<?= h($base_url . $video_poster_url) ?>')"></div>
                            <?php endif; ?>

                            <video id="custom-video-element-video-<?= $slide_num ?>" playsinline muted class="w-full h-full object-contain cursor-pointer" preload="metadata" onclick="toggleCustomVideo('video-<?= $slide_num ?>')" src="<?= h(strpos($page['video_url'], 'http') === 0 ? $page['video_url'] : $base_url . $page['video_url']) ?>" onerror="document.getElementById('video-play-error-video-<?= $slide_num ?>') && document.getElementById('video-play-error-video-<?= $slide_num ?>').classList.remove('hidden');">
                                Your browser does not support HTML5 video.
                            </video>
                            
                            <!-- Custom play/pause center overlay button with reveal animation and pulse glow -->
                            <div id="custom-video-overlay-video-<?= $slide_num ?>" class="absolute inset-0 flex items-center justify-center bg-black/35 transition-opacity duration-300 z-20 cursor-pointer" onclick="toggleCustomVideo('video-<?= $slide_num ?>')">
                                <button type="button" class="play-btn-overlay play-btn-glow w-16 h-16 rounded-full bg-pink-500/90 text-white flex items-center justify-center text-2xl font-bold shadow-lg transition-transform duration-300 transform group-hover:scale-110 pointer-events-auto">
                                    ▶
                                </button>
                            </div>

                            <!-- Custom bottom control bar -->
                            <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent p-3 flex flex-col opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-300 z-30">
                                <!-- Progress Bar -->
                                <div class="relative w-full h-1.5 bg-gray-600 rounded-full mb-2 cursor-pointer group/progress" onclick="seekCustomVideo(event, 'video-<?= $slide_num ?>')">
                                    <div id="custom-video-progress-video-<?= $slide_num ?>" class="h-full bg-pink-500 rounded-full w-0 transition-all duration-100 relative"></div>
                                </div>
                                <div class="flex items-center justify-between text-xs text-white">
                                    <div class="flex items-center space-x-3">
                                        <button type="button" onclick="toggleCustomVideo('video-<?= $slide_num ?>')" id="custom-video-play-btn-video-<?= $slide_num ?>" class="font-bold focus:outline-none hover:text-pink-400">
                                            ▶
                                        </button>
                                        <span class="font-mono text-[10px] text-gray-300" id="custom-video-time-video-<?= $slide_num ?>">0:00 / 0:00</span>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <!-- Volume controls -->
                                        <div class="flex items-center space-x-1 group/volume">
                                            <button type="button" onclick="toggleMuteCustomVideo('video-<?= $slide_num ?>')" id="custom-video-mute-video-<?= $slide_num ?>" class="focus:outline-none hover:text-pink-400">
                                                🔇
                                            </button>
                                            <input type="range" min="0" max="1" step="0.1" value="1" id="custom-video-volume-video-<?= $slide_num ?>" class="w-12 h-1 bg-white/20 rounded-lg appearance-none cursor-pointer accent-pink-500 transition-all focus:outline-none opacity-0 group-hover/volume:opacity-100 w-0 group-hover/volume:w-12" oninput="setVolumeCustomVideo(this.value, 'video-<?= $slide_num ?>')">
                                        </div>
                                        <!-- Playback Speed -->
                                        <button type="button" onclick="cycleSpeedCustomVideo('video-<?= $slide_num ?>')" id="custom-video-speed-video-<?= $slide_num ?>" class="focus:outline-none hover:text-pink-400 font-mono text-[10px] bg-white/10 px-2 py-0.5 rounded">
                                            1x
                                        </button>
                                        <!-- PiP -->
                                        <button type="button" onclick="togglePipCustomVideo('video-<?= $slide_num ?>')" class="focus:outline-none hover:text-pink-400">
                                            📺
                                        </button>
                                        <!-- Fullscreen -->
                                        <button type="button" onclick="fullscreenCustomVideo('video-<?= $slide_num ?>')" class="focus:outline-none hover:text-pink-400">
                                            fullscreen
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="w-full h-full flex flex-col justify-center items-center text-stone-500 text-xs">
                                <span class="text-4xl mb-2">🎬</span>
                                No Video message uploaded
                            </div>
                        <?php endif; ?>
                    </div>
                    <p id="video-play-error-video-<?= $slide_num ?>" class="hidden text-[10px] text-red-400 font-semibold mt-2 text-center px-4 leading-relaxed">⚠️ Video playback failed. <a href="<?= h(strpos($page['video_url'], 'http') === 0 ? $page['video_url'] : $base_url . $page['video_url']) ?>" target="_blank" class="underline text-pink-400 font-bold hover:text-pink-300">Tap here to open/download it directly 🍿</a></p>
                    
                    <div class="text-center pt-6 w-full z-10">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-4 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 active:scale-95 transition">
                            Close the Letter 💋
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 9. FOLDING LETTER ──────────
            elseif ($slide_type === 'love_letter_folding'): ?>
                <div id="folding-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#120c08] z-[100]">
                    <div class="absolute top-[8%] inset-x-6 text-center z-30">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="folding-letter-container" id="folding-letter-container-<?= $slide_num ?>" onclick="foldLoveLetterStep(<?= $slide_num ?>)">
                        <div class="folding-letter-sheet-3d" id="folding-sheet-<?= $slide_num ?>">
                            <div class="folding-panel folding-panel-top" id="folding-panel-top-<?= $slide_num ?>">
                                <p class="text-[10px] text-center text-stone-400 mt-2 font-serif">Sealed with hope</p>
                            </div>
                            <div class="folding-panel folding-panel-middle">
                                <p class="text-[12px] font-bold text-rose-800 font-serif">Written with Love</p>
                                <p class="text-[9px] text-stone-500 font-serif mt-2">Forever & Always</p>
                            </div>
                            <div class="folding-panel folding-panel-bottom" id="folding-panel-bottom-<?= $slide_num ?>">
                                <p class="text-[10px] text-center text-stone-400 mt-28 font-serif">Yours, <?= h($page['sender_name']) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="absolute bottom-[8%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <p id="folding-hint-<?= $slide_num ?>" class="text-xs font-semibold text-rose-400/80 animate-pulse">Tap the letter to fold it 💋</p>
                        <button id="folding-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-amber-600 to-amber-800 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(217,119,6,0.4)] border border-amber-500/20 active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Sealed Forever ❤️
                        </button>
                    </div>
                </div>

            <?php // ────────── LOVE LETTER: 10. REACTIONS FINALE ──────────
            elseif ($slide_type === 'love_letter_reactions_finale'): ?>
                <div id="reactions-finale-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-gradient-to-br from-[#1c000f] via-slate-950 to-[#2c001c] z-[100]">
                    <div class="absolute inset-x-6 top-[22%] text-center z-30 flex flex-col items-center">
                        <div class="text-6xl mb-4 animate-pulse">💌❤️💫</div>
                        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-rose-400 via-amber-200 to-pink-400 font-heading mb-3">With All My Love</h2>
                        <p id="reactions-quote-<?= $slide_num ?>" class="text-sm italic text-pink-200/90 font-serif max-w-xs leading-relaxed min-h-[40px] text-center">Every love story is beautiful, but ours is my favorite.</p>
                    </div>
                    
                    <div class="absolute bottom-[10%] inset-x-6 text-center z-30 flex flex-col items-center space-y-3 pointer-events-auto">
                        <div class="space-y-3 w-full">
                            <span class="block text-[10px] font-bold text-pink-400/70 uppercase tracking-widest">Send A Reaction</span>
                            <div class="flex space-x-2 justify-center">
                                <?php foreach ($reactions_list as $rkey => $rval): ?>
                                    <button onclick="sendReaction('<?= $rkey ?>')" class="w-12 h-12 bg-white/5 border border-white/10 hover:scale-110 rounded-2xl text-xl flex items-center justify-center transition shadow-sm"><?= $rval['emoji'] ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-4 bg-gradient-to-r from-rose-500 via-pink-500 to-rose-500 text-white font-black rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(244,63,94,0.5)] active:scale-95 transition">
                            Leave a Message Back 💌
                        </button>
                        
                        <a href="<?= $base_url ?>index.php" class="w-full py-4 bg-white/10 hover:bg-white/15 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider border border-white/20 active:scale-95 transition flex items-center justify-center gap-2">
                            ✨ Create Your Own Story
                        </a>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 1. ANGRY METER ──────────
            elseif ($slide_type === 'mana_lo_angry_meter'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full" id="angry-meter-slide-<?= $slide_num ?>">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center w-full max-w-sm px-6">
                        <div id="angry-emoji-<?= $slide_num ?>" class="text-7xl mb-6 transition-transform duration-500 select-none">😠</div>
                        <h3 id="angry-text-<?= $slide_num ?>" class="text-lg font-bold text-pink-700/80 mb-6 font-serif">Checking status...</h3>
                        
                        <div class="w-full bg-pink-100 border border-pink-200/50 rounded-full h-6 p-1 overflow-hidden shadow-inner relative">
                            <div id="angry-bar-fill-<?= $slide_num ?>" class="bg-gradient-to-r from-pink-400 to-rose-600 h-full rounded-full w-0 transition-all duration-300 flex items-center justify-end pr-2 text-[10px] font-bold text-white">0%</div>
                        </div>
                    </div>
                    
                    <div class="text-center w-full z-10">
                        <button id="angry-meter-btn-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-4 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> scale-0 opacity-0 hidden transition-all duration-300">
                            <?= $slide_btn ?: 'Really? 😭' ?>
                        </button>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 2. EMERGENCY ALERT ──────────
            elseif ($slide_type === 'mana_lo_emergency'): ?>
                <div id="emergency-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 z-[100] flex flex-col justify-between p-6 bg-[#160a0f]">
                    <div class="text-center mt-6 z-20">
                        <span class="text-xs font-extrabold uppercase tracking-widest text-rose-500 animate-pulse">⚠️ ALARM TRIGGERED ⚠️</span>
                        <h2 class="text-3xl font-extrabold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center z-20 w-full max-w-xs mx-auto">
                        <div class="w-full bg-stone-900/80 backdrop-blur-md rounded-3xl p-6 border border-rose-500/20 text-center mana-lo-siren-border">
                            <span class="text-5xl mb-4 block animate-bounce select-none">🚨</span>
                            <p class="text-sm font-semibold text-rose-300/90 leading-relaxed mb-6">A critical emotional alert has been logged. Someone extremely important is upset!</p>
                            
                            <div id="emergency-searching-<?= $slide_num ?>" class="space-y-3">
                                <p class="text-xs font-bold text-stone-400 animate-pulse">Searching for optimal solution...</p>
                                <div class="w-full bg-stone-800 rounded-full h-2 overflow-hidden">
                                    <div id="emergency-progress-<?= $slide_num ?>" class="bg-rose-500 h-full w-0 transition-all duration-[80ms]"></div>
                                </div>
                            </div>
                            
                            <div id="emergency-solution-<?= $slide_num ?>" class="scale-0 opacity-0 hidden transition-all duration-700 flex flex-col items-center">
                                <span class="text-xs font-extrabold text-green-400 uppercase tracking-widest mb-3">✔️ Solution Found</span>
                                <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-green-500 shadow-lg mb-3">
                                    <?php if (!empty($creator_profile_photo)): ?>
                                        <img src="<?= h($base_url . $creator_profile_photo) ?>" class="w-full h-full object-cover">
                                    <?php elseif (!empty($images[0]['image_path'])): ?>
                                        <img src="<?= h($base_url . $images[0]['image_path']) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-stone-800 flex items-center justify-center text-4xl select-none">👤</div>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs font-bold text-green-300">Only <?= h($page['sender_name']) ?> can fix this!</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center w-full z-20 mb-6">
                        <button id="emergency-btn-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-rose-600 to-rose-800 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg scale-0 opacity-0 hidden transition-all duration-300 border border-rose-500/20">
                            See How 🧐
                        </button>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 3. MEMORY RESCUE ──────────
            elseif ($slide_type === 'mana_lo_rescue'): ?>
                <div id="rescue-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#0f070b] z-[100] flex flex-col justify-between p-6">
                    <div class="text-center mt-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center w-full max-w-sm mx-auto my-4 space-y-6">
                        <div class="grid grid-cols-2 gap-4 w-full" id="rescue-grid-<?= $slide_num ?>">
                            <?php 
                            $rescue_images = array_slice($images, 0, 4);
                            $default_captions = [
                                'Remember our silly laughs? 😂',
                                'Our cutest day ever! 💕',
                                'Togetherness is best 🌸',
                                'Can\'t lose this smile 🥺'
                            ];
                            for ($i = 0; $i < 4; $i++): 
                                $r_img = $rescue_images[$i] ?? null;
                            ?>
                                <div class="bg-white/5 border border-white/10 hover:border-pink-500/30 rounded-2xl p-4 flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-300 relative overflow-hidden h-32" id="rescue-item-<?= $slide_num ?>-<?= $i ?>" onclick="openRescueFile(<?= $slide_num ?>, <?= $i ?>)">
                                    <div class="rescue-folder-icon text-3xl mb-1 select-none">📁</div>
                                    <span class="text-[10px] font-bold text-pink-300 font-mono">FILE_00<?= $i+1 ?>.DAT</span>
                                    
                                    <div class="absolute inset-0 bg-stone-950 scale-y-0 origin-bottom transition-transform duration-500 flex flex-col items-center justify-center p-2 z-10" id="rescue-photo-wrap-<?= $slide_num ?>-<?= $i ?>">
                                        <?php if ($r_img): ?>
                                            <img src="<?= h($base_url . $r_img['image_path']) ?>" class="w-full h-20 object-cover rounded-lg mb-1 shadow-inner">
                                        <?php else: ?>
                                            <div class="w-full h-20 bg-stone-900 rounded-lg mb-1 flex items-center justify-center text-xl select-none">🖼️</div>
                                        <?php endif; ?>
                                        <span class="text-[8px] text-pink-200 leading-tight font-serif"><?= h($r_img['caption'] ?? $default_captions[$i]) ?></span>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        
                        <div class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-center">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-pink-300/80 mb-2 block" id="rescue-status-label-<?= $slide_num ?>">Data Restored: 0%</span>
                            <div class="w-full bg-stone-900 rounded-full h-3 overflow-hidden">
                                <div id="rescue-progress-bar-<?= $slide_num ?>" class="bg-gradient-to-r from-pink-500 to-rose-500 h-full w-0 transition-all duration-300"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center w-full z-30 mb-4">
                        <button id="rescue-btn-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-pink-600 to-pink-800 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg scale-0 opacity-0 hidden transition-all duration-300">
                            Rescue Successful 🩹
                        </button>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 4. THINGS I MISS ──────────
            elseif ($slide_type === 'mana_lo_miss_things'): ?>
                <div id="miss-things-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#0c080d] z-[100] flex flex-col justify-between p-6">
                    <div class="text-center mt-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center w-full max-w-sm mx-auto my-4">
                        <div class="grid grid-cols-2 gap-4 justify-items-center">
                            <?php 
                            $miss_list = [
                                ['label' => 'Your Messages 💬', 'icon' => '✉️', 'desc' => 'Waiting for your text notification.'],
                                ['label' => 'Your Calls 📞', 'icon' => '☎️', 'desc' => 'Talking for hours and losing track of time.'],
                                ['label' => 'Your Smile 😊', 'icon' => '✨', 'desc' => 'That bright smile which makes my day.'],
                                ['label' => 'Our Chats ⚓', 'icon' => '💖', 'desc' => 'Sharing every tiny detail of my day.']
                            ];
                            $miss_images = array_slice($images, 1, 4);
                            foreach ($miss_list as $mi => $item):
                                $m_img = $miss_images[$mi] ?? ($rescue_images[$mi] ?? null);
                            ?>
                                <div class="manalo-card-flipper-container" onclick="flipMissCard(<?= $slide_num ?>, <?= $mi ?>)">
                                    <div class="manalo-card-flipper" id="miss-card-<?= $slide_num ?>-<?= $mi ?>">
                                        <div class="manalo-card-front flex flex-col items-center justify-center p-3">
                                            <span class="text-3xl mb-2 select-none"><?= $item['icon'] ?></span>
                                            <span class="text-xs font-bold text-pink-200 text-center"><?= $item['label'] ?></span>
                                        </div>
                                        <div class="manalo-card-back flex flex-col items-center justify-center p-3 text-center">
                                            <?php if ($m_img): ?>
                                                <img src="<?= h($base_url . $m_img['image_path']) ?>" class="w-full h-16 object-cover rounded-lg mb-2">
                                            <?php endif; ?>
                                            <p class="text-[9px] text-pink-100/90 leading-relaxed font-serif"><?= $item['desc'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="text-center w-full z-30 mb-4">
                        <p id="miss-things-hint-<?= $slide_num ?>" class="text-xs font-semibold text-pink-400/80 animate-pulse mb-3">Tap all cards to flip them 💬</p>
                        <button id="miss-things-btn-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-pink-600 to-fuchsia-700 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg scale-0 opacity-0 hidden transition-all duration-300">
                            I Miss You Too 🥺
                        </button>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 5. CUTE BRIBE ──────────
            elseif ($slide_type === 'mana_lo_bribe'): ?>
                <div id="bribe-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#0e070d] z-[100] flex flex-col justify-between p-6">
                    <div class="text-center mt-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center w-full max-w-sm mx-auto my-4 space-y-6">
                        <p class="text-xs text-stone-300 text-center font-serif px-4">I know I can be silly, so here's a cute bribe. Choose your favorite peace offering! 🥺</p>
                        
                        <div class="flex flex-wrap justify-center gap-4 w-full">
                            <?php 
                            $bribes = [
                                ['name' => 'Chocolate 🍫', 'val' => 'chocolate', 'desc' => 'A sweet box of your absolute favorite chocolates.'],
                                ['name' => 'Pizza Night 🍕', 'val' => 'pizza', 'desc' => 'Hot cheesy pizza with all the toppings you love.'],
                                ['name' => 'Warm Coffee ☕', 'val' => 'coffee', 'desc' => 'A warm, perfect coffee date with endless stories.'],
                                ['name' => 'Bubble Tea 🧋', 'val' => 'boba', 'desc' => 'Sweet, refreshing bubble tea with extra boba.'],
                                ['name' => 'Game Night 🎮', 'val' => 'game', 'desc' => 'Playful gaming session where I let you win!']
                            ];
                            foreach ($bribes as $bi => $br):
                            ?>
                                <div class="bg-white/5 border border-white/10 hover:border-pink-400/40 rounded-2xl p-4 flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-300 w-24 h-24 gift-wiggle-hover relative overflow-hidden" id="bribe-box-<?= $slide_num ?>-<?= $bi ?>" onclick="openBribeBox(<?= $slide_num ?>, <?= $bi ?>)">
                                    <div class="text-3xl mb-1 select-none" id="bribe-gift-icon-<?= $slide_num ?>-<?= $bi ?>">🎁</div>
                                    <span class="text-[10px] font-bold text-pink-300"><?= explode(' ', $br['name'])[0] ?></span>
                                    
                                    <div class="absolute inset-0 bg-[#2d1123] border border-pink-500/30 scale-0 opacity-0 transition-all duration-500 flex flex-col items-center justify-center p-1 z-10" id="bribe-reveal-<?= $slide_num ?>-<?= $bi ?>">
                                        <span class="text-2xl mb-1 select-none"><?= explode(' ', $br['name'])[1] ?></span>
                                        <span class="text-[9px] font-bold text-pink-300 leading-tight"><?= $br['name'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="bg-pink-950/30 border border-pink-500/20 backdrop-blur-sm rounded-2xl p-4 text-center w-full min-h-[60px] flex items-center justify-center" id="bribe-card-desc-<?= $slide_num ?>">
                            <p class="text-xs italic text-pink-200/90 font-serif">Tap on a gift box to open the bribe! 🎁</p>
                        </div>
                    </div>
                    
                    <div class="text-center w-full z-30 mb-4">
                        <button id="bribe-btn-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-pink-600 to-rose-700 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg scale-0 opacity-0 hidden transition-all duration-300 border border-pink-500/25">
                            Bribe Accepted 🍫💕
                        </button>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 6. VOICE MESSAGE ──────────
            elseif ($slide_type === 'mana_lo_voice'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1 font-heading tracking-wide"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center w-full max-w-sm px-6 py-8 rounded-3xl <?= $is_dark ? 'glass-dark border border-white/5' : 'glass border border-black/5' ?> shadow-2xl relative overflow-hidden" id="voice-wrapper-<?= $slide_num ?>">
                        <?php if (!empty($page['voice_url'])): ?>
                            <audio id="voice-audio-element-<?= $slide_num ?>" src="<?= h($base_url . $page['voice_url']) ?>" class="hidden" preload="none"></audio>
                        <?php endif; ?>
                        
                        <canvas id="voice-wave-canvas-<?= $slide_num ?>" class="w-full h-24 mb-8" style="background: transparent;"></canvas>
                        
                        <div class="love-voice-glow-ring" id="voice-glow-ring-<?= $slide_num ?>"></div>
                        <button onclick="toggleManaLoVoice(<?= $slide_num ?>)" id="voice-play-btn-<?= $slide_num ?>" class="love-voice-glow-button w-20 h-20 bg-pink-500 hover:bg-pink-600 text-white rounded-full flex items-center justify-center shadow-lg hover:scale-105 active:scale-95 transition focus:outline-none z-20">
                            <span id="voice-btn-icon-<?= $slide_num ?>" class="text-2xl select-none">▶️</span>
                        </button>
                        <p id="voice-duration-label-<?= $slide_num ?>" class="text-xs font-semibold mt-6 text-pink-400 z-20">Play my recorded message</p>
                    </div>
                    
                    <div class="text-center pt-6 w-full z-10">
                        <button id="voice-wave-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-4 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 active:scale-95 transition">
                            Take Simple Quiz 🧐
                        </button>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 7. PLAYFUL QUIZ ──────────
            elseif ($slide_type === 'mana_lo_quiz'): ?>
                <div id="quiz-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#0d070b] z-[100] flex flex-col justify-between p-6">
                    <div class="text-center mt-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center w-full max-w-sm mx-auto my-4">
                        <div class="bg-white/5 border border-white/10 rounded-3xl p-6 w-full text-center relative overflow-hidden" id="quiz-card-<?= $slide_num ?>">
                            
                            <!-- Question 1 -->
                            <div id="quiz-q-<?= $slide_num ?>-0" class="quiz-question-panel space-y-6">
                                <h3 class="text-lg font-bold text-pink-300 font-serif">Q1: Who is more stubborn? 😤</h3>
                                <div class="flex flex-col space-y-3">
                                    <button onclick="answerQuiz(<?= $slide_num ?>, 0, 'you')" class="py-3 bg-white/5 border border-white/10 hover:bg-pink-600/20 rounded-xl font-semibold text-sm transition">😤 You (Definitely)</button>
                                    <button onclick="answerQuiz(<?= $slide_num ?>, 0, 'me')" class="py-3 bg-white/5 border border-white/10 hover:bg-pink-600/20 rounded-xl font-semibold text-sm transition">😭 Me (I admit it)</button>
                                </div>
                            </div>
                            
                            <!-- Question 2 -->
                            <div id="quiz-q-<?= $slide_num ?>-1" class="quiz-question-panel space-y-6 hidden">
                                <h3 class="text-lg font-bold text-pink-300 font-serif">Q2: Who should message first? 💬</h3>
                                <div class="flex flex-col space-y-3">
                                    <button onclick="answerQuiz(<?= $slide_num ?>, 1, 'you')" class="py-3 bg-white/5 border border-white/10 hover:bg-pink-600/20 rounded-xl font-semibold text-sm transition">😤 You (I'm waiting!)</button>
                                    <button onclick="answerQuiz(<?= $slide_num ?>, 1, 'me')" class="py-3 bg-white/5 border border-white/10 hover:bg-pink-600/20 rounded-xl font-semibold text-sm transition">😭 Me (Right now!)</button>
                                </div>
                            </div>
                            
                            <!-- Question 3 -->
                            <div id="quiz-q-<?= $slide_num ?>-2" class="quiz-question-panel space-y-6 hidden">
                                <h3 class="text-lg font-bold text-pink-300 font-serif">Q3: Who misses whom more? 💓</h3>
                                <div class="flex flex-col space-y-3">
                                    <button onclick="answerQuiz(<?= $slide_num ?>, 2, 'you')" class="py-3 bg-white/5 border border-white/10 hover:bg-pink-600/20 rounded-xl font-semibold text-sm transition">😤 You (Secrets!)</button>
                                    <button onclick="answerQuiz(<?= $slide_num ?>, 2, 'me')" class="py-3 bg-white/5 border border-white/10 hover:bg-pink-600/20 rounded-xl font-semibold text-sm transition">😭 Me (Unlimited!)</button>
                                </div>
                            </div>
                            
                            <!-- Results -->
                            <div id="quiz-results-<?= $slide_num ?>" class="quiz-question-panel space-y-4 hidden scale-0 transition-transform duration-500">
                                <span class="text-3xl block select-none">📊</span>
                                <h3 class="text-lg font-bold text-green-400 font-heading">Evaluation Complete!</h3>
                                <p class="text-xs text-stone-300 leading-relaxed italic px-2">"Result: Both of us are stubborn, but we miss each other way too much to stay silent. Let's make peace! ❤️"</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center w-full z-30 mb-4">
                        <button id="quiz-btn-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-pink-600 to-rose-700 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg scale-0 opacity-0 hidden transition-all duration-300 border border-pink-500/25">
                            Unlock Secret Message 🔑
                        </button>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 8. HEART LOCK ──────────
            elseif ($slide_type === 'mana_lo_unlock'): ?>
                <div id="unlock-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#0a060a] z-[100] flex flex-col justify-between p-6">
                    <div class="text-center mt-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-rose-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center w-full max-w-sm mx-auto my-4 relative">
                        <div class="lock-container-wrap" id="lock-wrap-<?= $slide_num ?>">
                            <!-- SVG Heart Lock -->
                            <svg class="heart-lock-svg" id="heart-lock-<?= $slide_num ?>" viewBox="0 0 100 100">
                                <path d="M50 30 C35 10, 10 20, 10 45 C10 70, 50 90, 50 90 C50 90, 90 70, 90 45 C90 20, 65 10, 50 30 Z" />
                                <circle cx="50" cy="45" r="8" fill="#1c1917" />
                                <rect x="47" y="49" width="6" height="12" rx="2" fill="#1c1917" />
                            </svg>
                            
                            <!-- Draggable Key -->
                            <div class="heart-key-draggable flex items-center justify-center text-4xl select-none" id="heart-key-<?= $slide_num ?>">
                                🔑
                            </div>
                        </div>
                        
                        <!-- Unlocked message overlay -->
                        <div id="unlocked-msg-card-<?= $slide_num ?>" class="absolute inset-x-6 bg-[#270e1b] border border-pink-500/30 backdrop-blur-xl rounded-3xl p-6 text-center scale-0 opacity-0 transition-all duration-700 pointer-events-none z-30">
                            <span class="text-3xl mb-2 block select-none">💌</span>
                            <h3 class="text-sm font-bold text-pink-300 font-heading mb-2">Secret Message Unlocked</h3>
                            <p class="text-xs italic text-stone-200 leading-relaxed font-serif">"You mean the absolute world to me. I'm sorry for being silly. Let's start talking again? 🥺❤️"</p>
                        </div>
                    </div>
                    
                    <div class="text-center w-full z-30 mb-4">
                        <p id="unlock-hint-<?= $slide_num ?>" class="text-xs font-semibold text-rose-400/80 animate-pulse mb-3">Drag and drop the key into the keyhole 🗝️</p>
                        <button id="unlock-btn-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-rose-600 to-rose-800 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg scale-0 opacity-0 hidden transition-all duration-300 border border-rose-500/25">
                            Read Letter ✍️
                        </button>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 9. SINCERE LETTER ──────────
            elseif ($slide_type === 'mana_lo_letter'): ?>
                <div id="mana-lo-letter-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#160d13] z-[100]">
                    <div id="mana-lo-letter-paper-<?= $slide_num ?>" class="love-paper-unfolded">
                        <div class="overflow-y-auto max-h-[50vh] pr-2 space-y-4 text-[#5c3e21] text-lg leading-relaxed relative" id="mana-lo-letter-text-<?= $slide_num ?>">
                            <svg id="mana-lo-letter-quill-<?= $slide_num ?>" class="quill-pen absolute w-8 h-8 pointer-events-none transition-all duration-[80ms] z-30 hidden" viewBox="0 0 100 100" style="transform: rotate(-30deg);">
                                <path d="M90 10 L40 60 C35 65 30 75 30 80 C30 75 40 70 45 65 L95 15 Z" fill="#5c3e21"></path>
                                <path d="M30 80 L20 90 L25 85 Z" fill="#000"></path>
                            </svg>
                            <div id="mana-lo-letter-lines-<?= $slide_num ?>" class="space-y-4"></div>
                        </div>
                    </div>
                    
                    <div class="absolute bottom-[6%] inset-x-6 text-center z-30 flex flex-col items-center">
                        <button id="mana-lo-letter-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-10 py-4 bg-gradient-to-r from-pink-600 to-rose-800 text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg active:scale-95 transition duration-300 scale-0 opacity-0 hidden">
                            Make Me Smile 😊
                        </button>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 10. ONE SMILE PLEASE ──────────
            elseif ($slide_type === 'mana_lo_smile'): ?>
                <div id="smile-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-[#0c060b] z-[100] flex flex-col justify-between p-6">
                    <div class="text-center mt-6 z-25">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400/60"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold text-white mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-center items-center w-full max-w-sm mx-auto my-4 relative">
                        <div id="smile-emoji-<?= $slide_num ?>" class="text-8xl mb-6 transition-transform duration-700 select-none animate-bounce">😔</div>
                        <h3 id="smile-label-<?= $slide_num ?>" class="text-lg font-bold text-rose-300 font-serif mb-6 text-center">Are we friends again?</h3>
                    </div>
                    
                    <div class="absolute bottom-[10%] inset-x-6 text-center z-30 flex flex-col items-center space-y-4 pointer-events-auto">
                        <div class="flex flex-col sm:flex-row gap-3 justify-center items-center w-full max-w-xs relative min-h-[140px]" id="smile-button-wrap-<?= $slide_num ?>">
                            <button id="smile-yes-btn-<?= $slide_num ?>" onclick="acceptSmile(<?= $slide_num ?>)" class="w-full py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_20px_rgba(34,197,94,0.3)] hover:scale-105 transition duration-300 z-30">
                                😊 Fine, I'm Not Angry
                            </button>
                            <button id="smile-no-btn-<?= $slide_num ?>" class="w-full py-4 bg-stone-700 text-stone-300 font-bold rounded-2xl text-xs uppercase tracking-wider border border-white/5 transition-all duration-300 z-20 absolute" style="bottom: 0;">
                                😤 Still Angry
                            </button>
                        </div>
                    </div>
                </div>

            <?php // ────────── MANA LO YAAR: 11. FINALE RESTORED ──────────
            elseif ($slide_type === 'mana_lo_finale'): ?>
                <div id="restored-finale-container-<?= $slide_num ?>" class="cinematic-overlay opacity-0 pointer-events-none transition-opacity duration-1000 bg-gradient-to-br from-[#290c1f] via-slate-950 to-[#190616] z-[100]">
                    <div class="absolute inset-x-6 top-[15%] text-center z-30 flex flex-col items-center">
                        <div class="text-6xl mb-4 animate-pulse select-none">❤️😊✨</div>
                        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-pink-400 via-amber-200 to-rose-400 font-heading mb-2">Restored!</h2>
                        <p id="restored-quote-<?= $slide_num ?>" class="text-sm italic text-pink-200/90 font-serif max-w-xs leading-relaxed text-center"></p>
                    </div>
                    
                    <div class="absolute inset-x-6 top-[42%] bottom-[24%] overflow-hidden z-25 pointer-events-none" id="restored-photos-container-<?= $slide_num ?>">
                        <!-- Drifting photos injected here -->
                    </div>
                    
                    <div class="absolute bottom-[8%] inset-x-6 text-center z-30 flex flex-col items-center space-y-3 pointer-events-auto">
                        <div class="space-y-3 w-full">
                            <span class="block text-[10px] font-bold text-pink-400/70 uppercase tracking-widest">Send A Reaction</span>
                            <div class="flex space-x-2 justify-center">
                                <?php foreach ($reactions_list as $rkey => $rval): ?>
                                    <button onclick="sendReaction('<?= $rkey ?>')" class="w-12 h-12 bg-white/5 border border-white/10 hover:scale-110 rounded-2xl text-xl flex items-center justify-center transition shadow-sm"><?= $rval['emoji'] ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-4 bg-gradient-to-r from-pink-500 to-rose-600 text-white font-black rounded-2xl text-xs uppercase tracking-wider shadow-[0_0_30px_rgba(236,72,153,0.5)] active:scale-95 transition">
                            Leave a Message Back 💌
                        </button>
                        
                        <a href="<?= $base_url ?>index.php" class="w-full py-4 bg-white/10 hover:bg-white/15 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider border border-white/20 active:scale-95 transition flex items-center justify-center gap-2">
                            ✨ Create Your Own Story
                        </a>
                    </div>
                </div>

            <?php // ────────── 14. FRIENDSHIP: SPIN WHEEL ──────────
            elseif ($slide_type === 'friendship_wheel' || $slide_type === 'invite_date_wheel'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="wheel-container-<?= $slide_num ?>">
                        <div class="relative w-56 h-56 flex justify-center items-center mb-6">
                            <div class="absolute -top-4 text-3xl z-20">👇</div>
                            <svg viewBox="0 0 200 200" class="w-full h-full transition-transform duration-[4000ms]" style="transform-origin: center; transition: transform 4s cubic-bezier(0.1, 0.8, 0.1, 1);" id="wheel-svg-<?= $slide_num ?>">
                                <circle cx="100" cy="100" r="90" fill="#f3f4f6" stroke="#d1d5db" stroke-width="2"/>
                                <?php
                                $segments = $slide_type === 'invite_date_wheel' 
                                    ? ["☕ Coffee", "🎬 Movie", "🍽️ Dinner", "🚗 Long Drive", "🎮 Gaming"]
                                    : ["Crazy Trip 🚗", "Late Night 📞", "Worst Joke 💀", "Helper 🤝", "Fight 🥊"];
                                $total_segs = count($segments);
                                for($s = 0; $s < $total_segs; $s++):
                                    $angle = 360 / $total_segs;
                                    $start_angle = $s * $angle;
                                    $end_angle = ($s + 1) * $angle;
                                    $colors = ['#f43f5e', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'];
                                    $color = $colors[$s % 5];
                                ?>
                                    <path d="M100 100 L<?= 100 + 80 * cos(deg2rad($start_angle)) ?> <?= 100 + 80 * sin(deg2rad($start_angle)) ?> A 80 80 0 0 1 <?= 100 + 80 * cos(deg2rad($end_angle)) ?> <?= 100 + 80 * sin(deg2rad($end_angle)) ?> Z" fill="<?= $color ?>" opacity="0.85"/>
                                    <text x="100" y="100" transform="rotate(<?= $start_angle + $angle/2 ?> 100 100) translate(40 0) rotate(90 100 100)" fill="white" font-size="8" font-weight="bold" text-anchor="middle"><?= $segments[$s] ?></text>
                                <?php endfor; ?>
                                <circle cx="100" cy="100" r="12" fill="white" stroke="#9ca3af" stroke-width="2"/>
                            </svg>
                        </div>
                        <button onclick="spinWheelTrigger(<?= $slide_num ?>, <?= $total_segs ?>, '<?= $slide_type ?>')" id="spin-btn-<?= $slide_num ?>" class="px-8 py-3 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition">
                            🎰 SPIN THE WHEEL!
                        </button>
                        <div id="wheel-result-<?= $slide_num ?>" class="mt-4 px-4 py-2 rounded-xl border border-transparent text-xs font-bold opacity-0 transition-opacity duration-500 <?= $is_dark ? 'bg-white/5 text-white' : 'bg-slate-100 text-gray-800' ?>">
                            Selected: <span id="wheel-result-text-<?= $slide_num ?>" class="text-pink-500"></span>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="wheel-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Spin to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 15. FRIENDSHIP: MEMORY CARDS ──────────
            elseif ($slide_type === 'friendship_moment_cards'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex justify-center items-center space-x-2" id="friendship-cards-<?= $slide_num ?>">
                        <?php
                        $card_memories = ["You & Me 💖", "Best Team 🚀", "Crazy Crew 🥳"];
                        for($c = 0; $c < 3; $c++):
                            $img_src = (count($images) > $c) ? h($base_url . $images[$c]['image_path']) : '';
                        ?>
                            <div class="perspective-container w-24 h-36 cursor-pointer" onclick="flipFriendshipCard(this, <?= $slide_num ?>)">
                                <div class="card-flipper relative w-full h-full transition-transform duration-700 ease-out" style="transform-style: preserve-3d;">
                                    <div class="absolute inset-0 bg-gradient-to-br from-pink-500 to-violet-600 rounded-xl border border-white/20 shadow-md flex items-center justify-center text-white text-2xl backface-hidden">
                                        ❓
                                    </div>
                                    <div class="absolute inset-0 bg-white p-2 pb-6 rounded-xl border border-gray-300 shadow-lg flex flex-col items-center justify-between text-stone-800 backface-hidden" style="transform: rotateY(180deg);">
                                        <?php if ($img_src): ?>
                                            <img src="<?= $img_src ?>" class="w-full h-20 object-<?= $photo_fit_mode ?> <?= $photo_fit_mode === 'contain' ? 'bg-black/15' : '' ?> rounded-md" alt="Memory">
                                        <?php else: ?>
                                            <div class="w-full h-20 bg-stone-100 rounded-md flex items-center justify-center text-xl">📸</div>
                                        <?php endif; ?>
                                        <span class="text-[8px] font-bold tracking-tight text-center truncate w-full mt-1"><?= $card_memories[$c] ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <p class="text-[9px] font-bold text-violet-500 uppercase tracking-widest mb-4 animate-pulse">TAP THE CARDS TO REVEAL</p>
                    <div class="text-center pt-6 w-full">
                        <button id="friendship-cards-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Flip all cards to continue (0/3)
                        </button>
                    </div>
                </div>

            <?php // ────────── 16. FRIENDSHIP: CERTIFICATE ──────────
            elseif ($slide_type === 'friendship_badge'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="badge-container-<?= $slide_num ?>">
                        <div class="badge-wrapper cursor-pointer relative w-56 h-72 border-4 border-double border-yellow-600 rounded-3xl p-6 shadow-2xl <?= $is_dark ? 'bg-amber-955/20' : 'bg-amber-50' ?> flex flex-col justify-between items-center text-amber-900 dark:text-amber-100" onclick="stampFriendshipBadge(<?= $slide_num ?>)">
                            <h3 class="text-xs font-extrabold uppercase tracking-widest font-heading">Friendship Certificate</h3>
                            <p class="text-[10px] leading-relaxed italic text-center my-3">"Granted to <?= h($page['receiver_name']) ?> for being the most supportive, funny, and amazing friend a person could ask for."</p>
                            <div class="relative w-16 h-16 border-2 border-dashed border-yellow-600 rounded-full flex items-center justify-center">
                                <div class="absolute inset-1.5 bg-red-600 rounded-full border-2 border-red-700 shadow-md flex items-center justify-center text-white text-2xl font-bold transition-all duration-300 scale-0 origin-center" id="wax-stamp-<?= $slide_num ?>">🌟</div>
                                <span class="text-[8px] text-yellow-600/60 font-bold" id="stamp-label-<?= $slide_num ?>">TAP TO STAMP</span>
                            </div>
                            <div class="w-full flex justify-between text-[7px] font-bold uppercase tracking-wider mt-4">
                                <span>Signed: <?= h($page['sender_name']) ?></span>
                                <span>Date: <?= date('d M Y') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="badge-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Stamp Certificate to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 17. ANNIVERSARY: TOGETHER COUNTER ──────────
            elseif ($slide_type === 'anniversary_together_counter'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center z-10" id="together-counter-<?= $slide_num ?>">
                        <div class="relative w-28 h-28 flex items-center justify-center mb-6 animate-heartbeat text-rose-500">
                            <svg viewBox="0 0 32 29.6" class="w-full h-full fill-current">
                                <path d="M23.6,0c-3.4,0-6.3,2.7-7.6,5.6C14.7,2.7,11.8,0,8.4,0C3.8,0,0,3.8,0,8.4c0,9.4,9.5,11.9,16,21.2
                                    c6.1-9.3,16-12.1,16-21.2C32,3.8,28.2,0,23.6,0z"/>
                            </svg>
                            <span class="absolute text-white font-extrabold text-lg font-heading">US</span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 w-full max-w-sm px-4">
                            <div class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-xl p-2.5 flex flex-col">
                                <span class="text-xl font-extrabold <?= $theme['text_primary'] ?>" id="together-days-<?= $slide_num ?>">00</span>
                                <span class="text-[7px] font-bold uppercase <?= $theme['text_muted'] ?>">Days</span>
                            </div>
                            <div class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-xl p-2.5 flex flex-col">
                                <span class="text-xl font-extrabold <?= $theme['text_primary'] ?>" id="together-hours-<?= $slide_num ?>">00</span>
                                <span class="text-[7px] font-bold uppercase <?= $theme['text_muted'] ?>">Hours</span>
                            </div>
                            <div class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-xl p-2.5 flex flex-col">
                                <span class="text-xl font-extrabold <?= $theme['text_primary'] ?>" id="together-minutes-<?= $slide_num ?>">00</span>
                                <span class="text-[7px] font-bold uppercase <?= $theme['text_muted'] ?>">Mins</span>
                            </div>
                            <div class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-xl p-2.5 flex flex-col">
                                <span class="text-xl font-extrabold <?= $theme['text_primary'] ?>" id="together-seconds-<?= $slide_num ?>">00</span>
                                <span class="text-[7px] font-bold uppercase <?= $theme['text_muted'] ?>">Secs</span>
                            </div>
                        </div>
                        <?php if ($special_date_enabled && !empty($page['relationship_date'])): ?>
                            <p class="text-[9px] font-bold <?= $theme['text_muted'] ?> uppercase tracking-widest mt-6">Since <?= date('d M Y', strtotime($page['relationship_date'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── 18. ANNIVERSARY: TIMELINE UNLOCK ──────────
            elseif ($slide_type === 'anniversary_timeline_unlock'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="timeline-container-<?= $slide_num ?>">
                        <div class="relative w-full flex items-center justify-around py-4 mb-6">
                            <div class="absolute h-0.5 bg-rose-300 left-8 right-8 z-0"></div>
                            <?php
                            $milestones = [
                                ["label" => "Day 1 📍", "text" => "Where our hearts first crossed path. 💖"],
                                ["label" => "Year 1 📅", "text" => "A year filled with endless laughter and support. 🌱"],
                                ["label" => "Today 💝", "text" => "Stronger than ever. Happy Anniversary! 🥂"]
                            ];
                            foreach($milestones as $mi => $ms):
                            ?>
                                <button onclick="unlockMilestone(<?= $mi ?>, <?= $slide_num ?>, this)" 
                                        data-text="<?= h($ms['text']) ?>"
                                        class="w-10 h-10 rounded-full bg-rose-500 border-4 border-white text-[10px] text-white font-bold flex items-center justify-center transition-all duration-300 hover:scale-110 active:scale-95 shadow-md relative z-10" 
                                        id="milestone-bubble-<?= $slide_num ?>-<?= $mi ?>">
                                    <?= $mi + 1 ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-2xl p-4 w-full min-h-[5rem] flex flex-col justify-center items-center shadow-inner" id="timeline-card-<?= $slide_num ?>">
                            <p class="text-xs font-bold text-rose-500 mb-1" id="timeline-label-<?= $slide_num ?>">TAP A MILESTONE</p>
                            <p class="text-[10px] text-stone-600 dark:text-stone-300 leading-relaxed max-w-xs" id="timeline-text-<?= $slide_num ?>">Unlock each milestone to see memory details.</p>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="timeline-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Unlock all milestones to continue (0/3)
                        </button>
                    </div>
                </div>

            <?php // ────────── 19. ANNIVERSARY: MEMORY BOOK ──────────
            elseif ($slide_type === 'anniversary_memory_book'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center overflow-hidden" id="book-container-<?= $slide_num ?>">
                        <?php if (empty($images)): ?>
                            <div class="w-full h-40 flex items-center justify-center text-xs text-slate-500 bg-slate-900 rounded-3xl border border-white/10">No pages configured</div>
                        <?php else: ?>
                            <div class="w-56 h-40 relative transform-style-preserve-3d mb-4" id="memory-book-wrapper-<?= $slide_num ?>">
                                <?php foreach ($images as $i_idx => $img): 
                                    $p_path = $img['medium'] ?? $img['image_path'] ?? '';
                                ?>
                                    <div class="absolute inset-0 w-full h-full origin-left bg-stone-50 text-stone-800 shadow-2xl rounded-r-2xl border-l border-stone-300 transform-style-preserve-3d transition-transform duration-1000 ease-in-out z-10" 
                                         id="book-page-<?= $slide_num ?>-<?= $i_idx ?>" 
                                         data-page-index="<?= $i_idx ?>">
                                        <div class="absolute inset-0 p-2.5 flex flex-col justify-between h-full backface-hidden">
                                            <div class="w-full h-24 rounded-lg overflow-hidden border border-stone-300/60 shadow-sm mb-1 bg-stone-200">
                                                <img src="<?= h($base_url . $p_path) ?>" class="w-full h-full object-<?= $photo_fit_mode ?> <?= $photo_fit_mode === 'contain' ? 'bg-black/15' : '' ?> select-none">
                                            </div>
                                            <div class="flex-grow flex flex-col justify-center text-center px-1">
                                                <p class="text-[9px] font-bold text-stone-900 leading-tight">Memory #<?= $i_idx + 1 ?></p>
                                            </div>
                                            <p class="text-[6px] text-stone-400 font-bold uppercase tracking-wider text-right">Page <?= $i_idx + 1 ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Prev/Next Controls under the book -->
                            <div class="flex items-center space-x-4">
                                <button type="button" onclick="prevBookPage(<?= $slide_num ?>)" class="px-4 py-1.5 bg-slate-900/65 hover:bg-slate-800 text-stone-300 text-[10px] font-bold rounded-xl transition border border-slate-800 shadow active:scale-95">◀ Prev</button>
                                <button type="button" onclick="nextBookPage(<?= $slide_num ?>)" class="px-4 py-1.5 bg-slate-900/65 hover:bg-slate-800 text-stone-300 text-[10px] font-bold rounded-xl transition border border-slate-800 shadow active:scale-95">Next ▶</button>
                            </div>
                        <?php endif; ?>
                        <p class="text-xs font-semibold mt-4 animate-pulse" style="color: <?= $theme['accent'] ?>" id="book-hint-<?= $slide_num ?>">Flip through our favorite memories 📖</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="book-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Read the Journal to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 20. INVITE OUT: MAP PIN ──────────
            elseif ($slide_type === 'invite_map_reveal'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center overflow-hidden" id="map-reveal-container-<?= $slide_num ?>">
                        <div class="relative w-44 h-44 border-4 border-teal-500 rounded-full flex items-center justify-center bg-teal-500/10 shadow-xl overflow-hidden cursor-pointer" onclick="zoomMapPin(<?= $slide_num ?>)">
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-teal-500/30 to-transparent animate-pulse" id="radar-scan-<?= $slide_num ?>"></div>
                            <div class="text-6xl z-10 transition-transform duration-[2000ms] scale-0" id="map-pin-<?= $slide_num ?>">📍</div>
                            <div class="absolute w-20 h-20 border-2 border-teal-500 rounded-full animate-ping opacity-25"></div>
                        </div>
                        <p class="text-xs font-semibold mt-6 animate-pulse text-teal-500" id="map-hint-<?= $slide_num ?>">Tap to search location... 📡</p>
                        <?php
                        $dest_key = $page['destination'] ?? 'cafe';
                        $destinations = get_destinations();
                        $dest = $destinations[$dest_key] ?? $destinations['cafe'];
                        ?>
                        <div id="map-result-<?= $slide_num ?>" class="mt-4 px-4 py-2 rounded-xl border border-transparent text-xs font-bold opacity-0 transition-opacity duration-1000 <?= $is_dark ? 'bg-white/5 text-white' : 'bg-slate-100 text-gray-800' ?>">
                            Destination Secured: <span class="text-teal-500 font-extrabold"><?= h($dest['name']) ?></span>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="map-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Zoom Pin to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 21. INVITE OUT: RUNAWAY CHOICE ──────────
            elseif ($slide_type === 'invite_interactive_choice'): ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full" id="choice-container-<?= $slide_num ?>">
                    <div class="text-5xl mb-6"><?= $slide['emoji'] ?? '🥺☕' ?></div>
                    <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mb-8 font-heading max-w-xs leading-tight" id="choice-prompt-<?= $slide_num ?>">
                        <?= h($page['proposal_question']) ?>
                    </h2>
                    <div class="relative w-full h-32 flex justify-center items-center">
                        <button onclick="handleYes(<?= $slide_num ?>)" id="yes-btn-<?= $slide_num ?>" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-sm shadow-lg <?= $theme['btn_shadow'] ?> mr-4 active:scale-95 transition z-10">
                            <?= h($slide['yes_text'] ?? 'Yes, Let\'s Go! 🎉') ?>
                        </button>
                        <button id="no-btn-<?= $slide_num ?>" class="px-8 py-3 <?= $is_dark ? 'bg-white/10 text-white/70' : 'bg-slate-200 text-slate-700' ?> font-bold rounded-2xl text-sm transition absolute runaway-btn">
                            <?= h($slide['no_text'] ?? 'Maybe Later') ?>
                        </button>
                    </div>
                </div>
                <div class="flex-grow flex flex-col justify-between items-center text-center hidden w-full animate-fadeIn" id="choice-success-<?= $slide_num ?>">
                    <div class="flex-grow flex flex-col justify-center items-center w-full">
                        <div class="text-6xl mb-6 animate-bounce"><?= $slide['success_emoji'] ?? '🥳' ?></div>
                        <h2 class="text-2xl font-extrabold <?= $theme['text_primary'] ?> mb-2 font-heading"><?= rpl($slide['success_title'] ?? 'It\'s A Date! 🎉', $page) ?></h2>
                        <p class="text-sm <?= $theme['text_muted'] ?> max-w-xs mb-8"><?= rpl($slide['success_msg'] ?? '', $page) ?></p>
                        <div class="space-y-4">
                            <span class="block text-[10px] font-bold <?= $theme['text_muted'] ?> uppercase tracking-widest">Send A Reaction</span>
                            <div class="flex space-x-2 justify-center">
                                <?php foreach ($reactions_list as $rkey => $rval): ?>
                                    <button onclick="sendReaction('<?= $rkey ?>')" class="w-12 h-12 <?= $is_dark ? 'bg-white/5 border-white/10' : 'bg-white/60 border-white' ?> border hover:scale-110 rounded-2xl text-xl flex items-center justify-center transition shadow-sm"><?= $rval['emoji'] ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full max-w-xs">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 animate-pulse">
                            Reply to <?= h($page['sender_name']) ?> 💌
                        </button>
                    </div>
                </div>

            <?php // ────────── 22. PATCH-UP: REBUILD BRIDGE ──────────
            elseif ($slide_type === 'patchup_rebuild_bridge'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center overflow-hidden" id="bridge-container-<?= $slide_num ?>">
                        <div class="relative w-full h-40 flex items-center justify-between px-6 mb-6">
                            <div class="flex flex-col items-center z-10 transition-transform duration-1000" id="bridge-char-left-<?= $slide_num ?>">
                                <span class="text-4xl">🧑‍🤝‍🧑</span>
                                <span class="text-[8px] font-bold bg-white/70 px-1 py-0.5 rounded shadow mt-1 text-slate-800"><?= h($page['sender_name']) ?></span>
                            </div>
                            <div class="flex-grow mx-4 relative h-16 flex items-center justify-around border-t-4 border-b-4 border-amber-900 border-dashed">
                                <button onclick="placePlank(0, <?= $slide_num ?>)" id="plank-0-<?= $slide_num ?>" class="w-6 h-12 bg-amber-800 rounded opacity-25 border border-amber-950 flex items-center justify-center font-bold text-white text-[10px] transition-all hover:scale-105 active:scale-95 shadow">?</button>
                                <button onclick="placePlank(1, <?= $slide_num ?>)" id="plank-1-<?= $slide_num ?>" class="w-6 h-12 bg-amber-800 rounded opacity-25 border border-amber-950 flex items-center justify-center font-bold text-white text-[10px] transition-all hover:scale-105 active:scale-95 shadow">?</button>
                                <button onclick="placePlank(2, <?= $slide_num ?>)" id="plank-2-<?= $slide_num ?>" class="w-6 h-12 bg-amber-800 rounded opacity-25 border border-amber-950 flex items-center justify-center font-bold text-white text-[10px] transition-all hover:scale-105 active:scale-95 shadow">?</button>
                            </div>
                            <div class="flex flex-col items-center z-10 transition-transform duration-1000" id="bridge-char-right-<?= $slide_num ?>">
                                <span class="text-4xl">❤️</span>
                                <span class="text-[8px] font-bold bg-white/70 px-1 py-0.5 rounded shadow mt-1 text-slate-800"><?= h($page['receiver_name']) ?></span>
                            </div>
                        </div>
                        <p class="text-xs font-semibold mt-4 text-amber-600 animate-pulse" id="bridge-hint-<?= $slide_num ?>">Tap the missing planks to rebuild the bridge 🌉</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="bridge-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Rebuild Bridge to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 23. PATCH-UP: LOCK & KEY ──────────
            elseif ($slide_type === 'patchup_lock_key'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="lock-container-<?= $slide_num ?>">
                        <div class="relative w-48 h-48 flex justify-center items-center">
                            <div class="text-8xl select-none transition-transform duration-500" id="padlock-element-<?= $slide_num ?>">🔒</div>
                            <div class="text-5xl select-none cursor-pointer absolute transition-all duration-700 animate-bounce z-10" 
                                 id="key-element-<?= $slide_num ?>" 
                                 style="transform: translate(80px, 40px);"
                                 onclick="unlockPadlockTrigger(<?= $slide_num ?>)">🔑</div>
                        </div>
                        <p class="text-xs font-semibold mt-6 animate-pulse" style="color: <?= $theme['accent'] ?>" id="lock-hint-<?= $slide_num ?>">Tap the key to unlock my heart 🔑</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="lock-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Unlock to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 24. MISS YOU: STAR COLLECTION ──────────
            elseif ($slide_type === 'miss_you_star_collection'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow h-80 overflow-hidden" id="star-collection-<?= $slide_num ?>">
                        <?php
                        $star_thoughts = [
                            "Your warmth and laughter 🌟",
                            "Our endless talk sessions 📞",
                            "How safe I feel around you 🤗",
                            "Just your presence next to me ❤️"
                        ];
                        foreach($star_thoughts as $si => $thought):
                            $x = 20 + ($si * 22) + rand(-5, 5);
                            $y = 15 + (($si % 2) * 30) + rand(-5, 5);
                        ?>
                            <div class="star-thought cursor-pointer absolute transition-all duration-300 hover:scale-125" 
                                 id="star-<?= $slide_num ?>-<?= $si ?>"
                                 style="left: <?= $x ?>%; top: <?= $y ?>%;"
                                 onclick="collectStar(<?= $si ?>, <?= $slide_num ?>, '<?= addslashes(h($thought)) ?>')">
                                <span class="text-3xl opacity-40 transition-opacity duration-305" id="star-icon-<?= $slide_num ?>-<?= $si ?>">⭐</span>
                            </div>
                        <?php endforeach; ?>
                        <div id="star-display-card-<?= $slide_num ?>" class="absolute bottom-4 inset-x-4 text-xs <?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-2xl p-4 opacity-0 transition-opacity duration-550 z-20 shadow-lg">
                            <span class="block text-[8px] font-bold uppercase tracking-wider text-yellow-400 mb-1">Catching My Thought</span>
                            <p class="text-stone-600 dark:text-stone-300 font-semibold" id="star-text-<?= $slide_num ?>"></p>
                        </div>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="star-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Collect all stars (0/4)
                        </button>
                    </div>
                </div>

            <?php // ────────── 25. MISS YOU: MOON MESSAGE ──────────
            elseif ($slide_type === 'miss_you_moon_message'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="moon-container-<?= $slide_num ?>">
                        <div class="moon-wrapper relative w-48 h-48 cursor-pointer flex justify-center items-center" onclick="openMoonMessage(<?= $slide_num ?>)">
                            <div class="text-8xl transition-all duration-1000 z-10" id="moon-element-<?= $slide_num ?>">🌙</div>
                            <div class="absolute w-36 bg-amber-50 rounded-xl p-3 border-2 border-amber-200 text-stone-850 text-[9px] opacity-0 transition-all duration-1000 scale-50 z-0" id="moon-scroll-<?= $slide_num ?>">
                                📜 "Even though we are miles apart, we share the same sky and moon. Miss you!"
                            </div>
                        </div>
                        <p class="text-xs font-semibold mt-6 animate-pulse" style="color: <?= $theme['accent'] ?>" id="moon-hint-<?= $slide_num ?>">Tap the moon to slide it open 🌙</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="moon-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Slide Moon to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 26. MISS YOU: VOICE WAVE ──────────
            elseif ($slide_type === 'miss_you_voice_wave'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="voice-wave-container-<?= $slide_num ?>">
                        <?php if (!empty($page['voice_url'])): ?>
                            <div class="w-full <?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-3xl p-6 flex flex-col items-center shadow-lg">
                                <div class="flex items-end justify-center space-x-1.5 h-16 w-full mb-6" id="audio-wave-bars-<?= $slide_num ?>">
                                    <?php for($b=0; $b<12; $b++): ?>
                                        <div class="w-1.5 rounded-full bg-indigo-500 transition-all duration-150" style="height: 10px;"></div>
                                    <?php endfor; ?>
                                </div>
                                <audio id="voice-audio-element-<?= $slide_num ?>" src="<?= h($base_url . $page['voice_url']) ?>" class="hidden" preload="none" onplay="startVoiceWave(<?= $slide_num ?>)" onpause="stopVoiceWave(<?= $slide_num ?>)" onended="stopVoiceWave(<?= $slide_num ?>)"></audio>
                                <div class="flex items-center justify-center space-x-4 mt-2">
                                    <button onclick="playVoiceAudio(<?= $slide_num ?>)" id="voice-play-btn-<?= $slide_num ?>" class="w-10 h-10 rounded-full bg-indigo-600 hover:bg-indigo-500 text-white flex items-center justify-center font-bold text-xs transition shadow-md">▶️</button>
                                    <span class="text-xs font-semibold <?= $theme['text_secondary'] ?>">Play Voice Message</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-3xl p-8 flex flex-col items-center">
                                <span class="text-4xl mb-2">🎙️</span>
                                <span class="text-xs <?= $theme['text_muted'] ?>">No voice note uploaded</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── 27. CONGRATS: TROPHY REVEAL ──────────
            elseif ($slide_type === 'congrats_trophy_reveal'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="trophy-reveal-<?= $slide_num ?>">
                        <div class="trophy-wrapper relative w-44 h-44 cursor-pointer flex justify-center items-center" onclick="revealTrophy(<?= $slide_num ?>)">
                            <div class="text-[90px] leading-none opacity-0 scale-75 transition-all duration-1000 z-0" id="trophy-element-<?= $slide_num ?>">🏆</div>
                            <div class="absolute inset-0 bg-red-700 border-4 border-red-800 rounded-3xl shadow-xl flex items-center justify-center text-white text-xs font-bold font-serif transition-all duration-700 ease-out origin-top z-10" id="trophy-drape-<?= $slide_num ?>">
                                👑 REVEAL ACHIEVEMENT
                            </div>
                        </div>
                        <p class="text-xs font-semibold mt-6 animate-pulse" style="color: <?= $theme['accent'] ?>" id="trophy-hint-<?= $slide_num ?>">Tap the cloth to reveal! 🏆</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="trophy-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Reveal Trophy to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 28. CONGRATS: FIREWORKS ──────────
            elseif ($slide_type === 'congrats_fireworks'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center border border-dashed border-white/20 rounded-3xl bg-neutral-950/20 cursor-pointer" onclick="launchCelebrationFirework(event, <?= $slide_num ?>)" id="fireworks-area-<?= $slide_num ?>">
                        <span class="text-6xl mb-4 animate-bounce">🎆</span>
                        <p class="text-sm text-yellow-400 font-bold tracking-widest uppercase">TAP THE SKY!</p>
                        <p class="text-[10px] <?= $theme['text_muted'] ?> mt-1">Click anywhere in this box to burst fireworks</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── 29. CRUSH: SECRET ENVELOPE ──────────
            elseif ($slide_type === 'crush_secret_envelope'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="crush-envelope-container-<?= $slide_num ?>">
                        <div class="envelope-wrapper relative w-56 h-40 cursor-pointer flex justify-center items-center" onclick="openCrushEnvelope(<?= $slide_num ?>)">
                            <div class="absolute inset-0 bg-pink-200 dark:bg-pink-955/40 rounded-2xl border-2 border-pink-300 shadow-md transition-all duration-500" id="crush-envelope-body-<?= $slide_num ?>"></div>
                            <div class="absolute top-0 inset-x-0 h-20 bg-pink-300 dark:bg-pink-900 rounded-t-2xl transition-all duration-500 origin-top z-10" id="crush-envelope-flap-<?= $slide_num ?>" style="clip-path: polygon(0 0, 100% 0, 50% 100%);"></div>
                            <div class="absolute w-48 h-32 bg-white/90 dark:bg-zinc-800 rounded-xl p-4 transition-all duration-500 z-0 flex flex-col justify-center items-center shadow-inner" id="crush-envelope-letter-<?= $slide_num ?>" style="transform: translateY(0);">
                                <p class="text-[10px] font-semibold text-pink-500">I have a crush on you...</p>
                            </div>
                            <div class="absolute w-12 h-12 bg-pink-600 rounded-full flex items-center justify-center text-white text-lg font-bold shadow-lg border-2 border-pink-700 z-20 transition-all duration-300 hover:scale-110 active:scale-95" id="crush-envelope-seal-<?= $slide_num ?>">💖</div>
                        </div>
                        <p class="text-xs font-semibold mt-8 animate-pulse" style="color: <?= $theme['accent'] ?>" id="crush-envelope-hint-<?= $slide_num ?>">Tap the seal to unlock 🤫</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="crush-envelope-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Unlock to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 30. CRUSH: WIPE FOG ──────────
            elseif ($slide_type === 'crush_hidden_message'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex justify-center items-center" id="fog-container-<?= $slide_num ?>">
                        <div class="relative w-72 h-44 rounded-2xl overflow-hidden shadow-lg border <?= $theme['card_border'] ?> bg-white/10 flex items-center justify-center">
                            <div class="absolute inset-4 flex flex-col justify-center items-center text-center text-xs font-bold leading-relaxed <?= $theme['text_primary'] ?>">
                                "You make my heart skip a beat every time you enter the room. I think I'm falling for you."
                            </div>
                            <canvas id="fog-canvas-<?= $slide_num ?>" class="absolute inset-0 w-full h-full cursor-crosshair z-10"></canvas>
                        </div>
                    </div>
                    <p class="text-xs font-semibold mt-4 text-pink-400 animate-pulse" id="fog-hint-<?= $slide_num ?>">Wipe away the fog to read confession 🌫️</p>
                    <div class="text-center pt-6 w-full">
                        <button id="fog-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Wipe Fog to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 31. CRUSH: HEARTBEAT HOLD ──────────
            elseif ($slide_type === 'crush_heartbeat'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="heartbeat-container-<?= $slide_num ?>">
                        <div class="heartbeat-btn cursor-pointer w-32 h-32 relative flex items-center justify-center text-rose-500 transition-all duration-100 animate-pulse select-none" 
                             id="heartbeat-btn-<?= $slide_num ?>"
                             onmousedown="startHeartbeatPress(<?= $slide_num ?>)"
                             onmouseup="stopHeartbeatPress(<?= $slide_num ?>)"
                             ontouchstart="startHeartbeatPress(<?= $slide_num ?>)"
                             ontouchend="stopHeartbeatPress(<?= $slide_num ?>)">
                            <svg viewBox="0 0 32 29.6" class="w-full h-full fill-current">
                                <path d="M23.6,0c-3.4,0-6.3,2.7-7.6,5.6C14.7,2.7,11.8,0,8.4,0C3.8,0,0,3.8,0,8.4c0,9.4,9.5,11.9,16,21.2
                                    c6.1-9.3,16-12.1,16-21.2C32,3.8,28.2,0,23.6,0z"/>
                            </svg>
                            <span class="absolute text-white text-[10px] font-bold tracking-widest uppercase">HOLD</span>
                        </div>
                        <p class="text-xs font-semibold mt-8 text-rose-400" id="heartbeat-hint-<?= $slide_num ?>">Press and hold the heart! 💓</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="heartbeat-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Hold Heart to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 32. SURPRISE: MYSTERY BOX ──────────
            elseif ($slide_type === 'surprise_mystery_box'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="mystery-box-container-<?= $slide_num ?>">
                        <div class="mystery-box cursor-pointer relative gift-shake" id="mystery-box-<?= $slide_num ?>" onclick="openMysteryBox(<?= $slide_num ?>)">
                            <div class="text-[120px] leading-none select-none">🎁</div>
                        </div>
                        <p class="text-xs font-semibold mt-6 animate-pulse" style="color: <?= $theme['accent'] ?>" id="mystery-hint-<?= $slide_num ?>">Tap the gift box to open! 🎁</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="mystery-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Open the Box to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 33. SURPRISE: SCRATCH CARD ──────────
            elseif ($slide_type === 'surprise_scratch_card'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex justify-center items-center" id="scratch-container-<?= $slide_num ?>">
                        <div class="relative w-56 h-56 rounded-3xl overflow-hidden shadow-lg border <?= $theme['card_border'] ?> bg-white/10 flex items-center justify-center">
                            <?php if (count($images) > 0): ?>
                                <img src="<?= h($base_url . $images[0]['image_path']) ?>" class="absolute inset-0 w-full h-full object-<?= $photo_fit_mode ?> <?= $photo_fit_mode === 'contain' ? 'bg-black/15' : '' ?>" alt="Secret Prize">
                            <?php else: ?>
                                <div class="absolute inset-0 flex flex-col items-center justify-center text-center p-4">
                                    <span class="text-4xl mb-2">🎁</span>
                                    <span class="text-xs font-bold <?= $theme['text_primary'] ?>">Your Special Surprise!</span>
                                </div>
                            <?php endif; ?>
                            <canvas id="scratch-canvas-<?= $slide_num ?>" class="absolute inset-0 w-full h-full cursor-crosshair z-10"></canvas>
                        </div>
                    </div>
                    <p class="text-xs font-semibold mt-4 text-pink-400 animate-pulse" id="scratch-hint-<?= $slide_num ?>">Scratch the screen to reveal prize! 🪙</p>
                    <div class="text-center pt-6 w-full">
                        <button id="scratch-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Scratch to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── 34. SURPRISE: COUNTDOWN REVEAL ──────────
            elseif ($slide_type === 'surprise_countdown_reveal'): ?>
                <div class="flex-grow flex flex-col justify-between items-center text-center w-full">
                    <div class="mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    <div class="relative w-full flex-grow flex flex-col justify-center items-center" id="countdown-reveal-container-<?= $slide_num ?>" onclick="startCountdownReveal(<?= $slide_num ?>)">
                        <div id="countdown-reveal-display-<?= $slide_num ?>" class="text-8xl font-extrabold text-pink-500 font-heading select-none w-40 h-40 border-4 border-pink-500/20 rounded-full flex items-center justify-center shadow-lg" style="opacity: 0.2">10</div>
                        <p class="text-xs font-semibold mt-6 text-pink-400 animate-pulse" id="countdown-reveal-hint-<?= $slide_num ?>">Tap to start countdown! ⏱️</p>
                    </div>
                    <div class="text-center pt-6 w-full">
                        <button id="countdown-reveal-continue-<?= $slide_num ?>" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95 opacity-50 pointer-events-none">
                            Countdown to Continue
                        </button>
                    </div>
                </div>

            <?php // ────────── PREMIUM: PHOTO MEMORY REVEAL ──────────
            elseif ($slide_type === 'premium_memory_reveal'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-memory-reveal-container-<?= $slide_num ?>">
                    <div class="z-10 mb-4 px-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="relative w-full max-w-sm aspect-[4/3] rounded-3xl overflow-hidden shadow-2xl border border-white/10 glass-dark mb-6 flex items-center justify-center group">
                        <div id="reveal-blur-overlay-<?= $slide_num ?>" class="absolute inset-0 bg-black/60 backdrop-blur-xl z-20 flex flex-col items-center justify-center transition-all duration-1000">
                            <span class="text-4xl mb-3 animate-pulse">🔒</span>
                            <p class="text-xs font-semibold text-white/70">Unlocking memories...</p>
                        </div>
                        <div class="absolute inset-0 w-full h-full" id="reveal-photos-wrapper-<?= $slide_num ?>">
                            <?php if (empty($imgs)): ?>
                                <div class="w-full h-full flex items-center justify-center text-xs text-slate-500">No photos uploaded</div>
                            <?php else: ?>
                                <?php foreach ($imgs as $i_idx => $pinfo): 
                                    $p_path = $pinfo['medium'] ?? $pinfo['original'] ?? $pinfo['image_path'] ?? '';
                                ?>
                                    <div class="absolute inset-0 w-full h-full opacity-0 scale-95 transition-all duration-1000 ease-out" data-reveal-idx="<?= $i_idx ?>">
                                        <img src="<?= h($base_url . $p_path) ?>" class="w-full h-full object-cover select-none">
                                        <?php if (!empty($pinfo['caption']) || !empty($pinfo['date'])): ?>
                                            <div class="absolute bottom-4 inset-x-4 bg-black/60 backdrop-blur-md px-3 py-2 rounded-xl text-center">
                                                <?php if (!empty($pinfo['caption'])): ?>
                                                    <p class="text-xs text-white font-medium"><?= h($pinfo['caption']) ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($pinfo['date'])): ?>
                                                    <span class="text-[9px] text-pink-400 font-bold uppercase tracking-wider block mt-0.5"><?= h($pinfo['date']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($msg)): ?>
                        <div class="w-full max-w-sm px-4 mb-6 z-10">
                            <div class="glass-dark rounded-2xl p-4 text-center">
                                <p class="text-xs sm:text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap"><?= h($msg) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    

                    <div class="text-center pt-2 w-full z-10">
                        <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── PREMIUM: HEART FORMATION ──────────
            elseif ($slide_type === 'premium_heart_formation'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
                $fav_img = !empty($imgs) ? ($imgs[0]['original'] ?? $imgs[0]['medium'] ?? $imgs[0]['image_path'] ?? '') : '';
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-heart-formation-container-<?= $slide_num ?>">
                    <div class="z-10 mb-4 px-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>
                    
                    <div class="relative w-full max-w-sm aspect-square mb-6 flex items-center justify-center">
                        <canvas id="heart-formation-canvas-<?= $slide_num ?>" class="absolute inset-0 w-full h-full pointer-events-none z-10"></canvas>
                        
                        <div id="heart-reveal-inner-<?= $slide_num ?>" class="opacity-0 scale-90 w-44 h-44 rounded-full overflow-hidden shadow-2xl border-4 border-pink-500/30 transition-all duration-1000 z-20 flex items-center justify-center bg-black">
                            <?php if (!empty($fav_img)): ?>
                                <img src="<?= h($base_url . $fav_img) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-3xl animate-pulse">❤️</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($msg)): ?>
                        <div id="heart-message-<?= $slide_num ?>" class="w-full max-w-sm px-4 mb-6 z-10 opacity-0 transition-opacity duration-1000">
                            <div class="glass-dark rounded-2xl p-4 text-center">
                                <p class="text-xs sm:text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap font-semibold"><?= h($msg) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    

                    <div class="text-center pt-2 w-full z-10">
                        <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── PREMIUM: COLLAGE EXPLOSION ──────────
            elseif ($slide_type === 'premium_collage_explosion'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-collage-explosion-container-<?= $slide_num ?>">
                    <!-- Desktop Split Container -->
                    <div class="w-full h-full flex flex-col md:flex-row md:items-center md:justify-between md:gap-8 max-w-5xl mx-auto px-4 md:px-8 py-2 md:py-6">
                        <!-- Left Side: Title, Message, Video Button, Voice, Continue -->
                        <div class="w-full md:w-[45%] flex flex-col justify-center items-center md:items-start text-center md:text-left space-y-4 z-10">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                                <h2 class="text-2xl sm:text-3xl md:text-4xl font-extrabold <?= $theme['text_primary'] ?> mt-1 tracking-wide leading-tight"><?= $slide_title ?></h2>
                            </div>

                            <?php if (!empty($msg)): ?>
                                <div id="collage-message-<?= $slide_num ?>" class="w-full opacity-0 transition-opacity duration-1000">
                                    <div class="glass-dark rounded-2xl p-4 text-center md:text-left">
                                        <p class="text-xs sm:text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap font-semibold"><?= h($msg) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            

                            <div class="w-full pt-2">
                                <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full md:w-auto px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                                    Continue →
                                </button>
                            </div>
                        </div>

                        <!-- Right Side: The Collage Area -->
                        <div class="w-full md:w-[50%] flex items-center justify-center relative mt-6 md:mt-0 z-10">
                            <div class="relative w-full max-w-sm sm:max-w-md md:max-w-full aspect-[4/3] overflow-hidden bg-slate-950/20 rounded-3xl border border-white/5 shadow-2xl" id="collage-explosion-area-<?= $slide_num ?>">
                                <?php if (empty($imgs)): ?>
                                    <div class="w-full h-full flex items-center justify-center text-xs text-slate-500">No photos uploaded</div>
                                <?php else: ?>
                                    <?php foreach ($imgs as $i_idx => $pinfo): 
                                        $p_path = $pinfo['medium'] ?? $pinfo['original'] ?? $pinfo['image_path'] ?? '';
                                        $rot = rand(-15, 15);
                                    ?>
                                        <div class="absolute w-24 sm:w-32 md:w-36 p-1.5 pb-4 bg-white text-black shadow-lg rounded border border-gray-200 pointer-events-none transition-all duration-1000 ease-out opacity-0" 
                                             id="collage-card-<?= $slide_num ?>-<?= $i_idx ?>" 
                                             data-final-rot="<?= $rot ?>">
                                            <div class="w-full aspect-square overflow-hidden bg-gray-100 mb-1">
                                                <img src="<?= h($base_url . $p_path) ?>" class="w-full h-full object-cover">
                                            </div>
                                            <?php if (!empty($pinfo['caption'])): ?>
                                                <p class="text-[7px] text-center font-bold tracking-tight truncate"><?= h($pinfo['caption']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php // ────────── PREMIUM: FLOATING MEMORIES ──────────
            elseif ($slide_type === 'premium_floating_memories'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative overflow-hidden" id="premium-floating-memories-container-<?= $slide_num ?>">
                    <div class="absolute inset-0 pointer-events-none z-0" id="floating-memories-space-<?= $slide_num ?>">
                        <?php if (!empty($imgs)): ?>
                            <?php foreach ($imgs as $i_idx => $pinfo): 
                                $p_path = $pinfo['medium'] ?? $pinfo['original'] ?? $pinfo['image_path'] ?? '';
                                $speed = rand(15, 30);
                                $delay = rand(0, 10);
                                $left = rand(5, 85);
                                $scale = rand(7, 13) / 10;
                            ?>
                                <div class="absolute bg-white/95 p-1 pb-3 text-black shadow-md rounded border border-white/20 animate-drift pointer-events-none"
                                     style="left: <?= $left ?>%; bottom: -150px; animation-duration: <?= $speed ?>s; animation-delay: -<?= $delay ?>s; transform: scale(<?= $scale ?>); z-index: <?= $i_idx % 2 ?>; opacity: 0.85;">
                                    <div class="w-24 h-24 sm:w-28 sm:h-28 md:w-36 md:h-36 overflow-hidden bg-gray-100 mb-0.5">
                                        <img src="<?= h($base_url . $p_path) ?>" class="w-full h-full object-cover">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="z-10 mb-4 px-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>

                    <?php if (!empty($msg)): ?>
                        <div class="w-full max-w-sm px-4 mb-6 z-10">
                            <div class="glass-dark rounded-2xl p-5 text-center shadow-lg backdrop-blur-md">
                                <p class="text-xs sm:text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap"><?= h($msg) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    

                    <div class="text-center pt-2 w-full z-10">
                        <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── PREMIUM: STAR MEMORY SKY ──────────
            elseif ($slide_type === 'premium_star_sky'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-star-sky-container-<?= $slide_num ?>">
                    <!-- Desktop Split Container -->
                    <div class="w-full h-full flex flex-col md:flex-row md:items-center md:justify-between md:gap-8 max-w-5xl mx-auto px-4 md:px-8 py-2 md:py-6">
                        <!-- Left Side: Title, Message, Video Button, Voice, Continue -->
                        <div class="w-full md:w-[45%] flex flex-col justify-center items-center md:items-start text-center md:text-left space-y-4 z-10">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400"><?= $slide_subtitle ?></span>
                                <h2 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white mt-1 tracking-wide leading-tight"><?= $slide_title ?></h2>
                            </div>

                            <?php if (!empty($msg)): ?>
                                <div class="w-full">
                                    <div class="glass-dark rounded-2xl p-4 text-center md:text-left">
                                        <p class="text-xs sm:text-sm leading-relaxed text-slate-300 whitespace-pre-wrap font-medium"><?= h($msg) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            

                            <div class="w-full pt-2">
                                <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full md:w-auto px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                                    Continue →
                                </button>
                            </div>
                        </div>

                        <!-- Right Side: The Star Sky Viewport -->
                        <div class="w-full md:w-[50%] flex items-center justify-center relative mt-6 md:mt-0 z-10">
                            <div class="relative w-full max-w-sm sm:max-w-md md:max-w-full aspect-[4/3] overflow-hidden rounded-3xl border border-white/10 flex items-center justify-center bg-black/40 shadow-2xl" id="star-sky-viewport-<?= $slide_num ?>">
                                <canvas id="star-sky-canvas-<?= $slide_num ?>" class="absolute inset-0 w-full h-full pointer-events-none z-0 bg-black"></canvas>
                                <?php if (empty($imgs)): ?>
                                    <div class="w-full h-full flex items-center justify-center text-xs text-slate-500 z-10">No photos uploaded</div>
                                <?php else: ?>
                                    <?php foreach ($imgs as $i_idx => $pinfo): 
                                        $p_path = $pinfo['medium'] ?? $pinfo['original'] ?? $pinfo['image_path'] ?? '';
                                        $left = rand(15, 80);
                                        $top = rand(15, 75);
                                    ?>
                                        <div class="absolute cursor-pointer flex flex-col items-center group transition-all duration-300 z-10" 
                                             id="sky-star-<?= $slide_num ?>-<?= $i_idx ?>" 
                                             style="left: <?= $left ?>%; top: <?= $top ?>%;"
                                             onclick="toggleStarPhoto(<?= $slide_num ?>, <?= $i_idx ?>)">
                                            <div class="w-4 h-4 rounded-full bg-white animate-twinkle flex items-center justify-center shadow-[0_0_12px_#fff] group-hover:scale-125 transition"></div>
                                            <p class="text-[6px] text-white/50 font-bold mt-1 tracking-wider uppercase">Reveal ✨</p>
                                            
                                            <div class="absolute w-28 sm:w-36 md:w-40 p-1.5 pb-4 bg-white text-black shadow-2xl rounded border border-gray-200 pointer-events-none opacity-0 scale-75 -translate-y-4 transition-all duration-500 z-50"
                                                 id="star-photo-<?= $slide_num ?>-<?= $i_idx ?>">
                                                <div class="w-full aspect-square overflow-hidden bg-gray-100 mb-1">
                                                    <img src="<?= h($base_url . $p_path) ?>" class="w-full h-full object-cover">
                                                </div>
                                                <?php if (!empty($pinfo['caption'])): ?>
                                                    <p class="text-[7px] text-center font-bold tracking-tight truncate"><?= h($pinfo['caption']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php // ────────── PREMIUM: MEMORY BOOK ──────────
            elseif ($slide_type === 'premium_memory_book'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
                $default_quotes = [
                    'Every moment with you is a memory I treasure forever.',
                    'You make ordinary moments feel magical.',
                    'My favorite place in the world is right next to you.',
                    'With you, even silence speaks volumes of love.',
                    'You are the reason I believe in beautiful things.',
                    'Some moments are so perfect, they deserve their own page.',
                    'In your eyes, I found my home.',
                    'Every love story is beautiful, but ours is my favorite.',
                    'You are my today and all of my tomorrows.',
                    'The best thing to hold onto in life is each other.'
                ];
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-memory-book-container-<?= $slide_num ?>">
                    <!-- Desktop Split Container -->
                    <div class="w-full h-full flex flex-col md:flex-row md:items-center md:justify-between md:gap-8 max-w-5xl mx-auto px-4 md:px-8 py-2 md:py-6">
                        <!-- Left Side: Title, Message, Video Button, Voice, Continue -->
                        <div class="w-full md:w-[45%] flex flex-col justify-center items-center md:items-start text-center md:text-left space-y-4 z-10">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                                <h2 class="text-2xl sm:text-3xl md:text-4xl font-extrabold <?= $theme['text_primary'] ?> mt-1 tracking-wide leading-tight"><?= $slide_title ?></h2>
                            </div>

                            <?php if (!empty($msg)): ?>
                                <div class="w-full">
                                    <div class="glass-dark rounded-2xl p-4 text-center md:text-left">
                                        <p class="text-xs sm:text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap font-medium"><?= h($msg) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            

                            <div class="w-full pt-2">
                                <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full md:w-auto px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                                    Continue →
                                </button>
                            </div>
                        </div>

                        <!-- Right Side: The Memory Book Viewport -->
                        <div class="w-full md:w-[50%] flex flex-col items-center justify-center relative mt-6 md:mt-0 z-10">
                            <div class="relative w-full max-w-sm sm:max-w-md md:max-w-full aspect-[4/3] flex flex-col items-center justify-center bg-slate-950/20 rounded-3xl border border-white/5 p-4 shadow-2xl" id="memory-book-viewport-<?= $slide_num ?>">
                                <?php if (empty($imgs)): ?>
                                    <div class="w-full h-full flex items-center justify-center text-xs text-slate-500 bg-slate-900 rounded-3xl border border-white/10">No pages configured</div>
                                <?php else: ?>
                                    <div class="w-80 h-64 sm:w-[420px] sm:h-[336px] md:w-[440px] md:h-[352px] relative transform-style-preserve-3d" id="memory-book-wrapper-<?= $slide_num ?>">
                                        <?php foreach ($imgs as $i_idx => $pinfo): 
                                            $p_path = $pinfo['medium'] ?? $pinfo['original'] ?? $pinfo['image_path'] ?? '';
                                        ?>
                                            <div class="absolute inset-0 w-full h-full origin-left bg-stone-50 text-stone-800 shadow-2xl rounded-r-2xl border-l border-stone-300 transform-style-preserve-3d transition-transform duration-1000 ease-in-out z-10" 
                                                 id="book-page-<?= $slide_num ?>-<?= $i_idx ?>" 
                                                 data-page-index="<?= $i_idx ?>">
                                                <div class="absolute inset-0 p-3.5 flex flex-col justify-between h-full backface-hidden">
                                                    <div class="w-full h-28 sm:h-44 md:h-48 rounded-xl overflow-hidden border border-stone-300/60 shadow-md mb-2 bg-stone-200">
                                                        <img src="<?= h($base_url . $p_path) ?>" class="w-full h-full object-cover select-none">
                                                    </div>
                                                    <div class="flex-grow flex flex-col justify-start text-left overflow-y-auto pr-0.5" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                        <?php 
                                                            $page_caption = $pinfo['caption'] ?? '';
                                                            $page_date = $pinfo['date'] ?? '';
                                                            $page_memory = $pinfo['memory'] ?? '';
                                                            $has_text = !empty($page_caption) || !empty($page_date) || !empty($page_memory);
                                                        ?>
                                                        <?php if (!empty($page_caption)): ?>
                                                            <p class="text-[10px] font-bold text-stone-900 leading-tight mb-0.5">📝 <?= h($page_caption) ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($page_date)): ?>
                                                            <p class="text-[8px] font-bold text-pink-600 mb-1">📅 <?= h($page_date) ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($page_memory)): ?>
                                                            <p class="text-[9px] text-stone-600 leading-relaxed font-medium italic whitespace-pre-wrap"><?= h($page_memory) ?></p>
                                                        <?php elseif (!$has_text): ?>
                                                            <p class="text-[9px] text-stone-500 leading-relaxed font-medium italic">💕 <?= h($default_quotes[$i_idx % count($default_quotes)]) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-[7px] text-stone-400 font-bold uppercase tracking-wider mt-1 text-right">Page <?= $i_idx + 1 ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <!-- Prev/Next Controls under the book -->
                            <div class="flex items-center space-x-4 mt-4">
                                <button type="button" onclick="prevBookPage(<?= $slide_num ?>)" class="px-5 py-2.5 bg-slate-900/90 hover:bg-slate-800 active:scale-95 text-white text-xs font-bold rounded-xl transition shadow-lg border border-slate-700">◀ Prev</button>
                                <button type="button" onclick="nextBookPage(<?= $slide_num ?>)" class="px-5 py-2.5 bg-slate-900/90 hover:bg-slate-800 active:scale-95 text-white text-xs font-bold rounded-xl transition shadow-lg border border-slate-700">Next ▶</button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php // ────────── PREMIUM: REASONS WHY YOU ARE SPECIAL ──────────
            elseif ($slide_type === 'premium_reasons_special'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-reasons-special-container-<?= $slide_num ?>">
                    <div class="z-10 mb-4 px-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>

                    <div class="relative w-full max-w-sm aspect-[4/3] mb-6 flex items-center justify-center" id="reasons-stack-<?= $slide_num ?>">
                        <?php 
                        $reasons = array_filter(array_map('trim', explode("\n", $msg)));
                        if (empty($reasons)) {
                            $reasons = ["Your beautiful smile", "The sound of your voice", "Your kind heart", "Your endless support"];
                        }
                        foreach ($reasons as $r_idx => $reason):
                            $pinfo = $imgs[$r_idx] ?? null;
                            $p_path = $pinfo ? ($pinfo['medium'] ?? $pinfo['original'] ?? '') : '';
                        ?>
                            <div class="absolute w-64 p-5 rounded-3xl transition-all duration-700 shadow-[0_0_25px_rgba(236,72,153,0.15)] border border-pink-500/25 flex flex-col justify-between items-center text-center bg-zinc-950/80 backdrop-blur-xl pointer-events-none select-none transform opacity-0 scale-90"
                                 id="reasons-card-<?= $slide_num ?>-<?= $r_idx ?>" 
                                 data-reason-idx="<?= $r_idx ?>">
                                
                                <?php if (!empty($p_path)): ?>
                                    <div class="w-full aspect-[4/3] rounded-2xl overflow-hidden mb-4 shadow-lg border border-white/5 bg-zinc-900">
                                        <img src="<?= h($base_url . $p_path) ?>" class="w-full h-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="text-4xl mb-4 animate-bounce">💖</div>
                                <?php endif; ?>
                                
                                <p class="text-sm font-semibold text-white leading-relaxed mb-1"><?= h($reason) ?></p>
                                <span class="text-[9px] uppercase tracking-wider text-pink-400 font-bold">Reason #<?= $r_idx + 1 ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex items-center space-x-3 mb-6 z-10">
                        <button type="button" onclick="nextReasonCard(<?= $slide_num ?>, <?= count($reasons) ?>)" class="px-5 py-2.5 bg-pink-600 hover:bg-pink-500 active:scale-95 text-white text-xs font-bold rounded-2xl shadow-lg transition">Next Reason 💖</button>
                    </div>

                    

                    <div class="text-center pt-2 w-full z-10">
                        <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── PREMIUM: PHOTO PUZZLE REVEAL ──────────
            elseif ($slide_type === 'premium_puzzle_reveal'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
                $fav_img = !empty($imgs) ? ($imgs[0]['original'] ?? $imgs[0]['medium'] ?? $imgs[0]['image_path'] ?? '') : '';
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-puzzle-reveal-container-<?= $slide_num ?>">
                    <!-- Desktop Split Container -->
                    <div class="w-full h-full flex flex-col md:flex-row md:items-center md:justify-between md:gap-8 max-w-5xl mx-auto px-4 md:px-8 py-2 md:py-6">
                        <!-- Left Side: Title, Message, Video Button, Voice, Continue -->
                        <div class="w-full md:w-[45%] flex flex-col justify-center items-center md:items-start text-center md:text-left space-y-4 z-10">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                                <h2 class="text-2xl sm:text-3xl md:text-4xl font-extrabold <?= $theme['text_primary'] ?> mt-1 tracking-wide leading-tight"><?= $slide_title ?></h2>
                            </div>

                            <?php if (!empty($msg)): ?>
                                <div id="puzzle-message-<?= $slide_num ?>" class="w-full opacity-0 transition-opacity duration-1000">
                                    <div class="glass-dark rounded-2xl p-4 text-center md:text-left">
                                        <p class="text-xs sm:text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap font-semibold"><?= h($msg) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            

                            <div class="w-full pt-2">
                                <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full md:w-auto px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                                    Continue →
                                </button>
                            </div>
                        </div>

                        <!-- Right Side: The Puzzle Board Viewport -->
                        <div class="w-full md:w-[50%] flex items-center justify-center relative mt-6 md:mt-0 z-10">
                            <div class="relative w-full max-w-xs sm:max-w-sm md:max-w-md aspect-square overflow-hidden rounded-2xl bg-zinc-900 border border-white/10 shadow-2xl flex items-center justify-center" id="puzzle-board-<?= $slide_num ?>">
                                <?php if (empty($fav_img)): ?>
                                    <div class="text-xs text-slate-500">No photos uploaded for puzzle</div>
                                <?php else: ?>
                                    <div class="absolute inset-0 grid grid-cols-3 grid-rows-3 w-full h-full pointer-events-none" id="puzzle-grid-<?= $slide_num ?>">
                                        <?php for ($r = 0; $r < 3; $r++): 
                                            for ($c = 0; $c < 3; $c++): 
                                                $p_idx = $r * 3 + $c;
                                                $randX = rand(-120, 120);
                                                $randY = rand(-120, 120);
                                                $randRot = rand(-45, 45);
                                            ?>
                                                <div class="relative w-full h-full border border-black/10 overflow-hidden transition-all duration-1000 ease-out opacity-0"
                                                     id="puzzle-piece-<?= $slide_num ?>-<?= $p_idx ?>"
                                                     data-row="<?= $r ?>"
                                                     data-col="<?= $c ?>"
                                                     data-start-x="<?= $randX ?>"
                                                     data-start-y="<?= $randY ?>"
                                                     data-start-rot="<?= $randRot ?>">
                                                    <div class="absolute w-[300%] h-[300%] bg-cover"
                                                         style="background-image: url('<?= h($base_url . $fav_img) ?>'); left: -<?= $c * 100 ?>%; top: -<?= $r * 100 ?>%; width: 300%; height: 300%;"></div>
                                                </div>
                                            <?php endfor; 
                                        endfor; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php // ────────── PREMIUM: LOVE COUNTER ──────────
            elseif ($slide_type === 'premium_love_counter'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
                $rel_date = $page['relationship_date'] ?? '2025-01-01';
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-love-counter-container-<?= $slide_num ?>" data-rel-date="<?= h($rel_date) ?>">
                    <div class="z-10 mb-4 px-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>

                    <div class="grid grid-cols-2 gap-3 w-full max-w-sm mb-6 z-10 px-4">
                        <div class="glass-dark border border-white/10 rounded-2xl p-4 flex flex-col justify-center items-center">
                            <span class="text-3xl font-extrabold tracking-tight text-white mb-0.5" id="counter-days-<?= $slide_num ?>">0</span>
                            <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Days Together</span>
                        </div>
                        <div class="glass-dark border border-white/10 rounded-2xl p-4 flex flex-col justify-center items-center">
                            <span class="text-2xl font-extrabold tracking-tight text-white mb-0.5" id="counter-hours-<?= $slide_num ?>">0</span>
                            <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Hours Together</span>
                        </div>
                        <div class="glass-dark border border-white/10 rounded-2xl p-4 flex flex-col justify-center items-center">
                            <span class="text-2xl font-extrabold tracking-tight text-white mb-0.5" id="counter-mins-<?= $slide_num ?>">0</span>
                            <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Minutes Together</span>
                        </div>
                        <div class="glass-dark border border-white/10 rounded-2xl p-4 flex flex-col justify-center items-center">
                            <span class="text-2xl font-extrabold tracking-tight text-white mb-0.5" id="counter-secs-<?= $slide_num ?>">0</span>
                            <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Seconds Together</span>
                        </div>
                    </div>

                    <?php if (!empty($msg)): ?>
                        <div class="w-full max-w-sm px-4 mb-6 z-10">
                            <div class="glass-dark rounded-2xl p-4 text-center">
                                <p class="text-xs sm:text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap font-medium"><?= h($msg) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    

                    <div class="text-center pt-2 w-full z-10">
                        <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── PREMIUM: MEMORY TIMELINE ──────────
            elseif ($slide_type === 'premium_memory_timeline'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-memory-timeline-container-<?= $slide_num ?>">
                    <div class="z-10 mb-4 px-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1"><?= $slide_title ?></h2>
                    </div>

                    <div class="relative w-full max-w-sm aspect-[3/4] mb-6 overflow-y-auto px-4 z-10 border border-white/5 bg-zinc-950/20 rounded-3xl flex flex-col items-center py-6 timeline-scroll-container" id="timeline-area-<?= $slide_num ?>" style="scrollbar-width: none; -ms-overflow-style: none;">
                        <?php 
                        $has_rich_timeline = !empty($imgs);
                        $timeline_items = [];
                        if ($has_rich_timeline) {
                            foreach ($imgs as $i_idx => $pinfo) {
                                $timeline_items[] = [
                                    'title' => !empty($pinfo['caption']) ? $pinfo['caption'] : "Milestone #" . ($i_idx + 1),
                                    'date' => $pinfo['date'] ?? '',
                                    'desc' => $pinfo['memory'] ?? '',
                                    'img' => $pinfo['medium'] ?? $pinfo['original'] ?? ''
                                ];
                            }
                        } else {
                            $default_milestones = array_filter(array_map('trim', explode("\n", $msg)));
                            if (empty($default_milestones)) {
                                $default_milestones = ["Our First Hello 💬", "First Voice Call 📞", "The First Meetup 🍽️", "Our Special Date 🌹"];
                            }
                            foreach ($default_milestones as $i_idx => $miles_text) {
                                $timeline_items[] = [
                                    'title' => $miles_text,
                                    'date' => '',
                                    'desc' => '',
                                    'img' => ''
                                ];
                            }
                        }
                        ?>
                        <div class="relative flex flex-col w-full items-start pl-8 border-l-2 border-pink-500/20 space-y-8 select-none py-2 text-left">
                            <div class="absolute left-[-2px] top-0 bottom-full bg-pink-500 w-[2px] transition-all duration-[2000ms] ease-out" id="timeline-progress-bar-<?= $slide_num ?>"></div>

                            <?php foreach ($timeline_items as $m_idx => $item): ?>
                                <div class="relative w-full flex flex-col transition-all duration-700 translate-x-4 opacity-0" 
                                     id="timeline-node-<?= $slide_num ?>-<?= $m_idx ?>" 
                                     data-node-idx="<?= $m_idx ?>">
                                    <div class="absolute left-[-42px] top-1.5 w-5 h-5 rounded-full border-4 border-zinc-950 bg-pink-500 shadow-md"></div>
                                    
                                    <div class="glass-dark border border-white/10 rounded-2xl p-4 w-full">
                                        <div class="flex items-center justify-between mb-1.5 flex-wrap gap-1">
                                            <p class="text-xs font-bold text-white"><?= h($item['title']) ?></p>
                                            <?php if (!empty($item['date'])): ?>
                                                <span class="text-[8px] font-bold text-pink-400 bg-pink-500/10 px-2 py-0.5 rounded-full uppercase tracking-wider"><?= h($item['date']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($item['desc'])): ?>
                                            <p class="text-[10px] text-slate-350 leading-relaxed mb-2 whitespace-pre-wrap"><?= h($item['desc']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($item['img'])): ?>
                                            <div class="w-full rounded-xl overflow-hidden shadow border border-white/5 bg-zinc-950/60 flex justify-center items-center">
                                                <img src="<?= h($base_url . $item['img']) ?>" class="w-full h-auto max-h-[260px] object-contain block">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    

                    <div class="text-center pt-2 w-full z-10">
                        <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── PREMIUM: PHOTO MOSAIC HEART ──────────
            elseif ($slide_type === 'premium_mosaic_heart'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-mosaic-heart-container-<?= $slide_num ?>">
                    <!-- Desktop Split Container -->
                    <div class="w-full h-full flex flex-col md:flex-row md:items-center md:justify-between md:gap-8 max-w-5xl mx-auto px-4 md:px-8 py-2 md:py-6">
                        <!-- Left Side: Title, Message, Video Button, Voice, Continue -->
                        <div class="w-full md:w-[45%] flex flex-col justify-center items-center md:items-start text-center md:text-left space-y-4 z-10">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                                <h2 class="text-2xl sm:text-3xl md:text-4xl font-extrabold <?= $theme['text_primary'] ?> mt-1 tracking-wide leading-tight"><?= $slide_title ?></h2>
                            </div>

                            <?php if (!empty($msg)): ?>
                                <div id="mosaic-message-<?= $slide_num ?>" class="w-full opacity-0 transition-opacity duration-1000">
                                    <div class="glass-dark rounded-2xl p-4 text-center md:text-left">
                                        <p class="text-xs sm:text-sm leading-relaxed <?= $theme['text_secondary'] ?> whitespace-pre-wrap font-semibold"><?= h($msg) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            

                            <div class="w-full pt-2">
                                <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="w-full md:w-auto px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                                    Continue →
                                </button>
                            </div>
                        </div>

                        <!-- Right Side: The Mosaic Grid Area -->
                        <div class="w-full md:w-[50%] flex items-center justify-center relative mt-6 md:mt-0 z-10">
                            <div class="relative w-full max-w-[280px] sm:max-w-[340px] md:max-w-md aspect-square overflow-hidden rounded-3xl bg-zinc-950/40 border border-white/5 flex items-center justify-center p-3 shadow-inner" id="mosaic-board-<?= $slide_num ?>">
                                <?php if (empty($imgs)): ?>
                                    <div class="text-xs text-slate-500">No photos uploaded</div>
                                <?php else: ?>
                                    <div class="grid grid-cols-10 grid-rows-10 gap-0.5 w-full h-full" id="mosaic-grid-<?= $slide_num ?>">
                                        <?php 
                                        $cat_shape = $page['category'];
                                        for ($r = 0; $r < 10; $r++):
                                            for ($c = 0; $c < 10; $c++):
                                                $in_shape = inCategoryShape($cat_shape, $r, $c);
                                                $tile_img = $imgs[($r * 10 + $c) % count($imgs)]['thumb'] ?? $imgs[($r * 10 + $c) % count($imgs)]['original'] ?? '';
                                            ?>
                                                <div class="w-full h-full bg-slate-900 rounded-[2px] transition-all duration-700 ease-out scale-90 opacity-0"
                                                     id="mosaic-tile-<?= $slide_num ?>-<?= $r ?>-<?= $c ?>"
                                                     data-row="<?= $r ?>"
                                                     data-col="<?= $c ?>"
                                                     data-in-shape="<?= $in_shape ? 1 : 0 ?>">
                                                    <?php if ($in_shape && !empty($tile_img)): ?>
                                                        <img src="<?= h($base_url . $tile_img) ?>" class="w-full h-full object-cover select-none">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endfor;
                                        endfor;
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php // ────────── PREMIUM: FAVORITE PHOTO SPOTLIGHT ──────────
            elseif ($slide_type === 'premium_photo_spotlight'): 
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
                $fav_img = !empty($imgs) ? ($imgs[0]['original'] ?? $imgs[0]['medium'] ?? $imgs[0]['image_path'] ?? '') : '';
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative bg-black overflow-hidden" id="premium-photo-spotlight-container-<?= $slide_num ?>">
                    <div id="spotlight-mask-<?= $slide_num ?>" class="absolute inset-0 bg-black z-10 transition-all duration-1000 select-none pointer-events-none" style="background-image: radial-gradient(circle 90px at 50% 45%, transparent 100%, rgba(0,0,0,0.95) 100%);"></div>

                    <div class="z-20 mb-4 px-4 relative">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-400/80"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-1"><?= $slide_title ?></h2>
                    </div>

                    <div class="relative w-full max-w-sm aspect-[4/3] mb-6 rounded-3xl overflow-hidden shadow-2xl border border-white/10 bg-zinc-950 z-0 flex items-center justify-center" id="spotlight-photo-wrap-<?= $slide_num ?>">
                        <?php if (!empty($fav_img)): ?>
                            <img src="<?= h($base_url . $fav_img) ?>" class="w-full h-full object-cover scale-105 transition-transform duration-[4000ms] ease-out select-none" id="spotlight-img-<?= $slide_num ?>">
                        <?php else: ?>
                            <span class="text-5xl animate-pulse">⭐</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($msg)): ?>
                        <div class="w-full max-w-sm px-4 mb-6 z-20 relative">
                            <div class="glass-dark border border-white/10 rounded-2xl p-4 text-center">
                                <p class="text-xs sm:text-sm leading-relaxed text-slate-200 whitespace-pre-wrap font-medium typing-cursor" id="spotlight-message-<?= $slide_num ?>" data-spotlight-msg="<?= h($msg) ?>"></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    

                    <div class="text-center pt-2 w-full z-20 relative">
                        <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>
                </div>

            <?php // ────────── PREMIUM: OUR CHATS ──────────
            elseif ($slide_type === 'premium_our_chats'):
                $slide_key = $slide['key'];
                $msg = $slide_data[$slide_key . '_message'] ?? '';
                $imgs = $slide_data[$slide_key . '_images'] ?? [];
                if (empty($imgs)) {
                    $imgs = $images;
                }
                
                // Get the configured moments
                $moments = [];
                for ($m_idx = 1; $m_idx <= 5; $m_idx++) {
                    $moment_label = $slide_data[$slide_key . "_moment_{$m_idx}_label"] ?? '';
                    $moment_title = $slide_data[$slide_key . "_moment_{$m_idx}_title"] ?? '';
                    $moment_dialogue = $slide_data[$slide_key . "_moment_{$m_idx}_dialogue"] ?? '';
                    
                    if (!empty($moment_label) || !empty($moment_title) || !empty($moment_dialogue)) {
                        $moments[] = [
                            'label' => $moment_label ?: "Moment {$m_idx}",
                            'title' => $moment_title ?: "Moment Title",
                            'dialogue' => $moment_dialogue
                        ];
                    }
                }
                
                // Fallback default moment if none are configured
                if (empty($moments)) {
                    $moments[] = [
                        'label' => 'Our Chat 😍',
                        'title' => 'Screenshots from our story 💬',
                        'dialogue' => "Her: You save everything about me 😂\nYou: Everything? 😜\nHer: What if someday I accidentally lose you?\nHer: My soul would leave my body 🥺\nYou: Drama queen 😂"
                    ];
                }
            ?>
                <div class="flex-grow flex flex-col justify-center items-center text-center w-full h-full relative" id="premium-our-chats-container-<?= $slide_num ?>">
                    <!-- Header -->
                    <div class="z-10 mb-4 px-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>"><?= $slide_subtitle ?></span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold <?= $theme['text_primary'] ?> mt-1 font-heading"><?= $slide_title ?></h2>
                    </div>

                    <!-- Scrollable Moment Tags -->
                    <div class="w-full max-w-sm px-4 mb-4 z-10">
                        <div class="flex items-center space-x-2 overflow-x-auto py-2 chat-moments-scroll">
                            <?php foreach ($moments as $m_idx => $m): ?>
                                <button type="button" 
                                        onclick="selectChatMoment(<?= $slide_num ?>, <?= $m_idx ?>)" 
                                        id="chat-moment-tab-<?= $slide_num ?>-<?= $m_idx ?>" 
                                        class="chat-moment-tag px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap shadow-md border transition duration-300 <?= $m_idx === 0 ? 'bg-pink-500 border-pink-500 text-white' : 'bg-zinc-900/60 border-zinc-800 text-zinc-400' ?>">
                                    <?= h($m['label']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Chat Window Container -->
                    <div class="w-full max-w-sm px-4 mb-6 z-10">
                        <div class="glass-dark border border-white/10 rounded-3xl p-4 shadow-2xl relative overflow-hidden text-left min-h-[300px] flex flex-col justify-between">
                            <!-- Active Moment Title -->
                            <div class="border-b border-white/5 pb-2.5 mb-3 flex items-center justify-between">
                                <span id="chat-moment-title-<?= $slide_num ?>" class="text-[10px] font-bold tracking-wider text-pink-400 uppercase">
                                    <?= h($moments[0]['title']) ?>
                                </span>
                                <span class="text-[9px] text-zinc-500 font-mono">SoulSync Secure Chat</span>
                            </div>

                            <!-- Chat Messages Wrapper -->
                            <div id="chat-messages-<?= $slide_num ?>" class="flex-grow space-y-3 py-2 overflow-y-auto max-h-[250px] min-h-[200px] flex flex-col justify-start" data-theme-gradient="<?= h($theme['btn_gradient']) ?>">
                                <!-- Chat bubbles dynamically loaded here -->
                            </div>
                        </div>
                    </div>

                    

                    <div class="text-center pt-2 w-full z-10">
                        <button type="button" onclick="goSlide(<?= $slide_num + 1 ?>)" class="px-8 py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-xs uppercase tracking-wider shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 transition active:scale-95">
                            Continue →
                        </button>
                    </div>

                    <!-- JSON Data injected for JS script to read -->
                    <script>
                        window.chatMomentsData = window.chatMomentsData || {};
                        window.chatMomentsData[<?= $slide_num ?>] = <?= json_encode($moments) ?>;
                    </script>
                </div>

            <?php endif; ?>

            </div>
        </div>

        <?php endforeach; ?>

        <!-- ═══ FINAL REPLY & VIRAL END SCREEN SLIDE ═══ -->
        <div class="slide-pane" id="slide-<?= $total_slides ?>" data-slide="<?= $total_slides ?>">
            <div class="slide-content-wrapper">
            <div class="flex-grow flex flex-col justify-center">
                <!-- Header -->
                <div class="text-center mb-6">
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em] <?= $theme['text_muted'] ?>">Send a message back</span>
                    <h2 class="text-2xl font-bold <?= $theme['text_primary'] ?> mt-1 font-heading">Reply to <?= h($page['sender_name']) ?> ❤️</h2>
                </div>

                <!-- Form container -->
                <div id="reply-form-container" class="<?= $is_dark ? 'glass-dark' : 'glass' ?> rounded-3xl p-5 sm:p-6 shadow-sm space-y-4">
                    <form id="reply-form" onsubmit="submitReplyForm(event)" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                        
                        <!-- Sender Name -->
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider <?= $theme['text_muted'] ?> mb-2">Your Name (Optional)</label>
                            <input type="text" name="sender_name" placeholder="Anonymous" class="w-full px-4 py-2.5 rounded-xl border text-sm outline-none <?= $is_dark ? 'bg-white/5 border-white/10 text-white focus:border-pink-500' : 'bg-white/60 border-gray-200 text-gray-800 focus:border-purple-500' ?>">
                        </div>

                        <!-- Reply Type Buttons -->
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider <?= $theme['text_muted'] ?> mb-2">Reply Format</label>
                            <div class="grid grid-cols-4 gap-2">
                                <button type="button" onclick="setReplyType('text')" id="btn-reply-text" class="py-2.5 rounded-xl text-xs font-bold transition border flex flex-col items-center justify-center gap-1 bg-pink-500/10 border-pink-500/30 text-pink-400">
                                    <span class="text-base">📝</span> Text
                                </button>
                                <button type="button" onclick="setReplyType('voice')" id="btn-reply-voice" class="py-2.5 rounded-xl text-xs font-bold transition border flex flex-col items-center justify-center gap-1 border-transparent <?= $is_dark ? 'bg-white/5 text-slate-400 hover:bg-white/10' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                                    <span class="text-base">🎤</span> Voice
                                </button>
                                <button type="button" onclick="setReplyType('image')" id="btn-reply-image" class="py-2.5 rounded-xl text-xs font-bold transition border flex flex-col items-center justify-center gap-1 border-transparent <?= $is_dark ? 'bg-white/5 text-slate-400 hover:bg-white/10' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                                    <span class="text-base">🖼️</span> Image
                                </button>
                                <button type="button" onclick="setReplyType('video')" id="btn-reply-video" class="py-2.5 rounded-xl text-xs font-bold transition border flex flex-col items-center justify-center gap-1 border-transparent <?= $is_dark ? 'bg-white/5 text-slate-400 hover:bg-white/10' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                                    <span class="text-base">📹</span> Video
                                </button>
                            </div>
                            <input type="hidden" name="reply_type" id="reply-type-input" value="text">
                        </div>

                        <!-- Text message field -->
                        <div id="field-reply-text" class="space-y-2">
                            <label class="block text-[10px] font-bold uppercase tracking-wider <?= $theme['text_muted'] ?> mb-1">Your Message</label>
                            <textarea name="reply_text" rows="3" maxlength="500" placeholder="Write your heartfelt response..." class="w-full px-4 py-2.5 rounded-xl border text-sm outline-none resize-none <?= $is_dark ? 'bg-white/5 border-white/10 text-white focus:border-pink-500' : 'bg-white/60 border-gray-200 text-gray-800 focus:border-purple-500' ?>"></textarea>
                        </div>

                        <!-- File upload fields (hidden by default) -->
                        <div id="field-reply-file" class="hidden space-y-2">
                            <label class="block text-[10px] font-bold uppercase tracking-wider <?= $theme['text_muted'] ?> mb-1" id="file-label-text">Select File</label>
                            <div class="relative w-full">
                                <input type="file" name="reply_file" id="reply-file-input" class="hidden" onchange="fileSelected(this)">
                                <button type="button" onclick="document.getElementById('reply-file-input').click()" class="w-full py-4 border-2 border-dashed rounded-2xl flex flex-col items-center justify-center gap-1 transition <?= $is_dark ? 'border-white/10 bg-white/5 hover:bg-white/10 text-slate-300' : 'border-gray-300 bg-slate-50 hover:bg-slate-100 text-slate-600' ?>">
                                    <span class="text-2xl" id="file-upload-icon">📂</span>
                                    <span class="text-xs font-semibold" id="file-upload-text">Choose File (Max <span id="file-max-size">5MB</span>)</span>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="submit-reply-btn" class="w-full py-3.5 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-2xl text-sm transition shadow-lg <?= $theme['btn_shadow'] ?> hover:opacity-95 active:scale-95">
                            Submit Reply 🚀
                        </button>
                    </form>
                </div>

                <!-- Success Screen (hidden by default) -->
                <div id="reply-success-container" class="hidden flex-col items-center text-center space-y-6 animate-fadeIn py-8">
                    <div class="text-6xl animate-bounce">💌</div>
                    <h2 class="text-2xl font-extrabold <?= $theme['text_primary'] ?> font-heading font-bold">Reply Sent!</h2>
                    <p class="text-sm <?= $theme['text_muted'] ?> max-w-xs">Your reply has been delivered to <?= h($page['sender_name']) ?>'s dashboard.</p>
                </div>

                <!-- Viral End Screen -->
                <div id="viral-end-screen" class="mt-6 pt-6 border-t <?= $is_dark ? 'border-white/5' : 'border-black/5' ?> text-center space-y-4">
                    <div class="space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-widest <?= $theme['text_muted'] ?>">Inspired? Make one yourself</p>
                        <h3 class="text-lg font-extrabold font-heading text-white">Create a Page for Your Loved One</h3>
                    </div>

                    <a href="<?= $base_url ?>index.php" class="inline-flex items-center justify-center px-6 py-3.5 bg-gradient-to-r from-pink-500 via-purple-500 to-blue-500 text-white font-bold rounded-2xl transition shadow-xl shadow-purple-500/20 text-center text-xs w-full">
                        Create Your Page ❤️
                    </a>

                    <!-- Share details -->
                    <div class="flex items-center justify-center gap-2">
                        <a href="https://wa.me/?text=<?= urlencode("Create beautiful emotional story pages on SoulSync! Try it here: " . SITE_URL . "/index.php" . (!empty($creator_referral_code) ? "?ref=" . $creator_referral_code : "")) ?>" target="_blank" class="px-3.5 py-2 bg-green-500/10 hover:bg-green-500/20 border border-green-500/20 text-green-400 font-bold rounded-xl text-[10px] flex items-center gap-1.5 transition">
                            💚 WhatsApp
                        </a>
                        <button onclick="copyStoryLink()" class="px-3.5 py-2 bg-slate-900 border border-slate-800 text-slate-300 font-bold rounded-xl text-[10px] flex items-center gap-1.5 hover:bg-slate-800 transition">
                            📋 Copy Link
                        </button>
                    </div>
                </div>


                <!-- Replies List Section -->
                <?php
                // Get replies count
                $repl_stmt = $pdo->prepare("SELECT COUNT(*) FROM page_replies WHERE page_id = ?");
                $repl_stmt->execute([$page['id']]);
                $replies_count = (int)$repl_stmt->fetchColumn();
                ?>
                <div id="replies-section" class="mt-8 pt-6 border-t <?= $is_dark ? 'border-white/5' : 'border-black/5' ?> space-y-4 text-left">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-extrabold font-heading text-white">Visitor Replies (<span id="replies-count"><?= $replies_count ?></span>)</h3>
                    </div>
                    
                    <div id="replies-list-wrapper" class="relative min-h-[120px] rounded-2xl overflow-hidden">
                        <?php if (!$is_owner): ?>
                            <!-- Blurred mock replies -->
                            <div class="space-y-3 filter blur-sm select-none pointer-events-none opacity-40">
                                <div class="glass-dark rounded-xl p-3 flex items-start gap-3 bg-white/5 border border-white/5">
                                    <span class="text-sm">📝</span>
                                    <div class="flex-grow">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-semibold text-slate-400">Someone special</span>
                                            <span class="text-[10px] text-slate-600">Yesterday</span>
                                        </div>
                                        <p class="text-xs text-slate-350">This is a secret response that you cannot read...</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Login gate overlay -->
                            <div class="absolute inset-0 bg-slate-950/60 flex flex-col items-center justify-center text-center p-4 z-10">
                                <span class="text-2xl mb-2">🔒</span>
                                <p class="text-xs text-slate-300 font-semibold mb-3">Owner authorization required to view replies</p>
                                <a href="login.php?redirect=p.php?s=<?= h($page['slug']) ?>" class="px-4 py-2 bg-gradient-to-r <?= $theme['btn_gradient'] ?> text-white font-bold rounded-xl text-[10px] shadow-lg transition-all hover:scale-105">Login as Owner</a>
                            </div>
                        <?php else: ?>
                            <!-- Actual replies container (loaded via AJAX) -->
                            <div id="replies-list-container" class="space-y-3 max-h-80 overflow-y-auto pr-1">
                                <p class="text-xs text-slate-500 py-4 text-center animate-pulse">Loading replies...</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            </div>
        </div>

            <?php render_ad_banner('adsense_slot_published_bottom'); ?>

            <!-- Shared Watermark Footer -->
            <div class="text-center text-[10px] <?= $theme['text_muted'] ?> font-semibold tracking-wider py-4 relative z-10 border-t <?= $is_dark ? 'border-white/5' : 'border-black/5' ?>">
                Created with SoulSync
            </div>
        </div>

    </div>

    <!-- ════════════ DESKTOP SIDE PANEL (visible only on 768px+) ════════════ -->
    <div id="desktop-side-panel">
        <!-- Page Title Card -->
        <div class="side-card">
            <p class="text-[10px] font-bold uppercase tracking-widest text-pink-400 mb-1">✨ A Special Page</p>
            <h2 class="text-base font-extrabold text-white leading-tight mb-1 font-heading"><?= h($page['title']) ?></h2>
            <p class="text-[11px] text-slate-400">From <span class="text-pink-300 font-semibold"><?= h($page['sender_name']) ?></span> to <span class="text-white font-semibold"><?= h($page['receiver_name']) ?></span></p>
        </div>

        <!-- Share on Phone Card -->
        <div class="side-card text-center">
            <p class="text-[11px] text-slate-400 font-semibold mb-3">📱 Best viewed on mobile</p>
            <div class="w-32 h-32 mx-auto mb-3 bg-white rounded-xl flex items-center justify-center overflow-hidden">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=128x128&data=<?= urlencode(SITE_URL . '/p.php?s=' . h($page['slug'])) ?>"
                     alt="Scan to open on phone"
                     class="w-full h-full object-contain p-2"
                     onerror="this.parentNode.innerHTML='<span style=\'font-size:10px;color:#64748b\'>Scan QR unavailable</span>'"
                >
            </div>
            <p class="text-[10px] text-slate-500">Scan to open on your phone</p>
        </div>

        <!-- Share Buttons -->
        <div class="side-card">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Share this page</p>
            <div class="flex flex-col gap-2">
                <a href="https://wa.me/?text=<?= urlencode(SITE_URL . '/p.php?s=' . $page['slug']) ?>" target="_blank"
                   class="flex items-center gap-2 px-4 py-2.5 bg-green-500/10 hover:bg-green-500/20 border border-green-500/20 text-green-400 font-bold rounded-xl text-xs transition">
                    💬 Share via WhatsApp
                </a>
                <button onclick="copyStoryLink()"
                        class="flex items-center gap-2 px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 text-slate-300 font-bold rounded-xl text-xs transition">
                    📋 Copy Link
                </button>
            </div>
        </div>

        <!-- Create Your Own -->
        <div class="side-card text-center">
            <p class="text-[10px] text-slate-400 font-semibold mb-2">Inspired? Make your own!</p>
            <a href="<?= $base_url ?>index.php"
               class="inline-flex items-center justify-center px-5 py-2.5 bg-gradient-to-r from-pink-500 via-purple-500 to-blue-500 text-white font-bold rounded-xl text-xs shadow-lg w-full transition hover:opacity-90">
                Create Your Page ❤️
            </a>
        </div>
    </div>


    <!-- Lightbox Modal for Photo Preview -->
    <div id="photo-modal" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4 hidden" onclick="closePhotoModal()">
        <button type="button" class="absolute top-6 right-6 text-white text-3xl font-bold cursor-pointer" onclick="closePhotoModal()">&times;</button>
        <img id="photo-modal-img" src="" class="max-w-full max-h-[85vh] rounded-xl shadow-2xl object-contain" onclick="event.stopPropagation()">
    </div>

    <script>
        // ═══════════════════════════════════════════════════
        // STORY ENGINE JAVASCRIPT
        // ═══════════════════════════════════════════════════
        const pageId = <?= (int)($page['id'] ?? 0) ?>;
        let visitorName = 'Anonymous';
        function saveVisitorName(slideNum) {
            const input = document.getElementById('visitor-name-field');
            if (input && input.value.trim()) {
                visitorName = input.value.trim();
            }
            goSlide(slideNum + 1);
        }
        const totalSlides = <?= (int)$total_slides ?>;
        const totalImages = <?= count($images) ?>;
        const letterText = <?= json_encode($page['letter_text'] ?? '') ?>;
        const relationshipDate = <?= json_encode($page['relationship_date'] ?? '') ?>;
        const themeParticles = <?= json_encode(!empty($theme['particles']) ? $theme['particles'] : ['✨']) ?>;
        const accentColor = '<?= h($theme['accent'] ?? '#ec4899') ?>';
        const isDark = <?= $is_dark ? 'true' : 'false' ?>;
        const photoFitMode = '<?= h($page['photo_fit_mode'] ?? 'cover') ?>';
        const welcomeTitle = <?= json_encode($slides[0]['title'] ?? '') ?>;
        const receiverName = <?= json_encode($page['receiver_name'] ?? '') ?>;

        const baseUrl = '<?= $base_url ?>';
        const pageImages = <?= json_encode($images) ?>;
        
        // Build music tracks list from database
        const rawTracks = <?= json_encode(array_map(function($t) {
            return [
                'title' => $t['title'],
                'file_path' => $t['file_path']
            ];
        }, $music_list)) ?>;
        
        const currentTrackPath = <?= json_encode(!empty($page['music_url']) ? $page['music_url'] : '') ?>;
        const currentTrackTitle = <?= json_encode(!empty($page['music_url']) ? 'Original Surprise Music' : '') ?>;
        
        let trackList = [];
        if (currentTrackPath) {
            trackList.push({ title: currentTrackTitle, file_path: baseUrl + currentTrackPath });
        }
        rawTracks.forEach(t => {
            const fullPath = baseUrl + t.file_path;
            if (fullPath !== (baseUrl + currentTrackPath)) {
                trackList.push({ title: t.title, file_path: fullPath });
            }
        });
        if (trackList.length === 0) {
            trackList.push({ title: 'Default Theme', file_path: baseUrl + 'assets/music/default.mp3' });
        }

        let currentSlide = 1;
        let musicPlaying = false;
        let galleryIndexes = {};
        let typewriterRunning = {};
        let isTransitioning = false;
        let activeTrackIdx = 0;

        // Performance Mode Slide Timers Registry
        const slideIntervals = {};
        const slideTimeouts = {};

        function setSlideInterval(slideNum, fn, delay) {
            const id = setInterval(fn, delay);
            if (!slideIntervals[slideNum]) slideIntervals[slideNum] = [];
            slideIntervals[slideNum].push(id);
            return id;
        }

        function setSlideTimeout(slideNum, fn, delay) {
            const id = setTimeout(fn, delay);
            if (!slideTimeouts[slideNum]) slideTimeouts[slideNum] = [];
            slideTimeouts[slideNum].push(id);
            return id;
        }

        function clearSlideTimers(slideNum) {
            if (slideIntervals[slideNum]) {
                slideIntervals[slideNum].forEach(clearInterval);
                delete slideIntervals[slideNum];
            }
            if (slideTimeouts[slideNum]) {
                slideTimeouts[slideNum].forEach(clearTimeout);
                delete slideTimeouts[slideNum];
            }
        }

        function startPreloaderFlow() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    const el = entry.target;
                    if (entry.isIntersecting) {
                        el.style.animationPlayState = 'running';
                        el.querySelectorAll('*').forEach(child => {
                            child.style.animationPlayState = 'running';
                        });
                    } else {
                        el.style.animationPlayState = 'paused';
                        el.querySelectorAll('*').forEach(child => {
                            child.style.animationPlayState = 'paused';
                        });
                    }
                });
            }, { threshold: 0.1 });
            document.querySelectorAll('.slide-pane').forEach(pane => observer.observe(pane));
            
            // Load replies if current user is owner
            if (typeof isOwner !== 'undefined' && isOwner) {
                loadPageReplies();
            }

            // Start preloading page assets
            initPreloader();
        }

        if (document.readyState === 'loading') {
            document.addEventListener("DOMContentLoaded", startPreloaderFlow);
        } else {
            startPreloaderFlow();
        }

        function initPreloader() {
            const loadingView = document.getElementById('preloader-loading-view');
            const readyView = document.getElementById('preloader-ready-view');
            const statusText = document.getElementById('preloader-status-text');
            const progressBar = document.getElementById('preloader-progress-bar');
            const percentageText = document.getElementById('preloader-percentage');

            if (!loadingView || !readyView) return;

            const normalizeUrl = (path) => {
                if (!path) return '';
                try {
                    return new URL(path, window.location.href).href;
                } catch (e) {
                    return path;
                }
            };

            // Collect all URLs to preload
            const imagesToLoad = [];
            if (typeof pageImages !== 'undefined' && Array.isArray(pageImages)) {
                pageImages.forEach(img => {
                    const path = img.medium_path || img.image_path;
                    if (path) {
                        const absUrl = normalizeUrl(baseUrl + path);
                        if (!imagesToLoad.includes(absUrl)) {
                            imagesToLoad.push(absUrl);
                        }
                    }
                });
            }

            const audioToLoad = [];
            // Preload the primary background music track
            if (typeof trackList !== 'undefined' && trackList.length > 0 && trackList[0].file_path) {
                audioToLoad.push(normalizeUrl(trackList[0].file_path));
            }
            
            // Scan for other voice note audio tags
            document.querySelectorAll('audio[src]').forEach(audio => {
                if (audio.src) {
                    const absUrl = normalizeUrl(audio.src);
                    if (!audioToLoad.includes(absUrl)) {
                        audioToLoad.push(absUrl);
                    }
                }
            });

            const videosToLoad = [];
            document.querySelectorAll('video[src]').forEach(video => {
                if (video.src) {
                    const absUrl = normalizeUrl(video.src);
                    if (!videosToLoad.includes(absUrl)) {
                        videosToLoad.push(absUrl);
                    }
                }
            });

            const totalItems = imagesToLoad.length;
            let loadedItems = 0;
            let isFinished = false;

            if (totalItems === 0) {
                finishPreloading();
                return;
            }

            // Safety timeout (max 15 seconds) so they never get stuck
            const safetyTimeout = setTimeout(() => {
                if (!isFinished) {
                    if (statusText) statusText.textContent = "Loading complete (timeout)...";
                    finishPreloading();
                }
            }, 15000);

            function itemLoaded(url, type) {
                if (isFinished) return;
                loadedItems++;
                const progress = Math.min(100, Math.round((loadedItems / totalItems) * 100));
                
                if (progressBar) progressBar.style.width = progress + '%';
                if (percentageText) percentageText.textContent = progress + '%';
                
                if (statusText) {
                    statusText.textContent = `Loading ${type} (${loadedItems}/${totalItems})...`;
                }

                if (loadedItems >= totalItems) {
                    clearTimeout(safetyTimeout);
                    finishPreloading();
                }
            }

            function finishPreloading() {
                isFinished = true;
                if (progressBar) progressBar.style.width = '100%';
                if (percentageText) percentageText.textContent = '100%';
                if (statusText) statusText.textContent = 'Ready! ✨';

                setTimeout(() => {
                    // Fade out loading view and fade in cover card view
                    if (loadingView) {
                        loadingView.style.transition = 'opacity 0.4s ease-out';
                        loadingView.style.opacity = '0';
                        setTimeout(() => {
                            loadingView.classList.add('hidden');
                            
                            readyView.classList.remove('hidden');
                            setTimeout(() => {
                                readyView.classList.remove('scale-95', 'opacity-0');
                                readyView.classList.add('scale-100', 'opacity-100');
                            }, 50);
                        }, 400);
                    }
                }, 400);
            }

            // Start preloading images
            imagesToLoad.forEach(url => {
                const img = new Image();
                img.onload = () => itemLoaded(url, 'images');
                img.onerror = () => itemLoaded(url, 'images');
                img.src = url;
            });

            // Start preloading audio (non-blocking)
            audioToLoad.forEach(url => {
                try {
                    const audio = new Audio();
                    audio.src = url;
                    audio.preload = 'auto';
                    audio.load();
                } catch (e) {
                    console.warn("Background audio preload failed", e);
                }
            });

            // Start preloading videos (non-blocking)
            videosToLoad.forEach(url => {
                try {
                    const video = document.createElement('video');
                    video.src = url;
                    video.preload = 'auto';
                    video.muted = true;
                    video.playsInline = true;
                    video.load();
                } catch (e) {
                    console.warn("Background video preload failed", e);
                }
            });
        }

        // Mana Lo Yaar global state variables
        let manaLoIntervals = [];
        let manaLoSirenInterval = null;
        let manaLoRescueClicked = {};
        let manaLoMissFlipped = {};
        let manaLoBribesOpened = {};
        let manaLoVoiceInterval = null;
        let voiceEmojiIntervals = {};

        // Create Responsive Image helper
        function createResponsiveImage(imgObj, className) {
            const el = document.createElement('img');
            el.className = className + ' live-photo-entrance';
            
            const original = baseUrl + imgObj.image_path;
            const medium = imgObj.medium_path ? (baseUrl + imgObj.medium_path) : original;
            const thumb = imgObj.thumb_path ? (baseUrl + imgObj.thumb_path) : original;
            
            el.src = medium; // default to medium for optimal loading
            
            if (imgObj.medium_path && imgObj.thumb_path) {
                el.setAttribute('srcset', `${thumb} 150w, ${medium} 600w`);
                el.setAttribute('sizes', '(max-width: 480px) 100vw, 480px');
            }
            
            el.setAttribute('loading', 'lazy');
            return el;
        }

        // Lightbox helpers
        function openPhotoModal(url) {
            const modal = document.getElementById('photo-modal');
            const img = document.getElementById('photo-modal-img');
            if (modal && img) {
                img.src = url;
                modal.classList.remove('hidden');
            }
        }
        function closePhotoModal() {
            const modal = document.getElementById('photo-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function heartPosition(t, scale, cx, cy) {
            // Parametric equation of a heart
            const x = 16 * Math.pow(Math.sin(t), 3);
            const y = 13 * Math.cos(t) - 5 * Math.cos(2 * t) - 2 * Math.cos(3 * t) - Math.cos(4 * t);
            return {
                x: cx + x * scale,
                y: cy - y * scale // Subtract y because y-axis increases downwards
            };
        }

        // Particle / Confetti Helpers
        function triggerConfettiBurst() {
            if (typeof confetti === 'function') {
                confetti({ particleCount: 80, spread: 70, origin: { y: 0.6 } });
            }
        }
        function triggerHeartsOverlay(container) {
            for (let i = 0; i < 8; i++) {
                const heart = document.createElement('div');
                heart.textContent = '❤️';
                heart.className = 'absolute text-xs pointer-events-none opacity-0 z-20';
                heart.style.left = `${10 + Math.random() * 80}%`;
                heart.style.top = `${80 + Math.random() * 20}%`;
                heart.style.animation = `heartRise ${3 + Math.random() * 2}s ease-in-out infinite`;
                heart.style.animationDelay = `${Math.random() * 1.5}s`;
                heart.style.setProperty('--drift', `${(Math.random() - 0.5) * 40}px`);
                container.appendChild(heart);
            }
        }

        // ═══ CUSTOM MEDIA PLAYERS JAVASCRIPT ═══
        let voiceAnimationFrames = {};
        let musicPausedByVoice = false;
        let musicPausedByVideo = false;

        let voiceSlideshowIntervals = {};
        function startVoiceNoteSlideshow(playerId, slideNum) {
            const bgContainer = document.getElementById(`voice-bg-slideshow-${slideNum}`);
            if (!bgContainer) return;
            bgContainer.style.opacity = '1';
            
            const slides = bgContainer.querySelectorAll('.voice-slide-img');
            if (slides.length === 0) return;
            
            let currentIdx = 0;
            slides.forEach(s => s.style.opacity = '0');
            slides[currentIdx].style.opacity = '1';
            
            if (voiceSlideshowIntervals[playerId]) clearInterval(voiceSlideshowIntervals[playerId]);
            
            voiceSlideshowIntervals[playerId] = setInterval(() => {
                slides[currentIdx].style.opacity = '0';
                currentIdx = (currentIdx + 1) % slides.length;
                slides[currentIdx].style.opacity = '1';
            }, 4000);
        }

        function stopVoiceNoteSlideshow(playerId, slideNum) {
            if (voiceSlideshowIntervals[playerId]) {
                clearInterval(voiceSlideshowIntervals[playerId]);
                delete voiceSlideshowIntervals[playerId];
            }
            const bgContainer = document.getElementById(`voice-bg-slideshow-${slideNum}`);
            if (bgContainer) {
                bgContainer.style.opacity = '0';
                const slides = bgContainer.querySelectorAll('.voice-slide-img');
                slides.forEach(s => s.style.opacity = '0');
            }
        }

        function formatTimeGlobal(secs) {
            if (isNaN(secs)) return '0:00';
            const m = Math.floor(secs / 60);
            const s = Math.floor(secs % 60);
            return `${m}:${s < 10 ? '0' : ''}${s}`;
        }

        function updateCustomAudioUI(playerId, audio) {
            if (!audio) return;
            const currentTime = audio.mockCurrentTime !== undefined ? audio.mockCurrentTime : (audio.currentTime || 0);
            const duration = audio.mockDuration !== undefined ? audio.mockDuration : (audio.duration || 0);
            const pct = duration ? (currentTime / duration) : 0;
            
            const currentTimeText = document.getElementById(`custom-audio-current-${playerId}`);
            if (currentTimeText) {
                currentTimeText.textContent = formatTimeGlobal(currentTime);
            }
            
            const progressBar = document.getElementById(`custom-audio-progress-${playerId}`);
            if (progressBar) {
                progressBar.style.width = `${pct * 100}%`;
            }
            
            const wave = document.getElementById(`custom-audio-wave-${playerId}`);
            if (wave && !wave.id.includes('letter')) {
                const bars = wave.querySelectorAll('div');
                const activeBars = Math.floor(pct * bars.length);
                bars.forEach((bar, idx) => {
                    if (idx <= activeBars) {
                        bar.style.backgroundColor = accentColor;
                    } else {
                        bar.style.backgroundColor = '';
                    }
                });
            }
        }

        const audioContexts = {};
        const audioAnalysers = {};
        const audioSources = {};

        function initAudioAnalyser(playerId) {
            if (audioContexts[playerId]) return;
            const audio = document.getElementById(`custom-audio-element-${playerId}`);
            if (!audio) return;
            
            try {
                const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                const audioCtx = new AudioCtxClass();
                const analyser = audioCtx.createAnalyser();
                analyser.fftSize = 64; // 32 frequency bins
                
                const source = audioCtx.createMediaElementSource(audio);
                source.connect(analyser);
                analyser.connect(audioCtx.destination);
                
                audioContexts[playerId] = audioCtx;
                audioAnalysers[playerId] = analyser;
                audioSources[playerId] = source;
            } catch (e) {
                console.warn("Failed to initialize audio analyser", e);
            }
        }

        function toggleCustomAudio(playerId) {
            const player = document.getElementById(`custom-audio-element-${playerId}`);
            const btn = document.getElementById(`custom-audio-play-${playerId}`);
            const wave = document.getElementById(`custom-audio-wave-${playerId}`);
            const slideNum = wave ? wave.dataset.slideNum : null;
            const particleContainer = document.getElementById('particle-container');
            
            if (!player) {
                if (window.customAudioFallbackInterval) {
                    clearInterval(window.customAudioFallbackInterval);
                    window.customAudioFallbackInterval = null;
                    stopSynthesizedMelody(slideNum);
                    if (btn) btn.textContent = '▶';
                    if (wave) {
                        wave.classList.add('opacity-50');
                        if (wave.id.includes('letter')) {
                            stopVisualWave(wave);
                        } else {
                            stopVoiceWaveAnim(playerId);
                        }
                    }
                    if (particleContainer) {
                        particleContainer.classList.remove('slow-particles');
                    }
                    if (slideNum) {
                        stopVoiceNoteSlideshow(playerId, slideNum);
                    }
                    unduckBackgroundMusic();
                    return;
                }

                duckBackgroundMusic();
                
                if (btn) btn.textContent = '⏸';
                const durationText = document.getElementById(`custom-audio-duration-${playerId}`);
                const currentText = document.getElementById(`custom-audio-current-${playerId}`);
                if (durationText) durationText.textContent = '0:05';
                
                if (wave) {
                    wave.classList.remove('opacity-50');
                    if (wave.id.includes('letter')) {
                        startVisualWave(wave);
                    } else {
                        startVoiceWaveAnim(playerId);
                    }
                }
                if (particleContainer) {
                    particleContainer.classList.add('slow-particles');
                }
                if (slideNum) {
                    startVoiceNoteSlideshow(playerId, slideNum);
                }
                
                playSynthesizedMelody(slideNum);
                
                let mockCurrentTime = 0;
                const mockDuration = 5;
                window.customAudioFallbackInterval = setInterval(() => {
                    mockCurrentTime += 0.1;
                    if (currentText) {
                        const mins = Math.floor(mockCurrentTime / 60);
                        const secs = Math.floor(mockCurrentTime % 60).toString().padStart(2, '0');
                        currentText.textContent = `${mins}:${secs}`;
                    }
                    const progressEl = document.getElementById(`custom-audio-progress-${playerId}`);
                    if (progressEl) {
                        progressEl.style.width = `${(mockCurrentTime / mockDuration) * 100}%`;
                    }
                    
                    if (mockCurrentTime >= mockDuration) {
                        clearInterval(window.customAudioFallbackInterval);
                        window.customAudioFallbackInterval = null;
                        stopSynthesizedMelody(slideNum);
                        if (btn) btn.textContent = '▶';
                        if (wave) {
                            wave.classList.add('opacity-50');
                            if (wave.id.includes('letter')) {
                                stopVisualWave(wave);
                            } else {
                                stopVoiceWaveAnim(playerId);
                            }
                        }
                        if (particleContainer) {
                            particleContainer.classList.remove('slow-particles');
                        }
                        if (slideNum) {
                            stopVoiceNoteSlideshow(playerId, slideNum);
                        }
                        if (currentText) currentText.textContent = '0:00';
                        if (progressEl) progressEl.style.width = '0%';
                        unduckBackgroundMusic();
                    }
                }, 100);
                return;
            }
            
            if (player.isFallback) {
                player.isFallback = false;
                if (player.fallbackInterval) {
                    clearInterval(player.fallbackInterval);
                    player.fallbackInterval = null;
                }
                stopSynthesizedMelody(slideNum);
                btn.textContent = '▶';
                if (wave) {
                    wave.classList.add('opacity-50');
                    if (wave.id.includes('letter')) {
                        stopVisualWave(wave);
                    } else {
                        stopVoiceWaveAnim(playerId);
                    }
                }
                if (particleContainer) {
                    particleContainer.classList.remove('slow-particles');
                }
                if (slideNum) {
                    stopVoiceNoteSlideshow(playerId, slideNum);
                }
                unduckBackgroundMusic();
                return;
            }

            document.querySelectorAll('audio, video').forEach(el => {
                if (el.id !== `custom-audio-element-${playerId}` && el.id !== 'story-audio') {
                    el.pause();
                    if (el.isFallback) {
                        el.isFallback = false;
                        if (el.fallbackInterval) {
                            clearInterval(el.fallbackInterval);
                            el.fallbackInterval = null;
                        }
                        const elId = el.id.replace('custom-audio-element-', '');
                        const otherBtn = document.getElementById(`custom-audio-play-${elId}`);
                        if (otherBtn) otherBtn.textContent = '▶';
                        const otherWave = document.getElementById(`custom-audio-wave-${elId}`);
                        if (otherWave) {
                            otherWave.classList.add('opacity-50');
                            if (otherWave.id.includes('letter')) {
                                stopVisualWave(otherWave);
                            } else {
                                stopVoiceWaveAnim(elId);
                            }
                        }
                    }
                }
            });
            document.querySelectorAll('[id^="custom-audio-play-"]').forEach(b => {
                if (b.id !== `custom-audio-play-${playerId}`) b.textContent = '▶';
            });
            document.querySelectorAll('[id^="custom-audio-wave-"]').forEach(w => {
                if (w.id !== `custom-audio-wave-${playerId}`) {
                    w.classList.add('opacity-50');
                    stopVisualWave(w);
                    const sNum = w.dataset.slideNum;
                    const pId = w.id.replace('custom-audio-wave-', '');
                    if (sNum) stopVoiceNoteSlideshow(pId, sNum);
                }
            });
            document.querySelectorAll('[id^="voice-play-btn-"]').forEach(b => b.textContent = '▶️');

            const storyAudio = document.getElementById('story-audio');
            if (player.paused) {
                // Duck background music
                duckBackgroundMusic();
                
                // Always load to ensure proper state, especially on iOS
                if (player.readyState < 3) {
                    player.load();
                }
                
                initAudioAnalyser(playerId);
                if (audioContexts[playerId] && audioContexts[playerId].state === 'suspended') {
                    audioContexts[playerId].resume();
                }
                
                player.play().then(() => {
                    btn.textContent = '⏸';
                    btn.classList.remove('animate-heartbeat');
                    if (wave) {
                        wave.classList.remove('opacity-50');
                        if (wave.id.includes('letter')) {
                            startVisualWave(wave);
                        } else {
                            startVoiceWaveAnim(playerId);
                        }
                    }
                    if (particleContainer) {
                        particleContainer.classList.add('slow-particles');
                    }
                    if (slideNum) {
                        startVoiceNoteSlideshow(playerId, slideNum);
                    }
                }).catch(err => {
                    // If this is a broken .aac file, repair it (aac-play.js) and retry
                    // the real audio instead of jumping to the 5-second melody.
                    const psrc = player.currentSrc || player.src || '';
                    if (window.aacRepair && /\.(aac|acc)(\?.*)?$/i.test(psrc) && !player.dataset.aacRetried) {
                        player.dataset.aacRetried = '1';
                        btn.textContent = '⏳';
                        window.aacRepair(player).then(ok => {
                            btn.textContent = '▶';
                            if (ok) btn.click(); // retry with the repaired source
                        });
                        return;
                    }
                    console.log("Audio play failed, playing synthesized melody fallback: ", err);

                    player.isFallback = true;
                    player.mockCurrentTime = 0;
                    player.mockDuration = 5; // 5-second fallback
                    
                    btn.textContent = '⏸';
                    btn.classList.remove('animate-heartbeat');
                    
                    const durationText = document.getElementById(`custom-audio-duration-${playerId}`);
                    if (durationText) {
                        durationText.textContent = '0:05';
                    }
                    
                    if (wave) {
                        wave.classList.remove('opacity-50');
                        if (wave.id.includes('letter')) {
                            startVisualWave(wave);
                        } else {
                            startVoiceWaveAnim(playerId);
                        }
                    }
                    if (particleContainer) {
                        particleContainer.classList.add('slow-particles');
                    }
                    if (slideNum) {
                        startVoiceNoteSlideshow(playerId, slideNum);
                    }
                    
                    playSynthesizedMelody(slideNum);
                    
                    player.fallbackInterval = setInterval(() => {
                        player.mockCurrentTime += 0.1;
                        if (player.mockCurrentTime >= player.mockDuration) {
                            player.isFallback = false;
                            clearInterval(player.fallbackInterval);
                            player.fallbackInterval = null;
                            stopSynthesizedMelody(slideNum);
                            btn.textContent = '▶';
                            if (wave) {
                                wave.classList.add('opacity-50');
                                if (wave.id.includes('letter')) {
                                    stopVisualWave(wave);
                                } else {
                                    stopVoiceWaveAnim(playerId);
                                }
                            }
                            if (particleContainer) {
                                particleContainer.classList.remove('slow-particles');
                            }
                            if (slideNum) {
                                stopVoiceNoteSlideshow(playerId, slideNum);
                            }
                            unduckBackgroundMusic();
                        } else {
                            updateCustomAudioUI(playerId, player);
                        }
                    }, 100);
                });
            } else {
                player.pause();
                btn.textContent = '▶';
                if (wave) {
                    wave.classList.add('opacity-50');
                    if (wave.id.includes('letter')) {
                        stopVisualWave(wave);
                    } else {
                        stopVoiceWaveAnim(playerId);
                    }
                }
                if (particleContainer) {
                    particleContainer.classList.remove('slow-particles');
                }
                if (slideNum) {
                    stopVoiceNoteSlideshow(playerId, slideNum);
                }
                
                // Unduck background music
                unduckBackgroundMusic();
            }
        }

        function startVisualWave(waveEl) {
            const playerId = waveEl.id.replace('custom-audio-wave-', '');
            startVoiceWaveAnim(playerId);
        }

        function stopVisualWave(waveEl) {
            const playerId = waveEl.id.replace('custom-audio-wave-', '');
            stopVoiceWaveAnim(playerId);
        }

        // Voice Message Waveform Animations (60fps requestAnimationFrame)
        const voiceHeights = [30, 45, 60, 25, 40, 75, 50, 60, 35, 55, 80, 45, 30, 50, 70, 60, 40, 25, 45, 60, 75, 50, 35, 60, 80, 65, 45, 30, 55, 70, 50, 35];
        
        function startVoiceWaveAnim(playerId) {
            const audio = document.getElementById(`custom-audio-element-${playerId}`);
            const wave = document.getElementById(`custom-audio-wave-${playerId}`);
            if (!audio || !wave) return;
            
            const ring = document.getElementById(`voice-glow-ring-${playerId}`);
            const analyser = audioAnalysers[playerId];
            const bufferLength = analyser ? analyser.frequencyBinCount : 32;
            const dataArray = analyser ? new Uint8Array(bufferLength) : null;
            
            if (voiceAnimationFrames[playerId]) cancelAnimationFrame(voiceAnimationFrames[playerId]);
            
            function frame() {
                if (audio.paused && !audio.isFallback) {
                    if (ring) {
                        ring.style.transform = 'scale(1)';
                        ring.style.opacity = '0.5';
                    }
                    return;
                }
                const currentTime = audio.mockCurrentTime !== undefined ? audio.mockCurrentTime : (audio.currentTime || 0);
                const duration = audio.mockDuration !== undefined ? audio.mockDuration : (audio.duration || 0);
                const pct = duration ? (currentTime / duration) : 0;
                
                const bars = wave.querySelectorAll('div');
                const activeBars = Math.floor(pct * bars.length);
                
                if (analyser && dataArray) {
                    analyser.getByteFrequencyData(dataArray);
                }
                
                // Pulsing background ring
                if (ring) {
                    const pulse = 1 + Math.sin(Date.now() / 150) * 0.08;
                    ring.style.transform = `scale(${pulse})`;
                    ring.style.opacity = `${0.4 + Math.sin(Date.now() / 150) * 0.15}`;
                }
                
                bars.forEach((bar, idx) => {
                    let barHeightPercent = 0;
                    if (dataArray && analyser) {
                        // Read actual frequency amplitude (normalized 0 to 100%)
                        barHeightPercent = (dataArray[idx % bufferLength] / 255) * 100;
                    } else {
                        // Fallback math
                        barHeightPercent = (voiceHeights[idx % voiceHeights.length] / 100) * 50 + Math.sin(Date.now() / 90 + idx) * 15;
                    }
                    
                    // Cap the height between 15% and 95%
                    barHeightPercent = Math.max(15, Math.min(95, barHeightPercent));
                    
                    if (idx <= activeBars) {
                        bar.style.backgroundColor = accentColor;
                        bar.style.boxShadow = `0 0 8px ${accentColor}`;
                        bar.style.height = `${barHeightPercent}%`;
                    } else {
                        bar.style.backgroundColor = '';
                        bar.style.boxShadow = '';
                        bar.style.height = `${barHeightPercent}%`;
                    }
                });
                voiceAnimationFrames[playerId] = requestAnimationFrame(frame);
            }
            
            voiceAnimationFrames[playerId] = requestAnimationFrame(frame);
        }

        function stopVoiceWaveAnim(playerId) {
            if (voiceAnimationFrames[playerId]) {
                cancelAnimationFrame(voiceAnimationFrames[playerId]);
                delete voiceAnimationFrames[playerId];
            }
            const wave = document.getElementById(`custom-audio-wave-${playerId}`);
            if (wave) {
                const bars = wave.querySelectorAll('div');
                bars.forEach((bar, idx) => {
                    bar.style.backgroundColor = '';
                    bar.style.boxShadow = '';
                    bar.style.height = `${voiceHeights[idx]}%`;
                });
            }
            const ring = document.getElementById(`voice-glow-ring-${playerId}`);
            if (ring) {
                ring.style.transform = 'scale(1)';
                ring.style.opacity = '0.5';
            }
        }

        function seekCustomAudio(event, playerId) {
            const player = document.getElementById(`custom-audio-element-${playerId}`);
            const progressBarContainer = event.currentTarget;
            const rect = progressBarContainer.getBoundingClientRect();
            const clickX = event.clientX - rect.left;
            const width = rect.width;
            const percentage = clickX / width;
            player.currentTime = percentage * player.duration;
        }

        function seekCustomAudioByWaveform(event, playerId) {
            const player = document.getElementById(`custom-audio-element-${playerId}`);
            const wave = document.getElementById(`custom-audio-wave-${playerId}`);
            if (!player || !wave) return;
            
            const rect = wave.getBoundingClientRect();
            const clickX = event.clientX - rect.left;
            const width = rect.width;
            const pct = Math.max(0, Math.min(1, clickX / width));
            
            player.currentTime = pct * player.duration;
        }

        function changePlaybackSpeed(playerId) {
            const player = document.getElementById(`custom-audio-element-${playerId}`);
            const btn = document.getElementById(`custom-audio-speed-${playerId}`);
            if (!player || !btn) return;
            
            let speed = player.playbackRate;
            if (speed === 1.0) speed = 1.5;
            else if (speed === 1.5) speed = 2.0;
            else speed = 1.0;
            
            player.playbackRate = speed;
            btn.textContent = `${speed}x`;
        }

        function toggleCustomVideo(playerId) {
            const video = document.getElementById(`custom-video-element-${playerId}`);
            const overlay = document.getElementById(`custom-video-overlay-${playerId}`);
            const overlayBtn = overlay.querySelector('button');
            const playBtn = document.getElementById(`custom-video-play-btn-${playerId}`);
            const container = document.querySelector(`.custom-video-player[data-player-id="${playerId}"]`);
            
            document.querySelectorAll('audio, video').forEach(el => {
                if (el.id !== `custom-video-element-${playerId}` && el.id !== 'story-audio') {
                    el.pause();
                }
            });
            document.querySelectorAll('[id^="custom-audio-play-"]').forEach(b => b.textContent = '▶');
            document.querySelectorAll('[id^="custom-audio-wave-"]').forEach(w => {
                w.classList.add('opacity-50');
                stopVisualWave(w);
            });
            document.querySelectorAll('[id^="custom-video-play-btn-"]').forEach(b => {
                if (b.id !== `custom-video-play-btn-${playerId}`) b.textContent = '▶';
            });
            document.querySelectorAll('[id^="custom-video-overlay-"]').forEach(o => {
                if (o.id !== `custom-video-overlay-${playerId}`) {
                    o.classList.remove('opacity-0');
                    o.querySelector('button').textContent = '▶';
                    const c = o.closest('.custom-video-player');
                    if (c) {
                        c.classList.remove('video-playing');
                        c.classList.add('video-blurred');
                    }
                }
            });

            if (video.paused) {
                // Ensure the player is visible (video-revealed makes opacity: 1)
                if (container && !container.classList.contains('video-revealed')) {
                    container.classList.add('video-revealed');
                }

                // Update volume UI
                const muteBtn = document.getElementById(`custom-video-mute-${playerId}`);
                if (muteBtn) muteBtn.textContent = video.muted ? '🔇' : '🔊';
                const volumeSlider = document.getElementById(`custom-video-volume-${playerId}`);
                if (volumeSlider) volumeSlider.value = video.volume || 1.0;

                // Duck background music instead of pausing to avoid autoplay locks
                const storyAudio = document.getElementById('story-audio');
                if (storyAudio && musicPlaying) {
                    duckBackgroundMusicVideo();
                    musicPausedByVideo = true;
                }



                video.play().then(() => {
                    playBtn.textContent = '⏸';
                    overlayBtn.textContent = '⏸';
                    overlay.classList.add('opacity-0');
                    
                    // Hide the poster cover when the video starts playing
                    const poster = document.getElementById(`custom-video-poster-${playerId}`);
                    if (poster) {
                        poster.classList.add('opacity-0', 'pointer-events-none');
                    }
                    
                    if (container) {
                        container.classList.remove('video-blurred');
                        container.classList.add('video-playing');
                    }
                }).catch(err => {
                    console.log("Video playback error: ", err);
                    // Show user-facing error
                    const errEl = document.getElementById(`video-play-error-${playerId}`);
                    if (errEl) errEl.classList.remove('hidden');
                });
            } else {
                video.pause();
            }
        }

        function seekCustomVideo(event, playerId) {
            const video = document.getElementById(`custom-video-element-${playerId}`);
            const progressBarContainer = event.currentTarget;
            const rect = progressBarContainer.getBoundingClientRect();
            const clickX = event.clientX - rect.left;
            const width = rect.width;
            const percentage = clickX / width;
            video.currentTime = percentage * video.duration;
        }

        function toggleMuteCustomVideo(playerId) {
            const video = document.getElementById(`custom-video-element-${playerId}`);
            const muteBtn = document.getElementById(`custom-video-mute-${playerId}`);
            const volumeSlider = document.getElementById(`custom-video-volume-${playerId}`);
            video.muted = !video.muted;
            muteBtn.textContent = video.muted ? '🔇' : (video.volume < 0.5 ? '🔉' : '🔊');
            if (volumeSlider) {
                volumeSlider.value = video.muted ? 0 : video.volume;
            }
            
            // If the user unmuted the video, pause the background music
            if (!video.muted) {
                const storyAudio = document.getElementById('story-audio');
                if (storyAudio && !storyAudio.paused) {
                    storyAudio.pause();
                    const widgetPlayBtn = document.getElementById('music-widget-play');
                    if (widgetPlayBtn) widgetPlayBtn.textContent = '▶';
                    musicPlaying = false;
                }
            }
        }

        function setVolumeCustomVideo(value, playerId) {
            const video = document.getElementById(`custom-video-element-${playerId}`);
            const muteBtn = document.getElementById(`custom-video-mute-${playerId}`);
            if (!video) return;
            video.volume = value;
            if (parseFloat(value) === 0) {
                video.muted = true;
                if (muteBtn) muteBtn.textContent = '🔇';
            } else {
                video.muted = false;
                if (muteBtn) muteBtn.textContent = parseFloat(value) < 0.5 ? '🔉' : '🔊';
            }
        }

        function cycleSpeedCustomVideo(playerId) {
            const video = document.getElementById(`custom-video-element-${playerId}`);
            const speedBtn = document.getElementById(`custom-video-speed-${playerId}`);
            if (!video || !speedBtn) return;
            let nextSpeed = 1.0;
            if (video.playbackRate === 1.0) nextSpeed = 1.5;
            else if (video.playbackRate === 1.5) nextSpeed = 2.0;
            else if (video.playbackRate === 2.0) nextSpeed = 0.5;
            else nextSpeed = 1.0;
            video.playbackRate = nextSpeed;
            speedBtn.textContent = `${nextSpeed}x`;
        }

        function togglePipCustomVideo(playerId) {
            const video = document.getElementById(`custom-video-element-${playerId}`);
            if (!video) return;
            if (document.pictureInPictureElement) {
                document.exitPictureInPicture().catch(err => console.error(err));
            } else if (video.requestPictureInPicture) {
                video.requestPictureInPicture().catch(err => console.error(err));
            }
        }

        function fullscreenCustomVideo(playerId) {
            const container = document.querySelector(`.custom-video-player[data-player-id="${playerId}"]`);
            if (!container) return;
            if (container.requestFullscreen) {
                container.requestFullscreen();
            } else if (container.webkitRequestFullscreen) {
                container.webkitRequestFullscreen();
            } else if (container.msRequestFullscreen) {
                container.msRequestFullscreen();
            }
        }

        // ═══ FLOATING MUSIC WIDGET CONTROLS ═══
        function expandMusicWidget(e) {
            if (e.target.closest('button') || e.target.closest('input')) return;
            const widget = document.getElementById('music-widget');
            if (widget && !widget.classList.contains('expanded')) {
                widget.classList.add('expanded');
            }
        }
        function collapseMusicWidget(e) {
            if (e) e.stopPropagation();
            const widget = document.getElementById('music-widget');
            if (widget) {
                widget.classList.remove('expanded');
            }
        }
        function toggleMusicFromWidget(e) {
            if (e) e.stopPropagation();
            toggleMusic();
        }
        function toggleMuteFromWidget(e) {
            if (e) e.stopPropagation();
            const audio = document.getElementById('story-audio');
            const btn = document.getElementById('music-widget-mute');
            if (!audio || !btn) return;
            audio.muted = !audio.muted;
            btn.textContent = audio.muted ? '🔇' : '🔊';
        }
        function toggleLoopFromWidget(e) {
            if (e) e.stopPropagation();
            const audio = document.getElementById('story-audio');
            const btn = document.getElementById('music-widget-loop');
            if (!audio || !btn) return;
            audio.loop = !audio.loop;
            if (audio.loop) {
                btn.classList.add('bg-pink-500', 'text-white');
                btn.classList.remove('bg-slate-800', 'text-slate-400');
            } else {
                btn.classList.remove('bg-pink-500', 'text-white');
                btn.classList.add('bg-slate-800', 'text-slate-400');
            }
        }
        function setMusicVolume(e) {
            if (e) e.stopPropagation();
            const audio = document.getElementById('story-audio');
            if (!audio) return;
            const newVol = parseFloat(e.target.value);
            if (typeof isDucked !== 'undefined' && isDucked) {
                originalVolume = newVol;
                audio.volume = 0.15 * newVol;
            } else {
                audio.volume = newVol;
                if (typeof originalVolume !== 'undefined') {
                    originalVolume = newVol;
                }
            }
            audio.muted = false;
            const btn = document.getElementById('music-widget-mute');
            if (btn) btn.textContent = audio.volume === 0 ? '🔇' : '🔊';
        }
        function nextTrack(e) {
            if (e) e.stopPropagation();
            if (trackList.length <= 1) return;
            activeTrackIdx = (activeTrackIdx + 1) % trackList.length;
            loadTrack(activeTrackIdx);
        }
        function prevTrack(e) {
            if (e) e.stopPropagation();
            if (trackList.length <= 1) return;
            activeTrackIdx = (activeTrackIdx - 1 + trackList.length) % trackList.length;
            loadTrack(activeTrackIdx);
        }
        function selectTrackFromDropdown(e) {
            if (e) e.stopPropagation();
            const idx = parseInt(e.target.value);
            if (isNaN(idx) || idx < 0 || idx >= trackList.length) return;
            activeTrackIdx = idx;
            loadTrack(idx);
        }

        function loadTrack(idx) {
            const audio = document.getElementById('story-audio');
            const titleLabel = document.getElementById('music-track-title');
            if (!audio || !trackList[idx]) return;
            
            audio.src = trackList[idx].file_path;
            audio.load();
            if (musicPlaying) {
                audio.play().catch(err => console.log("Track play failed: ", err));
            }
            if (titleLabel) {
                titleLabel.textContent = trackList[idx].title || "Custom Track";
            }
            const widgetPlayBtn = document.getElementById('music-widget-play');
            if (widgetPlayBtn) widgetPlayBtn.textContent = musicPlaying ? '⏸' : '▶';
            
            const trackSelect = document.getElementById('music-track-select');
            if (trackSelect) {
                trackSelect.value = idx;
            }
        }

        // Setup custom media event listeners on DOM load
        window.addEventListener('DOMContentLoaded', () => {
            // Set initial widget title
            const titleLabel = document.getElementById('music-track-title');
            if (titleLabel && trackList[0]) {
                titleLabel.textContent = trackList[0].title || "Default Track";
            }

            // Populate select dropdown
            const trackSelect = document.getElementById('music-track-select');
            if (trackSelect && trackList.length > 0) {
                trackSelect.innerHTML = '';
                trackList.forEach((track, idx) => {
                    const opt = document.createElement('option');
                    opt.value = idx;
                    opt.textContent = track.title || `Track ${idx + 1}`;
                    trackSelect.appendChild(opt);
                });
                trackSelect.value = activeTrackIdx;
            }

            document.querySelectorAll('.custom-audio-player').forEach(playerContainer => {
                const playerId = playerContainer.dataset.playerId;
                const audio = document.getElementById(`custom-audio-element-${playerId}`);
                const wave = document.getElementById(`custom-audio-wave-${playerId}`);
                const currentTimeText = document.getElementById(`custom-audio-current-${playerId}`);
                const durationText = document.getElementById(`custom-audio-duration-${playerId}`);
                const loader = document.getElementById(`voice-loader-${playerId}`);
                const actual = document.getElementById(`voice-actual-${playerId}`);
                const btn = document.getElementById(`custom-audio-play-${playerId}`);

                function formatTime(secs) {
                    if (isNaN(secs)) return '0:00';
                    const m = Math.floor(secs / 60);
                    const s = Math.floor(secs % 60);
                    return `${m}:${s < 10 ? '0' : ''}${s}`;
                }

                // Waveform generation
                if (wave) {
                    wave.innerHTML = '';
                    voiceHeights.forEach((h, idx) => {
                        const bar = document.createElement('div');
                        bar.className = 'w-1 bg-slate-500/30 rounded transition-all duration-150 cursor-pointer';
                        bar.style.height = `${h}%`;
                        bar.dataset.idx = idx;
                        wave.appendChild(bar);
                    });
                }

                if (!audio) return;

                audio.addEventListener('loadedmetadata', () => {
                    if (durationText) durationText.textContent = formatTime(audio.duration);
                    if (loader && actual) {
                        loader.classList.add('hidden');
                        actual.classList.remove('hidden');
                    }
                });

                audio.addEventListener('error', () => {
                    // Broken .aac? repair via aac-play.js instead of showing "Error" —
                    // once the src is swapped, loadedmetadata fires and fills the duration.
                    const esrc = audio.currentSrc || audio.src || audio.getAttribute('src') || '';
                    if (window.aacRepair && /\.(aac|acc)(\?.*)?$/i.test(esrc)) {
                        if (durationText) durationText.textContent = '…';
                        window.aacRepair(audio).then(ok => {
                            if (!ok && durationText) durationText.textContent = 'Error';
                        });
                        return;
                    }
                    console.warn(`Failed to load audio for ${playerId}:`, audio.error);
                    if (durationText) durationText.textContent = "Error";
                    if (loader && actual) {
                        loader.classList.add('hidden');
                        actual.classList.remove('hidden');
                    }
                });

                audio.addEventListener('timeupdate', () => {
                    if (currentTimeText) currentTimeText.textContent = formatTime(audio.currentTime);
                    
                    // Update progress seek bar if it exists
                    const progressBar = document.getElementById(`custom-audio-progress-${playerId}`);
                    if (progressBar && audio.duration) {
                        const pct = (audio.currentTime / audio.duration) * 100;
                        progressBar.style.width = `${pct}%`;
                    }
                    
                    if (wave) {
                        const pct = audio.currentTime / audio.duration;
                        const bars = wave.querySelectorAll('div');
                        const activeBars = Math.floor(pct * bars.length);
                        bars.forEach((bar, idx) => {
                            if (idx <= activeBars) {
                                bar.style.backgroundColor = accentColor;
                            } else {
                                bar.style.backgroundColor = '';
                            }
                        });
                    }
                });

                audio.addEventListener('ended', () => {
                    btn.textContent = '▶';
                    if (wave) {
                        wave.classList.add('opacity-50');
                        if (wave.id.includes('letter')) {
                            stopVisualWave(wave);
                        } else {
                            stopVoiceWaveAnim(playerId);
                        }
                    }
                    
                    const particleContainer = document.getElementById('particle-container');
                    if (particleContainer) {
                        particleContainer.classList.remove('slow-particles');
                    }
                    const slideNum = wave ? wave.dataset.slideNum : null;
                    if (slideNum) {
                        stopVoiceNoteSlideshow(playerId, slideNum);
                    }
                    
                    // Automatically unduck background music if ended
                    unduckBackgroundMusic();
                });

                if (audio.readyState >= 1) {
                    if (durationText) durationText.textContent = formatTime(audio.duration);
                    if (loader && actual) {
                        loader.classList.add('hidden');
                        actual.classList.remove('hidden');
                    }
                }
            });

            document.querySelectorAll('.custom-video-player').forEach(playerContainer => {
                const playerId = playerContainer.dataset.playerId;
                const video = document.getElementById(`custom-video-element-${playerId}`);
                const progressBar = document.getElementById(`custom-video-progress-${playerId}`);
                const timeLabel = document.getElementById(`custom-video-time-${playerId}`);
                const playBtn = document.getElementById(`custom-video-play-btn-${playerId}`);
                const overlay = document.getElementById(`custom-video-overlay-${playerId}`);
                const overlayBtn = overlay.querySelector('button');
                const loader = document.getElementById(`video-loader-${playerId}`);

                function formatTime(secs) {
                    if (isNaN(secs)) return '0:00';
                    const m = Math.floor(secs / 60);
                    const s = Math.floor(secs % 60);
                    return `${m}:${s < 10 ? '0' : ''}${s}`;
                }

                function updateTimeDisplay() {
                    const elapsed = formatTime(video.currentTime);
                    const total = formatTime(video.duration);
                    if (timeLabel) timeLabel.textContent = `${elapsed} / ${total}`;
                }

                // Detect Aspect Ratio dynamically
                video.addEventListener('loadedmetadata', () => {
                    const ratio = video.videoWidth / video.videoHeight;
                    if (ratio > 1.2) {
                        playerContainer.style.aspectRatio = '16/9';
                        playerContainer.style.width = '100%';
                        playerContainer.style.height = 'auto';
                        playerContainer.style.maxHeight = '';
                    } else if (ratio < 0.8) {
                        playerContainer.style.aspectRatio = '9/16';
                        playerContainer.style.width = 'auto';
                        playerContainer.style.maxHeight = '45vh';
                    } else {
                        playerContainer.style.aspectRatio = '1/1';
                        playerContainer.style.width = 'auto';
                        playerContainer.style.maxHeight = '40vh';
                    }
                    updateTimeDisplay();
                });

                video.addEventListener('canplay', () => {
                    if (loader) {
                        loader.classList.add('opacity-0');
                        setTimeout(() => loader.classList.add('hidden'), 300);
                    }
                });

                video.addEventListener('waiting', () => {
                    if (loader) {
                        loader.classList.remove('hidden', 'opacity-0');
                    }
                });

                video.addEventListener('playing', () => {
                    if (loader) {
                        loader.classList.add('hidden', 'opacity-0');
                    }
                });

                video.addEventListener('timeupdate', () => {
                    if (video.duration && progressBar) {
                        const pct = (video.currentTime / video.duration) * 100;
                        progressBar.style.width = `${pct}%`;
                    }
                    updateTimeDisplay();
                });

                video.addEventListener('ended', () => {
                    playBtn.textContent = '▶';
                    overlayBtn.textContent = '▶';
                    overlay.classList.remove('opacity-0');
                    
                    // Restore the poster cover on video end
                    const poster = document.getElementById(`custom-video-poster-${playerId}`);
                    if (poster) {
                        poster.classList.remove('opacity-0', 'pointer-events-none');
                    }
                    
                    if (progressBar) progressBar.style.width = '0%';
                    updateTimeDisplay();

                    // Resume background music if paused by video
                    if (musicPausedByVideo) {
                        unduckBackgroundMusicVideo();
                        musicPausedByVideo = false;
                    }
                });

                video.addEventListener('pause', () => {
                    playBtn.textContent = '▶';
                    overlayBtn.textContent = '▶';
                    overlay.classList.remove('opacity-0');
                    
                    const playerContainer = video.closest('.custom-video-player');
                    if (playerContainer) {
                        playerContainer.classList.remove('video-playing');
                        playerContainer.classList.add('video-blurred');
                    }

                    // Resume background music if paused by video
                    if (musicPausedByVideo) {
                        unduckBackgroundMusicVideo();
                        musicPausedByVideo = false;
                    }
                });

                if (video.readyState >= 1) {
                    updateTimeDisplay();
                }
                
                // Safari workaround: seek slightly to generate thumbnail preview frame
                video.currentTime = 0.1;
            });
            
            // Initialize Media Virtualization
            initMediaVirtualization();
        });

        // ═══ MEDIA VIRTUALIZATION ENGINE ═══
        let mediaRegistry = [];
        function initMediaVirtualization() {
            document.querySelectorAll('.slide-pane img, .slide-pane video, .slide-pane audio').forEach((el) => {
                const slidePane = el.closest('.slide-pane');
                if (!slidePane) return;
                
                const slideNum = parseInt(slidePane.dataset.slide);
                if (!slideNum) return;
                
                const registryEntry = {
                    element: el,
                    slideNum: slideNum,
                    type: el.tagName.toLowerCase(),
                    src: el.getAttribute('src') || el.src || '',
                    srcset: el.getAttribute('srcset') || '',
                    sources: []
                };
                
                el.querySelectorAll('source').forEach(srcEl => {
                    registryEntry.sources.push({
                        element: srcEl,
                        src: srcEl.getAttribute('src') || '',
                        type: srcEl.getAttribute('type') || ''
                    });
                });
                
                mediaRegistry.push(registryEntry);
            });
            updateMediaLifecycle(currentSlide);
        }
        function manageSingleElementLifecycle(entry, activeSlideNum) {
            const el = entry.element;
            const slideNum = entry.slideNum;
            const isNear = (slideNum === activeSlideNum || slideNum === activeSlideNum - 1 || slideNum === activeSlideNum + 1);
            if (isNear) {
                if (entry.type === 'img') {
                    if (entry.src && el.getAttribute('src') !== entry.src) {
                        el.setAttribute('src', entry.src);
                    }
                    if (entry.srcset && el.getAttribute('srcset') !== entry.srcset) {
                        el.setAttribute('srcset', entry.srcset);
                    }
                } else if (entry.type === 'video' || entry.type === 'audio') {
                    let needsReload = false;
                    if (entry.sources.length > 0) {
                        const childSources = el.querySelectorAll('source');
                        if (childSources.length === 0) {
                            entry.sources.forEach(srcInfo => {
                                const sourceEl = document.createElement('source');
                                sourceEl.setAttribute('src', srcInfo.src);
                                if (srcInfo.type) sourceEl.setAttribute('type', srcInfo.type);
                                el.appendChild(sourceEl);
                            });
                            needsReload = true;
                        }
                    } else if (entry.src && !el.getAttribute('src')) {
                        el.setAttribute('src', entry.src);
                        needsReload = true;
                    }
                    if (needsReload) {
                        el.load();
                    }
                }
            } else {
                if (entry.type === 'img') {
                    if (el.getAttribute('src') !== 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"/>') {
                        el.setAttribute('src', 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"/>');
                        el.removeAttribute('srcset');
                    }
                } else if (entry.type === 'video' || entry.type === 'audio') {
                    el.pause();
                    el.removeAttribute('src');
                    el.innerHTML = '';
                    el.load();
                }
            }
        }
        function updateMediaLifecycle(activeSlideNum) {
            mediaRegistry.forEach(entry => {
                manageSingleElementLifecycle(entry, activeSlideNum);
            });
        }

        // ═══ START STORY ═══
        function startStory() {
            sessionStorage.setItem('story_started', 'true');
            document.getElementById('autoplay-overlay').classList.add('hidden');
            const audio = document.getElementById('story-audio');
            audio.play().then(() => {
                musicPlaying = true;
                const widgetPlayBtn = document.getElementById('music-widget-play');
                if (widgetPlayBtn) widgetPlayBtn.textContent = '⏸';
            }).catch(() => {
                console.log("Audio autoplay blocked");
            });
            createParticles();

            // Trigger welcome typewriter
            const welcomeEl = document.getElementById('welcome-title-1');
            if (welcomeEl) {
                typewriteTitle(welcomeEl, welcomeTitle.replace('[receiver]', '<?= h($page['receiver_name']) ?>').replace('[sender]', '<?= h($page['sender_name']) ?>'));
            }

            // Initialize first slide's effects
            onSlideEnter(1);
        }

        // ═══ MUSIC TOGGLE ═══
        function toggleMusic() {
            const audio = document.getElementById('story-audio');
            const widgetPlayBtn = document.getElementById('music-widget-play');
            if (musicPlaying) {
                audio.pause();
                if (window.activeSlideBgMusic) {
                    window.activeSlideBgMusic.pause();
                }
                musicPlaying = false;
                if (widgetPlayBtn) widgetPlayBtn.textContent = '▶';
            } else {
                // Pause all other audio/video elements when starting story music (except active slide music if it exists)
                document.querySelectorAll('audio, video').forEach(el => {
                    if (el.id !== 'story-audio' && (!window.activeSlideBgMusic || el.id !== window.activeSlideBgMusic.id)) {
                        el.pause();
                    }
                });
                
                // Update other play buttons to paused state
                document.querySelectorAll('[id^="custom-audio-play-"]').forEach(b => b.textContent = '▶');
                document.querySelectorAll('[id^="custom-audio-wave-"]').forEach(w => {
                    w.classList.add('opacity-50');
                    if (w.id.includes('letter')) {
                        stopVisualWave(w);
                    } else {
                        const pId = w.id.replace('custom-audio-wave-', '');
                        stopVoiceWaveAnim(pId);
                    }
                });
                document.querySelectorAll('[id^="voice-play-btn-"]').forEach(b => b.textContent = '▶️');
                musicPausedByVoice = false; // Reset voice pause state

                if (window.activeSlideBgMusic) {
                    window.activeSlideBgMusic.play().then(() => {
                        musicPlaying = true;
                        if (widgetPlayBtn) widgetPlayBtn.textContent = '⏸';
                    }).catch(() => {});
                } else {
                    audio.play().then(() => {
                        musicPlaying = true;
                        if (widgetPlayBtn) widgetPlayBtn.textContent = '⏸';
                    }).catch(() => {});
                }
            }
        }

        // ═══ FLOATING PARTICLES ═══
        let particleInterval = null;
        function createParticles() {
            if (window.LOOPR_PERF && window.LOOPR_PERF.maxParticles === 0) return;
            const container = document.getElementById('particle-container');
            if (!container) return;
            
            const isDesktop = window.innerWidth > 768;
            const baseMax = window.LOOPR_PERF ? window.LOOPR_PERF.maxParticles : 15;
            const max = isDesktop ? Math.max(baseMax, 45) : baseMax;
            
            let delayMultiplier = window.LOOPR_PERF ? (1 / window.LOOPR_PERF.animationScale) : 1;
            if (isDesktop) {
                delayMultiplier = 0.5;
            }
            const spawnInterval = isDesktop ? 400 : (1200 * delayMultiplier);
            
            function spawnParticle() {
                if (container.childElementCount >= max) {
                    return;
                }
                const p = document.createElement('div');
                p.className = 'particle';
                p.style.left = `${Math.random() * 100}vw`;
                p.style.fontSize = `${Math.random() * 18 + 14}px`;
                const baseDur = Math.random() * 5 + 7;
                p.style.setProperty('--dur', `${baseDur * delayMultiplier}s`);
                p.style.setProperty('--sc', `${Math.random() * 0.5 + 0.7}`);
                p.style.animationDelay = `${Math.random() * 2}s`;
                
                const particlesArray = (typeof themeParticles !== 'undefined' && Array.isArray(themeParticles) && themeParticles.length > 0) ? themeParticles : ['✨'];
                p.textContent = particlesArray[Math.floor(Math.random() * particlesArray.length)];
                
                container.appendChild(p);
                setTimeout(() => p.remove(), baseDur * delayMultiplier * 1000 + 2000);
            }
            
            const initialCount = Math.min(isDesktop ? 20 : 15, max);
            for (let i = 0; i < initialCount; i++) setTimeout(() => spawnParticle(), i * (isDesktop ? 150 : 300));
            
            if (particleInterval) clearInterval(particleInterval);
            particleInterval = setInterval(spawnParticle, spawnInterval);
        }

        // ═══ LOCAL VOICE NOTE PARTICLES ═══
        let voiceParticleIntervals = {};
        function spawnVoicePageParticles(slideNum, category) {
            const container = document.getElementById(`voice-particle-container-${slideNum}`);
            if (!container) return;
            
            container.innerHTML = '';
            if (voiceParticleIntervals[slideNum]) clearInterval(voiceParticleIntervals[slideNum]);
            
            let particlesList = ['✨', '🫧'];
            let animationClass = 'falling';
            
            if (category === 'proposal' || category === 'romantic') {
                particlesList = ['🌹', '🌸', '❤️', '💕'];
                animationClass = 'falling';
            } else if (category === 'birthday') {
                particlesList = ['🎈', '🎉', '🎁'];
                animationClass = 'floating-up';
            } else if (category === 'sorry' || category === 'patchup') {
                particlesList = ['💧', '🌧️', '✨'];
                animationClass = 'falling';
            } else if (category === 'miss_you') {
                particlesList = ['⭐', '🌟', '✨', '💫'];
                animationClass = 'twinkling';
            } else if (category === 'friendship') {
                particlesList = ['🌻', '🌈', '🎨', '🤝'];
                animationClass = 'floating-up';
            }
            
            function spawn() {
                if (container.childElementCount >= 20) return;
                const p = document.createElement('div');
                p.className = `voice-local-particle ${animationClass}`;
                p.textContent = particlesList[Math.floor(Math.random() * particlesList.length)];
                
                p.style.left = `${Math.random() * 100}%`;
                const scale = Math.random() * 0.6 + 0.6;
                p.style.setProperty('--sc', scale);
                p.style.setProperty('--op', Math.random() * 0.4 + 0.4);
                
                const duration = Math.random() * 3 + 4;
                p.style.setProperty('--dur', `${duration}s`);
                
                if (animationClass === 'twinkling') {
                    p.style.top = `${Math.random() * 100}%`;
                } else if (animationClass === 'falling') {
                    p.style.top = `-20px`;
                } else if (animationClass === 'floating-up') {
                    p.style.top = `100%`;
                }
                
                container.appendChild(p);
                setTimeout(() => p.remove(), duration * 1000);
            }
            
            for (let i = 0; i < 8; i++) {
                setTimeout(spawn, i * 200);
            }
            
            voiceParticleIntervals[slideNum] = setInterval(spawn, 800);
        }
        
        function clearVoicePageParticles(slideNum) {
            if (voiceParticleIntervals[slideNum]) {
                clearInterval(voiceParticleIntervals[slideNum]);
                delete voiceParticleIntervals[slideNum];
            }
            const container = document.getElementById(`voice-particle-container-${slideNum}`);
            if (container) container.innerHTML = '';
        }

        // ═══ SLIDE NAVIGATION & TRANSITIONS ═══
        function goSlide(num) {
            if (isTransitioning) return;
            if (num < 1 || num > totalSlides) return;
            if (currentSlide === num) return;

            const fromSlide = document.getElementById(`slide-${currentSlide}`);
            const toSlide = document.getElementById(`slide-${num}`);
            
            if (!fromSlide || !toSlide) return;
            
            // Instantly reveal any cinematic overlay inside the entering slide to prevent blank transition screen
            const overlay = toSlide.querySelector('.cinematic-overlay');
            if (overlay) {
                overlay.classList.remove('pointer-events-none', 'opacity-0');
                overlay.classList.add('opacity-100');
            }
            
            isTransitioning = true;
            
            // Check for animation style override
            const overrideTrans = toSlide.dataset.animationStyle;
            const transitions = ['fade', 'slide', 'zoom', 'parallax', 'flip', 'pageturn'];
            const transStyle = (overrideTrans && transitions.includes(overrideTrans)) ? overrideTrans : transitions[(currentSlide + num) % transitions.length];
            
            // Apply entering/exiting states
            fromSlide.classList.add('transitioning', `exit-${transStyle}`);
            toSlide.classList.add('transitioning', `enter-${transStyle}`, 'active');

            // Scroll container reset for letter slides
            if (toSlide.dataset.slideType === 'letter') {
                const scrollContainer = toSlide.querySelector('.overflow-y-auto');
                if (scrollContainer) scrollContainer.scrollTop = 0;
            }
            
            // Auto scale visible / transitioning slides
            if (typeof autoScaleSlides === 'function') autoScaleSlides();
            
            // Update progress bar
            document.getElementById('slide-progress').style.width = `${(num / totalSlides) * 100}%`;
            

            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Ambient Throttling / 60 FPS Throttler
            const toSlideType = toSlide.dataset.slideType;
            const particleCont = document.getElementById('particle-container');
            if (particleCont) {
                const disableParticleSlides = [
                    'voice_message', 'video_message', 'cake_blowout', 'gift_box', 'destination_reveal', 'interactive_choice', 'gallery',
                    'birthday_pop_balloons', 'birthday_memory_board', 'birthday_wish_board', 'birthday_blow_candles',
                    'proposal_rose_cinematic', 'proposal_constellation', 'proposal_heart_formation', 'proposal_love_letter', 'proposal_ring_cinematic', 'proposal_final_choice',
                    'love_letter_envelope', 'love_letter_handwrite', 'love_letter_scrapbook', 'love_letter_dreams', 'love_letter_voice_wave', 'love_letter_folding',
                    'manalo_angry_meter', 'manalo_emergency', 'manalo_rescue', 'manalo_miss_things', 'manalo_bribe', 'manalo_quiz', 'manalo_unlock', 'manalo_letter', 'manalo_smile', 'manalo_restored_finale'
                ];
                const hasCinematicElement = toSlide.querySelector('[id*="-cinematic-container-"]') || 
                                           toSlide.querySelector('[id*="-canvas-"]') || 
                                           toSlide.querySelector('.custom-video-player') ||
                                           toSlide.querySelector('.custom-audio-player');
                
                let shouldDisable = disableParticleSlides.includes(toSlideType) || hasCinematicElement;
                if (window.innerWidth > 768 && hasCinematicElement) {
                    shouldDisable = false;
                }

                if (shouldDisable) {
                    particleCont.style.display = 'none';
                    if (particleInterval) {
                        clearInterval(particleInterval);
                        particleInterval = null;
                    }
                    particleCont.innerHTML = '';
                } else {
                    particleCont.style.display = '';
                    if (!particleInterval) {
                        createParticles();
                    }
                }
            }

            // Stop any playing custom audio/video when changing slides
            document.querySelectorAll('audio, video').forEach(el => {
                if (el.id !== 'story-audio') {
                    el.pause();
                }
            });
            document.querySelectorAll('[id^="custom-audio-play-"]').forEach(b => b.textContent = '▶');
            document.querySelectorAll('[id^="custom-video-play-btn-"]').forEach(b => b.textContent = '▶');
            document.querySelectorAll('[id^="custom-video-overlay-"]').forEach(o => o.classList.remove('opacity-0'));
            
            // Clean up old slide local voice particles
            if (fromSlide) {
                const prevVoiceContainer = fromSlide.querySelector('[id^="voice-particle-container-"]');
                if (prevVoiceContainer) {
                    clearVoicePageParticles(currentSlide);
                }
                // Clean up dynamic gallery to reclaim memory instantly
                const prevGalleryRender = fromSlide.querySelector('[id^="gallery-render-"]');
                if (prevGalleryRender) {
                    prevGalleryRender.innerHTML = '';
                    const loader = fromSlide.querySelector('[id^="gallery-loader-"]');
                    if (loader) loader.classList.remove('hidden');
                }
            }

            // Media Virtualization Update
            if (typeof updateMediaLifecycle === 'function') {
                updateMediaLifecycle(num);
            }

            // Clean up slide timers and general animations
            clearSlideTimers(currentSlide);
            if (typeof stopSynthesizedMelody === 'function') stopSynthesizedMelody();
            if (typeof finaleHeartsInterval !== 'undefined' && finaleHeartsInterval) {
                clearInterval(finaleHeartsInterval);
                finaleHeartsInterval = null;
            }
            if (typeof cleanManaLoIntervals === 'function') {
                cleanManaLoIntervals();
            }
            if (typeof voiceWaveAnimIds === 'object') {
                Object.keys(voiceWaveAnimIds).forEach(id => {
                    cancelAnimationFrame(voiceWaveAnimIds[id]);
                    delete voiceWaveAnimIds[id];
                });
            }
            
            setTimeout(() => {
                // Clear transition styles
                fromSlide.classList.remove('active', 'transitioning', `exit-${transStyle}`);
                toSlide.classList.remove('transitioning', `enter-${transStyle}`);
                
                // Clear any active voice waveforms animations
                Object.keys(voiceAnimationFrames).forEach(id => stopVoiceWaveAnim(id));
                
                currentSlide = num;
                isTransitioning = false;
                onSlideEnter(num);
            }, 750);
        }

        function onSlideEnter(num) {
            const slide = document.getElementById(`slide-${num}`);
            if (!slide) return;

            // Preload any audio tags inside the slide
            slide.querySelectorAll('audio').forEach(audio => {
                try {
                    audio.load();
                } catch (e) {
                    console.warn("Audio preloading failed:", e);
                }
            });

            // Reveal any video players in this slide (makes them visible via CSS opacity)
            slide.querySelectorAll('.custom-video-player').forEach(vp => {
                vp.classList.add('video-revealed');
            });

            // Recalculate layout scaling
            if (typeof autoScaleSlides === 'function') autoScaleSlides();

            // Trigger local voice note particles
            const voiceCont = slide.querySelector('[id^="voice-particle-container-"]');
            if (voiceCont) {
                const category = '<?= h($page['category'] ?? 'surprise') ?>';
                spawnVoicePageParticles(num, category);
            }

            if (typeof slidesVisited !== 'undefined') {
                slidesVisited.add(num);
                if (num === totalSlides) {
                    reachedFinalSlide = true;
                }
            }

            // Trigger cinematic cake cutting if the slide has a cinematic container
            if (document.getElementById(`cake-cinematic-container-${num}`)) {
                runCakeCinematic(num);
            }

            // Trigger Proposal category cinematic animations
            if (document.getElementById(`rose-cinematic-container-${num}`)) {
                runRoseCinematic(num);
            }
            if (document.getElementById(`constellation-container-${num}`)) {
                runConstellationCinematic(num);
            }
            if (document.getElementById(`heart-formation-container-${num}`)) {
                runHeartFormationCinematic(num);
            }
            if (document.getElementById(`love-letter-container-${num}`)) {
                runLoveLetterCinematic(num);
            }
            if (document.getElementById(`portal-container-${num}`)) {
                runPortalCinematic(num);
            }
            if (document.getElementById(`proposal-countdown-display-${num}`)) {
                startProposalCountdown(num);
            }
            if (document.getElementById(`ring-cinematic-container-${num}`)) {
                // Initialize ring box closed
                const lid = document.getElementById(`ring-box-lid-${num}`);
                if (lid) lid.style.transform = 'translateY(0) scaleY(1)';
                const ring = document.getElementById(`ring-box-diamond-g-${num}`);
                if (ring) {
                    ring.classList.add('opacity-0', 'scale-0');
                    ring.classList.remove('opacity-100', 'scale-100');
                }
                const rays = document.getElementById(`ring-cinematic-rays-${num}`);
                if (rays) {
                    rays.classList.remove('ring-light-rays-active');
                    rays.querySelectorAll('.ring-light-ray').forEach(r => r.style.opacity = '0');
                }
                const btn = document.getElementById(`ring-cinematic-continue-${num}`);
                if (btn) btn.classList.add('hidden');
                
                // Dim music and show container
                dimBackgroundMusic();
                const container = document.getElementById(`ring-cinematic-container-${num}`);
                container.classList.remove('pointer-events-none', 'opacity-0');
                container.classList.add('opacity-100');
                ringBoxOpened[num] = false;
            }
            if (document.getElementById(`final-cinematic-container-${num}`)) {
                runFinalProposalCinematic(num);
            }
            if (document.getElementById(`celebration-container-${num}`)) {
                runCelebrationCinematic(num);
            }

            // Trigger Love Letter category cinematic animations
            if (document.getElementById(`envelope-cinematic-container-${num}`)) {
                initLoveEnvelopeCinematic(num);
            }
            if (document.getElementById(`love-letter-handwrite-container-${num}`)) {
                runLoveLetterHandwrite(num);
            }
            if (document.getElementById(`scrapbook-container-${num}`)) {
                runLoveLetterScrapbook(num);
            }
            if (document.getElementById(`timeline-container-${num}`)) {
                runLoveLetterTimeline(num);
            }
            if (document.getElementById(`future-dreams-container-${num}`)) {
                runLoveLetterFutureDreams(num);
            }
            if (document.getElementById(`voice-wave-canvas-${num}`)) {
                initLoveVoiceWave(num);
            }
            if (document.getElementById(`folding-container-${num}`)) {
                runLoveLetterFolding(num);
            }
            if (document.getElementById(`reaching-hands-area-${num}`)) {
                initReachingHands(num);
            }
            if (document.getElementById(`reactions-finale-container-${num}`)) {
                runLoveLetterReactionsFinale(num);
            }

            // Trigger Mana Lo Yaar category cinematic animations
            if (document.getElementById(`angry-meter-slide-${num}`)) {
                runManaLoAngryMeter(num);
            }
            if (document.getElementById(`emergency-container-${num}`)) {
                runManaLoEmergency(num);
            }
            if (document.getElementById(`rescue-container-${num}`)) {
                runManaLoRescue(num);
            }
            if (document.getElementById(`miss-things-container-${num}`)) {
                runManaLoMissThings(num);
            }
            if (document.getElementById(`bribe-container-${num}`)) {
                runManaLoBribe(num);
            }
            if (document.getElementById(`quiz-container-${num}`)) {
                runManaLoQuiz(num);
            }
            if (document.getElementById(`unlock-container-${num}`)) {
                runManaLoUnlock(num);
            }
            if (document.getElementById(`mana-lo-letter-container-${num}`)) {
                runManaLoLetter(num);
            }
            if (document.getElementById(`smile-container-${num}`)) {
                runManaLoSmile(num);
            }
            if (document.getElementById(`restored-finale-container-${num}`)) {
                runManaLoFinale(num);
            }

            // Trigger dynamic gallery if the slide is a gallery container
            if (slide.querySelector('[id^="gallery-container-"]')) {
                initDynamicGallery(num);
            }

            // Trigger custom video reveal animation
            const videoPlayer = slide.querySelector('.custom-video-player');
            if (videoPlayer) {
                setTimeout(() => {
                    videoPlayer.classList.add('video-revealed');
                }, 300);
            }

            // Animate cards
            const cardsContainer = slide.querySelector('.cards-container');
            if (cardsContainer) {
                const cards = cardsContainer.querySelectorAll('.card-enter');
                cards.forEach((card, i) => {
                    setTimeout(() => card.classList.add('visible'), i * 150 + 200);
                });
            }

            // Trigger polaroid cards dropping from top
            if (slide.querySelector('.polaroid-card')) {
                slide.querySelectorAll('.polaroid-card').forEach((card, ci) => {
                    setTimeout(() => {
                        const rot = (Math.random() - 0.5) * 30;
                        card.style.transform = `translateY(0px) rotate(${rot}deg)`;
                    }, ci * 300 + 200);
                });
            }

            // Together counter slide
            if (slide.querySelector('[id^="together-counter-"]')) {
                startAnniversaryCounter(num);
            }

            // Wipe fog canvas init
            if (slide.querySelector('[id^="fog-canvas-"]')) {
                initFogCanvas(num);
            }

            // Scratch card canvas init
            if (slide.querySelector('[id^="scratch-canvas-"]')) {
                initScratchCanvas(num);
            }

            // Trigger typewriter on letter slides
            const typewriterEl = document.getElementById(`typewriter-${num}`);
            if (typewriterEl && !typewriterRunning[num]) {
                typewriterRunning[num] = true;
                
                // Safety timeout fallback (30 seconds) to force show transition button if animations get stuck
                const safetyTimeout = setTimeout(() => {
                    const sig = document.getElementById(`signature-${num}`);
                    if (sig) sig.classList.remove('opacity-0');
                    const btn = document.getElementById(`letter-btn-${num}`);
                    if (btn) {
                        btn.classList.remove('opacity-0', 'pointer-events-none');
                        btn.classList.add('opacity-100');
                    }
                }, 30000);

                runTypewriter(typewriterEl, letterText, () => {
                    clearTimeout(safetyTimeout);
                    const sig = document.getElementById(`signature-${num}`);
                    if (sig) sig.classList.remove('opacity-0');
                    const btn = document.getElementById(`letter-btn-${num}`);
                    if (btn) {
                        btn.classList.remove('opacity-0', 'pointer-events-none');
                        btn.classList.add('opacity-100');
                    }
                });
            }

            // Trigger counter
            const counterEl = document.getElementById(`counter-display-${num}`);
            if (counterEl && relationshipDate) {
                renderCounter(counterEl, relationshipDate);
            }

            // Trigger 3-2-1 countdown
            const countdownEl = document.getElementById(`countdown-display-${num}`);
            if (countdownEl) {
                run321Countdown(countdownEl, num);
            }

            // Trigger destination reveal
            const destEl = document.getElementById(`dest-reveal-${num}`);
            if (destEl) {
                setTimeout(() => {
                    destEl.classList.remove('opacity-0', 'scale-75');
                    destEl.classList.add('opacity-100', 'scale-100');
                }, 500);
            }

            // Confetti on celebration slides
            const slideData = slide.dataset.slide;
            <?php foreach ($slides as $si => $sl): ?>
                <?php if (in_array($sl['type'], ['celebration', 'birthday_reveal'])): ?>
                    if (num === <?= $si + 1 ?>) {
                        setTimeout(() => {
                            confetti({ particleCount: 150, spread: 80, origin: { y: 0.5 } });
                            setTimeout(() => confetti({ particleCount: 80, spread: 60, origin: { x: 0.2, y: 0.6 } }), 300);
                            setTimeout(() => confetti({ particleCount: 80, spread: 60, origin: { x: 0.8, y: 0.6 } }), 600);
                        }, 400);
                    }
                <?php endif; ?>
            <?php endforeach; ?>

            // Slide-specific background audio lifecycle
            handleSlideAudioLifecycle(num);

            // Premium slides dispatchers
            if (document.getElementById(`premium-memory-reveal-container-${num}`)) {
                runPremiumMemoryReveal(num);
            }
            if (document.getElementById(`premium-heart-formation-container-${num}`)) {
                runPremiumHeartFormation(num);
            }
            if (document.getElementById(`premium-collage-explosion-container-${num}`)) {
                runPremiumCollageExplosion(num);
            }
            if (document.getElementById(`premium-floating-memories-container-${num}`)) {
                runPremiumFloatingMemories(num);
            }
            if (document.getElementById(`premium-star-sky-container-${num}`)) {
                runPremiumStarSky(num);
            }
            if (document.getElementById(`premium-memory-book-container-${num}`) || document.getElementById(`book-container-${num}`)) {
                runPremiumMemoryBook(num);
            }
            if (document.getElementById(`premium-reasons-special-container-${num}`)) {
                runPremiumReasonsSpecial(num);
            }
            if (document.getElementById(`premium-puzzle-reveal-container-${num}`)) {
                runPremiumPuzzleReveal(num);
            }
            if (document.getElementById(`premium-love-counter-container-${num}`)) {
                runPremiumLoveCounter(num);
            }
            if (document.getElementById(`premium-memory-timeline-container-${num}`)) {
                runPremiumMemoryTimeline(num);
            }
            if (document.getElementById(`premium-mosaic-heart-container-${num}`)) {
                runPremiumMosaicHeart(num);
            }
            if (document.getElementById(`premium-photo-spotlight-container-${num}`)) {
                runPremiumPhotoSpotlight(num);
            }
            if (document.getElementById(`premium-our-chats-container-${num}`)) {
                runPremiumOurChats(num);
            }
        }

        // ═══ DYNAMIC GALLERY ANIMATIONS ENGINE ═══
        function initDynamicGallery(slideNum) {
            const container = document.getElementById(`gallery-container-${slideNum}`);
            if (!container) return;
            
            const renderArea = document.getElementById(`gallery-render-${slideNum}`);
            const loader = document.getElementById(`gallery-loader-${slideNum}`);
            
            if (!renderArea) return;
            renderArea.innerHTML = '';
            
            if (loader) {
                loader.classList.remove('hidden');
            }
            
            if (!pageImages || pageImages.length === 0) {
                if (loader) loader.classList.add('hidden');
                renderArea.innerHTML = `<div class="text-center p-8 flex flex-col items-center justify-center min-h-[260px]"><span class="text-3xl mb-2">📸</span><span class="text-xs text-slate-400 font-semibold">No photos uploaded yet</span></div>`;
                return;
            }
            
            setTimeout(() => {
                if (loader) loader.classList.add('hidden');
                
                // Inject slideshow specific CSS if not present
                if (!document.getElementById('gallery-slideshow-styles')) {
                    const styleEl = document.createElement('style');
                    styleEl.id = 'gallery-slideshow-styles';
                    styleEl.textContent = `
                        .gallery-slideshow-container {
                            width: 100%;
                            height: 100%;
                            position: relative;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .gallery-slide-active {
                            width: 100%;
                            height: 100%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            position: relative;
                            border-radius: 2rem;
                            overflow: hidden;
                            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
                            background: rgba(15, 23, 42, 0.4);
                            border: 1px solid rgba(255, 255, 255, 0.1);
                        }
                        .gallery-slide-active img {
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            transition: opacity 0.4s ease, transform 0.4s ease;
                        }
                        .gallery-nav-btn {
                            position: absolute;
                            top: 50%;
                            transform: translateY(-50%);
                            width: 2.5rem;
                            height: 2.5rem;
                            border-radius: 50%;
                            background: rgba(0, 0, 0, 0.4);
                            backdrop-filter: blur(10px);
                            border: 1px solid rgba(255, 255, 255, 0.2);
                            color: white;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 0.8rem;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            z-index: 30;
                            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
                        }
                        .gallery-nav-btn:hover {
                            background: rgba(255, 255, 255, 0.2);
                            scale: 1.1;
                        }
                        .gallery-nav-btn:active {
                            scale: 0.9;
                        }
                        .gallery-nav-btn.prev {
                            left: 0.5rem;
                        }
                        .gallery-nav-btn.next {
                            right: 0.5rem;
                        }
                        .gallery-counter-tag {
                            position: absolute;
                            top: 1rem;
                            right: 1rem;
                            background: rgba(0, 0, 0, 0.6);
                            backdrop-filter: blur(5px);
                            padding: 0.25rem 0.75rem;
                            border-radius: 1rem;
                            font-size: 0.7rem;
                            font-weight: 700;
                            color: rgba(255, 255, 255, 0.9);
                            border: 1px solid rgba(255, 255, 255, 0.1);
                            z-index: 20;
                        }
                        .gallery-slide-overlay {
                            position: absolute;
                            bottom: 0;
                            left: 0;
                            right: 0;
                            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.2) 60%, transparent 100%);
                            padding: 1.5rem 1rem 1rem 1rem;
                            color: white;
                            text-align: left;
                            z-index: 10;
                            pointer-events: none;
                        }
                    `;
                    document.head.appendChild(styleEl);
                }
                
                // Initialize slideshow layout
                renderArea.className = 'gallery-render-area w-full h-full relative p-2 flex items-center justify-center';
                
                const slideShowContainer = document.createElement('div');
                slideShowContainer.className = 'gallery-slideshow-container w-full h-full max-h-[50vh] sm:max-h-[60vh]';
                renderArea.appendChild(slideShowContainer);
                
                let activeIdx = 0;
                
                // Create Active Slide Card
                const slideCard = document.createElement('div');
                slideCard.className = 'gallery-slide-active w-full h-full relative';
                slideShowContainer.appendChild(slideCard);
                
                // Helper function to update the current slide image
                function updateSlide(idx) {
                    slideCard.innerHTML = '';
                    slideCard.classList.add('skeleton-loader');
                    
                    const img = pageImages[idx];
                    const elImg = createResponsiveImage(img, 'w-full h-full object-cover transition-opacity duration-300 opacity-0');
                    elImg.onload = () => {
                        slideCard.classList.remove('skeleton-loader');
                        elImg.classList.remove('opacity-0');
                    };
                    slideCard.appendChild(elImg);
                    
                    // Counter tag (e.g. 1 / 5)
                    const counter = document.createElement('div');
                    counter.className = 'gallery-counter-tag';
                    counter.textContent = `${idx + 1} / ${pageImages.length}`;
                    slideCard.appendChild(counter);
                    
                    // Bottom Caption strip
                    const overlay = document.createElement('div');
                    overlay.className = 'gallery-slide-overlay';
                    overlay.innerHTML = `
                        <span class="text-[9px] font-bold uppercase tracking-wider text-pink-300">Memory #${idx + 1}</span>
                        <p class="text-xs text-white/90 mt-1 font-serif italic">A beautiful moment captured in time ✨</p>
                    `;
                    slideCard.appendChild(overlay);
                    
                    // Zoom on click
                    slideCard.onclick = () => openPhotoModal(baseUrl + img.image_path);
                }
                
                // Initialize first slide
                updateSlide(activeIdx);
                
                // Navigation Arrows (only if more than 1 image)
                if (pageImages.length > 1) {
                    const prevBtn = document.createElement('button');
                    prevBtn.className = 'gallery-nav-btn prev';
                    prevBtn.innerHTML = '◀';
                    prevBtn.onclick = (e) => {
                        e.stopPropagation();
                        activeIdx = (activeIdx - 1 + pageImages.length) % pageImages.length;
                        updateSlide(activeIdx);
                    };
                    slideShowContainer.appendChild(prevBtn);
                    
                    const nextBtn = document.createElement('button');
                    nextBtn.className = 'gallery-nav-btn next';
                    nextBtn.innerHTML = '▶';
                    nextBtn.onclick = (e) => {
                        e.stopPropagation();
                        activeIdx = (activeIdx + 1) % pageImages.length;
                        updateSlide(activeIdx);
                    };
                    slideShowContainer.appendChild(nextBtn);
                }
            }, 600);
        }

        function renderPremiumGallery(renderArea, images, slideNum, category) {
            const imgCount = images.length;
            const justifyClass = imgCount >= 5 ? 'justify-start' : 'justify-center';
            renderArea.className = `gallery-render-area w-full h-full relative flex flex-col ${justifyClass} items-center overflow-y-auto overflow-x-hidden p-2`;
            
            // 1. Add background decorations container
            const decorContainer = document.createElement('div');
            decorContainer.className = 'absolute inset-0 pointer-events-none z-0 overflow-hidden';
            renderArea.appendChild(decorContainer);
            
            // Spawn background particles matching current category
            spawnCategoryBackgroundDecorations(decorContainer, category);
            
            // 2. Add foreground container
            const gridContainer = document.createElement('div');
            gridContainer.className = 'w-full max-w-lg z-10 relative flex flex-col justify-center items-center h-full gap-3';
            renderArea.appendChild(gridContainer);
            
            if (imgCount === 1) {
                // 1 Photo: Display one large hero card (Width: 90%, Height: 60-70vh, centered)
                gridContainer.classList.add('w-full', 'h-full', 'flex', 'justify-center', 'items-center');
                const card = createPremiumGalleryCard(images[0], 0, 'hero-card');
                gridContainer.appendChild(card);
            } 
            else if (imgCount === 2) {
                // 2 Photos: Two large stacked cards (45% screen height each)
                gridContainer.classList.add('flex', 'flex-col', 'gap-4', 'w-full', 'h-full', 'justify-center');
                images.forEach((img, idx) => {
                    const card = createPremiumGalleryCard(img, idx, 'stacked-card');
                    gridContainer.appendChild(card);
                });
            } 
            else if (imgCount === 3) {
                // 3 Photos: Magazine style layout (one large featured photo, two smaller supporting photos)
                gridContainer.className = 'w-full max-w-lg z-10 relative grid grid-cols-3 grid-rows-2 gap-3 h-full max-h-[60dvh]';
                
                // Card 0: Featured (spans 2 columns, 2 rows)
                const card1 = createPremiumGalleryCard(images[0], 0, 'magazine-featured-card col-span-2 row-span-2');
                gridContainer.appendChild(card1);
                
                // Card 1: Supporting 1
                const card2 = createPremiumGalleryCard(images[1], 1, 'magazine-supporting-card col-span-1 row-span-1');
                gridContainer.appendChild(card2);
                
                // Card 2: Supporting 2
                const card3 = createPremiumGalleryCard(images[2], 2, 'magazine-supporting-card col-span-1 row-span-1');
                gridContainer.appendChild(card3);
            } 
            else if (imgCount === 4) {
                // 4 Photos: Balanced 2x2 grid
                gridContainer.className = 'w-full max-w-lg z-10 relative grid grid-cols-2 gap-3 h-full justify-center';
                images.forEach((img, idx) => {
                    const card = createPremiumGalleryCard(img, idx, 'grid-card aspect-square');
                    gridContainer.appendChild(card);
                });
            } 
            else {
                // 5+ Photos: Beautiful scrollable grid of square cards (perfectly organized, no overlapping)
                gridContainer.className = 'w-full max-w-lg z-10 relative grid grid-cols-2 sm:grid-cols-3 gap-3 p-1 h-auto';
                images.forEach((img, idx) => {
                    const card = createPremiumGalleryCard(img, idx, 'grid-card aspect-square');
                    gridContainer.appendChild(card);
                });
            }
            
            // Trigger celebration confetti
            triggerConfettiBurst();
        }

        function createPremiumGalleryCard(imgObj, idx, cardClass) {
            const card = document.createElement('div');
            card.className = `premium-gallery-card skeleton-loader cursor-pointer ${cardClass}`;
            
            // Staggered delay: 0.15s apart
            card.style.animationDelay = `${idx * 0.15}s`;
            
            const elImg = createResponsiveImage(imgObj, 'w-full h-full opacity-0 transition-opacity duration-300 rounded-2xl');
            
            elImg.onload = () => {
                const w = elImg.naturalWidth;
                const h = elImg.naturalHeight;
                const ratio = w / h;
                
                let orientation = 'square';
                if (ratio > 1.25) {
                    orientation = 'landscape';
                } else if (ratio < 0.8) {
                    orientation = 'portrait';
                }
                
                card.classList.add(`is-${orientation}`);
                
                // Always use cover for consistent full-bleed uniform display
                elImg.style.objectFit = 'cover';
                
                elImg.style.opacity = '1';
                card.classList.remove('skeleton-loader');
            };
            
            card.appendChild(elImg);
            card.onclick = () => openPhotoModal(baseUrl + imgObj.image_path);
            return card;
        }

        function spawnCategoryBackgroundDecorations(container, category) {
            const isDesktop = window.innerWidth > 768;
            
            if (category === 'sorry' || category === 'patchup') {
                // Falling rain / tears theme
                const sorryItems = ['💧', '💔', '🥺', '🕊️', '💧'];
                for (let i = 0; i < 8; i++) {
                    const item = document.createElement('div');
                    item.className = 'premium-bg-particle';
                    item.textContent = sorryItems[i % sorryItems.length];
                    item.style.left = `${5 + i * 12 + Math.random() * 5}%`;
                    item.style.fontSize = `${16 + Math.random() * 12}px`;
                    item.style.animation = `item-fall ${8 + Math.random() * 4}s linear infinite`;
                    item.style.animationDelay = `${-i * 1.5 - Math.random() * 2}s`;
                    item.style.setProperty('--drift', `${(Math.random() - 0.5) * 60}px`);
                    item.style.setProperty('--rot', `${(Math.random() - 0.5) * 45}deg`);
                    container.appendChild(item);
                }
            } 
            else if (category === 'anniversary') {
                // Rising romantic rose petals and hearts
                const romanticItems = ['❤️', '💖', '🌹', '💕', '🌹'];
                for (let i = 0; i < 8; i++) {
                    const item = document.createElement('div');
                    item.className = 'premium-bg-particle';
                    item.textContent = romanticItems[i % romanticItems.length];
                    item.style.left = `${5 + i * 12 + Math.random() * 5}%`;
                    item.style.fontSize = `${14 + Math.random() * 12}px`;
                    item.style.animation = `balloon-rise ${10 + Math.random() * 5}s linear infinite`;
                    item.style.animationDelay = `${-i * 1.5 - Math.random() * 2}s`;
                    item.style.setProperty('--drift', `${(Math.random() - 0.5) * 70}px`);
                    item.style.setProperty('--rot', `${(Math.random() - 0.5) * 35}deg`);
                    container.appendChild(item);
                }
            } 
            else if (category === 'friendship') {
                // Sunny flowers and rainbows theme
                const friendshipItems = ['🌻', '🌈', '🤝', '⭐', '🌻'];
                for (let i = 0; i < 7; i++) {
                    const item = document.createElement('div');
                    item.className = 'premium-bg-particle';
                    item.textContent = friendshipItems[i % friendshipItems.length];
                    item.style.left = `${10 + i * 13 + Math.random() * 5}%`;
                    item.style.fontSize = `${16 + Math.random() * 14}px`;
                    item.style.animation = `balloon-rise ${12 + Math.random() * 6}s linear infinite`;
                    item.style.animationDelay = `${-i * 2 - Math.random() * 2}s`;
                    item.style.setProperty('--drift', `${(Math.random() - 0.5) * 60}px`);
                    item.style.setProperty('--rot', `${(Math.random() - 0.5) * 30}deg`);
                    container.appendChild(item);
                }
            } 
            else if (category === 'miss_you') {
                // Twinkling stars theme
                for (let i = 0; i < 12; i++) {
                    const item = document.createElement('div');
                    item.className = 'premium-bg-particle text-yellow-250';
                    item.textContent = ['⭐', '🌟', '💫', '✨'][i % 4];
                    item.style.left = `${Math.random() * 95}%`;
                    item.style.top = `${Math.random() * 95}%`;
                    item.style.fontSize = `${12 + Math.random() * 12}px`;
                    item.style.animation = 'golden-sparkle 3.5s ease-in-out infinite';
                    item.style.animationDelay = `${Math.random() * 3.5}s`;
                    container.appendChild(item);
                }
            } 
            else if (category === 'birthday') {
                // Rising balloons and birthday sparkles
                for (let i = 0; i < 5; i++) {
                    const balloon = document.createElement('div');
                    balloon.className = 'premium-bg-particle';
                    balloon.textContent = '🎈';
                    balloon.style.left = `${10 + i * 20 + Math.random() * 5}%`;
                    balloon.style.fontSize = `${20 + Math.random() * 20}px`;
                    balloon.style.animation = `balloon-rise ${12 + Math.random() * 6}s linear infinite`;
                    balloon.style.animationDelay = `${-i * 2 - Math.random() * 2}s`;
                    balloon.style.setProperty('--drift', `${(Math.random() - 0.5) * 80}px`);
                    balloon.style.setProperty('--rot', `${(Math.random() - 0.5) * 40}deg`);
                    container.appendChild(balloon);
                }
                const birthdayItems = ['🎂', '🎁', '🎉', '🎁'];
                birthdayItems.forEach((emoji, idx) => {
                    const item = document.createElement('div');
                    item.className = 'premium-bg-particle';
                    item.textContent = emoji;
                    item.style.left = `${15 + idx * 22 + Math.random() * 5}%`;
                    item.style.fontSize = `${16 + Math.random() * 10}px`;
                    item.style.animation = `balloon-rise ${15 + Math.random() * 8}s linear infinite`;
                    item.style.animationDelay = `${-idx * 3 - Math.random() * 3}s`;
                    item.style.setProperty('--drift', `${(Math.random() - 0.5) * 60}px`);
                    item.style.setProperty('--rot', `${(Math.random() - 0.5) * 30}deg`);
                    container.appendChild(item);
                });
                for (let i = 0; i < 6; i++) {
                    const heart = document.createElement('div');
                    heart.className = 'premium-bg-particle';
                    heart.textContent = '❤️';
                    heart.style.left = `${5 + i * 16 + Math.random() * 5}%`;
                    heart.style.fontSize = `${12 + Math.random() * 10}px`;
                    heart.style.animation = `heart-drift ${9 + Math.random() * 5}s ease-in-out infinite`;
                    heart.style.animationDelay = `${-i * 1.5 - Math.random() * 2}s`;
                    heart.style.setProperty('--drift', `${(Math.random() - 0.5) * 50}px`);
                    container.appendChild(heart);
                }
            } 
            else {
                // Default / Surprise ambient bubbles and sparkles
                for (let i = 0; i < 6; i++) {
                    const bubble = document.createElement('div');
                    bubble.className = 'premium-bg-particle';
                    bubble.textContent = '🫧';
                    bubble.style.left = `${10 + i * 16 + Math.random() * 5}%`;
                    bubble.style.fontSize = `${14 + Math.random() * 12}px`;
                    bubble.style.animation = `balloon-rise ${14 + Math.random() * 6}s linear infinite`;
                    bubble.style.animationDelay = `${-i * 2 - Math.random() * 2}s`;
                    bubble.style.setProperty('--drift', `${(Math.random() - 0.5) * 70}px`);
                    bubble.style.setProperty('--rot', `${(Math.random() - 0.5) * 30}deg`);
                    container.appendChild(bubble);
                }
                for (let i = 0; i < 6; i++) {
                    const sparkle = document.createElement('div');
                    sparkle.className = 'premium-bg-particle text-white/50';
                    sparkle.textContent = '✨';
                    sparkle.style.left = `${Math.random() * 95}%`;
                    sparkle.style.top = `${Math.random() * 95}%`;
                    sparkle.style.fontSize = `${12 + Math.random() * 12}px`;
                    sparkle.style.animation = 'golden-sparkle 4s ease-in-out infinite';
                    sparkle.style.animationDelay = `${Math.random() * 4}s`;
                    container.appendChild(sparkle);
                }
            }
        }

        function renderGalleryStyle(renderArea, style, slideNum) {
            renderArea.className = 'gallery-render-area w-full h-full relative';
            
            let loadedCount = 0;
            const batchSize = 3;
            
            // Create style-specific containers
            let wrapper = renderArea;
            let extra = {};
            
            if (style === 'hero') {
                renderArea.classList.add('flex', 'items-center', 'justify-center', 'min-h-[55vh]');
            } else if (style === 'split') {
                renderArea.classList.add('flex', 'items-center', 'justify-center', 'min-h-[55vh]');
                wrapper = document.createElement('div');
                wrapper.className = 'gallery-split-container';
                renderArea.appendChild(wrapper);
            } else if (style === 'collage') {
                renderArea.classList.add('flex', 'items-center', 'justify-center', 'min-h-[55vh]');
                wrapper = document.createElement('div');
                wrapper.className = 'gallery-collage-container';
                renderArea.appendChild(wrapper);
            } else if (style === 'polaroid' || style === 'friendship') {
                renderArea.classList.add('flex', 'items-center', 'justify-center', 'min-h-[55vh]');
                wrapper = document.createElement('div');
                wrapper.className = 'gallery-polaroid-wrapper flex items-center justify-center';
                renderArea.appendChild(wrapper);
            } else if (style === 'cinematic') {
                renderArea.classList.add('flex', 'flex-col', 'items-center', 'justify-center', 'min-h-[55vh]');
                wrapper = document.createElement('div');
                wrapper.className = 'relative w-[90%] aspect-square rounded-3xl overflow-hidden shadow-2xl border ' + (isDark ? 'border-white/10' : 'border-slate-200');
                renderArea.appendChild(wrapper);
                
                if (pageImages.length > 1) {
                    const dots = document.createElement('div');
                    dots.className = 'flex space-x-1.5 mt-3 justify-center z-20';
                    renderArea.appendChild(dots);
                    extra.dotsContainer = dots;
                }
            } else if (style === 'sorry' || style === 'patchup') {
                renderArea.classList.add('min-h-[55vh]', 'flex', 'items-center', 'justify-center');
                const rain = document.createElement('div');
                rain.className = 'raindrop-overlay rounded-3xl overflow-hidden';
                for (let r = 0; r < 15; r++) {
                    const drop = document.createElement('div');
                    drop.className = 'absolute bg-white/20 w-0.5 rounded';
                    drop.style.left = `${Math.random() * 100}%`;
                    drop.style.top = `${-20 - Math.random() * 20}px`;
                    drop.style.height = `${10 + Math.random() * 15}px`;
                    drop.style.animation = `rainFall ${0.8 + Math.random() * 0.5}s linear infinite`;
                    drop.style.animationDelay = `${Math.random() * 1.5}s`;
                    rain.appendChild(drop);
                }
                renderArea.appendChild(rain);
                
                wrapper = document.createElement('div');
                wrapper.className = 'relative w-full aspect-video rounded-3xl overflow-hidden shadow-2xl border ' + (isDark ? 'border-white/10' : 'border-slate-200');
                renderArea.appendChild(wrapper);
                
                if (pageImages.length > 1) {
                    const nextBtn = document.createElement('button');
                    nextBtn.className = 'absolute right-4 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/40 text-white flex items-center justify-center font-bold text-xs shadow z-30';
                    nextBtn.textContent = '→';
                    let activeIdx = 0;
                    nextBtn.onclick = () => {
                        const cards = wrapper.querySelectorAll('.sorry-photo-card');
                        if (cards.length === 0) return;
                        cards[activeIdx].classList.remove('wiped');
                        cards[activeIdx].classList.add('hidden');
                        
                        activeIdx = (activeIdx + 1) % cards.length;
                        cards[activeIdx].classList.remove('hidden');
                        setTimeout(() => {
                            cards[activeIdx].classList.add('wiped');
                        }, 50);
                    };
                    renderArea.appendChild(nextBtn);
                }
            } else if (style === 'anniversary') {
                renderArea.classList.add('min-h-[55vh]', 'overflow-y-auto', 'no-scrollbar', 'pr-2');
                const line = document.createElement('div');
                line.className = 'timeline-line';
                renderArea.appendChild(line);
                extra.line = line;
            } else if (style === 'miss_you') {
                renderArea.classList.add('min-h-[55vh]', 'bg-slate-950/80', 'rounded-3xl', 'relative', 'overflow-hidden');
                for (let s = 0; s < 12; s++) {
                    const bgStar = document.createElement('div');
                    bgStar.className = 'absolute bg-yellow-100 rounded-full animate-pulse';
                    const size = 1 + Math.random() * 2;
                    bgStar.style.width = `${size}px`;
                    bgStar.style.height = `${size}px`;
                    bgStar.style.left = `${Math.random() * 95}%`;
                    bgStar.style.top = `${Math.random() * 95}%`;
                    renderArea.appendChild(bgStar);
                }
            } else if (style === 'proposal') {
                renderArea.classList.add('min-h-[55vh]', 'flex', 'items-center', 'justify-center');
                triggerHeartsOverlay(renderArea);
                wrapper = document.createElement('div');
                wrapper.className = 'grid grid-cols-2 gap-4 w-full p-2';
                renderArea.appendChild(wrapper);
            } else if (style === 'wall') {
                renderArea.classList.add('grid', 'grid-cols-2', 'gap-2', 'p-1', 'min-h-[55vh]');
            } else if (style === 'floating') {
                renderArea.classList.add('min-h-[55vh]');
            } else if (style === 'scrapbook') {
                renderArea.classList.add('min-h-[55vh]', 'overflow-hidden');
            } else if (style === 'birthday') {
                renderArea.classList.add('min-h-[55vh]', 'overflow-hidden');
            }
            
            // Create Sentinel element for lazy-loading
            const sentinel = document.createElement('div');
            sentinel.id = `gallery-sentinel-${slideNum}`;
            sentinel.className = 'w-full h-12 flex items-center justify-center text-[10px] text-slate-500 font-bold uppercase tracking-wider clear-both';
            sentinel.textContent = 'Scroll for more memories 📸';
            renderArea.appendChild(sentinel);
            
            function loadNextBatch() {
                if (loadedCount >= pageImages.length) {
                    if (sentinel.parentNode) sentinel.remove();
                    return;
                }
                
                const batch = pageImages.slice(loadedCount, loadedCount + batchSize);
                batch.forEach((img, idx) => {
                    const i = loadedCount + idx;
                    renderSingleImageCard(wrapper, img, i, style, slideNum, extra);
                });
                
                loadedCount += batch.length;
                
                // Reposition sentinel at the bottom of renderArea
                if (sentinel.parentNode) {
                    renderArea.appendChild(sentinel);
                }
                
                if (loadedCount >= pageImages.length) {
                    if (sentinel.parentNode) sentinel.remove();
                    if (style === 'birthday') {
                        triggerConfettiBurst();
                    }
                }
            }
            
            // Render first batch immediately
            loadNextBatch();
            
            // Observe sentinel using IntersectionObserver on scrollable slide pane
            if (loadedCount < pageImages.length && window.IntersectionObserver) {
                const slidePane = renderArea.closest('.slide-pane');
                const observer = new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting) {
                        loadNextBatch();
                    }
                }, {
                    root: slidePane || null,
                    rootMargin: '100px'
                });
                observer.observe(sentinel);
            } else {
                if (sentinel.parentNode) sentinel.remove();
            }
        }
        
        function renderSingleImageCard(wrapper, img, i, style, slideNum, extra) {
            if (style === 'hero') {
                const container = document.createElement('div');
                container.className = 'gallery-hero-container';
                
                // Blurred background
                const blurBg = document.createElement('div');
                blurBg.className = 'gallery-hero-blur-bg';
                blurBg.style.backgroundImage = `url(${baseUrl}${img.medium_path || img.image_path})`;
                container.appendChild(blurBg);
                
                // Image wrapper card
                const imgWrapper = document.createElement('div');
                imgWrapper.className = 'gallery-hero-wrapper skeleton-loader';
                
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover premium-photo-anim');
                elImg.onload = () => imgWrapper.classList.remove('skeleton-loader');
                imgWrapper.appendChild(elImg);
                
                // Caption overlay (Instagram / Netflix cover style)
                const caption = document.createElement('div');
                caption.className = 'gallery-hero-overlay-caption premium-caption-anim';
                caption.innerHTML = `
                    <span class="text-[9px] font-bold tracking-[0.2em] uppercase text-pink-300 drop-shadow-md">Our Memory</span>
                    <p class="text-xs text-white/95 mt-1 font-medium font-serif italic drop-shadow-md">A beautiful moment captured in time ✨</p>
                `;
                imgWrapper.appendChild(caption);
                
                imgWrapper.onclick = () => openPhotoModal(baseUrl + img.image_path);
                container.appendChild(imgWrapper);
                wrapper.appendChild(container);
            } else if (style === 'split') {
                const card = document.createElement('div');
                card.className = 'gallery-split-card skeleton-loader cursor-pointer';
                card.style.animationDelay = `${i * 0.3}s`;
                
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover premium-photo-anim');
                elImg.onload = () => card.classList.remove('skeleton-loader');
                card.appendChild(elImg);
                
                // Glassmorphic title strip
                const info = document.createElement('div');
                info.className = 'absolute bottom-0 inset-x-0 bg-black/55 backdrop-blur-[3px] py-2 text-center text-[10px] text-white/90 font-mono tracking-wider z-20';
                info.textContent = `Memory #${i + 1} ✨`;
                card.appendChild(info);
                
                card.onclick = () => openPhotoModal(baseUrl + img.image_path);
                wrapper.appendChild(card);
            } else if (style === 'collage') {
                const card = document.createElement('div');
                const spanClass = (pageImages.length === 3 && i === 0) ? ' span-two' : '';
                card.className = 'gallery-collage-card skeleton-loader cursor-pointer' + spanClass;
                card.style.animationDelay = `${i * 0.2}s`;
                
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover premium-photo-anim');
                elImg.onload = () => card.classList.remove('skeleton-loader');
                card.appendChild(elImg);
                
                card.onclick = () => openPhotoModal(baseUrl + img.image_path);
                wrapper.appendChild(card);
            } else if (style === 'floating' || style === 'birthday') {
                const card = document.createElement('div');
                card.className = 'float-memory-img absolute rounded-3xl overflow-hidden shadow-lg border border-white/10 skeleton-loader cursor-pointer';
                const isDesktop = window.innerWidth > 768;
                const scale = isDesktop ? 1.65 : 1.0;
                const left = (i % 2 === 0) ? (5 + Math.random() * 20) : (50 + Math.random() * 25);
                const top = isDesktop ? ((i * 110 + Math.random() * 25) % 320) : ((i * 75 + Math.random() * 20) % 230);
                const size = Math.round((150 + (i % 3) * 25) * scale);
                card.style.left = `${left}%`;
                card.style.top = `${top}px`;
                card.style.width = `${size}px`;
                card.style.height = `${size}px`;
                card.style.setProperty('--dur', `${6 + Math.random() * 4}s`);
                card.style.setProperty('--delay', `${-i * 1.5}s`);
                
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover');
                elImg.onload = () => card.classList.remove('skeleton-loader');
                card.appendChild(elImg);
                
                card.onclick = () => openPhotoModal(baseUrl + img.image_path);
                wrapper.appendChild(card);
            } else if (style === 'polaroid' || style === 'friendship') {
                const card = document.createElement('div');
                const isFriendship = (style === 'friendship');
                card.className = (isFriendship ? 'friendship-photo-card' : 'polaroid-drop-card') + ' absolute bg-white p-3 pb-6 shadow-xl border border-gray-250 rounded-sm cursor-pointer flex flex-col items-center';
                const isDesktop = window.innerWidth > 768;
                const scale = isDesktop ? 1.65 : 1.0;
                const cardWidth = Math.round(224 * scale);
                const imgHeight = Math.round(176 * scale);
                card.style.width = `${cardWidth}px`;
                
                const rot = (i % 2 === 0 ? 1 : -1) * (i * 4 + 2);
                card.style.setProperty('--rot', `${rot}deg`);
                card.style.zIndex = 10 + i;
                
                const imgContainer = document.createElement('div');
                imgContainer.className = 'w-full bg-gray-100 skeleton-loader rounded-sm overflow-hidden mb-2';
                imgContainer.style.height = `${imgHeight}px`;
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover');
                elImg.onload = () => imgContainer.classList.remove('skeleton-loader');
                imgContainer.appendChild(elImg);
                card.appendChild(imgContainer);
                
                const caption = document.createElement('p');
                caption.className = 'text-[10px] text-gray-700 font-mono text-center truncate w-full';
                caption.textContent = isFriendship ? ['Besties 💖', 'Fun Times 📸', 'Memories 🌟', 'Forever Friends 🤝'][i % 4] : `Memory #${i + 1}`;
                card.appendChild(caption);
                
                if (isFriendship) {
                    const sticker = document.createElement('div');
                    sticker.className = 'friendship-sticker absolute -top-2 -right-2 text-[9px] font-bold text-white uppercase px-1.5 py-0.5 rounded shadow';
                    const stickerColors = ['bg-pink-500', 'bg-purple-500', 'bg-blue-500', 'bg-yellow-500'];
                    sticker.classList.add(stickerColors[i % stickerColors.length]);
                    sticker.textContent = ['LOVE', 'SQUAD', 'FUN', 'FOREVER'][i % 4];
                    sticker.style.setProperty('--sticker-rot', `${(Math.random() - 0.5) * 20}deg`);
                    card.appendChild(sticker);
                    
                    card.onclick = (e) => {
                        e.stopPropagation();
                        card.classList.add('thrown');
                        setTimeout(() => {
                            card.classList.remove('thrown');
                            wrapper.insertBefore(card, wrapper.firstChild);
                            Array.from(wrapper.children).forEach((c, idx) => {
                                c.style.zIndex = 10 + idx;
                            });
                        }, 500);
                    };
                } else {
                    card.onclick = () => {
                        let maxZ = 10 + pageImages.length;
                        Array.from(wrapper.children).forEach(c => {
                            const z = parseInt(c.style.zIndex) || 0;
                            if (z > maxZ) maxZ = z;
                        });
                        card.style.zIndex = maxZ + 1;
                        card.style.transform = `translateY(0) scale(1.05) rotate(${(Math.random() - 0.5) * 8}deg)`;
                        setTimeout(() => {
                            card.style.transform = `translateY(0) scale(1) rotate(${(Math.random() - 0.5) * 8}deg)`;
                        }, 200);
                    };
                }
                
                wrapper.appendChild(card);
                
                if (!isFriendship) {
                    setTimeout(() => {
                        card.classList.add('dropped');
                    }, i * 250 + 100);
                }
            } else if (style === 'scrapbook') {
                const card = document.createElement('div');
                card.className = 'scrapbook-card absolute bg-stone-900 border border-amber-400/30 p-2 pb-5 shadow-lg rounded-sm cursor-pointer overflow-hidden';
                const isDesktop = window.innerWidth > 768;
                const scale = isDesktop ? 1.65 : 1.0;
                
                const rot = (i % 2 === 0 ? 1 : -1) * (Math.random() * 8 + 2);
                card.style.setProperty('--rot', `${rot}deg`);
                card.style.zIndex = 10 + i;
                
                const left = (i % 2 === 0) ? (5 + Math.random() * 10) : (48 + Math.random() * 10);
                const top = isDesktop ? ((i * 130 + Math.random() * 20) % 330) : ((i * 90 + Math.random() * 15) % 240);
                const size = Math.round((170 + (i % 2) * 30) * scale);
                card.style.left = `${left}%`;
                card.style.top = `${top}px`;
                card.style.width = `${size}px`;
                
                const imgContainer = document.createElement('div');
                imgContainer.className = 'w-full aspect-square skeleton-loader rounded-sm overflow-hidden';
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover');
                elImg.onload = () => imgContainer.classList.remove('skeleton-loader');
                imgContainer.appendChild(elImg);
                card.appendChild(imgContainer);
                
                const tape = document.createElement('div');
                tape.className = 'scrapbook-tape absolute w-14 h-4 bg-yellow-100/50 z-20';
                tape.style.top = '-8px';
                tape.style.left = `${20 + Math.random() * 20}%`;
                tape.style.setProperty('--tape-rot', `${(Math.random() - 0.5) * 30}deg`);
                card.appendChild(tape);
                
                card.onclick = () => openPhotoModal(baseUrl + img.image_path);
                wrapper.appendChild(card);
                
                setTimeout(() => {
                    card.classList.add('revealed');
                }, i * 250 + 100);
            } else if (style === 'cinematic') {
                const elImg = createResponsiveImage(img, 'cinematic-carousel-img absolute inset-0 w-full h-full object-cover ' + (i === 0 ? 'active' : ''));
                wrapper.appendChild(elImg);
                
                if (extra.dotsContainer) {
                    const dot = document.createElement('span');
                    dot.className = 'w-2.5 h-2.5 rounded-full cursor-pointer transition-colors duration-300 ' + (i === 0 ? 'bg-pink-500' : 'bg-slate-500/30');
                    dot.onclick = () => {
                        // Clear active states in JS slideshow
                        const imgs = wrapper.querySelectorAll('.cinematic-carousel-img');
                        const dt = extra.dotsContainer.querySelectorAll('span');
                        imgs.forEach((im, index) => {
                            im.classList.remove('active');
                            if (dt[index]) dt[index].className = 'w-2.5 h-2.5 rounded-full cursor-pointer transition-colors duration-300 bg-slate-500/30';
                        });
                        if (imgs[i]) imgs[i].classList.add('active');
                        dot.className = 'w-2.5 h-2.5 rounded-full cursor-pointer transition-colors duration-300 bg-pink-500';
                    };
                    extra.dotsContainer.appendChild(dot);
                }
            } else if (style === 'wall' || style === 'proposal') {
                const card = document.createElement('div');
                card.className = (style === 'proposal' ? 'proposal-photo-card aspect-square bg-rose-50 p-1 border-2 border-rose-200 rounded-3xl shadow-lg cursor-pointer overflow-hidden' : 'wall-card aspect-square skeleton-loader rounded-2xl overflow-hidden shadow border cursor-pointer ' + (isDark ? 'border-white/10' : 'border-slate-200'));
                card.onclick = () => openPhotoModal(baseUrl + img.image_path);
                
                const imgContainer = document.createElement('div');
                imgContainer.className = style === 'proposal' ? 'w-full h-full rounded-2xl skeleton-loader overflow-hidden' : 'w-full h-full';
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover');
                elImg.onload = () => imgContainer.classList.remove('skeleton-loader');
                imgContainer.appendChild(elImg);
                card.appendChild(imgContainer);
                
                wrapper.appendChild(card);
                
                setTimeout(() => {
                    card.classList.add('revealed');
                }, i * 150 + 100);
            } else if (style === 'sorry' || style === 'patchup') {
                const card = document.createElement('div');
                card.className = 'sorry-photo-card absolute inset-0 w-full h-full cursor-pointer ' + (i === 0 ? 'block' : 'hidden');
                card.onclick = () => openPhotoModal(baseUrl + img.image_path);
                
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover');
                card.appendChild(elImg);
                wrapper.appendChild(card);
                
                setTimeout(() => {
                    card.classList.add('wiped');
                }, i * 400 + 100);
            } else if (style === 'anniversary') {
                const row = document.createElement('div');
                row.className = 'flex items-center w-full mb-8 relative z-10';
                const isLeft = (i % 2 === 0);
                row.classList.add(isLeft ? 'justify-start' : 'justify-end');
                
                const node = document.createElement('div');
                node.className = 'timeline-node absolute left-1/2 -translate-x-1/2 w-4 h-4 rounded-full border-4 bg-amber-400 border-stone-900';
                row.appendChild(node);
                
                const card = document.createElement('div');
                card.className = (isLeft ? 'timeline-photo-left' : 'timeline-photo-right') + ' w-[45%] bg-stone-900 border-2 border-amber-400/50 p-1.5 rounded-2xl shadow-xl cursor-pointer overflow-hidden gold-glow';
                card.onclick = () => openPhotoModal(baseUrl + img.image_path);
                
                const imgContainer = document.createElement('div');
                imgContainer.className = 'w-full aspect-square skeleton-loader rounded-xl overflow-hidden';
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover');
                elImg.onload = () => imgContainer.classList.remove('skeleton-loader');
                imgContainer.appendChild(elImg);
                card.appendChild(imgContainer);
                
                row.appendChild(card);
                wrapper.appendChild(row);
                
                setTimeout(() => {
                    node.classList.add('revealed');
                    card.classList.add('revealed');
                    if (extra.line) extra.line.classList.add('visible');
                }, i * 350 + 150);
            } else if (style === 'miss_you') {
                const node = document.createElement('div');
                node.className = 'absolute flex flex-col items-center justify-center';
                const isDesktop = window.innerWidth > 768;
                const scale = isDesktop ? 1.65 : 1.0;
                const left = (i % 2 === 0) ? (5 + Math.random() * 15) : (50 + Math.random() * 18);
                const top = isDesktop ? ((i * 120 + Math.random() * 20) % 320 + 30) : ((i * 85 + Math.random() * 15) % 230 + 30);
                node.style.left = `${left}%`;
                node.style.top = `${top}px`;
                
                const star = document.createElement('div');
                star.className = 'missyou-star text-3xl mb-2';
                star.textContent = '⭐';
                node.appendChild(star);
                
                const size = Math.round(140 * scale);
                const card = document.createElement('div');
                card.className = 'missyou-photo-card hidden bg-stone-900 border border-yellow-350/40 p-1 rounded-2xl shadow-2xl cursor-pointer overflow-hidden';
                card.style.width = `${size}px`;
                card.style.height = `${size}px`;
                card.onclick = () => openPhotoModal(baseUrl + img.image_path);
                
                const elImg = createResponsiveImage(img, 'w-full h-full object-cover rounded-xl');
                card.appendChild(elImg);
                node.appendChild(card);
                
                star.onclick = () => {
                    star.classList.add('hidden');
                    card.classList.remove('hidden');
                    setTimeout(() => {
                        card.classList.add('revealed');
                    }, 50);
                };
                wrapper.appendChild(node);
            }
        }

        // ═══ TYPEWRITER EFFECTS ═══
        function typewriteTitle(el, text) {
            el.textContent = '';
            let i = 0;
            function type() {
                if (i < text.length) {
                    el.textContent += text.charAt(i);
                    i++;
                    setTimeout(type, 60);
                }
            }
            setTimeout(type, 400);
        }

        function runTypewriter(el, text, onComplete) {
            const TYPEWRITER_SPEED = 18; // ms per char (faster than 25ms)
            const MAX_TYPEWRITER_CHARS = 800; // instant show if longer

            if (text.length > MAX_TYPEWRITER_CHARS) {
                el.textContent = text;
                el.classList.remove('typewriter-cursor');
                const parent = el.closest('.overflow-y-auto');
                if (parent) parent.scrollTop = parent.scrollHeight;
                if (onComplete) onComplete();
                return;
            }

            el.textContent = '';
            let i = 0;
            function type() {
                if (i < text.length) {
                    el.textContent += text.charAt(i);
                    i++;
                    // Auto-scroll the parent container
                    const parent = el.closest('.overflow-y-auto');
                    if (parent) parent.scrollTop = parent.scrollHeight;
                    setTimeout(type, TYPEWRITER_SPEED);
                } else {
                    el.classList.remove('typewriter-cursor');
                    if (onComplete) onComplete();
                }
            }
            type();
        }

        // ═══ GALLERY NAVIGATION ═══
        function galleryNext(slideNum) {
            if (!galleryIndexes[slideNum]) galleryIndexes[slideNum] = 0;
            galleryIndexes[slideNum] = (galleryIndexes[slideNum] + 1) % totalImages;
            showGalleryImage(slideNum);
        }
        function galleryPrev(slideNum) {
            if (!galleryIndexes[slideNum]) galleryIndexes[slideNum] = 0;
            galleryIndexes[slideNum] = (galleryIndexes[slideNum] - 1 + totalImages) % totalImages;
            showGalleryImage(slideNum);
        }
        function showGalleryImage(slideNum) {
            const idx = galleryIndexes[slideNum] || 0;
            document.querySelectorAll(`img[data-gallery="${slideNum}"]`).forEach((img, i) => {
                img.classList.toggle('opacity-100', i === idx);
                img.classList.toggle('opacity-0', i !== idx);
            });
            document.querySelectorAll(`span.gallery-dot[data-gallery="${slideNum}"]`).forEach((dot, i) => {
                if (i === idx) {
                    dot.style.backgroundColor = accentColor;
                } else {
                    dot.style.backgroundColor = isDark ? 'rgba(255,255,255,0.3)' : '#9ca3af';
                }
            });
        }

        // ═══ COUNTER ═══
        function renderCounter(container, dateStr) {
            const start = new Date(dateStr);
            const now = new Date();
            const diff = Math.max(0, Math.floor((now - start) / (1000 * 60 * 60 * 24)));
            const digits = String(diff).padStart(4, '0').split('');
            container.innerHTML = digits.map(d => `<div class="counter-digit">${d}</div>`).join('');
        }

        // ═══ 3-2-1 COUNTDOWN ═══
        function run321Countdown(el, slideNum) {
            const nums = ['3', '2', '1', '🎉'];
            let i = 0;
            el.style.opacity = '1';
            function show() {
                if (i < nums.length) {
                    el.textContent = nums[i];
                    el.style.animation = 'none';
                    el.offsetHeight; // reflow
                    el.style.animation = 'countPulse 0.8s ease-out forwards';
                    i++;
                    setTimeout(show, 1000);
                } else {
                    setTimeout(() => goSlide(slideNum + 1), 500);
                }
            }
            setTimeout(show, 500);
        }

        // ═══ CAKE BLOWOUT ═══
        function blowCandle(slideNum) {
            const flame = document.getElementById(`candle-flame-${slideNum}`);
            if (flame) {
                flame.style.transition = 'all 0.3s';
                flame.style.opacity = '0';
                flame.style.transform = 'scale(0)';
                setTimeout(() => flame.remove(), 300);
                sendReaction('amazing');
                confetti({ particleCount: 180, spread: 90, origin: { y: 0.5 } });
                setTimeout(() => {
                    confetti({ particleCount: 100, spread: 60, origin: { x: 0.2, y: 0.6 } });
                    confetti({ particleCount: 100, spread: 60, origin: { x: 0.8, y: 0.6 } });
                }, 400);
                setTimeout(() => goSlide(slideNum + 1), 1500);
            }
        }

        // ═══ GIFT BOX ═══
        function openGift(slideNum) {
            const box = document.getElementById(`gift-box-${slideNum}`);
            const hint = document.getElementById(`gift-hint-${slideNum}`);
            if (box) {
                box.classList.remove('gift-shake');
                box.classList.add('gift-opened');
                box.innerHTML = '<div class="text-[120px] leading-none select-none">🎊</div>';
                if (hint) hint.textContent = 'Surprise!';
                confetti({ particleCount: 120, spread: 70, origin: { y: 0.5 } });
                setTimeout(() => goSlide(slideNum + 1), 1500);
            }
        }

        // ═══ RUNAWAY BUTTON (INTERACTIVE CHOICE) ═══
        document.querySelectorAll('.runaway-btn').forEach(noBtn => {
            const slideNum = noBtn.id.replace('no-btn-', '');
            const yesBtn = document.getElementById(`yes-btn-${slideNum}`);
            const promptEl = document.getElementById(`choice-prompt-${slideNum}`);
            let attempts = 0;
            const funnyPrompts = [
                "Are you sure? 🥺",
                "Think again! ❤️",
                "Please click Yes! 🙏",
                "Don't be mean! 😉",
                "Please reconsider! 🥺",
                "Yes is right there! 👉",
                "Come on! 💕",
                "You know you want to! 😊"
            ];

            const handleRunaway = (e) => {
                e.preventDefault();
                e.stopPropagation();
                attempts++;
                const container = noBtn.parentElement;
                const containerRect = container.getBoundingClientRect();
                const btnRect = noBtn.getBoundingClientRect();
                const maxX = containerRect.width ? Math.max(10, (containerRect.width - btnRect.width) / 2 - 10) : 80;
                const maxY = containerRect.height ? Math.max(10, (containerRect.height - btnRect.height) / 2 - 10) : 40;
                const rx = (Math.random() - 0.5) * 2 * maxX;
                const ry = (Math.random() - 0.5) * 2 * maxY;
                noBtn.style.transform = `translate(${rx}px, ${ry}px)`;
                if (promptEl) promptEl.textContent = funnyPrompts[attempts % funnyPrompts.length];
                if (yesBtn) yesBtn.style.transform = `scale(${1 + attempts * 0.12})`;
                if (attempts >= 7) noBtn.classList.add('hidden');
            };

            noBtn.addEventListener('mouseover', handleRunaway);
            noBtn.addEventListener('touchstart', handleRunaway);
            noBtn.addEventListener('click', handleRunaway);
        });

        // ═══ YES HANDLER ═══
        function handleYes(slideNum) {
            sendReaction('loved');
            confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
            setTimeout(() => confetti({ particleCount: 80, spread: 60, origin: { x: 0.3, y: 0.5 } }), 200);
            setTimeout(() => confetti({ particleCount: 80, spread: 60, origin: { x: 0.7, y: 0.5 } }), 400);

            // Show success state
            const choiceContainer = document.getElementById(`choice-container-${slideNum}`);
            const successContainer = document.getElementById(`choice-success-${slideNum}`);
            if (choiceContainer) choiceContainer.classList.add('hidden');
            if (successContainer) {
                successContainer.classList.remove('hidden');
                successContainer.classList.add('flex');
                successContainer.style.animation = 'slideEnter 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards';
            }
        }

        // ═══ REACTIONS API ═══
        function sendReaction(type) {
            const formData = new FormData();
            formData.append('page_id', pageId);
            formData.append('type', type);
            fetch(baseUrl + 'api.php?action=react', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    confetti({ particleCount: 60, spread: 50, origin: { y: 0.8 } });
                }
            }).catch(() => {});
        }

        // ═══ REPLY SYSTEM JS ═══
        let currentReplyType = 'text';

        function setReplyType(type) {
            currentReplyType = type;
            document.getElementById('reply-type-input').value = type;

            // Reset buttons
            const types = ['text', 'voice', 'image', 'video'];
            types.forEach(t => {
                const btn = document.getElementById(`btn-reply-${t}`);
                if (btn) {
                    btn.className = 'py-2.5 rounded-xl text-xs font-bold transition border flex flex-col items-center justify-center gap-1 ';
                    if (t === type) {
                        btn.className += 'bg-pink-500/10 border-pink-500/30 text-pink-400';
                    } else {
                        btn.className += isDark 
                            ? 'bg-white/5 border-transparent text-slate-400 hover:bg-white/10' 
                            : 'bg-slate-100 border-transparent text-slate-600 hover:bg-slate-200';
                    }
                }
            });

            // Toggle input fields
            const textField = document.getElementById('field-reply-text');
            const fileField = document.getElementById('field-reply-file');
            const fileInput = document.getElementById('reply-file-input');

            // Reset file input
            if (fileInput) fileInput.value = '';
            document.getElementById('file-upload-text').textContent = 'Choose File (Max ' + (type === 'video' ? '50MB' : '10MB') + ')';

            if (type === 'text') {
                if (textField) textField.classList.remove('hidden');
                if (fileField) fileField.classList.add('hidden');
            } else {
                if (textField) textField.classList.add('hidden');
                if (fileField) fileField.classList.remove('hidden');
                
                // Customize label & max size
                const label = document.getElementById('file-label-text');
                const maxSize = document.getElementById('file-max-size');
                const icon = document.getElementById('file-upload-icon');

                if (type === 'voice') {
                    if (label) label.textContent = 'Voice Note File (MP3/WAV/OGG/AAC/ACC)';
                    if (maxSize) maxSize.textContent = '10MB';
                    if (icon) icon.textContent = '🎤';
                    if (fileInput) fileInput.accept = 'audio/*';
                } else if (type === 'image') {
                    if (label) label.textContent = 'Image File (JPG/PNG/WEBP)';
                    if (maxSize) maxSize.textContent = '10MB';
                    if (icon) icon.textContent = '🖼️';
                    if (fileInput) fileInput.accept = 'image/*';
                } else if (type === 'video') {
                    if (label) label.textContent = 'Video File (MP4/WEBM)';
                    if (maxSize) maxSize.textContent = '50MB';
                    if (icon) icon.textContent = '📹';
                    if (fileInput) fileInput.accept = 'video/*';
                }
            }
        }

        function fileSelected(input) {
            const uploadText = document.getElementById('file-upload-text');
            if (input.files && input.files[0]) {
                const name = input.files[0].name;
                const size = input.files[0].size;
                const limit = currentReplyType === 'video' ? 50 * 1024 * 1024 : 10 * 1024 * 1024;
                
                if (size > limit) {
                    alert('File exceeds the size limit!');
                    input.value = '';
                    uploadText.textContent = 'Choose File';
                    return;
                }
                uploadText.textContent = name;
            }
        }

        function submitReplyForm(e) {
            e.preventDefault();
            const form = document.getElementById('reply-form');
            const btn = document.getElementById('submit-reply-btn');
            const originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Sending Reply... ⏳';

            const formData = new FormData(form);

            fetch(baseUrl + 'api.php?action=submit_reply', {
                method: 'POST',
                body: formData
            })
            .then(r => r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Raw response:', text);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 300));
                }
            }))
            .then(data => {
                btn.disabled = false;
                btn.textContent = originalText;

                if (data.success) {
                    document.getElementById('reply-form-container').classList.add('hidden');
                    const success = document.getElementById('reply-success-container');
                    success.classList.remove('hidden');
                    success.classList.add('flex');
                    
                    // Confetti!
                    confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
                } else {
                    alert(data.error || 'Failed to submit reply. Please try again.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.textContent = originalText;
                alert(err.message || 'Network error. Please try again.');
            });
        }

        // ═══════════════════════════════════════════════════
        // SIGNATURE MOMENTS INTERACTION HANDLERS
        // ═══════════════════════════════════════════════════
        
        // Dynamic Retro Audio Synthesizer
        function playSystemSound(type) {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const audioCtx = new AudioCtx();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);

                if (type === 'pop') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(400, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(1200, audioCtx.currentTime + 0.15);
                    gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.15);
                } else if (type === 'cut') {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(150, audioCtx.currentTime);
                    osc.frequency.linearRampToValueAtTime(40, audioCtx.currentTime + 0.25);
                    gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.25);
                } else if (type === 'stamp') {
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(90, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(30, audioCtx.currentTime + 0.2);
                    gain.gain.setValueAtTime(0.25, audioCtx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.01, audioCtx.currentTime + 0.2);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.2);
                } else if (type === 'success') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(523.25, audioCtx.currentTime);
                    osc.frequency.setValueAtTime(659.25, audioCtx.currentTime + 0.1);
                    osc.frequency.setValueAtTime(783.99, audioCtx.currentTime + 0.2);
                    gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.35);
                } else if (type === 'shing') {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(1000, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(3500, audioCtx.currentTime + 0.35);
                    gain.gain.setValueAtTime(0.01, audioCtx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.15, audioCtx.currentTime + 0.05);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35);
                    
                    const osc2 = audioCtx.createOscillator();
                    const gain2 = audioCtx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(4000, audioCtx.currentTime);
                    osc2.connect(gain2);
                    gain2.connect(audioCtx.destination);
                    gain2.gain.setValueAtTime(0.04, audioCtx.currentTime);
                    gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.25);
                    
                    osc.start();
                    osc2.start();
                    osc.stop(audioCtx.currentTime + 0.35);
                    osc2.stop(audioCtx.currentTime + 0.25);
                } else if (type === 'ignite') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(600, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(180, audioCtx.currentTime + 0.08);
                    gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.005, audioCtx.currentTime + 0.08);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.08);
                } else if (type === 'petal') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(800, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(1400, audioCtx.currentTime + 0.3);
                    gain.gain.setValueAtTime(0.06, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.3);
                } else if (type === 'scribble') {
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(120, audioCtx.currentTime);
                    osc.frequency.linearRampToValueAtTime(80, audioCtx.currentTime + 0.1);
                    gain.gain.setValueAtTime(0.03, audioCtx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.1);
                } else if (type === 'sparkle') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(2000, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(4000, audioCtx.currentTime + 0.2);
                    gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
                    
                    const osc2 = audioCtx.createOscillator();
                    const gain2 = audioCtx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(3000, audioCtx.currentTime);
                    osc2.connect(gain2);
                    gain2.connect(audioCtx.destination);
                    gain2.gain.setValueAtTime(0.05, audioCtx.currentTime);
                    gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.15);
                    
                    osc.start();
                    osc2.start();
                    osc.stop(audioCtx.currentTime + 0.2);
                    osc2.stop(audioCtx.currentTime + 0.15);
                } else if (type === 'heartbeat') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(80, audioCtx.currentTime);
                    osc.frequency.linearRampToValueAtTime(60, audioCtx.currentTime + 0.25);
                    gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.25);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.25);
                    
                    const osc2 = audioCtx.createOscillator();
                    const gain2 = audioCtx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(70, audioCtx.currentTime + 0.12);
                    osc2.connect(gain2);
                    gain2.connect(audioCtx.destination);
                    gain2.gain.setValueAtTime(0.25, audioCtx.currentTime + 0.12);
                    gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35);
                    osc2.start(audioCtx.currentTime + 0.12);
                    osc2.stop(audioCtx.currentTime + 0.35);
                } else if (type === 'portal') {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(200, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(800, audioCtx.currentTime + 0.25);
                    osc.frequency.exponentialRampToValueAtTime(200, audioCtx.currentTime + 0.5);
                    gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.5);
                } else if (type === 'wax_crack') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(1500, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(300, audioCtx.currentTime + 0.15);
                    gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.15);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.15);
                    
                    const osc2 = audioCtx.createOscillator();
                    const gain2 = audioCtx.createGain();
                    osc2.type = 'triangle';
                    osc2.frequency.setValueAtTime(180, audioCtx.currentTime);
                    osc2.frequency.linearRampToValueAtTime(60, audioCtx.currentTime + 0.1);
                    osc2.connect(gain2);
                    gain2.connect(audioCtx.destination);
                    gain2.gain.setValueAtTime(0.4, audioCtx.currentTime);
                    gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.12);
                    osc2.start();
                    osc2.stop(audioCtx.currentTime + 0.12);
                } else if (type === 'paper_rustle') {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(350, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(120, audioCtx.currentTime + 0.3);
                    gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.04, audioCtx.currentTime + 0.1);
                    gain.gain.linearRampToValueAtTime(0.08, audioCtx.currentTime + 0.2);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.3);
                } else if (type === 'page_flip') {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(140, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(70, audioCtx.currentTime + 0.2);
                    gain.gain.setValueAtTime(0.25, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.2);
                } else if (type === 'siren_alert') {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(440, audioCtx.currentTime);
                    osc.frequency.setValueAtTime(550, audioCtx.currentTime + 0.15);
                    osc.frequency.setValueAtTime(440, audioCtx.currentTime + 0.3);
                    osc.frequency.setValueAtTime(550, audioCtx.currentTime + 0.45);
                    gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.6);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.6);
                } else if (type === 'charge_up') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(200, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(1000, audioCtx.currentTime + 1.2);
                    gain.gain.setValueAtTime(0.01, audioCtx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.15, audioCtx.currentTime + 0.3);
                    gain.gain.linearRampToValueAtTime(0.08, audioCtx.currentTime + 0.8);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 1.2);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 1.2);
                } else if (type === 'lock_click') {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(150, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(30, audioCtx.currentTime + 0.12);
                    gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.12);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.12);
                    
                    const osc2 = audioCtx.createOscillator();
                    const gain2 = audioCtx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(1800, audioCtx.currentTime);
                    osc2.frequency.exponentialRampToValueAtTime(600, audioCtx.currentTime + 0.05);
                    osc2.connect(gain2);
                    gain2.connect(audioCtx.destination);
                    gain2.gain.setValueAtTime(0.15, audioCtx.currentTime);
                    gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.05);
                    osc2.start();
                    osc2.stop(audioCtx.currentTime + 0.05);
                } else if (type === 'gift_unwrap') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(800, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(1600, audioCtx.currentTime + 0.4);
                    gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.4);
                    
                    const osc2 = audioCtx.createOscillator();
                    const gain2 = audioCtx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(1200, audioCtx.currentTime + 0.1);
                    osc2.frequency.exponentialRampToValueAtTime(2400, audioCtx.currentTime + 0.4);
                    osc2.connect(gain2);
                    gain2.connect(audioCtx.destination);
                    gain2.gain.setValueAtTime(0.08, audioCtx.currentTime + 0.1);
                    gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
                    osc2.start(audioCtx.currentTime + 0.1);
                    osc2.stop(audioCtx.currentTime + 0.4);
                }
            } catch(e) {
                console.log("Audio synthesis blocked or not supported", e);
            }
        }

        // 1. Balloon Pop
        let poppedBalloons = {};
        function popBalloon(el, slideNum, message) {
            if (el.classList.contains('popped')) return;
            el.classList.add('popped');
            el.style.transform = 'scale(0)';
            el.style.opacity = '0';
            playSystemSound('pop');
            
            if (!poppedBalloons[slideNum]) poppedBalloons[slideNum] = 0;
            poppedBalloons[slideNum]++;
            
            const msgContainer = document.getElementById(`balloon-message-${slideNum}`);
            const msgText = document.getElementById(`balloon-msg-text-${slideNum}`);
            msgText.textContent = message;
            msgContainer.classList.remove('hidden');
            
            const rect = el.getBoundingClientRect();
            confetti({ particleCount: 30, spread: 40, origin: { x: rect.left / window.innerWidth, y: rect.top / window.innerHeight } });
            
            const total = 5;
            const btn = document.getElementById(`balloon-btn-${slideNum}`);
            btn.textContent = `Pop all balloons to continue (${poppedBalloons[slideNum]}/${total})`;
            if (poppedBalloons[slideNum] >= total) {
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }
        }

        // 2. Blow Candles
        let candlesBlown = {};
        function blowCandleTrigger(slideNum) {
            if (candlesBlown[slideNum]) return;
            candlesBlown[slideNum] = true;
            
            for (let f = 1; f <= 3; f++) {
                const flame = document.getElementById(`flame-${f}-${slideNum}`);
                if (flame) {
                    flame.style.transition = 'all 0.4s';
                    flame.style.opacity = '0';
                    flame.style.transform = 'scale(0)';
                }
            }
            
            const room = document.getElementById('story-body');
            room.style.transition = 'background 1.5s';
            room.style.backgroundColor = '#0c0817';
            
            playSystemSound('success');
            confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
            
            const blowBtn = document.getElementById(`blow-btn-${slideNum}`);
            if (blowBtn) {
                blowBtn.textContent = '💨 Blown Out! 🎉';
                blowBtn.disabled = true;
            }
            
            const btn = document.getElementById(`blow-continue-${slideNum}`);
            btn.classList.remove('opacity-50', 'pointer-events-none');
            btn.textContent = 'Continue →';
        }

        function requestMicBlow(slideNum) {
            const btn = document.getElementById(`mic-btn-${slideNum}`);
            btn.textContent = '🎙️ Requesting permission...';
            navigator.mediaDevices.getUserMedia({ audio: true, video: false })
            .then(stream => {
                btn.textContent = '🎙️ Listening for blow...';
                btn.classList.add('bg-green-600');
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const analyser = audioContext.createAnalyser();
                const microphone = audioContext.createMediaStreamSource(stream);
                const javascriptNode = audioContext.createScriptProcessor(2048, 1, 1);

                analyser.smoothingTimeConstant = 0.8;
                analyser.fftSize = 1024;

                microphone.connect(analyser);
                analyser.connect(javascriptNode);
                javascriptNode.connect(audioContext.destination);

                javascriptNode.onaudioprocess = function() {
                    const array = new Uint8Array(analyser.frequencyBinCount);
                    analyser.getByteFrequencyData(array);
                    let values = 0;
                    for (let i = 0; i < array.length; i++) {
                        values += (array[i]);
                    }
                    const average = values / array.length;
                    if (average > 55) {
                        blowCandleTrigger(slideNum);
                        stream.getTracks().forEach(track => track.stop());
                        audioContext.close();
                    }
                };
            })
            .catch(err => {
                btn.textContent = '🎙️ Mic not supported or denied';
            });
        }

        // 3. Cinematic Cake Cutting Sequence
        let originalMusicVolume = 1.0;
        let cakeCinematicRunning = {};
        let balloonTimer = {};
        let fireworksActive = {};

        function runCakeCinematic(slideNum) {
            if (cakeCinematicRunning[slideNum]) return;
            cakeCinematicRunning[slideNum] = true;

            const container = document.getElementById(`cake-cinematic-container-${slideNum}`);
            const spotlight = document.getElementById(`cinematic-spotlight-${slideNum}`);
            const viewport = document.getElementById(`cinematic-viewport-${slideNum}`);
            const titleEl = document.getElementById(`cinematic-title-${slideNum}`);
            const subtitleEl = document.getElementById(`cinematic-subtitle-${slideNum}`);
            const hintEl = document.getElementById(`cinematic-hint-${slideNum}`);
            const storyAudio = document.getElementById('story-audio');

            if (!container) return;

            // Make container interactive & visible
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            // Scene 1: Dark Introduction (3 seconds)
            if (storyAudio) {
                originalMusicVolume = storyAudio.volume || 1.0;
                let vol = originalMusicVolume;
                const volFade = setInterval(() => {
                    vol = Math.max(0.08, vol - 0.05);
                    storyAudio.volume = vol;
                    if (vol <= 0.08) clearInterval(volFade);
                }, 100);
            }

            setTimeout(() => {
                if (spotlight) spotlight.classList.remove('opacity-0');
                if (spotlight) spotlight.classList.add('opacity-100');
            }, 500);

            // Wait 3 seconds, then start Scene 2
            setTimeout(() => {
                // Scene 2: Cake Arrival (4 seconds)
                if (viewport) {
                    viewport.classList.remove('opacity-0', 'translate-y-24', 'scale-90');
                    viewport.classList.add('opacity-100', 'translate-y-0', 'scale-100');
                }

                // Zoom camera slightly
                const cakeWrapper = document.getElementById(`cinematic-cake-wrapper-${slideNum}`);
                if (cakeWrapper) {
                    cakeWrapper.style.transform = 'scale(1.08)';
                }

                // Candles light up one by one
                const candleDelays = [800, 1400, 2000, 2600, 3200];
                candleDelays.forEach((delay, idx) => {
                    setTimeout(() => {
                        const candleG = document.getElementById(`svg-candle-${idx + 1}-${slideNum}`);
                        const flame = document.getElementById(`svg-flame-${idx + 1}-${slideNum}`);
                        if (candleG) candleG.style.opacity = '1';
                        playSystemSound('ignite');
                        if (flame) {
                            flame.style.transition = 'all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
                            flame.style.opacity = '1';
                            flame.style.transform = 'scale(1)';
                            spawnSparkParticles(flame, idx + 1, container);
                        }
                    }, delay);
                });

                // Wait 4 seconds for arrival, then start Scene 3
                setTimeout(() => {
                    // Scene 3: Birthday Message
                    if (subtitleEl) {
                        subtitleEl.classList.remove('opacity-0', 'transform', '-translate-y-4');
                        subtitleEl.classList.add('opacity-100', 'translate-y-0');
                    }

                    const messageText = `Happy Birthday ${receiverName} ❤️`;
                    typewriteTitle(titleEl, messageText);

                    // Wait for message typing to finish (~1.8s), then start Scene 4
                    setTimeout(() => {
                        // Scene 4: Interactive Prompt
                        if (hintEl) {
                            hintEl.textContent = "Tap the cake to cut it 🎂";
                            hintEl.classList.remove('opacity-0');
                            hintEl.classList.add('opacity-100');
                        }

                        if (cakeWrapper) {
                            cakeWrapper.style.cursor = 'pointer';
                            cakeWrapper.classList.add('animate-cake-bounce');
                            
                            cakeWrapper.onclick = () => {
                                cakeWrapper.onclick = null;
                                revealKnife(slideNum);
                            };
                        }
                    }, 1800);

                }, 4000);

            }, 3000);
        }

        function spawnSparkParticles(flameEl, candleIdx, container) {
            const rect = flameEl.getBoundingClientRect();
            const parentRect = container.getBoundingClientRect();
            const flameX = rect.left - parentRect.left + rect.width / 2;
            const flameY = rect.top - parentRect.top + rect.height / 2;

            for (let i = 0; i < 5; i++) {
                const spark = document.createElement('div');
                spark.className = 'absolute w-1.5 h-1.5 bg-yellow-300 rounded-full pointer-events-none z-50';
                spark.style.left = `${flameX}px`;
                spark.style.top = `${flameY}px`;
                container.appendChild(spark);

                const angle = Math.random() * Math.PI * 2;
                const distance = 10 + Math.random() * 25;
                const tx = Math.cos(angle) * distance;
                const ty = Math.sin(angle) * distance - 15;

                spark.animate([
                    { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                    { transform: `translate(${tx}px, ${ty}px) scale(0)`, opacity: 0 }
                ], {
                    duration: 600 + Math.random() * 400,
                    easing: 'ease-out',
                    fill: 'forwards'
                });

                setTimeout(() => spark.remove(), 1000);
            }
        }

        function revealKnife(slideNum) {
            const cakeWrapper = document.getElementById(`cinematic-cake-wrapper-${slideNum}`);
            const hintEl = document.getElementById(`cinematic-hint-${slideNum}`);
            const knife = document.getElementById(`cinematic-knife-${slideNum}`);
            const dragIndicator = document.getElementById(`cinematic-drag-indicator-${slideNum}`);
            const dragOverlay = document.getElementById(`cinematic-drag-overlay-${slideNum}`);

            if (cakeWrapper) {
                cakeWrapper.classList.remove('animate-cake-bounce');
                cakeWrapper.style.cursor = '';
            }

            if (hintEl) {
                hintEl.classList.remove('opacity-100');
                hintEl.classList.add('opacity-0');
            }

            // Scene 5: Knife Reveal
            playSystemSound('shing');
            if (knife) {
                knife.style.transition = 'all 1s cubic-bezier(0.16, 1, 0.3, 1)';
                knife.style.opacity = '1';
                knife.style.transform = 'translateX(0px) translateY(-110px) rotate(-45deg)';
            }

            setTimeout(() => {
                if (dragIndicator) dragIndicator.classList.remove('opacity-0');
                if (dragIndicator) dragIndicator.classList.add('opacity-100');
                if (hintEl) {
                    hintEl.textContent = "Drag the knife down to cut 🔪";
                    hintEl.classList.remove('opacity-0');
                    hintEl.classList.add('opacity-100');
                }

                if (dragOverlay) {
                    dragOverlay.classList.remove('hidden');
                    initKnifeDragging(slideNum);
                }
            }, 1000);
        }

        let isKnifeDragging = false;
        let knifeStartY = 0;
        let knifeCurrentY = -110;
        let lastSoundTime = 0;

        function initKnifeDragging(slideNum) {
            const overlay = document.getElementById(`cinematic-drag-overlay-${slideNum}`);
            const knife = document.getElementById(`cinematic-knife-${slideNum}`);
            const leftHalf = document.getElementById(`svg-cake-left-${slideNum}`);
            const rightHalf = document.getElementById(`svg-cake-right-${slideNum}`);
            const cutFaceLeft = document.getElementById(`svg-cut-face-left-${slideNum}`);
            const cutFaceRight = document.getElementById(`svg-cut-face-right-${slideNum}`);
            const container = document.getElementById(`cake-cinematic-container-${slideNum}`);

            const startY = -110;
            const endY = 80;
            const range = endY - startY;

            function onPointerDown(e) {
                isKnifeDragging = true;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                knifeStartY = clientY - (knifeCurrentY - startY);
                document.getElementById(`cinematic-drag-indicator-${slideNum}`).classList.remove('opacity-100');
                document.getElementById(`cinematic-drag-indicator-${slideNum}`).classList.add('opacity-0');
            }

            function onPointerMove(e) {
                if (!isKnifeDragging) return;
                if (e.cancelable) e.preventDefault();
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                let currentY = clientY - knifeStartY + startY;
                
                currentY = Math.max(startY, Math.min(endY, currentY));
                knifeCurrentY = currentY;

                const progress = (currentY - startY) / range;

                if (knife) {
                    knife.style.transition = 'none';
                    knife.style.transform = `translateX(0px) translateY(${currentY}px) rotate(-45deg)`;
                }

                const separateX = progress * 15;
                const rotDeg = progress * 1.5;
                if (leftHalf) leftHalf.style.transform = `translateX(-${separateX}px) rotate(-${rotDeg}deg)`;
                if (rightHalf) rightHalf.style.transform = `translateX(${separateX}px) rotate(${rotDeg}deg)`;

                if (cutFaceLeft) cutFaceLeft.style.opacity = progress;
                if (cutFaceRight) cutFaceRight.style.opacity = progress;

                const knifeRect = knife.getBoundingClientRect();
                const containerRect = container.getBoundingClientRect();
                const tipX = knifeRect.left - containerRect.left + 25;
                const tipY = knifeRect.top - containerRect.top + 30;

                if (Math.random() < 0.35) {
                    spawnCreamParticle(tipX, tipY, container);
                }

                const now = Date.now();
                if (now - lastSoundTime > 120 && progress > 0.05 && progress < 0.95) {
                    playSystemSound('cut');
                    lastSoundTime = now;
                }
            }

            function onPointerUp() {
                if (!isKnifeDragging) return;
                isKnifeDragging = false;

                const progress = (knifeCurrentY - startY) / range;
                if (progress >= 0.85) {
                    overlay.classList.add('hidden');
                    overlay.removeEventListener('mousedown', onPointerDown);
                    overlay.removeEventListener('mousemove', onPointerMove);
                    overlay.removeEventListener('mouseup', onPointerUp);
                    overlay.removeEventListener('touchstart', onPointerDown);
                    overlay.removeEventListener('touchmove', onPointerMove);
                    overlay.removeEventListener('touchend', onPointerUp);
                    
                    splitCakeFully(slideNum);
                } else {
                    if (knife) {
                        knife.style.transition = 'all 0.5s ease-out';
                        knifeCurrentY = startY;
                        knife.style.transform = `translateX(0px) translateY(${startY}px) rotate(-45deg)`;
                    }
                    if (leftHalf) {
                        leftHalf.style.transition = 'all 0.5s ease-out';
                        leftHalf.style.transform = `translateX(0px) rotate(0deg)`;
                    }
                    if (rightHalf) {
                        rightHalf.style.transition = 'all 0.5s ease-out';
                        rightHalf.style.transform = `translateX(0px) rotate(0deg)`;
                    }
                    if (cutFaceLeft) cutFaceLeft.style.opacity = 0;
                    if (cutFaceRight) cutFaceRight.style.opacity = 0;

                    setTimeout(() => {
                        if (leftHalf) leftHalf.style.transition = 'transform 1.5s cubic-bezier(0.25, 1, 0.5, 1)';
                        if (rightHalf) rightHalf.style.transition = 'transform 1.5s cubic-bezier(0.25, 1, 0.5, 1)';
                        document.getElementById(`cinematic-drag-indicator-${slideNum}`).classList.add('opacity-100');
                    }, 500);
                }
            }

            overlay.addEventListener('mousedown', onPointerDown);
            overlay.addEventListener('mousemove', onPointerMove);
            overlay.addEventListener('mouseup', onPointerUp);
            overlay.addEventListener('touchstart', onPointerDown);
            overlay.addEventListener('touchmove', onPointerMove, { passive: false });
            overlay.addEventListener('touchend', onPointerUp);
        }

        function spawnCreamParticle(x, y, container) {
            const particle = document.createElement('div');
            const colors = ['#ffffff', '#ffd1dc', '#fffdd0', '#ff007f'];
            const color = colors[Math.floor(Math.random() * colors.length)];
            const size = 4 + Math.random() * 5;
            
            particle.className = 'absolute rounded-full pointer-events-none z-50';
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            particle.style.backgroundColor = color;
            particle.style.left = `${x}px`;
            particle.style.top = `${y}px`;
            container.appendChild(particle);

            const tx = (Math.random() - 0.5) * 40;
            const ty = 20 + Math.random() * 40;

            particle.animate([
                { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                { transform: `translate(${tx}px, ${ty}px) scale(0)`, opacity: 0.1 }
            ], {
                duration: 600 + Math.random() * 400,
                easing: 'ease-out',
                fill: 'forwards'
            });

            setTimeout(() => particle.remove(), 1000);
        }

        function splitCakeFully(slideNum) {
            const knife = document.getElementById(`cinematic-knife-${slideNum}`);
            const leftHalf = document.getElementById(`svg-cake-left-${slideNum}`);
            const rightHalf = document.getElementById(`svg-cake-right-${slideNum}`);
            const cutFaceLeft = document.getElementById(`svg-cut-face-left-${slideNum}`);
            const cutFaceRight = document.getElementById(`svg-cut-face-right-${slideNum}`);
            const viewport = document.getElementById(`cinematic-viewport-${slideNum}`);
            const hintEl = document.getElementById(`cinematic-hint-${slideNum}`);
            const storyAudio = document.getElementById('story-audio');

            if (hintEl) hintEl.classList.add('opacity-0');

            // Scene 7: Cake Split
            if (knife) {
                knife.style.transition = 'all 0.5s ease-out';
                knife.style.opacity = '0';
                knife.style.transform = 'translateY(180px) rotate(-45deg)';
            }

            if (leftHalf) {
                leftHalf.style.transition = 'transform 1.8s cubic-bezier(0.16, 1, 0.3, 1)';
                leftHalf.style.transform = 'translateX(-70px) rotate(-5deg)';
            }
            if (rightHalf) {
                rightHalf.style.transition = 'transform 1.8s cubic-bezier(0.16, 1, 0.3, 1)';
                rightHalf.style.transform = 'translateX(70px) rotate(5deg)';
            }
            if (cutFaceLeft) cutFaceLeft.style.opacity = '1';
            if (cutFaceRight) cutFaceRight.style.opacity = '1';

            if (viewport) {
                viewport.style.transition = 'transform 2s cubic-bezier(0.16, 1, 0.3, 1)';
                viewport.style.transform = 'scale(1.22) translateY(-25px)';
            }

            // Wait 1 second for split to finish, then explode
            setTimeout(() => {
                // Scene 8: Celebration Explosion
                playSystemSound('chime');

                if (storyAudio) {
                    let vol = storyAudio.volume;
                    const volFadeUp = setInterval(() => {
                        vol = Math.min(1.0, vol + 0.1);
                        storyAudio.volume = vol;
                        if (vol >= 1.0) clearInterval(volFadeUp);
                    }, 100);
                }

                if (typeof confetti === 'function') {
                    confetti({ particleCount: 160, angle: 60, spread: 65, origin: { x: 0, y: 0.8 } });
                    confetti({ particleCount: 160, angle: 120, spread: 65, origin: { x: 1, y: 0.8 } });
                    
                    setTimeout(() => {
                        confetti({ particleCount: 60, spread: 60, origin: { x: 0.3, y: 0.5 } });
                        confetti({ particleCount: 60, spread: 60, origin: { x: 0.7, y: 0.5 } });
                    }, 500);
                }

                startCinematicFireworks(slideNum);
                startCinematicBalloons(slideNum);

                setTimeout(() => {
                    revealMemoryPhotos(slideNum);
                }, 1500);

            }, 1000);
        }

        function startCinematicFireworks(slideNum) {
            const canvas = document.getElementById(`cinematic-fireworks-${slideNum}`);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.clientWidth || canvas.offsetWidth || 375;
            canvas.height = canvas.clientHeight || canvas.offsetHeight || 667;
            
            fireworksActive[slideNum] = true;
            let particles = [];

            class Particle {
                constructor(x, y, color) {
                    this.x = x;
                    this.y = y;
                    this.color = color;
                    this.angle = Math.random() * Math.PI * 2;
                    this.speed = 1 + Math.random() * 6;
                    this.vx = Math.cos(this.angle) * this.speed;
                    this.vy = Math.sin(this.angle) * this.speed;
                    this.alpha = 1;
                    this.decay = 0.015 + Math.random() * 0.02;
                    this.radius = 1.5 + Math.random() * 2;
                }
                update() {
                    this.x += this.vx;
                    this.y += this.vy;
                    this.vy += 0.05;
                    this.alpha -= this.decay;
                }
                draw() {
                    ctx.save();
                    ctx.globalAlpha = this.alpha;
                    ctx.fillStyle = this.color;
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();
                }
            }

            function createExplosion(x, y) {
                const colors = ['#f43f5e', '#a855f7', '#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#ffffff'];
                const color = colors[Math.floor(Math.random() * colors.length)];
                for (let i = 0; i < 40; i++) {
                    particles.push(new Particle(x, y, color));
                }
            }

            let explosionTimer = setInterval(() => {
                if (!fireworksActive[slideNum]) return;
                const rx = Math.random() * canvas.width;
                const ry = Math.random() * (canvas.height * 0.5);
                createExplosion(rx, ry);
            }, 850);

            function animate() {
                if (!fireworksActive[slideNum]) return;
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                particles.forEach((p, idx) => {
                    p.update();
                    if (p.alpha <= 0) {
                        particles.splice(idx, 1);
                    } else {
                        p.draw();
                    }
                });

                requestAnimationFrame(animate);
            }
            animate();

            canvas.dataset.timerId = explosionTimer;
        }

        function stopCinematicFireworks(slideNum) {
            fireworksActive[slideNum] = false;
            const canvas = document.getElementById(`cinematic-fireworks-${slideNum}`);
            if (canvas && canvas.dataset.timerId) {
                clearInterval(parseInt(canvas.dataset.timerId));
            }
        }

        function startCinematicBalloons(slideNum) {
            const container = document.getElementById(`cinematic-balloons-${slideNum}`);
            if (!container) return;
            
            const balloonEmojis = ['🎈', '🎈', '🎈', '💖', '🎂', '🥳'];
            
            function spawn() {
                const b = document.createElement('div');
                const emoji = balloonEmojis[Math.floor(Math.random() * balloonEmojis.length)];
                
                b.className = 'absolute text-4xl select-none pointer-events-none z-20';
                b.textContent = emoji;
                b.style.bottom = '-60px';
                b.style.left = `${10 + Math.random() * 80}%`;
                
                container.appendChild(b);
                
                const duration = 5000 + Math.random() * 3000;
                const tx = (Math.random() - 0.5) * 150;
                
                b.animate([
                    { transform: 'translateY(0) rotate(0deg)' },
                    { transform: `translateY(-115vh) translateX(${tx}px) rotate(${(Math.random() - 0.5) * 45}deg)` }
                ], {
                    duration: duration,
                    easing: 'linear',
                    fill: 'forwards'
                });
                
                setTimeout(() => b.remove(), duration + 500);
            }
            
            for (let i = 0; i < 8; i++) {
                setTimeout(spawn, i * 200);
            }
            
            balloonTimer[slideNum] = setInterval(spawn, 600);
        }

        function revealMemoryPhotos(slideNum) {
            const memoriesContainer = document.getElementById(`cinematic-memories-${slideNum}`);
            if (!memoriesContainer || !pageImages || pageImages.length === 0) {
                showContinueButton(slideNum);
                return;
            }

            const total = pageImages.length;
            let count = 0;

            const targetPositions = [
                { left: '10%', top: '22%', rot: -12 },
                { left: '72%', top: '25%', rot: 15 },
                { left: '8%', top: '55%', rot: 8 },
                { left: '75%', top: '58%', rot: -10 },
                { left: '15%', top: '78%', rot: -6 },
                { left: '70%', top: '78%', rot: 12 },
                { left: '42%', top: '15%', rot: 5 },
                { left: '42%', top: '80%', rot: -5 }
            ];

            pageImages.forEach((img, idx) => {
                setTimeout(() => {
                    const card = document.createElement('div');
                    card.className = 'absolute bg-white p-2 pb-5 shadow-2xl border border-gray-250 rounded-sm cursor-pointer w-28 sm:w-32 flex flex-col items-center pointer-events-auto transform scale-0 opacity-0 transition-all duration-[1200ms] cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                    
                    card.style.left = '50%';
                    card.style.top = '60%';
                    card.style.transform = 'translate(-50%, -50%) scale(0.01) rotate(0deg)';
                    card.style.zIndex = 30 + idx;

                    const imgDiv = document.createElement('div');
                    imgDiv.className = 'w-full h-20 sm:h-24 bg-gray-150 rounded-sm overflow-hidden mb-1.5';
                    const elImg = document.createElement('img');
                    elImg.src = baseUrl + img.image_path;
                    elImg.className = 'w-full h-full object-cover';
                    imgDiv.appendChild(elImg);
                    card.appendChild(imgDiv);

                    const label = document.createElement('p');
                    label.className = 'text-[8px] font-mono text-gray-500 truncate w-full text-center';
                    label.textContent = `Memory #${idx + 1}`;
                    card.appendChild(label);

                    card.onclick = () => openPhotoModal(baseUrl + img.image_path);
                    memoriesContainer.appendChild(card);
                    card.offsetHeight;

                    const pos = targetPositions[idx % targetPositions.length];
                    card.style.left = pos.left;
                    card.style.top = pos.top;
                    card.style.transform = `translate(-50%, -50%) scale(1.0) rotate(${pos.rot}deg)`;
                    card.style.opacity = '1';

                    playSystemSound('pop');

                    count++;
                    if (count === total) {
                        setTimeout(() => {
                            showContinueButton(slideNum);
                        }, 1500);
                    }

                }, idx * 800);
            });
        }

        function showContinueButton(slideNum) {
            const btn = document.getElementById(`cinematic-continue-${slideNum}`);
            const hintEl = document.getElementById(`cinematic-hint-${slideNum}`);

            if (hintEl) {
                hintEl.textContent = "Happy Birthday! 🥳";
                hintEl.classList.remove('opacity-0');
                hintEl.classList.add('opacity-100');
            }

            if (btn) {
                btn.classList.remove('hidden');
                btn.offsetHeight;
                btn.style.transition = 'all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
                btn.style.opacity = '1';
                btn.style.transform = 'scale(1)';
            }
        }

        function finishCakeCinematic(slideNum) {
            const container = document.getElementById(`cake-cinematic-container-${slideNum}`);
            const storyAudio = document.getElementById('story-audio');

            stopCinematicFireworks(slideNum);
            if (balloonTimer[slideNum]) {
                clearInterval(balloonTimer[slideNum]);
            }

            if (storyAudio) {
                storyAudio.volume = originalMusicVolume;
            }

            if (container) {
                container.style.transition = 'opacity 0.8s ease-out';
                container.classList.remove('opacity-100');
                container.classList.add('opacity-0');
                
                setTimeout(() => {
                    container.remove();
                    goSlide(slideNum + 1);
                }, 800);
            } else {
                goSlide(slideNum + 1);
            }
        }

        // 4. Bloom Rose
        let roseBloomed = {};
        function bloomRoseTrigger(slideNum) {
            if (roseBloomed[slideNum]) return;
            roseBloomed[slideNum] = true;
            
            const petals = document.getElementById(`rose-petals-${slideNum}`);
            const hint = document.getElementById(`rose-hint-${slideNum}`);
            
            petals.style.transform = 'scale(1.4)';
            petals.querySelectorAll('.petal').forEach((petal, pi) => {
                petal.style.transform = `scale(1.3) rotate(${pi * 35 - 35}deg) translateY(-8px)`;
            });
            
            playSystemSound('success');
            confetti({ particleCount: 60, spread: 60, colors: ['#f43f5e', '#e11d48', '#be123c'] });
            if (hint) hint.textContent = 'Bloomed! 🌹';
            
            const btn = document.getElementById(`rose-continue-${slideNum}`);
            btn.classList.remove('opacity-50', 'pointer-events-none');
            btn.textContent = 'Continue →';
        }

        // 5. Snap Heart Piece
        let heartPieces = {};
        function snapHeartPiece(side, slideNum) {
            if (!heartPieces[slideNum]) heartPieces[slideNum] = { left: false, right: false };
            heartPieces[slideNum][side] = true;
            
            const el = document.getElementById(`heart-${side}-${slideNum}`);
            el.style.transform = side === 'left' ? 'translate(-30px, 0px) rotate(0deg)' : 'translate(20px, 0px) rotate(0deg)';
            playSystemSound('pop');
            
            if (heartPieces[slideNum].left && heartPieces[slideNum].right) {
                setTimeout(() => {
                    const leftEl = document.getElementById(`heart-left-${slideNum}`);
                    const rightEl = document.getElementById(`heart-right-${slideNum}`);
                    leftEl.style.opacity = '0';
                    rightEl.style.opacity = '0';
                    
                    const container = document.getElementById(`heart-assembly-${slideNum}`);
                    const heartTarget = container.querySelector('.heart-target');
                    heartTarget.innerHTML = '<span class="text-8xl animate-heartbeat text-rose-600 select-none z-10">❤️</span>';
                    
                    playSystemSound('success');
                    confetti({ particleCount: 80, spread: 50, colors: ['#f43f5e', '#ec4899'] });
                    
                    const hint = document.getElementById(`heart-hint-${slideNum}`);
                    if (hint) hint.textContent = 'Mended! 💖';
                    
                    const btn = document.getElementById(`heart-continue-${slideNum}`);
                    btn.classList.remove('opacity-50', 'pointer-events-none');
                    btn.textContent = 'Continue →';
                }, 600);
            }
        }

        // 6. Love Meter
        function updateLoveMeter(val, slideNum) {
            const pct = document.getElementById(`love-pct-${slideNum}`);
            pct.textContent = `${val}%`;
            
            const fill = document.getElementById(`heart-fill-clip-${slideNum}`);
            fill.style.clipPath = `inset(${100 - val}% 0px 0px 0px)`;
            
            if (val == 100) {
                playSystemSound('success');
                confetti({ particleCount: 100, spread: 70, colors: ['#f43f5e', '#ff007f'] });
                const btn = document.getElementById(`love-continue-${slideNum}`);
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }
        }
        function checkLoveMeter(val, slideNum) {
            if (val < 100) {
                document.getElementById(`love-slider-${slideNum}`).value = 0;
                updateLoveMeter(0, slideNum);
            }
        }

        // 7. Ring Box & Proposal Choices
        let ringBoxOpened = {};
        function openRingBox(slideNum) {
            if (ringBoxOpened[slideNum]) return;
            ringBoxOpened[slideNum] = true;
            
            const lid = document.getElementById(`ring-lid-${slideNum}`);
            const ring = document.getElementById(`ring-diamond-${slideNum}`);
            const hint = document.getElementById(`ring-hint-${slideNum}`);
            
            lid.style.transform = 'translateY(-20px) rotateX(-120deg)';
            ring.style.opacity = '1';
            ring.style.transform = 'translateY(-20px) scale(1.2)';
            
            playSystemSound('success');
            
            setTimeout(() => {
                document.getElementById(`ring-box-container-${slideNum}`).classList.add('hidden');
                document.getElementById(`ring-question-${slideNum}`).classList.remove('hidden');
                document.getElementById(`ring-question-${slideNum}`).classList.add('flex');
                initProposalRunaway(slideNum);
            }, 1200);
        }

        function initProposalRunaway(slideNum) {
            const noBtn = document.getElementById(`proposal-no-btn-${slideNum}`);
            const yesBtn = document.getElementById(`proposal-yes-btn-${slideNum}`);
            const promptEl = document.getElementById(`proposal-prompt-${slideNum}`);
            let attempts = 0;
            const funnyPrompts = [
                "Are you sure? 🥺",
                "Think again! ❤️",
                "Please click Yes! 🙏",
                "Don't be mean! 😉",
                "Please reconsider! 🥺",
                "Yes is right there! 👉",
                "Come on! 💕",
                "You know you want to! 😊"
            ];

            let lastRunawayTime = 0;
            const handleRunaway = (e) => {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                const now = Date.now();
                if (now - lastRunawayTime < 250) return;
                lastRunawayTime = now;

                attempts++;
                const container = noBtn.parentElement;
                const containerRect = container.getBoundingClientRect();
                const btnRect = noBtn.getBoundingClientRect();
                const maxX = containerRect.width ? Math.max(10, (containerRect.width - btnRect.width) / 2 - 10) : 80;
                const maxY = containerRect.height ? Math.max(10, (containerRect.height - btnRect.height) / 2 - 10) : 40;
                const rx = (Math.random() - 0.5) * 2 * maxX;
                const ry = (Math.random() - 0.5) * 2 * maxY;
                noBtn.style.transform = `translate(${rx}px, ${ry}px)`;
                if (promptEl) promptEl.textContent = funnyPrompts[attempts % funnyPrompts.length];
                if (yesBtn) yesBtn.style.transform = `scale(${1 + attempts * 0.12})`;
                if (attempts >= 7) noBtn.classList.add('hidden');
            };

            noBtn.addEventListener('mouseover', handleRunaway);
            noBtn.addEventListener('touchstart', handleRunaway);
            noBtn.addEventListener('click', handleRunaway);
        }

        function handleProposalYes(slideNum) {
            sendReaction('loved');
            confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
            
            document.getElementById(`ring-question-${slideNum}`).classList.add('hidden');
            const success = document.getElementById(`proposal-success-${slideNum}`);
            success.classList.remove('hidden');
            success.classList.add('flex');
        }

        // 8. Sorry Broken Heart
        let sorryHeartRepaired = {};
        function repairSorryHeart(slideNum) {
            if (sorryHeartRepaired[slideNum]) return;
            sorryHeartRepaired[slideNum] = true;
            
            const svg = document.getElementById(`sorry-heart-svg-${slideNum}`);
            const cracks = document.getElementById(`sorry-heart-cracks-${slideNum}`);
            const hint = document.getElementById(`sorry-heart-hint-${slideNum}`);
            
            svg.style.color = '#e11d48';
            cracks.style.opacity = '0';
            
            playSystemSound('success');
            confetti({ particleCount: 60, spread: 50 });
            
            svg.parentElement.classList.add('animate-heartbeat');
            if (hint) hint.textContent = 'Mended with Love ❤️';
            
            const btn = document.getElementById(`sorry-heart-continue-${slideNum}`);
            btn.classList.remove('opacity-50', 'pointer-events-none');
            btn.textContent = 'Continue →';
        }

        // 9. Forgiveness Letter
        let forgivenessOpened = {};
        function openForgivenessLetter(slideNum) {
            if (forgivenessOpened[slideNum]) return;
            forgivenessOpened[slideNum] = true;
            
            const seal = document.getElementById(`forgive-seal-${slideNum}`);
            const paper = document.getElementById(`forgive-paper-${slideNum}`);
            const hint = document.getElementById(`forgive-hint-${slideNum}`);
            const btn = document.getElementById(`forgive-continue-${slideNum}`);
            const textEl = document.getElementById(`forgive-text-${slideNum}`);
            
            if (seal) seal.style.transform = 'scale(0) rotate(180deg)';
            playSystemSound('pop');
            
            setTimeout(() => {
                if (seal) seal.classList.add('hidden');
                if (paper) {
                    paper.classList.remove('hidden');
                    paper.style.transform = 'translateY(-20px)';
                }
                
                const message = "I value us more than my ego. Please forgive me.";
                let charIdx = 0;
                if (textEl) textEl.textContent = '';
                const typeTimer = setInterval(() => {
                    if (charIdx < message.length) {
                        if (textEl) textEl.textContent += message.charAt(charIdx);
                        charIdx++;
                    } else {
                        clearInterval(typeTimer);
                        if (btn) {
                            btn.classList.remove('opacity-50', 'pointer-events-none');
                            btn.textContent = 'Continue →';
                        }
                        if (hint) hint.textContent = "Envelope opened. Let's move forward. 🌸";
                        playSystemSound('success');
                    }
                }, 50);
            }, 600);
        }

        // 10. Blooming Rose
        let sorryRoseBloomed = {};
        function bloomApologyRose(slideNum) {
            if (sorryRoseBloomed[slideNum]) return;
            sorryRoseBloomed[slideNum] = true;
            
            const rose = document.getElementById(`rose-flower-${slideNum}`);
            const hint = document.getElementById(`rose-hint-${slideNum}`);
            const btn = document.getElementById(`rose-continue-${slideNum}`);
            
            if (!rose) return;
            
            playSystemSound('pop');
            rose.style.filter = 'grayscale(0) hue-rotate(90deg)';
            rose.style.transform = 'translateY(-10px) rotate(0deg) scale(1.1)';
            
            setTimeout(() => {
                rose.textContent = '🌹';
                rose.style.filter = 'none';
                rose.style.transform = 'translateY(-15px) scale(1.3)';
                
                confetti({ particleCount: 30, spread: 45, colors: ['#f43f5e', '#ec4899'] });
                
                if (btn) {
                    btn.classList.remove('opacity-50', 'pointer-events-none');
                    btn.textContent = 'Continue →';
                }
                if (hint) hint.textContent = 'Apology blooms with color 🌹';
                playSystemSound('success');
            }, 1000);
        }

        // 10b. Reaching Hands
        let handsConnected = {};
        function initReachingHands(slideNum) {
            if (handsConnected[slideNum]) return;
            
            const receiverHand = document.getElementById(`receiver-hand-${slideNum}`);
            const senderHand = document.getElementById(`sender-hand-${slideNum}`);
            const area = document.getElementById(`reaching-hands-area-${slideNum}`);
            const btn = document.getElementById(`hands-continue-${slideNum}`);
            const hint = document.getElementById(`hands-hint-${slideNum}`);
            
            if (!receiverHand || !senderHand || !area) return;
            
            let isDragging = false;
            let startX = 0;
            let initialRight = 24;
            
            const areaWidth = area.offsetWidth || 320;
            const threshold = 60;
            
            function onDragStart(e) {
                isDragging = true;
                startX = e.clientX || (e.touches && e.touches[0].clientX) || 0;
                receiverHand.style.cursor = 'grabbing';
            }
            
            function onDragMove(e) {
                if (!isDragging) return;
                const currentX = e.clientX || (e.touches && e.touches[0].clientX) || 0;
                const diffX = startX - currentX;
                
                let newRight = initialRight + diffX;
                
                const maxLeftMove = areaWidth - threshold - 40;
                if (newRight < 24) newRight = 24;
                if (newRight > maxLeftMove) newRight = maxLeftMove;
                
                receiverHand.style.right = newRight + 'px';
                
                const distanceLeft = areaWidth - newRight - 60;
                if (distanceLeft <= 80) {
                    isDragging = false;
                    handsConnected[slideNum] = true;
                    
                    senderHand.style.transform = 'translateX(25px)';
                    receiverHand.style.right = 'auto';
                    receiverHand.style.left = '55px';
                    
                    senderHand.textContent = '🤝';
                    receiverHand.style.display = 'none';
                    
                    playSystemSound('success');
                    confetti({ particleCount: 40, spread: 40, colors: ['#f43f5e', '#fbbf24'] });
                    
                    if (btn) {
                        btn.classList.remove('opacity-50', 'pointer-events-none');
                        btn.textContent = 'Continue →';
                    }
                    if (hint) hint.textContent = 'Reunited & Clasped together 🤝';
                }
            }
            
            function onDragEnd() {
                isDragging = false;
                receiverHand.style.cursor = 'grab';
            }
            
            receiverHand.addEventListener('mousedown', onDragStart);
            document.addEventListener('mousemove', onDragMove);
            document.addEventListener('mouseup', onDragEnd);
            
            receiverHand.addEventListener('touchstart', onDragStart, { passive: true });
            document.addEventListener('touchmove', onDragMove, { passive: true });
            document.addEventListener('touchend', onDragEnd, { passive: true });
        }

        // 10c. Memories Restored
        let restoredCount = {};
        function restoreApologyMemory(el, index, slideNum, total) {
            if (!restoredCount[slideNum]) restoredCount[slideNum] = new Set();
            if (restoredCount[slideNum].has(index)) return;
            
            restoredCount[slideNum].add(index);
            
            const img = el.querySelector('img');
            if (img) {
                img.style.filter = 'none';
                img.style.opacity = '1';
            }
            const lock = el.querySelector(`#memory-lock-${index}-${slideNum}`);
            if (lock) {
                lock.style.transform = 'scale(0)';
                setTimeout(() => lock.remove(), 300);
            }
            
            playSystemSound('pop');
            
            confetti({
                particleCount: 15,
                spread: 30,
                origin: {
                    x: el.getBoundingClientRect().left / window.innerWidth,
                    y: el.getBoundingClientRect().top / window.innerHeight
                }
            });
            
            if (restoredCount[slideNum].size === total) {
                setTimeout(() => {
                    const btn = document.getElementById(`memories-continue-${slideNum}`);
                    const hint = document.getElementById(`memories-hint-${slideNum}`);
                    if (btn) {
                        btn.classList.remove('opacity-50', 'pointer-events-none');
                        btn.textContent = 'Continue →';
                    }
                    if (hint) hint.textContent = 'All memories restored to full color! ✨';
                    playSystemSound('success');
                }, 500);
            }
        }

        // 11. Love Letter Envelope
        let envelopeOpened = {};
        function openEnvelopeTrigger(slideNum) {
            if (envelopeOpened[slideNum]) return;
            envelopeOpened[slideNum] = true;
            
            const seal = document.getElementById(`envelope-seal-${slideNum}`);
            const flap = document.getElementById(`envelope-flap-${slideNum}`);
            const letter = document.getElementById(`envelope-letter-${slideNum}`);
            const hint = document.getElementById(`envelope-hint-${slideNum}`);
            
            seal.style.transform = 'scale(0) rotate(180deg)';
            playSystemSound('pop');
            
            setTimeout(() => {
                flap.style.transform = 'rotateX(180deg)';
                setTimeout(() => {
                    letter.style.transform = 'translateY(-50px)';
                    letter.style.zIndex = '15';
                    
                    playSystemSound('success');
                    if (hint) hint.textContent = 'Opened! ✉️';
                    
                    const btn = document.getElementById(`envelope-continue-${slideNum}`);
                    btn.classList.remove('opacity-50', 'pointer-events-none');
                    btn.textContent = 'Continue →';
                }, 400);
            }, 300);
        }

        // 12. Typewriter key tapping
        let typewriterTaps = {};
        function typewriterKeyTap(slideNum) {
            if (!typewriterTaps[slideNum]) typewriterTaps[slideNum] = 0;
            typewriterTaps[slideNum]++;
            playSystemSound('pop');
            
            const el = document.getElementById(`typewriter-body-${slideNum}`);
            const progress = Math.min(letterText.length, typewriterTaps[slideNum] * 12);
            el.textContent = letterText.substring(0, progress);
            
            const parent = el.closest('.overflow-y-auto');
            if (parent) parent.scrollTop = parent.scrollHeight;
            
            if (progress >= letterText.length) {
                const sig = document.getElementById(`typewriter-sig-${slideNum}`);
                if (sig) sig.classList.remove('opacity-0');
                
                const btn = document.getElementById(`typewriter-continue-${slideNum}`);
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }
        }

        // 13. Polaroid Scatter
        let polaroidsBroughtToFront = {};
        function scatterPolaroid(slideNum, index) {
            const card = document.getElementById(`polaroid-card-${slideNum}-${index}`);
            const rot = (Math.random() - 0.5) * 40;
            const tx = (Math.random() - 0.5) * 50;
            const ty = (Math.random() - 0.5) * 40;
            
            if (!polaroidsBroughtToFront[slideNum]) polaroidsBroughtToFront[slideNum] = 20;
            polaroidsBroughtToFront[slideNum]++;
            
            card.style.transform = `translate(${tx}px, ${ty}px) rotate(${rot}deg)`;
            card.style.zIndex = polaroidsBroughtToFront[slideNum];
            playSystemSound('pop');
        }

        // 14. Spin Wheel (Friendship & Invite)
        let wheelSpun = {};
        function spinWheelTrigger(slideNum, totalSegs, type) {
            if (wheelSpun[slideNum]) return;
            wheelSpun[slideNum] = true;
            
            const wheel = document.getElementById(`wheel-svg-${slideNum}`);
            if (!window.wheelRotation) window.wheelRotation = {};
            if (!window.wheelRotation[slideNum]) window.wheelRotation[slideNum] = 0;
            
            window.wheelRotation[slideNum] += 1440 + Math.floor(Math.random() * 360);
            const currentRot = window.wheelRotation[slideNum];
            wheel.style.transform = `rotate(${currentRot}deg)`;
            playSystemSound('stamp');
            
            const degreesPerSeg = 360 / totalSegs;
            const targetAngle = (270 - (currentRot % 360) + 360) % 360;
            const segIdx = Math.floor(targetAngle / degreesPerSeg);
            
            const segments = type === 'invite_date_wheel' 
                ? ["☕ Coffee", "🎬 Movie", "🍽️ Dinner", "🚗 Long Drive", "🎮 Gaming"]
                : ["Crazy Trip 🚗", "Late Night 📞", "Worst Joke 💀", "Helper 🤝", "Fight 🥊"];
            
            setTimeout(() => {
                playSystemSound('success');
                confetti({ particleCount: 60, spread: 45 });
                
                const resultText = document.getElementById(`wheel-result-text-${slideNum}`);
                const resultBox = document.getElementById(`wheel-result-${slideNum}`);
                resultText.textContent = segments[segIdx];
                resultBox.classList.remove('opacity-0');
                
                const btn = document.getElementById(`wheel-continue-${slideNum}`);
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
                
                // Allow spinning again
                wheelSpun[slideNum] = false;
            }, 4000);
        }

        // 15. Flip Cards
        let flippedCards = {};
        function flipFriendshipCard(el, slideNum) {
            const flipper = el.querySelector('.card-flipper');
            if (flipper.style.transform === 'rotateY(180deg)') return;
            
            flipper.style.transform = 'rotateY(180deg)';
            playSystemSound('pop');
            
            if (!flippedCards[slideNum]) flippedCards[slideNum] = 0;
            flippedCards[slideNum]++;
            
            const btn = document.getElementById(`friendship-cards-continue-${slideNum}`);
            btn.textContent = `Flip all cards to continue (${flippedCards[slideNum]}/3)`;
            if (flippedCards[slideNum] >= 3) {
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }
        }

        // 16. Stamp Certificate Badge
        let badgeStamped = {};
        function stampFriendshipBadge(slideNum) {
            if (badgeStamped[slideNum]) return;
            badgeStamped[slideNum] = true;
            
            const stamp = document.getElementById(`wax-stamp-${slideNum}`);
            const label = document.getElementById(`stamp-label-${slideNum}`);
            
            label.style.opacity = '0';
            stamp.style.transform = 'scale(1)';
            playSystemSound('stamp');
            
            setTimeout(() => {
                playSystemSound('success');
                confetti({ particleCount: 80, spread: 60 });
                const btn = document.getElementById(`badge-continue-${slideNum}`);
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }, 500);
        }

        // 17. Live Anniversary Counter
        let anniversaryInterval = null;
        function startAnniversaryCounter(slideNum) {
            if (anniversaryInterval) clearInterval(anniversaryInterval);
            const start = new Date(relationshipDate);
            
            function update() {
                const now = new Date();
                const diff = Math.max(0, now - start);
                
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
                const minutes = Math.floor((diff / (1000 * 60)) % 60);
                const seconds = Math.floor((diff / 1000) % 60);
                
                const daysEl = document.getElementById(`together-days-${slideNum}`);
                const hoursEl = document.getElementById(`together-hours-${slideNum}`);
                const minutesEl = document.getElementById(`together-minutes-${slideNum}`);
                const secondsEl = document.getElementById(`together-seconds-${slideNum}`);
                
                if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
                if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
                if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
                if (secondsEl) secondsEl.textContent = String(seconds).padStart(2, '0');
            }
            
            update();
            anniversaryInterval = setInterval(update, 1000);
        }

        // 18. Unlock Milestones
        let milestonesUnlocked = {};
        function unlockMilestone(index, slideNum, element) {
            if (!milestonesUnlocked[slideNum]) milestonesUnlocked[slideNum] = new Set();
            milestonesUnlocked[slideNum].add(index);
            
            const text = typeof element === 'string' ? element : element.getAttribute('data-text');
            
            const bubble = document.getElementById(`milestone-bubble-${slideNum}-${index}`);
            bubble.classList.add('bg-green-500');
            bubble.classList.remove('bg-rose-500');
            
            const labels = ["Day 1 📍", "Year 1 📅", "Today 💝"];
            document.getElementById(`timeline-label-${slideNum}`).textContent = labels[index];
            document.getElementById(`timeline-text-${slideNum}`).textContent = text;
            playSystemSound('pop');
            
            const total = 3;
            const btn = document.getElementById(`timeline-continue-${slideNum}`);
            btn.textContent = `Unlock all milestones (${milestonesUnlocked[slideNum].size}/${total})`;
            if (milestonesUnlocked[slideNum].size >= total) {
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }
        }

        // 19. Turn Journal Book Page
        let bookPageTurned = {};
        function turnBookPage(slideNum) {
            if (bookPageTurned[slideNum]) return;
            bookPageTurned[slideNum] = true;
            
            const cover = document.getElementById(`book-cover-${slideNum}`);
            const pageRight = document.getElementById(`book-page-right-${slideNum}`);
            const hint = document.getElementById(`book-hint-${slideNum}`);
            
            cover.style.transform = 'rotateY(-140deg)';
            
            setTimeout(() => {
                pageRight.style.transform = 'rotateY(-180deg)';
                pageRight.style.zIndex = '5';
                playSystemSound('pop');
                if (hint) hint.textContent = 'Read! 📖';
                
                const btn = document.getElementById(`book-continue-${slideNum}`);
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }, 700);
        }

        // 20. Zoom Map Pin
        let mapPinZoomed = {};
        function zoomMapPin(slideNum) {
            if (mapPinZoomed[slideNum]) return;
            mapPinZoomed[slideNum] = true;
            
            const scan = document.getElementById(`radar-scan-${slideNum}`);
            const pin = document.getElementById(`map-pin-${slideNum}`);
            const hint = document.getElementById(`map-hint-${slideNum}`);
            const result = document.getElementById(`map-result-${slideNum}`);
            
            scan.style.animation = 'none';
            pin.style.transform = 'scale(1.3)';
            playSystemSound('pop');
            
            setTimeout(() => {
                playSystemSound('success');
                result.classList.remove('opacity-0');
                if (hint) hint.textContent = 'Located! 📍';
                const btn = document.getElementById(`map-continue-${slideNum}`);
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }, 1500);
        }

        // 21. Rebuild Bridge Planks
        let planksPlaced = {};
        function placePlank(idx, slideNum) {
            if (!planksPlaced[slideNum]) planksPlaced[slideNum] = new Set();
            if (planksPlaced[slideNum].has(idx)) return;
            planksPlaced[slideNum].add(idx);
            
            const plank = document.getElementById(`plank-${idx}-${slideNum}`);
            plank.style.opacity = '1';
            plank.textContent = '🪵';
            playSystemSound('pop');
            
            const total = 3;
            if (planksPlaced[slideNum].size >= total) {
                setTimeout(() => {
                    const left = document.getElementById(`bridge-char-left-${slideNum}`);
                    const right = document.getElementById(`bridge-char-right-${slideNum}`);
                    left.style.transform = 'translateX(60px)';
                    right.style.transform = 'translateX(-60px)';
                    
                    playSystemSound('success');
                    confetti({ particleCount: 50, spread: 40 });
                    
                    const hint = document.getElementById(`bridge-hint-${slideNum}`);
                    if (hint) hint.textContent = 'Reunited! 🤝';
                    
                    const btn = document.getElementById(`bridge-continue-${slideNum}`);
                    btn.classList.remove('opacity-50', 'pointer-events-none');
                    btn.textContent = 'Continue →';
                }, 600);
            }
        }

        // 22. Unlock Padlock Key
        let padlockUnlocked = {};
        function unlockPadlockTrigger(slideNum) {
            if (padlockUnlocked[slideNum]) return;
            padlockUnlocked[slideNum] = true;
            
            const key = document.getElementById(`key-element-${slideNum}`);
            const lock = document.getElementById(`padlock-element-${slideNum}`);
            const hint = document.getElementById(`lock-hint-${slideNum}`);
            
            key.classList.remove('animate-bounce');
            key.style.transform = 'translate(0px, 0px) rotate(-90deg)';
            
            setTimeout(() => {
                playSystemSound('cut');
                lock.textContent = '🔓';
                lock.style.transform = 'scale(1.2)';
                key.style.opacity = '0';
                
                playSystemSound('success');
                if (hint) hint.textContent = 'Unlocked! ❤️';
                
                const btn = document.getElementById(`lock-continue-${slideNum}`);
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }, 850);
        }

        // 23. Miss You Collect Stars
        let starsCollected = {};
        function collectStar(idx, slideNum, thought) {
            if (!starsCollected[slideNum]) starsCollected[slideNum] = new Set();
            if (starsCollected[slideNum].has(idx)) return;
            starsCollected[slideNum].add(idx);
            
            const star = document.getElementById(`star-icon-${slideNum}-${idx}`);
            star.style.opacity = '1';
            star.style.color = '#fbbf24';
            star.classList.add('animate-ping');
            playSystemSound('pop');
            
            const card = document.getElementById(`star-display-card-${slideNum}`);
            const text = document.getElementById(`star-text-${slideNum}`);
            text.textContent = thought;
            card.classList.remove('opacity-0');
            
            const total = 4;
            const btn = document.getElementById(`star-continue-${slideNum}`);
            btn.textContent = `Collect all stars (${starsCollected[slideNum].size}/${total})`;
            if (starsCollected[slideNum].size >= total) {
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }
        }

        // 24. Miss You Moon Envelope
        let moonMessageOpened = {};
        function openMoonMessage(slideNum) {
            if (moonMessageOpened[slideNum]) return;
            moonMessageOpened[slideNum] = true;
            
            const moon = document.getElementById(`moon-element-${slideNum}`);
            const scroll = document.getElementById(`moon-scroll-${slideNum}`);
            const hint = document.getElementById(`moon-hint-${slideNum}`);
            
            moon.style.transform = 'translateX(-60px) rotate(-45deg)';
            
            setTimeout(() => {
                scroll.style.opacity = '1';
                scroll.style.transform = 'scale(1)';
                scroll.style.zIndex = '15';
                
                playSystemSound('success');
                if (hint) hint.textContent = 'Opened! 🌙';
                
                const btn = document.getElementById(`moon-continue-${slideNum}`);
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }, 700);
        }

        // 25. Voice Wave Simulation
        let voiceWaveTimer = {};
        function playVoiceAudio(slideNum) {
            const audio = document.getElementById(`voice-audio-element-${slideNum}`);
            const playBtn = document.getElementById(`voice-play-btn-${slideNum}`);
            
            // Pause other custom media
            document.querySelectorAll('audio, video').forEach(el => {
                if (el.id !== `voice-audio-element-${slideNum}` && el.id !== 'story-audio') {
                    el.pause();
                }
            });
            document.querySelectorAll('[id^="custom-audio-play-"]').forEach(b => b.textContent = '▶');
            document.querySelectorAll('[id^="custom-audio-wave-"]').forEach(w => {
                w.classList.add('opacity-50');
                if (w.id.includes('letter')) {
                    stopVisualWave(w);
                } else {
                    const pId = w.id.replace('custom-audio-wave-', '');
                    stopVoiceWaveAnim(pId);
                }
            });

            const storyAudio = document.getElementById('story-audio');
            if (audio.paused) {
                if (storyAudio && musicPlaying) {
                    duckBackgroundMusic();
                    musicPausedByVoice = true;
                }
                
                if (audio.readyState === 0) {
                    audio.load();
                }
                
                audio.play().then(() => {
                    playBtn.textContent = '⏸️';
                }).catch(err => {
                    const wsrc = audio.currentSrc || audio.src || '';
                    if (window.aacRepair && /\.(aac|acc)(\?.*)?$/i.test(wsrc) && !audio.dataset.aacRetried) {
                        audio.dataset.aacRetried = '1';
                        playBtn.textContent = '⏳';
                        window.aacRepair(audio).then(ok => { playBtn.textContent = ok ? '⏸️' : '▶️'; if (ok) audio.play().catch(() => {}); });
                        return;
                    }
                    console.log("Voice wave play failed: ", err);
                });
            } else {
                audio.pause();
                playBtn.textContent = '▶️';
                
                // Automatically resume background music
                if (musicPausedByVoice && storyAudio) {
                    unduckBackgroundMusic();
                    musicPausedByVoice = false;
                }
            }
        }
        function startVoiceWave(slideNum) {
            const bars = document.querySelectorAll(`#audio-wave-bars-${slideNum} > div`);
            voiceWaveTimer[slideNum] = setInterval(() => {
                bars.forEach(bar => {
                    const hVal = Math.floor(Math.random() * 50) + 15;
                    bar.style.height = `${hVal}px`;
                });
            }, 150);
        }
        function stopVoiceWave(slideNum) {
            clearInterval(voiceWaveTimer[slideNum]);
            const bars = document.querySelectorAll(`#audio-wave-bars-${slideNum} > div`);
            bars.forEach(bar => {
                bar.style.height = `10px`;
            });
            const playBtn = document.getElementById(`voice-play-btn-${slideNum}`);
            if (playBtn) playBtn.textContent = '▶️';
            
            // Resume background music if ended
            const storyAudio = document.getElementById('story-audio');
            if (musicPausedByVoice && storyAudio) {
                unduckBackgroundMusic();
                musicPausedByVoice = false;
            }
        }

        // 26. Reveal Trophy
        let trophyRevealed = {};
        function revealTrophy(slideNum) {
            if (trophyRevealed[slideNum]) return;
            trophyRevealed[slideNum] = true;
            
            const drape = document.getElementById(`trophy-drape-${slideNum}`);
            const trophy = document.getElementById(`trophy-element-${slideNum}`);
            const hint = document.getElementById(`trophy-hint-${slideNum}`);
            
            drape.style.transform = 'translateY(150%) rotate(10deg)';
            drape.style.opacity = '0';
            
            setTimeout(() => {
                trophy.style.opacity = '1';
                trophy.style.transform = 'scale(1.2)';
                
                playSystemSound('success');
                confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
                if (hint) hint.textContent = 'Congratulations! 🏆';
                
                const btn = document.getElementById(`trophy-continue-${slideNum}`);
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }, 400);
        }

        // 27. Celebration Sky (Fireworks)
        function launchCelebrationFirework(e, slideNum) {
            const area = document.getElementById(`fireworks-area-${slideNum}`);
            const rect = area.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width;
            const y = (e.clientY - rect.top) / rect.height;
            playSystemSound('pop');
            confetti({ particleCount: 45, spread: 60, origin: { x: x, y: y } });
        }

        // 28. Crush Secret Envelope
        let crushEnvelopeOpened = {};
        function openCrushEnvelope(slideNum) {
            if (crushEnvelopeOpened[slideNum]) return;
            crushEnvelopeOpened[slideNum] = true;
            
            const seal = document.getElementById(`crush-envelope-seal-${slideNum}`);
            const flap = document.getElementById(`crush-envelope-flap-${slideNum}`);
            const letter = document.getElementById(`crush-envelope-letter-${slideNum}`);
            const hint = document.getElementById(`crush-envelope-hint-${slideNum}`);
            
            seal.style.transform = 'scale(0) rotate(180deg)';
            playSystemSound('pop');
            
            setTimeout(() => {
                flap.style.transform = 'rotateX(180deg)';
                setTimeout(() => {
                    letter.style.transform = 'translateY(-50px)';
                    letter.style.zIndex = '15';
                    
                    playSystemSound('success');
                    if (hint) hint.textContent = 'Opened! 🤫';
                    
                    const btn = document.getElementById(`crush-envelope-continue-${slideNum}`);
                    btn.classList.remove('opacity-50', 'pointer-events-none');
                    btn.textContent = 'Continue →';
                }, 400);
            }, 300);
        }

        // 29. Crush Wiping Fog Canvas
        let fogCanvasInitialized = {};
        function initFogCanvas(slideNum) {
            if (fogCanvasInitialized[slideNum]) return;
            fogCanvasInitialized[slideNum] = true;
            
            const canvas = document.getElementById(`fog-canvas-${slideNum}`);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            
            ctx.fillStyle = 'rgba(230, 240, 250, 0.95)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            ctx.fillStyle = 'rgba(80, 100, 120, 0.7)';
            ctx.font = '14px Outfit, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Wipe the foggy glass 🌫️', canvas.width / 2, canvas.height / 2);
            
            let wipeCount = 0;
            const handleWipe = (x, y) => {
                ctx.globalCompositeOperation = 'destination-out';
                ctx.beginPath();
                ctx.arc(x, y, 20, 0, Math.PI * 2);
                ctx.fill();
                
                wipeCount++;
                if (wipeCount > 150) {
                    canvas.style.transition = 'opacity 1s';
                    canvas.style.opacity = '0';
                    setTimeout(() => canvas.remove(), 1000);
                    
                    playSystemSound('success');
                    document.getElementById(`fog-hint-${slideNum}`).textContent = 'Revealed! 🤫';
                    const btn = document.getElementById(`fog-continue-${slideNum}`);
                    btn.classList.remove('opacity-50', 'pointer-events-none');
                    btn.textContent = 'Continue →';
                }
            };
            
            canvas.addEventListener('mousemove', e => {
                const r = canvas.getBoundingClientRect();
                handleWipe(e.clientX - r.left, e.clientY - r.top);
            });
            canvas.addEventListener('touchmove', e => {
                if (e.cancelable) e.preventDefault();
                const r = canvas.getBoundingClientRect();
                handleWipe(e.touches[0].clientX - r.left, e.touches[0].clientY - r.top);
            }, { passive: false });
        }

        // 30. Crush Heartbeat Hold
        let heartbeatHoldTimer = null;
        let heartbeatInterval = 400;
        let heartbeatHoldProgress = 0;

        function startHeartbeatPress(slideNum) {
            const btn = document.getElementById(`heartbeat-btn-${slideNum}`);
            const hint = document.getElementById(`heartbeat-hint-${slideNum}`);
            heartbeatHoldProgress = 0;
            heartbeatInterval = 400;
            
            function beat() {
                playSystemSound('pop');
                btn.style.transform = `scale(1.25)`;
                setTimeout(() => { btn.style.transform = `scale(1.05)`; }, 80);
                
                heartbeatHoldProgress++;
                if (heartbeatHoldProgress > 15) {
                    clearTimeout(heartbeatHoldTimer);
                    btn.style.transform = 'scale(0)';
                    
                    playSystemSound('success');
                    confetti({ particleCount: 150, spread: 80, colors: ['#f43f5e', '#ff007f'] });
                    hint.textContent = 'Confession Unlocked! 💖';
                    
                    const btnCont = document.getElementById(`heartbeat-continue-${slideNum}`);
                    btnCont.classList.remove('opacity-50', 'pointer-events-none');
                    btnCont.textContent = 'Continue →';
                    return;
                }
                heartbeatInterval = Math.max(100, heartbeatInterval - 40);
                heartbeatHoldTimer = setTimeout(beat, heartbeatInterval);
            }
            beat();
        }

        function stopHeartbeatPress(slideNum) {
            if (heartbeatHoldTimer) {
                clearTimeout(heartbeatHoldTimer);
                heartbeatHoldTimer = null;
            }
            const btn = document.getElementById(`heartbeat-btn-${slideNum}`);
            btn.style.transform = `scale(1)`;
        }

        // 31. Surprise Mystery Box
        let mysteryBoxOpened = {};
        function openMysteryBox(slideNum) {
            if (mysteryBoxOpened[slideNum]) return;
            mysteryBoxOpened[slideNum] = true;
            
            const box = document.getElementById(`mystery-box-${slideNum}`);
            const hint = document.getElementById(`mystery-hint-${slideNum}`);
            
            box.classList.remove('gift-shake');
            box.classList.add('gift-opened');
            box.innerHTML = '<div class="text-[120px] leading-none select-none">🎉</div>';
            
            playSystemSound('success');
            confetti({ particleCount: 150, spread: 80 });
            if (hint) hint.textContent = 'Surprise! 🎉';
            
            const btn = document.getElementById(`mystery-continue-${slideNum}`);
            btn.classList.remove('opacity-50', 'pointer-events-none');
            btn.textContent = 'Continue →';
        }

        // 32. Surprise Scratch Canvas
        let scratchCanvasInitialized = {};
        function initScratchCanvas(slideNum) {
            if (scratchCanvasInitialized[slideNum]) return;
            scratchCanvasInitialized[slideNum] = true;
            
            const canvas = document.getElementById(`scratch-canvas-${slideNum}`);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            
            ctx.fillStyle = '#c0c0c0';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            ctx.fillStyle = '#444';
            ctx.font = '14px Outfit, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Scratch to win! 🪙', canvas.width / 2, canvas.height / 2);
            
            let scratchCount = 0;
            const handleScratch = (x, y) => {
                ctx.globalCompositeOperation = 'destination-out';
                ctx.beginPath();
                ctx.arc(x, y, 25, 0, Math.PI * 2);
                ctx.fill();
                
                scratchCount++;
                if (scratchCount > 150) {
                    canvas.style.transition = 'opacity 1s';
                    canvas.style.opacity = '0';
                    setTimeout(() => canvas.remove(), 1000);
                    
                    playSystemSound('success');
                    document.getElementById(`scratch-hint-${slideNum}`).textContent = 'Revealed! 🎁';
                    const btn = document.getElementById(`scratch-continue-${slideNum}`);
                    btn.classList.remove('opacity-50', 'pointer-events-none');
                    btn.textContent = 'Continue →';
                }
            };
            
            canvas.addEventListener('mousemove', e => {
                const r = canvas.getBoundingClientRect();
                handleScratch(e.clientX - r.left, e.clientY - r.top);
            });
            canvas.addEventListener('touchmove', e => {
                if (e.cancelable) e.preventDefault();
                const r = canvas.getBoundingClientRect();
                handleScratch(e.touches[0].clientX - r.left, e.touches[0].clientY - r.top);
            }, { passive: false });
        }

        // 33. Countdown digital ticking reveal
        let countdownRevealStarted = {};
        function startCountdownReveal(slideNum) {
            if (countdownRevealStarted[slideNum]) return;
            countdownRevealStarted[slideNum] = true;
            
            const el = document.getElementById(`countdown-reveal-display-${slideNum}`);
            const hint = document.getElementById(`countdown-reveal-hint-${slideNum}`);
            
            el.style.opacity = '1';
            let current = 10;
            
            function tick() {
                el.textContent = current;
                el.style.animation = 'none';
                el.offsetHeight;
                el.style.animation = 'countPulse 0.8s ease-out forwards';
                playSystemSound('pop');
                
                current--;
                if (current >= 0) {
                    setTimeout(tick, 1000);
                } else {
                    el.textContent = '🎉';
                    playSystemSound('success');
                    confetti({ particleCount: 150, spread: 80 });
                    if (hint) hint.textContent = 'Go!';
                    
                    const btn = document.getElementById(`countdown-reveal-continue-${slideNum}`);
                    btn.classList.remove('opacity-50', 'pointer-events-none');
                    btn.textContent = 'Continue →';
                }
            }
            tick();
        }

        // 🌹 PROPOSAL CATEGORY CINEMATIC ANIMATIONS JS ENGINE
        let constellationAnimationId = {};
        let heartFormationAnimationId = {};
        let countdownFeelingsInterval = null;
        let proposalPetalsTimer = null;
        let finalStarsAnimId = null;
        let celebrationConfettiAnimId = null;

        function dimBackgroundMusic() {
            const storyAudio = document.getElementById('story-audio');
            if (storyAudio) {
                originalMusicVolume = storyAudio.volume || 1.0;
                let vol = originalMusicVolume;
                const interval = setInterval(() => {
                    vol -= 0.05;
                    if (vol <= 0.15) {
                        vol = 0.15;
                        clearInterval(interval);
                    }
                    storyAudio.volume = vol;
                }, 50);
            }
        }

        function restoreBackgroundMusic() {
            const storyAudio = document.getElementById('story-audio');
            if (storyAudio) {
                let vol = storyAudio.volume;
                const interval = setInterval(() => {
                    vol += 0.05;
                    if (vol >= originalMusicVolume) {
                        vol = originalMusicVolume;
                        clearInterval(interval);
                    }
                    storyAudio.volume = vol;
                }, 50);
            }
        }

        function typeWriterEffect(el, text, speed, onComplete) {
            if (!el) return;
            let i = 0;
            el.textContent = '';
            el.classList.add('typewriter-cursor');
            function type() {
                if (i < text.length) {
                    el.textContent += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                } else {
                    el.classList.remove('typewriter-cursor');
                    if (onComplete) onComplete();
                }
            }
            type();
        }

        // 1. Rose Bloom Cinematic
        function runRoseCinematic(slideNum) {
            dimBackgroundMusic();
            const container = document.getElementById(`rose-cinematic-container-${slideNum}`);
            if (!container) return;
            
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const stem = document.getElementById(`rose-stem-path-${slideNum}`);
            const leaf1 = document.getElementById(`rose-leaf-1-${slideNum}`);
            const leaf2 = document.getElementById(`rose-leaf-2-${slideNum}`);
            const glow = document.getElementById(`rose-cinematic-glow-${slideNum}`);
            const petals = container.querySelectorAll('.rose-petal-path');

            // Scene 1: Blackout & Spotlight (0 - 1500ms)
            setTimeout(() => {
                glow.style.transform = 'scale(1)';
                glow.style.opacity = '1';
                glow.classList.add('rose-glow-pulse');
            }, 800);

            // Scene 2: Draw Stem (1500ms)
            setTimeout(() => {
                if (stem) stem.style.strokeDashoffset = '0';
            }, 1500);

            // Leaves appear (2500ms)
            setTimeout(() => {
                if (leaf1) leaf1.style.opacity = '1';
                if (leaf2) leaf2.style.opacity = '1';
            }, 2500);

            // Petals unfold sequentially (3000ms onwards)
            petals.forEach((petal, pi) => {
                setTimeout(() => {
                    petal.style.opacity = '1';
                    petal.classList.add('rose-petal-anim');
                    playSystemSound('petal');
                    spawnRoseParticles(slideNum, 200, 140);
                }, 3000 + pi * 400);
            });

            // Scene 3: Birthday Message
            const nameEl = document.getElementById(`rose-cinematic-name-${slideNum}`);
            const msgEl = document.getElementById(`rose-cinematic-message-${slideNum}`);
            const nameText = `${receiverName} ❤️`;
            const msgText = "I have wanted to tell you something for a long time...";

            setTimeout(() => {
                typeWriterEffect(nameEl, nameText, 60, () => {
                    setTimeout(() => {
                        typeWriterEffect(msgEl, msgText, 40, () => {
                            const btn = document.getElementById(`rose-cinematic-continue-${slideNum}`);
                            if (btn) {
                                btn.classList.remove('hidden');
                                btn.classList.add('flex');
                                setTimeout(() => {
                                    btn.style.transform = 'scale(1)';
                                    btn.style.opacity = '1';
                                }, 100);
                            }
                        });
                    }, 500);
                });
            }, 3000 + petals.length * 400 + 1000);
        }

        function spawnRoseParticles(slideNum, cx, cy) {
            const container = document.getElementById(`rose-cinematic-particles-${slideNum}`);
            if (!container) return;
            for (let i = 0; i < 4; i++) {
                const p = document.createElement('div');
                p.className = 'absolute w-2 h-2 rounded-full bg-rose-400/80 pointer-events-none';
                p.style.left = `${cx}px`;
                p.style.top = `${cy}px`;
                const px = (Math.random() - 0.5) * 120;
                const py = (Math.random() - 0.5) * 120 - 40;
                p.style.setProperty('--px', `${px}px`);
                p.style.setProperty('--py', `${py}px`);
                p.style.animation = 'roseParticleFloat 1.2s cubic-bezier(0.1, 0.8, 0.3, 1) forwards';
                container.appendChild(p);
                setTimeout(() => p.remove(), 1200);
            }
        }

        function finishRoseCinematic(slideNum) {
            const container = document.getElementById(`rose-cinematic-container-${slideNum}`);
            restoreBackgroundMusic();
            if (container) {
                container.style.transition = 'opacity 0.8s ease-out';
                container.classList.remove('opacity-100');
                container.classList.add('opacity-0');
                setTimeout(() => {
                    container.remove();
                    goSlide(slideNum + 1);
                }, 800);
            } else {
                goSlide(slideNum + 1);
            }
        }

        // 2. Memory Constellation Cinematic
        function runConstellationCinematic(slideNum) {
            const container = document.getElementById(`constellation-container-${slideNum}`);
            if (!container) return;
            
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const canvas = document.getElementById(`constellation-canvas-${slideNum}`);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            
            canvas.width = canvas.clientWidth || canvas.offsetWidth || 375;
            canvas.height = canvas.clientHeight || canvas.offsetHeight || 667;

            const stars = [];
            for (let i = 0; i < 150; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 1.5,
                    alpha: Math.random(),
                    speed: 0.01 + Math.random() * 0.02
                });
            }

            const imgCount = pageImages.length;
            const points = getConstellationPositions(imgCount, canvas.width, canvas.height);
            let currentLineProgress = 0;
            let currentLineIndex = 0;

            function animate() {
                ctx.fillStyle = '#020617';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                stars.forEach(s => {
                    s.alpha += s.speed;
                    if (s.alpha > 1 || s.alpha < 0) s.speed = -s.speed;
                    ctx.fillStyle = `rgba(255, 255, 255, ${Math.max(0.2, s.alpha)})`;
                    ctx.beginPath();
                    ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
                    ctx.fill();
                });

                if (imgCount > 1) {
                    ctx.lineWidth = 1.5;
                    ctx.shadowBlur = 8;
                    ctx.shadowColor = 'rgba(236,72,153,0.5)';
                    
                    for (let i = 0; i < currentLineIndex; i++) {
                        const p1 = points[i];
                        const p2 = points[i + 1];
                        const grad = ctx.createLinearGradient(p1.x, p1.y, p2.x, p2.y);
                        grad.addColorStop(0, 'rgba(168,85,247,0.8)');
                        grad.addColorStop(1, 'rgba(236,72,153,0.8)');
                        ctx.strokeStyle = grad;
                        ctx.beginPath();
                        ctx.moveTo(p1.x, p1.y);
                        ctx.lineTo(p2.x, p2.y);
                        ctx.stroke();
                    }

                    if (currentLineIndex < imgCount - 1) {
                        const p1 = points[currentLineIndex];
                        const p2 = points[currentLineIndex + 1];
                        const grad = ctx.createLinearGradient(p1.x, p1.y, p2.x, p2.y);
                        grad.addColorStop(0, 'rgba(168,85,247,0.8)');
                        grad.addColorStop(1, 'rgba(236,72,153,0.8)');
                        ctx.strokeStyle = grad;
                        
                        const curX = p1.x + (p2.x - p1.x) * currentLineProgress;
                        const curY = p1.y + (p2.y - p1.y) * currentLineProgress;
                        
                        ctx.beginPath();
                        ctx.moveTo(p1.x, p1.y);
                        ctx.lineTo(curX, curY);
                        ctx.stroke();

                        currentLineProgress += 0.04;
                        if (currentLineProgress >= 1) {
                            currentLineProgress = 0;
                            currentLineIndex++;
                        }
                    }
                    ctx.shadowBlur = 0;
                }

                points.forEach((p) => {
                    ctx.fillStyle = '#fff';
                    ctx.shadowBlur = 10;
                    ctx.shadowColor = 'rgba(244,63,94,0.8)';
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.shadowBlur = 0;
                });

                constellationAnimationId[slideNum] = requestAnimationFrame(animate);
            }
            animate();

            const photoContainer = document.getElementById(`constellation-photos-${slideNum}`);
            if (imgCount === 0) {
                const fallbackMsg = document.createElement('div');
                fallbackMsg.className = 'absolute inset-0 flex flex-col justify-center items-center text-pink-300/80 font-bold p-8 text-center';
                fallbackMsg.innerHTML = '<span class="text-4xl mb-4">✨</span>Upload photos to build your constellation!';
                photoContainer.appendChild(fallbackMsg);
                showConstellationBtn(slideNum);
            } else {
                setTimeout(() => {
                    points.forEach((p, idx) => {
                        setTimeout(() => {
                            revealPolaroid(slideNum, p, pageImages[idx].medium_path || pageImages[idx].image_path);
                            if (idx === imgCount - 1) {
                                showConstellationBtn(slideNum);
                            }
                        }, idx * 600);
                    });
                }, 2000);
            }
        }

        function showConstellationBtn(slideNum) {
            setTimeout(() => {
                const btn = document.getElementById(`constellation-continue-${slideNum}`);
                if (btn) {
                    btn.classList.remove('hidden');
                    btn.classList.add('flex');
                    setTimeout(() => {
                        btn.style.transform = 'scale(1)';
                        btn.style.opacity = '1';
                    }, 100);
                }
            }, 1000);
        }

        function getConstellationPositions(count, w, h) {
            const positions = [];
            const cx = w / 2, cy = h / 2;
            const maxR = Math.min(w, h) * 0.32;
            const golden = 2.39996;
            for (let i = 0; i < count; i++) {
                const r = maxR * Math.sqrt((i + 0.5) / count);
                const theta = i * golden;
                positions.push({
                    x: cx + r * Math.cos(theta),
                    y: cy + r * Math.sin(theta)
                });
            }
            return positions;
        }

        function revealPolaroid(slideNum, pos, imgUrl) {
            const container = document.getElementById(`constellation-photos-${slideNum}`);
            if (!container) return;

            const card = document.createElement('div');
            card.className = 'polaroid-constellation';
            card.style.left = `${pos.x - 58}px`;
            card.style.top = `${pos.y - 66}px`;
            card.style.transform = 'scale(0) rotate(720deg)';
            card.innerHTML = `<img src="${baseUrl}${imgUrl}" alt="Memory"><div class="text-[8px] text-gray-500 font-semibold text-center mt-1">✨ Memory</div>`;
            
            card.onclick = (e) => {
                e.stopPropagation();
                openPhotoModal(`${baseUrl}${imgUrl}`);
            };

            container.appendChild(card);
            
            setTimeout(() => {
                const rot = (Math.random() - 0.5) * 24;
                card.style.transform = `scale(1) rotate(${rot}deg)`;
                playSystemSound('pop');
            }, 50);
        }

        // 3. Heart Formation Cinematic
        function runHeartFormationCinematic(slideNum) {
            const container = document.getElementById(`heart-formation-container-${slideNum}`);
            if (!container) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const canvas = document.getElementById(`heart-formation-canvas-${slideNum}`);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            canvas.width = canvas.clientWidth || canvas.offsetWidth || 375;
            canvas.height = canvas.clientHeight || canvas.offsetHeight || 667;

            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const particleCount = reducedMotion ? 100 : (canvas.width > 768 ? 1000 : 500);
            const particles = [];
            let phase = 'scatter';

            const cx = canvas.width / 2;
            const cy = canvas.height / 2 - 30;
            const heartScale = canvas.width > 768 ? 12 : 7.5;

            for (let i = 0; i < particleCount; i++) {
                const t = Math.PI * 2 * (i / particleCount);
                const targetPos = heartPosition(t, heartScale, cx, cy);
                
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    targetX: targetPos.x,
                    targetY: targetPos.y,
                    vx: (Math.random() - 0.5) * 4,
                    vy: (Math.random() - 0.5) * 4,
                    size: 1.5 + Math.random() * 2,
                    color: `rgba(${220 + Math.random()*35}, ${20 + Math.random()*60}, ${80 + Math.random()*60}, ${0.5 + Math.random()*0.5})`,
                    alpha: 0.5 + Math.random()*0.5
                });
            }

            setTimeout(() => {
                phase = 'gathering';
                playSystemSound('chime');
            }, 2500);

            let formedPulse = 1.0;
            let pulseDir = 0.005;

            function animate() {
                ctx.fillStyle = '#090507';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                let allSettled = true;
                particles.forEach(p => {
                    if (phase === 'scatter') {
                        p.x += p.vx;
                        p.y += p.vy;
                        if (p.x < 0 || p.x > canvas.width) p.vx = -p.vx;
                        if (p.y < 0 || p.y > canvas.height) p.vy = -p.vy;
                    } else if (phase === 'gathering' || phase === 'formed') {
                        let tx = p.targetX;
                        let ty = p.targetY;
                        
                        if (phase === 'formed') {
                            tx = cx + (p.targetX - cx) * formedPulse;
                            ty = cy + (p.targetY - cy) * formedPulse;
                        }

                        const dx = tx - p.x;
                        const dy = ty - p.y;
                        p.x += dx * 0.04;
                        p.y += dy * 0.04;

                        if (Math.abs(dx) > 1.5 || Math.abs(dy) > 1.5) {
                            allSettled = false;
                        }
                    }

                    ctx.fillStyle = p.color;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                    ctx.fill();
                });

                if (phase === 'gathering' && allSettled) {
                    phase = 'formed';
                    triggerHeartGallery(slideNum);
                }

                if (phase === 'formed') {
                    formedPulse += pulseDir;
                    if (formedPulse > 1.08 || formedPulse < 0.98) {
                        pulseDir = -pulseDir;
                    }
                }

                heartFormationAnimationId[slideNum] = requestAnimationFrame(animate);
            }
            animate();
        }

        function triggerHeartGallery(slideNum) {
            const textEl = document.getElementById(`heart-formation-text-${slideNum}`);
            if (textEl) {
                textEl.classList.remove('opacity-0', 'scale-75');
                textEl.classList.add('opacity-100', 'scale-100');
            }

            const imgContainer = document.getElementById(`heart-formation-photos-${slideNum}`);
            if (!imgContainer) return;

            const imgCount = Math.min(4, pageImages.length);
            if (imgCount > 0) {
                const layout = [
                    { x: -45, y: -20 },
                    { x: 45, y: -20 },
                    { x: -20, y: 35 },
                    { x: 20, y: 35 }
                ];
                for (let i = 0; i < imgCount; i++) {
                    setTimeout(() => {
                        const img = document.createElement('img');
                        img.src = `${baseUrl}${pageImages[i].medium_path || pageImages[i].image_path}`;
                        img.className = 'absolute w-16 h-16 rounded-full border-2 border-pink-400 object-cover shadow-lg opacity-0 transition-all duration-1000 scale-50';
                        img.style.left = `calc(50% + ${layout[i].x}px - 32px)`;
                        img.style.top = `calc(50% + ${layout[i].y}px - 32px)`;
                        imgContainer.appendChild(img);
                        
                        setTimeout(() => {
                            img.classList.remove('opacity-0', 'scale-50');
                            img.classList.add('opacity-100', 'scale-100');
                            playSystemSound('pop');
                        }, 50);
                    }, i * 600);
                }
            }

            setTimeout(() => {
                const btn = document.getElementById(`heart-formation-continue-${slideNum}`);
                if (btn) {
                    btn.classList.remove('hidden');
                    btn.classList.add('flex');
                    setTimeout(() => {
                        btn.style.transform = 'scale(1)';
                        btn.style.opacity = '1';
                    }, 100);
                }
            }, imgCount * 600 + 1000);
        }

        // 4. Love Letter Writing Cinematic
        function runLoveLetterCinematic(slideNum) {
            const container = document.getElementById(`love-letter-container-${slideNum}`);
            if (!container) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const paper = document.getElementById(`love-letter-paper-${slideNum}`);
            if (paper) {
                setTimeout(() => {
                    paper.style.transform = 'translateY(0)';
                }, 100);
            }

            const text = letterText || "You are the most precious person in my world. Knowing you has filled my heart with endless joy. Every step together feels like a beautiful dream...";
            const containerLines = document.getElementById(`love-letter-lines-${slideNum}`);
            if (!containerLines) return;

            const words = text.split(' ');
            const lines = [];
            let curLine = "";
            words.forEach(w => {
                if ((curLine + " " + w).length > 28) {
                    lines.push(curLine.trim());
                    curLine = w;
                } else {
                    curLine += " " + w;
                }
            });
            if (curLine) lines.push(curLine.trim());

            containerLines.innerHTML = lines.map((l, li) => `<div id="letter-line-${slideNum}-${li}" class="relative min-h-[30px] opacity-0 transition-opacity duration-500 font-serif font-semibold italic text-[#5c3e21]" style="clip-path: inset(0 100% 0 0); transition: clip-path 1.5s linear, opacity 0.5s;">${l}</div>`).join('');

            const pen = document.getElementById(`love-letter-pen-${slideNum}`);
            
            setTimeout(() => {
                if (pen) pen.classList.remove('hidden');
                writeLetterLine(slideNum, lines, 0);
            }, 1800);
        }

        function writeLetterLine(slideNum, lines, index) {
            if (index >= lines.length) {
                const pen = document.getElementById(`love-letter-pen-${slideNum}`);
                if (pen) {
                    pen.style.transform = 'translate(100px, -50px) scale(0)';
                    setTimeout(() => pen.remove(), 600);
                }
                
                const btn = document.getElementById(`love-letter-continue-${slideNum}`);
                if (btn) {
                    btn.classList.remove('hidden');
                    btn.classList.add('flex');
                    setTimeout(() => {
                        btn.style.transform = 'scale(1)';
                        btn.style.opacity = '1';
                    }, 100);
                }
                return;
            }

            const lineEl = document.getElementById(`letter-line-${slideNum}-${index}`);
            const pen = document.getElementById(`love-letter-pen-${slideNum}`);
            if (!lineEl) return;

            lineEl.style.opacity = '1';
            
            const startX = 10;
            const startY = lineEl.offsetTop + 12;

            if (pen) {
                pen.style.left = `${startX}px`;
                pen.style.top = `${startY}px`;
            }

            let scribbleTimer = setInterval(() => {
                playSystemSound('scribble');
            }, 150);

            setTimeout(() => {
                lineEl.style.clipPath = 'inset(0 0% 0 0)';
                
                let progress = 0;
                const lineLen = lineEl.offsetWidth || 300;
                const penMoveTimer = setInterval(() => {
                    progress += 0.05;
                    if (progress >= 1.0) {
                        clearInterval(penMoveTimer);
                        clearInterval(scribbleTimer);
                        setTimeout(() => writeLetterLine(slideNum, lines, index + 1), 400);
                    }
                    if (pen) {
                        pen.style.left = `${startX + progress * lineLen}px`;
                    }
                }, 75);
            }, 100);
        }

        // 5. Future Dreams Portal Cinematic
        function runPortalCinematic(slideNum) {
            const container = document.getElementById('portal-container-' + slideNum);
            if (!container) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const ringContainer = document.getElementById('portal-ring-container-' + slideNum);
            const cardsContainer = document.getElementById('portal-cards-' + slideNum);
            if (!ringContainer || !cardsContainer) return;

            // Ensure cards container has explicit full width/height
            cardsContainer.style.width = '100%';
            cardsContainer.style.height = '100%';

            setTimeout(() => {
                ringContainer.style.transform = 'scale(1.15)';
                playSystemSound('portal');
            }, 500);

            const dreams = [
                { icon: "🌍", text: "Travel the world together" },
                { icon: "🏡", text: "Build our dream home" },
                { icon: "💒", text: "Plan our dream wedding" },
                { icon: "💑", text: "Endless happy moments" },
                { icon: "🎓", text: "Achieve our goals side-by-side" },
                { icon: "👶", text: "Grow our happy family" }
            ];

            const angles = [0, 60, 120, 180, 240, 300];
            const w = container.clientWidth || container.offsetWidth || 375;
            const h = container.clientHeight || container.offsetHeight || 667;
            const radius = Math.min(w, h) * 0.35;

            setTimeout(() => {
                dreams.forEach((d, idx) => {
                    setTimeout(() => {
                        const card = document.createElement('div');
                        card.className = 'dream-card';
                        card.innerHTML = `<span class="text-xl">${d.icon}</span><span class="text-xs font-semibold">${d.text}</span>`;
                        cardsContainer.appendChild(card);
                        
                        playSystemSound('pop');

                        const angleRad = angles[idx] * (Math.PI / 180);
                        const tx = radius * Math.cos(angleRad);
                        const ty = radius * Math.sin(angleRad);

                        card.style.setProperty('--tx', `${tx}px`);
                        card.style.setProperty('--ty', `${ty}px`);

                        setTimeout(() => {
                            card.classList.add('active');
                        }, 50);
                    }, idx * 600);
                });
            }, 2000);

            const handleParallax = (e) => {
                const rect = cardsContainer.getBoundingClientRect();
                const containerCenterX = rect.left + rect.width / 2;
                const containerCenterY = rect.top + rect.height / 2;
                
                const mx = (e.clientX - containerCenterX) / (rect.width / 2 || 1) * 15;
                const my = (e.clientY - containerCenterY) / (rect.height / 2 || 1) * 15;
                
                const cards = cardsContainer.querySelectorAll('.dream-card');
                cards.forEach((c, ci) => {
                    const angleRad = angles[ci] * (Math.PI / 180);
                    const tx = radius * Math.cos(angleRad) + mx;
                    const ty = radius * Math.sin(angleRad) + my;
                    c.style.setProperty('--tx', `${tx}px`);
                    c.style.setProperty('--ty', `${ty}px`);
                });
            };
            window.addEventListener('mousemove', handleParallax);

            setTimeout(() => {
                const btn = document.getElementById('portal-continue-' + slideNum);
                if (btn) {
                    btn.classList.remove('hidden');
                    btn.classList.add('flex');
                    setTimeout(() => {
                        btn.style.transform = 'scale(1)';
                        btn.style.opacity = '1';
                    }, 100);
                }
            }, dreams.length * 600 + 2500);
        }

        // 6. Feelings Countdown
        function startProposalCountdown(slideNum) {
            const container = document.getElementById(`proposal-countdown-display-${slideNum}`);
            if (!container) return;

            const daysEl = document.getElementById(`countdown-days-${slideNum}`);
            const hoursEl = document.getElementById(`countdown-hours-${slideNum}`);
            const minutesEl = document.getElementById(`countdown-minutes-${slideNum}`);
            const heart = document.getElementById(`countdown-heart-${slideNum}`);

            const baseDate = relationshipDate ? new Date(relationshipDate) : new Date(Date.now() - 365*24*60*60*1000);

            function updateCounter() {
                const diffMs = Math.max(0, Date.now() - baseDate.getTime());
                const totalMins = Math.floor(diffMs / (1000 * 60));
                const totalHours = Math.floor(totalMins / 60);
                const totalDays = Math.floor(totalHours / 24);

                const finalMins = totalMins % 60;
                const finalHours = totalHours % 24;

                if (daysEl) daysEl.textContent = String(totalDays).padStart(3, '0');
                if (hoursEl) hoursEl.textContent = String(finalHours).padStart(2, '0');
                if (minutesEl) minutesEl.textContent = String(finalMins).padStart(2, '0');
            }

            updateCounter();
            countdownFeelingsInterval = setInterval(updateCounter, 30000);

            let duration = 1.5;
            const accelTimer = setInterval(() => {
                duration -= 0.15;
                if (duration <= 0.6) {
                    duration = 0.6;
                    clearInterval(accelTimer);
                }
                if (heart) {
                    heart.style.setProperty('--beat-duration', `${duration}s`);
                }
            }, 800);

            if (heart) {
                heart.onclick = () => {
                    playSystemSound('heartbeat');
                };
            }
        }

        // 7. Ring Box Cinematic
        function openRingCinematic(slideNum) {
            if (ringBoxOpened[slideNum]) return;
            ringBoxOpened[slideNum] = true;

            const lid = document.getElementById(`ring-box-lid-${slideNum}`);
            const ring = document.getElementById(`ring-box-diamond-g-${slideNum}`);
            const rays = document.getElementById(`ring-cinematic-rays-${slideNum}`);
            const hint = document.getElementById(`ring-cinematic-hint-${slideNum}`);

            if (hint) hint.classList.add('opacity-0');

            playSystemSound('shing');

            if (lid) lid.style.transform = 'translateY(-30px) scaleY(0.4)';

            setTimeout(() => {
                if (ring) {
                    ring.classList.remove('opacity-0', 'scale-0');
                    ring.classList.add('opacity-100', 'scale-100');
                }
                playSystemSound('sparkle');

                const flash = document.createElement('div');
                flash.className = 'fixed inset-0 bg-white z-[99] pointer-events-none transition-opacity duration-300 opacity-100';
                document.body.appendChild(flash);
                setTimeout(() => {
                    flash.style.opacity = '0';
                    setTimeout(() => flash.remove(), 300);
                }, 50);

                if (rays) {
                    rays.classList.add('ring-light-rays-active');
                    rays.querySelectorAll('.ring-light-ray').forEach((ray, ri) => {
                        setTimeout(() => {
                            ray.style.opacity = '1';
                        }, ri * 80);
                    });
                }
            }, 400);

            setTimeout(() => {
                const btn = document.getElementById(`ring-cinematic-continue-${slideNum}`);
                if (btn) {
                    btn.classList.remove('hidden');
                    btn.classList.add('flex');
                    setTimeout(() => {
                        btn.style.transform = 'scale(1)';
                        btn.style.opacity = '1';
                    }, 100);
                }
            }, 2000);
        }

        function finishRingCinematic(slideNum) {
            const container = document.getElementById(`ring-cinematic-container-${slideNum}`);
            if (container) {
                container.style.transition = 'opacity 0.8s ease-out';
                container.classList.remove('opacity-100');
                container.classList.add('opacity-0');
                setTimeout(() => {
                    container.remove();
                    goSlide(slideNum + 1);
                }, 800);
            } else {
                goSlide(slideNum + 1);
            }
        }

        // 8. Final Proposal Cinematic
        function runFinalProposalCinematic(slideNum) {
            dimBackgroundMusic();
            const container = document.getElementById(`final-cinematic-container-${slideNum}`);
            if (!container) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const canvas = document.getElementById(`final-stars-canvas-${slideNum}`);
            if (canvas) {
                const ctx = canvas.getContext('2d');
                canvas.width = canvas.clientWidth || canvas.offsetWidth || 375;
                canvas.height = canvas.clientHeight || canvas.offsetHeight || 667;

                const stars = [];
                for (let i = 0; i < 120; i++) {
                    stars.push({
                        x: Math.random() * canvas.width,
                        y: Math.random() * canvas.height,
                        size: Math.random() * 1.5,
                        alpha: Math.random(),
                        speed: 0.01 + Math.random() * 0.02
                    });
                }

                function starAnim() {
                    ctx.fillStyle = '#050308';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    stars.forEach(s => {
                        s.alpha += s.speed;
                        if (s.alpha > 1 || s.alpha < 0) s.speed = -s.speed;
                        ctx.fillStyle = `rgba(255, 255, 255, ${Math.max(0.2, s.alpha)})`;
                        ctx.beginPath();
                        ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
                        ctx.fill();
                    });
                    finalStarsAnimId = requestAnimationFrame(starAnim);
                }
                starAnim();
            }

            const petalsContainer = document.getElementById(`final-petals-${slideNum}`);
            if (petalsContainer) {
                proposalPetalsTimer = setInterval(() => {
                    const p = document.createElement('span');
                    p.className = 'falling-petal-emoji';
                    p.textContent = "🌹";
                    p.style.left = `${Math.random() * 100}%`;
                    const drift = (Math.random() - 0.5) * 120;
                    const dur = 6 + Math.random() * 4;
                    p.style.setProperty('--drift', `${drift}px`);
                    p.style.setProperty('--dur', `${dur}s`);
                    petalsContainer.appendChild(p);
                    setTimeout(() => p.remove(), dur * 1000);
                }, 600);
            }

            const heartPhotoContainer = document.getElementById(`final-photo-heart-${slideNum}`);
            const imgCount = pageImages.length;
            if (heartPhotoContainer && imgCount > 0) {
                const w = heartPhotoContainer.clientWidth || heartPhotoContainer.offsetWidth || 375;
                const h = heartPhotoContainer.clientHeight || heartPhotoContainer.offsetHeight || 667;
                const cx = w / 2;
                const cy = h / 2 - 20;
                const scale = w > 768 ? 6.5 : 4.0;
                
                setTimeout(() => {
                    for (let i = 0; i < imgCount; i++) {
                        setTimeout(() => {
                            const t = Math.PI * 2 * (i / imgCount);
                            const hpos = heartPosition(t, scale, cx, cy);

                            const imgCard = document.createElement('div');
                            imgCard.className = 'polaroid-heart-shape';
                            imgCard.style.left = `${hpos.x - 25}px`;
                            imgCard.style.top = `${hpos.y - 25}px`;
                            imgCard.style.transform = 'scale(0) rotate(180deg)';
                            imgCard.innerHTML = `<img src="${baseUrl}${pageImages[i].medium_path || pageImages[i].image_path}" alt="heart">`;
                            heartPhotoContainer.appendChild(imgCard);

                            setTimeout(() => {
                                imgCard.style.transform = 'scale(1) rotate(0deg)';
                                playSystemSound('pop');
                            }, 50);
                        }, i * 350);
                    }
                }, 1000);
            }

            const promptEl = document.getElementById(`final-proposal-text-${slideNum}`);
            const optionsEl = document.getElementById(`final-proposal-options-${slideNum}`);
            const proposalText = "Will You Be Mine? 💖";

            setTimeout(() => {
                typeWriterEffect(promptEl, proposalText, 70, () => {
                    playSystemSound('success');
                    if (optionsEl) {
                        optionsEl.classList.remove('opacity-0');
                        optionsEl.classList.add('opacity-100');
                    }
                });
            }, imgCount * 350 + 1500);

            const noBtn = document.getElementById(`final-no-btn-${slideNum}`);
            let noAttempts = 0;
            
            const dodgeBtn = (e) => {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                noAttempts++;
                
                if (noAttempts <= 5) {
                    const dx = (Math.random() - 0.5) * 120;
                    const dy = (Math.random() - 0.5) * 60;
                    noBtn.style.transform = `translate(${dx}px, ${dy}px)`;
                }

                if (noAttempts > 3 && noAttempts <= 5) {
                    noBtn.style.fontSize = `${Math.max(8, 14 - noAttempts)}px`;
                }

                if (noAttempts >= 6) {
                    noBtn.classList.add('hidden');
                }
            };

            if (noBtn) {
                noBtn.addEventListener('mouseover', dodgeBtn);
                noBtn.addEventListener('touchstart', dodgeBtn);
                noBtn.addEventListener('click', dodgeBtn);
            }
        }

        function handleFinalProposalYes(slideNum) {
            sendReaction('loved');
            playSystemSound('chime');
            
            confetti({ particleCount: 300, spread: 100, origin: { y: 0.5 } });
            setTimeout(() => confetti({ particleCount: 150, spread: 80, origin: { x: 0.2, y: 0.6 } }), 300);
            setTimeout(() => confetti({ particleCount: 150, spread: 80, origin: { x: 0.8, y: 0.6 } }), 600);

            const optionsEl = document.getElementById(`final-proposal-options-${slideNum}`);
            const textEl = document.getElementById(`final-proposal-text-${slideNum}`);
            if (optionsEl) optionsEl.classList.add('hidden');
            if (textEl) textEl.classList.add('hidden');

            const successEl = document.getElementById(`final-celebration-${slideNum}`);
            if (successEl) {
                successEl.classList.remove('opacity-0', 'scale-75');
                successEl.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
            }
        }

        // ═══════════════════════════════════════════════════
        // UNIVERSAL INTERACTIVE ENDING JS ENGINE
        // ═══════════════════════════════════════════════════
        let noBtnClicks = 0;
        let activeClones = [];
        let lastDodgeTime = 0;

        function dodgeNoButton(e, slideNum) {
            // Handle case where function is called with legacy signature (without event object)
            if (e && typeof e === 'number') {
                slideNum = e;
                e = null;
            }
            
            const now = Date.now();
            if (now - lastDodgeTime < 250) {
                if (e && e.preventDefault) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                return;
            }
            lastDodgeTime = now;

            if (e && e.preventDefault) {
                e.preventDefault();
                e.stopPropagation();
            }

            noBtnClicks++;
            const noBtn = document.getElementById(`no-btn-${slideNum}`);
            const yesBtn = document.getElementById(`yes-btn-${slideNum}`);
            const container = document.getElementById(`choice-container-${slideNum}`);
            
            if (!noBtn || !container) return;
            
            const areaWidth = container.offsetWidth || 300;
            const areaHeight = container.offsetHeight || 120;
            
            const btnWidth = noBtn.offsetWidth || 80;
            const btnHeight = noBtn.offsetHeight || 40;
            
            const maxX = areaWidth - btnWidth - 10;
            const maxY = areaHeight - btnHeight - 10;

            playSystemSound('pop');

            if (noBtnClicks <= 3) {
                // Stage 1-3: Random Dodge
                const randomX = Math.max(10, Math.random() * maxX);
                const randomY = Math.max(10, Math.random() * maxY);
                
                noBtn.style.position = 'absolute';
                noBtn.style.left = randomX + 'px';
                noBtn.style.top = randomY + 'px';
            } 
            else if (noBtnClicks <= 6) {
                // Stage 4-6: Clone Prank
                const randomX = Math.max(10, Math.random() * maxX);
                const randomY = Math.max(10, Math.random() * maxY);
                
                noBtn.style.position = 'absolute';
                noBtn.style.left = randomX + 'px';
                noBtn.style.top = randomY + 'px';

                // Spawn a clone
                const clone = noBtn.cloneNode(true);
                clone.removeAttribute('onmouseover');
                clone.removeAttribute('onclick');
                clone.id = `no-btn-clone-${Math.random().toString(36).substr(2, 9)}`;
                clone.style.left = Math.max(10, Math.random() * maxX) + 'px';
                clone.style.top = Math.max(10, Math.random() * maxY) + 'px';
                
                // Add hover/click listener to clone without executing parent dodge logic
                clone.onmouseover = function(evt) {
                    if (evt) { evt.preventDefault(); evt.stopPropagation(); }
                    clone.style.left = Math.max(10, Math.random() * maxX) + 'px';
                    clone.style.top = Math.max(10, Math.random() * maxY) + 'px';
                    playSystemSound('pop');
                };
                clone.onclick = function(evt) {
                    if (evt) { evt.preventDefault(); evt.stopPropagation(); }
                    clone.style.left = Math.max(10, Math.random() * maxX) + 'px';
                    clone.style.top = Math.max(10, Math.random() * maxY) + 'px';
                    playSystemSound('pop');
                };
                
                container.appendChild(clone);
                activeClones.push(clone);
            } 
            else if (noBtnClicks <= 8) {
                // Stage 7-8: Shrink NO / Grow YES
                const shrinkFactor = 1 - (noBtnClicks - 6) * 0.25;
                const growFactor = 1 + (noBtnClicks - 6) * 0.6;
                
                noBtn.style.transform = `scale(${shrinkFactor})`;
                if (yesBtn) {
                    yesBtn.style.transform = `scale(${growFactor})`;
                    yesBtn.style.zIndex = '50';
                }

                const randomX = Math.max(10, Math.random() * maxX);
                const randomY = Math.max(10, Math.random() * maxY);
                noBtn.style.left = randomX + 'px';
                noBtn.style.top = randomY + 'px';
            } 
            else {
                // Stage 9: Auto-convert
                noBtn.textContent = yesBtn ? yesBtn.textContent : 'Yes! ❤️';
                noBtn.className = yesBtn ? yesBtn.className : noBtn.className;
                noBtn.style.position = 'relative';
                noBtn.style.left = 'auto';
                noBtn.style.top = 'auto';
                noBtn.style.transform = 'scale(1)';
                
                // Remove all clones
                activeClones.forEach(c => c.remove());
                activeClones = [];
                
                // Change click handler
                noBtn.onmouseover = null;
                noBtn.onclick = function() {
                    handleUniversalYes(slideNum);
                };
                
                // Automatically trigger yes
                handleUniversalYes(slideNum);
            }
        }

        function handleUniversalYes(slideNum) {
            playSystemSound('chime');
            confetti({ particleCount: 200, spread: 80, origin: { y: 0.6 } });
            
            // Clean clones
            activeClones.forEach(c => c.remove());
            activeClones = [];
            
            // Hide choice container, show success
            const container = document.getElementById(`choice-container-${slideNum}`);
            const success = document.getElementById(`choice-success-${slideNum}`);
            if (container) container.classList.add('hidden');
            if (success) {
                success.classList.remove('hidden');
                success.classList.add('flex');
            }
            
            // Submit API reply
            submitInteractiveReply('yes');
        }

        function handleUniversalNo(slideNum) {
            playSystemSound('chime');
            
            const container = document.getElementById(`choice-container-${slideNum}`);
            const success = document.getElementById(`choice-success-${slideNum}`);
            if (container) container.classList.add('hidden');
            if (success) {
                const h2 = success.querySelector('h2');
                const p = success.querySelector('p');
                if (h2) h2.textContent = "Rejected... 💔";
                if (p) p.textContent = "Your response has been saved. We hope everything heals.";
                
                success.classList.remove('hidden');
                success.classList.add('flex');
            }
            
            submitInteractiveReply('no');
        }

        function submitInteractiveReply(answer) {
            const data = {
                page_id: pageId,
                visitor_name: visitorName,
                question: document.getElementById('choice-prompt-' + currentSlide) ? document.getElementById('choice-prompt-' + currentSlide).textContent.trim() : 'Interactive Decision',
                selected_answer: answer,
                positive_button_text: document.getElementById('yes-btn-' + currentSlide) ? document.getElementById('yes-btn-' + currentSlide).textContent.trim() : 'Yes! ❤️',
                negative_button_text: document.getElementById('no-btn-' + currentSlide) ? document.getElementById('no-btn-' + currentSlide).textContent.trim() : 'No',
                no_click_count: noBtnClicks
            };
            
            fetch(baseUrl + 'api.php?action=submit_interactive_reply', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(res => {
                console.log('Universal interactive reply submitted:', res);
            })
            .catch(err => {
                console.error('Error submitting interactive reply:', err);
            });
        }

        function finishFinalProposal(slideNum) {
            if (proposalPetalsTimer) clearInterval(proposalPetalsTimer);
            if (finalStarsAnimId) cancelAnimationFrame(finalStarsAnimId);
            restoreBackgroundMusic();

            const container = document.getElementById(`final-cinematic-container-${slideNum}`);
            if (container) {
                container.style.transition = 'opacity 0.8s ease-out';
                container.classList.remove('opacity-100');
                container.classList.add('opacity-0');
                setTimeout(() => {
                    container.remove();
                    goSlide(slideNum + 1);
                }, 800);
            } else {
                goSlide(slideNum + 1);
            }
        }

        // 9. Celebration Screen Cinematic
        function runCelebrationCinematic(slideNum) {
            const container = document.getElementById(`celebration-container-${slideNum}`);
            if (!container) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const canvas = document.getElementById(`celebration-confetti-${slideNum}`);
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.clientWidth || canvas.offsetWidth || 375;
            canvas.height = canvas.clientHeight || canvas.offsetHeight || 667;

            const confettis = [];
            for (let i = 0; i < 60; i++) {
                confettis.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * -canvas.height,
                    size: 4 + Math.random() * 6,
                    color: `hsl(${Math.random() * 360}, 90%, 65%)`,
                    vy: 1 + Math.random() * 2,
                    vx: (Math.random() - 0.5) * 1.5
                });
            }

            function drawConfetti() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                confettis.forEach(c => {
                    c.y += c.vy;
                    c.x += c.vx;
                    if (c.y > canvas.height) {
                        c.y = -20;
                        c.x = Math.random() * canvas.width;
                    }
                    ctx.fillStyle = c.color;
                    ctx.fillRect(c.x, c.y, c.size, c.size);
                });
                celebrationConfettiAnimId = requestAnimationFrame(drawConfetti);
            }
            drawConfetti();

            const photosContainer = document.getElementById(`celebration-photos-${slideNum}`);
            const imgCount = pageImages.length;
            if (photosContainer && imgCount > 0) {
                for (let i = 0; i < imgCount; i++) {
                    setTimeout(() => {
                        const imgCard = document.createElement('div');
                        imgCard.className = 'floating-celebration-photo';
                        
                        const rx = 10 + Math.random() * 80;
                        const ry = 15 + Math.random() * 60;
                        imgCard.style.left = `${rx}%`;
                        imgCard.style.top = `${ry}%`;

                        const rot = (Math.random() - 0.5) * 36;
                        imgCard.style.transform = `scale(0.8) rotate(${rot}deg)`;
                        imgCard.innerHTML = `<img src="${baseUrl}${pageImages[i].medium_path || pageImages[i].image_path}" alt="celebration">`;
                        photosContainer.appendChild(imgCard);

                        setTimeout(() => {
                            imgCard.style.opacity = '0.35';
                        }, 50);
                    }, i * 500);
                }
            }

            const quoteEl = document.getElementById(`celebration-quote-${slideNum}`);
            const quoteText = "Every love story is beautiful, but ours is my absolute favorite. ❤️";
            setTimeout(() => {
                typeWriterEffect(quoteEl, quoteText, 45);
            }, 1000);
        }

        function copyStoryLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copied! Share it with friends 🎉');
            });
        }

        // =========================================================================
        // LOVE LETTER CATEGORY CINEMATIC ENGINES
        // =========================================================================
        const scrapbookFlipped = {};
        const dreamsPopped = {};
        const currentFoldStep = {};
        const voiceWaveAnimIds = {};
        const voiceWaveAmplitudes = {};
        const voiceWaveTargets = {};
        const voiceWavePhases = {};
        let musicPausedByLoveVoice = false;
        let musicPausedByLoveVideo = false;
        let melodyInterval = null;
        let melodyCtx = null;
        let finaleHeartsInterval = null;

        // Slide 2: Wax-sealed Envelope Opening
        function initLoveEnvelopeCinematic(slideNum) {
            dimBackgroundMusic();
            const container = document.getElementById(`envelope-cinematic-container-${slideNum}`);
            if (!container) return;
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const seal = document.getElementById(`wax-seal-3d-${slideNum}`);
            const flap = document.getElementById(`envelope-flap-3d-${slideNum}`);
            const letter = document.getElementById(`envelope-letter-slide-${slideNum}`);
            const hint = document.getElementById(`envelope-cinematic-hint-${slideNum}`);
            const btn = document.getElementById(`envelope-cinematic-continue-${slideNum}`);

            if (seal) seal.classList.remove('cracked');
            if (flap) flap.classList.remove('open');
            if (letter) letter.classList.remove('slide-out');
            if (hint) {
                hint.textContent = "Tap the wax seal to break it ✉️";
                hint.classList.remove('hidden');
            }
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');

            envelopeOpened[slideNum] = false;
        }

        function openLoveEnvelopeCinematic(slideNum) {
            if (envelopeOpened[slideNum]) return;
            envelopeOpened[slideNum] = true;

            const seal = document.getElementById(`wax-seal-3d-${slideNum}`);
            const flap = document.getElementById(`envelope-flap-3d-${slideNum}`);
            const letter = document.getElementById(`envelope-letter-slide-${slideNum}`);
            const hint = document.getElementById(`envelope-cinematic-hint-${slideNum}`);
            const btn = document.getElementById(`envelope-cinematic-continue-${slideNum}`);

            playSystemSound('wax_crack');
            if (seal) seal.classList.add('cracked');

            setTimeout(() => {
                playSystemSound('paper_rustle');
                if (flap) flap.classList.add('open');
            }, 500);

            setTimeout(() => {
                playSystemSound('paper_rustle');
                if (letter) letter.classList.add('slide-out');
            }, 1300);

            setTimeout(() => {
                if (hint) hint.classList.add('hidden');
                if (btn) {
                    btn.classList.remove('hidden');
                    setTimeout(() => {
                        btn.classList.remove('scale-0', 'opacity-0');
                        btn.classList.add('scale-100', 'opacity-100');
                    }, 50);
                }
            }, 2500);
        }

        // Slide 3: Ink Handwriting Animation
        function runLoveLetterHandwrite(slideNum) {
            dimBackgroundMusic();
            const container = document.getElementById(`love-letter-handwrite-container-${slideNum}`);
            if (!container) return;
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const paper = document.getElementById(`love-letter-paper-${slideNum}`);
            if (paper) {
                setTimeout(() => {
                    paper.classList.add('active');
                }, 50);
            }

            const textTarget = document.getElementById(`love-letter-handwrite-lines-${slideNum}`);
            if (!textTarget) return;
            textTarget.innerHTML = '';

            const quill = document.getElementById(`love-letter-quill-${slideNum}`);
            if (quill) {
                quill.classList.remove('hidden');
                quill.style.opacity = '1';
            }

            const btn = document.getElementById(`love-letter-handwrite-continue-${slideNum}`);
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');

            const p = document.createElement('p');
            p.className = 'font-serif text-[#5c3e21] text-base md:text-lg leading-relaxed whitespace-pre-line';
            textTarget.appendChild(p);

            const scrollContainer = document.getElementById(`love-letter-handwrite-text-${slideNum}`);

            let charIndex = 0;
            function typeChar() {
                if (charIndex < letterText.length) {
                    const char = letterText[charIndex];
                    p.textContent += char;
                    charIndex++;

                    if (quill && scrollContainer) {
                        const marker = document.createElement('span');
                        marker.style.position = 'relative';
                        marker.style.display = 'inline-block';
                        marker.innerHTML = '&nbsp;';
                        p.appendChild(marker);

                        const parentRect = scrollContainer.getBoundingClientRect();
                        const markerRect = marker.getBoundingClientRect();
                        const left = markerRect.left - parentRect.left;
                        const top = markerRect.top - parentRect.top + scrollContainer.scrollTop;

                        quill.style.left = `${left + 5}px`;
                        quill.style.top = `${top - 20}px`;

                        p.removeChild(marker);
                    }

                    if (scrollContainer) {
                        scrollContainer.scrollTop = scrollContainer.scrollHeight;
                    }

                    if (charIndex % 5 === 0) {
                        playSystemSound('paper_rustle');
                    }

                    setTimeout(typeChar, 25);
                } else {
                    if (quill) {
                        quill.style.transition = 'opacity 0.5s';
                        quill.style.opacity = '0';
                        setTimeout(() => quill.classList.add('hidden'), 500);
                    }
                    if (btn) {
                        btn.classList.remove('hidden');
                        setTimeout(() => {
                            btn.classList.remove('scale-0', 'opacity-0');
                            btn.classList.add('scale-100', 'opacity-100');
                        }, 50);
                    }
                }
            }
            setTimeout(typeChar, 150);
        }

        // Slide 4: Scrapbook Page Flip Interactive
        function runLoveLetterScrapbook(slideNum) {
            const container = document.getElementById(`scrapbook-container-${slideNum}`);
            if (!container) return;
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            scrapbookFlipped[slideNum] = [false, false, false];

            const sheets = document.querySelectorAll(`[id^="scrapbook-sheet-${slideNum}-"]`);
            sheets.forEach((sheet, idx) => {
                sheet.classList.remove('flipped');
                const newSheet = sheet.cloneNode(true);
                sheet.parentNode.replaceChild(newSheet, sheet);

                newSheet.addEventListener('click', () => {
                    handleScrapbookPageClick(slideNum, idx);
                });
            });

            updateScrapbookZIndices(slideNum);

            const btn = document.getElementById(`scrapbook-continue-${slideNum}`);
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');
            const hint = document.getElementById(`scrapbook-hint-${slideNum}`);
            if (hint) {
                hint.textContent = "Tap on the right pages to flip them 📖";
                hint.classList.remove('hidden');
            }
        }

        function handleScrapbookPageClick(slideNum, idx) {
            const sheets = document.querySelectorAll(`[id^="scrapbook-sheet-${slideNum}-"]`);
            const sheet = sheets[idx];
            const isFlipped = sheet.classList.contains('flipped');

            if (isFlipped) {
                let canFlipBack = true;
                for (let i = idx + 1; i < sheets.length; i++) {
                    if (sheets[i].classList.contains('flipped')) {
                        canFlipBack = false;
                        break;
                    }
                }

                if (canFlipBack) {
                    playSystemSound('page_flip');
                    sheet.classList.remove('flipped');
                    scrapbookFlipped[slideNum][idx] = false;
                    updateScrapbookZIndices(slideNum);
                }
            } else {
                let canFlipForward = true;
                for (let i = 0; i < idx; i++) {
                    if (!sheets[i].classList.contains('flipped')) {
                        canFlipForward = false;
                        break;
                    }
                }

                if (canFlipForward) {
                    playSystemSound('page_flip');
                    sheet.classList.add('flipped');
                    scrapbookFlipped[slideNum][idx] = true;
                    updateScrapbookZIndices(slideNum);
                }
            }

            const allFlipped = scrapbookFlipped[slideNum].every(val => val === true);
            const btn = document.getElementById(`scrapbook-continue-${slideNum}`);
            const hint = document.getElementById(`scrapbook-hint-${slideNum}`);

            if (allFlipped) {
                if (hint) hint.classList.add('hidden');
                if (btn) {
                    btn.classList.remove('hidden');
                    setTimeout(() => {
                        btn.classList.remove('scale-0', 'opacity-0');
                        btn.classList.add('scale-100', 'opacity-100');
                    }, 50);
                }
            } else {
                if (hint) hint.classList.remove('hidden');
                if (btn) {
                    btn.classList.remove('scale-100', 'opacity-100');
                    btn.classList.add('scale-0', 'opacity-0');
                }
            }
        }

        function updateScrapbookZIndices(slideNum) {
            const sheets = document.querySelectorAll(`[id^="scrapbook-sheet-${slideNum}-"]`);
            sheets.forEach((sheet, idx) => {
                if (sheet.classList.contains('flipped')) {
                    sheet.style.zIndex = 10 + idx;
                } else {
                    sheet.style.zIndex = 20 + (sheets.length - idx);
                }
            });
        }

        // Slide 5: Memory Timeline Scroll Indicator
        function runLoveLetterTimeline(slideNum) {
            const container = document.getElementById(`timeline-container-${slideNum}`);
            if (!container) return;
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const progressLine = document.getElementById(`timeline-progress-${slideNum}`);
            if (progressLine) progressLine.style.height = '0%';

            const milestones = container.querySelectorAll('.timeline-milestone');
            milestones.forEach((ms, idx) => {
                if (idx === 0) {
                    ms.classList.add('active');
                } else {
                    ms.classList.remove('active');
                }
            });

            const btn = document.getElementById(`timeline-continue-${slideNum}`);
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');
            const hint = document.getElementById(`timeline-hint-${slideNum}`);
            if (hint) hint.classList.remove('hidden');

            setTimeout(() => {
                handleTimelineScroll(slideNum);
            }, 100);
        }

        function handleTimelineScroll(slideNum) {
            const scrollContainer = document.getElementById(`timeline-scroll-${slideNum}`);
            if (!scrollContainer) return;

            const progressLine = document.getElementById(`timeline-progress-${slideNum}`);
            const maxScroll = scrollContainer.scrollHeight - scrollContainer.clientHeight;
            const scrollPercent = maxScroll > 0 ? (scrollContainer.scrollTop / maxScroll) : 0;

            if (progressLine) {
                progressLine.style.height = `${scrollPercent * 92}%`;
            }

            const milestones = scrollContainer.querySelectorAll('.timeline-milestone');
            milestones.forEach((ms, idx) => {
                const msRect = ms.getBoundingClientRect();
                const containerRect = scrollContainer.getBoundingClientRect();

                const msCenter = msRect.top - containerRect.top + (msRect.height / 2);
                const containerCenter = containerRect.height / 1.6;

                if (msCenter < containerCenter) {
                    ms.classList.add('active');
                } else {
                    if (idx > 0) ms.classList.remove('active');
                }
            });

            const btn = document.getElementById(`timeline-continue-${slideNum}`);
            const hint = document.getElementById(`timeline-hint-${slideNum}`);
            if (scrollPercent > 0.85) {
                if (hint) hint.classList.add('hidden');
                if (btn) {
                    btn.classList.remove('hidden');
                    setTimeout(() => {
                        btn.classList.remove('scale-0', 'opacity-0');
                        btn.classList.add('scale-100', 'opacity-100');
                    }, 50);
                }
            }
        }

        // Slide 6: Future Dreams Floating Bubbles
        const dreamsData = [
            { title: "Cozy Mornings ☕", desc: "Waking up to fresh coffee, sharing quiet mornings, and starting every single day with your warm smile." },
            { title: "Travel Together 🗺️", desc: "Exploring new cities, lost in foreign streets, but always feeling at home because I'm with you." },
            { title: "Our Sanctuary 🏡", desc: "Building a peaceful home filled with books, warm lights, our favorite music, and endless late-night chats." },
            { title: "Grow Old Together 👴👵", desc: "Holding hands through every season of life, watching the years pass, and loving you more each day." },
            { title: "Endless Laughter 🌙", desc: "Sharing silly inside jokes and laughing until our stomachs hurt, even when the world gets heavy." }
        ];

        function runLoveLetterFutureDreams(slideNum) {
            const container = document.getElementById(`future-dreams-container-${slideNum}`);
            if (!container) return;
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            dreamsPopped[slideNum] = new Set();

            const bubblesContainer = document.getElementById(`future-dreams-bubbles-${slideNum}`);
            if (!bubblesContainer) return;
            bubblesContainer.innerHTML = '';

            let detailModal = document.getElementById(`dreams-detail-modal-${slideNum}`);
            if (!detailModal) {
                detailModal = document.createElement('div');
                detailModal.id = `dreams-detail-modal-${slideNum}`;
                detailModal.className = 'absolute inset-x-6 top-[30%] bg-stone-950/95 border border-pink-500/30 backdrop-blur-xl rounded-3xl p-6 shadow-2xl z-50 text-center flex flex-col items-center justify-center scale-0 opacity-0 transition-all duration-500 pointer-events-none';
                bubblesContainer.parentNode.appendChild(detailModal);
            }

            const positions = [
                { top: '22%', left: '10%', size: '105px', delay: '0s' },
                { top: '35%', left: '60%', size: '120px', delay: '1s' },
                { top: '55%', left: '8%', size: '115px', delay: '2s' },
                { top: '68%', left: '58%', size: '110px', delay: '1.5s' },
                { top: '18%', left: '62%', size: '95px', delay: '0.5s' }
            ];

            dreamsData.forEach((dream, idx) => {
                const bubble = document.createElement('div');
                bubble.className = 'love-dream-bubble';
                const pos = positions[idx % positions.length];

                bubble.style.top = pos.top;
                bubble.style.left = pos.left;
                bubble.style.width = pos.size;
                bubble.style.height = pos.size;
                bubble.style.setProperty('--float-delay', pos.delay);
                bubble.style.fontSize = '12px';
                bubble.style.fontWeight = 'bold';
                bubble.style.padding = '8px';
                bubble.innerHTML = `<div>${dream.title}</div>`;

                bubble.addEventListener('click', (e) => {
                    e.stopPropagation();
                    playBubblePopSound();
                    bubble.classList.add('revealed');
                    dreamsPopped[slideNum].add(idx);

                    showDreamDetail(slideNum, dream.title, dream.desc);

                    const btn = document.getElementById(`future-dreams-continue-${slideNum}`);
                    const hint = document.getElementById(`future-dreams-hint-${slideNum}`);
                    if (dreamsPopped[slideNum].size >= 3) {
                        if (hint) hint.classList.add('hidden');
                        if (btn) {
                            btn.classList.remove('hidden');
                            setTimeout(() => {
                                btn.classList.remove('scale-0', 'opacity-0');
                                btn.classList.add('scale-100', 'opacity-100');
                            }, 50);
                        }
                    }
                });

                bubblesContainer.appendChild(bubble);
            });

            container.addEventListener('click', () => {
                hideDreamDetail(slideNum);
            });
        }

        function showDreamDetail(slideNum, title, desc) {
            const modal = document.getElementById(`dreams-detail-modal-${slideNum}`);
            if (!modal) return;

            modal.innerHTML = `
                <h3 class="text-lg font-bold text-pink-300 font-heading mb-2">${title}</h3>
                <p class="text-sm italic text-stone-200 font-serif leading-relaxed mb-4">"${desc}"</p>
                <button class="px-5 py-2 bg-pink-600/30 hover:bg-pink-600/50 text-pink-200 border border-pink-500/30 text-xs font-semibold uppercase tracking-wider rounded-xl transition">Close</button>
            `;
            modal.classList.remove('scale-0', 'opacity-0', 'pointer-events-none');
            modal.classList.add('scale-100', 'opacity-100', 'pointer-events-auto');
        }

        function hideDreamDetail(slideNum) {
            const modal = document.getElementById(`dreams-detail-modal-${slideNum}`);
            if (!modal) return;
            modal.classList.remove('scale-100', 'opacity-100', 'pointer-events-auto');
            modal.classList.add('scale-0', 'opacity-0', 'pointer-events-none');
        }

        function playBubblePopSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(400, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1200, audioCtx.currentTime + 0.1);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.1);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.1);
            } catch (e) {
                console.log("Audio synthesis blocked", e);
            }
        }

        // Slide 7: Voice Wave Siri-Style Visualizer
        function initLoveVoiceWave(slideNum) {
            const canvas = document.getElementById(`voice-wave-canvas-${slideNum}`);
            if (!canvas) return;

            if (voiceWaveAnimIds[slideNum]) {
                cancelAnimationFrame(voiceWaveAnimIds[slideNum]);
            }

            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth * window.devicePixelRatio;
            canvas.height = canvas.offsetHeight * window.devicePixelRatio;
            ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

            voiceWaveAmplitudes[slideNum] = 0;
            voiceWaveTargets[slideNum] = 0;
            voiceWavePhases[slideNum] = 0;

            const audio = document.getElementById(`voice-audio-element-${slideNum}`);
            if (audio) {
                audio.pause();
                audio.currentTime = 0;
            }

            const playIcon = document.getElementById(`voice-btn-icon-${slideNum}`);
            if (playIcon) playIcon.textContent = '▶️';

            const label = document.getElementById(`voice-duration-label-${slideNum}`);
            if (label) label.textContent = 'Play my recorded message';

            const glowRing = document.getElementById(`voice-glow-ring-${slideNum}`);
            if (glowRing) {
                glowRing.classList.remove('love-voice-glow-active');
                glowRing.style.transform = 'translate(-50%, -50%) scale(1)';
                glowRing.style.opacity = '0';
            }

            const btn = document.getElementById(`voice-wave-continue-${slideNum}`);
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');

            function draw() {
                const width = canvas.width / window.devicePixelRatio;
                const height = canvas.height / window.devicePixelRatio;
                ctx.clearRect(0, 0, width, height);

                voiceWaveAmplitudes[slideNum] += (voiceWaveTargets[slideNum] - voiceWaveAmplitudes[slideNum]) * 0.1;
                const amp = voiceWaveAmplitudes[slideNum];

                voiceWavePhases[slideNum] += amp > 0.01 ? 0.15 : 0.03;
                const phase = voiceWavePhases[slideNum];

                const waves = [
                    { amplitude: 22, frequency: 0.018, opacity: 0.15, color: '#f43f5e' },
                    { amplitude: 14, frequency: 0.028, opacity: 0.35, color: '#ec4899' },
                    { amplitude: 8, frequency: 0.04, opacity: 0.65, color: '#fda4af' }
                ];

                waves.forEach((w, waveIdx) => {
                    ctx.beginPath();
                    ctx.strokeStyle = w.color;
                    ctx.globalAlpha = w.opacity;
                    ctx.lineWidth = waveIdx === 2 ? 2.5 : 1.5;

                    for (let x = 0; x < width; x++) {
                        const edgeDecay = Math.sin((x / width) * Math.PI);
                        const noise = amp > 0.01 ? Math.sin(phase * 2 + x * 0.1) * 2 * amp : 0;
                        const y = (height / 2) + Math.sin(x * w.frequency + phase + (waveIdx * Math.PI / 3)) * (w.amplitude * amp + noise) * edgeDecay;

                        if (x === 0) {
                            ctx.moveTo(x, y);
                        } else {
                            ctx.lineTo(x, y);
                        }
                    }
                    ctx.stroke();
                });

                if (glowRing && amp > 0.1) {
                    const scale = 1 + Math.sin(phase * 4) * 0.08 * amp;
                    glowRing.style.transform = `translate(-50%, -50%) scale(${scale})`;
                    glowRing.style.opacity = 0.3 + Math.sin(phase * 4) * 0.2;
                } else if (glowRing) {
                    glowRing.style.transform = 'translate(-50%, -50%) scale(1)';
                    glowRing.style.opacity = '0';
                }

                voiceWaveAnimIds[slideNum] = requestAnimationFrame(draw);
            }
            draw();
        }

        function toggleLoveVoice(slideNum) {
            const audio = document.getElementById(`voice-audio-element-${slideNum}`);
            const playIcon = document.getElementById(`voice-btn-icon-${slideNum}`);
            const label = document.getElementById(`voice-duration-label-${slideNum}`);
            const glowRing = document.getElementById(`voice-glow-ring-${slideNum}`);
            const btn = document.getElementById(`voice-wave-continue-${slideNum}`);
            const storyAudio = document.getElementById('story-audio');

            // If the synthesized melody is currently playing, treat this click as a PAUSE action!
            if (melodyInterval) {
                if (audio && audio.fallbackTimeout) {
                    clearTimeout(audio.fallbackTimeout);
                    audio.fallbackTimeout = null;
                }
                voiceWaveTargets[slideNum] = 0;
                if (playIcon) playIcon.textContent = '▶️';
                if (label) label.textContent = 'Play voice message';
                if (glowRing) glowRing.classList.remove('love-voice-glow-active');

                stopSynthesizedMelody(slideNum);

                if (musicPausedByLoveVoice && storyAudio) {
                    unduckBackgroundMusic();
                    musicPausedByLoveVoice = false;
                }
                return;
            }

            if (!audio) {
                if (voiceWaveTargets[slideNum] === 0) {
                    voiceWaveTargets[slideNum] = 1;
                    if (playIcon) playIcon.textContent = '⏸️';
                    if (label) label.textContent = 'Playing sweet melody...';
                    if (glowRing) glowRing.classList.add('love-voice-glow-active');

                    if (storyAudio && musicPlaying) {
                        duckBackgroundMusic();
                        musicPausedByLoveVoice = true;
                    }

                    playSynthesizedMelody(slideNum);

                    if (btn) {
                        btn.classList.remove('hidden');
                        setTimeout(() => {
                            btn.classList.remove('scale-0', 'opacity-0');
                            btn.classList.add('scale-100', 'opacity-100');
                        }, 50);
                    }
                } else {
                    voiceWaveTargets[slideNum] = 0;
                    if (playIcon) playIcon.textContent = '▶️';
                    if (label) label.textContent = 'Play sweet melody';
                    if (glowRing) glowRing.classList.remove('love-voice-glow-active');

                    stopSynthesizedMelody(slideNum);

                    if (musicPausedByLoveVoice && storyAudio) {
                        unduckBackgroundMusic();
                        musicPausedByLoveVoice = false;
                    }
                }
                return;
            }

            if (audio.paused) {
                document.querySelectorAll('audio, video').forEach(el => {
                    if (el.id !== `voice-audio-element-${slideNum}` && el.id !== 'story-audio') {
                        el.pause();
                    }
                });

                if (storyAudio && musicPlaying) {
                    duckBackgroundMusic();
                    musicPausedByLoveVoice = true;
                }

                if (audio.readyState === 0) {
                    audio.load();
                }

                audio.play().then(() => {
                    voiceWaveTargets[slideNum] = 1;
                    if (playIcon) playIcon.textContent = '⏸️';
                    if (label) label.textContent = 'Playing voice message...';
                    if (glowRing) glowRing.classList.add('love-voice-glow-active');

                    if (btn) {
                        btn.classList.remove('hidden');
                        setTimeout(() => {
                            btn.classList.remove('scale-0', 'opacity-0');
                            btn.classList.add('scale-100', 'opacity-100');
                        }, 50);
                    }

                    const checkEnded = setInterval(() => {
                        if (audio.ended || audio.paused) {
                            clearInterval(checkEnded);
                            voiceWaveTargets[slideNum] = 0;
                            if (playIcon) playIcon.textContent = '▶️';
                            if (label) label.textContent = 'Play voice message';
                            if (glowRing) glowRing.classList.remove('love-voice-glow-active');

                            if (musicPausedByLoveVoice && storyAudio) {
                                unduckBackgroundMusic();
                                musicPausedByLoveVoice = false;
                            }
                        }
                    }, 300);
                }).catch(e => {
                    // Broken .aac? repair via aac-play.js and retry the real audio first.
                    const asrc = audio.currentSrc || audio.src || '';
                    if (window.aacRepair && /\.(aac|acc)(\?.*)?$/i.test(asrc) && !audio.dataset.aacRetried) {
                        audio.dataset.aacRetried = '1';
                        if (label) label.textContent = 'Loading voice…';
                        window.aacRepair(audio).then(ok => { if (ok) audio.play().catch(() => {}); else if (label) label.textContent = 'Play voice message'; });
                        return;
                    }
                    console.log("Audio play failed, falling back to synthesized melody", e);

                    // Fallback to playing synthesized piano arpeggios
                    voiceWaveTargets[slideNum] = 1;
                    if (playIcon) playIcon.textContent = '⏸️';
                    if (label) label.textContent = 'Playing sweet melody (fallback)...';
                    if (glowRing) glowRing.classList.add('love-voice-glow-active');

                    playSynthesizedMelody(slideNum);

                    if (btn) {
                        btn.classList.remove('hidden');
                        setTimeout(() => {
                            btn.classList.remove('scale-0', 'opacity-0');
                            btn.classList.add('scale-100', 'opacity-100');
                        }, 50);
                    }

                    // Auto-end synthesized melody after 5 seconds if not paused manually
                    audio.fallbackTimeout = setTimeout(() => {
                        if (melodyInterval) {
                            voiceWaveTargets[slideNum] = 0;
                            if (playIcon) playIcon.textContent = '▶️';
                            if (label) label.textContent = 'Play voice message';
                            if (glowRing) glowRing.classList.remove('love-voice-glow-active');

                            stopSynthesizedMelody(slideNum);

                            if (musicPausedByLoveVoice && storyAudio) {
                                unduckBackgroundMusic();
                                musicPausedByLoveVoice = false;
                            }
                        }
                    }, 5000);
                });
            } else {
                audio.pause();
                voiceWaveTargets[slideNum] = 0;
                if (playIcon) playIcon.textContent = '▶️';
                if (label) label.textContent = 'Play voice message';
                if (glowRing) glowRing.classList.remove('love-voice-glow-active');

                if (musicPausedByLoveVoice && storyAudio) {
                    unduckBackgroundMusic();
                    musicPausedByLoveVoice = false;
                }
            }
        }

        function playSynthesizedMelody(slideNum) {
            if (melodyInterval) clearInterval(melodyInterval);

            const notes = [261.63, 329.63, 392.00, 493.88, 349.23, 440.00, 523.25, 659.25];
            let noteIdx = 0;

            melodyCtx = new (window.AudioContext || window.webkitAudioContext)();

            melodyInterval = setInterval(() => {
                if (!melodyCtx) return;
                try {
                    const osc = melodyCtx.createOscillator();
                    const gain = melodyCtx.createGain();
                    osc.type = 'sine';

                    const freq = notes[noteIdx % notes.length];
                    osc.frequency.setValueAtTime(freq, melodyCtx.currentTime);

                    osc.connect(gain);
                    gain.connect(melodyCtx.destination);

                    gain.gain.setValueAtTime(0.08, melodyCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, melodyCtx.currentTime + 1.2);

                    osc.start();
                    osc.stop(melodyCtx.currentTime + 1.2);

                    noteIdx++;
                } catch(e) {
                    console.log(e);
                }
            }, 600);
        }

        function stopSynthesizedMelody(slideNum) {
            if (melodyInterval) {
                clearInterval(melodyInterval);
                melodyInterval = null;
            }
            if (melodyCtx) {
                try {
                    melodyCtx.close();
                } catch(e) {}
                melodyCtx = null;
            }
        }

        // Slide 8: Custom Video Player Controls
        function toggleLoveVideo(slideNum) {
            const video = document.getElementById(`love-video-element-${slideNum}`);
            const playBtn = document.getElementById(`love-video-play-btn-${slideNum}`);
            const storyAudio = document.getElementById('story-audio');

            if (!video) return;

            if (video.paused) {
                document.querySelectorAll('audio, video').forEach(el => {
                    if (el.id !== `love-video-element-${slideNum}` && el.id !== 'story-audio') {
                        el.pause();
                    }
                });

                if (storyAudio && !storyAudio.paused) {
                    storyAudio.pause();
                    musicPausedByLoveVideo = true;
                }

                video.play().then(() => {
                    if (playBtn) playBtn.classList.add('hidden');

                    const checkEnded = setInterval(() => {
                        if (video.ended || video.paused) {
                            clearInterval(checkEnded);
                            if (playBtn) playBtn.classList.remove('hidden');

                            if (musicPausedByLoveVideo && storyAudio) {
                                storyAudio.play().catch(() => {});
                                musicPausedByLoveVideo = false;
                            }
                        }
                    }, 300);
                }).catch(e => {
                    console.log(e);
                });
            } else {
                video.pause();
                if (playBtn) playBtn.classList.remove('hidden');
                if (musicPausedByLoveVideo && storyAudio) {
                    storyAudio.play().catch(() => {});
                    musicPausedByLoveVideo = false;
                }
            }
        }

        // Slide 9: 3D Paper Letter Folding
        function runLoveLetterFolding(slideNum) {
            dimBackgroundMusic();
            const container = document.getElementById(`folding-container-${slideNum}`);
            if (!container) return;
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            currentFoldStep[slideNum] = 0;

            const topPanel = document.getElementById(`folding-panel-top-${slideNum}`);
            const bottomPanel = document.getElementById(`folding-panel-bottom-${slideNum}`);
            if (topPanel) topPanel.classList.remove('folded');
            if (bottomPanel) bottomPanel.classList.remove('folded');

            const containerWrap = document.getElementById(`folding-letter-container-${slideNum}`);
            const oldStamp = containerWrap.querySelector('.wax-seal-3d');
            if (oldStamp) oldStamp.remove();

            const hint = document.getElementById(`folding-hint-${slideNum}`);
            if (hint) {
                hint.textContent = "Tap the letter to fold it 💋";
                hint.classList.remove('hidden');
            }

            const btn = document.getElementById(`folding-continue-${slideNum}`);
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');
        }

        function foldLoveLetterStep(slideNum) {
            const step = currentFoldStep[slideNum];
            const topPanel = document.getElementById(`folding-panel-top-${slideNum}`);
            const bottomPanel = document.getElementById(`folding-panel-bottom-${slideNum}`);
            const containerWrap = document.getElementById(`folding-letter-container-${slideNum}`);
            const hint = document.getElementById(`folding-hint-${slideNum}`);
            const btn = document.getElementById(`folding-continue-${slideNum}`);

            if (step === 0) {
                playSystemSound('paper_rustle');
                if (bottomPanel) bottomPanel.classList.add('folded');
                if (hint) hint.textContent = "Tap again to fold the top ✉️";
                currentFoldStep[slideNum] = 1;
            } else if (step === 1) {
                playSystemSound('paper_rustle');
                if (topPanel) topPanel.classList.add('folded');
                if (hint) hint.textContent = "Tap once more to seal it ⚜️";
                currentFoldStep[slideNum] = 2;
            } else if (step === 2) {
                playSystemSound('wax_crack');

                const stamp = document.createElement('div');
                stamp.className = 'wax-seal-3d';
                stamp.style.top = '155px';
                stamp.style.left = '120px';
                stamp.style.margin = '0';
                stamp.style.transform = 'scale(2) rotate(-45deg)';
                stamp.style.opacity = '0';
                stamp.style.transition = 'all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                stamp.innerHTML = '⚜️';
                containerWrap.appendChild(stamp);

                setTimeout(() => {
                    stamp.style.transform = 'scale(1) rotate(0deg)';
                    stamp.style.opacity = '1';

                    setTimeout(() => {
                        stamp.style.boxShadow = '0 0 25px rgba(239, 68, 68, 0.8)';
                    }, 500);
                }, 50);

                if (hint) hint.textContent = "Sealed with love! ❤️";

                if (btn) {
                    btn.classList.remove('hidden');
                    setTimeout(() => {
                        btn.classList.remove('scale-0', 'opacity-0');
                        btn.classList.add('scale-100', 'opacity-100');
                    }, 300);
                }

                currentFoldStep[slideNum] = 3;
            }
        }

        // Slide 10: Finale Reactions
        function runLoveLetterReactionsFinale(slideNum) {
            const container = document.getElementById(`reactions-finale-container-${slideNum}`);
            if (!container) return;
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            restoreBackgroundMusic();

            const quoteEl = document.getElementById(`reactions-quote-${slideNum}`);
            if (quoteEl) {
                quoteEl.textContent = '';
                const text = "Every love story is beautiful, but ours is my absolute favorite. Forever and always. ❤️";
                typeWriterEffect(quoteEl, text, 50);
            }

            if (finaleHeartsInterval) clearInterval(finaleHeartsInterval);
            container.querySelectorAll('.floating-love-heart').forEach(h => h.remove());

            finaleHeartsInterval = setInterval(() => {
                const heart = document.createElement('div');
                heart.className = 'floating-love-heart absolute text-pink-500/20 pointer-events-none transition-all duration-[6000ms]';
                heart.style.fontSize = `${10 + Math.random() * 20}px`;
                heart.style.left = `${Math.random() * 100}%`;
                heart.style.bottom = '-40px';
                heart.style.transform = 'translateY(0) rotate(0deg)';
                heart.innerHTML = Math.random() > 0.5 ? '❤️' : '💖';
                container.appendChild(heart);

                setTimeout(() => {
                    heart.style.transform = `translateY(-${window.innerHeight + 100}px) rotate(${(Math.random() - 0.5) * 90}deg)`;
                    heart.style.opacity = '0';
                    setTimeout(() => heart.remove(), 6000);
                }, 50);
            }, 1200);
        }

        // ═══════════════════════════════════════════════════
        // MANA LO YAAR CATEGORY INTERACTIVE STATE MACHINES
        // ═══════════════════════════════════════════════════

        function cleanManaLoIntervals() {
            manaLoIntervals.forEach(i => clearInterval(i));
            manaLoIntervals = [];
            if (manaLoSirenInterval) {
                clearInterval(manaLoSirenInterval);
                manaLoSirenInterval = null;
            }
            if (manaLoVoiceInterval) {
                clearInterval(manaLoVoiceInterval);
                manaLoVoiceInterval = null;
            }
            Object.keys(voiceEmojiIntervals).forEach(k => {
                clearInterval(voiceEmojiIntervals[k]);
            });
            voiceEmojiIntervals = {};
            document.querySelectorAll('.floating-playful-emoji').forEach(e => e.remove());
        }

        // Slide 1: Angry Meter
        function runManaLoAngryMeter(slideNum) {
            cleanManaLoIntervals();
            const emoji = document.getElementById(`angry-emoji-${slideNum}`);
            const text = document.getElementById(`angry-text-${slideNum}`);
            const fill = document.getElementById(`angry-bar-fill-${slideNum}`);
            const btn = document.getElementById(`angry-meter-btn-${slideNum}`);

            if (!emoji || !text || !fill || !btn) return;

            // Reset state
            emoji.textContent = '😠';
            emoji.className = 'text-7xl mb-6 transition-all duration-500 select-none';
            emoji.style.transform = 'none';
            text.textContent = 'Checking status...';
            text.style.color = '';
            fill.style.width = '0%';
            fill.textContent = '0%';
            fill.className = 'bg-gradient-to-r from-pink-400 to-rose-600 h-full rounded-full w-0 transition-all duration-300 flex items-center justify-end pr-2 text-[10px] font-bold text-white';
            btn.classList.add('hidden', 'scale-0', 'opacity-0');

            // Play charge up sound
            playSystemSound('charge_up');

            let pct = 0;
            const interval = setInterval(() => {
                pct += 5;
                if (pct > 100) pct = 100;
                fill.style.width = `${pct}%`;
                fill.textContent = `${pct}%`;

                if (pct === 100) {
                    clearInterval(interval);
                    
                    // Burst to 9999%
                    setTimeout(() => {
                        playSystemSound('siren_alert');
                        emoji.textContent = '😡';
                        emoji.classList.add('angry-shake-active');
                        emoji.style.transform = 'scale(1.4)';
                        text.textContent = "I think you're VERY angry 😭";
                        text.style.color = '#e11d48';
                        
                        fill.style.width = '160%';
                        fill.textContent = '9999%';
                        fill.className = 'bg-rose-600 h-full rounded-full w-0 transition-all duration-500 flex items-center justify-end pr-2 text-xs font-extrabold text-white animate-pulse';

                        // Reveal continue button
                        btn.classList.remove('hidden');
                        setTimeout(() => {
                            btn.classList.remove('scale-0', 'opacity-0');
                            btn.classList.add('scale-100', 'opacity-100');
                        }, 500);
                    }, 500);
                }
            }, 60);

            manaLoIntervals.push(interval);
        }

        // Slide 2: Emergency Alert
        function runManaLoEmergency(slideNum) {
            cleanManaLoIntervals();
            dimBackgroundMusic();

            const container = document.getElementById(`emergency-container-${slideNum}`);
            const progress = document.getElementById(`emergency-progress-${slideNum}`);
            const searching = document.getElementById(`emergency-searching-${slideNum}`);
            const solution = document.getElementById(`emergency-solution-${slideNum}`);
            const btn = document.getElementById(`emergency-btn-${slideNum}`);

            if (!container) return;

            // Show container
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100', 'mana-lo-siren-bg');

            // Reset components
            if (progress) progress.style.width = '0%';
            if (searching) searching.classList.remove('hidden');
            if (solution) solution.classList.add('hidden', 'scale-0', 'opacity-0');
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');

            // Alarm sound sweep loop
            playSystemSound('siren_alert');
            manaLoSirenInterval = setInterval(() => {
                playSystemSound('siren_alert');
            }, 2500);

            // Animate search progress
            let pct = 0;
            const searchInterval = setInterval(() => {
                pct += 4;
                if (pct > 100) pct = 100;
                if (progress) progress.style.width = `${pct}%`;

                if (pct === 100) {
                    clearInterval(searchInterval);

                    setTimeout(() => {
                        // Stop alarm
                        if (manaLoSirenInterval) {
                            clearInterval(manaLoSirenInterval);
                            manaLoSirenInterval = null;
                        }

                        // Switch panel search -> solution
                        if (searching) searching.classList.add('hidden');
                        if (solution) {
                            solution.classList.remove('hidden');
                            setTimeout(() => {
                                solution.classList.remove('scale-0', 'opacity-0');
                                solution.classList.add('scale-100', 'opacity-100');
                                playSystemSound('success');
                            }, 50);
                        }

                        // Show See How button
                        if (btn) {
                            btn.classList.remove('hidden');
                            setTimeout(() => {
                                btn.classList.remove('scale-0', 'opacity-0');
                                btn.classList.add('scale-100', 'opacity-100');
                            }, 500);
                        }
                    }, 600);
                }
            }, 100);

            manaLoIntervals.push(searchInterval);
        }

        // Slide 3: Memory Rescue
        function runManaLoRescue(slideNum) {
            cleanManaLoIntervals();
            const container = document.getElementById(`rescue-container-${slideNum}`);
            const bar = document.getElementById(`rescue-progress-bar-${slideNum}`);
            const label = document.getElementById(`rescue-status-label-${slideNum}`);
            const btn = document.getElementById(`rescue-btn-${slideNum}`);

            if (!container) return;

            // Show container
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            // Reset
            if (bar) bar.style.width = '0%';
            if (label) label.textContent = 'Data Restored: 0%';
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');

            manaLoRescueClicked[slideNum] = [false, false, false, false];

            for (let i = 0; i < 4; i++) {
                const wrap = document.getElementById(`rescue-photo-wrap-${slideNum}-${i}`);
                if (wrap) wrap.style.transform = 'scaleY(0)';
            }
        }

        function openRescueFile(slideNum, idx) {
            if (!manaLoRescueClicked[slideNum]) {
                manaLoRescueClicked[slideNum] = [false, false, false, false];
            }
            if (manaLoRescueClicked[slideNum][idx]) return;

            manaLoRescueClicked[slideNum][idx] = true;
            playSystemSound('paper_rustle');

            // Open photo folder wrapper
            const wrap = document.getElementById(`rescue-photo-wrap-${slideNum}-${idx}`);
            if (wrap) wrap.style.transform = 'scaleY(1)';

            // Count clicked
            const clickedCount = manaLoRescueClicked[slideNum].filter(Boolean).length;
            const pct = clickedCount * 25;

            const bar = document.getElementById(`rescue-progress-bar-${slideNum}`);
            const label = document.getElementById(`rescue-status-label-${slideNum}`);
            if (bar) bar.style.width = `${pct}%`;
            if (label) label.textContent = `Data Restored: ${pct}%`;

            if (clickedCount === 4) {
                setTimeout(() => {
                    playSystemSound('success');
                    const btn = document.getElementById(`rescue-btn-${slideNum}`);
                    if (btn) {
                        btn.classList.remove('hidden');
                        setTimeout(() => {
                            btn.classList.remove('scale-0', 'opacity-0');
                            btn.classList.add('scale-100', 'opacity-100');
                        }, 100);
                    }
                }, 500);
            }
        }

        // Slide 4: Things I Miss Card Flipper
        function runManaLoMissThings(slideNum) {
            cleanManaLoIntervals();
            const container = document.getElementById(`miss-things-container-${slideNum}`);
            const btn = document.getElementById(`miss-things-btn-${slideNum}`);
            const hint = document.getElementById(`miss-things-hint-${slideNum}`);

            if (!container) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            // Reset cards
            for (let i = 0; i < 4; i++) {
                const card = document.getElementById(`miss-card-${slideNum}-${i}`);
                if (card) card.classList.remove('flipped');
            }

            if (hint) hint.textContent = 'Tap all cards to flip them 💬';
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');

            manaLoMissFlipped[slideNum] = [false, false, false, false];
        }

        function flipMissCard(slideNum, idx) {
            const card = document.getElementById(`miss-card-${slideNum}-${idx}`);
            if (!card) return;

            card.classList.toggle('flipped');
            playSystemSound('page_flip');

            if (!manaLoMissFlipped[slideNum]) {
                manaLoMissFlipped[slideNum] = [false, false, false, false];
            }

            if (card.classList.contains('flipped')) {
                manaLoMissFlipped[slideNum][idx] = true;
            }

            const flippedCount = manaLoMissFlipped[slideNum].filter(Boolean).length;
            const hint = document.getElementById(`miss-things-hint-${slideNum}`);
            const btn = document.getElementById(`miss-things-btn-${slideNum}`);

            if (flippedCount === 4) {
                if (hint) hint.textContent = 'All memories flipped! 💖';
                if (btn) {
                    btn.classList.remove('hidden');
                    setTimeout(() => {
                        btn.classList.remove('scale-0', 'opacity-0');
                        btn.classList.add('scale-100', 'opacity-100');
                    }, 300);
                }
            }
        }

        // Slide 5: Bribe Offerings
        function runManaLoBribe(slideNum) {
            cleanManaLoIntervals();
            const container = document.getElementById(`bribe-container-${slideNum}`);
            const btn = document.getElementById(`bribe-btn-${slideNum}`);
            const desc = document.getElementById(`bribe-card-desc-${slideNum}`);

            if (!container) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            // Reset boxes
            for (let i = 0; i < 5; i++) {
                const reveal = document.getElementById(`bribe-reveal-${slideNum}-${i}`);
                if (reveal) {
                    reveal.classList.add('scale-0', 'opacity-0', 'hidden');
                    reveal.classList.remove('scale-100', 'opacity-100');
                }
                const box = document.getElementById(`bribe-box-${slideNum}-${i}`);
                if (box) box.classList.remove('gift-wiggle-active');
            }

            if (desc) desc.innerHTML = '<p class="text-xs italic text-pink-200/90 font-serif">Tap on a gift box to open the bribe! 🎁</p>';
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');

            manaLoBribesOpened[slideNum] = [false, false, false, false, false];
        }

        function openBribeBox(slideNum, idx) {
            const bribes = [
                {name: 'Chocolate 🍫', val: 'chocolate', desc: 'A sweet box of your absolute favorite chocolates.'},
                {name: 'Pizza Night 🍕', val: 'pizza', desc: 'Hot cheesy pizza with all the toppings you love.'},
                {name: 'Warm Coffee ☕', val: 'coffee', desc: 'A warm, perfect coffee date with endless stories.'},
                {name: 'Bubble Tea 🧋', val: 'boba', desc: 'Sweet, refreshing bubble tea with extra boba.'},
                {name: 'Game Night 🎮', val: 'game', desc: 'Playful gaming session where I let you win!'}
            ];

            const reveal = document.getElementById(`bribe-reveal-${slideNum}-${idx}`);
            const box = document.getElementById(`bribe-box-${slideNum}-${idx}`);
            const desc = document.getElementById(`bribe-card-desc-${slideNum}`);
            const btn = document.getElementById(`bribe-btn-${slideNum}`);

            if (!manaLoBribesOpened[slideNum]) {
                manaLoBribesOpened[slideNum] = [false, false, false, false, false];
            }

            if (manaLoBribesOpened[slideNum][idx]) {
                if (desc) desc.innerHTML = `<p class="text-xs font-semibold text-pink-200 font-serif">${bribes[idx].desc}</p>`;
                playSystemSound('pop');
                return;
            }

            manaLoBribesOpened[slideNum][idx] = true;
            playSystemSound('gift_unwrap');

            if (reveal) {
                reveal.classList.remove('hidden', 'scale-0', 'opacity-0');
                reveal.classList.add('scale-100', 'opacity-100');
            }
            if (box) {
                box.classList.add('gift-wiggle-active');
            }

            if (desc) {
                desc.innerHTML = `<p class="text-xs font-semibold text-pink-200 font-serif">${bribes[idx].desc}</p>`;
            }

            if (btn) {
                btn.classList.remove('hidden');
                setTimeout(() => {
                    btn.classList.remove('scale-0', 'opacity-0');
                    btn.classList.add('scale-100', 'opacity-100');
                }, 100);
            }
        }

        // Slide 6: Voice Message Extension
        function toggleManaLoVoice(slideNum) {
            toggleLoveVoice(slideNum);
            
            setTimeout(() => {
                if (voiceWaveTargets[slideNum] === 1) {
                    if (voiceEmojiIntervals[slideNum]) clearInterval(voiceEmojiIntervals[slideNum]);
                    
                    const wrapper = document.getElementById(`voice-wrapper-${slideNum}`);
                    if (!wrapper) return;
                    
                    voiceEmojiIntervals[slideNum] = setInterval(() => {
                        const emojiList = ['🍫', '🍬', '🍩', '🍪', '☕', '🧋', '💖', '✨', '🌸', '🍭'];
                        const emoji = document.createElement('div');
                        emoji.className = 'floating-playful-emoji select-none text-2xl pointer-events-none transition-all duration-[5000ms]';
                        emoji.innerHTML = emojiList[Math.floor(Math.random() * emojiList.length)];
                        emoji.style.left = `${Math.random() * 80 + 10}%`;
                        emoji.style.bottom = '-40px';
                        emoji.style.transform = 'translateY(0) scale(1) rotate(0deg)';
                        emoji.style.setProperty('--rot-deg', `${(Math.random() - 0.5) * 360}deg`);
                        wrapper.appendChild(emoji);
                        
                        setTimeout(() => {
                            emoji.style.transform = `translateY(-350px) scale(1.3) rotate(${(Math.random() - 0.5) * 180}deg)`;
                            emoji.style.opacity = '0';
                            setTimeout(() => emoji.remove(), 5000);
                        }, 50);
                    }, 500);
                } else {
                    if (voiceEmojiIntervals[slideNum]) {
                        clearInterval(voiceEmojiIntervals[slideNum]);
                        delete voiceEmojiIntervals[slideNum];
                    }
                }
            }, 100);
        }

        // Slide 7: Playful Quiz
        function runManaLoQuiz(slideNum) {
            cleanManaLoIntervals();
            const container = document.getElementById(`quiz-container-${slideNum}`);
            const btn = document.getElementById(`quiz-btn-${slideNum}`);

            if (!container) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            // Reset panels
            document.getElementById(`quiz-q-${slideNum}-0`).classList.remove('hidden');
            document.getElementById(`quiz-q-${slideNum}-1`).classList.add('hidden');
            document.getElementById(`quiz-q-${slideNum}-2`).classList.add('hidden');
            
            const res = document.getElementById(`quiz-results-${slideNum}`);
            if (res) {
                res.classList.add('hidden', 'scale-0');
                res.classList.remove('scale-100');
            }

            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');
        }

        function answerQuiz(slideNum, qIdx, choice) {
            playSystemSound('pop');
            
            const currentPanel = document.getElementById(`quiz-q-${slideNum}-${qIdx}`);
            if (currentPanel) currentPanel.classList.add('hidden');

            if (qIdx < 2) {
                const nextPanel = document.getElementById(`quiz-q-${slideNum}-${qIdx + 1}`);
                if (nextPanel) nextPanel.classList.remove('hidden');
            } else {
                const results = document.getElementById(`quiz-results-${slideNum}`);
                const btn = document.getElementById(`quiz-btn-${slideNum}`);

                if (results) {
                    results.classList.remove('hidden');
                    setTimeout(() => {
                        results.classList.remove('scale-0');
                        results.classList.add('scale-100');
                        playSystemSound('success');
                    }, 50);
                }

                if (btn) {
                    btn.classList.remove('hidden');
                    setTimeout(() => {
                        btn.classList.remove('scale-0', 'opacity-0');
                        btn.classList.add('scale-100', 'opacity-100');
                    }, 300);
                }
            }
        }

        // Slide 8: Drag-to-Unlock Heart Lock
        function runManaLoUnlock(slideNum) {
            cleanManaLoIntervals();
            const container = document.getElementById(`unlock-container-${slideNum}`);
            const lock = document.getElementById(`heart-lock-${slideNum}`);
            const key = document.getElementById(`heart-key-${slideNum}`);
            const wrap = document.getElementById(`lock-wrap-${slideNum}`);
            const msgCard = document.getElementById(`unlocked-msg-card-${slideNum}`);
            const btn = document.getElementById(`unlock-btn-${slideNum}`);
            const hint = document.getElementById(`unlock-hint-${slideNum}`);

            if (!container || !lock || !key || !wrap || !msgCard) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            // Reset positions
            lock.classList.remove('unlocked');
            lock.style.opacity = '1';
            key.style.opacity = '1';
            key.style.left = '110px';
            key.style.top = '210px';
            key.style.transform = 'none';
            key.style.transition = 'transform 0.1s';

            msgCard.classList.add('hidden', 'scale-0', 'opacity-0');
            msgCard.classList.remove('scale-100', 'opacity-100');

            if (hint) hint.textContent = 'Drag and drop the key into the keyhole 🗝️';
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');

            let isDragging = false;
            let keyOffsetX = 0;
            let keyOffsetY = 0;
            let unlocked = false;

            key.onpointerdown = function(e) {
                if (unlocked) return;
                isDragging = true;
                key.setPointerCapture(e.pointerId);
                const rect = key.getBoundingClientRect();
                keyOffsetX = e.clientX - rect.left;
                keyOffsetY = e.clientY - rect.top;
                e.preventDefault();
            };

            key.onpointermove = function(e) {
                if (!isDragging || unlocked) return;
                const containerRect = wrap.getBoundingClientRect();
                
                let left = e.clientX - containerRect.left - keyOffsetX;
                let top = e.clientY - containerRect.top - keyOffsetY;
                
                left = Math.max(0, Math.min(containerRect.width - 60, left));
                top = Math.max(0, Math.min(containerRect.height - 60, top));
                
                key.style.left = `${left}px`;
                key.style.top = `${top}px`;
                
                // Keyhole is at center (140, 133)
                const keyCenterX = left + 30;
                const keyCenterY = top + 30;
                const dist = Math.hypot(keyCenterX - 140, keyCenterY - 133);
                
                if (dist < 28) {
                    key.style.left = '110px';
                    key.style.top = '103px';
                    
                    isDragging = false;
                    key.releasePointerCapture(e.pointerId);
                    unlocked = true;
                    triggerUnlock();
                }
            };

            key.onpointerup = function(e) {
                if (!isDragging) return;
                isDragging = false;
                key.releasePointerCapture(e.pointerId);
                
                if (!unlocked) {
                    key.style.transition = 'left 0.3s, top 0.3s';
                    key.style.left = '110px';
                    key.style.top = '210px';
                    setTimeout(() => {
                        key.style.transition = 'transform 0.1s';
                    }, 300);
                }
            };

            function triggerUnlock() {
                playSystemSound('lock_click');
                lock.classList.add('unlocked');
                key.style.transform = 'scale(0.8) rotate(45deg)';
                
                setTimeout(() => {
                    key.style.transition = 'opacity 0.5s';
                    key.style.opacity = '0';
                    lock.style.transition = 'opacity 0.5s';
                    lock.style.opacity = '0.1';
                    
                    msgCard.classList.remove('hidden');
                    setTimeout(() => {
                        msgCard.classList.remove('scale-0', 'opacity-0');
                        msgCard.classList.add('scale-100', 'opacity-100');
                    }, 100);
                    
                    if (hint) hint.textContent = 'Secret unlocked! ❤️';
                    if (btn) {
                        btn.classList.remove('hidden');
                        setTimeout(() => {
                            btn.classList.remove('scale-0', 'opacity-0');
                            btn.classList.add('scale-100', 'opacity-100');
                        }, 300);
                    }
                }, 600);
            }
        }

        // Slide 9: Handwriting Sincere Letter
        function runManaLoLetter(slideNum) {
            cleanManaLoIntervals();
            dimBackgroundMusic();
            const container = document.getElementById(`mana-lo-letter-container-${slideNum}`);
            if (!container) return;
            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            const paper = document.getElementById(`mana-lo-letter-paper-${slideNum}`);
            if (paper) {
                setTimeout(() => {
                    paper.classList.add('active');
                }, 50);
            }

            const textTarget = document.getElementById(`mana-lo-letter-lines-${slideNum}`);
            if (!textTarget) return;
            textTarget.innerHTML = '';

            const quill = document.getElementById(`mana-lo-letter-quill-${slideNum}`);
            if (quill) {
                quill.classList.remove('hidden');
                quill.style.opacity = '1';
            }

            const btn = document.getElementById(`mana-lo-letter-continue-${slideNum}`);
            if (btn) btn.classList.add('hidden', 'scale-0', 'opacity-0');

            const p = document.createElement('p');
            p.className = 'font-serif text-[#5c3e21] text-base md:text-lg leading-relaxed whitespace-pre-line';
            textTarget.appendChild(p);

            const scrollContainer = document.getElementById(`mana-lo-letter-text-${slideNum}`);

            let charIndex = 0;
            function typeChar() {
                if (currentSlide !== slideNum) return;
                
                if (charIndex < letterText.length) {
                    const char = letterText[charIndex];
                    p.textContent += char;
                    charIndex++;

                    if (quill && scrollContainer) {
                        const marker = document.createElement('span');
                        marker.style.position = 'relative';
                        marker.style.display = 'inline-block';
                        marker.innerHTML = '&nbsp;';
                        p.appendChild(marker);

                        const parentRect = scrollContainer.getBoundingClientRect();
                        const markerRect = marker.getBoundingClientRect();
                        const left = markerRect.left - parentRect.left;
                        const top = markerRect.top - parentRect.top + scrollContainer.scrollTop;

                        quill.style.left = `${left + 5}px`;
                        quill.style.top = `${top - 20}px`;

                        p.removeChild(marker);
                    }

                    if (scrollContainer) {
                        scrollContainer.scrollTop = scrollContainer.scrollHeight;
                    }

                    if (charIndex % 5 === 0) {
                        playSystemSound('paper_rustle');
                    }

                    setTimeout(typeChar, 25);
                } else {
                    if (quill) {
                        quill.style.transition = 'opacity 0.5s';
                        quill.style.opacity = '0';
                        setTimeout(() => quill.classList.add('hidden'), 500);
                    }
                    if (btn) {
                        btn.classList.remove('hidden');
                        setTimeout(() => {
                            btn.classList.remove('scale-0', 'opacity-0');
                            btn.classList.add('scale-100', 'opacity-100');
                        }, 50);
                    }
                }
            }
            setTimeout(typeChar, 500);
        }

        // Slide 10: Smile Acceptance & Playful Dodge Button
        function runManaLoSmile(slideNum) {
            cleanManaLoIntervals();
            const container = document.getElementById(`smile-container-${slideNum}`);
            const emoji = document.getElementById(`smile-emoji-${slideNum}`);
            const label = document.getElementById(`smile-label-${slideNum}`);
            const yesBtn = document.getElementById(`smile-yes-btn-${slideNum}`);
            const noBtn = document.getElementById(`smile-no-btn-${slideNum}`);
            const wrap = document.getElementById(`smile-button-wrap-${slideNum}`);

            if (!container || !emoji || !label || !noBtn || !wrap) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            // Reset
            emoji.textContent = '😔';
            emoji.className = 'text-8xl mb-6 transition-transform duration-700 select-none animate-bounce';
            emoji.style.transform = 'none';
            label.textContent = 'Are we friends again?';
            label.className = 'text-lg font-bold text-rose-300 font-serif mb-6 text-center';

            if (noBtn) {
                noBtn.style.display = 'block';
                noBtn.style.position = 'absolute';
                noBtn.style.left = '';
                noBtn.style.top = '';
                noBtn.style.bottom = '0';
                noBtn.style.transform = 'none';
            }
            if (yesBtn) yesBtn.style.display = 'block';

            function dodgeButton(e) {
                const wrapRect = wrap.getBoundingClientRect();
                const btnRect = noBtn.getBoundingClientRect();

                const maxX = wrapRect.width - btnRect.width;
                const maxY = wrapRect.height - btnRect.height;

                const randomX = Math.floor(Math.random() * maxX);
                const randomY = Math.floor(Math.random() * maxY);

                noBtn.style.left = `${randomX}px`;
                noBtn.style.top = `${randomY}px`;
                noBtn.style.bottom = 'auto';

                playSystemSound('pop');
            }

            noBtn.onpointerenter = dodgeButton;
            noBtn.ontouchstart = function(e) {
                e.preventDefault();
                dodgeButton(e);
            };
            noBtn.onclick = function(e) {
                e.preventDefault();
                dodgeButton(e);
            };
        }

        function acceptSmile(slideNum) {
            const emoji = document.getElementById(`smile-emoji-${slideNum}`);
            const label = document.getElementById(`smile-label-${slideNum}`);
            const yesBtn = document.getElementById(`smile-yes-btn-${slideNum}`);
            const noBtn = document.getElementById(`smile-no-btn-${slideNum}`);

            if (emoji) {
                emoji.textContent = '🥰';
                emoji.className = 'text-9xl mb-6 select-none transition-transform duration-500 scale-125';
            }
            if (label) {
                label.textContent = 'YAY! Love you! ❤️';
                label.className = 'text-2xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-pink-400 to-rose-400 font-heading mb-6 text-center animate-pulse';
            }

            if (noBtn) noBtn.style.display = 'none';
            if (yesBtn) yesBtn.style.display = 'none';

            playSystemSound('success');
            confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });

            setTimeout(() => {
                goSlide(slideNum + 1);
            }, 1800);
        }

        // Slide 11: Restored Finale with drifting photos
        function runManaLoFinale(slideNum) {
            cleanManaLoIntervals();
            const container = document.getElementById(`restored-finale-container-${slideNum}`);
            const quoteEl = document.getElementById(`restored-quote-${slideNum}`);
            const photosContainer = document.getElementById(`restored-photos-container-${slideNum}`);

            if (!container) return;

            container.classList.remove('pointer-events-none', 'opacity-0');
            container.classList.add('opacity-100');

            restoreBackgroundMusic();

            if (quoteEl) {
                quoteEl.textContent = '';
                const text = "Both of us are stubborn, but our bond is way too special to stay silent. Peace made forever! ❤️";
                typeWriterEffect(quoteEl, text, 50);
            }

            if (photosContainer) photosContainer.innerHTML = '';

            if (finaleHeartsInterval) clearInterval(finaleHeartsInterval);
            finaleHeartsInterval = setInterval(() => {
                if (currentSlide !== slideNum) {
                    clearInterval(finaleHeartsInterval);
                    return;
                }
                const heart = document.createElement('div');
                heart.className = 'floating-love-heart absolute text-pink-500/20 pointer-events-none transition-all duration-[6000ms]';
                heart.style.fontSize = `${10 + Math.random() * 20}px`;
                heart.style.left = `${Math.random() * 100}%`;
                heart.style.bottom = '-40px';
                heart.innerHTML = Math.random() > 0.5 ? '❤️' : '💖';
                container.appendChild(heart);

                setTimeout(() => {
                    heart.style.transform = `translateY(-${window.innerHeight + 100}px) rotate(${(Math.random() - 0.5) * 90}deg)`;
                    heart.style.opacity = '0';
                    setTimeout(() => heart.remove(), 6000);
                }, 50);
            }, 800);

            if (pageImages && pageImages.length > 0) {
                let photoIdx = 0;
                function spawnDriftingPhoto() {
                    if (currentSlide !== slideNum) return;
                    
                    const imgObj = pageImages[photoIdx % pageImages.length];
                    const src = baseUrl + (imgObj.medium_path || imgObj.image_path);
                    photoIdx++;

                    const polaroid = document.createElement('div');
                    polaroid.className = 'absolute bg-white p-2 pb-5 shadow-lg border border-slate-200/50 rounded pointer-events-none transition-all duration-[8000ms] ease-linear';
                    polaroid.style.width = '100px';
                    polaroid.style.left = `${Math.random() * 70 + 5}%`;
                    polaroid.style.bottom = '-140px';
                    polaroid.style.transform = `rotate(${(Math.random() - 0.5) * 30}deg)`;

                    const img = document.createElement('img');
                    img.src = src;
                    img.className = 'w-full h-[70px] object-cover rounded-sm';
                    polaroid.appendChild(img);

                    photosContainer.appendChild(polaroid);

                    setTimeout(() => {
                        polaroid.style.transform = `translateY(-${window.innerHeight + 250}px) rotate(${(Math.random() - 0.5) * 90}deg)`;
                        setTimeout(() => polaroid.remove(), 8000);
                    }, 100);
                }

                spawnDriftingPhoto();
                setTimeout(spawnDriftingPhoto, 2000);

                const photoInterval = setInterval(spawnDriftingPhoto, 4000);
                manaLoIntervals.push(photoInterval);
            } else {
                let idx = 0;
                function spawnDriftingEmoji() {
                    if (currentSlide !== slideNum) return;
                    const emojis = ['🎈', '🌸', '✨', '🧸', '🧸', '🍪'];
                    const emoji = document.createElement('div');
                    emoji.className = 'absolute text-4xl pointer-events-none transition-all duration-[6000ms] ease-linear select-none';
                    emoji.innerHTML = emojis[idx % emojis.length];
                    idx++;
                    emoji.style.left = `${Math.random() * 80 + 10}%`;
                    emoji.style.bottom = '-50px';
                    photosContainer.appendChild(emoji);

                    setTimeout(() => {
                        emoji.style.transform = `translateY(-${window.innerHeight + 100}px) rotate(${(Math.random() - 0.5) * 360}deg)`;
                        emoji.style.opacity = '0';
                        setTimeout(() => emoji.remove(), 6000);
                    }, 100);
                }

                spawnDriftingEmoji();
                const emojiInterval = setInterval(spawnDriftingEmoji, 3000);
                manaLoIntervals.push(emojiInterval);
            }
        }

        // REPLY INBOX VISITOR GATE & ANALYTICS CLIENT TRACKING
        const slidesVisited = new Set([1]);
        let reachedFinalSlide = false;
        let sessionTracked = false;
        const watchStartTime = Date.now();
        const isOwner = <?= $is_owner ? 'true' : 'false' ?>;

        function loadPageReplies() {
            if (!isOwner) return;
            const container = document.getElementById('replies-list-container');
            if (!container) return;
            
            fetch(baseUrl + 'api.php?action=get_replies&page_id=' + pageId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.replies.length > 0) {
                    container.innerHTML = data.replies.map(r => {
                        let content = '';
                        const typeIcons = {text: '📝', emoji: '😊', voice: '🎤', image: '🖼️', video: '📹'};
                        const icon = typeIcons[r.reply_type] || '💬';
                        
                        if (r.reply_type === 'text' || r.reply_type === 'emoji') {
                            content = '<p class="text-xs text-slate-350 whitespace-pre-wrap leading-relaxed">' + escapeHtml(r.message || '') + '</p>';
                        } else if (r.reply_type === 'voice' && r.voice_path) {
                            content = '<audio controls class="w-full h-8 rounded-lg mt-1" src="' + baseUrl + r.voice_path + '"></audio>';
                        } else if (r.reply_type === 'image' && r.image_path) {
                            content = '<a href="' + baseUrl + r.image_path + '" target="_blank"><img src="' + baseUrl + r.image_path + '" class="w-20 h-20 object-cover rounded-lg mt-1 border border-white/10 hover:opacity-90"></a>';
                        } else if (r.reply_type === 'video' && r.video_path) {
                            content = '<video controls class="w-full max-w-xs rounded-lg mt-1" src="' + baseUrl + r.video_path + '"></video>';
                        }
                        
                        return '<div class="flex items-start gap-3 bg-white/5 border border-white/5 rounded-xl p-3">' +
                            '<span class="text-sm flex-shrink-0">' + icon + '</span>' +
                            '<div class="flex-grow min-w-0">' +
                                '<div class="flex items-center justify-between mb-1">' +
                                    '<span class="text-xs font-semibold text-slate-200">' + escapeHtml(r.visitor_name || 'Anonymous') + '</span>' +
                                    '<span class="text-[9px] text-slate-500">' + new Date(r.created_at).toLocaleDateString() + '</span>' +
                                '</div>' +
                                content +
                            '</div>' +
                        '</div>';
                    }).join('');
                } else {
                    container.innerHTML = '<p class="text-xs text-slate-500 py-4 text-center">No replies yet.</p>';
                }
            })
            .catch(err => {
                container.innerHTML = '<p class="text-xs text-red-400 py-4 text-center">Failed to load replies.</p>';
            });
        }

        function trackSessionEnd() {
            if (sessionTracked) return;
            sessionTracked = true;
            
            const watchTime = Math.floor((Date.now() - watchStartTime) / 1000);
            const data = {
                page_id: pageId,
                watch_time: watchTime,
                completed: reachedFinalSlide ? 1 : 0,
                slides_viewed: slidesVisited.size
            };
            
            const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
            navigator.sendBeacon(baseUrl + 'api.php?action=track_session', blob);
        }
        
        window.addEventListener('pagehide', trackSessionEnd);
        window.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                trackSessionEnd();
            }
        });

        // ═══════════════════════════════════════════════════
        // PREMIUM SLIDES ANIMATION ENGINES
        // ═══════════════════════════════════════════════════
        let slideVoiceTimers = {};
        function toggleSlideVoice(slideNum) {
            const audio = document.getElementById(`slide-voice-element-${slideNum}`);
            const icon = document.getElementById(`slide-voice-icon-${slideNum}`);
            const progress = document.getElementById(`slide-voice-progress-${slideNum}`);
            if (!audio) {
                if (window.slideVoiceFallbackIntervals && window.slideVoiceFallbackIntervals[slideNum]) {
                    clearInterval(window.slideVoiceFallbackIntervals[slideNum]);
                    delete window.slideVoiceFallbackIntervals[slideNum];
                    stopSynthesizedMelody(slideNum);
                    if (icon) icon.textContent = '▶';
                    updateSlideVoiceUI(slideNum, { mockCurrentTime: 0, mockDuration: 5 });
                    unduckBackgroundMusic();
                    return;
                }

                if (!window.slideVoiceFallbackIntervals) window.slideVoiceFallbackIntervals = {};
                
                duckBackgroundMusic();
                if (icon) icon.textContent = '⏸';
                playSynthesizedMelody(slideNum);
                
                let mockCurrent = 0;
                const mockDur = 5;
                window.slideVoiceFallbackIntervals[slideNum] = setInterval(() => {
                    mockCurrent += 0.1;
                    updateSlideVoiceUI(slideNum, { mockCurrentTime: mockCurrent, mockDuration: mockDur });
                    
                    if (mockCurrent >= mockDur) {
                        clearInterval(window.slideVoiceFallbackIntervals[slideNum]);
                        delete window.slideVoiceFallbackIntervals[slideNum];
                        stopSynthesizedMelody(slideNum);
                        if (icon) icon.textContent = '▶';
                        updateSlideVoiceUI(slideNum, { mockCurrentTime: 0, mockDuration: mockDur });
                        unduckBackgroundMusic();
                    }
                }, 100);
                return;
            }
            
            if (audio.isFallback) {
                audio.isFallback = false;
                if (audio.fallbackInterval) {
                    clearInterval(audio.fallbackInterval);
                    audio.fallbackInterval = null;
                }
                stopSynthesizedMelody(slideNum);
                icon.textContent = '▶';
                if (progress) progress.style.width = '0%';
                updateSlideVoiceUI(slideNum, audio);
                unduckBackgroundMusic();
                return;
            }
            
            document.querySelectorAll('audio, video').forEach(el => {
                if (el !== audio && el.id !== 'story-audio') {
                    el.pause();
                    if (el.isFallback) {
                        el.isFallback = false;
                        if (el.fallbackInterval) {
                            clearInterval(el.fallbackInterval);
                            el.fallbackInterval = null;
                        }
                        const elNum = el.id.replace('slide-voice-element-', '').replace('custom-audio-element-', '').replace('voice-audio-element-', '');
                        const otherIcon = document.getElementById(`slide-voice-icon-${elNum}`);
                        if (otherIcon) otherIcon.textContent = '▶';
                        const otherProgress = document.getElementById(`slide-voice-progress-${elNum}`);
                        if (otherProgress) otherProgress.style.width = '0%';
                        updateSlideVoiceUI(elNum, el);
                    }
                }
            });
            document.querySelectorAll('[id^="slide-voice-play-"]').forEach(b => {
                const bNum = b.id.replace('slide-voice-play-', '');
                if (parseInt(bNum) !== slideNum) {
                    const otherIcon = document.getElementById(`slide-voice-icon-${bNum}`);
                    if (otherIcon) otherIcon.textContent = '▶';
                }
            });
            
            if (audio.paused) {
                // Duck background music
                duckBackgroundMusic();
                
                // Always load if not buffered enough, especially on iOS
                if (audio.readyState < 3) {
                    audio.load();
                }
                
                audio.play().then(() => {
                    icon.textContent = '⏸';
                    slideVoiceTimers[slideNum] = setInterval(() => {
                        const pct = (audio.currentTime / audio.duration) * 100;
                        if (progress) progress.style.width = `${pct}%`;
                        updateSlideVoiceUI(slideNum, audio);
                    }, 100);
                }).catch(err => {
                    // Broken .aac? repair via aac-play.js and retry the real audio first.
                    const vsrc = audio.currentSrc || audio.src || '';
                    if (window.aacRepair && /\.(aac|acc)(\?.*)?$/i.test(vsrc) && !audio.dataset.aacRetried) {
                        audio.dataset.aacRetried = '1';
                        icon.textContent = '⏳';
                        window.aacRepair(audio).then(ok => { icon.textContent = '▶'; if (ok) audio.play().then(() => { icon.textContent = '⏸'; }).catch(() => {}); });
                        return;
                    }
                    console.log("Voice note play error, playing synthesized melody fallback:", err);

                    audio.isFallback = true;
                    audio.mockCurrentTime = 0;
                    audio.mockDuration = 5; // 5-second fallback
                    
                    icon.textContent = '⏸';
                    playSynthesizedMelody(slideNum);
                    
                    audio.fallbackInterval = setInterval(() => {
                        audio.mockCurrentTime += 0.1;
                        if (audio.mockCurrentTime >= audio.mockDuration) {
                            audio.isFallback = false;
                            clearInterval(audio.fallbackInterval);
                            audio.fallbackInterval = null;
                            stopSynthesizedMelody(slideNum);
                            icon.textContent = '▶';
                            if (progress) progress.style.width = '0%';
                            updateSlideVoiceUI(slideNum, audio);
                            unduckBackgroundMusic();
                        } else {
                            const pct = (audio.mockCurrentTime / audio.mockDuration) * 100;
                            if (progress) progress.style.width = `${pct}%`;
                            updateSlideVoiceUI(slideNum, audio);
                        }
                    }, 100);
                });
            } else {
                audio.pause();
                icon.textContent = '▶';
                if (slideVoiceTimers[slideNum]) {
                    clearInterval(slideVoiceTimers[slideNum]);
                }
                unduckBackgroundMusic();
            }
            
            audio.onended = () => {
                icon.textContent = '▶';
                if (progress) progress.style.width = '0%';
                updateSlideVoiceUI(slideNum, audio);
                if (slideVoiceTimers[slideNum]) {
                    clearInterval(slideVoiceTimers[slideNum]);
                }
                unduckBackgroundMusic();
            };
        }

        function playSlideVideo(slideNum) {
            const modal = document.getElementById(`slide-video-modal-${slideNum}`);
            const player = document.getElementById(`slide-video-player-${slideNum}`);
            if (!modal || !player) return;
            
            const storyAudio = document.getElementById('story-audio');
            if (storyAudio && !storyAudio.paused) {
                storyAudio.pause();
                const widgetPlayBtn = document.getElementById('music-widget-play');
                if (widgetPlayBtn) widgetPlayBtn.textContent = '▶';
                musicPausedByVoice = true;
            }
            if (window.activeSlideBgMusic && !window.activeSlideBgMusic.paused) {
                window.activeSlideBgMusic.pause();
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            player.play().catch(e => console.log("Video play deferred:", e));
        }

        function closeSlideVideo(slideNum) {
            const modal = document.getElementById(`slide-video-modal-${slideNum}`);
            const player = document.getElementById(`slide-video-player-${slideNum}`);
            if (!modal || !player) return;
            
            player.pause();
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            
            if (musicPausedByVoice) {
                const storyAudio = document.getElementById('story-audio');
                if (storyAudio && musicPlaying && !window.activeSlideBgMusic) {
                    storyAudio.play().catch(e => console.log(e));
                    const widgetPlayBtn = document.getElementById('music-widget-play');
                    if (widgetPlayBtn) widgetPlayBtn.textContent = '⏸';
                }
                if (window.activeSlideBgMusic) {
                    window.activeSlideBgMusic.play().catch(e => console.log(e));
                }
                musicPausedByVoice = false;
            }
        }

        let originalVolume = 0.5;
        let isDucked = false;
        let isDuckedByVideo = false;

        function duckBackgroundMusic() {
            const storyAudio = document.getElementById('story-audio');
            if (!storyAudio) return;
            if (!isDucked) {
                originalVolume = storyAudio.volume;
                isDucked = true;
            }
            fadeVolume(storyAudio, 0.10 * originalVolume, 300);
            if (window.activeSlideBgMusic) {
                fadeVolume(window.activeSlideBgMusic, 0.10 * originalVolume, 300);
            }
        }

        function unduckBackgroundMusic() {
            const storyAudio = document.getElementById('story-audio');
            if (!storyAudio) return;
            if (isDucked) {
                isDucked = false;
                fadeVolume(storyAudio, originalVolume, 300);
                if (window.activeSlideBgMusic) {
                    fadeVolume(window.activeSlideBgMusic, originalVolume, 300);
                }
            }
        }

        function duckBackgroundMusicVideo() {
            const storyAudio = document.getElementById('story-audio');
            if (storyAudio && !isDuckedByVideo) {
                originalVolume = storyAudio.volume;
                isDuckedByVideo = true;
            }
            if (storyAudio) fadeVolume(storyAudio, 0.0, 300);
            if (window.activeSlideBgMusic) fadeVolume(window.activeSlideBgMusic, 0.0, 300);
        }

        function unduckBackgroundMusicVideo() {
            const storyAudio = document.getElementById('story-audio');
            if (isDuckedByVideo) {
                isDuckedByVideo = false;
                if (storyAudio) fadeVolume(storyAudio, originalVolume, 300);
                if (window.activeSlideBgMusic) fadeVolume(window.activeSlideBgMusic, originalVolume, 300);
            }
        }

        function fadeVolume(audioEl, targetVolume, durationMs) {
            if (audioEl.fadeTimer) clearInterval(audioEl.fadeTimer);
            const startVolume = audioEl.volume;
            const step = (targetVolume - startVolume) / (durationMs / 30);
            let currentVolume = startVolume;

            audioEl.fadeTimer = setInterval(() => {
                currentVolume += step;
                if ((step > 0 && currentVolume >= targetVolume) || (step < 0 && currentVolume <= targetVolume)) {
                    audioEl.volume = targetVolume;
                    clearInterval(audioEl.fadeTimer);
                    audioEl.fadeTimer = null;
                } else {
                    audioEl.volume = currentVolume;
                }
            }, 30);
        }

        let activeSlideMusic = null;
        window.activeSlideBgMusic = null;
        function handleSlideAudioLifecycle(num) {
            const toSlide = document.getElementById(`slide-${num}`);
            if (!toSlide) return;

            const storyAudio = document.getElementById('story-audio');
            if (!storyAudio) return;

            // Determine slide-specific music (if any) and global music URL
            const slideMusicAttr = toSlide.dataset.slideMusic;
            const slideMusicUrl = slideMusicAttr ? baseUrl + slideMusicAttr : '';
            const globalMusicUrl = currentTrackPath ? baseUrl + currentTrackPath : '';

            const targetUrl = slideMusicUrl || globalMusicUrl;

            if (!targetUrl) {
                // No music configured, pause if playing
                storyAudio.pause();
                const widgetPlayBtn = document.getElementById('music-widget-play');
                if (widgetPlayBtn) widgetPlayBtn.textContent = '▶';
                return;
            }

            // Normalise URLs to compare
            const currentSrc = storyAudio.src ? new URL(storyAudio.src, window.location.href).href : '';
            const targetSrc = new URL(targetUrl, window.location.href).href;

            if (currentSrc !== targetSrc) {
                // Music changed! Load new one
                storyAudio.src = targetSrc;
                storyAudio.load();
                if (musicPlaying) {
                    storyAudio.play().catch(e => console.log("Unified music play failed:", e));
                    const widgetPlayBtn = document.getElementById('music-widget-play');
                    if (widgetPlayBtn) widgetPlayBtn.textContent = '⏸';
                }
            } else {
                // Same music! If it should be playing and is paused, play it
                if (musicPlaying && storyAudio.paused && !musicPausedByVoice) {
                    storyAudio.play().catch(e => console.log("Unified music resume failed:", e));
                    const widgetPlayBtn = document.getElementById('music-widget-play');
                    if (widgetPlayBtn) widgetPlayBtn.textContent = '⏸';
                }
            }
        }

        // PHOTO MEMORY REVEAL
        window.premiumMemoryRevealTimers = window.premiumMemoryRevealTimers || {};
        function runPremiumMemoryReveal(num) {
            const overlay = document.getElementById(`reveal-blur-overlay-${num}`);
            if (!overlay) return;
            
            if (window.premiumMemoryRevealTimers[num]) {
                clearInterval(window.premiumMemoryRevealTimers[num]);
                window.premiumMemoryRevealTimers[num] = null;
            }
            
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            const photos = document.querySelectorAll(`#reveal-photos-wrapper-${num} [data-reveal-idx]`);
            
            photos.forEach(p => {
                p.style.opacity = '0';
                p.style.transform = 'scale(1)';
                p.style.transition = 'opacity 1s ease-in-out, transform 4.5s linear';
            });
            
            setTimeout(() => {
                overlay.classList.add('opacity-0', 'pointer-events-none');
                if (photos.length === 0) return;
                
                let currentIdx = 0;
                function showNext() {
                    const prevIdx = (currentIdx - 1 + photos.length) % photos.length;
                    if (photos.length > 1) {
                        photos[prevIdx].style.opacity = '0';
                        photos[prevIdx].style.transform = 'scale(1)';
                    }
                    
                    const activePhoto = photos[currentIdx];
                    activePhoto.style.opacity = '1';
                    activePhoto.style.transform = 'scale(1.08)';
                    
                    currentIdx = (currentIdx + 1) % photos.length;
                }
                
                showNext();
                if (photos.length > 1) {
                    window.premiumMemoryRevealTimers[num] = setInterval(showNext, 4500);
                }
            }, 1200);
        }

        // Attractor canvas particle system
        function runPremiumHeartFormation(num) {
            const canvas = document.getElementById(`heart-formation-canvas-${num}`);
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            const parent = canvas.parentElement;
            canvas.width = parent.clientWidth || 300;
            canvas.height = parent.clientHeight || 300;
            
            const category = '<?= h($page['category']) ?>';
            const innerCard = document.getElementById(`heart-reveal-inner-${num}`);
            const msgEl = document.getElementById(`heart-message-${num}`);
            if (innerCard) {
                innerCard.classList.add('opacity-0', 'scale-90');
                innerCard.classList.remove('opacity-100', 'scale-100');
            }
            if (msgEl) msgEl.classList.add('opacity-0');
            
            const points = [];
            const count = 350;
            const cx = canvas.width / 2;
            const cy = canvas.height / 2;
            
            if (['proposal', 'anniversary', 'love_letter', 'crush'].includes(category)) {
                for (let i = 0; i < count; i++) {
                    const t = (i / count) * Math.PI * 2;
                    const r = canvas.width * 0.045;
                    const x = cx + 16 * Math.pow(Math.sin(t), 3) * r;
                    const y = cy - (13 * Math.cos(t) - 5 * Math.cos(2*t) - 2 * Math.cos(3*t) - Math.cos(4*t)) * r;
                    points.push({ x, y });
                }
            } else if (['birthday', 'surprise'].includes(category)) {
                for (let i = 0; i < count; i++) {
                    const t = i / count;
                    let x, y;
                    if (t < 0.25) {
                        x = cx - 80 + t * 4 * 160;
                        y = cy + 40;
                    } else if (t < 0.5) {
                        x = cx - 60 + (t - 0.25) * 4 * 120;
                        y = cy + 10;
                    } else if (t < 0.75) {
                        x = cx - 40 + (t - 0.5) * 4 * 80;
                        y = cy - 20;
                    } else {
                        x = cx;
                        y = cy - 50 + (t - 0.75) * 4 * 30;
                    }
                    points.push({ x, y });
                }
            } else if (['sorry', 'patchup', 'mana_lo'].includes(category)) {
                for (let i = 0; i < count; i++) {
                    const t = (i / count) * Math.PI * 2;
                    let x, y;
                    if (i % 2 === 0) {
                        x = cx + Math.sin(t) * 90;
                        y = cy + Math.cos(t) * 90;
                    } else {
                        const mt = (i / count) * Math.PI;
                        x = cx + Math.cos(mt) * 40;
                        y = cy + 30 + Math.sin(mt) * 15;
                    }
                    points.push({ x, y });
                }
            } else {
                for (let i = 0; i < count; i++) {
                    const t = (i / count) * Math.PI * 2;
                    const r = 80 + 35 * Math.cos(5 * t);
                    const x = cx + r * Math.sin(t);
                    const y = cy - r * Math.cos(t);
                    points.push({ x, y });
                }
            }
            
            const particles = [];
            for (let i = 0; i < count; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    vx: 0,
                    vy: 0,
                    targetX: points[i].x,
                    targetY: points[i].y,
                    size: Math.random() * 2 + 1,
                    color: `rgba(${220 + Math.random()*35}, ${100 + Math.random()*60}, ${140 + Math.random()*40}, 0.8)`
                });
            }
            
            let frame = 0;
            let active = true;
            function tick() {
                if (!active) return;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                let converged = 0;
                
                particles.forEach(p => {
                    const dx = p.targetX - p.x;
                    const dy = p.targetY - p.y;
                    const dist = Math.sqrt(dx*dx + dy*dy);
                    
                    if (dist < 2) {
                        converged++;
                        p.x = p.targetX;
                        p.y = p.targetY;
                    } else {
                        const force = (dist / 100) * 0.15 + 0.05;
                        p.vx = p.vx * 0.9 + (dx / dist) * force;
                        p.vy = p.vy * 0.9 + (dy / dist) * force;
                        p.x += p.vx;
                        p.y += p.vy;
                    }
                    
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                    ctx.fillStyle = p.color;
                    ctx.fill();
                });
                
                frame++;
                if (converged > count * 0.95 || frame > 120) {
                    if (innerCard) {
                        innerCard.classList.remove('opacity-0', 'scale-90');
                        innerCard.classList.add('opacity-100', 'scale-100');
                    }
                    if (msgEl) {
                        msgEl.classList.remove('opacity-0');
                        msgEl.classList.add('opacity-100');
                    }
                    active = false;
                } else {
                    requestAnimationFrame(tick);
                }
            }
            tick();
        }

        // MEMORY COLLAGE EXPLOSION
        function runPremiumCollageExplosion(num) {
            const container = document.getElementById(`collage-explosion-area-${num}`);
            if (!container) return;
            
            const cards = container.querySelectorAll('[id^="collage-card-"]');
            const msgEl = document.getElementById(`collage-message-${num}`);
            if (msgEl) msgEl.classList.add('opacity-0');
            
            const w = container.clientWidth;
            const h = container.clientHeight;
            
            cards.forEach((card, idx) => {
                const side = idx % 4;
                let startX = 0, startY = 0;
                if (side === 0) { startX = Math.random() * w; startY = -120; }
                else if (side === 1) { startX = Math.random() * w; startY = h + 120; }
                else if (side === 2) { startX = -120; startY = Math.random() * h; }
                else { startX = w + 120; startY = Math.random() * h; }
                
                card.style.left = `${startX}px`;
                card.style.top = `${startY}px`;
                card.style.transform = `scale(0.5) rotate(0deg)`;
                card.style.opacity = '0';
                
                const cols = Math.ceil(Math.sqrt(cards.length));
                const rows = Math.ceil(cards.length / cols);
                const cellW = (w - 40) / cols;
                const cellH = (h - 40) / rows;
                
                const c = idx % cols;
                const r = Math.floor(idx / cols);
                
                const cardWidth = card.clientWidth || (window.innerWidth > 768 ? 160 : 96);
                const cardHeight = card.clientHeight || (window.innerWidth > 768 ? 200 : 120);
                const targetX = 10 + c * cellW + Math.random() * Math.max(10, cellW - cardWidth - 10);
                const targetY = 10 + r * cellH + Math.random() * Math.max(10, cellH - cardHeight - 10);
                const rot = parseFloat(card.dataset.finalRot) || 0;
                
                setTimeout(() => {
                    card.style.left = `${targetX}px`;
                    card.style.top = `${targetY}px`;
                    card.style.transform = `scale(1) rotate(${rot}deg)`;
                    card.style.opacity = '1';
                }, idx * 250);
            });
            
            setTimeout(() => {
                if (msgEl) {
                    msgEl.classList.remove('opacity-0');
                    msgEl.classList.add('opacity-100');
                }
            }, cards.length * 250 + 500);
        }

        // FLOATING MEMORIES
        function runPremiumFloatingMemories(num) {
            const space = document.getElementById(`floating-memories-space-${num}`);
            if (!space) return;
            const items = space.children;
            for (let item of items) {
                item.style.animation = 'none';
                item.offsetHeight;
                item.style.animation = '';
            }
        }

        // STAR MEMORY SKY
        function runPremiumStarSky(num) {
            const canvas = document.getElementById(`star-sky-canvas-${num}`);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const parent = canvas.parentElement;
            canvas.width = parent.clientWidth || 300;
            canvas.height = parent.clientHeight || 450;
            
            const stars = [];
            for (let i = 0; i < 60; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    radius: Math.random() * 1.5,
                    alpha: Math.random(),
                    speed: Math.random() * 0.02 + 0.005
                });
            }
            
            let active = true;
            function drawSky() {
                if (!active) return;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#020617';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                stars.forEach(s => {
                    s.alpha += s.speed;
                    if (s.alpha > 1 || s.alpha < 0) {
                        s.speed = -s.speed;
                    }
                    ctx.beginPath();
                    ctx.arc(s.x, s.y, s.radius, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(255, 255, 255, ${Math.max(0, Math.min(1, s.alpha))})`;
                    ctx.fill();
                });
                requestAnimationFrame(drawSky);
            }
            drawSky();
            
            document.querySelectorAll(`[id^="star-photo-${num}-"]`).forEach(card => {
                card.classList.add('opacity-0', 'scale-75', 'pointer-events-none');
            });
        }
        
        function toggleStarPhoto(slideNum, starIdx) {
            const card = document.getElementById(`star-photo-${slideNum}-${starIdx}`);
            if (!card) return;
            
            const isOpen = card.classList.contains('opacity-100');
            document.querySelectorAll(`[id^="star-photo-${slideNum}-"]`).forEach(c => {
                c.classList.add('opacity-0', 'scale-75', 'pointer-events-none');
                c.classList.remove('opacity-100', 'scale-100');
            });
            
            if (!isOpen) {
                card.classList.remove('opacity-0', 'scale-75', 'pointer-events-none');
                card.classList.add('opacity-100', 'scale-100');
            }
        }

        // MEMORY BOOK (SCRAPBOOK PAGEFLIPS)
        let activeBookPage = {};
        function runPremiumMemoryBook(num) {
            activeBookPage[num] = 0;
            const pages = document.querySelectorAll(`[id^="book-page-${num}-"]`);
            pages.forEach((page, idx) => {
                page.style.transform = 'rotateY(0deg)';
                page.style.zIndex = 100 - idx;
            });
            const btn = document.getElementById(`book-continue-${num}`);
            if (btn) {
                btn.classList.add('opacity-50', 'pointer-events-none');
                btn.textContent = 'Read the Journal to Continue';
            }
        }
        
        function prevBookPage(num) {
            if (activeBookPage[num] === undefined || activeBookPage[num] <= 0) return;
            activeBookPage[num]--;
            const idx = activeBookPage[num];
            const page = document.getElementById(`book-page-${num}-${idx}`);
            if (page) {
                page.style.transform = 'rotateY(0deg)';
                page.style.zIndex = 100 - idx;
            }
        }
        
        function nextBookPage(num) {
            const pages = document.querySelectorAll(`[id^="book-page-${num}-"]`);
            if (activeBookPage[num] === undefined || activeBookPage[num] >= pages.length - 1) return;
            const idx = activeBookPage[num];
            const page = document.getElementById(`book-page-${num}-${idx}`);
            if (page) {
                page.style.transform = 'rotateY(-180deg)';
                page.style.zIndex = 100 + idx;
            }
            activeBookPage[num]++;
            
            const btn = document.getElementById(`book-continue-${num}`);
            if (btn) {
                btn.classList.remove('opacity-50', 'pointer-events-none');
                btn.textContent = 'Continue →';
            }
        }

        // REASONS WHY YOU ARE SPECIAL (STACK DECK)
        let activeReasonIdx = {};
        function runPremiumReasonsSpecial(num) {
            activeReasonIdx[num] = 0;
            const cards = document.querySelectorAll(`[id^="reasons-card-${num}-"]`);
            cards.forEach((card, idx) => {
                card.classList.remove('opacity-100', 'scale-100', 'translate-x-[200px]', 'rotate-12', 'pointer-events-none');
                card.classList.add('opacity-0', 'scale-90');
                card.style.zIndex = cards.length - idx;
            });
            
            const first = document.getElementById(`reasons-card-${num}-0`);
            if (first) {
                first.classList.remove('opacity-0', 'scale-90');
                first.classList.add('opacity-100', 'scale-100');
            }
        }
        
        function nextReasonCard(num, totalReasons) {
            if (activeReasonIdx[num] === undefined) activeReasonIdx[num] = 0;
            const currIdx = activeReasonIdx[num];
            const currCard = document.getElementById(`reasons-card-${num}-${currIdx}`);
            if (currCard) {
                currCard.classList.remove('opacity-100', 'scale-100');
                currCard.classList.add('opacity-0', 'scale-90', 'translate-x-[200px]', 'rotate-12');
            }
            
            const cards = document.querySelectorAll(`[id^="reasons-card-${num}-"]`);
            const total = totalReasons || cards.length || 1;
            const nextIdx = (currIdx + 1) % total;
            activeReasonIdx[num] = nextIdx;
            
            const nextCard = document.getElementById(`reasons-card-${num}-${nextIdx}`);
            if (nextCard) {
                nextCard.classList.remove('translate-x-[200px]', 'rotate-12');
                nextCard.offsetHeight;
                nextCard.classList.remove('opacity-0', 'scale-90');
                nextCard.classList.add('opacity-100', 'scale-100');
            }
        }

        // PHOTO PUZZLE REVEAL
        function runPremiumPuzzleReveal(num) {
            const pieces = document.querySelectorAll(`[id^="puzzle-piece-${num}-"]`);
            const msgEl = document.getElementById(`puzzle-message-${num}`);
            if (msgEl) msgEl.classList.add('opacity-0');
            
            pieces.forEach((piece) => {
                const sx = piece.dataset.startX;
                const sy = piece.dataset.startY;
                const srot = piece.dataset.startRot;
                piece.style.transform = `translate(${sx}px, ${sy}px) rotate(${srot}deg)`;
                piece.style.opacity = '0';
                
                setTimeout(() => {
                    piece.style.transform = 'translate(0, 0) rotate(0deg)';
                    piece.style.opacity = '1';
                }, 800);
            });
            
            setTimeout(() => {
                if (msgEl) {
                    msgEl.classList.remove('opacity-0');
                    msgEl.classList.add('opacity-100');
                }
            }, 2000);
        }

        // LOVE COUNTER
        let loveCounterTimer = {};
        function runPremiumLoveCounter(num) {
            const container = document.getElementById(`premium-love-counter-container-${num}`);
            if (!container) return;
            const relDateStr = container.dataset.relDate || '2025-01-01';
            const relDate = new Date(relDateStr);
            
            if (loveCounterTimer[num]) clearInterval(loveCounterTimer[num]);
            
            function updateCounter() {
                const now = new Date();
                const diffMs = now - relDate;
                if (diffMs < 0) return;
                
                const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                const hours = Math.floor(diffMs / (1000 * 60 * 60));
                const mins = Math.floor(diffMs / (1000 * 60));
                const secs = Math.floor(diffMs / 1000);
                
                const daysEl = document.getElementById(`counter-days-${num}`);
                const hoursEl = document.getElementById(`counter-hours-${num}`);
                const minsEl = document.getElementById(`counter-mins-${num}`);
                const secsEl = document.getElementById(`counter-secs-${num}`);
                
                if (daysEl) daysEl.textContent = days.toLocaleString();
                if (hoursEl) hoursEl.textContent = hours.toLocaleString();
                if (minsEl) minsEl.textContent = mins.toLocaleString();
                if (secsEl) secsEl.textContent = secs.toLocaleString();
            }
            
            updateCounter();
            loveCounterTimer[num] = setInterval(updateCounter, 1000);
        }

        // MEMORY TIMELINE
        function runPremiumMemoryTimeline(num) {
            const nodes = document.querySelectorAll(`[id^="timeline-node-${num}-"]`);
            const bar = document.getElementById(`timeline-progress-bar-${num}`);
            if (bar) bar.style.bottom = '100%';
            
            nodes.forEach(n => {
                n.classList.add('opacity-0', 'translate-x-4');
                n.classList.remove('opacity-100', 'translate-x-0');
            });
            
            setTimeout(() => {
                if (bar) bar.style.bottom = '0%';
                nodes.forEach((node, idx) => {
                    setTimeout(() => {
                        node.classList.remove('opacity-0', 'translate-x-4');
                        node.classList.add('opacity-100', 'translate-x-0');
                    }, idx * 600);
                });
            }, 500);
        }

        // PHOTO MOSAIC HEART
        function runPremiumMosaicHeart(num) {
            const tiles = document.querySelectorAll(`[id^="mosaic-tile-${num}-"]`);
            const msgEl = document.getElementById(`mosaic-message-${num}`);
            if (msgEl) msgEl.classList.add('opacity-0');
            
            tiles.forEach(t => {
                t.classList.add('opacity-0', 'scale-90');
                t.classList.remove('opacity-100', 'scale-100');
            });
            
            tiles.forEach(tile => {
                const isShape = parseInt(tile.dataset.inShape) === 1;
                const r = parseInt(tile.dataset.row);
                const c = parseInt(tile.dataset.col);
                const dist = Math.abs(r - 4.5) + Math.abs(c - 4.5);
                setTimeout(() => {
                    tile.classList.remove('opacity-0', 'scale-90');
                    tile.classList.add('opacity-100', 'scale-100');
                }, dist * 150);
            });
            
            setTimeout(() => {
                if (msgEl) {
                    msgEl.classList.remove('opacity-0');
                    msgEl.classList.add('opacity-100');
                }
            }, 1800);
        }

        // FAVORITE PHOTO SPOTLIGHT
        let spotlightTypingTimer = {};
        function runPremiumPhotoSpotlight(num) {
            const mask = document.getElementById(`spotlight-mask-${num}`);
            const img = document.getElementById(`spotlight-img-${num}`);
            const msgEl = document.getElementById(`spotlight-message-${num}`);
            if (!mask) return;
            
            mask.style.backgroundImage = 'radial-gradient(circle 90px at 0% 0%, transparent 100%, rgba(0,0,0,0.95) 100%)';
            if (img) img.style.transform = 'scale(1.05)';
            if (msgEl) msgEl.textContent = '';
            
            if (spotlightTypingTimer[num]) clearTimeout(spotlightTypingTimer[num]);
            
            setTimeout(() => {
                mask.style.backgroundImage = 'radial-gradient(circle 95px at 50% 45%, transparent 100%, rgba(0,0,0,0.93) 100%)';
                if (img) img.style.transform = 'scale(1)';
                
                if (msgEl) {
                    const fullText = msgEl.dataset.spotlightMsg || '';
                    let charIdx = 0;
                    function typeChar() {
                        if (charIdx < fullText.length) {
                            msgEl.textContent += fullText.charAt(charIdx);
                            charIdx++;
                            spotlightTypingTimer[num] = setTimeout(typeChar, 45);
                        }
                    }
                    setTimeout(typeChar, 1000);
                }
            }, 600);
        }

        /* Redesigned Voice Waveform Player Interactions */
        function updateSlideVoiceUI(slideNum, audio) {
            if (!audio) return;
            const currentTime = audio.mockCurrentTime !== undefined ? audio.mockCurrentTime : (audio.currentTime || 0);
            const duration = audio.mockDuration !== undefined ? audio.mockDuration : (audio.duration || 0);
            const pct = duration ? (currentTime / duration) : 0;
            
            // Format time string
            const timeEl = document.getElementById(`slide-voice-time-${slideNum}`);
            if (timeEl) {
                const mins = Math.floor(currentTime / 60);
                const secs = Math.floor(currentTime % 60).toString().padStart(2, '0');
                timeEl.textContent = `${mins}:${secs}`;
            }
            
            // Color waveform bars
            const bars = document.querySelectorAll(`.slide-voice-bar-${slideNum}`);
            const numActive = Math.round(pct * bars.length);
            bars.forEach((bar, idx) => {
                if (idx < numActive) {
                    bar.classList.add('slide-voice-bar-active');
                    bar.style.backgroundColor = '#ec4899'; // bg-pink-500
                } else {
                    bar.classList.remove('slide-voice-bar-active');
                    bar.style.backgroundColor = ''; // back to slate-700
                }
            });
        }

        function seekSlideVoiceByWaveform(event, slideNum) {
            const audio = document.getElementById(`slide-voice-element-${slideNum}`);
            if (!audio || !audio.duration) return;
            
            // Get click position relative to the container element
            const rect = event.currentTarget.getBoundingClientRect();
            const clickX = event.clientX - rect.left;
            const pct = clickX / rect.width;
            
            audio.currentTime = pct * audio.duration;
            updateSlideVoiceUI(slideNum, audio);
        }

        /* PREMIUM: OUR CHATS */
        window.chatTimers = window.chatTimers || {};
        function runPremiumOurChats(slideNum) {
            selectChatMoment(slideNum, 0);
        }

        function selectChatMoment(slideNum, momentIdx) {
            // Update tab UI active states
            const tabs = document.querySelectorAll(`[id^="chat-moment-tab-${slideNum}-"]`);
            tabs.forEach(tab => {
                const idx = parseInt(tab.id.replace(`chat-moment-tab-${slideNum}-`, ''));
                if (idx === momentIdx) {
                    tab.className = "chat-moment-tag px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap shadow-md border transition duration-300 bg-pink-500 border-pink-500 text-white";
                } else {
                    tab.className = "chat-moment-tag px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap shadow-md border transition duration-300 bg-zinc-900/60 border-zinc-800 text-zinc-400";
                }
            });

            // Fetch moment data
            if (!window.chatMomentsData || !window.chatMomentsData[slideNum] || !window.chatMomentsData[slideNum][momentIdx]) return;
            const moment = window.chatMomentsData[slideNum][momentIdx];
            
            // Update subtitle/title
            const titleEl = document.getElementById(`chat-moment-title-${slideNum}`);
            if (titleEl) {
                titleEl.textContent = moment.title;
            }

            // Clear any active message timers
            if (window.chatTimers[slideNum]) {
                window.chatTimers[slideNum].forEach(t => clearTimeout(t));
            }
            window.chatTimers[slideNum] = [];

            // Get dialogue container
            const messagesContainer = document.getElementById(`chat-messages-${slideNum}`);
            if (!messagesContainer) return;
            messagesContainer.innerHTML = '';
            
            const themeGradient = messagesContainer.dataset.themeGradient || 'from-pink-500 to-rose-500';

            // Parse dialogue
            const lines = moment.dialogue.split('\n').map(l => l.trim()).filter(l => l.length > 0);
            
            lines.forEach((line, idx) => {
                let sender = '';
                let text = line;
                let alignment = 'center'; // default if no colon
                
                if (line.startsWith('Her:')) {
                    sender = 'Her';
                    text = line.substring(4).trim();
                    alignment = 'left';
                } else if (line.startsWith('You:')) {
                    sender = 'You';
                    text = line.substring(4).trim();
                    alignment = 'right';
                } else {
                    // Try to match generic Sender: format
                    const colonIdx = line.indexOf(':');
                    if (colonIdx > 0 && colonIdx < 15) {
                        sender = line.substring(0, colonIdx).trim();
                        text = line.substring(colonIdx + 1).trim();
                        alignment = sender.toLowerCase() === 'you' ? 'right' : 'left';
                    }
                }

                // Create bubble HTML structure
                const bubbleOuter = document.createElement('div');
                bubbleOuter.className = `flex w-full ${alignment === 'right' ? 'justify-end' : (alignment === 'left' ? 'justify-start' : 'justify-center')}`;
                
                const bubble = document.createElement('div');
                if (alignment === 'left') {
                    bubble.className = "chat-bubble chat-bubble-left px-4 py-2.5 rounded-2xl text-xs leading-relaxed font-medium shadow-md";
                    bubble.textContent = text;
                } else if (alignment === 'right') {
                    bubble.className = "chat-bubble chat-bubble-right px-4 py-2.5 rounded-2xl text-xs leading-relaxed font-medium shadow-md bg-gradient-to-r " + themeGradient;
                    bubble.textContent = text;
                } else {
                    bubble.className = "chat-bubble chat-bubble-center py-1 text-[10px] text-zinc-500 font-semibold tracking-wider uppercase";
                    bubble.textContent = text;
                }
                
                bubbleOuter.appendChild(bubble);
                messagesContainer.appendChild(bubbleOuter);

                // Animate entry sequentially
                const timer = setTimeout(() => {
                    bubble.classList.add('show');
                    // Scroll to bottom
                    messagesContainer.scrollTo({
                        top: messagesContainer.scrollHeight,
                        behavior: 'smooth'
                    });
                }, idx * 1000 + 400);

                window.chatTimers[slideNum].push(timer);
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        // ═══ AUTO-SCALING VIEWPORT RESIZER ═══
        function autoScaleSlides() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
            
            document.querySelectorAll('.slide-pane.active, .slide-pane.transitioning').forEach(pane => {
                let target = pane.querySelector('.slide-content-wrapper');
                if (!target) return;
                
                // Reset scale styles on wrapper to avoid layout issues
                const wrapper = pane.querySelector('.slide-content-wrapper');
                if (wrapper) {
                    wrapper.style.transform = 'none';
                    wrapper.style.width = '100%';
                    wrapper.style.height = '100%';
                }

                // Never scale cinematic overlays; always keep them full size to prevent misalignment
                pane.querySelectorAll('.cinematic-overlay').forEach(overlay => {
                    overlay.style.transform = 'none';
                    overlay.style.width = '100%';
                    overlay.style.height = '100%';
                });
                
                target.style.transform = 'none';
                target.style.width = '100%';
                target.style.height = '100%';
                
                const style = window.getComputedStyle(pane);
                const paddingTop = parseFloat(style.paddingTop) || 0;
                const paddingBottom = parseFloat(style.paddingBottom) || 0;
                const availableHeight = pane.clientHeight - paddingTop - paddingBottom;
                
                // Temporarily convert direct absolute children of target to relative flow to calculate natural scrollHeight
                const absoluteElements = [];
                const children = target.querySelectorAll('.absolute');
                children.forEach(el => {
                    if (el.parentNode === target) {
                        absoluteElements.push({ el, originalPosition: el.style.position });
                        el.style.position = 'relative';
                    }
                });
                
                target.style.height = 'auto';
                const naturalHeight = target.scrollHeight;
                
                // Restore original position styling
                absoluteElements.forEach(item => {
                    item.el.style.position = item.originalPosition;
                });
                
                if (naturalHeight > availableHeight && availableHeight > 0) {
                    const ratio = availableHeight / naturalHeight;
                    target.style.transform = `scale(${ratio})`;
                    target.style.transformOrigin = 'top center';
                    target.style.width = `${100 / ratio}%`;
                } else {
                    target.style.height = '100%';
                }
            });
        }

        window.addEventListener('resize', autoScaleSlides);
        window.addEventListener('orientationchange', autoScaleSlides);
        document.addEventListener('DOMContentLoaded', () => {
            autoScaleSlides();
            // Bind image load events
            document.querySelectorAll('.slide-pane img').forEach(img => {
                if (img.complete) {
                    autoScaleSlides();
                } else {
                    img.addEventListener('load', autoScaleSlides);
                }
            });
            // Bind font ready event
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(autoScaleSlides);
            }
        });
        window.addEventListener('load', autoScaleSlides);

        // ═══ TOUCH SWIPE NAVIGATION ═══
        let touchStartX = 0;
        let touchStartY = 0;
        let touchStartTarget = null;
        
        document.addEventListener('touchstart', e => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            touchStartTarget = e.target;
        }, { passive: true });
        
        document.addEventListener('touchend', e => {
            if (!touchStartTarget) return;
            
            // Check if touch originated from interactive gameplay elements
            if (touchStartTarget.closest('canvas, button, a, input, select, textarea, .heart-key-draggable, .lantern-wrapper, #music-widget, [onclick]')) {
                return;
            }
            
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            
            const diffX = touchEndX - touchStartX;
            const diffY = touchEndY - touchStartY;
            
            // Validate it is horizontal swipe and exceeds gesture thresholds
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 60) {
                if (diffX < 0) {
                    // Swipe Left -> Next Slide
                    if (currentSlide < totalSlides) {
                        // Check if the current slide is locked (requires completion before continuing)
                        const nextBtn = document.querySelector(`#slide-${currentSlide} button[id*="-continue-"], #slide-${currentSlide} button[id*="-btn-"]`);
                        if (nextBtn && (nextBtn.classList.contains('pointer-events-none') || nextBtn.disabled || nextBtn.classList.contains('opacity-50'))) {
                            return;
                        }
                        goSlide(currentSlide + 1);
                    }
                } else {
                    // Swipe Right -> Previous Slide
                    if (currentSlide > 1) {
                        goSlide(currentSlide - 1);
                    }
                }
            }
        }, { passive: true });
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
      // Import the functions you need from the SDKs you need
      import { initializeApp } from "firebase/app";
      import { getAnalytics } from "firebase/analytics";

      // Your web app's Firebase configuration
      const firebaseConfig = {
        apiKey: "AIzaSyB-UcbJripzj5BYfXNZzGVGNRvp6fdpzdk",
        authDomain: "loopr-5afff.firebaseapp.com",
        projectId: "loopr-5afff",
        storageBucket: "loopr-5afff.firebasestorage.app",
        messagingSenderId: "461317839365",
        appId: "1:461317839365:web:5a7e62412a085120edda6c",
        measurementId: "G-E64N7NW2HW"
      };

      // Initialize Firebase
      const app = initializeApp(firebaseConfig);
      const analytics = getAnalytics(app);
    </script>
    <?php endif; // end password check ?>

<!-- AAC Playback Fix: Runtime decoder for raw .aac files that won't play natively -->
<script src="<?= $base_url ?>assets/js/aac-playback-fix.js"></script>

<script src="<?= $base_url ?>assets/js/aac-play.js"></script>
</body>
</html>
