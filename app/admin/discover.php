<?php
// SoulSync Discover — admin dashboard + analytics + moderation.
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Discover';
$PAGE_SUB   = 'Discover users, analytics & moderation';
$PAGE_ICON  = '✨';
$flash = '';

$q1 = fn($sql) => (function() use ($pdo,$sql){ try { return $pdo->query($sql)->fetchColumn(); } catch(\Throwable $e){ return 0; } })();

// ── Moderation actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'verify')       $pdo->prepare("INSERT IGNORE INTO verified_users (user_id, verified_by) VALUES (?, 'admin')")->execute([$id]);
        elseif ($act === 'unverify') $pdo->prepare("DELETE FROM verified_users WHERE user_id=?")->execute([$id]);
        elseif ($act === 'hide')     $pdo->prepare("UPDATE discover_visibility SET mode='hidden', enabled=0 WHERE user_id=?")->execute([$id]);
        elseif ($act === 'enable')   $pdo->prepare("UPDATE discover_visibility SET mode='everyone', enabled=1 WHERE user_id=?")->execute([$id]);
        elseif ($act === 'remove') {
            foreach (['discover_profiles','discover_preferences','discover_visibility','user_interests',
                      'discover_location','favorites','waves','profile_views','daily_suggestions'] as $t)
                try { $pdo->prepare("DELETE FROM `$t` WHERE user_id=?")->execute([$id]); } catch(\Throwable $e){}
            $flash = "Removed user #$id from Discover.";
        }
        elseif ($act === 'resolve_report') { $pdo->prepare("DELETE FROM discover_reports WHERE id=?")->execute([$id]); $flash = "Report resolved."; }
        if ($flash === '') $flash = "Done.";
    } catch (\Throwable $e) { $flash = "Error: " . $e->getMessage(); }
}

// ── Stats ──
$stats = [
    'Total users'  => (int)$q1("SELECT COUNT(*) FROM discover_profiles"),
    'Online now'   => (int)$q1("SELECT COUNT(*) FROM discover_profiles p JOIN users u ON u.id=p.user_id WHERE u.is_online=1 AND u.presence_at > (NOW() - INTERVAL 70 SECOND)"),
    'Verified'     => (int)$q1("SELECT COUNT(*) FROM verified_users"),
    'Requests'     => (int)$q1("SELECT COUNT(*) FROM connection_requests"),
    'Accepted'     => (int)$q1("SELECT COUNT(*) FROM connection_requests WHERE status='accepted'"),
    'Rejected'     => (int)$q1("SELECT COUNT(*) FROM connection_requests WHERE status='rejected'"),
    'Matches'      => (int)$q1("SELECT COUNT(*) FROM discover_matches"),
    'Waves'        => (int)$q1("SELECT COUNT(*) FROM waves"),
    'Profile views'=> (int)$q1("SELECT COUNT(*) FROM profile_views"),
    'Favorites'    => (int)$q1("SELECT COUNT(*) FROM favorites"),
    'Reports'      => (int)$q1("SELECT COUNT(*) FROM discover_reports"),
    'Blocks'       => (int)$q1("SELECT COUNT(*) FROM discover_blocks"),
];
$accRate = $stats['Requests'] ? round($stats['Accepted'] / $stats['Requests'] * 100) : 0;

