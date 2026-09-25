<?php
require_once 'includes/functions.php';
$user = is_logged_in() ? get_user_profile($_SESSION['user_id']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?= h(SITE_NAME) ?></title>
    <meta name="description" content="Learn what <?= h(SITE_NAME) ?> is, why we built it, and how it helps people share emotions through personal interactive greeting pages.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'], heading: ['Outfit','sans-serif'] } } } }</script>
    <style>
        body { background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important; background-attachment: fixed; color: #0f172a !important; min-height: 100vh; }
        .sp-glass { background: rgba(255,255,255,0.8) !important; backdrop-filter: blur(20px); border: 1px solid rgba(236,72,153,0.12) !important; border-radius: 24px; }
        .sp-gradient-text { background: linear-gradient(135deg, #be185d 0%, #db2777 40%, #e11d48 70%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .prose h2 { font-family: 'Outfit', sans-serif; color: #0f172a; font-weight: 700; font-size: 1.25rem; margin-top: 2rem; margin-bottom: 1rem; border-bottom: 1px solid rgba(236,72,153,0.15); padding-bottom: 0.5rem; }
        .prose p { color: #334155; font-size: 0.9rem; line-height: 1.7; margin-bottom: 1.25rem; }
        .prose ul { list-style-type: disc; padding-left: 1.5rem; color: #334155; font-size: 0.9rem; margin-bottom: 1.25rem; }
        .prose li { margin-bottom: 0.5rem; }
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

    <main class="flex-grow py-12 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto w-full">
        <div class="sp-glass p-8 sm:p-12 relative z-10">
            <div class="text-center mb-8">
                <div class="text-5xl mb-4">💌</div>
                <h1 class="text-3xl sm:text-5xl font-extrabold font-heading sp-gradient-text mb-4">About <?= h(SITE_NAME) ?></h1>
                <p class="text-sm text-slate-500">The story behind the stories.</p>
            </div>

            <div class="prose max-w-none">
                <p><?= h(SITE_NAME) ?> is a small tool with one job: helping people say the things that matter to the people who matter — in a way that feels personal, not copy-pasted. We turn your words, photos, voice and music into a private, interactive greeting page that opens with a single link.</p>

                <h2>Why we built this</h2>
                <p>Most of us carry feelings we never quite deliver. The birthday wish that deserved more than a sticker. The apology that a "sorry yaar" text couldn't carry. The proposal you practised in the mirror. Greeting cards are generic, social media is public, and messaging apps flatten everything into the same grey bubble. We wanted a middle path — something you can make in ten minutes that still feels like it took a piece of your heart.</p>

                <h2>What a page can hold</h2>
                <ul>
                    <li><strong>Your words</strong> — letters that reveal themselves line by line, with formatting for emphasis.</li>
                    <li><strong>Your memories</strong> — photo galleries, polaroid walls, timelines of your milestones, chat-style conversations.</li>
                    <li><strong>Your voice</strong> — voice notes and video messages, because some things need to be heard, not read.</li>
                    <li><strong>Music</strong> — a soundtrack from our library or your own upload, playing softly behind the story.</li>
                    <li><strong>Their reply</strong> — the person you send it to can answer right on the page, and their response lands in your dashboard instantly.</li>
                </ul>

                <h2>Templates for every feeling</h2>
                <p>We have free simple pages for quick wishes, festival experiences that take thirty seconds to make, and premium cinematic templates — multi-scene experiences like the Girlfriend Birthday Surprise, the Cinematic Proposal, the Sorry Page, and the Couple Story, our flagship: a full mini-website of a relationship with a live "together since" timer, achievements, letters and a starry ending.</p>

                <h2>Privacy first</h2>
                <p>Every page is private by default — only people with the link can open it. You can add a password, set an expiry date, and when a page expires, its photos, audio and video files are permanently deleted from our servers. You can also delete your entire account, and everything with it, at any time. Read the full <a href="privacy.php" class="text-pink-600 hover:underline">Privacy Policy</a>.</p>

                <h2>Made in India, made with heart</h2>
                <p><?= h(SITE_NAME) ?> is built and run by a small independent team in India. Payments are processed securely through Razorpay, and credits are priced simply — ₹10 per credit, with your first page free. No subscriptions, no hidden charges.</p>

                <h2>Get in touch</h2>
                <p>Ideas, problems, or just want to tell us how the page went? We genuinely love hearing the stories. Reach us any time via our <a href="support.php" class="text-pink-600 hover:underline">Help & Support</a> page.</p>
            </div>
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
