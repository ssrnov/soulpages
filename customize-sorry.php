<?php
// =========================================================================
// customize-sorry.php — Editor for the "Sorry" premium template.
// Mirrors the other premium customizers: demo, admin-demo, stepped wizard,
// preview, media uploads, music library, 5-credit publish.
// =========================================================================
require_once 'includes/functions.php';
require_once __DIR__ . '/templates/sorry_cinematic.php';

$demo_key = 'sorry_cinematic';
$demo_setting_key = 'premium_demo_' . $demo_key;

// Public live demo (no login)
if (isset($_GET['demo'])) {
    $demo_cfg = json_decode(get_setting($demo_setting_key, ''), true);
    if (!is_array($demo_cfg) || empty($demo_cfg)) $demo_cfg = sorry_cinematic_defaults();
    render_sorry_cinematic([
        'id' => 0, 'user_id' => null,
        'sender_name' => $demo_cfg['_sender'] ?? get_example('sender'),
        'receiver_name' => $demo_cfg['_receiver'] ?? get_example('receiver'),
        'slide_data' => json_encode($demo_cfg),
        'template' => 'sorry_cinematic', 'status' => 'published',
        'password' => null, 'expiry_date' => null, 'is_expired' => 0, 'guest_session_id' => null,
    ]);
    exit;
}

$admin_demo = trim($_GET['admin_demo'] ?? ($_POST['admin_demo'] ?? ''));
$is_admin_demo = ($admin_demo !== '');

if (!is_logged_in()) {
    $_SESSION['login_error'] = 'Please log in to create your page.';
    redirect('login.php');
}
$user_id = $_SESSION['user_id'];
$user = get_user_profile($user_id);
if ($is_admin_demo && !is_admin()) redirect('dashboard.php');
if (!$is_admin_demo && $user && isset($user['email_verified']) && (int)$user['email_verified'] === 0) redirect('verification-pending.php');

$defaults = sorry_cinematic_defaults();
$error = ''; $editing = false; $page = null;

$slug = trim($_GET['slug'] ?? ($_POST['edit_slug'] ?? ''));
if (!$is_admin_demo && !empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND template = 'sorry_cinematic'");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    if ($page && ($page['user_id'] == $user_id || is_admin())) $editing = true;
    else $page = null;
}

if (!$editing && !$is_admin_demo && !can_create_page($user_id, PREMIUM_CREDIT_COST)) redirect('payment.php');

if ($is_admin_demo) $cfg = sorry_cinematic_config(['slide_data' => get_setting($demo_setting_key, '')]);
else $cfg = $page ? sorry_cinematic_config($page) : $defaults;

