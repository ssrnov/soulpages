<?php
require_once '../includes/functions.php';
if (!is_logged_in() || !is_admin()) redirect('login.php');

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_setting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
    set_setting('maintenance_message', trim($_POST['maintenance_message'] ?? ''));
    set_setting('site_title', trim($_POST['site_title'] ?? ''));
    set_setting('site_description', trim($_POST['site_description'] ?? ''));
    set_setting('homepage_h1', trim($_POST['homepage_h1'] ?? ''));
    set_setting('og_image_url', trim($_POST['og_image_url'] ?? ''));
    set_setting('custom_css', $_POST['custom_css'] ?? '');
    set_setting('custom_js', $_POST['custom_js'] ?? '');
    set_setting('blocked_ips', trim($_POST['blocked_ips'] ?? ''));
    $saved = true;
}

$vals = [
    'maintenance_mode'    => get_setting('maintenance_mode', '0'),
    'maintenance_message' => get_setting('maintenance_message', 'We are making things even more beautiful. Back soon! 💖'),
    'site_title'          => get_setting('site_title', ''),
    'site_description'    => get_setting('site_description', ''),
    'homepage_h1'         => get_setting('homepage_h1', ''),
    'og_image_url'        => get_setting('og_image_url', ''),
    'custom_css'          => get_setting('custom_css', ''),
    'custom_js'           => get_setting('custom_js', ''),
    'blocked_ips'         => get_setting('blocked_ips', ''),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Site Controls — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  .pfld { background:#0f172a; border:1px solid #334155; color:#fff; border-radius:10px; padding:10px 12px; font-size:0.85rem; outline:none; width:100%; }
  .pfld:focus { border-color:#ec4899; }
  textarea.pfld { resize:vertical; font-family:monospace; font-size:0.8rem; }
  .card { background:rgba(15,23,42,0.5); border:1px solid #1e293b; border-radius:18px; padding:22px; margin-bottom:20px; }
</style>
</head>
<body class="min-h-screen">
<?php $ADMIN_TITLE = 'Site Controls'; include '_nav.php'; ?>

<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <h1 class="heading text-2xl font-extrabold text-white mb-6">🛡️ Site Controls</h1>
  <?php if ($saved): ?><div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Saved.</div><?php endif; ?>

  <form method="post">
    <div class="card" style="border-color:rgba(245,158,11,0.3);">
      <h2 class="heading text-base font-bold text-amber-300 mb-4">🛠️ Maintenance Mode</h2>
      <label class="flex items-center gap-3 text-sm text-slate-300 cursor-pointer mb-4"><input type="checkbox" name="maintenance_mode" <?= $vals['maintenance_mode'] === '1' ? 'checked' : '' ?> class="w-5 h-5 accent-amber-500"> <b>Enable maintenance mode</b> — visitors see a friendly maintenance screen. Admins, login &amp; API keep working.</label>
      <div><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Maintenance message</label><input class="pfld" name="maintenance_message" value="<?= h($vals['maintenance_message']) ?>"></div>
    </div>

    <div class="card">
      <h2 class="heading text-base font-bold text-white mb-4">🔍 SEO</h2>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Site title</label><input class="pfld" name="site_title" value="<?= h($vals['site_title']) ?>"></div>
        <div><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Homepage H1</label><input class="pfld" name="homepage_h1" value="<?= h($vals['homepage_h1']) ?>"></div>
      </div>
      <div class="mt-4"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Meta description</label><textarea class="pfld" name="site_description" rows="2" style="font-family:Inter;"><?= h($vals['site_description']) ?></textarea></div>
      <div class="mt-4"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">OG share image URL</label><input class="pfld" name="og_image_url" value="<?= h($vals['og_image_url']) ?>" placeholder="uploads/og.jpg or https://…"></div>
    </div>

    <div class="card">
      <h2 class="heading text-base font-bold text-white mb-4">🎨 Custom CSS &amp; JS (injected on the homepage)</h2>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Custom CSS</label><textarea class="pfld" name="custom_css" rows="6" placeholder=".sp-btn-primary { border-radius: 8px; }"><?= h($vals['custom_css']) ?></textarea></div>
        <div><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Custom JS</label><textarea class="pfld" name="custom_js" rows="6" placeholder="console.log('hi');"><?= h($vals['custom_js']) ?></textarea></div>
      </div>
    </div>

    <div class="card" style="border-color:rgba(239,68,68,0.3);">
      <h2 class="heading text-base font-bold text-red-300 mb-4">🚫 IP Block</h2>
      <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Blocked IPs (one per line)</label>
      <textarea class="pfld" name="blocked_ips" rows="4" placeholder="1.2.3.4"><?= h($vals['blocked_ips']) ?></textarea>
      <p class="text-[11px] text-slate-500 mt-2">Blocked IPs get a 403 on every page. Admins are never blocked.</p>
    </div>

    <div class="text-center pb-6"><button type="submit" class="bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold text-sm px-8 py-3 rounded-xl shadow-lg shadow-pink-500/20 transition hover:-translate-y-0.5">💾 Save Site Controls</button></div>
  </form>
</main>
</body>
</html>
