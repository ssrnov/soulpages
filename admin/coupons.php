<?php
require_once '../includes/functions.php';
if (!is_logged_in() || !is_admin()) redirect('login.php');

$saved = false; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codes   = $_POST['c_code'] ?? [];
    $types   = $_POST['c_type'] ?? [];
    $values  = $_POST['c_value'] ?? [];
    $expiry  = $_POST['c_expiry'] ?? [];
    $limits  = $_POST['c_limit'] ?? [];
    $mins    = $_POST['c_min'] ?? [];
    $maxd    = $_POST['c_maxd'] ?? [];
    $used    = $_POST['c_used'] ?? [];
    $actives = $_POST['c_active'] ?? [];
    $deletes = $_POST['c_delete'] ?? [];

    $out = []; $seen = [];
    for ($i = 0; $i < count($codes); $i++) {
        if (isset($deletes[$i])) continue;
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($codes[$i])));
        $val = (float)($values[$i] ?? 0);
        if ($code === '' && $val <= 0) continue;
        if ($code === '' || $val <= 0) { $error = 'Every coupon needs a code and a discount value.'; break; }
        if (isset($seen[$code])) { $error = "Duplicate coupon code: {$code}"; break; }
        $seen[$code] = 1;
        $type = ($types[$i] ?? 'percent') === 'flat' ? 'flat' : 'percent';
        if ($type === 'percent' && $val > 90) $val = 90;
        $out[] = [
            'code' => $code, 'type' => $type, 'value' => $val,
            'expiry' => trim($expiry[$i] ?? ''),
            'usage_limit' => max(0, (int)($limits[$i] ?? 0)),
            'used' => max(0, (int)($used[$i] ?? 0)),
            'min_paise' => max(0, (int)round((float)($mins[$i] ?? 0) * 100)),
            'max_disc_paise' => max(0, (int)round((float)($maxd[$i] ?? 0) * 100)),
            'active' => isset($actives[$i]),
        ];
    }
    if (!$error) { set_setting('coupons', json_encode($out, JSON_UNESCAPED_UNICODE)); $saved = true; }
}

