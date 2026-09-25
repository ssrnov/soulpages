<?php
// =========================================================================
// PAYMENT GATEWAYS — PhonePe, Paytm, PayU, Airpay (+ Razorpay lives in api.php)
// Config JSON in site_settings key 'payment_gateways'. Orders reuse the
// `payments` table: razorpay_order_id column stores the gateway txn id
// (prefixed PAYU_/PPE_/PTM_/APY_), credits_added is set at creation, and
// gw_mark_paid() credits with the same double-credit guard as Razorpay.
// =========================================================================

function gw_defaults() {
    return [
        'razorpay' => ['enabled' => true],
        'payu'     => ['enabled' => false, 'key' => '', 'salt' => '', 'mode' => 'live'],
        'phonepe'  => ['enabled' => false, 'merchant_id' => '', 'salt_key' => '', 'salt_index' => '1', 'mode' => 'live'],
        'paytm'    => ['enabled' => false, 'mid' => '', 'merchant_key' => '', 'website' => 'DEFAULT', 'mode' => 'live'],
        'airpay'   => ['enabled' => false, 'merchant_id' => '', 'username' => '', 'password' => '', 'secret' => '', 'mode' => 'live'],
    ];
}

function gw_config() {
    $saved = json_decode(get_setting('payment_gateways', ''), true);
    $cfg = gw_defaults();
    if (is_array($saved)) {
        foreach ($saved as $k => $v) {
            if (isset($cfg[$k]) && is_array($v)) $cfg[$k] = array_replace($cfg[$k], $v);
        }
    }
    return $cfg;
}

/** Gateways ready to show on the payment page (enabled AND credentials filled). */
function gw_available() {
    $cfg = gw_config();
    $out = [];
    if (!empty($cfg['razorpay']['enabled'])) $out['razorpay'] = ['name' => 'Razorpay', 'icon' => '💳', 'note' => 'Cards · Net-banking · Wallets'];
    if (!empty($cfg['payu']['enabled']) && $cfg['payu']['key'] !== '' && $cfg['payu']['salt'] !== '') $out['payu'] = ['name' => 'PayU', 'icon' => '🟢', 'note' => 'UPI · Cards · Net-banking'];
    if (!empty($cfg['phonepe']['enabled']) && $cfg['phonepe']['merchant_id'] !== '' && $cfg['phonepe']['salt_key'] !== '') $out['phonepe'] = ['name' => 'PhonePe', 'icon' => '🟣', 'note' => 'UPI · PhonePe wallet · Cards'];
    if (!empty($cfg['paytm']['enabled']) && $cfg['paytm']['mid'] !== '' && $cfg['paytm']['merchant_key'] !== '') $out['paytm'] = ['name' => 'Paytm', 'icon' => '🔵', 'note' => 'UPI · Paytm wallet · Cards'];
    if (!empty($cfg['airpay']['enabled']) && $cfg['airpay']['merchant_id'] !== '' && $cfg['airpay']['secret'] !== '') $out['airpay'] = ['name' => 'Airpay', 'icon' => '🟠', 'note' => 'UPI · Cards · Net-banking'];
    return $out;
}

function gw_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    // pay-init/pay-return live in the site root; strip /includes if present
    $dir = preg_replace('#/includes$#', '', $dir);
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/';
}

/** Create the pending order row shared by all gateways. Returns txn id. */
function gw_create_order($user_id, $prefix, $amount_paise, $credits, $coupon_code = '') {
    global $pdo;
    $txn = $prefix . '_' . $user_id . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $pdo->prepare("INSERT INTO payments (user_id, razorpay_order_id, amount, currency, status, credits_added) VALUES (?, ?, ?, 'INR', 'created', ?)")
        ->execute([$user_id, $txn, $amount_paise, $credits]);
    if ($coupon_code !== '') $_SESSION['coupon_for_' . $txn] = $coupon_code;
    return $txn;
}

