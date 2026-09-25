<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) redirect('login.php');

$pages = tutorial_pages();
$saved_ok = false; $bad = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $out = [];
    foreach ($pages as $k => $label) {
        $url = trim($_POST['tut'][$k] ?? '');
        if ($url !== '' && youtube_id($url) === '') { $bad[] = $label; continue; }
        $out[$k] = $url;
    }
    if (empty($bad)) { set_setting('tutorials', json_encode($out, JSON_UNESCAPED_UNICODE)); $saved_ok = true; }
}
$cur = json_decode(get_setting('tutorials', ''), true);
$cur = is_array($cur) ? $cur : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tutorial Videos — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  .tfld { width:100%; background:#0f172a; border:1px solid #1e293b; border-radius:10px; padding:10px 13px; color:#fff; font-size:0.85rem; outline:none; }
  .tfld:focus { border-color:#ec4899; }
</style>
</head>
<body class="min-h-screen">

<?php $ADMIN_TITLE = 'Tutorial Videos'; include '_nav.php'; ?>

<main class="max-w-3xl mx-auto px-4 py-8">
  <div class="mb-6">
    <h1 class="heading text-2xl font-extrabold text-white">🎥 Tutorial Videos</h1>
    <p class="text-sm text-slate-400 mt-1">Har page type ke create/edit form pe ek <b>"How to create this page?"</b> button dikhta hai. Yahan uska YouTube link daalo — user usse dekh ke sikh sakega. Link khaali chhodo to us page pe button nahi dikhega.</p>
  </div>

  <?php if ($saved_ok): ?><div class="mb-5 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Tutorial links saved!</div><?php endif; ?>
  <?php if ($bad): ?><div class="mb-5 rounded-2xl p-4 text-sm bg-red-500/15 border border-red-500/30 text-red-200">⚠️ In pages ka link galat hai (YouTube URL nahi): <?= h(implode(', ', $bad)) ?>. Nothing was saved.</div><?php endif; ?>

  <form method="post" class="space-y-3">
    <?php foreach ($pages as $k => $label): $set = trim($cur[$k] ?? '') !== ''; ?>
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
      <div class="flex items-center justify-between mb-2">
        <label class="font-bold text-white text-sm"><?= h($label) ?></label>
        <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full <?= $set ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700/40 text-slate-400' ?>"><?= $set ? '● Video set' : '○ No video' ?></span>
      </div>
      <input class="tfld" name="tut[<?= h($k) ?>]" value="<?= h($cur[$k] ?? '') ?>" placeholder="https://youtu.be/xxxxxxxxxxx  (ya full YouTube link)">
    </div>
    <?php endforeach; ?>

    <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-4 text-[13px] text-slate-400">
      💡 Koi bhi YouTube link chalega — <code class="text-pink-300">youtu.be/…</code>, <code class="text-pink-300">youtube.com/watch?v=…</code>, ya Shorts link. Button premium pink pill ke roop me har create/edit page ke bottom-right corner me dikhta hai, aur click pe video popup me chalta hai.
    </div>

    <div class="text-center pt-1">
      <button class="font-bold text-white text-sm px-10 py-3.5 rounded-full bg-gradient-to-r from-pink-500 to-purple-500 shadow-lg shadow-pink-500/25 hover:-translate-y-0.5 transition">💾 Save Tutorial Links</button>
    </div>
  </form>
</main>

</body>
</html>
