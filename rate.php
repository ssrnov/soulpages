<?php
// Public "Rate SoulSync" page. Ratings are saved to the SoulSync DB and shown
// to the owner in the admin panel (Reviews). The app's "Rate SoulSync" button
// opens this page.
$SS = __DIR__ . '/app';
$done = false; $err = '';
$avg = 0; $count = 0;

try {
    require_once $SS . '/includes/db.php';
    require_once $SS . '/includes/helpers.php';

    $pdo->exec("CREATE TABLE IF NOT EXISTS app_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) DEFAULT '',
        rating TINYINT NOT NULL DEFAULT 5, comment VARCHAR(600) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
        $name = trim(substr($_POST['name'] ?? '', 0, 80));
        $comment = trim(substr($_POST['comment'] ?? '', 0, 600));
        if ($rating < 1) { $err = 'Please pick a star rating.'; }
        else {
            $pdo->prepare("INSERT INTO app_reviews (name, rating, comment) VALUES (?,?,?)")
                ->execute([$name, $rating, $comment]);
            $done = true;
        }
    }
    $row = $pdo->query("SELECT COUNT(*) c, AVG(rating) a FROM app_reviews")->fetch(PDO::FETCH_ASSOC);
    $count = (int)($row['c'] ?? 0);
    $avg = $count ? round((float)$row['a'], 1) : 0;
} catch (\Throwable $e) { $err = 'Something went wrong. Please try again later.'; }
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rate SoulSync 💜</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;font-family:'Inter',system-ui,sans-serif;background:linear-gradient(135deg,#1a0b2e,#2d0f38);color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .box{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:26px;padding:30px;max-width:400px;width:100%;text-align:center}
  h1{font-size:1.5rem;margin:8px 0 2px}
  .sub{color:#c4b5e0;font-size:.9rem;margin-bottom:6px}
  .avg{color:#f5c518;font-weight:700;font-size:.85rem;margin-bottom:16px}
  .stars{display:flex;justify-content:center;gap:8px;direction:rtl;margin:14px 0 18px}
  .stars input{display:none}
  .stars label{font-size:2.4rem;color:#4b4363;cursor:pointer;transition:transform .1s}
  .stars label:hover{transform:scale(1.15)}
  .stars input:checked ~ label,.stars label:hover,.stars label:hover ~ label{color:#f5c518}
  input[type=text],textarea{width:100%;margin:7px 0;padding:13px;border-radius:12px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:#fff;font-size:.95rem}
  textarea{min-height:80px;resize:vertical}
  ::placeholder{color:#9b90bd}
  .btn{display:block;width:100%;margin-top:14px;background:linear-gradient(135deg,#a855f7,#ec4899);color:#fff;border:none;font-weight:800;font-size:1.05rem;padding:15px;border-radius:14px;cursor:pointer}
  .thanks{font-size:3rem}
  a{color:#ec4899;text-decoration:none;font-size:.85rem;display:inline-block;margin-top:16px}
  .err{color:#f8b4b4;font-size:.85rem;margin-bottom:8px}
</style></head>
<body>
  <div class="box">
    <?php if ($done): ?>
      <div class="thanks">💜🎉</div>
      <h1>Thank you!</h1>
      <p class="sub">Your rating means the world to us.<br>Keep syncing hearts! 💗</p>
      <a href="soulsync.php">← Back to SoulSync</a>
    <?php else: ?>
      <div style="font-size:2.6rem">💜</div>
      <h1>Rate SoulSync</h1>
      <p class="sub">How is your SoulSync experience?</p>
      <?php if ($count): ?><div class="avg">★ <?= $avg ?> / 5 &nbsp;·&nbsp; <?= $count ?> ratings</div><?php endif; ?>
      <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <form method="post">
        <div class="stars">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" id="s<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i===5?'checked':'' ?>>
            <label for="s<?= $i ?>">★</label>
          <?php endfor; ?>
        </div>
        <input type="text" name="name" placeholder="Your name (optional)" maxlength="80">
        <textarea name="comment" placeholder="Tell us what you love or what to improve… (optional)" maxlength="600"></textarea>
        <button class="btn" type="submit">Submit Rating 💜</button>
      </form>
      <a href="soulsync.php">← Back to SoulSync</a>
    <?php endif; ?>
  </div>
</body></html>
