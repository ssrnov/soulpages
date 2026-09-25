<?php
// Website ad helper. Reads ad settings from the SoulSync DB (managed in the
// SoulSync admin → Monetization) and returns ad HTML per placement.
// Available on every soulpages page (required from functions.php).

function ss_ads_conf() {
    static $c = null;
    if ($c !== null) return $c;
    $c = ['on' => false, 'code' => '', 'p' => [], 'house' => []];
    try {
        $pdo2 = new PDO(
            "mysql:host=localhost;dbname=looprsi1_SoulSync;charset=utf8mb4",
            'looprsi1_ssrnov', 'Jayshreeram@12345',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_TIMEOUT => 3]
        );
        $rows = $pdo2->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (is_array($rows)) {
            $c['p']    = $rows;
            $c['on']   = (($rows['ads_enabled'] ?? '0') === '1') && (($rows['ad_web_enabled'] ?? '0') === '1');
            $c['code'] = $rows['adsterra_web_code'] ?? '';
        }
        // Your own image ads (house ads).
        $h = $pdo2->query("SELECT title, image, link, placement FROM house_ads WHERE enabled = 1 ORDER BY id DESC");
        if ($h) $c['house'] = $h->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}
    return $c;
}

// HTML for your own image ads matching a placement ('all' always shows).
function ss_house_ads($placement) {
    $c = ss_ads_conf();
    if (!$c['on'] || empty($c['house'])) return '';
    $out = '';
    foreach ($c['house'] as $a) {
        if (($a['placement'] ?? '') !== $placement && ($a['placement'] ?? '') !== 'all') continue;
        $img = '<img src="' . htmlspecialchars($a['image']) . '" alt="' . htmlspecialchars($a['title'] ?? '')
             . '" style="width:100%;height:auto;display:block">';
        // A clean, centered card with a small "Ad" label so it looks intentional
        // and consistent across the site (no random redirect/popup ads).
        $out .= '<div style="max-width:728px;margin:14px auto;border:1px solid rgba(148,163,184,.18);'
              . 'border-radius:12px;overflow:hidden;background:#fff">'
              . '<div style="font-size:10px;color:#94a3b8;font-weight:600;padding:4px 10px 0">Ad</div>'
              . '<div style="padding:4px">'
              . (!empty($a['link'])
                  ? '<a href="' . htmlspecialchars($a['link']) . '" target="_blank" rel="noopener nofollow" style="display:block;border-radius:8px;overflow:hidden">' . $img . '</a>'
                  : '<div style="border-radius:8px;overflow:hidden">' . $img . '</div>')
              . '</div></div>';
    }
    return $out;
}

// Code for a specific placement: its own box, else the legacy fallback code.
function ss_ad_code($placement) {
    $c = ss_ads_conf();
    $own = trim($c['p']['ad_web_' . $placement . '_code'] ?? '');
    if ($own !== '') return $own;
    return trim($c['code']); // legacy fallback
}

// The chosen provider for a placement ('adsterra' default, 'image' = house ad).
function ss_ad_provider($placement) {
    $c = ss_ads_conf();
    return $c['p']['ad_web_' . $placement . '_provider'] ?? 'adsterra';
}

// HTML for a placement, following the admin's provider choice for that spot:
//   'image' → your uploaded image ad ("My Ad")
//   anything else → the pasted network banner code (Adsterra/Monetag/…).
// Full-screen redirect formats (popunder / social bar) stay disabled via
// ss_web_overlays(), so only clean in-page banners + the sticky bar ever show.
function ss_ad_html($placement) {
    // Website shows ONLY your uploaded image ads. Pasted network code (Adsterra
    // "highperformanceformat" invoke.js etc.) is NOT rendered — that code is what
    // injects the redirect/popup ads on page load. Image ads never redirect.
    return ss_house_ads($placement);
}

// Returns the ad HTML for a placement if enabled, else ''.
function ss_web_ad($placement) {
    $c = ss_ads_conf();
    if (!$c['on']) return '';
    if (($c['p']['ad_web_' . $placement] ?? '0') !== '1') return '';
    $html = ss_ad_html($placement);
    if (trim($html) === '') return '';
    return '<div class="ss-ad-slot" style="text-align:center;margin:18px auto;max-width:100%;overflow:hidden">' . $html . '</div>';
}

// Site-wide sticky bottom banner (call once near </body>).
function ss_web_sticky() {
    $c = ss_ads_conf();
    if (!$c['on']) return '';
    if (($c['p']['ad_web_sticky'] ?? '0') !== '1') return '';
    $html = ss_ad_html('sticky');
    if (trim($html) === '') return '';
    // Hard cap the height so a tall banner image can never fill the screen.
    return '<style>.ss-sticky-bar{position:fixed;bottom:0;left:0;right:0;z-index:9998;background:rgba(255,255,255,0.97);box-shadow:0 -2px 14px rgba(0,0,0,0.12);text-align:center;padding:4px 34px 4px 4px;max-height:84px;overflow:hidden}'
         . '.ss-sticky-bar img{max-height:64px !important;width:auto !important;max-width:96% !important;height:auto !important;border-radius:8px}'
         . '.ss-sticky-bar iframe{max-height:64px !important}</style>'
         . '<div class="ss-sticky-bar">' . $html
         . '<span onclick="this.parentNode.style.display=\'none\'" style="position:absolute;top:6px;right:8px;cursor:pointer;color:#555;font-size:16px;line-height:20px;background:#eee;border-radius:50%;width:22px;height:22px;text-align:center">×</span></div>';
}

// Full-screen / floating ads (Popunder, Social Bar) — only if their toggle is ON.
// Call once near </body>. Turn OFF in admin to stop unwanted full-screen ads.
function ss_web_overlays() {
    // Popunder / social-bar overlays are DISABLED on purpose. These are the
    // networks that hijack a click and redirect the page to spammy sites, so
    // we never render them regardless of the admin toggle. Only clean banner
    // and sticky ads are allowed on the website.
    return '';
}
