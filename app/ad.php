<?php
// Serves a single in-app ad placement as a real web page so ad-network scripts
// (Adsterra etc.) load with a proper referrer. The app's AdBanner WebView loads
// this URL: ad.php?p=home|games|memories|interstitial
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$p = preg_replace('/[^a-z_]/', '', strtolower($_GET['p'] ?? 'home'));
$allowed = ['home', 'games', 'memories', 'interstitial'];
if (!in_array($p, $allowed, true)) $p = 'home';

$adsOn = (int)ss_setting($pdo, 'ads_enabled', '0') === 1
      && (int)ss_setting($pdo, 'adsterra_app_enabled', '0') === 1;
$toggle = (int)ss_setting($pdo, 'ad_' . ($p === 'home' ? 'home' : ($p === 'games' ? 'games' : ($p === 'memories' ? 'memories' : 'interstitial'))), '0') === 1;
$code = ss_setting($pdo, 'ad_app_' . $p . '_code', '');

header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>html,body{margin:0;padding:0;background:transparent;overflow:hidden;
display:flex;align-items:center;justify-content:center;min-height:100%}</style>
</head><body>
<?php if ($adsOn && $toggle && trim($code) !== '') { echo $code; } ?>
</body></html>
