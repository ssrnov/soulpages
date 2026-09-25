<?php
// SoulSync subscription / premium purchase page.
// The app opens this in a browser: subscribe.php?t=<signed-token>
// Supports Razorpay (checkout) and Instamojo (redirect). Keys come from the
// admin → Monetization page. On success the user is marked premium.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$price = (int)ss_setting($pdo, 'subscription_price', '99');
$days  = (int)ss_setting($pdo, 'subscription_days', '30');
$gw    = ss_setting($pdo, 'payment_gateway', 'instamojo');
$enabled = (int)ss_setting($pdo, 'payment_enabled', '0') === 1;

function done_page($ok, $msg) {
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<div style="font-family:Inter,system-ui,sans-serif;background:#0f0b1e;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px">'
       . '<div><div style="font-size:3rem">' . ($ok ? '✅' : '⚠️') . '</div>'
       . '<h2>' . htmlspecialchars($msg) . '</h2>'
       . '<p style="color:#aaa">You can close this page and return to SoulSync.</p></div></div>';
    exit;
}

function grant_premium($pdo, $uid, $days) {
    $u = $pdo->prepare("SELECT premium_until FROM users WHERE id=?"); $u->execute([$uid]); $row = $u->fetch();
    $base = (!empty($row['premium_until']) && strtotime($row['premium_until']) > time()) ? $row['premium_until'] : date('Y-m-d H:i:s');
    $until = date('Y-m-d H:i:s', strtotime($base) + $days * 86400);
    $pdo->prepare("UPDATE users SET is_premium=1, premium_until=? WHERE id=?")->execute([$until, $uid]);
    return $until;
}

// ── Razorpay verify (checkout success) ──
if (isset($_POST['razorpay_payment_id'])) {
    $uid = ss_verify_subscribe_token($_POST['t'] ?? '');
    if (!$uid) done_page(false, 'Session expired. Please try again from the app.');
    $secret = ss_setting($pdo, 'razorpay_key_secret', '');
    $expected = hash_hmac('sha256', ($_POST['razorpay_order_id'] ?? '') . '|' . $_POST['razorpay_payment_id'], $secret);
    if (hash_equals($expected, $_POST['razorpay_signature'] ?? '')) {
        grant_premium($pdo, $uid, $days);
        done_page(true, 'Payment successful — Premium activated!');
    }
    done_page(false, 'Payment could not be verified.');
}

// ── Instamojo redirect back ──
if (isset($_GET['instamojo'])) {
    $uid = ss_verify_subscribe_token($_GET['t'] ?? '');
    if (!$uid) done_page(false, 'Session expired.');
    $reqId = $_GET['payment_request_id'] ?? '';
    $payId = $_GET['payment_id'] ?? '';
    $apiKey = ss_setting($pdo, 'instamojo_api_key', '');
    $token  = ss_setting($pdo, 'instamojo_auth_token', '');
    if ($reqId && $payId) {
        $ch = curl_init("https://www.instamojo.com/api/1.1/payment-requests/$reqId/$payId/");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ["X-Api-Key: $apiKey", "X-Auth-Token: $token"]]);
        $resp = json_decode((string)curl_exec($ch), true); curl_close($ch);
        $status = $resp['payment_request']['payment']['status'] ?? '';
        if ($status === 'Credit') { grant_premium($pdo, $uid, $days); done_page(true, 'Payment successful — Premium activated!'); }
    }
    done_page(false, 'Payment not completed.');
}

// ── Show the purchase page ──
$uid = ss_verify_subscribe_token($_GET['t'] ?? '');
if (!$enabled) done_page(false, 'Subscriptions are not available right now.');
if (!$uid) done_page(false, 'Invalid or expired link. Open this from the SoulSync app.');
$user = $pdo->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$uid]); $user = $user->fetch();
if (!$user) done_page(false, 'User not found.');

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$self  = $proto . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
$tok   = $_GET['t'];

