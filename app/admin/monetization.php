<?php
require_once __DIR__ . '/_boot.php';
$PAGE_TITLE = 'Ads & Money';
$PAGE_SUB   = 'One clear place to control every ad';
$PAGE_ICON  = '💰';
$flash = '';

// ── All settings we manage here ──
$toggleKeys = [
    'ads_enabled','ad_web_enabled','adsterra_app_enabled',
    'ad_web_sticky','ad_web_home','ad_web_publish','ad_web_dashboard','ad_web_soulsync','ad_web_popunder','ad_web_social',
    'ad_home','ad_games','ad_memories','ad_interstitial',
    'reward_enabled','payment_enabled',
];
$textKeys = [
    'ad_web_sticky_code','ad_web_home_code','ad_web_publish_code','ad_web_dashboard_code',
    'ad_web_soulsync_code','ad_web_popunder_code','ad_web_social_code','adsterra_web_code',
    'ad_app_home_code','ad_app_games_code','ad_app_memories_code','ad_app_interstitial_code',
    // per-placement provider choice (which network to show on that spot)
    'ad_web_sticky_provider','ad_web_home_provider','ad_web_publish_provider','ad_web_dashboard_provider',
    'ad_web_soulsync_provider','ad_web_popunder_provider','ad_web_social_provider',
    'ad_app_home_provider','ad_app_games_provider','ad_app_memories_provider','ad_app_interstitial_provider',
    'reward_ad_url','reward_source','adsterra_app_key',
    'subscription_price','subscription_days','payment_gateway',
    'razorpay_key_id','razorpay_key_secret','instamojo_api_key','instamojo_auth_token','cashfree_app_id','cashfree_secret',
];

// Ad networks you can pick per spot.
$webProviders = ['adsterra'=>'Adsterra','monetag'=>'Monetag','adcash'=>'Adcash','hilltopads'=>'HilltopAds',
                 'propellerads'=>'PropellerAds','clickadu'=>'Clickadu','custom'=>'Other / Custom code','image'=>'My own image ad'];
$appProviders = ['startio'=>'Start.io (native)','html'=>'HTML code (Adsterra/Monetag)','image'=>'My ad (upload in My Ads)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($toggleKeys as $k) ss_set_setting($pdo, $k, isset($_POST[$k]) ? '1' : '0');
    foreach ($textKeys as $k) if (array_key_exists($k, $_POST)) ss_set_setting($pdo, $k, trim($_POST[$k]));
    $flash = 'Saved ✓ — app & website update instantly.';
}

$cur = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM app_settings") as $r) $cur[$r['setting_key']] = $r['setting_value'];
$on  = fn($k) => (int)($cur[$k] ?? 0) === 1;
$val = fn($k) => $cur[$k] ?? '';
$gw  = $cur['payment_gateway'] ?? 'instamojo';

// Placement definitions: [toggleKey, codeKey, providerKey, emoji, title, "where it shows", size]
$webAds = [
    ['ad_web_sticky','ad_web_sticky_code','ad_web_sticky_provider','📌','Sticky bottom bar','Fixed at the bottom of EVERY website page','320×50 / 728×90'],
    ['ad_web_home','ad_web_home_code','ad_web_home_provider','🏠','Home page','In the middle of the homepage','728×90 / 300×250'],
    ['ad_web_publish','ad_web_publish_code','ad_web_publish_provider','✅','Publish success','After a user creates a page','300×250'],
    ['ad_web_dashboard','ad_web_dashboard_code','ad_web_dashboard_provider','📊','Dashboard','On the logged-in dashboard','728×90'],
    ['ad_web_soulsync','ad_web_soulsync_code','ad_web_soulsync_provider','💜','SoulSync landing','On the soulsync.php app page','728×90 / 300×250'],
    ['ad_web_popunder','ad_web_popunder_code','ad_web_popunder_provider','🪟','Popunder (full-screen)','Full-screen popup on click — can annoy users','no size'],
    ['ad_web_social','ad_web_social_code','ad_web_social_provider','🎈','Social Bar (floating)','Floating bar that follows scrolling','no size'],
];
$appAds = [
    ['ad_home','ad_app_home_code','ad_app_home_provider','🏠','App — Home tab','Small banner at the bottom of the Home tab','320×50 banner'],
    ['ad_games','ad_app_games_code','ad_app_games_provider','🎮','App — Games','Banner on the Games screen','320×50 banner'],
    ['ad_memories','ad_app_memories_code','ad_app_memories_provider','📸','App — Memories','Banner on the Memories screen','320×50 banner'],
    ['ad_interstitial','ad_app_interstitial_code','ad_app_interstitial_provider','⛶','App — Full screen','Full-screen ad after finishing a game','300×250'],
];

