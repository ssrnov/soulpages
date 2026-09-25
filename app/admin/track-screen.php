<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

function fmt_dur($ms){ $s=(int)($ms/1000);$h=intdiv($s,3600);$m=intdiv($s%3600,60);$sx=$s%60; if($h>0)return "{$h}h {$m}m"; if($m>0)return "{$m}m {$sx}s"; return "{$sx}s"; }

$uid = (int)($_GET['user'] ?? 0);
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$dq = $pdo->quote($day);

// Raw sessions from DB.
$raw = [];
try {
    $rows = $pdo->query("SELECT started_at, ended_at, last_ping_at FROM screen_sessions
                         WHERE DATE(started_at)=$dq " . ($uid ? "AND user_id=$uid" : "") . "
                         ORDER BY started_at ASC")->fetchAll();
    foreach ($rows as $r) {
        $s = strtotime($r['started_at']);
        $e = $r['ended_at'] ? strtotime($r['ended_at']) : strtotime($r['last_ping_at']);
        $live = empty($r['ended_at']);
        $raw[] = ['start'=>$s, 'end'=>max($s,$e), 'live'=>$live];
    }
} catch (\Throwable $e) {}

// Merge sessions with gap < 60s into one continuous session.
$sessions = [];
foreach ($raw as $r) {
    if ($sessions) {
        $last = &$sessions[count($sessions)-1];
        if ($r['start'] - $last['end'] <= 90) {
            $last['end'] = max($last['end'], $r['end']);
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

// Reverse for display (newest first).
$sessions = array_reverse($sessions);

// Fetch app_timeline rows for this day.
$appTimeline = [];
try {
    $atRows = $pdo->query("SELECT user_id, app, started_at FROM app_timeline
                           WHERE DATE(started_at)=$dq " . ($uid ? "AND user_id=$uid" : "") . "
                           AND app != '' ORDER BY started_at")->fetchAll();
    foreach ($atRows as $atr) $appTimeline[] = ['app'=>$atr['app'], 'at'=>strtotime($atr['started_at']), 'uid'=>(int)$atr['user_id']];
} catch (\Throwable $e) {}

// Attach apps to each merged session.
foreach ($sessions as &$sess) {
    $sess['apps'] = [];
    foreach ($appTimeline as $at) {
        if ($uid && $at['uid'] !== $uid) continue;
        if ($at['at'] >= $sess['start'] && $at['at'] <= $sess['end']) {
            if (!in_array($at['app'], $sess['apps'])) $sess['apps'][] = $at['app'];
        }
    }
}
unset($sess);

$firstOn = $sessions ? min(array_column($sessions,'start')) : null;
$lastOn  = $sessions ? max(array_column($sessions,'start')) : null;

$PAGE_TITLE = 'Phone On/Off';
$PAGE_SUB   = 'When the phone screen was ON — from → to, per day';
$PAGE_ICON  = '🔌';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">All Users</option>';
foreach ($users as $uu) $PAGE_TOOLS .= '<option value="'.(int)$uu['id'].'" '.($uid===(int)$uu['id']?'selected':'').'>'.ss_admin_h($uu['name']).'</option>';
$PAGE_TOOLS .= '</select><input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" min="'.date('Y-m-d', strtotime('-30 days')).'" onchange="this.form.submit()"></form>';

require __DIR__ . '/_dark_head.php';
?>
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">🔓</span> Times Turned On</div><div class="v"><?= count($sessions) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">⏱️</span> Total Screen-On</div><div class="v" style="font-size:1.3rem"><?= $totalSec ? fmt_dur($totalSec*1000) : '—' ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🌅</span> First On</div><div class="v" style="font-size:1.3rem"><?= $firstOn?date('H:i',$firstOn):'—' ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🌙</span> Last On</div><div class="v" style="font-size:1.3rem"><?= $lastOn?date('H:i',$lastOn):'—' ?></div></div>
</div>

<div class="panel">
  <h3>🔌 Phone On Sessions — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="font-size:.75rem;color:var(--mut);margin:6px 0 12px">Each time the phone was used (brief flickers merged). 🟢 = still on right now.</div>
  <?php if(!$sessions): ?>
    <div class="empty">No screen sessions for this day. Needs the latest app installed with the background service running.</div>
  <?php else: ?>
    <div style="overflow-x:auto;max-height:560px;overflow-y:auto"><table>
      <thead><tr><th>On → Off</th><th>Duration</th><th>Status</th><th>Apps Used</th></tr></thead>
      <tbody>
      <?php foreach($sessions as $s): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('H:i:s',$s['start']) ?> &rarr; <?= $s['live']?'now':date('H:i:s',$s['end']) ?></td>
          <td><?= fmt_dur($s['dur']*1000) ?></td>
          <td><?= $s['live'] ? '<span class="pill" style="background:rgba(34,197,94,.15);color:#4ade80">🟢 On now</span>' : '<span style="color:var(--mut)">Off</span>' ?></td>
          <td style="max-width:300px;word-break:break-all;font-size:.75rem"><?php
            if ($s['apps']) {
                $labels = array_map(function($a){
                    $parts = explode('.', $a);
                    return end($parts);
                }, $s['apps']);
                echo implode(', ', array_map('ss_admin_h', $labels));
            } else { echo '<span style="color:var(--mut)">—</span>'; }
          ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_dark_foot.php';