function cbf($cfg, $sec, $key, $def = '') { return $cfg[$sec][$key] ?? $def; }

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(300); @ignore_user_abort(true);
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $error = 'Uploaded files are too large for the server limit (' . ini_get('post_max_size') . ').';
    } elseif (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Please submit again.';
    } else {
        $t = function ($k, $d = '') { return trim($_POST[$k] ?? $d); };
        $chk = function ($k) { return isset($_POST[$k]); };
        $config = $defaults;

        $config['colors'] = ['accent' => $t('color_accent', '#f43f5e') ?: '#f43f5e', 'deep' => $t('color_deep', '#0a0d14') ?: '#0a0d14'];
        $config['page_title'] = $t('page_title', $defaults['page_title']);
        $config['loading_text'] = $t('loading_text', $defaults['loading_text']);

        $config['hurt']  = ['enabled' => $chk('hurt_enabled'),  'lines' => $t('hurt_lines')  ?: $defaults['hurt']['lines']];
        $config['heart'] = ['enabled' => $chk('heart_enabled'), 'lines' => $t('heart_lines') ?: $defaults['heart']['lines']];

        $ex_mem = json_decode($_POST['existing_memories'] ?? '[]', true);
        if (!is_array($ex_mem) || isset($_POST['memories_clear'])) $ex_mem = [];
        $config['memories'] = ['enabled' => $chk('memories_enabled'), 'caption' => $t('memories_caption') ?: $defaults['memories']['caption'], 'images' => $ex_mem];

        $config['voice'] = ['enabled' => $chk('voice_enabled'), 'subtitle' => $t('voice_subtitle') ?: $defaults['voice']['subtitle'], 'quote' => $t('voice_quote') ?: $defaults['voice']['quote'], 'audio_url' => $t('existing_voice_audio')];
        $config['apology'] = ['enabled' => $chk('apology_enabled'), 'text' => $t('apology_text') ?: $defaults['apology']['text']];
        $config['reply'] = ['enabled' => $chk('reply_enabled'), 'question' => $t('reply_question') ?: 'Can you forgive me?', 'yes' => $t('reply_yes') ?: '❤️ Yes', 'more' => $t('reply_more') ?: '💔 I Need More Time', 'yes_msg' => $t('reply_yes_msg') ?: $defaults['reply']['yes_msg']];
        $config['final'] = ['enabled' => $chk('final_enabled'), 'text' => $t('final_text') ?: $defaults['final']['text']];
        $config['music'] = ['title' => $t('music_title', 'Soft Piano'), 'audio_url' => $t('existing_music_audio')];
        $lib_music = trim($_POST['music_library_url'] ?? '');
        if ($lib_music !== '' && strpos($lib_music, 'assets/music/') === 0 && strpos($lib_music, '..') === false) $config['music']['audio_url'] = $lib_music;

        $receiver = $t('receiver_name', 'You') ?: 'You';
        $sender   = $t('sender_name', 'Me') ?: 'Me';
        $title    = $t('page_title') ?: ('For ' . $receiver . " — I'm Sorry 💔");
        $letter_text = trim(strip_tags($config['apology']['text'])) ?: 'I am sorry.';
        $page_password = $t('page_password');

        // Preview without saving
        if (($_POST['do'] ?? '') === 'preview') {
            render_sorry_cinematic(['id' => $editing ? ($page['id'] ?? 0) : 0, 'user_id' => $user_id, 'sender_name' => $sender, 'receiver_name' => $receiver, 'slide_data' => json_encode($config, JSON_UNESCAPED_UNICODE), 'template' => 'sorry_cinematic', 'guest_session_id' => session_id(), 'expiry_date' => null, 'is_expired' => 0, 'password' => null, '_preview' => true]);
            exit;
        }

        // Uploads
        $warnings = [];
        if (!empty($_FILES['voice_audio']['name']) && $_FILES['voice_audio']['error'] === UPLOAD_ERR_OK) {
            $r = upload_voice($_FILES['voice_audio'], 'uploads/voice/');
            if ($r['success']) $config['voice']['audio_url'] = $r['path']; else $warnings[] = 'Voice: ' . $r['message'];
        }
        if (!empty($_FILES['music_audio']['name']) && $_FILES['music_audio']['error'] === UPLOAD_ERR_OK) {
            $r = upload_voice($_FILES['music_audio'], 'uploads/music/', 20);
            if ($r['success']) $config['music']['audio_url'] = $r['path']; else $warnings[] = 'Music: ' . $r['message'];
        }
        if (!empty($_FILES['memories_files']['name'][0])) {
            $cnt = count($_FILES['memories_files']['name']);
            for ($i = 0; $i < $cnt && count($ex_mem) < 6; $i++) {
                if ($_FILES['memories_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $single = ['name' => $_FILES['memories_files']['name'][$i], 'type' => $_FILES['memories_files']['type'][$i], 'tmp_name' => $_FILES['memories_files']['tmp_name'][$i], 'error' => $_FILES['memories_files']['error'][$i], 'size' => $_FILES['memories_files']['size'][$i]];
                $r = upload_image($single);
                if ($r['success']) $ex_mem[] = ['path' => $r['path'], 'medium' => $r['medium'] ?? $r['path'], 'thumb' => $r['thumb'] ?? $r['path']];
                else $warnings[] = 'Photo "' . $_FILES['memories_files']['name'][$i] . '": ' . $r['message'];
            }
            $config['memories']['images'] = array_slice($ex_mem, 0, 6);
        }

        if ($is_admin_demo) { $config['_sender'] = $sender; $config['_receiver'] = $receiver; }
        $slide_data_json = json_encode($config, JSON_UNESCAPED_UNICODE);

        if (empty($error)) {
            if ($is_admin_demo) {
                set_setting($demo_setting_key, $slide_data_json);
                redirect('customize-sorry.php?admin_demo=' . urlencode($admin_demo) . '&saved=1');
            } elseif ($editing) {
                $hashed_password = $page['password'];
                if ($page_password !== '') $hashed_password = ($page_password === '__CLEAR__') ? null : password_hash($page_password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE pages SET title=?, sender_name=?, receiver_name=?, letter_text=?, slide_data=?, accent_color=?, password=?, music_url=? WHERE id=?");
                $upd->execute([$title, $sender, $receiver, $letter_text, $slide_data_json, $config['colors']['accent'], $hashed_password, $config['music']['audio_url'], $page['id']]);
                $save_slug = $page['slug'];
            } else {
                $days = (int)get_setting('default_expiry_days', 10); if ($days < 1) $days = 10;
                $expiry_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                $cost = PREMIUM_CREDIT_COST;
                if (get_user_credits($user_id) < $cost) {
                    $error = 'Premium pages need ' . $cost . ' credits (₹10 each). Please buy credits.';
                } else {
                    $save_slug = generate_slug($receiver . '-sorry', $pdo);
                    $hashed_password = ($page_password !== '' && $page_password !== '__CLEAR__') ? password_hash($page_password, PASSWORD_DEFAULT) : null;
                    $ins = $pdo->prepare("INSERT INTO pages (user_id, category, template, title, slug, sender_name, receiver_name, letter_text, slide_data, music_url, theme, accent_color, font_style, proposal_question, password, status, guest_session_id, expiry_date) VALUES (?, 'sorry', 'sorry_cinematic', ?, ?, ?, ?, ?, ?, ?, 'elegant', ?, 'Cormorant Garamond', ?, ?, 'published', ?, ?)");
                    $ins->execute([$user_id, $title, $save_slug, $sender, $receiver, $letter_text, $slide_data_json, $config['music']['audio_url'], $config['colors']['accent'], $config['reply']['question'], $hashed_password, session_id(), $expiry_date]);
                    $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?")->execute([$cost, $user_id, $cost]);
                }
            }
            if (empty($error)) { if (!empty($warnings)) $_SESSION['cb_warnings'] = $warnings; redirect('publish-success.php?slug=' . urlencode($save_slug)); }
        }
    }
    if (!empty($error) && isset($config)) $cfg = $config;
}

// Music library
$music_library = [];
try {
    $stmt_ml = $pdo->query("SELECT id, title, file_path, category FROM music_library WHERE category IN ('premium','sorry','all') AND (status = 'approved' OR status IS NULL) ORDER BY FIELD(category,'premium','sorry','all'), title");
    $music_library = $stmt_ml->fetchAll();
} catch (PDOException $e) {
    try { $music_library = $pdo->query("SELECT id, title, file_path, category FROM music_library WHERE category IN ('premium','sorry','all') ORDER BY category, title")->fetchAll(); } catch (PDOException $e2) { $music_library = []; }
}

$mem_existing = array_values($cfg['memories']['images'] ?? []);
$cust_base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
$thumbs = function ($im) use ($cust_base) { $u = is_array($im) ? ($im['thumb'] ?? $im['path'] ?? '') : $im; return $u ? ($cust_base . ltrim($u, '/')) : ''; };
$csrf = generate_csrf_token();
$is_fresh = !$editing && !$is_admin_demo && $_SERVER['REQUEST_METHOD'] !== 'POST';
function enable_toggle($name, $cfg, $sec) {
    $on = !empty($cfg[$sec]['enabled']);
    echo '<label class="tgl"><input type="checkbox" name="' . $name . '" value="1" ' . ($on ? 'checked' : '') . ' class="w-4 h-4 accent-rose-500"> Show this slide</label>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $is_admin_demo ? 'Customize Demo' : ($editing ? 'Edit' : 'Create') ?> Sorry Page 💔 - <?= h(SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#0a0d14,#1a1224); color:#fbe3e8; }
  h1,h2,h3,.heading { font-family:'Outfit',sans-serif; }
  .fld { width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(253,164,175,0.2); border-radius:12px; padding:10px 14px; color:#fff; font-size:0.9rem; outline:none; }
  .fld:focus { border-color:#f43f5e; }
  .fld::placeholder { color:rgba(253,164,175,0.35); }
  .lbl { display:block; font-size:0.72rem; text-transform:uppercase; letter-spacing:1px; color:#fda4af; margin-bottom:5px; font-weight:600; }
  .sec { background:rgba(255,255,255,0.04); border:1px solid rgba(253,164,175,0.15); border-radius:20px; padding:22px; margin-bottom:18px; }
  .sec-title { font-size:1.05rem; font-weight:700; color:#fda4af; margin-bottom:14px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .btn-rose { background:linear-gradient(135deg,#f43f5e,#be123c); color:#fff; border:none; border-radius:50px; padding:14px 40px; font-weight:600; cursor:pointer; box-shadow:0 8px 24px rgba(244,63,94,0.35); }
  .hint { font-size:0.72rem; color:rgba(253,164,175,0.5); margin-top:4px; }
  textarea.fld { resize:vertical; line-height:1.6; }
  .fld.ghost { color:rgba(253,164,175,0.4) !important; font-style:italic; }
  .cbstep { display:none; animation:stepIn .35s ease; } .cbstep.active { display:block; }
  @keyframes stepIn { from { opacity:0; transform:translateY(8px);} to { opacity:1; transform:none;} }
  .cbtabs { display:flex; gap:6px; overflow-x:auto; padding:6px; margin-bottom:18px; background:rgba(0,0,0,0.25); border:1px solid rgba(253,164,175,0.15); border-radius:16px; position:sticky; top:0; z-index:15; backdrop-filter:blur(8px); }
  .cbtab { flex:1 0 auto; white-space:nowrap; padding:9px 12px; border-radius:11px; font-size:0.78rem; font-weight:600; color:#fda4af; background:transparent; border:none; cursor:pointer; }
  .cbtab.active { background:linear-gradient(135deg,#f43f5e,#be123c); color:#fff; }
  .cbtab .num { display:inline-flex; width:18px; height:18px; border-radius:50%; background:rgba(255,255,255,0.15); align-items:center; justify-content:center; font-size:0.68rem; margin-right:5px; }
  .cbfooter { position:sticky; bottom:0; z-index:15; margin-top:8px; padding:14px; background:rgba(10,13,20,0.94); backdrop-filter:blur(10px); border-top:1px solid rgba(244,63,94,0.3); border-radius:16px 16px 0 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:center; }
  .btn-nav { background:rgba(255,255,255,0.08); border:1px solid rgba(253,164,175,0.25); color:#fda4af; border-radius:50px; padding:12px 22px; font-weight:600; cursor:pointer; }
  .btn-preview { background:rgba(255,255,255,0.06); border:1px solid rgba(253,164,175,0.35); color:#fff; border-radius:50px; padding:12px 22px; font-weight:600; cursor:pointer; }
  .tgl { display:flex; align-items:center; gap:10px; cursor:pointer; margin-left:auto; font-size:0.75rem; color:#fda4af; }
</style>
</head>
<body class="min-h-screen">

<header class="sticky top-0 z-20" style="background:rgba(10,13,20,0.92); backdrop-filter:blur(12px); border-bottom:1px solid rgba(244,63,94,0.25);">
  <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="<?= $is_admin_demo ? 'admin/premium-demos.php' : 'dashboard.php' ?>" class="text-sm text-rose-200/70 hover:text-white">← Back</a>
    <div class="heading font-extrabold text-lg" style="color:#fda4af;">💔 Sorry Page</div>
    <a href="index.php" class="text-sm text-rose-200/50 hover:text-white"><?= h(SITE_NAME) ?></a>
  </div>
</header>

<main class="max-w-3xl mx-auto px-4 py-8">
  <div class="text-center mb-8">
    <h1 class="heading text-3xl font-extrabold" style="color:#fda4af;"><?= $is_admin_demo ? '🖼️ Customize the Demo' : ($editing ? 'Edit Your Sorry Page' : 'Say Sorry, Beautifully') ?></h1>
    <p class="text-sm text-rose-200/50 mt-2"><?= $is_admin_demo ? 'This demo is shown to users in "Live Preview". It updates live when you save.' : 'An interactive emotional story — rain that turns to sunshine, a heart that rebuilds itself, butterflies, sparkles, and a playful reply. 💔' ?></p>
  </div>

  <?php if ($is_admin_demo): ?><div class="mb-6 rounded-2xl p-4 text-sm flex items-center gap-3" style="background:rgba(124,58,237,0.15); border:1px solid rgba(124,58,237,0.35); color:#e9d5ff;">🛠️ <b>Admin Demo Mode</b> — editing the public demo for "Sorry Page".<a href="customize-sorry.php?demo=1" target="_blank" class="ml-auto underline hover:text-white">View live demo ↗</a></div><?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Demo saved!</div><?php endif; ?>
  <?php if ($error): ?><div class="mb-6 bg-red-500/15 border border-red-500/30 text-red-200 rounded-xl p-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="cbForm">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <?php if ($editing): ?><input type="hidden" name="edit_slug" value="<?= h($page['slug']) ?>"><?php endif; ?>
    <?php if ($is_admin_demo): ?><input type="hidden" name="admin_demo" value="<?= h($admin_demo) ?>"><?php endif; ?>
    <input type="hidden" name="existing_voice_audio" value="<?= h($cfg['voice']['audio_url'] ?? '') ?>">
    <input type="hidden" name="existing_music_audio" value="<?= h($cfg['music']['audio_url'] ?? '') ?>">
    <input type="hidden" name="existing_memories" value="<?= h(json_encode($mem_existing)) ?>">
    <input type="hidden" name="do" id="doField" value="publish">

    <div class="cbtabs">
      <button type="button" class="cbtab active" onclick="showStep(0)"><span class="num">1</span>Basics</button>
      <button type="button" class="cbtab" onclick="showStep(1)"><span class="num">2</span>Messages</button>
      <button type="button" class="cbtab" onclick="showStep(2)"><span class="num">3</span>Photos &amp; Voice</button>
      <button type="button" class="cbtab" onclick="showStep(3)"><span class="num">4</span>Reply &amp; Music</button>
    </div>

    <!-- STEP 1 -->
    <div class="cbstep active" data-step="0">
      <div class="sec">
        <div class="sec-title">💌 Basic Info — just fill 2 names</div>
        <p class="hint mb-4">These auto-fill across the page. Everything else is pre-filled with a heartfelt example.</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Their Name (receiver)</label><input class="fld" name="receiver_name" value="<?= h($is_admin_demo ? ($cfg['_receiver'] ?? get_example('receiver')) : ($is_fresh ? '' : ($editing ? ($page['receiver_name'] ?? '') : ($_POST['receiver_name'] ?? '')))) ?>" placeholder="e.g. <?= h(get_example('receiver')) ?>"></div>
          <div><label class="lbl">Your Name (sender)</label><input class="fld" name="sender_name" value="<?= h($is_admin_demo ? ($cfg['_sender'] ?? get_example('sender')) : ($editing ? ($page['sender_name'] ?? '') : ($_POST['sender_name'] ?? ''))) ?>" placeholder="e.g. <?= h(get_example('sender')) ?>"></div>
        </div>
        <div class="mt-4"><label class="lbl">Browser Tab / Share Title</label><input class="fld" name="page_title" value="<?= h($is_fresh ? '' : ($cfg['page_title'] ?? '')) ?>" placeholder="For [receiver] — I'm Sorry 💔"><p class="hint">Leave blank to auto-fill. <code>[receiver]</code> = the name above.</p></div>
        <div class="mt-4"><label class="lbl">Password (optional — lock the page)</label><input class="fld" name="page_password" type="text" placeholder="<?= $editing && !empty($page['password']) ? 'Blank keeps current — type __CLEAR__ to remove' : 'Leave blank for no password' ?>"></div>
        <div class="grid sm:grid-cols-3 gap-4 mt-4">
          <div><label class="lbl">Accent Color</label><input type="color" name="color_accent" value="<?= h($cfg['colors']['accent'] ?? '#f43f5e') ?>" class="w-full h-10 rounded-lg bg-transparent border border-rose-500/20 cursor-pointer"></div>
          <div><label class="lbl">Background</label><input type="color" name="color_deep" value="<?= h($cfg['colors']['deep'] ?? '#0a0d14') ?>" class="w-full h-10 rounded-lg bg-transparent border border-rose-500/20 cursor-pointer"></div>
          <div><label class="lbl">Loading Text</label><input class="fld" name="loading_text" value="<?= h($cfg['loading_text'] ?? '') ?>"></div>
        </div>
      </div>
    </div>

    <!-- STEP 2 -->
    <div class="cbstep" data-step="1">
      <div class="sec">
        <div class="sec-title">🌙 Slide 1 — I Know I Hurt You <?php enable_toggle('hurt_enabled', $cfg, 'hurt'); ?></div>
        <label class="lbl">Lines (one per line — they fade in one by one)</label>
        <textarea class="fld" name="hurt_lines" rows="5"><?= h(cbf($cfg,'hurt','lines')) ?></textarea>
      </div>
      <div class="sec">
        <div class="sec-title">🥀 Slide 2 — My Heart Speaks <?php enable_toggle('heart_enabled', $cfg, 'heart'); ?></div>
        <p class="hint mb-2">A broken heart slowly joins back together on this slide.</p>
        <label class="lbl">Lines (one per line)</label>
        <textarea class="fld" name="heart_lines" rows="5"><?= h(cbf($cfg,'heart','lines')) ?></textarea>
      </div>
      <div class="sec">
        <div class="sec-title">💌 Slide 5 — The Apology <?php enable_toggle('apology_enabled', $cfg, 'apology'); ?></div>
        <p class="hint mb-2">Shown on a handwritten-style card with candlelight.</p>
        <label class="lbl">Apology text</label>
        <textarea class="fld" name="apology_text" rows="8"><?= h(cbf($cfg,'apology','text')) ?></textarea>
      </div>
    </div>

    <!-- STEP 3 -->
    <div class="cbstep" data-step="2">
      <div class="sec">
        <div class="sec-title">📸 Slide 3 — Our Memories <?php enable_toggle('memories_enabled', $cfg, 'memories'); ?></div>
        <p class="hint mb-3">3–6 photos, slow-zoom slideshow with floating hearts. Only shows when photos are added.</p>
        <label class="lbl">Caption</label>
        <textarea class="fld" name="memories_caption" rows="2"><?= h(cbf($cfg,'memories','caption')) ?></textarea>
        <?php if ($mem_existing): ?>
        <div class="mt-3"><label class="lbl">Current (<?= count($mem_existing) ?>/6)</label>
          <div class="grid grid-cols-6 gap-2"><?php foreach ($mem_existing as $im): ?><div class="aspect-square rounded-lg overflow-hidden border border-rose-500/20"><img src="<?= h($thumbs($im)) ?>" class="w-full h-full object-cover"></div><?php endforeach; ?></div>
          <label class="flex items-center gap-2 mt-2 text-xs text-red-300"><input type="checkbox" name="memories_clear" value="1" class="accent-red-500"> Remove all &amp; replace</label>
        </div>
        <?php endif; ?>
        <div class="mt-4"><label class="lbl">Add photos (up to 6, each ≤10MB)</label><input class="fld" type="file" name="memories_files[]" accept="image/*" multiple></div>
      </div>
      <div class="sec">
        <div class="sec-title">🎙️ Slide 4 — Voice From The Heart <?php enable_toggle('voice_enabled', $cfg, 'voice'); ?></div>
        <div><label class="lbl">Subtitle (with voice note)</label><input class="fld" name="voice_subtitle" value="<?= h(cbf($cfg,'voice','subtitle')) ?>"></div>
        <div class="mt-4"><label class="lbl">Fallback quote (shown if no voice note)</label><textarea class="fld" name="voice_quote" rows="3"><?= h(cbf($cfg,'voice','quote')) ?></textarea></div>
        <div class="mt-4"><label class="lbl">Voice Note (mp3/m4a/aac/wav — max 10MB)</label><input class="fld" type="file" name="voice_audio" accept="audio/*"><?php if (!empty($cfg['voice']['audio_url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['voice']['audio_url'])) ?></p><?php endif; ?></div>
      </div>
    </div>

    <!-- STEP 4 -->
    <div class="cbstep" data-step="3">
      <div class="sec">
        <div class="sec-title">❤️ Slide 6 — Your Reply <?php enable_toggle('reply_enabled', $cfg, 'reply'); ?></div>
        <p class="hint mb-3">The "More Time" button is playful — it dodges, pleads ("Think again… 🥺") and finally disappears, leaving only ❤️ Yes. You'll instantly see in Dashboard → Replies which button they pressed (and how many times they tried to escape 😄), plus any message they write back.</p>
        <div><label class="lbl">Question</label><input class="fld" name="reply_question" value="<?= h(cbf($cfg,'reply','question')) ?>"></div>
        <div class="grid sm:grid-cols-2 gap-4 mt-4">
          <div><label class="lbl">"Yes" Button</label><input class="fld" name="reply_yes" value="<?= h(cbf($cfg,'reply','yes')) ?>"></div>
          <div><label class="lbl">"More Time" Button (playful — runs away)</label><input class="fld" name="reply_more" value="<?= h(cbf($cfg,'reply','more')) ?>"></div>
        </div>
        <div class="mt-4"><label class="lbl">Message after Yes</label><textarea class="fld" name="reply_yes_msg" rows="3"><?= h(cbf($cfg,'reply','yes_msg')) ?></textarea></div>
      </div>
      <div class="sec">
        <div class="sec-title">🌹 Final Screen <?php enable_toggle('final_enabled', $cfg, 'final'); ?></div>
        <label class="lbl">Closing message</label>
        <textarea class="fld" name="final_text" rows="2"><?= h(cbf($cfg,'final','text')) ?></textarea>
      </div>
      <div class="sec">
        <div class="sec-title">🎵 Background Music</div>
        <?php if (!empty($music_library)): ?>
        <?php $cur_music = $cfg['music']['audio_url'] ?? ''; ?>
        <div class="mb-4">
          <label class="lbl">Choose from the music library (recommended) 🎶</label>
          <select class="fld" name="music_library_url" id="musicLibSelect">
            <option value="">— None / upload your own —</option>
            <?php foreach ($music_library as $trk): ?>
            <option value="<?= h($trk['file_path']) ?>" <?= ($cur_music === $trk['file_path']) ? 'selected' : '' ?>><?= $trk['category'] === 'premium' ? '✨ ' : '' ?><?= h($trk['title']) ?><?= $trk['category'] !== 'premium' ? ' (' . h($trk['category']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint mt-2">Soft piano / rain ambience work beautifully here.</p>
        </div>
        <?php endif; ?>
        <div><label class="lbl">Track Title</label><input class="fld" name="music_title" value="<?= h(cbf($cfg,'music','title')) ?>"></div>
        <div class="mt-4"><label class="lbl">Or upload your own (mp3 — max 20MB)</label><input class="fld" type="file" name="music_audio" accept="audio/*"><?php if (!empty($cfg['music']['audio_url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['music']['audio_url'])) ?></p><?php endif; ?></div>
      </div>
    </div>

    <div class="cbfooter">
      <button type="button" class="btn-nav" id="btnPrev" onclick="stepMove(-1)" style="display:none;">← Back</button>
      <button type="submit" class="btn-preview" id="btnPreview" onclick="cbOpenPreviewLoader();" formtarget="cbPreviewWin" style="display:none;">👁️ Preview</button>
      <button type="button" class="btn-nav" id="btnNext" onclick="stepMove(1)">Next →</button>
      <button type="submit" class="btn-rose" id="btnPublish" onclick="document.getElementById('doField').value='publish';" style="display:none;"><?= $is_admin_demo ? '💾 Save Demo' : ($editing ? '💾 Save Changes' : '💔 Publish') ?></button>
    </div>
    <p class="hint text-center pb-6"><?= $editing ? 'Changes save instantly to your live page.' : 'Publishing uses ' . PREMIUM_CREDIT_COST . ' credits.' ?> &nbsp;·&nbsp; 👁️ Preview opens in a new tab.</p>
  </form>
</main>

<script>
let cbCurrentStep = 0; const cbTotalSteps = 4;
function showStep(n) {
  n = Math.max(0, Math.min(cbTotalSteps - 1, n)); cbCurrentStep = n;
  document.querySelectorAll('.cbstep').forEach((el, i) => el.classList.toggle('active', i === n));
  document.querySelectorAll('.cbtab').forEach((el, i) => el.classList.toggle('active', i === n));
  const last = (n === cbTotalSteps - 1);
  document.getElementById('btnPrev').style.display = (n === 0) ? 'none' : '';
  document.getElementById('btnNext').style.display = last ? 'none' : '';
  document.getElementById('btnPublish').style.display = last ? '' : 'none';
  document.getElementById('btnPreview').style.display = last ? '' : 'none';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function stepMove(d) { showStep(cbCurrentStep + d); }
document.getElementById('cbForm').addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'submit') e.preventDefault(); });

// "Generating" overlay on publish
document.getElementById('cbForm').addEventListener('submit', function () {
  if (document.getElementById('doField').value !== 'publish') return;
  const o = document.createElement('div');
  o.style.cssText = 'position:fixed;inset:0;z-index:5000;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(10,13,20,0.96);backdrop-filter:blur(8px);text-align:center;padding:20px;';
  o.innerHTML = '<div style="font-size:3.4rem;animation:genBob 1.2s ease-in-out infinite;">💔</div>'
    + '<div style="font-family:Outfit,sans-serif;font-weight:800;font-size:1.4rem;color:#fda4af;margin-top:14px;">Generating your page…</div>'
    + '<div style="color:rgba(253,164,175,0.6);font-size:0.85rem;margin-top:6px;">Uploading your photos, voice &amp; music ✨</div>'
    + '<div style="width:190px;height:5px;background:rgba(255,255,255,0.12);border-radius:4px;margin-top:20px;overflow:hidden;"><div style="height:100%;width:40%;border-radius:4px;background:linear-gradient(90deg,#f43f5e,#fb7185);animation:genSlide 1.2s ease-in-out infinite;"></div></div>'
    + '<style>@keyframes genBob{0%,100%{transform:translateY(0) rotate(-4deg);}50%{transform:translateY(-10px) rotate(4deg);}}@keyframes genSlide{0%{margin-left:-40%;}100%{margin-left:100%;}}</style>';
  document.body.appendChild(o);
  const pb = document.getElementById('btnPublish'); if (pb) pb.disabled = true;
});

<?php if ($is_fresh): ?>
(function(){
  const exclude = ['receiver_name','sender_name','page_title','page_password'];
  document.querySelectorAll('#cbForm input[type=text], #cbForm textarea').forEach(function(f){
    const nm=(f.name||'').replace('[]',''); if(exclude.indexOf(nm)!==-1) return; if(!f.value) return;
    f.dataset.demo=f.value; f.classList.add('ghost');
    f.addEventListener('focus',function(){ if(f.classList.contains('ghost')){ f.value=''; f.classList.remove('ghost'); } });
    f.addEventListener('blur',function(){ if(!f.value.trim()){ f.value=f.dataset.demo; f.classList.add('ghost'); } });
  });
})();
<?php endif; ?>
</script>
<script src="assets/js/img-compress.js"></script>
<script src="assets/js/audio-fix.js"></script>
<script src="assets/js/aac-playback-fix.js"></script>
<script>
// Opens the preview tab instantly with a loading animation; the form POST
// (target=cbPreviewWin) then replaces it with the rendered preview.
function cbOpenPreviewLoader() {
  document.getElementById('doField').value = 'preview';
  try {
    var w = window.open('', 'cbPreviewWin');
    if (w && w.document) {
      w.document.open();
      w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Preview loading…</title></head><body style="margin:0;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;background:linear-gradient(160deg,#0b0a10,#1a1020);color:#fda4af;font-family:sans-serif;text-align:center;padding:20px;">'
        + '<div style="font-size:3.2rem;animation:pvB 1.2s ease-in-out infinite;">💌</div>'
        + '<div style="font-weight:800;font-size:1.2rem;margin-top:14px;">Building your preview…</div>'
        + '<div style="font-size:.82rem;opacity:.65;margin-top:6px;">Just a few seconds ✨ Don’t close this tab.</div>'
        + '<div style="width:180px;height:5px;background:rgba(255,255,255,.12);border-radius:4px;margin-top:20px;overflow:hidden;"><div style="height:100%;width:40%;border-radius:4px;background:linear-gradient(90deg,#f43f5e,#fb7185);animation:pvS 1.2s ease-in-out infinite;"></div></div>'
        + '<style>@keyframes pvB{0%,100%{transform:scale(1) rotate(-3deg)}50%{transform:scale(1.15) rotate(3deg)}}@keyframes pvS{0%{margin-left:-40%}100%{margin-left:100%}}</style>'
        + '</body></html>');
      w.document.close();
    }
  } catch (e) { /* popup blocked: the form still opens the named target itself */ }
}
</script>
<?php render_tutorial_button('sorry'); ?>
</body>
</html>
