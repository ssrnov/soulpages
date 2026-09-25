<?php
require_once 'includes/functions.php';

// Enforce login
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user = get_user_profile($user_id);

// If already verified, go to dashboard
if ($user && isset($user['email_verified']) && (int)$user['email_verified'] === 1) {
    redirect('dashboard.php');
}

$success_msg = '';
$error_msg = '';
ensure_otp_column($pdo);
ensure_verify_request_column($pdo);

// Handle "Request Admin to Verify"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_admin'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Session expired. Please try again.';
    } elseif (is_rate_limited('admin_verify_req_' . $user_id, 1, 600)) {
        $error_msg = 'You already sent a request. Our team will verify you shortly.';
    } else {
        try { $pdo->prepare("UPDATE users SET verify_requested_at = NOW() WHERE id = ?")->execute([$user_id]); } catch (\Throwable $e) {}
        try { notify_admin_verify_request($user); } catch (\Throwable $e) {}
        $success_msg = 'Request sent! Our team has been notified and will verify your account soon — you can log in again in a little while.';
    }
}

// Handle Resend Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Invalid CSRF token. Please try again.';
    } elseif (is_rate_limited('resend_verification_' . $user_id, 1, 60)) {
        $error_msg = 'Please wait 60 seconds before requesting another verification email.';
    } else {
        $new_token = bin2hex(random_bytes(25));
        $new_otp = make_otp();
        try {
            $pdo->prepare("UPDATE users SET verification_token = ?, verification_otp = ? WHERE id = ?")->execute([$new_token, $new_otp, $user_id]);
        } catch (\Throwable $e) {
            $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?")->execute([$new_token, $user_id]);
        }
        if (send_verification_email($user['email'], $new_token, $user['name'], $new_otp)) {
            $success_msg = 'Verification email resent! Please check your Inbox AND Spam/Junk folder.';
        } else {
            $error_msg = 'Failed to send email. Please try again in a moment.';
        }
    }
}

// Handle OTP code entry (alternative to the link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Session expired. Please try again.';
    } elseif (is_rate_limited('otp_try_' . $user_id, 6, 300)) {
        $error_msg = 'Too many attempts. Please wait a few minutes.';
    } else {
        $entered = preg_replace('/\D/', '', $_POST['otp_code'] ?? '');
        $real = '';
        try { $real = (string)$pdo->query("SELECT verification_otp FROM users WHERE id = " . (int)$user_id)->fetchColumn(); } catch (\Throwable $e) {}
        if ($entered !== '' && $real !== '' && hash_equals($real, $entered)) {
            $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?")->execute([$user_id]);
            try { $pdo->prepare("UPDATE users SET verification_otp = NULL WHERE id = ?")->execute([$user_id]); } catch (\Throwable $e) {}
            redirect('dashboard.php');
        } else {
            $error_msg = 'That code is incorrect. Please re-check the code in your email.';
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Email Verification Pending - <?= h(SITE_NAME) ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        /* ═══ SOULPAGES LIGHT PINK GLOBAL THEME ═══ */
        :root {
            --bg-darkest:    #ffffff;
            --bg-dark:       #fcfcfc;
            --bg-mid:        #f8fafc;
            --bg-card:       rgba(255, 20, 120, 0.01);
            --border-glow:   rgba(236, 72, 153, 0.1);
            --pink-hot:      #db2777;
            --pink-rose:     #e11d48;
            --pink-soft:     #f43f5e;
            --pink-pale:     #fdf2f8;
            --white-pure:    #0f172a;
            --white-soft:    #f8fafc;
            --text-primary:  #0f172a;
            --text-secondary:#475569;
            --text-muted:    #64748b;
        }

        body {
            background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important;
            background-attachment: fixed;
            color: var(--text-primary) !important;
            min-height: 100vh;
        }

        .sp-glass {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(236, 72, 153, 0.12) !important;
            border-radius: 24px;
        }

        .sp-gradient-text {
            background: linear-gradient(135deg, #be185d 0%, #db2777 40%, #e11d48 70%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sp-btn-primary {
            background: linear-gradient(135deg, #db2777 0%, #e11d48 100%) !important;
            color: white !important;
            font-weight: 700;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(219,39,119,0.2) !important;
            transition: all 0.3s ease;
        }
        .sp-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(219,39,119,0.3) !important;
            opacity: 0.95;
        }

        /* Text overrides */
        .text-white {
            color: #0f172a !important;
        }
        .text-slate-400, .text-pink-200\/50 {
            color: #475569 !important;
        }
    </style>
</head>
<body class="font-sans min-h-screen flex flex-col justify-center items-center px-4 style-light">

    <!-- Ambient glows -->
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-pink-500/5 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-1/3 right-1/4 w-80 h-80 bg-purple-500/5 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="sp-glass max-w-sm w-full p-8 text-center relative z-10 shadow-2xl">
        <div class="text-4xl mb-3">✉️</div>
        <h1 class="text-2xl font-extrabold font-heading text-slate-900 mb-1.5">Enter your code</h1>
        <p class="text-sm text-slate-500 mb-6">We sent a 6-digit code to<br><span class="font-semibold text-slate-700"><?= h($user['email']) ?></span></p>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 text-emerald-700 p-3 rounded-xl text-xs mb-5 font-semibold"><?= h($success_msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="bg-rose-50 text-rose-600 p-3 rounded-xl text-xs mb-5 font-semibold"><?= h($error_msg) ?></div>
        <?php endif; ?>

        <!-- code entry (primary) -->
        <form method="POST" action="verification-pending.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="text" name="otp_code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus
                   placeholder="0 0 0 0 0 0"
                   class="w-full bg-white border-2 border-slate-200 focus:border-pink-500 rounded-2xl px-4 py-4 text-center text-2xl font-mono font-bold tracking-[0.4em] text-slate-900 outline-none transition">
            <button type="submit" name="verify_otp" class="sp-btn-primary w-full py-3.5 text-sm mt-3">Verify &amp; Continue</button>
        </form>

        <!-- calm one-line spam hint -->
        <p class="text-xs text-slate-400 mt-4 leading-relaxed">📬 Can't find it? Check your <b class="text-slate-500">Spam / Junk</b> folder — the code is in the email's subject line too.</p>

        <!-- secondary actions as light links -->
        <div class="flex items-center justify-center gap-2 text-xs mt-5 text-slate-400 flex-wrap">
            <form method="POST" action="verification-pending.php" class="inline">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <button type="submit" name="resend" class="text-pink-600 font-semibold hover:underline">Resend code</button>
            </form>
            <span>·</span>
            <form method="POST" action="verification-pending.php" class="inline">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <button type="submit" name="request_admin" class="text-indigo-600 font-semibold hover:underline">Ask admin to verify</button>
            </form>
            <span>·</span>
            <a href="logout.php" class="text-slate-500 font-semibold hover:underline">Log out</a>
        </div>
    </div>

</body>
</html>