$coupons = get_coupons();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Coupons — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  .pfld { background:#0f172a; border:1px solid #334155; color:#fff; border-radius:10px; padding:8px 10px; font-size:0.82rem; outline:none; width:100%; }
  .pfld:focus { border-color:#ec4899; }
</style>
</head>
<body class="min-h-screen">
<?php $ADMIN_TITLE = 'Coupons'; include '_nav.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="mb-6">
    <h1 class="heading text-2xl font-extrabold text-white">🎟 Coupons</h1>
    <p class="text-sm text-slate-400 mt-1">Percentage or flat (₹) discounts with expiry, usage limits, minimum purchase and a maximum discount cap. Users apply them on the buy page.</p>
  </div>

  <?php if ($saved): ?><div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Coupons saved.</div><?php endif; ?>
  <?php if ($error): ?><div class="mb-6 rounded-2xl p-4 text-sm bg-red-500/15 border border-red-500/30 text-red-200"><?= h($error) ?></div><?php endif; ?>

  <form method="post">
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 overflow-x-auto">
      <table class="w-full text-sm min-w-[1020px]">
        <thead>
          <tr class="border-b border-slate-800 text-left text-[11px] uppercase tracking-wider text-slate-500">
            <th class="px-4 py-3">Code</th>
            <th class="px-4 py-3 w-28">Type</th>
            <th class="px-4 py-3 w-24">Value</th>
            <th class="px-4 py-3 w-36">Expiry</th>
            <th class="px-4 py-3 w-24">Limit <span class="normal-case text-slate-600">(0=∞)</span></th>
            <th class="px-4 py-3 w-20">Used</th>
            <th class="px-4 py-3 w-24">Min ₹</th>
            <th class="px-4 py-3 w-28">Max Disc ₹</th>
            <th class="px-4 py-3 w-16 text-center">Active</th>
            <th class="px-4 py-3 w-14 text-center">Del</th>
          </tr>
        </thead>
        <tbody id="rows">
          <?php $ri = 0; foreach ($coupons as $c): ?>
          <tr class="border-b border-slate-800/60">
            <td class="px-4 py-3"><input class="pfld font-bold" style="text-transform:uppercase;" name="c_code[]" value="<?= h($c['code']) ?>"></td>
            <td class="px-4 py-3"><select class="pfld" name="c_type[]"><option value="percent" <?= $c['type'] === 'percent' ? 'selected' : '' ?>>% off</option><option value="flat" <?= $c['type'] === 'flat' ? 'selected' : '' ?>>₹ flat</option></select></td>
            <td class="px-4 py-3"><input class="pfld" type="number" min="1" step="0.01" name="c_value[]" value="<?= h($c['value']) ?>"></td>
            <td class="px-4 py-3"><input class="pfld" type="date" name="c_expiry[]" value="<?= h($c['expiry']) ?>"></td>
            <td class="px-4 py-3"><input class="pfld" type="number" min="0" name="c_limit[]" value="<?= (int)$c['usage_limit'] ?>"></td>
            <td class="px-4 py-3"><input class="pfld" type="number" min="0" name="c_used[]" value="<?= (int)$c['used'] ?>"></td>
            <td class="px-4 py-3"><input class="pfld" type="number" min="0" name="c_min[]" value="<?= $c['min_paise'] > 0 ? number_format($c['min_paise'] / 100, 0, '.', '') : '' ?>" placeholder="—"></td>
            <td class="px-4 py-3"><input class="pfld" type="number" min="0" name="c_maxd[]" value="<?= $c['max_disc_paise'] > 0 ? number_format($c['max_disc_paise'] / 100, 0, '.', '') : '' ?>" placeholder="—"></td>
            <td class="px-4 py-3 text-center"><input type="checkbox" name="c_active[<?= $ri ?>]" <?= $c['active'] ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500"></td>
            <td class="px-4 py-3 text-center"><input type="checkbox" name="c_delete[<?= $ri ?>]" class="w-5 h-5 accent-red-500"></td>
          </tr>
          <?php $ri++; endforeach; ?>
        </tbody>
      </table>
      <?php if (empty($coupons)): ?><p class="text-center text-slate-500 text-sm py-8">No coupons yet — click "+ Add Coupon" to create your first one (e.g. <b>WELCOME50</b>, 50% off).</p><?php endif; ?>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 mt-5">
      <button type="button" onclick="addRow()" class="text-xs font-bold bg-slate-800 border border-dashed border-slate-600 text-slate-300 px-5 py-3 rounded-xl hover:bg-slate-700 transition">+ Add Coupon</button>
      <button type="submit" class="bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold text-sm px-8 py-3 rounded-xl shadow-lg shadow-pink-500/20 transition hover:-translate-y-0.5">💾 Save Coupons</button>
    </div>
  </form>
</main>

<script>
let ri = <?= $ri ?>;
function addRow() {
  const tr = document.createElement('tr');
  tr.className = 'border-b border-slate-800/60';
  tr.innerHTML = '<td class="px-4 py-3"><input class="pfld font-bold" style="text-transform:uppercase;" name="c_code[]" placeholder="WELCOME50"></td>'
    + '<td class="px-4 py-3"><select class="pfld" name="c_type[]"><option value="percent">% off</option><option value="flat">₹ flat</option></select></td>'
    + '<td class="px-4 py-3"><input class="pfld" type="number" min="1" step="0.01" name="c_value[]" value="10"></td>'
    + '<td class="px-4 py-3"><input class="pfld" type="date" name="c_expiry[]"></td>'
    + '<td class="px-4 py-3"><input class="pfld" type="number" min="0" name="c_limit[]" value="0"></td>'
    + '<td class="px-4 py-3"><input class="pfld" type="number" min="0" name="c_used[]" value="0"></td>'
    + '<td class="px-4 py-3"><input class="pfld" type="number" min="0" name="c_min[]" placeholder="—"></td>'
    + '<td class="px-4 py-3"><input class="pfld" type="number" min="0" name="c_maxd[]" placeholder="—"></td>'
    + '<td class="px-4 py-3 text-center"><input type="checkbox" name="c_active[' + ri + ']" checked class="w-5 h-5 accent-emerald-500"></td>'
    + '<td class="px-4 py-3 text-center"><input type="checkbox" name="c_delete[' + ri + ']" class="w-5 h-5 accent-red-500"></td>';
  document.getElementById('rows').appendChild(tr);
  ri++;
}
</script>
</body>
</html>
