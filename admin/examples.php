<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) redirect('login.php');

$defs = example_defaults();
$saved_ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $out = [];
    foreach ($defs as $k => $dv) {
        $v = trim($_POST['ex'][$k] ?? '');
        $out[$k] = ($v !== '') ? $v : $dv;
    }
    set_setting('example_texts', json_encode($out, JSON_UNESCAPED_UNICODE));
    $saved_ok = true;
}
// reload current values
$cur = json_decode(get_setting('example_texts', ''), true);
$cur = array_replace($defs, is_array($cur) ? $cur : []);

// Grouped for a friendlier UI
$groups = [
    'Names (all create forms)' => [
        'sender'   => ['Your Name (sender)', 'e.g. Sny ☀️'],
        'receiver' => ['Their Name (receiver)', 'e.g. Shravni'],
        'nickname' => ['Nickname hint', 'e.g. Baby, Bestie, Champ'],
    ],
    'Proposal / Interactive' => [
        'question' => ['Question example', 'e.g. Will you be mine?'],
        'yes_text' => ['Yes button example', 'e.g. Yes! ❤️'],
        'no_text'  => ['No button example', 'e.g. No'],
    ],
    'Festival' => [
        'fest_name' => ['Festival name example', 'e.g. Shravni'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Example Texts — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  .efld { width:100%; background:#0f172a; border:1px solid #1e293b; border-radius:10px; padding:9px 12px; color:#fff; font-size:0.85rem; outline:none; }
  .efld:focus { border-color:#ec4899; }
</style>
</head>
<body class="min-h-screen">

<?php $ADMIN_TITLE = 'Example Texts'; include '_nav.php'; ?>

<main class="max-w-3xl mx-auto px-4 py-8">
  <div class="mb-6">
    <h1 class="heading text-2xl font-extrabold text-white">✍️ Example / Placeholder Texts</h1>
    <p class="text-sm text-slate-400 mt-1">Ye woh example text hai jo create forms ke boxes me faded hint ke roop me dikhta hai (jaise "e.g. Shravni"). Yahan se badlo — poori site pe apply ho jayega.</p>
  </div>

  <?php if ($saved_ok): ?><div class="mb-5 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Example texts saved!</div><?php endif; ?>

  <form method="post" class="space-y-6">
    <?php foreach ($groups as $gname => $keys): ?>
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
      <h2 class="heading font-bold text-white mb-4"><?= h($gname) ?></h2>
      <div class="grid sm:grid-cols-2 gap-4">
        <?php foreach ($keys as $k => $meta): ?>
        <div>
          <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5"><?= h($meta[0]) ?></label>
          <input class="efld" name="ex[<?= h($k) ?>]" value="<?= h($cur[$k]) ?>" placeholder="<?= h($meta[1]) ?>">
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-5 text-sm text-slate-400">
      <p class="font-semibold text-slate-300 mb-1">💡 Bade content examples (poore letter, apology text, timeline etc.) kahan badlein?</p>
      <ul class="list-disc pl-5 space-y-1 text-[13px]">
        <li><b>Premium pages</b> (Birthday / Proposal / Sorry / Couple) ke poore example content → <a href="premium-demos.php" class="text-pink-400 hover:underline">✨ Premium Demos</a></li>
        <li><b>Normal page</b> categories ka default content → <a href="categories.php" class="text-pink-400 hover:underline">🗂️ Categories</a></li>
        <li><b>Festival</b> ka text & poem → <a href="festivals.php" class="text-pink-400 hover:underline">🪔 Festivals</a></li>
        <li><b>Invitation</b> templates ka poora example content (bride/groom/venue naam, event dates, kaunse events dikhein) → <a href="invitations.php" class="text-pink-400 hover:underline">💌 Invites</a> me har template ke neeche <b>"✏️ Edit Example Content"</b></li>
      </ul>
    </div>

    <div class="text-center">
      <button class="font-bold text-white text-sm px-10 py-3.5 rounded-full bg-gradient-to-r from-pink-500 to-purple-500 shadow-lg shadow-pink-500/25 hover:-translate-y-0.5 transition">💾 Save Example Texts</button>
    </div>
  </form>
</main>

</body>
</html>
