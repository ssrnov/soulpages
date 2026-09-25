<?php
// =========================================================================
// CINEMATIC BIRTHDAY — Standalone Premium Template ("Shravni" style)
// -------------------------------------------------------------------------
// A self-contained 10-slide cinematic birthday experience. It does NOT use
// the DB-driven slide engine used by the other categories. All content is
// stored as a JSON config in `pages.slide_data` and every element is editable
// via customize-birthday.php.
//
//   render_cinematic_birthday($page)      -> outputs the full HTML page
//   cinematic_birthday_defaults()         -> full default (Shravni) config
// =========================================================================

if (!function_exists('cinematic_birthday_defaults')) {

/**
 * Safe, forgiving text formatter for user-authored content.
 * - Escapes all HTML so a stray "<" or a deleted tag can NEVER break the page.
 * - A new line becomes a line break.
 * - Text wrapped in *stars* becomes bold. Unmatched stars just stay as-is.
 * This means normal users type plain text — no HTML knowledge required.
 */
function cb_format($s) {
    $s = htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', $s);
    return nl2br($s, false);
}

/**
 * The default content. This is what a brand-new page starts with, and the
 * fallback for any field the owner has not customized. It reproduces the
 * original "Shravni" surprise 1:1.
 */
function cinematic_birthday_defaults() {
    return [
        'colors' => [
            'rose'  => '#e8405a',
            'blush' => '#f7c5cf',
            'cream' => '#fff8f5',
            'deep'  => '#1a0810',
            'gold'  => '#c9956b',
            'soft'  => '#fde8ec',
            'text'  => '#3a1a22',
        ],
        'page_title' => '[receiver] — My Favorite Person In The World ❤️',

        'landing' => [
            'emoji'    => '🌹',
            'name'     => 'Shravni',
            'script'   => 'My Favorite Person In The World',
            'subtitle' => "A little surprise made with all my love\nfor the most special girl in my life.",
            'btn'      => 'Open Your Surprise 🎁',
            'hint'     => 'Tap to begin your journey ✨',
        ],

        'intro' => [
            'label'     => 'A message for you',
            'script'    => 'Happy Birthday Baby 🎂',
            'body'      => "\"You've been the light of my world since the day we met ✨❤️\n\nEvery moment with you feels like my favorite memory, and your birthday is another beautiful reason to remind you how special you are to me.\n\nYou're not just someone I love…\nYou're my peace, my happiness, my comfort person, and the best thing that ever happened to me 💖🌍\"",
            'signature' => '— [sender] ❤️',
        ],

        'memories' => [
            'label'  => 'From the very beginning',
            'script' => 'Our Story ✨',
            'items'  => [
                ['dot' => '✨', 'date' => '24 January 2026 — The Beginning', 'title' => 'First Message 💬', 'chat' => "Her: \"Krle save\"\nYou: \"Haa\"", 'note' => '"A simple message started my favorite story."'],
                ['dot' => '🌙', 'date' => '25 January 2026', 'title' => 'First Long Conversation 🌙', 'chat' => 'Dark • Stranger Things • GOT • Breaking Bad • Money Heist', 'note' => '"One random conversation turned into hours and I never wanted it to end."'],
                ['dot' => '📞', 'date' => '18–19 February 2026', 'title' => 'First Late Night Call 📞', 'chat' => '', 'note' => '"Hearing your voice became my favorite habit."'],
                ['dot' => '❤️', 'date' => '24 February 2026', 'title' => 'First Love You ❤️', 'chat' => "You: \"Mai bhi soo jata hu love u ❤️ bye\"\nHer: \"Love u\"", 'note' => '"I said it first and I meant every word."'],
                ['dot' => '🤝', 'date' => '6 March 2026', 'title' => 'First Hand Hold 🤝', 'chat' => '', 'note' => '"The moment I held your hand, I wished time stopped forever."'],
                ['dot' => '💍', 'date' => '6 March 2026', 'title' => 'Ring Moment 💍', 'chat' => '', 'note' => '"When I placed that ring on your finger, I secretly imagined forever."'],
                ['dot' => '📸', 'date' => '6 March 2026', 'title' => 'Photo Day 📸', 'chat' => '', 'note' => '"One picture was never enough to capture how happy I felt with you."'],
                ['dot' => '💖', 'date' => '12 March 2026', 'title' => 'Proper Love Confession 💖', 'chat' => "Her: \"Love you 😘❤️\"\nYou: \"Love you too ❤️\"", 'note' => '"That was the day our hearts stopped hiding."'],
                ['dot' => '🎂', 'date' => '1 May 2026', 'title' => 'Birthday 🎂', 'chat' => '', 'note' => '"Today is all about celebrating the most beautiful person in my life."'],
            ],
        ],

        'chats' => [
            'label'  => 'Screenshots from our story',
            'script' => 'Our Chats 💬',
            'scenes' => [
                ['tab' => 'Attachment 🥹', 'label' => 'Attachment Moment 🥹', 'messages' => [
                    ['side' => 'received', 'text' => 'You save everything about me 😂'],
                    ['side' => 'sent', 'text' => 'Everything? 😂'],
                    ['side' => 'received', 'text' => 'What if someday I accidentally lose you? My soul would leave my body 🥺❤️'],
                    ['side' => 'sent', 'text' => 'Drama queen 😂❤️'],
                ]],
                ['tab' => 'Craving 🌙', 'label' => 'Craving To Talk ❤️', 'messages' => [
                    ['side' => 'sent', 'text' => "Please don't go offline tonight :("],
                    ['side' => 'received', 'text' => 'Why? 👀'],
                    ['side' => 'sent', 'text' => "Because I'm craving talking to you ❤️"],
                    ['side' => 'received', 'text' => 'Then how can I leave now? 🥹'],
                ]],
                ['tab' => 'More Than Friends 💖', 'label' => 'More Than Friends Phase 💖', 'messages' => [
                    ['side' => 'sent', 'text' => 'Why do you call me baby? 😂'],
                    ['side' => 'received', 'text' => "Because this feels like that phase where two people are more than friends but haven't confessed yet ❤️"],
                ]],
                ['tab' => 'Future Husband 💍', 'label' => 'Future Husband Joke 💍', 'messages' => [
                    ['side' => 'received', 'text' => 'My internet finishes fast now 😂'],
                    ['side' => 'sent', 'text' => 'Why?'],
                    ['side' => 'received', 'text' => 'Because I spend all day talking to my future husband ❤️'],
                ]],
                ['tab' => 'First Love You ❤️', 'label' => 'First Love You ❤️', 'messages' => [
                    ['side' => 'sent', 'text' => 'Mai bhi soo jata hu love u ❤️ bye'],
                    ['side' => 'received', 'text' => 'Love u ❤️'],
                ]],
            ],
        ],

        'voice' => [
            'label'      => 'For your ears only',
            'script'     => 'Voice Note 🎙️',
            'subtitle'   => 'Press play — hear my voice, baby 🥹',
            'name'       => '[sender]',
            'avatar'     => '☀️',
            'transcript' => "\"Hey baby…\n\nI just wanted you to hear my voice while going through this surprise.\n\nI love you more than I can explain. You're not just my present, I genuinely see my future with you.\n\nI want to see you happy. I want to see you successful. I want to be standing beside you while you achieve everything you dream about.\n\nI want us growing together, laughing together, and creating beautiful memories side by side.\n\nYou're my safe place, my heartbeat, and my forever ❤️\"",
            'audio_url'  => '',
        ],

        'letter' => [
            'label'      => 'Written just for you',
            'script'     => 'Love Letter 💌',
            'audio_url'  => '',
            'paragraphs' => [
                '*My baby, my cutiepie, my rasmalai, my jaan, my oxygen, my heartbeat ❤️*',
                "I honestly don't know how to explain how much you mean to me because words always feel smaller than my love for you.",
                'Since you came into my life, everything feels happier, brighter, and more meaningful.',
                'Every notification from you makes me smile. Every call feels special. Every moment with you becomes a memory I never want to lose.',
                "You're not just my girlfriend — you're my peace, my comfort person, and the person I want beside me in every phase of life.",
                'The day I held your hand, the day I gave you that ring, the day we said I love you — every moment feels unforgettable.',
                "No matter what happens, I'll always choose you.",
                'Every single time.',
                '*Forever ❤️*',
            ],
        ],

        'puzzle' => [
            'label'       => 'Can you guess?',
            'script'      => 'Secret Puzzle 🔐',
            'question'    => "\"It's the day our hearts got closer before words could explain it…\ncan you guess the date?\"",
            'placeholder' => 'Type the date… (e.g. 6 March 2026)',
            'answers'     => ['6 march 2026', '06/03/2026', 'march 6 2026', '6/3/2026', '6 march', 'march 6', '6/3', '06-03-2026', 'march 6th 2026', '6th march 2026', '6 mar 2026'],
            'hints'       => ['The day we got closest ❤️', 'Exactly 42 days after we met ✨', 'The day I gave you a ring 💍'],
            'success'     => '✅ 6 March 2026 ❤️ You remembered! Now the secret unlocks…',
        ],

        'secret' => [
            'label'      => 'The secret is revealed',
            'script'     => 'Who Said It First? 🫶',
            'question'   => "Who was the first one to say\n*\"I love you\"*?",
            'name'       => '[sender] ❤️',
            'reveal_sub' => 'was the first one brave enough to say it 💖',
            'quote'      => "\"I said it first because I couldn't hold it in anymore — you already had my whole heart ❤️\"",
        ],

        'proposal' => [
            'label'  => 'The most important question',
            'script' => 'Forever? 💍',
            'body'   => "\"From every memory we've created\nto every dream we'll chase together…\n\n*Will you stay with me forever?*\"",
            'yes'    => 'YES ❤️',
            'always' => 'ALWAYS ❤️',
        ],

        'ending' => [
            'title'     => "Happy Birthday\nMy Love",
            'body'      => "\"No matter how many birthdays come and go…\n\nI'll always be grateful that someone as beautiful as you exists in my life.\n\nThank you for loving me.\nThank you for choosing me.\nThank you for being my favorite person.\n\n*Happy Birthday My Love ❤️🎂✨\"*",
            'signature' => '— With all my love, [sender] ❤️',
        ],

        'reply' => [
            'enabled'     => true,
            'heading'     => 'Reply to me 💌',
            'subtitle'    => 'Send me a little message back ❤️',
            'placeholder' => 'Write your reply here…',
            'button'      => 'Send Reply 💖',
            'success'     => 'Sent! Thank you baby ❤️',
        ],

        'music' => [
            'title'     => 'Perfect — Ed Sheeran',
            'audio_url' => '',
        ],

        'album' => [
            'enabled'  => true,
            'label'    => 'Our beautiful moments',
            'script'   => 'Photo Album 📸',
            'subtitle' => 'Tap a photo to view it 💕',
            'images'   => [], // up to 6 uploaded image paths
        ],

        'video' => [
            'enabled'  => true,
            'label'    => 'Press play',
            'script'   => 'A Special Video 🎬',
            'subtitle' => 'Something I recorded just for you',
            'url'      => '', // uploaded video path
        ],

        'cake' => [
            'enabled'  => true,
            'label'    => 'Make a wish first',
            'script'   => 'Cut the Cake 🎂',
            'subtitle' => 'Tap the cake to cut it!',
            'message'  => 'Happy Birthday [receiver]! 🎉',
            'button'   => 'Cut the Cake 🔪',
        ],
    ];
}

/**
 * Merge a saved config over the defaults. Scalars and whole list-arrays in the
 * saved config win; anything missing falls back to the default.
 */
function cinematic_birthday_config($page) {
    $defaults = cinematic_birthday_defaults();
    $saved = [];
    if (!empty($page['slide_data'])) {
        $decoded = json_decode($page['slide_data'], true);
        if (is_array($decoded)) $saved = $decoded;
    }
    $config = $defaults;
    foreach ($saved as $section => $val) {
        if (is_array($val) && isset($defaults[$section]) && is_array($defaults[$section])) {
            // Section-level merge: keep default keys, override with saved keys
            $config[$section] = array_replace($defaults[$section], $val);
        } else {
            $config[$section] = $val;
        }
    }
    return $config;
}

/**
 * Render the full cinematic birthday page. Expects a $page row from `pages`.
 */
function render_cinematic_birthday($page) {
    global $pdo;
    $c = cinematic_birthday_config($page);

    // Auto-fill names everywhere: [sender] / [receiver] tokens are replaced with
    // the two names from the top of the customizer. So a user types 2 names and
    // the whole page (signatures, voice, secret, title…) updates automatically.
    $tok = [
        '[sender]'   => $page['sender_name'] ?? '',
        '[Sender]'   => $page['sender_name'] ?? '',
        '[receiver]' => $page['receiver_name'] ?? '',
        '[Receiver]' => $page['receiver_name'] ?? '',
    ];
    array_walk_recursive($c, function (&$v) use ($tok) {
        if (is_string($v)) $v = strtr($v, $tok);
    });

    // --- Ownership & expiry gate (kept independent of the slide engine) ---
    $is_owner = false;
    if (function_exists('is_logged_in') && is_logged_in() &&
        (($page['user_id'] ?? null) == ($_SESSION['user_id'] ?? null) || (function_exists('is_super_admin') && is_super_admin()))) {
        $is_owner = true;
    } elseif (($page['guest_session_id'] ?? null) === session_id()) {
        $is_owner = true;
    }

    $is_expired = false;
    if (!empty($page['is_expired']) && (int)$page['is_expired'] === 1) {
        $is_expired = true;
    } elseif (!empty($page['expiry_date']) && strtotime($page['expiry_date']) < time()) {
        $is_expired = true;
    }
    if ($is_expired && !$is_owner) {
        echo '<div style="font-family:sans-serif;text-align:center;padding:60px 20px;background:#1a0810;color:#ffb3c1;min-height:100vh;">'
           . '<div style="font-size:3rem">⏳</div><h2>Memory Archived</h2>'
           . '<p style="color:rgba(255,200,210,0.6)">This page has reached its expiration date.</p></div>';
        return;
    }

    // --- Optional password gate ---
    if (!empty($page['password']) && !$is_owner) {
        $unlocked = ($_SESSION['cb_unlocked_' . $page['id']] ?? false) === true;
        if (!$unlocked && ($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['cb_password'])) {
            if (password_verify($_POST['cb_password'], $page['password'])) {
                $_SESSION['cb_unlocked_' . $page['id']] = true;
                $unlocked = true;
            }
        }
        if (!$unlocked) {
            $err = ($_SERVER['REQUEST_METHOD'] === 'POST') ? '<p style="color:#ff6b81;font-size:0.85rem">Wrong password, try again.</p>' : '';
            echo '<div style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#1a0810;color:#ffb3c1">'
               . '<form method="post" style="text-align:center;background:rgba(255,255,255,0.05);padding:36px;border-radius:20px;border:1px solid rgba(255,150,170,0.2)">'
               . '<div style="font-size:2.5rem">🔒</div><h2>This surprise is locked</h2>'
               . '<input type="password" name="cb_password" placeholder="Enter password" style="margin-top:16px;padding:12px 16px;border-radius:12px;border:none;width:100%;text-align:center" required>'
               . $err
               . '<button type="submit" style="margin-top:14px;background:#e8405a;color:#fff;border:none;border-radius:50px;padding:12px 32px;cursor:pointer;font-size:1rem">Unlock ❤️</button>'
               . '</form></div>';
            return;
        }
    }

    // --- Track view for analytics (best-effort) ---
    if (!$is_owner && function_exists('track_view') && !empty($page['id'])) {
        try { track_view($page['id'], $pdo); } catch (\Throwable $e) {}
    }

    // Resolve media URLs relative to the app base
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    $media = function ($u) use ($base) {
        if (empty($u)) return '';
        if (preg_match('#^https?://#i', $u)) return $u;
        return $base . ltrim($u, '/');
    };
    $vn_audio     = $media($c['voice']['audio_url'] ?? '');
    $letter_audio = $media($c['letter']['audio_url'] ?? '');
    $music_audio  = $media($c['music']['audio_url'] ?? '');
    $video_url    = $media($c['video']['url'] ?? '');
    $album_images = [];
    foreach (($c['album']['images'] ?? []) as $img) {
        $u = is_array($img) ? ($img['medium'] ?? $img['path'] ?? '') : $img;
        if (!empty($u)) $album_images[] = $media($u);
    }
    $album_images = array_slice($album_images, 0, 6);

    // Which optional slides to show (also controls navigation order)
    $show_album = !empty($c['album']['enabled']) && !empty($album_images);
    $show_video = !empty($c['video']['enabled']) && !empty($video_url);
    $show_cake  = !empty($c['cake']['enabled']);

    // Ordered list of slide ids that are actually rendered → drives next()/prev()
    $slides = ['page-landing', 'page-intro'];
    if ($show_cake) $slides[] = 'page-cake';
    $slides = array_merge($slides, ['page-memories', 'page-chats']);
    if ($show_album) $slides[] = 'page-album';
    $slides[] = 'page-voicenote';
    $slides[] = 'page-letter';
    if ($show_video) $slides[] = 'page-video';
    $slides = array_merge($slides, ['page-puzzle', 'page-secret', 'page-proposal', 'page-ending']);

    $col = $c['colors'];
    // Escaping helper (falls back to htmlspecialchars if app h() missing)
    $e = function ($s) { return function_exists('h') ? h($s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    // Content fields go through the safe formatter (escape + *bold* + line breaks).
    // A user can delete anything and the page still renders — no HTML to break.
    $raw = function ($s) { return cb_format($s); };
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($c['page_title']) ?></title>
<meta property="og:title" content="<?= $e($c['page_title']) ?>">
<meta property="og:description" content="A little birthday surprise, made with love ❤️">
<meta property="og:type" content="website">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=DM+Sans:wght@300;400;500&family=Satisfy&display=swap" rel="stylesheet">
<style>
  :root {
    --rose: <?= $e($col['rose']) ?>;
    --blush: <?= $e($col['blush']) ?>;
    --cream: <?= $e($col['cream']) ?>;
    --deep: <?= $e($col['deep']) ?>;
    --gold: <?= $e($col['gold']) ?>;
    --soft: <?= $e($col['soft']) ?>;
    --text: <?= $e($col['text']) ?>;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { width: 100%; height: 100%; background: var(--deep); font-family: 'DM Sans', sans-serif; color: var(--text); overflow: hidden; }
  .page { position: fixed; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.7s ease; overflow-y: auto; padding: 20px; }
  .page.active { opacity: 1; pointer-events: all; }
  .petal-bg { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
  .petal { position: absolute; top: -60px; font-size: 1.2rem; opacity: 0.4; animation: fall linear infinite; }
  @keyframes fall { to { transform: translateY(110vh) rotate(360deg); opacity: 0; } }
  .card { background: rgba(255,255,255,0.97); border-radius: 24px; padding: 40px 36px; max-width: 520px; width: 100%; box-shadow: 0 20px 60px rgba(232,64,90,0.2), 0 2px 12px rgba(0,0,0,0.1); position: relative; z-index: 1; text-align: center; }
  .card-small { max-width: 440px; padding: 32px 28px; }
  .big-title { font-family: 'Cormorant Garamond', serif; font-size: clamp(2rem, 8vw, 3.2rem); font-weight: 300; line-height: 1.15; color: var(--rose); letter-spacing: -0.5px; }
  .script { font-family: 'Satisfy', cursive; font-size: clamp(1.6rem, 5vw, 2.4rem); color: var(--rose); }
  .subtitle { font-size: 0.95rem; color: #8a6070; line-height: 1.7; margin-top: 12px; font-style: italic; }
  .body-text { font-size: 0.95rem; color: var(--text); line-height: 1.8; }
  .label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: #b08090; margin-bottom: 6px; }
  .btn { display: inline-block; background: linear-gradient(135deg, var(--rose), #c4184e); color: white; border: none; border-radius: 50px; padding: 14px 36px; font-size: 1rem; font-family: 'DM Sans', sans-serif; font-weight: 500; cursor: pointer; margin-top: 24px; box-shadow: 0 8px 24px rgba(232,64,90,0.35); transition: transform 0.2s, box-shadow 0.2s; text-decoration: none; }
  .btn:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(232,64,90,0.45); }
  .btn:active { transform: translateY(0); }
  .btn-outline { background: transparent; border: 2px solid var(--rose); color: var(--rose); box-shadow: none; margin-top: 12px; }
  .btn-outline:hover { background: var(--soft); }
  .btn-ghost { background: transparent; border: none; color: #b08090; font-size: 0.85rem; cursor: pointer; font-family: 'DM Sans', sans-serif; margin-top: 8px; padding: 8px 16px; text-decoration: underline; }
  .divider { display: flex; align-items: center; gap: 10px; margin: 20px 0; color: #d4a0b0; font-size: 1.2rem; }
  .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: linear-gradient(to right, transparent, #e8c0cc, transparent); }
  .timeline { text-align: left; width: 100%; margin-top: 8px; }
  .timeline-item { display: flex; gap: 16px; margin-bottom: 28px; align-items: flex-start; }
  .tl-dot { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--rose), #ff8fa3); display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; margin-top: 2px; box-shadow: 0 4px 12px rgba(232,64,90,0.3); }
  .tl-content { flex: 1; }
  .tl-date { font-size: 0.72rem; color: #b08090; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 4px; }
  .tl-title { font-family: 'Cormorant Garamond', serif; font-size: 1.15rem; font-weight: 600; color: var(--rose); margin-bottom: 4px; }
  .tl-note { font-size: 0.88rem; color: #7a5060; font-style: italic; line-height: 1.5; }
  .chat-window { background: #1c1c1e; border-radius: 20px; padding: 20px; text-align: left; margin-top: 16px; max-height: 260px; overflow-y: auto; }
  .chat-row { display: flex; margin-bottom: 10px; }
  .chat-row.sent { justify-content: flex-end; }
  .chat-row.received { justify-content: flex-start; }
  .bubble { max-width: 75%; padding: 10px 14px; border-radius: 18px; font-size: 0.88rem; line-height: 1.5; }
  .bubble.sent { background: #e8405a; color: white; border-bottom-right-radius: 4px; }
  .bubble.received { background: #2c2c2e; color: #f0f0f0; border-bottom-left-radius: 4px; }
  .chat-label { font-size: 0.7rem; color: #b08090; text-align: center; margin-bottom: 10px; font-style: italic; }
  .letter-body { background: #fffdf8; border: 1px solid #f0dde4; border-radius: 16px; padding: 28px; text-align: left; font-family: 'Cormorant Garamond', serif; font-size: 1.05rem; line-height: 1.9; color: #3a1a22; margin-top: 16px; max-height: 300px; overflow-y: auto; position: relative; }
  .letter-body::before { content: '❝'; position: absolute; top: 12px; left: 16px; font-size: 3rem; color: var(--blush); line-height: 1; }
  .letter-body p { margin-bottom: 12px; padding-left: 8px; }
  .puzzle-input { width: 100%; padding: 14px 18px; border: 2px solid #f0c0cc; border-radius: 14px; font-size: 1rem; font-family: 'DM Sans', sans-serif; outline: none; color: var(--text); background: var(--cream); transition: border-color 0.2s; margin-top: 16px; text-align: center; }
  .puzzle-input:focus { border-color: var(--rose); }
  .hint-box { background: var(--soft); border-radius: 12px; padding: 12px 16px; margin-top: 10px; font-size: 0.88rem; color: #8a5060; text-align: left; border-left: 3px solid var(--blush); }
  .feedback { margin-top: 12px; font-size: 0.9rem; min-height: 24px; font-style: italic; color: var(--rose); }
  .vn-player { background: linear-gradient(135deg, #1a0810, #2d0f1e); border-radius: 20px; padding: 24px; margin-top: 16px; text-align: left; }
  .vn-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
  .vn-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--rose), #ff8fa3); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
  .vn-name { color: white; font-size: 0.9rem; font-weight: 500; }
  .vn-time { color: #a07080; font-size: 0.75rem; }
  .vn-controls { display: flex; align-items: center; gap: 14px; }
  .play-btn { width: 48px; height: 48px; border-radius: 50%; background: var(--rose); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; transition: transform 0.2s, background 0.2s; box-shadow: 0 4px 16px rgba(232,64,90,0.5); }
  .play-btn:hover { transform: scale(1.08); }
  .waveform { flex: 1; display: flex; align-items: center; gap: 3px; height: 36px; cursor: pointer; }
  .wbar { flex: 1; border-radius: 2px; background: #4a2030; transition: background 0.1s; min-width: 3px; }
  .wbar.played { background: var(--rose); }
  .vn-duration { color: #a07080; font-size: 0.78rem; flex-shrink: 0; }
  .vn-transcript { margin-top: 14px; background: rgba(255,255,255,0.05); border-radius: 10px; padding: 14px; color: #d0b0b8; font-size: 0.85rem; line-height: 1.7; font-style: italic; }
  .music-bar { position: fixed; bottom: 0; left: 0; right: 0; background: rgba(26,8,16,0.95); backdrop-filter: blur(12px); padding: 10px 20px; display: flex; align-items: center; gap: 14px; z-index: 100; border-top: 1px solid rgba(232,64,90,0.2); }
  .music-info { flex: 1; min-width: 0; }
  .music-title { color: white; font-size: 0.85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .music-artist { color: #a07080; font-size: 0.72rem; }
  .music-btn { background: none; border: none; color: white; font-size: 1.1rem; cursor: pointer; padding: 6px; opacity: 0.8; transition: opacity 0.2s; }
  .music-btn:hover { opacity: 1; }
  .music-progress { flex: 1; height: 3px; background: #4a2030; border-radius: 2px; cursor: pointer; position: relative; }
  .music-fill { height: 100%; background: var(--rose); border-radius: 2px; width: 0%; transition: width 0.5s linear; }
  .proposal-btns { display: flex; gap: 16px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
  .yes-btn { background: linear-gradient(135deg, #e8405a, #ff7090); color: white; border: none; border-radius: 50px; padding: 16px 40px; font-size: 1.1rem; cursor: pointer; font-family: 'DM Sans', sans-serif; font-weight: 500; box-shadow: 0 8px 28px rgba(232,64,90,0.4); transition: transform 0.2s; }
  .yes-btn:hover { transform: scale(1.05); }
  .heart-particle { position: fixed; pointer-events: none; z-index: 999; font-size: 1.5rem; animation: heartFloat 1.5s ease-out forwards; }
  @keyframes heartFloat { 0% { opacity: 1; transform: translateY(0) scale(1); } 100% { opacity: 0; transform: translateY(-80px) scale(0.3); } }
  .progress-dots { position: fixed; bottom: 60px; left: 50%; transform: translateX(-50%); display: flex; gap: 6px; z-index: 50; }
  .dot { width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,0.2); transition: all 0.3s; }
  .dot.active { background: var(--rose); width: 18px; border-radius: 3px; }
  #page-landing { background: linear-gradient(160deg, #1a0810 0%, #2d0f1e 50%, #1a0810 100%); }
  #page-landing .card { background: rgba(255,255,255,0); box-shadow: none; color: white; }
  #page-landing .big-title { color: #ffb3c1; }
  #page-landing .subtitle { color: rgba(255,200,210,0.7); }
  #page-landing .btn { background: linear-gradient(135deg, #e8405a, #ff7090) !important; color: white !important; border: 2px solid rgba(255,255,255,0.3); font-size: 1.05rem; padding: 16px 40px; }
  #page-intro { background: var(--cream); }
  #page-memories { background: #fdf5f7; }
  #page-chats { background: var(--cream); }
  #page-voicenote { background: linear-gradient(160deg, #1a0810, #2d0f1e); }
  #page-letter { background: #fffdf8; }
  #page-puzzle { background: var(--cream); }
  #page-secret { background: var(--soft); }
  #page-proposal { background: linear-gradient(160deg, #1a0810, #2d0f1e); }
  #page-ending { background: linear-gradient(160deg, #1a0810 0%, #2d0f1e 50%, #1a0810 100%); }
  #page-ending .card { background: transparent; box-shadow: none; color: white; }
  #page-ending .big-title { color: #ffb3c1; }
  #page-ending .body-text { color: rgba(255,200,210,0.85); }
  #page-memories .card, #page-chats .card, #page-letter .card { max-height: calc(100vh - 120px); overflow-y: auto; }
  .chat-tabs { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-bottom: 16px; }
  .chat-tab { padding: 6px 14px; border-radius: 20px; font-size: 0.78rem; border: 1.5px solid #e8c0cc; background: white; cursor: pointer; color: #8a6070; transition: all 0.2s; font-family: 'DM Sans', sans-serif; }
  .chat-tab.active { background: var(--rose); color: white; border-color: var(--rose); }
  .chat-scene { display: none; }
  .chat-scene.active { display: block; }
  @keyframes pulse-ring { 0% { box-shadow: 0 0 0 0 rgba(232,64,90,0.4); } 70% { box-shadow: 0 0 0 16px rgba(232,64,90,0); } 100% { box-shadow: 0 0 0 0 rgba(232,64,90,0); } }
  .ring-emoji { display: inline-block; animation: pulse-ring 2s infinite; border-radius: 50%; padding: 4px; }
  @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
  .page.active .card { animation: slideUp 0.5s ease-out both; }
  ::-webkit-scrollbar { width: 4px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: var(--blush); border-radius: 2px; }

  /* ── PHOTO ALBUM ── */
  #page-album { background: #fdf5f7; }
  .album-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 16px; }
  .album-grid.count-1 { grid-template-columns: 1fr; }
  .album-grid.count-2, .album-grid.count-4 { grid-template-columns: repeat(2, 1fr); }
  .album-cell { position: relative; aspect-ratio: 1/1; border-radius: 14px; overflow: hidden; cursor: pointer; box-shadow: 0 6px 18px rgba(232,64,90,0.15); background:#f0dde4; }
  .album-cell img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; display:block; }
  .album-cell:hover img { transform: scale(1.08); }
  .album-cell::after { content: '🔍'; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(232,64,90,0.0); color: transparent; font-size: 1.4rem; transition: all 0.3s; }
  .album-cell:hover::after { background: rgba(26,8,16,0.35); color: #fff; }
  .lightbox { position: fixed; inset: 0; background: rgba(10,4,7,0.94); z-index: 1000; display: none; align-items: center; justify-content: center; flex-direction: column; padding: 20px; }
  .lightbox.open { display: flex; }
  .lightbox img { max-width: 92vw; max-height: 78vh; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.6); }
  .lb-close { position: absolute; top: 18px; right: 22px; color: #fff; font-size: 2rem; cursor: pointer; opacity: 0.8; background:none; border:none; }
  .lb-nav { display: flex; gap: 24px; margin-top: 18px; }
  .lb-nav button { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,150,170,0.3); color: #fff; width: 46px; height: 46px; border-radius: 50%; font-size: 1.2rem; cursor: pointer; }
  .lb-nav button:hover { background: var(--rose); }

  /* ── VIDEO ── */
  #page-video { background: linear-gradient(160deg, #1a0810, #2d0f1e); }
  .video-frame { margin-top: 16px; border-radius: 20px; overflow: hidden; padding: 8px; background: linear-gradient(135deg, rgba(232,64,90,0.35), rgba(255,143,163,0.2)); box-shadow: 0 18px 50px rgba(232,64,90,0.35); position: relative; }
  .video-frame video { width: 100%; display: block; border-radius: 14px; background: #000; max-height: 62vh; }
  .video-glow { position:absolute; inset:-2px; border-radius:22px; background:conic-gradient(from 0deg, #e8405a,#ff8fa3,#c4184e,#e8405a); filter:blur(14px); opacity:0.35; z-index:-1; animation: spinGlow 6s linear infinite; }
  @keyframes spinGlow { to { transform: rotate(360deg); } }

  /* ── BALLOONS ── */
  .balloon-layer { position: fixed; inset: 0; pointer-events: none; z-index: 1; overflow: hidden; }
  .balloon { position: absolute; bottom: -140px; font-size: 2.6rem; animation: floatUp linear infinite; opacity: 0.9; }
  @keyframes floatUp {
    0% { transform: translateY(0) translateX(0) rotate(-6deg); opacity: 0; }
    12% { opacity: 0.9; }
    88% { opacity: 0.9; }
    100% { transform: translateY(-118vh) translateX(30px) rotate(6deg); opacity: 0; }
  }

  /* ── CONFETTI ── */
  .confetti-piece { position: fixed; top: -20px; width: 10px; height: 14px; z-index: 998; pointer-events: none; border-radius: 2px; animation: confettiFall linear forwards; }
  @keyframes confettiFall {
    0% { transform: translateY(-20px) rotate(0deg); opacity: 1; }
    100% { transform: translateY(105vh) rotate(720deg); opacity: 0; }
  }

  /* ── CAKE CUTTING ── */
  #page-cake { background: linear-gradient(160deg, #2d0f1e, #1a0810); }
  .cake-wrap { position: relative; width: 260px; height: 230px; margin: 24px auto 10px; cursor: pointer; user-select: none; }
  .cake-wrap:active { transform: scale(0.99); }
  .cake-plate { position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 250px; height: 26px; background: radial-gradient(ellipse at center, #ffffff, #e4b3c1); border-radius: 50%; box-shadow: 0 12px 26px rgba(0,0,0,0.45); }
  .cake-body { position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); width: 190px; height: 130px; }
  .cake-layer { position: absolute; left: 50%; transform: translateX(-50%); border-radius: 10px; box-shadow: inset 0 -6px 0 rgba(0,0,0,0.12); }
  .layer-bottom { bottom: 0; width: 190px; height: 70px; background: linear-gradient(#e8405a, #c4184e); }
  .layer-top { bottom: 60px; width: 158px; height: 60px; background: linear-gradient(#ff8fa3, #e8405a); }
  .frosting { position: absolute; top: -9px; left: 0; width: 100%; height: 20px; background: #fff8f5; border-radius: 12px 12px 40% 40% / 12px 12px 100% 100%; }
  .frosting::before, .frosting::after { content: ''; position: absolute; top: 8px; width: 26px; height: 20px; background: #fff8f5; border-radius: 50%; }
  .frosting::before { left: 22%; } .frosting::after { right: 22%; }
  .candle { position: absolute; bottom: 154px; width: 8px; height: 34px; border-radius: 3px; background: repeating-linear-gradient(45deg, #fff 0 6px, #e8405a 6px 12px); z-index: 3; }
  .candle-flame { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); width: 12px; height: 17px; background: radial-gradient(circle at 50% 75%, #fff59d, #ff9800 55%, #ff5722 100%); border-radius: 50% 50% 50% 50% / 60% 60% 42% 42%; box-shadow: 0 0 16px 3px rgba(255,152,0,0.7); transform-origin: bottom center; animation: flick 0.4s infinite alternate; transition: opacity 0.4s, transform 0.4s; }
  @keyframes flick { from { transform: translateX(-50%) scaleY(1) rotate(-2deg); } to { transform: translateX(-50%) scaleY(1.18) translateY(-2px) rotate(2deg); } }
  .cake-wrap.cut .candle-flame { opacity: 0; transform: translateX(-50%) scaleY(0); }
  .knife { position: absolute; top: -30px; right: 6px; font-size: 2.4rem; z-index: 6; transition: transform 0.7s cubic-bezier(.5,-0.25,.35,1.3); filter: drop-shadow(0 4px 6px rgba(0,0,0,0.4)); }
  .cake-wrap.cut .knife { transform: translate(-96px, 150px) rotate(38deg); }
  .cut-wedge { position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%) scaleY(0.6); width: 0; height: 0; border-left: 20px solid transparent; border-right: 20px solid transparent; border-bottom: 122px solid rgba(20,6,11,0.6); opacity: 0; transform-origin: bottom; transition: opacity 0.35s 0.55s; z-index: 4; pointer-events: none; }
  .cake-wrap.cut .cut-wedge { opacity: 1; }

  /* ── LOADING SCREEN ── */
  #cbLoader { position: fixed; inset: 0; z-index: 3000; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(160deg, #1a0810 0%, #2d0f1e 55%, #1a0810 100%); transition: opacity 0.6s ease; }
  #cbLoader.hide { opacity: 0; pointer-events: none; }
  .cbld-cake { font-size: 4rem; animation: cbBob 1.3s ease-in-out infinite; }
  @keyframes cbBob { 0%,100% { transform: translateY(0) rotate(-3deg); } 50% { transform: translateY(-12px) rotate(3deg); } }
  .cbld-title { font-family: 'Cormorant Garamond', serif; color: #ffb3c1; font-size: 1.5rem; margin-top: 16px; letter-spacing: 0.5px; }
  .cbld-sub { color: rgba(255,200,210,0.55); font-size: 0.85rem; margin-top: 6px; font-style: italic; }
  .cbld-bar { width: 180px; height: 5px; background: rgba(255,255,255,0.12); border-radius: 4px; margin-top: 22px; overflow: hidden; }
  .cbld-fill { height: 100%; width: 40%; border-radius: 4px; background: linear-gradient(90deg, #e8405a, #ff8fa3); animation: cbSlide 1.2s ease-in-out infinite; }
  @keyframes cbSlide { 0% { margin-left: -40%; } 100% { margin-left: 100%; } }
  .cbld-dots { margin-top: 14px; font-size: 1.2rem; letter-spacing: 6px; animation: cbPulse 1.4s ease-in-out infinite; }
  @keyframes cbPulse { 0%,100% { opacity: 0.4; } 50% { opacity: 1; } }
</style>
</head>
<body>

<?php if (!empty($page['_preview'])): ?>
<div style="position:fixed;top:0;left:0;right:0;z-index:3000;background:#1a0810;color:#ffb3c1;padding:9px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-family:'DM Sans',sans-serif;font-size:0.78rem;border-bottom:1px solid rgba(232,64,90,0.45);box-shadow:0 4px 16px rgba(0,0,0,0.4);">
  <span>🔍 <b>Preview</b> — this is just a preview (not saved). Go back to the editor tab to make changes. Newly selected photos/video will appear after you publish.</span>
  <button onclick="window.close()" style="flex-shrink:0;background:#e8405a;color:#fff;border:none;border-radius:20px;padding:6px 14px;cursor:pointer;font-weight:600;">✕ Close</button>
</div>
<?php endif; ?>

<!-- LOADING SCREEN -->
<div id="cbLoader">
  <div class="cbld-cake">🎂</div>
  <div class="cbld-title"><?= $e($c['landing']['name'] ?: 'A Surprise') ?> ❤️</div>
  <div class="cbld-sub">Loading your surprise…</div>
  <div class="cbld-bar"><div class="cbld-fill"></div></div>
  <div class="cbld-dots">💕 💗 💖</div>
</div>

<div class="petal-bg" id="petalBg"></div>
<div class="balloon-layer" id="balloonLayer"></div>

<!-- Photo lightbox -->
<div class="lightbox" id="lightbox">
  <button class="lb-close" onclick="closeLightbox()">✕</button>
  <img id="lbImg" src="" alt="">
  <div class="lb-nav">
    <button onclick="lbStep(-1)">‹</button>
    <button onclick="lbStep(1)">›</button>
  </div>
</div>

<!-- PAGE 1 — LANDING -->
<div class="page active" id="page-landing">
  <div class="card" style="text-align:center">
    <div style="font-size:3rem; margin-bottom:16px; animation: pulse-ring 2s infinite; display:inline-block; border-radius:50%; padding:8px"><?= $e($c['landing']['emoji']) ?></div>
    <div class="big-title"><?= $e($c['landing']['name']) ?></div>
    <div class="script" style="color:#ffb3c1; margin-top:4px"><?= $e($c['landing']['script']) ?></div>
    <div class="subtitle"><?= $raw($c['landing']['subtitle']) ?></div>
    <div style="margin-top:32px">
      <button onclick="goTo('page-intro')" style="display:inline-block; background:linear-gradient(135deg,#e8405a,#ff7090); color:white; border:2px solid rgba(255,255,255,0.3); border-radius:50px; padding:16px 40px; font-size:1.05rem; font-family:'DM Sans',sans-serif; font-weight:500; cursor:pointer; box-shadow:0 8px 28px rgba(232,64,90,0.5); transition:transform 0.2s;"><?= $e($c['landing']['btn']) ?></button>
    </div>
    <div style="color:rgba(255,200,210,0.4); font-size:0.75rem; margin-top:20px"><?= $e($c['landing']['hint']) ?></div>
  </div>
</div>

<!-- PAGE 2 — INTRO MESSAGE -->
<div class="page" id="page-intro">
  <div class="card">
    <div class="label"><?= $e($c['intro']['label']) ?></div>
    <div class="script"><?= $e($c['intro']['script']) ?></div>
    <div class="divider">❤️</div>
    <div class="body-text" style="font-style:italic; color:#5a2a3a; line-height:2"><?= $raw($c['intro']['body']) ?></div>
    <div style="margin-top:8px; color:#b08090; font-size:0.85rem"><?= $e($c['intro']['signature']) ?></div>
    <button class="btn" onclick="next()">Our Memories 🌸</button>
    <br><button class="btn-ghost" onclick="goTo('page-landing')">← Back</button>
  </div>
</div>

<?php if ($show_cake): ?>
<!-- PAGE — CAKE CUTTING -->
<div class="page" id="page-cake">
  <div class="card" style="background:rgba(255,255,255,0.05); box-shadow:none; color:white; border:1px solid rgba(255,150,170,0.2)">
    <div class="label" style="color:#ff8fa3"><?= $e($c['cake']['label']) ?></div>
    <div class="script" style="color:#ffb3c1"><?= $e($c['cake']['script']) ?></div>
    <div style="color:rgba(255,200,210,0.6); font-size:0.85rem; margin-top:6px; font-style:italic;"><?= $e($c['cake']['subtitle']) ?></div>
    <div class="cake-wrap" id="cakeWrap" onclick="cutCake()">
      <div class="knife">🔪</div>
      <div class="candle" style="left:106px"><div class="candle-flame"></div></div>
      <div class="candle" style="left:126px"><div class="candle-flame"></div></div>
      <div class="candle" style="left:146px"><div class="candle-flame"></div></div>
      <div class="cake-body">
        <div class="cake-layer layer-bottom"></div>
        <div class="cake-layer layer-top"><div class="frosting"></div></div>
      </div>
      <div class="cut-wedge"></div>
      <div class="cake-plate"></div>
    </div>
    <div id="cakeMsg" class="script" style="display:none; color:#ffb3c1; font-size:1.6rem; margin-top:4px;"><?= $e($c['cake']['message']) ?></div>
    <button class="btn" id="cakeCutBtn" onclick="cutCake()"><?= $e($c['cake']['button']) ?></button>
    <div id="cakeNextWrap" style="display:none;"><button class="btn" onclick="next()">Continue 💕</button></div>
    <br><button class="btn-ghost" style="color:#a07080" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- PAGE 3 — MEMORIES TIMELINE -->
<div class="page" id="page-memories">
  <div class="card">
    <div class="label"><?= $e($c['memories']['label']) ?></div>
    <div class="script"><?= $e($c['memories']['script']) ?></div>
    <div class="divider">🕰️</div>
    <div class="timeline">
      <?php foreach ($c['memories']['items'] as $it): ?>
      <div class="timeline-item">
        <div class="tl-dot"><?= $e($it['dot'] ?? '✨') ?></div>
        <div class="tl-content">
          <div class="tl-date"><?= $e($it['date'] ?? '') ?></div>
          <div class="tl-title"><?= $e($it['title'] ?? '') ?></div>
          <?php if (!empty($it['chat'])): ?>
          <div style="background:#f5f0f2; border-radius:10px; padding:10px 12px; font-size:0.85rem; margin:6px 0; color:#3a1a22"><?= $raw($it['chat']) ?></div>
          <?php endif; ?>
          <div class="tl-note"><?= $e($it['note'] ?? '') ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn" onclick="goTo('page-chats')">Our Chats 💬</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>

<!-- PAGE 4 — CHATS -->
<div class="page" id="page-chats">
  <div class="card">
    <div class="label"><?= $e($c['chats']['label']) ?></div>
    <div class="script"><?= $e($c['chats']['script']) ?></div>
    <div class="divider">💌</div>
    <div class="chat-tabs">
      <?php foreach ($c['chats']['scenes'] as $si => $sc): ?>
      <button class="chat-tab<?= $si === 0 ? ' active' : '' ?>" onclick="showChat(<?= (int)$si ?>)"><?= $e($sc['tab'] ?? ('Chat ' . ($si + 1))) ?></button>
      <?php endforeach; ?>
    </div>
    <?php foreach ($c['chats']['scenes'] as $si => $sc): ?>
    <div class="chat-scene<?= $si === 0 ? ' active' : '' ?>" id="cs<?= (int)$si ?>">
      <div class="chat-label"><?= $e($sc['label'] ?? '') ?></div>
      <div class="chat-window">
        <?php foreach (($sc['messages'] ?? []) as $m): $side = ($m['side'] ?? 'received') === 'sent' ? 'sent' : 'received'; ?>
        <div class="chat-row <?= $side ?>"><div class="bubble <?= $side ?>"><?= $raw($m['text'] ?? '') ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="goTo('page-memories')">← Back</button>
  </div>
</div>

<?php if ($show_album): ?>
<!-- PAGE — PHOTO ALBUM -->
<div class="page" id="page-album">
  <div class="card">
    <div class="label"><?= $e($c['album']['label']) ?></div>
    <div class="script"><?= $e($c['album']['script']) ?></div>
    <div style="color:#8a6070; font-size:0.85rem; margin-top:6px; font-style:italic;"><?= $e($c['album']['subtitle']) ?></div>
    <div class="album-grid count-<?= count($album_images) ?>">
      <?php foreach ($album_images as $ai => $img): ?>
      <div class="album-cell" onclick="openLightbox(<?= (int)$ai ?>)"><img src="<?= $e($img) ?>" loading="lazy" alt="Memory <?= (int)$ai + 1 ?>"></div>
      <?php endforeach; ?>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- PAGE 5 — VOICE NOTE -->
<div class="page" id="page-voicenote">
  <div class="card card-small">
    <div class="label" style="color:#ff8fa3"><?= $e($c['voice']['label']) ?></div>
    <div class="script" style="color:#ffb3c1"><?= $e($c['voice']['script']) ?></div>
    <div style="color:rgba(255,200,210,0.6); font-size:0.85rem; margin-top:6px; font-style:italic"><?= $e($c['voice']['subtitle']) ?></div>
    <div class="vn-player">
      <div class="vn-header">
        <div class="vn-avatar"><?= $e($c['voice']['avatar']) ?></div>
        <div>
          <div class="vn-name"><?= $e($c['voice']['name']) ?></div>
          <div class="vn-time" id="vnTime">Voice Message ✅</div>
        </div>
      </div>
      <div class="vn-controls">
        <button class="play-btn" id="vnPlayBtn" onclick="toggleVN()">▶</button>
        <div class="waveform" id="waveform" onclick="seekVN(event)"></div>
        <div class="vn-duration" id="vnDuration">0:00</div>
      </div>
      <div class="vn-transcript"><?= $raw($c['voice']['transcript']) ?></div>
      <?php if ($vn_audio): ?><audio id="vnAudio" src="<?= $e($vn_audio) ?>" preload="metadata"></audio><?php endif; ?>
    </div>
    <button class="btn" style="margin-top:20px" onclick="next()">Love Letter 💌</button>
    <br><button class="btn-ghost" style="color:#a07080" onclick="prev()">← Back</button>
  </div>
</div>

<!-- PAGE 6 — LOVE LETTER -->
<div class="page" id="page-letter">
  <div class="card">
    <div class="label"><?= $e($c['letter']['label']) ?></div>
    <div class="script"><?= $e($c['letter']['script']) ?></div>
    <div class="divider">🌹</div>
    <?php if ($letter_audio): ?>
    <div style="background:linear-gradient(135deg,#1a0810,#2d0f1e);border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;margin-bottom:16px;">
      <button id="letterPlayBtn" onclick="toggleLetter()" style="width:38px;height:38px;border-radius:50%;background:var(--rose);border:none;cursor:pointer;font-size:1rem;color:white;flex-shrink:0;box-shadow:0 4px 12px rgba(232,64,90,0.4);">▶</button>
      <div style="flex:1;min-width:0;">
        <div style="color:white;font-size:0.82rem;font-weight:500;">Letter Audio 💌</div>
        <div style="height:3px;background:#4a2030;border-radius:2px;margin-top:6px;cursor:pointer;" onclick="seekLetter(event)">
          <div id="letterFill" style="height:100%;background:var(--rose);border-radius:2px;width:0%;transition:width 0.3s linear;"></div>
        </div>
      </div>
      <div id="letterDur" style="color:#a07080;font-size:0.75rem;flex-shrink:0;">0:00</div>
    </div>
    <audio id="letterAudio" src="<?= $e($letter_audio) ?>" preload="metadata"></audio>
    <?php endif; ?>
    <div class="letter-body">
      <?php foreach ($c['letter']['paragraphs'] as $p): ?>
      <p><?= $raw($p) ?></p>
      <?php endforeach; ?>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>

<?php if ($show_video): ?>
<!-- PAGE — VIDEO -->
<div class="page" id="page-video">
  <div class="card">
    <div class="label" style="color:#ff8fa3"><?= $e($c['video']['label']) ?></div>
    <div class="script" style="color:#ffb3c1"><?= $e($c['video']['script']) ?></div>
    <div style="color:rgba(255,200,210,0.6); font-size:0.85rem; margin-top:6px; font-style:italic;"><?= $e($c['video']['subtitle']) ?></div>
    <div class="video-frame">
      <div class="video-glow"></div>
      <video src="<?= $e($video_url) ?>" controls playsinline muted preload="metadata"></video>
      <div style="text-align:center; color:rgba(255,200,210,0.5); font-size:0.72rem; margin-top:8px;">🔇 Muted — unmute for sound (background music will pause)</div>
    </div>
    <button class="btn" onclick="next()">Continue 💕</button>
    <br><button class="btn-ghost" style="color:#a07080" onclick="prev()">← Back</button>
  </div>
</div>
<?php endif; ?>

<!-- PAGE 7 — PUZZLE -->
<div class="page" id="page-puzzle">
  <div class="card card-small">
    <div class="label"><?= $e($c['puzzle']['label']) ?></div>
    <div class="script"><?= $e($c['puzzle']['script']) ?></div>
    <div class="divider">🔑</div>
    <div class="body-text" style="font-style:italic; color:#5a2a3a; font-size:1rem"><?= $raw($c['puzzle']['question']) ?></div>
    <input class="puzzle-input" id="puzzleInput" type="text" placeholder="<?= $e($c['puzzle']['placeholder']) ?>" onkeydown="if(event.key==='Enter')checkPuzzle()">
    <div id="puzzleFeedback" class="feedback"></div>
    <button class="btn" onclick="checkPuzzle()">Check Answer ✨</button>
    <?php if (!empty($c['puzzle']['hints'])): ?>
    <div style="margin-top:16px"><button class="btn-outline" onclick="toggleHints()">Show Hints 💡</button></div>
    <div id="hintsBox" style="display:none">
      <?php foreach ($c['puzzle']['hints'] as $hi => $hint): ?>
      <div class="hint-box"<?= $hi > 0 ? ' style="margin-top:6px"' : '' ?>>💡 Hint <?= (int)$hi + 1 ?>: <?= $e($hint) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <br><button class="btn-ghost" onclick="prev()">← Back</button>
  </div>
</div>

<!-- PAGE 8 — SECRET REVEAL -->
<div class="page" id="page-secret">
  <div class="card card-small">
    <div style="font-size:3rem; margin-bottom:12px">🔓</div>
    <div class="label"><?= $e($c['secret']['label']) ?></div>
    <div class="script"><?= $e($c['secret']['script']) ?></div>
    <div class="divider">❤️</div>
    <div class="body-text" style="font-size:1rem; margin-bottom:16px; color:#5a2a3a"><?= $raw($c['secret']['question']) ?></div>
    <div style="font-size:4rem; margin:12px 0">🫣</div>
    <div id="secretReveal" style="display:none">
      <div style="background: linear-gradient(135deg, var(--rose), #ff8fa3); color:white; border-radius:16px; padding:20px; margin:12px 0">
        <div style="font-size:1.8rem; font-weight:600; font-family:'Cormorant Garamond',serif"><?= $e($c['secret']['name']) ?></div>
        <div style="font-size:0.85rem; margin-top:6px; opacity:0.9; font-style:italic"><?= $e($c['secret']['reveal_sub']) ?></div>
      </div>
      <div class="body-text" style="font-style:italic; color:#5a2a3a; margin-top:12px"><?= $raw($c['secret']['quote']) ?></div>
    </div>
    <button class="btn" id="revealBtn" onclick="revealSecret()">Reveal the Secret 🔓</button>
    <div id="secretNext" style="display:none">
      <button class="btn" onclick="goTo('page-proposal')" style="margin-top:12px">One More Thing… 💍</button>
    </div>
    <br><button class="btn-ghost" onclick="goTo('page-puzzle')">← Back</button>
  </div>
</div>

<!-- PAGE 9 — PROPOSAL -->
<div class="page" id="page-proposal">
  <div class="card card-small" style="background:rgba(255,255,255,0.05); box-shadow:none; color:white; border:1px solid rgba(255,150,170,0.2)">
    <div style="font-size:3.5rem; margin-bottom:12px; animation:pulse-ring 2s infinite; display:inline-block; border-radius:50%">💍</div>
    <div class="label" style="color:#ff8fa3"><?= $e($c['proposal']['label']) ?></div>
    <div class="script" style="color:#ffb3c1; font-size:2rem"><?= $e($c['proposal']['script']) ?></div>
    <div style="color:rgba(255,200,210,0.75); font-size:0.95rem; margin:16px 0; line-height:1.9; font-style:italic"><?= $raw($c['proposal']['body']) ?></div>
    <div class="proposal-btns">
      <button class="yes-btn" onclick="yesAnswer()"><?= $e($c['proposal']['yes']) ?></button>
      <button class="yes-btn" style="background:linear-gradient(135deg,#c4184e,#e8405a)" onclick="yesAnswer()"><?= $e($c['proposal']['always']) ?></button>
    </div>
    <br><button class="btn-ghost" style="color:#a07080" onclick="goTo('page-secret')">← Back</button>
  </div>
</div>

<!-- PAGE 10 — ENDING -->
<div class="page" id="page-ending">
  <div class="card" style="text-align:center">
    <div style="font-size:3rem; margin-bottom:8px">🎂✨❤️</div>
    <div class="big-title"><?= $raw($c['ending']['title']) ?></div>
    <div class="divider" style="border-color:rgba(255,150,170,0.3)">🌹</div>
    <div class="body-text" style="font-style:italic; line-height:2.1"><?= $raw($c['ending']['body']) ?></div>
    <div style="margin-top:20px; color:rgba(255,200,210,0.5); font-size:0.8rem"><?= $e($c['ending']['signature']) ?></div>

    <?php if (!empty($c['reply']['enabled'])): ?>
    <!-- REPLY BOX -->
    <div id="cbReplyBox" style="margin-top:28px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,150,170,0.25); border-radius:18px; padding:20px; text-align:left;">
      <div style="font-family:'Satisfy',cursive; font-size:1.4rem; color:#ffb3c1; text-align:center;"><?= $e($c['reply']['heading']) ?></div>
      <div style="color:rgba(255,200,210,0.6); font-size:0.82rem; text-align:center; margin-top:4px; font-style:italic;"><?= $e($c['reply']['subtitle']) ?></div>
      <input id="cbReplyName" type="text" placeholder="Your name (optional)" style="width:100%; margin-top:14px; padding:11px 14px; border-radius:12px; border:1px solid rgba(255,150,170,0.25); background:rgba(255,255,255,0.9); color:#3a1a22; font-size:0.9rem; outline:none;">
      <textarea id="cbReplyMsg" rows="3" placeholder="<?= $e($c['reply']['placeholder']) ?>" style="width:100%; margin-top:10px; padding:11px 14px; border-radius:12px; border:1px solid rgba(255,150,170,0.25); background:rgba(255,255,255,0.9); color:#3a1a22; font-size:0.9rem; outline:none; resize:vertical; font-family:'DM Sans',sans-serif;"></textarea>
      <button id="cbReplyBtn" onclick="sendReply()" style="width:100%; margin-top:12px; background:linear-gradient(135deg,#e8405a,#c4184e); color:white; border:none; border-radius:50px; padding:13px; font-size:0.95rem; font-weight:600; cursor:pointer; box-shadow:0 8px 20px rgba(232,64,90,0.4);"><?= $e($c['reply']['button']) ?></button>
      <div id="cbReplyStatus" style="text-align:center; font-size:0.82rem; margin-top:10px; min-height:18px; color:#ffb3c1;"></div>
    </div>
    <?php endif; ?>

    <button onclick="goTo('page-landing')" style="display:inline-block; margin-top:24px; background:rgba(255,255,255,0.15); color:white; border:2px solid rgba(255,200,210,0.4); border-radius:50px; padding:14px 36px; font-size:1rem; font-family:'DM Sans',sans-serif; font-weight:500; cursor:pointer; backdrop-filter:blur(4px);">Replay from Start 🔄</button>
  </div>
</div>

<!-- MUSIC BAR -->
<div class="music-bar" id="musicBar">
  <button class="music-btn" id="musicPlayBtn" onclick="toggleMusic()" title="Play/Pause">▶</button>
  <div class="music-info">
    <div class="music-title" id="musicTitle"><?= $e($c['music']['title']) ?></div>
    <div class="music-artist">🎵 Background Music</div>
  </div>
  <div class="music-progress" onclick="seekMusic(event)"><div class="music-fill" id="musicFill"></div></div>
  <button class="music-btn" onclick="switchTrack()" title="Restart Track">⏭</button>
</div>
<?php if ($music_audio): ?><audio id="bgAudio" src="<?= $e($music_audio) ?>" loop preload="metadata"></audio><?php endif; ?>

<div class="progress-dots" id="progressDots"></div>

<script>
const CB_PAGE_ID = <?= (int)($page['id'] ?? 0) ?>;
const CB_BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
const CB_REPLY_SUCCESS = <?= json_encode($c['reply']['success'] ?? 'Sent! ❤️', JSON_UNESCAPED_UNICODE) ?>;
const CB_ALBUM = <?= json_encode(array_values($album_images), JSON_UNESCAPED_SLASHES) ?>;
const pages = <?= json_encode($slides) ?>;
let currentPage = pages[0];

function next() { const i = pages.indexOf(currentPage); if (i > -1 && i < pages.length - 1) goTo(pages[i + 1]); }
function prev() { const i = pages.indexOf(currentPage); if (i > 0) goTo(pages[i - 1]); }

const vnAudio = document.getElementById('vnAudio');
const letterAudio = document.getElementById('letterAudio');
const bgAudio = document.getElementById('bgAudio');

function goTo(id) {
  if (currentPage === 'page-letter' && letterAudio) { letterAudio.pause(); letterAudio.currentTime = 0; const b=document.getElementById('letterPlayBtn'); if(b)b.textContent='▶'; }
  document.getElementById(currentPage).classList.remove('active');
  document.getElementById(id).classList.add('active');
  currentPage = id;
  updateDots();
  window.scrollTo(0, 0);
  if (id === 'page-letter' && letterAudio) { letterAudio.play().catch(() => {}); const b=document.getElementById('letterPlayBtn'); if(b)b.textContent='⏸'; }
  if (id === 'page-ending') celebrate();
}

const dotsEl = document.getElementById('progressDots');
pages.forEach((p, i) => { const d = document.createElement('div'); d.className = 'dot' + (i === 0 ? ' active' : ''); d.id = 'dot-' + i; dotsEl.appendChild(d); });
function updateDots() { const idx = pages.indexOf(currentPage); pages.forEach((_, i) => { document.getElementById('dot-' + i).classList.toggle('active', i === idx); }); }

const petalEl = document.getElementById('petalBg');
const petalEmojis = ['🌸','🌺','❤️','💖','🌹','💗','✨','💕'];
for (let i = 0; i < 20; i++) { const p = document.createElement('div'); p.className = 'petal'; p.textContent = petalEmojis[Math.floor(Math.random() * petalEmojis.length)]; p.style.left = Math.random() * 100 + '%'; p.style.animationDuration = (8 + Math.random() * 10) + 's'; p.style.animationDelay = (Math.random() * 10) + 's'; p.style.fontSize = (0.8 + Math.random() * 1) + 'rem'; petalEl.appendChild(p); }

function showChat(idx) { document.querySelectorAll('.chat-scene').forEach((el, i) => el.classList.toggle('active', i === idx)); document.querySelectorAll('.chat-tab').forEach((el, i) => el.classList.toggle('active', i === idx)); }

// ── VOICE NOTE ──
const vnPlayBtn = document.getElementById('vnPlayBtn');
const vnDuration = document.getElementById('vnDuration');
const waveformEl = document.getElementById('waveform');
const vnTimeEl = document.getElementById('vnTime');
if (vnTimeEl) vnTimeEl.textContent = vnAudio ? 'Voice Message • tap play ▶' : 'No voice note added';
const barCount = 40;
for (let i = 0; i < barCount; i++) { const b = document.createElement('div'); b.className = 'wbar'; b.style.height = (20 + Math.random() * 80) + '%'; waveformEl.appendChild(b); }
function toggleVN() {
  if (!vnAudio) { if (vnTimeEl) vnTimeEl.textContent = '⚠️ No voice note added'; return; }
  if (vnAudio.paused) { vnAudio.play().then(() => { vnPlayBtn.textContent = '⏸'; }).catch(() => { vnPlayBtn.textContent = '▶'; if (vnTimeEl) vnTimeEl.textContent = '⚠️ Could not play audio'; }); }
  else { vnAudio.pause(); vnPlayBtn.textContent = '▶'; }
}
function seekVN(e) { if (!vnAudio || !vnAudio.duration) return; const rect = waveformEl.getBoundingClientRect(); vnAudio.currentTime = ((e.clientX - rect.left) / rect.width) * vnAudio.duration; }
if (vnAudio) {
  vnAudio.addEventListener('timeupdate', () => { const pct = vnAudio.duration ? vnAudio.currentTime / vnAudio.duration : 0; waveformEl.querySelectorAll('.wbar').forEach((b, i) => b.classList.toggle('played', i / barCount < pct)); const s = Math.floor(vnAudio.currentTime); vnDuration.textContent = Math.floor(s/60) + ':' + String(s%60).padStart(2,'0'); });
  vnAudio.addEventListener('ended', () => { vnPlayBtn.textContent = '▶'; waveformEl.querySelectorAll('.wbar').forEach(b => b.classList.remove('played')); vnDuration.textContent = '0:00'; });
}

// ── LOVE LETTER AUDIO ──
if (letterAudio) { letterAudio.volume = 0.6; }
function toggleLetter() { if (!letterAudio) return; const b = document.getElementById('letterPlayBtn'); if (letterAudio.paused) { letterAudio.play().catch(()=>{}); b.textContent = '⏸'; } else { letterAudio.pause(); b.textContent = '▶'; } }
function seekLetter(e) { if (!letterAudio || !letterAudio.duration) return; const r = e.currentTarget.getBoundingClientRect(); letterAudio.currentTime = ((e.clientX - r.left) / r.width) * letterAudio.duration; }
if (letterAudio) {
  letterAudio.addEventListener('timeupdate', () => { const f = document.getElementById('letterFill'); const d = document.getElementById('letterDur'); if (letterAudio.duration && f) { f.style.width = (letterAudio.currentTime / letterAudio.duration * 100) + '%'; const s = Math.floor(letterAudio.currentTime); d.textContent = Math.floor(s/60) + ':' + String(s%60).padStart(2,'0'); } });
  letterAudio.addEventListener('ended', () => { const b = document.getElementById('letterPlayBtn'); if (b) b.textContent = '▶'; });
}

// ── BACKGROUND MUSIC ──
let musicPlaying = false;
const musicPlayBtn = document.getElementById('musicPlayBtn');
const musicFill = document.getElementById('musicFill');
function toggleMusic() { if (!bgAudio) { document.getElementById('musicTitle').textContent = 'No music added'; return; } if (bgAudio.paused) { bgAudio.play().then(()=>{ musicPlayBtn.textContent = '⏸'; musicPlaying = true; }).catch(()=>{}); } else { bgAudio.pause(); musicPlayBtn.textContent = '▶'; musicPlaying = false; } }
function switchTrack() { if (bgAudio) { bgAudio.currentTime = 0; if (!bgAudio.paused) bgAudio.play().catch(()=>{}); } }
function seekMusic(e) { if (!bgAudio || !bgAudio.duration) return; const rect = e.currentTarget.getBoundingClientRect(); bgAudio.currentTime = ((e.clientX - rect.left) / rect.width) * bgAudio.duration; }
if (bgAudio) {
  bgAudio.addEventListener('timeupdate', () => { if (!bgAudio.duration) return; musicFill.style.width = (bgAudio.currentTime / bgAudio.duration * 100) + '%'; });
  bgAudio.addEventListener('ended', () => { musicPlayBtn.textContent = '▶'; musicPlaying = false; });
} else {
  // No music uploaded — make it clear instead of a misleading title
  document.getElementById('musicTitle').textContent = 'No background music';
  musicPlayBtn.style.opacity = '0.4';
}

// ── AUDIO DUCKING: voice note / letter / unmuted video pauses the music, then resumes ──
let bgDucked = false;
function duckBg()  { if (bgAudio && !bgAudio.paused) { bgDucked = true; bgAudio.pause(); musicPlayBtn.textContent = '▶'; } }
function unduckBg(){ if (bgAudio && bgDucked) { bgDucked = false; bgAudio.play().then(() => { musicPlayBtn.textContent = '⏸'; musicPlaying = true; }).catch(() => {}); } }

[vnAudio, letterAudio].forEach(function (a) {
  if (!a) return;
  a.addEventListener('play',  duckBg);
  a.addEventListener('pause', unduckBg);
  a.addEventListener('ended', unduckBg);
});

// Video: muted by default (music keeps playing). Unmute → pause music; pause/end/re-mute → resume.
const cbVideo = document.querySelector('#page-video video');
if (cbVideo) {
  cbVideo.muted = true;
  cbVideo.addEventListener('volumechange', function () { if (cbVideo.muted) unduckBg(); else duckBg(); });
  cbVideo.addEventListener('play',  function () { if (!cbVideo.muted) duckBg(); });
  cbVideo.addEventListener('pause', function () { if (!cbVideo.muted) unduckBg(); });
  cbVideo.addEventListener('ended', unduckBg);
}

// ── PUZZLE ──
const validAnswers = <?= json_encode(array_map('strtolower', $c['puzzle']['answers'] ?? []), JSON_UNESCAPED_UNICODE) ?>;
const puzzleSuccess = <?= json_encode($c['puzzle']['success'] ?? '✅ You remembered!', JSON_UNESCAPED_UNICODE) ?>;
function checkPuzzle() {
  const val = document.getElementById('puzzleInput').value.trim().toLowerCase();
  const fb = document.getElementById('puzzleFeedback');
  if (validAnswers.some(a => (val.length && val.includes(a.replace(' 2026',''))) || val === a)) {
    fb.style.color = '#e8405a'; fb.innerHTML = puzzleSuccess; setTimeout(() => goTo('page-secret'), 1500);
  } else if (val.length > 0) {
    fb.style.color = '#b08090'; fb.innerHTML = "💝 Even when you’re wrong… you’ll always be right in my heart ❤️🔐";
  }
}
function toggleHints() { const h = document.getElementById('hintsBox'); if (h) h.style.display = h.style.display === 'none' ? 'block' : 'none'; }

// ── SECRET / PROPOSAL / HEARTS ──
function revealSecret() { document.getElementById('secretReveal').style.display = 'block'; document.getElementById('revealBtn').style.display = 'none'; document.getElementById('secretNext').style.display = 'block'; spawnHearts(5); }
function yesAnswer() { spawnHearts(18); setTimeout(() => goTo('page-ending'), 1000); }
function spawnHearts(n) { for (let i = 0; i < n; i++) { setTimeout(() => { const h = document.createElement('div'); h.className = 'heart-particle'; h.textContent = ['❤️','💖','💗','💕','🌹'][Math.floor(Math.random()*5)]; h.style.left = (10 + Math.random() * 80) + 'vw'; h.style.top = (30 + Math.random() * 40) + 'vh'; document.body.appendChild(h); setTimeout(() => h.remove(), 1600); }, i * 80); } }

// ── PHOTO ALBUM LIGHTBOX ──
let lbIndex = 0;
function openLightbox(i) { if (!CB_ALBUM.length) return; lbIndex = i; document.getElementById('lbImg').src = CB_ALBUM[i]; document.getElementById('lightbox').classList.add('open'); }
function closeLightbox() { document.getElementById('lightbox').classList.remove('open'); }
function lbStep(d) { if (!CB_ALBUM.length) return; lbIndex = (lbIndex + d + CB_ALBUM.length) % CB_ALBUM.length; document.getElementById('lbImg').src = CB_ALBUM[lbIndex]; }
document.getElementById('lightbox').addEventListener('click', function(e){ if (e.target === this) closeLightbox(); });

// ── BALLOONS ──
function initBalloons() {
  const layer = document.getElementById('balloonLayer');
  if (!layer) return;
  const colors = ['🎈','🎈','🎈','🩷','💗','🎀'];
  const N = window.innerWidth < 768 ? 8 : 14;
  for (let i = 0; i < N; i++) {
    const b = document.createElement('div');
    b.className = 'balloon';
    b.textContent = colors[Math.floor(Math.random() * colors.length)];
    b.style.left = Math.random() * 96 + '%';
    b.style.fontSize = (2 + Math.random() * 1.6) + 'rem';
    b.style.animationDuration = (10 + Math.random() * 10) + 's';
    b.style.animationDelay = (Math.random() * -18) + 's';
    layer.appendChild(b);
  }
}
initBalloons();

// ── CONFETTI ──
function confettiBurst(n) {
  const colors = ['#e8405a','#ff8fa3','#ffd166','#c4184e','#f7c5cf','#7ed957'];
  for (let i = 0; i < n; i++) {
    const p = document.createElement('div');
    p.className = 'confetti-piece';
    p.style.left = Math.random() * 100 + 'vw';
    p.style.background = colors[Math.floor(Math.random() * colors.length)];
    p.style.animationDuration = (2.2 + Math.random() * 1.8) + 's';
    p.style.animationDelay = (Math.random() * 0.6) + 's';
    p.style.width = (7 + Math.random() * 7) + 'px';
    p.style.height = (10 + Math.random() * 8) + 'px';
    document.body.appendChild(p);
    setTimeout(() => p.remove(), 4500);
  }
}
let celebrated = false;
function celebrate() {
  confettiBurst(window.innerWidth < 768 ? 60 : 120);
  spawnHearts(14);
  if (!celebrated) { celebrated = true; setTimeout(() => confettiBurst(60), 900); }
}

// ── CAKE CUTTING ──
let cakeCut = false;
function cutCake() {
  if (cakeCut) return; cakeCut = true;
  const w = document.getElementById('cakeWrap');
  if (w) w.classList.add('cut');
  setTimeout(function () {
    confettiBurst(window.innerWidth < 768 ? 55 : 100);
    spawnHearts(8);
    const m = document.getElementById('cakeMsg'); if (m) m.style.display = 'block';
    const b = document.getElementById('cakeCutBtn'); if (b) b.style.display = 'none';
    const n = document.getElementById('cakeNextWrap'); if (n) n.style.display = 'block';
  }, 700);
}

// ── REPLY ──
function sendReply() {
  const btn = document.getElementById('cbReplyBtn');
  const status = document.getElementById('cbReplyStatus');
  const msg = (document.getElementById('cbReplyMsg').value || '').trim();
  const name = (document.getElementById('cbReplyName').value || '').trim() || 'Anonymous';
  if (!CB_PAGE_ID) { status.style.color = '#ffd1da'; status.textContent = 'Replies work on the published page — this is just a preview 🙂'; return; }
  if (!msg) { status.style.color = '#ffd1da'; status.textContent = 'Please write a message first 🙂'; return; }
  btn.disabled = true; btn.style.opacity = '0.6'; status.style.color = '#ffb3c1'; status.textContent = 'Sending…';
  fetch(CB_BASE + 'api.php?action=submit_reply', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ page_id: CB_PAGE_ID, reply_type: 'text', visitor_name: name, message: msg })
  })
  .then(r => r.json())
  .then(d => {
    if (d && d.success) {
      document.getElementById('cbReplyBox').innerHTML = '<div style="text-align:center; padding:16px; color:#ffb3c1; font-family:\'Satisfy\',cursive; font-size:1.4rem;">' + CB_REPLY_SUCCESS + '</div>';
      spawnHearts(10);
    } else {
      btn.disabled = false; btn.style.opacity = '1';
      status.style.color = '#ff9db0'; status.textContent = (d && d.error) ? d.error : 'Could not send. Try again.';
    }
  })
  .catch(() => { btn.disabled = false; btn.style.opacity = '1'; status.style.color = '#ff9db0'; status.textContent = 'Network error. Try again.'; });
}

// Auto-start music on first interaction
function tryAutoplay() { if (bgAudio) { bgAudio.play().then(() => { musicPlayBtn.textContent = '⏸'; musicPlaying = true; }).catch(() => {}); } document.removeEventListener('click', tryAutoplay); document.removeEventListener('touchstart', tryAutoplay); }
document.addEventListener('click', tryAutoplay);
document.addEventListener('touchstart', tryAutoplay);

// ── HIDE LOADING SCREEN once photos/media are ready (max 7s) ──
(function () {
  const loader = document.getElementById('cbLoader');
  if (!loader) return;
  let hidden = false;
  function hideLoader() {
    if (hidden) return; hidden = true;
    loader.classList.add('hide');
    setTimeout(function () { if (loader.parentNode) loader.parentNode.removeChild(loader); }, 700);
  }
  if (document.readyState === 'complete') { setTimeout(hideLoader, 400); }
  window.addEventListener('load', function () { setTimeout(hideLoader, 400); });
  setTimeout(hideLoader, 7000); // safety fallback
})();
</script>
<script src="assets/js/aac-playback-fix.js"></script>
<script src="<?= $base ?>assets/js/aac-play.js"></script>
</body>
</html><?php
}

} // end function_exists guard