/** Credit an order exactly once. Returns [ok(bool), message]. */
function gw_mark_paid($txn, $gateway_payment_id = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE razorpay_order_id = ?");
    $stmt->execute([$txn]);
    $row = $stmt->fetch();
    if (!$row) return [false, 'Order not found.'];
    if ($row['status'] === 'captured') return [true, 'Already credited.'];
    $pdo->prepare("UPDATE payments SET razorpay_payment_id = ?, status = 'captured' WHERE razorpay_order_id = ?")
        ->execute([$gateway_payment_id, $txn]);
    add_credits((int)$row['user_id'], max(1, (int)$row['credits_added']));
    if (!empty($_SESSION['coupon_for_' . $txn])) {
        if (function_exists('increment_coupon_usage')) increment_coupon_usage($_SESSION['coupon_for_' . $txn]);
        unset($_SESSION['coupon_for_' . $txn]);
    }
    return [true, 'Credited.'];
}

function gw_mark_failed($txn, $note = '') {
    global $pdo;
    $pdo->prepare("UPDATE payments SET status = 'failed', razorpay_payment_id = ? WHERE razorpay_order_id = ? AND status != 'captured'")
        ->execute([mb_substr($note, 0, 90), $txn]);
}

/** Echo a self-submitting POST form (for form-redirect gateways). */
function gw_autopost($url, $fields, $note = 'Redirecting to secure payment…') {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting…</title></head><body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#fdf2f8;color:#0f172a;">';
    echo '<form id="gwf" method="post" action="' . h($url) . '">';
    foreach ($fields as $k => $v) echo '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    echo '</form><div style="text-align:center;"><div style="font-size:2.4rem;">🔒</div><p>' . h($note) . '</p></div>';
    echo '<script>document.getElementById("gwf").submit();</script></body></html>';
    exit;
}

// ─────────────────────────────────────────────────────────────────────────
// PAYU  (hash-based form redirect — supports UPI out of the box)
// ─────────────────────────────────────────────────────────────────────────

function payu_endpoint($cfg) { return ($cfg['mode'] ?? 'live') === 'test' ? 'https://test.payu.in/_payment' : 'https://secure.payu.in/_payment'; }

function payu_begin($plan_paise, $credits, $user, $coupon) {
    $cfg = gw_config()['payu'];
    $txn = gw_create_order($user['id'], 'PAYU', $plan_paise, $credits, $coupon);
    $amount = number_format($plan_paise / 100, 2, '.', '');
    $pinfo = 'SoulSync Credits x' . $credits;
    $fname = preg_replace('/[^a-zA-Z ]/', '', $user['name'] ?? 'User') ?: 'User';
    $email = $user['email'] ?? 'noreply@soulsyncc.site';
    $base = gw_base_url();
    $hash = strtolower(hash('sha512', implode('|', [$cfg['key'], $txn, $amount, $pinfo, $fname, $email, '', '', '', '', '', '', '', '', '', '', $cfg['salt']])));
    gw_autopost(payu_endpoint($cfg), [
        'key' => $cfg['key'], 'txnid' => $txn, 'amount' => $amount,
        'productinfo' => $pinfo, 'firstname' => $fname, 'email' => $email,
        'phone' => '9999999999',
        'surl' => $base . 'pay-return.php?gw=payu',
        'furl' => $base . 'pay-return.php?gw=payu',
        'hash' => $hash,
    ], 'Redirecting to PayU…');
}

function payu_handle_return() {
    $cfg = gw_config()['payu'];
    $status = $_POST['status'] ?? '';
    $txn = $_POST['txnid'] ?? '';
    if ($txn === '') return [false, '', 'Missing transaction.'];
    // reverse hash: salt|status||||||udf5..udf1|email|firstname|productinfo|amount|txnid|key
    $calc = strtolower(hash('sha512', implode('|', [
        $cfg['salt'], $status, '', '', '', '', '',
        $_POST['udf5'] ?? '', $_POST['udf4'] ?? '', $_POST['udf3'] ?? '', $_POST['udf2'] ?? '', $_POST['udf1'] ?? '',
        $_POST['email'] ?? '', $_POST['firstname'] ?? '', $_POST['productinfo'] ?? '', $_POST['amount'] ?? '', $txn, $cfg['key'],
    ])));
    if (!hash_equals($calc, strtolower($_POST['hash'] ?? ''))) { gw_mark_failed($txn, 'payu-bad-hash'); return [false, $txn, 'Hash verification failed.']; }
    if (strtolower($status) === 'success') { gw_mark_paid($txn, $_POST['mihpayid'] ?? ''); return [true, $txn, 'Payment successful!']; }
    gw_mark_failed($txn, 'payu-' . $status);
    return [false, $txn, 'Payment ' . $status . '.'];
}

