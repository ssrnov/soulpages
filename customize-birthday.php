<?php
// =========================================================================
// customize-birthday.php — Dedicated editor for the "Cinematic Birthday"
// standalone premium template. Every element is customizable here.
// New page:   customize-birthday.php
// Edit page:  customize-birthday.php?slug=<existing-slug>  (owner only)
// =========================================================================
require_once 'includes/functions.php';
require_once __DIR__ . '/templates/cinematic_birthday.php';

// --- Premium demo storage key (admin curates one demo per template) ---
$demo_key = 'cinematic_birthday';
$demo_setting_key = 'premium_demo_' . $demo_key;

// --- Public live demo/preview (no login) ---
// premium.php ka "Live Preview" isse hit karta hai. Admin ka saved demo dikhata
// hai (agar admin ne set nahi kiya to default sample). Kuch save nahi hota.
if (isset($_GET['demo'])) {
    $demo_cfg = json_decode(get_setting($demo_setting_key, ''), true);
    if (!is_array($demo_cfg) || empty($demo_cfg)) $demo_cfg = cinematic_birthday_defaults();
    $demo_page = [
        'id'               => 0,
        'user_id'          => null,
        'sender_name'      => $demo_cfg['_sender'] ?? get_example('sender'),
        'receiver_name'    => $demo_cfg['landing']['name'] ?? get_example('receiver'),
        'slide_data'       => json_encode($demo_cfg),
        'template'         => 'cinematic_birthday',
        'status'           => 'published',
        'password'         => null,
        'expiry_date'      => null,
        'is_expired'       => 0,
        'guest_session_id' => null,
    ];
    render_cinematic_birthday($demo_page);
    exit;
}

// --- Admin demo edit mode (from the admin panel) ---
$admin_demo   = trim($_GET['admin_demo'] ?? ($_POST['admin_demo'] ?? ''));
$is_admin_demo = ($admin_demo !== '');

// --- Auth ---
if (!is_logged_in()) {
    $_SESSION['login_error'] = 'Please log in to create your birthday surprise.';
    redirect('login.php');
}
$user_id = $_SESSION['user_id'];
$user = get_user_profile($user_id);
if ($is_admin_demo && !is_admin()) {
    redirect('dashboard.php');
}
if (!$is_admin_demo && $user && isset($user['email_verified']) && (int)$user['email_verified'] === 0) {
    redirect('verification-pending.php');
}

$defaults = cinematic_birthday_defaults();
$error = '';
$editing = false;
$page = null;

// --- Load existing page for editing (skipped in admin-demo mode) ---
$slug = trim($_GET['slug'] ?? ($_POST['edit_slug'] ?? ''));
if (!$is_admin_demo && !empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND template = 'cinematic_birthday'");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    if ($page && ($page['user_id'] == $user_id || is_admin())) {
        $editing = true;
    } else {
        $page = null; // not owner / not found -> treat as new
    }
}

// New premium pages cost 5 credits (admin demo is free)
if (!$editing && !$is_admin_demo && !can_create_page($user_id, PREMIUM_CREDIT_COST)) {
    redirect('payment.php');
}

// Config used to prefill the form (merged over defaults)
if ($is_admin_demo) {
    $cfg = cinematic_birthday_config(['slide_data' => get_setting($demo_setting_key, '')]);
} else {
    $cfg = $page ? cinematic_birthday_config($page) : $defaults;
}

// Small prefill helper
function cbf($cfg, $sec, $key, $def = '') {
    return $cfg[$sec][$key] ?? $def;
}

