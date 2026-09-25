<?php
// =========================================================================
// f.php — 30-second Festival Experience
//   f.php?fest=independence → story: loading → 3 slides → create form (live preview)
//   f.php?s=<slug> (or /i/<slug>) → view: loading → 3 slides → personalised wish page
//   POST → instant generation → share screen
// =========================================================================
require_once 'includes/functions.php';

$mode = 'story';
$fest = null;
$page = null;

$view_slug = trim($_GET['s'] ?? '');
if ($view_slug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND template = 'festival'");
    $stmt->execute([$view_slug]);
    $page = $stmt->fetch();
    if ($page) {
        $fest = get_festival($page['category']);
        $mode = isset($_GET['created']) ? 'share' : 'view';
    }
}

if (!$fest) {
    $fest_slug = trim($_GET['fest'] ?? ($_POST['fest'] ?? ''));
    $fest = get_festival($fest_slug);
    if (!$fest) redirect('festival.php');
    if (get_festival_status($fest['slug']) !== 'enabled') redirect('festival.php');
}

// ── Instant page generation ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fest'])) {
    $sender = trim($_POST['sender_name'] ?? '');
    $receiver = trim($_POST['receiver_name'] ?? '');
    if ($sender === '') $sender = 'A friend';
    if (mb_strlen($sender) > 60) $sender = mb_substr($sender, 0, 60);
    if (mb_strlen($receiver) > 60) $receiver = mb_substr($receiver, 0, 60);

    if (is_rate_limited('festival_create', 5, 3600)) {
        redirect('f.php?fest=' . urlencode($fest['slug']));
    }

    // Short pretty slug: shravni-X82KD
    $base_name = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $receiver !== '' ? $receiver : $sender));
    $base_name = trim($base_name, '-') ?: $fest['slug'];
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 5; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        $slug = $base_name . '-' . $code;
        $chk = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE slug = ?");
        $chk->execute([$slug]);
    } while ($chk->fetchColumn() > 0);

    $title = $fest['wish'] . ($receiver !== '' ? ', ' . $receiver : '') . '!';
    // Festival pages are free & viral — they live for 1 day only
    $fest_days = max(1, (int)get_setting('festival_expiry_days', 1));
    $expiry = date('Y-m-d H:i:s', strtotime("+{$fest_days} days"));
    $ins = $pdo->prepare("INSERT INTO pages (user_id, category, template, title, slug, sender_name, receiver_name, letter_text, slide_data, theme, accent_color, font_style, proposal_question, status, guest_session_id, expiry_date)
        VALUES (?, ?, 'festival', ?, ?, ?, ?, ?, ?, 'romantic', ?, 'Outfit', ?, 'published', ?, ?)");
    $ins->execute([
        is_logged_in() ? $_SESSION['user_id'] : null,
        $fest['slug'], $title, $slug, $sender, $receiver !== '' ? $receiver : 'You',
        implode("\n", $fest['slides']), json_encode(['fest' => $fest['slug']]),
        $fest['accent'], $fest['wish'], session_id(), $expiry,
    ]);
    redirect('f.php?s=' . urlencode($slug) . '&created=1');
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$share_url = $page ? ($protocol . $_SERVER['HTTP_HOST'] . $dir . '/i/' . $page['slug']) : '';

// Optional festive music (admin library: exact festival first, then generic 'festival')
$music_url = '';
try {
    $stmt_m = $pdo->prepare("SELECT file_path FROM music_library WHERE category IN (?, 'festival') ORDER BY FIELD(category, ?) DESC, id DESC LIMIT 1");
    $stmt_m->execute([$fest['slug'], $fest['slug']]);
    $music_url = (string)($stmt_m->fetchColumn() ?: '');
} catch (PDOException $e) {}

$sender_name   = $page['sender_name'] ?? '';
$receiver_name = ($page && $page['receiver_name'] !== 'You') ? $page['receiver_name'] : '';
$counter = number_format(festival_counter($fest['slug']));
$is_ind = ($fest['slug'] === 'independence');
$loading_text = $fest['loading_text'] ?? 'Preparing something beautiful…';
$gen_text = $fest['gen_text'] ?? 'Generating your page…';
$wish_body = $fest['wish_body'] ?? '';
$sign_off = $fest['sign_off'] ?? '';
$e = function ($s) { return h($s); };
$ml = function ($s) { return nl2br(h($s), false); }; // multi-line text
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($page ? $page['title'] : ($fest['name'] . ' Wishes — ' . SITE_NAME)) ?></title>
<meta property="og:title" content="<?= $e($page ? $page['title'] : ($fest['wish'] . '! ' . $fest['emoji'])) ?>">
<meta property="og:description" content="<?= $e(strtok($fest['slides'][0], "\n")) ?>">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@400;500;700&family=Satisfy&display=swap" rel="stylesheet">
<?php if ($mode === 'share') render_adsense_head_script(); ?>
<style>
  :root { --acc: <?= $e($fest['accent']) ?>; --acc2: <?= $e($fest['accent2']) ?>; }
  * { margin:0; padding:0; box-sizing:border-box; }
  html,body { width:100%; height:100%; font-family:'DM Sans',sans-serif; color:#fff; overflow:hidden; background: <?= $fest['bg'] ?>; }
  .scene { position:fixed; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px; text-align:center; opacity:0; pointer-events:none; transition:opacity .7s ease; overflow-y:auto; }
  .scene.active { opacity:1; pointer-events:all; }
  .fx { position:fixed; inset:0; pointer-events:none; overflow:hidden; z-index:1; }
  .pt { position:absolute; top:-40px; font-size:1.3rem; opacity:.55; animation:fall linear infinite; }
  @keyframes fall { to { transform:translateY(112vh) rotate(340deg); opacity:0; } }
  .em { font-size:4rem; margin-bottom:14px; animation:bob 2s ease-in-out infinite; }
  @keyframes bob { 0%,100%{ transform:translateY(0);} 50%{ transform:translateY(-12px);} }
  .wish-line { font-family:'Cormorant Garamond',serif; font-size:clamp(1.35rem,5vw,2rem); line-height:1.6; max-width:640px; color:#fff; text-shadow:0 2px 18px rgba(0,0,0,.5); opacity:0; transform:translateY(16px); transition:opacity .9s, transform .9s; position:relative; z-index:3; }
  .wish-line.show { opacity:1; transform:none; }
  .tap-hint { position:fixed; bottom:34px; left:0; right:0; text-align:center; font-size:.78rem; color:rgba(255,255,255,.55); animation:pulse 1.8s infinite; z-index:5; }
  @keyframes pulse { 0%,100%{ opacity:.4;} 50%{ opacity:1;} }
  .bigwish { font-family:'Satisfy',cursive; font-size:clamp(1.9rem,7.5vw,3rem); color:var(--acc); text-shadow:0 0 30px rgba(0,0,0,.4); }
  .name { font-family:'Cormorant Garamond',serif; font-size:clamp(1.9rem,8.5vw,3.2rem); color:#fff; margin-top:6px; }
  .from { color:rgba(255,255,255,.75); font-style:italic; margin-top:14px; font-size:1.05rem; }
  .poem { font-family:'Cormorant Garamond',serif; font-size:clamp(1.15rem,4.4vw,1.5rem); line-height:1.85; color:#fff; text-shadow:0 2px 16px rgba(0,0,0,.5); margin-top:14px; position:relative; z-index:3; }
  .card { background:rgba(0,0,0,.38); border:1px solid rgba(255,255,255,.18); backdrop-filter:blur(14px); border-radius:24px; padding:28px 24px; max-width:440px; width:100%; position:relative; z-index:3; }
  .fld { width:100%; padding:13px 16px; border-radius:12px; border:1px solid rgba(255,255,255,.25); background:rgba(255,255,255,.94); color:#1a1a1a; font-size:1rem; outline:none; margin-top:8px; }
  .lbl { display:block; text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:1.5px; color:rgba(255,255,255,.7); margin-top:14px; }
  .btn { display:inline-block; margin-top:22px; background:linear-gradient(135deg,var(--acc),var(--acc2)); color:#fff; border:none; border-radius:50px; padding:15px 36px; font-size:1rem; font-weight:700; cursor:pointer; box-shadow:0 10px 30px rgba(0,0,0,.35); transition:transform .2s; }
  .btn:hover { transform:translateY(-2px); }
  .share-btn { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; margin-top:10px; padding:13px; border-radius:14px; font-weight:700; font-size:.92rem; text-decoration:none; color:#fff; border:none; cursor:pointer; }
  .counterchip { position:fixed; top:14px; left:50%; transform:translateX(-50%); z-index:20; background:rgba(0,0,0,.45); border:1px solid rgba(255,255,255,.15); backdrop-filter:blur(8px); border-radius:999px; padding:6px 16px; font-size:.72rem; color:rgba(255,255,255,.8); white-space:nowrap; }
  #ldr { position:fixed; inset:0; z-index:3000; display:flex; flex-direction:column; align-items:center; justify-content:center; background:<?= $fest['bg'] ?>; transition:opacity .6s; }
  #ldr.hide { opacity:0; pointer-events:none; }
  .ldr-bar { width:180px; height:5px; background:rgba(255,255,255,.15); border-radius:4px; margin-top:20px; overflow:hidden; }
  .ldr-fill { height:100%; width:40%; border-radius:4px; background:linear-gradient(90deg,var(--acc),var(--acc2)); animation:slide 1.1s ease-in-out infinite; }
  @keyframes slide { 0%{ margin-left:-40%;} 100%{ margin-left:100%;} }
  .adwrap { margin-top:22px; width:100%; display:flex; justify-content:center; }
  /* live preview card in the form */
  .lp { background:linear-gradient(180deg, rgba(255,255,255,.1), rgba(255,255,255,.04)); border:1px dashed rgba(255,255,255,.35); border-radius:16px; padding:18px 14px; margin-top:14px; }
  .lp-wish { font-family:'Satisfy',cursive; font-size:1.3rem; color:var(--acc); }
  .lp-by { font-size:.68rem; text-transform:uppercase; letter-spacing:2px; color:rgba(255,255,255,.55); margin-top:8px; }
  .lp-name { font-family:'Cormorant Garamond',serif; font-size:1.5rem; color:#fff; margin-top:2px; }
  .lp-sign { font-size:.8rem; color:rgba(255,255,255,.6); margin-top:6px; }

<?php if ($is_ind): ?>
  /* ═══ INDEPENDENCE DAY BESPOKE SCENES ═══ */
  /* waving tricolour flag */
  .flag { width:150px; height:100px; position:relative; margin:0 auto 8px; transform-origin:left center; animation:wave 2.6s ease-in-out infinite; border-radius:4px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.45); }
  .flag::before { content:''; position:absolute; inset:0; background:linear-gradient(180deg,#ff9933 33.4%, #ffffff 33.4% 66.7%, #138808 66.7%); }
  .flag .chk { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:26px; height:26px; border-radius:50%; border:2.5px solid #000080; }
  .flag .chk::before { content:''; position:absolute; inset:2px; border-radius:50%; background:repeating-conic-gradient(#000080 0 3deg, transparent 3deg 15deg); }
  @keyframes wave { 0%,100%{ transform:rotate(0) skewY(0deg);} 25%{ transform:skewY(2.4deg);} 75%{ transform:skewY(-2.4deg);} }
  .flagpole { width:4px; height:150px; background:linear-gradient(#cbd5e1,#64748b); margin:0 auto; border-radius:2px; margin-top:-6px; }
  /* flying birds */
  .bird { position:fixed; font-size:1.2rem; z-index:2; animation:fly linear infinite; opacity:.85; }
  @keyframes fly { 0%{ transform:translateX(-8vw) translateY(0);} 50%{ transform:translateX(50vw) translateY(-30px);} 100%{ transform:translateX(110vw) translateY(0);} }
  /* red fort skyline (slide 1) */
  .fort { position:absolute; bottom:0; left:0; right:0; height:130px; z-index:2; pointer-events:none;
    background:
      radial-gradient(60px 26px at 12% 100%, #3b1208 98%, transparent),
      radial-gradient(60px 26px at 50% 100%, #3b1208 98%, transparent),
      radial-gradient(60px 26px at 88% 100%, #3b1208 98%, transparent),
      linear-gradient(transparent 40px, #2a0d06 40px);
  }
  .fort::before { content:''; position:absolute; bottom:88px; left:0; right:0; height:18px;
    background:repeating-linear-gradient(90deg, #2a0d06 0 22px, transparent 22px 34px); }
  .sunrise { position:absolute; bottom:60px; left:50%; transform:translateX(-50%); width:340px; height:340px; border-radius:50%; background:radial-gradient(circle, rgba(255,153,51,.5), rgba(255,153,51,.12) 55%, transparent 70%); z-index:1; pointer-events:none; }
  #s0 { background:linear-gradient(180deg,#0a1230 0%,#3a1a3a 45%,#7a2d15 80%,#2a0d06 100%); padding-bottom:150px; }
  /* tribute (slide 2) */
  #s1 { background:linear-gradient(180deg,#050810 0%,#0a1225 55%,#111827 100%); padding-bottom:150px; }
  .mountains { position:absolute; bottom:0; left:0; right:0; height:150px; z-index:1; pointer-events:none;
    background:
      linear-gradient(115deg, transparent 46%, #0d1522 46% 54%, transparent 54%) ,
      linear-gradient(245deg, transparent 42%, #0a101c 42% 58%, transparent 58%),
      linear-gradient(transparent 90px, #070b14 90px);
  }
  .soldier { font-size:4.2rem; filter:brightness(0) drop-shadow(0 0 1px rgba(255,255,255,.25)); position:relative; z-index:3; margin-bottom:6px; }
  .flame { font-size:1.9rem; position:relative; z-index:3; animation:flick .5s infinite alternate; filter:drop-shadow(0 0 18px rgba(255,153,51,.9)); margin-bottom:10px; }
  @keyframes flick { from{ transform:scale(1) rotate(-3deg);} to{ transform:scale(1.12) rotate(3deg);} }
  /* wishes (slide 3) */
  #s2 { background:linear-gradient(180deg,#081a2e 0%,#12233a 55%,#0d3020 100%); }
  .chakra-big { width:110px; height:110px; border-radius:50%; border:4px solid #dbeafe; position:relative; margin:0 auto 14px; animation:spin 14s linear infinite; box-shadow:0 0 34px rgba(147,197,253,.4); z-index:3; }
  .chakra-big::before { content:''; position:absolute; inset:6px; border-radius:50%; background:repeating-conic-gradient(#dbeafe 0 2.5deg, transparent 2.5deg 15deg); }
  @keyframes spin { to { transform:rotate(360deg); } }
  .kids { font-size:2rem; letter-spacing:8px; margin-top:14px; position:relative; z-index:3; }
  /* final page look */
  #s3.viewmode { background:linear-gradient(180deg,#0a1230 0%,#1a1a3a 45%,#0d3020 100%); }
<?php endif; ?>
</style>
</head>
<body>

<!-- LOADING (no ads here — AdSense policy) -->
<div id="ldr">
  <?php if ($is_ind): ?>
    <div><div class="flag"><span class="chk"></span></div><div class="flagpole" style="height:40px;"></div></div>
    <div class="bird" style="top:18%; animation-duration:9s;">🕊️</div>
    <div class="bird" style="top:26%; animation-duration:12s; animation-delay:2s;">🕊️</div>
    <div class="bird" style="top:12%; animation-duration:15s; animation-delay:4s; font-size:.9rem;">🕊️</div>
  <?php else: ?>
    <div class="em"><?= $e($fest['emoji']) ?></div>
  <?php endif; ?>
  <div style="font-family:'Satisfy',cursive; font-size:1.6rem; color:var(--acc); margin-top:10px;"><?= $e($fest['name']) ?></div>
  <div style="color:rgba(255,255,255,.55); font-size:.85rem; margin-top:6px;"><?= $e($loading_text) ?></div>
  <div class="ldr-bar"><div class="ldr-fill"></div></div>
</div>

<div class="fx" id="fx"></div>
<div class="counterchip">❤️ <?= $counter ?> pages created today</div>

<!-- STORY SLIDES -->
<?php foreach ($fest['slides'] as $i => $line): ?>
<div class="scene" id="s<?= $i ?>">
  <?php if ($is_ind && $i === 0): ?>
    <div class="sunrise"></div><div class="fort"></div>
    <div style="position:relative; z-index:3;"><div class="flag"><span class="chk"></span></div><div class="flagpole" style="height:34px;"></div></div>
  <?php elseif ($is_ind && $i === 1): ?>
    <div class="mountains"></div>
    <div class="soldier">🧍</div>
    <div class="flame">🔥</div>
  <?php elseif ($is_ind && $i === 2): ?>
    <div class="chakra-big"></div>
  <?php else: ?>
    <div class="em" style="position:relative; z-index:3;"><?= $e($fest['emoji']) ?></div>
  <?php endif; ?>
  <div class="wish-line" id="w<?= $i ?>"><?= $ml($line) ?></div>
  <?php if ($is_ind && $i === 2): ?><div class="kids">🧒🇮🇳👧🇮🇳🧒</div><?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($mode === 'story'): ?>
<!-- CREATE FORM + LIVE PREVIEW -->
<div class="scene" id="s3">
  <div class="card">
    <div style="font-size:2rem;"><?= $e($fest['emoji']) ?></div>
    <div class="bigwish" style="font-size:1.5rem;">Create Your Own <?= $e($fest['name']) ?> Page</div>
    <!-- Live preview -->
    <div class="lp">
      <div class="lp-wish"><?= $e($fest['emoji']) ?> <?= $e($fest['wish']) ?></div>
      <div class="lp-by">Proudly Shared By</div>
      <div class="lp-name" id="lpName"><?= $e(get_example('fest_name')) ?> ❤️</div>
      <?php if ($sign_off): ?><div class="lp-sign"><?= $e($sign_off) ?></div><?php endif; ?>
    </div>
    <form method="post" id="genForm">
      <input type="hidden" name="fest" value="<?= $e($fest['slug']) ?>">
      <label class="lbl">Your Name</label>
      <input class="fld" name="sender_name" id="fName" required maxlength="60" placeholder="e.g. <?= $e(get_example('fest_name')) ?>" oninput="lpUpdate()">
      <label class="lbl">Dedicated To (Optional)</label>
      <input class="fld" name="receiver_name" maxlength="60" placeholder="e.g. Family / Friends / India">
      <button type="submit" class="btn" style="width:100%;"><?= $e($fest['emoji']) ?> Create My <?= $e($fest['name']) ?> Page</button>
    </form>
    <p style="color:rgba(255,255,255,.45); font-size:.72rem; margin-top:10px;">Free · unique link · ready in seconds</p>
  </div>
</div>
<?php else: ?>
<!-- PERSONALISED FINAL PAGE -->
<div class="scene <?= $is_ind ? 'viewmode' : '' ?>" id="s3">
  <?php if ($is_ind): ?><div style="position:relative; z-index:3;"><div class="flag" style="width:110px; height:74px;"><span class="chk" style="width:20px; height:20px;"></span></div></div><?php endif; ?>
  <div class="bigwish" style="margin-top:10px;"><?= $e($fest['emoji']) ?> <?= $e($fest['wish']) ?> <?= $e($fest['emoji']) ?></div>
  <?php if ($wish_body): ?><div class="poem"><?= $ml($wish_body) ?></div><?php endif; ?>
  <?php if ($receiver_name !== ''): ?><div class="from" style="margin-top:16px;">Dedicated to <b style="color:var(--acc);"><?= $e($receiver_name) ?></b></div><?php endif; ?>
  <div style="margin-top:18px; position:relative; z-index:3;">
    <div style="font-size:.7rem; text-transform:uppercase; letter-spacing:2.5px; color:rgba(255,255,255,.55);">Proudly Shared By</div>
    <div class="name" style="font-size:clamp(1.6rem,7vw,2.4rem);">❤️ <?= $e($sender_name) ?></div>
    <?php if ($sign_off): ?><div class="from" style="margin-top:8px; font-weight:700; color:var(--acc);"><?= $e($sign_off) ?></div><?php endif; ?>
  </div>
  <a href="f.php?fest=<?= $e($fest['slug']) ?>" class="btn">✨ Create Your Own <?= $e($fest['name']) ?> Page on <?= h(SITE_NAME) ?></a>
</div>
<?php endif; ?>

<?php if ($mode === 'share'): ?>
<!-- SHARE SCREEN -->
<div class="scene" id="sShare">
  <div class="card">
    <div style="font-size:2.6rem;">🎉</div>
    <div class="bigwish" style="font-size:1.6rem;">Your page is ready!</div>
    <p style="color:rgba(255,255,255,.65); font-size:.85rem; margin-top:8px; word-break:break-all;"><?= $e($share_url) ?></p>
    <?php $enc = rawurlencode($page['title'] . ' ' . $fest['emoji'] . ' ' . $share_url); ?>
    <a class="share-btn" style="background:#25D366;" href="https://wa.me/?text=<?= $enc ?>" target="_blank">🟢 Share on WhatsApp</a>
    <a class="share-btn" style="background:#229ED9;" href="https://t.me/share/url?url=<?= rawurlencode($share_url) ?>&text=<?= rawurlencode($page['title'] . ' ' . $fest['emoji']) ?>" target="_blank">✈️ Share on Telegram</a>
    <button class="share-btn" style="background:linear-gradient(45deg,#f09433,#dc2743,#bc1888);" onclick="copyLink(true)">📸 Instagram (copy link)</button>
    <button class="share-btn" style="background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3);" onclick="copyLink(false)" id="copyBtn">🔗 Copy Link</button>
    <button class="btn" style="width:100%; margin-top:14px;" onclick="startStory()">▶️ Watch Your Page</button>
    <div class="adwrap"><?php render_ad_banner('adsense_slot_festival_share'); ?></div>
  </div>
</div>
<?php endif; ?>

<div class="tap-hint" id="tapHint" style="display:none;">Tap anywhere to continue <?= $e($fest['emoji']) ?></div>
<?php if ($music_url): ?><audio id="bgm" src="<?= $e($music_url) ?>" loop preload="auto"></audio><?php endif; ?>

<script>
const MODE = <?= json_encode($mode) ?>;
const scenes = ['s0','s1','s2','s3'];
let cur = -1, started = false;

const fx = document.getElementById('fx');
const parts = <?= json_encode($fest['particles'], JSON_UNESCAPED_UNICODE) ?>;
for (let i = 0; i < 22; i++) { const p = document.createElement('div'); p.className='pt'; p.textContent = parts[i % parts.length]; p.style.left = Math.random()*100+'%'; p.style.animationDuration = (7+Math.random()*9)+'s'; p.style.animationDelay = (Math.random()*9)+'s'; p.style.fontSize = (0.9+Math.random()*1.1)+'rem'; fx.appendChild(p); }

function show(i) {
  if (cur >= 0) { const c = document.getElementById(scenes[cur]); if (c) c.classList.remove('active'); }
  else if (MODE === 'share') { const sh = document.getElementById('sShare'); if (sh) sh.classList.remove('active'); }
  cur = i;
  const n = document.getElementById(scenes[i]); if (!n) return;
  n.classList.add('active');
  const w = document.getElementById('w'+i); if (w) setTimeout(()=>w.classList.add('show'), 300);
  document.getElementById('tapHint').style.display = (i < 3) ? 'block' : 'none';
}
function nextScene(){ if (cur < 3) show(cur + 1); }
document.addEventListener('click', function (e) {
  if (!started) return;
  if (e.target.closest('form') || e.target.closest('a') || e.target.closest('button') || e.target.closest('#sShare')) return;
  nextScene();
});
function startStory(){ started = true; const sh=document.getElementById('sShare'); if(sh) sh.classList.remove('active'); cur=-1; show(0); playMusic(); }
function playMusic(){ const a=document.getElementById('bgm'); if(a){ a.volume=.6; a.play().catch(()=>{}); } }
function copyLink(insta){ const url = <?= json_encode($share_url) ?>; (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(()=>{ const b=document.getElementById('copyBtn'); if(b) b.textContent = insta ? '✅ Link copied — paste in your story!' : '✅ Copied!'; }).catch(()=>{ prompt('Copy this link:', url); }); }

// live preview name (example: Shravni ❤️)
function lpUpdate(){ const v=(document.getElementById('fName').value||'').trim(); const el=document.getElementById('lpName'); if(el) el.textContent = (v || <?= json_encode(get_example('fest_name')) ?>) + ' ❤️'; }

// generating overlay
const gf = document.getElementById('genForm');
if (gf) gf.addEventListener('submit', function () {
  const o = document.createElement('div');
  o.style.cssText = 'position:fixed;inset:0;z-index:6000;display:flex;flex-direction:column;align-items:center;justify-content:center;background:<?= $fest['bg'] ?>;text-align:center;padding:20px;';
  o.innerHTML = '<div class="em"><?= $e($fest['emoji']) ?></div><div style="font-family:Satisfy,cursive;font-size:1.6rem;color:var(--acc);"><?= $e($gen_text) ?></div><div style="color:rgba(255,255,255,.55);font-size:.85rem;margin-top:6px;">Adding your name & sprinkling magic ✨</div><div class="ldr-bar"><div class="ldr-fill"></div></div>';
  document.body.appendChild(o);
});

window.addEventListener('load', function () {
  setTimeout(function () {
    document.getElementById('ldr').classList.add('hide');
    started = true;
    if (MODE === 'share') { const sh = document.getElementById('sShare'); if (sh) { sh.classList.add('active'); cur = 4; } }
    else { show(0); }
    playMusic();
  }, 2400);
});
setTimeout(function(){ const l=document.getElementById('ldr'); if(l && !l.classList.contains('hide')){ l.classList.add('hide'); started=true; if(MODE==='share'){ const sh=document.getElementById('sShare'); if(sh) sh.classList.add('active'); } else show(0); } }, 6000);
</script>
<script src="assets/js/aac-playback-fix.js"></script>
<?php render_tutorial_button('festival'); ?>
</body>
</html>
