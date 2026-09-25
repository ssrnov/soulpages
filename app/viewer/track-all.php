<?php
require_once __DIR__ . '/_boot_viewer.php';

$uid = (int)($_GET['user'] ?? 0);
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$dq = $pdo->quote($day);
$dayStart = strtotime($day . ' 00:00:00');
$isToday = ($day === date('Y-m-d'));
$uw = $uid ? "AND user_id=$uid" : "";

$M = [];
function _slot(&$M,$mi){ if(!isset($M[$mi])) $M[$mi]=['on'=>false,'chg'=>false,'call'=>false,'calllbl'=>'','app'=>'','net'=>[],'notif'=>[]]; return $mi; }

if ($uid) {
  try { foreach ($pdo->query("SELECT started_at, ended_at, last_ping_at FROM screen_sessions WHERE user_id=$uid AND DATE(started_at)=$dq")->fetchAll() as $r) {
    $s=(int)floor((strtotime($r['started_at'])-$dayStart)/60); $e=(int)floor(((($r['ended_at']?strtotime($r['ended_at']):strtotime($r['last_ping_at'])))-$dayStart)/60);
    for($m=$s;$m<=$e;$m++) if($m>=0&&$m<1440){_slot($M,$m);$M[$m]['on']=true;}
  } } catch(\Throwable $e){}
  try { foreach ($pdo->query("SELECT started_at, ended_at, last_ping_at FROM charge_sessions WHERE user_id=$uid AND DATE(started_at)=$dq")->fetchAll() as $r) {
    $s=(int)floor((strtotime($r['started_at'])-$dayStart)/60); $e=(int)floor(((($r['ended_at']?strtotime($r['ended_at']):strtotime($r['last_ping_at'])))-$dayStart)/60);
    for($m=$s;$m<=$e;$m++) if($m>=0&&$m<1440){_slot($M,$m);$M[$m]['chg']=true;}
  } } catch(\Throwable $e){}
  try { foreach ($pdo->query("SELECT started_at, ended_at, last_ping_at, label FROM call_sessions WHERE user_id=$uid AND DATE(started_at)=$dq")->fetchAll() as $r) {
    $s=(int)floor((strtotime($r['started_at'])-$dayStart)/60); $e=(int)floor(((($r['ended_at']?strtotime($r['ended_at']):strtotime($r['last_ping_at'])))-$dayStart)/60);
    for($m=$s;$m<=$e;$m++) if($m>=0&&$m<1440){_slot($M,$m);$M[$m]['call']=true;$M[$m]['calllbl']=$r['label']?:'Call';}
  } } catch(\Throwable $e){}
  try { $fg=$pdo->query("SELECT app, started_at FROM app_timeline WHERE user_id=$uid AND DATE(started_at)=$dq AND app NOT LIKE '%soulsync%' ORDER BY started_at")->fetchAll();
    for($i=0;$i<count($fg);$i++){ $s=strtotime($fg[$i]['started_at']); $e=($i+1<count($fg))?strtotime($fg[$i+1]['started_at']):($isToday?time():($dayStart+86399));
      $ms=(int)floor(($s-$dayStart)/60); $me=(int)floor(($e-$dayStart)/60);
      for($m=$ms;$m<=$me;$m++) if($m>=0&&$m<1440){_slot($M,$m);$M[$m]['app']=$fg[$i]['app'];}
    }
  } catch(\Throwable $e){}
  try { foreach ($pdo->query("SELECT app, label, active_at FROM app_net_timeline WHERE user_id=$uid AND DATE(active_at)=$dq AND app NOT LIKE '%soulsync%'")->fetchAll() as $r) {
    $mi=(int)floor((strtotime($r['active_at'])-$dayStart)/60);
    $entry = !empty($r['label']) ? ($r['app'].' · '.$r['label']) : $r['app'];
    if($mi>=0&&$mi<1440){_slot($M,$mi); if(!in_array($entry,$M[$mi]['net'],true)) $M[$mi]['net'][]=$entry;}
  } } catch(\Throwable $e){}
  try { foreach ($pdo->query("SELECT app_name, posted_at FROM notification_events WHERE user_id=$uid AND DATE(posted_at)=$dq")->fetchAll() as $r) {
    $mi=(int)floor((strtotime($r['posted_at'])-$dayStart)/60); if($mi>=0&&$mi<1440){_slot($M,$mi);$M[$mi]['notif'][]=$r['app_name'];}
  } } catch(\Throwable $e){}
}
krsort($M);

$PAGE_TITLE = 'All Tracking';
$PAGE_SUB   = 'Everything per minute — phone, charge, app, network, notifications';
$PAGE_ICON  = '🧬';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input type="hidden" name="user" value="'.$uid.'"><input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" min="'.date('Y-m-d', strtotime('-30 days')).'" onchange="this.form.submit()"></form>';

require __DIR__ . '/_head_viewer.php';
?>
<div class="panel">
  <h3>🧬 Full Timeline — <?= ss_admin_h(date('d M Y', strtotime($day))) ?> <span style="font-size:.72rem;color:var(--mut)">(per minute)</span></h3>
  <?php if(!$uid): ?>
    <div class="empty">No tracking access for this user.</div>
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
<?php require __DIR__ . '/_foot_viewer.php';
