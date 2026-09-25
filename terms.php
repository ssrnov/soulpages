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

    <title>Terms of Service - <?= h(SITE_NAME) ?></title>
    <meta name="description" content="Read the terms of service and usage conditions for <?= h(SITE_NAME) ?>.">
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
                <h1 class="text-3xl sm:text-5xl font-extrabold font-heading sp-gradient-text mb-4">Terms of Service</h1>
                <p class="text-xs text-pink-200/40 uppercase tracking-widest font-semibold">Last Updated: June 2026</p>
            </div>

            <div class="prose max-w-none">
                <p>Welcome to <?= h(SITE_NAME) ?>. These Terms of Service ("Terms") govern your access to and use of the <?= h(SITE_NAME) ?> website and services ("Services"). By accessing or using our Services, you agree to be bound by these Terms.</p>

                <h2>1. Account Registration & Security</h2>
                <p>To use certain features of <?= h(SITE_NAME) ?>, you may need to register for an account using Firebase Authentication or Google Sign-In. You agree to:</p>
                <ul>
                    <li>Provide accurate, current, and complete information during registration.</li>
                    <li>Maintain the security of your account credentials.</li>
                    <li>Notify us immediately if you suspect any unauthorized access to your account.</li>
                </ul>

                <h2>2. User Content & Conduct</h2>
                <p>You are solely responsible for the pages you create, including all text, messages, uploaded music, voices, photos, and videos ("User Content"). By uploading content, you guarantee that you own or have the necessary rights to use it.</p>
                <p>You agree not to create pages or upload content that:</p>
                <ul>
                    <li>Is illegal, threatening, defamatory, harassing, or hateful.</li>
                    <li>Violates copyright, trademark, privacy, or intellectual property rights.</li>
                    <li>Contains sexually explicit or adult content.</li>
                    <li>Aims to mislead, scam, or impersonate other individuals.</li>
                </ul>

                <h2>3. Premium Credits & Payments</h2>
                <p>We offer paid credits allowing you to keep pages active for longer or create additional pages. Payments are processed securely via Razorpay.</p>
                <ul>
                    <li>Credits are non-refundable once purchased, except as required by law.</li>
                    <li>Any purchased credits will be linked directly to your authenticated user account.</li>
                    <li>We reserve the right to change page credit prices at any time.</li>
                </ul>

                <h2>4. Page Lifetimes & Auto-Deletion</h2>
                <p>To keep our storage optimized, the following policies apply:</p>
                <ul>
                    <li>Pages have an expiration date based on the plan or credits used during creation.</li>
                    <li>Expired pages will be queued for deletion. Upon deletion, all associated files (voice notes, uploaded music, videos, photos, and replies) are permanently deleted from our servers.</li>
                    <li>It is your responsibility to extend a page's lifespan before it reaches its expiration date.</li>
                </ul>

                <h2>5. Limitation of Liability</h2>
                <p>To the maximum extent permitted by law, <?= h(SITE_NAME) ?> shall not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits, data, or media, resulting from your use of or inability to use the Services.</p>

                <h2>6. Termination</h2>
                <p>We reserves the right to suspend or terminate your account and delete any content or pages created if you violate these Terms or participate in activities that harm the platform or other users.</p>

                <h2>7. Changes to These Terms</h2>
                <p>We may update these Terms from time to time. We will notify you of any material changes by updating the "Last Updated" date at the top of this page. Your continued use of the platform constitutes acceptance of the new Terms.</p>

                <h2>8. Contact Us</h2>
                <p>If you have any questions or feedback regarding these Terms, please contact us through our <a href="support.php" class="text-pink-450 hover:underline">support page</a>.</p>
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
