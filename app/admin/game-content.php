<?php
// Game Content — manage questions/challenges for every game, per-game layout.
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Game Content';
$PAGE_SUB   = 'Add & manage questions for each game';
$PAGE_ICON  = '🎲';
$flash = ''; $flash_type = 'ok';

$pdo->exec("CREATE TABLE IF NOT EXISTS game_questions (
    id INT AUTO_INCREMENT PRIMARY KEY, game VARCHAR(40) NOT NULL DEFAULT 'truth_or_dare',
    type VARCHAR(10) NOT NULL DEFAULT 'truth', category VARCHAR(30) NOT NULL DEFAULT 'couple',
    text VARCHAR(400) NOT NULL, extra VARCHAR(400) NULL, enabled TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(game), INDEX(type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
try { $pdo->query("SELECT extra FROM game_questions LIMIT 1"); }
catch (\Throwable $e) { try { $pdo->exec("ALTER TABLE game_questions ADD COLUMN extra VARCHAR(400) NULL"); } catch (\Throwable $ex) {} }

$categories = ['romantic','funny','cute','personal','couple','friendship','crazy','deep','spicy','birthday','anniversary'];

// Each game defines its own input layout.
// fields: which inputs to show. type: only for truth_or_dare.
$games = [
    'truth_or_dare'    => ['🎭', 'Truth or Dare',     ['type'=>true,  'cat'=>true,  'text'=>'Truth / Dare text',        'extra'=>null]],
    'never_have_i_ever'=> ['🙈', 'Never Have I Ever',  ['type'=>false, 'cat'=>true,  'text'=>'Statement (Never have I ever…)', 'extra'=>null]],
    'would_you_rather' => ['🤔', 'Would You Rather',   ['type'=>false, 'cat'=>true,  'text'=>'Option A',                 'extra'=>'Option B']],
    'couple_quiz'      => ['❓', 'Couple Quiz',        ['type'=>false, 'cat'=>true,  'text'=>'Question',                 'extra'=>'Answer (optional)']],
    'emoji_challenge'  => ['😀', 'Emoji Challenge',    ['type'=>false, 'cat'=>false, 'text'=>'Emoji clue (e.g. 🍕❤️)',   'extra'=>'Answer']],
    'love_letters'     => ['💌', 'Love Letters',       ['type'=>false, 'cat'=>true,  'text'=>'Prompt / starter line',    'extra'=>null]],
];

$fGame = $_GET['g'] ?? 'truth_or_dare';
if (!isset($games[$fGame])) $fGame = 'truth_or_dare';
$cfg = $games[$fGame][2];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $game = $_POST['game'] ?? 'truth_or_dare';
    if (!isset($games[$game])) $game = 'truth_or_dare';
    $gc = $games[$game][2];

    if ($action === 'add') {
        $type = ($gc['type'] && ($_POST['type'] ?? '') === 'dare') ? 'dare' : 'truth';
        $cat  = ($gc['cat'] && in_array($_POST['category'] ?? '', $categories, true)) ? $_POST['category'] : 'couple';
        $text = trim($_POST['text'] ?? '');
        $extra = $gc['extra'] ? trim($_POST['extra'] ?? '') : null;
        if ($text === '') { $flash = 'Please fill the main field.'; $flash_type = 'err'; }
        else {
            $pdo->prepare("INSERT INTO game_questions (game, type, category, text, extra) VALUES (?,?,?,?,?)")
                ->execute([$game, $type, $cat, $text, $extra]);
            $flash = 'Added.';
        }
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE game_questions SET enabled = 1 - enabled WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        $flash = 'Updated.';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM game_questions WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        $flash = 'Deleted.';
    } elseif ($action === 'bulk') {
        // One item per line. For 2-field games use "A | B". T&D: "truth|cat|text".
        $lines = preg_split('/\r\n|\r|\n/', $_POST['bulk'] ?? '');
        $ins = $pdo->prepare("INSERT INTO game_questions (game, type, category, text, extra) VALUES (?,?,?,?,?)");
        $n = 0;
        foreach ($lines as $ln) {
            $ln = trim($ln); if ($ln === '') continue;
            $type = 'truth'; $cat = 'couple'; $text = $ln; $extra = null;
            $parts = array_map('trim', explode('|', $ln));
            if ($game === 'truth_or_dare' && count($parts) >= 3) {
                $type = $parts[0]==='dare'?'dare':'truth';
                $cat = in_array($parts[1],$categories,true)?$parts[1]:'couple';
                $text = $parts[2];
            } elseif ($gc['extra'] && count($parts) >= 2) {
                $text = $parts[0]; $extra = $parts[1];
            }
            if ($text !== '') { $ins->execute([$game, $type, $cat, $text, $extra]); $n++; }
        }
        $flash = "$n added.";
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { header('Location: ?g=' . urlencode($game) . '&msg=' . urlencode($flash)); exit; }
}
if (isset($_GET['msg'])) $flash = $_GET['msg'];

$rows = $pdo->prepare("SELECT * FROM game_questions WHERE game=? ORDER BY id DESC LIMIT 500");
$rows->execute([$fGame]); $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
$counts = [];
foreach ($games as $slug => $g) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM game_questions WHERE game=?"); $q->execute([$slug]);
    $counts[$slug] = (int)$q->fetchColumn();
}

require __DIR__ . '/_dark_head.php';
?>
<?php if ($flash): ?><div class="panel" style="border-color:#22c55e;margin-bottom:14px"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<!-- Game selector -->
<div class="panel" style="margin-bottom:14px">
  <h3>Choose game</h3>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
    <?php foreach ($games as $slug => $g): ?>
      <a class="btn <?= $fGame===$slug?'pk':'' ?>" href="?g=<?= $slug ?>"><?= $g[0] ?> <?= ss_admin_h($g[1]) ?> (<?= $counts[$slug] ?>)</a>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid g2">
  <!-- Add one -->
  <div class="panel">
    <h3><?= $games[$fGame][0] ?> Add to <?= ss_admin_h($games[$fGame][1]) ?></h3>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="game" value="<?= $fGame ?>">
      <?php if ($cfg['type']): ?>
        <label style="font-size:.8rem;color:var(--mut)">Type</label>
        <select class="sel" style="width:100%;margin:6px 0 12px" name="type">
          <option value="truth">Truth 💬</option><option value="dare">Dare 🔥</option>
        </select>
      <?php endif; ?>
      <?php if ($cfg['cat']): ?>
        <label style="font-size:.8rem;color:var(--mut)">Category</label>
        <select class="sel" style="width:100%;margin:6px 0 12px" name="category">
          <?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?= ucfirst($c) ?></option><?php endforeach; ?>
        </select>
      <?php endif; ?>
      <label style="font-size:.8rem;color:var(--mut)"><?= ss_admin_h($cfg['text']) ?></label>
      <textarea class="sel" style="width:100%;margin:6px 0 12px;min-height:60px" name="text" placeholder="<?= ss_admin_h($cfg['text']) ?>"></textarea>
      <?php if ($cfg['extra']): ?>
        <label style="font-size:.8rem;color:var(--mut)"><?= ss_admin_h($cfg['extra']) ?></label>
        <input class="sel" style="width:100%;margin:6px 0 12px" name="extra" placeholder="<?= ss_admin_h($cfg['extra']) ?>">
      <?php endif; ?>
      <button class="btn pk" type="submit">➕ Add</button>
    </form>
  </div>

  <!-- Bulk -->
  <div class="panel">
    <h3>📥 Bulk import</h3>
    <div style="font-size:.75rem;color:var(--mut);margin-bottom:8px">
      One per line.
      <?php if ($fGame==='truth_or_dare'): ?> Format: <code>truth|romantic|text</code> (or just text).
      <?php elseif ($cfg['extra']): ?> Two fields: <code>Option A | Option B</code>.
      <?php else: ?> Just the text, one per line.<?php endif; ?>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="bulk">
      <input type="hidden" name="game" value="<?= $fGame ?>">
      <textarea class="sel" style="width:100%;margin:6px 0 12px;min-height:150px;font-family:monospace;font-size:.78rem" name="bulk"></textarea>
      <button class="btn pk" type="submit">📥 Import</button>
    </form>
  </div>
</div>

<div class="panel" style="margin-top:14px">
  <h3>📋 <?= ss_admin_h($games[$fGame][1]) ?> (<?= count($rows) ?>)</h3>
  <?php if (!$rows): ?>
    <div style="font-size:.85rem;color:var(--mut)">Nothing yet. Add above.</div>
  <?php else: foreach ($rows as $r): ?>
    <div style="display:flex;gap:10px;align-items:center;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.07)">
      <?php if ($cfg['type']): ?><span style="font-size:1.1rem"><?= $r['type']==='dare'?'🔥':'💬' ?></span><?php endif; ?>
      <div style="flex:1;min-width:0">
        <div style="font-size:.86rem;color:var(--txt)"><?= ss_admin_h($r['text']) ?><?= $r['extra'] ? ' <span style="color:#94a3b8">→ '.ss_admin_h($r['extra']).'</span>' : '' ?></div>
        <div style="font-size:.7rem;color:var(--mut)"><?= ss_admin_h($r['category']) ?> · <?= $r['enabled']?'🟢 On':'⚪ Off' ?></div>
      </div>
      <form method="post" style="margin:0"><input type="hidden" name="action" value="toggle"><input type="hidden" name="game" value="<?= $fGame ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn" type="submit"><?= $r['enabled']?'Off':'On' ?></button></form>
      <form method="post" style="margin:0" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="game" value="<?= $fGame ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn" type="submit" style="border-color:#ef4444;color:#ef4444">🗑</button></form>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/_dark_foot.php';
