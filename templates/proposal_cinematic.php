<?php
// =========================================================================
// PROPOSAL CINEMATIC — Standalone Premium Template (spine v1)
// A self-contained, movie-like proposal experience. Does NOT use the DB slide
// engine. Config JSON lives in `pages.slide_data`; every page is customizable
// and can be enabled/disabled individually.
//
//   proposal_cinematic_defaults()      -> full default config (with [tokens])
//   proposal_cinematic_config($page)   -> saved config merged over defaults
//   render_proposal_cinematic($page)   -> outputs the full HTML page
// =========================================================================

if (!function_exists('pc_format')) {

/** Safe, forgiving formatter: escapes HTML, *stars* -> bold, newlines -> <br>. */
function pc_format($s) {
    $s = htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', $s);
    return nl2br($s, false);
}

function proposal_cinematic_defaults() {
    return [
        'colors' => [
            'rose'  => '#e8405a',
            'deep'  => '#12030b',
            'gold'  => '#f4c56b',
            'blush' => '#f7c5cf',
            'cream' => '#fff8f5',
            'text'  => '#3a1a22',
        ],
        'page_title' => 'For [receiver] ❤️',

        // One continuous background track (library path or uploaded file)
        'music' => ['title' => 'Perfect — Ed Sheeran', 'audio_url' => ''],

        'welcome' => [
            'enabled'  => true,
            'subtitle' => 'Someone made something from the heart, just for you.',
            'button'   => 'Open Your Surprise 🌹',
            'particles' => true,
        ],
        'surprise' => [
            'enabled'    => true,
            'message'    => 'I have something special for you…',
            'button'     => 'Continue 💕',
            'gift_color' => '#e8405a',
        ],
        'journey' => [
            'enabled' => true,
            'label'   => 'From the very beginning',
            'script'  => 'Our Journey ✨',
            'items'   => [
                ['icon' => '💬', 'date' => 'The first message', 'title' => 'How it all began'],
                ['icon' => '📞', 'date' => 'First late-night call', 'title' => 'Hours felt like minutes'],
                ['icon' => '🤝', 'date' => 'The first time we met', 'title' => 'My heart raced'],
                ['icon' => '❤️', 'date' => 'The first "I love you"', 'title' => 'Everything changed'],
            ],
        ],
        'gallery' => [
            'enabled'  => true,
            'label'    => 'Our memories',
            'script'   => 'Memory Gallery 📸',
            'subtitle' => 'Moments I never want to forget',
            'images'   => [], // up to 10 uploaded images
        ],
        'favorites' => [
            'enabled' => true,
            'label'   => 'My favourites',
            'script'  => 'Favourite Photos ❤️',
            'photos'  => [], // each: {img, caption}
        ],
        'moments' => [
            'enabled' => true,
            'label'   => 'Little things I love',
            'script'  => 'Beautiful Moments 💕',
            'items'   => [], // each: {img, title, desc}
        ],
        'reasons' => [
            'enabled' => true,
            'label'   => 'Just a few of a thousand',
            'script'  => 'Why I Love You ❤️',
            'items'   => ['Your smile', 'The way you laugh', 'Your kind heart', 'How you make me feel', 'Just... you'],
        ],
        'future' => [
            'enabled' => true,
            'label'   => 'One day…',
            'script'  => 'Our Future 🌍',
            'items'   => [
                ['icon' => '✈️', 'text' => 'Travel the world together'],
                ['icon' => '🏡', 'text' => 'A little home of our own'],
                ['icon' => '☕', 'text' => 'Lazy mornings, forever'],
                ['icon' => '💍', 'text' => 'Grow old, hand in hand'],
            ],
        ],
        'ring' => [
            'enabled'    => true,
            'label'      => 'A little something',
            'script'     => 'For You 💍',
            'text'       => 'Every love story deserves a ring.',
            'ring_emoji' => '💍',
        ],

        'letter' => [
            'enabled'     => true,
            'label'       => 'Written just for you',
            'script'      => 'A Love Letter 💌',
            'paragraphs'  => [
                '*My love,*',
                "Some feelings are too big for words, but I'll try anyway.",
                'From the moment you came into my life, everything felt warmer, softer, and more alive.',
                'You are my calm, my chaos, my favourite hello and my hardest goodbye.',
                'I want every ordinary day and every big adventure — with you.',
                '*Forever yours ❤️*',
            ],
            'signature'   => '— [sender]',
            'font'        => 'Cormorant Garamond',
            'paper_color' => '#fffdf8',
            'audio_url'   => '',
        ],
        'voice' => [
            'enabled'   => true,
            'label'     => 'For your ears only',
            'script'    => 'Hear My Voice 🎙️',
            'subtitle'  => 'Press play — I recorded this just for you.',
            'name'      => '[sender]',
            'avatar'    => '💌',
            'audio_url' => '',
        ],
        'video' => [
            'enabled'  => true,
            'label'    => 'Press play',
            'script'   => 'A Video For You 🎥',
            'subtitle' => 'Something I wanted you to see.',
            'url'      => '',
            'poster'   => '',
        ],
        'scene' => [
            'enabled' => true,
            'line1'   => 'I never want to lose you.',
            'line2'   => 'Will you stay with me forever?',
            'button'  => 'One last thing… 💍',
        ],
        'final' => [
            'enabled'  => true,
            'question' => 'Will You Be Mine Forever?',
            'yes'      => 'YES ❤️',
            'no'       => 'No',
            'funny_no' => true,
        ],
        'celebration' => [
            'enabled' => true,
            'message' => "You just made me the happiest person alive.\nThis is the beginning of *our forever* ❤️",
        ],
        'reply' => [
            'enabled'     => true,
            'heading'     => 'Say something back 💌',
            'subtitle'    => 'Send me a little message — text, voice, photo or video.',
            'placeholder' => 'Write your reply…',
            'button'      => 'Send 💖',
            'success'     => 'Sent! Thank you ❤️',
        ],

        // ── Optional premium pages (off by default; creator enables) ──
        'photo_wall'  => ['enabled' => false, 'label' => 'Us, everywhere', 'script' => 'Our Photo Wall 🖼️', 'images' => []],
        'heart'       => ['enabled' => false, 'label' => 'Made of moments', 'script' => 'You Have My Heart 💗', 'subtitle' => 'Tap the heart 💗', 'images' => []],
        'scratch'     => ['enabled' => false, 'label' => 'Scratch to reveal', 'script' => 'A Hidden Message 🪙', 'cover' => 'Scratch here…', 'message' => 'You are my favourite person ❤️'],
        'secret_pw'   => ['enabled' => false, 'label' => 'Only you know this', 'script' => 'Secret Unlock 🔐', 'hint' => 'Our special date (e.g. 14 Feb)', 'password' => '', 'success' => 'Unlocked! 💖'],
        'puzzle'      => ['enabled' => false, 'label' => 'Put us back together', 'script' => 'Solve the Puzzle 🧩', 'image' => '', 'success' => 'You did it! 🧩❤️'],
        'quiz'        => ['enabled' => false, 'label' => 'How well do you know us?', 'script' => 'Memory Quiz 🧠', 'questions' => [
            ['q' => 'Where did we first meet?', 'options' => ['A café', 'Online', 'A party'], 'correct' => 1],
        ]],
        'love_meter'  => ['enabled' => false, 'label' => 'Scientifically proven', 'script' => 'Love Meter 💘', 'percent' => 100, 'caption' => 'Off the charts. Always.'],
        'countdown'   => ['enabled' => false, 'label' => 'Get ready…', 'message' => 'to forever ❤️'],
        'calendar'    => ['enabled' => false, 'label' => 'Days that matter', 'script' => 'Our Little Calendar 📅', 'dates' => [
            ['date' => '24 Jan', 'label' => 'The day we met'],
            ['date' => '14 Feb', 'label' => 'Our first date'],
        ]],
        'destination' => ['enabled' => false, 'label' => 'Pack your bags', 'script' => 'Where I\'m Taking You 📍', 'place' => 'A surprise weekend', 'address' => 'Details in your DM 😉'],
        'thankyou'    => ['enabled' => false, 'label' => 'From the bottom of my heart', 'script' => 'Thank You 🤍', 'message' => "For your patience, your love, and simply for being you.\nHere's to *us*.", 'signature' => '— [sender]'],
    ];
}

function proposal_cinematic_config($page) {
    $defaults = proposal_cinematic_defaults();
    $saved = [];
    if (!empty($page['slide_data'])) {
        $decoded = json_decode($page['slide_data'], true);
        if (is_array($decoded)) $saved = $decoded;
    }
    $config = $defaults;
    foreach ($saved as $section => $val) {
        if (is_array($val) && isset($defaults[$section]) && is_array($defaults[$section])) {
            $config[$section] = array_replace($defaults[$section], $val);
        } else {
            $config[$section] = $val;
        }
    }
    return $config;
}

function render_proposal_cinematic($page) {
    global $pdo;
    $c = proposal_cinematic_config($page);

    // Name auto-fill tokens
    $tok = [
        '[sender]'   => $page['sender_name'] ?? '',
        '[Sender]'   => $page['sender_name'] ?? '',
        '[receiver]' => $page['receiver_name'] ?? '',
        '[Receiver]' => $page['receiver_name'] ?? '',
    ];
    array_walk_recursive($c, function (&$v) use ($tok) {
        if (is_string($v)) $v = strtr($v, $tok);
    });
    $receiver = $page['receiver_name'] ?? 'My Love';

    // Ownership / expiry / password gates (independent of slide engine)
    $is_owner = false;
    if (function_exists('is_logged_in') && is_logged_in() &&
        (($page['user_id'] ?? null) == ($_SESSION['user_id'] ?? null) || (function_exists('is_super_admin') && is_super_admin()))) {
        $is_owner = true;
    } elseif (($page['guest_session_id'] ?? null) === session_id()) {
        $is_owner = true;
    }
    $is_expired = false;
    if (!empty($page['is_expired']) && (int)$page['is_expired'] === 1) $is_expired = true;
    elseif (!empty($page['expiry_date']) && strtotime($page['expiry_date']) < time()) $is_expired = true;
    if ($is_expired && !$is_owner) {
        echo '<div style="font-family:sans-serif;text-align:center;padding:60px 20px;background:#12030b;color:#ffb3c1;min-height:100vh;"><div style="font-size:3rem">⏳</div><h2>Memory Archived</h2><p style="color:rgba(255,200,210,0.6)">This page has reached its expiration date.</p></div>';
        return;
    }
    if (!empty($page['password']) && !$is_owner) {
        $unlocked = ($_SESSION['pc_unlocked_' . $page['id']] ?? false) === true;
        if (!$unlocked && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pc_password'])) {
            if (password_verify($_POST['pc_password'], $page['password'])) { $_SESSION['pc_unlocked_' . $page['id']] = true; $unlocked = true; }
        }
        if (!$unlocked) {
            $err = ($_SERVER['REQUEST_METHOD'] === 'POST') ? '<p style="color:#ff6b81;font-size:0.85rem">Wrong password, try again.</p>' : '';
            echo '<div style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#12030b;color:#ffb3c1"><form method="post" style="text-align:center;background:rgba(255,255,255,0.05);padding:36px;border-radius:20px;border:1px solid rgba(232,64,90,0.2)"><div style="font-size:2.5rem">🔒</div><h2>This surprise is locked</h2><input type="password" name="pc_password" placeholder="Enter password" style="margin-top:16px;padding:12px 16px;border-radius:12px;border:none;width:100%;text-align:center" required>' . $err . '<button type="submit" style="margin-top:14px;background:#e8405a;color:#fff;border:none;border-radius:50px;padding:12px 32px;cursor:pointer;font-size:1rem">Unlock ❤️</button></form></div>';
            return;
        }
    }
    if (!$is_owner && function_exists('track_view') && !empty($page['id'])) {
        try { track_view($page['id'], $pdo); } catch (\Throwable $e) {}
    }

    // Media resolution
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    $media = function ($u) use ($base) {
        if (empty($u)) return '';
        if (preg_match('#^https?://#i', $u)) return $u;
        return $base . ltrim($u, '/');
    };
    $music_audio  = $media($c['music']['audio_url'] ?? '');
    $letter_audio = $media($c['letter']['audio_url'] ?? '');
    $voice_audio  = $media($c['voice']['audio_url'] ?? '');
    $video_url    = $media($c['video']['url'] ?? '');
    $video_poster = $media($c['video']['poster'] ?? '');

    // Resolve image lists for the photo pages
    $img_url = function ($im) use ($media) {
        $u = is_array($im) ? ($im['medium'] ?? $im['path'] ?? '') : $im;
        return $media($u);
    };
    $gallery_imgs = [];
    foreach (($c['gallery']['images'] ?? []) as $im) { $u = $img_url($im); if ($u) $gallery_imgs[] = $u; }
    $gallery_imgs = array_slice($gallery_imgs, 0, 10);
    $fav_photos = [];
    foreach (($c['favorites']['photos'] ?? []) as $ph) { $u = $img_url($ph['img'] ?? $ph); if ($u) $fav_photos[] = ['img' => $u, 'caption' => is_array($ph) ? ($ph['caption'] ?? '') : '']; }
    $moment_items = [];
    foreach (($c['moments']['items'] ?? []) as $it) { $u = $img_url($it['img'] ?? ''); if ($u || !empty($it['title'])) $moment_items[] = ['img' => $u, 'title' => $it['title'] ?? '', 'desc' => $it['desc'] ?? '']; }
    $wall_imgs = [];
    foreach (($c['photo_wall']['images'] ?? []) as $im) { $u = $img_url($im); if ($u) $wall_imgs[] = $u; }
    $wall_imgs = array_slice($wall_imgs, 0, 12);
    $heart_imgs = [];
    foreach (($c['heart']['images'] ?? []) as $im) { $u = $img_url($im); if ($u) $heart_imgs[] = $u; }
    $heart_imgs = array_slice($heart_imgs, 0, 12);
    $puzzle_img = $img_url($c['puzzle']['image'] ?? '');

    // Which pages are shown, in order (data-driven navigation)
    $slides = [];
    if (!empty($c['welcome']['enabled']))     $slides[] = 'page-welcome';
    if (!empty($c['surprise']['enabled']))    $slides[] = 'page-surprise';
    if (!empty($c['journey']['enabled']) && !empty($c['journey']['items']))  $slides[] = 'page-journey';
    if (!empty($c['calendar']['enabled']) && !empty($c['calendar']['dates'])) $slides[] = 'page-calendar';
    if (!empty($c['gallery']['enabled']) && $gallery_imgs)   $slides[] = 'page-gallery';
    if (!empty($c['photo_wall']['enabled']) && $wall_imgs)   $slides[] = 'page-photowall';
    if (!empty($c['favorites']['enabled']) && $fav_photos)   $slides[] = 'page-favorites';
    if (!empty($c['moments']['enabled']) && $moment_items)   $slides[] = 'page-moments';
    if (!empty($c['heart']['enabled']) && $heart_imgs)       $slides[] = 'page-heart';
    if (!empty($c['letter']['enabled']))      $slides[] = 'page-letter';
    if (!empty($c['voice']['enabled']) && $voice_audio) $slides[] = 'page-voice';
    if (!empty($c['video']['enabled']) && $video_url)   $slides[] = 'page-video';
    if (!empty($c['scratch']['enabled']))     $slides[] = 'page-scratch';
    if (!empty($c['secret_pw']['enabled']) && !empty($c['secret_pw']['password'])) $slides[] = 'page-secretpw';
    if (!empty($c['puzzle']['enabled']) && $puzzle_img)      $slides[] = 'page-puzzle';
    if (!empty($c['quiz']['enabled']) && !empty($c['quiz']['questions'])) $slides[] = 'page-quiz';
    if (!empty($c['love_meter']['enabled']))  $slides[] = 'page-lovemeter';
    if (!empty($c['destination']['enabled'])) $slides[] = 'page-destination';
    if (!empty($c['reasons']['enabled']) && !empty($c['reasons']['items'])) $slides[] = 'page-reasons';
    if (!empty($c['future']['enabled']) && !empty($c['future']['items']))   $slides[] = 'page-future';
    if (!empty($c['ring']['enabled']))        $slides[] = 'page-ring';
    if (!empty($c['countdown']['enabled']))   $slides[] = 'page-countdown';
    if (!empty($c['scene']['enabled']))       $slides[] = 'page-scene';
    if (!empty($c['final']['enabled']))       $slides[] = 'page-final';
    if (!empty($c['celebration']['enabled'])) $slides[] = 'page-celebration';
    if (!empty($c['thankyou']['enabled']))    $slides[] = 'page-thankyou';
    if (empty($slides)) $slides[] = 'page-welcome';

    $col = $c['colors'];
    $e = function ($s) { return function_exists('h') ? h($s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $raw = function ($s) { return pc_format($s); };
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($c['page_title']) ?></title>
<meta property="og:title" content="<?= $e($c['page_title']) ?>">
<meta property="og:description" content="A little something made with love ❤️">
<meta property="og:type" content="website">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=DM+Sans:wght@300;400;500&family=Satisfy&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --rose: <?= $e($col['rose']) ?>; --deep: <?= $e($col['deep']) ?>; --gold: <?= $e($col['gold']) ?>;
    --blush: <?= $e($col['blush']) ?>; --cream: <?= $e($col['cream']) ?>; --text: <?= $e($col['text']) ?>;
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  html,body { width:100%; height:100%; background:var(--deep); font-family:'DM Sans',sans-serif; color:#fff; overflow:hidden; }

  /* ── page system ── */
  .page { position:fixed; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:22px; opacity:0; pointer-events:none; transition:opacity .8s ease; overflow-y:auto; }
  .page.active { opacity:1; pointer-events:all; }
  .glass { background:rgba(255,255,255,0.06); border:1px solid rgba(255,180,195,0.18); border-radius:26px; padding:38px 30px; max-width:520px; width:100%; text-align:center; backdrop-filter:blur(14px); box-shadow:0 30px 70px rgba(0,0,0,0.45); position:relative; z-index:2; }
  .label { font-size:0.7rem; text-transform:uppercase; letter-spacing:2.5px; color:#e79bb0; margin-bottom:8px; }
  .script { font-family:'Satisfy',cursive; font-size:clamp(1.8rem,6vw,2.6rem); color:#ffb3c1; }
  .serif { font-family:'Cormorant Garamond',serif; }
  .btn { display:inline-block; margin-top:26px; background:linear-gradient(135deg,var(--rose),#c4184e); color:#fff; border:none; border-radius:50px; padding:15px 40px; font-size:1.02rem; font-weight:500; font-family:'DM Sans',sans-serif; cursor:pointer; box-shadow:0 10px 30px rgba(232,64,90,0.45); transition:transform .2s, box-shadow .2s; }
  .btn:hover { transform:translateY(-2px); box-shadow:0 14px 36px rgba(232,64,90,0.55); }
  .btn-ghost { background:transparent; border:none; color:#c98da0; text-decoration:underline; font-size:0.82rem; cursor:pointer; margin-top:10px; font-family:'DM Sans',sans-serif; }

  /* ── ambient layers ── */
  .fx { position:fixed; inset:0; pointer-events:none; overflow:hidden; z-index:1; }
  .petal { position:absolute; top:-40px; font-size:1.2rem; opacity:.5; animation:fall linear infinite; }
  @keyframes fall { to { transform:translateY(112vh) rotate(360deg); opacity:0; } }
  .bokeh { position:absolute; border-radius:50%; filter:blur(14px); opacity:.35; animation:bokehFloat 14s ease-in-out infinite; }
  @keyframes bokehFloat { 0%,100%{ transform:translateY(0) scale(1);} 50%{ transform:translateY(-30px) scale(1.15);} }

  /* ── backgrounds per page ── */
  #page-welcome { background:radial-gradient(1200px 800px at 50% 20%, #3a0d20 0%, #12030b 60%); }
  #page-surprise { background:radial-gradient(900px 700px at 50% 30%, #2a0a18, #12030b); }
  #page-letter { background:linear-gradient(160deg,#1a0810,#2d0f1e); }
  #page-voice { background:radial-gradient(900px 700px at 50% 25%, #2d0f1e, #12030b); }
  #page-video { background:linear-gradient(160deg,#0c0207,#1c0812); }
  #page-scene { background:linear-gradient(180deg,#1a0a1e 0%,#2a0f22 55%,#3a1226 100%); }
  #page-final { background:radial-gradient(1000px 800px at 50% 30%, #3a0d20, #12030b); }
  #page-celebration { background:radial-gradient(1000px 800px at 50% 20%, #3a1226, #12030b); }

  /* ── welcome ── */
  .wl-name { font-family:'Cormorant Garamond',serif; font-weight:300; font-size:clamp(2.6rem,11vw,4rem); color:#fff; line-height:1.05; min-height:1.1em; }
  .wl-name .cursor { display:inline-block; width:3px; background:var(--rose); margin-left:3px; animation:blink .8s step-end infinite; }
  @keyframes blink { 50%{ opacity:0; } }
  .wl-sub { color:rgba(255,205,215,0.7); font-style:italic; margin-top:14px; opacity:0; transition:opacity 1s .3s; }
  .wl-sub.show { opacity:1; }
  .pulse-btn { animation:pulseGrow 1.8s ease-in-out infinite; }
  @keyframes pulseGrow { 0%,100%{ transform:scale(1); box-shadow:0 10px 30px rgba(232,64,90,.45);} 50%{ transform:scale(1.06); box-shadow:0 16px 42px rgba(232,64,90,.6);} }

  /* ── gift ── */
  .gift { width:150px; height:150px; margin:10px auto 0; position:relative; cursor:pointer; transition:transform .3s; }
  .gift:active { transform:scale(.96); }
  .gift-lid { position:absolute; top:26px; left:5px; width:140px; height:34px; background:var(--gift); border-radius:8px; transition:transform .7s cubic-bezier(.5,-0.4,.3,1.4); transform-origin:center bottom; z-index:3; box-shadow:0 6px 12px rgba(0,0,0,.3); }
  .gift-base { position:absolute; bottom:0; left:5px; width:140px; height:96px; background:var(--gift); border-radius:10px; filter:brightness(.85); overflow:hidden; }
  .gift-ribbon-v { position:absolute; top:26px; left:50%; transform:translateX(-50%); width:22px; height:124px; background:rgba(255,255,255,.85); z-index:4; transition:opacity .4s; }
  .gift.open .gift-lid { transform:translateY(-70px) rotate(-16deg); }
  .gift.open .gift-ribbon-v { opacity:0; }
  .gift-glow { position:absolute; inset:0; border-radius:50%; box-shadow:0 0 0 0 rgba(244,197,107,.6); animation:giftPulse 2s infinite; }
  @keyframes giftPulse { 70%{ box-shadow:0 0 0 26px rgba(244,197,107,0);} 100%{ box-shadow:0 0 0 0 rgba(244,197,107,0);} }

  /* ── letter ── */
  .paper { border-radius:14px; padding:30px 26px; text-align:left; margin-top:18px; max-height:52vh; overflow-y:auto; position:relative; box-shadow:inset 0 0 40px rgba(0,0,0,.06); }
  .paper p { margin-bottom:12px; }
  .type-cursor { display:inline-block; width:2px; height:1em; background:var(--rose); vertical-align:text-bottom; animation:blink .8s step-end infinite; }
  .envelope { width:170px; height:110px; margin:8px auto; position:relative; cursor:pointer; }
  .env-body { position:absolute; inset:0; background:linear-gradient(#e8405a,#c4184e); border-radius:8px; box-shadow:0 8px 20px rgba(232,64,90,.4); }
  .env-flap { position:absolute; top:0; left:0; width:0; height:0; border-left:85px solid transparent; border-right:85px solid transparent; border-top:60px solid #ff7c93; transform-origin:top; transition:transform .6s; z-index:3; }
  .envelope.open .env-flap { transform:rotateX(180deg); }
  .env-seal { position:absolute; top:44px; left:50%; transform:translateX(-50%); width:34px; height:34px; border-radius:50%; background:var(--gold); display:flex; align-items:center; justify-content:center; font-size:1rem; z-index:4; box-shadow:0 2px 6px rgba(0,0,0,.3); }
  .envelope.open .env-seal { opacity:0; }

  /* ── voice ── */
  .vp { background:linear-gradient(135deg,#1a0810,#2d0f1e); border-radius:22px; padding:26px; margin-top:16px; }
  .vp-avatar { width:74px; height:74px; border-radius:50%; margin:0 auto 8px; background:linear-gradient(135deg,var(--rose),#ff8fa3); display:flex; align-items:center; justify-content:center; font-size:2rem; box-shadow:0 0 0 0 rgba(232,64,90,.6); }
  .vp-avatar.playing { animation:giftPulse 1.6s infinite; }
  .waveform { display:flex; align-items:center; gap:2px; height:44px; margin:16px 0; cursor:pointer; }
  .wbar { flex:1; min-width:2px; border-radius:2px; background:#4a2030; }
  .wbar.on { background:var(--rose); }
  .vp-row { display:flex; align-items:center; gap:12px; justify-content:center; }
  .vp-btn { width:52px; height:52px; border-radius:50%; border:none; background:var(--rose); color:#fff; font-size:1.2rem; cursor:pointer; box-shadow:0 6px 18px rgba(232,64,90,.5); }
  .vp-meta { color:#a07080; font-size:0.78rem; }

  /* ── video ── */
  .video-frame { margin-top:16px; border-radius:18px; overflow:hidden; padding:6px; background:linear-gradient(135deg,rgba(232,64,90,.4),rgba(255,143,163,.2)); box-shadow:0 18px 50px rgba(232,64,90,.35); position:relative; }
  .video-frame video { width:100%; display:block; border-radius:13px; background:#000; max-height:64vh; }
  .video-frame.portrait video { max-height:70vh; width:auto; margin:0 auto; }

  /* ── proposal scene ── */
  .scene-stage { position:relative; width:100%; height:44vh; min-height:280px; margin-top:6px; }
  .ground { position:absolute; bottom:0; left:0; right:0; height:34%; background:linear-gradient(180deg,transparent, rgba(0,0,0,.5)); border-radius:50% 50% 0 0/40px 40px 0 0; }
  .silhouette { position:absolute; bottom:30%; left:14%; font-size:3.4rem; filter:brightness(0) drop-shadow(0 0 2px rgba(0,0,0,.6)); opacity:0; transition:left 2.2s ease, opacity 1s, transform .6s; }
  .scene-stage.walk .silhouette { left:34%; opacity:.92; }
  .scene-stage.kneel .silhouette { transform:translateY(6px) rotate(8deg); }
  .rose-stem { position:absolute; bottom:34%; left:60%; width:4px; height:0; background:linear-gradient(#4a9a41,#2c5c28); border-radius:2px; transition:height 1.4s cubic-bezier(.2,.8,.3,1); transform-origin:bottom; }
  .scene-stage.grow .rose-stem { height:118px; }
  .rose-leaf { position:absolute; bottom:34%; left:60%; width:0; height:11px; background:#3a7d33; border-radius:0 100% 0 100%; transform:translate(4px,-52px) rotate(-18deg); opacity:0; transition:width .55s .6s ease, opacity .5s .6s; }
  .scene-stage.grow .rose-leaf { width:18px; opacity:.9; }
  /* the bud rises from the ground with the growing stem tip, then blooms */
  .rose-bud { position:absolute; bottom:34%; left:60%; transform:translateX(-9px) scale(.25); font-size:2.4rem; opacity:0; transition:bottom 1.4s cubic-bezier(.2,.8,.3,1), transform .8s, opacity .5s; cursor:pointer; z-index:3; filter:drop-shadow(0 4px 8px rgba(0,0,0,.4)); }
  .scene-stage.grow .rose-bud { bottom:calc(34% + 110px); transform:translateX(-9px) scale(.9); opacity:1; }
  .scene-stage.bloom .rose-bud { transform:translateX(-9px) scale(1.5); }
  @keyframes budSway { 0%,100%{ margin-left:0;} 50%{ margin-left:3px;} }
  .scene-stage.grow .rose-bud { animation:budSway 3s ease-in-out infinite 1.4s; }
  .scene-line { color:rgba(255,205,215,.85); font-style:italic; font-family:'Cormorant Garamond',serif; font-size:1.15rem; margin-top:8px; min-height:1.4em; opacity:0; transition:opacity 1s; }
  .scene-line.show { opacity:1; }

  /* ── final proposal ── */
  .final-q { font-family:'Playfair Display',serif; font-weight:600; font-size:clamp(1.6rem,6vw,2.4rem); color:#fff; line-height:1.2; }
  .final-btns { display:flex; gap:16px; justify-content:center; margin-top:26px; position:relative; flex-wrap:wrap; min-height:64px; }
  .yes-btn { background:linear-gradient(135deg,#e8405a,#ff7090); color:#fff; border:none; border-radius:50px; padding:16px 44px; font-size:1.15rem; font-weight:600; cursor:pointer; box-shadow:0 10px 30px rgba(232,64,90,.5); animation:pulseGrow 1.6s ease-in-out infinite; }
  .no-btn { background:rgba(255,255,255,.1); color:#e6b8c4; border:1px solid rgba(255,180,195,.35); border-radius:50px; padding:16px 40px; font-size:1.05rem; cursor:pointer; transition:transform .25s ease; }

  /* ── reply ── */
  .reply-box { margin-top:24px; background:rgba(255,255,255,.06); border:1px solid rgba(255,180,195,.25); border-radius:18px; padding:20px; text-align:left; }
  .reply-tabs { display:flex; gap:6px; flex-wrap:wrap; justify-content:center; margin-bottom:12px; }
  .reply-tab { padding:7px 12px; border-radius:20px; font-size:0.76rem; border:1px solid rgba(255,180,195,.3); background:transparent; color:#e6b8c4; cursor:pointer; }
  .reply-tab.active { background:var(--rose); color:#fff; border-color:var(--rose); }
  .reply-field { width:100%; padding:11px 14px; border-radius:12px; border:1px solid rgba(255,180,195,.25); background:rgba(255,255,255,.92); color:#3a1a22; font-size:0.9rem; outline:none; font-family:'DM Sans',sans-serif; }
  .reply-send { width:100%; margin-top:12px; background:linear-gradient(135deg,var(--rose),#c4184e); color:#fff; border:none; border-radius:50px; padding:13px; font-weight:600; cursor:pointer; }

  /* ── particles: hearts, confetti, fireflies, butterflies ── */
  .heart-p { position:fixed; pointer-events:none; z-index:999; font-size:1.6rem; animation:heartFloat 1.6s ease-out forwards; }
  @keyframes heartFloat { 0%{ opacity:1; transform:translateY(0) scale(1);} 100%{ opacity:0; transform:translateY(-90px) scale(.3);} }
  .confetti-p { position:fixed; top:-16px; width:10px; height:14px; z-index:998; pointer-events:none; border-radius:2px; animation:confFall linear forwards; }
  @keyframes confFall { 0%{ transform:translateY(-16px) rotate(0);opacity:1;} 100%{ transform:translateY(106vh) rotate(720deg);opacity:0;} }
  .firefly { position:fixed; width:6px; height:6px; border-radius:50%; background:#ffe08a; box-shadow:0 0 10px 3px rgba(255,224,138,.8); pointer-events:none; z-index:2; animation:ffly 6s ease-in-out infinite; }
  @keyframes ffly { 0%,100%{ transform:translate(0,0); opacity:.2;} 50%{ transform:translate(30px,-40px); opacity:1;} }

  /* ── journey ── */
  #page-journey { background:radial-gradient(900px 700px at 50% 25%, #2a0a18, #12030b); }
  .jrn { text-align:left; margin-top:16px; position:relative; padding-left:4px; }
  .jrn-line { position:absolute; left:22px; top:8px; bottom:8px; width:2px; background:linear-gradient(var(--rose), rgba(232,64,90,.1)); transform:scaleY(0); transform-origin:top; transition:transform 1.3s ease; }
  #page-journey.active .jrn-line { transform:scaleY(1); }
  .jrn-item { display:flex; gap:16px; margin-bottom:22px; align-items:flex-start; opacity:0; transform:translateX(-30px); transition:opacity .6s, transform .6s; }
  .jrn-item.show { opacity:1; transform:none; }
  .jrn-dot { width:40px; height:40px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,var(--rose),#ff8fa3); display:flex; align-items:center; justify-content:center; font-size:1.1rem; box-shadow:0 0 18px rgba(232,64,90,.5); z-index:2; }
  .jrn-item.show .jrn-dot { animation:jbeat 1.8s ease-in-out infinite; }
  @keyframes jbeat { 0%,100%{ transform:scale(1);} 50%{ transform:scale(1.14);} }
  .jrn-date { font-size:.72rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--gold); margin-bottom:3px; text-shadow:0 0 8px rgba(244,197,107,.5); }
  .jrn-title { font-family:'Cormorant Garamond',serif; font-size:1.18rem; color:#fff; }

  /* ── gallery ── */
  #page-gallery { background:#160611; }
  .gal { margin-top:16px; display:grid; gap:8px; }
  .gal.n1 { grid-template-columns:1fr; } .gal.n2 { grid-template-columns:1fr 1fr; } .gal.n3, .gal.n4 { grid-template-columns:1fr 1fr; } .gal.many { grid-template-columns:repeat(3,1fr); }
  .gal-cell { position:relative; overflow:hidden; border-radius:14px; cursor:pointer; aspect-ratio:1/1; background:#2a1018; opacity:0; box-shadow:0 8px 20px rgba(0,0,0,.35); }
  .gal.n1 .gal-cell { aspect-ratio:4/3; }
  .gal-cell.show { animation:galIn .7s forwards; }
  @keyframes galIn { 0%{ opacity:0; transform:scale(.9); filter:blur(8px);} 100%{ opacity:1; transform:scale(1); filter:blur(0);} }
  .gal-cell img { width:100%; height:100%; object-fit:cover; transition:transform .5s; }
  .gal-cell:hover img { transform:scale(1.08); }
  .lightbox { position:fixed; inset:0; background:rgba(8,2,5,.95); z-index:1000; display:none; align-items:center; justify-content:center; flex-direction:column; padding:20px; }
  .lightbox.open { display:flex; }
  .lightbox img { max-width:92vw; max-height:80vh; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.6); }
  .lb-close { position:absolute; top:16px; right:20px; background:none; border:none; color:#fff; font-size:2rem; cursor:pointer; }

  /* ── favorites ── */
  #page-favorites { background:radial-gradient(900px 700px at 50% 20%, #2d0f1e, #12030b); }
  .fav-card { position:relative; border-radius:18px; overflow:hidden; margin:14px auto; max-width:330px; box-shadow:0 20px 50px rgba(0,0,0,.5); opacity:0; transition:opacity .8s, transform .8s cubic-bezier(.2,.8,.3,1); }
  .fav-card.fromL { transform:translateX(-70px) rotate(-7deg) scale(.9); } .fav-card.fromR { transform:translateX(70px) rotate(7deg) scale(.9); }
  .fav-card.show { opacity:1; transform:none; }
  .fav-card img { width:100%; display:block; }
  .fav-cap { position:absolute; bottom:0; left:0; right:0; padding:26px 16px 12px; background:linear-gradient(transparent, rgba(0,0,0,.78)); font-family:'Cormorant Garamond',serif; font-size:1.05rem; text-align:left; }

  /* ── moments ── */
  #page-moments { background:linear-gradient(160deg,#1a0810,#2d0f1e); }
  .mom-card { display:flex; gap:14px; align-items:center; background:rgba(255,255,255,.05); border:1px solid rgba(255,180,195,.15); border-radius:16px; padding:12px; margin-bottom:12px; text-align:left; opacity:0; transform:translateY(26px); transition:opacity .6s, transform .6s; }
  .mom-card.show { opacity:1; transform:none; }
  .mom-card img { width:72px; height:72px; object-fit:cover; border-radius:12px; flex-shrink:0; }
  .mom-t { font-family:'Cormorant Garamond',serif; font-size:1.12rem; color:#ffb3c1; }
  .mom-d { font-size:.85rem; color:rgba(255,205,215,.65); margin-top:2px; }

  /* ── reasons (flip cards) ── */
  #page-reasons { background:radial-gradient(900px 700px at 50% 25%, #3a0d20, #12030b); }
  .reasons-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:16px; }
  .reason { perspective:700px; height:92px; opacity:0; transform:translateY(20px); transition:opacity .5s, transform .5s; }
  .reason.show { opacity:1; transform:none; }
  .reason-inner { position:relative; width:100%; height:100%; transform-style:preserve-3d; transition:transform .8s; }
  .reason.flip .reason-inner { transform:rotateY(180deg); }
  .reason-face { position:absolute; inset:0; -webkit-backface-visibility:hidden; backface-visibility:hidden; border-radius:14px; display:flex; align-items:center; justify-content:center; padding:10px; text-align:center; }
  .reason-front { background:linear-gradient(135deg,var(--rose),#c4184e); font-size:1.6rem; box-shadow:0 8px 20px rgba(232,64,90,.4); }
  .reason-back { background:rgba(255,255,255,.08); border:1px solid rgba(255,180,195,.3); transform:rotateY(180deg); font-family:'Cormorant Garamond',serif; font-size:1rem; color:#fff; }

  /* ── future ── */
  #page-future { background:linear-gradient(180deg,#0a0620 0%,#1a0a2e 60%,#2a0f22 100%); }
  .moon { position:absolute; top:7%; right:12%; font-size:2.6rem; filter:drop-shadow(0 0 16px rgba(255,240,180,.6)); z-index:1; }
  .star2 { position:absolute; width:2px; height:2px; background:#fff; border-radius:50%; animation:twinkle 3s infinite; z-index:1; }
  @keyframes twinkle { 0%,100%{ opacity:.2;} 50%{ opacity:1;} }
  .fut-item { display:flex; gap:14px; align-items:center; text-align:left; margin-bottom:16px; opacity:0; transform:translateY(22px); transition:opacity .6s, transform .6s; }
  .fut-item.show { opacity:1; transform:none; }
  .fut-icon { font-size:1.5rem; width:46px; height:46px; border-radius:50%; background:rgba(255,255,255,.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 0 14px rgba(124,58,237,.3); }
  .fut-text { font-family:'Cormorant Garamond',serif; font-size:1.16rem; }

  /* ── ring reveal ── */
  #page-ring { background:radial-gradient(800px 700px at 50% 42%, #1a1020, #050208); }
  .ringbox { width:150px; height:124px; margin:18px auto 8px; position:relative; cursor:pointer; }
  .ring-rays { position:absolute; top:-34px; left:50%; transform:translateX(-50%); width:210px; height:210px; opacity:0; background:conic-gradient(from 0deg, rgba(244,197,107,.5), transparent 25%, rgba(244,197,107,.5) 50%, transparent 75%, rgba(244,197,107,.5)); border-radius:50%; filter:blur(3px); transition:opacity .6s .5s; pointer-events:none; }
  .ringbox.open .ring-rays { opacity:.6; animation:rayspin 9s linear infinite; }
  @keyframes rayspin { to { transform:translateX(-50%) rotate(360deg); } }
  .ring-jewel { position:absolute; top:22px; left:50%; transform:translateX(-50%) scale(0); font-size:2.8rem; transition:transform .8s .35s cubic-bezier(.3,1.5,.5,1); z-index:3; filter:drop-shadow(0 0 12px rgba(244,197,107,.7)); }
  .ringbox.open .ring-jewel { transform:translateX(-50%) scale(1) translateY(-16px); }
  .ringbox-lid { position:absolute; top:0; left:5px; width:140px; height:52px; background:linear-gradient(#3a2a1a,#241a10); border-radius:10px 10px 4px 4px; transform-origin:top center; transition:transform .8s cubic-bezier(.5,-0.3,.3,1.3); z-index:4; box-shadow:0 4px 10px rgba(0,0,0,.5); }
  .ringbox.open .ringbox-lid { transform:translateY(-6px) rotateX(-125deg); }
  .ringbox-base { position:absolute; bottom:0; left:5px; width:140px; height:80px; background:linear-gradient(#241a10,#160f08); border-radius:6px 6px 12px 12px; z-index:2; }

  /* ── photo wall ── */
  #page-photowall { background:#100510; }
  .wall { columns:3; column-gap:6px; margin-top:14px; }
  .wall img { width:100%; border-radius:8px; margin-bottom:6px; display:block; opacity:0; transform:scale(.85); transition:opacity .5s, transform .5s; }
  .wall img.show { opacity:1; transform:none; }

  /* ── heart formation ── */
  #page-heart { background:radial-gradient(800px 700px at 50% 40%, #2d0f1e, #0a0207); }
  .heart-wrap { position:relative; width:250px; height:230px; margin:14px auto; cursor:pointer; }
  .heart-glow { position:absolute; inset:-8px; border-radius:50%; box-shadow:0 0 46px 12px rgba(232,64,90,.55); opacity:0; transition:opacity 1s .5s; pointer-events:none; }
  #page-heart.active .heart-glow { opacity:1; animation:jbeat 2s ease-in-out infinite 1.2s; }
  .heart-mosaic { width:100%; height:100%; -webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 29.6'%3E%3Cpath d='M16 29.6C16 29.6 0 18 0 8 0 3 3 0 7 0 11 0 16 5 16 5 16 5 21 0 25 0 29 0 32 3 32 8 32 18 16 29.6 16 29.6Z'/%3E%3C/svg%3E") center/contain no-repeat; mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 29.6'%3E%3Cpath d='M16 29.6C16 29.6 0 18 0 8 0 3 3 0 7 0 11 0 16 5 16 5 16 5 21 0 25 0 29 0 32 3 32 8 32 18 16 29.6 16 29.6Z'/%3E%3C/svg%3E") center/contain no-repeat; opacity:0; transform:scale(.35); transition:opacity .8s, transform .9s cubic-bezier(.3,1.3,.5,1); }
  #page-heart.active .heart-mosaic { opacity:1; transform:scale(1); }
  .hm-grid { display:grid; grid-template-columns:repeat(4,1fr); grid-auto-rows:1fr; width:100%; height:100%; }
  .hm-grid img { width:100%; height:100%; object-fit:cover; }

  /* ── scratch card ── */
  #page-scratch { background:radial-gradient(800px 700px at 50% 30%, #2a0a18, #12030b); }
  .scratch-wrap { position:relative; width:300px; max-width:86vw; height:180px; margin:16px auto; border-radius:16px; overflow:hidden; box-shadow:0 12px 30px rgba(0,0,0,.4); }
  .scratch-msg { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; padding:20px; background:linear-gradient(135deg,#fff8f5,#ffe3ea); color:#3a1a22; font-family:'Cormorant Garamond',serif; font-size:1.3rem; line-height:1.5; }
  #scratchCanvas { position:absolute; inset:0; width:100%; height:100%; cursor:grab; touch-action:none; }

  /* ── secret password ── */
  #page-secretpw { background:radial-gradient(800px 700px at 50% 30%, #1a0a22, #0a0410); }
  .pw-input { width:100%; padding:13px 16px; border-radius:12px; border:1px solid rgba(255,180,195,.3); background:rgba(255,255,255,.92); color:#3a1a22; text-align:center; font-size:1rem; margin-top:14px; outline:none; }
  .pw-feedback { min-height:20px; margin-top:10px; font-size:.85rem; font-style:italic; color:#ffb3c1; }

  /* ── puzzle ── */
  #page-puzzle { background:radial-gradient(800px 700px at 50% 30%, #12030b, #2a0a18); }
  .puz-grid { width:280px; max-width:82vw; aspect-ratio:1/1; margin:16px auto; display:grid; grid-template-columns:1fr 1fr; grid-template-rows:1fr 1fr; gap:4px; }
  .puz-tile { background-size:200% 200%; border-radius:8px; cursor:pointer; outline:2px solid transparent; transition:outline .15s, transform .2s; }
  .puz-tile.sel { outline:2px solid var(--gold); transform:scale(.96); }

  /* ── quiz ── */
  #page-quiz { background:radial-gradient(800px 700px at 50% 25%, #2a0a18, #12030b); }
  .quiz-q { font-family:'Cormorant Garamond',serif; font-size:1.3rem; margin-top:16px; }
  .quiz-opt { display:block; width:100%; margin-top:10px; padding:13px; border-radius:12px; background:rgba(255,255,255,.06); border:1px solid rgba(255,180,195,.2); color:#fff; cursor:pointer; text-align:left; font-size:.95rem; transition:background .2s; }
  .quiz-opt:hover { background:rgba(232,64,90,.15); }
  .quiz-opt.correct { background:rgba(46,160,90,.35); border-color:#3ad07a; }
  .quiz-opt.wrong { background:rgba(220,60,60,.35); border-color:#e8405a; }

  /* ── love meter ── */
  #page-lovemeter { background:radial-gradient(800px 700px at 50% 30%, #3a0d20, #12030b); }
  .meter { width:200px; height:200px; margin:18px auto; position:relative; }
  .meter svg { transform:rotate(-90deg); }
  .meter-track { fill:none; stroke:rgba(255,255,255,.1); stroke-width:14; }
  .meter-bar { fill:none; stroke:url(#lmgrad); stroke-width:14; stroke-linecap:round; transition:stroke-dashoffset 1.6s cubic-bezier(.2,.8,.3,1); }
  .meter-num { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-family:'Playfair Display',serif; font-size:2.4rem; color:#fff; }

  /* ── countdown ── */
  #page-countdown { background:radial-gradient(900px 800px at 50% 40%, #2a0a18, #050208); }
  .cd-num { font-family:'Playfair Display',serif; font-size:7rem; color:#fff; height:1.1em; }
  .cd-num.pop { animation:cdPop .9s ease; }
  @keyframes cdPop { 0%{ opacity:0; transform:scale(.4);} 30%{ opacity:1; transform:scale(1.15);} 70%{ opacity:1; transform:scale(1);} 100%{ opacity:0; transform:scale(1.5);} }
  .cd-msg { font-family:'Satisfy',cursive; font-size:2.2rem; color:#ffb3c1; opacity:0; transition:opacity 1s; }
  .cd-msg.show { opacity:1; }

  /* ── calendar ── */
  #page-calendar { background:linear-gradient(160deg,#1a0810,#2d0f1e); }
  .cal-item { display:flex; align-items:center; gap:14px; background:rgba(255,255,255,.05); border:1px solid rgba(255,180,195,.15); border-radius:14px; padding:12px; margin-bottom:10px; text-align:left; opacity:0; transform:translateX(-22px); transition:opacity .5s, transform .5s; }
  .cal-item.show { opacity:1; transform:none; }
  .cal-badge { background:linear-gradient(135deg,var(--rose),#c4184e); border-radius:10px; padding:8px 12px; text-align:center; flex-shrink:0; min-width:66px; font-weight:700; font-size:.92rem; }
  .cal-label { font-family:'Cormorant Garamond',serif; font-size:1.12rem; }

  /* ── destination ── */
  #page-destination { background:#081218; }
  .map { width:300px; max-width:86vw; height:200px; margin:16px auto; border-radius:16px; position:relative; overflow:hidden; background:linear-gradient(135deg,#14343a,#0c2228); cursor:pointer; box-shadow:inset 0 0 40px rgba(0,0,0,.4); }
  .map::before { content:''; position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.06) 1px,transparent 1px); background-size:26px 26px; }
  .map-pin { position:absolute; top:50%; left:50%; transform:translate(-50%,-140%) scale(0); font-size:2.6rem; transition:transform .6s cubic-bezier(.3,1.5,.5,1); filter:drop-shadow(0 4px 6px rgba(0,0,0,.5)); z-index:2; }
  .map.open .map-pin { transform:translate(-50%,-100%) scale(1); }
  .dest-addr { opacity:0; transform:translateY(10px); transition:opacity .6s, transform .6s; margin-top:14px; }
  .dest-addr.show { opacity:1; transform:none; }

  /* ── thank you ── */
  #page-thankyou { background:radial-gradient(900px 800px at 50% 30%, #2d0f1e, #0a0207); }

  /* ── loader ── */
  #pcLoader { position:fixed; inset:0; z-index:3000; display:flex; flex-direction:column; align-items:center; justify-content:center; background:radial-gradient(1000px 700px at 50% 25%, #2d0f1e, #12030b); transition:opacity .6s; }
  #pcLoader.hide { opacity:0; pointer-events:none; }
  .pcl-rose { font-size:3.6rem; animation:pclBob 1.3s ease-in-out infinite; }
  @keyframes pclBob { 0%,100%{ transform:translateY(0) rotate(-4deg);} 50%{ transform:translateY(-12px) rotate(4deg);} }
  .pcl-title { font-family:'Cormorant Garamond',serif; color:#ffb3c1; font-size:1.4rem; margin-top:14px; }
  .pcl-bar { width:180px; height:5px; background:rgba(255,255,255,.12); border-radius:4px; margin-top:20px; overflow:hidden; }
  .pcl-fill { height:100%; width:40%; border-radius:4px; background:linear-gradient(90deg,var(--rose),#ff8fa3); animation:pclSlide 1.2s ease-in-out infinite; }
  @keyframes pclSlide { 0%{ margin-left:-40%;} 100%{ margin-left:100%;} }

  /* ── music bar + dots ── */
  .music-bar { position:fixed; bottom:0; left:0; right:0; background:rgba(18,3,11,.95); backdrop-filter:blur(12px); padding:9px 18px; display:flex; align-items:center; gap:12px; z-index:100; border-top:1px solid rgba(232,64,90,.2); }
  .music-info { flex:1; min-width:0; } .music-title { font-size:0.82rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; } .music-artist { color:#a07080; font-size:0.7rem; }
  .music-btn { background:none; border:none; color:#fff; font-size:1.05rem; cursor:pointer; padding:5px; opacity:.85; }
  .music-progress { flex:1; height:3px; background:#4a2030; border-radius:2px; cursor:pointer; } .music-fill { height:100%; width:0; background:var(--rose); border-radius:2px; }
  .dots { position:fixed; bottom:58px; left:50%; transform:translateX(-50%); display:flex; gap:6px; z-index:50; }
  .dot { width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,.2); transition:all .3s; } .dot.active { background:var(--rose); width:18px; border-radius:3px; }
  ::-webkit-scrollbar { width:4px; } ::-webkit-scrollbar-thumb { background:var(--blush); border-radius:2px; }
</style>
</head>
<body>

<!-- Loading -->
<div id="pcLoader">
  <div class="pcl-rose">🌹</div>
  <div class="pcl-title"><?= $e($receiver) ?> ❤️</div>
  <div class="pcl-bar"><div class="pcl-fill"></div></div>
</div>

<?php if (!empty($page['_preview'])): ?>
<div style="position:fixed;top:0;left:0;right:0;z-index:3000;background:#12030b;color:#ffb3c1;padding:9px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:0.78rem;border-bottom:1px solid rgba(232,64,90,0.45);">
  <span>🔍 <b>Preview</b> — this is just a preview (not saved). Newly selected photos/video appear after you publish.</span>
  <button onclick="window.close()" style="flex-shrink:0;background:#e8405a;color:#fff;border:none;border-radius:20px;padding:6px 14px;cursor:pointer;font-weight:600;">✕ Close</button>
</div>
<?php endif; ?>

<div class="fx" id="fx"></div>

<!-- Photo lightbox (gallery) -->
<div class="lightbox" id="pcLightboxEl"><button class="lb-close" onclick="pcCloseLb()">✕</button><img id="lbImg" src="" alt=""></div>

<!-- 1 · WELCOME -->
<?php if (!empty($c['welcome']['enabled'])): ?>
<div class="page" id="page-welcome">
  <div class="glass">
    <div style="font-size:2.6rem; margin-bottom:6px;">🌹</div>
    <div class="wl-name" id="wlName" data-name="<?= $e($receiver) ?>"></div>
    <div class="wl-sub" id="wlSub"><?= $e($c['welcome']['subtitle']) ?></div>
    <button class="btn pulse-btn" onclick="next()"><?= $e($c['welcome']['button']) ?></button>
  </div>
</div>
<?php endif; ?>

<!-- 2 · SMALL SURPRISE -->
<?php if (!empty($c['surprise']['enabled'])): ?>
<div class="page" id="page-surprise">
  <div class="glass">
    <div class="serif" style="font-size:1.4rem; color:#ffd9e0;"><?= $raw($c['surprise']['message']) ?></div>
    <div class="gift" id="giftBox" style="--gift:<?= $e($c['surprise']['gift_color']) ?>" onclick="openGift()">
      <div class="gift-glow"></div>
      <div class="gift-lid"></div>
      <div class="gift-ribbon-v"></div>
      <div class="gift-base"></div>
    </div>
    <div style="color:rgba(255,205,215,.5); font-size:0.78rem; margin-top:10px;" id="giftHint">Tap the gift 🎁</div>
    <div id="giftNext" style="display:none;"><button class="btn" onclick="next()"><?= $e($c['surprise']['button']) ?></button></div>
  </div>
</div>
<?php endif; ?>

<!-- 3 · OUR JOURNEY -->
<?php if (in_array('page-journey', $slides)): ?>
<div class="page" id="page-journey">
  <div class="glass">
    <div class="label"><?= $e($c['journey']['label']) ?></div>
    <div class="script"><?= $e($c['journey']['script']) ?></div>
    <div class="jrn">
      <div class="jrn-line"></div>
      <?php foreach ($c['journey']['items'] as $it): ?>
      <div class="jrn-item">
        <div class="jrn-dot"><?= $e($it['icon'] ?? '✨') ?></div>
        <div><div class="jrn-date"><?= $e($it['date'] ?? '') ?></div><div class="jrn-title"><?= $e($it['title'] ?? '') ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- 4 · MEMORY GALLERY -->
<?php if (in_array('page-gallery', $slides)): ?>
<div class="page" id="page-gallery">
  <div class="glass">
    <div class="label"><?= $e($c['gallery']['label']) ?></div>
    <div class="script"><?= $e($c['gallery']['script']) ?></div>
    <div style="color:rgba(255,205,215,.6);font-size:.85rem;margin-top:6px;font-style:italic;"><?= $e($c['gallery']['subtitle']) ?></div>
    <?php $gn = count($gallery_imgs); $galcls = $gn===1?'n1':($gn===2?'n2':($gn===3?'n3':($gn===4?'n4':'many'))); ?>
    <div class="gal <?= $galcls ?>" id="galGrid">
      <?php foreach ($gallery_imgs as $gi => $im): ?>
      <div class="gal-cell" onclick="pcLightbox(<?= (int)$gi ?>)"><img src="<?= $e($im) ?>" loading="lazy" alt="Memory <?= (int)$gi+1 ?>"></div>
      <?php endforeach; ?>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- 5 · FAVOURITE PHOTOS -->
<?php if (in_array('page-favorites', $slides)): ?>
<div class="page" id="page-favorites">
  <div class="glass" style="max-width:400px;">
    <div class="label"><?= $e($c['favorites']['label']) ?></div>
    <div class="script"><?= $e($c['favorites']['script']) ?></div>
    <div id="favWrap" style="margin-top:8px;">
      <?php foreach ($fav_photos as $fi => $ph): ?>
      <div class="fav-card <?= $fi % 2 ? 'fromR' : 'fromL' ?>"><img src="<?= $e($ph['img']) ?>" loading="lazy" alt=""><?php if (!empty($ph['caption'])): ?><div class="fav-cap"><?= $e($ph['caption']) ?></div><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- 6 · BEAUTIFUL MOMENTS -->
<?php if (in_array('page-moments', $slides)): ?>
<div class="page" id="page-moments">
  <div class="glass">
    <div class="label"><?= $e($c['moments']['label']) ?></div>
    <div class="script"><?= $e($c['moments']['script']) ?></div>
    <div id="momWrap" style="margin-top:16px;">
      <?php foreach ($moment_items as $it): ?>
      <div class="mom-card"><?php if (!empty($it['img'])): ?><img src="<?= $e($it['img']) ?>" loading="lazy" alt=""><?php endif; ?><div><div class="mom-t"><?= $e($it['title']) ?></div><div class="mom-d"><?= $e($it['desc']) ?></div></div></div>
      <?php endforeach; ?>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- 7 · LOVE LETTER -->
<?php if (!empty($c['letter']['enabled'])): ?>
<div class="page" id="page-letter">
  <div class="glass">
    <div class="label"><?= $e($c['letter']['label']) ?></div>
    <div class="script"><?= $e($c['letter']['script']) ?></div>
    <div class="envelope" id="envelope" onclick="openEnvelope()">
      <div class="env-body"></div>
      <div class="env-flap"></div>
      <div class="env-seal">❤</div>
    </div>
    <?php if ($letter_audio): ?><audio id="letterAudio" src="<?= $e($letter_audio) ?>" preload="metadata"></audio><?php endif; ?>
    <div id="letterWrap" style="display:none;">
      <div class="paper" id="letterPaper" style="background:<?= $e($c['letter']['paper_color']) ?>; color:#3a1a22; font-family:'<?= $e($c['letter']['font']) ?>',serif; font-size:1.08rem; line-height:1.9;"></div>
      <div style="color:#c98da0; font-size:0.9rem; margin-top:10px; text-align:right; font-family:'Satisfy',cursive;"><?= $e($c['letter']['signature']) ?></div>
      <button class="btn" onclick="next()">Continue 💕</button>
    </div>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
  <script type="application/json" id="letterData"><?= json_encode(array_values($c['letter']['paragraphs'] ?? []), JSON_UNESCAPED_UNICODE) ?></script>
</div>
<?php endif; ?>

<!-- 4 · VOICE -->
<?php if (!empty($c['voice']['enabled']) && $voice_audio): ?>
<div class="page" id="page-voice">
  <div class="glass">
    <div class="label" style="color:#ff8fa3"><?= $e($c['voice']['label']) ?></div>
    <div class="script"><?= $e($c['voice']['script']) ?></div>
    <div class="vp">
      <div class="vp-avatar" id="vpAvatar"><?= $e($c['voice']['avatar']) ?></div>
      <div style="font-weight:500;"><?= $e($c['voice']['name']) ?></div>
      <div class="vp-meta"><?= $e($c['voice']['subtitle']) ?></div>
      <div class="waveform" id="waveform" onclick="seekVoice(event)"></div>
      <div class="vp-row">
        <button class="vp-btn" id="vpBtn" onclick="toggleVoice()">▶</button>
        <span class="vp-meta" id="vpDur">0:00</span>
        <button class="vp-btn" style="width:40px;height:40px;font-size:0.8rem;" id="vpSpeed" onclick="cycleVoiceSpeed()">1x</button>
      </div>
      <audio id="voiceAudio" src="<?= $e($voice_audio) ?>" preload="metadata"></audio>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- 5 · VIDEO -->
<?php if (!empty($c['video']['enabled']) && $video_url): ?>
<div class="page" id="page-video">
  <div class="glass">
    <div class="label" style="color:#ff8fa3"><?= $e($c['video']['label']) ?></div>
    <div class="script"><?= $e($c['video']['script']) ?></div>
    <div style="color:rgba(255,205,215,.6); font-size:0.85rem; margin-top:6px; font-style:italic;"><?= $e($c['video']['subtitle']) ?></div>
    <div class="video-frame" id="videoFrame">
      <video id="pcVideo" src="<?= $e($video_url) ?>" <?= $video_poster ? 'poster="'.$e($video_poster).'"' : '' ?> controls playsinline muted preload="metadata"></video>
    </div>
    <div style="color:rgba(255,205,215,.45); font-size:0.72rem; margin-top:8px;">🔇 Muted — unmute for sound (background music will pause)</div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- 10 · WHY I LOVE YOU -->
<?php if (in_array('page-reasons', $slides)): ?>
<div class="page" id="page-reasons">
  <div class="glass">
    <div class="label"><?= $e($c['reasons']['label']) ?></div>
    <div class="script"><?= $e($c['reasons']['script']) ?></div>
    <div class="reasons-grid" id="reasonsGrid">
      <?php foreach ($c['reasons']['items'] as $rz): ?>
      <div class="reason"><div class="reason-inner"><div class="reason-face reason-front">❤️</div><div class="reason-face reason-back"><?= $e($rz) ?></div></div></div>
      <?php endforeach; ?>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- 11 · OUR FUTURE -->
<?php if (in_array('page-future', $slides)): ?>
<div class="page" id="page-future">
  <div class="moon">🌙</div>
  <div class="glass" style="background:rgba(255,255,255,0.04);">
    <div class="label"><?= $e($c['future']['label']) ?></div>
    <div class="script"><?= $e($c['future']['script']) ?></div>
    <div id="futWrap" style="margin-top:16px;">
      <?php foreach ($c['future']['items'] as $it): ?>
      <div class="fut-item"><div class="fut-icon"><?= $e($it['icon'] ?? '✨') ?></div><div class="fut-text"><?= $e($it['text'] ?? '') ?></div></div>
      <?php endforeach; ?>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- 12 · RING REVEAL -->
<?php if (in_array('page-ring', $slides)): ?>
<div class="page" id="page-ring">
  <div class="glass" style="background:rgba(255,255,255,0.04);">
    <div class="label"><?= $e($c['ring']['label']) ?></div>
    <div class="script" style="color:var(--gold);"><?= $e($c['ring']['script']) ?></div>
    <div class="ringbox" id="ringBox" onclick="openRing()">
      <div class="ring-rays"></div>
      <div class="ring-jewel"><?= $e($c['ring']['ring_emoji']) ?></div>
      <div class="ringbox-base"></div>
      <div class="ringbox-lid"></div>
    </div>
    <div style="color:rgba(255,205,215,.75);font-style:italic;font-family:'Cormorant Garamond',serif;font-size:1.1rem;margin-top:8px;"><?= $raw($c['ring']['text']) ?></div>
    <div style="color:rgba(255,205,215,.4);font-size:.78rem;margin-top:8px;" id="ringHint">Tap the box 💍</div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- 13 · PROPOSAL SCENE -->
<?php if (!empty($c['scene']['enabled'])): ?>
<div class="page" id="page-scene">
  <div class="glass" style="background:transparent; box-shadow:none; border:none; backdrop-filter:none; max-width:560px;">
    <div class="scene-stage" id="sceneStage">
      <div class="silhouette">🚶</div>
      <div class="rose-stem"></div>
      <div class="rose-leaf"></div>
      <div class="rose-bud" id="roseBud" onclick="bloomRose()">🌹</div>
      <div class="ground"></div>
    </div>
    <div class="scene-line" id="sceneLine1"><?= $raw($c['scene']['line1']) ?></div>
    <div class="scene-line" id="sceneLine2"><?= $raw($c['scene']['line2']) ?></div>
    <div style="color:rgba(255,205,215,.5); font-size:0.78rem; margin-top:12px;" id="sceneHint">Tap the rose 🌹</div>
    <div id="sceneNext" style="display:none;"><button class="btn" onclick="next()"><?= $e($c['scene']['button']) ?></button></div>
  </div>
</div>
<?php endif; ?>

<!-- 7 · FINAL PROPOSAL -->
<?php if (!empty($c['final']['enabled'])): ?>
<div class="page" id="page-final">
  <div class="glass" style="background:rgba(255,255,255,.05);">
    <div style="font-size:3rem; margin-bottom:8px;">💍</div>
    <div class="final-q"><?= $raw($c['final']['question']) ?></div>
    <div class="final-btns" id="finalBtns">
      <button class="yes-btn" onclick="sayYes()"><?= $e($c['final']['yes']) ?></button>
      <button class="no-btn" id="noBtn" <?= !empty($c['final']['funny_no']) ? 'onmouseover="dodgeNo()" onclick="dodgeNo()"' : 'onclick="sayYes()"' ?>><?= $e($c['final']['no']) ?></button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- 8 · CELEBRATION & REPLY -->
<?php if (!empty($c['celebration']['enabled'])): ?>
<div class="page" id="page-celebration">
  <div class="glass">
    <div style="font-size:3rem;">🎉❤️✨</div>
    <div class="script" style="margin-top:6px;">Yes! 💖</div>
    <div class="serif" style="font-size:1.2rem; color:#ffd9e0; margin-top:10px; line-height:1.7;"><?= $raw($c['celebration']['message']) ?></div>

    <?php if (!empty($c['reply']['enabled'])): ?>
    <div class="reply-box" id="replyBox">
      <div style="font-family:'Satisfy',cursive; font-size:1.3rem; color:#ffb3c1; text-align:center;"><?= $e($c['reply']['heading']) ?></div>
      <div style="color:rgba(255,205,215,.6); font-size:0.8rem; text-align:center; margin:4px 0 12px;"><?= $e($c['reply']['subtitle']) ?></div>
      <div class="reply-tabs">
        <button type="button" class="reply-tab active" data-t="text" onclick="pcReplyTab('text')">✍️ Text</button>
        <button type="button" class="reply-tab" data-t="voice" onclick="pcReplyTab('voice')">🎙️ Voice</button>
        <button type="button" class="reply-tab" data-t="image" onclick="pcReplyTab('image')">🖼️ Photo</button>
        <button type="button" class="reply-tab" data-t="video" onclick="pcReplyTab('video')">🎥 Video</button>
      </div>
      <input class="reply-field" id="replyName" placeholder="Your name (optional / anonymous)">
      <div id="replyText" class="mt-2" style="margin-top:8px;"><textarea class="reply-field" id="replyMsg" rows="3" placeholder="<?= $e($c['reply']['placeholder']) ?>"></textarea></div>
      <div id="replyFile" style="display:none; margin-top:8px;"><input class="reply-field" type="file" id="replyFileInput"></div>
      <button class="reply-send" id="replyBtn" onclick="pcSendReply()"><?= $e($c['reply']['button']) ?></button>
      <div id="replyStatus" style="text-align:center; font-size:0.8rem; margin-top:8px; min-height:16px; color:#ffb3c1;"></div>
    </div>
    <?php endif; ?>

    <button class="btn-ghost" style="margin-top:18px;" onclick="goTo(pcPages[0])">Replay from start 🔄</button>
  </div>
</div>
<?php endif; ?>

<!-- ══ OPTIONAL PREMIUM PAGES (shown/ordered via $slides) ══ -->
<?php if (in_array('page-photowall', $slides)): ?>
<div class="page" id="page-photowall"><div class="glass">
  <div class="label"><?= $e($c['photo_wall']['label']) ?></div><div class="script"><?= $e($c['photo_wall']['script']) ?></div>
  <div class="wall" id="wallGrid"><?php foreach ($wall_imgs as $im): ?><img src="<?= $e($im) ?>" loading="lazy" alt=""><?php endforeach; ?></div>
  <button class="btn" onclick="next()">Continue 💕</button><br><button class="btn-ghost" onclick="prev()">← Back</button>
</div></div>
<?php endif; ?>

<?php if (in_array('page-heart', $slides)): ?>
<div class="page" id="page-heart"><div class="glass" style="background:transparent;box-shadow:none;border:none;backdrop-filter:none;">
  <div class="label"><?= $e($c['heart']['label']) ?></div><div class="script"><?= $e($c['heart']['script']) ?></div>
  <div class="heart-wrap" id="heartWrap" onclick="burstHeart()">
    <div class="heart-glow"></div>
    <div class="heart-mosaic"><div class="hm-grid"><?php $hn = max(1, count($heart_imgs)); for ($i = 0; $i < 16; $i++) { echo '<img src="' . $e($heart_imgs[$i % $hn]) . '" loading="lazy" alt="">'; } ?></div></div>
  </div>
  <div style="color:rgba(255,205,215,.55);font-size:.8rem;margin-top:10px;"><?= $e($c['heart']['subtitle']) ?></div>
  <button class="btn" onclick="next()">Continue 💕</button><br><button class="btn-ghost" onclick="prev()">← Back</button>
</div></div>
<?php endif; ?>

<?php if (in_array('page-scratch', $slides)): ?>
<div class="page" id="page-scratch"><div class="glass">
  <div class="label"><?= $e($c['scratch']['label']) ?></div><div class="script"><?= $e($c['scratch']['script']) ?></div>
  <div class="scratch-wrap"><div class="scratch-msg"><?= $raw($c['scratch']['message']) ?></div><canvas id="scratchCanvas" data-cover="<?= $e($c['scratch']['cover']) ?>"></canvas></div>
  <div style="color:rgba(255,205,215,.5);font-size:.78rem;margin-top:8px;">Scratch with your finger / mouse 🪙</div>
  <button class="btn" onclick="next()">Continue 💕</button><br><button class="btn-ghost" onclick="prev()">← Back</button>
</div></div>
<?php endif; ?>

<?php if (in_array('page-secretpw', $slides)): ?>
<div class="page" id="page-secretpw"><div class="glass">
  <div class="label"><?= $e($c['secret_pw']['label']) ?></div><div class="script"><?= $e($c['secret_pw']['script']) ?></div>
  <div style="color:rgba(255,205,215,.7);font-style:italic;margin-top:10px;"><?= $e($c['secret_pw']['hint']) ?></div>
  <input class="pw-input" id="pwInput" type="text" placeholder="Type your answer…" onkeydown="if(event.key==='Enter')checkPw()">
  <div class="pw-feedback" id="pwFeedback"></div>
  <button class="btn" onclick="checkPw()">Unlock 🔓</button>
  <div id="pwNext" style="display:none;"><button class="btn" onclick="next()">Continue 💕</button></div>
  <br><button class="btn-ghost" onclick="prev()">← Back</button>
</div></div>
<?php endif; ?>

<?php if (in_array('page-puzzle', $slides)): ?>
<div class="page" id="page-puzzle"><div class="glass">
  <div class="label"><?= $e($c['puzzle']['label']) ?></div><div class="script"><?= $e($c['puzzle']['script']) ?></div>
  <div class="puz-grid" id="puzGrid" data-img="<?= $e($puzzle_img) ?>" data-success="<?= $e($c['puzzle']['success']) ?>"></div>
  <div id="puzFeedback" style="min-height:20px;color:#ffb3c1;font-style:italic;font-size:.85rem;margin-top:6px;"></div>
  <div id="puzNext" style="display:none;"><button class="btn" onclick="next()">Continue 💕</button></div>
  <br><button class="btn-ghost" onclick="prev()">← Back</button>
</div></div>
<?php endif; ?>

<?php if (in_array('page-quiz', $slides)): ?>
<div class="page" id="page-quiz"><div class="glass">
  <div class="label"><?= $e($c['quiz']['label']) ?></div><div class="script"><?= $e($c['quiz']['script']) ?></div>
  <div id="quizBody" style="margin-top:8px;"></div>
  <div id="quizNext" style="display:none;"><button class="btn" onclick="next()">Continue 💕</button></div>
  <br><button class="btn-ghost" onclick="prev()">← Back</button>
</div>
<script type="application/json" id="quizData"><?= json_encode(array_values($c['quiz']['questions'] ?? []), JSON_UNESCAPED_UNICODE) ?></script>
</div>
<?php endif; ?>

<?php if (in_array('page-lovemeter', $slides)): ?>
<div class="page" id="page-lovemeter"><div class="glass">
  <div class="label"><?= $e($c['love_meter']['label']) ?></div><div class="script"><?= $e($c['love_meter']['script']) ?></div>
  <div class="meter" data-pct="<?= (int)($c['love_meter']['percent'] ?? 100) ?>">
    <svg width="200" height="200" viewBox="0 0 200 200"><defs><linearGradient id="lmgrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e8405a"/><stop offset="1" stop-color="#ff8fa3"/></linearGradient></defs>
      <circle class="meter-track" cx="100" cy="100" r="86"/><circle class="meter-bar" id="meterBar" cx="100" cy="100" r="86"/></svg>
    <div class="meter-num" id="meterNum">0%</div>
  </div>
  <div style="color:rgba(255,205,215,.7);font-style:italic;"><?= $e($c['love_meter']['caption']) ?></div>
  <button class="btn" onclick="next()">Continue 💕</button><br><button class="btn-ghost" onclick="prev()">← Back</button>
</div></div>
<?php endif; ?>

<?php if (in_array('page-destination', $slides)): ?>
<div class="page" id="page-destination"><div class="glass">
  <div class="label"><?= $e($c['destination']['label']) ?></div><div class="script"><?= $e($c['destination']['script']) ?></div>
  <div class="map" id="destMap" onclick="openDest()"><div class="map-pin">📍</div></div>
  <div style="color:rgba(255,205,215,.5);font-size:.78rem;margin-top:8px;" id="destHint">Tap the map 🗺️</div>
  <div class="dest-addr" id="destAddr"><div style="font-family:'Cormorant Garamond',serif;font-size:1.3rem;color:#ffb3c1;"><?= $e($c['destination']['place']) ?></div><div style="color:rgba(255,205,215,.7);margin-top:4px;"><?= $e($c['destination']['address']) ?></div></div>
  <button class="btn" onclick="next()">Continue 💕</button><br><button class="btn-ghost" onclick="prev()">← Back</button>
</div></div>
<?php endif; ?>

<?php if (in_array('page-calendar', $slides)): ?>
<div class="page" id="page-calendar"><div class="glass">
  <div class="label"><?= $e($c['calendar']['label']) ?></div><div class="script"><?= $e($c['calendar']['script']) ?></div>
  <div id="calWrap" style="margin-top:16px;"><?php foreach ($c['calendar']['dates'] as $d): ?><div class="cal-item"><div class="cal-badge"><?= $e($d['date'] ?? '') ?></div><div class="cal-label"><?= $e($d['label'] ?? '') ?></div></div><?php endforeach; ?></div>
  <button class="btn" onclick="next()">Continue 💕</button><br><button class="btn-ghost" onclick="prev()">← Back</button>
</div></div>
<?php endif; ?>

<?php if (in_array('page-countdown', $slides)): ?>
<div class="page" id="page-countdown"><div class="glass" style="background:transparent;box-shadow:none;border:none;backdrop-filter:none;">
  <div class="label"><?= $e($c['countdown']['label']) ?></div>
  <div class="cd-num" id="cdNum"></div>
  <div class="cd-msg" id="cdMsg"><?= $raw($c['countdown']['message']) ?></div>
  <div id="cdNext" style="display:none;"><button class="btn" onclick="next()">Continue 💕</button></div>
</div></div>
<?php endif; ?>

<?php if (in_array('page-thankyou', $slides)): ?>
<div class="page" id="page-thankyou"><div class="glass">
  <div style="font-size:2.6rem;">🤍</div>
  <div class="label"><?= $e($c['thankyou']['label']) ?></div><div class="script"><?= $e($c['thankyou']['script']) ?></div>
  <div class="serif" style="font-size:1.15rem;color:#ffd9e0;margin-top:10px;line-height:1.7;"><?= $raw($c['thankyou']['message']) ?></div>
  <div style="color:rgba(255,205,215,.6);margin-top:14px;font-family:'Satisfy',cursive;font-size:1.2rem;"><?= $e($c['thankyou']['signature']) ?></div>
  <button class="btn-ghost" style="margin-top:16px;" onclick="goTo(pcPages[0])">Replay 🔄</button>
</div></div>
<?php endif; ?>

<!-- Music bar + dots -->
<div class="music-bar">
  <button class="music-btn" id="musicPlayBtn" onclick="toggleMusic()">▶</button>
  <div class="music-info"><div class="music-title" id="musicTitle"><?= $e($c['music']['title']) ?></div><div class="music-artist">🎵 Background Music</div></div>
  <div class="music-progress" onclick="seekMusic(event)"><div class="music-fill" id="musicFill"></div></div>
  <button class="music-btn" onclick="if(bgAudio){bgAudio.currentTime=0;}">⏮</button>
</div>
<?php if ($music_audio): ?><audio id="bgAudio" src="<?= $e($music_audio) ?>" loop preload="metadata"></audio><?php endif; ?>
<div class="dots" id="dots"></div>

<script>
const pcPages = <?= json_encode($slides) ?>;
const PC_PAGE_ID = <?= (int)($page['id'] ?? 0) ?>;
const PC_BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
const PC_REPLY_SUCCESS = <?= json_encode($c['reply']['success'] ?? 'Sent! ❤️', JSON_UNESCAPED_UNICODE) ?>;
let current = pcPages[0];

const bgAudio = document.getElementById('bgAudio');
const voiceAudio = document.getElementById('voiceAudio');
const letterAudio = document.getElementById('letterAudio');

function goTo(id){
  const cur = document.getElementById(current); if (cur) cur.classList.remove('active');
  const nx = document.getElementById(id); if (!nx) return;
  nx.classList.add('active'); current = id; updateDots(); window.scrollTo(0,0);
  preloadNext();
  if (id === 'page-welcome')   startWelcome();
  if (id === 'page-journey')   revealSeq('#page-journey .jrn-item', 350);
  if (id === 'page-gallery')   revealSeq('#galGrid .gal-cell', 220);
  if (id === 'page-favorites') revealSeq('#favWrap .fav-card', 420);
  if (id === 'page-moments')   revealSeq('#momWrap .mom-card', 260);
  if (id === 'page-reasons')   revealReasons();
  if (id === 'page-future')  { buildStars(); revealSeq('#futWrap .fut-item', 320); }
  if (id === 'page-photowall') revealSeq('#wallGrid img', 110);
  if (id === 'page-calendar')  revealSeq('#calWrap .cal-item', 250);
  if (id === 'page-scratch')   initScratch();
  if (id === 'page-puzzle')    initPuzzle();
  if (id === 'page-quiz')      initQuiz();
  if (id === 'page-lovemeter') animateMeter();
  if (id === 'page-countdown') runCountdown();
  if (id === 'page-scene')     startScene();
  if (id === 'page-final')     fireflies(10);
}
function next(){ const i = pcPages.indexOf(current); if (i>-1 && i<pcPages.length-1) goTo(pcPages[i+1]); }
function prev(){ const i = pcPages.indexOf(current); if (i>0) goTo(pcPages[i-1]); }

// dots
const dotsEl = document.getElementById('dots');
pcPages.forEach((p,i)=>{ const d=document.createElement('div'); d.className='dot'+(i===0?' active':''); d.id='pcdot-'+i; dotsEl.appendChild(d); });
function updateDots(){ const idx=pcPages.indexOf(current); pcPages.forEach((_,i)=>{ const d=document.getElementById('pcdot-'+i); if(d) d.classList.toggle('active', i===idx); }); }
function preloadNext(){ const i=pcPages.indexOf(current); const nx=pcPages[i+1]; if(!nx) return; const el=document.getElementById(nx); if(!el) return; el.querySelectorAll('img[data-src]').forEach(im=>{ im.src=im.dataset.src; }); }

// ── ambient FX ──
const fx = document.getElementById('fx');
(function buildFx(){
  const petals=['🌸','🌹','❤️','💕','🌺','✨'];
  for(let i=0;i<18;i++){ const p=document.createElement('div'); p.className='petal'; p.textContent=petals[Math.floor(Math.random()*petals.length)]; p.style.left=Math.random()*100+'%'; p.style.animationDuration=(8+Math.random()*10)+'s'; p.style.animationDelay=(Math.random()*10)+'s'; p.style.fontSize=(0.8+Math.random()*1)+'rem'; fx.appendChild(p); }
  const cols=['rgba(232,64,90,.5)','rgba(244,197,107,.4)','rgba(255,143,163,.4)'];
  for(let i=0;i<7;i++){ const b=document.createElement('div'); b.className='bokeh'; const s=40+Math.random()*90; b.style.width=s+'px'; b.style.height=s+'px'; b.style.left=Math.random()*100+'%'; b.style.top=Math.random()*100+'%'; b.style.background=cols[i%cols.length]; b.style.animationDelay=(Math.random()*-14)+'s'; fx.appendChild(b); }
})();

// ── welcome typewriter ──
let welcomeStarted=false;
function startWelcome(){ if(welcomeStarted) return; welcomeStarted=true; const el=document.getElementById('wlName'); if(!el) return; const name=el.dataset.name||''; el.innerHTML='<span class="cursor">&nbsp;</span>'; let i=0; (function type(){ if(i<=name.length){ el.innerHTML=name.slice(0,i)+'<span class="cursor">&nbsp;</span>'; i++; setTimeout(type, 110); } else { el.textContent=name; const sub=document.getElementById('wlSub'); if(sub) sub.classList.add('show'); } })(); }

// ── gift ──
function openGift(){ const g=document.getElementById('giftBox'); if(!g||g.classList.contains('open')) return; g.classList.add('open'); burstConfetti(30,'gold'); spawnHearts(6); const h=document.getElementById('giftHint'); if(h)h.style.display='none'; setTimeout(()=>{ const n=document.getElementById('giftNext'); if(n)n.style.display='block'; },500); }

// ── letter ──
function openEnvelope(){ const env=document.getElementById('envelope'); if(!env||env.classList.contains('open')) return; env.classList.add('open'); if(letterAudio){ letterAudio.play().catch(()=>{}); } setTimeout(()=>{ const w=document.getElementById('letterWrap'); if(w)w.style.display='block'; typeLetter(); env.style.display='none'; },650); }
function typeLetter(){ const paper=document.getElementById('letterPaper'); const data=JSON.parse(document.getElementById('letterData').textContent||'[]'); if(!paper) return; paper.innerHTML=''; let pi=0;
  function nextPara(){ if(pi>=data.length) return; const p=document.createElement('p'); p.innerHTML='<span class="type-cursor"></span>'; paper.appendChild(p); const full=data[pi]; let ci=0; (function tw(){ if(ci<=full.length){ p.innerHTML=fmt(full.slice(0,ci))+'<span class="type-cursor"></span>'; ci++; paper.scrollTop=paper.scrollHeight; setTimeout(tw, 22); } else { p.innerHTML=fmt(full); pi++; setTimeout(nextPara, 260); } })(); }
  nextPara();
}
function fmt(s){ const d=document.createElement('div'); d.textContent=s; let h=d.innerHTML; return h.replace(/\*([^*]+)\*/g,'<b>$1</b>'); }

// ── voice ──
const waveEl=document.getElementById('waveform'); const wbarN=44;
if(waveEl){ for(let i=0;i<wbarN;i++){ const b=document.createElement('div'); b.className='wbar'; b.style.height=(20+Math.random()*80)+'%'; waveEl.appendChild(b); } }
function toggleVoice(){ if(!voiceAudio) return; const btn=document.getElementById('vpBtn'); const av=document.getElementById('vpAvatar'); if(voiceAudio.paused){ voiceAudio.play().then(()=>{ btn.textContent='⏸'; av.classList.add('playing'); }).catch(()=>{}); } else { voiceAudio.pause(); btn.textContent='▶'; av.classList.remove('playing'); } }
function seekVoice(e){ if(!voiceAudio||!voiceAudio.duration) return; const r=waveEl.getBoundingClientRect(); voiceAudio.currentTime=((e.clientX-r.left)/r.width)*voiceAudio.duration; }
let vSpeeds=[1,1.25,1.5,0.75], vsi=0;
function cycleVoiceSpeed(){ vsi=(vsi+1)%vSpeeds.length; if(voiceAudio) voiceAudio.playbackRate=vSpeeds[vsi]; document.getElementById('vpSpeed').textContent=vSpeeds[vsi]+'x'; }
if(voiceAudio){ voiceAudio.addEventListener('timeupdate',()=>{ const pct=voiceAudio.duration?voiceAudio.currentTime/voiceAudio.duration:0; waveEl.querySelectorAll('.wbar').forEach((b,i)=>b.classList.toggle('on', i/wbarN<pct)); const s=Math.floor(voiceAudio.currentTime); document.getElementById('vpDur').textContent=Math.floor(s/60)+':'+String(s%60).padStart(2,'0'); }); voiceAudio.addEventListener('ended',()=>{ document.getElementById('vpBtn').textContent='▶'; document.getElementById('vpAvatar').classList.remove('playing'); waveEl.querySelectorAll('.wbar').forEach(b=>b.classList.remove('on')); }); }

// ── video ratio + mute handling ──
const pcVideo=document.getElementById('pcVideo');
if(pcVideo){ pcVideo.addEventListener('loadedmetadata',()=>{ const fr=document.getElementById('videoFrame'); if(pcVideo.videoHeight>pcVideo.videoWidth) fr.classList.add('portrait'); }); pcVideo.muted=true;
  pcVideo.addEventListener('volumechange',()=>{ if(pcVideo.muted) unduckBg(); else duckBg(); });
  pcVideo.addEventListener('play',()=>{ if(!pcVideo.muted) duckBg(); });
  pcVideo.addEventListener('pause',()=>{ if(!pcVideo.muted) unduckBg(); });
  pcVideo.addEventListener('ended', unduckBg);
}

// ── proposal scene ──
let sceneStarted=false;
function startScene(){ if(sceneStarted) return; sceneStarted=true; const st=document.getElementById('sceneStage'); if(!st) return; setTimeout(()=>st.classList.add('walk'),300); setTimeout(()=>st.classList.add('grow'),1600); setTimeout(()=>{ const l=document.getElementById('sceneLine1'); if(l)l.classList.add('show'); },2600); }
function bloomRose(){ const st=document.getElementById('sceneStage'); if(!st||st.classList.contains('bloom')) return; st.classList.add('bloom','kneel'); spawnHearts(8); if(bgAudio&&bgAudio.paused){ bgAudio.play().catch(()=>{}); } const l2=document.getElementById('sceneLine2'); if(l2)l2.classList.add('show'); const h=document.getElementById('sceneHint'); if(h)h.style.display='none'; setTimeout(()=>{ const n=document.getElementById('sceneNext'); if(n)n.style.display='block'; },700); }

// ── new story pages ──
const CB_GALLERY = <?= json_encode(array_values($gallery_imgs), JSON_UNESCAPED_SLASHES) ?>;
function revealSeq(sel, gap){ document.querySelectorAll(sel).forEach((el,i)=>{ setTimeout(()=>el.classList.add('show'), 300 + i*(gap||300)); }); }
function revealReasons(){ document.querySelectorAll('#reasonsGrid .reason').forEach((el,i)=>{ setTimeout(()=>{ el.classList.add('show'); setTimeout(()=>{ el.classList.add('flip'); spawnHearts(1); }, 450); }, 300 + i*380); }); }
function buildStars(){ const f=document.getElementById('page-future'); if(!f||f.dataset.stars) return; f.dataset.stars='1'; for(let i=0;i<40;i++){ const s=document.createElement('div'); s.className='star2'; s.style.left=Math.random()*100+'%'; s.style.top=Math.random()*70+'%'; s.style.animationDelay=(Math.random()*3)+'s'; f.appendChild(s); } }
let lbI=0;
function pcLightbox(i){ if(!CB_GALLERY.length) return; lbI=i; document.getElementById('lbImg').src=CB_GALLERY[i]; document.getElementById('pcLightboxEl').classList.add('open'); }
function pcCloseLb(){ document.getElementById('pcLightboxEl').classList.remove('open'); }
document.getElementById('pcLightboxEl').addEventListener('click', function(e){ if(e.target===this) pcCloseLb(); });
function openRing(){ const b=document.getElementById('ringBox'); if(!b||b.classList.contains('open')) return; b.classList.add('open'); const h=document.getElementById('ringHint'); if(h)h.style.display='none'; spawnHearts(6); burstConfetti(24,'gold'); }

// ── optional pages ──
const PC_PW = <?= json_encode(strtolower(trim($c['secret_pw']['password'] ?? '')), JSON_UNESCAPED_UNICODE) ?>;
const PC_PW_SUCCESS = <?= json_encode($c['secret_pw']['success'] ?? 'Unlocked!', JSON_UNESCAPED_UNICODE) ?>;
function burstHeart(){ const w=document.getElementById('heartWrap'); if(w){ w.style.transition='transform .3s'; w.style.transform='scale(1.06)'; setTimeout(()=>w.style.transform='',300); } spawnHearts(16); }
function checkPw(){ const v=(document.getElementById('pwInput').value||'').trim().toLowerCase(); const fb=document.getElementById('pwFeedback'); if(!PC_PW || v===PC_PW){ fb.style.color='#a7f3d0'; fb.textContent=PC_PW_SUCCESS; document.getElementById('pwNext').style.display='block'; spawnHearts(8); } else if(v){ fb.style.color='#ff9db0'; fb.textContent='Not quite… try again 🥺'; const i=document.getElementById('pwInput'); i.animate([{transform:'translateX(-8px)'},{transform:'translateX(8px)'},{transform:'translateX(0)'}],{duration:250}); } }
let scratchInit=false;
function initScratch(){ if(scratchInit) return; const cv=document.getElementById('scratchCanvas'); if(!cv) return; scratchInit=true; const r=cv.getBoundingClientRect(); cv.width=r.width; cv.height=r.height; const x=cv.getContext('2d'); const g=x.createLinearGradient(0,0,cv.width,cv.height); g.addColorStop(0,'#b0729a'); g.addColorStop(1,'#7a4a6a'); x.fillStyle=g; x.fillRect(0,0,cv.width,cv.height); x.fillStyle='rgba(255,255,255,.85)'; x.font='600 18px "DM Sans",sans-serif'; x.textAlign='center'; x.fillText(cv.dataset.cover||'Scratch here…', cv.width/2, cv.height/2); x.globalCompositeOperation='destination-out'; let down=false; function pt(e){ const b=cv.getBoundingClientRect(); const t=e.touches?e.touches[0]:e; return {x:t.clientX-b.left,y:t.clientY-b.top}; } function scr(e){ if(!down)return; const p=pt(e); x.beginPath(); x.arc(p.x,p.y,20,0,7); x.fill(); } cv.addEventListener('mousedown',()=>down=true); window.addEventListener('mouseup',()=>down=false); cv.addEventListener('mousemove',scr); cv.addEventListener('touchstart',e=>{down=true;scr(e);},{passive:true}); cv.addEventListener('touchmove',scr,{passive:true}); cv.addEventListener('touchend',()=>down=false); }
let puzzleInit=false;
function initPuzzle(){ if(puzzleInit) return; const g=document.getElementById('puzGrid'); if(!g||!g.dataset.img) return; puzzleInit=true; const img=g.dataset.img; const pos=['0% 0%','100% 0%','0% 100%','100% 100%']; let order=[0,1,2,3]; do{ order.sort(()=>Math.random()-0.5); }while(order.join('')==='0123'); let sel=-1; function render(){ g.innerHTML=''; order.forEach((p,idx)=>{ const t=document.createElement('div'); t.className='puz-tile'+(sel===idx?' sel':''); t.style.backgroundImage='url('+img+')'; t.style.backgroundPosition=pos[p]; t.onclick=()=>tap(idx); g.appendChild(t); }); } function tap(i){ if(sel===-1) sel=i; else if(sel===i) sel=-1; else { const tmp=order[sel]; order[sel]=order[i]; order[i]=tmp; sel=-1; if(order.join('')==='0123'){ document.getElementById('puzFeedback').textContent=g.dataset.success||'Solved!'; document.getElementById('puzNext').style.display='block'; spawnHearts(8); } } render(); } render(); }
let quizInit=false;
function initQuiz(){ if(quizInit) return; quizInit=true; const el=document.getElementById('quizData'); const data=el?JSON.parse(el.textContent||'[]'):[]; const body=document.getElementById('quizBody'); let qi=0; function show(){ if(qi>=data.length){ body.innerHTML='<div class="serif" style="font-size:1.3rem;color:#ffb3c1;">All correct! 🧠❤️</div>'; document.getElementById('quizNext').style.display='block'; spawnHearts(8); return; } const q=data[qi]; let html='<div class="quiz-q">'+(q.q||'')+'</div>'; (q.options||[]).forEach((o,i)=>{ html+='<button type="button" class="quiz-opt" data-i="'+i+'">'+o+'</button>'; }); body.innerHTML=html; body.querySelectorAll('.quiz-opt').forEach(b=>{ b.onclick=()=>{ if(+b.dataset.i===(q.correct|0)){ b.classList.add('correct'); qi++; setTimeout(show,600); } else b.classList.add('wrong'); }; }); } show(); }
function animateMeter(){ const m=document.querySelector('#page-lovemeter .meter'); if(!m||m.dataset.done) return; m.dataset.done='1'; const pct=Math.max(0,Math.min(100,parseInt(m.dataset.pct||'100'))); const bar=document.getElementById('meterBar'); const num=document.getElementById('meterNum'); const C=2*Math.PI*86; bar.style.strokeDasharray=C; bar.style.strokeDashoffset=C; setTimeout(()=>{ bar.style.strokeDashoffset=C*(1-pct/100); },100); let n=0; const iv=setInterval(()=>{ n+=Math.max(1,Math.round(pct/40)); if(n>=pct){ n=pct; clearInterval(iv); spawnHearts(6); } num.textContent=n+'%'; },40); }
let cdDone=false;
function runCountdown(){ if(cdDone) return; cdDone=true; const el=document.getElementById('cdNum'); const msg=document.getElementById('cdMsg'); let n=3; (function tick(){ if(n>0){ el.textContent=n; el.classList.remove('pop'); void el.offsetWidth; el.classList.add('pop'); n--; setTimeout(tick,1000); } else { el.textContent=''; msg.classList.add('show'); spawnHearts(10); setTimeout(()=>document.getElementById('cdNext').style.display='block',900); } })(); }
function openDest(){ const m=document.getElementById('destMap'); if(!m||m.classList.contains('open')) return; m.classList.add('open'); const h=document.getElementById('destHint'); if(h)h.style.display='none'; setTimeout(()=>document.getElementById('destAddr').classList.add('show'),500); spawnHearts(4); }

// ── final: funny-no engine ──
let noTries=0;
const pranks=['run','shake','tilt','sleep','swap','split','correct','vanish'];
function dodgeNo(){ const no=document.getElementById('noBtn'); const box=document.getElementById('finalBtns'); if(!no) return; const p=pranks[noTries % pranks.length]; noTries++;
  if(p==='run'){ const dx=(Math.random()*220-110), dy=(Math.random()*120-60); no.style.transform=`translate(${dx}px,${dy}px)`; }
  else if(p==='shake'){ no.style.transform='translateX(0)'; no.animate([{transform:'translateX(0)'},{transform:'translateX(-10px)'},{transform:'translateX(10px)'},{transform:'translateX(0)'}],{duration:300,iterations:2}); }
  else if(p==='tilt'){ no.style.transform='rotate('+(Math.random()*60-30)+'deg)'; }
  else if(p==='sleep'){ no.style.transform='scale(0.6)'; no.style.opacity='0.5'; no.textContent='😴 zzz'; }
  else if(p==='swap'){ const yes=box.querySelector('.yes-btn'); if(yes&&no.previousElementSibling===yes){ box.insertBefore(no,yes); } no.style.transform='translateY(-6px)'; }
  else if(p==='correct'){ no.textContent='Yes 😅'; no.style.transform='scale(1.05)'; }
  else if(p==='split'){ if(!document.getElementById('noClone')){ const cl=no.cloneNode(true); cl.id='noClone'; cl.setAttribute('onmouseover','dodgeNo()'); cl.setAttribute('onclick','dodgeNo()'); box.appendChild(cl); } no.style.transform='translate(-60px,0)'; document.getElementById('noClone').style.transform='translate(60px,0)'; }
  else if(p==='vanish'){ no.style.transition='opacity .4s, transform .4s'; no.style.opacity='0'; no.style.transform='scale(0)'; const cl=document.getElementById('noClone'); if(cl) cl.remove(); }
}
function sayYes(){ spawnHearts(24); butterflies(10); fireflies(8); setTimeout(()=>{ goTo('page-celebration'); },900); }

// ── celebration ──
function celebrate(){ burstConfetti(120,'party'); fireworks(); spawnHearts(16); if(bgAudio){ bgAudio.volume=Math.min(1,(bgAudio.volume||1)); } }

// ── particle helpers ──
function spawnHearts(n){ for(let i=0;i<n;i++){ setTimeout(()=>{ const h=document.createElement('div'); h.className='heart-p'; h.textContent=['❤️','💖','💗','💕','🌹'][Math.floor(Math.random()*5)]; h.style.left=(10+Math.random()*80)+'vw'; h.style.top=(30+Math.random()*40)+'vh'; document.body.appendChild(h); setTimeout(()=>h.remove(),1600); }, i*70); } }
function burstConfetti(n,mode){ const party=['#e8405a','#ff8fa3','#f4c56b','#7ed957','#5bc0ff','#c4184e']; const gold=['#f4c56b','#ffe08a','#e8405a','#fff']; const cols=mode==='gold'?gold:party; for(let i=0;i<n;i++){ const p=document.createElement('div'); p.className='confetti-p'; p.style.left=Math.random()*100+'vw'; p.style.background=cols[Math.floor(Math.random()*cols.length)]; p.style.animationDuration=(2.2+Math.random()*1.8)+'s'; p.style.animationDelay=(Math.random()*.5)+'s'; p.style.width=(7+Math.random()*7)+'px'; p.style.height=(10+Math.random()*8)+'px'; document.body.appendChild(p); setTimeout(()=>p.remove(),4500); } }
function fireflies(n){ for(let i=0;i<n;i++){ const f=document.createElement('div'); f.className='firefly'; f.style.left=Math.random()*100+'vw'; f.style.top=(20+Math.random()*60)+'vh'; f.style.animationDelay=(Math.random()*-6)+'s'; document.body.appendChild(f); setTimeout(()=>f.remove(),8000); } }
function butterflies(n){ for(let i=0;i<n;i++){ setTimeout(()=>{ const b=document.createElement('div'); b.className='heart-p'; b.textContent=['🦋','🧚','✨'][Math.floor(Math.random()*3)]; b.style.left=(10+Math.random()*80)+'vw'; b.style.top=(20+Math.random()*50)+'vh'; document.body.appendChild(b); setTimeout(()=>b.remove(),1600); }, i*90); } }
function fireworks(){ for(let k=0;k<4;k++){ setTimeout(()=>{ const cx=20+Math.random()*60, cy=20+Math.random()*40; for(let i=0;i<16;i++){ const s=document.createElement('div'); s.className='firefly'; s.style.left=cx+'vw'; s.style.top=cy+'vh'; s.style.background=['#f4c56b','#e8405a','#ff8fa3','#5bc0ff'][i%4]; document.body.appendChild(s); const ang=(i/16)*Math.PI*2; s.animate([{transform:'translate(0,0)',opacity:1},{transform:`translate(${Math.cos(ang)*120}px,${Math.sin(ang)*120}px)`,opacity:0}],{duration:900,easing:'ease-out'}); setTimeout(()=>s.remove(),950); } }, k*350); } }

// ── background music + ducking ──
let musicPlaying=false, bgDucked=false;
const musicPlayBtn=document.getElementById('musicPlayBtn'), musicFill=document.getElementById('musicFill');
function toggleMusic(){ if(!bgAudio){ document.getElementById('musicTitle').textContent='No background music'; return; } if(bgAudio.paused){ bgAudio.play().then(()=>{ musicPlayBtn.textContent='⏸'; musicPlaying=true; }).catch(()=>{}); } else { bgAudio.pause(); musicPlayBtn.textContent='▶'; musicPlaying=false; } }
function seekMusic(e){ if(!bgAudio||!bgAudio.duration) return; const r=e.currentTarget.getBoundingClientRect(); bgAudio.currentTime=((e.clientX-r.left)/r.width)*bgAudio.duration; }
function duckBg(){ if(bgAudio&&!bgAudio.paused){ bgDucked=true; bgAudio.pause(); musicPlayBtn.textContent='▶'; } }
function unduckBg(){ if(bgAudio&&bgDucked){ bgDucked=false; bgAudio.play().then(()=>{ musicPlayBtn.textContent='⏸'; }).catch(()=>{}); } }
if(bgAudio){ bgAudio.addEventListener('timeupdate',()=>{ if(bgAudio.duration) musicFill.style.width=(bgAudio.currentTime/bgAudio.duration*100)+'%'; }); }
else { document.getElementById('musicTitle').textContent='No background music'; musicPlayBtn.style.opacity='0.4'; }
[voiceAudio, letterAudio].forEach(a=>{ if(!a) return; a.addEventListener('play',duckBg); a.addEventListener('pause',unduckBg); a.addEventListener('ended',unduckBg); });

// ── reply ──
let replyType='text';
function pcReplyTab(t){ replyType=t; document.querySelectorAll('.reply-tab').forEach(el=>el.classList.toggle('active', el.dataset.t===t)); document.getElementById('replyText').style.display=(t==='text')?'block':'none'; const rf=document.getElementById('replyFile'); rf.style.display=(t==='text')?'none':'block'; const inp=document.getElementById('replyFileInput'); if(t==='voice'){inp.accept='audio/*';} else if(t==='image'){inp.accept='image/*';} else if(t==='video'){inp.accept='video/*';} }
function pcSendReply(){ const btn=document.getElementById('replyBtn'), st=document.getElementById('replyStatus'); const name=(document.getElementById('replyName').value||'').trim()||'Anonymous';
  if(!PC_PAGE_ID){ st.style.color='#ffd1da'; st.textContent='Replies work on the published page — this is just a preview 🙂'; return; }
  const fd=new FormData(); fd.append('page_id',PC_PAGE_ID); fd.append('visitor_name',name); fd.append('reply_type',replyType);
  if(replyType==='text'){ const m=(document.getElementById('replyMsg').value||'').trim(); if(!m){ st.textContent='Write a message first 🙂'; return; } fd.append('message',m); }
  else { const f=document.getElementById('replyFileInput').files[0]; if(!f){ st.textContent='Choose a file first 🙂'; return; } fd.append('reply_file',f); }
  btn.disabled=true; st.style.color='#ffb3c1'; st.textContent='Sending…';
  fetch(PC_BASE+'api.php?action=submit_reply',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d&&d.success){ document.getElementById('replyBox').innerHTML='<div style="text-align:center;padding:16px;color:#ffb3c1;font-family:Satisfy,cursive;font-size:1.3rem;">'+PC_REPLY_SUCCESS+'</div>'; spawnHearts(10); } else { btn.disabled=false; st.style.color='#ff9db0'; st.textContent=(d&&d.error)?d.error:'Could not send.'; } }).catch(()=>{ btn.disabled=false; st.style.color='#ff9db0'; st.textContent='Network error.'; }); }

// celebrate when entering celebration
const _goTo=goTo; goTo=function(id){ _goTo(id); if(id==='page-celebration') celebrate(); };

// ── autoplay music on first interaction ──
function tryAutoplay(){ if(bgAudio){ bgAudio.play().then(()=>{ musicPlayBtn.textContent='⏸'; musicPlaying=true; }).catch(()=>{}); } document.removeEventListener('click',tryAutoplay); document.removeEventListener('touchstart',tryAutoplay); }
document.addEventListener('click',tryAutoplay); document.addEventListener('touchstart',tryAutoplay);

// ── boot ──
goTo(pcPages[0]);
(function(){ const loader=document.getElementById('pcLoader'); if(!loader) return; let done=false; function hide(){ if(done)return; done=true; loader.classList.add('hide'); setTimeout(()=>loader.remove(),700); } if(document.readyState==='complete') setTimeout(hide,400); window.addEventListener('load',()=>setTimeout(hide,400)); setTimeout(hide,7000); })();
</script>
<script src="assets/js/aac-playback-fix.js"></script>
<script src="<?= $base ?>assets/js/aac-play.js"></script>
</body>
</html><?php
}

} // function_exists guard
