<?php
require_once 'includes/functions.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    redirect('index.php');
}

// Fetch Page Details
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page || $page['status'] !== 'published') {
    redirect('index.php');
}

// Construct the URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$dir = dirname($_SERVER['PHP_SELF']);
if ($dir === '\\' || $dir === '/') {
    $dir = '';
}
// Clean URL using .htaccess route
$clean_url = $protocol . $host . $dir . "/p/" . $slug;
// Fallback URL in case rewrite is disabled
$fallback_url = $protocol . $host . $dir . "/p.php?s=" . $slug;

// Calculate Expiry Details
$created_time = strtotime($page['created_at']);
$expiry_time = !empty($page['expiry_date']) ? strtotime($page['expiry_date']) : strtotime("+10 days", $created_time);
$now_time = time();
$days_remaining = max(0, ceil(($expiry_time - $now_time) / 86400));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Page Published Successfully! 🎉 - SoulSync</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        .hero-bg {
            background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important;
            background-attachment: fixed;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(236, 72, 153, 0.12);
        }

        /* Light-theme contrast overrides (same pattern as create-page.php) */
        body, p, label, li { color: #334155 !important; }
        h1, h2, h3, h4 { color: #0f172a !important; }
        .text-white, .text-slate-100, .text-slate-200, .text-slate-300 { color: #0f172a !important; }
        .text-slate-400, .text-slate-500 { color: #64748b !important; }
        .bg-slate-950\/80 { background-color: rgba(255,255,255,0.85) !important; }
        .bg-slate-900\/60, .bg-slate-900\/40, .bg-slate-950, .bg-slate-900 { background-color: #ffffff !important; }
        .hover\:bg-slate-800:hover { background-color: #fdf2f8 !important; }
        .border-slate-900, .border-slate-800, .border-slate-800\/80, .border-slate-800\/60 { border-color: rgba(203,213,225,0.8) !important; }
        .bg-clip-text.text-transparent { color: transparent !important; -webkit-text-fill-color: transparent !important; }
        .text-pink-400 { color: #db2777 !important; }
        .text-emerald-400 { color: #059669 !important; }
        .hover\:text-white:hover { color: #db2777 !important; }
        a.bg-\[\#25D366\], a.bg-\[\#25D366\] span { color: #ffffff !important; }
    </style>
</head>
<body class="text-slate-800 font-sans min-h-screen flex flex-col justify-between hero-bg antialiased">
<?= function_exists("ss_web_ad") ? ss_web_ad("publish") : "" ?>

    <!-- Emoji Rain -->
    <?php render_emoji_rain(); ?>

    <!-- Header -->
    <header class="bg-slate-950/80 backdrop-blur-md border-b border-slate-900 h-16 flex items-center">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-2">
                <span class="text-2xl font-bold font-heading tracking-wider bg-gradient-to-r from-pink-500 to-purple-500 bg-clip-text text-transparent">SoulSync</span>
            </a>
            <div class="flex items-center gap-4">
                <?php if (is_logged_in()): ?>
                    <a href="dashboard.php" class="text-xs font-semibold text-slate-350 hover:text-white transition">Dashboard</a>
                    <?php if (is_admin()): ?>
                        <a href="admin/index.php" class="text-xs font-semibold text-purple-400 hover:text-purple-300 transition">Admin Portal 🛠️</a>
                    <?php endif; ?>
                    <a href="logout.php" class="text-xs font-semibold text-slate-500 hover:text-white transition">Logout</a>
                <?php else: ?>
                    <a href="index.php" class="text-sm text-slate-400 hover:text-white transition">Home</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main -->
    <main class="flex-grow flex items-center justify-center p-4 py-8">
        <div class="w-full max-w-lg glass-card rounded-3xl p-8 shadow-2xl relative text-center overflow-hidden">
            <div class="absolute -top-10 -left-10 w-40 h-40 bg-pink-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-blue-500/10 rounded-full blur-3xl"></div>

            <div class="relative z-10">
                <div class="w-20 h-20 bg-pink-500/10 border border-pink-500/20 text-4xl flex items-center justify-center rounded-3xl mx-auto mb-6">
                    🎉
                </div>
                
                <h2 class="text-3xl font-extrabold font-heading mb-2">Congratulations!</h2>
                <p class="text-slate-400 text-sm mb-8">Your emotional story page is published and ready to share.</p>
                
                <!-- Shareable URL -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 mb-6">
                    <span class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest mb-2 text-left">Your Surprise Link</span>
                    <div class="flex items-center justify-between bg-slate-950 border border-slate-800 rounded-xl p-2.5">
                        <span class="text-xs text-white font-mono truncate mr-2 select-all" id="share-link-text"><?= h($clean_url) ?></span>
                        <button onclick="copyLink()" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-xs font-bold text-slate-300 rounded-lg shrink-0 transition" id="copy-btn">
                            Copy
                        </button>
                    </div>
                    <div class="text-[10px] text-slate-500 mt-2 text-left flex justify-between">
                        <span>Having hosting issues? Try:</span>
                        <a href="<?= h($fallback_url) ?>" class="text-pink-400 hover:underline"><?= h($fallback_url) ?></a>
                    </div>
                </div>

                <!-- Expiry Information Card -->
                <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5 mb-6 text-left relative overflow-hidden glass-card">
                    <span class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest mb-3">LIFESPAN & EXPIRY</span>
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block text-[10px] text-slate-400 uppercase tracking-wider">Created On</span>
                            <span class="text-xs sm:text-sm font-semibold text-white"><?= date('d M Y, h:i A', $created_time) ?></span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] text-slate-400 uppercase tracking-wider">Expires On</span>
                            <span class="text-xs sm:text-sm font-semibold text-white"><?= date('d M Y, h:i A', $expiry_time) ?></span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-800/60 flex items-center justify-between">
                        <span class="text-xs text-slate-400">Time Remaining</span>
                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center space-x-1">
                            <span>⏳</span> <span><?= $days_remaining ?> Days</span>
                        </span>
                    </div>
                </div>

                <!-- QR Code Generator -->
                <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-5 mb-6 flex flex-col items-center">
                    <span class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest mb-3">Scan QR Code</span>
                    <div class="bg-white p-3 rounded-2xl shadow-inner mb-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($clean_url) ?>" alt="QR Code" class="w-[140px] h-[140px] block">
                    </div>
                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?= urlencode($clean_url) ?>" target="_blank" class="text-xs text-pink-400 hover:text-pink-300 font-medium transition flex items-center space-x-1">
                        <span>📥</span> <span>Download QR Code (High Res)</span>
                    </a>
                </div>

                <!-- Sharing Buttons -->
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <!-- WhatsApp -->
                    <a href="https://api.whatsapp.com/send?text=<?= rawurlencode("Hey " . $page['receiver_name'] . ", I created a special surprise card for you! Open it here: " . $clean_url) ?>" 
                       target="_blank"
                       class="py-3.5 bg-[#25D366] hover:bg-[#20ba59] text-white font-bold rounded-2xl text-sm flex items-center justify-center space-x-2 shadow-lg transition">
                        <span>💬</span> <span>WhatsApp</span>
                    </a>
                    <!-- Preview -->
                    <a href="<?= h($clean_url) ?>" 
                       target="_blank"
                       class="py-3.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 text-white font-bold rounded-2xl text-sm flex items-center justify-center space-x-2 transition">
                        <span>👁️</span> <span>Open Live</span>
                    </a>
                </div>

                <!-- Guest Registration Banner -->
                <?php if (empty($page['user_id'])): ?>
                    <div class="bg-gradient-to-r from-pink-500/10 via-purple-500/10 to-blue-500/10 border border-slate-800 rounded-2xl p-6 text-left relative overflow-hidden">
                        <h3 class="font-bold text-white mb-1">Save this page & track views!</h3>
                        <p class="text-xs text-slate-400 leading-relaxed mb-4">
                            You created this card as a Guest. Register an account to see if they open it, check their reactions (❤️, 😊, 🎉), and edit it anytime.
                        </p>
                        <a href="signup.php" class="inline-block px-5 py-2.5 bg-gradient-to-r from-pink-500 to-purple-500 text-white text-xs font-bold rounded-xl hover:opacity-95 shadow-md transition">
                            Save My Page
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-xs text-slate-500">
                        Check your dashboard to view visitor statistics. <a href="dashboard.php" class="text-pink-400 hover:underline">Go to Dashboard</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        

    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs">
        &copy; <?= date('Y') ?> SoulSync. All rights reserved.
    </footer>

    <script>
        function copyLink() {
            const linkText = document.getElementById('share-link-text').innerText;
            navigator.clipboard.writeText(linkText).then(() => {
                const btn = document.getElementById('copy-btn');
                btn.textContent = 'Copied!';
                btn.classList.add('bg-pink-500', 'text-white');
                setTimeout(() => {
                    btn.textContent = 'Copy';
                    btn.classList.remove('bg-pink-500', 'text-white');
                }, 2000);
            });
        }
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
<?= function_exists("ss_web_sticky") ? ss_web_sticky() : "" ?>
<?= function_exists("ss_web_overlays") ? ss_web_overlays() : "" ?>
</body>
</html>
