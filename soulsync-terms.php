<?php
// SoulSync app — Terms & Conditions + Privacy. Opened inside the app (WebView)
// and on the web. Dependency-free so it loads fast anywhere.
$updated = 'July 2026';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SoulSync — Terms &amp; Conditions</title>
<style>
  :root{--pk:#e8467c;--pk2:#7c3aed;--bg:#fff;--tx:#1f2430;--mut:#6b7280;--line:#eef0f4;}
  @media (prefers-color-scheme: dark){:root{--bg:#0f1220;--tx:#e8ecf6;--mut:#98a2b8;--line:#232a3d;}}
  *{box-sizing:border-box}
  html,body{margin:0;background:var(--bg);color:var(--tx);font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;line-height:1.6}
  .wrap{max-width:760px;margin:0 auto;padding:26px 20px 60px}
  .badge{display:inline-flex;align-items:center;gap:8px;font-weight:800;font-size:1.25rem}
  .badge .dot{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--pk2),var(--pk));display:flex;align-items:center;justify-content:center;color:#fff}
  h1{font-size:1.5rem;margin:18px 0 2px}
  .upd{color:var(--mut);font-size:.82rem;margin-bottom:22px}
  h2{font-size:1.05rem;margin:26px 0 8px;color:var(--pk)}
  p,li{color:var(--tx);font-size:.94rem}
  .mut{color:var(--mut)}
  a{color:var(--pk)}
  .card{border:1px solid var(--line);border-radius:16px;padding:16px 18px;margin-top:14px}
</style>
</head>
<body>
<div class="wrap">
  <div class="badge"><span class="dot">💜</span> SoulSync</div>
  <h1>Terms &amp; Conditions</h1>
  <div class="upd">Last updated: <?= htmlspecialchars($updated) ?></div>

  <p>Welcome to SoulSync. By creating an account or using the app, you agree to these Terms &amp; Conditions and the Privacy practices below. Please read them carefully.</p>

  <h2>1. Who can use SoulSync</h2>
  <p>You must be at least 18 years old to use SoulSync. By signing up you confirm the information you provide is accurate and belongs to you.</p>

  <h2>2. Your account</h2>
  <p>You are responsible for keeping your password safe and for all activity on your account. One account is meant for one person — do not share your login.</p>

  <h2>3. Connecting with a partner</h2>
  <p>Connecting with a partner is <b>optional and mutual</b>. A connection only happens when the other person <b>accepts</b> your request. Either partner can disconnect at any time. Features that involve a partner (location, activity, notifications) work only when <b>both</b> people have connected and each has chosen to share.</p>

  <h2>4. Permissions &amp; sharing</h2>
  <p>SoulSync may ask for permissions such as notifications, location and app‑usage access. These power only the features you turn on, and are shared with a connected partner only when you enable that sharing. You can revoke any permission from your phone settings at any time. SoulSync must not be used to monitor anyone without their knowledge and consent.</p>

  <h2>5. Content &amp; conduct</h2>
  <p>You own the content you add (notes, memories, photos). Do not upload anything illegal, hateful, or that infringes someone else's rights. We may remove content or suspend accounts that break these rules.</p>

  <h2>6. Discover (optional)</h2>
  <p>Discover is an optional way to meet new people. If you enable it, an approximate location and the profile details you choose may be shown to others. You can pause or disable Discover anytime. Chatting is unlocked only after a mutual match.</p>

  <h2>7. Ads &amp; premium</h2>
  <p>SoulSync may show ads and offer optional premium features. Prices and availability may change.</p>

  <h2>8. Privacy</h2>
  <p>We store the minimum data needed to run the app. Location is approximate where possible, and sensitive sharing is off unless you turn it on. We do not sell your personal data. You may request deletion of your account and data by contacting us.</p>

  <h2>9. Termination</h2>
  <p>You can stop using SoulSync and delete your account anytime. We may suspend accounts that violate these terms.</p>

  <h2>10. Changes</h2>
  <p>We may update these terms. Continued use after an update means you accept the revised terms.</p>

  <div class="card">
    <p style="margin:0"><b>Questions?</b><br><span class="mut">Contact us through our</span> <a href="support.php">support page</a></p>
  </div>

  <p class="mut" style="margin-top:26px;font-size:.82rem">© <?= date('Y') ?> SoulSync · soulsyncc.site</p>
</div>
</body>
</html>
