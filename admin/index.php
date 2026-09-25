<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$q = function ($sql) use ($pdo) { try { return $pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; } };
$qa = function ($sql) use ($pdo) { try { return $pdo->query($sql)->fetchAll(); } catch (\Throwable $e) { return []; } };

// ── system health: storage used + broken (missing) media files ──
function _dir_size($dir) {
    $size = 0;
    if (is_dir($dir)) {
        try {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $f) {
                $size += $f->getSize();
            }
        } catch (\Throwable $e) {}
    }
    return $size;
}
$storage_bytes   = _dir_size(__DIR__ . '/../uploads');
$storage_display = $storage_bytes > 1073741824 ? round($storage_bytes / 1073741824, 2) . ' GB' : round($storage_bytes / 1048576, 1) . ' MB';

$missing_media = 0;
$base_path = __DIR__ . '/../';
foreach ([
    ['pages', ['music_url', 'video_url', 'voice_url']],
    ['page_images', ['image_path']],
    ['page_replies', ['voice_path', 'image_path', 'video_path']],
] as [$tbl, $cols]) {
    try {
        $rows = $pdo->query("SELECT " . implode(',', $cols) . " FROM $tbl");
        while ($row = $rows->fetch()) {
            foreach ($cols as $c) {
                if (!empty($row[$c]) && !preg_match('#^https?://#i', $row[$c]) && !file_exists($base_path . ltrim($row[$c], '/'))) {
                    $missing_media++;
                }
            }
        }
    } catch (\Throwable $e) {}
}

// ── headline stats ──
$total_users   = (int)$q("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_pages   = (int)$q("SELECT COUNT(*) FROM pages");
$active_pages  = (int)$q("SELECT COUNT(*) FROM pages WHERE status = 'published'");
$total_views   = (int)$q("SELECT COUNT(*) FROM page_views");
$rev_total     = (int)$q("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='captured'");

// ── 7-day vs previous-7-day deltas ──
function pct($now, $prev) { if ($prev <= 0) return $now > 0 ? 100 : 0; return round(($now - $prev) / $prev * 100, 1); }
$d = [];
foreach ([
    'pages'   => ['pages', 'created_at', ''],
    'views'   => ['page_views', 'created_at', ''],
    'users'   => ['users', 'created_at', "AND role='user'"],
    'rev'     => ['payments', 'created_at', "AND status='captured'"],
] as $k => $c) {
    $col = ($k === 'rev') ? 'COALESCE(SUM(amount),0)' : 'COUNT(*)';
    $now  = (int)$q("SELECT $col FROM {$c[0]} WHERE {$c[1]} >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) {$c[2]}");
    $prev = (int)$q("SELECT $col FROM {$c[0]} WHERE {$c[1]} >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND {$c[1]} < DATE_SUB(CURDATE(), INTERVAL 6 DAY) {$c[2]}");
    $d[$k] = pct($now, $prev);
}

// ── 7-day views series for the chart ──
$labels = []; $viewsSeries = []; $visSeries = [];
$byday = [];
foreach ($qa("SELECT DATE(created_at) d, COUNT(*) c FROM page_views WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)") as $r) $byday[$r['d']] = (int)$r['c'];
$visday = [];
foreach ($qa("SELECT DATE(created_at) d, COUNT(DISTINCT ip_address) c FROM page_views WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)") as $r) $visday[$r['d']] = (int)$r['c'];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d M', strtotime($day));
    $viewsSeries[] = $byday[$day] ?? 0;
    $visSeries[] = $visday[$day] ?? (int)round(($byday[$day] ?? 0) * 0.65);
}

// ── pages by category (donut) ──
$cat_rows = $qa("SELECT COALESCE(NULLIF(category,''),'other') c, COUNT(*) n FROM pages GROUP BY category ORDER BY n DESC");
$cat_labels = []; $cat_vals = [];
foreach ($cat_rows as $r) { $cat_labels[] = ucfirst($r['c']); $cat_vals[] = (int)$r['n']; }
if (empty($cat_vals)) { $cat_labels = ['No pages yet']; $cat_vals = [1]; }

