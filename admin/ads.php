<?php
require_once '../includes/functions.php';
if (!is_logged_in() || !is_admin()) redirect('login.php');

// Every ad slot in the product, with a human label + the page it appears on.
$slots = [
    'adsense_slot_homepage_1'     => ['Homepage — after stats',        'index.php'],
    'adsense_slot_homepage_2'     => ['Homepage — after premium row',  'index.php'],
    'adsense_slot_published_top'  => ['Published card — top',          'p.php'],
    'adsense_slot_published_bottom' => ['Published card — bottom',     'p.php'],
    'adsense_slot_festival_list'  => ['Festival listing — bottom',     'festival.php'],
    'adsense_slot_festival_share' => ['Festival share screen',         'f.php'],
];

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_setting('enable_ads', isset($_POST['enable_ads']) ? '1' : '0');
    set_setting('adsense_publisher_id', trim($_POST['pub_id'] ?? ''));
    set_setting('adsense_test_mode', isset($_POST['test_mode']) ? '1' : '0');
    foreach ($slots as $key => $meta) {
        set_setting('ad_off_' . $key, isset($_POST['slot_on'][$key]) ? '0' : '1');
        set_setting($key, trim($_POST['slot_id'][$key] ?? ''));
    }
    $saved = true;
}

$enable_ads = get_setting('enable_ads', '1');
$pub_id = get_setting('adsense_publisher_id', '');
$test_mode = get_setting('adsense_test_mode', '0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ads — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  .pfld { background:#0f172a; border:1px solid #334155; color:#fff; border-radius:10px; padding:9px 12px; font-size:0.85rem; outline:none; width:100%; }
  .pfld:focus { border-color:#ec4899; }
</style>
</head>
<body class="min-h-screen">
<?php $ADMIN_TITLE = 'Ad Placement Manager'; include '_nav.php'; ?>

<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="mb-6">
    <h1 class="heading text-2xl font-extrabold text-white">📢 Ad Placement Manager</h1>
    <p class="text-sm text-slate-400 mt-1">Master switch, AdSense settings, and per-slot on/off — no code changes needed.</p>
  </div>

  <?php if ($saved): ?><div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Ad settings saved.</div><?php endif; ?>

  <div class="mb-6 rounded-2xl p-4 text-xs leading-relaxed" style="background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3); color:#fcd34d;">
    ⚠️ <b>AdSense policy reminder:</b> ads are intentionally NOT placed on login, loading, or "generating" screens — Google flags those as "screens without publisher content". Keep it that way to pass review.
  </div>

  <form method="post">
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6 mb-6">
      <h2 class="heading text-base font-bold text-white mb-4">Global</h2>
      <div class="grid sm:grid-cols-3 gap-4 items-end">
        <label class="flex items-center gap-3 text-sm text-slate-300 cursor-pointer"><input type="checkbox" name="enable_ads" <?= $enable_ads === '1' ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500"> <b>Master switch</b> — ads on/off sitewide</label>
        <div><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">AdSense Publisher ID</label><input class="pfld" name="pub_id" value="<?= h($pub_id) ?>" placeholder="ca-pub-XXXXXXXXXXXXXXXX"></div>
        <label class="flex items-center gap-3 text-sm text-slate-300 cursor-pointer"><input type="checkbox" name="test_mode" <?= $test_mode === '1' ? 'checked' : '' ?> class="w-5 h-5 accent-amber-500"> Test mode (data-adtest)</label>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 overflow-hidden mb-6">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-800 text-left text-[11px] uppercase tracking-wider text-slate-500">
            <th class="px-5 py-3">Placement</th>
            <th class="px-5 py-3">Page</th>
            <th class="px-5 py-3 w-56">Ad Slot ID</th>
            <th class="px-5 py-3 w-24 text-center">On</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($slots as $key => $meta): $off = get_setting('ad_off_' . $key, '0') === '1'; ?>
          <tr class="border-b border-slate-800/60 hover:bg-slate-800/20">
            <td class="px-5 py-4 font-semibold text-white"><?= h($meta[0]) ?></td>
            <td class="px-5 py-4 text-slate-500 text-xs"><?= h($meta[1]) ?></td>
            <td class="px-5 py-4"><input class="pfld" name="slot_id[<?= $key ?>]" value="<?= h(get_setting($key, '')) ?>" placeholder="1234567890"></td>
            <td class="px-5 py-4 text-center"><input type="checkbox" name="slot_on[<?= $key ?>]" <?= !$off ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500"></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="text-center"><button type="submit" class="bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold text-sm px-8 py-3 rounded-xl shadow-lg shadow-pink-500/20 transition hover:-translate-y-0.5">💾 Save Ad Settings</button></div>
  </form>
</main>
</body>
</html>
