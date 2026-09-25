<?php
// Premium Control — pick which games & features require SoulSync Premium.
// Only the owner/admin sees this. The app reads the locked list from /config.
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Premium Control';
$PAGE_SUB   = 'Lock any game or feature behind Premium';
$PAGE_ICON  = '👑';
$flash = '';

// Core app features that can be locked (key => [emoji, label]).
$features = [
    'chat'         => ['💬', 'Chat'],
    'memories'     => ['📸', 'Memories'],
    'games'        => ['🎮', 'Games (all)'],
    'love_buzz'    => ['💜', 'Love Buzz'],
    'calls'        => ['📞', 'Voice / Video calls'],
    'countdown'    => ['⏳', 'Countdown events'],
    'location'     => ['📍', 'Partner location'],
    'app_usage'    => ['📊', 'Partner app usage'],
    'screen_time'  => ['📱', 'Partner screen time'],
    'notifications'=> ['🔔', 'Partner notifications'],
    'battery'      => ['🔋', 'Partner battery'],
];

// Individual games from the catalogue.
$games = [];
try { $games = $pdo->query("SELECT slug, name, emoji FROM games ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC); } catch (\Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $locked = [];
    foreach (($_POST['lock'] ?? []) as $k => $_) $locked[] = preg_replace('/[^a-z0-9_\-]/i', '', $k);
    ss_set_setting($pdo, 'premium_locked', implode(',', array_unique(array_filter($locked))));
    $flash = 'Saved. Locked items now need Premium in the app.';
}

$lockedSet = array_filter(array_map('trim', explode(',', ss_setting($pdo, 'premium_locked', ''))));
$isLocked = fn($k) => in_array($k, $lockedSet, true);

require __DIR__ . '/_dark_head.php';
?>
<?php if ($flash): ?><div class="panel" style="border-color:#22c55e;margin-bottom:14px"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<form method="post">
  <div class="grid g2">
    <div class="panel">
      <h3>👑 Premium Features</h3>
      <div style="font-size:.78rem;color:var(--mut);margin-bottom:12px">Turn ON to require Premium. Free users tapping it see the upgrade page.</div>
      <?php foreach ($features as $k => $f): ?>
        <label style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.07);font-size:.9rem;color:var(--txt)">
          <input type="checkbox" name="lock[feat_<?= $k ?>]" value="1" <?= $isLocked('feat_'.$k) ? 'checked' : '' ?>>
          <span style="font-size:1.1rem"><?= $f[0] ?></span> <?= ss_admin_h($f[1]) ?>
          <span style="margin-left:auto;font-size:.72rem;color:<?= $isLocked('feat_'.$k)?'#f59e0b':'var(--mut)' ?>"><?= $isLocked('feat_'.$k)?'👑 Premium':'Free' ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <h3>🎮 Individual Games</h3>
      <div style="font-size:.78rem;color:var(--mut);margin-bottom:12px">Lock specific games (works even if "Games (all)" is free).</div>
      <?php if (!$games): ?>
        <div style="font-size:.85rem;color:var(--mut)">No games found in the catalogue.</div>
      <?php else: foreach ($games as $g): $gk = 'game_' . $g['slug']; ?>
        <label style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.07);font-size:.9rem;color:var(--txt)">
          <input type="checkbox" name="lock[<?= ss_admin_h($gk) ?>]" value="1" <?= $isLocked($gk) ? 'checked' : '' ?>>
          <span style="font-size:1.1rem"><?= ss_admin_h($g['emoji'] ?: '🎮') ?></span> <?= ss_admin_h($g['name']) ?>
          <span style="margin-left:auto;font-size:.72rem;color:<?= $isLocked($gk)?'#f59e0b':'var(--mut)' ?>"><?= $isLocked($gk)?'👑 Premium':'Free' ?></span>
        </label>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <button class="btn pk" type="submit" style="margin-top:12px">💾 Save Premium Locks</button>
</form>

<div class="panel" style="margin-top:14px">
  <h3>How it works</h3>
  <div style="font-size:.85rem;color:#aab4cf;line-height:1.8">
    • Tick anything to make it <b>Premium-only</b>. Free users who tap it are sent to the <b>upgrade / purchase</b> page.<br>
    • Premium users (and you) see everything unlocked.<br>
    • Payments must be enabled in <b>Monetization</b> for the purchase page to work.
  </div>
</div>
<?php require __DIR__ . '/_dark_foot.php';
