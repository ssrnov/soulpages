<?php
// House Ads — your OWN image ads (upload an image + a link). Shown on the website
// independently of Adsterra. Gated by the master Ads switch + Website ads switch.
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'My Ads (House Ads)';
$PAGE_SUB   = 'Upload your own image ads with a link';
$PAGE_ICON  = '🖼️';
$flash = ''; $flash_type = 'ok';

// Make sure the table exists.
$pdo->exec("CREATE TABLE IF NOT EXISTS house_ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) DEFAULT '',
    image VARCHAR(500) NOT NULL,
    link VARCHAR(500) DEFAULT '',
    placement VARCHAR(30) DEFAULT 'sticky',
    enabled TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$adsDir = dirname(__DIR__) . '/uploads/ads';
$proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
// Public base to the ads folder (admin lives at .../soulsync-system/admin/).
$publicBase = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? '')
            . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\') . '/uploads/ads/';

$placements = [
    // ── Website ──
    'all'       => '🌐 Web: All pages (everywhere)',
    'sticky'    => '🌐 Web: Sticky bottom bar',
    'home'      => '🌐 Web: Home page',
    'dashboard' => '🌐 Web: Dashboard',
    'publish'   => '🌐 Web: Publish-Success page',
    'soulsync'  => '🌐 Web: SoulSync app page',
    // ── Mobile App (shown inside the Android app when its provider = "My ad") ──
    'app_home'      => '📱 App: Home',
    'app_games'     => '📱 App: Games',
    'app_memories'  => '📱 App: Memories',
    // ── Reward: upload an MP4 video; user watches it and gets the reward. ──
    'app_reward'    => '🎁 App: Reward Video (MP4)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $link  = trim($_POST['link'] ?? '');
        $place = $_POST['placement'] ?? 'sticky';
        if (!isset($placements[$place])) $place = 'sticky';

        if ($link !== '' && !preg_match('#^https?://#i', $link)) {
            $flash = 'Link must start with http:// or https://'; $flash_type = 'err';
        } elseif (empty($_FILES['image']['name']) || ($_FILES['image']['error'] ?? 1) !== UPLOAD_ERR_OK) {
            $flash = 'Please choose an image to upload.'; $flash_type = 'err';
        } else {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $isReward = ($place === 'app_reward');
            $okImage = in_array($ext, ['png', 'webp', 'jpg', 'jpeg', 'gif'], true);
            $okVideo = in_array($ext, ['mp4', 'webm', 'mov'], true);
            if ($isReward && !$okVideo) {
                $flash = 'Reward Video needs an MP4/WEBM file.'; $flash_type = 'err';
            } elseif (!$isReward && !$okImage) {
                $flash = 'Only PNG, WEBP, JPG or GIF images are allowed.'; $flash_type = 'err';
            } else {
                if (!is_dir($adsDir)) @mkdir($adsDir, 0755, true);
                $fname = 'ad-' . date('Ymd-His') . '-' . mt_rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $adsDir . '/' . $fname)) {
                    $stmt = $pdo->prepare("INSERT INTO house_ads (title, image, link, placement, enabled) VALUES (?,?,?,?,1)");
                    $stmt->execute([$title, $publicBase . $fname, $link, $place]);
                    $flash = 'Ad added and is now live.';
                } else {
                    $flash = 'Upload failed — check uploads/ads folder permissions.'; $flash_type = 'err';
                }
            }
        }
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE house_ads SET enabled = 1 - enabled WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        $flash = 'Ad updated.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $row = $pdo->prepare("SELECT image FROM house_ads WHERE id = ?"); $row->execute([$id]); $row = $row->fetch();
        if ($row) {
            $f = $adsDir . '/' . basename($row['image']);
            if (is_file($f)) @unlink($f);
        }
        $pdo->prepare("DELETE FROM house_ads WHERE id = ?")->execute([$id]);
        $flash = 'Ad deleted.';
    }
}

$ads = $pdo->query("SELECT * FROM house_ads ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/_dark_head.php';
?>
<?php if ($flash): ?><div class="panel" style="border-color:<?= $flash_type==='ok'?'#22c55e':'#ef4444' ?>;margin-bottom:14px"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<div class="grid g2">
  <div class="panel">
    <h3>➕ Add your ad</h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add">

      <label style="font-size:.8rem;color:var(--mut)">Ad image (PNG / WEBP / JPG / GIF)</label>
      <input class="sel" style="width:100%;margin:6px 0 14px" type="file" name="image" accept=".png,.webp,.jpg,.jpeg,.gif,.mp4,.webm,.mov" required>
      <div style="font-size:.7rem;color:var(--mut);margin:-8px 0 12px">For 🎁 Reward Video pick an <b>MP4</b> (keep it 3–8 MB, ~10–20s, 480–720p for fast loading). Other placements = image.</div>

      <label style="font-size:.8rem;color:var(--mut)">Link when clicked</label>
      <input class="sel" style="width:100%;margin:6px 0 14px" type="text" name="link" placeholder="https://example.com">

      <label style="font-size:.8rem;color:var(--mut)">Title (optional)</label>
      <input class="sel" style="width:100%;margin:6px 0 14px" type="text" name="title" placeholder="e.g. My promo">

      <label style="font-size:.8rem;color:var(--mut)">Show on</label>
      <select class="sel" style="width:100%;margin:6px 0 14px" name="placement">
        <?php foreach ($placements as $pk => $pl): ?>
          <option value="<?= $pk ?>"><?= ss_admin_h($pl) ?></option>
        <?php endforeach; ?>
      </select>

      <button class="btn pk" type="submit">⬆️ Add ad</button>
    </form>
    <div style="font-size:.72rem;color:var(--mut);margin-top:10px">
      Tip: banner size around <b>728×90</b> or <b>320×100</b> looks best. House ads only show when the
      master <b>Ads</b> switch and <b>Website ads</b> switch are ON (in Monetization).
    </div>
  </div>

  <div class="panel">
    <h3>📋 Your ads (<?= count($ads) ?>)</h3>
    <?php if (!$ads): ?>
      <div style="font-size:.85rem;color:var(--mut)">No ads yet. Add one on the left.</div>
    <?php else: foreach ($ads as $a): ?>
      <div style="display:flex;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.08)">
        <img src="<?= ss_admin_h($a['image']) ?>" alt="" style="width:90px;height:48px;object-fit:cover;border-radius:8px;background:#0002">
        <div style="flex:1;min-width:0">
          <div style="font-size:.85rem;color:var(--txt);font-weight:600"><?= ss_admin_h($a['title'] ?: '(no title)') ?></div>
          <div style="font-size:.72rem;color:var(--mut)"><?= ss_admin_h($a['placement']) ?> · <?= $a['enabled'] ? '🟢 On' : '⚪ Off' ?></div>
          <?php if ($a['link']): ?><div style="font-size:.7rem;color:var(--mut);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= ss_admin_h($a['link']) ?></div><?php endif; ?>
        </div>
        <form method="post" style="margin:0"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn" type="submit" title="On/Off"><?= $a['enabled'] ? 'Off' : 'On' ?></button></form>
        <form method="post" style="margin:0" onsubmit="return confirm('Delete this ad?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn" type="submit" style="border-color:#ef4444;color:#ef4444">🗑</button></form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php require __DIR__ . '/_dark_foot.php';
