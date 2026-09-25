<?php
require_once 'includes/functions.php';

$user = is_logged_in() ? get_user_profile($_SESSION['user_id']) : null;

$festivals = get_festivals();
$statuses  = get_festival_statuses();
$featured_slug = get_featured_festival_slug();

// Featured festival goes to the top (if not disabled)
usort($festivals, function ($a, $b) use ($featured_slug) {
    return ($b['slug'] === $featured_slug) <=> ($a['slug'] === $featured_slug);
});

function fest_status($statuses, $slug) {
    $st = $statuses[$slug] ?? 'enabled';
    return in_array($st, ['enabled', 'coming_soon', 'maintenance', 'disabled']) ? $st : 'enabled';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Festival Wishes — <?= h(SITE_NAME) ?></title>
<meta name="description" content="Create a beautiful animated festival greeting page in 30 seconds — Independence Day, Diwali, Holi, Eid, Christmas & more. Free & shareable.">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:linear-gradient(160deg,#fffbeb 0%,#fff7ed 45%,#fdf2f8 100%); color:#3a2410; min-height:100vh; }
  h1,h2,h3,.heading { font-family:'Outfit',sans-serif; }
  .fcard { transition:transform .3s ease, box-shadow .3s ease; position:relative; overflow:hidden; background:#fff; border:1px solid rgba(217,119,6,0.18); border-radius:24px; box-shadow:0 12px 30px rgba(217,119,6,0.08); }
  .fcard:hover { transform:translateY(-6px); box-shadow:0 24px 50px rgba(217,119,6,0.16); }
  .btn-fest { display:inline-block; background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border-radius:9999px; padding:11px 26px; font-weight:700; font-size:0.85rem; box-shadow:0 8px 20px rgba(217,119,6,0.3); transition:transform .2s; }
  .btn-fest:hover { transform:translateY(-2px); }
  .btn-soon { display:inline-block; background:#f5f0e8; color:#a16207; border-radius:9999px; padding:11px 26px; font-weight:700; font-size:0.85rem; cursor:default; }
  .counter { font-size:.72rem; color:#b45309; background:#fef3c7; border-radius:999px; padding:4px 12px; display:inline-block; }
  @keyframes flame { 0%,100%{ transform:scale(1);} 50%{ transform:scale(1.12);} }
  .trending { animation:flame 1.4s ease-in-out infinite; display:inline-block; }
</style>
</head>
<body class="flex flex-col">

<header class="sticky top-0 z-50" style="background:rgba(255,251,235,0.9); backdrop-filter:blur(16px); border-bottom:1px solid rgba(217,119,6,0.15);">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="create.php" class="text-sm font-medium text-amber-700 hover:text-amber-900">‹ Back</a>
    <span class="heading font-extrabold text-lg text-amber-800">🎉 Festival Wishes</span>
    <?php if ($user): ?><a href="dashboard.php" class="text-sm font-medium text-amber-700 hover:text-amber-900">Dashboard</a><?php else: ?><span></span><?php endif; ?>
  </div>
</header>

<main class="flex-grow max-w-6xl mx-auto w-full px-4 py-12">
  <div class="text-center mb-10">
    <p class="text-xs font-bold uppercase tracking-widest text-amber-600 mb-3">Free · No editor · Ready in 30 seconds</p>
    <h1 class="text-4xl sm:text-5xl font-extrabold heading text-amber-900">Send a Festive Greeting Page</h1>
    <p class="text-amber-700/70 mt-3">Pick a festival → watch a beautiful animated story → add your name → share the link. That's it! 🎁</p>
  </div>

  <?php
  // ── FEATURED CARD ──
  $feat = null;
  foreach ($festivals as $f) { if ($f['slug'] === $featured_slug && fest_status($statuses, $f['slug']) !== 'disabled') { $feat = $f; break; } }
  if ($feat): $fst = fest_status($statuses, $feat['slug']); ?>
  <div class="fcard p-8 md:p-10 mb-8 text-center" style="background:<?= $feat['bg'] ?>; border:1px solid rgba(255,255,255,0.15);">
    <span class="absolute top-4 right-4 text-[11px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-full trending" style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3);">🔥 Trending</span>
    <div class="relative z-10">
      <div class="text-6xl mb-4"><?= h($feat['emoji']) ?></div>
      <h2 class="text-3xl font-extrabold heading text-white mb-2"><?= h($feat['name']) ?> <span class="text-sm font-bold align-middle px-2 py-1 rounded-full" style="background:rgba(255,255,255,0.15); color:#fff;">Featured</span></h2>
      <p class="text-sm leading-relaxed mb-3 max-w-xl mx-auto" style="color:rgba(255,255,255,0.75);"><?= h($feat['desc']) ?></p>
      <p class="counter mb-5" style="background:rgba(255,255,255,0.15); color:#fff;">❤️ <?= number_format(festival_counter($feat['slug'])) ?> pages created today</p>
      <div>
        <?php if ($fst === 'enabled'): ?>
          <a href="f.php?fest=<?= h($feat['slug']) ?>" class="btn-fest" style="background:linear-gradient(135deg,<?= h($feat['accent']) ?>,<?= h($feat['accent2']) ?>); font-size:1rem; padding:14px 36px;">✨ Create in 30 Seconds</a>
        <?php elseif ($fst === 'maintenance'): ?>
          <span class="btn-soon">🛠️ Under Maintenance</span>
        <?php else: ?>
          <span class="btn-soon">Coming Soon ✨</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── GRID ── -->
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($festivals as $f):
        if ($f['slug'] === $featured_slug) continue; // already shown on top
        $status = fest_status($statuses, $f['slug']);
        if ($status === 'disabled') continue;
    ?>
    <div class="fcard p-7 text-center<?= $status === 'maintenance' ? ' opacity-80' : '' ?>">
      <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full" style="background:rgba(244,197,107,0.35); filter:blur(20px);"></div>
      <div class="relative z-10">
        <div class="text-5xl mb-3"><?= h($f['emoji']) ?></div>
        <h2 class="text-xl font-bold heading text-amber-900 mb-2"><?= h($f['name']) ?></h2>
        <p class="text-sm text-amber-700/70 leading-relaxed mb-3"><?= h($f['desc']) ?></p>
        <p class="counter mb-5">❤️ <?= number_format(festival_counter($f['slug'])) ?> today</p>
        <div>
          <?php if ($status === 'enabled'): ?>
            <a href="f.php?fest=<?= h($f['slug']) ?>" class="btn-fest">✨ Create in 30 Seconds</a>
          <?php elseif ($status === 'maintenance'): ?>
            <span class="btn-soon" style="background:#fef3c7; color:#92400e;">🛠️ Under Maintenance</span>
          <?php else: ?>
            <span class="btn-soon">Coming Soon ✨</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- How it works (content + trust) -->
  <div class="mt-14 grid sm:grid-cols-4 gap-4 text-center">
    <?php $steps = [['1️⃣','Pick a festival','Choose from the cards above'],['2️⃣','Watch the story','3 beautiful animated slides'],['3️⃣','Add your name','And who it\'s for (optional)'],['4️⃣','Share the link','WhatsApp, Instagram, Telegram']]; foreach ($steps as $s): ?>
    <div class="bg-white/80 border border-amber-200/60 rounded-2xl p-5">
      <div class="text-2xl mb-2"><?= $s[0] ?></div>
      <div class="text-sm font-bold text-amber-900"><?= h($s[1]) ?></div>
      <div class="text-xs text-amber-700/60 mt-1"><?= h($s[2]) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- small banner on this content-rich listing page only -->
  <div class="mt-10 flex justify-center"><?php render_ad_banner('adsense_slot_festival_list'); ?></div>

  <div class="text-center mt-10">
    <p class="text-sm text-amber-700/60">Want something more personal? <a href="create-page.php" class="font-bold text-amber-700 underline hover:text-amber-900">Browse all categories</a> or <a href="premium.php" class="font-bold text-amber-700 underline hover:text-amber-900">go Premium ✨</a></p>
  </div>
</main>

</body>
</html>
