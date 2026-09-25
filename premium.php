<?php
require_once 'includes/functions.php';

$user = is_logged_in() ? get_user_profile($_SESSION['user_id']) : null;

// Premium templates registry. Add future premium templates here.
$premium_templates = [
    [
        'key'         => 'couple_story',
        'name'        => 'Couple Story',
        'emoji'       => '👑',
        'tagline'     => 'Your own mini love-story website',
        'description' => 'Not a slideshow — a full romantic website: envelope opening, live "together for X days" timer, love timeline, polaroid photo wall (up to 30 photos), videos, voice notes, WhatsApp-style chats, love letters, playlist, love map, promises, auto-unlocking achievements, love meter, secret message, guest book & a starry "Our Journey Never Ends" ending.',
        'features'    => ['⏳ Live together-timer', '📸 30-photo polaroid wall', '💬 WhatsApp-style chats', '💌 Letters, playlist & love map', '🏆 Auto achievements & love meter', '📝 Guest book & 6 themes'],
        'link'        => 'customize-couple.php',
        'badge'       => '👑 Flagship',
    ],
    [
        'key'         => 'cinematic_birthday',
        'name'        => 'Girlfriend Birthday Wish',
        'emoji'       => '🎂',
        'tagline'     => 'A 10-scene cinematic surprise',
        'description' => 'Landing, birthday message, cake cutting, memories timeline, chats, photo album, voice note, love letter, video, secret puzzle, proposal & a confetti ending. Everything is customizable.',
        'features'    => ['🎂 Interactive cake cutting', '📸 Photo album (6 photos)', '🎬 Video message', '🎙️ Voice note', '💌 Love letter', '🎈 Balloons & confetti'],
        'link'        => 'customize-birthday.php',
        'badge'       => 'New ✨',
    ],
    [
        'key'         => 'proposal_cinematic',
        'name'        => 'Cinematic Proposal',
        'emoji'       => '💍',
        'tagline'     => 'A romantic movie-style proposal',
        'description' => 'Welcome, a tap-to-open surprise, love letter, voice note, video, an animated proposal scene, the big question with a playful "no" button, and a confetti celebration with replies. Every page is customizable and can be turned on or off.',
        'features'    => ['🌹 Animated proposal scene', '💌 Typewriter love letter', '🎙️ Voice note', '🎥 Video message', '💍 The big question', '🎉 Celebration & replies'],
        'link'        => 'customize-proposal.php',
        'badge'       => 'New ✨',
    ],
    [
        'key'         => 'sorry_cinematic',
        'name'        => 'Sorry Page',
        'emoji'       => '💔',
        'tagline'     => 'An emotional, elegant apology',
        'description' => 'An interactive emotional story — rain slowly turns to sunshine as a broken heart rebuilds itself piece by piece. Memories, voice note, candlelight apology, butterflies, sparkles on every touch, and a playful "I need more time" button that runs away until only ❤️ Yes remains.',
        'features'    => ['🌧️ Rain → sunshine healing', '💔 Heart rebuilds piece by piece', '🦋 Butterflies & touch sparkles', '🎙️ Voice note & memories', '🕯️ Candlelight apology card', '😄 Runaway button & reply box'],
        'link'        => 'customize-sorry.php',
        'badge'       => 'New ✨',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Premium Pages — <?= h(SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:linear-gradient(160deg,#1a0810 0%,#2d0f1e 55%,#1a0810 100%); color:#f5e6ec; min-height:100vh; }
  h1,h2,h3,.heading { font-family:'Outfit',sans-serif; }
  .ptmpl { transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
  .ptmpl:hover { transform: translateY(-5px); border-color:rgba(232,64,90,0.6); box-shadow:0 24px 55px rgba(232,64,90,0.25); }
  .btn-rose { background:linear-gradient(135deg,#e8405a,#c4184e); color:#fff; border-radius:50px; padding:12px 28px; font-weight:600; display:inline-block; box-shadow:0 8px 24px rgba(232,64,90,0.35); }
  .btn-ghost { border:1px solid rgba(255,150,170,0.35); color:#ffb3c1; border-radius:50px; padding:12px 24px; font-weight:600; display:inline-block; }
</style>
</head>
<body class="flex flex-col">

<header class="sticky top-0 z-50" style="background:rgba(26,8,16,0.9); backdrop-filter:blur(14px); border-bottom:1px solid rgba(232,64,90,0.2);">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="create.php" class="text-sm text-pink-200/70 hover:text-white">‹ Back</a>
    <span class="heading font-extrabold" style="color:#ffb3c1;">Premium Pages ✨</span>
    <?php if ($user): ?><a href="dashboard.php" class="text-sm text-pink-200/60 hover:text-white">Dashboard</a><?php else: ?><span></span><?php endif; ?>
  </div>
</header>

<main class="flex-grow max-w-6xl mx-auto w-full px-4 py-12">
  <div class="text-center mb-12">
    <p class="text-xs font-bold uppercase tracking-widest text-pink-400 mb-3">Premium collection</p>
    <h1 class="text-4xl sm:text-5xl font-extrabold heading" style="color:#ffb3c1;">Cinematic Surprise Pages</h1>
    <p class="text-pink-100/50 mt-3">Multi-scene, animated, fully personal. Choose a template to start.</p>
  </div>

  <div class="grid md:grid-cols-2 gap-6">
    <?php foreach ($premium_templates as $t): ?>
    <div class="ptmpl rounded-3xl p-7 relative overflow-hidden" style="background:rgba(255,255,255,0.04); border:1px solid rgba(232,64,90,0.25);">
      <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full" style="background:rgba(232,64,90,0.18); filter:blur(40px);"></div>
      <div class="relative z-10">
        <div class="flex items-start justify-between">
          <div class="text-5xl mb-4"><?= h($t['emoji']) ?></div>
          <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full" style="background:rgba(232,64,90,0.3); color:#ffb3c1;"><?= h($t['badge']) ?></span>
        </div>
        <h2 class="text-2xl font-bold heading text-white"><?= h($t['name']) ?></h2>
        <p class="text-sm text-pink-300/80 mt-1"><?= h($t['tagline']) ?></p>
        <p class="text-sm text-pink-100/55 leading-relaxed mt-4"><?= h($t['description']) ?></p>
        <div class="grid grid-cols-2 gap-2 mt-5">
          <?php foreach ($t['features'] as $f): ?>
          <div class="text-xs text-pink-100/70 bg-black/20 border border-pink-500/10 rounded-lg px-3 py-2"><?= h($f) ?></div>
          <?php endforeach; ?>
        </div>
        <div class="flex flex-wrap gap-3 mt-6">
          <a href="<?= h($t['link']) ?>" class="btn-rose">Create This 🎁</a>
          <a href="<?= h($t['link']) ?>?demo=1" class="btn-ghost" target="_blank" rel="noopener">Live Preview 👁️</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Coming soon placeholder -->
    <div class="rounded-3xl p-7 flex flex-col items-center justify-center text-center" style="background:rgba(255,255,255,0.02); border:1px dashed rgba(232,64,90,0.25);">
      <div class="text-4xl mb-3 opacity-60">✨</div>
      <h3 class="text-lg font-bold heading text-pink-200/70">More premium templates coming soon</h3>
      <p class="text-sm text-pink-100/40 mt-2">Anniversary, proposal &amp; wedding cinematic pages next.</p>
    </div>
  </div>
</main>

</body>
</html>
