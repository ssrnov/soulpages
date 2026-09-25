<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'App Update';
$PAGE_SUB   = 'Push an update — users are sent to your download page';
$PAGE_ICON  = '🚀';
$flash = ''; $flash_type = 'ok';

$uploadsDir = dirname(__DIR__) . '/uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'publish';

    // ── APK file upload ──
    if ($action === 'upload_apk') {
        if (empty($_FILES['apk']['name']) || ($_FILES['apk']['error'] ?? 1) !== UPLOAD_ERR_OK) {
            $flash = 'Please choose an APK file to upload.'; $flash_type = 'err';
        } elseif (strtolower(pathinfo($_FILES['apk']['name'], PATHINFO_EXTENSION)) !== 'apk') {
            $flash = 'Only .apk files are allowed.'; $flash_type = 'err';
        } else {
            if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);
            $fname = 'SoulSync-' . date('Ymd-His') . '.apk';
            $dest  = $uploadsDir . '/' . $fname;
            if (move_uploaded_file($_FILES['apk']['tmp_name'], $dest)) {
                ss_set_setting($pdo, 'apk_filename', $fname);
                ss_set_setting($pdo, 'download_enabled', '1'); // auto-enable once an APK exists
                $flash = 'APK uploaded successfully. Download is now live.';
            } else {
                $flash = 'Upload failed — check the uploads/ folder permissions.'; $flash_type = 'err';
            }
        }
    }
    // ── Set a direct APK download link (hosted anywhere) ──
    elseif ($action === 'set_link') {
        $link = trim($_POST['apk_link'] ?? '');
        if ($link !== '' && !preg_match('#^https?://#i', $link)) {
            $flash = 'Link must start with http:// or https://'; $flash_type = 'err';
        } else {
            ss_set_setting($pdo, 'download_url', $link);
            if ($link !== '') ss_set_setting($pdo, 'download_enabled', '1'); // auto-enable
            $flash = $link !== '' ? 'Direct link saved. Download now goes straight to your APK.'
                                  : 'Direct link cleared.';
        }
    }
    // ── Website download button link (separate from the app update link) ──
    elseif ($action === 'set_web_link') {
        $wl = trim($_POST['web_link'] ?? '');
        if ($wl !== '' && !preg_match('#^https?://#i', $wl)) {
            $flash = 'Link must start with http:// or https://'; $flash_type = 'err';
        } else {
            ss_set_setting($pdo, 'web_download_link', $wl);
            $flash = $wl !== '' ? 'Website download button link saved.' : 'Website link cleared (will use the direct link).';
        }
    }
    // ── Download on/off toggle ──
    elseif ($action === 'toggle_download') {
        $on = isset($_POST['download_enabled']) ? '1' : '0';
        ss_set_setting($pdo, 'download_enabled', $on);
        $flash = $on === '1' ? 'Download is now ON — users can download the app.' : 'Download is now OFF — the download page is closed.';
    }
    // ── Publish version / update settings ──
    else {
        $version  = trim($_POST['version'] ?? '');
        $force    = isset($_POST['force_update']) ? '1' : '0';
        $message  = trim($_POST['update_message'] ?? '');
        $download = trim($_POST['download_url'] ?? '');

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            $flash = 'Version must look like 1.0.1'; $flash_type = 'err';
        } elseif ($download !== '' && !preg_match('#^https?://#i', $download)) {
            $flash = 'Download URL must start with http:// or https://'; $flash_type = 'err';
        } else {
            ss_set_setting($pdo, 'latest_version', $version);
            if ($force === '1') ss_set_setting($pdo, 'min_app_version', $version);
            ss_set_setting($pdo, 'force_update', $force);
            ss_set_setting($pdo, 'update_message', $message);
            // Only touch the link if this form actually sent it (managed in the Direct Link panel now).
            if (isset($_POST['download_url'])) ss_set_setting($pdo, 'download_url', $download);
            $flash = 'Saved. ' . ($force === '1'
                ? 'Users below ' . ss_admin_h($version) . ' will be sent to the download page.'
                : 'Optional update is now available.');
        }
    }
}

$cur = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM app_settings") as $r) $cur[$r['setting_key']] = $r['setting_value'];
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
$defaultDl = $base . '/download.php';

// Current APK on the server + download switch.
$apkName = $cur['apk_filename'] ?? '';
$apkPath = $uploadsDir . '/' . $apkName;
$apkExists = $apkName && is_file($apkPath);
$apkSize = $apkExists ? round(filesize($apkPath) / 1048576, 1) . ' MB' : '—';
$dlOn = (int)($cur['download_enabled'] ?? 0) === 1;

require __DIR__ . '/_dark_head.php';
?>
<?php if ($flash): ?><div class="panel" style="border-color:<?= $flash_type==='ok'?'#22c55e':'#ef4444' ?>;margin-bottom:14px"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<div class="cards">
  <div class="stat"><div class="h"><span class="dot">📌</span> Latest Version</div><div class="v" style="font-size:1.4rem"><?= ss_admin_h($cur['latest_version'] ?? '1.0.0') ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🔴</span> Force Update</div><div class="v" style="font-size:1.1rem"><?= (int)($cur['force_update'] ?? 0) ? 'ON' : 'OFF' ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🔗</span> Direct link</div><div class="v" style="font-size:1rem"><?= !empty($cur['download_url']) ? '✅ Set' : '❌ None' ?></div></div>
  <div class="stat"><div class="h"><span class="dot"><?= $dlOn ? '🟢' : '⚪' ?></span> Download</div><div class="v" style="font-size:1.1rem"><?= $dlOn ? 'ON' : 'OFF' ?></div></div>
