<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Couple Quiz';
$flash = '';

// Ensure tables exist (db.php normally creates them).
try { $pdo->query("SELECT 1 FROM quiz_bank LIMIT 1"); }
catch (\Throwable $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_bank (id INT AUTO_INCREMENT PRIMARY KEY, category VARCHAR(30) DEFAULT 'random', difficulty VARCHAR(10) DEFAULT 'easy', type VARCHAR(20) DEFAULT 'choice', question VARCHAR(255) NOT NULL, options TEXT NULL, is_custom TINYINT DEFAULT 0, couple_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $ex) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        $q = trim($_POST['question'] ?? '');
        $opts = array_values(array_filter(array_map('trim', explode("\n", $_POST['options'] ?? ''))));
        $cat = trim($_POST['category'] ?? 'random');
        $diff = trim($_POST['difficulty'] ?? 'easy');
        $type = trim($_POST['type'] ?? 'choice');
        if ($q === '' || count($opts) < 2) { $flash = 'Add a question and at least 2 options (one per line).'; }
        else {
            $pdo->prepare("INSERT INTO quiz_bank (category,difficulty,type,question,options,is_custom) VALUES (?,?,?,?,?,0)")
                ->execute([$cat, $diff, $type, $q, json_encode(array_slice($opts, 0, 4))]);
            $flash = 'Question added.';
        }
    } elseif ($act === 'delete') {
        $pdo->prepare("DELETE FROM quiz_bank WHERE id=? AND is_custom=0")->execute([(int)($_POST['id'] ?? 0)]);
        $flash = 'Question deleted.';
    }
}

// ── Stats ──
$today   = (int)$pdo->query("SELECT COUNT(*) FROM quiz_sessions WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$active  = (int)$pdo->query("SELECT COUNT(*) FROM quiz_sessions WHERE status='active'")->fetchColumn();
$answers = (int)$pdo->query("SELECT COUNT(*) FROM quiz_answers")->fetchColumn();
$pairs   = (int)$pdo->query("SELECT COUNT(*) FROM quiz_answers a JOIN quiz_answers b ON a.session_id=b.session_id AND a.q_index=b.q_index AND a.user_id<b.user_id")->fetchColumn();
$matches = (int)$pdo->query("SELECT COUNT(*) FROM quiz_answers a JOIN quiz_answers b ON a.session_id=b.session_id AND a.q_index=b.q_index AND a.user_id<b.user_id WHERE LOWER(TRIM(a.answer))=LOWER(TRIM(b.answer))")->fetchColumn();
$avgScore = $pairs > 0 ? round($matches / $pairs * 100) : 0;
$topCat = $pdo->query("SELECT category, COUNT(*) c FROM quiz_sessions GROUP BY category ORDER BY c DESC LIMIT 1")->fetch();
$questions = $pdo->query("SELECT * FROM quiz_bank WHERE is_custom=0 ORDER BY category, id DESC")->fetchAll();

require __DIR__ . '/_head.php';
?>
<?php if ($flash): ?><div class="flash ok"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<div class="cards">
  <div class="card"><div class="k">Games Today</div><div class="v"><?= $today ?></div></div>
  <div class="card"><div class="k">Active Now</div><div class="v"><?= $active ?></div></div>
  <div class="card"><div class="k">Avg Match Score</div><div class="v"><?= $avgScore ?>%</div></div>
  <div class="card"><div class="k">Answers Given</div><div class="v"><?= $answers ?></div></div>
  <div class="card"><div class="k">Top Category</div><div class="v" style="font-size:1.2rem"><?= ss_admin_h($topCat['category'] ?? '—') ?></div></div>
  <div class="card"><div class="k">Questions</div><div class="v"><?= count($questions) ?></div></div>
</div>

<div class="panel">
  <h2>Add a Question</h2>
  <form method="post" style="padding:16px">
    <input type="hidden" name="action" value="add">
    <label>Question</label>
    <input name="question" placeholder="What's my favorite food?" required>
    <label>Options (one per line, 2–4)</label>
    <textarea name="options" rows="4" placeholder="Pizza&#10;Burger&#10;Biryani&#10;Pasta" required></textarea>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px">
      <div><label>Category</label>
        <select name="category">
          <?php foreach (['love','romantic','food','travel','habits','movies','music','dreams','future','friendship','funny','deep','random'] as $c): ?>
          <option value="<?= $c ?>"><?= ucfirst($c) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Difficulty</label>
        <select name="difficulty"><option>easy</option><option>medium</option><option>hard</option></select></div>
      <div><label>Type</label>
        <select name="type"><option value="choice">Multiple Choice</option><option value="thisorthat">This or That</option><option value="yesno">Yes / No</option><option value="emoji">Emoji</option><option value="pick">Pick One</option></select></div>
    </div>
    <button class="btn primary" style="margin-top:14px" type="submit">Add Question</button>
  </form>
</div>

<div class="panel">
  <h2>Question Bank (<?= count($questions) ?>)</h2>
  <table>
    <thead><tr><th>Category</th><th>Question</th><th>Options</th><th>Diff</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($questions as $q): $opts = json_decode($q['options'] ?? '[]', true) ?: []; ?>
      <tr>
        <td><span class="pill"><?= ss_admin_h($q['category']) ?></span></td>
        <td><?= ss_admin_h($q['question']) ?></td>
        <td style="font-size:.78rem;color:#64748b"><?= ss_admin_h(implode(' · ', $opts)) ?></td>
        <td><?= ss_admin_h($q['difficulty']) ?></td>
        <td><form method="post" onsubmit="return confirm('Delete this question?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button class="btn danger" type="submit">Delete</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
