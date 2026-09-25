<?php
require_once __DIR__ . '/_boot_viewer.php';

function fmt_dur($ms){ $s=(int)($ms/1000);$h=intdiv($s,3600);$m=intdiv($s%3600,60);$x=$s%60; if($h>0)return "{$h}h {$m}m"; if($m>0)return "{$m}m {$x}s"; return "{$s}s"; }

$uid = (int)($_GET['user'] ?? 0);
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$dq = $pdo->quote($day);

$live = [];
try {
    $live = $pdo->query("SELECT u.name, u.username, d.call_started_at, d.current_app, d.call_contact, d.call_type
                         FROM users u JOIN device_status d ON d.user_id=u.id
                         WHERE u.role='user' AND d.on_call=1 AND u.id=$uid")->fetchAll();
} catch (\Throwable $e) {}

$sessions = []; $totalMs = 0;
try {
    $rows = $pdo->query("SELECT c.started_at, c.ended_at, c.last_ping_at, c.label, c.call_type, c.duration_sec, u.name
                         FROM call_sessions c JOIN users u ON u.id=c.user_id
                         WHERE DATE(c.started_at)=$dq " . ($uid ? "AND c.user_id=$uid" : "") . "
                         ORDER BY c.started_at DESC")->fetchAll();
    foreach ($rows as $r) {
        $s = strtotime($r['started_at']);
        $e = $r['ended_at'] ? strtotime($r['ended_at']) : strtotime($r['last_ping_at']);
        $dur = $r['duration_sec'] !== null ? (int)$r['duration_sec'] : max(0, $e - $s);
        $sessions[] = ['name'=>$r['name'],'start'=>$s,'end'=>$e,'dur'=>$dur,'live'=>empty($r['ended_at']),'label'=>$r['label'],'call_type'=>$r['call_type'] ?? ''];
        $totalMs += $dur * 1000;
    }
} catch (\Throwable $e) {}

$PAGE_TITLE = 'Phone Calls';
$PAGE_SUB   = 'Every call — who, when, how long. 🟢 = on a call right now';
$PAGE_ICON  = '📞';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input type="hidden" name="user" value="'.$uid.'"><input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" min="'.date('Y-m-d', strtotime('-30 days')).'" onchange="this.form.submit()"></form>';

require __DIR__ . '/_head_viewer.php';
?>
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">📞</span> Calls Today</div><div class="v"><?= count($sessions) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">⏱️</span> Total Talk Time</div><div class="v" style="font-size:1.3rem"><?= $totalMs?fmt_dur($totalMs):'—' ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🟢</span> On Call Now</div><div class="v"><?= count($live) ?></div></div>
</div>

<?php if($live): ?>
<div class="panel">
  <h3>🟢 On a call right now</h3>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>User</th><th>Since</th><th>Duration</th><th>Direction</th><th>With</th><th>Via</th></tr></thead>
    <tbody>
    <?php foreach($live as $l): $st=$l['call_started_at']?strtotime($l['call_started_at']):time();
        $lct = strtolower(trim($l['call_type'] ?? ''));
        $ldir = $lct==='incoming' ? '📥 Incoming' : ($lct==='outgoing' ? '📤 Outgoing' : '—');
    ?>
      <tr>
        <td><b><?= ss_admin_h($l['name'] ?: $l['username']) ?></b></td>
        <td style="white-space:nowrap"><?= $l['call_started_at']?date('H:i',$st):'—' ?></td>
        <td><span class="pill" style="background:rgba(34,197,94,.15);color:#4ade80"><?= fmt_dur((time()-$st)*1000) ?></span></td>
        <td><?= $ldir ?></td>
        <td><?= ss_admin_h($l['call_contact'] ?: '—') ?></td>
        <td><?= ss_admin_h($l['current_app'] ?: '📞 Call') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<div class="panel">
  <h3>📞 Call Log — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="font-size:.75rem;color:var(--mut);margin:6px 0 12px">Phone & app (WhatsApp etc.) calls. 🟢 = call is live right now.</div>
  <?php if(!$sessions): ?>
    <div class="empty">No calls for this day.</div>
  <?php else: ?>
    <div style="overflow-x:auto;max-height:560px;overflow-y:auto"><table>
      <thead><tr><?php if(!$uid): ?><th>User</th><?php endif; ?><th>From → To</th><th>Duration</th><th>Direction</th><th>Type</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($sessions as $s):
          $dir = '';
          $ct = strtolower(trim($s['call_type'] ?? ''));
          if ($ct === 'incoming') $dir = '<span class="pill" style="background:rgba(59,130,246,.15);color:#60a5fa;font-size:.7rem">📥 Incoming</span>';
          elseif ($ct === 'outgoing') $dir = '<span class="pill" style="background:rgba(168,85,247,.15);color:#c084fc;font-size:.7rem">📤 Outgoing</span>';
          else $dir = '<span style="color:var(--mut);font-size:.75rem">—</span>';
      ?>
        <tr>
          <?php if(!$uid): ?><td><?= ss_admin_h($s['name']) ?></td><?php endif; ?>
          <td style="white-space:nowrap"><?= date('H:i',$s['start']) ?> &rarr; <?= $s['live']?'now':date('H:i',$s['end']) ?></td>
          <td><?= fmt_dur($s['dur']*1000) ?></td>
          <td><?= $dir ?></td>
          <td><?= ss_admin_h($s['label'] ?: '📞 Call') ?></td>
          <td><?= $s['live'] ? '<span class="pill" style="background:rgba(34,197,94,.15);color:#4ade80">🟢 On call</span>' : '<span style="color:var(--mut)">Ended</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot_viewer.php';
