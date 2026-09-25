<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Broadcast';
$flash = ''; $flash_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $bodyt = trim($_POST['body'] ?? '');
    if ($title === '') {
        $flash = 'Title is required.'; $flash_type = 'err';
    } else {
        $ids = array_column($pdo->query("SELECT id FROM users WHERE role='user' AND status='active'")->fetchAll(), 'id');
        $ins = $pdo->prepare("INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'broadcast', ?, ?)");
        $pushed = 0;
        foreach ($ids as $uid) {
            $ins->execute([$uid, $title, $bodyt]);
            if (ss_push_to_user($pdo, $uid, $title, $bodyt, ['type' => 'broadcast'])) $pushed++;
        }
        $flash = "Broadcast sent to " . count($ids) . " users ($pushed push delivered).";
    }
}

$recent = $pdo->query(
    "SELECT title, body, created_at, COUNT(*) AS n FROM notifications
     WHERE type='broadcast' GROUP BY title, body, created_at ORDER BY created_at DESC LIMIT 20"
)->fetchAll();

require __DIR__ . '/_head.php';
?>
<?php if ($flash): ?><div class="flash <?= $flash_type ?>"><?= ss_admin_h($flash) ?></div><?php endif; ?>
<div class="panel">
  <h2>Send a Broadcast to All Users</h2>
  <form method="post" style="padding:16px;max-width:520px;">
    <label>Title</label><input name="title" required placeholder="e.g. New games are live! 🎮">
    <label>Message</label><textarea name="body" rows="3" placeholder="Optional message body"></textarea>
    <div style="margin-top:14px;"><button class="btn primary" onclick="return confirm('Send to every active user?')">📢 Send Broadcast</button></div>
  </form>
  <div class="sub" style="padding:0 16px 16px;">Push delivery needs the FCM server key set in Settings.</div>
</div>

<div class="panel">
  <h2>Recent Broadcasts</h2>
  <?php if (!$recent): ?>
    <div class="empty">No broadcasts sent yet.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Title</th><th>Message</th><th>Recipients</th><th>Sent</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><b><?= ss_admin_h($r['title']) ?></b></td>
        <td><?= ss_admin_h($r['body'] ?: '—') ?></td>
        <td><?= (int)$r['n'] ?></td>
        <td><?= ss_admin_h(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot.php';