require __DIR__ . '/_dark_head.php';

// A single placement card: ON/OFF + provider picker + code box.
// $isApp true → provider list is Start.io/HTML (code box hidden for Start.io).
function ad_card($p, $on, $val, $providers, $isApp = false) {
    [$tk,$ck,$pk,$emoji,$title,$where,$size] = $p;
    $prov = $val($pk) ?: ($isApp ? 'startio' : 'adsterra');
    $needsCode = !$isApp ? ($prov !== 'image') : ($prov === 'html');
    $live = $on($tk) && ($prov !== '') && (!$needsCode || trim($val($ck)) !== '');
    ?>
    <div style="border:1px solid <?= $live ? '#22c55e55' : 'rgba(255,255,255,.08)' ?>;border-radius:14px;padding:14px;margin-bottom:12px;background:rgba(255,255,255,.02)">
      <div style="display:flex;align-items:center;gap:10px">
        <span style="font-size:1.3rem"><?= $emoji ?></span>
        <div style="flex:1">
          <div style="font-weight:700;color:var(--txt);font-size:.95rem"><?= ss_admin_h($title) ?>
            <span style="font-size:.62rem;background:rgba(255,255,255,.08);padding:2px 7px;border-radius:20px;color:var(--mut);margin-left:4px">📐 <?= ss_admin_h($size) ?></span></div>
          <div style="font-size:.72rem;color:var(--mut)">Shows: <?= ss_admin_h($where) ?></div>
        </div>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
          <span style="font-size:.7rem;color:<?= $live?'#22c55e':'var(--mut)' ?>;font-weight:700"><?= $on($tk) ? 'ON' : 'OFF' ?></span>
          <input type="checkbox" name="<?= $tk ?>" value="1" <?= $on($tk)?'checked':'' ?> style="width:18px;height:18px">
        </label>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-top:10px">
        <span style="font-size:.72rem;color:var(--mut);white-space:nowrap">Ad network:</span>
        <select class="sel" name="<?= $pk ?>" style="flex:1;font-size:.8rem" onchange="this.closest('div').parentNode.querySelector('.codebox')?.classList.toggle('hidden', this.value==='image'||this.value==='startio')">
          <?php foreach ($providers as $pv => $pl): ?>
            <option value="<?= $pv ?>" <?= $prov===$pv?'selected':'' ?>><?= ss_admin_h($pl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <textarea class="sel codebox <?= $needsCode ? '' : 'hidden' ?>" name="<?= $ck ?>"
        placeholder="Paste the ad code from the network you picked above…"
        style="width:100%;margin-top:8px;min-height:58px;font-family:monospace;font-size:.74rem"><?= ss_admin_h($val($ck)) ?></textarea>
    </div>
    <?php
}
?>
<style>.hidden{display:none !important}</style>
<?php if ($flash): ?><div class="panel" style="border-color:#22c55e;margin-bottom:14px"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<!-- Quick guide -->
<div class="panel" style="margin-bottom:14px;background:linear-gradient(135deg,rgba(124,58,237,.12),rgba(219,39,119,.12))">
  <h3>🧭 How ads work (read once)</h3>
  <div style="font-size:.82rem;color:#cbd5e1;line-height:1.9">
    1. <b>Master switch</b> ON → then <b>Website ads</b> and/or <b>App ads</b> master ON.<br>
    2. Each spot: flip <b>ON</b>, pick the <b>Ad network</b> for that spot, then paste that network's code. Green border = live.<br>
    3. You can run a <b>different network on each spot</b> — pick it from the dropdown.<br>
    4. <b>Website networks:</b> Adsterra, Monetag, Adcash, HilltopAds, PropellerAds, Clickadu, or <b>My own image ad</b>.<br>
    5. <b>App networks:</b> <b>Start.io</b> (native, no code — works best) or <b>HTML code</b> (Adsterra/Monetag banner).<br>
    6. 🟢 = ready · ⚪ = off/empty · Premium users never see ads.
  </div>
</div>

<!-- Ad network signup links -->
<div class="panel" style="margin-bottom:14px">
  <h3>🌐 Ad networks (sign up, get code)</h3>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">
    <?php foreach ([
      'Adsterra'=>'https://adsterra.com','Monetag'=>'https://monetag.com','Adcash'=>'https://adcash.com',
      'HilltopAds'=>'https://hilltopads.com','PropellerAds'=>'https://propellerads.com','Clickadu'=>'https://clickadu.com',
      'Start.io (app)'=>'https://start.io','AdMob (app)'=>'https://admob.google.com',
    ] as $nm=>$u): ?>
      <a class="btn" href="<?= $u ?>" target="_blank" rel="noopener" style="font-size:.75rem"><?= ss_admin_h($nm) ?> ↗</a>
    <?php endforeach; ?>
  </div>
  <div style="font-size:.72rem;color:var(--mut);margin-top:8px">Sign up on any → create a banner ad unit → copy its code → paste in the matching spot above.</div>
</div>

<form method="post">

  <!-- MASTER -->
  <div class="panel" style="margin-bottom:14px">
    <label style="display:flex;align-items:center;gap:12px;cursor:pointer">
      <input type="checkbox" name="ads_enabled" value="1" <?= $on('ads_enabled')?'checked':'' ?> style="width:22px;height:22px">
      <div>
        <div style="font-weight:800;font-size:1.05rem;color:var(--txt)">🔌 MASTER — Ads <?= $on('ads_enabled')?'ON':'OFF' ?></div>
        <div style="font-size:.75rem;color:var(--mut)">OFF = no ads anywhere (website + app). This is the main switch.</div>
      </div>
    </label>
  </div>

  <div class="grid g2">
    <!-- WEBSITE ADS -->
    <div class="panel">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
        <h3 style="margin:0">🌐 Website Ads</h3>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.75rem;color:var(--mut)">
          Website master
          <input type="checkbox" name="ad_web_enabled" value="1" <?= $on('ad_web_enabled')?'checked':'' ?> style="width:18px;height:18px">
        </label>
      </div>
      <?php foreach ($webAds as $p) ad_card($p, $on, $val, $webProviders, false); ?>
      <label style="font-size:.72rem;color:var(--mut)">Fallback code (used only where a spot above is empty)</label>
      <textarea class="sel" name="adsterra_web_code" style="width:100%;margin-top:4px;min-height:44px;font-family:monospace;font-size:.72rem"><?= ss_admin_h($val('adsterra_web_code')) ?></textarea>
    </div>

    <!-- APP ADS -->
    <div class="panel">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
        <h3 style="margin:0">📱 App Ads</h3>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.75rem;color:var(--mut)">
          App master
          <input type="checkbox" name="adsterra_app_enabled" value="1" <?= $on('adsterra_app_enabled')?'checked':'' ?> style="width:18px;height:18px">
        </label>
      </div>
      <?php foreach ($appAds as $p) ad_card($p, $on, $val, $appProviders, true); ?>
      <div style="font-size:.72rem;color:#fbbf24;background:rgba(251,191,36,.08);padding:8px 10px;border-radius:10px">
        ⚠️ Use a <b>Banner 320×50</b> code here (Adsterra/Monetag). Social Bar / Popunder / Start.io SDK will show <b>blank</b> in the app.
      </div>
    </div>
  </div>

  <!-- REWARD -->
  <div class="panel" style="margin-top:14px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
      <h3 style="margin:0">🎁 Reward — watch ad = 1 day Premium</h3>
      <label style="margin-left:auto;display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.75rem;color:var(--mut)">
        <?= $on('reward_enabled')?'ON':'OFF' ?>
        <input type="checkbox" name="reward_enabled" value="1" <?= $on('reward_enabled')?'checked':'' ?> style="width:18px;height:18px">
      </label>
    </div>
    <?php $rsrc = $val('reward_source') ?: 'my_ad'; ?>
    <label style="font-size:.75rem;color:var(--mut)">Reward source — what the user watches to earn 1 free day</label>
    <select class="sel" name="reward_source" style="width:100%;margin:4px 0 10px">
      <option value="my_ad"  <?= $rsrc==='my_ad'?'selected':'' ?>>🎁 My uploaded Reward Video (upload it in My Ads → "App: Reward Video")</option>
      <option value="link"   <?= $rsrc==='link' ?'selected':'' ?>>🔗 External ad link (URL below)</option>
    </select>
    <label style="font-size:.75rem;color:var(--mut)">Reward ad link (used only when source = External link)</label>
    <input class="sel" name="reward_ad_url" value="<?= ss_admin_h($val('reward_ad_url')) ?>" placeholder="https://…" style="width:100%;margin-top:4px">
    <div style="font-size:.7rem;color:var(--mut);margin-top:6px">Tip: To use your own video, keep source = <b>My uploaded Reward Video</b> and upload an MP4 under <b>My Ads → 🎁 App: Reward Video</b>.</div>
  </div>

  <!-- PAYMENTS -->
  <div class="panel" style="margin-top:14px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <h3 style="margin:0">💳 Payments (buy Premium)</h3>
      <label style="margin-left:auto;display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.75rem;color:var(--mut)">
        <?= $on('payment_enabled')?'ON':'OFF' ?>
        <input type="checkbox" name="payment_enabled" value="1" <?= $on('payment_enabled')?'checked':'' ?> style="width:18px;height:18px">
      </label>
    </div>
    <div class="grid g2">
      <div>
        <label style="font-size:.75rem;color:var(--mut)">Gateway</label>
        <select class="sel" name="payment_gateway" style="width:100%;margin:4px 0 12px">
          <?php foreach (['instamojo'=>'Instamojo (easy approval)','cashfree'=>'Cashfree','razorpay'=>'Razorpay'] as $g=>$gl): ?>
            <option value="<?= $g ?>" <?= $gw===$g?'selected':'' ?>><?= $gl ?></option>
          <?php endforeach; ?>
        </select>
        <label style="font-size:.75rem;color:var(--mut)">Price (₹)</label>
        <input class="sel" type="number" name="subscription_price" value="<?= ss_admin_h($val('subscription_price') ?: '99') ?>" style="width:100%;margin:4px 0 12px">
        <label style="font-size:.75rem;color:var(--mut)">Length (days)</label>
        <input class="sel" type="number" name="subscription_days" value="<?= ss_admin_h($val('subscription_days') ?: '30') ?>" style="width:100%;margin:4px 0 12px">
      </div>
      <div>
        <label style="font-size:.75rem;color:var(--mut)">Razorpay Key ID</label>
        <input class="sel" name="razorpay_key_id" value="<?= ss_admin_h($val('razorpay_key_id')) ?>" style="width:100%;margin:4px 0 10px">
        <label style="font-size:.75rem;color:var(--mut)">Razorpay Secret</label>
        <input class="sel" type="password" name="razorpay_key_secret" value="<?= ss_admin_h($val('razorpay_key_secret')) ?>" style="width:100%;margin:4px 0 10px">
        <label style="font-size:.75rem;color:var(--mut)">Instamojo API Key</label>
        <input class="sel" name="instamojo_api_key" value="<?= ss_admin_h($val('instamojo_api_key')) ?>" style="width:100%;margin:4px 0 10px">
        <label style="font-size:.75rem;color:var(--mut)">Instamojo Auth Token</label>
        <input class="sel" type="password" name="instamojo_auth_token" value="<?= ss_admin_h($val('instamojo_auth_token')) ?>" style="width:100%;margin:4px 0 10px">
      </div>
    </div>
  </div>

  <button class="btn pk" type="submit" style="margin-top:16px;font-size:1rem;padding:12px 28px">💾 Save All</button>
</form>
<?php require __DIR__ . '/_dark_foot.php';
