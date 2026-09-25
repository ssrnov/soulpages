<?php
// =========================================================================
// SORRY CINEMATIC — Standalone Premium Template
// Emotional, elegant, mobile-first apology experience (6 slides + ending).
// Config JSON in pages.slide_data; respectful reply (no funny-no tricks).
// =========================================================================

if (!function_exists('sc_format')) {

function sc_format($s) {
    $s = htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', $s);
    return nl2br($s, false);
}

function sorry_cinematic_defaults() {
    return [
        'colors' => ['accent' => '#f43f5e', 'deep' => '#0a0d14'],
        'page_title' => 'For [receiver] — I\'m Sorry 💔',
        'loading_text' => 'Preparing something from the heart… ❤️',
        'music' => ['title' => 'Soft Piano', 'audio_url' => ''],

        'hurt' => [
            'enabled' => true,
            'lines' => "I know…\nThings haven't been the same.\nMaybe I made mistakes.\nMaybe I hurt you.\nAnd I'm truly sorry… ❤️",
        ],
        'heart' => [
            'enabled' => true,
            'lines' => "Every misunderstanding…\nEvery argument…\nEvery moment of silence…\nHas been hurting me too.\nBecause losing you\nwas never my intention.",
        ],
        'memories' => [
            'enabled' => true,
            'caption' => "Every picture reminds me…\nhow beautiful everything was with you.",
            'images'  => [], // up to 6
        ],
        'voice' => [
            'enabled'   => true,
            'subtitle'  => "Listen to what I couldn't say in words…",
            'quote'     => "Some feelings are too heavy for words.\nBut every beat of my heart still says your name.",
            'audio_url' => '',
        ],
        'apology' => [
            'enabled' => true,
            'text'    => "I'm not asking you\nto forget everything.\n\nI'm only asking\nfor one more chance.\n\nBecause you're worth fighting for.\n\nAnd I'll keep trying\nuntil I make things right.\n\n❤️ I'm Sorry.",
        ],
        'reply' => [
            'enabled'  => true,
            'question' => 'Can you forgive me?',
            'yes'      => '❤️ Yes',
            'more'     => '💔 I Need More Time',
            'yes_msg'  => "Thank you\nfor giving us another chance. ❤️\nI promise I'll never stop valuing you.",
        ],
        'final' => [
            'enabled' => true,
            'text'    => 'Some words are difficult to say. Thank you for reading till the end. ❤️',
        ],
    ];
}

function sorry_cinematic_config($page) {
    $defaults = sorry_cinematic_defaults();
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

function render_sorry_cinematic($page) {
    global $pdo;
    $c = sorry_cinematic_config($page);

    $tok = ['[sender]' => $page['sender_name'] ?? '', '[Sender]' => $page['sender_name'] ?? '', '[receiver]' => $page['receiver_name'] ?? '', '[Receiver]' => $page['receiver_name'] ?? ''];
    array_walk_recursive($c, function (&$v) use ($tok) { if (is_string($v)) $v = strtr($v, $tok); });

    // gates (owner / expiry / password) — same pattern as the other premium templates
    $is_owner = false;
    if (function_exists('is_logged_in') && is_logged_in() && (($page['user_id'] ?? null) == ($_SESSION['user_id'] ?? null) || (function_exists('is_super_admin') && is_super_admin()))) $is_owner = true;
    elseif (($page['guest_session_id'] ?? null) === session_id()) $is_owner = true;
    $is_expired = false;
    if (!empty($page['is_expired']) && (int)$page['is_expired'] === 1) $is_expired = true;
    elseif (!empty($page['expiry_date']) && strtotime($page['expiry_date']) < time()) $is_expired = true;
    if ($is_expired && !$is_owner) {
        echo '<div style="font-family:sans-serif;text-align:center;padding:60px 20px;background:#0a0d14;color:#fda4af;min-height:100vh;"><div style="font-size:3rem">⏳</div><h2>Memory Archived</h2><p style="color:rgba(253,164,175,0.6)">This page has reached its expiration date.</p></div>';
        return;
    }
    if (!empty($page['password']) && !$is_owner) {
        $unlocked = ($_SESSION['sc_unlocked_' . $page['id']] ?? false) === true;
        if (!$unlocked && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_password'])) {
            if (password_verify($_POST['sc_password'], $page['password'])) { $_SESSION['sc_unlocked_' . $page['id']] = true; $unlocked = true; }
        }
        if (!$unlocked) {
            $err = ($_SERVER['REQUEST_METHOD'] === 'POST') ? '<p style="color:#fb7185;font-size:0.85rem">Wrong password, try again.</p>' : '';
            echo '<div style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#0a0d14;color:#fda4af"><form method="post" style="text-align:center;background:rgba(255,255,255,0.05);padding:36px;border-radius:20px;border:1px solid rgba(244,63,94,0.25)"><div style="font-size:2.5rem">🔒</div><h2>This page is locked</h2><input type="password" name="sc_password" placeholder="Enter password" style="margin-top:16px;padding:12px 16px;border-radius:12px;border:none;width:100%;text-align:center" required>' . $err . '<button type="submit" style="margin-top:14px;background:#f43f5e;color:#fff;border:none;border-radius:50px;padding:12px 32px;cursor:pointer;font-size:1rem">Unlock ❤️</button></form></div>';
            return;
        }
    }
    if (!$is_owner && function_exists('track_view') && !empty($page['id'])) {
        try { track_view($page['id'], $pdo); } catch (\Throwable $e) {}
    }

    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    $media = function ($u) use ($base) { if (empty($u)) return ''; if (preg_match('#^https?://#i', $u)) return $u; return $base . ltrim($u, '/'); };
    $music_audio = $media($c['music']['audio_url'] ?? '');
    $voice_audio = $media($c['voice']['audio_url'] ?? '');
    $mem_imgs = [];
    foreach (($c['memories']['images'] ?? []) as $im) {
        $u = is_array($im) ? ($im['medium'] ?? $im['path'] ?? '') : $im;
        if ($u) $mem_imgs[] = $media($u);
    }
    $mem_imgs = array_slice($mem_imgs, 0, 6);

    // slides order (each toggleable)
    $slides = [];
    if (!empty($c['hurt']['enabled']))     $slides[] = 'sl-hurt';
    if (!empty($c['heart']['enabled']))    $slides[] = 'sl-heart';
    if (!empty($c['memories']['enabled']) && $mem_imgs) $slides[] = 'sl-mem';
    if (!empty($c['voice']['enabled']))    $slides[] = 'sl-voice';
    if (!empty($c['apology']['enabled']))  $slides[] = 'sl-apology';
    if (!empty($c['reply']['enabled']))    $slides[] = 'sl-reply';
    if (!empty($c['final']['enabled']))    $slides[] = 'sl-final';
    if (empty($slides)) $slides[] = 'sl-apology';

    $acc = $c['colors']['accent'];
    $e = function ($s) { return function_exists('h') ? h($s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $ml = function ($s) { return sc_format($s); };
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($c['page_title']) ?></title>
<meta property="og:title" content="<?= $e($c['page_title']) ?>">
<meta property="og:description" content="Something from the heart ❤️">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500&family=Satisfy&display=swap" rel="stylesheet">
<style>
  :root { --acc: <?= $e($acc) ?>; }
  * { margin:0; padding:0; box-sizing:border-box; }
  html,body { width:100%; height:100%; background:<?= $e($c['colors']['deep']) ?>; font-family:'DM Sans',sans-serif; color:#fff; overflow:hidden; }
  .scene { position:fixed; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:26px; text-align:center; opacity:0; pointer-events:none; transition:opacity .9s ease; overflow-y:auto; }
  .scene.active { opacity:1; pointer-events:all; }
  .lines { font-family:'Cormorant Garamond',serif; font-size:clamp(1.3rem,5vw,1.9rem); line-height:1.9; max-width:560px; position:relative; z-index:4; text-shadow:0 2px 20px rgba(0,0,0,.6); }
  .lines .ln { display:block; opacity:0; transform:translateY(14px); transition:opacity 1s, transform 1s; }
  .lines .ln.show { opacity:1; transform:none; }
  .tap-hint { position:fixed; bottom:32px; left:0; right:0; text-align:center; font-size:.76rem; color:rgba(255,255,255,.45); animation:pulse 2s infinite; z-index:6; }
  @keyframes pulse { 0%,100%{ opacity:.35;} 50%{ opacity:.9;} }
  .btn { display:inline-block; margin-top:26px; background:linear-gradient(135deg,var(--acc),#be123c); color:#fff; border:none; border-radius:50px; padding:14px 38px; font-size:1rem; font-weight:500; cursor:pointer; box-shadow:0 10px 30px rgba(244,63,94,.35); transition:transform .2s; position:relative; z-index:5; }
  .btn:hover { transform:translateY(-2px); }
  .btn-ghost { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.2); box-shadow:none; }

  /* rain */
  .rain { position:fixed; inset:0; pointer-events:none; z-index:2; overflow:hidden; }
  .drop { position:absolute; top:-60px; width:1.5px; height:44px; background:linear-gradient(rgba(174,194,224,0), rgba(174,194,224,.5)); animation:rainfall linear infinite; }
  @keyframes rainfall { to { transform:translateY(115vh); } }
  .petal { position:fixed; top:-30px; font-size:1rem; opacity:.4; animation:pfall linear infinite; pointer-events:none; z-index:2; }
  @keyframes pfall { to { transform:translateY(112vh) rotate(300deg); opacity:0; } }

  /* loading */
  #ldr { position:fixed; inset:0; z-index:3000; display:flex; flex-direction:column; align-items:center; justify-content:center; background:linear-gradient(180deg,#0a0d14,#141824); transition:opacity .7s; }
  #ldr.hide { opacity:0; pointer-events:none; }
  .blur-heart { font-size:4.4rem; filter:blur(2.5px); opacity:.85; animation:hb 1.6s ease-in-out infinite; }
  @keyframes hb { 0%,100%{ transform:scale(1);} 25%{ transform:scale(1.1);} 40%{ transform:scale(1);} 55%{ transform:scale(1.08);} }
  .ldr-bar { width:170px; height:4px; background:rgba(255,255,255,.12); border-radius:4px; margin-top:22px; overflow:hidden; }
  .ldr-fill { height:100%; width:40%; border-radius:4px; background:linear-gradient(90deg,var(--acc),#fb7185); animation:slide 1.2s ease-in-out infinite; }
  @keyframes slide { 0%{ margin-left:-40%;} 100%{ margin-left:100%;} }

  /* slide 1 — rainy window */
  #sl-hurt { background:linear-gradient(180deg,#0a0d18 0%,#131a2a 60%,#0a0d14 100%); }
  .window-glow { position:absolute; top:12%; right:14%; width:120px; height:160px; border-radius:10px; background:linear-gradient(rgba(253,224,71,.12), rgba(253,224,71,.04)); border:2px solid rgba(255,255,255,.08); box-shadow:0 0 60px rgba(253,224,71,.1); z-index:1; }
  .window-glow::before { content:''; position:absolute; inset:0; background:linear-gradient(90deg, transparent 47%, rgba(255,255,255,.08) 47% 53%, transparent 53%), linear-gradient(0deg, transparent 47%, rgba(255,255,255,.08) 47% 53%, transparent 53%); }

  /* slide 2 — broken heart joins */
  #sl-heart { background:radial-gradient(700px 600px at 50% 32%, #1a0d16, #0a0d14); }
  .heart-join { position:relative; width:130px; height:110px; margin:0 auto 20px; z-index:4; }
  .hhalf { position:absolute; inset:0; font-size:6rem; line-height:110px; transition:transform 2.4s cubic-bezier(.2,.8,.3,1), filter 2.4s; }
  .hhalf.l { clip-path:inset(0 50% 0 0); transform:translateX(-26px) rotate(-10deg); }
  .hhalf.r { clip-path:inset(0 0 0 50%); transform:translateX(26px) rotate(10deg); }
  .heart-join.joined .hhalf { transform:none; filter:drop-shadow(0 0 24px rgba(244,63,94,.7)); }
  .heart-join.joined { animation:hb 1.6s ease-in-out infinite 2.6s; }

  /* slide 3 — memories */
  #sl-mem { background:linear-gradient(180deg,#0d0a14 0%,#1a1224 60%,#0a0d14 100%); }
  .mem-frame { position:relative; width:min(320px,80vw); aspect-ratio:4/5; border-radius:18px; overflow:hidden; margin:0 auto 18px; box-shadow:0 24px 60px rgba(0,0,0,.55); border:1px solid rgba(255,255,255,.12); z-index:4; }
  .mem-frame img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; opacity:0; transform:scale(1); transition:opacity 1.4s; }
  .mem-frame img.on { opacity:1; animation:slowzoom 6s ease-out forwards; }
  @keyframes slowzoom { from{ transform:scale(1);} to{ transform:scale(1.12);} }
  .fl-heart { position:fixed; pointer-events:none; z-index:5; font-size:1.2rem; animation:flh 3.2s ease-out forwards; }
  @keyframes flh { 0%{ opacity:0; transform:translateY(0) scale(.7);} 15%{ opacity:.9;} 100%{ opacity:0; transform:translateY(-130px) scale(1.15);} }

  /* slide 4 — voice */
  #sl-voice { background:radial-gradient(700px 600px at 50% 30%, #12101c, #0a0d14); }
  .vplay { width:92px; height:92px; border-radius:50%; border:none; background:linear-gradient(135deg,var(--acc),#be123c); color:#fff; font-size:2rem; cursor:pointer; box-shadow:0 0 0 0 rgba(244,63,94,.5); animation:ring 2s infinite; margin-bottom:16px; position:relative; z-index:5; }
  @keyframes ring { 70%{ box-shadow:0 0 0 26px rgba(244,63,94,0);} 100%{ box-shadow:0 0 0 0 rgba(244,63,94,0);} }

  /* slide 5 — apology card + candle */
  #sl-apology { background:linear-gradient(180deg,#0d0a08 0%,#1a1208 55%,#0a0d14 100%); }
  .candle { position:absolute; bottom:10%; left:12%; z-index:2; text-align:center; }
  .candle .stick { width:26px; height:72px; background:linear-gradient(#fef3c7,#fde68a); border-radius:6px; margin:0 auto; }
  .candle .fl { width:14px; height:22px; margin:0 auto -4px; background:radial-gradient(circle at 50% 75%, #fff7c0, #fb923c 60%, #ea580c); border-radius:50% 50% 45% 45%/60% 60% 40% 40%; animation:flick .35s infinite alternate; box-shadow:0 0 26px 8px rgba(251,146,60,.55); position:relative; z-index:2; }
  @keyframes flick { from{ transform:scaleY(1) rotate(-2deg);} to{ transform:scaleY(1.15) rotate(2deg);} }
  .apology-card { background:linear-gradient(180deg,#fffdf6,#fdf6ec); color:#3b2a20; border-radius:16px; padding:34px 26px; max-width:430px; width:100%; font-family:'Satisfy',cursive; font-size:clamp(1.15rem,4.6vw,1.45rem); line-height:2; box-shadow:0 26px 70px rgba(0,0,0,.6); position:relative; z-index:4; transform:rotate(-1.2deg); }

  /* slide 6 — reply */
  #sl-reply { background:radial-gradient(700px 600px at 50% 32%, #1a0d16, #0a0d14); }
  .rq { font-family:'Cormorant Garamond',serif; font-size:clamp(1.7rem,7vw,2.4rem); position:relative; z-index:4; }
  .rbtns { display:flex; gap:14px; flex-wrap:wrap; justify-content:center; margin-top:26px; position:relative; z-index:5; }
  .conf { position:fixed; top:-16px; width:9px; height:13px; z-index:998; pointer-events:none; border-radius:2px; animation:cfall linear forwards; }
  @keyframes cfall { 0%{ transform:translateY(-16px) rotate(0);opacity:1;} 100%{ transform:translateY(106vh) rotate(700deg);opacity:0;} }

  /* final */
  #sl-final { background:linear-gradient(180deg,#0a0d14,#141824); transition:background 2s ease; }
  #sl-final.dawn { background:linear-gradient(180deg,#1b2a4a 0%,#3d4f7a 45%,#8a6f8f 100%); }
  .foot { position:fixed; bottom:12px; left:0; right:0; text-align:center; font-size:.7rem; color:rgba(255,255,255,.35); z-index:6; }

  /* rain → sunshine: global rain layer dims as story progresses */
  .rain { transition:opacity 2.5s ease; }
  body.clearing .rain { opacity:.28; }
  body.sunny .rain { opacity:0; }
  /* sun that rises near the end */
  #sun { position:fixed; top:-140px; left:50%; transform:translateX(-50%); width:150px; height:150px; border-radius:50%; background:radial-gradient(circle at 50% 50%, #fff7d6, #ffd76a 55%, rgba(255,183,77,0) 72%); filter:blur(2px); opacity:0; transition:opacity 2.5s ease, top 3.5s ease; z-index:1; pointer-events:none; }
  body.sunny #sun { opacity:.9; top:8%; }
  /* rainbow after the rain */
  #rainbow { position:fixed; left:50%; bottom:-42vh; width:150vw; height:150vw; transform:translateX(-50%); border-radius:50%; opacity:0; transition:opacity 3s ease; z-index:1; pointer-events:none;
    background:radial-gradient(circle, transparent 0 60%, rgba(239,68,68,.20) 60% 63%, rgba(249,115,22,.20) 63% 66%, rgba(250,204,21,.20) 66% 69%, rgba(34,197,94,.18) 69% 72%, rgba(59,130,246,.18) 72% 75%, rgba(139,92,246,.18) 75% 78%, transparent 78%); }
  body.sunny #rainbow { opacity:1; }

  /* touch sparkles + petal dissolve */
  .spark { position:fixed; pointer-events:none; z-index:997; font-size:.9rem; animation:sparkFade .8s ease-out forwards; }
  @keyframes sparkFade { 0%{ opacity:1; transform:scale(.4) rotate(0);} 100%{ opacity:0; transform:scale(1.3) translateY(-18px) rotate(60deg);} }
  .petal { cursor:pointer; pointer-events:auto; }
  .petal.pop { animation:petalPop .5s ease-out forwards !important; }
  @keyframes petalPop { to { opacity:0; transform:scale(1.8) rotate(90deg); } }

  /* floating apology words */
  .fword { position:fixed; pointer-events:none; z-index:3; font-family:'Cormorant Garamond',serif; font-style:italic; color:rgba(255,255,255,.5); animation:fwordRise 6.5s ease-out forwards; text-shadow:0 2px 18px rgba(0,0,0,.5); }
  @keyframes fwordRise { 0%{ opacity:0; transform:translateY(30px) scale(.9);} 12%{ opacity:.75;} 82%{ opacity:.55;} 100%{ opacity:0; transform:translateY(-90px) scale(1.05);} }

  /* butterflies of hope */
  .bfly { position:fixed; pointer-events:auto; cursor:pointer; z-index:6; font-size:1.5rem; animation:bflyPath 9s ease-in-out forwards; }
  @keyframes bflyPath { 0%{ opacity:0; transform:translate(0,20px) rotate(-8deg);} 10%{ opacity:1;} 25%{ transform:translate(24px,-40px) rotate(8deg);} 50%{ transform:translate(-20px,-110px) rotate(-8deg);} 75%{ transform:translate(28px,-170px) rotate(8deg);} 100%{ opacity:0; transform:translate(-10px,-240px) rotate(0);} }
  .bfly.flee { animation:bflyFlee 1.1s ease-in forwards !important; }
  @keyframes bflyFlee { to { opacity:0; transform:translate(60px,-160px) scale(.4) rotate(40deg); } }

  /* magical light particles */
  .mote { position:fixed; border-radius:50%; pointer-events:none; z-index:1; background:radial-gradient(circle, rgba(255,220,150,.9), rgba(255,220,150,0) 70%); animation:moteDrift linear infinite; }
  @keyframes moteDrift { 0%{ transform:translateY(0) translateX(0); opacity:0;} 15%{ opacity:.7;} 85%{ opacity:.6;} 100%{ transform:translateY(-60px) translateX(14px); opacity:0;} }

  /* typewriter cursor */
  .tw-cursor { display:inline-block; width:2px; margin-left:1px; background:currentColor; animation:twblink .8s steps(1) infinite; }
  @keyframes twblink { 50%{ opacity:0; } }

  /* heartbeat pulse on the apology / sorry lines */
  .beat { animation:beatPulse 1.15s ease-in-out infinite; }
  @keyframes beatPulse { 0%,100%{ transform:rotate(-1.2deg) scale(1);} 14%{ transform:rotate(-1.2deg) scale(1.03);} 28%{ transform:rotate(-1.2deg) scale(1);} 42%{ transform:rotate(-1.2deg) scale(1.02);} }
  .candle .fl { transition:filter .25s, transform .25s; }
  .candle.bright .fl { transform:scale(1.4); box-shadow:0 0 46px 16px rgba(251,146,60,.85); }
  .candle { cursor:pointer; }

  /* mischievous "more time" button */
  #moreBtn { transition:transform .28s cubic-bezier(.34,1.56,.64,1), opacity .3s; will-change:transform; }
  #moreBtn.gone { opacity:0; pointer-events:none; transform:scale(.2) rotate(30deg); }
  #nudge { min-height:22px; margin-top:14px; font-family:'Cormorant Garamond',serif; font-style:italic; color:rgba(255,255,255,.7); font-size:1.05rem; opacity:0; transition:opacity .4s; position:relative; z-index:5; }
  #nudge.show { opacity:1; }

  /* reply box */
  .reply-wrap { margin-top:22px; width:min(420px,90vw); position:relative; z-index:5; }
  .reply-wrap textarea { width:100%; min-height:80px; border-radius:14px; border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.06); color:#fff; padding:12px 14px; font-family:'DM Sans',sans-serif; font-size:.95rem; resize:vertical; }
  .reply-wrap textarea::placeholder { color:rgba(255,255,255,.4); }

  /* firework burst */
  .fw { position:fixed; width:6px; height:6px; border-radius:50%; z-index:998; pointer-events:none; animation:fwBurst .9s ease-out forwards; }
  @keyframes fwBurst { 0%{ opacity:1; transform:translate(0,0) scale(1);} 100%{ opacity:0; transform:translate(var(--fx),var(--fy)) scale(.3);} }

  /* heart-rebuild progress HUD (assembles piece by piece across the story) */
  #hud { position:fixed; top:16px; left:50%; transform:translateX(-50%); width:44px; height:40px; z-index:900; pointer-events:none; opacity:.9; }
  #hud .hh { position:absolute; inset:0; font-size:2rem; line-height:40px; text-align:center; transition:transform 1.6s cubic-bezier(.2,.8,.3,1), filter 1.2s; }
  #hud .hh.l { clip-path:inset(0 50% 0 0); transform:translateX(-14px) rotate(-8deg); }
  #hud .hh.r { clip-path:inset(0 0 0 50%); transform:translateX(14px) rotate(8deg); }
  #hud.glow .hh { filter:drop-shadow(0 0 14px rgba(244,63,94,.9)); animation:beatSm 1.1s ease-in-out infinite; }
  @keyframes beatSm { 0%,100%{ transform:scale(1);} 14%{ transform:scale(1.18);} 28%{ transform:scale(1);} }
  #hud.slowbeat .hh { animation:beatSm 2.6s ease-in-out infinite; }

  /* gift box on the final screen */
  .gift { position:relative; width:96px; height:86px; margin:0 auto 18px; cursor:pointer; z-index:5; }
  .gift .gbox { position:absolute; bottom:0; left:8px; right:8px; height:56px; background:linear-gradient(135deg,var(--acc),#be123c); border-radius:8px; }
  .gift .glid { position:absolute; top:14px; left:0; right:0; height:22px; background:linear-gradient(135deg,#fb7185,var(--acc)); border-radius:6px; transition:transform .8s cubic-bezier(.34,1.56,.64,1), opacity .8s; }
  .gift .grib { position:absolute; bottom:0; left:50%; width:10px; height:86px; transform:translateX(-50%); background:rgba(255,255,255,.85); border-radius:3px; transition:opacity .5s; }
  .gift .gmsg { position:absolute; left:50%; bottom:24px; transform:translateX(-50%) scale(.3); font-size:2rem; opacity:0; transition:transform .9s cubic-bezier(.34,1.56,.64,1) .35s, opacity .7s .35s; }
  .gift.open .glid { transform:translateY(-56px) rotate(-14deg); opacity:0; }
  .gift.open .grib { opacity:0; }
  .gift.open .gmsg { transform:translateX(-50%) translateY(-46px) scale(1.15); opacity:1; }
  .gift-note { min-height:26px; margin-top:4px; margin-bottom:10px; font-family:'Cormorant Garamond',serif; font-style:italic; color:rgba(255,255,255,.75); opacity:.75; transition:opacity .5s; position:relative; z-index:5; animation:pulse 2s infinite; }
</style>
</head>
<body>

<?php if (!empty($page['_preview'])): ?>
<div style="position:fixed;top:0;left:0;right:0;z-index:4000;background:#0a0d14;color:#fda4af;padding:9px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:0.78rem;border-bottom:1px solid rgba(244,63,94,0.4);">
  <span>🔍 <b>Preview</b> — this is just a preview (not saved). Newly selected photos/voice appear after you publish.</span>
  <button onclick="window.close()" style="flex-shrink:0;background:#f43f5e;color:#fff;border:none;border-radius:20px;padding:6px 14px;cursor:pointer;font-weight:600;">✕ Close</button>
</div>
<?php endif; ?>

<!-- LOADING -->
<div id="ldr">
  <div class="blur-heart">💔</div>
  <div style="font-family:'Cormorant Garamond',serif; font-style:italic; color:rgba(253,164,175,.85); font-size:1.1rem; margin-top:16px;"><?= $e($c['loading_text']) ?></div>
  <div class="ldr-bar"><div class="ldr-fill"></div></div>
</div>

<div class="rain" id="rain"></div>
<div id="sun"></div>
<div id="rainbow"></div>
<!-- broken heart that rebuilds piece by piece as the story progresses -->
<div id="hud" class="slowbeat"><span class="hh l">❤️</span><span class="hh r">❤️</span></div>

<!-- 1 · I KNOW I HURT YOU -->
<?php if (in_array('sl-hurt', $slides)): ?>
<div class="scene" id="sl-hurt">
  <div class="window-glow"></div>
  <div class="lines" data-lines><?= $ml($c['hurt']['lines']) ?></div>
</div>
<?php endif; ?>

<!-- 2 · MY HEART SPEAKS -->
<?php if (in_array('sl-heart', $slides)): ?>
<div class="scene" id="sl-heart">
  <div class="heart-join" id="heartJoin"><span class="hhalf l">❤️</span><span class="hhalf r">❤️</span></div>
  <div class="lines" data-lines><?= $ml($c['heart']['lines']) ?></div>
</div>
<?php endif; ?>

<!-- 3 · OUR MEMORIES -->
<?php if (in_array('sl-mem', $slides)): ?>
<div class="scene" id="sl-mem">
  <div class="mem-frame" id="memFrame">
    <?php foreach ($mem_imgs as $mi => $im): ?><img src="<?= $e($im) ?>" loading="lazy" alt="" <?= $mi === 0 ? 'class="on"' : '' ?>><?php endforeach; ?>
  </div>
  <div class="lines" data-lines style="font-size:clamp(1.05rem,4.2vw,1.4rem);"><?= $ml($c['memories']['caption']) ?></div>
</div>
<?php endif; ?>

<!-- 4 · VOICE FROM THE HEART -->
<?php if (in_array('sl-voice', $slides)): ?>
<div class="scene" id="sl-voice">
  <?php if ($voice_audio): ?>
    <button class="vplay" id="vPlay" onclick="toggleVoice(event)">▶</button>
    <div class="lines"><span class="ln show" style="font-style:italic;">"<?= $e($c['voice']['subtitle']) ?>"</span></div>
    <div id="vTime" style="color:rgba(255,255,255,.45); font-size:.8rem; margin-top:10px; position:relative; z-index:4;">Tap to listen 🎙️</div>
    <audio id="vAudio" src="<?= $e($voice_audio) ?>" preload="metadata"></audio>
  <?php else: ?>
    <div style="font-size:3rem; margin-bottom:14px; position:relative; z-index:4;">🕊️</div>
    <div class="lines" data-lines><?= $ml($c['voice']['quote']) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- 5 · THE APOLOGY -->
<?php if (in_array('sl-apology', $slides)): ?>
<div class="scene" id="sl-apology">
  <div class="candle"><div class="fl"></div><div class="stick"></div></div>
  <div class="apology-card"><?= $ml($c['apology']['text']) ?></div>
</div>
<?php endif; ?>

<!-- 6 · YOUR REPLY -->
<?php if (in_array('sl-reply', $slides)): ?>
<div class="scene" id="sl-reply">
  <div id="replyAsk">
    <div style="font-size:3rem; margin-bottom:12px; position:relative; z-index:4;">🥺</div>
    <div class="rq"><?= $e($c['reply']['question']) ?></div>
    <div class="rbtns">
      <button class="btn" id="yesBtn" onclick="answer('yes')"><?= $e($c['reply']['yes']) ?></button>
      <button class="btn btn-ghost" id="moreBtn" onclick="moreTime(event)"><?= $e($c['reply']['more']) ?></button>
    </div>
    <div id="nudge"></div>
  </div>
  <div id="replyDone" style="display:none;">
    <div style="font-size:3rem; margin-bottom:12px;" id="rdEmoji">❤️</div>
    <div class="lines" id="rdMsg" style="font-style:italic;"></div>
    <div class="reply-wrap">
      <textarea id="rMsg" placeholder="Write something back… (optional) 💌"></textarea>
      <input id="rName" placeholder="Your name (optional)" style="width:100%; margin-top:10px; border-radius:12px; border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.06); color:#fff; padding:11px 14px; font-family:'DM Sans',sans-serif; font-size:.92rem;">
      <button class="btn" style="margin-top:14px;" onclick="sendReply()">Send Reply 💌</button>
      <button class="btn btn-ghost" style="margin-top:14px; margin-left:8px;" onclick="next()">Skip</button>
    </div>
  </div>
  <div id="replySent" style="display:none;">
    <div style="font-size:3rem; margin-bottom:12px;">💌</div>
    <div class="lines"><span class="ln show" style="font-style:italic;">Your reply has been sent ❤️</span></div>
    <button class="btn" style="margin-top:24px;" onclick="next()">Continue</button>
  </div>
</div>
<?php endif; ?>

<!-- FINAL -->
<?php if (in_array('sl-final', $slides)): ?>
<div class="scene" id="sl-final">
  <div class="gift" id="gift" onclick="openGift(event)">
    <div class="grib"></div>
    <div class="gbox"></div>
    <div class="glid"></div>
    <div class="gmsg">❤️</div>
  </div>
  <div class="gift-note">Tap the gift 🎁</div>
  <div class="lines"><span class="ln show" style="font-style:italic;"><?= $ml($c['final']['text']) ?></span></div>
  <a class="btn" href="<?= $base ?>premium.php" style="text-decoration:none;">✨ Create Your Own Sorry Page</a>
  <div style="margin-top:14px; color:rgba(255,255,255,.35); font-size:.75rem;">Created with ❤️ on <?= h(SITE_NAME) ?></div>
</div>
<?php endif; ?>

<div class="tap-hint" id="tapHint" style="display:none;">Tap anywhere to continue 💔</div>
<?php if ($music_audio): ?><audio id="bgm" src="<?= $e($music_audio) ?>" loop preload="auto"></audio><?php endif; ?>

<script>
const SC_PAGE_ID = <?= (int)($page['id'] ?? 0) ?>;
const SC_BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
const SLIDES = <?= json_encode($slides) ?>;
const YES_MSG = <?= json_encode($c['reply']['yes_msg'], JSON_UNESCAPED_UNICODE) ?>;
let cur = -1, started = false;

// rain + petals
const rain = document.getElementById('rain');
for (let i = 0; i < 60; i++) { const d = document.createElement('div'); d.className='drop'; d.style.left = Math.random()*100+'%'; d.style.animationDuration = (0.7+Math.random()*0.8)+'s'; d.style.animationDelay = (Math.random()*2)+'s'; rain.appendChild(d); }
for (let i = 0; i < 10; i++) { const p = document.createElement('div'); p.className='petal'; p.textContent='🥀'; p.style.left = Math.random()*100+'%'; p.style.animationDuration = (9+Math.random()*9)+'s'; p.style.animationDelay = (Math.random()*9)+'s'; document.body.appendChild(p); }

// split each [data-lines] block's <br>-separated content into revealable lines
document.querySelectorAll('[data-lines]').forEach(el => {
  const parts = el.innerHTML.split(/<br\s*\/?>/i).filter(s => s.trim() !== '');
  el.innerHTML = parts.map(s => '<span class="ln">' + s + '</span>').join('');
});
function revealLines(sceneEl) { sceneEl.querySelectorAll('.ln').forEach((ln, i) => setTimeout(() => ln.classList.add('show'), 500 + i * 900)); }

let memTimer = null, memIdx = 0, heartTimer = null;

// ── broken-heart rebuild HUD: halves drift closer with every slide ──
function hudProgress(i) {
  const hud = document.getElementById('hud');
  const total = Math.max(SLIDES.length - 1, 1);
  const t = Math.min(i / total, 1);            // 0 → 1 across the story
  const gap = 14 * (1 - t);                    // px apart
  const rot = 8 * (1 - t);
  hud.querySelector('.hh.l').style.transform = 'translateX(' + (-gap) + 'px) rotate(' + (-rot) + 'deg)';
  hud.querySelector('.hh.r').style.transform = 'translateX(' + gap + 'px) rotate(' + rot + 'deg)';
  if (SLIDES[i] === 'sl-apology' || t >= 1) hud.classList.add('glow');
}

// ── rain → sunshine as the story heals ──
function weather(i) {
  const t = i / Math.max(SLIDES.length - 1, 1);
  document.body.classList.toggle('clearing', t >= 0.45 && t < 0.8);
  document.body.classList.toggle('sunny', t >= 0.8);
  if (SLIDES[i] === 'sl-final') document.getElementById('sl-final').classList.add('dawn');
}

function show(i) {
  if (cur >= 0) { const c = document.getElementById(SLIDES[cur]); if (c) c.classList.remove('active'); }
  cur = i;
  const el = document.getElementById(SLIDES[i]); if (!el) return;
  el.classList.add('active');
  revealLines(el);
  hudProgress(i);
  weather(i);
  document.getElementById('tapHint').style.display = (SLIDES[i] !== 'sl-reply' && SLIDES[i] !== 'sl-final') ? 'block' : 'none';
  if (SLIDES[i] === 'sl-hurt') { startFloatWords(); }
  if (SLIDES[i] === 'sl-heart') { setTimeout(() => { const h = document.getElementById('heartJoin'); if (h) h.classList.add('joined'); }, 900); }
  if (SLIDES[i] === 'sl-mem') { startMemories(); }
  if (SLIDES[i] === 'sl-apology') { startApology(); }
  if (SLIDES[i] === 'sl-reply' || SLIDES[i] === 'sl-final') { butterflies(4); }
}
function next(){ if (cur < SLIDES.length - 1) show(cur + 1); }
document.addEventListener('click', function (e) {
  if (!started) return;
  if (e.target.closest('button') || e.target.closest('a') || SLIDES[cur] === 'sl-reply') return;
  next();
});

function startMemories() {
  const imgs = document.querySelectorAll('#memFrame img');
  if (!imgs.length || memTimer) return;
  memTimer = setInterval(() => {
    imgs[memIdx].classList.remove('on');
    memIdx = (memIdx + 1) % imgs.length;
    imgs[memIdx].classList.add('on');
    floatHeart();
  }, 4200);
  setInterval(floatHeart, 1600);
}
function floatHeart() { if (SLIDES[cur] !== 'sl-mem') return; const h = document.createElement('div'); h.className='fl-heart'; h.textContent=['❤️','💗','🤍'][Math.floor(Math.random()*3)]; h.style.left=(15+Math.random()*70)+'vw'; h.style.top=(55+Math.random()*30)+'vh'; document.body.appendChild(h); setTimeout(()=>h.remove(), 3300); }

// voice
function toggleVoice(e) {
  e.stopPropagation();
  const a = document.getElementById('vAudio'), b = document.getElementById('vPlay'), t = document.getElementById('vTime');
  if (!a) return;
  if (a.paused) { a.play().then(() => { b.textContent='⏸'; duckBg(); }).catch(()=>{ t.textContent='Could not play audio'; }); }
  else { a.pause(); b.textContent='▶'; unduckBg(); }
}
const vA = document.getElementById('vAudio');
if (vA) { vA.addEventListener('timeupdate', () => { const t=document.getElementById('vTime'); const s=Math.floor(vA.currentTime); t.textContent = Math.floor(s/60)+':'+String(s%60).padStart(2,'0'); }); vA.addEventListener('ended', () => { document.getElementById('vPlay').textContent='▶'; unduckBg(); }); }

// ── mischievous "I Need More Time" button — dodges, pleads, then leaves only YES ──
let moreTries = 0;
const NUDGES = ['Think again… 🥺', 'Are you sure? 💔', 'One more chance? Please… 🙏', 'My heart is still waiting… ❤️', 'Okay okay… but look at the Yes button 👀', 'Fine… only ❤️ Yes remains now.'];
function moreTime(e) {
  e.stopPropagation();
  const b = document.getElementById('moreBtn'), n = document.getElementById('nudge');
  moreTries++;
  n.textContent = NUDGES[Math.min(moreTries - 1, NUDGES.length - 1)];
  n.classList.add('show');
  if (moreTries >= 6) {
    b.classList.add('gone');
    const y = document.getElementById('yesBtn');
    y.style.animation = 'beatSm 1s ease-in-out infinite';
    return;
  }
  // run away: jump to a random offset, shrink a little each time
  const dx = (Math.random() - 0.5) * 200, dy = (Math.random() - 0.5) * 130;
  const sc = Math.max(1 - moreTries * 0.13, 0.4);
  b.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(' + sc + ')';
}

// ── YES = the only real answer; celebration + reply box ──
function answer(kind) {
  document.getElementById('replyAsk').style.display = 'none';
  const done = document.getElementById('replyDone');
  done.style.display = 'block';
  document.getElementById('rdEmoji').textContent = '❤️';
  const rd = document.getElementById('rdMsg');
  rd.innerHTML = YES_MSG.split('\n').map(s => '<span class="ln">' + s.replace(/</g,'&lt;') + '</span>').join('');
  rd.querySelectorAll('.ln').forEach((ln, i) => setTimeout(() => ln.classList.add('show'), 300 + i * 800));
  // celebration: confetti + fireworks + petals burst + glowing pulsing heart + vibration
  confetti(110); fireworks(5); butterflies(7);
  const hud = document.getElementById('hud'); hud.classList.remove('slowbeat'); hud.classList.add('glow');
  document.getElementById('rdEmoji').style.animation = 'beatSm .9s ease-in-out infinite';
  if (navigator.vibrate) { try { navigator.vibrate([80, 60, 80, 60, 160]); } catch(_){} }
  // notify the creator instantly which button was pressed (+ how many escape attempts)
  notifyCreator('❤️ Pressed "Yes" — forgave you!' + (moreTries ? ' (tried "I need more time" ' + moreTries + '×first 😄)' : ''));
}
function notifyCreator(message, name) {
  if (!SC_PAGE_ID) return;
  const fd = new FormData();
  fd.append('page_id', SC_PAGE_ID); fd.append('reply_type', 'text');
  fd.append('visitor_name', name || 'Anonymous'); fd.append('message', message);
  return fetch(SC_BASE + 'api.php?action=submit_reply', { method: 'POST', body: fd }).catch(() => {});
}
function sendReply() {
  const msg = (document.getElementById('rMsg').value || '').trim();
  const name = (document.getElementById('rName').value || '').trim();
  if (msg) notifyCreator('💌 ' + msg, name || 'Anonymous');
  document.getElementById('replyDone').style.display = 'none';
  document.getElementById('replySent').style.display = 'block';
  confetti(40);
}
function confetti(n) { const cols=['#f43f5e','#fb7185','#fda4af','#fff','#fbbf24']; for (let i=0;i<n;i++){ const p=document.createElement('div'); p.className='conf'; p.style.left=Math.random()*100+'vw'; p.style.background=cols[i%cols.length]; p.style.animationDuration=(2.2+Math.random()*1.6)+'s'; p.style.animationDelay=(Math.random()*.5)+'s'; document.body.appendChild(p); setTimeout(()=>p.remove(),4200); } }
function fireworks(bursts) {
  const cols=['#f43f5e','#fbbf24','#60a5fa','#fb7185','#a78bfa','#fff'];
  for (let b = 0; b < bursts; b++) {
    setTimeout(() => {
      const cx = 15 + Math.random()*70, cy = 12 + Math.random()*40;
      for (let i = 0; i < 22; i++) {
        const p = document.createElement('div'); p.className='fw';
        p.style.left = cx+'vw'; p.style.top = cy+'vh'; p.style.background = cols[i%cols.length];
        const ang = (i/22)*Math.PI*2, r = 50+Math.random()*70;
        p.style.setProperty('--fx', Math.cos(ang)*r+'px'); p.style.setProperty('--fy', Math.sin(ang)*r+'px');
        document.body.appendChild(p); setTimeout(()=>p.remove(), 1000);
      }
    }, b * 420);
  }
}

// ── floating apology words (slide 1) ──
let fwTimer = null;
const FLOAT_WORDS = ['Sorry…', 'Miss you…', 'Please…', 'Forgive me…', 'I was wrong…'];
function startFloatWords() {
  if (fwTimer) return;
  let wi = 0;
  fwTimer = setInterval(() => {
    if (SLIDES[cur] !== 'sl-hurt') return;
    const w = document.createElement('div'); w.className='fword'; w.textContent = FLOAT_WORDS[wi++ % FLOAT_WORDS.length];
    w.style.left = (12 + Math.random()*66) + 'vw'; w.style.top = (58 + Math.random()*28) + 'vh';
    w.style.fontSize = (1 + Math.random()*0.5) + 'rem';
    document.body.appendChild(w); setTimeout(()=>w.remove(), 6600);
  }, 2100);
}

// ── butterflies of hope (tap → they flutter away) ──
function butterflies(n) {
  for (let i = 0; i < n; i++) {
    setTimeout(() => {
      const b = document.createElement('div'); b.className='bfly'; b.textContent = Math.random() < .5 ? '🦋' : '🤍';
      b.style.left = (8 + Math.random()*80) + 'vw'; b.style.top = (55 + Math.random()*35) + 'vh';
      b.style.animationDuration = (7 + Math.random()*5) + 's';
      b.addEventListener('click', (e) => { e.stopPropagation(); b.classList.add('flee'); setTimeout(()=>b.remove(), 1200); });
      document.body.appendChild(b); setTimeout(()=>b.remove(), 12500);
    }, i * 800);
  }
}

// ── apology slide: heartbeat pulse + tappable candle ──
function startApology() {
  const card = document.querySelector('.apology-card');
  if (card) setTimeout(() => card.classList.add('beat'), 2200);
  const cd = document.querySelector('.candle');
  if (cd && !cd.dataset.wired) {
    cd.dataset.wired = '1';
    cd.addEventListener('click', (e) => { e.stopPropagation(); cd.classList.add('bright'); sparkleAt(e.clientX, e.clientY, 5); setTimeout(()=>cd.classList.remove('bright'), 900); });
  }
}

// ── magical light particles (always-on ambience) ──
for (let i = 0; i < 16; i++) {
  const m = document.createElement('div'); m.className='mote';
  const s = 3 + Math.random()*5; m.style.width = s+'px'; m.style.height = s+'px';
  m.style.left = Math.random()*100+'vw'; m.style.top = (20+Math.random()*75)+'vh';
  m.style.animationDuration = (5+Math.random()*6)+'s'; m.style.animationDelay = (Math.random()*6)+'s';
  document.body.appendChild(m);
}

// ── sparkles follow every touch; tapped petals dissolve ──
function sparkleAt(x, y, n) {
  for (let i = 0; i < (n || 3); i++) {
    const s = document.createElement('div'); s.className='spark'; s.textContent = ['✨','⭐','💫'][i%3];
    s.style.left = (x - 8 + Math.random()*24) + 'px'; s.style.top = (y - 8 + Math.random()*24) + 'px';
    document.body.appendChild(s); setTimeout(()=>s.remove(), 850);
  }
}
document.addEventListener('pointerdown', (e) => {
  sparkleAt(e.clientX, e.clientY, 3);
  const pet = e.target.closest('.petal');
  if (pet && !pet.classList.contains('pop')) { pet.classList.add('pop'); setTimeout(()=>pet.remove(), 550); }
});

// ── final screen: tap the gift ──
function openGift(e) {
  e.stopPropagation();
  const g = document.getElementById('gift');
  if (g.classList.contains('open')) return;
  g.classList.add('open');
  const note = document.querySelector('.gift-note');
  if (note) { note.textContent = 'Thank you for reading ❤️'; note.style.animation = 'none'; note.style.opacity = '1'; }
  confetti(50); sparkleAt(e.clientX, e.clientY, 6);
}

// music
const bgm = document.getElementById('bgm');
let ducked = false;
function duckBg(){ if (bgm && !bgm.paused) { ducked = true; bgm.pause(); } }
function unduckBg(){ if (bgm && ducked) { ducked = false; bgm.play().catch(()=>{}); } }
function playMusic(){ if (bgm) { bgm.volume = .5; bgm.play().catch(()=>{}); } }

// boot
window.addEventListener('load', function () {
  setTimeout(function () { document.getElementById('ldr').classList.add('hide'); started = true; show(0); playMusic(); }, 2400);
});
setTimeout(function(){ const l=document.getElementById('ldr'); if (l && !l.classList.contains('hide')) { l.classList.add('hide'); started = true; show(0); } }, 6000);
</script>
<script src="assets/js/aac-playback-fix.js"></script>
<script src="<?= $base ?>assets/js/aac-play.js"></script>
</body>
</html><?php
}

} // guard
