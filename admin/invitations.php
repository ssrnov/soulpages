<?php
require_once '../includes/functions.php';
require_once '../includes/invitations.php';

if (!is_logged_in() || !is_admin()) redirect('login.php');

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(120);
    if (!empty($_FILES['tpl_zip']['name'])) {
        [$ok, $r] = inv_install_zip($_FILES['tpl_zip']);
        if ($ok) $msg = 'Template installed & published: ' . $r . '. It now shows on the Video Invitations page.';
        else $err = $r;
    } elseif (isset($_POST['meta_id'])) {
        inv_update_meta($_POST['meta_id'], [
            'status' => in_array($_POST['status'] ?? '', ['published', 'draft', 'hidden']) ? $_POST['status'] : 'draft',
            'featured' => isset($_POST['featured']),
            'trending' => isset($_POST['trending']),
            'new_badge' => isset($_POST['new_badge']),
            'credit_cost' => max(0, (int)($_POST['credit_cost'] ?? 1)),
            'sort' => (int)($_POST['sort'] ?? 999),
        ]);
        $msg = 'Saved.';
    } elseif (isset($_POST['content_id'])) {
        // Per-template example content: field defaults + event on/off & dates
        $fo = [];
        foreach (($_POST['field'] ?? []) as $k => $v) { $v = trim($v); if ($v !== '') $fo[preg_replace('/[^a-z0-9_]/i', '', $k)] = $v; }
        $eo = [];
        foreach (($_POST['evt'] ?? []) as $k => $row) {
            $k = preg_replace('/[^a-z0-9_]/i', '', $k);
            $eo[$k] = ['on' => !empty($row['on']), 'date' => trim($row['date'] ?? ''), 'time' => trim($row['time'] ?? '')];
        }
        inv_update_meta($_POST['content_id'], ['fields_override' => $fo, 'events_override' => $eo]);
        $msg = 'Example content saved for this template.';
    }
}
if (isset($_GET['del']) && $_GET['del'] !== 'royal_gold_wedding') { inv_delete($_GET['del']); redirect('invitations.php'); }

