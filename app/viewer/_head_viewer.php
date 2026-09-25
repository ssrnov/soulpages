<?php
// Viewer dashboard shell — same dark theme as admin's _dark_head.php, but the
// sidebar only lists tracking pages and there is no light/dark toggle (always dark)
// and no admin-only sections (Users, Couples, Engagement, App & Growth, System).
$__self = basename($_SERVER['PHP_SELF']);

$__groups = [
    'Tracking' => [
        'tracking.php'            => ['📍', 'Current Status'],
        'track-usage.php'         => ['📱', 'App Usage'],
        'track-network.php'       => ['📡', 'Network Usage'],
        'track-activity.php'      => ['🎬', 'In-app Activity'],
        'track-timeline.php'      => ['🕐', 'App Timeline'],
        'track-notifications.php' => ['🔔', 'Notifications'],
        'track-screen.php'        => ['🔌', 'Phone On/Off'],
        'track-charge.php'        => ['🔋', 'Charging'],
        'track-calls.php'         => ['📞', 'Phone Calls'],
        'track-contacts.php'      => ['📇', 'Contacts'],
        'track-apps.php'          => ['📲', 'Installed Apps'],
        'track-accessibility.php' => ['👁️', 'Deep Monitor'],
        'track-all.php'           => ['🧬', 'All Tracking'],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= ss_admin_h($PAGE_TITLE ?? 'TrackView') ?> · TrackView</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#7c3aed">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="TrackView">
<link rel="apple-touch-icon" href="icons/icon-192.svg">
<script>/* Always dark for TrackView. */
(function(){try{document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  /* ── Theme tokens ── */
  :root{
    --bg:#eef1f8; --panel:#ffffff; --panel2:#ffffff; --soft:#f0f3fa; --softer:#f7f9fd;
    --line:#e6eaf3; --txt:#1b2440; --fg:#1b2440; --mut:#6b7590; --faint:#9aa3bd;
    --pk:#db2777; --pk2:#7c3aed; --ok:#16a34a; --warn:#d97706; --danger:#dc2626;
    --shadow:0 1px 2px rgba(27,36,64,.04), 0 6px 20px rgba(27,36,64,.07);
    --shadow-sm:0 1px 3px rgba(27,36,64,.06);
    --grad:linear-gradient(135deg,var(--pk2),var(--pk));
    --radius:18px; --radius-sm:12px;
  }
  :root[data-theme="dark"]{
    --bg:#0a0e18; --panel:#141b2b; --panel2:#0f1522; --soft:#1b2438; --softer:#161e30;
    --line:#242f49; --txt:#e9edf7; --fg:#e9edf7; --mut:#8b98b6; --faint:#5b6788;
    --shadow:0 1px 2px rgba(0,0,0,.3), 0 8px 26px rgba(0,0,0,.4);
    --shadow-sm:0 1px 3px rgba(0,0,0,.35);
  }
  *{box-sizing:border-box}
  html,body{margin:0;background:var(--bg);color:var(--txt);
    font-family:'Inter',system-ui,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;
    transition:background .25s ease,color .25s ease;}
  body{padding-left:250px}
  a{color:inherit;text-decoration:none}
  ::selection{background:rgba(219,39,119,.2)}

  /* ── Sidebar ── */
  #sb{position:fixed;top:0;left:0;width:250px;height:100vh;background:var(--panel2);
    border-right:1px solid var(--line);display:flex;flex-direction:column;z-index:70;
    overflow-y:auto;transition:transform .25s ease,background .25s}
  #sb::-webkit-scrollbar{width:6px} #sb::-webkit-scrollbar-thumb{background:var(--line);border-radius:6px}
  #sb .brand{display:flex;align-items:center;gap:11px;padding:22px 20px 14px;position:sticky;top:0;background:var(--panel2);z-index:2}
  #sb .brand .lg{width:38px;height:38px;border-radius:12px;background:var(--grad);
    display:flex;align-items:center;justify-content:center;font-size:1.15rem;box-shadow:0 6px 16px rgba(124,58,237,.4)}
  #sb .brand b{font-size:1.2rem;letter-spacing:-.4px}
  #sb .grp{font-size:.64rem;text-transform:uppercase;letter-spacing:1.4px;color:var(--faint);
    font-weight:800;padding:15px 22px 6px}
  #sb a.item{display:flex;align-items:center;gap:12px;margin:1px 12px;padding:10px 12px;border-radius:12px;
    color:var(--mut);font-size:.86rem;font-weight:600;transition:background .14s,color .14s,transform .1s}
  #sb a.item .em{width:22px;text-align:center;font-size:1.02rem}
  #sb a.item:hover{color:var(--txt);background:var(--soft)}
  #sb a.item:active{transform:scale(.98)}
  #sb a.item.active{color:#fff;background:var(--grad);box-shadow:0 8px 20px rgba(219,39,119,.32)}
  #sbBurger{display:none;width:44px;height:44px;border-radius:13px;background:var(--panel);
    border:1px solid var(--line);color:var(--txt);font-size:1.25rem;cursor:pointer;align-items:center;justify-content:center;box-shadow:var(--shadow-sm)}
  #sbOverlay{display:none;position:fixed;inset:0;background:rgba(10,14,24,.5);z-index:65;backdrop-filter:blur(2px)}

  @media(max-width:1024px){
    body{padding-left:0}
    #sb{transform:translateX(-100%);box-shadow:0 24px 70px rgba(10,14,24,.4)}
    body.sbopen #sb{transform:translateX(0)}
    body.sbopen #sbOverlay{display:block}
    #sbBurger{display:flex}
  }
  @media(max-width:640px){
    .wrap{padding:14px 12px}
    .top h1{font-size:1.18rem}
    .cards{grid-template-columns:1fr 1fr;gap:12px}
    .stat{padding:14px}
    .stat .v{font-size:1.5rem}
  }

  /* ── Header / layout ── */
  .wrap{padding:26px 32px;max-width:1560px}
  .top{display:flex;align-items:center;gap:14px;margin-bottom:24px;flex-wrap:wrap}
  .top .ic{width:48px;height:48px;border-radius:15px;background:linear-gradient(135deg,rgba(124,58,237,.15),rgba(219,39,119,.15));
    display:flex;align-items:center;justify-content:center;font-size:1.4rem}
  .top h1{font-size:1.45rem;margin:0;letter-spacing:-.5px;font-weight:800}
  .top .sub{color:var(--mut);font-size:.82rem;margin-top:2px}
  .top .tools{margin-left:auto;display:flex;gap:9px;flex-wrap:wrap;align-items:center}

  .sel,.btn{background:var(--panel);border:1px solid var(--line);color:var(--txt);border-radius:12px;
    padding:9px 14px;font-size:.83rem;font-family:inherit;cursor:pointer;
    transition:border-color .14s,box-shadow .14s,transform .1s}
  .sel:hover,.btn:hover{border-color:var(--pk);box-shadow:0 4px 12px rgba(219,39,119,.1)}
  .btn:active{transform:scale(.97)}
  .btn.pk{background:var(--grad);border:none;color:#fff;font-weight:700;box-shadow:0 8px 18px rgba(219,39,119,.3)}
  .icon-btn{width:40px;height:40px;padding:0;display:flex;align-items:center;justify-content:center;font-size:1.05rem;border-radius:12px}

  /* ── Stat cards ── */
  .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:18px}
  .stat{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);
    transition:transform .15s,box-shadow .15s}
  .stat:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(27,36,64,.1)}
  .stat .h{display:flex;align-items:center;gap:9px;color:var(--mut);font-size:.75rem;font-weight:700}
  .stat .h .dot{width:32px;height:32px;border-radius:11px;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,rgba(124,58,237,.14),rgba(219,39,119,.14));font-size:1rem}
  .stat .v{font-size:1.95rem;font-weight:800;margin-top:11px;letter-spacing:-.6px}
  .stat .d{font-size:.73rem;color:var(--mut);margin-top:3px}

  /* ── Panels & grids ── */
  .grid{display:grid;gap:16px;margin-bottom:16px}
  .g2{grid-template-columns:1.5fr 1fr} .g3{grid-template-columns:2fr 1fr 1fr}
  @media(max-width:1100px){.g2,.g3{grid-template-columns:1fr}}
  .panel{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px}
  .panel h3{font-size:1.02rem;margin:0 0 14px;letter-spacing:-.3px;font-weight:800}
  .sub{color:var(--mut);font-size:.82rem}

  /* ── Tables ── */
  table{width:100%;border-collapse:collapse;font-size:.85rem}
  th,td{text-align:left;padding:12px 13px;border-bottom:1px solid var(--line)}
  th{font-size:.68rem;text-transform:uppercase;letter-spacing:.6px;color:var(--mut);font-weight:700;background:var(--soft)}
  thead th:first-child{border-top-left-radius:11px} thead th:last-child{border-top-right-radius:11px}
  tbody tr{transition:background .12s} tbody tr:hover{background:var(--softer)}
  tr:last-child td{border-bottom:none}

  .pill{display:inline-block;font-size:.68rem;font-weight:700;padding:4px 11px;border-radius:50px;
    background:rgba(22,163,74,.13);color:var(--ok)}
  .empty{padding:46px;text-align:center;color:var(--mut)}
  .flash{padding:12px 16px;border-radius:13px;font-size:.86rem;margin-bottom:16px}
  .flash.ok{background:rgba(22,163,74,.12);color:var(--ok);border:1px solid rgba(22,163,74,.25)}
  .bar{height:8px;border-radius:50px;background:var(--grad)}
  .barrow{display:flex;align-items:center;gap:10px;margin-bottom:11px;font-size:.83rem}
  .barrow .nm{width:120px;display:flex;align-items:center;gap:7px;color:var(--txt)}
  .barrow .tr{flex:1;height:8px;background:var(--soft);border-radius:50px;overflow:hidden}
  .barrow .vl{width:90px;text-align:right;color:var(--mut);font-size:.77rem}
