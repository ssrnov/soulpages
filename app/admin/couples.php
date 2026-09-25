<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Couples';
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && $action === 'disconnect') {
        $pdo->prepare("UPDATE couples SET status='ended', ended_at=NOW() WHERE id=?")->execute([$id]);
        $flash = "Couple #$id disconnected (data kept 10 days).";
    } elseif ($id && $action === 'purge') {
        foreach (['messages','memories','countdown_events','chat_daily','game_sessions'] as $tbl) {
            try { $pdo->prepare("DELETE FROM `$tbl` WHERE couple_id=?")->execute([$id]); } catch (\Throwable $e) {}
        }
        $pdo->prepare("DELETE FROM couples WHERE id=?")->execute([$id]);
        $flash = "Couple #$id data permanently deleted.";
    }
}

// Disconnected couples still within the 10-day retention window.
$retained = [];
try {
    $retained = $pdo->query(
        "SELECT c.*, u1.username AS u1name, u2.username AS u2name,
            (SELECT COUNT(*) FROM messages m WHERE m.couple_id=c.id) AS msgs,
            (SELECT COUNT(*) FROM memories mm WHERE mm.couple_id=c.id) AS mems
         FROM couples c
         JOIN users u1 ON u1.id=c.user1_id JOIN users u2 ON u2.id=c.user2_id
         WHERE c.status='ended' AND c.ended_at IS NOT NULL ORDER BY c.ended_at DESC LIMIT 200"
    )->fetchAll();
} catch (\Throwable $e) {}

$couples = $pdo->query(
    "SELECT c.*, u1.username AS u1name, u1.name AS u1full, u2.username AS u2name, u2.name AS u2full
     FROM couples c
     JOIN users u1 ON u1.id=c.user1_id
     JOIN users u2 ON u2.id=c.user2_id
     WHERE c.status='connected' ORDER BY c.id DESC LIMIT 200"
)->fetchAll();

$pending = $pdo->query(
    "SELECT c.*, u1.username AS u1name, u2.username AS u2name
     FROM couples c
     JOIN users u1 ON u1.id=c.user1_id
     JOIN users u2 ON u2.id=c.user2_id
     WHERE c.status='pending' ORDER BY c.id DESC LIMIT 100"
)->fetchAll();

require __DIR__ . '/_head.php';
?>
<?php if ($flash): ?><div class="flash ok"><?= ss_admin_h($flash) ?></div><?php endif; ?>
<div class="panel">
  <h2>Connected Couples (<?= count($couples) ?>)</h2>
  <?php if (!$couples): ?>
    <div class="empty">No connected couples yet.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>ID</th><th>Partner A</th><th>Partner B</th><th>Since</th><th>Streak</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($couples as $c): ?>
      <tr>
        <td>#<?= (int)$c['id'] ?></td>
        <td><?= ss_admin_h($c['u1full']) ?> <span style="color:#94a3b8">@<?= ss_admin_h($c['u1name']) ?></span></td>
        <td><?= ss_admin_h($c['u2full']) ?> <span style="color:#94a3b8">@<?= ss_admin_h($c['u2name']) ?></span></td>
        <td><?= $c['since'] ? ss_admin_h(date('d M Y', strtotime($c['since']))) : '—' ?></td>
        <td>🔥 <?= (int)$c['streak_count'] ?></td>
        <td>
          <form method="post" style="display:inline;">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn danger" name="action" value="disconnect" onclick="return confirm('Disconnect this couple?')">Disconnect</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>Disconnected — data kept 10 days (<?= count($retained) ?>)</h2>
  <?php if (!$retained): ?>
    <div class="empty">No disconnected couples in retention.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>ID</th><th>Partner A</th><th>Partner B</th><th>Disconnected</th><th>Data</th><th>Auto-delete in</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($retained as $c):
      $daysLeft = 10 - floor((time() - strtotime($c['ended_at'])) / 86400);
    ?>
      <tr>
        <td>#<?= (int)$c['id'] ?></td>
        <td>@<?= ss_admin_h($c['u1name']) ?></td>
        <td>@<?= ss_admin_h($c['u2name']) ?></td>
        <td><?= ss_admin_h(date('d M Y', strtotime($c['ended_at']))) ?></td>
        <td>💬 <?= (int)$c['msgs'] ?> · 📸 <?= (int)$c['mems'] ?></td>
        <td><?= $daysLeft > 0 ? $daysLeft.' days' : 'purging…' ?></td>
        <td>
          <form method="post" style="display:inline;">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn danger" name="action" value="purge" onclick="return confirm('Permanently delete all their data now?')">Delete now</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>Pending Requests (<?= count($pending) ?>)</h2>
  <?php if (!$pending): ?>
    <div class="empty">No pending requests.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>ID</th><th>From</th><th>To</th><th>Requested</th></tr></thead>
    <tbody>
    <?php foreach ($pending as $c): ?>
      <tr>
        <td>#<?= (int)$c['id'] ?></td>
        <td>@<?= ss_admin_h($c['u1name']) ?></td>
        <td>@<?= ss_admin_h($c['u2name']) ?></td>
        <td><?= ss_admin_h(date('d M Y H:i', strtotime($c['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot.php';