// ── Chart data ──
$genderRows = []; try { $genderRows = $pdo->query("SELECT gender, COUNT(*) c FROM discover_profiles GROUP BY gender")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(\Throwable $e){}
$ageBuckets = ['18-22'=>0,'23-27'=>0,'28-35'=>0,'36+'=>0];
try { foreach ($pdo->query("SELECT age FROM discover_profiles") as $r) {
    $a=(int)$r['age']; if($a<=22)$ageBuckets['18-22']++; elseif($a<=27)$ageBuckets['23-27']++; elseif($a<=35)$ageBuckets['28-35']++; else $ageBuckets['36+']++;
} } catch(\Throwable $e){}
$topInterests = []; try { $topInterests = $pdo->query("SELECT interest, COUNT(*) c FROM user_interests GROUP BY interest ORDER BY c DESC LIMIT 10")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(\Throwable $e){}
$daily = []; try { foreach ($pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM connection_requests WHERE created_at > (NOW() - INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY d") as $r) $daily[$r['d']]=(int)$r['c']; } catch(\Throwable $e){}
$growth = []; try { foreach ($pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM discover_profiles WHERE created_at > (NOW() - INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY d") as $r) $growth[$r['d']]=(int)$r['c']; } catch(\Throwable $e){}

// ── Reports + users lists ──
$reports = []; try { $reports = $pdo->query("SELECT r.id, r.reason, r.created_at, r.target_id, p.display_name
    FROM discover_reports r LEFT JOIN discover_profiles p ON p.user_id=r.target_id ORDER BY r.id DESC LIMIT 100")->fetchAll(); } catch(\Throwable $e){}

$search = trim($_GET['q'] ?? '');
$users = [];
try {
    $sql = "SELECT p.user_id, p.display_name, p.age, p.gender, p.city, v.enabled, v.mode,
                   (SELECT 1 FROM verified_users vu WHERE vu.user_id=p.user_id) verified,
                   u.username, u.is_online
            FROM discover_profiles p
            LEFT JOIN discover_visibility v ON v.user_id=p.user_id
            LEFT JOIN users u ON u.id=p.user_id";
    $args = [];
    if ($search !== '') { $sql .= " WHERE p.display_name LIKE ? OR u.username LIKE ?"; $args = ["%$search%","%$search%"]; }
    $sql .= " ORDER BY p.user_id DESC LIMIT 100";
    $st = $pdo->prepare($sql); $st->execute($args); $users = $st->fetchAll();
} catch(\Throwable $e){}

require __DIR__ . '/_dark_head.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if ($flash): ?><div class="panel" style="border-color:#22c55e;margin-bottom:14px"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<!-- Stat cards -->
<div class="cards" style="flex-wrap:wrap">
  <?php foreach ($stats as $k=>$v): ?>
    <div class="stat"><div class="h"><span class="dot">•</span> <?= ss_admin_h($k) ?></div><div class="v" style="font-size:1.4rem"><?= number_format($v) ?></div></div>
  <?php endforeach; ?>
  <div class="stat"><div class="h"><span class="dot">📈</span> Acceptance</div><div class="v" style="font-size:1.4rem"><?= $accRate ?>%</div></div>
</div>

<!-- Charts -->
<div class="grid g2">
  <div class="panel"><h3>Gender ratio</h3><canvas id="cGender" height="160"></canvas></div>
  <div class="panel"><h3>Age distribution</h3><canvas id="cAge" height="160"></canvas></div>
  <div class="panel"><h3>Top interests</h3><canvas id="cInterests" height="160"></canvas></div>
  <div class="panel"><h3>Daily requests (14d)</h3><canvas id="cDaily" height="160"></canvas></div>
</div>

<!-- Reports -->
<div class="panel" style="margin-top:14px">
  <h3>🚩 Reports (<?= count($reports) ?>)</h3>
  <?php if (!$reports): ?><div style="font-size:.85rem;color:var(--mut)">No reports.</div>
  <?php else: foreach ($reports as $r): ?>
    <div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.07)">
      <div style="flex:1"><b><?= ss_admin_h($r['display_name'] ?: ('User #'.$r['target_id'])) ?></b>
        <span style="color:var(--mut);font-size:.78rem"> — <?= ss_admin_h($r['reason'] ?: 'no reason') ?> · <?= ss_admin_h($r['created_at']) ?></span></div>
      <form method="post" style="margin:0"><input type="hidden" name="id" value="<?= (int)$r['target_id'] ?>"><button class="btn" name="action" value="hide">Hide user</button></form>
      <form method="post" style="margin:0"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn" name="action" value="resolve_report">Resolve</button></form>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- Moderation -->
<div class="panel" style="margin-top:14px">
  <h3>👥 Users — moderation</h3>
  <form method="get" style="margin:8px 0"><input class="sel" name="q" value="<?= ss_admin_h($search) ?>" placeholder="Search name / username…" style="width:60%"> <button class="btn pk">Search</button></form>
  <table style="width:100%;font-size:.82rem"><thead><tr style="text-align:left;color:var(--mut)">
    <th>User</th><th>Age</th><th>Gender</th><th>City</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($users as $u): ?>
    <tr style="border-top:1px solid rgba(255,255,255,.06)">
      <td style="padding:8px 0"><?= ss_admin_h($u['display_name']) ?> <span style="color:var(--mut)">@<?= ss_admin_h($u['username']) ?></span> <?= $u['verified']?'✅':'' ?></td>
      <td><?= (int)$u['age'] ?></td><td><?= ss_admin_h($u['gender']) ?></td><td><?= ss_admin_h($u['city']) ?></td>
      <td><?= (int)($u['enabled']??0)===1 ? '🟢 visible' : '⚪ hidden' ?></td>
      <td style="white-space:nowrap">
        <?php $mk = function($a,$l) use ($u){ return '<form method="post" style="display:inline;margin:0"><input type="hidden" name="id" value="'.(int)$u['user_id'].'"><button class="btn" name="action" value="'.$a.'" style="padding:3px 8px;font-size:.72rem">'.$l.'</button></form>'; }; ?>
        <?= $u['verified'] ? $mk('unverify','Unverify') : $mk('verify','Verify') ?>
        <?= (int)($u['enabled']??0)===1 ? $mk('hide','Hide') : $mk('enable','Enable') ?>
        <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Remove all Discover data for this user?')"><input type="hidden" name="id" value="<?= (int)$u['user_id'] ?>"><button class="btn" name="action" value="remove" style="padding:3px 8px;font-size:.72rem;border-color:#ef4444;color:#ef4444">Remove</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>

<script>
const gEl=document.getElementById('cGender'), aEl=document.getElementById('cAge'), iEl=document.getElementById('cInterests'), dEl=document.getElementById('cDaily');
const grid={color:'rgba(255,255,255,.06)'}, tick={color:'#94a3b8'};
new Chart(gEl,{type:'doughnut',data:{labels:<?= json_encode(array_keys($genderRows)) ?>,datasets:[{data:<?= json_encode(array_values($genderRows)) ?>,backgroundColor:['#3b82f6','#ec4899','#a855f7']}]},options:{plugins:{legend:{labels:{color:'#cbd5e1'}}}}});
new Chart(aEl,{type:'bar',data:{labels:<?= json_encode(array_keys($ageBuckets)) ?>,datasets:[{data:<?= json_encode(array_values($ageBuckets)) ?>,backgroundColor:'#e8467c'}]},options:{plugins:{legend:{display:false}},scales:{x:{grid,ticks:tick},y:{grid,ticks:tick}}}});
new Chart(iEl,{type:'bar',data:{labels:<?= json_encode(array_keys($topInterests)) ?>,datasets:[{data:<?= json_encode(array_values($topInterests)) ?>,backgroundColor:'#7c3aed'}]},options:{indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{grid,ticks:tick},y:{grid,ticks:tick}}}});
new Chart(dEl,{type:'line',data:{labels:<?= json_encode(array_keys($daily)) ?>,datasets:[{data:<?= json_encode(array_values($daily)) ?>,borderColor:'#e8467c',backgroundColor:'rgba(232,70,124,.15)',fill:true,tension:.3}]},options:{plugins:{legend:{display:false}},scales:{x:{grid,ticks:tick},y:{grid,ticks:tick}}}});
</script>
<?php require __DIR__ . '/_dark_foot.php';
