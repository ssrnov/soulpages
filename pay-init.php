<?php
// Starts a payment on the chosen gateway (PayU / PhonePe / Paytm / Airpay).
// Razorpay keeps its existing JS-checkout flow via api.php.
require_once 'includes/functions.php';
require_once 'includes/gateways.php';

if (!is_logged_in()) redirect('login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) redirect('payment.php');

$user = get_user_profile($_SESSION['user_id']);
$gw = $_POST['gw'] ?? '';
$plan_id = $_POST['plan_id'] ?? '';
$coupon = strtoupper(trim($_POST['coupon'] ?? ''));

$plans = get_credit_plans(true);
$plan = null;
foreach ($plans as $p) { if ((string)$p['id'] === (string)$plan_id) { $plan = $p; break; } }
if (!$plan) redirect('payment.php?status=error&msg=' . urlencode('Plan not found.'));

$amount_paise = plan_effective_paise($plan);
if ($coupon !== '' && function_exists('validate_coupon')) {
    $cp = validate_coupon($coupon, $amount_paise);
    if (is_array($cp) && function_exists('coupon_discount_paise')) {
        $amount_paise = max(100, $amount_paise - coupon_discount_paise($cp, $amount_paise));
    } else {
        $coupon = '';
    }
}
$credits = max(1, (int)$plan['credits']);

$available = gw_available();
if (!isset($available[$gw])) redirect('payment.php?status=error&msg=' . urlencode('This payment method is not available right now.'));

switch ($gw) {
    case 'payu':    payu_begin($amount_paise, $credits, $user, $coupon); break;
    case 'phonepe': $err = phonepe_begin($amount_paise, $credits, $user, $coupon); break;
    case 'paytm':   $err = paytm_begin($amount_paise, $credits, $user, $coupon); break;
    case 'airpay':  airpay_begin($amount_paise, $credits, $user, $coupon); break;
    default: redirect('payment.php');
}
// begin() functions exit on success; reaching here means an error string
redirect('payment.php?status=error&msg=' . urlencode(strip_tags($err ?? 'Could not start payment.')));
