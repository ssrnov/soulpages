<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Games';
$flash = '';

$chatGames = [
    'couple_quiz'  => '🎮 Couple Quiz',
    'would_rather' => '🤔 Would You Rather',
    'truth_dare'   => '💕 Truth & Dare',
    'emoji'        => '😊 Emoji Challenge',
    'puzzle'       => '🧩 Puzzle Time',
    'nhie'         => '❤️ Never Have I Ever',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'chat_games') {
        // Save ON/OFF for each in-chat game.
        foreach ($chatGames as $gk => $_lbl) {
            $on = isset($_POST['g_' . $gk]) ? '1' : '0';
            ss_set_setting($pdo, 'game_' . $gk . '_on', $on);
        }
        $flash = 'Game availability updated.';
    } elseif ($act === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $_POST['status'] ?? 'enabled';
        if (in_array($st, ['enabled','disabled','coming_soon'], true)) {
            $pdo->prepare("UPDATE games SET status=? WHERE id=?")->execute([$st, $id]);
            $flash = "Game updated.";
        }
    } elseif ($act === 'add') {
        $name = trim($_POST['name'] ?? '');
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
        if ($name && $slug) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO games (slug,name,emoji,description,type,status,sort_order) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$slug, $name, trim($_POST['emoji'] ?: '🎮'), trim($_POST['description'] ?? ''),
                            $_POST['type'] ?? 'turn', 'enabled', (int)($_POST['sort_order'] ?? 99)]);
            $flash = "Game added.";
        }
    }
}

$games = $pdo->query("SELECT * FROM games ORDER BY sort_order ASC")->fetchAll();
require __DIR__ . '/_head.php';
?>
<?php if ($flash): ?><div class="flash ok"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<div class="panel">
  <h2>Game Availability (in-chat games)</h2>
  <p style="color:#94a3b8;font-size:.82rem;margin:0 16px 12px">Turn a game ON/OFF for all users. OFF games are hidden from the app's chat & Games page.</p>
  <form method="post" style="padding:0 16px 16px">
    <input type="hidden" name="action" value="chat_games">
    <?php foreach ($chatGames as $gk => $lbl): $on = (int)ss_setting($pdo, 'game_' . $gk . '_on', '1') === 1; ?>
      <label style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f1f3f9;font-weight:600;">
        <input type="checkbox" name="g_<?= $gk ?>" value="1" <?= $on ? 'checked' : '' ?> style="width:18px;height:18px">
        <?= ss_admin_h($lbl) ?>
        <span style="margin-left:auto;font-size:.72rem;color:<?= $on ? '#16a34a' : '#94a3b8' ?>"><?= $on ? 'ON' : 'OFF' ?></span>
      </label>
    <?php endforeach; ?>
    <button class="btn primary" type="submit" style="margin-top:14px">Save availability</button>
  </form>
</div>

<div class="panel">
  <h2>Games Catalogue</h2>
  <table>
    <thead><tr><th>Order</th><th>Game</th><th>Type</th><th>Status</th><th>Change</th></tr></thead>
    <tbody>
    <?php foreach ($games as $g): ?>
      <tr>
        <td><?= (int)$g['sort_order'] ?></td>
        <td><?= ss_admin_h($g['emoji']) ?> <b><?= ss_admin_h($g['name']) ?></b><br><span style="color:#94a3b8;font-size:.76rem"><?= ss_admin_h($g['description']) ?></span></td>
        <td><?= ss_admin_h($g['type']) ?></td>
        <td>
          <span class="pill <?= $g['status']==='enabled'?'on':($g['status']==='coming_soon'?'warn':'off') ?>"><?= ss_admin_h($g['status']) ?></span>
        </td>
        <td>
          <form method="post" style="display:flex;gap:6px;">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
            <select name="status" style="width:auto;">
              <option value="enabled" <?= $g['status']==='enabled'?'selected':'' ?>>Enabled</option>
              <option value="coming_soon" <?= $g['status']==='coming_soon'?'selected':'' ?>>Coming soon</option>
              <option value="disabled" <?= $g['status']==='disabled'?'selected':'' ?>>Disabled</option>
            </select>
            <button class="btn primary">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h2>Add a Game</h2>
  <form method="post" style="padding:16px;max-width:520px;">
    <input type="hidden" name="action" value="add">
    <label>Name</label><input name="name" required placeholder="e.g. Story Builder">
    <label>Emoji</label><input name="emoji" placeholder="🎮" maxlength="4">
    <label>Description</label><input name="description" placeholder="Short one-line description">
    <label>Type</label>
    <select name="type"><option value="turn">Turn-based</option><option value="quiz">Quiz</option><option value="realtime">Realtime</option></select>
    <label>Sort order</label><input name="sort_order" type="number" value="99">
    <div style="margin-top:14px;"><button class="btn primary">Add Game</button></div>
  </form>
</div>
<?php require __DIR__ . '/_foot.php';
