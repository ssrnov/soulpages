<?php
require_once 'includes/functions.php';

// Calculate absolute base URL to prevent clean URL path resolution bugs
$base_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_dir = rtrim($base_dir, '/') . '/';
$base_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $base_dir;

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    redirect('index.php');
}

// Fetch Page Details
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    redirect('index.php');
}

// Verify edit authorization
$session_id = session_id();
$is_authorized = false;
if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) {
    $is_authorized = true;
} elseif (empty($page['user_id']) && $page['guest_session_id'] === $session_id) {
    $is_authorized = true;
}

if (!$is_authorized) {
    // If not authorized to edit, they can only view the public page (redirect to p.php)
    redirect("p.php?s=$slug");
}

// Handle Publish Action
if (isset($_POST['action']) && $_POST['action'] === 'publish') {
    // Calculate chosen duration
    $days = 10;
    if (!empty($page['expiry_date']) && !empty($page['created_at'])) {
        $diff = strtotime($page['expiry_date']) - strtotime($page['created_at']);
        if ($diff > 0) {
            $days = round($diff / 86400);
        }
    }
    if ($days < 10) $days = 10;
    
    $expiry_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    $storage_bytes = calculate_page_storage($page['id'], $pdo);
    
    $stmt_pub = $pdo->prepare("UPDATE pages SET status = 'published', expiry_date = ?, storage_bytes = ?, is_expired = 0 WHERE id = ?");
    if ($stmt_pub->execute([$expiry_date, $storage_bytes, $page['id']])) {
        redirect("publish-success.php?slug=$slug");
    }
}

// Calculate selected duration for UI checks
$days = 10;
if (!empty($page['expiry_date']) && !empty($page['created_at'])) {
    $diff = strtotime($page['expiry_date']) - strtotime($page['created_at']);
    if ($diff > 0) {
        $days = round($diff / 86400);
    }
}
$requires_payment = false;
$price = 0;
if ($days > 10) {
    if ($days === 15) $price = 5;
    elseif ($days === 30) $price = 20;
    elseif ($days === 60) $price = 50;
    elseif ($days === 90) $price = 80;
    else $price = ($days - 10) * 1;
    
    // Payment is only required if guest
    if (empty($page['user_id'])) {
        $requires_payment = true;
    }
}

// Fetch Page Images
$stmt_img = $pdo->prepare("SELECT * FROM page_images WHERE page_id = ? ORDER BY position");
$stmt_img->execute([$page['id']]);
$images = $stmt_img->fetchAll();

$categories = get_categories(true);
$cat_key = $page['category'];
$cat_info = ($categories[$cat_key] ?? reset($categories)) ?: [];
$slide_config = $cat_info['slides'] ?? [];
$slide_data = decode_slide_data($page['slide_data'] ?? '');

