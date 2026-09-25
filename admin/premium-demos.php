<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Registry of premium templates that have an admin-curated demo.
// Keep in sync with premium.php. Each demo config is stored in site_settings
// under key "premium_demo_<key>" and edited via customize-birthday.php?admin_demo=<key>.
$premium_templates = [
    [
        'key'         => 'couple_story',
        'name'        => 'Couple Story (Flagship)',
        'emoji'       => '👑',
        'description' => 'Mini love-story website — envelope intro, live together-timer, timeline, polaroid wall, chats, letters, playlist, love map, achievements, love meter, secret message, guest book & starry ending.',
        'editor'      => '../customize-couple.php?admin_demo=couple_story',
        'view'        => '../customize-couple.php?demo=1',
    ],
    [
        'key'         => 'cinematic_birthday',
        'name'        => 'Girlfriend Birthday Wish',
        'emoji'       => '🎂',
        'description' => 'Cinematic 10-scene surprise — cake cutting, memories, chats, album, video, voice, letter, puzzle, proposal.',
        'editor'      => '../customize-birthday.php?admin_demo=cinematic_birthday',
        'view'        => '../customize-birthday.php?demo=1',
    ],
    [
        'key'         => 'proposal_cinematic',
        'name'        => 'Cinematic Proposal',
        'emoji'       => '💍',
        'description' => 'Movie-style proposal — welcome, surprise gift, love letter, voice, video, proposal scene, the big question (playful no), celebration & replies.',
        'editor'      => '../customize-proposal.php?admin_demo=proposal_cinematic',
        'view'        => '../customize-proposal.php?demo=1',
    ],
    [
        'key'         => 'sorry_cinematic',
        'name'        => 'Sorry Page',
        'emoji'       => '💔',
        'description' => 'Interactive emotional story — rain→sunshine, heart rebuilds piece by piece, butterflies, sparkles, candlelight apology, runaway "more time" button & reply box.',
        'editor'      => '../customize-sorry.php?admin_demo=sorry_cinematic',
        'view'        => '../customize-sorry.php?demo=1',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Premium Demos — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
</style>
</head>
<body class="min-h-screen">

<?php $ADMIN_TITLE = 'Premium Demos'; include '_nav.php'; ?>

<main class="max-w-6xl mx-auto px-4 py-8">
  <div class="mb-6">
    <h1 class="heading text-2xl font-extrabold text-white">Premium Page Demos</h1>
    <p class="text-sm text-slate-400 mt-1">Customize one demo per premium template here. This demo is shown to users in "Live Preview" so they can decide whether to buy the page.</p>
  </div>

  <div class="grid md:grid-cols-2 gap-5">
    <?php foreach ($premium_templates as $t):
        $saved = get_setting('premium_demo_' . $t['key'], '');
        $has_demo = !empty($saved);
    ?>
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
      <div class="flex items-start justify-between">
        <div class="text-4xl mb-3"><?= h($t['emoji']) ?></div>
        <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full <?= $has_demo ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700/40 text-slate-400' ?>">
          <?= $has_demo ? '● Demo set' : '○ Using default' ?>
        </span>
      </div>
      <h2 class="heading text-lg font-bold text-white"><?= h($t['name']) ?></h2>
      <p class="text-sm text-slate-400 mt-1 leading-relaxed"><?= h($t['description']) ?></p>
      <div class="flex flex-wrap gap-2 mt-5">
        <a href="<?= h($t['editor']) ?>" class="text-xs font-bold bg-gradient-to-r from-pink-500 to-purple-500 text-white px-4 py-2.5 rounded-xl hover:-translate-y-0.5 transition">✏️ Customize Demo</a>
        <a href="<?= h($t['view']) ?>" target="_blank" class="text-xs font-semibold bg-slate-800 border border-slate-700 text-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-700 transition">👁️ View Live Demo</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>

</body>
</html>
