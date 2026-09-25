<?php
require_once 'includes/functions.php';

// Fetch user info if logged in
$user = null;
if (is_logged_in()) {
    $user = get_user_profile($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Privacy Policy - <?= h(SITE_NAME) ?></title>
    <meta name="description" content="Read the privacy policy and data protection guidelines for <?= h(SITE_NAME) ?>.">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
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

        body {
            background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important;
            background-attachment: fixed;
            color: var(--text-primary) !important;
            min-height: 100vh;
        }

        .sp-glass {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(236, 72, 153, 0.12) !important;
            border-radius: 24px;
        }

        .sp-gradient-text {
            background: linear-gradient(135deg, #be185d 0%, #db2777 40%, #e11d48 70%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .prose h2 {
            font-family: 'Outfit', sans-serif;
            color: #0f172a;
            font-weight: 700;
            font-size: 1.25rem;
            margin-top: 2rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(236, 72, 153, 0.15);
            padding-bottom: 0.5rem;
        }

        .prose p {
            color: #334155;
            font-size: 0.875rem;
            line-height: 1.625;
            margin-bottom: 1.25rem;
        }

        .prose ul {
            list-style-type: disc;
            padding-left: 1.5rem;
            color: #334155;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }

        .prose li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body class="font-sans min-h-screen flex flex-col antialiased" style="background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%); color: #0f172a;">

    <!-- Header / Navigation -->
    <header style="background: rgba(255,255,255,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(236,72,153,0.12);" class="sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="index.php" class="text-2xl font-extrabold font-heading sp-gradient-text tracking-wide">
                <?= h(SITE_NAME) ?>
            </a>
            <nav class="flex items-center gap-3">
                <a href="index.php" class="text-sm font-medium text-slate-650 hover:text-pink-600 transition px-4 py-2">Home</a>
                <?php if (is_logged_in() && $user): ?>
                    <a href="dashboard.php" class="text-sm font-bold bg-pink-500/10 text-pink-500 border border-pink-500/20 px-4 py-2 rounded-xl transition">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="text-sm font-bold bg-pink-500/10 text-pink-500 border border-pink-500/20 px-4 py-2 rounded-xl transition">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow py-12 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto w-full">
        <!-- Ambient glows -->
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-pink-500/5 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-1/3 right-1/4 w-80 h-80 bg-purple-500/5 rounded-full blur-[100px] pointer-events-none"></div>

        <div class="sp-glass p-8 sm:p-12 relative z-10">
            <div class="text-center mb-8">
                <h1 class="text-3xl sm:text-5xl font-extrabold font-heading sp-gradient-text mb-4">Privacy Policy</h1>
                <p class="text-xs text-pink-200/40 uppercase tracking-widest font-semibold">Last Updated: June 2026</p>
            </div>

            <div class="prose max-w-none">
                <p>At <?= h(SITE_NAME) ?>, we are committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website and use our services.</p>

                <h2>1. Information We Collect</h2>
                <p>We collect information you provide directly to us when creating a page or replies. This includes:</p>
                <ul>
                    <li><strong>Account Information:</strong> Name, email address, username, password, profile photo, and external auth IDs (when using Google/Firebase login).</li>
                    <li><strong>User-Generated Content:</strong> Recipient names, emotional letters/texts, custom photos, uploaded music tracks, voice recordings, and videos.</li>
                    <li><strong>Visitor Responses:</strong> Comments, text, emojis, or audio/media replies submitted by visitors on your active pages.</li>
                    <li><strong>Transaction Data:</strong> Payment details (order ID, payment ID) processed via Razorpay. We do not store credit card numbers on our servers.</li>
                </ul>

                <h2>2. How We Use Your Information</h2>
                <p>We use the collected data for the following purposes:</p>
                <ul>
                    <li>To operate, maintain, and deliver the services of <?= h(SITE_NAME) ?>.</li>
                    <li>To render your emotional love stories with interactive elements.</li>
                    <li>To send notification emails, transaction receipts, and system alerts.</li>
                    <li>To process transactions and award page credits.</li>
                    <li>To optimize system resources by deleting files of expired pages.</li>
                </ul>

                <h2>3. Data Storage & Auto-Deletion</h2>
                <p>We value server efficiency and storage privacy:</p>
                <ul>
                    <li>All media files (images, audio, videos) associated with a page are stored securely in our upload directories.</li>
                    <li><strong>Automatic Cleanup:</strong> When a page expires, all related media files (voice notes, uploaded music, videos, photos, and reply media) are permanently deleted from our servers to protect your privacy and free up disk space.</li>
                </ul>

                <h2>4. Third-Party Services</h2>
                <p>We integrate several secure third-party components:</p>
                <ul>
                    <li><strong>Firebase Authentication / Google Identity:</strong> To provide secure, instant logins.</li>
                    <li><strong>Razorpay:</strong> To process premium credit checkouts securely.</li>
                    <li><strong>Google AdSense:</strong> To display advertising on content pages of the site.</li>
                </ul>

                <h2>5. Advertising & Google AdSense</h2>
                <p>We use Google AdSense to serve advertisements on some pages of <?= h(SITE_NAME) ?>. Please note the following:</p>
                <ul>
                    <li>Third-party vendors, including Google, use cookies to serve ads based on your prior visits to this website or other websites.</li>
                    <li>Google's use of advertising cookies (such as the DoubleClick cookie) enables it and its partners to serve ads to you based on your visit to our site and/or other sites on the Internet.</li>
                    <li>You may opt out of personalised advertising by visiting <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener" class="text-pink-450 hover:underline">Google Ads Settings</a>, or opt out of some third-party vendors' use of cookies at <a href="https://www.aboutads.info/choices" target="_blank" rel="noopener" class="text-pink-450 hover:underline">www.aboutads.info</a>.</li>
                    <li>We do not place advertising on login, checkout, or other purely functional screens.</li>
                </ul>

                <h2>6. Cookies & Tracking Technologies</h2>
                <p>We use session cookies and persistent cookies to manage login sessions, secure forms (CSRF protection), and store referral attributes (`sp_ref`). Third-party advertising partners such as Google may also set cookies as described in the section above. You can control or delete cookies at the individual browser level; most browsers also let you block third-party cookies entirely.</p>

                <h2>7. Your Rights & Choice</h2>
                <p>You can manage your profile settings, upload a new photo, or update password from your Dashboard. You also have the right to **Permanently Delete Your Account** from the settings panel. Deletion will immediately destroy all your data, including pages, uploads, and views, and is completely irreversible.</p>

                <h2>8. Contact Us</h2>
                <p>If you have any questions or concerns about this Privacy Policy, please contact us through our <a href="support.php" class="text-pink-450 hover:underline">support page</a>.</p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="background: rgba(6,0,15,0.9); border-top: 1px solid rgba(236,72,153,0.08);" class="py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-sm text-pink-200/30 font-medium">
                © <?= date('Y') ?> <?= h(SITE_NAME) ?> — Made with 💖 for sharing emotions.
            </div>
            <div class="flex gap-6 text-sm text-pink-200/30 flex-wrap justify-center">
                <a href="about.php" class="hover:text-pink-400 transition">About</a>
                <a href="contact.php" class="hover:text-pink-400 transition">Contact</a>
                <a href="privacy.php" class="hover:text-pink-400 transition">Privacy Policy</a>
                <a href="terms.php" class="hover:text-pink-400 transition">Terms</a>
            </div>
        </div>
    </footer>

</body>
</html>