// =========================================================================
// POST — build config, upload media, save
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(300); @ignore_user_abort(true);
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $error = 'Uploaded files are too large for the server limit (' . ini_get('post_max_size') . '). Try smaller audio files.';
    } elseif (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Please try submitting again.';
    } else {
        $t = function ($k, $d = '') { return trim($_POST[$k] ?? $d); };

        // ---- Build the config ----
        $config = $defaults;

        // Colors
        foreach (['rose','blush','cream','deep','gold','soft','text'] as $ck) {
            $val = $t('color_' . $ck);
            if ($val !== '') $config['colors'][$ck] = $val;
        }
        $config['page_title'] = $t('page_title', $defaults['page_title']);

        // Landing
        $config['landing'] = [
            'emoji'    => $t('landing_emoji', '🌹'),
            'name'     => $t('landing_name', 'My Love'),
            'script'   => $t('landing_script'),
            'subtitle' => $t('landing_subtitle'),
            'btn'      => $t('landing_btn', 'Open Your Surprise 🎁'),
            'hint'     => $t('landing_hint'),
        ];

        // Intro
        $config['intro'] = [
            'label'     => $t('intro_label'),
            'script'    => $t('intro_script'),
            'body'      => $t('intro_body'),
            'signature' => $t('intro_signature'),
        ];

        // Memories (repeating rows)
        $mem_items = [];
        $mem_dot   = $_POST['mem_dot']   ?? [];
        $mem_date  = $_POST['mem_date']  ?? [];
        $mem_title = $_POST['mem_title'] ?? [];
        $mem_chat  = $_POST['mem_chat']  ?? [];
        $mem_note  = $_POST['mem_note']  ?? [];
        for ($i = 0; $i < count($mem_title); $i++) {
            $d = trim($mem_date[$i] ?? '');
            $ti = trim($mem_title[$i] ?? '');
            $n = trim($mem_note[$i] ?? '');
            if ($d === '' && $ti === '' && $n === '') continue;
            $mem_items[] = [
                'dot'   => trim($mem_dot[$i] ?? '✨') ?: '✨',
                'date'  => $d,
                'title' => $ti,
                'chat'  => trim($mem_chat[$i] ?? ''),
                'note'  => $n,
            ];
        }
        $config['memories'] = [
            'label'  => $t('memories_label'),
            'script' => $t('memories_script'),
            'items'  => !empty($mem_items) ? $mem_items : $defaults['memories']['items'],
        ];

        // Chats (repeating scenes; messages entered one per line, "me: ..." = sent)
        $scenes = [];
        $chat_tab   = $_POST['chat_tab']   ?? [];
        $chat_label = $_POST['chat_label'] ?? [];
        $chat_msgs  = $_POST['chat_msgs']  ?? [];
        for ($i = 0; $i < count($chat_tab); $i++) {
            $tab = trim($chat_tab[$i] ?? '');
            $raw_msgs = trim($chat_msgs[$i] ?? '');
            if ($tab === '' && $raw_msgs === '') continue;
            $messages = [];
            foreach (preg_split('/\r\n|\r|\n/', $raw_msgs) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (preg_match('/^(me|sent|you)\s*:\s*(.*)$/i', $line, $mm)) {
                    $messages[] = ['side' => 'sent', 'text' => trim($mm[2])];
                } elseif (preg_match('/^(her|him|received|them)\s*:\s*(.*)$/i', $line, $mm)) {
                    $messages[] = ['side' => 'received', 'text' => trim($mm[2])];
                } else {
                    $messages[] = ['side' => 'received', 'text' => $line];
                }
            }
            $scenes[] = [
                'tab'      => $tab ?: ('Chat ' . ($i + 1)),
                'label'    => trim($chat_label[$i] ?? ''),
                'messages' => $messages,
            ];
        }
        $config['chats'] = [
            'label'  => $t('chats_label'),
            'script' => $t('chats_script'),
            'scenes' => !empty($scenes) ? $scenes : $defaults['chats']['scenes'],
        ];

        // Voice
        $config['voice'] = [
            'label'      => $t('voice_label'),
            'script'     => $t('voice_script'),
            'subtitle'   => $t('voice_subtitle'),
            'name'       => $t('voice_name'),
            'avatar'     => $t('voice_avatar', '☀️'),
            'transcript' => $t('voice_transcript'),
            'audio_url'  => $t('existing_voice_audio'),
        ];

        // Letter (paragraphs one per line)
        $letter_paras = [];
        foreach (preg_split('/\r\n|\r|\n/', $_POST['letter_paragraphs'] ?? '') as $line) {
            $line = rtrim($line);
            if (trim($line) !== '') $letter_paras[] = $line;
        }
        $config['letter'] = [
            'label'      => $t('letter_label'),
            'script'     => $t('letter_script'),
            'audio_url'  => $t('existing_letter_audio'),
            'paragraphs' => !empty($letter_paras) ? $letter_paras : $defaults['letter']['paragraphs'],
        ];

        // Puzzle (answers & hints one per line)
        $answers = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['puzzle_answers'] ?? '')), 'strlen'));
        $hints   = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['puzzle_hints'] ?? '')), 'strlen'));
        $config['puzzle'] = [
            'label'       => $t('puzzle_label'),
            'script'      => $t('puzzle_script'),
            'question'    => $t('puzzle_question'),
            'placeholder' => $t('puzzle_placeholder', 'Type the date…'),
            'answers'     => !empty($answers) ? $answers : $defaults['puzzle']['answers'],
            'hints'       => $hints,
            'success'     => $t('puzzle_success', '✅ You remembered! ❤️'),
        ];

        // Secret
        $config['secret'] = [
            'label'      => $t('secret_label'),
            'script'     => $t('secret_script'),
            'question'   => $t('secret_question'),
            'name'       => $t('secret_name'),
            'reveal_sub' => $t('secret_reveal_sub'),
            'quote'      => $t('secret_quote'),
        ];

        // Proposal
        $config['proposal'] = [
            'label'  => $t('proposal_label'),
            'script' => $t('proposal_script'),
            'body'   => $t('proposal_body'),
            'yes'    => $t('proposal_yes', 'YES ❤️'),
            'always' => $t('proposal_always', 'ALWAYS ❤️'),
        ];

        // Ending
        $config['ending'] = [
            'title'     => $t('ending_title'),
            'body'      => $t('ending_body'),
            'signature' => $t('ending_signature'),
        ];

        // Music
        $config['music'] = [
            'title'     => $t('music_title', 'Background Music'),
            'audio_url' => $t('existing_music_audio'),
        ];
        // Music picked from the admin library (only accept safe asset paths).
        // A newly uploaded file (handled later) will still take precedence.
        $lib_music = trim($_POST['music_library_url'] ?? '');
        if ($lib_music !== '' && strpos($lib_music, 'assets/music/') === 0 && strpos($lib_music, '..') === false) {
            $config['music']['audio_url'] = $lib_music;
        }

        // Reply box
        $config['reply'] = [
            'enabled'     => isset($_POST['reply_enabled']),
            'heading'     => $t('reply_heading', 'Reply to me 💌'),
            'subtitle'    => $t('reply_subtitle'),
            'placeholder' => $t('reply_placeholder', 'Write your reply here…'),
            'button'      => $t('reply_button', 'Send Reply 💖'),
            'success'     => $t('reply_success', 'Sent! Thank you ❤️'),
        ];

        // ---- Video / Album / Cake config (media defaults to existing; uploads applied below) ----
        $config['video'] = [
            'enabled'  => isset($_POST['video_enabled']),
            'label'    => $t('video_label', 'Press play'),
            'script'   => $t('video_script', 'A Special Video 🎬'),
            'subtitle' => $t('video_subtitle'),
            'url'      => $t('existing_video'),
        ];
        $existing_album = json_decode($_POST['existing_album'] ?? '[]', true);
        if (!is_array($existing_album)) $existing_album = [];
        if (isset($_POST['album_clear'])) $existing_album = [];
        $config['album'] = [
            'enabled'  => isset($_POST['album_enabled']),
            'label'    => $t('album_label', 'Our beautiful moments'),
            'script'   => $t('album_script', 'Photo Album 📸'),
            'subtitle' => $t('album_subtitle'),
            'images'   => array_slice(array_values($existing_album), 0, 6),
        ];
        $config['cake'] = [
            'enabled'  => isset($_POST['cake_enabled']),
            'label'    => $t('cake_label', 'Make a wish first'),
            'script'   => $t('cake_script', 'Cut the Cake 🎂'),
            'subtitle' => $t('cake_subtitle', 'Tap the cake to cut it!'),
            'message'  => $t('cake_message', 'Happy Birthday [receiver]! 🎉'),
            'button'   => $t('cake_button', 'Cut the Cake 🔪'),
        ];

        // ---- Meta / required columns ----
        $receiver = $t('landing_name', 'My Love') ?: 'My Love';
        $sender   = $t('sender_name', 'Me') ?: 'Me';
        $title    = $t('page_title') ?: ($receiver . "'s Birthday Surprise 🎂");
        $letter_text = trim(strip_tags(implode("\n", $config['letter']['paragraphs']))) ?: 'A birthday surprise.';
        $accent   = $config['colors']['rose'] ?? '#e8405a';
        $proposal_q = strip_tags($config['proposal']['body'] ?? 'Forever?');
        $page_password = $t('page_password');

        // ---- PREVIEW: render live without saving or uploading (opens in a new tab) ----
        if (($_POST['do'] ?? '') === 'preview') {
            $preview_page = [
                'id'               => $editing ? ($page['id'] ?? 0) : 0,
                'user_id'          => $user_id,
                'sender_name'      => $sender,
                'receiver_name'    => $receiver,
                'slide_data'       => json_encode($config, JSON_UNESCAPED_UNICODE),
                'template'         => 'cinematic_birthday',
                'guest_session_id' => session_id(),
                'expiry_date'      => null,
                'is_expired'       => 0,
                'password'         => null,
                '_preview'         => true,
            ];
            render_cinematic_birthday($preview_page);
            exit;
        }

        // ---- Media uploads (override existing when a new file is provided) ----
        $warnings = [];
        $do_upload = function ($field, $dir, $max_mb = 10) use (&$warnings) {
            if (empty($_FILES[$field]['name'])) return null;
            if ($_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
            if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                $warnings[] = "Upload error on {$field} (code {$_FILES[$field]['error']}) — file may be too large for the server.";
                return null;
            }
            $res = upload_voice($_FILES[$field], $dir, $max_mb);
            if ($res['success']) return $res['path'];
            $warnings[] = "Audio for {$field} failed: " . $res['message'];
            return null;
        };
        if ($p = $do_upload('voice_audio', 'uploads/voice/'))       $config['voice']['audio_url']  = $p;
        if ($p = $do_upload('letter_audio', 'uploads/voice/'))      $config['letter']['audio_url'] = $p;
        if ($p = $do_upload('music_audio', 'uploads/music/', 20))    $config['music']['audio_url']  = $p;

        // Video upload
        if (!empty($_FILES['video_file']['name'])) {
            if ($_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                $vres = upload_video($_FILES['video_file']);
                if ($vres['success']) $config['video']['url'] = $vres['path'];
                else $warnings[] = 'Video: ' . $vres['message'];
            } elseif ($_FILES['video_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $warnings[] = 'Video upload error (code ' . $_FILES['video_file']['error'] . ') — file may exceed the server limit.';
            }
        }

        // Album uploads (append to existing, cap 6)
        $album_imgs = $config['album']['images'];
        if (!empty($_FILES['album_files']['name'][0])) {
            $cnt = count($_FILES['album_files']['name']);
            for ($i = 0; $i < $cnt && count($album_imgs) < 6; $i++) {
                if ($_FILES['album_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $single = [
                    'name'     => $_FILES['album_files']['name'][$i],
                    'type'     => $_FILES['album_files']['type'][$i],
                    'tmp_name' => $_FILES['album_files']['tmp_name'][$i],
                    'error'    => $_FILES['album_files']['error'][$i],
                    'size'     => $_FILES['album_files']['size'][$i],
                ];
                $r = upload_image($single);
                if ($r['success']) $album_imgs[] = ['path' => $r['path'], 'medium' => $r['medium'] ?? $r['path'], 'thumb' => $r['thumb'] ?? $r['path']];
                else $warnings[] = 'Photo "' . $_FILES['album_files']['name'][$i] . '": ' . $r['message'];
            }
        }
        $config['album']['images'] = array_slice($album_imgs, 0, 6);

        // For the admin demo we store the sender name inside the config (no pages row)
        if ($is_admin_demo) $config['_sender'] = $sender;

        $slide_data_json = json_encode($config, JSON_UNESCAPED_UNICODE);

        if (empty($error)) {
            if ($is_admin_demo) {
                // Save the curated demo to site settings — this is what "Live Preview" shows
                set_setting($demo_setting_key, $slide_data_json);
                if (!empty($warnings)) $_SESSION['cb_warnings'] = $warnings;
                redirect('customize-birthday.php?admin_demo=' . urlencode($admin_demo) . '&saved=1');
            } elseif ($editing) {
                // Preserve password if left blank; hash if newly provided
                $hashed_password = $page['password'];
                if ($page_password !== '') {
                    $hashed_password = ($page_password === '__CLEAR__') ? null : password_hash($page_password, PASSWORD_DEFAULT);
                }
                $upd = $pdo->prepare("UPDATE pages SET title = ?, sender_name = ?, receiver_name = ?, letter_text = ?, slide_data = ?, accent_color = ?, proposal_question = ?, password = ?, music_url = ? WHERE id = ?");
                $upd->execute([$title, $sender, $receiver, $letter_text, $slide_data_json, $accent, $proposal_q, $hashed_password, $config['music']['audio_url'], $page['id']]);
                $save_slug = $page['slug'];
            } else {
                $days = (int)get_setting('default_expiry_days', 10);
                if ($days < 1) $days = 10;
                $expiry_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                $cost = PREMIUM_CREDIT_COST; // premium page = 5 credits
                if (get_user_credits($user_id) < $cost) {
                    $error = 'Premium pages need ' . $cost . ' credits (₹10 each). Please buy credits.';
                } else {
                    $save_slug = generate_slug($receiver . '-birthday', $pdo);
                    $hashed_password = ($page_password !== '' && $page_password !== '__CLEAR__') ? password_hash($page_password, PASSWORD_DEFAULT) : null;
                    $ins = $pdo->prepare("INSERT INTO pages
                        (user_id, category, template, title, slug, sender_name, receiver_name, letter_text, slide_data, music_url, theme, accent_color, font_style, proposal_question, password, status, guest_session_id, expiry_date)
                        VALUES (?, 'birthday', 'cinematic_birthday', ?, ?, ?, ?, ?, ?, ?, 'romantic', ?, 'Cormorant Garamond', ?, ?, 'published', ?, ?)");
                    $ins->execute([$user_id, $title, $save_slug, $sender, $receiver, $letter_text, $slide_data_json, $config['music']['audio_url'], $accent, $proposal_q, $hashed_password, session_id(), $expiry_date]);
                    $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?")->execute([$cost, $user_id, $cost]);
                }
            }

            if (empty($error)) {
                if (!empty($warnings)) $_SESSION['cb_warnings'] = $warnings;
                redirect('publish-success.php?slug=' . urlencode($save_slug));
            }
        }
    }

    // On error, keep submitted values by rebuilding $cfg from POST-derived $config
    if (!empty($error) && isset($config)) {
        $cfg = $config;
    }
}

// ---- Admin-curated music library available to premium pages ----
$music_library = [];
try {
    $stmt_ml = $pdo->query("SELECT id, title, file_path, category FROM music_library
        WHERE category IN ('premium','birthday','all')
        AND (status = 'approved' OR status IS NULL)
        ORDER BY FIELD(category,'premium','birthday','all'), title");
    $music_library = $stmt_ml->fetchAll();
} catch (PDOException $e) {
    // Fallback if the status column doesn't exist yet
    try {
        $stmt_ml = $pdo->query("SELECT id, title, file_path, category FROM music_library WHERE category IN ('premium','birthday','all') ORDER BY category, title");
        $music_library = $stmt_ml->fetchAll();
    } catch (PDOException $e2) { $music_library = []; }
}

// ---- Prefill string builders for repeating fields ----
$letter_prefill = implode("\n", $cfg['letter']['paragraphs'] ?? []);
$answers_prefill = implode("\n", $cfg['puzzle']['answers'] ?? []);
$hints_prefill = implode("\n", $cfg['puzzle']['hints'] ?? []);
function chat_msgs_prefill($scene) {
    $out = [];
    foreach (($scene['messages'] ?? []) as $m) {
        $prefix = (($m['side'] ?? 'received') === 'sent') ? 'me: ' : 'her: ';
        $out[] = $prefix . ($m['text'] ?? '');
    }
    return implode("\n", $out);
}
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $editing ? 'Edit' : 'Create' ?> Cinematic Birthday 🎂 - <?= h(SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#1a0810,#2d0f1e); color:#f5e6ec; }
  h1,h2,h3,.heading { font-family:'Outfit',sans-serif; }
  .fld { width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(255,150,170,0.18); border-radius:12px; padding:10px 14px; color:#fff; font-size:0.9rem; outline:none; transition:border-color .2s; }
  .fld:focus { border-color:#e8405a; }
  .fld::placeholder { color:rgba(255,200,210,0.35); }
  .lbl { display:block; font-size:0.72rem; text-transform:uppercase; letter-spacing:1px; color:#e39ab0; margin-bottom:5px; font-weight:600; }
  .sec { background:rgba(255,255,255,0.04); border:1px solid rgba(255,150,170,0.14); border-radius:20px; padding:22px; margin-bottom:18px; }
  .sec-title { font-size:1.1rem; font-weight:700; color:#ffb3c1; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
  .btn-rose { background:linear-gradient(135deg,#e8405a,#c4184e); color:#fff; border:none; border-radius:50px; padding:14px 40px; font-weight:600; cursor:pointer; box-shadow:0 8px 24px rgba(232,64,90,0.35); }
  .btn-rose:hover { opacity:.94; }
  .btn-add { background:rgba(232,64,90,0.15); border:1px dashed rgba(232,64,90,0.5); color:#ffb3c1; border-radius:10px; padding:8px 14px; font-size:0.8rem; cursor:pointer; }
  .btn-del { background:rgba(220,38,38,0.15); border:1px solid rgba(220,38,38,0.3); color:#fca5a5; border-radius:8px; padding:4px 10px; font-size:0.72rem; cursor:pointer; }
  .row-card { background:rgba(0,0,0,0.2); border:1px solid rgba(255,150,170,0.12); border-radius:14px; padding:14px; margin-bottom:12px; }
  .hint { font-size:0.72rem; color:rgba(255,200,210,0.5); margin-top:4px; }
  textarea.fld { resize:vertical; line-height:1.6; }
  /* Faded example text — user clicks to start writing their own */
  .fld.ghost { color:rgba(255,200,210,0.4) !important; font-style:italic; }
  /* Wizard steps */
  .cbstep { display:none; }
  .cbstep.active { display:block; animation: stepIn .3s ease; }
  @keyframes stepIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
  .cbtabs { display:flex; gap:6px; overflow-x:auto; padding:6px; margin-bottom:18px; background:rgba(0,0,0,0.25); border:1px solid rgba(255,150,170,0.14); border-radius:16px; position:sticky; top:66px; z-index:15; backdrop-filter:blur(8px); }
  .cbtab { flex:1 0 auto; white-space:nowrap; padding:9px 12px; border-radius:11px; font-size:0.78rem; font-weight:600; color:#e39ab0; background:transparent; border:none; cursor:pointer; transition:all .2s; }
  .cbtab.active { background:linear-gradient(135deg,#e8405a,#c4184e); color:#fff; box-shadow:0 6px 16px rgba(232,64,90,0.3); }
  .cbtab .num { display:inline-flex; width:18px; height:18px; border-radius:50%; background:rgba(255,255,255,0.15); align-items:center; justify-content:center; font-size:0.68rem; margin-right:5px; }
  .cbfooter { position:sticky; bottom:0; z-index:15; margin-top:8px; padding:14px; background:rgba(26,8,16,0.92); backdrop-filter:blur(10px); border-top:1px solid rgba(232,64,90,0.25); border-radius:16px 16px 0 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:center; }
  .btn-nav { background:rgba(255,255,255,0.08); border:1px solid rgba(255,150,170,0.25); color:#ffb3c1; border-radius:50px; padding:12px 22px; font-weight:600; cursor:pointer; }
  .btn-nav:hover { background:rgba(255,255,255,0.14); }
  .btn-preview { background:rgba(255,255,255,0.06); border:1px solid rgba(255,200,210,0.35); color:#fff; border-radius:50px; padding:12px 22px; font-weight:600; cursor:pointer; }
  .btn-preview:hover { background:rgba(255,255,255,0.12); }
</style>
</head>
<body class="min-h-screen">

<header class="sticky top-0 z-20" style="background:rgba(26,8,16,0.92); backdrop-filter:blur(12px); border-bottom:1px solid rgba(232,64,90,0.2);">
  <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="dashboard.php" class="text-sm text-pink-200/70 hover:text-white">← Dashboard</a>
    <div class="heading font-extrabold text-lg" style="color:#ffb3c1;">🎂 Cinematic Birthday</div>
    <a href="index.php" class="text-sm text-pink-200/50 hover:text-white"><?= h(SITE_NAME) ?></a>
  </div>
</header>

<main class="max-w-3xl mx-auto px-4 py-8">
  <div class="text-center mb-8">
    <h1 class="heading text-3xl font-extrabold" style="color:#ffb3c1;"><?= $is_admin_demo ? '🖼️ Customize the Demo' : ($editing ? 'Edit Your Surprise' : 'Design Your Birthday Surprise') ?></h1>
    <p class="text-sm text-pink-200/50 mt-2"><?= $is_admin_demo ? 'This demo is what users see in "Live Preview" to help them decide. It updates live as soon as you save.' : 'Customize every element of every section — text, memories, chats, voice, letter, puzzle and music. ✨' ?></p>
  </div>

  <?php if ($is_admin_demo): ?>
    <div class="mb-6 rounded-2xl p-4 text-sm flex items-center gap-3" style="background:rgba(124,58,237,0.15); border:1px solid rgba(124,58,237,0.35); color:#e9d5ff;">
      🛠️ <b>Admin Demo Mode</b> — you are editing the public demo for "Girlfriend Birthday Wish".
      <a href="customize-birthday.php?demo=1" target="_blank" class="ml-auto underline hover:text-white">View live demo ↗</a>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['saved'])): ?>
    <div class="mb-6 rounded-2xl p-4 text-sm" style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0;">✅ Demo saved! Users will now see this in "Live Preview".</div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="mb-6 bg-red-500/15 border border-red-500/30 text-red-200 rounded-xl p-4 text-sm"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="cbForm">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <?php if ($editing): ?><input type="hidden" name="edit_slug" value="<?= h($page['slug']) ?>"><?php endif; ?>
    <?php if ($is_admin_demo): ?><input type="hidden" name="admin_demo" value="<?= h($admin_demo) ?>"><?php endif; ?>
    <input type="hidden" name="existing_voice_audio" value="<?= h($cfg['voice']['audio_url'] ?? '') ?>">
    <input type="hidden" name="existing_letter_audio" value="<?= h($cfg['letter']['audio_url'] ?? '') ?>">
    <input type="hidden" name="existing_music_audio" value="<?= h($cfg['music']['audio_url'] ?? '') ?>">
    <input type="hidden" name="existing_video" value="<?= h($cfg['video']['url'] ?? '') ?>">
    <input type="hidden" name="existing_album" value="<?= h(json_encode($cfg['album']['images'] ?? [])) ?>">
    <input type="hidden" name="do" id="doField" value="publish">

    <!-- STEP TABS -->
    <div class="cbtabs" id="cbTabs">
      <button type="button" class="cbtab active" onclick="showStep(0)"><span class="num">1</span>Basics</button>
      <button type="button" class="cbtab" onclick="showStep(1)"><span class="num">2</span>Story</button>
      <button type="button" class="cbtab" onclick="showStep(2)"><span class="num">3</span>Voice &amp; Letter</button>
      <button type="button" class="cbtab" onclick="showStep(3)"><span class="num">4</span>Interactive &amp; End</button>
      <button type="button" class="cbtab" onclick="showStep(4)"><span class="num">5</span>Cake &amp; Media</button>
    </div>

    <div class="cbstep active" data-step="0">
    <!-- BASIC -->
    <?php $is_fresh = !$editing && !$is_admin_demo && $_SERVER['REQUEST_METHOD'] !== 'POST'; ?>
    <div class="sec">
      <div class="sec-title">💌 Basic Info — just fill 2 names</div>
      <p class="hint mb-4" style="font-size:0.8rem;">👇 These two names auto-fill across the whole page (signatures, voice note, secret, title — everywhere). Everything else is pre-filled with an example; edit it below if you like.</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="lbl">Receiver's Name (whose birthday it is)</label><input class="fld" name="landing_name" value="<?= h($is_fresh ? '' : cbf($cfg,'landing','name')) ?>" placeholder="e.g. <?= h(get_example('receiver')) ?>"></div>
        <div><label class="lbl">Your Name (sender)</label><input class="fld" name="sender_name" value="<?= h($is_admin_demo ? ($cfg['_sender'] ?? get_example('sender')) : ($editing ? ($page['sender_name'] ?? '') : ($_POST['sender_name'] ?? ''))) ?>" placeholder="e.g. <?= h(get_example('sender')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Browser Tab / Share Title</label><input class="fld" name="page_title" value="<?= h($is_fresh ? '' : ($cfg['page_title'] ?? '')) ?>" placeholder="[receiver] — My Favorite Person ❤️"><p class="hint">Leave blank and it fills automatically. <code>[receiver]</code> = the name above.</p></div>
      <div class="mt-4"><label class="lbl">Password (optional — lock the page)</label>
        <input class="fld" name="page_password" type="text" placeholder="<?= $editing && !empty($page['password']) ? 'Leave blank to keep current — type __CLEAR__ to remove' : 'Leave blank for no password' ?>">
      </div>
    </div>

    <!-- COLORS -->
    <div class="sec">
      <div class="sec-title">🎨 Colors &amp; Theme</div>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <?php
        $color_labels = ['rose'=>'Main Accent','blush'=>'Blush','soft'=>'Soft BG','cream'=>'Cream BG','deep'=>'Deep Dark','text'=>'Text','gold'=>'Gold'];
        foreach ($color_labels as $ck=>$clabel): ?>
        <div>
          <label class="lbl"><?= h($clabel) ?></label>
          <input type="color" name="color_<?= $ck ?>" value="<?= h($cfg['colors'][$ck] ?? '#e8405a') ?>" class="w-full h-10 rounded-lg bg-transparent border border-pink-500/20 cursor-pointer">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- LANDING -->
    <div class="sec">
      <div class="sec-title">🌹 Slide 1 — Landing</div>
      <div class="grid sm:grid-cols-3 gap-4">
        <div><label class="lbl">Top Emoji</label><input class="fld" name="landing_emoji" value="<?= h(cbf($cfg,'landing','emoji')) ?>"></div>
        <div class="sm:col-span-2"><label class="lbl">Sub-heading (script)</label><input class="fld" name="landing_script" value="<?= h(cbf($cfg,'landing','script')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Subtitle</label><textarea class="fld" name="landing_subtitle" rows="2"><?= h(cbf($cfg,'landing','subtitle')) ?></textarea></div>
      <div class="grid sm:grid-cols-2 gap-4 mt-4">
        <div><label class="lbl">Button Text</label><input class="fld" name="landing_btn" value="<?= h(cbf($cfg,'landing','btn')) ?>"></div>
        <div><label class="lbl">Hint Text</label><input class="fld" name="landing_hint" value="<?= h(cbf($cfg,'landing','hint')) ?>"></div>
      </div>
    </div>

    <!-- INTRO -->
    <div class="sec">
      <div class="sec-title">🎂 Slide 2 — Intro Message</div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="lbl">Label</label><input class="fld" name="intro_label" value="<?= h(cbf($cfg,'intro','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="intro_script" value="<?= h(cbf($cfg,'intro','script')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Message Body</label><textarea class="fld" name="intro_body" rows="5"><?= h(cbf($cfg,'intro','body')) ?></textarea></div>
      <div class="mt-4"><label class="lbl">Signature</label><input class="fld" name="intro_signature" value="<?= h(cbf($cfg,'intro','signature')) ?>"><p class="hint"><code>[sender]</code> = your name (auto-filled).</p></div>
    </div>

    </div><!-- /step 0 -->

    <div class="cbstep" data-step="1">
    <!-- MEMORIES -->
    <div class="sec">
      <div class="sec-title">✨ Slide 3 — Memories Timeline</div>
      <div class="grid sm:grid-cols-2 gap-4 mb-4">
        <div><label class="lbl">Label</label><input class="fld" name="memories_label" value="<?= h(cbf($cfg,'memories','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="memories_script" value="<?= h(cbf($cfg,'memories','script')) ?>"></div>
      </div>
      <div id="memList">
        <?php foreach (($cfg['memories']['items'] ?? []) as $it): ?>
        <div class="row-card mem-row">
          <div class="flex items-center justify-between mb-2"><span class="text-xs text-pink-300/70 font-semibold">Memory</span><button type="button" class="btn-del" onclick="this.closest('.mem-row').remove()">Remove ✕</button></div>
          <div class="grid grid-cols-4 gap-3">
            <div><label class="lbl">Emoji</label><input class="fld" name="mem_dot[]" value="<?= h($it['dot'] ?? '✨') ?>"></div>
            <div class="col-span-3"><label class="lbl">Date</label><input class="fld" name="mem_date[]" value="<?= h($it['date'] ?? '') ?>"></div>
          </div>
          <div class="mt-3"><label class="lbl">Title</label><input class="fld" name="mem_title[]" value="<?= h($it['title'] ?? '') ?>"></div>
          <div class="mt-3"><label class="lbl">Chat box (optional — one message per line)</label><textarea class="fld" name="mem_chat[]" rows="2"><?= h($it['chat'] ?? '') ?></textarea></div>
          <div class="mt-3"><label class="lbl">Note</label><input class="fld" name="mem_note[]" value="<?= h($it['note'] ?? '') ?>"></div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn-add" onclick="addMem()">+ Add Memory</button>
    </div>

    <!-- CHATS -->
    <div class="sec">
      <div class="sec-title">💬 Slide 4 — Our Chats</div>
      <div class="grid sm:grid-cols-2 gap-4 mb-4">
        <div><label class="lbl">Label</label><input class="fld" name="chats_label" value="<?= h(cbf($cfg,'chats','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="chats_script" value="<?= h(cbf($cfg,'chats','script')) ?>"></div>
      </div>
      <p class="hint mb-3">One message per line. Start your own messages with <b>me:</b> and theirs with <b>her:</b>. (Example: <code>me: I love you ❤️</code>)</p>
      <div id="chatList">
        <?php foreach (($cfg['chats']['scenes'] ?? []) as $sc): ?>
        <div class="row-card chat-row-card">
          <div class="flex items-center justify-between mb-2"><span class="text-xs text-pink-300/70 font-semibold">Chat Tab</span><button type="button" class="btn-del" onclick="this.closest('.chat-row-card').remove()">Remove ✕</button></div>
          <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="lbl">Tab Button Label</label><input class="fld" name="chat_tab[]" value="<?= h($sc['tab'] ?? '') ?>"></div>
            <div><label class="lbl">Scene Caption</label><input class="fld" name="chat_label[]" value="<?= h($sc['label'] ?? '') ?>"></div>
          </div>
          <div class="mt-3"><label class="lbl">Messages (one per line)</label><textarea class="fld" name="chat_msgs[]" rows="4"><?= h(chat_msgs_prefill($sc)) ?></textarea></div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn-add" onclick="addChat()">+ Add Chat Tab</button>
    </div>

    </div><!-- /step 1 -->

    <div class="cbstep" data-step="2">
    <!-- VOICE -->
    <div class="sec">
      <div class="sec-title">🎙️ Slide 5 — Voice Note</div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="lbl">Label</label><input class="fld" name="voice_label" value="<?= h(cbf($cfg,'voice','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="voice_script" value="<?= h(cbf($cfg,'voice','script')) ?>"></div>
      </div>
      <div class="grid sm:grid-cols-3 gap-4 mt-4">
        <div><label class="lbl">Subtitle</label><input class="fld" name="voice_subtitle" value="<?= h(cbf($cfg,'voice','subtitle')) ?>"></div>
        <div><label class="lbl">Speaker Name</label><input class="fld" name="voice_name" value="<?= h(cbf($cfg,'voice','name')) ?>"><p class="hint"><code>[sender]</code> = auto</p></div>
        <div><label class="lbl">Avatar Emoji</label><input class="fld" name="voice_avatar" value="<?= h(cbf($cfg,'voice','avatar')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Transcript</label><textarea class="fld" name="voice_transcript" rows="5"><?= h(cbf($cfg,'voice','transcript')) ?></textarea></div>
      <div class="mt-4"><label class="lbl">Voice Note Audio (mp3/m4a/wav — max 10MB)</label><input class="fld" type="file" name="voice_audio" accept="audio/*">
        <?php if (!empty($cfg['voice']['audio_url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['voice']['audio_url'])) ?> (upload to replace)</p><?php endif; ?>
      </div>
    </div>

    <!-- LETTER -->
    <div class="sec">
      <div class="sec-title">💌 Slide 6 — Love Letter</div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="lbl">Label</label><input class="fld" name="letter_label" value="<?= h(cbf($cfg,'letter','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="letter_script" value="<?= h(cbf($cfg,'letter','script')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Letter Paragraphs (one paragraph per line)</label><textarea class="fld" name="letter_paragraphs" rows="8"><?= h($letter_prefill) ?></textarea><p class="hint">For bold, wrap in stars: <code>*My love*</code></p></div>
      <div class="mt-4"><label class="lbl">Letter Audio (optional — plays on this slide)</label><input class="fld" type="file" name="letter_audio" accept="audio/*">
        <?php if (!empty($cfg['letter']['audio_url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['letter']['audio_url'])) ?> (upload to replace)</p><?php endif; ?>
      </div>
    </div>

    </div><!-- /step 2 -->

    <div class="cbstep" data-step="3">
    <!-- PUZZLE -->
    <div class="sec">
      <div class="sec-title">🔐 Slide 7 — Secret Puzzle</div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="lbl">Label</label><input class="fld" name="puzzle_label" value="<?= h(cbf($cfg,'puzzle','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="puzzle_script" value="<?= h(cbf($cfg,'puzzle','script')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Question</label><textarea class="fld" name="puzzle_question" rows="2"><?= h(cbf($cfg,'puzzle','question')) ?></textarea></div>
      <div class="grid sm:grid-cols-2 gap-4 mt-4">
        <div><label class="lbl">Input Placeholder</label><input class="fld" name="puzzle_placeholder" value="<?= h(cbf($cfg,'puzzle','placeholder')) ?>"></div>
        <div><label class="lbl">Success Message</label><input class="fld" name="puzzle_success" value="<?= h(cbf($cfg,'puzzle','success')) ?>"></div>
      </div>
      <div class="grid sm:grid-cols-2 gap-4 mt-4">
        <div><label class="lbl">Accepted Answers (one per line)</label><textarea class="fld" name="puzzle_answers" rows="4"><?= h($answers_prefill) ?></textarea><p class="hint">Case-insensitive. Add every variation.</p></div>
        <div><label class="lbl">Hints (one per line, optional)</label><textarea class="fld" name="puzzle_hints" rows="4"><?= h($hints_prefill) ?></textarea></div>
      </div>
    </div>

    <!-- SECRET -->
    <div class="sec">
      <div class="sec-title">🫶 Slide 8 — Secret Reveal</div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="lbl">Label</label><input class="fld" name="secret_label" value="<?= h(cbf($cfg,'secret','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="secret_script" value="<?= h(cbf($cfg,'secret','script')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Question</label><textarea class="fld" name="secret_question" rows="2"><?= h(cbf($cfg,'secret','question')) ?></textarea></div>
      <div class="grid sm:grid-cols-2 gap-4 mt-4">
        <div><label class="lbl">Reveal Name</label><input class="fld" name="secret_name" value="<?= h(cbf($cfg,'secret','name')) ?>"><p class="hint"><code>[sender]</code> = auto</p></div>
        <div><label class="lbl">Reveal Subtitle</label><input class="fld" name="secret_reveal_sub" value="<?= h(cbf($cfg,'secret','reveal_sub')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Reveal Quote</label><textarea class="fld" name="secret_quote" rows="2"><?= h(cbf($cfg,'secret','quote')) ?></textarea></div>
    </div>

    <!-- PROPOSAL -->
    <div class="sec">
      <div class="sec-title">💍 Slide 9 — The Big Question</div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="lbl">Label</label><input class="fld" name="proposal_label" value="<?= h(cbf($cfg,'proposal','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="proposal_script" value="<?= h(cbf($cfg,'proposal','script')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Question Body</label><textarea class="fld" name="proposal_body" rows="3"><?= h(cbf($cfg,'proposal','body')) ?></textarea></div>
      <div class="grid sm:grid-cols-2 gap-4 mt-4">
        <div><label class="lbl">Button 1 Text</label><input class="fld" name="proposal_yes" value="<?= h(cbf($cfg,'proposal','yes')) ?>"></div>
        <div><label class="lbl">Button 2 Text</label><input class="fld" name="proposal_always" value="<?= h(cbf($cfg,'proposal','always')) ?>"></div>
      </div>
    </div>

    <!-- ENDING -->
    <div class="sec">
      <div class="sec-title">🎉 Slide 10 — Ending</div>
      <div class="mt-1"><label class="lbl">Title (new line allowed)</label><textarea class="fld" name="ending_title" rows="2"><?= h(cbf($cfg,'ending','title')) ?></textarea></div>
      <div class="mt-4"><label class="lbl">Message Body</label><textarea class="fld" name="ending_body" rows="5"><?= h(cbf($cfg,'ending','body')) ?></textarea></div>
      <div class="mt-4"><label class="lbl">Signature</label><input class="fld" name="ending_signature" value="<?= h(cbf($cfg,'ending','signature')) ?>"><p class="hint"><code>[sender]</code> = your name (auto-filled).</p></div>
    </div>

    </div><!-- /step 3 -->

    <div class="cbstep" data-step="4">
    <!-- CAKE CUTTING -->
    <div class="sec">
      <div class="sec-title">🎂 Cake Cutting (interactive)</div>
      <label class="flex items-center gap-3 cursor-pointer mb-4">
        <input type="checkbox" name="cake_enabled" value="1" <?= !empty($cfg['cake']['enabled']) ? 'checked' : '' ?> class="w-5 h-5 accent-pink-500">
        <span class="text-sm text-pink-100/80">Show the cake-cutting slide — the recipient taps to cut the cake (candles blow out + confetti 🎉).</span>
      </label>
      <div class="grid sm:grid-cols-3 gap-4">
        <div><label class="lbl">Label</label><input class="fld" name="cake_label" value="<?= h(cbf($cfg,'cake','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="cake_script" value="<?= h(cbf($cfg,'cake','script')) ?>"></div>
        <div><label class="lbl">Subtitle</label><input class="fld" name="cake_subtitle" value="<?= h(cbf($cfg,'cake','subtitle')) ?>"></div>
      </div>
      <div class="grid sm:grid-cols-2 gap-4 mt-4">
        <div><label class="lbl">Button Text</label><input class="fld" name="cake_button" value="<?= h(cbf($cfg,'cake','button')) ?>"></div>
        <div><label class="lbl">Message after cutting</label><input class="fld" name="cake_message" value="<?= h(cbf($cfg,'cake','message')) ?>"><p class="hint"><code>[receiver]</code> = name auto-filled.</p></div>
      </div>
    </div>

    <!-- PHOTO ALBUM -->
    <div class="sec">
      <div class="sec-title">📸 Photo Album (max 6 photos)</div>
      <label class="flex items-center gap-3 cursor-pointer mb-4">
        <input type="checkbox" name="album_enabled" value="1" <?= !empty($cfg['album']['enabled']) ? 'checked' : '' ?> class="w-5 h-5 accent-pink-500">
        <span class="text-sm text-pink-100/80">Show the photo album slide (it only appears once you add photos).</span>
      </label>
      <div class="grid sm:grid-cols-3 gap-4">
        <div><label class="lbl">Label</label><input class="fld" name="album_label" value="<?= h(cbf($cfg,'album','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="album_script" value="<?= h(cbf($cfg,'album','script')) ?>"></div>
        <div><label class="lbl">Subtitle</label><input class="fld" name="album_subtitle" value="<?= h(cbf($cfg,'album','subtitle')) ?>"></div>
      </div>
      <?php $cur_imgs = $cfg['album']['images'] ?? []; ?>
      <?php if (!empty($cur_imgs)): ?>
      <div class="mt-4">
        <label class="lbl">Current Photos (<?= count($cur_imgs) ?>/6)</label>
        <div class="grid grid-cols-6 gap-2">
          <?php foreach ($cur_imgs as $im): $src = is_array($im) ? ($im['thumb'] ?? $im['path'] ?? '') : $im; ?>
          <div class="aspect-square rounded-lg overflow-hidden border border-pink-500/20"><img src="<?= h($src) ?>" class="w-full h-full object-cover"></div>
          <?php endforeach; ?>
        </div>
        <label class="flex items-center gap-2 mt-3 cursor-pointer text-xs text-red-300">
          <input type="checkbox" name="album_clear" value="1" class="accent-red-500"> Remove all existing photos (replace with new uploads)
        </label>
      </div>
      <?php endif; ?>
      <div class="mt-4"><label class="lbl">Add Photos (JPG/PNG/WEBP — each max 10MB)</label>
        <input class="fld" type="file" name="album_files[]" accept="image/*" multiple>
        <p class="hint">You can select up to 6 at once. No more than 6 will be kept in total.</p>
      </div>
    </div>

    <!-- VIDEO -->
    <div class="sec">
      <div class="sec-title">🎬 Video Message</div>
      <label class="flex items-center gap-3 cursor-pointer mb-4">
        <input type="checkbox" name="video_enabled" value="1" <?= !empty($cfg['video']['enabled']) ? 'checked' : '' ?> class="w-5 h-5 accent-pink-500">
        <span class="text-sm text-pink-100/80">Show the video slide (it only appears once you upload a video).</span>
      </label>
      <div class="grid sm:grid-cols-3 gap-4">
        <div><label class="lbl">Label</label><input class="fld" name="video_label" value="<?= h(cbf($cfg,'video','label')) ?>"></div>
        <div><label class="lbl">Title (script)</label><input class="fld" name="video_script" value="<?= h(cbf($cfg,'video','script')) ?>"></div>
        <div><label class="lbl">Subtitle</label><input class="fld" name="video_subtitle" value="<?= h(cbf($cfg,'video','subtitle')) ?>"></div>
      </div>
      <div class="mt-4"><label class="lbl">Video File (MP4/WEBM/MOV — max 50MB)</label>
        <input class="fld" type="file" name="video_file" accept="video/*">
        <?php if (!empty($cfg['video']['url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['video']['url'])) ?> (upload to replace)</p><?php endif; ?>
      </div>
    </div>

    <!-- MUSIC -->
    <div class="sec">
      <div class="sec-title">🎵 Background Music</div>

      <?php if (!empty($music_library)): ?>
      <?php $cur_music = $cfg['music']['audio_url'] ?? ''; ?>
      <div class="mb-4">
        <label class="lbl">Choose from the music library (recommended) 🎶</label>
        <select class="fld" name="music_library_url" id="musicLibSelect" onchange="cbLibChanged()">
          <option value="">— None / upload your own —</option>
          <?php foreach ($music_library as $trk): ?>
          <option value="<?= h($trk['file_path']) ?>" data-title="<?= h($trk['title']) ?>" <?= ($cur_music === $trk['file_path']) ? 'selected' : '' ?>>
            <?= $trk['category'] === 'premium' ? '✨ ' : '' ?><?= h($trk['title']) ?><?= $trk['category'] !== 'premium' ? ' (' . h($trk['category']) . ')' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="flex items-center gap-2 mt-2">
          <button type="button" class="btn-preview" style="padding:8px 16px; font-size:0.8rem;" onclick="cbToggleLibPreview()">▶ Preview</button>
          <span class="hint" id="libPrevStatus"></span>
        </div>
        <audio id="libPreviewAudio" preload="none"></audio>
        <p class="hint mt-2">The admin's premium music appears here. Or upload your own file below.</p>
      </div>
      <?php endif; ?>

      <div><label class="lbl">Track Title (shown in player)</label><input class="fld" name="music_title" value="<?= h(cbf($cfg,'music','title')) ?>"></div>
      <div class="mt-4"><label class="lbl">Or upload your own music file (mp3 — max 20MB)</label><input class="fld" type="file" name="music_audio" accept="audio/*">
        <?php if (!empty($cfg['music']['audio_url'])): ?><p class="hint">✅ Current: <?= h(basename($cfg['music']['audio_url'])) ?></p><?php endif; ?>
      </div>
    </div>

    <!-- REPLIES -->
    <div class="sec">
      <div class="sec-title">💬 Reply Box (recipient can message you back)</div>
      <label class="flex items-center gap-3 cursor-pointer mb-4">
        <input type="checkbox" name="reply_enabled" value="1" <?= !empty($cfg['reply']['enabled']) ? 'checked' : '' ?> class="w-5 h-5 accent-pink-500">
        <span class="text-sm text-pink-100/80">Allow replies — on the last slide the recipient can send you a message (you'll see it in Dashboard → Replies).</span>
      </label>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="lbl">Heading</label><input class="fld" name="reply_heading" value="<?= h(cbf($cfg,'reply','heading')) ?>"></div>
        <div><label class="lbl">Subtitle</label><input class="fld" name="reply_subtitle" value="<?= h(cbf($cfg,'reply','subtitle')) ?>"></div>
      </div>
      <div class="grid sm:grid-cols-3 gap-4 mt-4">
        <div><label class="lbl">Input Placeholder</label><input class="fld" name="reply_placeholder" value="<?= h(cbf($cfg,'reply','placeholder')) ?>"></div>
        <div><label class="lbl">Button Text</label><input class="fld" name="reply_button" value="<?= h(cbf($cfg,'reply','button')) ?>"></div>
        <div><label class="lbl">Success Message</label><input class="fld" name="reply_success" value="<?= h(cbf($cfg,'reply','success')) ?>"></div>
      </div>
    </div>

    </div><!-- /step 4 -->

    <div class="cbfooter">
      <button type="button" class="btn-nav" id="btnPrev" onclick="stepMove(-1)" style="display:none;">← Back</button>
      <button type="submit" class="btn-preview" id="btnPreview" onclick="cbOpenPreviewLoader();" formtarget="cbPreviewWin" style="display:none;">👁️ Preview</button>
      <button type="button" class="btn-nav" id="btnNext" onclick="stepMove(1)">Next →</button>
      <button type="submit" class="btn-rose" id="btnPublish" onclick="document.getElementById('doField').value='publish';" style="display:none;">
        <?= $is_admin_demo ? '💾 Save Demo' : ($editing ? '💾 Save Changes' : '🎁 Publish') ?>
      </button>
    </div>
    <p class="hint text-center pb-6"><?= $editing ? 'Changes save instantly to your live page.' : 'Publishing uses 1 free page or 1 credit.' ?> &nbsp;·&nbsp; 👁️ Preview opens in a new tab; the editor stays here.</p>
  </form>
</main>

<script>
// ── WIZARD STEPS ──
let cbCurrentStep = 0;
const cbTotalSteps = 5;
function showStep(n) {
  n = Math.max(0, Math.min(cbTotalSteps - 1, n));
  cbCurrentStep = n;
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
// Prevent Enter in a text field from accidentally submitting/publishing
document.getElementById('cbForm').addEventListener('keydown', function (e) {
  if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'submit') e.preventDefault();
});

// "Generating your page" overlay so publish never feels like a hang (uploads can take a while)
document.getElementById('cbForm').addEventListener('submit', function () {
  if (document.getElementById('doField').value !== 'publish') return; // preview opens a new tab
  const o = document.createElement('div');
  o.id = 'genOverlay';
  o.style.cssText = 'position:fixed;inset:0;z-index:5000;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(26,8,16,0.96);backdrop-filter:blur(8px);text-align:center;padding:20px;';
  o.innerHTML = '<div style="font-size:3.4rem;animation:genBob 1.2s ease-in-out infinite;">🎂</div>'
    + '<div style="font-family:Outfit,sans-serif;font-weight:800;font-size:1.4rem;color:#ffb3c1;margin-top:14px;">Generating your page…</div>'
    + '<div style="color:rgba(255,200,210,0.6);font-size:0.85rem;margin-top:6px;" id="genStatus">Uploading your photos, music &amp; video ✨</div>'
    + '<div style="width:190px;height:5px;background:rgba(255,255,255,0.12);border-radius:4px;margin-top:20px;overflow:hidden;"><div style="height:100%;width:40%;border-radius:4px;background:linear-gradient(90deg,#e8405a,#ff8fa3);animation:genSlide 1.2s ease-in-out infinite;"></div></div>'
    + '<div style="color:rgba(255,200,210,0.4);font-size:0.75rem;margin-top:16px;">Please don\'t close this tab 💕</div>'
    + '<style>@keyframes genBob{0%,100%{transform:translateY(0) rotate(-4deg);}50%{transform:translateY(-10px) rotate(4deg);}}@keyframes genSlide{0%{margin-left:-40%;}100%{margin-left:100%;}}</style>';
  document.body.appendChild(o);
  const msgs = ['Uploading your photos, music & video ✨', 'Building your cinematic pages 🎬', 'Adding animations & sparkles 🎈', 'Almost there… 🎂'];
  let mi = 0; setInterval(() => { mi = (mi + 1) % msgs.length; const s = document.getElementById('genStatus'); if (s) s.textContent = msgs[mi]; }, 2600);
  const pb = document.getElementById('btnPublish'); if (pb) pb.disabled = true;
});

function addMem() {
  const html = `<div class="row-card mem-row">
    <div class="flex items-center justify-between mb-2"><span class="text-xs text-pink-300/70 font-semibold">Memory</span><button type="button" class="btn-del" onclick="this.closest('.mem-row').remove()">Remove ✕</button></div>
    <div class="grid grid-cols-4 gap-3">
      <div><label class="lbl">Emoji</label><input class="fld" name="mem_dot[]" value="✨"></div>
      <div class="col-span-3"><label class="lbl">Date</label><input class="fld" name="mem_date[]" placeholder="1 May 2026"></div>
    </div>
    <div class="mt-3"><label class="lbl">Title</label><input class="fld" name="mem_title[]" placeholder="A special moment"></div>
    <div class="mt-3"><label class="lbl">Chat box (optional — one message per line)</label><textarea class="fld" name="mem_chat[]" rows="2"></textarea></div>
    <div class="mt-3"><label class="lbl">Note</label><input class="fld" name="mem_note[]"></div>
  </div>`;
  document.getElementById('memList').insertAdjacentHTML('beforeend', html);
}
function addChat() {
  const html = `<div class="row-card chat-row-card">
    <div class="flex items-center justify-between mb-2"><span class="text-xs text-pink-300/70 font-semibold">Chat Tab</span><button type="button" class="btn-del" onclick="this.closest('.chat-row-card').remove()">Remove ✕</button></div>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="lbl">Tab Button Label</label><input class="fld" name="chat_tab[]" placeholder="Our Moment 💖"></div>
      <div><label class="lbl">Scene Caption</label><input class="fld" name="chat_label[]"></div>
    </div>
    <div class="mt-3"><label class="lbl">Messages (one per line)</label><textarea class="fld" name="chat_msgs[]" rows="4" placeholder="me: I love you ❤️&#10;her: I love you too 🥹"></textarea></div>
  </div>`;
  document.getElementById('chatList').insertAdjacentHTML('beforeend', html);
}

// ── MUSIC LIBRARY PICKER ──
const CB_MEDIA_BASE = <?= json_encode(rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/', JSON_UNESCAPED_SLASHES) ?>;
function cbLibChanged() {
  const sel = document.getElementById('musicLibSelect');
  const a = document.getElementById('libPreviewAudio');
  const st = document.getElementById('libPrevStatus');
  if (a) a.pause();
  if (st) st.textContent = '';
  if (sel && sel.value) {
    const t = sel.options[sel.selectedIndex].getAttribute('data-title');
    const mt = document.querySelector('[name=music_title]');
    if (t && mt && (!mt.value.trim() || mt.classList.contains('ghost'))) { mt.value = t; mt.classList.remove('ghost'); }
  }
}
function cbToggleLibPreview() {
  const sel = document.getElementById('musicLibSelect');
  const a = document.getElementById('libPreviewAudio');
  const st = document.getElementById('libPrevStatus');
  if (!sel || !sel.value) { if (st) st.textContent = 'Please choose a track first 🙂'; return; }
  if (a.src.indexOf(sel.value) === -1) a.src = CB_MEDIA_BASE + sel.value;
  if (a.paused) { a.play().then(() => { st.textContent = '▶ Playing preview…'; }).catch(() => { st.textContent = 'Preview failed to load'; }); }
  else { a.pause(); st.textContent = '⏸ Paused'; }
}

<?php if ($is_fresh): ?>
(function(){
  const exclude = ['landing_name','sender_name','page_title','page_password'];
  document.querySelectorAll('#cbForm input[type=text], #cbForm textarea').forEach(function(f){
    const nm = (f.name || '').replace('[]','');
    if (exclude.indexOf(nm) !== -1) return;
    if (!f.value) return;
    f.dataset.demo = f.value;
    f.classList.add('ghost');
    f.addEventListener('focus', function(){ if (f.classList.contains('ghost')) { f.value=''; f.classList.remove('ghost'); } });
    f.addEventListener('blur', function(){ if (!f.value.trim()) { f.value = f.dataset.demo; f.classList.add('ghost'); } });
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
    'Creating Your Premium Magic Page... 💖',
    'Brewing the love potions... 🧪✨',
    'Designing your 10-scene cinematic theme... 🎨',
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
        <div class="text-4xl animate-bounce">💖</div>
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
<?php render_tutorial_button('birthday'); ?>
</body>
</html>
