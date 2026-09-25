<?php
// Public landing page for the SoulSync app. Content is editable from the
// SoulSync admin panel (Landing Page). Reads from the SoulSync database.
$SS = __DIR__;
$headline = 'Distance Love, Close Hearts';
$subtitle = "SoulSync keeps couples close — private chat, shared moods, memories, games, and gentle ways to feel connected all day.";
$dlText   = 'Download for Android';
$dlUrl    = 'download.php';
$featuresRaw = "💬 | Private Chat | Just you two. Real-time messages with read receipts.\n💜 | Love Buzz | One tap sends a warm buzz to your partner's phone.\n😊 | Moods & Notes | Share how you feel and leave sweet notes.\n📸 | Memories | Save your favourite photos, videos and moments.\n🎮 | Couple Games | Truth or Dare, quizzes and more.\n🔋 | Know Your Partner | Battery, screen time & location (when you both share).\n📌 | Home Widget | Your partner's note, right on your home screen.\n🔥 | Streaks | Keep your daily connection streak going.";
$comingRaw   = "📞 | Voice & Video Calls | Call your partner inside SoulSync.\n🎤 | Voice Notes | Send quick voice messages in chat.\n🗺️ | Shared Places | Mark and revisit the places you love.";

// Pull admin-edited content from the SoulSync DB (best effort).
try {
    require_once $SS . '/includes/db.php';
    require_once $SS . '/includes/helpers.php';
    $headline = ss_setting($pdo, 'landing_headline', $headline) ?: $headline;
    $subtitle = ss_setting($pdo, 'landing_subtitle', $subtitle) ?: $subtitle;
    $dlText   = ss_setting($pdo, 'landing_download_text', $dlText) ?: $dlText;
    $du       = ss_setting($pdo, 'download_url', '');
    if ($du) $dlUrl = $du;
    $f = ss_setting($pdo, 'landing_features', ''); if ($f !== '' && $f !== null) $featuresRaw = $f;
    $c = ss_setting($pdo, 'landing_coming', '');   if ($c !== null) $comingRaw = $c;
    $ss_ads_on   = (int)ss_setting($pdo, 'ads_enabled', '0') === 1
                   && (int)ss_setting($pdo, 'ad_web_enabled', '0') === 1
                   && (int)ss_setting($pdo, 'ad_web_soulsync', '0') === 1;
    $ss_ads_code = ss_setting($pdo, 'adsterra_web_code', '');
} catch (\Throwable $e) { /* fall back to defaults */ }
$ss_ads_on   = $ss_ads_on ?? false;
$ss_ads_code = $ss_ads_code ?? '';

