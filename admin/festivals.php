<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$festivals = get_festivals();
$saved = false;

// Save statuses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['statuses']) && is_array($_POST['statuses'])) {
    $allowed = ['enabled', 'coming_soon', 'maintenance', 'disabled'];
    $out = [];
    foreach ($festivals as $f) {
        $st = $_POST['statuses'][$f['slug']] ?? 'enabled';
        $out[$f['slug']] = in_array($st, $allowed) ? $st : 'enabled';
    }
    set_setting('festival_statuses', json_encode($out));
    // Festival page lifespan (days) — festival pages are free & viral, short-lived
    if (isset($_POST['festival_expiry_days'])) {
        set_setting('festival_expiry_days', (string)max(1, (int)$_POST['festival_expiry_days']));
    }
    $saved = true;
}
$festival_expiry_days = max(1, (int)get_setting('festival_expiry_days', 1));

$statuses = get_festival_statuses();
$db_categories = get_categories(true); // include inactive, keyed by slug

$status_meta = [
    'enabled'     => ['label' => '✅ Enabled',            'chip' => 'bg-emerald-500/20 text-emerald-300'],
    'coming_soon' => ['label' => '✨ Coming Soon',        'chip' => 'bg-amber-500/20 text-amber-300'],
    'maintenance' => ['label' => '🛠️ Maintenance',        'chip' => 'bg-orange-500/20 text-orange-300'],
    'disabled'    => ['label' => '🚫 Disabled (hidden)',  'chip' => 'bg-slate-600/40 text-slate-400'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Festivals — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  select.status-sel { background:#0f172a; border:1px solid #334155; color:#fff; border-radius:10px; padding:8px 12px; font-size:0.82rem; outline:none; }
  select.status-sel:focus { border-color:#ec4899; }
</style>
</head>
<body class="min-h-screen">

<?php $ADMIN_TITLE = 'Festivals'; include '_nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 pt-4 flex justify-end"><a href="../festival.php" target="_blank" class="text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 px-3.5 py-2 rounded-xl hover:bg-slate-800 hover:text-white transition">View Live Page ↗</a></div>

<main class="max-w-6xl mx-auto px-4 py-8">
  <div class="mb-6">
    <h1 class="heading text-2xl font-extrabold text-white">Festival Pages Control</h1>
    <p class="text-sm text-slate-400 mt-1">Set each festival's status on the public Festival page: <b>Enabled</b> (Create Now), <b>Coming Soon</b>, <b>Maintenance</b>, or <b>Disabled</b> (card hidden).</p>
  </div>

  <?php if ($saved): ?>
    <div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Saved! The festival page is updated.</div>
  <?php endif; ?>

  <form method="post">
    <!-- Festival page lifespan -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5 mb-5 flex flex-wrap items-center gap-4">
      <div>
        <label class="block text-sm font-bold text-white">⏳ Festival page lifespan</label>
        <p class="text-xs text-slate-500 mt-0.5">Festival pages free & viral hain — kitne din baad expire ho, yahan set karo. Currently: <b class="text-pink-400"><?= (int)$festival_expiry_days ?> day<?= $festival_expiry_days == 1 ? '' : 's' ?></b>.</p>
      </div>
      <div class="ml-auto flex items-center gap-2">
        <input type="number" name="festival_expiry_days" min="1" max="365" value="<?= (int)$festival_expiry_days ?>" class="w-20 bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-center text-white outline-none focus:border-pink-500">
        <span class="text-sm text-slate-400">day(s)</span>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-800 text-left text-[11px] uppercase tracking-wider text-slate-500">
            <th class="px-5 py-3">Festival</th>
            <th class="px-5 py-3">Current</th>
            <th class="px-5 py-3">Experience</th>
            <th class="px-5 py-3">Set Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($festivals as $f):
              $st = $statuses[$f['slug']] ?? 'enabled';
              if (!isset($status_meta[$st])) $st = 'enabled';
          ?>
          <tr class="border-b border-slate-800/60 hover:bg-slate-800/20">
            <td class="px-5 py-4">
              <span class="text-xl mr-2"><?= h($f['emoji']) ?></span>
              <span class="font-semibold text-white"><?= h($f['name']) ?></span>
              <span class="text-[10px] text-slate-500 ml-2"><?= h($f['slug']) ?></span>
            </td>
            <td class="px-5 py-4">
              <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full <?= $status_meta[$st]['chip'] ?>"><?= $status_meta[$st]['label'] ?></span>
            </td>
            <td class="px-5 py-4">
              <span class="text-emerald-400 text-xs font-semibold">✓ Built-in 30-sec flow</span>
              <a href="../f.php?fest=<?= h($f['slug']) ?>" target="_blank" class="text-[10px] text-pink-400 underline hover:text-pink-300 ml-2">preview ↗</a>
            </td>
            <td class="px-5 py-4">
              <select class="status-sel" name="statuses[<?= h($f['slug']) ?>]">
                <?php foreach ($status_meta as $key => $m): ?>
                <option value="<?= $key ?>" <?= $st === $key ? 'selected' : '' ?>><?= $m['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="mt-6 text-center">
      <button type="submit" class="bg-gradient-to-r from-pink-500 to-purple-500 hover:from-pink-400 hover:to-purple-400 text-white font-bold text-sm px-8 py-3 rounded-xl shadow-lg shadow-pink-500/20 transition hover:-translate-y-0.5">💾 Save All Statuses</button>
    </div>
  </form>
</main>

</body>
</html>