// ─────────────────────────────────────────────────────────────────────────
// PHONEPE  (Standard Checkout — PAY_PAGE)
// ─────────────────────────────────────────────────────────────────────────

function phonepe_host($cfg) { return ($cfg['mode'] ?? 'live') === 'test' ? 'https://api-preprod.phonepe.com/apis/pg-sandbox' : 'https://api.phonepe.com/apis/hermes'; }

function phonepe_begin($plan_paise, $credits, $user, $coupon) {
    $cfg = gw_config()['phonepe'];
    $txn = gw_create_order($user['id'], 'PPE', $plan_paise, $credits, $coupon);
    $base = gw_base_url();
    $payload = [
        'merchantId' => $cfg['merchant_id'],
        'merchantTransactionId' => $txn,
        'merchantUserId' => 'U' . $user['id'],
        'amount' => (int)$plan_paise,
        'redirectUrl' => $base . 'pay-return.php?gw=phonepe&txn=' . urlencode($txn),
        'redirectMode' => 'REDIRECT',
        'callbackUrl' => $base . 'pay-return.php?gw=phonepe&txn=' . urlencode($txn),
        'paymentInstrument' => ['type' => 'PAY_PAGE'],
    ];
    $b64 = base64_encode(json_encode($payload));
    $xverify = hash('sha256', $b64 . '/pg/v1/pay' . $cfg['salt_key']) . '###' . ($cfg['salt_index'] ?: '1');
    $ch = curl_init(phonepe_host($cfg) . '/pg/v1/pay');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-VERIFY: ' . $xverify],
        CURLOPT_POSTFIELDS => json_encode(['request' => $b64]),
    ]);
    $res = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    $url = $res['data']['instrumentResponse']['redirectInfo']['url'] ?? '';
    if ($url === '') { gw_mark_failed($txn, 'phonepe-init'); return 'PhonePe error: ' . h($res['message'] ?? 'could not start payment'); }
    header('Location: ' . $url);
    exit;
}

function phonepe_handle_return() {
    $cfg = gw_config()['phonepe'];
    $txn = $_GET['txn'] ?? ($_POST['transactionId'] ?? '');
    if ($txn === '') return [false, '', 'Missing transaction.'];
    // Always confirm with the server-to-server status API
    $path = '/pg/v1/status/' . $cfg['merchant_id'] . '/' . $txn;
    $xverify = hash('sha256', $path . $cfg['salt_key']) . '###' . ($cfg['salt_index'] ?: '1');
    $ch = curl_init(phonepe_host($cfg) . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-VERIFY: ' . $xverify, 'X-MERCHANT-ID: ' . $cfg['merchant_id']],
    ]);
    $res = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    if (($res['code'] ?? '') === 'PAYMENT_SUCCESS') { gw_mark_paid($txn, $res['data']['transactionId'] ?? ''); return [true, $txn, 'Payment successful!']; }
    if (($res['code'] ?? '') === 'PAYMENT_PENDING') return [false, $txn, 'Payment is pending — credits will be added once it completes. Refresh in a minute.'];
    gw_mark_failed($txn, 'phonepe-' . ($res['code'] ?? 'fail'));
    return [false, $txn, 'Payment failed or was cancelled.'];
}

// ─────────────────────────────────────────────────────────────────────────
// PAYTM  (Initiate Transaction + JS Checkout; official checksum algorithm)
// ─────────────────────────────────────────────────────────────────────────

