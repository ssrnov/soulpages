<?php
// Reviews — ratings submitted from the website Rate page.
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Reviews';
$PAGE_SUB   = 'Ratings & feedback from users';
$PAGE_ICON  = '⭐';
$flash = '';

$pdo->exec("CREATE TABLE IF NOT EXISTS app_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) DEFAULT '',
    rating TINYINT NOT NULL DEFAULT 5, comment VARCHAR(600) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare("DELETE FROM app_reviews WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    $flash = 'Review deleted.';
}

$rows = $pdo->query("SELECT * FROM app_reviews ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
$stat = $pdo->query("SELECT COUNT(*) c, AVG(rating) a FROM app_reviews")->fetch(PDO::FETCH_ASSOC);
$count = (int)($stat['c'] ?? 0);
$avg = $count ? round((float)$stat['a'], 1) : 0;

require __DIR__ . '/_dark_head.php';
?>
<?php if ($flash): ?><div class="panel" style="border-color:#22c55e;margin-bottom:14px"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<div class="cards">
  <div class="stat"><div class="h"><span class="dot">⭐</span> Average</div><div class="v" style="font-size:1.4rem"><?= $avg ?: '—' ?> / 5</div></div>
  <div class="stat"><div class="h"><span class="dot">💬</span> Total reviews</div><div class="v" style="font-size:1.4rem"><?= $count ?></div></div>
</div>

<div class="panel">
  <h3>Recent Reviews</h3>
  <?php if (!$rows): ?>
    <div style="font-size:.85rem;color:var(--mut)">No reviews yet. Share your Rate page: <code>/soulpages/rate.php</code></div>
  <?php else: foreach ($rows as $r): ?>
    <div style="display:flex;gap:12px;align-items:flex-start;padding:11px 0;border-bottom:1px solid rgba(255,255,255,.07)">
      <div style="color:#f5c518;font-size:.95rem;white-space:nowrap"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:.85rem;color:var(--txt);font-weight:600"><?= ss_admin_h($r['name'] ?: 'Anonymous') ?>
          <span style="font-size:.7rem;color:var(--mut);font-weight:400">· <?= ss_admin_h($r['created_at']) ?></span></div>
        <?php if ($r['comment']): ?><div style="font-size:.82rem;color:#aab4cf;margin-top:2px"><?= nl2br(ss_admin_h($r['comment'])) ?></div><?php endif; ?>
      </div>
      <form method="post" style="margin:0" onsubmit="return confirm('Delete this review?')">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button class="btn" type="submit" style="border-color:#ef4444;color:#ef4444">🗑</button>
      </form>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/_dark_foot.php';
