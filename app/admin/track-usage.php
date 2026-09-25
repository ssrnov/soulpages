<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

function fmt_dur($ms) {
    $s = (int)($ms / 1000); $h = intdiv($s,3600); $m = intdiv($s%3600,60);
    if ($h>0) return "{$h}h {$m}m"; if ($m>0) return "{$m}m"; return "{$s}s";
}
function fmt_bytes($b) {
    $b = (float)$b;
    if ($b >= 1073741824) return round($b/1073741824, 2).' GB';
    if ($b >= 1048576)    return round($b/1048576, 1).' MB';
    if ($b >= 1024)       return round($b/1024, 1).' KB';
    return (int)$b.' B';
}
function usage_cat($pkg) {
    $a = strtolower((string)$pkg);
    foreach (['bgmi','pubg','game','supercell','com.king','roblox','freefire','ludo','carrom','minecraft'] as $k) if(strpos($a,$k)!==false) return 'Gaming';
    foreach (['youtube','netflix','spotify','primevideo','hotstar','music','jiocinema','gaana','wynk'] as $k) if(strpos($a,$k)!==false) return 'Entertainment';
    foreach (['instagram','facebook','snapchat','twitter','tiktok','whatsapp','telegram','messenger','discord','reddit'] as $k) if(strpos($a,$k)!==false) return 'Social';
    foreach (['gmail','docs','office','word','excel','chrome','browser','keep','calendar','drive','outlook','zoom','meet'] as $k) if(strpos($a,$k)!==false) return 'Productivity';
    return 'Other';
}

$uid = (int)($_GET['user'] ?? 0);
$uwhere = $uid ? 'AND ue.user_id = '.$uid : '';

// Selected day (defaults to today). Kept to the last 30 days — matches the
// retention window in db.php so we don't show stale/empty ranges.
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$isToday = ($day === date('Y-m-d'));
$dq = $pdo->quote($day);

