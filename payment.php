<?php
require_once 'includes/functions.php';
require_once 'includes/gateways.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user = get_user_profile($user_id);
$credits = get_user_credits($user_id);
$page_count = get_user_page_count($user_id);
$price_paise = get_price_per_page();
$price_rupees = $price_paise / 100;
$razorpay_key = get_setting('razorpay_key_id', RAZORPAY_KEY_ID);
$plans = get_credit_plans(true);
$sale = get_flash_sale();
$gateways = gw_available();
$csrf = generate_csrf_token();
$status = $_GET['status'] ?? '';
$status_msg = trim($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Buy Credits - <?= h(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], heading: ['Outfit', 'sans-serif'] } } }
        }
    </script>
    <style>
        body {
            background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important;
            background-attachment: fixed;
            color: #0f172a;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(236, 72, 153, 0.12);
        }
        .sp-gradient-text { background: linear-gradient(135deg, #be185d 0%, #db2777 40%, #e11d48 70%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeInUp 0.6s ease-out forwards; }
        .fade-in-delay { animation: fadeInUp 0.6s ease-out 0.15s forwards; opacity: 0; }
        .fade-in-delay2 { animation: fadeInUp 0.6s ease-out 0.3s forwards; opacity: 0; }
        .gw-opt { display:flex; align-items:center; gap:14px; width:100%; text-align:left; background:#fff; border:1.5px solid rgba(236,72,153,0.15); border-radius:16px; padding:14px 16px; cursor:pointer; transition:all .2s; }
        .gw-opt:hover { border-color:#db2777; transform:translateY(-1px); box-shadow:0 8px 22px rgba(219,39,119,0.12); }
    </style>
</head>
<body class="font-sans min-h-screen flex flex-col antialiased">

    <!-- Emoji Rain -->
    <?php render_emoji_rain(); ?>

    <header style="background: rgba(255,255,255,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(236,72,153,0.12);" class="sticky top-0 z-40 h-16 flex items-center">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full flex items-center justify-between">
            <a href="index.php" class="text-2xl font-bold font-heading tracking-wider sp-gradient-text"><?= h(SITE_NAME) ?></a>
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-xs font-semibold text-slate-600 hover:text-pink-600 transition">Dashboard</a>
                <?php if (is_admin()): ?>
                    <a href="admin/index.php" class="text-xs font-semibold text-purple-500 hover:text-purple-400 transition">Admin Portal 🛠️</a>
                <?php endif; ?>
                <a href="logout.php" class="text-xs font-semibold text-slate-400 hover:text-pink-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main -->
    <main class="flex-grow flex items-center justify-center p-4 py-10">
        <div class="w-full max-w-3xl">
            <!-- Title -->
            <div class="text-center mb-8 fade-in">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-pink-500/15 to-purple-500/15 border border-pink-500/20 mb-4">
                    <span class="text-3xl">✨</span>
                </div>
                <h1 class="text-3xl font-extrabold font-heading text-slate-900 mb-2">Get More Page Credits</h1>
                <p class="text-slate-500 text-sm">₹10 = 1 credit · Normal page = 1 credit · Premium = 5 credits</p>
            </div>

            <?php if ($status === 'success'): ?>
            <div class="rounded-2xl p-5 mb-6 text-center fade-in" style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.35);">
                <div class="text-3xl mb-1">🎉</div>
                <p class="font-extrabold text-emerald-700">Payment successful — credits added!</p>
                <p class="text-xs text-slate-500 mt-1"><?= h($status_msg) ?></p>
                <a href="create.php" class="inline-block mt-3 text-sm font-bold text-white px-8 py-2.5 rounded-full" style="background:linear-gradient(135deg,#059669,#10b981);">Create a Page →</a>
            </div>
            <?php elseif ($status === 'failed' || $status === 'error'): ?>
            <div class="rounded-2xl p-5 mb-6 text-center fade-in" style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.3);">
                <div class="text-3xl mb-1">😕</div>
                <p class="font-extrabold text-red-600">Payment didn't go through</p>
                <p class="text-xs text-slate-500 mt-1"><?= h($status_msg ?: 'No money was deducted — you can try again.') ?></p>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-3 mb-6 fade-in-delay">
                <div class="glass-card rounded-2xl p-4 text-center">
                    <p class="text-2xl font-extrabold font-heading text-pink-600"><?= $page_count ?></p>
                    <p class="text-xs text-slate-500 mt-1">Pages Created</p>
                </div>
                <div class="glass-card rounded-2xl p-4 text-center">
                    <p class="text-2xl font-extrabold font-heading text-purple-600"><?= $credits ?></p>
                    <p class="text-xs text-slate-500 mt-1">Credits Left</p>
                </div>
                <div class="glass-card rounded-2xl p-4 text-center">
                    <p class="text-2xl font-extrabold font-heading text-emerald-600">₹<?= number_format($price_rupees) ?></p>
                    <p class="text-xs text-slate-500 mt-1">Per Credit</p>
                </div>
            </div>

            <?php if ($sale['active']): ?>
            <!-- Flash sale banner -->
            <div class="rounded-2xl p-4 mb-5 text-center fade-in-delay" style="background:linear-gradient(135deg, rgba(245,158,11,0.12), rgba(236,72,153,0.1)); border:1px solid rgba(245,158,11,0.4);">
                <span class="text-sm font-extrabold text-amber-600">⚡ <?= h($sale['name']) ?> — <?= (int)$sale['percent'] ?>% OFF on all plans!</span>
            </div>
            <?php endif; ?>

            <!-- Plans -->
            <div class="grid gap-4 fade-in-delay2 <?= count($plans) >= 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-' . max(1, count($plans)) ?>">
                <?php foreach ($plans as $p):
                    $orig = $p['price_paise'] / 100;
                    $pay = plan_effective_paise($p) / 100;
                    $per = $pay / max(1, $p['credits']);
                    $has_cut = $pay < $orig;
                ?>
                <div class="glass-card rounded-3xl p-6 text-center relative flex flex-col" style="<?= $p['badge'] !== '' ? 'border:1.5px solid rgba(219,39,119,0.45); box-shadow:0 12px 34px rgba(219,39,119,0.12);' : '' ?>">
                    <?php if ($p['badge'] !== ''): ?>
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 text-[10px] font-bold uppercase tracking-wider bg-gradient-to-r from-pink-500 to-purple-500 text-white px-3 py-1 rounded-full whitespace-nowrap"><?= h($p['badge']) ?></span>
                    <?php endif; ?>
                    <h2 class="text-base font-bold text-slate-900 mt-2"><?= h($p['name']) ?></h2>
                    <div class="mt-2">
                        <?php if ($has_cut): ?><span class="text-sm text-slate-400 line-through mr-1.5">₹<?= number_format($orig) ?></span><?php endif; ?>
                        <span class="text-3xl font-extrabold font-heading sp-gradient-text">₹<?= number_format($pay, $pay == floor($pay) ? 0 : 2) ?></span>
                    </div>
                    <p class="text-sm text-slate-700 mt-1 font-semibold">✨ <?= (int)$p['credits'] ?> Credit<?= $p['credits'] > 1 ? 's' : '' ?></p>
                    <p class="text-[11px] text-slate-400 mt-1">₹<?= number_format($per, $per == floor($per) ? 0 : 2) ?> per credit</p>
                    <?php if (!empty($p['features'])): ?>
                    <ul class="mt-3 space-y-1 text-left mx-auto">
                        <?php foreach ($p['features'] as $ft): ?>
                        <li class="text-[11px] text-slate-500"><span class="text-emerald-500">✓</span> <?= h($ft) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <button onclick="chooseGateway('<?= h($p['id']) ?>', this)"
                            class="pay-btn mt-5 w-full bg-gradient-to-r from-pink-500 to-purple-500 hover:from-pink-400 hover:to-purple-400 text-white font-bold text-xs py-3 rounded-xl shadow-lg shadow-pink-500/20 transition-all hover:-translate-y-0.5">
                        Buy Now →
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Coupon -->
            <div class="glass-card rounded-2xl p-5 mt-6 fade-in-delay2">
                <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">🎟 Have a coupon?</label>
                <div class="flex gap-2">
                    <input id="couponInput" class="flex-1 bg-white border border-pink-500/20 rounded-xl px-4 py-3 text-sm text-slate-900 outline-none focus:border-pink-500" placeholder="Enter coupon code" style="text-transform:uppercase;">
                    <button onclick="applyCoupon()" class="bg-slate-100 border border-slate-200 text-slate-700 font-bold text-xs px-5 rounded-xl hover:bg-pink-50 transition">Apply</button>
                </div>
                <p id="couponStatus" class="text-xs mt-2 min-h-[16px]"></p>
            </div>

            <div class="glass-card rounded-2xl p-5 mt-4 fade-in-delay2">
                <ul class="grid sm:grid-cols-2 gap-2">
                    <li class="flex items-center gap-2 text-xs text-slate-600"><span class="text-emerald-500">✓</span> Normal page = 1 credit · Premium = 5</li>
                    <li class="flex items-center gap-2 text-xs text-slate-600"><span class="text-emerald-500">✓</span> Photo, video &amp; voice uploads</li>
                    <li class="flex items-center gap-2 text-xs text-slate-600"><span class="text-emerald-500">✓</span> All animations &amp; effects included</li>
                    <li class="flex items-center gap-2 text-xs text-slate-600"><span class="text-emerald-500">✓</span> Visitor reactions &amp; reply system</li>
                </ul>
                <p class="text-center text-xs text-slate-400 mt-4">🔒 100% secure payments — UPI, Cards, Net Banking &amp; Wallets</p>
            </div>
        </div>
    </main>

    <!-- Gateway chooser modal -->
    <div id="gwModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(15,23,42,0.5); backdrop-filter:blur(6px);">
        <div class="glass-card rounded-3xl p-6 w-full max-w-sm" style="background:rgba(255,255,255,0.97);">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold font-heading text-slate-900">Choose payment method</h3>
                <button onclick="closeGw()" class="text-slate-400 hover:text-slate-700 text-xl leading-none">✕</button>
            </div>
            <div class="space-y-3">
                <?php foreach ($gateways as $gk => $g): ?>
                <button class="gw-opt" onclick="startPay('<?= h($gk) ?>')">
                    <span class="text-2xl"><?= h($g['icon']) ?></span>
                    <span>
                        <b class="block text-sm text-slate-900"><?= h($g['name']) ?></b>
                        <span class="text-[11px] text-slate-500"><?= h($g['note']) ?></span>
                    </span>
                    <span class="ml-auto text-pink-500 font-bold">›</span>
                </button>
                <?php endforeach; ?>
                <?php if (empty($gateways)): ?>
                <p class="text-sm text-slate-500 text-center py-4">No payment method is configured yet. Please contact support.</p>
                <?php endif; ?>
            </div>
            <p class="text-[10px] text-slate-400 text-center mt-4">🔒 You'll be redirected to the gateway's secure page.</p>
        </div>
    </div>

    <!-- hidden form for redirect gateways -->
    <form id="gwForm" method="post" action="pay-init.php" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="gw" id="gwField">
        <input type="hidden" name="plan_id" id="gwPlan">
        <input type="hidden" name="coupon" id="gwCoupon">
    </form>

    <script>
    let appliedCoupon = '';
    let currentPlan = '';
    let currentBtn = null;

    function applyCoupon() {
        const code = (document.getElementById('couponInput').value || '').trim().toUpperCase();
        const st = document.getElementById('couponStatus');
        if (!code) { st.className = 'text-xs mt-2 text-slate-400'; st.textContent = 'Enter a code first.'; return; }
        fetch('api.php?action=validate_coupon', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ coupon: code }) })
        .then(r => r.json()).then(d => {
            if (d.success) { appliedCoupon = d.code; st.className = 'text-xs mt-2 text-emerald-600'; st.textContent = '✅ ' + d.code + ' applied — you save ₹' + (d.discount_paise / 100) + ' (final price shown at checkout).'; }
            else { appliedCoupon = ''; st.className = 'text-xs mt-2 text-red-500'; st.textContent = d.error || 'Invalid coupon.'; }
        }).catch(() => { st.className = 'text-xs mt-2 text-red-500'; st.textContent = 'Could not check coupon.'; });
    }

    const GW_COUNT = <?= count($gateways) ?>;
    const ONLY_GW = <?= count($gateways) === 1 ? json_encode(array_key_first($gateways)) : 'null' ?>;

    function chooseGateway(planId, btn) {
        currentPlan = planId; currentBtn = btn;
        if (GW_COUNT === 0) { alert('No payment method configured. Please contact support.'); return; }
        if (GW_COUNT === 1) { startPay(ONLY_GW); return; }
        const m = document.getElementById('gwModal');
        m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeGw() { const m = document.getElementById('gwModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
    document.getElementById('gwModal').addEventListener('click', function(e){ if (e.target === this) closeGw(); });

    function startPay(gw) {
        closeGw();
        if (gw === 'razorpay') { initiateRazorpay(currentPlan, currentBtn); return; }
        document.getElementById('gwField').value = gw;
        document.getElementById('gwPlan').value = currentPlan;
        document.getElementById('gwCoupon').value = appliedCoupon;
        document.getElementById('gwForm').submit();
    }

    function initiateRazorpay(planId, btn) {
        if (btn) { btn.disabled = true; btn.textContent = 'Creating order...'; }

        fetch('api.php?action=create_razorpay_order', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ plan_id: planId, coupon: appliedCoupon })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert(data.error || 'Failed to create order.');
                if (btn) { btn.disabled = false; btn.textContent = 'Buy Now →'; }
                return;
            }

            const options = {
                key: '<?= h($razorpay_key) ?>',
                amount: data.amount,
                currency: 'INR',
                name: '<?= h(SITE_NAME) ?>',
                description: 'Page Credits',
                order_id: data.order_id,
                handler: function(response) {
                    fetch('api.php?action=verify_payment', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature: response.razorpay_signature,
                        })
                    })
                    .then(r => r.json())
                    .then(vdata => {
                        if (vdata.success) {
                            window.location.href = 'payment.php?status=success&msg=' + encodeURIComponent((vdata.credits_added || '') + ' credits added to your account');
                        } else {
                            alert('Payment verification failed. Contact support.');
                            window.location.reload();
                        }
                    });
                },
                prefill: {
                    name: '<?= h($_SESSION['user_name'] ?? '') ?>',
                    email: '<?= h($_SESSION['user_email'] ?? '') ?>',
                },
                theme: { color: '#ec4899' },
                modal: {
                    ondismiss: function() {
                        if (btn) { btn.disabled = false; btn.textContent = 'Buy Now →'; }
                    }
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();
        })
        .catch(() => {
            alert('Network error. Please try again.');
            if (btn) { btn.disabled = false; btn.textContent = 'Buy Now →'; }
        });
    }
    </script>
</body>
</html>