function paytm_host($cfg) { return ($cfg['mode'] ?? 'live') === 'test' ? 'https://securegw-stage.paytm.in' : 'https://securegw.paytm.in'; }

function paytm_encrypt($input, $key) {
    return base64_encode(openssl_encrypt($input, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, '@@@@&&&&####$$$$'));
}
function paytm_decrypt($input, $key) {
    return openssl_decrypt(base64_decode($input), 'AES-128-CBC', $key, OPENSSL_RAW_DATA, '@@@@&&&&####$$$$');
}
function paytm_generate_signature($params, $key) {
    if (is_array($params)) { ksort($params); $params = implode('|', array_values($params)); }
    $salt = substr(str_shuffle('AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz0123456789'), 0, 4);
    $hash = hash('sha256', $params . '|' . $salt) . $salt;
    return paytm_encrypt($hash, $key);
}
function paytm_verify_signature($params, $key, $checksum) {
    if (is_array($params)) { unset($params['CHECKSUMHASH']); ksort($params); $params = implode('|', array_values($params)); }
    $decoded = paytm_decrypt($checksum, $key);
    if ($decoded === false || strlen($decoded) < 4) return false;
    $salt = substr($decoded, -4);
    return hash_equals($decoded, hash('sha256', $params . '|' . $salt) . $salt);
}

function paytm_begin($plan_paise, $credits, $user, $coupon) {
    $cfg = gw_config()['paytm'];
    $txn = gw_create_order($user['id'], 'PTM', $plan_paise, $credits, $coupon);
    $base = gw_base_url();
    $amount = number_format($plan_paise / 100, 2, '.', '');
    $body = [
        'requestType' => 'Payment',
        'mid' => $cfg['mid'],
        'websiteName' => $cfg['website'] ?: 'DEFAULT',
        'orderId' => $txn,
        'callbackUrl' => $base . 'pay-return.php?gw=paytm',
        'txnAmount' => ['value' => $amount, 'currency' => 'INR'],
        'userInfo' => ['custId' => 'U' . $user['id']],
    ];
    $signature = paytm_generate_signature(json_encode($body, JSON_UNESCAPED_SLASHES), $cfg['merchant_key']);
    $post = json_encode(['body' => $body, 'head' => ['signature' => $signature]], JSON_UNESCAPED_SLASHES);
    $url = paytm_host($cfg) . '/theia/api/v1/initiateTransaction?mid=' . urlencode($cfg['mid']) . '&orderId=' . urlencode($txn);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => $post]);
    $res = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    $token = $res['body']['txnToken'] ?? '';
    if ($token === '') { gw_mark_failed($txn, 'paytm-init'); return 'Paytm error: ' . h($res['body']['resultInfo']['resultMsg'] ?? 'could not start payment'); }
    // JS Checkout page
    $mid = h($cfg['mid']);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Paytm…</title></head><body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#fdf2f8;color:#0f172a;"><div style="text-align:center;"><div style="font-size:2.4rem;">🔒</div><p>Opening Paytm…</p></div>';
    echo '<script src="' . h(paytm_host($cfg)) . '/merchantpgpui/checkoutjs/merchants/' . $mid . '.js" crossorigin="anonymous"></script>';
    echo '<script>
      document.addEventListener("DOMContentLoaded", function(){
        var cfg = { root:"", flow:"DEFAULT", data:{ orderId:' . json_encode($txn) . ', token:' . json_encode($token) . ', tokenType:"TXN_TOKEN", amount:' . json_encode($amount) . ' }, handler:{ notifyMerchant:function(){} } };
        if (window.Paytm && Paytm.CheckoutJS) { Paytm.CheckoutJS.init(cfg).then(function(){ Paytm.CheckoutJS.invoke(); }).catch(function(e){ document.body.innerHTML += "<p>"+e+"</p>"; }); }
      });
    </script></body></html>';
    exit;
}