function ss_parse_items($raw) {
    $out = [];
    foreach (explode("\n", (string)$raw) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $p = array_map('trim', explode('|', $line));
        $out[] = ['emoji' => $p[0] ?? '💜', 'title' => $p[1] ?? '', 'desc' => $p[2] ?? ''];
    }
    return $out;
}
function eh($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$features = ss_parse_items($featuresRaw);
$coming   = ss_parse_items($comingRaw);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= eh($headline) ?> — SoulSync 💜</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  html{background:#0b0716}
  body{font-family:'Inter',system-ui,-apple-system,Segoe UI,sans-serif;background:#0b0716;color:#f4f0ff;line-height:1.6}
  /* keep the dark base as the bottom background layer so text stays readable */
  .bg{background:radial-gradient(1200px 600px at 50% -10%,rgba(168,85,247,.35),transparent),radial-gradient(900px 500px at 90% 10%,rgba(236,72,153,.25),transparent),#0b0716;min-height:100vh;}
  .wrap{max-width:1080px;margin:0 auto;padding:0 20px}
  header{padding:22px 0;display:flex;align-items:center;justify-content:space-between}
  .logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:1.3rem}
  .logo .l{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#a855f7,#ec4899);display:flex;align-items:center;justify-content:center;font-size:1.2rem}
  .cta{display:inline-block;background:linear-gradient(135deg,#a855f7,#ec4899);color:#fff;font-weight:700;padding:13px 26px;border-radius:50px;text-decoration:none;box-shadow:0 12px 30px rgba(236,72,153,.35);transition:transform .15s}
  .cta:hover{transform:translateY(-2px)}
  .cta.sm{padding:10px 20px;font-size:.9rem}
  .hero{text-align:center;padding:60px 0 50px}
  .hero .heart{font-size:4rem;animation:beat 1.4s infinite}
  @keyframes beat{0%,100%{transform:scale(1)}25%{transform:scale(1.18)}40%{transform:scale(1)}}
  .hero h1{font-size:2.8rem;margin:16px 0 10px;letter-spacing:-1px;background:linear-gradient(135deg,#fff,#e9d5ff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
  .hero p{color:#c4b5e0;font-size:1.15rem;max-width:560px;margin:0 auto 28px}
  .badges{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:16px;color:#a99bc9;font-size:.82rem}
  .badges span{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);padding:6px 14px;border-radius:50px}
  .sec{padding:40px 0}
  .sec h2{text-align:center;font-size:1.7rem;margin-bottom:8px}
  .sec .sub{text-align:center;color:#a99bc9;margin-bottom:32px}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
  .card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:24px}
  .card .ic{font-size:1.8rem;margin-bottom:10px}
  .card h3{font-size:1.05rem;margin-bottom:6px}
  .card p{color:#b3a6d1;font-size:.9rem}
  .card.soon{opacity:.75}
  .soonbadge{display:inline-block;background:rgba(245,158,11,.15);color:#fbbf24;font-size:.68rem;font-weight:800;padding:3px 10px;border-radius:50px;margin-top:10px;letter-spacing:.5px}
  .download{text-align:center;padding:56px 20px;margin:40px 0;background:linear-gradient(135deg,rgba(168,85,247,.15),rgba(236,72,153,.15));border:1px solid rgba(255,255,255,.08);border-radius:28px}
  .download h2{font-size:1.9rem;margin-bottom:8px}
  .download p{color:#c4b5e0;margin-bottom:24px}
  .note{color:#8b7fa8;font-size:.8rem;margin-top:14px}
  footer{text-align:center;padding:40px 0;color:#8b7fa8;font-size:.85rem}
  @media(max-width:640px){.hero h1{font-size:2rem}}
</style>
</head>
<body class="bg">
<div class="wrap">
  <header>
    <div class="logo"><span class="l">💜</span> SoulSync</div>
    <a class="cta sm" href="<?= eh($dlUrl) ?>">⬇ Download</a>
  </header>

  <section class="hero">
    <div class="heart">💜</div>
    <h1><?= eh($headline) ?></h1>
    <p><?= eh($subtitle) ?></p>
    <a class="cta" href="<?= eh($dlUrl) ?>">⬇ <?= eh($dlText) ?></a>
    <div class="badges"><span>📱 Android</span><span>🔒 Private</span><span>💜 Made for two</span></div>
  </section>

  <?php if ($ss_ads_on && trim($ss_ads_code) !== ''): ?>
  <div style="text-align:center;margin:20px auto;min-height:60px"><?= $ss_ads_code /* admin-provided Adsterra code */ ?></div>
  <?php endif; ?>

  <?php if ($features): ?>
  <section class="sec">
    <h2>What you can do</h2>
    <div class="sub">Everything a couple needs, in one calm little app.</div>
    <div class="grid">
      <?php foreach ($features as $f): ?>
        <div class="card"><div class="ic"><?= eh($f['emoji']) ?></div><h3><?= eh($f['title']) ?></h3><p><?= eh($f['desc']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($coming): ?>
  <section class="sec">
    <h2>Coming soon</h2>
    <div class="sub">We're building these next 💫</div>
    <div class="grid">
      <?php foreach ($coming as $f): ?>
        <div class="card soon"><div class="ic"><?= eh($f['emoji']) ?></div><h3><?= eh($f['title']) ?></h3><p><?= eh($f['desc']) ?></p><span class="soonbadge">COMING SOON</span></div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="download">
    <h2>Ready to sync your souls? 💜</h2>
    <p>Download SoulSync and connect with your partner in minutes.</p>
    <a class="cta" href="<?= eh($dlUrl) ?>">⬇ <?= eh($dlText) ?></a>
    <div class="note">After downloading, allow "install from unknown sources" to install the app.</div>
  </section>

  <footer>© <?= date('Y') ?> SoulSync · Made with 💜 for couples · <a href="../index.php" style="color:#a99bc9">Home</a></footer>
</div>
</body>
</html>
