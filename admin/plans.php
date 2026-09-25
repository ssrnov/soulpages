<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$saved = false;
$error = '';
$notice = '';

// ── Save flash sale ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_flash_sale'])) {
    set_setting('flash_sale', json_encode([
        'active'  => isset($_POST['fs_active']),
        'name'    => trim($_POST['fs_name'] ?? 'Flash Sale'),
        'percent' => max(0, min(90, (int)($_POST['fs_percent'] ?? 0))),
    ]));
    $notice = '⚡ Flash sale settings saved.';
}

// ── Gift credits to a user ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gift_credits'])) {
    $g_email = trim($_POST['gift_email'] ?? '');
    $g_amt = max(1, min(1000, (int)($_POST['gift_amount'] ?? 0)));
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$g_email]);
    $g_user = $stmt->fetch();
    if ($g_user) {
        add_credits($g_user['id'], $g_amt);
        $notice = "🎁 Gifted {$g_amt} credits to " . h($g_user['name']) . " ({$g_email}).";
    } else {
        $error = 'No user found with that email.';
    }
}

// ── Save plans ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plans'])) {
    $names   = $_POST['plan_name'] ?? [];
    $creds   = $_POST['plan_credits'] ?? [];
    $prices  = $_POST['plan_price'] ?? [];   // rupees
    $offers  = $_POST['plan_offer'] ?? [];   // rupees, optional
    $badges  = $_POST['plan_badge'] ?? [];
    $featsA  = $_POST['plan_features'] ?? [];
    $actives = $_POST['plan_active'] ?? [];
    $deletes = $_POST['plan_delete'] ?? [];

    $out = [];
    for ($i = 0; $i < count($names); $i++) {
        if (isset($deletes[$i])) continue;
        $name = trim($names[$i]);
        $credits = (int)($creds[$i] ?? 0);
        $rupees = (float)($prices[$i] ?? 0);
        $offer = (float)($offers[$i] ?? 0);
        if ($name === '' && $credits <= 0 && $rupees <= 0) continue;
        if ($name === '' || $credits < 1 || $rupees < 1) { $error = 'Every plan needs a name, ≥1 credit and a price of ₹1+.'; break; }
        if ($offer > 0 && $offer >= $rupees) { $error = "Offer price for \"{$name}\" must be lower than the regular price."; break; }
        $out[] = [
            'id'          => 'plan_' . $credits . '_' . (int)round($rupees * 100) . '_' . $i,
            'name'        => $name,
            'credits'     => $credits,
            'price_paise' => (int)round($rupees * 100),
            'offer_paise' => $offer > 0 ? (int)round($offer * 100) : 0,
            'badge'       => trim($badges[$i] ?? ''),
            'features'    => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $featsA[$i] ?? '')), 'strlen')),
            'active'      => isset($actives[$i]),
        ];
    }
    if (!$error) {
        if (empty($out)) $error = 'You need at least one plan.';
        else { set_setting('credit_plans', json_encode($out, JSON_UNESCAPED_UNICODE)); $saved = true; }
    }
}