// ── recent pages (with view counts) ──
$recent_pages = $qa("SELECT p.*, u.name AS user_name,
        (SELECT COUNT(*) FROM page_views v WHERE v.page_id = p.id) AS views
    FROM pages p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 6");

// ── recent activity feed ──
$activity = [];
foreach ($qa("SELECT title, created_at FROM pages ORDER BY created_at DESC LIMIT 3") as $r)
    $activity[] = ['i' => '📄', 'c' => '#db2777', 't' => 'New page created', 's' => $r['title'], 'at' => $r['created_at']];
foreach ($qa("SELECT name, email, created_at FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 2") as $r)
    $activity[] = ['i' => '👤', 'c' => '#2563eb', 't' => 'New user registered', 's' => $r['email'] ?: $r['name'], 'at' => $r['created_at']];
foreach ($qa("SELECT py.amount, u.name un, py.created_at FROM payments py LEFT JOIN users u ON py.user_id=u.id WHERE py.status='captured' ORDER BY py.created_at DESC LIMIT 2") as $r)
    $activity[] = ['i' => '💰', 'c' => '#16a34a', 't' => 'Payment received', 's' => '₹' . number_format($r['amount'] / 100) . ' from ' . ($r['un'] ?? 'user'), 'at' => $r['created_at']];
usort($activity, function ($a, $b) { return strtotime($b['at']) - strtotime($a['at']); });
$activity = array_slice($activity, 0, 6);
function ago($ts) {
    $s = time() - strtotime($ts);
    if ($s < 60) return $s . 's ago';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' hr ago';
    return floor($s / 86400) . 'd ago';
}

$stat_cards = [
    ['Total Pages',  number_format($total_pages), '📄', '#db2777', '#fdf2f8', $d['pages']],
    ['Total Views',  number_format($total_views), '👁️', '#7c3aed', '#f5f3ff', $d['views']],
    ['Total Users',  number_format($total_users), '👥', '#2563eb', '#eff6ff', $d['users']],
    ['Total Revenue','₹' . number_format($rev_total / 100), '💰', '#16a34a', '#f0fdf4', $d['rev']],
    ['Active Pages', number_format($active_pages), '⚡', '#f59e0b', '#fffbeb', $d['pages']],
];
$cat_colors = ['#ec4899', '#8b5cf6', '#3b82f6', '#f59e0b', '#6366f1', '#10b981', '#f43f5e', '#94a3b8'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — <?= h(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>tailwind.config = { theme: { extend: { fontFamily: { sans:['Inter','sans-serif'], heading:['Outfit','sans-serif'] } } } }</script>
<style>
  body { font-family:'Inter',sans-serif; }
  .card { background:#fff; border:1px solid #eef0f4; border-radius:20px; box-shadow:0 4px 18px rgba(30,41,59,0.05); }
  .heading { font-family:'Outfit',sans-serif; }
  .qa { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; padding:18px 8px; border-radius:16px; background:#fff; border:1px solid #eef0f4; text-decoration:none; transition:.18s; }
  .qa:hover { transform:translateY(-3px); box-shadow:0 12px 26px rgba(219,39,119,.12); border-color:#fbcfe8; }
</style>
</head>
<body>

<?php $ADMIN_TITLE = "Here's what's happening with your SoulPages platform today"; include '_nav.php'; ?>

<main class="max-w-[1400px] mx-auto px-5 sm:px-8 py-7">

  <!-- STAT CARDS -->
  <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
    <?php foreach ($stat_cards as $c): $up = $c[5] >= 0; ?>
    <div class="card p-5">
      <div class="flex items-start justify-between">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center text-lg" style="background:<?= $c[4] ?>;"><?= $c[2] ?></div>
      </div>
      <p class="text-xs text-slate-500 font-semibold mt-3"><?= $c[0] ?></p>
      <p class="text-2xl font-extrabold heading text-slate-900 mt-0.5"><?= $c[1] ?></p>
      <p class="text-xs mt-1.5 font-semibold" style="color:<?= $up ? '#16a34a' : '#dc2626' ?>;">
        <?= $up ? '↑' : '↓' ?> <?= abs($c[5]) ?>% <span class="text-slate-400 font-medium">from last 7 days</span>
      </p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- CHARTS ROW -->
  <div class="grid lg:grid-cols-3 gap-5 mb-6">
    <div class="card p-6 lg:col-span-2">
      <div class="flex items-center justify-between mb-4">
        <h2 class="heading font-bold text-slate-900">Overview</h2>
        <span class="text-xs text-slate-400 font-semibold">Last 7 Days</span>
      </div>
      <canvas id="ovChart" height="120"></canvas>
    </div>
    <div class="card p-6">
      <h2 class="heading font-bold text-slate-900 mb-4">Pages by Category</h2>
      <canvas id="catChart" height="180"></canvas>
      <p class="text-center text-xs text-slate-400 mt-3">Total Pages <b class="text-slate-700"><?= number_format($total_pages) ?></b></p>
    </div>
  </div>

  <!-- RECENT + ACTIVITY -->
  <div class="grid lg:grid-cols-3 gap-5 mb-6">
    <div class="card p-6 lg:col-span-2">
      <div class="flex items-center justify-between mb-4">
        <h2 class="heading font-bold text-slate-900">Recent Pages</h2>
        <a href="pages.php" class="text-xs font-bold text-pink-600 hover:underline">View All →</a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
            <th class="py-2 font-semibold">Page Title</th><th class="py-2 font-semibold">Category</th>
            <th class="py-2 font-semibold">User</th><th class="py-2 font-semibold">Views</th>
            <th class="py-2 font-semibold">Status</th><th class="py-2 font-semibold">Created</th>
          </tr></thead>
          <tbody>
            <?php foreach ($recent_pages as $rp): ?>
            <tr class="border-b border-slate-50 last:border-0">
              <td class="py-3 pr-2 font-semibold text-slate-800 max-w-[180px] truncate"><?= h($rp['title']) ?></td>
              <td class="py-3 pr-2"><span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-pink-50 text-pink-600"><?= h(ucfirst($rp['category'] ?: '—')) ?></span></td>
              <td class="py-3 pr-2 text-slate-500"><?= h($rp['user_name'] ?? 'Guest') ?></td>
              <td class="py-3 pr-2 text-slate-700 font-semibold"><?= number_format($rp['views']) ?></td>
              <td class="py-3 pr-2"><span class="text-[11px] font-bold px-2.5 py-1 rounded-full <?= $rp['status'] === 'published' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' ?>"><?= $rp['status'] === 'published' ? 'Active' : ucfirst($rp['status']) ?></span></td>
              <td class="py-3 text-slate-400 text-xs whitespace-nowrap"><?= date('d M Y', strtotime($rp['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recent_pages)): ?><tr><td colspan="6" class="py-6 text-center text-slate-400">No pages yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-6">
      <h2 class="heading font-bold text-slate-900 mb-4">Recent Activity</h2>
      <div class="space-y-4">
        <?php foreach ($activity as $a): ?>
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center text-sm shrink-0" style="background:<?= $a['c'] ?>1a;"><?= $a['i'] ?></div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-800"><?= h($a['t']) ?></p>
            <p class="text-xs text-slate-500 truncate"><?= h($a['s']) ?></p>
          </div>
          <span class="text-[11px] text-slate-400 whitespace-nowrap"><?= ago($a['at']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($activity)): ?><p class="text-sm text-slate-400 text-center py-4">No activity yet.</p><?php endif; ?>
      </div>

      <!-- System Health -->
      <div class="mt-6 pt-5 border-t border-slate-100">
        <h2 class="heading font-bold text-slate-900 mb-3">System Health</h2>
        <div class="space-y-3">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-sm shrink-0" style="background:#eff6ff;">💾</div>
            <div class="flex-1"><p class="text-sm font-semibold text-slate-800">Storage Used</p><p class="text-xs text-slate-500">across all uploads</p></div>
            <span class="text-sm font-extrabold text-blue-600"><?= $storage_display ?></span>
          </div>
          <a href="media-debug.php" class="flex items-center gap-3 rounded-xl -mx-1 px-1 py-1 hover:bg-slate-50 transition">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-sm shrink-0" style="background:<?= $missing_media > 0 ? '#fef2f2' : '#f0fdf4' ?>;"><?= $missing_media > 0 ? '⚠️' : '✅' ?></div>
            <div class="flex-1"><p class="text-sm font-semibold text-slate-800">Media Health</p><p class="text-xs text-slate-500"><?= $missing_media > 0 ? 'broken / missing files — tap to review' : 'all media files present' ?></p></div>
            <span class="text-sm font-extrabold <?= $missing_media > 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= $missing_media > 0 ? $missing_media . ' broken' : 'Healthy' ?></span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- QUICK ACTIONS -->
  <div class="card p-6">
    <h2 class="heading font-bold text-slate-900 mb-4">Quick Actions</h2>
    <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
      <?php
      $qas = [
          ['../create.php', '📝', 'Add Page', '#fdf2f8'],
          ['categories.php', '🗂️', 'Categories', '#f5f3ff'],
          ['premium-demos.php', '✨', 'Premium', '#eff6ff'],
          ['invitations.php', '💌', 'Invitations', '#fff7ed'],
          ['music.php', '🎵', 'Music', '#f0fdf4'],
          ['analytics.php', '📈', 'Analytics', '#fdf4ff'],
      ];
      foreach ($qas as $a): ?>
      <a class="qa" href="<?= $a[0] ?>">
        <span class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl" style="background:<?= $a[3] ?>;"><?= $a[1] ?></span>
        <span class="text-xs font-bold text-slate-600"><?= $a[2] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <p class="text-center text-xs text-slate-400 mt-8">© <?= date('Y') ?> <?= h(SITE_NAME) ?> · Made with 💗 for beautiful connections</p>
</main>

<script>
const gpink = document.getElementById('ovChart').getContext('2d');
const grad = gpink.createLinearGradient(0, 0, 0, 240);
grad.addColorStop(0, 'rgba(236,72,153,0.28)'); grad.addColorStop(1, 'rgba(236,72,153,0)');
new Chart(gpink, {
  type: 'line',
  data: { labels: <?= json_encode($labels) ?>, datasets: [
    { label: 'Views', data: <?= json_encode($viewsSeries) ?>, borderColor: '#ec4899', backgroundColor: grad, fill: true, tension: .4, borderWidth: 3, pointRadius: 3, pointBackgroundColor: '#ec4899' },
    { label: 'Visitors', data: <?= json_encode($visSeries) ?>, borderColor: '#94a3b8', borderDash: [5,5], fill: false, tension: .4, borderWidth: 2, pointRadius: 0 }
  ]},
  options: { responsive: true, plugins: { legend: { position: 'top', align: 'start', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } } },
    scales: { y: { grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8', font: { size: 11 } } }, x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } } } }
});
new Chart(document.getElementById('catChart'), {
  type: 'doughnut',
  data: { labels: <?= json_encode($cat_labels) ?>, datasets: [{ data: <?= json_encode($cat_vals) ?>, backgroundColor: <?= json_encode(array_slice($cat_colors, 0, max(1, count($cat_vals)))) ?>, borderWidth: 0 }] },
  options: { cutout: '66%', plugins: { legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 }, color: '#475569' } } } }
});
</script>
</body>
</html>