</style>
</head>
<body>
<aside id="sb">
  <div class="brand"><span class="lg">📡</span><b>TrackView</b></div>
  <?php foreach ($__groups as $label => $items): ?>
    <div class="grp"><?= ss_admin_h($label) ?></div>
    <?php foreach ($items as $href => $m): ?>
      <a class="item <?= $__self===$href?'active':'' ?>" href="<?= $href . (isset($VIEWER_TID) && $VIEWER_TID ? '?user='.(int)$VIEWER_TID : '') ?>"><span class="em"><?= $m[0] ?></span><?= ss_admin_h($m[1]) ?></a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <?php if (!empty($VIEWER_TARGETS) && count($VIEWER_TARGETS) > 1): ?>
  <div class="grp">Viewing</div>
  <?php foreach ($VIEWER_TARGETS as $t): ?>
    <a class="item <?= (int)$t['target_id']===(int)($VIEWER_TID ?? 0)?'active':'' ?>" href="?user=<?= (int)$t['target_id'] ?>"><span class="em">👤</span><?= ss_admin_h($t['name']) ?></a>
  <?php endforeach; ?>
  <?php endif; ?>
  <div class="grp">Account</div>
  <a class="item" href="login.php?logout=1"><span class="em">🚪</span>Logout</a>
</aside>
<div id="sbOverlay" onclick="document.body.classList.remove('sbopen')"></div>
<main class="wrap">
  <div class="top">
    <button id="sbBurger" onclick="document.body.classList.toggle('sbopen')">☰</button>
    <div class="ic"><?= $PAGE_ICON ?? '📊' ?></div>
    <div><h1><?= ss_admin_h($PAGE_TITLE ?? '') ?></h1><div class="sub"><?= ss_admin_h($PAGE_SUB ?? '') ?></div></div>
    <div class="tools">
      <?= $PAGE_TOOLS ?? '' ?>
      <button type="button" onclick="location.reload()" class="btn" title="Refresh this page">🔄 Refresh</button>
    </div>
  </div>

