<?php
// =========================================================================
// invite-edit.php — Invitation Studio editor v2 (cinematic engine).
// Template.json can now declare: themes, languages (en/hi/hinglish/bi),
// wedding events with ON/OFF + auto timeline, and cinematic canvas scenes
// (palace, mandap, couple illustration, diyas, fireworks, petals, doors…).
// Rendering + video export happen fully in the browser; the server only
// charges credits.
// =========================================================================
require_once 'includes/functions.php';
require_once 'includes/invitations.php';

$tpl = inv_get($_GET['id'] ?? ($_POST['id'] ?? ''), !(is_logged_in() && is_admin()));
if (!$tpl) redirect('invitations.php');
$cost = max(0, (int)($tpl['credit_cost'] ?? 1));

// charge endpoint (AJAX) — deduct credits once, right before export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'charge') {
    header('Content-Type: application/json');
    if (!is_logged_in()) { echo json_encode(['success' => false, 'error' => 'login', 'msg' => 'Please log in to download.']); exit; }
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'msg' => 'Session expired — reload the page.']); exit; }
    $uid = $_SESSION['user_id'];
    if ($cost > 0) {
        if (get_user_credits($uid) < $cost) { echo json_encode(['success' => false, 'error' => 'credits', 'msg' => "This template needs {$cost} credit(s).", 'need' => $cost]); exit; }
        $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?")->execute([$cost, $uid, $cost]);
    }
    $stats = json_decode(get_setting('invitation_stats', '{}'), true) ?: [];
    $stats[$tpl['id']] = ['uses' => (int)($stats[$tpl['id']]['uses'] ?? 0) + 1, 'last' => date('Y-m-d H:i')];
    set_setting('invitation_stats', json_encode($stats));
    echo json_encode(['success' => true]);
    exit;
}