$plans = get_credit_plans(false);
$sale = get_flash_sale();
$sale_raw = json_decode(get_setting('flash_sale', ''), true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plan Builder — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  .pfld { background:#0f172a; border:1px solid #334155; color:#fff; border-radius:10px; padding:9px 12px; font-size:0.85rem; outline:none; width:100%; }
  .pfld:focus { border-color:#ec4899; }
  textarea.pfld { resize:vertical; }
  .card { background:rgba(15,23,42,0.5); border:1px solid #1e293b; border-radius:18px; padding:20px; }
</style>
</head>
<body class="min-h-screen">

<?php $ADMIN_TITLE = 'Dynamic Plan Builder'; include '_nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 pt-4 flex justify-end"><a href="../payment.php" target="_blank" class="text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 px-3.5 py-2 rounded-xl hover:bg-slate-800 hover:text-white transition">View Buy Page ↗</a></div>

<main class="max-w-6xl mx-auto px-4 py-8">
  <?php if ($saved): ?><div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Plans saved! The buy page is updated instantly.</div><?php endif; ?>
  <?php if ($notice): ?><div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;"><?= $notice ?></div><?php endif; ?>
  <?php if ($error): ?><div class="mb-6 rounded-2xl p-4 text-sm bg-red-500/15 border border-red-500/30 text-red-200"><?= h($error) ?></div><?php endif; ?>

  <!-- ⚡ FLASH SALE / FESTIVAL OFFER -->
  <div class="card mb-6" style="border-color:rgba(244,197,107,0.35);">
    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
      <h2 class="heading text-lg font-extrabold text-amber-300">⚡ Flash Sale / Festival Offer</h2>
      <span class="text-[10px] font-bold uppercase tracking-wider px-3 py-1 rounded-full <?= $sale['active'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700/40 text-slate-400' ?>"><?= $sale['active'] ? '● LIVE — ' . (int)$sale['percent'] . '% OFF' : '○ Off' ?></span>
    </div>
    <form method="post" class="grid sm:grid-cols-4 gap-4 items-end">
      <div class="sm:col-span-2"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Offer Name (shown as a banner)</label><input class="pfld" name="fs_name" value="<?= h($sale_raw['name'] ?? 'Independence Day Offer 🇮🇳') ?>" placeholder="Independence Day 50% OFF 🇮🇳"></div>
      <div><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Discount %</label><input class="pfld" type="number" min="0" max="90" name="fs_percent" value="<?= (int)($sale_raw['percent'] ?? 0) ?>"></div>
      <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer"><input type="checkbox" name="fs_active" <?= !empty($sale_raw['active']) ? 'checked' : '' ?> class="w-5 h-5 accent-amber-500"> Live</label>
        <button type="submit" name="save_flash_sale" value="1" class="bg-gradient-to-r from-amber-500 to-orange-500 text-white font-bold text-xs px-5 py-3 rounded-xl hover:-translate-y-0.5 transition">💾 Save</button>
      </div>
    </form>
    <p class="text-[11px] text-slate-500 mt-3">One click: turn it on for a festival, off after. Discount applies on top of every plan's price (offer price included) on the buy page &amp; at checkout.</p>
  </div>

  <!-- 🎁 GIFT CREDITS -->
  <div class="card mb-6" style="border-color:rgba(236,72,153,0.25);">
    <h2 class="heading text-lg font-extrabold text-pink-300 mb-4">🎁 Give Premium / Credits to a User</h2>
    <form method="post" class="grid sm:grid-cols-4 gap-4 items-end">
      <div class="sm:col-span-2"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">User Email</label><input class="pfld" type="email" name="gift_email" required placeholder="user@example.com"></div>
      <div><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Credits</label><input class="pfld" type="number" min="1" max="1000" name="gift_amount" value="5"></div>
      <div><button type="submit" name="gift_credits" value="1" class="w-full bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold text-xs px-5 py-3 rounded-xl hover:-translate-y-0.5 transition">🎁 Gift Credits</button></div>
    </form>
    <p class="text-[11px] text-slate-500 mt-3">Tip: 5 credits = one premium page. Great for support cases, influencers or giveaways.</p>
  </div>

  <!-- 💳 PLANS -->
  <div class="mb-4">
    <h2 class="heading text-lg font-extrabold text-white">💳 Plans</h2>
    <p class="text-sm text-slate-400 mt-1">Add unlimited plans — name, price, offer (strike-through) price, badge &amp; feature list. No coding needed. Rows save top-to-bottom (that's the display order).</p>
  </div>

  <form method="post">
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 overflow-x-auto">
      <table class="w-full text-sm min-w-[980px]">
        <thead>
          <tr class="border-b border-slate-800 text-left text-[11px] uppercase tracking-wider text-slate-500">
            <th class="px-4 py-3">Plan Name</th>
            <th class="px-4 py-3 w-24">Credits</th>
            <th class="px-4 py-3 w-28">Price ₹</th>
            <th class="px-4 py-3 w-28">Offer ₹ <span class="normal-case text-slate-600">(opt.)</span></th>
            <th class="px-4 py-3">Badge</th>
            <th class="px-4 py-3 w-64">Features (one per line)</th>
            <th class="px-4 py-3 w-16 text-center">Active</th>
            <th class="px-4 py-3 w-16 text-center">Del</th>
            <th class="px-4 py-3 w-16 text-center">Copy</th>
          </tr>
        </thead>
        <tbody id="planRows">
          <?php $ri = 0; foreach ($plans as $p): ?>
          <tr class="border-b border-slate-800/60 align-top">
            <td class="px-4 py-3"><input class="pfld" name="plan_name[]" value="<?= h($p['name']) ?>"></td>
            <td class="px-4 py-3"><input class="pfld" type="number" min="1" name="plan_credits[]" value="<?= (int)$p['credits'] ?>"></td>
            <td class="px-4 py-3"><input class="pfld" type="number" min="1" name="plan_price[]" value="<?= number_format($p['price_paise'] / 100, 0, '.', '') ?>"></td>
            <td class="px-4 py-3"><input class="pfld" type="number" min="0" name="plan_offer[]" value="<?= $p['offer_paise'] > 0 ? number_format($p['offer_paise'] / 100, 0, '.', '') : '' ?>" placeholder="—"></td>
            <td class="px-4 py-3"><input class="pfld" name="plan_badge[]" value="<?= h($p['badge']) ?>" placeholder="Popular 💖"></td>
            <td class="px-4 py-3"><textarea class="pfld" name="plan_features[]" rows="2" placeholder="All templates&#10;No watermark"><?= h(implode("\n", $p['features'])) ?></textarea></td>
            <td class="px-4 py-3 text-center"><input type="checkbox" name="plan_active[<?= $ri ?>]" <?= $p['active'] ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500"></td>
            <td class="px-4 py-3 text-center"><input type="checkbox" name="plan_delete[<?= $ri ?>]" class="w-5 h-5 accent-red-500"></td>
            <td class="px-4 py-3 text-center"><button type="button" onclick="dupRow(this)" class="text-xs bg-slate-800 border border-slate-700 text-slate-300 px-2.5 py-1.5 rounded-lg hover:bg-slate-700" title="Duplicate">⧉</button></td>
          </tr>
          <?php $ri++; endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-5">
      <button type="button" onclick="addPlanRow()" class="text-xs font-bold bg-slate-800 border border-dashed border-slate-600 text-slate-300 px-5 py-3 rounded-xl hover:bg-slate-700 transition">+ Add Plan</button>
      <button type="submit" name="save_plans" value="1" class="bg-gradient-to-r from-pink-500 to-purple-500 hover:from-pink-400 hover:to-purple-400 text-white font-bold text-sm px-8 py-3 rounded-xl shadow-lg shadow-pink-500/20 transition hover:-translate-y-0.5">💾 Save Plans</button>
    </div>
  </form>

  <div class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/30 p-5 text-xs text-slate-400 leading-relaxed">
    💡 <b class="text-slate-300">How pricing works:</b> user pays the <b>Offer ₹</b> if set (regular price shows struck-through), and any live <b>Flash Sale %</b> applies on top — all enforced server-side at checkout. Normal page = 1 credit · Premium = 5 credits.
  </div>
</main>

<script>
let rowIdx = <?= $ri ?>;
function rowHtml(v) {
  v = v || {};
  return '<td class="px-4 py-3"><input class="pfld" name="plan_name[]" value="' + (v.name || '') + '" placeholder="New Plan"></td>'
    + '<td class="px-4 py-3"><input class="pfld" type="number" min="1" name="plan_credits[]" value="' + (v.credits || 1) + '"></td>'
    + '<td class="px-4 py-3"><input class="pfld" type="number" min="1" name="plan_price[]" value="' + (v.price || 10) + '"></td>'
    + '<td class="px-4 py-3"><input class="pfld" type="number" min="0" name="plan_offer[]" value="' + (v.offer || '') + '" placeholder="—"></td>'
    + '<td class="px-4 py-3"><input class="pfld" name="plan_badge[]" value="' + (v.badge || '') + '"></td>'
    + '<td class="px-4 py-3"><textarea class="pfld" name="plan_features[]" rows="2">' + (v.features || '') + '</textarea></td>'
    + '<td class="px-4 py-3 text-center"><input type="checkbox" name="plan_active[' + rowIdx + ']" checked class="w-5 h-5 accent-emerald-500"></td>'
    + '<td class="px-4 py-3 text-center"><input type="checkbox" name="plan_delete[' + rowIdx + ']" class="w-5 h-5 accent-red-500"></td>'
    + '<td class="px-4 py-3 text-center"><button type="button" onclick="dupRow(this)" class="text-xs bg-slate-800 border border-slate-700 text-slate-300 px-2.5 py-1.5 rounded-lg hover:bg-slate-700">⧉</button></td>';
}
function esc(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
function addPlanRow(v) {
  const tr = document.createElement('tr');
  tr.className = 'border-b border-slate-800/60 align-top';
  tr.innerHTML = rowHtml(v);
  document.getElementById('planRows').appendChild(tr);
  rowIdx++;
}
function dupRow(btn) {
  const tr = btn.closest('tr');
  const get = (sel) => tr.querySelector(sel) ? tr.querySelector(sel).value : '';
  addPlanRow({
    name: esc(get('[name="plan_name[]"]')) + ' (copy)',
    credits: get('[name="plan_credits[]"]') || 1,
    price: get('[name="plan_price[]"]') || 10,
    offer: get('[name="plan_offer[]"]'),
    badge: esc(get('[name="plan_badge[]"]')),
    features: esc(get('[name="plan_features[]"]')),
  });
}
</script>
</body>
</html>
