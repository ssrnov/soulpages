<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

function fmt_dur($ms){ $s=(int)($ms/1000);$h=intdiv($s,3600);$m=intdiv($s%3600,60);$sx=$s%60; if($h>0)return "{$h}h {$m}m"; if($m>0)return "{$m}m {$sx}s"; return "{$sx}s"; }

$uid = (int)($_GET['user'] ?? 0);
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$dq = $pdo->quote($day);

// Raw charging sessions.
$raw = [];
try {
    $rows = $pdo->query("SELECT started_at, ended_at, last_ping_at, start_battery, end_battery FROM charge_sessions
                         WHERE DATE(started_at)=$dq " . ($uid ? "AND user_id=$uid" : "") . "
                         ORDER BY started_at ASC")->fetchAll();
    foreach ($rows as $r) {
        $s = strtotime($r['started_at']);
        $e = $r['ended_at'] ? strtotime($r['ended_at']) : strtotime($r['last_ping_at']);
        $raw[] = ['start'=>$s, 'end'=>max($s,$e), 'live'=>empty($r['ended_at']),
                  'sb'=>$r['start_battery'], 'eb'=>$r['end_battery']];
    }
} catch (\Throwable $e) {}

// Merge sessions with gap < 60s.
$sessions = [];
foreach ($raw as $r) {
    if ($sessions) {
        $last = &$sessions[count($sessions)-1];
        if ($r['start'] - $last['end'] <= 90) {
            $last['end'] = max($last['end'], $r['end']);
            $last['eb'] = $r['eb'];
            if ($r['live']) $last['live'] = true;
            continue;
        }
        unset($last);
    }
    $sessions[] = $r;
}

// Calculate duration and totals.
$totalSec = 0;
foreach ($sessions as &$s) {
    $s['dur'] = max(0, $s['end'] - $s['start']);
    $totalSec += $s['dur'];
}
unset($s);

// Newest first.
$sessions = array_reverse($sessions);

$PAGE_TITLE = 'Charging';
$PAGE_SUB   = 'When the device was charging — from → to, per day';
$PAGE_ICON  = '🔋';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">All Users</option>';
foreach ($users as $uu) $PAGE_TOOLS .= '<option value="'.(int)$uu['id'].'" '.($uid===(int)$uu['id']?'selected':'').'>'.ss_admin_h($uu['name']).'</option>';
$PAGE_TOOLS .= '</select><input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" min="'.date('Y-m-d', strtotime('-30 days')).'" onchange="this.form.submit()"></form>';

require __DIR__ . '/_dark_head.php';
?>
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">🔌</span> Times Charged</div><div class="v"><?= count($sessions) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">⏱️</span> Total Charging</div><div class="v" style="font-size:1.3rem"><?= $totalSec ? fmt_dur($totalSec*1000) : '—' ?></div></div>
</div>
<div class="panel">
  <h3>🔋 Charging Sessions — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="font-size:.75rem;color:var(--mut);margin:6px 0 12px">When the charger was plugged in and unplugged (brief flickers merged). 🟢 = charging right now.</div>
  <?php if(!$sessions): ?>
    <div class="empty">No charging sessions for this day.</div>
  <?php else: ?>
    <div style="overflow-x:auto;max-height:560px;overflow-y:auto"><table>
      <thead><tr><th>Plugged → Unplugged</th><th>Duration</th><th>Battery</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($sessions as $s): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('H:i',$s['start']) ?> &rarr; <?= $s['live']?'now':date('H:i',$s['end']) ?></td>
          <td><?= fmt_dur($s['dur']*1000) ?></td>
          <td><?= $s['sb']!==null?(int)$s['sb'].'%':'—' ?> &rarr; <?= $s['eb']!==null?(int)$s['eb'].'%':'—' ?></td>
          <td><?= $s['live'] ? '<span class="pill" style="background:rgba(34,197,94,.15);color:#4ade80">🟢 Charging</span>' : '<span style="color:var(--mut)">Done</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_dark_foot.php';
