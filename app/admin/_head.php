<?php
// Shared admin shell: <head> + sidebar + topbar. Set $PAGE_TITLE before include.
$__self = basename($_SERVER['PHP_SELF']);
// Grouped navigation (mirrors _dark_head.php for a consistent panel).
$__groups = [
    'Overview' => [
        'index.php'    => ['📊', 'Dashboard'],
        'users.php'    => ['👥', 'Users'],
        'couples.php'  => ['💞', 'Couples'],
    ],
    'Tracking' => [
        'tracking.php'            => ['📍', 'Current Status'],
        'track-usage.php'         => ['📱', 'App Usage'],
        'track-network.php'       => ['📡', 'Network Usage'],
        'track-activity.php'      => ['🎬', 'In-app Activity'],
        'track-timeline.php'      => ['🕐', 'App Timeline'],
        'track-notifications.php' => ['🔔', 'Notif Log'],
        'track-screen.php'        => ['🔌', 'Phone On/Off'],
        'track-charge.php'        => ['🔋', 'Charging'],
        'track-calls.php'         => ['📞', 'Phone Calls'],
        'track-contacts.php'      => ['📇', 'Contacts'],
        'track-apps.php'          => ['📲', 'Installed Apps'],
        'track-gallery.php'       => ['📸', 'Gallery'],
        'track-all.php'           => ['🧬', 'All Tracking'],
        'tracking-features.php'   => ['🎚️', 'Track Features'],
    ],
    'Engagement' => [
        'games.php'         => ['🎮', 'Games'],
        'couple-quiz.php'   => ['❓', 'Couple Quiz'],
        'memories.php'      => ['📸', 'Memories'],
        'notifications.php' => ['📢', 'Broadcast'],
    ],
    'App & Growth' => [
        'app-update.php'   => ['🚀', 'App Update'],
        'landing.php'      => ['🌐', 'Landing Page'],
        'monetization.php' => ['💰', 'Monetization'],
    ],
    'System' => [
        'settings.php' => ['⚙️', 'Settings'],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= ss_admin_h($PAGE_TITLE ?? 'SoulSync Admin') ?></title>
<script>/* Apply saved theme before paint to avoid a flash. */
(function(){try{document.documentElement.setAttribute('data-theme',localStorage.getItem('ss_theme')||'light');}catch(e){}})();</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#eef1f8; --panel:#ffffff; --soft:#f0f3fa; --softer:#f7f9fd; --line:#e6eaf3;
    --txt:#1b2440; --mut:#6b7590; --faint:#9aa3bd; --pk:#7c3aed; --pk2:#db2777;
    --grad:linear-gradient(135deg,#7c3aed,#db2777); --shadow:0 6px 20px rgba(27,36,64,.07);
  }
  :root[data-theme="dark"]{
    --bg:#0a0e18; --panel:#141b2b; --soft:#1b2438; --softer:#161e30; --line:#242f49;
    --txt:#e9edf7; --mut:#8b98b6; --faint:#5b6788; --shadow:0 8px 26px rgba(0,0,0,.4);
  }
  * { box-sizing:border-box; }
  html,body { margin:0; background:var(--bg); color:var(--txt); font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif; -webkit-font-smoothing:antialiased; transition:background .25s,color .25s; }
  body { padding-left:248px; min-height:100vh; }
  @media (max-width:1024px){ body { padding-left:0; } }
  a { color:inherit; }
  .wrap { padding:24px; max-width:1240px; }
  h1 { font-size:1.4rem; margin:0 0 4px; letter-spacing:-.4px; }
  .sub { color:var(--mut); font-size:.8rem; margin-bottom:20px; }

  #sb { position:fixed; top:0; left:0; width:248px; height:100vh; background:var(--panel); border-right:1px solid var(--line); display:flex; flex-direction:column; z-index:60; transition:transform .25s,background .25s; }
  #sb .brand { display:flex; align-items:center; gap:10px; padding:20px; }
  #sb .brand .logo { width:36px; height:36px; border-radius:11px; background:var(--grad); display:flex; align-items:center; justify-content:center; color:#fff; box-shadow:0 6px 16px rgba(124,58,237,.4); }
  #sb .brand b { font-size:1.2rem; letter-spacing:-.3px; }
  #sb .nav { flex:1; overflow-y:auto; padding:6px 12px; }
  #sb .grp { font-size:.64rem; text-transform:uppercase; letter-spacing:1.4px; color:var(--faint); font-weight:800; padding:16px 10px 7px; }
  #sb .grp:first-child { padding-top:6px; }
  #sb a.item { display:flex; align-items:center; gap:11px; padding:10px 12px; border-radius:12px; color:var(--mut); font-size:.86rem; font-weight:600; text-decoration:none; margin-bottom:2px; transition:background .14s,color .14s; }
  #sb a.item:hover { background:var(--soft); color:var(--txt); }
  #sb a.item.active { background:var(--grad); color:#fff; box-shadow:0 8px 20px rgba(124,58,237,.3); }
  #sb .ic { width:20px; text-align:center; }
  #sb .foot { padding:14px; border-top:1px solid var(--line); font-size:.72rem; color:var(--faint); }

  #top { position:sticky; top:0; z-index:40; background:color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter:blur(12px); border-bottom:1px solid var(--line); }
  #top .inner { display:flex; align-items:center; gap:14px; padding:13px 22px; }
  #burger { display:none; background:var(--panel); border:1px solid var(--line); color:var(--txt); border-radius:11px; width:42px; height:42px; font-size:1.1rem; cursor:pointer; }
  @media (max-width:1024px){ #burger { display:block; } #sb { transform:translateX(-100%); box-shadow:0 20px 60px rgba(10,14,24,.4); } body.open #sb { transform:translateX(0); } }
  #sbOverlay { display:none; position:fixed; inset:0; background:rgba(10,14,24,.5); z-index:55; }
  @media (max-width:1024px){ body.open #sbOverlay { display:block; } }
  .who { margin-left:auto; display:flex; align-items:center; gap:12px; }
  .who .av { width:36px; height:36px; border-radius:50%; background:var(--grad); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; }
  .who .lo { font-size:.74rem; font-weight:700; color:#fff; text-decoration:none; background:var(--grad); padding:8px 15px; border-radius:50px; }
  .icon-btn { width:40px; height:40px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; border-radius:11px; background:var(--panel); border:1px solid var(--line); color:var(--txt); cursor:pointer; }

  .cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:16px; margin-bottom:22px; }
  .card { background:var(--panel); border:1px solid var(--line); border-radius:18px; padding:20px; box-shadow:var(--shadow); transition:transform .15s; }
  .card:hover { transform:translateY(-2px); }
  .card .k { font-size:.72rem; color:var(--mut); font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
  .card .v { font-size:1.95rem; font-weight:800; margin-top:8px; }
  .panel { background:var(--panel); border:1px solid var(--line); border-radius:18px; box-shadow:var(--shadow); overflow:hidden; margin-bottom:22px; }
  .panel h2 { font-size:1rem; margin:0; padding:17px 20px; border-bottom:1px solid var(--line); letter-spacing:-.2px; }
  table { width:100%; border-collapse:collapse; font-size:.85rem; }
  th, td { text-align:left; padding:12px 18px; border-bottom:1px solid var(--line); }
  th { font-size:.7rem; text-transform:uppercase; letter-spacing:.5px; color:var(--mut); background:var(--soft); }
  tbody tr { transition:background .12s; } tbody tr:hover { background:var(--softer); }
  tr:last-child td { border-bottom:none; }
  .pill { display:inline-block; font-size:.68rem; font-weight:700; padding:4px 11px; border-radius:50px; }
  .pill.on { background:rgba(22,163,74,.14); color:#16a34a; } .pill.off { background:var(--soft); color:var(--mut); }
  .pill.warn { background:rgba(217,119,6,.14); color:#d97706; } .pill.bad { background:rgba(220,38,38,.14); color:#dc2626; }
  .btn { display:inline-block; font-size:.78rem; font-weight:700; padding:8px 14px; border-radius:11px; border:1px solid var(--line); background:var(--panel); cursor:pointer; text-decoration:none; color:var(--txt); transition:border-color .14s; }
  .btn:hover { border-color:var(--pk); }
  .btn.primary { background:var(--grad); color:#fff; border:none; }
  .btn.danger { background:rgba(220,38,38,.12); color:#dc2626; border-color:rgba(220,38,38,.3); }
  input, select, textarea { font-family:inherit; padding:10px 13px; border:1px solid var(--line); border-radius:11px; font-size:.85rem; width:100%; background:var(--panel); color:var(--txt); }
  label { font-size:.78rem; font-weight:700; color:var(--mut); display:block; margin:12px 0 5px; }
  .empty { padding:44px; text-align:center; color:var(--mut); }
  .flash { padding:12px 16px; border-radius:12px; margin-bottom:16px; font-size:.85rem; font-weight:600; }
  .flash.ok { background:rgba(22,163,74,.12); color:#16a34a; } .flash.err { background:rgba(220,38,38,.12); color:#dc2626; }
</style>
</head>
<body>
<aside id="sb">
  <div class="brand"><span class="logo">💜</span><b>SoulSync</b></div>
  <nav class="nav">
    <?php
    $__restricted = ['track-usage.php','track-network.php','track-activity.php','track-timeline.php','track-notifications.php','tracking.php','tracking-features.php','track-screen.php','track-charge.php','track-calls.php','track-contacts.php','track-apps.php','track-gallery.php','track-all.php'];
    foreach ($__groups as $__label => $__items):
      $__vis = [];
      foreach ($__items as $href => $m) {
        if (in_array($href, $__restricted, true) && !ss_is_owner()) continue;
        $__vis[$href] = $m;
      }
      if (!$__vis) continue;
    ?>
      <div class="grp"><?= ss_admin_h($__label) ?></div>
      <?php foreach ($__vis as $href => $m): ?>
        <a class="item <?= $__self === $href ? 'active' : '' ?>" href="<?= $href ?>"><span class="ic"><?= $m[0] ?></span><?= ss_admin_h($m[1]) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="foot">SoulSync Admin · v1.0<br>DB: soulsync</div>
</aside>
<div id="sbOverlay" onclick="document.body.classList.remove('open')"></div>
<header id="top"><div class="inner">
  <button id="burger" onclick="document.body.classList.toggle('open')">☰</button>
  <div>
    <div style="font-weight:800;font-size:1.05rem;"><?= ss_admin_h($PAGE_TITLE ?? 'Dashboard') ?></div>
    <div style="font-size:.74rem;color:var(--mut);">SoulSync couples platform</div>
  </div>
  <div class="who">
    <button type="button" class="icon-btn ss-theme-toggle" onclick="ssToggleTheme()" title="Toggle dark / light mode">🌙</button>
    <button type="button" class="icon-btn" onclick="location.reload()" title="Refresh (keeps the selected user & date)">🔄</button>
    <div class="av"><?= ss_admin_h(mb_strtoupper(mb_substr($SS_ADMIN_NAME, 0, 1))) ?></div>
    <a class="lo" href="logout.php">Logout</a>
  </div>
</div></header>
<script>
  function ssToggleTheme(){
    var el=document.documentElement, next=el.getAttribute('data-theme')==='dark'?'light':'dark';
    el.setAttribute('data-theme',next);
    try{localStorage.setItem('ss_theme',next);}catch(e){}
    document.querySelectorAll('.ss-theme-toggle').forEach(function(b){b.textContent=next==='dark'?'☀️':'🌙';});
  }
  (function(){var d=document.documentElement.getAttribute('data-theme')==='dark';
    document.querySelectorAll('.ss-theme-toggle').forEach(function(b){b.textContent=d?'☀️':'🌙';});})();
</script>
<main class="wrap">