</div>

<div class="grid g2" style="margin-bottom:14px">
  <!-- Direct APK link -->
  <div class="panel">
    <h3>🔗 Direct APK Link</h3>
    <div style="font-size:.8rem;color:var(--mut);margin-bottom:12px">
      Paste the direct link to your APK file (hosted anywhere — your server, Google Drive, GitHub). The download button opens this directly. No file upload needed.
    </div>
    <form method="post">
      <input type="hidden" name="action" value="set_link">
      <input class="sel" style="width:100%;margin:6px 0 12px" type="text" name="apk_link"
             value="<?= ss_admin_h($cur['download_url'] ?? '') ?>" placeholder="https://soulsyncc.site/app/SoulSync.apk">
      <button class="btn pk" type="submit">💾 Save link</button>
    </form>
    <div style="font-size:.72rem;color:var(--mut);margin-top:10px">Saving a link auto-turns Download ON. Leave empty &amp; save to clear it.</div>
  </div>

  <!-- Website download button link (separate) -->
  <div class="panel">
    <h3>🌐 Website Download Button</h3>
    <div style="font-size:.8rem;color:var(--mut);margin-bottom:12px">
      The link the <b>Download button on your website</b> opens. Set it separately from the app-update link if you want. Leave empty to use the Direct APK Link above.
    </div>
    <form method="post">
      <input type="hidden" name="action" value="set_web_link">
      <input class="sel" style="width:100%;margin:6px 0 12px" type="text" name="web_link"
             value="<?= ss_admin_h($cur['web_download_link'] ?? '') ?>" placeholder="https://soulsyncc.site/app/SoulSync.apk">
      <button class="btn pk" type="submit">💾 Save website link</button>
    </form>
  </div>

  <!-- Download on/off -->
  <div class="panel">
    <h3><?= $dlOn ? '🟢' : '⚪' ?> Download Switch</h3>
    <div style="font-size:.85rem;color:#aab4cf;line-height:1.7;margin-bottom:14px">
      Turn the public download page ON or OFF. When OFF, users visiting the link see a "coming soon" message and cannot download — even if an APK is on the server.
    </div>
    <form method="post">
      <input type="hidden" name="action" value="toggle_download">
      <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;margin-bottom:14px">
        <input type="checkbox" name="download_enabled" value="1" <?= $dlOn ? 'checked' : '' ?>>
        Download enabled (users can download the app)
      </label>
      <button class="btn" type="submit">💾 Save switch</button>
    </form>
    <a class="btn" style="display:inline-block;margin-top:12px" href="<?= ss_admin_h($defaultDl) ?>" target="_blank">Test download page ↗</a>
  </div>
</div>

<div class="grid g2">
  <div class="panel">
    <h3>Publish an Update</h3>
    <form method="post">
      <label style="font-size:.8rem;color:var(--mut)">New version number</label>
      <input class="sel" style="width:100%;margin:6px 0 14px" name="version" value="<?= ss_admin_h($cur['latest_version'] ?? '1.0.1') ?>" placeholder="1.0.1" required>

      <div style="font-size:.72rem;color:var(--mut);margin-bottom:14px">Download link is set in the <b>Direct APK Link</b> panel above.</div>

      <label style="font-size:.8rem;color:var(--txt);font-weight:600">📝 What's new — this text is shown to users on the Update screen</label>
      <textarea class="sel" style="width:100%;margin:6px 0 4px;min-height:90px" name="update_message" placeholder="e.g.&#10;• Fixed battery & online status&#10;• New Countdown feature&#10;• Faster chat"><?= ss_admin_h($cur['update_message'] ?? '') ?></textarea>
      <div style="font-size:.72rem;color:var(--mut);margin-bottom:14px">Write what changed. Users see exactly this in the "Update Required" popup.</div>

      <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;margin-bottom:14px">
        <input type="checkbox" name="force_update" value="1" <?= (int)($cur['force_update'] ?? 0) ? 'checked' : '' ?>>
        Force update (block users on older versions)
      </label>

      <button class="btn pk" type="submit">🚀 Publish</button>
    </form>
  </div>

  <div class="panel">
    <h3>How it works</h3>
    <div style="font-size:.85rem;color:#aab4cf;line-height:1.8">
      1. <b>Upload the APK</b> above — it's stored on your server and the download page serves it automatically.<br>
      2. Set the new <b>version number</b> here and tick <b>Force update</b> if needed, then Publish.<br>
      3. Users on older versions see an <b>Update Required</b> screen; tapping <b>Update Now</b> opens your
      download page, where they download &amp; install the new APK.<br>
      4. Use the <b>Download Switch</b> to open or close the public download anytime.
    </div>
    <a class="btn" style="display:inline-block;margin-top:14px" href="<?= ss_admin_h($cur['download_url'] ?: $defaultDl) ?>" target="_blank">Open download page ↗</a>
  </div>
</div>
<?php require __DIR__ . '/_dark_foot.php';
