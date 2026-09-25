<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();
$PAGE_TITLE = 'Tracking Features';
$flash = '';

// Ensure the table exists (in case DB was created before this feature shipped).
try { $pdo->query("SELECT 1 FROM tracking_features LIMIT 1"); }
catch (\Throwable $e) {
    // Trigger the db.php migration by re-including is not trivial here; create inline.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `tracking_features` (
            `feature_key` VARCHAR(50) PRIMARY KEY, `name` VARCHAR(120) NOT NULL,
            `emoji` VARCHAR(10) DEFAULT '📊', `description` VARCHAR(255) NULL,
            `status` VARCHAR(20) DEFAULT 'enabled', `sort_order` INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $ex) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['status'] ?? [] as $key => $st) {
        if (in_array($st, ['enabled','disabled','coming_soon'], true)) {
            $pdo->prepare("UPDATE tracking_features SET status=? WHERE feature_key=?")->execute([$st, $key]);
        }
    }
    $flash = 'Tracking features updated. The app picks up changes on next sync.';
}

$features = $pdo->query("SELECT * FROM tracking_features ORDER BY sort_order")->fetchAll();
require __DIR__ . '/_head.php';
?>
<div class="sub">Control which tracking features users see in the app. <b>Enabled</b> = visible & active · <b>Coming soon</b> = shown greyed out · <b>Disabled</b> = hidden completely.</div>
<?php if ($flash): ?><div class="flash ok"><?= ss_admin_h($flash) ?></div><?php endif; ?>
<form method="post">
<div class="panel">
  <h2>Tracking Features</h2>
  <table>
    <thead><tr><th>Feature</th><th>Description</th><th>Visibility</th></tr></thead>
    <tbody>
    <?php foreach ($features as $f): ?>
      <tr>
        <td><?= ss_admin_h($f['emoji']) ?> <b><?= ss_admin_h($f['name']) ?></b></td>
        <td style="color:#64748b;"><?= ss_admin_h($f['description']) ?></td>
        <td>
          <select name="status[<?= ss_admin_h($f['feature_key']) ?>]" style="width:auto;">
            <option value="enabled"     <?= $f['status']==='enabled'?'selected':'' ?>>✅ Enabled</option>
            <option value="coming_soon" <?= $f['status']==='coming_soon'?'selected':'' ?>>🔜 Coming soon</option>
            <option value="disabled"    <?= $f['status']==='disabled'?'selected':'' ?>>🚫 Disabled</option>
          </select>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<button class="btn primary" type="submit">Save Changes</button>
</form>
<?php require __DIR__ . '/_foot.php';