// Create the order/request up front.
$razorOrderId = ''; $instamojoUrl = '';
if ($gw === 'razorpay') {
    $kid = ss_setting($pdo, 'razorpay_key_id', ''); $ksec = ss_setting($pdo, 'razorpay_key_secret', '');
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => "$kid:$ksec", CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['amount' => $price * 100, 'currency' => 'INR', 'payment_capture' => 1])]);
    $o = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $razorOrderId = $o['id'] ?? '';
} elseif ($gw === 'instamojo') {
    $apiKey = ss_setting($pdo, 'instamojo_api_key', ''); $token = ss_setting($pdo, 'instamojo_auth_token', '');
    $redirect = $self . '?instamojo=1&t=' . urlencode($tok);
    $ch = curl_init('https://www.instamojo.com/api/1.1/payment-requests/');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ["X-Api-Key: $apiKey", "X-Auth-Token: $token"],
        CURLOPT_POSTFIELDS => http_build_query(['purpose' => 'SoulSync Premium', 'amount' => $price,
            'redirect_url' => $redirect, 'allow_repeated_payments' => 'false',
            'buyer_name' => $user['name'], 'email' => $user['email'] ?: 'user@soulsync.app'])]);
    $r = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $instamojoUrl = $r['payment_request']['longurl'] ?? '';
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SoulSync Premium 💜</title>
<style>
  body{margin:0;font-family:'Inter',system-ui,sans-serif;background:linear-gradient(135deg,#1a0b2e,#2d0f38);color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .box{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:24px;padding:32px;max-width:360px;width:100%;text-align:center}
  h1{font-size:1.5rem;margin:10px 0}
  .price{font-size:2.6rem;font-weight:800;margin:14px 0 4px}
  .price span{font-size:1rem;color:#c4b5e0;font-weight:400}
  ul{text-align:left;color:#d6cbe8;font-size:.9rem;line-height:1.9;margin:18px 0;padding-left:20px}
  .pay{display:block;width:100%;margin-top:12px;background:linear-gradient(135deg,#a855f7,#ec4899);color:#fff;border:none;font-weight:800;font-size:1.05rem;padding:15px;border-radius:14px;cursor:pointer;text-decoration:none}
</style></head>
<body>
  <div class="box">
    <div style="font-size:3rem">💜</div>
    <h1>SoulSync Premium</h1>
    <div class="price">₹<?= $price ?> <span>/ <?= $days ?> days</span></div>
    <ul>
      <li>✨ No ads, anywhere</li>
      <li>💬 Full chat & memories</li>
      <li>🎮 All couple games</li>
      <li>🔋 Full partner insights</li>
    </ul>
    <?php if ($gw === 'razorpay' && $razorOrderId): ?>
      <button class="pay" id="payBtn">Pay ₹<?= $price ?></button>
      <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
      <script>
      document.getElementById('payBtn').onclick = function(){
        var rzp = new Razorpay({
          key: <?= json_encode(ss_setting($pdo,'razorpay_key_id','')) ?>,
          order_id: <?= json_encode($razorOrderId) ?>,
          amount: <?= $price*100 ?>, currency: 'INR',
          name: 'SoulSync Premium', description: '<?= $days ?> days',
          handler: function(r){
            var f=document.createElement('form'); f.method='POST'; f.action='';
            var add=function(n,v){var i=document.createElement('input');i.name=n;i.value=v;f.appendChild(i);};
            add('razorpay_payment_id',r.razorpay_payment_id);
            add('razorpay_order_id',r.razorpay_order_id);
            add('razorpay_signature',r.razorpay_signature);
            add('t', <?= json_encode($tok) ?>);
            document.body.appendChild(f); f.submit();
          }
        });
        rzp.open();
      };
      </script>
    <?php elseif ($gw === 'instamojo' && $instamojoUrl): ?>
      <a class="pay" href="<?= htmlspecialchars($instamojoUrl) ?>">Pay ₹<?= $price ?></a>
    <?php else: ?>
      <p style="color:#f8b4b4">Payment gateway is not configured yet. Add keys in the admin panel.</p>
    <?php endif; ?>
  </div>
</body></html>
