<?php
// Gateway return/callback handler — verifies the payment server-side,
// credits exactly once (gw_mark_paid guard), then bounces to payment.php.
require_once 'includes/functions.php';
require_once 'includes/gateways.php';

$gw = $_GET['gw'] ?? '';
$ok = false; $txn = ''; $msg = 'Unknown gateway.';

switch ($gw) {
    case 'payu':    [$ok, $txn, $msg] = payu_handle_return(); break;
    case 'phonepe': [$ok, $txn, $msg] = phonepe_handle_return(); break;
    case 'paytm':   [$ok, $txn, $msg] = paytm_handle_return(); break;
    case 'airpay':  [$ok, $txn, $msg] = airpay_handle_return(); break;
}

redirect('payment.php?status=' . ($ok ? 'success' : 'failed') . '&msg=' . urlencode($msg));
