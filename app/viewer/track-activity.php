<?php
// In-app Activity — what each user was doing inside an app, minute by minute,
// guessed from the data rate (Watching Reels vs Texting, etc.).
require_once __DIR__ . '/_boot_viewer.php';

function fmt_dur($ms){ $s=(int)($ms/1000);$h=intdiv($s,3600);$m=intdiv($s%3600,60); if($h>0)return "{$h}h {$m}m"; if($m>0)return "{$m}m"; return "{$s}s"; }
function fmt_bytes($b){ $b=(float)$b; if($b>=1073741824)return round($b/1073741824,2).' GB'; if($b>=1048576)return round($b/1048576,1).' MB'; if($b>=1024)return round($b/1024,1).' KB'; return (int)$b.' B'; }

function act_style($label){
    $l = strtolower((string)$label);
    if (strpos($l,'reel')!==false || strpos($l,'short')!==false || strpos($l,'video')!==false || strpos($l,'stream')!==false || strpos($l,'watch')!==false) return ['🎬','#ef4444'];
    if (strpos($l,'call')!==false) return ['📹','#f43f5e'];
    if (strpos($l,'feed')!==false || strpos($l,'scroll')!==false || strpos($l,'brows')!==false) return ['📲','#f59e0b'];
    if (strpos($l,'stor')!==false) return ['📖','#8b5cf6'];
    if (strpos($l,'camera')!==false || strpos($l,'photo')!==false) return ['📷','#a855f7'];
    if (strpos($l,'profile')!==false) return ['👤','#6366f1'];
    if (strpos($l,'search')!==false || strpos($l,'explor')!==false) return ['🔍','#06b6d4'];
    if (strpos($l,'voice')!==false) return ['🖼️','#a855f7'];
    if (strpos($l,'text')!==false || strpos($l,'chat')!==false || strpos($l,'dm')!==false) return ['💬','#22c55e'];
    return ['•','#64748b'];
}

$uid = (int)($_GET['user'] ?? 0);
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$dq = $pdo->quote($day);

$rows = [];
try {
    $rows = $pdo->query("SELECT app, label, bytes, active_at FROM app_net_timeline
                         WHERE DATE(active_at)=$dq AND label IS NOT NULL AND app NOT LIKE '%soulsync%' " . ($uid ? "AND user_id=$uid" : "") . "
                         ORDER BY app, active_at")->fetchAll();
} catch (\Throwable $e) {}

$acts = [];
$GAP = 180; $CYCLE = 60;
$ca=null; $cl=null; $rs=null; $re=null; $bsum=0;
$flush = function() use (&$acts,&$ca,&$cl,&$rs,&$re,&$bsum,$CYCLE){ if($ca!==null){ $acts[]=['app'=>$ca,'label'=>$cl,'start'=>$rs,'end'=>$re+$CYCLE,'dur'=>($re+$CYCLE)-$rs,'bytes'=>$bsum]; } };
foreach ($rows as $r) {
    $t = strtotime($r['active_at']);
    if ($ca===$r['app'] && $cl===$r['label'] && $rs!==null && ($t-$re)<=$GAP) { $re=$t; $bsum+=(int)$r['bytes']; }
    else { $flush(); $ca=$r['app']; $cl=$r['label']; $rs=$t; $re=$t; $bsum=(int)$r['bytes']; }
}
$flush();
usort($acts, fn($a,$b)=> $b['start'] <=> $a['start']);

$minutes = $rows;
usort($minutes, fn($a,$b)=> strtotime($b['active_at']) <=> strtotime($a['active_at']));

$byLabel = [];
foreach ($rows as $r) { $byLabel[$r['label']] = ($byLabel[$r['label']] ?? 0) + $CYCLE; }
arsort($byLabel);

$PAGE_TITLE = 'In-app Activity';
$PAGE_SUB   = 'What was happening inside apps — Reels vs Texting, minute by minute';
$PAGE_ICON  = '🎬';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input type="hidden" name="user" value="'.$uid.'"><input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" min="'.date('Y-m-d', strtotime('-30 days')).'" onchange="this.form.submit()"></form>';

require __DIR__ . '/_head_viewer.php';
?>
<div class="panel">
  <div style="font-size:.8rem;color:var(--mut);margin-bottom:10px">Guessed from how much data each app used per minute — e.g. Instagram burning data = <b style="color:#ef4444">Watching Reels</b>, barely any = <b style="color:#22c55e">Texting</b>. It's a smart guess from the data rate, not proof. Data is kept for the last 30 days.</div>
  <?php if($byLabel): ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach($byLabel as $lab=>$sec): [$ico,$col]=act_style($lab); ?>
        <span class="pill" style="background:<?= $col ?>22;color:<?= $col ?>"><?= $ico ?> <?= ss_admin_h($lab) ?> · <?= fmt_dur($sec*1000) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <h3>🎬 Activity Ranges — <?= ss_admin_h(date("d M Y", strtotime($day))) ?></h3>
  <?php if(!$acts): ?>
    <div class="empty">No activity yet. Needs the latest app + Usage Access + a few minutes of syncing.</div>
  <?php else: ?>
    <div style="overflow-x:auto;max-height:520px;overflow-y:auto"><table>
      <thead><tr><th>App</th><th>Activity</th><th>From → To</th><th>Duration</th><th>Data</th></tr></thead>
      <tbody>
      <?php foreach($acts as $a): [$ico,$col] = act_style($a['label']); ?>
        <tr>
          <td><b><?= ss_admin_h($a['app']) ?></b></td>
          <td><span class="pill" style="background:<?= $col ?>22;color:<?= $col ?>"><?= $ico ?> <?= ss_admin_h($a['label']) ?></span></td>
          <td style="white-space:nowrap"><?= date('H:i',$a['start']) ?> &rarr; <?= date('H:i',$a['end']) ?></td>
          <td><?= fmt_dur($a['dur']*1000) ?></td>
          <td style="color:var(--mut)"><?= fmt_bytes($a['bytes']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="panel">
  <h3>⏱ Minute by Minute — <?= ss_admin_h(date("d M Y", strtotime($day))) ?></h3>
  <?php if(!$minutes): ?>
    <div class="empty">No per-minute data yet.</div>
  <?php else: ?>
    <div style="overflow-x:auto;max-height:520px;overflow-y:auto"><table>
      <thead><tr><th>Time</th><th>App</th><th>Activity</th><th>Data / min</th></tr></thead>
      <tbody>
      <?php foreach($minutes as $m): [$ico,$col] = act_style($m['label']); ?>
        <tr>
          <td style="white-space:nowrap"><?= date('H:i',strtotime($m['active_at'])) ?></td>
          <td><b><?= ss_admin_h($m['app']) ?></b></td>
          <td><span class="pill" style="background:<?= $col ?>22;color:<?= $col ?>"><?= $ico ?> <?= ss_admin_h($m['label']) ?></span></td>
          <td style="color:var(--mut)"><?= fmt_bytes($m['bytes']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot_viewer.php';
