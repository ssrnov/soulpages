<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) redirect('login.php');
ensure_verify_request_column($pdo);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Backup-email for alerts
    if (isset($_POST['save_email'])) {
        set_setting('admin_notify_email', trim($_POST['admin_notify_email'] ?? ''));
        $msg = 'Backup alert email saved.';
    }
    // Verify or dismiss a request
    if (isset($_POST['verify_user'])) {
        $uid = (int)$_POST['verify_user'];
        $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, verify_requested_at = NULL WHERE id = ?")->execute([$uid]);
        try { $pdo->prepare("UPDATE users SET verification_otp = NULL WHERE id = ?")->execute([$uid]); } catch (\Throwable $e) {}
        $msg = 'User verified ✅';
    }
    if (isset($_POST['dismiss_user'])) {
        $uid = (int)$_POST['dismiss_user'];
        $pdo->prepare("UPDATE users SET verify_requested_at = NULL WHERE id = ?")->execute([$uid]);
        $msg = 'Request dismissed.';
    }
}

$requests = [];
try {
    $requests = $pdo->query("SELECT id, name, email, created_at, verify_requested_at FROM users WHERE email_verified = 0 AND verify_requested_at IS NOT NULL ORDER BY verify_requested_at DESC")->fetchAll();
} catch (\Throwable $e) {}

$adm_email = get_setting('admin_notify_email', '');
$devices = count(get_push_subs());
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Requests — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; }
  .heading { font-family:'Outfit',sans-serif; }
  .tfld { width:100%; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:9px 12px; color:#0f172a; font-size:.85rem; outline:none; }
  .tfld:focus { border-color:#db2777; }
</style>
</head>
<body>

<?php $ADMIN_TITLE = 'Verify Requests'; include '_nav.php'; ?>

<main class="max-w-4xl mx-auto px-5 sm:px-8 py-7">
  <div class="mb-6">
    <h1 class="heading text-2xl font-extrabold text-slate-900">🙋 Verification Requests</h1>
    <p class="text-sm text-slate-500 mt-1">Jin users ke email nahi aa rahe / spam me chale gaye, wo yahan "verify me" request bhejte hain. Ek click me verify karo. Request aate hi <b>aapke installed app pe notification</b> aayega.</p>
  </div>

  <?php if ($msg): ?><div class="mb-5 rounded-2xl p-4 text-sm card" style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;">✅ <?= h($msg) ?></div><?php endif; ?>

  <!-- Push notification status + test -->
  <div class="mb-6" style="background:#fff;border:1px solid #eef0f4;border-radius:20px;box-shadow:0 4px 18px rgba(30,41,59,0.05);padding:20px;">
    <div class="flex items-start gap-3 flex-wrap">
      <div class="text-2xl">🔔</div>
      <div class="flex-1 min-w-[200px]">
        <p class="font-bold text-slate-900">App notifications — <?= $devices ?> device<?= $devices == 1 ? '' : 's' ?> connected</p>
        <p class="text-xs text-slate-500 mt-0.5">Install the app on your phone (sidebar → <b>Install App</b>), then tap <b>Enable &amp; Test</b> once. After that you'll get a notification whenever a user requests verification — <b>even when the app is closed</b>.</p>
      </div>
      <button type="button" onclick="window.spTestNotify && window.spTestNotify()" class="text-sm font-bold text-white px-5 py-2.5 rounded-full" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);">🔔 Enable &amp; Test</button>
    </div>
  </div>

  <!-- Pending requests -->
  <div class="card p-6 mb-6" style="background:#fff;border:1px solid #eef0f4;border-radius:20px;box-shadow:0 4px 18px rgba(30,41,59,0.05);">
    <div class="flex items-center justify-between mb-4">
      <h2 class="heading font-bold text-slate-900">Pending <span class="ml-1 text-xs font-bold bg-pink-100 text-pink-600 px-2 py-0.5 rounded-full"><?= count($requests) ?></span></h2>
      <a href="verify-requests.php" class="text-xs font-bold text-pink-600 hover:underline">↻ Refresh</a>
    </div>
    <?php if (empty($requests)): ?>
      <div class="text-center py-10 text-slate-400"><div class="text-4xl mb-2">🎉</div>No pending requests.</div>
    <?php else: foreach ($requests as $r): ?>
      <div class="flex items-center gap-3 py-3 border-b border-slate-50 last:border-0 flex-wrap">
        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold" style="background:linear-gradient(135deg,#f43f5e,#db2777);"><?= h(mb_strtoupper(mb_substr($r['name'] ?: 'U', 0, 1))) ?></div>
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-slate-800 truncate"><?= h($r['name'] ?: 'User') ?></p>
          <p class="text-xs text-slate-500 truncate"><?= h($r['email']) ?> · requested <?= h(date('d M, h:i A', strtotime($r['verify_requested_at']))) ?></p>
        </div>
        <form method="POST" class="flex gap-2">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <button name="verify_user" value="<?= (int)$r['id'] ?>" class="text-xs font-bold text-white px-4 py-2 rounded-xl" style="background:linear-gradient(135deg,#059669,#10b981);">✅ Verify</button>
          <button name="dismiss_user" value="<?= (int)$r['id'] ?>" onclick="return confirm('Dismiss this request without verifying?')" class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-2 rounded-xl">Dismiss</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- How-to + backup email -->
  <div class="card p-6" style="background:#fff;border:1px solid #eef0f4;border-radius:20px;box-shadow:0 4px 18px rgba(30,41,59,0.05);">
    <h2 class="heading font-bold text-slate-900 mb-1">📲 Get alerts on your phone</h2>
    <div class="rounded-xl p-4 mb-4 text-[13px] text-slate-600" style="background:#f8fafc;border:1px solid #eef0f4;">
      <b class="text-slate-800">One-time setup:</b>
      <ol class="list-decimal pl-5 mt-1 space-y-1">
        <li>Phone <b>Chrome</b> me admin panel kholo → login.</li>
        <li>Sidebar me <b>⬇ Install App</b> dabao (ya Chrome menu ⋮ → <b>Install app</b>). Home screen pe app aa jayega.</li>
        <li>App kholo → is page pe <b>🔔 Enable &amp; Test</b> dabao → <b>Allow</b> karo. Ek test notification aayega ✅</li>
      </ol>
      Bas! Ab jab bhi koi user verify request karega, phone pe notification aayega — app band ho tab bhi.
    </div>
    <form method="POST" class="flex flex-wrap items-end gap-3">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="flex-1 min-w-[220px]"><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Backup email for alerts (optional)</label><input class="tfld" name="admin_notify_email" value="<?= h($adm_email) ?>" placeholder="you@gmail.com"></div>
      <button name="save_email" value="1" class="text-sm font-bold text-white px-6 py-2.5 rounded-full" style="background:linear-gradient(135deg,#db2777,#e11d48);">💾 Save</button>
    </form>
  </div>
</main>

</body>
</html>
