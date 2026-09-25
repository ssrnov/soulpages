<?php
// =========================================================================
// Shared admin shell — light left-sidebar + top bar.
// Every admin page does `include '_nav.php';` right after <body>. This file
// injects global CSS that (a) lays out the fixed sidebar + padded content and
// (b) converts the old dark card/surface utility classes to a light theme, so
// existing pages become light automatically without editing each one.
// Set $ADMIN_TITLE before including for the top-bar title.
// =========================================================================
$__current = basename($_SERVER['PHP_SELF']);
$__admin_name = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$__verify_pending = function_exists('count_verify_requests') ? count_verify_requests($pdo) : 0;

// Grouped navigation
$__groups = [
    'Overview' => [
        'index.php'      => ['📊', 'Dashboard'],
        'analytics.php'  => ['📈', 'Analytics'],
    ],
    'Content' => [
        'pages.php'          => ['📄', 'Pages'],
        'categories.php'     => ['🗂️', 'Categories'],
        'premium-demos.php'  => ['✨', 'Premium Demos'],
        'invitations.php'    => ['💌', 'Invitations'],
        'festivals.php'      => ['🪔', 'Festivals'],
        'examples.php'       => ['✍️', 'Example Texts'],
        'tutorials.php'      => ['🎥', 'Tutorials'],
    ],
    'People' => [
        'users.php'           => ['👥', 'Users'],
        'verify-requests.php' => ['🙋', 'Verify Requests'],
        'replies.php'         => ['💬', 'Replies'],
        'messages.php'        => ['📮', 'Inbox'],
    ],
    'Money' => [
        'payments.php' => ['💰', 'Payments'],
        'plans.php'    => ['💳', 'Plans'],
        'gateways.php' => ['🏦', 'Gateways'],
        'coupons.php'  => ['🎟', 'Coupons'],
        'ads.php'      => ['📢', 'Ads'],
    ],
    'Library & System' => [
        'music.php'    => ['🎵', 'Music'],
        'support.php'  => ['💬', 'Support Messages'],
        'site.php'     => ['🛡️', 'Site Controls'],
        'settings.php' => ['⚙️', 'Settings'],
    ],
];

