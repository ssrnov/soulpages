<?php
require_once '../includes/functions.php';
require_once '../includes/gateways.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$saved_ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cfg = gw_config();
    $g = $_POST['g'] ?? [];
    foreach (gw_defaults() as $key => $def) {
        foreach ($def as $field => $dv) {
            if ($field === 'enabled') $cfg[$key]['enabled'] = !empty($g[$key]['enabled']);
            else $cfg[$key][$field] = trim($g[$key][$field] ?? ($cfg[$key][$field] ?? $dv));
        }
    }
    set_setting('payment_gateways', json_encode($cfg, JSON_UNESCAPED_UNICODE));
    $saved_ok = true;
}
$cfg = gw_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Gateways — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  .gfld { width:100%; background:#0f172a; border:1px solid #1e293b; border-radius:12px; padding:9px 13px; color:#fff; font-size:0.82rem; outline:none; font-family:monospace; }
  .gfld:focus { border-color:#ec4899; }
  .glbl { display:block; font-size:0.65rem; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:4px; font-weight:700; }
</style>
</head>
<body class="min-h-screen">

<?php $ADMIN_TITLE = 'Payment Gateways'; include '_nav.php'; ?>

<main class="max-w-4xl mx-auto px-4 py-8">
  <div class="mb-6">
    <h1 class="heading text-2xl font-extrabold text-white">🏦 Payment Gateways</h1>
    <p class="text-sm text-slate-400 mt-1">Enable multiple gateways so buyers always have a UPI option. A gateway appears on the payment page only when it's enabled <b>and</b> its credentials are filled.</p>
  </div>

  <?php if ($saved_ok): ?><div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Gateway settings saved!</div><?php endif; ?>

  <form method="post" class="space-y-5">

    <!-- Razorpay -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div><span class="text-2xl">💳</span> <b class="text-white">Razorpay</b> <span class="text-xs text-slate-500">— keys are in Admin → Settings (existing flow)</span></div>
        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer"><input type="checkbox" name="g[razorpay][enabled]" value="1" <?= !empty($cfg['razorpay']['enabled']) ? 'checked' : '' ?> class="w-4 h-4 accent-pink-500"> Enabled</label>
      </div>
      <p class="text-[11px] text-slate-500 mt-2">💡 UPI not showing in Razorpay? UPI must be activated on your Razorpay dashboard (Settings → Payment Methods). New accounts often need KYC completion first.</p>
    </div>

    <!-- PayU -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
      <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
        <div><span class="text-2xl">🟢</span> <b class="text-white">PayU</b> <span class="text-xs text-emerald-400">— easiest UPI support, recommended</span></div>
        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer"><input type="checkbox" name="g[payu][enabled]" value="1" <?= !empty($cfg['payu']['enabled']) ? 'checked' : '' ?> class="w-4 h-4 accent-pink-500"> Enabled</label>
      </div>
      <div class="grid sm:grid-cols-3 gap-4">
        <div><label class="glbl">Merchant Key</label><input class="gfld" name="g[payu][key]" value="<?= h($cfg['payu']['key']) ?>"></div>
        <div><label class="glbl">Salt (v1)</label><input class="gfld" name="g[payu][salt]" value="<?= h($cfg['payu']['salt']) ?>"></div>
        <div><label class="glbl">Mode</label><select class="gfld" name="g[payu][mode]"><option value="live" <?= $cfg['payu']['mode'] === 'live' ? 'selected' : '' ?>>Live</option><option value="test" <?= $cfg['payu']['mode'] === 'test' ? 'selected' : '' ?>>Test</option></select></div>
      </div>
      <p class="text-[11px] text-slate-500 mt-2">From PayU Dashboard → Settings → API Keys. Success/Failure URL is handled automatically (pay-return.php).</p>
    </div>

    <!-- PhonePe -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
      <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
        <div><span class="text-2xl">🟣</span> <b class="text-white">PhonePe</b> <span class="text-xs text-slate-500">— Standard Checkout (PG)</span></div>
        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer"><input type="checkbox" name="g[phonepe][enabled]" value="1" <?= !empty($cfg['phonepe']['enabled']) ? 'checked' : '' ?> class="w-4 h-4 accent-pink-500"> Enabled</label>
      </div>
      <div class="grid sm:grid-cols-4 gap-4">
        <div><label class="glbl">Merchant ID</label><input class="gfld" name="g[phonepe][merchant_id]" value="<?= h($cfg['phonepe']['merchant_id']) ?>"></div>
        <div><label class="glbl">Salt Key</label><input class="gfld" name="g[phonepe][salt_key]" value="<?= h($cfg['phonepe']['salt_key']) ?>"></div>
        <div><label class="glbl">Salt Index</label><input class="gfld" name="g[phonepe][salt_index]" value="<?= h($cfg['phonepe']['salt_index']) ?>"></div>
        <div><label class="glbl">Mode</label><select class="gfld" name="g[phonepe][mode]"><option value="live" <?= $cfg['phonepe']['mode'] === 'live' ? 'selected' : '' ?>>Live</option><option value="test" <?= $cfg['phonepe']['mode'] === 'test' ? 'selected' : '' ?>>Test (preprod)</option></select></div>
      </div>
      <p class="text-[11px] text-slate-500 mt-2">From PhonePe Business dashboard → Developer Settings. Needs an approved PG merchant account.</p>
    </div>

    <!-- Paytm -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
      <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
        <div><span class="text-2xl">🔵</span> <b class="text-white">Paytm</b> <span class="text-xs text-slate-500">— All-in-One JS Checkout</span></div>
        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer"><input type="checkbox" name="g[paytm][enabled]" value="1" <?= !empty($cfg['paytm']['enabled']) ? 'checked' : '' ?> class="w-4 h-4 accent-pink-500"> Enabled</label>
      </div>
      <div class="grid sm:grid-cols-4 gap-4">
        <div><label class="glbl">MID</label><input class="gfld" name="g[paytm][mid]" value="<?= h($cfg['paytm']['mid']) ?>"></div>
        <div><label class="glbl">Merchant Key</label><input class="gfld" name="g[paytm][merchant_key]" value="<?= h($cfg['paytm']['merchant_key']) ?>"></div>
        <div><label class="glbl">Website</label><input class="gfld" name="g[paytm][website]" value="<?= h($cfg['paytm']['website']) ?>" placeholder="DEFAULT / WEBSTAGING"></div>
        <div><label class="glbl">Mode</label><select class="gfld" name="g[paytm][mode]"><option value="live" <?= $cfg['paytm']['mode'] === 'live' ? 'selected' : '' ?>>Live</option><option value="test" <?= $cfg['paytm']['mode'] === 'test' ? 'selected' : '' ?>>Test (staging)</option></select></div>
      </div>
      <p class="text-[11px] text-slate-500 mt-2">From Paytm Business dashboard → Developer → API Keys. Test mode uses Website "WEBSTAGING".</p>
    </div>

    <!-- Airpay -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
      <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
        <div><span class="text-2xl">🟠</span> <b class="text-white">Airpay</b> <span class="text-xs text-amber-400">— beta: test with ₹1 before going live</span></div>
        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer"><input type="checkbox" name="g[airpay][enabled]" value="1" <?= !empty($cfg['airpay']['enabled']) ? 'checked' : '' ?> class="w-4 h-4 accent-pink-500"> Enabled</label>
      </div>
      <div class="grid sm:grid-cols-4 gap-4">
        <div><label class="glbl">Merchant ID</label><input class="gfld" name="g[airpay][merchant_id]" value="<?= h($cfg['airpay']['merchant_id']) ?>"></div>
        <div><label class="glbl">Username</label><input class="gfld" name="g[airpay][username]" value="<?= h($cfg['airpay']['username']) ?>"></div>
        <div><label class="glbl">Password</label><input class="gfld" name="g[airpay][password]" value="<?= h($cfg['airpay']['password']) ?>"></div>
        <div><label class="glbl">Secret Key</label><input class="gfld" name="g[airpay][secret]" value="<?= h($cfg['airpay']['secret']) ?>"></div>
      </div>
      <p class="text-[11px] text-slate-500 mt-2">Values from your Airpay merchant kit. Checksum formats vary by kit version — verify with a ₹1 transaction first.</p>
    </div>

    <div class="text-center pt-2">
      <button type="submit" class="font-bold text-white text-sm px-10 py-3.5 rounded-full bg-gradient-to-r from-pink-500 to-purple-500 shadow-lg shadow-pink-500/25 hover:-translate-y-0.5 transition">💾 Save Gateways</button>
    </div>
  </form>
</main>

</body>
</html>
