<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Users';
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($id && $act === 'block')   { $pdo->prepare("UPDATE users SET status='blocked' WHERE id=?")->execute([$id]); $flash = "User #$id blocked."; }
    if ($id && $act === 'unblock') { $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]); $flash = "User #$id unblocked."; }
    if ($id && $act === 'delete')  { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]); $flash = "User #$id deleted."; }
    if ($id && $act === 'premium_on') {
        // Grant premium for 1 year. (Columns auto-created by db.php migration.)
        try { $pdo->prepare("UPDATE users SET is_premium=1, premium_until=DATE_ADD(NOW(), INTERVAL 365 DAY) WHERE id=?")->execute([$id]); $flash = "User #$id upgraded to Premium."; }
        catch (\Throwable $e) { $flash = "Couldn't set premium: " . $e->getMessage(); }
    }
    if ($id && $act === 'premium_off') {
        try { $pdo->prepare("UPDATE users SET is_premium=0, premium_until=NULL WHERE id=?")->execute([$id]); $flash = "Premium removed from user #$id."; }
        catch (\Throwable $e) { $flash = "Couldn't remove premium: " . $e->getMessage(); }
    }
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role='user' AND (username LIKE ? OR name LIKE ? OR email LIKE ?) ORDER BY id DESC LIMIT 200");
    $like = "%$q%"; $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM users WHERE role='user' ORDER BY id DESC LIMIT 200");
}
$users = $stmt->fetchAll();

require __DIR__ . '/_head.php';
?>
<?php if ($flash): ?><div class="flash ok"><?= ss_admin_h($flash) ?></div><?php endif; ?>
<form method="get" style="margin-bottom:16px;max-width:340px;">
  <input type="search" name="q" value="<?= ss_admin_h($q) ?>" placeholder="Search username, name or email…">
</form>
<div class="panel">
  <h2>Users (<?= count($users) ?>)</h2>
  <?php if (!$users): ?>
    <div class="empty">No users found.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Status</th><th>Premium</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u):
      $isPrem = (int)($u['is_premium'] ?? 0) === 1 && (empty($u['premium_until']) || strtotime($u['premium_until']) > time());
    ?>
      <tr>
        <td>#<?= (int)$u['id'] ?></td>
        <td>@<?= ss_admin_h($u['username']) ?></td>
        <td><?= ss_admin_h($u['name']) ?></td>
        <td><?= ss_admin_h($u['email'] ?: '—') ?></td>
        <td><span class="pill <?= $u['status']==='blocked' ? 'bad' : 'on' ?>"><?= ss_admin_h($u['status']) ?></span></td>
        <td>
          <?php if ($isPrem): ?><span class="pill warn">👑 Premium</span>
          <?php else: ?><span class="pill off">Free</span><?php endif; ?>
        </td>
        <td><?= ss_admin_h(date('d M Y', strtotime($u['created_at']))) ?></td>
        <td style="white-space:nowrap;">
          <form method="post" style="display:inline;">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <?php if ($isPrem): ?>
              <button class="btn" name="action" value="premium_off">Remove Premium</button>
            <?php else: ?>
              <button class="btn primary" name="action" value="premium_on">Make Premium</button>
            <?php endif; ?>
            <?php if ($u['status']==='blocked'): ?>
              <button class="btn" name="action" value="unblock">Unblock</button>
            <?php else: ?>
              <button class="btn" name="action" value="block">Block</button>
            <?php endif; ?>
            <button class="btn danger" name="action" value="delete" onclick="return confirm('Delete this user and all their data?')">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot.php';