function paytm_handle_return() {
    $cfg = gw_config()['paytm'];
    $params = $_POST;
    $txn = $params['ORDERID'] ?? '';
    if ($txn === '') return [false, '', 'Missing order.'];
    $checksum = $params['CHECKSUMHASH'] ?? '';
    if ($checksum === '' || !paytm_verify_signature($params, $cfg['merchant_key'], $checksum)) {
        gw_mark_failed($txn, 'paytm-bad-checksum');
        return [false, $txn, 'Checksum verification failed.'];
    }
    if (($params['STATUS'] ?? '') === 'TXN_SUCCESS') { gw_mark_paid($txn, $params['TXNID'] ?? ''); return [true, $txn, 'Payment successful!']; }
    if (($params['STATUS'] ?? '') === 'PENDING') return [false, $txn, 'Payment is pending — credits will be added once it completes.'];
    gw_mark_failed($txn, 'paytm-' . ($params['RESPCODE'] ?? 'fail'));
    return [false, $txn, $params['RESPMSG'] ?? 'Payment failed.'];
}

// ─────────────────────────────────────────────────────────────────────────
// AIRPAY  (form redirect per the Airpay PHP kit)
// ─────────────────────────────────────────────────────────────────────────

function airpay_begin($plan_paise, $credits, $user, $coupon) {
    $cfg = gw_config()['airpay'];
    $txn = gw_create_order($user['id'], 'APY', $plan_paise, $credits, $coupon);
    // Airpay order ids must be numeric-ish & short — use payments row lookup via alldata
    $orderid = preg_replace('/\D/', '', $txn);
    $orderid = substr($orderid ?: (string)time(), -12);
    $_SESSION['airpay_order_' . $orderid] = $txn;
    $amount = number_format($plan_paise / 100, 2, '.', '');
    $email = $user['email'] ?? 'noreply@soulsyncc.site';
    $fname = preg_replace('/[^a-zA-Z ]/', '', $user['name'] ?? 'User') ?: 'User';
    $lname = 'SoulSync';
    $privatekey = hash('sha256', $cfg['secret'] . '@' . $cfg['username'] . ':|:' . $cfg['password']);
    $keysha = hash('sha256', $cfg['username'] . '~:~' . $cfg['password']);
    $alldata = $email . $fname . $lname . '' . '' . '' . '' . $amount . $orderid;
    $checksum = hash('sha256', $keysha . '@' . $alldata . date('Y-m-d'));
    gw_autopost('https://payments.airpay.co.in/pay/index.php', [
        'privatekey' => $privatekey, 'mercid' => $cfg['merchant_id'], 'currency' => '356', 'isocurrency' => 'INR',
        'orderid' => $orderid, 'amount' => $amount,
        'buyerEmail' => $email, 'buyerPhone' => '9999999999',
        'buyerFirstName' => $fname, 'buyerLastName' => $lname,
        'buyerAddress' => '', 'buyerCity' => '', 'buyerState' => '', 'buyerCountry' => 'India', 'buyerPinCode' => '',
        'checksum' => $checksum, 'chmod' => '',
    ], 'Redirecting to Airpay…');
}

function airpay_handle_return() {
    $orderid = $_POST['TRANSACTIONID'] ?? ($_POST['orderid'] ?? '');
    $status = strtolower($_POST['TRANSACTIONSTATUS'] ?? ($_POST['TRANSACTIONPAYMENTSTATUS'] ?? ''));
    $apid = $_POST['APTRANSACTIONID'] ?? '';
    $txn = $_SESSION['airpay_order_' . $orderid] ?? '';
    if ($txn === '') return [false, '', 'Order not found in session — if money was deducted, contact support with your transaction id.'];
    if (in_array($status, ['success', '200'], true) || ($_POST['TRANSACTIONSTATUS'] ?? '') === '200') {
        gw_mark_paid($txn, $apid);
        return [true, $txn, 'Payment successful!'];
    }
    gw_mark_failed($txn, 'airpay-' . $status);
    return [false, $txn, 'Payment failed or was cancelled.'];
}
