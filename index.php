<?php
require_once 'includes/functions.php';

// Handle referral code from URL
if (isset($_GET['ref'])) {
    $ref = trim($_GET['ref']);
    setcookie('sp_ref', $ref, time() + (30 * 24 * 60 * 60), '/');
    $_SESSION['referral_code'] = $ref;
}

// Fetch some fake/real stats for the landing page
$stmt = $pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published'");
$real_count = $stmt->fetchColumn();
$display_count = $real_count + 12840; // Add seed count for viral look

// Fetch user info if logged in
$user = null;
if (is_logged_in()) {
    $user = get_user_profile($_SESSION['user_id']);
}

$categories = get_categories();

// Get dynamic SEO & Ads settings
$site_title = get_setting('site_title', 'SoulSync - Express Your Feelings Beautifully');
$site_description = get_setting('site_description', 'Create beautiful, interactive emotional story pages.');
$homepage_h1 = get_setting('homepage_h1', 'Create a Beautiful, Interactive Emotional Story Page');
$og_image_url = get_setting('og_image_url', '');
if (!empty($og_image_url) && strpos($og_image_url, 'http') !== 0) {
    $og_image_url = rtrim(SITE_URL, '/') . '/' . ltrim($og_image_url, '/');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($site_title) ?></title>
    <meta name="description" content="<?= h($site_description) ?>">
    <!-- OpenGraph (OG) Meta Tags for Social Sharing -->
    <meta property="og:title" content="<?= h($site_title) ?>">
    <meta property="og:description" content="<?= h($site_description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h(SITE_URL) ?>">
    <?php if (!empty($og_image_url)): ?>
        <meta property="og:image" content="<?= h($og_image_url) ?>">
    <?php endif; ?>
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($site_title) ?>">
    <meta name="twitter:description" content="<?= h($site_description) ?>">
    <?php if (!empty($og_image_url)): ?>
        <meta name="twitter:image" content="<?= h($og_image_url) ?>">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&family=Playfair+Display:ital,wght@0,600;1,700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
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

        /* Force light background and high contrast text colors */
        body {
            background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important;
            background-attachment: fixed;
            color: #0f172a !important;
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
        .sp-glass {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(236, 72, 153, 0.12) !important;
            border-radius: 24px;
            transition: all 0.3s ease;
        }
        .sp-glass:hover {
            border-color: rgba(236, 72, 153, 0.3) !important;
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.06), 0 20px 40px rgba(0,0,0,0.02) !important;
            transform: translateY(-3px);
        }

        /* Pink gradient text */
        .sp-gradient-text {
            background: linear-gradient(135deg, #be185d 0%, #db2777 40%, #e11d48 70%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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

        /* Scroll reveal animation */
        .sp-reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.7s cubic-bezier(0.16,1,0.3,1), transform 0.7s cubic-bezier(0.16,1,0.3,1);
        }
        .sp-reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .sp-reveal-delay-1 { transition-delay: 0.1s; }
        .sp-reveal-delay-2 { transition-delay: 0.2s; }
        .sp-reveal-delay-3 { transition-delay: 0.3s; }
        .sp-reveal-delay-4 { transition-delay: 0.4s; }

        /* Nav light */
        .sp-nav {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(236,72,153,0.12);
        }
    </style>
    <?php render_adsense_head_script(); ?>
    <?php $custom_css = get_setting('custom_css', ''); if ($custom_css !== ''): ?><style><?= $custom_css ?></style><?php endif; ?>
</head>
<body class="font-sans min-h-screen flex flex-col antialiased" style="background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%); color: #0f172a;">

    <!-- Emoji Rain -->
    <?php render_emoji_rain(); ?>

    <!-- Header / Navigation -->
    <header style="background: rgba(255,255,255,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(236,72,153,0.12);" class="sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <!-- Logo -->
            <a href="index.php" class="text-2xl font-extrabold font-heading sp-gradient-text tracking-wide">
                <?= h(SITE_NAME) ?>
            </a>
            
            <!-- Nav links — white/pink colors -->
            <nav class="flex items-center gap-3">
                <a href="soulsync.php" class="hidden sm:inline text-sm font-semibold text-purple-600 hover:text-pink-600 transition px-3 py-1.5">💜 SoulSync App</a>
                <?php if (is_logged_in() && $user): ?>
                    <?php if (is_admin()): ?>
                        <a href="admin/index.php" class="text-xs font-semibold bg-purple-500/10 border border-purple-500/20 text-purple-400 px-3 py-1.5 rounded-xl transition">Admin Portal 🛠️</a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-pink-600 transition">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img src="<?= h($user['profile_photo']) ?>" class="w-6 h-6 rounded-full border border-pink-500/30" alt="">
                        <?php endif; ?>
                        <span class="hidden md:inline"><?= h($user['name']) ?></span>
                    </a>
                    <a href="dashboard.php" class="text-xs bg-pink-500/10 text-pink-500 px-2.5 py-1 rounded-md font-semibold border border-pink-500/20">✨ <?= (int)get_user_credits($user['id']) ?></a>
                    <a href="logout.php" class="text-sm font-medium bg-slate-100 border border-slate-200 text-slate-600 px-3.5 py-1.5 rounded-xl hover:bg-pink-50 hover:text-pink-600 transition">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="text-sm font-medium text-slate-600 hover:text-pink-600 transition px-3 py-1.5">Login</a>
                    <a href="create.php" class="text-sm font-bold sp-btn-primary px-5 py-2.5 !rounded-xl text-sm">
                        Create Free ✨
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">


        <!-- HERO: Full viewport -->
        <section class="min-h-[calc(100vh-4rem)] flex flex-col justify-center items-center text-center px-4 relative">
            <!-- Ambient glows -->
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-pink-500/8 rounded-full blur-[120px] pointer-events-none animate-pulse"></div>
            <div class="absolute bottom-1/3 right-1/4 w-80 h-80 bg-purple-500/6 rounded-full blur-[100px] pointer-events-none"></div>
            
            <div class="relative z-10 max-w-4xl mx-auto py-12">
                <!-- Pill badge -->
                <div class="sp-reveal inline-flex items-center gap-2 bg-pink-500/10 border border-pink-500/25 px-4 py-2 rounded-full text-xs font-bold text-pink-600 uppercase tracking-widest mb-8">
                    ✨ Express Your Feelings Beautifully
                </div>
                
                <!-- Main heading -->
                <h1 class="sp-reveal sp-reveal-delay-1 text-5xl sm:text-7xl font-extrabold font-heading tracking-tight mb-6 leading-[1.05]">
                    <span style="color:#0f172a !important; -webkit-text-fill-color:#0f172a;">Create a</span><br>
                    <span class="sp-gradient-text">Beautiful Love Story</span><br>
                    <span style="color:#0f172a !important; -webkit-text-fill-color:#0f172a;">in 2 Minutes</span>
                </h1>

                <!-- Subtext -->
                <p class="sp-reveal sp-reveal-delay-2 text-lg max-w-2xl mx-auto mb-10 leading-relaxed" style="color:#64748b !important;">
                    Interactive emotional story pages for proposals, birthdays, apologies & more.
                    Share a magical link with music, photos & personal messages.
                </p>

                <!-- CTAs -->
                <div class="sp-reveal sp-reveal-delay-3 flex flex-col sm:flex-row items-center justify-center gap-4 mb-16">
                    <a href="create.php" class="sp-btn-primary text-lg px-10 py-4 w-full sm:w-auto">
                        💌 Create Your Page
                    </a>
                    <a href="premium.php" class="px-10 py-4 rounded-2xl font-semibold w-full sm:w-auto text-center transition"
                       style="background:linear-gradient(135deg,#2d0f1e,#1a0810); border:1px solid rgba(232,64,90,0.4); color:#ffb3c1 !important; box-shadow:0 8px 25px rgba(45,15,30,0.25);">
                        ✨ Explore Premium
                    </a>
                </div>

                <!-- Scroll indicator -->
                <div class="sp-reveal sp-reveal-delay-4 flex flex-col items-center gap-2" style="color:#f472b6;">
                    <span class="text-xs font-semibold uppercase tracking-widest">Scroll to explore</span>
                    <div class="w-5 h-8 border border-pink-400/60 rounded-full flex items-start justify-center p-1">
                        <div class="w-1 h-2 bg-pink-500 rounded-full animate-bounce"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- STATS (appears on scroll) -->
        <section class="max-w-5xl mx-auto px-4 py-12">
            <div class="sp-reveal sp-glass p-8 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="text-3xl font-black sp-gradient-text font-heading"><?= number_format($display_count) ?>+</div>
                    <div class="text-sm text-slate-500 mt-1 font-medium">Pages Created</div>
                </div>
                <div>
                    <div class="text-3xl font-black sp-gradient-text font-heading">2 min</div>
                    <div class="text-sm text-slate-500 mt-1 font-medium">Creation Time</div>
                </div>
                <div>
                    <div class="text-3xl font-black sp-gradient-text font-heading">98.4%</div>
                    <div class="text-sm text-slate-500 mt-1 font-medium">Smile Responses</div>
                </div>
                <div>
                    <div class="text-3xl font-black sp-gradient-text font-heading">Free</div>
                    <div class="text-sm text-slate-500 mt-1 font-medium">Always</div>
                </div>
            </div>
        </section>

        <!-- Feature strip -->
        <section class="max-w-5xl mx-auto px-4 pb-4">
            <div class="sp-reveal grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
                <?php $feats = [['🔗','Just a Link','No app needed — opens anywhere'],['🎵','Music & Voice','Add songs, voice notes & video'],['🔒','Private','Optional password lock'],['💬','They Reply','Get their reaction back']]; foreach ($feats as $ft): ?>
                <div class="bg-white/70 border border-pink-100 rounded-2xl px-4 py-5" style="box-shadow:0 8px 24px rgba(219,39,119,0.05);">
                    <div class="text-2xl mb-2"><?= $ft[0] ?></div>
                    <div class="text-sm font-bold text-slate-800"><?= $ft[1] ?></div>
                    <div class="text-xs mt-1" style="color:#94a3b8 !important;"><?= $ft[2] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php render_ad_banner('adsense_slot_homepage_1'); ?>

        <!-- Categories Grid -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="sp-reveal text-center mb-14">
                <p class="text-xs font-bold uppercase tracking-widest text-pink-400 mb-3">Choose Your Story</p>
                <h2 class="text-4xl font-extrabold font-heading text-slate-900">What Do You Want to Express?</h2>
                <p class="text-slate-500 mt-3 text-base">Pick the emotion that matches your heart right now.</p>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-5">
                <?php $ci = 0; foreach ($categories as $key => $cat): $ci++; ?>
                <div class="sp-reveal sp-glass p-5 sm:p-6 flex flex-col justify-between group cursor-pointer relative overflow-hidden"
                     style="transition-delay: <?= ($ci % 4) * 0.07 ?>s">
                    <!-- Pink glow on hover -->
                    <div class="absolute inset-0 bg-gradient-to-br from-pink-500/0 to-pink-500/0 group-hover:from-pink-500/5 group-hover:to-rose-500/5 transition-all duration-500 rounded-3xl"></div>
                    <div class="relative z-10">
                        <div class="text-3xl mb-4 group-hover:scale-110 transition-transform duration-300 w-fit"><?= h($cat['icon']) ?></div>
                        <h3 class="text-lg font-bold text-slate-900 font-heading mb-2 group-hover:text-pink-600 transition duration-300"><?= h($cat['name']) ?></h3>
                        <p class="text-xs text-slate-500 line-clamp-3 mb-6"><?= h($cat['description']) ?></p>
                    </div>
                    <a href="create.php"
                       class="relative z-10 text-[10px] font-bold text-pink-400 uppercase tracking-wider flex items-center gap-1 group-hover:translate-x-1 transition duration-300">
                        Create Now <span class="text-xs">→</span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- PREMIUM SHOWCASE -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="sp-reveal text-center mb-14">
                <p class="text-xs font-bold uppercase tracking-widest text-pink-600 mb-3">✨ Premium Collection</p>
                <h2 class="text-4xl font-extrabold font-heading">Cinematic Movie-Style Surprises</h2>
                <p class="mt-3 text-base" style="color:#64748b !important;">Multi-scene interactive experiences — like a romantic film, made just for them.</p>
            </div>
            <div class="grid md:grid-cols-2 gap-6 max-w-5xl mx-auto">
                <a href="premium.php" class="sp-reveal block rounded-3xl p-8 relative overflow-hidden group transition hover:-translate-y-1.5 duration-300"
                   style="background:linear-gradient(160deg,#2d0f1e,#1a0810); border:1px solid rgba(232,64,90,0.35); box-shadow:0 20px 50px rgba(45,15,30,0.25);">
                    <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full" style="background:rgba(232,64,90,0.2); filter:blur(40px);"></div>
                    <div class="relative z-10">
                        <div class="text-5xl mb-4">🎂</div>
                        <h3 class="text-2xl font-bold font-heading mb-2" style="color:#ffb3c1 !important;">Girlfriend Birthday Wish</h3>
                        <p class="text-sm leading-relaxed mb-5" style="color:rgba(255,205,215,0.65) !important;">Cake cutting, memories timeline, chats, photo album, voice note, love letter, video & confetti ending.</p>
                        <span class="text-sm font-bold" style="color:#ff8fa3 !important;">Explore →</span>
                    </div>
                </a>
                <a href="premium.php" class="sp-reveal block rounded-3xl p-8 relative overflow-hidden group transition hover:-translate-y-1.5 duration-300" style="background:linear-gradient(160deg,#1a1020,#0a0410); border:1px solid rgba(244,197,107,0.3); box-shadow:0 20px 50px rgba(20,10,25,0.3); transition-delay:0.08s;">
                    <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full" style="background:rgba(244,197,107,0.16); filter:blur(40px);"></div>
                    <div class="relative z-10">
                        <div class="text-5xl mb-4">💍</div>
                        <h3 class="text-2xl font-bold font-heading mb-2" style="color:#f4c56b !important;">Cinematic Proposal</h3>
                        <p class="text-sm leading-relaxed mb-5" style="color:rgba(255,225,190,0.6) !important;">26 movie-like scenes — ring reveal, proposal scene, love meter, puzzle, the big question & celebration.</p>
                        <span class="text-sm font-bold" style="color:#f4c56b !important;">Explore →</span>
                    </div>
                </a>
            </div>
        </section>

        <?php render_ad_banner('adsense_slot_homepage_2'); ?>

        <!-- How It Works -->
        <section id="how-it-works" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 border-t border-pink-500/5">
            <div class="sp-reveal text-center mb-16">
                <p class="text-xs font-bold uppercase tracking-widest text-pink-400 mb-3">Simple Steps</p>
                <h2 class="text-4xl font-extrabold font-heading text-slate-900">How SoulSync Works?</h2>
                <p class="text-slate-500 mt-3 text-base">Make a personalized surprise in three quick steps.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="sp-reveal sp-glass p-8 text-center relative overflow-hidden group">
                    <div class="text-5xl mb-6">1</div>
                    <h3 class="text-xl font-bold text-slate-900 font-heading mb-3">Pick a Category</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Choose Birthday, Proposal, Sorry, or Anniversary template as your base.</p>
                </div>
                <div class="sp-reveal sp-glass p-8 text-center relative overflow-hidden group" style="transition-delay: 0.1s">
                    <div class="text-5xl mb-6">2</div>
                    <h3 class="text-xl font-bold text-slate-900 font-heading mb-3">Add Your Memories</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Write a letter, upload up to 10 photos, a voice note, or background music.</p>
                </div>
                <div class="sp-reveal sp-glass p-8 text-center relative overflow-hidden group" style="transition-delay: 0.2s">
                    <div class="text-5xl mb-6">3</div>
                    <h3 class="text-xl font-bold text-slate-900 font-heading mb-3">Share the Link</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Copy the customized URL and send it to your special someone.</p>
                </div>
            </div>
        </section>

        <!-- Why SoulSync / content section -->
        <section class="max-w-4xl mx-auto px-4 sm:px-6 py-16 border-t border-pink-500/5">
            <div class="sp-reveal text-center mb-10">
                <p class="text-xs font-bold uppercase tracking-widest text-pink-400 mb-3">Why digital greeting pages?</p>
                <h2 class="text-3xl md:text-4xl font-extrabold font-heading text-slate-900">More Personal Than a Card, More Lasting Than a Text</h2>
            </div>
            <div class="sp-reveal space-y-5 text-slate-600 text-[15px] leading-relaxed">
                <p>A birthday text takes ten seconds to write and ten seconds to forget. A paper card gets read once and ends up in a drawer. <?= h(SITE_NAME) ?> was built for the moments in between — when you want to say something that actually <em>feels</em> like you, but you're not a designer, a developer, or a poet.</p>
                <p>Every page you create here is a small interactive experience: your own words revealed line by line, your photos in a gallery your person can zoom into, your voice saying the things that are hard to type, and music playing softly behind it all. The person you send it to doesn't need an account or an app — they just open your link, and the story begins.</p>
                <p>People use <?= h(SITE_NAME) ?> for birthdays and anniversaries, long-overdue apologies, long-distance relationships, proposals they've rehearsed a hundred times, festival wishes for the whole family group, and sometimes just an ordinary Tuesday when someone needs to hear that they matter. The recipient can reply right on the page — with a message, an emoji, or a voice note — so it's a conversation, not a broadcast.</p>
                <p>Your pages stay private by default: only people with the link can see them, and you can add a password or an expiry date. When a page expires, its photos, audio and videos are permanently removed from our servers.</p>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="max-w-4xl mx-auto px-4 sm:px-6 py-16 border-t border-pink-500/5">
            <div class="sp-reveal text-center mb-10">
                <p class="text-xs font-bold uppercase tracking-widest text-pink-400 mb-3">Questions?</p>
                <h2 class="text-3xl md:text-4xl font-extrabold font-heading text-slate-900">Frequently Asked Questions</h2>
            </div>
            <div class="space-y-4 max-w-3xl mx-auto">
                <?php
                $faqs = [
                    ['Is SoulSync free to use?', 'Yes — every new account gets a free credit, which is enough to create and share your first full page with a letter, photos, music and replies. Premium cinematic templates (multi-scene experiences with animations, video and voice) cost 5 credits, and credits are ₹10 each.'],
                    ['Does the person I send it to need an account?', 'No. Your recipient just opens the link — no signup, no app, no login. They can read the page, play the music and voice notes, and send you a reply directly from the page.'],
                    ['What can I put on a page?', 'A personal letter with formatting, up to 30 photos (depending on template), background music from our library or your own upload, voice notes, video messages, chat-style memories, timelines, and interactive elements like a love meter, quizzes and secret passwords — it varies by template.'],
                    ['Who can see my page?', 'Only people who have your link. Pages are never listed publicly or searchable. You can additionally lock a page with a password, and every page has an expiry date after which it is archived and its media files are deleted from our servers.'],
                    ['How long does a page stay live?', 'By default a page stays live for the number of days shown at publish time (typically 10 days). You can extend any page from your dashboard whenever you like.'],
                    ['How do replies work?', 'Every page has an optional reply section. When your special someone reacts — chooses "Yes", writes a message, or sends an emoji or voice reply — it appears instantly in your Dashboard under Replies.'],
                    ['Can I edit a page after publishing?', 'Yes. Open your Dashboard, hit Edit on any page, change anything (text, photos, music, sections) and save — the live link updates instantly, no new link needed.'],
                    ['What payment methods do you support?', 'Credits are purchased securely through Razorpay, which supports UPI, all major debit/credit cards, net-banking and popular wallets. We never see or store your card details.'],
                ];
                foreach ($faqs as $fi => $fq): ?>
                <details class="sp-reveal sp-glass !rounded-2xl px-6 py-4 group" <?= $fi === 0 ? 'open' : '' ?>>
                    <summary class="cursor-pointer font-bold text-slate-800 text-[15px] list-none flex items-center justify-between">
                        <?= h($fq[0]) ?>
                        <span class="text-pink-400 group-open:rotate-45 transition-transform text-xl leading-none">+</span>
                    </summary>
                    <p class="text-sm text-slate-500 leading-relaxed mt-3"><?= h($fq[1]) ?></p>
                </details>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- SoulSync App Banner -->
        <section class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
            <a href="soulsync.php" class="sp-reveal block rounded-3xl p-10 md:p-12 text-center relative overflow-hidden transition hover:-translate-y-1 duration-300"
               style="background:linear-gradient(135deg,#2d0f38,#4a1259); border:1px solid rgba(168,85,247,0.35); box-shadow:0 24px 60px rgba(124,58,237,0.25);">
                <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full blur-[70px]" style="background:rgba(236,72,153,0.35)"></div>
                <div class="relative z-10">
                    <div class="text-5xl mb-4">💜</div>
                    <h3 class="text-3xl font-extrabold font-heading text-white mb-3">Introducing SoulSync</h3>
                    <p class="text-purple-100/80 mb-2 text-base max-w-xl mx-auto">A premium app for couples — private chat, shared moods &amp; notes, memories, couple games, and gentle ways to stay close all day.</p>
                    <p class="text-purple-200/60 mb-7 text-sm">📞 Voice &amp; video calls, voice notes &amp; more — <span class="font-semibold">coming soon</span>.</p>
                    <span class="inline-block bg-gradient-to-r from-purple-500 to-pink-500 text-white font-bold text-base px-10 py-4 rounded-2xl shadow-lg">
                        ⬇ Download SoulSync (Android)
                    </span>
                </div>
            </a>
        </section>

        <!-- Ad slot (home) -->
        <div class="max-w-5xl mx-auto px-4"><?= function_exists('ss_web_ad') ? ss_web_ad('home') : '' ?></div>

        <!-- CTA Banner -->
        <section class="max-w-5xl mx-auto px-4 sm:px-6 py-12">
            <div class="sp-reveal sp-glass p-10 md:p-14 text-center relative overflow-hidden">
                <div class="absolute -top-20 -right-20 w-64 h-64 bg-pink-500/8 rounded-full blur-[60px]"></div>
                <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-rose-500/8 rounded-full blur-[60px]"></div>
                <div class="relative z-10">
                    <div class="text-5xl mb-6">💝</div>
                    <h3 class="text-3xl font-extrabold font-heading text-slate-900 mb-4">Ready to make them smile?</h3>
                    <p class="text-slate-500 mb-8 text-base">Join thousands who've already shared their story.</p>
                    <a href="create.php" class="sp-btn-primary inline-block text-base px-12 py-4">
                        Start Creating — It's Free 🌸
                    </a>
                </div>
            </div>
        </section>



    </main>

    <!-- Footer -->
    <footer style="background: rgba(255,255,255,0.85); border-top: 1px solid rgba(236,72,153,0.08);" class="py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-sm text-slate-500 font-medium">
                © <?= date('Y') ?> <?= h(SITE_NAME) ?> — Made with 💖 for sharing emotions.
            </div>
            <div class="flex gap-6 text-sm text-slate-500 flex-wrap justify-center">
                <a href="soulsync.php" class="hover:text-pink-400 transition">💜 SoulSync App</a>
                <a href="about.php" class="hover:text-pink-400 transition">About</a>
                <a href="contact.php" class="hover:text-pink-400 transition">Contact</a>
                <a href="#faq" class="hover:text-pink-400 transition">FAQ</a>
                <a href="privacy.php" class="hover:text-pink-400 transition">Privacy Policy</a>
                <a href="terms.php" class="hover:text-pink-400 transition">Terms</a>
            </div>
        </div>
    </footer>

    <!-- Emoji Rain + Intersection Observer Scroll Reveal scripts -->
    <script>


        // ═══ SCROLL REVEAL (Intersection Observer) ═══
        (function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            
            document.querySelectorAll('.sp-reveal').forEach(el => observer.observe(el));
            
            // Trigger immediately visible elements
            setTimeout(() => {
                document.querySelectorAll('.sp-reveal').forEach(el => {
                    const rect = el.getBoundingClientRect();
                    if (rect.top < window.innerHeight) el.classList.add('visible');
                });
            }, 100);
        })();
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
    <?php $custom_js = get_setting('custom_js', ''); if ($custom_js !== ''): ?><script><?= $custom_js ?></script><?php endif; ?>
<?= function_exists("ss_web_sticky") ? ss_web_sticky() : "" ?>
<?= function_exists("ss_web_overlays") ? ss_web_overlays() : "" ?>
</body>
</html>
