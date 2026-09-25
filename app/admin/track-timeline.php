<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

function fmt_dur($ms){ $s=(int)($ms/1000);$h=intdiv($s,3600);$m=intdiv($s%3600,60); if($h>0)return "{$h}h {$m}m"; if($m>0)return "{$m}m"; return "{$s}s"; }

$uid = (int)($_GET['user'] ?? 0);
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$dq = $pdo->quote($day);
$isToday = ($day === date('Y-m-d'));
$uw = $uid ? "AND user_id=$uid" : "";

// Build a PER-MINUTE picture of which app was in use, from BOTH signals:
//   • foreground (app_timeline) — most accurate, takes priority
//   • network activity (app_net_timeline) — fills gaps + catches hidden apps
// Then group consecutive same-app minutes into ranges.
$dayStart = strtotime($day . ' 00:00:00');
$minute = [];   // minuteIndex (0..1439) => ['app'=>, 'src'=>]

// 1) Network-active minutes (each row ≈ one active minute).
try {
    foreach ($pdo->query("SELECT app, active_at FROM app_net_timeline WHERE DATE(active_at)=$dq AND app NOT LIKE '%soulsync%' $uw ORDER BY active_at")->fetchAll() as $r) {
        $mi = (int)floor((strtotime($r['active_at']) - $dayStart) / 60);
        if ($mi >= 0 && $mi < 1440) $minute[$mi] = ['app'=>$r['app'], 'src'=>'net'];
    }
} catch (\Throwable $e) {}

// 2) Foreground sessions OVERWRITE those minutes (priority).
try {
    $fg = $pdo->query("SELECT app, started_at FROM app_timeline WHERE DATE(started_at)=$dq AND app NOT LIKE '%soulsync%' $uw ORDER BY started_at")->fetchAll();
    for ($i=0; $i<count($fg); $i++) {
        $s = strtotime($fg[$i]['started_at']);
        $e = ($i+1 < count($fg)) ? strtotime($fg[$i+1]['started_at']) : ($isToday ? time() : ($dayStart + 86399));
        $ms = (int)floor(($s - $dayStart)/60);
        $me = (int)floor(($e - $dayStart)/60);
        for ($m=$ms; $m<=$me; $m++) if ($m>=0 && $m<1440) $minute[$m] = ['app'=>$fg[$i]['app'], 'src'=>'app'];
    }
} catch (\Throwable $e) {}

// 3) Group consecutive same-app minutes into ranges.
$timeline = [];
ksort($minute);
$curApp=null; $curSrc=null; $rs=null; $rePrev=null;
$flush = function() use (&$timeline,&$curApp,&$curSrc,&$rs,&$rePrev,$dayStart){
    if ($curApp!==null){ $st=$dayStart+$rs*60; $en=$dayStart+($rePrev+1)*60; $timeline[]=['app'=>$curApp,'src'=>$curSrc,'start'=>$st,'end'=>$en,'dur'=>$en-$st]; }
};
foreach ($minute as $mi=>$info) {
    if ($curApp===$info['app'] && $rePrev!==null && ($mi-$rePrev)<=2) { $rePrev=$mi; }
    else { $flush(); $curApp=$info['app']; $curSrc=$info['src']; $rs=$mi; $rePrev=$mi; }
}
$flush();
usort($timeline, fn($a,$b)=> $b['start'] <=> $a['start']);

$PAGE_TITLE = 'App Timeline';
$PAGE_SUB   = 'Which app at which time — from foreground + network activity';
$PAGE_ICON  = '🕐';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">All Users</option>';
foreach ($users as $uu) $PAGE_TOOLS .= '<option value="'.(int)$uu['id'].'" '.($uid===(int)$uu['id']?'selected':'').'>'.ss_admin_h($uu['name']).'</option>';
$PAGE_TOOLS .= '</select><input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" onchange="this.form.submit()"></form>';

require __DIR__ . '/_dark_head.php';
?>
<div class="panel">
  <h3>🕐 App Timeline — <?= ss_admin_h(date('d M Y', strtotime($day))) ?> <span style="font-size:.75rem;color:var(--mut)">(<?= count($timeline) ?> blocks)</span></h3>
  <div style="font-size:.75rem;color:var(--mut);margin:6px 0 12px">Per-minute — which app the user was on, merged from <b>foreground</b> (📱) and <b>network activity</b> (📡, catches hidden apps). e.g. "10:03 → 10:25 · Instagram".</div>
  <?php if(!$timeline): ?>
    <div class="empty">No activity for this day yet. Needs the latest app installed + Usage Access granted, then a few minutes of syncing.</div>
  <?php else: ?>
    <div style="overflow-x:auto;max-height:560px;overflow-y:auto"><table>
      <thead><tr><th>From → To</th><th>App</th><th>Duration</th><th>Via</th></tr></thead>
      <tbody>
      <?php foreach($timeline as $t): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('H:i',$t['start']) ?> &rarr; <?= date('H:i',$t['end']) ?></td>
          <td><b><?= ss_admin_h($t['app']) ?></b></td>
          <td><?= fmt_dur($t['dur']*1000) ?></td>
          <td><?= $t['src']==='app' ? '📱 screen' : '📡 network' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_dark_foot.php';
