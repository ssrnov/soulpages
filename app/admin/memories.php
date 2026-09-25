<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Memories';
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id && ($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare("DELETE FROM memories WHERE id=?")->execute([$id]);
        $flash = "Memory #$id deleted.";
    }
}

$rows = $pdo->query(
    "SELECT m.*, u.username FROM memories m JOIN users u ON u.id=m.author_id
     ORDER BY m.id DESC LIMIT 200"
)->fetchAll();

require __DIR__ . '/_head.php';
?>
<?php if ($flash): ?><div class="flash ok"><?= ss_admin_h($flash) ?></div><?php endif; ?>
<div class="panel">
  <h2>All Memories (<?= count($rows) ?>)</h2>
  <?php if (!$rows): ?>
    <div class="empty">No memories yet.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>ID</th><th>Type</th><th>Title</th><th>Author</th><th>Fav</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): ?>
      <tr>
        <td>#<?= (int)$m['id'] ?></td>
        <td><span class="pill off"><?= ss_admin_h($m['type']) ?></span></td>
        <td><?= ss_admin_h($m['title'] ?: '—') ?></td>
        <td>@<?= ss_admin_h($m['username']) ?></td>
        <td><?= $m['is_favorite'] ? '⭐' : '' ?></td>
        <td><?= ss_admin_h(date('d M Y', strtotime($m['created_at']))) ?></td>
        <td>
          <form method="post" style="display:inline;">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn danger" name="action" value="delete" onclick="return confirm('Delete this memory?')">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot.php';
