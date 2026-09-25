<?php
require_once 'includes/functions.php';
$user = is_logged_in() ? get_user_profile($_SESSION['user_id']) : null;

$sent = false; $cerr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $cerr = 'Security token expired — please try again.';
    } else {
        $cname = trim($_POST['cname'] ?? '');
        $cemail = trim($_POST['cemail'] ?? '');
        $cmsg = trim($_POST['cmsg'] ?? '');
        if ($cmsg === '' || $cname === '') {
            $cerr = 'Please fill your name and message.';
        } else {
            // store in site_settings-backed inbox (no schema change needed)
            $inbox = json_decode(get_setting('contact_inbox', '[]'), true);
            if (!is_array($inbox)) $inbox = [];
            array_unshift($inbox, ['name' => mb_substr($cname, 0, 100), 'email' => mb_substr($cemail, 0, 150), 'msg' => mb_substr($cmsg, 0, 2000), 'at' => date('Y-m-d H:i:s'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            $inbox = array_slice($inbox, 0, 200);
            set_setting('contact_inbox', json_encode($inbox, JSON_UNESCAPED_UNICODE));
            // best-effort email notification if a mailer exists
            if (function_exists('send_email')) {
                // Email send removed — contact messages are saved via the form above
            }
            $sent = true;
        }
    }
}
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?= h(SITE_NAME) ?></title>
    <meta name="description" content="Get in touch with the <?= h(SITE_NAME) ?> team — support, feedback, partnership or anything else.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'], heading: ['Outfit','sans-serif'] } } } }</script>
    <style>
        body { background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important; background-attachment: fixed; color: #0f172a !important; min-height: 100vh; }
        .sp-glass { background: rgba(255,255,255,0.8) !important; backdrop-filter: blur(20px); border: 1px solid rgba(236,72,153,0.12) !important; border-radius: 24px; }
        .sp-gradient-text { background: linear-gradient(135deg, #be185d 0%, #db2777 40%, #e11d48 70%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .cfld { width: 100%; background: #fff; border: 1px solid rgba(236,72,153,0.25); border-radius: 14px; padding: 12px 16px; color: #0f172a; font-size: 0.9rem; outline: none; }
        .cfld:focus { border-color: #db2777; }
    </style>
</head>
<body class="font-sans min-h-screen flex flex-col antialiased">

    <header style="background: rgba(255,255,255,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(236,72,153,0.12);" class="sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="index.php" class="text-2xl font-extrabold font-heading sp-gradient-text tracking-wide"><?= h(SITE_NAME) ?></a>
            <nav class="flex items-center gap-3">
                <a href="index.php" class="text-sm font-medium text-slate-600 hover:text-pink-600 transition px-4 py-2">Home</a>
                <?php if ($user): ?>
                    <a href="dashboard.php" class="text-sm font-bold bg-pink-500/10 text-pink-500 border border-pink-500/20 px-4 py-2 rounded-xl transition">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="text-sm font-bold bg-pink-500/10 text-pink-500 border border-pink-500/20 px-4 py-2 rounded-xl transition">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="flex-grow py-12 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto w-full">
        <div class="sp-glass p-8 sm:p-12 relative z-10">
            <div class="text-center mb-8">
                <div class="text-5xl mb-4">📮</div>
                <h1 class="text-3xl sm:text-4xl font-extrabold font-heading sp-gradient-text mb-3">Contact Us</h1>
                <p class="text-sm text-slate-500 leading-relaxed max-w-lg mx-auto">Questions about a page, payments, or a feature idea? Write to us — we usually reply within 24 hours. You can also visit our <a href="support.php" class="text-pink-600 font-semibold hover:underline">Help & Support</a> page.</p>
            </div>

            <?php if ($sent): ?>
            <div class="rounded-2xl p-6 text-center" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3);">
                <div class="text-3xl mb-2">💌</div>
                <p class="font-bold text-emerald-700">Message sent!</p>
                <p class="text-sm text-slate-500 mt-1">Thank you for writing to us. We'll get back to you soon.</p>
                <a href="index.php" class="inline-block mt-4 text-sm font-bold text-pink-600 hover:underline">← Back to home</a>
            </div>
            <?php else: ?>
            <?php if ($cerr): ?><div class="mb-5 bg-red-500/10 border border-red-400/40 text-red-600 rounded-xl p-4 text-sm"><?= h($cerr) ?></div><?php endif; ?>
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Your Name</label><input class="cfld" name="cname" required maxlength="100" value="<?= h($user['name'] ?? '') ?>"></div>
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email (for our reply)</label><input class="cfld" type="email" name="cemail" maxlength="150" value="<?= h($user['email'] ?? '') ?>"></div>
                </div>
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Message</label><textarea class="cfld" name="cmsg" rows="6" required maxlength="2000" placeholder="Tell us what's on your mind…"></textarea></div>
                <div class="text-center pt-2">
                    <button type="submit" class="font-bold text-white px-10 py-3.5 rounded-full" style="background: linear-gradient(135deg, #db2777, #e11d48); box-shadow: 0 10px 26px rgba(219,39,119,0.3);">Send Message 💌</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </main>

    <footer style="background: rgba(255,255,255,0.85); border-top: 1px solid rgba(236,72,153,0.08);" class="py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-sm text-slate-500 font-medium">© <?= date('Y') ?> <?= h(SITE_NAME) ?> — Made with 💖 for sharing emotions.</div>
            <div class="flex gap-6 text-sm text-slate-500 flex-wrap justify-center">
                <a href="about.php" class="hover:text-pink-400 transition">About</a>
                <a href="contact.php" class="hover:text-pink-400 transition">Contact</a>
                <a href="privacy.php" class="hover:text-pink-400 transition">Privacy Policy</a>
                <a href="terms.php" class="hover:text-pink-400 transition">Terms</a>
            </div>
        </div>
    </footer>
</body>
</html>
