<?php
require_once 'includes/functions.php';

// Handle referral code from URL in case they land on login page
$ref = trim($_GET['ref'] ?? '');
if (!empty($ref)) {
    setcookie('sp_ref', $ref, time() + (30 * 24 * 60 * 60), '/');
    $_SESSION['referral_code'] = $ref;
}

// If already logged in, go to dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$old_email = '';

// Check for session error (from old redirects)
if (!empty($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember_me']) ? 1 : 0;

        $old_email = $email;

        // Rate limiting: max 5 login attempts per minute per email
        if (is_rate_limited('login_' . md5($email), 5, 60)) {
            $error = 'Too many login attempts. Please try again after 60 seconds.';
        } elseif (empty($email) || empty($password)) {
            $error = 'Please enter your email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Find user by email (both user and admin roles check here, but we redirect admin if needed, or admin logins go through admin/login.php)
            // Wait, the spec says "Separate Admin Login Page /admin/login.php. Only admins can access: Users...".
            // So if they login as admin on the frontend login, should we deny or redirect them to dashboard? It is safer to let them log in, and redirect them appropriately.
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'No account found with this email. Please sign up first.';
            } elseif (empty($user['password'])) {
                $error = 'This account has no password set (possibly created via old Google oauth). Please use forgot password to set a password.';
            } elseif (!password_verify($password, $user['password'])) {
                $error = 'Incorrect password. Please try again.';
            } elseif (($user['status'] ?? 'active') === 'suspended') {
                $error = 'Your account has been suspended. Please contact support.';
            } else {
                // Secure session regeneration
                regenerate_user_session();

                // Login successful — set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_photo'] = $user['profile_photo'] ?? '';

                // Handle Remember Me cookie
                if ($remember) {
                    $token = $user['id'] . '|' . hash_hmac('sha256', $user['id'], 'loopr_secure_cookie_salt');
                    setcookie('loopr_remember', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                }

                // Claim guest-created pages from this browser session
                $session_id = session_id();
                $stmt_claim = $pdo->prepare("UPDATE pages SET user_id = ? WHERE user_id IS NULL AND guest_session_id = ?");
                $stmt_claim->execute([$user['id'], $session_id]);
                $claimed_count = $stmt_claim->rowCount();
                if ($claimed_count > 0) {
                    // Update free_pages_used since guest page is now claimed
                    $pdo->prepare("UPDATE users SET free_pages_used = free_pages_used + ? WHERE id = ?")
                        ->execute([$claimed_count, $user['id']]);
                }

                if (in_array($user['role'], ['admin', 'manager'], true)) {
                    redirect('admin/index.php');
                } else {
                    redirect('dashboard.php');
                }
            }
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

    <title>Login - SoulSync</title>
    <meta name="description" content="Sign in to SoulSync to create emotional webpages for your loved ones.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
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

        /* Force light background and high contrast text colors */
        body {
            background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important;
            background-attachment: fixed;
            color: #0f172a !important;
            min-height: 100vh;
        }

        /* Global Typography Contrast Fix */
        body, p, label, li, span:not(.sp-gradient-text):not(.text-transparent) {
            color: #334155 !important; /* slate-700 */
        }
        h1, h2, h3, h4, h5, h6 {
            color: #0f172a !important; /* slate-900 */
        }

        /* Buttons & Badges (White Text) */
        .sp-btn-primary, .sp-btn-primary *, button[type="submit"], button[type="submit"] *, .sp-btn-primary span {
            color: #ffffff !important;
        }

        /* Gradient & Transparent text preservation */
        .sp-gradient-text, .bg-clip-text, .text-transparent {
            color: transparent !important;
            -webkit-text-fill-color: transparent !important;
        }

        /* Interactive & Alert Text Color Overrides */
        .text-pink-500, .text-pink-650, .text-pink-600, .text-pink-400, .text-pink-300, .hover\:text-pink-500:hover, .hover\:text-pink-400:hover {
            color: #db2777 !important;
        }

        /* Glass card light */
        .sp-glass {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(236, 72, 153, 0.12) !important;
            border-radius: 24px;
            transition: all 0.3s ease;
        }
        .sp-glass:hover {
            border-color: rgba(236, 72, 153, 0.3) !important;
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.06), 0 20px 40px rgba(0,0,0,0.02) !important;
            transform: translateY(-3px);
        }

        /* Pink gradient text */
        .sp-gradient-text {
            background: linear-gradient(135deg, #be185d 0%, #db2777 40%, #e11d48 70%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Primary button */
        .sp-btn-primary {
            background: linear-gradient(135deg, #db2777 0%, #e11d48 100%) !important;
            color: white !important;
            font-weight: 700;
            border-radius: 16px;
            padding: 14px 32px;
            border: none;
            box-shadow: 0 8px 25px rgba(219,39,119,0.2) !important;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .sp-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(219,39,119,0.3) !important;
            opacity: 0.95;
        }

        /* Form elements */
        input[type="text"], input[type="number"], input[type="email"], input[type="password"], textarea, select {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }
        input::placeholder, textarea::placeholder {
            color: #94a3b8 !important;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #db2777 !important;
            box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15) !important;
        }
        .bg-slate-900\/40, .bg-slate-900, .bg-slate-950, .bg-slate-950\/40 {
            background-color: #ffffff !important;
        }
        .border-slate-800, .border-slate-850 {
            border-color: #cbd5e1 !important;
        }
    </style>
    <!-- No ads on the login screen (AdSense policy: no ads on navigation/behavioral screens) -->
</head>
<body style="background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%); color: #0f172a; min-height: 100vh;" class="flex items-center justify-center font-sans">

    <!-- Emoji Rain -->
    <?php render_emoji_rain(); ?>

    <!-- Ambient glow -->
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute top-1/4 left-1/3 w-72 h-72 bg-pink-500/8 rounded-full blur-[80px]"></div>
        <div class="absolute bottom-1/4 right-1/4 w-64 h-64 bg-purple-500/6 rounded-full blur-[70px]"></div>
    </div>

    <div class="w-full max-w-md mx-4 py-8 relative z-10">
        <!-- Login Card -->
        <div class="sp-glass p-8 sm:p-10">
            <div class="text-center mb-8">
                <a href="index.php" class="text-3xl font-extrabold font-heading sp-gradient-text"><?= h(SITE_NAME) ?></a>
                <p class="text-pink-200/50 text-sm mt-2">Welcome back 💌</p>
            </div>
            
            <!-- Error box -->
            <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-300 rounded-2xl p-4 mb-6 text-sm">
                ⚠️ <?= h($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block text-[10px] font-bold text-pink-200/40 mb-1.5 uppercase tracking-wider">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="you@example.com" value="<?= h($old_email) ?>"
                           class="w-full bg-white/5 border border-pink-500/20 focus:border-pink-500/60 text-white placeholder-pink-200/30 rounded-2xl px-4 py-3.5 outline-none transition text-sm">
                </div>

                <!-- Password -->
                <div class="mb-5">
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-[10px] font-bold text-pink-200/40 uppercase tracking-wider">Password</label>
                        <a href="forgot-password.php" class="text-[10px] text-pink-400 hover:underline">Forgot Password?</a>
                    </div>
                    <div class="relative">
                        <input type="password" id="password" name="password" required placeholder="Enter your password"
                               class="w-full bg-white/5 border border-pink-500/20 focus:border-pink-500/60 text-white placeholder-pink-200/30 rounded-2xl px-4 py-3.5 outline-none transition text-sm pr-12">
                        <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-350 transition text-sm" id="toggle-pw-btn">
                            👁️
                        </button>
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center gap-2 mb-6">
                    <input type="checkbox" id="remember_me" name="remember_me" class="rounded border-pink-500/20 bg-slate-900 text-pink-500 focus:ring-0 focus:ring-offset-0">
                    <label for="remember_me" class="text-[10px] text-pink-200/40 select-none cursor-pointer">Remember Me</label>
                </div>

                <button type="submit" class="sp-btn-primary w-full py-4 text-base">
                    Login to SoulSync 💖
                </button>
            </form>

            <?php
            $g_client_id = get_setting('google_client_id', GOOGLE_CLIENT_ID);
            if ($g_client_id && $g_client_id !== 'YOUR_GOOGLE_CLIENT_ID'):
            ?>
            <div class="flex items-center gap-3 my-5">
                <div class="flex-1 h-px bg-pink-500/10"></div>
                <span class="text-xs" style="color:#94a3b8 !important">or</span>
                <div class="flex-1 h-px bg-pink-500/10"></div>
            </div>
            <script src="https://accounts.google.com/gsi/client" async defer></script>
            <div id="google-btn-wrap" style="display:flex;justify-content:center;margin-top:4px"></div>
            <script>
            function handleGoogleSignIn(response) {
                fetch('auth/google-onetap.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'credential=' + encodeURIComponent(response.credential)
                })
                .then(r => r.text())
                .then(text => {
                    try { var data = JSON.parse(text); } catch(e) { alert('Server error: ' + text.substring(0,200)); return; }
                    if (data.success) window.location.href = data.redirect || 'index.php';
                    else alert(data.error || 'Google sign-in failed');
                })
                .catch(e => alert('Network error: ' + e.message));
            }
            window.addEventListener('load', function() {
                google.accounts.id.initialize({
                    client_id: '<?= h($g_client_id) ?>',
                    callback: handleGoogleSignIn
                });
                google.accounts.id.renderButton(document.getElementById('google-btn-wrap'), {
                    theme: 'outline', size: 'large', width: 360, text: 'continue_with', shape: 'pill'
                });
            });
            </script>
            <?php endif; ?>

            <p class="text-center text-sm text-pink-200/40 mt-6">
                Don't have an account? <a href="signup.php<?= !empty($_SESSION['referral_code']) ? '?ref=' . urlencode($_SESSION['referral_code']) : '' ?>" class="text-pink-400 font-semibold hover:text-pink-300">Sign Up Free</a>
            </p>
        </div>
    </div>

    <!-- Emoji Rain Script -->
    <script>


        function togglePassword() {
            const field = document.getElementById('password');
            const btn = document.getElementById('toggle-pw-btn');
            if (field.type === 'password') {
                field.type = 'text';
                btn.textContent = '🙈';
            } else {
                field.type = 'password';
                btn.textContent = '👁️';
            }
        }
    </script>
</body>
</html>
