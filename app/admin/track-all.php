<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

$uid = (int)($_GET['user'] ?? 0);
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$dq = $pdo->quote($day);
$dayStart = strtotime($day . ' 00:00:00');
$isToday = ($day === date('Y-m-d'));
$uw = $uid ? "AND user_id=$uid" : "";

// minuteIndex (0..1439) => ['on'=>bool,'chg'=>bool,'app'=>str,'net'=>[apps],'notif'=>[]]
$M = [];
function _slot(&$M,$mi){ if(!isset($M[$mi])) $M[$mi]=['on'=>false,'chg'=>false,'call'=>false,'calllbl'=>'','app'=>'','net'=>[],'notif'=>[]]; return $mi; }

if ($uid) {
  // Phone ON (screen_sessions)
  try { foreach ($pdo->query("SELECT started_at, ended_at, last_ping_at FROM screen_sessions WHERE user_id=$uid AND DATE(started_at)=$dq")->fetchAll() as $r) {
    $s=(int)floor((strtotime($r['started_at'])-$dayStart)/60); $e=(int)floor(((($r['ended_at']?strtotime($r['ended_at']):strtotime($r['last_ping_at'])))-$dayStart)/60);
    for($m=$s;$m<=$e;$m++) if($m>=0&&$m<1440){_slot($M,$m);$M[$m]['on']=true;}
  } } catch(\Throwable $e){}
  // Charging (charge_sessions)
  try { foreach ($pdo->query("SELECT started_at, ended_at, last_ping_at FROM charge_sessions WHERE user_id=$uid AND DATE(started_at)=$dq")->fetchAll() as $r) {
    $s=(int)floor((strtotime($r['started_at'])-$dayStart)/60); $e=(int)floor(((($r['ended_at']?strtotime($r['ended_at']):strtotime($r['last_ping_at'])))-$dayStart)/60);
    for($m=$s;$m<=$e;$m++) if($m>=0&&$m<1440){_slot($M,$m);$M[$m]['chg']=true;}
  } } catch(\Throwable $e){}
  // Phone calls (call_sessions)
  try { foreach ($pdo->query("SELECT started_at, ended_at, last_ping_at, label FROM call_sessions WHERE user_id=$uid AND DATE(started_at)=$dq")->fetchAll() as $r) {
    $s=(int)floor((strtotime($r['started_at'])-$dayStart)/60); $e=(int)floor(((($r['ended_at']?strtotime($r['ended_at']):strtotime($r['last_ping_at'])))-$dayStart)/60);
    for($m=$s;$m<=$e;$m++) if($m>=0&&$m<1440){_slot($M,$m);$M[$m]['call']=true;$M[$m]['calllbl']=$r['label']?:'Call';}
  } } catch(\Throwable $e){}
  // Foreground app (app_timeline sessions)
  try { $fg=$pdo->query("SELECT app, started_at FROM app_timeline WHERE user_id=$uid AND DATE(started_at)=$dq AND app NOT LIKE '%soulsync%' ORDER BY started_at")->fetchAll();
    for($i=0;$i<count($fg);$i++){ $s=strtotime($fg[$i]['started_at']); $e=($i+1<count($fg))?strtotime($fg[$i+1]['started_at']):($isToday?time():($dayStart+86399));
      $ms=(int)floor(($s-$dayStart)/60); $me=(int)floor(($e-$dayStart)/60);
      for($m=$ms;$m<=$me;$m++) if($m>=0&&$m<1440){_slot($M,$m);$M[$m]['app']=$fg[$i]['app'];}
    }
  } catch(\Throwable $e){}
  // Network-active apps (app_net_timeline, 1-min slots) — MULTIPLE apps can be
  // active in the same minute (e.g. SoulSync + Instagram together); keep them all.
  try { foreach ($pdo->query("SELECT app, label, active_at FROM app_net_timeline WHERE user_id=$uid AND DATE(active_at)=$dq AND app NOT LIKE '%soulsync%'")->fetchAll() as $r) {
    $mi=(int)floor((strtotime($r['active_at'])-$dayStart)/60);
    // "Instagram · Watching Reels" when we have a guessed activity, else just the app.
    $entry = !empty($r['label']) ? ($r['app'].' · '.$r['label']) : $r['app'];
    if($mi>=0&&$mi<1440){_slot($M,$mi); if(!in_array($entry,$M[$mi]['net'],true)) $M[$mi]['net'][]=$entry;}
  } } catch(\Throwable $e){}
  // Notifications (notification_events)
  try { foreach ($pdo->query("SELECT app_name, posted_at FROM notification_events WHERE user_id=$uid AND DATE(posted_at)=$dq")->fetchAll() as $r) {
    $mi=(int)floor((strtotime($r['posted_at'])-$dayStart)/60); if($mi>=0&&$mi<1440){_slot($M,$mi);$M[$mi]['notif'][]=$r['app_name'];}
  } } catch(\Throwable $e){}
}
krsort($M); // newest minute first

$PAGE_TITLE = 'All Tracking';
$PAGE_SUB   = 'Everything per minute — phone, charge, app, network, notifications';
$PAGE_ICON  = '🧬';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">Select a user…</option>';
foreach ($users as $uu) $PAGE_TOOLS .= '<option value="'.(int)$uu['id'].'" '.($uid===(int)$uu['id']?'selected':'').'>'.ss_admin_h($uu['name']).'</option>';
$PAGE_TOOLS .= '</select><input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" min="'.date('Y-m-d', strtotime('-30 days')).'" onchange="this.form.submit()"></form>';

require __DIR__ . '/_dark_head.php';
?>
<div class="panel">
  <h3>🧬 Full Timeline — <?= ss_admin_h(date('d M Y', strtotime($day))) ?> <span style="font-size:.72rem;color:var(--mut)">(per minute)</span></h3>
  <?php if(!$uid): ?>
    <div class="empty">Select a user in the top filter to see the full per-minute timeline.</div>
  <?php elseif(!$M): ?>
    <div class="empty">No activity recorded for this day.</div>
  <?php else: ?>
    <div style="font-size:.72rem;color:var(--mut);margin:6px 0 12px">Each row = one minute. 🟢 phone on · 🔌 charging · 📞 on call · 📱 app on screen · 📡 app networking · 🔔 notifications.</div>
    <div style="overflow-x:auto;max-height:640px;overflow-y:auto"><table>
      <thead><tr><th>Time</th><th>Phone</th><th>Charge</th><th>Call</th><th>App (screen)</th><th>App (network)</th><th>Notifications</th></tr></thead>
      <tbody>
      <?php foreach($M as $mi=>$d): ?>
        <tr>
          <td style="white-space:nowrap;font-variant-numeric:tabular-nums"><?= sprintf('%02d:%02d', intdiv($mi,60), $mi%60) ?></td>
          <td><?= $d['on'] ? '🟢' : '<span style="color:#475569">⚪</span>' ?></td>
          <td><?= $d['chg'] ? '🔌' : '—' ?></td>
          <td><?= $d['call'] ? '📞 <span style="color:var(--mut);font-size:.7rem">'.ss_admin_h($d['calllbl']).'</span>' : '—' ?></td>
          <td><?= $d['app']!=='' ? '<b>'.ss_admin_h($d['app']).'</b>' : '—' ?></td>
          <td><?= $d['net'] ? ss_admin_h(implode(', ', $d['net'])) : '—' ?></td>
          <td><?= $d['notif'] ? ss_admin_h(implode(', ', array_slice(array_unique($d['notif']),0,3))).' <span style="color:var(--mut)">('.count($d['notif']).')</span>' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_dark_foot.php';
