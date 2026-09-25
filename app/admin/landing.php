<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Landing Page';
$PAGE_SUB   = 'Edit the public SoulSync app page (soulsync.php)';
$PAGE_ICON  = '🌐';
$flash = '';

$fields = [
    'landing_headline'      => ['Headline', 'Distance Love, Close Hearts'],
    'landing_subtitle'      => ['Subtitle', 'SoulSync keeps couples close — private chat, shared moods, memories, games, and gentle ways to feel connected all day.'],
    'landing_download_text' => ['Download button text', 'Download for Android'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $k => $_) ss_set_setting($pdo, $k, trim($_POST[$k] ?? ''));
    ss_set_setting($pdo, 'landing_features', trim($_POST['landing_features'] ?? ''));
    ss_set_setting($pdo, 'landing_coming', trim($_POST['landing_coming'] ?? ''));
    $flash = 'Saved. Your public page soulsync.php now shows these.';
}

$cur = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM app_settings") as $r) $cur[$r['setting_key']] = $r['setting_value'];

$defFeatures = "💬 | Private Chat | Just you two. Real-time messages with read receipts.\n💜 | Love Buzz | One tap sends a warm buzz to your partner's phone.\n😊 | Moods & Notes | Share how you feel and leave sweet notes.\n📸 | Memories | Save your favourite photos, videos and moments.\n🎮 | Couple Games | Truth or Dare, quizzes and more.\n🔋 | Know Your Partner | Battery, screen time & location (when you both share).\n📌 | Home Widget | Your partner's note, right on your home screen.\n🔥 | Streaks | Keep your daily connection streak going.";
$defComing   = "📞 | Voice & Video Calls | Call your partner inside SoulSync.\n🎤 | Voice Notes | Send quick voice messages in chat.\n🗺️ | Shared Places | Mark and revisit the places you love.";

require __DIR__ . '/_dark_head.php';
?>
<?php if ($flash): ?><div class="panel" style="border-color:#22c55e;margin-bottom:14px"><?= ss_admin_h($flash) ?></div><?php endif; ?>
<div class="grid g2">
  <div class="panel">
    <h3>Page Content</h3>
    <form method="post">
      <?php foreach ($fields as $k => $meta): ?>
        <label style="font-size:.8rem;color:var(--mut)"><?= ss_admin_h($meta[0]) ?></label>
        <?php if ($k === 'landing_subtitle'): ?>
          <textarea class="sel" style="width:100%;margin:6px 0 14px;min-height:60px" name="<?= $k ?>"><?= ss_admin_h($cur[$k] ?? $meta[1]) ?></textarea>
        <?php else: ?>
          <input class="sel" style="width:100%;margin:6px 0 14px" name="<?= $k ?>" value="<?= ss_admin_h($cur[$k] ?? $meta[1]) ?>">
        <?php endif; ?>
      <?php endforeach; ?>

      <label style="font-size:.8rem;color:var(--mut)">Features — one per line: <code>emoji | title | description</code></label>
      <textarea class="sel" style="width:100%;margin:6px 0 14px;min-height:170px;font-size:.82rem" name="landing_features"><?= ss_admin_h($cur['landing_features'] ?? $defFeatures) ?></textarea>

      <label style="font-size:.8rem;color:var(--mut)">Coming Soon — one per line: <code>emoji | title | description</code></label>
      <textarea class="sel" style="width:100%;margin:6px 0 14px;min-height:110px;font-size:.82rem" name="landing_coming"><?= ss_admin_h($cur['landing_coming'] ?? $defComing) ?></textarea>

      <button class="btn pk" type="submit">💾 Save Landing Page</button>
    </form>
  </div>
  <div class="panel">
    <h3>Notes</h3>
    <div style="font-size:.85rem;color:#aab4cf;line-height:1.8">
      • These edits appear on your public page <b>soulsync.php</b> instantly.<br>
      • The <b>Download button</b> link is set in <a href="app-update.php" style="color:#ec4899">App Update</a> (Download page URL).<br>
      • Add or remove features by adding/removing lines. Format each line as
        <code>emoji | title | description</code>.<br>
      • Leave a section empty to hide it.
    </div>
    <a class="btn" style="display:inline-block;margin-top:14px" href="../soulsync.php" target="_blank">Preview page ↗</a>
  </div>
</div>
<?php require __DIR__ . '/_dark_foot.php';
