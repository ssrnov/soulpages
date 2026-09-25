<?php
require_once 'includes/functions.php';
require_once 'includes/invitations.php';

$user = is_logged_in() ? get_user_profile($_SESSION['user_id']) : null;
$templates = inv_templates(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Invitation Maker - <?= h(SITE_NAME) ?></title>
<meta name="description" content="Create beautiful animated video invitations — wedding, birthday, engagement — customise names, date, venue & photos, then download as video.">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#ffffff 0%,#fdf2f8 50%,#f1f5f9 100%); color:#0f172a; min-height:100vh; }
  h1,h2,h3,.heading { font-family:'Outfit',sans-serif; }
  .sp-gradient-text { background:linear-gradient(135deg,#be185d 0%,#db2777 40%,#e11d48 70%,#d97706 100%); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; }
  .tcard { transition:transform .25s ease, box-shadow .25s ease; }
  .tcard:hover { transform:translateY(-5px); box-shadow:0 22px 50px rgba(219,39,119,0.15); }
</style>
</head>
<body class="flex flex-col">

<header class="sticky top-0 z-50" style="background:rgba(255,255,255,0.85); backdrop-filter:blur(20px); border-bottom:1px solid rgba(236,72,153,0.12);">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="index.php" class="text-2xl font-extrabold heading sp-gradient-text">‹ <?= h(SITE_NAME) ?></a>
    <?php if ($user): ?><a href="dashboard.php" class="text-sm font-medium text-slate-600 hover:text-pink-600">Dashboard</a><?php else: ?><a href="login.php" class="text-sm font-medium text-slate-600 hover:text-pink-600">Login</a><?php endif; ?>
  </div>
</header>

<main class="flex-grow max-w-6xl mx-auto w-full px-4 py-12">
  <div class="text-center mb-12">
    <p class="text-xs font-bold uppercase tracking-widest text-pink-600 mb-3">Invitation Studio 🎬</p>
    <h1 class="text-4xl sm:text-5xl font-extrabold heading text-slate-900">Animated Video Invitations</h1>
    <p class="text-slate-500 mt-3">Pick a template, add your names, date, venue &amp; photos — download a ready-to-share video in one minute.</p>
  </div>

  <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-6">
    <?php foreach ($templates as $t): ?>
    <div class="tcard rounded-3xl bg-white border border-pink-100 overflow-hidden" style="box-shadow:0 14px 36px rgba(219,39,119,0.07);">
      <div class="relative flex items-center justify-center" style="aspect-ratio:9/12; background:linear-gradient(160deg,<?= h($t['colors']['bg1'] ?? '#160f04') ?>,<?= h($t['colors']['bg2'] ?? '#3a2a0a') ?>);">
        <?php if (!empty($t['thumbnail']) && file_exists($t['thumbnail'])): ?>
          <img src="<?= h($t['thumbnail']) ?>" class="absolute inset-0 w-full h-full object-cover" alt="">
        <?php else: ?>
          <div class="text-center px-6">
            <div class="text-6xl mb-3"><?= h($t['emoji'] ?? '💌') ?></div>
            <div class="font-extrabold heading text-xl" style="color:<?= h($t['colors']['accent'] ?? '#eab308') ?>;"><?= h($t['name']) ?></div>
          </div>
        <?php endif; ?>
        <div class="absolute top-3 left-3 flex gap-1.5">
          <?php if (!empty($t['featured'])): ?><span class="text-[9px] font-bold bg-amber-400 text-amber-950 px-2 py-0.5 rounded-full">⭐ FEATURED</span><?php endif; ?>
          <?php if (!empty($t['trending'])): ?><span class="text-[9px] font-bold bg-red-500 text-white px-2 py-0.5 rounded-full">🔥 TRENDING</span><?php endif; ?>
          <?php if (!empty($t['new_badge'])): ?><span class="text-[9px] font-bold bg-emerald-500 text-white px-2 py-0.5 rounded-full">✨ NEW</span><?php endif; ?>
        </div>
      </div>
      <div class="p-5">
        <div class="flex items-center justify-between">
          <h2 class="font-bold heading text-slate-900"><?= h($t['name']) ?></h2>
          <span class="text-[10px] font-bold bg-pink-50 text-pink-600 border border-pink-200 px-2 py-1 rounded-full"><?= (int)($t['credit_cost'] ?? 1) ?> Credit<?= (int)($t['credit_cost'] ?? 1) === 1 ? '' : 's' ?></span>
        </div>
        <p class="text-xs text-slate-500 mt-1.5 leading-relaxed"><?= h($t['description'] ?? '') ?></p>
        <p class="text-[10px] text-slate-400 mt-2"><?= h($t['category'] ?? '') ?> · <?= (int)($t['duration'] ?? 30) ?> sec · <?= h($t['aspect'] ?? '9:16') ?> · Video download</p>
        <a href="invite-edit.php?id=<?= h($t['id']) ?>" class="mt-4 block text-center font-bold text-white text-sm py-3 rounded-xl" style="background:linear-gradient(135deg,#db2777,#e11d48); box-shadow:0 8px 20px rgba(219,39,119,0.25);">Customize &amp; Preview 🎬</a>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="rounded-3xl p-8 flex flex-col items-center justify-center text-center border border-dashed border-pink-300 bg-white/50">
      <div class="text-4xl mb-3 opacity-60">✨</div>
      <h3 class="font-bold heading text-slate-600">More templates coming soon</h3>
      <p class="text-xs text-slate-400 mt-2">Birthday, engagement, housewarming &amp; festival invites.</p>
    </div>
  </div>
</main>

<footer style="background:rgba(255,255,255,0.85); border-top:1px solid rgba(236,72,153,0.08);" class="py-8 mt-12">
  <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="text-sm text-slate-500 font-medium">© <?= date('Y') ?> <?= h(SITE_NAME) ?> — Made with 💖 for sharing emotions.</div>
    <div class="flex gap-6 text-sm text-slate-500"><a href="about.php" class="hover:text-pink-400">About</a><a href="contact.php" class="hover:text-pink-400">Contact</a><a href="privacy.php" class="hover:text-pink-400">Privacy</a><a href="terms.php" class="hover:text-pink-400">Terms</a></div>
  </div>
</footer>
</body>
</html>
