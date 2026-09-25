<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

function fmt_dur($ms){ $s=(int)($ms/1000);$h=intdiv($s,3600);$m=intdiv($s%3600,60); if($h>0)return "{$h}h {$m}m"; if($m>0)return "{$m}m"; return "{$s}s"; }
function fmt_bytes($b){ $b=(float)$b; if($b>=1073741824)return round($b/1073741824,2).' GB'; if($b>=1048576)return round($b/1048576,1).' MB'; if($b>=1024)return round($b/1024,1).' KB'; return (int)$b.' B'; }

$uid = (int)($_GET['user'] ?? 0);
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$dq = $pdo->quote($day);

// Per-app network totals for the chosen day (data + estimated active duration).
$netApps = [];
try {
    $netApps = $pdo->query("SELECT app_name, package, SUM(bytes) bytes, SUM(active_ms) active_ms FROM app_net_daily
                            WHERE day=$dq AND app_name NOT LIKE '%soulsync%' " . ($uid ? "AND user_id=$uid" : "") . "
                            GROUP BY package ORDER BY bytes DESC LIMIT 80")->fetchAll();
} catch (\Throwable $e) {}
$seenApps = [];
try { foreach ($pdo->query("SELECT DISTINCT LOWER(app_name) a FROM usage_events WHERE day=$dq " . ($uid ? "AND user_id=$uid" : ""))->fetchAll() as $r) $seenApps[$r['a']] = true; } catch (\Throwable $e) {}

// Network active TIME RANGES per app (group consecutive 1-min slots).
$ranges = [];
try {
    $rows = $pdo->query("SELECT app, active_at FROM app_net_timeline
                         WHERE DATE(active_at)=$dq AND app NOT LIKE '%soulsync%' " . ($uid ? "AND user_id=$uid" : "") . "
                         ORDER BY app, active_at")->fetchAll();
    $GAP = 180; // seconds — slots within 3 min join into one range
    $CYCLE = 60;
    $curApp=null; $rs=null; $re=null;
    $flush = function() use (&$ranges,&$curApp,&$rs,&$re,$CYCLE){ if($curApp!==null){ $ranges[]=['app'=>$curApp,'start'=>$rs,'end'=>$re+$CYCLE,'dur'=>($re+$CYCLE)-$rs]; } };
    foreach ($rows as $r) {
        $t = strtotime($r['active_at']);
        if ($curApp === $r['app'] && $rs!==null && ($t - $re) <= $GAP) { $re = $t; }
        else { $flush(); $curApp=$r['app']; $rs=$t; $re=$t; }
    }
    $flush();
    usort($ranges, fn($a,$b)=> $b['start'] <=> $a['start']);
} catch (\Throwable $e) {}

$PAGE_TITLE = 'App Usage · Network';
$PAGE_SUB   = 'Data usage + time ranges (catches hidden apps) — via networking';
$PAGE_ICON  = '📡';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">All Users</option>';
foreach ($users as $uu) $PAGE_TOOLS .= '<option value="'.(int)$uu['id'].'" '.($uid===(int)$uu['id']?'selected':'').'>'.ss_admin_h($uu['name']).'</option>';
$PAGE_TOOLS .= '</select><input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" min="'.date('Y-m-d', strtotime('-30 days')).'" onchange="this.form.submit()"></form>';

require __DIR__ . '/_dark_head.php';
?>
<div class="panel">
  <h3>📊 Data Usage per App — <?= ss_admin_h(date("d M Y", strtotime($day))) ?></h3>
  <div style="font-size:.75rem;color:var(--mut);margin:6px 0 12px">Every app that used data today, even if it never appeared on screen.
    <b style="color:#f59e0b">⚠ Hidden</b> = used data but has <b>no screen time</b> → likely a hidden / vault / background app.</div>
  <?php if(!$netApps): ?>
    <div class="empty">No network data yet. Install the latest app, grant Usage Access & wait ~5–10 min.</div>
  <?php else: ?>
    <div style="overflow-x:auto"><table>
      <thead><tr><th>App</th><th>Package</th><th>Data Today</th><th>Est. Active</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($netApps as $n): $isHidden = !isset($seenApps[strtolower($n['app_name'])]); ?>
        <tr style="<?= $isHidden?'background:rgba(245,158,11,.07)':'' ?>">
          <td><b><?= ss_admin_h($n['app_name']) ?></b></td>
          <td style="color:var(--mut);font-size:.72rem"><?= ss_admin_h($n['package']) ?></td>
          <td><b><?= fmt_bytes($n['bytes']) ?></b></td>
          <td><?= (int)$n['active_ms']>0?fmt_dur((int)$n['active_ms']):'—' ?></td>
          <td><?= $isHidden ? '<span class="pill" style="background:rgba(245,158,11,.15);color:#f59e0b">⚠ Hidden</span>' : '<span style="color:#4ade80">🟢 on screen</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="panel">
  <h3>🕐 Network Activity Time Ranges — <?= ss_admin_h(date("d M Y", strtotime($day))) ?></h3>
  <div style="font-size:.75rem;color:var(--mut);margin:6px 0 12px">When each app was actively using data — e.g. "10:04 → 11:30". Guessed from network activity, so it also catches hidden apps.</div>
  <?php if(!$ranges): ?>
    <div class="empty">No activity ranges yet. Needs the latest app & a few sync cycles.</div>
  <?php else: ?>
    <div style="overflow-x:auto;max-height:460px;overflow-y:auto"><table>
      <thead><tr><th>App</th><th>From → To</th><th>Duration</th></tr></thead>
      <tbody>
      <?php foreach($ranges as $r): ?>
        <tr>
          <td><b><?= ss_admin_h($r['app']) ?></b></td>
          <td style="white-space:nowrap"><?= date('H:i',$r['start']) ?> &rarr; <?= date('H:i',$r['end']) ?></td>
          <td><?= fmt_dur($r['dur']*1000) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php
// Total usage per app (network-guessed active duration) for the bar graph.
$totals = [];
foreach ($netApps as $n) { $ms = (int)($n['active_ms'] ?? 0); if ($ms > 0) $totals[] = ['app'=>$n['app_name'], 'ms'=>$ms]; }
usort($totals, fn($a,$b)=> $b['ms'] <=> $a['ms']);
$maxMs = $totals ? $totals[0]['ms'] : 1;
?>
<div class="panel">
  <h3>📊 Total App Usage — <?= ss_admin_h(date('d M Y', strtotime($day))) ?> <span style="font-size:.72rem;color:var(--mut)">(how long each app was used — network guess)</span></h3>
  <?php if(!$totals): ?>
    <div class="empty">No usage durations yet.</div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px;margin-top:8px">
      <?php foreach($totals as $t): $pct = $maxMs>0 ? max(3, round($t['ms']/$maxMs*100)) : 3; ?>
        <div>
          <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:3px">
            <b><?= ss_admin_h($t['app']) ?></b>
            <span style="color:var(--mut)"><?= fmt_dur($t['ms']) ?></span>
          </div>
          <div style="height:12px;background:var(--soft);border-radius:50px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#a855f7,#ec4899)"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_dark_foot.php';
