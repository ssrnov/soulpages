<?php
require_once '../includes/functions.php';
if (!is_logged_in() || !is_admin()) redirect('login.php');

// ── 30-day series helpers ──
function day_series($pdo, $sql) {
    $out = [];
    for ($i = 29; $i >= 0; $i--) $out[date('Y-m-d', strtotime("-{$i} days"))] = 0;
    try {
        foreach ($pdo->query($sql)->fetchAll() as $r) {
            if (isset($out[$r['d']])) $out[$r['d']] = (float)$r['v'];
        }
    } catch (PDOException $e) {}
    return $out;
}
$pages_series = day_series($pdo, "SELECT DATE(created_at) d, COUNT(*) v FROM pages WHERE created_at >= CURDATE() - INTERVAL 30 DAY GROUP BY DATE(created_at)");
$users_series = day_series($pdo, "SELECT DATE(created_at) d, COUNT(*) v FROM users WHERE created_at >= CURDATE() - INTERVAL 30 DAY GROUP BY DATE(created_at)");
$rev_series   = day_series($pdo, "SELECT DATE(created_at) d, COALESCE(SUM(amount),0)/100 v FROM payments WHERE status='captured' AND created_at >= CURDATE() - INTERVAL 30 DAY GROUP BY DATE(created_at)");
$views_series = day_series($pdo, "SELECT DATE(viewed_at) d, COUNT(*) v FROM page_views WHERE viewed_at >= CURDATE() - INTERVAL 30 DAY GROUP BY DATE(viewed_at)");

// ── Top pages / categories ──
$top_pages = []; $top_cats = [];
try { $top_pages = $pdo->query("SELECT p.title, p.slug, p.category, COUNT(v.id) views FROM pages p JOIN page_views v ON v.page_id = p.id GROUP BY p.id ORDER BY views DESC LIMIT 10")->fetchAll(); } catch (PDOException $e) {}
try { $top_cats = $pdo->query("SELECT category, COUNT(*) c FROM pages GROUP BY category ORDER BY c DESC LIMIT 8")->fetchAll(); } catch (PDOException $e) {}

// ── Device / browser breakdown (simple UA parsing over recent views) ──
$devices = ['Mobile' => 0, 'Desktop' => 0, 'Tablet' => 0];
$browsers = ['Chrome' => 0, 'Safari' => 0, 'Firefox' => 0, 'Edge' => 0, 'Other' => 0];
try {
    foreach ($pdo->query("SELECT user_agent FROM page_views ORDER BY id DESC LIMIT 2000")->fetchAll() as $r) {
        $ua = $r['user_agent'] ?? '';
        if (preg_match('/iPad|Tablet/i', $ua)) $devices['Tablet']++;
        elseif (preg_match('/Mobile|Android|iPhone/i', $ua)) $devices['Mobile']++;
        else $devices['Desktop']++;
        if (stripos($ua, 'Edg') !== false) $browsers['Edge']++;
        elseif (stripos($ua, 'Chrome') !== false) $browsers['Chrome']++;
        elseif (stripos($ua, 'Safari') !== false) $browsers['Safari']++;
        elseif (stripos($ua, 'Firefox') !== false) $browsers['Firefox']++;
        else $browsers['Other']++;
    }
} catch (PDOException $e) {}

