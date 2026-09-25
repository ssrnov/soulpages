<?php
// =========================================================================
// customize-proposal.php — Editor for the "Cinematic Proposal" premium template.
// Mirrors customize-birthday.php: public demo, admin-demo mode, stepped wizard,
// live preview, media uploads, music-library picker, credits/draft.
// =========================================================================
require_once 'includes/functions.php';
require_once __DIR__ . '/templates/proposal_cinematic.php';

$demo_key = 'proposal_cinematic';
$demo_setting_key = 'premium_demo_' . $demo_key;

// --- Public live demo (no login) ---
if (isset($_GET['demo'])) {
    $demo_cfg = json_decode(get_setting($demo_setting_key, ''), true);
    if (!is_array($demo_cfg) || empty($demo_cfg)) $demo_cfg = proposal_cinematic_defaults();
    $demo_page = [
        'id' => 0, 'user_id' => null,
        'sender_name'   => $demo_cfg['_sender'] ?? get_example('sender'),
        'receiver_name' => $demo_cfg['_receiver'] ?? get_example('receiver'),
        'slide_data' => json_encode($demo_cfg),
        'template' => 'proposal_cinematic', 'status' => 'published',
        'password' => null, 'expiry_date' => null, 'is_expired' => 0, 'guest_session_id' => null,
    ];
    render_proposal_cinematic($demo_page);
    exit;
}

$admin_demo    = trim($_GET['admin_demo'] ?? ($_POST['admin_demo'] ?? ''));
$is_admin_demo = ($admin_demo !== '');

// --- Auth ---
if (!is_logged_in()) {
    $_SESSION['login_error'] = 'Please log in to create your proposal.';
    redirect('login.php');
}
$user_id = $_SESSION['user_id'];
$user = get_user_profile($user_id);
if ($is_admin_demo && !is_admin()) redirect('dashboard.php');
if (!$is_admin_demo && $user && isset($user['email_verified']) && (int)$user['email_verified'] === 0) {
    redirect('verification-pending.php');
}

$defaults = proposal_cinematic_defaults();
$error = '';
$editing = false;
$page = null;

$slug = trim($_GET['slug'] ?? ($_POST['edit_slug'] ?? ''));
if (!$is_admin_demo && !empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND template = 'proposal_cinematic'");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    if ($page && ($page['user_id'] == $user_id || is_admin())) $editing = true;
    else $page = null;
}

if (!$editing && !$is_admin_demo && !can_create_page($user_id, PREMIUM_CREDIT_COST)) redirect('payment.php');

if ($is_admin_demo) {
    $cfg = proposal_cinematic_config(['slide_data' => get_setting($demo_setting_key, '')]);
} else {
    $cfg = $page ? proposal_cinematic_config($page) : $defaults;
}

function cbf($cfg, $sec, $key, $def = '') { return $cfg[$sec][$key] ?? $def; }