$templates = inv_templates(false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invitation Studio — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  .ifld { background:#0f172a; border:1px solid #1e293b; border-radius:10px; padding:7px 11px; color:#fff; font-size:0.8rem; outline:none; }
</style>
</head>
<body class="min-h-screen">

<?php $ADMIN_TITLE = 'Invitation Studio'; include '_nav.php'; ?>

<main class="max-w-5xl mx-auto px-4 py-8">
  <div class="mb-6">
    <h1 class="heading text-2xl font-extrabold text-white">💌 Invitation Studio</h1>
    <p class="text-sm text-slate-400 mt-1">Animated video invitation templates. Upload a ZIP (with <code class="text-pink-300">template.json</code>) to add unlimited templates — no code changes. Users customise & download as video; rendering happens in their browser, zero server load.</p>
  </div>

  <?php if ($msg): ?><div class="mb-5 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ <?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="mb-5 rounded-2xl p-4 text-sm bg-red-500/15 border border-red-500/30 text-red-200">⚠️ <?= h($err) ?></div><?php endif; ?>

  <!-- Upload ZIP -->
  <form method="post" enctype="multipart/form-data" class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6 mb-8 flex flex-wrap items-end gap-4">
    <div class="flex-1 min-w-[240px]">
      <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Template ZIP (template.json + assets, ≤80MB)</label>
      <input type="file" name="tpl_zip" accept=".zip" required class="ifld w-full">
    </div>
    <button class="font-bold text-white text-sm px-8 py-2.5 rounded-full bg-gradient-to-r from-pink-500 to-purple-500">⬆️ Install Template</button>
    <p class="w-full text-[11px] text-slate-500">ZIP structure: <code>template.json</code> (required) + optional <code>thumbnail.jpg, music.mp3, background.jpg, assets/…</code>. Use the built-in "Royal Golden Wedding" as the format reference — its JSON defines fields, scenes, animations & credit cost.</p>
  </form>

  <!-- Templates -->
  <div class="space-y-4">
    <?php foreach ($templates as $t): $builtin = $t['id'] === 'royal_gold_wedding'; ?>
    <form method="post" class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5 flex flex-wrap items-center gap-4">
      <input type="hidden" name="meta_id" value="<?= h($t['id']) ?>">
      <div class="text-3xl"><?= h($t['emoji'] ?? '💌') ?></div>
      <div class="min-w-[180px]">
        <b class="text-white block"><?= h($t['name']) ?> <?= $builtin ? '<span class="text-[9px] text-purple-300 border border-purple-500/40 rounded px-1.5 py-0.5 ml-1">BUILT-IN</span>' : '' ?></b>
        <span class="text-[11px] text-slate-500"><?= h($t['category'] ?? '') ?> · <?= (int)($t['duration'] ?? 30) ?>s · <?= h($t['aspect'] ?? '9:16') ?> · id: <?= h($t['id']) ?></span>
      </div>
      <label class="text-[11px] text-slate-400">Status
        <select name="status" class="ifld ml-1">
          <?php foreach (['published' => '✅ Published', 'draft' => '📝 Draft', 'hidden' => '🙈 Hidden'] as $sv => $sl): ?>
          <option value="<?= $sv ?>" <?= ($t['status'] ?? 'draft') === $sv ? 'selected' : '' ?>><?= $sl ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="text-[11px] text-slate-400">Credits <input type="number" name="credit_cost" value="<?= (int)($t['credit_cost'] ?? 1) ?>" min="0" class="ifld w-16 ml-1"></label>
      <label class="text-[11px] text-slate-400">Sort <input type="number" name="sort" value="<?= (int)($t['sort'] ?? 999) ?>" class="ifld w-16 ml-1"></label>
      <label class="flex items-center gap-1.5 text-[11px] text-slate-300"><input type="checkbox" name="featured" <?= !empty($t['featured']) ? 'checked' : '' ?> class="accent-pink-500"> ⭐ Featured</label>
      <label class="flex items-center gap-1.5 text-[11px] text-slate-300"><input type="checkbox" name="trending" <?= !empty($t['trending']) ? 'checked' : '' ?> class="accent-pink-500"> 🔥 Trending</label>
      <label class="flex items-center gap-1.5 text-[11px] text-slate-300"><input type="checkbox" name="new_badge" <?= !empty($t['new_badge']) ? 'checked' : '' ?> class="accent-pink-500"> ✨ New</label>
      <div class="ml-auto flex gap-2">
        <a href="../invite-edit.php?id=<?= h($t['id']) ?>" target="_blank" class="text-xs font-semibold bg-slate-800 border border-slate-700 text-slate-200 px-3.5 py-2 rounded-xl hover:bg-slate-700">👁️ Preview</a>
        <button class="text-xs font-bold bg-gradient-to-r from-pink-500 to-purple-500 text-white px-4 py-2 rounded-xl">💾 Save</button>
        <?php if (!$builtin): ?><a href="invitations.php?del=<?= h($t['id']) ?>" onclick="return confirm('Delete this template and its files?')" class="text-xs font-semibold bg-red-500/10 border border-red-500/30 text-red-300 px-3.5 py-2 rounded-xl">🗑</a><?php endif; ?>
      </div>
    </form>

    <!-- Per-template example content editor -->
    <?php if (!empty($t['fields']) || !empty($t['events'])): ?>
    <details class="-mt-2 mb-4 rounded-2xl border border-slate-800/70 bg-slate-900/30">
      <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-pink-300">✏️ Edit Example Content <span class="text-slate-500 font-normal">— names, venue, dates, which events show</span></summary>
      <form method="post" class="px-5 pb-5">
        <input type="hidden" name="content_id" value="<?= h($t['id']) ?>">
        <?php if (!empty($t['fields'])): ?>
        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2 mt-1">Text Fields</p>
        <div class="grid sm:grid-cols-2 gap-3 mb-4">
          <?php foreach ($t['fields'] as $f): if (($f['type'] ?? '') === 'color') continue; ?>
          <div>
            <label class="block text-[11px] text-slate-400 mb-1"><?= h(inv_plain($f['label'] ?? $f['key'])) ?></label>
            <input class="ifld w-full" name="field[<?= h($f['key']) ?>]" value="<?= h(inv_plain($f['default'] ?? '')) ?>">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($t['events'])): ?>
        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Events (tick = shows by default)</p>
        <div class="space-y-2 mb-4">
          <?php foreach ($t['events'] as $ev): ?>
          <div class="flex items-center gap-2 flex-wrap">
            <label class="flex items-center gap-1.5 text-xs text-slate-300 w-44"><input type="checkbox" name="evt[<?= h($ev['key']) ?>][on]" <?= ($ev['on'] ?? false) ? 'checked' : '' ?> class="accent-pink-500"> <?= h($ev['emoji'] ?? '') ?> <?= h(inv_plain($ev['label'] ?? $ev['key'])) ?></label>
            <input class="ifld w-28" name="evt[<?= h($ev['key']) ?>][date]" value="<?= h($ev['date'] ?? '') ?>" placeholder="Date">
            <input class="ifld w-28" name="evt[<?= h($ev['key']) ?>][time]" value="<?= h($ev['time'] ?? '') ?>" placeholder="Time">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="flex items-center gap-3">
          <button class="text-xs font-bold bg-gradient-to-r from-emerald-500 to-teal-500 text-white px-5 py-2 rounded-xl">💾 Save Content</button>
          <span class="text-[11px] text-slate-500">Colour fields &amp; photos stay user-editable in the studio. Live for everyone instantly.</span>
        </div>
      </form>
    </details>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
</main>

</body>
</html>