$stmt_mus = $pdo->prepare("SELECT * FROM music_library WHERE category = ? OR category = 'all' ORDER BY title");
$stmt_mus->execute([$cat_key]);
$music_options = $stmt_mus->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Preview - <?= h($page['title']) ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;600;800&family=Pacifico&family=Playfair+Display:ital,wght@0,600;1,700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                        serif: ['Playfair Display', 'serif'],
                        cursive: ['Pacifico', 'cursive'],
                    }
                }
            }
        }
    </script>
    <style>
        html, body {
            overscroll-behavior-y: none;
        }
        /* â•â•â• SOULPAGES LIGHT PINK GLOBAL THEME â•â•â• */
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

        body {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }

        /* Global Typography Contrast Fix */
        body, p, label, li, span:not(.sp-gradient-text):not(.text-transparent):not(.relative) {
            color: #334155 !important; /* slate-700 */
        }
        h1, h2, h3, h4, h5, h6 {
            color: #0f172a !important; /* slate-900 */
        }

        /* Buttons & Badges (White Text) */
        .sp-btn-primary, .sp-btn-primary *, button[type="submit"], button[type="submit"] *, .sp-btn-primary span, button[onclick*="saveDetails"] {
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

        .glass-modal {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(16px);
            border: 1px solid rgba(0, 0, 0, 0.08) !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        }

        /* Floating Edit Bar override */
        .fixed[class*="bg-slate-900/80"] {
            background-color: rgba(255, 255, 255, 0.9) !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
        }
        .fixed[class*="bg-slate-900/80"] a, .fixed[class*="bg-slate-900/80"] span, .fixed[class*="bg-slate-900/80"] button {
            color: #334155 !important;
        }
        .fixed[class*="bg-slate-900/80"] a:hover, .fixed[class*="bg-slate-900/80"] button:hover {
            color: #db2777 !important;
        }

        /* Form elements */
        input[type="text"], input[type="number"], input[type="email"], input[type="password"], textarea, select {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }
        input::placeholder, textarea::placeholder {
            color: #94a3b8 !important;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #db2777 !important;
            box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15) !important;
        }

        /* Card and modal item containers */
        .bg-slate-900\/40, .bg-slate-950\/40, .bg-slate-950\/60, .bg-slate-900\/60, .bg-slate-900, .bg-slate-950, .bg-slate-850, .bg-slate-900\/30, .bg-slate-905\/40, .bg-slate-955\/40 {
            background-color: #ffffff !important;
        }
        .border-slate-800, .border-slate-850, .border-slate-900 {
            border-color: #cbd5e1 !important;
        }

        /* Buttons and recorders */
        .bg-slate-805, .bg-slate-800, .bg-slate-850, button[type="button"] {
            background-color: #f1f5f9 !important;
            color: #334155 !important;
        }
        .peer:not(:checked) + .bg-slate-800 {
            background-color: #cbd5e1 !important;
        }
        .peer:checked + .bg-slate-800 {
            background-color: #db2777 !important;
        }

        .preview-pane {
            font-family: '<?= $page['font_style'] === 'Playfair Display' ? 'Playfair Display' : ($page['font_style'] === 'Outfit' ? 'Outfit' : 'Inter') ?>', sans-serif;
        }
    </style>
    <?php if ($requires_payment): ?>
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <?php endif; ?>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col antialiased select-none">

    <!-- Emoji Rain -->
    <?php render_emoji_rain(); ?>

    <!-- FLOATING EDIT BAR -->
    <div class="fixed top-4 left-1/2 -translate-x-1/2 z-50 w-[95%] max-w-4xl bg-slate-900/80 backdrop-blur-md border border-slate-800 rounded-2xl px-4 py-3 flex items-center justify-between shadow-2xl">
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="text-xs font-bold text-slate-300 hover:text-white transition flex items-center gap-1" title="Go to Dashboard">
                <span>🏠</span>
                <span class="hidden md:inline">Dashboard</span>
            </a>
            <?php if (is_admin()): ?>
                <a href="admin/index.php" class="text-xs font-bold text-purple-400 hover:text-purple-300 transition flex items-center gap-1" title="Admin Portal">
                    <span>🛠️</span>
                    <span class="hidden lg:inline">Admin</span>
                </a>
            <?php endif; ?>
            <a href="logout.php" class="text-xs font-semibold text-slate-500 hover:text-white transition" title="Logout">Logout</a>
            
            <span class="flex h-2 w-2 relative hidden sm:inline-flex">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-pink-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-pink-500"></span>
            </span>
        </div>
        
        <div class="flex items-center space-x-2">
            <button onclick="openModal('detailsModal')" class="px-2 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1">
                <span>✍️</span> <span class="hidden md:inline">Details</span>
            </button>
            <button onclick="openModal('musicModal')" class="px-2 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1">
                <span>🎵</span> <span class="hidden md:inline">Music</span>
            </button>
            <button onclick="openModal('photosModal')" class="px-2 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1">
                <span>🖼️</span> <span class="hidden md:inline">Photos</span>
            </button>
            <button onclick="openModal('mediaModal')" class="px-2 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1">
                <span>🎙️</span> <span class="hidden md:inline">Voice & Video</span>
            </button>
            
            <?php if ($requires_payment): ?>
                <button id="publish-btn" onclick="initiatePublishPayment()" class="px-3 py-2 bg-gradient-to-r from-pink-500 to-purple-500 hover:opacity-95 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1 shadow-lg shadow-pink-500/20">
                    <span>🚀</span> <span>Publish</span>
                </button>
            <?php else: ?>
                <form action="edit-preview.php?slug=<?= h($slug) ?>" method="POST" class="inline" onsubmit="return showPublishingOverlay();">
                    <input type="hidden" name="action" value="publish">
                    <button type="submit" class="px-3 py-2 bg-gradient-to-r from-pink-500 to-purple-500 hover:opacity-95 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1 shadow-lg shadow-pink-500/20">
                        <span>🚀</span> <span>Publish</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Warnings Banner -->
    <?php if (isset($_SESSION['upload_warnings']) && !empty($_SESSION['upload_warnings'])): ?>
        <div class="fixed top-20 left-1/2 -translate-x-1/2 z-50 w-full max-w-md px-4">
            <div class="bg-red-500/15 border border-red-500/30 text-red-400 p-4 rounded-2xl text-xs shadow-xl backdrop-blur-md relative">
                <button onclick="this.parentElement.parentElement.remove()" class="absolute top-2 right-3 text-red-400 hover:text-white font-bold text-sm">&times;</button>
                <p class="font-bold mb-1.5 flex items-center gap-1">âš ï¸ Upload Errors / Warnings during creation:</p>
                <ul class="list-disc pl-4 space-y-1">
                    <?php foreach ($_SESSION['upload_warnings'] as $warning): ?>
                        <li><?= h($warning) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-2.5 text-[10px] text-slate-400">You can try re-uploading the file using the <strong>Voice & Video</strong> button in the toolbar above.</p>
            </div>
        </div>
        <?php unset($_SESSION['upload_warnings']); ?>
    <?php endif; ?>

    <!-- PREVIEW AREA (Live iframe Preview) -->
    <main class="flex-grow pt-20 md:pt-24 pb-0 md:pb-8 flex items-stretch md:items-center justify-center px-0 md:px-4 h-[calc(100vh-5rem)] md:h-auto">
        <div class="w-full h-full md:h-[80vh] md:max-w-md md:aspect-[9/19] bg-slate-950 md:rounded-[2.5rem] md:shadow-2xl md:border-8 md:border-slate-900 relative overflow-hidden" id="preview-frame">
            <iframe src="p.php?s=<?= h($slug) ?>" class="w-full h-full border-0" id="live-preview-iframe"></iframe>
        </div>
    </main>

    <!-- MODAL 1: EDIT DETAILS -->
    <div id="detailsModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
        <div class="w-full max-w-lg glass-modal rounded-3xl p-6 md:p-8 relative">
            <button onclick="closeModal('detailsModal')" class="absolute top-4 right-4 text-slate-400 hover:text-white font-bold text-xl">&times;</button>
            <h3 class="text-xl font-bold mb-4 font-heading text-white">Edit Details</h3>
            
            <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Sender Name</label>
                        <input type="text" id="edit_sender" value="<?= h($page['sender_name']) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Recipient Name</label>
                        <input type="text" id="edit_receiver" value="<?= h($page['receiver_name']) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Nickname (Optional)</label>
                        <input type="text" id="edit_nickname" value="<?= h($page['nickname'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-sm" placeholder="Baby, Bestie...">
                    </div>
                    <input type="checkbox" id="edit_special_date_enabled" value="1" style="display: none;">
                    <input type="hidden" id="edit_date" value="">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Page Title</label>
                    <input type="text" id="edit_title" value="<?= h($page['title']) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Letter Content Message</label>
                    <textarea id="edit_letter" rows="4" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-sm resize-none"><?= h($page['letter_text']) ?></textarea>
                </div>

                <!-- Universal Interactive Ending Panel -->
                <div class="mb-4 bg-slate-900/40 border border-slate-800 rounded-2xl p-4 space-y-3">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" id="edit_interactive_ending" value="1" <?= (int)($page['interactive_ending'] ?? 1) === 1 ? 'checked' : '' ?> class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                        <span class="text-xs font-semibold text-slate-200 font-heading">Interactive Ending</span>
                    </label>
                    
                    <div id="edit-interactive-ending-settings" class="<?= (int)($page['interactive_ending'] ?? 1) === 1 ? '' : 'hidden' ?> space-y-3 pt-2 border-t border-slate-800/60">
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Interactive Prompt Question</label>
                            <input type="text" id="edit_question" value="<?= h($page['interactive_question'] ?? $page['proposal_question']) ?>"
                                   class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-3 py-2 text-white placeholder-slate-500 outline-none text-xs"
                                   placeholder="e.g. Will you be mine?">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">YES Button</label>
                                <input type="text" id="edit_interactive_yes_text" value="<?= h($page['interactive_yes_text'] ?? 'Yes! ❤️') ?>"
                                       class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-3 py-1.5 text-white outline-none text-xs">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">NO Button</label>
                                <input type="text" id="edit_interactive_no_text" value="<?= h($page['interactive_no_text'] ?? 'No') ?>"
                                       class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-3 py-1.5 text-white outline-none text-xs">
                            </div>
                        </div>
                        
                        <div class="flex flex-col space-y-2 pt-1">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" id="edit_interactive_funny_no" value="1" <?= (int)($page['interactive_funny_no'] ?? 1) === 1 ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                                <span class="text-[10px] font-medium text-slate-300">Enable Funny NO Button Pranks</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" id="edit_interactive_ask_name" value="1" <?= (int)($page['interactive_ask_name'] ?? 1) === 1 ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                                <span class="text-[10px] font-medium text-slate-300">Ask Recipient's Name at Startup</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Active Slide Toggles -->
                <div class="mb-4 bg-slate-900/40 border border-slate-800 rounded-2xl p-4 space-y-3">
                    <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider font-heading mb-1">Choose Active Slide Blocks</h4>
                    
                    <div class="space-y-2">
                        <!-- Toggle for Letter -->
                        <label class="flex items-center justify-between cursor-pointer py-1">
                            <span class="text-xs text-slate-200">Text Letter Slide</span>
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="edit_letter_enabled" value="1" <?= (!isset($slide_data['letter_enabled']) || (int)$slide_data['letter_enabled'] === 1) ? 'checked' : '' ?> class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                            </div>
                        </label>
                        
                        <!-- Toggle for Gallery -->
                        <label class="flex items-center justify-between cursor-pointer py-1">
                            <span class="text-xs text-slate-200">Photos Gallery Slide</span>
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="edit_gallery_enabled" value="1" <?= (!isset($slide_data['gallery_enabled']) || (int)$slide_data['gallery_enabled'] === 1) ? 'checked' : '' ?> class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                            </div>
                        </label>
                        
                        <!-- Toggle for Voice Message -->
                        <label class="flex items-center justify-between cursor-pointer py-1">
                            <span class="text-xs text-slate-200">Voice Note Slide</span>
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="edit_voice_message_enabled" value="1" <?= (!isset($slide_data['voice_message_enabled']) || (int)$slide_data['voice_message_enabled'] === 1) ? 'checked' : '' ?> class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                            </div>
                        </label>
                        
                        <!-- Toggle for Video Message -->
                        <label class="flex items-center justify-between cursor-pointer py-1">
                            <span class="text-xs text-slate-200">Video Message Slide</span>
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="edit_video_message_enabled" value="1" <?= (!isset($slide_data['video_message_enabled']) || (int)$slide_data['video_message_enabled'] === 1) ? 'checked' : '' ?> class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                            </div>
                        </label>
                    </div>
                </div>

                <?php
                $all_themes = get_themes();
                ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Visual Theme</label>
                    <select id="edit_theme" class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-3 py-2 text-sm outline-none focus:border-pink-500">
                        <?php foreach ($all_themes as $tkey => $tval): ?>
                            <option value="<?= $tkey ?>" <?= ($page['theme'] ?? '') === $tkey ? 'selected' : '' ?>><?= $tval['icon'] ?> <?= h($tval['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Accent Color</label>
                        <input type="color" id="edit_accent" value="<?= h($page['accent_color']) ?>" class="w-full h-10 bg-transparent border-0 rounded-xl cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Font Typography</label>
                        <select id="edit_font" class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-3 py-2 text-sm outline-none focus:border-pink-500">
                            <option value="Playfair Display" <?= $page['font_style'] === 'Playfair Display' ? 'selected' : '' ?>>Playfair Display</option>
                            <option value="Outfit" <?= $page['font_style'] === 'Outfit' ? 'selected' : '' ?>>Outfit</option>
                            <option value="Inter" <?= $page['font_style'] === 'Inter' ? 'selected' : '' ?>>Inter</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Photo Display Mode</label>
                    <select id="edit_photo_fit" class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-3 py-2 text-sm outline-none focus:border-pink-500">
                        <option value="cover" <?= ($page['photo_fit_mode'] ?? 'cover') === 'cover' ? 'selected' : '' ?>>Crop to Fill / Cover (Modern & Full)</option>
                        <option value="contain" <?= ($page['photo_fit_mode'] ?? 'cover') === 'contain' ? 'selected' : '' ?>>Fit Entire Photo / Contain (No Cropping)</option>
                    </select>
                </div>

                <?php
                // Calculate current selected duration
                $curr_days = 10;
                if (!empty($page['expiry_date']) && !empty($page['created_at'])) {
                    $diff = strtotime($page['expiry_date']) - strtotime($page['created_at']);
                    if ($diff > 0) {
                        $curr_days = round($diff / 86400);
                    }
                }
                $is_preset = in_array($curr_days, [10, 15, 30, 60, 90]);
                ?>
                <div class="mt-4 border-t border-slate-800/60 pt-4">
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Page Expiry Duration</label>
                    <select id="edit_expiry_duration" onchange="updateEditValidityCost()" class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-3 py-2 text-sm outline-none focus:border-pink-500">
                        <option value="10" <?= $curr_days === 10 ? 'selected' : '' ?>>10 Days (Free / Default)</option>
                        <option value="15" <?= $curr_days === 15 ? 'selected' : '' ?>>15 Days (₹5 / 5 paid days)</option>
                        <option value="30" <?= $curr_days === 30 ? 'selected' : '' ?>>30 Days (₹20 / 20 paid days)</option>
                        <option value="60" <?= $curr_days === 60 ? 'selected' : '' ?>>60 Days (₹50 / 50 paid days)</option>
                        <option value="90" <?= $curr_days === 90 ? 'selected' : '' ?>>90 Days (₹80 / 80 paid days)</option>
                        <option value="custom" <?= !$is_preset ? 'selected' : '' ?>>Custom Days (₹1 / extra day)</option>
                    </select>
                </div>
                
                <div id="edit_custom_days_container" class="<?= $is_preset ? 'hidden' : '' ?> mt-2">
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Enter Custom Days</label>
                    <input type="number" id="edit_custom_days" min="10" max="365" value="<?= $curr_days ?>" oninput="updateEditValidityCost()" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-sm">
                </div>

                <?php if (!empty($page['user_id'])): ?>
                <?php
                // Fetch user credits
                $u_credits = get_user_credits($page['user_id']);
                ?>
                <div class="bg-slate-900/40 border border-slate-850 rounded-2xl p-4 text-xs space-y-2 mt-2" id="edit-validity-cost-card">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Your Current Credits:</span>
                        <span id="edit-user-current-credits" class="font-bold text-white"><?= $u_credits ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Additional Credits Required:</span>
                        <span id="edit-credits-required" class="font-bold text-emerald-400">0 Credits</span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Category Specific Customizations -->
                <?php if (!empty($slide_config)): ?>
                    <div class="border-t border-slate-800 pt-4 mt-4">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Slide Content Customizations</h4>
                        <div class="space-y-4">
                            <?php foreach ($slide_config as $slide): ?>
                                <?php if ($slide['type'] === 'text_story' && isset($slide['key'])): ?>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1"><?= h($slide['title']) ?></label>
                                        <textarea id="edit_slide_<?= h($slide['key']) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-sm resize-none" rows="3"><?= h(get_slide_value($slide_data, $slide['key'], $slide['default'] ?? '')) ?></textarea>
                                    </div>
                                <?php elseif ($slide['type'] === 'cards' && isset($slide['key'])): ?>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-400 mb-1"><?= h($slide['title']) ?> Cards</label>
                                        <?php 
                                        $defaults = $slide['defaults'] ?? [];
                                        $current_cards = get_slide_cards($slide_data, $slide['key'], $defaults);
                                        for ($i = 0; $i < count($defaults); $i++):
                                            $val = $current_cards[$i] ?? '';
                                        ?>
                                            <input type="text" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-sm mb-2 edit_slide_card_<?= h($slide['key']) ?>" value="<?= h($val) ?>" placeholder="Card <?= $i + 1 ?>">
                                        <?php endfor; ?>
                                    </div>
                                <?php elseif (isset($slide['is_optional']) && $slide['is_optional']): ?>
                                    <!-- Optional Premium Slide Customizer -->
                                    <details class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden group mb-4">
                                        <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-sm text-slate-200 hover:bg-slate-800/40 select-none">
                                            <div class="flex items-center space-x-2">
                                                <span>✨</span>
                                                <span><?= h($slide['title']) ?></span>
                                            </div>
                                            <div class="flex items-center space-x-3" onclick="event.stopPropagation();">
                                                <?php
                                                $is_enabled = get_slide_value($slide_data, $slide['key'] . '_enabled') === '1';
                                                ?>
                                                <input type="checkbox" id="edit_slide_<?= h($slide['key']) ?>_enabled" value="1" <?= $is_enabled ? 'checked' : '' ?> class="w-4 h-4 rounded text-pink-500 bg-slate-950 border-slate-850 focus:ring-pink-500" onchange="updateSlideStatusBadge(this, '<?= h($slide['key']) ?>')">
                                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide <?= $is_enabled ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700/50' ?>" id="badge_<?= h($slide['key']) ?>">
                                                    <?= $is_enabled ? 'Enabled' : 'Disabled' ?>
                                                </span>
                                                <span class="transition-transform group-open:rotate-180 text-xs text-slate-500">â–¼</span>
                                            </div>
                                        </summary>
                                        <div class="p-4 border-t border-slate-800/50 space-y-4 text-slate-300">
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Custom Heading</label>
                                                    <input type="text" id="edit_slide_<?= h($slide['key']) ?>_title" value="<?= h(get_slide_value($slide_data, $slide['key'] . '_title', $slide['title'] ?? '')) ?>" class="w-full bg-slate-950 border border-slate-850 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-xs">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Custom Subtitle</label>
                                                    <input type="text" id="edit_slide_<?= h($slide['key']) ?>_subtitle" value="<?= h(get_slide_value($slide_data, $slide['key'] . '_subtitle', $slide['subtitle'] ?? '')) ?>" class="w-full bg-slate-950 border border-slate-850 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-xs">
                                                </div>
                                            </div>

                                            <?php if ($slide['type'] === 'premium_our_chats'): ?>
                                                <!-- Custom Chats Editor -->
                                                <div class="space-y-3 pt-2">
                                                    <p class="text-[10px] text-pink-400 font-bold uppercase tracking-wider">Customize Chat Moments (Up to 5)</p>
                                                    <?php 
                                                    for ($m_idx = 1; $m_idx <= 5; $m_idx++): 
                                                        $moment_label = get_slide_value($slide_data, $slide['key'] . "_moment_{$m_idx}_label", $m_idx === 1 ? 'Attachment 😍' : '');
                                                        $moment_title = get_slide_value($slide_data, $slide['key'] . "_moment_{$m_idx}_title", $m_idx === 1 ? 'Attachment Moment 📦' : '');
                                                        $moment_dialogue = get_slide_value($slide_data, $slide['key'] . "_moment_{$m_idx}_dialogue", $m_idx === 1 ? "Her: You save everything about me 😂\nYou: Everything? 😜\nHer: What if someday I accidentally lose you?\nHer: My soul would leave my body 🥺\nYou: Drama queen 😂" : '');
                                                    ?>
                                                        <div class="border border-slate-800 bg-slate-950/40 p-3 rounded-xl space-y-2">
                                                            <span class="text-[10px] font-bold text-pink-500">Moment <?= $m_idx ?></span>
                                                            <div class="grid grid-cols-2 gap-2">
                                                                <div>
                                                                    <label class="block text-[9px] text-slate-400">Tab Label (e.g. Attachment 😍)</label>
                                                                    <input type="text" id="edit_slide_<?= h($slide['key']) ?>_moment_<?= $m_idx ?>_label" value="<?= h($moment_label) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-white outline-none focus:border-pink-500 text-xs">
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[9px] text-slate-400">Moment Title (e.g. Attachment Moment 📦)</label>
                                                                    <input type="text" id="edit_slide_<?= h($slide['key']) ?>_moment_<?= $m_idx ?>_title" value="<?= h($moment_title) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-white outline-none focus:border-pink-500 text-xs">
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[9px] text-slate-400">Dialogue (Use "Her:" or "You:" on new lines)</label>
                                                                <textarea id="edit_slide_<?= h($slide['key']) ?>_moment_<?= $m_idx ?>_dialogue" rows="3" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-white outline-none focus:border-pink-500 text-xs resize-none placeholder-slate-600" placeholder="Her: message&#10;You: message"><?= h($moment_dialogue) ?></textarea>
                                                            </div>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            <?php else: ?>
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Custom Message / Story</label>
                                                    <textarea id="edit_slide_<?= h($slide['key']) ?>_message" rows="3" class="w-full bg-slate-950 border border-slate-850 rounded-xl px-3 py-2 text-white outline-none focus:border-pink-500 text-xs resize-none"><?= h(get_slide_value($slide_data, $slide['key'] . '_message', '')) ?></textarea>
                                                </div>
                                            <?php endif; ?>

                                            <div>
                                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Custom Background Gradient (Optional)</label>
                                                <select id="edit_slide_<?= h($slide['key']) ?>_bg" class="w-full bg-slate-950 border border-slate-850 text-white rounded-xl px-3 py-2 text-xs outline-none focus:border-pink-500">
                                                    <option value="">-- Use Category default theme --</option>
                                                    <option value="bg-gradient-to-b from-rose-950 via-rose-900 to-red-950" <?= get_slide_value($slide_data, $slide['key'] . '_bg') === 'bg-gradient-to-b from-rose-950 via-rose-900 to-red-950' ? 'selected' : '' ?>>Rose Midnight Gradient</option>
                                                    <option value="bg-gradient-to-b from-slate-950 via-gray-900 to-zinc-950" <?= get_slide_value($slide_data, $slide['key'] . '_bg') === 'bg-gradient-to-b from-slate-950 via-gray-900 to-zinc-950' ? 'selected' : '' ?>>Dark Onyx Gradient</option>
                                                    <option value="bg-gradient-to-b from-violet-950 via-purple-950 to-orange-950" <?= get_slide_value($slide_data, $slide['key'] . '_bg') === 'bg-gradient-to-b from-violet-950 via-purple-950 to-orange-950' ? 'selected' : '' ?>>Sunset Amethyst Gradient</option>
                                                    <option value="bg-gradient-to-b from-emerald-950 via-green-950 to-rose-950" <?= get_slide_value($slide_data, $slide['key'] . '_bg') === 'bg-gradient-to-b from-emerald-950 via-green-950 to-rose-950' ? 'selected' : '' ?>>Emerald Rosewood Gradient</option>
                                                    <option value="bg-gradient-to-b from-indigo-950 via-purple-950 to-violet-950" <?= get_slide_value($slide_data, $slide['key'] . '_bg') === 'bg-gradient-to-b from-indigo-950 via-purple-950 to-violet-950' ? 'selected' : '' ?>>Cosmic Galaxy Gradient</option>
                                                </select>
                                            </div>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <button onclick="saveDetails()" class="w-full py-3 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl hover:opacity-95 transition text-sm">
                    Save Details & Reload
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 2: CHANGE MUSIC -->
    <div id="musicModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
        <div class="w-full max-w-md glass-modal rounded-3xl p-6 relative">
            <button onclick="closeModal('musicModal')" class="absolute top-4 right-4 text-slate-400 hover:text-white font-bold text-xl">&times;</button>
            <h3 class="text-xl font-bold mb-4 font-heading text-white">Change Music Track</h3>
            
            <div class="space-y-2 max-h-64 overflow-y-auto pr-2 mb-4">
                <?php foreach ($music_options as $track): ?>
                    <label class="block cursor-pointer">
                        <input type="radio" name="preview_music" value="<?= h($track['file_path']) ?>" class="sr-only peer" <?= $page['music_url'] === $track['file_path'] ? 'checked' : '' ?>>
                        <div class="flex items-center justify-between p-3 rounded-xl border border-slate-800 bg-slate-900/30 peer-checked:border-pink-500/50 peer-checked:bg-pink-500/5 hover:bg-slate-900/50 transition">
                            <div class="flex items-center space-x-3">
                                <button type="button" class="w-7 h-7 rounded-full bg-slate-800 hover:bg-pink-500 text-white flex items-center justify-center text-xs preview-play-btn" data-url="<?= h($track['file_path']) ?>">
                                    â–¶
                                </button>
                                <span class="text-xs font-semibold text-white"><?= h($track['title']) ?></span>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <button onclick="saveMusic()" class="w-full py-3 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl hover:opacity-95 transition text-sm">
                Save Music Choice
            </button>
        </div>
    </div>

    <!-- MODAL 3: MANAGE PHOTOS -->
    <div id="photosModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
        <div class="w-full max-w-md glass-modal rounded-3xl p-6 relative">
            <button onclick="closeModal('photosModal')" class="absolute top-4 right-4 text-slate-400 hover:text-white font-bold text-xl">&times;</button>
            <h3 class="text-xl font-bold mb-4 font-heading text-white font-heading">Manage Photos</h3>
            
            <!-- Current Photos List -->
            <div class="grid grid-cols-3 gap-3 mb-6" id="editor-photos-list">
                <?php foreach ($images as $img): ?>
                    <div class="relative aspect-square bg-slate-900 rounded-xl overflow-hidden border border-slate-800 photo-item-card cursor-grab active:cursor-grabbing" draggable="true" data-id="<?= $img['id'] ?>">
                        <img src="<?= h($img['image_path']) ?>" class="w-full h-full object-cover pointer-events-none">
                        <button onclick="deletePhoto(<?= $img['id'] ?>)" class="absolute -top-1 -right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] font-bold shadow-md z-10">
                            &times;
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Upload New Photo form -->
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-400 mb-2">Upload New Photo (Max 10 total)</label>
                <input type="file" id="ajax_photo_input" accept="image/*" class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-white hover:file:bg-slate-700 cursor-pointer">
            </div>

            <button onclick="uploadAjaxPhoto()" id="upload-photo-btn" class="w-full py-3 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl hover:opacity-95 transition text-sm">
                Upload Photo &rarr;
            </button>
        </div>
    </div>

    <!-- MODAL 4: VOICE & VIDEO MESSAGES -->
    <div id="mediaModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
        <div class="w-full max-w-md glass-modal rounded-3xl p-6 relative text-slate-100">
            <button onclick="closeModal('mediaModal')" class="absolute top-4 right-4 text-slate-400 hover:text-white font-bold text-xl">&times;</button>
            <h3 class="text-xl font-bold mb-4 font-heading text-white">Voice & Video Messages</h3>
            
            <!-- Voice Note Card / Upload -->
            <div class="mb-6 border-b border-slate-800 pb-5">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">🎙️ Voice Message Note</h4>
                <?php if (!empty($page['voice_url'])): ?>
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-3 flex items-center justify-between mb-3 text-xs">
                        <div class="truncate pr-4 flex items-center space-x-1.5">
                            <span class="text-base text-pink-500">🔊</span>
                            <span class="text-slate-200 font-mono truncate"><?= h(basename($page['voice_url'])) ?></span>
                        </div>
                        <button onclick="deleteVoiceNote()" class="text-[10px] font-bold bg-red-500/10 hover:bg-red-500/20 text-red-400 px-2.5 py-1.5 rounded-xl transition">
                            Delete
                        </button>
                    </div>
                <?php endif; ?>
                <div class="flex items-center gap-2">
                    <input type="file" id="media_voice_input" accept=".mp3,.aac,.m4a,.wav,.ogg,audio/mpeg,audio/aac,audio/mp4,audio/wav,audio/ogg" class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-pink-600 file:text-white hover:file:bg-pink-500 cursor-pointer" onchange="const btn = document.getElementById('clear-edit-voice-btn'); if(btn) btn.classList.remove('hidden')">
                    <button type="button" onclick="clearEditSelectedVoice()" id="clear-edit-voice-btn" class="hidden px-2.5 py-2 bg-slate-850 hover:bg-slate-850 text-slate-300 hover:text-white rounded-xl text-xs font-bold transition flex-shrink-0">
                        Deselect
                    </button>
                </div>
                <input type="hidden" id="voice_duration_input" value="0">
                <p class="text-[10px] text-slate-600 mt-1.5">MP3, AAC, M4A, WAV, OGG — up to 10MB</p>
            </div>

            <!-- Video Message Card / Upload -->
            <div class="mb-6 pb-2">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">📹 Video Message</h4>
                <?php if (!empty($page['video_url'])): ?>
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-3 flex items-center justify-between mb-3 text-xs">
                        <div class="truncate pr-4 flex items-center space-x-1.5">
                            <span class="text-base text-purple-500">🎥</span>
                            <span class="text-slate-200 font-mono truncate"><?= h(basename($page['video_url'])) ?></span>
                        </div>
                        <button onclick="deleteVideoMessage()" class="text-[10px] font-bold bg-red-500/10 hover:bg-red-500/20 text-red-400 px-2.5 py-1.5 rounded-xl transition">
                            Delete
                        </button>
                    </div>
                <?php endif; ?>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 mb-1.5 uppercase">Upload / Replace Video file</label>
                    <div class="flex items-center gap-2">
                        <input type="file" id="media_video_input" accept="video/*" class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-white hover:file:bg-slate-700 cursor-pointer" onchange="const btn = document.getElementById('clear-edit-video-btn'); if(btn) btn.classList.remove('hidden')">
                        <button type="button" onclick="clearEditSelectedVideo()" id="clear-edit-video-btn" class="hidden px-2.5 py-2 bg-slate-850 hover:bg-slate-850 text-slate-300 hover:text-white rounded-xl text-xs font-bold transition flex-shrink-0">
                            Deselect
                        </button>
                    </div>
                    <p class="text-[9px] text-slate-600 mt-1">MP4, WEBM, MOV up to 20MB</p>
                </div>
            </div>

            <div id="media-upload-error" class="hidden text-xs text-red-400 bg-red-500/10 border border-red-500/20 p-3 rounded-2xl mb-4"></div>

            <button onclick="saveMediaUploads()" id="save-media-btn" class="w-full py-3 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl hover:opacity-95 transition text-sm">
                Save & Update Media
            </button>
        </div>
    </div>

    <!-- Hidden audio element for previews -->
    <audio id="modal-audio" class="hidden"></audio>

    <script>
        const baseUrl = '<?= $base_url ?>';
        const pageId = <?= $page['id'] ?>;
        
        const POST_MAX_SIZE = <?= parse_ini_bytes(ini_get('post_max_size')) ?>;
        const MAX_IMAGE_SIZE = 10 * 1024 * 1024;
        const MAX_VIDEO_SIZE = 50 * 1024 * 1024;
        const MAX_VOICE_SIZE = 10 * 1024 * 1024;
        const MAX_MUSIC_SIZE = 10 * 1024 * 1024;

        function clearEditSelectedVoice() {
            const input = document.getElementById('media_voice_input');
            if (input) input.value = '';
            const clearBtn = document.getElementById('clear-edit-voice-btn');
            if (clearBtn) clearBtn.classList.add('hidden');
        }

        function clearEditSelectedVideo() {
            const input = document.getElementById('media_video_input');
            if (input) input.value = '';
            const clearBtn = document.getElementById('clear-edit-video-btn');
            if (clearBtn) clearBtn.classList.add('hidden');
        }

        // Delete voice note
        function deleteVoiceNote() {
            if (!confirm('Are you sure you want to delete this voice message?')) return;
            fetch('api.php?action=delete_voice', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ page_id: pageId })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.message || 'Failed to delete voice note.');
                }
            });
        }


        // Delete video message
        function deleteVideoMessage() {
            if (!confirm('Are you sure you want to delete this video message?')) return;
            fetch('api.php?action=delete_video', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ page_id: pageId })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.message || 'Failed to delete video message.');
                }
            });
        }

        // Save Voice & Video
        function saveMediaUploads() {
            const voiceInput = document.getElementById('media_voice_input');
            const videoInput = document.getElementById('media_video_input');
            const btn = document.getElementById('save-media-btn');
            const errDiv = document.getElementById('media-upload-error');

            if (!voiceInput.files.length && !videoInput.files.length) {
                alert('Please select a voice or video file to upload.');
                return;
            }

            let totalSize = 0;
            const errors = [];

            if (voiceInput && voiceInput.files.length > 0) {
                const file = voiceInput.files[0];
                totalSize += file.size;
                if (file.size > MAX_VOICE_SIZE) {
                    errors.push(`Voice note "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max size is 10MB.`);
                }
            }

            if (videoInput && videoInput.files.length > 0) {
                const file = videoInput.files[0];
                totalSize += file.size;
                if (file.size > MAX_VIDEO_SIZE) {
                    errors.push(`Video message "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max size is 50MB.`);
                }
            }

            if (totalSize > POST_MAX_SIZE) {
                errors.push(`Total size of files is ${(totalSize / 1024 / 1024).toFixed(2)}MB, which exceeds the server limit of ${(POST_MAX_SIZE / 1024 / 1024).toFixed(2)}MB.`);
            }

            if (errors.length > 0) {
                alert("Upload Size Validation Error:\n\n" + errors.join("\n"));
                return;
            }

            const formData = new FormData();
            formData.append('page_id', pageId);
            if (voiceInput.files.length) {
                formData.append('voice', voiceInput.files[0]);
                formData.append('voice_duration', document.getElementById('voice_duration_input').value || 0);
            }
            if (videoInput.files.length) {
                formData.append('video', videoInput.files[0]);
            }

            btn.disabled = true;
            btn.textContent = 'Uploading files...';
            errDiv.classList.add('hidden');
            errDiv.textContent = '';

            fetch('api.php?action=upload_voice_video', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                btn.disabled = false;
                btn.textContent = 'Save & Update Media';
                if (res.success) {
                    window.location.reload();
                } else {
                    errDiv.textContent = res.error || 'Failed to upload media files.';
                    errDiv.classList.remove('hidden');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.textContent = 'Save & Update Media';
                errDiv.textContent = 'A connection error occurred during file upload.';
                errDiv.classList.remove('hidden');
                console.error(err);
            });
        }



        
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            // Pause any playing music preview
            const modalAudio = document.getElementById('modal-audio');
            modalAudio.pause();
            document.querySelectorAll('.preview-play-btn').forEach(btn => btn.textContent = 'â–¶');
        }

        function saveDetails() {
            const slideData = {};
            slideData['letter_enabled'] = document.getElementById('edit_letter_enabled').checked ? 1 : 0;
            slideData['gallery_enabled'] = document.getElementById('edit_gallery_enabled').checked ? 1 : 0;
            slideData['voice_message_enabled'] = document.getElementById('edit_voice_message_enabled').checked ? 1 : 0;
            slideData['video_message_enabled'] = document.getElementById('edit_video_message_enabled').checked ? 1 : 0;
            slideData['special_date_enabled'] = document.getElementById('edit_special_date_enabled').checked ? 1 : 0;
            <?php foreach ($slide_config as $slide): ?>
                <?php if ($slide['type'] === 'text_story' && isset($slide['key'])): ?>
                    const slideEl_<?= $slide['key'] ?> = document.getElementById('edit_slide_<?= $slide['key'] ?>');
                    if (slideEl_<?= $slide['key'] ?>) {
                        slideData['<?= $slide['key'] ?>'] = slideEl_<?= $slide['key'] ?>.value.trim();
                    }
                <?php elseif ($slide['type'] === 'cards' && isset($slide['key'])): ?>
                    const cardInputs_<?= $slide['key'] ?> = document.querySelectorAll('.edit_slide_card_<?= $slide['key'] ?>');
                    const cardsArray_<?= $slide['key'] ?> = [];
                    cardInputs_<?= $slide['key'] ?>.forEach(input => {
                        if (input.value.trim() !== '') {
                            cardsArray_<?= $slide['key'] ?>.push(input.value.trim());
                        }
                    });
                    slideData['<?= $slide['key'] ?>'] = cardsArray_<?= $slide['key'] ?>;
                <?php elseif (isset($slide['is_optional']) && $slide['is_optional']): ?>
                    const enabledEl_<?= $slide['key'] ?> = document.getElementById('edit_slide_<?= $slide['key'] ?>_enabled');
                    const titleEl_<?= $slide['key'] ?> = document.getElementById('edit_slide_<?= $slide['key'] ?>_title');
                    const subtitleEl_<?= $slide['key'] ?> = document.getElementById('edit_slide_<?= $slide['key'] ?>_subtitle');
                    const messageEl_<?= $slide['key'] ?> = document.getElementById('edit_slide_<?= $slide['key'] ?>_message');
                    const bgEl_<?= $slide['key'] ?> = document.getElementById('edit_slide_<?= $slide['key'] ?>_bg');

                    slideData['<?= $slide['key'] ?>_enabled'] = (enabledEl_<?= $slide['key'] ?> && enabledEl_<?= $slide['key'] ?>.checked) ? '1' : '0';
                    if (titleEl_<?= $slide['key'] ?>) slideData['<?= $slide['key'] ?>_title'] = titleEl_<?= $slide['key'] ?>.value.trim();
                    if (subtitleEl_<?= $slide['key'] ?>) slideData['<?= $slide['key'] ?>_subtitle'] = subtitleEl_<?= $slide['key'] ?>.value.trim();
                    if (messageEl_<?= $slide['key'] ?>) slideData['<?= $slide['key'] ?>_message'] = messageEl_<?= $slide['key'] ?>.value.trim();
                    if (bgEl_<?= $slide['key'] ?>) slideData['<?= $slide['key'] ?>_bg'] = bgEl_<?= $slide['key'] ?>.value;

                    <?php if ($slide['type'] === 'premium_our_chats'): ?>
                        // Serialize chat moments
                        for (let m = 1; m <= 5; m++) {
                            const lbl = document.getElementById('edit_slide_<?= h($slide['key']) ?>_moment_' + m + '_label');
                            const ttl = document.getElementById('edit_slide_<?= h($slide['key']) ?>_moment_' + m + '_title');
                            const dlg = document.getElementById('edit_slide_<?= h($slide['key']) ?>_moment_' + m + '_dialogue');
                            if (lbl) slideData['<?= $slide['key'] ?>_moment_' + m + '_label'] = lbl.value.trim();
                            if (ttl) slideData['<?= $slide['key'] ?>_moment_' + m + '_title'] = ttl.value.trim();
                            if (dlg) slideData['<?= $slide['key'] ?>_moment_' + m + '_dialogue'] = dlg.value.trim();
                        }
                    <?php endif; ?>

                    // Serialize slide photos with custom captions/dates/memories
                    const photoRows_<?= $slide['key'] ?> = document.querySelectorAll('#slide_photos_list_<?= $slide['key'] ?> .photo-config-row');
                    if (photoRows_<?= $slide['key'] ?>.length > 0) {
                        const photosArray_<?= $slide['key'] ?> = [];
                        photoRows_<?= $slide['key'] ?>.forEach(row => {
                            const original = row.dataset.original;
                            const medium = row.dataset.medium;
                            const thumb = row.dataset.thumb;
                            const captionEl = row.querySelector('.photo-caption');
                            const dateEl = row.querySelector('.photo-date');
                            const memoryEl = row.querySelector('.photo-memory');
                            
                            photosArray_<?= $slide['key'] ?>.push({
                                original: original,
                                medium: medium,
                                thumb: thumb,
                                caption: captionEl ? captionEl.value.trim() : '',
                                date: dateEl ? dateEl.value.trim() : '',
                                memory: memoryEl ? memoryEl.value.trim() : ''
                            });
                        });
                        slideData['<?= $slide['key'] ?>_images'] = photosArray_<?= $slide['key'] ?>;
                    }
                <?php endif; ?>
            <?php endforeach; ?>

            if (editUserIsLoggedIn) {
                const durationSelect = document.getElementById('edit_expiry_duration');
                const customDaysInput = document.getElementById('edit_custom_days');
                let days = 10;
                if (durationSelect.value === 'custom') {
                    days = parseInt(customDaysInput.value) || 10;
                } else {
                    days = parseInt(durationSelect.value);
                }
                
                let oldCost = 0;
                if (editFreePagesLeft <= 0) {
                    oldCost = Math.ceil(oldDays / 10);
                } else {
                    oldCost = (oldDays <= 10) ? 0 : Math.ceil((oldDays - 10) / 10);
                }
                
                let newCost = 0;
                if (editFreePagesLeft <= 0) {
                    newCost = Math.ceil(days / 10);
                } else {
                    newCost = (days <= 10) ? 0 : Math.ceil((days - 10) / 10);
                }
                
                const creditDiff = Math.max(0, newCost - oldCost);
                if (editUserCredits < creditDiff) {
                    alert(`Insufficient credits! Extending to ${days} days requires ${creditDiff} additional credits, but you only have ${editUserCredits} credits.`);
                    return;
                }
            }

            const data = {
                page_id: pageId,
                sender_name: document.getElementById('edit_sender').value,
                receiver_name: document.getElementById('edit_receiver').value,
                title: document.getElementById('edit_title').value,
                letter_text: document.getElementById('edit_letter').value,
                proposal_question: document.getElementById('edit_question').value,
                accent_color: document.getElementById('edit_accent').value,
                font_style: document.getElementById('edit_font').value,
                photo_fit_mode: document.getElementById('edit_photo_fit').value,
                nickname: document.getElementById('edit_nickname').value,
                relationship_date: document.getElementById('edit_date').value,
                theme: document.getElementById('edit_theme').value,
                slide_data: slideData,
                expiry_duration: document.getElementById('edit_expiry_duration').value,
                custom_days: document.getElementById('edit_custom_days').value,
                interactive_ending: document.getElementById('edit_interactive_ending').checked ? 1 : 0,
                interactive_question: document.getElementById('edit_question').value,
                interactive_yes_text: document.getElementById('edit_interactive_yes_text').value,
                interactive_no_text: document.getElementById('edit_interactive_no_text').value,
                interactive_funny_no: document.getElementById('edit_interactive_funny_no').checked ? 1 : 0,
                interactive_ask_name: document.getElementById('edit_interactive_ask_name').checked ? 1 : 0
            };

            fetch('api.php?action=edit_details', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.message || res.error || 'Unknown error occurred');
                }
            });
        }

        // Music save action
        function saveMusic() {
            const selectedRadio = document.querySelector('input[name="preview_music"]:checked');
            if (!selectedRadio) {
                alert('Please select a track.');
                return;
            }
            
            fetch('api.php?action=change_music', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    page_id: pageId,
                    music_url: selectedRadio.value
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    alert('Music choice updated!');
                    closeModal('musicModal');
                } else {
                    alert(res.message || res.error || 'Unknown error occurred');
                }
            });
        }

        // AJAX Photo Upload
        function uploadAjaxPhoto() {
            const fileInput = document.getElementById('ajax_photo_input');
            if (fileInput.files.length === 0) {
                alert('Please choose a file first.');
                return;
            }
            
            const file = fileInput.files[0];
            if (file.size > MAX_IMAGE_SIZE) {
                alert(`Photo "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max size is 10MB.`);
                return;
            }
            if (file.size > POST_MAX_SIZE) {
                alert(`Photo "${file.name}" size is ${(file.size / 1024 / 1024).toFixed(2)}MB, which exceeds the server limit of ${(POST_MAX_SIZE / 1024 / 1024).toFixed(2)}MB.`);
                return;
            }

            const formData = new FormData();
            formData.append('page_id', pageId);
            formData.append('photo', file);

            const btn = document.getElementById('upload-photo-btn');
            btn.textContent = 'Uploading...';
            btn.disabled = true;

            fetch('api.php?action=upload_photo', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                btn.textContent = 'Upload Photo →';
                btn.disabled = false;
                
                if (res.success) {
                    alert('Photo uploaded!');
                    // Append photo in the modal view list
                    const list = document.getElementById('editor-photos-list');
                    const div = document.createElement('div');
                    div.className = 'relative aspect-square bg-slate-900 rounded-xl overflow-hidden border border-slate-800 photo-item-card cursor-grab active:cursor-grabbing';
                    div.setAttribute('draggable', 'true');
                    div.setAttribute('data-id', res.photo.id);
                    div.innerHTML = `
                        <img src="${res.photo.path}" class="w-full h-full object-cover pointer-events-none">
                        <button onclick="deletePhoto(${res.photo.id})" class="absolute -top-1 -right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] font-bold shadow-md z-10">&times;</button>
                    `;
                    list.appendChild(div);
                    
                    fileInput.value = '';
                    
                    // Simple prompt to refresh preview
                    window.location.reload();
                } else {
                    alert(res.message || res.error || 'Unknown error occurred');
                }
            });
        }

        // AJAX Photo Delete
        function deletePhoto(photoId) {
            if (!confirm('Are you sure you want to delete this photo?')) return;
            
            fetch('api.php?action=delete_photo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    page_id: pageId,
                    photo_id: photoId
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const card = document.querySelector(`.photo-item-card[data-id="${photoId}"]`);
                    if (card) card.remove();
                    
                    alert('Photo deleted!');
                    window.location.reload();
                } else {
                    alert(res.message || res.error || 'Unknown error occurred');
                }
            });
        }

        // Preview play audio in modals
        const modalAudio = document.getElementById('modal-audio');
        let playingUrl = null;

        document.querySelectorAll('.preview-play-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const url = btn.getAttribute('data-url');
                
                if (playingUrl === url) {
                    modalAudio.pause();
                    btn.textContent = 'â–¶';
                    playingUrl = null;
                } else {
                    document.querySelectorAll('.preview-play-btn').forEach(b => b.textContent = 'â–¶');
                    modalAudio.src = url;
                    modalAudio.play().then(() => {
                        btn.textContent = 'â¸';
                        playingUrl = url;
                    }).catch(err => {
                        btn.textContent = 'â¸';
                        playingUrl = url;
                        console.log("Audio simulation playing: " + url);
                    });
                }
            });
        });

        // HTML5 drag and drop photo reordering
        function setupPhotoDragAndDrop() {
            const list = document.getElementById('editor-photos-list');
            if (!list) return;

            let draggedCard = null;

            list.addEventListener('dragstart', (e) => {
                const card = e.target.closest('.photo-item-card');
                if (card) {
                    draggedCard = card;
                    card.classList.add('opacity-50');
                }
            });

            list.addEventListener('dragend', (e) => {
                const card = e.target.closest('.photo-item-card');
                if (card) {
                    card.classList.remove('opacity-50');
                }
                draggedCard = null;
            });

            list.addEventListener('dragover', (e) => {
                e.preventDefault();
            });

            list.addEventListener('drop', (e) => {
                e.preventDefault();
                const targetCard = e.target.closest('.photo-item-card');
                if (targetCard && draggedCard && draggedCard !== targetCard) {
                    const children = Array.from(list.children);
                    const draggedIndex = children.indexOf(draggedCard);
                    const targetIndex = children.indexOf(targetCard);

                    if (draggedIndex < targetIndex) {
                        list.insertBefore(draggedCard, targetCard.nextSibling);
                    } else {
                        list.insertBefore(draggedCard, targetCard);
                    }
                    
                    savePhotoOrder();
                }
            });
        }

        function savePhotoOrder() {
            const list = document.getElementById('editor-photos-list');
            const photoIds = Array.from(list.querySelectorAll('.photo-item-card')).map(card => parseInt(card.getAttribute('data-id')));
            
            fetch('api.php?action=reorder_photos', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    page_id: pageId,
                    photo_ids: photoIds
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    // Reload live preview iframe to show new ordering
                    const iframe = document.getElementById('live-preview-iframe');
                    if (iframe) {
                        iframe.src = iframe.src;
                    }
                } else {
                    alert('Failed to save order: ' + res.message);
                }
            });
        }

        function showPublishingOverlay() {
            const overlay = document.getElementById('publishing-overlay');
            if (overlay) {
                overlay.classList.remove('pointer-events-none');
                overlay.classList.add('opacity-100');
            }
            
            const statuses = [
                'Finalizing page configurations... 📝',
                'Generating secure shareable URL... 🔗',
                'Publishing details to search indices... 🚀',
                'Almost ready! Preparing redirects... ✨'
            ];
            
            let currentStatusIdx = 0;
            const statusText = document.getElementById('publishing-status');
            const progressBar = document.getElementById('publishing-progress');
            
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 12 + 3;
                if (progress >= 95) {
                    progress = 95;
                    clearInterval(progressInterval);
                }
                if (progressBar) progressBar.style.width = `${progress}%`;
            }, 150);
            
            const statusInterval = setInterval(() => {
                currentStatusIdx = (currentStatusIdx + 1) % statuses.length;
                if (statusText) statusText.textContent = statuses[currentStatusIdx];
            }, 800);
            
            return true;
        }

        const editUserIsLoggedIn = <?= !empty($page['user_id']) ? 'true' : 'false' ?>;
        const editUserCredits = parseInt("<?= !empty($page['user_id']) ? get_user_credits($page['user_id']) : 0 ?>") || 0;
        const editFreePagesLeft = parseInt("<?= !empty($page['user_id']) ? max(0, (int)get_setting('free_pages_per_user', 1) - (int)get_user_profile($page['user_id'])['free_pages_used']) : 0 ?>") || 0;
        const oldDays = parseInt("<?= $curr_days ?>") || 10;

        function updateEditValidityCost() {
            const durationSelect = document.getElementById('edit_expiry_duration');
            const customDaysContainer = document.getElementById('edit_custom_days_container');
            const customDaysInput = document.getElementById('edit_custom_days');
            
            let days = 10;
            if (durationSelect.value === 'custom') {
                customDaysContainer.classList.remove('hidden');
                days = parseInt(customDaysInput.value) || 10;
                if (days < 10) {
                    days = 10;
                    customDaysInput.value = 10;
                }
            } else {
                customDaysContainer.classList.add('hidden');
                days = parseInt(durationSelect.value);
            }
            
            if (editUserIsLoggedIn) {
                // Calculate old cost
                let oldCost = 0;
                if (editFreePagesLeft <= 0) {
                    oldCost = Math.ceil(oldDays / 10);
                } else {
                    oldCost = (oldDays <= 10) ? 0 : Math.ceil((oldDays - 10) / 10);
                }
                
                // Calculate new cost
                let newCost = 0;
                if (editFreePagesLeft <= 0) {
                    newCost = Math.ceil(days / 10);
                } else {
                    newCost = (days <= 10) ? 0 : Math.ceil((days - 10) / 10);
                }
                
                const creditDiff = Math.max(0, newCost - oldCost);
                const reqEl = document.getElementById('edit-credits-required');
                if (reqEl) {
                    reqEl.textContent = creditDiff + (creditDiff === 1 ? ' Credit' : ' Credits');
                }
            }
        }

        // Initialize edit validity cost on open
        document.addEventListener('DOMContentLoaded', () => {
            updateEditValidityCost();
            
            const editInteractiveEnding = document.getElementById('edit_interactive_ending');
            if (editInteractiveEnding) {
                editInteractiveEnding.addEventListener('change', function() {
                    const settingsPanel = document.getElementById('edit-interactive-ending-settings');
                    if (settingsPanel) {
                        settingsPanel.classList.toggle('hidden', !this.checked);
                    }
                });
            }
        });

        function initiatePublishPayment() {
            const publishBtn = document.getElementById('publish-btn');
            publishBtn.disabled = true;
            publishBtn.textContent = 'Preparing payment...';
            
            fetch('api.php?action=create_publish_order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'page_id=' + pageId
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert(data.error || 'Failed to create order.');
                    publishBtn.disabled = false;
                    publishBtn.textContent = 'Publish';
                    return;
                }
                
                const options = {
                    key: data.key,
                    amount: data.amount,
                    currency: 'INR',
                    name: 'SoulSync',
                    description: 'Publish Surprise Card - ' + data.days + ' Days',
                    order_id: data.order_id,
                    handler: function(response) {
                        showPublishingOverlay();
                        // Verify payment and publish
                        fetch('api.php?action=verify_publish_payment', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                page_id: pageId,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature,
                            })
                        })
                        .then(r => r.json())
                        .then(vdata => {
                            if (vdata.success) {
                                window.location.href = 'publish-success.php?slug=' + vdata.slug;
                            } else {
                                alert('Payment verification failed. Contact support.');
                                window.location.reload();
                            }
                        });
                    },
                    prefill: {
                        name: 'Guest User',
                        email: 'guest@soulsyncc.site',
                    },
                    theme: {
                        color: '#ec4899'
                    },
                    modal: {
                        ondismiss: function() {
                            publishBtn.disabled = false;
                            publishBtn.textContent = 'Publish';
                        }
                    }
                };
                
                const rzp = new Razorpay(options);
                rzp.open();
            })
            .catch(err => {
                alert('Network error. Please try again.');
                publishBtn.disabled = false;
                publishBtn.textContent = 'Publish';
            });
        }

        window.addEventListener('DOMContentLoaded', setupPhotoDragAndDrop);

        // Slide Status Badge Update Helper
        function updateSlideStatusBadge(checkbox, slideKey) {
            const badge = document.getElementById('badge_' + slideKey);
            if (badge) {
                if (checkbox.checked) {
                    badge.textContent = 'Enabled';
                    badge.className = 'text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                } else {
                    badge.textContent = 'Disabled';
                    badge.className = 'text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide bg-slate-800 text-slate-400 border border-slate-700/50';
                }
            }
        }

        // Slide Specific AJAX Image Uploads
        async function uploadSlidePhotos(input, slideKey) {
            if (!input.files.length) return;

            let totalSize = 0;
            const errors = [];
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                totalSize += file.size;
                if (file.size > MAX_IMAGE_SIZE) {
                    errors.push(`Photo "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max size is 10MB.`);
                }
            }

            if (totalSize > POST_MAX_SIZE) {
                errors.push(`Total size of photos is ${(totalSize / 1024 / 1024).toFixed(2)}MB, which exceeds the server limit of ${(POST_MAX_SIZE / 1024 / 1024).toFixed(2)}MB.`);
            }

            if (errors.length > 0) {
                alert("Upload Size Validation Error:\n\n" + errors.join("\n"));
                input.value = '';
                return;
            }

            const progressContainer = document.getElementById('upload_progress_container_' + slideKey);
            const progressBar = document.getElementById('upload_progress_bar_' + slideKey);
            if (progressContainer) progressContainer.classList.remove('hidden');
            if (progressBar) progressBar.style.width = '20%';

            const formData = new FormData();
            formData.append('page_id', pageId);
            formData.append('slide_key', slideKey);
            formData.append('media_type', 'images');
            
            for (let i = 0; i < input.files.length; i++) {
                formData.append('files[]', input.files[i]);
            }

            try {
                if (progressBar) progressBar.style.width = '50%';
                const res = await fetch('api.php?action=upload_slide_media', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (progressBar) progressBar.style.width = '100%';
                setTimeout(() => { if (progressContainer) progressContainer.classList.add('hidden'); }, 300);

                if (data.success) {
                    const container = document.getElementById('slide_photos_list_' + slideKey);
                    if (container) {
                        container.innerHTML = '';
                        const isStorySlide = ['premium_memory_book', 'premium_memory_timeline'].includes(slideKey);
                        data.paths.forEach(pinfo => {
                            const thumb = pinfo.thumb || pinfo.original;
                            const div = document.createElement('div');
                            div.className = 'flex items-start space-x-3 bg-slate-950/40 p-3 rounded-xl border border-slate-850 photo-config-row';
                            div.dataset.original = pinfo.original;
                            div.dataset.medium = pinfo.medium || pinfo.original;
                            div.dataset.thumb = thumb;
                            
                            let innerHtml = `
                                <div class="w-16 h-16 bg-slate-900 rounded-lg overflow-hidden border border-slate-800 flex-shrink-0 relative group/thumb">
                                    <img src="${thumb}" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-grow space-y-1">
                                    <input type="text" placeholder="Photo Caption" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-white outline-none focus:border-pink-500 text-xs photo-caption" value="">
                                    <input type="text" placeholder="Memory Date (e.g. 2026-06-23)" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-white outline-none focus:border-pink-500 text-xs photo-date" value="">
                            `;
                            if (isStorySlide) {
                                innerHtml += `<textarea placeholder="Story / Memory description" rows="2" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-white outline-none focus:border-pink-500 text-xs resize-none photo-memory"></textarea>`;
                            }
                            innerHtml += `</div>`;
                            div.innerHTML = innerHtml;
                            container.appendChild(div);
                        });
                    }
                    // Refresh live preview iframe
                    document.getElementById('live-preview-iframe').contentWindow.location.reload();
                } else {
                    alert(data.error || 'Failed to upload images.');
                }
            } catch (err) {
                console.error(err);
                alert('Connection error uploading images.');
                if (progressContainer) progressContainer.classList.add('hidden');
            }
        }

        // Slide Specific AJAX Media Uploads (Video, Voice, Music)
        async function uploadSlideMedia(input, slideKey, mediaType) {
            if (!input.files.length) return;

            const file = input.files[0];
            let maxSize = MAX_IMAGE_SIZE;
            let typeName = "Media file";
            if (mediaType === 'video') {
                maxSize = MAX_VIDEO_SIZE;
                typeName = "Video";
            } else if (mediaType === 'voice') {
                maxSize = MAX_VOICE_SIZE;
                typeName = "Voice note";
            } else if (mediaType === 'music') {
                maxSize = MAX_MUSIC_SIZE;
                typeName = "Background music";
            }

            if (file.size > maxSize) {
                alert(`${typeName} "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max size is ${(maxSize / 1024 / 1024)}MB.`);
                input.value = '';
                return;
            }

            if (file.size > POST_MAX_SIZE) {
                alert(`${typeName} "${file.name}" size is ${(file.size / 1024 / 1024).toFixed(2)}MB, which exceeds the server limit of ${(POST_MAX_SIZE / 1024 / 1024).toFixed(2)}MB.`);
                input.value = '';
                return;
            }

            const progressContainer = document.getElementById('upload_progress_container_' + slideKey);
            const progressBar = document.getElementById('upload_progress_bar_' + slideKey);
            if (progressContainer) progressContainer.classList.remove('hidden');
            if (progressBar) progressBar.style.width = '20%';

            const formData = new FormData();
            formData.append('page_id', pageId);
            formData.append('slide_key', slideKey);
            formData.append('media_type', mediaType);
            formData.append('file', file);

            try {
                if (progressBar) progressBar.style.width = '50%';
                const res = await fetch('api.php?action=upload_slide_media', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (progressBar) progressBar.style.width = '100%';
                setTimeout(() => { if (progressContainer) progressContainer.classList.add('hidden'); }, 300);

                if (data.success) {
                    const statusContainer = document.getElementById(`slide_${mediaType}_status_${slideKey}`);
                    if (statusContainer) {
                        const filename = data.path.split('/').pop();
                        statusContainer.innerHTML = `
                            <span class="text-emerald-400 truncate text-[10px]">Uploaded: ${filename}</span>
                            <button type="button" onclick="deleteSlideMedia('${slideKey}', '${mediaType}')" class="text-red-400 hover:text-red-300 ml-1">Delete</button>
                        `;
                    }
                    // Refresh live preview iframe
                    document.getElementById('live-preview-iframe').contentWindow.location.reload();
                } else {
                    alert(data.error || `Failed to upload ${mediaType}.`);
                }
            } catch (err) {
                console.error(err);
                alert(`Connection error uploading ${mediaType}.`);
                if (progressContainer) progressContainer.classList.add('hidden');
            }
        }

        // Slide Specific AJAX Media Deletions
        async function deleteSlideMedia(slideKey, mediaType) {
            if (!confirm(`Are you sure you want to delete this slide ${mediaType}?`)) return;

            try {
                const res = await fetch('api.php?action=delete_slide_media', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        page_id: pageId,
                        slide_key: slideKey,
                        media_type: mediaType
                    })
                });
                const data = await res.json();
                if (data.success) {
                    if (mediaType === 'images') {
                        const container = document.getElementById('slide_photos_list_' + slideKey);
                        if (container) container.innerHTML = '';
                    } else {
                        const statusContainer = document.getElementById(`slide_${mediaType}_status_${slideKey}`);
                        if (statusContainer) {
                            let label = 'No video uploaded';
                            if (mediaType === 'voice') label = 'No voice note';
                            else if (mediaType === 'music') label = 'No music file';
                            statusContainer.innerHTML = `<span>${label}</span>`;
                        }
                    }
                    // Refresh live preview iframe
                    document.getElementById('live-preview-iframe').contentWindow.location.reload();
                } else {
                    alert(data.message || `Failed to delete ${mediaType}.`);
                }
            } catch (err) {
                console.error(err);
                alert(`Connection error deleting ${mediaType}.`);
            }
        }
    </script>

    <!-- Premium fullscreen publishing overlay -->
    <div id="publishing-overlay" class="fixed inset-0 bg-slate-950/90 z-[9999] flex flex-col items-center justify-center backdrop-blur-md opacity-0 pointer-events-none transition-opacity duration-500">
        <div class="relative w-64 h-64 flex items-center justify-center mb-8">
            <!-- Glowing rings -->
            <div class="absolute inset-0 rounded-full border-4 border-t-pink-500 border-r-transparent border-b-cyan-400 border-l-transparent animate-spin duration-1000"></div>
            <div class="absolute inset-4 rounded-full border-4 border-t-transparent border-r-purple-500 border-b-transparent border-l-pink-400 animate-spin duration-700 reverse"></div>
            <div class="absolute inset-8 rounded-full border-2 border-dashed border-cyan-400/30 animate-pulse"></div>
            <!-- Center icon -->
            <div class="text-4xl animate-bounce">🚀</div>
        </div>
        <!-- Publishing status and progress -->
        <h3 class="text-xl font-bold text-white mb-2 tracking-wide text-center">Publishing Your Page Live...</h3>
        <p id="publishing-status" class="text-sm text-pink-400 font-medium h-6 animate-pulse text-center">Creating the final magic links...</p>
        
        <!-- Progress bar -->
        <div class="w-64 h-1.5 bg-slate-800 rounded-full overflow-hidden mt-6 shadow-inner">
            <div id="publishing-progress" class="h-full bg-gradient-to-r from-pink-500 via-purple-500 to-cyan-400 w-0 transition-all duration-300 ease-out"></div>
        </div>
        <p class="text-[10px] text-slate-400 font-bold mt-4 text-center tracking-wider uppercase animate-pulse">âš ï¸ Please do not press back or refresh. Be patient...</p>
    </div>


    <!-- Microphone permission alert modal -->
    <div id="mic-permission-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[999] hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-sm w-full text-center space-y-4 shadow-2xl">
            <div class="w-12 h-12 rounded-full bg-red-500/10 text-red-400 flex items-center justify-center mx-auto text-xl font-sans">âš ï¸</div>
            <h3 class="text-white font-bold text-sm">Microphone Access Required</h3>
            <p class="text-xs text-slate-400 leading-relaxed">Microphone access is required to record a voice message. Please enable it in your browser settings and try again.</p>
            <div class="flex items-center gap-3 pt-2">
                <button type="button" id="btn-mic-retry" class="flex-1 py-2 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition hover:opacity-95">Allow Again</button>
                <button type="button" id="btn-mic-cancel" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl text-xs transition">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Slide Voice Record Modal -->
    <div id="slide-record-modal" class="fixed inset-0 bg-slate-950/85 backdrop-blur-sm z-[999] hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-sm w-full text-center space-y-4 shadow-2xl relative">
            <button type="button" id="btn-close-slide-record" class="absolute top-4 right-4 text-slate-500 hover:text-slate-300 text-lg font-bold">&times;</button>
            <div class="w-12 h-12 rounded-full bg-pink-500/10 text-pink-500 flex items-center justify-center mx-auto text-xl">🎙️</div>
            <h3 class="text-white font-bold text-sm">Record Voice for <span id="slide-record-title">Slide</span></h3>
            
            <div class="flex items-center justify-between mb-3 text-xs bg-slate-950/20 p-2.5 rounded-xl border border-slate-800">
                <span id="slide-record-status-text" class="text-slate-400 font-semibold">Ready to record</span>
                <span id="slide-record-timer" class="font-mono text-pink-400 font-bold">0:00</span>
            </div>

            <!-- Waveform Canvas -->
            <canvas id="slide-record-visualizer" class="w-full h-12 bg-slate-950/60 rounded-xl border border-slate-800/40 hidden" width="300" height="48"></canvas>

            <div class="flex items-center justify-center gap-2">
                <button type="button" id="btn-start-slide-rec" class="px-4 py-2 bg-pink-600 hover:bg-pink-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-pink-600/20">
                    <span>âºï¸</span> <span>Record</span>
                </button>
                <button type="button" id="btn-pause-slide-rec" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 hidden">
                    <span>â¸ï¸</span> <span>Pause</span>
                </button>
                <button type="button" id="btn-resume-slide-rec" class="px-4 py-2 bg-pink-600 hover:bg-pink-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 hidden">
                    <span>â–¶ï¸</span> <span>Resume</span>
                </button>
                <button type="button" id="btn-stop-slide-rec" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 hidden">
                    <span>â¹ï¸</span> <span>Stop</span>
                </button>
            </div>

            <!-- Preview & Save -->
            <div id="slide-record-preview-container" class="space-y-3 hidden pt-3 border-t border-slate-800/50">
                <audio id="slide-record-preview-player" controls class="h-8 w-full max-w-[240px] mx-auto rounded-lg"></audio>
                <div class="flex items-center gap-3">
                    <button type="button" id="btn-clear-slide-rec" class="flex-grow py-2 bg-slate-800 hover:bg-slate-750 text-red-400 font-semibold rounded-xl text-xs transition">Delete</button>
                    <button type="button" id="btn-save-slide-rec" class="flex-grow py-2 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition hover:opacity-95">Save Recording</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mic permission modal utility
        function showMicPermissionModal(retryCallback) {
            const modal = document.getElementById('mic-permission-modal');
            const retryBtn = document.getElementById('btn-mic-retry');
            const cancelBtn = document.getElementById('btn-mic-cancel');
            if (!modal) return;
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Clean up old events
            const newRetryBtn = retryBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            retryBtn.parentNode.replaceChild(newRetryBtn, retryBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
            
            newRetryBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                if (retryCallback) retryCallback();
            });
            
            newCancelBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            });
        }

        // Slide Voice Recording Variables
        let activeSlideRecordKey = null;
        let slideMediaRecorder = null;
        let slideAudioChunks = [];
        let slideRecordSeconds = 0;
        let isSlideRecordPaused = false;
        let slideRecordTimerInterval = null;
        let slideAudioBlob = null;
        let slideAudioCtx = null;
        let slideAnalyser = null;
        let slideVisualSource = null;
        let slideDrawVisualId = null;

        const slideModal = document.getElementById('slide-record-modal');
        const slideModalTitle = document.getElementById('slide-record-title');
        const slideCloseBtn = document.getElementById('btn-close-slide-record');
        const slideStatusText = document.getElementById('slide-record-status-text');
        const slideTimerSpan = document.getElementById('slide-record-timer');
        const slideVisualizerCanvas = document.getElementById('slide-record-visualizer');
        const slideStartBtn = document.getElementById('btn-start-slide-rec');
        const slidePauseBtn = document.getElementById('btn-pause-slide-rec');
        const slideResumeBtn = document.getElementById('btn-resume-slide-rec');
        const slideStopBtn = document.getElementById('btn-stop-slide-rec');
        const slidePreviewContainer = document.getElementById('slide-record-preview-container');
        const slidePreviewPlayer = document.getElementById('slide-record-preview-player');
        const slideClearBtn = document.getElementById('btn-clear-slide-rec');
        const slideSaveBtn = document.getElementById('btn-save-slide-rec');

        function drawSlideWaveform() {
            if (!slideAnalyser || !slideVisualizerCanvas) return;
            const ctx = slideVisualizerCanvas.getContext('2d');
            const bufferLength = slideAnalyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);
            
            slideVisualizerCanvas.style.display = 'block';

            function draw() {
                if (!slideMediaRecorder || slideMediaRecorder.state === 'inactive') {
                    slideVisualizerCanvas.style.display = 'none';
                    return;
                }
                slideDrawVisualId = requestAnimationFrame(draw);
                
                slideAnalyser.getByteFrequencyData(dataArray);
                ctx.fillStyle = 'rgba(15, 23, 42, 0.7)';
                ctx.fillRect(0, 0, slideVisualizerCanvas.width, slideVisualizerCanvas.height);

                const barWidth = (slideVisualizerCanvas.width / bufferLength) * 2.5;
                let barHeight;
                let x = 0;

                for (let i = 0; i < bufferLength; i++) {
                    barHeight = dataArray[i] / 2.5;
                    ctx.fillStyle = isSlideRecordPaused ? '#64748b' : '#ec4899';
                    ctx.fillRect(x, slideVisualizerCanvas.height - barHeight, barWidth - 1, barHeight);
                    x += barWidth;
                }
            }
            draw();
        }

        function openSlideVoiceRecorder(slideKey) {
            activeSlideRecordKey = slideKey;
            slideModalTitle.textContent = slideKey;
            slideModal.classList.remove('hidden');
            slideModal.classList.add('flex');
            resetSlideRecorderUI();
        }

        function resetSlideRecorderUI() {
            slideStatusText.textContent = 'Ready to record';
            slideTimerSpan.textContent = '0:00';
            slideRecordSeconds = 0;
            isSlideRecordPaused = false;
            slideAudioChunks = [];
            slideAudioBlob = null;
            
            slideStartBtn.classList.remove('hidden');
            slidePauseBtn.classList.add('hidden');
            slideResumeBtn.classList.add('hidden');
            slideStopBtn.classList.add('hidden');
            slidePreviewContainer.classList.add('hidden');
            slideVisualizerCanvas.style.display = 'none';
            slidePreviewPlayer.src = '';

            if (slideRecordTimerInterval) clearInterval(slideRecordTimerInterval);
            if (slideDrawVisualId) cancelAnimationFrame(slideDrawVisualId);
            if (slideAudioCtx && slideAudioCtx.state !== 'closed') {
                slideAudioCtx.close();
            }
        }

        slideCloseBtn.addEventListener('click', () => {
            slideModal.classList.add('hidden');
            slideModal.classList.remove('flex');
            resetSlideRecorderUI();
        });

        slideStartBtn.addEventListener('click', async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                slideAudioChunks = [];
                isSlideRecordPaused = false;
                
                let mimeType = 'audio/webm';
                if (MediaRecorder.isTypeSupported('audio/mp4')) mimeType = 'audio/mp4';
                else if (MediaRecorder.isTypeSupported('audio/ogg')) mimeType = 'audio/ogg';
                else if (MediaRecorder.isTypeSupported('audio/wav')) mimeType = 'audio/wav';

                slideMediaRecorder = new MediaRecorder(stream, { mimeType });
                
                try {
                    slideAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    slideAnalyser = slideAudioCtx.createAnalyser();
                    slideVisualSource = slideAudioCtx.createMediaStreamSource(stream);
                    slideVisualSource.connect(slideAnalyser);
                    slideAnalyser.fftSize = 64;
                    drawSlideWaveform();
                } catch (ae) {
                    console.warn("Slide visualizer failed to init", ae);
                }

                slideMediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        slideAudioChunks.push(event.data);
                    }
                };

                slideMediaRecorder.onstop = () => {
                    slideAudioBlob = new Blob(slideAudioChunks, { type: mimeType });
                    const audioUrl = URL.createObjectURL(slideAudioBlob);
                    slidePreviewPlayer.src = audioUrl;
                    slidePreviewContainer.classList.remove('hidden');
                    
                    stream.getTracks().forEach(track => track.stop());
                    if (slideAudioCtx && slideAudioCtx.state !== 'closed') {
                        slideAudioCtx.close();
                    }
                    if (slideDrawVisualId) cancelAnimationFrame(slideDrawVisualId);
                    slideVisualizerCanvas.style.display = 'none';
                };

                slideMediaRecorder.start();
                
                slideStartBtn.classList.add('hidden');
                slidePauseBtn.classList.remove('hidden');
                slideStopBtn.classList.remove('hidden');
                slideResumeBtn.classList.add('hidden');
                slideStatusText.textContent = 'Recording';

                slideRecordSeconds = 0;
                slideTimerSpan.textContent = '0:00';
                slideRecordTimerInterval = setInterval(() => {
                    if (!isSlideRecordPaused) {
                        slideRecordSeconds++;
                        const mins = Math.floor(slideRecordSeconds / 60);
                        const secs = slideRecordSeconds % 60;
                        slideTimerSpan.textContent = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
                        
                        // Limit to 5 minutes (300 seconds)
                        if (slideRecordSeconds >= 300) {
                            slideStopBtn.click();
                        }
                    }
                }, 1000);

            } catch (err) {
                console.error('Microphone error:', err);
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError' || err.message.includes('permission')) {
                    showMicPermissionModal(() => slideStartBtn.click());
                } else {
                    alert('Recording failed: ' + err.message);
                }
            }
        });

        slidePauseBtn.addEventListener('click', () => {
            if (slideMediaRecorder && slideMediaRecorder.state === 'recording') {
                slideMediaRecorder.pause();
                isSlideRecordPaused = true;
                slidePauseBtn.classList.add('hidden');
                slideResumeBtn.classList.remove('hidden');
                slideStatusText.textContent = 'Paused';
            }
        });

        slideResumeBtn.addEventListener('click', () => {
            if (slideMediaRecorder && slideMediaRecorder.state === 'paused') {
                slideMediaRecorder.resume();
                isSlideRecordPaused = false;
                slideResumeBtn.classList.add('hidden');
                slidePauseBtn.classList.remove('hidden');
                slideStatusText.textContent = 'Recording';
            }
        });

        slideStopBtn.addEventListener('click', () => {
            if (slideMediaRecorder && slideMediaRecorder.state !== 'inactive') {
                slideMediaRecorder.stop();
                clearInterval(slideRecordTimerInterval);
                slideStartBtn.classList.remove('hidden');
                slidePauseBtn.classList.add('hidden');
                slideResumeBtn.classList.add('hidden');
                slideStopBtn.classList.add('hidden');
                slideStatusText.textContent = 'Stopped';
            }
        });

        slideClearBtn.addEventListener('click', () => {
            resetSlideRecorderUI();
        });

        slideSaveBtn.addEventListener('click', async () => {
            if (!slideAudioBlob || !activeSlideRecordKey) return;
            
            const btn = slideSaveBtn;
            btn.disabled = true;
            btn.textContent = 'Saving...';

            const mimeType = slideAudioBlob.type;
            const extension = mimeType.split('/')[1].split(';')[0];
            const file = new File([slideAudioBlob], `recorded_slide_voice.${extension}`, { type: mimeType });

            const formData = new FormData();
            formData.append('page_id', pageId);
            formData.append('slide_key', activeSlideRecordKey);
            formData.append('media_type', 'voice');
            formData.append('file', file);
            formData.append('voice_duration', slideRecordSeconds);

            try {
                const response = await fetch('api.php?action=upload_slide_media', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                btn.disabled = false;
                btn.textContent = 'Save Recording';

                if (res.success) {
                    slideModal.classList.add('hidden');
                    slideModal.classList.remove('flex');
                    resetSlideRecorderUI();
                    
                    // Update status label in slide list
                    const statusDiv = document.getElementById(`slide_voice_status_${activeSlideRecordKey}`);
                    if (statusDiv) {
                        statusDiv.innerHTML = `<span class="text-emerald-400 truncate">Recorded: ${res.path.split('/').pop()}</span>
                        <button type="button" onclick="deleteSlideMedia('${activeSlideRecordKey}', 'voice')" class="text-red-400 hover:text-red-300 ml-1">Delete</button>`;
                    }
                    
                    // Reload preview iframe
                    if (previewIframe) {
                        previewIframe.contentWindow.location.reload();
                    }
                } else {
                    alert(res.error || 'Failed to save slide voice note.');
                }
            } catch (err) {
                console.error(err);
                btn.disabled = false;
                btn.textContent = 'Save Recording';
                alert('A connection error occurred while saving.');
            }
        });
    </script>
<script src="assets/js/img-compress.js"></script>
<script src="assets/js/audio-fix.js"></script>
<script src="assets/js/aac-playback-fix.js"></script>
</body>
</html>