// =========================================================================
// POST
// =========================================================================
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

        foreach (['rose','deep','gold','blush','cream','text'] as $ck) {
            $v = $t('color_' . $ck); if ($v !== '') $config['colors'][$ck] = $v;
        }
        $config['page_title'] = $t('page_title', $defaults['page_title']);

        $config['welcome'] = ['enabled' => $chk('welcome_enabled'), 'subtitle' => $t('welcome_subtitle'), 'button' => $t('welcome_button', 'Open 🌹'), 'particles' => $chk('welcome_particles')];
        $config['surprise'] = ['enabled' => $chk('surprise_enabled'), 'message' => $t('surprise_message'), 'button' => $t('surprise_button', 'Continue 💕'), 'gift_color' => $t('surprise_gift_color', '#e8405a')];

        $letter_paras = [];
        foreach (preg_split('/\r\n|\r|\n/', $_POST['letter_paragraphs'] ?? '') as $line) { if (trim($line) !== '') $letter_paras[] = rtrim($line); }
        $config['letter'] = ['enabled' => $chk('letter_enabled'), 'label' => $t('letter_label'), 'script' => $t('letter_script'), 'paragraphs' => !empty($letter_paras) ? $letter_paras : $defaults['letter']['paragraphs'], 'signature' => $t('letter_signature'), 'font' => $t('letter_font', 'Cormorant Garamond'), 'paper_color' => $t('letter_paper_color', '#fffdf8'), 'audio_url' => $t('existing_letter_audio')];

        $config['voice'] = ['enabled' => $chk('voice_enabled'), 'label' => $t('voice_label'), 'script' => $t('voice_script'), 'subtitle' => $t('voice_subtitle'), 'name' => $t('voice_name', '[sender]'), 'avatar' => $t('voice_avatar', '💌'), 'audio_url' => $t('existing_voice_audio')];
        $config['video'] = ['enabled' => $chk('video_enabled'), 'label' => $t('video_label'), 'script' => $t('video_script'), 'subtitle' => $t('video_subtitle'), 'url' => $t('existing_video'), 'poster' => $t('existing_poster')];
        $config['scene'] = ['enabled' => $chk('scene_enabled'), 'line1' => $t('scene_line1'), 'line2' => $t('scene_line2'), 'button' => $t('scene_button', 'One last thing… 💍')];
        $config['final'] = ['enabled' => $chk('final_enabled'), 'question' => $t('final_question', 'Will You Be Mine Forever?'), 'yes' => $t('final_yes', 'YES ❤️'), 'no' => $t('final_no', 'No'), 'funny_no' => $chk('final_funny_no')];
        $config['celebration'] = ['enabled' => $chk('celebration_enabled'), 'message' => $t('celebration_message')];
        $config['reply'] = ['enabled' => $chk('reply_enabled'), 'heading' => $t('reply_heading', 'Say something back 💌'), 'subtitle' => $t('reply_subtitle'), 'placeholder' => $t('reply_placeholder', 'Write your reply…'), 'button' => $t('reply_button', 'Send 💖'), 'success' => $t('reply_success', 'Sent! Thank you ❤️')];
        $config['music'] = ['title' => $t('music_title', 'Background Music'), 'audio_url' => $t('existing_music_audio')];
        $lib_music = trim($_POST['music_library_url'] ?? '');
        if ($lib_music !== '' && strpos($lib_music, 'assets/music/') === 0 && strpos($lib_music, '..') === false) $config['music']['audio_url'] = $lib_music;

        // ── Story pages (7) ──
        $split = function ($k) { return preg_split('/\r\n|\r|\n/', $_POST[$k] ?? ''); };
        $dec   = function ($k) { $v = json_decode($_POST[$k] ?? '[]', true); return is_array($v) ? $v : []; };

        $jrn = [];
        foreach ($split('journey_items') as $line) { $line = trim($line); if ($line === '') continue; $p = array_map('trim', explode('|', $line)); if (count($p) >= 3) $jrn[] = ['icon' => $p[0] ?: '✨', 'date' => $p[1], 'title' => $p[2]]; elseif (count($p) === 2) $jrn[] = ['icon' => '✨', 'date' => $p[0], 'title' => $p[1]]; else $jrn[] = ['icon' => '✨', 'date' => '', 'title' => $p[0]]; }
        $config['journey'] = ['enabled' => $chk('journey_enabled'), 'label' => $t('journey_label'), 'script' => $t('journey_script'), 'items' => $jrn ?: $defaults['journey']['items']];

        $rsn = array_values(array_filter(array_map('trim', $split('reasons_items')), 'strlen'));
        $config['reasons'] = ['enabled' => $chk('reasons_enabled'), 'label' => $t('reasons_label'), 'script' => $t('reasons_script'), 'items' => $rsn ?: $defaults['reasons']['items']];

        $fut = [];
        foreach ($split('future_items') as $line) { $line = trim($line); if ($line === '') continue; $p = array_map('trim', explode('|', $line)); if (count($p) >= 2) $fut[] = ['icon' => $p[0] ?: '✨', 'text' => $p[1]]; else $fut[] = ['icon' => '✨', 'text' => $p[0]]; }
        $config['future'] = ['enabled' => $chk('future_enabled'), 'label' => $t('future_label'), 'script' => $t('future_script'), 'items' => $fut ?: $defaults['future']['items']];

        $config['ring'] = ['enabled' => $chk('ring_enabled'), 'label' => $t('ring_label'), 'script' => $t('ring_script'), 'text' => $t('ring_text'), 'ring_emoji' => $t('ring_emoji', '💍')];

        // Photo pages — start from existing images (new uploads appended later)
        $ex_gal = isset($_POST['gallery_clear'])   ? [] : $dec('existing_gallery');
        $ex_fav = isset($_POST['favorites_clear'])  ? [] : $dec('existing_favorites');
        $ex_mom = isset($_POST['moments_clear'])    ? [] : $dec('existing_moments');
        $fav_caps  = array_map('trim', $split('favorites_captions'));
        $mom_lines = array_map('trim', $split('moments_items'));
        $zipFav = function ($imgs, $caps) { $o = []; foreach ($imgs as $i => $im) $o[] = ['img' => $im, 'caption' => $caps[$i] ?? '']; return $o; };
        $zipMom = function ($imgs, $lines) { $o = []; foreach ($imgs as $i => $im) { $pp = array_map('trim', explode('|', $lines[$i] ?? '')); $o[] = ['img' => $im, 'title' => $pp[0] ?? '', 'desc' => $pp[1] ?? '']; } return $o; };
        $config['gallery']   = ['enabled' => $chk('gallery_enabled'), 'label' => $t('gallery_label'), 'script' => $t('gallery_script'), 'subtitle' => $t('gallery_subtitle'), 'images' => $ex_gal];
        $config['favorites'] = ['enabled' => $chk('favorites_enabled'), 'label' => $t('favorites_label'), 'script' => $t('favorites_script'), 'photos' => $zipFav($ex_fav, $fav_caps)];
        $config['moments']   = ['enabled' => $chk('moments_enabled'), 'label' => $t('moments_label'), 'script' => $t('moments_script'), 'items' => $zipMom($ex_mom, $mom_lines)];

        // ── Optional pages ──
        $ex_wall  = isset($_POST['photo_wall_clear']) ? [] : $dec('existing_photowall');
        $ex_heart = isset($_POST['heart_clear'])      ? [] : $dec('existing_heart');
        $config['photo_wall'] = ['enabled' => $chk('photo_wall_enabled'), 'label' => $t('photo_wall_label'), 'script' => $t('photo_wall_script'), 'images' => $ex_wall];
        $config['heart']      = ['enabled' => $chk('heart_enabled'), 'label' => $t('heart_label'), 'script' => $t('heart_script'), 'subtitle' => $t('heart_subtitle'), 'images' => $ex_heart];
        $config['scratch']    = ['enabled' => $chk('scratch_enabled'), 'label' => $t('scratch_label'), 'script' => $t('scratch_script'), 'cover' => $t('scratch_cover', 'Scratch here…'), 'message' => $t('scratch_message')];
        $config['secret_pw']  = ['enabled' => $chk('secret_pw_enabled'), 'label' => $t('secret_pw_label'), 'script' => $t('secret_pw_script'), 'hint' => $t('secret_pw_hint'), 'password' => $t('secret_pw_password'), 'success' => $t('secret_pw_success', 'Unlocked!')];
        $config['puzzle']     = ['enabled' => $chk('puzzle_enabled'), 'label' => $t('puzzle_label'), 'script' => $t('puzzle_script'), 'image' => $t('existing_puzzle_img'), 'success' => $t('puzzle_success', 'You did it!')];
        $quiz_q = []; $qq = $_POST['quiz_q'] ?? []; $qo = $_POST['quiz_opts'] ?? []; $qc = $_POST['quiz_correct'] ?? [];
        for ($i = 0; $i < count($qq); $i++) { $qt = trim($qq[$i]); if ($qt === '') continue; $opts = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $qo[$i] ?? '')), 'strlen')); if (!$opts) continue; $ci = (int)($qc[$i] ?? 1) - 1; if ($ci < 0 || $ci >= count($opts)) $ci = 0; $quiz_q[] = ['q' => $qt, 'options' => $opts, 'correct' => $ci]; }
        $config['quiz']       = ['enabled' => $chk('quiz_enabled'), 'label' => $t('quiz_label'), 'script' => $t('quiz_script'), 'questions' => $quiz_q ?: $defaults['quiz']['questions']];
        $config['love_meter'] = ['enabled' => $chk('love_meter_enabled'), 'label' => $t('love_meter_label'), 'script' => $t('love_meter_script'), 'percent' => max(0, min(100, (int)$t('love_meter_percent', 100))), 'caption' => $t('love_meter_caption')];
        $config['countdown']  = ['enabled' => $chk('countdown_enabled'), 'label' => $t('countdown_label'), 'message' => $t('countdown_message')];
        $cal = []; foreach ($split('calendar_items') as $line) { $line = trim($line); if ($line === '') continue; $p = array_map('trim', explode('|', $line)); if (count($p) >= 2) $cal[] = ['date' => $p[0], 'label' => $p[1]]; else $cal[] = ['date' => '', 'label' => $p[0]]; }
        $config['calendar']    = ['enabled' => $chk('calendar_enabled'), 'label' => $t('calendar_label'), 'script' => $t('calendar_script'), 'dates' => $cal ?: $defaults['calendar']['dates']];
        $config['destination'] = ['enabled' => $chk('destination_enabled'), 'label' => $t('destination_label'), 'script' => $t('destination_script'), 'place' => $t('destination_place'), 'address' => $t('destination_address')];
        $config['thankyou']    = ['enabled' => $chk('thankyou_enabled'), 'label' => $t('thankyou_label'), 'script' => $t('thankyou_script'), 'message' => $t('thankyou_message'), 'signature' => $t('thankyou_signature')];

        // Meta
        $receiver = $t('receiver_name', 'My Love') ?: 'My Love';
        $sender   = $t('sender_name', 'Me') ?: 'Me';
        $title    = $t('page_title') ?: ($receiver . ' — A Proposal 💍');
        $letter_text = trim(strip_tags(implode("\n", $config['letter']['paragraphs']))) ?: 'A proposal.';
        $accent   = $config['colors']['rose'] ?? '#e8405a';
        $proposal_q = strip_tags($config['final']['question'] ?? 'Will you be mine?');
        $page_password = $t('page_password');

        // Preview (render without saving)
        if (($_POST['do'] ?? '') === 'preview') {
            $preview_page = ['id' => $editing ? ($page['id'] ?? 0) : 0, 'user_id' => $user_id, 'sender_name' => $sender, 'receiver_name' => $receiver, 'slide_data' => json_encode($config, JSON_UNESCAPED_UNICODE), 'template' => 'proposal_cinematic', 'guest_session_id' => session_id(), 'expiry_date' => null, 'is_expired' => 0, 'password' => null, '_preview' => true];
            render_proposal_cinematic($preview_page);
            exit;
        }

        // Media uploads
        $warnings = [];
        $do_upload = function ($field, $dir, $max_mb = 10) use (&$warnings) {
            if (empty($_FILES[$field]['name'])) return null;
            if ($_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
            if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) { $warnings[] = "Upload error on {$field} (code {$_FILES[$field]['error']})."; return null; }
            $res = upload_voice($_FILES[$field], $dir, $max_mb);
            if ($res['success']) return $res['path'];
            $warnings[] = "Audio for {$field} failed: " . $res['message']; return null;
        };
        if ($p = $do_upload('letter_audio', 'uploads/voice/'))   $config['letter']['audio_url'] = $p;
        if ($p = $do_upload('voice_audio', 'uploads/voice/'))    $config['voice']['audio_url'] = $p;
        if ($p = $do_upload('music_audio', 'uploads/music/', 20)) $config['music']['audio_url'] = $p;

        // Video (up to 100MB)
        if (!empty($_FILES['video_file']['name'])) {
            if ($_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                $vres = upload_video($_FILES['video_file'], 'uploads/videos/', 100);
                if ($vres['success']) $config['video']['url'] = $vres['path'];
                else $warnings[] = 'Video: ' . $vres['message'];
            } elseif ($_FILES['video_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $warnings[] = 'Video upload error (code ' . $_FILES['video_file']['error'] . ').';
            }
        }
        // Video poster (image)
        if (!empty($_FILES['video_poster']['name']) && $_FILES['video_poster']['error'] === UPLOAD_ERR_OK) {
            $pr = upload_image($_FILES['video_poster']);
            if ($pr['success']) $config['video']['poster'] = $pr['medium'] ?? $pr['path'];
            else $warnings[] = 'Poster: ' . $pr['message'];
        }

        // Multi-image uploads for the photo pages (append to existing, capped)
        $upmulti = function ($field, $cap, $have) use (&$warnings) {
            $out = [];
            if (!empty($_FILES[$field]['name'][0])) {
                $cnt = count($_FILES[$field]['name']);
                for ($i = 0; $i < $cnt && ($have + count($out)) < $cap; $i++) {
                    if ($_FILES[$field]['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $single = ['name' => $_FILES[$field]['name'][$i], 'type' => $_FILES[$field]['type'][$i], 'tmp_name' => $_FILES[$field]['tmp_name'][$i], 'error' => $_FILES[$field]['error'][$i], 'size' => $_FILES[$field]['size'][$i]];
                    $r = upload_image($single);
                    if ($r['success']) $out[] = ['path' => $r['path'], 'medium' => $r['medium'] ?? $r['path'], 'thumb' => $r['thumb'] ?? $r['path']];
                    else $warnings[] = 'Photo "' . $_FILES[$field]['name'][$i] . '": ' . $r['message'];
                }
            }
            return $out;
        };
        $config['gallery']['images']   = array_slice(array_merge($ex_gal, $upmulti('gallery_files', 10, count($ex_gal))), 0, 10);
        $config['favorites']['photos'] = $zipFav(array_slice(array_merge($ex_fav, $upmulti('favorites_files', 8, count($ex_fav))), 0, 8), $fav_caps);
        $config['moments']['items']    = $zipMom(array_slice(array_merge($ex_mom, $upmulti('moments_files', 6, count($ex_mom))), 0, 6), $mom_lines);
        $config['photo_wall']['images'] = array_slice(array_merge($ex_wall, $upmulti('photo_wall_files', 12, count($ex_wall))), 0, 12);
        $config['heart']['images']      = array_slice(array_merge($ex_heart, $upmulti('heart_files', 12, count($ex_heart))), 0, 12);
        if (!empty($_FILES['puzzle_img']['name']) && $_FILES['puzzle_img']['error'] === UPLOAD_ERR_OK) { $pr = upload_image($_FILES['puzzle_img']); if ($pr['success']) $config['puzzle']['image'] = $pr['medium'] ?? $pr['path']; else $warnings[] = 'Puzzle image: ' . $pr['message']; }

        if ($is_admin_demo) { $config['_sender'] = $sender; $config['_receiver'] = $receiver; }
        $slide_data_json = json_encode($config, JSON_UNESCAPED_UNICODE);

        if (empty($error)) {
            if ($is_admin_demo) {
                set_setting($demo_setting_key, $slide_data_json);
                if (!empty($warnings)) $_SESSION['cb_warnings'] = $warnings;
                redirect('customize-proposal.php?admin_demo=' . urlencode($admin_demo) . '&saved=1');
            } elseif ($editing) {
                $hashed_password = $page['password'];
                if ($page_password !== '') $hashed_password = ($page_password === '__CLEAR__') ? null : password_hash($page_password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE pages SET title=?, sender_name=?, receiver_name=?, letter_text=?, slide_data=?, accent_color=?, proposal_question=?, password=?, music_url=? WHERE id=?");
                $upd->execute([$title, $sender, $receiver, $letter_text, $slide_data_json, $accent, $proposal_q, $hashed_password, $config['music']['audio_url'], $page['id']]);
                $save_slug = $page['slug'];
            } else {
                $days = (int)get_setting('default_expiry_days', 10); if ($days < 1) $days = 10;
                $expiry_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                $cost = PREMIUM_CREDIT_COST; // premium page = 5 credits
                if (get_user_credits($user_id) < $cost) {
                    $error = 'Premium pages need ' . $cost . ' credits (₹10 each). Please buy credits.';
                } else {
                    $save_slug = generate_slug($receiver . '-proposal', $pdo);
                    $hashed_password = ($page_password !== '' && $page_password !== '__CLEAR__') ? password_hash($page_password, PASSWORD_DEFAULT) : null;
                    $ins = $pdo->prepare("INSERT INTO pages (user_id, category, template, title, slug, sender_name, receiver_name, letter_text, slide_data, music_url, theme, accent_color, font_style, proposal_question, password, status, guest_session_id, expiry_date) VALUES (?, 'proposal', 'proposal_cinematic', ?, ?, ?, ?, ?, ?, ?, 'romantic', ?, 'Cormorant Garamond', ?, ?, 'published', ?, ?)");
                    $ins->execute([$user_id, $title, $save_slug, $sender, $receiver, $letter_text, $slide_data_json, $config['music']['audio_url'], $accent, $proposal_q, $hashed_password, session_id(), $expiry_date]);
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
    $stmt_ml = $pdo->query("SELECT id, title, file_path, category FROM music_library WHERE category IN ('premium','proposal','all') AND (status = 'approved' OR status IS NULL) ORDER BY FIELD(category,'premium','proposal','all'), title");
    $music_library = $stmt_ml->fetchAll();
} catch (PDOException $e) {
    try { $music_library = $pdo->query("SELECT id, title, file_path, category FROM music_library WHERE category IN ('premium','proposal','all') ORDER BY category, title")->fetchAll(); } catch (PDOException $e2) { $music_library = []; }
}

$letter_prefill = implode("\n", $cfg['letter']['paragraphs'] ?? []);
$journey_prefill  = implode("\n", array_map(function ($it) { return ($it['icon'] ?? '✨') . ' | ' . ($it['date'] ?? '') . ' | ' . ($it['title'] ?? ''); }, $cfg['journey']['items'] ?? []));
$reasons_prefill  = implode("\n", $cfg['reasons']['items'] ?? []);
$future_prefill   = implode("\n", array_map(function ($it) { return ($it['icon'] ?? '✨') . ' | ' . ($it['text'] ?? ''); }, $cfg['future']['items'] ?? []));
$favcaps_prefill  = implode("\n", array_map(function ($p) { return $p['caption'] ?? ''; }, $cfg['favorites']['photos'] ?? []));
$moments_prefill  = implode("\n", array_map(function ($it) { return ($it['title'] ?? '') . ' | ' . ($it['desc'] ?? ''); }, $cfg['moments']['items'] ?? []));
$cust_base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
$thumbs = function ($im) use ($cust_base) { $u = is_array($im) ? ($im['thumb'] ?? $im['path'] ?? '') : $im; return $u ? ($cust_base . ltrim($u, '/')) : ''; };
$gal_existing = array_values($cfg['gallery']['images'] ?? []);
$fav_existing = array_values(array_map(function ($p) { return $p['img'] ?? $p; }, $cfg['favorites']['photos'] ?? []));
$mom_existing = array_values(array_map(function ($it) { return $it['img'] ?? ''; }, $cfg['moments']['items'] ?? []));
$calendar_prefill = implode("\n", array_map(function ($d) { return ($d['date'] ?? '') . ' | ' . ($d['label'] ?? ''); }, $cfg['calendar']['dates'] ?? []));
$wall_existing  = array_values($cfg['photo_wall']['images'] ?? []);
$heart_existing = array_values($cfg['heart']['images'] ?? []);
$quiz_qs = $cfg['quiz']['questions'] ?? [];
$csrf = generate_csrf_token();
$fonts = ['Cormorant Garamond', 'Playfair Display', 'Satisfy', 'DM Sans'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $is_admin_demo ? 'Customize Demo' : ($editing ? 'Edit' : 'Create') ?> Cinematic Proposal 💍 - <?= h(SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#12030b,#2d0f1e); color:#f5e6ec; }
  h1,h2,h3,.heading { font-family:'Outfit',sans-serif; }
  .fld { width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(255,150,170,0.18); border-radius:12px; padding:10px 14px; color:#fff; font-size:0.9rem; outline:none; }
  .fld:focus { border-color:#e8405a; }
  .fld::placeholder { color:rgba(255,200,210,0.35); }
  .lbl { display:block; font-size:0.72rem; text-transform:uppercase; letter-spacing:1px; color:#e39ab0; margin-bottom:5px; font-weight:600; }
  .sec { background:rgba(255,255,255,0.04); border:1px solid rgba(255,150,170,0.14); border-radius:20px; padding:22px; margin-bottom:18px; }
  .sec-title { font-size:1.05rem; font-weight:700; color:#ffb3c1; margin-bottom:14px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .btn-rose { background:linear-gradient(135deg,#e8405a,#c4184e); color:#fff; border:none; border-radius:50px; padding:14px 40px; font-weight:600; cursor:pointer; box-shadow:0 8px 24px rgba(232,64,90,0.35); }
  .hint { font-size:0.72rem; color:rgba(255,200,210,0.5); margin-top:4px; }
  textarea.fld { resize:vertical; line-height:1.6; }
  .fld.ghost { color:rgba(255,200,210,0.4) !important; font-style:italic; }
  .cbstep { display:none; animation:stepIn .35s ease; } .cbstep.active { display:block; }
  @keyframes stepIn { from { opacity:0; transform:translateY(8px);} to { opacity:1; transform:none;} }
  .cbtabs { display:flex; gap:6px; overflow-x:auto; padding:6px; margin-bottom:18px; background:rgba(0,0,0,0.25); border:1px solid rgba(255,150,170,0.14); border-radius:16px; position:sticky; top:0; z-index:15; backdrop-filter:blur(8px); }
  .cbtab { flex:1 0 auto; white-space:nowrap; padding:9px 12px; border-radius:11px; font-size:0.78rem; font-weight:600; color:#e39ab0; background:transparent; border:none; cursor:pointer; }
  .cbtab.active { background:linear-gradient(135deg,#e8405a,#c4184e); color:#fff; }
  .cbtab .num { display:inline-flex; width:18px; height:18px; border-radius:50%; background:rgba(255,255,255,0.15); align-items:center; justify-content:center; font-size:0.68rem; margin-right:5px; }
  .cbfooter { position:sticky; bottom:0; z-index:15; margin-top:8px; padding:14px; background:rgba(18,3,11,0.94); backdrop-filter:blur(10px); border-top:1px solid rgba(232,64,90,0.25); border-radius:16px 16px 0 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:center; }
  .btn-nav { background:rgba(255,255,255,0.08); border:1px solid rgba(255,150,170,0.25); color:#ffb3c1; border-radius:50px; padding:12px 22px; font-weight:600; cursor:pointer; }
  .btn-preview { background:rgba(255,255,255,0.06); border:1px solid rgba(255,200,210,0.35); color:#fff; border-radius:50px; padding:12px 22px; font-weight:600; cursor:pointer; }
  .tgl { display:flex; align-items:center; gap:10px; cursor:pointer; margin-left:auto; font-size:0.75rem; color:#e6b8c4; }
</style>
</head>
<body class="min-h-screen">

<header class="sticky top-0 z-20" style="background:rgba(18,3,11,0.92); backdrop-filter:blur(12px); border-bottom:1px solid rgba(232,64,90,0.2);">
  <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="<?= $is_admin_demo ? 'admin/premium-demos.php' : 'dashboard.php' ?>" class="text-sm text-pink-200/70 hover:text-white">← Back</a>
    <div class="heading font-extrabold text-lg" style="color:#ffb3c1;">💍 Cinematic Proposal</div>
    <a href="index.php" class="text-sm text-pink-200/50 hover:text-white"><?= h(SITE_NAME) ?></a>
  </div>
</header>

<main class="max-w-3xl mx-auto px-4 py-8">
  <div class="text-center mb-8">
    <h1 class="heading text-3xl font-extrabold" style="color:#ffb3c1;"><?= $is_admin_demo ? '🖼️ Customize the Demo' : ($editing ? 'Edit Your Proposal' : 'Design Your Proposal') ?></h1>
    <p class="text-sm text-pink-200/50 mt-2"><?= $is_admin_demo ? 'This demo is shown to users in "Live Preview". It updates live as soon as you save.' : 'A movie-style proposal. Turn any page on/off and customize everything. ✨' ?></p>
  </div>

  <?php if ($is_admin_demo): ?>
    <div class="mb-6 rounded-2xl p-4 text-sm flex items-center gap-3" style="background:rgba(124,58,237,0.15); border:1px solid rgba(124,58,237,0.35); color:#e9d5ff;">🛠️ <b>Admin Demo Mode</b> — you are editing the public demo for "Cinematic Proposal".<a href="customize-proposal.php?demo=1" target="_blank" class="ml-auto underline hover:text-white">View live demo ↗</a></div>
  <?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Demo saved! Users will now see this in "Live Preview".</div><?php endif; ?>
  <?php if ($error): ?><div class="mb-6 bg-red-500/15 border border-red-500/30 text-red-200 rounded-xl p-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <?php $is_fresh = !$editing && !$is_admin_demo && $_SERVER['REQUEST_METHOD'] !== 'POST'; ?>
  <?php
    // helper to render an enable toggle for a page section
    function enable_toggle($name, $cfg, $sec) {
        $on = !empty($cfg[$sec]['enabled']);
        echo '<label class="tgl"><input type="checkbox" name="' . $name . '" value="1" ' . ($on ? 'checked' : '') . ' class="w-4 h-4 accent-pink-500"> Show this page</label>';
    }
  ?>

  <form method="post" enctype="multipart/form-data" id="cbForm">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <?php if ($editing): ?><input type="hidden" name="edit_slug" value="<?= h($page['slug']) ?>"><?php endif; ?>
    <?php if ($is_admin_demo): ?><input type="hidden" name="admin_demo" value="<?= h($admin_demo) ?>"><?php endif; ?>
    <input type="hidden" name="existing_letter_audio" value="<?= h($cfg['letter']['audio_url'] ?? '') ?>">
    <input type="hidden" name="existing_voice_audio" value="<?= h($cfg['voice']['audio_url'] ?? '') ?>">
    <input type="hidden" name="existing_music_audio" value="<?= h($cfg['music']['audio_url'] ?? '') ?>">
    <input type="hidden" name="existing_video" value="<?= h($cfg['video']['url'] ?? '') ?>">
    <input type="hidden" name="existing_poster" value="<?= h($cfg['video']['poster'] ?? '') ?>">
    <input type="hidden" name="existing_gallery" value="<?= h(json_encode($gal_existing)) ?>">
    <input type="hidden" name="existing_favorites" value="<?= h(json_encode($fav_existing)) ?>">
    <input type="hidden" name="existing_moments" value="<?= h(json_encode($mom_existing)) ?>">
    <input type="hidden" name="existing_photowall" value="<?= h(json_encode($wall_existing)) ?>">
    <input type="hidden" name="existing_heart" value="<?= h(json_encode($heart_existing)) ?>">
    <input type="hidden" name="existing_puzzle_img" value="<?= h($cfg['puzzle']['image'] ?? '') ?>">
    <input type="hidden" name="do" id="doField" value="publish">

    <div class="cbtabs">
      <button type="button" class="cbtab active" onclick="showStep(0)"><span class="num">1</span>Basics</button>
      <button type="button" class="cbtab" onclick="showStep(1)"><span class="num">2</span>Story</button>
      <button type="button" class="cbtab" onclick="showStep(2)"><span class="num">3</span>Letter &amp; Media</button>
      <button type="button" class="cbtab" onclick="showStep(3)"><span class="num">4</span>Proposal</button>
      <button type="button" class="cbtab" onclick="showStep(4)"><span class="num">5</span>End &amp; Music</button>
      <button type="button" class="cbtab" onclick="showStep(5)"><span class="num">6</span>Extras</button>
    </div>

    <!-- STEP 1 · BASICS -->
    <div class="cbstep active" data-step="0">
      <div class="sec">
        <div class="sec-title">💌 Basic Info — just fill 2 names</div>
        <p class="hint mb-4">These two names auto-fill across the whole proposal (letter, voice, signature). The rest is pre-filled with an example.</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Receiver's Name</label><input class="fld" name="receiver_name" value="<?= h($is_admin_demo ? ($cfg['_receiver'] ?? get_example('receiver')) : ($is_fresh ? '' : ($editing ? ($page['receiver_name'] ?? '') : ($_POST['receiver_name'] ?? '')))) ?>" placeholder="e.g. <?= h(get_example('receiver')) ?>"></div>
          <div><label class="lbl">Your Name (sender)</label><input class="fld" name="sender_name" value="<?= h($is_admin_demo ? ($cfg['_sender'] ?? get_example('sender')) : ($editing ? ($page['sender_name'] ?? '') : ($_POST['sender_name'] ?? ''))) ?>" placeholder="e.g. <?= h(get_example('sender')) ?>"></div>
        </div>
        <div class="mt-4"><label class="lbl">Browser Tab / Share Title</label><input class="fld" name="page_title" value="<?= h($is_fresh ? '' : ($cfg['page_title'] ?? '')) ?>" placeholder="For [receiver] ❤️"><p class="hint">Leave blank to auto-fill. <code>[receiver]</code> = the name above.</p></div>
        <div class="mt-4"><label class="lbl">Password (optional — lock the page)</label><input class="fld" name="page_password" type="text" placeholder="<?= $editing && !empty($page['password']) ? 'Blank keeps current — type __CLEAR__ to remove' : 'Leave blank for no password' ?>"></div>
      </div>

      <div class="sec">
        <div class="sec-title">🎨 Colors</div>
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-4">
          <?php foreach (['rose'=>'Accent','deep'=>'Background','gold'=>'Gold','blush'=>'Blush','cream'=>'Cream','text'=>'Text'] as $ck=>$cl): ?>
          <div><label class="lbl"><?= h($cl) ?></label><input type="color" name="color_<?= $ck ?>" value="<?= h($cfg['colors'][$ck] ?? '#e8405a') ?>" class="w-full h-10 rounded-lg bg-transparent border border-pink-500/20 cursor-pointer"></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="sec">
        <div class="sec-title">🌹 Page 1 — Welcome <?php enable_toggle('welcome_enabled', $cfg, 'welcome'); ?></div>
        <div><label class="lbl">Subtitle</label><textarea class="fld" name="welcome_subtitle" rows="2"><?= h(cbf($cfg,'welcome','subtitle')) ?></textarea></div>
        <div class="grid sm:grid-cols-2 gap-4 mt-4">
          <div><label class="lbl">Button Text</label><input class="fld" name="welcome_button" value="<?= h(cbf($cfg,'welcome','button')) ?>"></div>
          <label class="tgl" style="margin-left:0; align-self:end; padding-bottom:10px;"><input type="checkbox" name="welcome_particles" value="1" <?= !empty($cfg['welcome']['particles']) ? 'checked' : '' ?> class="w-4 h-4 accent-pink-500"> Floating petals &amp; bokeh</label>
        </div>
      </div>

      <div class="sec">
        <div class="sec-title">🎁 Page 2 — A Small Surprise <?php enable_toggle('surprise_enabled', $cfg, 'surprise'); ?></div>
        <div><label class="lbl">Message (above the gift)</label><input class="fld" name="surprise_message" value="<?= h(cbf($cfg,'surprise','message')) ?>"></div>
        <div class="grid sm:grid-cols-2 gap-4 mt-4">
          <div><label class="lbl">Button Text</label><input class="fld" name="surprise_button" value="<?= h(cbf($cfg,'surprise','button')) ?>"></div>
          <div><label class="lbl">Gift Color</label><input type="color" name="surprise_gift_color" value="<?= h(cbf($cfg,'surprise','gift_color','#e8405a')) ?>" class="w-full h-10 rounded-lg bg-transparent border border-pink-500/20 cursor-pointer"></div>
        </div>
      </div>
    </div>

    <!-- STEP 2 · STORY -->
    <div class="cbstep" data-step="1">
      <div class="sec">
        <div class="sec-title">✨ Page 3 — Our Journey <?php enable_toggle('journey_enabled', $cfg, 'journey'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="journey_label" value="<?= h(cbf($cfg,'journey','label')) ?>"></div>
          <div><label class="lbl">Title (script)</label><input class="fld" name="journey_script" value="<?= h(cbf($cfg,'journey','script')) ?>"></div>
        </div>
        <div class="mt-4"><label class="lbl">Timeline events — one per line: <code>icon | date | title</code></label><textarea class="fld" name="journey_items" rows="5"><?= h($journey_prefill) ?></textarea></div>
      </div>

      <div class="sec">
        <div class="sec-title">📸 Page 4 — Memory Gallery <?php enable_toggle('gallery_enabled', $cfg, 'gallery'); ?></div>
        <p class="hint mb-3">Layout auto-adjusts to photo count (1 hero → 2 split → grid → scrapbook). Only shows when photos are added.</p>
        <div class="grid sm:grid-cols-3 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="gallery_label" value="<?= h(cbf($cfg,'gallery','label')) ?>"></div>
          <div><label class="lbl">Title</label><input class="fld" name="gallery_script" value="<?= h(cbf($cfg,'gallery','script')) ?>"></div>
          <div><label class="lbl">Subtitle</label><input class="fld" name="gallery_subtitle" value="<?= h(cbf($cfg,'gallery','subtitle')) ?>"></div>
        </div>
        <?php if ($gal_existing): ?>
        <div class="mt-3"><label class="lbl">Current (<?= count($gal_existing) ?>/10)</label>
          <div class="grid grid-cols-6 gap-2"><?php foreach ($gal_existing as $im): ?><div class="aspect-square rounded-lg overflow-hidden border border-pink-500/20"><img src="<?= h($thumbs($im)) ?>" class="w-full h-full object-cover"></div><?php endforeach; ?></div>
          <label class="flex items-center gap-2 mt-2 text-xs text-red-300"><input type="checkbox" name="gallery_clear" value="1" class="accent-red-500"> Remove all &amp; replace</label>
        </div>
        <?php endif; ?>
        <div class="mt-4"><label class="lbl">Add photos (up to 10, each ≤10MB)</label><input class="fld" type="file" name="gallery_files[]" accept="image/*" multiple></div>
      </div>

      <div class="sec">
        <div class="sec-title">❤️ Page 5 — Favourite Photos <?php enable_toggle('favorites_enabled', $cfg, 'favorites'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="favorites_label" value="<?= h(cbf($cfg,'favorites','label')) ?>"></div>
          <div><label class="lbl">Title</label><input class="fld" name="favorites_script" value="<?= h(cbf($cfg,'favorites','script')) ?>"></div>
        </div>
        <?php if ($fav_existing): ?>
        <div class="mt-3"><label class="lbl">Current (<?= count($fav_existing) ?>/8)</label>
          <div class="grid grid-cols-6 gap-2"><?php foreach ($fav_existing as $im): ?><div class="aspect-square rounded-lg overflow-hidden border border-pink-500/20"><img src="<?= h($thumbs($im)) ?>" class="w-full h-full object-cover"></div><?php endforeach; ?></div>
          <label class="flex items-center gap-2 mt-2 text-xs text-red-300"><input type="checkbox" name="favorites_clear" value="1" class="accent-red-500"> Remove all &amp; replace</label>
        </div>
        <?php endif; ?>
        <div class="mt-4"><label class="lbl">Add photos (up to 8)</label><input class="fld" type="file" name="favorites_files[]" accept="image/*" multiple></div>
        <div class="mt-4"><label class="lbl">Captions — one per line (matches photo order)</label><textarea class="fld" name="favorites_captions" rows="4"><?= h($favcaps_prefill) ?></textarea></div>
      </div>

      <div class="sec">
        <div class="sec-title">💕 Page 6 — Beautiful Moments <?php enable_toggle('moments_enabled', $cfg, 'moments'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="moments_label" value="<?= h(cbf($cfg,'moments','label')) ?>"></div>
          <div><label class="lbl">Title</label><input class="fld" name="moments_script" value="<?= h(cbf($cfg,'moments','script')) ?>"></div>
        </div>
        <?php if ($mom_existing): ?>
        <div class="mt-3"><label class="lbl">Current (<?= count($mom_existing) ?>/6)</label>
          <div class="grid grid-cols-6 gap-2"><?php foreach ($mom_existing as $im): ?><div class="aspect-square rounded-lg overflow-hidden border border-pink-500/20"><img src="<?= h($thumbs($im)) ?>" class="w-full h-full object-cover"></div><?php endforeach; ?></div>
          <label class="flex items-center gap-2 mt-2 text-xs text-red-300"><input type="checkbox" name="moments_clear" value="1" class="accent-red-500"> Remove all &amp; replace</label>
        </div>
        <?php endif; ?>
        <div class="mt-4"><label class="lbl">Add photos (up to 6)</label><input class="fld" type="file" name="moments_files[]" accept="image/*" multiple></div>
        <div class="mt-4"><label class="lbl">Text — one per line: <code>title | description</code> (matches photo order)</label><textarea class="fld" name="moments_items" rows="4"><?= h($moments_prefill) ?></textarea></div>
      </div>
    </div>

    <!-- STEP 3 · LETTER & MEDIA -->
    <div class="cbstep" data-step="1">
      <div class="sec">
        <div class="sec-title">💌 Page 3 — Love Letter <?php enable_toggle('letter_enabled', $cfg, 'letter'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="letter_label" value="<?= h(cbf($cfg,'letter','label')) ?>"></div>
          <div><label class="lbl">Title (script)</label><input class="fld" name="letter_script" value="<?= h(cbf($cfg,'letter','script')) ?>"></div>
        </div>
        <div class="mt-4"><label class="lbl">Letter (one paragraph per line)</label><textarea class="fld" name="letter_paragraphs" rows="8"><?= h($letter_prefill) ?></textarea><p class="hint">For bold, wrap in stars: <code>*My love*</code></p></div>
        <div class="grid sm:grid-cols-3 gap-4 mt-4">
          <div><label class="lbl">Signature</label><input class="fld" name="letter_signature" value="<?= h(cbf($cfg,'letter','signature')) ?>"><p class="hint"><code>[sender]</code> = auto</p></div>
          <div><label class="lbl">Font</label><select class="fld" name="letter_font"><?php foreach ($fonts as $f): ?><option value="<?= h($f) ?>" <?= cbf($cfg,'letter','font')===$f?'selected':'' ?>><?= h($f) ?></option><?php endforeach; ?></select></div>
          <div><label class="lbl">Paper Color</label><input type="color" name="letter_paper_color" value="<?= h(cbf($cfg,'letter','paper_color','#fffdf8')) ?>" class="w-full h-10 rounded-lg bg-transparent border border-pink-500/20 cursor-pointer"></div>
        </div>
        <div class="mt-4"><label class="lbl">Optional letter audio (plays on this page)</label><input class="fld" type="file" name="letter_audio" accept="audio/*"><?php if (!empty($cfg['letter']['audio_url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['letter']['audio_url'])) ?></p><?php endif; ?></div>
      </div>

      <div class="sec">
        <div class="sec-title">🎙️ Page 4 — Voice Note <?php enable_toggle('voice_enabled', $cfg, 'voice'); ?></div>
        <p class="hint mb-3">This page only appears when a voice note is uploaded.</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="voice_label" value="<?= h(cbf($cfg,'voice','label')) ?>"></div>
          <div><label class="lbl">Title (script)</label><input class="fld" name="voice_script" value="<?= h(cbf($cfg,'voice','script')) ?>"></div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4 mt-4">
          <div><label class="lbl">Subtitle</label><input class="fld" name="voice_subtitle" value="<?= h(cbf($cfg,'voice','subtitle')) ?>"></div>
          <div><label class="lbl">Speaker Name</label><input class="fld" name="voice_name" value="<?= h(cbf($cfg,'voice','name')) ?>"><p class="hint"><code>[sender]</code> = auto</p></div>
          <div><label class="lbl">Avatar Emoji</label><input class="fld" name="voice_avatar" value="<?= h(cbf($cfg,'voice','avatar')) ?>"></div>
        </div>
        <div class="mt-4"><label class="lbl">Voice Note (mp3/m4a/wav — max 10MB)</label><input class="fld" type="file" name="voice_audio" accept="audio/*"><?php if (!empty($cfg['voice']['audio_url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['voice']['audio_url'])) ?></p><?php endif; ?></div>
      </div>

      <div class="sec">
        <div class="sec-title">🎥 Page 5 — Video Message <?php enable_toggle('video_enabled', $cfg, 'video'); ?></div>
        <p class="hint mb-3">This page only appears when a video is uploaded. Portrait/landscape/square auto-detected.</p>
        <div class="grid sm:grid-cols-3 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="video_label" value="<?= h(cbf($cfg,'video','label')) ?>"></div>
          <div><label class="lbl">Title (script)</label><input class="fld" name="video_script" value="<?= h(cbf($cfg,'video','script')) ?>"></div>
          <div><label class="lbl">Subtitle</label><input class="fld" name="video_subtitle" value="<?= h(cbf($cfg,'video','subtitle')) ?>"></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4 mt-4">
          <div><label class="lbl">Video File (MP4/WEBM/MOV — max 100MB)</label><input class="fld" type="file" name="video_file" accept="video/*"><?php if (!empty($cfg['video']['url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['video']['url'])) ?></p><?php endif; ?></div>
          <div><label class="lbl">Poster image (shown before play)</label><input class="fld" type="file" name="video_poster" accept="image/*"><?php if (!empty($cfg['video']['poster'])): ?><p class="hint">✅ Current poster set</p><?php endif; ?></div>
        </div>
      </div>
    </div>

    <!-- STEP 4 · PROPOSAL -->
    <div class="cbstep" data-step="2">
      <div class="sec">
        <div class="sec-title">❤️ Page 10 — Why I Love You <?php enable_toggle('reasons_enabled', $cfg, 'reasons'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="reasons_label" value="<?= h(cbf($cfg,'reasons','label')) ?>"></div>
          <div><label class="lbl">Title (script)</label><input class="fld" name="reasons_script" value="<?= h(cbf($cfg,'reasons','script')) ?>"></div>
        </div>
        <div class="mt-4"><label class="lbl">Reasons — one per line (each becomes a flip card)</label><textarea class="fld" name="reasons_items" rows="5"><?= h($reasons_prefill) ?></textarea></div>
      </div>
      <div class="sec">
        <div class="sec-title">🌍 Page 11 — Our Future <?php enable_toggle('future_enabled', $cfg, 'future'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="future_label" value="<?= h(cbf($cfg,'future','label')) ?>"></div>
          <div><label class="lbl">Title (script)</label><input class="fld" name="future_script" value="<?= h(cbf($cfg,'future','script')) ?>"></div>
        </div>
        <div class="mt-4"><label class="lbl">Dreams — one per line: <code>icon | text</code></label><textarea class="fld" name="future_items" rows="4"><?= h($future_prefill) ?></textarea></div>
      </div>
      <div class="sec">
        <div class="sec-title">💍 Page 12 — Ring Reveal <?php enable_toggle('ring_enabled', $cfg, 'ring'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Label</label><input class="fld" name="ring_label" value="<?= h(cbf($cfg,'ring','label')) ?>"></div>
          <div><label class="lbl">Title (script)</label><input class="fld" name="ring_script" value="<?= h(cbf($cfg,'ring','script')) ?>"></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4 mt-4">
          <div><label class="lbl">Text (below the box)</label><input class="fld" name="ring_text" value="<?= h(cbf($cfg,'ring','text')) ?>"></div>
          <div><label class="lbl">Ring Emoji</label><input class="fld" name="ring_emoji" value="<?= h(cbf($cfg,'ring','ring_emoji','💍')) ?>"></div>
        </div>
      </div>
      <div class="sec">
        <div class="sec-title">🌹 Page 6 — Proposal Scene <?php enable_toggle('scene_enabled', $cfg, 'scene'); ?></div>
        <p class="hint mb-3">Silhouette walks in, a rose grows, they tap it to bloom, then kneel.</p>
        <div><label class="lbl">Line 1</label><input class="fld" name="scene_line1" value="<?= h(cbf($cfg,'scene','line1')) ?>"></div>
        <div class="mt-4"><label class="lbl">Line 2</label><input class="fld" name="scene_line2" value="<?= h(cbf($cfg,'scene','line2')) ?>"></div>
        <div class="mt-4"><label class="lbl">Button Text</label><input class="fld" name="scene_button" value="<?= h(cbf($cfg,'scene','button')) ?>"></div>
      </div>

      <div class="sec">
        <div class="sec-title">💍 Page 7 — The Big Question <?php enable_toggle('final_enabled', $cfg, 'final'); ?></div>
        <div><label class="lbl">Question</label><input class="fld" name="final_question" value="<?= h(cbf($cfg,'final','question')) ?>"></div>
        <div class="grid sm:grid-cols-2 gap-4 mt-4">
          <div><label class="lbl">YES Button</label><input class="fld" name="final_yes" value="<?= h(cbf($cfg,'final','yes')) ?>"></div>
          <div><label class="lbl">NO Button</label><input class="fld" name="final_no" value="<?= h(cbf($cfg,'final','no')) ?>"></div>
        </div>
        <label class="tgl" style="margin-left:0; margin-top:12px;"><input type="checkbox" name="final_funny_no" value="1" <?= !empty($cfg['final']['funny_no']) ? 'checked' : '' ?> class="w-4 h-4 accent-pink-500"> Playful "No" (runs away, shakes, dodges — only YES works)</label>
      </div>
    </div>

    <!-- STEP 4 · END & MUSIC -->
    <div class="cbstep" data-step="3">
      <div class="sec">
        <div class="sec-title">🎉 Page 8 — Celebration <?php enable_toggle('celebration_enabled', $cfg, 'celebration'); ?></div>
        <div><label class="lbl">Celebration Message</label><textarea class="fld" name="celebration_message" rows="3"><?= h(cbf($cfg,'celebration','message')) ?></textarea></div>
      </div>

      <div class="sec">
        <div class="sec-title">💬 Reply Box <?php enable_toggle('reply_enabled', $cfg, 'reply'); ?></div>
        <p class="hint mb-3">The recipient can send back text / voice / photo / video. You'll see it in Dashboard → Replies.</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="lbl">Heading</label><input class="fld" name="reply_heading" value="<?= h(cbf($cfg,'reply','heading')) ?>"></div>
          <div><label class="lbl">Subtitle</label><input class="fld" name="reply_subtitle" value="<?= h(cbf($cfg,'reply','subtitle')) ?>"></div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4 mt-4">
          <div><label class="lbl">Placeholder</label><input class="fld" name="reply_placeholder" value="<?= h(cbf($cfg,'reply','placeholder')) ?>"></div>
          <div><label class="lbl">Button</label><input class="fld" name="reply_button" value="<?= h(cbf($cfg,'reply','button')) ?>"></div>
          <div><label class="lbl">Success Message</label><input class="fld" name="reply_success" value="<?= h(cbf($cfg,'reply','success')) ?>"></div>
        </div>
      </div>

      <div class="sec">
        <div class="sec-title">🎵 Background Music</div>
        <?php if (!empty($music_library)): ?>
        <?php $cur_music = $cfg['music']['audio_url'] ?? ''; ?>
        <div class="mb-4">
          <label class="lbl">Choose from the music library (recommended) 🎶</label>
          <select class="fld" name="music_library_url" id="musicLibSelect" onchange="cbLibChanged()">
            <option value="">— None / upload your own —</option>
            <?php foreach ($music_library as $trk): ?>
            <option value="<?= h($trk['file_path']) ?>" data-title="<?= h($trk['title']) ?>" <?= ($cur_music === $trk['file_path']) ? 'selected' : '' ?>><?= $trk['category'] === 'premium' ? '✨ ' : '' ?><?= h($trk['title']) ?><?= $trk['category'] !== 'premium' ? ' (' . h($trk['category']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <div class="flex items-center gap-2 mt-2"><button type="button" class="btn-preview" style="padding:8px 16px; font-size:0.8rem;" onclick="cbToggleLibPreview()">▶ Preview</button><span class="hint" id="libPrevStatus"></span></div>
          <audio id="libPreviewAudio" preload="none"></audio>
        </div>
        <?php endif; ?>
        <div><label class="lbl">Track Title (shown in player)</label><input class="fld" name="music_title" value="<?= h(cbf($cfg,'music','title')) ?>"></div>
        <div class="mt-4"><label class="lbl">Or upload your own music file (mp3 — max 20MB)</label><input class="fld" type="file" name="music_audio" accept="audio/*"></div>
      </div>
    </div>

    <!-- STEP 6 · EXTRAS (optional pages, off by default) -->
    <div class="cbstep" data-step="5">
      <p class="hint mb-3" style="font-size:.82rem;">Optional bonus pages — turn on only the ones you want. Each slots into the story automatically.</p>

      <div class="sec">
        <div class="sec-title">🖼️ Couple Photo Wall <?php enable_toggle('photo_wall_enabled', $cfg, 'photo_wall'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="photo_wall_label" value="<?= h(cbf($cfg,'photo_wall','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="photo_wall_script" value="<?= h(cbf($cfg,'photo_wall','script')) ?>"></div></div>
        <?php if ($wall_existing): ?><div class="mt-3"><label class="lbl">Current (<?= count($wall_existing) ?>/12)</label><div class="grid grid-cols-6 gap-2"><?php foreach ($wall_existing as $im): ?><div class="aspect-square rounded-lg overflow-hidden border border-pink-500/20"><img src="<?= h($thumbs($im)) ?>" class="w-full h-full object-cover"></div><?php endforeach; ?></div><label class="flex items-center gap-2 mt-2 text-xs text-red-300"><input type="checkbox" name="photo_wall_clear" value="1" class="accent-red-500"> Remove all</label></div><?php endif; ?>
        <div class="mt-4"><label class="lbl">Add photos (up to 12)</label><input class="fld" type="file" name="photo_wall_files[]" accept="image/*" multiple></div>
      </div>

      <div class="sec">
        <div class="sec-title">💗 Heart Formation <?php enable_toggle('heart_enabled', $cfg, 'heart'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="heart_label" value="<?= h(cbf($cfg,'heart','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="heart_script" value="<?= h(cbf($cfg,'heart','script')) ?>"></div></div>
        <div class="mt-4"><label class="lbl">Subtitle</label><input class="fld" name="heart_subtitle" value="<?= h(cbf($cfg,'heart','subtitle')) ?>"></div>
        <?php if ($heart_existing): ?><div class="mt-3"><label class="lbl">Current (<?= count($heart_existing) ?>/12)</label><div class="grid grid-cols-6 gap-2"><?php foreach ($heart_existing as $im): ?><div class="aspect-square rounded-lg overflow-hidden border border-pink-500/20"><img src="<?= h($thumbs($im)) ?>" class="w-full h-full object-cover"></div><?php endforeach; ?></div><label class="flex items-center gap-2 mt-2 text-xs text-red-300"><input type="checkbox" name="heart_clear" value="1" class="accent-red-500"> Remove all</label></div><?php endif; ?>
        <div class="mt-4"><label class="lbl">Photos that form the heart (up to 12)</label><input class="fld" type="file" name="heart_files[]" accept="image/*" multiple></div>
      </div>

      <div class="sec">
        <div class="sec-title">🪙 Scratch Card <?php enable_toggle('scratch_enabled', $cfg, 'scratch'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="scratch_label" value="<?= h(cbf($cfg,'scratch','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="scratch_script" value="<?= h(cbf($cfg,'scratch','script')) ?>"></div></div>
        <div class="grid sm:grid-cols-2 gap-4 mt-4"><div><label class="lbl">Cover text</label><input class="fld" name="scratch_cover" value="<?= h(cbf($cfg,'scratch','cover')) ?>"></div><div><label class="lbl">Hidden message</label><input class="fld" name="scratch_message" value="<?= h(cbf($cfg,'scratch','message')) ?>"></div></div>
      </div>

      <div class="sec">
        <div class="sec-title">🔐 Secret Password <?php enable_toggle('secret_pw_enabled', $cfg, 'secret_pw'); ?></div>
        <p class="hint mb-2">Only shows if an answer is set. (In-story fun gate, not page security.)</p>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="secret_pw_label" value="<?= h(cbf($cfg,'secret_pw','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="secret_pw_script" value="<?= h(cbf($cfg,'secret_pw','script')) ?>"></div></div>
        <div class="grid sm:grid-cols-3 gap-4 mt-4"><div><label class="lbl">Hint</label><input class="fld" name="secret_pw_hint" value="<?= h(cbf($cfg,'secret_pw','hint')) ?>"></div><div><label class="lbl">Answer</label><input class="fld" name="secret_pw_password" value="<?= h(cbf($cfg,'secret_pw','password')) ?>"></div><div><label class="lbl">Success</label><input class="fld" name="secret_pw_success" value="<?= h(cbf($cfg,'secret_pw','success')) ?>"></div></div>
      </div>

      <div class="sec">
        <div class="sec-title">🧩 Puzzle <?php enable_toggle('puzzle_enabled', $cfg, 'puzzle'); ?></div>
        <p class="hint mb-2">Splits your photo into 4 tiles to rearrange. Needs a photo.</p>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="puzzle_label" value="<?= h(cbf($cfg,'puzzle','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="puzzle_script" value="<?= h(cbf($cfg,'puzzle','script')) ?>"></div></div>
        <div class="mt-4"><label class="lbl">Success message</label><input class="fld" name="puzzle_success" value="<?= h(cbf($cfg,'puzzle','success')) ?>"></div>
        <div class="mt-4"><label class="lbl">Puzzle photo</label><input class="fld" type="file" name="puzzle_img" accept="image/*"><?php if (!empty($cfg['puzzle']['image'])): ?><p class="hint">✅ Current photo set</p><?php endif; ?></div>
      </div>

      <div class="sec">
        <div class="sec-title">🧠 Memory Quiz <?php enable_toggle('quiz_enabled', $cfg, 'quiz'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="quiz_label" value="<?= h(cbf($cfg,'quiz','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="quiz_script" value="<?= h(cbf($cfg,'quiz','script')) ?>"></div></div>
        <?php $qslots = max(2, count($quiz_qs)); for ($qi = 0; $qi < $qslots; $qi++): $qd = $quiz_qs[$qi] ?? null; ?>
        <div style="background:rgba(0,0,0,.18);border:1px solid rgba(255,150,170,.12);border-radius:12px;padding:12px;margin-top:12px;">
          <div><label class="lbl">Question <?= $qi + 1 ?></label><input class="fld" name="quiz_q[]" value="<?= h($qd['q'] ?? '') ?>"></div>
          <div class="grid sm:grid-cols-2 gap-3 mt-3"><div><label class="lbl">Options (one per line)</label><textarea class="fld" name="quiz_opts[]" rows="3"><?= h($qd ? implode("\n", $qd['options'] ?? []) : '') ?></textarea></div><div><label class="lbl">Correct option # (1,2,3…)</label><input class="fld" name="quiz_correct[]" type="number" min="1" value="<?= (int)($qd['correct'] ?? 0) + 1 ?>"></div></div>
        </div>
        <?php endfor; ?>
      </div>

      <div class="sec">
        <div class="sec-title">💘 Love Meter <?php enable_toggle('love_meter_enabled', $cfg, 'love_meter'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="love_meter_label" value="<?= h(cbf($cfg,'love_meter','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="love_meter_script" value="<?= h(cbf($cfg,'love_meter','script')) ?>"></div></div>
        <div class="grid sm:grid-cols-2 gap-4 mt-4"><div><label class="lbl">Percent (0–100)</label><input class="fld" type="number" min="0" max="100" name="love_meter_percent" value="<?= (int)cbf($cfg,'love_meter','percent',100) ?>"></div><div><label class="lbl">Caption</label><input class="fld" name="love_meter_caption" value="<?= h(cbf($cfg,'love_meter','caption')) ?>"></div></div>
      </div>

      <div class="sec">
        <div class="sec-title">⏳ Countdown <?php enable_toggle('countdown_enabled', $cfg, 'countdown'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="countdown_label" value="<?= h(cbf($cfg,'countdown','label')) ?>"></div><div><label class="lbl">Message (after 3·2·1)</label><input class="fld" name="countdown_message" value="<?= h(cbf($cfg,'countdown','message')) ?>"></div></div>
      </div>

      <div class="sec">
        <div class="sec-title">📅 Love Calendar <?php enable_toggle('calendar_enabled', $cfg, 'calendar'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="calendar_label" value="<?= h(cbf($cfg,'calendar','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="calendar_script" value="<?= h(cbf($cfg,'calendar','script')) ?>"></div></div>
        <div class="mt-4"><label class="lbl">Dates — one per line: <code>date | what happened</code></label><textarea class="fld" name="calendar_items" rows="4"><?= h($calendar_prefill) ?></textarea></div>
      </div>

      <div class="sec">
        <div class="sec-title">📍 Destination Reveal <?php enable_toggle('destination_enabled', $cfg, 'destination'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="destination_label" value="<?= h(cbf($cfg,'destination','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="destination_script" value="<?= h(cbf($cfg,'destination','script')) ?>"></div></div>
        <div class="grid sm:grid-cols-2 gap-4 mt-4"><div><label class="lbl">Place name</label><input class="fld" name="destination_place" value="<?= h(cbf($cfg,'destination','place')) ?>"></div><div><label class="lbl">Address / details</label><input class="fld" name="destination_address" value="<?= h(cbf($cfg,'destination','address')) ?>"></div></div>
      </div>

      <div class="sec">
        <div class="sec-title">🤍 Final Thank You <?php enable_toggle('thankyou_enabled', $cfg, 'thankyou'); ?></div>
        <div class="grid sm:grid-cols-2 gap-4"><div><label class="lbl">Label</label><input class="fld" name="thankyou_label" value="<?= h(cbf($cfg,'thankyou','label')) ?>"></div><div><label class="lbl">Title</label><input class="fld" name="thankyou_script" value="<?= h(cbf($cfg,'thankyou','script')) ?>"></div></div>
        <div class="mt-4"><label class="lbl">Message</label><textarea class="fld" name="thankyou_message" rows="3"><?= h(cbf($cfg,'thankyou','message')) ?></textarea></div>
        <div class="mt-4"><label class="lbl">Signature</label><input class="fld" name="thankyou_signature" value="<?= h(cbf($cfg,'thankyou','signature')) ?>"></div>
      </div>
    </div>

    <div class="cbfooter">
      <button type="button" class="btn-nav" id="btnPrev" onclick="stepMove(-1)" style="display:none;">← Back</button>
      <button type="submit" class="btn-preview" id="btnPreview" onclick="cbOpenPreviewLoader();" formtarget="cbPreviewWin" style="display:none;">👁️ Preview</button>
      <button type="button" class="btn-nav" id="btnNext" onclick="stepMove(1)">Next →</button>
      <button type="submit" class="btn-rose" id="btnPublish" onclick="document.getElementById('doField').value='publish';" style="display:none;"><?= $is_admin_demo ? '💾 Save Demo' : ($editing ? '💾 Save Changes' : '🎁 Publish') ?></button>
    </div>
    <p class="hint text-center pb-6"><?= $editing ? 'Changes save instantly to your live page.' : 'Publishing uses 1 free page or 1 credit.' ?> &nbsp;·&nbsp; 👁️ Preview opens in a new tab; the editor stays here.</p>
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

// "Generating your page" overlay so publish never feels like a hang (uploads can take a while)
document.getElementById('cbForm').addEventListener('submit', function () {
  if (document.getElementById('doField').value !== 'publish') return; // preview opens a new tab
  const o = document.createElement('div');
  o.id = 'genOverlay';
  o.style.cssText = 'position:fixed;inset:0;z-index:5000;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(18,3,11,0.96);backdrop-filter:blur(8px);text-align:center;padding:20px;';
  o.innerHTML = '<div style="font-size:3.4rem;animation:genBob 1.2s ease-in-out infinite;">💌</div>'
    + '<div style="font-family:Outfit,sans-serif;font-weight:800;font-size:1.4rem;color:#ffb3c1;margin-top:14px;">Generating your page…</div>'
    + '<div style="color:rgba(255,200,210,0.6);font-size:0.85rem;margin-top:6px;" id="genStatus">Uploading your photos, music &amp; video ✨</div>'
    + '<div style="width:190px;height:5px;background:rgba(255,255,255,0.12);border-radius:4px;margin-top:20px;overflow:hidden;"><div style="height:100%;width:40%;border-radius:4px;background:linear-gradient(90deg,#e8405a,#ff8fa3);animation:genSlide 1.2s ease-in-out infinite;"></div></div>'
    + '<div style="color:rgba(255,200,210,0.4);font-size:0.75rem;margin-top:16px;">Please don\'t close this tab 💕</div>'
    + '<style>@keyframes genBob{0%,100%{transform:translateY(0) rotate(-4deg);}50%{transform:translateY(-10px) rotate(4deg);}}@keyframes genSlide{0%{margin-left:-40%;}100%{margin-left:100%;}}</style>';
  document.body.appendChild(o);
  const msgs = ['Uploading your photos, music & video ✨', 'Building your cinematic pages 🎬', 'Adding animations & sparkles 🌹', 'Almost there… 💍'];
  let mi = 0; setInterval(() => { mi = (mi + 1) % msgs.length; const s = document.getElementById('genStatus'); if (s) s.textContent = msgs[mi]; }, 2600);
  document.getElementById('btnPublish').disabled = true;
});

const CB_MEDIA_BASE = <?= json_encode(rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/', JSON_UNESCAPED_SLASHES) ?>;
function cbLibChanged() { const sel=document.getElementById('musicLibSelect'), a=document.getElementById('libPreviewAudio'), st=document.getElementById('libPrevStatus'); if(a)a.pause(); if(st)st.textContent=''; if(sel&&sel.value){ const t=sel.options[sel.selectedIndex].getAttribute('data-title'); const mt=document.querySelector('[name=music_title]'); if(t&&mt&&(!mt.value.trim()||mt.classList.contains('ghost'))){ mt.value=t; mt.classList.remove('ghost'); } } }
function cbToggleLibPreview() { const sel=document.getElementById('musicLibSelect'), a=document.getElementById('libPreviewAudio'), st=document.getElementById('libPrevStatus'); if(!sel||!sel.value){ if(st)st.textContent='Please choose a track first 🙂'; return; } if(a.src.indexOf(sel.value)===-1) a.src=CB_MEDIA_BASE+sel.value; if(a.paused){ a.play().then(()=>{st.textContent='▶ Playing preview…';}).catch(()=>{st.textContent='Preview failed to load';}); } else { a.pause(); st.textContent='⏸ Paused'; } }

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

function showGeneratingOverlay() {
  const overlay = document.getElementById('generating-overlay');
  if (overlay) {
    overlay.classList.remove('pointer-events-none');
    overlay.classList.add('opacity-100');
  }
  
  const statuses = [
    'Creating Your Premium Proposal Page... 💖',
    'Brewing the love potions... 🧪✨',
    'Designing your movie-style proposal scene... 💍',
    'Optimizing photos and memories... 📸',
    'Preparing background music & audio notes... 🎵',
    'Polishing micro-animations... ✨',
    'Generating secure shareable link... 🔗',
    'Sealing with a digital kiss... 💋'
  ];
  
  let currentStatusIdx = 0;
  const statusText = document.getElementById('generating-status');
  const progressBar = document.getElementById('generating-progress');
  
  let progress = 0;
  const progressInterval = setInterval(() => {
    progress += Math.random() * 8 + 2;
    if (progress >= 95) {
      progress = 95;
      clearInterval(progressInterval);
    }
    if (progressBar) progressBar.style.width = `${progress}%`;
  }, 200);
  
  const statusInterval = setInterval(() => {
    currentStatusIdx = (currentStatusIdx + 1) % statuses.length;
    if (statusText) statusText.textContent = statuses[currentStatusIdx];
  }, 1000);
}

const cbForm = document.getElementById('cbForm');
if (cbForm) {
  cbForm.addEventListener('submit', function (e) {
    const doVal = document.getElementById('doField').value;
    if (doVal === 'publish') {
      showGeneratingOverlay();
    }
  });
}
</script>

<!-- Premium fullscreen loading overlay -->
<div id="generating-overlay" class="fixed inset-0 bg-slate-950/90 z-50 flex flex-col items-center justify-center backdrop-blur-md opacity-0 pointer-events-none transition-opacity duration-500">
    <div class="relative w-64 h-64 flex items-center justify-center mb-8">
        <!-- Glowing rings -->
        <div class="absolute inset-0 rounded-full border-4 border-t-pink-500 border-r-transparent border-b-violet-500 border-l-transparent animate-spin duration-1000"></div>
        <div class="absolute inset-4 rounded-full border-4 border-t-transparent border-r-purple-500 border-b-transparent border-l-cyan-400 animate-spin duration-700 reverse"></div>
        <div class="absolute inset-8 rounded-full border-2 border-dashed border-pink-400/30 animate-pulse"></div>
        <!-- Center icon -->
        <div class="text-4xl animate-bounce">💍</div>
    </div>
    <!-- Generating status and progress -->
    <h3 class="text-xl font-bold text-white mb-2 tracking-wide text-center">Creating Your Premium Magic Page...</h3>
    <p id="generating-status" class="text-sm text-pink-400 font-medium h-6 animate-pulse text-center">Injecting romance and memories...</p>
    
    <!-- Progress bar -->
    <div class="w-64 h-1.5 bg-slate-800 rounded-full overflow-hidden mt-6 shadow-inner">
        <div id="generating-progress" class="h-full bg-gradient-to-r from-pink-500 via-purple-500 to-cyan-400 w-0 transition-all duration-300 ease-out"></div>
    </div>
    <p class="text-[10px] text-slate-400 font-bold mt-4 text-center tracking-wider uppercase animate-pulse">⚠️ Please do not press back or refresh. Be patient...</p>
</div>

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
<?php render_tutorial_button('proposal'); ?>
</body>
</html>