$user = is_logged_in() ? get_user_profile($_SESSION['user_id']) : null;
$credits = $user ? get_user_credits($user['id']) : 0;
$csrf = generate_csrf_token();
$music_url = (!empty($tpl['music']) && file_exists($tpl['music'])) ? $tpl['music'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($tpl['name']) ?> — Invitation Studio</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;600;800&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Tiro+Devanagari+Hindi&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#ffffff 0%,#fdf2f8 50%,#f1f5f9 100%); color:#0f172a; min-height:100vh; }
  h1,h2,.heading { font-family:'Outfit',sans-serif; }
  .fld { width:100%; background:#fff; border:1px solid rgba(236,72,153,0.25); border-radius:12px; padding:10px 13px; color:#0f172a; font-size:.88rem; outline:none; }
  .fld:focus { border-color:#db2777; }
  .lbl { display:block; font-size:.68rem; text-transform:uppercase; letter-spacing:1px; color:#db2777; margin-bottom:4px; font-weight:700; }
  #stage { width:100%; max-width:340px; aspect-ratio:9/16; border-radius:22px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.3); margin:0 auto; background:#000; }
  #stage canvas { width:100%; height:100%; display:block; }
  .theme-chip { border:2px solid transparent; border-radius:12px; padding:6px 12px; font-size:.72rem; font-weight:700; cursor:pointer; color:#fff; }
  .theme-chip.on { border-color:#db2777; box-shadow:0 4px 14px rgba(219,39,119,.3); }
  .evt { border:1px solid rgba(236,72,153,.18); border-radius:14px; padding:12px; }
  .evt.off .evt-body { display:none; }
</style>
</head>
<body>

<header class="sticky top-0 z-50" style="background:rgba(255,255,255,0.9); backdrop-filter:blur(16px); border-bottom:1px solid rgba(236,72,153,0.12);">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="invitations.php" class="text-sm font-medium text-slate-600 hover:text-pink-600">← Templates</a>
    <span class="heading font-extrabold text-slate-900">🎬 <?= h($tpl['name']) ?></span>
    <?php if ($user): ?><span class="text-xs bg-pink-50 text-pink-600 border border-pink-200 px-2.5 py-1 rounded-full font-bold">✨ <?= (int)$credits ?></span><?php else: ?><a href="login.php" class="text-sm text-pink-600 font-bold">Login</a><?php endif; ?>
  </div>
</header>

<main class="max-w-6xl mx-auto px-4 py-8 grid lg:grid-cols-2 gap-8 items-start">

  <!-- PREVIEW -->
  <div class="lg:sticky lg:top-24">
    <div id="stage"><canvas id="cv"></canvas></div>
    <div class="flex items-center justify-center gap-3 mt-4 flex-wrap">
      <button id="btnPlay" onclick="togglePreview()" class="font-bold text-white text-sm px-8 py-3 rounded-full" style="background:linear-gradient(135deg,#db2777,#e11d48);">▶ Play Preview</button>
      <button id="btnDl" onclick="startExport()" class="font-bold text-white text-sm px-8 py-3 rounded-full" style="background:linear-gradient(135deg,#059669,#10b981);">⬇ Download Video (<?= $cost ?> credit<?= $cost === 1 ? '' : 's' ?>)</button>
    </div>
    <p id="exStatus" class="text-center text-xs text-slate-500 mt-3 min-h-[18px]"></p>
    <p class="text-center text-[10px] text-slate-400 mt-1">Preview is free — credits are used only on download. <?= h($tpl['aspect'] ?? '9:16') ?> · 1080p video</p>
  </div>

  <!-- EDITOR (built by JS from template.json) -->
  <div class="space-y-4" id="editorCol">
    <div class="rounded-2xl bg-white border border-pink-100 p-6" id="secTop" style="box-shadow:0 10px 30px rgba(219,39,119,0.06);"></div>
    <div class="rounded-2xl bg-white border border-pink-100 p-6" id="secFields"></div>
    <div class="rounded-2xl bg-white border border-pink-100 p-6" id="secEvents" style="display:none;"></div>
    <div class="rounded-2xl bg-white border border-pink-100 p-6" id="secMedia"></div>
  </div>
</main>

<audio id="musEl" src="<?= h($music_url) ?>" preload="auto" loop crossorigin="anonymous"></audio>

<script src="assets/js/audio-fix.js"></script>
<script>
const TPL = <?= json_encode($tpl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const NEED_LOGIN = <?= $user ? 'false' : 'true' ?>;
const CSRF = <?= json_encode($csrf) ?>;
const SITE = <?= json_encode(SITE_NAME) ?>;

// ═══════════════════════ state ═══════════════════════
const W = 1080, H = 1920;
const cv = document.getElementById('cv'); cv.width = W; cv.height = H;
const ctx = cv.getContext('2d');
let LANG = (TPL.languages && TPL.languages[0]) || 'en';
let THEME = TPL.themes ? Object.keys(TPL.themes)[0] : null;
const photos = [];
let vals = {};      // field values
let evtOn = {};     // event key → bool

function C() { return THEME ? TPL.themes[THEME].colors : (TPL.colors || {}); }
function FONT() { return (LANG === 'hi') ? 'Tiro Devanagari Hindi' : (TPL.font || 'Cormorant Garamond'); }
function L(x) { // resolve a translatable string: plain, or {en,hi,hng}
  if (x === null || x === undefined) return '';
  if (typeof x !== 'object') return String(x);
  if (LANG === 'bi') return (x.hi || x.en || '') + (x.en && x.hi ? '\n' : '') + (x.hi ? (x.en || '') : '');
  return x[LANG] || x.hng || x.en || Object.values(x)[0] || '';
}
function tokens(s) {
  return String(s || '').replace(/\[(\w+)\]/g, (m, k) => vals[k] !== undefined && vals[k] !== '' ? vals[k] : m);
}
function col(name) { const c = C(); return c[name] || name || c.text || '#fff'; }
function ease(t) { return t < 0 ? 0 : t > 1 ? 1 : 1 - Math.pow(1 - t, 3); }

// ═══════════════════════ editor build ═══════════════════════
function buildEditor() {
  const top = document.getElementById('secTop');
  let html = '<h2 class="heading font-extrabold text-lg mb-4">🎨 Style</h2>';
  if (TPL.languages && TPL.languages.length > 1) {
    const names = { en: 'English', hi: 'हिन्दी', hng: 'Hinglish', bi: 'Hindi + English' };
    html += '<label class="lbl">Language</label><div class="flex gap-2 flex-wrap mb-4">' + TPL.languages.map(l =>
      '<button type="button" onclick="setLang(\'' + l + '\')" data-lang="' + l + '" class="langBtn text-xs font-bold px-4 py-2 rounded-full border ' + (l === LANG ? 'bg-pink-600 text-white border-pink-600' : 'border-pink-200 text-slate-600') + '">' + (names[l] || l) + '</button>').join('') + '</div>';
  }
  if (TPL.themes) {
    html += '<label class="lbl">Theme</label><div class="flex gap-2 flex-wrap">' + Object.keys(TPL.themes).map(k => {
      const th = TPL.themes[k];
      return '<button type="button" onclick="setTheme(\'' + k + '\')" data-th="' + k + '" class="theme-chip ' + (k === THEME ? 'on' : '') + '" style="background:linear-gradient(135deg,' + th.colors.bg1 + ',' + th.colors.bg2 + ');color:' + th.colors.accent2 + ';">' + th.name + '</button>';
    }).join('') + '</div>';
  }
  top.innerHTML = html;
  if (html === '<h2 class="heading font-extrabold text-lg mb-4">🎨 Style</h2>') top.style.display = 'none';

  // fields
  const fs = document.getElementById('secFields');
  let fh = '<h2 class="heading font-extrabold text-lg mb-4">✏️ Your Details</h2><div class="grid sm:grid-cols-2 gap-4">';
  (TPL.fields || []).forEach(f => {
    const def = L(f.default);
    const long = def.length > 40 || f.type === 'textarea';
    fh += '<div class="' + (long ? 'sm:col-span-2' : '') + '"><label class="lbl">' + L(f.label) + '</label>';
    if (f.type === 'color') fh += '<input type="color" class="inv-field w-full h-10 rounded-lg border border-pink-200 cursor-pointer" data-key="' + f.key + '" value="' + (f.default || '#e11d48') + '">';
    else if (long) fh += '<textarea class="fld inv-field" data-key="' + f.key + '" rows="2">' + def + '</textarea>';
    else fh += '<input class="fld inv-field" data-key="' + f.key + '" value="' + def.replace(/"/g, '&quot;') + '">';
    fh += '</div>';
  });
  fs.innerHTML = fh + '</div>';

  // events
  if (TPL.events && TPL.events.length) {
    const se = document.getElementById('secEvents');
    se.style.display = '';
    let eh = '<h2 class="heading font-extrabold text-lg mb-1">🗓️ Wedding Functions</h2><p class="text-xs text-slate-500 mb-4">Jitni chaho rasmein ON/OFF karo — video me har ON event ka scene + ek auto timeline banegi.</p><div class="space-y-3">';
    TPL.events.forEach(ev => {
      const on = ev.on !== false;
      evtOn[ev.key] = on;
      eh += '<div class="evt ' + (on ? '' : 'off') + '" id="evt_' + ev.key + '">'
        + '<label class="flex items-center gap-2 cursor-pointer font-bold text-sm text-slate-800"><input type="checkbox" ' + (on ? 'checked' : '') + ' onchange="toggleEvt(\'' + ev.key + '\',this.checked)" class="accent-pink-600 w-4 h-4"> ' + (ev.emoji || '💠') + ' ' + L(ev.label) + '</label>'
        + '<div class="evt-body grid sm:grid-cols-3 gap-2 mt-3">'
        + '<input class="fld inv-field" data-key="evt_' + ev.key + '_date" value="' + (ev.date || '') + '" placeholder="Date (e.g. 12 Feb)">'
        + '<input class="fld inv-field" data-key="evt_' + ev.key + '_time" value="' + (ev.time || '') + '" placeholder="Time">'
        + '<input class="fld inv-field" data-key="evt_' + ev.key + '_venue" value="' + (ev.venue || '') + '" placeholder="Venue">'
        + '</div></div>';
    });
    se.innerHTML = eh + '</div>';
  }

  // media
  const sm = document.getElementById('secMedia');
  let mh = '';
  if (TPL.photos) {
    mh += '<h2 class="heading font-extrabold text-lg mb-2">📸 ' + (L(TPL.photos.label) || 'Photos') + '</h2>'
      + '<p class="text-xs text-slate-500 mb-3">Photos stay on your device — they go straight into the video.</p>'
      + '<input type="file" id="photoIn" accept="image/*" multiple class="fld"><div id="photoPrev" class="flex gap-2 mt-3 flex-wrap"></div><div class="my-4 border-t border-pink-100"></div>';
  }
  mh += '<h2 class="heading font-extrabold text-lg mb-2">🎵 Music</h2>'
    + (document.getElementById('musEl').src ? '<p class="text-xs text-slate-500 mb-2">Template music included — or replace it:</p>' : '')
    + '<input type="file" id="musicIn" accept="audio/*" class="fld"><p class="text-[10px] text-slate-400 mt-2">Mixed into the video with fade in/out.</p>';
  sm.innerHTML = mh;

  document.querySelectorAll('.inv-field').forEach(f => {
    f.addEventListener('input', () => { readVals(); if (!playing) render(previewT); });
  });
  const pi = document.getElementById('photoIn');
  if (pi) pi.addEventListener('change', () => {
    const max = (TPL.photos && TPL.photos.max) || 3;
    photos.length = 0;
    const prev = document.getElementById('photoPrev'); prev.innerHTML = '';
    Array.from(pi.files).slice(0, max).forEach((f, i) => {
      const img = new Image();
      img.onload = () => { photos[i] = img; if (!playing) render(previewT); };
      img.src = URL.createObjectURL(f);
      const th = document.createElement('img');
      th.src = img.src; th.className = 'w-16 h-16 object-cover rounded-xl border border-pink-200';
      prev.appendChild(th);
    });
  });
  const mi = document.getElementById('musicIn');
  if (mi) mi.addEventListener('change', () => { if (mi.files[0]) { musEl.src = URL.createObjectURL(mi.files[0]); musEl.load(); } });
  readVals();
}
function readVals() { vals = {}; document.querySelectorAll('.inv-field').forEach(f => vals[f.dataset.key] = f.value); }
function setLang(l) {
  LANG = l;
  document.querySelectorAll('.langBtn').forEach(b => b.className = 'langBtn text-xs font-bold px-4 py-2 rounded-full border ' + (b.dataset.lang === l ? 'bg-pink-600 text-white border-pink-600' : 'border-pink-200 text-slate-600'));
  buildScenes(); if (!playing) render(previewT);
}
function setTheme(k) {
  THEME = k;
  document.querySelectorAll('.theme-chip').forEach(b => b.classList.toggle('on', b.dataset.th === k));
  if (!playing) render(previewT);
}
function toggleEvt(k, on) {
  evtOn[k] = on;
  document.getElementById('evt_' + k).classList.toggle('off', !on);
  buildScenes(); if (!playing) render(previewT);
}

// ═══════════════════════ scene expansion ═══════════════════════
let SCENES = [], TOTAL = 0;
function buildScenes() {
  SCENES = [];
  (TPL.scenes || []).forEach(sc => {
    if (sc.type === 'events' && TPL.events) {
      TPL.events.forEach(ev => {
        if (!evtOn[ev.key]) return;
        SCENES.push({ d: sc.d || 4.5, fx: sc.fx, els: [
          { type: 'text', text: (ev.emoji || '') + ' ' + L(ev.label), size: 62, y: 0.36, anim: 'zoom', delay: 0.3, color: 'accent' },
          { type: 'flourish', y: 0.44, delay: 0.8 },
          { type: 'text', text: '[evt_' + ev.key + '_date]', size: 48, y: 0.52, anim: 'up', delay: 1.0, skipEmpty: true },
          { type: 'text', text: '[evt_' + ev.key + '_time]', size: 38, y: 0.59, anim: 'up', delay: 1.5, skipEmpty: true, color: 'accent2' },
          { type: 'text', text: '[evt_' + ev.key + '_venue]', size: 40, y: 0.67, anim: 'fade', delay: 2.0, skipEmpty: true, italic: true, wrap: 0.85 },
        ]});
      });
      return;
    }
    if (sc.type === 'eventlist' && TPL.events) {
      // reference-card style: icon circle · name+time · date column (6 per screen)
      const rows = TPL.events.filter(ev => evtOn[ev.key]);
      for (let c = 0; c < rows.length; c += 6) {
        const chunk = rows.slice(c, c + 6);
        const els = [{ type: 'text', text: L(sc.title || { en: 'Wedding Events', hi: 'विवाह समारोह' }), size: 42, y: 0.155, anim: 'fade', delay: 0.3, caps: true, spacing: 8, color: 'accent' },
                     { type: 'flourish', y: 0.19, delay: 0.5 }];
        chunk.forEach((ev, i) => {
          els.push({ type: 'evtcard', ev: ev, y: 0.25 + i * 0.115, delay: 0.6 + i * 0.35 });
        });
        SCENES.push({ d: sc.d || (3 + chunk.length * 0.9), fx: sc.fx, els });
      }
      return;
    }
    if (sc.type === 'timeline' && TPL.events) {
      const rows = TPL.events.filter(ev => evtOn[ev.key]);
      if (!rows.length) return;
      const els = [{ type: 'text', text: L(sc.title || 'Wedding Timeline'), size: 52, y: 0.20, anim: 'fade', delay: 0.3, color: 'accent', caps: true, spacing: 6 }];
      const y0 = 0.30, step = Math.min(0.09, 0.55 / rows.length);
      rows.forEach((ev, i) => {
        els.push({ type: 'tlrow', label: (ev.emoji || '') + ' ' + L(ev.label), datekey: 'evt_' + ev.key + '_date', y: y0 + i * step, delay: 0.6 + i * 0.45 });
      });
      SCENES.push({ d: sc.d || Math.max(6, 2.5 + rows.length * 0.8), fx: sc.fx, els });
      return;
    }
    SCENES.push(sc);
  });
  TOTAL = SCENES.reduce((s, sc) => s + (sc.d || 5), 0);
}

// ═══════════════════════ cinematic drawing kit ═══════════════════════
const rnd = (() => { let s = 7; return () => (s = (s * 16807) % 2147483647) / 2147483647; })();
const parts = Array.from({ length: 70 }, () => ({ x: Math.random(), y: Math.random(), r: 1 + Math.random() * 3.2, s: .15 + Math.random() * .5, p: Math.random() * 7 }));
const petals = Array.from({ length: 26 }, () => ({ x: Math.random(), y: Math.random(), s: .3 + Math.random() * .7, p: Math.random() * 7 }));
const confs = Array.from({ length: 120 }, () => ({ x: Math.random(), y: Math.random(), r: 4 + Math.random() * 8, s: .25 + Math.random() * .6, p: Math.random() * 7, c: Math.floor(Math.random() * 5) }));
const confCols = ['#f43f5e', '#fbbf24', '#60a5fa', '#a78bfa', '#34d399'];

function fxParticles(t) {
  ctx.save();
  for (const p of parts) {
    const y = ((p.y - t * .014 * p.s) % 1 + 1) % 1;
    ctx.globalAlpha = .28 * (.45 + .55 * Math.sin(t * 2.2 + p.p));
    ctx.fillStyle = col('accent2');
    ctx.beginPath(); ctx.arc((p.x + Math.sin(t * .7 + p.p) * .012) * W, y * H, p.r * 1.9, 0, 7); ctx.fill();
  }
  ctx.restore();
}
function fxPetals(t) {
  ctx.save();
  for (const p of petals) {
    const y = ((p.y + t * .05 * p.s) % 1.08) - .04;
    const x = p.x + Math.sin(t * 1.1 + p.p) * .05;
    ctx.globalAlpha = .8;
    ctx.translate(x * W, y * H); ctx.rotate(t * 1.5 + p.p);
    ctx.fillStyle = '#fb7185';
    ctx.beginPath(); ctx.ellipse(0, 0, 16 * p.s + 8, 9 * p.s + 4, 0, 0, 7); ctx.fill();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
  }
  ctx.restore();
}
function fxDiyas(t) {
  ctx.save();
  for (let i = 0; i < 7; i++) {
    const x = (i + .5) / 7 * W, y = H * .94, fl = 1 + Math.sin(t * 7 + i * 2) * .18;
    ctx.fillStyle = '#7c2d12';
    ctx.beginPath(); ctx.ellipse(x, y, 40, 16, 0, 0, 7); ctx.fill();
    const g = ctx.createRadialGradient(x, y - 26, 2, x, y - 26, 30);
    g.addColorStop(0, '#fff7c0'); g.addColorStop(.5, '#fb923c'); g.addColorStop(1, 'rgba(251,146,60,0)');
    ctx.fillStyle = g;
    ctx.beginPath(); ctx.ellipse(x, y - 26, 12 * fl, 20 * fl, 0, 0, 7); ctx.fill();
  }
  ctx.restore();
}
function fxFireworks(t) {
  ctx.save();
  for (let b = 0; b < 3; b++) {
    const bt = (t * .8 + b * .37) % 1.2;
    if (bt > 1) continue;
    const cx = (0.2 + ((b * 379) % 100) / 160) * W, cy = (0.10 + ((b * 173) % 100) / 500) * H;
    const R = ease(bt) * 200, al = 1 - bt;
    ctx.globalAlpha = al;
    for (let i = 0; i < 14; i++) {
      const a = i / 14 * Math.PI * 2;
      ctx.fillStyle = confCols[(i + b) % 5];
      ctx.beginPath(); ctx.arc(cx + Math.cos(a) * R, cy + Math.sin(a) * R + bt * 40, 5, 0, 7); ctx.fill();
    }
  }
  ctx.restore();
}
function fxConfetti(t) {
  ctx.save();
  for (const c of confs) {
    const y = ((c.y + t * .12 * c.s) % 1.1) - .05;
    ctx.globalAlpha = .85; ctx.fillStyle = confCols[c.c];
    ctx.save(); ctx.translate(c.x * W + Math.sin(t * 2 + c.p) * 26, y * H); ctx.rotate(t * 3 + c.p);
    ctx.fillRect(-c.r / 2, -c.r, c.r, c.r * 1.6); ctx.restore();
  }
  ctx.restore();
}
// hanging golden lanterns with gentle sway + glow
function fxLanterns(t) {
  ctx.save();
  const spots = [[.10, .04, 130], [.90, .02, 170], [.16, .0, 250], [.86, .05, 300]];
  spots.forEach(([px, py, len], i) => {
    const sway = Math.sin(t * 1.1 + i * 1.9) * .05;
    const x0 = px * W, y0 = py * H;
    ctx.save(); ctx.translate(x0, y0); ctx.rotate(sway);
    ctx.strokeStyle = col('accent'); ctx.lineWidth = 3; ctx.globalAlpha = .8;
    ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(0, len); ctx.stroke();
    const g = ctx.createRadialGradient(0, len + 42, 4, 0, len + 42, 70);
    g.addColorStop(0, 'rgba(255,236,170,0.85)'); g.addColorStop(1, 'rgba(255,220,130,0)');
    ctx.fillStyle = g; ctx.beginPath(); ctx.arc(0, len + 42, 70, 0, 7); ctx.fill();
    ctx.fillStyle = col('accent'); ctx.globalAlpha = .95;
    ctx.beginPath(); ctx.roundRect(-22, len, 44, 66, 12); ctx.fill();
    ctx.beginPath(); ctx.roundRect(-13, len - 12, 26, 12, 4); ctx.fill();
    ctx.beginPath(); ctx.moveTo(0, len + 66); ctx.lineTo(0, len + 84); ctx.stroke();
    ctx.fillStyle = '#fff7d6'; ctx.globalAlpha = .9;
    ctx.beginPath(); ctx.roundRect(-11, len + 14, 22, 36, 6); ctx.fill();
    ctx.restore();
  });
  ctx.restore();
}
// watercolor flower clusters in the corners, gently breathing/swaying
function drawFlower(x, y, r, rot, c1, c2) {
  ctx.save(); ctx.translate(x, y); ctx.rotate(rot);
  ctx.fillStyle = c1;
  for (let i = 0; i < 6; i++) { ctx.rotate(Math.PI / 3); ctx.beginPath(); ctx.ellipse(r * .8, 0, r * .75, r * .45, 0, 0, 7); ctx.fill(); }
  ctx.fillStyle = c2; ctx.beginPath(); ctx.arc(0, 0, r * .42, 0, 7); ctx.fill();
  ctx.restore();
}
function fxFlorals(t) {
  ctx.save(); ctx.globalAlpha = .9;
  const clusters = [
    { cx: .06, cy: .045, s: 1 }, { cx: .95, cy: .07, s: .8 },
    { cx: .05, cy: .95, s: 1.1 }, { cx: .94, cy: .955, s: .9 },
  ];
  const pinks = ['#f9a8d4', '#f472b6', '#fbcfe8'];
  clusters.forEach((cl, ci) => {
    const sway = Math.sin(t * 1.3 + ci * 2.1) * .08;
    const breathe = 1 + Math.sin(t * 1.7 + ci) * .05;
    ctx.save(); ctx.translate(cl.cx * W, cl.cy * H); ctx.rotate(sway); ctx.scale(breathe * cl.s, breathe * cl.s);
    ctx.strokeStyle = '#86a878'; ctx.lineWidth = 6;
    for (let l = 0; l < 3; l++) {
      ctx.save(); ctx.rotate(l * 1.1 - 1.1 + sway);
      ctx.beginPath(); ctx.moveTo(0, 0); ctx.quadraticCurveTo(40, -40, 96, -56); ctx.stroke();
      ctx.fillStyle = '#a3c493';
      ctx.beginPath(); ctx.ellipse(58, -46, 22, 10, -.5, 0, 7); ctx.fill();
      ctx.restore();
    }
    drawFlower(0, 0, 40, sway, pinks[ci % 3], '#fda4af');
    drawFlower(74, -50, 28, -sway * 2, pinks[(ci + 1) % 3], '#fecdd3');
    drawFlower(-56, 60, 24, sway * 3, pinks[(ci + 2) % 3], '#fecdd3');
    ctx.restore();
  });
  ctx.restore();
}
// elegant double golden frame with corner curls
function fxFrame() {
  ctx.save(); ctx.strokeStyle = col('accent'); ctx.globalAlpha = .85;
  ctx.lineWidth = 4; ctx.strokeRect(36, 36, W - 72, H - 72);
  ctx.lineWidth = 1.6; ctx.strokeRect(54, 54, W - 108, H - 108);
  ctx.lineWidth = 3;
  const corner = (x, y, sx, sy) => { ctx.beginPath(); ctx.moveTo(x + 90 * sx, y); ctx.quadraticCurveTo(x, y, x, y + 90 * sy); ctx.stroke(); };
  corner(70, 70, 1, 1); corner(W - 70, 70, -1, 1); corner(70, H - 70, 1, -1); corner(W - 70, H - 70, -1, -1);
  ctx.restore();
}
const FX = { particles: fxParticles, petals: fxPetals, diyas: fxDiyas, fireworks: fxFireworks, confetti: fxConfetti, lanterns: fxLanterns, florals: fxFlorals, frame: fxFrame };

// palace skyline silhouette (domes + arches)
function drawPalace(a, t) {
  ctx.save(); ctx.globalAlpha = a * .9;
  const base = H * .86;
  ctx.fillStyle = 'rgba(0,0,0,0.55)';
  ctx.fillRect(0, base, W, H - base);
  const dome = (x, r) => {
    ctx.beginPath(); ctx.arc(x, base, r, Math.PI, 0); ctx.fill();
    ctx.fillRect(x - r, base, r * 2, 6);
    ctx.beginPath(); ctx.moveTo(x, base - r - 34); ctx.lineTo(x - 7, base - r); ctx.lineTo(x + 7, base - r); ctx.fill();
  };
  dome(W * .5, 120); dome(W * .22, 70); dome(W * .78, 70); dome(W * .06, 46); dome(W * .94, 46);
  // little glowing windows
  ctx.fillStyle = col('accent2');
  for (let i = 0; i < 9; i++) {
    const tw = .4 + .6 * Math.sin(t * 3 + i * 1.7);
    ctx.globalAlpha = a * .5 * tw;
    ctx.fillRect(W * (.08 + i * .105), base + 24, 14, 22);
  }
  ctx.restore();
}
// mandap: canopy + pillars + garlands
function drawMandap(a) {
  ctx.save(); ctx.globalAlpha = a;
  const y0 = H * .18, x0 = W * .12, x1 = W * .88;
  ctx.strokeStyle = col('accent'); ctx.lineWidth = 10;
  ctx.beginPath(); ctx.moveTo(x0, y0 + 90); ctx.quadraticCurveTo(W / 2, y0 - 70, x1, y0 + 90); ctx.stroke();
  ctx.lineWidth = 14;
  ctx.beginPath(); ctx.moveTo(x0, y0 + 90); ctx.lineTo(x0, H * .75); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(x1, y0 + 90); ctx.lineTo(x1, H * .75); ctx.stroke();
  // garland dots
  for (let i = 0; i <= 12; i++) {
    const gx = x0 + (x1 - x0) * i / 12;
    const gy = y0 + 90 + Math.sin(i / 12 * Math.PI) * 70;
    ctx.fillStyle = i % 2 ? '#fb923c' : '#fbbf24';
    ctx.beginPath(); ctx.arc(gx, gy, 12, 0, 7); ctx.fill();
  }
  ctx.restore();
}
// stylised bride & groom (lehenga + sherwani, customisable colours)
function drawCouple(a, t, meet) {
  const gap = meet !== undefined ? (1 - meet) * 240 : 0;
  const cy = H * .62, scale = 2.1;
  const bx = W / 2 - 150 - gap, gx = W / 2 + 150 + gap;
  const leh = vals.lehenga_color || '#dc2626';
  const sher = vals.sherwani_color || '#f5e6c8';
  const skin = '#eab486';
  ctx.save(); ctx.globalAlpha = a;
  // groom
  ctx.save(); ctx.translate(gx, cy); ctx.scale(scale, scale);
  ctx.fillStyle = sher;
  ctx.beginPath(); ctx.moveTo(-34, 130); ctx.lineTo(-26, 0); ctx.quadraticCurveTo(0, -14, 26, 0); ctx.lineTo(34, 130); ctx.closePath(); ctx.fill();
  ctx.fillStyle = skin; ctx.beginPath(); ctx.arc(0, -38, 24, 0, 7); ctx.fill();
  ctx.fillStyle = col('accent'); ctx.beginPath(); ctx.arc(0, -52, 25, Math.PI, 0); ctx.fill(); // safa
  ctx.beginPath(); ctx.moveTo(0, -77); ctx.lineTo(8, -96); ctx.lineTo(-8, -96); ctx.fill();     // kalgi
  ctx.strokeStyle = col('accent'); ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(0, -8); ctx.lineTo(0, 90); ctx.stroke();
  ctx.restore();
  // bride
  ctx.save(); ctx.translate(bx, cy); ctx.scale(scale, scale);
  ctx.fillStyle = leh;
  ctx.beginPath(); ctx.moveTo(-58, 130); ctx.quadraticCurveTo(-40, 20, -18, -4); ctx.quadraticCurveTo(0, -14, 18, -4); ctx.quadraticCurveTo(40, 20, 58, 130); ctx.closePath(); ctx.fill();
  ctx.fillStyle = skin; ctx.beginPath(); ctx.arc(0, -38, 22, 0, 7); ctx.fill();
  // dupatta
  ctx.fillStyle = leh; ctx.globalAlpha = a * .85;
  ctx.beginPath(); ctx.arc(0, -44, 30, Math.PI * 1.05, Math.PI * 1.95); ctx.quadraticCurveTo(34, -10, 28, 30); ctx.lineTo(18, 26); ctx.quadraticCurveTo(24, -8, 14, -30); ctx.closePath(); ctx.fill();
  ctx.globalAlpha = a;
  // gold border on lehenga
  ctx.strokeStyle = col('accent'); ctx.lineWidth = 5;
  ctx.beginPath(); ctx.moveTo(-58, 128); ctx.quadraticCurveTo(0, 112, 58, 128); ctx.stroke();
  ctx.restore();
  // heart glow between them when met
  if (meet !== undefined && meet > .9) {
    const pulse = 1 + Math.sin(t * 6) * .12;
    ctx.font = (70 * pulse) + 'px serif'; ctx.textAlign = 'center';
    ctx.shadowColor = '#f43f5e'; ctx.shadowBlur = 46;
    ctx.fillText('❤️', W / 2, cy - 210);
  }
  ctx.restore();
}
function drawFlourish(y, a) {
  ctx.save(); ctx.globalAlpha = a; ctx.strokeStyle = col('accent'); ctx.lineWidth = 3;
  const cx = W / 2, w = 320 * a;
  ctx.beginPath(); ctx.moveTo(cx - w, y * H); ctx.lineTo(cx + w, y * H); ctx.stroke();
  ctx.font = '52px serif'; ctx.textAlign = 'center'; ctx.fillStyle = col('accent');
  ctx.fillText('❦', cx, y * H + 18);
  ctx.restore();
}
function drawRing(y, a, t) {
  ctx.save(); ctx.globalAlpha = a; ctx.font = (90 + Math.sin(t * 2) * 6) + 'px serif'; ctx.textAlign = 'center';
  ctx.shadowColor = col('accent'); ctx.shadowBlur = 40;
  ctx.fillText('💍', W / 2 - 300, y * H); ctx.fillText('💍', W / 2 + 300, y * H);
  ctx.restore();
}
function wrapText(text, size, maxW, italic) {
  ctx.font = (italic ? 'italic ' : '') + size + 'px "' + FONT() + '", serif';
  const out = [];
  for (const para of text.split('\n')) {
    let line = '';
    for (const w2 of para.split(' ')) {
      if (ctx.measureText(line + ' ' + w2).width > maxW && line) { out.push(line); line = w2; }
      else line = line ? line + ' ' + w2 : w2;
    }
    out.push(line);
  }
  return out;
}

function drawEl(el, st, gt, sc) {
  const a = ease((st - (el.delay || 0)) / 1.1);
  if (a <= 0) return;
  switch (el.type) {
    case 'flourish': return drawFlourish(el.y, a);
    case 'ring': return drawRing(el.y, a, gt);
    case 'palace': return drawPalace(a, gt);
    case 'mandap': return drawMandap(a);
    case 'couple': {
      let meet;
      if (el.meet) meet = ease((st - (el.delay || 0)) / (el.meetDur || 3));
      return drawCouple(a, gt, meet);
    }
    case 'evtcard': {
      const ev = el.ev;
      const y = el.y * H + (1 - a) * 60;
      const cw = W * .86, ch = H * .098, x0 = (W - cw) / 2;
      ctx.save(); ctx.globalAlpha = a;
      // card
      ctx.fillStyle = 'rgba(255,255,255,0.92)';
      ctx.strokeStyle = col('accent'); ctx.lineWidth = 3;
      ctx.shadowColor = 'rgba(120,80,20,0.18)'; ctx.shadowBlur = 26;
      ctx.beginPath(); ctx.roundRect(x0, y, cw, ch, 34); ctx.fill();
      ctx.shadowBlur = 0; ctx.stroke();
      // icon circle
      ctx.fillStyle = '#fce7ef';
      ctx.beginPath(); ctx.arc(x0 + 92, y + ch / 2, 56, 0, 7); ctx.fill();
      ctx.strokeStyle = col('accent'); ctx.lineWidth = 2.5; ctx.stroke();
      ctx.font = '54px serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(ev.emoji || '💠', x0 + 92, y + ch / 2 + 4);
      // name + time
      ctx.textAlign = 'left';
      ctx.fillStyle = '#7f1d1d';
      ctx.font = '700 40px "' + FONT() + '", serif';
      ctx.fillText(L(ev.label).toUpperCase(), x0 + 175, y + ch * .36);
      ctx.fillStyle = '#6b7280'; ctx.font = '30px Inter, sans-serif';
      const tv = vals['evt_' + ev.key + '_time'] || '';
      if (tv) ctx.fillText('🕐 ' + tv + ' Onwards', x0 + 175, y + ch * .70);
      // divider + date column
      ctx.strokeStyle = 'rgba(180,130,50,0.4)'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(x0 + cw - 235, y + 22); ctx.lineTo(x0 + cw - 235, y + ch - 22); ctx.stroke();
      const dv = vals['evt_' + ev.key + '_date'] || '';
      const dm = dv.match(/^(\d+)\s*(.*)$/);
      ctx.textAlign = 'center'; ctx.fillStyle = col('accent');
      if (dm) {
        ctx.font = '700 56px "' + FONT() + '", serif';
        ctx.fillText(dm[1], x0 + cw - 118, y + ch * .38);
        ctx.font = '600 26px Inter, sans-serif';
        ctx.fillText(dm[2].toUpperCase(), x0 + cw - 118, y + ch * .72);
      } else {
        ctx.font = '600 34px "' + FONT() + '", serif';
        ctx.fillText(dv || '—', x0 + cw - 118, y + ch / 2);
      }
      ctx.restore();
      return;
    }
    case 'tlrow': {
      const y = el.y * H + (1 - a) * 40;
      ctx.save(); ctx.globalAlpha = a;
      ctx.font = '600 40px "' + FONT() + '", serif'; ctx.textBaseline = 'middle';
      ctx.textAlign = 'right'; ctx.fillStyle = col('accent'); ctx.fillText(tokens('[' + el.datekey + ']').replace(/\[.*\]/, '—'), W / 2 - 40, y);
      ctx.fillStyle = col('accent2'); ctx.beginPath(); ctx.arc(W / 2, y, 8, 0, 7); ctx.fill();
      ctx.textAlign = 'left'; ctx.fillStyle = col('text'); ctx.fillText(el.label, W / 2 + 40, y);
      ctx.restore();
      return;
    }
    case 'photo': {
      const img = photos[el.index || 0];
      if (!img) return;
      const hh = (el.h || .45) * H, ww = hh * .75;
      const zoom = el.anim === 'zoom' ? (.86 + .14 * a) : 1;
      ctx.save(); ctx.globalAlpha = a;
      ctx.translate(W / 2, el.y * H); ctx.scale(zoom, zoom);
      ctx.beginPath(); ctx.roundRect(-ww / 2, -hh / 2, ww, hh, 28); ctx.clip();
      const ir = img.width / img.height, fr = ww / hh;
      let dw, dh; if (ir > fr) { dh = hh; dw = hh * ir; } else { dw = ww; dh = ww / ir; }
      ctx.drawImage(img, -dw / 2, -dh / 2, dw, dh);
      ctx.restore();
      ctx.save(); ctx.globalAlpha = a; ctx.strokeStyle = col('accent'); ctx.lineWidth = 6;
      ctx.beginPath(); ctx.roundRect(W / 2 - ww / 2 * zoom, el.y * H - hh / 2 * zoom, ww * zoom, hh * zoom, 28); ctx.stroke();
      ctx.restore();
      return;
    }
    default: { // text
      let txt = tokens(L(el.text));
      if (el.skipEmpty && /\[\w+\]/.test(txt)) return;
      if (el.caps) txt = txt.toUpperCase();
      const size = (el.size || 40) * 1.9;
      let y = el.y * H;
      if (el.anim === 'up') y += (1 - a) * 70;
      const zoom = el.anim === 'zoom' ? (.7 + .3 * a) : 1;
      ctx.save(); ctx.globalAlpha = a;
      ctx.translate(W / 2, y); ctx.scale(zoom, zoom);
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillStyle = col(el.color || 'text');
      ctx.font = (el.italic ? 'italic ' : '') + size + 'px "' + FONT() + '", serif';
      if (el.spacing) { try { ctx.letterSpacing = el.spacing + 'px'; } catch (e) {} }
      ctx.shadowColor = C().shadow || 'rgba(0,0,0,0.55)'; ctx.shadowBlur = 24;
      if (el.anim === 'typein') {
        const lines = wrapText(txt, size, (el.wrap || .85) * W, el.italic);
        let shown = Math.floor(txt.length * ease((st - (el.delay || 0)) / 2.6));
        let ly = -((lines.length - 1) / 2) * size * 1.25;
        for (const ln of lines) { const take = Math.min(ln.length, Math.max(0, shown)); shown -= take; ctx.fillText(ln.slice(0, take), 0, ly); ly += size * 1.25; }
      } else if (el.wrap || txt.indexOf('\n') !== -1) {
        const lines = wrapText(txt, size, (el.wrap || .85) * W, el.italic);
        let ly = -((lines.length - 1) / 2) * size * 1.25;
        for (const ln of lines) { ctx.fillText(ln, 0, ly); ly += size * 1.25; }
      } else ctx.fillText(txt, 0, 0);
      ctx.restore();
    }
  }
}

function render(t) {
  const c = C();
  const g = ctx.createLinearGradient(0, 0, 0, H);
  g.addColorStop(0, c.bg1 || '#111'); g.addColorStop(.55, c.bg2 || '#221'); g.addColorStop(1, c.bg1 || '#111');
  ctx.globalAlpha = 1; ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
  const rg = ctx.createRadialGradient(W / 2, H * .42, 60, W / 2, H * .42, H * .75);
  if (c.light) { rg.addColorStop(0, 'rgba(255,246,225,0.35)'); rg.addColorStop(1, 'rgba(190,140,80,0.14)'); }
  else { rg.addColorStop(0, 'rgba(255,220,130,0.10)'); rg.addColorStop(1, 'rgba(0,0,0,0.28)'); }
  ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H);
  fxParticles(t);

  let acc = 0, idx = 0, st = 0;
  for (let i = 0; i < SCENES.length; i++) {
    const d = SCENES[i].d || 5;
    if (t < acc + d || i === SCENES.length - 1) { idx = i; st = t - acc; break; }
    acc += d;
  }
  const sc = SCENES[idx] || { els: [] };
  const d = sc.d || 5;
  let fade = 1;
  if (st < .6) fade = st / .6;
  if (st > d - .6 && idx < SCENES.length - 1) fade = Math.max(0, (d - st) / .6);

  // golden doors opening reveal
  ctx.save(); ctx.globalAlpha = fade;
  (sc.fx || []).forEach(f => { if (FX[f] && f !== 'confetti') FX[f](t); });
  for (const el of (sc.els || [])) drawEl(el, st, t, sc);
  ctx.restore();
  if ((sc.fx || []).indexOf('confetti') !== -1) fxConfetti(t);
  if (sc.doors) {
    const open = ease(st / 1.6);
    if (open < 1) {
      ctx.save();
      const dg = ctx.createLinearGradient(0, 0, W, 0);
      dg.addColorStop(0, c.bg2); dg.addColorStop(1, c.bg1);
      ctx.fillStyle = dg;
      ctx.fillRect(-open * W / 2, 0, W / 2, H);
      ctx.fillRect(W / 2 + open * W / 2, 0, W / 2, H);
      ctx.strokeStyle = c.accent; ctx.lineWidth = 8;
      ctx.beginPath(); ctx.moveTo(W / 2 - open * W / 2, 0); ctx.lineTo(W / 2 - open * W / 2, H); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(W / 2 + open * W / 2, 0); ctx.lineTo(W / 2 + open * W / 2, H); ctx.stroke();
      ctx.restore();
    }
  }
  ctx.save(); ctx.globalAlpha = .5; ctx.font = '26px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillStyle = c.text || '#fff';
  ctx.fillText('Made with ❤️ on ' + SITE, W / 2, H - 42);
  ctx.restore();
}

// ═══════════════════════ preview + export ═══════════════════════
let playing = false, t0 = 0, raf = null, previewT = 1.4;
const musEl = document.getElementById('musEl');
function loop(ts) {
  if (!playing) return;
  const t = (ts - t0) / 1000;
  if (t >= TOTAL) { stopPreview(); render(TOTAL - .01); return; }
  render(t); raf = requestAnimationFrame(loop);
}
function togglePreview() {
  if (playing) { stopPreview(); return; }
  playing = true;
  document.getElementById('btnPlay').textContent = '⏸ Pause';
  t0 = performance.now();
  if (musEl.src) { musEl.currentTime = 0; musEl.volume = .7; musEl.play().catch(() => {}); }
  raf = requestAnimationFrame(loop);
}
function stopPreview() {
  playing = false;
  document.getElementById('btnPlay').textContent = '▶ Play Preview';
  if (raf) cancelAnimationFrame(raf);
  musEl.pause();
}

async function startExport() {
  const st = document.getElementById('exStatus');
  if (NEED_LOGIN) { location.href = 'login.php'; return; }
  const btn = document.getElementById('btnDl');
  btn.disabled = true;
  st.textContent = 'Checking credits…';
  try {
    const fd = new FormData();
    fd.append('action', 'charge'); fd.append('csrf_token', CSRF);
    const r = await fetch('invite-edit.php?id=' + encodeURIComponent(TPL.id), { method: 'POST', body: fd }).then(x => x.json());
    if (!r.success) {
      st.textContent = r.msg || 'Could not start.';
      if (r.error === 'credits') setTimeout(() => location.href = 'payment.php', 1400);
      btn.disabled = false; return;
    }
  } catch (e) { st.textContent = 'Network error.'; btn.disabled = false; return; }

  stopPreview();
  st.textContent = '🎬 Rendering your video… keep this tab open';
  const stream = cv.captureStream(30);
  let ac = null;
  if (musEl.src) {
    try {
      ac = new (window.AudioContext || window.webkitAudioContext)();
      const srcNode = ac.createMediaElementSource(musEl);
      const gain = ac.createGain(); const dest = ac.createMediaStreamDestination();
      srcNode.connect(gain); gain.connect(dest); gain.connect(ac.destination);
      gain.gain.setValueAtTime(.0001, ac.currentTime);
      gain.gain.exponentialRampToValueAtTime(.8, ac.currentTime + 1.5);
      gain.gain.setValueAtTime(.8, ac.currentTime + Math.max(2, TOTAL - 2.5));
      gain.gain.exponentialRampToValueAtTime(.0001, ac.currentTime + TOTAL);
      dest.stream.getAudioTracks().forEach(tk => stream.addTrack(tk));
      musEl.currentTime = 0; musEl.play().catch(() => {});
    } catch (e) {}
  }
  let mime = '';
  for (const m of ['video/mp4;codecs=avc1', 'video/mp4', 'video/webm;codecs=vp9', 'video/webm']) {
    if (window.MediaRecorder && MediaRecorder.isTypeSupported(m)) { mime = m; break; }
  }
  if (!mime) { st.textContent = 'This browser cannot record video — try Chrome.'; btn.disabled = false; return; }
  const isMp4 = mime.indexOf('mp4') !== -1;
  const rec = new MediaRecorder(stream, { mimeType: mime, videoBitsPerSecond: 9000000 });
  const chunks = [];
  rec.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
  rec.onstop = () => {
    if (ac) { try { ac.close(); } catch (e) {} }
    musEl.pause();
    const blob = new Blob(chunks, { type: isMp4 ? 'video/mp4' : 'video/webm' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = (TPL.name || 'invitation').replace(/[^a-z0-9]+/gi, '_') + (isMp4 ? '.mp4' : '.webm');
    a.click();
    st.textContent = '✅ Downloaded!' + (isMp4 ? '' : ' (WebM — WhatsApp/Instagram friendly)');
    btn.disabled = false;
  };
  rec.start(400);
  const start = performance.now();
  (function exLoop(ts) {
    const t = (ts - start) / 1000;
    st.textContent = '🎬 Rendering… ' + Math.min(99, Math.round(t / TOTAL * 100)) + '% (keep this tab open)';
    if (t >= TOTAL) { rec.stop(); return; }
    render(t); requestAnimationFrame(exLoop);
  })(start);
}

// boot
buildEditor();
buildScenes();
render(previewT);
</script>
<?php render_tutorial_button('invitation'); ?>
</body>
</html>
