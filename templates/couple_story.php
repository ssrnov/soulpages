<?php
// =========================================================================
// COUPLE STORY — SoulPages flagship premium template.
// Not a slideshow: a scrolling mini romantic website. Envelope intro,
// live together-timer, timeline, polaroid wall, chats, letters, playlist,
// love map, reasons, dreams, promises, achievements, love meter, secret
// message, guest book & a starry "Our Journey Never Ends" ending.
// Config JSON in pages.slide_data. Every section toggleable.
// =========================================================================

if (!function_exists('cs_format')) {

function cs_format($s) {
    $s = htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', $s);
    return nl2br($s, false);
}

function couple_story_themes() {
    return [
        'dark_love'  => ['name' => 'Dark Love 🖤',  'bg1' => '#0b0a10', 'bg2' => '#1a1020', 'acc' => '#f43f5e', 'acc2' => '#fb7185', 'card' => 'rgba(255,255,255,0.05)', 'text' => '#f5e6ec', 'soft' => 'rgba(253,164,175,0.65)'],
        'pink_love'  => ['name' => 'Pink Love 💗',  'bg1' => '#fdf2f8', 'bg2' => '#fce7f3', 'acc' => '#db2777', 'acc2' => '#ec4899', 'card' => 'rgba(255,255,255,0.75)', 'text' => '#500724', 'soft' => 'rgba(131,24,67,0.6)'],
        'galaxy'     => ['name' => 'Galaxy 🌌',     'bg1' => '#070a1a', 'bg2' => '#141a3d', 'acc' => '#a78bfa', 'acc2' => '#67e8f9', 'card' => 'rgba(167,139,250,0.08)', 'text' => '#e8e6ff', 'soft' => 'rgba(196,181,253,0.6)'],
        'neon'       => ['name' => 'Neon ⚡',       'bg1' => '#050508', 'bg2' => '#0d0d18', 'acc' => '#22d3ee', 'acc2' => '#f472b6', 'card' => 'rgba(34,211,238,0.06)', 'text' => '#e0faff', 'soft' => 'rgba(103,232,249,0.6)'],
        'minimal'    => ['name' => 'Minimal 🤍',    'bg1' => '#faf9f7', 'bg2' => '#f1efe9', 'acc' => '#b45309', 'acc2' => '#d97706', 'card' => 'rgba(255,255,255,0.85)', 'text' => '#292524', 'soft' => 'rgba(87,83,78,0.6)'],
        'royal_gold' => ['name' => 'Royal Gold 👑', 'bg1' => '#12100a', 'bg2' => '#241c0d', 'acc' => '#eab308', 'acc2' => '#fbbf24', 'card' => 'rgba(234,179,8,0.07)', 'text' => '#fdf6e3', 'soft' => 'rgba(250,204,21,0.55)'],
    ];
}

function couple_story_defaults() {
    return [
        'theme' => 'dark_love',
        'page_title' => '[c1] ❤️ [c2] — Our Story',
        'loading_text' => 'Opening our story… ❤️',
        'music' => ['title' => 'Romantic Piano', 'audio_url' => ''],
        'couple' => [
            'c1' => 'Sny ☀️', 'c2' => 'Shravni',
            'title' => 'Our Love Story',
            'since' => '2023-08-15',
            'status' => 'Madly in love ❤️',
            'nick1' => 'Hubby', 'nick2' => 'Shona',
            'first_meet' => 'College fest — the day everything changed ✨',
            'first_chat' => 'Instagram — a simple "Hii 👋"',
            'distance' => '',
        ],
        'intro' => ['enabled' => true, 'line' => "This isn't just a story…\nIt's our journey. ❤️"],
        'about' => ['enabled' => true, 'heading' => 'About Us 💑'],
        'counter' => ['enabled' => true, 'heading' => 'Together Since'],
        'timeline' => ['enabled' => true, 'heading' => 'Our Love Timeline 📅', 'items' => [
            ['emoji' => '❤️', 'date' => '14 Feb 2023', 'title' => 'First Message', 'text' => 'One "hii" that started everything.'],
            ['emoji' => '☕', 'date' => '02 Mar 2023', 'title' => 'First Meeting', 'text' => 'Coffee, nervous smiles, and endless talking.'],
            ['emoji' => '🌹', 'date' => '15 Aug 2023', 'title' => 'It Became Official', 'text' => 'The best yes of my life.'],
            ['emoji' => '✈️', 'date' => '20 Dec 2023', 'title' => 'First Trip', 'text' => 'Mountains, chai and you.'],
        ]],
        'gallery' => ['enabled' => true, 'heading' => 'Our Memories 📸', 'images' => []], // up to 30
        'videos' => ['enabled' => true, 'heading' => 'Video Memories 🎥', 'items' => []], // {url, caption}
        'voices' => ['enabled' => true, 'heading' => 'Voice Notes 🎙️', 'items' => []],   // {url, title}
        'chats' => ['enabled' => true, 'heading' => 'Chat Memories 💬', 'items' => [
            ['side' => 'l', 'text' => 'Hii 👋', 'time' => '9:12 PM'],
            ['side' => 'r', 'text' => 'Hii! Finally you messaged 😄', 'time' => '9:14 PM'],
            ['side' => 'l', 'text' => 'I was waiting for a reason to talk to you…', 'time' => '9:15 PM'],
            ['side' => 'r', 'text' => 'You never needed one ❤️', 'time' => '9:16 PM'],
        ]],
        'letters' => ['enabled' => true, 'heading' => 'Love Letters 💌', 'items' => [
            ['title' => 'To my favourite person', 'text' => "Dear [c2],\n\nIf I could relive one day forever, it would be any day with you.\n\nYours,\n[c1] ❤️"],
        ]],
        'playlist' => ['enabled' => true, 'heading' => 'Our Playlist 🎵', 'items' => [
            ['title' => 'Perfect', 'artist' => 'Ed Sheeran', 'note' => 'Our song 💃'],
            ['title' => 'Raabta', 'artist' => 'Arijit Singh', 'note' => 'First long drive 🚗'],
        ]],
        'places' => ['enabled' => true, 'heading' => 'Our Love Map 🗺️', 'items' => [
            ['emoji' => '📍', 'name' => 'Where We Met', 'note' => 'The college fest gate'],
            ['emoji' => '☕', 'name' => 'First Date', 'note' => 'That corner café'],
            ['emoji' => '🌅', 'name' => 'Favourite Place', 'note' => 'The sunset point'],
        ]],
        'reasons' => ['enabled' => true, 'heading' => 'Reasons I Love You 💞', 'items' => [
            ['emoji' => '😊', 'title' => 'Your Smile', 'text' => 'It fixes my worst days in a second.'],
            ['emoji' => '🎧', 'title' => 'Your Voice', 'text' => 'My favourite sound in the world.'],
            ['emoji' => '💗', 'title' => 'Your Kindness', 'text' => 'The way you care for everyone around you.'],
        ]],
        'dreams' => ['enabled' => true, 'heading' => 'Dreams Together 🌙', 'items' => [
            ['emoji' => '✈️', 'text' => 'Travel the world together'],
            ['emoji' => '🏡', 'text' => 'Our own little home'],
            ['emoji' => '💍', 'text' => 'Forever, officially'],
        ]],
        'promises' => ['enabled' => true, 'heading' => 'Promise Wall 📜', 'items' => [
            'I will always choose you.',
            'I will never let you sleep angry.',
            'I will hold your hand in every storm.',
        ]],
        'achieve' => ['enabled' => true, 'heading' => 'Achievements 🏆'],
        'meter' => ['enabled' => true, 'heading' => 'Love Meter ❤️', 'label' => 'Us, measured scientifically'],
        'secret' => ['enabled' => false, 'heading' => 'Secret Message 🔐', 'hint' => 'Our special date (DDMM)', 'code' => '1402', 'text' => 'You found it! This is our little secret… I love you more than I ever say. ❤️'],
        'guestbook' => ['enabled' => true, 'heading' => 'Guest Book 📝', 'subtitle' => 'Wish this beautiful couple ❤️'],
        'final' => ['enabled' => true, 'quote' => 'Every memory with you is my favorite chapter. ❤️'],
    ];
}

function couple_story_config($page) {
    $defaults = couple_story_defaults();
    $saved = [];
    if (!empty($page['slide_data'])) {
        $d = json_decode($page['slide_data'], true);
        if (is_array($d)) $saved = $d;
    }
    $config = $defaults;
    foreach ($saved as $k => $v) {
        if (is_array($v) && isset($defaults[$k]) && is_array($defaults[$k])) $config[$k] = array_replace($defaults[$k], $v);
        else $config[$k] = $v;
    }
    return $config;
}

function render_couple_story($page) {
    global $pdo;
    $c = couple_story_config($page);
    $themes = couple_story_themes();
    $th = $themes[$c['theme']] ?? $themes['dark_love'];

    $tok = [
        '[c1]' => $c['couple']['c1'] ?? '', '[c2]' => $c['couple']['c2'] ?? '',
        '[sender]' => $page['sender_name'] ?? ($c['couple']['c1'] ?? ''), '[receiver]' => $page['receiver_name'] ?? ($c['couple']['c2'] ?? ''),
    ];
    array_walk_recursive($c, function (&$v) use ($tok) { if (is_string($v)) $v = strtr($v, $tok); });

    // gates (owner / expiry / password)
    $is_owner = false;
    if (function_exists('is_logged_in') && is_logged_in() && (($page['user_id'] ?? null) == ($_SESSION['user_id'] ?? null) || (function_exists('is_super_admin') && is_super_admin()))) $is_owner = true;
    elseif (($page['guest_session_id'] ?? null) === session_id()) $is_owner = true;
    $is_expired = false;
    if (!empty($page['is_expired']) && (int)$page['is_expired'] === 1) $is_expired = true;
    elseif (!empty($page['expiry_date']) && strtotime($page['expiry_date']) < time()) $is_expired = true;
    if ($is_expired && !$is_owner) {
        echo '<div style="font-family:sans-serif;text-align:center;padding:60px 20px;background:#0b0a10;color:#fda4af;min-height:100vh;"><div style="font-size:3rem">⏳</div><h2>Story Archived</h2><p style="color:rgba(253,164,175,0.6)">This page has reached its expiration date.</p></div>';
        return;
    }
    if (!empty($page['password']) && !$is_owner) {
        $unlocked = ($_SESSION['cs_unlocked_' . $page['id']] ?? false) === true;
        if (!$unlocked && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cs_password'])) {
            if (password_verify($_POST['cs_password'], $page['password'])) { $_SESSION['cs_unlocked_' . $page['id']] = true; $unlocked = true; }
        }
        if (!$unlocked) {
            $err = ($_SERVER['REQUEST_METHOD'] === 'POST') ? '<p style="color:#fb7185;font-size:0.85rem">Wrong password, try again.</p>' : '';
            echo '<div style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#0b0a10;color:#fda4af"><form method="post" style="text-align:center;background:rgba(255,255,255,0.05);padding:36px;border-radius:20px;border:1px solid rgba(244,63,94,0.25)"><div style="font-size:2.5rem">🔒</div><h2>This story is private</h2><input type="password" name="cs_password" placeholder="Enter password" style="margin-top:16px;padding:12px 16px;border-radius:12px;border:none;width:100%;text-align:center" required>' . $err . '<button type="submit" style="margin-top:14px;background:#f43f5e;color:#fff;border:none;border-radius:50px;padding:12px 32px;cursor:pointer;font-size:1rem">Unlock ❤️</button></form></div>';
            return;
        }
    }
    if (!$is_owner && function_exists('track_view') && !empty($page['id'])) {
        try { track_view($page['id'], $pdo); } catch (\Throwable $e) {}
    }

    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    $media = function ($u) use ($base) { if (empty($u)) return ''; if (preg_match('#^https?://#i', $u)) return $u; return $base . ltrim($u, '/'); };
    $music_audio = $media($c['music']['audio_url'] ?? '');

    $gal = [];
    foreach (($c['gallery']['images'] ?? []) as $im) {
        $u = is_array($im) ? ($im['medium'] ?? $im['path'] ?? '') : $im;
        $f = is_array($im) ? ($im['path'] ?? $u) : $im;
        if ($u) $gal[] = ['m' => $media($u), 'f' => $media($f)];
    }
    $gal = array_slice($gal, 0, 30);
    $vids = array_slice(array_values(array_filter(($c['videos']['items'] ?? []), function ($v) { return !empty($v['url']); })), 0, 3);
    $vocs = array_slice(array_values(array_filter(($c['voices']['items'] ?? []), function ($v) { return !empty($v['url']); })), 0, 4);

    $since_ts = strtotime(($c['couple']['since'] ?? '') ?: 'today');
    $days_together = max(0, (int)floor((time() - $since_ts) / 86400));

    $acc = $th['acc'];
    $e = function ($s) { return function_exists('h') ? h($s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $ml = function ($s) { return cs_format($s); };
    $share_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($c['page_title']) ?></title>
<meta property="og:title" content="<?= $e($c['page_title']) ?>">
<meta property="og:description" content="Our journey, our story ❤️">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;700&family=Satisfy&display=swap" rel="stylesheet">
<style>
  :root { --acc:<?= $e($th['acc']) ?>; --acc2:<?= $e($th['acc2']) ?>; --bg1:<?= $e($th['bg1']) ?>; --bg2:<?= $e($th['bg2']) ?>; --card:<?= $e($th['card']) ?>; --txt:<?= $e($th['text']) ?>; --soft:<?= $e($th['soft']) ?>; }
  * { margin:0; padding:0; box-sizing:border-box; }
  html { scroll-behavior:smooth; }
  body { background:linear-gradient(180deg,var(--bg1),var(--bg2) 50%,var(--bg1)); font-family:'DM Sans',sans-serif; color:var(--txt); overflow-x:hidden; }
  .serif { font-family:'Cormorant Garamond',serif; }
  section { max-width:640px; margin:0 auto; padding:64px 22px; position:relative; z-index:4; }
  .sec-head { font-family:'Cormorant Garamond',serif; font-size:clamp(1.7rem,6.5vw,2.3rem); font-weight:600; text-align:center; margin-bottom:8px; }
  .sec-sub { text-align:center; color:var(--soft); font-size:.85rem; margin-bottom:30px; }
  .card { background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 22%, transparent); border-radius:20px; padding:20px; backdrop-filter:blur(6px); }
  .rv { opacity:0; transform:translateY(26px); transition:opacity .9s ease, transform .9s ease; }
  .rv.vis { opacity:1; transform:none; }
  .btn { display:inline-block; background:linear-gradient(135deg,var(--acc),var(--acc2)); color:#fff; border:none; border-radius:50px; padding:13px 30px; font-size:.95rem; font-weight:600; cursor:pointer; box-shadow:0 10px 28px color-mix(in srgb, var(--acc) 40%, transparent); transition:transform .2s; text-decoration:none; }
  .btn:hover { transform:translateY(-2px); }
  .btn-ghost { background:transparent; border:1px solid color-mix(in srgb, var(--acc) 45%, transparent); color:var(--txt); box-shadow:none; }

  /* loading + envelope intro */
  #ldr { position:fixed; inset:0; z-index:3000; display:flex; flex-direction:column; align-items:center; justify-content:center; background:linear-gradient(180deg,var(--bg1),var(--bg2)); transition:opacity .7s; }
  #ldr.hide { opacity:0; pointer-events:none; }
  .env { position:relative; width:150px; height:104px; cursor:pointer; }
  .env .body { position:absolute; inset:0; background:linear-gradient(135deg,var(--acc),var(--acc2)); border-radius:10px; box-shadow:0 20px 50px rgba(0,0,0,.4); }
  .env .flap { position:absolute; top:0; left:0; right:0; height:0; border-left:75px solid transparent; border-right:75px solid transparent; border-top:58px solid color-mix(in srgb, var(--acc) 75%, #000); transform-origin:top; transition:transform 1s cubic-bezier(.34,1.2,.5,1); z-index:3; }
  .env .note { position:absolute; left:10px; right:10px; bottom:8px; height:78px; background:#fffdf6; border-radius:6px; transition:transform 1.1s ease .5s; z-index:2; display:flex; align-items:center; justify-content:center; font-size:1.9rem; }
  .env.open .flap { transform:rotateX(180deg); }
  .env.open .note { transform:translateY(-64px); }
  #introLine { font-family:'Cormorant Garamond',serif; font-style:italic; font-size:1.2rem; margin-top:26px; min-height:56px; text-align:center; color:var(--txt); padding:0 24px; }
  #introLine .cursor { display:inline-block; width:2px; height:1em; background:currentColor; vertical-align:-2px; animation:blink .8s steps(1) infinite; }
  @keyframes blink { 50%{ opacity:0; } }
  .tap-open { margin-top:18px; font-size:.8rem; color:var(--soft); animation:pulse 2s infinite; }
  @keyframes pulse { 0%,100%{ opacity:.4;} 50%{ opacity:1;} }

  /* cover */
  #cover { min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; }
  .cover-names { font-family:'Cormorant Garamond',serif; font-size:clamp(2.2rem,9vw,3.6rem); font-weight:600; line-height:1.2; }
  .cover-amp { color:var(--acc); display:inline-block; animation:beatBig 1.4s ease-in-out infinite; }
  @keyframes beatBig { 0%,100%{ transform:scale(1);} 14%{ transform:scale(1.25);} 28%{ transform:scale(1);} }
  .cover-title { letter-spacing:4px; text-transform:uppercase; font-size:.75rem; color:var(--soft); margin-bottom:14px; }
  #liveChip { margin-top:22px; background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 30%, transparent); border-radius:50px; padding:10px 22px; font-size:.9rem; backdrop-filter:blur(8px); }
  #liveChip b { color:var(--acc2); }
  .scroll-cue { position:absolute; bottom:26px; left:50%; transform:translateX(-50%); font-size:1.4rem; animation:cue 1.6s infinite; opacity:.6; }
  @keyframes cue { 0%,100%{ transform:translate(-50%,0);} 50%{ transform:translate(-50%,10px);} }

  /* swipe-up hint — tells the visitor this is a scrolling story */
  #swipeHint { position:fixed; left:50%; bottom:26px; transform:translateX(-50%); z-index:1200; display:flex; flex-direction:column; align-items:center; gap:8px; pointer-events:none; transition:opacity .6s ease; }
  #swipeHint.gone { opacity:0; }
  #swipeHint .ring { width:30px; height:50px; border-radius:16px; border:2px solid color-mix(in srgb, var(--acc2) 80%, #fff 20%); position:relative; box-shadow:0 0 22px color-mix(in srgb, var(--acc) 45%, transparent); background:color-mix(in srgb, var(--bg1) 55%, transparent); backdrop-filter:blur(4px); }
  #swipeHint .ring i { position:absolute; left:50%; top:9px; width:5px; height:5px; margin-left:-2.5px; border-radius:50%; background:var(--acc2); animation:swWheel 1.5s ease-in-out infinite; }
  @keyframes swWheel { 0%{ transform:translateY(0); opacity:0;} 25%{ opacity:1;} 70%{ transform:translateY(20px); opacity:0;} 100%{ opacity:0;} }
  #swipeHint .lbl { font-family:'Cormorant Garamond',serif; font-style:italic; font-size:.92rem; color:var(--txt); text-shadow:0 2px 12px rgba(0,0,0,.5); animation:swBob 1.5s ease-in-out infinite; }
  #swipeHint .chev { color:var(--acc2); font-size:1rem; line-height:.6; animation:swBob 1.5s ease-in-out infinite; }
  @keyframes swBob { 0%,100%{ transform:translateY(0); opacity:.7;} 50%{ transform:translateY(-5px); opacity:1;} }

  /* floating hearts + touch trail */
  .fheart { position:fixed; bottom:-30px; pointer-events:none; z-index:2; animation:frise linear forwards; opacity:.5; }
  @keyframes frise { to { transform:translateY(-112vh) rotate(40deg); opacity:0; } }
  .trail { position:fixed; pointer-events:none; z-index:996; font-size:.85rem; animation:trailUp 1s ease-out forwards; }
  @keyframes trailUp { 0%{ opacity:.9; transform:scale(.6);} 100%{ opacity:0; transform:translateY(-34px) scale(1.15);} }

  /* about */
  .facts { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .fact { background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 18%, transparent); border-radius:16px; padding:16px 14px; }
  .fact .k { font-size:.65rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--soft); margin-bottom:5px; }
  .fact .v { font-size:.95rem; font-weight:500; }

  /* live counter boxes */
  .cnt { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
  .cnt .u { background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 25%, transparent); border-radius:14px; padding:14px 10px; min-width:72px; text-align:center; }
  .cnt .n { font-size:1.6rem; font-weight:700; color:var(--acc2); font-variant-numeric:tabular-nums; }
  .cnt .l { font-size:.62rem; text-transform:uppercase; letter-spacing:1px; color:var(--soft); margin-top:3px; }

  /* timeline */
  .tl { position:relative; padding-left:34px; }
  .tl::before { content:''; position:absolute; left:12px; top:6px; bottom:6px; width:2px; background:linear-gradient(var(--acc),transparent); }
  .tl-item { position:relative; margin-bottom:22px; }
  .tl-item .dot { position:absolute; left:-34px; top:0; width:26px; height:26px; border-radius:50%; background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 45%, transparent); display:flex; align-items:center; justify-content:center; font-size:.85rem; }
  .tl-item .d { font-size:.68rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--acc2); }
  .tl-item .t { font-weight:700; margin:2px 0 3px; }
  .tl-item .x { font-size:.88rem; color:var(--soft); line-height:1.55; }

  /* polaroid gallery */
  .rope { height:3px; background:linear-gradient(90deg,transparent, color-mix(in srgb, var(--acc) 40%, transparent), transparent); border-radius:3px; margin-bottom:-8px; }
  .polas { display:flex; gap:14px; overflow-x:auto; padding:18px 4px 26px; scroll-snap-type:x mandatory; }
  .pola { flex:0 0 auto; width:158px; background:#fffdf6; border-radius:6px; padding:8px 8px 26px; box-shadow:0 14px 34px rgba(0,0,0,.35); transform:rotate(var(--r)); scroll-snap-align:center; cursor:pointer; transition:transform .3s; position:relative; }
  .pola:hover { transform:rotate(0) scale(1.04); }
  .pola::before { content:'📎'; position:absolute; top:-13px; left:50%; transform:translateX(-50%); font-size:1rem; }
  .pola img { width:100%; aspect-ratio:1; object-fit:cover; border-radius:3px; display:block; }
  .gal-grid { column-count:2; column-gap:12px; }
  .gal-grid .gi { break-inside:avoid; margin-bottom:12px; border-radius:14px; overflow:hidden; cursor:pointer; border:1px solid color-mix(in srgb, var(--acc) 18%, transparent); }
  .gal-grid .gi img { width:100%; display:block; transition:transform .4s; }
  .gal-grid .gi:hover img { transform:scale(1.05); }

  /* lightbox */
  #lb { position:fixed; inset:0; z-index:2500; background:rgba(0,0,0,.92); display:none; align-items:center; justify-content:center; flex-direction:column; padding:20px; }
  #lb.on { display:flex; }
  #lb img { max-width:94vw; max-height:76vh; border-radius:12px; }
  #lb .lb-bar { margin-top:16px; display:flex; gap:10px; }

  /* video */
  .vid { border-radius:18px; overflow:hidden; border:1px solid color-mix(in srgb, var(--acc) 22%, transparent); margin-bottom:16px; background:#000; }
  .vid video { width:100%; display:block; max-height:70vh; }
  .vid .cap { padding:12px 14px; font-size:.85rem; color:var(--soft); background:var(--card); }

  /* voice notes */
  .vn { display:flex; align-items:center; gap:14px; background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 22%, transparent); border-radius:18px; padding:14px 16px; margin-bottom:12px; }
  .vn .pl { width:48px; height:48px; flex-shrink:0; border-radius:50%; border:none; background:linear-gradient(135deg,var(--acc),var(--acc2)); color:#fff; font-size:1.1rem; cursor:pointer; }
  .vn .meta { flex:1; min-width:0; }
  .vn .tt { font-size:.9rem; font-weight:600; margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .wave { display:flex; align-items:flex-end; gap:2.5px; height:22px; }
  .wave i { width:3px; background:color-mix(in srgb, var(--acc2) 70%, transparent); border-radius:2px; height:30%; }
  .vn.playing .wave i { animation:wv .9s ease-in-out infinite; }
  @keyframes wv { 0%,100%{ height:25%;} 50%{ height:95%;} }
  .vn .spd { flex-shrink:0; background:transparent; border:1px solid color-mix(in srgb, var(--acc) 40%, transparent); color:var(--txt); border-radius:10px; padding:5px 9px; font-size:.7rem; cursor:pointer; }

  /* whatsapp-style chats */
  .chat-wrap { background:#0b141a; border-radius:20px; padding:18px 12px; border:1px solid rgba(255,255,255,.08); }
  .bub { max-width:78%; padding:9px 13px; border-radius:12px; margin-bottom:9px; font-size:.88rem; line-height:1.45; position:relative; cursor:pointer; color:#e9edef; }
  .bub .tm { display:block; font-size:.6rem; color:rgba(233,237,239,.55); text-align:right; margin-top:3px; }
  .bub.l { background:#202c33; border-top-left-radius:3px; margin-right:auto; }
  .bub.r { background:#005c4b; border-top-right-radius:3px; margin-left:auto; }
  .bub .react { position:absolute; bottom:-10px; left:8px; font-size:.8rem; opacity:0; transform:scale(.4); transition:all .3s cubic-bezier(.34,1.56,.64,1); }
  .bub.hearted .react { opacity:1; transform:scale(1); }

  /* letters */
  .letter { margin-bottom:16px; }
  .letter .lenv { display:flex; align-items:center; gap:12px; background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 25%, transparent); border-radius:16px; padding:16px; cursor:pointer; }
  .letter .lenv .ic { font-size:1.6rem; transition:transform .4s; }
  .letter.open .lenv .ic { transform:rotate(-12deg) scale(1.15); }
  .letter .body { max-height:0; overflow:hidden; transition:max-height .9s ease; }
  .letter.open .body { max-height:1200px; }
  .letter .paper { background:linear-gradient(180deg,#fffdf6,#fdf6ec); color:#3b2a20; border-radius:14px; padding:24px 20px; margin-top:10px; font-family:'Satisfy',cursive; font-size:1.12rem; line-height:1.95; box-shadow:0 18px 44px rgba(0,0,0,.35); }

  /* playlist */
  .song { display:flex; align-items:center; gap:13px; background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 18%, transparent); border-radius:16px; padding:12px 14px; margin-bottom:10px; }
  .song .cv { width:46px; height:46px; border-radius:10px; background:linear-gradient(135deg,var(--acc),var(--acc2)); display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
  .song .st { font-weight:600; font-size:.92rem; }
  .song .sa { font-size:.75rem; color:var(--soft); }
  .song .sn { margin-left:auto; font-size:.72rem; color:var(--acc2); text-align:right; max-width:34%; }

  /* love map */
  .map { position:relative; border-radius:20px; overflow:hidden; border:1px solid color-mix(in srgb, var(--acc) 25%, transparent); background:
    radial-gradient(circle at 20% 30%, color-mix(in srgb, var(--acc) 12%, transparent), transparent 40%),
    radial-gradient(circle at 75% 65%, color-mix(in srgb, var(--acc2) 10%, transparent), transparent 45%),
    repeating-linear-gradient(0deg, transparent 0 38px, color-mix(in srgb, var(--txt) 5%, transparent) 38px 39px),
    repeating-linear-gradient(90deg, transparent 0 38px, color-mix(in srgb, var(--txt) 5%, transparent) 38px 39px),
    var(--card);
    padding:22px; }
  .pin { display:flex; gap:12px; align-items:flex-start; background:color-mix(in srgb, var(--bg1) 72%, transparent); border:1px solid color-mix(in srgb, var(--acc) 28%, transparent); border-radius:14px; padding:12px 14px; margin-bottom:10px; backdrop-filter:blur(4px); }
  .pin .pe { font-size:1.3rem; animation:pinBob 2.4s ease-in-out infinite; }
  @keyframes pinBob { 0%,100%{ transform:translateY(0);} 50%{ transform:translateY(-5px);} }
  .pin .pn { font-weight:600; font-size:.92rem; }
  .pin .px { font-size:.78rem; color:var(--soft); }

  /* reasons */
  .rsn { background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 22%, transparent); border-radius:16px; padding:16px; margin-bottom:10px; cursor:pointer; }
  .rsn .rt { display:flex; align-items:center; gap:10px; font-weight:600; }
  .rsn .rx { max-height:0; overflow:hidden; transition:max-height .5s ease; font-size:.86rem; color:var(--soft); line-height:1.55; }
  .rsn.open .rx { max-height:200px; padding-top:8px; }

  /* dreams + promises */
  .dream { display:flex; align-items:center; gap:12px; background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 18%, transparent); border-radius:14px; padding:13px 15px; margin-bottom:9px; }
  .prom { background:var(--card); border:1px dashed color-mix(in srgb, var(--acc) 40%, transparent); border-radius:14px; padding:15px; margin-bottom:9px; font-family:'Cormorant Garamond',serif; font-style:italic; font-size:1.05rem; cursor:pointer; text-align:center; }

  /* achievements */
  .badges { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px; }
  .badge { text-align:center; background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 22%, transparent); border-radius:16px; padding:16px 8px; }
  .badge.lk { opacity:.38; filter:grayscale(1); }
  .badge .be { font-size:1.7rem; }
  .badge .bt { font-size:.72rem; font-weight:700; margin-top:6px; }
  .badge .bd { font-size:.62rem; color:var(--soft); margin-top:2px; }

  /* love meter */
  .meter { text-align:center; }
  .mbar { height:20px; border-radius:20px; background:color-mix(in srgb, var(--txt) 10%, transparent); overflow:hidden; border:1px solid color-mix(in srgb, var(--acc) 30%, transparent); }
  .mfill { height:100%; width:0%; border-radius:20px; background:linear-gradient(90deg,var(--acc),var(--acc2)); transition:width 3s cubic-bezier(.25,.8,.35,1); }
  .mpct { font-size:2rem; font-weight:800; color:var(--acc2); margin-top:12px; font-variant-numeric:tabular-nums; }

  /* secret */
  .secret input { background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 35%, transparent); border-radius:12px; padding:12px 16px; color:var(--txt); text-align:center; font-size:1rem; width:200px; outline:none; }
  #secretMsg { display:none; margin-top:16px; }

  /* guest book */
  .gb textarea, .gb input { width:100%; background:var(--card); border:1px solid color-mix(in srgb, var(--acc) 25%, transparent); border-radius:14px; padding:12px 14px; color:var(--txt); font-family:'DM Sans',sans-serif; font-size:.9rem; outline:none; }
  .gb textarea { min-height:84px; resize:vertical; }

  /* final — starry night */
  #final { min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; position:relative; overflow:hidden; background:radial-gradient(1100px 700px at 50% 110%, #1a1035, #070510 70%); border-radius:34px 34px 0 0; max-width:none; }
  .star { position:absolute; background:#fff; border-radius:50%; animation:twk ease-in-out infinite alternate; }
  @keyframes twk { from{ opacity:.15;} to{ opacity:.9;} }
  .shoot { position:absolute; width:2px; height:2px; background:#fff; border-radius:50%; box-shadow:0 0 6px 2px rgba(255,255,255,.6); animation:shoot 1.6s ease-in forwards; }
  @keyframes shoot { 0%{ transform:translate(0,0); opacity:1;} 100%{ transform:translate(-46vw,34vh); opacity:0;} }
  .silhouette { font-size:3.2rem; letter-spacing:-12px; filter:brightness(0) invert(0) drop-shadow(0 0 1px #000); opacity:.9; margin-bottom:6px; position:relative; z-index:3; }
  .inf-heart { font-size:3.6rem; position:relative; z-index:3; animation:infGlow 2.2s ease-in-out infinite; }
  @keyframes infGlow { 0%,100%{ transform:scale(1); filter:drop-shadow(0 0 10px rgba(244,63,94,.5));} 50%{ transform:scale(1.14); filter:drop-shadow(0 0 34px rgba(244,63,94,.95));} }
  .fquote { font-family:'Cormorant Garamond',serif; font-style:italic; font-size:clamp(1.2rem,5vw,1.6rem); max-width:520px; margin:18px auto; position:relative; z-index:3; color:#f5e6ec; }
  .fbtns { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-top:14px; position:relative; z-index:3; }
  .share-row { display:flex; gap:10px; justify-content:center; margin-top:26px; position:relative; z-index:3; flex-wrap:wrap; }
  .share-row a, .share-row button { width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.1rem; cursor:pointer; text-decoration:none; }

  .conf { position:fixed; top:-16px; width:9px; height:13px; z-index:998; pointer-events:none; border-radius:2px; animation:cfall linear forwards; }
  @keyframes cfall { 0%{ transform:translateY(-16px) rotate(0); opacity:1;} 100%{ transform:translateY(106vh) rotate(700deg); opacity:0;} }
  .foot { text-align:center; font-size:.7rem; color:rgba(255,255,255,.35); padding:20px 0 26px; position:relative; z-index:3; }
</style>
</head>
<body>

<?php if (!empty($page['_preview'])): ?>
<div style="position:fixed;top:0;left:0;right:0;z-index:4000;background:var(--bg1);color:var(--soft);padding:9px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:0.78rem;border-bottom:1px solid var(--acc);">
  <span>🔍 <b>Preview</b> — this is just a preview (not saved). Newly selected photos/videos/voice appear after you publish.</span>
  <button onclick="window.close()" style="flex-shrink:0;background:var(--acc);color:#fff;border:none;border-radius:20px;padding:6px 14px;cursor:pointer;font-weight:600;">✕ Close</button>
</div>
<?php endif; ?>

<!-- ENVELOPE INTRO -->
<div id="ldr">
  <?php if (!empty($c['intro']['enabled'])): ?>
  <div class="env" id="env" onclick="openStory()">
    <div class="flap"></div>
    <div class="note">💌</div>
    <div class="body"></div>
  </div>
  <div id="introLine"></div>
  <div class="tap-open">Tap the envelope to open ❤️</div>
  <?php else: ?>
  <div style="font-size:3.4rem;animation:beatBig 1.4s infinite;">❤️</div>
  <div class="serif" style="font-style:italic;color:var(--soft);margin-top:14px;"><?= $e($c['loading_text']) ?></div>
  <?php endif; ?>
</div>

<!-- COVER -->
<section id="cover">
  <div class="cover-title"><?= $e($c['couple']['title']) ?></div>
  <div class="cover-names"><?= $e($c['couple']['c1']) ?> <span class="cover-amp">❤️</span> <?= $e($c['couple']['c2']) ?></div>
  <div id="liveChip">Together for <b id="liveTop">…</b></div>
</section>

<!-- swipe-up hint (hidden once the visitor starts scrolling) -->
<div id="swipeHint">
  <div class="lbl">Swipe up to read our story</div>
  <div class="ring"><i></i></div>
  <div class="chev">︿</div>
</div>

<?php if (!empty($c['about']['enabled'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['about']['heading']) ?></div>
  <div class="sec-sub"><?= $e($c['couple']['status']) ?></div>
  <div class="facts">
    <div class="fact"><div class="k">Him / Her</div><div class="v"><?= $e($c['couple']['c1']) ?> & <?= $e($c['couple']['c2']) ?></div></div>
    <?php if (!empty($c['couple']['nick1']) || !empty($c['couple']['nick2'])): ?><div class="fact"><div class="k">Nicknames</div><div class="v"><?= $e($c['couple']['nick1']) ?> 🤝 <?= $e($c['couple']['nick2']) ?></div></div><?php endif; ?>
    <div class="fact"><div class="k">Since</div><div class="v"><?= $e(date('d M Y', $since_ts)) ?></div></div>
    <?php if (!empty($c['couple']['first_meet'])): ?><div class="fact"><div class="k">First Meet</div><div class="v"><?= $e($c['couple']['first_meet']) ?></div></div><?php endif; ?>
    <?php if (!empty($c['couple']['first_chat'])): ?><div class="fact"><div class="k">First Chat</div><div class="v"><?= $e($c['couple']['first_chat']) ?></div></div><?php endif; ?>
    <?php if (!empty($c['couple']['distance'])): ?><div class="fact"><div class="k">Distance Between Us</div><div class="v"><?= $e($c['couple']['distance']) ?></div></div><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($c['counter']['enabled'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['counter']['heading']) ?> ⏳</div>
  <div class="sec-sub">and every second still counts…</div>
  <div class="cnt" id="bigCnt">
    <div class="u"><div class="n" data-u="y">0</div><div class="l">Years</div></div>
    <div class="u"><div class="n" data-u="mo">0</div><div class="l">Months</div></div>
    <div class="u"><div class="n" data-u="d">0</div><div class="l">Days</div></div>
    <div class="u"><div class="n" data-u="h">0</div><div class="l">Hours</div></div>
    <div class="u"><div class="n" data-u="mi">0</div><div class="l">Minutes</div></div>
    <div class="u"><div class="n" data-u="s">0</div><div class="l">Seconds</div></div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($c['timeline']['enabled']) && !empty($c['timeline']['items'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['timeline']['heading']) ?></div>
  <div class="sec-sub">every milestone, remembered</div>
  <div class="tl">
    <?php foreach ($c['timeline']['items'] as $it): if (empty($it['title'])) continue; ?>
    <div class="tl-item rv">
      <div class="dot"><?= $e($it['emoji'] ?: '❤️') ?></div>
      <div class="d"><?= $e($it['date'] ?? '') ?></div>
      <div class="t"><?= $e($it['title']) ?></div>
      <div class="x"><?= $ml($it['text'] ?? '') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($c['gallery']['enabled']) && $gal): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['gallery']['heading']) ?></div>
  <div class="sec-sub"><?= count($gal) ?> memories — tap any photo to zoom</div>
  <div class="rope"></div>
  <div class="polas">
    <?php foreach (array_slice($gal, 0, 8) as $gi => $g): ?>
    <div class="pola" style="--r:<?= ($gi % 2 ? '' : '-') . (1 + ($gi % 3)) ?>deg" onclick="openLb(<?= $gi ?>)"><img src="<?= $e($g['m']) ?>" loading="lazy" alt=""></div>
    <?php endforeach; ?>
  </div>
  <?php if (count($gal) > 8): ?>
  <div class="gal-grid" style="margin-top:8px;">
    <?php foreach (array_slice($gal, 8, 30, true) as $gi => $g): ?>
    <div class="gi" onclick="openLb(<?= $gi ?>)"><img src="<?= $e($g['m']) ?>" loading="lazy" alt=""></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!empty($c['videos']['enabled']) && $vids): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['videos']['heading']) ?></div>
  <div class="sec-sub">press play, relive it</div>
  <?php foreach ($vids as $v): ?>
  <div class="vid">
    <video src="<?= $e($media($v['url'])) ?>" controls playsinline preload="metadata"></video>
    <?php if (!empty($v['caption'])): ?><div class="cap"><?= $e($v['caption']) ?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($c['voices']['enabled']) && $vocs): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['voices']['heading']) ?></div>
  <div class="sec-sub">the sound of us</div>
  <?php foreach ($vocs as $vi => $v): ?>
  <div class="vn" id="vn<?= $vi ?>">
    <button class="pl" onclick="vnToggle(<?= $vi ?>, event)">▶</button>
    <div class="meta">
      <div class="tt"><?= $e($v['title'] ?: ('Voice Note ' . ($vi + 1))) ?></div>
      <div class="wave"><?php for ($b = 0; $b < 24; $b++): ?><i style="animation-delay:<?= ($b * 0.07) ?>s;height:<?= 22 + (($b * 37) % 60) ?>%"></i><?php endfor; ?></div>
    </div>
    <button class="spd" onclick="vnSpeed(<?= $vi ?>, event)">1x</button>
    <audio src="<?= $e($media($v['url'])) ?>" preload="metadata" onended="vnEnd(<?= $vi ?>)"></audio>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($c['chats']['enabled']) && !empty($c['chats']['items'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['chats']['heading']) ?></div>
  <div class="sec-sub">tap a message to ❤️ it</div>
  <div class="chat-wrap">
    <?php foreach ($c['chats']['items'] as $m): if (empty($m['text'])) continue; ?>
    <div class="bub <?= ($m['side'] ?? 'l') === 'r' ? 'r' : 'l' ?>" onclick="this.classList.toggle('hearted')"><?= $ml($m['text']) ?><span class="tm"><?= $e($m['time'] ?? '') ?></span><span class="react">❤️</span></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($c['letters']['enabled']) && !empty($c['letters']['items'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['letters']['heading']) ?></div>
  <div class="sec-sub">tap an envelope to open it</div>
  <?php foreach ($c['letters']['items'] as $lt): if (empty($lt['text'])) continue; ?>
  <div class="letter">
    <div class="lenv" onclick="this.parentElement.classList.toggle('open')">
      <span class="ic">💌</span>
      <b><?= $e($lt['title'] ?: 'A letter for you') ?></b>
      <span style="margin-left:auto;color:var(--soft);font-size:.8rem;">open ›</span>
    </div>
    <div class="body"><div class="paper"><?= $ml($lt['text']) ?></div></div>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($c['playlist']['enabled']) && !empty($c['playlist']['items'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['playlist']['heading']) ?></div>
  <div class="sec-sub">the soundtrack of our story</div>
  <?php foreach ($c['playlist']['items'] as $s): if (empty($s['title'])) continue; ?>
  <div class="song">
    <div class="cv">🎵</div>
    <div><div class="st"><?= $e($s['title']) ?></div><div class="sa"><?= $e($s['artist'] ?? '') ?></div></div>
    <?php if (!empty($s['note'])): ?><div class="sn"><?= $e($s['note']) ?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($c['places']['enabled']) && !empty($c['places']['items'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['places']['heading']) ?></div>
  <div class="sec-sub">the places that know our story</div>
  <div class="map">
    <?php foreach ($c['places']['items'] as $p): if (empty($p['name'])) continue; ?>
    <div class="pin"><span class="pe"><?= $e($p['emoji'] ?: '📍') ?></span><div><div class="pn"><?= $e($p['name']) ?></div><div class="px"><?= $e($p['note'] ?? '') ?></div></div></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($c['reasons']['enabled']) && !empty($c['reasons']['items'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['reasons']['heading']) ?></div>
  <div class="sec-sub">tap a card to read why</div>
  <?php foreach ($c['reasons']['items'] as $r): if (empty($r['title'])) continue; ?>
  <div class="rsn" onclick="this.classList.toggle('open')">
    <div class="rt"><span><?= $e($r['emoji'] ?: '❤️') ?></span> <?= $e($r['title']) ?> <span style="margin-left:auto;color:var(--soft);">›</span></div>
    <div class="rx"><?= $ml($r['text'] ?? '') ?></div>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($c['dreams']['enabled']) && !empty($c['dreams']['items'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['dreams']['heading']) ?></div>
  <div class="sec-sub">our bucket list</div>
  <?php foreach ($c['dreams']['items'] as $d): if (empty($d['text'])) continue; ?>
  <div class="dream"><span style="font-size:1.3rem;"><?= $e($d['emoji'] ?: '🌙') ?></span> <?= $e($d['text']) ?></div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($c['promises']['enabled']) && !empty($c['promises']['items'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['promises']['heading']) ?></div>
  <div class="sec-sub">tap a promise — seal it with a heart</div>
  <?php foreach ($c['promises']['items'] as $pr): $prt = is_array($pr) ? ($pr['text'] ?? '') : $pr; if (empty($prt)) continue; ?>
  <div class="prom" onclick="promiseSeal(event)">"<?= $e($prt) ?>"</div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($c['achieve']['enabled'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['achieve']['heading']) ?></div>
  <div class="sec-sub">badges unlock automatically as your days grow</div>
  <div class="badges">
    <?php
    $badge_defs = [
        [100, '💯', '100 Days'], [365, '🎂', '1 Year'], [500, '🌟', '500 Days'],
        [730, '💎', '2 Years'], [1000, '👑', '1000 Days'], [1825, '🏰', '5 Years'],
    ];
    foreach ($badge_defs as $bd):
        $unlocked = $days_together >= $bd[0]; ?>
    <div class="badge <?= $unlocked ? '' : 'lk' ?>">
      <div class="be"><?= $unlocked ? $bd[1] : '🔒' ?></div>
      <div class="bt"><?= $e($bd[2]) ?> Together</div>
      <div class="bd"><?= $unlocked ? 'Unlocked ✅' : ('in ' . ($bd[0] - $days_together) . ' days') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($c['meter']['enabled'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['meter']['heading']) ?></div>
  <div class="sec-sub"><?= $e($c['meter']['label']) ?></div>
  <div class="meter">
    <div class="mbar"><div class="mfill" id="mfill"></div></div>
    <div class="mpct" id="mpct">0%</div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($c['secret']['enabled']) && !empty($c['secret']['text'])): ?>
<section class="rv">
  <div class="sec-head"><?= $e($c['secret']['heading']) ?></div>
  <div class="sec-sub">Hint: <?= $e($c['secret']['hint']) ?></div>
  <div class="secret" style="text-align:center;">
    <input id="secretIn" placeholder="Enter the code…" autocomplete="off">
    <button class="btn" style="margin-left:8px;padding:12px 22px;" onclick="trySecret()">Unlock 🔐</button>
    <div id="secretMsg" class="card" style="font-family:'Satisfy',cursive;font-size:1.15rem;line-height:1.9;"></div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($c['guestbook']['enabled'])): ?>
<section class="rv gb">
  <div class="sec-head"><?= $e($c['guestbook']['heading']) ?></div>
  <div class="sec-sub"><?= $e($c['guestbook']['subtitle']) ?></div>
  <div id="gbForm">
    <textarea id="gbMsg" placeholder="Beautiful couple ❤️ Write your wish…"></textarea>
    <input id="gbName" placeholder="Your name (optional)" style="margin-top:10px;">
    <div style="text-align:center;margin-top:14px;"><button class="btn" onclick="gbSend()">Sign the Guest Book 📝</button></div>
  </div>
  <div id="gbDone" style="display:none;text-align:center;" class="card">💌 Thank you! Your wish has been sent to the couple ❤️</div>
</section>
<?php endif; ?>

<?php if (!empty($c['final']['enabled'])): ?>
<section id="final">
  <div id="starfield"></div>
  <div class="silhouette">🧍🧍</div>
  <div class="inf-heart">❤️</div>
  <div class="serif" style="font-size:clamp(1.6rem,6vw,2.2rem);position:relative;z-index:3;">Our Journey Never Ends…</div>
  <div class="fquote">"<?= $ml($c['final']['quote']) ?>"</div>
  <div class="fbtns">
    <a class="btn btn-ghost" href="#gbForm" onclick="document.querySelector('.gb')?.scrollIntoView({behavior:'smooth'});return false;">💌 Leave a Message</a>
    <button class="btn btn-ghost" onclick="sendLove(event)">❤️ Send Love Reaction</button>
    <a class="btn" href="<?= $base ?>premium.php">✨ Create Your Own</a>
  </div>
  <div class="share-row">
    <a href="https://wa.me/?text=<?= urlencode('Our story ❤️ ' . $share_url) ?>" target="_blank" rel="noopener" title="WhatsApp">🟢</a>
    <a href="https://t.me/share/url?url=<?= urlencode($share_url) ?>&text=<?= urlencode('Our story ❤️') ?>" target="_blank" rel="noopener" title="Telegram">✈️</a>
    <button onclick="copyLink(event)" title="Copy link">🔗</button>
    <button onclick="toggleQr(event)" title="QR code">▦</button>
  </div>
  <div id="qrBox" style="display:none;margin-top:16px;position:relative;z-index:3;background:#fff;padding:10px;border-radius:14px;">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= urlencode($share_url) ?>" alt="QR" width="160" height="160">
  </div>
  <div class="foot">Created with ❤️ on <?= h(SITE_NAME) ?></div>
</section>
<?php endif; ?>

<!-- LIGHTBOX -->
<div id="lb" onclick="if(event.target===this)closeLb()">
  <img id="lbImg" src="" alt="">
  <div class="lb-bar">
    <button class="btn btn-ghost" onclick="lbNav(-1)">‹ Prev</button>
    <a class="btn btn-ghost" id="lbDl" href="" download>⬇ Download</a>
    <button class="btn btn-ghost" onclick="lbNav(1)">Next ›</button>
    <button class="btn" onclick="closeLb()">✕</button>
  </div>
</div>

<?php if ($music_audio): ?><audio id="bgm" src="<?= $e($music_audio) ?>" loop preload="auto"></audio><?php endif; ?>

<script>
const CS_PAGE_ID = <?= (int)($page['id'] ?? 0) ?>;
const CS_BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
const SINCE = <?= (int)$since_ts ?> * 1000;
const INTRO_LINE = <?= json_encode((string)($c['intro']['line'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
const SECRET_CODE = <?= json_encode((string)($c['secret']['code'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
const SECRET_TEXT = <?= json_encode((string)($c['secret']['text'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
const GALLERY = <?= json_encode(array_map(function ($g) { return $g['f']; }, $gal), JSON_UNESCAPED_SLASHES) ?>;
const HAS_INTRO = <?= !empty($c['intro']['enabled']) ? 'true' : 'false' ?>;

// ── envelope intro ──
let storyOpen = false;
function openStory() {
  if (storyOpen) return; storyOpen = true;
  const env = document.getElementById('env');
  if (env) env.classList.add('open');
  playMusic();
  burstHearts(18);
  // typewriter tagline, then fade the loader
  const el = document.getElementById('introLine');
  if (el && INTRO_LINE) {
    let i = 0;
    el.innerHTML = '<span id="twTxt"></span><span class="cursor"></span>';
    const tw = setInterval(() => {
      document.getElementById('twTxt').innerHTML = INTRO_LINE.slice(0, ++i).replace(/\n/g, '<br>');
      if (i >= INTRO_LINE.length) { clearInterval(tw); setTimeout(dismissLoader, 1400); }
    }, 55);
  } else { setTimeout(dismissLoader, 900); }
}
function dismissLoader() { document.getElementById('ldr').classList.add('hide'); armSwipeHint(); }
if (!HAS_INTRO) window.addEventListener('load', () => setTimeout(() => { dismissLoader(); playMusic(); }, 1800));
setTimeout(() => { if (!storyOpen && !HAS_INTRO) dismissLoader(); }, 6000);

// ── swipe-up hint: show after the cover loads, hide on first scroll ──
let swipeArmed = false;
function armSwipeHint() {
  if (swipeArmed) return; swipeArmed = true;
  const hint = document.getElementById('swipeHint');
  if (!hint) return;
  const hideHint = () => {
    hint.classList.add('gone');
    setTimeout(() => { hint.style.display = 'none'; }, 700);
    window.removeEventListener('scroll', onScrollHint);
    window.removeEventListener('touchmove', hideHint);
    window.removeEventListener('wheel', hideHint);
  };
  const onScrollHint = () => { if (window.scrollY > 40) hideHint(); };
  window.addEventListener('scroll', onScrollHint, { passive: true });
  window.addEventListener('touchmove', hideHint, { passive: true });
  window.addEventListener('wheel', hideHint, { passive: true });
  // gentle bounce after a few idle seconds so they notice the page scrolls
  setTimeout(() => {
    if (hint.classList.contains('gone') || window.scrollY > 40) return;
    window.scrollTo({ top: 70, behavior: 'smooth' });
    setTimeout(() => { if (window.scrollY <= 80) window.scrollTo({ top: 0, behavior: 'smooth' }); }, 650);
  }, 3500);
  // safety auto-hide
  setTimeout(() => { if (!hint.classList.contains('gone')) hideHint(); }, 12000);
}

// ── live together timer ──
function tick() {
  let diff = Math.max(0, Date.now() - SINCE);
  const s = Math.floor(diff / 1000);
  const days = Math.floor(s / 86400), hrs = Math.floor((s % 86400) / 3600), mins = Math.floor((s % 3600) / 60), secs = s % 60;
  const top = document.getElementById('liveTop');
  if (top) top.textContent = days.toLocaleString() + ' days, ' + hrs + ' hours';
  const y = Math.floor(days / 365), mo = Math.floor((days % 365) / 30), d = (days % 365) % 30;
  const set = (u, v) => { const el = document.querySelector('#bigCnt [data-u="' + u + '"]'); if (el) el.textContent = String(v).padStart(2, '0'); };
  set('y', y); set('mo', mo); set('d', d); set('h', hrs); set('mi', mins); set('s', secs);
}
setInterval(tick, 1000); tick();

// ── reveal on scroll ──
const io = new IntersectionObserver(es => es.forEach(x => { if (x.isIntersecting) x.target.classList.add('vis'); }), { threshold: .12 });
document.querySelectorAll('.rv').forEach(el => io.observe(el));

// ── love meter (animates when visible) ──
const mfill = document.getElementById('mfill');
if (mfill) {
  const mio = new IntersectionObserver(es => es.forEach(x => {
    if (!x.isIntersecting || mfill.dataset.done) return;
    mfill.dataset.done = '1';
    let p = 0;
    const iv = setInterval(() => {
      p += 2; if (p > 100) { clearInterval(iv); burstHearts(24); confetti(50); return; }
      mfill.style.width = p + '%';
      document.getElementById('mpct').textContent = (p === 100 ? '∞ 100%' : p + '%');
    }, 55);
  }), { threshold: .5 });
  mio.observe(mfill);
}

// ── floating hearts ambience ──
function spawnHeart() {
  const h = document.createElement('div'); h.className = 'fheart';
  h.textContent = ['❤️', '💗', '💞', '🤍'][Math.floor(Math.random() * 4)];
  h.style.left = Math.random() * 100 + 'vw';
  h.style.fontSize = (0.8 + Math.random() * 1) + 'rem';
  h.style.animationDuration = (7 + Math.random() * 8) + 's';
  document.body.appendChild(h); setTimeout(() => h.remove(), 15500);
}
setInterval(spawnHeart, 2200); for (let i = 0; i < 4; i++) spawnHeart();
function burstHearts(n) { for (let i = 0; i < n; i++) setTimeout(spawnHeart, i * 90); }

// ── finger-touch heart trail ──
let lastTrail = 0;
document.addEventListener('pointermove', e => {
  const now = Date.now(); if (now - lastTrail < 90) return; lastTrail = now;
  const t = document.createElement('div'); t.className = 'trail'; t.textContent = '💗';
  t.style.left = e.clientX + 'px'; t.style.top = e.clientY + 'px';
  document.body.appendChild(t); setTimeout(() => t.remove(), 1050);
});

// ── lightbox ──
let lbIdx = 0;
function openLb(i) { lbIdx = i; const lb = document.getElementById('lb'); document.getElementById('lbImg').src = GALLERY[i]; document.getElementById('lbDl').href = GALLERY[i]; lb.classList.add('on'); }
function closeLb() { document.getElementById('lb').classList.remove('on'); }
function lbNav(d) { lbIdx = (lbIdx + d + GALLERY.length) % GALLERY.length; document.getElementById('lbImg').src = GALLERY[lbIdx]; document.getElementById('lbDl').href = GALLERY[lbIdx]; }

// ── voice notes ──
const SPEEDS = [1, 1.5, 2];
function vnAudio(i) { return document.querySelector('#vn' + i + ' audio'); }
function vnToggle(i, e) {
  e.stopPropagation();
  document.querySelectorAll('.vn audio').forEach((a, j) => { if (j !== i && !a.paused) { a.pause(); vnEnd(j); } });
  const a = vnAudio(i), box = document.getElementById('vn' + i), b = box.querySelector('.pl');
  if (a.paused) { a.play().then(() => { box.classList.add('playing'); b.textContent = '⏸'; duckBg(); }).catch(() => {}); }
  else { a.pause(); vnEnd(i); }
}
function vnEnd(i) { const box = document.getElementById('vn' + i); box.classList.remove('playing'); box.querySelector('.pl').textContent = '▶'; unduckBg(); }
function vnSpeed(i, e) {
  e.stopPropagation();
  const a = vnAudio(i), b = e.target;
  const cur = SPEEDS.indexOf(a.playbackRate); const nxt = SPEEDS[(cur + 1) % SPEEDS.length];
  a.playbackRate = nxt; b.textContent = nxt + 'x';
}

// ── promises: tap → hearts burst from the card ──
function promiseSeal(e) {
  for (let i = 0; i < 6; i++) {
    const h = document.createElement('div'); h.className = 'trail'; h.textContent = '❤️';
    h.style.left = (e.clientX - 20 + Math.random() * 40) + 'px'; h.style.top = (e.clientY - 10 + Math.random() * 20) + 'px';
    h.style.fontSize = '1.2rem';
    document.body.appendChild(h); setTimeout(() => h.remove(), 1050);
  }
}

// ── secret message ──
function trySecret() {
  const inp = document.getElementById('secretIn'), box = document.getElementById('secretMsg');
  if ((inp.value || '').trim() === SECRET_CODE && SECRET_CODE !== '') {
    box.style.display = 'block';
    box.innerHTML = SECRET_TEXT.replace(/</g, '&lt;').replace(/\n/g, '<br>');
    burstHearts(12);
  } else {
    inp.style.borderColor = '#ef4444'; inp.value = ''; inp.placeholder = 'Wrong code… try again 🙈';
    setTimeout(() => inp.style.borderColor = '', 900);
  }
}

// ── guest book + love reaction → creator's Dashboard → Replies ──
function notifyCreator(message, name) {
  if (!CS_PAGE_ID) return Promise.resolve();
  const fd = new FormData();
  fd.append('page_id', CS_PAGE_ID); fd.append('reply_type', 'text');
  fd.append('visitor_name', name || 'Anonymous'); fd.append('message', message);
  return fetch(CS_BASE + 'api.php?action=submit_reply', { method: 'POST', body: fd }).catch(() => {});
}
function gbSend() {
  const msg = (document.getElementById('gbMsg').value || '').trim();
  if (!msg) { document.getElementById('gbMsg').placeholder = 'Please write something first ❤️'; return; }
  const name = (document.getElementById('gbName').value || '').trim();
  notifyCreator('📝 Guest book: ' + msg, name);
  document.getElementById('gbForm').style.display = 'none';
  document.getElementById('gbDone').style.display = 'block';
  confetti(40);
}
let loveSent = false;
function sendLove(e) {
  burstHearts(22); confetti(60);
  for (let i = 0; i < 10; i++) promiseSeal({ clientX: e.clientX, clientY: e.clientY });
  if (!loveSent) { loveSent = true; notifyCreator('❤️ Sent a love reaction on your story!'); }
}
function copyLink(e) {
  navigator.clipboard.writeText(location.href).then(() => { e.target.textContent = '✓'; setTimeout(() => e.target.textContent = '🔗', 1400); }).catch(() => {});
}
function toggleQr() { const q = document.getElementById('qrBox'); q.style.display = q.style.display === 'none' ? 'block' : 'none'; }

// ── starry final sky + shooting stars ──
const sf = document.getElementById('starfield');
if (sf) {
  const fin = document.getElementById('final');
  for (let i = 0; i < 70; i++) {
    const s = document.createElement('div'); s.className = 'star';
    const sz = 1 + Math.random() * 2; s.style.width = sz + 'px'; s.style.height = sz + 'px';
    s.style.left = Math.random() * 100 + '%'; s.style.top = Math.random() * 100 + '%';
    s.style.animationDuration = (1.5 + Math.random() * 2.5) + 's';
    fin.appendChild(s);
  }
  setInterval(() => {
    if (Math.random() < .5) return;
    const sh = document.createElement('div'); sh.className = 'shoot';
    sh.style.left = (40 + Math.random() * 55) + '%'; sh.style.top = (Math.random() * 30) + '%';
    fin.appendChild(sh); setTimeout(() => sh.remove(), 1700);
  }, 2600);
}

function confetti(n) { const cols = ['#f43f5e', '#fb7185', '#fda4af', '#fff', '#fbbf24', '#a78bfa']; for (let i = 0; i < n; i++) { const p = document.createElement('div'); p.className = 'conf'; p.style.left = Math.random() * 100 + 'vw'; p.style.background = cols[i % cols.length]; p.style.animationDuration = (2.2 + Math.random() * 1.6) + 's'; p.style.animationDelay = (Math.random() * .5) + 's'; document.body.appendChild(p); setTimeout(() => p.remove(), 4200); } }

// ── music ──
const bgm = document.getElementById('bgm');
let ducked = false;
function duckBg() { if (bgm && !bgm.paused) { ducked = true; bgm.pause(); } }
function unduckBg() { if (bgm && ducked) { ducked = false; bgm.play().catch(() => {}); } }
function playMusic() { if (bgm) { bgm.volume = .5; bgm.play().catch(() => {}); } }
</script>
<script src="assets/js/aac-playback-fix.js"></script>
<script src="<?= $base ?>assets/js/aac-play.js"></script>
</body>
</html><?php
}

} // guard
