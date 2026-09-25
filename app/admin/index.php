<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Dashboard';

$one = function ($sql) use ($pdo) { try { return $pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; } };

$total_users   = (int)$one("SELECT COUNT(*) FROM users WHERE role='user'");
$online_users  = (int)$one("SELECT COUNT(*) FROM users WHERE is_online=1 AND presence_at > DATE_SUB(NOW(), INTERVAL 70 SECOND)");
$couples       = (int)$one("SELECT COUNT(*) FROM couples WHERE status='connected'");
$pending       = (int)$one("SELECT COUNT(*) FROM couples WHERE status='pending'");
$messages      = (int)$one("SELECT COUNT(*) FROM messages");
$msgs_today    = (int)$one("SELECT COUNT(*) FROM messages WHERE DATE(created_at)=CURDATE()");
$memories      = (int)$one("SELECT COUNT(*) FROM memories");
$buzzes        = (int)$one("SELECT COUNT(*) FROM love_buzz");
$locations     = (int)$one("SELECT COUNT(*) FROM locations");
$sharing_loc   = (int)$one("SELECT COUNT(*) FROM tracking_settings WHERE share_location=1");

$recent = [];
try {
    $recent = $pdo->query("SELECT id, username, name, is_online, last_seen, created_at FROM users ORDER BY id DESC LIMIT 8")->fetchAll();
} catch (\Throwable $e) {}

require __DIR__ . '/_head.php';
?>
<div class="cards">
  <div class="card"><div class="k">Total Users</div><div class="v"><?= $total_users ?></div></div>
  <div class="card"><div class="k">Online Now</div><div class="v"><?= $online_users ?></div></div>
  <div class="card"><div class="k">Connected Couples</div><div class="v"><?= $couples ?></div></div>
  <div class="card"><div class="k">Pending Requests</div><div class="v"><?= $pending ?></div></div>
  <div class="card"><div class="k">Messages Today</div><div class="v"><?= $msgs_today ?></div></div>
  <div class="card"><div class="k">Total Messages</div><div class="v"><?= $messages ?></div></div>
  <div class="card"><div class="k">Memories</div><div class="v"><?= $memories ?></div></div>
  <div class="card"><div class="k">Love Buzz Sent</div><div class="v"><?= $buzzes ?></div></div>
  <div class="card"><div class="k">Location Pings</div><div class="v"><?= $locations ?></div></div>
  <div class="card"><div class="k">Sharing Location</div><div class="v"><?= $sharing_loc ?></div></div>
</div>

<div class="panel">
  <h2>Newest Users</h2>
  <?php if (!$recent): ?>
    <div class="empty">No users yet.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Status</th><th>Joined</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): $on = $r['is_online'] && strtotime($r['last_seen']) > time()-120; ?>
      <tr>
        <td>#<?= (int)$r['id'] ?></td>
        <td>@<?= ss_admin_h($r['username']) ?></td>
        <td><?= ss_admin_h($r['name']) ?></td>
        <td><span class="pill <?= $on ? 'on' : 'off' ?>"><?= $on ? 'Online' : 'Offline' ?></span></td>
        <td><?= ss_admin_h(date('d M Y', strtotime($r['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot.php';