$labels = json_encode(array_map(function ($d) { return date('j M', strtotime($d)); }, array_keys($pages_series)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
  .card { background:rgba(15,23,42,0.5); border:1px solid #1e293b; border-radius:18px; padding:20px; }
</style>
</head>
<body class="min-h-screen">
<?php $ADMIN_TITLE = 'Analytics'; include '_nav.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <h1 class="heading text-2xl font-extrabold text-white mb-6">📈 Analytics — last 30 days</h1>

  <div class="grid lg:grid-cols-2 gap-5 mb-5">
    <div class="card"><h2 class="text-sm font-bold text-slate-300 mb-3">📄 Pages Created</h2><canvas id="chPages" height="120"></canvas></div>
    <div class="card"><h2 class="text-sm font-bold text-slate-300 mb-3">👥 New Users</h2><canvas id="chUsers" height="120"></canvas></div>
    <div class="card"><h2 class="text-sm font-bold text-slate-300 mb-3">💰 Revenue (₹)</h2><canvas id="chRev" height="120"></canvas></div>
    <div class="card"><h2 class="text-sm font-bold text-slate-300 mb-3">👁️ Page Views</h2><canvas id="chViews" height="120"></canvas></div>
  </div>

  <div class="grid lg:grid-cols-3 gap-5 mb-5">
    <div class="card"><h2 class="text-sm font-bold text-slate-300 mb-3">📱 Devices</h2><canvas id="chDev" height="200"></canvas></div>
    <div class="card"><h2 class="text-sm font-bold text-slate-300 mb-3">🌐 Browsers</h2><canvas id="chBrow" height="200"></canvas></div>
    <div class="card">
      <h2 class="text-sm font-bold text-slate-300 mb-3">🗂️ Pages by Category</h2>
      <?php foreach ($top_cats as $c): ?>
      <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-800/50"><span class="text-slate-300"><?= h($c['category']) ?></span><span class="font-bold text-pink-400"><?= (int)$c['c'] ?></span></div>
      <?php endforeach; ?>
      <?php if (!$top_cats): ?><p class="text-xs text-slate-500">No data yet.</p><?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2 class="text-sm font-bold text-slate-300 mb-3">🔥 Most Viewed Pages</h2>
    <table class="w-full text-sm">
      <thead><tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-800"><th class="py-2">Title</th><th class="py-2">Category</th><th class="py-2 text-right">Views</th></tr></thead>
      <tbody>
        <?php foreach ($top_pages as $tp): ?>
        <tr class="border-b border-slate-800/50"><td class="py-2.5"><?php if (is_super_admin()): ?><a href="../p.php?s=<?= h($tp['slug']) ?>" target="_blank" class="text-pink-400 hover:underline"><?= h(mb_substr($tp['title'], 0, 60)) ?></a><?php else: ?><span class="text-slate-300"><?= h(mb_substr($tp['title'], 0, 60)) ?></span><?php endif; ?></td><td class="py-2.5 text-slate-500 text-xs"><?= h($tp['category']) ?></td><td class="py-2.5 text-right font-bold text-white"><?= (int)$tp['views'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$top_pages): ?><tr><td colspan="3" class="py-6 text-center text-slate-500 text-xs">No views recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<script>
const L = <?= $labels ?>;
const opts = { plugins:{legend:{display:false}}, scales:{ x:{ ticks:{ color:'#64748b', maxTicksLimit:8 }, grid:{ color:'rgba(51,65,85,.25)'}}, y:{ ticks:{ color:'#64748b' }, grid:{ color:'rgba(51,65,85,.25)'}, beginAtZero:true } } };
function line(id, data, color) { new Chart(document.getElementById(id), { type:'line', data:{ labels:L, datasets:[{ data, borderColor:color, backgroundColor:color+'22', fill:true, tension:.35, pointRadius:0, borderWidth:2 }]}, options:opts }); }
line('chPages', <?= json_encode(array_values($pages_series)) ?>, '#ec4899');
line('chUsers', <?= json_encode(array_values($users_series)) ?>, '#a78bfa');
line('chRev',   <?= json_encode(array_values($rev_series)) ?>, '#34d399');
line('chViews', <?= json_encode(array_values($views_series)) ?>, '#38bdf8');
new Chart(document.getElementById('chDev'), { type:'doughnut', data:{ labels:<?= json_encode(array_keys($devices)) ?>, datasets:[{ data:<?= json_encode(array_values($devices)) ?>, backgroundColor:['#ec4899','#a78bfa','#f59e0b'] }]}, options:{ plugins:{ legend:{ labels:{ color:'#94a3b8' } } } } });
new Chart(document.getElementById('chBrow'), { type:'doughnut', data:{ labels:<?= json_encode(array_keys($browsers)) ?>, datasets:[{ data:<?= json_encode(array_values($browsers)) ?>, backgroundColor:['#38bdf8','#34d399','#f97316','#818cf8','#64748b'] }]}, options:{ plugins:{ legend:{ labels:{ color:'#94a3b8' } } } } });
</script>
</body>
</html>