// Per-app usage for the selected day (with opens)
$today = $pdo->query("SELECT ue.app_name, SUM(ue.foreground_ms) ms, SUM(ue.open_count) opens FROM usage_events ue
                      WHERE ue.day=$dq AND ue.app_name NOT LIKE '%soulsync%' $uwhere GROUP BY ue.app_name ORDER BY ms DESC")->fetchAll();

// Per-app NETWORK usage for that day (bytes + estimated active duration).
$netApps = []; $seenApps = [];
try {
    $netApps = $pdo->query("SELECT app_name, package, SUM(bytes) bytes, SUM(active_ms) active_ms FROM app_net_daily
                            WHERE day=$dq AND app_name NOT LIKE '%soulsync%' " . ($uid ? "AND user_id=$uid" : "") . "
                            GROUP BY package ORDER BY bytes DESC LIMIT 60")->fetchAll();
    foreach ($pdo->query("SELECT DISTINCT LOWER(app_name) a FROM usage_events WHERE day=$dq " . ($uid ? "AND user_id=$uid" : ""))->fetchAll() as $r) $seenApps[$r['a']] = true;
} catch (\Throwable $e) {}

// Usage view mode: combined (screen + network) | screen only | network only.
$view = $_GET['view'] ?? 'combined';
$comb = [];
foreach ($today as $a) $comb[$a['app_name']] = ['name'=>$a['app_name'], 'screen'=>(int)$a['ms'], 'net'=>0, 'opens'=>(int)$a['opens']];
foreach ($netApps as $n) {
    $k = $n['app_name'];
    if (!isset($comb[$k])) $comb[$k] = ['name'=>$k, 'screen'=>0, 'net'=>0, 'opens'=>0];
    $comb[$k]['net'] += (int)($n['active_ms'] ?? 0);
}
foreach ($comb as &$c) $c['total'] = $c['screen'] + $c['net']; unset($c);
usort($comb, fn($a,$b)=> $b['total'] <=> $a['total']);

// ── App-watch alerts: owner sets which apps trigger a push notification ──
if ($uid && ($_POST['watch_add'] ?? '') !== '') {
    $kw = trim($_POST['watch_add']);
    if ($kw !== '') { try { $pdo->prepare("INSERT IGNORE INTO app_watch (user_id, keyword) VALUES (?, ?)")->execute([$uid, $kw]); } catch (\Throwable $e) {} }
    header("Location: ?user=$uid"); exit;
}
if ($uid && ($_GET['watch_del'] ?? '') !== '') {
    try { $pdo->prepare("DELETE FROM app_watch WHERE id=? AND user_id=?")->execute([(int)$_GET['watch_del'], $uid]); } catch (\Throwable $e) {}
    header("Location: ?user=$uid"); exit;
}
$watches = [];
if ($uid) { try { $w = $pdo->prepare("SELECT id, keyword, last_notified FROM app_watch WHERE user_id=? ORDER BY keyword"); $w->execute([$uid]); $watches = $w->fetchAll(); } catch (\Throwable $e) {} }

// App timeline for the selected day (which app at which time) with computed duration.
$timeline = [];
try {
    $tq = $pdo->query("SELECT app, started_at FROM app_timeline
                       WHERE DATE(started_at)=$dq AND app NOT LIKE '%soulsync%' " . ($uid ? "AND user_id=$uid" : "") . "
                       ORDER BY started_at DESC LIMIT 200");
    $rowsT = $tq->fetchAll();
    // duration = time until the NEXT (newer) row; newest = "now" only for today,
    // else the end of that day (23:59:59) so past days show sane durations.
    $dayEnd = $isToday ? time() : strtotime($day . ' 23:59:59');
    for ($i = 0; $i < count($rowsT); $i++) {
        $start = strtotime($rowsT[$i]['started_at']);
        $end = ($i === 0) ? $dayEnd : strtotime($rowsT[$i-1]['started_at']);
        $timeline[] = ['app'=>$rowsT[$i]['app'], 'at'=>$rowsT[$i]['started_at'], 'dur'=>max(0,$end-$start)];
    }
} catch (\Throwable $e) {}

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="app-usage-'.$day.'.csv"');
    $out = fopen('php://output','w'); fputcsv($out, ['App','Usage','Opens','Share %']);
    $tot = array_sum(array_column($today,'ms'));
    foreach ($today as $a) fputcsv($out, [$a['app_name'], fmt_dur($a['ms']), (int)$a['opens'], $tot?round($a['ms']/$tot*100):0]);
    fclose($out); exit;
}

$PAGE_TITLE = 'App Usage';
$PAGE_SUB   = 'Monitor how users spend time across applications';
$PAGE_ICON  = '📱';

$totalMs = array_sum(array_column($today, 'ms'));
$totalOpens = array_sum(array_column($today, 'opens'));
$appsUsed = count($today);
$mostApp = $today[0]['app_name'] ?? '—';
$mostPct = $totalMs>0 && $today ? round($today[0]['ms']/$totalMs*100) : 0;
$avgSession = $totalOpens>0 ? fmt_dur($totalMs/$totalOpens) : '—';
$activeUsers = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM usage_events WHERE day=$dq")->fetchColumn();
$lastSync = $pdo->query("SELECT MAX(last_seen) FROM device_status")->fetchColumn();

// Categories today
$catMs = ['Gaming'=>0,'Social'=>0,'Entertainment'=>0,'Productivity'=>0,'Other'=>0];
foreach ($today as $a) $catMs[usage_cat($a['app_name'])] += (int)$a['ms'];

// Weekly total
$weekRows = $pdo->query("SELECT day, SUM(foreground_ms) ms FROM usage_events
                         WHERE day >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) ".($uid?"AND user_id=$uid":"")."
                         GROUP BY day ORDER BY day")->fetchAll();
$weekMap=[]; foreach($weekRows as $r) $weekMap[$r['day']]=(int)$r['ms'];
$weekLabels=[];$weekData=[]; for($i=6;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i days"));$weekLabels[]=date('D',strtotime($d));$weekData[]=round(($weekMap[$d]??0)/3600000,2);}

// Hourly trend (today)
$hourRows = $pdo->query("SELECT hour, SUM(minutes) m FROM usage_hourly WHERE day=$dq ".($uid?"AND user_id=$uid":"")." GROUP BY hour")->fetchAll();
$hmap=[]; foreach($hourRows as $r) $hmap[(int)$r['hour']]=(int)$r['m'];
$hourData=[]; for($h=0;$h<24;$h++) $hourData[]=$hmap[$h]??0;
$hourLabels=[]; for($h=0;$h<24;$h++) $hourLabels[]=($h%3==0)?(($h%12==0?12:$h%12).($h<12?'a':'p')):'';

// Heatmap last 7 days x 24h
$heat = $pdo->query("SELECT day, hour, SUM(minutes) m FROM usage_hourly
                     WHERE day >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) ".($uid?"AND user_id=$uid":"")." GROUP BY day,hour")->fetchAll();
$heatMap=[]; $heatMax=1;
foreach($heat as $r){ $heatMap[$r['day']][(int)$r['hour']]=(int)$r['m']; if($r['m']>$heatMax)$heatMax=(int)$r['m']; }

// Device overview
$devQ = $pdo->prepare("SELECT d.*, u.name, u.is_online, u.presence_at FROM device_status d JOIN users u ON u.id=d.user_id
                       ".($uid?"WHERE d.user_id=$uid":"")." ORDER BY d.last_seen DESC LIMIT 1");
$devQ->execute(); $dev = $devQ->fetch();

$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();
$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">All Users</option>';
foreach ($users as $u) $PAGE_TOOLS .= '<option value="'.(int)$u['id'].'" '.($uid===(int)$u['id']?'selected':'').'>'.ss_admin_h($u['name']).'</option>';
$PAGE_TOOLS .= '</select><input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" min="'.date('Y-m-d', strtotime('-30 days')).'" onchange="this.form.submit()"></form><a class="btn" href="?user='.$uid.'&day='.ss_admin_h($day).'&export=csv">⬇ CSV</a><a class="btn" href="?user='.$uid.'&day='.ss_admin_h($day).'" onclick="location.reload();return false">🔄</a>';

$maxMs = $today ? max(array_column($today,'ms')) : 1;
require __DIR__ . '/_dark_head.php';
?>
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">🕐</span> Screen Time · Today</div><div class="v"><?= fmt_dur($totalMs) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📦</span> Apps Used</div><div class="v"><?= $appsUsed ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🏆</span> Most Used</div><div class="v" style="font-size:1.15rem"><?= ss_admin_h($mostApp) ?></div><div class="d"><?= fmt_dur($today[0]['ms']??0) ?> (<?= $mostPct ?>%)</div></div>
  <div class="stat"><div class="h"><span class="dot">📲</span> App Opens · Today</div><div class="v"><?= number_format($totalOpens) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">⏱️</span> Avg Session</div><div class="v" style="font-size:1.3rem"><?= $avgSession ?></div></div>
  <div class="stat"><div class="h"><span class="dot">👥</span> Active Users</div><div class="v"><?= $activeUsers ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🔄</span> Last Sync</div><div class="v" style="font-size:1rem"><?= $lastSync?date('d M, H:i',strtotime($lastSync)):'—' ?></div></div>
</div>

<?php if(!$today): ?>
  <div class="panel"><div class="empty">No app usage data available yet.<br><span style="font-size:.85rem">Tracking begins after <b>App Usage Access</b> is granted in the app.</span></div></div>
<?php else: ?>

<div class="panel">
  <h3>Screen Time Trend — Today (minutes/hour)</h3>
  <canvas id="hourly" height="80"></canvas>
</div>

<div class="grid g2">
  <div class="panel">
    <h3>Top Apps · Today</h3>
    <?php foreach(array_slice($today,0,10) as $a): $pct=round($a['ms']/$maxMs*100); ?>
      <div class="barrow"><div class="nm"><?= ss_admin_h($a['app_name']) ?></div><div class="tr"><div class="bar" style="width:<?= $pct ?>%"></div></div><div class="vl"><?= fmt_dur($a['ms']) ?> · <?= (int)$a['opens'] ?>x</div></div>
    <?php endforeach; ?>
  </div>
  <div class="panel">
    <h3>Usage Distribution</h3>
    <canvas id="donut" height="220"></canvas>
  </div>
</div>

<div class="cards">
  <div class="stat"><div class="h"><span class="dot">🎮</span> Gaming</div><div class="v" style="font-size:1.3rem"><?= fmt_dur($catMs['Gaming']) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">💬</span> Social</div><div class="v" style="font-size:1.3rem"><?= fmt_dur($catMs['Social']) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🎬</span> Entertainment</div><div class="v" style="font-size:1.3rem"><?= fmt_dur($catMs['Entertainment']) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">💼</span> Productivity</div><div class="v" style="font-size:1.3rem"><?= fmt_dur($catMs['Productivity']) ?></div></div>
</div>

<div class="grid g2">
  <div class="panel">
    <h3>Weekly Screen Time (hours)</h3>
    <canvas id="week" height="120"></canvas>
  </div>
  <div class="panel">
    <h3>Device Overview</h3>
    <?php if(!$dev): ?><div class="empty">No device data.</div><?php else: ?>
    <table>
      <tr><th>User</th><td><?= ss_admin_h($dev['name']) ?></td></tr>
      <tr><th>Current App</th><td><?= ss_admin_h($dev['current_app'] ?: '—') ?></td></tr>
      <tr><th>Device</th><td><?= ss_admin_h($dev['device_model'] ?: '—') ?></td></tr>
      <tr><th>Android</th><td><?= ss_admin_h($dev['os_version'] ?: '—') ?></td></tr>
      <tr><th>Battery</th><td><?= $dev['battery']!==null?(int)$dev['battery'].'%'.($dev['is_charging']?' ⚡':''):'—' ?></td></tr>
      <tr><th>Online</th><td><?= ($dev['is_online'] && !empty($dev['presence_at']) && strtotime($dev['presence_at'])>time()-70)?'<span class="pill">Online</span>':'Offline' ?></td></tr>
      <tr><th>Last Sync</th><td><?= $dev['last_seen']?date('d M, H:i',strtotime($dev['last_seen'])):'—' ?></td></tr>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <h3>Activity Heatmap (last 7 days)</h3>
  <div style="overflow-x:auto"><table style="border:none">
    <?php for($i=6;$i>=0;$i--): $d=date('Y-m-d',strtotime("-$i days")); ?>
      <tr style="border:none"><td style="border:none;color:var(--mut);font-size:.72rem;width:44px"><?= date('D',strtotime($d)) ?></td>
      <?php for($h=0;$h<24;$h++): $m=$heatMap[$d][$h]??0; $op=$m>0?max(0.12,min(1,$m/$heatMax)):0; ?>
        <td style="border:none;padding:2px"><div title="<?= $h ?>:00 · <?= $m ?>m" style="width:14px;height:14px;border-radius:3px;background:<?= $m>0?'rgba(236,72,153,'.$op.')':'#161d30' ?>"></div></td>
      <?php endfor; ?></tr>
    <?php endfor; ?>
    <tr style="border:none"><td style="border:none"></td><?php for($h=0;$h<24;$h+=3): ?><td colspan="3" style="border:none;color:var(--mut);font-size:.62rem"><?= $h ?></td><?php endfor; ?></tr>
  </table></div>
</div>

<!-- TABLE 1: App usage (screen time + network guess side by side) -->
<div class="panel">
  <h3>🔔 App Alerts — notify me (super admin)</h3>
  <?php if(!$uid): ?>
    <div class="empty">Pick a specific user in the top filter to set app alerts.</div>
  <?php else: ?>
    <div style="font-size:.75rem;color:var(--mut);margin-bottom:10px">Get a push on <b>your</b> phone when this user opens one of these apps (10-min cooldown per app).</div>
    <form method="post" style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap">
      <input class="sel" name="watch_add" placeholder="app name e.g. Instagram" style="flex:1;min-width:160px">
      <button class="btn" type="submit" style="background:#ec4899;color:#fff">+ Add</button>
    </form>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach(['Instagram','Snapchat','WhatsApp','Telegram','Facebook'] as $q): ?>
        <form method="post" style="display:inline"><input type="hidden" name="watch_add" value="<?= $q ?>"><button class="btn" type="submit" style="padding:4px 10px;font-size:.72rem"><?= $q ?> +</button></form>
      <?php endforeach; ?>
    </div>
    <?php if($watches): ?>
    <div style="margin-top:14px;display:flex;flex-direction:column;gap:6px">
      <?php foreach($watches as $wr): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:8px;border:1px solid var(--line)">
          <span>👀 <b><?= ss_admin_h($wr['keyword']) ?></b><?php if($wr['last_notified']):?> <span style="color:var(--mut);font-size:.7rem">· last alert <?= date('d M H:i',strtotime($wr['last_notified'])) ?></span><?php endif;?></span>
          <a class="btn" style="padding:3px 10px;font-size:.72rem;background:rgba(239,68,68,.15);color:#f87171" href="?user=<?= $uid ?>&watch_del=<?= (int)$wr['id'] ?>">Remove</a>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="panel">
  <h3>Most Used Apps (Detailed)</h3>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>App / Package</th><th>Today's Usage</th><th>Opens</th><th>Avg Session</th><th>Share</th></tr></thead>
    <tbody>
    <?php foreach($today as $a): $avg=$a['opens']>0?fmt_dur($a['ms']/$a['opens']):'—'; ?>
      <tr><td><b><?= ss_admin_h($a['app_name']) ?></b></td><td><?= fmt_dur($a['ms']) ?></td><td><?= (int)$a['opens'] ?></td><td><?= $avg ?></td><td><?= $totalMs?round($a['ms']/$totalMs*100):0 ?>%</td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<script>
Chart.defaults.color='#8592ad'; Chart.defaults.borderColor='#1e2740'; Chart.defaults.font.family='Inter';
new Chart(document.getElementById('hourly'),{type:'line',
  data:{labels:<?= json_encode($hourLabels) ?>,datasets:[{data:<?= json_encode($hourData) ?>,
    borderColor:'#ec4899',backgroundColor:'rgba(236,72,153,.15)',fill:true,tension:.35,pointRadius:0}]},
  options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#1e2740'}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('donut'),{type:'doughnut',
  data:{labels:<?= json_encode(array_map(fn($a)=>$a['app_name'],array_slice($today,0,6))) ?>,
    datasets:[{data:<?= json_encode(array_map(fn($a)=>round($a['ms']/60000),array_slice($today,0,6))) ?>,
      backgroundColor:['#ec4899','#a855f7','#6366f1','#3b82f6','#22c55e','#f59e0b'],borderWidth:0}]},
  options:{plugins:{legend:{position:'right',labels:{boxWidth:10,font:{size:11}}}},cutout:'62%'}});
new Chart(document.getElementById('week'),{type:'line',
  data:{labels:<?= json_encode($weekLabels) ?>,datasets:[{data:<?= json_encode($weekData) ?>,
    borderColor:'#a855f7',backgroundColor:'rgba(168,85,247,.15)',fill:true,tension:.4,pointRadius:3,pointBackgroundColor:'#a855f7'}]},
  options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#1e2740'}},x:{grid:{display:false}}}}});
</script>

<?php endif; require __DIR__ . '/_dark_foot.php';
