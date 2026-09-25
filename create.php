<?php
require_once 'includes/functions.php';

$user = is_logged_in() ? get_user_profile($_SESSION['user_id']) : null;
$site_title = 'Create a Page — ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($site_title) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;600;800&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#ffffff 0%,#fdf2f8 45%,#f1f5f9 100%); color:#0f172a; min-height:100vh; }
  h1,h2,h3,.heading { font-family:'Outfit',sans-serif; }
  .choice { transition: transform .3s ease, box-shadow .3s ease; position:relative; overflow:hidden; }
  .choice:hover { transform: translateY(-8px); }
  .sp-gradient-text { background:linear-gradient(135deg,#be185d 0%,#db2777 40%,#e11d48 70%,#d97706 100%); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; }
  .tag { display:inline-block; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; padding:4px 12px; border-radius:9999px; margin-bottom:14px; }
</style>
</head>
<body class="flex flex-col">

<header class="sticky top-0 z-50" style="background:rgba(255,255,255,0.85); backdrop-filter:blur(20px); border-bottom:1px solid rgba(236,72,153,0.12);">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="index.php" class="text-2xl font-extrabold font-heading sp-gradient-text">‹ <?= h(SITE_NAME) ?></a>
    <?php if ($user): ?><a href="dashboard.php" class="text-sm font-medium text-slate-600 hover:text-pink-600">Dashboard</a><?php endif; ?>
  </div>
</header>

<main class="flex-grow flex flex-col items-center justify-center px-4 py-14">
  <div class="text-center mb-12">
    <p class="text-xs font-bold uppercase tracking-widest text-pink-600 mb-3">Let's begin ✨</p>
    <h1 class="text-4xl sm:text-5xl font-extrabold heading">What do you want to create?</h1>
    <p class="text-slate-500 mt-3 text-base">A quick category page, a cinematic premium surprise, or a festive greeting.</p>
  </div>

  <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 w-full max-w-6xl">
    <!-- NORMAL -->
    <a href="create-page.php" class="choice block rounded-3xl p-8 bg-white text-center border border-pink-100" style="box-shadow:0 16px 40px rgba(219,39,119,0.08);">
      <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-pink-100/60" style="filter:blur(24px);"></div>
      <div class="relative z-10">
        <div class="text-5xl mb-5">💌</div>
        <span class="tag" style="background:#fdf2f8; color:#db2777;">1 Credit</span>
        <h2 class="text-2xl font-bold heading mb-2">Normal Page</h2>
        <p class="text-sm text-slate-500 leading-relaxed mb-6">Proposal, birthday, anniversary, sorry &amp; more — pick a ready-made category and build it in 2 minutes.</p>
        <span class="inline-block sp-gradient-text font-bold text-sm">Choose a Category →</span>
      </div>
    </a>

    <!-- PREMIUM -->
    <a href="premium.php" class="choice block rounded-3xl p-8 text-center text-white" style="background:linear-gradient(160deg,#2d0f1e,#1a0810); border:1px solid rgba(232,64,90,0.4); box-shadow:0 20px 50px rgba(45,15,30,0.3);">
      <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full" style="background:rgba(232,64,90,0.22); filter:blur(40px);"></div>
      <div class="relative z-10">
        <div class="text-5xl mb-5">🎬</div>
        <span class="tag" style="background:rgba(232,64,90,0.25); color:#ffb3c1;">Premium ✨ · 5 Credits</span>
        <h2 class="text-2xl font-bold heading mb-2" style="color:#ffb3c1;">Premium Pages</h2>
        <p class="text-sm leading-relaxed mb-6" style="color:rgba(255,205,215,0.65);">Full cinematic multi-scene experiences — cake cutting, ring reveal, photo album, video, voice &amp; more.</p>
        <span class="inline-block font-bold text-sm" style="color:#ff8fa3;">Explore Premium →</span>
      </div>
    </a>

    <!-- FESTIVAL -->
    <a href="festival.php" class="choice block rounded-3xl p-8 text-center" style="background:linear-gradient(160deg,#fff7ed,#fef3c7); border:1px solid rgba(217,119,6,0.25); box-shadow:0 16px 40px rgba(217,119,6,0.12);">
      <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full" style="background:rgba(244,197,107,0.5); filter:blur(24px);"></div>
      <div class="relative z-10">
        <div class="text-5xl mb-5">🪔</div>
        <span class="tag" style="background:rgba(217,119,6,0.12); color:#b45309;">Festive 🎉</span>
        <h2 class="text-2xl font-bold heading mb-2" style="color:#92400e;">Festival Pages</h2>
        <p class="text-sm leading-relaxed mb-6" style="color:#a16207;">Diwali, Holi, Raksha Bandhan, Eid, Christmas, New Year — send a beautiful festive greeting page.</p>
        <span class="inline-block font-bold text-sm" style="color:#d97706;">Pick a Festival →</span>
      </div>
    </a>

    <!-- VIDEO INVITATIONS -->
    <a href="invitations.php" class="choice block rounded-3xl p-8 text-center text-white" style="background:linear-gradient(160deg,#160f04,#3a2a0a); border:1px solid rgba(234,179,8,0.4); box-shadow:0 20px 50px rgba(58,42,10,0.3);">
      <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full" style="background:rgba(234,179,8,0.22); filter:blur(40px);"></div>
      <div class="relative z-10">
        <div class="text-5xl mb-5">🎬</div>
        <span class="tag" style="background:rgba(234,179,8,0.22); color:#fde68a;">Video · 1 Credit</span>
        <h2 class="text-2xl font-bold heading mb-2" style="color:#fde68a;">Video Invitations</h2>
        <p class="text-sm leading-relaxed mb-6" style="color:rgba(253,230,138,0.6);">Animated wedding &amp; event invites — add names, date, venue, photos &amp; music, download as a video.</p>
        <span class="inline-block font-bold text-sm" style="color:#facc15;">Open Invitation Studio →</span>
      </div>
    </a>
  </div>

  <!-- Nothing here — grid closes above -->
  <a href="index.php" class="mt-10 text-sm text-slate-400 hover:text-pink-500">← Back to home</a>
</main>

</body>
</html>
