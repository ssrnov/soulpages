<?php
require_once 'includes/functions.php';

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    redirect('login.php');
}

$error = '';
$success = '';

// Find user by verification token
$stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ? LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $error = 'Invalid or expired email verification link. Please check your inbox or request a new link.';
} else {
    // Mark as verified
    $upd = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
    if ($upd->execute([$user['id']])) {
        $success = 'Email verified successfully! Welcome to ' . SITE_NAME . ' 💖';
        
        // Auto-login the verified user
        regenerate_user_session();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_photo'] = $user['profile_photo'] ?? '';
    } else {
        $error = 'Failed to verify email. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Email Verification - <?= h(SITE_NAME) ?></title>
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
        .text-slate-400 {
            color: #475569 !important;
        }
    </style>
</head>
<body class="font-sans min-h-screen flex flex-col justify-center items-center px-4 style-light">

    <!-- Ambient glows -->
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-pink-500/5 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-1/3 right-1/4 w-80 h-80 bg-purple-500/5 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="sp-glass max-w-md w-full p-8 sm:p-10 text-center relative z-10 shadow-2xl">
        <?php if (!empty($success)): ?>
            <div class="text-5xl mb-6">🎉</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold font-heading text-white mb-2">Verified!</h1>
            <p class="text-sm text-pink-200/50 mb-6"><?= h($success) ?></p>
            
            <a href="dashboard.php" class="sp-btn-primary block w-full py-3.5 text-sm text-center">
                Go to Dashboard 🚀
            </a>
        <?php else: ?>
            <div class="text-5xl mb-6">❌</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold font-heading text-white mb-2">Verification Failed</h1>
            <p class="text-sm text-rose-400 mb-6"><?= h($error) ?></p>
            
            <a href="login.php" class="block w-full py-3 bg-white/5 border border-white/10 hover:bg-white/10 text-slate-300 font-bold rounded-2xl text-xs uppercase tracking-wider transition">
                Back to Login
            </a>
        <?php endif; ?>
    </div>

</body>
</html>