// ── SoulSync bridge: owner (super admin) and managers get a one-click SSO
//    link into the separate SoulSync admin panel. The role is signed into the
//    token so SoulSync can hide restricted sections from managers. ──
$__ss_is_owner = function_exists('is_super_admin') && is_super_admin();
$__ss_is_mgr   = function_exists('is_manager') && is_manager();
// SoulSync is now part of soulpages (app/admin), authed by this same login —
// no SSO. Links go straight to the native pages.
$__ss_show = ($__ss_is_owner || $__ss_is_mgr);
// SoulSync sections. Managers can't see the restricted (owner-only) ones.
$__ss_all = [
    'index.php'           => ['📊', 'SoulSync Dashboard', false],
    'users.php'           => ['👥', 'App Users',          false],
    'couples.php'         => ['💞', 'Couples',            false],
    'game-content.php'    => ['🎲', 'Game Content',       true],
    'couple-quiz.php'     => ['❓', 'Couple Quiz',         true],
    'discover.php'        => ['✨', 'Discover',            true],
    'premium-control.php' => ['👑', 'Premium Control',    true],
    'monetization.php'    => ['💰', 'Ads & Monetization', true],
    'house-ads.php'       => ['🖼️', 'My Ads',             true],
    'reviews.php'         => ['⭐', 'Reviews',            true],
    'tracking.php'        => ['📍', 'Location',           true],
    'track-usage.php'     => ['📈', 'App Usage',          true],
    'track-notifications.php' => ['🔔', 'Notifications',  true],
    'tracking-features.php'   => ['🎚️', 'Track Features', true],
    'memories.php'        => ['📸', 'Memories',           false],
    'notifications.php'   => ['📢', 'Broadcast',          false],
    'app-update.php'      => ['🚀', 'App Update',         true],
    'landing.php'         => ['🌐', 'Landing Page',       true],
    'settings.php'        => ['⚙️', 'SoulSync Settings',  true],
];
?>
<style>
  :root { --ad-pink:#db2777; --ad-pink2:#e11d48; }
  /* ===== light theme + sidebar layout (overrides old dark admin styling) ===== */
  html, body { background:#f6f7fb !important; color:#1e293b !important; }
  body { padding-left:248px !important; min-height:100vh; }
  @media (max-width:1024px){ body { padding-left:0 !important; } }

  /* surfaces: old dark cards → white */
  .glass-card,
  .bg-slate-950, .bg-slate-950\/40, .bg-slate-950\/60, .bg-slate-950\/80, .bg-slate-950\/50,
  .bg-slate-900, .bg-slate-900\/40, .bg-slate-900\/50, .bg-slate-900\/60, .bg-slate-900\/70 {
    background:#ffffff !important; backdrop-filter:none !important;
    border:1px solid #eef0f4 !important; box-shadow:0 4px 18px rgba(30,41,59,0.05) !important;
  }
  .bg-slate-800, .bg-slate-800\/50, .bg-slate-850 { background:#f1f3f9 !important; }
  .border-slate-800, .border-slate-900, .border-slate-800\/50, .border-slate-800\/60, .border-slate-800\/80, .border-slate-850, .border-slate-700 { border-color:#eef0f4 !important; }
  /* table row dividers + dark hover states → light */
  [class*="divide-slate-9"] > * { border-color:#eef0f4 !important; }
  [class*="hover:bg-slate-9"]:hover { background:#f8fafc !important; }
  thead[class*="bg-slate-9"], .bg-slate-900\/80 { background:#f8fafc !important; }

  /* text: light → dark */
  .text-white, .text-slate-100, .text-slate-200, .text-slate-300 { color:#0f172a !important; }
  .text-slate-350, .text-slate-400, .text-slate-500, .text-slate-600 { color:#64748b !important; }

  /* form controls on light */
  input:not([type=checkbox]):not([type=radio]):not([type=color]), textarea, select,
  .ifld, .efld, .tfld, .pfld, .gfld, .fld {
    background:#ffffff !important; border:1px solid #e2e8f0 !important; color:#0f172a !important;
  }
  input::placeholder, textarea::placeholder { color:#94a3b8 !important; }

  /* gradient/pink buttons keep white text (must come after .text-white rule) */
  [class*="from-pink"], [class*="from-purple"], [class*="from-emerald"], [class*="from-rose"],
  [class*="from-pink"] *, [class*="from-purple"] *, [class*="from-emerald"] *,
  .btn-primary, .btn-primary * { color:#ffffff !important; }

  /* ===== sidebar ===== */
  #adSidebar { position:fixed; top:0; left:0; width:248px; height:100vh; background:#ffffff; border-right:1px solid #eef0f4;
    display:flex; flex-direction:column; z-index:60; transition:transform .25s ease; }
  #adSidebar .brand { display:flex; align-items:center; gap:10px; padding:20px 20px 16px; }
  #adSidebar .brand .logo { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,#f43f5e,#db2777); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.1rem; box-shadow:0 6px 16px rgba(219,39,119,.3); }
  #adSidebar .brand b { font-family:'Outfit',sans-serif; font-size:1.25rem; color:#0f172a; }
  #adSidebar .nav { flex:1; overflow-y:auto; padding:6px 12px 16px; }
  #adSidebar .grp { font-size:.62rem; text-transform:uppercase; letter-spacing:1.4px; color:#a3adc2; font-weight:800; padding:14px 12px 6px; }
  #adSidebar a.item { display:flex; align-items:center; gap:11px; padding:9px 12px; border-radius:11px; color:#475569; font-size:.85rem; font-weight:600; text-decoration:none; margin-bottom:2px; transition:.15s; }
  #adSidebar a.item:hover { background:#f6f7fb; color:#0f172a; }
  #adSidebar a.item.active { background:linear-gradient(135deg,#f43f5e,#db2777); color:#fff; box-shadow:0 8px 20px rgba(219,39,119,.28); }
  #adSidebar a.item .ic { font-size:1rem; width:20px; text-align:center; }
  #adSidebar .pro { margin:12px; padding:16px; border-radius:16px; background:linear-gradient(150deg,#1a0810,#2d0f1e); color:#fff; text-align:center; }
  #adSidebar .pro .u { display:inline-block; margin-top:8px; background:linear-gradient(135deg,#f43f5e,#db2777); color:#fff !important; font-size:.72rem; font-weight:800; padding:8px 18px; border-radius:50px; text-decoration:none; }

  /* ===== topbar ===== */
  #adTop { position:sticky; top:0; z-index:40; background:rgba(246,247,251,.85); backdrop-filter:blur(12px); border-bottom:1px solid #eef0f4; }
  #adTop .inner { display:flex; align-items:center; gap:14px; padding:14px 22px; }
  #adBurger { display:none; background:#fff; border:1px solid #eef0f4; border-radius:10px; width:40px; height:40px; font-size:1.1rem; cursor:pointer; }
  @media (max-width:1024px){ #adBurger { display:block; } #adSidebar { transform:translateX(-100%); box-shadow:0 20px 60px rgba(0,0,0,.2); } body.ad-open #adSidebar { transform:translateX(0); } #adOverlay.on { display:block; } }
  #adOverlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.4); z-index:55; }
  #adTop .who { margin-left:auto; display:flex; align-items:center; gap:12px; }
  #adTop .who .av { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#f43f5e,#db2777); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; }
  #adTop .who .nm { font-size:.85rem; font-weight:700; color:#0f172a; line-height:1.1; }
  #adTop .who .rl { font-size:.68rem; color:#94a3b8; }
  #adTop .logout { font-size:.74rem; font-weight:700; color:#db2777; text-decoration:none; background:#fdf2f8; border:1px solid #fbcfe8; padding:8px 14px; border-radius:50px; }
</style>

<div id="adOverlay" onclick="document.body.classList.remove('ad-open');this.classList.remove('on');"></div>
<aside id="adSidebar">
  <div class="brand">
    <span class="logo">💗</span>
    <b>SoulPages</b>
  </div>
  <nav class="nav">
    <?php foreach ($__groups as $gname => $items): ?>
    <div class="grp"><?= h($gname) ?></div>
    <?php foreach ($items as $href => $meta): $active = ($__current === $href); ?>
    <a class="item <?= $active ? 'active' : '' ?>" href="<?= $href ?>"><span class="ic"><?= $meta[0] ?></span><?= h($meta[1]) ?><?php if ($href === 'verify-requests.php' && $__verify_pending > 0): ?><span style="margin-left:auto;background:#ef4444;color:#fff;font-size:.62rem;font-weight:800;padding:2px 7px;border-radius:50px;"><?= (int)$__verify_pending ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if ($__ss_show): ?>
    <div class="grp">SoulSync App</div>
    <?php foreach ($__ss_all as $__f => $__m):
        if ($__m[2] && !$__ss_is_owner) continue; // owner-only sections hidden from managers
    ?>
    <a class="item" href="../app/admin/<?= $__f ?>"><span class="ic"><?= $__m[0] ?></span><?= h($__m[1]) ?></a>
    <?php endforeach; ?>
    <?php endif; ?>
  </nav>
  <div class="pro">
    <div style="font-size:1.4rem;">📲</div>
    <div style="font-family:'Outfit',sans-serif; font-weight:800; font-size:.95rem; margin-top:2px;">Install the Admin App</div>
    <div style="font-size:.7rem; color:rgba(255,255,255,.6); margin-top:2px;">Get verification alerts on your phone</div>
    <a class="u" href="#" id="adInstallBtn" style="display:none;">⬇ Install App</a>
    <a class="u" href="../index.php" id="adViewSite" style="background:rgba(255,255,255,.12);">↗ View Site</a>
    <button type="button" id="adEnableNotif" class="u" style="display:none;border:none;cursor:pointer;background:rgba(255,255,255,.12);">🔔 Enable Alerts</button>
  </div>
</aside>

<header id="adTop">
  <div class="inner">
    <button id="adBurger" onclick="document.body.classList.toggle('ad-open');document.getElementById('adOverlay').classList.toggle('on');">☰</button>
    <div>
      <div style="font-family:'Outfit',sans-serif; font-weight:800; font-size:1.15rem; color:#0f172a;">Welcome back, <?= h(explode(' ', $__admin_name)[0]) ?>! 👋</div>
      <div style="font-size:.76rem; color:#94a3b8;"><?= !empty($ADMIN_TITLE) ? h($ADMIN_TITLE) : "Here's what's happening on your platform" ?></div>
    </div>
    <div class="who">
      <div class="av"><?= h(mb_strtoupper(mb_substr($__admin_name, 0, 1))) ?></div>
      <div class="hidden sm:block">
        <div class="nm"><?= h($__admin_name) ?></div>
        <div class="rl"><?= is_super_admin() ? '👑 Super Admin' : '🛠️ Manager' ?></div>
      </div>
      <a class="logout" href="../logout.php">Logout</a>
    </div>
  </div>
</header>

<?php ensure_vapid_keys(); ?>
<script>
(function () {
  var VAPID_PUB = <?= json_encode(vapid_public_key()) ?>;
  // ── make the admin panel an installable PWA (acts like a phone app) ──
  var head = document.head;
  if (!document.querySelector('link[rel="manifest"]')) {
    head.insertAdjacentHTML('beforeend',
      '<link rel="manifest" href="manifest.webmanifest">' +
      '<meta name="theme-color" content="#db2777">' +
      '<meta name="apple-mobile-web-app-capable" content="yes">' +
      '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' +
      '<meta name="apple-mobile-web-app-title" content="SP Admin">' +
      '<link rel="apple-touch-icon" href="icon.svg">');
  }

  var swReg = null;
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').then(function (r) { swReg = r; }).catch(function () {});
  }

  // Install button (Android/desktop Chrome fire beforeinstallprompt)
  var deferred = null;
  var installBtn = document.getElementById('adInstallBtn');
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); deferred = e;
    if (installBtn) installBtn.style.display = '';
  });
  if (installBtn) installBtn.addEventListener('click', function (e) {
    e.preventDefault();
    if (deferred) { deferred.prompt(); deferred.userChoice.finally(function () { deferred = null; installBtn.style.display = 'none'; }); }
    else { alert('On your phone: open browser menu (⋮) → "Install app" / "Add to Home screen".'); }
  });

  // ── Web Push subscription: lets the server notify this device even when the app is closed ──
  function urlB64(base64) {
    var pad = '='.repeat((4 - base64.length % 4) % 4);
    var raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var arr = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
    return arr;
  }
  function subscribePush(test) {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !VAPID_PUB) return Promise.resolve(false);
    return navigator.serviceWorker.ready.then(function (reg) {
      return reg.pushManager.getSubscription().then(function (sub) {
        if (sub) return sub;
        return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64(VAPID_PUB) });
      });
    }).then(function (sub) {
      return fetch('push-subscribe.php' + (test ? '?test=1' : ''), {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(sub)
      }).then(function (r) { return r.json(); });
    }).catch(function () { return false; });
  }

  // ── notifications: enable + poll for new verification requests ──
  var notifBtn = document.getElementById('adEnableNotif');
  function notifState() {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'default' && notifBtn) notifBtn.style.display = '';
    else if (notifBtn) notifBtn.style.display = 'none';
  }
  notifState();
  if ('Notification' in window && Notification.permission === 'granted') subscribePush(false);
  if (notifBtn) notifBtn.addEventListener('click', function () {
    Notification.requestPermission().then(function (p) { notifState(); if (p === 'granted') { subscribePush(false); startPolling(); } });
  });

  function showNotif(title, body) {
    if (swReg && swReg.showNotification) {
      swReg.showNotification(title, { body: body, icon: 'icon.svg', badge: 'icon.svg', tag: 'sp-verify', data: { url: 'verify-requests.php' }, vibrate: [120, 60, 120] });
    } else if (swReg && swReg.active) {
      swReg.active.postMessage({ type: 'notify', title: title, body: body, url: 'verify-requests.php', tag: 'sp-verify' });
    } else if ('Notification' in window && Notification.permission === 'granted') {
      var n = new Notification(title, { body: body, icon: 'icon.svg' });
      n.onclick = function () { window.focus(); location.href = 'verify-requests.php'; };
    }
  }
  function fireNotify(count) {
    showNotif('🙋 New verification request', count + ' user' + (count > 1 ? 's are' : ' is') + ' waiting to be verified — tap to review.');
  }

  var seen = parseInt(localStorage.getItem('sp_verify_seen') || '0', 10);
  var pollTimer = null;
  function pollOnce() {
    return fetch('verify-poll.php', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) return;
      var c = d.count | 0;
      if (c > seen && Notification.permission === 'granted') fireNotify(c);
      seen = c; localStorage.setItem('sp_verify_seen', String(c));
      return c;
    }).catch(function () {});
  }
  function startPolling() {
    if (pollTimer || !('Notification' in window)) return;
    pollOnce();                                   // check immediately on load
    pollTimer = setInterval(pollOnce, 30000);     // then every 30s while open
  }
  startPolling();

  // Exposed so the verify page can enable + send a real end-to-end test push.
  window.spTestNotify = function () {
    if (!('Notification' in window)) { alert('This browser does not support notifications.'); return; }
    Notification.requestPermission().then(function (p) {
      notifState();
      if (p !== 'granted') { alert('Notifications are blocked. Enable them for this site in your browser settings, then try again.'); return; }
      startPolling();
      subscribePush(true).then(function (res) {
        if (res && res.sent) { /* server push on its way → SW will show it */ }
        else { showNotif('✅ Test alert', 'Notifications are on. (If server push failed, alerts still work while the app is open.)'); }
      });
    });
  };
})();
</script>
