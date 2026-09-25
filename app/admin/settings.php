<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Settings';
$flash = '';

$keys = [
    'min_app_version'           => ['App min version', 'Users below this are asked to update'],
    'force_update'              => ['Force update (0/1)', 'If 1, users below min version are blocked'],
    'maintenance_mode'          => ['Maintenance mode (0/1)', 'If 1, app shows maintenance screen'],
    'maintenance_message'       => ['Maintenance message', 'Shown when maintenance is on'],
    'chat_poll_seconds'         => ['Chat poll interval (sec)', 'How often the app polls for new messages'],
    'location_interval_minutes' => ['Location interval (min)', 'How often the app records location (default 60)'],
    'fcm_service_account'       => ['FCM service-account JSON', 'RECOMMENDED. Firebase → Project settings → Service accounts → Generate new private key. Paste the whole JSON here.'],
    'fcm_server_key'            => ['FCM server key (legacy)', 'Only for older projects that still have a legacy server key. Leave blank if using the service account above.'],
    'fcm_project_id'            => ['FCM project id', 'Firebase project id (e.g. soulsync-85121)'],
    'welcome_message'           => ['Welcome message', 'Shown on the app splash/home'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($keys as $k => $_) {
        if (array_key_exists($k, $_POST)) {
            ss_set_setting($pdo, $k, trim($_POST[$k]));
        }
    }
    $flash = 'Settings saved.';
}

$current = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM app_settings") as $r) {
    $current[$r['setting_key']] = $r['setting_value'];
}

require __DIR__ . '/_head.php';
?>
<?php if ($flash): ?><div class="flash ok"><?= ss_admin_h($flash) ?></div><?php endif; ?>
<div class="panel">
  <h2>App &amp; Push Settings</h2>
  <form method="post" style="padding:16px;max-width:600px;">
    <?php foreach ($keys as $k => $meta): ?>
      <label><?= ss_admin_h($meta[0]) ?></label>
      <?php if ($k === 'fcm_service_account'): ?>
        <textarea name="<?= $k ?>" rows="5" style="font-family:monospace;font-size:.72rem;" placeholder='{"type":"service_account", ...}'><?= ss_admin_h($current[$k] ?? '') ?></textarea>
      <?php elseif ($k === 'maintenance_message' || $k === 'welcome_message'): ?>
        <textarea name="<?= $k ?>" rows="2"><?= ss_admin_h($current[$k] ?? '') ?></textarea>
      <?php else: ?>
        <input name="<?= $k ?>" value="<?= ss_admin_h($current[$k] ?? '') ?>" <?= $k==='fcm_server_key' ? 'type="password"' : '' ?>>
      <?php endif; ?>
      <div style="font-size:.72rem;color:#94a3b8;margin-top:3px;"><?= ss_admin_h($meta[1]) ?></div>
    <?php endforeach; ?>
    <div style="margin-top:18px;"><button class="btn primary">Save Settings</button></div>
  </form>
</div>
<?php require __DIR__ . '/_foot.php';
