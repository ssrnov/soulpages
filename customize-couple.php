<?php
// =========================================================================
// customize-couple.php — Editor for the flagship "Couple Story" template.
// 6-step wizard with add-more repeaters (timeline, chats, letters, playlist,
// places, reasons, dreams, promises), 30-photo gallery, 3 videos, 4 voice
// notes, 6 themes. Demo / admin-demo / preview / 5-credit publish.
// =========================================================================
require_once 'includes/functions.php';
require_once __DIR__ . '/templates/couple_story.php';

$demo_key = 'couple_story';
$demo_setting_key = 'premium_demo_' . $demo_key;

// Public live demo (no login)
if (isset($_GET['demo'])) {
    $demo_cfg = json_decode(get_setting($demo_setting_key, ''), true);
    if (!is_array($demo_cfg) || empty($demo_cfg)) $demo_cfg = couple_story_defaults();
    render_couple_story([
        'id' => 0, 'user_id' => null,
        'sender_name' => $demo_cfg['couple']['c1'] ?? get_example('sender'),
        'receiver_name' => $demo_cfg['couple']['c2'] ?? get_example('receiver'),
        'slide_data' => json_encode($demo_cfg),
        'template' => 'couple_story', 'status' => 'published',
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

$defaults = couple_story_defaults();
$themes = couple_story_themes();
$error = ''; $editing = false; $page = null;

$slug = trim($_GET['slug'] ?? ($_POST['edit_slug'] ?? ''));
if (!$is_admin_demo && !empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND template = 'couple_story'");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    if ($page && ($page['user_id'] == $user_id || is_admin())) $editing = true;
    else $page = null;
}

if (!$editing && !$is_admin_demo && !can_create_page($user_id, PREMIUM_CREDIT_COST)) redirect('payment.php');

if ($is_admin_demo) $cfg = couple_story_config(['slide_data' => get_setting($demo_setting_key, '')]);
else $cfg = $page ? couple_story_config($page) : $defaults;

function csf($cfg, $sec, $key, $def = '') { return $cfg[$sec][$key] ?? $def; }

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(300); @ignore_user_abort(true); // photo/video publishes must survive slow shared hosting
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $error = 'Uploaded files are too large for the server limit (' . ini_get('post_max_size') . ').';
    } elseif (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Please submit again.';
    } else {
        $t = function ($k, $d = '') { return trim($_POST[$k] ?? $d); };
        $chk = function ($k) { return isset($_POST[$k]); };
        // zip parallel repeater arrays into row arrays, dropping fully empty rows
        $rows = function ($fields, $require) {
            $first = $_POST[reset($fields)] ?? [];
            $n = is_array($first) ? count($first) : 0;
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $row = [];
                foreach ($fields as $k => $nm) { $v = $_POST[$nm][$i] ?? ''; $row[$k] = trim(is_string($v) ? $v : ''); }
                if (trim($row[$require] ?? '') !== '') $out[] = $row;
            }
            return $out;
        };
        $config = $defaults;

        $config['theme'] = isset($themes[$t('theme')]) ? $t('theme') : 'dark_love';
        $config['page_title'] = $t('page_title', $defaults['page_title']);
        $config['loading_text'] = $t('loading_text', $defaults['loading_text']);
        $config['couple'] = [
            'c1' => $t('c1') ?: 'Me', 'c2' => $t('c2') ?: 'You',
            'title' => $t('couple_title') ?: $defaults['couple']['title'],
            'since' => $t('couple_since') ?: $defaults['couple']['since'],
            'status' => $t('couple_status') ?: $defaults['couple']['status'],
            'nick1' => $t('nick1'), 'nick2' => $t('nick2'),
            'first_meet' => $t('first_meet'), 'first_chat' => $t('first_chat'),
            'distance' => $t('distance'),
        ];
        $config['intro'] = ['enabled' => $chk('intro_enabled'), 'line' => $t('intro_line') ?: $defaults['intro']['line']];
        $config['about'] = ['enabled' => $chk('about_enabled'), 'heading' => $t('about_heading') ?: $defaults['about']['heading']];
        $config['counter'] = ['enabled' => $chk('counter_enabled'), 'heading' => $t('counter_heading') ?: $defaults['counter']['heading']];
        $config['timeline'] = ['enabled' => $chk('timeline_enabled'), 'heading' => $t('timeline_heading') ?: $defaults['timeline']['heading'],
            'items' => $rows(['emoji' => 'tl_emoji', 'date' => 'tl_date', 'title' => 'tl_title', 'text' => 'tl_text'], 'title')];
        $config['chats'] = ['enabled' => $chk('chats_enabled'), 'heading' => $t('chats_heading') ?: $defaults['chats']['heading'],
            'items' => $rows(['side' => 'ch_side', 'text' => 'ch_text', 'time' => 'ch_time'], 'text')];
        $config['letters'] = ['enabled' => $chk('letters_enabled'), 'heading' => $t('letters_heading') ?: $defaults['letters']['heading'],
            'items' => $rows(['title' => 'lt_title', 'text' => 'lt_text'], 'text')];
        $config['playlist'] = ['enabled' => $chk('playlist_enabled'), 'heading' => $t('playlist_heading') ?: $defaults['playlist']['heading'],
            'items' => $rows(['title' => 'pl_title', 'artist' => 'pl_artist', 'note' => 'pl_note'], 'title')];
        $config['places'] = ['enabled' => $chk('places_enabled'), 'heading' => $t('places_heading') ?: $defaults['places']['heading'],
            'items' => $rows(['emoji' => 'pc_emoji', 'name' => 'pc_name', 'note' => 'pc_note'], 'name')];
        $config['reasons'] = ['enabled' => $chk('reasons_enabled'), 'heading' => $t('reasons_heading') ?: $defaults['reasons']['heading'],
            'items' => $rows(['emoji' => 'rs_emoji', 'title' => 'rs_title', 'text' => 'rs_text'], 'title')];
        $config['dreams'] = ['enabled' => $chk('dreams_enabled'), 'heading' => $t('dreams_heading') ?: $defaults['dreams']['heading'],
            'items' => $rows(['emoji' => 'dm_emoji', 'text' => 'dm_text'], 'text')];
        $pr_rows = $rows(['text' => 'pr_text'], 'text');
        $config['promises'] = ['enabled' => $chk('promises_enabled'), 'heading' => $t('promises_heading') ?: $defaults['promises']['heading'],
            'items' => array_map(function ($r) { return $r['text']; }, $pr_rows)];
        $config['achieve'] = ['enabled' => $chk('achieve_enabled'), 'heading' => $t('achieve_heading') ?: $defaults['achieve']['heading']];
        $config['meter'] = ['enabled' => $chk('meter_enabled'), 'heading' => $t('meter_heading') ?: $defaults['meter']['heading'], 'label' => $t('meter_label') ?: $defaults['meter']['label']];
        $config['secret'] = ['enabled' => $chk('secret_enabled'), 'heading' => $defaults['secret']['heading'], 'hint' => $t('secret_hint') ?: $defaults['secret']['hint'], 'code' => $t('secret_code') ?: $defaults['secret']['code'], 'text' => $t('secret_text') ?: $defaults['secret']['text']];
        $config['guestbook'] = ['enabled' => $chk('guestbook_enabled'), 'heading' => $t('guestbook_heading') ?: $defaults['guestbook']['heading'], 'subtitle' => $t('guestbook_subtitle') ?: $defaults['guestbook']['subtitle']];
        $config['final'] = ['enabled' => $chk('final_enabled'), 'quote' => $t('final_quote') ?: $defaults['final']['quote']];

        // media: existing kept via hidden JSON, cleared via checkboxes
        $ex_gal = json_decode($_POST['existing_gallery'] ?? '[]', true);
        if (!is_array($ex_gal) || isset($_POST['gallery_clear'])) $ex_gal = [];
        $config['gallery'] = ['enabled' => $chk('gallery_enabled'), 'heading' => $t('gallery_heading') ?: $defaults['gallery']['heading'], 'images' => $ex_gal];

        $ex_vid = json_decode($_POST['existing_videos'] ?? '[]', true);
        if (!is_array($ex_vid) || isset($_POST['videos_clear'])) $ex_vid = [];
        $ex_voc = json_decode($_POST['existing_voices'] ?? '[]', true);
        if (!is_array($ex_voc) || isset($_POST['voices_clear'])) $ex_voc = [];

        $config['music'] = ['title' => $t('music_title', 'Romantic Piano'), 'audio_url' => $t('existing_music_audio')];
        $lib_music = trim($_POST['music_library_url'] ?? '');
        if ($lib_music !== '' && strpos($lib_music, 'assets/music/') === 0 && strpos($lib_music, '..') === false) $config['music']['audio_url'] = $lib_music;

        $c1 = $config['couple']['c1']; $c2 = $config['couple']['c2'];
        $title = $t('page_title') ?: ($c1 . ' ❤️ ' . $c2 . ' — Our Story');
        $page_password = $t('page_password');

        // captions/titles typed for media rows apply to existing entries too
        for ($i = 0; $i < 3; $i++) { if (isset($ex_vid[$i])) $ex_vid[$i]['caption'] = $t('video_cap_' . $i, $ex_vid[$i]['caption'] ?? ''); }
        for ($i = 0; $i < 4; $i++) { if (isset($ex_voc[$i])) $ex_voc[$i]['title'] = $t('voice_title_' . $i, $ex_voc[$i]['title'] ?? ''); }
        $config['videos'] = ['enabled' => $chk('videos_enabled'), 'heading' => $t('videos_heading') ?: $defaults['videos']['heading'], 'items' => $ex_vid];
        $config['voices'] = ['enabled' => $chk('voices_enabled'), 'heading' => $t('voices_heading') ?: $defaults['voices']['heading'], 'items' => $ex_voc];

        // Preview without saving (new uploads appear after publish)
        if (($_POST['do'] ?? '') === 'preview') {
            render_couple_story(['id' => $editing ? ($page['id'] ?? 0) : 0, 'user_id' => $user_id, 'sender_name' => $c1, 'receiver_name' => $c2, 'slide_data' => json_encode($config, JSON_UNESCAPED_UNICODE), 'template' => 'couple_story', 'guest_session_id' => session_id(), 'expiry_date' => null, 'is_expired' => 0, 'password' => null, '_preview' => true]);
            exit;
        }

        // Uploads
        $warnings = [];
        if (!empty($_FILES['gallery_files']['name'][0])) {
            $cnt = count($_FILES['gallery_files']['name']);
            for ($i = 0; $i < $cnt && count($ex_gal) < 30; $i++) {
                if ($_FILES['gallery_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $single = ['name' => $_FILES['gallery_files']['name'][$i], 'type' => $_FILES['gallery_files']['type'][$i], 'tmp_name' => $_FILES['gallery_files']['tmp_name'][$i], 'error' => $_FILES['gallery_files']['error'][$i], 'size' => $_FILES['gallery_files']['size'][$i]];
                $r = upload_image($single);
                if ($r['success']) $ex_gal[] = ['path' => $r['path'], 'medium' => $r['medium'] ?? $r['path'], 'thumb' => $r['thumb'] ?? $r['path']];
                else $warnings[] = 'Photo "' . $_FILES['gallery_files']['name'][$i] . '": ' . $r['message'];
            }
            $config['gallery']['images'] = array_slice($ex_gal, 0, 30);
        }
        for ($i = 0; $i < 3; $i++) {
            $f = 'video_file_' . $i;
            if (!empty($_FILES[$f]['name']) && $_FILES[$f]['error'] === UPLOAD_ERR_OK) {
                $r = function_exists('upload_video') ? upload_video($_FILES[$f], 'uploads/videos/') : ['success' => false, 'message' => 'Video upload unavailable'];
                if ($r['success']) $ex_vid[$i] = ['url' => $r['path'], 'caption' => $t('video_cap_' . $i)];
                else $warnings[] = 'Video ' . ($i + 1) . ': ' . $r['message'];
            }
        }
        $config['videos']['items'] = array_values(array_filter($ex_vid, function ($v) { return !empty($v['url']); }));
        for ($i = 0; $i < 4; $i++) {
            $f = 'voice_file_' . $i;
            if (!empty($_FILES[$f]['name']) && $_FILES[$f]['error'] === UPLOAD_ERR_OK) {
                $r = upload_voice($_FILES[$f], 'uploads/voice/');
                if ($r['success']) $ex_voc[$i] = ['url' => $r['path'], 'title' => $t('voice_title_' . $i)];
                else $warnings[] = 'Voice ' . ($i + 1) . ': ' . $r['message'];
            }
        }
        $config['voices']['items'] = array_values(array_filter($ex_voc, function ($v) { return !empty($v['url']); }));
        if (!empty($_FILES['music_audio']['name']) && $_FILES['music_audio']['error'] === UPLOAD_ERR_OK) {
            $r = upload_voice($_FILES['music_audio'], 'uploads/music/', 20);
            if ($r['success']) $config['music']['audio_url'] = $r['path']; else $warnings[] = 'Music: ' . $r['message'];
        }

        $slide_data_json = json_encode($config, JSON_UNESCAPED_UNICODE);

        if (empty($error)) {
            if ($is_admin_demo) {
                set_setting($demo_setting_key, $slide_data_json);
                redirect('customize-couple.php?admin_demo=' . urlencode($admin_demo) . '&saved=1');
            } elseif ($editing) {
                $hashed_password = $page['password'];
                if ($page_password !== '') $hashed_password = ($page_password === '__CLEAR__') ? null : password_hash($page_password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE pages SET title=?, sender_name=?, receiver_name=?, letter_text=?, slide_data=?, accent_color=?, password=?, music_url=? WHERE id=?");
                $upd->execute([$title, $c1, $c2, $config['final']['quote'], $slide_data_json, $themes[$config['theme']]['acc'], $hashed_password, $config['music']['audio_url'], $page['id']]);
                $save_slug = $page['slug'];
            } else {
                $days = (int)get_setting('default_expiry_days', 10); if ($days < 1) $days = 10;
                $expiry_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                $cost = PREMIUM_CREDIT_COST;
                if (get_user_credits($user_id) < $cost) {
                    $error = 'Premium pages need ' . $cost . ' credits (₹10 each). Please buy credits.';
                } else {
                    $save_slug = generate_slug($c1 . '-' . $c2 . '-story', $pdo);
                    $hashed_password = ($page_password !== '' && $page_password !== '__CLEAR__') ? password_hash($page_password, PASSWORD_DEFAULT) : null;
                    $ins = $pdo->prepare("INSERT INTO pages (user_id, category, template, title, slug, sender_name, receiver_name, letter_text, slide_data, music_url, theme, accent_color, font_style, proposal_question, password, status, guest_session_id, expiry_date) VALUES (?, 'couple', 'couple_story', ?, ?, ?, ?, ?, ?, ?, 'elegant', ?, 'Cormorant Garamond', ?, ?, 'published', ?, ?)");
                    $ins->execute([$user_id, $title, $save_slug, $c1, $c2, $config['final']['quote'], $slide_data_json, $config['music']['audio_url'], $themes[$config['theme']]['acc'], $config['couple']['title'], $hashed_password, session_id(), $expiry_date]);
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
    $stmt_ml = $pdo->query("SELECT id, title, file_path, category FROM music_library WHERE category IN ('premium','couple','love','all') AND (status = 'approved' OR status IS NULL) ORDER BY FIELD(category,'premium','couple','love','all'), title");
    $music_library = $stmt_ml->fetchAll();
} catch (PDOException $e) {
    try { $music_library = $pdo->query("SELECT id, title, file_path, category FROM music_library WHERE category IN ('premium','couple','love','all') ORDER BY category, title")->fetchAll(); } catch (PDOException $e2) { $music_library = []; }
}

$gal_existing = array_values($cfg['gallery']['images'] ?? []);
$vid_existing = array_values($cfg['videos']['items'] ?? []);
$voc_existing = array_values($cfg['voices']['items'] ?? []);
$cust_base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
$thumbs = function ($im) use ($cust_base) { $u = is_array($im) ? ($im['thumb'] ?? $im['path'] ?? '') : $im; return $u ? ($cust_base . ltrim($u, '/')) : ''; };
$csrf = generate_csrf_token();
$is_fresh = !$editing && !$is_admin_demo && $_SERVER['REQUEST_METHOD'] !== 'POST';
function cs_toggle($name, $cfg, $sec) {
    $on = !empty($cfg[$sec]['enabled']);
    echo '<label class="tgl"><input type="checkbox" name="' . $name . '" value="1" ' . ($on ? 'checked' : '') . ' class="w-4 h-4 accent-rose-500"> Show this section</label>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $is_admin_demo ? 'Customize Demo' : ($editing ? 'Edit' : 'Create') ?> Couple Story ❤️ - <?= h(SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#0b0a10,#1a1020); color:#fbe3e8; }
  h1,h2,h3,.heading { font-family:'Outfit',sans-serif; }
  .fld { width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(253,164,175,0.2); border-radius:12px; padding:10px 14px; color:#fff; font-size:0.9rem; outline:none; }
  .fld:focus { border-color:#f43f5e; }
  .fld::placeholder { color:rgba(253,164,175,0.35); }
  select.fld option { background:#1a1020; }
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
  .cbfooter { position:sticky; bottom:0; z-index:15; margin-top:8px; padding:14px; background:rgba(11,10,16,0.94); backdrop-filter:blur(10px); border-top:1px solid rgba(244,63,94,0.3); border-radius:16px 16px 0 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:center; }
  .btn-nav { background:rgba(255,255,255,0.08); border:1px solid rgba(253,164,175,0.25); color:#fda4af; border-radius:50px; padding:12px 22px; font-weight:600; cursor:pointer; }
  .btn-preview { background:rgba(255,255,255,0.06); border:1px solid rgba(253,164,175,0.35); color:#fff; border-radius:50px; padding:12px 22px; font-weight:600; cursor:pointer; }
  .tgl { display:flex; align-items:center; gap:10px; cursor:pointer; margin-left:auto; font-size:0.75rem; color:#fda4af; }
  .rep-row { display:grid; gap:8px; background:rgba(0,0,0,0.22); border:1px solid rgba(253,164,175,0.12); border-radius:14px; padding:12px; margin-bottom:10px; position:relative; }
  .rep-del { position:absolute; top:8px; right:8px; background:rgba(239,68,68,0.18); border:1px solid rgba(239,68,68,0.35); color:#fca5a5; border-radius:8px; width:26px; height:26px; cursor:pointer; font-size:.8rem; line-height:1; }
  .rep-add { background:rgba(244,63,94,0.12); border:1px dashed rgba(244,63,94,0.45); color:#fda4af; border-radius:12px; padding:10px 18px; cursor:pointer; font-size:.82rem; font-weight:600; width:100%; }
  .theme-pick { display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:10px; }
  .theme-pick label { display:block; border:2px solid transparent; border-radius:14px; padding:10px 6px; text-align:center; cursor:pointer; font-size:.75rem; font-weight:600; }
  .theme-pick input { display:none; }
  .theme-pick input:checked + span::after { content:' ✓'; }
</style>
</head>
<body class="min-h-screen">

<header class="sticky top-0 z-20" style="background:rgba(11,10,16,0.92); backdrop-filter:blur(12px); border-bottom:1px solid rgba(244,63,94,0.25);">
  <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="<?= $is_admin_demo ? 'admin/premium-demos.php' : 'dashboard.php' ?>" class="text-sm text-rose-200/70 hover:text-white">← Back</a>
    <div class="heading font-extrabold text-lg" style="color:#fda4af;">👑 Couple Story</div>
    <a href="index.php" class="text-sm text-rose-200/50 hover:text-white"><?= h(SITE_NAME) ?></a>
  </div>
</header>

<main class="max-w-3xl mx-auto px-4 py-8">
  <div class="text-center mb-8">
    <h1 class="heading text-3xl font-extrabold" style="color:#fda4af;"><?= $is_admin_demo ? '🖼️ Customize the Demo' : ($editing ? 'Edit Your Couple Story' : 'Build Your Love Story Website') ?></h1>
    <p class="text-sm text-rose-200/50 mt-2"><?= $is_admin_demo ? 'This demo is shown to users in "Live Preview". It updates live when you save.' : 'Not a slideshow — a mini romantic website: live together-timer, timeline, polaroid wall, chats, letters, playlist, guest book & a starry ending. 👑' ?></p>
  </div>

  <?php if ($is_admin_demo): ?><div class="mb-6 rounded-2xl p-4 text-sm flex items-center gap-3" style="background:rgba(124,58,237,0.15); border:1px solid rgba(124,58,237,0.35); color:#e9d5ff;">🛠️ <b>Admin Demo Mode</b> — editing the public demo for "Couple Story".<a href="customize-couple.php?demo=1" target="_blank" class="ml-auto underline hover:text-white">View live demo ↗</a></div><?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Demo saved!</div><?php endif; ?>
  <?php if ($error): ?><div class="mb-6 bg-red-500/15 border border-red-500/30 text-red-200 rounded-xl p-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="cbForm">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <?php if ($editing): ?><input type="hidden" name="edit_slug" value="<?= h($page['slug']) ?>"><?php endif; ?>
    <?php if ($is_admin_demo): ?><input type="hidden" name="admin_demo" value="<?= h($admin_demo) ?>"><?php endif; ?>
    <input type="hidden" name="existing_music_audio" value="<?= h($cfg['music']['audio_url'] ?? '') ?>">
    <input type="hidden" name="existing_gallery" value="<?= h(json_encode($gal_existing)) ?>">
    <input type="hidden" name="existing_videos" value="<?= h(json_encode($vid_existing)) ?>">
    <input type="hidden" name="existing_voices" value="<?= h(json_encode($voc_existing)) ?>">
    <input type="hidden" name="do" id="doField" value="publish">

    <div class="cbtabs">
      <button type="button" class="cbtab active" onclick="showStep(0)"><span class="num">1</span>Couple</button>
      <button type="button" class="cbtab" onclick="showStep(1)"><span class="num">2</span>Timeline</button>
      <button type="button" class="cbtab" onclick="showStep(2)"><span class="num">3</span>Media</button>
      <button type="button" class="cbtab" onclick="showStep(3)"><span class="num">4</span>Chats &amp; Letters</button>
      <button type="button" class="cbtab" onclick="showStep(4)"><span class="num">5</span>Playlist &amp; More</button>
      <button type="button" class="cbtab" onclick="showStep(5)"><span class="num">6</span>Fun &amp; Final</button>
    </div>

    <!-- STEP 1 · COUPLE & THEME -->
    <div class="cbstep active" data-step="0">
      <div class="sec">
        <div class="sec-title">💑 The Couple</div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Your Name</label><input class="fld" name="c1" value="<?= h($is_fresh ? '' : csf($cfg,'couple','c1')) ?>" placeholder="e.g. <?= h(get_example('sender')) ?>"></div>
          <div><label class="lbl">Partner's Name</label><input class="fld" name="c2" value="<?= h($is_fresh ? '' : csf($cfg,'couple','c2')) ?>" placeholder="e.g. <?= h(get_example('receiver')) ?>"></div>
          <div><label class="lbl">Your Nickname (optional)</label><input class="fld" name="nick1" value="<?= h(csf($cfg,'couple','nick1')) ?>"></div>
          <div><label class="lbl">Partner's Nickname (optional)</label><input class="fld" name="nick2" value="<?= h(csf($cfg,'couple','nick2')) ?>"></div>
          <div><label class="lbl">Together Since (powers the live timer ⏳)</label><input class="fld" type="date" name="couple_since" value="<?= h(csf($cfg,'couple','since')) ?>"></div>
          <div><label class="lbl">Relationship Status</label><input class="fld" name="couple_status" value="<?= h(csf($cfg,'couple','status')) ?>"></div>
          <div><label class="lbl">First Meet (optional)</label><input class="fld" name="first_meet" value="<?= h(csf($cfg,'couple','first_meet')) ?>"></div>
          <div><label class="lbl">First Chat (optional)</label><input class="fld" name="first_chat" value="<?= h(csf($cfg,'couple','first_chat')) ?>"></div>
          <div><label class="lbl">Distance Between You (optional)</label><input class="fld" name="distance" value="<?= h(csf($cfg,'couple','distance')) ?>" placeholder="e.g. 412 km apart, 0 km at heart"></div>
          <div><label class="lbl">Cover Title</label><input class="fld" name="couple_title" value="<?= h(csf($cfg,'couple','title')) ?>"></div>
        </div>
        <p class="hint mt-3">Everywhere in the page you can write <code>[c1]</code> and <code>[c2]</code> — they become your names automatically.</p>
      </div>
      <div class="sec">
        <div class="sec-title">🎨 Theme</div>
        <div class="theme-pick">
          <?php foreach ($themes as $tk => $tv): ?>
          <label style="background:linear-gradient(135deg,<?= h($tv['bg1']) ?>,<?= h($tv['bg2']) ?>); color:<?= h($tv['text']) ?>; border-color:<?= ($cfg['theme'] ?? 'dark_love') === $tk ? h($tv['acc']) : 'transparent' ?>;">
            <input type="radio" name="theme" value="<?= h($tk) ?>" <?= ($cfg['theme'] ?? 'dark_love') === $tk ? 'checked' : '' ?> onchange="this.closest('.theme-pick').querySelectorAll('label').forEach(l=>l.style.borderColor='transparent'); this.parentElement.style.borderColor='<?= h($tv['acc']) ?>';">
            <span><?= h($tv['name']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="sec">
        <div class="sec-title">💌 Envelope Opening <?php cs_toggle('intro_enabled', $cfg, 'intro'); ?></div>
        <p class="hint mb-2">The story starts sealed in an envelope. Tap → it opens, hearts fly, and this line types itself out.</p>
        <textarea class="fld" name="intro_line" rows="2"><?= h(csf($cfg,'intro','line')) ?></textarea>
        <div class="grid sm:grid-cols-2 gap-4 mt-4">
          <div><label class="lbl">Browser Tab / Share Title</label><input class="fld" name="page_title" value="<?= h($is_fresh ? '' : ($cfg['page_title'] ?? '')) ?>" placeholder="[c1] ❤️ [c2] — Our Story"></div>
          <div><label class="lbl">Password (optional)</label><input class="fld" name="page_password" type="text" placeholder="<?= $editing && !empty($page['password']) ? 'Blank keeps current — __CLEAR__ removes' : 'Leave blank for no password' ?>"></div>
        </div>
      </div>
    </div>

    <!-- STEP 2 · ABOUT + COUNTER + TIMELINE -->
    <div class="cbstep" data-step="1">
      <div class="sec">
        <div class="sec-title">💑 About Us <?php cs_toggle('about_enabled', $cfg, 'about'); ?></div>
        <label class="lbl">Heading</label><input class="fld" name="about_heading" value="<?= h(csf($cfg,'about','heading')) ?>">
      </div>
      <div class="sec">
        <div class="sec-title">⏳ Live Relationship Counter <?php cs_toggle('counter_enabled', $cfg, 'counter'); ?></div>
        <p class="hint mb-2">Years / months / days / hours / minutes / seconds — ticking live from your "Together Since" date.</p>
        <label class="lbl">Heading</label><input class="fld" name="counter_heading" value="<?= h(csf($cfg,'counter','heading')) ?>">
      </div>
      <div class="sec">
        <div class="sec-title">📅 Love Timeline <?php cs_toggle('timeline_enabled', $cfg, 'timeline'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="timeline_heading" value="<?= h(csf($cfg,'timeline','heading')) ?>">
        <div id="rep-tl">
          <?php foreach (($cfg['timeline']['items'] ?? []) as $it): ?>
          <div class="rep-row">
            <button type="button" class="rep-del" onclick="this.parentElement.remove()">✕</button>
            <div class="grid grid-cols-3 gap-2">
              <input class="fld" name="tl_emoji[]" value="<?= h($it['emoji'] ?? '') ?>" placeholder="❤️">
              <input class="fld col-span-2" name="tl_date[]" value="<?= h($it['date'] ?? '') ?>" placeholder="14 Feb 2023">
            </div>
            <input class="fld" name="tl_title[]" value="<?= h($it['title'] ?? '') ?>" placeholder="First Message">
            <textarea class="fld" name="tl_text[]" rows="2" placeholder="What happened…"><?= h($it['text'] ?? '') ?></textarea>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="rep-add" onclick="addRow('tl')">➕ Add Milestone (unlimited)</button>
      </div>
    </div>

    <!-- STEP 3 · MEDIA -->
    <div class="cbstep" data-step="2">
      <div class="sec">
        <div class="sec-title">📸 Memory Gallery <?php cs_toggle('gallery_enabled', $cfg, 'gallery'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="gallery_heading" value="<?= h(csf($cfg,'gallery','heading')) ?>">
        <?php if ($gal_existing): ?>
        <div class="mb-3"><label class="lbl">Current (<?= count($gal_existing) ?>/30)</label>
          <div class="grid grid-cols-8 gap-2"><?php foreach ($gal_existing as $im): ?><div class="aspect-square rounded-lg overflow-hidden border border-rose-500/20"><img src="<?= h($thumbs($im)) ?>" class="w-full h-full object-cover"></div><?php endforeach; ?></div>
          <label class="flex items-center gap-2 mt-2 text-xs text-red-300"><input type="checkbox" name="gallery_clear" value="1" class="accent-red-500"> Remove all &amp; replace</label>
        </div>
        <?php endif; ?>
        <label class="lbl">Add photos (up to 30 total, each ≤10MB — first 8 become the polaroid wall)</label>
        <input class="fld" type="file" name="gallery_files[]" accept="image/*" multiple>
        <p class="hint">Visitors can zoom &amp; download every photo.</p>
      </div>
      <div class="sec">
        <div class="sec-title">🎥 Video Memories <?php cs_toggle('videos_enabled', $cfg, 'videos'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="videos_heading" value="<?= h(csf($cfg,'videos','heading')) ?>">
        <?php if (isset($_POST['videos_clear']) || !$vid_existing): ?><?php endif; ?>
        <?php for ($i = 0; $i < 3; $i++): $cv = $vid_existing[$i] ?? null; ?>
        <div class="rep-row">
          <label class="lbl">Video <?= $i + 1 ?> (mp4 — max 50MB)<?= $cv ? ' — ✅ ' . h(basename($cv['url'])) : '' ?></label>
          <input class="fld" type="file" name="video_file_<?= $i ?>" accept="video/*">
          <input class="fld" name="video_cap_<?= $i ?>" value="<?= h($cv['caption'] ?? '') ?>" placeholder="Caption (optional)">
        </div>
        <?php endfor; ?>
        <?php if ($vid_existing): ?><label class="flex items-center gap-2 text-xs text-red-300"><input type="checkbox" name="videos_clear" value="1" class="accent-red-500"> Remove all current videos</label><?php endif; ?>
      </div>
      <div class="sec">
        <div class="sec-title">🎙️ Voice Notes <?php cs_toggle('voices_enabled', $cfg, 'voices'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="voices_heading" value="<?= h(csf($cfg,'voices','heading')) ?>">
        <?php for ($i = 0; $i < 4; $i++): $cvo = $voc_existing[$i] ?? null; ?>
        <div class="rep-row">
          <label class="lbl">Voice Note <?= $i + 1 ?> (mp3/m4a — max 10MB)<?= $cvo ? ' — ✅ ' . h(basename($cvo['url'])) : '' ?></label>
          <input class="fld" type="file" name="voice_file_<?= $i ?>" accept="audio/*">
          <input class="fld" name="voice_title_<?= $i ?>" value="<?= h($cvo['title'] ?? '') ?>" placeholder="Title, e.g. Good morning 🎧">
        </div>
        <?php endfor; ?>
        <?php if ($voc_existing): ?><label class="flex items-center gap-2 text-xs text-red-300"><input type="checkbox" name="voices_clear" value="1" class="accent-red-500"> Remove all current voice notes</label><?php endif; ?>
        <p class="hint">Wave animation + playback speed player, like a premium music app.</p>
      </div>
    </div>

    <!-- STEP 4 · CHATS & LETTERS -->
    <div class="cbstep" data-step="3">
      <div class="sec">
        <div class="sec-title">💬 Chat Memories (WhatsApp style) <?php cs_toggle('chats_enabled', $cfg, 'chats'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="chats_heading" value="<?= h(csf($cfg,'chats','heading')) ?>">
        <div id="rep-ch">
          <?php foreach (($cfg['chats']['items'] ?? []) as $m): ?>
          <div class="rep-row">
            <button type="button" class="rep-del" onclick="this.parentElement.remove()">✕</button>
            <div class="grid grid-cols-3 gap-2">
              <select class="fld" name="ch_side[]"><option value="l" <?= ($m['side'] ?? 'l') === 'l' ? 'selected' : '' ?>>Them (left)</option><option value="r" <?= ($m['side'] ?? '') === 'r' ? 'selected' : '' ?>>You (right)</option></select>
              <input class="fld col-span-2" name="ch_time[]" value="<?= h($m['time'] ?? '') ?>" placeholder="9:14 PM">
            </div>
            <input class="fld" name="ch_text[]" value="<?= h($m['text'] ?? '') ?>" placeholder="Message…">
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="rep-add" onclick="addRow('ch')">➕ Add Message</button>
        <p class="hint mt-2">Visitors can tap any bubble to ❤️ it.</p>
      </div>
      <div class="sec">
        <div class="sec-title">💌 Love Letters <?php cs_toggle('letters_enabled', $cfg, 'letters'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="letters_heading" value="<?= h(csf($cfg,'letters','heading')) ?>">
        <div id="rep-lt">
          <?php foreach (($cfg['letters']['items'] ?? []) as $lt): ?>
          <div class="rep-row">
            <button type="button" class="rep-del" onclick="this.parentElement.remove()">✕</button>
            <input class="fld" name="lt_title[]" value="<?= h($lt['title'] ?? '') ?>" placeholder="To my favourite person">
            <textarea class="fld" name="lt_text[]" rows="5" placeholder="Dear [c2]…"><?= h($lt['text'] ?? '') ?></textarea>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="rep-add" onclick="addRow('lt')">➕ Add Letter (unlimited)</button>
        <p class="hint mt-2">Each letter sits in an envelope — tap to unfold on handwritten paper.</p>
      </div>
    </div>

    <!-- STEP 5 · PLAYLIST, MAP, REASONS, DREAMS -->
    <div class="cbstep" data-step="4">
      <div class="sec">
        <div class="sec-title">🎵 Our Playlist <?php cs_toggle('playlist_enabled', $cfg, 'playlist'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="playlist_heading" value="<?= h(csf($cfg,'playlist','heading')) ?>">
        <div id="rep-pl">
          <?php foreach (($cfg['playlist']['items'] ?? []) as $s): ?>
          <div class="rep-row">
            <button type="button" class="rep-del" onclick="this.parentElement.remove()">✕</button>
            <div class="grid grid-cols-2 gap-2">
              <input class="fld" name="pl_title[]" value="<?= h($s['title'] ?? '') ?>" placeholder="Song">
              <input class="fld" name="pl_artist[]" value="<?= h($s['artist'] ?? '') ?>" placeholder="Artist">
            </div>
            <input class="fld" name="pl_note[]" value="<?= h($s['note'] ?? '') ?>" placeholder="Why it's ours (optional)">
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="rep-add" onclick="addRow('pl')">➕ Add Song</button>
      </div>
      <div class="sec">
        <div class="sec-title">🗺️ Love Map <?php cs_toggle('places_enabled', $cfg, 'places'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="places_heading" value="<?= h(csf($cfg,'places','heading')) ?>">
        <div id="rep-pc">
          <?php foreach (($cfg['places']['items'] ?? []) as $p): ?>
          <div class="rep-row">
            <button type="button" class="rep-del" onclick="this.parentElement.remove()">✕</button>
            <div class="grid grid-cols-3 gap-2">
              <input class="fld" name="pc_emoji[]" value="<?= h($p['emoji'] ?? '') ?>" placeholder="📍">
              <input class="fld col-span-2" name="pc_name[]" value="<?= h($p['name'] ?? '') ?>" placeholder="Where We Met">
            </div>
            <input class="fld" name="pc_note[]" value="<?= h($p['note'] ?? '') ?>" placeholder="A little note">
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="rep-add" onclick="addRow('pc')">➕ Add Place</button>
      </div>
      <div class="sec">
        <div class="sec-title">💞 Reasons I Love You <?php cs_toggle('reasons_enabled', $cfg, 'reasons'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="reasons_heading" value="<?= h(csf($cfg,'reasons','heading')) ?>">
        <div id="rep-rs">
          <?php foreach (($cfg['reasons']['items'] ?? []) as $r): ?>
          <div class="rep-row">
            <button type="button" class="rep-del" onclick="this.parentElement.remove()">✕</button>
            <div class="grid grid-cols-3 gap-2">
              <input class="fld" name="rs_emoji[]" value="<?= h($r['emoji'] ?? '') ?>" placeholder="😊">
              <input class="fld col-span-2" name="rs_title[]" value="<?= h($r['title'] ?? '') ?>" placeholder="Your Smile">
            </div>
            <textarea class="fld" name="rs_text[]" rows="2" placeholder="Why…"><?= h($r['text'] ?? '') ?></textarea>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="rep-add" onclick="addRow('rs')">➕ Add Reason (unlimited)</button>
      </div>
      <div class="sec">
        <div class="sec-title">🌙 Dreams Together <?php cs_toggle('dreams_enabled', $cfg, 'dreams'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="dreams_heading" value="<?= h(csf($cfg,'dreams','heading')) ?>">
        <div id="rep-dm">
          <?php foreach (($cfg['dreams']['items'] ?? []) as $d): ?>
          <div class="rep-row">
            <button type="button" class="rep-del" onclick="this.parentElement.remove()">✕</button>
            <div class="grid grid-cols-4 gap-2">
              <input class="fld" name="dm_emoji[]" value="<?= h($d['emoji'] ?? '') ?>" placeholder="✈️">
              <input class="fld col-span-3" name="dm_text[]" value="<?= h($d['text'] ?? '') ?>" placeholder="Travel the world together">
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="rep-add" onclick="addRow('dm')">➕ Add Dream</button>
      </div>
    </div>

    <!-- STEP 6 · FUN & FINAL -->
    <div class="cbstep" data-step="5">
      <div class="sec">
        <div class="sec-title">📜 Promise Wall <?php cs_toggle('promises_enabled', $cfg, 'promises'); ?></div>
        <label class="lbl">Heading</label><input class="fld mb-3" name="promises_heading" value="<?= h(csf($cfg,'promises','heading')) ?>">
        <div id="rep-pr">
          <?php foreach (($cfg['promises']['items'] ?? []) as $pr): $prt = is_array($pr) ? ($pr['text'] ?? '') : $pr; ?>
          <div class="rep-row">
            <button type="button" class="rep-del" onclick="this.parentElement.remove()">✕</button>
            <input class="fld" name="pr_text[]" value="<?= h($prt) ?>" placeholder="I will always choose you.">
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="rep-add" onclick="addRow('pr')">➕ Add Promise</button>
      </div>
      <div class="sec">
        <div class="sec-title">🏆 Achievements <?php cs_toggle('achieve_enabled', $cfg, 'achieve'); ?></div>
        <p class="hint mb-2">Auto-unlocking badges: 100 days, 1 year, 500 days, 2 years, 1000 days, 5 years — computed from your "Together Since" date.</p>
        <label class="lbl">Heading</label><input class="fld" name="achieve_heading" value="<?= h(csf($cfg,'achieve','heading')) ?>">
      </div>
      <div class="sec">
        <div class="sec-title">❤️ Love Meter <?php cs_toggle('meter_enabled', $cfg, 'meter'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Heading</label><input class="fld" name="meter_heading" value="<?= h(csf($cfg,'meter','heading')) ?>"></div>
          <div><label class="lbl">Subtitle</label><input class="fld" name="meter_label" value="<?= h(csf($cfg,'meter','label')) ?>"></div>
        </div>
        <p class="hint mt-2">Fills 0 → ∞100% when scrolled into view, then hearts explode.</p>
      </div>
      <div class="sec">
        <div class="sec-title">🔐 Secret Message <?php cs_toggle('secret_enabled', $cfg, 'secret'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Hint shown to them</label><input class="fld" name="secret_hint" value="<?= h(csf($cfg,'secret','hint')) ?>"></div>
          <div><label class="lbl">Secret code</label><input class="fld" name="secret_code" value="<?= h(csf($cfg,'secret','code')) ?>"></div>
        </div>
        <div class="mt-4"><label class="lbl">Hidden message</label><textarea class="fld" name="secret_text" rows="3"><?= h(csf($cfg,'secret','text')) ?></textarea></div>
      </div>
      <div class="sec">
        <div class="sec-title">📝 Guest Book <?php cs_toggle('guestbook_enabled', $cfg, 'guestbook'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Heading</label><input class="fld" name="guestbook_heading" value="<?= h(csf($cfg,'guestbook','heading')) ?>"></div>
          <div><label class="lbl">Subtitle</label><input class="fld" name="guestbook_subtitle" value="<?= h(csf($cfg,'guestbook','subtitle')) ?>"></div>
        </div>
        <p class="hint mt-2">Visitor wishes land in your Dashboard → Replies.</p>
      </div>
      <div class="sec">
        <div class="sec-title">🌌 Final Screen — "Our Journey Never Ends…" <?php cs_toggle('final_enabled', $cfg, 'final'); ?></div>
        <p class="hint mb-2">Night sky full of stars, couple silhouette, infinite glowing heart, share buttons &amp; QR code.</p>
        <label class="lbl">Closing quote</label>
        <textarea class="fld" name="final_quote" rows="2"><?= h(csf($cfg,'final','quote')) ?></textarea>
      </div>
      <div class="sec">
        <div class="sec-title">🎵 Background Music</div>
        <?php if (!empty($music_library)): ?>
        <?php $cur_music = $cfg['music']['audio_url'] ?? ''; ?>
        <div class="mb-4">
          <label class="lbl">Choose from the music library (recommended) 🎶</label>
          <select class="fld" name="music_library_url">
            <option value="">— None / upload your own —</option>
            <?php foreach ($music_library as $trk): ?>
            <option value="<?= h($trk['file_path']) ?>" <?= ($cur_music === $trk['file_path']) ? 'selected' : '' ?>><?= $trk['category'] === 'premium' ? '✨ ' : '' ?><?= h($trk['title']) ?><?= $trk['category'] !== 'premium' ? ' (' . h($trk['category']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div><label class="lbl">Track Title</label><input class="fld" name="music_title" value="<?= h(csf($cfg,'music','title')) ?>"></div>
        <div class="mt-4"><label class="lbl">Or upload your own (mp3 — max 20MB)</label><input class="fld" type="file" name="music_audio" accept="audio/*"><?php if (!empty($cfg['music']['audio_url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['music']['audio_url'])) ?></p><?php endif; ?></div>
      </div>
    </div>

    <div class="cbfooter">
      <button type="button" class="btn-nav" id="btnPrev" onclick="stepMove(-1)" style="display:none;">← Back</button>
      <button type="submit" class="btn-preview" id="btnPreview" onclick="cbOpenPreviewLoader();" formtarget="cbPreviewWin" style="display:none;">👁️ Preview</button>
      <button type="button" class="btn-nav" id="btnNext" onclick="stepMove(1)">Next →</button>
      <button type="submit" class="btn-rose" id="btnPublish" onclick="document.getElementById('doField').value='publish';" style="display:none;"><?= $is_admin_demo ? '💾 Save Demo' : ($editing ? '💾 Save Changes' : '👑 Publish Our Story') ?></button>
    </div>
    <p class="hint text-center pb-6"><?= $editing ? 'Changes save instantly to your live page.' : 'Publishing uses ' . PREMIUM_CREDIT_COST . ' credits.' ?> &nbsp;·&nbsp; 👁️ Preview opens in a new tab.</p>
  </form>
</main>

<script>
let cbCurrentStep = 0; const cbTotalSteps = 6;
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

// repeater templates
const REP = {
  tl: '<div class="grid grid-cols-3 gap-2"><input class="fld" name="tl_emoji[]" placeholder="❤️"><input class="fld col-span-2" name="tl_date[]" placeholder="14 Feb 2023"></div><input class="fld" name="tl_title[]" placeholder="Milestone title"><textarea class="fld" name="tl_text[]" rows="2" placeholder="What happened…"></textarea>',
  ch: '<div class="grid grid-cols-3 gap-2"><select class="fld" name="ch_side[]"><option value="l">Them (left)</option><option value="r">You (right)</option></select><input class="fld col-span-2" name="ch_time[]" placeholder="9:14 PM"></div><input class="fld" name="ch_text[]" placeholder="Message…">',
  lt: '<input class="fld" name="lt_title[]" placeholder="Letter title"><textarea class="fld" name="lt_text[]" rows="5" placeholder="Dear [c2]…"></textarea>',
  pl: '<div class="grid grid-cols-2 gap-2"><input class="fld" name="pl_title[]" placeholder="Song"><input class="fld" name="pl_artist[]" placeholder="Artist"></div><input class="fld" name="pl_note[]" placeholder="Why it\'s ours (optional)">',
  pc: '<div class="grid grid-cols-3 gap-2"><input class="fld" name="pc_emoji[]" placeholder="📍"><input class="fld col-span-2" name="pc_name[]" placeholder="Place"></div><input class="fld" name="pc_note[]" placeholder="A little note">',
  rs: '<div class="grid grid-cols-3 gap-2"><input class="fld" name="rs_emoji[]" placeholder="😊"><input class="fld col-span-2" name="rs_title[]" placeholder="Your Smile"></div><textarea class="fld" name="rs_text[]" rows="2" placeholder="Why…"></textarea>',
  dm: '<div class="grid grid-cols-4 gap-2"><input class="fld" name="dm_emoji[]" placeholder="✈️"><input class="fld col-span-3" name="dm_text[]" placeholder="A dream…"></div>',
  pr: '<input class="fld" name="pr_text[]" placeholder="A promise…">'
};
function addRow(k) {
  const wrap = document.getElementById('rep-' + k);
  const row = document.createElement('div'); row.className = 'rep-row';
  row.innerHTML = '<button type="button" class="rep-del" onclick="this.parentElement.remove()">✕</button>' + REP[k];
  wrap.appendChild(row);
}

// "Generating" overlay on publish
document.getElementById('cbForm').addEventListener('submit', function () {
  if (document.getElementById('doField').value !== 'publish') return;
  const o = document.createElement('div');
  o.style.cssText = 'position:fixed;inset:0;z-index:5000;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(11,10,16,0.96);backdrop-filter:blur(8px);text-align:center;padding:20px;';
  o.innerHTML = '<div style="font-size:3.4rem;animation:genBob 1.2s ease-in-out infinite;">👑</div>'
    + '<div style="font-family:Outfit,sans-serif;font-weight:800;font-size:1.4rem;color:#fda4af;margin-top:14px;">Building your love story…</div>'
    + '<div style="color:rgba(253,164,175,0.6);font-size:0.85rem;margin-top:6px;">Uploading photos, videos, voice &amp; music ✨</div>'
    + '<div style="width:190px;height:5px;background:rgba(255,255,255,0.12);border-radius:4px;margin-top:20px;overflow:hidden;"><div style="height:100%;width:40%;border-radius:4px;background:linear-gradient(90deg,#f43f5e,#fb7185);animation:genSlide 1.2s ease-in-out infinite;"></div></div>'
    + '<style>@keyframes genBob{0%,100%{transform:translateY(0) rotate(-4deg);}50%{transform:translateY(-10px) rotate(4deg);}}@keyframes genSlide{0%{margin-left:-40%;}100%{margin-left:100%;}}</style>';
  document.body.appendChild(o);
  const pb = document.getElementById('btnPublish'); if (pb) pb.disabled = true;
});

<?php if ($is_fresh): ?>
(function(){
  const exclude = ['c1','c2','page_title','page_password','secret_code'];
  document.querySelectorAll('#cbForm input:not([type=file]):not([type=date]):not([type=hidden]):not([type=checkbox]):not([type=radio]), #cbForm textarea').forEach(function(f){
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
<?php render_tutorial_button('couple'); ?>
</body>
</html>
